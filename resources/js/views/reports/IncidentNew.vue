<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item"><RouterLink to="/reports/incident-analysis">Incident Analysis Report</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Create New Incident Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Create New Incident Report</h4>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-body p-4">
        <div class="row g-3">
          <!-- Row 1: Vehicle ID & Driver -->
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold text-muted">Vehicle ID</label>
            <input type="text" class="form-control" placeholder="Enter Vehicle ID" v-model="form.vehicleId" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold text-muted">Driver</label>
            <select class="form-select text-muted" v-model="form.driverId">
              <option value="">--Select Driver --</option>
              <option value="1">Adam</option>
              <option value="2">Bella</option>
              <option value="3">Chong</option>
              <option value="4">Danish</option>
            </select>
          </div>

          <!-- Row 2: Incident Start & Incident End -->
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold text-muted">Incident Start</label>
            <div class="input-group">
              <input type="datetime-local" class="form-control text-muted" v-model="form.incidentStart" />
            </div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold text-muted">Incident End</label>
            <div class="input-group">
              <input type="datetime-local" class="form-control text-muted" v-model="form.incidentEnd" />
              <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
            </div>
          </div>

          <!-- Row 3: Impact Date/Time -->
          <div class="col-12">
            <label class="form-label small fw-semibold text-muted">Impact Date/Time</label>
            <input type="datetime-local" class="form-control text-muted" v-model="form.impactTime" />
          </div>

          <!-- Row 4: Description -->
          <div class="col-12">
            <label class="form-label small fw-semibold text-muted">Description</label>
            <textarea class="form-control text-muted" rows="4" placeholder="write description here....." v-model="form.description"></textarea>
          </div>

          <!-- Row 5: Remarks -->
          <div class="col-12">
            <label class="form-label small fw-semibold text-muted">Remarks</label>
            <textarea class="form-control text-muted" rows="4" placeholder="write your remarks here....." v-model="form.remarks"></textarea>
          </div>
        </div>

        <!-- Buttons -->
        <div class="d-flex justify-content-end gap-2 mt-4">
          <RouterLink to="/reports/incident-analysis" class="btn btn-secondary px-4">Cancel</RouterLink>
          <button class="btn btn-info text-white px-4" @click="save">Save Only</button>
          <button class="btn btn-dark px-4" @click="saveAndAdd">Save & Add More</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();

const form = ref({
  vehicleId: '',
  driverId: '',
  incidentStart: '',
  incidentEnd: '',
  impactTime: '',
  description: '',
  remarks: ''
});

const save = () => {
  axios.post('/web/reports/incidents', form.value)
    .then(() => {
      router.push('/reports/incident-analysis');
    })
    .catch((e) => {
      console.error('Failed to save incident', e);
    });
};

const saveAndAdd = () => {
  axios.post('/web/reports/incidents', form.value)
    .then(() => {
      form.value = {
        vehicleId: '',
        driverId: '',
        incidentStart: '',
        incidentEnd: '',
        impactTime: '',
        description: '',
        remarks: ''
      };
    })
    .catch((e) => {
      console.error('Failed to save incident', e);
    });
};
</script>

<style scoped>
.form-label {
  font-size: 0.85rem;
  margin-bottom: 0.4rem;
}
textarea {
  resize: none;
}
/* Calendar icon fix if needed */
input[type="datetime-local"]::-webkit-calendar-picker-indicator {
  background: transparent;
  bottom: 0;
  color: transparent;
  cursor: pointer;
  height: auto;
  left: 0;
  position: absolute;
  right: 0;
  top: 0;
  width: auto;
}
</style>
