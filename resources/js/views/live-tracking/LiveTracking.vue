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
            <!-- Panel outside map for desktop -->
             <div v-if="!isMobile && panelVisible" class="panel-floating is-visible">
               <div class="panel-header">
                 <h3 class="panel-title">Search Vehicle</h3>
                 <label class="form-label small">Vehicle Name</label>
                 <input v-model="query" type="text" class="form-control panel-input" placeholder="eg. Transit Van" />
               </div>
               <div class="panel-body" @wheel.stop>
                 <div v-if="loading" class="text-muted small">Loading…</div>
                 <div v-else>
                 <div v-for="v in filtered" :key="deviceKey(v)" :class="['vehicle-card', { 'is-selected': selectedId === deviceKey(v) }]" @click.stop="focusVehicle(v)" @mousedown.stop @touchstart.stop @pointerdown.stop>
                    <div class="vehicle-avatar">
                      <img v-if="getImage(v) && !brokenImages[deviceKey(v)]" :src="getImage(v)" alt="" @error="brokenImages[deviceKey(v)] = true" />
                    </div>
                    <div class="vehicle-info">
                      <div class="vehicle-name-row">
                        <div class="vehicle-name">{{ deviceName(v) }}</div>
                        <img :src="getIcon(v)" class="status-icon" alt="" />
                      </div>
                      <div class="vehicle-meta-lines">
                        <div class="meta-line">
                          <span class="meta-label">Device:</span>
                          <span class="meta-value">{{ getVehicleMeta(v).model || '—' }}</span>
                          <span class="icon-dot" :class="statusDotClass(v)"></span>
                        </div>
                        <div class="meta-line">
                          <span class="meta-label">Number Plate:</span>
                          <span class="meta-value">{{ getVehicleMeta(v).plate || '—' }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                   <div v-if="!filtered.length" class="text-muted small">No vehicles found.</div>
                 </div>
               </div>
             </div>
            <div v-if="showMap" class="map-inner" style="height: 100%; width: 100%; position: relative;">
              <div v-if="isTestingMode" class="map-provider-switcher" style="position: absolute; bottom: 30px !important; right: 70px !important; z-index: 3000; background: white; padding: 6px 10px; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.3);">
                <div class="btn-group btn-group-sm" role="group" aria-label="Map provider">
                  <button type="button" class="btn btn-outline-primary" :class="{ active: mapProvider === 'leaflet' }" @click="mapProvider = 'leaflet'">Leaflet</button>
                  <button type="button" class="btn btn-outline-primary" :class="{ active: mapProvider === 'google' }" @click="mapProvider = 'google'">Google</button>
                </div>
              </div>
              <l-map v-if="mapProvider === 'leaflet'" id="liveMap" :zoom="zoom" :center="center" :options="mapOptions" @ready="onMapReady">
                <l-tile-layer :url="tileUrl" :attribution="tileAttribution" />
                <l-marker
                  v-for="m in markerItems"
                  :key="m.id"
                  :lat-lng="[m.lat, m.lon]"
                  :icon="getLeafletIcon(m)"
                  :ref="el => setMarkerRef(m.id, el)"
                  :z-index-offset="m.isMoving ? 1000 : 0"
                >
                  <l-popup>
                    <div class="popup-card" v-html="m.popup"></div>
                  </l-popup>
                </l-marker>
              </l-map>
              <GoogleMap
                v-else
                :center="center"
                :zoom="zoom"
                :markers="markerItems"
                :selected-id="selectedId"
                @ready="onGoogleMapReady"
                @error="onGoogleMapError"
              />
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
                      <div
                        v-for="v in filtered"
                        :key="deviceKey(v)"
                        :class="['vehicle-card', { 'is-selected': selectedId === deviceKey(v) }]"
                        @click.stop="focusVehicle(v)"
                        @mousedown.stop
                        @touchstart.stop
                        @pointerdown.stop
                      >
                        <div class="vehicle-avatar">
                          <img
                            v-if="getImage(v) && !brokenImages[deviceKey(v)]"
                            :src="getImage(v)"
                            alt=""
                            @error="brokenImages[deviceKey(v)] = true"
                          />
                        </div>
                        <div class="vehicle-info">
                          <div class="vehicle-name-row">
                            <div class="vehicle-name">{{ deviceName(v) }}</div>
                            <img :src="getIcon(v)" class="status-icon" alt="" />
                          </div>
                          <div class="vehicle-meta-lines">
                            <div class="meta-line">
                              <span class="meta-label">Device:</span>
                              <span class="meta-value">{{ getVehicleMeta(v).model || '—' }}</span>
                              <span class="icon-dot" :class="statusDotClass(v)"></span>
                            </div>
                            <div class="meta-line">
                              <span class="meta-label">Number Plate:</span>
                              <span class="meta-value">{{ getVehicleMeta(v).plate || '—' }}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div v-if="!filtered.length" class="text-muted small">No vehicles found.</div>
                    </div>
                  </div>
                </div>
              </transition>
            </div>
        </div>
    </div>
</template>


<script setup>
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount, inject } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { getCurrentUser, clearAuthCache } from '../../auth';
import { LMap, LTileLayer, LMarker, LPopup, LCircle } from '@vue-leaflet/vue-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { formatTelemetry } from '../../utils/telemetry';
import { formatDateTime } from '../../utils/datetime';
import GoogleMap from '../../components/GoogleMap.vue';


