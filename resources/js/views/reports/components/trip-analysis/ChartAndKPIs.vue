<template>
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
      <div class="card border rounded-3 shadow-0 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-3 mb-2">
             <div class="d-flex align-items-center gap-1"><span style="width:10px;height:10px;background:#e83e8c;display:inline-block;border-radius:2px;"></span> <span class="small text-muted">Trip Duration</span></div>
             <div class="d-flex align-items-center gap-1"><span style="width:10px;height:10px;background:#0b0f28;display:inline-block;border-radius:2px;"></span> <span class="small text-muted">Idle Duration</span></div>
             <div class="d-flex align-items-center gap-1"><span style="width:10px;height:10px;background:#339af0;display:inline-block;border-radius:2px;"></span> <span class="small text-muted">Distance</span></div>
          </div> 
          <!-- Chart Visualization -->
          <div class="h-100 bg-white rounded" style="min-height: 220px; position: relative;">
             <canvas v-if="trips.length > 0" ref="chartCanvas"></canvas>
             <div v-else class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                 <span class="text-muted small">No trip data available for chart</span>
             </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card border rounded-3 shadow-0 h-100">
        <div class="card-body">
          <div class="row g-3 h-100">
            <div class="col-12 col-md-5">
              <div class="h-100 d-flex flex-column justify-content-center">
                <div class="small text-muted mb-1">Total Duration</div>
                <div class="display-6 fw-bold mb-3">{{ formatDuration(totalTimeMs) }}</div>

                <div class="progress mb-1" style="height: 8px;">
                  <div class="progress-bar" :style="{width: tripPct + '%', backgroundColor: '#e83e8c'}"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                   <div class="small fw-semibold" style="color: #e83e8c;">Trip Duration</div>
                   <div class="fw-bold">{{ formatDuration(summary?.totalDuration) }}</div>
                </div>

                <div class="progress mb-1" style="height: 8px;">
                  <div class="progress-bar" :style="{width: idlePct + '%', backgroundColor: '#0b0f28'}"></div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                   <div class="small fw-semibold" style="color: #0b0f28;">Idle Duration</div>
                   <div class="fw-bold">{{ formatDuration(summary?.totalIdle) }}</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-7 border-start">
              <div class="ps-md-3 h-100 d-flex flex-column justify-content-center">
                <h6 class="fw-bold mb-3">Summary</h6>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Total Distance (km)</span><span class="fw-semibold">{{ formatDistance(summary?.totalDistance) }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Total Trip Duration (hr)</span><span class="fw-semibold">{{ formatDuration(summary?.totalDuration) }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Total Idling (hr)</span><span class="fw-semibold">{{ formatDuration(summary?.totalIdle) }}</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Idling Percentage vs Trip Duration</span><span class="fw-semibold">{{ idlePct.toFixed(2) }}%</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Max Speed</span><span class="fw-semibold">{{ Math.round(summary?.maxSpeed || 0) }} km/h</span></div>
                <div class="d-flex justify-content-between"><span class="small text-muted">Total Fuel Usage (litre)</span><span class="fw-semibold">{{ (summary?.totalFuel || 0).toFixed(1) }} Litre</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  summary: {
    type: Object,
    default: () => ({})
  }
});

const totalTimeMs = computed(() => (props.summary?.totalDuration || 0) + (props.summary?.totalIdle || 0));

const tripPct = computed(() => {
  if (!totalTimeMs.value) return 0;
  return (props.summary?.totalDuration / totalTimeMs.value) * 100;
});

const idlePct = computed(() => {
  if (!totalTimeMs.value) return 0;
  return (props.summary?.totalIdle / totalTimeMs.value) * 100;
});

const formatDuration = (ms) => {
  if (!ms) return '0s';
  const sec = Math.floor(ms / 1000);
  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  const s = sec % 60;
  
  if (h > 0) return `${h}h ${m}m ${s}s`;
  if (m > 0) return `${m}m ${s}s`;
  return `${s}s`;
};

const formatDistance = (m) => {
  if (!m) return '0 KM';
  return (m / 1000).toFixed(2) + ' KM';
};
</script>
