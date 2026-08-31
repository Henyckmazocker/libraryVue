/**
 * i18n.js — el motor de traducción, sin dependencias de producción.
 *
 * **Por qué composable propio y no `vue-i18n`**: la app pide plurales en ocho
 * sitios y los dos idiomas —español e inglés— tienen ambos dos formas
 * (`one`/`other`), así que las reglas CLDR de `vue-i18n` no compran nada aquí. Y
 * el repo ya tiene cultura de composable propio: `createMediaStore`,
 * `createMediaComposable`, `chartTheme`, `useFocusTrap`.
 *
 * **Por qué el catálogo es un `ref` y no un objeto suelto**: `t()` lo lee dentro,
 * así que cualquier plantilla que llame a `t()` queda suscrita y se repinta sola
 * al cambiar de idioma — sin recargar y sin que ningún componente se entere. Es
 * el mismo truco que usa `config/chartTheme.js` con `themeVersion`.
 *
 * **Por qué se carga con `import()` y antes de montar**: así solo viaja al
 * navegador el idioma en uso, y a partir del arranque `t()` es **síncrono**. Sin
 * eso habría que envolver cada plantilla en un `v-if="ready"`.
 *
 * El contrato del API no cambia: los slugs de estado y los códigos de idioma
 * siguen viajando en inglés. Traducir es cosa del cliente.
 */
import { ref, readonly } from 'vue'
import Logger from '@/utils/logger'

/** Los idiomas que existen. Añadir uno es un `.yaml` más y una entrada aquí. */
export const availableLocales = [
  { code: 'es', native: 'Español', bcp47: 'es-ES' },
  { code: 'en', native: 'English', bcp47: 'en-GB' }
]

const CODES = availableLocales.map(l => l.code)
const FALLBACK = 'es'
const STORAGE_KEY = 'locale'

const catalog = ref({})
const current = ref(FALLBACK)

/** Solo lectura desde fuera: el idioma se cambia con `setLocale`, no asignando. */
export const locale = readonly(current)

/**
 * La etiqueta BCP-47 del idioma activo, para `toLocaleDateString`,
 * `toLocaleTimeString` y `toLocaleString`.
 *
 * **No es el código del catálogo.** `Intl` quiere una etiqueta de idioma con
 * región —`es-ES`, `en-GB`—, y pasarle `'es'` a secas funciona por casualidad
 * pero deja el formato a merced de la implementación. Se declara junto al idioma
 * en `availableLocales` en vez de componerla, que es donde se mira al añadir uno.
 *
 * Se lee `current.value`, así que quien la use dentro de un `computed` se repinta
 * al cambiar de idioma, como todo lo demás.
 */
export function intlLocale () {
  return availableLocales.find(l => l.code === current.value)?.bcp47 ?? 'es-ES'
}

/**
 * Las claves que ya se han avisado. Sin esto, una clave que falta en una lista
 * de cien filas deja cien líneas idénticas en el log y en el buffer que `Logger`
 * manda al backend.
 */
const avisadas = new Set()

/**
 * La cascada de elección, en el orden en que puede fallar:
 *   1. lo que el usuario eligió y persistió
 *   2. el idioma del navegador —en Capacitor, la WebView devuelve el del sistema
 *      por `navigator.language`, así que no hace falta `@capacitor/device`—
 *   3. español
 */
export function detectLocale () {
  try {
    const guardado = localStorage.getItem(STORAGE_KEY)
    if (CODES.includes(guardado)) return guardado
  } catch {
    // Modo privado o almacenamiento bloqueado: se sigue por el navegador.
  }

  // `window` y no `globalThis`: es el idiom del repo (`utils/logger.js` guarda
  // igual) y `globalThis` no está en el `env` de ESLint de este proyecto.
  const navegador = (typeof window === 'undefined' ? '' : window.navigator?.language || '').slice(0, 2)
  if (CODES.includes(navegador)) return navegador

  return FALLBACK
}

/** Carga un catálogo y lo activa. El `import()` es lo que parte el bundle. */
async function cargar (code) {
  const mod = code === 'en'
    ? await import('@/locales/en.yaml')
    : await import('@/locales/es.yaml')

  // `import()` de un módulo con `export default` devuelve `{ default: … }`; el
  // loader de YAML puede dar una cosa u otra según la cadena, así que se acepta
  // las dos formas en vez de fiarse de una.
  catalog.value = mod.default ?? mod
  current.value = code
  avisadas.clear()
}

