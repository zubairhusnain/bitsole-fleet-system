<template>
  <div class="settings-wrapper">
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Settings</li>
      </ol>
    </div>

    <ul class="nav nav-tabs mb-3">
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'alerts' }" @click="activeTab = 'alerts'">Global Alert Settings</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'models' }" @click="activeTab = 'models'">Device Model</button>
      </li>
      <li class="nav-item">
        <button class="nav-link" :class="{ active: activeTab === 'backup' }" @click="activeTab = 'backup'">System Backup</button>
      </li>
      <li class="nav-item" v-if="isAdmin">
        <button class="nav-link" :class="{ active: activeTab === 'developer' }" @click="activeTab = 'developer'">Developer</button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- Tab 1: Global Alert Settings -->
      <div v-if="activeTab === 'alerts'" class="tab-pane fade show active">
        <div class="card">
          <div class="card-header"><h6 class="mb-0">Alerts & Notifications Settings</h6></div>
          <div class="card-body p-3">
            <div v-if="loading" class="text-muted small mb-2">Loading notifications…</div>

            <div class="row mt-3 align-items-center">
              <div class="col-sm-6"><h6 class="mb-2">Alarms</h6></div>
              <div class="col-sm-6 text-end">
                <button class="btn btn-sm btn-outline-success me-2" :disabled="loading || saving" @click="enableAll(alarmType, true)">Enable All</button>
                <button class="btn btn-sm btn-outline-danger" :disabled="loading || saving" @click="enableAll(alarmType, false)">Disable All</button>
              </div>
              <div class="col-12"></div>
              <div class="col-12 col-sm-3" v-for="(item, idx) in alarmType" :key="item.id">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" :id="'statue_'+item.attributes.alarms" v-model.lazy="item.already_xist" @change="changeStatus(item)" :disabled="loading || saving" />
                  <label class="form-check-label" :for="'statue_'+item.attributes.alarms">{{ item.attributes.alarms }}</label>
                </div>
              </div>
            </div>

            <div class="row mt-4 align-items-center">
              <div class="col-sm-6"><h6 class="mb-2">Notifications</h6></div>
              <div class="col-sm-6 text-end">
                <button class="btn btn-sm btn-outline-success me-2" :disabled="loading || saving" @click="enableAll(notificationType, true)">Enable All</button>
                <button class="btn btn-sm btn-outline-danger" :disabled="loading || saving" @click="enableAll(notificationType, false)">Disable All</button>
              </div>
              <div class="col-12"></div>
              <template v-for="(item, idx) in notificationType" :key="item.id">
                <div class="col-12 col-sm-3" v-if="item && item.type!='alarm'">
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" :id="'statue_'+item.type" v-model.lazy="item.already_xist" @change="changeStatus(item)" :disabled="loading || saving" />
                    <label class="form-check-label" :for="'statue_'+item.type">{{ item.type }}</label>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: Device Model -->
      <div v-if="activeTab === 'models'" class="tab-pane fade show active">
        <div class="card">
          <div class="card-body">
            <VehicleModelsManager />
          </div>
        </div>
      </div>

      <!-- Tab 3: System Backup -->
      <div v-if="activeTab === 'backup'" class="tab-pane fade show active">
        <div class="card" v-if="isAdmin">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">System Backups</h6>
            <button class="btn btn-sm btn-primary" @click="fetchBackups" :disabled="backupLoading">
              <i class="bi bi-arrow-clockwise" :class="{'animate-spin': backupLoading}"></i> Refresh
            </button>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead> 
                <tbody>
                  <tr v-if="backupLoading && !backups.length">
                    <td colspan="4" class="text-center p-3">Loading backups...</td>
                  </tr>
                  <tr v-if="!backupLoading && !backups.length">
                    <td colspan="4" class="text-center p-3">No backups found</td>
                  </tr>
                  <tr v-for="backup in backups" :key="backup.path">
                    <td>{{ backup.name }}</td>
                    <td>{{ backup.size }}</td>
                    <td>{{ formatDate(backup.timestamp) }}</td>
                    <td class="text-end">
                      <button class="btn btn-sm btn-outline-primary me-2" @click="downloadBackup(backup.path)" title="Download">
                        <i class="bi bi-download"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger me-2" @click="deleteBackup(backup.path)" title="Delete">
                        <i class="bi bi-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div v-else class="alert alert-warning">
          Access restricted to Administrators.
        </div>
      </div>

      <!-- Tab 4: Developer Settings -->
      <div v-if="activeTab === 'developer'" class="tab-pane fade show active">
        <div class="card" v-if="isAdmin">
          <div class="card-header"><h6 class="mb-0">Developer Settings</h6></div>
          <div class="card-body">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="testingModeSwitch" :checked="isTestingMode" @change="toggleTestingMode">
              <label class="form-check-label" for="testingModeSwitch">Enable Testing Mode</label>
            </div>
            <p class="text-muted small mt-2">
              Enabling testing mode reveals additional debugging tools and buttons throughout the application (e.g., Settings button in Vehicles list).
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, inject } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { authState, getCurrentUser } from '../auth';
import VehicleModelsManager from '../components/VehicleModelsManager.vue';
import { formatDateTime } from '../utils/datetime';

