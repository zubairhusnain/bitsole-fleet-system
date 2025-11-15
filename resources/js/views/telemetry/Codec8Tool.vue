<template>
  <div class="codec8-tool-view">
    <h4 class="mb-3 fw-semibold">Codec 8 Extended Decoder</h4>
    <div class="card mb-3">
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-sm-12 col-md-4">
            <label class="form-label small">Device</label>
            <select v-model="selectedDeviceId" class="form-select">
              <option value="" disabled>Select a device</option>
              <option v-for="opt in deviceOptions" :key="opt.deviceId || opt.id" :value="opt.deviceId || opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div class="col-sm-6 col-md-3">
            <label class="form-label small">From (UTC)</label>
            <input v-model="fromIso" type="datetime-local" class="form-control" />
          </div>
          <div class="col-sm-6 col-md-3">
            <label class="form-label small">To (UTC)</label>
            <input v-model="toIso" type="datetime-local" class="form-control" />
          </div>
          <div class="col-sm-12 col-md-2 d-grid">
            <button class="btn btn-app-dark" :disabled="loading || !selectedDeviceId" @click="fetchLogs">Fetch Logs</button>
          </div>
        </div>
        <div class="small text-muted mt-2" v-if="serverWindow">Server window: {{ serverWindow }}</div>
        <div class="text-danger small mt-2" v-if="error">{{ error }}</div>
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label small">Paste hex string</label>
      <textarea class="form-control" rows="8" v-model="inputHex" placeholder="000000..."></textarea>
    </div>
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-app-dark" @click="decode">Decode</button>
      <span class="text-danger small" v-if="error">{{ error }}</span>
    </div>
    <div>
      <pre class="decoded-json">{{ output }}</pre>
    </div>
    <div v-if="decodedFromLogs.length" class="mt-3">
      <h6 class="fw-semibold">Decoded from Logs</h6>
      <pre class="decoded-json">{{ JSON.stringify(decodedFromLogs, null, 2) }}</pre>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { parseCodec8Extended } from '../../utils/codec8e'

const inputHex = ref('')
const error = ref('')
const output = ref('[]')
const loading = ref(false)
const selectedDeviceId = ref('')
const deviceOptions = ref([])
const loadingOptions = ref(false)
const fromIso = ref('')
const toIso = ref('')
const serverWindow = ref('')
const decodedFromLogs = ref([])

function decode() {
  error.value = ''
  output.value = '[]'
  const src = (inputHex.value || '').trim()
  if (!src) return
  try {
    const recs = parseCodec8Extended(src)
    output.value = JSON.stringify(recs, null, 2)
  } catch (e) {
    error.value = e?.message || 'Decode failed'
  }
}

async function fetchLogs() {
  error.value = ''
  loading.value = true
  decodedFromLogs.value = []
  serverWindow.value = ''
  try {
    const params = {}
    if (fromIso.value) params.from = new Date(fromIso.value).toISOString().replace(/\.\d{3}Z$/, 'Z')
    if (toIso.value) params.to = new Date(toIso.value).toISOString().replace(/\.\d{3}Z$/, 'Z')
    const { data } = await axios.get(`/web/vehicles/${selectedDeviceId.value}/logs`, { params })
    serverWindow.value = data?.from && data?.to ? `${data.from} → ${data.to}` : ''
    const logs = Array.isArray(data?.logs) ? data.logs : []
    const decoded = []
    for (const entry of logs) {
      const hex = String(entry?.hex || '').trim()
      if (!hex || /[^0-9a-fA-F]/.test(hex)) continue
      try {
        const recs = parseCodec8Extended(hex)
        decoded.push({ meta: { time: entry.time, protocol: entry.protocol }, records: recs })
      } catch (e) {
        // ignore decode errors for non-Teltonika payloads
      }
    }
    decodedFromLogs.value = decoded
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to fetch logs'
  } finally {
    loading.value = false
  }
}
async function fetchDeviceOptions() {
  loadingOptions.value = true
  try {
    const { data } = await axios.get('/web/vehicles/options', { params: { includeAll: 1 } })
    deviceOptions.value = Array.isArray(data?.options) ? data.options : []
    if (!selectedDeviceId.value && deviceOptions.value.length) {
      selectedDeviceId.value = deviceOptions.value[0].deviceId || deviceOptions.value[0].id
    }
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load devices'
  } finally {
    loadingOptions.value = false
  }
}
fetchDeviceOptions()
</script>

<style scoped>
.decoded-json { white-space: pre-wrap; background: #0b0f2810; padding: 12px; border-radius: 10px; }
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>
