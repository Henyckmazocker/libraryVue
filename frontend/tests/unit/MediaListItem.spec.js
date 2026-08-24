import { describe, expect, it } from 'vitest'
import MediaListItem from '@/components/shared/MediaListItem.vue'
import { mediaRegistry, storeMediaKeys } from '@/config/mediaRegistry'
import { mountComponent } from './helpers/mount'

const mount = (media, item, allowedStatuses = []) =>
  mountComponent(MediaListItem, { props: { media, item, allowedStatuses } })

describe('MediaListItem — título y subtítulo por medio', () => {
  const casos = [
    ['book', { title: 'Dune', author: 'Frank Herbert', publicationDate: '1965-08-01' },
      'Dune', 'Frank Herbert • 1965'],
    ['movie', { title: 'Alien', director: 'Ridley Scott', year: 1979 },
      'Alien', 'Ridley Scott • 1979'],
    ['game', { name: 'Hollow Knight', developer: 'Team Cherry', releaseDate: '2017-02-24' },
      'Hollow Knight', 'Team Cherry • 2017'],
    ['album', { name: 'Kid A', artist: 'Radiohead', release_date: '2000-10-02' },
      'Kid A', 'Radiohead • 2000'],
    ['video', { title: 'Charla', channel_name: 'Canal X', duration: '12:04' },
      'Charla', 'Canal X • 12:04']
  ]

  it.each(casos)('%s pinta "%s" con subtítulo "%s"', (media, item, title, subtitle) => {
    const wrapper = mount(media, item)

    expect(wrapper.find('.list-item__title').text()).toBe(title)
    expect(wrapper.find('.list-item__subtitle').text()).toBe(subtitle)
    expect(wrapper.classes()).toContain('list-item--' + media)
  })

  it('cae a los textos por defecto cuando falta el autor o el año', () => {
    expect(mount('book', { title: 'Sin datos' }).find('.list-item__subtitle').text())
      .toBe('Autor desconocido')
    expect(mount('album', { name: 'Sin datos' }).find('.list-item__subtitle').text())
      .toBe('Artista desconocido')
    expect(mount('game', { name: 'Sin datos' }).find('.list-item__subtitle').text())
      .toBe('Desarrollador desconocido')
  })
})

describe('MediaListItem — portada', () => {
  it('usa el campo de portada de cada medio', () => {
    // Álbumes y vídeos traen snake_case; el resto, camelCase.
    expect(mount('album', { name: 'A', cover_url: '/a.jpg' }).find('img').attributes('src')).toBe('/a.jpg')
    expect(mount('book', { title: 'B', coverUrl: '/b.jpg' }).find('img').attributes('src')).toBe('/b.jpg')
  })

  it('cae al placeholder si la imagen falla', async () => {
    const wrapper = mount('album', { name: 'A', cover_url: '/roto.jpg' })
    expect(wrapper.find('.list-item__cover-placeholder').exists()).toBe(false)

    await wrapper.find('img').trigger('error')

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('.list-item__cover-placeholder i').classes()).toContain('fa-music')
  })

  it('cae al placeholder si no hay portada', () => {
    const wrapper = mount('game', { name: 'G' })

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('.list-item__cover-placeholder i').classes()).toContain('fa-gamepad')
  })
})

describe('MediaListItem — lo propio de cada medio', () => {
  it('solo las películas traen badge, y distingue serie de película', () => {
    const pelicula = mount('movie', { title: 'Alien', media_type: 'movie' })
    expect(pelicula.find('.list-item__type-badge').text()).toContain('Película')
    expect(pelicula.find('.list-item__type-badge').classes()).toContain('is-movie')
    expect(pelicula.find('.list-item__cover-placeholder i').classes()).toContain('fa-film')

    const serie = mount('movie', { title: 'Alien', media_type: 'series' })
    expect(serie.find('.list-item__type-badge').text()).toContain('Serie')
    expect(serie.find('.list-item__type-badge').classes()).toContain('is-series')
    expect(serie.find('.list-item__cover-placeholder i').classes()).toContain('fa-tv')

    expect(mount('album', { name: 'A' }).find('.list-item__type-badge').exists()).toBe(false)
  })

  it('solo los juegos traen la línea de plataformas, con su icono de marca', () => {
    const wrapper = mount('game', { name: 'G', platforms: 'PlayStation 5, PC' })

    expect(wrapper.find('.list-item__extra span').text()).toBe('PlayStation 5, PC')
    expect(wrapper.find('.list-item__extra i').classes()).toContain('fa-playstation')
    expect(mount('album', { name: 'A' }).find('.list-item__extra').exists()).toBe(false)
  })

  it('recorta las plataformas a dos', () => {
    const wrapper = mount('game', { name: 'G', platforms: ['PC', 'Xbox', 'Switch'] })

    expect(wrapper.find('.list-item__extra span').text()).toBe('PC, Xbox...')
  })
})

describe('MediaListItem — rating, estados y click', () => {
  it('muestra el rating solo si es mayor que cero', () => {
    expect(mount('album', { name: 'A', user_rating: 4 }).find('.list-item__rating').exists()).toBe(true)
    expect(mount('album', { name: 'A', user_rating: 0 }).find('.list-item__rating').exists()).toBe(false)
    expect(mount('album', { name: 'A' }).find('.list-item__rating').exists()).toBe(false)
  })

  it('resuelve la etiqueta del estado por key, name o id', () => {
    const item = { name: 'A', userStatuses: ['listening'] }

    expect(mount('album', item, [{ key: 'listening', name: 'Escuchando' }])
      .find('.list-item__status-badge').text()).toBe('Escuchando')
    expect(mount('album', item, [{ name: 'listening', label: 'Escuchando' }])
      .find('.list-item__status-badge').text()).toBe('Escuchando')
    expect(mount('album', item, [{ id: 'listening', label: 'Escuchando' }])
      .find('.list-item__status-badge').text()).toBe('Escuchando')
  })

  it('deja pasar el estado tal cual si no está en la lista permitida', () => {
    const wrapper = mount('album', { name: 'A', userStatuses: ['desconocido'] }, [])

    expect(wrapper.find('.list-item__status-badge').text()).toBe('desconocido')
  })

  it('emite click con el ítem', async () => {
    const item = { name: 'A' }
    const wrapper = mount('album', item)

    await wrapper.trigger('click')

    expect(wrapper.emitted('click')).toEqual([[item]])
  })
})

describe('MediaListItem — el registry cubre los cinco medios', () => {
  it('cada entrada declara el bloque list completo', () => {
    // `series` es entrada del registry pero **no aparece en listados**: no
    // tiene store ni bloque `list`, comparte los de películas. Por eso se
    // recorre `storeMediaKeys` y no todo el registry.
    for (const media of storeMediaKeys) {
      const config = mediaRegistry[media]
      expect(config.list, media).toBeDefined()
      expect(typeof config.list.iconOf, media).toBe('function')
      expect(typeof config.list.coverOf, media).toBe('function')
      expect(typeof config.list.titleOf, media).toBe('function')
      expect(typeof config.list.subtitleOf, media).toBe('function')
      expect(config.list.aspect, media).toBeTruthy()
      expect(config.list.width, media).toBeTruthy()
    }
  })
})
