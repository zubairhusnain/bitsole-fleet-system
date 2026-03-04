<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Ranking Report</li>
      </ol>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center">
        <h4 class="mb-0">Vehicle Ranking Report</h4>
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
          <div class="fw-semibold small">About this report</div>
        </div>
        <div class="card-body pt-2 pb-3 small">
          <p class="mb-2">
            Vehicle Ranking Report compares the driving behaviour of vehicles over the selected duration
            using a simple points-based safety score. Every vehicle starts with 100 points and loses points
            whenever harsh or unsafe events are detected from the tracking data.
          </p>
          <p class="mb-2">
            The score is reduced for hard acceleration, hard braking, hard cornering and speeding events.
            More frequent or more severe events lead to a lower score, while vehicles with smoother driving
            and fewer violations keep more of their original points.
          </p>
          <p class="mb-2">
            Use the Type option to switch between different views: Ranking by Percentage shows performance
            as an overall score out of 100%, Ranking by Total Points focuses on the remaining safety score,
            and Driving Behaviour highlights the underlying event counts to help explain why a vehicle is
            ranked higher or lower.
          </p>
          <p class="mb-0">
            This report is useful for identifying your safest and riskiest vehicles or drivers, tracking the
            impact of training or policies over time, and quickly spotting vehicles that generate many harsh
            events even if they do not drive long distances.
          </p>
        </div>
      </div>
    </div>

    <UiAlert :show="!!errorMessage" :message="errorMessage" variant="danger" dismissible @dismiss="errorMessage = null" />

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
                <th class="text-center" data-bs-toggle="tooltip" title="Total number of hard acceleration events">
                  Total HA
                </th>
                <th class="text-center" data-bs-toggle="tooltip" title="Total number of hard braking events">
                  Total HB
                </th>
                <th class="text-center" data-bs-toggle="tooltip" title="Total number of hard cornering events">
                  Total HC
                </th>
                <th class="text-center" data-bs-toggle="tooltip" title="Total number of speed limit violations">
                  Total SV
                </th>
                <th class="text-center" data-bs-toggle="tooltip" title="Driver safety score (starts at 100)">
                  Points
                </th>
                <th class="text-center" data-bs-toggle="tooltip" title="Overall performance rating">
                  Percentage
                </th>
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
import UiAlert from '../../components/UiAlert.vue';
import axios from 'axios';

const showInfo = ref(false);

const rows = ref([]);
const loading = ref(false);
const errorMessage = ref(null);
const fromDate = ref('');
const toDate = ref('');
const rankingType = ref('points');
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
  errorMessage.value = null;
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
    errorMessage.value = 'Failed to load vehicle ranking data. Please try again.';
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
.pagination-app .page-item.active .page-link { background-color: var(--brand-primary); border-color: var(--brand-primary); color: white; }
.pagination-app .page-link { color: #333; }
</style>
