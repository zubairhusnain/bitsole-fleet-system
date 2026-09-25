<template>
  <div class="content-wrapper">
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Home</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Alerts & Notifications</li>
      </ol>
    </div>

    <div class="row mb-3">
      <div class="col-sm-12"><h4 class="mb-0 fw-semibold">Alerts & Notifications</h4></div>
    </div>
 
    <div class="card border rounded-3 shadow-0 mb-3">
      <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
        <h6 class="mb-0 fw-bold">Search Option</h6>
      </div>
      <div class="card-body pt-2">
        <div class="d-flex flex-column flex-xl-row flex-wrap gap-3 align-items-xl-end alerts-filter-row">
          <div class="alerts-filter-field alerts-filter-vehicle">
            <label class="form-label small fw-semibold text-muted">Vehicle</label>
            <select v-model="filters.deviceId" class="form-select">
              <option value="">All Vehicles</option>
              <option v-for="d in deviceOptions" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
            </select>
          </div>
          <div class="alerts-filter-field alerts-filter-type">
            <MultiSelect
              v-model="filters.types"
              label="Alert Type"
              placeholder="Please select..."
              :options="alertTypeSelectOptions"
            />
          </div>
          <div class="alerts-filter-field alerts-filter-duration">
            <label class="form-label small fw-semibold text-muted">Duration (last 24h default)</label>
            <div class="input-group">
              <input v-model="filters.dateFrom" type="datetime-local" class="form-control alerts-filter-datetime" />
              <span class="input-group-text bg-white px-2">–</span>
              <input v-model="filters.dateTo" type="datetime-local" class="form-control alerts-filter-datetime" />
            </div>
          </div>
          <div class="alerts-filter-field alerts-filter-per-page">
            <label class="form-label small fw-semibold text-muted">Per Page</label>
            <select v-model.number="selectedPerPage" class="form-select" @change="onPerPageChange">
              <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <div class="alerts-filter-actions flex-shrink-0 ms-xl-auto">
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-app-dark" style="min-width: 100px;" @click="applyFilters" :disabled="loadingHistory">
                <i class="bi bi-funnel me-1"></i>
                Search
              </button>
              <button type="button" class="btn btn-outline-secondary" style="min-width: 90px;" @click="clearFilters" :disabled="loadingHistory">
                Reset
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border rounded-3 shadow-0">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h6 class="mb-0">Notification Messages</h6>
        <span v-if="socketConnected" class="badge bg-success-subtle text-success border border-success-subtle">
          <i class="bi bi-broadcast me-1"></i>Live
        </span>
      </div>
      <div class="card-body p-0">
        <div v-if="loadingHistory" class="d-flex align-items-center justify-content-center text-muted py-3 border-bottom">
          <div class="d-flex align-items-center gap-2 small">
            <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
            <span>Loading history...</span>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm align-middle mb-0 table-grid-lines table-nowrap">
            <thead class="thead-app-dark">
              <tr>
                <th class="py-2 ps-4">Device Name</th>
                <th class="py-2">Alert Message</th>
                <th class="py-2">Time</th>
                <th class="py-2">Location</th>
                <th class="py-2 pe-4 text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="totalResults === 0 && !loadingHistory">
                <td colspan="5" class="text-center text-muted py-4">No notifications found</td>
              </tr>
              <tr v-for="msg in messages" :key="msg.id">
                <td class="ps-4 fw-semibold">{{ msg.deviceName }}</td>
                <td>
                  <div>{{ msg.message }}</div>
                  <span class="badge bg-light text-dark mt-1">{{ formatTitle(msg.key) }}</span>
                </td>
                <td class="text-nowrap">{{ msg.date }}</td>
                <td>
                  <a
                    v-if="msg.mapUrl"
                    :href="msg.mapUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-decoration-none"
                    :title="msg.location"
                  >
                    <i class="bi bi-geo-alt me-1"></i>{{ msg.location }}
                  </a>
                  <span v-else class="text-muted">{{ msg.location }}</span>
                </td>
                <td class="pe-4 text-end">
                  <button class="btn btn-sm btn-outline-secondary border-0" @click="deleteEvent(msg.id)" title="Remove Event">
                    <i class="bi bi-x-lg"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div v-if="hasSearched && totalResults > 0" class="card-footer d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 py-2">
        <div class="text-muted small">
          Showing {{ paginationStart }} to {{ paginationEnd }} of {{ totalResults }} results
        </div>
        <nav aria-label="Alerts pagination">
          <ul class="pagination pagination-sm mb-0 pagination-app">
            <li class="page-item" :class="{ disabled: currentPage === 1 }">
              <button type="button" class="page-link" @click="changePage(currentPage - 1)">‹</button>
            </li>
            <li
              v-for="page in visiblePages"
              :key="page"
              class="page-item"
              :class="{ active: currentPage === page }"
            >
              <button type="button" class="page-link" @click="changePage(page)">{{ page }}</button>
            </li>
            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
              <button type="button" class="page-link" @click="changePage(currentPage + 1)">›</button>
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import MultiSelect from '../../components/MultiSelect.vue';
import { formatDateTime } from '../../utils/datetime';
import { defaultLast24HoursDatetimeRange } from '../../utils/reportDates';
import { NOTIFICATION_TITLE_MAP, formatNotificationTitle, formatAlertMessage, parseEventAttributes } from '../../utils/notificationTitles';
import { authState } from '../../auth';
import { createCancellableRequest, isRequestAborted } from '../../utils/cancellableRequest';

