<template>
  <div class="edit-vehicle-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/vehicles">Vehicle Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Edit Vehicle</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">Edit Vehicle</h4>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="dismissError" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="dismissMessage" />

    <form @submit.prevent="submit">
      <div v-if="loading" class="d-flex align-items-center mb-2">
        <div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading…</span></div>
        <span class="text-muted small">Loading vehicle…</span>
      </div>

      <!-- Vehicle Information -->
      <div v-if="!loading" class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Vehicle Information</h6></div>
        <div class="card-body">
          <div class="row g-3 align-items-start">
            <input :value="deviceId" type="hidden" />
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Name</label>
              <input v-model="form.name" type="text" class="form-control" placeholder="e.g. Toyota Camry" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle No</label>
              <input v-model="form.attributes.vehicleNo" type="text" class="form-control" placeholder="e.g. V-001" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Type</label>
              <select v-model="form.attributes.type" class="form-select">
                <option value="">-- Select Option --</option>
                <option>Sedan</option>
                <option>SUV</option>
                <option>MPV</option>
                <option>BUS</option>
                <option>Pick-Up</option>
                <option>Hatchback</option>
                <option>Truck</option>
                <option>Trailer Truck</option>
                <option>Van</option>
                <option>Machinery</option>
                <option>Forklift</option>
              </select>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Color</label>
              <input v-model="form.attributes.color" type="text" class="form-control" placeholder="e.g. White" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Model</label>
              <input v-model="form.model" type="text" class="form-control" placeholder="e.g. 2023" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Manufacturer</label>
              <input v-model="form.attributes.manufacturer" type="text" class="form-control" placeholder="e.g. Toyota" />
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small">Registration Number</label>
              <input v-model="form.attributes.registration" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="e.g. 987654" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Plate Number</label>
              <input v-model="form.attributes.plate" type="text" class="form-control" placeholder="e.g. ABC-123" />
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small">Fuel Average</label>
              <input
                v-model.number="form.attributes.fuelAverage"
                type="number"
                min="0"
                step="1"
                inputmode="numeric"
                pattern="[0-9]*"
                class="form-control"
                placeholder="e.g. 10"
              />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Fuel Type</label>
              <select v-model="form.attributes.fuelType" class="form-select">
                <option value="">-- Select Fuel Type --</option>
                <option>Diesel</option>
                <option>Petrol</option>
                <option>Electric Vehicle (EV)</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Fuel Tank Capacity (Liters)</label>
              <input v-model.number="form.attributes.fuelTankCapacity" type="number" min="0" step="0.1" inputmode="decimal" class="form-control" placeholder="e.g. 60" />
            </div>
          </div>
        </div>
      </div>

      <!-- Tracking Device Information -->
      <div v-if="!loading" class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Tracking Device Information</h6></div>
        <div class="card-body">
          <div class="row g-3 align-items-start">
            <div class="col-12 col-md-4">
              <label class="form-label small">Device ID ( IMEI )</label>
              <input v-model="form.uniqueId" type="text" class="form-control" placeholder="e.g. 123456789012345" disabled />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Tracker Model</label>
              <select v-model="form.attributes.trackerModel" class="form-select">
                <option value="">-- Select Tracker Model --</option>
                <option v-for="opt in trackerModels" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Tracking Device Profile -->
      <div v-if="!loading" class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Tracking Device Profile</h6></div>
        <div class="card-body">
          <div class="row g-3 align-items-start">
            <div class="col-12 col-md-4">
              <label class="form-label small">Max Speed</label>
              <input v-model="form.speedLimit" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="e.g. 120" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Odometer Attribute</label>
              <select v-model="form.attributes.odometerAttr" class="form-select">
                <option value="">-- Select Odometer Attribute --</option>
                <option v-for="opt in odometerOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Fuel Attribute</label>
              <select v-model="form.attributes.fuelAttr" class="form-select">
                <option value="">-- Select Fuel Attribute --</option>
                <option v-for="opt in fuelOptions" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Vehicle Photos -->
      <div v-if="!loading" class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Vehicle Photos</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div v-for="(preview, i) in previews" :key="i" class="col-12 col-md-4">
              <div class="upload-wrapper position-relative">
                <label class="upload-box w-100" :class="{ 'has-image': !!preview }">
                  <input type="file" accept="image/png,image/jpeg" class="d-none" @change="onFile(i, $event)" />
                  <img v-if="preview" :src="preview" alt="Vehicle photo" class="upload-img" />
                  <div v-else class="upload-empty">
                    <i class="bi bi-cloud-arrow-up fs-3"></i>
                    <div class="small mt-1">Upload Vehicle Images</div>
                    <div class="text-muted xsmall">jpeg & png only</div>
                  </div>
                </label>
                <button v-if="preview" type="button" class="btn btn-sm btn-outline-danger remove-btn" @click="removePhoto(i)">Remove</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div v-if="!loading" class="d-flex gap-2 justify-content-end">
        <RouterLink to="/vehicles" class="btn btn-outline-secondary">Cancel</RouterLink>
        <button type="submit" class="btn btn-app-dark" :disabled="submitting">
          <span v-if="!submitting">Save Changes</span>
          <span v-else>Saving…</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const route = useRoute();
