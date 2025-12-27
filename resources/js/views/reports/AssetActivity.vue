<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Asset Activity Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Asset Activity Report</h4>

    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small">Duration</label>
            <input type="text" class="form-control" placeholder="dd/mm/yyyy - dd/mm/yyyy" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Vehicle</label>
            <select class="form-select">
              <option>-- Select an Vehicle --</option>
              <option>VGPS2563</option>
              <option>VHCL-1006</option>
              <option>VHCL-1009</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Search Filter</label>
            <select class="form-select">
              <option>-- Search Filter --</option>
              <option>Door Sensor</option>
              <option>Exceptions</option>
              <option>Power Disconnection</option>
              <option>Ignition</option>
              <option>Idling</option>
              <option>Seatbelt</option>
              <option>Trailer</option>
            </select>
          </div>
          <div class="col-12 col-md-3 text-md-end">
            <button class="btn btn-app-dark w-100">Submit</button>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Report Format</label>
            <select class="form-select">
              <option>— Report Format —</option>
              <option>Website</option>
              <option>Map</option>
              <option>Excel</option>
              <option>PDF</option>
              <option>Google Earth KML</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Map Option</label>
            <select class="form-select">
              <option>— Map Option —</option>
              <option>Icon</option>
              <option>Line</option>
              <option>Playback</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Vehicle Activity Report Result</h6></div>
      <div class="card-body">
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
            <div class="fw-semibold">2024/03/11 00:00 - 2024/03/20 23:59</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">View Type</div>
            <div class="fw-semibold">Summery</div>
          </div>
          <div class="col-12">
            <div class="small text-muted">Remarks</div>
            <div class="fw-semibold">Average fuel consumption calculated up to 6 months of data. Fuel refill amount shown for duration selected. Fuel refill amount does not imply fuel consumed in the same duration selected.</div>
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
                <th>Activity Duration (Hours)</th>
                <th class="text-center">Idling Duration (Hours)</th>
                <th class="text-center">Utilisation (%)</th>
                <th class="text-center">Avg. Fuel Consumption</th>
                <th class="text-center">Fuel Refill (L)</th>
                <th class="text-center">Fuel Refill (Frequency)</th>
                <th class="text-center">Max Speed (Km/h)</th>
                <th class="text-center">Action</th>
              </tr>
              <tr class="text-muted">
                <th>Avg. Per Day</th>
                <th class="text-center">Total • Avg. Per Day</th>
                <th class="text-center">—</th>
                <th class="text-center">Avg. Litres • Avg. KM/L</th>
                <th class="text-center">Total</th>
                <th class="text-center">Count</th>
                <th class="text-center">—</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.key">
                <td>{{ row.activePerDay }}</td>
                <td class="text-center">{{ row.idleTotal }} • {{ row.idlePerDay }}</td>
                <td class="text-center">{{ row.util }}</td>
                <td class="text-center">{{ row.avgLitres }} • {{ row.avgKml }}</td>
                <td class="text-center">{{ row.refillL }}</td>
                <td class="text-center">{{ row.refillFreq }}</td>
                <td class="text-center">{{ row.speed }}</td>
                <td class="text-center">
                  <i class="bi bi-eye text-primary me-2" role="button"></i>
                  <i class="bi bi-trash text-danger" role="button"></i>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">Showing 1 to 10 of 10 results</div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item disabled"><button class="page-link">‹</button></li>
            <li class="page-item active"><button class="page-link">1</button></li>
            <li class="page-item"><button class="page-link">2</button></li>
            <li class="page-item"><button class="page-link">3</button></li>
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
  { key: 1, activePerDay: '8.5', idleTotal: '42.5', idlePerDay: '4.2', util: '85%', avgLitres: '12.5', avgKml: '8.2', refillL: '450', refillFreq: '3', speed: '95' },
  { key: 2, activePerDay: '7.2', idleTotal: '35.0', idlePerDay: '3.5', util: '78%', avgLitres: '10.8', avgKml: '9.5', refillL: '380', refillFreq: '2', speed: '88' },
  { key: 3, activePerDay: '9.1', idleTotal: '55.2', idlePerDay: '5.5', util: '92%', avgLitres: '14.2', avgKml: '7.8', refillL: '520', refillFreq: '4', speed: '102' },
  { key: 4, activePerDay: '6.5', idleTotal: '28.4', idlePerDay: '2.8', util: '65%', avgLitres: '9.5', avgKml: '10.2', refillL: '320', refillFreq: '2', speed: '85' },
  { key: 5, activePerDay: '8.8', idleTotal: '48.6', idlePerDay: '4.8', util: '88%', avgLitres: '13.5', avgKml: '8.0', refillL: '480', refillFreq: '3', speed: '98' },
  { key: 6, activePerDay: '7.9', idleTotal: '38.2', idlePerDay: '3.8', util: '82%', avgLitres: '11.8', avgKml: '8.8', refillL: '410', refillFreq: '3', speed: '92' },
  { key: 7, activePerDay: '5.5', idleTotal: '22.0', idlePerDay: '2.2', util: '55%', avgLitres: '8.2', avgKml: '11.5', refillL: '250', refillFreq: '1', speed: '80' },
  { key: 8, activePerDay: '9.5', idleTotal: '60.5', idlePerDay: '6.0', util: '95%', avgLitres: '15.5', avgKml: '7.2', refillL: '580', refillFreq: '4', speed: '105' },
  { key: 9, activePerDay: '6.8', idleTotal: '32.5', idlePerDay: '3.2', util: '72%', avgLitres: '10.2', avgKml: '9.8', refillL: '350', refillFreq: '2', speed: '86' },
  { key: 10, activePerDay: '8.2', idleTotal: '45.0', idlePerDay: '4.5', util: '86%', avgLitres: '12.8', avgKml: '8.5', refillL: '460', refillFreq: '3', speed: '94' },
]);
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; }
tbody tr td { font-size: 13px; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
</style>
