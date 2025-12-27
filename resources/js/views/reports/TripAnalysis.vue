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
                <tr v-for="r in rowsDailyTrips" :key="r.key">
                  <td class="ps-3"><a href="#" class="text-decoration-none" :class="r.key === 1 ? 'text-primary fw-semibold' : ''">{{ r.date }}</a></td>
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
            <div class="text-muted small me-auto">Showing 1 to {{ rowsDailyTrips.length }} of {{ rowsDailyTrips.length }} results</div>
            <nav aria-label="Pagination" class="ms-auto">
              <ul class="pagination pagination-sm mb-0 pagination-app">
                <li class="page-item disabled"><button class="page-link"><i class="bi bi-chevron-left"></i></button></li>
                <li class="page-item active"><button class="page-link">1</button></li>
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
            <table class="table table-sm align-middle mb-0 table-striped">
              <thead class="table-dark">
                <tr>
                  <th class="ps-3">Date</th>
                  <th>Vehicle ID</th>
                  <th class="text-end">Travelled Distance</th>
                  <th class="text-end">Trip Duration</th>
                  <th class="text-end">Idle Duration</th>
                  <th class="text-end pe-3">Idle Percentage</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rowsDailyVehicleList" :key="r.key">
                  <td class="ps-3">{{ r.date }}</td>
                  <td class="text-primary">{{ r.vehicle }}</td>
                  <td class="text-end">{{ r.distance }}</td>
                  <td class="text-end">{{ r.trip }}</td>
                  <td class="text-end">{{ r.idle }}</td>
                  <td class="text-end pe-3">{{ r.idlePct }}</td>
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
      <div class="card border rounded-3 shadow-0">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12 col-lg-4">
              <div class="list-group small">
                <template v-for="day in rowsDailyBreakdown" :key="day.key">
                  <!-- Day Header -->
                  <div class="list-group-item d-flex justify-content-between align-items-center bg-light"
                       @click="day.isOpen = !day.isOpen"
                       role="button">
                    <div>
                      <div class="fw-bold">{{ day.date }}</div>
                      <div class="text-muted">{{ day.distance }}</div>
                    </div>
                    <i class="bi" :class="day.isOpen ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                  </div>

                  <!-- Day Details (Timeline) -->
                  <div v-if="day.isOpen" class="list-group-item p-0 border-0">
                    <div v-if="day.summary" class="bg-light border-bottom p-3">
                      <div class="fw-semibold mb-2">Summary for {{ day.summary.date }}</div>
                      <div class="row g-2">
                        <div class="col-6">
                          <div class="text-muted small">Total Distance</div>
                          <div class="fw-bold">{{ day.summary.dist }}</div>
                        </div>
                        <div class="col-6">
                          <div class="text-muted small">Total Duration</div>
                          <div class="fw-bold">{{ day.summary.dur }}</div>
                        </div>
                        <div class="col-6">
                          <div class="text-muted small">Total Idling</div>
                          <div class="fw-bold">{{ day.summary.idle }}</div>
                        </div>
                        <div class="col-6">
                          <div class="text-muted small">Behaviour</div>
                          <div class="fw-bold text-danger">{{ day.summary.behav }}</div>
                        </div>
                      </div>
                    </div>
                    <div class="list-group list-group-flush">
                      <div v-for="(item, idx) in day.timeline" :key="idx" class="list-group-item">
                        <div class="d-flex gap-3">
                          <div class="d-flex flex-column align-items-center" style="width: 60px;">
                            <div class="text-muted small">{{ item.time }}</div>
                            <div v-if="idx < day.timeline.length - 1 || item.type === 'start'" class="flex-grow-1 border-start border-2 my-1"></div>
                          </div>
                          <div class="pb-2">
                            <div class="fw-semibold" :class="item.type === 'start' ? 'text-primary' : 'text-danger'">{{ item.type === 'start' ? 'Start' : 'End' }}</div>
                            <div class="small text-muted">{{ item.location }}</div>
                            <div v-if="item.dist || item.dur || item.alert" class="mt-1">
                              <span v-if="item.dist" class="badge bg-light text-dark border me-1">{{ item.dist }}</span>
                              <span v-if="item.dur" class="badge bg-light text-dark border me-1">{{ item.dur }}</span>
                              <span v-if="item.alert" class="badge bg-danger-subtle text-danger border border-danger">{{ item.alert }}</span>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </div>
            <div class="col-12 col-lg-8">
              <div class="position-relative h-100">
                 <div class="position-absolute top-0 start-50 translate-middle-x mt-3 z-3 bg-white p-2 rounded-pill shadow-sm d-flex align-items-center gap-2" style="width: fit-content;">
                    <button class="btn btn-sm btn-link text-dark text-decoration-none fw-semibold"><i class="bi bi-arrow-counterclockwise"></i> Restart</button>
                    <div class="vr"></div>
                    <button class="btn btn-sm btn-link text-secondary"><i class="bi bi-skip-backward-fill"></i></button>
                    <button class="btn btn-sm btn-link text-secondary"><i class="bi bi-play-fill"></i></button>
                    <button class="btn btn-sm btn-link text-secondary"><i class="bi bi-pause-fill"></i></button>
                    <button class="btn btn-sm btn-link text-secondary"><i class="bi bi-skip-forward-fill"></i></button>
                    <div class="vr"></div>
                    <span class="small text-muted ms-1">Slow</span>
                    <input type="range" class="form-range" style="width: 80px">
                    <span class="small text-muted me-1">Fast</span>
                 </div>
                 <div ref="mapEl" style="height: 60vh; min-height: 320px;" class="rounded-3 overflow-hidden border"></div>
              </div>
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
                  <th class="ps-3">Date</th>
                  <th class="text-end">Travelled Distance</th>
                  <th class="text-end">Trip Duration</th>
                  <th class="text-end">Idle Duration</th>
                  <th class="text-end pe-3">Idle Percentage</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rowsDailySummary" :key="r.key">
                  <td class="ps-3">{{ r.date }}</td>
                  <td class="text-end">{{ r.distance }}</td>
                  <td class="text-end">{{ r.trip }}</td>
                  <td class="text-end">{{ r.idle }}</td>
                  <td class="text-end pe-3">{{ r.idlePct }}</td>
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
                  <th class="ps-3">Date</th>
                  <th class="text-end">Travelled Distance</th>
                  <th class="text-end">Trip Duration</th>
                  <th class="text-end">Idle Duration</th>
                  <th class="text-end pe-3">Idle Percentage</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rowsMonthlySummary" :key="r.key">
                  <td class="ps-3">{{ r.date }}</td>
                  <td class="text-end">{{ r.distance }}</td>
                  <td class="text-end">{{ r.trip }}</td>
                  <td class="text-end">{{ r.idle }}</td>
                  <td class="text-end pe-3">{{ r.idlePct }}</td>
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
                  <th class="ps-3">Vehicle ID</th>
                  <th>Date</th>
                  <th class="text-end">Travelled Distance</th>
                  <th class="text-end">Trip Duration</th>
                  <th class="text-end">Idle Duration</th>
                  <th class="text-end pe-3">Idle Percentage</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in rowsMonthlyVehicleList" :key="r.key">
                  <td class="ps-3 text-primary">{{ r.vehicle }}</td>
                  <td>{{ r.date }}</td>
                  <td class="text-end">{{ r.distance }}</td>
                  <td class="text-end">{{ r.trip }}</td>
                  <td class="text-end">{{ r.idle }}</td>
                  <td class="text-end pe-3">{{ r.idlePct }}</td>
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

