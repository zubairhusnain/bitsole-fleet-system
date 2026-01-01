<template>
  <div class="container-fluid py-3">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
        </li>
        <li class="breadcrumb-item">Monitoring</li>
        <li class="breadcrumb-item active" aria-current="page">Zone Monitoring</li>
      </ol>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />

    <!-- Page Title -->
    <div class="row mb-3">
      <div class="col-sm-12 col-md-12 col-xl-8">
        <h4 class="mb-0 fw-semibold">Zone Monitoring</h4>
      </div>
    </div>

    <!-- Widgets -->
    <div class="row g-3 mb-3">
        <div class="col-sm-12 col-md-6 col-lg-3">
            <div class="card border rounded-4 shadow-0 h-100 bg-white">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-3 p-2 bg-light text-primary d-inline-flex align-items-center justify-content-center">
                        <i class="bi bi-diagram-3 fs-5"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Total Zone</div>
                        <div class="fw-semibold">{{ totalZones }} Zones</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-md-6 col-lg-3">
            <div class="card border rounded-4 shadow-0 h-100 bg-white">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-3 p-2 bg-light text-success d-inline-flex align-items-center justify-content-center">
                        <i class="bi bi-diagram-3 fs-5"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Active Zone</div>
                        <div class="fw-semibold">{{ activeZones }} Zones</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-md-6 col-lg-3">
            <div class="card border rounded-4 shadow-0 h-100 bg-white">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-3 p-2 bg-light text-danger d-inline-flex align-items-center justify-content-center">
                        <i class="bi bi-diagram-3 fs-5"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Inactive Zone</div>
                        <div class="fw-semibold">{{ inactiveZones }} Zones</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-12 col-md-6 col-lg-3">
            <div class="card border rounded-4 shadow-0 h-100 bg-white">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="rounded-3 p-2 bg-light text-info d-inline-flex align-items-center justify-content-center">
                        <i class="bi bi-car-front fs-5"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Vehicles in Zone</div>
                        <div class="fw-semibold">{{ vehiclesInZone }} Vehicles</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Option -->
    <div class="card border rounded-3 shadow-0 mb-3 bg-white">
      <div class="card-body">
        <div class="fw-semibold mb-2">Search Option</div>
        <div class="row g-2 align-items-end">
          <div class="col-sm-12 col-md-3">
            <label class="form-label small">Zone Name</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" placeholder="Enter Zone Name" v-model="filterName">
            </div>
          </div>
          <div class="col-sm-12 col-md-2">
            <label class="form-label small">Status</label>
            <select class="form-select" v-model="filterStatus">
                <option value="">-- Select --</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="col-sm-12 col-md-5">
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
          <div class="col-sm-12 col-md-2">
            <button class="btn btn-primary w-100" @click="applyFilters">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Zone Table -->
    <div class="card border rounded-3 shadow-0 bg-white">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
            <thead class="thead-app-dark">
              <tr>
                <th class="fw-semibold py-2">Zone Name</th>
                <th class="fw-semibold py-2">Description</th>
                <th class="fw-semibold py-2">Created</th>
                <th class="fw-semibold py-2">Last Update</th>
                <th class="fw-semibold py-2">Status</th>
                <th class="fw-semibold py-2 text-center">Assign Vehicles</th>
                <th class="fw-semibold py-2 text-center">Vehicles Inside</th>
                <th class="fw-semibold py-2 text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="9" class="text-center py-5">
                  <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </td>
              </tr>
              <tr v-else-if="paginatedZones.length === 0">
                <td colspan="9" class="text-center py-5 text-muted">
                  No zones found matching your criteria.
                </td>
              </tr>
              <tr v-else v-for="zone in paginatedZones" :key="zone.id">
                <td class="text-muted text-nowrap">{{ zone.name }}</td>
                <td class="text-muted text-nowrap">{{ zone.description || '—' }}</td>
                <td class="text-muted text-nowrap">{{ formatDate(zone.created_at) }}</td>
                <td class="text-muted text-nowrap">{{ formatDate(zone.updated_at) }}</td>
                <td class="text-nowrap">
                    <span :class="['status-badge', isActive(zone.status) ? 'is-on' : 'is-off']">
                        <span class="dot"></span>
                        {{ isActive(zone.status) ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="text-center">
                    <span class="badge rounded-pill px-3 py-2" style="background-color: #8B4513; color: white;">
                        {{ zone.assigned_count || 0 }} Vehicles
                    </span>
                </td>
                <td class="text-center">
                    <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">
                        {{ zone.inside_count || 0 }} Vehicles
                    </span>
                </td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <RouterLink :to="{ name: 'monitoring-zone-details', params: { zoneId: zone.id } }" class="btn btn-outline-primary" title="View">
                            <i class="bi bi-eye"></i>
                        </RouterLink>
                        <!-- Delete button removed as per monitoring view usually read-only or specific actions, keeping view only for now unless requested -->
                    </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Pagination -->
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">
            Showing {{ startIndex + 1 }} to {{ Math.min(endIndex, detailZones.length) }} of {{ detailZones.length }} results
        </div>
        <nav aria-label="Page navigation" class="ms-auto">
            <ul class="pagination pagination-sm mb-0 pagination-app">
                <li class="page-item" :class="{ disabled: currentPage === 1 }">
                    <button class="page-link" @click="prevPage">‹</button>
                </li>
                <li class="page-item" v-for="page in totalPages" :key="page" :class="{ active: currentPage === page }">
                    <button class="page-link" @click="goToPage(page)">
                        {{ page }}
                    </button>
                </li>
                <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                    <button class="page-link" @click="nextPage">›</button>
                </li>
            </ul>
        </nav>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import axios from 'axios';
import { formatDateTime } from '../../utils/datetime';
import UiAlert from '../../components/UiAlert.vue';

// State
const loading = ref(false);
const zones = ref([]);
const vehiclesInZoneCount = ref(0);
const error = ref('');

// Auto Refresh
const refreshOptions = ['30s', '1m', '2m', '3m', '4m', '5m', '10m', 'Off'];
const selectedRefresh = ref(7); // Default Off
const refreshInterval = ref(null);

// Filters
const filterName = ref('');
const filterStatus = ref('');
const activeFilterName = ref('');
const activeFilterStatus = ref('');

// Pagination
const currentPage = ref(1);
const itemsPerPage = 10;

// Helpers
const isActive = (status) => {
    if (!status) return true; // Default to active if status is missing/null
    return String(status).toLowerCase() === 'active';
};

// Computed Properties for Widgets
const totalZones = computed(() => zones.value.length);
const activeZones = computed(() => zones.value.filter(z => isActive(z.status)).length);
const inactiveZones = computed(() => zones.value.filter(z => !isActive(z.status)).length);
const vehiclesInZone = computed(() => vehiclesInZoneCount.value);

// Computed Properties for Table
const detailZones = computed(() => {
    let filtered = zones.value;

    // Filter by Name
    if (activeFilterName.value.trim()) {
        const q = activeFilterName.value.toLowerCase();
        filtered = filtered.filter(z => z.name.toLowerCase().includes(q));
    }

    // Filter by Status
    if (activeFilterStatus.value) {
        const isFilterActive = activeFilterStatus.value === 'active';
        filtered = filtered.filter(z => isActive(z.status) === isFilterActive);
    }

    return filtered;
});

const totalPages = computed(() => Math.ceil(detailZones.value.length / itemsPerPage));
const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage);
const endIndex = computed(() => startIndex.value + itemsPerPage);
const paginatedZones = computed(() => detailZones.value.slice(startIndex.value, endIndex.value));

// Methods
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
        refreshInterval.value = setInterval(fetchZones, ms);
    }

    try { localStorage.setItem('zoneMonitoringRefresh', val); } catch {}
};

