<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  id: { type: String, default: null },
  label: { type: String, default: '' },
  required: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  placeholder: { type: String, default: 'Selecciona una opción...' },
  options: { type: Array, default: () => [] },
  valueKey: { type: String, default: 'value' },
  labelKey: { type: String, default: 'label' },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'search'])

const selectId = props.id || `search-select-${Math.random().toString(36).slice(2, 9)}`
const isOpen = ref(false)
const searchQuery = ref('')
const containerRef = ref(null)
const searchInputRef = ref(null)

const selectedOption = computed(() => {
  if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) return null
  return props.options.find(opt => String(opt[props.valueKey]) === String(props.modelValue)) || null
})

const filteredOptions = computed(() => {
  if (!searchQuery.value.trim()) return props.options
  const q = searchQuery.value.toLowerCase().trim()
  return props.options.filter(opt => {
    const label = String(opt[props.labelKey] || '').toLowerCase()
    return label.includes(q)
  })
})

function selectOption(option) {
  emit('update:modelValue', option[props.valueKey])
  isOpen.value = false
  searchQuery.value = ''
}

function clearSelection(event) {
  event.stopPropagation()
  emit('update:modelValue', '')
  searchQuery.value = ''
  emit('search', '')
}

let searchTimeout = null
function onSearchInput(event) {
  const val = event.target.value
  searchQuery.value = val
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    emit('search', val)
  }, 250)
}

function toggleOpen() {
  if (props.disabled) return
  isOpen.value = !isOpen.value
  if (isOpen.value) {
    searchQuery.value = ''
    nextTick(() => {
      searchInputRef.value?.focus()
    })
  }
}

function handleClickOutside(event) {
  if (containerRef.value && !containerRef.value.contains(event.target)) {
    isOpen.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
  if (searchTimeout) clearTimeout(searchTimeout)
})

const baseSelectClasses =
  'w-full appearance-none rounded-lg border border-aid-gray-200 bg-white px-3.5 py-2.5 text-sm text-aid-text focus:border-aid-teal focus:outline-none disabled:bg-aid-gray-50 disabled:opacity-60 cursor-pointer flex items-center justify-between shadow-sm'

const errorClasses = props.error ? 'border-aid-danger focus:border-aid-danger' : ''
</script>

<template>
  <div class="space-y-1.5" ref="containerRef">
    <label
      v-if="label"
      :for="selectId"
      class="block text-sm font-medium text-aid-text"
    >
      {{ label }}
      <span v-if="!required" class="font-normal text-aid-text-muted">(opcional)</span>
    </label>

    <div class="relative">
      <div
        :id="selectId"
        :class="[baseSelectClasses, errorClasses, disabled ? 'cursor-not-allowed' : '']"
        @click="toggleOpen"
      >
        <span v-if="selectedOption" class="truncate font-medium text-aid-text">
          {{ selectedOption[labelKey] }}
        </span>
        <span v-else class="truncate text-aid-text-muted">
          {{ placeholder }}
        </span>

        <div class="flex items-center gap-1.5 ml-2 shrink-0 text-aid-text-muted">
          <button
            v-if="selectedOption && !disabled"
            type="button"
            @click="clearSelection"
            class="rounded p-0.5 hover:bg-aid-gray-100 hover:text-aid-danger focus:outline-none"
            title="Limpiar selección"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <svg class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': isOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </div>

      <!-- Dropdown panel -->
      <div
        v-if="isOpen"
        class="absolute left-0 z-50 mt-1 w-full rounded-lg border border-aid-gray-200 bg-white shadow-xl"
      >
        <!-- Search Input Header -->
        <div class="border-b border-aid-gray-100 p-2 bg-aid-gray-50/50 rounded-t-lg">
          <div class="relative flex items-center">
            <svg class="pointer-events-none absolute left-3 h-4 w-4 text-aid-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              ref="searchInputRef"
              type="text"
              :value="searchQuery"
              @input="onSearchInput"
              class="w-full rounded-md border border-aid-gray-200 bg-white py-1.5 pl-9 pr-3 text-sm text-aid-text focus:border-aid-teal focus:outline-none placeholder:text-aid-text-muted"
              placeholder="Escribe para filtrar o buscar..."
            />
          </div>
        </div>

        <!-- Options list -->
        <ul class="max-h-56 overflow-y-auto py-1 divide-y divide-aid-gray-50">
          <li
            v-if="loading"
            class="px-3.5 py-3 text-xs text-aid-text-muted text-center flex items-center justify-center gap-2"
          >
            <svg class="h-4 w-4 animate-spin text-aid-teal" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Buscando representantes...
          </li>
          <li
            v-else-if="filteredOptions.length === 0"
            class="px-3.5 py-3 text-xs text-aid-text-muted text-center"
          >
            No se encontraron representantes con "{{ searchQuery }}"
          </li>
          <li
            v-for="option in filteredOptions"
            :key="option[valueKey]"
            @click="selectOption(option)"
            class="cursor-pointer px-3.5 py-2.5 text-sm text-aid-text hover:bg-aid-teal/10 flex items-center justify-between transition-colors duration-150"
            :class="String(option[valueKey]) === String(modelValue) ? 'bg-aid-teal/15 font-semibold text-aid-navy' : ''"
          >
            <span class="truncate pr-2">{{ option[labelKey] }}</span>
            <svg v-if="String(option[valueKey]) === String(modelValue)" class="h-4 w-4 shrink-0 text-aid-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </li>
        </ul>
      </div>
    </div>

    <p v-if="error" class="text-xs text-aid-danger">{{ error }}</p>
    <p v-else-if="hint" class="text-xs text-aid-text-light">{{ hint }}</p>
  </div>
</template>
