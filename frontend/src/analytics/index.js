// El ÚNICO sitio de la app que toca el SDK de Augur (frontend/src/augur/, código de fuera).
// Ningún otro fichero de src/ importa '@/augur/': lo vigila tests/unit/analytics.spec.js.
//
// Ciclo de vida (Plan «Consentimiento de Analítica», orden fijado por su M0):
//   initAnalytics(router)  una vez, en main.js: configure → setConsent(false) → trackRouter →
//                          captureErrors. Sin clave o fuera de contexto seguro, no hace nada.
//   syncAnalytics(user)    tras login / initializeAuth / updateAnalyticsConsent: solo
//                          `analytics_consent === 1` enciende; null, undefined (backend sin migrar)
//                          y 0 apagan.
//   stopAnalytics()        en logout: sube lo pendiente y apaga.
//   isAnalyticsAvailable() hay clave en esta build y se configuró.
//   track(name, props)     un evento de producto, SOLO si está en analytics/catalog.js (Plan
//                          «Catálogo de Eventos de Producto»): nombre declarado, props declaradas y
//                          valores de su tipo cerrado. Lo que no casa no se envía.
//   trackApiError / trackUncountedApiError / trackRateLimited / currentRouteName
//                          los transversales del M2 de ese plan (errores del API y avisos).
//   failureCode / statusSlug / mediaOrNull
//                          lo que los disparos del M3 necesitan para reducir un fallo, un estado
//                          o un medio a un valor del catálogo.
//
// Nadie sin sesión se mide: al arrancar se fuerza setConsent(false) aunque el localStorage guarde
// un `consent: true` de una visita anterior, y solo syncAnalytics con un usuario que dijo sí lo
// vuelve a encender.

import Augur from '@/augur/augur.js'
import { routePattern } from '@/augur/capture.js'
import Logger from '@/utils/logger'
import { CATALOG, API_ACTIONS, ROUTE_NAMES, STATUSES, MEDIA } from './catalog'

let available = false
let routerRef = null
// El router, aunque Augur no se configure: `currentRouteName()` lo lee para el `source` de
// `error_shown`, y lo que no se envía también se valida (y se prueba).
let namesRouter = null

// ¿La ruta actual ya emitió su page_view? El `afterEach` del SDK (trackRouter) solo lo emite si
// hay consentimiento en ese instante, así que lo recordamos con un `afterEach` propio:
//   'pending'  la navegación inicial aún no terminó: su afterEach lo emitirá (o no) él solo.
//              Es el caso de las rutas protegidas, cuyo guard espera a initializeAuth.
//   'emitted'  terminó con consentimiento: el SDK ya lo mandó.
//   'missed'   terminó sin consentimiento (rutas públicas: Header.vue resuelve la sesión DESPUÉS
//              de navegar, o el login ocurre ya en la página).
// En la transición false → true, si está en 'missed' se emite a mano con el mismo formato que
// capture.js (el patrón de la ruta, nunca la URL con ids). Así no se pierde ni se duplica.
let routeState = 'pending'

function isSecureEnough () {
  // Sin navigator.locks el uploader sube SÍNCRONO dentro de configure() si el localStorage guarda
  // un consent:true viejo, antes de que llegue el setConsent(false) (M0, augur.js:439-442). En un
  // contexto no seguro (p. ej. http://<IP-LAN>) no se configura: esa build simplemente no mide.
  return typeof window !== 'undefined' && window.isSecureContext === true &&
    typeof navigator !== 'undefined' && !!navigator.locks
}

/**
 * Configura el SDK sin consentimiento. Se llama UNA vez (configure() es de un solo uso).
 * @param {import('vue-router').Router} router
 */
