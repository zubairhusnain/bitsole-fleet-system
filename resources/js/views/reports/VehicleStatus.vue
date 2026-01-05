<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Status Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Vehicle Status Report</h4>
    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label small">Vehicle</label>
            <select class="form-select" v-model="selectedVehicleId">
                <option value="">-- All Vehicles --</option>
                <option v-for="opt in vehicleOptions" :key="opt.id" :value="opt.deviceId">
                    {{ opt.label }}
                </option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Group</label>
            <select class="form-select">
              <option>-- Select Group --</option>
              <option>Group A</option>
              <option>Group B</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Report Format</label>
            <select class="form-select">
              <option>-- Report Format --</option>
              <option>Website</option>
              <option>Excel</option>
              <option>PDF</option>
            </select>
          </div>
          <div class="col-12 col-md-1 text-md-end">
            <button class="btn btn-app-dark w-100" @click="fetchVehicles">Submit</button>
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
                <th>Vehicle ID</th>
                <th>Owner</th>
                <th>Type/Model</th>
                <th>Device Model</th>
                <th>IMEI</th>
                <th>ICCID</th>
                <th>Odometer</th>
                <th>Power</th>
                <th>Last Report</th>
                <th>Longitude</th>
                <th>Latitude</th>
                <th>Location</th>
                <th>Speed</th>
                <th>GPS Signal</th>
                <th>Ignition</th>
                <th>Last Ignition On</th>
                <th>Last Ignition Off</th>
                <th>Activation Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                 <td colspan="18" class="text-center py-4">Loading...</td>
              </tr>
              <tr v-else-if="paginatedVehicles.length === 0">
                 <td colspan="18" class="text-center py-4">No vehicles found</td>
              </tr>
              <tr v-else v-for="row in paginatedVehicles" :key="row.id">
                <td>{{ row.vehicle_id }}</td>
                <td>{{ row.owner }}</td>
                <td>{{ row.type_model }}</td>
                <td>{{ row.device_model }}</td>
                <td>{{ row.imei }}</td>
                <td>{{ row.iccid }}</td>
                <td>{{ row.odometer }}</td>
                <td>
                  <span class="badge" :class="row.power === 'On' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                    {{ row.power }}
                  </span>
                </td>
                <td>{{ row.last_report }}</td>
                <td>{{ row.longitude }}</td>
                <td>{{ row.latitude }}</td>
                <td>{{ row.location }}</td>
                <td>{{ row.speed }}</td>
                <td>
                  <i class="bi bi-broadcast me-1" :class="{'text-success': row.gps_signal === 'Good', 'text-warning': row.gps_signal === 'Fair', 'text-danger': row.gps_signal === 'Weak'}"></i>
                  {{ row.gps_signal }}
                </td>
                <td>
                  <span class="badge" :class="row.ignition ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                    {{ row.ignition ? 'ON' : 'OFF' }}
                  </span>
                </td>
                <td>{{ row.last_ignition_on }}</td>
                <td>{{ row.last_ignition_off }}</td>
                <td>{{ row.activation_date }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">Showing {{ paginationStart }} to {{ paginationEnd }} of {{ filteredVehicles.length }} results</div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <button class="page-link" @click="changePage(currentPage - 1)">‹</button>
            </li>
            <li class="page-item" v-for="page in visiblePages" :key="page" :class="{ active: currentPage === page }">
                <button class="page-link" @click="changePage(page)">{{ page }}</button>
            </li>
            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                <button class="page-link" @click="changePage(currentPage + 1)">›</button>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const vehicles = ref([]);
const vehicleOptions = ref([]);
const selectedVehicleId = ref('');
const loading = ref(true);
const currentPage = ref(1);
const itemsPerPage = 16;

// Helper functions
const parseAttrs = (a) => {
    if (!a) return {};
    if (typeof a === 'object') return a;
    try { return JSON.parse(a); } catch { return {}; }
};

const formatDate = (dateStr) => {
    if (!dateStr || dateStr === 'N/A') return 'N/A';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return 'N/A';
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} - ${hours}:${minutes}`;
};

const getSignalStatus = (sat) => {
    if (!sat) return 'Weak';
    const n = parseInt(sat);
    if (isNaN(n)) return 'Weak';
    if (n >= 7) return 'Good';
    if (n >= 4) return 'Fair';
    return 'Weak';
};

// Fetch Options
const fetchOptions = async () => {
    try {
        const { data } = await axios.get('/web/reports/device-options');
        vehicleOptions.value = data.options || [];
    } catch (e) {
        console.error(e);
    }
};

// Fetch Data
const fetchVehicles = async () => {
    loading.value = true;
    currentPage.value = 1;
    try {
        const params = { per_page: 500 };
        if (selectedVehicleId.value) {
            params.vehicle_id = selectedVehicleId.value;
        }
        
        const { data } = await axios.get('/web/reports/vehicle-status', { params });
        const list = Array.isArray(data) ? data : (data.data ?? []);

        vehicles.value = list.map(v => {
            const tc = v.tc_device || v.tcDevice || {};
            const pos = tc.position || {};
            const attrs = parseAttrs(pos.attributes);
            const deviceAttrs = parseAttrs(tc.attributes);

            const vehicleId = deviceAttrs.vehicleNo || deviceAttrs.vehicle_id || deviceAttrs.vehicleId || deviceAttrs.vehicleID || null;

            return {
                id: v.device_id || v.id,
                vehicle_id: vehicleId || tc.name || v.name || 'Unknown',
                owner: v.manager ? v.manager.name : (v.group || 'N/A'),
                type_model: `${deviceAttrs.type || ''} ${tc.model || ''}`.trim() || 'N/A',
                device_model: tc.model || 'N/A',
                imei: tc.uniqueid || 'N/A',
                iccid: deviceAttrs.iccid || 'N/A',
                odometer: attrs.odometer ? (Number(attrs.odometer) / 1000).toFixed(0) + ' km' : '0 km',
                power: attrs.ignition ? 'On' : 'Off',
                last_report: formatDate(pos.servertime || pos.fixtime),
                longitude: pos.longitude ? parseFloat(pos.longitude).toFixed(5) : 'N/A',
                latitude: pos.latitude ? parseFloat(pos.latitude).toFixed(5) : 'N/A',
                location: pos.address || 'N/A',
                speed: pos.speed != null ? Number((parseFloat(pos.speed) * 1.852).toFixed(1)) + ' km/h' : '0 km/h',
                gps_signal: getSignalStatus(attrs.sat),
                ignition: attrs.ignition || false,
                last_ignition_on: formatDate(v.last_ignition_on),
                last_ignition_off: formatDate(v.last_ignition_off),
                activation_date: formatDate(v.created_at)
            };
        });
    } catch (err) {
        console.error("Failed to fetch vehicles", err);
    } finally {
        loading.value = false;
    }
};

// Computed
const filteredVehicles = computed(() => {
    return vehicles.value;
});

const totalPages = computed(() => Math.ceil(filteredVehicles.value.length / itemsPerPage));

const paginatedVehicles = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return filteredVehicles.value.slice(start, end);
});

const visiblePages = computed(() => {
    const pages = [];
    const total = totalPages.value;
    const current = currentPage.value;
    let startPage = Math.max(1, current - 2);
    let endPage = Math.min(total, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }
    for (let i = startPage; i <= endPage; i++) {
        if (i > 0) pages.push(i);
    }
    return pages;
});

const paginationStart = computed(() => filteredVehicles.value.length === 0 ? 0 : (currentPage.value - 1) * itemsPerPage + 1);
const paginationEnd = computed(() => Math.min(currentPage.value * itemsPerPage, filteredVehicles.value.length));

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

onMounted(() => {
    fetchOptions();
    fetchVehicles();
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; white-space: nowrap; padding: 10px 20px; }
tbody tr td { font-size: 13px; white-space: nowrap; padding: 10px 20px; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
.badge { font-weight: 500; font-size: 0.75rem; }
</style>
