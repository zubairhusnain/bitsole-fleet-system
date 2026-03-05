<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Status Report</li>
      </ol>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center">
        <h4 class="mb-0">Vehicle Status Report</h4>
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
            Vehicle Status provides a snapshot of each tracked vehicle based on its latest GPS report.
            It brings together vehicle details, device information and current telemetry in a single table.
          </p>
          <p class="mb-2">
            Use this report to see which vehicles are currently online, where they last reported from, their ignition state,
            GPS signal quality, odometer reading and activation date. This lets you quickly spot vehicles that may be offline
            or showing unusual readings without opening multiple screens.
          </p>
          <p class="mb-0">
            You can filter by vehicle or group and choose an output format when you want to export the same information
            for reporting, sharing with others or keeping records.
          </p>
        </div>
      </div>
    </div>

    <UiAlert :show="!!errorMessage" :message="errorMessage" variant="danger" dismissible @dismiss="errorMessage = null" />

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
            <select class="form-select" v-model="selectedGroupId">
              <option value="">-- All Groups --</option>
              <option v-for="grp in groupOptions" :key="grp.id" :value="grp.id">
                {{ grp.name }}
              </option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Report Format</label>
            <select class="form-select" v-model="selectedFormat">
              <option value="Website">Website</option>
              <option value="Excel">Excel</option>
              <option value="PDF">PDF</option>
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
import { ref, computed, onMounted, watch } from 'vue';
import UiAlert from '../../components/UiAlert.vue';
import axios from 'axios';
import { formatTelemetry } from '../../utils/telemetry';
import { formatDateTime } from '../../utils/datetime';
const showInfo = ref(false);
const vehicles = ref([]);
const vehicleOptions = ref([]);
const groupOptions = ref([]);
const selectedVehicleId = ref('');
const selectedGroupId = ref('');
const selectedFormat = ref('Website');
const loading = ref(true);
const errorMessage = ref(null);
const currentPage = ref(1);
const itemsPerPage = 16;

// Helper functions
const parseAttrs = (a) => {
    if (!a) return {};
    if (typeof a === 'object') return a;
    try { return JSON.parse(a); } catch { return {}; }
};

const pickAttr = (attrs, keys) => {
    for (const k of keys) {
        const val = attrs?.[k];
        if (val !== undefined && val !== null && val !== '') return val;
    }
    return null;
};

const formatDate = (dateStr) => {
    if (!dateStr || dateStr === 'N/A') return 'N/A';
    return formatDateTime(dateStr);
};

const getSignalStatus = (sat) => {
    if (!sat) return 'Weak';
    const n = parseInt(sat);
    if (isNaN(n)) return 'Weak';
    if (n >= 7) return 'Good';
    if (n >= 4) return 'Fair';
    return 'Weak';
};

// Fetch Groups
const fetchGroups = async () => {
    try {
        const { data } = await axios.get('/web/reports/group-options');
        groupOptions.value = data.options || [];
    } catch (e) {
        console.error(e);
    }
};

// Fetch Options
const fetchOptions = async () => {
    try {
        const params = {};
        if (selectedGroupId.value) params.group_id = selectedGroupId.value;
        const { data } = await axios.get('/web/reports/device-options', { params });
        vehicleOptions.value = data.options || [];
    } catch (e) {
        console.error(e);
    }
};

watch(selectedGroupId, () => {
    selectedVehicleId.value = '';
    fetchOptions();
});

