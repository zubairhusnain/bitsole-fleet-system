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
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-body p-4">
        <div class="row g-3">
          <!-- Row 1: Vehicle & Driver -->
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Vehicle</label>
            <select class="form-select text-muted" v-model="form.deviceId" @change="onDeviceChange">
              <option value="">-- Select Vehicle --</option>
              <option v-for="opt in deviceOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
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
            <select class="form-select text-muted" v-model="form.driverId">
              <option value="">-- Select Driver --</option>
              <option v-for="drv in driverOptions" :key="drv.id" :value="drv.name">{{ drv.name }}</option>
            </select>
          </div>

          <!-- Row 2: Incident Start & Incident End -->
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold text-muted">Incident Start</label>
            <div class="input-group">
              <input type="datetime-local" class="form-control text-muted" v-model="form.incidentStart" />
              <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
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
            <div class="input-group">
              <input type="datetime-local" class="form-control text-muted" v-model="form.impactTime" />
              <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
            </div>
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
import UiAlert from '../../components/UiAlert.vue';

const router = useRouter();

const deviceOptions = ref([]);
const driverOptions = ref([]);
const message = ref('');
const error = ref('');

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
  loadDriverOptions();
});

async function loadDeviceOptions() {
  try {
    const res = await axios.get('/web/reports/device-options?includeAll=true');
    deviceOptions.value = res.data.options || res.data || [];
  } catch (e) {
    console.error('Failed to load device options', e);
  }
}

async function loadDriverOptions() {
  try {
    const res = await axios.get('/web/drivers');
    // API returns { drivers: [...] }
    const drivers = res.data.drivers || [];
    driverOptions.value = drivers.map(d => ({
      id: d.id,
      name: d.name || 'Unknown'
    }));
  } catch (e) {
    console.error('Failed to load driver options', e);
  }
}

function onDeviceChange() {
  // Convert to number for comparison since API returns number IDs but select value is string
  const id = Number(form.value.deviceId);
  const selected = deviceOptions.value.find(d => d.id === id);
  if (selected) {
    form.value.vehicleId = selected.label || selected.name || 'Unknown';
  } else {
    form.value.vehicleId = '';
  }
}

function validate() {
  error.value = '';
  message.value = '';
  if (!form.value.deviceId) {
    error.value = 'Please select a vehicle.';
    return false;
  }
  if (!form.value.incidentStart) {
    error.value = 'Please select incident start time.';
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
      error.value = e.response?.data?.message || 'Failed to save incident.';
    });
};

const saveAndAdd = () => {
  if (!validate()) return;
  axios.post('/web/reports/incidents', form.value)
    .then(() => {
      message.value = 'Incident saved successfully.';
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
    })
    .catch((e) => {
      console.error('Failed to save incident', e);
      error.value = e.response?.data?.message || 'Failed to save incident.';
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
