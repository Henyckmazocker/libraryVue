// Almacenamiento del SDK web de Augur.
//
//   localStorage
//     augur:state              {"install_id": "<uuid4>", "consent": true|false}   ausente = sin decisión
//     augur:s:<session_uuid>   {"h": {session_id, client_version, build_id, platform, locale,
//                                     context, started_at},
//                               "started_wall_ms", "next_seq", "acked", "last_ts",
//                               "last_elapsed_ms", "last_wall_ms", "ended_at": null | "<iso>",
//                               "events": [{seq, name, ts, elapsed_ms, props}, …]}  ← solo los NO subidos
//   sessionStorage
//     augur:tab                {"session_id": "<uuid>"}
//
// 🔴 Nada se cachea en memoria: cada operación lee, modifica y escribe sobre el storage, porque
// otras pestañas del mismo origen comparten localStorage y pueden haber tocado un registro.

export const PREFIX = 'augur:'
export const STATE_KEY = 'augur:state'
export const SESSION_PREFIX = 'augur:s:'
export const TAB_KEY = 'augur:tab'
export const CAP_BYTES = 1024 * 1024

/**
 * @typedef {object} SessionHeader
 * @property {string} session_id
 * @property {string} client_version
 * @property {string|null} build_id
 * @property {string} platform
 * @property {string|null} locale
 * @property {{screen: string, viewport: string}} context
 * @property {string} started_at
 */

/**
 * @typedef {object} StoredEvent
 * @property {number} seq
 * @property {string} name
 * @property {string} ts
 * @property {number} elapsed_ms
 * @property {object} props
 */

/**
 * @typedef {object} SessionRecord
 * @property {SessionHeader} h
 * @property {number} started_wall_ms
 * @property {number} next_seq
 * @property {number} acked            último seq aceptado por el servidor; -1 = nada
 * @property {string} last_ts
 * @property {number} last_elapsed_ms
 * @property {number} last_wall_ms
 * @property {string|null} ended_at
 * @property {StoredEvent[]} events
 * @property {Object<string, number>} [capped]   eventos con tope por sesión ya emitidos (error)
 */

/**
 * @typedef {object} AugurState
 * @property {string} install_id
 * @property {boolean} consent
 */

/**
 * ¿Es un error de cuota llena? Cada navegador lo nombra a su manera.
 * @param {unknown} err
 * @returns {boolean}
 */
export function isQuotaError (err) {
  if (!err || typeof err !== 'object') return false
  const e = /** @type {{name?: string, code?: number}} */ (err)
  return e.name === 'QuotaExceededError' || e.name === 'NS_ERROR_DOM_QUOTA_REACHED' ||
    e.code === 22 || e.code === 1014
}

/**
 * JSON.parse que devuelve null en vez de lanzar (un registro a medias o tocado a mano).
 * @param {string|null} text
 * @returns {any}
 */
function parse (text) {
  if (text == null) return null
  try {
    return JSON.parse(text)
  } catch {
    return null
  }
}

/**
 * @param {Storage} local  localStorage (compartido entre pestañas)
 * @param {Storage} tab    sessionStorage (uno por pestaña)
 * @param {number} [capBytes]
 */
