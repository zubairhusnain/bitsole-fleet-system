<template>
  <div> 
    <ReportSummary :summary="summaryData" :vehicle="vehicleInfo" :dateRange="{start: startDate, end: endDate}" />
    <ChartAndKPIs :summary="summaryData" :trips="rowsDailyTrips" :stops="rowsDailyStops" :startDate="startDate" :endDate="endDate" />
  </div>
</template>

<script setup>
import ReportSummary from './ReportSummary.vue';
import ChartAndKPIs from './ChartAndKPIs.vue';

defineProps({
  rowsDailyTrips: {
    type: Array,
    required: true
  },
  rowsDailyStops: {
    type: Array,
    default: () => []
  },
  summaryData: {
    type: Object,
    default: () => ({})
  },
  vehicleInfo: {
    type: Object,
    default: () => null
  },
  startDate: String,
  endDate: String
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; border-color: rgba(255,255,255,0.15); vertical-align: middle; }
tbody tr td { font-size: 13px; vertical-align: middle; }
.badge.border { border: 1px solid currentColor; }
</style>
