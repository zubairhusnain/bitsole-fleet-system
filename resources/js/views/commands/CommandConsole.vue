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
                <select class="form-select" v-model="selectedDeviceId" required @change="onDeviceChange">
                  <option value="" disabled>-- Select a Vehicle --</option>
                  <option v-for="opt in deviceOptions" :key="opt.id" :value="opt.deviceId || opt.id">{{ opt.label }}</option>
                </select>
              </div>

              <!-- Mode Selection (New or Saved) -->
              <div class="mb-3" v-if="selectedDeviceId">
                <div class="btn-group w-100" role="group">
                  <input type="radio" class="btn-check" name="cmdMode" id="modeNew" value="new" v-model="commandMode" checked>
                  <label class="btn btn-outline-secondary" for="modeNew">New Command</label>

                  <input type="radio" class="btn-check" name="cmdMode" id="modeSaved" value="saved" v-model="commandMode" :disabled="!savedCommands.length">
                  <label class="btn btn-outline-secondary" for="modeSaved">
                    Saved Commands <span v-if="savedCommands.length" class="badge bg-secondary ms-1">{{ savedCommands.length }}</span>
                  </label>
                </div>
              </div>

              <!-- New Command Type -->
              <div class="mb-3" v-if="commandMode === 'new'">
                <label class="form-label small fw-semibold text-muted">Command Type</label>
                <select class="form-select" v-model="selectedType" required @change="handleTypeChange">
                  <option value="" disabled>-- Select Command --</option>
                  <option v-for="type in commandTypes" :key="type.type" :value="type.type">
                    {{ type.description || type.type }}
                  </option>
                </select>
                <div v-if="selectedTypeDetails?.danger" class="form-text text-danger">
                  <i class="bi bi-exclamation-triangle me-1"></i> Warning: This command affects vehicle operation.
                </div>
              </div>

              <!-- Saved Command Selection -->
              <div class="mb-3" v-if="commandMode === 'saved'">
                <label class="form-label small fw-semibold text-muted">Saved Command</label>
                <select class="form-select" v-model="selectedSavedId" required>
                  <option value="" disabled>-- Select Saved Command --</option>
                  <option v-for="cmd in savedCommands" :key="cmd.id" :value="cmd.id">
                    {{ cmd.description }} ({{ cmd.type }})
                  </option>
                </select>
              </div>

              <!-- Dynamic Attributes -->
              <div v-if="commandMode === 'new' && selectedType === 'custom'" class="mb-3">
                <label class="form-label small fw-semibold text-muted">Custom Command Data</label>
                <input type="text" class="form-control" v-model="customData" placeholder="e.g. setdigout 1" required>
              </div>

              <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary" :disabled="loading || !selectedDeviceId || (commandMode === 'new' && !selectedType) || (commandMode === 'saved' && !selectedSavedId)">
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
const savedCommands = ref([]);
const selectedDeviceId = ref('');
const commandMode = ref('new'); // 'new' or 'saved'
const selectedType = ref('');
const selectedSavedId = ref('');
const customData = ref('');
const loading = ref(false);
const responseMessage = ref('');
const responseSuccess = ref(false);

const selectedTypeDetails = computed(() => {
  return commandTypes.value.find(t => t.type === selectedType.value);
});

onMounted(async () => {
  await loadDeviceOptions();
  // Types will be loaded when device is selected
});

const loadDeviceOptions = async () => {
  try {
    const res = await axios.get('/web/device-options');
    deviceOptions.value = res.data.options || res.data || [];
  } catch (e) {
    console.error('Failed to load devices', e);
  }
};

const onDeviceChange = async () => {
  selectedType.value = '';
  selectedSavedId.value = '';
  commandTypes.value = [];
  savedCommands.value = [];
  customData.value = '';

  if (!selectedDeviceId.value) return;

  try {
    const [typesRes, savedRes] = await Promise.all([
      axios.get(`/web/commands/types?device_id=${selectedDeviceId.value}`),
      axios.get(`/web/commands/saved?device_id=${selectedDeviceId.value}`)
    ]);
    commandTypes.value = typesRes.data || [];
    savedCommands.value = savedRes.data || [];

    // Default to 'new' unless only saved commands are available? No, default new.
    commandMode.value = 'new';
  } catch (e) {
    console.error('Failed to load command data', e);
  }
};

const handleTypeChange = () => {
  customData.value = '';
  responseMessage.value = '';
};

const sendCommand = async () => {
  if (commandMode.value === 'new' && selectedTypeDetails.value?.danger) {
    if (!confirm(`Are you sure you want to send "${selectedTypeDetails.value.description}" to this vehicle?`)) {
      return;
    }
  }

  loading.value = true;
  responseMessage.value = '';
  responseSuccess.value = false;

  const payload = {
    device_id: selectedDeviceId.value,
  };

  if (commandMode.value === 'saved') {
    payload.command_id = selectedSavedId.value;
  } else {
    payload.type = selectedType.value;
    payload.attributes = {};
    if (selectedType.value === 'custom') {
      payload.attributes.data = customData.value;
    }
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
