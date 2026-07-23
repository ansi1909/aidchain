<script setup>
import { onMounted, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useAlertsStore } from '../stores/alerts'
import { useIdentityStore } from '../stores/identity'
import { useCatalogStore } from '../stores/catalog'
import { useNeedsStore } from '../stores/needs'
import BaseCard from '../components/ui/BaseCard.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'
import ShelterMap from '../components/ui/ShelterMap.vue'

const alertsStore = useAlertsStore()
const identity = useIdentityStore()
const catalog = useCatalogStore()
const needs = useNeedsStore()

const esAdmin = computed(() => (identity.coordinator?.roles ?? []).includes('admin'))
const esEncargadoRefugio = computed(() => (identity.coordinator?.roles ?? []).includes('encargado_refugio'))
const miShelterId = computed(() => identity.coordinator?.shelterId ? Number(identity.coordinator.shelterId) : null)
const miShelterNombre = computed(() => {
  if (!miShelterId.value) return 'Mi Refugio'
  const found = catalog.shelters.find(s => s.id === miShelterId.value)
  return found?.nombre ?? 'Mi Refugio'
})

onMounted(async () => {
  identity.init()
  await catalog.cargarCatalogos()
  alertsStore.cargarAlertasActivas()
  alertsStore.cargarEventosCongelados()
  needs.cargarDashboardNeeds()
  if (esEncargadoRefugio.value && miShelterId.value && !esAdmin.value) {
    await catalog.cargarBeneficiarios(miShelterId.value)
  }
})

// 1. Cantidad de refugios registrados y estadísticas de cobertura
const totalRefugios = computed(() => catalog.shelters.length)

const refugiosConNecesidadesCount = computed(() => {
  const ids = new Set(needs.dashboardNeeds.filter(n => n.estado !== 'satisfecho').map(n => n.shelter.id))
  return ids.size
})

const refugiosSinNecesidadesCount = computed(() => Math.max(0, totalRefugios.value - refugiosConNecesidadesCount.value))

// Agrupación por refugio para calcular quién tiene más y menos necesidades
const estadisticasPorRefugio = computed(() => {
  const mapa = new Map()
  
  catalog.shelters.forEach(s => {
    mapa.set(s.id, {
      id: s.id,
      nombre: s.nombre,
      zona: s.zona,
      total: 0,
      pendientes: 0,
      criticas: 0,
      porcentajePromedio: 100,
      sumaPorcentaje: 0
    })
  })

  needs.dashboardNeeds.forEach(n => {
    const sId = n.shelter.id
    if (!mapa.has(sId)) {
      mapa.set(sId, {
        id: sId,
        nombre: n.shelter.nombre,
        zona: n.shelter.zona,
        total: 0,
        pendientes: 0,
        criticas: 0,
        porcentajePromedio: 0,
        sumaPorcentaje: 0
      })
    }
    const stat = mapa.get(sId)
    stat.total += 1
    if (n.estado !== 'satisfecho') {
      stat.pendientes += 1
    }
    if (n.prioridad === 'critica' && n.estado !== 'satisfecho') {
      stat.criticas += 1
    }
    stat.sumaPorcentaje += Number(n.porcentajeSatisfaccion || 0)
  })

  return Array.from(mapa.values()).map(item => ({
    ...item,
    porcentajePromedio: item.total > 0 ? (item.sumaPorcentaje / item.total) : 100
  }))
})

// 2. Refugios con más necesidades (orden descendente por necesidades pendientes y críticas)
const refugiosConMasNecesidades = computed(() => {
  return [...estadisticasPorRefugio.value]
    .filter(s => s.pendientes > 0 || s.total > 0)
    .sort((a, b) => {
      if (b.pendientes !== a.pendientes) return b.pendientes - a.pendientes
      if (b.criticas !== a.criticas) return b.criticas - a.criticas
      return b.total - a.total
    })
    .slice(0, 4)
})

// 3. Refugios con menos necesidades (orden ascendente por necesidades pendientes y porcentaje)
const refugiosConMenosNecesidades = computed(() => {
  return [...estadisticasPorRefugio.value]
    .sort((a, b) => {
      if (a.pendientes !== b.pendientes) return a.pendientes - b.pendientes
      return b.porcentajePromedio - a.porcentajePromedio
    })
    .slice(0, 4)
})

