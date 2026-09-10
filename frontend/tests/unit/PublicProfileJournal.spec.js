import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import PublicProfileView from '@/views/PublicProfileView.vue'
import { useAuthStore } from '@/store/auth'
import { mountComponent } from './helpers/mount'

/**
 * La sección del diario en el perfil público de otra persona.
 *
 * Lo que se comprueba aquí es **que no se enseña de más**, y la forma de
 * hacerlo es que la vista no decida nada: `get_user_journal` devuelve la misma
 * lista vacía sin amistad aceptada, con `show_journal` apagado y con el diario
 * vacío, y la sección simplemente no existe. Que el backend cierre las tres
 * puertas lo fijan sus tests (`UserJournalTest`, `GetUserJournalUseCaseTest`).
 *
 * `vue-router` se mockea entero —y no solo `useRoute`— porque la vista y la fila
 * del diario importan también `RouterLink` de ahí.
 */

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { username: 'david' } }),
  RouterLink: { name: 'RouterLink', props: ['to'], template: '<a><slot /></a>' }
}))

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: vi.fn() })
}))

const respuesta = (data, status = 'success') => ({
  data: { status, data, message: 'backend message in english', http_code: 200 }
})

// El contrato real de `GetPublicProfileUseCase::doExecute`, ni un campo más:
// este fixture declaraba seis que el backend nunca ha devuelto.
const perfil = {
  id: 2,
  username: 'david',
  name: 'David',
  picture: null,
  friend_status: 'friends',
  friendship_id: 7
}

const entrada = (extra = {}) => ({
  id: 1,
  media: 'movie',
  entity_id: 'tt0133093',
  entity_title: 'The Matrix',
  entity_cover: null,
  entry_date: '2026-08-27',
  rating: null,
  source: 'manual',
  is_repeat: false,
  ...extra
})

/**
 * Despacha por acción: la vista lanza las tres (`get_public_profile`,
 * `get_user_lists` y `get_user_journal`) y las dos últimas van sin `await`.
 */
const montar = (diario) => {
  const apiCall = vi.fn((accion) => {
    if (accion === 'get_public_profile') return Promise.resolve(respuesta(perfil))
    if (accion === 'get_user_lists') return Promise.resolve(respuesta({ lists: [] }))
    if (accion === 'get_user_journal') return diario()
    return Promise.resolve(respuesta(null, 'error'))
  })

  useAuthStore().authenticatedApiCall = apiCall

  return { wrapper: mountComponent(PublicProfileView), apiCall }
}

describe('PublicProfileView — la sección del diario', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('sin entradas no hay sección, y no se dice por qué', async () => {
    const { wrapper } = montar(() => Promise.resolve(respuesta({
      entries: [], total: 0, hasMore: false
    })))
    await flushPromises()

    expect(wrapper.find('.public-profile-view__journal').exists()).toBe(false)
    // El perfil sí está: la ausencia es de la sección, no de la página.
    expect(wrapper.find('.public-profile-view__username').text()).toBe('david')
  })

  it('con entradas pinta la sección, con su encabezado de día', async () => {
    const { wrapper } = montar(() => Promise.resolve(respuesta({
      entries: [entrada()], total: 1, hasMore: false
    })))
    await flushPromises()

    expect(wrapper.find('.public-profile-view__journal').exists()).toBe(true)
    expect(wrapper.findAll('.journal-row')).toHaveLength(1)
    expect(wrapper.find('.public-profile-view__journal-day-title').text()).not.toBe('')
    expect(wrapper.text()).toContain('The Matrix')
  })

  it('las filas van en modo lectura: ni un menú ⋯ en el perfil ajeno', async () => {
    const { wrapper } = montar(() => Promise.resolve(respuesta({
      entries: [entrada()], total: 1, hasMore: false
    })))
    await flushPromises()

    expect(wrapper.find('.journal-row__menu').exists()).toBe(false)
  })

  it('el botón de ver más sale solo si el backend dice que hay más', async () => {
    const sinMas = montar(() => Promise.resolve(respuesta({
      entries: [entrada()], total: 1, hasMore: false
    })))
    await flushPromises()
    expect(sinMas.wrapper.find('.public-profile-view__journal-more').exists()).toBe(false)

    setActivePinia(createPinia())
    const conMas = montar(() => Promise.resolve(respuesta({
      entries: [entrada()], total: 30, hasMore: true
    })))
    await flushPromises()
    expect(conMas.wrapper.find('.public-profile-view__journal-more').exists()).toBe(true)
  })

  it('ver más pide la página siguiente y la acumula bajo el mismo encabezado', async () => {
    let llamadas = 0
    const { wrapper, apiCall } = montar(() => {
      llamadas += 1
      return Promise.resolve(respuesta({
        entries: [entrada({ id: llamadas })],
        total: 2,
        hasMore: llamadas === 1
      }))
    })
    await flushPromises()

    await wrapper.find('.public-profile-view__journal-more').trigger('click')
    await flushPromises()

    expect(apiCall).toHaveBeenLastCalledWith('get_user_journal', {
      username: 'david', limit: 10, offset: 1
    })
    expect(wrapper.findAll('.journal-row')).toHaveLength(2)
  })

  it('si el diario falla, el perfil se pinta entero igual', async () => {
    // La carga va fuera del `try` del perfil y sin `await`, como la de listas:
    // un fallo suyo se lleva la sección, no la página.
    const { wrapper } = montar(() => Promise.reject(new Error('boom')))
    await flushPromises()

    expect(wrapper.find('.public-profile-view__username').text()).toBe('david')
    expect(wrapper.find('.public-profile-view__journal').exists()).toBe(false)
    expect(wrapper.find('.public-profile-view__error').exists()).toBe(false)
  })
})
