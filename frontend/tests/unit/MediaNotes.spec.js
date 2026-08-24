import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

const apiCall = vi.fn()

// El composable resuelve el store por su módulo; mockearlo evita montar Pinia
// entera para comprobar qué acción se pide al backend.
vi.mock('@/store/auth', () => ({
  useAuthStore: () => ({ authenticatedApiCall: apiCall })
}))

const MediaNotes = (await import('@/components/shared/MediaNotes.vue')).default
const { mediaRegistry } = await import('@/config/mediaRegistry')
const { mountComponent, createNotificationsStub } = await import('./helpers/mount')

/** Dialog de PrimeVue se teletransporta a body; en su lugar rinde el slot inline. */
const DialogStub = {
  props: ['visible'],
  template: '<div v-if="visible" class="dialog-stub"><slot /></div>'
}

const ok = (data = []) => ({ data: { status: 'success', data } })

/** Deja que se resuelva la carga inicial de notas antes de mirar el DOM. */
const flush = async () => {
  await nextTick()
  await nextTick()
}

function mountNotes (media, itemId, { notifications } = {}) {
  return mountComponent(MediaNotes, {
    props: { media, itemId },
    global: {
      provide: notifications ? { notifications } : {},
      stubs: { Dialog: DialogStub }
    }
  })
}

beforeEach(() => {
  apiCall.mockReset()
  apiCall.mockResolvedValue(ok())
})

describe('MediaNotes — carga inicial', () => {
  const casos = [
    ['book', 42, 'get_edition_notes', 'userEditionId'],
    ['movie', 'tt0111161', 'get_movie_notes', 'movieIsbn'],
    ['game', 7, 'get_game_notes', 'gameId'],
    ['album', 13, 'get_album_notes', 'albumId'],
    ['video', 'dQw4w9WgXcQ', 'get_video_notes', 'youtubeId']
  ]

  it.each(casos)('%s con id %s pide la acción %s y la clave %s', async (media, itemId, action, idKey) => {
    mountNotes(media, itemId)
    await nextTick()

    expect(apiCall).toHaveBeenCalledTimes(1)
    expect(apiCall).toHaveBeenCalledWith(action, { [idKey]: itemId })
  })

  it('no llama al backend si el itemId está vacío', async () => {
    mountNotes('album', 0)
    await nextTick()

    expect(apiCall).not.toHaveBeenCalled()
  })

  it('recarga al cambiar el itemId', async () => {
    const wrapper = mountNotes('game', 1)
    await nextTick()
    await wrapper.setProps({ itemId: 2 })
    await nextTick()

    expect(apiCall).toHaveBeenNthCalledWith(2, 'get_game_notes', { gameId: 2 })
  })
})

describe('MediaNotes — configuración por medio', () => {
  it('pinta el título y la ayuda del registry', async () => {
    const wrapper = mountNotes('movie', 'tt1')
    await flush()

    expect(wrapper.find('h3').text()).toBe(mediaRegistry.movie.notes.title)
    expect(wrapper.find('.empty-hint').text()).toBe(mediaRegistry.movie.notes.emptyHint)
  })

  it('solo las ediciones muestran el campo de página', async () => {
    const conPagina = mountNotes('book', 1)
    await conPagina.find('.add-note-btn').trigger('click')
    expect(conPagina.find('#pageNumber').exists()).toBe(true)

    const sinPagina = mountNotes('album', 1)
    await sinPagina.find('.add-note-btn').trigger('click')
    expect(sinPagina.find('#pageNumber').exists()).toBe(false)
  })

  it('las ediciones ofrecen 7 tipos de nota y el resto 3', () => {
    expect(mediaRegistry.book.notes.types).toHaveLength(7)
    for (const media of ['movie', 'game', 'album', 'video']) {
      expect(mediaRegistry[media].notes.types).toHaveLength(3)
    }
  })

  it('el botón de nueva nota se deshabilita sin itemId', async () => {
    const wrapper = mountNotes('album', 0)
    await nextTick()

    expect(wrapper.find('.add-note-btn').attributes('disabled')).toBeDefined()
  })
})

