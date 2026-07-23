<script setup>
import { onMounted, reactive, ref, computed } from 'vue'
import QRCode from 'qrcode'
import * as XLSX from 'xlsx'
import { useCatalogStore } from '../stores/catalog'
import { useIdentityStore } from '../stores/identity'
import BaseCard from '../components/ui/BaseCard.vue'
import BaseInput from '../components/ui/BaseInput.vue'
import BaseSelect from '../components/ui/BaseSelect.vue'
import BaseSearchSelect from '../components/ui/BaseSearchSelect.vue'
import BaseButton from '../components/ui/BaseButton.vue'
import BaseAlert from '../components/ui/BaseAlert.vue'
import BaseTooltip from '../components/ui/BaseTooltip.vue'

const catalog = useCatalogStore()
const identity = useIdentityStore()

const esAdmin = computed(() => (identity.coordinator?.roles ?? []).includes('admin'))
const esEncargadoRefugio = computed(() => (identity.coordinator?.roles ?? []).includes('encargado_refugio'))

const refugiosDisponibles = computed(() => {
  const shelterIdAsignado = identity.coordinator?.shelterId
  if (esEncargadoRefugio.value && shelterIdAsignado) {
    return catalog.shelters.filter(s => s.id === shelterIdAsignado)
  }
  return catalog.shelters
})

const form = reactive({
  shelterId: '',
  nombre: '',
  documento: '',
  sexo: '',
  telefono: '',
  edad: '',
  personas: '',
  notas: '',
  esRepresentante: 'si',
  representanteId: '',
})

const enviando = ref(false)
const error = ref(null)
const ultimo = ref(null) // { token, nombre, qrDataUrl }

// Estado para búsqueda de representantes de grupo
const buscandoRep = ref(false)
const representantesEncontrados = ref([])

// Estados para carga de Excel
const archivoExcel = ref(null)
const cargandoExcel = ref(false)
const resultadoCarga = ref(null) // { exitosos: number, errores: number, detalles: [] }
const errorCarga = ref(null)

const documentoError = computed(() => {
  if (!form.documento) return null

  // Quitar espacios en blanco al principio y al final
  const doc = form.documento.trim().toUpperCase()

  // Validar cédula venezolana: V-XXXXXX o E-XXXXXX (6-8 dígitos, guion opcional)
  // Solo V/E para beneficiarios (no pasaportes)
  if (/^[VE]-?\d{6,8}$/.test(doc)) return null

  return 'Formato inválido. Use V-XXXXXX o E-XXXXXX (6-8 dígitos, guion opcional).'
})

onMounted(async () => {
  await identity.init()
  if (catalog.shelters.length === 0) {
    await catalog.cargarCatalogos()
  }
  // Auto-seleccionar el refugio asignado para encargado de refugio
  if (esEncargadoRefugio.value && identity.coordinator?.shelterId) {
    form.shelterId = String(identity.coordinator.shelterId)
    await cargarRepresentantes()
  }
})

async function cargarRepresentantes(query = '') {
  if (!form.shelterId) return
  buscandoRep.value = true
  try {
    representantesEncontrados.value = await catalog.buscarRepresentantes(Number(form.shelterId), query)
  } finally {
    buscandoRep.value = false
  }
}