const duration = ref('2025-08-26 to 2025-08-31');
const vehicle = ref('VGPS2563');
const viewType = ref('Daily Breakdown (with map)');

const rowsDailyTrips = ref([
  {
    key: 1,
    date: '26/08/2025',
    startTime: '05:48 AM',
    startLocation: 'Exit 3506, Bukit Jelutong North Intersection, Persiaran Gerbang Utama E35, 40150 Shah Alam, Selangor',
    endTime: '05:49 AM',
    endLocation: 'Persiaran Gerbang Utama, Bukit Jelutong',
    distance: '1.00 KM'
  },
  {
    key: 2,
    date: '26/08/2025',
    startTime: '06:15 AM',
    startLocation: 'Exit 3510, Bukit Jelutong East Intersection, Persiaran Gerbang Utama E35, 40150 Shah Alam, Selangor',
    endTime: '06:16 AM',
    endLocation: 'Persiaran Gerbang Timur, Bukit Jelutong',
    distance: '3.00 KM'
  },
  {
    key: 3,
    date: '28/08/2025',
    startTime: '07:30 AM',
    startLocation: 'Persiaran Gerbang Utama, Bukit Jelutong',
    endTime: '08:45 AM',
    endLocation: 'Kuala Lumpur City Centre',
    distance: '35.50 KM'
  },
  {
    key: 4,
    date: '28/08/2025',
    startTime: '05:00 PM',
    startLocation: 'Kuala Lumpur City Centre',
    endTime: '06:30 PM',
    endLocation: 'Persiaran Gerbang Utama, Bukit Jelutong',
    distance: '36.20 KM'
  },
  {
    key: 5,
    date: '29/08/2025',
    startTime: '08:00 AM',
    startLocation: 'Bukit Jelutong',
    endTime: '09:00 AM',
    endLocation: 'Subang Jaya',
    distance: '40.00 KM'
  },
  {
    key: 6,
    date: '29/08/2025',
    startTime: '06:00 PM',
    startLocation: 'Subang Jaya',
    endTime: '07:10 PM',
    endLocation: 'Bukit Jelutong',
    distance: '40.00 KM'
  },
  {
    key: 7,
    date: '30/08/2025',
    startTime: '08:30 AM',
    startLocation: 'Bukit Jelutong',
    endTime: '09:45 AM',
    endLocation: 'Shah Alam',
    distance: '25.00 KM'
  },
  {
    key: 8,
    date: '30/08/2025',
    startTime: '05:15 PM',
    startLocation: 'Shah Alam',
    endTime: '06:15 PM',
    endLocation: 'Bukit Jelutong',
    distance: '26.00 KM'
  }
]);

