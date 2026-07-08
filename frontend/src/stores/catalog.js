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
        // Los selectores operativos solo muestran refugios activos.
        const [orgs, shelters] = await Promise.all([
          api.get('/organizations'),
          api.get('/shelters', { params: { soloActivos: true } }),
        ])
        this.organizations = orgs.data
        this.shelters = shelters.data
      } catch {
        this.error = 'No se pudieron cargar los catálogos. Verifica la conexión con el backend.'
      } finally {
        this.cargando = false
      }
    },

    /**
     * Carga refugios para el módulo de administración.
     * @param {boolean} soloActivos - si true, omite los inactivos.
     */
    async cargarRefugios(soloActivos = false) {
      const params = soloActivos ? { soloActivos: true } : {}
      const { data } = await api.get('/shelters', { params })
      return data
    },

    /**
     * Crea un refugio.
     * @param {{nombre: string, zona: string, latitud: ?string, longitud: ?string, capacidadCensada: ?number, organizationId: ?number}} datos
     */
    async crearRefugio(datos) {
      const { data } = await api.post('/shelters', datos)
      return data
    },

    /**
     * Actualiza un refugio existente.
     * @param {number} id
     * @param {object} datos
     */
    async actualizarRefugio(id, datos) {
      const { data } = await api.put(`/shelters/${id}`, datos)
      return data
    },

    /**
     * Activa o inactiva un refugio (soft-delete).
     * @param {number} id
     * @param {boolean} activo
     * @param {{coordinatorId: number, firma: string}} firmaData - datos de firma para trazabilidad
     */
    async cambiarEstadoRefugio(id, activo, firmaData) {
      const { data } = await api.patch(`/shelters/${id}/estado`, { activo, ...firmaData })
      return data
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
