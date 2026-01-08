<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Ranking Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Vehicle Ranking Report <i class="bi bi-info-circle text-muted ms-2" style="font-size: 0.6em; cursor: help;" data-bs-toggle="tooltip" data-bs-html="true" title="<div class='text-start'><strong>Vehicle Ranking System</strong><br>Every vehicle starts with <strong>100 points</strong>.<br>Points are deducted for unsafe events:<br>• Hard Acceleration: -5 pts<br>• Hard Braking: -5 pts<br>• Hard Cornering: -5 pts<br>• Speeding: -10 pts<br>Higher score indicates safer driving.</div>"></i></h4>
    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body pt-2">
        <form @submit.prevent="fetchRanking">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-md-3">
              <label class="form-label small fw-semibold text-muted">Duration</label>
              <div class="input-group">
                <input type="date" class="form-control" v-model="fromDate" required />
                <span class="input-group-text bg-white border-start-0 border-end-0">to</span>
                <input type="date" class="form-control" v-model="toDate" required />
              </div>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label small fw-semibold text-muted">Vehicle</label>
              <select class="form-select" v-model="filterVehicleId">
                <option value="">-- All Vehicles --</option>
                <option v-for="opt in deviceOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label small fw-semibold text-muted">Type</label>
              <select class="form-select" v-model="rankingType">
                <option value="percentage">Ranking by Percentage</option>
                <option value="points">Ranking by Total Points</option>
                <option value="behaviour">Driving Behaviour</option>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <button type="submit" class="btn btn-info text-white w-100" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Submit
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-striped w-100 text-nowrap">
            <thead class="table-dark">
              <tr>
                <th class="ps-3">Vehicle ID</th>
                <th>Type/Model</th>
                <th class="text-center">Distance</th>
                <th class="text-center">Duration</th>
                <th class="text-center" data-bs-toggle="tooltip" title="Total number of hard acceleration events">Total HA <i class="bi bi-info-circle text-muted ms-1" style="font-size: 0.8em;"></i></th>
                <th class="text-center" data-bs-toggle="tooltip" title="Total number of hard braking events">Total HB <i class="bi bi-info-circle text-muted ms-1" style="font-size: 0.8em;"></i></th>
                <th class="text-center" data-bs-toggle="tooltip" title="Total number of hard cornering events">Total HC <i class="bi bi-info-circle text-muted ms-1" style="font-size: 0.8em;"></i></th>
                <th class="text-center" data-bs-toggle="tooltip" title="Total number of speed limit violations">Total SV <i class="bi bi-info-circle text-muted ms-1" style="font-size: 0.8em;"></i></th>
                <th class="text-center" data-bs-toggle="tooltip" title="Driver safety score (starts at 100)">Points <i class="bi bi-info-circle text-muted ms-1" style="font-size: 0.8em;"></i></th>
                <th class="text-center" data-bs-toggle="tooltip" title="Overall performance rating">Percentage <i class="bi bi-info-circle text-muted ms-1" style="font-size: 0.8em;"></i></th>
                <th class="text-center">Rank</th>
                <th class="text-center pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="12" class="text-center py-4">Loading data...</td>
              </tr>
              <tr v-else-if="rows.length === 0">
                <td colspan="12" class="text-center py-4">No data found</td>
              </tr>
              <tr v-else v-for="(row, index) in rows" :key="index">
                <td class="ps-3">{{ row.vehicleId }}</td>
                <td>{{ row.typeModel }}</td>
                <td class="text-center">{{ row.distance }}</td>
                <td class="text-center">{{ row.duration }}</td>
                <td class="text-center">{{ row.totalHA }}</td>
                <td class="text-center">{{ row.totalHB }}</td>
                <td class="text-center">{{ row.totalHC }}</td>
                <td class="text-center">{{ row.totalSV }}</td>
                <td class="text-center fw-bold" :class="row.points < 0 ? 'text-danger' : 'text-dark'">{{ row.points }}</td>
                <td class="text-center">
                  <span class="badge rounded-1 px-2 py-1 text-dark" :style="{ backgroundColor: getPercentageColor(row.percentage) }">{{ row.percentage }}%</span>
                </td>
                <td class="text-center">{{ row.rank }}</td>
                <td class="text-center pe-3">
                  <button class="btn btn-sm p-0 text-dark me-2"><i class="bi bi-file-earmark-excel fs-5"></i></button>
                  <button class="btn btn-sm p-0 text-info"><i class="bi bi-file-earmark-pdf fs-5"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
        <div class="text-muted small me-auto">Showing {{ rows.length }} results</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';

const rows = ref([]);
const loading = ref(false);
const fromDate = ref('');
const toDate = ref('');
const rankingType = ref('percentage');
const filterVehicleId = ref('');
const deviceOptions = ref([]);

// Set default dates (one week)
onMounted(async () => {
  const now = new Date();
  const oneWeekAgo = new Date();
  oneWeekAgo.setDate(now.getDate() - 7);

  fromDate.value = oneWeekAgo.toISOString().split('T')[0];
  toDate.value = now.toISOString().split('T')[0];

  // Initialize tooltips
  nextTick(() => {
    if (window.bootstrap && window.bootstrap.Tooltip) {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new window.bootstrap.Tooltip(tooltipTriggerEl);
      });
    }
  });

  await loadDeviceOptions();

  fetchRanking();
});

const loadDeviceOptions = async () => {
  try {
    const res = await axios.get('/web/reports/device-options?includeAll=true');
    deviceOptions.value = res.data.options || res.data || [];
  } catch (e) {
    console.error('Failed to load device options', e);
  }
};

const fetchRanking = async () => {
  loading.value = true;
  try {
    const params = {
        from_date: fromDate.value,
        to_date: toDate.value,
        type: rankingType.value
    };
    if (filterVehicleId.value) {
        params.vehicle_ids = [filterVehicleId.value];
    }
    const response = await axios.get('/web/reports/vehicle-ranking', { params });
    rows.value = response.data;
  } catch (error) {
    console.error('Error fetching ranking:', error);
    // Keep mock data or clear it? Let's clear it to show error state if needed, but for now just log
    rows.value = [];
  } finally {
    loading.value = false;
  }
};

const getPercentageColor = (val) => {
  if (val >= 70) return '#d4edda'; // light green
  if (val >= 40) return '#fff3cd'; // light yellow
  return '#f8d7da'; // light red
};
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; vertical-align: middle; font-weight: 500; font-size: 13px; border-bottom: none; }
tbody tr td { font-size: 13px; color: #333; }
.badge { font-weight: 600; font-size: 12px; }
.form-label { font-size: 0.85rem; }
.pagination-app .page-item.active .page-link { background-color: #0b0f28; border-color: #0b0f28; color: white; }
.pagination-app .page-link { color: #333; }
</style>
