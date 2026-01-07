<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item"><RouterLink to="/reports/incident-analysis">Incident Analysis Report</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Create New Incident Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Create New Incident Report</h4>

    <!-- Alerts -->
    <div v-if="alert.message" :class="`alert alert-${alert.type} alert-dismissible fade show`" role="alert">
      {{ alert.message }}
      <button type="button" class="btn-close" @click="alert.message = ''"></button>
    </div>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-body p-4">
        <div class="row g-3">
          <!-- Row 1: Vehicle & Driver -->
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Vehicle</label>
            <select class="form-select text-muted" v-model="form.deviceId" @change="onDeviceChange">
              <option value="">-- Select Vehicle --</option>
              <option v-for="opt in deviceOptions" :key="opt.id" :value="opt.id">{{ opt.name }}</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Type/Model</label>
            <select class="form-select text-muted" v-model="form.typeModel">
              <option value="">-- Select Type --</option>
              <option value="Collision">Collision</option>
              <option value="Overspeed">Overspeed</option>
              <option value="Harsh Braking">Harsh Braking</option>
              <option value="Geofence Exit">Geofence Exit</option>
              <option value="Geofence Enter">Geofence Enter</option>
              <option value="Idling">Idling</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Driver</label>
            <input type="text" class="form-control text-muted" placeholder="Enter Driver Name" v-model="form.driverId" />
          </div>

          <!-- Row 2: Incident Start & Incident End -->
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold text-muted">Incident Start</label>
            <div class="input-group">
              <input type="datetime-local" class="form-control text-muted" v-model="form.incidentStart" />
            </div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold text-muted">Incident End</label>
            <div class="input-group">
              <input type="datetime-local" class="form-control text-muted" v-model="form.incidentEnd" />
              <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
            </div>
          </div>

          <!-- Row 3: Impact Date/Time -->
          <div class="col-12">
            <label class="form-label small fw-semibold text-muted">Impact Date/Time</label>
            <input type="datetime-local" class="form-control text-muted" v-model="form.impactTime" />
          </div>

          <!-- Row 4: Description -->
          <div class="col-12">
            <label class="form-label small fw-semibold text-muted">Description</label>
            <textarea class="form-control text-muted" rows="4" placeholder="write description here....." v-model="form.description"></textarea>
          </div>

          <!-- Row 5: Remarks -->
          <div class="col-12">
            <label class="form-label small fw-semibold text-muted">Remarks</label>
            <textarea class="form-control text-muted" rows="4" placeholder="write your remarks here....." v-model="form.remarks"></textarea>
          </div>
        </div>

        <!-- Buttons -->
        <div class="d-flex justify-content-end gap-2 mt-4">
          <RouterLink to="/reports/incident-analysis" class="btn btn-secondary px-4">Cancel</RouterLink>
          <button class="btn btn-info text-white px-4" @click="save">Save Only</button>
          <button class="btn btn-dark px-4" @click="saveAndAdd">Save & Add More</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();

const deviceOptions = ref([]);
const alert = ref({ message: '', type: '' });

const form = ref({
  deviceId: '',
  vehicleId: '', // Label
  typeModel: '',
  driverId: '',
  incidentStart: '',
  incidentEnd: '',
  impactTime: '',
  description: '',
  remarks: ''
});

onMounted(() => {
  loadDeviceOptions();
});

async function loadDeviceOptions() {
  try {
    const res = await axios.get('/web/reports/device-options');
    deviceOptions.value = res.data || [];
  } catch (e) {
    console.error('Failed to load device options', e);
  }
}

function onDeviceChange() {
  const selected = deviceOptions.value.find(d => d.id === form.value.deviceId);
  if (selected) {
    form.value.vehicleId = selected.name || 'Unknown';
  } else {
    form.value.vehicleId = '';
  }
}

function validate() {
  if (!form.value.deviceId) {
    alert.value = { message: 'Please select a vehicle.', type: 'danger' };
    return false;
  }
  if (!form.value.incidentStart) {
    alert.value = { message: 'Please select incident start time.', type: 'danger' };
    return false;
  }
  return true;
}

const save = () => {
  if (!validate()) return;
  axios.post('/web/reports/incidents', form.value)
    .then(() => {
      router.push('/reports/incident-analysis');
    })
    .catch((e) => {
      console.error('Failed to save incident', e);
      alert.value = { message: 'Failed to save incident.', type: 'danger' };
    });
};

const saveAndAdd = () => {
  if (!validate()) return;
  axios.post('/web/reports/incidents', form.value)
    .then(() => {
      alert.value = { message: 'Incident saved successfully.', type: 'success' };
      // Reset form but keep maybe vehicle? No, clear all usually.
      form.value = {
        deviceId: '',
        vehicleId: '',
        typeModel: '',
        driverId: '',
        incidentStart: '',
        incidentEnd: '',
        impactTime: '',
        description: '',
        remarks: ''
      };
      // Scroll to top
      window.scrollTo(0, 0);
    })
    .catch((e) => {
      console.error('Failed to save incident', e);
      alert.value = { message: 'Failed to save incident.', type: 'danger' };
    });
};
</script>

<style scoped>
.form-label {
  font-size: 0.85rem;
  margin-bottom: 0.4rem;
}
textarea {
  resize: none;
}
/* Calendar icon fix if needed */
input[type="datetime-local"]::-webkit-calendar-picker-indicator {
  background: transparent;
  bottom: 0;
  color: transparent;
  cursor: pointer;
  height: auto;
  left: 0;
  position: absolute;
  right: 0;
  top: 0;
  width: auto;
}
</style>
