<script setup>
import { onMounted, reactive, computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useIdentityStore } from '../stores/identity'
import { useCatalogStore } from '../stores/catalog'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseInput from '../components/ui/BaseInput.vue'
import BaseSelect from '../components/ui/BaseSelect.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseAlert from '../components/ui/BaseAlert.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'

const identity = useIdentityStore()
const catalog = useCatalogStore()
const route = useRoute()

const modo = ref(route.query.modo === 'recuperar' ? 'recuperar' : 'registro') // 'registro' o 'recuperar'

const roles = [
  { value: 'despachador', label: 'Despachador', hint: 'Origina salidas de un centro de acopio (OUT_DISPATCH).' },
  { value: 'encargado_refugio', label: 'Encargado de refugio', hint: 'Confirma y firma la recepción de lotes en destino.' },
  { value: 'auditor', label: 'Auditor', hint: 'Perfil de lectura, auditoría y liberación de bloqueos.' },
  { value: 'admin', label: 'Administrador', hint: 'Configuración del sistema: gestión de refugios, catálogos y umbrales.' },
]

const form = reactive({
  nombre: '',
  documento: '',
  roles: ['despachador'],
  organizationId: '',
  shelterId: '',
})

const documentoError = computed(() => {
  if (!form.documento) return null

  // Quitar espacios en blanco al principio y al final
  const doc = form.documento.trim().toUpperCase()

  // Validar cédula venezolana: V-XXXXXX o E-XXXXXX (6-8 dígitos, guion opcional)
  if (/^[VE]-?\d{6,8}$/.test(doc)) return null

  // Validar pasaporte alfanumérico (6-9 caracteres, solo letras y números)
  if (/^[A-Z0-9]{6,9}$/.test(doc)) return null

  return 'Formato inválido. Use V-XXXXXX o E-XXXXXX (6-8 dígitos, guion opcional), o pasaporte alfanumérico (6-9 caracteres).'
})

function toggleRol(value) {
  const i = form.roles.indexOf(value)
  if (i === -1) form.roles.push(value)
  else form.roles.splice(i, 1)
}

function cambiarModo(nuevoModo) {
  modo.value = nuevoModo
  // Limpiar formulario al cambiar de modo
  form.nombre = ''
  form.documento = ''
  form.roles = ['despachador']
  form.organizationId = ''
  form.shelterId = ''
  identity.error = null
}

async function onRecover() {
  if (!form.documento) {
    identity.error = 'El documento es requerido para recuperar la identidad.'
    return
  }

  const success = await identity.recover({ documento: form.documento.trim() })
  if (success) {
    form.documento = ''
  }
}

const orgSeleccionada = computed(() =>
  catalog.organizations.find((o) => o.id === Number(form.organizationId)),
)

// Los refugios son lugares físicos compartidos: no se filtran por organización.
// Un coordinador de la org X puede estar asignado a un refugio gestionado por la org Y.
const filteredShelters = computed(() => catalog.shelters)

// Enriched options para el select: incluye la organización gestora si existe.
const shelterOptions = computed(() =>
  catalog.shelters.map((s) => ({
    ...s,
    label: s.organizationNombre
      ? `${s.nombre} (gestionado por ${s.organizationNombre})`
      : s.nombre,
  })),
)

onMounted(async () => {
  await identity.init()
  if (catalog.organizations.length === 0) {
    await catalog.cargarCatalogos()
  }
})

async function onSubmit() {
  await identity.register({
    nombre: form.nombre.trim(),
    documento: form.documento.trim(),
    roles: form.roles,
    organizationId: Number(form.organizationId),
    shelterId: form.shelterId ? Number(form.shelterId) : null,
  })
}

const rolesLabel = computed(() =>
  (identity.coordinator?.roles ?? [])
    .map((r) => roles.find((o) => o.value === r)?.label ?? r)
    .join(', '),
)

