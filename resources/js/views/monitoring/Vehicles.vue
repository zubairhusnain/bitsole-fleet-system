<template>
  <div class="container-fluid">
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
                    <h4 class="fw-bold mb-0">{{ stats.ignitionOff }} Drivers</h4>
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
                    <h4 class="fw-bold mb-0">{{ stats.maintenance }} KM</h4>
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
                    <h4 class="fw-bold mb-0">{{ stats.alerts }} hours</h4>
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
                    <input type="text" class="form-control" placeholder="Auto Refresh (sec / min)" v-model="refreshInput">
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
                    <thead class="thead-app-dark bg-dark text-white">
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
                            <td colspan="8" class="text-center py-4">Loading...</td>
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
                                {{ vehicle.maintenance || 'N/A' }}
                            </td>
                            <td class="text-center">
                                <span v-if="vehicle.alert_count > 0" class="badge bg-danger rounded-pill px-3">
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
    </div>

    <!-- Pagination (Outside Card) -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="text-muted small">
            Showing {{ paginationStart }} to {{ paginationEnd }} of {{ filteredVehicles.length }} results
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0 gap-1">
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <button class="page-link border-0 rounded"
                        :class="currentPage === 1 ? 'bg-secondary-subtle text-muted' : 'bg-dark text-white'"
                        @click="changePage(currentPage - 1)">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                </li>
                <li class="page-item" v-for="page in visiblePages" :key="page" :class="{ active: currentPage === page }">
                    <button class="page-link border-0 rounded" :class="currentPage === page ? 'bg-dark text-white' : 'bg-white text-dark shadow-sm'" @click="changePage(page)">
                        {{ page }}
                    </button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                    <button class="page-link border-0 rounded"
                        :class="currentPage === totalPages ? 'bg-secondary-subtle text-muted' : 'bg-dark text-white'"
                        @click="changePage(currentPage + 1)">
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Vehicle Details Modal (Custom Implementation) -->
    <div v-if="showDetailsModal" class="driver-modal-overlay" @click.self="closeModal">
      <div class="driver-modal" role="dialog" aria-modal="true">
        <div class="modal-header">
          <h5 class="mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-truck text-muted"></i>
            <span>Vehicle Details</span>
          </h5>
          <button type="button" class="btn btn-light btn-sm" @click="closeModal" aria-label="Close">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
        <div class="modal-body" v-if="selectedVehicle">
           <div class="text-center mb-4">
              <div class="avatar avatar-lg bg-light rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                  <i class="bi bi-truck fs-2 text-primary"></i>
              </div>
              <h5>{{ selectedVehicle.name }}</h5>
              <p class="text-muted">{{ selectedVehicle.plate }}</p>
           </div>
           <div class="row g-3">
             <div class="col-6">
               <label class="small text-muted">Driver</label>
               <div class="fw-medium">{{ selectedVehicle.driver_name || 'N/A' }}</div>
             </div>
             <div class="col-6">
               <label class="small text-muted">IMEI</label>
               <div class="fw-medium">{{ selectedVehicle.uniqueid }}</div>
             </div>
             <div class="col-6">
               <label class="small text-muted">Group</label>
               <div class="fw-medium">{{ selectedVehicle.group || 'Default' }}</div>
             </div>
             <div class="col-6">
               <label class="small text-muted">Model</label>
               <div class="fw-medium">{{ selectedVehicle.model || 'Unknown' }}</div>
             </div>
           </div>
           <hr>
           <div class="d-flex justify-content-between align-items-center">
              <div>
                 <label class="small text-muted d-block">Current Status</label>
                 <span class="badge" :class="selectedVehicle.ignition ? 'bg-success' : 'bg-danger'">{{ selectedVehicle.ignition ? 'Ignition On' : 'Ignition Off' }}</span>
              </div>
              <div>
                 <label class="small text-muted d-block">Fuel Level</label>
                 <span class="fw-medium">{{ selectedVehicle.fuel || '0' }}%</span>
              </div>
              <div>
                 <label class="small text-muted d-block">Odometer</label>
                 <span class="fw-medium">{{ selectedVehicle.odometer || '0' }} km</span>
              </div>
           </div>
        </div>
        <div class="modal-footer d-flex justify-content-between p-3 border-top">
          <div class="d-flex gap-2">
            <button v-if="hasPermission('monitoring.vehicles', 'update') && !selectedVehicle.blocked" type="button" class="btn btn-outline-danger" @click="toggleBlock(true)">Block Engine</button>
            <button v-if="hasPermission('monitoring.vehicles', 'update') && selectedVehicle.blocked" type="button" class="btn btn-outline-success" @click="toggleBlock(false)">Unblock Engine</button>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" @click="closeModal">Close</button>
            <button v-if="hasPermission('monitoring.vehicles', 'update')" type="button" class="btn btn-primary" @click="editVehicle(selectedVehicle)">Edit Vehicle</button>
          </div>
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

