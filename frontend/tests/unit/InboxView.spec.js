import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { RouterLinkStub } from '@vue/test-utils'
import { flushPromises } from '@vue/test-utils'
import InboxView from '@/views/InboxView.vue'
import { useInboxStore } from '@/store/inbox'
import { mountComponent, createNotificationsStub } from './helpers/mount'

/**
 * La bandeja: la lista, el vacío y los dos botones.
 *
 * El store se sustituye por dobles en vez de mockear `auth.apiCall`, porque lo
 * que esta vista tiene que garantizar es que **despacha por tipo de tarjeta** y
 * que cada botón llama a lo suyo con la recomendación entera. Que las acciones
 * hablen bien con el backend es cosa de los siete tests de integración.
 *
 * `RouterLinkStub` no es opcional: la tarjeta pinta enlaces al ítem y al perfil,
 * y sin él cada montaje escupe `Failed to resolve component: router-link` sin
 * llegar a fallar — la misma clase de fallo que dejó `v-tooltip` tres meses sin
 * registrar.
 */

const recomendacion = (extra = {}) => ({
  id: 1,
  kind: 'recommendation',
  entity_type: 'album',
  entity_id: 'f2e3a1b0-0000-4000-8000-000000000000',
  entity_title: 'Prequelle',
  entity_cover: null,
  comment: 'Escúchalo entero',
  status: 'pending',
  created_at: new Date().toISOString(),
  sender: { id: 2, username: 'david', name: 'David' },
  ...extra
})

const montar = (notifications = createNotificationsStub()) => mountComponent(InboxView, {
  global: {
    stubs: { RouterLink: RouterLinkStub },
    provide: { notifications }
  }
})

describe('InboxView', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('pide la bandeja al montar', () => {
    const store = useInboxStore()
    store.fetchInbox = vi.fn()

    montar()

    expect(store.fetchInbox).toHaveBeenCalled()
  })

  it('dice el vacío con texto, no con un spinner eterno', async () => {
    const store = useInboxStore()
    store.fetchInbox = vi.fn()
    store.items = []
    store.isLoading = false

    const w = montar()
    await flushPromises()

    expect(w.text()).toContain('No tienes recomendaciones pendientes')
    expect(w.find('.pi-spin').exists()).toBe(false)
  })

  it('pinta una tarjeta por recomendación, con quién la manda y su comentario', async () => {
    const store = useInboxStore()
    store.fetchInbox = vi.fn()
    store.items = [recomendacion(), recomendacion({ id: 2, entity_title: 'Meliora' })]
    store.isLoading = false

    const w = montar()
    await flushPromises()

    expect(w.findAll('.recommendation-card')).toHaveLength(2)
    expect(w.text()).toContain('david')
    expect(w.text()).toContain('Prequelle')
    expect(w.text()).toContain('Escúchalo entero')
  })

  it('«Descartar» llama a dismiss con la recomendación entera', async () => {
    const store = useInboxStore()
    store.fetchInbox = vi.fn()
    store.dismiss = vi.fn().mockResolvedValue({ success: true })
    store.items = [recomendacion()]
    store.isLoading = false

    const w = montar()
    await flushPromises()

    await w.findAll('.recommendation-card__action')[1].trigger('click')

    expect(store.dismiss).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }))
  })

  it('«Añadir» llama a addToLibrary y avisa al usuario', async () => {
    const store = useInboxStore()
    const notifications = createNotificationsStub()
    store.fetchInbox = vi.fn()
    store.addToLibrary = vi.fn().mockResolvedValue({ success: true })
    store.items = [recomendacion()]
    store.isLoading = false

    const w = montar(notifications)
    await flushPromises()

    await w.find('.recommendation-card__action--add').trigger('click')
    await flushPromises()

    expect(store.addToLibrary).toHaveBeenCalledWith(expect.objectContaining({ id: 1 }))
    expect(notifications.calls.some((c) => c.type === 'success')).toBe(true)
  })

  it('un fallo al añadir se dice, y la recomendación no desaparece', async () => {
    const store = useInboxStore()
    const notifications = createNotificationsStub()
    store.fetchInbox = vi.fn()
    store.addToLibrary = vi.fn().mockResolvedValue({ success: false, message: 'No se pudo recuperar la ficha' })
    store.items = [recomendacion()]
    store.isLoading = false

    const w = montar(notifications)
    await flushPromises()

    await w.find('.recommendation-card__action--add').trigger('click')
    await flushPromises()

    expect(notifications.calls.some((c) => c.type === 'error')).toBe(true)
    expect(w.findAll('.recommendation-card')).toHaveLength(1)
  })

  it('mientras se resuelve una, solo se desactivan SUS botones', async () => {
    const store = useInboxStore()
    store.fetchInbox = vi.fn()
    store.items = [recomendacion(), recomendacion({ id: 2 })]
    store.isLoading = false
    store.resolvingId = 1

    const w = montar()
    await flushPromises()

    const tarjetas = w.findAll('.recommendation-card')
    expect(tarjetas[0].findAll('button')[0].attributes('disabled')).toBeDefined()
    expect(tarjetas[1].findAll('button')[0].attributes('disabled')).toBeUndefined()
  })
})
