<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { useCatalogStore } from '../stores/catalog'
import { useNeedsStore } from '../stores/needs'
import { useIdentityStore } from '../stores/identity'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseInput from '../components/ui/BaseInput.vue'
import BaseSelect from '../components/ui/BaseSelect.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseAlert from '../components/ui/BaseAlert.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'

const catalog = useCatalogStore()
const needs = useNeedsStore()
const identity = useIdentityStore()

const esAdmin = computed(() => (identity.coordinator?.roles ?? []).includes('admin'))
const esEncargadoRefugio = computed(() => (identity.coordinator?.roles ?? []).includes('encargado_refugio'))

const refugiosDisponibles = computed(() => {
  const shelterIdAsignado = identity.coordinator?.shelterId
  if (esEncargadoRefugio.value && shelterIdAsignado) {
    return catalog.shelters.filter(s => s.id === shelterIdAsignado)
  }
  return catalog.shelters
})

const form = reactive({
  shelterId: '',
  item: '',
  cantidadRequerida: '',
  unidad: 'unidades',
  prioridad: '',
  notas: '',
})

const editandoId = ref(null)
const enviando = ref(false)
const error = ref(null)

const prioridades = [
  { value: 'baja', label: 'Baja' },
  { value: 'media', label: 'Media' },
  { value: 'alta', label: 'Alta' },
  { value: 'critica', label: 'Crítica' },
]

const unidadesComunes = [
  { value: 'unidades', label: 'Unidades / Piezas' },
  { value: 'cajas', label: 'Cajas' },
  { value: 'litros', label: 'Litros (L)' },
  { value: 'botellas', label: 'Botellas' },
  { value: 'botellones', label: 'Botellones' },
  { value: 'ampollas', label: 'Ampollas' },
  { value: 'pastillas', label: 'Pastillas / Comprimidos' },
  { value: 'paquetes', label: 'Paquetes / Bolsas' },
  { value: 'latas', label: 'Latas' },
  { value: 'kg', label: 'Kilogramos (kg)' },
  { value: 'toneladas', label: 'Toneladas' },
]

onMounted(async () => {
  await identity.init()
  if (catalog.shelters.length === 0) {
    await catalog.cargarCatalogos()
  }
  // Auto-seleccionar el refugio asignado para encargado de refugio
  if (esEncargadoRefugio.value && identity.coordinator?.shelterId) {
    form.shelterId = String(identity.coordinator.shelterId)
    await cargarNecesidades()
  }
})

async function cargarNecesidades() {
  if (!form.shelterId) return
  error.value = null
  try {
    await needs.cargarNecesidadesShelter(Number(form.shelterId))
  } catch (err) {
    error.value = err?.response?.data?.error ?? 'No se pudieron cargar las necesidades.'
  }
}

async function onSubmit() {
  error.value = null

  if (!form.shelterId || !form.item.trim() || !form.cantidadRequerida || !form.unidad || !form.prioridad) {
    error.value = 'Por favor completa todos los campos requeridos (Insumo, Cantidad, Formato/Unidad y Prioridad).'
    return
  }

  enviando.value = true
  try {
    const datos = {
      item: form.item.trim(),
      unidad: form.unidad,
      cantidadRequerida: Number(form.cantidadRequerida),
      prioridad: form.prioridad,
      notas: form.notas.trim() || null,
    }

    if (editandoId.value) {
      await needs.actualizarNecesidad(Number(form.shelterId), editandoId.value, datos)
      editandoId.value = null
    } else {
      await needs.crearNecesidad(Number(form.shelterId), datos)
    }

    form.item = ''
    form.cantidadRequerida = ''
    form.unidad = 'unidades'
    form.prioridad = ''
    form.notas = ''
    await cargarNecesidades()
  } catch (err) {
    error.value = err?.response?.data?.error ?? 'No se pudo guardar la necesidad.'
  } finally {
    enviando.value = false
  }
}

function editar(need) {
  editandoId.value = need.id
  form.item = need.item
  form.cantidadRequerida = need.cantidadRequerida
  form.unidad = need.unidad || 'unidades'
  form.prioridad = need.prioridad
  form.notas = need.notas || ''
}

function cancelarEdicion() {
  editandoId.value = null
  form.item = ''
  form.cantidadRequerida = ''
  form.unidad = 'unidades'
  form.prioridad = ''
  form.notas = ''
}

async function eliminar(needId) {
  if (!confirm('¿Eliminar esta necesidad?')) return
  error.value = null
  try {
    await needs.eliminarNecesidad(Number(form.shelterId), needId)
    await cargarNecesidades()
  } catch (err) {
    error.value = err?.response?.data?.error ?? 'No se pudo eliminar la necesidad.'
  }
}

function getEstadoColor(estado) {
  switch (estado) {
    case 'pendiente':
      return 'bg-red-100 text-red-800'
    case 'parcialmente_satisfecho':
      return 'bg-yellow-100 text-yellow-800'
    case 'satisfecho':
      return 'bg-green-100 text-green-800'
    default:
      return 'bg-gray-100 text-gray-800'
  }
}

