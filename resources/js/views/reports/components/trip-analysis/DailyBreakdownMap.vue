<template>
  <div class="card border rounded-3 shadow-0 h-100">
    <div class="card-body p-0">
      <div class="row g-0 h-100">
        <!-- Left Sidebar: Day List & Timeline -->
        <div class="col-12 col-lg-4 border-end d-flex flex-column" style="height: 75vh; max-height: 800px;">
          <div class="overflow-auto custom-scrollbar flex-grow-1">
            <div class="list-group list-group-flush">
              <template v-for="day in rowsDailyBreakdown" :key="day.key">
                <!-- Day Header -->
                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3"
                     :class="{'bg-light': !day.isOpen, 'bg-primary-subtle text-primary': day.isOpen}"
                     @click="toggleDay(day)"
                     role="button"
                     style="cursor: pointer;">
                  <div>  
                    <div class="fw-bold">{{ day.date }}</div>
                    <div class="small" :class="day.isOpen ? 'text-primary' : 'text-muted'">{{ day.distance }}</div>
                  </div> 
                  <i class="bi" :class="day.isOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </div>

                
                <!-- Day Details (Timeline) -->
                <div v-if="day.isOpen" class="bg-white">
                  <!-- Summary Box -->
                  <div class="p-3 m-3 rounded-3 bg-info-subtle text-dark">
                    <div class="fw-bold mb-2 small text-uppercase text-primary-emphasis">Summary for {{ day.summary.date }}</div>
                    <div class="row g-2">
                      <div class="col-6">
                        <div class="text-muted small" style="font-size: 0.75rem;">Total Distance</div>
                        <div class="fw-bold">{{ day.summary.dist }}</div>
                      </div>
                      <div class="col-6">
                        <div class="text-muted small" style="font-size: 0.75rem;">Total Duration</div>
                        <div class="fw-bold">{{ day.summary.dur }}</div>
                      </div>
                      <div class="col-6">
                        <div class="text-muted small" style="font-size: 0.75rem;">Total Idling</div>
                        <div class="fw-bold">{{ day.summary.idle }}</div>
                      </div>
                      <div class="col-6">
                        <div class="text-muted small" style="font-size: 0.75rem;">Behaviour</div>
                        <div class="fw-bold text-danger">{{ day.summary.behav }}</div>
                      </div>
                    </div>
                  </div>

                  <!-- Timeline -->
                  <div class="px-3 pb-3 position-relative">
                    <div class="timeline-line"></div>
                    
                    <div v-for="(item, idx) in day.timeline" :key="idx" class="d-flex mb-3 position-relative z-1">
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
                              <div class="fw-bold small" :class="getTypeColor(item.type)">{{ getTypeLabel(item.type, item.alert) }}</div>
                              <div class="text-muted small" style="font-size: 0.75rem;">{{ item.location }}</div>
                              <div v-if="item.dist || item.dur" class="mt-1">
                                <span v-if="item.dist" class="badge bg-light text-dark border me-1 fw-normal">{{ item.dist }}</span>
                                <span v-if="item.dur" class="badge bg-light text-dark border me-1 fw-normal">{{ item.dur }}</span>
                              </div>
                           </div>
                           <div class="text-muted small fw-semibold">{{ item.time }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </template>
              <div v-if="rowsDailyBreakdown.length === 0" class="text-center p-5 text-muted">
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
               class="position-absolute top-0 start-50 translate-middle-x mt-3 z-3 bg-white p-2 rounded-pill shadow-sm d-flex align-items-center gap-2 px-3">
            
            <button class="btn btn-sm btn-light rounded-circle" @click="restartPlayback" title="Restart">
              <i class="bi bi-arrow-counterclockwise"></i>
            </button>
            
            <div class="vr mx-1"></div>

            <button class="btn btn-sm btn-light rounded-circle" @click="stepBackward" title="Step Back">
               <i class="bi bi-skip-backward-fill"></i>
            </button>
            
            <button class="btn btn-primary btn-sm rounded-circle shadow-sm" @click="togglePlay" style="width: 32px; height: 32px;">
              <i class="bi" :class="isPlaying ? 'bi-pause-fill' : 'bi-play-fill'"></i>
            </button>

            <button class="btn btn-sm btn-light rounded-circle" @click="stepForward" title="Step Forward">
               <i class="bi bi-skip-forward-fill"></i>
            </button>

            <div class="vr mx-1"></div>

            <div class="btn-group btn-group-sm" role="group">
              <input type="radio" class="btn-check" name="speed" id="speed1" autocomplete="off" :checked="playbackSpeed === 10" @click="setSpeed(10)">
              <label class="btn btn-outline-secondary border-0 small py-0" for="speed1">Slow</label>

              <input type="radio" class="btn-check" name="speed" id="speed2" autocomplete="off" :checked="playbackSpeed === 50" @click="setSpeed(50)">
              <label class="btn btn-outline-secondary border-0 small py-0" for="speed2">Normal</label>

              <input type="radio" class="btn-check" name="speed" id="speed3" autocomplete="off" :checked="playbackSpeed === 200" @click="setSpeed(200)">
              <label class="btn btn-outline-secondary border-0 small py-0" for="speed3">Fast</label>
            </div>
          </div>

          <!-- Date Overlay on Map -->
           <div v-if="activeDay" class="position-absolute bottom-0 start-0 m-3 z-3">
              <div class="bg-white px-3 py-2 rounded shadow-sm opacity-75 small fw-bold">
                 {{ playbackTimeDisplay }}
              </div>
           </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, onUnmounted, watch, computed } from 'vue';
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

