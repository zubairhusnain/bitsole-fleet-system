<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports &amp; Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Fuel Report</li>
      </ol>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center">
        <h4 class="mb-0">Fuel Report</h4>
        <button
          type="button"
          class="btn btn-link p-0 ms-2 text-muted"
          :class="{ 'd-testingmode': !isTestingMode }"
          @click="showInfo = !showInfo"
        >
          <i class="bi bi-info-circle"></i>
        </button>
      </div>
    </div>

    <div v-if="showInfo" class="mb-3" :class="{ 'd-testingmode': !isTestingMode }">
      <div class="card border-0 bg-light">
        <div class="card-header bg-transparent py-2">
          <div class="fw-semibold small">About this report</div>
        </div>
        <div class="card-body pt-2 pb-3 small">
          <p class="mb-2">
            Fuel Report summarises fuel consumption, refuelling and efficiency for each vehicle over the selected period
            using Traccar core fuel data.
          </p>
          <p class="mb-2">
            It combines distance, tank capacity and fuel level changes to estimate how much fuel was consumed, how often
            vehicles were refuelled and the average litres per 100km.
          </p>
          <p class="mb-0">
            Use this report to identify high-consumption vehicles, investigate unusual fuel drops and track refuelling
            behaviour across your fleet.
          </p>
        </div>
      </div>
    </div>

    <UiAlert
      :show="!!errorMessage"
      :message="errorMessage"
      variant="danger"
      dismissible
      @dismiss="errorMessage = ''"
    />

    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-5">
            <label class="form-label text-muted small fw-semibold">Duration</label>
            <div class="input-group">
              <input type="date" v-model="fromDate" class="form-control" />
              <span class="input-group-text bg-white">-</span>
              <input type="date" v-model="toDate" class="form-control" />
            </div>
          </div>
          <div class="col-12 col-md-5">
            <label class="form-label text-muted small fw-semibold">Vehicle (optional)</label>
            <select class="form-select" v-model="selectedDeviceId">
              <option value="">All Vehicles</option>
              <option v-for="opt in deviceOptions" :key="opt.id" :value="opt.id">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div class="col-12 col-md-2">
            <button
              class="btn btn-info text-white w-100 fw-semibold"
              style="background-color: #0ea5e9; border: none;"
              @click="fetchReport"
              :disabled="loading"
            >
              Submit
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="text-center my-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>

    <div v-else>
      <div class="card border-0 shadow-0 mb-3">
        <div class="card-header bg-transparent py-2">
          <h6 class="mb-0 fw-bold text-uppercase small text-muted">Fuel Refuel Summary</h6>
        </div>
        <div class="card-body">
          <div v-if="!fuelSummary">
            <div class="text-muted small">
              No refuel events for the selected filters.
            </div>
          </div>
          <div v-else class="row g-3">
            <div class="col-12 col-md-3">
              <div class="fuel-widget fuel-widget-primary">
                <div class="fuel-widget-icon">
                  <i class="bi bi-fuel-pump"></i>
                </div>
                <div class="fuel-widget-body">
                  <div class="fuel-widget-label">Total Refuel Volume</div>
                  <div class="fuel-widget-value">{{ fuelSummary.totalRefillLitres.toFixed(1) }} L</div>
                  <div class="fuel-widget-sub">Sum of all refuel events</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-3">
              <div class="fuel-widget fuel-widget-emerald">
                <div class="fuel-widget-icon">
                  <i class="bi bi-droplet-half"></i>
                </div>
                <div class="fuel-widget-body">
                  <div class="fuel-widget-label">Refuel Events</div>
                  <div class="fuel-widget-value">{{ fuelSummary.totalEvents }}</div>
                  <div class="fuel-widget-sub">Number of refuel entries</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-3">
              <div class="fuel-widget fuel-widget-amber">
                <div class="fuel-widget-icon">
                  <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="fuel-widget-body">
                  <div class="fuel-widget-label">Average Refuel Size</div>
                  <div class="fuel-widget-value">{{ fuelSummary.avgRefillLitres.toFixed(1) }} L</div>
                  <div class="fuel-widget-sub">Per refuel event</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-3">
              <div class="fuel-widget fuel-widget-sky">
                <div class="fuel-widget-icon">
                  <i class="bi bi-percent"></i>
                </div>
                <div class="fuel-widget-body">
                  <div class="fuel-widget-label">Avg Level After Refuel</div>
                  <div class="fuel-widget-value">{{ fuelSummary.avgEndPct.toFixed(1) }}%</div>
                  <div class="fuel-widget-sub">{{ fuelSummary.periodLabel }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card border rounded-3 shadow-0">
        <div class="card-header bg-white py-3"><h6 class="mb-0 fw-bold">Fuel Entries</h6></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0 table-hover">
              <thead class="table-dark">
                <tr>
                  <th class="py-3 ps-3">Date / Time</th>
                  <th class="py-3">Vehicle</th>
                  <th class="py-3">Start Level</th>
                  <th class="py-3">End Level</th>
                  <th class="py-3">Refuel Qty (L)</th>
                  <th class="py-3">Fuel After (L)</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="entries.length === 0">
                  <td colspan="6" class="text-center text-muted py-4">No results</td>
                </tr>
                <tr v-else v-for="entry in pagedEntries" :key="entry.time + '-' + entry.deviceId">
                  <td class="ps-3 py-3 fw-medium text-dark">
                    {{ entry.eventDate }} {{ entry.eventTime }}
                  </td>
                  <td class="py-3">{{ entry.vehicleName }}</td>
                  <td class="py-3">
                    <span class="badge rounded-pill bg-light text-dark">
                      {{ formatPct(entry.startPct) }}%
                    </span>
                    <span class="text-muted small ms-1">
                      ({{ formatLitres(entry.startPct, entry.tankCapacity) }} L)
                    </span>
                  </td>
                  <td class="py-3">
                    <span class="badge rounded-pill bg-primary-subtle text-primary">
                      {{ formatPct(entry.endPct) }}%
                    </span>
                    <span class="text-muted small ms-1">
                      ({{ formatLitres(entry.endPct, entry.tankCapacity) }} L)
                    </span>
                  </td>
                  <td class="py-3">{{ entry.increaseLitres.toFixed(2) }}</td>
                  <td class="py-3">{{ (entry.endLitres ?? 0).toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer d-flex align-items-center py-2">
          <div class="text-muted small me-auto">
            Showing {{ totalCount > 0 ? startIndex + 1 : 0 }} to {{ endIndex }} of {{ totalCount }} results
          </div>
          <nav aria-label="Pagination" class="ms-auto">
            <ul class="pagination pagination-sm mb-0 pagination-app">
              <li class="page-item" :class="{ disabled: page === 1 || loading }">
                <button class="page-link" @click="prevPage" :disabled="page === 1 || loading">‹</button>
              </li>
              <li
                class="page-item"
                v-for="n in totalPages"
                :key="n"
                :class="{ active: page === n }"
              >
                <button class="page-link" @click="goPage(n)" :disabled="loading">{{ n }}</button>
              </li>
              <li class="page-item" :class="{ disabled: page === totalPages || loading }">
                <button class="page-link" @click="nextPage" :disabled="page === totalPages || loading">›</button>
              </li>
            </ul>
          </nav>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const isTestingMode = inject('isTestingMode', ref(false));
const showInfo = ref(false);

const fromDate = ref(new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));
const toDate = ref(new Date().toISOString().slice(0, 10));

const deviceOptions = ref([]);
const selectedDeviceId = ref('');

const loading = ref(false);
const errorMessage = ref('');
const entries = ref([]);

const page = ref(1);
const pageSize = ref(20);
const totalCount = computed(() => entries.value.length);
const startIndex = computed(() => (page.value - 1) * pageSize.value);
const endIndex = computed(() => Math.min(startIndex.value + pageSize.value, totalCount.value));
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / pageSize.value)));
const pagedEntries = computed(() =>
  entries.value.slice(startIndex.value, startIndex.value + pageSize.value)
);

