// Los eventos transversales (Plan «Catálogo de Eventos de Producto», M2): errores del API, avisos,
// diálogos, confirmaciones, redirecciones y sesión.
//
// El SDK se falsea y Augur se enciende como en analytics-catalog.spec.js, así que lo que se mira
// es lo que de verdad llegaría a `Augur.track` después de pasar la validación del catálogo. Y
// `console.warn` se vigila: un disparo con una prop mal escrita no se envía y avisa por ahí.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { h, nextTick } from 'vue'
import { createRequire } from 'node:module'
import { join } from 'node:path'
import { mountComponent } from './helpers/mount'

vi.mock('@/augur/augur.js', () => ({
  default: {
    configure: vi.fn(),
    setConsent: vi.fn(),
    hasConsent: vi.fn(() => true),
    trackRouter: vi.fn(),
    captureErrors: vi.fn(),
    track: vi.fn(),
    flush: vi.fn(() => Promise.resolve(true)),
  },
}))
// axios se falsea sin importarlo: la regla no-restricted-imports lo reserva a store/auth.js
const axios = vi.hoisted(() => ({ post: vi.fn() }))
vi.mock('axios', () => ({ default: axios }))

import Augur from '@/augur/augur.js'
import { initAnalytics, _resetForTests } from '@/analytics'
import { apiError } from '@/composables/useApiError'
import { handleStoreError } from '@/utils/storeHelpers'
import { useAuthStore } from '@/store/auth'
import { useUIStore } from '@/store/ui'
import BaseModal from '@/components/common/BaseModal.vue'
import AddToListDialog from '@/components/Lists/AddToListDialog.vue'
import ListFormDialog from '@/components/Lists/ListFormDialog.vue'

// ── Andamiaje ────────────────────────────────────────────────────────────────────────────────────

const Vacio = { render: () => null }
const testRouter = () => createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'Home', component: Vacio },
    { path: '/lists', name: 'Lists', component: Vacio },
    { path: '/sin-nombre', component: Vacio },
  ],
})

/** Los eventos que llegaron al SDK con ese nombre, solo sus props. */
const sent = (name) => Augur.track.mock.calls.filter(([n]) => n === name).map(([, p]) => p)

const httpError = (status, data = { status: 'error', http_code: status, message: 'Something failed for ana@example.com' }, headers = {}) =>
  Object.assign(new Error(`HTTP ${status}`), { isAxiosError: true, request: {}, response: { status, data, headers } })
const networkError = () => Object.assign(new Error('Network Error'), { isAxiosError: true, request: {} })
const ok = (data = {}) => ({ status: 200, data: { status: 'success', data } })

let warn
let router

beforeEach(async () => {
  setActivePinia(createPinia())
  _resetForTests()
  vi.clearAllMocks()
  localStorage.clear()
  vi.stubEnv('VUE_APP_AUGUR_KEY', 'clave-de-prueba')
  Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true })
  Object.defineProperty(navigator, 'locks', { value: { request: vi.fn() }, configurable: true })
  router = testRouter()
  initAnalytics(router)
  await router.push('/')
  warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
})

afterEach(() => {
  // Ningún disparo del M2 puede caer en la validación del catálogo.
  const analyticsWarnings = warn.mock.calls.filter(([m]) => String(m).startsWith('[analytics]'))
  expect(analyticsWarnings).toEqual([])
  warn.mockRestore()
  vi.unstubAllEnvs()
})

const authenticated = () => {
  const auth = useAuthStore()
  auth.$patch({ user: { id: 7, name: 'Ana', analytics_consent: 1 }, isAuthenticated: true, csrfToken: 'csrf' })
  return auth
}

// ── api_error ────────────────────────────────────────────────────────────────────────────────────