const processVehicleData = (list) => {
    return list.map(v => {
        const tc = v.tc_device || v.tcDevice || {};
        const pos = tc.position || {};
        const attrs = parseAttrs(pos.attributes);
        const deviceAttrs = parseAttrs(tc.attributes);
        const vehicleAttrs = parseAttrs(v.attributes);

        // Merge: Device < Vehicle < Position (Standard Traccar/Laravel precedence)
        const mergedAttrs = { ...deviceAttrs, ...vehicleAttrs, ...attrs };
        const deviceModelAttr = pickAttr(deviceAttrs, ['trackerModel']);

        const vehicleId = deviceAttrs.vehicleNo || deviceAttrs.vehicle_id || deviceAttrs.vehicleId || deviceAttrs.vehicleID || null;

        // Speed logic aligned with Vehicle List
        const speedAttr = pickAttr(mergedAttrs, ['speedKmh', 'speed_kmh', 'speedKmH', 'speed', 'speedKMH']);
        let speedVal = (typeof pos.speed === 'number' ? Math.round(pos.speed * 1.852) : pos.speed) ?? v.speed ?? speedAttr;
        let speed = '0 km/h';
        if (speedVal != null) {
            if (typeof speedVal === 'string' && /km\/h/i.test(speedVal)) {
                speed = speedVal;
            } else {
                const n = Number(speedVal);
                speed = Number.isFinite(n) ? `${Math.round(n)} km/h` : String(speedVal);
            }
        }

        // Location logic aligned with Vehicle List
        let coords = null;
        if (pos.latitude && pos.longitude) {
            coords = `${parseFloat(pos.latitude).toFixed(5)}, ${parseFloat(pos.longitude).toFixed(5)}`;
        }
        const location = pos.address || v.location || pickAttr(mergedAttrs, ['address', 'location']) || coords || 'N/A';

        // Ignition logic
        const ignRaw = mergedAttrs.ignition ?? v.ignition;
        const ignition = ignRaw === true || ignRaw === 1 || String(ignRaw).toLowerCase() === 'on';

        // Extract configured attributes for telemetry
        const configuredOdometerAttr = vehicleAttrs.odometerAttr || vehicleAttrs.odometer_attribute || deviceAttrs.odometerAttr || deviceAttrs.odometer_attribute || null;
        const configuredFuelAttr = vehicleAttrs.fuelAttr || vehicleAttrs.fuel_attribute || deviceAttrs.fuelAttr || deviceAttrs.fuel_attribute || null;

        // Use formatTelemetry with merged attributes and configuration
        const tel = formatTelemetry(mergedAttrs, { protocol: null, model: tc.model, preferNamedOdometer: true, odometerAttr: configuredOdometerAttr, fuelAttr: configuredFuelAttr });

        let odometer = null;
        if (tel?.odometer?.display) {
            odometer = tel.odometer.display;
        }
        if (!odometer) {
            const rawOdo = pickAttr(mergedAttrs, ['odometer', 'mileage', 'odometerKm', 'odometer_km', 'totalDistance', 'distance']);
            if (rawOdo != null && rawOdo !== '') {
                const n = Number(rawOdo);
                if (Number.isFinite(n)) {
                    odometer = `${Math.round(n).toLocaleString()} km`;
                } else {
                    odometer = String(rawOdo);
                }
            }
        }

        return {
            id: v.device_id || v.id,
            vehicle_id: vehicleId || tc.name || v.name || 'Unknown',
            owner: v.manager ? v.manager.name : (v.group || 'N/A'),
            type_model: `${deviceAttrs.type || ''} ${tc.model || ''}`.trim() || 'N/A',
            device_model: deviceModelAttr || tc.model || 'N/A',
            imei: tc.uniqueid || 'N/A',
            iccid: deviceAttrs.iccid || 'N/A',
            odometer: odometer || 'N/A',
            power: ignition ? 'On' : 'Off', // Mapping Power to Ignition status as common fallback
            last_report: formatDate(pos.servertime || pos.fixtime),
            longitude: pos.longitude ? parseFloat(pos.longitude).toFixed(5) : 'N/A',
            latitude: pos.latitude ? parseFloat(pos.latitude).toFixed(5) : 'N/A',
            location: location,
            speed: speed,
            gps_signal: getSignalStatus(mergedAttrs.sat || pos.attributes?.sat),
            ignition: ignition,
            last_ignition_on: formatDate(v.last_ignition_on),
            last_ignition_off: formatDate(v.last_ignition_off),
            activation_date: formatDate(v.created_at)
        };
    });
};

