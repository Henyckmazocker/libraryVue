import { describe, expect, it } from 'vitest'
import { mountComponent } from './helpers/mount'
import ListFormDialog from '@/components/Lists/ListFormDialog.vue'

/**
 * El formulario de lista, y sobre todo su selector de visibilidad: es donde el
 * usuario decide quién ve la lista, así que lo que diga tiene que ser cierto.
 */
/**
 * `Dialog` de PrimeVue se teletransporta a body y `@vue/test-utils` no lo ve, así
 * que se sustituye por un stub que rinde los dos slots inline — el mismo apaño
 * que `RecommendDialog.spec.js:24` y `MediaNotes.spec.js:17`.
 */
const DialogStub = {
  props: ['visible'],
  template: '<div v-if="visible" class="dialog-stub"><slot /><slot name="footer" /></div>'
}

const montar = (props = {}) =>
  mountComponent(ListFormDialog, {
    props: { modelValue: true, ...props },
    global: { stubs: { Dialog: DialogStub } }
  })

describe('ListFormDialog — el selector de visibilidad', () => {
  it('ofrece las tres visibilidades, de menos a más abierta', () => {
    const w = montar()
    const opciones = w.findAll('.list-form-dialog__option-label').map((o) => o.text())

    expect(opciones).toEqual(['Privada', 'Pública', 'Colaborativa'])
  })

  it('dice que una lista colaborativa NO es pública, que es el malentendido de siempre', () => {
    const w = montar()
    const textos = w.findAll('.list-form-dialog__option-hint').map((o) => o.text())

    expect(textos[2]).toContain('No es pública')
    // Y que la pública la ve cualquier registrado, no solo los amigos.
    expect(textos[1]).toContain('cualquier usuario registrado')
  })

  it('nace privada: la visibilidad más cerrada es la que no hay que elegir', () => {
    const w = montar()
    const seleccionada = w.find('.list-form-dialog__option--selected')

    expect(seleccionada.text()).toContain('Privada')
  })

  it('no deja crear una lista sin nombre', async () => {
    const w = montar()
    const crear = w.findAll('.list-form-dialog__action').at(-1)

    expect(crear.attributes('disabled')).toBeDefined()

    await w.find('input').setValue('Para el verano')
    expect(crear.attributes('disabled')).toBeUndefined()
  })

  it('emite el formulario con el nombre recortado', async () => {
    const w = montar()
    await w.find('input').setValue('  Para el verano  ')
    await w.findAll('.list-form-dialog__option').at(1).trigger('click')
    await w.findAll('.list-form-dialog__action').at(-1).trigger('click')

    expect(w.emitted('submit')[0][0]).toEqual({
      name: 'Para el verano',
      description: '',
      visibility: 'public'
    })
  })

  it('avisa ANTES de guardar cuando dejar de ser colaborativa echa a los colaboradores', async () => {
    const w = montar({ list: { id: 1, name: 'Compartida', visibility: 'collaborative' } })

    // Mientras siga colaborativa, no hay nada que avisar.
    expect(w.find('.list-form-dialog__warning').exists()).toBe(false)

    await w.findAll('.list-form-dialog__option').at(0).trigger('click')
    expect(w.find('.list-form-dialog__warning').text()).toContain('perderán el acceso')
  })

  it('al editar, parte de lo que la lista ya era', () => {
    const w = montar({
      list: { id: 1, name: 'Ya existía', description: 'Con descripción', visibility: 'public' }
    })

    expect(w.find('input').element.value).toBe('Ya existía')
    expect(w.find('.list-form-dialog__option--selected').text()).toContain('Pública')
  })
})
