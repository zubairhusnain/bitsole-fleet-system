<template>
  <div>
    <div class="row mb-3">
      <div class="col">
        <h4 class="mb-0">Reports & Analytics</h4>
        <small class="text-muted">Insights and trends</small>
      </div>
      <div class="col-auto">
        <button class="btn btn-outline-secondary">Export</button>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-12 col-lg-4" v-for="i in 3" :key="i">
        <div class="card">
          <div class="card-header"><h6 class="mb-0">Metric {{ i }}</h6></div>
          <div class="card-body"><div class="placeholder-glow"><span class="placeholder col-12" style="height:140px"></span></div></div>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header"><h6 class="mb-0">Performance Over Time</h6></div>
      <div class="card-body"><div class="placeholder-glow"><span class="placeholder col-12" style="height:280px"></span></div></div>
    </div>
  </div>
</template>

<script setup>
// Chart placeholders; integrate ApexCharts or similar later
</script>

<style scoped>
.placeholder { display: block; background-color: rgba(0,0,0,.075); }
</style>