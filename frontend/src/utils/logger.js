/**
 * Frontend Logger Utility
 * Handles logging based on environment (development vs production)
 * Sends important logs to backend for centralized logging
 */

const isDevelopment = process.env.NODE_ENV === 'development'

class Logger {
  // Send log to backend for centralized storage
  static async sendToBackend(level, message, data = {}) {
    if (typeof window === 'undefined') return // Skip in SSR
    
    try {
      const logData = {
        level,
        message,
        data: data,
        timestamp: new Date().toISOString(),
        url: window.location.href,
        userAgent: navigator.userAgent,
        source: 'frontend'
      }

      // Send to backend logging endpoint (don't await to avoid blocking)
      fetch(process.env.VUE_APP_API_URL || '/index.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          action: 'log_frontend',
          log_data: logData
        })
      }).catch(() => {
        // Silently fail if backend logging fails - no console output
      })
    } catch (error) {
      // Don't let logging errors break the app - silent failure
    }
  }

  static log(...args) {
    // Send general logs to backend
    this.sendToBackend('info', args.join(' '), { args })
  }

  static error(...args) {
    // Send to backend for centralized storage
    this.sendToBackend('error', args.join(' '), { args })
  }

  static warn(...args) {
    // Send warnings to backend for analysis
    this.sendToBackend('warn', args.join(' '), { args })
  }

  static info(...args) {
    // Send info logs to backend
    this.sendToBackend('info', args.join(' '), { args })
  }

  static debug(...args) {
    // Send debug logs to backend (only in development)
    if (isDevelopment) {
      this.sendToBackend('debug', args.join(' '), { args })
    }
  }

  // Auth-specific logging
  static auth(message, ...data) {
    // Send auth events to backend for security monitoring
    this.sendToBackend('auth', message, { data })
  }

  // API-specific logging
  static api(message, ...data) {
    // Send API logs to backend
    this.sendToBackend('info', `[API] ${message}`, { data })
  }

  // UI-specific logging
  static ui(message, ...data) {
    // Send UI logs to backend (only in development)
    if (isDevelopment) {
      this.sendToBackend('info', `[UI] ${message}`, { data })
    }
  }
}

export default Logger
