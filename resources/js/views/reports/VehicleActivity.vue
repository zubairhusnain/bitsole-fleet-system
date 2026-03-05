<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Activity Report</li>
      </ol>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center">
        <h4 class="mb-0">Vehicle Activity Report</h4>
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
            Vehicle Activity shows a detailed point-by-point timeline for a single vehicle over the selected dates.
            Each row represents a GPS position with status, speed, direction, location and signal information.
          </p>
          <p class="mb-2">
            Status is derived from movement and ignition, so you can clearly see when the vehicle was moving, idling,
            stopped or offline and where those events occurred along the route. This helps you understand how the vehicle
            was being used at each moment rather than just seeing totals.
          </p>
          <p class="mb-0">
            Use this report when you need to reconstruct a specific journey in detail, troubleshoot complaints or investigate
            incidents that require a point-by-point view of the vehicle’s route and behaviour.
          </p>
        </div>
      </div>
    </div>

    <UiAlert :show="!!errorMessage" :message="errorMessage" variant="danger" dismissible @dismiss="errorMessage = null" />

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body pt-2">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Duration</label>
            <div class="input-group">
              <input type="date" v-model="fromDate" class="form-control" />
              <span class="input-group-text bg-white">-</span>
              <input type="date" v-model="toDate" class="form-control" />
            </div>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Vehicle</label>
            <select class="form-select text-muted" v-model="selectedDevice">
              <option :value="null">--Select a Vehicle--</option>
              <option v-for="d in devices" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted d-block">&nbsp;</label>
            <button class="btn btn-primary w-100" @click="fetchReport">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="headerInfo" class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Vehicle Activity Report Result</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-3">
            <div class="small text-muted">Vehicle ID</div>
            <div class="fw-semibold">{{ headerInfo.vehicleId }}</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Device ID</div>
            <div class="fw-semibold">#{{ headerInfo.deviceUniqueId || headerInfo.deviceId }}</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Duration</div>
            <div class="fw-semibold">{{ headerInfo.duration }}</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Last Report</div>
            <div class="fw-semibold">{{ headerInfo.lastReport }}</div>
          </div>
          <div class="col-12">
            <div class="small text-muted">Last Location</div>
            <div class="fw-semibold text-primary">
              <a
                v-if="headerInfo.lastLocation && headerInfo.lastLocation.startsWith('http')"
                :href="headerInfo.lastLocation"
                target="_blank"
                rel="noopener"
                class="text-primary text-decoration-underline"
              >
                <span v-if="headerInfo.lastLocationLat != null && headerInfo.lastLocationLon != null">
                  {{ headerInfo.lastLocationLat }}, {{ headerInfo.lastLocationLon }}
                </span>
                <span v-else>
                  {{ headerInfo.lastLocation }}
                </span>
              </a>
              <span v-else>
                {{ headerInfo.lastLocation || 'N/A' }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height: none; overflow: hidden;">
          <table class="table table-sm align-middle mb-0 table-striped">
            <thead class="table-dark">
              <tr>
                <th class="py-2 ps-3">Date</th>
                <th class="py-2">Time</th>
                <th class="py-2">Status</th>
                <th class="py-2">Longitude</th>
                <th class="py-2">Latitude</th>
                <th class="py-2">Location</th>
                <th class="py-2 text-center">Direction</th>
                <th class="py-2">Speed</th>
                <th class="py-2">GSM Signal</th>
                <th class="py-2">GPS Signal</th>
                <th class="py-2">Power</th>
                <th class="py-2">Ignition</th>
                <th class="py-2 pe-3">Fuel</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="13" class="text-center py-4">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </td>
              </tr>
              <tr v-else-if="!reportData || !reportData.rows || reportData.rows.length === 0">
                <td colspan="13" class="text-center py-4">No data available.</td>
              </tr>
              <template v-else v-for="(group, dateKey) in groupedRows" :key="dateKey">
                <tr class="table-section">
                  <td colspan="13" class="fw-semibold ps-3 py-2 text-primary bg-light">{{ dateKey }}</td>
                </tr>
                <tr v-for="row in group" :key="row.key">
                  <td class="ps-3">{{ row.date }}</td>
                  <td>{{ row.time }}</td>
                  <td>{{ row.status }}</td>
                  <td>{{ row.lon }}</td>
                  <td>{{ row.lat }}</td>
                  <td>
                    <a
                      v-if="row.lat && row.lon"
                      :href="(row.location && row.location.startsWith('http'))
                        ? row.location
                        : `https://www.google.com/maps?q=${row.lat},${row.lon}`"
                      target="_blank"
                      rel="noopener"
                      class="text-truncate d-inline-block"
                      style="max-width: 220px;"
                    >
                      {{ `${row.lat}, ${row.lon}` }}
                    </a>
                    <span v-else>{{ row.location }}</span>
                  </td>
                  <td class="text-center">
                     <a v-if="!row.isEvent" :href="`https://www.google.com/maps?q=${row.lat},${row.lon}`" target="_blank" rel="noopener">
                      <i class="bi bi-arrow-up text-primary" :style="{ transform: `rotate(${row.direction}deg)`, display: 'inline-block' }"></i>
                    </a>
                  </td>
                  <td>{{ row.speed }}</td>
                  <td>
                    <i v-if="row.gsm" class="bi bi-reception-4 me-1"></i>
                    {{ row.gsm }}
                  </td>
                  <td>{{ row.gps }}</td>
                  <td class="text-primary fw-bold">{{ row.power }}</td>
                  <td>
                    <span v-if="row.ignition" :class="row.ignition === 'ON' ? 'badge bg-success' : 'badge bg-secondary'">
                      {{ row.ignition }}
                    </span>
                  </td>
                  <td class="pe-3">{{ row.fuel }}</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-3 bg-white border-top" v-if="reportData && reportData.rows.length > 0">
        <div class="text-muted small me-auto">
           Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredRows.length) }} of {{ filteredRows.length }} results
        </div>
        <div class="d-flex gap-1">
            <button class="btn btn-sm border rounded-1 d-flex align-items-center justify-content-center p-0 page-link"
                    style="width: 32px; height: 32px;"
                    :disabled="currentPage === 1"
                    @click="changePage(currentPage - 1)">
              <i class="bi bi-chevron-left small"></i>
            </button>

            <div v-for="p in totalPages" :key="p" v-show="Math.abs(p - currentPage) < 3 || p === 1 || p === totalPages" class="page-item" :class="{ active: p === currentPage }">
              <button class="btn btn-sm border rounded-1 d-flex align-items-center justify-content-center p-0 fw-semibold page-link"
                      style="width: 32px; height: 32px;"
                      @click="changePage(p)">
                {{ p }}
              </button>
            </div>

            <button class="btn btn-sm border rounded-1 d-flex align-items-center justify-content-center p-0 page-link"
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
import { useRouter } from 'vue-router';
import { formatDateTime, formatDate, formatTime } from '../../utils/datetime';

