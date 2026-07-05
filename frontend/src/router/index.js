import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import IdentityView from '../views/IdentityView.vue'
import CensoView from '../views/CensoView.vue'
import DespachoView from '../views/DespachoView.vue'
import RecepcionView from '../views/RecepcionView.vue'
import NecesidadesView from '../views/NecesidadesView.vue'
import InventarioView from '../views/InventarioView.vue'

const routes = [
  {
    path: '/',
    name: 'dashboard',
    component: DashboardView,
  },
  {
    path: '/identidad',
    name: 'identidad',
    component: IdentityView,
  },
  {
    path: '/censo',
    name: 'censo',
    component: CensoView,
  },
  {
    path: '/despacho',
    name: 'despacho',
    component: DespachoView,
  },
  {
    path: '/recepcion',
    name: 'recepcion',
    component: RecepcionView,
  },
  {
    path: '/necesidades',
    name: 'necesidades',
    component: NecesidadesView,
  },
  {
    path: '/inventario',
    name: 'inventario',
    component: InventarioView,
  },
  // Aquí se irán agregando las vistas de las siguientes fases:
  // /importar          -> importador de Excel/CSV para ONGs — Fase 6
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
