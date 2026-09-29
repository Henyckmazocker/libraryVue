// Capturadores opcionales del SDK web de Augur. Ninguno se activa solo: la app los enciende con
// Augur.trackRouter(router), Augur.captureErrors() y Augur.captureClicks(), después de configure().
//
// No importan augur.js: reciben un `CaptureContext` con la función que registra el evento (la misma
// validación y las mismas reglas de consentimiento que track()) y los globales donde escuchar. Así
// augur.js los enchufa sin import circular y la suite puede darles un window/document propio.
//
//   trackRouter    page_view {path, referrer?} en router.afterEach, sin failure. path = el PATRÓN
//                  de la ruta (/book/:id), no la ruta real (/book/42): agrupa y no manda ids.
//                  referrer = host de document.referrer, solo en el primer page_view de la carga
//                  y solo si es de otro dominio.
//   captureErrors  error {message} en window 'error' y 'unhandledrejection', cortado a 200, con
//                  un tope de 20 por sesión (un error en bucle no llena la cola).
//   captureClicks  click {target} en document (fase de captura), del elemento más cercano con
//                  data-augur-click="<id>". Solo lo marcado: nada de clicks sueltos.

export const MAX_ERROR_MESSAGE = 200
export const MAX_ERRORS_PER_SESSION = 20
export const CLICK_ATTRIBUTE = 'data-augur-click'

/**
 * @typedef {object} CaptureContext
 * @property {(name: string, props: object, cap?: number) => void} record
 *   registra un evento como track(); con `cap`, como mucho ese número de eventos con ese nombre en
 *   la sesión (el contador vive en el registro de localStorage, no en memoria)
 * @property {Window|undefined} window
 * @property {Document|undefined} document
 */

/**
 * @typedef {object} RouteLike
 * @property {string} path
 * @property {{path?: string}[]} [matched]
 */

/**
 * El patrón de la ruta (el del último registro de `matched`) o, si no hay, su path.
 * @param {RouteLike} to
 * @returns {string}
 */
export function routePattern (to) {
  const matched = Array.isArray(to?.matched) ? to.matched : []
  const last = matched.length > 0 ? matched[matched.length - 1] : null
  const pattern = last && typeof last.path === 'string' && last.path !== '' ? last.path : null
  return pattern ?? String(to?.path ?? '')
}

/**
 * El host de document.referrer si es de otro dominio, o null (vacío, ilegible o el mismo).
 * @param {Document|undefined} doc
 * @param {Window|undefined} win
 * @returns {string|null}
 */
export function externalReferrer (doc, win) {
  const ref = doc?.referrer
  if (typeof ref !== 'string' || ref === '') return null
  try {
    const host = new URL(ref).host
    if (host === '' || host === win?.location?.host) return null
    return host
  } catch {
    return null
  }
}

/**
 * page_view en cada navegación que termina bien (vue-router 4: afterEach(to, from, failure)).
 * @param {CaptureContext} ctx
 * @param {{afterEach: Function}} router
 */
export function trackRouter (ctx, router) {
  let first = true
  router.afterEach((/** @type {RouteLike} */ to, /** @type {unknown} */ _from, /** @type {unknown} */ failure) => {
    if (failure) return
    /** @type {{path: string, referrer?: string}} */
    const props = { path: routePattern(to) }
    if (first) {
      first = false
      const host = externalReferrer(ctx.document, ctx.window)
      if (host) props.referrer = host
    }
    ctx.record('page_view', props)
  })
}

/**
 * El mensaje de un ErrorEvent o de la razón de un unhandledrejection, cortado a 200.
 * @param {unknown} v
 * @returns {string}
 */
export function errorMessage (v) {
  let msg = ''
  if (v instanceof Error) msg = v.message || v.name
  else if (typeof v === 'string') msg = v
  else if (v && typeof v === 'object' && typeof (/** @type {any} */ (v).message) === 'string') msg = /** @type {any} */ (v).message
  else if (v !== undefined && v !== null) {
    try { msg = String(v) } catch { msg = '' }
  }
  if (msg === '') msg = 'unknown error'
  return msg.slice(0, MAX_ERROR_MESSAGE)
}

/**
 * error {message} en los errores no capturados y en las promesas rechazadas sin catch.
 * @param {CaptureContext} ctx
 */
export function captureErrors (ctx) {
  const win = ctx.window
  if (!win || typeof win.addEventListener !== 'function') return
  win.addEventListener('error', (/** @type {any} */ e) => {
    // Un recurso que no carga (<img>, <script>) también dispara 'error' en window, sin message.
    const msg = e?.error ?? e?.message
    if (msg === undefined || msg === null || msg === '') return
    ctx.record('error', { message: errorMessage(msg) }, MAX_ERRORS_PER_SESSION)
  })
  win.addEventListener('unhandledrejection', (/** @type {any} */ e) => {
    ctx.record('error', { message: errorMessage(e?.reason) }, MAX_ERRORS_PER_SESSION)
  })
}

/**
 * click {target} en los elementos marcados con data-augur-click (o en cualquiera de sus hijos).
 * @param {CaptureContext} ctx
 */
export function captureClicks (ctx) {
  const doc = ctx.document
  if (!doc || typeof doc.addEventListener !== 'function') return
  doc.addEventListener('click', (/** @type {any} */ e) => {
    let node = e?.target
    // Un nodo de texto no tiene closest(): se sube a su elemento.
    if (node && typeof node.closest !== 'function') node = node.parentElement
    const el = node && typeof node.closest === 'function' ? node.closest(`[${CLICK_ATTRIBUTE}]`) : null
    if (!el) return
    const target = el.getAttribute(CLICK_ATTRIBUTE)
    if (typeof target !== 'string' || target === '') return
    ctx.record('click', { target })
  }, true)
}
