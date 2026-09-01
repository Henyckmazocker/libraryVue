import { describe, expect, it } from 'vitest'
import { getMediaConfig, mediaKeys, transformIgdbGame } from '@/config/mediaRegistry'
import { formatDate } from '@/utils/dates'

/**
 * Las barreras de los defectos que se arreglaron el 2026-09-01.
 *
 * Los tres tenían en común que **nada los detectaba**: no rompían el lint, no
 * rompían ningún test y no dejaban rastro en la consola. Se veían mirando la
 * pantalla, y solo si sabías qué mirar.
 */

describe('ningún medio repite etiqueta en la misma ficha', () => {
  // La ficha de película pintaba «Director» dos veces: `libraryItem.fields`
  // declaraba `i.director` e `i.author`, que son el mismo dato con dos entradas.
  // Nadie lo veía porque las dos filas eran correctas por separado.
  it.each(mediaKeys)('%s no declara dos campos con el mismo rótulo', (media) => {
    const campos = getMediaConfig(media).libraryItem?.fields ?? []
    const rotulos = campos.map((c) => c.label)

    expect(rotulos).toEqual([...new Set(rotulos)])
  })
})

describe('formatDate', () => {
  it('convierte un ISO con hora y zona en fecha legible', () => {
    // La ficha de vídeo enseñaba «2026-08-10T20:54:12Z» tal cual.
    expect(formatDate('2026-08-10T20:54:12Z')).toMatch(/2026/)
    expect(formatDate('2026-08-10T20:54:12Z')).not.toContain('T')
    expect(formatDate('2026-08-10T20:54:12Z')).not.toContain('Z')
  })

  it('devuelve cadena vacía sin fecha, y no «Invalid Date»', () => {
    expect(formatDate(null)).toBe('')
    expect(formatDate(undefined)).toBe('')
    expect(formatDate('')).toBe('')
    expect(formatDate('no es una fecha')).toBe('')
  })
})

describe('clasificación por edad de IGDB', () => {
  const juego = (ageRatings) => transformIgdbGame({ id: 1, name: 'X', age_ratings: ageRatings })

  const PEGI = { organization: { id: 2, name: 'PEGI' }, rating_category: { id: 9, rating: '16' } }
  const ESRB = { organization: { id: 1, name: 'ESRB' }, rating_category: { id: 11, rating: 'M' } }
  const CERO = { organization: { id: 3, name: 'CERO' }, rating_category: { id: 14, rating: 'B' } }

  it('prefiere PEGI cuando está', () => {
    expect(juego([ESRB, CERO, PEGI]).esrbRating).toBe('PEGI 16')
  })

  it('cae a ESRB si no hay PEGI', () => {
    expect(juego([CERO, ESRB]).esrbRating).toBe('ESRB M')
  })

  it('no inventa nada con organizaciones que no se usan', () => {
    expect(juego([CERO]).esrbRating).toBe('')
  })

  it('sobrevive a un juego sin clasificar', () => {
    expect(juego(null).esrbRating).toBe('')
    expect(juego([]).esrbRating).toBe('')
  })

  it('lleva el organismo delante, porque el valor de PEGI es un número desnudo', () => {
    // «16» a secas no significa nada; «PEGI 16» sí.
    expect(juego([PEGI]).esrbRating).not.toBe('16')
  })

  it('ignora la forma retirada de la API', () => {
    // Antes se filtraba por `r.category === 1`. IGDB retiró `category` y, como
    // ignora en silencio lo que no conoce, el campo llegaba ausente: el `find`
    // no encontraba nada y el badge no se pintaba nunca.
    expect(juego([{ category: 1, rating: 'M' }]).esrbRating).toBe('')
  })
})

describe('nombre de un enlace externo de IGDB', () => {
  // No se prueba `websiteName` directamente porque vive dentro del SFC; lo que se
  // fija aquí es que la transformación no descarta el tipo, que es de donde sale
  // el nombre. La prueba del rótulo en sí está en `DetailViews.spec.js`.
  it('conserva el tipo del website, que es de donde sale el nombre', () => {
    const sitios = [{ url: 'https://store.steampowered.com/app/1', type: { id: 13, type: 'Steam' } }]
    expect(transformIgdbGame({ id: 1, name: 'X', websites: sitios }).websites).toEqual(sitios)
  })
})
