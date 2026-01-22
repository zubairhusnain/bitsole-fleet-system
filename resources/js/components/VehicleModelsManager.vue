<template>
  <div class="vehicle-models-manager">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h6 class="mb-0">Device Models</h6>
      <button class="btn btn-sm btn-primary" @click="openModal()" :disabled="loading">
        <i class="bi bi-plus-lg"></i> Add Device Model
      </button>
    </div>

    <div v-if="loading && !models.length" class="text-center p-3 text-muted">Loading models...</div>

    <div v-else class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th>Device Model</th>
            <th>Attributes</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!models.length">
            <td colspan="3" class="text-center p-3 text-muted">No device models found.</td>
          </tr>
          <tr v-for="model in models" :key="model.id">
            <td>{{ model.modelname }}</td>
            <td>
              <div
                v-if="
                  model.attributes &&
                  ((model.attributes.odometer && model.attributes.odometer.length) ||
                    (model.attributes.fuel && model.attributes.fuel.length) ||
                    (model.attributes.speed && model.attributes.speed.length))
                "
              >
                <table class="table table-sm mb-0">
                  <thead>
                    <tr class="small">
                      <th class="border-0">Odometer</th>
                      <th class="border-0">Fuel</th>
                      <th class="border-0">Speed</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="(row, idx) in getAttributeRows(model)"
                      :key="'attr-row-' + model.id + '-' + idx"
                      class="small"
                    >
                      <td class="border-0 text-muted">
                        <span v-if="row.odometer">
                          {{ row.odometer.name }} ({{ row.odometer.key }})
                        </span>
                      </td>
                      <td class="border-0 text-muted">
                        <span v-if="row.fuel">
                          {{ row.fuel.name }} ({{ row.fuel.key }})
                        </span>
                      </td>
                      <td class="border-0 text-muted">
                        <span v-if="row.speed">
                          {{ row.speed.name }} ({{ row.speed.key }})
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <span
                v-else
                class="text-muted"
              >
                -
              </span>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-primary me-2" @click="openModal(model)" title="Edit">
                <i class="bi bi-pencil"></i>
              </button>
              <button class="btn btn-sm btn-outline-danger" @click="deleteModel(model)" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="vehicleModelModal" tabindex="-1" aria-hidden="true" ref="modalRef">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ isEditing ? 'Edit Vehicle Model' : 'Add Vehicle Model' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveModel">
              <div class="mb-3">
                <label for="modelName" class="form-label">Model Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="modelName" v-model="form.modelname" required>
              </div>
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label class="form-label fw-bold mb-0">Odometer Attributes</label>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="addOdometerInput"><i class="bi bi-plus"></i> Add</button>
                </div>
                <div v-for="(input, idx) in form.odometerInputs" :key="'odo-'+idx" class="row g-2 mb-2 align-items-start">
                  <div class="col-5">
                    <input type="text" class="form-control form-control-sm" :class="{ 'is-invalid': input.nameError }" v-model="input.name" placeholder="Name (e.g. Dashboard)">
                    <div v-if="input.nameError" class="invalid-feedback">{{ input.nameError }}</div>
                  </div>
                  <div class="col-5">
                    <input type="text" class="form-control form-control-sm" :class="{ 'is-invalid': input.keyError }" v-model="input.key" placeholder="Key (e.g. 16)">
                    <div v-if="input.keyError" class="invalid-feedback">{{ input.keyError }}</div>
                  </div>
                  <div class="col-2">
                     <button type="button" class="btn btn-sm btn-outline-danger w-100" @click="removeOdometerInput(idx)"><i class="bi bi-trash"></i></button>
                  </div>
                </div>
                <div v-if="!form.odometerInputs.length" class="text-muted small fst-italic">No odometer attributes configured.</div>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label class="form-label fw-bold mb-0">Fuel Attributes</label>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="addFuelInput"><i class="bi bi-plus"></i> Add</button>
                </div>
                <div v-for="(input, idx) in form.fuelInputs" :key="'fuel-'+idx" class="row g-2 mb-2 align-items-start">
                  <div class="col-4">
                    <input type="text" class="form-control form-control-sm" :class="{ 'is-invalid': input.nameError }" v-model="input.name" placeholder="Name (e.g. Tank 1)" required pattern=".*\S+.*" title="Must not be empty or whitespace">
                    <div v-if="input.nameError" class="invalid-feedback">{{ input.nameError }}</div>
                  </div>
                  <div class="col-4">
                    <input type="text" class="form-control form-control-sm" :class="{ 'is-invalid': input.keyError }" v-model="input.key" placeholder="Key (e.g. 84)" required pattern=".*\S+.*" title="Must not be empty or whitespace">
                    <div v-if="input.keyError" class="invalid-feedback">{{ input.keyError }}</div>
                  </div>
                  <div class="col-2 text-center pt-1">
                    <div class="form-check d-inline-block">
                        <input class="form-check-input" type="checkbox" v-model="input.is_analog" :id="'fuel-check-'+idx">
                        <label class="form-check-label small" :for="'fuel-check-'+idx">
                            Analog
                        </label>
                    </div>
                  </div>
                  <div class="col-2">
                     <button type="button" class="btn btn-sm btn-outline-danger w-100" @click="removeFuelInput(idx)"><i class="bi bi-trash"></i></button>
                  </div>
                </div>
                 <div v-if="!form.fuelInputs.length" class="text-muted small fst-italic">No fuel attributes configured.</div>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <label class="form-label fw-bold mb-0">Speed Attributes</label>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="addSpeedInput"><i class="bi bi-plus"></i> Add</button>
                </div>
                <div v-for="(input, idx) in form.speedInputs" :key="'speed-'+idx" class="row g-2 mb-2 align-items-start">
                  <div class="col-5">
                    <input type="text" class="form-control form-control-sm" :class="{ 'is-invalid': input.nameError }" v-model="input.name" placeholder="Name (e.g. Speed)">
                    <div v-if="input.nameError" class="invalid-feedback">{{ input.nameError }}</div>
                  </div>
                  <div class="col-5">
                    <input type="text" class="form-control form-control-sm" :class="{ 'is-invalid': input.keyError }" v-model="input.key" placeholder="Key (e.g. speed)">
                    <div v-if="input.keyError" class="invalid-feedback">{{ input.keyError }}</div>
                  </div>
                  <div class="col-2">
                     <button type="button" class="btn btn-sm btn-outline-danger w-100" @click="removeSpeedInput(idx)"><i class="bi bi-trash"></i></button>
                  </div>
                </div>
                 <div v-if="!form.speedInputs.length" class="text-muted small fst-italic">No speed attributes configured.</div>
              </div>
              <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" :disabled="saving">
                  <span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                  {{ isEditing ? 'Update' : 'Save' }}
                </button>
              </div>
            </form>
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
// We assume Bootstrap 5 is available globally or via import, but for modal handling we might need the instance
// Ideally we import Modal from bootstrap if using a bundler
// import { Modal } from 'bootstrap';

