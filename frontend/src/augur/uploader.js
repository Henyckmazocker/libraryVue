// Subida del SDK web de Augur: arma lotes con lo pendiente en localStorage, los manda a
// POST <endpoint>/v1/batch y aplica la tabla de respuestas del SDK de Godot, portada tal cual
// (sdk/godot/addons/augur/augur_uploader.gd).
//
// El cuerpo va SIEMPRE como text/plain;charset=UTF-8 y con la clave dentro (`write_key`), por
// fetch y por beacon: así ninguna de las dos pide preflight y hay un solo camino.
//
// 🔴 El transporte se INYECTA: `{ fetch, sendBeacon, locks }`. En el navegador son los globales
// (fetch, navigator.sendBeacon, navigator.locks); en la suite, falsos que contestan el código que
// toque, así que toda la tabla se prueba sin red. El reloj (`now`) y los temporizadores
// (`timers`) también se inyectan.
//
// Tabla de respuestas:
//   200                  acked = último seq enviado; recorta los eventos subidos y borra las
//                        sesiones terminadas (ended_at y acked == next_seq - 1)
//   400 invalid_payload  console.error con el detail; borra las sesiones del lote salvo la de esta
//                        pestaña, que se da por subida
//   400 no_consent       console.error, no se borra nada
//   401                  console.error y deja de subir en esta carga de página; los datos se quedan
//   413                  parte el lote por la mitad y reintenta; un evento solo → como el 400
//   429                  espera Retry-After (o 60 s)
//   5xx / red / timeout  espera 10 → 20 → 40 → … → 300 s; un 200 la devuelve a 10 s
//
// Una subida a la vez entre todas las pestañas: locks.request('augur-upload', {ifAvailable: true}).
// Si el bloqueo está cogido se salta el turno; sin locks se sube igual (los duplicados son
// inofensivos por la idempotencia de (session_id, seq)).
//
// Un beacon no devuelve respuesta: no avanza `acked` ni toca el storage; lo que llevaba se vuelve a
// mandar en la siguiente subida y el núcleo lo cuenta en `duplicates`.
//
// Nada se cachea en memoria: cada paso relee el registro de localStorage (otras pestañas escriben).

// El ESLint del repo cliente puede no conocer globalThis (ver augur.js).
/* global globalThis */

export const UPLOAD_INTERVAL_MS = 10 * 1000
export const MAX_EVENTS = 1500                 // margen bajo los 2.000 del servidor
export const MAX_BYTES = 400 * 1024            // margen bajo los 512 KB del servidor
export const BEACON_MAX_BYTES = 60 * 1024      // bajo la cuota de 64 KB de beacons en vuelo
export const MAX_SESSIONS = 50                 // BatchValidator::MAX_SESSIONS
export const BASE_DELAY_S = 10
export const MAX_DELAY_S = 300
export const DEFAULT_RETRY_AFTER_S = 60
export const REQUEST_TIMEOUT_MS = 10 * 1000
export const LOCK_NAME = 'augur-upload'
export const CONTENT_TYPE = 'text/plain;charset=UTF-8'

/**
 * @typedef {object} Transport
 * @property {typeof fetch} [fetch]
 * @property {(url: string, data: BodyInit) => boolean} [sendBeacon]
 * @property {{request: Function}} [locks]
 */

/**
 * @typedef {object} Timers
 * @property {(fn: Function, ms: number) => any} setInterval
 * @property {(id: any) => void} clearInterval
 * @property {(fn: Function, ms: number) => any} setTimeout
 * @property {(id: any) => void} clearTimeout
 */

/**
 * @typedef {object} BatchSession
 * @property {string} session_id
 * @property {number} last_seq     último seq que va en el lote
 * @property {boolean} final       el lote llega a su último evento y la sesión tiene ended_at
 */

/**
 * @typedef {object} Batch
 * @property {string} body
 * @property {BatchSession[]} sessions
 * @property {number} events
 * @property {number} bytes
 */

/** Temporizadores globales, resueltos en cada llamada (así vi.useFakeTimers() también vale). */
export const globalTimers = /** @type {Timers} */ ({
  setInterval: (fn, ms) => globalThis.setInterval(fn, ms),
  clearInterval: (id) => globalThis.clearInterval(id),
  setTimeout: (fn, ms) => globalThis.setTimeout(fn, ms),
  clearTimeout: (id) => globalThis.clearTimeout(id),
})

