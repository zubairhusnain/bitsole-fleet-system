<template>
  <div class="card border rounded-3 shadow-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 table-striped">
          <thead class="table-dark">
            <tr>
              <th class="ps-3">Vehicle ID</th>
              <th>Date</th>
              <th class="text-end">Travelled Distance</th>
              <th class="text-end">Trip Duration</th>
              <th class="text-end">Idle Duration</th>
              <th class="text-end pe-3">Idle Percentage</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rowsMonthlyVehicleList" :key="r.key">
              <td class="ps-3 text-primary">{{ r.vehicle }}</td>
              <td>{{ r.date }}</td>
              <td class="text-end">{{ r.distance }}</td>
              <td class="text-end">{{ r.trip }}</td>
              <td class="text-end">{{ r.idle }}</td>
              <td class="text-end pe-3">{{ r.idlePct }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between align-items-center p-2 small text-muted">
        <div>Showing {{ rowsMonthlyVehicleList.length }} results</div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  rowsMonthlyVehicleList: {
    type: Array,
    required: true
  }
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #886654 !important; color: #fff; border-color: rgba(255,255,255,0.15); vertical-align: middle; }
tbody tr td { font-size: 13px; vertical-align: middle; }
</style>
