<template>
  <div class="card border rounded-3 shadow-0 h-100">
    <div class="card-body p-0">
      <div class="row g-0 h-100">
        <!-- Left Sidebar: Day List & Timeline -->
        <div class="col-12 col-lg-4 border-end d-flex flex-column" style="height: 75vh; max-height: 800px;">
          <!-- List Header -->
          <div class="d-flex justify-content-between align-items-center p-3 bg-dark text-white rounded-top-start">
              <div class="fw-bold">Date</div>
              <div class="fw-bold">Distance</div>
          </div>
 
          <div class="overflow-auto custom-scrollbar flex-grow-1">
            <div class="list-group list-group-flush">
              <template v-for="day in displayedRows" :key="day.key || (String(day.date || '') + '_' + String(day.deviceId || ''))">
                <!-- Day Header -->
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3"
                     :class="{
                        'bg-light': activeDay !== day,
                        'bg-primary-subtle text-primary border-start border-4 border-primary': activeDay === day
                     }"
                     @click="selectDay(day)"
                     role="button"
                     style="cursor: pointer;">
                  <div>
                    <div class="fw-bold">{{ cleanDate(getDayDate(day)) }}</div>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <div class="small fw-bold" :class="activeDay === day ? 'text-primary' : 'text-dark'">{{ getDayDistance(day) }}</div>
                  </div>
                </div>


                <!-- Day Details (Timeline) -->
                <div class="bg-white">
                  <!-- Summary Box -->
                  <div class="p-3 m-3 rounded-3 bg-info-subtle text-dark">
                    <div class="fw-bold mb-2 small text-uppercase text-primary-emphasis">Summary for {{ cleanDate(getSummaryDate(day)) }}</div>
                    <div class="row g-2">
                      <div class="col-6">
                        <div class="text-muted small" style="font-size: 0.75rem;">Total Distance</div>
                        <div class="fw-bold">{{ getSummaryDistance(day) }}</div>
                      </div>
                      <div class="col-6">
                        <div class="text-muted small" style="font-size: 0.75rem;">Total Duration</div>
                        <div class="fw-bold">{{ getSummaryDuration(day) }}</div>
                      </div>
                      <div class="col-6">
                        <div class="text-muted small" style="font-size: 0.75rem;">Total Idling</div>
                        <div class="fw-bold">{{ getSummaryIdle(day) }}</div>
                      </div>
                      <div class="col-6">
                        <div class="text-muted small" style="font-size: 0.75rem;">Behaviour</div>
                        <div class="fw-bold text-danger">{{ getSummaryBehaviour(day) }}</div>
                      </div>
                    </div>
                  </div>

                  <!-- Timeline -->
                  <div class="px-3 pb-3 position-relative">
                    <div class="timeline-line"></div>

                    <div v-for="(item, idx) in (day.timeline || [])" :key="idx" class="d-flex mb-3 position-relative z-1">
                      <template v-if="!item.hidden">
                      <!-- Icon/Dot Column -->
                      <div class="d-flex flex-column align-items-center me-3" style="width: 24px;">
                        <div v-if="item.type === 'start'" class="rounded-circle bg-primary border border-2 border-white shadow-sm" style="width: 16px; height: 16px; margin-top: 4px;"></div>
                        <div v-else-if="item.type === 'end'" class="rounded-circle bg-danger border border-2 border-white shadow-sm" style="width: 16px; height: 16px; margin-top: 4px;"></div>
                        <div v-else-if="item.type === 'alert'" class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center shadow-sm" style="width: 24px; height: 24px;">
                           <i class="bi bi-exclamation-triangle-fill" style="font-size: 12px;"></i>
                        </div>
                        <div v-else-if="item.type === 'stop'" class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center shadow-sm" style="width: 20px; height: 20px;">
                           <i class="bi bi-pause-fill" style="font-size: 12px;"></i>
                        </div>
                      </div>

                      <!-- Content Column -->
                      <div class="flex-grow-1 cursor-pointer" @click="seekToTime(day, item.time_sort)">
                        <div class="d-flex justify-content-between align-items-start">
                           <div>
                              <div class="d-flex align-items-center gap-2 mb-1">
                                  <div class="fw-bold small" :class="getTypeColor(item.type)">{{ item.time }}</div>
                              </div>

                              <div class="text-muted small mb-1" style="font-size: 0.8rem; line-height: 1.2;">{{ item.location }}</div>

                              <div class="d-flex flex-wrap gap-2 mt-1">
                                <span v-if="item.dist" class="badge bg-white text-primary border d-flex align-items-center gap-1 fw-normal shadow-sm">
                                    <i class="bi bi-bezier2"></i> {{ item.dist }}
                                </span>
                                <span v-if="item.dur" class="badge bg-white text-dark border d-flex align-items-center gap-1 fw-normal shadow-sm">
                                    <i class="bi bi-clock"></i> {{ item.dur }}
                                </span>
                                <span v-if="item.alert" class="badge bg-white text-danger border d-flex align-items-center gap-1 fw-normal shadow-sm">
                                    <i class="bi bi-exclamation-triangle"></i> {{ item.alert }}
                                </span>
                              </div>
                           </div>
                        </div>
                      </div>
                      </template>
                    </div>
                  </div>
                </div>
              </template>
              <div v-if="displayedRows.length === 0" class="text-center p-5 text-muted">
                  <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                  No data available
              </div>
            </div>
          </div>
        </div>

        <!-- Right Side: Map -->
        <div class="col-12 col-lg-8 position-relative bg-light" style="height: 75vh; max-height: 800px;">
          <div ref="mapEl" class="h-100 w-100 z-0"></div>

          <!-- Playback Controls Overlay -->
          <div v-if="activeDay && activeDay.route && activeDay.route.length > 0"
               class="position-absolute top-0 start-50 translate-middle-x mt-3 z-3 bg-white p-1 rounded-pill shadow-sm d-flex align-items-center gap-2 border">

            <button class="btn btn-sm btn-light rounded-pill px-3 fw-bold d-flex align-items-center" @click="restartPlayback" title="Restart">
              <i class="bi bi-arrow-counterclockwise me-2"></i> Restart
            </button>

            <div class="vr my-1"></div>

            <div class="d-flex align-items-center gap-1 px-1">
                <button class="btn btn-sm btn-light rounded-circle" @click="stepBackward" title="Step Back">
                   <i class="bi bi-skip-backward-fill"></i>
                </button>

                <button class="btn btn-primary btn-sm rounded-circle shadow-sm d-flex align-items-center justify-content-center" @click="togglePlay" style="width: 32px; height: 32px;">
                  <i class="bi" :class="isPlaying ? 'bi-pause-fill' : 'bi-play-fill'"></i>
                </button>

                <button class="btn btn-sm btn-light rounded-circle" @click="stepForward" title="Step Forward">
                   <i class="bi bi-skip-forward-fill"></i>
                </button>
            </div>

            <div class="vr my-1"></div>

            <div class="bg-light rounded-pill p-1 d-flex">
              <button class="btn btn-sm rounded-pill px-3 border-0"
                      :class="playbackSpeed === 10 ? 'bg-white shadow-sm fw-bold text-dark' : 'text-muted'"
                      @click="setSpeed(10)">Slow</button>
              <button class="btn btn-sm rounded-pill px-3 border-0"
                      :class="playbackSpeed === 200 ? 'bg-white shadow-sm fw-bold text-dark' : 'text-muted'"
                      @click="setSpeed(200)">Fast</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix marker icons
