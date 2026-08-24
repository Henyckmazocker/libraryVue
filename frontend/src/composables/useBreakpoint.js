/**
 * useBreakpoint — el umbral de móvil, en un solo sitio.
 *
 * Antes vivía duplicado en `Layout.vue` y `MobileNavBar.vue`: dos `ref(window.innerWidth)`,
 * dos listeners de `resize` y dos veces el literal `768`, que además ya existía como
 * `bp('md')` en `assets/styles/abstracts/_breakpoints.scss`. Cada componente que quisiera
 * saber si está en móvil añadía un listener más.
 *
 * Aquí el estado es de módulo y el listener es **uno solo**, compartido por todos los
 * consumidores y montado de forma perezosa la primera vez que alguien pregunta. No hace
 * falta desmontarlo: vive lo que la pestaña.
 */
import { ref, computed, readonly } from 'vue'
import { Capacitor } from '@capacitor/core'

// Espejo de bp('md') en _breakpoints.scss. Si cambia allí, cambia aquí: son el mismo
// umbral visto desde CSS y desde JS, y el sistema no tiene puente entre los dos.
export const MOBILE_BREAKPOINT = 768

const width = ref(typeof window === 'undefined' ? MOBILE_BREAKPOINT + 1 : window.innerWidth)
let listening = false

function listen () {
  if (listening || typeof window === 'undefined') return
  listening = true
  window.addEventListener('resize', () => { width.value = window.innerWidth })
}

export function useBreakpoint () {
  listen()
  // `< MOBILE_BREAKPOINT`, no `<=`: `responsive-below(md)` compila a
  // `max-width: 767px`, así que a 768px exactos el CSS ya considera escritorio. El
  // código anterior usaba `<= 768` y discrepaba con `_visibility.scss` justo en ese
  // píxel — la clase `.u-hidden-mobile` se ocultaba mientras el JS decía «móvil».
  const isMobile = computed(() => width.value < MOBILE_BREAKPOINT)
  // Lo que decide si se pinta la barra inferior: móvil por tamaño **o** app nativa,
  // porque en Capacitor la ventana puede ser ancha (tablet) y seguir siendo la app.
  const isNativeOrMobile = computed(() => Capacitor.isNativePlatform() || isMobile.value)
  return { width: readonly(width), isMobile, isNativeOrMobile }
}

export default useBreakpoint
