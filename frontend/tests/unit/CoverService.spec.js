import { describe, expect, it } from 'vitest'
import CoverService from '@/services/CoverService'

/**
 * El servicio solo compone una cadena, pero esa cadena tiene que casar con lo
 * que el backend espera en `?cover=<media_type>/<clave>`. Si deja de casar, la
 * portada no se rompe —el `@error` la recupera del CDN— pero deja de servirse
 * local sin que nada avise, que es lo peor de detectar.
 */
describe('CoverService — URL de portada local', () => {
  it('compone la URL contra el endpoint del backend', () => {
    expect(CoverService.localCoverUrl('movie', 'tt0068646'))
      .toBe('http://127.0.0.1:8888/index.php?cover=movie/tt0068646')
  })

  it('acepta una clave numérica, como la de juegos y álbumes', () => {
    expect(CoverService.localCoverUrl('game', 305017))
      .toBe('http://127.0.0.1:8888/index.php?cover=game/305017')
  })

  it('escapa la clave: un ISBN o un id de YouTube pueden traer caracteres de URL', () => {
    expect(CoverService.localCoverUrl('video', 'A9m/vu+Aw'))
      .toContain('cover=video/A9m%2Fvu%2BAw')
  })

  it('devuelve null sin clave, para que quien llama caiga a la URL remota', () => {
    expect(CoverService.localCoverUrl('movie', null)).toBeNull()
    expect(CoverService.localCoverUrl('movie', undefined)).toBeNull()
    expect(CoverService.localCoverUrl('movie', '')).toBeNull()
    expect(CoverService.localCoverUrl('', 'tt0068646')).toBeNull()
  })
})
