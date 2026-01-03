<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Trip Analysis Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Trip Analysis Report</h4>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body pt-2">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold text-muted">Start Date</label>
            <input v-model="startDate" type="datetime-local" class="form-control" />
          </div>
          <div class="col-12 col-md-2">
            <label class="form-label small fw-semibold text-muted">End Date</label>
            <input v-model="endDate" type="datetime-local" class="form-control" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">
              Vehicle <span v-if="viewType === 'Daily Breakdown' || viewType === 'Daily Breakdown (with map)'" class="text-danger">*</span>
            </label>
            <select v-model="vehicle" class="form-select text-muted">
              <option value="">-- All Vehicles --</option>
              <option v-for="v in vehicles" :key="v.id" :value="v.device_id">
                {{ v.name }}
              </option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">View Type</label>
            <select v-model="viewType" class="form-select text-muted">
              <option>Trip Summary</option>
              <option>Daily Breakdown</option>
              <option>Daily Breakdown (with map)</option>
              <option>Daily Summary</option>
              <option>Daily Summary List</option>
              <option>Monthly Summary</option>
              <option>Monthly Summary List</option>
            </select>
          </div>
          <div class="col-12 col-md-1 text-md-end">
            <button class="btn btn-primary w-100" @click="handleSearch">Search</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="d-flex align-items-center justify-content-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <template v-else>
      <DailyBreakdown v-if="viewType === 'Daily Breakdown'" :rowsDailyTrips="rowsDailyTrips" :rowsDailyStops="rowsDailyStops" :summaryData="dailySummaryData" :vehicleInfo="selectedVehicleInfo" :startDate="startDate" :endDate="endDate" />

      <TripSummary v-else-if="viewType === 'Trip Summary'" :rowsTripSummary="rowsTripSummary" @view-details="handleViewDetails" />

      <DailySummaryList v-else-if="viewType === 'Daily Summary List'" :rowsDailyVehicleList="rowsDailyVehicleList" />

      <DailyBreakdownMap v-else-if="viewType === 'Daily Breakdown (with map)'" :rowsDailyBreakdown="rowsDailyBreakdown" />

      <DailySummary v-else-if="viewType === 'Daily Summary'" :rowsDailySummary="rowsDailySummary" />

      <MonthlySummary v-else-if="viewType === 'Monthly Summary'" :rowsMonthlySummary="rowsMonthlySummary" />

      <MonthlySummaryList v-else-if="viewType === 'Monthly Summary List'" :rowsMonthlyVehicleList="rowsMonthlyVehicleList" />
    </template>

  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import DailyBreakdown from './components/trip-analysis/DailyBreakdown.vue';
import DailyBreakdownMap from './components/trip-analysis/DailyBreakdownMap.vue';
import DailySummary from './components/trip-analysis/DailySummary.vue';
import DailySummaryList from './components/trip-analysis/DailySummaryList.vue';
import MonthlySummary from './components/trip-analysis/MonthlySummary.vue';
import MonthlySummaryList from './components/trip-analysis/MonthlySummaryList.vue';
import TripSummary from './components/trip-analysis/TripSummary.vue';

const startDate = ref('');
const endDate = ref('');
const vehicle = ref('');
const vehicles = ref([]);
const viewType = ref('Trip Summary');
const loading = ref(false);

const rowsTripSummary = ref([]);
const rowsDailyTrips = ref([]);
const rowsDailyStops = ref([]);
const rowsDailyBreakdown = ref([]);
const rowsDailySummary = ref([]);
const rowsDailyVehicleList = ref([]);
const rowsMonthlySummary = ref([]);
const rowsMonthlyVehicleList = ref([]);
const dailySummaryData = ref(null);

const selectedVehicleInfo = computed(() => {
    if (!vehicle.value) return null;
    return vehicles.value.find(v => v.device_id == vehicle.value) || null;
});

const fetchVehicles = async () => {
  try {
    const response = await window.axios.get('/web/reports/device-options');
    // Ensure format matches {id, name, device_id}
    vehicles.value = response.data.options.map(v => ({
        id: v.id,
        name: v.name,
        device_id: v.deviceId,
        uniqueId: v.uniqueId
    }));
  } catch (error) {
    console.error('Error fetching vehicles:', error);
  }
};

