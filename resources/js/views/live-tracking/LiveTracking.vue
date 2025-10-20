<template>
    <div class="live-tracking-view">
        <div class="app-content-header mb-2">
            <ol class="breadcrumb mb-0 small text-muted">
                <li class="breadcrumb-item">
                    <RouterLink to="/dashboard">Dashboard</RouterLink>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Live Tracking</li>
            </ol>
        </div>

        <div class="map-wrap card">
            <div class="panel-floating">
                <div class="panel-header">
                    <h3 class="panel-title">Search Vehicle</h3>
                    <label class="form-label small">Vehicle Name</label>
                    <input v-model="query" type="text" class="form-control panel-input" placeholder="eg. Transit Van" />
                </div>
                <div class="panel-body">
                    <div v-if="loading" class="text-muted small">Loading…</div>
                    <div v-else>
                        <div v-for="v in filtered" :key="deviceKey(v)" class="vehicle-card" @click="focusVehicle(v)">
                            <div class="vehicle-avatar">
                                <img v-if="getImage(v) && !brokenImages[deviceKey(v)]" :src="getImage(v)" alt="" @error="brokenImages[deviceKey(v)] = true" />
                            </div>
                            <div class="vehicle-info">
                                <span class="badge status-badge" :class="statusClass(v)">{{ statusLabel(v) }}</span>
                                <div class="vehicle-name">{{ deviceName(v) }}</div>
                                <div class="vehicle-meta">Vehicle ID {{ uniqueId(v) || '—' }}</div>
                            </div>
                        </div>
                        <div v-if="!filtered.length" class="text-muted small">No vehicles found.</div>
                    </div>
                </div>
            </div>
            <div id="liveMap" ref="mapEl"></div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { getCurrentUser } from '../../auth';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const mapEl = ref(null);
const map = ref(null);
const deviceLayer = ref(null);
const markersById = new Map();

const loading = ref(false);
const error = ref('');
const vehicles = ref([]);
const query = ref('');
const brokenImages = reactive({});
let broadcastPing = null;

function parseAttrs(val) {
    try { return typeof val === 'string' ? JSON.parse(val) : (val || {}); } catch { return {}; }
}

function trackingId(v) {
    return v.device_id ?? v.deviceId ?? (v.tcDevice?.id ?? v.tc_device?.id) ?? (typeof v.id === 'number' ? v.id : null);
}

function cryptoRandomId() {
    try { return crypto.randomUUID(); } catch { return Math.random().toString(36).slice(2); }
}

function deviceKey(v) {
    const tid = trackingId(v);
    return tid ?? cryptoRandomId();
}

function deviceName(v) {
    const n = v.name ?? (v.tcDevice?.name ?? v.tc_device?.name);
    return typeof n === 'string' && n.trim() ? n : 'Unknown';
}

// Merge realtime positions into the vehicles list (dedupe by tracking device id)
function applyRealtimePositions(list) {
    const base = new Map();
    for (const v of vehicles.value) {
        const key = trackingId(v);
        if (key != null) base.set(key, v);
    }
    list.forEach(p => {
        const id = p.id ?? p.deviceId ?? null; // positions payload uses tracking device id
        if (!id) return;
        const existing = base.get(id) || { device_id: id, name: p.name };
        existing.position = {
            latitude: p.latitude,
            longitude: p.longitude,
            speed: p.speed,
            address: p.address,
            attributes: { ignition: p.ignition, motion: p.motion ?? null, online: p.online ?? null },
        };
        existing.name = existing.name || p.name;
        // Attach lightweight tcDevice stub for display fields when missing
        if (!existing.tcDevice) {
            existing.tcDevice = { id, name: existing.name };
        }
        // Preserve uniqueId when provided by backend positions payloads
        if (p.uniqueId && !existing.tcDevice.uniqueId) {
            existing.tcDevice.uniqueId = p.uniqueId;
        }
        // Update lastUpdate from backend payload (tcDevice)
        if (p.lastUpdate) {
            existing.tcDevice.lastUpdate = p.lastUpdate;
        }
        // Update device status from backend payload
        if (p.status) {
            existing.tcDevice.status = p.status;
        }
        // Preserve device-level attributes for UI (image, meta)
        if (p.attributes && !existing.tcDevice.attributes) {
            existing.tcDevice.attributes = p.attributes;
        }
        base.set(id, existing);
    });
    vehicles.value = Array.from(base.values());
}