const map = ref(null);
const googleMap = ref(null);
const markerRefs = new Map();

const router = useRouter();
const isTestingMode = inject('isTestingMode', ref(false));

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

function onGoogleMapReady(mapObj) {
    googleMap.value = mapObj;
}

function onGoogleMapError() {
    mapProvider.value = 'leaflet';
}

function setMarkerRef(id, el) {
    try {
        const mk = el?.leafletObject ?? el;
        if (mk) {
            markerRefs.set(id, mk);
            if (mapProvider.value === 'leaflet' && String(id) === String(selectedId.value)) {
                setTimeout(() => {
                    try { mk.openPopup(); } catch {}
                }, 200);
            }
        }
        else markerRefs.delete(id);
    } catch {}
}


const showMap = ref(true);
const mapProvider = ref('leaflet');
const zoom = ref(4);
const center = ref([39.8283, -98.5795]);
const selectedId = ref(null);
const mapOptions = { zoomControl: true, preferCanvas: true, attributionControl: false };
const tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const tileAttribution = '';
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
let socketsSeen = false;
let fallbackStartTimer = null;
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
function armPollingFallback() {
    if (fallbackStartTimer) clearTimeout(fallbackStartTimer);
    fallbackStartTimer = setTimeout(() => {
        if (!socketsSeen) startPositionsPolling();
    }, 8000);
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

// Animated marker display positions (decoupled from raw device positions)
const displayPositions = reactive({}); // { [id]: { lat, lon } }
const animations = new Map(); // { id -> { raf } }
const ANIM_MS = 1000; // default animation duration per update
const JUMP_CUTOFF_METERS = 1500; // skip animation for large jumps

function setDisplayPos(id, lat, lon) {
    if (typeof id === 'undefined' || id === null) return;
    if (typeof lat !== 'number' || typeof lon !== 'number') return;
    displayPositions[id] = { lat, lon };
}

function easeInOutCubic(t) {
    return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
}

function distanceMeters(a, b) {
    if (!a || !b || typeof a.lat !== 'number' || typeof a.lon !== 'number' || typeof b.lat !== 'number' || typeof b.lon !== 'number') return NaN;
    const R = 6371000;
    const toRad = Math.PI / 180;
    const dLat = (b.lat - a.lat) * toRad;
    const dLon = (b.lon - a.lon) * toRad;
    const lat1 = a.lat * toRad;
    const lat2 = b.lat * toRad;
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(h));
}

function animateMarkerTo(id, toLat, toLon, duration = ANIM_MS) {
    if (typeof toLat !== 'number' || typeof toLon !== 'number') return;
    const current = displayPositions[id];
    const from = current && typeof current.lat === 'number' && typeof current.lon === 'number' ? current : null;
    if (!from) {
        // No current display state; snap to target to establish baseline
        setDisplayPos(id, toLat, toLon);
        return;
    }
    const dist = distanceMeters(from, { lat: toLat, lon: toLon });
    if (!Number.isFinite(dist) || dist > JUMP_CUTOFF_METERS) {
        // Large jump or invalid; snap without animating
        setDisplayPos(id, toLat, toLon);
        return;
    }
    const startLat = from.lat;
    const startLon = from.lon;
    const dLat = toLat - startLat;
    const dLon = toLon - startLon;
    const startTime = performance?.now ? performance.now() : Date.now();
    const prev = animations.get(id);
    if (prev?.raf) {
        try { cancelAnimationFrame(prev.raf); } catch {}
    }
    const anim = { raf: 0 };
    const step = (now) => {
        const tRaw = Math.min(1, ((now ?? (performance?.now ? performance.now() : Date.now())) - startTime) / duration);
        const t = tRaw < 0 ? 0 : tRaw;
        const e = easeInOutCubic(t);
        setDisplayPos(id, startLat + dLat * e, startLon + dLon * e);
        if (t < 1) {
            anim.raf = requestAnimationFrame(step);
        } else {
            animations.delete(id);
        }
    };
    animations.set(id, anim);
    anim.raf = requestAnimationFrame(step);
}

