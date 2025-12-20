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
                <th>(Hours)</th>
                <th class="text-center">Idling Duration (Hours)</th>
                <th class="text-center">Utilisation(%)</th>
                <th class="text-center">Avg. Fuel Consumption</th>
                <th class="text-center">Fuel Refill (L)</th>
                <th class="text-center">Fuel Refill (Frequency)</th>
                <th class="text-center">Speed (Km/h)</th>
                <th class="text-center">Action</th>
              </tr>
              <tr class="text-muted">
                <th>Avg. Per Day</th>
                <th class="text-center">Total • Avg. Per Day</th>
                <th class="text-center">—</th>
                <th class="text-center">Avg. Litres • Avg. KM/L</th>
                <th class="text-center">—</th>
                <th class="text-center">—</th>
                <th class="text-center">—</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.key">
                <td>{{ row.avgPerDay }}</td>
                <td class="text-center">{{ row.idleTotal }} • {{ row.idlePerDay }}</td>
                <td class="text-center">{{ row.util }}</td>
                <td class="text-center">{{ row.avgLitres }} • {{ row.avgKml }}</td>
                <td class="text-center">{{ row.refillL }}</td>
                <td class="text-center">{{ row.refillFreq }}</td>
                <td class="text-center">{{ row.speed }}</td>
                <td class="text-center">
                  <i class="bi bi-eye text-primary me-2"></i>
                  <i class="bi bi-trash text-danger"></i>
                </td>
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
  { key: 1, avgPerDay: '175.6', idleTotal: '5680.5', idlePerDay: '175.6', util: 'B345-3920-5436', avgLitres: '412.75', avgKml: '175.6', refillL: '321,450 KM', refillFreq: '12', speed: '0 km/h' },
  { key: 2, avgPerDay: '130.0', idleTotal: '3000.8', idlePerDay: '130.0', util: 'C567-3890-2845', avgLitres: '298.90', avgKml: '130.0', refillL: '154,200 KM', refillFreq: '10', speed: '0 km/h' },
  { key: 3, avgPerDay: '140.2', idleTotal: '4550.0', idlePerDay: '140.2', util: 'D666-2399-7743', avgLitres: '376.85', avgKml: '140.2', refillL: '245,600 KM', refillFreq: '9', speed: '0 km/h' },
  { key: 4, avgPerDay: '160.4', idleTotal: '5000.4', idlePerDay: '160.4', util: 'A123-8470-9032', avgLitres: '450.00', avgKml: '160.4', refillL: '290,300 KM', refillFreq: '11', speed: '0 km/h' },
  { key: 5, avgPerDay: '190.5', idleTotal: '6200.7', idlePerDay: '190.5', util: 'E890-5623-0012', avgLitres: '520.25', avgKml: '190.5', refillL: '410,100 KM', refillFreq: '14', speed: '0 km/h' },
  { key: 6, avgPerDay: '145.8', idleTotal: '4500.1', idlePerDay: '145.8', util: 'F234-9502-1287', avgLitres: '389.90', avgKml: '145.8', refillL: '123,000 KM', refillFreq: '7', speed: '0 km/h' },
  { key: 7, avgPerDay: '155.4', idleTotal: '4700.3', idlePerDay: '155.4', util: 'G456-7321-8745', avgLitres: '395.75', avgKml: '155.4', refillL: '200,900 KM', refillFreq: '8', speed: '0 km/h' },
  { key: 8, avgPerDay: '185.0', idleTotal: '5200.2', idlePerDay: '185.0', util: 'H789-3491-0010', avgLitres: '460.80', avgKml: '185.0', refillL: '370,200 KM', refillFreq: '13', speed: '0 km/h' },
  { key: 9, avgPerDay: '210.7', idleTotal: '5900.6', idlePerDay: '210.7', util: 'I901-4783-5629', avgLitres: '510.55', avgKml: '210.7', refillL: '500,300 KM', refillFreq: '15', speed: '0 km/h' },
  { key: 10, avgPerDay: '195.2', idleTotal: '5300.9', idlePerDay: '195.2', util: 'J876-5432-1098', avgLitres: '485.60', avgKml: '195.2', refillL: '380,450 KM', refillFreq: '12', speed: '0 km/h' },
]);
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; }
tbody tr td { font-size: 13px; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
</style>
