<template>
  <div>
    <ReportSummary :summary="summaryData" :vehicle="vehicleInfo" :dateRange="{start: startDate, end: endDate}" />
    <ChartAndKPIs :summary="summaryData" :trips="rowsDailyTrips" :startDate="startDate" :endDate="endDate" />
    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-striped w-100 text-nowrap">
            <thead class="table-dark">
              <tr> 
                <th class="py-2 ps-3">Date</th>
                <th class="py-2">Start Time</th>
                <th class="py-2">Start Location</th>
                <th class="py-2">End Time</th>
                <th class="py-2">End Location</th>
                <th class="py-2 pe-3 text-end">Travelled Dist</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rowsDailyTrips" :key="r.key">
                <td class="ps-3"><a href="#" class="text-decoration-none" :class="r.key === 1 ? 'text-primary fw-semibold' : ''">{{ r.date }}</a></td>
                <td>{{ r.startTime }}</td>
                <td class="text-primary">{{ r.startLocation }}</td>
                <td>{{ r.endTime }}</td>
                <td class="text-primary">{{ r.endLocation }}</td>
                <td class="text-end pe-3">{{ r.distance }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
          <div class="text-muted small me-auto">Showing {{ rowsDailyTrips.length }} results</div>
        </div>
    </div>
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