export function createStore (local, tab, capBytes = CAP_BYTES) {
  /** Todas las claves augur:* de localStorage. Se copian antes de borrar nada. */
  function augurKeys () {
    const keys = []
    for (let i = 0; i < local.length; i++) {
      const k = local.key(i)
      if (k != null && k.startsWith(PREFIX)) keys.push(k)
    }
    return keys
  }

  /** @returns {string[]} ids de las sesiones guardadas */
  function sessionIds () {
    return augurKeys().filter((k) => k.startsWith(SESSION_PREFIX)).map((k) => k.slice(SESSION_PREFIX.length))
  }

  /** Suma de la longitud de clave + valor de cada augur:* de localStorage. */
  function size () {
    let total = 0
    for (const k of augurKeys()) total += k.length + (local.getItem(k) ?? '').length
    return total
  }

  /**
   * Tope sobre las augur:*: borra sesiones ENTERAS, de la más vieja a la más nueva por
   * started_at, hasta quedar en `maxBytes` o menos. `keepId` (la de esta pestaña) no se borra
   * nunca. Borrar eventos sueltos dejaría huecos de seq que parecerían pérdidas del servidor.
   * @param {string|null} keepId
   * @param {number} [maxBytes]
   * @returns {string[]} ids borrados
   */
  function enforceCap (keepId, maxBytes = capBytes) {
    let total = size()
    /** @type {string[]} */
    const deleted = []
    if (total <= maxBytes) return deleted
    const candidates = []
    for (const id of sessionIds()) {
      if (id === keepId) continue
      const key = SESSION_PREFIX + id
      const raw = local.getItem(key) ?? ''
      const rec = parse(raw)
      // Registro ilegible → started_at "" → se considera el más viejo.
      const startedAt = rec && rec.h && typeof rec.h.started_at === 'string' ? rec.h.started_at : ''
      candidates.push({ id, bytes: key.length + raw.length, startedAt })
    }
    candidates.sort((a, b) => (a.startedAt === b.startedAt ? (a.id < b.id ? -1 : 1) : (a.startedAt < b.startedAt ? -1 : 1)))
    for (const c of candidates) {
      if (total <= maxBytes) break
      local.removeItem(SESSION_PREFIX + c.id)
      total -= c.bytes
      deleted.push(c.id)
    }
    return deleted
  }

  /**
   * setItem con la regla de la cuota: si lanza QuotaExceededError se aplica el tope, se
   * reintenta una vez y, si vuelve a fallar, devuelve false (quien llama descarta y avisa).
   * @param {string} key
   * @param {string} value
   * @param {string|null} keepId
   * @returns {boolean}
   */
  function setWithCap (key, value, keepId) {
    try {
      local.setItem(key, value)
      return true
    } catch (err) {
      if (!isQuotaError(err)) return false
    }
    enforceCap(keepId)
    try {
      local.setItem(key, value)
      return true
    } catch {
      return false
    }
  }

  return {
    size,
    sessionIds,
    enforceCap,

    /** @returns {AugurState|null} null = sin decisión */
    readState () {
      const s = parse(local.getItem(STATE_KEY))
      return s && typeof s === 'object' && !Array.isArray(s) ? s : null
    },

    /**
     * @param {AugurState} state
     * @returns {boolean}
     */
    writeState (state) {
      return setWithCap(STATE_KEY, JSON.stringify(state), null)
    },

    /**
     * @param {string} id
     * @returns {SessionRecord|null}
     */
    readSession (id) {
      const rec = parse(local.getItem(SESSION_PREFIX + id))
      return rec && typeof rec === 'object' && rec.h && Array.isArray(rec.events) ? rec : null
    },

    /**
     * Escribe el registro entero (no hay append en localStorage). El tope de la cuota nunca
     * borra la propia sesión.
     * @param {string} id
     * @param {SessionRecord} rec
     * @returns {boolean}
     */
    writeSession (id, rec) {
      return setWithCap(SESSION_PREFIX + id, JSON.stringify(rec), id)
    },

    /** @param {string} id */
    deleteSession (id) {
      local.removeItem(SESSION_PREFIX + id)
    },

    /** Borra todas las augur:s:* (revocar el consentimiento). */
    deleteAllSessions () {
      for (const id of sessionIds()) local.removeItem(SESSION_PREFIX + id)
    },

    /** @returns {string|null} la sesión de esta pestaña */
    readTabSessionId () {
      const t = parse(tab.getItem(TAB_KEY))
      return t && typeof t.session_id === 'string' ? t.session_id : null
    },

    /** @param {string} id */
    writeTabSessionId (id) {
      try {
        tab.setItem(TAB_KEY, JSON.stringify({ session_id: id }))
        return true
      } catch {
        return false
      }
    },

    clearTab () {
      try {
        tab.removeItem(TAB_KEY)
      } catch {
        // sessionStorage bloqueado: no hay nada que borrar
      }
    },
  }
}

/**
 * Storage en memoria con la interfaz de Web Storage. Lo usa augur.js cuando sessionStorage no
 * está disponible (la sesión dura entonces lo que la carga de la página), y la suite.
 * @returns {Storage}
 */
export function createMemoryStorage () {
  /** @type {Map<string, string>} */
  const map = new Map()
  return /** @type {Storage} */ ({
    get length () { return map.size },
    key (i) { return [...map.keys()][i] ?? null },
    getItem (k) { return map.has(k) ? /** @type {string} */ (map.get(k)) : null },
    setItem (k, v) { map.set(String(k), String(v)) },
    removeItem (k) { map.delete(k) },
    clear () { map.clear() },
  })
}
