// Toda vista que monta el router tiene que tener UN solo nodo raíz en su <template>, sin
// comentarios. App.vue envuelve el <router-view> en <Transition mode="out-in">: con un comentario
// o dos nodos en la raíz la vista es un Fragment, Vue cuelga la transición del elemento pero
// desmonta el Fragment sin llamar a afterLeave, y el `out-in` se queda esperando para siempre. El
// centro sale en blanco en cada navegación posterior, sin un solo error en consola. En dev los
// comentarios se conservan (en el build de prod no), así que solo se ve en desarrollo.
// Pasó el 2026-09-29 con PrivacyView.vue (Plan «Consentimiento de Analítica», M3).
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'
import { join, resolve } from 'node:path'
import { parse } from '@vue/compiler-sfc'

const SRC = resolve(__dirname, '../../src')
const routerSource = readFileSync(join(SRC, 'router/index.js'), 'utf8')

// Los componentes de ruta se cargan como '../views/X.vue' o '../components/X.vue'.
const routeComponents = [...new Set(
  [...routerSource.matchAll(/'\.\.\/([^']+\.vue)'/g)].map(m => m[1])
)]

// NodeTypes de @vue/compiler-core: 1 = ELEMENT, 3 = COMMENT, 2 = TEXT.
const rootNodes = (file) => {
  const { descriptor } = parse(readFileSync(join(SRC, file), 'utf8'), { filename: file })
  return descriptor.template.ast.children.filter(n => !(n.type === 2 && !n.content.trim()))
}

describe('vistas del router: un solo nodo raíz', () => {
  it('encuentra los componentes de ruta', () => {
    expect(routeComponents.length).toBeGreaterThan(10)
  })

  it.each(routeComponents)('%s tiene un único elemento raíz y ningún comentario', (file) => {
    const roots = rootNodes(file)
    const kinds = roots.map(n => (n.type === 1 ? `<${n.tag}>` : n.type === 3 ? 'comentario' : `tipo ${n.type}`))
    expect(kinds).toHaveLength(1)
    expect(roots[0].type).toBe(1)
  })
})
