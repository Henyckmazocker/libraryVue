import { mount } from '@vue/test-utils'
import PrimeVue from 'primevue/config'
import { createPinia, getActivePinia, setActivePinia } from 'pinia'

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
 *
 * Y Pinia se instala SOLO SI NO HAY UNO ACTIVO. El condicional no es una
 * cautela decorativa: 19 de los 50 specs hacen `setActivePinia(createPinia())`
 * en su `beforeEach`, piden el store ANTES de montar y le pisan métodos
 * —`RecommendDialog.spec.js:56-57` hace `const social = useSocialStore();
 * social.fetchFriends = vi.fn()`—. Si el montaje activara otra instancia, el
 * componente recibiría un store distinto del que el spec preparó: los mocks no
 * se aplicarían y el fallo aparecería como una aserción rara, no como un error
 * de Pinia. Con el condicional, esos 19 siguen mandando sobre su instancia y
 * los otros 31 reciben una limpia por montaje —que es lo que evita el
 * `"getActivePinia()" was called but there was no active Pinia` de los tres
 * diálogos que llaman a un store en su `setup` (RecommendDialog.vue:137,
 * AddToListDialog.vue:111, AddToClubDialog.vue:113)—.
 *
 * Van las DOS cosas, plugin y `setActivePinia`: el plugin cubre lo que el
 * componente pide en su `setup`; `setActivePinia` cubre lo que el propio spec
 * pida fuera del componente (un store consultado tras montar). Y es
 * `createPinia()`, no `createTestingPinia()`: este último stubea las acciones
 * por defecto y cambiaría el comportamiento de los 31 specs que hoy no esperan
 * stubs.
 */
export function mountComponent (component, options = {}) {
  const { global = {}, ...rest } = options
  const pinia = getActivePinia() ? null : createPinia()

  if (pinia) setActivePinia(pinia)

  return mount(component, {
    ...rest,
    global: {
      ...global,
      plugins: [...(pinia ? [pinia] : []), [PrimeVue, { unstyled: true }], ...(global.plugins ?? [])],
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
