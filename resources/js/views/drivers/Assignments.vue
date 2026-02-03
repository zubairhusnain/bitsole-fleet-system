<template>
  <div class="assignments-view">
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/drivers">Driver Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Client Driver Assignments</li>
      </ol>
    </div>

    <div class="row mb-3">
      <div class="col-12"><h4 class="mb-0 fw-semibold">Client Driver Assignments</h4></div>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!successMessage" :message="successMessage" variant="success" dismissible @dismiss="successMessage = ''" />

    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
            <thead class="thead-app-dark">
              <tr>
                <th class="fw-semibold py-2">Driver Name</th>
                <th class="fw-semibold py-2">Current Vehicle</th>
                <th class="fw-semibold py-2">Status</th>
                <th class="fw-semibold py-2 text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="driver in clientDrivers" :key="driver.localId">
                <td>{{ driver.name }}</td>
                <td>{{ driver.activeVehicle || 'None' }}</td>
                <td><span class="badge" :class="driver.activeAssignment ? 'bg-success' : 'bg-secondary'">{{ driver.activeAssignment ? 'Assigned' : 'Available' }}</span></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-info me-2" @click="openHistoryModal(driver)">
                    History
                  </button>
                  <button v-if="driver.activeAssignment" class="btn btn-sm btn-outline-danger" @click="endTrip(driver)">
                    End Trip
                  </button>
                  <button v-else class="btn btn-sm btn-outline-primary" @click="openAssignmentModal(driver)">
                    Assign Vehicle
                  </button>
                </td>
              </tr>
              <tr v-if="clientDrivers.length === 0">
                <td colspan="4" class="text-center text-muted py-3">No client drivers found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Assignment Modal -->
    <div v-if="showModal" class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Assign Vehicle to {{ selectedDriver?.name }}</h5>
            <button type="button" class="btn-close" @click="closeModal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Select Vehicle</label>
              <select v-model="assignmentForm.vehicleId" class="form-select">
                <option value="">-- Select Vehicle --</option>
                <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.label || v.name }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Start Time</label>
              <input v-model="assignmentForm.startTime" type="datetime-local" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">End Time (Optional)</label>
              <input v-model="assignmentForm.endTime" type="datetime-local" class="form-control">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeModal">Cancel</button>
            <button type="button" class="btn btn-primary" @click="submitAssignment" :disabled="submitting">
              {{ submitting ? 'Assigning...' : 'Assign' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- History Modal -->
    <div v-if="showHistoryModal" class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Trip History: {{ selectedDriver?.name }}</h5>
            <button type="button" class="btn-close" @click="closeHistoryModal"></button>
          </div>
          <div class="modal-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-striped mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Vehicle</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Duration</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(trip, index) in driverHistory" :key="index">
                    <td>{{ trip.deviceName }}</td>
                    <td>{{ formatTime(trip.startTime) }}</td>
                    <td>{{ formatTime(trip.endTime) }}</td>
                    <td>{{ trip.duration || '-' }}</td>
                    <td>
                      <span class="badge" :class="{
                        'bg-success': trip.status === 'active',
                        'bg-secondary': trip.status === 'completed',
                        'bg-warning text-dark': trip.status === 'scheduled'
                      }">{{ trip.status }}</span>
                    </td>
                  </tr>
                  <tr v-if="driverHistory.length === 0">
                    <td colspan="5" class="text-center text-muted py-3">No history found.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="closeHistoryModal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const clientDrivers = ref([]);
const vehicles = ref([]);
const error = ref('');
const successMessage = ref('');
const showModal = ref(false);
const showHistoryModal = ref(false);
const selectedDriver = ref(null);
const submitting = ref(false);
const driverHistory = ref([]);

const assignmentForm = ref({
  vehicleId: '',
  startTime: '',
  endTime: ''
});

async function fetchData() {
  try {
    const [driversRes, vehiclesRes, assignmentsRes] = await Promise.all([
      axios.get('/web/drivers'),
      axios.get('/web/drivers/options'),
      axios.get('/web/drivers/assignments?status=active')
    ]);

    const allDrivers = driversRes.data.drivers || [];
    const activeAssignments = assignmentsRes.data || [];

    // Create map of driver_id -> active assignment
    const assignmentMap = {};
    const assignedVehicleIds = new Set();
    activeAssignments.forEach(a => {
      assignmentMap[a.driver_id] = a;
      if (a.vehicle_id) assignedVehicleIds.add(a.vehicle_id);
    });

    // Filter only client drivers and format
    clientDrivers.value = allDrivers
      .filter(d => d.is_client_driver || d.isClientDriver)
      .map(d => {
        const assignment = assignmentMap[d.localId]; // Use localId to match assignment
        return {
          ...d,
          activeAssignment: assignment,
          activeVehicle: assignment?.vehicle?.name || assignment?.vehicle?.name || null
        };
      });

    const allVehicles = vehiclesRes.data.options || [];
    vehicles.value = allVehicles.filter(v => !assignedVehicleIds.has(v.id));
  } catch (e) {
    error.value = 'Failed to load data';
  }
}

async function endTrip(driver) {
  if (!driver.activeAssignment) return;

  if (!confirm(`End trip for ${driver.name}? This will mark the assignment as completed.`)) {
    return;
  }

  try {
    const now = new Date();
    // Adjust to local ISO string
    const localIso = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);

    await axios.put(`/web/drivers/assignments/${driver.activeAssignment.id}`, {
      end_time: localIso,
      status: 'completed'
    });

    successMessage.value = 'Trip ended successfully';
    fetchData();
  } catch (e) {
    error.value = 'Failed to end trip';
  }
}

