// La barrera del catálogo de eventos (Plan «Catálogo de Eventos de Producto», M1).
//
// El riesgo que manda es que se cuele un dato personal en Augur, y de ahí no se borra por evento.
// Se para por construcción, en cuatro sitios:
//   1. El catálogo solo tiene tipos CERRADOS (enum, int, bool): un tipo abierto hace fallar esto.
//   2. track() valida contra el catálogo: lo que no casa no llega al SDK y avisa.
//   3. Todo track('…') de src/ usa un nombre declarado, y siempre con literal.
//   4. API_ACTIONS sigue al ActionRouter del backend: una action nueva no puede quedarse fuera.
//
// La quinta pieza —que nadie fuera de src/analytics/ importe @/augur/— ya vivía en
// analytics.spec.js (Plan «Consentimiento de Analítica») y se queda allí: no se duplica.
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { join, relative } from 'node:path'
import { createRouter, createMemoryHistory } from 'vue-router'

vi.mock('@/augur/augur.js', () => ({
  default: {
    configure: vi.fn(),
    setConsent: vi.fn(),
    hasConsent: vi.fn(() => false),
    trackRouter: vi.fn(),
    captureErrors: vi.fn(),
    track: vi.fn(),
    flush: vi.fn(() => Promise.resolve(true)),
  },
}))

import Augur from '@/augur/augur.js'
import { initAnalytics, track, checkEvent, _resetForTests } from '@/analytics'
import {
  CATALOG, PROP_DOCS, MEDIA, API_ACTIONS, ROUTE_NAMES, NOTE_TYPES,
} from '@/analytics/catalog'
import { mediaKeys, getMediaConfig } from '@/config/mediaRegistry'
import router from '@/router'

// Desde process.cwd(), como i18n.spec.js: en Vitest import.meta.url no siempre es file://.
const SRC = join(process.cwd(), 'src')
const ACTION_ROUTER = join(process.cwd(), '..', 'backend', 'src', 'Router', 'ActionRouter.php')

/** El patrón de nombres de Augur (`UpsertEventDef::NAME_PATTERN`) y el de sus propiedades. */
const EVENT_NAME = /^[A-Za-z0-9_.:-]{1,64}$/
const PROP_NAME = /^[A-Za-z0-9_]{1,64}$/

/** Por qué un tipo de prop NO es cerrado, o null. La regla del catálogo, escrita una vez. */
function openTypeProblem (type) {
  if (type === 'int' || type === 'bool') return null
  if (!type || typeof type !== 'object' || Array.isArray(type)) return `tipo abierto o desconocido: ${JSON.stringify(type)}`
  const keys = Object.keys(type)
  if (keys.length !== 1 || keys[0] !== 'enum') return `un enum solo lleva la clave enum: ${keys.join(',')}`
  if (!Array.isArray(type.enum) || type.enum.length === 0) return 'enum vacío'
  if (!type.enum.every((v) => typeof v === 'string' && v.length > 0)) return 'enum con valores que no son cadenas'
  if (new Set(type.enum).size !== type.enum.length) return 'enum con valores repetidos'
  return null
}

function filesOf (dir, acc = []) {
  for (const name of readdirSync(dir)) {
    const full = join(dir, name)
    if (statSync(full).isDirectory()) filesOf(full, acc)
    else if (/\.(js|mjs|cjs|vue)$/.test(name)) acc.push(full)
  }
  return acc
}

/** Mismo limpiador que i18n.spec.js: un ejemplo en un comentario no es un disparo. */
function withoutComments (code) {
  return code.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '')
}

/**
 * Las claves de los brazos del `match ($request['action'])` de ActionRouter.php. Un escáner
 * pequeño y no un regex por línea: salta cadenas y comentarios, lleva la profundidad de
 * paréntesis, y solo cuenta las cadenas que están a profundidad 0 justo antes de un `=>` (con
 * varias, `'a', 'b' =>`, cuentan todas). Así un `'filters' => …` dentro de un array de argumentos
 * no se confunde con una action.
 */