const activeTab = ref('alerts');
const isTestingMode = inject('isTestingMode', ref(false));

const loading = ref(false);
const saving = ref(false);
const notificationChannel = ref({ web: true, mail: false, mobile: false });
const requestData = ref([]);
const notificationType = ref([]);
const alarmType = ref([]);
const error = ref('');

// Backup state
const backups = ref([]);
const backupLoading = ref(false);
const isAdmin = computed(() => authState.user && authState.user.role === 3);

function toggleTestingMode() {
  const newVal = !isTestingMode.value;
  isTestingMode.value = newVal;
  if (newVal) {
    localStorage.setItem('testingMode', '1');
    showToast('Testing Mode Enabled', 'success');
  } else {
    localStorage.removeItem('testingMode');
    showToast('Testing Mode Disabled', 'success');
  }
}

function formatDate(ts) {
  if (!ts) return '-';
  return formatDateTime(ts * 1000);
}

async function fetchBackups() {
  if (!isAdmin.value) return;
  backupLoading.value = true;
  try {
    const res = await axios.get('/web/backups');
    backups.value = res.data.backups || [];
  } catch (e) {
    console.error('Failed to fetch backups', e);
  } finally {
    backupLoading.value = false;
  }
}

function downloadBackup(path) {
  window.location.href = `/web/backups/download?path=${encodeURIComponent(path)}`;
}

async function deleteBackup(path) {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, delete it!'
  });

  if (result.isConfirmed) {
    try {
      await axios.delete('/web/backups/delete', { data: { path } });
      showToast('Backup deleted successfully');
      fetchBackups();
    } catch (e) {
      showToast('Failed to delete backup', 'error');
    }
  }
}

function applyChannels(item) {
  item.web = !!notificationChannel.value.web;
  item.mail = !!notificationChannel.value.mail;
  item.sms = !!notificationChannel.value.mobile;
}

async function allNotification() {
  error.value = '';
  loading.value = true;
  try {
    const res = await axios.get('/web/notifications');
    const notif = Array.isArray(res?.data?.notificationType) ? res.data.notificationType : [];
    const alarms = Array.isArray(res?.data?.alarmType) ? res.data.alarmType : [];

    // Fetch backups if admin
    if (isAdmin.value) {
      fetchBackups();
    }
    notificationType.value = notif;
    alarmType.value = alarms;
  } catch (e) {
    error.value = e?.response?.data?.message || e.message || 'Failed to update notifications';
    return false;
  } finally {
    saving.value = false;
    loading.value = false;
  }
}

async function creatingNotification(payload) {
  saving.value = true;
  try {
    const res = await axios.post('/web/notifications', payload);
    if (res.data && res.data.ok === false) {
      console.error('Notification creation errors:', res.data.errors);
      throw new Error('Failed to create/update notification on server');
    }
    await allNotification();
    return true;
  } catch (e) {
    error.value = e?.response?.data?.message || e.message || 'Failed to update notifications';
    return false;
  } finally {
    saving.value = false;
  }
}

function changeChannel() {
  if (requestData.value.length) {
    for (let i = 0; i < requestData.value.length; i++) {
      applyChannels(requestData.value[i]);
    }
  }
}

async function changeStatus(item) {
  if (saving.value) return;
  requestData.value = [];
  applyChannels(item);
  // Send single item in array
  const payload = [{
    ...item,
    web: !!item.web,
    mail: !!item.mail,
    sms: !!item.sms,
    already_xist: !!item.already_xist
  }];

  const ok = await creatingNotification(payload);
  const label = itemLabel(item);
  const enabled = !!item.already_xist;
  if (ok) showToast(`${enabled ? 'Enabled' : 'Disabled'} ${label}`, 'success');
  else showToast(`Failed to update ${label}`, 'error');
}

async function enableAll(list, enabled) {
  if (saving.value) return;

  // Filter visible items only (match the UI filtering)
  const rawList = Array.isArray(list) ? list : [];
  // If list is notificationType, we must exclude alarms (since they have their own section)
  // If list is alarmType, they are all alarms.

  let itemsToProcess = rawList;
  if (list === notificationType.value) {
    itemsToProcess = rawList.filter(item => item && item.type !== 'alarm');
  }

  const payload = itemsToProcess.map(item => ({
    ...item,
    web: !!item.web,
    mail: !!item.mail,
    sms: !!item.sms,
    already_xist: enabled
  }));

  const ok = await creatingNotification(payload);
  if (ok) showToast(`${enabled ? 'Enabled' : 'Disabled'} All`, 'success');
  else showToast('Failed to update all', 'error');
}

function itemLabel(item) { return item?.type === 'alarm' ? (item?.attributes?.alarms || 'Alarm') : (item?.type || 'Notification'); }
function showToast(title, icon = 'success') { Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true, icon, title }); }

onMounted(allNotification);
</script>

<style scoped>
.settings-wrapper { padding: 16px; }
.form-check { text-align: left; }
.form-check-input { cursor: pointer; }
.form-check-label { cursor: pointer; }
.nav-tabs .nav-link { cursor: pointer; }
</style>
