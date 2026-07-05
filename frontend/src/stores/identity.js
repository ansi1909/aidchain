import { defineStore } from 'pinia'
import api from '../services/api'
import {
  generateAndStoreKeyPair,
  hasStoredKey,
  clearStoredKey,
} from '../services/crypto'

const STORAGE_KEY = 'aidchain_coordinator'

function loadFromStorage() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    // Retrocompatibilidad: identidades guardadas antes del multi-rol tenían `rol`.
    if (parsed && !Array.isArray(parsed.roles)) {
      parsed.roles = parsed.rol ? [parsed.rol] : []
      delete parsed.rol
    }
    return parsed
  } catch {
    return null
  }
}

export const useIdentityStore = defineStore('identity', {
  state: () => ({
    coordinator: loadFromStorage(), // { id, nombre, roles, organizationId, shelterId }
    keyPresent: false,
    cargando: false,
    error: null,
  }),
  getters: {
    // Identidad completa solo si hay registro en backend + llave privada local.
    isReady: (state) => state.coordinator !== null && state.keyPresent,
    coordinatorId: (state) => state.coordinator?.id ?? null,
    roles: (state) => state.coordinator?.roles ?? [],
    esDespachador: (state) => (state.coordinator?.roles ?? []).includes('despachador'),
    esEncargado: (state) => (state.coordinator?.roles ?? []).includes('encargado_refugio'),
  },
  actions: {
    async init() {
      this.keyPresent = await hasStoredKey()
    },

    /**
     * Genera la identidad criptográfica y registra al coordinador en el backend.
     * @param {{nombre: string, documento: string, roles: string[], organizationId: number, shelterId: ?number}} datos
     */
    async register(datos) {
      this.cargando = true
      this.error = null
      try {
        const publicKey = await generateAndStoreKeyPair()
        this.keyPresent = true

        const { data } = await api.post('/coordinators/register', {
          nombre: datos.nombre,
          documento: datos.documento,
          roles: datos.roles,
          organizationId: datos.organizationId,
          shelterId: datos.shelterId ?? null,
          publicKey,
        })

        this.coordinator = {
          id: data.id,
          nombre: data.nombre,
          documento: data.documento,
          roles: data.roles ?? [],
          organizationId: data.organizationId,
          shelterId: data.shelterId,
        }
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.coordinator))
        return true
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudo registrar la identidad.'
        return false
      } finally {
        this.cargando = false
      }
    },

    /**
     * Recupera la identidad de un coordinador existente en un nuevo dispositivo.
     * @param {{documento: string}} datos
     */
    async recover(datos) {
      this.cargando = true
      this.error = null
      try {
        const publicKey = await generateAndStoreKeyPair()
        this.keyPresent = true

        const { data } = await api.post('/coordinators/recover', {
          documento: datos.documento,
          publicKey,
        })

        this.coordinator = {
          id: data.id,
          nombre: data.nombre,
          documento: data.documento,
          roles: data.roles ?? [],
          organizationId: data.organizationId,
          shelterId: data.shelterId,
        }
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.coordinator))
        return true
      } catch (err) {
        this.error = err?.response?.data?.error ?? 'No se pudo recuperar la identidad.'
        return false
      } finally {
        this.cargando = false
      }
    },

    async reset() {
      await clearStoredKey()
      localStorage.removeItem(STORAGE_KEY)
      this.coordinator = null
      this.keyPresent = false
    },
  },
})
