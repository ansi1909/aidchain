import axios from 'axios'

// En desarrollo, /api es redirigido al backend Symfony por el proxy de Vite
// configurado en vite.config.js. En producción, apunta a la URL real de la API.
const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('jwt_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
