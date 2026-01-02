<template>
  <div class="card border rounded-3 shadow-0">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <div class="list-group small">
            <template v-for="day in rowsDailyBreakdown" :key="day.key">
              <!-- Day Header -->
              <div class="list-group-item d-flex justify-content-between align-items-center bg-light"
                   @click="toggleDay(day)"
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
                        <div class="fw-semibold" :class="item.type === 'start' ? 'text-primary' : 'text-danger'">{{ item.type === 'start' ? 'Start' : (item.type === 'end' ? 'End' : 'Alert') }}</div>
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
            <div v-if="rowsDailyBreakdown.length === 0" class="text-center p-3 text-muted">
                No data available
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-8">
          <div class="position-relative h-100">
             <div class="position-absolute top-0 start-50 translate-middle-x mt-3 z-3 bg-white p-2 rounded-pill shadow-sm d-flex align-items-center gap-2" style="width: fit-content;">
                <button class="btn btn-sm btn-link text-dark text-decoration-none fw-semibold" @click="fitBounds"><i class="bi bi-arrow-counterclockwise"></i> Reset View</button>
             </div>
             <div ref="mapEl" style="height: 60vh; min-height: 320px;" class="rounded-3 overflow-hidden border"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, onUnmounted, watch } from 'vue';
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
let markersLayer = L.layerGroup();

function initMap() {
  if (!mapEl.value) return;
  if (map) return; // Already initialized

  map = L.map(mapEl.value).setView([0, 0], 2);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  markersLayer.addTo(map);
}

function updateMap(day) {
    if (!map) initMap();
    
    // Clear existing layers
    if (polylineLayer) {
        map.removeLayer(polylineLayer);
        polylineLayer = null;
    }
    markersLayer.clearLayers();

    if (!day || !day.route || day.route.length === 0) {
        return;
    }

    // Draw Route
    const routePoints = day.route; // [[lat, lon], ...]
    if (routePoints.length > 0) {
        polylineLayer = L.polyline(routePoints, { color: '#0b5ed7', weight: 4 }).addTo(map);
        map.fitBounds(polylineLayer.getBounds(), { padding: [50, 50] });
    }

    // Draw Markers from Timeline
    if (day.timeline) {
        day.timeline.forEach(item => {
            if (item.lat && item.lon && item.lat !== 0 && item.lon !== 0) {
                const marker = L.marker([item.lat, item.lon]);
                let popupContent = `<strong>${item.type === 'start' ? 'Start' : (item.type === 'end' ? 'End' : 'Event')}</strong><br>${item.time}`;
                if (item.location) popupContent += `<br>${item.location}`;
                marker.bindPopup(popupContent);
                markersLayer.addLayer(marker);
            }
        });
    }
}

function toggleDay(day) {
    // Close others? Or allow multiple?
    // Let's close others to keep map focused on one day
    props.rowsDailyBreakdown.forEach(d => {
        if (d !== day) d.isOpen = false;
    });
    
    day.isOpen = !day.isOpen;
    
    if (day.isOpen) {
        updateMap(day);
    } else {
        // Clear map if closed
        if (polylineLayer) map.removeLayer(polylineLayer);
        markersLayer.clearLayers();
    }
}

function fitBounds() {
    if (polylineLayer && map) {
        map.fitBounds(polylineLayer.getBounds(), { padding: [50, 50] });
    }
}

// Watch for data changes
watch(() => props.rowsDailyBreakdown, (newVal) => {
    if (newVal && newVal.length > 0) {
        // Automatically open the first day if available?
        // Or just wait for user interaction.
        // Let's reset map.
        if (polylineLayer && map) map.removeLayer(polylineLayer);
        if (markersLayer) markersLayer.clearLayers();
        
        // Maybe open first day by default
        // newVal[0].isOpen = true;
        // updateMap(newVal[0]);
    }
}, { deep: true });

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
