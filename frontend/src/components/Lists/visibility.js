/**
 * Las tres visibilidades, con su etiqueta y su icono.
 *
 * Vive aquí y no en `mediaRegistry` a propósito: el registry describe lo que
 * varía **por medio**, y una visibilidad no es de ningún medio. Lo consumen la
 * rejilla de listas, la cabecera de la lista y el formulario, que son los tres
 * sitios donde se nombra.
 *
 * El texto de `hint` es lo que impide el malentendido de siempre: `collaborative`
 * NO es pública.
 *
 * Rótulo y pista son **getters**, como en `mediaRegistry`: este módulo se importa
 * antes de que el catálogo esté cargado, y un valor fijado al declarar se quedaría
 * con la clave sin traducir para siempre.
 */
import { t } from '@/config/i18n'

export const VISIBILITY = {
  private: {
    value: 'private',
    get label () { return t('visibility.private.label') },
    icon: 'pi pi-lock',
    get hint () { return t('visibility.private.hint') }
  },
  public: {
    value: 'public',
    get label () { return t('visibility.public.label') },
    icon: 'pi pi-globe',
    get hint () { return t('visibility.public.hint') }
  },
  collaborative: {
    value: 'collaborative',
    get label () { return t('visibility.collaborative.label') },
    icon: 'pi pi-users',
    get hint () { return t('visibility.collaborative.hint') }
  }
}

/** En el orden en que se ofrecen: de menos a más abierta. */
export const VISIBILITY_OPTIONS = [
  VISIBILITY.private,
  VISIBILITY.public,
  VISIBILITY.collaborative
]
