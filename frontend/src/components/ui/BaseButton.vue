<script setup>
const props = defineProps({
  variant: {
    type: String,
    default: 'primary',
    validator: (v) => ['primary', 'secondary', 'outline', 'ghost', 'danger', 'white'].includes(v),
  },
  size: {
    type: String,
    default: 'md',
    validator: (v) => ['sm', 'md', 'lg'].includes(v),
  },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  block: { type: Boolean, default: false },
})

const emit = defineEmits(['click'])

const baseClasses =
  'inline-flex items-center justify-center rounded-lg font-medium transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-aid-teal/50 disabled:cursor-not-allowed disabled:opacity-50'

const variantClasses = {
  primary:
    'bg-aid-teal text-white shadow-sm hover:bg-aid-teal-600 hover:shadow-md active:bg-aid-teal-700',
  secondary:
    'bg-aid-navy text-white shadow-sm hover:bg-aid-navy-600 hover:shadow-md active:bg-aid-navy-700',
  outline:
    'border border-aid-gray-200 bg-white text-aid-text hover:border-aid-teal hover:text-aid-teal active:bg-aid-teal-50',
  ghost: 'text-aid-text-light hover:bg-aid-gray-50 hover:text-aid-teal',
  danger: 'bg-aid-danger text-white shadow-sm hover:bg-aid-danger-600 active:bg-aid-danger-700',
  white: 'bg-white text-aid-navy shadow-sm hover:bg-aid-gray-50 active:bg-aid-gray-100',
}

const sizeClasses = {
  sm: 'px-3 py-1.5 text-xs gap-1.5',
  md: 'px-4 py-2 text-sm gap-2',
  lg: 'px-6 py-2.5 text-base gap-2',
}

const classes = [
  baseClasses,
  variantClasses[props.variant],
  sizeClasses[props.size],
  props.block ? 'w-full' : '',
]
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="classes"
    @click="emit('click', $event)"
  >
    <span
      v-if="loading"
      class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"
    />
    <slot />
  </button>
</template>
