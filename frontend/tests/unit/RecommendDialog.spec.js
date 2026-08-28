import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import RecommendDialog from '@/components/Social/RecommendDialog.vue'
import { useSocialStore } from '@/store/social'
import { useInboxStore } from '@/store/inbox'
import { mountComponent, createNotificationsStub } from './helpers/mount'

/**
 * El diálogo de recomendar.
 *
 * Lo que se fija aquí es el contrato con el backend —qué viaja en el payload— y
 * las dos reglas que el M0 y el M1 dejaron escritas: el medio que se manda es el
 * de la **fila** (una serie viaja como `movie`, y eso lo resuelve quien llama con
 * `coverMedia`), y el mensaje de error se queda **dentro** del diálogo, porque el
 * 409 de «ya se la mandaste» hay que poder leerlo mientras se elige a otro amigo.
 */

/**
 * `Dialog` de PrimeVue se teletransporta a body y `@vue/test-utils` no lo ve, así
 * que se sustituye por un stub que rinde los dos slots inline — el mismo apaño
 * que `MediaNotes.spec.js:17`, más el `#footer`, donde viven los botones.
 */
const DialogStub = {
  props: ['visible'],
  template: '<div v-if="visible" class="dialog-stub"><slot /><slot name="footer" /></div>'
}

const amigos = [
  { id: 2, username: 'ana', name: 'Ana', picture: null },
  { id: 3, username: 'luis', name: 'Luis', picture: null }
]

const montar = (props = {}, notifications = createNotificationsStub()) => mountComponent(RecommendDialog, {
  props: {
    modelValue: true,
    entityType: 'movie',
    entityId: 'tt0111161',
    entityTitle: 'Cadena perpetua',
    entityCover: 'https://cdn.example/poster.jpg',
    ...props
  },
  global: {
    provide: { notifications },
    stubs: { Dialog: DialogStub }
  }
})

describe('RecommendDialog', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('pide los amigos al montar, que es cuando se abre', async () => {
    const social = useSocialStore()
    social.fetchFriends = vi.fn()

    montar()
    await flushPromises()

    expect(social.fetchFriends).toHaveBeenCalled()
  })

  it('manda el payload que espera send_recommendation', async () => {
    const social = useSocialStore()
    social.fetchFriends = vi.fn()
    social.friends = amigos

    const inbox = useInboxStore()
    inbox.sendRecommendation = vi.fn().mockResolvedValue({ success: true })

    const w = montar()
    await flushPromises()

    await w.findAll('.recommend-dialog__friend')[1].trigger('click')
    await w.find('textarea').setValue('Te va a gustar')
    await w.find('.recommend-dialog__action--primary').trigger('click')
    await flushPromises()

    expect(inbox.sendRecommendation).toHaveBeenCalledWith({
      recipientId: 3,
      entityType: 'movie',
      entityId: 'tt0111161',
      entityTitle: 'Cadena perpetua',
      entityCover: 'https://cdn.example/poster.jpg',
      comment: 'Te va a gustar'
    })
  })

  it('sin destinatario elegido, el botón de enviar está desactivado', async () => {
    const social = useSocialStore()
    social.fetchFriends = vi.fn()
    social.friends = amigos

    const w = montar()
    await flushPromises()

    expect(w.find('.recommend-dialog__action--primary').attributes('disabled')).toBeDefined()
  })

  it('el error se queda a la vista dentro del diálogo, y traducido por su código', async () => {
    const social = useSocialStore()
    social.fetchFriends = vi.fn()
    social.friends = amigos

    const inbox = useInboxStore()
    inbox.sendRecommendation = vi.fn().mockResolvedValue({
      success: false,
      code: 409,
      message: 'You already recommended this item to this friend'
    })

    const w = montar()
    await flushPromises()

    await w.find('.recommend-dialog__friend').trigger('click')
    await w.find('.recommend-dialog__action--primary').trigger('click')
    await flushPromises()

    expect(w.find('.recommend-dialog__error').text()).toBe('Ya le recomendaste esto a esta persona.')
    // Y el diálogo NO se cierra: hay que poder elegir a otro.
    expect(w.emitted('update:modelValue')).toBeFalsy()
  })

  it('un error sin código conocido se enseña tal cual, no se inventa', async () => {
    const social = useSocialStore()
    social.fetchFriends = vi.fn()
    social.friends = amigos

    const inbox = useInboxStore()
    inbox.sendRecommendation = vi.fn().mockResolvedValue({
      success: false,
      code: 500,
      message: 'Internal error'
    })

    const w = montar()
    await flushPromises()

    await w.find('.recommend-dialog__friend').trigger('click')
    await w.find('.recommend-dialog__action--primary').trigger('click')
    await flushPromises()

    // Peor que un mensaje en inglés es uno inventado que no describe qué pasó.
    expect(w.find('.recommend-dialog__error').text()).toBe('Internal error')
  })

  it('al enviar bien, avisa y se cierra', async () => {
    const social = useSocialStore()
    social.fetchFriends = vi.fn()
    social.friends = amigos

    const inbox = useInboxStore()
    inbox.sendRecommendation = vi.fn().mockResolvedValue({ success: true })
    const notifications = createNotificationsStub()

    const w = montar({}, notifications)
    await flushPromises()

    await w.find('.recommend-dialog__friend').trigger('click')
    await w.find('.recommend-dialog__action--primary').trigger('click')
    await flushPromises()

    expect(notifications.calls.some((c) => c.type === 'success')).toBe(true)
    expect(w.emitted('update:modelValue')?.at(-1)).toEqual([false])
  })

  it('sin amigos lo dice, en vez de enseñar una lista vacía', async () => {
    const social = useSocialStore()
    social.fetchFriends = vi.fn()
    social.friends = []

    const w = montar()
    await flushPromises()

    expect(w.text()).toContain('Aún no tienes amigos')
    expect(w.find('textarea').exists()).toBe(false)
  })
})
