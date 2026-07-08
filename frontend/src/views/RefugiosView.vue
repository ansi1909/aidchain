<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import { useCatalogStore } from '../stores/catalog'
import { useIdentityStore } from '../stores/identity'
import { buildCanonicalPayload, signMessage } from '../services/crypto'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseInput from '../components/ui/BaseInput.vue'
import BaseSelect from '../components/ui/BaseSelect.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseAlert from '../components/ui/BaseAlert.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'

const catalog = useCatalogStore()
const identity = useIdentityStore()

const esAdmin = computed(() => (identity.coordinator?.roles ?? []).includes('admin'))

const refugios = ref([])
const cargando = ref(false)
const error = ref(null)
const busqueda = ref('')
const mostrarInactivos = ref(true)

// Estado del modal de creación/edición
const modalAbierto = ref(false)
const guardando = ref(false)
const formError = ref(null)
const editandoId = ref(null)
const form = reactive({
  nombre: '',
  zona: '',
  latitud: '',
  longitud: '',
  capacidadCensada: '',
  organizationId: '',
})

const refugiosFiltrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return refugios.value.filter((r) => {
    if (!mostrarInactivos.value && !r.activo) return false
    if (!q) return true
    return (
      r.nombre.toLowerCase().includes(q) ||
      r.zona.toLowerCase().includes(q) ||
      (r.organizationNombre && r.organizationNombre.toLowerCase().includes(q))
    )
  })
})

onMounted(async () => {
  await identity.init()
  if (catalog.organizations.length === 0) {
    await catalog.cargarCatalogos()
  }
  await cargar()
})

async function cargar() {
  cargando.value = true
  error.value = null
  try {
    refugios.value = await catalog.cargarRefugios(false)
  } catch {
    error.value = 'No se pudieron cargar los refugios.'
  } finally {
    cargando.value = false
  }
}

function abrirCrear() {
  editandoId.value = null
  formError.value = null
  Object.assign(form, {
    nombre: '',
    zona: '',
    latitud: '',
    longitud: '',
    capacidadCensada: '',
    organizationId: '',
  })
  modalAbierto.value = true
}

function abrirEditar(refugio) {
  editandoId.value = refugio.id
  formError.value = null
  Object.assign(form, {
    nombre: refugio.nombre,
    zona: refugio.zona,
    latitud: refugio.latitud ?? '',
    longitud: refugio.longitud ?? '',
    capacidadCensada: refugio.capacidadCensada ?? '',
    organizationId: refugio.organizationId ?? '',
  })
  modalAbierto.value = true
}

function cerrarModal() {
  modalAbierto.value = false
}

async function guardar() {
  formError.value = null

  if (!form.nombre.trim()) {
    formError.value = 'El nombre es obligatorio.'
    return
  }
  if (!form.zona.trim()) {
    formError.value = 'La zona es obligatoria.'
    return
  }

  if (!identity.isReady) {
    formError.value = 'Necesitas registrar tu identidad criptográfica antes de firmar acciones administrativas.'
    return
  }

  guardando.value = true
  try {
    const payload = {
      nombre: form.nombre.trim(),
      zona: form.zona.trim(),
      latitud: form.latitud !== '' ? String(form.latitud) : null,
      longitud: form.longitud !== '' ? String(form.longitud) : null,
      capacidadCensada: form.capacidadCensada !== '' ? Number(form.capacidadCensada) : null,
      organizationId: form.organizationId !== '' ? Number(form.organizationId) : null,
    }

    // Firmar la acción para trazabilidad
    const tipo = editandoId.value ? 'config_shelter_update' : 'config_shelter_create'
    const datosConfig = editandoId.value
      ? { shelter_id: editandoId.value, estado_nuevo: payload }
      : { ...payload }

    const canonical = buildCanonicalPayload({
      tipo,
      item: null,
      cantidad: null,
      unidad: null,
      beneficiaryToken: null,
      shelterId: null,
      organizationId: null,
      coordinatorId: identity.coordinator.id,
      canalOrigen: 'web',
      loteId: null,
      datosConfiguracion: datosConfig,
    })

    const firma = await signMessage(canonical)

    const payloadConFirma = {
      ...payload,
      coordinatorId: identity.coordinator.id,
      firma,
    }

    if (editandoId.value) {
      await catalog.actualizarRefugio(editandoId.value, payloadConFirma)
    } else {
      await catalog.crearRefugio(payloadConFirma)
    }

    modalAbierto.value = false
    await cargar()
  } catch (err) {
    formError.value = err?.response?.data?.error || 'No se pudo guardar el refugio.'
  } finally {
    guardando.value = false
  }
}

async function alternarEstado(refugio) {
  const accion = refugio.activo ? 'inactivar' : 'activar'
  if (!confirm(`¿Seguro que deseas ${accion} el refugio "${refugio.nombre}"?`)) return

  if (!identity.isReady) {
    error.value = 'Necesitas registrar tu identidad criptográfica antes de firmar acciones administrativas.'
    return
  }

  try {
    const nuevoActivo = !refugio.activo

    // Firmar la acción para trazabilidad
    const canonical = buildCanonicalPayload({
      tipo: 'config_shelter_inactivate',
      item: null,
      cantidad: null,
      unidad: null,
      beneficiaryToken: null,
      shelterId: null,
      organizationId: null,
      coordinatorId: identity.coordinator.id,
      canalOrigen: 'web',
      loteId: null,
      datosConfiguracion: {
        shelter_id: refugio.id,
        activo_anterior: refugio.activo,
        activo_nuevo: nuevoActivo,
      },
    })

    const firma = await signMessage(canonical)

    await catalog.cambiarEstadoRefugio(refugio.id, nuevoActivo, {
      coordinatorId: identity.coordinator.id,
      firma,
    })
    await cargar()
  } catch (err) {
    error.value = err?.response?.data?.error || `No se pudo ${accion} el refugio.`
  }
}
</script>

