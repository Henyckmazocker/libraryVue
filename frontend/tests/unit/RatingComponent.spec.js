import { describe, expect, it } from 'vitest'
import RatingComponent from '@/components/common/RatingComponent.vue'
import { mountComponent, stubBoundingRect } from './helpers/mount'

describe('RatingComponent', () => {
  it('pinta cinco estrellas de solo lectura con la mitad exacta', () => {
    const wrapper = mountComponent(RatingComponent, {
      props: { rating: 3.5, editable: false },
    })

    const icons = wrapper.findAll('.star-icon')
    expect(icons).toHaveLength(5)
    expect(icons[0].classes()).toContain('filled')
    expect(icons[2].classes()).toContain('filled')
    expect(icons[3].classes()).toContain('half-filled')
    expect(icons[4].classes()).toContain('empty')
    expect(wrapper.find('.stars-input').exists()).toBe(false)
  })

  it('pinta botones cuando es editable', () => {
    const wrapper = mountComponent(RatingComponent, { props: { rating: 2 } })

    expect(wrapper.findAll('button.star-button')).toHaveLength(5)
    expect(wrapper.find('.stars-display').exists()).toBe(false)
  })

  it('emite la estrella completa al pulsar la mitad derecha', async () => {
    const wrapper = mountComponent(RatingComponent, { props: { rating: null } })
    const star = wrapper.findAll('button.star-button')[3]
    stubBoundingRect(star.element, { width: 20 })

    await star.trigger('click', { clientX: 15 })

    expect(wrapper.emitted('update:rating')).toEqual([[4]])
    expect(wrapper.emitted('rating-changed')).toEqual([[4]])
  })

  it('emite media estrella al pulsar la mitad izquierda', async () => {
    const wrapper = mountComponent(RatingComponent, { props: { rating: null } })
    const star = wrapper.findAll('button.star-button')[3]
    stubBoundingRect(star.element, { width: 20 })

    await star.trigger('click', { clientX: 4 })

    expect(wrapper.emitted('update:rating')).toEqual([[3.5]])
  })

  it('no emite nada al pulsar si no es editable', async () => {
    const wrapper = mountComponent(RatingComponent, {
      props: { rating: 1, editable: false },
    })

    await wrapper.find('.stars-display').trigger('click')

    expect(wrapper.emitted('update:rating')).toBeUndefined()
  })

  it('refleja un cambio externo del rating', async () => {
    const wrapper = mountComponent(RatingComponent, { props: { rating: 1 } })

    await wrapper.setProps({ rating: 5 })

    const active = wrapper.findAll('button.star-button').filter((b) => b.classes().includes('active'))
    expect(active).toHaveLength(5)
  })
})
