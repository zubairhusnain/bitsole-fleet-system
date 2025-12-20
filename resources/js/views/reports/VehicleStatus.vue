<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Status Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Vehicle Status Report</h4>
    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label small">Vehicle</label>
            <select class="form-select">
              <option>--Select an Vehicle--</option>
              <option>VHCL-1001</option>
              <option>VHCL-1002</option>
              <option>VHCL-1003</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Group</label>
            <select class="form-select">
              <option>-- Select Group --</option>
              <option>Group A</option>
              <option>Group B</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Report Format</label>
            <select class="form-select">
              <option>-- Report Format --</option>
              <option>Website</option>
              <option>Excel</option>
              <option>PDF</option>
            </select>
          </div>
          <div class="col-12 col-md-1 text-md-end">
            <button class="btn btn-app-dark w-100">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-striped">
            <thead class="table-dark">
              <tr>
                <th>Vehicle ID</th>
                <th>Owner</th>
                <th>Type/Model</th>
                <th>Device Model</th>
                <th>IMEI</th>
                <th>ICCID</th>
                <th>Odometer</th>
                <th>Power</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id">
                <td>{{ row.id }}</td>
                <td>{{ row.owner }}</td>
                <td>{{ row.type }}</td>
                <td>{{ row.device }}</td>
                <td>{{ row.imei }}</td>
                <td>{{ row.iccid }}</td>
                <td>{{ row.odo }}</td>
                <td class="text-primary">{{ row.power }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">Showing 1 to 16 of 1079 results</div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item disabled"><button class="page-link">‹</button></li>
            <li class="page-item active"><button class="page-link">1</button></li>
            <li class="page-item"><button class="page-link">2</button></li>
            <li class="page-item"><button class="page-link">3</button></li>
            <li class="page-item"><button class="page-link">4</button></li>
            <li class="page-item"><button class="page-link">5</button></li>
            <li class="page-item"><button class="page-link">›</button></li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const rows = ref([
  { id: 'VHCL-1001', owner: 'AMT - Sime Darby Rent A Car S...', type: 'MPV • STARIA 2.2V', device: 'FMC130', imei: '864292044667002', iccid: '8960012002723151360', odo: '181141', power: '13.4V' },
  { id: 'VHCL-1002', owner: 'AMT - Sime Darby Rent A Car S...', type: 'CAR • Camry', device: 'FMC130', imei: '864292044667002', iccid: '8960012002723151360', odo: '181141', power: '13.6V' },
  { id: 'VHCL-1002', owner: 'AMT - Sime Darby Rent A Car S...', type: 'CAR • Camry 2.5V', device: 'FMC130', imei: '864292044667002', iccid: '8960012002723151360', odo: '181141', power: '13.8V' },
  { id: 'VHCL-1003', owner: 'AMT - Sime Darby Rent A Car S...', type: 'CAR • Camry', device: 'FMC130', imei: '864292044667002', iccid: '8960012002723151360', odo: '181141', power: '13.8V' },
  { id: 'VHCL-1004', owner: 'AMT - Sime Darby Rent A Car S...', type: 'CAR • Camry 2.5V', device: 'FMC130', imei: '864292044667002', iccid: '8960012002723151360', odo: '181141', power: '14.0V' },
  { id: 'VHCL-1005', owner: 'AMT - Sime Darby Rent A Car S...', type: 'CAR • Camry', device: 'FMC130', imei: '864292044667002', iccid: '8960012002723151360', odo: '181141', power: '14.4V' },
  { id: 'VHCL-1006', owner: 'AMT - Sime Darby Rent A Car S...', type: 'CAR • Camry', device: 'FMC130', imei: '864292044667002', iccid: '8960012002723151360', odo: '181141', power: '14.6V' },
  { id: 'VHCL-1003', owner: 'AMT - Sime Darby Rent A Car S...', type: 'CAR • Camry', device: 'FMC130', imei: '864292044667002', iccid: '8960012002723151360', odo: '181141', power: '13.8V' },
]);
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; }
tbody tr td { font-size: 13px; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
</style>
