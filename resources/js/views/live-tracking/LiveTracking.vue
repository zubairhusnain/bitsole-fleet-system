<template>
    <div class="live-tracking-view">
        <!-- Breadcrumbs removed per request -->

        <div class="map-wrap">
            <div v-if="isMobile && showMobileTopbar" class="mobile-topbar">
              <button class="mobile-btn btn btn-dark btn-sm" @click="toggleSidebar" aria-label="Toggle sidebar">
                <i class="bi bi-list"></i>
              </button>
              <button class="mobile-btn btn btn-dark btn-sm logout" @click="logout" aria-label="Logout">
                <i class="bi bi-box-arrow-right"></i>
              </button>
            </div>
            <button v-if="isMobile || !panelVisible" class="panel-toggle btn btn-light btn-sm" @click="panelVisible = !panelVisible" :aria-expanded="panelVisible.toString()" aria-controls="device-panel">
                 <i class="bi me-1" :class="panelVisible ? 'bi-x-lg' : 'bi-list'"></i>
                 <span class="toggle-title">Vehicle List</span>
             </button>
            <!-- Panel outside l-map for desktop -->
             <div v-if="!isMobile && panelVisible" class="panel-floating is-visible">
               <div class="panel-header">
                 <h3 class="panel-title">Search Vehicle</h3>
                 <label class="form-label small">Vehicle Name</label>
                 <input v-model="query" type="text" class="form-control panel-input" placeholder="eg. Transit Van" />
               </div>
               <div class="panel-body" @wheel.stop>
                 <div v-if="loading" class="text-muted small">Loading…</div>
                 <div v-else>
                   <div v-for="v in filtered" :key="deviceKey(v)" class="vehicle-card" @click.stop="focusVehicle(v)" @mousedown.stop @touchstart.stop @pointerdown.stop>
                     <div class="vehicle-avatar">
                       <img v-if="getImage(v) && !brokenImages[deviceKey(v)]" :src="getImage(v)" alt="" @error="brokenImages[deviceKey(v)] = true" />
                     </div>
                     <div class="vehicle-info">
                       <div class="vehicle-name-row">
                         <div class="vehicle-name">{{ deviceName(v) }}</div>
                         <div class="vehicle-status" :class="statusClass(v)">
                           <span v-if="statusIs(v, 'online')" class="icon-buffering"></span>
                           <span v-else class="icon-dot"></span>
                           <span class="status-text">{{ statusLabel(v) }}</span>
                         </div>
                       </div>
                       <div class="vehicle-meta">Vehicle ID {{ uniqueId(v) || '—' }}</div>
                     </div>
                   </div>
                   <div v-if="!filtered.length" class="text-muted small">No vehicles found.</div>
                 </div>
               </div>
             </div>
            <l-map v-if="showMap" id="liveMap" :zoom="zoom" :center="center" :options="mapOptions" @ready="onMapReady">
            <l-tile-layer :url="tileUrl" :attribution="tileAttribution" />
            <l-marker v-for="m in markerItems" :key="m.id" :lat-lng="[m.lat, m.lon]" :icon="carIcon" :ref="el => setMarkerRef(m.id, el)">
            <l-popup>
            <div class="popup-card" v-html="m.popup"></div>
            </l-popup>
            </l-marker>
            <!-- Panel rendered inside the map container (plain layer) -->
             <transition name="mobile-panel">
             <div v-if="isMobile && panelVisible" class="panel-floating">
              <div class="panel-header">
                <h3 class="panel-title">Search Vehicle</h3>
                <label class="form-label small">Vehicle Name</label>
                <input v-model="query" type="text" class="form-control panel-input" placeholder="eg. Transit Van" />
              </div>
              <div class="panel-body" @wheel.stop>
                <div v-if="loading" class="text-muted small">Loading…</div>
                <div v-else>
                  <div v-for="v in filtered" :key="deviceKey(v)" class="vehicle-card" @click.stop="focusVehicle(v)" @mousedown.stop @touchstart.stop @pointerdown.stop>
                    <div class="vehicle-avatar">
                      <img v-if="getImage(v) && !brokenImages[deviceKey(v)]" :src="getImage(v)" alt="" @error="brokenImages[deviceKey(v)] = true" />
                    </div>
                    <div class="vehicle-info">
                      <div class="vehicle-name-row">
                        <div class="vehicle-name">{{ deviceName(v) }}</div>
                        <div class="vehicle-status" :class="statusClass(v)">
                          <span v-if="statusIs(v, 'online')" class="icon-buffering"></span>
                          <span v-else class="icon-dot"></span>
                          <span class="status-text">{{ statusLabel(v) }}</span>
                        </div>
                      </div>
                      <div class="vehicle-meta">Vehicle ID {{ uniqueId(v) || '—' }}</div>
                    </div>
                  </div>
                  <div v-if="!filtered.length" class="text-muted small">No vehicles found.</div>
                </div>
              </div>
            </div>
            </transition>
            </l-map>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { getCurrentUser, clearAuthCache } from '../../auth';
