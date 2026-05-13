<template>
  <div class="feed-event-card" :class="`feed-event-card--${event.entity_type}`">
    <div class="feed-event-card__avatar">
      <img v-if="event.user?.picture" :src="event.user.picture" :alt="event.user?.username" />
      <i v-else class="pi pi-user" />
    </div>

    <div class="feed-event-card__cover">
      <img v-if="event.entity_cover" :src="event.entity_cover" :alt="event.entity_title" />
      <div v-else class="feed-event-card__cover-placeholder">
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
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

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
    album: 'pi pi-music',
    video: 'pi pi-youtube'
  }
  return icons[props.event.entity_type] ?? 'pi pi-star'
})

const eventDescription = computed(() => {
  switch (props.event.event_type) {
    case 'item_added': return 'se añadió a la biblioteca'
    case 'item_rated': return `recibió una valoración de ${props.event.new_value}`
    case 'status_changed': return `cambió de estado a "${props.event.new_value}"`
    case 'notes_updated': return 'tiene notas actualizadas'
    case 'reading_session': return 'tiene una sesión de lectura registrada'
    default: return ''
  }
})

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
}
</style>