function trackingId(v) {
    return v.device_id ?? v.deviceId ?? (typeof v.id === 'number' ? v.id : null);
}

function cryptoRandomId() {
    try { return crypto.randomUUID(); } catch { return Math.random().toString(36).slice(2); }
}

function deviceKey(v) {
    const tid = trackingId(v);
    return tid ?? cryptoRandomId();
}

function deviceName(v) {
    const n = v.name;
    return typeof n === 'string' && n.trim() ? n : 'Unknown';
}

// Merge realtime positions into the vehicles list (dedupe by tracking device id)
function applyRealtimePositions(list) {
    const base = new Map();
    const before = new Map();
    for (const v of vehicles.value) {
        const key = trackingId(v);
        if (key != null) {
            base.set(key, v);
            before.set(key, v);
        }
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
            attributes: {
                ...(existing.position?.attributes || {}),
                ignition: p.ignition,
                motion: p.motion ?? null,
                online: p.online ?? null,
                ...(p.attributes || {}),
            },
        };
        existing.name = existing.name || p.name;
        // Preserve uniqueId when provided by backend positions payloads
        if (p.uniqueId && !existing.uniqueId) {
            existing.uniqueId = p.uniqueId;
        }
        // Update lastUpdate from backend payload
        if (p.lastUpdate) {
            existing.lastUpdate = p.lastUpdate;
        }
        // Derive status from payload
        if (p.status && ['online','offline','unknown'].includes(String(p.status).toLowerCase())) {
            existing.status = String(p.status).toLowerCase();
        } else if (typeof p.online === 'boolean') {
            existing.status = p.online ? 'online' : 'offline';
        } else if (p.serverTime) {
            const t = Date.parse(p.serverTime);
            if (!Number.isNaN(t)) {
                const isRecent = (Date.now() - t) <= (60 * 60 * 1000);
                existing.status = isRecent ? 'online' : 'offline';
            }
        }
        // Preserve device-level attributes for UI (image, meta)
        if (p.attributes && !existing.attributes) {
            existing.attributes = p.attributes;
        }
        base.set(id, existing);
    });
    vehicles.value = Array.from(base.values());

    // Kick off marker animations towards new positions for updated devices
    list.forEach(p => {
        const id = p.id ?? p.deviceId ?? null;
        const toLatRaw = p.latitude ?? p.lat ?? null;
        const toLonRaw = p.longitude ?? p.lon ?? null;
        const toLat = typeof toLatRaw === 'string' ? parseFloat(toLatRaw) : toLatRaw;
        const toLon = typeof toLonRaw === 'string' ? parseFloat(toLonRaw) : toLonRaw;
        if (!id || !Number.isFinite(toLat) || !Number.isFinite(toLon)) return;
        if (!displayPositions[id]) {
            const prevV = before.get(id);
            const prevPos = prevV ? getPosition(prevV) : null;
            if (prevPos && typeof prevPos.lat === 'number' && typeof prevPos.lon === 'number') {
                setDisplayPos(id, prevPos.lat, prevPos.lon);
            }
        }
        animateMarkerTo(id, toLat, toLon);
    });
}

function getPosition(v) {
    const pos = v.position || v.positionData || {};
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
    const courseRaw = pos.course ?? null;
    const course = typeof courseRaw === 'string' ? parseFloat(courseRaw) : courseRaw;
    const address = pos.address || null;
    return { lat, lon, ignition, speed, address, course, raw: pos };
}

