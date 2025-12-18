<template>
  <div class="container-fluid">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
        </li>
        <li class="breadcrumb-item">Monitoring</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Dashboard</li>
      </ol>
    </div>

    <!-- Page Title -->
    <div class="row mb-3">
      <div class="col-12">
        <h4 class="mb-0 fw-semibold">Vehicle Dashboard</h4>
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
                    <label class="form-label small">Auto Refresh Sec/Min</label>
                    <div class="d-flex gap-0 bg-light rounded overflow-hidden">
                        <button
                            v-for="(label, index) in refreshOptions"
                            :key="index"
                            class="btn btn-sm px-3 py-2 fw-medium flex-fill border-0 rounded-0"
                            :class="selectedRefresh === index ? 'btn-primary text-white' : 'btn-light text-muted'"
                            :style="selectedRefresh !== index ? 'background-color: #f8f9fa;' : 'background-color: #00A3FF;'"
                            @click="selectRefresh(index)"
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

    <!-- Vehicle Grid -->
    <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    <div v-else-if="paginatedVehicles.length === 0" class="text-center py-5 text-muted">
        No vehicles found matching your criteria.
    </div>
    <div v-else class="row g-3 mb-4">
        <div v-for="vehicle in paginatedVehicles" :key="vehicle.id" class="col-12 col-md-4">
            <div class="card border rounded-3 shadow-0 h-100 bg-white cursor-pointer hover-shadow transition-all" @click="showDetails(vehicle)">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <div class="fw-bold mb-0">{{ vehicle.vehicle_no || vehicle.name }}</div>
                        </div>
                        <span class="badge rounded-pill px-3 py-2 d-inline-flex align-items-center"
                              :class="speedClass(vehicle.speed)">
                            <i class="bi bi-speedometer me-1"></i> {{ vehicle.speed }} km/h
                        </span>
                    </div>
                    <div class="row g-3 align-items-center">
                        <div class="col-5">
                            <img :src="vehicle.image_verified_url || vehicle.image_url || '/assets/images/vehicle-placeholder.jpg'"
                                 class="img-fluid img-thumbnail rounded w-120"
                                 alt="Vehicle Image"
                                 onerror="this.src='https://placehold.co/360x240/e9ecef/6c757d?text=Vehicle'">
                        </div>
                        <div class="col-7">
                            <div class="fw-bold small">Last Report</div>
                            <div class="small mb-2">{{ vehicle.last_update }}</div>
                            <div class="fw-bold small">Ignition</div>
                            <div class="small mb-2">{{ vehicle.ignition ? 'On' : 'Off' }}</div>
                            <div class="fw-bold small">Location</div>
                            <a :href="`https://maps.google.com/?q=${vehicle.lat},${vehicle.lng}`"
                               target="_blank"
                               class="text-decoration-none text-info small">
                                {{ vehicle.address || `${vehicle.lat}, ${vehicle.lng}` }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-between align-items-center mb-4" v-if="!loading && filteredVehicles.length > 0">
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

    <!-- Vehicle Details Modal -->
    <VehicleDetailModal
      v-if="showDetailsModal"
      :vehicle="selectedVehicle"
      :show-zone-name="false"
      @close="closeModal"
      @change-status="openAlertStatusPopup"
    />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import { hasPermission } from '../../auth';
import VehicleDetailModal from '../../components/VehicleDetailModal.vue';

// State
const vehicles = ref([]);
const loading = ref(true);
const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = 12; // Adjusted for grid layout (3x4)
const showDetailsModal = ref(false);
const selectedVehicle = ref(null);

// Stats
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

// Auto Refresh
const refreshOptions = ['30s', '1m', '2m', '3m', '4m', '5m', '10m', 'Off'];
const selectedRefresh = ref(0); // default 30s
let refreshInterval = null;

// Methods
const fetchVehicles = async () => {
    try {
        const { data } = await axios.get('/web/monitoring/vehicles', { params: { per_page: 50 } });
        const list = Array.isArray(data) ? data : (data.data ?? []);

        if (data.stats) {
            stats.value = data.stats;
        }

        vehicles.value = list.map(v => {
            const tc = v.tc_device || v.tcDevice || {};
            console.log('device data list ',tc);
            const pos = tc.position || {};
            const attrs = parseAttrs(pos.attributes);
            const deviceAttrs = parseAttrs(tc.attributes);

            const vehicleId = deviceAttrs.vehicleNo || deviceAttrs.vehicle_id || deviceAttrs.vehicleId || deviceAttrs.vehicleID || null;
            const speedKmh = pos && pos.speed != null ? Number((parseFloat(pos.speed) * 1.852).toFixed(1)) : 0;
            const ign = attrs.ignition || false;
            const status = tc.status ? String(tc.status).charAt(0).toUpperCase() + String(tc.status).slice(1) : undefined;
            const imgAttr = deviceAttrs.vehicleImage || deviceAttrs.image || deviceAttrs.photo || (Array.isArray(deviceAttrs.photos) ? deviceAttrs.photos[0] : null) || v.photo || null;
            const imageUrl = resolveImage(imgAttr);

            return {
                id: v.device_id || v.id,
                name: vehicleId || tc.name || v.name || 'Unknown',
                vehicle_no: vehicleId || null,
                uniqueid: tc.uniqueid || v.uniqueid || 'N/A',
                type: deviceAttrs.type || 'Unknown',
                model: tc.model || v.model || 'Unknown',
                speed: speedKmh,
                ignition: ign,
                last_ignition_on: v.last_ignition_on || 'N/A',
                last_ignition_off: v.last_ignition_off || 'N/A',
                last_update: formatDate(pos.servertime || pos.fixtime),
                lat: parseFloat(pos.latitude) || 0,
                lng: parseFloat(pos.longitude) || 0,
                address: pos.address || '',
                maintenance_count: v.maintenance_count || 0,
                alert_count: v.alert_count || 0,
                status: status || 'Unknown',
                image_url: imageUrl,
                image_verified_url: null
            };
        });
        await verifyImages();
        loading.value = false;
    } catch (e) {
        console.error('Failed to fetch vehicles', e);
        loading.value = false;
    }
};

// Filter & Pagination
const filteredVehicles = computed(() => {
    if (!searchQuery.value) return vehicles.value;
    const query = searchQuery.value.toLowerCase();
    return vehicles.value.filter(v =>
        (v.name && v.name.toLowerCase().includes(query)) ||
        (v.vehicle_no && v.vehicle_no.toLowerCase().includes(query)) ||
        (v.uniqueid && v.uniqueid.toLowerCase().includes(query))
    );
});

const totalPages = computed(() => Math.ceil(filteredVehicles.value.length / itemsPerPage));
const paginationStart = computed(() => (currentPage.value - 1) * itemsPerPage + 1);
const paginationEnd = computed(() => Math.min(currentPage.value * itemsPerPage, filteredVehicles.value.length));

const paginatedVehicles = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredVehicles.value.slice(start, start + itemsPerPage);
});

