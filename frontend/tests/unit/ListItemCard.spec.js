import { describe, expect, it } from 'vitest'
import { mountComponent } from './helpers/mount'
import ListItemCard from '@/components/Lists/ListItemCard.vue'

/**
 * La tarjeta de un ítem de lista.
 *
 * Estos tests fijan la razón por la que NO se reutilizó `MediaListItem`: aquel
 * lee el ítem a través de los accessors del registry, que leen campos propios de
 * cada medio. Esta tarjeta lee la forma `entity_*` que la tabla guarda de verdad.
 */
const stubs = { RouterLink: { template: '<a><slot /></a>' } }

const fila = (overrides = {}) => ({
  id: 1,
  list_id: 7,
  entity_type: 'book',
  entity_id: '9780141036144',
  entity_title: '1984',
  entity_cover: 'https://cdn.ejemplo/1984.jpg',
  added_by: 1,
  position: 0,
  ...overrides
})

const montar = (props = {}) =>
  mountComponent(ListItemCard, {
    props: { item: fila(), ...props },
    global: { stubs }
  })

describe('ListItemCard — la tarjeta de un ítem de lista', () => {
  it('pinta el título que la lista guardó, no uno del catálogo', () => {
    const w = montar()

    expect(w.find('.list-card__title').text()).toBe('1984')
  })

  it('cae al título por defecto en vez de dejar la tarjeta muda', () => {
    const w = montar({ item: fila({ entity_title: null }) })

    expect(w.find('.list-card__title').text()).toBe('Sin título')
  })

  it('empieza por la portada LOCAL, que es la que no depende de un CDN ajeno', () => {
    const w = montar()

    const src = w.find('img').attributes('src')
    expect(src).toContain('cover=book/9780141036144')
  })

  it('baja a la remota al primer error y al placeholder solo al segundo', async () => {
    const w = montar()

    await w.find('img').trigger('error')
    expect(w.find('img').attributes('src')).toBe('https://cdn.ejemplo/1984.jpg')

    await w.find('img').trigger('error')
    // Sin los DOS indicadores, el `src` no cambiaría y el navegador no
    // reintentaría: el placeholder no llegaría nunca.
    expect(w.find('img').exists()).toBe(false)
    expect(w.find('.list-card__cover-placeholder').exists()).toBe(true)
  })

  it('va directo al placeholder cuando no hay ni local ni remota', async () => {
    // Sin `entity_id` no hay clave que pedirle a `?cover=`.
    const w = montar({ item: fila({ entity_id: '', entity_cover: null }) })

    expect(w.find('img').exists()).toBe(false)
    expect(w.find('.list-card__cover-placeholder').exists()).toBe(true)
  })

  it('acenta la tarjeta por medio, que es lo que distingue una lista mezclada', () => {
    expect(montar().classes()).toContain('list-card--book')
    expect(montar({ item: fila({ entity_type: 'album' }) }).classes()).toContain('list-card--album')
  })

  it('no revienta con un medio que el registry no conoce', () => {
    // `getMediaConfig` LANZA con un medio desconocido; la tarjeta comprueba
    // contra `mediaKeys` antes de llamarlo.
    const w = montar({ item: fila({ entity_type: 'podcast', entity_cover: null, entity_id: '' }) })

    expect(w.find('.list-card__media').text()).toContain('Ítem')
  })

  it('solo ofrece quitar el ítem si el SERVIDOR dijo que se puede editar', async () => {
    expect(montar().find('.list-card__remove').exists()).toBe(false)

    const w = montar({ canEdit: true })
    expect(w.find('.list-card__remove').exists()).toBe(true)

    await w.find('.list-card__remove').trigger('click')
    expect(w.emitted('remove')).toHaveLength(1)
  })
})
