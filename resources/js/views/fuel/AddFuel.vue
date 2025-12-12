<template>
  <div class="add-fuel-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/fuel">Fuel Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Add Fuel Entry</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">Create Fuel Entry</h4>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <form @submit.prevent="submit">

      <!-- Select Vehicle -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Select Vehicle</h6></div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-10">
                    <label class="form-label small">Vehicle ID</label>
                    <select class="form-select" v-model="form.device_id" @change="onVehicleSelect" required>
                        <option value="" disabled>--Select Vehicle--</option>
                        <option v-for="dev in devices" :key="dev.id" :value="dev.id">{{ dev.label }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button type="button" class="btn btn-primary w-100" @click="onVehicleSelect">Submit</button>
                </div>
            </div>
        </div>
      </div>

      <!-- Vehicle Information -->
      <div class="card mb-3" v-if="selectedVehicle">
        <div class="card-header"><h6 class="mb-0">Vehicle Information</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted d-block">Vehicle ID</label>
                    <span class="fw-medium">{{ selectedVehicle.tc_device?.attributes?.vehicleNo || '-' }}</span>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted d-block">Identifier</label>
                    <span class="fw-medium">{{ selectedVehicle.tc_device?.uniqueid || '-' }}</span>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted d-block">Vehicle Type</label>
                    <span class="fw-medium">{{ selectedVehicle.tc_device?.attributes?.type || '-' }}</span>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small text-muted d-block">Model</label>
                    <span class="fw-medium">{{ selectedVehicle.tc_device?.attributes?.model || selectedVehicle.tc_device?.model || '-' }}</span>
                </div>
            </div>
        </div>
      </div>

      <!-- Fuel Entry Detail -->
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Fuel Entry Detail</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label small">Fuel Type</label>
                    <select class="form-select" v-model="form.fuel_type">
                        <option value="">--Select Fuel Type--</option>
                        <option v-for="type in fuelTypes" :key="type" :value="type">{{ type }}</option>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small">Refuel Datetime</label>
                    <input type="datetime-local" class="form-control" v-model="form.fill_date" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small">Volume (Litre)</label>
                    <input type="number" step="0.01" class="form-control" v-model="form.quantity" placeholder="Volume (Litre)" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small">Odometer</label>
                    <input type="number" class="form-control" v-model="form.odometer" placeholder="Odometer">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small">Amount (RM)</label>
                    <input type="number" step="0.01" class="form-control" v-model="form.cost" placeholder="Amount (RM)" required>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label small">Payment Type</label>
                    <select class="form-select" v-model="form.payment_type">
                        <option value="">--Select Payment Type--</option>
                        <option v-for="type in paymentTypes" :key="type" :value="type">{{ type }}</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small">Remarks</label>
                    <textarea class="form-control" v-model="form.notes" rows="3" placeholder="Remarks"></textarea>
                </div>
            </div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <RouterLink to="/fuel" class="btn btn-secondary" style="min-width: 100px;">Cancel</RouterLink>
        <button type="submit" class="btn btn-primary" :disabled="saving" style="min-width: 100px;">
            <span v-if="saving && !addingMore" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Save Only
        </button>
        <button type="button" class="btn btn-dark" @click="submitAndAdd" :disabled="saving" style="min-width: 140px;">
            <span v-if="saving && addingMore" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Save & Add More
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';
import { hasPermission } from '../../auth';

const router = useRouter();
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const message = ref('');
const devices = ref([]);
const selectedVehicle = ref(null);
const addingMore = ref(false);

const fuelTypes = ['Diesel', 'Petrol (RON95)', 'Petrol (RON97)', 'Electric', 'Other'];
const paymentTypes = ['Cash', 'Credit Card', 'Fleet Card', 'TnG RFID', 'Online Transfer', 'Other'];

const form = reactive({
    device_id: '',
    fill_date: '',
    quantity: '',
    cost: '',
    odometer: '',
    notes: '',
    fuel_type: '',
    payment_type: ''
});

const fetchDevices = async () => {
    try {
        const { data } = await axios.get('/web/fuel/vehicles');
        devices.value = data || [];
        // Don't auto-select first device if we want to force user to select
    } catch (e) {
        console.error("Failed to load devices", e);
    }
};

const onVehicleSelect = () => {
    if (!form.device_id) return;
    const vehicle = devices.value.find(d => d.id === form.device_id);
    if (vehicle) {
        selectedVehicle.value = vehicle;
        // Auto-fill fuel type if available
        if (vehicle.tc_device?.attributes?.fuelType) {
            form.fuel_type = vehicle.tc_device.attributes.fuelType;
        }
    }
};

const submit = async () => {
    saving.value = true;
    error.value = '';
    try {
        await axios.post('/web/fuel', form);
        message.value = 'Entry added successfully';
        if (!addingMore.value) {
            setTimeout(() => router.push('/fuel'), 1000);
        } else {
            // Reset form but keep vehicle
            form.fill_date = '';
            form.quantity = '';
            form.cost = '';
            form.odometer = '';
            form.notes = '';
            form.payment_type = '';
            // keep fuel_type as it might be same for same vehicle
            addingMore.value = false;
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to save entry';
    } finally {
        saving.value = false;
    }
};

const submitAndAdd = () => {
    addingMore.value = true;
    submit();
};

onMounted(() => {
    if (!hasPermission('fuel', 'create')) {
        router.push('/fuel');
        return;
    }
    fetchDevices();
});
</script>
