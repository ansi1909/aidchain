<script setup>
import { onMounted, reactive, ref, computed, watch } from 'vue'
import QRCode from 'qrcode'
import { useIdentityStore } from '../stores/identity'
import { useCatalogStore } from '../stores/catalog'
import { useNeedsStore } from '../stores/needs'
import { buildCanonicalPayload, signMessage } from '../services/crypto'
import api from '../services/api'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseInput from '../components/ui/BaseInput.vue'
import BaseSelect from '../components/ui/BaseSelect.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseAlert from '../components/ui/BaseAlert.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'

const identity = useIdentityStore()
const catalog = useCatalogStore()
const needs = useNeedsStore()

// La recepción (IN_RECEPTION) se registra en su propia vista con firma cruzada.
const tipos = [
  { value: 'out_dispatch', label: 'Salida / Despacho (logística al refugio)' },
  { value: 'out_beneficiary', label: 'Entrega a beneficiario (última milla)' },
  { value: 'in_stock', label: 'Ingreso a bodega' },
]

const form = reactive({
  tipo: '',
  shelterId: '',
  item: '',
  cantidad: '',
  unidad: 'cajas',
  beneficiaryToken: '',
  loteId: '',
})

const enviando = ref(false)
const resultado = ref(null)
const error = ref(null)
const loteQrDataUrl = ref(null)

// Identificador de lote legible y único para el QR físico (Fase 4).
function generarLoteId() {
  const stamp = new Date().toISOString().slice(0, 10).replace(/-/g, '')
  const rand = Math.random().toString(36).slice(2, 6).toUpperCase()
  return `LOTE-${stamp}-${rand}`
}

const shelterSeleccionado = computed(() =>
  catalog.shelters.find((s) => s.id === Number(form.shelterId)),
)

// Originar despachos/ingresos requiere la capacidad DESPACHADOR.
const esDespachador = computed(() => (identity.coordinator?.roles ?? []).includes('despachador'))

// La entrega individual (última milla) la realiza el refugio: exige ENCARGADO_REFUGIO.
const esEncargadoRefugio = computed(() => (identity.coordinator?.roles ?? []).includes('encargado_refugio'))

// Rol requerido según la etapa del flujo de dos etapas (Fase 8).
// Sin tipo seleccionado, no se habilita el registro (obliga a elegir conscientemente).
const puedeRegistrar = computed(() => {
  if (form.tipo === 'out_beneficiary') return esEncargadoRefugio.value
  if (form.tipo === 'out_dispatch' || form.tipo === 'in_stock') return esDespachador.value
  return false
})

// El aviso de rol solo aparece cuando ya se eligió un tipo que no corresponde al rol.
const rolNoAutorizado = computed(() => form.tipo !== '' && !puedeRegistrar.value)

// Necesidades del refugio destino (Fase 7b)
const necesidadesRefugio = computed(() => needs.needs.filter((n) => n.estado === 'pendiente'))

// Necesidad que coincide con el insumo escrito (para el aviso de cobertura, Fase 8)
const necesidadItem = computed(() => {
  const item = form.item.trim().toLowerCase()
  if (!item) return null
  return needs.needs.find((n) => n.item.trim().toLowerCase() === item) ?? null
})

// Aviso NO bloqueante de cobertura cuando se despacha (logística) contra necesidades
const avisoDespacho = computed(() => {
  if (form.tipo !== 'out_dispatch' || !form.shelterId || !form.item.trim()) return null

  const need = necesidadItem.value
  const cantidad = Number(form.cantidad) || 0

  if (!need) {
    return {
      variant: 'info',
      title: 'Sin necesidad reportada',
      mensaje: 'Este insumo no tiene una necesidad reportada en este refugio. Verifica si es el destino correcto o si otro refugio lo requiere con más urgencia.',
    }
  }

  const requerido = Number(need.cantidadRequerida) || 0
  const despachado = Number(need.cantidadRecibida) || 0
  const pendiente = Math.max(0, requerido - despachado)
  const base = `Requerido: ${requerido} · Despachado: ${despachado} · Pendiente: ${pendiente} ${need.unidad ?? ''}`.trim()

  if (pendiente === 0) {
    return {
      variant: 'warning',
      title: 'Necesidad ya cubierta',
      mensaje: `${base}. Este refugio ya cubrió su necesidad de "${need.item}". Puedes continuar, pero considera despachar a un refugio con mayor necesidad.`,
    }
  }

  if (cantidad > pendiente) {
    return {
      variant: 'warning',
      title: 'Cantidad supera lo pendiente',
      mensaje: `${base}. Estás despachando ${cantidad}, que excede lo pendiente (${pendiente}). Puedes continuar si es intencional (stock de reserva).`,
    }
  }

  return {
    variant: 'info',
    title: 'Cobertura de la necesidad',
    mensaje: base + '.',
  }
})

