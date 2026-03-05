<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Overview</RouterLink></li>
        <li class="breadcrumb-item">Reports</li>
        <li class="breadcrumb-item active" aria-current="page">Fuel Efficiency Report</li>
      </ol>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center">
        <h4 class="mb-0 fw-semibold">Fuel Efficiency Report</h4>
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
            Fuel Efficiency Report calculates fuel efficiency and consumption based on manual fuel entries and distance traveled.
          </p>
          <p class="mb-0">
            It provides key metrics such as Total Fuel Consumed, Fuel Efficiency (KM/L), Consumption (L/100KM), and Cost per Kilometer.
            Ensure that fuel entries (refills) are logged correctly in the Fuel Management section for accurate reporting.
          </p>
        </div>
      </div>
    </div>

    <UiAlert :show="!!errorMessage" :message="errorMessage" variant="danger" dismissible @dismiss="errorMessage = null" />

    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body pt-2">
        <form @submit.prevent="fetchReport">
          <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold text-muted">Duration</label>
              <div class="input-group">
                <input type="date" class="form-control" v-model="fromDate" required />
                <span class="input-group-text bg-white border-start-0 border-end-0">to</span>
                <input type="date" class="form-control" v-model="toDate" required />
              </div>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small fw-semibold text-muted">Vehicle</label>
              <select class="form-select" v-model="filterVehicleId">
                <option value="">-- All Vehicles --</option>
                <option v-for="opt in deviceOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
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
                <th class="ps-3">Vehicle</th>
                <th class="text-center">Total Distance</th>
                <th class="text-center">Total Fuel</th>
                <th class="text-center">Total Cost</th>
                <th class="text-center">Efficiency (KM/L)</th>
                <th class="text-center">Consumption (L/100KM)</th>
                <th class="text-center">Cost / KM</th>
                <th class="text-center pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="8" class="text-center py-4">Loading data...</td>
              </tr>
              <tr v-else-if="rows.length === 0">
                <td colspan="8" class="text-center py-4">No data found</td>
              </tr>
              <tr v-else v-for="(row, index) in rows" :key="index">
                <td class="ps-3 fw-semibold">{{ row.vehicleId }}</td>
                <td class="text-center">{{ row.totalDistance }} km</td>
                <td class="text-center">{{ row.totalFuel }} L</td>
                <td class="text-center">{{ row.totalCost }}</td>
                <td class="text-center">
                    <span class="badge bg-light text-dark border">{{ row.efficiency }}</span>
                </td>
                <td class="text-center">
                    <span class="badge bg-light text-dark border">{{ row.consumption }}</span>
                </td>
                <td class="text-center">{{ row.costPerKm }}</td>
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
const filterVehicleId = ref('');
const deviceOptions = ref([]);

// Set default dates (one week)
onMounted(async () => {
  const now = new Date();
  const oneWeekAgo = new Date();
  oneWeekAgo.setDate(now.getDate() - 30); // Default to last 30 days for better fuel data

  fromDate.value = oneWeekAgo.toISOString().split('T')[0];
  toDate.value = now.toISOString().split('T')[0];

  await loadDeviceOptions();
  fetchReport();
});

const loadDeviceOptions = async () => {
  try {
    const res = await axios.get('/web/reports/device-options?includeAll=true');
    deviceOptions.value = res.data.options || res.data || [];
  } catch (e) {
    console.error('Failed to load device options', e);
  }
};

const fetchReport = async () => {
  loading.value = true;
  errorMessage.value = null;
  try {
    const params = {
        from_date: fromDate.value,
        to_date: toDate.value,
    };
    if (filterVehicleId.value) {
        params.vehicle_ids = [filterVehicleId.value];
    }
    const response = await axios.get('/web/reports/effective-fuel', { params });
    rows.value = response.data.rows || [];
  } catch (error) {
    console.error('Error fetching effective fuel report:', error);
    errorMessage.value = 'Failed to load report data. Please try again.';
    rows.value = [];
  } finally {
    loading.value = false;
  }
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