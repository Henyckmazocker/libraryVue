import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { useConfirmationModal } from '@/composables/useConfirmationModal'
import { setLocale } from '@/config/i18n'

/**
 * Los diálogos de confirmación tienen **dos capas de texto** —lo que pone
 * `showConfirmation` por defecto y lo que pone encima cada método de
 * conveniencia— y traducir solo una deja la mitad de los diálogos a medias sin
 * que se note hasta que se abre justo ese. Aquí se fijan las dos.
 *
 * No se monta ningún componente a propósito: lo que se comprueba es el objeto
 * de configuración, que es donde vive el texto. El modal solo lo pinta.
 */
describe('useConfirmationModal — el texto sale del catálogo', () => {
  const { modalState, showConfirmation, confirmDelete, confirmCompleteBook, closeModal } = useConfirmationModal()

  afterEach(() => {
    closeModal()
  })

  describe('en español', () => {
    beforeEach(async () => {
      await setLocale('es')
    })

    it('los valores por defecto salen del catálogo', () => {
      showConfirmation({})

      expect(modalState.config.title).toBe('Confirmar acción')
      expect(modalState.config.message).toBe('¿Estás seguro de que deseas continuar?')
      expect(modalState.config.confirmText).toBe('Confirmar')
      expect(modalState.config.cancelText).toBe('Cancelar')
      expect(modalState.config.processingText).toBe('Procesando...')
    })

    it('el borrado interpola el nombre y pide teclear la palabra', () => {
      confirmDelete('Dune', 'Se irán también sus notas')

      expect(modalState.config.message).toContain('<strong>"Dune"</strong>')
      expect(modalState.config.message).toContain('Se irán también sus notas')
      expect(modalState.config.textConfirmationValue).toBe('ELIMINAR')
      expect(modalState.config.textConfirmationPlaceholder).toBe('Escribe "ELIMINAR" para confirmar')
      expect(modalState.config.details).toEqual(['Esta acción no se puede deshacer'])
    })
  })

  describe('en inglés', () => {
    beforeEach(async () => {
      await setLocale('en')
    })

    afterEach(async () => {
      await setLocale('es')
    })

    it('los valores por defecto cambian de idioma', () => {
      showConfirmation({})

      expect(modalState.config.title).toBe('Confirm action')
      expect(modalState.config.confirmText).toBe('Confirm')
      expect(modalState.config.cancelText).toBe('Cancel')
    })

    it('la palabra que hay que teclear se traduce CON el diálogo', () => {
      // Si se tradujera el texto y no la palabra, el usuario leería «type
      // "DELETE"» y el botón no se activaría nunca escribiéndolo.
      confirmDelete('Dune')

      expect(modalState.config.textConfirmationValue).toBe('DELETE')
      expect(modalState.config.textConfirmationPlaceholder).toBe('Type "DELETE" to confirm')
    })

    it('lo que pone el método de conveniencia también, no solo lo de por defecto', () => {
      confirmCompleteBook('Dune', 412)

      expect(modalState.config.title).toBe('Finish book')
      expect(modalState.config.confirmText).toBe('Mark as finished')
      expect(modalState.config.details).toContain('Final page: 412')
    })
  })

  it('lo que pasa el llamante gana al valor por defecto', async () => {
    await setLocale('es')
    showConfirmation({ title: 'Un título propio' })

    expect(modalState.config.title).toBe('Un título propio')
    // …y el resto sigue viniendo del catálogo.
    expect(modalState.config.cancelText).toBe('Cancelar')
  })
})
