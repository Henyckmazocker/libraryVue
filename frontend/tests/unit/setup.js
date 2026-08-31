import { vi } from 'vitest'

// jsdom no implementa matchMedia y el Select de PrimeVue lo usa al montar
// (node_modules/src/select/Select.vue:833). Sin esto ningún componente con un
// Dropdown se puede montar en los tests.
if (!window.matchMedia) {
  window.matchMedia = vi.fn().mockImplementation((query) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn()
  }))
}

// El catálogo de idioma, cargado una vez antes de cualquier test, igual que
// `main.js` lo carga antes de `app.mount()`. Sin esto, todo componente que llame a
// `t()` vería el catálogo vacío y pintaría claves en vez de texto — y los tests
// pasarían o fallarían por una razón que no es la suya.
import { setLocale } from '@/config/i18n'
await setLocale('es')
