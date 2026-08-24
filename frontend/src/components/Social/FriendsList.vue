<template>
  <div class="friends-list">
    <div
      v-if="friends.length === 0"
      class="friends-list__empty"
    >
      <i class="pi pi-users" />
      <p>Todavía no tienes amigos. ¡Busca usuarios y envía solicitudes!</p>
    </div>

    <div
      v-else
      class="friends-list__items"
    >
      <div
        v-for="friend in friends"
        :key="friend.id"
        class="friends-list__item"
      >
        <div class="friends-list__avatar">
          <img
            v-if="friend.avatar"
            :src="friend.avatar"
            :alt="friend.username"
            loading="lazy"
            decoding="async"
          >
          <i
            v-else
            class="pi pi-user"
          />
        </div>
        <div class="friends-list__info">
          <router-link
            :to="`/user/${friend.username}`"
            class="friends-list__name"
          >
            {{ friend.username }}
          </router-link>
          <span
            v-if="friend.display_name"
            class="friends-list__display"
          >{{ friend.display_name }}</span>
        </div>
        <Button
          v-tooltip.top="'Eliminar amigo'"
          icon="pi pi-user-minus"
          severity="secondary"
          text
          size="small"
          @click="$emit('remove', friend.id)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'

defineProps({
  friends: { type: Array, default: () => [] }
})
defineEmits(['remove'])
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.friends-list {
  &__empty {
    text-align: center;
    padding: spacing(2xl);
    color: var(--color-text-secondary);
    i { font-size: 2.5rem; display: block; margin-bottom: spacing(md); }
  }

  &__items {
    display: flex;
    flex-direction: column;
    gap: spacing(xs);
  }

  &__item {
    display: flex;
    align-items: center;
    gap: spacing(md);
    padding: spacing(sm) spacing(md);
    border-radius: radius(md);
    background: var(--color-background-mute);
    transition: transition(fast);

    &:hover { background: var(--color-background-soft); }
  }

  &__avatar {
    width: 40px;
    height: 40px;
    border-radius: radius(full);
    overflow: hidden;
    background: var(--color-background-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    img { width: 100%; height: 100%; object-fit: cover; }
    i { font-size: 1.25rem; color: var(--color-text-secondary); }
  }

  &__info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
  }

  &__name {
    font-weight: 600;
    color: var(--color-text);
    text-decoration: none;
    &:hover { color: var(--color-primary); }
  }

  &__display {
    font-size: 0.8rem;
    color: var(--color-text-secondary);
  }
}
</style>
