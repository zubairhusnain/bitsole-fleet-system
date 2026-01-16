<template>
  <div class="content-wrapper">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/vehicles">Vehicle Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Alert</li>
      </ol>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />

    <div class="row">
      <!-- Maintenance Form Card -->
      <div class="col-md-12 mb-4" v-if="(isEditing && hasPerm('vehicles.maintenance', 'update')) || (!isEditing && hasPerm('vehicles.maintenance', 'create'))">
        <div class="card border rounded-3 shadow-0">
          <div class="card-header bg-white border-bottom">
            <h5 class="card-title mb-0">{{ isEditing ? 'Edit Vehicle Alert' : 'Create Vehicle Alert' }}</h5>
          </div>
          <div class="card-body bg-white">
            <form @submit.prevent="saveMaintenance">
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="name" class="form-label">Name</label>
                  <input
                    type="text"
                    id="name"
                    v-model="form.name"
                    class="form-control"
                    required
                    placeholder="e.g. Overspeed Alert"
                  > 
                </div>
                <div class="col-md-6">
                  <label for="type" class="form-label">Type</label>
                  <div class="alert-type-control">
                    <input type="hidden" id="type" :value="form.type">
                    <div class="alert-type-toggle" @click="toggleTypeDropdown">
                      <span v-if="selectedType" class="alert-type-selected-text">{{ selectedType.label }}</span>
                      <span v-else class="alert-type-placeholder">Select Type</span>
                      <i class="bi" :class="typeDropdownOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                    </div>
                    <div v-if="typeDropdownOpen" class="alert-type-menu" @click.stop>
                      <div v-for="group in typeGroups" :key="group.name" class="alert-type-group">
                        <div class="alert-type-group-title">{{ group.name }}</div>
                        <div
                          v-for="option in group.options"
                          :key="option.value"
                          class="alert-type-option"
                          :class="{ 'is-selected': form.type === option.value }"
                          @click.stop="selectType(option.value)"
                        >
                          <span class="alert-type-radio">
                            <span v-if="form.type === option.value" class="alert-type-radio-dot"></span>
                          </span>
                          <span class="alert-type-option-label">{{ option.label }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="row g-3 align-items-end mt-1">
                <div class="col-md-3">
                  <label for="start" class="form-label">Start</label>
                  <input
                    v-if="isDateType"
                    type="date"
                    id="start"
                    v-model="startDate"
                    class="form-control"
                    required
                    :placeholder="typePlaceholders.start"
                  >
                  <input
                    v-else
                    type="number"
                    id="start"
                    v-model="form.start"
                    class="form-control"
                    required
                    :placeholder="typePlaceholders.start"
                    step="any"
                  >
                </div>

                <div class="col-md-3">
                  <label for="period" class="form-label">Period</label>
                  <input
                    type="number"
                    id="period"
                    v-model="form.period"
                    class="form-control"
                    required
                    step="any"
                    :placeholder="typePlaceholders.period"
                  >
                </div>

                <div class="col-md-3">
                  <label for="deviceId" class="form-label">Assign To</label>
                  <select id="deviceId" v-model="form.deviceId" class="form-select">
                    <option value="">None</option>
                    <option value="all">All Devices</option>
                    <option v-for="device in devices" :key="device.id" :value="device.id">
                      {{ device.label }}
                    </option>
                  </select>
                </div>

                <div class="col-md-3 d-flex align-items-end justify-content-md-end">
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
            <h5 class="card-title mb-0">Vehicle Alert List</h5>
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
                    <td colspan="5" class="text-center py-4 text-muted">No alerts found.</td>
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
import { ref, onMounted, reactive, computed, watch } from 'vue';
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

const typeGroups = [
  {
    name: "Driving Behavior Alerts",
    options: [
      { value: "speed", label: "Speed" },
      { value: "rpm", label: "RPM" },
      { value: "throttle", label: "Throttle" },
      { value: "acceleration", label: "Acceleration" },
      { value: "course", label: "Course" },
      { value: "drivingTime", label: "Driving Time" }
    ]
  },
  {
    name: "Fuel Alerts",
    options: [
      { value: "fuel", label: "Fuel" },
      { value: "fuelUsed", label: "Fuel Used" },
      { value: "fuelConsumption", label: "Fuel Consumption" }
    ]
  },
  {
    name: "Engine & Health Alerts",
    options: [
      { value: "engineTemp", label: "Engine Temperature" },
      { value: "coolantTemp", label: "Coolant Temperature" },
      { value: "deviceTemp", label: "Device Temperature" }
    ]
  },
  {
    name: "Electrical & Power Alerts",
    options: [
      { value: "battery", label: "Battery" },
      { value: "batteryLevel", label: "Battery Level" },
      { value: "power", label: "Power" },
      { value: "input", label: "Input" },
      { value: "output", label: "Output" }
    ]
  },
  {
    name: "GPS & Signal Alerts",
    options: [
      { value: "gps", label: "GPS" },
      { value: "accuracy", label: "Accuracy" },
      { value: "hdop", label: "HDOP" },
      { value: "pdop", label: "PDOP" },
      { value: "sat", label: "Satellites" },
      { value: "rssi", label: "RSSI" },
      { value: "latitude", label: "Latitude" },
      { value: "longitude", label: "Longitude" }
    ]
  }
];

const typeOptions = typeGroups.flatMap(g => g.options);
const alertTypeValues = typeOptions.map(o => o.value);

const form = reactive({
  name: '',
  type: '',
  start: '',
  period: '',
  deviceId: 'all'
});

const typeDropdownOpen = ref(false);
const selectedType = computed(() => typeOptions.find(opt => opt.value === form.type) || null);

const isDateType = computed(() => form.type === 'time');
const startDate = ref('');

const typePlaceholders = computed(() => {
  switch (form.type) {
    case 'speed':
      return { start: 'Max speed km/h (e.g. 100)', period: 'Margin km/h (e.g. 10)' };
    case 'rpm':
      return { start: 'Max RPM (e.g. 4500)', period: 'Margin (e.g. 500)' };
    case 'throttle':
      return { start: 'Throttle % (e.g. 80)', period: 'Margin % (e.g. 10)' };
    case 'acceleration':
      return { start: 'Acceleration (e.g. 5)', period: 'Margin (e.g. 1)' };
    case 'course':
      return { start: 'Course change ° (e.g. 45)', period: '' };
    case 'drivingTime':
      return { start: 'Driving time h (e.g. 8)', period: 'Margin h (e.g. 1)' };
    case 'fuel':
      return { start: 'Fuel % (e.g. 15)', period: 'Margin % (e.g. 5)' };
    case 'fuelUsed':
      return { start: 'Fuel used L (e.g. 20)', period: 'Margin L (e.g. 5)' };
    case 'fuelConsumption':
      return { start: 'Consumption (e.g. 8)', period: 'Margin (e.g. 2)' };
    case 'engineTemp':
      return { start: 'Engine temp °C (e.g. 105)', period: 'Margin °C (e.g. 5)' };
    case 'coolantTemp':
      return { start: 'Coolant temp °C (e.g. 90)', period: 'Margin °C (e.g. 5)' };
    case 'deviceTemp':
      return { start: 'Device temp °C (e.g. 70)', period: 'Margin °C (e.g. 5)' };
    case 'battery':
      return { start: 'Battery state (e.g. 0)', period: '' };
    case 'batteryLevel':
      return { start: 'Battery % (e.g. 20)', period: 'Margin % (e.g. 5)' };
    case 'power':
      return { start: 'Power state (e.g. 0)', period: '' };
    case 'input':
    case 'output':
      return { start: 'IO state (e.g. 1)', period: '' };
    case 'gps':
      return { start: 'GPS status (e.g. 0)', period: '' };
    case 'accuracy':
    case 'hdop':
    case 'pdop':
      return { start: 'Value (e.g. 5)', period: '' };
    case 'sat':
      return { start: 'Satellites (e.g. 4)', period: '' };
    case 'rssi':
      return { start: 'RSSI dBm (e.g. -90)', period: '' };
    case 'latitude':
    case 'longitude':
      return { start: 'Threshold (e.g. geofence)', period: '' };
    case 'time':
      return { start: 'Select date', period: 'days(0)' };
    default:
      return { start: '', period: '' };
  }
});

const dateStrFromDays = (days) => {
  const n = Number(days);
  if (!Number.isFinite(n) || n <= 0) return '';
  const d = new Date(n * 86400000);
  if (Number.isNaN(d.getTime())) return '';
  return d.toISOString().slice(0, 10);
};

const daysFromDateStr = (str) => {
  if (!str) return 0;
  const d = new Date(str);
  if (Number.isNaN(d.getTime())) return 0;
  return Math.floor(d.getTime() / 86400000);
};

watch(isDateType, (useDate) => {
  if (useDate) {
    startDate.value = dateStrFromDays(form.start);
  } else {
    startDate.value = '';
  }
});

watch(startDate, (val) => {
  if (isDateType.value) {
    form.start = daysFromDateStr(val);
  }
});

const toggleTypeDropdown = () => {
  typeDropdownOpen.value = !typeDropdownOpen.value;
};

const selectType = (value) => {
  form.type = value;
  typeDropdownOpen.value = false;
};

watch(
  () => form.type,
  () => {
    if (isDateType.value) {
      startDate.value = '';
    }
    form.start = '';
    form.period = '';
  }
);

onMounted(() => {
  fetchMaintenance();
  fetchDevices();
});

const fetchMaintenance = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/web/vehicles/maintenance');
    const all = Array.isArray(response.data) ? response.data : [];
    maintenanceList.value = all.filter(item => alertTypeValues.includes(String(item.type)));
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

  const assignedIds = item.deviceIds || [];

  if (assignedIds.length === 0) {
      form.deviceId = '';
  } else if (assignedIds.length >= devices.value.length && devices.value.length > 0) {
      form.deviceId = 'all';
  } else if (assignedIds.length === 1) {
      form.deviceId = assignedIds[0];
  } else {
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
  form.start = '';
  form.period = '';
  form.deviceId = 'all';
  error.value = '';
};
</script>

<style scoped>
.alert-type-control {
  position: relative;
}

.alert-type-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border: 1px solid #ced4da;
  border-radius: 0.375rem;
  padding: 0.375rem 0.75rem;
  background-color: #ffffff;
  cursor: pointer;
}

.alert-type-placeholder {
  color: #6c757d;
  font-size: 0.9rem;
}

.alert-type-selected-text {
  font-size: 0.9rem;
}

.alert-type-menu {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  z-index: 1050;
  margin-top: 2px;
  border: 1px solid #ced4da;
  border-radius: 0.375rem;
  background-color: #ffffff;
  max-height: 260px;
  overflow-y: auto;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  padding: 0.5rem 0.75rem;
}

.alert-type-group {
  margin-bottom: 0.5rem;
}

.alert-type-group-title {
  font-weight: 600;
  font-size: 0.9rem;
  margin-bottom: 0.25rem;
}

.alert-type-option {
  display: flex;
  align-items: center;
  padding: 0.15rem 0;
  cursor: pointer;
}

.alert-type-option-label {
  font-size: 0.9rem;
}

.alert-type-radio {
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid #ced4da;
  margin-right: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.alert-type-radio-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background-color: #0d6efd;
}

.alert-type-option.is-selected .alert-type-radio {
  border-color: #0d6efd;
}

.alert-type-option.is-selected .alert-type-option-label {
  font-weight: 600;
}
</style>
