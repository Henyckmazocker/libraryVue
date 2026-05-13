<template>
  <div class="friend-requests">
    <div v-if="requests.length === 0" class="friend-requests__empty">
      <i class="pi pi-inbox" />
      <p>No tienes solicitudes pendientes</p>
    </div>

    <div v-else class="friend-requests__items">
      <div
        v-for="req in requests"
        :key="req.id"
        class="friend-requests__item"
      >
        <div class="friend-requests__avatar">
          <img v-if="req.avatar" :src="req.avatar" :alt="req.username" />
          <i v-else class="pi pi-user" />
        </div>
        <div class="friend-requests__info">
          <span class="friend-requests__name">{{ req.username }}</span>
          <span class="friend-requests__sub">quiere ser tu amigo/a</span>
        </div>
        <div class="friend-requests__actions">
          <Button
            icon="pi pi-check"
            severity="success"
            size="small"
            v-tooltip.top="'Aceptar'"
            @click="$emit('accept', req.friendship_id)"
          />
          <Button
            icon="pi pi-times"
            severity="danger"
            text
            size="small"
            v-tooltip.top="'Rechazar'"
            @click="$emit('reject', req.friendship_id)"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'

defineProps({
  requests: { type: Array, default: () => [] }
})
defineEmits(['accept', 'reject'])
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.friend-requests {
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
  }

  &__sub {
    font-size: 0.8rem;
    color: var(--color-text-secondary);
  }

  &__actions {
    display: flex;
    gap: spacing(xs);
  }
}
</style>
