<template>
  <div class="drivers-view">
    <!-- Breadcrumb using AdminLTE/Bootstrap -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Driver Management</li>
      </ol>
    </div>

    <!-- Page Title and Actions -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="mb-0 fw-semibold">Drivers Directory</h4>
      <div class="d-flex align-items-center gap-1">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input v-model="query" type="text" class="form-control input-w-360" placeholder="Search driver/ID" />
        </div>
        <button class="btn btn-outline-secondary btn-icon-44" title="Filters"><i class="bi bi-sliders2"></i></button>
        <RouterLink to="/drivers/new" class="btn btn-app-dark"><i class="bi bi-plus-lg me-1"></i> New Driver</RouterLink>
      </div>
    </div>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <div v-if="loading" class="text-muted small mb-2">Loading drivers…</div>

    <!-- Table -->
    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
            <thead class="thead-app-dark">
              <tr>
                <th class="fw-semibold py-2">Driver ID</th>
                <th class="fw-semibold py-2">Driver Name</th>
                <th class="fw-semibold py-2">Email Address</th>
                <th class="fw-semibold py-2">Phone Number</th>
                <th class="fw-semibold py-2">Licence Number</th>
                <th class="fw-semibold py-2">License Expiry</th>
                <th class="fw-semibold py-2">Assigned Vehicles</th>
                <th class="fw-semibold py-2">Last Ride</th>
                <th class="fw-semibold py-2 text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in pagedRows" :key="row.id" class="border-bottom">
                <td class="text-muted text-nowrap">{{ row.code }}</td>
                <td class="text-nowrap">
                  <div class="d-flex align-items-center gap-2">
                    <img v-if="row.avatarUrl" :src="row.avatarUrl" alt="Avatar" class="rounded-circle" style="width:32px;height:32px;object-fit:cover;" />
                    <i v-else class="bi bi-person-circle fs-5 text-muted"></i>
                    <span>{{ row.name }}</span>
                  </div>
                </td>
                <td class="text-muted text-nowrap">{{ row.email }}</td>
                <td class="text-muted text-nowrap">{{ row.phone }}</td>
                <td class="text-muted text-nowrap">{{ row.licence }}</td>
                <td class="text-muted text-nowrap">{{ row.expiry }}</td>
                <td class="text-nowrap">
                  <span class="me-2">{{ row.vehicle }}</span>
                  <span class="badge bg-success rounded-pill badge-app">{{ row.status }}</span>
                </td>
                <td class="text-muted text-nowrap">{{ row.lastRide }}</td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" title="Edit" @click="toEdit(row)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-outline-danger" title="Delete" @click="deleteDriver(row.id, row.name)" :disabled="deleting[row.id] === true">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="pagedRows.length === 0 && !loading">
                <td colspan="9" class="text-center text-muted py-3">No drivers found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">Showing {{ startIndex + 1 }} to {{ Math.min(startIndex + pageSize, totalCount) }} of {{ totalCount }} results</div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item" :class="{ disabled: page === 1 }"><button class="page-link" @click="prevPage">‹</button></li>
            <li class="page-item" v-for="n in totalPages" :key="n" :class="{ active: page === n }"><button class="page-link" @click="goPage(n)">{{ n }}</button></li>
            <li class="page-item" :class="{ disabled: page === totalPages }"><button class="page-link" @click="nextPage">›</button></li>
          </ul>
        </nav>
      </div>
    </div>
  </div>

</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const router = useRouter();
const query = ref('');
const page = ref(1);
const pageSize = 16;
const loading = ref(false);
const error = ref('');
const rows = ref([]);
const deleting = ref({});

function formatDriver(d) {
  const attrs = d?.attributes || {};
  return {
    id: d?.id ?? Math.random(),
    code: d?.uniqueId || (d?.id ? `DRV-${d.id}` : '-'),
    name: d?.name || '-',
    email: attrs?.email || '-',
    phone: attrs?.phone || '-',
    licence: attrs?.licence || attrs?.license || '-',
    expiry: attrs?.licenseExpiry || '-',
    vehicle: d?.deviceName || d?.deviceUniqueId || attrs?.assignedVehicle || '-',
    status: (d?.status || attrs?.status || 'Active'),
    lastRide: attrs?.lastRide || '-',
    avatarUrl: d?.avatarImageUrl || (attrs?.avatarImage ? `/storage/${attrs.avatarImage}` : ''),
  };
}

async function fetchDrivers() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get('/web/drivers');
    const list = Array.isArray(data?.drivers) ? data.drivers : (Array.isArray(data) ? data : []);
    rows.value = list.map(formatDriver);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load drivers';
  } finally {
    loading.value = false;
  }
}

async function deleteDriver(id, name) {
  const result = await Swal.fire({
    title: `Delete driver ${name || id}?`,
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
    await axios.delete(`/web/drivers/${id}`);
    rows.value = rows.value.filter(r => r.id !== id);
    await Swal.fire({
      title: 'Deleted',
      text: 'Driver has been deleted.',
      icon: 'success',
      timer: 1400,
      showConfirmButton: false,
    });
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to delete driver';
    await Swal.fire({
      title: 'Delete failed',
      text: error.value,
      icon: 'error',
    });
  } finally {
    deleting.value[id] = false;
  }
}

onMounted(fetchDrivers);

const filtered = computed(() => {
  if (!query.value) return rows.value;
  const q = query.value.toLowerCase();
  return rows.value.filter(r =>
    (r.code || '').toLowerCase().includes(q) ||
    (r.name || '').toLowerCase().includes(q) ||
    (r.email || '').toLowerCase().includes(q)
  );
});

const totalCount = computed(() => filtered.value.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalCount.value / pageSize)));
const startIndex = computed(() => (page.value - 1) * pageSize);
const pagedRows = computed(() => filtered.value.slice(startIndex.value, startIndex.value + pageSize));

function goPage(n) { page.value = n; }
function prevPage() { if (page.value > 1) page.value--; }
function nextPage() { if (page.value < totalPages.value) page.value++; }

function toEdit(row) {
  if (!row?.id) return;
  router.push(`/drivers/${row.id}/edit`);
}
</script>
