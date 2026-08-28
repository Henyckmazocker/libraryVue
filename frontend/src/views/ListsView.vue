<template>
  <div class="lists-view">
    <div class="lists-view__header">
      <h1 class="lists-view__title">
        <i class="pi pi-list" />
        Mis listas
      </h1>

      <button
        type="button"
        class="lists-view__create"
        @click="openCreate"
      >
        <i class="pi pi-plus" />
        Nueva lista
      </button>
    </div>

    <div
      v-if="isLoading"
      class="lists-view__loading"
    >
      <i class="pi pi-spin pi-spinner" />
    </div>

    <!-- El vacío se dice con texto, no con un spinner eterno. -->
    <div
      v-else-if="!hasLists"
      class="lists-view__empty"
    >
      <i class="pi pi-list" />
      <p>Todavía no tienes ninguna lista</p>
      <p class="lists-view__empty-hint">
        Una lista mezcla libros, películas, juegos, álbumes y vídeos.
      </p>
    </div>

    <div
      v-else
      class="lists-view__grid"
    >
      <RouterLink
        v-for="list in lists"
        :key="list.id"
        class="lists-view__card"
        :to="{ name: 'ListDetail', params: { listId: String(list.id) } }"
      >
        <span class="lists-view__card-name">{{ list.name }}</span>

        <span
          v-if="list.description"
          class="lists-view__card-description"
        >{{ list.description }}</span>

        <span class="lists-view__card-meta">
          <span
            class="lists-view__badge"
            :class="'lists-view__badge--' + list.visibility"
          >
            <i :class="VISIBILITY[list.visibility].icon" />
            {{ VISIBILITY[list.visibility].label }}
          </span>
          <span>{{ list.item_count }} {{ list.item_count === 1 ? 'ítem' : 'ítems' }}</span>
          <!-- Una lista en la que solo colaboro no es mía, y se dice. -->
          <span
            v-if="!list.is_owner"
            class="lists-view__shared"
          >
            <i class="pi pi-users" />
            Compartida contigo
          </span>
        </span>
      </RouterLink>
    </div>

    <p
      v-if="error"
      class="lists-view__error"
      role="alert"
    >
      {{ error }}
    </p>

    <!-- Con `v-if` y no solo con `v-model`, como el RecommendDialog: así el
         formulario no existe mientras nadie lo abre. -->
    <ListFormDialog
      v-if="showCreate"
      v-model="showCreate"
      @submit="handleCreate"
    />
  </div>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useListsStore } from '@/store/lists'
import ListFormDialog from '@/components/Lists/ListFormDialog.vue'
import { VISIBILITY } from '@/components/Lists/visibility'

const listsStore = useListsStore()
const { lists, isLoading, error } = storeToRefs(listsStore)
const hasLists = computed(() => listsStore.hasLists)

const router = useRouter()
const notifications = inject('notifications', null)

const showCreate = ref(false)
const openCreate = () => { showCreate.value = true }

onMounted(() => listsStore.fetchMyLists())

const handleCreate = async (form) => {
  const result = await listsStore.createList(form)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo crear la lista')
    return
  }

  showCreate.value = false
  notifications?.showSuccess?.('Lista creada')
  // Se entra directo a la lista recién creada: lo siguiente que quiere el
  // usuario es meterle algo.
  router.push({ name: 'ListDetail', params: { listId: String(result.listId) } })
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.lists-view {
  max-width: 860px;
  margin: 0 auto;
  padding: spacing(lg);

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: spacing(md);
    margin-bottom: spacing(lg);
  }

  &__title {
    display: flex;
    align-items: center;
    gap: spacing(sm);
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text);

    i { color: var(--color-primary); }
  }

  &__create {
    @include button-reset;

    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(2xs) spacing(sm);
    border-radius: radius(sm);
    border: 1px solid var(--color-primary);
    color: var(--color-primary);
    font-size: 0.875rem;

    &:hover { background: var(--color-background-mute); }
  }

  &__grid {
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
  }

  &__card {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    padding: spacing(md);
    border-radius: radius(md);
    background: var(--color-background-mute);
    border: 1px solid var(--color-border-light);
    text-decoration: none;

    &:hover { border-color: var(--color-primary); }
  }

  &__card-name {
    font-weight: 600;
    color: var(--color-text);
  }

  &__card-description {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
  }

  &__card-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: spacing(sm);
    font-size: 0.8125rem;
    color: var(--color-text-secondary);
  }

  &__badge {
    display: inline-flex;
    align-items: center;
    gap: spacing(3xs);
  }

  &__shared {
    display: inline-flex;
    align-items: center;
    gap: spacing(3xs);
  }

  &__loading,
  &__empty {
    text-align: center;
    padding: spacing(2xl);
    color: var(--color-text-secondary);

    i { font-size: 3rem; display: block; margin-bottom: spacing(md); }
  }

  &__empty-hint {
    font-size: 0.875rem;
  }

  &__error {
    margin-top: spacing(md);
    text-align: center;
    color: var(--color-error);
  }
}
</style>
