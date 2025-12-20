<template>
  <div class="content-wrapper">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/vehicles">Vehicle Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Maintenance</li>
      </ol>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />

    <div class="row">
      <!-- Maintenance Form Card -->
      <div class="col-md-12 mb-4" v-if="(isEditing && hasPerm('vehicles.maintenance', 'update')) || (!isEditing && hasPerm('vehicles.maintenance', 'create'))">
        <div class="card border rounded-3 shadow-0">
          <div class="card-header bg-white border-bottom">
            <h5 class="card-title mb-0">{{ isEditing ? 'Edit Maintenance' : 'Create Maintenance' }}</h5>
          </div>
          <div class="card-body bg-white">
            <form @submit.prevent="saveMaintenance">
              <div class="row g-3 align-items-end">
                <!-- Name -->
                <div class="col-md-2">
                  <label for="name" class="form-label">Name</label>
                  <input type="text" id="name" v-model="form.name" class="form-control" required placeholder="e.g. Oil Change">
                </div>

                <!-- Type -->
                <div class="col-md-2">
                  <label for="type" class="form-label">Type</label>
                  <select id="type" v-model="form.type" class="form-select" required>
                    <option value="" disabled>Select Type</option>
                    <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                      {{ option.label }}
                    </option>
                  </select>
                </div>

                <!-- Start -->
                <div class="col-md-2">
                  <label for="start" class="form-label">Start</label>
                  <input type="number" id="start" v-model="form.start" class="form-control" required step="any">
                </div>

                <!-- Period -->
                <div class="col-md-2">
                  <label for="period" class="form-label">Period</label>
                  <input type="number" id="period" v-model="form.period" class="form-control" required step="any">
                </div>

                <!-- Device Selection -->
                <div class="col-md-2">
                  <label for="deviceId" class="form-label">Assign To</label>
                  <select id="deviceId" v-model="form.deviceId" class="form-select">
                    <option value="">None</option>
                    <option value="all">All Devices</option>
                    <option v-for="device in devices" :key="device.id" :value="device.id">
                      {{ device.label }}
                    </option>
                  </select>
                </div>

                <!-- Actions -->
                <div class="col-md-2 d-flex align-items-end">
                  <button type="submit" class="btn btn-primary me-2" :disabled="saving">
                    <i class="bi bi-save me-1"></i> {{ isEditing ? 'Update' : 'Save' }}
                  </button>
                  <button type="button" class="btn btn-secondary" v-if="isEditing" @click="resetForm">
                    Cancel
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Maintenance List -->
      <div class="col-md-12">
        <div class="card border rounded-3 shadow-0">
          <div class="card-header bg-white border-bottom">
            <h5 class="card-title mb-0">Maintenance List</h5>
          </div>
          <div class="card-body p-0 bg-white">
            <div class="table-responsive">
              <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
                <thead class="thead-app-dark">
                  <tr>
                    <th class="fw-semibold py-2">Name</th>
                    <th class="fw-semibold py-2">Type</th>
                    <th class="fw-semibold py-2">Assigned To</th>
                    <th class="fw-semibold py-2">Start</th>
                    <th class="fw-semibold py-2">Period</th>
                    <th class="fw-semibold py-2 text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="5" class="text-center py-4 text-muted">Loading...</td>
                  </tr>
                  <tr v-else-if="maintenanceList.length === 0">
                    <td colspan="5" class="text-center py-4 text-muted">No maintenance records found.</td>
                  </tr>
                  <tr v-for="item in maintenanceList" :key="item.id">
                    <td>{{ item.name }}</td>
                    <td>{{ formatType(item.type) }}</td>
                    <td>
                      <span v-if="!item.deviceIds || item.deviceIds.length === 0" class="text-muted">None</span>
                      <span v-else-if="item.deviceIds.length >= devices.length && devices.length > 0" class="badge bg-info">All Devices</span>
                      <span v-else-if="item.deviceIds.length === 1">{{ getDeviceName(item.deviceIds[0]) }}</span>
                      <span v-else class="badge bg-secondary">{{ item.deviceIds.length }} Devices</span>
                    </td>
                    <td>{{ item.start }}</td>
                    <td>{{ item.period }}</td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-secondary me-1" v-if="hasPerm('vehicles.maintenance', 'update')" @click="editMaintenance(item)">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger" v-if="hasPerm('vehicles.maintenance', 'delete')" @click="deleteMaintenance(item.id)">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import UiAlert from '../../components/UiAlert.vue';
import { hasPermission as _hasPermission } from '../../auth';

const hasPerm = (k, a) => _hasPermission(k, a);

const maintenanceList = ref([]);
const devices = ref([]);
const loading = ref(false);
const saving = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const error = ref('');

