// SDK web de Augur. API pública: la instancia única es el `export default`.
//
//   import Augur from '@/augur/augur.js'
//   Augur.configure({ writeKey, endpoint, clientVersion, buildId })
//   Augur.setConsent(true)
//   Augur.track('page_view', { path: '/book/:id' })
//
// Guarda en localStorage (ver store.js) con una sesión por pestaña, cuyo id vive en
// sessionStorage: recargar la pestaña continúa la sesión y su seq. Sin configure() o sin
// consentimiento no toca el storage.
//
// Además de la instancia se exporta la factoría `createAugur()`: la suite la usa para simular una
// recarga (instancia nueva con el mismo sessionStorage) o dos pestañas (dos sessionStorage y el
// mismo localStorage). Una app usa siempre el default.
//
// La subida (uploader.js) arranca en configure(): un lote al configurar, otro cada 10 s y otro con
// flush().
//
// Ciclo de vida (oyentes que configure() registra UNA vez; sin consentimiento no escriben nada):
//   visibilitychange → hidden   app_background (si platform !== 'web') y beacon con lo pendiente
//   visibilitychange → visible  la comprobación de los 30 min y después app_open (si es app)
//   pageshow con persisted      como visible (vuelta desde la bfcache)
//   pagehide                    solo beacon: salta igual al recargar que al cerrar, así que NUNCA
//                               cierra la sesión (la cierra la regla de los 30 min)
// Al abrir sesión en una app, app_open va justo después de session_start.
//
// Los capturadores opcionales (trackRouter, captureErrors, captureClicks) viven en capture.js.

// La copia se lintea con la configuración del repo cliente, y la de LibraryVue (env node, sin
// es2020) no conoce globalThis: sin esta línea, su ESLint da 'no-undef' y el serve falla (M5).
/* global globalThis */

import { createStore, createMemoryStorage, CAP_BYTES } from './store.js'
import { createUploader, browserTransport } from './uploader.js'
import * as capture from './capture.js'

export const SDK_VERSION = '0.1.0'                  // va en "sdk" como "web/0.1.0"
export const MAX_NAME_LENGTH = 64
export const MAX_LOCALE_LENGTH = 16
export const SESSION_TIMEOUT_MS = 30 * 60 * 1000     // la misma ventana que usa el dashboard
export const DEV_VERSION = '0.0.0-dev'

/**
 * @typedef {object} AugurConfig
 * @property {string} writeKey
 * @property {string} endpoint        origen sin barra final
 * @property {string} [clientVersion] el de stamp.cjs; si falta → "0.0.0-dev"
 * @property {string} [buildId]       el de stamp.cjs; si falta → null
 */

/**
 * @typedef {object} TestHooks
 * @property {() => number} [now]           reloj de pared en ms
 * @property {Storage} [localStorage]
 * @property {Storage} [sessionStorage]
 * @property {number} [capBytes]            tope de las augur:* (1 MB por defecto)
 * @property {import('./uploader.js').Transport} [transport]   { fetch, sendBeacon, locks }
 * @property {import('./uploader.js').Timers} [timers]         setInterval/setTimeout y sus clear
 * @property {(code: number, info: object) => void} [onResponse]  cada respuesta de la subida
 * @property {Window} [window]              donde escuchan pagehide/pageshow y captureErrors
 * @property {Document} [document]          donde escuchan visibilitychange y captureClicks
 */

/**
 * ¿Es un objeto plano? Un Array, null, una fecha o una instancia de clase no lo son.
 * @param {unknown} v
 * @returns {boolean}
 */
function isPlainObject (v) {
  if (v === null || typeof v !== 'object' || Array.isArray(v)) return false
  const proto = Object.getPrototypeOf(v)
  return proto === Object.prototype || proto === null
}

/**
 * localStorage/sessionStorage del navegador, o null si no existe o lanza al tocarlo (Safari
 * privado, cookies bloqueadas). Solo se LEE: probar con setItem escribiría sin consentimiento.
 * @param {'localStorage'|'sessionStorage'} name
 * @returns {Storage|null}
 */
