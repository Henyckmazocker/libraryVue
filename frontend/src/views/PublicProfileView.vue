<template>
  <div class="public-profile-view">
    <div
      v-if="loading"
      class="public-profile-view__loading"
    >
      <i class="pi pi-spin pi-spinner" />
    </div>

    <div
      v-else-if="error"
      class="public-profile-view__error"
    >
      <i class="pi pi-exclamation-triangle" />
      <p>{{ error }}</p>
    </div>

    <template v-else-if="profile">
      <div class="public-profile-view__header">
        <div class="public-profile-view__avatar">
          <img
            v-if="profile.picture"
            :src="profile.picture"
            :alt="profile.username"
            loading="lazy"
            decoding="async"
          >
          <i
            v-else
            class="pi pi-user"
          />
        </div>
        <div class="public-profile-view__info">
          <h1 class="public-profile-view__username">
            {{ profile.username }}
          </h1>
          <!-- Solo si aporta algo: cuando el usuario no ha elegido `username`, el
               caso de uso devuelve `name` en los DOS campos
               (`GetPublicProfileUseCase.php:58`) y la cabecera repetiría el mismo
               texto dos veces, una grande y otra pequeña. -->
          <p
            v-if="profile.name && profile.name !== profile.username"
            class="public-profile-view__display"
          >
            {{ profile.name }}
          </p>
        </div>

        <!-- Los cuatro valores de `friend_status`, con la guarda de `isCurrentUser`
             sobre los cuatro y no solo sobre «Agregar»: uno no se envía solicitudes
             a sí mismo, pero es una línea y evita pintar una rama imposible. -->
        <div
          v-if="!isCurrentUser"
          class="public-profile-view__actions"
        >
          <Button
            v-if="profile.friend_status === 'none'"
            :label="t('social.addFriend')"
            icon="pi pi-user-plus"
            :loading="requestSending"
            @click="handleSendRequest"
          />
          <Tag
            v-else-if="profile.friend_status === 'pending_sent'"
            :value="t('social.requestSent')"
            severity="secondary"
          />
          <Tag
            v-else-if="profile.friend_status === 'friends'"
            :value="t('social.friends')"
            severity="success"
          />
          <!-- A quien te ha escrito se le responde aquí mismo: el caso de uso ya
               devuelve el `friendship_id` que necesitan las dos acciones, así que
               no hay razón para mandarle a /friends. -->
          <template v-else-if="profile.friend_status === 'pending_received'">
            <span class="public-profile-view__request-hint">
              {{ t('social.wantsToBeFriendShort') }}
            </span>
            <div class="public-profile-view__request-buttons">
              <Button
                :label="t('common.accept')"
                icon="pi pi-check"
                severity="success"
                size="small"
                :loading="requestAnswering"
                @click="handleAcceptRequest"
              />
              <Button
                :label="t('common.reject')"
                icon="pi pi-times"
                severity="danger"
                text
                size="small"
                :loading="requestAnswering"
                @click="handleRejectRequest"
              />
            </div>
          </template>
        </div>
      </div>

      <!-- Listas públicas. Solo aparece la sección si hay alguna: un perfil sin
           listas no gana nada con un hueco que diga que no tiene. Lo que llega
           aquí ya viene filtrado por el `WHERE` del backend, no por el cliente. -->
      <section
        v-if="hasUserLists"
        class="public-profile-view__lists"
      >
        <h2 class="public-profile-view__lists-title">
          <i class="pi pi-list" />
          {{ t('lists.public') }}
        </h2>

        <div class="public-profile-view__lists-grid">
          <RouterLink
            v-for="list in userLists"
            :key="list.id"
            class="public-profile-view__list-card"
            :to="{ name: 'ListDetail', params: { listId: String(list.id) } }"
          >
            <!-- Con `{{ }}`, nunca `v-html`: lo escribe otra persona. -->
            <span class="public-profile-view__list-name">{{ list.name }}</span>
            <span
              v-if="list.description"
              class="public-profile-view__list-description"
            >{{ list.description }}</span>
            <span class="public-profile-view__list-count">
              {{ t('library.itemCount', { n: list.item_count }) }}
            </span>
          </RouterLink>
        </div>
      </section>

      <!-- El diario. Mismo trato que las listas: la sección solo existe si hay
           entradas, y lo que decide si las hay es el backend. `get_user_journal`
           devuelve la MISMA lista vacía sin amistad aceptada, con el interruptor
           apagado y sin entradas, así que desde aquí no se puede distinguir
           —ni hace falta— «no lo enseña» de «no tiene nada». -->
      <section
        v-if="hasUserEntries"
        class="public-profile-view__journal"
      >
        <h2 class="public-profile-view__journal-title">
          <i class="fas fa-book-open" />
          {{ t('journal.userTitle', { name: profile.username }) }}
        </h2>

        <section
          v-for="dia in userByDay"
          :key="dia.date"
          class="public-profile-view__journal-day"
        >
          <h3 class="public-profile-view__journal-day-title">
            {{ journalDayLabel(dia.date) }}
          </h3>

          <JournalEntryRow
            v-for="entrada in dia.entries"
            :key="entrada.id"
            :entry="entrada"
            readonly
          />
        </section>

        <button
          v-if="userHasMore"
          type="button"
          class="btn btn--secondary public-profile-view__journal-more"
          :class="{ 'is-loading': isLoadingUser }"
          :disabled="isLoadingUser"
          @click="journalStore.loadMoreUserJournal()"
        >
          {{ t('journal.loadMore') }}
        </button>
      </section>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import { useAuthStore } from '@/store/auth'
