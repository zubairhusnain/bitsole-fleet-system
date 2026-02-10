<template>
  <div class="driver-modal-overlay" @click.self="$emit('close')">
    <div class="driver-modal overflow-hidden" role="dialog" aria-modal="true" style="max-width: 800px;">
      <div class="modal-body p-0" v-if="vehicle">
        <!-- Header Image -->
        <div class="position-relative bg-light" style="height: 250px;">
          <!-- Using a placeholder image or vehicle image if available -->
          <img :src="vehicle.image_url || 'https://placehold.co/800x400/e9ecef/6c757d?text=Vehicle+Image'"
               class="w-100 h-100 object-fit-cover"
               alt="Vehicle Image"
               onerror="this.src='https://placehold.co/800x400/e9ecef/6c757d?text=Vehicle+Image'">

          <button type="button" class="btn btn-close position-absolute top-0 end-0 m-3 bg-white p-2" @click="$emit('close')" aria-label="Close"></button>
        </div>

        <div class="p-4">
          <!-- Title & Status Badge -->
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="fw-bold mb-0">Vehicle Status Information</h5>
            <span class="badge px-3 py-2 rounded-pill border"
                  :class="vehicle.ignition ? 'bg-success-subtle text-success border-success' : 'bg-danger-subtle text-danger border-danger'">
                {{ vehicle.ignition ? 'Normal' : 'Stopped' }}
            </span>
          </div>

          <!-- Grid -->
          <div class="row g-4 mb-4">
            <!-- Row 1 -->
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Vehicle ID</div>
              <div class="text-muted small">{{ vehicle.vehicle_no || vehicle.name }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Device ID</div>
              <div class="text-muted small">{{ vehicle.uniqueid }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Vehicle Type</div>
              <div class="text-muted small">{{ vehicle.type }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Model</div>
              <div class="text-muted small">{{ vehicle.model }}</div>
            </div>

            <!-- Row 2 -->
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Ignition</div>
              <div class="text-muted small">{{ vehicle.ignition ? 'On' : 'Off' }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Last Ignition On</div>
              <div class="text-muted small">{{ vehicle.last_ignition_on || 'N/A' }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Last Ignition Off</div>
              <div class="text-muted small">{{ vehicle.last_ignition_off || 'N/A' }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Speed</div>
              <div class="text-muted small">{{ vehicle.speed_display || vehicle.speed + ' km/h' }}</div>
            </div>

            <!-- Row 3 -->
            <div class="col-6 col-md-3" v-if="showZoneName">
              <div class="fw-bold mb-1 text-dark">Zone Name</div>
              <div class="text-muted small">{{ vehicle.zone_name || 'N/A' }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Last Report</div>
              <div class="text-muted small">{{ vehicle.last_update }}</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="fw-bold mb-1 text-dark">Status</div>
              <div class="small" :class="vehicle.status === 'Online' ? 'text-success fw-bold' : (vehicle.status === 'Offline' ? 'text-danger fw-bold' : 'text-muted')">
                <i v-if="vehicle.status === 'Online'" class="bi bi-wifi me-1"></i>
                <i v-if="vehicle.status === 'Offline'" class="bi bi-wifi-off me-1"></i>
                {{ vehicle.status || 'Positioning Log' }}
              </div>
            </div>
          </div>

          <!-- Location -->
          <div class="mb-4">
            <div class="fw-bold mb-1 text-dark">Location</div>
            <a :href="`https://maps.google.com/?q=${vehicle.lat},${vehicle.lng}`" target="_blank" class="text-decoration-none text-info small">
                {{ vehicle.address || `${vehicle.lat}, ${vehicle.lng}` }}
            </a>
          </div>

          <!-- Button removed as per request -->
        </div>
      </div>
      <div class="modal-body p-5 text-center" v-else>
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  vehicle: { type: Object, default: null },
  showZoneName: { type: Boolean, default: true }
});

defineEmits(['close', 'change-status']);
</script>

<style scoped>
.driver-modal-overlay { position: fixed; inset: 0; background: rgba(9, 12, 28, 0.4); backdrop-filter: blur(2px); z-index: 1050; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 24px; }
.driver-modal { background: #fff; border-radius: 16px; box-shadow: 0 10px 24px rgba(0,0,0,.15); width: 100%; max-width: 800px; font-family: var(--font-sans); }
.modal-body { padding: 16px; }
.bg-success-subtle { background-color: #d1e7dd; }
.bg-danger-subtle { background-color: #f8d7da; }
@media (max-width: 576px) {
  .driver-modal { border-radius: 0; max-width: none; height: 100vh; }
}
</style>
