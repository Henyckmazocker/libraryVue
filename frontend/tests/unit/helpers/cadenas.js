/**
 * Extractor de cadenas de `<script>`, compartido por el generador de
 * `i18n-strings.allowlist.json` y por la barrera que lo vigila.
 *
 * **Vive aquí y no en un guion suelto a propósito.** Si el clasificador que
 * genera la lista y el test que la comprueba usaran reglas distintas, la lista
 * se descuadraría sola en el primer barrido y nadie sabría cuál de los dos
 * miente.
 *
 * Lo que descarta lo descarta **por la forma de la llamada**, no por una lista de
 * excepciones: un mensaje de `Logger` se reconoce porque está dentro de
 * `Logger.algo(…)`, y una clave de acción porque es el primer argumento de
 * `apiCall(…)`. Es la diferencia entre una regla que sigue valiendo dentro de un
 * año y una lista que hay que mantener.
 *
 * No usa un parser de verdad (`espree` es una dependencia transitiva de ESLint,
 * no declarada, y para los SFC haría falta además `vue-eslint-parser`). El
 * recorrido de abajo salta comentarios y literales de plantilla a mano, que es lo
 * único que un regex hace mal de verdad.
 */

/** Los seis métodos que existen hoy en `utils/logger.js`. */
const LOGGER = /Logger\s*\.\s*(?:api|auth|debug|error|info|warn)\s*\(\s*$/

/** El primer argumento de una llamada al backend es la acción, no un texto. */
const ACCION = /\b(?:authenticatedApiCall|apiCall)\s*\(\s*$/

/** Una clave del catálogo ya está traducida: es la clave, no el texto. */
const CLAVE_I18N = /\bt\s*\(\s*$/

/** `import 'x'`, `from 'x'`, `import('x')`. */
const IMPORTACION = /\b(?:import|from|require)\s*\(?\s*$/

/**
 * `[MenuStore]`, `[useWorkSearch]`, `[mediaRegistry]`… La convención del repo para
 * un mensaje de quien depura, y el criterio que el plan nombra para `store/auth.js`.
 */
const PREFIJO_DE_LOG = /^\s*\[[A-Za-z][\w.-]*\]/

/**
 * Campos cuyo valor es un identificador y no un rótulo.
 *
 * `name:` va aparte, en `NOMBRE_DE_RUTA`: fuera de `router/index.js` un `name:`
 * es casi siempre una etiqueta — `useFileImport.js:50` tiene
 * `name: 'Datos Serializados'`, que es lo que lee el usuario al importar.
 */
const NOMBRE_TECNICO = /\b(?:path|redirect|component|key|action|type|ref|tag|as|locale|code|code2|code3|native|storeKey|routeName|media|entity_type)\s*:\s*$/

/** El `name:` de una ruta sí es un identificador: lo usa `router.push({ name })`. */
const NOMBRE_DE_RUTA = /\bname\s*:\s*$/

/**
 * Un operando de comparación es un valor con el que se casa, no algo que se
 * escriba en pantalla: `newStatus === 'to read'` compara contra un slug del
 * backend. Nadie compara contra el texto que ve el usuario.
 */
const COMPARACION = /[!=]==?\s*$/

/**
 * Una cadena seguida de `:` es la CLAVE de un objeto… **o la rama de un
 * ternario**, que se escribe igual y es texto de pleno derecho:
 * `props.itemType === 'book' ? 'el libro' : 'la película'`. Por eso no basta con
 * mirar lo de detrás: delante de una clave hay `{`, `,` o principio de línea.
 */
const CLAVE_DE_OBJETO = [/[{,]\s*$|\n\s*$/, /^\s*:/]

/**
 * `statusStats['por leer']` accede a un dato del backend, no escribe texto. Pero
 * `['Esta acción no se puede deshacer']` es un **array de un elemento** y se
 * escribe igual: el acceso exige que delante del `[` haya algo a lo que acceder.
 */
const ACCESO_POR_CLAVE = [/[\w)\]]\s*\[\s*$/, /^\s*\]/]

/** Iconos, unidades, colores, MIME y demás vocabulario de máquina. */
const OTRAS_TECNICAS = [
  /^(?:fas|far|fab|fal|pi)\b/,                          // iconos
  /^[A-Za-z][\w.-]*$/,                                  // un identificador suelto
  /^#[0-9a-fA-F]{3,8}$/,                                // color
  /^-?\d/,                                              // empieza por número
  /^[\s\W]*$/,                                          // solo símbolos
  /^\w+\/[\w+.-]+$/,                                    // MIME
  /^(?:rgba?|hsla?|var|calc|url)\s*\(/,                  // CSS: `rgba(163, 203, 193, .16)`
  /^\(.*\)$/,                                           // `(prefers-color-scheme: dark)`
  /^[.#][\w-]+(?:[\s>+~][.#]?[\w-]+)*$/,                 // selector CSS: `.stars-input .star-button`
  /^[A-Z_]+$/                                           // CONSTANTE
]

/**
 * Varias palabras en minúscula **y con un guion en alguna**: una lista de clases.
 *
 * El guion no es un capricho. Sin él, «varias palabras en minúscula» se traga
 * **cualquier frase**: «por leer», «ahora mismo», «tiene una nota nueva» y
 * «actualizado correctamente» acababan en el montón de identificadores, que es la
 * dirección peligrosa — se quedan sin traducir y nada avisa. Una lista de clases
 * de verdad (`btn btn--primary`, `fas fa-book`, `is-open`) siempre lleva guion.
 */
const VARIAS_PALABRAS = /^[a-z][\w-]*(?:\s+[a-z][\w-]*)+$/
const CLASE_CSS = /-/

/** Un texto de interfaz lleva letras y, o espacio, o un signo del castellano. */
const ACENTOS = /[áéíóúüñÁÉÍÓÚÜÑ¿¡]/

/**
 * Recorre el fuente y devuelve `[{ texto, indice }]` de cada literal.
 *
 * Los literales de plantilla se parten por sus `${}`: en `` `Hola ${x} qué tal` ``
 * lo que se mira es «Hola » y « qué tal», porque la expresión de en medio es
 * código y no texto.
 */
export function literales (fuente) {
  const fuera = []
  let i = 0

  /** El último carácter con contenido antes de `i`. */
  const anterior = () => {
    for (let j = i - 1; j >= 0; j--) if (!/\s/.test(fuente[j])) return fuente[j]
    return ''
  }

  while (i < fuente.length) {
    const c = fuente[i]

    // Comentarios
    if (c === '/' && fuente[i + 1] === '/') {
      while (i < fuente.length && fuente[i] !== '\n') i++
      continue
    }
    if (c === '/' && fuente[i + 1] === '*') {
      i = fuente.indexOf('*/', i + 2)
      i = i === -1 ? fuente.length : i + 2
      continue
    }

    // Un `/` que no abre comentario puede abrir un regex. Se distingue por lo
    // anterior: tras un valor viene una división; tras un operador, un regex.
    if (c === '/') {
      const previo = anterior()
      if (previo && !/[)\]}\w'"`]/.test(previo)) {
        i++
        while (i < fuente.length && fuente[i] !== '/' && fuente[i] !== '\n') {
          if (fuente[i] === '\\') i++
          i++
        }
        i++
        continue
      }
      i++
      continue
    }

    if (c === "'" || c === '"') {
      const inicio = i
      i++
      let texto = ''
      while (i < fuente.length && fuente[i] !== c) {
        if (fuente[i] === '\\') { texto += fuente[i + 1] ?? ''; i += 2; continue }
        if (fuente[i] === '\n') break
        texto += fuente[i]
        i++
      }
      i++
      fuera.push({ texto, indice: inicio, fin: i, grupo: fuera.length })
      continue
    }

    if (c === '`') {
      const inicio = i
      const grupo = fuera.length
      i++
      let texto = ''
      let profundidad = 0
      while (i < fuente.length) {
        if (profundidad === 0 && fuente[i] === '`') break
        if (fuente[i] === '\\') { texto += fuente[i + 1] ?? ''; i += 2; continue }
        if (fuente[i] === '$' && fuente[i + 1] === '{') {
          // La expresión no es texto: se corta el trozo y se salta.
          if (texto.trim()) fuera.push({ texto, indice: inicio, fin: i, grupo })
          texto = ''
          i += 2
          profundidad = 1
          while (i < fuente.length && profundidad > 0) {
            if (fuente[i] === '{') profundidad++
            else if (fuente[i] === '}') profundidad--
            if (profundidad > 0) i++
          }
          i++
          profundidad = 0
          continue
        }
        texto += fuente[i]
        i++
      }
      i++
      if (texto.trim()) fuera.push({ texto, indice: inicio, fin: i, grupo })
      continue
    }

    i++
  }

  return fuera
}

/** El `<script>` de un SFC; el fichero entero si es `.js`. */
export function guionDe (contenido, ruta) {
  if (!ruta.endsWith('.vue')) return contenido
  const trozos = [...contenido.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)]
  return trozos.map((m) => m[1]).join('\n')
}

/**
 * Clasifica una cadena en `log`, `identificador` o `interfaz`.
 *
 * `antes` es lo que precede al literal en el fuente; de ahí salen las tres
 * exclusiones por forma de llamada.
 */
export function clasifica (texto, antes, despues = '', ruta = '') {
  const recorte = antes.slice(-120)
  const cola = despues.slice(0, 40)

  if (LOGGER.test(recorte)) return 'log'
  if (PREFIJO_DE_LOG.test(texto)) return 'log'
  if (ACCION.test(recorte) || CLAVE_I18N.test(recorte) || IMPORTACION.test(recorte)) return 'identificador'
  if (NOMBRE_TECNICO.test(recorte)) return 'identificador'
  if (NOMBRE_DE_RUTA.test(recorte) && ruta.includes('router/')) return 'identificador'
  if (COMPARACION.test(recorte)) return 'identificador'
  if (CLAVE_DE_OBJETO[0].test(recorte) && CLAVE_DE_OBJETO[1].test(cola)) return 'identificador'
  if (ACCESO_POR_CLAVE[0].test(recorte) && ACCESO_POR_CLAVE[1].test(cola)) return 'identificador'

  const limpio = texto.trim()
  if (limpio.length < 3) return 'identificador'
  if (OTRAS_TECNICAS.some((re) => re.test(limpio))) return 'identificador'
  if (VARIAS_PALABRAS.test(limpio) && CLASE_CSS.test(limpio)) return 'identificador'

  // Lo que queda es texto si lleva letras y, o un espacio interior, o un signo
  // que solo aparece escribiendo para una persona.
  const tieneLetras = /[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/.test(limpio)
  if (!tieneLetras) return 'identificador'
  if (/\s/.test(limpio) || ACENTOS.test(limpio)) return 'interfaz'

  return 'identificador'
}

/** Todas las cadenas de un fichero, ya clasificadas. */
export function cadenasDe (contenido, ruta) {
  const guion = guionDe(contenido, ruta)
  const trozos = literales(guion).map(({ texto, indice, fin, grupo }) => ({
    texto,
    grupo,
    monton: clasifica(texto, guion.slice(0, indice), guion.slice(fin ?? indice), ruta)
  }))

  // Un literal de plantilla es UN mensaje, aunque sus `${}` lo partan en trozos:
  // `` `[mediaRegistry] Medio desconocido: "${m}". Válidos: ${…}` `` es un `throw`
  // para quien depura, y la segunda mitad no puede clasificarse por su cuenta.
  const deLog = new Set(trozos.filter((x) => x.monton === 'log').map((x) => x.grupo))
  return trozos.map((x) => (deLog.has(x.grupo) ? { ...x, monton: 'log' } : x))
}