export function initAnalytics (router) {
  namesRouter = router ?? namesRouter
  if (available) return
  const writeKey = process.env.VUE_APP_AUGUR_KEY
  if (!writeKey) return
  if (!isSecureEnough()) {
    Logger.warn('[analytics] Contexto no seguro: Augur no se configura')
    return
  }
  Augur.configure({
    writeKey,
    endpoint: process.env.VUE_APP_AUGUR_ENDPOINT || 'http://localhost:8897',
    clientVersion: process.env.VUE_APP_AUGUR_VERSION,
    buildId: process.env.VUE_APP_AUGUR_BUILD,
  })
  // Antes de trackRouter: hasta saber quién es el usuario, nada sale.
  Augur.setConsent(false)
  Augur.trackRouter(router)
  Augur.captureErrors()
  available = true
  routerRef = router
  routeState = 'pending'
  router.afterEach((_to, _from, failure) => {
    if (failure) return
    routeState = Augur.hasConsent() ? 'emitted' : 'missed'
  })
}

/**
 * Aplica la decisión del usuario. Solo `analytics_consent === 1` enciende.
 * @param {{analytics_consent?: number|null}|null|undefined} user
 */
export function syncAnalytics (user) {
  if (!available) return
  try {
    const granted = !!user && user.analytics_consent === 1
    if (!granted) {
      Augur.setConsent(false)
      // Con el consentimiento se va la sesión: si vuelve, la ruta actual ya no cuenta como emitida.
      if (routeState === 'emitted') routeState = 'missed'
      return
    }
    const wasGranted = Augur.hasConsent()
    Augur.setConsent(true)
    if (!wasGranted && routeState === 'missed' && routerRef) {
      Augur.track('page_view', { path: routePattern(routerRef.currentRoute.value) })
      routeState = 'emitted'
    }
  } catch (e) {
    // La analítica nunca rompe la app.
    Logger.warn('[analytics] syncAnalytics falló:', e)
  }
}

/** Logout: sube lo pendiente ANTES de apagar (setConsent(false) borra la cola). */
export async function stopAnalytics () {
  if (!available) return
  try {
    await Augur.flush()
  } catch (e) {
    Logger.warn('[analytics] flush falló:', e)
  }
  try {
    Augur.setConsent(false)
    if (routeState === 'emitted') routeState = 'missed'
  } catch (e) {
    Logger.warn('[analytics] stopAnalytics falló:', e)
  }
}

/** Hay clave en esta build y el SDK se configuró (el modal y el panel se ocultan si no). */
export function isAnalyticsAvailable () {
  return available
}

/**
 * Por qué NO se puede mandar un evento (un código: `undeclared_event`, `undeclared_prop:<prop>`,
 * `missing_prop:<prop>`, `not_int:<prop>`…), o `null` si casa con el catálogo. Códigos y no frases:
 * solo los lee quien depura, en la consola. Pura: no mira si Augur está configurado, para que un
 * track mal formado se vea siempre, también en desarrollo sin clave y en los tests. Exige TODAS las props declaradas: una que falte es un disparo mal escrito, y en
 * Augur saldría como un grupo `null` indistinguible de un valor real.
 * @param {string} name
 * @param {object} props
 * @returns {string|null}
 */
export function checkEvent (name, props) {
  if (typeof name !== 'string' || !Object.prototype.hasOwnProperty.call(CATALOG, name)) {
    return 'undeclared_event'
  }
  if (props === null || typeof props !== 'object' || Array.isArray(props)) {
    return 'props_not_plain_object'
  }
  const declared = CATALOG[name].props
  for (const key of Object.keys(props)) {
    if (!Object.prototype.hasOwnProperty.call(declared, key)) return `undeclared_prop:${key}`
  }
  for (const [key, type] of Object.entries(declared)) {
    if (!Object.prototype.hasOwnProperty.call(props, key)) return `missing_prop:${key}`
    const value = props[key]
    if (type === 'int') {
      if (!Number.isInteger(value)) return `not_int:${key}`
    } else if (type === 'bool') {
      if (typeof value !== 'boolean') return `not_bool:${key}`
    } else if (type && Array.isArray(type.enum)) {
      if (!type.enum.includes(value)) return `not_in_enum:${key}`
    } else {
      // Un tipo abierto en el catálogo no se manda nunca (y el test del catálogo no lo deja entrar).
      return `open_type:${key}`
    }
  }
  return null
}

