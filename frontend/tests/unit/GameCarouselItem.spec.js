import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import GameCarouselItem from '@/components/Games/GameCarouselItem.vue'
import { handleStoreError } from '@/utils/storeHelpers'
import { mountComponent } from './helpers/mount'

/** El `title` del badge de plataforma es lo único que expone `platformsText`. */
function platformTooltip (platforms) {
  const wrapper = mountComponent(GameCarouselItem, {
    props: { game: { id: 1, title: 'Un juego', platforms } },
  })

  return wrapper.find('.platform-badge').attributes('title')
}

describe('GameCarouselItem — platformsText', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('une un array de strings con comas', () => {
    expect(platformTooltip(['PC (Microsoft Windows)', 'PlayStation 5']))
      .toBe('PC (Microsoft Windows), PlayStation 5')
  })

  it('une un array de objetos de IGDB por su nombre', () => {
    expect(platformTooltip([{ platform: { name: 'Xbox Series X' } }, { name: 'PC' }]))
      .toBe('Xbox Series X, PC')
  })

  it('parsea la string con el array serializado que manda la API', () => {
    expect(platformTooltip('["PC (Microsoft Windows)", "PlayStation 5"]'))
      .toBe('PC (Microsoft Windows), PlayStation 5')
  })

  it('devuelve la string tal cual cuando no lleva JSON dentro', () => {
    expect(platformTooltip('Nintendo Switch')).toBe('Nintendo Switch')
  })

  it('cae a la string original si el JSON está roto, que es el peor caso previo', () => {
    expect(platformTooltip('["PC (Microsoft Windows)", "PlayStation 5"'))
      .toBe('["PC (Microsoft Windows)", "PlayStation 5"')
  })

  it('no pinta el badge cuando no hay plataforma reconocible', () => {
    const wrapper = mountComponent(GameCarouselItem, {
      props: { game: { id: 1, title: 'Un juego', platforms: [] } },
    })

    expect(wrapper.find('.platform-badge').exists()).toBe(false)
  })
})

describe('storeHelpers', () => {
  /**
   * `matchesGameId` se retiró al migrar los stores a `createMediaStore`, pero
   * `handleStoreError` sigue vivo: lo importa `store/createMediaStore.js:5`.
   */
  it('sigue exportando handleStoreError', () => {
    expect(typeof handleStoreError).toBe('function')
    expect(handleStoreError({ response: { status: 401 } }))
      .toBe('Authentication required. Please login again.')
  })
})
