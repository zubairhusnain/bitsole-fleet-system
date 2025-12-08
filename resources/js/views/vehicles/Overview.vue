<template>
  <div class="content-wrapper">
    <div class="page-header mb-3">
      <h1 class="h4 mb-1">Vehicle Overview</h1>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><RouterLink to="/dashboard">Dashboard</RouterLink></li>
          <li class="breadcrumb-item"><RouterLink to="/vehicles">Vehicle Management</RouterLink></li>
          <li class="breadcrumb-item active" aria-current="page">Overview</li>
        </ol>
      </nav>
    </div>
    <div class="card">
      <div class="card-header"><h6 class="mb-0">Select Vehicle</h6></div>
      <div class="card-body">
        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-6">
            <label class="form-label small">Vehicle</label>
            <select class="form-select" v-model.number="selectedId" @change="goDetail">
              <option :value="0" disabled>Select a vehicle…</option>
              <option v-for="v in options" :key="v.id" :value="v.id">{{ v.label }}</option>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <button class="btn btn-app-dark" @click="goDetail" :disabled="!selectedId">Go to Detail</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const options = ref([])
const selectedId = ref(0)

function goDetail() {
  if (!selectedId.value) return
  router.push(`/vehicles/${selectedId.value}`)
}

onMounted(async () => {
  try {
    const { data } = await axios.get('/web/vehicles/options')
    options.value = (data?.options || []).map(o => ({ id: Number(o.id), label: o.label || (o.name || ('#' + o.id)) }))
    if (!options.value.length) {
      router.replace({ path: '/vehicles', query: { error: 'No vehicles available' } })
      return
    }
    selectedId.value = Number(options.value[0].id)
    goDetail()
  } catch (e) {
    router.replace({ path: '/vehicles', query: { error: 'Failed to load vehicles' } })
  }
})
</script>

<style scoped>
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>
