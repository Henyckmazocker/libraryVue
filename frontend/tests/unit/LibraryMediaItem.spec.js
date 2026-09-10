import { describe, expect, it } from 'vitest'
import LibraryMediaItem from '@/components/shared/LibraryMediaItem.vue'
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import { mountComponent } from './helpers/mount'

const mount = (media, item, options = {}) => mountComponent(LibraryMediaItem, {
  props: {
    media,
    item,
    allowedStatuses: options.allowedStatuses ?? ['owned', 'wishlist'],
    isNew: options.isNew ?? false
  }
})

const texto = (wrapper) => wrapper.text().replace(/\s+/g, ' ')

/**
 * Los campos declarados por medio dejaron de pintarse en el panel el 2026-09-02: la
 * cabecera de la ficha ya los enseña y repetirlos era el defecto que arregló el
 * rediseño. Pero `libraryItem.fields` **se conserva en el registry** —el `<details>`
 * de datos técnicos los necesita— y esta era su única cobertura, así que los tests
 * se conservan también, ejercitando el resolutor en vez del componente.
 *
 * Es el mismo `resolve` que tenía `LibraryMediaItem`: un campo se pinta si tiene
 * valor, salvo los marcados `always`.
 */
const campos = (media, item) => getMediaConfig(media).libraryItem.fields
  .map((def) => ({ ...def, text: def.value(item) }))
  .filter((def) => def.always || (def.text !== null && def.text !== undefined && def.text !== '' && def.text !== 0))

const rotulados = (media, item) => campos(media, item)
  .map((c) => `${c.label}: ${c.text}`)
  .join(' | ')

describe('LibraryMediaItem — clases por medio', () => {
  it.each(mediaKeys)('%s pinta las clases que espera el mixin library-item', (media) => {
    const wrapper = mount(media, { title: 'X', isbn: '1', id: 1 })

    expect(wrapper.classes()).toContain(`library-${media}-item-container`)
    expect(wrapper.find(`.${media}-details`).exists()).toBe(true)

    // El panel ya NO repite el catálogo: ni portada, ni título, ni los campos.
    // Los pinta la cabecera de la ficha, dos dedos más arriba.
    expect(wrapper.find(`.${media}-title`).exists()).toBe(false)
    expect(wrapper.find('.cover-image-container').exists()).toBe(false)

    // Y desde el 2026-09-02 tampoco pinta los botones de la barra: guardar,
    // editar y eliminar viven en `MediaDetailView`, que es quien tiene el store.
    expect(wrapper.find('.btn--primary').exists()).toBe(false)
    expect(wrapper.find('.btn--danger').exists()).toBe(false)
  })
})