const downloadCSV = async () => {
    errorMessage.value = null;
    try {
        const params = {
            vehicle_id: selectedVehicleId.value,
            group_id: selectedGroupId.value,
            per_page: 999999
        };
        const { data } = await axios.get('/web/reports/vehicle-status', { params });
        const list = Array.isArray(data) ? data : (data.data ?? []);
        const rows = processVehicleData(list);

        const headers = [
            'Vehicle ID', 'Owner', 'Type/Model', 'Device Model', 'IMEI', 'ICCID',
            'Odometer', 'Power', 'Last Report', 'Longitude', 'Latitude', 'Location',
            'Speed', 'GPS Signal', 'Ignition', 'Last Ignition On', 'Last Ignition Off', 'Activation Date'
        ];

        const csvRows = [headers.join(',')];

        rows.forEach(r => {
            const vals = [
                r.vehicle_id, r.owner, r.type_model, r.device_model, r.imei,
                r.odometer, r.power, r.last_report, r.longitude, r.latitude,
                `"${(r.location || '').replace(/"/g, '""')}"`, // Escape quotes
                r.speed, r.gps_signal, r.ignition ? 'ON' : 'OFF', r.last_ignition_on, r.last_ignition_off, r.activation_date
            ];
            csvRows.push(vals.join(','));
        });

        const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `Vehicle_Status_Report_${new Date().toISOString().slice(0,10)}.csv`;
        link.click();

    } catch (e) {
        console.error('Export failed:', e);
        errorMessage.value = 'Export failed. Please try again.';
    }
};

const downloadPDF = async () => {
    loading.value = true;
    errorMessage.value = null;
    try {
        const params = {
            vehicle_id: selectedVehicleId.value,
            group_id: selectedGroupId.value,
            per_page: 999999
        };
        const response = await axios.get('/web/reports/vehicle-status/export-pdf', {
            params,
            responseType: 'blob'
        });

        const blob = new Blob([response.data], { type: 'application/pdf' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `Vehicle_Status_Report_${new Date().toISOString().slice(0,10)}.pdf`;
        link.click();
        URL.revokeObjectURL(link.href);

    } catch (e) {
        console.error('PDF Export failed:', e);
        errorMessage.value = 'PDF Export failed. Please try again.';
    } finally {
        loading.value = false;
    }
};

// Fetch Data
const fetchVehicles = async () => {
    errorMessage.value = null;
    if (selectedFormat.value === 'Excel') {
        downloadCSV();
        return;
    }
    if (selectedFormat.value === 'PDF') {
        await downloadPDF();
        return;
    }

    loading.value = true;
    currentPage.value = 1;
    try {
        const params = { per_page: 500 };
        if (selectedVehicleId.value) {
            params.vehicle_id = selectedVehicleId.value;
        }
        if (selectedGroupId.value) {
            params.group_id = selectedGroupId.value;
        }

        const { data } = await axios.get('/web/reports/vehicle-status', { params });
        const list = Array.isArray(data) ? data : (data.data ?? []);

        vehicles.value = processVehicleData(list);
    } catch (err) {
        console.error("Failed to fetch vehicles", err);
        errorMessage.value = 'Failed to fetch vehicles. Please try again.';
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
    fetchGroups();
    fetchOptions();
    fetchVehicles();
});
</script>

<style scoped>
thead.table-dark tr th { background-color: #886654 !important; color: #fff; vertical-align: middle; font-weight: 500; font-size: 13px; border-bottom: none; }
tbody tr td { font-size: 13px; color: #333; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
.badge { font-weight: 600; font-size: 12px; }
.form-label { font-size: 0.85rem; }
.pagination-app .page-item.active .page-link { background-color: var(--brand-primary); border-color: var(--brand-primary); color: white; }
.pagination-app .page-link { color: #333; }
</style>
