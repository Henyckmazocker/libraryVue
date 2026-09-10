import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { flushPromises } from '@vue/test-utils'
import PublicProfileView from '@/views/PublicProfileView.vue'
import { useAuthStore } from '@/store/auth'
import { mountComponent } from './helpers/mount'

/**
 * El bloque de acciones del perfil público, estado por estado.
 *
 * Son **cuatro** valores de `friend_status` y a ojo solo se prueban dos: el
 * defecto que sobrevivió meses —«Agregar amigo» ofrecido a quien ya es tu
 * amigo— era exactamente el estado que nadie miraba. Aquí se fija qué control
 * pinta cada uno.
 *
 * `vue-router` se mockea entero —y no solo `useRoute`— porque la vista importa
 * también `RouterLink` de ahí.
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

/** El contrato real de `GetPublicProfileUseCase::doExecute`, ni un campo más. */
const perfil = (friendStatus, friendshipId = 7) => ({
  id: 2,
  username: 'david',
  name: 'David',
  picture: null,
  friend_status: friendStatus,
  friendship_id: friendStatus === 'none' ? null : friendshipId
})

/**
 * Despacha por acción: la vista lanza las tres de carga (`get_public_profile`,
 * `get_user_lists` y `get_user_journal`), y aceptar añade dos más —
 * `accept_friend_request` y el `get_friends` con que el store se refresca.
 */
const montar = (friendStatus) => {
  const apiCall = vi.fn((accion) => {
    if (accion === 'get_public_profile') return Promise.resolve(respuesta(perfil(friendStatus)))
    if (accion === 'get_user_lists') return Promise.resolve(respuesta({ lists: [] }))
    if (accion === 'get_user_journal') {
      return Promise.resolve(respuesta({ entries: [], total: 0, hasMore: false }))
    }
    if (accion === 'get_friends') return Promise.resolve(respuesta([]))
    return Promise.resolve(respuesta(null))
  })

  useAuthStore().authenticatedApiCall = apiCall

  return { wrapper: mountComponent(PublicProfileView), apiCall }
}

/** Los controles del bloque de acciones, que es lo único que mira este fichero. */
const acciones = (wrapper) => wrapper.find('.public-profile-view__actions')
const botones = (wrapper) => wrapper.findAll('.public-profile-view__actions button')

describe('PublicProfileView — los cuatro estados de amistad', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('sin relación ofrece agregar, y nada más', async () => {
    const { wrapper } = montar('none')
    await flushPromises()

    expect(botones(wrapper)).toHaveLength(1)
    expect(acciones(wrapper).text()).toContain('Agregar amigo')
  })

  it('con la solicitud enviada no hay botón: solo la etiqueta', async () => {
    const { wrapper } = montar('pending_sent')
    await flushPromises()

    expect(botones(wrapper)).toHaveLength(0)
    expect(acciones(wrapper).text()).toContain('Solicitud enviada')
  })

  it('con la amistad aceptada sale «Amigos» y NINGÚN botón', async () => {
    // El defecto original: aquí es donde se ofrecía «Agregar amigo».
    const { wrapper } = montar('friends')
    await flushPromises()

    expect(botones(wrapper)).toHaveLength(0)
    expect(acciones(wrapper).text()).toContain('Amigos')
    expect(acciones(wrapper).text()).not.toContain('Agregar amigo')
  })

  it('con una solicitud recibida se puede aceptar o rechazar desde aquí', async () => {
    const { wrapper } = montar('pending_received')
    await flushPromises()

    expect(botones(wrapper)).toHaveLength(2)
    const texto = acciones(wrapper).text()
    expect(texto).toContain('Te ha enviado una solicitud')
    expect(texto).toContain('Aceptar')
    expect(texto).toContain('Rechazar')
  })

  it('aceptar desde el perfil manda el `friendship_id` y deja el estado en amigos', async () => {
    const { wrapper, apiCall } = montar('pending_received')
    await flushPromises()

    await botones(wrapper)[0].trigger('click')
    await flushPromises()

    expect(apiCall).toHaveBeenCalledWith('accept_friend_request', { friendshipId: 7 })
    // El store refresca la lista de amigos: /friends queda al día sin recargar.
    expect(apiCall).toHaveBeenCalledWith('get_friends', {})
    expect(botones(wrapper)).toHaveLength(0)
    expect(acciones(wrapper).text()).toContain('Amigos')
  })

  it('rechazar devuelve el perfil a «Agregar amigo»', async () => {
    const { wrapper, apiCall } = montar('pending_received')
    await flushPromises()

    await botones(wrapper)[1].trigger('click')
    await flushPromises()

    expect(apiCall).toHaveBeenCalledWith('reject_friend_request', { friendshipId: 7 })
    expect(botones(wrapper)).toHaveLength(1)
    expect(acciones(wrapper).text()).toContain('Agregar amigo')
  })

  it('en el perfil propio no se pinta ninguna acción', async () => {
    const { wrapper } = montar('none')
    useAuthStore().user = { username: 'david' }
    await flushPromises()

    expect(acciones(wrapper).exists()).toBe(false)
  })
})
