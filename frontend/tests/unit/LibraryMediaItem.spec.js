import { describe, expect, it } from 'vitest'
import LibraryMediaItem from '@/components/shared/LibraryMediaItem.vue'
import { mediaKeys } from '@/config/mediaRegistry'
import { mountComponent } from './helpers/mount'

const mount = (media, item, options = {}) => mountComponent(LibraryMediaItem, {
  props: {
    media,
    item,
    allowedStatuses: options.allowedStatuses ?? ['owned', 'wishlist'],
    isNew: options.isNew ?? false,
    canDelete: options.canDelete ?? true
  }
})

const texto = (wrapper) => wrapper.text().replace(/\s+/g, ' ')

describe('LibraryMediaItem — clases por medio', () => {
  it.each(mediaKeys)('%s pinta las clases que espera el mixin library-item', (media) => {
    const wrapper = mount(media, { title: 'X', isbn: '1', id: 1 })

    expect(wrapper.classes()).toContain(`library-${media}-item-container`)
    expect(wrapper.find(`.${media}-details`).exists()).toBe(true)
    expect(wrapper.find(`.${media}-title`).exists()).toBe(true)
    expect(wrapper.find(`.${media}-actions`).exists()).toBe(true)
  })
})

describe('LibraryMediaItem — campos declarados en el registry', () => {
  it('el libro pinta autor, editorial y fecha con sus rótulos', () => {
    const wrapper = mount('book', {
      title: 'Dune', author: 'Frank Herbert', publisher: 'Ace', publicationDate: '1965'
    })

    expect(wrapper.find('.book-title').text()).toBe('Dune')
    expect(texto(wrapper)).toContain('Author: Frank Herbert')
    expect(texto(wrapper)).toContain('Publisher: Ace')
    expect(texto(wrapper)).toContain('Publication Date: 1965')
  })

  it('el libro prefiere la lista `publishers` a `publisher`', () => {
    const wrapper = mount('book', { title: 'X', publishers: ['Ace', 'Gollancz'], publisher: 'Otra' })
    expect(texto(wrapper)).toContain('Publisher: Ace, Gollancz')
  })

  it('la película oculta el título original si coincide con el título', () => {
    expect(texto(mount('movie', { title: 'Alien', originalTitle: 'Alien', isbn: 'tt1' })))
      .not.toContain('Original Title')
    expect(texto(mount('movie', { title: 'Alien', originalTitle: 'Xenomorph', isbn: 'tt1' })))
      .toContain('Original Title: Xenomorph')
  })

  it('la película siempre pinta el IMDb ID, aunque el resto falte', () => {
    expect(texto(mount('movie', { title: 'Alien', isbn: 'tt0078748' }))).toContain('IMDb ID: tt0078748')
  })

  it('el juego une desarrolladores, plataformas y géneros vengan como vengan', () => {
    const wrapper = mount('game', {
      name: 'Hollow Knight',
      developers: [{ name: 'Team Cherry' }],
      platforms: [{ platform: { name: 'PC' } }, 'Switch'],
      genres: ['Metroidvania', { name: 'Acción' }],
      id: 7
    })

    expect(texto(wrapper)).toContain('Desarrollador: Team Cherry')
    expect(texto(wrapper)).toContain('Plataformas: PC, Switch')
    expect(texto(wrapper)).toContain('Géneros: Metroidvania, Acción')
    expect(texto(wrapper)).toContain('RAWG ID: 7')
  })

  it('el juego colorea el Metacritic por tramos', () => {
    expect(mount('game', { name: 'A', id: 1, metacritic: 90 }).find('.score-high').exists()).toBe(true)
    expect(mount('game', { name: 'A', id: 1, metacritic: 60 }).find('.score-medium').exists()).toBe(true)
    expect(mount('game', { name: 'A', id: 1, metacritic: 20 }).find('.score-low').exists()).toBe(true)
  })

  it('el álbum formatea la duración en horas o en minutos:segundos', () => {
    expect(texto(mount('album', { name: 'A', duration_ms: 4_500_000 }))).toContain('Duración: 1h 15m')
    expect(texto(mount('album', { name: 'A', duration_ms: 2_587_000 }))).toContain('Duración: 43:07')
  })

  it('el vídeo pinta canal, duración y su id de YouTube', () => {
    const wrapper = mount('video', {
      title: 'Charla', channel_name: 'Canal X', duration: '12:04', youtube_id: 'abc'
    })

    expect(texto(wrapper)).toContain('Canal: Canal X')
    expect(texto(wrapper)).toContain('Duración: 12:04')
    expect(texto(wrapper)).toContain('YouTube ID: abc')
  })
})

