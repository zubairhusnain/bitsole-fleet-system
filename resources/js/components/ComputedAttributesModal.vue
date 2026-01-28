<template>
  <div v-if="show" class="modal-overlay" @click.self="emit('close')">
    <div class="modal-content-custom" role="dialog" aria-modal="true">
      <div class="modal-header">
        <h5 class="mb-0">Computed Attributes <span v-if="vehicleName" class="text-muted text-truncate ms-2 small">({{ vehicleName }})</span></h5>
        <button type="button" class="btn btn-light btn-sm" @click="emit('close')" aria-label="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <div class="modal-body">
        <div v-if="loading" class="text-center py-3">
          <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
          <span class="ms-2 small">Loading attributes...</span>
        </div>
        <div v-else-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
        <div v-else>
          <div v-if="attributes.length === 0" class="text-muted small text-center py-3">
            No computed attributes assigned to this vehicle.
          </div>
          <div v-else class="list-group list-group-flush">
            <div v-for="attr in attributes" :key="attr.id" class="list-group-item px-0 py-3">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-bold small">{{ attr.description }}</div>
                  <div class="text-muted xsmall font-monospace mt-1">{{ attr.attribute }} = {{ attr.expression }}</div>
                  <div class="text-muted xsmall mt-1">Type: {{ attr.type }}</div>
                </div>
                <button 
                  class="btn btn-outline-danger btn-sm ms-2" 
                  @click="confirmDelete(attr)"
                  title="Remove from vehicle"
                  :disabled="deletingId === attr.id"
                >
                  <span v-if="deletingId === attr.id" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                  <i v-else class="bi bi-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  show: Boolean,
  deviceId: [Number, String],
  vehicleName: String
});

const emit = defineEmits(['close']);

const attributes = ref([]);
const loading = ref(false);
const error = ref('');
const deletingId = ref(null);

watch(() => props.show, (val) => {
  if (val && props.deviceId) {
    fetchAttributes();
  }
});

async function fetchAttributes() {
  loading.value = true;
  error.value = '';
  attributes.value = [];
  try {
    const res = await axios.get(`/web/vehicles/${props.deviceId}/computed-attributes`);
    attributes.value = res.data.attributes || [];
  } catch (e) {
    error.value = 'Failed to load attributes.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

async function confirmDelete(attr) {
  if (!confirm(`Are you sure you want to remove "${attr.description}" from this vehicle?`)) return;
  
  deletingId.value = attr.id;
  try {
    await axios.delete(`/web/vehicles/${props.deviceId}/computed-attributes/${attr.id}`);
    // Remove from local list
    attributes.value = attributes.value.filter(a => a.id !== attr.id);
  } catch (e) {
    alert('Failed to remove attribute.');
    console.error(e);
  } finally {
    deletingId.value = null;
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1050;
}
.modal-content-custom {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 600px;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.modal-header {
  padding: 1rem;
  border-bottom: 1px solid #dee2e6;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.modal-body {
  padding: 1rem;
  overflow-y: auto;
}
.xsmall { font-size: 0.75rem; }
</style>
