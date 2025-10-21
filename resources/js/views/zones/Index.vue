<template>
  <div>
    <div class="row mb-3">
      <div class="col">
        <h4 class="mb-0">Zone Management</h4>
        <small class="text-muted">Create and manage geofences</small>
      </div>
      <div class="col-auto">
        <button class="btn btn-primary">Add Zone</button>
      </div>
    </div>

    <ComingSoon class="mb-3" />

    <div class="row g-3">
      <div class="col-12 col-xl-8">
        <div class="card">
          <div class="card-header"><h6 class="mb-0">Map</h6></div>
          <div class="card-body"><div class="placeholder-glow"><span class="placeholder col-12" style="height:360px"></span></div></div>
        </div>
      </div>
      <div class="col-12 col-xl-4">
        <div class="card">
          <div class="card-header"><h6 class="mb-0">Zones</h6></div>
          <div class="card-body">
            <ul class="list-group">
              <li class="list-group-item">Warehouse A</li>
              <li class="list-group-item">Hub North</li>
              <li class="list-group-item">Port C</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import ComingSoon from '../../components/ComingSoon.vue';
// Management actions to be wired
</script>

<style scoped>
.placeholder { display: block; background-color: rgba(0,0,0,.075); }
</style>