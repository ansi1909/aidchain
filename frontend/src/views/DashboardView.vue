<script setup>
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAlertsStore } from '../stores/alerts'
import { useIdentityStore } from '../stores/identity'
import { useNeedsStore } from '../stores/needs'
import BaseCard from '../components/ui/BaseCard.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'

const alertsStore = useAlertsStore()
const identity = useIdentityStore()
const needs = useNeedsStore()

const accesos = [
  {
    to: '/censo',
    title: 'Censo',
    desc: 'Registra beneficiarios y genera códigos QR.',
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6 4h8a2 2 0 002-2v-8a2 2 0 00-2-2h-5.5',
    color: 'bg-aid-teal-50 text-aid-teal',
  },
  {
    to: '/despacho',
    title: 'Despacho',
    desc: 'Firma y registra eventos de entrega.',
    icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
    color: 'bg-aid-success-50 text-aid-success',
  },
  {
    to: '/necesidades',
    title: 'Necesidades',
    desc: 'Reporta y gestiona necesidades por refugio.',
    icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
    color: 'bg-aid-warning-50 text-aid-warning',
  },
]

onMounted(() => {
  identity.init()
  alertsStore.cargarAlertasActivas()
  alertsStore.cargarEventosCongelados()
  needs.cargarDashboardNeeds()
})
</script>