function routerActions (php) {
  const start = php.indexOf("match ($request['action'])")
  if (start < 0) throw new Error('No encuentro el match de actions en ActionRouter.php')
  let i = php.indexOf('{', start) + 1
  let depth = 0
  const tokens = []
  while (i < php.length) {
    const c = php[i]
    const next = php[i + 1]
    if (c === '/' && next === '/') { i = php.indexOf('\n', i); if (i < 0) break; continue }
    if (c === '#') { i = php.indexOf('\n', i); if (i < 0) break; continue }
    if (c === '/' && next === '*') { i = php.indexOf('*/', i) + 2; continue }
    if (c === "'" || c === '"') {
      let j = i + 1
      let text = ''
      while (j < php.length && php[j] !== c) {
        if (php[j] === '\\') { text += php[j + 1]; j += 2; continue }
        text += php[j]
        j++
      }
      if (depth === 0) tokens.push({ kind: 'str', text })
      i = j + 1
      continue
    }
    if (c === '(' || c === '[' || c === '{') { depth++; i++; continue }
    if (c === ')' || c === ']' || c === '}') {
      if (depth === 0) break   // la llave que cierra el match
      depth--
      i++
      continue
    }
    if (depth === 0 && c === '=' && next === '>') { tokens.push({ kind: 'arrow' }); i += 2; continue }
    if (depth === 0 && c === ',') { tokens.push({ kind: 'comma' }); i++; continue }
    if (depth === 0 && !/\s/.test(c)) {
      const last = tokens[tokens.length - 1]
      if (!last || last.kind !== 'other') tokens.push({ kind: 'other' })
    }
    i++
  }
  const actions = []
  tokens.forEach((tok, k) => {
    if (tok.kind !== 'arrow') return
    const keys = []
    let j = k - 1
    while (j >= 0 && tokens[j].kind === 'str') {
      keys.unshift(tokens[j].text)
      if (j - 1 >= 0 && tokens[j - 1].kind === 'comma' && j - 2 >= 0 && tokens[j - 2].kind === 'str') j -= 2
      else break
    }
    actions.push(...keys)
  })
  return actions
}

describe('el catálogo: solo tipos cerrados y nombres que Augur acepta', () => {
  it('la regla de tipos no es de adorno: rechaza los abiertos', () => {
    for (const bad of ['string', 'number', { type: 'string' }, { enum: [] }, { enum: [1, 2] },
      { enum: ['a', 'a'] }, { enum: ['a'], extra: true }, ['a'], null, undefined]) {
      expect(openTypeProblem(bad), JSON.stringify(bad)).not.toBeNull()
    }
    expect(openTypeProblem({ enum: ['a', 'b'] })).toBeNull()
  })

  it('cada evento: nombre válido, descripción, y todas sus props con tipo cerrado y documentado', () => {
    const problems = []
    for (const [name, def] of Object.entries(CATALOG)) {
      if (!EVENT_NAME.test(name)) problems.push(`${name}: nombre fuera del patrón de Augur`)
      if (!/^[a-z0-9_]+$/.test(name)) problems.push(`${name}: no es snake_case`)
      if (typeof def.description !== 'string' || !def.description || def.description.length > 255) {
        problems.push(`${name}: descripción vacía o de más de 255`)
      }
      if (!def.props || typeof def.props !== 'object' || Array.isArray(def.props)) {
        problems.push(`${name}: props tiene que ser un objeto`)
        continue
      }
      for (const [prop, type] of Object.entries(def.props)) {
        if (!PROP_NAME.test(prop)) problems.push(`${name}.${prop}: nombre de prop no válido`)
        const why = openTypeProblem(type)
        if (why) problems.push(`${name}.${prop}: ${why}`)
        if (!PROP_DOCS[prop]) problems.push(`${name}.${prop}: sin entrada en PROP_DOCS`)
      }
    }
    expect(problems).toEqual([])
  })

  it('no hay nada en PROP_DOCS que ningún evento use', () => {
    const used = new Set(Object.values(CATALOG).flatMap((d) => Object.keys(d.props)))
    expect(Object.keys(PROP_DOCS).filter((p) => !used.has(p))).toEqual([])
  })

  it('MEDIA son exactamente las claves del registry', () => {
    expect([...MEDIA].sort()).toEqual([...mediaKeys].sort())
  })

  it('ROUTE_NAMES son exactamente los name del router', () => {
    const names = router.getRoutes().map((r) => r.name).filter(Boolean)
    expect([...ROUTE_NAMES].sort()).toEqual([...new Set(names)].sort())
  })

  it('NOTE_TYPES cubre todos los tipos de nota del registry', () => {
    const registry = new Set(mediaKeys.flatMap((m) => (getMediaConfig(m).notes?.types ?? []).map((t) => t.value)))
    expect([...registry].filter((v) => !NOTE_TYPES.includes(v))).toEqual([])
  })
})