function getPosition(v) {
    const dev = v.tcDevice || v.tc_device || {};
    const pos = dev.position || dev.tcPosition || dev.tc_position || v.position || v.tcPosition || v.tc_position || v.positionData || {};
    const latRaw = pos.latitude ?? pos.lat ?? pos.y ?? null;
    const lonRaw = pos.longitude ?? pos.lon ?? pos.x ?? null;
    const toNumber = (val) => {
        const n = typeof val === 'string' ? parseFloat(val) : val;
        return Number.isFinite(n) ? n : null;
    };
    const lat = toNumber(latRaw);
    const lon = toNumber(lonRaw);
    const ignRaw = pos.attributes?.ignition ?? pos.ignition ?? null;
    let ignition = null;
    if (ignRaw !== null && ignRaw !== undefined) {
        const s = String(ignRaw).toLowerCase();
        ignition = s === 'on' || s === 'true' || s === '1' || ignRaw === true || ignRaw === 1 ? true
            : (s === 'off' || s === 'false' || s === '0' || ignRaw === false || ignRaw === 0 ? false : null);
    }
    const speedRaw = pos.speed ?? null;
    const speed = typeof speedRaw === 'string' ? parseFloat(speedRaw) : speedRaw; // Traccar speed usually in knots
    const address = pos.address || null;
    return { lat, lon, ignition, speed, address, raw: pos };
}

function hasLocation(v) {
    const { lat, lon } = getPosition(v);
    if (typeof lat === 'number' && typeof lon === 'number') return true;
    const dev = v.tcDevice || v.tc_device || {};
    const pid = v.positionId ?? v.positionid ?? dev.positionId ?? dev.positionid ?? null;
    return pid != null;
}

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = vehicles.value.filter(hasLocation);
    if (!q) return list;
    return list.filter(v => {
        const name = deviceName(v).toLowerCase();
        return name.includes(q) || String(uniqueId(v) || '').toLowerCase().includes(q);
    });
});

function getImage(v) {
    const tc = v.tcDevice || v.tc_device || {};
    const attrs = parseAttrs(tc.attributes);
    const photos = attrs?.photos;
    let candidate = '';

    if (Array.isArray(photos) && photos.length > 0) {
        candidate = photos.find(p => typeof p === 'string' && p.trim()) || '';
    } else {
        const alt = attrs?.photo || attrs?.image || (Array.isArray(attrs?.images) ? attrs.images[0] : '');
        candidate = typeof alt === 'string' ? alt : '';
    }

    if (!candidate) return '';
    const urlish = candidate.trim();
    return urlish.startsWith('http') ? urlish : `/storage/${urlish.replace(/^\/*/, '')}`;
}

function statusText(v) {
    const tc = v.tcDevice || v.tc_device || {};
    const pos = getPosition(v);
    const online = pos.raw?.attributes?.online;
    if (typeof tc.status === 'string' && tc.status) return tc.status;
    if (online === false) return 'Inactive';
    const status = v.status || (pos.ignition === true ? (pos.speed > 0 ? 'moving' : 'idle') : (pos.ignition === false ? 'stopped' : null));
    return status || 'Unknown';
}

function statusClass(v) {
    const { ignition, speed, raw } = getPosition(v);
    const online = raw?.attributes?.online;
    if (online === false) return 'status-off';
    if (ignition === true && speed > 0) return 'status-on';
    if (ignition === true && (!speed || speed === 0)) return 'status-off';
    if (ignition === false) return 'status-off';
    if (ignition === null) return 'status-unknown';
    if (typeof speed === 'number' && speed > 100) return 'status-critical';
    return 'status-unknown';
}