const rowsDailyBreakdown = ref([
  {
    key: 1,
    date: '26/08/2025 - Saturday',
    distance: '126.53 KM',
    isOpen: true,
    summary: {
      date: '26/08/2025 - Saturday',
      dist: '126.53 km',
      dur: '2h 8m 8s',
      idle: '0',
      behav: '12 SV, 1 HA'
    },
    timeline: [
      { time: '05:48 AM', location: 'Exit 3506, Bukit Jelutong North Intersection, Persiaran Gerbang Utama E35, 40150 Shah Alam, Selangor', dist: '1KM', dur: '14m 59s', type: 'start' },
      { time: '05:49 AM', location: 'Persiaran Gerbang Utama, Bukit Jelutong', type: 'end' },
      { time: '06:15 AM', location: 'Exit 3510, Bukit Jelutong East Intersection, Persiaran Gerbang Utama E35, 40150 Shah Alam, Selangor', dist: '3KM', dur: '44m 15s', alert: '12 SV', type: 'start' },
      { time: '06:16 AM', location: 'Persiaran Gerbang Timur, Bukit Jelutong', type: 'end' }
    ]
  },
  {
    key: 2,
    date: '27/08/2025 - Sunday',
    distance: '0.00 KM',
    isOpen: false,
    summary: {
        date: '27/08/2025 - Sunday',
        dist: '0.00 km',
        dur: '0h 0m 0s',
        idle: '0',
        behav: '-'
    },
    timeline: []
  },
  {
    key: 3,
    date: '28/08/2025 - Monday',
    distance: '118.22 KM',
    isOpen: false,
    summary: {
      date: '28/08/2025 - Monday',
      dist: '118.22 km',
      dur: '1h 50m 30s',
      idle: '5m',
      behav: '2 SV'
    },
    timeline: [
        { time: '07:30 AM', location: 'Persiaran Gerbang Utama, Bukit Jelutong', dist: '35.5KM', dur: '1h 15m', type: 'start' },
        { time: '08:45 AM', location: 'Kuala Lumpur City Centre', type: 'end' },
        { time: '05:00 PM', location: 'Kuala Lumpur City Centre', dist: '36.2KM', dur: '1h 30m', alert: '2 SV', type: 'start' },
        { time: '06:30 PM', location: 'Persiaran Gerbang Utama, Bukit Jelutong', type: 'end' }
    ]
  },
  {
      key: 4,
      date: '29/08/2025 - Tuesday',
      distance: '145.90 KM',
      isOpen: false,
      summary: {
        date: '29/08/2025 - Tuesday',
        dist: '145.90 km',
        dur: '2h 15m 10s',
        idle: '20m',
        behav: '5 SV'
      },
      timeline: [
        { time: '08:00 AM', location: 'Bukit Jelutong', dist: '40KM', dur: '1h', type: 'start' },
        { time: '09:00 AM', location: 'Subang Jaya', type: 'end' },
        { time: '06:00 PM', location: 'Subang Jaya', dist: '40KM', dur: '1h 10m', alert: '5 SV', type: 'start' },
        { time: '07:10 PM', location: 'Bukit Jelutong', type: 'end' }
      ]
  },
  { key: 5, date: '30/08/2025 - Wednesday', distance: '121.15 KM', isOpen: false },
  { key: 6, date: '31/08/2025 - Thursday', distance: '134.67 KM', isOpen: false }
]);

