<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Overview</RouterLink></li>
        <li class="breadcrumb-item">Reports</li>
        <li class="breadcrumb-item active" aria-current="page">Analytics Overview</li>
      </ol>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center">
        <h4 class="mb-0">Analytics Overview</h4>
        <button
          type="button"
          class="btn btn-link p-0 ms-2 text-muted"
          @click="showInfo = !showInfo"
        >
          <i class="bi bi-info-circle"></i>
        </button>
      </div>
    </div>
    <div v-if="showInfo" class="mb-3">
      <div class="card border-0 bg-light">
        <div class="card-header bg-transparent py-2">
          <div class="fw-semibold small">About this page</div>
        </div>
        <div class="card-body pt-2 pb-3 small">
          <p class="mb-2">
            Analytics Overview provides a high-level summary of fleet performance over time,
            bringing together utilisation, fuel efficiency and safety indicators in one place.
          </p>
          <p class="mb-2">
            Use the date range and group filters to focus on a subset of vehicles and compare how their key metrics change
            across different periods. This helps you see whether recent changes in routes, drivers or policies are having
            a positive or negative impact.
          </p>
          <p class="mb-0">
            This page is ideal for management dashboards and quick health checks of your fleet, giving you a simple view
            of whether things are generally improving, stable or need attention without diving into raw data tables.
          </p>
        </div>
      </div>
    </div>
    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Search Option </h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small">Start Date</label>
            <input type="datetime-local" class="form-control" v-model="startDate" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">End Date</label>
            <input type="datetime-local" class="form-control" v-model="endDate" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Vehicle Group</label>
            <select class="form-select">
              <option>-- Select Group --</option>
              <option>All Vehicles</option>
              <option>Group A</option>
              <option>Group B</option>
            </select>
          </div>
          <div class="col-12 col-md-3 text-md-end">
            <button class="btn btn-app-dark w-100">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-3">
      <div class="col-12 col-lg-4">
        <div class="card border rounded-3 shadow-0 h-100">
          <div class="card-header"><h6 class="mb-0">Fleet Utilisation</h6></div>
          <div class="card-body d-flex align-items-center justify-content-center">
             <div class="text-center">
                <h2 class="fw-bold text-primary">85%</h2>
                <div class="small text-muted">Average Daily Utilisation</div>
             </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <div class="card border rounded-3 shadow-0 h-100">
          <div class="card-header"><h6 class="mb-0">Fuel Efficiency</h6></div>
          <div class="card-body d-flex align-items-center justify-content-center">
             <div class="text-center">
                <h2 class="fw-bold text-success">12.5 km/L</h2>
                <div class="small text-muted">Average Fleet Consumption</div>
             </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <div class="card border rounded-3 shadow-0 h-100">
          <div class="card-header"><h6 class="mb-0">Safety Score</h6></div>
          <div class="card-body d-flex align-items-center justify-content-center">
             <div class="text-center">
                <h2 class="fw-bold text-info">92/100</h2>
                <div class="small text-muted">Fleet Safety Rating</div>
             </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0">
      <div class="card-header"><h6 class="mb-0">Performance Over Time</h6></div>
      <div class="card-body">
        <div class="placeholder-glow">
          <span class="placeholder col-12 bg-light rounded" style="height:280px; display:flex; align-items:center; justify-content:center; color:#adb5bd;">
            Chart Visualization Placeholder
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const startDate = ref('');
const endDate = ref('');
const showInfo = ref(false);
</script>

<style scoped>
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
</style>
