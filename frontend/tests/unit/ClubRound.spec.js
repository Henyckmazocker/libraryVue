import { describe, it, expect } from 'vitest'
import { mountComponent } from './helpers/mount'
import ClubRound from '@/components/Clubs/ClubRound.vue'

/**
 * El bloque de ronda del club.
 *
 * Lo que se fija aquí no es cómo se ve, sino tres cosas que sí importan: que el
 * componente **no puede enseñar quién votó a quién** —el servidor no se lo
 * manda—, que la rotación se **lee** del servidor en vez de recalcularse, y que
 * las dos válvulas del dueño no aparecen para el resto.
 */
const RONDA = {
  id: 7,
  phase: 'proposing',
  ballot: 1,
  winning_proposal_id: null,
  canPropose: true,
  reasonBlocked: null,
  proposals: [],
  myVote: null,
  pendingVoters: 0
}

const MIEMBROS = [
  { user_id: 1, name: 'David Carvajal', username: 'david' },
  { user_id: 2, name: 'La otra', username: null }
]

const propuesta = (extra = {}) => ({
  id: 11,
  round_id: 7,
  user_id: 2,
  entity_type: 'movie',
  entity_id: 'tt0111161',
  entity_title: 'Cadena perpetua',
  entity_cover: null,
  votes: 0,
  eliminated: false,
  ...extra
})

const montar = (round = {}, props = {}) => mountComponent(ClubRound, {
  props: { round: { ...RONDA, ...round }, members: MIEMBROS, ...props },
  global: { stubs: { RouterLink: true } }
})

describe('ClubRound', () => {
  it('dice que no hay nada propuesto cuando la ronda acaba de abrirse', () => {
    const wrapper = montar()

    expect(wrapper.text()).toContain('Nadie ha propuesto nada todavía')
    expect(wrapper.findAll('.club-round__proposal')).toHaveLength(0)
  })

  it('pinta cada propuesta con su título y quién la propone', () => {
    const wrapper = montar({ proposals: [propuesta()] })

    expect(wrapper.text()).toContain('Cadena perpetua')
    // `username` es NULLable y se cae al nombre, que no lo es.
    expect(wrapper.text()).toContain('La otra')
  })

  it('en la fase de propuestas no hay ni recuento ni botón de votar', () => {
    const wrapper = montar({ proposals: [propuesta({ votes: 3 })] })

    expect(wrapper.find('.club-round__votes').exists()).toBe(false)
    expect(wrapper.find('.club-round__vote').exists()).toBe(false)
  })

  it('votando, enseña el recuento y marca el voto propio', () => {
    const wrapper = montar({
      phase: 'voting',
      canPropose: false,
      reasonBlocked: 'voting',
      myVote: 11,
      pendingVoters: 1,
      proposals: [propuesta({ votes: 2 })]
    })

    expect(wrapper.find('.club-round__votes').text()).toContain('2')
    expect(wrapper.find('.club-round__vote').attributes('aria-pressed')).toBe('true')
    expect(wrapper.text()).toContain('Tu voto')
    expect(wrapper.text()).toContain('Faltan 1 por votar')
  })

  it('no puede enseñar quién votó a quién, porque no lo recibe', () => {
    // La verificación nº 3 del plan, en su versión de componente: el servidor
    // manda recuentos y `myVote`, y nada más. Se le pasa un `voters` inventado
    // A PROPÓSITO: si una regresión del backend lo dejara viajar, la plantilla
    // tampoco puede pintarlo.
    const wrapper = montar({
      phase: 'voting',
      proposals: [propuesta({ votes: 2, voters: ['david', 'la-otra'] })]
    })

    expect(wrapper.html()).not.toContain('la-otra')
    expect(wrapper.html()).not.toContain('voters')
  })

  it('el aviso de rotación se LEE del servidor, no se deduce', () => {
    const wrapper = montar({ canPropose: false, reasonBlocked: 'rotation' })

    expect(wrapper.text()).toContain('Ganaste la ronda anterior')
    // Y sin botón de proponer: te toca rotar.
    expect(wrapper.find('.club-round__action').exists()).toBe(false)
  })

  it('avisa de que ya propusiste, que es una por persona', () => {
    const wrapper = montar({ canPropose: false, reasonBlocked: 'already_proposed' })

    expect(wrapper.text()).toContain('Ya has propuesto')
  })

  it('marca las propuestas eliminadas en el desempate', () => {
    const wrapper = montar({
      phase: 'voting',
      ballot: 2,
      proposals: [
        propuesta({ id: 11, votes: 1 }),
        propuesta({ id: 12, user_id: 1, entity_title: 'El padrino', eliminated: true })
      ]
    })

    expect(wrapper.text()).toContain('el desempate')
    expect(wrapper.findAll('.club-round__proposal--eliminated')).toHaveLength(1)
    // Y no se puede votar lo eliminado: el backend lo rechaza con 404, así que
    // ofrecer el botón sería ofrecer un fallo.
    expect(wrapper.findAll('.club-round__vote')).toHaveLength(1)
  })

  it('las dos válvulas son solo del dueño', () => {
    expect(montar({}, { isOwner: false }).find('.club-round__valves').exists()).toBe(false)

    const dueno = montar({ proposals: [propuesta()] }, { isOwner: true })
    expect(dueno.find('.club-round__valves').exists()).toBe(true)
    expect(dueno.text()).toContain('Abrir el voto con lo que hay')
  })

  it('el dueño no puede abrir un voto sin propuestas', () => {
    // Abrir un voto vacío dejaría la ronda clavada un escalón más allá; el
    // backend responde 409 y aquí ni se ofrece.
    const wrapper = montar({ proposals: [] }, { isOwner: true })

    expect(wrapper.find('.club-round__action--valve').attributes('disabled')).toBeDefined()
  })

  it('el dueño no puede cerrar una votación sin votos', () => {
    const wrapper = montar(
      { phase: 'voting', proposals: [propuesta({ votes: 0 })] },
      { isOwner: true }
    )

    const valvula = wrapper.find('.club-round__action--valve')
    expect(valvula.text()).toContain('Cerrar la votación')
    expect(valvula.attributes('disabled')).toBeDefined()
  })

  it('emite el voto con el id de la propuesta', async () => {
    const wrapper = montar({ phase: 'voting', proposals: [propuesta()] })

    await wrapper.find('.club-round__vote').trigger('click')

    expect(wrapper.emitted('vote')).toEqual([[11]])
  })

  it('la portada de una propuesta es de CATÁLOGO, no de biblioteca', () => {
    // Una propuesta es un ítem que nadie tiene guardado: su fila de
    // `cover_file` es de `scope = 'catalog'`.
    const wrapper = montar({ proposals: [propuesta()] })

    expect(wrapper.find('.club-round__cover').attributes('src')).toContain('cover=movie/tt0111161')
  })

  it('cae a la portada copiada cuando el catálogo no resuelve el medio', () => {
    // `catalogCoverUrl` solo resuelve `movie` y `album`: libros, juegos y
    // vídeos se buscan contra APIs sin dump y su URL no se deduce de nada.
    const wrapper = montar({
      proposals: [propuesta({
        entity_type: 'book',
        entity_id: '9788401352836',
        entity_cover: 'https://cdn.ejemplo.test/portada.jpg'
      })]
    })

    expect(wrapper.find('.club-round__cover').attributes('src'))
      .toBe('https://cdn.ejemplo.test/portada.jpg')
  })
})
