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
const userMenuOpen = ref(false)
const refugiosMenuOpen = ref(false)

const nav = [
  { to: '/', label: 'Dashboard', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
  { to: '/censo', label: 'Censo', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6 4h8a2 2 0 002-2v-8a2 2 0 00-2-2h-5.5' },
  { to: '/despacho', label: 'Despacho', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  { to: '/recepcion', label: 'Recepción', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  { to: '/necesidades', label: 'Necesidades', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { to: '/inventario', label: 'Inventario', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { to: '/entrega', label: 'Padrón', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
]

const esAdmin = computed(() => (identity.coordinator?.roles ?? []).includes('admin'))

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
      class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-aid-gray-100 bg-white transition-transform lg:translate-x-0"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Marca -->
      <RouterLink to="/" class="flex items-center gap-2 border-b border-aid-gray-100 px-5 py-4" @click="sidebarOpen = false">
        <img :src="logoUrl" alt="AidChain" class="h-9 w-auto" />
        <div class="flex flex-col">
          <span class="text-sm font-bold leading-tight tracking-tight text-aid-navy">
            AID<span class="text-aid-teal">CHAIN</span>
          </span>
          <span class="text-[10px] font-medium uppercase tracking-wider text-aid-gray">
            Trazabilidad humanitaria
          </span>
        </div>
      </RouterLink>

      <!-- Navegación -->
      <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <RouterLink
          v-for="item in nav"
          :key="item.to"
          :to="item.to"
          class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-aid-text-light transition-colors hover:bg-aid-teal-50 hover:text-aid-teal"
          :class="route.path === item.to ? 'bg-aid-teal-50 text-aid-teal' : ''"
          @click="sidebarOpen = false"
        >
          <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
          <span>{{ item.label }}</span>
        </RouterLink>

        <!-- Grupo Refugios (solo admin) -->
        <div v-if="esAdmin" class="space-y-1">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-aid-text-light transition-colors hover:bg-aid-teal-50 hover:text-aid-teal"
            :class="refugiosMenuOpen ? 'bg-aid-teal-50 text-aid-teal' : ''"
            @click="refugiosMenuOpen = !refugiosMenuOpen"
          >
            <div class="flex items-center gap-3">
              <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
              <span>Refugios</span>
            </div>
            <svg
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

          <div v-if="refugiosMenuOpen" class="ml-8 space-y-1">
            <RouterLink
              to="/refugios"
              class="block rounded-lg px-3 py-2 text-sm text-aid-text-light transition-colors hover:bg-aid-teal-50 hover:text-aid-teal"
              :class="route.path === '/refugios' ? 'bg-aid-teal-50 text-aid-teal' : ''"
              @click="sidebarOpen = false"
            >
              Gestión de refugios
            </RouterLink>
          </div>
        </div>
      </nav>

      <div class="border-t border-aid-gray-100 px-4 py-3 text-[11px] text-aid-text-muted">
        Ledger criptográfico humanitario
      </div>
    </aside>

    <!-- Contenido principal -->
    <div class="flex min-h-screen flex-1 flex-col lg:pl-64">
      <!-- Barra superior -->
      <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-aid-gray-100 bg-white/95 px-4 py-2.5 backdrop-blur-sm">
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="rounded-lg p-2 text-aid-text hover:bg-aid-gray-100 lg:hidden"
            @click="sidebarOpen = true"
          >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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