try {
  delete L.Icon.Default.prototype._getIconUrl;
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).toString(),
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).toString(),
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).toString(),
  });
} catch {}

const props = defineProps({
  rowsDailyBreakdown: {
    type: Array,
    required: true
  }
});

const displayedRows = computed(() => {
    return props.rowsDailyBreakdown;
});

const mapEl = ref(null);
let map = null;
let polyline = null;
let playbackMarker = null;
let animationFrameId = null;
let markers = [];

const isPlaying = ref(false);
const playbackSpeed = ref(200); // ms per step
const playbackIndex = ref(0);
const activeDay = ref(null);

onMounted(() => {
  initMap();
  if (displayedRows.value.length > 0) {
      // Load the first day on map by default, without toggling (since they default to open)
      activeDay.value = displayedRows.value[0];
      loadDayOnMap(activeDay.value);
  }
});

onUnmounted(() => {
  if (map) {
    map.remove();
    map = null;
  }
  stopPlayback();
});

// Watch for prop changes to update list if real data comes in
watch(() => props.rowsDailyBreakdown, (newVal) => {
    if (newVal.length > 0) {
        // If data changes, load the first one on map
        activeDay.value = newVal[0];
        loadDayOnMap(activeDay.value);
    } else {
        activeDay.value = null;
        clearMapLayers();
    }
}, { deep: true });