const rowsDailySummary = ref([
  { key: 1, date: '26/08/2025', distance: '126.53 Kilo-meters', trip: '2h 8m 8s', idle: '0h 13m 41s', idlePct: '11.23%' },
  { key: 2, date: '27/08/2025', distance: '0.00 Kilo-meters', trip: '0h 0m 0s', idle: '0h 0m 0s', idlePct: '0%' },
  { key: 3, date: '28/08/2025', distance: '118.22 Kilo-meters', trip: '1h 50m 30s', idle: '0h 5m 0s', idlePct: '4.5%' },
  { key: 4, date: '29/08/2025', distance: '145.90 Kilo-meters', trip: '2h 15m 10s', idle: '0h 20m 0s', idlePct: '14.8%' },
  { key: 5, date: '30/08/2025', distance: '121.15 Kilo-meters', trip: '1h 55m 45s', idle: '0h 10m 15s', idlePct: '8.8%' },
  { key: 6, date: '31/08/2025', distance: '134.67 Kilo-meters', trip: '2h 5m 20s', idle: '0h 12m 30s', idlePct: '9.9%' },
]);

const rowsMonthlySummary = ref([
  { key: 1, date: '05/2025', distance: '8515.33 KM', trip: '7d 13h 19m 56s', idle: '4h 20m 33s', idlePct: '2.4%' },
  { key: 2, date: '06/2025', distance: '7840.12 KM', trip: '6d 22h 10m 15s', idle: '3h 45m 20s', idlePct: '2.2%' },
  { key: 3, date: '07/2025', distance: '9102.50 KM', trip: '8d 05h 30m 45s', idle: '5h 10m 10s', idlePct: '2.6%' },
]);

const rowsMonthlyVehicleList = ref([
  { key: 1, vehicle: 'VGP7894', date: '08/2025', distance: '9200.45 KM', trip: '8d 5h 12m 30s', idle: '5h 15m 20s', idlePct: '03%' },
  { key: 2, vehicle: 'VGP1023', date: '08/2025', distance: '8100.20 KM', trip: '7d 2h 10m 15s', idle: '4h 05m 10s', idlePct: '02%' },
  { key: 3, vehicle: 'VGP4567', date: '08/2025', distance: '7540.80 KM', trip: '6d 18h 45m 20s', idle: '3h 50m 30s', idlePct: '03%' },
  { key: 4, vehicle: 'VGP8910', date: '08/2025', distance: '8800.60 KM', trip: '7d 20h 30m 45s', idle: '4h 30m 15s', idlePct: '02%' },
  { key: 5, vehicle: 'VGP2345', date: '08/2025', distance: '6900.30 KM', trip: '5d 15h 20m 10s', idle: '3h 10m 05s', idlePct: '04%' },
  { key: 6, vehicle: 'VGP6789', date: '08/2025', distance: '9500.90 KM', trip: '8d 10h 05m 50s', idle: '5h 40m 25s', idlePct: '03%' },
  { key: 7, vehicle: 'VGP3456', date: '08/2025', distance: '7200.50 KM', trip: '6d 08h 15m 30s', idle: '3h 25m 40s', idlePct: '03%' },
  { key: 8, vehicle: 'VGP1234', date: '08/2025', distance: '8300.75 KM', trip: '7d 06h 40m 20s', idle: '4h 15m 55s', idlePct: '02%' },
  { key: 9, vehicle: 'VGP5678', date: '08/2025', distance: '7900.10 KM', trip: '6d 22h 55m 10s', idle: '4h 00m 05s', idlePct: '03%' },
  { key: 10, vehicle: 'VGP8901', date: '08/2025', distance: '8600.40 KM', trip: '7d 16h 25m 35s', idle: '4h 45m 50s', idlePct: '02%' },
  { key: 11, vehicle: 'VGP2341', date: '08/2025', distance: '7100.80 KM', trip: '6d 04h 10m 15s', idle: '3h 30m 20s', idlePct: '04%' },
  { key: 12, vehicle: 'VGP9876', date: '08/2025', distance: '9000.25 KM', trip: '8d 02h 50m 45s', idle: '5h 05m 10s', idlePct: '03%' },
  { key: 13, vehicle: 'VGP5432', date: '08/2025', distance: '7400.60 KM', trip: '6d 12h 35m 55s', idle: '3h 45m 30s', idlePct: '03%' },
]);

