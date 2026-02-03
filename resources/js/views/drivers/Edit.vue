<template>
  <div class="edit-driver-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/drivers">Driver Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Edit Driver</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">Edit Driver</h4>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <form @submit.prevent="submit">
      <div v-if="loading" class="d-flex align-items-center mb-2">
        <div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading…</span></div>
        <span class="text-muted small">Loading driver…</span>
      </div>

      <!-- Personal Information -->
      <div class="card mb-3" v-if="!loading">
        <div class="card-header"><h6 class="mb-0">Personal Information</h6></div>
        <div class="card-body">
          <div class="row g-3 align-items-start">
            <div class="col-12 col-md-3">
              <label class="form-label small">Driver ID</label>
              <input v-model="form.driverId" type="text" class="form-control" placeholder="DRV-1016" disabled />
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label small">Full Name</label>
              <input v-model="form.fullName" type="text" class="form-control" placeholder="eg. Ethan Lewis" />
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label small">Gender</label>
              <select v-model="form.gender" class="form-select">
                <option value="">-- Select Option --</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label small">Date of Birth</label>
              <input v-model="form.dob" type="date" class="form-control" :max="dobMax" />
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label small">ID Card Number</label>
              <input v-model="form.idCard" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="ID Card Number" />
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label small">Passport Number</label>
              <input v-model="form.passport" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="Passport Number" />
            </div>

            <div class="col-12">
              <div class="form-check form-switch mt-2">
                <input v-model="form.healthOk" class="form-check-input" type="checkbox" id="healthOk">
                <label class="form-check-label small" for="healthOk">
                  This Person do not have any medical condition and fit to drive safely.
                </label>
              </div>
              <div class="form-check form-switch mt-2">
                <input v-model="form.isClientDriver" class="form-check-input" type="checkbox" id="isClientDriver">
                <label class="form-check-label small" for="isClientDriver">
                  Is Client Driver (Available for temporary assignments)
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact Information -->
      <div class="card mb-3" v-if="!loading">
        <div class="card-header"><h6 class="mb-0">Contact Information</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label small">Email Address</label>
              <input v-model="form.email" type="email" class="form-control" placeholder="Email Address" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Phone Number</label>
              <input v-model="form.phone" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="Phone Number" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Address</label>
              <input v-model="form.address" type="text" class="form-control" placeholder="Home Address" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Telephone</label>
              <input v-model="form.telephone" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="Telephone number" />
            </div>
          </div>
        </div>
      </div>

      <!-- Driving License Information -->
      <div class="card mb-3" v-if="!loading">
        <div class="card-header"><h6 class="mb-0">Driving License Information</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small">Avatar Image</label>
              <div class="d-flex align-items-center gap-3 mb-2">
                <img v-if="existingAvatarUrl" :src="existingAvatarUrl" alt="Avatar" class="rounded-circle" style="width:64px;height:64px;object-fit:cover;" />
                <span v-else class="text-muted small">No avatar uploaded</span>
              </div>
              <div class="input-group">
                <input class="form-control" type="file" accept="image/*" @change="onAvatarFile" />
                <span class="input-group-text">Browse Files</span>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label small">Licence Image</label>
              <div class="d-flex align-items-center gap-3 mb-2">
                <img v-if="existingLicenseUrl" :src="existingLicenseUrl" alt="Licence" class="rounded" style="width:96px;height:64px;object-fit:cover;" />
                <span v-else class="text-muted small">No licence image uploaded</span>
              </div>
              <div class="input-group">
                <input class="form-control" type="file" accept="image/*" @change="onFile" />
                <span class="input-group-text">Browse Files</span>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">License Number</label>
              <input v-model="form.licence" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="License Number" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Expiry Date</label>
              <input v-model="form.expiry" type="date" class="form-control" :min="expiryMin" />
            </div>
          </div>
        </div>
      </div>

      <!-- Assigned Vehicle -->
      <div class="card mb-3" v-if="!loading">
        <div class="card-header"><h6 class="mb-0">Assigned Vehicle</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small">Assigned Vehicle</label>
              <select v-model="form.vehicle" class="form-select">
                <option value="">-- Select Vehicle --</option>
                <option v-for="opt in vehiclesOptions" :key="opt.id" :value="opt.id">
                  {{ opt.label || opt.name || opt.uniqueId || opt.id }}
                </option>
              </select>
              <div v-if="loadingVehicles" class="text-muted small mt-1">Loading vehicles…</div>
              <div v-if="vehiclesError" class="text-danger small mt-1">{{ vehiclesError }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="d-flex align-items-center justify-content-end gap-2">
        <RouterLink to="/drivers" class="btn btn-outline-secondary">Cancel</RouterLink>
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

const route = useRoute();
const router = useRouter();
const driverId = Number(route.params.driverId);

const form = reactive({
  driverId: '',
  fullName: '',
  gender: '',
  dob: '',
  idCard: '',
  passport: '',
  healthOk: false,
  isClientDriver: false,
  email: '',
  phone: '',
  address: '',
  telephone: '',
  licence: '',
  expiry: '',
  vehicle: '',
  licenceImage: null,
  avatar: null,
});

const message = ref('');
const error = ref('');
const loading = ref(false);
const submitting = ref(false);

const vehiclesOptions = ref([]);
const loadingVehicles = ref(false);
const vehiclesError = ref('');

const existingAvatarUrl = ref('');
const existingLicenseUrl = ref('');

const dobMax = computed(() => {
  const today = new Date();
  const y = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
  const yyyy = y.getFullYear();
  const mm = String(y.getMonth() + 1).padStart(2, '0');
  const dd = String(y.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
});

const expiryMin = computed(() => {
  const today = new Date();
  const t = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
  const yyyy = t.getFullYear();
  const mm = String(t.getMonth() + 1).padStart(2, '0');
  const dd = String(t.getDate()).padStart(2, '0');
  return `${yyyy}-${mm}-${dd}`;
});

function parseAttrsMaybe(attr) {
  if (!attr) return {};
  if (typeof attr === 'string') {
    try { return JSON.parse(attr); } catch { return {}; }
  }
  return { ...attr };
}

async function fetchVehicleOptions() {
  loadingVehicles.value = true;
  vehiclesError.value = '';
  try {
    const [optionsRes, assignmentsRes] = await Promise.all([
       axios.get('/web/drivers/options'),
       axios.get('/web/drivers/assignments?status=active')
     ]);

    const list = Array.isArray(optionsRes.data?.options) ? optionsRes.data.options : [];
    const activeAssignments = assignmentsRes.data || [];

    // Filter out vehicles assigned to *other* drivers
    // Keep the vehicle if it is assigned to *this* driver
    const currentDriverId = Number(route.params.driverId);
    const assignedVehicleIds = new Set();

    activeAssignments.forEach(a => {
      // a.driver_id is the local ID of the driver who has this assignment
      if (Number(a.driver_id) !== currentDriverId) {
        assignedVehicleIds.add(a.vehicle_id);
      }
    });

    vehiclesOptions.value = list.filter(v => !assignedVehicleIds.has(v.id));
  } catch (e) {
    vehiclesError.value = e?.response?.data?.message || 'Failed to load vehicles';
  } finally {
    loadingVehicles.value = false;
  }
}

async function fetchDriver() {
  loading.value = true;
  error.value = '';
  try {
    const { data } = await axios.get(`/web/drivers/${driverId}`);
    const attrs = parseAttrsMaybe(data?.attributes);
    form.fullName = data?.name || '';
    form.driverId = data?.uniqueId || '';
    form.email = attrs?.email || '';
    form.phone = attrs?.phone || '';
    form.gender = attrs?.gender || '';
    form.dob = attrs?.dob || '';
    form.idCard = attrs?.idCard || '';
    form.passport = attrs?.passport || '';
    form.healthOk = Boolean(attrs?.healthOk);
    form.isClientDriver = Boolean(data?.isClientDriver);
    form.address = attrs?.address || '';
    form.telephone = attrs?.telephone || '';
    form.licence = attrs?.licence || attrs?.license || '';
    form.expiry = attrs?.licenseExpiry || '';
    // Prefer local device mapping; fallback to stored attribute
    form.vehicle = data?.deviceId || attrs?.assignedVehicle || '';
    existingAvatarUrl.value = data?.avatarImageUrl || (attrs?.avatarImage ? `/storage/${attrs.avatarImage}` : '');
    existingLicenseUrl.value = data?.licenseImageUrl || (attrs?.licenseImage ? `/storage/${attrs.licenseImage}` : '');
  } catch (e) {
    const msg = e?.response?.data?.message || 'Failed to load driver';
    error.value = msg;
    // Redirect back to list on failure to load
    setTimeout(() => router.replace({ path: '/drivers', query: { error: msg } }), 300);
  } finally {
    loading.value = false;
  }
}

function onFile(event) {
  form.licenceImage = event.target.files?.[0] || null;
}

function onAvatarFile(event) {
  form.avatar = event.target.files?.[0] || null;
}

onMounted(async () => {
  await Promise.all([fetchDriver(), fetchVehicleOptions()]);
});

async function submit() {
  message.value = '';
  error.value = '';
  submitting.value = true;

  // Validate DOB is before today
  const today = new Date();
  const todayMid = new Date(today.getFullYear(), today.getMonth(), today.getDate());
  if (form.dob) {
    const dobDate = new Date(form.dob);
    if (dobDate >= todayMid) {
      error.value = 'Date of Birth must be before today';
      submitting.value = false;
      return;
    }
  }

  // Validate Expiry is after today
  if (form.expiry) {
    const expDate = new Date(form.expiry);
    if (expDate <= todayMid) {
      error.value = 'Expiry Date must be after today';
      submitting.value = false;
      return;
    }
  }

  // Validate email format
  if (form.email) {
    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRe.test(form.email)) {
      error.value = 'Email Address must be a valid email';
      submitting.value = false;
      return;
    }
  }

  // Validate phone and telephone numeric and non-negative
  if (form.phone && !/^\d+$/.test(form.phone)) {
    error.value = 'Phone Number must be numeric and >= 0';
    submitting.value = false;
    return;
  }
  if (form.telephone && !/^\d+$/.test(form.telephone)) {
    error.value = 'Telephone must be numeric and >= 0';
    submitting.value = false;
    return;
  }

  // Validate ID Card numeric and non-negative
  if (form.idCard && !/^\d+$/.test(form.idCard)) {
    error.value = 'ID Card Number must be numeric and >= 0';
    submitting.value = false;
    return;
  }
  // Validate Passport numeric and non-negative
  if (form.passport && !/^\d+$/.test(form.passport)) {
    error.value = 'Passport Number must be numeric and >= 0';
    submitting.value = false;
    return;
  }
  // Validate License Number numeric and non-negative
  if (form.licence && !/^\d+$/.test(form.licence)) {
    error.value = 'License Number must be numeric and >= 0';
    submitting.value = false;
    return;
  }

  try {
    const attrs = {
      driverId: form.driverId,
      email: form.email || null,
      phone: form.phone || null,
      gender: form.gender,
      dob: form.dob,
      idCard: form.idCard,
      passport: form.passport,
      healthOk: form.healthOk,
      address: form.address,
      telephone: form.telephone,
      licence: form.licence,
      licenseExpiry: form.expiry,
      assignedVehicle: form.vehicle,
    };

    const fd = new FormData();
    fd.append('_method', 'PUT');
    fd.append('name', form.fullName);
    fd.append('uniqueId', (form.driverId || '').toString().trim());
    fd.append('is_client_driver', form.isClientDriver ? '1' : '0');
    fd.append('attributes', JSON.stringify(attrs));
    if (form.licenceImage) fd.append('licenceImage', form.licenceImage);
    if (form.avatar) fd.append('avatar', form.avatar);

    const { data } = await axios.post(`/web/drivers/${driverId}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });

    message.value = data?.message || 'Driver updated';
    setTimeout(() => router.push('/drivers'), 400);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to save changes';
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.input-w-360 { width: 360px; }
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>
