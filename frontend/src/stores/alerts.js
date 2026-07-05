import { defineStore } from 'pinia'
import api from '../services/api'

export const useAlertsStore = defineStore('alerts', {
  state: () => ({
    alertas: [],
    eventosCongelados: [],
    cargando: false,
    error: null,
  }),
  getters: {
    criticas: (state) => state.alertas.filter((a) => a.severidad === 'critica'),
  },
  actions: {
    async cargarAlertasActivas() {
      this.cargando = true
      this.error = null
      try {
        // Endpoint a implementar en el backend: GET /api/alerts?resuelto=false
        const { data } = await api.get('/alerts', { params: { resuelto: false } })
        this.alertas = data
      } catch (err) {
        this.error = 'No se pudieron cargar las alertas. Verifica la conexión con el backend.'
      } finally {
        this.cargando = false
      }
    },

    async cargarEventosCongelados() {
      this.cargando = true
      this.error = null
      try {
        const { data } = await api.get('/ledger/dispatches/frozen')
        this.eventosCongelados = data
      } catch (err) {
        this.error = 'No se pudieron cargar los eventos congelados. Verifica la conexión con el backend.'
      } finally {
        this.cargando = false
      }
    },

    async liberarEvento(eventoId) {
      try {
        await api.post(`/ledger/dispatches/${eventoId}/release`)
        await this.cargarEventosCongelados()
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudo liberar el evento.'
        throw err
      }
    },

    async rechazarEvento(eventoId) {
      try {
        await api.post(`/ledger/dispatches/${eventoId}/reject`)
        await this.cargarEventosCongelados()
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudo rechazar el evento.'
        throw err
      }
    },
  },
})
