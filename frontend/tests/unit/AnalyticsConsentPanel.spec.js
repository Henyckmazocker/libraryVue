// El interruptor de analítica de /profile (Plan «Consentimiento de Analítica», M3): refleja la
// decisión guardada y guarda al cambiar.
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import { mountComponent } from './helpers/mount'

const disponible = vi.hoisted(() => ({ valor: true }))
vi.mock('@/analytics', () => ({
  isAnalyticsAvailable: () => disponible.valor,
  syncAnalytics: vi.fn(),
  stopAnalytics: vi.fn(() => Promise.resolve()),
}))

import AnalyticsConsentPanel from '@/components/Social/AnalyticsConsentPanel.vue'
import { useAuthStore } from '@/store/auth'
import { useUIStore } from '@/store/ui'

const RouterLink = { props: ['to'], template: '<a :href="to"><slot /></a>' }

let auth
const montar = () => mountComponent(AnalyticsConsentPanel, {
  global: { stubs: { RouterLink, 'router-link': RouterLink } },
})
const interruptor = (w) => w.findComponent({ name: 'ToggleSwitch' })

beforeEach(() => {
  setActivePinia(createPinia())
  disponible.valor = true
  auth = useAuthStore()
  auth.$patch({ user: { id: 7, analytics_consent: null }, isAuthenticated: true })
  auth.updateAnalyticsConsent = vi.fn(async (consent) => {
    auth.user = { ...auth.user, analytics_consent: consent ? 1 : 0 }
    return { data: { status: 'success', data: { analytics_consent: consent ? 1 : 0 } } }
  })
})

describe('AnalyticsConsentPanel', () => {
  it.each([
    [1, true],
    [0, false],
    [null, false], // sin decidir sale apagado
  ])('con analytics_consent = %s el interruptor está a %s', (valor, esperado) => {
    auth.$patch({ user: { id: 7, analytics_consent: valor } })
    const w = montar()
    expect(interruptor(w).props('modelValue')).toBe(esperado)
    expect(interruptor(w).attributes('aria-labelledby') ?? interruptor(w).props('ariaLabelledby'))
      .toBe('analytics-consent-label')
  })

  it('encenderlo guarda true, apagarlo guarda false', async () => {
    const w = montar()
    interruptor(w).vm.$emit('update:modelValue', true)
    await flushPromises()
    expect(auth.updateAnalyticsConsent).toHaveBeenLastCalledWith(true)
    expect(interruptor(w).props('modelValue')).toBe(true)

    interruptor(w).vm.$emit('update:modelValue', false)
    await flushPromises()
    expect(auth.updateAnalyticsConsent).toHaveBeenLastCalledWith(false)
    expect(auth.updateAnalyticsConsent).toHaveBeenCalledTimes(2)
    expect(interruptor(w).props('modelValue')).toBe(false)
  })

  it('si guardar falla vuelve a su posición y avisa por uiStore.showError', async () => {
    auth.updateAnalyticsConsent = vi.fn().mockRejectedValue(new Error('500'))
    const showError = vi.spyOn(useUIStore(), 'showError')
    const w = montar()
    interruptor(w).vm.$emit('update:modelValue', true)
    await flushPromises()
    expect(showError).toHaveBeenCalledTimes(1)
    expect(interruptor(w).props('modelValue')).toBe(false)
  })

  it('enlaza a /privacy', () => {
    expect(montar().find('a[href="/privacy"]').exists()).toBe(true)
  })

  it('no se pinta sin Augur en la build', () => {
    disponible.valor = false
    expect(interruptor(montar()).exists()).toBe(false)
  })

  it('no se pinta si el backend aún no manda el campo', () => {
    auth.$patch({ user: { id: 7 } })
    auth.user = { id: 7 }
    expect(interruptor(montar()).exists()).toBe(false)
  })
})