const models = ref([]);
const loading = ref(false);
const saving = ref(false);
const modalRef = ref(null);
let bsModal = null;

const isEditing = ref(false);
const editingId = ref(null);
const form = reactive({
  modelname: '',
  odometerInputs: [],
  fuelInputs: [],
  speedInputs: []
});

function getAttributeRows(model) {
  const odo = (model.attributes && model.attributes.odometer) || [];
  const fuel = (model.attributes && model.attributes.fuel) || [];
  const speed = (model.attributes && model.attributes.speed) || [];
  const max = Math.max(odo.length, fuel.length, speed.length);
  const rows = [];
  for (let i = 0; i < max; i += 1) {
    rows.push({
      odometer: odo[i] || null,
      fuel: fuel[i] || null,
      speed: speed[i] || null
    });
  }
  return rows;
}

function addOdometerInput() {
  form.odometerInputs.push({ name: '', key: '', nameError: '', keyError: '' });
}
function removeOdometerInput(index) {
  form.odometerInputs.splice(index, 1);
}
function addFuelInput() {
  form.fuelInputs.push({ name: '', key: '', is_analog: false, nameError: '', keyError: '' });
}
function removeFuelInput(index) {
  form.fuelInputs.splice(index, 1);
}
function addSpeedInput() {
  form.speedInputs.push({ name: '', key: '', nameError: '', keyError: '' });
}
function removeSpeedInput(index) {
  form.speedInputs.splice(index, 1);
}

