import { describe, expect, it } from 'vitest'
import { detailRouteFor, getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import router from '@/router'

/**
 * El destino de la ficha de detalle, derivado del registry.
 *
 * Lo que se fija aquí no es la forma del objeto —eso lo vería cualquiera— sino
 * que **el router lo acepta**: por eso cada destino se pasa por
 * `router.resolve()` en vez de compararlo con un literal escrito a mano. Un
 * `name` que no existe o un parámetro con el nombre cambiado se cazan ahí, y no
 * los cazaría un `toEqual` contra una tabla copiada del propio registry.
 *
 * Importa porque el aviso natural de vue-router es un `console.warn`, que no
 * rompe nada: es exactamente cómo `v-tooltip` estuvo tres meses sin registrar.
 */

const CLAVE_DE_PRUEBA = 'clave-de-prueba'

describe('detailRouteFor — las seis entradas del registry', () => {
  it.each(mediaKeys)('%s devuelve un destino que el router resuelve', (media) => {
    const destino = detailRouteFor(media, CLAVE_DE_PRUEBA)

    expect(destino).toMatchObject({ name: expect.any(String), params: expect.any(Object) })

    const resuelto = router.resolve(destino)

    // `NotFound` es el comodín: si el `name` no casara con ninguna ruta de
    // verdad, se resolvería ahí en vez de fallar.
    expect(resuelto.name).toBe(destino.name)
    expect(resuelto.path).toContain(CLAVE_DE_PRUEBA)
  })

  it('las seis son las cinco con store más series', () => {
    expect(mediaKeys).toEqual(['book', 'movie', 'game', 'album', 'video', 'series'])
  })

  it('una serie va a su propia ficha y no a la de película', () => {
    expect(router.resolve(detailRouteFor('series', 'tt0098936')).path).toBe('/series/tt0098936')
    expect(router.resolve(detailRouteFor('movie', 'tt0098936')).path).toBe('/movies/tt0098936')
  })
})

describe('detailRouteFor — lo que no se puede enlazar', () => {
  // La tarjeta del feed llama a esto con `entity_type`, que es NULLable, y con
  // `entity_id`, que lo es también: un evento de `achievement` no tiene ninguno
  // de los dos. Devolver `null` es lo que la deja pintarse sin enlace.
  it.each([
    ['un medio que el registry no conoce', 'podcast'],
    ['un medio nulo', null],
    ['un medio vacío', '']
  ])('%s devuelve null', (_caso, media) => {
    expect(detailRouteFor(media, CLAVE_DE_PRUEBA)).toBeNull()
  })

  it.each([
    ['null', null],
    ['undefined', undefined],
    ['cadena vacía', '']
  ])('un entityId %s devuelve null', (_caso, entityId) => {
    expect(detailRouteFor('book', entityId)).toBeNull()
  })

  // El 0 es un id legítimo en los medios con id numérico; si la guarda usara
  // `!entityId` se lo comería junto con la cadena vacía.
  it('un entityId 0 sí es un destino', () => {
    expect(detailRouteFor('game', 0)).toEqual({ name: 'GameDetail', params: { gameId: 0 } })
  })
})

/**
 * La estructura de la ficha es UNA, no dos.
 *
 * Hasta el 2026-09-02, el bloque `detail` de cada medio traía tres banderas
 * —`librarySectionClass`, `libraryTitleIcon` y `divider`— que partían los seis en
 * dos grupos idénticos: libro, película y serie con icono y filete; juego, álbum y
 * vídeo con un `<h2>` pelado. Nadie lo había decidido: venía de respetar la
 * divergencia que ya existía al unificar las seis vistas en `MediaDetailView`.
 *
 * Este test es la única barrera posible contra que reaparezca, y el momento en que
 * reaparecería es al añadir un medio nuevo copiando el bloque del de al lado.
 */
describe('el bloque `detail` no bifurca la estructura por medio', () => {
  const BANDERAS_PROHIBIDAS = ['librarySectionClass', 'libraryTitleIcon', 'divider']

  it.each(mediaKeys)('%s no declara ninguna bandera de estructura', (media) => {
    const detail = getMediaConfig(media).detail

    for (const bandera of BANDERAS_PROHIBIDAS) {
      expect(
        Object.prototype.hasOwnProperty.call(detail, bandera),
        `\`${media}.detail.${bandera}\` volvió: la sección de biblioteca es la misma `
        + 'para los seis medios y su forma se decide en la plantilla, no en el registry.'
      ).toBe(false)
    }
  })
})
