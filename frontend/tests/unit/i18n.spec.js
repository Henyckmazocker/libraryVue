import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import es from '@/locales/es.yaml'
import en from '@/locales/en.yaml'
import { cadenasDe } from './helpers/cadenas'
import lista from './i18n-strings.allowlist.json'

// El YAML crudo, además del compilado: una clave repetida no se ve en el objeto.
const CRUDOS = {
  'es.yaml': join(process.cwd(), 'src/locales/es.yaml'),
  'en.yaml': join(process.cwd(), 'src/locales/en.yaml')
}

/**
 * La barrera del catálogo.
 *
 * Es lo que hace seguro el barrido de los M3 y M4: sin ella, una cadena que se
 * queda a medias **no rompe nada** —se ve en el idioma en que se escribió— y solo
 * se descubre mirando la pantalla en el otro idioma.
 *
 * Tres comprobaciones, en el orden en que fallan:
 *   1. `es.yaml` y `en.yaml` tienen exactamente las mismas claves.
 *   2. Toda clave literal usada como `t('…')` en `src/` existe en el catálogo.
 *   3. No hay claves en el catálogo que nadie use.
 *   4. Ningún bloque está declarado dos veces.
 *   5. No hay cadenas de interfaz en el `<script>` fuera del catálogo.
 *
 * La quinta es el candado del plan de textos de stores y servicios: la regla de
 * lint solo mira la plantilla, y en esta app **la mayoría de las cadenas no
 * estaban ahí** sino en stores, composables y servicios. `helpers/cadenas.js` las
 * reparte en tres montones por la **forma de la llamada**, y aquí se exige que el
 * de interfaz esté vacío.
 */

// Desde `process.cwd()` y no desde `import.meta.url`: en Vitest esa URL no siempre
// es `file://` —y el `URL` del entorno jsdom tampoco es el de Node—, así que
// `fileURLToPath` se queja de las dos maneras. Vitest corre con la raíz del
// frontend como cwd.
const SRC = join(process.cwd(), 'src')

/**
 * Prefijos que se construyen en tiempo de ejecución —`t('status.' + slug)`— y que
 * por definición no se pueden encontrar buscando literales. Se **declaran** aquí
 * en vez de intentar detectarlos: es el patrón que hace útil el catálogo, y una
 * lista corta y explícita se lee mejor que una heurística.
 */
const PREFIJOS_DINAMICOS = [
  'status.',   // t('status.' + slug), los 28 estados de la biblioteca
  'errors.',   // t('errors.' + código) — los mensajes del backend, por código
  'media.',    // t('media.' + medio + '.…'), desde `config/mediaRegistry.js`
  'menu.',     // t(item.nameKey), con la clave dentro de `sidebar-menu.json`
  'dashboardCards.',   // t(`dashboardCards.total.${clave}`), por medio
  'dashboardCharts.',  // t(`dashboardCharts.status.${clave}`), por medio
  'book.subjectGroups.', // t(`book.subjectGroups.${vocabulario}`) de OpenLibrary
  'language.'           // t(`language.${código}`) desde `utils/languageConstants.js`
]

/**
 * Claves escritas **antes** de que nada las use. Se vació al cerrar el M4, que es
 * lo que el plan pedía: `common.loading` la consume `TrendingCarousel` y
 * `library.itemCount`, los cuatro sitios que cuentan ítems de una lista.
 *
 * Sigue **vacía**: una clave aquí es una clave que sobra en el catálogo.
 */
const PENDIENTES_DE_USO = []

// ── utilidades ──────────────────────────────────────────────────────────────

/** Aplana el catálogo a claves con puntos. Un plural (`{one, other}`) es UNA hoja. */
function claves (nodo, prefijo = '', acc = []) {
  for (const [k, v] of Object.entries(nodo)) {
    const ruta = prefijo ? `${prefijo}.${k}` : k
    const esPlural = v && typeof v === 'object' &&
      Object.keys(v).every(f => f === 'one' || f === 'other')
    if (v && typeof v === 'object' && !esPlural) claves(v, ruta, acc)
    else acc.push(ruta)
  }
  return acc
}

function ficherosDe (dir, ext = ['.vue', '.js'], acc = []) {
  for (const nombre of readdirSync(dir)) {
    const ruta = join(dir, nombre)
    if (statSync(ruta).isDirectory()) ficherosDe(ruta, ext, acc)
    else if (ext.some(e => nombre.endsWith(e))) acc.push(ruta)
  }
  return acc
}

/**
 * Quita comentarios antes de buscar. Sin esto, el ejemplo del JSDoc del motor
 * —`t('library.title')`— cuenta como un uso real y la barrera exige una clave que
 * nadie usa. Es un limpiador simple, no un parser: una `//` dentro de una cadena
 * se lleva el resto de la línea. Se acepta porque el caso que importa
 * —documentación con ejemplos— lo cubre, y un parser aquí sería desproporcionado.
 */
function sinComentarios (contenido) {
  return contenido
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*\/\/.*$/gm, '')
}