describe('api_error: una vez por fallo, donde se sabe qué action falló', () => {
  it('un error HTTP sale desde apiCall con su action, su código y sin el mensaje', async () => {
    const auth = authenticated()
    const err = httpError(404)
    axios.post.mockRejectedValueOnce(err)

    await expect(auth.authenticatedApiCall('get_list', { listId: 1 })).rejects.toBe(err)

    expect(sent('api_error')).toEqual([{ action: 'get_list', status: 404, code: 404, network: false }])
  })

  it('los dos puntos de negocio no vuelven a contar el mismo fallo', async () => {
    const auth = authenticated()
    const err = httpError(409)
    axios.post.mockRejectedValueOnce(err)
    await auth.authenticatedApiCall('add_list_item').catch(() => {})

    apiError(err)
    apiError(err.response.data)
    handleStoreError(err)
    apiError(409) // un número no se puede reconocer: no se cuenta nunca

    expect(sent('api_error')).toHaveLength(1)
  })

  it('un fallo de red sale como network, sin status ni code', async () => {
    const auth = authenticated()
    const err = networkError()
    axios.post.mockRejectedValueOnce(err)
    await auth.authenticatedApiCall('get_my_lists').catch(() => {})
    handleStoreError(err)

    expect(sent('api_error')).toEqual([{ action: 'get_my_lists', status: 0, code: 0, network: true }])
  })

  it('un 2xx con status error también es un fallo', async () => {
    const auth = authenticated()
    const response = { status: 200, data: { status: 'error', http_code: 409, message: 'dup' } }
    axios.post.mockResolvedValueOnce(response)

    await expect(auth.authenticatedApiCall('add_list_item')).resolves.toBe(response)
    apiError(response.data)

    expect(sent('api_error')).toEqual([{ action: 'add_list_item', status: 200, code: 409, network: false }])
  })

  it('lo que no pasó por apiCall lo cuentan los puntos de negocio, con action unknown', () => {
    apiError({ status: 'error', http_code: 403, message: 'no' })
    handleStoreError(networkError())
    apiError(new Error('un error de JS no es del API'))
    apiError(null)

    expect(sent('api_error')).toEqual([
      { action: 'unknown', status: 0, code: 403, network: false },
      { action: 'unknown', status: 0, code: 0, network: true },
    ])
  })

  it('una action que el catálogo no conoce sale como unknown', async () => {
    const auth = authenticated()
    axios.post.mockRejectedValueOnce(httpError(500))
    await auth.apiCall('action_inventada').catch(() => {})

    expect(sent('api_error')).toEqual([{ action: 'unknown', status: 500, code: 500, network: false }])
  })
})

describe('api_rate_limited', () => {
  it('un 429 largo sale con la action y los segundos, y no como api_error', async () => {
    const auth = authenticated()
    axios.post.mockRejectedValueOnce(httpError(429, {}, { 'retry-after': '30' }))

    await auth.authenticatedApiCall('search_users').catch(() => {})

    expect(sent('api_rate_limited')).toEqual([{ action: 'search_users', retry_after: 30 }])
    expect(sent('api_error')).toEqual([])
  })
})

// ── error_shown y confirm_cancelled ──────────────────────────────────────────────────────────────

describe('error_shown', () => {
  it('sale con los errores y las advertencias, con la ruta donde se enseñó', async () => {
    await router.push('/lists')
    const ui = useUIStore()

    ui.showError('No se pudo')
    ui.showWarning('Cuidado')
    ui.showSuccess('Bien')
    ui.showInfo('Dato')

    expect(sent('error_shown')).toEqual([{ source: 'Lists' }, { source: 'Lists' }])
  })

  it('en una ruta sin nombre del catálogo, el source es unknown', async () => {
    await router.push('/sin-nombre')
    useUIStore().showError('x')
    expect(sent('error_shown')).toEqual([{ source: 'unknown' }])
  })
})

describe('confirm_cancelled', () => {
  it('sale al cancelar, con el tipo del modal, y no al confirmar', async () => {
    const ui = useUIStore()

    const primera = ui.showConfirmationModal({ type: 'danger' })
    ui.closeConfirmationModal(false)
    await expect(primera).resolves.toBe(false)

    const segunda = ui.showConfirmationModal({ type: 'warning' })
    ui.closeConfirmationModal(true)
    await expect(segunda).resolves.toBe(true)

    // Cerrar lo que ya está cerrado no es cancelar nada.
    ui.closeConfirmationModal(false)

    expect(sent('confirm_cancelled')).toEqual([{ kind: 'danger' }])
  })
})

