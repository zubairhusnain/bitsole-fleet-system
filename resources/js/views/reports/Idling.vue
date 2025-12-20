<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Idling Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Idling Report</h4>

    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label small">Duration</label>
            <input type="text" class="form-control" placeholder="dd/mm/yyyy - dd/mm/yyyy" />
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Vehicle</label>
            <select class="form-select">
              <option>--Select an Vehicle--</option>
              <option>VGPS2563</option>
              <option>VHCL-1006</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Idling Time Filter</label>
            <select class="form-select">
              <option>-- Report Format --</option>
              <option>Less then 2 Minutes</option>
              <option>Less then 3 Minutes</option>
              <option>Less then 5 Minutes</option>
              <option>Greater then 2 Minutes</option>
              <option>Greater then 5 Minutes</option>
              <option>Greater then 10 Minutes</option>
              <option>Greater then 20 Minutes</option>
              <option>Greater then 30 Minutes</option>
              <option>Greater then 1 Hour</option>
              <option>Greater then 2 Hours</option>
              <option>Greater then 5 Hours</option>
            </select>
          </div>
          <div class="col-12 col-md-1 text-md-end">
            <button class="btn btn-app-dark w-100">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Idling Report Result</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-3">
            <div class="small text-muted">Vehicle ID</div>
            <div class="fw-semibold">#VGPS2563</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Device ID</div>
            <div class="fw-semibold">#84349829</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Duration</div>
            <div class="fw-semibold">2024/03/11 00:00 - 2024/03/20 23:59</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Idle Duration Criteria</div>
            <div class="fw-semibold">&gt; 2 minute(s)</div>
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
                <th>Date</th>
                <th>Start Time</th>
                <th>End Time</th>
                <th>Duration</th>
                <th>Location</th>
                <th>Longitude</th>
                <th>Latitude</th>
              </tr>
            </thead>
            <tbody>
              <tr class="table-section">
                <td colspan="7" class="fw-semibold">11-03-2024 <span class="text-muted">Monday</span></td>
              </tr>
              <tr v-for="row in monday" :key="row.t1">
                <td>{{ row.date }}</td>
                <td>{{ row.t1 }}</td>
                <td>{{ row.t2 }}</td>
                <td>{{ row.dur }}</td>
                <td>{{ row.loc }}</td>
                <td>{{ row.lon }}</td>
                <td>{{ row.lat }}</td>
              </tr>
              <tr class="table-section">
                <td colspan="7" class="fw-semibold">12-03-2024 <span class="text-muted">Tuesday</span></td>
              </tr>
              <tr v-for="row in tuesday" :key="row.t1">
                <td>{{ row.date }}</td>
                <td>{{ row.t1 }}</td>
                <td>{{ row.t2 }}</td>
                <td>{{ row.dur }}</td>
                <td>{{ row.loc }}</td>
                <td>{{ row.lon }}</td>
                <td>{{ row.lat }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">Showing 1 to 8 of 8 results</div>
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
const monday = ref([
  { date: '11-03-2024', t1: '05:05:44', t2: '05:07:49', dur: '2m 5s', loc: 'LORONG LOKE YEW JALAN LOKE YEW, PUDU, 55200 KUALA LUMPUR', lon: '101.71032', lat: '3.13032' },
  { date: '11-03-2024', t1: '05:25:11', t2: '05:27:50', dur: '2m 39s', loc: 'MEGAN PHLEO PROMENADE BLOCK B, KAMPUNG BARU, 55100 KUALA LUMPUR', lon: '101.71032', lat: '3.13032' },
  { date: '11-03-2024', t1: '06:02:45', t2: '06:05:02', dur: '2m 17s', loc: 'PLAZA OSK', lon: '101.71032', lat: '3.13032' },
  { date: '11-03-2024', t1: '14:23:47', t2: '14:29:49', dur: '6m 2s', loc: 'JALAN CTA 2, KUALA LUMPUR INTERNATIONAL AIRPORT (KUL), KLIA, SELANGOR', lon: '101.71032', lat: '3.13032' },
  { date: '11-03-2024', t1: '17:07:47', t2: '17:09:56', dur: '2m 9s', loc: 'GAMBANG R&R W TO E', lon: '101.71032', lat: '3.13032' },
]);
const tuesday = ref([
  { date: '12-03-2024', t1: '05:05:44', t2: '05:07:49', dur: '2m 5s', loc: 'LORONG LOKE YEW JALAN LOKE YEW, PUDU, 55200 KUALA LUMPUR', lon: '101.71032', lat: '3.13032' },
  { date: '12-03-2024', t1: '05:25:11', t2: '05:27:50', dur: '2m 39s', loc: 'MEGAN PHLEO PROMENADE BLOCK B, KAMPUNG BARU, 55100 KUALA LUMPUR', lon: '101.71032', lat: '3.13032' },
  { date: '12-03-2024', t1: '06:02:45', t2: '06:05:02', dur: '2m 17s', loc: 'PLAZA OSK', lon: '101.71032', lat: '3.13032' },
]);
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; }
.table-section { background: #f2f4f8; }
tbody tr td { font-size: 13px; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
</style>
