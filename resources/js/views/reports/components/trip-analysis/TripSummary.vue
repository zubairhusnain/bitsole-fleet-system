<template>
  <div>
    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-striped text-nowrap">
            <thead class="table-dark">
              <tr>
                <th rowspan="2" class="ps-3 border-end border-secondary py-3 align-middle">Vehicle ID</th>
                <th rowspan="2" class="border-end border-secondary py-3 align-middle">Vehicles Name</th>
                <th colspan="2" class="text-center border-end border-secondary bg-primary text-white py-2">Travelled Distance (KM)</th>
                <th colspan="2" class="text-center border-end border-secondary bg-primary text-white py-2">Trip Duration (Hours)</th>
                <th colspan="2" class="text-center border-end border-secondary bg-primary text-white py-2">Idling Duration (Hours)</th>
                <th rowspan="2" class="border-end border-secondary py-3 align-middle text-center">Utilisation(%)</th>
                <th colspan="2" class="text-center border-end border-secondary bg-primary text-white py-2">Avg. Fuel Consumption</th>
                <th rowspan="2" class="border-end border-secondary py-3 align-middle text-center">Fuel Refill (L)</th>
                <th rowspan="2" class="border-end border-secondary py-3 align-middle text-center">Fuel Refill (Frequency)</th>
                <th rowspan="2" class="border-end border-secondary py-3 align-middle text-center">Speed (Km/h)</th>
                <th rowspan="2" class="py-3 align-middle text-center">Action</th>
              </tr>
              <tr>
                <th class="text-center border-end border-secondary bg-primary text-white py-2">Total</th>
                <th class="text-center border-end border-secondary bg-primary text-white py-2">Avg. Per Day</th>
                <th class="text-center border-end border-secondary bg-primary text-white py-2">Total</th>
                <th class="text-center border-end border-secondary bg-primary text-white py-2">Avg. Per Day</th>
                <th class="text-center border-end border-secondary bg-primary text-white py-2">Total</th>
                <th class="text-center border-end border-secondary bg-primary text-white py-2">Avg. Per Day</th>
                <th class="text-center border-end border-secondary bg-primary text-white py-2">Avg. Litres</th>
                <th class="text-center border-end border-secondary bg-primary text-white py-2">Avg. KM/L</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="r in rowsTripSummary" :key="r.key">
                <td class="ps-3">{{ r.vehicleId }}</td>
                <td>{{ r.vehicleName }}</td>
                <td class="text-center">{{ r.distTotal }}</td>
                <td class="text-center">{{ r.distAvg }}</td>
                <td class="text-center">{{ r.durTotal }}</td>
                <td class="text-center">{{ r.durAvg }}</td>
                <td class="text-center">{{ r.idleTotal }}</td>
                <td class="text-center">{{ r.idleAvg }}</td>
                <td class="text-center">{{ r.util }}</td>
                <td class="text-center">{{ r.avgLitres }}</td>
                <td class="text-center">{{ r.avgKmL }}</td>
                <td class="text-center">{{ r.fuelRefill }}</td>
                <td class="text-center">{{ r.fuelRefillFreq }}</td>
                <td class="text-center">{{ r.speed }}</td>
                <td class="text-center">
                  <button class="btn btn-sm btn-link text-primary p-0" @click="$emit('view-details', r)"><i class="bi bi-eye"></i></button>
                </td>
              </tr> 
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
          <div class="text-muted small me-auto">Showing {{ rowsTripSummary.length }} results</div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  rowsTripSummary: {
    type: Array,
    required: true
  }
});

defineEmits(['view-details']);
</script>

<style scoped>
thead.table-dark tr th {
  color: #fff;
  border-color: rgba(255,255,255,0.15);
  vertical-align: middle;
  font-weight: 500;
  font-size: 0.85rem;
}
/* Ensure th with specific backgrounds keep their color */
thead.table-dark tr th.bg-primary {
    background-color: #0d6efd !important;
    color: #fff !important;
}
tbody tr td {
  font-size: 13px;
  vertical-align: middle;
}
</style>
