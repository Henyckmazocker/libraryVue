<template>
  <div class="feed-list">
    <div v-if="feed.length === 0 && !feedLoading" class="feed-list__empty">
      <i class="pi pi-users" />
      <p>Añade amigos para ver su actividad aquí</p>
    </div>

    <div class="feed-list__items">
      <FeedEventCard v-for="event in feed" :key="event.id" :event="event" />
    </div>

    <div ref="sentinel" class="feed-list__sentinel" />

    <div v-if="feedLoading" class="feed-list__loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <p v-if="!feedHasMore && feed.length > 0" class="feed-list__end">
      No hay más eventos
    </p>
  </div>
</template>

<script setup>
import FeedEventCard from './FeedEventCard.vue'
import { useFeed } from '@/composables/useFeed'

const { feed, feedHasMore, feedLoading, sentinel } = useFeed()
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.feed-list {
  display: flex;
  flex-direction: column;
  gap: spacing(sm);

  &__empty {
    text-align: center;
    padding: spacing(2xl);
    color: var(--color-text-secondary);

    i { font-size: 3rem; display: block; margin-bottom: spacing(md); }
  }

  &__items {
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
  }

  &__loading {
    text-align: center;
    padding: spacing(md);
    color: var(--color-text-secondary);
  }

  &__end {
    text-align: center;
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    padding: spacing(md);
  }

  &__sentinel {
    height: 1px;
  }
}
</style>
