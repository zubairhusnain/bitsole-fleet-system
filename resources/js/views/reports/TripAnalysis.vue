<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Trip Analysis Report</li>
      </ol>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center">
        <h4 class="mb-0">Trip Analysis Report</h4>
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
            Trip Analysis shows how your vehicles moved over time for the selected dates.
            It uses GPS trip data to calculate distance travelled, driving time, stop time and basic fuel usage where available.
            Each trip is built from start and end points so you can see when movement began, where it ended and what happened in between.
          </p>
          <p class="mb-2">
            Use the view type to switch between different summaries:
            Trip Summary gives one line per vehicle for a quick fleet-level overview,
            Daily Breakdown shows each trip with stops, events and locations in time order,
            Daily and Monthly summaries group totals by day or month and can show charts,
            and the “with map” view adds a route map so you can visually inspect paths and problem areas.
          </p>
          <p class="mb-2">
            The report highlights driving patterns such as frequent short trips, long continuous driving, extended stops and
            areas where the vehicle spent most of its time. Where your devices support it, fuel and event information
            (for example harsh braking or overspeeding) is also included so you can link driving behaviour to fuel usage.
          </p>
          <p class="mb-0">
            This report is useful to answer questions like “how far did each vehicle travel”, “when was it driving or stopped”,
            “which days were most active” and “where did important driving events such as harsh braking or overspeeding happen”.
          </p>
        </div>
      </div>
    </div>

    <UiAlert :show="!!errorMessage" :message="errorMessage" variant="danger" dismissible @dismiss="errorMessage = null" />

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
              Vehicle <span v-if="isVehicleRequired" class="text-danger">*</span>
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
          <div class="col-12 col-md-2 text-md-end">
            <div class="d-flex flex-column gap-2">
              <button class="btn btn-primary w-100" @click="handleSearch">Search</button>
              <div v-if="isExportableView" class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary flex-fill" :disabled="!canExport" @click="downloadCsvActive">
                  <i class="bi bi-file-earmark-excel me-1"></i> Excel
                </button>
                <button type="button" class="btn btn-outline-secondary flex-fill" :disabled="!canExport" @click="downloadPdfActive">
                  <i class="bi bi-file-earmark-pdf me-1"></i> PDF
                </button>
              </div>
            </div>
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

    <div v-else-if="!hasSearched" class="card border rounded-3 shadow-0">
      <div class="card-body text-center py-5 text-muted">
        Select filters and click <strong>Search</strong> to load the report.
      </div>
    </div>

    <template v-else>
      <ReportSummary
        v-if="activeSummary"
        :summary="activeSummary"
        :vehicle="selectedVehicleInfo"
        :dateRange="dateRange"
        :viewType="viewType"
      />

      <DailyBreakdown v-if="viewType === 'Daily Breakdown'" :rowsDailyTrips="rowsDailyTrips" :rowsDailyStops="rowsDailyStops" :summaryData="dailySummaryData" :vehicleInfo="selectedVehicleInfo" :startDate="startDate" :endDate="endDate" />

      <TripSummary v-else-if="viewType === 'Trip Summary'" :rowsTripSummary="rowsTripSummary" @view-details="handleViewDetails" />

      <DailyBreakdownMap v-else-if="viewType === 'Daily Breakdown (with map)'" :rowsDailyBreakdown="rowsDailyBreakdown" />

      <DailySummary v-else-if="viewType === 'Daily Summary'" :rowsDailySummary="rowsDailySummary" :summary="dailySummaryTotals" :chartData="dailySummaryChart" :vehicle="selectedVehicleInfo" :startDate="startDate" :endDate="endDate" />

      <DailySummaryList v-else-if="viewType === 'Daily Summary List'" :rowsDailyVehicleList="rowsDailyVehicleList" />

      <MonthlySummary v-else-if="viewType === 'Monthly Summary'" :rowsMonthlySummary="rowsMonthlySummary" :summary="monthlySummaryTotals" :chartData="monthlySummaryChart" :vehicle="selectedVehicleInfo" :startDate="startDate" :endDate="endDate" />

      <MonthlySummaryList v-else-if="viewType === 'Monthly Summary List'" :rowsMonthlyVehicleList="rowsMonthlyVehicleList" />
    </template>

  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import { createCancellableRequest, isRequestAborted } from '../../utils/cancellableRequest';
