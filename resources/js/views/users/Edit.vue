<template>
  <div class="edit-user-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/users">User Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Edit {{ displayRoleLabel }}</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">Edit {{ displayRoleLabel }}</h4>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <form @submit.prevent="submit">
      <div v-if="loading" class="d-flex align-items-center mb-2">
        <div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading…</span></div>
        <span class="text-muted small">Loading user…</span>
      </div>

      <!-- Account Information -->
      <div class="card mb-3" v-if="!loading">
        <div class="card-header"><h6 class="mb-0">Account Information</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label small">First Name</label>
              <input v-model="form.firstName" type="text" class="form-control" placeholder="First Name" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Last Name</label>
              <input v-model="form.lastName" type="text" class="form-control" placeholder="Last Name" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Email Address</label>
              <input v-model="form.email" type="email" class="form-control" placeholder="Email Address" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Phone Number</label>
              <input v-model="form.phone" type="tel" class="form-control" placeholder="Phone Number" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">New Password</label>
              <div class="position-relative">
                <input v-model="form.password" :type="showPassword ? 'text' : 'password'" class="form-control pe-5" placeholder="Leave blank to keep" />
                <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-decoration-none text-muted" @click="showPassword = !showPassword" tabindex="-1">
                  <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Confirm Password</label>
              <div class="position-relative">
                <input v-model="form.password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" class="form-control pe-5" placeholder="Confirm Password" />
                <button type="button" class="btn btn-link position-absolute top-50 end-0 translate-middle-y text-decoration-none text-muted" @click="showConfirmPassword = !showConfirmPassword" tabindex="-1">
                  <i class="bi" :class="showConfirmPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Role selection removed on Edit; roles are not editable here -->

      <!-- Vehicle Assignment for Fleet Managers -->
      <div class="card mb-3" v-if="isFleetManager && isTargetFleetViewer && !loading">
        <div class="card-header"><h6 class="mb-0">Vehicle Assignment</h6></div>
        <div class="card-body">
          <div class="mb-2 text-muted small">Select vehicles to assign to this Fleet Viewer. Unselected vehicles will be unassigned.</div>

          <div v-if="loadingDevices" class="text-muted small mb-2">Loading vehicles…</div>
          <div class="multi-input form-control" :class="{ disabled: loadingDevices || submitting }" @click="devicesOpen = !devicesOpen">
            <span v-for="id in form.assignedDeviceIds" :key="'chip-dev-'+id" class="chip">
              {{ labelForDevice(id) }}
              <button type="button" class="chip-remove" @click.stop="removeDevice(id)" aria-label="Remove">×</button>
            </span>
            <i class="bi bi-caret-down-fill ms-auto small"></i>
          </div>
          <div class="dropdown-list" v-show="devicesOpen">
            <div v-for="d in deviceOptionsNormalized" :key="d.id" class="dropdown-item"
                 :class="{ selected: form.assignedDeviceIds.includes(d.id) }"
                 @click="toggleDevice(d.id)">
              {{ d.label }}
            </div>
            <div v-if="deviceOptionsNormalized.length === 0" class="dropdown-item text-muted">No vehicles available</div>
          </div>
          <div class="text-muted small mt-1">Assigned: {{ form.assignedDeviceIds.length }}</div>
        </div>
      </div>

      <!-- Actions -->
      <div class="d-flex align-items-center justify-content-end gap-2">
        <RouterLink to="/users" class="btn btn-outline-secondary">Cancel</RouterLink>
        <button type="submit" class="btn btn-app-dark" :disabled="submitting">{{ submitting ? 'Saving…' : 'Save Changes' }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';
import { authState } from '../../auth';

const route = useRoute();
const router = useRouter();
const userId = Number(route.params.userId);

const form = reactive({
  id: null,
  firstName: "",
  lastName: "",
  email: "",
  phone: "",
  password: "",
  password_confirmation: "",
  role: 0,
  role_label: "",
  assignedDeviceIds: [],
});

const message = ref('');
const error = ref('');
const loading = ref(false);
const submitting = ref(false);
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const availableDevices = ref([]);
const loadingDevices = ref(false);
const devicesOpen = ref(false);

// Role/distributor options removed; roles are not editable here

const isFleetManager = computed(() => Number(authState.user?.role) === 1);
const isTargetFleetViewer = computed(() => Number(form.role) === 0); // Assuming 0 is ROLE_USER (Fleet Viewer)

function roleLabel(role) {
  switch (Number(role)) {
    case 3: return 'admin';
    case 2: return 'distributor';
    case 1: return 'fleet manager';
    default: return 'user';
  }
}

const displayRoleLabel = computed(() => {
  const lbl = String(form.role_label || '').trim();
  return lbl ? lbl : roleLabel(form.role);
});

async function fetchUser() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(`/web/users/${userId}`);
    const [firstName, ...rest] = (data?.name || '').split(' ');
    const lastName = rest.join(' ');
    form.id = data?.id ?? null;
    form.firstName = firstName || '';
    form.lastName = lastName || '';
    form.email = data?.email || '';
    form.phone = data?.phone || '';
    form.role = Number(data?.role ?? 0);
    form.role_label = String(data?.role_label || '');
    form.assignedDeviceIds = (data?.assigned_device_ids || []).map(Number);
  } catch (e) {
    const msg = e?.response?.data?.message || 'Failed to load user';
    error.value = msg;
    setTimeout(() => router.replace({ path: '/users', query: { error: msg } }), 300);
  } finally {
    loading.value = false;
  }
}

