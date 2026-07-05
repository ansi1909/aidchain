import { defineStore } from 'pinia'
import api from '../services/api'

export const useStockStore = defineStore('stock', {
  state: () => ({
    stock: [], // Stock del refugio actual
    dashboardStock: [], // Stock consolidado de todos los refugios
    cargando: false,
    error: null,
  }),

  actions: {
    /**
     * Carga el inventario de un refugio específico.
     * @param {number} shelterId - ID del refugio
     */
    async cargarStockShelter(shelterId) {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.get(`/shelters/${shelterId}/stock`)
        this.stock = data
        return data
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudo cargar el inventario.'
        throw err
      } finally {
        this.cargando = false
      }
    },

    /**
     * Carga el inventario consolidado de todos los refugios (vista de inventario).
     */
    async cargarDashboardStock() {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.get('/stock/dashboard')
        this.dashboardStock = data
        return data
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudo cargar el inventario consolidado.'
        throw err
      } finally {
        this.cargando = false
      }
    },
  },
})