function getPrioridadColor(prioridad) {
  switch (prioridad) {
    case 'critica':
      return 'text-red-600 font-semibold'
    case 'alta':
      return 'text-orange-600 font-semibold'
    case 'media':
      return 'text-yellow-600'
    case 'baja':
      return 'text-gray-600'
    default:
      return 'text-gray-600'
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl animate-fade-in-up">
    <header class="mb-6 text-center">
      <h1 class="inline-flex items-center justify-center gap-2 text-2xl font-bold text-aid-navy">
        Necesidades por refugio
        <BaseTooltip>
          Reporta los insumos que tu refugio necesita. Los despachos se priorizarán según urgencia.
        </BaseTooltip>
      </h1>
    </header>

    <BaseAlert v-if="error" variant="error" class="mb-4">
      {{ error }}
    </BaseAlert>

    <BaseCard padding="lg" shadow="md" class="mb-6">
      <div class="mb-4">
        <label class="block text-sm font-medium text-aid-text">Refugio</label>
        <BaseSelect
          v-model="form.shelterId"
          :options="refugiosDisponibles.map((s) => ({ value: s.id, label: s.nombre }))"
          placeholder="Selecciona un refugio"
          @change="cargarNecesidades"
          :disabled="esEncargadoRefugio"
          required
        />
      </div>

      <div v-if="form.shelterId" class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-3">
          <BaseInput
            v-model="form.item"
            label="Insumo"
            placeholder="Ej. Agua potable, Antibióticos"
            required
          />
          <BaseInput
            v-model="form.cantidadRequerida"
            label="Cantidad requerida"
            type="number"
            step="0.001"
            placeholder="Ej. 50"
            required
          />
          <BaseSelect
            v-model="form.unidad"
            label="Formato / Unidad"
            :options="unidadesComunes"
            placeholder="Selecciona formato"
            required
          />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <BaseSelect
            v-model="form.prioridad"
            label="Prioridad"
            :options="prioridades"
            placeholder="Selecciona prioridad"
            required
          />
          <BaseInput
            v-model="form.notas"
            label="Notas"
            placeholder="Detalles adicionales"
          />
        </div>

        <div class="flex gap-2">
          <BaseButton
            type="submit"
            :loading="enviando"
            @click="onSubmit"
          >
            {{ editandoId ? 'Actualizar necesidad' : 'Agregar necesidad' }}
          </BaseButton>
          <BaseButton
            v-if="editandoId"
            variant="secondary"
            @click="cancelarEdicion"
          >
            Cancelar
          </BaseButton>
        </div>
      </div>
    </BaseCard>

    <BaseCard v-if="form.shelterId && needs.needs.length > 0" padding="lg" shadow="md">
      <h2 class="mb-4 text-lg font-semibold text-aid-navy">Necesidades actuales</h2>
      <div class="space-y-3">
        <div
          v-for="need in needs.needs"
          :key="need.id"
          class="flex items-start justify-between rounded-lg border border-aid-gray-100 bg-white p-4 shadow-sm"
        >
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <span class="font-medium text-aid-navy">{{ need.item }}</span>
              <span :class="['rounded-full px-2 py-0.5 text-xs', getEstadoColor(need.estado)]">
                {{ need.estadoLabel }}
              </span>
            </div>
            <div class="mt-1 text-sm text-aid-text-light">
              <span :class="getPrioridadColor(need.prioridad)">
                {{ need.prioridadLabel }}
              </span>
              · {{ need.cantidadRequerida }} {{ need.unidad || 'unidades' }} {{ need.cantidadRecibida > 0 ? `(recibido: ${need.cantidadRecibida} ${need.unidad || 'unidades'})` : '' }}
            </div>
            <div v-if="need.notas" class="mt-1 text-xs text-aid-text-light">
              {{ need.notas }}
            </div>
            <div class="mt-2 h-2 w-full rounded-full bg-aid-gray-100">
              <div
                class="h-2 rounded-full bg-aid-teal transition-all"
                :style="{ width: `${need.porcentajeSatisfaccion}%` }"
              />
            </div>
            <div class="mt-1 text-xs text-aid-text-light">
              {{ need.porcentajeSatisfaccion.toFixed(0) }}% satisfecho
            </div>
          </div>
          <div class="ml-4 flex gap-2">
            <BaseButton size="sm" variant="secondary" @click="editar(need)">
              Editar
            </BaseButton>
            <BaseButton size="sm" variant="danger" @click="eliminar(need.id)">
              Eliminar
            </BaseButton>
          </div>
        </div>
      </div>
    </BaseCard>

    <BaseCard v-else-if="form.shelterId && needs.needs.length === 0" padding="lg" shadow="md">
      <p class="text-center text-aid-text-light">
        Este refugio no tiene necesidades reportadas. Agrega la primera arriba.
      </p>
    </BaseCard>
  </div>
</template>