/**
 * Manda un evento de producto a Augur. Nunca lanza y devuelve siempre `undefined`: si Augur falla,
 * la app sigue igual.
 *
 * Primero valida contra el catálogo (aunque Augur no esté configurado): lo que no casa no se envía
 * y, fuera de producción, avisa por consola con el nombre y el motivo —nunca con el valor, que es
 * justo lo que podría ser un dato personal—. Después, sin clave no hace nada, y el consentimiento
 * no se mira aquí: el SDK descarta todo mientras no lo haya.
 * @param {string} name
 * @param {object} [props]
 */
export function track (name, props = {}) {
  try {
    const problem = checkEvent(name, props)
    if (problem) {
      if (process.env.NODE_ENV !== 'production') {
        // eslint-disable-next-line no-console
        console.warn(`[analytics] track('${String(name)}') no se envía: ${problem}`)
      }
      return undefined
    }
    if (!available) return undefined
    Augur.track(name, { ...props })
  } catch (e) {
    Logger.warn('[analytics] track falló:', e)
  }
  return undefined
}

/**
 * El `name` de la ruta actual si es uno de `ROUTE_NAMES`, o `'unknown'`. Es el `source` de
 * `error_shown`: quien pinta un aviso no dice quién lo pidió, pero sí se sabe dónde estaba.
 * @returns {string}
 */
export function currentRouteName () {
  try {
    const name = namesRouter?.currentRoute?.value?.name
    return ROUTE_NAMES.includes(name) ? name : 'unknown'
  } catch {
    return 'unknown'
  }
}

// ── api_error: una vez por fallo ─────────────────────────────────────────────────────────────────
//
// El fallo se cuenta donde se sabe QUÉ action falló: en `auth.apiCall`, con `trackApiError`. Lo
// que ese fallo deja (el error de axios, su `response`, su `data`) se apunta aquí, y los dos
// puntos de errores de negocio (`apiError` y `handleStoreError`) llaman a `trackUncountedApiError`,
// que no vuelve a contar nada apuntado. Así un mismo fallo no sale dos veces aunque pase por el
// store Y por el traductor de mensajes. Lo que llega a esos puntos como NÚMERO (`apiError(404)`) no
// se cuenta nunca: un número no se puede reconocer, y todo número de estos sale de una respuesta
// que ya pasó por `apiCall`.
const countedFailures = new WeakSet()

function remember (...objects) {
  for (const o of objects) {
    if (o && typeof o === 'object') countedFailures.add(o)
  }
}

function isCounted (origin) {
  return [origin, origin.response, origin.response?.data, origin.data]
    .some((o) => o && typeof o === 'object' && countedFailures.has(o))
}

const toInt = (v) => {
  const n = Number(v)
  return Number.isInteger(n) ? n : 0
}

const knownAction = (action) => (API_ACTIONS.includes(action) ? action : 'unknown')

/**
 * `api_error` de una llamada al backend que ha fallado. `origin` es el error de axios (HTTP o red)
 * o la respuesta 2xx que trae `status: 'error'`. Nunca manda el `message`: puede llevar un dato.
 * @param {string} action
 * @param {object} origin
 */