const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages.value, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }
    return pages;
});

const changePage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

const applySearch = () => {
    currentPage.value = 1;
};

// Auto Refresh Logic
const setupAutoRefresh = (idx = selectedRefresh.value) => {
    if (refreshInterval) clearInterval(refreshInterval);
    const option = refreshOptions[idx];
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
        refreshInterval = setInterval(fetchVehicles, ms);
    }
};

const selectRefresh = (index) => {
    selectedRefresh.value = index;
    try { localStorage.setItem('vehicleDashboardRefresh', String(index)); } catch {}
    setupAutoRefresh(index);
};

// Modal Logic
const showDetails = (vehicle) => {
    selectedVehicle.value = vehicle;
    showDetailsModal.value = true;
};

const closeModal = () => {
    showDetailsModal.value = false;
    selectedVehicle.value = null;
};

const openAlertStatusPopup = (vehicle) => {
    // Placeholder for status change functionality
    console.log('Change status for', vehicle);
};

// Lifecycle
onMounted(() => {
    // Load saved refresh preference
    const savedRaw = localStorage.getItem('vehicleDashboardRefresh');
    if (savedRaw != null) {
        const savedIdx = parseInt(savedRaw);
        if (!Number.isNaN(savedIdx) && refreshOptions[savedIdx]) {
            selectedRefresh.value = savedIdx;
        } else {
            const legacy = parseInt(savedRaw);
            const legacyMap = { 30: 0, 60: 1, 120: 2, 180: 3, 240: 4, 300: 5, 600: 6 };
            const idx = legacyMap[legacy];
            if (typeof idx === 'number' && refreshOptions[idx]) selectedRefresh.value = idx;
        }
    }

    fetchVehicles();
    setupAutoRefresh(selectedRefresh.value);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});

// Helpers
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
    const year = String(d.getFullYear()).slice(-2);
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year}-${hours}:${minutes}`;
};
const placeholderImg = 'https://placehold.co/360x240/e9ecef/6c757d?text=Vehicle';
const resolveImage = (p) => {
    if (!p) return null;
    const s = String(p).trim();
    if (s.startsWith('http')) return s;
    if (s.startsWith('/storage/')) return s;
    if (s.startsWith('storage/')) return `/${s}`;
    if (s.includes('vehicle-images/')) return `/storage/${s}`;
    return `/${s}`;
};
const imageExists = async (url) => {
    try {
        const res = await fetch(url, { method: 'HEAD' });
        return res.ok;
    } catch {
        return false;
    }
};
const verifyImages = async () => {
    const tasks = vehicles.value.map(async (v) => {
        const u = v.image_url;
        if (!u) { v.image_verified_url = null; return; }
        const ok = await imageExists(u);
        v.image_verified_url = ok ? u : null;
    });
    try { await Promise.all(tasks); } catch {}
};
const speedClass = (s) => {
    const sp = Number(s) || 0;
    if (sp >= 120) return 'bg-danger-subtle text-danger';
    if (sp >= 80) return 'bg-warning-subtle text-warning';
    return 'bg-success-subtle text-success';
};
</script>

<style scoped>
.hover-shadow:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    transform: translateY(-2px);
}
.transition-all {
    transition: all 0.3s ease;
}
.cursor-pointer {
    cursor: pointer;
}
</style>
