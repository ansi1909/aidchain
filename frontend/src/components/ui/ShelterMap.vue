<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick, computed } from 'vue'
import { RouterLink } from 'vue-router'
import 'leaflet/dist/leaflet.css'
import L from 'leaflet'

const props = defineProps({
  shelters: {
    type: Array,
    default: () => []
  },
  estadisticasPorRefugio: {
    type: Array,
    default: () => []
  },
  centerShelterId: {
    type: Number,
    default: null
  },
  esAdmin: {
    type: Boolean,
    default: false
  }
})

const mapContainer = ref(null)
let mapInstance = null
let markersLayer = null

const refugiosConCoordenadas = computed(() => {
  return props.shelters.filter(s => {
    const lat = Number(s.latitud)
    const lng = Number(s.longitud)
    return !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0
  })
})

const refugiosSinCoordenadas = computed(() => {
  return props.shelters.filter(s => {
    const lat = Number(s.latitud)
    const lng = Number(s.longitud)
    return isNaN(lat) || isNaN(lng) || lat === 0 || lng === 0
  })
})

function getStatForShelter(shelterId) {
  return props.estadisticasPorRefugio.find(st => Number(st.id) === Number(shelterId)) || {
    pendientes: 0,
    criticas: 0,
    porcentajePromedio: 100
  }
}

function createCustomMarkerIcon(stat) {
  let colorClass = 'bg-aid-teal border-white'
  let ringClass = 'ring-aid-teal/30'
  
  if (stat.criticas > 0) {
    colorClass = 'bg-aid-danger border-white'
    ringClass = 'ring-aid-danger/40 animate-pulse'
  } else if (stat.pendientes > 0) {
    colorClass = 'bg-aid-warning border-white'
    ringClass = 'ring-aid-warning/40'
  } else {
    colorClass = 'bg-aid-success border-white'
    ringClass = 'ring-aid-success/30'
  }

  const html = `
    <div class="relative flex items-center justify-center w-12 h-12 -top-6 -left-6">
      <span class="absolute w-8 h-8 rounded-full ${ringClass} ring-4"></span>
      <div class="relative flex flex-col items-center">
        <div class="w-8 h-8 rounded-full shadow-lg border-2 ${colorClass} flex items-center justify-center text-white font-bold text-xs">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
        </div>
        <div class="w-0 h-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent border-t-[8px] border-t-current -mt-1 ${colorClass.split(' ')[0]}"></div>
      </div>
    </div>
  `
  return L.divIcon({
    className: 'custom-shelter-pin',
    html: html,
    iconSize: [48, 48],
    iconAnchor: [24, 44],
    popupAnchor: [0, -40]
  })
}

