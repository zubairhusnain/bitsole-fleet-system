<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Create New Incident</li>
      </ol>
    </div>
    <h4 class="mb-3">Create New Incident</h4>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Incident Details</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <label class="form-label small">Date & Time</label>
            <input type="datetime-local" class="form-control" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Vehicle</label>
            <select class="form-select">
              <option>-- Select Vehicle --</option>
              <option>VGPS2563</option>
              <option>VHCL-1006</option>
              <option>VHCL-1009</option>
              <option>VHCL-1011</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Driver</label>
            <select class="form-select">
              <option>-- Select Driver --</option>
              <option>Adam</option>
              <option>Bella</option>
              <option>Chong</option>
              <option>Danish</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Incident Type</label>
            <select class="form-select">
              <option>— Select Type —</option>
              <option>Overspeed</option>
              <option>Harsh Braking</option>
              <option>Harsh Acceleration</option>
              <option>Power Disconnect</option>
              <option>Door Open</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Severity</label>
            <select class="form-select">
              <option>— Select Severity —</option>
              <option>Low</option>
              <option>Medium</option>
              <option>High</option>
              <option>Critical</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small">Description</label>
            <textarea class="form-control" rows="3" placeholder="Describe the incident"></textarea>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small">Location</label>
            <input type="text" class="form-control" placeholder="City, Country" />
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label small">Attachment</label>
            <input type="file" class="form-control" />
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
      <RouterLink to="/reports/incident-analysis" class="btn btn-secondary" style="min-width: 120px;">Cancel</RouterLink>
      <button class="btn btn-primary" style="min-width: 120px;">Save</button>
      <button class="btn btn-dark" style="min-width: 140px;">Save & Add More</button>
    </div>
  </div>
</template>

<script setup>
</script>

<style scoped>
.card-header h6 { font-weight: 600; }
</style>