function hasLocation(v) {
    const { lat, lon } = getPosition(v);
    if (typeof lat === 'number' && typeof lon === 'number') return true;
    const pid = v.positionId ?? v.positionid ?? null;
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
            const { lat, lon, speed, course } = getPosition(v);
            const id = deviceKey(v);
            const disp = displayPositions[id];
            const dlat = typeof disp?.lat === 'number' ? disp.lat : lat;
            const dlon = typeof disp?.lon === 'number' ? disp.lon : lon;
            const sp = speedKmh(speed) || 0;
            const isMoving = sp > 0;
            const iconUrl = isSelected(id) ? '/images/markers/focus-marker.svg' : '/images/markers/device-pin.png';
            return { id, lat: dlat, lon: dlon, popup: popupHtml(v), iconUrl, course: course || 0, isMoving };
        })
        .filter(m => typeof m.lat === 'number' && typeof m.lon === 'number')
        .sort((a, b) => String(a.id).localeCompare(String(b.id)));
});

function getLeafletIcon(m) {
     if (m.isMoving) {
         return L.divIcon({
             className: 'arrow-marker-icon',
             html: `<img src="/images/markers/arrow.svg" style="transform: rotate(${m.course}deg); width: 24px; height: 24px; display: block;" alt="arrow" />`,
             iconSize: [24, 24],
             iconAnchor: [12, 12],
             popupAnchor: [0, -12]
         });
     }
     if (isSelected(m.id)) return focusIcon;
     return carIcon;
 }

const selectedMarker = computed(() => {
    if (!selectedId.value) return null;
    return markerItems.value.find(m => String(m.id) === String(selectedId.value)) || null;
});

watch(
    markerItems,
    (list) => {
        if (!fitDone.value && list.length && !selectedId.value) {
            if (mapProvider.value === 'leaflet' && map.value) {
                const bounds = L.latLngBounds(list.map(m => [m.lat, m.lon]));
                try { map.value.fitBounds(bounds.pad(0.2)); } catch { map.value.fitBounds(bounds); }
                fitDone.value = true;
            } else if (mapProvider.value === 'google' && googleMap.value && window.google && window.google.maps && window.google.maps.LatLngBounds) {
                const bounds = new window.google.maps.LatLngBounds();
                list.forEach(m => {
                    const lat = Number(m.lat);
                    const lon = Number(m.lon);
                    if (Number.isFinite(lat) && Number.isFinite(lon)) {
                        bounds.extend({ lat, lng: lon });
                    }
                });
                try { googleMap.value.fitBounds(bounds); } catch {}
                fitDone.value = true;
            }
        }
    },
    { flush: 'post' }
);

// Keep selected vehicle centered while it moves
watch(
    selectedMarker,
    (m) => {
        if (!m) return;
        center.value = [m.lat, m.lon];
        let current;
        if (mapProvider.value === 'leaflet') {
            current = typeof map.value?.getZoom === 'function' ? map.value.getZoom() : zoom.value;
        } else if (mapProvider.value === 'google') {
            current = typeof googleMap.value?.getZoom === 'function' ? googleMap.value.getZoom() : zoom.value;
        } else {
            current = zoom.value;
        }
        zoom.value = Math.max(current || 0, 15);
    }
);

