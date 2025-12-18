<template>
  <div class="telemetry-decode-view">
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/vehicles">Vehicle Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Telemetry Decode</li>
      </ol>
    </div>
    <h4 class="mb-3 fw-semibold">Real-time Telemetry</h4>
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">Select Vehicle</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-6">
            <label class="form-label small">Vehicle</label>
            <select class="form-select" v-model.number="selectedDeviceId" @change="onSelectDevice">
              <option :value="0" disabled>Select a vehicle…</option>
              <option v-for="v in vehicleOptions" :key="v.id" :value="v.id">{{ v.label }}</option>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Window (hours)</label>
            <input type="number" min="1" class="form-control" v-model.number="hours" />
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small">Limit</label>
            <input type="number" min="1" class="form-control" v-model.number="limit" />
          </div>
        </div>
      </div>
    </div>
    <div class="card mb-3" v-if="selectedDeviceId">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Latest Values</h6>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary" @click="reload" :disabled="loading">Reload</button>
          <span class="text-muted small" v-if="lastUpdated">Updated {{ lastUpdated }}</span>
        </div>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-xl-6">
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <tbody>
                  <tr v-for="row in latestStandard" :key="row.k">
                    <th class="text-muted w-25">{{ row.k }}</th>
                    <td>{{ row.v }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-12 col-xl-6">
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead>
                  <tr><th>IO ID</th><th>Value</th></tr>
                </thead>
                <tbody>
                  <tr v-for="row in latestIO" :key="row.id">
                    <td class="text-muted">{{ row.id }}</td>
                    <td>{{ row.value }}</td>
                  </tr>
                  <tr v-if="latestIO.length === 0"><td colspan="2" class="text-muted">No IO values</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="card" v-if="selectedDeviceId">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Recent Samples</h6>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="autoRefresh" v-model="autoRefresh" />
          <label class="form-check-label" for="autoRefresh">Auto refresh</label>
        </div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr><th>Time</th><th>Lat</th><th>Lon</th><th>Speed</th><th>Angle</th><th>Sat</th></tr>
            </thead>
            <tbody>
              <tr v-for="p in positions" :key="p.id">
                <td class="text-muted">{{ p.time }}</td>
                <td>{{ p.lat }}</td>
                <td>{{ p.lon }}</td>
                <td>{{ p.speed }}</td>
                <td>{{ p.course }}</td>
                <td>{{ p.sat }}</td>
              </tr>
              <tr v-if="positions.length === 0"><td colspan="6" class="text-muted">No data</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  </template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';
import { parseCodec8Extended } from '../../utils/codec8e';
import { formatDateTime } from '../../utils/datetime';

const vehicleOptions = ref([]);
const selectedDeviceId = ref(0);
const loading = ref(false);
const error = ref('');
const hours = ref(1);
const limit = ref(20);
const positions = ref([]);
const lastUpdated = ref('');
const autoRefresh = ref(true);
let timer = null;

function setTimer() {
  clearTimer();
  if (autoRefresh.value) {
    timer = setInterval(reload, 5000);
  }
}
function clearTimer() { if (timer) { clearInterval(timer); timer = null; } }

async function loadVehicles() {
  error.value = '';
  try {
    const { data } = await axios.get('/web/vehicles/options');
    vehicleOptions.value = (data?.options || []).map(o => ({ id: Number(o.id), label: o.label || (o.name || ('#' + o.id)) }));
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load vehicles';
  }
}

function onSelectDevice() { reloadPositionsRaw(); reload(); setTimer(); }

function fmtTime(s) { return formatDateTime(s); }

function standardFromPosition(pos) {
  const a = pos.attributes || {};
  return [
    { k: 'Time', v: fmtTime(pos.deviceTime || pos.serverTime || pos.fixTime) },
    { k: 'Latitude', v: Number(pos.latitude ?? 0) },
    { k: 'Longitude', v: Number(pos.longitude ?? 0) },
    { k: 'Speed', v: Number(pos.speed ?? 0) },
    { k: 'Course', v: Number(pos.course ?? 0) },
    { k: 'Altitude', v: Number(pos.altitude ?? 0) },
    { k: 'Satellites', v: Number(a.sat ?? a.satellites ?? 0) },
    { k: 'Ignition', v: a.ignition ?? a.IGN ?? a.io200 ?? null },
    { k: 'External Power', v: a.power ?? a.extPower ?? a.io66 ?? null },
    { k: 'Battery', v: a.battery ?? a.io67 ?? null },
  ];
}

function ioFromAttributes(attrs) {
  const out = [];
  for (const [k, v] of Object.entries(attrs || {})) {
    if (k.startsWith('io')) {
      const id = Number(k.replace('io', ''));
      out.push({ id, value: v });
    }
  }
  return out.sort((a, b) => a.id - b.id);
}

async function reload() {
  if (!selectedDeviceId.value) return;
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(`/web/vehicles/${selectedDeviceId.value}/positions`, { params: { hours: hours.value, limit: limit.value } });
    const list = Array.isArray(data?.positions) ? data.positions : [];
    positions.value = list.map(p => ({ id: p.id || `${p.deviceId}_${p.fixTime || p.deviceTime || p.serverTime}`, time: fmtTime(p.deviceTime || p.serverTime || p.fixTime), lat: p.latitude, lon: p.longitude, speed: p.speed, course: p.course, sat: (p.attributes?.sat ?? p.attributes?.satellites ?? null) }));
    positionsRaw.value = list;
    lastUpdated.value = fmtTime(new Date().toISOString());
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load telemetry';
  } finally { loading.value = false; }
}

const positionsRaw = ref([]);
const latest = computed(() => positions.value.length ? positions.value[positions.value.length - 1] : null);
const latestAttrs = computed(() => {
  const raw = latest.value;
  return raw ? (raw.attributes || {}) : {};
});
const latestStandard = computed(() => {
  const p = positions.value.length ? positions.value[positions.value.length - 1] : null;
  if (!p) return [];
  const pos = (positionsRaw.value.length ? positionsRaw.value[positionsRaw.value.length - 1] : null) || {};
  return standardFromPosition(pos);
});
const latestIO = computed(() => {
  const pos = positionsRaw.value.length ? positionsRaw.value[positionsRaw.value.length - 1] : null;
  const attrs = pos?.attributes || {};
  return ioFromAttributes(attrs);
});

onMounted(async () => {
  await loadVehicles();
  if (vehicleOptions.value.length) {
    selectedDeviceId.value = Number(vehicleOptions.value[0].id);
    await reloadPositionsRaw();
    await reload();
    setTimer();
  }
});

onBeforeUnmount(() => { clearTimer(); });

async function reloadPositionsRaw() {
  if (!selectedDeviceId.value) return;
  try {
    const { data } = await axios.get(`/web/vehicles/${selectedDeviceId.value}/positions`, { params: { hours: hours.value, limit: limit.value } });
    positionsRaw.value = Array.isArray(data?.positions) ? data.positions : [];
  } catch {}
}
</script>

<style scoped>
.table-sm th { width: 160px; }
</style>
