import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { flushPromises } from '@vue/test-utils'
import PrivacySettingsPanel from '@/components/Social/PrivacySettingsPanel.vue'
import { mountComponent } from './helpers/mount'

/**
 * El panel de privacidad, que hasta el 2026-08-25 pintaba cuatro campos que no
 * existen (`profile_visibility`, `library_visibility`, `feed_visibility`,
 * `allow_friend_requests`).
 *
 * Lo que se fija aquí es que manda **exactamente** las siete claves reales de
 * `user_privacy_settings` —seis del feed más `show_journal`, que es permiso de
 * lectura del diario y no un evento—. No es un detalle de nombres:
 * `UpdatePrivacySettingsCommand::fromArray` las lee con un `?? true` de
 * respaldo, así que una clave que no llegue **no se conserva, se resetea**.
 */

const fetchPrivacySettings = vi.fn()
const updatePrivacySettings = vi.fn().mockResolvedValue({})

// Un `ref` de verdad, no un objeto con `.value`: el componente hace
// `watch(privacySettings, …, { immediate: true })` y con un objeto plano el
// watcher no se dispara nunca — el `v-if` ve algo truthy y los tests pasan
// mintiendo. Descubierto escribiendo estos tests.
const ajustes = ref(null)

vi.mock('@/composables/usePrivacySettings', () => ({
  usePrivacySettings: () => ({
    privacySettings: ajustes,
    fetchPrivacySettings,
    updatePrivacySettings
  })
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() })
}))

const LAS_SIETE = [
  'show_additions',
  'show_status_changes',
  'show_ratings',
  'show_notes',
  'show_reading_sessions',
  'show_achievements',
  'show_journal'
]

const guardados = () => ({
  show_additions: true,
  show_status_changes: true,
  show_ratings: true,
  show_notes: false,
  show_reading_sessions: true,
  show_achievements: true,
  show_journal: false
})

describe('PrivacySettingsPanel — las siete columnas reales', () => {
  beforeEach(() => {
    ajustes.value = guardados()
    updatePrivacySettings.mockClear()
    fetchPrivacySettings.mockClear()
  })

  it('pinta un interruptor por cada ajuste que existe, y ninguno más', () => {
    const w = mountComponent(PrivacySettingsPanel)

    expect(w.findAllComponents({ name: 'ToggleSwitch' })).toHaveLength(7)
    // Los desplegables de visibilidad eran de campos inexistentes.
    expect(w.findAllComponents({ name: 'Select' })).toHaveLength(0)
  })

  it('guarda las siete claves con el nombre que espera el backend', async () => {
    const w = mountComponent(PrivacySettingsPanel)
    await flushPromises()

    await w.find('button').trigger('click')

    expect(updatePrivacySettings).toHaveBeenCalledTimes(1)
    expect(Object.keys(updatePrivacySettings.mock.calls[0][0]).sort()).toEqual([...LAS_SIETE].sort())
  })

  it('un `false` guardado sobrevive a la carga', async () => {
    // Con `||` en vez de `??` esto se perdería: `show_notes` volvería a su
    // default y el ajuste parecería no guardarse nunca.
    ajustes.value = { ...guardados(), show_notes: false, show_ratings: false }

    const w = mountComponent(PrivacySettingsPanel)
    await flushPromises()
    await w.find('button').trigger('click')

    const enviado = updatePrivacySettings.mock.calls[0][0]
    expect(enviado.show_notes).toBe(false)
    expect(enviado.show_ratings).toBe(false)
  })

  it('`show_notes` avisa de que las notas privadas nunca se publican', () => {
    // Los dos interruptores se confunden con facilidad: éste decide si el
    // evento se VE, y el `is_private` de la nota si llega a emitirse.
    const w = mountComponent(PrivacySettingsPanel)

    expect(w.text()).toContain('Las privadas no se publican nunca')
  })

  it('el diario apagado sobrevive a la carga y viaja como `false`', async () => {
    // Mismo riesgo que `show_notes`, y aquí es el peor de los dos: si un
    // `false` se perdiera, «Guardar» encendería el diario sin pedirlo.
    ajustes.value = { ...guardados(), show_journal: false }

    const w = mountComponent(PrivacySettingsPanel)
    await flushPromises()
    await w.find('button').trigger('click')

    expect(updatePrivacySettings.mock.calls[0][0].show_journal).toBe(false)
  })

  it('el diario avisa de que no se publica en el feed', () => {
    // Los otros seis rótulos dicen «cuando yo hago X» y el intro del panel
    // habla del feed: sin el aviso, éste se lee como uno más de esos.
    const w = mountComponent(PrivacySettingsPanel)

    expect(w.text()).toContain('No se publica en el feed')
  })

  it('sin ajustes cargados enseña el spinner y los pide', () => {
    ajustes.value = null

    const w = mountComponent(PrivacySettingsPanel)

    expect(w.find('.privacy-settings-panel__loading').exists()).toBe(true)
    expect(fetchPrivacySettings).toHaveBeenCalled()
  })
})
