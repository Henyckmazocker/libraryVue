import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import AlbumCarouselItem from '@/components/Albums/AlbumCarouselItem.vue'
import MovieCarouselItem from '@/components/Movies/MovieCarouselItem.vue'
import { mountComponent } from './helpers/mount'

/**
 * Las portadas de los carruseles de búsqueda, que es donde vive la caché del
 * catálogo.
 *
 * Lo que se fija aquí es el escalón de degradación, que es lo único que hace
 * que apuntar al backend nunca sea peor que apuntar al CDN: copia local →
 * URL remota → placeholder. Sin él, un 404 del endpoint dejaría el hueco vacío.
 */

const CDN_CAA = 'https://coverartarchive.org/release-group/abc/front-500'
const CDN_TMDB = 'https://image.tmdb.org/t/p/w500/x.jpg'

const montarAlbum = (album) => mountComponent(AlbumCarouselItem, { props: { album } })
const montarPelicula = (movie) => mountComponent(MovieCarouselItem, { props: { movie } })

describe('AlbumCarouselItem — la carátula sale del backend', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('pide la carátula al backend con el MBID, no a Cover Art Archive', () => {
    const w = montarAlbum({
      id: '1f25d940-89e2-4813-a86f-955b0e99c391',
      name: 'Prequelle',
      cover_url: CDN_CAA,
    })

    expect(w.find('img.album-cover').attributes('src'))
      .toMatch(/[?&]cover=album\/1f25d940-89e2-4813-a86f-955b0e99c391$/)
  })

  it('si el backend falla, cae a la URL del CDN', async () => {
    const w = montarAlbum({ id: 'abc', name: 'Algo', cover_url: CDN_CAA })

    await w.find('img.album-cover').trigger('error')

    expect(w.find('img.album-cover').attributes('src')).toBe(CDN_CAA)
    expect(w.find('.album-cover-placeholder').exists()).toBe(false)
  })

  it('si también falla el CDN, se pinta el placeholder', async () => {
    const w = montarAlbum({ id: 'abc', name: 'Algo', cover_url: CDN_CAA })

    await w.find('img.album-cover').trigger('error')
    await w.find('img.album-cover').trigger('error')

    expect(w.find('img.album-cover').exists()).toBe(false)
    expect(w.find('.album-cover-placeholder').exists()).toBe(true)
  })

  it('sin id no se gasta un escalón: el primer fallo ya pinta el placeholder', () => {
    // Es lo que protege la guarda `coverUrl !== remoteUrl` del handler. Sin id
    // no hay URL local que pedir, así que local y remota son la MISMA: tratar
    // el primer error como «falló la copia local» gastaría un reintento contra
    // la URL que acaba de fallar.
    const w = montarAlbum({ name: 'Sin id', cover_url: CDN_CAA })

    expect(w.find('img.album-cover').attributes('src')).toBe(CDN_CAA)

    return w.find('img.album-cover').trigger('error').then(() => {
      expect(w.find('img.album-cover').exists()).toBe(false)
      expect(w.find('.album-cover-placeholder').exists()).toBe(true)
    })
  })

  it('un álbum sin carátula sigue enseñando el placeholder, sin pedir nada', () => {
    // `has_cover_art = 0` en el mirror: no hay imagen que pedir y hacerlo sería
    // un 404 garantizado.
    const w = montarAlbum({ id: 'abc', name: 'Algo', cover_url: null })

    expect(w.find('img.album-cover').exists()).toBe(false)
    expect(w.find('.album-cover-placeholder').exists()).toBe(true)
  })
})

describe('MovieCarouselItem — el póster que la búsqueda no trae', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('pide el póster al backend aunque la búsqueda mande Poster: null', () => {
    // Éste es el caso normal y el motivo del hito: los dumps de IMDb no traen
    // pósters, así que `coverUrl` llega null y antes se veía el icono de relleno.
    const w = montarPelicula({ imdbID: 'tt0111503', title: 'Mentiras arriesgadas', coverUrl: null })

    expect(w.find('img.movie-poster').attributes('src'))
      .toMatch(/[?&]cover=movie\/tt0111503$/)
  })

  it('sin URL remota a la que caer, el fallo lleva directo al placeholder', async () => {
    const w = montarPelicula({ imdbID: 'tt0111503', title: 'Mentiras arriesgadas', coverUrl: null })

    await w.find('img.movie-poster').trigger('error')

    expect(w.find('img.movie-poster').exists()).toBe(false)
    expect(w.find('.movie-poster-placeholder').exists()).toBe(true)
  })

  it('cuando sí hay URL remota, el primer fallo cae a ella', async () => {
    const w = montarPelicula({ imdbID: 'tt0111503', title: 'X', coverUrl: CDN_TMDB })

    await w.find('img.movie-poster').trigger('error')

    expect(w.find('img.movie-poster').attributes('src')).toBe(CDN_TMDB)
  })

  it('sin imdbID no se inventa una clave: se usa lo que haya', () => {
    const w = montarPelicula({ title: 'Sin id', coverUrl: CDN_TMDB })

    expect(w.find('img.movie-poster').attributes('src')).toBe(CDN_TMDB)
  })
})
