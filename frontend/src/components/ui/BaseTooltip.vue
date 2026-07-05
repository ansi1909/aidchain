<script setup>
/**
 * Tooltip accesible con icono de información por defecto.
 *
 * Uso:
 *   <BaseTooltip>
 *     Texto explicativo que aparece al pasar el cursor.
 *   </BaseTooltip>
 *
 * También se puede personalizar el trigger:
 *   <BaseTooltip>
 *     <template #trigger>?</template>
 *     Texto de ayuda.
 *   </BaseTooltip>
 */
import { ref } from 'vue'

defineProps({
  position: {
    type: String,
    default: 'bottom',
    validator: (v) => ['top', 'bottom', 'left', 'right'].includes(v),
  },
})

const visible = ref(false)

function show() {
  visible.value = true
}

function hide() {
  visible.value = false
}
</script>

<template>
  <span
    class="relative inline-flex items-center"
    @mouseenter="show"
    @mouseleave="hide"
    @focusin="show"
    @focusout="hide"
  >
    <span class="sr-only">Información</span>
    <span class="cursor-help text-aid-text-light hover:text-aid-teal">
      <slot name="trigger">
        <svg
          class="h-4 w-4"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
          aria-hidden="true"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      </slot>
    </span>
    <transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0 translate-y-1 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100 translate-y-0 scale-100"
      leave-to-class="opacity-0 translate-y-1 scale-95"
    >
      <span
        v-if="visible"
        role="tooltip"
        class="pointer-events-none absolute z-50 w-64 rounded-lg border border-aid-gray-200 bg-white p-3 text-xs text-aid-text shadow-lg"
        :class="{
          'left-1/2 top-full mt-2 -translate-x-1/2': position === 'bottom',
          'bottom-full left-1/2 mb-2 -translate-x-1/2': position === 'top',
          'right-full top-1/2 mr-2 -translate-y-1/2': position === 'left',
          'left-full top-1/2 ml-2 -translate-y-1/2': position === 'right',
        }"
      >
        <slot />
      </span>
    </transition>
  </span>
</template>
