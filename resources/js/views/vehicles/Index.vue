<template>
    <div class="vehicles-view">
        <!-- Breadcrumb -->
        <div class="app-content-header mb-2">
            <ol class="breadcrumb mb-0 small text-muted">
                <li class="breadcrumb-item">
                    <RouterLink to="/dashboard">Dashboard</RouterLink>
                </li>
                <li class="breadcrumb-item active" aria-current="page">Vehicle Management</li>
            </ol>
        </div>
        <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="dismissError" />
        <!-- Page Title and Actions -->
        <div class="row mb-3">
            <div class="col-sm-12 col-md-12 col-xl-8">
                <h4 class="mb-0 fw-semibold">Vehicles Management</h4>
            </div>
            <div class="col-sm-12 col-md-12 col-xl-4">
                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-7 ml-auto">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input v-model="query" type="text" class="form-control input-w-360"
                                placeholder="Search vehicle/ID" />
                            <span class="input-group-text"><i class="bi bi-sliders2"></i></span>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-5 ml-auto" v-if="hasPerm('vehicles','create')">
                        <RouterLink to="/vehicles/new" class="btn btn-app-dark"><i class="bi bi-plus-lg me-1"></i> List New Vehicle</RouterLink>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="card border rounded-3 shadow-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
                        <thead class="thead-app-dark">
                            <tr>
                                <th class="fw-semibold py-2">Vehicle ID</th>
                                <th class="fw-semibold py-2">Vehicles Name</th>
                                <th class="fw-semibold py-2">VIN Number</th>
                                <th class="fw-semibold py-2">Plate number</th>
                                <th class="fw-semibold py-2">Odometer</th>
                                <th class="fw-semibold py-2">Ignition</th>
                                <th class="fw-semibold py-2">Speed (Km/h)</th>
                                <th class="fw-semibold py-2">Fuel Level</th>
                                <th class="fw-semibold py-2 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in pagedRows" :key="row.device_id" class="border-bottom">
                                <td class="text-muted text-nowrap">{{ row.uniqueid ?? '—' }}</td>
                                <td class="text-muted text-nowrap">
                                    {{ row.name ?? '—' }}
                                    <span v-if="row.blocked" class="badge rounded-pill bg-danger ms-2">Blocked</span>
                                </td>
                                <td class="text-muted text-nowrap">{{ row.vin ?? '—' }}</td>
                                <td class="text-muted text-nowrap">{{ row.plate ?? '—' }}</td>
                                <td class="text-muted text-nowrap">{{ row.odometer ?? '—' }}</td>
                                <td class="text-nowrap">
                                    <span :class="['status-badge', ignitionClass(row.ignition)]">
                                        <span class="dot"></span>
                                        {{ row.ignition ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-nowrap">
                                    <span :class="['status-badge', speedClass(row.speed)]">
                                        <span class="dot"></span>
                                        {{ row.speed ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-muted text-nowrap">{{ row.fuel ?? '—' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button v-if="!row.blocked && hasPerm('vehicles','update')" class="btn btn-outline-secondary" title="Edit" @click="toEdit(row)"><i
                                                class="bi bi-pencil"></i></button>
                                        <button v-if="(hasPerm('vehicles','read') || hasPerm('vehicles.overview','read')) && !row.blocked && hasLocation(row)" class="btn btn-outline-primary" title="View" @click="toDetail(row)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button v-if="!row.blocked && hasPerm('vehicles','update')" class="btn btn-outline-warning" title="Block" @click="block(row)"
                                            :disabled="blocking[row.device_id] === true">
                                            <i class="bi bi-slash-circle"></i>
                                        </button>
                                        <button v-if="row.blocked && hasPerm('vehicles','update')" class="btn btn-outline-success" title="Activate" @click="activate(row)"
                                            :disabled="activating[row.device_id] === true">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                        <button v-if="row.blocked && hasPerm('vehicles','delete')" class="btn btn-outline-danger" title="Permanent Delete" @click="permanentRemove(row)"
                                            :disabled="deleting[row.device_id] === true">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="pagedRows.length === 0 && !loading">
                                <td colspan="9" class="text-center text-muted py-3">No vehicles found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex align-items-center py-2">
                <div class="text-muted small me-auto">Showing {{ startIndex + 1 }} to {{ Math.min(startIndex + pageSize,
                    totalCount) }} of {{ totalCount }} results</div>
                <nav aria-label="Pagination" class="ms-auto">
                    <ul class="pagination pagination-sm mb-0 pagination-app">
                        <li class="page-item" :class="{ disabled: page === 1 }"><button class="page-link"
                                @click="prevPage">‹</button></li>
                        <li class="page-item" v-for="n in totalPages" :key="n" :class="{ active: page === n }"><button
                                class="page-link" @click="goPage(n)">{{ n }}</button></li>
                        <li class="page-item" :class="{ disabled: page === totalPages }"><button class="page-link"
                                @click="nextPage">›</button></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useRouter, useRoute } from 'vue-router';
import Swal from 'sweetalert2';
import UiAlert from '../../components/UiAlert.vue';
import { formatTelemetry } from '../../utils/telemetry';
import { hasPermission as _hasPermission } from '../../auth';

const router = useRouter();
const route = useRoute();
const query = ref('');
const page = ref(1);
const pageSize = ref(25);
const loading = ref(false);
const error = ref('');
const rows = ref([]);
const deleting = ref({});
const blocking = ref({});
const activating = ref({});
const meta = ref({ total: 0, current_page: 1, per_page: 25 });

// hide device detail link in production
const showDeviceDetailLink = !import.meta.env.PROD;

const hasPerm = (k, a) => _hasPermission(k, a);

async function fetchPage(n = 1) {
    loading.value = true;
    try {
        const { data } = await axios.get('/web/vehicles', { params: { page: n } });
        const list = Array.isArray(data) ? data : (data.data ?? []);
        rows.value = list;
        console.log('res row ',rows.value);
        meta.value = {
            total: data.total ?? (data.meta?.total ?? list.length),
            current_page: data.current_page ?? (data.meta?.current_page ?? n),
            per_page: data.per_page ?? (data.meta?.per_page ?? 25)
        };
        page.value = meta.value.current_page;
        pageSize.value = meta.value.per_page;
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to load vehicles.';
        rows.value = [];
        meta.value = { total: 0, current_page: 1, per_page: 25 };
    } finally {
        loading.value = false;
    }
}

function refresh() { fetchPage(page.value); }

function dismissError() { error.value = ''; }

onMounted(() => {
    const errParam = route.query?.error;
    if (errParam) {
        error.value = Array.isArray(errParam) ? String(errParam[0]) : String(errParam);
        // Clear query to prevent persistent banner on later navigations
        router.replace('/vehicles');
    }
    fetchPage(1);
});

// Hydrate rows with tc_device attributes to match table headers
function parseAttrs(attrs) {
    try { return typeof attrs === 'string' ? JSON.parse(attrs) : (attrs || {}); } catch { return {}; }
}
function pickAttr(attrs, keys) {
    for (const k of keys) {
        const val = attrs?.[k];
        if (val !== undefined && val !== null && val !== '') return val;
    }
    return null;
}
// Helper to pick attribute value and also know which key matched
function pickAttrWithKey(attrs, keys) {
    for (const k of keys) {
        const val = attrs?.[k];
        if (val !== undefined && val !== null && val !== '') return { key: k, value: val };
    }
    return { key: null, value: null };
}
// Extract numeric value from a string or number (handles commas and unit suffixes)
function extractNumber(raw) {
    if (raw == null) return NaN;
    if (typeof raw === 'number') return raw;
    const s = String(raw);
    const match = s.replace(/,/g, '').match(/-?\d+(?:\.\d+)?/);
    return match ? Number(match[0]) : NaN;
}
function deriveRow(r) {
    const tc = r?.tc_device ?? r?.tcDevice ?? {};
    const attrs = parseAttrs(tc.attributes);
    const pos = tc.position || {};
    const posAttrs = parseAttrs(pos.attributes);
    const uniqueid = r.uniqueid ?? r.uniqueId ?? tc.uniqueid ?? tc.uniqueId ?? pickAttr(attrs, ['uniqueId', 'uniqueid']);
    const name = r.name ?? tc.name ?? pickAttr(attrs, ['name']);
    const vin = r.vin ?? pickAttr(attrs, ['vin', 'VIN']);
    const plate = r.plate ?? pickAttr(attrs, ['plate', 'licensePlate', 'registration', 'regNumber']);
    const model = r.model ?? tc.model ?? pickAttr(attrs, ['model']);
    const capacity = attrs.fuelTankCapacity ?? attrs.FuelTankCapacity ?? attrs.fueltankcapacity ?? null;
    // Odometer via shared telemetry formatter (merge device + position attrs for consistency)
    const mergedAttrs = { ...attrs, ...posAttrs };
    const tel = formatTelemetry(mergedAttrs, { protocol: pos.protocol, model, capacity, preferNamedOdometer: true });
    const odometer = tel?.odometer?.display ?? null;
    // ignition: prefer tc.position.attributes.ignition, fallback to tc_device.attributes
    const ignRaw = posAttrs.ignition ?? (r.ignition ?? pickAttr(attrs, ['ignition', 'Ignition']));
    const ignition = ignRaw === true || ignRaw === 1 || String(ignRaw).toLowerCase() === 'on'
        ? 'on'
        : (ignRaw === false || ignRaw === 0 || String(ignRaw).toLowerCase() === 'off' ? 'off' : null);

    // speed: prefer tc.position.speed (knots -> km/h), fallback to attributes
    const speedAttr = pickAttr(attrs, ['speedKmh', 'speed_kmh', 'speedKmH', 'speed', 'speedKMH']);
    const speedVal = (typeof pos.speed === 'number' ? Math.round(pos.speed * 1.852) : pos.speed) ?? r.speed ?? speedAttr;
    let speed = null;
    if (speedVal != null) {
        if (typeof speedVal === 'string' && /km\/h/i.test(speedVal)) {
            speed = speedVal;
        } else {
            const n = Number(speedVal);
            speed = Number.isFinite(n) ? `${Math.round(n)} km/h` : String(speedVal);
        }
    }

    // location: prefer tc.position.address; fallback to attributes; then coords if available
    let coords = null;
    if (typeof pos.latitude === 'number' && typeof pos.longitude === 'number') {
        coords = `${pos.latitude.toFixed(5)}, ${pos.longitude.toFixed(5)}`;
    } else if (typeof pos.lat === 'number' && typeof pos.lon === 'number') {
        coords = `${pos.lat.toFixed(5)}, ${pos.lon.toFixed(5)}`;
    }
    const location = pos.address ?? (r.location ?? pickAttr(attrs, ['address', 'location'])) ?? coords;
    let fuel = null;
    if (tel?.fuel) {
        const liters = tel.fuel.liters;
        const percent = tel.fuel.percent;
        if (liters != null && percent != null) fuel = `${liters} L (${percent}%)`;
        else if (liters != null) fuel = `${liters} L`;
        else if (percent != null) fuel = `${percent}%`;
    }

    const blocked = !!(r?.deleted_at || r?.deletedAt || r?.blocked);
    return { ...r, uniqueid, name, vin, plate, model, odometer, ignition, speed, location, fuel, blocked };
}

async function block(row) {
    if (!row?.device_id && !row?.deviceId) return;
    const id = row.device_id || row.deviceId;
    const result = await Swal.fire({
        title: `Block vehicle ${row.name || id}?`,
        text: 'This will hide it and mark as blocked.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Block',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#f59f00',
    });
    if (!result.isConfirmed) return;
    blocking.value[id] = true;
    error.value = '';
    try {
        await axios.delete(`/web/vehicles/${id}`);
        await Swal.fire({ title: 'Blocked', text: 'Vehicle has been blocked.', icon: 'success', timer: 1200, showConfirmButton: false });
        refresh();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to block vehicle.';
        await Swal.fire({ title: 'Block failed', text: error.value, icon: 'error' });
    } finally {
        blocking.value[id] = false;
    }
}

async function permanentRemove(row) {
    if (!row?.device_id && !row?.deviceId) return;
    const id = row.device_id || row.deviceId;
    const result = await Swal.fire({
        title: `Permanently delete ${row.name || id}?`,
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
    });
    if (!result.isConfirmed) return;
    deleting.value[id] = true;
    error.value = '';
    try {
        await axios.delete(`/web/vehicles/${id}`, { params: { force: 1 } });
        rows.value = rows.value.filter(r => (r.device_id || r.deviceId) !== id);
        await Swal.fire({ title: 'Deleted', text: 'Vehicle has been permanently deleted.', icon: 'success', timer: 1400, showConfirmButton: false });
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to delete vehicle.';
        await Swal.fire({ title: 'Delete failed', text: error.value, icon: 'error' });
    } finally {
        deleting.value[id] = false;
    }
}

async function activate(row) {
    if (!row?.device_id && !row?.deviceId) return;
    const id = row.device_id || row.deviceId;
    const result = await Swal.fire({
        title: `Activate vehicle ${row.name || id}?`,
        text: 'This will restore the vehicle and show it in the list.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Activate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745',
    });
    if (!result.isConfirmed) return;
    activating.value[id] = true;
    error.value = '';
    try {
        await axios.patch(`/web/vehicles/${id}/restore`);
        await Swal.fire({ title: 'Activated', text: 'Vehicle has been activated.', icon: 'success', timer: 1200, showConfirmButton: false });
        refresh();
    } catch (e) {
        error.value = e?.response?.data?.message || 'Failed to activate vehicle.';
        await Swal.fire({ title: 'Activate failed', text: error.value, icon: 'error' });
    } finally {
        activating.value[id] = false;
    }
}

const rowsHydrated = computed(() => rows.value.map(r => deriveRow(r)));

const filtered = computed(() => {
    const base = rowsHydrated.value;
    if (!query.value) return base;
    const q = String(query.value).toLowerCase();
    return base.filter(r => String(r.device_id).toLowerCase().includes(q)
        || String(r.uniqueid || '').toLowerCase().includes(q)
        || String(r.name || '').toLowerCase().includes(q)
        || String(r.vin || '').toLowerCase().includes(q)
        || String(r.plate || '').toLowerCase().includes(q));
});

const totalCount = computed(() => meta.value.total || filtered.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / meta.value.per_page)));
const startIndex = computed(() => (page.value - 1) * meta.value.per_page);
const pagedRows = computed(() => filtered.value.slice(startIndex.value, startIndex.value + meta.value.per_page));

console.log('pagedRows ',pagedRows);
function ignitionClass(val) {
    const v = String(val || '').toLowerCase();
    if (v === 'on') return 'is-on';
    if (v === 'off') return 'is-off';
    return 'is-unknown';
}

function speedClass(val) {
    const match = String(val || '').match(/(\d+)/);
    const s = match ? parseInt(match[1], 10) : NaN;
    if (!Number.isFinite(s)) return 'is-unknown';
    if (s === 0) return 'is-off';
    if (s > 100) return 'is-critical';
    if (s >= 80) return 'is-high';
    if (s >= 40) return 'is-medium';
    return 'is-low';
}

// Format tc_device.attributes (string or object) into key: value pairs
function formatTcAttributes(attrs) {
    if (attrs == null) return '—';
    try {
        const obj = typeof attrs === 'string' ? JSON.parse(attrs) : attrs;
        if (obj && typeof obj === 'object' && !Array.isArray(obj)) {
            const entries = Object.entries(obj);
            if (entries.length === 0) return '—';
            return entries.map(([k, v]) => `${k}: ${stringifyAttr(v)}`).join(', ');
        }
        return String(attrs);
    } catch {
        return String(attrs);
    }
}

function stringifyAttr(v) {
    if (v === null || v === undefined) return '—';
    if (typeof v === 'object') return JSON.stringify(v);
    if (typeof v === 'boolean') return v ? 'true' : 'false';
    return String(v);
}

function goPage(n) { if (n >= 1 && n <= totalPages.value) { page.value = n; fetchPage(n); } }
function prevPage() { goPage(page.value - 1); }
function nextPage() { goPage(page.value + 1); }

// Show "View" only for vehicles that have a known position
function hasLocation(row) {
    const tc = row?.tc_device ?? row?.tcDevice ?? {};
    const pos = tc?.position || {};
    const toNum = (v) => {
        const n = typeof v === 'string' ? parseFloat(v) : v;
        return Number.isFinite(n) ? n : null;
    };
    const lat = toNum(pos.latitude ?? pos.lat ?? null);
    const lon = toNum(pos.longitude ?? pos.lon ?? null);
    if (typeof lat === 'number' && typeof lon === 'number') return true;
    const pid = row?.positionId ?? row?.positionid ?? tc?.positionId ?? tc?.positionid ?? null;
    return pid != null;
}

async function remove(row) {
    if (!row?.device_id) return;
    const result = await Swal.fire({
        title: `Delete vehicle ${row.device_id}?`,
        text: 'This will permanently remove this vehicle from the system.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
    });
    if (!result.isConfirmed) return;
    deleting.value[row.device_id] = true;
    try {
        await axios.delete(`/web/vehicles/${row.device_id}`);
        await Swal.fire({
            title: 'Deleted',
            text: 'Vehicle deleted successfully.',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false,
        });
        await fetchPage(page.value);
    } catch (e) {
        const serverMessage = e?.response?.data?.message || '';
        const safeMessage = /traccar/i.test(serverMessage) ? 'Failed to delete vehicle.' : (serverMessage || 'Failed to delete vehicle.');
        await Swal.fire({
            title: 'Delete failed',
            text: safeMessage,
            icon: 'error',
        });
    } finally {
        deleting.value[row.device_id] = false;
    }
}

function toEdit(row) {
    if (!row?.device_id) return;
    router.push(`/vehicles/${row.device_id}/edit`);
}

function toDetail(row) {
    if (!row?.device_id) return;
    router.push(`/vehicles/${row.device_id}`);
}
</script>