// ── Sesión ───────────────────────────────────────────────────────────────────────────────────────

describe('login, logout y perfil', () => {
  it('logged_in lleva el método', async () => {
    axios.post.mockResolvedValueOnce(ok({ user: { id: 7, analytics_consent: 1 }, csrf_token: 'c' }))
    const res = await useAuthStore().login('token', 'google_native')

    expect(res.success).toBe(true)
    expect(sent('logged_in')).toEqual([{ method: 'google_native' }])
  })

  it('login_failed lleva solo el código HTTP', async () => {
    axios.post.mockRejectedValueOnce(httpError(401))
    await useAuthStore().login('token')

    expect(sent('login_failed')).toEqual([{ status: 401 }])
    expect(sent('logged_out')).toEqual([]) // limpiar un login fallido no es cerrar sesión
  })

  it('logged_out distingue el cierre del usuario del forzado por un 401', async () => {
    axios.post.mockResolvedValue(ok())
    const auth = authenticated()
    await auth.logout()

    authenticated()
    auth.handleAuthError({ response: { status: 401 } })
    await flushPromises()

    expect(sent('logged_out')).toEqual([{ forced: false }, { forced: true }])
    // Y va ANTES de apagar: con el consentimiento apagado el SDK ya no lo mandaría.
    const orden = Augur.track.mock.invocationCallOrder[Augur.track.mock.calls.findIndex(([n]) => n === 'logged_out')]
    expect(orden).toBeLessThan(Augur.setConsent.mock.invocationCallOrder.at(-1))
  })

  it('profile_updated cuenta los campos, no los manda', async () => {
    const auth = authenticated()
    axios.post.mockResolvedValueOnce(ok({ user: { lastfm_username: 'ana' } }))
    await auth.updateProfile({ lastfm_username: 'ana' })

    expect(sent('profile_updated')).toEqual([{ fields: 1 }])
  })
})

describe('auth_redirected', () => {
  it('una ruta con sesión, sin sesión, sale con el name de la ruta', async () => {
    // El router de la app, con su guard. El guard pide el store con un `require` de CommonJS
    // (router/index.js), que en Vitest no pasa por el alias `@` ni por vi.mock: se le sirve desde
    // la caché de `require` un store sin sesión ya comprobada.
    const req = createRequire(join(process.cwd(), 'src', 'router', 'index.js'))
    const authPath = req.resolve('../store/auth.js')
    const sinSesion = { isAuthenticated: false, _authChecked: true, isLoggedIn: false, initializeAuth: vi.fn() }
    req.cache[authPath] = { id: authPath, filename: authPath, loaded: true, exports: { useAuthStore: () => sinSesion } }
    try {
      const { default: appRouter } = await import('@/router')
      await appRouter.push('/lists')

      expect(appRouter.currentRoute.value.name).toBe('Home')
      expect(sent('auth_redirected')).toEqual([{ to: 'Lists' }])
    } finally {
      delete req.cache[authPath]
    }
  })
})

// ── BaseModal ────────────────────────────────────────────────────────────────────────────────────

