<template>
  <div class="codec8-tool-view">
    <h4 class="mb-3 fw-semibold">Codec 8 Extended Decoder</h4>
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
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { parseCodec8Extended } from '../../utils/codec8e'

const inputHex = ref('')
const error = ref('')
const output = ref('[]')

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
</script>

<style scoped>
.decoded-json { white-space: pre-wrap; background: #0b0f2810; padding: 12px; border-radius: 10px; }
.btn-app-dark { background-color: #0b0f28; color: #fff; border-radius: 12px; padding: .5rem .75rem; }
</style>