import { initTodayDatetimeRange, todayStartDatetimeLocal, todayEndDatetimeLocal } from '../../utils/reportDates';
import UiAlert from '../../components/UiAlert.vue';
import DailyBreakdown from './components/trip-analysis/DailyBreakdown.vue';
import DailyBreakdownMap from './components/trip-analysis/DailyBreakdownMap.vue';
import DailySummary from './components/trip-analysis/DailySummary.vue';
import DailySummaryList from './components/trip-analysis/DailySummaryList.vue';
import MonthlySummary from './components/trip-analysis/MonthlySummary.vue';
import MonthlySummaryList from './components/trip-analysis/MonthlySummaryList.vue';
import TripSummary from './components/trip-analysis/TripSummary.vue';
import ReportSummary from './components/trip-analysis/ReportSummary.vue';
import { openReportExport } from '../../utils/reportExport';

const showInfo = ref(false);
const { startDate: defaultStart, endDate: defaultEnd } = initTodayDatetimeRange();
const startDate = ref(defaultStart);
const endDate = ref(defaultEnd);
const vehicle = ref('');
const vehicles = ref([]);
const viewType = ref('Trip Summary');
const loading = ref(false);
const hasSearched = ref(false);
const errorMessage = ref(null);
const reportRequest = createCancellableRequest();

const rowsTripSummary = ref([]);
const rowsDailyTrips = ref([]);
const rowsDailyStops = ref([]);
const rowsDailyBreakdown = ref([]);
const rowsDailySummary = ref([]);
const rowsDailyVehicleList = ref([]);
const rowsMonthlySummary = ref([]);
const rowsMonthlyVehicleList = ref([]);
const dailySummaryData = ref(null);
const dailySummaryTotals = ref({});
const dailySummaryChart = ref([]);
const monthlySummaryTotals = ref({});
const monthlySummaryChart = ref([]);

const selectedVehicleInfo = computed(() => {
    if (!vehicle.value) return { name: 'All Vehicles', device_id: 'all' };
    return vehicles.value.find(v => v.device_id == vehicle.value) || null;
});

const isVehicleRequired = computed(() => {
    return ['Daily Breakdown', 'Daily Breakdown (with map)'].includes(viewType.value);
});

const isExportableView = computed(() => {
  return ['Trip Summary', 'Daily Summary List', 'Monthly Summary List'].includes(viewType.value);
});

const canExport = computed(() => {
  if (!isExportableView.value || !startDate.value || !endDate.value) return false;
  if (isVehicleRequired.value && !vehicle.value) return false;
  return true;
});

function exportParams() {
  const params = {
    from_date: startDate.value,
    to_date: endDate.value,
  };
  if (vehicle.value) params.device_ids = [vehicle.value];
  return params;
}

function downloadCsvActive() {
  if (!canExport.value) return;
  const params = exportParams();
  if (viewType.value === 'Trip Summary') {
    openReportExport('/web/reports/trip-summary/export-csv', params);
  } else if (viewType.value === 'Daily Summary List') {
    openReportExport('/web/reports/daily-summary/export-csv', params);
  } else if (viewType.value === 'Monthly Summary List') {
    openReportExport('/web/reports/monthly-summary/export-csv', params);
  }
}

function downloadPdfActive() {
  if (!canExport.value) return;
  const params = exportParams();
  if (viewType.value === 'Trip Summary') {
    openReportExport('/web/reports/trip-summary/export-pdf', params);
  } else if (viewType.value === 'Daily Summary List') {
    openReportExport('/web/reports/daily-summary/export-pdf', params);
  } else if (viewType.value === 'Monthly Summary List') {
    openReportExport('/web/reports/monthly-summary/export-pdf', params);
  }
}

const dateRange = computed(() => ({
  start: startDate.value,
  end: endDate.value
}));

