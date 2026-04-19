/**
 * Frontend Logger Utility
 * Handles logging based on environment (development vs production)
 * Sends important logs to backend in batches for centralized logging
 *
 * Batching strategy:
 *   - Logs are buffered and flushed every FLUSH_INTERVAL_MS (2 s)
 *   - Or immediately when the buffer reaches MAX_BUFFER_SIZE (20 entries)
 *   - A final flush is attempted on page unload via sendBeacon
 */

const isDevelopment = process.env.NODE_ENV === 'development'

/** Maximum entries before an automatic flush */
const MAX_BUFFER_SIZE = 20
/** Milliseconds between periodic flushes */
const FLUSH_INTERVAL_MS = 2000

class Logger {
  /** @type {Array<Object>} Buffered log entries waiting to be sent */
  static _buffer = []
  /** @type {number|null} Handle returned by setInterval */
  static _flushTimer = null

  // ----------------------------------------------------------------
  // Buffer & flush
  // ----------------------------------------------------------------

  /**
   * Queue a log entry.  Triggers a flush when the buffer is full.
   * @private
   */
  static _enqueue(level, message, data = {}) {
    if (typeof window === 'undefined') return // Skip in SSR

    this._buffer.push({
      level,
      message,
      data,
      timestamp: new Date().toISOString(),
      url: window.location.href,
      source: 'frontend'
    })

    // Start the periodic flush timer on first entry
    if (!this._flushTimer) {
      this._flushTimer = setInterval(() => this.flush(), FLUSH_INTERVAL_MS)
    }

    if (this._buffer.length >= MAX_BUFFER_SIZE) {
      this.flush()
    }
  }

  /**
   * Send all buffered entries to the backend in a single request.
   * Safe to call even when the buffer is empty.
   */
  static flush() {
    if (this._buffer.length === 0) return

    const entries = this._buffer.splice(0)

    try {
      const payload = JSON.stringify({
        action: 'log_frontend_batch',
        logs: entries
      })

      // Prefer sendBeacon during unload, fetch otherwise
      if (this._useBeacon) {
        navigator.sendBeacon(
          process.env.VUE_APP_API_URL || '/index.php',
          new Blob([payload], { type: 'application/json' })
        )
      } else {
        fetch(process.env.VUE_APP_API_URL || '/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: payload
        }).catch(() => {
          // Silently fail — don't break the app for logging
        })
      }
    } catch {
      // Don't let logging errors break the app
    }
  }

  /** @private Flag toggled during page unload */
  static _useBeacon = false

  /**
   * Wire up unload handler once at module load time.
   * Uses `sendBeacon` so the request survives page navigation.
   * @private
   */
  static _initUnloadHandler() {
    if (typeof window === 'undefined') return

    window.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'hidden') {
        this._useBeacon = true
        this.flush()
        this._useBeacon = false
      }
    })

    window.addEventListener('pagehide', () => {
      this._useBeacon = true
      this.flush()
      this._useBeacon = false
    })
  }

  // ----------------------------------------------------------------
  // Public API (unchanged signatures)
  // ----------------------------------------------------------------

  static log(...args) {
    this._enqueue('info', args.join(' '), { args })
  }

  static error(...args) {
    this._enqueue('error', args.join(' '), { args })
  }

  static warn(...args) {
    this._enqueue('warn', args.join(' '), { args })
  }

  static info(...args) {
    this._enqueue('info', args.join(' '), { args })
  }

  static debug(...args) {
    if (isDevelopment) {
      this._enqueue('debug', args.join(' '), { args })
    }
  }

  // Auth-specific logging
  static auth(message, ...data) {
    this._enqueue('auth', message, { data })
  }

  // API-specific logging
  static api(message, ...data) {
    this._enqueue('info', `[API] ${message}`, { data })
  }

  // UI-specific logging
  static ui(message, ...data) {
    if (isDevelopment) {
      this._enqueue('info', `[UI] ${message}`, { data })
    }
  }
}

// Initialise the unload handler at import time
Logger._initUnloadHandler()

export default Logger
