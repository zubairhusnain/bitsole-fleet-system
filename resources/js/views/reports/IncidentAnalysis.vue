<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Incident Analysis Report</li>
      </ol>
    </div>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">Incident Analysis Report</h4>
      <RouterLink to="/reports/incident/new" class="btn btn-dark btn-sm px-3 py-2">Add New Incident</RouterLink>
    </div>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body pt-2">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-5">
            <label class="form-label small fw-semibold text-muted">Date</label>
            <div class="input-group">
              <input type="text" class="form-control" placeholder="dd/mm/yyyy" />
              <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
            </div>
          </div>
          <div class="col-12 col-md-5">
            <label class="form-label small fw-semibold text-muted">Vehicle</label>
            <input type="text" class="form-control" placeholder="--Select an Vehicle --" />
          </div>
          <div class="col-12 col-md-2">
            <button class="btn btn-info text-white w-100">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 table-striped w-100 text-nowrap">
            <thead class="table-dark">
              <tr>
                <th class="ps-3">Vehicle ID</th>
                <th>Type/Model</th>
                <th>Incident Start</th>
                <th>Incident End</th>
                <th>Impact Date/Time</th>
                <th>Driver</th>
                <th>Description</th>
                <th>Remarks</th>
                <th class="text-center pe-3">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.key">
                <td class="ps-3">{{ row.vehicleId }}</td>
                <td>{{ row.typeModel }}</td>
                <td>{{ row.incidentStart }}</td>
                <td>{{ row.incidentEnd }}</td>
                <td>{{ row.impactTime }}</td>
                <td>{{ row.driver }}</td>
                <td class="text-truncate" style="max-width: 200px;">{{ row.description }}</td>
                <td class="text-truncate" style="max-width: 200px;">{{ row.remarks }}</td>
                <td class="text-center pe-3">
                  <button class="btn btn-sm p-0 text-dark me-2"><i class="bi bi-file-earmark-excel fs-6"></i></button>
                  <button class="btn btn-sm p-0 text-primary"><i class="bi bi-file-earmark-pdf fs-6"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
        <div class="text-muted small me-auto">Showing 1 to {{ rows.length }} of 1079 results</div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item disabled"><button class="page-link"><i class="bi bi-chevron-left"></i></button></li>
            <li class="page-item active"><button class="page-link">1</button></li>
            <li class="page-item"><button class="page-link">2</button></li>
            <li class="page-item"><button class="page-link">3</button></li>
            <li class="page-item"><button class="page-link">4</button></li>
            <li class="page-item"><button class="page-link">5</button></li>
            <li class="page-item"><button class="page-link"><i class="bi bi-chevron-right"></i></button></li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const rows = ref([
  { key: 1, vehicleId: 'VHCL-1001', typeModel: 'MPV - STARIA', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Sophia Martinez', description: 'This report details a single incident...', remarks: 'This report details a single incident...' },
  { key: 2, vehicleId: 'VHCL-1002', typeModel: 'CAR - Camry 2.5V', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Liam Johnson', description: 'This report details a single incident...', remarks: 'N/A' },
  { key: 3, vehicleId: 'VHCL-1003', typeModel: 'CAR - Accord', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Ava Smith', description: 'This report details a single incident...', remarks: 'This report details a single incident...' },
  { key: 4, vehicleId: 'VHCL-1004', typeModel: 'CAR - Camry 2.5V', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Mason Brown', description: 'This report details a single incident...', remarks: 'This report details a single incident...' },
  { key: 5, vehicleId: 'VHCL-1005', typeModel: 'CAR - Accord', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Isabella Garcia', description: 'This report details a single incident...', remarks: 'N/A' },
  { key: 6, vehicleId: 'VHCL-1006', typeModel: 'CAR - Accord', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Noah Wilson', description: 'This report details a single incident...', remarks: 'N/A' },
  { key: 7, vehicleId: 'VHCL-1002', typeModel: 'CAR - Camry 2.5V', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Olivia Taylor', description: 'This report details a single incident...', remarks: 'N/A' },
  { key: 8, vehicleId: 'VHCL-1003', typeModel: 'CAR - Accord', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Lucas Anderson', description: 'This report details a single incident...', remarks: 'This report details a single incident...' },
  { key: 9, vehicleId: 'VHCL-1004', typeModel: 'CAR - Camry 2.5V', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Mia Thomas', description: 'This report details a single incident...', remarks: 'N/A' },
  { key: 10, vehicleId: 'VHCL-1006', typeModel: 'CAR - Accord', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Jacob Jackson', description: 'This report details a single incident...', remarks: 'This report details a single incident...' },
  { key: 11, vehicleId: 'VHCL-1005', typeModel: 'CAR - Accord', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Charlotte White', description: 'This report details a single incident...', remarks: 'N/A' },
  { key: 12, vehicleId: 'VHCL-1002', typeModel: 'CAR - Camry 2.5V', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'Amelia Harris', description: 'This report details a single incident...', remarks: 'N/A' },
  { key: 13, vehicleId: 'VHCL-1003', typeModel: 'CAR - Accord', incidentStart: '09-08-2025', incidentEnd: '14-08-2025', impactTime: '09-08-2025 19:00', driver: 'William Thompson', description: 'This report details a single incident...', remarks: 'This report details a single incident...' },
]);
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; font-weight: 500; font-size: 0.85rem; }
tbody tr td { font-size: 0.85rem; color: #4b5563; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
.btn-info { background-color: #0ea5e9; border-color: #0ea5e9; }
.btn-info:hover { background-color: #0284c7; border-color: #0284c7; }
</style>
