<template>
  <div class="card border rounded-3 shadow-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 table-striped">
          <thead class="table-dark">
            <tr>
              <th class="ps-3">Date</th>
              <th>Vehicle ID</th>
              <th class="text-end">Travelled Distance</th>
              <th class="text-end">Trip Duration</th>
              <th class="text-end">Idle Duration</th>
              <th class="text-end pe-3">Idle Percentage</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rowsDailyVehicleList" :key="r.key">
              <td class="ps-3">{{ r.date }}</td>
              <td class="text-primary">{{ r.vehicle }}</td>
              <td class="text-end">{{ r.distance }}</td>
              <td class="text-end">{{ r.trip }}</td>
              <td class="text-end">{{ r.idle }}</td>
              <td class="text-end pe-3">{{ r.idlePct }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
      <div class="text-muted small me-auto">Showing 1 to 16 of 1079 results</div>
      <nav aria-label="Pagination" class="ms-auto">
        <ul class="pagination pagination-sm mb-0 pagination-app">
          <li class="page-item disabled"><button class="page-link"><i class="bi bi-chevron-left"></i></button></li>
          <li class="page-item active"><button class="page-link">1</button></li>
          <li class="page-item"><button class="page-link">2</button></li>
          <li class="page-item"><button class="page-link">3</button></li>
          <li class="page-item"><button class="page-link">4</button></li>
          <li class="page-item"><button class="page-link">5</button></li>
          <li class="page-item"><button class="page-link"><i class="bi bi-chevron-right"></i></button></li>
        </ul>
      </nav>
    </div>
  </div>
</template>

<script setup>
defineProps({
  rowsDailyVehicleList: {
    type: Array,
    required: true
  }
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; border-color: rgba(255,255,255,0.15); vertical-align: middle; }
tbody tr td { font-size: 13px; vertical-align: middle; }
</style>