const formatTitle = (type) => typeLabels.value[type] ?? formatNotificationTitle(type);

const messages = ref([]);
const deviceOptions = ref([]);
const typeLabels = ref({ ...NOTIFICATION_TITLE_MAP });
const typeOptions = ref(Object.keys(NOTIFICATION_TITLE_MAP).sort());
const alertTypeSelectOptions = computed(() => typeOptions.value.map((type) => ({
    value: type,
    label: formatTitle(type),
})));
const { dateFrom: defaultDateFrom, dateTo: defaultDateTo } = defaultLast24HoursDatetimeRange();
const filters = ref({ deviceId: '', types: [], dateFrom: defaultDateFrom, dateTo: defaultDateTo });

const toFilterMs = (value, isEnd = false) => {
    if (!value) return null;
    if (String(value).includes('T')) {
        const ms = new Date(value).getTime();
        return Number.isFinite(ms) ? ms : null;
    }
    const d = new Date(`${value}T00:00:00`);
    if (isEnd) d.setHours(23, 59, 59, 999);
    const ms = d.getTime();
    return Number.isFinite(ms) ? ms : null;
};
const myDeviceIds = ref([]);
const loadingHistory = ref(false);
const hasSearched = ref(false);
const socketConnected = ref(false);
const listRequest = createCancellableRequest();
const perPageOptions = [10, 25, 50, 100, 200, 500];
const selectedPerPage = ref(25);
const currentPage = ref(1);
const totalResults = ref(0);
const lastPage = ref(1);
let echoChannel = null;

let pendingAlerts = [];
let flushTimer = null;
const FLUSH_MS = 250;

function scheduleAlertsMerge(list) {
    if (Array.isArray(list) && list.length) {
        pendingAlerts.push(...list);
        if (!flushTimer) {
            flushTimer = setTimeout(() => {
                const batch = pendingAlerts;
                pendingAlerts = [];
                flushTimer = null;
                ingestSocketAlerts(batch);
            }, FLUSH_MS);
        }
    }
}

function isAllowedDevice(event) {
    const deviceId = event?.deviceid ?? event?.deviceId ?? event?.device_id ?? null;
    return event && deviceId && myDeviceIds.value.includes(Number(deviceId));
}

const totalPages = computed(() => Math.max(1, lastPage.value));
const paginationStart = computed(() => (
    totalResults.value === 0 ? 0 : (currentPage.value - 1) * selectedPerPage.value + 1
));
const paginationEnd = computed(() => Math.min(currentPage.value * selectedPerPage.value, totalResults.value));
const visiblePages = computed(() => {
    const pages = [];
    const total = totalPages.value;
    const current = currentPage.value;
    let startPage = Math.max(1, current - 2);
    let endPage = Math.min(total, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }
    for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
    }
    return pages;
});

const changePage = async (page) => {
    if (page >= 1 && page <= totalPages.value && page !== currentPage.value) {
        await fetchEvents(page);
    }
};

const onPerPageChange = async () => {
    currentPage.value = 1;
    if (hasSearched.value) {
        await fetchEvents(1);
    }
};

