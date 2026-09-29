// El modal de consentimiento (Plan «Consentimiento de Analítica», M3): sale solo a quien tiene la
// decisión en NULL, cada botón guarda su valor y cerrar no guarda nada.
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import { mountComponent } from './helpers/mount'

const disponible = vi.hoisted(() => ({ valor: true }))
vi.mock('@/analytics', () => ({
  isAnalyticsAvailable: () => disponible.valor,
  syncAnalytics: vi.fn(),
  stopAnalytics: vi.fn(() => Promise.resolve()),
}))

const ruta = vi.hoisted(() => ({ actual: null }))
vi.mock('vue-router', () => ({ useRoute: () => ruta.actual }))

import AnalyticsConsentModal from '@/components/common/AnalyticsConsentModal.vue'
import { useAuthStore } from '@/store/auth'
import { useUIStore } from '@/store/ui'

const RouterLink = { props: ['to'], template: '<a :href="to"><slot /></a>' }

let auth
const ok = (analytics_consent) => ({ data: { status: 'success', data: { analytics_consent } } })

const montar = () => mountComponent(AnalyticsConsentModal, {
  global: { stubs: { RouterLink, 'router-link': RouterLink } },
})

const abierto = (w) => w.find('[role="dialog"]').exists()

beforeEach(() => {
  setActivePinia(createPinia())
  disponible.valor = true
  ruta.actual = reactive({ path: '/library' })
  auth = useAuthStore()
  auth.$patch({ user: { id: 7, analytics_consent: null }, isAuthenticated: true })
  auth.updateAnalyticsConsent = vi.fn(async (consent) => {
    auth.user = { ...auth.user, analytics_consent: consent ? 1 : 0 }
    return ok(consent ? 1 : 0)
  })
})

describe('AnalyticsConsentModal — cuándo sale', () => {
  it('sale con la decisión en null', () => {
    expect(abierto(montar())).toBe(true)
  })

  it.each([
    ['sí', 1],
    ['no', 0],
    // backend sin migrar: el campo no llega y la action daría 500, así que no se pregunta
    ['sin el campo', undefined],
  ])('no sale con la decisión %s', (_n, valor) => {
    auth.$patch({ user: { id: 7, analytics_consent: valor } })
    expect(abierto(montar())).toBe(false)
  })

  it('no sale sin usuario (mientras initializeAuth resuelve, o sin sesión)', () => {
    auth.$patch({ user: null, isAuthenticated: false })
    expect(abierto(montar())).toBe(false)
  })

  it('no sale si la build no tiene Augur', () => {
    disponible.valor = false
    expect(abierto(montar())).toBe(false)
  })

  it('no sale en /privacy, y vuelve al salir de ella', async () => {
    ruta.actual.path = '/privacy'
    const w = montar()
    expect(abierto(w)).toBe(false)
    ruta.actual.path = '/library'
    await flushPromises()
    expect(abierto(w)).toBe(true)
  })

  it('enlaza a /privacy', () => {
    expect(montar().find('a[href="/privacy"]').exists()).toBe(true)
  })
})

describe('AnalyticsConsentModal — los botones', () => {
  it('«Sí, ayudar» guarda true y se cierra', async () => {
    const w = montar()
    await w.find('.analytics-consent-modal__accept').trigger('click')
    await flushPromises()
    expect(auth.updateAnalyticsConsent).toHaveBeenCalledTimes(1)
    expect(auth.updateAnalyticsConsent).toHaveBeenCalledWith(true)
    expect(abierto(w)).toBe(false)
  })

  it('«No, gracias» guarda false y se cierra', async () => {
    const w = montar()
    await w.find('.analytics-consent-modal__decline').trigger('click')
    await flushPromises()
    expect(auth.updateAnalyticsConsent).toHaveBeenCalledTimes(1)
    expect(auth.updateAnalyticsConsent).toHaveBeenCalledWith(false)
    expect(abierto(w)).toBe(false)
  })

  it('cerrar con la X no guarda nada, pero se cierra', async () => {
    const w = montar()
    await w.find('.base-modal__close').trigger('click')
    await flushPromises()
    expect(auth.updateAnalyticsConsent).not.toHaveBeenCalled()
    expect(abierto(w)).toBe(false)
    expect(auth.user.analytics_consent).toBeNull()
  })

  it('cerrar dura lo que dura el usuario: otro login vuelve a preguntar', async () => {
    const w = montar()
    await w.find('.base-modal__close').trigger('click')
    auth.$patch({ user: null, isAuthenticated: false })
    await flushPromises()
    auth.$patch({ user: { id: 8, analytics_consent: null }, isAuthenticated: true })
    await flushPromises()
    expect(abierto(w)).toBe(true)
  })

  it('si guardar falla avisa por uiStore.showError y no insiste', async () => {
    auth.updateAnalyticsConsent = vi.fn().mockRejectedValue(new Error('500'))
    const ui = useUIStore()
    const showError = vi.spyOn(ui, 'showError')
    const w = montar()
    await w.find('.analytics-consent-modal__accept').trigger('click')
    await flushPromises()
    expect(showError).toHaveBeenCalledTimes(1)
    expect(abierto(w)).toBe(false)
  })
})
