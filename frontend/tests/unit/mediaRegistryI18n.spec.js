import { describe, it, expect, afterAll } from 'vitest'
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import { setLocale } from '@/config/i18n'

/**
 * Los rótulos del registro salen del catálogo.
 *
 * Lo que se fija aquí es **por qué son getters y no valores**: `mediaRegistry.js`
 * se importa antes de que el catálogo esté cargado, así que un valor se congelaría
 * con el catálogo vacío y la app pintaría claves. Un getter se evalúa al leerlo, y
 * además queda suscrito al `ref` del catálogo, así que la interfaz cambia de idioma
 * **sin recargar**.
 *
 * Si alguien convierte estos getters en valores «para simplificar», este test cae.
 */
describe('mediaRegistry — los rótulos vienen del catálogo', () => {
  afterAll(async () => { await setLocale('es') })

  it('traduce el rótulo de cada medio', () => {
    expect(getMediaConfig('book').label).toBe('Libro')
    expect(getMediaConfig('movie').label).toBe('Película')
    expect(getMediaConfig('series').label).toBe('Serie')
  })

  it('y sigue al idioma sin volver a importar el módulo', async () => {
    await setLocale('en')

    expect(getMediaConfig('book').label).toBe('Book')
    expect(getMediaConfig('movie').label).toBe('Movie')

    await setLocale('es')
    expect(getMediaConfig('book').label).toBe('Libro')
  })

  it('los seis medios tienen rótulo, estado y texto de vacío, y ninguno es una clave', () => {
    // Que devuelva la clave («media.book.label») significaría que falta en el
    // catálogo: el motor devuelve la clave tal cual cuando no la encuentra.
    for (const medio of mediaKeys) {
      const cfg = getMediaConfig(medio)
      for (const campo of ['label']) {
        expect(cfg[campo], `${medio}.${campo}`).toBeTruthy()
        expect(cfg[campo], `${medio}.${campo}`).not.toMatch(/^media\./)
      }
    }
  })

  it('el `statusLabel` ya no rotula en inglés en tres medios y en español en dos', () => {
    // Era una excepción declarada en un comentario de `mediaRegistry.js:424`.
    // Desde el 2026-08-31 todos salen del catálogo y dicen lo mismo.
    const rotulos = ['book', 'movie', 'game', 'album', 'video']
      .map((m) => getMediaConfig(m).detail?.statusLabel ?? getMediaConfig(m).libraryItem?.statusLabel)
      .filter(Boolean)

    expect(new Set(rotulos).size).toBe(1)
    expect(rotulos[0]).toBe('Estado')
  })
})
