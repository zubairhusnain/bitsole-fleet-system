<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Monitoring</li>
        <li class="breadcrumb-item active" aria-current="page">Command Console</li>
      </ol>
    </div>

    <div class="row">
      <!-- Command Form -->
      <div class="col-md-6 mb-4">
        <div class="card border rounded-3 shadow-0 h-100">
          <div class="card-header bg-white border-bottom pt-3 pb-2">
            <h5 class="card-title mb-0 fw-bold text-dark">
              <i class="bi bi-terminal me-2 text-primary"></i>Send Command
            </h5>
          </div>
          <div class="card-body">
            <form @submit.prevent="sendCommand">
              <div class="mb-3">
                <label class="form-label small fw-semibold text-muted">Select Vehicle</label>
                <select class="form-select" v-model="selectedDeviceId" required>
                  <option value="" disabled>-- Select a Vehicle --</option>
                  <option v-for="opt in deviceOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-semibold text-muted">Command Type</label>
                <select class="form-select" v-model="selectedType" required @change="handleTypeChange">
                  <option value="" disabled>-- Select Command --</option>
                  <option v-for="type in commandTypes" :key="type.value" :value="type.value">
                    {{ type.label }}
                  </option>
                </select>
                <div v-if="selectedTypeDetails?.danger" class="form-text text-danger">
                  <i class="bi bi-exclamation-triangle me-1"></i> Warning: This command affects vehicle operation.
                </div>
              </div>

              <!-- Dynamic Attributes -->
              <div v-if="selectedType === 'custom'" class="mb-3">
                <label class="form-label small fw-semibold text-muted">Custom Command Data</label>
                <input type="text" class="form-control" v-model="customData" placeholder="e.g. setdigout 1" required>
              </div>

              <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary" :disabled="loading || !selectedDeviceId || !selectedType">
                  <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                  <i v-else class="bi bi-send me-2"></i>
                  Send Command
                </button>
              </div>
            </form>

            <div v-if="responseMessage" class="mt-3 alert" :class="responseSuccess ? 'alert-success' : 'alert-danger'">
              <i class="bi" :class="responseSuccess ? 'bi-check-circle' : 'bi-x-circle'"></i>
              {{ responseMessage }}
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions / History Placeholder -->
      <div class="col-md-6 mb-4">
        <div class="card border rounded-3 shadow-0 h-100 bg-light">
          <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-5">
            <div class="mb-3 text-muted">
              <i class="bi bi-clock-history fs-1"></i>
            </div>
            <h6 class="fw-bold text-muted">Command History</h6>
            <p class="small text-muted mb-0">Recent commands will appear here in future updates.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const deviceOptions = ref([]);
const commandTypes = ref([]);
const selectedDeviceId = ref('');
const selectedType = ref('');
const customData = ref('');
const loading = ref(false);
const responseMessage = ref('');
const responseSuccess = ref(false);

const selectedTypeDetails = computed(() => {
  return commandTypes.value.find(t => t.value === selectedType.value);
});

onMounted(async () => {
  await loadDeviceOptions();
  await loadCommandTypes();
});

const loadDeviceOptions = async () => {
  try {
    const res = await axios.get('/web/commands/device-options');
    deviceOptions.value = res.data.options || res.data || [];
  } catch (e) {
    console.error('Failed to load devices', e);
  }
};

const loadCommandTypes = async () => {
  try {
    const res = await axios.get('/web/commands/types');
    commandTypes.value = res.data || [];
  } catch (e) {
    console.error('Failed to load command types', e);
  }
};

const handleTypeChange = () => {
  customData.value = '';
  responseMessage.value = '';
};

const sendCommand = async () => {
  if (selectedTypeDetails.value?.danger) {
    if (!confirm(`Are you sure you want to send "${selectedTypeDetails.value.label}" to this vehicle?`)) {
      return;
    }
  }

  loading.value = true;
  responseMessage.value = '';
  responseSuccess.value = false;

  const payload = {
    device_id: selectedDeviceId.value,
    type: selectedType.value,
    attributes: {}
  };

  if (selectedType.value === 'custom') {
    payload.attributes.data = customData.value;
  }

  try {
    const res = await axios.post('/web/commands/send', payload);
    responseSuccess.value = true;
    responseMessage.value = res.data.message;
  } catch (e) {
    responseSuccess.value = false;
    responseMessage.value = e.response?.data?.message || 'Failed to send command.';
  } finally {
    loading.value = false;
  }
};
</script>
