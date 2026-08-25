import { describe, expect, it } from 'vitest'
import FeedEventCard from '@/components/Social/FeedEventCard.vue'
import { mountComponent } from './helpers/mount'

/**
 * La tarjeta del feed.
 *
 * Dos cosas se fijan aquí. La nueva —el «ver más» de las notas— y una vieja que
 * estaba rota: hasta el 2026-08-25 la tarjeta leía `event.new_value`, **campo
 * que no existe en `feed_events`**, así que un evento de valoración decía
 * literalmente «recibió una valoración de undefined».
 */

const CORTA = 'Una nota breve.'
const LARGA = 'Un párrafo bastante largo que no cabe en tres líneas. '.repeat(6)

const evento = (extra = {}) => ({
  entity_type: 'album',
  entity_title: 'Prequelle',
  entity_cover: null,
  created_at: new Date().toISOString(),
  user: { username: 'david' },
  ...extra
})

const montar = (e) => mountComponent(FeedEventCard, { props: { event: e } })

describe('FeedEventCard — el texto de la nota', () => {
  it('pinta el texto de la nota que viaja en metadata', () => {
    const w = montar(evento({
      event_type: 'notes_updated',
      metadata: { note_text: CORTA, note_type: 'quote' }
    }))

    expect(w.find('.feed-event-card__note').text()).toBe(CORTA)
  })

  it('una nota corta no ofrece el botón de desplegar', () => {
    const w = montar(evento({ event_type: 'notes_updated', metadata: { note_text: CORTA } }))

    expect(w.find('.feed-event-card__note-more').exists()).toBe(false)
  })

  it('una nota larga sí lo ofrece, y despliega', async () => {
    const w = montar(evento({ event_type: 'notes_updated', metadata: { note_text: LARGA } }))
    const boton = w.find('.feed-event-card__note-more')

    expect(boton.exists()).toBe(true)
    expect(boton.text()).toBe('Ver más')
    expect(w.find('.feed-event-card__note').classes()).not.toContain('feed-event-card__note--open')

    await boton.trigger('click')

    expect(w.find('.feed-event-card__note-more').text()).toBe('Ver menos')
    expect(w.find('.feed-event-card__note').classes()).toContain('feed-event-card__note--open')
  })

  it('el texto completo está en el DOM aunque se vea recortado', () => {
    // El recorte es CSS, no JavaScript: es lo que permite que un lector de
    // pantalla lo lea entero. Si algún día alguien lo trunca en JS, esto cae.
    const w = montar(evento({ event_type: 'notes_updated', metadata: { note_text: LARGA } }))

    expect(w.find('.feed-event-card__note').text()).toBe(LARGA.trim())
  })

  it('un evento que no es de nota no pinta bloque de nota', () => {
    const w = montar(evento({ event_type: 'item_added' }))

    expect(w.find('.feed-event-card__note').exists()).toBe(false)
  })

  it('escapa el texto en vez de interpretarlo', () => {
    // Es un feed social y el texto lo escribe una persona: `{{ }}`, nunca
    // `v-html`.
    const w = montar(evento({
      event_type: 'notes_updated',
      metadata: { note_text: '<img src=x onerror=alert(1)>' }
    }))

    expect(w.find('.feed-event-card__note').html()).not.toContain('<img')
    expect(w.find('.feed-event-card__note').text()).toContain('<img src=x')
  })
})

describe('FeedEventCard — los eventos que decían «undefined»', () => {
  it('una valoración enseña el número, no undefined', () => {
    const w = montar(evento({ event_type: 'item_rated', metadata: { rating: 4.5 } }))

    expect(w.text()).toContain('recibió una valoración de 4.5')
    expect(w.text()).not.toContain('undefined')
  })

  it('un cambio de estado enseña el estado, no undefined', () => {
    const w = montar(evento({
      event_type: 'status_changed',
      metadata: { old_status: 'wishlist', new_status: 'owned' }
    }))

    expect(w.text()).toContain('cambió de estado a "owned"')
    expect(w.text()).not.toContain('undefined')
  })

  it('sin metadata no rompe: pone un guion', () => {
    // `metadata` es NULL en la columna para `item_added`, y un evento viejo
    // puede no traerla.
    const w = montar(evento({ event_type: 'item_rated' }))

    expect(w.text()).toContain('recibió una valoración de —')
    expect(w.text()).not.toContain('undefined')
  })
})
