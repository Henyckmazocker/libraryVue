<template>
  <div class="user-search-result">
    <div class="user-search-result__avatar">
      <img v-if="user.avatar" :src="user.avatar" :alt="user.username" />
      <i v-else class="pi pi-user" />
    </div>
    <div class="user-search-result__info">
      <router-link :to="`/user/${user.username}`" class="user-search-result__name">
        {{ user.username }}
      </router-link>
      <span v-if="user.display_name" class="user-search-result__display">{{ user.display_name }}</span>
    </div>
    <Button
      v-if="!user.is_friend && !user.request_sent"
      icon="pi pi-user-plus"
      severity="primary"
      text
      size="small"
      v-tooltip.top="'Enviar solicitud'"
      @click="$emit('send-request', user.id)"
    />
    <Tag v-else-if="user.request_sent" value="Solicitud enviada" severity="secondary" />
    <Tag v-else-if="user.is_friend" value="Amigo/a" severity="success" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Tag from 'primevue/tag'

defineProps({
  user: { type: Object, required: true }
})
defineEmits(['send-request'])
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.user-search-result {
  display: flex;
  align-items: center;
  gap: spacing(md);
  padding: spacing(sm) spacing(md);
  border-radius: radius(md);
  background: var(--color-background-mute);
  transition: transition(fast);

  &:hover { background: var(--color-background-soft); }

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
