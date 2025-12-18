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

    <!-- Page Title -->
    <div class="row mb-3">
      <div class="col-sm-12 col-md-12 col-xl-8">
        <h4 class="mb-0 fw-semibold">Zone Monitoring</h4>
      </div>
    </div>

    <!-- Controls -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
      <div class="card-body p-4">
        <div class="row g-4">
          <!-- Asset Search -->
          <div class="col-md-5">
            <label class="form-label fw-semibold small text-dark mb-2">Asset Search</label>
            <div class="input-group">
              <input type="text" class="form-control border-end-0 bg-white" placeholder="Search Asset Reg. No." v-model="searchQuery" @keyup.enter="fetchZones">
              <span class="input-group-text bg-white border-start-0 text-primary cursor-pointer" @click="fetchZones">
                <i class="bi bi-arrow-clockwise"></i>
              </span>
            </div>
          </div>
          <!-- Auto Refresh -->
          <div class="col-md-7">
            <label class="form-label fw-semibold small text-dark mb-2">Auto Refresh Sec/Min</label>
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
        </div>

        <!-- Zone Summary Cards -->
        <div class="row g-3 mt-4">
          <div v-for="zone in visibleSummaryZones" :key="zone.id" class="col-12 col-md-4 col-lg-2">
            <div class="card border-0 rounded-4 h-100 cursor-pointer transition-transform"
                 :style="{ backgroundColor: zone.bgColor, transform: selectedZoneId === zone.id ? 'scale(1.05)' : 'scale(1)', border: selectedZoneId === zone.id ? `2px solid ${zone.barColor}` : 'none' }"
                 @click="toggleZoneSelection(zone.id)">
              <div class="card-body p-3 d-flex flex-column justify-content-between" style="min-height: 120px;">
                <div class="mb-2">
                  <div class="rounded-circle d-flex align-items-center justify-content-center"
                       :style="{ width: '32px', height: '32px', backgroundColor: 'rgba(255,255,255,0.4)' }">
                    <i class="bi bi-diagram-3" :style="{ color: zone.textColor }"></i>
                  </div>
                </div>
                <div>
                  <div class="fw-semibold mb-1 text-truncate" :title="zone.name" :style="{ color: '#333' }">{{ zone.name }}</div>
                  <div class="d-flex align-items-end justify-content-between">
                    <h3 class="fw-bold mb-0" :style="{ color: '#333' }">{{ zone.count }}</h3>
                    <div class="d-flex align-items-center gap-2 w-50 mb-1">
                      <div class="progress flex-grow-1" style="height: 6px; background-color: rgba(0,0,0,0.1);">
                        <div class="progress-bar" role="progressbar"
                             :style="{ width: zone.percent + '%', backgroundColor: zone.barColor }"></div>
                      </div>
                      <span class="small fw-medium" :style="{ color: '#666', fontSize: '0.75rem' }">{{ zone.percent }}%</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Loader on scroll -->
          <div v-if="isLoadingMore" class="col-12 text-center py-3">
              <div class="spinner-border text-primary spinner-border-sm" role="status">
                  <span class="visually-hidden">Loading...</span>
              </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Zone Details -->
    <div class="d-flex flex-column gap-3">
      <div v-if="loading" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
          </div>
      </div>
      <div v-else-if="detailZones.length === 0" class="text-center py-5 text-muted">
          No zones found matching your criteria.
      </div>
      <div v-else v-for="zone in detailZones" :key="'detail-' + zone.id" class="card border-0 shadow-sm rounded-4 bg-white">
        <div class="card-body p-4">
          <!-- Zone Header -->
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle d-flex align-items-center justify-content-center"
                   :style="{ width: '32px', height: '32px', backgroundColor: zone.bgColor }">
                <i class="bi bi-diagram-3" :style="{ color: zone.textColor }"></i>
              </div>
              <h5 class="fw-bold mb-0 text-dark">{{ zone.name }}</h5>
            </div>
            <div class="text-muted small fw-medium">{{ zone.count }} / Total {{ totalDevices }}</div>
          </div>

          <!-- Vehicles Grid -->
          <div class="row g-3">
            <div v-for="(vehicle, idx) in zone.vehicles" :key="idx" class="col-12 col-sm-6 col-md-4 col-lg-3 col-xl-2">
              <div class="d-flex align-items-center p-3 rounded-3 bg-light-subtle border border-light-subtle h-100 cursor-pointer hover-shadow"
                   @click="openVehicleModal(vehicle)">
                <i class="bi bi-power fs-4 me-3" :class="vehicle.active ? 'text-success' : 'text-danger'"></i>
                <div class="overflow-hidden">
                  <div class="text-muted small text-truncate" style="font-size: 0.7rem;">{{ vehicle.uniqueid }}</div>
                  <div class="fw-bold text-dark text-truncate" :title="vehicle.name">{{ vehicle.name }}</div>
                </div>
              </div>
            </div>
            <div v-if="zone.vehicles.length === 0" class="col-12">
              <div class="text-muted small fst-italic">No vehicles in this zone</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Vehicle Detail Modal -->
    <VehicleDetailModal
      v-if="showModal"
      :vehicle="selectedVehicle"
      @close="closeModal"
      @change-status="changeVehicleStatus"
    />

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import axios from 'axios';
import VehicleDetailModal from '../../components/VehicleDetailModal.vue';