const rowsDailyVehicleList = ref([
  { key: 1, date: '25/08/2025', vehicle: 'VGP7894', distance: '120.45 KM', trip: '2h 15m 30s', idle: '15m 20s', idlePct: '11.3%' },
  { key: 2, date: '25/08/2025', vehicle: 'VGP1023', distance: '98.20 KM', trip: '1h 50m 10s', idle: '10m 15s', idlePct: '9.3%' },
  { key: 3, date: '25/08/2025', vehicle: 'VGP4567', distance: '145.80 KM', trip: '3h 05m 20s', idle: '25m 30s', idlePct: '13.7%' },
  { key: 4, date: '25/08/2025', vehicle: 'VGP8910', distance: '110.60 KM', trip: '2h 10m 45s', idle: '12m 15s', idlePct: '9.4%' },
  { key: 5, date: '25/08/2025', vehicle: 'VGP2345', distance: '85.30 KM', trip: '1h 30m 10s', idle: '08m 05s', idlePct: '8.9%' },
  { key: 6, date: '25/08/2025', vehicle: 'VGP6789', distance: '132.90 KM', trip: '2h 45m 50s', idle: '20m 25s', idlePct: '12.3%' },
  { key: 7, date: '25/08/2025', vehicle: 'VGP3456', distance: '105.50 KM', trip: '2h 00m 30s', idle: '11m 40s', idlePct: '9.7%' },
  { key: 8, date: '26/08/2025', vehicle: 'VGP7894', distance: '115.40 KM', trip: '2h 05m 15s', idle: '14m 20s', idlePct: '11.4%' },
  { key: 9, date: '26/08/2025', vehicle: 'VGP1023', distance: '95.10 KM', trip: '1h 45m 05s', idle: '09m 10s', idlePct: '8.7%' },
  { key: 10, date: '26/08/2025', vehicle: 'VGP4567', distance: '150.20 KM', trip: '3h 15m 40s', idle: '28m 15s', idlePct: '14.4%' },
  { key: 11, date: '26/08/2025', vehicle: 'VGP8910', distance: '118.70 KM', trip: '2h 20m 55s', idle: '13m 30s', idlePct: '9.5%' },
  { key: 12, date: '26/08/2025', vehicle: 'VGP2345', distance: '88.50 KM', trip: '1h 35m 25s', idle: '08m 45s', idlePct: '9.1%' },
  { key: 13, date: '26/08/2025', vehicle: 'VGP6789', distance: '128.60 KM', trip: '2h 40m 35s', idle: '19m 50s', idlePct: '12.3%' },
  { key: 14, date: '26/08/2025', vehicle: 'VGP3456', distance: '102.30 KM', trip: '1h 55m 20s', idle: '10m 55s', idlePct: '9.4%' },
]);

