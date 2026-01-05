<template>
  <div>
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item">Reports & Analytics</li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Status Report</li>
      </ol>
    </div>
    <h4 class="mb-3">Vehicle Status Report</h4>
    <div class="card panel border rounded-3 shadow-0 mb-3">
      <div class="card-header"><h6 class="mb-0">Search Option</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-4">
            <label class="form-label small">Vehicle</label>
            <select class="form-select">
              <option>--Select an Vehicle--</option>
              <option>VHCL-1001</option>
              <option>VHCL-1002</option>
              <option>VHCL-1003</option>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label small">Group</label>
            <select class="form-select">
              <option>-- Select Group --</option>
              <option>Group A</option>
              <option>Group B</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label small">Report Format</label>
            <select class="form-select">
              <option>-- Report Format --</option>
              <option>Website</option>
              <option>Excel</option>
              <option>PDF</option>
            </select>
          </div>
          <div class="col-12 col-md-1 text-md-end">
            <button class="btn btn-app-dark w-100">Submit</button>
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
                <th>Vehicle ID</th>
                <th>Owner</th>
                <th>Type/Model</th>
                <th>Device Model</th>
                <th>IMEI</th>
                <th>ICCID</th>
                <th>Odometer</th>
                <th>Power</th>
                <th>Last Report</th>
                <th>Longitude</th>
                <th>Latitude</th>
                <th>Location</th>
                <th>Speed</th>
                <th>GPS Signal</th>
                <th>Ignition</th>
                <th>Last Ignition On</th>
                <th>Last Ignition Off</th>
                <th>Activation Date</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id">
                <td>{{ row.vehicle_id }}</td>
                <td>{{ row.owner }}</td>
                <td>{{ row.type_model }}</td>
                <td>{{ row.device_model }}</td>
                <td>{{ row.imei }}</td>
                <td>{{ row.iccid }}</td>
                <td>{{ row.odometer }}</td>
                <td>
                  <span class="badge" :class="row.power === 'On' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                    {{ row.power }}
                  </span>
                </td>
                <td>{{ row.last_report }}</td>
                <td>{{ row.longitude }}</td>
                <td>{{ row.latitude }}</td>
                <td>{{ row.location }}</td>
                <td>{{ row.speed }}</td>
                <td>
                  <i class="bi bi-broadcast me-1" :class="{'text-success': row.gps_signal === 'Good', 'text-warning': row.gps_signal === 'Fair', 'text-danger': row.gps_signal === 'Weak'}"></i>
                  {{ row.gps_signal }}
                </td>
                <td>
                  <span class="badge" :class="row.ignition ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                    {{ row.ignition ? 'ON' : 'OFF' }}
                  </span>
                </td>
                <td>{{ row.last_ignition_on }}</td>
                <td>{{ row.last_ignition_off }}</td>
                <td>{{ row.activation_date }}</td>
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
  {
    id: 1,
    vehicle_id: 'VHCL-1001',
    owner: 'John Doe',
    type_model: 'Toyota Camry',
    device_model: 'FMB120',
    imei: '865432045678901',
    iccid: '8986012345678901234',
    odometer: '12,345 km',
    power: 'On',
    last_report: '25/08/2025 - 18:00',
    longitude: '101.71032',
    latitude: '3.13032',
    location: 'PLAZA OSK',
    speed: '0 km/h',
    gps_signal: 'Fair',
    ignition: true,
    last_ignition_on: '25/08/2025 - 18:00',
    last_ignition_off: '25/08/2025 - 18:00',
    activation_date: '01/01/2024'
  },
  {
    id: 2,
    vehicle_id: 'VHCL-1002',
    owner: 'Jane Smith',
    type_model: 'Honda Civic',
    device_model: 'FMB120',
    imei: '865432045678902',
    iccid: '8986012345678901235',
    odometer: '5,678 km',
    power: 'On',
    last_report: '25/08/2025 - 18:00',
    longitude: '101.71032',
    latitude: '3.13032',
    location: 'LORONG LOKE YEW JALAN LOK...',
    speed: '0 km/h',
    gps_signal: 'Good',
    ignition: false,
    last_ignition_on: '25/08/2025 - 18:00',
    last_ignition_off: '25/08/2025 - 18:00',
    activation_date: '15/02/2024'
  },
  {
    id: 3,
    vehicle_id: 'VHCL-1003',
    owner: 'Logistics Corp',
    type_model: 'Isuzu Elf',
    device_model: 'FMB120',
    imei: '865432045678903',
    iccid: '8986012345678901236',
    odometer: '45,000 km',
    power: 'Off',
    last_report: '25/08/2025 - 18:00',
    longitude: '101.71032',
    latitude: '3.13032',
    location: 'LORONG LOKE YEW JALAN LOK...',
    speed: '0 km/h',
    gps_signal: 'Good',
    ignition: false,
    last_ignition_on: '25/08/2025 - 18:00',
    last_ignition_off: '25/08/2025 - 18:00',
    activation_date: '10/03/2024'
  },
  {
    id: 4,
    vehicle_id: 'VHCL-1004',
    owner: 'Transport Ltd',
    type_model: 'Scania R500',
    device_model: 'FMB120',
    imei: '865432045678904',
    iccid: '8986012345678901237',
    odometer: '120,500 km',
    power: 'On',
    last_report: '25/08/2025 - 18:00',
    longitude: '101.71032',
    latitude: '3.13032',
    location: 'MEGAN PHILEO PROMENADE B...',
    speed: '0 km/h',
    gps_signal: 'Weak',
    ignition: true,
    last_ignition_on: '25/08/2025 - 18:00',
    last_ignition_off: '25/08/2025 - 18:00',
    activation_date: '20/04/2024'
  },
  {
    id: 5,
    vehicle_id: 'VHCL-1005',
    owner: 'John Doe',
    type_model: 'Toyota Camry',
    device_model: 'FMB120',
    imei: '865432045678905',
    iccid: '8986012345678901238',
    odometer: '15,000 km',
    power: 'On',
    last_report: '25/08/2025 - 18:00',
    longitude: '101.71032',
    latitude: '3.13032',
    location: 'PLAZA OSK',
    speed: '0 km/h',
    gps_signal: 'Fair',
    ignition: false,
    last_ignition_on: '25/08/2025 - 18:00',
    last_ignition_off: '25/08/2025 - 18:00',
    activation_date: '05/05/2024'
  },
  {
    id: 6,
    vehicle_id: 'VHCL-1006',
    owner: 'City Taxi',
    type_model: 'Proton Saga',
    device_model: 'FMB120',
    imei: '865432045678906',
    iccid: '8986012345678901239',
    odometer: '30,000 km',
    power: 'On',
    last_report: '25/08/2025 - 18:00',
    longitude: '101.71032',
    latitude: '3.13032',
    location: 'JALAN CTA 2, KUALA LUMPUR I...',
    speed: '0 km/h',
    gps_signal: 'Good',
    ignition: true,
    last_ignition_on: '25/08/2025 - 18:00',
    last_ignition_off: '25/08/2025 - 18:00',
    activation_date: '12/06/2024'
  },
  {
    id: 7,
    vehicle_id: 'VHCL-1007',
    owner: 'Jane Smith',
    type_model: 'Honda Civic',
    device_model: 'FMB120',
    imei: '865432045678907',
    iccid: '8986012345678901240',
    odometer: '8,000 km',
    power: 'Off',
    last_report: '25/08/2025 - 18:00',
    longitude: '101.71032',
    latitude: '3.13032',
    location: 'GAMBANG R&R W TO E',
    speed: '0 km/h',
    gps_signal: 'Weak',
    ignition: false,
    last_ignition_on: '25/08/2025 - 18:00',
    last_ignition_off: '25/08/2025 - 18:00',
    activation_date: '25/07/2024'
  },
  {
    id: 8,
    vehicle_id: 'VHCL-1008',
    owner: 'John Doe',
    type_model: 'Toyota Camry',
    device_model: 'FMB120',
    imei: '865432045678908',
    iccid: '8986012345678901241',
    odometer: '20,000 km',
    power: 'On',
    last_report: '25/08/2025 - 18:00',
    longitude: '101.71032',
    latitude: '3.13032',
    location: 'PLAZA OSK',
    speed: '0 km/h',
    gps_signal: 'Fair',
    ignition: true,
    last_ignition_on: '25/08/2025 - 18:00',
    last_ignition_off: '25/08/2025 - 18:00',
    activation_date: '30/08/2024'
  },
]);
</script>

<style scoped>
thead.table-dark tr th { background-color: #0b0f28 !important; color: #fff; }
tbody tr td { font-size: 13px; }
.panel .card-body { padding-top: 1rem; padding-bottom: 1rem; }
.card-header h6 { font-weight: 600; }
.table-striped tbody tr:nth-of-type(odd) { --bs-table-accent-bg: #f8f9fb; }
.badge { font-weight: 500; font-size: 0.75rem; }
</style>
