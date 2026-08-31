import { describe, it, expect } from 'vitest'
import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import es from '@/locales/es.yaml'
import en from '@/locales/en.yaml'

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
  'book.subjectGroups.' // t(`book.subjectGroups.${vocabulario}`) de OpenLibrary
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

const clavesEs = claves(es).sort()
const clavesEn = claves(en).sort()

const usos = new Map()
for (const fichero of ficherosDe(SRC)) {
  for (const clave of usosEn(readFileSync(fichero, 'utf8'))) {
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

  it('la lista de pendientes de uso sigue vacía', () => {
    // El M4 la vació. Volver a llenarla es una decisión, no un efecto colateral:
    // una clave sin consumidor es una clave que sobra en el catálogo.
    expect(PENDIENTES_DE_USO).toEqual([])
  })
})
