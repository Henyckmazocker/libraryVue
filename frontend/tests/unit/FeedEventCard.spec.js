import { describe, expect, it } from 'vitest'
import { RouterLinkStub } from '@vue/test-utils'
import FeedEventCard from '@/components/Social/FeedEventCard.vue'
import { mountComponent } from './helpers/mount'

/**
 * La tarjeta del feed.
 *
 * Tres cosas se fijan aquí. Los dos destinos —el ítem y la persona—, el «ver
 * más» de las notas, y una vieja que estaba rota: hasta el 2026-08-25 la
 * tarjeta leía `event.new_value`, **campo que no existe en `feed_events`**, así
 * que un evento de valoración decía literalmente «recibió una valoración de
 * undefined».
 *
 * El `RouterLinkStub` va aquí y no en `helpers/mount.js`: es el único spec que
 * monta enlaces, y el helper es de todos. Sin él la tarjeta se monta **y los
 * tests pasan igual**, pero cada montaje escupe `Failed to resolve component:
 * router-link` y el enlace no se renderiza — la misma clase de fallo que dejó
 * `v-tooltip` tres meses sin registrar.
 */

const CORTA = 'Una nota breve.'
const LARGA = 'Un párrafo bastante largo que no cabe en tres líneas. '.repeat(6)

const evento = (extra = {}) => ({
  entity_type: 'album',
  entity_id: 42,
  entity_title: 'Prequelle',
  entity_cover: null,
  created_at: new Date().toISOString(),
  user: { username: 'david' },
  ...extra
})

const montar = (e) => mountComponent(FeedEventCard, {
  props: { event: e },
  global: { stubs: { RouterLink: RouterLinkStub } }
})

/** Los `to` de todos los enlaces de la tarjeta, en orden de aparición. */
const destinos = (w) => w.findAllComponents(RouterLinkStub).map((l) => l.props('to'))

/**
 * El destino de una zona concreta. Va por `findComponent` y no por `find`
 * porque `find` devuelve el elemento del DOM, que no sabe nada del `to`.
 */
const destinoDe = (w, selector) => w.findComponent(selector).props('to')

describe('FeedEventCard — el texto de la nota', () => {
  it('pinta el texto de la nota que viaja en metadata', () => {
    const w = montar(evento({
      event_type: 'notes_updated',
      metadata: { note_text: CORTA, note_type: 'quote' }
    }))

    expect(w.find('.feed-event-card__note').text()).toBe(CORTA)
  })

  it('una nota corta no ofrece el botón de desplegar', () => {
    const w = montar(evento({ event_type: 'notes_updated', metadata: { note_text: CORTA } }))

    expect(w.find('.feed-event-card__note-more').exists()).toBe(false)
  })

  it('una nota larga sí lo ofrece, y despliega', async () => {
    const w = montar(evento({ event_type: 'notes_updated', metadata: { note_text: LARGA } }))
    const boton = w.find('.feed-event-card__note-more')

    expect(boton.exists()).toBe(true)
    expect(boton.text()).toBe('Ver más')
    expect(w.find('.feed-event-card__note').classes()).not.toContain('feed-event-card__note--open')

    await boton.trigger('click')

    expect(w.find('.feed-event-card__note-more').text()).toBe('Ver menos')
    expect(w.find('.feed-event-card__note').classes()).toContain('feed-event-card__note--open')
  })

  it('el texto completo está en el DOM aunque se vea recortado', () => {
    // El recorte es CSS, no JavaScript: es lo que permite que un lector de
    // pantalla lo lea entero. Si algún día alguien lo trunca en JS, esto cae.
    const w = montar(evento({ event_type: 'notes_updated', metadata: { note_text: LARGA } }))

    expect(w.find('.feed-event-card__note').text()).toBe(LARGA.trim())
  })

  it('un evento que no es de nota no pinta bloque de nota', () => {
    const w = montar(evento({ event_type: 'item_added' }))

    expect(w.find('.feed-event-card__note').exists()).toBe(false)
  })

  it('escapa el texto en vez de interpretarlo', () => {
    // Es un feed social y el texto lo escribe una persona: `{{ }}`, nunca
    // `v-html`.
    const w = montar(evento({
      event_type: 'notes_updated',
      metadata: { note_text: '<img src=x onerror=alert(1)>' }
    }))

    expect(w.find('.feed-event-card__note').html()).not.toContain('<img')
    expect(w.find('.feed-event-card__note').text()).toContain('<img src=x')
  })
})