function initMap() {
  if (mapEl.value && !map) {
    map = L.map(mapEl.value).setView([3.1412, 101.6865], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap'
    }).addTo(map);
  }
}

function cleanDate(d) {
    if (!d) return '';
    return d.replace(' - ', ' | ');
}

function getDayDate(day) {
    return day && day.date ? day.date : '';
}

function getDayDistance(day) {
    if (!day) return '';
    return day.distance || day.totalDistance || (day.summary && day.summary.dist) || '';
}

function getSummary(day) {
    return day && day.summary ? day.summary : {};
}

function getSummaryDate(day) {
    const s = getSummary(day);
    return s.date || getDayDate(day);
}

function getSummaryDistance(day) {
    const s = getSummary(day);
    return s.dist || (day && (day.totalDistance || day.distance)) || '';
}

function getSummaryDuration(day) {
    const s = getSummary(day);
    return s.dur || (day && day.totalDuration) || '';
}

function getSummaryIdle(day) {
    const s = getSummary(day);
    return s.idle || (day && day.totalIdle) || '';
}

function getSummaryBehaviour(day) {
    const s = getSummary(day);
    if (s.behav) return s.behav;
    const t = day && day.timeline ? day.timeline : null;
    const computed = computeBehaviour(t);
    return computed || '-';
}

function computeBehaviour(timeline) {
    if (!Array.isArray(timeline)) return '';
    let sv = 0;
    let ha = 0;
    let hb = 0;
    const re = /(\d+)\s*(SV|HA|HB)\b/g;
    for (const item of timeline) {
        const alert = item && item.alert ? String(item.alert) : '';
        re.lastIndex = 0;
        let match;
        while ((match = re.exec(alert)) !== null) {
            const n = Number(match[1]) || 0;
            const code = match[2];
            if (code === 'SV') sv += n;
            else if (code === 'HA') ha += n;
            else if (code === 'HB') hb += n;
        }
    }
    const parts = [];
    if (sv > 0) parts.push(`${sv} SV`);
    if (ha > 0) parts.push(`${ha} HA`);
    if (hb > 0) parts.push(`${hb} HB`);
    return parts.join(', ');
}

function getTypeColor(type) {
    switch(type) {
        case 'start': return 'text-primary';
        case 'end': return 'text-danger';
        case 'alert': return 'text-warning';
        case 'stop': return 'text-secondary';
        default: return 'text-dark';
    }
}

function selectDay(day) {
    if (activeDay.value === day) {
        return;
    }
    activeDay.value = day;
    loadDayOnMap(day);
}

function clearMapLayers() {
    if (!map) return;
    if (polyline) map.removeLayer(polyline);
    if (playbackMarker) map.removeLayer(playbackMarker);
    markers.forEach(m => map.removeLayer(m));
    markers = [];
}