function openAssignmentModal(driver) {
  selectedDriver.value = driver;
  const now = new Date();
  // Adjust to local ISO string for datetime-local input
  const localIso = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);

  assignmentForm.value = {
    vehicleId: '',
    startTime: localIso,
    endTime: ''
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  selectedDriver.value = null;
}

async function openHistoryModal(driver) {
  selectedDriver.value = driver;
  showHistoryModal.value = true;
  driverHistory.value = [];

  try {
    const res = await axios.get('/web/drivers/assignments/history', {
      params: { driver_id: driver.localId }
    });
    driverHistory.value = res.data;
  } catch (e) {
    console.error('Failed to fetch history', e);
  }
}

function formatTime(iso) {
  if (!iso) return '-';
  return new Date(iso).toLocaleString();
}

function formatDistance(meters) {
  if (!meters) return '0 km';
  return (meters / 1000).toFixed(2) + ' km';
}

function formatDuration(ms) {
  if (!ms) return '0m';
  const min = Math.floor(ms / 60000);
  const h = Math.floor(min / 60);
  const m = min % 60;
  if (h > 0) return `${h}h ${m}m`;
  return `${m}m`;
}

function formatSpeed(knots) {
  if (!knots) return '0 km/h';
  return (knots * 1.852).toFixed(0) + ' km/h';
}

function closeHistoryModal() {
  showHistoryModal.value = false;
  selectedDriver.value = null;
  driverHistory.value = [];
}

async function submitAssignment() {
  if (!assignmentForm.value.vehicleId || !assignmentForm.value.startTime) {
    error.value = 'Vehicle and Start Time are required';
    return;
  }

  submitting.value = true;
  error.value = '';
console.log('selectedDriver ',selectedDriver);
  try {
    await axios.post('/web/drivers/assignments', {
      driver_id: selectedDriver.value.localId,
      vehicle_id: assignmentForm.value.vehicleId,
      start_time: assignmentForm.value.startTime,
      end_time: assignmentForm.value.endTime || null
    });

    successMessage.value = 'Vehicle assigned successfully';
    closeModal();
    fetchData(); // Refresh list
  } catch (e) {
    error.value = e.response?.data?.message || 'Assignment failed';
  } finally {
    submitting.value = false;
  }
}

onMounted(fetchData);
</script>

<style scoped>
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>