function statusLabel(v) {
    const { ignition, speed, raw } = getPosition(v);
    const online = raw?.attributes?.online;
    if (online === false) return 'Inactive';
    if (ignition === true && speed > 0) return 'Moving';
    if (ignition === true && (!speed || speed === 0)) return 'Idle';
    if (ignition === false) return 'Stopped';
    if (typeof speed === 'number' && speed > 100) return 'Critical';
    return 'Unknown';
}

function formatTime(val) {
    if (!val) return '—';
    try {
        const d = new Date(val);
        if (!isNaN(d.getTime())) return d.toLocaleString();
        const n = Number(val);
        if (Number.isFinite(n)) return new Date(n).toLocaleString();
        return String(val);
    } catch { return String(val); }
}

function lastUpdate(v) {
    const tc = v.tcDevice || v.tc_device || {};
    const pos = getPosition(v).raw || {};
    return tc.lastUpdate || tc.lastupdate || pos.serverTime || pos.deviceTime || pos.fixTime || null;
}

function uniqueId(v) {
    const tc = v.tcDevice || v.tc_device || {};
    const attrs = parseAttrs(tc.attributes);
    return v.uniqueId || v.uniqueid || tc.uniqueId || tc.uniqueid || attrs.uniqueId || attrs.uniqueid || null;
}

function speedKmh(speed) {
    if (typeof speed !== 'number') return null;
    return Math.round(speed * 1.852);
}