const mapEl = ref(null);
let map = null;
let polylineLayer = null;
let movingMarker = null;
let markersLayer = L.layerGroup();

const activeDay = ref(null);
const isPlaying = ref(false);
const playbackSpeed = ref(50); // Points per second approx? No, logic will be different.
// Logic: We will use an interval. Speed determines how many ms we skip per tick or how fast the tick is.
// Let's say we have an index.
const currentIndex = ref(0);
let animationFrame = null;
let lastTimestamp = 0;

// Computed for display
const playbackTimeDisplay = computed(() => {
    if (!activeDay.value || !activeDay.value.route || activeDay.value.route.length === 0) return '';
    const point = activeDay.value.route[currentIndex.value];
    if (point && point[2]) {
        return new Date(point[2]).toLocaleString();
    }
    return activeDay.value.date;
});

function initMap() {
  if (!mapEl.value) return;
  if (map) return; 

  map = L.map(mapEl.value).setView([0, 0], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  markersLayer.addTo(map);
}

function updateMap(day) {
    if (!map) initMap();
    
    activeDay.value = day;
    currentIndex.value = 0;
    stopPlayback();

    // Clear layers
    if (polylineLayer) {
        map.removeLayer(polylineLayer);
        polylineLayer = null;
    }
    if (movingMarker) {
        map.removeLayer(movingMarker);
        movingMarker = null;
    }
    markersLayer.clearLayers();

    if (!day || !day.route || day.route.length === 0) {
        return;
    }

    // Draw Route (Blue Line)
    // route is [[lat, lon, time], ...]
    const latlngs = day.route.map(p => [p[0], p[1]]);
    polylineLayer = L.polyline(latlngs, { color: '#0b5ed7', weight: 4 }).addTo(map);
    map.fitBounds(polylineLayer.getBounds(), { padding: [50, 50] });

    // Draw Static Markers (Start, End, Events)
    if (day.timeline) {
        day.timeline.forEach(item => {
            if (item.lat && item.lon && item.lat !== 0 && item.lon !== 0) {
                // Determine color/icon
                let color = 'blue';
                if (item.type === 'end') color = 'red';
                if (item.type === 'alert') color = 'orange';
                if (item.type === 'stop') color = 'grey';

                const circleMarker = L.circleMarker([item.lat, item.lon], {
                    radius: 6,
                    fillColor: color,
                    color: '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                });

                let popupContent = `<strong>${getTypeLabel(item.type, item.alert)}</strong><br>${item.time}`;
                if (item.location) popupContent += `<br>${item.location}`;
                circleMarker.bindPopup(popupContent);
                markersLayer.addLayer(circleMarker);
            }
        });
    }

    // Initialize Moving Marker at Start
    if (latlngs.length > 0) {
        movingMarker = L.circleMarker(latlngs[0], {
            radius: 8,
            fillColor: '#0d6efd',
            color: '#fff',
            weight: 3,
            opacity: 1,
            fillOpacity: 1
        }).addTo(map);
    }
}

function toggleDay(day) {
    // If clicking same day, just toggle
    if (day.isOpen) {
        day.isOpen = false;
        activeDay.value = null;
        return;
    }

    // Close others
    props.rowsDailyBreakdown.forEach(d => {
        d.isOpen = false;
    });
    
    day.isOpen = true;
    updateMap(day);
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
    
    // If reached end, restart
    if (currentIndex.value >= activeDay.value.route.length - 1) {
        currentIndex.value = 0;
    }

    isPlaying.value = true;
    lastTimestamp = performance.now();
    requestAnimationFrame(animate);
}

function stopPlayback() {
    isPlaying.value = false;
    lastTimestamp = 0;
}

function restartPlayback() {
    currentIndex.value = 0;
    updateMarkerPosition();
    if (isPlaying.value) stopPlayback();
}

function setSpeed(val) {
    playbackSpeed.value = val;
}

function stepForward() {
    stopPlayback();
    if (activeDay.value && currentIndex.value < activeDay.value.route.length - 1) {
        currentIndex.value = Math.min(currentIndex.value + 10, activeDay.value.route.length - 1); // Skip 10 points
        updateMarkerPosition();
    }
}

function stepBackward() {
    stopPlayback();
    if (activeDay.value && currentIndex.value > 0) {
        currentIndex.value = Math.max(currentIndex.value - 10, 0);
        updateMarkerPosition();
    }
}

function seekToTime(day, timeSort) {
    // Find closest index in route
    if (!day.route) return;
    
    // Convert timeSort (seconds) to ms
    const timeMs = timeSort * 1000;
    
    // Find index
    const idx = day.route.findIndex(p => p[2] >= timeMs);
    if (idx !== -1) {
        currentIndex.value = idx;
        updateMarkerPosition();
    }
}

function animate(timestamp) {
    if (!isPlaying.value) return;

    const elapsed = timestamp - lastTimestamp;
    
    // Logic: Speed = points per second? Or just simple skip?
    // Let's try simple frame skip based on speed value.
    // If speed is 10 (Slow), we advance 1 index every 50ms?
    // If speed is 200 (Fast), we advance 5 indexes every frame?
    
    // Better: Target FPS.
    // Let's assume we want to cover the route in X seconds.
    // But route length varies.
    
    // Simple approach: Advance N points per frame.
    // Speed 10 = 1 point per 5 frames?
    // Speed 50 = 1 point per frame.
    // Speed 200 = 5 points per frame.
    
    let pointsToAdvance = 1;
    if (playbackSpeed.value === 10) {
        // Slow: Throttle
        if (elapsed < 100) { // Limit to 10fps
             requestAnimationFrame(animate);
             return; 
        }
    } else if (playbackSpeed.value === 50) {
        pointsToAdvance = 1;
    } else if (playbackSpeed.value === 200) {
        pointsToAdvance = 5;
    }

    lastTimestamp = timestamp;
    
    currentIndex.value += pointsToAdvance;
    
    if (currentIndex.value >= activeDay.value.route.length) {
        currentIndex.value = activeDay.value.route.length - 1;
        stopPlayback();
        updateMarkerPosition();
        return;
    }

    updateMarkerPosition();
    requestAnimationFrame(animate);
}

function updateMarkerPosition() {
    if (!movingMarker || !activeDay.value || !activeDay.value.route) return;
    const point = activeDay.value.route[currentIndex.value];
    if (point) {
        movingMarker.setLatLng([point[0], point[1]]);
        // Optional: Pan map to follow marker if out of bounds?
        // map.panTo([point[0], point[1]]); // Can be annoying if user zoomed out.
    }
}

function getTypeLabel(type, alert) {
    if (type === 'start') return 'Start';
    if (type === 'end') return 'End';
    if (type === 'alert') return alert || 'Alert';
    if (type === 'stop') return 'Idle/Stop';
    return type;
}

function getTypeColor(type) {
    if (type === 'start') return 'text-primary';
    if (type === 'end') return 'text-danger';
    if (type === 'alert') return 'text-warning-emphasis';
    if (type === 'stop') return 'text-secondary';
    return 'text-dark';
}

watch(() => props.rowsDailyBreakdown, (newVal) => {
    if (newVal && newVal.length > 0) {
        // Open first day by default
        newVal[0].isOpen = true;
        nextTick(() => {
           updateMap(newVal[0]);
        });
    } else {
        if (map) {
            markersLayer.clearLayers();
            if (polylineLayer) map.removeLayer(polylineLayer);
            if (movingMarker) map.removeLayer(movingMarker);
        }
    }
}, { deep: true });

onMounted(async () => {
  await nextTick();
  initMap();
});

onUnmounted(() => {
  stopPlayback();
  if (map) {
    map.remove();
    map = null;
  }
});
</script>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f1f1;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #ccc;
  border-radius: 3px;
}
.timeline-line {
    position: absolute;
    top: 10px;
    bottom: 30px;
    left: 27px; /* Center of icon column (16px + 24px/2 approx) - Wait, icon col is 24px wide, + me-3 (16px). Center is ~12px? No. */
    /* Icon col width is 24px. Center is 12px. Plus padding-left of container (16px px-3). Total left ~ 28px. */
    width: 2px;
    background-color: #dee2e6;
    z-index: 0;
}
.cursor-pointer {
    cursor: pointer;
}
</style>
