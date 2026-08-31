/**
 * useI18n — lo único que tocan los componentes.
 *
 * No guarda estado propio: el motor de `config/i18n.js` ya es un módulo con su
 * `ref`, así que esto es solo la puerta. Se hace composable, y no un import
 * directo del motor, porque es la convención del repo y porque deja un solo sitio
 * donde cambiar si algún día el idioma pasa a vivir en un store.
 */
import { locale, setLocale, availableLocales, t, statusLabel } from '@/config/i18n'

export function useI18n () {
  return { t, statusLabel, locale, setLocale, availableLocales }
}