const mapEl = ref(null);
let map = null;
function initMap() {
  if (!mapEl.value) return;
  if (map) {
    map.remove();
    map = null;
  }
  map = L.map(mapEl.value).setView([3.111, 101.533], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  const route = [
    [3.111, 101.533], // Bukit Jelutong
    [3.115, 101.540],
    [3.120, 101.550],
    [3.130, 101.600],
    [3.140, 101.650],
    [3.150, 101.700],
    [3.157, 101.711], // KLCC
  ];
  L.polyline(route, { color: '#0b5ed7', weight: 4 }).addTo(map);
  L.marker(route[0]).bindPopup('Start Time 05:48 AM • 26/08/2025<br>Bukit Jelutong').addTo(map);
  L.marker(route[route.length - 1]).bindPopup('End Time 06:30 AM • 26/08/2025<br>Kuala Lumpur').addTo(map);
  map.fitBounds(L.polyline(route).getBounds(), { padding: [50, 50] });
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
    <div class="card-header bg-white border-bottom-0 pt-3 ps-3"><h6 class="mb-0 fw-bold">Vehicle Activity Report Result</h6></div>
    <div class="card-body pt-0">
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
          <div class="d-flex align-items-center gap-3 mb-2">
             <div class="d-flex align-items-center gap-1"><span style="width:10px;height:10px;background:#e83e8c;display:inline-block;border-radius:2px;"></span> <span class="small text-muted">Trip Duration</span></div>
             <div class="d-flex align-items-center gap-1"><span style="width:10px;height:10px;background:#0b0f28;display:inline-block;border-radius:2px;"></span> <span class="small text-muted">Idle Duration</span></div>
             <div class="d-flex align-items-center gap-1"><span style="width:10px;height:10px;background:#339af0;display:inline-block;border-radius:2px;"></span> <span class="small text-muted">Distance</span></div>
          </div>
          <svg viewBox="0 0 600 260" width="100%" height="220">
            <rect x="0" y="0" width="600" height="220" fill="#fff" rx="8"/>
            <!-- Grid lines -->
            <line x1="40" y1="40" x2="580" y2="40" stroke="#f1f3f5" stroke-dasharray="4"/>
            <line x1="40" y1="80" x2="580" y2="80" stroke="#f1f3f5" stroke-dasharray="4"/>
            <line x1="40" y1="120" x2="580" y2="120" stroke="#f1f3f5" stroke-dasharray="4"/>
            <line x1="40" y1="160" x2="580" y2="160" stroke="#f1f3f5" stroke-dasharray="4"/>
            <line x1="40" y1="200" x2="580" y2="200" stroke="#f1f3f5" stroke-dasharray="4"/>
            <g transform="translate(10,0)">
              <!-- Bars (Distance - Blue) -->
              <rect v-for="(bar,i) in [140, 100, 80, 160, 120, 70, 110]" :key="'b'+i" :x="40 + i*75" :y="200-bar" width="20" :height="bar" fill="#339af0" rx="2"/>
              <!-- Bars (Idle - Dark) - Mocking different values -->
              <rect v-for="(bar,i) in [40, 30, 20, 50, 40, 10, 30]" :key="'i'+i" :x="40 + i*75 + 24" :y="200-bar" width="20" :height="bar" fill="#0b0f28" rx="2"/>
              <!-- Line (Trip Duration - Pink) -->
              <path d="M50 120 L125 80 L200 140 L275 70 L350 110 L425 90 L500 130" stroke="#e83e8c" stroke-width="3" fill="none"/>
              <circle v-for="(cx,i) in [50, 125, 200, 275, 350, 425, 500]" :key="'p'+i" :cx="cx" :cy="[120, 80, 140, 70, 110, 90, 130][i]" r="4" fill="#e83e8c"/>
            </g>
          </svg>
        </div>
      </div>
    </div>
    <div class="col-12 col-lg-6">
      <div class="card border rounded-3 shadow-0 h-100">
        <div class="card-body">
          <div class="row g-3 h-100">
            <div class="col-12 col-md-5">
              <div class="h-100 d-flex flex-column justify-content-center">
                <div class="small text-muted mb-1">Total Duration</div>
                <div class="display-6 fw-bold mb-3">2h 8m 8s</div>

                <div class="progress mb-1" style="height: 8px;"><div class="progress-bar" style="width:90%; background-color: #e83e8c;"></div></div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                   <div class="small fw-semibold" style="color: #e83e8c;">Trip Duration</div>
                   <div class="fw-bold">1h 54m 27s</div>
                </div>

                <div class="progress mb-1" style="height: 8px;"><div class="progress-bar" style="width:10%; background-color: #0b0f28;"></div></div>
                <div class="d-flex justify-content-between align-items-center">
                   <div class="small fw-semibold" style="color: #0b0f28;">Idle Duration</div>
                   <div class="fw-bold">13m 41s</div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-7 border-start">
              <div class="ps-md-3 h-100 d-flex flex-column justify-content-center">
                <h6 class="fw-bold mb-3">Summary</h6>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Total Distance (km)</span><span class="fw-semibold">126.53 KM</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Total Trip Duration (hr)</span><span class="fw-semibold">1h 54m 27s</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Total Idling (hr)</span><span class="fw-semibold">13m 41s</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Idling Percentage vs Trip Duration</span><span class="fw-semibold">11.23%</span></div>
                <div class="d-flex justify-content-between mb-2"><span class="small text-muted">Average Fuel Consumption (km/litre)</span><span class="fw-semibold">0 km/l</span></div>
                <div class="d-flex justify-content-between"><span class="small text-muted">Total Fuel Usage (litre)</span><span class="fw-semibold">0 Litre</span></div>
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