describe('MediaNotes — alta de nota', () => {
  it('rechaza el texto vacío sin llamar al backend', async () => {
    const notifications = createNotificationsStub()
    const wrapper = mountNotes('album', 13, { notifications })
    await nextTick()
    apiCall.mockClear()

    await wrapper.find('.add-note-btn').trigger('click')
    await wrapper.findAll('button').at(-1).trigger('click')

    expect(apiCall).not.toHaveBeenCalled()
    expect(notifications.calls).toEqual([
      { type: 'error', args: ['El contenido de la nota no puede estar vacío'] }
    ])
  })

  it('envía la acción y el payload del medio', async () => {
    const wrapper = mountNotes('album', 13)
    await nextTick()
    apiCall.mockClear()

    await wrapper.find('.add-note-btn').trigger('click')
    await wrapper.find('#noteText').setValue('Suena mejor de noche')
    await wrapper.findAll('button').at(-1).trigger('click')
    await nextTick()

    expect(apiCall).toHaveBeenCalledWith('add_album_note', {
      albumId: 13,
      noteText: 'Suena mejor de noche',
      noteType: 'note',
      isPrivate: true
    })
  })

  it('las ediciones añaden pageNumber al payload', async () => {
    const wrapper = mountNotes('book', 42)
    await nextTick()
    apiCall.mockClear()

    await wrapper.find('.add-note-btn').trigger('click')
    await wrapper.find('#noteText').setValue('Cita del capítulo 3')
    await wrapper.findAll('button').at(-1).trigger('click')
    await nextTick()

    expect(apiCall).toHaveBeenCalledWith('add_edition_note', {
      userEditionId: 42,
      noteText: 'Cita del capítulo 3',
      noteType: 'note',
      isPrivate: true,
      pageNumber: 1
    })
  })
})

describe('MediaNotes — orden de la lista', () => {
  const nota = (id, page, createdAt) => ({
    id, pageNumber: page, createdAt, noteText: 'n' + id, noteType: 'note', isPrivate: true, updatedAt: createdAt
  })

  it('las ediciones ordenan por página ascendente', async () => {
    apiCall.mockResolvedValue(ok([
      nota(1, 30, '2026-01-01'),
      nota(2, 10, '2026-01-02'),
      nota(3, 20, '2026-01-03')
    ]))

    const wrapper = mountNotes('book', 42)
    await nextTick()
    await nextTick()

    expect(wrapper.findAll('.note-text').map((n) => n.text())).toEqual(['n2', 'n3', 'n1'])
    expect(wrapper.findAll('.note-page').map((n) => n.text())).toEqual([
      'Página 10', 'Página 20', 'Página 30'
    ])
  })

  it('el resto ordena por fecha descendente y no muestra página', async () => {
    apiCall.mockResolvedValue(ok([
      nota(1, null, '2026-01-01'),
      nota(2, null, '2026-01-03'),
      nota(3, null, '2026-01-02')
    ]))

    const wrapper = mountNotes('album', 13)
    await nextTick()
    await nextTick()

    expect(wrapper.findAll('.note-text').map((n) => n.text())).toEqual(['n2', 'n3', 'n1'])
    expect(wrapper.find('.note-page').exists()).toBe(false)
  })
})

describe('MediaNotes — borrado', () => {
  it('pide confirmación y manda la acción del medio', async () => {
    apiCall.mockResolvedValue(ok([
      { id: 9, noteText: 'fuera', noteType: 'note', isPrivate: true, createdAt: '2026-01-01', updatedAt: '2026-01-01' }
    ]))
    const notifications = createNotificationsStub()
    const wrapper = mountNotes('game', 7, { notifications })
    await nextTick()
    await nextTick()
    apiCall.mockClear()
    apiCall.mockResolvedValue(ok())

    vi.spyOn(window, 'confirm').mockReturnValue(true)
    await wrapper.find('.note-action-btn.delete').trigger('click')
    await nextTick()

    expect(apiCall).toHaveBeenNthCalledWith(1, 'delete_game_note', { noteId: 9 })
    expect(apiCall).toHaveBeenNthCalledWith(2, 'get_game_notes', { gameId: 7 })
  })

  it('no borra si se cancela la confirmación', async () => {
    apiCall.mockResolvedValue(ok([
      { id: 9, noteText: 'sigue', noteType: 'note', isPrivate: true, createdAt: '2026-01-01', updatedAt: '2026-01-01' }
    ]))
    const wrapper = mountNotes('game', 7)
    await nextTick()
    await nextTick()
    apiCall.mockClear()

    vi.spyOn(window, 'confirm').mockReturnValue(false)
    await wrapper.find('.note-action-btn.delete').trigger('click')

    expect(apiCall).not.toHaveBeenCalled()
  })
})
