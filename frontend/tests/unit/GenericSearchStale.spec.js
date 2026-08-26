import { describe, expect, it, vi } from 'vitest'
import { markRaw } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { mountComponent } from './helpers/mount'
import GenericSearch from '@/components/shared/GenericSearch.vue'

// GenericSearch navega con useRouter y pinta tarjetas de carrusel; ni una cosa
// ni la otra participan aquí.
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn() }) }))

// `markRaw` porque VTU hace reactiva la prop `config` y Vue avisa si dentro
// viaja un componente. Es del arnés de test, no del código.
const Tarjeta = markRaw({ props: ['item'], template: '<div class="tarjeta" />' })

/**
 * La config mínima que el validator de GenericSearch acepta (`:74-95`), más lo
 * que cada test necesite encima.
 */
function config (extra = {}) {
  return {
    title: 'Buscador',
    inputs: [{ type: 'name', placeholder: 'Busca algo' }],
    carouselItemComponent: Tarjeta,
    itemProp: 'item',
    searchHandler: vi.fn().mockResolvedValue([]),
    transformResult: (r) => r,
    navigateToDetail: vi.fn(),
    getResultKey: (r) => r.id,
    fetchAllowedStatuses: vi.fn().mockResolvedValue([]),
    ...extra
  }
}

async function buscar (cfg) {
  const w = mountComponent(GenericSearch, { props: { config: cfg } })
  await flushPromises()

  await w.find('.search-input').setValue('zelda')
  await w.find('.search-button').trigger('click')
  await flushPromises()

  return w
}

const conResultados = (extra) => ({
  results: [{ id: 1, name: 'Un resultado' }],
  ...extra
})

describe('GenericSearch — la franja de degradación', () => {
  it('la pinta cuando el medio la soporta y la respuesta viene rancia', async () => {
    const w = await buscar(config({
      media: 'game',
      staleProvider: 'IGDB',
      searchHandler: vi.fn().mockResolvedValue(
        conResultados({ stale: true, cached_at: '2026-08-03T11:42:07+00:00' })
      )
    }))

    const franja = w.find('.stale-notice')
    expect(franja.exists()).toBe(true)
    expect(franja.text()).toContain('IGDB')
  })

  it('no la pinta cuando la respuesta viene fresca', async () => {
    const w = await buscar(config({
      media: 'game',
      staleProvider: 'IGDB',
      searchHandler: vi.fn().mockResolvedValue(
        conResultados({ stale: false, cached_at: '2026-08-03T11:42:07+00:00' })
      )
    }))

    expect(w.find('.stale-notice').exists()).toBe(false)
  })

  it('un medio sin `supportsStale` la ignora aunque venga en la respuesta', async () => {
    // La garantía que protege a películas y álbumes: vienen del mirror local y
    // no pueden ser rancios, así que no deben cambiar ni un píxel.
    const w = await buscar(config({
      media: 'movie',
      staleProvider: 'TMDB',
      searchHandler: vi.fn().mockResolvedValue(
        conResultados({ stale: true, cached_at: '2026-08-03T11:42:07+00:00' })
      )
    }))

    expect(w.find('.stale-notice').exists()).toBe(false)
  })

  it('una config sin `media` tampoco la pinta', async () => {
    const w = await buscar(config({
      searchHandler: vi.fn().mockResolvedValue(
        conResultados({ stale: true, cached_at: '2026-08-03T11:42:07+00:00' })
      )
    }))

    expect(w.find('.stale-notice').exists()).toBe(false)
  })

  it('sigue aceptando el handler que devuelve la lista pelada de siempre', async () => {
    const w = await buscar(config({
      media: 'game',
      searchHandler: vi.fn().mockResolvedValue([{ id: 1, name: 'Un resultado' }])
    }))

    expect(w.findAll('.tarjeta')).toHaveLength(1)
    expect(w.find('.stale-notice').exists()).toBe(false)
  })

  it('con cero resultados da el error de siempre y NO una franja sobre el vacío', async () => {
    // La verificación nº2 del plan: proveedor caído y sin caché tiene que seguir
    // dando el error de siempre.
    const w = await buscar(config({
      media: 'game',
      staleProvider: 'IGDB',
      searchHandler: vi.fn().mockResolvedValue({ results: [], stale: true, cached_at: null })
    }))

    expect(w.find('.stale-notice').exists()).toBe(false)
    expect(w.find('.error-message').text()).toContain('No se encontraron resultados')
  })

  it('una búsqueda que falla retira la franja de la búsqueda anterior', async () => {
    const handler = vi.fn()
      .mockResolvedValueOnce(conResultados({ stale: true, cached_at: '2026-08-03T11:42:07+00:00' }))
      .mockRejectedValueOnce(new Error('se cayó'))

    const w = await buscar(config({ media: 'game', staleProvider: 'IGDB', searchHandler: handler }))
    expect(w.find('.stale-notice').exists()).toBe(true)

    await w.find('.search-button').trigger('click')
    await flushPromises()

    expect(w.find('.stale-notice').exists()).toBe(false)
    expect(w.find('.error-message').exists()).toBe(true)
  })
})
