import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick, ref } from 'vue'
import { mountComponent } from './helpers/mount'
import MyLibrary from '@/components/MyLibrary.vue'

// Cada medio responde cuando el test lo decide, para poder comprobar que uno
// lento no retiene a los demás.
const pending = {}
let libraryItemsPromise = null
const resolveAction = (action, data) => {
  const p = pending[action]
  if (!p) throw new Error(`Nadie pidió ${action}. Pedidas: ${Object.keys(pending).join(', ')}`)
  delete pending[action]
  p.resolve({ data: { status: 'success', data } })
  return flush()
}

// Cada respuesta atraviesa varios `await` (el del store, el de `fetchLibrary` y
// el del propio render), así que un solo tick no basta.
const flush = async () => {
  for (let i = 0; i < 5; i++) {
    await new Promise((r) => setTimeout(r, 0))
    await nextTick()
  }
}
const rejectAction = (action, message) => {
  const p = pending[action]
  delete pending[action]
  p.reject(new Error(message))
  return flush()
}

const authenticatedApiCall = vi.fn((action) => new Promise((resolve, reject) => {
  pending[action] = { resolve, reject }
}))

vi.mock('@/store/auth', () => ({
  useAuthStore: () => ({ authenticatedApiCall, apiCall: authenticatedApiCall })
}))

// Libros y películas comparten get_library_items por _libraryCache; aquí se
// sustituye por una promesa controlable más, con la misma forma.
vi.mock('@/store/_libraryCache', () => ({
  // Deduplica como el real: libros y películas comparten get_library_items, y
  // dos promesas distintas dejarían una de las dos colgada para siempre.
  fetchLibraryItems: () => {
    if (!libraryItemsPromise) {
      libraryItemsPromise = new Promise((resolve, reject) => {
        pending.get_library_items = {
          resolve: (r) => resolve(r.data.data),
          reject
        }
      })
    }
    return libraryItemsPromise
  }
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() })
}))

// `onMounted` solo carga si hay sesión (MyLibrary.vue:429-433).
vi.mock('@/composables/useAuth', () => ({
  useAuth: () => ({ isAuthenticated: ref(true) })
}))

const titulos = (wrapper) => wrapper.findAll('.book-item').map((w) => w.text())

describe('MyLibrary — la carga no espera a la última respuesta', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockClear()
    Object.keys(pending).forEach((k) => delete pending[k])
    libraryItemsPromise = null
  })

  it('un medio lento no retiene a los que ya han respondido', async () => {
    const wrapper = mountComponent(MyLibrary)
    await nextTick()

    expect(titulos(wrapper)).toHaveLength(0)

    // Responden libros y películas (comparten get_library_items); álbumes,
    // juegos y vídeos siguen en vuelo.
    await resolveAction('get_library_items', {
      books: [{ isbn: '9780000000001', title: 'Fills de la boira' }],
      movies: []
    })

    expect(titulos(wrapper).join(' ')).toContain('Fills de la boira')
    expect(pending.get_albums).toBeDefined()
    expect(pending.get_videos).toBeDefined()

    wrapper.unmount()
  })

  it('un medio que falla no borra los otros y el aviso dice cuál fue', async () => {
    const wrapper = mountComponent(MyLibrary)
    await nextTick()

    await resolveAction('get_library_items', {
      books: [{ isbn: '9780000000001', title: 'Fills de la boira' }],
      movies: []
    })
    await resolveAction('get_games', [])
    await resolveAction('get_videos', [])
    await rejectAction('get_albums', 'Álbumes caído')

    // El libro que sí llegó sigue en la lista.
    expect(titulos(wrapper).join(' ')).toContain('Fills de la boira')

    const aviso = wrapper.find('.error-message')
    expect(aviso.exists()).toBe(true)
    expect(aviso.text().toLowerCase()).toContain('álbum')

    wrapper.unmount()
  })
})
