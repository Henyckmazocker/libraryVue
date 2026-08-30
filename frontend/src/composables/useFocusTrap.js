import { watch, nextTick, onBeforeUnmount } from 'vue'

// Lo que el navegador considera enfocable y nos sirve como parada de tabulación.
// `[tabindex]:not([tabindex="-1"])` cubre lo que se hace enfocable a mano.
const FOCUSABLE = [
  'a[href]',
  'button',
  'input',
  'select',
  'textarea',
  '[tabindex]:not([tabindex="-1"])'
].map(s => `${s}:not([disabled]):not([aria-hidden="true"])`).join(', ')

// Los paneles que PrimeVue teletransporta al `body`. Con uno abierto, el Escape es
// suyo: lo que el usuario está cerrando es el desplegable, no el modal entero.
const PRIMEVUE_OVERLAYS = [
  '.p-multiselect-overlay', '.p-multiselect-panel',
  '.p-dropdown-overlay', '.p-dropdown-panel',
  '.p-select-overlay',
  '.p-autocomplete-overlay', '.p-autocomplete-panel',
  '.p-datepicker-panel'
].join(', ')

/**
 * Atrapa el foco dentro de un contenedor mientras está abierto.
 *
 * Al abrir guarda quién tenía el foco y enfoca el primer elemento de dentro;
 * mientras está abierto, Tab y Shift+Tab ciclan sin salirse y Escape llama a
 * `onEscape`; al cerrar devuelve el foco a quien lo tenía.
 *
 * No sirve para los `<Dialog>` de PrimeVue: teleportan su contenido a `<body>`,
 * así que el contenedor que ve el componente no es donde vive el marcado. Esos
 * ya traen su propio trap.
 *
 * @param {import('vue').Ref<HTMLElement|null>} containerRef - raíz del modal
 * @param {object} options
 * @param {import('vue').Ref<boolean>|(() => boolean)} options.isOpen - si está abierto
 * @param {function} [options.onEscape] - qué hacer al pulsar Escape
 */
export function useFocusTrap (containerRef, { isOpen, onEscape } = {}) {
  let previouslyFocused = null
  let activo = false

  // `offsetParent !== null` sería más corto, pero da null para cualquier hijo de
  // un contenedor `position: fixed` —que es lo que son los overlays de este
  // proyecto— y en jsdom da null siempre, así que los tests no verían nada.
  const isVisible = (el) => {
    const style = getComputedStyle(el)
    return style.display !== 'none' && style.visibility !== 'hidden'
  }

  const focusablesOf = (root) => [...root.querySelectorAll(FOCUSABLE)].filter(isVisible)

  function onKeydown (event) {
    const root = containerRef.value
    if (!root) return

    if (event.key === 'Escape') {
      // Este listener va en fase de CAPTURA sobre `document`, así que llega antes
      // que el de PrimeVue: sin esta guarda, un Escape con el desplegable abierto
      // cerraba el panel y el modal a la vez y se perdía el formulario entero.
      // Medido el 2026-08-29 en `EditItemModal` con el `MultiSelect` de
      // `StatusSelector`. El segundo Escape, ya sin panel, sí cierra el modal.
      if (document.querySelector(PRIMEVUE_OVERLAYS)) return
      onEscape?.()
      return
    }

    if (event.key !== 'Tab') return

    const focusables = focusablesOf(root)
    // Sin nada enfocable dentro, el foco se queda en el contenedor y no se escapa.
    if (focusables.length === 0) {
      event.preventDefault()
      root.focus?.()
      return
    }

    const first = focusables[0]
    const last = focusables[focusables.length - 1]
    const active = document.activeElement

    if (event.shiftKey && (active === first || !root.contains(active))) {
      event.preventDefault()
      last.focus()
    } else if (!event.shiftKey && (active === last || !root.contains(active))) {
      event.preventDefault()
      first.focus()
    }
  }

  async function activate () {
    previouslyFocused = document.activeElement
    activo = true
    document.addEventListener('keydown', onKeydown, true)
    await nextTick()

    const root = containerRef.value
    if (!root) return
    const focusables = focusablesOf(root)
    ;(focusables[0] ?? root).focus?.()
  }

  function deactivate () {
    activo = false
    document.removeEventListener('keydown', onKeydown, true)
    // Devolver el foco al control que abrió el modal es lo que evita que quien
    // navega con teclado acabe al principio de la página cada vez que cierra.
    previouslyFocused?.focus?.()
    previouslyFocused = null
  }

  watch(
    () => (typeof isOpen === 'function' ? isOpen() : isOpen?.value),
    (open, wasOpen) => {
      if (open) activate()
      else if (wasOpen) deactivate()
    },
    { immediate: true }
  )

  // Desmontarse cuenta como cerrarse. `EditItemModal` es el caso real: su
  // consumidor lo monta con `v-if` (`views/shared/MediaDetailView.vue:82`), así
  // que al cerrar el componente desaparece y el `watch` de arriba ya no llega a
  // correr — sin esto, Escape cerraba el modal y dejaba el foco en el `<body>`.
  onBeforeUnmount(() => {
    if (activo) deactivate()
    else document.removeEventListener('keydown', onKeydown, true)
  })

  return { activate, deactivate }
}