function prependLiveEvents(list) {
    if (!Array.isArray(list) || list.length === 0 || currentPage.value !== 1) return;

    const existingIds = new Set(messages.value.map((m) => String(m.id)));
    const toPrepend = list
        .filter(isAllowedDevice)
        .filter(eventMatchesFilters)
        .filter((event) => !existingIds.has(String(event.id ?? `${event.deviceid}-${event.eventtime}`)))
        .map(mapEvent);

    if (!toPrepend.length) return;

    messages.value = [...toPrepend, ...messages.value].slice(0, selectedPerPage.value);
    totalResults.value += toPrepend.length;
}

function ingestSocketAlerts(list) {
    if (!Array.isArray(list) || list.length === 0) return;

    socketConnected.value = true;

    if (!hasSearched.value) return; 

    prependLiveEvents(list);
    markAsRead();
}

const markAsRead = async () => {
    try {
        await axios.post('/web/notifications/mark-read');
    } catch (e) {}
};

const fetchMyDeviceIds = async () => {
    try {
        const { data } = await axios.get('/web/notifications/my-device-ids');
        myDeviceIds.value = Array.isArray(data) ? data.map(Number).filter((n) => Number.isFinite(n)) : [];
    } catch (e) {
        console.error('Failed to fetch device IDs', e);
    }
};

const listenForAlerts = () => {
    if (echoChannel) return;
    if (!window.echo) return;
    if (!authState.user || !authState.user.id) return;

    const userId = authState.user.id;
    echoChannel = window.echo.private(`alerts.${userId}`)
        .listen('.alerts.updated', (payload) => {
            if (Array.isArray(payload?.alerts)) {
                scheduleAlertsMerge(payload.alerts);
            }
        });

    if (echoChannel.subscription) {
        echoChannel.subscription.bind('pusher:subscription_succeeded', () => {
            socketConnected.value = true;
        });
    }
};

const formatLocation = (event) => {
    const address = event.address || event.location;
    if (address && String(address).trim()) {
        return String(address).trim();
    }

    const lat = event.latitude ?? event.lat;
    const lng = event.longitude ?? event.lng;
    if (lat != null && lng != null && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng))) {
        return `${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)}`;
    }

    return '—';
};

const mapEvent = (e) => {
    const lat = e.latitude ?? e.lat;
    const lng = e.longitude ?? e.lng;
    const hasCoords = lat != null && lng != null && Number.isFinite(Number(lat)) && Number.isFinite(Number(lng));

    return {
        key: e.type ?? e.notification_type ?? e.notificationType,
        deviceName: e.device_name || e.deviceName || `Vehicle #${e.deviceid ?? e.deviceId ?? '—'}`,
        message: formatAlertMessage(e),
        date: formatDateTime(e.eventtime),
        location: formatLocation(e),
        mapUrl: hasCoords ? `https://www.google.com/maps?q=${Number(lat)},${Number(lng)}` : null,
        id: e.id || `${e.deviceid}-${e.eventtime}`,
    };
};

const eventMatchesFilters = (event) => {
    const f = filters.value;
    if (f.deviceId) {
        const n = Number(f.deviceId);
        const deviceId = event.deviceid ?? event.deviceId ?? event.device_id;
        if (Number.isFinite(n) && Number(deviceId) !== n) return false;
    }
    if (f.types?.length) {
        const type = event.type ?? event.notification_type ?? event.notificationType;
        const attrs = parseEventAttributes(event);
        const alarmType = attrs.alarm ? String(attrs.alarm) : null;
        const matches = f.types.includes(type) || (alarmType && f.types.includes(alarmType));
        if (!matches) return false;
    }
    if (f.dateFrom || f.dateTo) {
        const eventMs = new Date(event.eventtime).getTime();
        if (!Number.isFinite(eventMs)) return false;
        const fromMs = toFilterMs(f.dateFrom);
        const toMs = toFilterMs(f.dateTo, true);
        if (fromMs != null && eventMs < fromMs) return false;
        if (toMs != null && eventMs > toMs) return false;
    }
    return true;
};
 
const fetchDeviceOptions = async () => {
    try {
        const { data } = await axios.get('/web/notifications/device-options');
        deviceOptions.value = Array.isArray(data) ? data : [];
    } catch (e) {
        deviceOptions.value = [];
    }
};

const mergeTypeOptions = (apiTypes = []) => {
    const merged = [...new Set([...Object.keys(NOTIFICATION_TITLE_MAP), ...apiTypes])];
    merged.sort();
    typeOptions.value = merged;
};

