import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'

/**
 * La barrera del movimiento.
 *
 * Ningún `@keyframes` puede animar `left`, `right`, `top` ni `bottom`.
 *
 * El porqué: `tests/visual/overflow.mjs` mide `getBoundingClientRect().right`
 * elemento a elemento, y ese rect **incluye** el desplazamiento. Una animación que
 * mueve la caja la mete y la saca del viewport varias veces por segundo, así que la
 * barrera de desbordamiento pasa a depender del fotograma en que mida. Pasó de
 * verdad: `.progress-bar-shine` animaba `left: -100% → 100%` y `/books/:isbn` salía
 * `right=366 (viewport 360)` en una pasada y `ok` en la siguiente (Roadmap #21).
 *
 * Por qué aquí y no en stylelint: `declaration-property-value-disallowed-list` es
 * global, y esta app usa `left`/`top` legítimamente en decenas de posicionamientos
 * (`position: absolute; left: 0`). No hay regla estándar que acote propiedades
 * **dentro de un `@keyframes`**, y forzar la global exigiría un `stylelint-disable`
 * por cada uno de esos posicionamientos.
 *
 * Se limita a los cuatro offsets **a propósito**. `width`, `height` y los márgenes
 * también mueven cajas, pero animar `width` es legítimo y frecuente aquí —la propia
 * `.progress-bar` lo hace—, así que incluirlos convertiría la barrera en ruido. Y se
 * prohíben offsets, **no `transform`s**: `btn-shake` y `btn-pulse-success` mueven la
 * caja del botón de verdad, pero son finitos (0,5 s), solo se disparan tras una
 * acción del usuario y la barrera no interactúa con la app, así que no los ve.
 *
 * **Sin fichero de excepciones**, a diferencia del de i18n. Allí la lista existía
 * porque el barrido dejaba deuda real; aquí la barrera nace con cero casos y no se
 * conoce ninguno legítimo. Una lista vacía es una invitación a estrenarla.
 */

// Desde `process.cwd()` y no desde `import.meta.url`, por lo mismo que en
// `i18n.spec.js`: en Vitest esa URL no siempre es `file://`. La raíz es el frontend.
const SRC = join(process.cwd(), 'src')

const OFFSETS = ['left', 'right', 'top', 'bottom']

function ficherosDe (dir, ext = ['.vue', '.scss'], acc = []) {
  for (const nombre of readdirSync(dir)) {
    const ruta = join(dir, nombre)
    if (statSync(ruta).isDirectory()) ficherosDe(ruta, ext, acc)
    else if (ext.some(e => nombre.endsWith(e))) acc.push(ruta)
  }
  return acc
}

/**
 * Fuera los comentarios antes de mirar. Sin esto, un `left` comentado dentro de un
 * `@keyframes` —o el ejemplo de un JSDoc que cite este mismo test— haría fallar la
 * barrera. Limpiador simple, no un parser: es lo mismo que hace `i18n.spec.js`.
 */
function sinComentarios (contenido) {
  return contenido
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*\/\/.*$/gm, '')
}

/**
 * Los bloques `@keyframes`, **contando llaves**. Un regex de una línea no vale: el
 * cuerpo de un `@keyframes` tiene sus propios bloques anidados (`0% { … }`) y el
 * primer `}` que aparece cierra el escalón, no la animación.
 */
function bloquesKeyframes (contenido) {
  const limpio = sinComentarios(contenido)
  const bloques = []
  const cabecera = /@(?:-\w+-)?keyframes\s+([\w-]+)\s*\{/g
  let m
  while ((m = cabecera.exec(limpio)) !== null) {
    let profundidad = 1
    let i = m.index + m[0].length
    while (i < limpio.length && profundidad > 0) {
      if (limpio[i] === '{') profundidad++
      else if (limpio[i] === '}') profundidad--
      i++
    }
    bloques.push({ nombre: m[1], cuerpo: limpio.slice(m.index + m[0].length, i - 1) })
    cabecera.lastIndex = i
  }
  return bloques
}

/**
 * Las declaraciones de offset del cuerpo. El prefijo `[{;\s]` exige que la propiedad
 * empiece ahí: sin él, `margin-left:` y `border-top:` contarían como `left` y `top`.
 */
function offsetsEn (cuerpo) {
  return OFFSETS.filter(prop => new RegExp(`(?:^|[{;\\s])${prop}\\s*:`).test(cuerpo))
}

const bloques = []
for (const fichero of ficherosDe(SRC)) {
  for (const bloque of bloquesKeyframes(readFileSync(fichero, 'utf8'))) {
    bloques.push({ ...bloque, fichero: relative(SRC, fichero) })
  }
}

describe('animaciones — la barrera del movimiento', () => {
  it('ningún `@keyframes` anima `left`, `right`, `top` ni `bottom`', () => {
    const culpables = bloques
      .map(b => ({ ...b, props: offsetsEn(b.cuerpo) }))
      .filter(b => b.props.length > 0)
      .map(b => `${b.fichero}  @keyframes ${b.nombre}  →  ${b.props.join(', ')}`)

    expect(
      culpables,
      'Hay animaciones que mueven la caja de un elemento:\n  ' + culpables.join('\n  ') +
      '\n\nAnímalo con `transform` (translate/scale/rotate), que no toca el layout, o ' +
      'mueve el fondo con `background-position` como hace `@keyframes shine` en ' +
      '`components/common/ReadingProgressBar.vue`. Un offset animado rompe ' +
      '`tests/visual/overflow.mjs`, que mide cajas.'
    ).toEqual([])
  })

  it('el extractor encuentra los `@keyframes` que hay: una barrera que no lee nada siempre pasa', () => {
    // El modo de fallo silencioso de este tipo de test es que el extractor deje de
    // encontrar bloques —un cambio de sintaxis, una carpeta que se mueve— y la
    // barrera se quede en verde para siempre sin mirar nada.
    expect(
      bloques.length,
      `No se encontró ningún bloque @keyframes bajo ${SRC}. O no queda ninguno en la ` +
      'app, o `bloquesKeyframes()` ha dejado de reconocerlos.'
    ).toBeGreaterThan(0)
  })
})
