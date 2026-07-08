<script setup>
import { onMounted, ref, computed, watch } from 'vue'
import QRCode from 'qrcode'
import { useCatalogStore } from '../stores/catalog'
import { useIdentityStore } from '../stores/identity'
import api from '../services/api'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseInput from '../components/ui/BaseInput.vue'
import BaseSelect from '../components/ui/BaseSelect.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseAlert from '../components/ui/BaseAlert.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'

const catalog = useCatalogStore()
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

const shelterId = ref('')
const busqueda = ref('')
const beneficiarios = ref([])
const cargando = ref(false)
const error = ref(null)

const seleccionado = ref(null)
const qrDataUrl = ref(null)
const historial = ref([])
const cargandoHistorial = ref(false)
const tokenCopiado = ref(false)

const beneficiariosFiltrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  if (!q) return beneficiarios.value
  return beneficiarios.value.filter((b) =>
    (b.nombre && b.nombre.toLowerCase().includes(q)) ||
    (b.documento && b.documento.toLowerCase().includes(q)) ||
    b.beneficiaryToken.toLowerCase().includes(q),
  )
})

watch(shelterId, async (id) => {
  seleccionado.value = null
  qrDataUrl.value = null
  historial.value = []
  busqueda.value = ''
  if (id) {
    await cargarBeneficiarios(id)
  } else {
    beneficiarios.value = []
  }
})

onMounted(async () => {
  await identity.init()
  if (catalog.shelters.length === 0) {
    await catalog.cargarCatalogos()
  }
  // Auto-seleccionar el refugio asignado para encargado de refugio
  if (esEncargadoRefugio.value && identity.coordinator?.shelterId) {
    shelterId.value = String(identity.coordinator.shelterId)
    await cargarBeneficiarios(shelterId.value)
  }
})

async function cargarBeneficiarios(id) {
  cargando.value = true
  error.value = null
  try {
    beneficiarios.value = await catalog.cargarBeneficiarios(id)
  } catch {
    error.value = 'No se pudieron cargar los beneficiarios del refugio.'
  } finally {
    cargando.value = false
  }
}

async function seleccionar(beneficiario) {
  seleccionado.value = beneficiario
  tokenCopiado.value = false
  qrDataUrl.value = await QRCode.toDataURL(beneficiario.beneficiaryToken, {
    width: 220,
    margin: 2,
    color: { dark: '#1F4E79', light: '#FFFFFF' },
  })
  await cargarHistorial(beneficiario.beneficiaryToken)
}

async function cargarHistorial(token) {
  cargandoHistorial.value = true
  try {
    const { data } = await api.get(`/ledger/beneficiary/${token}/events`)
    historial.value = data.filter((e) => e.tipo === 'out_beneficiary')
  } catch {
    historial.value = []
  } finally {
    cargandoHistorial.value = false
  }
}

async function copiarToken() {
  if (!seleccionado.value) return
  try {
    await navigator.clipboard.writeText(seleccionado.value.beneficiaryToken)
    tokenCopiado.value = true
    setTimeout(() => (tokenCopiado.value = false), 2000)
  } catch {
    // Ignorar si el navegador no permite clipboard
  }
}

function descargarQr() {
  if (!qrDataUrl.value || !seleccionado.value) return
  const link = document.createElement('a')
  link.href = qrDataUrl.value
  link.download = `aidchain-beneficiario-${seleccionado.value.beneficiaryToken.slice(0, 8)}.png`
  link.click()
}
</script>

