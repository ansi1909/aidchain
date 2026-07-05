<script setup>
const props = defineProps({
  variant: {
    type: String,
    default: 'default',
    validator: (v) => ['default', 'success', 'warning', 'danger', 'info', 'teal', 'navy'].includes(v),
  },
  label: { type: String, default: '' },
  dot: { type: Boolean, default: true },
})

const variantStyles = {
  default: 'bg-aid-gray-100 text-aid-gray-700',
  success: 'bg-aid-success-50 text-aid-success-600',
  warning: 'bg-aid-warning-50 text-aid-warning-600',
  danger: 'bg-aid-danger-50 text-aid-danger-600',
  info: 'bg-aid-navy-50 text-aid-navy',
  teal: 'bg-aid-teal-50 text-aid-teal-700',
  navy: 'bg-aid-navy-50 text-aid-navy',
}

const dotStyles = {
  default: 'bg-aid-gray-400',
  success: 'bg-aid-success',
  warning: 'bg-aid-warning',
  danger: 'bg-aid-danger',
  info: 'bg-aid-navy',
  teal: 'bg-aid-teal',
  navy: 'bg-aid-navy',
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
    :class="variantStyles[variant]"
  >
    <span
      v-if="dot"
      class="h-1.5 w-1.5 rounded-full"
      :class="dotStyles[variant]"
    />
    {{ label }}
  </span>
</template>
