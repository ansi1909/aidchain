<script setup>
const props = defineProps({
  padding: { type: String, default: 'md' },
  shadow: { type: String, default: 'sm' },
  hover: { type: Boolean, default: false },
  border: { type: Boolean, default: true },
})

const paddingClasses = {
  none: '',
  sm: 'p-4',
  md: 'p-5',
  lg: 'p-6',
}

const shadowClasses = {
  none: '',
  sm: 'shadow-sm',
  md: 'shadow-md',
  lg: 'shadow-lg',
}

const classes = [
  'rounded-xl bg-white',
  paddingClasses[props.padding],
  shadowClasses[props.shadow],
  props.border ? 'border border-aid-gray-100' : '',
  props.hover ? 'transition-shadow hover:shadow-md' : '',
]
</script>

<template>
  <div :class="classes">
    <slot />
  </div>
</template>