function renderMap() {
  if (!mapContainer.value) return

  if (!mapInstance) {
    // Coordenadas por defecto (Venezuela centrado)
    const defaultCenter = [10.4806, -66.9036]
    const defaultZoom = 6

    mapInstance = L.map(mapContainer.value, {
      center: defaultCenter,
      zoom: defaultZoom,
      zoomControl: true,
      attributionControl: false
    })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      subdomains: ['a', 'b', 'c']
    }).addTo(mapInstance)

    markersLayer = L.layerGroup().addTo(mapInstance)
  }

  markersLayer.clearLayers()

  if (refugiosConCoordenadas.value.length === 0) {
    return
  }

  const bounds = L.latLngBounds()

  refugiosConCoordenadas.value.forEach(s => {
    const lat = Number(s.latitud)
    const lng = Number(s.longitud)
    const stat = getStatForShelter(s.id)

    bounds.extend([lat, lng])

    const icon = createCustomMarkerIcon(stat)
    const marker = L.marker([lat, lng], { icon, title: s.nombre })

    const badgeColor = stat.criticas > 0
      ? 'text-aid-danger bg-red-50 border-red-200'
      : stat.pendientes > 0
      ? 'text-aid-warning bg-orange-50 border-orange-200'
      : 'text-aid-success bg-green-50 border-green-200'

    const badgeText = stat.criticas > 0
      ? `${stat.criticas} críticas (${stat.pendientes} pend.)`
      : stat.pendientes > 0
      ? `${stat.pendientes} pendientes`
      : '100% Cubierto'

    const popupHtml = `
      <div class="p-3 max-w-[250px] text-left font-sans">
        <div class="flex items-center justify-between gap-2 border-b border-aid-gray-100 pb-2 mb-2">
          <h4 class="font-bold text-aid-navy text-sm m-0 leading-tight truncate">${s.nombre}</h4>
          <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-semibold border ${badgeColor}">
            ${stat.criticas > 0 ? 'CRÍTICA' : stat.pendientes > 0 ? 'ALERTA' : 'ÓPTIMO'}
          </span>
        </div>
        
        <p class="text-xs text-aid-text-light m-0 mb-2.5 flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 text-aid-text-muted shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <span class="truncate">${s.zona || 'Zona no especificada'}</span>
        </p>

        <div class="bg-aid-gray-50 rounded-lg p-2 border border-aid-gray-100 text-xs mb-3 space-y-1">
          <div class="flex justify-between items-center">
            <span class="text-aid-text-muted">Estado logístico:</span>
            <span class="font-semibold ${stat.criticas > 0 ? 'text-aid-danger' : stat.pendientes > 0 ? 'text-aid-warning' : 'text-aid-success'}">
              ${badgeText}
            </span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-aid-text-muted">Coordenadas:</span>
            <span class="font-mono text-[10px] text-aid-text">${lat.toFixed(4)}, ${lng.toFixed(4)}</span>
          </div>
        </div>

        <div class="flex items-center justify-between gap-2 border-t border-aid-gray-100 pt-2.5">
          <a href="/despacho?shelterId=${s.id}" class="rounded bg-aid-teal px-2.5 py-1 text-xs font-semibold text-white hover:bg-aid-teal-700 transition-colors">
            Realizar despacho
          </a>
          <a href="/censo?shelterId=${s.id}" class="text-xs font-medium text-aid-text-light hover:text-aid-navy transition-colors">
            Ver censo →
          </a>
        </div>
      </div>
    `

    marker.bindPopup(popupHtml, {
      className: 'custom-shelter-popup',
      closeButton: true,
      minWidth: 240
    })

    marker.addTo(markersLayer)
  })

  // Ajustar vista del mapa
  if (props.centerShelterId) {
    const target = refugiosConCoordenadas.value.find(s => Number(s.id) === Number(props.centerShelterId))
    if (target) {
      mapInstance.setView([Number(target.latitud), Number(target.longitud)], 14)
      return
    }
  }

  if (refugiosConCoordenadas.value.length === 1) {
    const s = refugiosConCoordenadas.value[0]
    mapInstance.setView([Number(s.latitud), Number(s.longitud)], 12)
  } else if (refugiosConCoordenadas.value.length > 1) {
    mapInstance.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 })
  }
}

function centerOnShelter(s) {
  if (!mapInstance) return
  const lat = Number(s.latitud)
  const lng = Number(s.longitud)
  if (!isNaN(lat) && !isNaN(lng)) {
    mapInstance.setView([lat, lng], 14, { animate: true })
    // Abrir popup de ese marcador si coincide
    markersLayer.eachLayer(layer => {
      if (layer.getLatLng && layer.getLatLng().lat === lat && layer.getLatLng().lng === lng) {
        layer.openPopup()
      }
    })
  }
}

watch(
  () => [props.shelters, props.estadisticasPorRefugio, props.centerShelterId],
  () => {
    nextTick(() => renderMap())
  },
  { deep: true }
)

onMounted(() => {
  nextTick(() => renderMap())
})

onBeforeUnmount(() => {
  if (mapInstance) {
    mapInstance.remove()
    mapInstance = null
  }
})
</script>