export function trackApiError (action, origin) {
  try {
    if (!origin || typeof origin !== 'object' || isCounted(origin)) return
    let props
    if (origin.response || origin.request || origin.isAxiosError) {
      // Error de axios: con respuesta es HTTP; sin ella, red (o timeout).
      const response = origin.response
      props = {
        action: knownAction(action),
        status: toInt(response?.status),
        code: response ? toInt(response.data?.http_code ?? response.status) : 0,
        network: !response,
      }
    } else if (origin.data && typeof origin.data === 'object' && 'status' in origin) {
      // Respuesta 2xx con un error de negocio dentro.
      props = { action: knownAction(action), status: toInt(origin.status), code: toInt(origin.data.http_code), network: false }
    } else {
      // El `data` suelto de una respuesta (lo que reciben `apiError` y compañía).
      props = { action: knownAction(action), status: 0, code: toInt(origin.http_code), network: false }
    }
    remember(origin, origin.response, origin.response?.data, origin.data)
    track('api_error', props)
  } catch (e) {
    Logger.warn('[analytics] trackApiError falló:', e)
  }
}

/**
 * Los puntos de errores de negocio: cuenta `origin` solo si parece un fallo del backend y
 * `auth.apiCall` no lo contó ya (con `action: 'unknown'`, porque aquí no se sabe).
 * @param {*} origin
 */
export function trackUncountedApiError (origin) {
  try {
    if (!origin || typeof origin !== 'object' || isCounted(origin)) return
    const looksLikeApi = origin.response || origin.request || origin.isAxiosError ||
      origin.http_code != null || origin.data?.http_code != null
    if (!looksLikeApi) return
    trackApiError('unknown', origin)
  } catch (e) {
    Logger.warn('[analytics] trackUncountedApiError falló:', e)
  }
}

/**
 * `api_rate_limited`: un 429 del backend, con los segundos que pide esperar.
 * @param {string} action
 * @param {number} retryAfter
 */
export function trackRateLimited (action, retryAfter) {
  track('api_rate_limited', { action: knownAction(action), retry_after: toInt(retryAfter) })
}

// ── Ayudas de los disparos de dominio (M3) ───────────────────────────────────────────────────────

/**
 * El `code` de un `*_failed`: el `http_code` del backend si lo hay, el status HTTP si no, y `0`
 * sin respuesta. `origin` es un error de axios, el `data` de una respuesta con `status: 'error'`
 * o cualquier otra cosa (un `Error` propio → `0`). Nunca lee el `message`.
 * @param {*} origin
 * @returns {number}
 */
export function failureCode (origin) {
  try {
    if (!origin || typeof origin !== 'object') return 0
    const response = origin.response
    const candidates = [response?.data?.http_code, response?.status, origin.http_code, origin.data?.http_code]
    for (const c of candidates) {
      const n = Number(c)
      if (c != null && Number.isInteger(n)) return n
    }
  } catch {
    // Un fallo leyendo el origen no puede tumbar a quien lo cuenta.
  }
  return 0
}

/**
 * Un estado de la biblioteca como slug de `STATUSES` (las claves `status.*` del catálogo de
 * textos, las mismas que usa `statusLabel`). Tolera las dos formas del backend —cadena plana o
 * `{id, name}` de vídeos— y los nombres con espacio o guion bajo (`'to read'` → `'to-read'`). Lo
 * que no es un slug conocido sale como `'unknown'`: un estado nuevo del backend no puede colar su
 * texto en Augur.
 * @param {*} entry
 * @returns {string}
 */
export function statusSlug (entry) {
  const raw = typeof entry === 'string' ? entry : entry?.name ?? entry?.slug
  if (typeof raw !== 'string') return 'unknown'
  const slug = raw.trim().toLowerCase().replace(/[\s_]+/g, '-')
  return slug && slug !== 'none' && STATUSES.includes(slug) ? slug : 'unknown'
}

/**
 * El medio si es uno de `MEDIA`, o `null`. Para los disparos que reciben el medio de fuera (una
 * entrada del diario, la config de un buscador): con `null`, quien llama decide qué mandar.
 * @param {*} media
 * @returns {string|null}
 */
export function mediaOrNull (media) {
  return MEDIA.includes(media) ? media : null
}

/** Solo tests: vuelve al estado de antes de initAnalytics (el SDK real no se puede reconfigurar). */
export function _resetForTests () {
  available = false
  routerRef = null
  namesRouter = null
  routeState = 'pending'
}