/**
 * Arranca el motor con el idioma que toque. Se llama **antes** de `app.mount()`.
 */
export async function initI18n () {
  await cargar(detectLocale())
  return current.value
}

/** Cambia de idioma, lo persiste y repinta la interfaz sin recargar. */
export async function setLocale (code) {
  if (!CODES.includes(code)) {
    Logger.warn(`[i18n] Idioma desconocido: ${code}. Se ignora.`)
    return current.value
  }
  // Y no solo `code === current.value`: al arrancar, `current` ya vale 'es' con el
  // catálogo VACÍO, así que mirar solo el código convertía `setLocale('es')` en un
  // no-op que dejaba la app pintando claves. Lo destapó un test de componente.
  if (code === current.value && Object.keys(catalog.value).length > 0) return code

  await cargar(code)

  try {
    localStorage.setItem(STORAGE_KEY, code)
  } catch {
    // Que no se pueda persistir no debe impedir cambiar de idioma ahora.
  }

  return code
}

/** Baja por la clave con puntos. Devuelve `undefined` si algo del camino falta. */
function buscar (key) {
  return key.split('.').reduce((nodo, parte) => nodo?.[parte], catalog.value)
}

/** `{n}` y `{what}` — llave simple, sin escape ni expresiones. */
function interpolar (texto, params) {
  if (!params) return texto
  return texto.replace(/\{(\w+)\}/g, (crudo, nombre) =>
    Object.prototype.hasOwnProperty.call(params, nombre) ? String(params[nombre]) : crudo
  )
}

/**
 * Traduce una clave.
 *
 *   t('library.title')                      → 'Mi biblioteca'
 *   t('common.loading', { what: 'Matrix' }) → 'Cargando Matrix…'
 *   t('library.itemCount', { n: 3 })        → '3 ítems'
 *
 * Una clave que no existe **devuelve la clave misma** y avisa una vez. Nunca una
 * pantalla en blanco: una traducción que falta se ve, pero no rompe nada.
 */
export function t (key, params) {
  const valor = buscar(key)

  if (valor === undefined || valor === null) {
    if (!avisadas.has(key)) {
      avisadas.add(key)
      Logger.warn(`[i18n] Falta la clave «${key}» en el catálogo «${current.value}»`)
    }
    return key
  }

  // Plural de dos formas, que es lo que piden español e inglés. Se elige por `n`
  // y `n` sigue disponible para interpolarlo dentro del texto elegido.
  if (typeof valor === 'object') {
    if (params && typeof params.n === 'number') {
      return interpolar(params.n === 1 ? (valor.one ?? valor.other) : (valor.other ?? valor.one), params)
    }
    // Una clave que apunta a una rama del catálogo y no a un texto: es un error
    // de quien la escribió, y se trata como una clave que falta.
    if (!avisadas.has(key)) {
      avisadas.add(key)
      Logger.warn(`[i18n] La clave «${key}» apunta a una rama, no a un texto`)
    }
    return key
  }

  return interpolar(String(valor), params)
}

/**
 * La etiqueta de un estado de la biblioteca. Existe para no repetir dos matices en
 * los cuatro sitios que pintan estados —el selector, la fila de la biblioteca, las
 * leyendas del dashboard y la tarjeta del feed—:
 *
 *   1. **La forma.** Cuatro de los cinco medios devuelven cadenas planas y vídeos
 *      devuelve `[{id, name}]`. Se toleran las dos; arreglar el backend para que
 *      devuelva lo mismo es otro plan.
 *   2. **La caída.** Un slug que el catálogo no conoce sale **tal cual** y no como
 *      `status.loquesea`: el backend puede añadir estados y la pantalla no tiene
 *      por qué afearse por eso. El aviso ya lo dejó `t()`.
 */
export function statusLabel (entrada) {
  const slug = typeof entrada === 'string' ? entrada : entrada?.name ?? entrada?.id
  if (!slug) return ''
  const clave = `status.${slug}`
  const traducido = t(clave)
  return traducido === clave ? slug : traducido
}
