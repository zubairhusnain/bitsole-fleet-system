<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Utilisation Report</li>
      </ol>
    </div> 
    <h4 class="mb-3">Utilisation Report</h4>
    <UiAlert :show="!!errorMessage" :message="errorMessage" variant="danger" dismissible @dismiss="errorMessage = ''" />
    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label text-muted small fw-semibold">Duration</label>
            <div class="input-group">
               <input type="date" v-model="fromDate" class="form-control" />
               <span class="input-group-text bg-white">-</span>
               <input type="date" v-model="toDate" class="form-control" />
            </div>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label text-muted small fw-semibold">Vehicle</label>
            <select class="form-select" v-model="selectedDeviceId">
              <option value="" disabled>--Select an Vehicle--</option>
              <option v-for="opt in deviceOptions" :key="opt.id" :value="opt.id">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label text-muted small fw-semibold">Type</label>
            <input type="text" class="form-control" value="Movement" />
          </div>
          <div class="col-12 col-md-1">
            <button class="btn btn-info text-white w-100 fw-semibold" style="background-color: #0ea5e9; border: none;" @click="fetchReport" :disabled="loading">
              Submit
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold">Utilisation Report Result</h6>
        <div class="d-flex align-items-right gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="d-inline-block rounded-1" style="width: 12px; height: 12px; background-color: #0ea5e9;"></span>
                <span class="small text-muted">With Movement</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="d-inline-block rounded-1" style="width: 12px; height: 12px; background-color: #e5e7eb;"></span>
                <span class="small text-muted">Without Movement</span>
            </div>
        </div>
      </div>
      <div class="card-body bg-white">
        <div class="row g-3">
            <div class="col-12 col-md-3">
                <div class="bg-light rounded-2 p-3 h-100">
                    <div class="small fw-bold text-dark mb-1">Vehicle ID</div>
                    <div class="small text-muted">#{{ summary.vehicleIdDisplay }}</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="bg-light rounded-2 p-3 h-100">
                    <div class="small fw-bold text-dark mb-1">Device ID</div>
                    <div class="small text-muted">#{{ summary.deviceId }}</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="bg-light rounded-2 p-3 h-100">
                    <div class="small fw-bold text-dark mb-1">Duration</div>
                    <div class="small text-muted">{{ summary.durationDisplay }}</div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="bg-light rounded-2 p-3 h-100">
                    <div class="small fw-bold text-dark mb-1">Total Days</div>
                    <div class="small text-muted">{{ summary.totalDays }} Days</div>
                </div>
            </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table align-middle mb-0 table-hover">
            <thead class="table-dark">
              <tr>
                <th class="py-3 ps-3">Date/Day</th>
                <th class="py-3">Usage</th>
                <th class="py-3">Total Movement Time</th>
                <th class="py-3">Distance</th>
                <th class="py-3">Hourly Activity</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="5" class="text-center py-4">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </td>
              </tr>
              <tr v-for="row in rows" :key="row.day">
                <td class="ps-3 py-3 fw-medium text-dark">{{ row.day }}</td>
                <td class="py-3"><span class="badge bg-success-subtle text-success px-3 py-2 rounded-1" style="min-width: 50px;">{{ row.usage }}</span></td>
                <td class="py-3">{{ row.move }}</td>
                <td class="py-3">{{ row.dist }}</td>
                <td class="py-3">
                  <div class="d-flex gap-1">
                    <div v-for="(active, i) in row.hours" :key="i" class="d-flex flex-column align-items-center" style="width: 14px;">
                        <span class="text-muted mb-1" style="font-size: 8px; line-height: 1;">{{ String(i + 1).padStart(2, '0') }}</span>
                        <div class="rounded-1" :style="{ width: '100%', height: '12px', backgroundColor: active ? '#0ea5e9' : '#e5e7eb' }"></div>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">Showing 1 to 10 of 10 results</div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item disabled"><button class="page-link">‹</button></li>
            <li class="page-item active"><button class="page-link">1</button></li>
            <li class="page-item"><button class="page-link">2</button></li>
            <li class="page-item"><button class="page-link">3</button></li>
            <li class="page-item"><button class="page-link">4</button></li>
            <li class="page-item"><button class="page-link">5</button></li>
            <li class="page-item"><button class="page-link">›</button></li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const deviceOptions = ref([]);
const selectedDeviceId = ref('');
const fromDate = ref(new Date(Date.now() - 6 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));
const toDate = ref(new Date().toISOString().slice(0, 10));
const loading = ref(false);
const errorMessage = ref(null);

const summary = ref({
  vehicleIdDisplay: '',
  deviceId: '',
  durationDisplay: '',
  totalDays: 0,
});

const rows = ref([]);

async function loadDeviceOptions() {
  try {
    const res = await axios.get('/web/reports/device-options');
    deviceOptions.value = res.data.options || [];
    if (!selectedDeviceId.value && deviceOptions.value.length > 0) {
      selectedDeviceId.value = deviceOptions.value[0].id;
    }
  } catch (e) {
    console.error('Failed to load devices', e);
  }
}

async function fetchReport() {
  if (!selectedDeviceId.value) {
    errorMessage.value = 'Please select a vehicle for this report.';
    return;
  }
  loading.value = true;
  errorMessage.value = null;
  rows.value = [];
  try {
    const params = {
      from_date: fromDate.value,
      to_date: toDate.value,
      device_ids: [selectedDeviceId.value],
    };

    const response = await axios.get('/web/reports/utilisation', { params });
    const data = response.data;

    rows.value = data.rows || [];
    summary.value = data.summary || {
        vehicleIdDisplay: '',
        deviceId: '',
        durationDisplay: '',
        totalDays: 0
    };

    // Override vehicleIdDisplay with uniqueId if available locally
    const opt = deviceOptions.value.find(x => x.id === selectedDeviceId.value);
    if (opt && opt.uniqueId) {
        summary.value.vehicleIdDisplay = opt.uniqueId;
    }

  } catch (e) {
    console.error('Failed to fetch utilisation report', e);
    errorMessage.value = e.response?.data?.message || 'Failed to fetch report data.';
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await loadDeviceOptions();
  if (selectedDeviceId.value) {
    fetchReport();
  }
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; font-weight: 500; font-size: 0.875rem; }
tbody tr td { font-size: 13px; vertical-align: middle; }
.panel .card-body { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.card-header { border-bottom: 1px solid #f3f4f6; }
</style>
