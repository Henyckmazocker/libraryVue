import { mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'

/**
 * Monta un componente con el andamiaje mínimo que el proyecto da por hecho en
 * runtime: el plugin de PrimeVue registrado y el `notifications` que los
 * paneles de notas reciben por inject (AlbumNotes.vue:20).
 *
 * PrimeVue va en modo `unstyled`: los tests no assertan sobre estilos y así no
 * hay que arrastrar el preset de src/config/primevue-preset.js.
 *
 * Y el `<Teleport>` se deja INERTE. Desde que los doce modales cuelgan de
 * `BaseModal` (2026-08-29), su marcado se teletransporta al `body` y deja de
 * colgar del wrapper: sin este stub, `wrapper.find()` no encuentra nada de lo
 * que hay dentro de un modal y 17 tests que no habían cambiado se caen a la vez.
 * Es la vía que recomienda Vue Test Utils, y además mantiene los tests hablando
 * del componente y no de `document`.
 */
export function mountComponent (component, options = {}) {
  const { global = {}, ...rest } = options

  return mount(component, {
    ...rest,
    global: {
      ...global,
      plugins: [[PrimeVue, { unstyled: true }], ...(global.plugins ?? [])],
      provide: { notifications: createNotificationsStub(), ...(global.provide ?? {}) },
      stubs: { teleport: true, ...(global.stubs ?? {}) },
    },
  })
}

/** Registra las notificaciones emitidas en vez de mostrarlas. */
export function createNotificationsStub () {
  const calls = []
  const record = (type) => (...args) => calls.push({ type, args })

  return {
    calls,
    showSuccess: record('success'),
    showError: record('error'),
    showInfo: record('info'),
    showWarning: record('warning'),
  }
}

/**
 * jsdom devuelve un rect de ceros, y RatingComponent decide media estrella o
 * estrella completa comparando clientX con la mitad del ancho del botón. Sin
 * esto los tests dependerían de ese cero implícito.
 */
export function stubBoundingRect (element, { left = 0, width = 20 } = {}) {
  element.getBoundingClientRect = () => ({
    left, width, right: left + width, top: 0, bottom: 0, height: 0, x: left, y: 0,
    toJSON: () => ({}),
  })
}
