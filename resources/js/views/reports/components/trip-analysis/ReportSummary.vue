<template>
  <div class="card border rounded-3 shadow-0 mb-3">
    <div class="card-header bg-white border-bottom-0 pt-3 ps-3"><h6 class="mb-0 fw-bold">Vehicle Activity Report Result</h6></div>
    <div class="card-body pt-0">
      <div class="row g-3">
        <div class="col-12 col-md-3">
          <div class="small text-muted">Vehicle Name</div>
          <div class="fw-semibold">{{ vehicle?.name || '-' }}</div>
        </div>
        <div class="col-12 col-md-3">
          <div class="small text-muted">Device ID</div>
          <div class="fw-semibold">{{ vehicle?.uniqueId || vehicle?.device_id || '-' }}</div>
        </div>
        <div class="col-12 col-md-3"> 
          <div class="small text-muted">Duration</div>
          <div class="fw-semibold">{{ formatTime(dateRange?.start) }} - {{ formatTime(dateRange?.end) }}</div>
        </div>
        <div class="col-12 col-md-3">
          <div class="small text-muted">View Type</div>
          <div class="fw-semibold">Daily Breakdown</div>
        </div>
        <div class="col-12">
          <div class="small text-muted">Remarks</div>
          <div class="fw-semibold">Average fuel consumption calculated up to 6 months of data. Fuel refill amount shown for duration selected. Fuel refill amount does not imply fuel consumed in the same duration selected.</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  summary: Object,
  vehicle: Object,
  dateRange: Object
});

const formatTime = (t) => {
  if (!t) return '';
  return t.replace('T', ' ');
};
</script>
