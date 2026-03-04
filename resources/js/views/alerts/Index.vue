<template>
  <div class="content-wrapper">
    <div class="app-content-header mb-2">
      <ol class="breadcrumb mb-0 small text-muted">
        <li class="breadcrumb-item"><RouterLink to="/">Home</RouterLink></li>
        <li class="breadcrumb-item active" aria-current="page">Alerts</li>
      </ol>
    </div>

    <div class="row mb-3">
      <div class="col-sm-12"><h4 class="mb-0 fw-semibold">Alerts</h4></div>
    </div>

    <div class="card">
      <div class="card-header"><h6 class="mb-0">Notification Messages</h6></div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <li v-for="msg in messages" :key="msg.id" class="list-group-item d-flex align-items-center justify-content-between">
            <div>
              <div class="fw-semibold">{{ msg.title }}</div>
              <div class="text-muted small">{{ msg.description }}</div>
              <div class="text-muted small">{{ msg.date }}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-light text-dark">{{ msg.key }}</span>
              <button class="btn btn-sm btn-outline-secondary border-0" @click="deleteEvent(msg.id)" title="Remove Event">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { formatDateTime } from '../../utils/datetime';
import { authState } from '../../auth';

const messages = ref([]);
const myDeviceIds = ref([]);
let echoChannel = null;

// Batch incoming alerts to reduce UI thrash
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
                applyRealtimeAlerts(batch);
            }, FLUSH_MS);
        }
    }
}

function applyRealtimeAlerts(list) {
    const newMsgs = [];
    list.forEach(e => {
        if (e && e.deviceid && myDeviceIds.value.includes(e.deviceid)) {
             // Dedupe check
             if (!messages.value.some(m => m.id == e.id)) {
                 const newMsg = mapEvent(e);
                 if (e.id) newMsg.id = e.id;
                 newMsgs.push(newMsg);
             }
        }
    });

    if (newMsgs.length > 0) {
        markAsRead();
        messages.value.unshift(...newMsgs);
        // Sort by id desc (newest first)
        messages.value.sort((a, b) => b.id - a.id);
    }
}

// Live polling fallback
let pollTimer = null;
let visibilityHandler = null;
const POLL_MS = 10000;
let socketsSeen = false;
let fallbackStartTimer = null;

async function pollAlertsOnce() {
    await fetchMessages();
}

function startAlertsPolling() {
    stopAlertsPolling();
    pollTimer = setInterval(() => {
        pollAlertsOnce();
    }, POLL_MS);
}

function stopAlertsPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function armPollingFallback() {
    if (fallbackStartTimer) clearTimeout(fallbackStartTimer);
    fallbackStartTimer = setTimeout(() => {
        if (!socketsSeen) startAlertsPolling();
    }, 8000);
}

const markAsRead = async () => {
     try {
        await axios.post('/web/notifications/mark-read');
    } catch (e) {}
};

const fetchMyDeviceIds = async () => {
    try {
        const { data } = await axios.get('/web/notifications/my-device-ids');
        myDeviceIds.value = data;
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
                console.log('Alerts Batch Received:', payload.alerts);
                scheduleAlertsMerge(payload.alerts);
                socketsSeen = true;
                stopAlertsPolling();
                if (fallbackStartTimer) { clearTimeout(fallbackStartTimer); fallbackStartTimer = null; }
            }
        });
};

const titleMap = {
  geofenceEnter: 'Geofence Entered',
  geofenceExit: 'Geofence Exited',
  deviceOverspeed: 'Speed Limit Exceeded',
  alarm: 'Alarm Triggered',
  ignitionOn: 'Engine Started',
  ignitionOff: 'Engine Stopped',
  sos: 'SOS Alert',
  powerCut: 'Power Disconnected',
  lowBattery: 'Low Battery Warning',
  motion: 'Motion Detected',
  deviceOnline: 'Device Online',
  deviceOffline: 'Device Offline',
  deviceUnknown: 'Device Status Unknown',
  deviceMoving: 'Device Moving',
  deviceStopped: 'Device Stopped',
  deviceInactive: 'Device Inactive',
  maintenance: 'Maintenance Required',
  textMessage: 'Text Message Received',
  driverChanged: 'Driver Changed'
};

const formatTitle = (type) => {
    // If direct mapping exists, use it
    if (titleMap[type]) return titleMap[type];

    // Otherwise convert camelCase/snake_case to Title Case
    return type
        // Insert space before capital letters
        .replace(/([A-Z])/g, ' $1')
        // Replace underscores with spaces
        .replace(/_/g, ' ')
        // Capitalize first letter
        .replace(/^./, (str) => str.toUpperCase())
        // Remove extra spaces
        .trim();
};

const formatDescription = (event) => {
    let desc = `Device: ${event.device_name || event.deviceid}`;
    if (event.attributes) {
        try {
            const attrs = typeof event.attributes === 'string' ? JSON.parse(event.attributes) : event.attributes;
            if (attrs.alarm) desc += ` - Alarm: ${attrs.alarm}`;
            if (attrs.message) desc += ` - ${attrs.message}`;
        } catch (e) {}
    }
    return desc;
};

const mapEvent = (e) => ({
    key: e.type,
    title: formatTitle(e.type),
    description: formatDescription(e),
    date: formatDateTime(e.eventtime),
    id: e.id || `${e.deviceid}-${e.eventtime}`
});

const fetchMessages = async () => {
    try {
        const { data } = await axios.get('/web/notifications/events');
        messages.value = data.map(mapEvent);
    } catch (e) {
        console.error('Failed to fetch messages', e);
    }
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
            popup: 'rounded-3 p-3'
        },
        buttonsStyling: false
    });

    if (result.isConfirmed) {
        try {
            await axios.delete(`/web/notifications/events/${id}`);
            // Optimistic update: remove immediately, though broadcast will also handle it
            messages.value = messages.value.filter(m => m.id !== id);

            // Small toast notification instead of big alert
            Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            }).fire({
                icon: 'success',
                title: 'Notification removed successfully'
            });
        } catch (error) {
            console.error('Failed to Notification ', error);
            Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            }).fire({
                icon: 'error',
                title: 'Failed to delete Notification'
            });
        }
    }
};

onMounted(() => {
    fetchMessages();
    fetchMyDeviceIds().then(() => {
        listenForAlerts();
    });
    markAsRead();

    // Start polling only if sockets don’t deliver updates shortly
    armPollingFallback();
    try {
        visibilityHandler = () => {
            if (document.hidden) {
                stopAlertsPolling();
            } else {
                if (!socketsSeen) {
                    startAlertsPolling();
                } else {
                    stopAlertsPolling();
                }
            }
        };
        document.addEventListener('visibilitychange', visibilityHandler);
    } catch {}
});

onUnmounted(() => {
    if (echoChannel && authState.user) {
        window.echo.leave(`alerts.${authState.user.id}`);
    }
    if (flushTimer) clearTimeout(flushTimer);
    if (pollTimer) clearInterval(pollTimer);
    if (fallbackStartTimer) clearTimeout(fallbackStartTimer);
    try {
        if (visibilityHandler) {
            document.removeEventListener('visibilitychange', visibilityHandler);
            visibilityHandler = null;
        }
    } catch {}
});
</script>

<style scoped>
.list-group-item { padding: 12px 16px; }
.badge { border: 1px solid #e5e7eb; }
</style>
