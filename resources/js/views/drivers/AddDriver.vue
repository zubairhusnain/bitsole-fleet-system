<template>
  <div class="add-driver-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/drivers">Driver Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Add New Driver</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">Add New Driver</h4>

    <form @submit.prevent="submit">
      <!-- Personal Information -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Personal Information</h6></div>
        <div class="card-body">
          <div class="row g-3 align-items-start">
            <div class="col-12 col-md-3">
              <label class="form-label small">Driver ID</label>
              <input v-model="form.driverId" type="text" class="form-control" placeholder="DRV-1016" />
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
              <input v-model="form.dob" type="text" class="form-control" placeholder="dd/mm/yyyy" />
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label small">ID Card Number</label>
              <input v-model="form.idCard" type="text" class="form-control" placeholder="ID Card Number" />
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label small">Passport Number</label>
              <input v-model="form.passport" type="text" class="form-control" placeholder="Passport Number" />
            </div>

            <div class="col-12">
              <div class="form-check mt-2">
                <input v-model="form.healthOk" class="form-check-input" type="checkbox" id="healthOk">
                <label class="form-check-label small" for="healthOk">
                  This Person do not have any medical condition and fit to drive safely.
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact Information -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Contact Information</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label small">Email Address</label>
              <input v-model="form.email" type="email" class="form-control" placeholder="Email Address" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Phone Number</label>
              <input v-model="form.phone" type="text" class="form-control" placeholder="Phone Number" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Address</label>
              <input v-model="form.address" type="text" class="form-control" placeholder="Home Address" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Telephone</label>
              <input v-model="form.telephone" type="text" class="form-control" placeholder="Telephone number" />
            </div>
          </div>
        </div>
      </div>

      <!-- Driving License Information -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Driving License Information</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small">Licence Image</label>
              <div class="input-group">
                <input class="form-control" type="file" @change="onFile" />
                <span class="input-group-text">Browse Files</span>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">License Number</label>
              <input v-model="form.licence" type="text" class="form-control" placeholder="License Number" />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label small">Expiry Date</label>
              <input v-model="form.expiry" type="text" class="form-control" placeholder="dd/mm/yyyy" />
            </div>
          </div>
        </div>
      </div>

      <!-- Assigned Vehicle -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Assigned Vehicle</h6></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small">Assigned Vehicle</label>
              <select v-model="form.vehicle" class="form-select">
                <option value="">-- Select Vehicle --</option>
                <option value="Phantom Racer 9 Pro">Phantom Racer 9 Pro</option>
                <option value="Racer X 2020">Racer X 2020</option>
                <option value="TurboMax Z">TurboMax Z</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="d-flex align-items-center justify-content-end gap-2">
        <RouterLink to="/drivers" class="btn btn-outline-secondary">Cancel</RouterLink>
        <button type="submit" class="btn btn-app-dark">Add Driver</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive } from 'vue';

const form = reactive({
  driverId: 'DRV-1016',
  fullName: '',
  gender: '',
  dob: '',
  idCard: '',
  passport: '',
  healthOk: false,
  email: '',
  phone: '',
  address: '',
  telephone: '',
  licence: '',
  expiry: '',
  vehicle: '',
  licenceImage: null,
});

function onFile(event) {
  form.licenceImage = event.target.files?.[0] || null;
}

function submit() {
  // For now, just log; later integrate API
  console.log('Add Driver form submitted', JSON.parse(JSON.stringify(form)));
}
</script>

<style scoped>
.input-w-360 { width: 360px; }
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>