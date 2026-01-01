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
              <option>Trip Summary</option>
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

    <DailyBreakdown v-if="viewType === 'Daily Breakdown'" :rowsDailyTrips="rowsDailyTrips" />

    <TripSummary v-else-if="viewType === 'Trip Summary'" :rowsTripSummary="rowsTripSummary" />

    <DailySummaryList v-else-if="viewType === 'Daily Summary List'" :rowsDailyVehicleList="rowsDailyVehicleList" />

    <DailyBreakdownMap v-else-if="viewType === 'Daily Breakdown (with map)'" :rowsDailyBreakdown="rowsDailyBreakdown" />

    <DailySummary v-else-if="viewType === 'Daily Summary'" :rowsDailySummary="rowsDailySummary" />

    <MonthlySummary v-else-if="viewType === 'Monthly Summary'" :rowsMonthlySummary="rowsMonthlySummary" />

    <MonthlySummaryList v-else-if="viewType === 'Monthly Summary List'" :rowsMonthlyVehicleList="rowsMonthlyVehicleList" />

  </div>
</template>
 
<script setup>
import { ref } from 'vue';
import DailyBreakdown from './components/trip-analysis/DailyBreakdown.vue';
import DailyBreakdownMap from './components/trip-analysis/DailyBreakdownMap.vue';
import DailySummary from './components/trip-analysis/DailySummary.vue';
import DailySummaryList from './components/trip-analysis/DailySummaryList.vue';
import MonthlySummary from './components/trip-analysis/MonthlySummary.vue';
import MonthlySummaryList from './components/trip-analysis/MonthlySummaryList.vue';
import TripSummary from './components/trip-analysis/TripSummary.vue';

const duration = ref('2025-08-26 to 2025-08-31');
const vehicle = ref('VGPS2563');
const viewType = ref('Trip Summary');

const rowsTripSummary = ref([
  {
    key: 1, vehicleId: 'VHCL-1002', vehicleName: 'Turbo Hawk ZR',
    distTotal: '5680.5', distAvg: '175.6',
    durTotal: '145h 30m', durAvg: '4h 15m',
    stopTotal: '120h 15m', stopAvg: '3h 45m',
    idleTotal: '12h 15m', idleAvg: '0h 25m',
    speedMax: '110 km/h', speedAvg: '65 km/h',
    fuelTotal: '450.5 L', avgFuel: '7.9 L/100km',
    util: '45.5%'
  },
  {
    key: 2, vehicleId: 'VHCL-1003', vehicleName: 'Stealth Chaser X',
    distTotal: '3000.8', distAvg: '130.0',
    durTotal: '98h 10m', durAvg: '3h 45m',
    stopTotal: '150h 20m', stopAvg: '5h 30m',
    idleTotal: '8h 40m', idleAvg: '0h 20m',
    speedMax: '105 km/h', speedAvg: '60 km/h',
    fuelTotal: '280.2 L', avgFuel: '9.3 L/100km',
    util: '38.2%'
  },
  {
    key: 3, vehicleId: 'VHCL-1004', vehicleName: 'Lunar Explorer 5',
    distTotal: '4550.0', distAvg: '140.2',
    durTotal: '112h 20m', durAvg: '4h 05m',
    stopTotal: '130h 40m', stopAvg: '4h 45m',
    idleTotal: '10h 30m', idleAvg: '0h 22m',
    speedMax: '115 km/h', speedAvg: '68 km/h',
    fuelTotal: '390.0 L', avgFuel: '8.5 L/100km',
    util: '42.1%'
  },
  {
    key: 4, vehicleId: 'VHCL-1005', vehicleName: 'Raptor GT',
    distTotal: '5000.4', distAvg: '160.4',
    durTotal: '130h 45m', durAvg: '4h 30m',
    stopTotal: '110h 15m', stopAvg: '3h 50m',
    idleTotal: '11h 15m', idleAvg: '0h 24m',
    speedMax: '120 km/h', speedAvg: '70 km/h',
    fuelTotal: '420.4 L', avgFuel: '8.4 L/100km',
    util: '48.9%'
  },
  {
    key: 5, vehicleId: 'VHCL-1006', vehicleName: 'Shadow Hunter 12',
    distTotal: '6200.7', distAvg: '190.5',
    durTotal: '155h 10m', durAvg: '5h 00m',
    stopTotal: '95h 30m', stopAvg: '3h 10m',
    idleTotal: '14h 20m', idleAvg: '0h 28m',
    speedMax: '125 km/h', speedAvg: '72 km/h',
    fuelTotal: '510.8 L', avgFuel: '8.2 L/100km',
    util: '52.3%'
  },
  {
    key: 6, vehicleId: 'VHCL-1007', vehicleName: 'Volt Fusion R',
    distTotal: '4500.1', distAvg: '145.8',
    durTotal: '115h 30m', durAvg: '4h 10m',
    stopTotal: '140h 20m', stopAvg: '5h 00m',
    idleTotal: '9h 45m', idleAvg: '0h 21m',
    speedMax: '108 km/h', speedAvg: '62 km/h',
    fuelTotal: '360.5 L', avgFuel: '8.0 L/100km',
    util: '40.5%'
  },
  {
    key: 7, vehicleId: 'VHCL-1008', vehicleName: 'Quantum Leap 6',
    distTotal: '4700.3', distAvg: '155.4',
    durTotal: '122h 40m', durAvg: '4h 25m',
    stopTotal: '125h 10m', stopAvg: '4h 30m',
    idleTotal: '10h 50m', idleAvg: '0h 23m',
    speedMax: '112 km/h', speedAvg: '66 km/h',
    fuelTotal: '395.2 L', avgFuel: '8.4 L/100km',
    util: '43.8%'
  },
  {
    key: 8, vehicleId: 'VHCL-1009', vehicleName: 'Meteor Strike 11',
    distTotal: '5200.2', distAvg: '185.0',
    durTotal: '135h 20m', durAvg: '4h 50m',
    stopTotal: '115h 40m', stopAvg: '4h 10m',
    idleTotal: '12h 10m', idleAvg: '0h 26m',
    speedMax: '118 km/h', speedAvg: '69 km/h',
    fuelTotal: '440.6 L', avgFuel: '8.5 L/100km',
    util: '49.2%'
  },
  {
    key: 9, vehicleId: 'VHCL-1010', vehicleName: 'Apex Predator S',
    distTotal: '5900.6', distAvg: '210.7',
    durTotal: '150h 00m', durAvg: '5h 15m',
    stopTotal: '100h 50m', stopAvg: '3h 30m',
    idleTotal: '13h 30m', idleAvg: '0h 27m',
    speedMax: '122 km/h', speedAvg: '71 km/h',
    fuelTotal: '490.8 L', avgFuel: '8.3 L/100km',
    util: '55.6%'
  },
  {
    key: 10, vehicleId: 'VHCL-1011', vehicleName: 'Falcon Cruiser Z',
    distTotal: '5300.9', distAvg: '195.2',
    durTotal: '140h 15m', durAvg: '5h 05m',
    stopTotal: '118h 25m', stopAvg: '4h 15m',
    idleTotal: '12h 45m', idleAvg: '0h 26m',
    speedMax: '116 km/h', speedAvg: '67 km/h',
    fuelTotal: '455.4 L', avgFuel: '8.6 L/100km',
    util: '50.1%'
  }
]);

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
</script>

<style scoped>
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
</style>