describe('LibraryMediaItem — bloque de solo lectura', () => {
  it('libro y película sacan el formato suelto, sin envoltorio', () => {
    const wrapper = mount('movie', { title: 'X', isbn: 't1', ownershipFormat: { label: 'Blu-ray' } })

    expect(wrapper.find('.movie-specific-fields').exists()).toBe(false)
    expect(wrapper.find('.ownership-format-badge').text()).toBe('Blu-ray')
  })

  it.each(['game', 'album', 'video'])('%s agrupa sus campos en .readonly-fields', (media) => {
    const wrapper = mount(media, {
      title: 'X', name: 'X', id: 1, personalNotes: 'una nota', notes: 'una nota'
    })

    expect(wrapper.find(`.${media}-specific-fields`).classes()).toContain('readonly-fields')
    expect(texto(wrapper)).toContain('Notas: una nota')
  })

  it('el bloque desaparece cuando no hay nada que enseñar', () => {
    expect(mount('album', { name: 'A' }).find('.album-specific-fields').exists()).toBe(false)
  })
})

describe('LibraryMediaItem — estado por defecto al añadir', () => {
  it.each(['book', 'movie', 'game', 'album'])('%s preselecciona `owned` si está permitido', (media) => {
    const wrapper = mount(media, { title: 'X', name: 'X', id: 1 }, { isNew: true })
    expect(wrapper.vm.$.setupState.selectedUserStatuses).toEqual(['owned'])
  })

  it('el vídeo es el único que no preselecciona nada', () => {
    const wrapper = mount('video', { title: 'X' }, { isNew: true })
    expect(wrapper.vm.$.setupState.selectedUserStatuses).toEqual([])
  })

  it('los estados que ya tiene el ítem mandan sobre el valor por defecto', () => {
    const wrapper = mount('book', { title: 'X', userStatuses: ['reading'] }, { isNew: true })
    expect(wrapper.vm.$.setupState.selectedUserStatuses).toEqual(['reading'])
  })
})

describe('LibraryMediaItem — payloads de los eventos', () => {
  const guardar = (wrapper) => wrapper.find('.save-button').trigger('click')
  const borrar = (wrapper) => wrapper.find('.delete-button').trigger('click')

  it.each([
    ['book', { title: 'X', isbn: '1' }, 'book'],
    ['movie', { title: 'X', isbn: 't1' }, 'movie'],
    ['game', { name: 'X', id: 1 }, 'game']
  ])('%s emite el ítem anidado bajo su clave, con estados y itemType', async (media, item, key) => {
    const wrapper = mount(media, item, { isNew: true })
    await guardar(wrapper)

    const [payload] = wrapper.emitted('save')[0]
    expect(payload).toHaveProperty(key)
    expect(payload.itemType).toBe(media)
    expect(payload.statuses).toEqual(['owned'])
  })

  it.each([
    ['album', { name: 'X', id: 1 }],
    ['video', { title: 'X', youtube_id: 'abc' }]
  ])('%s emite el ítem entero, sin envolver', async (media, item) => {
    const wrapper = mount(media, item, { isNew: true })
    await guardar(wrapper)

    const [payload] = wrapper.emitted('save')[0]
    expect(payload).not.toHaveProperty(media)
    expect(payload).toHaveProperty('userStatuses')
    expect(payload.title || payload.name).toBe('X')
  })

  it('el borrado de película manda `imdbID` con el valor de `isbn`', async () => {
    const wrapper = mount('movie', { title: 'X', isbn: 'tt1', imdbID: 'OTRO' })
    await borrar(wrapper)

    expect(wrapper.emitted('delete')[0][0]).toEqual({ isbn: 'tt1', imdbID: 'tt1', itemType: 'movie' })
  })

  it.each([
    ['album', { name: 'X', id: 9, spotify_id: 'sp' }, 9],
    ['video', { title: 'X', youtube_id: 'abc' }, 'abc']
  ])('%s borra con un identificador escalar, no con un objeto', async (media, item, esperado) => {
    const wrapper = mount(media, item)
    await borrar(wrapper)

    expect(wrapper.emitted('delete')[0][0]).toBe(esperado)
  })

  it('el juego añade sus campos propios al guardar y al editar', async () => {
    const wrapper = mount('game', { name: 'X', id: 1, hours_played: 12 }, { isNew: true })
    await guardar(wrapper)

    expect(wrapper.emitted('save')[0][0].game.hoursPlayed).toBe(12)
  })
})

describe('LibraryMediaItem — el feedback lo confirma el padre', () => {
  it('el botón de guardar no se pone en verde solo', async () => {
    const wrapper = mount('video', { title: 'X' }, { isNew: true })
    await wrapper.find('.save-button').trigger('click')

    // Antes, álbumes y vídeos pasaban a `success` en el acto (1500 ms).
    expect(wrapper.find('.save-button').classes()).toContain('save-button--idle')
  })

  it('setSaveSuccess y setSaveError los llama el padre', async () => {
    const wrapper = mount('video', { title: 'X' }, { isNew: true })

    wrapper.vm.setSaveSuccess()
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.save-button').classes()).toContain('save-button--success')

    wrapper.vm.setSaveError()
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.save-button').classes()).toContain('save-button--error')
  })

  it('los cinco medios exponen los cuatro métodos', () => {
    mediaKeys.forEach((media) => {
      const wrapper = mount(media, { title: 'X', name: 'X', id: 1 })
      expect(typeof wrapper.vm.setSaveSuccess).toBe('function')
      expect(typeof wrapper.vm.setSaveError).toBe('function')
      expect(typeof wrapper.vm.setEditSuccess).toBe('function')
      expect(typeof wrapper.vm.setEditError).toBe('function')
    })
  })
})

