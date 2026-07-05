// Identidad criptográfica del coordinador (dApp de terreno).
//
// Genera un par de llaves ECDSA P-256 con la Web Crypto API. La llave PRIVADA
// se guarda en IndexedDB y NUNCA se envía al servidor ni sale del dispositivo;
// solo se transmite la clave pública (para registrarla) y la FIRMA de cada
// evento. La firma se produce en formato raw IEEE P1363 (r||s, 64 bytes), que
// es exactamente lo que verifica el backend (SignatureVerifierService).

const DB_NAME = 'aidchain'
const STORE_NAME = 'identity'
const KEY_ID = 'coordinator-key'

const ALGO = { name: 'ECDSA', namedCurve: 'P-256' }
const SIGN_ALGO = { name: 'ECDSA', hash: 'SHA-256' }

// --- IndexedDB (envoltorio mínimo basado en promesas) ---

function openDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1)
    request.onupgradeneeded = () => {
      request.result.createObjectStore(STORE_NAME)
    }
    request.onsuccess = () => resolve(request.result)
    request.onerror = () => reject(request.error)
  })
}

async function idbGet(key) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readonly')
    const req = tx.objectStore(STORE_NAME).get(key)
    req.onsuccess = () => resolve(req.result ?? null)
    req.onerror = () => reject(req.error)
  })
}

async function idbSet(key, value) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite')
    tx.objectStore(STORE_NAME).put(value, key)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

async function idbDelete(key) {
  const db = await openDb()
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAME, 'readwrite')
    tx.objectStore(STORE_NAME).delete(key)
    tx.oncomplete = () => resolve()
    tx.onerror = () => reject(tx.error)
  })
}

// --- Utilidades de codificación ---

function arrayBufferToBase64(buffer) {
  const bytes = new Uint8Array(buffer)
  let binary = ''
  for (let i = 0; i < bytes.length; i += 1) {
    binary += String.fromCharCode(bytes[i])
  }
  return btoa(binary)
}

function toPem(spkiBuffer) {
  const b64 = arrayBufferToBase64(spkiBuffer)
  const lines = b64.match(/.{1,64}/g).join('\n')
  return `-----BEGIN PUBLIC KEY-----\n${lines}\n-----END PUBLIC KEY-----`
}

// --- API pública ---

/**
 * Genera un par de llaves ECDSA P-256, guarda la privada en IndexedDB y
 * devuelve la clave pública en formato PEM para registrarla en el backend.
 */
export async function generateAndStoreKeyPair() {
  const keyPair = await crypto.subtle.generateKey(ALGO, true, ['sign', 'verify'])
  const spki = await crypto.subtle.exportKey('spki', keyPair.publicKey)
  const publicKeyPem = toPem(spki)

  await idbSet(KEY_ID, { privateKey: keyPair.privateKey, publicKeyPem })

  return publicKeyPem
}

/** Indica si ya existe una identidad (llave) almacenada en el dispositivo. */
export async function hasStoredKey() {
  return (await idbGet(KEY_ID)) !== null
}

/** Devuelve la clave pública PEM almacenada, o null si no hay identidad. */
export async function getStoredPublicKeyPem() {
  const record = await idbGet(KEY_ID)
  return record ? record.publicKeyPem : null
}

/** Elimina la identidad local (llave privada incluida). */
export async function clearStoredKey() {
  await idbDelete(KEY_ID)
}

/**
 * Firma un mensaje (string) con la llave privada almacenada y devuelve la
 * firma en base64 (formato raw r||s, listo para el backend).
 */
export async function signMessage(message) {
  const record = await idbGet(KEY_ID)
  if (!record) {
    throw new Error('No hay una identidad criptográfica en este dispositivo.')
  }
  const data = new TextEncoder().encode(message)
  const signature = await crypto.subtle.sign(SIGN_ALGO, record.privateKey, data)
  return arrayBufferToBase64(signature)
}

/**
 * Canonicaliza la cantidad a su forma numérica mínima (sin ceros finales tras
 * el punto decimal). DEBE coincidir con CryptoLedgerService::normalizeCantidad
 * del backend, porque `cantidad` se persiste como decimal(12,3) y al releerse
 * vuelve como "50.000"; sin esto, la firma y el hash no cuadrarían.
 */
function normalizeCantidad(v) {
  let c = String(v ?? '').trim()
  if (c === '') return '0'
  if (c.includes('.')) {
    c = c.replace(/0+$/, '').replace(/\.$/, '')
  }
  return c === '' ? '0' : c
}

/**
 * Serialización canónica y determinista de un evento, IDÉNTICA a la del
 * backend (App\Service\CryptoLedgerService::canonicalPayload):
 * claves ordenadas alfabéticamente y JSON sin escapar unicode ni barras.
 * JSON.stringify de JS ya cumple ambas condiciones.
 *
 * @param {object} e - Campos del evento.
 * @param {string}      e.tipo
 * @param {string}      e.item
 * @param {string}      e.cantidad         (string, p. ej. "20")
 * @param {string}      e.unidad
 * @param {?string}     e.beneficiaryToken
 * @param {number}      e.shelterId
 * @param {number}      e.organizationId
 * @param {number}      e.coordinatorId
 * @param {string}      e.canalOrigen
 * @param {?string}     e.loteId
 */
export function buildCanonicalPayload(e) {
  // Orden alfabético ascendente de claves (equivalente a ksort en PHP).
  const ordered = {
    beneficiary_token: e.beneficiaryToken ?? null,
    canal_origen: e.canalOrigen,
    cantidad: normalizeCantidad(e.cantidad),
    coordinator_id: e.coordinatorId,
    item: e.item,
    lote_id: e.loteId ?? null,
    organization_id: e.organizationId,
    shelter_id: e.shelterId,
    tipo: e.tipo,
    unidad: e.unidad,
  }
  return JSON.stringify(ordered)
}