// Los rótulos pasaron del inglés al catálogo el 2026-08-31: `mediaRegistry.js`
// rotulaba en inglés en libros, películas y juegos y en español en álbumes y
// vídeos, y estaba escrito que era a propósito. Ya no.
describe('LibraryMediaItem — campos declarados en el registry', () => {
  it('el libro pinta autor, editorial y fecha con sus rótulos', () => {
    const r = rotulados('book', {
      title: 'Dune', author: 'Frank Herbert', publisher: 'Ace', publicationDate: '1965'
    })

    expect(r).toContain('Autor: Frank Herbert')
    expect(r).toContain('Editorial: Ace')
    expect(r).toContain('Fecha de publicación: 1965')
  })

  it('el libro prefiere la lista `publishers` a `publisher`', () => {
    expect(rotulados('book', { title: 'X', publishers: ['Ace', 'Gollancz'], publisher: 'Otra' }))
      .toContain('Editorial: Ace, Gollancz')
  })

  it('la película oculta el título original si coincide con el título', () => {
    expect(rotulados('movie', { title: 'Alien', originalTitle: 'Alien', isbn: 'tt1' }))
      .not.toContain('Título original')
    expect(rotulados('movie', { title: 'Alien', originalTitle: 'Xenomorph', isbn: 'tt1' }))
      .toContain('Título original: Xenomorph')
  })

  it('la película siempre pinta el IMDb ID, aunque el resto falte', () => {
    expect(rotulados('movie', { title: 'Alien', isbn: 'tt0078748' })).toContain('IMDb ID: tt0078748')
  })

  it('el juego une desarrolladores, plataformas y géneros vengan como vengan', () => {
    const r = rotulados('game', {
      name: 'Hollow Knight',
      developers: [{ name: 'Team Cherry' }],
      platforms: [{ platform: { name: 'PC' } }, 'Switch'],
      genres: ['Metroidvania', { name: 'Acción' }],
      id: 7
    })

    expect(r).toContain('Desarrollador: Team Cherry')
    expect(r).toContain('Plataformas: PC, Switch')
    expect(r).toContain('Géneros: Metroidvania, Acción')
    expect(r).toContain('RAWG ID: 7')
  })

  it('el juego colorea el Metacritic por tramos', () => {
    const clase = (metacritic) => campos('game', { name: 'A', id: 1, metacritic })
      .find((c) => c.valueClass)?.valueClass({ metacritic })

    expect(clase(90)).toBe('score-high')
    expect(clase(60)).toBe('score-medium')
    expect(clase(20)).toBe('score-low')
  })

  it('el álbum formatea la duración en horas o en minutos:segundos', () => {
    expect(rotulados('album', { name: 'A', duration_ms: 4_500_000 })).toContain('Duración: 1h 15m')
    expect(rotulados('album', { name: 'A', duration_ms: 2_587_000 })).toContain('Duración: 43:07')
  })

  it('el vídeo pinta canal, duración y su id de YouTube', () => {
    const r = rotulados('video', {
      title: 'Charla', channel_name: 'Canal X', duration: '12:04', youtube_id: 'abc'
    })

    expect(r).toContain('Canal: Canal X')
    expect(r).toContain('Duración: 12:04')
    expect(r).toContain('YouTube ID: abc')
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

/**
 * El botón de guardar subió a la barra de la ficha el 2026-09-02, pero el payload se
 * sigue armando aquí —lleva los estados y la valoración de este panel—, así que el
 * disparo es ahora el método expuesto que el CTA llama.
 */
describe('LibraryMediaItem — payloads de los eventos', () => {
  const guardar = (wrapper) => wrapper.vm.guardar()

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

  // `deletePayload` ya no lo emite este panel: lo llama `MediaDetailView` al armar
  // la entrada «Eliminar» del menú `⋯`. Se conservan los dos casos porque la forma
  // del payload es del registry y sigue importando; que el menú la use de verdad lo
  // fija `MediaDetailView.spec.js`.
  it('el borrado de película manda `imdbID` con el valor de `isbn`', () => {
    const payload = getMediaConfig('movie').libraryItem
      .deletePayload({ title: 'X', isbn: 'tt1', imdbID: 'OTRO' })

    expect(payload).toEqual({ isbn: 'tt1', imdbID: 'tt1', itemType: 'movie' })
  })

  it.each([
    ['album', { name: 'X', id: 9, spotify_id: 'sp' }, 9],
    ['video', { title: 'X', youtube_id: 'abc' }, 'abc']
  ])('%s borra con un identificador escalar, no con un objeto', (media, item, esperado) => {
    expect(getMediaConfig(media).libraryItem.deletePayload(item)).toBe(esperado)
  })

  it('el juego añade sus campos propios al guardar y al editar', async () => {
    const wrapper = mount('game', { name: 'X', id: 1, hours_played: 12 }, { isNew: true })
    await guardar(wrapper)

    expect(wrapper.emitted('save')[0][0].game.hoursPlayed).toBe(12)
  })
})

// El bloque «el feedback lo confirma el padre» se fue con los botones: el acuse en
// verde o en rojo lo pinta ahora el CTA de la barra, y lo prueba
// `MediaDetailView.spec.js` → «el CTA de la barra».

describe('LibraryMediaItem — acciones propias de un medio', () => {
  it('solo el libro trae el botón de historial, y solo si ya está en la biblioteca', () => {
    expect(mount('book', { title: 'X' }).find('.btn--secondary').exists()).toBe(true)
    expect(mount('book', { title: 'X' }, { isNew: true }).find('.btn--secondary').exists()).toBe(false)
    expect(mount('movie', { title: 'X', isbn: 't1' }).find('.btn--secondary').exists()).toBe(false)
  })

  it('el historial emite `show-history` con el ítem', async () => {
    const wrapper = mount('book', { title: 'X', isbn: '1' })
    await wrapper.find('.btn--secondary').trigger('click')

    expect(wrapper.emitted('show-history')[0][0].isbn).toBe('1')
  })

  // `when` es el segundo filtro, y mira el ÍTEM en vez del estado en la biblioteca:
  // un libro sin `work_key` no tiene ediciones que ofrecer aunque esté guardado.
  it('las ediciones solo se ofrecen si el libro tiene `work_key`', () => {
    const con = mount('book', { title: 'X', isbn: '1', work_key: '/works/OL1W' })
    const sin = mount('book', { title: 'X', isbn: '1' })

    const rotulos = (w) => w.findAll('.btn--secondary').map((b) => b.text())
    expect(rotulos(con)).toContain('Ver ediciones')
    expect(rotulos(sin)).not.toContain('Ver ediciones')
  })

  it('elegir ediciones emite `show-editions` con el ítem', async () => {
    const wrapper = mount('book', { title: 'X', isbn: '1', work_key: '/works/OL1W' })
    const boton = wrapper.findAll('.btn--secondary').find((b) => b.text().includes('Ver ediciones'))
    await boton.trigger('click')

    expect(wrapper.emitted('show-editions')[0][0].work_key).toBe('/works/OL1W')
  })

  it('la serie ofrece las temporadas, y la película NO se las hereda', () => {
    // `series.libraryItem` sale de `movie.libraryItem` por prototipo. Con la
    // referencia compartida que había antes, este `extraActions` habría aparecido
    // también en la ficha de películas, que no tienen temporadas.
    const serie = mount('series', { title: 'GoT', isbn: 'tt9', totalSeasons: 8 })
    const peli = mount('movie', { title: 'Matrix', isbn: 'tt1', totalSeasons: 8 })

    expect(serie.findAll('.btn--secondary').map((b) => b.text())).toContain('Temporadas')
    expect(peli.findAll('.btn--secondary')).toHaveLength(0)
  })

  it('sin `totalSeasons` la serie tampoco ofrece temporadas', () => {
    expect(mount('series', { title: 'X', isbn: 'tt9' }).findAll('.btn--secondary')).toHaveLength(0)
  })

  it('y estando sin guardar tampoco: el seguimiento necesita el ítem en la biblioteca', () => {
    const w = mount('series', { title: 'X', isbn: 'tt9', totalSeasons: 8 }, { isNew: true })
    expect(w.findAll('.btn--secondary')).toHaveLength(0)
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

// El bloque «portada servida por el backend» vivía aquí y se retiró el 2026-09-02:
// el panel dejó de pintar la portada —la cabecera de la ficha ya la enseña— así que
// sus cuatro tests se quedaron sin sujeto. **La cobertura no se pierde**: la misma
// cadena local → remota → placeholder la prueban `MediaDetailView.spec.js:251-307`
// con cinco casos, incluido el de la serie que pide la clave de película, y
// `CoverService.spec.js` la composición de la URL.

describe('LibraryMediaItem — revertir la selección de estados', () => {
  /**
   * El watch que resincroniza el desplegable mira solo `idOf(props.item)`, y es a
   * propósito: uno profundo borraría la selección del usuario cada vez que la ficha
   * reemplaza el objeto al enriquecerlo en segundo plano. El precio era que un
   * guardado que NO llegó a ocurrir —cancelar la confirmación de sesión— dejaba el
   * chip pintado hasta recargar, con la base de datos diciendo otra cosa. De ahí
   * `revertirEstados`, que `MediaDetailView` llama en sus dos ramas de vuelta atrás.
   */
  const libro = { isbn: '9788427200203', title: 'Los Juegos del Hambre', userStatuses: ['owned', 'reading'] }

  it('cambiar el `item` sin cambiar su id NO resincroniza el desplegable', async () => {
    const wrapper = mount('book', libro, { allowedStatuses: ['owned', 'reading', 'read'] })

    await wrapper.setProps({ item: { ...libro, userStatuses: ['owned'] } })

    // Sigue mostrando lo que había: es la conducta que protege la selección del
    // usuario durante el enriquecimiento, y la razón de que haga falta el método.
    expect(wrapper.vm.selectedUserStatuses ?? []).toEqual(['owned', 'reading'])
  })

  it('`revertirEstados` devuelve el desplegable a lo que se le pase', () => {
    const wrapper = mount('book', libro, { allowedStatuses: ['owned', 'reading', 'read'] })

    wrapper.vm.revertirEstados(['owned', 'read'])
    expect(wrapper.vm.selectedUserStatuses).toEqual(['owned', 'read'])

    // Copia, no la misma referencia: quien llama conserva su array.
    const previos = ['owned', 'reading']
    wrapper.vm.revertirEstados(previos)
    expect(wrapper.vm.selectedUserStatuses).toEqual(previos)
    expect(wrapper.vm.selectedUserStatuses).not.toBe(previos)
  })

  it('sin argumento vuelve a los estados del `item`', () => {
    const wrapper = mount('book', libro, { allowedStatuses: ['owned', 'reading', 'read'] })

    wrapper.vm.revertirEstados(['owned', 'read'])
    wrapper.vm.revertirEstados()
    expect(wrapper.vm.selectedUserStatuses).toEqual(['owned', 'reading'])
  })
})