import { useSocialStore } from '@/store/social'
import { storeToRefs } from 'pinia'
import { useListsStore } from '@/store/lists'
import { useJournalStore } from '@/store/journal'
import JournalEntryRow from '@/components/Journal/JournalEntryRow.vue'
import { journalDayLabel } from '@/utils/dates'
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const route = useRoute()
const toast = useToast()
const authStore = useAuthStore()
const socialStore = useSocialStore()
const listsStore = useListsStore()
const { userLists } = storeToRefs(listsStore)
const hasUserLists = computed(() => listsStore.hasUserLists)
const journalStore = useJournalStore()
const { userByDay, userHasMore, isLoadingUser } = storeToRefs(journalStore)
const hasUserEntries = computed(() => journalStore.hasUserEntries)

const profile = ref(null)
const loading = ref(true)
const error = ref(null)
const requestSending = ref(false)
const requestAnswering = ref(false)

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
      error.value = response.data.message || t('toasts.profileNotFound')
    }
  } catch {
    error.value = t('toasts.profileFailed')
  } finally {
    loading.value = false
  }

  // Fuera del try del perfil y sin `await` que lo bloquee: si las listas
  // fallan, el perfil ya se ha pintado y lo único que falta es la sección.
  listsStore.fetchUserLists(route.params.username)
  journalStore.fetchUserJournal(route.params.username)
})

const handleSendRequest = async () => {
  requestSending.value = true
  try {
    await socialStore.sendFriendRequest(profile.value.id)
    // El estado vive en el `ref` local: es un cambio de un campo que el usuario
    // acaba de provocar, y volver a pedir el perfil entero no diría nada nuevo.
    profile.value.friend_status = 'pending_sent'
    toast.add({ severity: 'success', summary: t('toasts.requestSent'), life: 3000 })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.message, life: 4000 })
  } finally {
    requestSending.value = false
  }
}

// Calcados de FriendsView.vue:116-131 —el mismo try/catch, los mismos toasts y el
// rechazo igualmente sin toast de éxito— para que responder desde el perfil y
// responder desde /friends se comporten igual. `acceptFriendRequest` refresca de
// paso la lista de amigos del store, así que /friends ya queda al día.
// El `friendship_id` nunca es null bajo `pending_received`, pero se comprueba
// antes de llamar: es más barato que descubrirlo por un 400 del backend.
const handleAcceptRequest = async () => {
  if (!profile.value.friendship_id) return
  requestAnswering.value = true
  try {
    await socialStore.acceptFriendRequest(profile.value.friendship_id)
    profile.value.friend_status = 'friends'
    toast.add({ severity: 'success', summary: t('toasts.requestAccepted'), life: 3000 })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.message, life: 4000 })
  } finally {
    requestAnswering.value = false
  }
}

const handleRejectRequest = async () => {
  if (!profile.value.friendship_id) return
  requestAnswering.value = true
  try {
    await socialStore.rejectFriendRequest(profile.value.friendship_id)
    profile.value.friend_status = 'none'
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.message, life: 4000 })
  } finally {
    requestAnswering.value = false
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
    i { font-size: var(--font-size-4xl); display: block; margin-bottom: spacing(md); }
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
    i { font-size: var(--font-size-3xl); color: var(--color-text-secondary); }
  }

  &__info {
    flex: 1;
    min-width: 0;
  }

  &__username {
    font-size: var(--font-size-xl);
    font-weight: 700;
    color: var(--color-text);
    margin: 0 0 spacing(2xs);
  }

  &__display {
    font-size: var(--font-size-base);
    color: var(--color-text-secondary);
    margin: 0 0 spacing(xs);
  }

  // Solo `pending_received` pinta más de un elemento aquí —el aviso y los dos
  // botones—; en los otros tres estados hay un control suelto al que esta regla
  // no le cambia nada.
  &__actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: spacing(xs);
    flex-shrink: 0;
  }

  &__request-hint {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    text-align: right;
  }

  &__request-buttons {
    display: flex;
    gap: spacing(xs);
  }

  &__lists {
    margin-top: spacing(xl);
  }

  &__lists-title {
    display: flex;
    align-items: center;
    gap: spacing(sm);
    margin-bottom: spacing(md);
    font-size: var(--font-size-md);
    font-weight: 700;
    color: var(--color-text);

    i { color: var(--color-primary); }
  }

  &__lists-grid {
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
  }

  &__list-card {
    display: flex;
    flex-direction: column;
    gap: spacing(3xs);
    padding: spacing(md);
    border-radius: radius(md);
    background: var(--color-background-mute);
    // Hairline suave de tarjeta decorativa, no el borde de inputs y botones.
    border: 1px solid var(--color-border-light);
    text-decoration: none;

    &:hover { border-color: var(--color-primary); }
  }

  &__list-name {
    font-weight: 600;
    color: var(--color-text);
  }

  &__list-description {
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
  }

  &__list-count {
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
  }

  &__journal {
    margin-top: spacing(xl);
  }

  &__journal-title {
    display: flex;
    align-items: center;
    gap: spacing(sm);
    margin-bottom: spacing(md);
    font-size: var(--font-size-md);
    font-weight: 700;
    color: var(--color-text);

    i { color: var(--color-primary); }
  }

  &__journal-day {
    margin-bottom: spacing(md);
  }

  &__journal-day-title {
    margin: 0 0 spacing(2xs);
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--color-text-secondary);
  }

  &__journal-more {
    width: 100%;
  }
}
</style>