async function onReset() {
  if (confirm('Esto borrará tu llave privada local y tu identidad. ¿Continuar?')) {
    await identity.reset()
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl animate-fade-in-up">
    <header class="mb-6 text-center">
      <h1 class="inline-flex items-center justify-center gap-2 text-2xl font-bold text-aid-navy">
        {{ modo === 'registro' ? 'Registrar Identidad' : 'Recuperar Identidad' }}
        <BaseTooltip>
          {{ modo === 'registro' 
            ? 'Genera un par de llaves ECDSA P-256 en este dispositivo. La llave privada se guarda' 
            : 'Recupera tu identidad en un nuevo dispositivo generando una nueva llave.' }}
        </BaseTooltip>
      </h1>
      <div class="mt-4 flex justify-center gap-2">
        <BaseButton
          :variant="modo === 'registro' ? 'primary' : 'outline'"
          size="sm"
          @click="cambiarModo('registro')"
        >
          Registro nuevo
        </BaseButton>
        <BaseButton
          :variant="modo === 'recuperar' ? 'primary' : 'outline'"
          size="sm"
          @click="cambiarModo('recuperar')"
        >
          Recuperar identidad
        </BaseButton>
      </div>
    </header>

    <BaseCard v-if="identity.isReady" padding="lg" shadow="md" class="border-aid-teal-100">
      <div class="flex items-start gap-4">
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-aid-teal-50">
          <svg class="h-6 w-6 text-aid-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="flex-1">
          <h2 class="text-lg font-semibold text-aid-navy">Identidad activa en este dispositivo</h2>
          <p class="mt-1 text-sm text-aid-text-light">
            Puedes firmar eventos de despacho y recepción con esta identidad.
          </p>

          <dl class="mt-4 grid gap-2 text-sm">
            <div class="flex justify-between border-b border-aid-gray-100 py-2">
              <dt class="text-aid-text-muted">Coordinador</dt>
              <dd class="font-medium text-aid-text">{{ identity.coordinator.nombre }}</dd>
            </div>
            <div class="flex justify-between border-b border-aid-gray-100 py-2">
              <dt class="text-aid-text-muted">Roles</dt>
              <dd class="font-medium text-aid-text">{{ rolesLabel || '—' }}</dd>
            </div>
            <div class="flex justify-between py-2">
              <dt class="text-aid-text-muted">ID</dt>
              <dd class="font-medium text-aid-text">#{{ identity.coordinator.id }}</dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="mt-6 flex justify-end">
        <BaseButton variant="danger" size="sm" @click="onReset">
          Borrar identidad local
        </BaseButton>
      </div>
    </BaseCard>

    <BaseCard v-else padding="lg" shadow="md">
      <!-- Formulario de recuperación -->
      <form v-if="modo === 'recuperar'" class="space-y-5" @submit.prevent="onRecover">
        <BaseInput
          v-model="form.documento"
          label="Documento de identidad"
          placeholder="Ej. V-12345678"
          required
        />

        <BaseAlert v-if="identity.error" variant="danger" title="Error al recuperar">
          {{ identity.error }}
        </BaseAlert>

        <BaseButton
          type="submit"
          variant="primary"
          size="lg"
          block
          :loading="identity.cargando"
          :disabled="!form.documento"
        >
          {{ identity.cargando ? 'Recuperando…' : 'Recuperar identidad' }}
        </BaseButton>
      </form>

      <!-- Formulario de registro -->
      <form v-else class="space-y-5" @submit.prevent="onSubmit">
        <BaseInput
          v-model="form.nombre"
          label="Nombre completo"
          placeholder="Ej. María Pérez"
          required
        />

        <BaseInput
          v-model="form.documento"
          label="Documento de identidad (DNI/Cédula)"
          placeholder="Ej. V-12345678 o pasaporte"
          required
        />

        <BaseAlert v-if="documentoError" variant="danger" title="Formato inválido">
          {{ documentoError }}
        </BaseAlert>

        <div class="space-y-1.5">
          <label class="block text-sm font-medium text-aid-text">
            Roles / capacidades
          </label>
          <p class="text-xs text-aid-text-light">
            Puedes asignar más de uno. Una misma persona puede despachar y recibir.
          </p>
          <div class="mt-2 space-y-2">
            <label
              v-for="r in roles"
              :key="r.value"
              class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors"
              :class="form.roles.includes(r.value)
                ? 'border-aid-teal bg-aid-teal-50'
                : 'border-aid-gray-200 hover:border-aid-teal-100'"
            >
              <input
                type="checkbox"
                class="mt-0.5 h-4 w-4 rounded border-aid-gray-300 text-aid-teal focus:ring-aid-teal/50"
                :checked="form.roles.includes(r.value)"
                @change="toggleRol(r.value)"
              />
              <span>
                <span class="block text-sm font-medium text-aid-text">{{ r.label }}</span>
                <span class="block text-xs text-aid-text-light">{{ r.hint }}</span>
              </span>
            </label>
          </div>
        </div>

        <BaseSelect
          v-model="form.organizationId"
          label="Organización"
          placeholder="Selecciona una organización"
          :options="catalog.organizations"
          value-key="id"
          label-key="nombre"
          required
        />

        <BaseSelect
          v-model="form.shelterId"
          label="Refugio asignado"
          placeholder="Sin refugio asignado"
          :options="shelterOptions"
          value-key="id"
          label-key="label"
        />

        <BaseAlert v-if="identity.error" variant="danger" title="No se pudo registrar">
          {{ identity.error }}
        </BaseAlert>

        <BaseButton
          type="submit"
          variant="primary"
          size="lg"
          block
          :loading="identity.cargando"
          :disabled="!form.organizationId || !form.nombre || !form.documento || documentoError !== null || form.roles.length === 0"
        >
          {{ identity.cargando ? 'Generando llaves…' : 'Generar identidad y registrar' }}
        </BaseButton>
      </form>
    </BaseCard>
  </div>
</template>
