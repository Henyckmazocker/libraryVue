<template>
  <div class="public-profile-view">
    <div v-if="loading" class="public-profile-view__loading">
      <i class="pi pi-spin pi-spinner" />
    </div>

    <div v-else-if="error" class="public-profile-view__error">
      <i class="pi pi-exclamation-triangle" />
      <p>{{ error }}</p>
    </div>

    <template v-else-if="profile">
      <div class="public-profile-view__header">
        <div class="public-profile-view__avatar">
          <img v-if="profile.avatar" :src="profile.avatar" :alt="profile.username" />
          <i v-else class="pi pi-user" />
        </div>
        <div class="public-profile-view__info">
          <h1 class="public-profile-view__username">{{ profile.username }}</h1>
          <p v-if="profile.display_name" class="public-profile-view__display">{{ profile.display_name }}</p>
          <p v-if="profile.bio" class="public-profile-view__bio">{{ profile.bio }}</p>
        </div>

        <div class="public-profile-view__actions">
          <Button
            v-if="!profile.is_friend && !profile.request_sent && !isCurrentUser"
            label="Agregar amigo"
            icon="pi pi-user-plus"
            @click="handleSendRequest"
            :loading="requestSending"
          />
          <Tag v-else-if="profile.request_sent" value="Solicitud enviada" severity="secondary" />
          <Tag v-else-if="profile.is_friend" value="Amigo/a" severity="success" />
        </div>
      </div>

      <!-- Stats -->
      <div v-if="profile.stats" class="public-profile-view__stats">
        <div class="public-profile-view__stat">
          <span class="public-profile-view__stat-value">{{ profile.stats.books ?? 0 }}</span>
          <span class="public-profile-view__stat-label">Libros</span>
        </div>
        <div class="public-profile-view__stat">
          <span class="public-profile-view__stat-value">{{ profile.stats.movies ?? 0 }}</span>
          <span class="public-profile-view__stat-label">Películas</span>
        </div>
        <div class="public-profile-view__stat">
          <span class="public-profile-view__stat-value">{{ profile.stats.games ?? 0 }}</span>
          <span class="public-profile-view__stat-label">Juegos</span>
        </div>
        <div class="public-profile-view__stat">
          <span class="public-profile-view__stat-value">{{ profile.stats.albums ?? 0 }}</span>
          <span class="public-profile-view__stat-label">Álbumes</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '@/store/auth'
import { useSocialStore } from '@/store/social'

const route = useRoute()
const toast = useToast()
const authStore = useAuthStore()
const socialStore = useSocialStore()

const profile = ref(null)
const loading = ref(true)
const error = ref(null)
const requestSending = ref(false)

const isCurrentUser = computed(() => {
  return authStore.user?.username === route.params.username
})

onMounted(async () => {
  try {
    const response = await authStore.authenticatedApiCall('get_public_profile', {
      username: route.params.username
    })
    if (response.data.status === 'success') {
      profile.value = response.data.data
    } else {
      error.value = response.data.message || 'Perfil no encontrado'
    }
  } catch {
    error.value = 'No se pudo cargar el perfil'
  } finally {
    loading.value = false
  }
})

const handleSendRequest = async () => {
  requestSending.value = true
  try {
    await socialStore.sendFriendRequest(profile.value.id)
    profile.value.request_sent = true
    toast.add({ severity: 'success', summary: 'Solicitud enviada', life: 3000 })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.message, life: 4000 })
  } finally {
    requestSending.value = false
  }
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.public-profile-view {
  max-width: 720px;
  margin: 0 auto;
  padding: spacing(lg);

  &__loading,
  &__error {
    text-align: center;
    padding: spacing(3xl);
    color: var(--color-text-secondary);
    i { font-size: 3rem; display: block; margin-bottom: spacing(md); }
  }

  &__header {
    display: flex;
    align-items: flex-start;
    gap: spacing(lg);
    padding: spacing(xl);
    background: var(--color-background-mute);
    border-radius: radius(lg);
    margin-bottom: spacing(lg);
  }

  &__avatar {
    width: 80px;
    height: 80px;
    border-radius: radius(full);
    overflow: hidden;
    background: var(--color-background-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    img { width: 100%; height: 100%; object-fit: cover; }
    i { font-size: 2.5rem; color: var(--color-text-secondary); }
  }

  &__info {
    flex: 1;
    min-width: 0;
  }

  &__username {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--color-text);
    margin: 0 0 spacing(2xs);
  }

  &__display {
    font-size: 1rem;
    color: var(--color-text-secondary);
    margin: 0 0 spacing(xs);
  }

  &__bio {
    font-size: 0.875rem;
    color: var(--color-text);
    margin: 0;
  }

  &__stats {
    display: flex;
    gap: spacing(md);
    flex-wrap: wrap;
  }

  &__stat {
    flex: 1;
    min-width: 80px;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: spacing(md);
    background: var(--color-background-mute);
    border-radius: radius(md);

    &-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--color-primary);
    }

    &-label {
      font-size: 0.75rem;
      color: var(--color-text-secondary);
    }
  }
}
</style>