/**
 * El transporte del navegador: los globales, o undefined donde no existan.
 * @returns {Transport}
 */
export function browserTransport () {
  const nav = globalThis.navigator
  return {
    fetch: typeof globalThis.fetch === 'function' ? (input, init) => globalThis.fetch(input, init) : undefined,
    sendBeacon: nav && typeof nav.sendBeacon === 'function' ? (url, data) => nav.sendBeacon(url, data) : undefined,
    locks: nav && nav.locks && typeof nav.locks.request === 'function' ? nav.locks : undefined,
  }
}

/**
 * Longitud en bytes UTF-8 de un texto.
 * @param {string} s
 * @returns {number}
 */
export function utf8Length (s) {
  if (typeof TextEncoder === 'function') return new TextEncoder().encode(s).length
  return unescape(encodeURIComponent(s)).length
}

/** @param {unknown} v */
function isPlainObject (v) {
  if (v === null || typeof v !== 'object' || Array.isArray(v)) return false
  const proto = Object.getPrototypeOf(v)
  return proto === Object.prototype || proto === null
}

/** Texto no vacío, o null (el validador rechaza cadenas vacías en los opcionales). */
function nonEmptyOrNull (/** @type {unknown} */ v) {
  return typeof v === 'string' && v.trim() !== '' ? v : null
}

/**
 * La sesión sin eventos, serializada (termina en "}"). Solo los campos del contrato.
 * @param {import('./store.js').SessionHeader} h
 * @param {string|null} endedAt
 */
function sessionHead (h, endedAt) {
  return JSON.stringify({
    session_id: String(h.session_id),
    client_version: String(h.client_version ?? ''),
    build_id: nonEmptyOrNull(h.build_id),
    platform: String(h.platform ?? ''),
    locale: nonEmptyOrNull(h.locale),
    context: isPlainObject(h.context) ? h.context : null,
    started_at: String(h.started_at ?? ''),
    ended_at: endedAt,
  })
}

/**
 * Un evento serializado. `props` sale SIEMPRE como objeto: `{}`, nunca `[]` (el núcleo rechaza las
 * listas). seq y elapsed_ms pasan por Math.floor.
 * @param {import('./store.js').StoredEvent} e
 */
function eventJson (e) {
  return JSON.stringify({
    seq: Math.floor(e.seq),
    name: e.name,
    ts: e.ts,
    elapsed_ms: Math.floor(e.elapsed_ms),
    props: isPlainObject(e.props) ? e.props : {},
  })
}

/**
 * Arma un lote con lo pendiente de TODAS las sesiones de localStorage (las de esta pestaña y las
 * ajenas), de la más vieja a la más nueva por started_at: los eventos con seq > acked, y su
 * ended_at si lo tiene y el lote llega a su último evento. Corta en `limit` eventos, `maxBytes` de
 * cuerpo y MAX_SESSIONS sesiones. No escribe nada en el storage.
 *
 * Con `strict` (el beacon) el tope de bytes no se pasa nunca; sin él (fetch), el primer evento
 * entra aunque solo ya pase del tope, como en Godot (el servidor admite hasta 512 KB).
 *
 * @param {ReturnType<typeof import('./store.js').createStore>} store
 * @param {{writeKey: string, sdk: string, installId: string, limit: number, maxBytes: number, strict?: boolean}} o
 * @returns {Batch|null} null si no hay nada que subir
 */
