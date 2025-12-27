<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Incident Analysis Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Incident Analysis Report</h4>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body pt-2">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Duration</label>
            <div class="input-group">
              <input type="text" class="form-control" placeholder="dd/mm/yyyy - dd/mm/yyyy" />
              <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Vehicle</label>
            <select class="form-select text-muted">
              <option>-- Select Vehicle --</option>
              <option>VGPS2563</option>
              <option>VHCL-1006</option>
              <option>VHCL-1009</option>
              <option>VHCL-1011</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Incident Type</label>
            <select class="form-select text-muted">
              <option>— Select Type —</option>
              <option>Overspeed</option>
              <option>Harsh Braking</option>
              <option>Harsh Acceleration</option>
              <option>Power Disconnect</option>
              <option>Door Open</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Severity</label>
            <select class="form-select text-muted">
              <option>— Any —</option>
              <option>Low</option>
              <option>Medium</option>
              <option>High</option>
              <option>Critical</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">View Type</label>
            <select class="form-select text-muted">
              <option>Summary</option>
              <option>Daily Breakdown</option>
              <option>Monthly Summary</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Report Format</label>
            <select class="form-select text-muted">
              <option>— Report Format —</option>
              <option>Website</option>
              <option>Excel</option>
              <option>PDF</option>
            </select>
          </div>
          <div class="col-12 col-md-3 text-md-end ms-md-auto">
            <button class="btn btn-primary w-100">Search</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Incident Report Result</h6></div>
      <div class="card-body">
        <div class="bg-light rounded-3 p-3">
          <div class="row g-3">
            <div class="col-12 col-md-3">
              <div class="small text-muted">Vehicle ID</div>
              <div class="fw-semibold">VGPS2563</div>
            </div>
            <div class="col-12 col-md-3">
              <div class="small text-muted">Device ID</div>
              <div class="fw-semibold">#34939829</div>
            </div>
            <div class="col-12 col-md-3">
            <div class="small text-muted">Duration</div>
            <div class="fw-semibold">2025/08/26 00:00 - 2025/08/31 23:59</div>
          </div>
            <div class="col-12 col-md-3">
              <div class="small text-muted">View Type</div>
              <div class="fw-semibold">Summary</div>
            </div>
            <div class="col-12">
              <div class="small text-muted">Remarks</div>
              <div class="fw-semibold">Incident analysis compiled from selected duration. Severity reflects configured thresholds. Map and playback options are available in detailed views.</div>
            </div>
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
                <th>Date & Time</th>
                <th>Incident Type</th>
                <th class="text-center">Severity</th>
                <th class="text-center">Vehicle ID</th>
                <th class="text-center">Driver</th>
                <th>Location</th>
                <th class="text-center">Duration</th>
                <th class="text-center">Action</th>
              </tr>
              <tr class="text-muted">
                <th>—</th>
                <th>—</th>
                <th class="text-center">Low • High</th>
                <th class="text-center">—</th>
                <th class="text-center">—</th>
                <th>—</th>
                <th class="text-center">hh:mm:ss</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.key">
                <td>{{ row.time }}</td>
                <td>{{ row.type }}</td>
                <td class="text-center">{{ row.severity }}</td>
                <td class="text-center">{{ row.vehicle }}</td>
                <td class="text-center">{{ row.driver }}</td>
                <td>{{ row.location }}</td>
                <td class="text-center">{{ row.duration }}</td>
                <td class="text-center">
                  <button class="btn btn-sm p-0 text-primary me-2"><i class="bi bi-eye"></i></button>
                  <button class="btn btn-sm p-0 text-danger"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
        <div class="text-muted small me-auto">Showing 1 to 10 of 10 results</div>
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
  { key: 1, time: '2024/03/11 09:15:22', type: 'Overspeed', severity: 'High', vehicle: 'VGPS2563', driver: 'Adam', location: 'Kuala Lumpur, MY', duration: '00:03:12' },
  { key: 2, time: '2024/03/12 14:41:03', type: 'Harsh Braking', severity: 'Medium', vehicle: 'VHCL-1006', driver: 'Bella', location: 'Shah Alam, MY', duration: '00:00:18' },
  { key: 3, time: '2024/03/13 07:05:10', type: 'Power Disconnect', severity: 'Critical', vehicle: 'VHCL-1009', driver: 'Chong', location: 'Putrajaya, MY', duration: '00:10:45' },
  { key: 4, time: '2024/03/14 17:20:55', type: 'Door Open', severity: 'Low', vehicle: 'VHCL-1011', driver: 'Danish', location: 'Johor Bahru, MY', duration: '00:01:02' },
  { key: 5, time: '2024/03/15 12:33:47', type: 'Harsh Acceleration', severity: 'Medium', vehicle: 'VGPS2563', driver: 'Adam', location: 'Seremban, MY', duration: '00:00:22' },
  { key: 6, time: '2024/03/16 08:12:19', type: 'Overspeed', severity: 'High', vehicle: 'VHCL-1006', driver: 'Bella', location: 'Klang, MY', duration: '00:02:35' },
  { key: 7, time: '2024/03/17 19:40:01', type: 'Door Open', severity: 'Low', vehicle: 'VHCL-1009', driver: 'Chong', location: 'Kajang, MY', duration: '00:00:58' },
  { key: 8, time: '2024/03/18 13:27:44', type: 'Harsh Braking', severity: 'Medium', vehicle: 'VHCL-1011', driver: 'Danish', location: 'Bangi, MY', duration: '00:00:15' },
  { key: 9, time: '2024/03/19 11:01:06', type: 'Power Disconnect', severity: 'Critical', vehicle: 'VGPS2563', driver: 'Adam', location: 'Cyberjaya, MY', duration: '00:05:20' },
  { key: 10, time: '2024/03/20 16:56:30', type: 'Overspeed', severity: 'High', vehicle: 'VHCL-1011', driver: 'Danish', location: 'Melaka, MY', duration: '00:04:11' },
]);
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; }
tbody tr td { font-size: 13px; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
</style>
