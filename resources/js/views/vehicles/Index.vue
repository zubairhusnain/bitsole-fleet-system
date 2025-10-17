<template>
  <div class="vehicles-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Management</li>
      </ol>
    </div>

    <!-- Page Title and Actions -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h4 class="mb-0 fw-semibold">Vehicles Management</h4>
        <small class="text-muted">Inventory and status</small>
      </div>
      <div class="d-flex align-items-center gap-1">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input v-model="query" type="text" class="form-control input-w-360" placeholder="Search vehicle/ID" />
        </div>
        <button class="btn btn-outline-secondary btn-icon-44" title="Filters"><i class="bi bi-sliders2"></i></button>
        <RouterLink to="/vehicles/new" class="btn btn-app-dark"><i class="bi bi-plus-lg me-1"></i> List New Vehicle</RouterLink>
      </div>
    </div>

    <!-- Table -->
    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
            <thead class="thead-app-dark">
              <tr>
                <th class="fw-semibold py-2">Vehicle ID</th>
                <th class="fw-semibold py-2">Vehicles Name</th>
                <th class="fw-semibold py-2">VIN Number</th>
                <th class="fw-semibold py-2">Plate number</th>
                <th class="fw-semibold py-2">Odometer</th>
                <th class="fw-semibold py-2">Ignition</th>
                <th class="fw-semibold py-2">Speed (Km/h)</th>
                <th class="fw-semibold py-2">Location</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in pagedRows" :key="row.id" class="border-bottom">
                <td class="text-muted text-nowrap">{{ row.code }}</td>
                <td class="text-nowrap">{{ row.name }}</td>
                <td class="text-muted text-nowrap">{{ row.vin }}</td>
                <td class="text-muted text-nowrap">{{ row.plate }}</td>
                <td class="text-muted text-nowrap">{{ row.odometer }}</td>
                <td>
                  <span :class="['badge rounded-pill badge-app', row.ignition === 'On' ? 'text-bg-success' : 'text-bg-secondary']">{{ row.ignition }}</span>
                </td>
                <td class="text-muted text-nowrap">{{ row.speed }}</td>
                <td class="text-nowrap">{{ row.location }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">Showing {{ startIndex + 1 }} to {{ Math.min(startIndex + pageSize, rows.length) }} of {{ rows.length }} results</div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item" :class="{ disabled: page === 1 }"><button class="page-link" @click="prevPage">‹</button></li>
            <li class="page-item" v-for="n in totalPages" :key="n" :class="{ active: page === n }"><button class="page-link" @click="goPage(n)">{{ n }}</button></li>
            <li class="page-item" :class="{ disabled: page === totalPages }"><button class="page-link" @click="nextPage">›</button></li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const query = ref('');
const page = ref(1);
const pageSize = 16;

// Sample vehicle rows to match the structure in the screenshot
const rows = ref([
  { id: 1, code: 'VHCL-1001', name: 'Phantom Racer 9 Pro', vin: '1FTRX18W43NA12345', plate: 'D248-1982-6794', odometer: '212,300 KM', ignition: 'Off', speed: '0 km/h', location: '1234 Elm St, Springfield' },
  { id: 2, code: 'VHCL-1002', name: 'Racer X 2020', vin: '1G1TZX628X4F123456', plate: 'F732-6118-2945', odometer: '212,958 KM', ignition: 'On', speed: '95 km/h', location: '789 Pine St, Seattle, WA' },
  { id: 3, code: 'VHCL-1003', name: 'TurboMax Z', vin: '2C3CAC3G5AH123456', plate: 'G384-9917-8842', odometer: '213,612 KM', ignition: 'Off', speed: '0 km/h', location: '456 Oak Rd, Austin, TX' },
  { id: 4, code: 'VHCL-1004', name: 'Speedster 3000', vin: '1NAL3JAP4EC123456', plate: 'K185-4273-6021', odometer: '214,275 KM', ignition: 'On', speed: '110 km/h', location: '321 Maple Dr, Denver, CO' },
  { id: 5, code: 'VHCL-1005', name: 'Velocity Racer', vin: 'WAU3LJ5BE69A123456', plate: 'M569-8431-1186', odometer: '214,935 KM', ignition: 'Off', speed: '0 km/h', location: '987 Cedar Ln, Orlando, FL' },
  { id: 6, code: 'VHCL-1006', name: 'Extreme Speedster', vin: 'JHMZN2H5BSB123456', plate: 'R912-7032-5714', odometer: '215,600 KM', ignition: 'On', speed: '55 km/h', location: '654 Birch Blvd, Portland, OR' },
  { id: 7, code: 'VHCL-1007', name: 'Nitro Falcon', vin: '1ZVHT85H3757123456', plate: 'S334-6689-0078', odometer: '216,267 KM', ignition: 'Off', speed: '0 km/h', location: '147 Walnut St, Nashville, TN' },
  { id: 8, code: 'VHCL-1008', name: 'Thunderbolt X', vin: 'YV1RS5924X4234567', plate: 'T498-2205-7340', odometer: '216,932 KM', ignition: 'On', speed: '88 km/h', location: '258 Spruce Ct, Philadelphia, PA' },
  { id: 9, code: 'VHCL-1009', name: 'Lightning Racer', vin: '3N1AB7AP4FY123456', plate: 'W125-9873-1402', odometer: '217,600 KM', ignition: 'Off', speed: '0 km/h', location: '369 Ash Ave, San Diego, CA' },
  { id: 10, code: 'VHCL-1010', name: 'Flash 6000', vin: '5N1A2ZMHF9FN123456', plate: 'Y863-4451-3909', odometer: '218,275 KM', ignition: 'On', speed: '105 km/h', location: '159 Cherry Way, Miami, FL' },
  { id: 11, code: 'VHCL-1011', name: 'Rapid Racer V2', vin: '2G1FB1E31F9123456', plate: 'D248-1982-6794', odometer: '218,942 KM', ignition: 'Off', speed: '0 km/h', location: '753 Palm St, Charlotte, NC' },
  { id: 12, code: 'VHCL-1012', name: 'Supernova R', vin: '1FBAXX8BSK8B123456', plate: 'F732-6118-2945', odometer: '219,610 KM', ignition: 'On', speed: '72 km/h', location: '852 Fir Pl, Phoenix, AZ' },
  { id: 13, code: 'VHCL-1013', name: 'Gravity Racer', vin: '1J8HS582XBC123456', plate: 'G384-9917-8842', odometer: '220,278 KM', ignition: 'On', speed: '0 km/h', location: '951 Dogwood Dr, Indianapolis, IN' },
  { id: 14, code: 'VHCL-1014', name: 'Stellar Racer X1', vin: '2G8WP52S5X4123456', plate: 'K185-4273-6021', odometer: '220,950 KM', ignition: 'On', speed: '98 km/h', location: '258 Sycamore Ln, Dallas, TX' },
  { id: 15, code: 'VHCL-1015', name: 'Racer X 2020', vin: '1GKRRDE9A1P123456', plate: 'M569-8431-1186', odometer: '221,625 KM', ignition: 'On', speed: '65 km/h', location: '369 Cypress Blvd, New Orleans, LA' },
  { id: 16, code: 'VHCL-1016', name: 'Velocity Racer', vin: '1C4RJF8G1AC123456', plate: 'R912-7032-5714', odometer: '222,300 KM', ignition: 'Off', speed: '0 km/h', location: '789 Magnolia St, San Francisco, CA' },
]);

const filtered = computed(() => {
  if (!query.value) return rows.value;
  const q = query.value.toLowerCase();
  return rows.value.filter(r =>
    r.code.toLowerCase().includes(q) ||
    r.name.toLowerCase().includes(q) ||
    r.vin.toLowerCase().includes(q)
  );
});

const totalPages = computed(() => Math.ceil(filtered.value.length / pageSize));
const startIndex = computed(() => (page.value - 1) * pageSize);
const pagedRows = computed(() => filtered.value.slice(startIndex.value, startIndex.value + pageSize));

function goPage(n) { page.value = n; }
function prevPage() { if (page.value > 1) page.value--; }
function nextPage() { if (page.value < totalPages.value) page.value++; }
</script>

<style scoped>
.input-w-360 { width: 360px; }
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>