const activeSummary = computed(() => {
  if (viewType.value === 'Daily Breakdown') return dailySummaryData.value;
  if (viewType.value === 'Daily Summary' || viewType.value === 'Daily Summary List') return dailySummaryTotals.value;
  if (viewType.value === 'Monthly Summary' || viewType.value === 'Monthly Summary List') return monthlySummaryTotals.value;
  if (viewType.value === 'Trip Summary') return null;
  if (viewType.value === 'Daily Breakdown (with map)') return dailySummaryData.value;
  return null;
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
  let from = startDate.value || todayStartDatetimeLocal();
  let to = endDate.value || todayEndDatetimeLocal();
  if (!startDate.value) startDate.value = from;
  if (!endDate.value) endDate.value = to;

  // Validate Device Selection
  if (!vehicle.value && isVehicleRequired.value) {
      errorMessage.value = 'Please select a vehicle for this report type.';
      return;
  }

  const params = {
    from_date: from,
    to_date: to,
  };

  if (vehicle.value) {
    params.device_ids = [vehicle.value];
  }

  const signal = reportRequest.nextSignal();
  hasSearched.value = true;
  loading.value = true;
  errorMessage.value = null;
  try {
    const requestConfig = { params, signal };
    if (viewType.value === 'Trip Summary') {
      const response = await window.axios.get('/web/reports/trip-summary', requestConfig);
      rowsTripSummary.value = response.data;
    } else if (viewType.value === 'Daily Breakdown') {
      const response = await window.axios.get('/web/reports/daily-trips', requestConfig);
      rowsDailyTrips.value = response.data.rows;
      rowsDailyStops.value = response.data.stops || [];
      dailySummaryData.value = response.data.summary;
    } else if (viewType.value === 'Daily Breakdown (with map)') {
      const response = await window.axios.get('/web/reports/daily-breakdown-map', requestConfig);
      rowsDailyBreakdown.value = response.data;
    } else if (viewType.value === 'Daily Summary') {
      const p = { ...params, group_by: 'date' };
      const response = await window.axios.get('/web/reports/daily-summary', { params: p, signal });
      rowsDailySummary.value = response.data.rows || [];
      dailySummaryTotals.value = response.data.summary || {};
      dailySummaryChart.value = response.data.chart || [];
    } else if (viewType.value === 'Daily Summary List') {
      const response = await window.axios.get('/web/reports/daily-summary', requestConfig);
      rowsDailyVehicleList.value = response.data.rows || [];
    } else if (viewType.value === 'Monthly Summary') {
      const p = { ...params, group_by: 'month' };
      const response = await window.axios.get('/web/reports/monthly-summary', { params: p, signal });
      rowsMonthlySummary.value = response.data.rows || [];
      monthlySummaryTotals.value = response.data.summary || {};
      monthlySummaryChart.value = response.data.chart || [];
    } else if (viewType.value === 'Monthly Summary List') {
      const response = await window.axios.get('/web/reports/monthly-summary', requestConfig);
      rowsMonthlyVehicleList.value = response.data.rows || [];
    }
  } catch (error) {
    if (isRequestAborted(error)) return;
    console.error('Error fetching report data:', error);
    errorMessage.value = error.response?.data?.message || 'Error fetching report data.';
    // Clear the current view's data on error
    if (viewType.value === 'Trip Summary') rowsTripSummary.value = [];
    else if (viewType.value === 'Daily Breakdown') rowsDailyTrips.value = [];
    else if (viewType.value === 'Daily Breakdown (with map)') rowsDailyBreakdown.value = [];
    else if (viewType.value === 'Daily Summary') rowsDailySummary.value = [];
    else if (viewType.value === 'Daily Summary List') rowsDailyVehicleList.value = [];
    else if (viewType.value === 'Monthly Summary') rowsMonthlySummary.value = [];
    else if (viewType.value === 'Monthly Summary List') rowsMonthlyVehicleList.value = [];
  } finally {
    if (!signal.aborted) {
      loading.value = false;
    }
  }
};

const handleViewDetails = (row) => {
  vehicle.value = row.key;
  viewType.value = 'Daily Breakdown';
  handleSearch();
};

watch(viewType, () => {
    if (!vehicle.value && vehicles.value.length > 0 && isVehicleRequired.value) {
        vehicle.value = vehicles.value[0].device_id;
    }
});

onMounted(() => {
  fetchVehicles();
});

onBeforeUnmount(() => {
  reportRequest.cancel();
});
</script>

<style scoped>
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
</style>
