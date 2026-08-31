<template>
  <div class="inbox-view u-content-width">
    <div class="inbox-view__header">
      <h1 class="inbox-view__title">
        <i class="pi pi-inbox" />
        {{ t('inbox.title') }}
      </h1>
    </div>

    <div
      v-if="isLoading"
      class="inbox-view__loading"
    >
      <i class="pi pi-spin pi-spinner" />
    </div>

    <!-- El vacío se dice con texto, no con un spinner eterno. -->
    <EmptyState
      v-else-if="!hasItems"
      icon="pi pi-inbox"
      :title="t('inbox.empty')"
    />

    <div
      v-else
      class="inbox-view__items"
    >
      <!-- Lista de tarjetas TIPADAS: cada elemento trae su `kind` y el mapa
           decide el componente. Hoy solo hay recomendaciones; las invitaciones a
           listas y a clubs entrarán como un `kind` nuevo y un componente más,
           sin tocar esta pantalla. -->
      <component
        :is="cardFor(item.kind)"
        v-for="item in items"
        :key="item.id"
        v-bind="propsFor(item)"
        :busy="resolvingId === item.id"
        @add="handleAdd"
        @accept="handleAccept"
        @dismiss="handleDismiss"
      />
    </div>

    <p
      v-if="error"
      class="inbox-view__error"
      role="alert"
    >
      {{ error }}
    </p>
  </div>
</template>

<script setup>
import { computed, inject, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useInboxStore } from '@/store/inbox'
import RecommendationCard from '@/components/Inbox/RecommendationCard.vue'
import ListInvitationCard from '@/components/Inbox/ListInvitationCard.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { useRouter } from 'vue-router'
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const inbox = useInboxStore()
const { items, isLoading, resolvingId, error } = storeToRefs(inbox)
const hasItems = computed(() => inbox.hasItems)

const notifications = inject('notifications', null)

// El mapa de tipos: añadir uno es añadir una línea y un componente.
const CARDS = {
  recommendation: RecommendationCard,
  list_invitation: ListInvitationCard
}

const cardFor = (kind) => CARDS[kind] ?? RecommendationCard

// Cada tarjeta nombra su prop principal por lo que la cosa ES, no por la fila
// que la transporta: una invitación no es una recomendación aunque comparta
// tabla. Un `v-bind` dinámico evita pasarle las dos a todas.
const propsFor = (item) =>
  item.kind === 'list_invitation' ? { invitation: item } : { recommendation: item }

const router = useRouter()

onMounted(() => inbox.fetchInbox())

const handleAdd = async (recommendation) => {
  const result = await inbox.addToLibrary(recommendation)

  if (result.success) {
    notifications?.showSuccess?.('Añadido a tu biblioteca')
  } else {
    notifications?.showError?.(result.message || 'No se pudo añadir')
  }
}

const handleAccept = async (invitation) => {
  const result = await inbox.acceptCollaboration(invitation)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo aceptar la invitación')
    return
  }

  notifications?.showSuccess?.('Ya colaboras en la lista')
  // Se entra directo: lo siguiente que quiere quien acepta es verla.
  if (result.listId) {
    router.push({ name: 'ListDetail', params: { listId: String(result.listId) } })
  }
}

const handleDismiss = async (recommendation) => {
  const result = await inbox.dismiss(recommendation)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo descartar')
  }
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.inbox-view {
  padding: spacing(lg);

  &__header {
    margin-bottom: spacing(lg);
  }

  &__title {
    display: flex;
    align-items: center;
    gap: spacing(sm);
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--color-text);

    i { color: var(--color-primary); }
  }

  &__items {
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
  }

  &__loading {
    text-align: center;
    padding: spacing(2xl);
    color: var(--color-text-secondary);

    i { font-size: var(--font-size-4xl); display: block; margin-bottom: spacing(md); }
  }

  &__error {
    margin-top: spacing(md);
    text-align: center;
    color: var(--color-error);
  }
}
</style>