import { LMap, LTileLayer, LMarker, LIcon, LPopup } from '@vue-leaflet/vue-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const map = ref(null);
const markerRefs = new Map();

const router = useRouter();

async function logout() {
    try { await axios.post('/web/auth/logout'); } catch {}
    clearAuthCache();
    router.push('/login');
}

function toggleSidebar() {
    const body = document.body;
    const isOpen = body.classList.contains('sidebar-open');
    if (isOpen) {
        body.classList.remove('sidebar-open');
        body.classList.add('sidebar-collapse');
    } else {
        body.classList.add('sidebar-open');
        body.classList.remove('sidebar-collapse');
    }
}

function onMapReady(mapObj) {
    map.value = mapObj;
    try { map.value.zoomControl.setPosition('bottomright'); } catch {}
}

function setMarkerRef(id, el) {
    try {
        const mk = el?.leafletObject ?? el;
        if (mk) markerRefs.set(id, mk);
        else markerRefs.delete(id);
    } catch {}
}


const showMap = ref(true);
const zoom = ref(4);
const center = ref([39.8283, -98.5795]);
const mapOptions = { zoomControl: true, preferCanvas: true };
const tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const tileAttribution = '&copy; OpenStreetMap contributors';
const fitDone = ref(false);
const loading = ref(false);
const error = ref('');
const vehicles = ref([]);
const query = ref('');
const brokenImages = reactive({});
let broadcastPing = null;
// Batch incoming position updates to reduce UI thrash
let pendingPositions = [];
let flushTimer = null;
const FLUSH_MS = 250;
function schedulePositionsMerge(list) {
    if (Array.isArray(list) && list.length) {
        pendingPositions.push(...list);
        if (!flushTimer) {
            flushTimer = setTimeout(() => {
                const batch = pendingPositions;
                pendingPositions = [];
                flushTimer = null;
                applyRealtimePositions(batch);
            }, FLUSH_MS);
        }
    }
}