const fuelSummary = computed(() => {
  if (!entries.value.length) return null;
  const totalRefillLitres = entries.value.reduce(
    (sum, e) => sum + (e.increaseLitres || 0),
    0
  );
  const totalEvents = entries.value.length;
  const avgRefillLitres = totalEvents ? totalRefillLitres / totalEvents : 0;
  const totalEndPct = entries.value.reduce(
    (sum, e) => sum + (e.endPct || 0),
    0
  );
  const avgEndPct = totalEvents ? totalEndPct / totalEvents : 0;
  const first = entries.value[0];
  const last = entries.value[entries.value.length - 1];
  const periodLabel =
    first.eventDate === last.eventDate
      ? first.eventDate
      : `${first.eventDate} - ${last.eventDate}`;

  return {
    totalRefillLitres,
    totalEvents,
    avgRefillLitres,
    avgEndPct,
    periodLabel,
  };
});

function formatPct(value) {
  const v = Number(value || 0);
  return v.toFixed(1);
}

function formatLitres(pct, capacity) {
  const p = Number(pct || 0);
  const cap = Number(capacity || 0);
  const litres = (p / 100) * cap;
  return litres.toFixed(1);
}

function goPage(n) {
  if (n >= 1 && n <= totalPages.value) page.value = n;
}

