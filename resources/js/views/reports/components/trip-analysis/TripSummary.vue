<template>
  <div>
    <ReportSummary />
    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-striped text-nowrap">
            <thead class="table-dark">
              <tr>
                <th rowspan="2" class="ps-3 border-end border-secondary py-3 align-middle">Vehicle ID</th>
                <th rowspan="2" class="border-end border-secondary py-3 align-middle">Vehicles Name</th>
                <th colspan="2" class="text-center border-end border-secondary bg-primary py-2 text-white" style="--bs-bg-opacity: .8;">Travelled Distance (KM)</th>
                <th colspan="2" class="text-center border-end border-secondary bg-success py-2 text-white" style="--bs-bg-opacity: .8;">Trip Duration (Hours)</th>
                <th colspan="2" class="text-center border-end border-secondary bg-danger py-2 text-white" style="--bs-bg-opacity: .8;">Stop Duration (Hours)</th>
                <th colspan="2" class="text-center border-end border-secondary bg-warning py-2 text-dark" style="--bs-bg-opacity: .8;">Idling Duration (Hours)</th>
                <th colspan="2" class="text-center border-end border-secondary bg-info py-2 text-dark" style="--bs-bg-opacity: .8;">Speed (km/h)</th>
                <th colspan="2" class="text-center border-end border-secondary bg-secondary py-2 text-white" style="--bs-bg-opacity: .8;">Fuel Consumption</th>
                <th rowspan="2" class="border-end border-secondary py-3 align-middle">Utilisation(%)</th>
              </tr>
              <tr>
                <th class="text-center bg-primary py-2 text-white" style="--bs-bg-opacity: .6;">Total</th>
                <th class="text-center border-end border-secondary bg-primary py-2 text-white" style="--bs-bg-opacity: .6;">Avg. Per Day</th>
                <th class="text-center bg-success py-2 text-white" style="--bs-bg-opacity: .6;">Total</th>
                <th class="text-center border-end border-secondary bg-success py-2 text-white" style="--bs-bg-opacity: .6;">Avg. Per Day</th>
                <th class="text-center bg-danger py-2 text-white" style="--bs-bg-opacity: .6;">Total</th>
                <th class="text-center border-end border-secondary bg-danger py-2 text-white" style="--bs-bg-opacity: .6;">Avg. Per Day</th>
                <th class="text-center bg-warning py-2 text-dark" style="--bs-bg-opacity: .6;">Total</th>
                <th class="text-center border-end border-secondary bg-warning py-2 text-dark" style="--bs-bg-opacity: .6;">Avg. Per Day</th>
                <th class="text-center bg-info py-2 text-dark" style="--bs-bg-opacity: .6;">Max</th>
                <th class="text-center border-end border-secondary bg-info py-2 text-dark" style="--bs-bg-opacity: .6;">Avg</th>
                <th class="text-center bg-secondary py-2 text-white" style="--bs-bg-opacity: .6;">Total (L)</th>
                <th class="text-center border-end border-secondary bg-secondary py-2 text-white" style="--bs-bg-opacity: .6;">Avg (L/100km)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rowsTripSummary" :key="r.key">
                <td class="ps-3">{{ r.vehicleId }}</td>
                <td>{{ r.vehicleName }}</td>
                <td class="text-center bg-primary-subtle">{{ r.distTotal }}</td>
                <td class="text-center bg-primary-subtle">{{ r.distAvg }}</td>
                <td class="text-center bg-success-subtle">{{ r.durTotal }}</td>
                <td class="text-center bg-success-subtle">{{ r.durAvg }}</td>
                <td class="text-center bg-danger-subtle">{{ r.stopTotal || '-' }}</td>
                <td class="text-center bg-danger-subtle">{{ r.stopAvg || '-' }}</td>
                <td class="text-center bg-warning-subtle">{{ r.idleTotal }}</td>
                <td class="text-center bg-warning-subtle">{{ r.idleAvg }}</td>
                <td class="text-center bg-info-subtle">{{ r.speedMax || '-' }}</td>
                <td class="text-center bg-info-subtle">{{ r.speedAvg || '-' }}</td>
                <td class="text-center bg-secondary-subtle">{{ r.fuelTotal || '-' }}</td>
                <td class="text-center bg-secondary-subtle pe-3">{{ r.avgFuel }}</td>
                <td class="text-center">{{ r.util }}</td>
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
thead.table-dark tr th {
  /* background-color: #0b0f28; */ /* Removed to allow bg-* classes to work */
  color: #fff;
  border-color: rgba(255,255,255,0.15);
  vertical-align: middle;
}
/* Ensure th with specific backgrounds keep their color but might need text color adjustment */
thead.table-dark tr th.bg-warning,
thead.table-dark tr th.bg-info {
    color: #000 !important;
}
tbody tr td { font-size: 13px; vertical-align: middle; }
</style>
 