// Live polling fallback to keep data changing without page refresh
let pollTimer = null;
let visibilityHandler = null;
const POLL_MS = 5000;
async function pollPositionsOnce() {
    try {
        const posRes = await axios.get('/web/live/positions/current').catch(() => ({ data: {} }));
        const positions = Array.isArray(posRes?.data?.positions) ? posRes.data.positions : [];
        if (positions.length) {
            schedulePositionsMerge(positions);
        }
    } catch {}
}
function startPositionsPolling() {
    stopPositionsPolling();
    pollTimer = setInterval(() => {
        pollPositionsOnce();
    }, POLL_MS);
}
function stopPositionsPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}
const panelVisible = ref(false);
const showMobileTopbar = ref(true);
const MOBILE_MAX = 576;
const isMobile = ref(false);
function updatePanelVisibilityForViewport() {
    const w = window?.innerWidth ?? 1024;
    isMobile.value = w <= MOBILE_MAX;
    if (w > MOBILE_MAX) {
        panelVisible.value = true;
    }
}

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
        // Only set status when payload explicitly provides tc_devices-style status
        if (p.status && ['online','offline','unknown'].includes(String(p.status).toLowerCase())) {
            existing.tcDevice.status = String(p.status).toLowerCase();
        }
        // Fallback: derive status from p.online or serverTime when p.status missing
        if (!existing.tcDevice.status) {
            if (typeof p.online === 'boolean') {
                existing.tcDevice.status = p.online ? 'online' : 'offline';
            } else if (p.serverTime) {
                const t = Date.parse(p.serverTime);
                if (!Number.isNaN(t)) {
                    const isRecent = (Date.now() - t) <= (60 * 60 * 1000);
                    existing.tcDevice.status = isRecent ? 'online' : 'offline';
                }
            }
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

const markerItems = computed(() => {
    return filtered.value
        .map(v => {
            const { lat, lon } = getPosition(v);
            return { id: deviceKey(v), lat, lon, popup: popupHtml(v) };
        })
        .filter(m => typeof m.lat === 'number' && typeof m.lon === 'number')
        .sort((a, b) => String(a.id).localeCompare(String(b.id)));
});

watch(
    markerItems,
    (list) => {
        if (map.value && !fitDone.value && list.length) {
            const bounds = L.latLngBounds(list.map(m => [m.lat, m.lon]));
            try { map.value.fitBounds(bounds.pad(0.2)); } catch { map.value.fitBounds(bounds); }
            fitDone.value = true;
        }
    },
    { flush: 'post' }
);

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
    const s = statusValue(v);
    if (s === 'online') return 'status-on';
    if (s === 'offline') return 'status-off';
    return 'status-unknown';
}

function statusValue(v) {
    const tc = v.tcDevice || v.tc_device || {};
    const rawStatus = typeof tc.status === 'string' ? tc.status.trim().toLowerCase() : '';
    if (['online','offline','unknown'].includes(rawStatus)) return rawStatus;
    const { raw } = getPosition(v);
    const online = raw?.attributes?.online;
    if (typeof online === 'boolean') return online ? 'online' : 'offline';
    return 'unknown';
}

function statusLabel(v) {
    const s = statusValue(v);
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : 'offline';
}


function statusIs(v, value) {
    return statusValue(v) === String(value).toLowerCase();
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
    iconUrl: '/images/markers/device-pin.svg',
    iconSize: [36, 48],
    iconAnchor: [18, 44],
    popupAnchor: [0, -38],
});

function popupHtml(v) {
    const { ignition, speed, address } = getPosition(v);
    const name = deviceName(v);
    const sp = speedKmh(speed);
    const ign = ignition === null ? 'Unknown' : ignition ? 'On' : 'Off';
    const locText = address || 'Coordinates available';
    const uniq = uniqueId(v) || '—';
    const lu = formatTime(lastUpdate(v));
    const sClass = statusClass(v);
    const sLabel = statusLabel(v);
    const isOnline = statusIs(v, 'online');
    return `
    <div class="popup-card">
      <div class="popup-title-row">
        <div class="popup-title">${name}</div>
        <div class="popup-status ${sClass}">
          ${isOnline ? '<span class="icon-buffering"></span>' : '<span class="icon-dot"></span>'}
          <span class="status-text">${sLabel}</span>
        </div>
      </div>
      <div class="popup-row"><span>Unique ID:</span> <strong>${uniq}</strong></div>
      <div class="popup-row"><span>Last Update:</span> <strong>${lu}</strong></div>
      <div class="popup-row"><span>Ignition:</span> <strong>${ign}</strong></div>
      <div class="popup-row"><span>Speed:</span> <strong>${sp ?? '-'} km/h</strong></div>
      <div class="popup-row"><span>Location:</span> <span>${locText}</span></div>
    </div>
  `;
}

function placeMarkers() {
    // Obsolete with vue-leaflet: markers render reactively via <l-marker>.
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
        // Trigger initial fit once markers are ready
        fitDone.value = false;
    }
}