<template>
  <div class="mx-auto max-w-5xl animate-fade-in-up">
    <header class="mb-6 text-center">
      <h1 class="inline-flex items-center justify-center gap-2 text-2xl font-bold text-aid-navy">
        Padrón de beneficiarios
        <BaseTooltip>
          Listado de beneficiarios censados por refugio. Consulta su token/QR e historial de entregas.
          Las entregas se registran en la sección Despacho.
        </BaseTooltip>
      </h1>
      <p class="mt-1 text-sm text-aid-text-muted">
        Consulta los beneficiarios censados y su token para la entrega. El registro de la entrega se realiza en
        <strong>Despacho → Entrega a beneficiario</strong>.
      </p>
    </header>

    <BaseCard padding="lg" shadow="md" class="mb-6">
      <div class="grid gap-4 sm:grid-cols-2">
        <BaseSelect
          v-model="shelterId"
          label="Refugio"
          placeholder="Selecciona un refugio"
          :options="refugiosDisponibles"
          value-key="id"
          label-key="nombre"
          :disabled="esEncargadoRefugio"
        />
        <BaseInput
          v-if="shelterId"
          v-model="busqueda"
          label="Buscar beneficiario"
          placeholder="Nombre, documento o token"
        />
      </div>
    </BaseCard>

    <BaseAlert v-if="error" variant="danger" title="Error" class="mb-6">
      {{ error }}
    </BaseAlert>

    <div v-if="shelterId" class="grid gap-6 lg:grid-cols-5">
      <!-- Listado de beneficiarios -->
      <BaseCard padding="lg" shadow="md" class="lg:col-span-3">
        <h2 class="mb-4 text-lg font-semibold text-aid-navy">
          Beneficiarios censados ({{ beneficiariosFiltrados.length }})
        </h2>

        <div v-if="cargando" class="text-sm text-aid-text-muted">Cargando…</div>
        <div v-else-if="beneficiariosFiltrados.length === 0" class="text-sm text-aid-text-muted">
          No hay beneficiarios censados en este refugio.
        </div>
        <div v-else class="max-h-[28rem] space-y-2 overflow-y-auto">
          <button
            v-for="b in beneficiariosFiltrados"
            :key="b.beneficiaryToken"
            type="button"
            class="block w-full rounded-lg border p-3 text-left text-sm transition-colors"
            :class="seleccionado?.beneficiaryToken === b.beneficiaryToken
              ? 'border-aid-teal bg-aid-teal-50'
              : 'border-aid-gray-200 bg-aid-gray-50 hover:border-aid-teal hover:bg-aid-teal-50/40'"
            @click="seleccionar(b)"
          >
            <div class="flex items-center justify-between">
              <span class="font-medium text-aid-navy">{{ b.nombre || 'Sin nombre' }}</span>
              <span class="text-xs text-aid-text-muted">{{ b.documento || 'Sin documento' }}</span>
            </div>
            <div class="mt-1 break-all font-mono text-xs text-aid-text-light">
              {{ b.beneficiaryToken }}
            </div>
          </button>
        </div>
      </BaseCard>

      <!-- Detalle del beneficiario -->
      <div class="space-y-6 lg:col-span-2">
        <BaseCard v-if="seleccionado" padding="lg" shadow="md" class="text-center">
          <h3 class="text-lg font-semibold text-aid-navy">
            {{ seleccionado.nombre || 'Beneficiario sin nombre' }}
          </h3>
          <p class="mt-1 text-xs text-aid-text-muted">{{ seleccionado.documento || 'Sin documento' }}</p>

          <div v-if="qrDataUrl" class="mt-4 inline-block rounded-2xl border-4 border-aid-teal-50 bg-white p-3 shadow-sm">
            <img :src="qrDataUrl" alt="QR del beneficiario" class="h-48 w-48 rounded-lg" />
          </div>

          <div class="mt-4 space-y-2">
            <p class="break-all text-xs text-aid-text-light">
              <span class="font-medium text-aid-text">Token:</span> {{ seleccionado.beneficiaryToken }}
            </p>
            <div class="flex justify-center gap-2">
              <BaseButton variant="outline" size="sm" @click="copiarToken">
                {{ tokenCopiado ? '¡Copiado!' : 'Copiar token' }}
              </BaseButton>
              <BaseButton variant="outline" size="sm" @click="descargarQr">
                Descargar QR
              </BaseButton>
            </div>
          </div>

          <div
            v-if="seleccionado.datosDemograficos"
            class="mt-4 rounded-lg bg-aid-gray-50 p-3 text-left text-xs text-aid-text-light"
          >
            <p v-if="seleccionado.datosDemograficos.personas">
              <strong>Personas a cargo:</strong> {{ seleccionado.datosDemograficos.personas }}
            </p>
            <p v-if="seleccionado.datosDemograficos.telefono">
              <strong>Teléfono:</strong> {{ seleccionado.datosDemograficos.telefono }}
            </p>
            <p v-if="seleccionado.datosDemograficos.notas">
              <strong>Notas:</strong> {{ seleccionado.datosDemograficos.notas }}
            </p>
          </div>
        </BaseCard>

        <BaseCard v-if="seleccionado" padding="lg" shadow="md">
          <h3 class="mb-3 text-sm font-semibold text-aid-navy">
            Entregas recibidas ({{ historial.length }})
          </h3>
          <div v-if="cargandoHistorial" class="text-sm text-aid-text-muted">Cargando…</div>
          <div v-else-if="historial.length === 0" class="text-sm text-aid-text-muted">
            Este beneficiario no tiene entregas registradas.
          </div>
          <div v-else class="max-h-64 space-y-2 overflow-y-auto">
            <div
              v-for="e in historial"
              :key="e.id"
              class="rounded border border-aid-gray-200 bg-aid-gray-50 p-3 text-sm"
            >
              <div class="flex justify-between">
                <span class="font-medium text-aid-navy">{{ e.item }}</span>
                <span class="text-aid-teal">{{ e.cantidad }} {{ e.unidad }}</span>
              </div>
              <div class="text-xs text-aid-text-muted">
                {{ new Date(e.createdAt).toLocaleString() }} · {{ e.estado }}
              </div>
            </div>
          </div>
        </BaseCard>

        <BaseCard v-else padding="lg" shadow="sm" class="text-center text-sm text-aid-text-muted">
          Selecciona un beneficiario de la lista para ver su token, QR e historial de entregas.
        </BaseCard>
      </div>
    </div>
  </div>
</template>
