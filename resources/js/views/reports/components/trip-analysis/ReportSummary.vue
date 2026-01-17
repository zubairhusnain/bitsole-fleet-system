<template>
  <div class="card border rounded-3 shadow-0 mb-3">
    <div class="card-header bg-white border-bottom-0 pt-3 ps-3"><h6 class="mb-0 fw-bold">Trip Analysis Report Result</h6></div>
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
          <div class="fw-semibold">{{ formatTimeRange(dateRange?.start, dateRange?.end) }}</div>
        </div>
        <div class="col-12 col-md-3">
          <div class="small text-muted">View Type</div>
          <div class="fw-semibold">{{ viewType }}</div>
        </div>
        <div class="col-12">
          <div class="small text-muted">Remarks</div>
          <div class="fw-semibold">{{ remarksText }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { formatDateTime } from '../../../../utils/datetime';

const props = defineProps({
  summary: Object,
  vehicle: Object,
  dateRange: Object,
  viewType: {
    type: String,
    default: 'Daily Breakdown'
  }
});

const remarksText = computed(() => {
  switch (props.viewType) {
    case 'Daily Breakdown':
    case 'Daily Breakdown (with map)':
      return 'Summary data (Total Distance, Duration, Idle) is calculated by aggregating all individual trips and stops for the selected vehicle within the date range.';
    case 'Summary':
    case 'Daily Summary':
      return 'Summary data is derived by summing daily totals for the selected vehicle. Each day\'s values are pre-aggregated from that day\'s trips and stops.';
    case 'Daily Summary List':
      return 'Summary data represents the grand total of all daily records shown in the list. It aggregates performance metrics across all listed vehicles and days.';
    case 'Monthly Summary':
      return 'Summary data is derived by summing monthly totals for the selected vehicle. Each month\'s values are pre-aggregated from all daily activities within that month.';
    case 'Monthly Summary List':
      return 'Summary data represents the grand total of all monthly records shown in the list. It aggregates performance metrics across all listed vehicles and months.';
    case 'Trip Summary':
      return 'Summary data is calculated by summing the specific metrics (Distance, Duration) of every individual trip log displayed in the report table below.';
    default:
      return 'Average fuel consumption calculated up to 6 months of data. Fuel refill amount shown for duration selected. Fuel refill amount does not imply fuel consumed in the same duration selected.';
  }
});

const formatTimeRange = (start, end) => {
  return `${formatDateTime(start)} - ${formatDateTime(end)}`;
};
</script>