function getImage(v) {
    // Merge attributes: Traccar Device attributes < Vehicle attributes
    const tcDeviceAttrs = parseAttrs(v.tc_device?.attributes || v.tcDevice?.attributes);
    const vehicleAttrs = parseAttrs(v.attributes);
    const mergedAttrs = { ...tcDeviceAttrs, ...vehicleAttrs };

    const toPath = (it) => {
        if (!it && it !== 0) return '';
        if (Array.isArray(it)) return it.map(toPath).filter(Boolean);
        if (typeof it === 'string') {
            const s = it.trim();
            if (!s) return '';
            if ((s.startsWith('[') && s.endsWith(']')) || (s.startsWith('{') && s.endsWith('}'))) {
                try {
                    const parsed = JSON.parse(s);
                    return toPath(parsed);
                } catch { /* fall through */ }
            }
            return s;
        }
        if (typeof it === 'number') return String(it);
        if (typeof it === 'object') {
            const cand = it.url ?? it.path ?? it.src ?? it.image ?? it.photo;
            return typeof cand === 'string' ? cand.trim() : '';
        }
        return '';
    };

    const pickAttr = (keys) => {
        for (const k of keys) {
            if (mergedAttrs[k] != null && mergedAttrs[k] !== '') return mergedAttrs[k];
        }
        return null;
    };

    const photoUrl = (p) => {
        if (!p && p !== 0) return '';
        const raw = String(p).trim();
        if (!raw) return '';
        if (raw.startsWith('http') || raw.startsWith('data:')) return raw;
        if (raw.startsWith('/')) return raw;
        if (raw.startsWith('storage/')) return `/${raw}`;
        if (raw.startsWith('public/')) return `/${raw.replace(/^public\//, 'storage/')}`;
        return `/storage/${raw.replace(/^\/*/, '')}`;
    };

    const out = [];
    const arrLike = pickAttr(['photos', 'images']);
    const arrResolved = toPath(arrLike);
    if (Array.isArray(arrResolved)) out.push(...arrResolved);
    else if (typeof arrResolved === 'string' && arrResolved) out.push(arrResolved);

    const single = toPath(pickAttr(['photo', 'image', 'vehiclePhoto', 'vehicleImage']));
    if (Array.isArray(single)) out.push(...single);
    else if (typeof single === 'string' && single) out.push(single);

    const uniq = Array.from(new Set(out.filter(v => typeof v === 'string' && v.trim() !== '')));
    return uniq.length > 0 ? photoUrl(uniq[0]) : '';
}

function statusText(v) {
    const pos = getPosition(v);
    const online = pos.raw?.attributes?.online;
    if (typeof v.status === 'string' && v.status) return v.status;
    if (online === false) return 'Inactive';
    const status = v.status || (pos.ignition === true ? (pos.speed > 0 ? 'moving' : 'idle') : (pos.ignition === false ? 'stopped' : null));
    return status || 'Unknown';
}

function getVehicleMeta(v) {
    const tc = v.tc_device ?? v.tcDevice ?? {};
    const tcAttrs = parseAttrs(tc.attributes);
    const vehicleAttrs = parseAttrs(v.attributes);
    const attrs = { ...tcAttrs, ...vehicleAttrs };
    const brand = v.brand ?? attrs.brand ?? attrs.make ?? attrs.brandName ?? '';
    const model = v.trackerModel ?? tc.trackerModel ?? attrs.trackerModel ?? '';
    const plate =
        attrs.plate
        || attrs.plateNumber
        || attrs.plate_number
        || attrs.numberPlate
        || attrs.number_plate
        || attrs.licensePlate
        || attrs.registration
        || attrs.regNumber
        || attrs.vehicleNumber
        || attrs.vehicleNo
        || attrs.plateNo
        || '';
    return { brand, model, plate };
}

function getActivity(v) {
    const { ignition, speed } = getPosition(v);
    const speedVal = speedKmh(speed) || 0;
    const isIgnOn = ignition === true;

    if (!isIgnOn) return { label: 'Stopped', class: 'text-danger' };
    if (speedVal > 0) return { label: 'Moving', class: 'text-success' };
    return { label: 'Idle', class: 'text-warning' };
}

function getIcon(v) {
    const { label } = getActivity(v);
    if (label === 'Moving') return '/images/moving_car.png';
    if (label === 'Stopped') return '/images/stop_car.png';
    return '/images/idle_car.png';
}

function statusClass(v) {
    const s = statusValue(v);
    if (s === 'online') return 'status-on';
    if (s === 'offline') return 'status-off';
    return 'status-unknown';
}

function statusDotClass(v) {
    const s = statusValue(v);
    if (s === 'online') return 'dot-online';
    if (s === 'offline') return 'dot-offline';
    return 'dot-offline';
}

