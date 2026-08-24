/**
 * Errores tipados del cliente.
 *
 * Se usan para que quien llama pueda distinguir un fallo esperado y accionable
 * (rate limit) de un error de red cualquiera, sin inspeccionar `error.response`.
 */

/**
 * El backend ha devuelto 429 (RateLimitMiddleware).
 *
 * `retryAfter` viene de la cabecera `Retry-After` y son segundos enteros.
 */
export class RateLimitError extends Error {
  constructor(retryAfter = 0, message = 'Demasiadas peticiones') {
    super(message)
    this.name = 'RateLimitError'
    this.retryAfter = retryAfter
  }
}

export default { RateLimitError }
