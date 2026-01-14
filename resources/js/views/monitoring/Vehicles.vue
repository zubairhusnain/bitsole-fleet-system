<template>
  <div class="container-fluid py-3">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
        </li>
        <li class="breadcrumb-item">Monitoring</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Monitoring</li>
      </ol>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />

    <!-- Page Title -->
    <div class="row mb-3">
      <div class="col-12">
        <h4 class="mb-0 fw-semibold">Vehicles Monitoring</h4>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md d-flex">
            <div class="card border rounded-4 shadow-0 h-100 flex-fill bg-white">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-car-front fs-3 text-primary"></i>
                    </div>
                    <div class="text-muted small">No. of Vehicles</div>
                    <h4 class="fw-bold mb-0">{{ stats.total }} Vehicles</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md d-flex">
            <div class="card border rounded-4 shadow-0 h-100 flex-fill bg-white">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-power fs-3 text-success"></i>
                    </div>
                    <div class="text-muted small">Ignition On</div>
                    <h4 class="fw-bold mb-0">{{ stats.ignitionOn }} Vehicles</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md d-flex">
            <div class="card border rounded-4 shadow-0 h-100 flex-fill bg-white">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-power fs-3 text-danger"></i>
                    </div>
                    <div class="text-muted small">Ignition Off</div>
                    <h4 class="fw-bold mb-0">{{ stats.ignitionOff }} Vehicles</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md d-flex">
            <div class="card border rounded-4 shadow-0 h-100 flex-fill bg-white">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-speedometer fs-3 text-info"></i>
                    </div>
                    <div class="text-muted small">Moving</div>
                    <h4 class="fw-bold mb-0">{{ stats.moving }} Vehicles</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md d-flex">
            <div class="card border rounded-4 shadow-0 h-100 flex-fill bg-white">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-stop-circle fs-3 text-danger"></i>
                    </div>
                    <div class="text-muted small">Stop</div>
                    <h4 class="fw-bold mb-0">{{ stats.stopped }} Vehicles</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md d-flex">
            <div class="card border rounded-4 shadow-0 h-100 flex-fill bg-white">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-pause-circle fs-3 text-warning"></i>
                    </div>
                    <div class="text-muted small">Idle</div>
                    <h4 class="fw-bold mb-0">{{ stats.idle }} Vehicles</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md d-flex">
            <div class="card border rounded-4 shadow-0 h-100 flex-fill bg-white">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-tools fs-3 text-info"></i>
                    </div>
                    <div class="text-muted small">Maintenance</div>
                    <h4 class="fw-bold mb-0">{{ stats.maintenance }} Vehicles</h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md d-flex">
            <div class="card border rounded-4 shadow-0 h-100 flex-fill bg-white">
                <div class="card-body">
                    <div class="mb-2">
                        <i class="bi bi-exclamation-triangle fs-3 text-danger"></i>
                    </div>
                    <div class="text-muted small">Alerts</div>
                    <h4 class="fw-bold mb-0">{{ stats.alerts }} Vehicles</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Option -->
    <div class="card border rounded-3 shadow-0 mb-4 bg-white">
        <div class="card-body">
            <div class="fw-semibold mb-2">Search Option</div>
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small">Vehicle</label>
                    <input type="text" class="form-control" placeholder="Search Vehicle ID" v-model="searchQuery">
                </div>
                <div class="col-md-5">
                    <label class="form-label small">Auto Refresh (sec / min)</label>
                    <div class="d-flex gap-0 bg-light rounded overflow-hidden">
                        <button
                            v-for="(label, index) in refreshOptions"
                            :key="index"
                            class="btn btn-sm px-3 py-2 fw-medium flex-fill border-0 rounded-0"
                            :class="selectedRefresh === index ? 'btn-primary text-white' : 'btn-light text-muted'"
                            :style="selectedRefresh !== index ? 'background-color: #f8f9fa;' : 'background-color: #00A3FF;'"
                            @click="selectedRefresh = index"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100 text-white" @click="applySearch">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border rounded-3 shadow-0 bg-white mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
                    <thead class="thead-app-dark">
                        <tr>
                            <th class="py-2 ps-4">vehicle ID</th>
                            <th class="py-2">Vehicle Type/Model</th>
                            <th class="py-2">Owner</th>
                            <th class="py-2">Last Report</th>
                            <th class="py-2 text-center">Engine</th>
                            <th class="py-2 text-center">Maintenance</th>
                            <th class="py-2 text-center">Alert</th>
                            <th class="py-2 text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="8" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-else-if="paginatedVehicles.length === 0">
                            <td colspan="8" class="text-center py-4">No vehicles found</td>
                        </tr>
                        <tr v-else v-for="vehicle in paginatedVehicles" :key="vehicle.id">
                            <td class="ps-4 fw-medium">{{ vehicle.uniqueid }} - {{ vehicle.name }}</td>
                            <td>{{ vehicle.type }} - {{ vehicle.model }}</td>
                            <td>{{ vehicle.owner }}</td>
                            <td>{{ vehicle.last_update }}</td>
                            <td class="text-center">
                                <span class="badge rounded-pill px-3 py-2" :class="vehicle.ignition ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                                    {{ vehicle.ignition ? 'Ignition On' : 'Ignition Off' }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span v-if="vehicle.maintenance_count > 0" class="badge bg-warning text-dark rounded-pill px-3">
                                    <i class="bi bi-tools me-1"></i> {{ vehicle.maintenance_count }}
                                </span>
                                <span v-else class="text-muted">N/A</span>
                            </td>
                            <td class="text-center">
                                <span v-if="vehicle.alert_count > 0" class="badge bg-danger rounded-pill px-3 cursor-pointer" @click="showAlerts(vehicle)">
                                    <i class="bi bi-exclamation-triangle me-1"></i> {{ vehicle.alert_count }}
                                </span>
                                <span v-else class="text-muted">N/A</span>
                            </td>
                            <td class="text-end pe-4">
                                <button v-if="hasPermission('monitoring.vehicles', 'read')" class="btn btn-sm btn-link text-primary p-0 me-2" @click="showDetails(vehicle)">
                                    <i class="bi bi-eye fs-5"></i>
                                </button>
                                <button v-if="hasPermission('monitoring.vehicles', 'update')" class="btn btn-sm btn-link text-dark p-0" @click="editVehicle(vehicle)">
                                    <i class="bi bi-pencil-square fs-5"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Pagination -->
        <div class="card-footer d-flex align-items-center py-2">
            <div class="text-muted small me-auto">
                Showing {{ paginationStart }} to {{ paginationEnd }} of {{ filteredVehicles.length }} results
            </div>
            <nav aria-label="Page navigation" class="ms-auto">
                <ul class="pagination pagination-sm mb-0 pagination-app">
                    <li class="page-item" :class="{ disabled: currentPage === 1 }">
                        <button class="page-link" @click="changePage(currentPage - 1)">‹</button>
                    </li>
                    <li class="page-item" v-for="page in visiblePages" :key="page" :class="{ active: currentPage === page }">
                        <button class="page-link" @click="changePage(page)">
                            {{ page }}
                        </button>
                    </li>
                    <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                        <button class="page-link" @click="changePage(currentPage + 1)">›</button>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <!-- Alert Detail Modal -->
    <div v-if="showAlertModal" class="driver-modal-overlay" @click.self="closeAlertModal">
      <div class="driver-modal overflow-hidden" role="dialog" aria-modal="true" style="max-width: 500px;">
         <div class="modal-header">
            <h5 class="fw-bold mb-0">Alert Detail</h5>
            <button type="button" class="btn-close" @click="closeAlertModal"></button>
         </div>
         <div class="modal-body">
             <!-- List View -->
             <div v-if="!selectedAlert">
                 <div v-if="loadingAlerts" class="text-center py-4">
                     <div class="spinner-border text-primary" role="status"></div>
                 </div>
                 <div v-else-if="vehicleAlerts.length === 0" class="text-center py-4 text-muted">
                     No active alerts found.
                 </div>
                 <ul v-else class="list-group list-group-flush">
                     <li v-for="alert in vehicleAlerts" :key="alert.id"
                         class="list-group-item list-group-item-action d-flex justify-content-between align-items-center cursor-pointer"
                         @click="selectAlert(alert)">
                         <div>
                             <div class="fw-bold">{{ alert.type }}</div>
                             <div class="small text-muted">{{ formatDate(alert.eventtime) }}</div>
                         </div>
                         <i class="bi bi-chevron-right text-muted"></i>
                     </li>
                 </ul>
             </div>

             <!-- Detail/Acknowledge View -->
             <div v-else>
                 <button class="btn btn-sm btn-link text-decoration-none ps-0 mb-3" @click="selectedAlert = null">
                     <i class="bi bi-arrow-left me-1"></i> Back to List
                 </button>

                 <div class="mb-3">
                     <label class="small text-muted d-block">Date/Time</label>
                     <div class="fw-medium">{{ formatDate(selectedAlert.eventtime) }}</div>
                 </div>

                 <div class="mb-4">
                     <label class="small text-muted d-block">Alert Type</label>
                     <div class="fw-medium text-danger">{{ selectedAlert.type }}</div>
                 </div>

                 <h6 class="fw-bold mb-3">Alert Acknowledgement</h6>
                 <div class="mb-3">
                     <label class="form-label small">Remarks</label>
                     <textarea class="form-control" rows="3" v-model="alertRemarks" placeholder="Enter remarks..."></textarea>
                 </div>

                <button class="btn btn-primary w-100" @click="acknowledgeAlert" :disabled="submittingAlert">
                     {{ submittingAlert ? 'Submitting...' : 'Acknowledge Alert' }}
                 </button>
             </div>
         </div>
      </div>
    </div>

    <!-- Vehicle Details Modal (Custom Implementation) -->
    <div v-if="showDetailsModal" class="driver-modal-overlay" @click.self="closeModal">
      <div class="driver-modal overflow-hidden" role="dialog" aria-modal="true" style="max-width: 800px;">
        <div class="modal-body p-0" v-if="selectedVehicle">
            <!-- Header Image -->
            <div class="position-relative bg-light" style="height: 250px;">
                <!-- Using a placeholder image or vehicle image if available -->
                <img v-if="selectedVehicle.photos && selectedVehicle.photos.length"
                     :src="photoUrl(selectedVehicle.photos[0])"
                     class="w-100 h-100 object-fit-cover"
                     alt="Vehicle Image"
                     onerror="this.src='https://placehold.co/800x400/e9ecef/6c757d?text=Vehicle+Image'">
                <img v-else src="https://placehold.co/800x400/e9ecef/6c757d?text=Vehicle+Image"
                     class="w-100 h-100 object-fit-cover"
                     alt="Vehicle Placeholder"
                     onerror="this.style.display='none'">

                <button type="button" class="btn btn-close position-absolute top-0 end-0 m-3 bg-white p-2" @click="closeModal" aria-label="Close"></button>
            </div>

            <div class="p-4">
                <!-- Title & Status Badge -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Vehicle Status Information</h5>
                    <span class="badge px-3 py-2 rounded-pill border"
                          :class="selectedVehicle.ignition ? 'bg-success-subtle text-success border-success' : 'bg-danger-subtle text-danger border-danger'">
                        {{ selectedVehicle.ignition ? 'Normal' : 'Stopped' }}
                    </span>
                </div>

                <!-- Grid -->
                <div class="row g-4 mb-4">
                    <!-- Row 1 -->
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Vehicle No</div>
                        <div class="text-muted small">{{ selectedVehicle.vehicle_no || selectedVehicle.name }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Device ID</div>
                        <div class="text-muted small">{{ selectedVehicle.uniqueid }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Vehicle Type</div>
                        <div class="text-muted small">{{ selectedVehicle.type }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Model</div>
                        <div class="text-muted small">{{ selectedVehicle.model }}</div>
                    </div>

                    <!-- Row 2 -->
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Ignition</div>
                        <div class="text-muted small">{{ selectedVehicle.ignition ? 'On' : 'Off' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Last Ignition On</div>
                        <div class="text-muted small">{{ selectedVehicle.last_ignition_on || 'N/A' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Last Ignition Off</div>
                        <div class="text-muted small">{{ selectedVehicle.last_ignition_off || 'N/A' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Speed</div>
                        <div class="text-muted small">{{ selectedVehicle.speed }} km/h</div>
                    </div>

                    <!-- Row 3 -->
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Last Report</div>
                        <div class="text-muted small">{{ selectedVehicle.last_update }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Odometer</div>
                        <div class="text-muted small">{{ selectedVehicle.odometer }} km</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Maintenance</div>
                        <div class="text-muted small">{{ selectedVehicle.maintenance }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-bold mb-1 text-dark">Alert</div>
                        <div class="text-muted small">
                            <span v-if="selectedVehicle.alert_count > 0" class="text-danger fw-bold">
                                <i class="bi bi-exclamation-triangle me-1"></i> {{ selectedVehicle.alert_count }}
                            </span>
                            <span v-else>N/A</span>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="mb-4">
                    <div class="fw-bold mb-1 text-dark">Location</div>
                    <a :href="`https://maps.google.com/?q=${selectedVehicle.lat},${selectedVehicle.lng}`" target="_blank" class="text-decoration-none text-info small">
                        {{ selectedVehicle.address || `${selectedVehicle.lat}, ${selectedVehicle.lng}` }}
                    </a>
                </div>

                <!-- Button -->
                <button class="btn btn-primary w-100 py-2 text-white" @click="openAlertStatusPopup(selectedVehicle)">Change Status</button>
            </div>
        </div>
        <div class="modal-body p-5 text-center" v-else>
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
      </div>
    </div>

    <!-- Status Popup -->
    <div v-if="showAlertStatusPopup" class="driver-modal-overlay" @click.self="closeAlertStatusPopup">
      <div class="driver-modal overflow-hidden" role="dialog" aria-modal="true" style="max-width: 400px;">
        <div class="modal-header">
          <h5 class="fw-bold mb-0">Change Status</h5>
          <button type="button" class="btn-close" @click="closeAlertStatusPopup"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Alerts</label>
            <select v-model="selectedAlertStatus" class="form-select">
              <option value="" disabled>--Select Alerts Status--</option>
              <option value="enabled">Enable Alerts</option>
              <option value="disabled">Disable Alerts</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Maintenance</label>
            <select v-model="selectedMaintenanceStatus" class="form-select">
              <option value="" disabled>--Select Maintenance Status--</option>
              <option value="enabled">Enable Maintenance</option>
              <option value="disabled">Disable Maintenance</option>
            </select>
          </div>
          <button class="btn btn-primary w-100" @click="updateAlertStatus" :disabled="submittingStatus">
            {{ submittingStatus ? 'Updating...' : 'Update Status' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { hasPermission } from '../../auth';
import UiAlert from '../../components/UiAlert.vue';
import { formatDateTime } from '../../utils/datetime';

const router = useRouter();

// State
const vehicles = ref([]);
const loading = ref(true);
const error = ref('');
const searchQuery = ref('');
const refreshOptions = ['30s', '1m', '2m', '3m', '4m', '5m', '10m', 'Off'];
const selectedRefresh = ref(7); // Default Off
const refreshInterval = ref(null);
const selectedVehicle = ref(null);
const showDetailsModal = ref(false);

// Alert Modal State
const showAlertModal = ref(false);
const vehicleAlerts = ref([]);
const loadingAlerts = ref(false);
const selectedAlert = ref(null);
const alertRemarks = ref('');
const submittingAlert = ref(false);

// Alert Status Popup State
const showAlertStatusPopup = ref(false);
const selectedAlertStatus = ref('');
const selectedMaintenanceStatus = ref('');
const alertStatusTargetId = ref(null);
const submittingStatus = ref(false);

// Pagination
const currentPage = ref(1);
const itemsPerPage = 16;

const stats = ref({
    total: 0,
    ignitionOn: 0,
    ignitionOff: 0,
    moving: 0,
    stopped: 0,
    idle: 0,
    maintenance: 0,
    alerts: 0
});

// Filtering & Pagination
const filteredVehicles = computed(() => {
    let res = vehicles.value;
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        res = res.filter(v =>
            v.name.toLowerCase().includes(q) ||
            v.uniqueid.toLowerCase().includes(q) ||
            (v.driver_name && v.driver_name.toLowerCase().includes(q))
        );
    }
    return res;
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

    // Simple pagination logic: show current +/- 2
    let startPage = Math.max(1, current - 2);
    let endPage = Math.min(total, startPage + 4);

    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }

    for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
    }
    return pages;
});

const paginationStart = computed(() => (currentPage.value - 1) * itemsPerPage + 1);
const paginationEnd = computed(() => Math.min(currentPage.value * itemsPerPage, filteredVehicles.value.length));

// Methods
const parseAttrs = (a) => {
    if (!a) return {};
    if (typeof a === 'object') return a;
    try { return JSON.parse(a); } catch { return {}; }
};

const photoUrl = (p) => {
    if (!p && p !== 0) return '';
    const raw = String(p).trim();
    if (!raw) return '';
    if (raw.startsWith('http') || raw.startsWith('data:')) return raw;
    if (raw.startsWith('/')) return raw; // already absolute from root
    if (raw.startsWith('storage/')) return `/${raw}`;
    if (raw.startsWith('public/')) return `/${raw.replace(/^public\//, 'storage/')}`;
    // default: treat as a public disk path under storage
    return `/storage/${raw.replace(/^\/*/, '')}`;
};

// Helper for date formatting (timezone-aware)
const formatDate = (dateStr) => {
    if (!dateStr || dateStr === 'N/A') return 'N/A';
    return formatDateTime(dateStr);
};

const fetchVehicles = async () => {
    try {
        error.value = '';
        const { data } = await axios.get('/web/monitoring/vehicles', { params: { per_page: 50 } });
        const list = Array.isArray(data) ? data : (data.data ?? []);

        if (data.stats) {
            stats.value = data.stats;
        }

        vehicles.value = list.map(v => {
            const tc = v.tc_device || v.tcDevice || {};
            const pos = tc.position || {};
            const attrs = parseAttrs(pos.attributes);
            const deviceAttrs = parseAttrs(tc.attributes);

            // Extract vehicle_id from attributes, prioritizing vehicleNo
            const vehicleId = deviceAttrs.vehicleNo || deviceAttrs.vehicle_id || deviceAttrs.vehicleId || deviceAttrs.vehicleID || null;

            return {
                id: v.device_id || v.id,
                // Prioritize vehicle_id from attributes, then Name
                name: vehicleId || tc.name || v.name || 'Unknown',
                original_name: tc.name || v.name || 'Unknown',
                uniqueid: tc.uniqueid || v.uniqueid || 'N/A',
                plate: v.plate || tc.plate || 'N/A',
                lat: parseFloat(pos.latitude) || 0,
                lng: parseFloat(pos.longitude) || 0,
                speed: pos.speed != null ? Number((parseFloat(pos.speed) * 1.852).toFixed(1)) : 0,
                driver_name: v.driver_name || tc.driverUniqueId || 'N/A',
                last_update: formatDate(pos.servertime || pos.fixtime),
                fuel: attrs.fuel || 0,
                odometer: attrs.odometer || 0,
                ignition: attrs.ignition || false,
                group: v.group || 'Default Group',
                model: tc.model || v.model || 'Unknown',
                type: deviceAttrs.type || 'Unknown',
                owner: v.manager ? v.manager.name : (v.group || 'N/A'),
                maintenance: v.maintenance_display || 'N/A',
                maintenance_count: v.maintenance_count || 0,
                alert_count: v.alert_count || 0,
                blocked: v.blocked,
                alert_status: deviceAttrs.alert_status || '',
                maintenance_status: deviceAttrs.maintenance_status || ''
            };
        });

    } catch (e) {
        console.error("Failed to fetch vehicles", e);
        error.value = 'Failed to load vehicles.';
    } finally {
        loading.value = false;
    }
};

const applySearch = () => {
    currentPage.value = 1;
};

const setupAutoRefresh = (val) => {
    if (refreshInterval.value) clearInterval(refreshInterval.value);

    const option = refreshOptions[val];
    let ms = 0;

    if (option === '30s') ms = 30 * 1000;
    else if (option === '1m') ms = 60 * 1000;
    else if (option === '2m') ms = 120 * 1000;
    else if (option === '3m') ms = 180 * 1000;
    else if (option === '4m') ms = 240 * 1000;
    else if (option === '5m') ms = 300 * 1000;
    else if (option === '10m') ms = 600 * 1000;
    else ms = 0; // Off

    if (ms > 0) {
        refreshInterval.value = setInterval(fetchVehicles, ms);
    }

    try { localStorage.setItem('vehicleMonitoringRefresh', val); } catch {}
};

watch(selectedRefresh, (val) => {
    setupAutoRefresh(val);
});

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const showDetails = async (vehicle) => {
    if (!vehicle || !vehicle.id) return;

    // Set initial state
    selectedVehicle.value = null;
    showDetailsModal.value = true;

    try {
        const { data } = await axios.get(`/web/monitoring/vehicles/${vehicle.id}`);

        const tc = data.tc_device || data.tcDevice || {};
        const pos = tc.position || {};
        const attrs = parseAttrs(pos.attributes);
        const deviceAttrs = parseAttrs(tc.attributes);
        const allAttrs = { ...deviceAttrs, ...attrs };

        // Helper to extract photos (logic from Detail.vue)
        const getPhotos = () => {
            const out = [];
            const toPath = (it) => {
                if (!it && it !== 0) return '';
                if (Array.isArray(it)) return it.map(toPath).filter(Boolean);
                if (typeof it === 'string') {
                    const s = it.trim();
                    if (!s) return '';
                    if ((s.startsWith('[') && s.endsWith(']')) || (s.startsWith('{') && s.endsWith('}'))) {
                        try {
                            const parsed = JSON.parse(s);
                            return toPath(parsed);
                        } catch { }
                    }
                    return s;
                }
                if (typeof it === 'number') return String(it);
                if (typeof it === 'object') {
                    const cand = it.url ?? it.path ?? it.src ?? it.image ?? it.photo;
                    return typeof cand === 'string' ? cand.trim() : '';
                }
                return '';
            };

            const pick = (keys) => {
                for (const k of keys) {
                    if (allAttrs[k] != null && allAttrs[k] !== '') return allAttrs[k];
                }
                return null;
            };

            const arrLike = pick(['photos', 'images']);
            const arrResolved = toPath(arrLike);
            if (Array.isArray(arrResolved)) out.push(...arrResolved);
            else if (typeof arrResolved === 'string' && arrResolved) out.push(arrResolved);

            const single = toPath(pick(['photo', 'image', 'vehiclePhoto', 'vehicleImage']));
            if (Array.isArray(single)) out.push(...single);
            else if (typeof single === 'string' && single) out.push(single);

            return Array.from(new Set(out.filter(v => typeof v === 'string' && v.trim() !== '')));
        };

        selectedVehicle.value = {
            id: data.device_id || data.id,
            // Use vehicle_id_attr if available, otherwise fallback to name, then 'Unknown'
            name: data.vehicle_id_attr || tc.name || data.name || 'Unknown',
            uniqueid: tc.uniqueid || data.uniqueid || 'N/A',
            plate: data.plate || tc.plate || 'N/A',
            lat: parseFloat(pos.latitude) || 0,
            lng: parseFloat(pos.longitude) || 0,
            speed: pos.speed != null ? Number((parseFloat(pos.speed) * 1.852).toFixed(1)) : 0,
            driver_name: data.driver_name || tc.driverUniqueId || 'N/A',
            last_update: formatDate(pos.servertime || pos.fixtime),
            fuel: attrs.fuel || 0,
            odometer: attrs.odometer || 0,
            ignition: attrs.ignition || false,
            group: data.group || 'Default Group',
            model: tc.model || data.model || 'Unknown',
            type: deviceAttrs.type || 'Unknown',
            owner: data.manager ? data.manager.name : (data.group || 'N/A'),
            maintenance_count: data.maintenance_count || 0,
            alert_count: data.alert_count || 0,
            blocked: data.blocked,
            last_ignition_on: formatDate(data.last_ignition_on),
            last_ignition_off: formatDate(data.last_ignition_off),
            address: pos.address,
            // Format maintenance string
            maintenance: data.maintenance_count > 0 ? `${data.maintenance_count} Due` : 'N/A',
            alert_status: deviceAttrs.alert_status || '',
            maintenance_status: deviceAttrs.maintenance_status || '',
            photos: getPhotos()
        };

    } catch (e) {
        console.error("Failed to fetch vehicle details", e);
        // Fallback to passed vehicle object if API fails
        // Ensure formatting is applied to fallback as well
        selectedVehicle.value = {
            ...vehicle,
            last_update: formatDate(vehicle.last_update), // Re-format if needed or just use as is
            last_ignition_on: 'N/A', // Missing in list view
            last_ignition_off: 'N/A' // Missing in list view
        };
    }
};

const closeModal = () => {
    showDetailsModal.value = false;
};

const editVehicle = (vehicle) => {
    if (vehicle) {
        closeModal();
        router.push(`/vehicles/${vehicle.id}/edit`);
    }
};

const showAlerts = async (vehicle) => {
    vehicleAlerts.value = [];
    selectedAlert.value = null;
    alertRemarks.value = '';
    showAlertModal.value = true;
    loadingAlerts.value = true;

    try {
        // Use device_id (Traccar ID) if available, otherwise id
        const id = vehicle.id;
        const { data } = await axios.get(`/web/monitoring/vehicles/${id}/events`);
        vehicleAlerts.value = data;
    } catch (e) {
        console.error("Failed to fetch alerts", e);
    } finally {
        loadingAlerts.value = false;
    }
};

const closeAlertModal = () => {
    showAlertModal.value = false;
    selectedAlert.value = null;
    vehicleAlerts.value = [];
};

const selectAlert = (alert) => {
    selectedAlert.value = alert;
    alertRemarks.value = ''; // Reset remarks
};

const acknowledgeAlert = async () => {
    if (!alertRemarks.value.trim()) return;

    submittingAlert.value = true;
    try {
        await axios.post(`/web/monitoring/vehicles/events/${selectedAlert.value.id}/acknowledge`, {
            remarks: alertRemarks.value
        });

        // Remove acknowledged alert from list or refresh list
        vehicleAlerts.value = vehicleAlerts.value.filter(a => a.id !== selectedAlert.value.id);
        selectedAlert.value = null; // Go back to list

        // Refresh vehicle list to update counters
        fetchVehicles();

    } catch (e) {
        console.error("Failed to acknowledge alert", e);
        alert("Failed to acknowledge alert. Please try again.");
    } finally {
        submittingAlert.value = false;
    }
};

const openAlertStatusPopup = (vehicle) => {
    if (!vehicle) return;
    alertStatusTargetId.value = vehicle.id;
    selectedAlertStatus.value = vehicle.alert_status || '';
    selectedMaintenanceStatus.value = vehicle.maintenance_status || '';
    showAlertStatusPopup.value = true;
};

const closeAlertStatusPopup = () => {
    showAlertStatusPopup.value = false;
};

const updateAlertStatus = async () => {
    if (!alertStatusTargetId.value) return;
    try {
        submittingStatus.value = true;
        await axios.post(`/web/monitoring/vehicles/${alertStatusTargetId.value}/alert-status`, {
            alert_status: selectedAlertStatus.value || null,
            maintenance_status: selectedMaintenanceStatus.value || null
        });
        closeAlertStatusPopup();
        fetchVehicles();
    } catch (e) {
        console.error("Failed to update alert status", e);
        alert("Failed to update alert status. Please try again.");
    } finally {
        submittingStatus.value = false;
    }
};

onMounted(() => {
    fetchVehicles();
    try {
        const saved = localStorage.getItem('vehicleMonitoringRefresh');
        if (saved !== null) {
            const idx = parseInt(saved);
            if (!isNaN(idx) && idx >= 0 && idx < refreshOptions.length) {
                selectedRefresh.value = idx;
                setupAutoRefresh(idx);
            }
        } else {
             // Default 30s (Index 0)
            selectedRefresh.value = 0;
            setupAutoRefresh(0);
        }
    } catch {
        selectedRefresh.value = 0;
        setupAutoRefresh(0);
    }
});

onUnmounted(() => {
    if (refreshInterval.value) clearInterval(refreshInterval.value);
});
</script>

<style scoped>
/* Custom Modal Styles from DriverDetailModal.vue */
.driver-modal-overlay { position: fixed; inset: 0; background: rgba(9, 12, 28, 0.4); backdrop-filter: blur(2px); z-index: 1050; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 24px; }
.driver-modal { background: #fff; border-radius: 16px; box-shadow: 0 10px 24px rgba(0,0,0,.15); width: 100%; max-width: 600px; font-family: var(--font-sans); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid #e9ecef; }
.modal-body { padding: 16px; }

@media (max-width: 576px) {
  .driver-modal { border-radius: 0; max-width: none; height: 100vh; }
  .modal-body { padding: 12px; }
}

.modal-header h5 { font-size: 1.25rem; }
</style>