watch(selectedRefresh, (val) => {
    setupAutoRefresh(val);
});

onUnmounted(() => {
    if (refreshInterval.value) clearInterval(refreshInterval.value);
});

const fetchZones = async () => {
  try {
    if (zones.value.length === 0) loading.value = true;
    error.value = '';
    const { data } = await axios.get('/web/monitoring/zones', {
        params: { mine: true }
    });
    zones.value = data.zones || [];
    vehiclesInZoneCount.value = data.vehicles_in_zone || 0;
  } catch (e) {
    console.error("Failed to fetch zones", e);
    error.value = 'Failed to load zones.';
  } finally {
    loading.value = false;
  }
};

const applyFilters = () => {
    activeFilterName.value = filterName.value;
    activeFilterStatus.value = filterStatus.value;
    currentPage.value = 1;
};

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    return formatDateTime(dateStr);
};

// Pagination Methods
const nextPage = () => {
    if (currentPage.value < totalPages.value) currentPage.value++;
};

const prevPage = () => {
    if (currentPage.value > 1) currentPage.value--;
};

const goToPage = (page) => {
    currentPage.value = page;
};

onMounted(() => {
    fetchZones();
    // Load preference
    try {
        const saved = localStorage.getItem('zoneMonitoringRefresh');
        if (saved !== null) {
            const idx = parseInt(saved, 10);
            if (!isNaN(idx) && idx >= 0 && idx < refreshOptions.length) {
                selectedRefresh.value = idx;
            }
        }
    } catch {}
});
</script>

<style scoped>
/* Reuse global app.css styles for tables, badges, and pagination */
</style>
