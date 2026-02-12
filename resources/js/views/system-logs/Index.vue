<template>
  <div class="system-logs-view">
    <!-- Breadcrumb -->
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item">
          <RouterLink to="/dashboard">Dashboard</RouterLink>
        </li>
        <li class="breadcrumb-item active" aria-current="page">System Logs</li>
      </ol>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <div v-if="loading" class="text-muted small mb-2">Loading logs…</div>

    <!-- Page Title and Filters -->
    <div class="row mb-3 align-items-center">
      <div class="col-sm-12 col-md-12 col-xl-3">
        <h4 class="mb-0 fw-semibold">System Activity Logs</h4>
      </div>
      <div class="col-sm-12 col-md-12 col-xl-9">
        <div class="row g-2 justify-content-xl-end">
          <div class="col-6 col-md-3 col-xl-2">
            <select v-model="filters.module" class="form-select form-select-sm" @change="fetchLogs(1)">
              <option value="">All Modules</option>
              <option v-for="mod in filterData.modules" :key="mod" :value="mod">{{ mod }}</option>
            </select>
          </div>
          <div class="col-6 col-md-3 col-xl-2">
            <select v-model="filters.action" class="form-select form-select-sm" @change="fetchLogs(1)">
              <option value="">All Actions</option>
              <option value="CREATE">Create</option>
              <option value="UPDATE">Update</option>
              <option value="DELETE">Delete</option>
            </select>
          </div>
          <div class="col-6 col-md-3 col-xl-2">
            <select v-model="filters.user_id" class="form-select form-select-sm" @change="fetchLogs(1)">
              <option value="">All Users</option>
              <option v-for="user in filterData.users" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
          </div>
          <div class="col-6 col-md-3 col-xl-2">
            <input v-model="filters.date_from" type="date" class="form-control form-control-sm" placeholder="From Date" @change="fetchLogs(1)" />
          </div>
          <div class="col-6 col-md-3 col-xl-2">
            <input v-model="filters.date_to" type="date" class="form-control form-control-sm" placeholder="To Date" @change="fetchLogs(1)" />
          </div>
          <div class="col-6 col-md-3 col-xl-1">
            <button class="btn btn-app-dark btn-sm w-100" @click="fetchLogs(1)">
              <i class="bi bi-funnel"></i>
            </button>
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
                <th class="fw-semibold py-2">ID</th>
                <th class="fw-semibold py-2">User</th>
                <th class="fw-semibold py-2">Role</th>
                <th class="fw-semibold py-2">Action</th>
                <th class="fw-semibold py-2">Module</th>
                <th class="fw-semibold py-2">Description</th>
                <th class="fw-semibold py-2">Date</th>
                <th class="fw-semibold py-2 text-end">Details</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="logs.data.length === 0 && !loading">
                <td colspan="8" class="text-center text-muted py-3">No logs found.</td>
              </tr>
              <tr v-for="log in logs.data" :key="log.id" class="border-bottom">
                <td class="text-muted text-nowrap">#{{ log.id }}</td>
                <td class="text-nowrap">
                  <div class="fw-medium">{{ log.user_name }}</div>
                  <small class="text-muted">ID: {{ log.user_id }}</small>
                </td>
                <td class="text-muted text-nowrap">
                  <span class="badge rounded-pill badge-app bg-secondary">{{ getRoleLabel(log.user_role) }}</span>
                </td>
                <td class="text-nowrap">
                  <span :class="getActionBadge(log.action)">{{ log.action }}</span>
                </td>
                <td class="text-muted text-nowrap">{{ log.module }}</td>
                <td class="text-wrap" style="min-width: 200px;">{{ log.description }}</td>
                <td class="text-muted text-nowrap">{{ formatDate(log.created_at) }}</td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-secondary" title="View Details" @click="viewDetails(log)">
                    <i class="bi bi-eye"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex align-items-center py-2" v-if="logs.total > 0">
        <div class="text-muted small me-auto">
          Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} results
        </div>
        <nav aria-label="Pagination" class="ms-auto">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item" :class="{ disabled: logs.current_page === 1 }">
              <button class="page-link" @click="fetchLogs(logs.current_page - 1)">‹</button>
            </li>
            <li class="page-item" v-for="n in logs.last_page" :key="n" :class="{ active: logs.current_page === n }">
              <button class="page-link" @click="fetchLogs(n)">{{ n }}</button>
            </li>
            <li class="page-item" :class="{ disabled: logs.current_page === logs.last_page }">
              <button class="page-link" @click="fetchLogs(logs.current_page + 1)">›</button>
            </li>
          </ul>
        </nav>
      </div>
    </div>

    <!-- Details Modal -->
    <div v-if="showDetailsModal" class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" style="background: rgba(0,0,0,0.5)">
      <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title">Log Details #{{ selectedLog?.id }}</h6>
            <button type="button" class="btn-close" @click="showDetailsModal = false"></button>
          </div>
          <div class="modal-body" v-if="selectedLog">
            <div class="row mb-4">
              <div class="col-md-6">
                <div class="mb-2"><strong>User:</strong> {{ selectedLog.user_name }} (ID: {{ selectedLog.user_id }})</div>
                <div class="mb-2"><strong>Role:</strong> {{ getRoleLabel(selectedLog.user_role) }}</div>
                <div><strong>IP Address:</strong> {{ selectedLog.ip_address }}</div>
              </div>
              <div class="col-md-6 text-md-end">
                <div class="mb-2"><strong>Date:</strong> {{ formatDate(selectedLog.created_at) }}</div>
                <div class="mb-2"><strong>Module:</strong> {{ selectedLog.module }}</div>
                <div><strong>Request:</strong> <code class="small">{{ selectedLog.request_path }}</code></div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <h6 class="fw-semibold mb-2">Old Data</h6>
                <pre class="bg-light p-3 border rounded small" style="max-height: 400px; overflow: auto;">{{ formatJson(selectedLog.old_data) }}</pre>
              </div>
              <div class="col-md-6">
                <h6 class="fw-semibold mb-2">New Data</h6>
                <pre class="bg-light p-3 border rounded small" style="max-height: 400px; overflow: auto;">{{ formatJson(selectedLog.new_data) }}</pre>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" @click="showDetailsModal = false">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const logs = ref({ data: [], current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
