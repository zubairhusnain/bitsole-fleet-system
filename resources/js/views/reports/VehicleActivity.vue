<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Activity Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Vehicle Activity Report</h4>
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
              <option>--Select an Vehicle--</option>
              <option>VGPS2563</option>
              <option>VHCL-1006</option>
              <option>VHCL-1009</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Search Filter</label>
            <input type="text" class="form-control" placeholder="-- Search Filter --" />
          </div>
          <div class="col-12 col-md-3">
             <!-- Empty or move button here if layout requires -->
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Report Format</label>
            <input type="text" class="form-control" placeholder="-- Report Format --" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">Map Option</label>
            <input type="text" class="form-control" placeholder="-- Map Option --" />
          </div>
          <div class="col-12 col-md-6 text-md-end">
             <button class="btn btn-primary px-4">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Vehicle Activity Report Result</h6></div>
      <div class="card-body pt-2">
        <div class="row g-3 bg-light rounded-3 p-3 mx-1">
          <div class="col-12 col-md-3">
            <div class="small text-muted fw-semibold">Vehicle ID</div>
            <div class="fw-bold text-dark">#VGPS2563</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted fw-semibold">Device ID</div>
            <div class="fw-bold text-dark">#43499829</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted fw-semibold">Duration</div>
            <div class="fw-bold text-dark">2024/03/11 00:00 - 2024/03/20 23:59</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted fw-semibold">View Type</div>
            <div class="fw-bold text-dark">Summary</div>
          </div>
          <div class="col-12 mt-3 pt-3 border-top border-secondary-subtle">
            <div class="small text-muted fw-bold">Remarks</div>
            <div class="small text-muted">Average fuel consumption calculated up to 6 months of data. Fuel refill shown for duration selected. Fuel refill amount does not imply fuel consumed in the same duration selected.</div>
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
                <th class="py-2 ps-3">Date</th>
                <th class="py-2">Time</th>
                <th class="py-2">Status</th>
                <th class="py-2">Longitude</th>
                <th class="py-2">Latitude</th>
                <th class="py-2">Location</th>
                <th class="py-2 text-center">Direction</th>
                <th class="py-2">Speed</th>
                <th class="py-2">GSM Signal</th>
                <th class="py-2">GPS Signal</th>
                <th class="py-2">Power</th>
                <th class="py-2">Ignition</th>
                <th class="py-2 pe-3">Fuel</th>
              </tr>
            </thead>
            <tbody>
              <tr class="table-section">
                <td colspan="13" class="fw-semibold ps-3 py-2">11-03-2024 <span class="text-muted fw-normal">Monday</span></td>
              </tr>
              <tr v-for="row in monday" :key="row.t">
                <td class="ps-3">{{ row.date }}</td>
                <td>{{ row.t }}</td>
                <td>{{ row.status }}</td>
                <td>{{ row.lon }}</td>
                <td>{{ row.lat }}</td>
                <td>{{ row.loc }}</td>
                <td class="text-center"><i class="bi bi-arrow-right"></i></td>
                <td>{{ row.speed }}</td>
                <td><i class="bi bi-reception-4"></i> {{ row.gsm }}</td>
                <td>{{ row.gps }}</td>
                <td class="text-success">{{ row.power }}</td>
                <td><span :class="row.ignition === 'ON' ? 'badge bg-success' : 'badge bg-secondary'">{{ row.ignition }}</span></td>
                <td class="pe-3">{{ row.fuel }}</td>
              </tr>
              <tr class="table-section">
                <td colspan="13" class="fw-semibold ps-3 py-2">12-03-2024 <span class="text-muted fw-normal">Tuesday</span></td>
              </tr>
              <tr v-for="row in tuesday" :key="row.t">
                <td class="ps-3">{{ row.date }}</td>
                <td>{{ row.t }}</td>
                <td>{{ row.status }}</td>
                <td>{{ row.lon }}</td>
                <td>{{ row.lat }}</td>
                <td>{{ row.loc }}</td>
                <td class="text-center"><i class="bi bi-arrow-right"></i></td>
                <td>{{ row.speed }}</td>
                <td><i class="bi bi-reception-4"></i> {{ row.gsm }}</td>
                <td>{{ row.gps }}</td>
                <td class="text-success">{{ row.power }}</td>
                <td><span :class="row.ignition === 'ON' ? 'badge bg-success' : 'badge bg-secondary'">{{ row.ignition }}</span></td>
                <td class="pe-3">{{ row.fuel }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
        <div class="text-muted small me-auto">Showing 1 to 16 of 1079 results</div>
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

const monday = ref([
  { date: '11-03-2024', t: '05:05:44', status: 'Position Log', lon: '101.71032', lat: '3.13032', loc: 'LORONG LOKE YEW JALAN LOKE YEW, PUDU, 55200 KUALA LUMPUR', gsm: 'Fair', gps: 'Fair', power: '13.4V', ignition: 'ON', fuel: '17.6 L', speed: '0 km/h' },
  { date: '11-03-2024', t: '05:25:11', status: 'IgnitionOn', lon: '101.71032', lat: '3.13032', loc: 'MEGAN PHLEO PROMENADE BLOCK B, KAMPUNG BARU, 55100 KUALA LUMPUR', gsm: 'Good', gps: 'Good', power: '13.6V', ignition: 'OFF', fuel: '17.6 L', speed: '0 km/h' },
  { date: '11-03-2024', t: '06:02:45', status: 'Auto-Geofencing', lon: '101.71032', lat: '3.13032', loc: 'PLAZA OSK', gsm: 'Weak', gps: 'Weak', power: '13.8V', ignition: 'ON', fuel: '17.6 L', speed: '0 km/h' },
  { date: '11-03-2024', t: '14:23:47', status: 'Trip Stop', lon: '101.71032', lat: '3.13032', loc: 'JALAN CTA 2, KUALA LUMPUR INTERNATIONAL AIRPORT (KUL), KLIA, SELANGOR', gsm: 'Fair', gps: 'Fair', power: '14.0V', ignition: 'OFF', fuel: '17.6 L', speed: '0 km/h' },
  { date: '11-03-2024', t: '17:07:47', status: 'Door Close', lon: '101.71032', lat: '3.13032', loc: 'GAMBANG R&R W TO E', gsm: 'Good', gps: 'Good', power: '14.2V', ignition: 'ON', fuel: '17.6 L', speed: '0 km/h' },
]);
const tuesday = ref([
  { date: '12-03-2024', t: '05:05:44', status: 'Stop Journey', lon: '101.71032', lat: '3.13032', loc: 'LORONG LOKE YEW JALAN LOKE YEW, PUDU, 55200 KUALA LUMPUR', gsm: 'Weak', gps: 'Weak', power: '14.4V', ignition: 'OFF', fuel: '17.6 L', speed: '0 km/h' },
  { date: '12-03-2024', t: '05:25:11', status: 'Door Open', lon: '101.71032', lat: '3.13032', loc: 'MEGAN PHLEO PROMENADE BLOCK B, KAMPUNG BARU, 55100 KUALA LUMPUR', gsm: 'Fair', gps: 'Fair', power: '14.6V', ignition: 'ON', fuel: '17.6 L', speed: '0 km/h' },
  { date: '12-03-2024', t: '06:02:45', status: 'Position Log', lon: '101.71032', lat: '3.13032', loc: 'PLAZA OSK', gsm: 'Good', gps: 'Good', power: '14.8V', ignition: 'OFF', fuel: '17.6 L', speed: '0 km/h' },
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
