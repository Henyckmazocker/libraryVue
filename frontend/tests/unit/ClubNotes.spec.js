import { describe, it, expect } from 'vitest'
import { mountComponent } from './helpers/mount'
import ClubNotes from '@/components/Clubs/ClubNotes.vue'

/**
 * El bloque de notas del club.
 *
 * Lo que se fija aquí no es cómo se ve, sino que **el componente no puede
 * enseñar lo que no tiene**: una nota marcada llega con `text: null` del
 * servidor, así que ni difuminada ni oculta con CSS — no está.
 */
const montar = (props) => mountComponent(ClubNotes, { props })

describe('ClubNotes', () => {
  it('pinta el texto de una nota que no es spoiler', () => {
    const wrapper = montar({
      axis: 'page',
      notes: [{ noteId: 1, author: 'bruno', isMine: false, isSpoiler: false, text: 'Empieza flojo', atPoint: 30 }]
    })

    expect(wrapper.text()).toContain('Empieza flojo')
    expect(wrapper.find('.club-notes__spoiler').exists()).toBe(false)
  })

  it('una nota marcada no pinta texto, aunque le llegue texto', () => {
    // El contrato es que el servidor manda `text: null`. Pero se le pasa texto
    // A PROPÓSITO: si algún día una regresión del backend lo dejara viajar, el
    // componente tampoco puede pintarlo. Es la segunda barrera de lo único
    // irreversible de este plan.
    const wrapper = montar({
      axis: 'page',
      notes: [{ noteId: 2, author: 'bruno', isMine: false, isSpoiler: true, text: 'MUERE EL PROTAGONISTA', atPoint: 180 }]
    })

    expect(wrapper.find('.club-notes__text').exists()).toBe(false)
    expect(wrapper.find('.club-notes__spoiler').exists()).toBe(true)
    // Ni pintado ni escondido en el marcado: no está.
    expect(wrapper.html()).not.toContain('MUERE EL PROTAGONISTA')
  })

  it('dice hasta dónde hay que llegar cuando la nota tiene punto', () => {
    const wrapper = montar({
      axis: 'page',
      notes: [{ noteId: 3, author: 'bruno', isMine: false, isSpoiler: true, text: null, atPoint: 180 }]
    })

    // `atPoint` viaja aunque la nota esté oculta: decir «hay una nota en la 180»
    // no destripa nada y es lo que da sentido a la espera.
    expect(wrapper.text()).toContain('180')
    expect(wrapper.text()).toContain('página')
  })

  it('sin eje, el aviso es «cuando lo termines»', () => {
    const wrapper = montar({
      axis: null,
      notes: [{ noteId: 4, author: 'bruno', isMine: false, isSpoiler: true, text: null, atPoint: null }]
    })

    expect(wrapper.text()).toContain('lo termines')
    expect(wrapper.find('.club-notes__point').exists()).toBe(false)
  })

  it('marca mi propia nota y la enseña', () => {
    const wrapper = montar({
      axis: 'page',
      notes: [{ noteId: 5, author: 'ana', isMine: true, isSpoiler: false, text: 'Mía', atPoint: 300 }]
    })

    expect(wrapper.text()).toContain('(tú)')
    expect(wrapper.text()).toContain('Mía')
  })

  it('con series usa la unidad de temporada del registry', () => {
    const wrapper = montar({
      axis: 'season',
      notes: [{ noteId: 6, author: 'bruno', isMine: false, isSpoiler: true, text: null, atPoint: 2 }]
    })

    expect(wrapper.text()).toContain('temporada')
  })

  it('sin notas lo dice, no se queda en blanco', () => {
    const wrapper = montar({ axis: 'page', notes: [] })

    expect(wrapper.find('.club-notes__empty').exists()).toBe(true)
    expect(wrapper.find('.club-notes__list').exists()).toBe(false)
  })
})
