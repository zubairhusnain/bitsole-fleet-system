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
              <label class="form-label small">Device ID ( IMEI )</label>
              <input v-model="form.uniqueId" type="text" class="form-control" placeholder="VHCL-1016" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Tracker Model</label>
              <select v-model="form.attributes.trackerModel" class="form-select">
                <option value="">-- Select Tracker Model --</option>
                <option>Teltonika-FMC-003</option>
                <option>Teltonika-FMC-150</option>
                <option>Teltonika-FMC-130</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Device Name</label>
              <input v-model="form.name" type="text" class="form-control" placeholder="Device Name" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Type</label>
              <select v-model="form.attributes.type" class="form-select">
                <option value="">-- Select Option --</option>
                <option>Sedan</option>
                <option>SUV</option>
                <option>Truck</option>
                <option>Van</option>
              </select>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small">Model</label>
              <input v-model="form.model" type="text" class="form-control" placeholder="Model" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Manufacturer</label>
              <input v-model="form.attributes.manufacturer" type="text" class="form-control" placeholder="Manufacturer" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Color</label>
              <input v-model="form.attributes.color" type="text" class="form-control" placeholder="Vehicle Color" />
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small">Registration Number</label>
              <input v-model="form.attributes.registration" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="Registration Number" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Plate Number</label>
              <input v-model="form.attributes.plate" type="text" class="form-control" placeholder="Plate Number" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">VIN Number</label>
              <input v-model="form.attributes.vin" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="VIN Number" />
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small">Odometer Reading</label>
              <input v-model="form.attributes.odometer" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="Odometer Reading" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Fuel Average</label>
              <select v-model="form.attributes.fuelAverage" class="form-select">
                <option value="">--Select Information--</option>
                <option>5 km/l</option>
                <option>10 km/l</option>
                <option>15 km/l</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Max Speed</label>
              <input v-model="form.attributes.maxSpeed" type="number" min="0" step="1" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="Max Speed" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Fuel Tank Capacity (Liters)</label>
              <input v-model.number="form.attributes.fuelTankCapacity" type="number" min="0" step="0.1" inputmode="decimal" class="form-control" placeholder="e.g. 60" />
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
import { reactive, ref, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const router = useRouter();

const form = reactive({
  uniqueId: 'VHCL-1016', /** vehicleId **/
  name: '',
  model: '',
  attributes: {
    type: '',
    manufacturer: '',
    color: '',
    registration: '',
    plate: '',
    vin: '',
    odometer: '',
    fuelAverage: '',
    maxSpeed: '',
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

async function submit() {
  message.value = '';
  error.value = '';

  // Basic client-side validation aligned with backend
  if (!form.name || !form.uniqueId) {
    error.value = 'Please provide both Vehicle Name and Vehicle ID.';
    return;
  }

  // Validate numeric fields: odometer and maxSpeed
  if (form.attributes.odometer !== '' && form.attributes.odometer !== null) {
    const odStr = String(form.attributes.odometer);
    if (!/^\d+$/.test(odStr)) {
      error.value = 'Odometer Reading must be numeric and >= 0';
      return;
    }
  }
  if (form.attributes.maxSpeed !== '' && form.attributes.maxSpeed !== null) {
    const msStr = String(form.attributes.maxSpeed);
    if (!/^\d+$/.test(msStr)) {
      error.value = 'Max Speed must be numeric and >= 0';
      return;
    }
  }

  // Validate numeric fields: registration and vin
  if (form.attributes.registration !== '' && form.attributes.registration !== null) {
    const regStr = String(form.attributes.registration);
    if (!/^\d+$/.test(regStr)) {
      error.value = 'Registration Number must be numeric and >= 0';
      return;
    }
  }
  if (form.attributes.vin !== '' && form.attributes.vin !== null) {
    const vinStr = String(form.attributes.vin);
    if (!/^\d+$/.test(vinStr)) {
      error.value = 'VIN Number must be numeric and >= 0';
      return;
    }
  }

  submitting.value = true;
  try {
    const fd = new FormData();
    fd.append('name', form.name.trim());
    fd.append('uniqueId', form.uniqueId.trim());
    if (form.model) fd.append('model', form.model.trim());
    fd.append('attributes', JSON.stringify({ ...form.attributes }));
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
