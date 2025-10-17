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

    <form @submit.prevent="submit">
      <!-- Vehicle Information -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Vehicle Information</h6></div>
        <div class="card-body">
          <div class="row g-3 align-items-start">
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle ID</label>
              <input v-model="form.vehicleId" type="text" class="form-control" placeholder="VHCL-1016" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Name</label>
              <input v-model="form.name" type="text" class="form-control" placeholder="Vehicle Name" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Type</label>
              <select v-model="form.type" class="form-select">
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
              <input v-model="form.manufacturer" type="text" class="form-control" placeholder="Manufacturer" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Vehicle Color</label>
              <input v-model="form.color" type="text" class="form-control" placeholder="Vehicle Color" />
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small">Registration Number</label>
              <input v-model="form.registration" type="text" class="form-control" placeholder="Registration Number" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Plate Number</label>
              <input v-model="form.plate" type="text" class="form-control" placeholder="Plate Number" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">VIN Number</label>
              <input v-model="form.vin" type="text" class="form-control" placeholder="VIN Number" />
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label small">Odometer Reading</label>
              <input v-model="form.odometer" type="text" class="form-control" placeholder="Odometer Reading" />
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Fuel Average</label>
              <select v-model="form.fuelAverage" class="form-select">
                <option value="">--Select Information--</option>
                <option>5 km/l</option>
                <option>10 km/l</option>
                <option>15 km/l</option>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small">Max Speed</label>
              <input v-model="form.maxSpeed" type="text" class="form-control" placeholder="Max Speed" />
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
                <input type="file" accept="image/png,image/jpeg" class="d-none" @change="onFile(i, $event)" />
                <img v-if="preview" :src="preview" alt="Vehicle photo" class="upload-img" />
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
        <button type="submit" class="btn btn-app-dark">Add Vehicle</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, ref, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const form = reactive({
  vehicleId: 'VHCL-1016', name: '', type: '', model: '', manufacturer: '', color: '',
  registration: '', plate: '', vin: '', odometer: '', fuelAverage: '', maxSpeed: ''
});

const previews = ref([null, null, null]);
const blobs = ref([null, null, null]);

function onFile(i, e) {
  const file = e.target.files?.[0];
  if (!file) return;
  const ok = /image\/(png|jpeg)/.test(file.type);
  if (!ok) { alert('Only PNG/JPEG images allowed'); return; }
  if (previews.value[i]) URL.revokeObjectURL(previews.value[i]);
  previews.value[i] = URL.createObjectURL(file);
  blobs.value[i] = file;
}

onBeforeUnmount(() => {
  previews.value.forEach((url) => url && URL.revokeObjectURL(url));
});

function submit() {
  // Placeholder submit: integrate API later
  alert('Vehicle added (placeholder).');
  router.push('/vehicles');
}
</script>

<style scoped>
.card-header h6 { font-weight: 600; }
.btn-app-dark { background-color: #0b0f28; color: #fff; }
.upload-box { border: 2px dashed #cfd6e4; border-radius: .75rem; height: 220px; display:flex; align-items:center; justify-content:center; background:#f8fafc; cursor:pointer; }
.upload-box.has-image { border-style: solid; background:#fff; }
.upload-empty { text-align:center; color:#2b2f4a; }
.upload-img { width: 100%; height: 100%; object-fit: cover; border-radius: .75rem; }
.xsmall { font-size: .75rem; }
</style>