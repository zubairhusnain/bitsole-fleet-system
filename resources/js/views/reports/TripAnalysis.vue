<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Trip Analysis Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Trip Analysis Report</h4>

    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0 ps-3"><h6 class="mb-0 fw-bold">Search Option</h6></div>
      <div class="card-body pt-2">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Duration</label>
            <div class="input-group">
              <input v-model="duration" type="text" class="form-control" placeholder="yyyy-mm-dd to yyyy-mm-dd" />
              <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small fw-semibold text-muted">Vehicle</label>
            <select v-model="vehicle" class="form-select text-muted">
              <option value="">--Select Vehicle--</option>
              <option>VGPS2563</option>
              <option>VHCL-1006</option>
              <option>VHCL-1009</option>
              <option>VHCL-1011</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold text-muted">View Type</label>
            <select v-model="viewType" class="form-select text-muted">
              <option>Daily Breakdown</option>
              <option>Daily Breakdown (with map)</option>
              <option>Daily Summary</option>
              <option>Daily Summary List</option>
              <option>Monthly Summary</option>
              <option>Monthly Summary List</option>
            </select>
          </div>
          <div class="col-12 col-md-1 text-md-end">
            <button class="btn btn-primary w-100">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <template v-if="viewType === 'Daily Breakdown'">
      <ReportSummary />
      <ChartAndKPIs />
      <div class="card border rounded-3 shadow-0">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-striped w-100 text-nowrap">
              <thead class="table-dark">
                <tr>
                  <th class="py-2 ps-3">Date</th>
                  <th class="py-2">Start Time</th>
                  <th class="py-2">Start Location</th>
                  <th class="py-2">Start Remarks</th>
                  <th class="py-2">End Time</th>
                  <th class="py-2">End Location</th>
                  <th class="py-2">End Remarks</th>
                  <th class="py-2 pe-3 text-end">Travelled Dist</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rowsDailyBreakdown" :key="r.key">
                  <td class="ps-3"><a href="#" class="text-decoration-none" :class="r.key === 8 ? 'text-primary fw-semibold' : ''">{{ r.date }}</a></td>
                  <td>{{ r.startTime }}</td>
                  <td class="text-primary">{{ r.startLocation }}</td>
                  <td><span class="badge bg-danger-subtle text-danger border">OUT PC</span></td>
                  <td>{{ r.endTime }}</td>
                  <td class="text-primary">{{ r.endLocation }}</td>
                  <td><span class="badge bg-success-subtle text-success border">IN PC</span></td>
                  <td class="text-end pe-3">{{ r.distance }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer d-flex align-items-center py-2 bg-white border-top">
            <div class="text-muted small me-auto">Showing 1 to 18 of 1079 results</div>
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
    </template>

    <template v-else-if="viewType === 'Daily Summary List'">
      <div class="card border rounded-3 shadow-0">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-striped w-100 text-nowrap">
              <thead class="table-dark">
                <tr>
                  <th rowspan="2" class="align-middle ps-3">Vehicle ID</th>
                  <th rowspan="2" class="align-middle">Vehicles Name</th>
                  <th colspan="2" class="text-center border-bottom border-secondary">Travelled Distance (KM)</th>
                  <th colspan="2" class="text-center border-bottom border-secondary">Trip Duration (Hours)</th>
                  <th colspan="2" class="text-center border-bottom border-secondary">Idling Duration (Hours)</th>
                  <th rowspan="2" class="align-middle">Utilisation(%)</th>
                  <th colspan="2" class="text-center border-bottom border-secondary">Avg. Fuel Consumption</th>
                  <th rowspan="2" class="align-middle">Fuel Refill (L)</th>
                  <th rowspan="2" class="align-middle">Fuel Refill<br>(Frequency)</th>
                  <th rowspan="2" class="align-middle">Speed (Km/h)</th>
                  <th rowspan="2" class="align-middle pe-3">Action</th>
                </tr>
                <tr>
                  <th class="bg-custom-blue text-white text-center">Total</th>
                  <th class="bg-custom-blue text-white text-center">Avg. Per Day</th>
                  <th class="bg-custom-blue text-white text-center">Total</th>
                  <th class="bg-custom-blue text-white text-center">Avg. Per Day</th>
                  <th class="bg-custom-blue text-white text-center">Total</th>
                  <th class="bg-custom-blue text-white text-center">Avg. Per Day</th>
                  <th class="bg-custom-blue text-white text-center">Avg. Litres</th>
                  <th class="bg-custom-blue text-white text-center">Avg. KM/L</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rowsDailyVehicleList" :key="r.key" :class="{ 'table-active': r.highlight }">
                  <td class="ps-3">{{ r.vehicle }}</td>
                  <td>{{ r.vehicleName }}</td>
                  <td class="text-center">{{ r.distTotal }}</td>
                  <td class="text-center">{{ r.distAvg }}</td>
                  <td class="text-center">{{ r.tripTotal }}</td>
                  <td class="text-center">{{ r.tripAvg }}</td>
                  <td class="text-center">{{ r.idleTotal }}</td>
                  <td class="text-center">{{ r.idleAvg }}</td>
                  <td>{{ r.utilisation }}</td>
                  <td class="text-center">{{ r.fuelAvgLitres }}</td>
                  <td class="text-center">{{ r.fuelAvgKmL }}</td>
                  <td>{{ r.fuelRefillL }}</td>
                  <td class="text-center">{{ r.fuelRefillFreq }}</td>
                  <td>{{ r.speed }}</td>
                  <td class="pe-3">
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm p-0 text-primary"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm p-0 text-danger"><i class="bi bi-trash"></i></button>
                    </div>
                  </td>
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
    </template>

    <template v-else-if="viewType === 'Daily Breakdown (with map)'">
      <ReportSummary />
      <div class="card border rounded-3 shadow-0">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-lg-4">
              <div class="list-group small">
                <div class="list-group-item d-flex justify-content-between align-items-center bg-light">
                  <div>
                    <div class="fw-bold">26/08/2025 • Saturday</div>
                    <div class="text-muted">126.53 KM</div>
                  </div>
                  <i class="bi bi-chevron-up"></i>
                </div>
                <div class="list-group-item p-0 border-0">
                  <div class="bg-light border-bottom p-3">
                    <div class="fw-semibold mb-2">Summary for 26/08/2025 • Saturday</div>
                    <div class="row g-2">
                      <div class="col-6">
                        <div class="text-muted small">Total Distance</div>
                        <div class="fw-bold">126.53 KM</div>
                      </div>
                      <div class="col-6">
                        <div class="text-muted small">Total Duration</div>
                        <div class="fw-bold">2h 8m 8s</div>
                      </div>
                      <div class="col-6">
                        <div class="text-muted small">Total Idling</div>
                        <div class="fw-bold">0</div>
                      </div>
                      <div class="col-6">
                        <div class="text-muted small">Behaviour</div>
                        <div class="fw-bold text-danger">12 SV, 1 HA</div>
                      </div>
                    </div>
                  </div>
                  <div class="list-group list-group-flush">
                    <div class="list-group-item">
                      <div class="d-flex gap-3">
                        <div class="d-flex flex-column align-items-center">
                          <div class="text-muted small">05:48 AM</div>
                          <div class="flex-grow-1 border-start border-2 my-1"></div>
                        </div>
                        <div class="pb-3">
                          <div class="fw-semibold text-primary">Start</div>
                          <div class="small text-muted">Exit 3506, Bukit Jelutong North Intersection, Persiaran Gerbang Utama E35, 40150 Shah Alam, Selangor</div>
                        </div>
                      </div>
                      <div class="d-flex gap-3">
                        <div class="d-flex flex-column align-items-center">
                          <div class="text-muted small">05:49 AM</div>
                        </div>
                        <div>
                          <div class="fw-semibold text-primary">End</div>
                          <div class="small text-muted">Persiaran Gerbang Utama, Bukit Jelutong</div>
                          <div class="mt-1"><span class="badge bg-light text-dark border me-1">1KM</span> <span class="badge bg-light text-dark border">14m 59s</span></div>
                        </div>
                      </div>
                    </div>
                    <div class="list-group-item">
                      <div class="d-flex gap-3">
                        <div class="d-flex flex-column align-items-center">
                          <div class="text-muted small">06:15 AM</div>
                          <div class="flex-grow-1 border-start border-2 my-1"></div>
                        </div>
                        <div class="pb-3">
                          <div class="fw-semibold text-primary">Start</div>
                          <div class="small text-muted">Exit 3510, Bukit Jelutong East Intersection, Persiaran Gerbang Utama E35, 40150 Shah Alam, Selangor</div>
                        </div>
                      </div>
                      <div class="d-flex gap-3">
                        <div class="d-flex flex-column align-items-center">
                          <div class="text-muted small">06:16 AM</div>
                        </div>
                        <div>
                          <div class="fw-semibold text-primary">End</div>
                          <div class="small text-muted">Persiaran Gerbang Timur, Bukit Jelutong</div>
                          <div class="mt-1"><span class="badge bg-light text-dark border me-1">3KM</span> <span class="badge bg-light text-dark border">44m 15s</span> <span class="badge bg-danger-subtle text-danger border border-danger">12 SV</span></div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold">28/08/2025 • Monday</div>
                    <div class="text-muted">118.22 KM</div>
                  </div>
                  <i class="bi bi-chevron-down"></i>
                </div>
                 <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold">29/08/2025 • Tuesday</div>
                    <div class="text-muted">145.90 KM</div>
                  </div>
                  <i class="bi bi-chevron-down"></i>
                </div>
                 <div class="list-group-item d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold">30/08/2025 • Wednesday</div>
                    <div class="text-muted">121.15 KM</div>
                  </div>
                  <i class="bi bi-chevron-down"></i>
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-8">
              <div class="d-flex align-items-center gap-2 mb-2">
                <button class="btn btn-sm btn-outline-secondary">Restart</button>
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-skip-backward-fill"></i></button>
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-play-fill"></i></button>
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-pause-fill"></i></button>
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-skip-forward-fill"></i></button>
                <div class="ms-auto d-flex align-items-center gap-2">
                  <span class="small">Slow</span>
                  <div class="form-range w-25"><div class="progress" style="height:6px;"><div class="progress-bar" style="width:50%"></div></div></div>
                  <span class="small">Fast</span>
                </div>
              </div>
              <div ref="mapEl" style="height: 60vh; min-height: 320px;" class="rounded-3 overflow-hidden border"></div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="viewType === 'Daily Summary'">
      <ReportSummary />
      <ChartAndKPIs />
      <div class="card border rounded-3 shadow-0">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-striped">
              <thead class="table-dark">
                <tr>
                  <th>Date</th>
                  <th>Travelled Distance</th>
                  <th>Trip Duration</th>
                  <th>Idle Duration</th>
                  <th>Idle Percentage</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rowsDailySummary" :key="r.key">
                  <td>{{ r.date }}</td>
                  <td>{{ r.distance }}</td>
                  <td>{{ r.trip }}</td>
                  <td>{{ r.idle }}</td>
                  <td>{{ r.idlePct }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center p-2 small text-muted">
            <div>Showing 1 to 18 of 1079 results</div>
            <nav>
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item disabled"><span class="page-link">&lt;</span></li>
                <li class="page-item active"><span class="page-link">1</span></li>
                <li class="page-item"><span class="page-link">2</span></li>
                <li class="page-item"><span class="page-link">3</span></li>
                <li class="page-item"><span class="page-link">4</span></li>
                <li class="page-item"><span class="page-link">5</span></li>
                <li class="page-item"><span class="page-link">&gt;</span></li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="viewType === 'Monthly Summary'">
      <ReportSummary />
      <ChartAndKPIs />
      <div class="card border rounded-3 shadow-0">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-striped">
              <thead class="table-dark">
                <tr>
                  <th>Date</th>
                  <th>Travelled Distance</th>
                  <th>Trip Duration</th>
                  <th>Idle Duration</th>
                  <th>Idle Percentage</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rowsMonthlySummary" :key="r.key">
                  <td>{{ r.date }}</td>
                  <td>{{ r.distance }}</td>
                  <td>{{ r.trip }}</td>
                  <td>{{ r.idle }}</td>
                  <td>{{ r.idlePct }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>

    <template v-else-if="viewType === 'Monthly Summary List'">
      <div class="card border rounded-3 shadow-0">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 table-striped">
              <thead class="table-dark">
                <tr>
                  <th>Vehicle ID</th>
                  <th>Date</th>
                  <th>Travelled Distance</th>
                  <th>Trip Duration</th>
                  <th>Idle Duration</th>
                  <th>Idle Percentage</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rowsMonthlyVehicleList" :key="r.key">
                  <td>{{ r.vehicle }}</td>
                  <td>{{ r.date }}</td>
                  <td>{{ r.distance }}</td>
                  <td>{{ r.trip }}</td>
                  <td>{{ r.idle }}</td>
                  <td>{{ r.idlePct }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="d-flex justify-content-between align-items-center p-2 small text-muted">
            <div>Showing 1 to 16 of 1079 results</div>
            <nav>
              <ul class="pagination pagination-sm mb-0">
                <li class="page-item disabled"><span class="page-link">&lt;</span></li>
                <li class="page-item active"><span class="page-link">1</span></li>
                <li class="page-item"><span class="page-link">2</span></li>
                <li class="page-item"><span class="page-link">3</span></li>
                <li class="page-item"><span class="page-link">4</span></li>
                <li class="page-item"><span class="page-link">5</span></li>
                <li class="page-item"><span class="page-link">&gt;</span></li>
              </ul>
            </nav>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

try {
  delete L.Icon.Default.prototype._getIconUrl;
  L.Icon.Default.mergeOptions({
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).toString(),
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).toString(),
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).toString(),
  });
} catch {}

const duration = ref('2025-08-01 to 2025-08-12');
const vehicle = ref('VGP2563');
const viewType = ref('Daily Breakdown (with map)');

const rowsDailyBreakdown = ref([
  {
    key: 1,
    date: '26/08/2025 - Saturday',
    distance: '126.53 KM',
    isOpen: false
  },
  {
    key: 2,
    date: '28/08/2025 - Monday',
    distance: '118.22 KM',
    isOpen: true,
    summary: {
      date: '28/08/2025 - Monday',
      dist: '126.53 km',
      dur: '2h 8m 35s',
      idle: '0',
      behav: '12 SV, 1 HA'
    },
    timeline: [
      { time: '05:48 AM', location: 'Exit 3506, Bukit Jelutong North Intersection, Persiaran Gerbang Utama E35, 40150 Shah Alam, Selangor', dist: '1KM', dur: '14m 59s', type: 'start' },
      { time: '05:49 AM', location: 'Persiaran Gerbang Utama, Bukit Jelutong', type: 'end' },
      { time: '06:15 AM', location: 'Exit 3510, Bukit Jelutong East Intersection, Persiaran Gerbang Utama E35, 40150 Shah Alam, Selangor', dist: '3KM', dur: '44m 15s', alert: '12 SV', type: 'start' },
      { time: '06:16 AM', location: 'Persiaran Gerbang Timur, Bukit Jelutong', type: 'end' },
      { time: '06:05 AM', location: 'Exit 3508, Bukit Jelutong South Intersection, Persiaran Gerbang Utama E35, 40150 Shah Alam, Selangor', dist: '2KM', dur: '29m 30s', type: 'start' },
      { time: '06:06 AM', location: 'Persiaran Gerbang Selatan, Bukit Jelutong', type: 'end' }
    ]
  },
  { key: 3, date: '28/08/2025 - Monday', distance: '118.22 KM', isOpen: false },
  { key: 4, date: '29/08/2025 - Tuesday', distance: '145.90 KM', isOpen: false },
  { key: 5, date: '30/08/2025 - Wednesday', distance: '121.15 KM', isOpen: false },
  { key: 6, date: '31/08/2025 - Thursday', distance: '134.67 KM', isOpen: false }
]);

const rowsDailySummary = ref([
  { key: 1, date: '26/08/2025', distance: '0.01 Kilo-meters', trip: '1h 3m 48s', idle: '1h 3m 48s', idlePct: '01%' },
  { key: 2, date: '26/08/2025', distance: '14.00 Kilo-meters', trip: '2h 10m 5s', idle: '2h 10m 5s', idlePct: '10%' },
  { key: 3, date: '26/08/2025', distance: '14.00 Kilo-meters', trip: '0h 36m 58s', idle: '0h 36m 58s', idlePct: '05%' },
  { key: 4, date: '26/08/2025', distance: '3.00 Kilo-meters', trip: '2h 51m 26s', idle: '2h 51m 26s', idlePct: '03%' },
  { key: 5, date: '26/08/2025', distance: '63.00 Kilo-meters', trip: '1h 18m 40s', idle: '1h 18m 40s', idlePct: '45%' },
  { key: 6, date: '26/08/2025', distance: '46.00 Kilo-meters', trip: '3h 14m 33s', idle: '3h 14m 33s', idlePct: '05%' },
  { key: 7, date: '26/08/2025', distance: '0.52 Kilo-meters', trip: '0h 57m 19s', idle: '0h 57m 19s', idlePct: '17%' },
  { key: 8, date: '26/08/2025', distance: '14.00 Kilo-meters', trip: '1h 42m 7s', idle: '1h 42m 7s', idlePct: '10%' },
  { key: 9, date: '26/08/2025', distance: '3.00 Kilo-meters', trip: '0h 29m 54s', idle: '0h 29m 54s', idlePct: '20%' },
  { key: 10, date: '26/08/2025', distance: '63.00 Kilo-meters', trip: '1h 30m 22s', idle: '1h 30m 22s', idlePct: '05%' },
  { key: 11, date: '26/08/2025', distance: '0.52 Kilo-meters', trip: '2h 5m 15s', idle: '2h 5m 15s', idlePct: '10%' },
]);

const rowsMonthlySummary = ref([
  { key: 1, date: '05/2025', distance: '8515.33 KM', trip: '7d 13h 19m 56s', idle: '4h 20m 33s', idlePct: '02%' },
  { key: 2, date: '06/2025', distance: '8515.33 KM', trip: '7d 13h 19m 56s', idle: '4h 20m 33s', idlePct: '02%' },
  { key: 3, date: '07/2025', distance: '8515.33 KM', trip: '7d 13h 19m 56s', idle: '4h 20m 33s', idlePct: '02%' },
]);

const rowsMonthlyVehicleList = ref([
  { key: 1, vehicle: 'VGP7894', date: '04/2025', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 2, vehicle: 'VGP1023', date: '05/2025', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 3, vehicle: 'VGP4567', date: '06/2025', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 4, vehicle: 'VGP8910', date: '07/2025', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 5, vehicle: 'VGP2345', date: '09/2025', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 6, vehicle: 'VGP6789', date: '10/2025', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 7, vehicle: 'VGP3456', date: '11/2025', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 8, vehicle: 'VGP1234', date: '12/2025', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 9, vehicle: 'VGP5678', date: '01/2026', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 10, vehicle: 'VGP8901', date: '02/2026', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 11, vehicle: 'VGP2341', date: '03/2026', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 12, vehicle: 'VGP9876', date: '04/2026', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 13, vehicle: 'VGP5432', date: '05/2026', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
]);

const rowsDailyVehicleList = ref([
  { key: 1, vehicle: 'VHCL-1002', vehicleName: 'Turbo Hawk ZR', distTotal: '5680.5', distAvg: '175.6', tripTotal: '5680.5', tripAvg: '175.6', idleTotal: '5680.5', idleAvg: '175.6', utilisation: 'B345-3920-5436', fuelAvgLitres: '412.75', fuelAvgKmL: '175.6', fuelRefillL: '321,450 KM', fuelRefillFreq: '12', speed: '0 km/h' },
  { key: 2, vehicle: 'VHCL-1003', vehicleName: 'Stealth Chaser X', distTotal: '3000.8', distAvg: '130.0', tripTotal: '3000.8', tripAvg: '130.0', idleTotal: '3000.8', idleAvg: '130.0', utilisation: 'C567-3890-2845', fuelAvgLitres: '298.90', fuelAvgKmL: '130.0', fuelRefillL: '154,200 KM', fuelRefillFreq: '10', speed: '0 km/h' },
  { key: 3, vehicle: 'VHCL-1004', vehicleName: 'Lunar Explorer 5', distTotal: '4550.0', distAvg: '140.2', tripTotal: '4550.0', tripAvg: '140.2', idleTotal: '4550.0', idleAvg: '140.2', utilisation: 'D666-2399-7743', fuelAvgLitres: '376.85', fuelAvgKmL: '140.2', fuelRefillL: '245,600 KM', fuelRefillFreq: '9', speed: '0 km/h' },
  { key: 4, vehicle: 'VHCL-1004', vehicleName: 'Lunar Explorer 5', distTotal: '4550.0', distAvg: '140.2', tripTotal: '4550.0', tripAvg: '140.2', idleTotal: '4550.0', idleAvg: '140.2', utilisation: 'D666-2399-7743', fuelAvgLitres: '376.85', fuelAvgKmL: '140.2', fuelRefillL: '245,600 KM', fuelRefillFreq: '9', speed: '0 km/h' },
  { key: 5, vehicle: 'VHCL-1005', vehicleName: 'Raptor GT', distTotal: '5000.4', distAvg: '160.4', tripTotal: '5000.4', tripAvg: '160.4', idleTotal: '5000.4', idleAvg: '160.4', utilisation: 'A123-8470-9032', fuelAvgLitres: '450.00', fuelAvgKmL: '160.4', fuelRefillL: '290,300 KM', fuelRefillFreq: '11', speed: '0 km/h' },
  { key: 6, vehicle: 'VHCL-1006', vehicleName: 'Shadow Hunter 12', distTotal: '6200.7', distAvg: '190.5', tripTotal: '6200.7', tripAvg: '190.5', idleTotal: '6200.7', idleAvg: '190.5', utilisation: 'E890-5623-0012', fuelAvgLitres: '520.25', fuelAvgKmL: '190.5', fuelRefillL: '410,100 KM', fuelRefillFreq: '14', speed: '0 km/h' },
  { key: 7, vehicle: 'VHCL-1006', vehicleName: 'Shadow Hunter 12', distTotal: '6200.7', distAvg: '190.5', tripTotal: '6200.7', tripAvg: '190.5', idleTotal: '6200.7', idleAvg: '190.5', utilisation: 'E890-5623-0012', fuelAvgLitres: '520.25', fuelAvgKmL: '190.5', fuelRefillL: '410,100 KM', fuelRefillFreq: '14', speed: '0 km/h' },
  { key: 8, vehicle: 'VHCL-1007', vehicleName: 'Volt Fusion R', distTotal: '4500.1', distAvg: '145.8', tripTotal: '4500.1', tripAvg: '145.8', idleTotal: '4500.1', idleAvg: '145.8', utilisation: 'F234-9502-1287', fuelAvgLitres: '389.90', fuelAvgKmL: '145.8', fuelRefillL: '123,000 KM', fuelRefillFreq: '7', speed: '0 km/h' },
  { key: 9, vehicle: 'VHCL-1008', vehicleName: 'Quantum Leap 6', distTotal: '4700.3', distAvg: '155.4', tripTotal: '4700.3', tripAvg: '155.4', idleTotal: '4700.3', idleAvg: '155.4', utilisation: 'G456-7321-8745', fuelAvgLitres: '395.75', fuelAvgKmL: '155.4', fuelRefillL: '200,900 KM', fuelRefillFreq: '8', speed: '0 km/h' },
  { key: 10, vehicle: 'VHCL-1008', vehicleName: 'Quantum Leap 6', distTotal: '4700.3', distAvg: '155.4', tripTotal: '4700.3', tripAvg: '155.4', idleTotal: '4700.3', idleAvg: '155.4', utilisation: 'G456-7321-8745', fuelAvgLitres: '395.75', fuelAvgKmL: '155.4', fuelRefillL: '200,900 KM', fuelRefillFreq: '8', speed: '0 km/h' },
  { key: 11, vehicle: 'VHCL-1009', vehicleName: 'Meteor Strike 11', distTotal: '5200.2', distAvg: '185.0', tripTotal: '5200.2', tripAvg: '185.0', idleTotal: '5200.2', idleAvg: '185.0', utilisation: 'H789-3491-0010', fuelAvgLitres: '460.80', fuelAvgKmL: '185.0', fuelRefillL: '370,200 KM', fuelRefillFreq: '13', speed: '0 km/h' },
  { key: 12, vehicle: 'VHCL-1010', vehicleName: 'Apex Predator S', distTotal: '5900.6', distAvg: '210.7', tripTotal: '5900.6', tripAvg: '210.7', idleTotal: '5900.6', idleAvg: '210.7', utilisation: 'I901-4783-5629', fuelAvgLitres: '510.55', fuelAvgKmL: '210.7', fuelRefillL: '500,300 KM', fuelRefillFreq: '15', speed: '0 km/h' },
  { key: 13, vehicle: 'VHCL-1011', vehicleName: 'Falcon Cruiser Z', distTotal: '5300.9', distAvg: '195.2', tripTotal: '5300.9', tripAvg: '195.2', idleTotal: '5300.9', idleAvg: '195.2', utilisation: 'J876-5432-1098', fuelAvgLitres: '485.60', fuelAvgKmL: '195.2', fuelRefillL: '380,450 KM', fuelRefillFreq: '12', speed: '0 km/h' },
]);

const mapEl = ref(null);
let map = null;
function initMap() {
  if (!mapEl.value) return;
  if (map) {
    map.remove();
    map = null;
  }
  map = L.map(mapEl.value).setView([38.627, -90.199], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  const route = [
    [38.627, -90.199],
    [38.9, -89.7],
    [39.5, -89.3],
    [40.0, -89.0],
    [40.5, -88.5],
    [41.0, -88.0],
    [41.878, -87.629],
  ];
  L.polyline(route, { color: '#0b5ed7', weight: 4 }).addTo(map);
  L.marker(route[0]).bindPopup('Start Time 05:48 AM • 26/08/2025').addTo(map);
  L.marker(route[route.length - 1]).bindPopup('End Time 05:49 AM • 26/08/2025').addTo(map);
  map.fitBounds(L.polyline(route).getBounds(), { padding: [20, 20] });
}

watch(viewType, async (val) => {
  if (val === 'Daily Breakdown (with map)') {
    await nextTick();
    initMap();
  }
});

onMounted(async () => {
  if (viewType.value === 'Daily Breakdown (with map)') {
    await nextTick();
    initMap();
  }
});
</script>

<script>
const ReportSummary = {
  name: 'ReportSummary',
  template: `
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
          <div class="fw-semibold">2025-08-26 00:00 - 2025-08-31 23:59</div>
        </div>
        <div class="col-12 col-md-3">
          <div class="small text-muted">View Type</div>
          <div class="fw-semibold">Summary</div>
        </div>
        <div class="col-12">
          <div class="small text-muted">Remarks</div>
          <div class="fw-semibold">Average fuel consumption calculated up to 6 months of data. Fuel refill amount shown for duration selected. Fuel refill amount does not imply fuel consumed in the same duration selected.</div>
        </div>
      </div>
    </div>
  </div>`
};

const ChartAndKPIs = {
  name: 'ChartAndKPIs',
  template: `
  <div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
      <div class="card border rounded-3 shadow-0 h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="small text-muted">Trip Duration • Idle Duration • Distance</div>
          </div>
          <svg viewBox="0 0 600 260" width="100%" height="220">
            <rect x="0" y="0" width="600" height="220" fill="#f8f9fa" rx="8"/>
            <g>
              <rect v-for="(bar,i) in [180,120,90,200,160,80,140]" :key="'b'+i" :x="30 + i*80" :y="200-bar" width="40" :height="bar" fill="#74c0fc"/>
              <rect v-for="(bar,i) in [120,100,70,150,110,60,90]" :key="'i'+i" :x="30 + i*80 + 45" :y="200-bar" width="40" :height="bar" fill="#adb5bd"/>
              <path d="M30 120 L110 80 L190 140 L270 70 L350 110 L430 90 L510 130" stroke="#f03e3e" stroke-width="3" fill="none"/>
            </g>
          </svg>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card border rounded-3 shadow-0 h-100">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <div class="bg-light rounded-3 p-3 h-100">
                <div class="small text-muted">Total Duration</div>
                <div class="display-6 fw-semibold">7d 13h 19m 56s</div>
                <div class="mt-3 small">Trip Duration</div>
                <div class="progress" style="height: 6px;"><div class="progress-bar" style="width:90%"></div></div>
                <div class="fw-semibold mt-1">7d 8h 59m 23s</div>
                <div class="mt-3 small">Idle Duration</div>
                <div class="progress" style="height: 6px;"><div class="progress-bar bg-secondary" style="width:10%"></div></div>
                <div class="fw-semibold mt-1">4h 20m 33s</div>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <div class="bg-light rounded-3 p-3 h-100">
                <div class="small text-muted mb-2">Summary</div>
                <div class="d-flex justify-content-between"><span>Total Distance (km)</span><span class="fw-semibold">8515.33 KM</span></div>
                <div class="d-flex justify-content-between"><span>Total Trip Duration (hr)</span><span class="fw-semibold">7d 13h 19m 56s</span></div>
                <div class="d-flex justify-content-between"><span>Total Idling (hr)</span><span class="fw-semibold">4h 20m 33s</span></div>
                <div class="d-flex justify-content-between"><span>Idling Percentage vs Trip Duration</span><span class="fw-semibold">02%</span></div>
                <div class="d-flex justify-content-between"><span>Average Fuel Consumption (km/litre)</span><span class="fw-semibold">0 km/l</span></div>
                <div class="d-flex justify-content-between"><span>Total Fuel Usage (litre)</span><span class="fw-semibold">0 Litre</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>`
};
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; border-color: rgba(255,255,255,0.15); vertical-align: middle; }
thead.table-dark tr th.bg-custom-blue { background-color: #00b0f0 !important; border-color: rgba(255,255,255,0.15); }
tbody tr td { font-size: 13px; vertical-align: middle; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.badge.border { border: 1px solid currentColor; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
</style>
