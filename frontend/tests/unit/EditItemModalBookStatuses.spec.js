import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { nextTick } from 'vue'
import { mountComponent } from './helpers/mount'
import EditItemModal from '@/components/EditItemModal.vue'
import { useBooksStore } from '@/store/books'
import { useSessionsStore } from '@/store/sessions'

const authenticatedApiCall = vi.fn(() => Promise.resolve({ data: { status: 'success', data: [] } }))

vi.mock('@/store/auth', () => ({
  useAuthStore: () => ({ authenticatedApiCall, apiCall: authenticatedApiCall })
}))

// La confirmación al cerrar un libro con sesión abierta: aquí se decide qué
// contesta el usuario.
const confirmStatusChangeWithSession = vi.fn()

vi.mock('@/composables/useConfirmationModal', () => ({
  useConfirmationModal: () => ({
    confirmDelete: vi.fn(),
    confirm: vi.fn(),
    confirmStatusChangeWithSession
  })
}))

const notificados = []
vi.mock('@/composables/useSessionFeedback', () => ({
  useSessionFeedback: () => ({
    notifyAutoSessionStart: (t) => notificados.push(['start', t]),
    notifyAutoSessionComplete: (t) => notificados.push(['complete', t]),
    notifyAutoSessionPause: (t) => notificados.push(['pause', t]),
    notifyAutoSessionAbandoned: (t) => notificados.push(['abandoned', t])
  })
}))

const libro = {
  isbn: '9780000000001',
  title: 'Fills de la boira',
  userStatuses: ['owned'],
  user_rating: 3,
  pages: 688
}

const flush = async () => {
  for (let i = 0; i < 6; i++) {
    await new Promise((r) => setTimeout(r, 0))
    await nextTick()
  }
}

const acciones = () => authenticatedApiCall.mock.calls.map((c) => c[0])
const payloadDe = (accion) => authenticatedApiCall.mock.calls.find((c) => c[0] === accion)?.[1]

const montar = () => mountComponent(EditItemModal, {
  props: { show: true, item: { ...libro }, itemType: 'book' }
})

describe('EditItemModal — los estados de un libro pasan por la máquina de transiciones', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockClear()
    confirmStatusChangeWithSession.mockReset()
    notificados.length = 0
    // `updateBookStatuses` busca el libro en el store para leer su sesión.
    useBooksStore().books = [{ ...libro }]
  })

  it('un cambio de estado se manda por update_book_user_statuses, no dentro de edit_user_book', async () => {
    const wrapper = montar()
    await flush()

    wrapper.vm.localStatuses = ['owned', 'read']
    await wrapper.vm.handleSave()
    await flush()

    expect(acciones()).toContain('update_book_user_statuses')
    expect(payloadDe('update_book_user_statuses')).toMatchObject({
      isbn: libro.isbn,
      statuses: ['owned', 'read']
    })
    // Y no se escriben dos veces: edit_user_book va sin `statuses`.
    expect(payloadDe('edit_user_book').data).not.toHaveProperty('statuses')
    // La transición se notifica, que es lo que estaba desconectado.
    expect(notificados).toContainEqual(['complete', libro.title])

    wrapper.unmount()
  })

  it('sin tocar los estados no se llama a update_book_user_statuses', async () => {
    const wrapper = montar()
    await flush()

    await wrapper.vm.handleSave()
    await flush()

    expect(acciones()).not.toContain('update_book_user_statuses')
    expect(acciones()).toContain('edit_user_book')

    wrapper.unmount()
  })

  it('cerrar un libro con una sesión abierta pide confirmación, y un «no» no guarda nada', async () => {
    // Esto es lo que estaba desconectado: con los estados dentro de
    // edit_user_book, la confirmación no llegaba a aparecer nunca.
    useSessionsStore().activeSessions = {
      [libro.isbn]: { session_number: 2, started_at: '2026-08-01' }
    }
    confirmStatusChangeWithSession.mockResolvedValue(false)

    const wrapper = montar()
    await flush()

    wrapper.vm.localStatuses = ['owned', 'read']
    await wrapper.vm.handleSave()
    await flush()

    expect(confirmStatusChangeWithSession).toHaveBeenCalledWith(
      libro.title,
      'read',
      expect.objectContaining({ hasActiveSession: true, sessionNumber: 2 })
    )
    // Ni los estados ni el resto: el guardado entero se abandona.
    expect(acciones()).not.toContain('update_book_user_statuses')
    expect(acciones()).not.toContain('edit_user_book')

    wrapper.unmount()
  })

  it('si el libro no está en el store se mantiene el camino de antes', async () => {
    useBooksStore().books = []
    const wrapper = montar()
    await flush()

    wrapper.vm.localStatuses = ['owned', 'read']
    await wrapper.vm.handleSave()
    await flush()

    expect(acciones()).not.toContain('update_book_user_statuses')
    expect(payloadDe('edit_user_book').data.statuses).toEqual(['owned', 'read'])

    wrapper.unmount()
  })
})
