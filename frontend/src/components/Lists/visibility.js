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
 */
export const VISIBILITY = {
  private: {
    value: 'private',
    label: 'Privada',
    icon: 'pi pi-lock',
    hint: 'Solo la ves tú.'
  },
  public: {
    value: 'public',
    label: 'Pública',
    icon: 'pi pi-globe',
    hint: 'La ve cualquier usuario registrado desde tu perfil. Editarla, solo tú.'
  },
  collaborative: {
    value: 'collaborative',
    label: 'Colaborativa',
    icon: 'pi pi-users',
    hint: 'La ven y la editan las personas que invites. No es pública.'
  }
}

/** En el orden en que se ofrecen: de menos a más abierta. */
export const VISIBILITY_OPTIONS = [
  VISIBILITY.private,
  VISIBILITY.public,
  VISIBILITY.collaborative
]
