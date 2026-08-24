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