<template>
  <div class="animate-fade-in-up">
    <header class="mb-8">
      <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-aid-teal-50">
          <svg class="h-6 w-6 text-aid-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m6 0a2 2 0 002-2h-2a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2" />
          </svg>
        </div>
        <div>
          <h1 class="inline-flex items-center gap-2 text-2xl font-bold text-aid-navy">
            Panel de trazabilidad
            <BaseTooltip>
              Resumen operativo del ledger humanitario.
            </BaseTooltip>
          </h1>
        </div>
      </div>
    </header>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <RouterLink
        v-for="acc in accesos"
        :key="acc.to"
        :to="acc.to"
        class="group flex items-start gap-4 rounded-xl border border-aid-gray-100 bg-white p-5 shadow-sm transition-all hover:-translate-y-0.5 hover:border-aid-teal-200 hover:shadow-md"
      >
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg" :class="acc.color">
          <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="acc.icon" />
          </svg>
        </div>
        <div>
          <h3 class="font-semibold text-aid-text group-hover:text-aid-teal">{{ acc.title }}</h3>
          <p class="mt-0.5 text-sm text-aid-text-light">{{ acc.desc }}</p>
        </div>
      </RouterLink>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <BaseCard padding="md" shadow="sm" class="lg:col-span-2">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-aid-navy">Eventos congelados (doble cobro)</h2>
          <StatusBadge
            v-if="alertsStore.eventosCongelados.length > 0"
            variant="danger"
            :label="`${alertsStore.eventosCongelados.length} pendientes`"
          />
          <StatusBadge v-else variant="success" label="Sin congelados" />
        </div>

        <div v-if="alertsStore.cargando" class="py-8 text-center">
          <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-aid-teal border-t-transparent" />
          <p class="mt-2 text-sm text-aid-text-muted">Cargando eventos...</p>
        </div>

        <div
          v-else-if="alertsStore.eventosCongelados.length === 0"
          class="flex flex-col items-center justify-center rounded-xl bg-aid-gray-50 py-10 text-center"
        >
          <div class="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm">
            <svg class="h-6 w-6 text-aid-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <p class="font-medium text-aid-text">No hay eventos congelados</p>
          <p class="text-sm text-aid-text-light">El control de doble cobro está operativo.</p>
        </div>

        <ul v-else class="space-y-3">
          <li
            v-for="evento in alertsStore.eventosCongelados"
            :key="evento.id"
            class="flex items-start gap-3 rounded-lg border border-aid-gray-100 bg-aid-gray-50 p-3"
          >
            <div class="mt-0.5 h-2 w-2 shrink-0 rounded-full bg-aid-danger" />
            <div class="flex-1">
              <p class="text-sm font-medium text-aid-text">{{ evento.item }} · {{ evento.cantidad }} {{ evento.unidad }}</p>
              <p class="text-sm text-aid-text-light">
                {{ evento.shelter.nombre }} · {{ evento.beneficiary?.nombre || 'Sin beneficiario' }}
              </p>
            </div>
            <div class="flex gap-2">
              <button
                type="button"
                class="text-xs font-medium text-aid-teal hover:text-aid-teal-700"
                @click="alertsStore.liberarEvento(evento.id)"
              >
                Liberar
              </button>
              <button
                type="button"
                class="text-xs font-medium text-aid-danger hover:text-aid-danger-700"
                @click="alertsStore.rechazarEvento(evento.id)"
              >
                Rechazar
              </button>
            </div>
          </li>
        </ul>
      </BaseCard>

      <BaseCard padding="md" shadow="sm">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-aid-navy">Alertas activas</h2>
          <StatusBadge
            v-if="alertsStore.criticas.length > 0"
            variant="danger"
            :label="`${alertsStore.criticas.length} críticas`"
          />
          <StatusBadge v-else variant="success" label="Sin críticas" />
        </div>

        <div v-if="alertsStore.cargando" class="py-8 text-center">
          <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-aid-teal border-t-transparent" />
          <p class="mt-2 text-sm text-aid-text-muted">Cargando alertas...</p>
        </div>

        <BaseAlert v-else-if="alertsStore.error" variant="danger" title="Error de conexión">
          {{ alertsStore.error }}
        </BaseAlert>

        <div
          v-else-if="alertsStore.alertas.length === 0"
          class="flex flex-col items-center justify-center rounded-xl bg-aid-gray-50 py-10 text-center"
        >
          <div class="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm">
            <svg class="h-6 w-6 text-aid-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <p class="font-medium text-aid-text">No hay alertas activas</p>
          <p class="text-sm text-aid-text-light">La cadena de eventos se encuentra estable.</p>
        </div>

        <ul v-else class="space-y-3">
          <li
            v-for="alerta in alertsStore.alertas"
            :key="alerta.id"
            class="flex items-start gap-3 rounded-lg border border-aid-gray-100 bg-aid-gray-50 p-3"
          >
            <div class="mt-0.5 h-2 w-2 shrink-0 rounded-full" :class="{
              'bg-aid-danger': alerta.severidad === 'critica' || alerta.severidad === 'alta',
              'bg-aid-warning': alerta.severidad === 'media',
              'bg-aid-navy': alerta.severidad === 'baja',
            }" />
            <div class="flex-1">
              <p class="text-sm font-medium text-aid-text">{{ alerta.tipo }}</p>
              <p class="text-sm text-aid-text-light">{{ alerta.mensaje }}</p>
            </div>
            <span class="text-xs font-medium uppercase tracking-wider text-aid-text-muted">{{ alerta.severidad }}</span>
          </li>
        </ul>
      </BaseCard>

      <BaseCard padding="md" shadow="sm">
        <div class="mb-4 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-aid-navy">Necesidades pendientes</h2>
          <RouterLink to="/necesidades" class="text-sm text-aid-teal hover:text-aid-teal-700">
            Ver todas
          </RouterLink>
        </div>

        <div v-if="needs.cargando" class="py-8 text-center">
          <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-aid-teal border-t-transparent" />
          <p class="mt-2 text-sm text-aid-text-muted">Cargando necesidades...</p>
        </div>

        <div
          v-else-if="needs.dashboardNeeds.length === 0"
          class="flex flex-col items-center justify-center rounded-xl bg-aid-gray-50 py-10 text-center"
        >
          <div class="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm">
            <svg class="h-6 w-6 text-aid-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6 4h8a2 2 0 002-2v-8a2 2 0 00-2-2h-5.5" />
            </svg>
          </div>
          <p class="font-medium text-aid-text">No hay necesidades pendientes</p>
          <p class="text-sm text-aid-text-light">Todos los refugios están cubiertos.</p>
        </div>

        <ul v-else class="space-y-3">
          <li
            v-for="need in needs.dashboardNeeds.slice(0, 5)"
            :key="need.id"
            class="flex items-start gap-3 rounded-lg border border-aid-gray-100 bg-aid-gray-50 p-3"
          >
            <div class="mt-0.5 h-2 w-2 shrink-0 rounded-full" :class="{
              'bg-aid-danger': need.prioridad === 'critica',
              'bg-aid-warning': need.prioridad === 'alta',
              'bg-aid-teal': need.prioridad === 'media',
              'bg-aid-navy': need.prioridad === 'baja',
            }" />
            <div class="flex-1">
              <p class="text-sm font-medium text-aid-text">{{ need.item }}</p>
              <p class="text-sm text-aid-text-light">
                {{ need.shelter.nombre }} · {{ need.cantidadRequerida }} {{ need.prioridadLabel }}
              </p>
            </div>
            <span class="text-xs font-medium uppercase tracking-wider text-aid-text-muted">{{ need.estadoLabel }}</span>
          </li>
        </ul>
      </BaseCard>

      <BaseCard padding="md" shadow="sm" class="bg-gradient-to-br from-aid-teal to-aid-teal-600 text-white">
        <h2 class="text-lg font-semibold">Estado del sistema</h2>
        <div class="mt-4 space-y-4">
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/20">
              <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <p class="text-xs font-medium uppercase tracking-wider text-aid-teal-100">Ledger</p>
              <p class="text-sm font-semibold">Operativo</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/20">
              <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
            </div>
            <div>
              <p class="text-xs font-medium uppercase tracking-wider text-aid-teal-100">Identidad</p>
              <p class="text-sm font-semibold">{{ identity.isReady ? 'Activa' : 'Pendiente' }}</p>
            </div>
          </div>
        </div>
      </BaseCard>
    </div>
  </div>
</template>