function normalizeOption(o) {
  const idRaw = o?.id ?? o?.deviceId;
  const id = Number(idRaw || 0);
  const labelRaw = o?.label ?? o?.name ?? o?.text ?? o?.title ?? (id ? ('#' + id) : '');
  const label = typeof labelRaw === 'string' ? labelRaw : String(labelRaw || '');
  return { id, label: label.trim() };
}

const deviceOptionsNormalized = computed(() => {
  const list = Array.isArray(availableDevices.value) ? availableDevices.value : [];
  return list.map(normalizeOption).filter(o => o.id > 0 && o.label);
});

function labelForDevice(id) {
  const opt = deviceOptionsNormalized.value.find(o => o.id === Number(id));
  return opt?.label || ('#' + id);
}

function toggleDevice(id) {
  const n = Number(id);
  const set = new Set(form.assignedDeviceIds.map(Number));
  if (set.has(n)) set.delete(n); else set.add(n);
  form.assignedDeviceIds = Array.from(set);
}

function removeDevice(id) {
  const n = Number(id);
  form.assignedDeviceIds = form.assignedDeviceIds.filter(v => Number(v) !== n);
}

function clearDevices() { form.assignedDeviceIds = []; }

onMounted(async () => {
  await fetchUser();
  if (isFleetManager.value) {
    loadingDevices.value = true;
    try {
      const { data } = await axios.get('/web/users/device-options?all=1');
      // API returns array directly or {options: []}
      availableDevices.value = Array.isArray(data) ? data : (data?.options || []);
    } catch (e) {
      console.error('Failed to load vehicles', e);
    } finally {
      loadingDevices.value = false;
    }
  }
});

async function submit() {
  message.value = '';
  error.value = '';
  submitting.value = true;
  try {
    const payload = {
      name: `${form.firstName} ${form.lastName}`.trim(),
      email: form.email,
      phone: form.phone,
    };
    if (form.password) {
      payload.password = form.password;
      payload.password_confirmation = form.password_confirmation;
    }

    if (isFleetManager.value && isTargetFleetViewer.value) {
      payload.device_ids = form.assignedDeviceIds;
    }

    const { data } = await axios.put(`/web/users/${userId}`, payload);
    message.value = data?.message || 'User updated';
    setTimeout(() => router.push('/users'), 400);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to save changes';
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>

/* Chip Multiselect Styles */
.gap-2 { gap: .5rem; }
.multi-input { display: flex; align-items: center; gap: .25rem; min-height: 38px; cursor: pointer; flex-wrap: wrap; padding: 4px 8px; }
.multi-input.disabled { pointer-events: none; opacity: .6; }
.chip { display: inline-flex; align-items: center; gap: .25rem; background: #eef1f6; color: #333; border-radius: 16px; padding: .125rem .5rem; font-size: 12px; }
.chip-remove { background: transparent; border: 0; color: #666; line-height: 1; padding: 0; cursor: pointer; }
.clear-btn { margin-left: auto; background: transparent; border: 0; color: #666; cursor: pointer; }
.dropdown-list { border: 1px solid #dee2e6; border-top: 0; border-radius: 0 0 .375rem .375rem; max-height: 200px; overflow: auto; }
.dropdown-item { padding: .375rem .5rem; font-size: 13px; cursor: pointer; }
.dropdown-item:hover { background: #f6f7fb; }
.dropdown-item.selected { background: #e9f3ff; }
</style>
