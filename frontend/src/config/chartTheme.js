/**
 * chartTheme.js — el color de las gráficas, leído del sistema de tokens.
 *
 * Antes cada gráfica sacaba su color de `StatsService.generateColors()`, una lista
 * de seis valores de demo de Chart.js **que se repetía** a partir de la séptima
 * serie y que asignaba por posición: al filtrar, las series supervivientes
 * cambiaban de color. Aquí el color lo pone el tema y se asigna por identidad.
 *
 * Todo se resuelve contra `document.documentElement` en tiempo de llamada, así que
 * el resultado ya viene bien tanto en `:root` como en `.app-dark`.
 *
 * **Por qué hay una señal reactiva y no un simple `var(--…)`**: Chart.js pinta sobre
 * un `<canvas>`, y ahí un `var()` no significa nada — el color hay que resolverlo a
 * un valor concreto en el momento de pintar. Así que cada función de este módulo
 * toca `themeVersion`, que sube cuando `store/ui.js` conmuta el tema. Cualquier
 * `computed` que llame a estas funciones queda suscrito y se recalcula solo: el
 * dashboard repinta sin recargar y sin que ningún componente tenga que enterarse.
 */
import { ref } from 'vue'

const themeVersion = ref(0)
let observing = false

function watchTheme () {
  if (observing || typeof MutationObserver === 'undefined') return
  observing = true
  new MutationObserver(() => { themeVersion.value++ })
    .observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] })
}

const cssVar = (name, fallback = '') => {
  watchTheme()
  themeVersion.value // eslint-disable-line no-unused-expressions -- suscripción al cambio de tema
  if (typeof document === 'undefined') return fallback
  const v = getComputedStyle(document.documentElement).getPropertyValue(name)
  return v.trim() || fallback
}

/**
 * Los cinco medios, con el MISMO color que su tarjeta en /library.
 * Acepta singular o plural ('game' y 'games'), porque el dashboard usa el plural
 * y los tokens del registry el singular.
 */
export function entityColor (media) {
  const key = String(media).replace(/s$/, '')
  return cssVar(`--color-card-${key}-accent`, cssVar('--chart-1'))
}

/**
 * Paleta categórica de propósito general (géneros, estados, plataformas…).
 *
 * Orden fijo: la serie N recibe siempre el color N, así que filtrar no recolorea
 * a las demás. Ocho ranuras; a partir de la novena categoría, quien llame debe
 * agrupar el resto en «Otros» —`foldToOther()` lo hace— en vez de inventar tonos:
 * generar por ángulo áureo, que es lo que hacía `generateColors`, no garantiza
 * ninguna separación.
 */
const SLOT_COUNT = 7

export function categoricalPalette (n = SLOT_COUNT) {
  const slots = []
  for (let i = 1; i <= Math.min(n, SLOT_COUNT); i++) slots.push(cssVar(`--chart-${i}`))
  if (n > SLOT_COUNT) slots.push(cssVar('--chart-other'))
  return slots
}

/**
 * Agrupa la cola de una serie categórica en «Otros».
 * Devuelve `{ labels, data }` listos para Chart.js, con como mucho 8 entradas.
 */
export function foldToOther (labels, data, max = SLOT_COUNT) {
  if (labels.length <= max) return { labels: [...labels], data: [...data] }
  const head = labels.slice(0, max)
  const tail = data.slice(max).reduce((a, b) => a + b, 0)
  return { labels: [...head, 'Otros'], data: [...data.slice(0, max), tail] }
}

/** Tinta de ejes, rejilla y texto. Recesiva, como manda la guía de dataviz. */
export function chartInk () {
  return {
    text: cssVar('--color-text-secondary'),
    muted: cssVar('--color-text-muted'),
    grid: cssVar('--color-border-light'),
    axis: cssVar('--color-border')
  }
}

/** Fondo, borde y tintas del tooltip, según el tema activo. */
export function chartTooltip () {
  return {
    backgroundColor: cssVar('--color-background-card'),
    titleColor: cssVar('--color-text'),
    bodyColor: cssVar('--color-text-secondary'),
    borderColor: cssVar('--color-border'),
    borderWidth: 1
  }
}

/**
 * Contador que sube en cada cambio de tema. Expuesto por si algo necesita
 * suscribirse a mano (un `watch`) en vez de a través de un `computed`.
 */
export { themeVersion }

export default { entityColor, categoricalPalette, foldToOther, chartInk, chartTooltip, themeVersion }