describe('FeedEventCard — los eventos que decían «undefined»', () => {
  it('una valoración enseña el número, no undefined', () => {
    const w = montar(evento({ event_type: 'item_rated', metadata: { rating: 4.5 } }))

    expect(w.text()).toContain('recibió una valoración de 4.5')
    expect(w.text()).not.toContain('undefined')
  })

  it('un cambio de estado enseña el estado, no undefined', () => {
    const w = montar(evento({
      event_type: 'status_changed',
      metadata: { old_status: 'wishlist', new_status: 'owned' }
    }))

    expect(w.text()).toContain('cambió de estado a "owned"')
    expect(w.text()).not.toContain('undefined')
  })

  it('sin metadata no rompe: pone un guion', () => {
    // `metadata` es NULL en la columna para `item_added`, y un evento viejo
    // puede no traerla.
    const w = montar(evento({ event_type: 'item_rated' }))

    expect(w.text()).toContain('recibió una valoración de —')
    expect(w.text()).not.toContain('undefined')
  })
})

describe('FeedEventCard — los dos destinos', () => {
  it('la portada y el título llevan a la ficha del ítem', () => {
    const w = montar(evento({ event_type: 'item_added' }))

    const alItem = { name: 'AlbumDetail', params: { albumId: 42 } }

    expect(destinoDe(w, '.feed-event-card__cover')).toEqual(alItem)
    expect(destinoDe(w, '.feed-event-card__title')).toEqual(alItem)
  })

  it('el avatar y el nombre llevan al perfil', () => {
    const w = montar(evento({ event_type: 'item_added' }))

    const alPerfil = { name: 'PublicProfile', params: { username: 'david' } }

    expect(destinoDe(w, '.feed-event-card__avatar')).toEqual(alPerfil)
    expect(destinoDe(w, '.feed-event-card__user')).toEqual(alPerfil)
  })

  it('cada medio va a su propia ruta con su propio parámetro', () => {
    // El mapeo sale de `detailRouteFor`, que lo deriva del registry; aquí se
    // comprueba que la tarjeta le pasa el `entity_type` y el `entity_id` que
    // guarda `feed_events`, no otra cosa.
    const casos = [
      ['book', '9788401352836', { name: 'BookDetail', params: { isbn: '9788401352836' } }],
      ['movie', 'tt0098936', { name: 'MovieDetail', params: { imdbId: 'tt0098936' } }],
      ['game', 1942, { name: 'GameDetail', params: { gameId: 1942 } }],
      ['video', 'dQw4w9WgXcQ', { name: 'VideoDetail', params: { youtubeId: 'dQw4w9WgXcQ' } }]
    ]

    for (const [tipo, id, esperado] of casos) {
      const w = montar(evento({ event_type: 'item_added', entity_type: tipo, entity_id: id }))

      expect(destinoDe(w, '.feed-event-card__cover')).toEqual(esperado)
    }
  })

  it('los cuatro enlaces son hermanos: ninguno está dentro de otro', () => {
    // Un ancla dentro de otra es HTML inválido y el navegador la desanida por su
    // cuenta. Con `<component :is>` el lint no puede verlo, así que se mira aquí.
    const w = montar(evento({ event_type: 'item_added' }))

    expect(destinos(w)).toHaveLength(4)
    expect(w.findAllComponents(RouterLinkStub).every(
      (l) => l.element.querySelector('a') === null
    )).toBe(true)
  })
})