// Métricas demográficas para el encargado de refugio
const familiasMiRefugioCount = computed(() => {
  if (!miShelterId.value) return 0
  return catalog.beneficiaries.filter(b => Number(b.shelterId) === miShelterId.value && b.esRepresentante).length
})

const totalCensadosMiRefugioCount = computed(() => {
  if (!miShelterId.value) return 0
  return catalog.beneficiaries.filter(b => Number(b.shelterId) === miShelterId.value).length
})

const menoresMiRefugioCount = computed(() => {
  if (!miShelterId.value) return 0
  return catalog.beneficiaries.filter(b => {
    if (Number(b.shelterId) !== miShelterId.value) return false
    const edad = b.datosDemograficos?.edad
    return edad !== undefined && edad !== null && Number(edad) < 18
  }).length
})

const ancianosMiRefugioCount = computed(() => {
  if (!miShelterId.value) return 0
  return catalog.beneficiaries.filter(b => {
    if (Number(b.shelterId) !== miShelterId.value) return false
    const edad = b.datosDemograficos?.edad
    return edad !== undefined && edad !== null && Number(edad) >= 60
  }).length
})

// 4. Ranking de insumos más requeridos (filtrado si es encargado de refugio)
const rankingInsumos = computed(() => {
  const mapa = new Map()

  needs.dashboardNeeds.forEach(n => {
    if (n.estado === 'satisfecho') return
    if (!esAdmin.value && esEncargadoRefugio.value && miShelterId.value && Number(n.shelter.id) !== miShelterId.value) {
      return
    }
    const key = n.item.trim().toLowerCase()
    if (!mapa.has(key)) {
      mapa.set(key, {
        item: n.item.trim(),
        totalRequerido: 0,
        totalRecibido: 0,
        refugiosSolicitantes: new Set(),
        unidades: new Set(),
        prioridadMaxima: 'baja'
      })
    }
    const info = mapa.get(key)
    info.totalRequerido += Number(n.cantidadRequerida || 0)
    info.totalRecibido += Number(n.cantidadRecibida || 0)
    info.refugiosSolicitantes.add(n.shelter.id)
    if (n.unidad) info.unidades.add(n.unidad)

    const pesos = { critica: 4, alta: 3, media: 2, baja: 1 }
    if (pesos[n.prioridad] > pesos[info.prioridadMaxima]) {
      info.prioridadMaxima = n.prioridad
    }
  })

  return Array.from(mapa.values())
    .map(info => ({
      ...info,
      conteoRefugios: info.refugiosSolicitantes.size,
      unidadMostrar: Array.from(info.unidades).join(', ') || 'unidades',
      porcentajeCubierto: info.totalRequerido > 0 ? Math.min(100, (info.totalRecibido / info.totalRequerido) * 100) : 0
    }))
    .sort((a, b) => {
      if (esAdmin.value) {
        if (b.conteoRefugios !== a.conteoRefugios) return b.conteoRefugios - a.conteoRefugios
      }
      return b.totalRequerido - a.totalRequerido
    })
    .slice(0, 6)
})

