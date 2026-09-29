// Sello de versión del SDK web de Augur. Lo carga NODE en build (vue.config.js o vite.config.js),
// nunca el bundle: por eso es CommonJS y no se importa desde augur.js.
//
//   clientVersion(root) → "version" de <root>/package.json; si falta → "0.0.0-dev"
//   buildId(root)       → SHA-256 de ruta + contenido de cada fichero bajo src/ y public/, más
//                         package-lock.json, en orden alfabético, SIN src/augur/ → 16 hex
//
// src/augur/ queda fuera para que actualizar el SDK (que ya viaja en el campo "sdk") no cambie el
// sello de la app. No sale de git HEAD: se trabaja con ficheros sin commitear.

'use strict'

const crypto = require('crypto')
const fs = require('fs')
const path = require('path')

const DEV_VERSION = '0.0.0-dev'
const HASHED_DIRS = ['src', 'public']
const HASHED_FILES = ['package-lock.json']
const EXCLUDED_PREFIX = 'src/augur/'

/**
 * Recorre `dir` y devuelve las rutas de sus ficheros. Los enlaces simbólicos no se siguen.
 * @param {string} dir
 * @param {string[]} out
 * @returns {string[]}
 */
function walk (dir, out) {
  if (!fs.existsSync(dir)) return out
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) walk(full, out)
    else if (entry.isFile()) out.push(full)
  }
  return out
}

/**
 * La "version" de <root>/package.json, o "0.0.0-dev" si no hay fichero, no es JSON o no la trae.
 * @param {string} root
 * @returns {string}
 */
function clientVersion (root) {
  try {
    const pkg = JSON.parse(fs.readFileSync(path.join(root, 'package.json'), 'utf8'))
    return typeof pkg.version === 'string' && pkg.version.trim() !== '' ? pkg.version.trim() : DEV_VERSION
  } catch {
    return DEV_VERSION
  }
}

/**
 * Las rutas relativas (con "/") que entran en el hash, ya ordenadas.
 * @param {string} root
 * @returns {string[]}
 */
function hashedFiles (root) {
  const files = []
  for (const dir of HASHED_DIRS) walk(path.join(root, dir), files)
  for (const name of HASHED_FILES) {
    const full = path.join(root, name)
    if (fs.existsSync(full) && fs.statSync(full).isFile()) files.push(full)
  }
  return files
    .map((f) => path.relative(root, f).split(path.sep).join('/'))
    .filter((f) => !f.startsWith(EXCLUDED_PREFIX))
    .sort()
}

/**
 * SHA-256 de ruta + contenido de cada fichero de hashedFiles(), cortado a 16 hex.
 * @param {string} root
 * @returns {string}
 */
function buildId (root) {
  const hash = crypto.createHash('sha256')
  for (const rel of hashedFiles(root)) {
    hash.update(rel).update('\0').update(fs.readFileSync(path.join(root, rel))).update('\0')
  }
  return hash.digest('hex').slice(0, 16)
}

module.exports = { clientVersion, buildId, hashedFiles, DEV_VERSION }
