import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useBooks } from '@/composables/useBooks'
import { useBooksStore } from '@/store/books'
import { useSessionsStore } from '@/store/sessions'

// Lo que contesta el backend a `get_active_reading_session`, que es la petición
// que este hito añade: hasta ahora el mapa `activeSessions` del store no lo
// llenaba nadie y el diálogo de confirmación no se ejecutaba jamás.
let sesionActiva = null
let sesionRota = false

const authenticatedApiCall = vi.fn((accion) => {
  if (accion === 'get_active_reading_session') {
    // El backend caído: `loadActiveSession` se traga el fallo, borra la entrada
    // y devuelve `{ success: false }`.
    if (sesionRota) return Promise.reject(new Error('Network Error'))
    return Promise.resolve({ data: { status: 'success', data: sesionActiva } })
  }
  return Promise.resolve({ data: { status: 'success', data: null } })
})

vi.mock('@/store/auth', () => ({
  useAuthStore: () => ({ authenticatedApiCall })
}))

const confirmStatusChangeWithSession = vi.fn()

vi.mock('@/composables/useConfirmationModal', () => ({
  useConfirmationModal: () => ({
    confirm: vi.fn(),
    confirmDelete: vi.fn(),
    confirmStatusChangeWithSession
  })
}))

vi.mock('@/composables/useSessionFeedback', () => ({
  useSessionFeedback: () => ({
    notifyAutoSessionStart: vi.fn(),
    notifyAutoSessionComplete: vi.fn(),
    notifyAutoSessionPause: vi.fn(),
    notifyAutoSessionAbandoned: vi.fn()
  })
}))

const libro = {
  isbn: '9788427200203',
  title: 'Los Juegos del Hambre',
  userStatuses: ['owned', 'reading'],
  current_page: 68,
  pages: 334
}

const acciones = () => authenticatedApiCall.mock.calls.map((c) => c[0])
const vecesPedida = (accion) => acciones().filter((a) => a === accion).length

