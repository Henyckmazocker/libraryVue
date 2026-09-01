import { describe, it, expect } from 'vitest'
import { normalizarTexto, puntuarTitulo, ordenarPorRelevancia } from '@/utils/searchRelevance'
import { mediaKeys } from '@/config/mediaRegistry'

/**
 * La barrera de la relevancia.
 *
 * Es lo que hace que la pantalla del M4 se pueda escribir sin miedo: la regla
 * que decide el orden se valida sola, antes de que exista nada que pintar. El
 * mismo orden que se siguió con `ClubRoundResolver`.
 *
 * Lo que de verdad se juega aquí son **los títulos cortos**. «it», «up» y «yes»
 * caen dentro de decenas de títulos, y una puntuación que solo mirara subcadenas
 * los subiría todos al mismo escalón: la primera pantalla del buscador saldría
 * llena de coincidencias casuales. Por eso el escalón de palabra entera existe y
 * va por encima del de subcadena, y por eso esos casos tienen su bloque aquí.
 */

describe('normalizarTexto', () => {
  it('baja a minúsculas y quita los acentos, para que «amelie» encuentre «Amélie»', () => {
    expect(normalizarTexto('Amélie')).toBe('amelie')
    expect(normalizarTexto('EL IMPERIO FINAL')).toBe('el imperio final')
    // La `İ` turca se descompone en `i` + punto combinante, así que al quitar
    // los diacríticos queda una `i` normal. Es lo que se quiere: el título turco
    // de Mistborn se encuentra escribiendo «imparatorluk» sin la tilde.
    expect(normalizarTexto('Sissoylu — Son İmparatorluk')).toBe('sissoylu son imparatorluk')
  })

  it('convierte cualquier signo en separador, no en nada', () => {
    // Si los signos se borraran en vez de separar, «Dune:Parte» sería una sola
    // palabra y el escalón de palabra entera no encontraría «parte».
    expect(normalizarTexto('Dune: Parte Dos')).toBe('dune parte dos')
    expect(normalizarTexto("Frank Herbert's Dune")).toBe('frank herbert s dune')
    expect(normalizarTexto('Zelda II: The Adventure of Link')).toBe('zelda ii the adventure of link')
  })

  it('aguanta lo que no es texto sin reventar', () => {
    expect(normalizarTexto(null)).toBe('')
    expect(normalizarTexto(undefined)).toBe('')
    expect(normalizarTexto('   ')).toBe('')
  })
})

describe('puntuarTitulo — los cinco escalones', () => {
  it('100 · el título es exactamente la query', () => {
    expect(puntuarTitulo('Dune', 'dune')).toBe(100)
    expect(puntuarTitulo('dune', 'DUNE')).toBe(100)
    // La igualdad es DESPUÉS de normalizar: los signos no cuentan.
    expect(puntuarTitulo('¡Dune!', 'dune')).toBe(100)
  })

  it('75 · el título empieza por la query', () => {
    expect(puntuarTitulo('Dune: Parte Dos', 'dune')).toBe(75)
    expect(puntuarTitulo('Dune Messiah', 'dune')).toBe(75)
  })

  it('50 · la query aparece como palabra entera', () => {
    expect(puntuarTitulo("Frank Herbert's Dune", 'dune')).toBe(50)
    expect(puntuarTitulo('Herejes de Dune', 'dune')).toBe(50)
  })

  it('25 · la query aparece solo como subcadena', () => {
    expect(puntuarTitulo('Dunedain', 'dune')).toBe(25)
    expect(puntuarTitulo('Bardunes', 'dune')).toBe(25)
  })

  it('0 · no aparece, y se descarta', () => {
    expect(puntuarTitulo('El Imperio Final', 'dune')).toBe(0)
    expect(puntuarTitulo('', 'dune')).toBe(0)
    expect(puntuarTitulo('Dune', '')).toBe(0)
  })

  it('los escalones están ordenados: cada caso gana el más alto que cumple', () => {
    // «Dune: Parte Dos» cumple los tres de abajo a la vez —empieza por la query,
    // la contiene como palabra y como subcadena—: tiene que salir 75, no 50.
    const prefijo   = puntuarTitulo('Dune: Parte Dos', 'dune')
    const palabra   = puntuarTitulo("Frank Herbert's Dune", 'dune')
    const subcadena = puntuarTitulo('Dunedain', 'dune')

    expect(prefijo).toBeGreaterThan(palabra)
    expect(palabra).toBeGreaterThan(subcadena)
  })
})

