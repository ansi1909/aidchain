<script setup>
import { computed, onMounted, ref } from 'vue'
import { useIdentityStore } from '../stores/identity'
import { useCatalogStore } from '../stores/catalog'
import { buildCanonicalPayload, signMessage } from '../services/crypto'
import api from '../services/api'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseInput from '../components/ui/BaseInput.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseAlert from '../components/ui/BaseAlert.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'

const identity = useIdentityStore()
const catalog = useCatalogStore()

const cargando = ref(false)
const filtroLote = ref('')
const confirmandoId = ref(null)
const error = ref(null)
const resultado = ref(null)

// Solo un coordinador con capacidad ENCARGADO_REFUGIO puede firmar la recepción.
const esEncargado = computed(() => (identity.coordinator?.roles ?? []).includes('encargado_refugio'))
// Un auditor puede VER todos los lotes (solo lectura) para supervisión, pero no firmar.
const esAuditor = computed(() => (identity.coordinator?.roles ?? []).includes('auditor'))
// Refugio asignado al coordinador actual.
const shelterIdAsignado = computed(() => identity.coordinator?.shelterId ?? null)

const pendientes = computed(() => {
  const term = filtroLote.value.trim().toLowerCase()
  if (!term) return catalog.despachosPendientes
  return catalog.despachosPendientes.filter((d) =>
    (d.loteId ?? '').toLowerCase().includes(term),
  )
})

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    // Un encargado solo ve los lotes dirigidos a su refugio asignado.
    // Un auditor (supervisión) ve todos los lotes en tránsito.
    const shelterId = esAuditor.value ? null : shelterIdAsignado.value
    await catalog.cargarDespachosPendientes(shelterId)
  } catch {
    error.value = 'No se pudieron cargar los despachos pendientes. Verifica la conexión con el backend.'
  } finally {
    cargando.value = false
  }
}

onMounted(async () => {
  await identity.init()
  await cargar()
})

function formatearFecha(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString()
}

async function confirmar(despacho) {
  error.value = null
  resultado.value = null

  if (!identity.isReady) {
    error.value = 'Necesitas una identidad criptográfica activa para firmar la recepción.'
    return
  }
  if (!esEncargado.value) {
    error.value = 'Solo un Encargado de refugio puede confirmar la recepción de un lote.'
    return
  }

  confirmandoId.value = despacho.id
  try {
    // El receptor firma EXACTAMENTE el mismo payload canónico del despacho.
    const payload = buildCanonicalPayload({
      tipo: despacho.tipo,
      item: despacho.item,
      cantidad: despacho.cantidad,
      unidad: despacho.unidad,
      beneficiaryToken: despacho.beneficiaryToken,
      shelterId: despacho.shelterId,
      organizationId: despacho.organizationId,
      coordinatorId: despacho.coordinatorId,
      canalOrigen: despacho.canalOrigen,
      loteId: despacho.loteId,
    })
    const firmaDestino = await signMessage(payload)

    const { data } = await api.post(
      `/ledger/dispatches/${encodeURIComponent(despacho.loteId)}/receive`,
      {
        coordinatorId: identity.coordinator.id,
        firmaDestino,
      },
    )

    resultado.value = data
    // Quitar el lote consolidado de la lista de pendientes.
    catalog.despachosPendientes = catalog.despachosPendientes.filter((d) => d.id !== despacho.id)
  } catch (err) {
    error.value = err?.response?.data?.error ?? 'No se pudo confirmar la recepción.'
  } finally {
    confirmandoId.value = null
  }
}
</script>

