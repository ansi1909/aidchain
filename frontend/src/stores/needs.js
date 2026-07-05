import { defineStore } from 'pinia'
import api from '../services/api'

export const useNeedsStore = defineStore('needs', {
  state: () => ({
    needs: [], // Necesidades del refugio actual
    dashboardNeeds: [], // Todas las necesidades para el dashboard
    cargando: false,
    error: null,
  }),

  actions: {
    /**
     * Carga las necesidades de un refugio específico.
     * @param {number} shelterId - ID del refugio
     * @param {?string} estado - Filtro opcional por estado (pendiente, parcialmente_satisfecho, satisfecho)
     */
    async cargarNecesidadesShelter(shelterId, estado = null) {
      this.cargando = true
      this.error = null
      try {
        const params = estado ? { estado } : {}
        const { data } = await api.get(`/shelters/${shelterId}/needs`, { params })
        this.needs = data
        return data
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudieron cargar las necesidades.'
        throw err
      } finally {
        this.cargando = false
      }
    },

    /**
     * Carga todas las necesidades para el dashboard (vista consolidada).
     */
    async cargarDashboardNeeds() {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.get('/needs/dashboard')
        this.dashboardNeeds = data
        return data
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudieron cargar las necesidades del dashboard.'
        throw err
      } finally {
        this.cargando = false
      }
    },

    /**
     * Crea una nueva necesidad para un refugio.
     * @param {number} shelterId - ID del refugio
     * @param {object} datos - { item, cantidadRequerida, prioridad, notas }
     */
    async crearNecesidad(shelterId, datos) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.post(`/shelters/${shelterId}/needs`, datos)
        this.needs.push(data)
        return data
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudo crear la necesidad.'
        throw err
      } finally {
        this.cargando = false
      }
    },

    /**
     * Actualiza una necesidad existente.
     * @param {number} shelterId - ID del refugio
     * @param {number} needId - ID de la necesidad
     * @param {object} datos - { cantidadRequerida?, prioridad?, notas? }
     */
    async actualizarNecesidad(shelterId, needId, datos) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.put(`/shelters/${shelterId}/needs/${needId}`, datos)
        const index = this.needs.findIndex((n) => n.id === needId)
        if (index !== -1) {
          this.needs[index] = data
        }
        return data
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudo actualizar la necesidad.'
        throw err
      } finally {
        this.cargando = false
      }
    },

    /**
     * Elimina una necesidad.
     * @param {number} shelterId - ID del refugio
     * @param {number} needId - ID de la necesidad
     */
    async eliminarNecesidad(shelterId, needId) {
      this.cargando = true
      this.error = null
      try {
        await api.delete(`/shelters/${shelterId}/needs/${needId}`)
        this.needs = this.needs.filter((n) => n.id !== needId)
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudo eliminar la necesidad.'
        throw err
      } finally {
        this.cargando = false
      }
    },
  },
})
