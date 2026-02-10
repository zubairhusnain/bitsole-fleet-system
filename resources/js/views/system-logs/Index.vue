<template>
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>System Activity Logs</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><router-link to="/">Home</router-link></li>
          <li class="breadcrumb-item active">System Logs</li>
        </ol>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Activity Filter</h3>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3">
            <label class="form-label">Module</label>
            <input type="text" class="form-control" v-model="filters.module" placeholder="e.g. Vehicles">
          </div>
          <div class="col-md-3">
            <label class="form-label">Action</label>
            <select class="form-select" v-model="filters.action">
              <option value="">All</option>
              <option value="CREATE">Create</option>
              <option value="UPDATE">Update</option>
              <option value="DELETE">Delete</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">User ID</label>
            <input type="number" class="form-control" v-model="filters.user_id" placeholder="User ID">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100" @click="fetchLogs(1)">
              <i class="bi bi-search me-1"></i> Filter
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
          <thead>
            <tr>
              <th>ID</th>
              <th>User</th>
              <th>Role</th>
              <th>Action</th>
              <th>Module</th>
              <th>Description</th>
              <th>Date</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="8" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
              </td>
            </tr>
            <tr v-else-if="logs.data.length === 0">
              <td colspan="8" class="text-center py-4">No logs found.</td>
            </tr>
            <tr v-for="log in logs.data" :key="log.id">
              <td>{{ log.id }}</td>
              <td>
                <div>{{ log.user_name }}</div>
                <small class="text-muted">ID: {{ log.user_id }}</small>
              </td>
              <td>{{ getRoleLabel(log.user_role) }}</td>
              <td>
                <span :class="getActionBadge(log.action)">{{ log.action }}</span>
              </td>
              <td>{{ log.module }}</td>
              <td>{{ log.description }}</td>
              <td>{{ formatDate(log.created_at) }}</td>
              <td>
                <button class="btn btn-sm btn-outline-info" @click="viewDetails(log)">
                  <i class="bi bi-eye"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer clearfix" v-if="logs.last_page > 1">
        <ul class="pagination pagination-sm m-0 float-end">
          <li class="page-item" :class="{ disabled: logs.current_page === 1 }">
            <a class="page-link" href="#" @click.prevent="fetchLogs(logs.current_page - 1)">&laquo;</a>
          </li>
          <li class="page-item disabled">
            <span class="page-link">Page {{ logs.current_page }} of {{ logs.last_page }}</span>
          </li>
          <li class="page-item" :class="{ disabled: logs.current_page === logs.last_page }">
            <a class="page-link" href="#" @click.prevent="fetchLogs(logs.current_page + 1)">&raquo;</a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Log Details #{{ selectedLog?.id }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" v-if="selectedLog">
            <div class="row mb-3">
              <div class="col-md-6">
                <strong>User:</strong> {{ selectedLog.user_name }} ({{ selectedLog.user_id }})<br>
                <strong>Role:</strong> {{ getRoleLabel(selectedLog.user_role) }}<br>
                <strong>IP Address:</strong> {{ selectedLog.ip_address }}
              </div>
              <div class="col-md-6 text-end">
                <strong>Date:</strong> {{ formatDate(selectedLog.created_at) }}<br>
                <strong>Module:</strong> {{ selectedLog.module }}<br>
                <strong>Request Path:</strong> {{ selectedLog.request_path }}
              </div>
            </div>

            <div class="row">
              <div class="col-md-6">
                <h6>Old Data</h6>
                <pre class="bg-light p-2 border rounded" style="max-height: 300px; overflow: auto;">{{ formatJson(selectedLog.old_data) }}</pre>
              </div>
              <div class="col-md-6">
                <h6>New Data</h6>
                <pre class="bg-light p-2 border rounded" style="max-height: 300px; overflow: auto;">{{ formatJson(selectedLog.new_data) }}</pre>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';

const logs = ref({ data: [], current_page: 1, last_page: 1 });
const loading = ref(false);
const filters = ref({
  module: '',
  action: '',
  user_id: ''
});
const selectedLog = ref(null);
let detailsModal = null;

const fetchLogs = async (page = 1) => {
  loading.value = true;
  try {
    const params = {
      page,
      ...filters.value
    };
    const response = await axios.get('/web/system-logs', { params });
    logs.value = response.data;
  } catch (error) {
    console.error('Error fetching logs:', error);
  } finally {
    loading.value = false;
  }
};

const getRoleLabel = (role) => {
  switch (Number(role)) {
    case 0: return 'Fleet Viewer'; // Using User prompt context: "fleetwiver"
    case 1: return 'Fleet Manager';
    case 2: return 'Distributor';
    case 3: return 'Super Admin';
    default: return 'Unknown';
  }
};

const getActionBadge = (action) => {
  switch (action) {
    case 'CREATE': return 'badge bg-success';
    case 'UPDATE': return 'badge bg-warning text-dark';
    case 'DELETE': return 'badge bg-danger';
    default: return 'badge bg-secondary';
  }
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  return new Date(dateString).toLocaleString();
};

const formatJson = (data) => {
  if (!data) return 'None';
  return JSON.stringify(data, null, 2);
};

const viewDetails = (log) => {
  selectedLog.value = log;
  if (!detailsModal) {
    detailsModal = new Modal(document.getElementById('logDetailsModal'));
  }
  detailsModal.show();
};

onMounted(() => {
  fetchLogs();
});
</script>

<style scoped>
pre {
  font-size: 0.85rem;
}
</style>
