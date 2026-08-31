import { describe, it, expect, beforeEach, vi } from 'vitest'
import { t, setLocale, locale, availableLocales, initI18n, detectLocale } from '@/config/i18n'
import { useI18n } from '@/composables/useI18n'
import Logger from '@/utils/logger'

/**
 * El motor del plan de internacionalización.
 *
 * Lo que se fija aquí es el contrato del M1, no el contenido de los catálogos:
 * que traduce, que interpola, que elige plural por `n`, que una clave que falta
 * **no rompe la pantalla** y que la cascada de elección de idioma respeta su
 * orden. El catálogo de verdad lo cubre la barrera de paridad del M2.
 */
describe('i18n — el motor', () => {
  beforeEach(async () => {
    localStorage.clear()
    await setLocale('es')
  })

  it('arranca por la cascada: lo persistido gana al navegador', async () => {
    // jsdom dice `en-US`, así que sin nada guardado arranca en inglés — el paso 2.
    // Comprobarlo así vale más que forzar el idioma: prueba la cascada de verdad.
    await initI18n()
    expect(locale.value).toBe('en')
    expect(t('profile.language.title')).toBe('Language')

    // Y con algo guardado gana eso, que es el paso 1.
    localStorage.setItem('locale', 'es')
    await initI18n()
    expect(locale.value).toBe('es')
    expect(t('profile.language.title')).toBe('Idioma')
  })

  it('cambia de idioma y la traducción cambia con él', async () => {
    expect(t('profile.language.title')).toBe('Idioma')
    await setLocale('en')
    expect(locale.value).toBe('en')
    expect(t('profile.language.title')).toBe('Language')
  })

  it('persiste el idioma elegido, que es lo que lo hace sobrevivir a un F5', async () => {
    await setLocale('en')
    expect(localStorage.getItem('locale')).toBe('en')
    // Y la cascada lo lee de vuelta: es el paso 1 de los tres.
    expect(detectLocale()).toBe('en')
  })

  it('interpola con llave simple', async () => {
    expect(t('common.loading', { what: 'Matrix' })).toBe('Cargando Matrix…')
  })

  it('deja la llave intacta si no le pasan ese parámetro', () => {
    // Mejor un `{what}` visible que un «Cargando undefined…»: uno se ve y se
    // arregla, el otro parece un dato del usuario.
    expect(t('common.loading')).toBe('Cargando {what}…')
  })

  it('una clave que falta devuelve la clave misma y avisa UNA vez', () => {
    const warn = vi.spyOn(Logger, 'warn').mockImplementation(() => {})

    expect(t('nope.nope')).toBe('nope.nope')
    expect(warn).toHaveBeenCalledTimes(1)

    // La segunda no vuelve a avisar: una clave que falta dentro de una lista de
    // cien filas dejaría cien líneas idénticas en el log y en el buffer que
    // `Logger` manda al backend.
    expect(t('nope.nope')).toBe('nope.nope')
    expect(warn).toHaveBeenCalledTimes(1)

    warn.mockRestore()
  })

  it('una clave que apunta a una rama se trata como que falta', () => {
    const warn = vi.spyOn(Logger, 'warn').mockImplementation(() => {})
    expect(t('common')).toBe('common')
    expect(warn).toHaveBeenCalled()
    warn.mockRestore()
  })

  it('un idioma desconocido no cambia nada', async () => {
    const warn = vi.spyOn(Logger, 'warn').mockImplementation(() => {})
    await setLocale('de')
    expect(locale.value).toBe('es')
    warn.mockRestore()
  })

  it('la cascada cae al navegador y, si tampoco, al español', () => {
    const idioma = vi.spyOn(window.navigator, 'language', 'get')

    idioma.mockReturnValue('en-GB')
    expect(detectLocale()).toBe('en')

    idioma.mockReturnValue('fr-FR')
    expect(detectLocale()).toBe('es')

    idioma.mockRestore()
  })

  it('el composable expone lo mismo que el motor', () => {
    const { t: tc, locale: lc, setLocale: sc, availableLocales: ac } = useI18n()
    expect(tc).toBe(t)
    expect(lc).toBe(locale)
    expect(sc).toBe(setLocale)
    expect(ac).toEqual(availableLocales)
  })

  it('`locale` es de solo lectura desde fuera', () => {
    // Vue avisa y no asigna; lo que importa es que no se pueda cambiar el idioma
    // por la puerta de atrás, saltándose la persistencia.
    const antes = locale.value
    try { locale.value = 'en' } catch { /* readonly */ }
    expect(locale.value).toBe(antes)
  })
})