const handleSearch = async () => {
  let from = startDate.value;
  let to = endDate.value;

  // Default to today if empty
  if (!from) {
      const now = new Date();
      now.setHours(0, 0, 0, 0);
      // Adjust to local timezone ISO string for input
      const offset = now.getTimezoneOffset() * 60000;
      const localISOTime = (new Date(now - offset)).toISOString().slice(0, 16);
      startDate.value = localISOTime;
      from = localISOTime;
  }

  if (!to) {
      const now = new Date();
      now.setHours(23, 59, 59, 999);
      const offset = now.getTimezoneOffset() * 60000;
      const localISOTime = (new Date(now - offset)).toISOString().slice(0, 16);
      endDate.value = localISOTime;
      to = localISOTime;
  }

  // Validate Device Selection for Breakdown views
  if (!vehicle.value && (viewType.value === 'Daily Breakdown' || viewType.value === 'Daily Breakdown (with map)')) {
      alert('Please select a vehicle for this report type.');
      return;
  }

  const params = {
    from_date: from,
    to_date: to,
  };

  if (vehicle.value) {
    params.device_ids = [vehicle.value];
  }

  loading.value = true;
  try {
    if (viewType.value === 'Trip Summary') {
      const response = await window.axios.get('/web/reports/trip-summary', { params });
      rowsTripSummary.value = response.data;
    } else if (viewType.value === 'Daily Breakdown') {
      const response = await window.axios.get('/web/reports/daily-trips', { params });
      rowsDailyTrips.value = response.data.rows;
      rowsDailyStops.value = response.data.stops || [];
      dailySummaryData.value = response.data.summary;
    } else if (viewType.value === 'Daily Breakdown (with map)') {
      const response = await window.axios.get('/web/reports/daily-breakdown-map', { params });
      rowsDailyBreakdown.value = response.data;
    } else if (viewType.value === 'Daily Summary') {
      const p = { ...params, group_by: 'date' };
      const response = await window.axios.get('/web/reports/daily-summary', { params: p });
      rowsDailySummary.value = response.data;
    } else if (viewType.value === 'Daily Summary List') {
      const response = await window.axios.get('/web/reports/daily-summary', { params });
      rowsDailyVehicleList.value = response.data;
    } else if (viewType.value === 'Monthly Summary') {
      const p = { ...params, group_by: 'month' };
      const response = await window.axios.get('/web/reports/monthly-summary', { params: p });
      rowsMonthlySummary.value = response.data;
    } else if (viewType.value === 'Monthly Summary List') {
      const response = await window.axios.get('/web/reports/monthly-summary', { params });
      rowsMonthlyVehicleList.value = response.data;
    }
  } catch (error) {
    console.error('Error fetching report data:', error);
    // Clear the current view's data on error
    if (viewType.value === 'Trip Summary') rowsTripSummary.value = [];
    else if (viewType.value === 'Daily Breakdown') rowsDailyTrips.value = [];
    else if (viewType.value === 'Daily Breakdown (with map)') rowsDailyBreakdown.value = [];
    else if (viewType.value === 'Daily Summary') rowsDailySummary.value = [];
    else if (viewType.value === 'Daily Summary List') rowsDailyVehicleList.value = [];
    else if (viewType.value === 'Monthly Summary') rowsMonthlySummary.value = [];
    else if (viewType.value === 'Monthly Summary List') rowsMonthlyVehicleList.value = [];
  } finally {
    loading.value = false;
  }
};

const handleViewDetails = (row) => {
  vehicle.value = row.key;
  viewType.value = 'Daily Breakdown';
  handleSearch();
};

onMounted(() => {
  fetchVehicles();

  // Set default duration to last 7 days (Start of day to End of day)
  const end = new Date();
  end.setHours(23, 59, 59, 999);

  const start = new Date();
  start.setDate(end.getDate() - 6);
  start.setHours(0, 0, 0, 0);

  const toIsoLocal = (date) => {
      const offset = date.getTimezoneOffset() * 60000;
      return (new Date(date - offset)).toISOString().slice(0, 16);
  };

  startDate.value = toIsoLocal(start);
  endDate.value = toIsoLocal(end);

  handleSearch();
});
</script>

<style scoped>
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
</style>
