<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Ranking Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Vehicle Ranking Report</h4>

    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-3">
            <label class="form-label small">Duration</label>
            <input type="text" class="form-control" placeholder="dd/mm/yyyy - dd/mm/yyyy" />
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Ranking Metric</label>
            <select class="form-select">
              <option>— Select Metric —</option>
              <option>Travelled Distance</option>
              <option>Utilisation</option>
              <option>Idling Duration</option>
              <option>Fuel Efficiency</option>
              <option>Speed Violations</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Report Format</label>
            <select class="form-select">
              <option>— Report Format —</option>
              <option>Website</option>
              <option>Excel</option>
              <option>PDF</option>
            </select>
          </div>
          <div class="col-12 col-md-3 text-md-end">
            <button class="btn btn-app-dark w-100">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Ranking Report Result</h6></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-3">
            <div class="small text-muted">Total Vehicles</div>
            <div class="fw-semibold">10 Vehicles</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Duration</div>
            <div class="fw-semibold">2024/03/11 - 2024/03/20</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Top Performer</div>
            <div class="fw-semibold">Apex Predator S (98.5)</div>
          </div>
          <div class="col-12 col-md-3">
            <div class="small text-muted">Average Score</div>
            <div class="fw-semibold">89.1</div>
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
                <th class="text-center">Rank</th>
                <th>Vehicle ID</th>
                <th>Vehicle Name</th>
                <th class="text-center">Travelled Distance (KM)</th>
                <th class="text-center">Idling Duration (Hours)</th>
                <th class="text-center">Utilisation(%)</th>
                <th class="text-center">Avg. Fuel Consumption (L)</th>
                <th class="text-center">Score</th>
              </tr>
              <tr class="text-muted">
                <th class="text-center">—</th>
                <th></th>
                <th></th>
                <th class="text-center">Total</th>
                <th class="text-center">Total</th>
                <th class="text-center">—</th>
                <th class="text-center">Avg. Litres</th>
                <th class="text-center">—</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.rank">
                <td class="text-center">
                  <span v-if="row.rank <= 3" class="me-1">
                    <i class="bi" :class="row.rank === 1 ? 'bi-trophy-fill text-warning' : (row.rank === 2 ? 'bi-trophy-fill text-secondary' : 'bi-trophy-fill text-bronze')"></i>
                  </span>
                  {{ row.rank }}
                </td>
                <td>{{ row.id }}</td>
                <td>{{ row.name }}</td>
                <td class="text-center">{{ row.distTotal }}</td>
                <td class="text-center">{{ row.idleTotal }}</td>
                <td class="text-center">{{ row.util }}</td>
                <td class="text-center">{{ row.avgLitres }}</td>
                <td class="text-center">{{ row.score }}</td>
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
  { rank: 1, id: 'VHCL-1010', name: 'Apex Predator S', distTotal: '5900.6', idleTotal: '210.7', util: '92%', avgLitres: '510.55', score: '98.5' },
  { rank: 2, id: 'VHCL-1006', name: 'Shadow Hunter 12', distTotal: '6200.7', idleTotal: '190.5', util: '90%', avgLitres: '520.25', score: '96.2' },
  { rank: 3, id: 'VHCL-1009', name: 'Meteor Strike 11', distTotal: '5200.2', idleTotal: '185.0', util: '88%', avgLitres: '460.80', score: '94.0' },
  { rank: 4, id: 'VHCL-1002', name: 'Turbo Hawk ZR', distTotal: '5680.5', idleTotal: '175.6', util: '86%', avgLitres: '412.75', score: '91.3' },
  { rank: 5, id: 'VHCL-1011', name: 'Falcon Cruiser Z', distTotal: '5300.9', idleTotal: '195.2', util: '85%', avgLitres: '485.60', score: '90.1' },
  { rank: 6, id: 'VHCL-1005', name: 'Raptor GT', distTotal: '5000.4', idleTotal: '160.4', util: '83%', avgLitres: '450.00', score: '88.7' },
  { rank: 7, id: 'VHCL-1004', name: 'Lunar Explorer 5', distTotal: '4550.0', idleTotal: '140.2', util: '81%', avgLitres: '376.85', score: '86.5' },
  { rank: 8, id: 'VHCL-1007', name: 'Volt Fusion R', distTotal: '4500.1', idleTotal: '145.8', util: '79%', avgLitres: '389.90', score: '84.2' },
  { rank: 9, id: 'VHCL-1008', name: 'Quantum Leap 6', distTotal: '4700.3', idleTotal: '155.4', util: '77%', avgLitres: '395.75', score: '82.0' },
  { rank: 10, id: 'VHCL-1003', name: 'Stealth Chaser X', distTotal: '3000.8', idleTotal: '130.0', util: '75%', avgLitres: '298.90', score: '80.4' },
]);
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; }
tbody tr td { font-size: 13px; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.text-bronze { color: #cd7f32; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
</style>
