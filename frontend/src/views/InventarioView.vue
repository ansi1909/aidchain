<script setup>
import { onMounted, computed, ref } from 'vue'
import { useStockStore } from '../stores/stock'
import { useNeedsStore } from '../stores/needs'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseAlert from '../components/ui/BaseAlert.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'

const stock = useStockStore()
const needs = useNeedsStore()

const cargando = ref(false)
const error = ref(null)

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    await Promise.all([stock.cargarDashboardStock(), needs.cargarDashboardNeeds()])
  } catch (err) {
    error.value = err?.response?.data?.error ?? 'No se pudo cargar el inventario.'
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

const num = (v) => Number(v ?? 0)

/**
 * Cruza inventario (ShelterStock) con demanda (ShelterNeed) por refugio + item.
 * Devuelve una lista de refugios, cada uno con sus filas (item) consolidadas.
 */
const refugios = computed(() => {
  const mapa = new Map()

  const asegurarRefugio = (shelter) => {
    if (!mapa.has(shelter.id)) {
      mapa.set(shelter.id, { id: shelter.id, nombre: shelter.nombre, zona: shelter.zona, filas: new Map() })
    }
    return mapa.get(shelter.id)
  }

  const asegurarFila = (refugio, item, unidad) => {
    if (!refugio.filas.has(item)) {
      refugio.filas.set(item, {
        item,
        unidad: unidad ?? '',
        requerido: 0,
        despachado: 0,
        disponible: 0,
        tieneNecesidad: false,
      })
    }
    return refugio.filas.get(item)
  }

  // Necesidades (demanda)
  for (const n of needs.dashboardNeeds) {
    const refugio = asegurarRefugio(n.shelter)
    const fila = asegurarFila(refugio, n.item, null)
    fila.requerido += num(n.cantidadRequerida)
    fila.despachado += num(n.cantidadRecibida)
    fila.tieneNecesidad = true
  }

  // Stock (inventario)
  for (const s of stock.dashboardStock) {
    const refugio = asegurarRefugio(s.shelter)
    const fila = asegurarFila(refugio, s.item, s.unidad)
    fila.disponible += num(s.cantidadDisponible)
    if (s.unidad) fila.unidad = s.unidad
  }

  return Array.from(mapa.values())
    .map((r) => ({ ...r, filas: Array.from(r.filas.values()).sort((a, b) => a.item.localeCompare(b.item)) }))
    .sort((a, b) => a.nombre.localeCompare(b.nombre))
})

const hayDatos = computed(() => refugios.value.length > 0)

/**
 * Estado de cobertura de una fila:
 * - sin_demanda: hay stock pero no se reportó necesidad
 * - cubierto: despachado >= requerido
 * - parcial: 0 < despachado < requerido
 * - pendiente: despachado == 0 con necesidad
 * - excedido: despachado > requerido
 */
function coberturaFila(fila) {
  if (!fila.tieneNecesidad) return { label: 'Sin demanda', clase: 'bg-gray-100 text-gray-700' }
  if (fila.requerido > 0 && fila.despachado > fila.requerido) {
    return { label: 'Excedido', clase: 'bg-purple-100 text-purple-800' }
  }
  if (fila.despachado >= fila.requerido && fila.requerido > 0) {
    return { label: 'Cubierto', clase: 'bg-green-100 text-green-800' }
  }
  if (fila.despachado > 0) return { label: 'Parcial', clase: 'bg-yellow-100 text-yellow-800' }
  return { label: 'Pendiente', clase: 'bg-red-100 text-red-800' }
}

function porcentaje(fila) {
  if (!fila.requerido) return fila.despachado > 0 ? 100 : 0
  return Math.min(100, (fila.despachado / fila.requerido) * 100)
}
</script>

<template>
  <div class="mx-auto max-w-5xl animate-fade-in-up">
    <header class="mb-6 text-center">
      <h1 class="inline-flex items-center justify-center gap-2 text-2xl font-bold text-aid-navy">
        Inventario y cobertura
        <BaseTooltip>
          Cruce entre lo que cada refugio necesita, lo que ya se le ha despachado y el stock disponible actual.
        </BaseTooltip>
      </h1>
      <p class="mt-1 text-sm text-aid-text-light">
        Stock disponible por refugio vs. necesidades reportadas
      </p>
    </header>

    <div class="mb-4 flex justify-end">
      <BaseButton variant="secondary" size="sm" :loading="cargando" @click="cargar">
        Actualizar
      </BaseButton>
    </div>

    <BaseAlert v-if="error" variant="danger" title="Error" class="mb-4">
      {{ error }}
    </BaseAlert>

    <BaseCard v-if="!hayDatos && !cargando" padding="lg" shadow="md">
      <p class="text-center text-aid-text-light">
        Aún no hay inventario ni necesidades registradas. Confirma recepciones de despachos para generar stock.
      </p>
    </BaseCard>

    <div class="space-y-6">
      <BaseCard
        v-for="refugio in refugios"
        :key="refugio.id"
        padding="lg"
        shadow="md"
      >
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold text-aid-navy">{{ refugio.nombre }}</h2>
            <p v-if="refugio.zona" class="text-xs text-aid-text-light">{{ refugio.zona }}</p>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-aid-gray-100 text-left text-xs uppercase tracking-wide text-aid-text-muted">
                <th class="py-2 pr-4">Insumo</th>
                <th class="py-2 pr-4 text-right">Requerido</th>
                <th class="py-2 pr-4 text-right">Despachado</th>
                <th class="py-2 pr-4 text-right">Stock disponible</th>
                <th class="py-2 pr-4">Cobertura</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="fila in refugio.filas"
                :key="fila.item"
                class="border-b border-aid-gray-50"
              >
                <td class="py-2 pr-4 font-medium text-aid-navy">{{ fila.item }}</td>
                <td class="py-2 pr-4 text-right text-aid-text">
                  {{ fila.tieneNecesidad ? `${fila.requerido} ${fila.unidad}` : '—' }}
                </td>
                <td class="py-2 pr-4 text-right text-aid-text">{{ fila.despachado }} {{ fila.unidad }}</td>
                <td class="py-2 pr-4 text-right font-semibold text-aid-navy">
                  {{ fila.disponible }} {{ fila.unidad }}
                </td>
                <td class="py-2 pr-4">
                  <div class="flex items-center gap-2">
                    <span :class="['rounded-full px-2 py-0.5 text-xs', coberturaFila(fila).clase]">
                      {{ coberturaFila(fila).label }}
                    </span>
                    <div v-if="fila.tieneNecesidad" class="h-1.5 w-16 rounded-full bg-aid-gray-100">
                      <div
                        class="h-1.5 rounded-full bg-aid-teal transition-all"
                        :style="{ width: `${porcentaje(fila)}%` }"
                      />
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>
    </div>
  </div>
</template>
