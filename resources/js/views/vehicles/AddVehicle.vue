<template>
  <div class="add-vehicle-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/vehicles">Vehicle Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Add New Vehicle</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">List New Vehicle</h4>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="dismissError" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="dismissMessage" />

    <form @submit.prevent="submit">
      <!-- Vehicle Information -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Vehicle Information</h6></div>
        <div class="card-body">
          <div class="row g-3 align-items-start">
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Name</label>
              <input v-model="form.name" type="text" class="form-control" placeholder="e.g. Toyota Camry" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle ID</label>
              <input v-model="form.attributes.vehicleNo" type="text" class="form-control" placeholder="e.g. V-001" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Model</label>
              <input v-model="form.model" type="text" class="form-control" placeholder="e.g. 2026" />
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
              <label class="form-label small">Fuel Type</label>
              <select v-model="form.attributes.fuelType" class="form-select">
                <option value="">-- Select Fuel Type --</option>
                <option>Diesel</option>
                <option>Petrol</option>
                <option>Electric Vehicle (EV)</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Fuel Average</label>
              <input v-model.number="form.attributes.fuelAverage" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="e.g. 10" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Fuel Tank Capacity (Liters)</label>
              <input v-model.number="form.attributes.fuelTankCapacity" type="number" min="0" step="0.1" inputmode="decimal" class="form-control" placeholder="e.g. 60" />
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
          </div>
        </div>
      </div>

      <!-- Tracking Device Information -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Tracking Device Information</h6></div>
        <div class="card-body">
          <div class="row g-3 align-items-start">
            <div class="col-12 col-md-6">
              <label class="form-label small">Device ID (IMEI)</label>
              <input v-model="form.uniqueId" type="text" class="form-control" placeholder="e.g. 123456789012345" />
            </div>
            <div class="col-12 col-md-6">
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
      <div class="card mb-3">
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
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Vehicle Photos</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div v-for="(preview, i) in previews" :key="i" class="col-12 col-md-4">
              <label class="upload-box w-100" :class="{ 'has-image': !!preview }">
                <input type="file" accept="image/png,image/jpeg" class="d-none" @change="onFile(i, $event)" :ref="el => fileRefs[i] = el" />
                <img v-if="preview" :src="preview" alt="Vehicle photo" class="upload-img" />
                <button v-if="preview" type="button" class="btn-remove" title="Remove image" @click.stop.prevent="removeFile(i)">
                  <i class="bi bi-x-lg"></i>
                </button>
                <div v-else class="upload-empty">
                  <i class="bi bi-cloud-arrow-up fs-3"></i>
                  <div class="small mt-1">Upload Vehicle Images</div>
                  <div class="text-muted xsmall">jpeg & png only</div>
                </div>
              </label>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="d-flex gap-2 justify-content-end">
        <RouterLink to="/vehicles" class="btn btn-outline-secondary">Cancel</RouterLink>
        <button type="submit" class="btn btn-app-dark" :disabled="submitting">
          <span v-if="!submitting">Add Vehicle</span>
          <span v-else>Submitting…</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref, onBeforeUnmount, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const router = useRouter();

const form = reactive({
  uniqueId: '', /** vehicleId **/
  name: '',
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
    trackerModel: '',
    fuelTankCapacity: ''
  },

});

const previews = ref([null, null, null]);
const blobs = ref([null, null, null]);
const fileRefs = ref([null, null, null]);
const message = ref('');
const error = ref('');
const submitting = ref(false);
const MAX_IMAGE_SIZE_MB = 16;
const trackerModels = ref(['Teltonika-FMC-003','Teltonika-FMC-150','Teltonika-FMC-130','Teltonika-FMC-920']);

function dismissError() { error.value = ''; }
function dismissMessage() { message.value = ''; }

function onFile(i, e) {
  const file = e.target.files?.[0];
  if (!file) return;
  const ok = /image\/(png|jpeg)/.test(file.type);
  if (!ok) { error.value = 'Only PNG/JPEG images allowed'; e.target.value=''; return; }
  if (file.size > MAX_IMAGE_SIZE_MB * 1024 * 1024) {
    error.value = `File too large (max ${MAX_IMAGE_SIZE_MB}MB)`;
    e.target.value = '';
    return;
  }
  if (previews.value[i]) URL.revokeObjectURL(previews.value[i]);
  previews.value[i] = URL.createObjectURL(file);
  blobs.value[i] = file;
}

function removeFile(i) {
  try {
    if (previews.value[i]) URL.revokeObjectURL(previews.value[i]);
  } catch {}
  previews.value[i] = null;
  blobs.value[i] = null;
  const input = fileRefs.value[i];
  if (input) input.value = '';
}

onBeforeUnmount(() => {
  previews.value.forEach((url) => url && URL.revokeObjectURL(url));
});

onMounted(async () => {
  try {
    const resp = await axios.get('/web/vehicles/models/options');
    const opts = Array.isArray(resp?.data?.options) ? resp.data.options : [];
    const names = opts
      .map(o => (typeof o === 'string' ? o : (o.modelname || o.name || '')))
      .filter(Boolean);
    if (names.length > 0) trackerModels.value = names;
  } catch {}
  await refreshModelAttributes(form.attributes.trackerModel);
});

const odometerOptions = ref([]);
const fuelOptions = ref([]);

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

async function submit() {
  message.value = '';
  error.value = '';

  // Basic client-side validation aligned with backend
  if (!form.name || !form.uniqueId) {
    error.value = 'Please provide both Vehicle Name and Vehicle ID.';
    return;
  }

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
    fd.append('name', form.name.trim());
    fd.append('uniqueId', form.uniqueId.trim());
    if (form.model) fd.append('model', form.model.trim());
    {
      const attrs = { ...form.attributes };
      if (attrs.fuelType && !attrs.fuel_type) attrs.fuel_type = attrs.fuelType;
      attrs.speedLimit = form.speedLimit ?? '';
      fd.append('attributes', JSON.stringify(attrs));
    }
    blobs.value.forEach((file, i) => { if (file) fd.append(`images[${i}]`, file); });

    const { data } = await axios.post('/web/vehicles', fd);
    message.value = 'Vehicle created successfully.';

    // Small delay to show success message, then navigate
    setTimeout(() => router.push('/vehicles'), 600);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to create vehicle.';
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.card-header h6 { font-weight: 600; }
.btn-app-dark { background-color: #0b0f28; color: #fff; }
.upload-box { border: 2px dashed #cfd6e4; border-radius: .75rem; height: 220px; display:flex; align-items:center; justify-content:center; background:#f8fafc; cursor:pointer; position: relative; }
.upload-box.has-image { border-style: solid; background:#fff; }
.upload-empty { text-align:center; color:#2b2f4a; }
.upload-img { width: 100%; height: 100%; object-fit: cover; border-radius: .75rem; }
.btn-remove { position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; border-radius: 50%; border: 1px solid #dee2e6; background: #fff; color: #212529; display:flex; align-items:center; justify-content:center; line-height: 1; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
.xsmall { font-size: .75rem; }
</style>
