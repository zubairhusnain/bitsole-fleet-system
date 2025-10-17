<template>
  <div class="drivers-view">
    <!-- Breadcrumb using AdminLTE/Bootstrap -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Dashboard</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Driver Management</li>
      </ol>
    </div>

    <!-- Page Title and Actions -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="mb-0 fw-semibold">Drivers Directory</h4>
      <div class="d-flex align-items-center gap-1">
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input v-model="query" type="text" class="form-control input-w-360" placeholder="Search driver/ID" />
        </div>
        <button class="btn btn-outline-secondary btn-icon-44" title="Filters"><i class="bi bi-sliders2"></i></button>
        <RouterLink to="/drivers/new" class="btn btn-app-dark"><i class="bi bi-plus-lg me-1"></i> New Driver</RouterLink>
      </div>
    </div>

    <!-- Table -->
    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
            <thead class="thead-app-dark">
              <tr>
                <th class="fw-semibold py-2">Driver ID</th>
                <th class="fw-semibold py-2">Driver Name</th>
                <th class="fw-semibold py-2">Email Address</th>
                <th class="fw-semibold py-2">Phone Number</th>
                <th class="fw-semibold py-2">Licence Number</th>
                <th class="fw-semibold py-2">License Expiry</th>
                <th class="fw-semibold py-2">Assigned Vehicles</th>
                <th class="fw-semibold py-2">Last Ride</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in pagedRows" :key="row.id" class="border-bottom">
                <td class="text-muted text-nowrap">{{ row.code }}</td>
                <td class="text-nowrap">{{ row.name }}</td>
                <td class="text-muted text-nowrap">{{ row.email }}</td>
                <td class="text-muted text-nowrap">{{ row.phone }}</td>
                <td class="text-muted text-nowrap">{{ row.licence }}</td>
                <td class="text-muted text-nowrap">{{ row.expiry }}</td>
                <td class="text-nowrap">
                  <span class="me-2">{{ row.vehicle }}</span>
                  <span class="badge bg-success rounded-pill badge-app">{{ row.status }}</span>
                </td>
                <td class="text-muted text-nowrap">{{ row.lastRide }}</td>
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