<template>
  <div class="mx-auto max-w-6xl animate-fade-in-up">
    <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <h1 class="inline-flex items-center gap-2 text-2xl font-bold text-aid-navy">
        Refugios
        <BaseTooltip>
          Gestión de refugios y centros de acopio. Inactivar conserva la historia (censo y ledger) y solo lo oculta de los selectores operativos.
        </BaseTooltip>
      </h1>
      <BaseButton v-if="esAdmin" variant="primary" @click="abrirCrear">
        Nuevo refugio
      </BaseButton>
    </header>

    <BaseAlert v-if="!esAdmin" variant="warning" title="Rol requerido">
      La gestión de refugios está disponible solo para coordinadores con rol <strong>Administrador</strong>.
    </BaseAlert>

    <template v-else>
      <BaseCard padding="md" shadow="sm" class="mb-4">
        <div class="flex flex-wrap items-center gap-4">
          <BaseInput
            v-model="busqueda"
            class="flex-1"
            placeholder="Buscar por nombre, zona u organización"
          />
          <label class="flex cursor-pointer items-center gap-2 text-sm text-aid-text">
            <input
              type="checkbox"
              v-model="mostrarInactivos"
              class="h-4 w-4 rounded border-aid-gray-300 text-aid-teal focus:ring-aid-teal/50"
            />
            Mostrar inactivos
          </label>
        </div>
      </BaseCard>

      <BaseAlert v-if="error" variant="danger" title="Error" class="mb-4">
        {{ error }}
      </BaseAlert>

      <BaseCard padding="none" shadow="md">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-aid-gray-100 text-left text-xs uppercase tracking-wider text-aid-text-muted">
                <th class="px-4 py-3 font-medium">Nombre</th>
                <th class="px-4 py-3 font-medium">Zona</th>
                <th class="px-4 py-3 font-medium">Capacidad</th>
                <th class="px-4 py-3 font-medium">Organización</th>
                <th class="px-4 py-3 font-medium">Estado</th>
                <th class="px-4 py-3 text-right font-medium">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="cargando">
                <td colspan="6" class="px-4 py-6 text-center text-aid-text-muted">Cargando…</td>
              </tr>
              <tr v-else-if="refugiosFiltrados.length === 0">
                <td colspan="6" class="px-4 py-6 text-center text-aid-text-muted">
                  No hay refugios que coincidan.
                </td>
              </tr>
              <tr
                v-for="r in refugiosFiltrados"
                :key="r.id"
                class="border-b border-aid-gray-50 transition-colors hover:bg-aid-gray-50"
                :class="!r.activo ? 'opacity-60' : ''"
              >
                <td class="px-4 py-3 font-medium text-aid-navy">{{ r.nombre }}</td>
                <td class="px-4 py-3 text-aid-text-light">{{ r.zona }}</td>
                <td class="px-4 py-3 text-aid-text-light">{{ r.capacidadCensada ?? '—' }}</td>
                <td class="px-4 py-3 text-aid-text-light">{{ r.organizationNombre ?? '—' }}</td>
                <td class="px-4 py-3">
                  <StatusBadge
                    :variant="r.activo ? 'success' : 'default'"
                    :label="r.activo ? 'Activo' : 'Inactivo'"
                    dot
                  />
                </td>
                <td class="px-4 py-3">
                  <div class="flex justify-end gap-2">
                    <BaseButton variant="outline" size="sm" @click="abrirEditar(r)">
                      Editar
                    </BaseButton>
                    <BaseButton
                      :variant="r.activo ? 'danger' : 'success'"
                      size="sm"
                      @click="alternarEstado(r)"
                    >
                      {{ r.activo ? 'Inactivar' : 'Activar' }}
                    </BaseButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>
    </template>

    <!-- Modal crear/editar -->
    <div
      v-if="modalAbierto"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
      @click.self="cerrarModal"
    >
      <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
        <h2 class="mb-4 text-lg font-semibold text-aid-navy">
          {{ editandoId ? 'Editar refugio' : 'Nuevo refugio' }}
        </h2>

        <form class="space-y-4" @submit.prevent="guardar">
          <BaseInput v-model="form.nombre" label="Nombre" placeholder="Ej. Refugio Central Zona A" required />
          <BaseInput v-model="form.zona" label="Zona" placeholder="Ej. Zona A" required />

          <div class="grid gap-4 sm:grid-cols-2">
            <BaseInput v-model="form.latitud" label="Latitud" placeholder="10.4806000" />
            <BaseInput v-model="form.longitud" label="Longitud" placeholder="-66.9036000" />
          </div>

          <div class="grid gap-4 sm:grid-cols-2">
            <BaseInput
              v-model="form.capacidadCensada"
              type="number"
              min="0"
              label="Capacidad censada"
              placeholder="250"
            />
            <BaseSelect
              v-model="form.organizationId"
              label="Organización (opcional)"
              placeholder="Sin organización"
              :options="catalog.organizations"
              value-key="id"
              label-key="nombre"
            />
          </div>

          <BaseAlert v-if="formError" variant="danger" title="Error">
            {{ formError }}
          </BaseAlert>

          <div class="flex justify-end gap-2 pt-2">
            <BaseButton type="button" variant="outline" @click="cerrarModal">Cancelar</BaseButton>
            <BaseButton type="submit" variant="primary" :loading="guardando">
              {{ editandoId ? 'Guardar cambios' : 'Crear refugio' }}
            </BaseButton>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
