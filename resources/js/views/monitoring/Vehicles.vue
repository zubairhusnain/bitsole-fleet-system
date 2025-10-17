<template>
  <div>
    <div class="row mb-3">
      <div class="col">
        <h4 class="mb-0">Vehicles Monitoring</h4>
        <small class="text-muted">Real-time status and health overview</small>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header"><h5 class="card-title mb-0">Fleet Status</h5></div>
          <div class="card-body">
            <div class="row text-center">
              <div class="col"><div class="small-box bg-primary"><div class="inner"><h3>128</h3><p>Active</p></div></div></div>
              <div class="col"><div class="small-box bg-success"><div class="inner"><h3>36</h3><p>Idle</p></div></div></div>
              <div class="col"><div class="small-box bg-danger"><div class="inner"><h3>5</h3><p>Alerts</p></div></div></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header"><h5 class="card-title mb-0">Utilization</h5></div>
          <div class="card-body"><div class="placeholder-glow"><span class="placeholder col-12" style="height:180px"></span></div></div>
        </div>
      </div>
    </div>

    <div class="row g-4 mt-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0">Vehicles</h5>
            <div>
              <button class="btn btn-sm btn-outline-secondary">Export</button>
            </div>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Vehicle</th>
                    <th>Driver</th>
                    <th>Status</th>
                    <th>Last Update</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td>TRK-001</td><td>John D.</td><td><span class="badge text-bg-success">Moving</span></td><td>2m ago</td></tr>
                  <tr><td>VAN-023</td><td>Ali R.</td><td><span class="badge text-bg-warning">Idle</span></td><td>5m ago</td></tr>
                  <tr><td>BUS-112</td><td>Sara K.</td><td><span class="badge text-bg-danger">Alert</span></td><td>1m ago</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Static skeleton; data wiring to follow
</script>

<style scoped>
.small-box { border-radius: .5rem; padding: 1rem; color: #fff; }
</style>