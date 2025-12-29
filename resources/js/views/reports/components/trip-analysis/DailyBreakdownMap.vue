<template>
  <div class="card border rounded-3 shadow-0">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <div class="list-group small">
            <template v-for="day in rowsDailyBreakdown" :key="day.key">
              <!-- Day Header -->
              <div class="list-group-item d-flex justify-content-between align-items-center bg-light"
                   @click="day.isOpen = !day.isOpen"
                   role="button">
                <div>
                  <div class="fw-bold">{{ day.date }}</div>
                  <div class="text-muted">{{ day.distance }}</div>
                </div>
                <i class="bi" :class="day.isOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
              </div>

              <!-- Day Details (Timeline) -->
              <div v-if="day.isOpen" class="list-group-item p-0 border-0">
                <div v-if="day.summary" class="bg-light border-bottom p-3">
                  <div class="fw-semibold mb-2">Summary for {{ day.summary.date }}</div>
                  <div class="row g-2">
                    <div class="col-6">
                      <div class="text-muted small">Total Distance</div>
                      <div class="fw-bold">{{ day.summary.dist }}</div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted small">Total Duration</div>
                      <div class="fw-bold">{{ day.summary.dur }}</div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted small">Total Idling</div>
                      <div class="fw-bold">{{ day.summary.idle }}</div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted small">Behaviour</div>
                      <div class="fw-bold text-danger">{{ day.summary.behav }}</div>
                    </div>
                  </div>
                </div>
                <div class="list-group list-group-flush">
                  <div v-for="(item, idx) in day.timeline" :key="idx" class="list-group-item">
                    <div class="d-flex gap-3">
                      <div class="d-flex flex-column align-items-center" style="width: 60px;">
                        <div class="text-muted small">{{ item.time }}</div>
                        <div v-if="idx < day.timeline.length - 1 || item.type === 'start'" class="flex-grow-1 border-start border-2 my-1"></div>
                      </div>
                      <div class="pb-2">
                        <div class="fw-semibold" :class="item.type === 'start' ? 'text-primary' : 'text-danger'">{{ item.type === 'start' ? 'Start' : 'End' }}</div>
                        <div class="small text-muted">{{ item.location }}</div>
                        <div v-if="item.dist || item.dur || item.alert" class="mt-1">
                          <span v-if="item.dist" class="badge bg-light text-dark border me-1">{{ item.dist }}</span>
                          <span v-if="item.dur" class="badge bg-light text-dark border me-1">{{ item.dur }}</span>
                          <span v-if="item.alert" class="badge bg-danger-subtle text-danger border border-danger">{{ item.alert }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        </div>
        <div class="col-12 col-lg-8">
          <div class="position-relative h-100">
             <div class="position-absolute top-0 start-50 translate-middle-x mt-3 z-3 bg-white p-2 rounded-pill shadow-sm d-flex align-items-center gap-2" style="width: fit-content;">
                <button class="btn btn-sm btn-link text-dark text-decoration-none fw-semibold"><i class="bi bi-arrow-counterclockwise"></i> Restart</button>
                <div class="vr"></div>
                <button class="btn btn-sm btn-link text-secondary"><i class="bi bi-skip-backward-fill"></i></button>
                <button class="btn btn-sm btn-link text-secondary"><i class="bi bi-play-fill"></i></button>
                <button class="btn btn-sm btn-link text-secondary"><i class="bi bi-pause-fill"></i></button>
                <button class="btn btn-sm btn-link text-secondary"><i class="bi bi-skip-forward-fill"></i></button>
                <div class="vr"></div>
                <span class="small text-muted ms-1">Slow</span>
                <input type="range" class="form-range" style="width: 80px">
                <span class="small text-muted me-1">Fast</span>
             </div>
             <div ref="mapEl" style="height: 60vh; min-height: 320px;" class="rounded-3 overflow-hidden border"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, onUnmounted } from 'vue';
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

function initMap() {
  if (!mapEl.value) return;
  if (map) {
    map.remove();
    map = null;
  }
  map = L.map(mapEl.value).setView([3.111, 101.533], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  const route = [
    [3.111, 101.533], // Bukit Jelutong
    [3.115, 101.540],
    [3.120, 101.550],
    [3.130, 101.600],
    [3.140, 101.650],
    [3.150, 101.700],
    [3.157, 101.711], // KLCC
  ];
  L.polyline(route, { color: '#0b5ed7', weight: 4 }).addTo(map);
  L.marker(route[0]).bindPopup('Start Time 05:48 AM • 26/08/2025<br>Bukit Jelutong').addTo(map);
  L.marker(route[route.length - 1]).bindPopup('End Time 06:30 AM • 26/08/2025<br>Kuala Lumpur').addTo(map);
  map.fitBounds(L.polyline(route).getBounds(), { padding: [50, 50] });
}

onMounted(async () => {
  await nextTick();
  initMap();
});

onUnmounted(() => {
  if (map) {
    map.remove();
    map = null;
  }
});
</script>

<style scoped>
/* Add any specific styles if needed */
</style>
