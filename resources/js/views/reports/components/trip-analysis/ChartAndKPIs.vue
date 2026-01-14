<template>
  <div class="row g-3 mb-3">
    <!-- Combo Chart Section -->
    <div class="col-12 col-lg-6">
      <div class="card border rounded-3 shadow-sm h-100">
        <div class="card-body d-flex flex-column">
          <!-- Legend -->
          <div class="d-flex align-items-center justify-content-center gap-3 mb-4">
             <div class="d-flex align-items-center gap-1"><span style="width:12px;height:12px;background:#e83e8c;display:inline-block;border-radius:2px;"></span> <span class="small fw-semibold text-muted">Trip Duration</span></div>
             <div class="d-flex align-items-center gap-1"><span style="width:12px;height:12px;background:#0b0f28;display:inline-block;border-radius:2px;"></span> <span class="small fw-semibold text-muted">Idle Duration</span></div>
             <div class="d-flex align-items-center gap-1"><span style="width:12px;height:12px;background:#339af0;display:inline-block;border-radius:2px;"></span> <span class="small fw-semibold text-muted">Distance</span></div>
          </div>

          <!-- Chart Container -->
          <div class="flex-grow-1 position-relative" style="min-height: 250px;">
             <div v-if="!chartData.length" class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                <span class="text-muted small">No data available for chart</span>
             </div>

             <div v-else class="h-100 w-100 position-relative">
                <!-- SVG Chart -->
                <svg class="w-100 h-100" viewBox="0 0 100 60" preserveAspectRatio="none">
                    <!-- Y-Axis Grid Lines (Left - Distance) -->
                    <g class="grid-lines">
                        <line v-for="i in 5" :key="'grid-'+i" x1="10" :y1="10 + (i-1)*10" x2="90" :y2="10 + (i-1)*10" stroke="#f0f0f0" stroke-width="0.5" />
                    </g>

                    <!-- Data Bars (Distance) -->
                    <g class="bars">
                        <rect v-for="(d, i) in chartData" :key="'bar-'+i"
                              :x="getX(i) - 2"
                              :y="getYDistance(d.distance)"
                              width="4"
                              :height="50 - getYDistance(d.distance)"
                              fill="#339af0"
                              opacity="0.8"
                              rx="0.5"
                        >
                            <title>Distance: {{ formatDistance(d.distance) }}</title>
                        </rect>
                    </g>

                    <!-- Trip Duration Line -->
                    <polyline :points="tripLinePoints" fill="none" stroke="#e83e8c" stroke-width="0.8" />
                    <circle v-for="(d, i) in chartData" :key="'trip-dot-'+i"
                            :cx="getX(i)" :cy="getYDuration(d.tripDuration)" r="1.5" fill="#e83e8c" stroke="#fff" stroke-width="0.5">
                        <title>Trip: {{ formatDuration(d.tripDuration) }}</title>
                    </circle>

                    <!-- Idle Duration Line -->
                    <polyline :points="idleLinePoints" fill="none" stroke="#0b0f28" stroke-width="0.8" />
                    <circle v-for="(d, i) in chartData" :key="'idle-dot-'+i"
                            :cx="getX(i)" :cy="getYDuration(d.idleDuration)" r="1.5" fill="#0b0f28" stroke="#fff" stroke-width="0.5">
                        <title>Idle: {{ formatDuration(d.idleDuration) }}</title>
                    </circle>

                    <!-- X-Axis Labels -->
                    <g class="x-labels">
                        <text v-for="(d, i) in chartData" :key="'label-'+i"
                              :x="getX(i)" y="56"
                              font-size="2.5" text-anchor="middle" fill="#6c757d">{{ formatDateLabel(d.date) }}</text>
                    </g>

                    <!-- Left Y-Axis Labels (Distance) -->
                    <text x="8" y="10" font-size="2.5" text-anchor="end" fill="#6c757d">{{ Math.round(maxDistance) }}</text>
                    <text x="8" y="30" font-size="2.5" text-anchor="end" fill="#6c757d">{{ Math.round(maxDistance/2) }}</text>
                    <text x="8" y="50" font-size="2.5" text-anchor="end" fill="#6c757d">0</text>
                    <!-- Label -->
                    <text x="2" y="30" font-size="2.5" text-anchor="middle" transform="rotate(-90, 2, 30)" fill="#6c757d">(Kilo-meters)</text>

                    <!-- Right Y-Axis Labels (Duration) -->
                    <text x="92" y="10" font-size="2.5" text-anchor="start" fill="#6c757d">{{ Math.round(maxDuration/60000) }}</text>
                    <text x="92" y="30" font-size="2.5" text-anchor="start" fill="#6c757d">{{ Math.round((maxDuration/2)/60000) }}</text>
                    <text x="92" y="50" font-size="2.5" text-anchor="start" fill="#6c757d">0</text>
                    <!-- Label -->
                    <text x="98" y="30" font-size="2.5" text-anchor="middle" transform="rotate(90, 98, 30)" fill="#6c757d">(Minutes)</text>
                </svg>
             </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Total Duration & Summary Section -->
    <div class="col-12 col-lg-6">
      <div class="row g-3 h-100">
        <!-- Total Duration Card -->
        <div class="col-12 col-md-5">
           <div class="card border rounded-3 shadow-sm h-100">
             <div class="card-body d-flex flex-column justify-content-center">
                <div class="small fw-bold text-muted mb-1">Total Duration</div>
                <div class="display-6 fw-bold mb-3 text-dark">{{ formatDuration(totalTimeMs) }}</div>

                <!-- Progress Bar -->
                <div class="progress mb-4" style="height: 6px;">
                  <div class="progress-bar" :style="{width: tripPct + '%', backgroundColor: '#e83e8c'}"></div>
                  <div class="progress-bar" :style="{width: idlePct + '%', backgroundColor: '#0b0f28'}"></div>
                </div>

                <!-- Stacked Info Blocks -->
                <div class="d-flex flex-column gap-3">
                    <div class="p-3 border rounded bg-white shadow-sm">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span style="width:8px;height:8px;background:#e83e8c;border-radius:50%;display:inline-block;"></span>
                            <span class="small fw-bold text-muted">Trip Duration</span>
                        </div>
                        <div class="h5 fw-bold mb-0">{{ formatDuration(summary?.totalDuration) }}</div>
                    </div>
                    <div class="p-3 border rounded bg-white shadow-sm">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <span style="width:8px;height:8px;background:#0b0f28;border-radius:50%;display:inline-block;"></span>
                            <span class="small fw-bold text-muted">Idle Duration</span>
                        </div>
                        <div class="h5 fw-bold mb-0">{{ formatDuration(summary?.totalIdle) }}</div>
                    </div>
                </div>
             </div>
           </div>
        </div>

        <!-- Summary Card -->
        <div class="col-12 col-md-7">
          <div class="card border rounded-3 shadow-sm h-100">
            <div class="card-body d-flex flex-column justify-content-center">
                <h5 class="fw-bold mb-4">Summary</h5>

                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                    <span class="small fw-semibold text-muted">Total Distance (km)</span>
                    <span class="fw-bold">{{ formatDistance(summary?.totalDistance) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                    <span class="small fw-semibold text-muted">Total Trip Duration (hr)</span>
                    <span class="fw-bold">{{ formatDuration(summary?.totalDuration) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                    <span class="small fw-semibold text-muted">Total Idling (hr)</span>
                    <span class="fw-bold">{{ formatDuration(summary?.totalIdle) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                    <span class="small fw-semibold text-muted">Idling Percentage vs Trip Duration</span>
                    <span class="fw-bold">{{ idlePct.toFixed(2) }}%</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom border-light">
                    <span class="small fw-semibold text-muted">Average Fuel Consumption (km/litre)</span>
                    <span class="fw-bold">{{ toNumber(summary?.avgKmL) }} Km/l</span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="small fw-semibold text-muted">Total Fuel Usage (litre)</span>
                    <span class="fw-bold">{{ toNumber(summary?.totalFuel).toFixed(1) }} Litre</span>
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
  },
  trips: {
    type: Array,
    default: () => []
  },
  stops: {
    type: Array,
    default: () => []
  },
  precomputedChartData: {
    type: Array,
    default: () => []
  },
  startDate: String,
  endDate: String
});

const toNumber = (v) => {
  const n = typeof v === 'number' ? v : Number(v);
  return Number.isFinite(n) ? n : 0;
};

// Helper to format duration
const formatDuration = (ms) => {
  const safeMs = toNumber(ms);
  if (!safeMs) return '0s';
  const sec = Math.floor(safeMs / 1000);
  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  const s = sec % 60;

  if (h > 0) return `${h}h ${m}m ${s}s`;
  if (m > 0) return `${m}m ${s}s`;
  return `${s}s`;
};

const formatDistance = (m) => {
  const safeM = toNumber(m);
  if (!safeM) return '0 KM';
  return (safeM / 1000).toFixed(2) + ' KM';
};

// Total Time for Progress Bar
const totalTimeMs = computed(() => toNumber(props.summary?.totalDuration) + toNumber(props.summary?.totalIdle));

const tripPct = computed(() => {
  if (!totalTimeMs.value) return 0;
  return (toNumber(props.summary?.totalDuration) / totalTimeMs.value) * 100;
});

const idlePct = computed(() => {
  if (!totalTimeMs.value) return 0;
  return (toNumber(props.summary?.totalIdle) / totalTimeMs.value) * 100;
});

// Chart Data Processing
const chartData = computed(() => {
    // If precomputed data is provided (e.g. from Daily Summary), use it
    if (props.precomputedChartData && props.precomputedChartData.length > 0) {
        return props.precomputedChartData
          .map((d) => ({
            ...d,
            distance: toNumber(d.distance),
            tripDuration: toNumber(d.tripDuration),
            idleDuration: toNumber(d.idleDuration),
          }))
          .sort((a, b) => String(a.date).localeCompare(String(b.date)));
    }

    const dataMap = {};

    // Process Trips
    props.trips.forEach(t => {
        const d = (t.startTimeIso || '').split('T')[0];
        if (!d) return;
        if (!dataMap[d]) dataMap[d] = { date: d, distance: 0, tripDuration: 0, idleDuration: 0 };
        dataMap[d].distance += toNumber(t.distance_m);
        dataMap[d].tripDuration += toNumber(t.duration_ms);
    });

    // Process Stops (Idle)
    props.stops.forEach(s => {
        const d = (s.startTimeIso || '').split('T')[0];
        if (!d) return;
        if (!dataMap[d]) dataMap[d] = { date: d, distance: 0, tripDuration: 0, idleDuration: 0 };
        dataMap[d].idleDuration += toNumber(s.duration_ms);
    });

    return Object.values(dataMap).sort((a, b) => a.date.localeCompare(b.date));
});

// Chart Scaling
const maxDistance = computed(() => {
    if (!chartData.value.length) return 100;
    const max = Math.max(...chartData.value.map(d => d.distance));
    return max > 0 ? max * 1.1 : 100; // Add 10% padding
});

const maxDuration = computed(() => {
    if (!chartData.value.length) return 3600000;
    const max = Math.max(...chartData.value.map(d => Math.max(d.tripDuration, d.idleDuration)));
    return max > 0 ? max * 1.1 : 3600000;
});

// Chart Drawing Helpers
const getX = (index) => {
    const count = chartData.value.length;
    const padding = 10;
    const width = 80; // 90 - 10
    const step = width / (count > 1 ? count - 1 : 1);
    return padding + (index * step);
};

const getYDistance = (val) => {
    const height = 40; // 50 - 10
    const ratio = toNumber(val) / maxDistance.value;
    return 50 - (ratio * height);
};

const getYDuration = (val) => {
    const height = 40;
    const ratio = toNumber(val) / maxDuration.value;
    return 50 - (ratio * height);
};

const tripLinePoints = computed(() => {
    return chartData.value.map((d, i) => `${getX(i)},${getYDuration(d.tripDuration)}`).join(' ');
});

const idleLinePoints = computed(() => {
    return chartData.value.map((d, i) => `${getX(i)},${getYDuration(d.idleDuration)}`).join(' ');
});

const formatDateLabel = (isoDate) => {
    if (!isoDate) return '';
    const d = new Date(isoDate);
    const s = d.toLocaleDateString('en-GB');
    const parts = s.split('/');
    if (parts.length < 2) return s;
    return `${parts[0]}/${parts[1]}`;
};

</script>

<style scoped>
.display-6 { font-size: 2.5rem; }
</style>
