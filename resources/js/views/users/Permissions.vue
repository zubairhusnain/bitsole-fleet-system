<template>
  <div class="permissions-view" v-if="canAccessPermissions">
    <div v-if="pageLoading" class="d-flex justify-content-center py-5">
      <div class="spinner-border text-secondary" role="status"></div>
    </div>
    <div v-else>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/users">User Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">User Permissions</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">Assign Module Permissions</h4>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <!-- Target User Selection -->
    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">Select User</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-6">
            <label class="form-label small">User</label>
            <select class="form-select" v-model.number="selectedUserId" @change="onSelectUser">
              <option :value="0" disabled>Select a user…</option>
              <option v-for="u in filteredUserOptions" :key="u.id" :value="u.id">
                {{ u.name }} ({{ u.email }}) — {{ u.role_label || roleLabel(u.role) }}
              </option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small">Modules</label>
            <div class="small text-muted">Modules are listed automatically from system configuration.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3" v-if="selectedUserId">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Module Access</h6>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary" @click="reloadPermissions" :disabled="loading">Reload</button>
          <button class="btn btn-app-dark" @click="save" :disabled="!canAssignToSelected || saving">
            {{ saving ? 'Saving…' : 'Save Permissions' }}
          </button>
        </div>
      </div>
      <div class="card-body">
        <div v-if="loading" class="text-center py-4">
          <div class="spinner-border text-secondary" role="status"></div>
        </div>
        <div v-else class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Module</th>
                <th class="text-center">Read</th>
                <th class="text-center">Create</th>
                <th class="text-center">Update</th>
                <th class="text-center">Delete</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in modules" :key="m.key">
                <td>{{ m.label }}</td>
                <td class="text-center"><input type="checkbox" v-model="permissionsMap[m.key].read" /></td>
                <td class="text-center"><input type="checkbox" v-model="permissionsMap[m.key].create" /></td>
                <td class="text-center"><input type="checkbox" v-model="permissionsMap[m.key].update" /></td>
                <td class="text-center"><input type="checkbox" v-model="permissionsMap[m.key].delete" /></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="!modules.length" class="text-muted mb-0">No modules registered.</p>
      </div>
    </div>
    </div>
  </div>
  <div v-else class="text-muted">You do not have access to permissions.</div>

</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { authState } from '../../auth';
import UiAlert from '../../components/UiAlert.vue';

const router = useRouter();
const loading = ref(false);
const saving = ref(false);
const pageLoading = ref(true);
const error = ref('');
const message = ref('');
const selectedUserId = ref(0);
const userOptions = ref([]);
const modules = ref([]);
const permissionsMap = reactive({});

const currentUser = computed(() => authState?.user || {});
const currentRole = computed(() => Number(currentUser.value?.role ?? 0));

// Only distributor (2) and manager (1) can access permissions page
const canAccessPermissions = computed(() => currentRole.value === 2 || currentRole.value === 1);

function roleLabel(role) {
  switch (Number(role)) {
    case 3: return 'admin';
    case 2: return 'distributor';
    case 1: return 'manager';
    default: return 'user';
  }
}

function canAssignTo(user) {
  if (!user) return false;
  const me = currentUser.value;
  const myRole = Number(me?.role ?? 0);
  const userRole = Number(user.role ?? 0);
  if (myRole === 3) return true; // admin can assign to anyone
  if (myRole === 2) {
    // Distributor: can assign only to their own managers
    return Number(user.distributor_id) === Number(me.id) && userRole === 1;
  }
  if (myRole === 1) {
    // Manager: can assign only to their own users
    return Number(user.manager_id) === Number(me.id) && userRole === 0;
  }
  return false;
}

const canAssignToSelected = computed(() => {
  const u = userOptions.value.find(x => Number(x.id) === Number(selectedUserId.value));
  return canAssignTo(u);
});

// Filtered options based on current role and ownership
const filteredUserOptions = computed(() => {
  const me = currentUser.value;
  const myRole = Number(me?.role ?? 0);
  const all = userOptions.value;
  if (myRole === 3) return all; // admin sees all
  if (myRole === 2) {
    // Distributor sees only their own managers
    return all.filter(u => Number(u.role) === 1 && Number(u.distributor_id) === Number(me.id));
  }
  if (myRole === 1) {
    // Manager sees only their own users
    return all.filter(u => Number(u.role) === 0 && Number(u.manager_id) === Number(me.id));
  }
  return [];
});

async function loadOptions() {
  error.value = '';
  try {
    const [{ data: usersResp }, { data: optResp }] = await Promise.all([
      axios.get('/web/users'),
      axios.get('/web/users/options'),
    ]);
    userOptions.value = (usersResp?.users ?? []).map(u => ({
      id: u.id,
      name: u.name,
      email: u.email,
      role: u.role,
      role_label: u.role_label,
      distributor_id: u.distributor_id,
      manager_id: u.manager_id,
    }));
    modules.value = (optResp?.modules ?? []).map(m => ({ key: m.key, label: m.label }));
    // Reset selection in case previous selection becomes invalid under new filter
    selectedUserId.value = 0;
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load options';
    pageLoading.value = false;
  }
}

async function reloadPermissions() {
  error.value = '';
  if (!selectedUserId.value) return;
  loading.value = true;
  try {
    const { data } = await axios.get(`/web/users/${selectedUserId.value}/permissions`);
    const perms = data?.permissions ?? [];
    for (const m of modules.value) {
      permissionsMap[m.key] = { read: false, create: false, update: false, delete: false };
    }
    for (const p of perms) {
      permissionsMap[p.key] = {
        read: !!p.can_read,
        create: !!p.can_create,
        update: !!p.can_update,
        delete: !!p.can_delete,
      };
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load permissions';
  } finally {
    loading.value = false;
  }
}

function onSelectUser() { reloadPermissions(); }

async function save() {
  if (!selectedUserId.value) return;
  if (!canAssignToSelected.value) {
    error.value = 'You are not allowed to assign permissions to this user.';
    return;
  }
  saving.value = true;
  error.value = '';
  message.value = '';
  try {
    const payload = {
      permissions: modules.value.map(m => ({
        key: m.key,
        can_read: !!permissionsMap[m.key].read,
        can_create: !!permissionsMap[m.key].create,
        can_update: !!permissionsMap[m.key].update,
        can_delete: !!permissionsMap[m.key].delete,
      })),
    };
    const { data } = await axios.put(`/web/users/${selectedUserId.value}/permissions`, payload);
    message.value = data?.message || 'Permissions updated';
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to save permissions';
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await loadOptions();
  const list = filteredUserOptions.value;
  if (list && list.length > 0) {
    selectedUserId.value = Number(list[0].id);
    await reloadPermissions();
    pageLoading.value = false;
  } else {
    router.push({ path: '/users', query: { alert: 'No eligible users to assign permissions' } });
    pageLoading.value = false;
  }
});
</script>

<style scoped>
.input-w-360 { max-width: 360px; }
</style>