const loading = ref(false);
const error = ref('');
const filters = ref({
  module: '',
  action: '',
  user_id: '',
  date_from: '',
  date_to: ''
});
const filterData = ref({
  modules: [],
  users: []
});
const selectedLog = ref(null);
const showDetailsModal = ref(false);

const fetchFilterData = async () => {
  try {
    const response = await axios.get('/web/system-logs/filters-data');
    filterData.value = response.data;
  } catch (err) {
    console.error('Error fetching filter data:', err);
  }
};

const fetchLogs = async (page = 1) => {
  if (page < 1 || (logs.value.last_page > 0 && page > logs.value.last_page)) return;

  loading.value = true;
  error.value = '';
  try {
    const params = {
      page,
      ...filters.value
    };
    const response = await axios.get('/web/system-logs', { params });
    logs.value = response.data;
  } catch (err) {
    console.error('Error fetching logs:', err);
    error.value = 'Failed to load system activity logs.';
  } finally {
    loading.value = false;
  }
};

const getRoleLabel = (role) => {
  switch (Number(role)) {
    case 0: return 'Fleet Viewer';
    case 1: return 'Fleet Manager';
    case 2: return 'Distributor';
    case 3: return 'Super Admin';
    default: return 'Unknown';
  }
};

const getActionBadge = (action) => {
  switch (action) {
    case 'CREATE': return 'badge rounded-pill bg-success';
    case 'UPDATE': return 'badge rounded-pill bg-warning text-dark';
    case 'DELETE': return 'badge rounded-pill bg-danger';
    default: return 'badge rounded-pill bg-secondary';
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '—';
  return new Date(dateString).toLocaleString();
};

const formatJson = (data) => {
  if (!data) return 'No data';
  try {
    const obj = typeof data === 'string' ? JSON.parse(data) : data;
    return JSON.stringify(obj, null, 2);
  } catch (e) {
    return data;
  }
};

const viewDetails = (log) => {
  selectedLog.value = log;
  showDetailsModal.value = true;
};

onMounted(() => {
  fetchFilterData();
  fetchLogs();
});
</script>
