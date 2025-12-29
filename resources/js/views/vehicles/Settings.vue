<template>
  <div class="content-wrapper">
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
        <li class="breadcrumb-item"><RouterLink to="/vehicles">Vehicle Management</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Vehicle Settings</li>
      </ol>
    </div>

    <div class="row mb-3">
      <div class="col-sm-12"><h4 class="mb-0 fw-semibold">Vehicle Settings</h4></div>
    </div>

    <UiAlert :show="!!error" :message="error" variant="danger" dismissible @dismiss="error = ''" />
    <UiAlert :show="!!message" :message="message" variant="success" dismissible @dismiss="message = ''" />

    <!-- Drivers & Zones assignment (two columns) -->
    <div class="row">
      <div class="col-12 col-lg-6">
        <div class="card mb-3">
          <div class="card-header"><h6 class="mb-0">Assign Drivers</h6></div>
          <div class="card-body">
            <div v-if="loadingDrivers" class="text-muted small mb-2">Loading drivers…</div>
            <div class="row g-3 align-items-end">
              <div class="col-12">
                <label class="form-label small">Select Drivers</label>
                <div class="multi-input form-control form-control-sm" :class="{ disabled: loadingDrivers || submitting }" @click="driversOpen = !driversOpen">
                  <span v-for="id in selectedDriverIds" :key="'chip-d-'+id" class="chip">
                    {{ labelForDriver(id) }}
                    <button type="button" class="chip-remove" @click.stop="removeDriver(id)" aria-label="Remove">×</button>
                  </span>
                  <i class="bi bi-caret-down-fill ms-auto small"></i>
                </div>
                <div class="dropdown-list" v-show="driversOpen">
                  <div v-for="d in driverOptionsNormalized" :key="d.id" class="dropdown-item"
                       :class="{ selected: selectedDriverIds.includes(d.id) }"
                       @click="toggleDriver(d.id)">
                    {{ d.label }}
                  </div>
                </div>
                <div class="text-muted small mt-1">Assigned: {{ assignedDriverIds.length }}</div>
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn btn-sm btn-primary" :disabled="loadingDrivers || submitting" @click="saveDrivers">Save Changes</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-6">
        <div class="card mb-3">
          <div class="card-header"><h6 class="mb-0">Assign Zones</h6></div>
          <div class="card-body">
            <div v-if="loadingZones" class="text-muted small mb-2">Loading zones…</div>
            <div class="row g-3 align-items-end">
              <div class="col-12">
                <label class="form-label small">Select Zones</label>
                <div class="multi-input form-control form-control-sm" :class="{ disabled: loadingZones || submitting }" @click="zonesOpen = !zonesOpen">
                  <span v-for="id in selectedZoneIds" :key="'chip-z-'+id" class="chip">
                    {{ labelForZone(id) }}
                    <button type="button" class="chip-remove" @click.stop="removeZone(id)" aria-label="Remove">×</button>
                  </span>
                  <i class="bi bi-caret-down-fill ms-auto small"></i>
                </div>
                <div class="dropdown-list" v-show="zonesOpen">
                  <div v-for="z in zoneOptions" :key="z.id" class="dropdown-item"
                       :class="{ selected: selectedZoneIds.includes(z.id) }"
                       @click="toggleZone(z.id)">
                    {{ z.label }}
                  </div>
                </div>
                <div class="text-muted small mt-1">Assigned: {{ assignedZoneIds.length }}</div>
              </div>
              <div class="col-12 d-flex gap-2">
                <button class="btn btn-sm btn-primary" :disabled="loadingZones || submitting" @click="saveZones">Save Changes</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Notifications -->
    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">Notification Preferences</h6></div>
      <div class="card-body">
        <div v-if="loadingNotifications" class="text-muted small mb-2">Loading notifications…</div>
        <div v-else-if="noAdminNotifications" class="text-muted small mb-2">No notifications are enabled in admin settings. Enable notifications in Admin Settings to manage device preferences.</div>
        <div v-else>
        <!-- Notification Channels Hidden
        <div class="row g-3">
          <div class="col-12 col-sm-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="device_web" v-model.lazy="notificationChannel.web" :disabled="loadingNotifications || savingNotifications" />
              <label class="form-check-label" for="device_web">Web Alert</label>
            </div>
          </div>
          <div class="col-12 col-sm-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="device_mail" v-model.lazy="notificationChannel.mail" :disabled="loadingNotifications || savingNotifications" />
              <label class="form-check-label" for="device_mail">E-Mail Alert</label>
            </div>
          </div>
          <div class="col-12 col-sm-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="device_mobile" v-model.lazy="notificationChannel.mobile" :disabled="loadingNotifications || savingNotifications" />
              <label class="form-check-label" for="device_mobile">Mobile Alert</label>
            </div>
          </div>
        </div>
        -->

        <div class="row mt-3 align-items-center">
          <div class="col-sm-6"><h6 class="mb-2">Alarms</h6></div>
          <div class="col-sm-6 text-end">
            <button class="btn btn-sm btn-outline-success me-2" :disabled="loadingNotifications || savingNotifications" @click="enableAll(alarmType, true)">Enable All</button>
            <button class="btn btn-sm btn-outline-danger" :disabled="loadingNotifications || savingNotifications" @click="enableAll(alarmType, false)">Disable All</button>
          </div>
          <div class="col-12"></div>
          <div class="col-12" v-if="!loadingNotifications && alarmType.length === 0">
            <div class="text-muted small">No alarms available. Create and enable alarms in Admin Settings.</div>
          </div>
          <div class="col-12 col-sm-3" v-for="item in alarmType" :key="'alm-'+item.id">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" :id="'alm_'+(item.attributes?.alarms || item.id)" v-model.lazy="item.already_xist" @change="changeStatus(applyChannels(item))" :disabled="loadingNotifications || savingNotifications" />
              <label class="form-check-label" :for="'alm_'+(item.attributes?.alarms || item.id)">{{ item.attributes?.alarms }}</label>
            </div>
          </div>
        </div>

        <div class="row mt-4 align-items-center">
          <div class="col-sm-6"><h6 class="mb-2">Notifications</h6></div>
          <div class="col-sm-6 text-end">
            <button class="btn btn-sm btn-outline-success me-2" :disabled="loadingNotifications || savingNotifications" @click="enableAll(notificationType, true)">Enable All</button>
            <button class="btn btn-sm btn-outline-danger" :disabled="loadingNotifications || savingNotifications" @click="enableAll(notificationType, false)">Disable All</button>
          </div>
          <div class="col-12"></div>
          <div class="col-12" v-if="!loadingNotifications && notificationType.length === 0">
            <div class="text-muted small">No standard notifications available. Create and enable notifications in Admin Settings.</div>
          </div>
          <template v-for="item in notificationType" :key="'std-'+item.id">
            <div class="col-12 col-sm-3" v-if="item && item.type!='alarm'">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" :id="'std_'+item.type" v-model.lazy="item.already_xist" @change="changeStatus(applyChannels(item))" :disabled="loadingNotifications || savingNotifications" />
                <label class="form-check-label" :for="'std_'+item.type">{{ item.type }}</label>
              </div>
            </div>
          </template>
        </div>
        </div>
      </div>
    </div>
  </div>