const carIcon = L.icon({
    iconUrl:
        'data:image/svg+xml;utf8,' +
        encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="48" height="24" viewBox="0 0 48 24">
      <rect x="4" y="6" width="40" height="12" rx="6" fill="#e53935"/>
      <rect x="12" y="4" width="16" height="6" rx="3" fill="#c62828"/>
      <circle cx="12" cy="18" r="3.5" fill="#263238"/>
      <circle cx="36" cy="18" r="3.5" fill="#263238"/>
      <rect x="18" y="8" width="14" height="4" fill="#000" opacity="0.15"/>
    </svg>`),
    iconSize: [48, 24],
    iconAnchor: [24, 12],
    popupAnchor: [0, -10],
});

function popupHtml(v) {
    const { ignition, speed, address } = getPosition(v);
    const name = deviceName(v);
    const sp = speedKmh(speed);
    const ign = ignition === null ? 'Unknown' : ignition ? 'On' : 'Off';
    const locText = address || 'Coordinates available';
    const uniq = uniqueId(v) || '—';
    const lu = formatTime(lastUpdate(v));
    const stat = statusText(v);
    return `
    <div class="popup-card">
      <div class="popup-title">${name}</div>
      <div class="popup-row"><span>Unique ID:</span> <strong>${uniq}</strong></div>
      <div class="popup-row"><span>Status:</span> <strong>${stat}</strong></div>
      <div class="popup-row"><span>Last Update:</span> <strong>${lu}</strong></div>
      <div class="popup-row"><span>Ignition:</span> <strong>${ign}</strong></div>
      <div class="popup-row"><span>Speed:</span> <strong>${sp ?? '-'} km/h</strong></div>
      <div class="popup-row"><span>Location:</span> <span>${locText}</span></div>
    </div>
  `;
}

function placeMarkers(list) {
    if (!deviceLayer.value) return;
    deviceLayer.value.clearLayers();
    markersById.clear();
    const latLngs = [];
    list.forEach(v => {
        const { lat, lon } = getPosition(v);
        if (typeof lat === 'number' && typeof lon === 'number') {
            const m = L.marker([lat, lon], { icon: carIcon });
            m.bindPopup(popupHtml(v), { autoClose: false, closeOnClick: true });
            m.addTo(deviceLayer.value);
            markersById.set(deviceKey(v), m);
            latLngs.push([lat, lon]);
        }
    });
    if (latLngs.length) {
        const group = L.featureGroup(latLngs.map(ll => L.marker(ll)));
        map.value.fitBounds(group.getBounds().pad(0.2));
    }
}

async function fetchVehicles() {
    loading.value = true;
    error.value = '';
    try {
        const res = await axios.get('/web/vehicles').catch(() => ({ data: [] }));
        const data = res?.data ?? [];
        const list = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []);
        vehicles.value = list || [];

        // HTTP fallback: fetch current positions and merge into vehicles
        const posRes = await axios.get('/web/live/positions/current').catch(() => ({ data: {} }));
        const positions = Array.isArray(posRes?.data?.positions) ? posRes.data.positions : [];
        if (positions.length) {
            applyRealtimePositions(positions);
        }
    } catch (e) {
        error.value = 'Failed to load vehicles for map';
        console.error(e);
        vehicles.value = [];
    } finally {
        loading.value = false;
        placeMarkers(filtered.value);
    }
}

function focusVehicle(v) {
    const key = deviceKey(v);
    const m = markersById.get(key);
    if (m && map.value) {
        map.value.setView(m.getLatLng(), Math.max(map.value.getZoom(), 8), { animate: true });
        m.openPopup();
    }
}

onMounted(() => {
    if (!mapEl.value) return;
    map.value = L.map(mapEl.value, { zoomControl: true, preferCanvas: true }).setView([39.8283, -98.5795], 4);
    // Move zoom control to top right
    try { map.value.zoomControl.setPosition('topright'); } catch { }
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map.value);
    deviceLayer.value = L.layerGroup().addTo(map.value);
    fetchVehicles();

    // Subscribe to WebSocket channel for live positions (per-user private channel)
    getCurrentUser().then((user) => {
        if (user?.id && window.echo) {
            window.echo.private(`positions.${user.id}`).listen('.positions.updated', (e) => {
                if (Array.isArray(e?.positions)) {
                    applyRealtimePositions(e.positions);
                    placeMarkers(filtered.value);
                }
            });
        }
    }).catch(() => {});

    // Periodically trigger a broadcast to demo live updates
    broadcastPing = setInterval(() => {
        axios.get('/web/live/positions/broadcast').catch(() => { });
    }, 5000);
});

onBeforeUnmount(() => {
    if (map.value) map.value.remove();
    if (broadcastPing) clearInterval(broadcastPing);
});
</script>

<style scoped>
.live-tracking-view {
    position: relative;
}

.map-wrap {
    position: relative;
    height: calc(100vh - 180px);
    min-height: 480px;
    border-radius: 12px;
    overflow: hidden;
}

#liveMap {
    height: 100%;
    width: 100%;
}

.panel-floating {
    position: absolute;
    top: 16px;
    left: 16px;
    width: 360px;
    z-index: 1000;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}

.panel-floating .panel-header {
    padding: 14px 16px 10px 16px;
    border-bottom: 1px solid #eee;
}

.panel-title {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.panel-input {
    border-radius: 10px;
}

.panel-floating .panel-body {
    max-height: 60vh;
    overflow: auto;
    padding: 12px;
}

.vehicle-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    cursor: pointer;
}

.vehicle-card:hover {
    background: rgba(0, 0, 0, .04);
}

.vehicle-avatar {
    width: 56px;
    height: 56px;
    border-radius: 12px;
    background: #f4f4f4;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
}

.vehicle-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.vehicle-info {
    display: flex;
    flex-direction: column;
}

.vehicle-name {
    font-weight: 600;
}

.vehicle-meta {
    color: #6c757d;
    font-size: 12px;
}

.status-on {
    color: #2e7d32;
}

.status-off {
    color: #616161;
}

.status-critical {
    color: #c62828;
}

.status-unknown {
    color: #757575;
}

.status-badge {
    border-radius: 12px;
    background: #e8f5e9;
    color: #2e7d32;
    padding: 4px 8px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 6px;
}

.status-badge.status-off {
    background: #f5f5f5;
    color: #616161;
}

.status-badge.status-critical {
    background: #ffebee;
    color: #c62828;
}

.status-badge.status-unknown {
    background: #f5f5f5;
    color: #757575;
}

.popup-title {
    font-weight: 600;
    margin-bottom: 4px;
}

.popup-row {
    display: flex;
    gap: 6px;
}

.popup-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f4f4f4;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.popup-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>
