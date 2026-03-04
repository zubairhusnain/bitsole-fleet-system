<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Incident Analysis Report</li>
      </ol>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center">
        <h4 class="mb-0">Incident Analysis Report</h4>
        <button
          type="button"
          class="btn btn-link p-0 ms-2 text-muted"
          @click="showInfo = !showInfo"
        >
          <i class="bi bi-info-circle"></i>
        </button>
      </div>
      <RouterLink to="/reports/incident/new" class="btn btn-dark btn-sm px-3 py-2">Add New Incident</RouterLink>
    </div>

    <div v-if="showInfo" class="mb-3">
      <div class="card border-0 bg-light">
        <div class="card-header bg-transparent py-2">
          <div class="fw-semibold small">About this report</div>
        </div>
        <div class="card-body pt-2 pb-3 small">
          <p class="mb-2">
            Incident Analysis Report aggregates incidents that have been logged against your vehicles and drivers.
            Each row shows when the incident occurred, what type it was, which vehicle and driver were involved and a short description.
          </p>
          <p class="mb-2">
            Use the date range and vehicle filters to focus on specific periods or assets, then export detailed records
            for auditing, insurance claims or internal investigations. This makes it easier to review the full history
            of an incident or group of incidents without collecting data from multiple places.
          </p>
          <p class="mb-0">
            This report helps you understand recurring safety or operational issues, track corrective actions taken over time
            and measure whether driver training or policy changes are reducing the number and severity of incidents.
          </p>
        </div>
      </div>
    </div>

    <!-- Alerts -->
    <UiAlert :show="!!alert.message" :message="alert.message" :variant="alert.type" dismissible @dismiss="alert.message = ''" />

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body pt-2">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">From Date</label>
            <div class="input-group">
              <input type="date" class="form-control" v-model="fromDate" />
              <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">To Date</label>
            <div class="input-group">
              <input type="date" class="form-control" v-model="toDate" />
              <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Vehicle</label>
            <select class="form-select" v-model="filterVehicleId">
              <option value="">-- All Vehicles --</option>
              <option v-for="opt in deviceOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div class="col-12 col-md-2">
            <button class="btn btn-info text-white w-100" @click="fetchIncidents" :disabled="loading">Submit</button>
          </div>
        </div>
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
                <th>Incident Start</th>
                <th>Incident End</th>
                <th>Impact Date/Time</th>
                <th>Driver</th>
                <th>Description</th>
                <th>Remarks</th>
                <th class="text-center pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
                  <tr v-for="row in pagedRows" :key="row.deviceId + '_' + row.vehicleId">
                <td class="ps-3">{{ row.vehicleId }}</td>
                <td>{{ row.typeModel }}</td>
                    <td>{{ formatDateTime(row.incidentStart) }}</td>
                    <td>{{ formatDateTime(row.incidentEnd) }}</td>
                    <td>{{ formatDateTime(row.impactTime) }}</td>
                <td>{{ row.driver }}</td>
                <td class="text-truncate" style="max-width: 200px;">{{ row.description }}</td>
                <td class="text-truncate" style="max-width: 200px;">{{ row.remarks }}</td>
                <td class="text-center pe-3">
                  <button class="btn btn-sm p-0 text-dark me-2" @click="exportExcel(row)"><i class="bi bi-file-earmark-excel fs-6"></i></button>
                  <button class="btn btn-sm p-0 text-primary" @click="exportPdf(row)"><i class="bi bi-file-earmark-pdf fs-6"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
        <div class="text-muted small me-auto">Showing {{ totalCount > 0 ? (startIndex + 1) : 0 }} to {{ endIndex }} of {{ totalCount }} results</div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item" :class="{ disabled: page === 1 || loading }"><button class="page-link" @click="prevPage" :disabled="page === 1 || loading"><i class="bi bi-chevron-left"></i></button></li>
            <li class="page-item" v-for="n in totalPages" :key="n" :class="{ active: page === n }"><button class="page-link" @click="goPage(n)" :disabled="loading">{{ n }}</button></li>
            <li class="page-item" :class="{ disabled: page === totalPages || loading }"><button class="page-link" @click="nextPage" :disabled="page === totalPages || loading"><i class="bi bi-chevron-right"></i></button></li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import UiAlert from '../../components/UiAlert.vue';
import axios from 'axios';
import { hasPermission } from '../../auth';
import { formatDateTime } from '../../utils/datetime';

const hasPerm = (m, a) => hasPermission(m, a);
const showInfo = ref(false);
const fromDate = ref(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));
const toDate = ref(new Date().toISOString().slice(0, 10));
const filterVehicleId = ref('');
const deviceOptions = ref([]);
const alert = ref({ message: '', type: '' });
const loading = ref(false);
const rows = ref([]);
const page = ref(1);
const pageSize = ref(10);
const totalCount = computed(() => rows.value.length);
const startIndex = computed(() => (page.value - 1) * pageSize.value);
const endIndex = computed(() => Math.min(startIndex.value + pageSize.value, totalCount.value));
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / pageSize.value)));
const pagedRows = computed(() => rows.value.slice(startIndex.value, startIndex.value + pageSize.value));

function goPage(n) { if (n >= 1 && n <= totalPages.value) page.value = n; }
function prevPage() { if (page.value > 1) page.value -= 1; }
function nextPage() { if (page.value < totalPages.value) page.value += 1; }

async function loadDeviceOptions() {
  try {
    const res = await axios.get('/web/reports/device-options?includeAll=true');
    deviceOptions.value = res.data.options || res.data || [];
  } catch (e) {
    console.error('Failed to load device options', e);
  }
}

async function fetchIncidents() {
  loading.value = true;
  rows.value = [];
  try {
    const params = { from_date: fromDate.value, to_date: toDate.value, vehicle_id: filterVehicleId.value };
    const res = await axios.get('/web/reports/incidents', { params });
    rows.value = res.data.rows || [];
    page.value = 1;
    alert.value = { message: '', type: '' };
  } catch (e) {
    console.error('Failed to fetch incidents', e);
    alert.value = { message: 'Failed to fetch incidents.', type: 'danger' };
  } finally {
    loading.value = false;
  }
}

function exportPdf(row) {
  const params = new URLSearchParams({
    from_date: fromDate.value,
    to_date: toDate.value,
    vehicle_id: filterVehicleId.value,
    incident_id: String(row.incidentId || '')
  });
  window.open('/web/reports/incidents/export-pdf?' + params.toString(), '_blank');
}
function exportExcel(row) {
  const params = new URLSearchParams({
    from_date: fromDate.value,
    to_date: toDate.value,
    vehicle_id: filterVehicleId.value,
    incident_id: String(row.incidentId || '')
  });
  window.open('/web/reports/incidents/export-excel?' + params.toString(), '_blank');
}

onMounted(async () => {
  await loadDeviceOptions();
  fetchIncidents();
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; vertical-align: middle; font-weight: 500; font-size: 13px; border-bottom: none; }
tbody tr td { font-size: 13px; color: #333; }
.badge { font-weight: 600; font-size: 12px; }
.form-label { font-size: 0.85rem; }
.pagination-app .page-item.active .page-link { background-color: var(--brand-primary); border-color: var(--brand-primary); color: white; }
.pagination-app .page-link { color: #333; }
</style>