</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import UiAlert from '../../components/UiAlert.vue';

const route = useRoute();
const deviceId = computed(() => parseInt(route.params.deviceId));

const message = ref('');
const error = ref('');
const submitting = ref(false);
const loadingDrivers = ref(false);
const loadingZones = ref(false);
const loadingNotifications = ref(false);
const savingNotifications = ref(false);
const notificationChannel = ref({ web: true, mail: false, mobile: false });

const driverOptions = ref([]);
const zoneOptions = ref([]);
const assignedDriverIds = ref([]);
const assignedZoneIds = ref([]);
const selectedDriverIds = ref([]);
const selectedZoneIds = ref([]);
const driversOpen = ref(false);
const zonesOpen = ref(false);

const notificationType = ref([]);
const alarmType = ref([]);
const noAdminNotifications = computed(() => {
  const aLen = Array.isArray(alarmType.value) ? alarmType.value.length : 0;
  const nLen = Array.isArray(notificationType.value) ? notificationType.value.length : 0;
  return (aLen + nLen) === 0;
});
function applyChannels(item) {
  item.web = !!notificationChannel.value.web;
  item.mail = !!notificationChannel.value.mail;
  item.sms = !!notificationChannel.value.mobile;
  return item;
}

function dismissAlerts() { message.value = ''; error.value = ''; }

