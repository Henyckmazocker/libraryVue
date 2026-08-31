<template>
  <div class="list-invitation-card">
    <div class="list-invitation-card__icon">
      <i class="pi pi-users" />
    </div>

    <div class="list-invitation-card__content">
      <p class="list-invitation-card__header">
        <!-- Con `{{ }}`, nunca `v-html`: lo escribe una persona. -->
        <component
          :is="profileRoute ? RouterLink : 'span'"
          class="list-invitation-card__sender"
          :to="profileRoute"
        >
          {{ senderName }}
        </component>
        {{ t('inbox.invitesYou') }}
        <strong>{{ invitation.entity_title || t('inbox.aList') }}</strong>
        <span class="list-invitation-card__time">{{ relativeTime }}</span>
      </p>

      <p class="list-invitation-card__hint">
        {{ t('inbox.inviteHint') }}
      </p>

      <div class="list-invitation-card__actions">
        <button
          type="button"
          class="btn btn--primary btn--sm"
          :disabled="busy"
          @click="$emit('accept', invitation)"
        >
          <i :class="busy ? 'pi pi-spin pi-spinner' : 'pi pi-check'" />
          {{ t('common.accept') }}
        </button>
        <button
          type="button"
          class="btn btn--ghost btn--sm"
          :disabled="busy"
          @click="$emit('dismiss', invitation)"
        >
          <i class="pi pi-times" />
          {{ t('common.reject') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

/**
 * Una invitación a colaborar en una lista, en la bandeja.
 *
 * Viaja por el mismo buzón que las recomendaciones —`entity_type = 'list'`,
 * `entity_id` el id de la lista y `entity_title` su nombre—, así que la
 * pantalla no cambia: `InboxView` despacha por `kind` contra su mapa `CARDS`.
 *
 * **Sin portada, y a propósito**: una lista no tiene carátula, así que no hay
 * escalón local → remota → placeholder que sostener. Lo que la identifica es un
 * icono fijo, que es también lo que la distingue de un ítem de un vistazo.
 */
const props = defineProps({
  invitation: {
    type: Object,
    required: true
  },
  busy: {
    type: Boolean,
    default: false
  }
})

defineEmits(['accept', 'dismiss'])

const senderName = computed(() =>
  props.invitation.sender?.username || props.invitation.sender?.name || 'Alguien'
)

// El perfil se resuelve por `username`, que es lo que pide `/user/:username`:
// quien no tiene username no tiene perfil al que ir.
const profileRoute = computed(() => {
  const username = props.invitation.sender?.username
  return username ? { name: 'PublicProfile', params: { username } } : null
})

// No enlaza a la lista: hasta aceptar, `get_list` responde 403 y el enlace
// llevaría a una pantalla de «no tienes permiso».
const relativeTime = computed(() => {
  const diff = Date.now() - new Date(props.invitation.created_at).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return t('feed.justNow')
  if (mins < 60) return `hace ${mins} min`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `hace ${hours} h`
  const days = Math.floor(hours / 24)
  return days < 30 ? `hace ${days} d` : `hace ${Math.floor(days / 30)} m`
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/cards' as *;

.list-invitation-card {
  display: flex;
  gap: spacing(md);
  @include card-base($padding: md, $radius-key: md);

  &__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 48px;
    height: 48px;
    border-radius: radius(sm);
    background: var(--color-background-soft);
    color: var(--color-primary);
  }

  &__content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: spacing(xs);
  }

  &__header {
    color: var(--color-text);
  }

  &__sender {
    font-weight: 600;
  }

  &__time {
    margin-left: spacing(xs);
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
  }

  &__hint {
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
  }

  &__actions {
    display: flex;
    gap: spacing(sm);
    margin-top: spacing(xs);
  }

}
</style>