const router = useRouter();
const deviceId = route.params.deviceId;

const form = reactive({
  name: '',
  uniqueId: '',
  model: '',
  speedLimit: '',
  attributes: {
    vehicleNo: '',
    type: '',
    manufacturer: '',
    color: '',
    registration: '',
    plate: '',

    fuelAverage: '',
    fuelType: '',
    trackerModel: ''
  }
});

// Resolve assets from Laravel backend in dev; use current origin in prod
const assetBase = import.meta.env.DEV ? (import.meta.env.VITE_BACKEND_PROXY_TARGET || 'http://127.0.0.1:8001') : window.location.origin;
function toPhotoUrl(path) {
  if (!path) return null;
  if (/^https?:\/\//.test(path)) return path;
  const normalized = path.startsWith('/storage/') ? path : `/storage/${path}`;
  return assetBase + normalized;
}

const previews = ref([null, null, null]);
const blobs = ref([null, null, null]);
const existingPhotos = ref([null, null, null]);

const message = ref('');
const error = ref('');
const submitting = ref(false);
const loading = ref(true);
const trackerModels = ref(['Teltonika-FMC-003','Teltonika-FMC-150','Teltonika-FMC-130','Teltonika-FMC-920']);
const odometerOptions = ref([]);
const fuelOptions = ref([]);

function dismissError() { error.value = ''; }
function dismissMessage() { message.value = ''; }

onMounted(async () => {
  try {
    const { data } = await axios.get('/web/vehicles/models/options');
    const opts = Array.isArray(data?.options) ? data.options : [];
    const names = opts
      .map(o => (typeof o === 'string' ? o : (o.modelname || o.name || '')))
      .filter(Boolean);
    if (names.length > 0) trackerModels.value = names;
  } catch {}

  // Fallback: if still static and admin list is available
  if (trackerModels.value && trackerModels.value.length && trackerModels.value[0].includes('Teltonika-')) {
    try {
      const { data } = await axios.get('/web/settings/vehicle-models');
      const rows = Array.isArray(data?.models) ? data.models : [];
      const names = rows.map(r => r.modelname).filter(Boolean);
      if (names.length > 0) trackerModels.value = names;
    } catch {}
  }

  try {
    const { data } = await axios.get(`/web/vehicles/${deviceId}`);
    const tc = data?.tc_device;
    if (tc) {
      hydrateFormFromTc(tc);
      if (form.attributes.trackerModel) {
        refreshModelAttributes(form.attributes.trackerModel);
      }
      loading.value = false;
    } else {
      loading.value = false;
      router.replace({ path: '/vehicles', query: { error: 'Vehicle data not available for this device id.' } });
    }
  } catch (e) {
    const status = e?.response?.status;
    let msg = '';
    if (status === 404) {
      msg = 'Vehicle not found for the given device id.';
    } else if (status === 403) {
      msg = 'You do not have permission to edit this vehicle.';
    } else {
      msg = e?.response?.data?.message || 'Failed to load vehicle.';
    }
    loading.value = false;
    router.replace({ path: '/vehicles', query: { error: msg } });
  }
});

async function refreshModelAttributes(modelName) {
  odometerOptions.value = [];
  fuelOptions.value = [];
  if (!modelName) return;
  try {
    const { data } = await axios.get('/web/vehicles/models/options');
    const rows = Array.isArray(data?.models) ? data.models : [];
    const row = rows.find(r => String(r.modelname || '').trim() === String(modelName || '').trim());
    if (row && row.attributes && typeof row.attributes === 'object') {
      const attrs = row.attributes;
      const odo = Array.isArray(attrs.odometer) ? attrs.odometer.map(i => i.name).filter(Boolean) : [];
      const fuel = Array.isArray(attrs.fuel) ? attrs.fuel.map(i => i.name).filter(Boolean) : [];
      odometerOptions.value = odo;
      fuelOptions.value = fuel;
      if (!form.attributes.odometerAttr && odo.length) form.attributes.odometerAttr = odo[0];
      if (!form.attributes.fuelAttr && fuel.length) form.attributes.fuelAttr = fuel[0];
    }
  } catch {}
}

watch(() => form.attributes.trackerModel, (val) => {
  form.attributes.odometerAttr = '';
  form.attributes.fuelAttr = '';
  if (val) {
    refreshModelAttributes(val);
  } else {
    odometerOptions.value = [];
    fuelOptions.value = [];
  }
});

function parseAttrsMaybe(attr) {
  if (!attr) return {};
  if (typeof attr === 'string') {
    try { return JSON.parse(attr); } catch { return {}; }
  }
  return { ...attr };
}

function hydrateFormFromTc(tc) {
  const attrs = parseAttrsMaybe(tc.attributes);
  form.name = tc.name || '';
  form.uniqueId = tc.uniqueId || tc.uniqueid || '';
  form.model = tc.model || '';
  form.attributes.vehicleNo = attrs.vehicleNo || '';
  form.attributes.type = attrs.type || '';
  form.attributes.manufacturer = attrs.manufacturer || '';
  form.attributes.color = attrs.color || '';
  form.attributes.registration = attrs.registration || '';
  form.attributes.plate = attrs.plate || attrs.licensePlate || attrs.plateNumber || '';

  {
    const fa = attrs.fuelAverage;
    if (fa === null || fa === undefined || fa === '') {
      form.attributes.fuelAverage = '';
    } else if (typeof fa === 'number') {
      form.attributes.fuelAverage = fa;
    } else {
      const m = String(fa).match(/\d+/);
      form.attributes.fuelAverage = m ? parseInt(m[0], 10) : '';
    }
  }
  form.attributes.fuelType = (
    attrs.fuelType || attrs.fuel_type || attrs.FuelType || attrs.fueltype || ''
  );
  form.speedLimit = attrs.speedLimit || '';
  form.attributes.trackerModel = attrs.trackerModel || attrs.deviceModel || attrs.gpsModel || attrs.teltonikaModel || tc.model || '';
  form.attributes.fuelTankCapacity = attrs.fuelTankCapacity || attrs.FuelTankCapacity || attrs.fueltankcapacity || '';
  form.attributes.odometerAttr = attrs.odometerAttr || attrs.odometer_attribute || '';
  form.attributes.fuelAttr = attrs.fuelAttr || attrs.fuel_attribute || '';

  // Hydrate previews from existing attributes.photos if present
  const photosArr = Array.isArray(attrs.photos)
    ? attrs.photos
    : (typeof attrs.photos === 'string' ? [attrs.photos] : []);
  existingPhotos.value = [photosArr[0] || null, photosArr[1] || null, photosArr[2] || null];
  previews.value = existingPhotos.value.map(p => p ? toPhotoUrl(p) : null);
}

function onFile(i, e) {
  const file = e.target.files?.[0];
  if (!file) return;
  const ok = /image\/(png|jpeg)/.test(file.type);
  if (!ok) { alert('Only PNG/JPEG images allowed'); return; }
  if (previews.value[i] && previews.value[i].startsWith('blob:')) URL.revokeObjectURL(previews.value[i]);
  previews.value[i] = URL.createObjectURL(file);
  blobs.value[i] = file;
  // New upload replaces any existing photo in this slot
  existingPhotos.value[i] = null;
}

function removePhoto(i) {
  if (previews.value[i] && previews.value[i].startsWith('blob:')) {
    URL.revokeObjectURL(previews.value[i]);
  }
  previews.value[i] = null;
  blobs.value[i] = null;
  existingPhotos.value[i] = null;
}

async function submit() {
  message.value = '';
  error.value = '';

  // Validate numeric fields: max speed
  if (form.speedLimit !== '' && form.speedLimit !== null) {
    const msStr = String(form.speedLimit);
    if (!/^\d+$/.test(msStr)) {
      error.value = 'Max Speed must be numeric and >= 0';
      return;
    }
  }

  // Validate numeric fields: registration
  if (form.attributes.registration !== '' && form.attributes.registration !== null) {
    const regStr = String(form.attributes.registration);
    if (!/^\d+$/.test(regStr)) {
      error.value = 'Registration Number must be numeric and >= 0';
      return;
    }
  }

  // Validate numeric fields: fuelAverage (integer)
  if (form.attributes.fuelAverage !== '' && form.attributes.fuelAverage !== null) {
    const fa = form.attributes.fuelAverage;
    if (!Number.isInteger(fa) || fa < 0) {
      error.value = 'Fuel Average must be an integer and >= 0';
      return;
    }
  }

  submitting.value = true;
  try {
    const fd = new FormData();
    fd.append('name', form.name?.trim() || '');
    fd.append('uniqueId', form.uniqueId?.trim() || '');
    if (form.model) fd.append('model', form.model?.trim());
    const attrsOut = { ...form.attributes };
    if (attrsOut.fuelType && !attrsOut.fuel_type) attrsOut.fuel_type = attrsOut.fuelType;
    const keptPhotos = existingPhotos.value.filter(Boolean);
    attrsOut.photos = keptPhotos;
    attrsOut.speedLimit = form.speedLimit ?? '';
    fd.append('attributes', JSON.stringify(attrsOut));
    blobs.value.forEach((file, i) => { if (file) fd.append(`images[${i}]`, file); });
    // Use POST with method override to ensure Laravel parses multipart fields/files
    fd.append('_method', 'PUT');

    const { data } = await axios.post(`/web/vehicles/${deviceId}`, fd);
    message.value = 'Vehicle updated successfully.';
    setTimeout(() => router.push('/vehicles'), 600);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to update vehicle.';
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.card-header h6 { font-weight: 600; }
.btn-app-dark { background-color: #0b0f28; color: #fff; }
.upload-wrapper { position: relative; }
.upload-box { border: 2px dashed #cfd6e4; border-radius: .75rem; height: 220px; display:flex; align-items:center; justify-content:center; background:#f8fafc; cursor:pointer; }
.upload-box.has-image { border-style: solid; background:#fff; }
.upload-empty { text-align:center; color:#2b2f4a; }
.upload-img { width: 100%; height: 100%; object-fit: cover; border-radius: .75rem; }
.remove-btn { position: absolute; top: .5rem; right: .5rem; z-index: 2; }
xsmall { font-size: .75rem; }
</style>