describe('FeedEventCard — lo que no se puede enlazar', () => {
  it('un evento sin entity_id se pinta sin enlace al ítem', () => {
    const w = montar(evento({ event_type: 'item_added', entity_id: null }))

    expect(w.find('.feed-event-card__cover').element.tagName).toBe('DIV')
    expect(w.find('.feed-event-card__title').element.tagName).toBe('SPAN')
    // El perfil sí sigue enlazado: son dos destinos independientes.
    expect(destinos(w)).toEqual([
      { name: 'PublicProfile', params: { username: 'david' } },
      { name: 'PublicProfile', params: { username: 'david' } }
    ])
  })

  it('un entity_type que el registry no conoce tampoco enlaza', () => {
    // `achievement` está en el ENUM de `event_type` y no lo emite nadie: el día
    // que se emita no traerá entidad, y la tarjeta no puede romperse por eso.
    const w = montar(evento({
      event_type: 'achievement', entity_type: null, entity_id: null, entity_title: 'Diez libros'
    }))

    expect(w.find('.feed-event-card__cover').element.tagName).toBe('DIV')
    expect(w.text()).toContain('Diez libros')
  })

  it('un usuario sin username se pinta sin enlace al perfil', () => {
    const w = montar(evento({ event_type: 'item_added', user: { name: 'Anónimo' } }))

    expect(w.find('.feed-event-card__avatar').element.tagName).toBe('DIV')
    expect(w.find('.feed-event-card__user').element.tagName).toBe('SPAN')
    expect(w.find('.feed-event-card__user').text()).toBe('Anónimo')
  })
})

describe('FeedEventCard — el «Ver más» no navega', () => {
  it('el botón de la nota está fuera de los dos enlaces', () => {
    // Si el botón cayera dentro de un enlace, desplegar la nota navegaría. El
    // marcado de hermanos lo evita sin necesidad de `@click.stop`; esto lo fija.
    const w = montar(evento({ event_type: 'notes_updated', metadata: { note_text: LARGA } }))

    const boton = w.find('.feed-event-card__note-more')

    expect(boton.exists()).toBe(true)
    expect(w.findAllComponents(RouterLinkStub).some(
      (l) => l.element.contains(boton.element)
    )).toBe(false)
  })

  it('desplegar la nota no cambia los destinos', async () => {
    const w = montar(evento({ event_type: 'notes_updated', metadata: { note_text: LARGA } }))
    const antes = destinos(w)

    await w.find('.feed-event-card__note-more').trigger('click')

    expect(destinos(w)).toEqual(antes)
    expect(w.find('.feed-event-card__note').classes()).toContain('feed-event-card__note--open')
  })
})

describe('FeedEventCard — una serie no es una película', () => {
  // `feed_events.entity_type` no tiene `'series'` y no puede tenerlo: en el
  // backend series y películas son la misma entidad. Lo que las separa es
  // `entity_media_type`, que llega por el `LEFT JOIN` a `movie.media_type`.
  const dePeliculas = (extra) => evento({
    event_type: 'item_added', entity_type: 'movie', entity_id: 'tt0098936',
    entity_title: 'Twin Peaks', ...extra
  })

  it('con entity_media_type series va a la ficha de serie', () => {
    const w = montar(dePeliculas({ entity_media_type: 'series' }))

    expect(destinoDe(w, '.feed-event-card__cover')).toEqual({
      name: 'SeriesDetail', params: { imdbId: 'tt0098936' } })
  })

  it('con entity_media_type movie va a la ficha de película', () => {
    const w = montar(dePeliculas({ entity_media_type: 'movie' }))

    expect(destinoDe(w, '.feed-event-card__cover')).toEqual({
      name: 'MovieDetail', params: { imdbId: 'tt0098936' } })
  })

  it('sin entity_media_type se lee como película', () => {
    // Es el `DEFAULT` de la columna, y es lo que llega para un evento anterior
    // al `LEFT JOIN` o para una película que el JOIN no encuentre.
    const w = montar(dePeliculas({}))

    expect(destinoDe(w, '.feed-event-card__cover')).toEqual({
      name: 'MovieDetail', params: { imdbId: 'tt0098936' } })
  })

  it('el entity_media_type de otro medio no lo desvía', () => {
    // La guarda mira `entity_type === 'movie'` además del media_type: un álbum
    // con basura en esa columna tiene que seguir yendo a su ficha.
    const w = montar(evento({ event_type: 'item_added', entity_media_type: 'series' }))

    expect(destinoDe(w, '.feed-event-card__cover')).toEqual({
      name: 'AlbumDetail', params: { albumId: 42 } })
  })
})

