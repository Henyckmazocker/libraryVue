import { describe, it, expect } from 'vitest'
import { mountComponent } from './helpers/mount'
import ClubMemberProgress from '@/components/Clubs/ClubMemberProgress.vue'
import { mediaKeys, mediaRegistry } from '@/config/mediaRegistry'

/**
 * El progreso por miembro de la pantalla del club.
 *
 * Lo que se fija aquí es lo que un cambio inocente rompe sin ruido: que el
 * miembro sin el ítem SIGA saliendo —es quien bloquea el cierre automático— y
 * que la unidad salga del registry y no de una constante escrita a mano.
 */
const miembros = [
  { user_id: 1, username: 'david', point: 50, completed: false },
  { user_id: 2, username: 'ana', point: 200, completed: false },
  { user_id: 3, username: 'luis', point: null, completed: false },
]

const montar = (props) => mountComponent(ClubMemberProgress, { props })

describe('ClubMemberProgress', () => {
  it('pinta una fila por miembro, incluido el que no ha empezado', () => {
    const wrapper = montar({ axis: 'page', members: miembros })

    expect(wrapper.findAll('.club-progress__row')).toHaveLength(3)
    // El que no lo tiene NO se oculta: es el que frena el cierre automático, y
    // esconderlo sería esconder el motivo.
    expect(wrapper.text()).toContain('luis')
    expect(wrapper.text()).toContain('Sin empezar')
  })

  it('enseña las posiciones distintas con la unidad del registry', () => {
    const wrapper = montar({ axis: 'page', members: miembros })
    const texto = wrapper.text()

    expect(texto).toContain('50')
    expect(texto).toContain('200')
    expect(texto).toContain(mediaRegistry.book.progress.unit)
  })

  it('sin eje, la marca es binaria y no hay número', () => {
    const wrapper = montar({
      axis: null,
      members: [
        { user_id: 1, username: 'david', point: null, completed: true },
        { user_id: 2, username: 'ana', point: null, completed: false },
      ],
    })

    expect(wrapper.text()).toContain('Sí')
    expect(wrapper.text()).toContain('Todavía no')
    expect(wrapper.find('.club-progress__bar').exists()).toBe(false)
  })

  it('la barra es relativa al que más ha avanzado, no a un total', () => {
    // Ni los libros tienen siempre `pages` ni las series un `total_seasons`
    // fiable: un porcentaje sobre el total mentiría la mitad de las veces.
    const wrapper = montar({ axis: 'page', members: miembros })
    const anchos = wrapper.findAll('.club-progress__fill').map((n) => n.attributes('style'))

    expect(anchos[0]).toContain('25%')
    expect(anchos[1]).toContain('100%')
  })

  it('marca a quien lo ha terminado, con texto para lector de pantalla', () => {
    const wrapper = montar({
      axis: 'page',
      members: [{ user_id: 1, username: 'david', point: 400, completed: true }],
    })

    expect(wrapper.find('.club-progress__done-icon').exists()).toBe(true)
    expect(wrapper.find('.u-sr-only').text()).toBe('Lo ha terminado')
  })

  it('sin miembros no revienta ni divide por cero', () => {
    const wrapper = montar({ axis: 'page', members: [] })

    expect(wrapper.findAll('.club-progress__row')).toHaveLength(0)
  })
})

describe('el bloque progress del mediaRegistry', () => {
  it('lo declaran los SEIS medios, no los cinco con store', () => {
    // `series` necesita el suyo y no tiene store: iterar `storeMediaKeys` en vez
    // de `mediaKeys` es el error fácil, y dejaría sin eje justo al único medio
    // que usa `season`.
    expect(mediaKeys).toHaveLength(6)
    for (const key of mediaKeys) {
      expect(mediaRegistry[key].progress, `falta progress en ${key}`).toBeDefined()
      expect(mediaRegistry[key].progress.completedStatuses.length).toBeGreaterThan(0)
    }
  })

  it('solo libros usa el eje page y solo series el eje season', () => {
    const conEje = (axis) => mediaKeys.filter((k) => mediaRegistry[k].progress.axis === axis)

    expect(conEje('page')).toEqual(['book'])
    expect(conEje('season')).toEqual(['series'])
    // Los otros cuatro no tienen eje: añadir minuto, porcentaje o pista era
    // «Fuera» del plan, así que ahí el progreso es binario.
    expect(conEje(null)).toHaveLength(4)
  })

  it('los estados de completado son los del seed de init.sql', () => {
    expect(mediaRegistry.book.progress.completedStatuses).toEqual(['read'])
    expect(mediaRegistry.movie.progress.completedStatuses).toEqual(['viewed'])
    expect(mediaRegistry.game.progress.completedStatuses).toEqual(['completed'])
    expect(mediaRegistry.album.progress.completedStatuses).toEqual(['listened'])
    expect(mediaRegistry.video.progress.completedStatuses).toEqual(['watched'])
    // La serie usa el estado de PELÍCULA, que es la misma fuente que consulta
    // `ClubCompletion` en el backend. Contar temporadas aquí haría que la
    // pantalla contradijera al cierre automático.
    expect(mediaRegistry.series.progress.completedStatuses).toEqual(['viewed'])
  })
})
