<template>
  <div class="fuel-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
        </li>
        <li class="breadcrumb-item active" aria-current="page">Fuel Management</li>
      </ol>
    </div>

    <!-- Page Title and Actions -->
    <div class="row mb-3">
      <div class="col-sm-12 col-md-12 col-xl-8">
        <h4 class="mb-0 fw-semibold">Fuel Management</h4>
      </div>
      <div class="col-sm-12 col-md-12 col-xl-4 d-flex justify-content-xl-end" v-if="hasPerm('fuel','create')">
        <RouterLink to="/fuel/new" class="btn btn-app-dark"><i class="bi bi-plus-lg me-1"></i> Add Fuel Entry</RouterLink>
      </div>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <!-- Summary Cards -->
    <div class="row g-3 mb-3">
      <div class="col-sm-12 col-md-6" v-for="card in summaryCards" :key="card.title">
        <div class="card border rounded-4 shadow-0 h-100">
          <div class="card-body d-flex align-items-center gap-3 py-3">
            <div class="rounded-3 p-2 bg-light text-primary d-inline-flex align-items-center justify-content-center">
              <i :class="card.icon" class="fs-5"></i>
            </div>
            <div>
              <div class="small text-muted">{{ card.title }}</div>
              <div class="fw-semibold">{{ formatNumber(card.value) }} {{ card.suffix }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-body">
        <div class="fw-semibold mb-2">Search Option</div>
        <div class="row g-2 align-items-end">
          <div class="col-sm-12 col-md-3">
            <label class="form-label small">Vehicle</label>
            <select class="form-select" v-model="filters.device_id">
              <option value="">All Vehicles</option>
              <option v-for="dev in devices" :key="dev.id" :value="dev.id">{{ dev.label }}</option>
            </select>
          </div>
          <div class="col-sm-12 col-md-2">
            <label class="form-label small">Start Date</label>
            <input type="date" class="form-control" v-model="filters.start_date">
          </div>
          <div class="col-sm-12 col-md-2">
            <label class="form-label small">End Date</label>
            <input type="date" class="form-control" v-model="filters.end_date">
          </div>
          <div class="col-sm-12 col-md-3 d-flex align-items-center mb-2">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="showBlocked" v-model="filters.show_blocked">
                <label class="form-check-label small" for="showBlocked">Show Blocked</label>
            </div>
          </div>
          <div class="col-sm-12 col-md-2 d-flex align-items-end">
            <button class="btn btn-primary w-100" @click="fetchData(1)">Filter</button>
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
                <th class="fw-semibold py-2">Date</th>
                <th class="fw-semibold py-2">Vehicle</th>
                <th class="fw-semibold py-2">Quantity (L)</th>
                <th class="fw-semibold py-2">Cost</th>
                <th class="fw-semibold py-2">Odometer</th>
                <th class="fw-semibold py-2">Notes</th>
                <th class="fw-semibold py-2 text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="7" class="text-center py-3">Loading...</td>
              </tr>
              <tr v-else-if="entries.length === 0">
                <td colspan="7" class="text-center text-muted py-3">No fuel entries found</td>
              </tr>
              <tr v-for="entry in entries" :key="entry.id" class="border-bottom">
                <td class="text-muted">{{ $formatDateTime(entry.fill_date) }}</td>
                <td class="text-muted">
                  {{ entry.device ? entry.device.name : 'Unknown' }}
                  <span v-if="entry.deleted_at" class="badge rounded-pill bg-danger ms-2">Blocked</span>
                </td>
                <td class="text-muted">{{ entry.quantity }}</td>
                <td class="text-muted">{{ formatCurrency(entry.cost) }}</td>
                <td class="text-muted">{{ entry.odometer || '-' }}</td>
                <td class="text-muted small text-truncate" style="max-width: 200px;">{{ entry.notes }}</td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <RouterLink :to="`/fuel/${entry.id}/edit`" class="btn btn-outline-secondary" v-if="!entry.deleted_at && hasPerm('fuel','update')" title="Edit"><i class="bi bi-pencil"></i></RouterLink>

                    <button class="btn btn-outline-warning" @click="blockEntry(entry.id)" v-if="!entry.deleted_at && hasPerm('fuel','delete')" title="Block" :disabled="blocking[entry.id]">
                        <span v-if="blocking[entry.id]" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <i v-else class="bi bi-slash-circle"></i>
                    </button>

                    <button class="btn btn-outline-success" @click="activateEntry(entry.id)" v-if="entry.deleted_at && hasPerm('fuel','update')" title="Activate" :disabled="activating[entry.id]">
                        <span v-if="activating[entry.id]" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <i v-else class="bi bi-check-circle"></i>
                    </button>

                    <button class="btn btn-outline-danger" @click="permanentDeleteEntry(entry.id)" v-if="entry.deleted_at && hasPerm('fuel','delete')" title="Permanent Delete" :disabled="deleting[entry.id]">
                        <span v-if="deleting[entry.id]" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        <i v-else class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2" v-if="lastPage > 1">
        <div class="text-muted small me-auto">Showing Page {{ currentPage }} of {{ lastPage }}</div>
        <nav aria-label="Pagination" class="ms-auto">
            <ul class="pagination pagination-sm mb-0 pagination-app">
                <li class="page-item" :class="{ disabled: currentPage <= 1 }"><button class="page-link" @click="changePage(currentPage - 1)">‹</button></li>
                <li class="page-item active"><span class="page-link">{{ currentPage }}</span></li>
                <li class="page-item" :class="{ disabled: currentPage >= lastPage }"><button class="page-link" @click="changePage(currentPage + 1)">›</button></li>
            </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, reactive } from 'vue';