describe('FeedEventCard — la portada la sirve el backend', () => {
  // `entity_cover` es una URL de CDN congelada en el momento del evento. La
  // copia local va primero; la remota es el respaldo y el placeholder el último.
  const CDN = 'https://cdn.ajeno.test/prequelle.jpg'

  const conPortada = (extra = {}) => evento({ event_type: 'item_added', entity_cover: CDN, ...extra })

  const src = (w) => w.find('.feed-event-card__cover img').attributes('src')

  it('pide la portada al propio backend, no al CDN', () => {
    const w = montar(conPortada())

    expect(src(w)).toContain('cover=album/42')
    expect(src(w)).not.toContain('cdn.ajeno.test')
  })

  it('una serie pide su portada como movie', () => {
    // Se guarda con `AddMovieUseCase`, así que su fila de `cover_file` lleva
    // `media_type = 'movie'` aunque su ficha sea la de serie.
    const w = montar(conPortada({
      entity_type: 'movie', entity_id: 'tt0098936', entity_media_type: 'series'
    }))

    expect(src(w)).toContain('cover=movie/tt0098936')
    expect(destinoDe(w, '.feed-event-card__cover')).toEqual({
      name: 'SeriesDetail', params: { imdbId: 'tt0098936' } })
  })

  it('si la local falla cae a la remota, y si esa falla al placeholder', async () => {
    const w = montar(conPortada())

    await w.find('.feed-event-card__cover img').trigger('error')
    expect(src(w)).toBe(CDN)

    await w.find('.feed-event-card__cover img').trigger('error')
    expect(w.find('.feed-event-card__cover img').exists()).toBe(false)
    expect(w.find('.feed-event-card__cover-placeholder').exists()).toBe(true)
  })

  it('sin copia local que pedir, un solo error basta para el placeholder', async () => {
    // Sin `entity_id` no hay clave que pedirle al backend: lo que se pinta ya es
    // la remota, así que el primer error tiene que llevar al placeholder. Con un
    // único indicador de fallo haría falta un segundo error que nunca llega,
    // porque el `src` no cambia y el navegador no reintenta.
    const w = montar(conPortada({ entity_id: null }))

    expect(src(w)).toBe(CDN)

    await w.find('.feed-event-card__cover img').trigger('error')
    expect(w.find('.feed-event-card__cover-placeholder').exists()).toBe(true)
  })

  it('sin portada de ninguna clase, el placeholder desde el principio', () => {
    const w = montar(conPortada({ entity_id: null, entity_cover: null }))

    expect(w.find('.feed-event-card__cover img').exists()).toBe(false)
    expect(w.find('.feed-event-card__cover-placeholder').exists()).toBe(true)
  })

  it('al reutilizarse la tarjeta con otro evento, el fallo no se arrastra', async () => {
    // `FeedList` teclea por `event.id`, así que al paginar un componente se
    // reutiliza con otro evento.
    const w = montar(conPortada())

    await w.find('.feed-event-card__cover img').trigger('error')
    expect(src(w)).toBe(CDN)

    await w.setProps({ event: conPortada({ id: 99, entity_id: 77 }) })

    expect(src(w)).toContain('cover=album/77')
  })
})