function prevPage() {
  if (page.value > 1) page.value -= 1;
}

function nextPage() {
  if (page.value < totalPages.value) page.value += 1;
}

async function loadDeviceOptions() {
  try {
    const res = await axios.get('/web/reports/device-options');
    deviceOptions.value = res.data.options || [];
  } catch (e) {
    console.error('Failed to load devices', e);
  }
}

async function fetchReport() {
  try {
    loading.value = true;
    errorMessage.value = '';
    entries.value = [];

    const params = {
      from_date: fromDate.value,
      to_date: toDate.value,
    };

    if (selectedDeviceId.value) {
      params.device_ids = [selectedDeviceId.value];
    }

    const res = await axios.get('/web/reports/fuel-detailed', { params });
    const data = res.data || {};
    const allEntries = Array.isArray(data.entries) ? data.entries : [];
    entries.value = allEntries.filter((e) => (e.increaseLitres || 0) > 0);
    page.value = 1;
  } catch (e) {
    console.error('Failed to fetch fuel report', e);
    errorMessage.value = 'Failed to fetch fuel report data.';
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await loadDeviceOptions();
});
</script>

<style scoped>
.panel .card-body {
  padding-top: 1rem;
  padding-bottom: 1rem;
}
.card-header h6 {
  font-weight: 600;
}

.fuel-widget {
  display: flex;
  align-items: center;
  border-radius: 0.75rem;
  padding: 0.75rem 0.85rem;
  color: #0f172a;
  background: #f9fafb;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
}

.fuel-widget-primary {
  background: linear-gradient(135deg, #0ea5e9, #0369a1);
  color: #f9fafb;
}

.fuel-widget-emerald {
  background: linear-gradient(135deg, #10b981, #047857);
  color: #ecfdf3;
}

.fuel-widget-amber {
  background: linear-gradient(135deg, #f59e0b, #b45309);
  color: #fffbeb;
}

.fuel-widget-sky {
  background: linear-gradient(135deg, #38bdf8, #0f766e);
  color: #e0f2fe;
}

.fuel-widget-icon {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(15, 23, 42, 0.12);
  margin-right: 0.75rem;
  font-size: 1.1rem;
}

.fuel-widget-body {
  flex: 1;
}

.fuel-widget-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.85;
}

.fuel-widget-value {
  font-size: 1.25rem;
  font-weight: 700;
  line-height: 1.2;
}

.fuel-widget-sub {
  font-size: 0.75rem;
  opacity: 0.92;
}
</style>