async function loadDrivers() {
  try {
    const res = await axios.get('/web/vehicles/drivers/options');
    const opts = Array.isArray(res?.data?.options) ? res.data.options : [];
    driverOptions.value = opts;
  } catch (e) {
    error.value = 'Failed to load drivers';
  }
}

async function loadAssignedDrivers() {
  try {
    const res = await axios.get(`/web/vehicles/${deviceId.value}/drivers`);
    const list = Array.isArray(res?.data?.drivers) ? res.data.drivers : [];
    const ids = list.map(d => Number(d.id || 0)).filter(id => id > 0);
    assignedDriverIds.value = ids;
    selectedDriverIds.value = [...ids];
  } catch {}
}

async function initDrivers() {
  loadingDrivers.value = true;
  try { await Promise.all([loadDrivers(), loadAssignedDrivers()]); } finally { loadingDrivers.value = false; }
}

async function loadZones() {
  try {
    const res = await axios.get('/web/vehicles/geofences/options?pageSize=500');
    const list = Array.isArray(res?.data?.data) ? res.data.data : [];
    zoneOptions.value = list.map(g => ({ id: Number(g.id), label: g.name || ('Zone #' + g.id) })).filter(z => z.id > 0);
  } catch (e) {
    error.value = 'Failed to load zones';
  }
}

async function loadAssignedZones() {
  try {
    const res = await axios.get(`/web/vehicles/${deviceId.value}/geofences`);
    const list = Array.isArray(res?.data?.geofences) ? res.data.geofences : [];
    const ids = list.map(g => Number(g.id || 0)).filter(id => id > 0);
    assignedZoneIds.value = ids;
    selectedZoneIds.value = [...ids];
  } catch {}
}

async function initZones() {
  loadingZones.value = true;
  try { await Promise.all([loadZones(), loadAssignedZones()]); } finally { loadingZones.value = false; }
}

async function loadNotifications() {
  try {
    const res = await axios.get(`/web/vehicles/${deviceId.value}/notifications`);
    notificationType.value = Array.isArray(res?.data?.notificationType) ? res.data.notificationType : [];
    alarmType.value = Array.isArray(res?.data?.alarmType) ? res.data.alarmType : [];
  } catch (e) {
    error.value = 'Failed to load notifications';
  }
}

async function initNotifications() {
  loadingNotifications.value = true;
  try { await loadNotifications(); } finally { loadingNotifications.value = false; }
}

function normalizeOption(o) {
  const idRaw = o?.id;
  const id = Number(idRaw || 0);
  const labelRaw = o?.label ?? o?.name ?? o?.text ?? o?.title ?? (id ? ('#' + id) : '');
  const label = typeof labelRaw === 'string' ? labelRaw : String(labelRaw || '');
  return { id, label: label.trim() };
}

const driverOptionsNormalized = computed(() => {
  const list = Array.isArray(driverOptions.value) ? driverOptions.value : [];
  return list.map(normalizeOption).filter(o => o.id > 0 && o.label);
});

function labelForDriver(id) {
  const opt = driverOptionsNormalized.value.find(o => o.id === Number(id));
  return opt?.label || ('#' + id);
}
function labelForZone(id) {
  const opt = zoneOptions.value.find(o => o.id === Number(id));
  return opt?.label || ('#' + id);
}

function toggleDriver(id) {
  const n = Number(id);
  const set = new Set(selectedDriverIds.value.map(Number));
  if (set.has(n)) set.delete(n); else set.add(n);
  selectedDriverIds.value = Array.from(set);
}
function removeDriver(id) {
  const n = Number(id);
  selectedDriverIds.value = selectedDriverIds.value.filter(v => Number(v) !== n);
}

function toggleZone(id) {
  const n = Number(id);
  const set = new Set(selectedZoneIds.value.map(Number));
  if (set.has(n)) set.delete(n); else set.add(n);
  selectedZoneIds.value = Array.from(set);
}
function removeZone(id) {
  const n = Number(id);
  selectedZoneIds.value = selectedZoneIds.value.filter(v => Number(v) !== n);
}

