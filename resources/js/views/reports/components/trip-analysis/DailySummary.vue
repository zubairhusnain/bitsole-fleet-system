<template>
  <div>
    <ChartAndKPIs :summary="summary" :precomputedChartData="chartData" :startDate="startDate" :endDate="endDate" />
  </div>
</template>

<script setup>
import ChartAndKPIs from './ChartAndKPIs.vue';

defineProps({
  rowsDailySummary: {
    type: Array,
    required: true
  },
  summary: {
    type: Object,
    default: () => ({}) 
  },
  chartData: {
    type: Array,
    default: () => []
  },
  vehicle: {
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
</style>
