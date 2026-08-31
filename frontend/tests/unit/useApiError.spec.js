import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { apiError } from '@/composables/useApiError'
import { setLocale } from '@/config/i18n'
import Logger from '@/utils/logger'

/**
 * `apiError` sustituye a las cuatro copias que traducían errores del backend, y
 * fija la regla del plan: **el `message` del backend no se enseña**. Llega en
 * inglés y con la redacción de quien escribió el endpoint; su sitio es el log.
 */
const DEL_BACKEND = 'An unexpected error occurred.'

describe('apiError — los errores del backend, por código', () => {
  beforeEach(() => {
    vi.spyOn(Logger, 'error').mockImplementation(() => {})
  })

  afterEach(async () => {
    vi.restoreAllMocks()
    await setLocale('es')
  })

  it('saca el código de un número, de un error de axios y del `data` de la respuesta', () => {
    expect(apiError(404)).toBe('Eso ya no existe')
    expect(apiError({ response: { status: 404 } })).toBe('Eso ya no existe')
    expect(apiError({ response: { data: { http_code: 404 } } })).toBe('Eso ya no existe')
    expect(apiError({ http_code: 404 })).toBe('Eso ya no existe')
  })

  it('lo del dominio gana a lo genérico', () => {
    expect(apiError(403)).toBe('No tienes permiso para hacer eso')
    expect(apiError(403, { 403: 'lists.error403' })).toBe('No tienes permiso sobre esta lista')
  })

  it('sin código conocido cae al respaldo del dominio, y luego al genérico', () => {
    expect(apiError(null, { defecto: 'lists.loadError' })).toBe('No se pudieron cargar tus listas')
    expect(apiError(null)).toBe('No se pudo completar la operación')
    expect(apiError(418)).toBe('No se pudo completar la operación')
  })

  it('NUNCA devuelve el texto del backend, y lo manda al log', () => {
    const salida = apiError({
      response: { status: 500, data: { message: DEL_BACKEND } }
    })

    expect(salida).toBe('Algo ha ido mal en el servidor')
    expect(salida).not.toContain('unexpected')
    expect(Logger.error).toHaveBeenCalledWith('[apiError]', { codigo: 500, mensaje: DEL_BACKEND })
  })

  it('cambia de idioma con el catálogo', async () => {
    await setLocale('en')

    expect(apiError(500)).toBe('Something went wrong on the server')
    expect(apiError(403, { 403: 'clubs.error403' })).toBe('You have no permission over this club')
    expect(apiError(null)).toBe('The operation could not be completed')
  })
})
