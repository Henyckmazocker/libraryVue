<template>
  <div
    class="feed-event-card"
    :class="`feed-event-card--${event.entity_type}`"
  >
    <div class="feed-event-card__avatar">
      <img
        v-if="event.user?.picture"
        :src="event.user.picture"
        :alt="event.user?.username"
        loading="lazy"
        decoding="async"
      >
      <i
        v-else
        class="pi pi-user"
      />
    </div>

    <div class="feed-event-card__cover">
      <img
        v-if="event.entity_cover"
        :src="event.entity_cover"
        :alt="event.entity_title"
        width="48"
        height="64"
        loading="lazy"
        decoding="async"
      >
      <div
        v-else
        class="feed-event-card__cover-placeholder"
      >
        <i :class="entityIcon" />
      </div>
    </div>

    <div class="feed-event-card__content">
      <div class="feed-event-card__header">
        <span class="feed-event-card__user">{{ event.user?.username || event.user?.name || 'Usuario' }}</span>
        <span class="feed-event-card__time">{{ relativeTime }}</span>
      </div>
      <p class="feed-event-card__description">
        <strong>{{ event.entity_title }}</strong>
        {{ eventDescription }}
      </p>

      <!-- El texto de la nota va SIEMPRE entero en el DOM y se recorta con CSS
           (`-webkit-line-clamp`), no con JavaScript: así un lector de pantalla
           lo lee completo aunque visualmente esté cortado. Y con `{{ }}`, nunca
           `v-html`: esto lo escribe una persona y es un feed social. -->
      <template v-if="noteText">
        <p
          class="feed-event-card__note"
          :class="{ 'feed-event-card__note--open': noteOpen }"
        >
          {{ noteText }}
        </p>
        <button
          v-if="noteIsLong"
          type="button"
          class="feed-event-card__note-more"
          :aria-expanded="noteOpen"
          @click="noteOpen = !noteOpen"
        >
          {{ noteOpen ? 'Ver menos' : 'Ver más' }}
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  event: {
    type: Object,
    required: true
  }
})

const entityIcon = computed(() => {
  const icons = {
    book: 'pi pi-book',
    movie: 'pi pi-video',
    game: 'pi pi-desktop',
    album: 'pi pi-headphones',
    video: 'pi pi-youtube'
  }
  return icons[props.event.entity_type] ?? 'pi pi-star'
})

/**
 * Lo que la tarjeta sabe del evento vive en `metadata`, no en columnas sueltas.
 *
 * Hasta el 2026-08-25 esto leía `props.event.new_value`, **campo que no existe
 * en `feed_events`**: los datos van en `metadata` (`status_changed` lo usa desde
 * la migración de mayo, e `item_rated` igual). El resultado era que un evento de
 * valoración decía literalmente «recibió una valoración de **undefined**» y uno
 * de cambio de estado, «cambió de estado a "undefined"». Es un fallo
 * preexistente, no de este plan, pero estaba en el mismo `computed` que había
 * que tocar.
 */
const metadata = computed(() => props.event.metadata ?? {})

const eventDescription = computed(() => {
  switch (props.event.event_type) {
    case 'item_added': return 'se añadió a la biblioteca'
    case 'item_rated': return `recibió una valoración de ${metadata.value.rating ?? '—'}`
    case 'status_changed': return `cambió de estado a "${metadata.value.new_status ?? '—'}"`
    case 'notes_updated': return 'tiene una nota nueva'
    case 'reading_session': return 'tiene una sesión de lectura registrada'
    default: return ''
  }
})

/** El texto de la nota, cuando el evento es de nota. */
const noteText = computed(() =>
  props.event.event_type === 'notes_updated' ? (metadata.value.note_text ?? '') : ''
)

// El umbral es de caracteres y no de líneas a propósito: contar líneas exige
// medir el DOM, y aquí basta con saber si merece la pena ofrecer el botón.
const noteIsLong = computed(() => noteText.value.length > 180)
const noteOpen = ref(false)

const relativeTime = computed(() => {
  const diff = Date.now() - new Date(props.event.created_at).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'ahora mismo'
  if (mins < 60) return `hace ${mins} min`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `hace ${hours} h`
  const days = Math.floor(hours / 24)
  return `hace ${days} d`
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.feed-event-card {
  display: flex;
  gap: spacing(md);
  padding: spacing(md);
  border-radius: radius(md);
  background: var(--color-background-mute);
  border-left: 3px solid var(--color-primary);

  &--book { border-left-color: var(--color-card-book-accent); }
  &--movie { border-left-color: var(--color-card-movie-accent); }
  &--game { border-left-color: var(--color-card-game-accent); }
  &--album { border-left-color: var(--color-card-album-accent); }
  &--video { border-left-color: var(--color-card-video-accent); }

  &__avatar {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: radius(full);
    overflow: hidden;
    background: var(--color-background-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    align-self: center;
    font-size: 1.1rem;
    color: var(--color-text-secondary);

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__cover {
    flex-shrink: 0;
    width: 48px;
    height: 64px;
    border-radius: radius(sm);
    overflow: hidden;
    background: var(--color-background-soft);
    display: flex;
    align-items: center;
    justify-content: center;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__cover-placeholder {
    font-size: 1.5rem;
    color: var(--color-text-secondary);
  }

  &__content {
    flex: 1;
    min-width: 0;
  }

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: spacing(sm);
    margin-bottom: spacing(2xs);
  }

  &__user {
    font-weight: 600;
    color: var(--color-primary);
  }

  &__time {
    font-size: 0.75rem;
    color: var(--color-text-secondary);
    white-space: nowrap;
  }

  &__description {
    font-size: 0.875rem;
    color: var(--color-text);
    margin: 0;
  }

  // El recorte es CSS y no JavaScript a propósito: el texto completo está
  // siempre en el DOM, así que un lector de pantalla lo lee entero aunque
  // visualmente se vean tres líneas.
  &__note {
    margin: spacing(xs) 0 0;
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    white-space: pre-line;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;

    &--open {
      -webkit-line-clamp: unset;
      overflow: visible;
    }
  }

  // `button-reset` va sin `width` a propósito (ver la nota del mixin), así que
  // este se ajusta a su texto y no ocupa la fila entera.
  &__note-more {
    @include button-reset;

    margin-top: spacing(xxs);
    font-size: 0.8125rem;
    color: var(--color-accent);
    text-decoration: underline;
    cursor: pointer;
  }
}
</style>
