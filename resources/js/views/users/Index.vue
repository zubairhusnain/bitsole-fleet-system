<template>
  <div class="users-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">User Management</li>
      </ol>
    </div>

    <!-- Page Title and Actions -->

    <div class="row mb-3">
      <div class="col-sm-12 col-md-12 col-xl-8"><h4 class="mb-0 fw-semibold">Users Directory</h4></div>
      <div class="col-sm-12 col-md-12 col-xl-4">
        <div class="row">
            <div class="col-sm-12 col-md-6 col-xl-7">
                <div class="input-group flex-nowrap">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input v-model="query" type="text" class="form-control input-w-360" placeholder="Search name/email" />
                <span class="input-group-text"><i class="bi bi-sliders2"></i></span>
                </div>
            </div>
            <div class="col-sm-12 col-md-6 col-xl-5">
                    <RouterLink to="/users/new" class="btn btn-app-dark"><i class="bi bi-plus-lg me-1"></i> New User</RouterLink>
            </div>
        </div>
      </div>
    </div>
    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <div v-if="loading" class="text-muted small mb-2">Loading users…</div>

    <!-- Table -->
    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
            <thead class="thead-app-dark">
              <tr>
                <th class="fw-semibold py-2">ID</th>
                <th class="fw-semibold py-2">Name</th>
                <th class="fw-semibold py-2">Email</th>
                <th class="fw-semibold py-2">Role</th>
                <th class="fw-semibold py-2">Distributor</th>
                <th class="fw-semibold py-2">Created</th>
                <th class="fw-semibold py-2 text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in pagedRows" :key="row.id" class="border-bottom">
                <td class="text-muted text-nowrap">{{ row.id }}</td>
                <td class="text-nowrap">{{ row.name }}</td>
                <td class="text-muted text-nowrap">{{ row.email }}</td>
                <td class="text-nowrap"><span class="badge rounded-pill badge-app bg-secondary">{{ roleLabel(row.role) }}</span></td>
                <td class="text-muted text-nowrap">{{ row.distributor_id || '-' }}</td>
                <td class="text-muted text-nowrap">{{ row.created_at || '-' }}</td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" title="Edit" @click="toEdit(row)" :disabled="!canEdit(row)"><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-outline-danger" title="Delete" @click="deleteUser(row.id, row.name)" :disabled="!canDelete || deleting[row.id] === true">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="pagedRows.length === 0 && !loading">
                <td colspan="7" class="text-center text-muted py-3">No users found.</td>
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
import { authState } from '../../auth';

const router = useRouter();
const query = ref('');
const page = ref(1);
const pageSize = 16;
const loading = ref(false);
const error = ref('');
const rows = ref([]);
const deleting = ref({});

const currentRole = computed(() => (authState?.user?.role ?? 3));
const canCreate = computed(() => currentRole.value === 3 || currentRole.value === 2); // admin or distributor
const canDelete = computed(() => currentRole.value === 3); // admin
function canEdit(row) {
  if (currentRole.value === 3 || currentRole.value === 2) return true; // admin/distributor
  return authState?.user?.id === row?.id; // self-edit
}
function roleLabel(role) {
  switch (Number(role)) {
    case 3: return 'admin';
    case 2: return 'distributor';
    case 1: return 'fleet_manager';
    default: return 'user';
  }
}

async function fetchUsers() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get('/web/users', { params: { q: query.value } });
    const list = Array.isArray(data?.users) ? data.users : [];
    rows.value = list;
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load users';
  } finally {
    loading.value = false;
  }
}

async function deleteUser(id, name) {
  if (!canDelete.value) return;
  const result = await Swal.fire({
    title: `Delete user ${name || id}?`,
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
    await axios.delete(`/web/users/${id}`);
    rows.value = rows.value.filter(r => r.id !== id);
    await Swal.fire({ title: 'Deleted', text: 'User has been deleted.', icon: 'success', timer: 1400, showConfirmButton: false });
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to delete user';
    await Swal.fire({ title: 'Delete failed', text: error.value, icon: 'error' });
  } finally {
    deleting.value[id] = false;
  }
}

onMounted(fetchUsers);

const filtered = computed(() => {
  if (!query.value) return rows.value;
  const q = query.value.toLowerCase();
  return rows.value.filter(r => (r.name || '').toLowerCase().includes(q) || (r.email || '').toLowerCase().includes(q));
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
  router.push(`/users/${row.id}/edit`);
}
</script>

<style scoped>
.input-w-360 { max-width: 360px; width: 100%; }
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>
