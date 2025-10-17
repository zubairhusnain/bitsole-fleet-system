<template>
  <div>
    <div class="row mb-3">
      <div class="col">
        <h4 class="mb-0">Zone Monitoring</h4>
        <small class="text-muted">Geofences and region activity</small>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-xl-8">
        <div class="card">
          <div class="card-header"><h5 class="card-title mb-0">Map</h5></div>
          <div class="card-body"><div class="placeholder-glow"><span class="placeholder col-12" style="height:300px"></span></div></div>
        </div>
      </div>
      <div class="col-12 col-xl-4">
        <div class="card">
          <div class="card-header"><h5 class="card-title mb-0">Zones</h5></div>
          <div class="card-body">
            <ul class="list-group">
              <li class="list-group-item d-flex justify-content-between align-items-center">Warehouse A <span class="badge text-bg-success">Active</span></li>
              <li class="list-group-item d-flex justify-content-between align-items-center">Hub North <span class="badge text-bg-warning">Idle</span></li>
              <li class="list-group-item d-flex justify-content-between align-items-center">Port C <span class="badge text-bg-danger">Alert</span></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Static skeleton; map integration will be added later
</script>

<style scoped>
.placeholder { display: block; background-color: rgba(0,0,0,.075); }
</style>