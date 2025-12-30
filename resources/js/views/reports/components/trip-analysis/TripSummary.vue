<template>
  <div>
    <ReportSummary />
    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-striped text-nowrap">
            <thead class="table-dark">
              <tr>
                <th rowspan="2" class="ps-3 border-end border-secondary py-3">Vehicle ID</th>
                <th rowspan="2" class="border-end border-secondary py-3">Vehicles Name</th>
                <th colspan="2" class="text-center border-end border-secondary bg-primary py-2" style="--bs-bg-opacity: .8;">Travelled Distance (KM)</th>
                <th colspan="2" class="text-center border-end border-secondary py-2">Trip Duration (Hours)</th>
                <th colspan="2" class="text-center border-end border-secondary py-2">Idling Duration (Hours)</th>
                <th rowspan="2" class="border-end border-secondary py-3">Utilisation(%)</th>
                <th rowspan="2" class="pe-3 py-3">Avg. Litre/100KM</th>
              </tr>
              <tr>
                <th class="text-center bg-primary py-2" style="--bs-bg-opacity: .8;">Total</th>
                <th class="text-center border-end border-secondary bg-primary py-2" style="--bs-bg-opacity: .8;">Avg. Per Day</th>
                <th class="text-center py-2">Total</th>
                <th class="text-center border-end border-secondary py-2">Avg. Per Day</th>
                <th class="text-center py-2">Total</th>
                <th class="text-center border-end border-secondary py-2">Avg. Per Day</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rowsTripSummary" :key="r.key">
                <td class="ps-3">{{ r.vehicleId }}</td>
                <td>{{ r.vehicleName }}</td>
                <td class="text-center bg-primary-subtle">{{ r.distTotal }}</td>
                <td class="text-center bg-primary-subtle">{{ r.distAvg }}</td>
                <td class="text-center">{{ r.durTotal }}</td>
                <td class="text-center">{{ r.durAvg }}</td>
                <td class="text-center">{{ r.idleTotal }}</td>
                <td class="text-center">{{ r.idleAvg }}</td>
                <td class="text-center">{{ r.util }}</td>
                <td class="text-center pe-3">{{ r.avgFuel }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
          <div class="text-muted small me-auto">Showing 1 to {{ rowsTripSummary.length }} of {{ rowsTripSummary.length }} results</div>
          <nav aria-label="Pagination" class="ms-auto">
            <ul class="pagination pagination-sm mb-0 pagination-app">
              <li class="page-item disabled"><button class="page-link"><i class="bi bi-chevron-left"></i></button></li>
              <li class="page-item active"><button class="page-link">1</button></li>
              <li class="page-item"><button class="page-link"><i class="bi bi-chevron-right"></i></button></li>
            </ul>
          </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import ReportSummary from './ReportSummary.vue';

defineProps({
  rowsTripSummary: {
    type: Array,
    required: true
  }
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; border-color: rgba(255,255,255,0.15); vertical-align: middle; }
tbody tr td { font-size: 13px; vertical-align: middle; }
</style>
