<template>
  <div class="users-view" v-if="canAccessUsers">
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
              <div class="d-flex align-items-center justify-content-xl-end gap-2">
                <RouterLink to="/users/new" class="btn btn-app-dark"><i class="bi bi-plus-lg me-1"></i> New User</RouterLink>
              </div>
            </div>
        </div>
      </div>
    </div>
    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!alertMsg" :message="alertMsg" variant="warning" dismissible @dismiss="alertMsg = ''" />
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
                <th class="fw-semibold py-2">Phone</th>
                <th class="fw-semibold py-2 text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in pagedRows" :key="row.id" class="border-bottom">
                <td class="text-muted text-nowrap">{{ row.id }}</td>
                <td class="text-nowrap">{{ row.name }} <span v-if="row.blocked" class="badge rounded-pill bg-danger ms-2">Blocked</span></td>
                <td class="text-muted text-nowrap">{{ row.email }}</td>
                <td class="text-nowrap"><span class="badge rounded-pill badge-app bg-secondary">{{ roleLabel(row.role) }}</span></td>
                <td class="text-muted text-nowrap">{{ row.distributorName || '-' }}</td>
                <td class="text-muted text-nowrap">{{ row.phone || '-' }}</td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <button v-if="!row.blocked" class="btn btn-outline-secondary" title="Edit" @click="toEdit(row)" :disabled="!canEdit(row)"><i class="bi bi-pencil"></i></button>
                    <button v-if="!row.blocked" class="btn btn-outline-warning" title="Block" @click="blockUser(row.id, row.name)" :disabled="!canDelete || blocking[row.id] === true"><i class="bi bi-slash-circle"></i></button>
                    <button v-if="row.blocked" class="btn btn-outline-success" title="Activate" @click="activateUser(row.id, row.name)" :disabled="!canDelete || activating[row.id] === true"><i class="bi bi-check-circle"></i></button>
                    <button v-if="row.blocked" class="btn btn-outline-danger" title="Permanent Delete" @click="deleteUserPermanent(row.id, row.name)" :disabled="!canDelete || deleting[row.id] === true"><i class="bi bi-trash"></i></button>
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
  <div v-else class="users-view">
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">User Management</li>
      </ol>
    </div>
    <UiAlert :show="true" message="You are not authorized to access User Management." variant="warning" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';
import Swal from 'sweetalert2';
import { useRouter, useRoute } from 'vue-router';
import { authState } from '../../auth';

const router = useRouter();
const route = useRoute();
const query = ref('');
const page = ref(1);
const pageSize = 16;
const loading = ref(false);
const error = ref('');
const alertMsg = ref('');
const rows = ref([]);
const deleting = ref({});
const blocking = ref({});
const activating = ref({});

const currentRole = computed(() => (authState?.user?.role ?? 3));
const canAccessUsers = computed(() => currentRole.value === 3 || currentRole.value === 2 || currentRole.value === 1);
const canCreate = computed(() => currentRole.value === 3 || currentRole.value === 2 || currentRole.value === 1); // admin, distributor, fleet manager
const canDelete = computed(() => currentRole.value === 3 || currentRole.value === 2 || currentRole.value === 1); // admin, distributor, fleet manager
function canEdit(row) {
  // Admin/distributor can edit anyone
  if (currentRole.value === 3 || currentRole.value === 2) return true;
  // Fleet manager can edit normal users they manage
  if (currentRole.value === 1) {
    return Number(row?.role) === 0 && Number(row?.manager_id) === Number(authState?.user?.id);
  }
  // Otherwise, allow self-edit
  return Number(authState?.user?.id) === Number(row?.id);
}
function roleLabel(role) {
  switch (Number(role)) {
    case 3: return 'admin';
    case 2: return 'distributor';
    case 1: return 'fleet manager';
    default: return 'user';
  }
}

async function fetchUsers() {
  loading.value = true;
  error.value = '';
  try {
    const params = { q: query.value, withDeleted: 1 };
    const { data } = await axios.get('/web/users', { params });
    const list = Array.isArray(data?.users) ? data.users : [];
    rows.value = list;
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load users';
  } finally {
    loading.value = false;
  }
}

async function deleteUserPermanent(id, name) {
  if (!canDelete.value) return;
  const result = await Swal.fire({
    title: `Permanently delete user ${name || id}?`,
    text: 'This will permanently remove the user.',
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
    await axios.delete(`/web/users/${id}`, { params: { force: 1 } });
    rows.value = rows.value.filter(r => r.id !== id);
    await Swal.fire({ title: 'Deleted', text: 'User has been permanently deleted.', icon: 'success', timer: 1400, showConfirmButton: false });
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to permanently delete user';
    await Swal.fire({ title: 'Delete failed', text: error.value, icon: 'error' });
  } finally {
    deleting.value[id] = false;
  }
}

async function blockUser(id, name) {
  if (!canDelete.value) return;
  const result = await Swal.fire({
    title: `Block user ${name || id}?`,
    text: 'This will hide the user and mark as blocked.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Block',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#f0ad4e',
  });
  if (!result.isConfirmed) return;
  blocking.value[id] = true;
  error.value = '';
  try {
    await axios.delete(`/web/users/${id}`);
    // We always include blocked, so update the row state in-place
    const idx = rows.value.findIndex(r => r.id === id);
    if (idx >= 0) rows.value[idx] = { ...rows.value[idx], blocked: true, deletedAt: new Date().toISOString() };
    await Swal.fire({ title: 'Blocked', text: 'User has been blocked.', icon: 'success', timer: 1200, showConfirmButton: false });
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to block user';
    await Swal.fire({ title: 'Block failed', text: error.value, icon: 'error' });
  } finally {
    blocking.value[id] = false;
  }
}

async function activateUser(id, name) {
  if (!canDelete.value) return;
  const result = await Swal.fire({
    title: `Activate user ${name || id}?`,
    text: 'This will restore access for the user.',
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
    await axios.patch(`/web/users/${id}/restore`);
    const idx = rows.value.findIndex(r => r.id === id);
    if (idx >= 0) rows.value[idx] = { ...rows.value[idx], blocked: false, deletedAt: null };
    await Swal.fire({ title: 'Activated', text: 'User has been activated.', icon: 'success', timer: 1200, showConfirmButton: false });
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to activate user';
    await Swal.fire({ title: 'Activate failed', text: error.value, icon: 'error' });
  } finally {
    activating.value[id] = false;
  }
}

onMounted(async () => {
  alertMsg.value = String(route.query?.alert || '');
  await fetchUsers();
});

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
