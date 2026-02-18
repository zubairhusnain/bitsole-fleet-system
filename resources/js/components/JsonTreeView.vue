<template>
  <div class="json-tree-view small">
    <template v-if="parsedData && typeof parsedData === 'object'">
      <div v-if="Array.isArray(parsedData)" class="json-array">
        <div v-for="(item, index) in parsedData" :key="index" class="ms-3 border-start ps-2 my-1">
          <span class="text-muted small">[{{ index }}]</span>
          <JsonTreeView :data="item" />
        </div>
      </div>
      <div v-else class="json-object">
        <div v-for="(value, key) in parsedData" :key="key" class="ms-3 border-start ps-2 my-1">
          <span class="text-primary fw-semibold">{{ key }}:</span>
          <JsonTreeView :data="value" class="d-inline" />
        </div>
      </div>
    </template>
    <template v-else>
      <span :class="getValueClass(parsedData)" class="ms-1">{{ formatValue(parsedData) }}</span>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: {
    type: [Object, Array, String, Number, Boolean, null],
    required: true
  }
});

const parsedData = computed(() => {
  if (typeof props.data === 'string') {
    try {
      const parsed = JSON.parse(props.data);
      if (typeof parsed === 'object' && parsed !== null) {
        return parsed;
      }
    } catch (e) {
      // Not JSON
    }
  }
  return props.data;
});

const formatValue = (val) => {
  if (val === null) return 'null';
  if (typeof val === 'boolean') return val ? 'true' : 'false';
  if (typeof val === 'string') return `"${val}"`;
  return val;
};

const getValueClass = (val) => {
  if (val === null) return 'text-muted';
  if (typeof val === 'boolean') return 'text-info';
  if (typeof val === 'number') return 'text-danger';
  if (typeof val === 'string') return 'text-success';
  return '';
};
</script>

<style scoped>
.json-tree-view {
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
  line-height: 1.4;
}
.border-start {
  border-left: 1px dashed #dee2e6 !important;
}
</style>
