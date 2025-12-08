<template>
  <div class="notfound-view">
    <div class="msg">{{ missingName }} page not found</div>
    <div class="detail text-muted">Requested URL: {{ missingUrl }}</div>
    <div class="spacer" />
    <div class="links">
      <RouterLink v-if="isAuthed" to="/" class="btn btn-app-dark">Go Home</RouterLink>
      <RouterLink v-else to="/login" class="btn btn-app-dark">Go to Login</RouterLink>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { authState } from '../auth'
const isAuthed = computed(() => !!authState?.user)
const route = useRoute()
const missingUrl = computed(() => String(route?.query?.missing || route?.fullPath || route?.path || window.location.pathname))
const missingName = computed(() => {
  const raw = String(missingUrl.value || '').replace(/\/+$/, '')
  const parts = raw.split('/').filter(Boolean)
  const last = parts.length ? parts[parts.length - 1] : raw
  try { return decodeURIComponent(last) } catch { return last }
})
</script>

<style scoped>
.notfound-view { min-height: 50vh; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.msg { color: #444; margin-bottom: 6px; font-weight: 600; }
.detail { margin-bottom: 8px; }
.spacer { height: 24px; }
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>