// Sample rows to match the screenshot structure (can be wired to API later)
const rows = ref([
  { id: 1, code: 'DRV-1001', name: 'Oliver Thompson', email: 'oliverthompson@gmail.com', phone: '(727) 540-0492', licence: 'D248-1982-6794', expiry: 'July 15, 2035', vehicle: 'Phantom Racer 9 Pro', status: 'Active', statusClass: 'text-bg-success', lastRide: 'July 15, 2025' },
  { id: 2, code: 'DRV-1002', name: 'Emma Johnson', email: 'emmajohnson@email.com', phone: '(415) 287-3849', licence: 'F732-6118-2945', expiry: 'Aug 22, 2029', vehicle: 'Racer X 2020', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Aug 22, 2025' },
  { id: 3, code: 'DRV-1003', name: 'Liam Smith', email: 'liamsmith@yahoo.com', phone: '(510) 200-5678', licence: 'G384-9917-8842', expiry: 'Sep 10, 2031', vehicle: 'TurboMax Z', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Sep 10, 2025' },
  { id: 4, code: 'DRV-1004', name: 'Sophia Brown', email: 'sophiabrown@gmail.com', phone: '(212) 777-9101', licence: 'K185-4273-6021', expiry: 'Oct 5, 2032', vehicle: 'Speedster 3000', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Oct 5, 2025' },
  { id: 5, code: 'DRV-1005', name: 'Mason Davis', email: 'masondavis@email.com', phone: '(303) 555-1234', licence: 'M569-8431-1186', expiry: 'Nov 30, 2034', vehicle: 'Velocity Racer', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Nov 30, 2025' },
  { id: 6, code: 'DRV-1006', name: 'Isabella Wilson', email: 'isabellawilson@gmail.com', phone: '(602) 112-3456', licence: 'R912-7032-5714', expiry: 'Dec 15, 2035', vehicle: 'Extreme Speedster', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Dec 15, 2025' },
  { id: 7, code: 'DRV-1007', name: 'Elijah Martinez', email: 'elijahmartinez@yahoo.com', phone: '(503) 888-9999', licence: 'S334-6689-0078', expiry: 'Jan 22, 2028', vehicle: 'Nitro Falcon', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Jan 22, 2026' },
  { id: 8, code: 'DRV-1008', name: 'Ava Garcia', email: 'avagarcia@gmail.com', phone: '(404) 222-3333', licence: 'T498-2205-7340', expiry: 'Feb 16, 2026', vehicle: 'Thunderbolt X', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Feb 16, 2026' },
  { id: 9, code: 'DRV-1009', name: 'James Rodriguez', email: 'jamesrodriguez@email.com', phone: '(312) 654-9876', licence: 'W125-9873-1402', expiry: 'Mar 19, 2026', vehicle: 'Lightning Racer', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Mar 19, 2026' },
  { id: 10, code: 'DRV-1010', name: 'Mia Hernandez', email: 'miahernandez@yahoo.com', phone: '(416) 987-6543', licence: 'Y863-4451-3909', expiry: 'Apr 12, 2026', vehicle: 'Flash 6000', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Apr 12, 2026' },
  { id: 11, code: 'DRV-1011', name: 'Benjamin Lee', email: 'benjaminlee@gmail.com', phone: '(702) 333-4444', licence: 'D248-1982-6794', expiry: 'May 25, 2032', vehicle: 'Rapid Racer V2', status: 'Active', statusClass: 'text-bg-success', lastRide: 'May 25, 2026' },
  { id: 12, code: 'DRV-1012', name: 'Charlotte Young', email: 'charlotteyoung@email.com', phone: '(818) 555-1111', licence: 'F732-6118-2945', expiry: 'Jun 27, 2030', vehicle: 'Supernova R', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Jun 27, 2026' },
  { id: 13, code: 'DRV-1013', name: 'Logan King', email: 'loganking@yahoo.com', phone: '(305) 444-2222', licence: 'G384-9917-8842', expiry: 'Jul 3, 2030', vehicle: 'Gravity Racer', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Jul 3, 2026' },
  { id: 14, code: 'DRV-1014', name: 'Avery Scott', email: 'averyscott@gmail.com', phone: '(407) 777-5555', licence: 'K185-4273-6021', expiry: 'Aug 18, 2028', vehicle: 'Stellar Racer X1', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Aug 18, 2026' },
  { id: 15, code: 'DRV-1015', name: 'Emma Johnson', email: 'emmajohnson@email.com', phone: '(415) 287-3849', licence: 'M569-8431-1186', expiry: 'Aug 22, 2029', vehicle: 'Racer X 2020', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Aug 22, 2026' },
  { id: 16, code: 'DRV-1016', name: 'Mason Davis', email: 'masondavis@email.com', phone: '(303) 555-1234', licence: 'R912-7032-5714', expiry: 'Nov 30, 2025', vehicle: 'Velocity Racer', status: 'Active', statusClass: 'text-bg-success', lastRide: 'Nov 30, 2025' },
]);

const filtered = computed(() => {
  if (!query.value) return rows.value;
  const q = query.value.toLowerCase();
  return rows.value.filter(r =>
    r.code.toLowerCase().includes(q) ||
    r.name.toLowerCase().includes(q) ||
    r.email.toLowerCase().includes(q)
  );
});

const totalPages = computed(() => Math.ceil(filtered.value.length / pageSize));
const startIndex = computed(() => (page.value - 1) * pageSize);
const pagedRows = computed(() => filtered.value.slice(startIndex.value, startIndex.value + pageSize));

function goPage(n) { page.value = n; }
function prevPage() { if (page.value > 1) page.value--; }
function nextPage() { if (page.value < totalPages.value) page.value++; }
</script>