const fetchTypeOptions = async () => {
    try {
        const { data } = await axios.get('/web/notifications/type-options');
        const apiTypes = Array.isArray(data) ? data : (data?.types ?? []);
        if (data?.labels && typeof data.labels === 'object') {
            typeLabels.value = { ...NOTIFICATION_TITLE_MAP, ...data.labels };
        }
        if (apiTypes.length) {
            mergeTypeOptions(apiTypes);
        }
    } catch (e) {
        // Keep NOTIFICATION_TITLE_MAP defaults when the API is unavailable.
    }
};

const buildEventParams = (page = currentPage.value) => ({
    page,
    per_page: selectedPerPage.value,
    device_id: filters.value.deviceId || undefined,
    types: filters.value.types.length ? filters.value.types : undefined,
    date_from: filters.value.dateFrom || undefined,
    date_to: filters.value.dateTo || undefined,
});

const fetchEvents = async (page = 1) => {
    const signal = listRequest.nextSignal();
    loadingHistory.value = true;
    try {
        const { data } = await axios.get('/web/notifications/events', {
            params: buildEventParams(page),
            signal,
        });
        const list = Array.isArray(data) ? data : (data.data ?? []);

        messages.value = list.map(mapEvent);
        totalResults.value = data.total ?? list.length;
        lastPage.value = data.last_page ?? 1;
        currentPage.value = data.current_page ?? page;
    } catch (e) {
        if (isRequestAborted(e)) return;
        console.error('Failed to fetch alert history', e);
    } finally {
        if (!signal.aborted) {
            loadingHistory.value = false;
        }
    }
};

const applyFilters = async () => {
    hasSearched.value = true;
    currentPage.value = 1;
    await fetchEvents(1);
    await markAsRead();
};
  
const clearFilters = async () => {
    listRequest.cancel();
    const { dateFrom, dateTo } = defaultLast24HoursDatetimeRange();
    filters.value = { deviceId: '', types: [], dateFrom, dateTo };
    currentPage.value = 1;
    await applyFilters();
};

const deleteEvent = async (id) => {
    const result = await Swal.fire({
        title: 'Delete Event?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        width: '250px',
        customClass: {
            title: 'fs-6',
            actions: 'd-flex gap-2 justify-content-center',
            confirmButton: 'btn btn-sm btn-danger m-0',
            cancelButton: 'btn btn-sm btn-secondary m-0',
            popup: 'rounded-3 p-3',
        },
        buttonsStyling: false,
    });

    if (result.isConfirmed) {
        try {
            await axios.delete(`/web/notifications/events/${id}`);
            messages.value = messages.value.filter((m) => m.id !== id);
            totalResults.value = Math.max(0, totalResults.value - 1);

            Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            }).fire({
                icon: 'success',
                title: 'Notification removed successfully',
            });
        } catch (error) {
            console.error('Failed to Notification ', error);
            Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
            }).fire({
                icon: 'error',
                title: 'Failed to delete Notification',
            });
        }
    }
};

onMounted(async () => {
    fetchDeviceOptions();
    fetchTypeOptions();
    await fetchMyDeviceIds();
    listenForAlerts();
    await applyFilters();
});

onBeforeUnmount(() => {
    listRequest.cancel();
});

onUnmounted(() => {
    if (echoChannel && authState.user) {
        window.echo.leave(`alerts.${authState.user.id}`);
    }
    if (flushTimer) clearTimeout(flushTimer);
});
</script>

<style scoped>
.badge { border: 1px solid #e5e7eb; }

.alerts-filter-row {
    row-gap: 0.75rem;
}

.alerts-filter-field {
    flex: 1 1 auto;
    min-width: 0;
}

.alerts-filter-vehicle,
.alerts-filter-type {
    flex: 1 1 220px;
    max-width: 280px;
}

.alerts-filter-duration {
    flex: 2 1 320px;
    max-width: 480px;
}

.alerts-filter-per-page {
    flex: 0 0 90px;
    width: 90px;
}

.alerts-filter-datetime {
    min-width: 0;
    font-size: 0.8125rem;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.alerts-filter-actions {
    padding-bottom: 1px;
}

@media (min-width: 1200px) {
    .alerts-filter-row {
        flex-wrap: nowrap;
    }
}
</style>