export function buildBatch (store, o) {
  const recs = []
  for (const id of store.sessionIds()) {
    const rec = store.readSession(id)
    if (!rec || !rec.h || typeof rec.h.session_id !== 'string') continue   // ilegible: no se puede mandar
    recs.push({ id, rec, startedAt: typeof rec.h.started_at === 'string' ? rec.h.started_at : '' })
  }
  recs.sort((a, b) => (a.startedAt === b.startedAt ? (a.id < b.id ? -1 : 1) : (a.startedAt < b.startedAt ? -1 : 1)))

  const prefix = '{' +
    '"write_key":' + JSON.stringify(o.writeKey) +
    ',"consent":true' +
    ',"sdk":' + JSON.stringify(o.sdk) +
    ',"install_id":' + JSON.stringify(o.installId) +
    ',"sessions":['
  const suffix = ']}'
  let bytes = utf8Length(prefix) + utf8Length(suffix)
  /** @type {string[]} */
  const parts = []
  /** @type {BatchSession[]} */
  const sessions = []
  let total = 0

  for (const { rec } of recs) {
    if (sessions.length >= MAX_SESSIONS || total >= o.limit) break
    const acked = Math.floor(typeof rec.acked === 'number' ? rec.acked : -1)
    const pending = rec.events
      .filter((e) => e && typeof e.seq === 'number' && e.seq > acked)
      .sort((a, b) => a.seq - b.seq)
    if (pending.length === 0) continue
    const endedAt = typeof rec.ended_at === 'string' && rec.ended_at !== '' ? rec.ended_at : null
    // Cabecera con ended_at relleno para medir el peor caso.
    const sep = parts.length > 0 ? 1 : 0
    const headBytes = utf8Length(sessionHead(rec.h, endedAt)) + ',"events":[]}'.length + sep
    if (bytes + headBytes > o.maxBytes && (o.strict || parts.length > 0)) break
    /** @type {string[]} */
    const taken = []
    let takenBytes = 0
    let lastSeq = acked
    for (const e of pending) {
      if (total + taken.length >= o.limit) break
      const raw = eventJson(e)
      const add = utf8Length(raw) + (taken.length > 0 ? 1 : 0)
      const emptyBatch = parts.length === 0 && taken.length === 0
      if (bytes + headBytes + takenBytes + add > o.maxBytes && (o.strict || !emptyBatch)) break
      taken.push(raw)
      takenBytes += add
      lastSeq = Math.floor(e.seq)
    }
    if (taken.length === 0) break                   // lo que no cabe va en el siguiente turno
    const complete = taken.length === pending.length
    const head = sessionHead(rec.h, complete ? endedAt : null)
    const json = head.slice(0, -1) + ',"events":[' + taken.join(',') + ']}'
    parts.push(json)
    bytes += utf8Length(json) + sep
    total += taken.length
    sessions.push({ session_id: rec.h.session_id, last_seq: lastSeq, final: complete && endedAt !== null })
  }
  if (sessions.length === 0) return null
  return { body: prefix + parts.join(',') + suffix, sessions, events: total, bytes }
}

/**
 * @typedef {object} UploaderOptions
 * @property {ReturnType<typeof import('./store.js').createStore>} store
 * @property {Transport} transport
 * @property {() => number} now                         reloj de pared en ms
 * @property {Timers} [timers]
 * @property {string} endpoint                          origen sin barra final
 * @property {string} writeKey
 * @property {string} sdk                               "web/0.1.0"
 * @property {() => string} installId
 * @property {() => boolean} canUpload                  ¿hay consentimiento?
 * @property {() => string|null} currentSessionId       la sesión de esta pestaña
 * @property {(id: string) => void} closeStale          cierra una sesión dormida (augur.js)
 * @property {number} sessionTimeoutMs
 * @property {(code: number, info: object) => void} [onResponse]   solo la suite y la integración
 */

/**
 * @param {UploaderOptions} o
 */
