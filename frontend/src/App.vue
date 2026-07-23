<script setup>
import { onMounted, ref, computed } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useIdentityStore } from './stores/identity'
import { useCatalogStore } from './stores/catalog'
import logoUrl from './assets/logo-aidchain.png'

const identity = useIdentityStore()
const catalog = useCatalogStore()
const route = useRoute()
const router = useRouter()

const sidebarOpen = ref(false)
const sidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true')
const userMenuOpen = ref(false)
const refugiosMenuOpen = ref(false)

function toggleSidebarCollapse() {
  sidebarCollapsed.value = !sidebarCollapsed.value
  localStorage.setItem('sidebar_collapsed', String(sidebarCollapsed.value))
}

const nav = [
  { to: '/', label: 'Dashboard', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
  { to: '/despacho', label: 'Despacho', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  { to: '/recepcion', label: 'Recepción', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
]

const esAdmin = computed(() => (identity.coordinator?.roles ?? []).includes('admin'))
const esEncargadoRefugio = computed(() => (identity.coordinator?.roles ?? []).includes('encargado_refugio'))
const puedeVerRefugios = computed(() => esAdmin.value || esEncargadoRefugio.value)

const ROLE_LABELS = {
  despachador: 'Despachador',
  encargado_refugio: 'Encargado de refugio',
  auditor: 'Auditor',
  admin: 'Administrador',
}

const rolesLabel = computed(() =>
  (identity.coordinator?.roles ?? [])
    .map((r) => ROLE_LABELS[r] ?? r)
    .join(' · '),
)

const organizacionNombre = computed(() => {
  const orgId = identity.coordinator?.organizationId
  if (!orgId) return null
  return catalog.organizations.find((o) => o.id === Number(orgId))?.nombre ?? null
})

const iniciales = computed(() => {
  const nombre = identity.coordinator?.nombre ?? ''
  return nombre
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join('')
})

function irRecuperar() {
  sidebarOpen.value = false
  userMenuOpen.value = false
  router.push({ path: '/identidad', query: { modo: 'recuperar' } })
}

async function borrarIdentidad() {
  userMenuOpen.value = false
  if (confirm('Esto borrará tu llave privada local y tu identidad de este dispositivo. ¿Continuar?')) {
    await identity.reset()
    router.push('/identidad')
  }
}

onMounted(async () => {
  await identity.init()
  if (catalog.organizations.length === 0) {
    await catalog.cargarCatalogos()
  }
})
</script>

<template>
  <div class="flex min-h-screen bg-aid-gray-50">
    <!-- Overlay para móvil -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-30 bg-black/40 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Menú lateral vertical -->
    <aside
      class="fixed inset-y-0 left-0 z-40 flex flex-col border-r border-aid-gray-100 bg-white transition-all duration-300"
      :class="[
        sidebarOpen ? 'translate-x-0 w-64' : '-translate-x-full lg:translate-x-0',
        sidebarCollapsed ? 'w-64 lg:w-20' : 'w-64'
      ]"
    >
      <!-- Marca -->
      <RouterLink
        to="/"
        class="flex items-center border-b border-aid-gray-100 py-4 transition-all"
        :class="sidebarCollapsed ? 'justify-center px-2' : 'gap-2.5 px-5'"
        @click="sidebarOpen = false"
      >
        <img :src="logoUrl" alt="AidChain" class="h-9 w-auto shrink-0 transition-transform hover:scale-105" />
        <div v-if="!sidebarCollapsed" class="flex flex-col overflow-hidden">
          <span class="text-sm font-bold leading-tight tracking-tight text-aid-navy">
            AID<span class="text-aid-teal">CHAIN</span>
          </span>
          <span class="text-[10px] font-medium uppercase tracking-wider text-aid-gray truncate">
            Trazabilidad humanitaria
          </span>
        </div>
      </RouterLink>

      <!-- Navegación -->
      <nav class="flex-1 space-y-1.5 overflow-y-auto px-3 py-4">
        <RouterLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          :title="sidebarCollapsed ? item.label : undefined"
          class="group flex items-center rounded-lg py-2.5 text-sm font-medium text-aid-text-light transition-all hover:bg-aid-teal-50 hover:text-aid-teal"
          :class="[
            route.path === item.to ? 'bg-aid-teal-50 text-aid-teal font-semibold' : '',
            sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-3'
          ]"
          @click="sidebarOpen = false"
        >
          <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
          <span v-if="!sidebarCollapsed" class="truncate">{{ item.label }}</span>
        </RouterLink>

        <!-- Grupo Refugios (admin y encargado de refugio) -->
        <div v-if="puedeVerRefugios" class="space-y-1">
          <button
            type="button"
            :title="sidebarCollapsed ? 'Refugios (abrir menú)' : undefined"
            class="flex w-full items-center rounded-lg py-2.5 text-sm font-medium text-aid-text-light transition-all hover:bg-aid-teal-50 hover:text-aid-teal"
            :class="[
              refugiosMenuOpen ? 'bg-aid-teal-50 text-aid-teal' : '',
              sidebarCollapsed ? 'justify-center px-2' : 'justify-between px-3'
            ]"
            @click="if (sidebarCollapsed) { sidebarCollapsed = false; localStorage.setItem('sidebar_collapsed', 'false'); refugiosMenuOpen = true; } else { refugiosMenuOpen = !refugiosMenuOpen; }"
          >
            <div class="flex items-center" :class="sidebarCollapsed ? 'justify-center' : 'gap-3'">
              <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
              <span v-if="!sidebarCollapsed" class="truncate">Refugios</span>
            </div>
            <svg
              v-if="!sidebarCollapsed"
              class="h-4 w-4 shrink-0 transition-transform"
              :class="refugiosMenuOpen ? 'rotate-180' : ''"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <div v-if="refugiosMenuOpen && !sidebarCollapsed" class="ml-8 space-y-1 animate-fade-in-up">
            <RouterLink
              to="/refugios"
              class="block rounded-lg px-3 py-2 text-sm text-aid-text-light transition-colors hover:bg-aid-teal-50 hover:text-aid-teal"
              :class="route.path === '/refugios' ? 'bg-aid-teal-50 text-aid-teal font-medium' : ''"
              @click="sidebarOpen = false"
            >
              Gestión de refugios
            </RouterLink>
            <RouterLink
              to="/censo"
              class="block rounded-lg px-3 py-2 text-sm text-aid-text-light transition-colors hover:bg-aid-teal-50 hover:text-aid-teal"
              :class="route.path === '/censo' ? 'bg-aid-teal-50 text-aid-teal font-medium' : ''"
              @click="sidebarOpen = false"
            >
              Censo
            </RouterLink>
            <RouterLink
              to="/necesidades"
              class="block rounded-lg px-3 py-2 text-sm text-aid-text-light transition-colors hover:bg-aid-teal-50 hover:text-aid-teal"
              :class="route.path === '/necesidades' ? 'bg-aid-teal-50 text-aid-teal font-medium' : ''"
              @click="sidebarOpen = false"
            >
              Necesidades
            </RouterLink>
            <RouterLink
              to="/inventario"
              class="block rounded-lg px-3 py-2 text-sm text-aid-text-light transition-colors hover:bg-aid-teal-50 hover:text-aid-teal"
              :class="route.path === '/inventario' ? 'bg-aid-teal-50 text-aid-teal font-medium' : ''"
              @click="sidebarOpen = false"
            >
              Inventario
            </RouterLink>
            <RouterLink
              to="/entrega"
              class="block rounded-lg px-3 py-2 text-sm text-aid-text-light transition-colors hover:bg-aid-teal-50 hover:text-aid-teal"
              :class="route.path === '/entrega' ? 'bg-aid-teal-50 text-aid-teal font-medium' : ''"
              @click="sidebarOpen = false"
            >
              Padrón
            </RouterLink>
          </div>
        </div>
      </nav>

      <!-- Botón colapsar/expandir en parte inferior del sidebar (sólo desktop) -->
      <div class="hidden border-t border-aid-gray-100 p-2.5 lg:block">
        <button
          type="button"
          class="flex w-full items-center rounded-lg py-2 text-xs font-semibold text-aid-text-light transition-colors hover:bg-aid-gray-100 hover:text-aid-navy"
          :class="sidebarCollapsed ? 'justify-center px-2' : 'gap-3 px-3'"
          @click="toggleSidebarCollapse"
          :title="sidebarCollapsed ? 'Expandir menú lateral' : 'Colapsar menú lateral'"
        >
          <svg
            class="h-5 w-5 shrink-0 transition-transform"
            :class="sidebarCollapsed ? 'rotate-180 text-aid-teal' : 'text-aid-text-muted'"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
          >
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
          </svg>
          <span v-if="!sidebarCollapsed" class="truncate">Colapsar menú</span>
        </button>
      </div>

      <div
        class="border-t border-aid-gray-100 px-4 py-3 text-[11px] text-aid-text-muted transition-all"
        :class="sidebarCollapsed ? 'hidden' : 'block'"
      >
        Ledger criptográfico humanitario
      </div>
    </aside>

    <!-- Contenido principal -->
    <div
      class="flex min-h-screen flex-1 flex-col transition-all duration-300"
      :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'"
    >
      <!-- Barra superior -->
      <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-aid-gray-100 bg-white/95 px-4 py-2.5 backdrop-blur-sm">
        <div class="flex items-center gap-2">
          <!-- Botón hamburguesa móvil -->
          <button
            type="button"
            class="rounded-lg p-2 text-aid-text hover:bg-aid-gray-100 lg:hidden"
            @click="sidebarOpen = true"
            title="Abrir menú"
          >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <!-- Botón colapsar/expandir en cabecera desktop -->
          <button
            type="button"
            class="hidden rounded-lg p-2 text-aid-text hover:bg-aid-gray-100 lg:flex items-center justify-center transition-colors"
            @click="toggleSidebarCollapse"
            :title="sidebarCollapsed ? 'Expandir menú lateral' : 'Colapsar menú lateral'"
          >
            <svg
              class="h-5 w-5 text-aid-text-muted hover:text-aid-teal transition-transform"
              :class="sidebarCollapsed ? 'rotate-180' : ''"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <span class="text-sm font-bold text-aid-navy lg:hidden">AID<span class="text-aid-teal">CHAIN</span></span>
        </div>

        <!-- Usuario identificado (esquina superior derecha) -->
        <div class="relative">
          <template v-if="identity.isReady">
            <button
              type="button"
              class="flex items-center gap-2.5 rounded-lg border border-aid-gray-200 py-1.5 pl-2 pr-2.5 transition-colors hover:border-aid-teal hover:bg-aid-teal-50/40"
              @click="userMenuOpen = !userMenuOpen"
            >
              <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-aid-teal-50 text-xs font-bold text-aid-teal">
                {{ iniciales || '?' }}
              </span>
              <span class="hidden min-w-0 flex-col text-left sm:flex">
                <span class="truncate text-sm font-semibold leading-tight text-aid-navy">{{ identity.coordinator.nombre }}</span>
                <span class="truncate text-[11px] leading-tight text-aid-text-light">
                  {{ rolesLabel || 'Sin rol' }}<template v-if="organizacionNombre"> · {{ organizacionNombre }}</template>
                </span>
              </span>
              <svg class="h-4 w-4 shrink-0 text-aid-text-muted transition-transform" :class="userMenuOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <!-- Overlay para cerrar al hacer clic fuera -->
            <div v-if="userMenuOpen" class="fixed inset-0 z-30" @click="userMenuOpen = false" />

            <!-- Dropdown -->
            <div
              v-if="userMenuOpen"
              class="absolute right-0 z-40 mt-2 w-64 overflow-hidden rounded-xl border border-aid-gray-100 bg-white shadow-lg"
            >
              <div class="border-b border-aid-gray-100 px-4 py-3">
                <p class="truncate text-sm font-semibold text-aid-navy">{{ identity.coordinator.nombre }}</p>
                <p class="mt-0.5 truncate text-xs text-aid-text-light">{{ rolesLabel || 'Sin rol' }}</p>
                <p v-if="organizacionNombre" class="mt-1 truncate text-xs text-aid-text-muted">
                  <span class="font-medium text-aid-text">Organización:</span> {{ organizacionNombre }}
                </p>
              </div>
              <div class="py-1">
                <button
                  type="button"
                  class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-aid-text transition-colors hover:bg-aid-gray-50"
                  @click="irRecuperar"
                >
                  <svg class="h-4 w-4 text-aid-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  Recuperar identidad
                </button>
                <button
                  type="button"
                  class="flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm text-aid-red transition-colors hover:bg-aid-red/10"
                  @click="borrarIdentidad"
                >
                  <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                  Borrar identidad
                </button>
              </div>
            </div>
          </template>
          <template v-else>
            <RouterLink
              to="/identidad"
              class="flex items-center gap-2 rounded-lg bg-aid-teal px-3 py-2 text-sm font-medium text-white transition-colors hover:bg-aid-teal/90"
            >
              <span class="h-2 w-2 rounded-full bg-white/80"></span>
              Registrar identidad
            </RouterLink>
          </template>
        </div>
      </header>

      <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <RouterView />
      </main>

      <footer class="border-t border-aid-gray-100 bg-white py-6">
        <div class="mx-auto max-w-6xl px-4 text-center text-xs text-aid-text-muted">
          <p>AidChain — Ledger criptográfico para trazabilidad humanitaria.</p>
        </div>
      </footer>
    </div>
  </div>
</template>