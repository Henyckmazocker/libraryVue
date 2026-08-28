import { describe, expect, it } from 'vitest'
import { mountComponent } from './helpers/mount'
import ListInvitationCard from '@/components/Inbox/ListInvitationCard.vue'

/**
 * La invitación a colaborar, en la bandeja.
 *
 * Viaja por el mismo buzón que las recomendaciones, así que lo que estos tests
 * fijan es que la tarjeta se comporta como lo que la cosa ES —una invitación—
 * y no como la fila que la transporta.
 */
const stubs = { RouterLink: { template: '<a><slot /></a>' } }

const invitacion = (overrides = {}) => ({
  id: 3,
  entity_type: 'list',
  entity_id: '7',
  entity_title: 'A cuatro manos',
  sender: { id: 2, username: 'ana', name: 'Ana' },
  created_at: new Date().toISOString(),
  ...overrides
})

const montar = (props = {}) =>
  mountComponent(ListInvitationCard, {
    props: { invitation: invitacion(), ...props },
    global: { stubs }
  })

describe('ListInvitationCard', () => {
  it('dice quién invita y a qué lista', () => {
    const texto = montar().text()

    expect(texto).toContain('ana')
    expect(texto).toContain('A cuatro manos')
  })

  it('avisa de lo que aceptar da y de lo que no', () => {
    // La distinción del M1: `canEdit` abre el contenido, no la lista misma.
    const texto = montar().text()

    expect(texto).toContain('añadir y quitar ítems')
    expect(texto).toContain('Renombrarla o borrarla')
  })

  it('no enlaza a la lista, porque hasta aceptar responde 403', () => {
    const w = montar()
    const enlaces = w.findAll('a')

    // Solo el del perfil de quien invita.
    expect(enlaces).toHaveLength(1)
    expect(enlaces[0].text()).toBe('ana')
  })

  it('no ofrece perfil de quien no tiene username', () => {
    const w = montar({ invitation: invitacion({ sender: { id: 2, name: 'Sin nombre de usuario' } }) })

    expect(w.findAll('a')).toHaveLength(0)
    expect(w.text()).toContain('Sin nombre de usuario')
  })

  it('emite aceptar y rechazar', async () => {
    const w = montar()
    const botones = w.findAll('.list-invitation-card__action')

    await botones[0].trigger('click')
    await botones[1].trigger('click')

    expect(w.emitted('accept')).toHaveLength(1)
    expect(w.emitted('dismiss')).toHaveLength(1)
  })

  it('desactiva los dos botones mientras se resuelve', () => {
    const w = montar({ busy: true })

    w.findAll('.list-invitation-card__action').forEach((b) => {
      expect(b.attributes('disabled')).toBeDefined()
    })
  })
})