onMounted(async () => {
  await identity.init()
  if (catalog.shelters.length === 0) {
    await catalog.cargarCatalogos()
  }
})

// Cargar necesidades del refugio cuando cambie la selección (solo para despachos)
watch(
  () => form.shelterId,
  async (newShelterId) => {
    if (newShelterId && form.tipo === 'out_dispatch') {
      try {
        await needs.cargarNecesidadesShelter(Number(newShelterId))
      } catch {
        // Silencioso: si falla, simplemente no mostramos necesidades
      }
    }
  },
)

// Cargar necesidades cuando cambie el tipo de evento
watch(
  () => form.tipo,
  async (newTipo) => {
    if (newTipo === 'out_dispatch' && form.shelterId) {
      try {
        await needs.cargarNecesidadesShelter(Number(form.shelterId))
      } catch {
        // Silencioso
      }
    } else {
      needs.needs = []
    }
  },
)

async function onSubmit() {
  error.value = null
  resultado.value = null

  if (!identity.isReady) {
    error.value = 'Necesitas registrar tu identidad criptográfica antes de firmar eventos.'
    return
  }

  if (!form.tipo) {
    error.value = 'Selecciona un tipo de evento.'
    return
  }

  if (!puedeRegistrar.value) {
    error.value = form.tipo === 'out_beneficiary'
      ? 'Solo un coordinador con rol Encargado de refugio puede registrar entregas a beneficiarios (última milla).'
      : 'Solo un coordinador con rol Despachador puede originar despachos logísticos o ingresos a bodega.'
    return
  }

  // Validaciones según tipo de evento
  if (form.tipo === 'out_dispatch') {
    if (form.beneficiaryToken.trim()) {
      error.value = 'OUT_DISPATCH es para despachos logísticos al refugio (sin beneficiario específico). Usa OUT_BENEFICIARY para entregas individuales.'
      return
    }
  } else if (form.tipo === 'out_beneficiary') {
    if (!form.beneficiaryToken.trim()) {
      error.value = 'OUT_BENEFICIARY requiere un token de beneficiario para la entrega individual.'
      return
    }
  }

  enviando.value = true
  loteQrDataUrl.value = null
  try {
    // Un despacho (OUT_DISPATCH) siempre viaja con un lote para poder
    // confirmarse en destino (firma cruzada, Fase 4). Si el usuario no lo
    // indica, se genera automáticamente antes de firmar.
    let loteId = form.loteId.trim() || null
    if (form.tipo === 'out_dispatch' && !loteId) {
      loteId = generarLoteId()
    }

    const evento = {
      tipo: form.tipo,
      item: form.item.trim(),
      cantidad: String(form.cantidad),
      unidad: form.unidad.trim(),
      beneficiaryToken: form.beneficiaryToken.trim() || null,
      shelterId: Number(form.shelterId),
      organizationId: identity.coordinator.organizationId,
      coordinatorId: identity.coordinator.id,
      canalOrigen: 'app_terreno',
      loteId,
    }

    const payload = buildCanonicalPayload(evento)
    const firmaOrigen = await signMessage(payload)

    const { data } = await api.post('/ledger/events', {
      tipo: evento.tipo,
      item: evento.item,
      cantidad: evento.cantidad,
      unidad: evento.unidad,
      shelterId: evento.shelterId,
      coordinatorId: evento.coordinatorId,
      beneficiaryToken: evento.beneficiaryToken,
      canalOrigen: evento.canalOrigen,
      loteId: evento.loteId,
      firmaOrigen,
    })

    resultado.value = data

    // Para un despacho, generamos el QR del lote: el destino lo escanea para
    // confirmar la recepción y consolidar el evento (firma cruzada).
    if (data.loteId) {
      loteQrDataUrl.value = await QRCode.toDataURL(data.loteId, {
        width: 220,
        margin: 2,
        color: { dark: '#1F4E79', light: '#FFFFFF' },
      })
    }

    form.item = ''
    form.cantidad = ''
    form.beneficiaryToken = ''
    form.loteId = ''
  } catch (err) {
    // Capturar mensaje específico del backend si está disponible
    const errorMessage = err?.response?.data?.error || err?.message || 'No se pudo registrar el evento.'
    error.value = errorMessage
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-3xl animate-fade-in-up">
    <header class="mb-6 text-center">
      <h1 class="inline-flex items-center justify-center gap-2 text-2xl font-bold text-aid-navy">
        Registrar evento firmado
        <BaseTooltip>
          El evento se firma con tu llave privada local y se encadena de forma inmutable en el ledger.
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
      v-else-if="rolNoAutorizado"
      variant="warning"
      title="Rol no autorizado"
      class="mb-6"
    >
      <template v-if="form.tipo === 'out_beneficiary'">
        Solo un coordinador con rol <strong>Encargado de refugio</strong> puede registrar entregas a beneficiarios (última milla).
      </template>
      <template v-else>
        Solo un coordinador con rol <strong>Despachador</strong> puede originar despachos logísticos o ingresos a bodega.
      </template>
    </BaseAlert>

    <div class="grid gap-6 lg:grid-cols-5">
      <BaseCard padding="lg" shadow="md" class="lg:col-span-3">
        <form class="space-y-5" @submit.prevent="onSubmit">
          <BaseSelect
            v-model="form.tipo"
            label="Tipo de evento"
            :options="tipos"
            required
          />

          <BaseSelect
            v-model="form.shelterId"
            label="Refugio / Centro"
            placeholder="Selecciona un refugio"
            :options="catalog.shelters"
            value-key="id"
            label-key="nombre"
            required
          />

          <!-- Necesidades del refugio destino (Fase 7b) -->
          <div
            v-if="form.tipo === 'out_dispatch' && necesidadesRefugio.length > 0"
            class="rounded-lg border border-aid-teal-100 bg-aid-teal-50 p-4"
          >
            <h3 class="mb-2 text-sm font-semibold text-aid-navy">Necesidades pendientes del refugio</h3>
            <div class="space-y-2">
              <div
                v-for="need in necesidadesRefugio"
                :key="need.id"
                class="flex items-center justify-between rounded bg-white p-2 text-sm"
              >
                <div>
                  <span class="font-medium text-aid-navy">{{ need.item }}</span>
                  <span class="ml-2 text-aid-text-light">({{ need.cantidadRequerida }} {{ need.unidad || '' }} · {{ need.prioridadLabel }})</span>
                </div>
                <button
                  type="button"
                  class="text-aid-teal hover:text-aid-teal-700"
                  @click="form.item = need.item; if (need.unidad) form.unidad = need.unidad"
                >
                  Usar
                </button>
              </div>
            </div>
          </div>

          <div class="grid gap-5 sm:grid-cols-3">
            <BaseInput
              v-model="form.item"
              class="sm:col-span-2"
              label="Insumo"
              placeholder="Ej. Agua potable"
              required
            />
            <BaseInput
              v-model="form.cantidad"
              type="number"
              min="0"
              step="any"
              label="Cantidad"
              placeholder="20"
              required
            />
          </div>

          <div class="grid gap-5 sm:grid-cols-2">
            <BaseInput
              v-model="form.unidad"
              label="Unidad"
              placeholder="cajas"
              required
            />
            <BaseInput
              v-if="form.tipo === 'out_dispatch'"
              v-model="form.loteId"
              label="Lote"
              placeholder="LOTE-001"
            />
          </div>

          <BaseInput
            v-if="form.tipo === 'out_beneficiary'"
            v-model="form.beneficiaryToken"
            label="Token de beneficiario"
            placeholder="Token del beneficiario receptor (QR)"
            required
          />

          <BaseAlert v-if="form.tipo === 'out_dispatch'" variant="info" title="Despacho logístico">
            Este despacho abastece el refugio (sin beneficiario específico). Para entregas individuales, usa "Entrega a beneficiario".
          </BaseAlert>

          <BaseAlert
            v-if="avisoDespacho"
            :variant="avisoDespacho.variant"
            :title="avisoDespacho.title"
          >
            {{ avisoDespacho.mensaje }}
          </BaseAlert>

          <BaseAlert v-if="form.tipo === 'out_beneficiary'" variant="info" title="Entrega individual">
            Esta entrega se registra contra el stock del refugio y se valida contra umbrales de doble cobro.
          </BaseAlert>

          <BaseAlert v-if="error" variant="danger" title="Error al registrar">
            {{ error }}
          </BaseAlert>

          <BaseButton
            type="submit"
            variant="primary"
            size="lg"
            block
            :loading="enviando"
            :disabled="!identity.isReady || !puedeRegistrar || !form.shelterId || !form.item || !form.cantidad"
          >
            {{ enviando ? 'Firmando y registrando…' : 'Firmar y registrar evento' }}
          </BaseButton>
        </form>
      </BaseCard>

      <div class="space-y-6 lg:col-span-2">
        <BaseCard padding="md" shadow="sm" class="bg-gradient-to-br from-aid-navy to-aid-navy-600 text-white">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/20">
              <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2v4a2 2 0 01-2 2h-1.5a2 2 0 01-2-2V9a2 2 0 012-2H15zM9 7a2 2 0 012 2v4a2 2 0 01-2 2H7.5a2 2 0 01-2-2V9a2 2 0 012-2H9z" />
              </svg>
            </div>
            <div>
              <p class="text-xs font-medium uppercase tracking-wider text-aid-teal-200">Resumen</p>
              <p class="text-sm font-semibold">{{ tipos.find(t => t.value === form.tipo)?.label }}</p>
            </div>
          </div>
          <div class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-aid-teal-100">Insumo</span>
              <span class="font-medium">{{ form.item || '—' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-aid-teal-100">Cantidad</span>
              <span class="font-medium">{{ form.cantidad ? `${form.cantidad} ${form.unidad}` : '—' }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-aid-teal-100">Destino</span>
              <span class="font-medium">{{ shelterSeleccionado?.nombre || '—' }}</span>
            </div>
          </div>
        </BaseCard>

        <BaseCard
          v-if="resultado"
          padding="md"
          shadow="md"
          class="border-aid-success-100"
        >
          <div class="mb-3 flex items-center gap-2 text-aid-success">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm font-semibold">Evento registrado</span>
          </div>
          <p class="mb-3 text-2xl font-bold text-aid-navy">Bloque #{{ resultado.id }}</p>

          <div v-if="loteQrDataUrl" class="mb-4 text-center">
            <p class="mb-2 text-xs text-aid-text-muted">Escanea este QR en destino para confirmar la recepción</p>
            <div class="inline-block rounded-xl border-4 border-aid-teal-50 bg-white p-2 shadow-sm">
              <img :src="loteQrDataUrl" alt="QR del lote" class="h-40 w-40 rounded-lg" />
            </div>
            <p class="mt-2 break-all font-mono text-xs font-medium text-aid-navy">{{ resultado.loteId }}</p>
          </div>

          <dl class="space-y-2 text-xs">
            <div class="flex justify-between border-b border-aid-gray-100 py-1.5">
              <dt class="text-aid-text-muted">Estado</dt>
              <dd class="font-medium text-aid-text">{{ resultado.estado }}</dd>
            </div>
            <div class="border-b border-aid-gray-100 py-1.5">
              <dt class="mb-1 text-aid-text-muted">Hash</dt>
              <dd class="break-all font-mono font-medium text-aid-navy">{{ resultado.hashActual }}</dd>
            </div>
            <div class="py-1.5">
              <dt class="mb-1 text-aid-text-muted">Hash anterior</dt>
              <dd class="break-all font-mono font-medium text-aid-text-light">
                {{ resultado.hashAnterior ?? '— (bloque génesis)' }}
              </dd>
            </div>
          </dl>
        </BaseCard>
      </div>
    </div>
  </div>
</template>
