import { defineStore } from 'pinia'
import api from '../services/api'

export const useCatalogStore = defineStore('catalog', {
  state: () => ({
    organizations: [],
    shelters: [],
    beneficiaries: [],
    despachosPendientes: [],
    cargando: false,
    error: null,
  }),
  actions: {
    async cargarCatalogos() {
      this.cargando = true
      this.error = null
      try {
        const [orgs, shelters] = await Promise.all([
          api.get('/organizations'),
          api.get('/shelters'),
        ])
        this.organizations = orgs.data
        this.shelters = shelters.data
      } catch {
        this.error = 'No se pudieron cargar los catálogos. Verifica la conexión con el backend.'
      } finally {
        this.cargando = false
      }
    },

    async cargarBeneficiarios(shelterId = null) {
      const params = shelterId ? { shelterId } : {}
      const { data } = await api.get('/beneficiaries', { params })
      this.beneficiaries = data
      return data
    },

    /**
     * Registra un beneficiario en el censo.
     * @param {{nombre: ?string, shelterId: number, datosDemograficos: ?object}} datos
     */
    async crearBeneficiario(datos) {
      const { data } = await api.post('/beneficiaries', datos)
      this.beneficiaries.unshift(data)
      return data
    },

    /**
     * Carga los despachos pendientes de confirmación de recepción (Fase 4).
     * @param {?number} shelterId - filtra por refugio de destino (opcional).
     */
    async cargarDespachosPendientes(shelterId = null) {
      const params = shelterId ? { shelterId } : {}
      const { data } = await api.get('/ledger/dispatches/pending', { params })
      this.despachosPendientes = data
      return data
    },
  },
})
