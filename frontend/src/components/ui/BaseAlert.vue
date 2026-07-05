<script setup>
const props = defineProps({
  variant: {
    type: String,
    default: 'info',
    validator: (v) => ['info', 'success', 'warning', 'danger'].includes(v),
  },
  title: { type: String, default: '' },
  icon: { type: Boolean, default: true },
})

const variantStyles = {
  info: {
    container: 'bg-aid-navy-50 border-aid-navy-100 text-aid-navy',
    iconColor: 'text-aid-navy',
  },
  success: {
    container: 'bg-aid-success-50 border-aid-success-100 text-aid-success-600',
    iconColor: 'text-aid-success',
  },
  warning: {
    container: 'bg-aid-warning-50 border-aid-warning-100 text-aid-warning-600',
    iconColor: 'text-aid-warning',
  },
  danger: {
    container: 'bg-aid-danger-50 border-aid-danger-100 text-aid-danger-600',
    iconColor: 'text-aid-danger',
  },
}

const icons = {
  info: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  success: 'M5 13l4 4L19 7',
  warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
  danger: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
}

const style = variantStyles[props.variant]
</script>

<template>
  <div
    class="flex gap-3 rounded-xl border p-4"
    :class="style.container"
  >
    <svg
      v-if="icon"
      class="mt-0.5 h-5 w-5 shrink-0"
      :class="style.iconColor"
      fill="none"
      viewBox="0 0 24 24"
      stroke="currentColor"
      stroke-width="2"
    >
      <path stroke-linecap="round" stroke-linejoin="round" :d="icons[variant]" />
    </svg>
    <div>
      <p v-if="title" class="font-medium">{{ title }}</p>
      <div class="text-sm leading-relaxed">
        <slot />
      </div>
    </div>
  </div>
</template>