<template>
  <div class="mx-auto max-w-4xl animate-fade-in-up">
    <header class="mb-6 text-center">
      <h1 class="inline-flex items-center justify-center gap-2 text-2xl font-bold text-aid-navy">
        Confirmar recepción
        <BaseTooltip>
          Firma la recepción de un lote en destino. Esto añade tu <strong>firma de destino</strong>
          y consolida el despacho (firma cruzada de doble vía).
        </BaseTooltip>
      </h1>
    </header>

    <BaseAlert
      v-if="!identity.isReady"
      variant="warning"
      title="Identidad requerida"
      class="mb-6"
    >
      No tienes una identidad activa. Ve a <strong>Identidad</strong> para generar tus llaves.
    </BaseAlert>

    <BaseAlert
      v-else-if="!esEncargado && !esAuditor"
      variant="warning"
      title="Rol no autorizado"
      class="mb-6"
    >
      Solo un <strong>Encargado de refugio</strong> puede confirmar recepciones. Tus roles actuales son
      <strong>{{ (identity.coordinator?.roles ?? []).join(', ') || '—' }}</strong>.
    </BaseAlert>

    <BaseAlert
      v-else-if="esAuditor && !esEncargado"
      variant="info"
      title="Modo supervisión (solo lectura)"
      class="mb-6"
    >
      Como <strong>Auditor</strong> puedes ver todos los lotes en tránsito para supervisión, pero no
      puedes firmar recepciones.
    </BaseAlert>

    <BaseAlert v-if="error" variant="danger" title="Error" class="mb-6">
      {{ error }}
    </BaseAlert>

    <BaseCard
      v-if="resultado"
      padding="lg"
      shadow="md"
      class="mb-6 border-aid-success-100"
    >
      <div class="mb-3 flex items-center gap-2 text-aid-success">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="text-sm font-semibold">Recepción consolidada</span>
      </div>
      <p class="mb-3 text-lg font-bold text-aid-navy">
        Lote {{ resultado.loteId }} — Bloque #{{ resultado.id }}
      </p>
      <dl class="grid gap-2 text-xs sm:grid-cols-2">
        <div class="flex justify-between border-b border-aid-gray-100 py-1.5">
          <dt class="text-aid-text-muted">Estado</dt>
          <dd>
            <StatusBadge variant="success" :label="resultado.estado" />
          </dd>
        </div>
        <div class="flex justify-between border-b border-aid-gray-100 py-1.5">
          <dt class="text-aid-text-muted">Origen</dt>
          <dd class="font-medium text-aid-text">{{ resultado.coordinatorOrigen ?? '—' }}</dd>
        </div>
        <div class="flex justify-between border-b border-aid-gray-100 py-1.5">
          <dt class="text-aid-text-muted">Destino</dt>
          <dd class="font-medium text-aid-text">{{ resultado.coordinatorDestino ?? '—' }}</dd>
        </div>
        <div class="border-b border-aid-gray-100 py-1.5 sm:col-span-2">
          <dt class="mb-1 text-aid-text-muted">Hash del bloque</dt>
          <dd class="break-all font-mono font-medium text-aid-navy">{{ resultado.hashActual }}</dd>
        </div>
      </dl>
    </BaseCard>

    <div class="mb-4 flex items-center justify-between gap-4">
      <div class="flex-1">
        <BaseInput
          v-model="filtroLote"
          label="Buscar lote"
          placeholder="Escanea o escribe el ID de lote (ej. LOTE-...)"
        />
      </div>
      <BaseButton variant="outline" size="sm" :loading="cargando" @click="cargar">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Actualizar
      </BaseButton>
    </div>

    <div v-if="pendientes.length === 0 && !cargando" class="py-12 text-center">
      <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-aid-gray-100">
        <svg class="h-6 w-6 text-aid-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
      </div>
      <p class="text-sm text-aid-text-light">No hay despachos pendientes de recepción.</p>
    </div>

    <div class="grid gap-4">
      <BaseCard
        v-for="d in pendientes"
        :key="d.id"
        padding="md"
        shadow="sm"
        class="border-aid-warning-100"
      >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0 flex-1">
            <div class="mb-1 flex items-center gap-2">
              <StatusBadge variant="warning" label="En tránsito" />
              <span class="truncate font-mono text-xs text-aid-text-muted">{{ d.loteId ?? 'sin lote' }}</span>
            </div>
            <p class="text-base font-semibold text-aid-navy">
              {{ d.item }} · {{ d.cantidad }} {{ d.unidad }}
            </p>
            <p class="mt-0.5 text-xs text-aid-text-light">
              Destino: <span class="font-medium text-aid-text">{{ d.shelterNombre }}</span>
              · Despachado por <span class="font-medium text-aid-text">{{ d.coordinatorOrigenNombre ?? '—' }}</span>
              · {{ formatearFecha(d.createdAt) }}
            </p>
          </div>
          <BaseButton
            v-if="esEncargado"
            variant="primary"
            :loading="confirmandoId === d.id"
            :disabled="!identity.isReady || !d.loteId"
            @click="confirmar(d)"
          >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            {{ confirmandoId === d.id ? 'Firmando…' : 'Confirmar y firmar' }}
          </BaseButton>
          <span v-else-if="esAuditor" class="shrink-0 text-xs font-medium text-aid-text-light">
            Solo lectura
          </span>
        </div>
      </BaseCard>
    </div>
  </div>
</template>