import UiAlert from '../../components/UiAlert.vue';
import axios from 'axios';
import { hasPermission } from '../../auth';
import { useRouter } from 'vue-router';

import Swal from 'sweetalert2';

const router = useRouter();

// State
const loading = ref(false);
const blocking = ref({});
const activating = ref({});
const deleting = ref({});
const error = ref('');
const message = ref('');
const entries = ref([]);
const devices = ref([]);
const summary = ref({ total_cost: 0, total_quantity: 0 });

// Pagination
const currentPage = ref(1);
const lastPage = ref(1);

// Filters
const filters = reactive({
    device_id: '',
    start_date: '',
    end_date: '',
    show_blocked: false
});

// Form
// (Modal form removed in favor of separate pages)

// Permissions
const hasPerm = (module, action) => hasPermission(module, action);

// Helpers
const formatNumber = (num) => Number(num).toLocaleString(undefined, { maximumFractionDigits: 2 });
const formatCurrency = (num) => '$' + Number(num).toFixed(2);


// Summary Cards
const summaryCards = computed(() => [
    { title: 'Total Cost', value: summary.value.total_cost, suffix: '', icon: 'bi-cash' },
    { title: 'Total Volume', value: summary.value.total_quantity, suffix: 'L', icon: 'bi-fuel-pump' },
]);

// Methods
const fetchDevices = async () => {
    try {
        const { data } = await axios.get('/web/fuel/vehicles');
        devices.value = data || [];
    } catch (e) {
        console.error("Failed to load devices", e);
    }
};

const fetchData = async (page = 1) => {
    loading.value = true;
    currentPage.value = page;
    try {
        const params = {
            page,
            ...filters,
            withDeleted: filters.show_blocked ? 1 : 0
        };
        const { data } = await axios.get('/web/fuel', { params });
        entries.value = data.data;
        lastPage.value = data.last_page;

        // Also fetch summary
        const summaryRes = await axios.get('/web/fuel/summary', { params });
        summary.value = summaryRes.data;

    } catch (e) {
        error.value = 'Failed to load data';
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const changePage = (page) => {
    fetchData(page);
};

const blockEntry = async (id) => {
    const result = await Swal.fire({
        title: 'Block entry?',
        text: 'This will hide the entry and mark as blocked.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Block',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#f59f00',
    });
    if (!result.isConfirmed) return;

    blocking.value[id] = true;
    try {
        await axios.delete(`/web/fuel/${id}`);
        await Swal.fire({ title: 'Blocked', text: 'Entry has been blocked.', icon: 'success', timer: 1200, showConfirmButton: false });
        fetchData(currentPage.value);
    } catch (e) {
        await Swal.fire({ title: 'Block failed', text: 'Failed to block entry', icon: 'error' });
    } finally {
        blocking.value[id] = false;
    }
};

const activateEntry = async (id) => {
    const result = await Swal.fire({
        title: 'Activate entry?',
        text: 'This will restore the entry and show it in the list.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Activate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#28a745',
    });
    if (!result.isConfirmed) return;

    activating.value[id] = true;
    try {
        await axios.patch(`/web/fuel/${id}/restore`);
        await Swal.fire({ title: 'Activated', text: 'Entry has been activated.', icon: 'success', timer: 1200, showConfirmButton: false });
        fetchData(currentPage.value);
    } catch (e) {
        await Swal.fire({ title: 'Activate failed', text: 'Failed to activate entry', icon: 'error' });
    } finally {
        activating.value[id] = false;
    }
};

const permanentDeleteEntry = async (id) => {
    const result = await Swal.fire({
        title: 'Permanently delete entry?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d33',
    });
    if (!result.isConfirmed) return;

    deleting.value[id] = true;
    try {
        await axios.delete(`/web/fuel/${id}`, { params: { force: 1 } });
        await Swal.fire({ title: 'Deleted', text: 'Entry has been permanently deleted.', icon: 'success', timer: 1400, showConfirmButton: false });
        fetchData(currentPage.value);
    } catch (e) {
        await Swal.fire({ title: 'Delete failed', text: 'Failed to delete entry', icon: 'error' });
    } finally {
        deleting.value[id] = false;
    }
};

onMounted(() => {
    if (!hasPermission('fuel', 'read')) {
        router.push('/');
        return;
    }
    fetchDevices();
    fetchData();
});
</script>

<style scoped>
.modal-dialog {
    max-width: 600px;
}
</style>
