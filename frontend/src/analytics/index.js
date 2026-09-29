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
//
// Nadie sin sesión se mide: al arrancar se fuerza setConsent(false) aunque el localStorage guarde
// un `consent: true` de una visita anterior, y solo syncAnalytics con un usuario que dijo sí lo
// vuelve a encender.

import Augur from '@/augur/augur.js'
import { routePattern } from '@/augur/capture.js'
import Logger from '@/utils/logger'

let available = false
let routerRef = null

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

/** Solo tests: vuelve al estado de antes de initAnalytics (el SDK real no se puede reconfigurar). */
export function _resetForTests () {
  available = false
  routerRef = null
  routeState = 'pending'
}