/** Las llamadas `t('clave')` con literal. Las dinámicas caen en los prefijos. */
function usosEn (contenido) {
  // Dos exigencias, y las dos las puso un falso positivo real:
  //   `\bt\(` y no `t\(`  → si no, casa dentro de `format(`, `split(` y compañía.
  //   `[,)]` al final      → el literal tiene que ser el argumento COMPLETO. Sin
  //                          esto, `t('status.' + slug)` cuenta como un uso de la
  //                          clave literal «status.», que no existe ni debe existir.
  return [...sinComentarios(contenido).matchAll(/\bt\(\s*['"]([\w.-]+)['"]\s*[,)]/g)].map(m => m[1])
}

/**
 * Una clave también se usa cuando viaja como **dato** en vez de como argumento
 * de `t()`: los mapas por código de `store/lists.js` y `store/clubs.js` guardan
 * `403: 'lists.error403'` y es `apiError` quien lo traduce.
 *
 * Se cuenta solo si el literal **casa exactamente** con una clave que existe;
 * cualquier otra cadena con puntos —una ruta, un `fas fa-x`— no cuela.
 */
function clavesComoDato (contenido, existentes) {
  return [...sinComentarios(contenido).matchAll(/['"]([\w-]+(?:\.[\w-]+)+)['"]/g)]
    .map(m => m[1])
    .filter(k => existentes.has(k))
}

const clavesEs = claves(es).sort()
const clavesEn = claves(en).sort()

const conjuntoEs = new Set(clavesEs)
const usos = new Map()
for (const fichero of ficherosDe(SRC)) {
  const contenido = readFileSync(fichero, 'utf8')
  for (const clave of usosEn(contenido)) {
    if (!usos.has(clave)) usos.set(clave, relative(SRC, fichero))
  }
  // Las que viajan como dato solo cuentan para «nadie la usa»: para la
  // comprobación de huérfanas no aportan nada, porque ya se filtran por existir.
  for (const clave of clavesComoDato(contenido, conjuntoEs)) {
    if (!usos.has(clave)) usos.set(clave, relative(SRC, fichero))
  }
}

// ── las tres comprobaciones ─────────────────────────────────────────────────

describe('i18n — la barrera del catálogo', () => {
  it('`es.yaml` y `en.yaml` tienen exactamente las mismas claves', () => {
    const faltanEnIngles = clavesEs.filter(k => !clavesEn.includes(k))
    const faltanEnEspanol = clavesEn.filter(k => !clavesEs.includes(k))

    expect(
      { faltanEnIngles, faltanEnEspanol },
      `Descuadre entre catálogos.\n` +
      `  Faltan en en.yaml: ${faltanEnIngles.join(', ') || '—'}\n` +
      `  Faltan en es.yaml: ${faltanEnEspanol.join(', ') || '—'}`
    ).toEqual({ faltanEnIngles: [], faltanEnEspanol: [] })
  })

  it('toda clave usada como `t(\'…\')` existe en el catálogo', () => {
    const huerfanas = [...usos.entries()]
      .filter(([clave]) => !clavesEs.includes(clave))
      .map(([clave, fichero]) => `${clave}  (${fichero})`)

    expect(huerfanas, `Se usan claves que no están en el catálogo:\n  ${huerfanas.join('\n  ')}`)
      .toEqual([])
  })

  it('no hay claves en el catálogo que nadie use', () => {
    const usadas = new Set(usos.keys())
    const sobran = clavesEs.filter(k =>
      !usadas.has(k) &&
      !PENDIENTES_DE_USO.includes(k) &&
      !PREFIJOS_DINAMICOS.some(p => k.startsWith(p))
    )

    expect(sobran, `Claves en el catálogo que no usa nadie:\n  ${sobran.join('\n  ')}`)
      .toEqual([])
  })

  it('ningún bloque del catálogo está declarado dos veces', () => {
    // YAML se queda con la última y tira la anterior entera. Pasó el 2026-08-31
    // añadiendo un segundo bloque `lists:` al final del fichero.
    //
    // `@rollup/plugin-yaml` **sí** lo detecta, pero tumbando la carga del módulo:
    // la suite entera sale con «no tests» y sin nombrar el fichero ni la clave.
    // Esta comprobación no añade cobertura, añade el nombre — que es lo que
    // convierte diez minutos de búsqueda en uno.
    for (const [nombre, ruta] of Object.entries(CRUDOS)) {
      const bloques = readFileSync(ruta, 'utf8')
        .split('\n')
        .filter(l => /^[a-zA-Z]/.test(l) && l.includes(':'))
        .map(l => l.split(':')[0])

      const repetidos = bloques.filter((b, i) => bloques.indexOf(b) !== i)

      expect(repetidos, `${nombre} declara dos veces: ${repetidos.join(', ')}`).toEqual([])
    }
  })

  it('no hay cadenas de interfaz en el `<script>` fuera del catálogo', () => {
    const datos = new Set((lista.datosDelFormato ?? []).map(x => `${x.file}||${x.text}`))
    const sueltas = []

    for (const fichero of ficherosDe(SRC)) {
      const rel = relative(SRC, fichero)
      for (const { texto, monton } of cadenasDe(readFileSync(fichero, 'utf8'), fichero)) {
        if (monton !== 'interfaz') continue
        if (datos.has(`${rel}||${texto}`)) continue
        sueltas.push(`${rel}  «${texto.slice(0, 70)}»`)
      }
    }

    expect(
      sueltas,
      'Cadenas de interfaz sin sacar al catálogo:\n  ' + sueltas.join('\n  ') +
      '\n\nSácalas a `src/locales/*.yaml` y úsalas con `t()`. Si NO son interfaz, ' +
      'mira `tests/unit/i18n-strings.allowlist.json` → `_comoSeApaga`.'
    ).toEqual([])
  })

  it('la lista de deuda sigue vacía: es el candado, no una lista de pendientes', () => {
    // El M5 la vació. Volver a llenarla apagaría la comprobación de arriba para
    // esa cadena, que es justo lo que no puede pasar sin que se vea en el diff.
    expect(lista.pendientes).toEqual([])
  })

  it('la lista de pendientes de uso sigue vacía', () => {
    // El M4 la vació. Volver a llenarla es una decisión, no un efecto colateral:
    // una clave sin consumidor es una clave que sobra en el catálogo.
    expect(PENDIENTES_DE_USO).toEqual([])
  })
})