function focusVehicle(v) {
    const { lat, lon } = getPosition(v);
    if (map.value && typeof lat === 'number' && typeof lon === 'number') {
        const z = typeof map.value.getZoom === 'function' ? Math.max(map.value.getZoom(), 8) : 8;
        try {
            if (typeof map.value.flyTo === 'function') {
                map.value.flyTo([lat, lon], z, { duration: 0.6 });
            } else {
                map.value.setView([lat, lon], z, { animate: true });
            }
        } catch {}
        const mk = markerRefs.get(deviceKey(v));
        try { mk?.openPopup?.(); } catch {}
    }
}

onMounted(() => {
    // Map is created declaratively via <l-map/>; load data and listeners
    fetchVehicles();

    // Initialize by viewport: show on desktop, keep hidden on mobile
    updatePanelVisibilityForViewport();
    try { window.addEventListener('resize', updatePanelVisibilityForViewport); } catch {}

    // Start polling as a fallback to sockets to keep data fresh
    startPositionsPolling();
    try {
        visibilityHandler = () => {
            if (document.hidden) {
                stopPositionsPolling();
            } else {
                startPositionsPolling();
            }
        };
        document.addEventListener('visibilitychange', visibilityHandler);
    } catch {}

    // Subscribe to WebSocket channel for live positions (per-user private channel)
    getCurrentUser().then((user) => {
        if (user?.id && window.echo) {
            window.echo.private(`positions.${user.id}`).listen('.positions.updated', (e) => {
                if (Array.isArray(e?.positions)) {
                    schedulePositionsMerge(e.positions);
                    // keep user viewport stable; no auto-fit on every update
                }
            });
        }
    }).catch(() => {});

    // Periodically trigger a broadcast to demo live updates (dev only)
    if (import.meta.env.DEV) {
        broadcastPing = setInterval(() => {
            axios.get('/web/live/positions/broadcast').catch(() => { });
        }, 5000);
    }
});

onBeforeUnmount(() => {
    if (map.value) map.value.remove();
    if (broadcastPing) clearInterval(broadcastPing);
    if (flushTimer) clearTimeout(flushTimer);
    if (pollTimer) clearInterval(pollTimer);
    try {
        if (visibilityHandler) {
            document.removeEventListener('visibilitychange', visibilityHandler);
            visibilityHandler = null;
        }
        window.removeEventListener('resize', updatePanelVisibilityForViewport);
    } catch {}
});
</script>

<style scoped>
.live-tracking-view {
    position: relative;
    margin-left: calc(-1 * var(--bs-gutter-x, .75rem));
    margin-right: calc(-1 * var(--bs-gutter-x, .75rem));
}

.map-wrap {
    position: relative;
    height: calc(100vh - 120px);
    min-height: 520px;
    border-radius: 0;
    overflow: hidden;
    margin-top: 0 !important;
    padding-top: 0 !important;
}

#liveMap {
    height: 100%;
    width: 100%;
}

/* LiveTracking-only: remove top margin and padding on main app container */
:global(.live-tracking-route .app-main) {
    margin-top: 0 !important;
    padding: 0 !important;
}

.panel-toggle {
    position: fixed;
    top: 10px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 3000;
    display: inline-block;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 6px 16px rgba(0,0,0,.15);
    color: #111;
    font-weight: 600;
    padding: 8px 14px;
}
.panel-toggle .bi { vertical-align: middle; }
.toggle-title { vertical-align: middle; }

.panel-floating {
    position: absolute;
    top: 16px;
    left: 16px;
    width: 360px;
    z-index: 4000;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
    transform: translateX(-110%);
    opacity: 0;
    pointer-events: none;
    transition: transform .25s ease, opacity .25s ease;
}
.panel-floating.is-visible {
    transform: translateX(0);
    opacity: 1;
    pointer-events: auto;
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
    position: relative; /* create stacking context for z-index */
    z-index: 4001; /* ensure whole card overlays within the panel */
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
    color: #2e7d32; /* green */
}

.status-off {
    color: #616161; /* gray */
}