describe('BaseModal: abierto, abandonado y enviado', () => {
  const montar = (props = {}) => mountComponent(BaseModal, {
    props: { modelValue: true, title: 'T', analyticsName: 'list_form', ...props },
    slots: { default: () => h('input', { class: 'campo' }) },
  })

  it('nace abierto: dialog_opened; la X sin tocar nada: dialog_dismissed sin input', async () => {
    const w = montar()
    await w.find('.base-modal__close').trigger('click')
    await w.setProps({ modelValue: false })

    expect(sent('dialog_opened')).toEqual([{ dialog: 'list_form' }])
    expect(sent('dialog_dismissed')).toEqual([{ dialog: 'list_form', had_input: false }])
  })

  it('con algo escrito, had_input es true', async () => {
    const w = montar()
    await w.find('.campo').setValue('Mi lista')
    await w.setProps({ modelValue: false })

    expect(sent('dialog_dismissed')).toEqual([{ dialog: 'list_form', had_input: true }])
  })

  it('tras markSubmitted() el cierre no es un abandono', async () => {
    const w = montar()
    w.vm.markSubmitted()
    await w.setProps({ modelValue: false })

    expect(sent('dialog_dismissed')).toEqual([])
  })

  it('desmontarlo abierto (el v-if del padre) también es abandonarlo, una sola vez', async () => {
    const w = montar()
    w.unmount()
    await nextTick()

    expect(sent('dialog_dismissed')).toEqual([{ dialog: 'list_form', had_input: false }])
  })

  it('cada apertura cuenta, y el envío de la anterior no se hereda', async () => {
    const w = montar({ modelValue: false })
    await w.setProps({ modelValue: true })
    w.vm.markSubmitted()
    await w.setProps({ modelValue: false })
    await w.setProps({ modelValue: true })
    await w.setProps({ modelValue: false })

    expect(sent('dialog_opened')).toHaveLength(2)
    expect(sent('dialog_dismissed')).toEqual([{ dialog: 'list_form', had_input: false }])
  })

  it('analyticsDirty (un selector de botones con algo elegido) también es had_input', async () => {
    const w = montar({ analyticsDirty: true })
    await w.setProps({ modelValue: false })

    expect(sent('dialog_dismissed')).toEqual([{ dialog: 'list_form', had_input: true }])
  })

  it('sin analyticsName no mide nada', async () => {
    const w = montar({ analyticsName: '' })
    await w.setProps({ modelValue: false })

    expect(sent('dialog_opened')).toEqual([])
    expect(sent('dialog_dismissed')).toEqual([])
  })
})

// ── El «Hecho cuando» del M2, lo que se puede sin navegador ─────────────────────────────────────

describe('el «Hecho cuando» del M2', () => {
  it('un error en AddToListDialog se pinta como aviso y llega como error_shown + api_error', async () => {
    authenticated()
    await router.push('/lists')
    axios.post.mockImplementation((_url, body) => {
      if (body.action === 'get_my_lists') {
        return Promise.resolve(ok({ lists: [{ id: 3, name: 'Pendientes', is_owner: true }] }))
      }
      return Promise.reject(httpError(409))
    })

    const w = mountComponent(AddToListDialog, {
      props: { modelValue: true, entityType: 'movie', entityId: 'tt0111161', entityTitle: 'Cadena perpetua' },
    })
    await flushPromises()
    await w.find('.add-to-list-dialog__list').trigger('click')
    await w.find('.btn--primary').trigger('click')
    await flushPromises()

    const avisos = useUIStore().notifications
    expect(avisos.map((n) => n.type)).toEqual(['error'])
    expect(sent('error_shown')).toEqual([{ source: 'Lists' }])
    expect(sent('api_error')).toEqual([{ action: 'add_list_item', status: 409, code: 409, network: false }])
    expect(sent('form_submit')).toEqual([{ form: 'add_to_list', ok: false }])
    expect(sent('dialog_opened')).toEqual([{ dialog: 'add_to_list' }])
  })

  it('abrir y cerrar ListFormDialog sin enviar llega como dialog_dismissed { list_form, had_input }', async () => {
    const w = mountComponent(ListFormDialog, { props: { modelValue: true } })
    await w.find('#list-name').setValue('Algo')
    await w.find('.base-modal__close').trigger('click')
    // El padre lo monta con v-if + v-model: al cerrar, lo desmonta.
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual([false])
    w.unmount()

    expect(sent('dialog_opened')).toEqual([{ dialog: 'list_form' }])
    expect(sent('dialog_dismissed')).toEqual([{ dialog: 'list_form', had_input: true }])
  })
})
