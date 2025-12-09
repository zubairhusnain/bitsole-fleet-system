<template>
  <div class="edit-fuel-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/fuel">Fuel Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Edit Fuel Entry</li>
      </ol>
    </div>

    <h4 class="mb-3 fw-semibold">Edit Fuel Entry</h4>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <div v-if="loading" class="d-flex align-items-center mb-2">
        <div class="spinner-border spinner-border-sm me-2" role="status"><span class="visually-hidden">Loading…</span></div>
        <span class="text-muted small">Loading entry details...</span>
    </div>

    <form v-else @submit.prevent="submit">

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
                    <button type="button" class="btn btn-primary w-100" @click="onVehicleSelect">Reload Info</button>
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
                    <label class="form-label small text-muted d-block">Vehicle Name</label>
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
            <span v-if="saving" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Save Changes
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const router = useRouter();
const route = useRoute();
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const message = ref('');
const devices = ref([]);
const selectedVehicle = ref(null);

const fuelTypes = ['Diesel', 'Petrol (RON95)', 'Petrol (RON97)', 'Electric', 'Other'];
const paymentTypes = ['Cash', 'Credit Card', 'Fleet Card', 'TnG RFID', 'Online Transfer', 'Other'];

const form = reactive({
    id: null,
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
    } catch (e) {
        console.error("Failed to load devices", e);
    }
};

const onVehicleSelect = (event) => {
    if (!form.device_id) return;
    const vehicle = devices.value.find(d => d.id === form.device_id);
    if (vehicle) {
        selectedVehicle.value = vehicle;

        // Only auto-fill fuel type if triggered by user (event exists) OR if form.fuel_type is empty
        if ((event || !form.fuel_type) && vehicle.tc_device?.attributes?.fuelType) {
            form.fuel_type = vehicle.tc_device.attributes.fuelType;
        }
    }
};

const fetchEntry = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/web/fuel/${route.params.id}`);
        Object.assign(form, {
            id: data.id,
            device_id: data.device_id,
            fill_date: data.fill_date ? data.fill_date.slice(0, 16) : '', // Format for datetime-local (YYYY-MM-DDTHH:mm)
            quantity: data.quantity,
            cost: data.cost,
            odometer: data.odometer,
            notes: data.notes,
            fuel_type: data.fuel_type || '',
            payment_type: data.payment_type || ''
        });

        // Load vehicle info after loading entry
        if (form.device_id) {
            // Call without event to prevent overwriting existing fuel_type unless it's empty
            onVehicleSelect();
        }
    } catch (e) {
        error.value = 'Failed to load fuel entry';
        console.error(e);
    } finally {
        loading.value = false;
    }
};

const submit = async () => {
    saving.value = true;
    error.value = '';
    try {
        await axios.put(`/web/fuel/${form.id}`, form);
        message.value = 'Entry updated successfully';
        setTimeout(() => router.push('/fuel'), 1000);
    } catch (e) {
        error.value = e.response?.data?.message || 'Failed to update entry';
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    await fetchDevices();
    if (route.params.id) {
        await fetchEntry();
    } else {
        loading.value = false;
    }
});
</script>