function loadDayOnMap(day) {
    clearMapLayers();
    if (!map || !day.route || day.route.length === 0) return;

    // Draw route
    const latlngs = day.route.map(pt => [pt[0], pt[1]]);
    polyline = L.polyline(latlngs, { color: 'blue', weight: 4 }).addTo(map);
    map.fitBounds(polyline.getBounds(), { padding: [50, 50] });

    // Add markers for timeline events
    if (day.timeline) {
        day.timeline.forEach(item => {
            if (item.lat && item.lon) {
                let color = 'blue';
                if (item.type === 'end') color = 'red';
                if (item.type === 'alert') color = 'orange';
                if (item.type === 'stop') color = 'gray';

                const marker = L.circleMarker([item.lat, item.lon], {
                    radius: 6,
                    fillColor: color,
                    color: '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(map);

                marker.bindPopup(`<b>${item.time}</b><br>${item.location}<br>${item.type.toUpperCase()}`);
                markers.push(marker);
            }
        });
    }

    // Initialize playback marker at start
    playbackIndex.value = 0;
    const startPt = day.route[0];
    playbackMarker = L.marker([startPt[0], startPt[1]]).addTo(map);
}

function seekToTime(day, timeSort) {
    if (!day || !Array.isArray(day.route) || day.route.length === 0) return;
    if (!timeSort) {
        restartPlayback();
        return;
    }

    const targetMs = Number(timeSort) * 1000;
    let bestIndex = 0;
    let bestDiff = Infinity;

    for (let i = 0; i < day.route.length; i++) {
        const pt = day.route[i];
        const t = Array.isArray(pt) ? Number(pt[2]) : NaN;
        if (!Number.isFinite(t)) continue;
        const diff = Math.abs(t - targetMs);
        if (diff < bestDiff) {
            bestDiff = diff;
            bestIndex = i;
        }
    }

    playbackIndex.value = bestIndex;
    updatePlaybackMarker();
    stopPlayback();
}

// Playback Logic
function togglePlay() {
    if (isPlaying.value) {
        stopPlayback();
    } else {
        startPlayback();
    }
}

function startPlayback() {
    if (!activeDay.value || !activeDay.value.route) return;
    isPlaying.value = true;
    animate();
}

function stopPlayback() {
    isPlaying.value = false;
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }
}

function restartPlayback() {
    playbackIndex.value = 0;
    updatePlaybackMarker();
    if (!isPlaying.value) startPlayback();
}

function stepForward() {
    stopPlayback();
    if (activeDay.value && activeDay.value.route && playbackIndex.value < activeDay.value.route.length - 1) {
        playbackIndex.value++;
        updatePlaybackMarker();
    }
}

function stepBackward() {
    stopPlayback();
    if (playbackIndex.value > 0) {
        playbackIndex.value--;
        updatePlaybackMarker();
    }
}

function setSpeed(ms) {
    playbackSpeed.value = ms;
}

let lastFrameTime = 0;
function animate(time) {
    if (!isPlaying.value) return;

    if (time - lastFrameTime > playbackSpeed.value) {
        if (activeDay.value && activeDay.value.route) {
            if (playbackIndex.value < activeDay.value.route.length - 1) {
                playbackIndex.value++;
                updatePlaybackMarker();
            } else {
                stopPlayback(); // Reached end
                return;
            }
        }
        lastFrameTime = time;
    }
    animationFrameId = requestAnimationFrame(animate);
}

function updatePlaybackMarker() {
    if (!playbackMarker || !activeDay.value || !activeDay.value.route) return;
    const pt = activeDay.value.route[playbackIndex.value];
    playbackMarker.setLatLng([pt[0], pt[1]]);
}

</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f1f1;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #555;
}
.timeline-line {
  position: absolute;
  left: 31px; /* Center of the 24px width column (12px) + padding-left (16px) approx */
  top: 0;
  bottom: 0;
  width: 2px;
  background-color: #e9ecef;
  z-index: 0;
}
</style>