describe('track(): lo que no casa con el catálogo no se envía', () => {
  let warn

  beforeEach(() => {
    _resetForTests()
    vi.clearAllMocks()
    vi.stubEnv('VUE_APP_AUGUR_KEY', 'clave-de-prueba')
    Object.defineProperty(window, 'isSecureContext', { value: true, configurable: true })
    Object.defineProperty(navigator, 'locks', { value: { request: vi.fn() }, configurable: true })
    initAnalytics(createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: { render: () => null } }] }))
    warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
  })

  afterEach(() => {
    warn.mockRestore()
    vi.unstubAllEnvs()
  })

  it('un evento bien formado llega al SDK', () => {
    expect(track('library_item_added', { media: 'book', source: 'search' })).toBeUndefined()
    expect(Augur.track).toHaveBeenCalledWith('library_item_added', { media: 'book', source: 'search' })
    expect(warn).not.toHaveBeenCalled()
  })

  it('un evento sin props se manda con {} (el valor por defecto)', () => {
    track('club_created')
    expect(Augur.track).toHaveBeenCalledWith('club_created', {})
  })

  it.each([
    ['nombre no declarado', 'no_existe', {}],
    ['prop no declarada (p. ej. un título)', 'club_created', { title: 'Mi club' }],
    ['valor fuera del enum', 'library_item_added', { media: 'podcast', source: 'search' }],
    ['falta una prop declarada', 'library_item_added', { media: 'book' }],
    ['cadena donde va un entero', 'item_rated', { media: 'book', rating: '5' }],
    ['decimal donde va un entero', 'item_rated', { media: 'book', rating: 4.5 }],
    ['número donde va un booleano', 'logged_out', { forced: 1 }],
    ['props que no son un objeto', 'club_created', ['x']],
    ['props null', 'club_created', null],
  ])('%s → no envía y avisa', (_label, name, props) => {
    expect(track(name, props)).toBeUndefined()
    expect(Augur.track).not.toHaveBeenCalled()
    expect(warn).toHaveBeenCalledTimes(1)
    expect(warn.mock.calls[0][0]).toContain(`track('${name}')`)
  })

  it('el aviso nunca lleva el valor rechazado (podría ser justo el dato personal)', () => {
    track('club_created', { title: 'Club de Ana García' })
    expect(warn.mock.calls[0].join(' ')).not.toContain('Ana García')
  })

  it('valida aunque Augur no esté configurado: el aviso sale igual, y lo bueno no llama a nada', () => {
    _resetForTests()
    track('no_existe')
    expect(warn).toHaveBeenCalledTimes(1)
    track('club_created')
    expect(Augur.track).not.toHaveBeenCalled()
    expect(warn).toHaveBeenCalledTimes(1)
  })

  it('no lanza nunca, aunque el SDK lance', () => {
    Augur.track.mockImplementationOnce(() => { throw new Error('boom') })
    expect(() => track('club_created')).not.toThrow()
  })

  it('checkEvent acepta cada evento del catálogo con valores de su tipo', () => {
    const sample = (type) => (type === 'int' ? 0 : type === 'bool' ? false : type.enum[0])
    for (const [name, def] of Object.entries(CATALOG)) {
      const props = Object.fromEntries(Object.entries(def.props).map(([k, t]) => [k, sample(t)]))
      expect(checkEvent(name, props), name).toBeNull()
    }
  })
})

describe('los disparos de src/ usan nombres declarados', () => {
  it('todo track(…) fuera de analytics/ lleva un literal que está en el catálogo', () => {
    const problems = []
    // `track(` suelto, no `Augur.track(` ni `algo.track(`: solo el de analytics/.
    const call = /(^|[^\w.$])track\s*\(\s*([^,)]*)/g
    for (const file of filesOf(SRC)) {
      const rel = relative(SRC, file).split(/[\\/]/)
      if (rel[0] === 'augur' || rel[0] === 'analytics') continue
      const code = withoutComments(readFileSync(file, 'utf8'))
      for (const m of code.matchAll(call)) {
        const arg = m[2].trim()
        const lit = /^(['"`])([^'"`$]*)\1$/.exec(arg)
        if (!lit) problems.push(`${rel.join('/')}: track(${arg}) — el nombre tiene que ser un literal`)
        else if (!Object.prototype.hasOwnProperty.call(CATALOG, lit[2])) {
          problems.push(`${rel.join('/')}: track('${lit[2]}') no está en analytics/catalog.js`)
        }
      }
    }
    expect(problems).toEqual([])
  })
})

describe('API_ACTIONS sigue al ActionRouter del backend', () => {
  it('el escáner del router distingue actions de claves de argumentos', () => {
    const php = `return match ($request['action']) {
      // 'comentada' => nada,
      'a' => $c->a($data['x'] ?? 'y'),
      'b', 'c' => $c->b([
        'filters' => $data['filters'] ?? []
      ]),
      default => ['status' => 'error'],
    };`
    expect(routerActions(php)).toEqual(['a', 'b', 'c'])
  })

  // El contenedor `frontend` solo monta frontend/: el backend no está a la vista y este caso se
  // salta allí. En el host (`npx vitest run tests/unit/analytics-catalog.spec.js` desde frontend/)
  // sí corre. Saltarse NO es pasar: el aviso lo deja escrito en la salida.
  const visible = existsSync(ACTION_ROUTER)
  if (!visible) {
    // eslint-disable-next-line no-console
    console.warn(`[analytics-catalog.spec] ${ACTION_ROUTER} no es visible: se salta la comparación con API_ACTIONS`)
  }

  it.skipIf(!visible)('toda action del router está en API_ACTIONS, y ninguna sobra', () => {
    const actions = routerActions(readFileSync(ACTION_ROUTER, 'utf8'))
    expect(actions.length).toBeGreaterThan(100)
    expect(actions.filter((a) => !API_ACTIONS.includes(a)), 'actions del router sin declarar').toEqual([])
    expect(API_ACTIONS.filter((a) => !actions.includes(a)), 'API_ACTIONS que el router ya no tiene').toEqual([])
  })

  it('API_ACTIONS no tiene repetidas', () => {
    expect(new Set(API_ACTIONS).size).toBe(API_ACTIONS.length)
  })
})
