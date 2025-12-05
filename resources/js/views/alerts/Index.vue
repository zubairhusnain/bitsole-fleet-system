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

    <div class="card">
      <div class="card-header"><h6 class="mb-0">Notification Messages</h6></div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <li v-for="msg in messages" :key="msg.key" class="list-group-item d-flex align-items-center justify-content-between">
            <div>
              <div class="fw-semibold">{{ msg.title }}</div>
              <div class="text-muted small">{{ msg.description }}</div>
              <div class="text-muted small">{{ msg.date }}</div>
            </div>
            <span class="badge bg-light text-dark">{{ msg.key }}</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const messages = ref([
  { key: 'geofenceEnter', title: 'Geofence Enter', description: 'Triggered when a device enters a geofence.' },
  { key: 'geofenceExit', title: 'Geofence Exit', description: 'Triggered when a device exits a geofence.' },
  { key: 'deviceOverspeed', title: 'Overspeed', description: 'Speed exceeds configured threshold.' },
  { key: 'alarm', title: 'Alarm', description: 'General alarm event from the device.' },
  { key: 'ignitionOn', title: 'Ignition On', description: 'Vehicle ignition turned on.' },
  { key: 'ignitionOff', title: 'Ignition Off', description: 'Vehicle ignition turned off.' },
  { key: 'sos', title: 'SOS', description: 'Emergency SOS button pressed.' },
  { key: 'powerCut', title: 'Power Cut', description: 'External power disconnected.' },
  { key: 'lowBattery', title: 'Low Battery', description: 'Device battery level is low.' },
  { key: 'motion', title: 'Motion', description: 'Device motion detected.' }
]);

const stamp = new Date().toLocaleString();
messages.value = messages.value.map(m => ({ ...m, date: stamp }));
</script>

<style scoped>
.list-group-item { padding: 12px 16px; }
.badge { border: 1px solid #e5e7eb; }
</style>
