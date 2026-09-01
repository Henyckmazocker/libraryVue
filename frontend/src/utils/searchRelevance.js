import { mediaKeys } from '@/config/mediaRegistry'

/**
 * La relevancia del buscador general: cuánto casa un título con lo que escribiste.
 *
 * **Por qué solo el título.** Es lo único comparable entre seis proveedores. El
 * `rating` de IGDB y el orden de Google Books no significan lo mismo, no están en
 * la misma escala y la mitad de los medios no traen ninguno; ordenar por ellos
 * sería inventarse una autoridad que no existe. Aquí se mide una cosa sola, y se
 * mide igual para los seis.
 *
 * **Los cinco escalones**, de mayor a menor, con «dune» de ejemplo:
 *
 * | Puntos | Caso                                  | Ejemplo                 |
 * |--------|---------------------------------------|-------------------------|
 * | 100    | El título es exactamente la query     | «Dune»                  |
 * | 75     | El título empieza por la query        | «Dune: Parte Dos»       |
 * | 50     | La query aparece como palabra entera  | «Frank Herbert's Dune»  |
 * | 25     | La query aparece como subcadena       | «Dunedain»              |
 * | 0      | No aparece                            | *(se descarta)*         |
 *
 * **El escalón de palabra entera existe por los títulos cortos.** «it» cae dentro
 * de decenas de títulos —«Bitmap», «Little»— y sin ese escalón todos subirían al
 * mismo nivel que «It Follows», que es el que de verdad buscas. Es el fallo que
 * solo se ve con datos reales y ya de cara al usuario.
 *
 * Módulo puro a propósito: sin Vue, sin red y sin estado, para poder probarlo
 * antes de que exista la pantalla. Es el mismo orden que se siguió con
 * `ClubRoundResolver` en el plan de votación del club.
 */

/**
 * Deja el texto comparable: minúsculas, sin acentos y sin signos.
 *
 * Los dos lados pasan por aquí. Sin quitar acentos, «Amelie» no encontraría
 * «Amélie»; sin quitar signos, «Dune: Parte Dos» no empezaría por «dune» sino por
 * «dune:», y el escalón de prefijo no se dispararía nunca en un título con
 * subtítulo — que son casi todos.
 */
export function normalizarTexto (texto) {
  return String(texto ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')   // los diacríticos, ya separados por NFD
    .toLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, ' ')  // cualquier signo pasa a separador
    .trim()
}

/** Escapa lo que va a viajar dentro de una expresión regular. */
function escaparRegex (texto) {
  return texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * Los puntos de un título contra una query. Ver la tabla de arriba.
 *
 * El orden de las comprobaciones importa y no es intercambiable: un título que
 * empieza por la query también la contiene como palabra entera y como subcadena,
 * así que gana el escalón más alto que cumpla.
 */
export function puntuarTitulo (titulo, query) {
  const t = normalizarTexto(titulo)
  const q = normalizarTexto(query)

  if (t === '' || q === '') return 0
  if (t === q) return 100

  // Prefijo, pero **de palabra entera**: sin esta condición «Dunedain» empezaría
  // por «dune» y se colaría en el escalón de 75, por delante de «Dune: Parte Dos».
  if (t.startsWith(q) && t[q.length] === ' ') return 75

  if (new RegExp(`(^| )${escaparRegex(q)}( |$)`).test(t)) return 50
  if (t.includes(q)) return 25

  return 0
}

/**
 * Ordena la lista mezclada de los seis medios, y descarta lo que no casa.
 *
 * Cada entrada es `{ media, title, … }`: el resto viaja intacto, que es lo que
 * permite que quien llame meta ahí el ítem entero. El `title` lo pone quien
 * construye la lista, sacándolo de `api.search.titleOf` del registry — así este
 * módulo no necesita saber que un álbum llama `name` a lo que una película llama
 * `Title`.
 *
 * **El desempate es el orden de declaración de `mediaKeys`** (libro, película,
 * juego, álbum, vídeo, serie). No es que un medio importe más que otro —no hay
 * tal cosa—: es que hace falta un criterio determinista para que dos resultados
 * con la misma puntuación salgan siempre en el mismo sitio, y que los tests sean
 * reproducibles. Dentro de un mismo medio se conserva el orden en que llegaron,
 * que es el que el proveedor considera relevante.
 */
export function ordenarPorRelevancia (entradas, query) {
  const orden = new Map(mediaKeys.map((clave, i) => [clave, i]))

  return entradas
    .map((entrada) => ({ ...entrada, score: puntuarTitulo(entrada.title, query) }))
    .filter((entrada) => entrada.score > 0)
    .sort((a, b) => {
      if (b.score !== a.score) return b.score - a.score

      // `?? mediaKeys.length` deja al final un medio que el registry no conozca,
      // en vez de mandarlo arriba con un índice indefinido.
      const oa = orden.get(a.media) ?? mediaKeys.length
      const ob = orden.get(b.media) ?? mediaKeys.length

      return oa - ob
    })
}
