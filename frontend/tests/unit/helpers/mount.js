import { mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'

/**
 * Monta un componente con el andamiaje mínimo que el proyecto da por hecho en
 * runtime: el plugin de PrimeVue registrado y el `notifications` que los
 * paneles de notas reciben por inject (AlbumNotes.vue:20).
 *
 * PrimeVue va en modo `unstyled`: los tests no assertan sobre estilos y así no
 * hay que arrastrar el preset de src/config/primevue-preset.js.
 */
export function mountComponent (component, options = {}) {
  const { global = {}, ...rest } = options

  return mount(component, {
    ...rest,
    global: {
      ...global,
      plugins: [[PrimeVue, { unstyled: true }], ...(global.plugins ?? [])],
      provide: { notifications: createNotificationsStub(), ...(global.provide ?? {}) },
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
