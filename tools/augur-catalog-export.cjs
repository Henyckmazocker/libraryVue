#!/usr/bin/env node
// Exporta frontend/src/analytics/catalog.js al formato de `catalog.upsert` de Augur (Plan «Catálogo
// de Eventos de Producto», M1). Lo usa tools/augur-catalog.sh; no habla con la red.
//
// Salida (stdout): un array JSON de { name, description, properties }, con
//   properties = { prop: { type: 'string' | 'number' | 'bool', description } }
// que es lo que valida `UpsertEventDef` en Augur. La traducción de tipos:
//   { enum: [...] } → 'string'   (la description lleva los valores admitidos)
//   'int'           → 'number'
//   'bool'          → 'bool'
// Cualquier otro tipo aborta: el catálogo no admite tipos abiertos y el export tampoco.
//
// Uso: node tools/augur-catalog-export.cjs [ruta/a/catalog.js]
// .cjs porque el package.json del frontend no es `type: module`, y el catálogo sí es un módulo
// ES: se carga con import() dinámico, que Node acepta desde CommonJS.

const path = require('node:path')
const { pathToFileURL } = require('node:url')

const MAX_DESC = 255   // `UpsertEventDef`: descripción del evento y de cada propiedad

function clip (text) {
  return text.length <= MAX_DESC ? text : text.slice(0, MAX_DESC - 1) + '…'
}

function propSpec (event, prop, type, docs) {
  const doc = docs[prop]
  if (!doc) throw new Error(`${event}.${prop}: sin entrada en PROP_DOCS`)
  if (type === 'int') return { type: 'number', description: clip(`${doc} (entero)`) }
  if (type === 'bool') return { type: 'bool', description: clip(doc) }
  if (type && Array.isArray(type.enum) && type.enum.length > 0) {
    const values = type.enum.join(', ')
    const text = `${doc}. Uno de: ${values}`
    // Un enum largo (las ~190 actions del API) no cabe: se dice cuántos y de dónde salen.
    return {
      type: 'string',
      description: text.length <= MAX_DESC
        ? text
        : clip(`${doc}. Uno de ${type.enum.length} valores cerrados (ver analytics/catalog.js)`),
    }
  }
  throw new Error(`${event}.${prop}: tipo abierto o desconocido (${JSON.stringify(type)})`)
}

async function main () {
  const file = path.resolve(process.argv[2] ||
    path.join(__dirname, '..', 'frontend', 'src', 'analytics', 'catalog.js'))
  const { CATALOG, PROP_DOCS } = await import(pathToFileURL(file).href)
  if (!CATALOG || !PROP_DOCS) throw new Error(`${file} no exporta CATALOG y PROP_DOCS`)

  const out = Object.entries(CATALOG).map(([name, def]) => ({
    name,
    description: clip(def.description),
    properties: Object.fromEntries(
      Object.entries(def.props).map(([prop, type]) => [prop, propSpec(name, prop, type, PROP_DOCS)])
    ),
  }))
  process.stdout.write(JSON.stringify(out, null, 2) + '\n')
}

main().catch((e) => {
  process.stderr.write(`augur-catalog-export: ${e.message}\n`)
  process.exit(1)
})