.status-unknown {
    color: #757575; /* gray-500 */
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

.vehicle-name-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}
.vehicle-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 600;
}
.icon-buffering {
    width: 10px;
    height: 10px;
    border: 2px solid currentColor;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.icon-dot {
    width: 8px;
    height: 8px;
    background: currentColor;
    border-radius: 50%;
}
.status-text {
    line-height: 1;
}

/* Mobile-friendly adjustments for panel and map */
@media (max-width: 576px) {
    .map-wrap {
        height: 100vh;
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    /* Full-bleed map: stretch to viewport width inside padded container */
    .live-tracking-view .map-wrap {
        width: 100vw;
        margin-left: calc(-50vw + 50%);
        border-radius: 0;
    }
    /* Keep toggle at top-center on mobile as well */
    .panel-toggle {
        position: fixed;
        top: 10px;
        bottom: auto;
        left: 50%;
        transform: translateX(-50%);
        z-index: 3000;
        display: inline-block;
        border-radius: 12px;
        padding: 8px 14px;
        box-shadow: 0 6px 16px rgba(0,0,0,.15);
    }

    /* Top overlay on mobile: slide down from top, centered horizontally */
    .panel-floating {
        position: absolute; /* keep overlay inside map container */
        top: 120px;
        bottom: auto;
        left: 50%;
        right: auto;
        width: min(360px, 92vw);
        transform: translate(-50%, 0); /* final position; transition animates from below */
        border-radius: 12px; /* rounded corners */
        box-shadow: 0 8px 24px rgba(0,0,0,.12);
        opacity: 1; /* ensure visible after transition completes */
        pointer-events: auto; /* clickable after transition */
        z-index: 4000; /* keep above map controls */
    }

    .panel-floating .panel-body {
        max-height: 50vh;
        padding: 10px;
    }
    .vehicle-avatar {
        width: 48px;
        height: 48px;
    }
    .vehicle-card {
        gap: 10px;
        padding: 8px 10px;
    }
    .vehicle-name { font-size: 14px; }
    .vehicle-status { font-size: 11px; }

    :global(.live-tracking-route .app-header) { display: none !important; }
    :global(.live-tracking-route .app-footer) { display: none !important; }
    :global(.live-tracking-route .app-main .app-content .container-fluid) {
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Mobile overlay controls */
    .mobile-topbar {
        position: fixed;
        top: 8px;
        left: 8px;
        right: 8px;
        z-index: 1200;
        display: flex;
        justify-content: space-between;
        pointer-events: none;
    }
    .mobile-topbar .mobile-btn {
        pointer-events: auto;
        background: #111;
        color: #fff;
        border-radius: 12px;
        padding: 8px 10px;
        box-shadow: 0 6px 16px rgba(0,0,0,.15);
    }

    /* Transition for slight bottom-to-top reveal */
    .mobile-panel-enter-active,
    .mobile-panel-leave-active {
        transition: transform .18s ease, opacity .18s ease;
    }
    .mobile-panel-enter-from {
        transform: translate(-50%, 24px);
        opacity: 0;
    }
    .mobile-panel-enter-to {
        transform: translate(-50%, 0);
        opacity: 1;
    }
    .mobile-panel-leave-from {
        transform: translate(-50%, 0);
        opacity: 1;
    }
    .mobile-panel-leave-to {
        transform: translate(-50%, 24px);
        opacity: 0;
    }

    :global(.app-sidebar[data-v-27b1954b]) {
          z-index: 10000000 !important;
      }
}

/* Popup layout improvements */
.popup-card {
    max-width: min(320px, 85vw);
    box-sizing: border-box;
    font-size: 13px;
    line-height: 1.4;
    word-break: break-word;
}

.popup-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}

/* Leaflet popup tweaks (scoped deep selectors) */
#liveMap :deep(.leaflet-popup-content) {
    width: auto;
    margin: 10px 12px;
}
#liveMap :deep(.leaflet-popup-content-wrapper) {
    border-radius: 10px;
}

</style>
