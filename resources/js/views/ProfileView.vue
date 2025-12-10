<template>
  <div class="container py-3">
    <div v-if="loading" class="d-flex justify-content-center py-5">
      <div class="spinner-border text-secondary" role="status"></div>
    </div>
    <div v-else>
      <div class="app-content-header mb-2">
        <ol class="breadcrumb mb-0 small text-muted">
          <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
          <li class="breadcrumb-item active" aria-current="page">Profile</li>
        </ol>
      </div>
      <h4 class="mb-3 fw-semibold">My Profile</h4>
      <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
      <UiAlert :show="!!success" :message="success" variant="success" dismissible @dismiss="success = ''" />
      <div class="row g-3">
        <div class="col-12 col-lg-4">
          <div class="card">
            <div class="card-body">
              <div class="mb-2 fw-semibold">{{ user.name }}</div>
              <div class="text-muted small">{{ user.email }}</div>
              <div class="mt-2 badge bg-secondary text-uppercase">{{ displayRoleLabel }}</div>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-8">
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h6 class="mb-0">Update Profile</h6>
              <button class="btn btn-primary btn-sm" :disabled="saving" @click="saveProfile">{{ saving ? 'Saving…' : 'Save Changes' }}</button>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-12 col-md-6">
                  <label class="form-label">Name</label>
                  <input type="text" class="form-control" v-model.trim="edit.name" />
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Email</label>
                  <input type="email" class="form-control" v-model="edit.email" disabled />
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Phone</label>
                  <input type="text" class="form-control" v-model.trim="edit.phone" />
                </div>
                <div class="col-12 col-md-6">
                  <label class="form-label">Password</label>
                  <div class="position-relative">
                    <input :type="showPassword ? 'text' : 'password'" class="form-control pe-5" v-model="edit.password" placeholder="Leave blank to keep current" />
                    <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-decoration-none text-muted" @click="showPassword = !showPassword" tabindex="-1">
                      <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="card" v-if="role === 1 || role === 0">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h6 class="mb-0">Assigned Permissions</h6>
              <button class="btn btn-outline-secondary btn-sm" @click="reload">Reload</button>
            </div>
            <div class="card-body">
              <div v-if="permLoading" class="text-center py-3">
                <div class="spinner-border text-secondary" role="status"></div>
              </div>
              <div v-else>
                <div class="table-responsive">
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
                      <tr v-for="row in permRows" :key="row.key">
                        <td>{{ row.key }}</td>
                        <td class="text-center">{{ row.read ? '✓' : '—' }}</td>
                        <td class="text-center">{{ row.create ? '✓' : '—' }}</td>
                        <td class="text-center">{{ row.update ? '✓' : '—' }}</td>
                        <td class="text-center">{{ row.delete ? '✓' : '—' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <p v-if="!permRows.length" class="text-muted mb-0">No permissions assigned.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink, useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import UiAlert from '../components/UiAlert.vue';
import { authState } from '../auth';

const router = useRouter();
const route = useRoute();
const loading = ref(true);
const permLoading = ref(false);
const error = ref('');
const success = ref('');
const user = ref({});
const permissions = ref({});
const saving = ref(false);
const showPassword = ref(false);
const edit = ref({ name: '', email: '', phone: '', password: '' });

const role = computed(() => Number(user.value?.role ?? 0));

function roleLabel(r) {
  switch (Number(r)) {
    case 3: return 'admin';
    case 2: return 'distributor';
    case 1: return 'manager';
    default: return 'user';
  }
}
const displayRoleLabel = computed(() => {
  const lbl = String(user.value?.role_label || '').trim();
  return lbl ? lbl : roleLabel(role.value);
});

const permRows = computed(() => {
  const map = permissions.value || {};
  return Object.keys(map)
    .sort()
    .map(k => ({ key: k, read: !!map[k]?.read, create: !!map[k]?.create, update: !!map[k]?.update, delete: !!map[k]?.delete }))
    .filter(r => r.read || r.create || r.update || r.delete);
});

async function reload() {
  permLoading.value = true;
  try {
    const { data } = await axios.get('/web/auth/me');
    user.value = data?.user || {};
    permissions.value = data?.permissions || {};
    authState.user = user.value;
    authState.permissions = permissions.value;
    edit.value = { name: user.value?.name || '', email: user.value?.email || '', phone: user.value?.phone || '', password: '' };
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load profile';
  } finally {
    permLoading.value = false;
  }
}

async function saveProfile() {
  error.value = '';
  success.value = '';
  saving.value = true;
  try {
    const uid = Number(user.value?.id);
    const payload = {
      name: edit.value.name,
      phone: edit.value.phone,
    };
    const pw = String(edit.value.password || '').trim();
    if (pw.length > 0) {
      payload.password = pw;
    }
    const { data } = await axios.put(`/web/users/${uid}`, payload);
    user.value = data?.user || user.value;
    authState.user = user.value;
    success.value = 'Profile updated successfully';
    edit.value.password = '';
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to update profile';
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  const qErr = route.query?.error;
  if (qErr) {
    const missing = route.query?.missing ? String(route.query.missing) : '';
    error.value = qErr === 'route_not_exist'
      ? (missing ? ('Requested page not found: ' + missing) : 'Requested page does not exist')
      : String(qErr);
    router.replace('/profile');
  }
  // Show cached auth data immediately (fast initial render)
  user.value = authState.user || {};
  permissions.value = authState.permissions || {};
  loading.value = false;
  edit.value = { name: user.value?.name || '', email: user.value?.email || '', phone: user.value?.phone || '', password: '' };
  // Then refresh from server in the background for latest permissions
  await reload();
});
</script>

<style scoped>
</style>
