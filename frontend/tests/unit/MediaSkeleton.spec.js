import { describe, it, expect } from 'vitest'
import { mountComponent } from './helpers/mount'
import MediaSkeleton from '@/components/shared/MediaSkeleton.vue'

const VARIANTS = ['list-item', 'library-item', 'carousel', 'detail']

describe('MediaSkeleton — las cuatro variantes', () => {
  it.each(VARIANTS)('«%s» monta y se marca con su clase de variante', (variant) => {
    const wrapper = mountComponent(MediaSkeleton, { props: { variant } })

    expect(wrapper.classes()).toContain('media-skeleton')
    expect(wrapper.classes()).toContain(`media-skeleton--${variant}`)
    expect(wrapper.findAll('.media-skeleton__unit')).toHaveLength(1)
  })

  it.each(VARIANTS)('«%s» pinta una silueta con portada y líneas', (variant) => {
    const wrapper = mountComponent(MediaSkeleton, { props: { variant } })

    expect(wrapper.find('.media-skeleton__cover').exists()).toBe(true)
    expect(wrapper.findAll('.media-skeleton__lines > *').length).toBeGreaterThan(0)
  })

  it('respeta `count` repitiendo la silueta', () => {
    const wrapper = mountComponent(MediaSkeleton, {
      props: { variant: 'list-item', count: 8 },
    })

    expect(wrapper.findAll('.media-skeleton__unit')).toHaveLength(8)
    // La portada se repite con la silueta, no una sola vez.
    expect(wrapper.findAll('.media-skeleton__cover')).toHaveLength(8)
  })

  it('por defecto es una fila de biblioteca, una sola', () => {
    const wrapper = mountComponent(MediaSkeleton)

    expect(wrapper.classes()).toContain('media-skeleton--list-item')
    expect(wrapper.findAll('.media-skeleton__unit')).toHaveLength(1)
  })

  it('se anuncia una vez y esconde las piezas del lector de pantalla', () => {
    const wrapper = mountComponent(MediaSkeleton, {
      props: { count: 4, label: 'Cargando biblioteca…' },
    })

    expect(wrapper.attributes('role')).toBe('status')
    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.find('.u-sr-only').text()).toBe('Cargando biblioteca…')
    wrapper.findAll('.media-skeleton__unit').forEach((unit) => {
      expect(unit.attributes('aria-hidden')).toBe('true')
    })
  })

  it('rechaza una variante desconocida por el validador de la prop', () => {
    const { validator } = MediaSkeleton.props.variant

    expect(validator('list-item')).toBe(true)
    expect(validator('libro')).toBe(false)
  })
})