async function onSubmit() {
  error.value = null
  enviando.value = true
  try {
    const esRep = form.esRepresentante === 'si'
    const repId = !esRep && form.representanteId ? Number(form.representanteId) : null

    if (!esRep && !repId) {
      throw new Error('Debes seleccionar al representante del grupo familiar.')
    }

    const datosDemograficos = {}
    if (form.edad) datosDemograficos.edad = Number(form.edad)
    if (esRep && form.personas) datosDemograficos.personas = Number(form.personas)
    if (form.notas.trim()) datosDemograficos.notas = form.notas.trim()
    if (form.sexo) datosDemograficos.sexo = form.sexo
    if (form.telefono.trim()) datosDemograficos.telefono = form.telefono.trim()

    const beneficiario = await catalog.crearBeneficiario({
      nombre: form.nombre.trim() || null,
      documento: form.documento.trim() || null,
      shelterId: Number(form.shelterId),
      esRepresentante: esRep,
      representanteId: repId,
      datosDemograficos: Object.keys(datosDemograficos).length ? datosDemograficos : null,
    })

    const qrDataUrl = await QRCode.toDataURL(beneficiario.beneficiaryToken, {
      width: 240,
      margin: 2,
      color: {
        dark: '#1F4E79',
        light: '#FFFFFF',
      },
    })

    ultimo.value = {
      token: beneficiario.beneficiaryToken,
      nombre: beneficiario.nombre,
      qrDataUrl,
    }

    form.nombre = ''
    form.documento = ''
    form.sexo = ''
    form.telefono = ''
    form.edad = ''
    form.personas = ''
    form.notas = ''
    form.representanteId = ''

    // Actualizar lista de representantes si acabamos de registrar un nuevo representante
    if (esRep) {
      cargarRepresentantes()
    }
  } catch (err) {
    error.value = err?.response?.data?.error ?? err.message ?? 'No se pudo registrar al beneficiario.'
  } finally {
    enviando.value = false
  }
}

function descargarQr() {
  if (!ultimo.value?.qrDataUrl) return
  const link = document.createElement('a')
  link.href = ultimo.value.qrDataUrl
  link.download = `aidchain-beneficiario-${ultimo.value.token.slice(0, 8)}.png`
  link.click()
}

function onArchivoSeleccionado(event) {
  archivoExcel.value = event.target.files[0]
  errorCarga.value = null
  resultadoCarga.value = null
}

