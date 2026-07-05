<script setup>
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: null },
  label: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  placeholder: { type: String, default: 'Selecciona una opción' },
  options: { type: Array, default: () => [] },
  valueKey: { type: String, default: 'value' },
  labelKey: { type: String, default: 'label' },
})

const emit = defineEmits(['update:modelValue'])

const selectId = props.id || `select-${Math.random().toString(36).slice(2, 9)}`

const baseSelectClasses =
  'w-full appearance-none rounded-lg border border-aid-gray-200 bg-white px-3.5 py-2.5 text-sm text-aid-text focus:border-aid-teal focus:outline-none disabled:bg-aid-gray-50 disabled:opacity-60'

const errorClasses = props.error ? 'border-aid-danger focus:border-aid-danger' : ''
</script>

<template>
  <div class="space-y-1.5">
    <label
      v-if="label"
      :for="selectId"
      class="block text-sm font-medium text-aid-text"
    >
      {{ label }}
      <span v-if="!required" class="font-normal text-aid-text-muted">(opcional)</span>
    </label>

    <div class="relative">
      <select
        :id="selectId"
        :value="modelValue"
        :required="required"
        :disabled="disabled"
        :class="[baseSelectClasses, errorClasses]"
        @change="emit('update:modelValue', $event.target.value)"
      >
        <option value="" disabled>{{ placeholder }}</option>
        <option
          v-for="option in options"
          :key="option[valueKey]"
          :value="option[valueKey]"
        >
          {{ option[labelKey] }}
        </option>
      </select>

      <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-aid-text-muted">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
      </div>
    </div>

    <p v-if="error" class="text-xs text-aid-danger">{{ error }}</p>
    <p v-else-if="hint" class="text-xs text-aid-text-light">{{ hint }}</p>
  </div>
</template>
