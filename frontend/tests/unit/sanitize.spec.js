import { afterEach, describe, expect, it } from 'vitest'
import { sanitizePlain, sanitizeRich } from '@/utils/sanitize'
import ConfirmationModal from '@/components/common/ConfirmationModal.vue'
import { mountComponent } from './helpers/mount'

describe('sanitizeRich', () => {
  it('conserva el marcado que Google Books manda de verdad', () => {
    const html = '<p>Primer párrafo</p><p>Con <i>cursiva</i>, <b>negrita</b> y un salto<br>aquí</p>'

    expect(sanitizeRich(html)).toBe(html)
  })

  it('conserva las listas', () => {
    expect(sanitizeRich('<ul><li>uno</li><li>dos</li></ul>'))
      .toBe('<ul><li>uno</li><li>dos</li></ul>')
  })

  it('elimina script, iframe, img onerror y svg onload', () => {
    expect(sanitizeRich('<script>alert(1)</script>hola')).toBe('hola')
    expect(sanitizeRich('<iframe src="http://evil"></iframe>')).toBe('')
    expect(sanitizeRich('<img src=x onerror="alert(1)">')).toBe('')
    expect(sanitizeRich('<svg onload="alert(1)"></svg>')).toBe('')
  })

  it('quita los atributos incluso de las etiquetas permitidas', () => {
    expect(sanitizeRich('<p onclick="alert(1)" style="color:red">texto</p>'))
      .toBe('<p>texto</p>')
    expect(sanitizeRich('<span class="x" title="y">texto</span>'))
      .toBe('<span>texto</span>')
  })

  it('trata null y undefined como cadena vacía', () => {
    expect(sanitizeRich(null)).toBe('')
    expect(sanitizeRich(undefined)).toBe('')
  })
})

describe('sanitizePlain', () => {
  it('conserva solo saltos y énfasis', () => {
    expect(sanitizePlain('Vas a borrar <b>Dune</b>.<br>No se puede deshacer.'))
      .toBe('Vas a borrar <b>Dune</b>.<br>No se puede deshacer.')
  })

  it('reduce el marcado de bloque a su texto', () => {
    expect(sanitizePlain('<p>uno</p><ul><li>dos</li></ul>')).toBe('unodos')
  })
})

describe('ConfirmationModal — el XSS que antes funcionaba', () => {
  /**
   * El `message` interpola títulos que vienen de la API y del usuario
   * (useConfirmationModal.js:98,124,148,167,186), así que un libro titulado
   * `<img src=x onerror=…>` se ejecutaba al pedir confirmación de borrado.
   */
  const PAYLOAD = '¿Borrar «Test<img src=x onerror="document.title=\'XSS\'">»?'

  // Desde que `ConfirmationModal` envuelve a `BaseModal` (2026-08-29), su
  // marcado va dentro de un `<Teleport to="body">`, así que no cuelga del
  // wrapper: se consulta el documento, que es donde el navegador lo pinta.
  const enElDoc = (sel) => document.querySelector(sel)

  afterEach(() => { document.body.innerHTML = '' })

  it('no deja el atributo onerror en el DOM', () => {
    mountComponent(ConfirmationModal, {
      props: { isVisible: true, message: PAYLOAD },
      attachTo: document.body,
    })

    const html = enElDoc('.modal-message').innerHTML
    expect(html).not.toContain('onerror')
    expect(html).not.toContain('<img')
    expect(enElDoc('.modal-message img')).toBeNull()
  })

  it('conserva el texto legible del mensaje', () => {
    mountComponent(ConfirmationModal, {
      props: { isVisible: true, message: PAYLOAD },
      attachTo: document.body,
    })

    expect(enElDoc('.modal-message').textContent).toContain('¿Borrar «Test')
  })

  it('conserva el <br> que algunos mensajes usan para separar líneas', () => {
    mountComponent(ConfirmationModal, {
      props: { isVisible: true, message: 'Primera línea.<br>Segunda línea.' },
      attachTo: document.body,
    })

    expect(enElDoc('.modal-message br')).not.toBeNull()
  })
})
