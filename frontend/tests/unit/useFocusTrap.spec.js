import { describe, expect, it, beforeEach } from 'vitest'
import { defineComponent, h, ref, nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { useFocusTrap } from '@/composables/useFocusTrap'

/**
 * Contenedor de prueba con tres elementos enfocables dentro y uno fuera, que es
 * el que abre el modal. Funciona en jsdom porque el composable decide qué es
 * visible con `getComputedStyle` y no con `offsetParent`, que aquí sería null
 * para todo.
 */
const Host = defineComponent({
  props: { open: { type: Boolean, default: false } },
  emits: ['escape'],
  setup (props, { emit }) {
    const dialogRef = ref(null)
    useFocusTrap(dialogRef, { isOpen: () => props.open, onEscape: () => emit('escape') })
    return { dialogRef }
  },
  render () {
    return h('div', [
      h('button', { class: 'opener' }, 'abrir'),
      this.open
        ? h('div', { ref: 'dialogRef', class: 'dialog', tabindex: '-1' }, [
            h('button', { class: 'first' }, 'uno'),
            h('input', { class: 'middle' }),
            h('button', { class: 'last' }, 'tres'),
          ])
        : null,
    ])
  },
})

const mountHost = () => mount(Host, { attachTo: document.body })
const key = (k, extra = {}) =>
  document.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true, cancelable: true, ...extra }))

describe('useFocusTrap', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('al abrir enfoca el primer elemento del contenedor', async () => {
    const wrapper = mountHost()
    await wrapper.setProps({ open: true })
    await nextTick()

    expect(document.activeElement).toBe(wrapper.find('.first').element)
    wrapper.unmount()
  })

  it('al cerrar devuelve el foco a quien lo tenía', async () => {
    const wrapper = mountHost()
    const opener = wrapper.find('.opener').element
    opener.focus()

    await wrapper.setProps({ open: true })
    await nextTick()
    expect(document.activeElement).not.toBe(opener)

    await wrapper.setProps({ open: false })
    expect(document.activeElement).toBe(opener)
    wrapper.unmount()
  })

  it('Escape llama a onEscape mientras está abierto', async () => {
    const wrapper = mountHost()
    await wrapper.setProps({ open: true })
    await nextTick()

    key('Escape')
    expect(wrapper.emitted('escape')).toHaveLength(1)
    wrapper.unmount()
  })

  it('no responde a Escape una vez cerrado', async () => {
    const wrapper = mountHost()
    await wrapper.setProps({ open: true })
    await nextTick()
    await wrapper.setProps({ open: false })

    key('Escape')
    expect(wrapper.emitted('escape')).toBeUndefined()
    wrapper.unmount()
  })

  it('Tab desde el último elemento vuelve al primero', async () => {
    const wrapper = mountHost()
    await wrapper.setProps({ open: true })
    await nextTick()

    wrapper.find('.last').element.focus()
    key('Tab')
    expect(document.activeElement).toBe(wrapper.find('.first').element)
    wrapper.unmount()
  })

  it('Shift+Tab desde el primero salta al último', async () => {
    const wrapper = mountHost()
    await wrapper.setProps({ open: true })
    await nextTick()

    wrapper.find('.first').element.focus()
    key('Tab', { shiftKey: true })
    expect(document.activeElement).toBe(wrapper.find('.last').element)
    wrapper.unmount()
  })

  // Regresión: `EditItemModal` lo monta su consumidor con `v-if`, así que al
  // cerrarse se desmonta y el `watch` de `isOpen` ya no corre. Medido en el
  // navegador: Escape cerraba el modal y dejaba el foco en el `<body>`.
  // El abridor se crea fuera del componente porque en la app real vive en el
  // padre (`views/shared/MediaDetailView.vue:82`), que sobrevive al desmontaje.
  it('al desmontarse estando abierto también devuelve el foco', async () => {
    const externo = document.createElement('button')
    document.body.appendChild(externo)
    externo.focus()

    const wrapper = mountHost()
    await wrapper.setProps({ open: true })
    await nextTick()
    expect(document.activeElement).toBe(document.querySelector('.first'))

    wrapper.unmount()
    expect(document.activeElement).toBe(externo)
  })

  it('devuelve el foco al contenedor si el foco se ha escapado fuera', async () => {
    const wrapper = mountHost()
    await wrapper.setProps({ open: true })
    await nextTick()

    wrapper.find('.opener').element.focus()
    key('Tab')
    expect(wrapper.find('.dialog').element.contains(document.activeElement)).toBe(true)
    wrapper.unmount()
  })
})
