<template>
  <div class="settings-wrapper">
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Settings</li>
      </ol>
    </div>

    <div class="card">
      <div class="card-header"><h6 class="mb-0">Alerts & Notifications Settings</h6></div>
      <div class="card-body p-3">
        <div v-if="loading" class="text-muted small mb-2">Loading notifications…</div>

        <div class="row g-3">
          <div class="col-12 col-sm-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="statue_web" v-model.lazy="notificationChannel.web" @change="changeChannel" :disabled="loading || saving" />
              <label class="form-check-label" for="statue_web">Web Alert</label>
            </div>
          </div>
          <div class="col-12 col-sm-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="statue_mail" v-model.lazy="notificationChannel.mail" @change="changeChannel" :disabled="loading || saving" />
              <label class="form-check-label" for="statue_mail">E-Mail Alert</label>
            </div>
          </div>
          <div class="col-12 col-sm-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="statue_mobile" v-model.lazy="notificationChannel.mobile" @change="changeChannel" :disabled="loading || saving" />
              <label class="form-check-label" for="statue_mobile">Mobile Alert</label>
            </div>
          </div>
        </div>

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

</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const loading = ref(false);
const saving = ref(false);
const notificationChannel = ref({ web: true, mail: true, mobile: true });
const requestData = ref([]);
const notificationType = ref([]);
const alarmType = ref([]);
const error = ref('');

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
    notificationType.value = notif;
    alarmType.value = alarms;
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load notifications';
  } finally {
    loading.value = false;
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
  // We can heuristically filter: if we are disabling 'notifications', exclude 'alarm' type.
  // But 'list' is just an array. We can check if it's the notificationType array.

  let itemsToProcess = rawList;
  if (list === notificationType.value) {
    itemsToProcess = rawList.filter(item => item && item.type !== 'alarm');
  } else if (list === alarmType.value) {
    itemsToProcess = rawList; // all alarms
  }

  if (itemsToProcess.length === 0) return;

  // Optimistic Update: Update UI immediately
  itemsToProcess.forEach(item => {
    item.already_xist = !!enabled;
    // Update channels based on global setting if enabling, or keep if disabling?
    // Actually applyChannels updates local properties 'web', 'mail', 'sms' based on global toggles.
    // If disabling, it doesn't matter much what channels are set, but let's be consistent.
    item.web = !!notificationChannel.value.web;
    item.mail = !!notificationChannel.value.mail;
    item.sms = !!notificationChannel.value.mobile;
  });

  // Prepare payload
  const payload = itemsToProcess.map(orig => {
    return {
      ...orig,
      already_xist: !!enabled,
      web: !!orig.web,
      mail: !!orig.mail,
      sms: !!orig.sms
    };
  });

  const ok = await creatingNotification(payload);

  if (ok) {
    showToast(`${enabled ? 'Enabled' : 'Disabled'} all`, 'success');
  } else {
    // Revert on failure (or reload)
    showToast('Failed to update all', 'error');
    await allNotification(); // Reload to get true state
  }
}

async function creatingNotification(payload) {
  error.value = '';
  saving.value = true;
  try {
    // Use provided payload or fallback to requestData for legacy/other calls
    const data = payload || requestData.value;
    const res = await axios.post('/web/notifications', data);
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

function itemLabel(item) { return item?.type === 'alarm' ? (item?.attributes?.alarms || 'Alarm') : (item?.type || 'Notification'); }
function showToast(title, icon = 'success') { Swal.fire({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true, icon, title }); }

onMounted(allNotification);
</script>

<style scoped>
.settings-wrapper { padding: 16px; }
.form-check { text-align: left; }
.form-check-input { cursor: pointer; }
.form-check-label { cursor: pointer; }
</style>
