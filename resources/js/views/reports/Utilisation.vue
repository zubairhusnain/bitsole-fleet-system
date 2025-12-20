<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Utilisation Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Utilisation Report</h4>
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
            <label class="form-label small">Type</label>
            <input type="text" class="form-control" value="Movement" />
          </div>
          <div class="col-12 col-md-1 text-md-end">
            <button class="btn btn-app-dark w-100">Submit</button>
          </div>
        </div>
        <div class="mt-3 d-flex align-items-center gap-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" checked id="withMovement">
            <label class="form-check-label" for="withMovement">With Movement</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="withoutMovement">
            <label class="form-check-label" for="withoutMovement">Without Movement</label>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Utilisation Report Result</h6></div>
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
            <div class="small text-muted">Total Days</div>
            <div class="fw-semibold">10 Days</div>
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
                <th>Date/Day</th>
                <th>Usage</th>
                <th>Total Movement Time</th>
                <th>Distance</th>
                <th>Hourly Activity</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.day">
                <td>{{ row.day }}</td>
                <td><span class="badge bg-primary">{{ row.usage }}</span></td>
                <td>{{ row.move }}</td>
                <td>{{ row.dist }}</td>
                <td>
                  <div class="hours">
                    <span v-for="(active, i) in row.hours" :key="i" :class="['h', active ? 'on' : 'off']">{{ i }}</span>
                  </div>
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
function makeHours(activeIdx = []) {
  const arr = new Array(24).fill(false);
  activeIdx.forEach(i => { if (i >= 0 && i < 24) arr[i] = true; });
  return arr;
}
const rows = ref([
  { day: '11-03-2024 Monday', usage: '75%', move: '1 hour 5 minutes', dist: '35 KM', hours: makeHours([7,8,9,10,15,17,19]) },
  { day: '12-03-2024 Tuesday', usage: '68%', move: '3 hours 5 minutes', dist: '127 KM', hours: makeHours([6,7,8,12,13,14,16]) },
  { day: '13-03-2024 Wednesday', usage: '80%', move: '3 hours 7 minutes', dist: '150 KM', hours: makeHours([8,9,10,11,16,17]) },
  { day: '14-03-2024 Thursday', usage: '83%', move: '6 hours 4 minutes', dist: '116 KM', hours: makeHours([7,8,9,10,11,12,13]) },
  { day: '15-03-2024 Friday', usage: '90%', move: '4 hours 45 minutes', dist: '425 KM', hours: makeHours([6,7,8,9,17,18,19]) },
  { day: '16-03-2024 Saturday', usage: '78%', move: '3 hours 52 minutes', dist: '187 KM', hours: makeHours([8,9,10,11,12,13]) },
  { day: '17-03-2024 Sunday', usage: '82%', move: '2 hours 25 minutes', dist: '127 KM', hours: makeHours([7,8,9,10,20]) },
  { day: '18-03-2024 Monday', usage: '74%', move: '4 hours 57 minutes', dist: '150 KM', hours: makeHours([6,7,8,9,10,17,18]) },
  { day: '19-03-2024 Tuesday', usage: '88%', move: '3 hours 10 minutes', dist: '116 KM', hours: makeHours([6,7,8,9,10,11,12]) },
  { day: '20-03-2024 Wednesday', usage: '92%', move: '2 hours 58 minutes', dist: '187 KM', hours: makeHours([7,8,9,17,18,19]) },
]);
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; }
tbody tr td { font-size: 13px; }
.hours { display: grid; grid-template-columns: repeat(24, 1fr); gap: 2px; }
.h { display: inline-block; text-align: center; font-size: 10px; padding: 2px 0; border-radius: 3px; background: #e9ecef; color: #6c757d; }
.h.on { background: #3b82f6; color: #fff; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
</style>
