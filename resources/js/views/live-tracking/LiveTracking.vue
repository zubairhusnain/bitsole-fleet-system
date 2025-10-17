<template>
  <div>
    <div class="row mb-3 g-4">
      <div class="col">
        <h4 class="mb-0">Live Tracking</h4>
        <small class="text-muted">Real-time locations and telemetry</small>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="placeholder-glow">
          <span class="placeholder col-12" style="height:420px"></span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Map component will be integrated here
</script>

<style scoped>
.placeholder { display: block; background-color: rgba(0,0,0,.075); }
</style>