function getPrioridadColor(prioridad) {
  switch (prioridad) {
    case 'critica':
      return 'bg-red-100 text-red-800 border-red-200'
    case 'alta':
      return 'bg-orange-100 text-orange-800 border-orange-200'
    case 'media':
      return 'bg-yellow-100 text-yellow-800 border-yellow-200'
    default:
      return 'bg-gray-100 text-gray-800 border-gray-200'
  }
}
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
            Panel de trazabilidad y análisis
            <BaseTooltip>
              Métricas operativas en tiempo real según tu nivel de acceso al ledger humanitario.
            </BaseTooltip>
          </h1>
        </div>
      </div>
    </header>

    <!-- Fila 1 (Vista de Administrador): Indicadores estratégicos de toda la red de refugios -->
    <div v-if="esAdmin" class="mb-8 grid gap-6 sm:grid-cols-3">
      <!-- Tarjeta 1: Cantidad de Refugios Registrados -->
      <BaseCard padding="lg" shadow="sm" class="flex flex-col justify-between border-t-4 border-t-aid-teal bg-white">
        <div>
          <div class="flex items-center justify-between text-aid-text-light">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-aid-navy">Refugios Registrados</h2>
            <svg class="h-5 w-5 text-aid-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <p class="mt-4 text-4xl font-extrabold text-aid-navy">{{ totalRefugios }}</p>
          <p class="mt-1 text-xs text-aid-text-light">Centros activos en el catálogo global</p>
        </div>

        <div class="mt-6 border-t border-aid-gray-100 pt-3 text-xs space-y-1.5">
          <div class="flex justify-between items-center">
            <span class="text-aid-text-muted flex items-center gap-1.5">
              <span class="h-2 w-2 rounded-full bg-aid-warning"></span> Con demandas pendientes
            </span>
            <span class="font-semibold text-aid-navy">{{ refugiosConNecesidadesCount }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-aid-text-muted flex items-center gap-1.5">
              <span class="h-2 w-2 rounded-full bg-aid-success"></span> Cubiertos 100% / Sin demandas
            </span>
            <span class="font-semibold text-aid-success">{{ refugiosSinNecesidadesCount }}</span>
          </div>
        </div>
      </BaseCard>

      <!-- Tarjeta 2: Refugios con Más Necesidades -->
      <BaseCard padding="lg" shadow="sm" class="flex flex-col justify-between border-t-4 border-t-aid-danger bg-white">
        <div>
          <div class="flex items-center justify-between text-aid-text-light">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-aid-danger">Refugios con Más Necesidades</h2>
            <svg class="h-5 w-5 text-aid-danger" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>

          <div v-if="refugiosConMasNecesidades.length === 0" class="mt-6 py-6 text-center">
            <p class="text-sm font-medium text-aid-text">Sin urgencias activas</p>
            <p class="text-xs text-aid-text-light">Ningún refugio reporta necesidades pendientes.</p>
          </div>

          <ul v-else class="mt-4 space-y-2.5">
            <li
              v-for="s in refugiosConMasNecesidades"
              :key="s.id"
              class="flex items-center justify-between rounded-lg bg-aid-gray-50 px-3 py-2 text-sm"
            >
              <div class="truncate pr-2">
                <p class="font-medium text-aid-navy truncate">{{ s.nombre }}</p>
                <p class="text-xs text-aid-text-light truncate">{{ s.zona }}</p>
              </div>
              <div class="flex items-center gap-1.5 shrink-0">
                <span
                  v-if="s.criticas > 0"
                  class="rounded bg-red-100 px-1.5 py-0.5 text-xs font-bold text-red-700"
                  title="Necesidades críticas"
                >
                  {{ s.criticas }} críticas
                </span>
                <span class="rounded bg-aid-navy/10 px-2 py-0.5 text-xs font-semibold text-aid-navy">
                  {{ s.pendientes }} pend.
                </span>
              </div>
            </li>
          </ul>
        </div>

        <div class="mt-4 border-t border-aid-gray-100 pt-2.5 text-right">
          <RouterLink to="/despacho" class="text-xs font-medium text-aid-teal hover:text-aid-teal-700">
            Realizar despachos →
          </RouterLink>
        </div>
      </BaseCard>

      <!-- Tarjeta 3: Refugios con Menos Necesidades -->
      <BaseCard padding="lg" shadow="sm" class="flex flex-col justify-between border-t-4 border-t-aid-success bg-white">
        <div>
          <div class="flex items-center justify-between text-aid-text-light">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-aid-success">Refugios con Menos Necesidades</h2>
            <svg class="h-5 w-5 text-aid-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>

          <div v-if="refugiosConMenosNecesidades.length === 0" class="mt-6 py-6 text-center">
            <p class="text-sm font-medium text-aid-text">Catálogo vacío</p>
            <p class="text-xs text-aid-text-light">Aún no hay refugios registrados.</p>
          </div>

          <ul v-else class="mt-4 space-y-2.5">
            <li
              v-for="s in refugiosConMenosNecesidades"
              :key="s.id"
              class="flex items-center justify-between rounded-lg bg-aid-gray-50 px-3 py-2 text-sm"
            >
              <div class="truncate pr-2">
                <p class="font-medium text-aid-navy truncate">{{ s.nombre }}</p>
                <p class="text-xs text-aid-text-light truncate">{{ s.zona }}</p>
              </div>
              <div class="shrink-0">
                <span
                  v-if="s.pendientes === 0"
                  class="rounded bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800"
                >
                  Cubierto 100%
                </span>
                <span v-else class="rounded bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-700">
                  {{ s.pendientes }} pend.
                </span>
              </div>
            </li>
          </ul>
        </div>

        <div class="mt-4 border-t border-aid-gray-100 pt-2.5 text-right">
          <RouterLink to="/despacho" class="text-xs font-medium text-aid-teal hover:text-aid-teal-700">
            Realizar despachos →
          </RouterLink>
        </div>
      </BaseCard>
    </div>

    <!-- Fila 1 (Vista de Encargado de Refugio): Métricas del propio centro y vulnerabilidad -->
    <div v-else-if="esEncargadoRefugio" class="mb-8 grid gap-6 sm:grid-cols-3">
      <!-- Tarjeta 1: Familias Registradas en su refugio -->
      <BaseCard padding="lg" shadow="sm" class="flex flex-col justify-between border-t-4 border-t-aid-teal bg-white">
        <div>
          <div class="flex items-center justify-between text-aid-text-light">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-aid-navy">Familias en {{ miShelterNombre }}</h2>
            <svg class="h-5 w-5 text-aid-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <p class="mt-4 text-4xl font-extrabold text-aid-navy">{{ familiasMiRefugioCount }}</p>
          <p class="mt-1 text-xs text-aid-text-light">Grupos familiares / representantes</p>
        </div>

        <div class="mt-6 border-t border-aid-gray-100 pt-3 flex justify-between items-center text-xs">
          <span class="text-aid-text-muted">Población total censada</span>
          <span class="font-bold text-aid-navy">{{ totalCensadosMiRefugioCount }} personas</span>
        </div>
      </BaseCard>

      <!-- Tarjeta 2: Menores de edad -->
      <BaseCard padding="lg" shadow="sm" class="flex flex-col justify-between border-t-4 border-t-aid-warning bg-white">
        <div>
          <div class="flex items-center justify-between text-aid-text-light">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-aid-warning">Menores de Edad (&lt; 18)</h2>
            <svg class="h-5 w-5 text-aid-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <p class="mt-4 text-4xl font-extrabold text-aid-navy">{{ menoresMiRefugioCount }}</p>
          <p class="mt-1 text-xs text-aid-text-light">Niños y adolescentes en resguardo</p>
        </div>

        <div class="mt-6 border-t border-aid-gray-100 pt-2.5 text-right">
          <RouterLink to="/censo" class="text-xs font-medium text-aid-teal hover:text-aid-teal-700">
            Ver censo demográfico →
          </RouterLink>
        </div>
      </BaseCard>

      <!-- Tarjeta 3: Adultos mayores / Ancianos -->
      <BaseCard padding="lg" shadow="sm" class="flex flex-col justify-between border-t-4 border-t-aid-success bg-white">
        <div>
          <div class="flex items-center justify-between text-aid-text-light">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-aid-success">Adultos Mayores (≥ 60)</h2>
            <svg class="h-5 w-5 text-aid-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
            </svg>
          </div>
          <p class="mt-4 text-4xl font-extrabold text-aid-navy">{{ ancianosMiRefugioCount }}</p>
          <p class="mt-1 text-xs text-aid-text-light">Población geriátrica prioritaria</p>
        </div>

        <div class="mt-6 border-t border-aid-gray-100 pt-2.5 text-right">
          <RouterLink to="/necesidades" class="text-xs font-medium text-aid-teal hover:text-aid-teal-700">
            Reportar necesidades geriátricas →
          </RouterLink>
        </div>
      </BaseCard>
    </div>

    <!-- Fila 2: Mapa Georreferenciado de Refugios -->
    <ShelterMap
      class="mb-8"
      :shelters="catalog.shelters"
      :estadisticas-por-refugio="estadisticasPorRefugio"
      :center-shelter-id="esEncargadoRefugio && !esAdmin ? miShelterId : null"
      :es-admin="esAdmin"
    />

    <!-- Fila 3: Ranking de insumos más requeridos -->
    <BaseCard padding="lg" shadow="sm" class="mb-8 bg-white">
      <div class="mb-5 flex items-center justify-between">
        <div>
          <h2 class="text-lg font-bold text-aid-navy">
            {{ esAdmin ? 'Ranking de insumos más requeridos (Red global)' : `Ranking de insumos requeridos en ${miShelterNombre}` }}
          </h2>
          <p class="text-xs text-aid-text-light">
            {{ esAdmin ? 'Consolidado de demanda agregada por insumo y número de refugios solicitantes' : 'Prioridades locales de abastecimiento reportadas para tu centro' }}
          </p>
        </div>
        <StatusBadge
          :variant="rankingInsumos.length > 0 ? 'info' : 'success'"
          :label="`${rankingInsumos.length} insumos pendientes`"
        />
      </div>

      <div v-if="needs.cargando" class="py-12 text-center">
        <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-aid-teal border-t-transparent" />
        <p class="mt-2 text-sm text-aid-text-muted">Calculando ranking de insumos...</p>
      </div>

      <div
        v-else-if="rankingInsumos.length === 0"
        class="flex flex-col items-center justify-center rounded-xl bg-aid-gray-50 py-12 text-center"
      >
        <div class="mb-2 flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-sm">
          <svg class="h-6 w-6 text-aid-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <p class="font-semibold text-aid-navy">No hay insumos pendientes de abastecimiento</p>
        <p class="text-sm text-aid-text-light">Todos los requerimientos se encuentran cubiertos en este momento.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-aid-gray-200 bg-aid-gray-50 text-xs font-semibold uppercase tracking-wider text-aid-text-muted">
              <th class="py-3 pl-4 pr-3">#</th>
              <th class="py-3 px-3">Insumo</th>
              <th v-if="esAdmin" class="py-3 px-3">Refugios Solicitantes</th>
              <th class="py-3 px-3">Demanda Pendiente</th>
              <th class="py-3 px-3">Satisfacción</th>
              <th class="py-3 pl-3 pr-4 text-right">Prioridad Máxima</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-aid-gray-100">
            <tr
              v-for="(item, idx) in rankingInsumos"
              :key="item.item"
              class="hover:bg-aid-gray-50/70 transition-colors"
            >
              <td class="py-3.5 pl-4 pr-3 font-bold text-aid-navy">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-aid-teal/15 text-xs text-aid-navy">
                  {{ idx + 1 }}
                </span>
              </td>
              <td class="py-3.5 px-3 font-semibold text-aid-text">{{ item.item }}</td>
              <td v-if="esAdmin" class="py-3.5 px-3 text-aid-text-light">
                <span class="font-medium text-aid-navy">{{ item.conteoRefugios }}</span>
                {{ item.conteoRefugios === 1 ? 'refugio' : 'refugios' }}
              </td>
              <td class="py-3.5 px-3">
                <span class="font-bold text-aid-navy">{{ item.totalRequerido - item.totalRecibido }}</span>
                <span class="text-xs text-aid-text-light ml-1">{{ item.unidadMostrar }}</span>
                <span v-if="item.totalRecibido > 0" class="block text-xs text-aid-text-muted">
                  (Recibido: {{ item.totalRecibido }} {{ item.unidadMostrar }})
                </span>
              </td>
              <td class="py-3.5 px-3">
                <div class="flex items-center gap-2">
                  <div class="h-2 w-24 rounded-full bg-aid-gray-200 overflow-hidden">
                    <div
                      class="h-2 rounded-full bg-aid-teal transition-all"
                      :style="{ width: `${item.porcentajeCubierto}%` }"
                    />
                  </div>
                  <span class="text-xs font-semibold text-aid-text-light">{{ item.porcentajeCubierto.toFixed(0) }}%</span>
                </div>
              </td>
              <td class="py-3.5 pl-3 pr-4 text-right">
                <span
                  class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold border uppercase tracking-wide"
                  :class="getPrioridadColor(item.prioridadMaxima)"
                >
                  {{ item.prioridadMaxima }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

    <!-- Fila 4: Monitoreo de integridad del Ledger y Alertas (Trazabilidad) -->
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
    </div>
  </div>
</template>