describe('LibraryMediaItem — acciones propias de un medio', () => {
  it('solo el libro trae el botón de historial, y solo si ya está en la biblioteca', () => {
    expect(mount('book', { title: 'X' }).find('.history-button').exists()).toBe(true)
    expect(mount('book', { title: 'X' }, { isNew: true }).find('.history-button').exists()).toBe(false)
    expect(mount('movie', { title: 'X', isbn: 't1' }).find('.history-button').exists()).toBe(false)
  })

  it('el historial emite `show-history` con el ítem', async () => {
    const wrapper = mount('book', { title: 'X', isbn: '1' })
    await wrapper.find('.history-button').trigger('click')

    expect(wrapper.emitted('show-history')[0][0].isbn).toBe('1')
  })

  it('sin `canDelete` no hay botón de borrar, y estando nuevo tampoco', () => {
    expect(mount('album', { name: 'A' }, { canDelete: false }).find('.delete-button').exists()).toBe(false)
    expect(mount('album', { name: 'A' }, { isNew: true }).find('.delete-button').exists()).toBe(false)
  })
})

describe('LibraryMediaItem — resincronización con el ítem', () => {
  it('enriquecer el ítem NO borra los estados que el usuario acaba de elegir', async () => {
    // Las fichas de detalle reemplazan el objeto al traer datos de la API en
    // segundo plano (MovieDetailView.vue:522-530). Con un watch profundo, eso
    // pisaba la selección del usuario.
    const wrapper = mount('movie', { title: 'Alien', isbn: 'tt1', imdbID: 'tt1' }, { isNew: true })
    wrapper.vm.$.setupState.selectedUserStatuses = ['wishlist']

    await wrapper.setProps({ item: { title: 'Alien', isbn: 'tt1', imdbID: 'tt1', plot: 'Enriquecido' } })

    expect(wrapper.vm.$.setupState.selectedUserStatuses).toEqual(['wishlist'])
  })

  it('cambiar de ítem sí recalcula los estados', async () => {
    const wrapper = mount('movie', { title: 'Alien', isbn: 'tt1', imdbID: 'tt1' }, { isNew: true })
    wrapper.vm.$.setupState.selectedUserStatuses = ['wishlist']

    await wrapper.setProps({ item: { title: 'Otra', isbn: 'tt2', imdbID: 'tt2' } })

    expect(wrapper.vm.$.setupState.selectedUserStatuses).toEqual(['owned'])
  })

  it('la valoración sí se sincroniza en cuanto cambia', async () => {
    const wrapper = mount('game', { name: 'X', id: 1, user_rating: 2 })
    await wrapper.setProps({ item: { name: 'X', id: 1, user_rating: 5 } })

    expect(wrapper.vm.$.setupState.rating).toBe(5)
  })
})

describe('LibraryMediaItem — portada servida por el backend', () => {
  it('apunta al endpoint local, no al CDN del proveedor', () => {
    const wrapper = mount('movie', {
      imdbID: 'tt0068646', title: 'El Padrino', coverUrl: 'https://image.tmdb.org/t/p/w500/a.jpg'
    })

    expect(wrapper.find('img.cover-image').attributes('src'))
      .toBe('http://127.0.0.1:8888/index.php?cover=movie/tt0068646')
  })

  it('cae a la URL remota cuando la imagen local no carga', async () => {
    const wrapper = mount('movie', {
      imdbID: 'tt0068646', title: 'El Padrino', coverUrl: 'https://image.tmdb.org/t/p/w500/a.jpg'
    })

    // Un 404 del endpoint = el ítem no tiene fila en cover_file (guardado antes
    // de que esto existiera y sin sembrar). No puede verse un icono roto.
    await wrapper.find('img.cover-image').trigger('error')

    expect(wrapper.find('img.cover-image').attributes('src'))
      .toBe('https://image.tmdb.org/t/p/w500/a.jpg')
  })

  it('usa la URL remota directamente si el ítem no tiene clave', () => {
    const wrapper = mount('movie', { title: 'Sin id', coverUrl: 'https://cdn.test/a.jpg' })

    expect(wrapper.find('img.cover-image').attributes('src')).toBe('https://cdn.test/a.jpg')
  })

  it('sin portada de ningún tipo no pinta img', () => {
    const wrapper = mount('movie', { imdbID: 'tt1', title: 'Sin portada' })

    expect(wrapper.find('img.cover-image').exists()).toBe(false)
  })
})