async function fetchModels() {
  loading.value = true;
  try {
    const res = await axios.get('/web/settings/vehicle-models');
    models.value = res.data.models || [];
  } catch (e) {
    console.error('Failed to fetch vehicle models', e);
    Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'error', title: 'Failed to load models' });
  } finally {
    loading.value = false;
  }
}

function openModal(model = null) {
  if (model) {
    isEditing.value = true;
    editingId.value = model.id;
    form.modelname = model.modelname;
    const attrs = model.attributes || {};
    form.odometerInputs = attrs.odometer ? JSON.parse(JSON.stringify(attrs.odometer)).map(i => ({...i, nameError: '', keyError: ''})) : [];
    form.fuelInputs = attrs.fuel ? JSON.parse(JSON.stringify(attrs.fuel)).map(f => ({
      ...f,
      is_analog: !!f.is_analog,
      nameError: '',
      keyError: ''
    })) : [];
    form.speedInputs = attrs.speed ? JSON.parse(JSON.stringify(attrs.speed)).map(i => ({...i, nameError: '', keyError: ''})) : [];
  } else {
    isEditing.value = false;
    editingId.value = null;
    form.modelname = '';
    form.odometerInputs = [];
    form.fuelInputs = [];
    form.speedInputs = [];
  }

  if (!bsModal && modalRef.value) {
    // Dynamically import bootstrap to avoid issues if it's not available in this scope directly
    // Or assume it's on window if using a script tag.
    // Usually in Vite/Laravel setups, bootstrap is imported in app.js.
    // We'll try to use the global bootstrap object or the element API.
    if (window.bootstrap) {
      bsModal = new window.bootstrap.Modal(modalRef.value);
    }
  }

  if (bsModal) bsModal.show();
}

async function saveModel() {
  let hasError = false;

  const validate = (inputs, type) => {
    inputs.forEach(input => {
      input.nameError = '';
      input.keyError = '';

      if (!input.name || !input.name.trim()) {
        input.nameError = 'Name is required';
        hasError = true;
      } else if (/\s/.test(input.name)) {
        input.nameError = 'Spaces are not allowed';
        hasError = true;
      }

      if (!input.key || !input.key.trim()) {
        input.keyError = 'Key is required';
        hasError = true;
      } else if (/\s/.test(input.key)) {
        input.keyError = 'Spaces are not allowed';
        hasError = true;
      }
    });
  };

  validate(form.odometerInputs, 'Odometer');
  validate(form.fuelInputs, 'Fuel');
  validate(form.speedInputs, 'Speed');

  if (hasError) {
    // Optionally show a general toast, or just rely on inline errors
    // Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'error', title: 'Please correct errors' });
    return;
  }

  saving.value = true;
  try {
    // Strip error fields before sending
    const clean = (inputs) => inputs.map(({ name, key, is_analog }) => {
        const obj = { name, key };
        if (typeof is_analog !== 'undefined') obj.is_analog = is_analog;
        return obj;
    });

    const payload = {
      modelname: form.modelname,
      attributes: {
        odometer: clean(form.odometerInputs),
        fuel: clean(form.fuelInputs),
        speed: clean(form.speedInputs)
      }
    };

    if (isEditing.value) {
      await axios.put(`/web/settings/vehicle-models/${editingId.value}`, payload);
      Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, icon: 'success', title: 'Model updated' });
    } else {
      await axios.post('/web/settings/vehicle-models', payload);
      Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, icon: 'success', title: 'Model added' });
    }
    if (bsModal) bsModal.hide();
    fetchModels();
  } catch (e) {
    console.error('Failed to save model', e);
    const msg = e.response?.data?.message || 'Failed to save model';
    Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'error', title: msg });
  } finally {
    saving.value = false;
  }
}

async function deleteModel(model) {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: `Delete vehicle model "${model.modelname}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  });

  if (result.isConfirmed) {
    try {
      await axios.delete(`/web/settings/vehicle-models/${model.id}`);
      Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, icon: 'success', title: 'Model deleted' });
      fetchModels();
    } catch (e) {
      console.error('Failed to delete model', e);
      Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, icon: 'error', title: 'Failed to delete model' });
    }
  }
}

onMounted(() => {
  fetchModels();
});
</script>
