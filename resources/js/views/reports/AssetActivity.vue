<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Asset Activity Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Asset Activity Report</h4>

    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small">Start Date</label>
            <input v-model="startDate" type="datetime-local" class="form-control" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">End Date</label>
            <input v-model="endDate" type="datetime-local" class="form-control" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Vehicle</label>
            <select v-model="vehicle" class="form-select">
              <option value="">-- All Vehicles --</option>
              <option v-for="v in vehicles" :key="v.id" :value="v.device_id">{{ v.name }}</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Search Filter</label>
            <select class="form-select" v-model="searchFilter">
              <option value="">-- Search Filter --</option>
              <option>Door Sensor</option>
              <option>Exceptions</option>
              <option>Power Disconnection</option>
              <option>Ignition</option>
              <option>Idling</option>
              <option>Seatbelt</option>
              <option>Trailer</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Report Format</label>
            <select class="form-select" v-model="reportFormat">
              <option value="">-- Report Format --</option>
              <option>Website</option>
              <option>Map</option>
              <option>Excel</option>
              <option>PDF</option>
              <option>Google Earth KML</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Map Option</label>
            <select class="form-select" v-model="mapOption">
              <option value="">-- Map Option --</option>
              <option>Icon</option>
              <option>Line</option>
              <option>Playback</option>
            </select>
          </div>
          <div class="col-12 col-md-3 text-md-end offset-md-3">
            <button class="btn btn-app-dark w-100" @click="handleSearch" :disabled="loading">
              <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
              Submit
            </button>
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
            <div class="fw-semibold">#{{ headerInfo.deviceId }}</div>
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
            <div class="fw-semibold text-primary">{{ headerInfo.lastLocation }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-hover">
            <thead class="table-dark">
              <tr>
                <th>Vehicle</th>
                <th>Date</th>
                <th>Time</th>
                <th>Status</th>
                <th>Longitude</th>
                <th>Latitude</th>
                <th>Location</th>
                <th>Direction</th>
                <th>Speed</th>
                <th>GSM Signal</th>
                <th>GPS Signal</th>
                <th>Power</th>
                <th>Ignition</th>
                <th>Fuel</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="groupedRows.length">
                <template v-for="(group, gIndex) in groupedRows" :key="gIndex">
                  <tr class="table-light">
                    <td colspan="13" class="fw-bold text-primary">{{ group.date }}</td>
                  </tr>
                  <tr v-for="row in group.rows" :key="row.key">
                    <td>{{ row.vehicle }}</td>
                    <td>{{ row.date }}</td>
                    <td>{{ row.time }}</td>
                    <td>{{ row.status }}</td>
                    <td>{{ row.lon }}</td>
                    <td>{{ row.lat }}</td>
                    <td>
                      <div class="text-truncate" style="max-width: 200px;" :title="row.location">{{ row.location }}</div>
                    </td>
                    <td class="text-center">
                      <i class="bi bi-arrow-up text-primary" :style="{ transform: `rotate(${row.direction}deg)`, display: 'inline-block' }"></i>
                    </td>
                    <td>{{ row.speed }}</td>
                    <td>
                      <i class="bi bi-reception-4 text-success" v-if="row.gsm"></i>
                      <span v-else>-</span>
                    </td>
                    <td>
                       <i class="bi bi-broadcast text-success" v-if="row.gps"></i>
                       <span v-else>-</span>
                    </td>
                    <td class="text-info fw-bold">{{ row.power }}</td>
                    <td>
                      <span v-if="row.ignition" class="badge bg-success bg-opacity-10 text-success border border-success">ON</span>
                      <span v-else class="badge bg-danger bg-opacity-10 text-danger border border-danger">OFF</span>
                    </td>
                    <td>{{ row.fuel }}</td>
                  </tr>
                </template>
              </template>
              <tr v-else-if="!loading && hasSearched">
                <td colspan="13" class="text-center py-4 text-muted">No data found for the selected period.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2" v-if="rows.length">
        <div class="text-muted small me-auto">
          Showing {{ (currentPage - 1) * itemsPerPage + 1 }} to {{ Math.min(currentPage * itemsPerPage, filteredRows.length) }} of {{ filteredRows.length }} results
        </div>
        <div class="me-3">
          <select v-model="itemsPerPage" class="form-select form-select-sm" style="width: auto; display: inline-block;">
            <option :value="50">50</option>
            <option :value="100">100</option>
            <option :value="200">200</option>
            <option :value="500">500</option>
          </select>
        </div>
        <nav aria-label="Pagination" class="ms-auto" v-if="totalPages > 1">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item" :class="{ disabled: currentPage === 1 }">
              <button class="page-link" @click="currentPage--">‹</button>
            </li>
            <li class="page-item disabled">
              <span class="page-link">{{ currentPage }} / {{ totalPages }}</span>
            </li>
            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
              <button class="page-link" @click="currentPage++">›</button>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';

const startDate = ref('');
const endDate = ref('');
const vehicle = ref('');
const vehicles = ref([]);
const searchFilter = ref('');
const reportFormat = ref('');
const mapOption = ref('');
const loading = ref(false);
const rows = ref([]);
const headerInfo = ref(null);
const hasSearched = ref(false);

// Pagination
const currentPage = ref(1);
const itemsPerPage = ref(100);

const filteredRows = computed(() => {
  if (!rows.value.length) return [];
  if (!searchFilter.value) return rows.value;

  const filter = searchFilter.value;
  return rows.value.filter(row => {
    if (filter === 'Ignition') {
      return row.rawType === 'ignitionOn' || row.rawType === 'ignitionOff';
    }
    if (filter === 'Power Disconnection') {
      return row.rawType === 'powerCut';
    }
    if (filter === 'Idling') {
      return row.rawType === 'idling' || (row.status && row.status.toLowerCase().includes('idling'));
    }
    if (filter === 'Exceptions') {
      // Exclude positions and common status events
      if (row.rawType === 'position') return false;
      if (['ignitionOn', 'ignitionOff', 'powerCut', 'idling'].includes(row.rawType)) return false;
      return true;
    }
    // TODO: Implement logic for Door Sensor, Seatbelt, Trailer when data is available
    return false;
  });
});

const groupedRows = computed(() => {
  if (!filteredRows.value.length) return [];
  const groups = {};
  filteredRows.value.forEach(row => {
    if (!groups[row.groupDate]) {
      groups[row.groupDate] = [];
    }
    groups[row.groupDate].push(row);
  });
  return Object.keys(groups).map(date => ({
    date,
    rows: groups[date]
  }));
});

onMounted(async () => {
  // Set default dates (Last 7 Days)
  const now = new Date();
  const start = new Date(now);
  start.setDate(start.getDate() - 7); // Go back 7 days
  start.setHours(0, 0, 0, 0);
  
  const end = new Date(now);
  end.setHours(23, 59, 59, 999);

  startDate.value = toIsoLocal(start);
  endDate.value = toIsoLocal(end);

  // Fetch vehicles
  try {
    const { data } = await axios.get('/web/reports/device-options');
    vehicles.value = data.options || [];
  } catch (e) {
    console.error('Failed to load vehicles', e);
  }

  // Auto search on load
  handleSearch();
});

function toIsoLocal(d) {
  const pad = (n) => n.toString().padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

async function handleSearch() {
  loading.value = true;
  hasSearched.value = true;
  rows.value = [];
  headerInfo.value = null;

  const deviceIds = vehicle.value ? [vehicle.value] : [];

  try {
    const { data } = await axios.get('/web/reports/asset-activity', {
      params: {
        from_date: startDate.value,
        to_date: endDate.value,
        device_ids: deviceIds
      }
    });

    if (data.rows) {
      rows.value = data.rows;
      headerInfo.value = data.header;
    }
  } catch (e) {
    console.error('Error fetching asset activity', e);
    alert('Failed to load report data.');
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; font-weight: 500; font-size: 0.85rem; }
tbody tr td { font-size: 13px; vertical-align: middle; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
</style>