describe('puntuarTitulo — los títulos cortos, que es donde esto se rompe', () => {
  it('«it» como palabra entera gana a «it» dentro de otra palabra', () => {
    expect(puntuarTitulo('It Follows', 'it')).toBe(75)
    expect(puntuarTitulo('Stephen King\'s It', 'it')).toBe(50)
    // Los que sin el escalón de palabra entera empatarían con los de arriba:
    expect(puntuarTitulo('Bitmap Brothers', 'it')).toBe(25)
    expect(puntuarTitulo('Little Women', 'it')).toBe(25)
    expect(puntuarTitulo('Titanic', 'it')).toBe(25)
  })

  it('«up» no confunde «Up» con «Superbad»', () => {
    expect(puntuarTitulo('Up', 'up')).toBe(100)
    expect(puntuarTitulo('Up in the Air', 'up')).toBe(75)
    expect(puntuarTitulo('Superbad', 'up')).toBe(25)
  })

  it('«yes» distingue el disco de «Eyes Wide Shut»', () => {
    expect(puntuarTitulo('Yes', 'yes')).toBe(100)
    expect(puntuarTitulo('Eyes Wide Shut', 'yes')).toBe(25)
  })

  it('una query de varias palabras también tiene sus escalones', () => {
    expect(puntuarTitulo('Kind of Blue', 'kind of blue')).toBe(100)
    expect(puntuarTitulo('Kind of Blue (Legacy Edition)', 'kind of blue')).toBe(75)
    expect(puntuarTitulo('Some Kind of Blue', 'kind of blue')).toBe(50)
    expect(puntuarTitulo('Reggae Interpretation of Kind of Blueness', 'kind of blue')).toBe(25)
  })

  it('un carácter especial en la query no revienta la expresión regular', () => {
    // Sin escapar, un `(` en la query lanzaría SyntaxError al construir el regex.
    expect(() => puntuarTitulo('Todo (bien)', '(bien)')).not.toThrow()
    expect(puntuarTitulo('Todo (bien)', '(bien)')).toBe(50)
    expect(() => puntuarTitulo('C++ para todos', 'c++')).not.toThrow()
  })
})

describe('ordenarPorRelevancia', () => {
  const entrada = (media, title) => ({ media, title })

  it('ordena por puntuación de mayor a menor', () => {
    const r = ordenarPorRelevancia([
      entrada('movie', 'Dunedain'),           // 25
      entrada('movie', "Frank Herbert's Dune"), // 50
      entrada('movie', 'Dune'),               // 100
      entrada('movie', 'Dune: Parte Dos')     // 75
    ], 'dune')

    expect(r.map((e) => e.title)).toEqual([
      'Dune', 'Dune: Parte Dos', "Frank Herbert's Dune", 'Dunedain'
    ])
  })

  it('descarta lo que no casa en vez de dejarlo al final', () => {
    const r = ordenarPorRelevancia([
      entrada('movie', 'Dune'),
      entrada('game', 'El Imperio Final')
    ], 'dune')

    expect(r).toHaveLength(1)
    expect(r[0].title).toBe('Dune')
  })

  it('empata por el orden de declaración de `mediaKeys`, que es determinista', () => {
    // Los seis con la misma puntuación: el orden de salida tiene que ser el del
    // registry, no el de entrada.
    const alReves = [...mediaKeys].reverse().map((m) => entrada(m, 'Dune'))

    expect(ordenarPorRelevancia(alReves, 'dune').map((e) => e.media)).toEqual(mediaKeys)
  })

  it('dentro de un mismo medio conserva el orden del proveedor', () => {
    const r = ordenarPorRelevancia([
      entrada('game', 'Dune Primero'),
      entrada('game', 'Dune Segundo'),
      entrada('game', 'Dune Tercero')
    ], 'dune')

    expect(r.map((e) => e.title)).toEqual(['Dune Primero', 'Dune Segundo', 'Dune Tercero'])
  })

  it('la puntuación manda sobre el medio: un vídeo exacto gana a un libro parcial', () => {
    const r = ordenarPorRelevancia([
      entrada('book', 'Dunedain'),  // 25, y `book` va primero en mediaKeys
      entrada('video', 'Dune')      // 100
    ], 'dune')

    expect(r.map((e) => e.media)).toEqual(['video', 'book'])
  })

  it('deja intacto lo que viaja en la entrada, y añade `score`', () => {
    const item = { isbn: '123', coverUrl: 'x.jpg' }
    const [r] = ordenarPorRelevancia([{ media: 'book', title: 'Dune', item }], 'dune')

    expect(r.item).toBe(item)
    expect(r.score).toBe(100)
  })

  it('una lista vacía o una query vacía devuelven lista vacía, no revientan', () => {
    expect(ordenarPorRelevancia([], 'dune')).toEqual([])
    expect(ordenarPorRelevancia([entrada('movie', 'Dune')], '')).toEqual([])
  })

  it('un medio que el registry no conoce va al final, no arriba', () => {
    const r = ordenarPorRelevancia([
      entrada('inventado', 'Dune'),
      entrada('book', 'Dune')
    ], 'dune')

    expect(r.map((e) => e.media)).toEqual(['book', 'inventado'])
  })
})