const router = useRouter();

// State
const vehicles = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const refreshInput = ref('');
const refreshInterval = ref(null);
const selectedVehicle = ref(null);
const showDetailsModal = ref(false);

// Pagination
const currentPage = ref(1);
const itemsPerPage = 16;

// Stats
const stats = computed(() => {
    const total = vehicles.value.length;
    const ignitionOn = vehicles.value.filter(v => v.ignition).length;
    const ignitionOff = total - ignitionOn;
    // Mock data for maintenance and alerts as API doesn't fully support them yet
    const maintenance = "11,284";
    const alerts = "1,128,436";

    return {
        total,
        ignitionOn,
        ignitionOff,
        maintenance,
        alerts
    };
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

const fetchVehicles = async () => {
    try {
        const { data } = await axios.get('/web/monitoring/vehicles', { params: { per_page: 500 } });
        const list = Array.isArray(data) ? data : (data.data ?? []);

        vehicles.value = list.map(v => {
            const tc = v.tc_device || v.tcDevice || {};
            const pos = tc.position || {};
            const attrs = parseAttrs(pos.attributes);
            const deviceAttrs = parseAttrs(tc.attributes);

            return {
                id: v.device_id || v.id,
                name: tc.name || v.name || 'Unknown',
                uniqueid: tc.uniqueid || v.uniqueid || 'N/A',
                plate: v.plate || tc.plate || 'N/A',
                lat: parseFloat(pos.latitude) || 0,
                lng: parseFloat(pos.longitude) || 0,
                speed: parseFloat(pos.speed).toFixed(1) || 0,
                driver_name: v.driver_name || tc.driverUniqueId || 'N/A',
                last_update: pos.servertime || pos.fixtime || 'N/A',
                fuel: attrs.fuel || 0,
                odometer: attrs.odometer || 0,
                ignition: attrs.ignition || false,
                group: v.group || 'Default Group',
                model: tc.model || v.model || 'Unknown',
                type: deviceAttrs.type || 'Unknown',
                owner: v.manager ? v.manager.name : (v.group || 'N/A'),
                maintenance: 'N/A',
                alert_count: 0,
                blocked: v.blocked
            };
        });

    } catch (e) {
        console.error("Failed to fetch vehicles", e);
    } finally {
        loading.value = false;
    }
};

const applySearch = () => {
    currentPage.value = 1;
    // Auto refresh logic
    if (refreshInput.value) {
        setupAutoRefresh(refreshInput.value);
    }
};

const setupAutoRefresh = (val) => {
    if (refreshInterval.value) clearInterval(refreshInterval.value);

    let ms = 0;
    if (val.includes('min')) {
        ms = parseInt(val) * 60 * 1000;
    } else {
        ms = parseInt(val) * 1000;
    }

    if (ms > 0) {
        refreshInterval.value = setInterval(fetchVehicles, ms);
    }
};

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const showDetails = (vehicle) => {
    selectedVehicle.value = vehicle;
    showDetailsModal.value = true;
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

const toggleBlock = async (block) => {
    if (!confirm(`Are you sure you want to ${block ? 'block' : 'unblock'} this vehicle?`)) return;
    // Implementation would go here - likely a POST to /web/vehicles/block
    alert(`${block ? 'Blocking' : 'Unblocking'} vehicle...`);
};

onMounted(() => {
    fetchVehicles();
    // Default polling 30s
    refreshInterval.value = setInterval(fetchVehicles, 30000);
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

/* Table Styles */
.table th {
    font-weight: 500;
    font-size: 0.875rem;
    white-space: nowrap;
}
.table td {
    font-size: 0.875rem;
    vertical-align: middle;
}
.bg-success-subtle {
    background-color: #d1e7dd;
}
.bg-danger-subtle {
    background-color: #f8d7da;
}
</style>