<template>
  <div class="flex flex-col rounded-2xl border border-aid-gray-200 bg-white shadow-sm overflow-hidden">
    <!-- Encabezado del mapa con leyenda interactiva -->
    <div class="flex flex-col gap-4 border-b border-aid-gray-100 bg-aid-gray-50/60 p-5 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h3 class="flex items-center gap-2.5 text-base font-bold text-aid-navy">
          <svg class="h-5 w-5 text-aid-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Mapa Georreferenciado de Refugios
        </h3>
        <p class="text-xs text-aid-text-light">
          Ubicación en tiempo real y estado logístico de los centros de acopio en territorio.
        </p>
      </div>

      <!-- Leyenda de marcadores -->
      <div class="flex flex-wrap items-center gap-3.5 text-xs font-medium text-aid-text-light">
        <span class="flex items-center gap-1.5">
          <span class="h-3 w-3 rounded-full bg-aid-danger ring-2 ring-red-200"></span>
          Prioridad crítica
        </span>
        <span class="flex items-center gap-1.5">
          <span class="h-3 w-3 rounded-full bg-aid-warning ring-2 ring-orange-200"></span>
          Demandas pendientes
        </span>
        <span class="flex items-center gap-1.5">
          <span class="h-3 w-3 rounded-full bg-aid-success ring-2 ring-green-200"></span>
          100% Cubierto
        </span>
      </div>
    </div>

    <!-- Cuerpo: Mapa y barra lateral de accesos rápidos -->
    <div class="grid grid-cols-1 lg:grid-cols-4 min-h-[420px]">
      <!-- Contenedor del Mapa Leaflet -->
      <div class="relative lg:col-span-3 h-[380px] sm:h-[450px] lg:h-auto w-full bg-aid-gray-100">
        <div ref="mapContainer" class="absolute inset-0 z-10 w-full h-full" />

        <!-- Overlay si no hay ningún refugio con coordenadas -->
        <div
          v-if="refugiosConCoordenadas.length === 0"
          class="absolute inset-0 z-20 flex flex-col items-center justify-center bg-white/90 p-6 text-center backdrop-blur-sm"
        >
          <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-aid-teal/10">
            <svg class="h-7 w-7 text-aid-teal" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <p class="text-base font-bold text-aid-navy">No hay coordenadas GPS registradas en el mapa</p>
          <p class="mt-1 max-w-md text-xs text-aid-text-light">
            Asigna latitud y longitud a tus centros en el módulo de gestión para visualizar su ubicación interactiva y planificar rutas de despacho.
          </p>
          <RouterLink
            to="/refugios"
            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-aid-teal px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors hover:bg-aid-teal-700"
          >
            Configurar coordenadas GPS →
          </RouterLink>
        </div>
      </div>

      <!-- Lista lateral de centros con coordenadas o avisos de sin coordenadas -->
      <div class="flex flex-col border-t border-aid-gray-100 lg:border-t-0 lg:border-l bg-white max-h-[450px] overflow-y-auto">
        <div class="border-b border-aid-gray-100 px-4 py-3 bg-aid-gray-50/50">
          <h4 class="text-xs font-bold uppercase tracking-wider text-aid-navy">
            Centros en el mapa ({{ refugiosConCoordenadas.length }})
          </h4>
        </div>

        <div v-if="refugiosConCoordenadas.length === 0" class="p-4 text-center text-xs text-aid-text-muted">
          Sin centros activos para ubicar.
        </div>

        <ul v-else class="divide-y divide-aid-gray-100 flex-1 overflow-y-auto">
          <li
            v-for="s in refugiosConCoordenadas"
            :key="s.id"
            class="flex items-center justify-between p-3.5 hover:bg-aid-teal-50/40 cursor-pointer transition-colors group"
            @click="centerOnShelter(s)"
          >
            <div class="min-w-0 pr-2">
              <p class="text-sm font-semibold text-aid-navy truncate group-hover:text-aid-teal transition-colors">
                {{ s.nombre }}
              </p>
              <p class="text-xs text-aid-text-light truncate">{{ s.zona }}</p>
            </div>
            <div class="shrink-0 flex items-center gap-2">
              <span
                class="h-2.5 w-2.5 rounded-full"
                :class="getStatForShelter(s.id).criticas > 0 ? 'bg-aid-danger' : getStatForShelter(s.id).pendientes > 0 ? 'bg-aid-warning' : 'bg-aid-success'"
                :title="getStatForShelter(s.id).pendientes > 0 ? `${getStatForShelter(s.id).pendientes} pendientes` : 'Cubierto'"
              />
              <svg class="w-4 h-4 text-aid-text-muted group-hover:text-aid-teal transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </li>
        </ul>

        <!-- Aviso de centros sin coordenadas -->
        <div v-if="refugiosSinCoordenadas.length > 0" class="border-t border-aid-gray-100 bg-aid-warning/10 p-3.5">
          <div class="flex items-start gap-2 text-xs text-aid-text">
            <svg class="w-4 h-4 text-aid-warning shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <div>
              <p class="font-semibold text-aid-navy">
                {{ refugiosSinCoordenadas.length }} {{ refugiosSinCoordenadas.length === 1 ? 'refugio sin ubicación' : 'refugios sin ubicación' }}
              </p>
              <RouterLink to="/refugios" class="text-aid-teal font-medium hover:underline block mt-0.5">
                Asignar coordenadas GPS →
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
.custom-shelter-popup .leaflet-popup-content-wrapper {
  padding: 0;
  border-radius: 0.75rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
  border: 1px solid #E2E8F0;
  overflow: hidden;
}
.custom-shelter-popup .leaflet-popup-content {
  margin: 0;
  line-height: 1.4;
}
.custom-shelter-popup .leaflet-popup-close-button {
  top: 10px !important;
  right: 10px !important;
  color: #64748B !important;
  font-weight: bold !important;
}
.custom-shelter-popup .leaflet-popup-close-button:hover {
  color: #0F172A !important;
}
</style>
