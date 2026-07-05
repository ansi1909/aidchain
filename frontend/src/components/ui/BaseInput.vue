<script setup>
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: null },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  min: { type: [String, Number], default: null },
  step: { type: String, default: null },
  rows: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:modelValue'])

const inputId = props.id || `field-${Math.random().toString(36).slice(2, 9)}`

const baseInputClasses =
  'w-full rounded-lg border border-aid-gray-200 bg-white px-3.5 py-2.5 text-sm text-aid-text placeholder-aid-text-muted transition-colors focus:border-aid-teal focus:outline-none disabled:bg-aid-gray-50 disabled:opacity-60'

const errorClasses = props.error ? 'border-aid-danger focus:border-aid-danger' : ''
</script>

<template>
  <div class="space-y-1.5">
    <label
      v-if="label"
      :for="inputId"
      class="block text-sm font-medium text-aid-text"
    >
      {{ label }}
      <span v-if="!required" class="font-normal text-aid-text-muted">(opcional)</span>
    </label>

    <textarea
      v-if="type === 'textarea'"
      :id="inputId"
      :value="modelValue"
      :rows="rows || 3"
      :placeholder="placeholder"
      :disabled="disabled"
      :class="[baseInputClasses, errorClasses]"
      @input="emit('update:modelValue', $event.target.value)"
    />

    <input
      v-else
      :id="inputId"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :min="min"
      :step="step"
      :class="[baseInputClasses, errorClasses]"
      @input="emit('update:modelValue', $event.target.value)"
    />

    <p v-if="error" class="text-xs text-aid-danger">{{ error }}</p>
    <p v-else-if="hint" class="text-xs text-aid-text-light">{{ hint }}</p>
  </div>
</template>
