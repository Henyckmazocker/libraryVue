<template>
  <div class="clubs-view">
    <div class="clubs-view__header">
      <h1 class="clubs-view__title">
        <i class="pi pi-users" />
        Mis clubs
      </h1>

      <button
        type="button"
        class="clubs-view__create"
        @click="openCreate"
      >
        <i class="pi pi-plus" />
        Nuevo club
      </button>
    </div>

    <div
      v-if="isLoading"
      class="clubs-view__loading"
    >
      <i class="pi pi-spin pi-spinner" />
    </div>

    <!-- El vacío se dice con texto, no con un spinner eterno. -->
    <div
      v-else-if="!hasClubs"
      class="clubs-view__empty"
    >
      <i class="pi pi-users" />
      <p>Todavía no estás en ningún club</p>
      <p class="clubs-view__empty-hint">
        Un club es un grupo de amigos con un mismo libro, película, juego, álbum
        o vídeo a la vez.
      </p>
    </div>

    <div
      v-else
      class="clubs-view__grid"
    >
      <RouterLink
        v-for="club in clubs"
        :key="club.id"
        class="clubs-view__card"
        :to="{ name: 'ClubDetail', params: { clubId: String(club.id) } }"
      >
        <span class="clubs-view__card-name">{{ club.name }}</span>

        <span
          v-if="club.description"
          class="clubs-view__card-description"
        >{{ club.description }}</span>

        <span class="clubs-view__card-meta">
          <span>{{ club.member_count }} {{ club.member_count === 1 ? 'miembro' : 'miembros' }}</span>
          <!-- Un club en el que solo participo no es mío, y se dice. -->
          <span
            v-if="!club.is_owner"
            class="clubs-view__guest"
          >
            <i class="pi pi-user" />
            Te invitaron
          </span>
        </span>
      </RouterLink>
    </div>

    <p
      v-if="error"
      class="clubs-view__error"
      role="alert"
    >
      {{ error }}
    </p>

    <!-- Con `v-if` y no solo con `v-model`, como el RecommendDialog: así el
         formulario no existe mientras nadie lo abre. -->
    <ClubFormDialog
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
import { useClubsStore } from '@/store/clubs'
import ClubFormDialog from '@/components/Clubs/ClubFormDialog.vue'

const clubsStore = useClubsStore()
const { clubs, isLoading, error } = storeToRefs(clubsStore)
const hasClubs = computed(() => clubsStore.hasClubs)

const router = useRouter()
const notifications = inject('notifications', null)

const showCreate = ref(false)
const openCreate = () => { showCreate.value = true }

onMounted(() => clubsStore.fetchMyClubs())

const handleCreate = async (form) => {
  const result = await clubsStore.createClub(form)

  if (!result.success) {
    notifications?.showError?.(result.message || 'No se pudo crear el club')
    return
  }

  showCreate.value = false
  notifications?.showSuccess?.('Club creado')
  // Se entra directo al club recién creado: lo siguiente que quiere el usuario
  // es invitar a alguien y elegir el primer ítem.
  router.push({ name: 'ClubDetail', params: { clubId: String(result.clubId) } })
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.clubs-view {
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

  &__guest {
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
