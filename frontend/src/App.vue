<script setup>
import { onMounted } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import { useIdentityStore } from './stores/identity'
import StatusBadge from './components/ui/StatusBadge.vue'
import logoUrl from './assets/logo-aidchain.png'

const identity = useIdentityStore()
const route = useRoute()

const nav = [
  { to: '/', label: 'Dashboard', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z' },
  { to: '/identidad', label: 'Identidad', icon: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3 3 0 01-3-3m0 0a3 3 0 013-3m0 0a3 3 0 013 3m0 0a3 3 0 01-3 3' },
  { to: '/censo', label: 'Censo', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6 4h8a2 2 0 002-2v-8a2 2 0 00-2-2h-5.5' },
  { to: '/despacho', label: 'Despacho', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  { to: '/recepcion', label: 'Recepción', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
  { to: '/necesidades', label: 'Necesidades', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
  { to: '/inventario', label: 'Inventario', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
]

onMounted(() => {
  identity.init()
})
</script>

<template>
  <div class="min-h-screen bg-aid-gray-50">
    <header class="sticky top-0 z-50 border-b border-aid-gray-100 bg-white/95 backdrop-blur-sm">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
        <RouterLink to="/" class="flex items-center gap-2">
          <img :src="logoUrl" alt="AidChain" class="h-9 w-auto" />
          <div class="hidden flex-col sm:flex">
            <span class="text-sm font-bold leading-tight tracking-tight text-aid-navy">
              AID<span class="text-aid-teal">CHAIN</span>
            </span>
            <span class="text-[10px] font-medium uppercase tracking-wider text-aid-gray">
              Trazabilidad humanitaria
            </span>
          </div>
        </RouterLink>

        <nav class="flex items-center gap-1">
          <RouterLink
            v-for="item in nav"
            :key="item.to"
            :to="item.to"
            class="group relative flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-aid-text-light transition-colors hover:bg-aid-teal-50 hover:text-aid-teal"
            :class="route.path === item.to ? 'bg-aid-teal-50 text-aid-teal' : ''"
          >
            <svg
              class="h-4 w-4 transition-transform group-hover:-translate-y-0.5"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              stroke-width="2"
            >
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
            <span class="hidden sm:inline">{{ item.label }}</span>
          </RouterLink>
        </nav>
      </div>
    </header>

    <div class="border-b border-aid-gray-100 bg-white">
      <div class="mx-auto flex max-w-6xl items-center justify-end gap-3 px-4 py-2">
        <StatusBadge
          v-if="identity.isReady"
          variant="success"
          :label="identity.coordinator.nombre"
          dot
        />
        <StatusBadge
          v-else
          variant="warning"
          label="Sin identidad criptográfica"
          dot
        />
        <span class="text-xs text-aid-text-muted">
          {{ identity.isReady ? 'Listo para firmar eventos' : 'Regístrate en Identidad' }}
        </span>
      </div>
    </div>

    <main class="mx-auto max-w-6xl px-4 py-8">
      <RouterView />
    </main>

    <footer class="mt-auto border-t border-aid-gray-100 bg-white py-6">
      <div class="mx-auto max-w-6xl px-4 text-center text-xs text-aid-text-muted">
        <p>AidChain — Ledger criptográfico para trazabilidad humanitaria.</p>
      </div>
    </footer>
  </div>
</template>