// State
const loading = ref(false);
const refreshOptions = ['30s', '1m', '2m', '3m', '4m', '5m', '10m', 'Off'];
const selectedRefresh = ref(0); // Default 30s
const searchQuery = ref('');
const totalDevices = ref(0);
const zones = ref([]);
const selectedZoneId = ref(null);
const refreshInterval = ref(null);
const visibleLimit = ref(12);
const isLoadingMore = ref(false);

// Modal State
const showModal = ref(false);
const selectedVehicle = ref(null);

// Color Palette (Pastels)
const zoneColors = [
  { bgColor: '#FFEBEE', textColor: '#EF5350', barColor: '#EF5350' }, // Pink
  { bgColor: '#FFF8E1', textColor: '#FFCA28', barColor: '#FFCA28' }, // Yellow
  { bgColor: '#E8F5E9', textColor: '#66BB6A', barColor: '#66BB6A' }, // Green
  { bgColor: '#F3E5F5', textColor: '#AB47BC', barColor: '#AB47BC' }, // Purple
  { bgColor: '#E1F5FE', textColor: '#29B6F6', barColor: '#29B6F6' }, // Blue
  { bgColor: '#F5F5F5', textColor: '#757575', barColor: '#BDBDBD' }, // Grey
];

// Computed
const summaryZones = computed(() => {
    return zones.value.map((z, index) => {
        const colors = zoneColors[index % zoneColors.length];
        return { ...z, ...colors };
    });
});

const visibleSummaryZones = computed(() => {
    return summaryZones.value.slice(0, visibleLimit.value);
});

const detailZones = computed(() => {
    let filtered = summaryZones.value;

    // Filter by selected zone
    if (selectedZoneId.value) {
        filtered = filtered.filter(z => z.id === selectedZoneId.value);
    }

    // Filter by search query (vehicle name or reg no)
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        // Deep filter vehicles inside zones
        filtered = filtered.map(z => ({
            ...z,
            vehicles: z.vehicles.filter(v =>
                (v.name && v.name.toLowerCase().includes(q)) ||
                (v.uniqueid && v.uniqueid.toLowerCase().includes(q))
            )
        })).filter(z => z.vehicles.length > 0);
    }

    return filtered;
});

// Methods
const fetchZones = async () => {
  try {
    // Only show loading on first load or manual refresh, not auto-refresh
    if (zones.value.length === 0) loading.value = true;

    const { data } = await axios.get('/web/monitoring/zones', {
        params: { mine: true } // Or other filters if needed
    });

    zones.value = data.zones || [];
    totalDevices.value = data.total_devices || 0;

    // Default to first zone if none selected and zones exist
    if (!selectedZoneId.value && zones.value.length > 0) {
        selectedZoneId.value = zones.value[0].id;
    }
  } catch (e) {
    console.error("Failed to fetch zones", e);
  } finally {
    loading.value = false;
  }
};

const toggleZoneSelection = (id) => {
    selectedZoneId.value = id;
};

const openVehicleModal = (vehicle) => {
    selectedVehicle.value = vehicle;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedVehicle.value = null;
};

const changeVehicleStatus = (vehicle) => {
    // Placeholder for status change logic
    alert(`Change status for ${vehicle.name}`);
};

const handleScroll = () => {
    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 100) {
        if (!isLoadingMore.value && visibleLimit.value < zones.value.length) {
            isLoadingMore.value = true;
            // Simulate loading delay
            setTimeout(() => {
                visibleLimit.value += 12;
                isLoadingMore.value = false;
            }, 500);
        }
    }
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
        refreshInterval.value = setInterval(fetchZones, ms);
    }

    try { localStorage.setItem('zoneMonitoringRefresh', val); } catch {}
};

watch(selectedRefresh, (val) => {
    setupAutoRefresh(val);
});

onMounted(() => {
    fetchZones();
    window.addEventListener('scroll', handleScroll);

    // Restore refresh preference
    try {
        const saved = localStorage.getItem('zoneMonitoringRefresh');
        if (saved !== null) {
            const idx = parseInt(saved);
            if (!isNaN(idx) && idx >= 0 && idx < refreshOptions.length) {
                selectedRefresh.value = idx;
                setupAutoRefresh(idx);
            }
        } else {
             // Default 30s (Index 0)
            setupAutoRefresh(0);
        }
    } catch {
        setupAutoRefresh(0);
    }
});

onUnmounted(() => {
    if (refreshInterval.value) clearInterval(refreshInterval.value);
    window.removeEventListener('scroll', handleScroll);
});
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
.btn-light {
  background-color: #f8f9fa;
  border-color: #f8f9fa;
}
.bg-light-subtle {
    background-color: #f8f9fa !important;
}
.hover-shadow:hover {
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
    transition: box-shadow 0.2s;
}
.transition-transform {
    transition: transform 0.2s, border 0.2s;
}
</style>