function browserStorage (name) {
  try {
    const s = globalThis[name]
    if (!s) return null
    s.getItem('augur:state')
    return s
  } catch {
    return null
  }
}

/** uuid4 con crypto.randomUUID(), y a mano si no existe (contextos no seguros: http a una IP). */
function uuid4 () {
  const c = globalThis.crypto
  if (c && typeof c.randomUUID === 'function') return c.randomUUID()
  const b = new Uint8Array(16)
  if (c && typeof c.getRandomValues === 'function') c.getRandomValues(b)
  else for (let i = 0; i < 16; i++) b[i] = Math.floor(Math.random() * 256)
  b[6] = (b[6] & 0x0f) | 0x40
  b[8] = (b[8] & 0x3f) | 0x80
  const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('')
  return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`
}

/** "android"/"ios" dentro de Capacitor; si no, "web". */
function detectPlatform () {
  try {
    const p = globalThis.window?.Capacitor?.getPlatform?.()
    return typeof p === 'string' && p !== '' ? p : 'web'
  } catch {
    return 'web'
  }
}

/** ¿Corre dentro de una app (Capacitor)? Entonces se emiten app_open/app_background. */
function isApp () {
  return detectPlatform() !== 'web'
}

/** navigator.language cortado a 16, o null. */
function detectLocale () {
  const lang = globalThis.navigator?.language
  return typeof lang === 'string' && lang !== '' ? lang.slice(0, MAX_LOCALE_LENGTH) : null
}

/** {screen, viewport}. Sin user agent: identifica demasiado para lo que aporta. */
function detectContext () {
  const w = globalThis.window
  const scr = w?.screen
  return {
    screen: `${Math.floor(scr?.width ?? 0)}x${Math.floor(scr?.height ?? 0)}`,
    viewport: `${Math.floor(w?.innerWidth ?? 0)}x${Math.floor(w?.innerHeight ?? 0)}`,
  }
}

/**
 * Crea una instancia del SDK. Las apps usan el `export default`; la suite crea las suyas.
 */
export function createAugur () {
  let configured = false
  let inert = false
  let writeKey = ''
  let endpoint = ''
  let clientVersion = DEV_VERSION
  /** @type {string|null} */
  let buildId = null
  let installId = ''
  /** @type {ReturnType<typeof createStore>|null} */
  let store = null
  /** @type {ReturnType<typeof createUploader>|null} */
  let uploader = null

  /** @type {TestHooks} */
  let hooks = {}
  // El ciclo de vida: ¿la pestaña está oculta desde el último visible? Evita un app_open doble
  // cuando la vuelta desde la bfcache dispara pageshow y visibilitychange.
  let backgrounded = false
  let lifecycleBound = false
  /** @type {Set<string>} capturadores ya encendidos: llamar dos veces no duplica oyentes */
  const captures = new Set()

  const now = () => Math.floor(hooks.now ? hooks.now() : Date.now())
  const iso = (/** @type {number} */ ms) => new Date(ms).toISOString()

  function consentGranted () {
    const s = store?.readState()
    return !!s && s.consent === true
  }

  /**
   * Añade un evento al registro de la sesión (leer, modificar, escribir). Si la escritura
   * falla, el registro no cambia: el seq no se gasta.
   * @param {string} id
   * @param {string} name
   * @param {object} props
   * @param {number} [cap]   como mucho `cap` eventos con este nombre en la sesión (el contador va
   *                         en el registro, `capped`, y no en memoria: sobrevive a las recargas)
   * @returns {boolean}
   */
  function appendEvent (id, name, props, cap) {
    const rec = store.readSession(id)
    if (!rec || rec.ended_at != null) return false
    if (cap !== undefined) {
      const capped = rec.capped && typeof rec.capped === 'object' ? rec.capped : {}
      const n = Math.floor(capped[name] ?? 0)
      if (n >= cap) return false
      capped[name] = n + 1
      rec.capped = capped
    }
    const t = now()
    // elapsed_ms = max(último, reloj de pared - inicio): la sesión sobrevive a las recargas y
    // performance.now() no, y el reloj de pared puede retroceder.
    const elapsed = Math.floor(Math.max(rec.last_elapsed_ms, t - rec.started_wall_ms))
    const ts = iso(t)
    rec.events.push({ seq: Math.floor(rec.next_seq), name, ts, elapsed_ms: elapsed, props })
    rec.next_seq = Math.floor(rec.next_seq) + 1
    rec.last_ts = ts
    rec.last_elapsed_ms = elapsed
    rec.last_wall_ms = t
    if (!store.writeSession(id, rec)) {
      console.warn(`Augur: sin sitio en localStorage; se descarta el evento '${name}'`)
      return false
    }
    return true
  }

  /**
   * Cierra una sesión dormida: session_end con el ts y el elapsed_ms de su ÚLTIMO evento, y
   * ended_at = ese mismo ts. La sesión terminó cuando dejó de pasar algo.
   * @param {string} id
   */
  function closeStale (id) {
    const rec = store.readSession(id)
    if (!rec || rec.ended_at != null) return
    rec.events.push({
      seq: Math.floor(rec.next_seq),
      name: 'session_end',
      ts: rec.last_ts,
      elapsed_ms: Math.floor(rec.last_elapsed_ms),
      props: {},
    })
    rec.next_seq = Math.floor(rec.next_seq) + 1
    rec.ended_at = rec.last_ts
    if (!store.writeSession(id, rec)) {
      console.warn('Augur: no se pudo cerrar la sesión dormida en localStorage')
    }
  }

  /** Abre una sesión nueva con su session_start. @returns {string|null} */
  function openSession () {
    const id = uuid4()
    const t = now()
    const ts = iso(t)
    /** @type {import('./store.js').SessionRecord} */
    const rec = {
      h: {
        session_id: id,
        client_version: clientVersion,
        build_id: buildId,
        platform: detectPlatform(),
        locale: detectLocale(),
        context: detectContext(),
        started_at: ts,
      },
      started_wall_ms: t,
      next_seq: 1,
      acked: -1,
      last_ts: ts,
      last_elapsed_ms: 0,
      last_wall_ms: t,
      ended_at: null,
      events: [{ seq: 0, name: 'session_start', ts, elapsed_ms: 0, props: {} }],
    }
    // En una app, app_open justo después de session_start y en el mismo setItem.
    if (rec.h.platform !== 'web') {
      rec.events.push({ seq: 1, name: 'app_open', ts, elapsed_ms: 0, props: {} })
      rec.next_seq = 2
    }
    if (!store.writeSession(id, rec)) {
      console.warn('Augur: sin sitio en localStorage; no se abre sesión')
      return null
    }
    store.writeTabSessionId(id)
    store.enforceCap(id)
    return id
  }

  /**
   * La sesión de esta pestaña: la continúa si sigue viva, la cierra si lleva más de 30 min sin
   * eventos, y abre otra si no hay o si otra pestaña ya la cerró. Nunca se cachea: se relee.
   * @returns {string|null}
   */
  function ensureSession () {
    return ensureSessionInfo().id
  }

  /**
   * ensureSession() diciendo además si la sesión es nueva (ya lleva su app_open).
   * @returns {{id: string|null, opened: boolean}}
   */
  function ensureSessionInfo () {
    const id = store.readTabSessionId()
    if (id) {
      const rec = store.readSession(id)
      if (rec && rec.ended_at == null) {
        if (now() - rec.last_wall_ms <= SESSION_TIMEOUT_MS) return { id, opened: false }
        closeStale(id)
      }
    }
    return { id: openSession(), opened: true }
  }

  /**
   * track() por dentro: la misma validación, y el tope opcional por sesión de los capturadores.
   * @param {string} name
   * @param {object} props
   * @param {number} [cap]
   */
  function record (name, props, cap) {
    if (!configured || !consentGranted()) return
    if (typeof name !== 'string' || name === '' || name.length > MAX_NAME_LENGTH) {
      console.error(`Augur: nombre de evento vacío o de más de ${MAX_NAME_LENGTH} caracteres: '${name}'`)
      return
    }
    if (!isPlainObject(props)) {
      console.error(`Augur: las props de '${name}' tienen que ser un objeto plano; se descarta`)
      return
    }
    let copy
    try {
      copy = structuredClone(props)
    } catch (err) {
      console.error(`Augur: las props de '${name}' no se pueden copiar; se descarta`, err)
      return
    }
    const id = ensureSession()
    if (id) appendEvent(id, name, copy, cap)
  }

  /** La pestaña se oculta (o se cierra): app_background si es app, y beacon con lo pendiente. */
  function onHidden () {
    backgrounded = true
    if (!configured || !consentGranted()) return
    if (isApp()) record('app_background', {})
    uploader.beacon()
  }

  /** La pestaña vuelve: corte de los 30 min (puede abrir sesión, con su app_open) y app_open. */
  function onVisible () {
    if (!backgrounded) return
    backgrounded = false
    if (!configured || !consentGranted()) return
    const { id, opened } = ensureSessionInfo()
    if (id && !opened && isApp()) appendEvent(id, 'app_open', {})
  }

  /** pagehide salta igual al recargar que al cerrar: solo sube, nunca cierra la sesión. */
  function onPageHide () {
    backgrounded = true
    if (!configured || !consentGranted()) return
    uploader.beacon()
  }

  /** Los oyentes del ciclo de vida, una sola vez y solo tras un configure() que salió bien. */
  function bindLifecycle () {
    if (lifecycleBound) return
    lifecycleBound = true
    const win = hooks.window ?? globalThis.window
    const doc = hooks.document ?? globalThis.document
    if (doc && typeof doc.addEventListener === 'function') {
      doc.addEventListener('visibilitychange', () => {
        if (doc.visibilityState === 'hidden') onHidden()
        else if (doc.visibilityState === 'visible') onVisible()
      })
    }
    if (win && typeof win.addEventListener === 'function') {
      win.addEventListener('pagehide', () => onPageHide())
      win.addEventListener('pageshow', (/** @type {any} */ e) => { if (e && e.persisted) onVisible() })
    }
  }

  /**
   * Enciende un capturador de capture.js una sola vez, y solo tras configure().
   * @param {string} name
   * @param {(ctx: import('./capture.js').CaptureContext) => void} fn
   */
  function startCapture (name, fn) {
    if (!configured) {
      console.warn(`Augur: ${name}() antes de configure(); se ignora`)
      return
    }
    if (captures.has(name)) {
      console.warn(`Augur: ${name}() ya se llamó; se ignora`)
      return
    }
    captures.add(name)
    fn({
      record,
      window: hooks.window ?? globalThis.window,
      document: hooks.document ?? globalThis.document,
    })
  }

  return {
    /**
     * Sin writeKey o llamado dos veces → console.warn y nada. Si localStorage no está
     * disponible, el SDK queda inerte. Con consentimiento, continúa o abre la sesión.
     * @param {AugurConfig} config
     */
    configure (config) {
      if (configured || inert) {
        console.warn('Augur: configure() ya se llamó; se ignora')
        return
      }
      const cfg = config || /** @type {AugurConfig} */ ({})
      if (typeof cfg.writeKey !== 'string' || cfg.writeKey.trim() === '') {
        console.warn('Augur: configure() sin writeKey; el SDK no hace nada')
        return
      }
      const local = hooks.localStorage ?? browserStorage('localStorage')
      if (!local) {
        inert = true
        console.warn('Augur: localStorage no disponible; el SDK queda inerte')
        return
      }
      const tab = hooks.sessionStorage ?? browserStorage('sessionStorage') ?? createMemoryStorage()
      configured = true
      writeKey = cfg.writeKey.trim()
      endpoint = typeof cfg.endpoint === 'string' ? cfg.endpoint.replace(/\/+$/, '') : ''
      clientVersion = typeof cfg.clientVersion === 'string' && cfg.clientVersion !== '' ? cfg.clientVersion : DEV_VERSION
      buildId = typeof cfg.buildId === 'string' && cfg.buildId !== '' ? cfg.buildId : null
      store = createStore(local, tab, hooks.capBytes ?? CAP_BYTES)
      const state = store.readState()
      if (state && typeof state.install_id === 'string' && state.install_id !== '') {
        installId = state.install_id
      } else {
        // Nace en memoria y se escribe con el primer setConsent(): sin decisión no hay nada en
        // disco. Si ya había decisión (sin install_id, tocado a mano), se completa.
        installId = uuid4()
        if (state) store.writeState({ install_id: installId, consent: state.consent === true })
      }
      uploader = createUploader({
        store,
        transport: hooks.transport ?? browserTransport(),
        now,
        timers: hooks.timers,
        endpoint,
        writeKey,
        sdk: 'web/' + SDK_VERSION,
        installId: () => installId,
        canUpload: consentGranted,
        currentSessionId: () => store.readTabSessionId(),
        closeStale,
        sessionTimeoutMs: SESSION_TIMEOUT_MS,
        onResponse: hooks.onResponse,
      })
      uploader.start()
      bindLifecycle()
      if (consentGranted()) {
        ensureSession()
        uploader.upload()
      }
    },

    /**
     * true: lo guarda y abre sesión. false: lo guarda y BORRA todas las augur:s:*.
     * @param {boolean} granted
     */
    setConsent (granted) {
      if (!configured) {
        console.warn('Augur: setConsent() antes de configure(); se ignora')
        return
      }
      const ok = granted === true
      store.writeState({ install_id: installId, consent: ok })
      if (ok) {
        ensureSession()
      } else {
        store.deleteAllSessions()
        store.clearTab()
      }
    },

    /** La web pregunta solo si es false. */
    hasConsentDecision () {
      return configured && store.readState() !== null
    },

    hasConsent () {
      return configured && consentGranted()
    },

    /**
     * Sin configure o sin consentimiento: return sin tocar storage. Nombre vacío o > 64, o props
     * que no sea objeto plano → console.error y se descarta sin gastar seq.
     * @param {string} name
     * @param {object} [props]
     */
    track (name, props = {}) {
      record(name, props)
    },

    /**
     * Sube ya, respetando la subida en vuelo, el 401 y las esperas (429 / backoff). Resuelve con
     * si llegó a mandar algo.
     * @returns {Promise<boolean>}
     */
    flush () {
      if (!configured || !uploader) return Promise.resolve(false)
      return uploader.upload()
    },

    /**
     * page_view {path, referrer?} en router.afterEach (vue-router 4), con el patrón de la ruta.
     * @param {{afterEach: Function}} router
     */
    trackRouter (router) {
      if (!router || typeof router.afterEach !== 'function') {
        console.error('Augur: trackRouter() necesita un router con afterEach()')
        return
      }
      startCapture('trackRouter', (ctx) => capture.trackRouter(ctx, router))
    },

    /** error {message} en window 'error' y 'unhandledrejection', 20 por sesión como mucho. */
    captureErrors () {
      startCapture('captureErrors', capture.captureErrors)
    },

    /** click {target} en los elementos con data-augur-click="<id>" y sus hijos. */
    captureClicks () {
      startCapture('captureClicks', capture.captureClicks)
    },

    /** Para el borrado RGPD. "" antes de configure(). */
    getInstallId () {
      return installId
    },

    /**
     * Solo la suite y la integración, antes de configure(): reloj, los dos storages, el tope, el
     * transporte, los temporizadores y el oyente de respuestas. El mismo patrón que
     * _set_test_root() en el SDK de Godot.
     * @param {TestHooks} h
     */
    _setTestHooks (h) {
      hooks = { ...hooks, ...h }
    },

    /** Solo la suite: la sesión de esta pestaña. */
    _currentSessionId () {
      return store ? store.readTabSessionId() : null
    },

    /** Solo la suite y la integración: el uploader (null antes de configure()). */
    _uploader () {
      return uploader
    },

    /** Solo la suite: lo configurado. */
    _config () {
      return { writeKey, endpoint, clientVersion, buildId, sdk: 'web/' + SDK_VERSION }
    },
  }
}

const Augur = createAugur()
export default Augur