async function procesarExcel() {
  if (!archivoExcel.value || !form.shelterId) {
    errorCarga.value = 'Debes seleccionar un refugio y un archivo Excel.'
    return
  }

  cargandoExcel.value = true
  errorCarga.value = null
  resultadoCarga.value = null

  try {
    const data = await archivoExcel.value.arrayBuffer()
    const workbook = XLSX.read(data, { type: 'array' })
    const firstSheet = workbook.Sheets[workbook.SheetNames[0]]
    const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 })

    if (jsonData.length < 2) {
      throw new Error('El archivo Excel no tiene datos suficientes.')
    }

    // Asumimos que la primera fila es el encabezado
    const headers = jsonData[0].map(h => String(h).toLowerCase().trim())
    const rows = jsonData.slice(1)

    // Mapear columnas esperadas
    const colMap = {
      nombre: headers.findIndex(h => h.includes('nombre')),
      documento: headers.findIndex(h => h.includes('documento') || h.includes('cedula')),
      sexo: headers.findIndex(h => h.includes('sexo')),
      telefono: headers.findIndex(h => h.includes('telefono') || h.includes('tel')),
      edad: headers.findIndex(h => h.includes('edad')),
      familiares: headers.findIndex(h => h.includes('familiares') || h.includes('personas')),
      es_representante: headers.findIndex(h => h === 'es_representante' || h === 'representante' || h.includes('es_representante')),
      documento_representante: headers.findIndex(h => h.includes('documento_representante') || h.includes('cedula_representante') || h.includes('doc_representante')),
      notas: headers.findIndex(h => h.includes('nota')),
    }

    let exitosos = 0
    let errores = 0
    const detalles = []

    // Clasificar filas en dos pasadas: 1. Representantes, 2. Miembros
    const filasClasificadas = rows.map((row, idx) => {
      const rowNum = idx + 2
      const esRepRaw = colMap.es_representante >= 0 ? String(row[colMap.es_representante] || '').trim().toLowerCase() : ''
      const docRepRaw = colMap.documento_representante >= 0 ? String(row[colMap.documento_representante] || '').trim().toUpperCase() : ''
      // Es representante si dice 'si', 'true', '1' o si no especifica rol ni tiene documento_representante asignado
      const esRepresentante = (esRepRaw === 'no' || esRepRaw === 'false' || esRepRaw === '0' || (!esRepRaw && docRepRaw)) ? false : true
      return { row, rowNum, esRepresentante, docRepRaw }
    })

    const filasOrdenadas = [
      ...filasClasificadas.filter(item => item.esRepresentante),
      ...filasClasificadas.filter(item => !item.esRepresentante)
    ]

    for (let i = 0; i < filasOrdenadas.length; i++) {
      const item = filasOrdenadas[i]
      const { row, rowNum, esRepresentante, docRepRaw } = item

      try {
        const nombre = colMap.nombre >= 0 ? String(row[colMap.nombre] || '').trim() : null
        const documento = colMap.documento >= 0 ? String(row[colMap.documento] || '').trim() : null
        const sexoRaw = colMap.sexo >= 0 ? String(row[colMap.sexo] || '').trim().toUpperCase() : null
        let sexo = null
        if (sexoRaw) {
          if (sexoRaw === 'M' || sexoRaw === 'MASCULINO') sexo = 'M'
          else if (sexoRaw === 'F' || sexoRaw === 'FEMENINO') sexo = 'F'
        }
        const telefono = colMap.telefono >= 0 ? String(row[colMap.telefono] || '').trim() : null
        const edad = colMap.edad >= 0 ? Number(row[colMap.edad]) : null
        const familiares = colMap.familiares >= 0 ? Number(row[colMap.familiares]) : null
        const notas = colMap.notas >= 0 ? String(row[colMap.notas] || '').trim() : null

        if (!nombre) {
          throw new Error('Nombre es requerido')
        }

        if (documento) {
          const doc = documento.toUpperCase()
          if (!/^[VE]-?\d{6,8}$/.test(doc)) {
            throw new Error('Formato de documento inválido')
          }
        }

        if (!esRepresentante && !docRepRaw) {
          throw new Error('Los miembros del grupo familiar deben incluir documento_representante (cédula del jefe de familia)')
        }

        const datosDemograficos = {}
        if (edad) datosDemograficos.edad = edad
        if (esRepresentante && familiares) datosDemograficos.personas = familiares
        if (sexo) datosDemograficos.sexo = sexo
        if (telefono) datosDemograficos.telefono = telefono
        if (notas) datosDemograficos.notas = notas

        await catalog.crearBeneficiario({
          nombre: nombre || null,
          documento: documento || null,
          shelterId: Number(form.shelterId),
          esRepresentante,
          documentoRepresentante: !esRepresentante ? docRepRaw : null,
          datosDemograficos: Object.keys(datosDemograficos).length ? datosDemograficos : null,
        })

        exitosos++
      } catch (err) {
        errores++
        const errorMessage = err?.response?.data?.error || err.message || 'Error desconocido'
        detalles.push({
          fila: rowNum,
          error: errorMessage,
        })
      }
    }

    resultadoCarga.value = {
      exitosos,
      errores,
      detalles: detalles.slice(0, 20),
    }

    archivoExcel.value = null
    cargarRepresentantes()
  } catch (err) {
    errorCarga.value = err.message || 'Error al procesar el archivo Excel.'
  } finally {
    cargandoExcel.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-2xl animate-fade-in-up">
    <header class="mb-6 text-center">
      <h1 class="inline-flex items-center justify-center gap-2 text-2xl font-bold text-aid-navy">
        Censo de beneficiarios
        <BaseTooltip>
          Registra a un beneficiario y genera su código QR único para controlar el doble cobro.
        </BaseTooltip>
      </h1>
    </header>

    <BaseCard padding="lg" shadow="md">
      <form class="space-y-5" @submit.prevent="onSubmit">
        <BaseSelect
          v-model="form.shelterId"
          label="Refugio"
          placeholder="Selecciona un refugio"
          :options="refugiosDisponibles"
          value-key="id"
          label-key="nombre"
          required
          :disabled="esEncargadoRefugio"
        />

        <!-- Carga masiva por Excel -->
        <div class="rounded-lg border border-aid-gray-200 bg-aid-gray-50 p-4">
          <h3 class="mb-3 text-sm font-semibold text-aid-navy">Carga masiva desde Excel</h3>
          <p class="mb-3 text-xs text-aid-text-light">
            Carga múltiples beneficiarios desde un archivo Excel (.xlsx, .xls). Columnas esperadas: nombre, documento, sexo, teléfono, edad, familiares, es_representante, documento_representante, notas.
            <br /><span class="font-medium text-aid-teal">Nota:</span> Si <code>es_representante</code> es NO, la columna <code>documento_representante</code> es obligatoria con la cédula de su jefe de familia.
          </p>
          <div class="flex items-center gap-3">
            <input
              type="file"
              accept=".xlsx,.xls"
              @change="onArchivoSeleccionado"
              class="block w-full text-sm text-aid-text file:mr-4 file:rounded file:border-0 file:bg-aid-teal file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-aid-teal/90"
            />
            <BaseButton
              type="button"
              variant="outline"
              size="sm"
              :loading="cargandoExcel"
              :disabled="!archivoExcel || !form.shelterId"
              @click="procesarExcel"
            >
              Procesar
            </BaseButton>
          </div>
        </div>

        <BaseAlert v-if="errorCarga" variant="danger" title="Error al procesar Excel">
          {{ errorCarga }}
        </BaseAlert>

        <BaseAlert v-if="resultadoCarga" variant="info" title="Resultados de la carga">
          <div class="space-y-2">
            <p class="text-sm">
              <span class="font-medium text-aid-teal">Exitosos:</span> {{ resultadoCarga.exitosos }}
              <span class="ml-4 font-medium text-aid-red">Errores:</span> {{ resultadoCarga.errores }}
            </p>
            <div v-if="resultadoCarga.detalles.length > 0" class="mt-2 max-h-40 overflow-y-auto rounded bg-aid-gray-100 p-2">
              <p class="mb-1 text-xs font-medium text-aid-text-muted">Detalles de errores:</p>
              <ul class="space-y-1 text-xs">
                <li v-for="(detalle, idx) in resultadoCarga.detalles" :key="idx" class="text-aid-red">
                  Fila {{ detalle.fila }}: {{ detalle.error }}
                </li>
              </ul>
            </div>
          </div>
        </BaseAlert>

        <hr class="border-aid-gray-200" />

        <h3 class="text-sm font-semibold text-aid-navy">Registro individual</h3>

        <!-- Selector de Rol -->
        <div class="rounded-lg border border-aid-gray-200 bg-aid-gray-50 p-3.5">
          <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-aid-navy">Rol en el Grupo Familiar</label>
          <div class="flex flex-wrap gap-6 text-sm">
            <label class="flex items-center gap-2 cursor-pointer font-medium text-aid-navy">
              <input
                type="radio"
                v-model="form.esRepresentante"
                value="si"
                class="text-aid-teal focus:ring-aid-teal"
              />
              <span>Representante / Jefe de Familia</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer font-medium text-aid-navy">
              <input
                type="radio"
                v-model="form.esRepresentante"
                value="no"
                class="text-aid-teal focus:ring-aid-teal"
                @change="cargarRepresentantes()"
              />
              <span>Miembro / Dependiente</span>
            </label>
          </div>
        </div>

        <!-- Buscador de Representante cuando es miembro -->
        <div v-if="form.esRepresentante === 'no'" class="rounded-lg border border-aid-teal/30 bg-aid-teal/5 p-4 space-y-3">
          <div class="flex items-center justify-between">
            <label class="block text-xs font-semibold uppercase tracking-wider text-aid-navy">
              Vincular al Representante del Grupo
            </label>
            <BaseButton type="button" variant="outline" size="sm" @click="cargarRepresentantes()" :loading="buscandoRep">
              Actualizar lista
            </BaseButton>
          </div>
          <BaseSearchSelect
            v-model="form.representanteId"
            label="Representante / Jefe de Familia"
            placeholder="Selecciona o escribe para buscar representante..."
            :options="representantesEncontrados.map(r => ({
              id: String(r.id),
              label: `${r.nombre || 'Sin nombre'} (${r.documento || 'Sin doc'})`
            }))"
            value-key="id"
            label-key="label"
            :loading="buscandoRep"
            @search="cargarRepresentantes"
            required
          />
          <p class="text-xs text-aid-text-light">
            Solo aparecen personas registradas como Representantes en este refugio. Si aún no está registrado, regístralo primero como Representante antes de agregar miembros.
          </p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
          <BaseInput
            v-model="form.nombre"
            :label="form.esRepresentante === 'si' ? 'Nombre del representante' : 'Nombre del miembro'"
            placeholder="Ej. Juan Pérez"
          />

          <BaseInput
            v-model="form.documento"
            label="Documento de identidad (V/E)"
            placeholder="Ej. V-12345678"
          />
        </div>

        <BaseAlert v-if="documentoError" variant="danger" title="Formato inválido">
          {{ documentoError }}
        </BaseAlert>

        <div class="grid gap-5 sm:grid-cols-2">
          <BaseSelect
            v-model="form.sexo"
            label="Sexo"
            placeholder="Seleccionar"
            :options="[
              { value: 'M', label: 'Masculino' },
              { value: 'F', label: 'Femenino' },
            ]"
            value-key="value"
            label-key="label"
          />

          <BaseInput
            v-model="form.telefono"
            label="Teléfono"
            placeholder="Ej. 0414-1234567"
          />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
          <BaseInput
            v-model="form.edad"
            type="number"
            min="0"
            label="Edad"
            placeholder="Ej. 35"
          />

          <BaseInput
            v-if="form.esRepresentante === 'si'"
            v-model="form.personas"
            type="number"
            min="1"
            label="Personas a cargo"
            placeholder="4"
          />

          <BaseInput
            v-model="form.notas"
            type="textarea"
            :rows="2"
            label="Notas"
            placeholder="Condiciones médicas, menores, etc."
          />
        </div>

        <BaseAlert v-if="error" variant="danger" title="Error al registrar">
          {{ error }}
        </BaseAlert>

        <BaseButton
          type="submit"
          variant="primary"
          size="lg"
          block
          :loading="enviando"
          :disabled="!form.shelterId || documentoError !== null"
        >
          {{ enviando ? 'Registrando…' : 'Registrar y generar QR' }}
        </BaseButton>
      </form>
    </BaseCard>

    <BaseCard
      v-if="ultimo"
      padding="lg"
      shadow="md"
      class="mt-6 border-aid-teal-100 text-center"
    >
      <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-aid-teal-50 px-3 py-1 text-sm font-medium text-aid-teal">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        Beneficiario registrado
      </div>

      <h3 class="text-lg font-semibold text-aid-navy">
        {{ ultimo.nombre || 'Beneficiario sin nombre' }}
      </h3>
      <p class="mt-1 text-xs text-aid-text-muted">Escanea este código para registrar una entrega</p>

      <div class="mt-5 inline-block rounded-2xl border-4 border-aid-teal-50 bg-white p-3 shadow-sm">
        <img
          :src="ultimo.qrDataUrl"
          alt="QR del beneficiario"
          class="h-56 w-56 rounded-lg"
        />
      </div>

      <div class="mt-5 space-y-2">
        <p class="break-all text-xs text-aid-text-light">
          <span class="font-medium text-aid-text">Token:</span> {{ ultimo.token }}
        </p>
        <BaseButton variant="outline" size="sm" @click="descargarQr">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          Descargar QR
        </BaseButton>
      </div>
    </BaseCard>
  </div>
</template>