describe('useBooks — la sesión activa se pide al cambiar de estado', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    authenticatedApiCall.mockClear()
    confirmStatusChangeWithSession.mockReset()
    sesionActiva = null
    sesionRota = false
    useBooksStore().books = [{ ...libro }]
  })

  it('con una sesión abierta, marcar «leído» pide el diálogo con su número y la página actual', async () => {
    sesionActiva = { id: 6, session_number: 3, started_at: '2026-09-01' }
    confirmStatusChangeWithSession.mockResolvedValue(false)

    const { updateBookStatuses } = useBooks()
    const resultado = await updateBookStatuses(libro.isbn, ['owned', 'read'])

    expect(confirmStatusChangeWithSession).toHaveBeenCalledWith(
      libro.title,
      'read',
      {
        hasActiveSession: true,
        sessionNumber: 3,
        // El diálogo pinta la página del LIBRO, no la de la sesión.
        currentPage: 68,
        totalPages: 334,
        startedAt: '2026-09-01'
      }
    )
    // Cancelar deja el estado sin cambiar: no se llega al backend.
    expect(resultado).toEqual({ success: false, cancelled: true })
    expect(acciones()).not.toContain('update_book_user_statuses')
    expect(useBooksStore().books[0].userStatuses).toEqual(['owned', 'reading'])
  })

  it('aceptar el diálogo cambia el estado', async () => {
    sesionActiva = { id: 6, session_number: 3, started_at: '2026-09-01' }
    confirmStatusChangeWithSession.mockResolvedValue(true)

    const { updateBookStatuses } = useBooks()
    const resultado = await updateBookStatuses(libro.isbn, ['owned', 'read'])

    expect(resultado).toEqual({ success: true, statuses: ['owned', 'read'] })
    expect(authenticatedApiCall).toHaveBeenCalledWith('update_book_user_statuses', {
      isbn: libro.isbn,
      statuses: ['owned', 'read']
    })
    expect(useBooksStore().books[0].userStatuses).toEqual(['owned', 'read'])
  })

  it('«abandonado» pasa por el mismo diálogo', async () => {
    sesionActiva = { id: 6, session_number: 3, started_at: '2026-09-01' }
    confirmStatusChangeWithSession.mockResolvedValue(true)

    const { updateBookStatuses } = useBooks()
    await updateBookStatuses(libro.isbn, ['owned', 'abandoned'])

    expect(confirmStatusChangeWithSession).toHaveBeenCalledWith(
      libro.title,
      'abandoned',
      expect.objectContaining({ hasActiveSession: true, sessionNumber: 3 })
    )
  })

  it('terminar un libro retira la lectura en curso del payload', async () => {
    // El defecto que solo se vio en el navegador: el desplegable solo sabe AÑADIR,
    // así que marcar «leído» sobre un libro en curso mandaba los tres estados y el
    // libro quedaba leyendo y leído a la vez, con la sesión ya cerrada.
    // `validateStatusLogic` no lo impide porque `read` es histórico.
    sesionActiva = { id: 6, session_number: 1, started_at: '2026-09-03' }
    confirmStatusChangeWithSession.mockResolvedValue(true)

    const { updateBookStatuses } = useBooks()
    const resultado = await updateBookStatuses(libro.isbn, ['owned', 'reading', 'read'])

    expect(authenticatedApiCall).toHaveBeenCalledWith('update_book_user_statuses', {
      isbn: libro.isbn,
      statuses: ['owned', 'read']
    })
    // Y los efectivos vuelven, para que la ficha no pinte un `reading` que ya no está.
    expect(resultado.statuses).toEqual(['owned', 'read'])
  })

  it('re-reading sobrevive si no se está terminando el libro', async () => {
    // `read` + `re-reading` es una combinación VÁLIDA: se llega a ella marcando
    // `re-reading` sobre un libro ya leído. Lo que se retira es la lectura en curso
    // en el momento de terminar, no la posibilidad de releer.
    sesionActiva = null
    const conRelectura = { ...libro, userStatuses: ['owned', 'read'] }
    useBooksStore().books = [conRelectura]

    const { updateBookStatuses } = useBooks()
    await updateBookStatuses(libro.isbn, ['owned', 'read', 're-reading'])

    expect(authenticatedApiCall).toHaveBeenCalledWith('update_book_user_statuses', {
      isbn: libro.isbn,
      statuses: ['owned', 'read', 're-reading']
    })
  })

  it('sin sesión abierta no aparece nada, y la sesión se pide UNA sola vez por cambio de estado', async () => {
    const { updateBookStatuses } = useBooks()
    const resultado = await updateBookStatuses(libro.isbn, ['owned', 'read'])

    expect(confirmStatusChangeWithSession).not.toHaveBeenCalled()
    expect(resultado).toEqual({ success: true, statuses: ['owned', 'read'] })
    // El coste del hito: una petición por cambio de estado, no por libro listado.
    expect(vecesPedida('get_active_reading_session')).toBe(1)
  })

  it('con el backend caído el cambio de estado sigue adelante, sin diálogo', async () => {
    // El mapa trae una sesión de antes; la carga falla y `loadActiveSession`
    // borra la entrada, así que el getter no encuentra nada y no hay diálogo.
    useSessionsStore().activeSessions = {
      [libro.isbn]: { id: 6, session_number: 3, started_at: '2026-09-01' }
    }
    sesionRota = true

    const { updateBookStatuses } = useBooks()
    const resultado = await updateBookStatuses(libro.isbn, ['owned', 'read'])

    expect(confirmStatusChangeWithSession).not.toHaveBeenCalled()
    expect(resultado).toEqual({ success: true, statuses: ['owned', 'read'] })
    expect(acciones()).toContain('update_book_user_statuses')
    expect(useSessionsStore().activeSessions[libro.isbn]).toBeUndefined()
  })

  it('una sesión ya cerrada en el servidor no revive el diálogo desde el mapa del store', async () => {
    useSessionsStore().activeSessions = {
      [libro.isbn]: { id: 6, session_number: 3, started_at: '2026-09-01' }
    }
    sesionActiva = null

    const { updateBookStatuses } = useBooks()
    await updateBookStatuses(libro.isbn, ['owned', 'read'])

    expect(confirmStatusChangeWithSession).not.toHaveBeenCalled()
    expect(acciones()).toContain('update_book_user_statuses')
  })
})