export function createUploader (o) {
  const timers = o.timers ?? globalTimers
  const url = o.endpoint.replace(/\/+$/, '') + '/v1/batch'

  let inFlight = false
  let stopped = false                          // 401: no se sube más en esta carga de página
  let waitUntilMs = 0
  let backoffS = BASE_DELAY_S
  let eventLimit = MAX_EVENTS                  // baja tras un 413, vuelve a MAX_EVENTS con un 200
  let lastWaitS = 0
  let requestsSent = 0
  let beaconsSent = 0
  /** @type {any} */
  let intervalId = null
  /** @type {Promise<boolean>|null} */
  let current = null

  /** @param {{limit: number, maxBytes: number, strict?: boolean}} lim */
  function build (lim) {
    return buildBatch(o.store, { writeKey: o.writeKey, sdk: o.sdk, installId: o.installId(), ...lim })
  }

  /**
   * La primera pestaña que sube después de los 30 minutos cierra cualquier sesión ajena sin
   * ended_at y con last_wall_ms de hace más de 30 min (session_end en el ts de su último evento).
   */
  function closeForeignStale () {
    const own = o.currentSessionId()
    const t = o.now()
    for (const id of o.store.sessionIds()) {
      if (id === own) continue
      const rec = o.store.readSession(id)
      if (rec && rec.ended_at == null && t - rec.last_wall_ms > o.sessionTimeoutMs) o.closeStale(id)
    }
  }

  /** Borra las sesiones terminadas y subidas enteras (ended_at y acked == next_seq - 1). */
  function deleteFinished () {
    for (const id of o.store.sessionIds()) {
      const rec = o.store.readSession(id)
      if (rec && rec.ended_at != null && rec.acked >= rec.next_seq - 1) o.store.deleteSession(id)
    }
  }

  /**
   * Da por subidos los eventos hasta `lastSeq`: acked avanza (nunca retrocede), se recortan los
   * eventos y, si la sesión está terminada y subida entera, se borra. Relee el registro: la
   * pestaña ha podido añadir eventos mientras la petición volaba.
   * @param {string} id
   * @param {number} lastSeq
   */
  function ackSession (id, lastSeq) {
    const rec = o.store.readSession(id)
    if (!rec) return                            // ya no está (consentimiento revocado, tope)
    const acked = Math.max(Math.floor(rec.acked ?? -1), lastSeq)
    rec.acked = acked
    rec.events = rec.events.filter((e) => e.seq > acked)
    if (rec.ended_at != null && acked >= rec.next_seq - 1) o.store.deleteSession(id)
    else o.store.writeSession(id, rec)
  }

  /** @param {Batch} batch */
  function ack (batch) {
    for (const s of batch.sessions) ackSession(s.session_id, s.last_seq)
  }

  /**
   * Reenviar un lote inválido daría el mismo 400 para siempre. Las sesiones ajenas se borran; la
   * de esta pestaña no puede (sigue escribiéndose), así que se dan por subidos sus eventos.
   * @param {Batch} batch
   */
  function discard (batch) {
    const own = o.currentSessionId()
    for (const s of batch.sessions) {
      if (s.session_id === own) ackSession(s.session_id, s.last_seq)
      else o.store.deleteSession(s.session_id)
    }
  }

  function backOff () {
    lastWaitS = backoffS
    waitUntilMs = o.now() + backoffS * 1000
    backoffS = Math.min(backoffS * 2, MAX_DELAY_S)
  }

  /** @param {string|null} value */
  function retryAfter (value) {
    const v = typeof value === 'string' ? value.trim() : ''
    if (/^\d+$/.test(v)) return parseInt(v, 10)
    return DEFAULT_RETRY_AFTER_S
  }

  /**
   * Un POST con timeout de 10 s (AbortController). status 0 = red, timeout o sin fetch.
   * @param {string} body
   * @returns {Promise<{status: number, retryAfter: string|null, text: string}>}
   */
  async function send (body) {
    if (typeof o.transport.fetch !== 'function') return { status: 0, retryAfter: null, text: '' }
    const ctrl = typeof AbortController === 'function' ? new AbortController() : null
    const timer = timers.setTimeout(() => ctrl?.abort(), REQUEST_TIMEOUT_MS)
    try {
      const resp = await o.transport.fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': CONTENT_TYPE },
        body,
        credentials: 'omit',
        ...(ctrl ? { signal: ctrl.signal } : {}),
      })
      const text = await resp.text()
      const ra = resp.headers && typeof resp.headers.get === 'function' ? resp.headers.get('Retry-After') : null
      return { status: resp.status, retryAfter: ra, text }
    } catch {
      return { status: 0, retryAfter: null, text: '' }
    } finally {
      timers.clearTimeout(timer)
    }
  }

  /**
   * Aplica la tabla de respuestas. Devuelve true si hay que reintentar ya (413 partido).
   * @param {{status: number, retryAfter: string|null, text: string}} res
   * @param {Batch} batch
   */
  function handle (res, batch) {
    const code = res.status
    let data = {}
    try {
      const parsed = res.text && res.text.trim().startsWith('{') ? JSON.parse(res.text) : null
      if (isPlainObject(parsed)) data = parsed
    } catch {
      // cuerpo que no es JSON: se trata por el código
    }
    const info = { code, events: batch.events, sessions: batch.sessions.length, bytes: batch.bytes, body: data }
    let retry = false

    if (code === 0) {
      backOff()
    } else if (code === 200) {
      backoffS = BASE_DELAY_S
      eventLimit = MAX_EVENTS
      lastWaitS = 0
      waitUntilMs = 0
      ack(batch)
    } else if (code === 400 && data.error === 'no_consent') {
      console.error('Augur: el servidor respondió no_consent (no debería pasar); no se borra nada')
    } else if (code === 400) {
      console.error(`Augur: lote rechazado (invalid_payload): ${data.detail ?? res.text} — se descartan sus sesiones; es un bug del SDK`)
      discard(batch)
    } else if (code === 401) {
      console.error('Augur: clave rotada o mal configurada (401 invalid_key); no se sube más en esta carga de página')
      stopped = true
    } else if (code === 413) {
      if (batch.sessions.length === 1 && batch.events <= 1) {
        console.error('Augur: un solo evento da 413 (batch_too_large); se descarta como un 400')
        discard(batch)
      } else {
        eventLimit = Math.max(1, Math.floor(batch.events / 2))
        info.split_to = eventLimit
        retry = true
      }
    } else if (code === 429) {
      const wait = retryAfter(res.retryAfter)
      lastWaitS = wait
      waitUntilMs = o.now() + wait * 1000
    } else {
      backOff()
    }
    info.wait_s = lastWaitS
    if (o.onResponse) {
      try { o.onResponse(code, info) } catch { /* el oyente de la suite no rompe la subida */ }
    }
    return retry
  }

  /** Ya con el bloqueo: cierra las ajenas dormidas, limpia y sube un lote (o sus mitades). */
  async function locked () {
    closeForeignStale()
    deleteFinished()
    let sent = false
    for (;;) {
      if (stopped || !o.canUpload()) return sent
      const batch = build({ limit: eventLimit, maxBytes: MAX_BYTES })
      if (!batch) return sent
      sent = true
      requestsSent++
      const res = await send(batch.body)
      if (!handle(res, batch)) return true
    }
  }

  async function run () {
    try {
      const locks = o.transport.locks
      if (locks && typeof locks.request === 'function') {
        // Si otra pestaña (u otra instancia) tiene el bloqueo, lock llega null: se salta el turno.
        return await locks.request(LOCK_NAME, { ifAvailable: true }, (lock) => (lock ? locked() : false))
      }
      return await locked()
    } catch (err) {
      console.warn('Augur: la subida falló', err)
      return false
    } finally {
      inFlight = false
      current = null
    }
  }

  const api = {
    /**
     * Sube un lote si toca. No hace nada si hay una subida en vuelo (una como mucho), si un 401 la
     * paró, si se está esperando (429 / backoff) o si no hay consentimiento. Resuelve con si llegó
     * a mandar algo.
     * @returns {Promise<boolean>}
     */
    upload () {
      if (stopped || inFlight || !o.canUpload() || o.now() < waitUntilMs) return Promise.resolve(false)
      inFlight = true
      current = run()
      return current
    },

    /**
     * Manda por beacon lo pendiente que quepa en 60 KB. Sin respuesta: no avanza acked ni toca el
     * storage, y el `false` de sendBeacon (cuota llena) se ignora. Salta las esperas, nunca el 401.
     * @returns {boolean} si llegó a llamar a sendBeacon
     */
    beacon () {
      if (stopped || !o.canUpload() || typeof o.transport.sendBeacon !== 'function') return false
      const batch = build({ limit: MAX_EVENTS, maxBytes: BEACON_MAX_BYTES, strict: true })
      if (!batch) return false
      const data = typeof Blob === 'function' ? new Blob([batch.body], { type: CONTENT_TYPE }) : batch.body
      try {
        o.transport.sendBeacon(url, data)
      } catch {
        // lo que no salió sube en la siguiente carga
      }
      beaconsSent++
      return true
    },

    /** Arranca la subida periódica (cada 10 s). */
    start () {
      if (intervalId !== null) return
      intervalId = timers.setInterval(() => { api.upload() }, UPLOAD_INTERVAL_MS)
    },

    stop () {
      if (intervalId === null) return
      timers.clearInterval(intervalId)
      intervalId = null
    },

    /** La subida en vuelo, o una promesa resuelta si no hay ninguna. */
    whenIdle () {
      return current ?? Promise.resolve(false)
    },

    isInFlight () { return inFlight },
    isStopped () { return stopped },
    /** Solo lectura para la suite y la integración. */
    stats () {
      return { lastWaitS, waitUntilMs, backoffS, eventLimit, requestsSent, beaconsSent }
    },
  }
  return api
}