async function saveDrivers() {
  dismissAlerts();
  submitting.value = true;
  try {
    const current = new Set(selectedDriverIds.value.map(Number));
    const original = new Set(assignedDriverIds.value.map(Number));

    const toAdd = [...current].filter(id => !original.has(id));
    const toRemove = [...original].filter(id => !current.has(id));

    if (toAdd.length === 0 && toRemove.length === 0) {
      message.value = 'No changes to save';
      return;
    }

    const promises = [];
    if (toAdd.length > 0) promises.push(axios.post(`/web/vehicles/${deviceId.value}/drivers/assign`, { driverIds: toAdd }));
    if (toRemove.length > 0) promises.push(axios.post(`/web/vehicles/${deviceId.value}/drivers/unassign`, { driverIds: toRemove }));

    await Promise.all(promises);
    message.value = 'Drivers updated successfully';
    await loadAssignedDrivers();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to update drivers';
  } finally { submitting.value = false; }
}

async function saveZones() {
  dismissAlerts();
  submitting.value = true;
  try {
    const current = new Set(selectedZoneIds.value.map(Number));
    const original = new Set(assignedZoneIds.value.map(Number));

    const toAdd = [...current].filter(id => !original.has(id));
    const toRemove = [...original].filter(id => !current.has(id));

    if (toAdd.length === 0 && toRemove.length === 0) {
      message.value = 'No changes to save';
      return;
    }

    const promises = [];
    if (toAdd.length > 0) promises.push(axios.post(`/web/vehicles/${deviceId.value}/zones/assign`, { geofenceIds: toAdd }));
    if (toRemove.length > 0) promises.push(axios.post(`/web/vehicles/${deviceId.value}/zones/unassign`, { geofenceIds: toRemove }));

    await Promise.all(promises);
    message.value = 'Zones updated successfully';
    await loadAssignedZones();
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to update zones';
  } finally { submitting.value = false; }
}

async function changeStatus(item) {
  if (savingNotifications.value) return;
  error.value = '';
  savingNotifications.value = true;
  try {
    const payload = { notificationId: Number(item.id), already_xist: !!item.already_xist, web: !!item.web, mail: !!item.mail, sms: !!item.sms };
    await axios.post(`/web/vehicles/${deviceId.value}/notifications/assign`, payload);
    message.value = 'Notification updated';
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to update notification';
  } finally {
    savingNotifications.value = false;
  }
}

async function enableAll(list, enabled) {
  if (savingNotifications.value) return;
  error.value = '';
  savingNotifications.value = true;
  try {
    const rawList = Array.isArray(list) ? list : [];
    const items = rawList.map(orig => {
      // Prepare item with new status (but don't mutate orig yet)
      const item = applyChannels({ ...orig, already_xist: !!enabled });
      return {
        notificationId: Number(item.id),
        already_xist: !!item.already_xist,
        web: !!item.web,
        mail: !!item.mail,
        sms: !!item.sms
      };
    });

    // Send bulk request
    await axios.post(`/web/vehicles/${deviceId.value}/notifications/assign`, { items });

    // Update local state on success
    rawList.forEach(orig => {
      orig.already_xist = !!enabled;
      // Also update channels to match global setting since we applied it in payload
      orig.web = !!notificationChannel.value.web;
      orig.mail = !!notificationChannel.value.mail;
      orig.sms = !!notificationChannel.value.mobile;
    });

    message.value = enabled ? 'Enabled all' : 'Disabled all';
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to update';
  } finally {
    savingNotifications.value = false;
  }
}

onMounted(async () => {
  await Promise.all([
    initDrivers(),
    initZones(),
    initNotifications(),
  ]);
});
</script>

<style scoped>
.gap-2 { gap: .5rem; }
.list-group-item { padding: 8px 12px; }
.form-check-input { cursor: pointer; }
.form-check-label { cursor: pointer; }
.multi-input { display: flex; align-items: center; gap: .25rem; min-height: 34px; cursor: pointer; }
.multi-input.disabled { pointer-events: none; opacity: .6; }
.chip { display: inline-flex; align-items: center; gap: .25rem; background: #eef1f6; color: #333; border-radius: 16px; padding: .125rem .5rem; font-size: 12px; }
.chip-remove { background: transparent; border: 0; color: #666; line-height: 1; padding: 0; cursor: pointer; }
.clear-btn { margin-left: auto; background: transparent; border: 0; color: #666; cursor: pointer; }
.dropdown-list { border: 1px solid #dee2e6; border-top: 0; border-radius: 0 0 .375rem .375rem; max-height: 180px; overflow: auto; }
.dropdown-item { padding: .375rem .5rem; font-size: 13px; cursor: pointer; }
.dropdown-item:hover { background: #f6f7fb; }
.dropdown-item.selected { background: #e9f3ff; }
</style>
