<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Utilisation Report</li>
      </ol>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center">
        <h4 class="mb-0">Utilisation Report</h4>
        <button
          type="button"
          class="btn btn-link p-0 ms-2 text-muted"

          @click="showInfo = !showInfo"
        >
          <i class="bi bi-info-circle"></i>
        </button>
      </div>
    </div>
    <div v-if="showInfo" class="mb-3" >
      <div class="card border-0 bg-light">
        <div class="card-header bg-transparent py-2">
          <div class="fw-semibold small">About this report</div>
        </div>
        <div class="card-body pt-2 pb-3 small">
          <p class="mb-2">
            Utilisation Report shows how consistently a vehicle was used over the selected period.
            It summarises days with movement or engine-on time, distance covered and hourly activity so you can see which
            days were busy and which were quiet.
          </p>
          <p class="mb-2">
            Switch between Movement and Engine Hours to focus on driving time or engine running time, and use the daily
            breakdown to spot days with unusually low or high utilisation. This makes it easier to see if vehicles are
            being underused, overworked or used in short, inefficient bursts.
          </p>
          <p class="mb-0">
            This report is useful for balancing workload across your fleet, planning rotations and deciding whether you
            need more or fewer vehicles to support your operations.
          </p>
        </div>
      </div>
    </div>
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
            <select class="form-select" v-model="selectedType">
              <option value="Movement">Movement</option>
              <option value="Engine Hours">Engine Hours</option>
            </select>
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
                <span class="small text-muted">{{ selectedType === 'Engine Hours' ? 'Engine On' : 'With Movement' }}</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="d-inline-block rounded-1" style="width: 12px; height: 12px; background-color: #e5e7eb;"></span>
                <span class="small text-muted">{{ selectedType === 'Engine Hours' ? 'Engine Off' : 'Without Movement' }}</span>
            </div>
        </div>
      </div>
      <div class="card-body bg-white">
        <div class="row g-3">
            <div class="col-12 col-md-3">
                <div class="bg-light rounded-2 p-3 h-100">
                    <div class="small fw-bold text-dark mb-1">Vehicle ID</div>
                    <div class="small text-muted">{{ summary.vehicleIdDisplay }}</div>
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
                    <div class="small text-muted">{{ summaryDuration }}</div>
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
                <th class="py-3">{{ selectedType === 'Engine Hours' ? 'Engine On' : 'With Movement' }}</th>
                <th class="py-3">{{ selectedType === 'Engine Hours' ? 'Engine Off' : 'Without Movement' }}</th>
                <th class="py-3">Distance</th>
                <th class="py-3">Hourly Activity</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="6" class="text-center py-4">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </td>
              </tr>
              <tr v-else-if="rows.length === 0">
                <td colspan="6" class="text-center text-muted py-4">No results</td>
              </tr>
              <tr v-else v-for="row in pagedRows" :key="row.day">
                <td class="ps-3 py-3 fw-medium text-dark">{{ row.day }}</td>
                <td class="py-3"><span class="badge bg-success-subtle text-success px-3 py-2 rounded-1" style="min-width: 50px;">{{ row.usage }}</span></td>
                <td class="py-3">{{ row.move }}</td>
                <td class="py-3">{{ row.idle }}</td>
                <td class="py-3">{{ row.dist }}</td>
                <td class="py-3">
                  <div class="d-flex gap-1">
                    <div v-for="(active, i) in row.hours" :key="i" class="d-flex flex-column align-items-center" style="width: 25px;">
                        <span class="text-muted mb-1" style="font-size: 10px; line-height: 1;">{{ String(i + 1).padStart(2, '0') }}</span>
                        <div class="rounded-1" :style="{ width: '100%', height: '20px', backgroundColor: active ? '#0ea5e9' : '#e5e7eb' }"></div>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">
          Showing {{ totalCount > 0 ? (startIndex + 1) : 0 }} to {{ endIndex }} of {{ totalCount }} results
        </div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item" :class="{ disabled: page === 1 || loading }">
              <button class="page-link" @click="prevPage" :disabled="page === 1 || loading">‹</button>
            </li>
            <li class="page-item" v-for="n in totalPages" :key="n" :class="{ active: page === n }">
              <button class="page-link" @click="goPage(n)" :disabled="loading">{{ n }}</button>
            </li>
            <li class="page-item" :class="{ disabled: page === totalPages || loading }">
              <button class="page-link" @click="nextPage" :disabled="page === totalPages || loading">›</button>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, inject } from 'vue';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';
import { formatDateTime } from '../../utils/datetime';

const showInfo = ref(false);
const deviceOptions = ref([]);
const selectedDeviceId = ref('');
const selectedType = ref('Movement');
const fromDate = ref(new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));
const toDate = ref(new Date().toISOString().slice(0, 10));
const loading = ref(false);
const errorMessage = ref(null);

const summary = ref({
  vehicleIdDisplay: '',
  deviceId: '',
  durationDisplay: '',
  totalDays: 0,
});

const summaryDuration = computed(() => {
  if (!summary.value || !summary.value.durationDisplay) return '';
  const raw = summary.value.durationDisplay;
  if (typeof raw !== 'string') return raw;
  const sep = ' - ';
  const idx = raw.indexOf(sep);
  if (idx === -1) return raw;
  const fromStr = raw.slice(0, idx).trim();
  const toStr = raw.slice(idx + sep.length).trim();
  if (!fromStr || !toStr) return raw;
  return `${formatDateTime(fromStr)} - ${formatDateTime(toStr)}`;
});

const rows = ref([]);
const page = ref(1);
const pageSize = ref(10);
const totalCount = computed(() => rows.value.length);
const startIndex = computed(() => (page.value - 1) * pageSize.value);
const endIndex = computed(() => Math.min(startIndex.value + pageSize.value, totalCount.value));
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / pageSize.value)));
const pagedRows = computed(() => rows.value.slice(startIndex.value, startIndex.value + pageSize.value));

function goPage(n) {
  if (n >= 1 && n <= totalPages.value) page.value = n;
}
function prevPage() {
  if (page.value > 1) page.value -= 1;
}
function nextPage() {
  if (page.value < totalPages.value) page.value += 1;
}

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
      type: selectedType.value,
    };

    const response = await axios.get('/web/reports/utilisation-db', { params });
    const data = response.data;

    rows.value = data.rows || [];
    summary.value = data.summary || {
        vehicleIdDisplay: '',
        deviceId: '',
        durationDisplay: '',
        totalDays: 0
    };
    console.log('summary data ',summary);

    page.value = 1;

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
    setTimeout(() => {
      fetchReport();
    }, 500);
  }
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; font-weight: 500; font-size: 0.875rem; }
tbody tr td { font-size: 13px; vertical-align: middle; }
.panel .card-body { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.card-header { border-bottom: 1px solid #f3f4f6; }
</style>
