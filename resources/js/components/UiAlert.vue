<template>
  <div v-if="message && show" :class="['alert', `alert-${variant}`, dismissible ? 'alert-dismissible' : '']" role="alert">
    <span>{{ message }}</span>
    <button v-if="dismissible" type="button" class="btn-close" aria-label="Close" @click="onDismiss"></button>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  message: { type: String, default: '' },
  variant: { type: String, default: 'danger' },
  dismissible: { type: Boolean, default: true },
  show: { type: Boolean, default: true },
});

const emit = defineEmits(['dismiss']);

function onDismiss() {
  emit('dismiss');
}
</script>

<style scoped>
/* Inherit Bootstrap alert styling; no extra styles needed */
</style>