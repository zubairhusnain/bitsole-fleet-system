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
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Duration</label>
            <div class="input-group">
              <input v-model="duration" type="text" class="form-control" placeholder="dd/mm/yyyy - dd/mm/yyyy" />
            </div>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Vehicle</label>
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

    <DailyBreakdown v-if="viewType === 'Daily Breakdown'" :rowsDailyTrips="rowsDailyTrips" />

    <TripSummary v-else-if="viewType === 'Trip Summary'" :rowsTripSummary="rowsTripSummary" @view-details="handleViewDetails" />

    <DailySummaryList v-else-if="viewType === 'Daily Summary List'" :rowsDailyVehicleList="rowsDailyVehicleList" />

    <DailyBreakdownMap v-else-if="viewType === 'Daily Breakdown (with map)'" :rowsDailyBreakdown="rowsDailyBreakdown" />

    <DailySummary v-else-if="viewType === 'Daily Summary'" :rowsDailySummary="rowsDailySummary" />

    <MonthlySummary v-else-if="viewType === 'Monthly Summary'" :rowsMonthlySummary="rowsMonthlySummary" />

    <MonthlySummaryList v-else-if="viewType === 'Monthly Summary List'" :rowsMonthlyVehicleList="rowsMonthlyVehicleList" />

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DailyBreakdown from './components/trip-analysis/DailyBreakdown.vue';
import DailyBreakdownMap from './components/trip-analysis/DailyBreakdownMap.vue';
import DailySummary from './components/trip-analysis/DailySummary.vue';
import DailySummaryList from './components/trip-analysis/DailySummaryList.vue';
import MonthlySummary from './components/trip-analysis/MonthlySummary.vue';
import MonthlySummaryList from './components/trip-analysis/MonthlySummaryList.vue';
import TripSummary from './components/trip-analysis/TripSummary.vue';

const duration = ref('');
const vehicle = ref('');
const vehicles = ref([]);
const viewType = ref('Trip Summary');

const rowsTripSummary = ref([]);
const rowsDailyTrips = ref([]);
const rowsDailyBreakdown = ref([]);
const rowsDailySummary = ref([]);
const rowsDailyVehicleList = ref([]);
const rowsMonthlySummary = ref([]);
const rowsMonthlyVehicleList = ref([]);

const fetchVehicles = async () => {
  try {
    const response = await window.axios.get('/web/reports/device-options');
    // Ensure format matches {id, name, device_id}
    vehicles.value = response.data.options.map(v => ({
        id: v.id,
        name: v.label,
        device_id: v.deviceId
    }));
  } catch (error) {
    console.error('Error fetching vehicles:', error);
  }
};

const handleSearch = async () => {
  // Parse duration
  let from = '';
  let to = '';
  if (duration.value) {
    const parts = duration.value.split(' - ');
    if (parts.length === 2) {
      const toIso = (d) => {
        const [day, month, year] = d.split('/');
        return `${year}-${month}-${day}`;
      };
      from = toIso(parts[0]);
      to = toIso(parts[1]);
    }
  }

  // Default to today if empty or invalid
  if (!from) {
      const today = new Date().toISOString().split('T')[0];
      from = today;
      to = today;

      // Update display
      const d = new Date();
      const dd = String(d.getDate()).padStart(2, '0');
      const mm = String(d.getMonth() + 1).padStart(2, '0');
      const yyyy = d.getFullYear();
      duration.value = `${dd}/${mm}/${yyyy} - ${dd}/${mm}/${yyyy}`;
  }

  const params = {
    from_date: from,
    to_date: to,
  };

  if (vehicle.value) {
    params.device_ids = [vehicle.value];
  }

  try {
    if (viewType.value === 'Trip Summary') {
      const response = await window.axios.get('/web/reports/trip-summary', { params });
      rowsTripSummary.value = response.data;
    } else if (viewType.value === 'Daily Breakdown') {
      const response = await window.axios.get('/web/reports/daily-trips', { params });
      rowsDailyTrips.value = response.data;
    } else if (viewType.value === 'Daily Breakdown (with map)') {
      const response = await window.axios.get('/web/reports/daily-breakdown-map', { params });
      rowsDailyBreakdown.value = response.data;
    } else if (viewType.value === 'Daily Summary') {
      const response = await window.axios.get('/web/reports/daily-summary', { params });
      rowsDailySummary.value = response.data;
    } else if (viewType.value === 'Daily Summary List') {
      const response = await window.axios.get('/web/reports/daily-summary', { params });
      rowsDailyVehicleList.value = response.data;
    } else if (viewType.value === 'Monthly Summary') {
      const response = await window.axios.get('/web/reports/monthly-summary', { params });
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
  }
};

const handleViewDetails = (row) => {
  vehicle.value = row.key;
  viewType.value = 'Daily Breakdown';
  handleSearch();
};

onMounted(() => {
  fetchVehicles();

  // Set default duration to last 7 days
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - 6);

  const formatDate = (date) => {
      const dd = String(date.getDate()).padStart(2, '0');
      const mm = String(date.getMonth() + 1).padStart(2, '0');
      const yyyy = date.getFullYear();
      return `${dd}/${mm}/${yyyy}`;
  };

  duration.value = `${formatDate(start)} - ${formatDate(end)}`;

  handleSearch();
});
</script>

<style scoped>
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
</style>
