<template>
  <div ref="rootEl" class="multi-select" :class="{ 'is-open': open, 'is-disabled': disabled }">
    <button
      type="button"
      class="multi-select-control"
      :disabled="disabled"
      :aria-expanded="open"
      @click="toggleOpen"
    >
      <span class="multi-select-label">{{ label }}</span>
      <div class="multi-select-value">
        <span v-if="!selectedValues.length" class="multi-select-placeholder">{{ placeholder }}</span>
        <span
          v-for="value in selectedValues"
          :key="value"
          class="multi-select-chip"
        >
          {{ optionLabel(value) }}
          <button
            type="button"
            class="multi-select-chip-remove"
            :aria-label="`Remove ${optionLabel(value)}`"
            @click.stop="removeValue(value)"
          >
            <i class="bi bi-x"></i>
          </button>
        </span>
      </div>
      <i class="bi bi-chevron-down multi-select-chevron"></i>
    </button>

    <div v-show="open" class="multi-select-menu">
      <button
        v-for="option in normalizedOptions"
        :key="option.value"
        type="button"
        class="multi-select-option"
        :class="{ 'is-selected': isSelected(option.value) }"
        @click="toggleValue(option.value)"
      >
        <span class="multi-select-check">
          <i v-if="isSelected(option.value)" class="bi bi-check2"></i>
        </span>
        <span class="multi-select-option-label">{{ option.label }}</span>
      </button>
      <div v-if="!normalizedOptions.length" class="multi-select-empty">No options available</div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    options: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: 'Multi-select',
    },
    placeholder: {
        type: String,
        default: 'Please select...',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const rootEl = ref(null);

const normalizedOptions = computed(() => props.options.map((option) => {
    if (typeof option === 'string' || typeof option === 'number') {
        return { value: option, label: String(option) };
    }
    return {
        value: option.value,
        label: option.label ?? String(option.value),
    };
}));

const selectedValues = computed(() => props.modelValue ?? []);

const optionLabel = (value) => {
    const match = normalizedOptions.value.find((option) => option.value === value);
    return match?.label ?? String(value);
};

const isSelected = (value) => selectedValues.value.includes(value);

const toggleOpen = () => {
    if (props.disabled) return;
    open.value = !open.value;
};

const toggleValue = (value) => {
    const next = [...selectedValues.value];
    const index = next.indexOf(value);
    if (index >= 0) {
        next.splice(index, 1);
    } else {
        next.push(value);
    }
    emit('update:modelValue', next);
};

const removeValue = (value) => {
    emit('update:modelValue', selectedValues.value.filter((item) => item !== value));
};

const onDocumentClick = (event) => {
    if (!rootEl.value?.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>

<style scoped>
.multi-select {
    position: relative;
    width: 100%;
}

.multi-select-control {
    position: relative;
    display: flex;
    align-items: flex-start;
    width: 100%;
    min-height: 56px;
    padding: 1.15rem 2rem 0.55rem 0.75rem;
    border: 1px solid #c4c4c4;
    border-radius: 0.375rem;
    background: #fff;
    text-align: left;
    cursor: pointer;
}

.multi-select-control:hover:not(:disabled) {
    border-color: #9ca3af;
}

.multi-select.is-open .multi-select-control {
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
    border-color: #886654;
}

.multi-select-control:disabled {
    background: #f9fafb;
    cursor: not-allowed;
    opacity: 0.7;
}

.multi-select-label {
    position: absolute;
    top: 0.45rem;
    left: 0.75rem;
    font-size: 0.75rem;
    line-height: 1;
    color: #6b7280;
    pointer-events: none;
}

.multi-select-value {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    width: 100%;
    min-height: 1.5rem;
    padding-top: 0.15rem;
}

.multi-select-placeholder {
    color: #9ca3af;
    font-size: 0.875rem;
    line-height: 1.5rem;
}

.multi-select-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.15rem;
    max-width: 100%;
    padding: 0.15rem 0.35rem 0.15rem 0.55rem;
    border-radius: 0.25rem;
    background: #f3f4f6;
    color: #374151;
    font-size: 0.8125rem;
    line-height: 1.2;
}

.multi-select-chip-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    height: 1rem;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
}

.multi-select-chip-remove:hover {
    background: #e5e7eb;
    color: #111827;
}

.multi-select-chevron {
    position: absolute;
    top: 50%;
    right: 0.75rem;
    transform: translateY(-50%);
    color: #6b7280;
    font-size: 0.875rem;
    pointer-events: none;
    transition: transform 0.15s ease;
}

.multi-select.is-open .multi-select-chevron {
    transform: translateY(-50%) rotate(180deg);
}

.multi-select-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 20;
    max-height: 240px;
    overflow-y: auto;
    border: 1px solid #886654;
    border-top: 0;
    border-radius: 0 0 0.375rem 0.375rem;
    background: #fff;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
}

.multi-select-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.55rem 0.75rem;
    border: 0;
    background: transparent;
    color: #111827;
    font-size: 0.875rem;
    text-align: left;
    cursor: pointer;
}

.multi-select-option:hover {
    background: #f9fafb;
}

.multi-select-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    flex-shrink: 0;
    color: #111827;
    font-size: 0.95rem;
}

.multi-select-option-label {
    min-width: 0;
}

.multi-select-empty {
    padding: 0.75rem;
    color: #9ca3af;
    font-size: 0.875rem;
}
</style>
