<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Idling Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Idling Report</h4>

    <UiAlert :show="!!errorMessage" :message="errorMessage" variant="danger" dismissible @dismiss="errorMessage = null" />

    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small">Duration</label>
            <div class="input-group">
                <input type="date" v-model="fromDate" class="form-control" />
                <span class="input-group-text bg-white">-</span>
                <input type="date" v-model="toDate" class="form-control" />
            </div>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Vehicle</label>
            <select v-model="selectedDevice" class="form-select">
              <option value="" disabled>--Select a Vehicle--</option>
              <option v-for="d in devices" :key="d.id" :value="d.id">{{ d.label }}</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Idling Time Filter</label>
            <select v-model="timeFilter" class="form-select">
              <option value="">-- Report Format --</option>
              <option value="<120">Less than 2 Minutes</option>
              <option value="<180">Less than 3 Minutes</option>
              <option value="<300">Less than 5 Minutes</option>
              <option value=">120">Greater than 2 Minutes</option>
              <option value=">300">Greater than 5 Minutes</option>
              <option value=">600">Greater than 10 Minutes</option>
              <option value=">1200">Greater than 20 Minutes</option>
              <option value=">1800">Greater than 30 Minutes</option>
              <option value=">3600">Greater than 1 Hour</option>
              <option value=">7200">Greater than 2 Hours</option>
              <option value=">18000">Greater than 5 Hours</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <button class="btn btn-app-dark w-100" @click="fetchReport" :disabled="loading">
                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                Submit
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="reportData && reportData.length > 0" class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Idling Report Result</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-3">
            <div class="small text-muted">Vehicle</div>
            <div class="fw-semibold">{{ getDeviceName(selectedDevice) }}</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Duration</div>
            <div class="fw-semibold">{{ fromDate }} - {{ toDate }}</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Idle Duration Criteria</div>
            <div class="fw-semibold">{{ getTimeFilterLabel(timeFilter) }}</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Total Events</div>
            <div class="fw-semibold">{{ filteredRows.length }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-striped">
            <thead class="table-dark">
              <tr>
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Duration</th>
                <th>Location</th>
                <th>Longitude</th>
                <th>Latitude</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="7" class="text-center py-4">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </td>
              </tr>
              <tr v-else-if="filteredRows.length === 0">
                <td colspan="7" class="text-center py-4">No idling events found.</td>
              </tr>
              <template v-else v-for="(group, dateKey) in groupedRows" :key="dateKey">
                <tr class="table-section">
                  <td colspan="7" class="fw-semibold ps-3 py-2 text-primary bg-light">{{ dateKey }}</td>
                </tr>
                <tr v-for="(row, idx) in group" :key="idx">
                  <td class="ps-3">{{ row.date }}</td>
                  <td>{{ row.startTime }}</td>
                  <td>{{ row.endTime }}</td>
                  <td>{{ row.durationFormatted }}</td>
                  <td>
                    <a :href="`https://www.google.com/maps?q=${row.lat},${row.lon}`" target="_blank" class="text-decoration-none">
                        {{ row.location }}
                    </a>
                  </td>
                  <td>{{ row.lon }}</td>
                  <td>{{ row.lat }}</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-3 bg-white border-top" v-if="filteredRows.length > 0">
        <div class="text-muted small me-auto">
            Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredRows.length) }} of {{ filteredRows.length }} results
        </div>
        <div class="d-flex gap-1">
            <button class="btn btn-sm border rounded-1 d-flex align-items-center justify-content-center p-0"
                    style="width: 32px; height: 32px;"
                    :disabled="currentPage === 1"
                    @click="changePage(currentPage - 1)">
              <i class="bi bi-chevron-left small"></i>
            </button>

            <button v-for="p in totalPages" :key="p"
                    class="btn btn-sm border rounded-1 d-flex align-items-center justify-content-center p-0 fw-semibold"
                    :class="p === currentPage ? 'bg-dark text-white border-dark' : 'bg-white text-dark'"
                    style="width: 32px; height: 32px;"
                    v-show="Math.abs(p - currentPage) < 3 || p === 1 || p === totalPages"
                    @click="changePage(p)">
              {{ p }}
            </button>

            <button class="btn btn-sm border rounded-1 d-flex align-items-center justify-content-center p-0"
                    style="width: 32px; height: 32px;"
                    :disabled="currentPage === totalPages"
                    @click="changePage(currentPage + 1)">
              <i class="bi bi-chevron-right small"></i>
            </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import UiAlert from '../../components/UiAlert.vue';
import axios from 'axios';
import { formatDateTime, formatTime } from '../../utils/datetime';

// State
const devices = ref([]);
const selectedDevice = ref('');
const fromDate = ref(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));
const toDate = ref(new Date().toISOString().slice(0, 10));
const timeFilter = ref('>120'); // Default > 2 mins
const loading = ref(false);
const errorMessage = ref(null);
const reportData = ref([]);
 
// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(20); 

// Computed
const filteredRows = computed(() => {
    if (!reportData.value) return [];

    let rows = reportData.value;

    if (timeFilter.value) {
        const op = timeFilter.value.substring(0, 1);
        const val = parseInt(timeFilter.value.substring(1));

        rows = rows.filter(r => {
            if (op === '<') return r.durationSeconds < val;
            if (op === '>') return r.durationSeconds > val;
            return true;
        });
    }

    return rows;
});

const paginatedRows = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredRows.value.slice(start, end);
});

const groupedRows = computed(() => {
    const groups = {};
    paginatedRows.value.forEach(row => {
        const dt = new Date(row.startEpoch * 1000);
        const dayName = dt.toLocaleDateString('en-US', {
            weekday: 'long',
            timeZone: undefined,
        });
        const key = `${row.date} ${dayName}`;

        if (!groups[key]) groups[key] = [];
        groups[key].push(row);
    });
    return groups;
});

const totalPages = computed(() => Math.ceil(filteredRows.value.length / itemsPerPage.value));

// Methods
function getDeviceName(id) {
    const d = devices.value.find(x => x.id === id);
    return d ? d.label : id;
}

function getTimeFilterLabel(val) {
    if (!val) return 'All';
    const op = val.substring(0, 1);
    const num = parseInt(val.substring(1));
    const mins = Math.floor(num / 60);
    const hours = Math.floor(mins / 60);

    const timeStr = hours > 0 ? `${hours} Hour(s)` : `${mins} Minute(s)`;
    return (op === '<' ? 'Less than ' : 'Greater than ') + timeStr;
}

function changePage(page) {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
}

async function loadDevices() {
    try {
        const res = await axios.get('/web/reports/device-options');
        devices.value = res.data.options || [];
        if (devices.value.length > 0) {
            selectedDevice.value = devices.value[0].id;
            // Auto-fetch report on load
            fetchReport();
        }
    } catch (e) {
        console.error('Failed to load devices', e);
    }
}

async function fetchReport() {
    if (!selectedDevice.value) return;

    loading.value = true;
    errorMessage.value = null;
    reportData.value = [];
    currentPage.value = 1;

    try {
        const res = await axios.get('/web/reports/idling', {
            params: {
                from_date: fromDate.value,
                to_date: toDate.value,
                device_ids: [selectedDevice.value]
            }
        });
        reportData.value = res.data;
    } catch (e) {
        console.error('Failed to fetch idling report', e);
        errorMessage.value = e.response?.data?.message || 'Failed to fetch report data';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadDevices();
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; }
.table-section { background: #f2f4f8; }
tbody tr td { font-size: 13px; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
</style>
