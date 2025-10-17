<template>
  <div>
    <div class="row mb-3">
      <div class="col">
        <h4 class="mb-0">Fuel Management</h4>
        <small class="text-muted">Consumption and costs</small>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header"><h6 class="mb-0">Fuel Usage</h6></div>
          <div class="card-body"><div class="placeholder-glow"><span class="placeholder col-12" style="height:200px"></span></div></div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header"><h6 class="mb-0">Costs</h6></div>
          <div class="card-body"><div class="placeholder-glow"><span class="placeholder col-12" style="height:200px"></span></div></div>
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header"><h6 class="mb-0">Fuel Logs</h6></div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Date</th>
                <th>Vehicle</th>
                <th>Liters</th>
                <th>Cost</th>
              </tr>
            </thead>
            <tbody>
              <tr><td>2025-08-01</td><td>TRK-001</td><td>56</td><td>$98.34</td></tr>
              <tr><td>2025-08-02</td><td>VAN-023</td><td>34</td><td>$55.20</td></tr>
              <tr><td>2025-08-03</td><td>BUS-112</td><td>88</td><td>$149.00</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Data integration later
</script>

<style scoped>
.placeholder { display: block; background-color: rgba(0,0,0,.075); }
</style>