function statusValue(v) {
    const rawStatus = typeof v.status === 'string' ? v.status.trim().toLowerCase() : '';
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

// Extract fuel value from position attributes
function fuelDisplay(v) {
    const pos = getPosition(v).raw || {};
    const model = v.model || (v.tc_device?.model ?? v.tcDevice?.model) || null;
  const devRaw = (v.tc_device?.attributes ?? v.tcDevice?.attributes ?? v.attributes) || {};
  const devAttrs = parseAttrs(devRaw);
  const configuredFuelAttr = devAttrs?.fuelAttr || devAttrs?.fuel_attribute || null;
  const capKeys = ['fuelTankCapacity','FuelTankCapacity','fueltankcapacity','fuel_capacity','fuelCapacity','tankCapacity','fuel_tank_capacity'];
  let cap = null;
  for (const k of capKeys) {
    const val = devAttrs?.[k];
    if (val !== undefined && val !== null && val !== '') { cap = val; break; }
  }
  const capNum = (typeof cap === 'string') ? parseFloat(cap) : (typeof cap === 'number' ? cap : null);
  const posAttrs = parseAttrs(pos.attributes);
  const mergedAttrs = { ...devAttrs, ...posAttrs };
  const keys = ['fuelLevel','fuel_percent','fuelpercentage','fuelPercent','fuelPercent','fuelLiter','fuelLiters','FuelLiters','fuel','io89','89','io48','48','io84','84','io67','67','io68','68','io69','69','io240','240','io241','241','io242','242','io243','243','fuelRaw','analog1','analog2','analog3','adc1','adc2','adc3'];
  let has = false;
  for (const k of keys) { const v = posAttrs?.[k]; if (v !== undefined && v !== null && v !== '') { has = true; break; } }
  const tel = formatTelemetry(posAttrs, { protocol: pos.protocol, model, capacity: (has ? capNum : null), preferNamedOdometer: true, fuelAttr: configuredFuelAttr });
    if (!tel.fuel) return null;
    const liters = tel.fuel.liters;
    const percent = tel.fuel.percent;
    if (liters != null && percent != null) return `${liters} L (${percent}%)`;
    if (liters != null) return `${liters} L`;
    if (percent != null) return `${percent}%`;
    return tel.fuel.display ?? null;
}

// Extract odometer from position attributes and format in km
function odometerDisplay(v) {
    const pos = getPosition(v).raw || {};
    const model = v.model || (v.tc_device?.model ?? v.tcDevice?.model) || null;

    // Merge attributes: Tracker < Vehicle < Position
    const trackerAttrs = parseAttrs(v.tc_device?.attributes ?? v.tcDevice?.attributes);
    const vehicleAttrs = parseAttrs(v.attributes);
    const posAttrs = parseAttrs(pos.attributes);
    // User feedback: odometer value is in position attribute. Prioritize position attributes.
    const mergedAttrs = { ...trackerAttrs, ...vehicleAttrs, ...posAttrs };
    const configuredOdometerAttr = vehicleAttrs.odometerAttr || vehicleAttrs.odometer_attribute || trackerAttrs.odometerAttr || trackerAttrs.odometer_attribute || null;

    // Pass protocol as null to prevent formatTelemetry from assuming 'odometer' key is in meters for Teltonika.
    // This aligns with Detail page behavior where the value (e.g. 118,213) is treated as km.
    const tel = formatTelemetry(mergedAttrs, { protocol: null, model, preferNamedOdometer: true, odometerAttr: configuredOdometerAttr });
    return tel?.odometer?.display ?? null;
}


function statusIs(v, value) {
    return statusValue(v) === String(value).toLowerCase();
}

function formatTime(val) {
    return formatDateTime(val);
}

function lastUpdate(v) {
    const pos = getPosition(v).raw || {};
    // Prefer explicit device-level lastUpdate from the object, then position timestamps
    return v.lastUpdate || v.lastupdate || pos.serverTime || pos.deviceTime || pos.fixTime || null;
}

function uniqueId(v) {
    const rawAttrs = v.attributes || v.tc_device?.attributes || v.tcDevice?.attributes;
    const attrs = parseAttrs(rawAttrs);
    return v.uniqueId || v.uniqueid || v.tc_device?.uniqueId || v.tc_device?.uniqueid || v.tcDevice?.uniqueId || v.tcDevice?.uniqueid || attrs.uniqueId || attrs.uniqueid || null;
}

function speedKmh(speed) {
    if (typeof speed !== 'number') return null;
    return Math.round(speed * 1.852);
}

const carIcon = L.icon({
    iconUrl: '/images/markers/device-pin.png',
    iconSize: [36, 48],
    iconAnchor: [18, 44],
    popupAnchor: [0, -38],
});

const focusIcon = L.icon({
    iconUrl: '/images/markers/focus-marker.svg',
    iconSize: [30, 42],
    iconAnchor: [18, 44],
    popupAnchor: [0, -38],
});

function isSelected(id) {
    return String(id) === String(selectedId.value);
}

function popupHtml(v) {
    const { ignition, speed, address, lat, lon } = getPosition(v);
    const name = deviceName(v);
    const sp = speedKmh(speed);
    const ign = ignition === null ? 'Unknown' : ignition ? 'On' : 'Off';
    const hasCoords = typeof lat === 'number' && typeof lon === 'number';
    const locText = address || (hasCoords ? `<a href="https://www.google.com/maps?q=${lat},${lon}" target="_blank" class="text-primary text-decoration-underline">Live Tracking</a>` : 'Coordinates available');
    const uniq = uniqueId(v) || '—';
    const lu = formatTime(lastUpdate(v));
    const sClass = statusClass(v);
    const sLabel = statusLabel(v);
    const isOnline = statusIs(v, 'online');
    const fuel = fuelDisplay(v);
    const odo = odometerDisplay(v);
    const id = trackingId(v);
    const detailUrl = typeof id === 'number' || typeof id === 'string' ? `/vehicles/${id}` : null;
    return `
    <div class="popup-card" style="box-sizing:border-box; font-size:13px; line-height:1.4; word-break:break-word;">
      <div class="popup-title-row" style="margin:0 0 8px 0;">
        <div class="popup-title" style="font-weight:700; font-size:14px; margin-bottom:2px;">${name}</div>
        <div class="popup-status ${sClass}" style="display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:600; line-height:1;">
          ${isOnline ? '<span class="icon-buffering"></span>' : '<span class="icon-dot"></span>'}
          <span class="status-text">${sLabel}</span>
        </div>
      </div>
      <div class="popup-row" style="display:flex;gap:6px;"><span>Unique ID:</span> <strong>${uniq}</strong></div>
      <div class="popup-row" style="display:flex;gap:6px;"><span>Last Update:</span> <strong>${lu}</strong></div>
      <div class="popup-row" style="display:flex;gap:6px;"><span>Ignition:</span> <strong>${ign}</strong></div>
      <div class="popup-row" style="display:flex;gap:6px;"><span>Speed:</span> <strong>${sp ?? '-'} km/h</strong></div>
      <div class="popup-row" style="display:flex;gap:6px;"><span>Odometer:</span> <strong>${odo ?? '—'}</strong></div>
      <div class="popup-row" style="display:flex;gap:6px;"><span>Fuel:</span> <strong>${fuel ?? '—'}</strong></div>
      <div class="popup-row" style="display:flex;gap:6px;"><span>Location:</span> <span>${locText}</span></div>
      ${detailUrl ? `<div class="popup-row"><a href="${detailUrl}" class="text-primary text-decoration-underline">View Details</a></div>` : ''}
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
    const id = deviceKey(v);
    if (typeof id !== 'undefined' && id !== null) selectedId.value = id;
    if (typeof lat === 'number' && typeof lon === 'number') {
        fitDone.value = true;
        const desiredZoom = 15;
        let currentZoom = zoom.value;
        if (mapProvider.value === 'leaflet' && map.value && typeof map.value.getZoom === 'function') {
            currentZoom = map.value.getZoom();
        } else if (mapProvider.value === 'google' && googleMap.value && typeof googleMap.value.getZoom === 'function') {
            currentZoom = googleMap.value.getZoom();
        }
        const z = Math.max(currentZoom || 0, desiredZoom);
        center.value = [lat, lon];
        zoom.value = z;
        if (mapProvider.value === 'leaflet' && map.value) {
            try {
                if (typeof map.value.setView === 'function') {
                    map.value.setView([lat, lon], z, { animate: true });
                }
            } catch {}
            const mk = markerRefs.get(id);
            try { mk?.openPopup?.(); } catch {}
        }
    }
}

// When switching providers, reset fit and re-open selected popup on the new map
watch(
    mapProvider,
    async (prov) => {
        fitDone.value = false;
        // Allow new map to render its markers first
        try { await nextTick(); } catch {}
        if (!selectedId.value) return;

        // Trigger click again on selected vehicle after delay
        const v = vehicles.value.find(veh => String(deviceKey(veh)) === String(selectedId.value));
        if (v) {
            setTimeout(() => {
                focusVehicle(v);
            }, 200);
        }
    }
);

onMounted(() => {
    // Preload icons to avoid delay on first click
    const img = new Image();
    img.src = '/images/markers/focus-marker.svg';

    // Map is created declaratively via <l-map/>; load data and listeners
    fetchVehicles();

    // Initialize by viewport: show on desktop, keep hidden on mobile
    updatePanelVisibilityForViewport();
    try { window.addEventListener('resize', updatePanelVisibilityForViewport); } catch {}

    // Start polling only if sockets don’t deliver updates shortly
    armPollingFallback();
    try {
        visibilityHandler = () => {
            if (document.hidden) {
                stopPositionsPolling();
            } else {
                if (!socketsSeen) {
                    startPositionsPolling();
                } else {
                    stopPositionsPolling();
                }
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
                    socketsSeen = true;
                    stopPositionsPolling();
                    if (fallbackStartTimer) { clearTimeout(fallbackStartTimer); fallbackStartTimer = null; }
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
    if (broadcastPing) clearInterval(broadcastPing);
    if (flushTimer) clearTimeout(flushTimer);
    if (pollTimer) clearInterval(pollTimer);
    if (fallbackStartTimer) clearTimeout(fallbackStartTimer);
    try {
        if (visibilityHandler) {
            document.removeEventListener('visibilitychange', visibilityHandler);
            visibilityHandler = null;
        }
        window.removeEventListener('resize', updatePanelVisibilityForViewport);
    } catch {}
    try {
        animations.forEach(a => { if (a?.raf) cancelAnimationFrame(a.raf); });
        animations.clear();
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
    padding: 10px 42px 10px 12px;
    border-radius: 10px;
    cursor: pointer;
    position: relative; /* create stacking context for z-index */
    z-index: 4001; /* ensure whole card overlays within the panel */
}

.vehicle-right {
    margin-left: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vehicle-card:hover {
    background: rgba(0, 0, 0, .04);
}

.vehicle-card.is-selected {
    background: rgba(33, 150, 243, 0.18);
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

.vehicle-meta-lines {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.meta-line {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}
.meta-label {
    color: #6c757d;
}
.meta-value {
    color: #111;
    font-weight: 500;
}

.vehicle-name {
    font-weight: 600;
    padding-right:10px;
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

.dot-online {
    color: #16a34a; /* green */
}

.dot-offline {
    color: #9ca3af; /* gray */
}
.status-text {
    line-height: 1;
}

.status-icon {
    width: 36px;
    height: auto;
    object-fit: contain;
    position: absolute;
    top: 18px;
    right: 12px;
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

    :global(.app-sidebar) {
           position: relative !important;
           z-index: 10000000 !important;
       }
}

/* Popup layout improvements */
:global(.popup-card) {
    max-width: min(320px, 85vw);
    box-sizing: border-box;
    font-size: 13px;
    line-height: 1.4;
    word-break: break-word;
}

:global(.popup-title-row) {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
    width: 90% !important;
    max-width: 90% !important;
    margin-right: auto;
}

:global(.popup-title) {
    white-space: normal !important;
    word-break: break-word !important;
}

/* Leaflet popup tweaks (scoped deep selectors) */
#liveMap :deep(.leaflet-popup-content) {
    width: auto;
    margin: 10px 12px;
}
#liveMap :deep(.leaflet-popup-content-wrapper) {
    border-radius: 10px;
}

/* Force close button to top right and ensure visibility */
#liveMap :deep(.leaflet-popup-close-button) {
    top: 6px !important;
    right: 6px !important;
    width: 24px !important;
    height: 24px !important;
    font-size: 20px !important;
    z-index: 1000 !important;
    display: flex !important;
    align-items: center;
    justify-content: center;
    color: #666 !important;
}

/* Slightly raise Leaflet zoom control on LiveTracking */
#liveMap :deep(.leaflet-top .leaflet-control-zoom) {
    margin-top: 2px;
}

/* Google Maps InfoWindow adjustments to align content with close icon */
:global(.gm-style .gm-style-iw-c) {
    padding: 0 !important;
    border-radius: 8px !important;
}
:global(.gm-style .gm-style-iw-d) {
    overflow: hidden !important;
    padding: 12px 15px 12px 12px !important; /* Right padding to accommodate close button */
    max-height: none !important;
    margin-top:-44px;
}
:global(.gm-style .gm-style-iw .popup-title-row) {
    margin-top: 0 !important;
}
:global(.gm-style .gm-ui-hover-effect) {
    top: 0px !important; /* Vertically center with title text (approx) */
    right: 0px !important;
    opacity: 0.6;
}

</style>
