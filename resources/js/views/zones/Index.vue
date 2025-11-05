<template>
  <div class="zones-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
        </li>
        <li class="breadcrumb-item active" aria-current="page">Zone Management</li>
      </ol>
    </div>

    <!-- Page Title and Actions -->
    <div class="row mb-3">
      <div class="col-sm-12 col-md-12 col-xl-8">
        <h4 class="mb-0 fw-semibold">Zone Management</h4>
      </div>
      <div class="col-sm-12 col-md-12 col-xl-4 d-flex justify-content-xl-end">
        <RouterLink to="/zones/new" class="btn btn-app-dark"><i class="bi bi-plus-lg me-1"></i> Add New Zone</RouterLink>
      </div>
    </div>

    <!-- Status Messages -->
    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <!-- Summary Cards -->
    <div class="row g-3 mb-3">
      <div class="col-sm-12 col-md-6 col-lg-3" v-for="card in summaryCards" :key="card.title">
        <div class="card border rounded-4 shadow-0 h-100">
          <div class="card-body d-flex align-items-center gap-3 py-3">
            <div class="rounded-3 p-2 bg-light text-primary d-inline-flex align-items-center justify-content-center">
              <i class="bi bi-diagram-3 fs-5"></i>
            </div>
            <div>
              <div class="small text-muted">{{ card.title }}</div>
              <div class="fw-semibold">{{ formatNumber(card.value) }} {{ card.suffix }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search Option -->
    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-body">
        <div class="fw-semibold mb-2">Search Option</div>
        <div class="row g-2 align-items-end">
          <div class="col-sm-12 col-md-6 col-lg-4">
            <label class="form-label small">Zone Name</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-search"></i></span>
              <input type="text" class="form-control" placeholder="Enter Zone Name" v-model="searchName" />
            </div>
          </div>
          <div class="col-sm-12 col-md-6 col-lg-4">
            <label class="form-label small">Status</label>
            <select class="form-select">
              <option value="">-- Select Status --</option>
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
          <div class="col-sm-12 col-md-6 col-lg-4">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" id="showBlocked" v-model="showBlocked">
              <label class="form-check-label small" for="showBlocked">Include Blocked Zones</label>
            </div>
          </div>
          <div class="col-sm-12 col-md-6 col-lg-4">
            <button class="btn btn-primary mt-3 mt-md-0" @click="fetchZones">Submit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card border rounded-3 shadow-0">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
            <thead class="thead-app-dark">
              <tr>
                <th class="fw-semibold py-2">Zone Name</th>
                <th class="fw-semibold py-2">Owner</th>
                <th class="fw-semibold py-2">Description</th>
                <th class="fw-semibold py-2">Created</th>
                <th class="fw-semibold py-2">Last Update</th>
                <th class="fw-semibold py-2">Status</th>
                <th class="fw-semibold py-2 text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in pagedRows" :key="row.id" class="border-bottom">
                <td class="text-muted text-nowrap">{{ row.zoneName }}</td>
                <td class="text-muted text-nowrap">{{ row.owner }}</td>
                <td class="text-muted text-nowrap">{{ row.description }}</td>
                <td class="text-muted text-nowrap">{{ row.created }}</td>
                <td class="text-muted text-nowrap">{{ row.lastUpdate }}</td>
                <td class="text-nowrap">
                  <span :class="['status-badge', statusClass(row.status)]">
                    <span class="dot"></span>
                    {{ row.status }}
                  </span>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" title="View" @click="goView(row.id, row.geofenceId)"><i class="bi bi-eye"></i></button>
                    <button class="btn btn-outline-secondary" title="Edit" @click="goEdit(row.id, row.geofenceId)" :disabled="row.deletedAt"><i class="bi bi-pencil"></i></button>
                    <button v-if="!row.deletedAt" class="btn btn-outline-warning" title="Block" @click="confirmBlock(row.id, row.geofenceId)"><i class="bi bi-slash-circle"></i></button>
                    <button v-if="row.deletedAt" class="btn btn-outline-success" title="Restore" @click="restoreZone(row.id, row.geofenceId)"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button class="btn btn-outline-danger" title="Delete" @click="confirmDelete(row.id, row.geofenceId)"><i class="bi bi-trash"></i></button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2">
        <div class="text-muted small me-auto">Showing {{ startIndex + 1 }} to {{ Math.min(startIndex + pageSize,
          totalCount) }} of {{ totalCount }} results</div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item" :class="{ disabled: page === 1 }"><button class="page-link"
                @click="prevPage">‹</button></li>
            <li class="page-item" v-for="n in totalPages" :key="n" :class="{ active: page === n }"><button
                class="page-link" @click="goPage(n)">{{ n }}</button></li>
            <li class="page-item" :class="{ disabled: page === totalPages }"><button class="page-link"
                @click="nextPage">›</button></li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

// Search input (static for now)
const searchName = ref('');

// Summary metrics (dynamic counts from listing)
const summaryCards = ref([
  { title: 'Total Geofencing', value: 0, suffix: 'Zones' },
  { title: 'Active Zone', value: 0, suffix: 'Zones' },
  { title: 'Inactive Zone', value: 0, suffix: 'Zones' },
  { title: 'Geo Fencing Violation', value: 0, suffix: 'Time' },
]);

function formatNumber(n) {
  return new Intl.NumberFormat('en-US').format(n);
}

// Table data and pagination
const page = ref(1);
const pageSize = ref(16);
const totalCount = ref(0);
const totalPages = ref(1);
const rows = ref([]);
const loading = ref(false);
const message = ref('');
const error = ref('');
const router = useRouter();

const startIndex = computed(() => (page.value - 1) * pageSize.value);
const pagedRows = computed(() => rows.value.slice(startIndex.value, startIndex.value + pageSize.value));

function goPage(n) {
  page.value = n;
  fetchZones();
}
function prevPage() { if (page.value > 1) { page.value -= 1; fetchZones(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; fetchZones(); } }

function statusClass(s) {
  return s === 'Active' ? 'is-on' : 'is-off';
}

function formatDateTime(iso) {
  if (!iso) return '-';
  try {
    const d = new Date(iso);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `${dd}/${mm}/${yyyy} - ${hh}:${min}`;
  } catch { return iso; }
}

function mapRow(z) {
  const owner = (z.user && z.user.name) ? z.user.name : (z.user_id ? `User #${z.user_id}` : '-');
  const status = z.deleted_at ? 'Blocked' : ((z.status || '').toLowerCase() === 'inactive' ? 'Inactive' : 'Active');
  return {
    id: z.id,
    geofenceId: z.geofence_id,
    zoneName: z.name || '-',
    owner,
    description: z.description || '-',
    created: formatDateTime(z.created_at),
    lastUpdate: formatDateTime(z.updated_at),
    status,
    deletedAt: z.deleted_at || null,
  };
}

async function fetchZones() {
  loading.value = true;
  error.value = '';
  try {
    // Backend uses page size 25; we page client-side at 16 to fit UI.
    const params = {};
    if (searchName.value) params.name = searchName.value;
    params.page = page.value;
    if (showBlocked.value) params.withDeleted = 1;
    const { data } = await axios.get('/web/zones', { params });
    const list = Array.isArray(data?.data) ? data.data : [];
    rows.value = list.map(mapRow);
    console.log('rows list ',rows);
    totalCount.value = Number(data?.total || list.length);
    const serverTotalPages = Number(data?.last_page || 1);
    // Mirror UI pagination count to show enough pages
    totalPages.value = Math.max(1, Math.ceil(totalCount.value / pageSize.value));
    // Summary cards
    summaryCards.value[0].value = totalCount.value;
    const activeCount = list.filter(z => !z.deleted_at && (z.status || '').toLowerCase() !== 'inactive').length;
    summaryCards.value[1].value = activeCount;
    summaryCards.value[2].value = totalCount.value - activeCount;
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load zones';
  } finally {
    loading.value = false;
  }
}

function goEdit(id, geofenceId) { router.push(`/zones/${geofenceId ?? id}/edit`); }
function goView(id, geofenceId) { router.push(`/zones/${geofenceId ?? id}/edit`); }
async function confirmDelete(id, geofenceId) {
  if (!confirm('Permanently delete this zone? This removes the remote geofence.')) return;
  error.value = '';
  message.value = '';
  try {
    const { data } = await axios.delete(`/web/zones/${geofenceId ?? id}`, { params: { force: 1 } });
    message.value = data?.message || 'Zone deleted';
    // Refresh current page
    fetchZones();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to delete zone';
  }
}

async function confirmBlock(id, geofenceId) {
  if (!confirm('Block this zone? It will be hidden but not removed from Traccar.')) return;
  error.value = '';
  message.value = '';
  try {
    const { data } = await axios.delete(`/web/zones/${geofenceId ?? id}`); // soft delete (block)
    message.value = data?.message || 'Zone blocked';
    fetchZones();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to block zone';
  }
}

async function restoreZone(id, geofenceId) {
  error.value = '';
  message.value = '';
  try {
    const { data } = await axios.patch(`/web/zones/${geofenceId ?? id}/restore`);
    message.value = data?.message || 'Zone restored';
    fetchZones();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to restore zone';
  }
}

onMounted(fetchZones);
</script>

<style scoped>
/* Reuse global app.css styles for tables, badges, and pagination */
</style>
const showBlocked = ref(false);