const typeOptions = [
  { value: "id", label: "Identifier" },
  { value: "latitude", label: "Latitude" },
  { value: "longitude", label: "Longitude" },
  { value: "speed", label: "Speed" },
  { value: "course", label: "Course" },
  { value: "altitude", label: "Altitude" },
  { value: "accuracy", label: "Accuracy" },
  { value: "deviceTime", label: "Device Time" },
  { value: "fixTime", label: "Fix Time" },
  { value: "serverTime", label: "Server Time" },
  { value: "index", label: "Index" },
  { value: "hdop", label: "HDOP" },
  { value: "vdop", label: "VDOP" },
  { value: "pdop", label: "PDOP" },
  { value: "sat", label: "Satellites" },
  { value: "satVisible", label: "Visible Satellites" },
  { value: "rssi", label: "RSSI" },
  { value: "coolantTemp", label: "Coolant Temperature" },
  { value: "engineTemp", label: "Engine Temperature" },
  { value: "gps", label: "GPS" },
  { value: "odometer", label: "Odometer" },
  { value: "serviceOdometer", label: "Service Odometer" },
  { value: "tripOdometer", label: "Trip Odometer" },
  { value: "hours", label: "Hours" },
  { value: "steps", label: "Steps" },
  { value: "heartRate", label: "Heart Rate" },
  { value: "input", label: "Input" },
  { value: "output", label: "Output" },
  { value: "power", label: "Power" },
  { value: "battery", label: "Battery" },
  { value: "batteryLevel", label: "Battery Level" },
  { value: "fuel", label: "Fuel" },
  { value: "fuelUsed", label: "Fuel Used" },
  { value: "fuelConsumption", label: "Fuel Consumption" },
  { value: "distance", label: "Distance" },
  { value: "totalDistance", label: "Total Distance" },
  { value: "rpm", label: "RPM" },
  { value: "throttle", label: "Throttle" },
  { value: "acceleration", label: "Acceleration" },
  { value: "humidity", label: "Humidity" },
  { value: "deviceTemp", label: "Device Temperature" },
  { value: "temp1", label: "Temperature 1" },
  { value: "temp2", label: "Temperature 2" },
  { value: "temp3", label: "Temperature 3" },
  { value: "temp4", label: "Temperature 4" },
  { value: "obdSpeed", label: "OBD Speed" },
  { value: "obdOdometer", label: "OBD Odometer" },
  { value: "drivingTime", label: "Driving Time" }
];

const form = reactive({
  name: '',
  type: '',
  start: 0,
  period: 0,
  deviceId: 'all' // Default to all
});

onMounted(() => {
  fetchMaintenance();
  fetchDevices();
});

const fetchMaintenance = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/web/vehicles/maintenance');
    maintenanceList.value = response.data;
  } catch (err) {
    error.value = 'Failed to load maintenance records';
    console.error('Error fetching maintenance:', err);
    error.value = 'Failed to fetch maintenance records. Please try again.';
  } finally {
    loading.value = false;
  }
};

const fetchDevices = async () => {
  try {
    const response = await axios.get('/web/vehicles/maintenance/vehicle/options');
    // Response is directly the array of options
    if (Array.isArray(response.data)) {
      devices.value = response.data;
    } else {
      devices.value = [];
    }
  } catch (err) {
    console.error('Error fetching devices:', err);
  }
};

const formatType = (type) => {
  const option = typeOptions.find(opt => opt.value === type);
  return option ? option.label : type;
};

const getDeviceName = (id) => {
  const device = devices.value.find(d => d.id === id || d.deviceId === id);
  return device ? device.label : `Device #${id}`;
};

const saveMaintenance = async () => {
  saving.value = true;
  error.value = '';
  try {
    if (isEditing.value) {
      await axios.put(`/web/vehicles/maintenance/${editingId.value}`, form);
    } else {
      await axios.post('/web/vehicles/maintenance', form);
    }
    await fetchMaintenance();
    // Also re-fetch devices to update maintenanceIds in the list (if we rely on them for future edits)
    await fetchDevices();
    resetForm();
    Swal.fire('Success', 'Maintenance updated successfully', 'success');
  } catch (err) {
    console.error('Error saving maintenance:', err);
    error.value = 'Failed to save maintenance. Please check your inputs and try again.';
  } finally {
    saving.value = false;
  }
};

const editMaintenance = (item) => {
  isEditing.value = true;
  editingId.value = item.id;
  form.name = item.name;
  form.type = item.type;
  form.start = item.start;
  form.period = item.period;

  // Use deviceIds from API response
  const assignedIds = item.deviceIds || [];

  if (assignedIds.length === 0) {
      form.deviceId = '';
  } else if (assignedIds.length >= devices.value.length && devices.value.length > 0) {
      // Heuristic: if assigned count matches total device count, assume 'all'
      // Note: This isn't perfect if devices list changes, but 'all' is a special UI state
      form.deviceId = 'all';
  } else if (assignedIds.length === 1) {
      form.deviceId = assignedIds[0];
  } else {
      // If multiple devices but not all, default to 'all' or handle as multi-select
      // Since UI only has single select or 'all', we default to 'all' or the first one?
      // Given the logic in store/update, 'all' is safer to avoid unassigning others
      form.deviceId = 'all';
  }
};

const deleteMaintenance = async (id) => {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/web/vehicles/maintenance/${id}`);
      Swal.fire('Deleted!', 'Maintenance record has been deleted.', 'success');
      fetchMaintenance();
    } catch (err) {
      console.error('Error deleting maintenance:', err);
      Swal.fire('Error', 'Failed to delete maintenance', 'error');
    }
  }
};

const resetForm = () => {
  isEditing.value = false;
  editingId.value = null;
  form.name = '';
  form.type = '';
  form.start = 0;
  form.period = 0;
  form.deviceId = 'all';
  error.value = '';
};
</script>
