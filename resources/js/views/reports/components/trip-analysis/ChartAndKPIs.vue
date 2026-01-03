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
          <!-- Timeline Chart -->
          <div class="h-100 d-flex flex-column justify-content-center bg-white rounded" style="min-height: 220px;">
             <div v-if="!trips.length" class="d-flex align-items-center justify-content-center h-100 bg-light rounded">
                <span class="text-muted small">No trip data available for chart</span>
             </div>
             <div v-else class="px-4 py-3 h-100 d-flex flex-column">
                <h6 class="small fw-bold text-muted mb-4">Activity Timeline</h6>
                <div class="flex-grow-1 position-relative">
                   <!-- Base Line -->
                   <div class="position-absolute w-100 bg-light rounded" style="height: 20px; top: 50%; transform: translateY(-50%);"></div>

                   <!-- Trip Segments -->
                   <div v-for="(seg, idx) in timelineSegments" :key="idx"
                        class="position-absolute rounded shadow-sm"
                        :style="{
                           left: seg.left,
                           width: seg.width,
                           height: '20px',
                           top: '50%',
                           transform: 'translateY(-50%)',
                           backgroundColor: '#e83e8c',
                           cursor: 'pointer'
                        }"
                        :title="`${seg.data.startTime} - ${seg.data.endTime} (${seg.data.distance})`"
                   ></div>
                </div>

                <!-- Time Labels -->
                <div class="d-flex justify-content-between mt-2 text-muted" style="font-size: 10px;">
                   <span>{{ formatTimeLabel(chartRange.min) }}</span>
                   <span>{{ formatTimeLabel((chartRange.min + chartRange.max) / 2) }}</span>
                   <span>{{ formatTimeLabel(chartRange.max) }}</span>
                </div>
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
  },
  trips: {
    type: Array,
    default: () => []
  },
  startDate: String,
  endDate: String
});

const chartRange = computed(() => {
  // 1. If explicit date range is provided (and valid), use it.
  if (props.startDate && props.endDate) {
      const min = new Date(props.startDate).getTime();
      const max = new Date(props.endDate).getTime();
      if (!isNaN(min) && !isNaN(max) && max > min) {
          return { min, max };
      }
  }

  // 2. Fallback to computing from trips
  if (!props.trips || !props.trips.length) return { min: 0, max: 0 };

  // Use ISO fields if available, otherwise formatted strings might fail in new Date()
  const times = props.trips.flatMap(t => {
      const s = t.startTimeIso || t.startTime;
      const e = t.endTimeIso || t.endTime;
      return [new Date(s).getTime(), new Date(e).getTime()];
  });

  let min = Math.min(...times);
  let max = Math.max(...times);

  // If we only have invalid dates (NaN), default to now
  if (isNaN(min) || isNaN(max)) {
      const now = Date.now();
      return { min: now, max: now + 86400000 };
  }

  // If fallback logic is used, and it looks like a single day, clamp to 00:00-23:59
  const firstDate = new Date(min);
  const startOfDay = new Date(firstDate).setHours(0,0,0,0);
  const endOfDay = new Date(firstDate).setHours(23,59,59,999);

  // If the span is small (less than a day), expand to full day context
  if ((max - min) < 86400000) {
      return { min: startOfDay, max: endOfDay };
  }

  return { min, max };
});

const timelineSegments = computed(() => {
  if (!props.trips || !props.trips.length) return [];

  const { min, max } = chartRange.value;
  const totalDuration = max - min;

  if (totalDuration <= 0) return [];

  return props.trips.map(t => {
      const s = t.startTimeIso || t.startTime;
      const e = t.endTimeIso || t.endTime;

      const start = new Date(s).getTime();
      const end = new Date(e).getTime();

      if (isNaN(start) || isNaN(end)) return null;

      // Clamp values
      const safeStart = Math.max(start, min);
      const safeEnd = Math.min(end, max);

      // If segment is outside range, skip
      if (safeEnd < min || safeStart > max) return null;

      const left = ((safeStart - min) / totalDuration) * 100;
      const width = ((safeEnd - safeStart) / totalDuration) * 100;

      return {
          left: `${left}%`,
          width: `${width}%`,
          data: t
      };
  }).filter(Boolean);
});

const formatTimeLabel = (ts) => {
   if (!ts) return '';
   const d = new Date(ts);
   if (isNaN(d.getTime())) return '';

   // If range > 24h, show date + time
   const range = chartRange.value.max - chartRange.value.min;
   if (range > 86400000) {
       return d.toLocaleDateString([], {month: 'short', day: 'numeric'}) + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
   }
   return d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
};

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