const router = useRouter();

const showInfo = ref(false);

// State
const devices = ref([]);
const selectedDevice = ref(null);
const fromDate = ref(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));
const toDate = ref(new Date().toISOString().slice(0, 10));
const loading = ref(false);
const errorMessage = ref(null);
const reportData = ref(null);

const headerInfo = computed(() => {
  if (!reportData.value || !reportData.value.header) return null;
  const h = reportData.value.header;
  let fromStr = null;
  let toStr = null;
  if (typeof h.duration === 'string') {
    const sep = ' - ';
    const idx = h.duration.indexOf(sep);
    if (idx !== -1) {
      fromStr = h.duration.slice(0, idx).trim();
      toStr = h.duration.slice(idx + sep.length).trim();
    }
  }
  const formattedDuration = fromStr && toStr
    ? `${formatDateTime(fromStr)} - ${formatDateTime(toStr)}`
    : h.duration;
  return {
    ...h,
    duration: formattedDuration,
    lastReport: formatDateTime(h.lastReport),
  };
});

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(20);

// Computed
const filteredRows = computed(() => {
  if (!reportData.value || !reportData.value.rows) return [];
  return reportData.value.rows;
});

const totalPages = computed(() => Math.ceil(filteredRows.value.length / itemsPerPage.value));

const paginatedRows = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  const end = start + itemsPerPage.value;
  return filteredRows.value.slice(start, end);
});

const groupedRows = computed(() => {
  const groups = {};
  paginatedRows.value.forEach(row => {
    const epochMs = row.epoch ? row.epoch * 1000 : null;
    const dateKey = epochMs ? formatDate(epochMs) : row.groupDate || row.date;
    if (!groups[dateKey]) {
      groups[dateKey] = [];
    }
    groups[dateKey].push({
      ...row,
      date: epochMs ? formatDate(epochMs) : row.date,
      time: epochMs ? formatTime(epochMs) : row.time,
    });
  });
  return groups;
});

// Methods
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
  reportData.value = null;

  try {
    const res = await axios.get('/web/reports/vehicle-activity', {
      params: {
        from_date: fromDate.value,
        to_date: toDate.value,
        device_ids: [selectedDevice.value]
      }
    });
    reportData.value = res.data;
  } catch (e) {
    console.error('Failed to fetch report', e);
    errorMessage.value = e.response?.data?.message || 'Failed to fetch report data';
  } finally {
    loading.value = false;
  }
}

// Lifecycle
onMounted(() => {
  loadDevices();
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #886654 !important; color: #fff; vertical-align: middle; font-weight: 500; font-size: 13px; border-bottom: none; }
.table-section { background: #f2f4f8; }
tbody tr td { font-size: 13px; color: #333; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
.badge { font-weight: 600; font-size: 12px; }
.form-label { font-size: 0.85rem; }
.page-item.active .page-link { background-color: var(--brand-primary); border-color: var(--brand-primary); color: white; }
.page-link { color: #333; }
</style>
