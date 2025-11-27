<template>
  <div class="settings-wrapper">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Vehicle Models IO Settings</h5>
      </div>
      <div class="card-body">
        <form class="row g-3 align-items-end mb-3" @submit.prevent="submitModel">
          <div class="col-12 col-md-4">
            <label class="form-label">Model Name</label>
            <input v-model="form.modelname" type="text" class="form-control" placeholder="e.g. Hilux" required :disabled="submitting" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Odmeter IOID <span class="text-muted small">(optional)</span></label>
            <input v-model="form.odmeter_ioid" type="text" class="form-control" placeholder="e.g. 39" :disabled="submitting" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Fuel IOID <span class="text-muted small">(optional)</span></label>
            <input v-model="form.fuel_ioid" type="text" class="form-control" placeholder="e.g. 45" :disabled="submitting" />
          </div>
          <div class="col-12 col-md-2 d-flex">
            <button type="submit" class="btn btn-primary ms-md-2 w-100" :disabled="submitting">
              <span v-if="submitting" class="d-inline-flex align-items-center">
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                {{ editingId ? 'Update Model…' : 'Add Model…' }}
              </span>
              <span v-else>{{ editingId ? 'Update Model' : 'Add Model' }}</span>
            </button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle">
            <thead>
              <tr>
                <th style="width: 40px">#</th>
                <th>Model Name</th>
                <th class="text-center">Odmeter IOID</th>
                <th class="text-center">Fuel IOID</th>
                <th style="width: 100px" class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="5" class="text-center py-4">
                  <span class="spinner-border" role="status" aria-hidden="true"></span>
                </td>
              </tr>
              <tr v-for="m in models" :key="m.id">
                <td>{{ m.id }}</td>
                <td>{{ m.modelname }}</td>
                <td class="text-center">{{ m.odmeter_ioid }}</td>
                <td class="text-center">{{ m.fuel_ioid ?? '-' }}</td>
                <td class="text-end d-flex justify-content-end gap-2">
                  <button class="btn btn-sm btn-outline-secondary" @click="startEdit(m)" :disabled="submitting">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger" @click="removeModel(m)" :disabled="submitting">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="!loading && models.length === 0">
                <td colspan="5" class="text-center text-muted">No models added yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const models = ref([]);
const loading = ref(false);
const submitting = ref(false);
const editingId = ref(null);
const form = reactive({ modelname: '', odmeter_ioid: null, fuel_ioid: null });

async function fetchModels() {
  loading.value = true;
  try {
    const { data } = await axios.get('/web/settings/vehicle-models');
    models.value = data?.models || [];
  } finally {
    loading.value = false;
  }
}

async function submitModel() {
  submitting.value = true;
  try {
    const payload = {
      modelname: form.modelname,
      odmeter_ioid: (form.odmeter_ioid ?? '').toString().trim() || null,
      fuel_ioid: (form.fuel_ioid ?? '').toString().trim() || null,
    };
    if (editingId.value) {
      await axios.put(`/web/settings/vehicle-models/${editingId.value}`, payload);
    } else {
      await axios.post('/web/settings/vehicle-models', payload);
    }
    form.modelname = '';
    form.odmeter_ioid = null;
    form.fuel_ioid = null;
    editingId.value = null;
    await fetchModels();
  } finally {
    submitting.value = false;
  }
}

function startEdit(m) {
  editingId.value = m.id;
  form.modelname = m.modelname;
  form.odmeter_ioid = m.odmeter_ioid;
  form.fuel_ioid = m.fuel_ioid;
}

async function removeModel(m) {
  submitting.value = true;
  try {
    await axios.delete(`/web/settings/vehicle-models/${m.id}`);
    await fetchModels();
  } finally {
    submitting.value = false;
  }
}

onMounted(fetchModels);
</script>

<style scoped>
.settings-wrapper { padding: 16px; }
</style>
