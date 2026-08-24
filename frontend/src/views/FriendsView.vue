<template>
  <div class="friends-view">
    <Toast />
    <div class="friends-view__header">
      <h1 class="friends-view__title">
        <i class="pi pi-users" />
        Amigos
      </h1>
    </div>

    <TabView class="friends-view__tabs">
      <!-- Feed -->
      <TabPanel header="Feed">
        <div class="friends-view__tab-content">
          <FeedList />
        </div>
      </TabPanel>

      <!-- Amigos -->
      <TabPanel :header="`Amigos (${friends.length})`">
        <div class="friends-view__tab-content">
          <FriendsList
            :friends="friends"
            @remove="handleRemoveFriend"
          />
        </div>
      </TabPanel>

      <!-- Solicitudes -->
      <TabPanel :header="`Solicitudes${pendingRequestsCount > 0 ? ` (${pendingRequestsCount})` : ''}`">
        <div class="friends-view__tab-content">
          <FriendRequests
            :requests="pendingRequests"
            @accept="handleAcceptRequest"
            @reject="handleRejectRequest"
          />
        </div>
      </TabPanel>

      <!-- Buscar -->
      <TabPanel header="Buscar usuarios">
        <div class="friends-view__tab-content friends-view__search">
          <UserSearchInput />
          <div
            v-if="isSearching"
            class="friends-view__searching"
          >
            <i class="pi pi-spin pi-spinner" />
          </div>
          <div
            v-else-if="searchResults.length > 0"
            class="friends-view__results"
          >
            <UserSearchResult
              v-for="user in searchResults"
              :key="user.id"
              :user="user"
              @send-request="handleSendRequest"
            />
          </div>
          <p
            v-else-if="query && !isSearching && searchResults.length === 0"
            class="friends-view__no-results"
          >
            No se encontraron usuarios
          </p>
        </div>
      </TabPanel>

      <!-- Privacidad -->
      <TabPanel header="Privacidad">
        <div class="friends-view__tab-content">
          <PrivacySettingsPanel />
        </div>
      </TabPanel>
    </TabView>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { useFriends } from '@/composables/useFriends'
import { useUserSearch } from '@/composables/useUserSearch'
import { useSocialStore } from '@/store/social'
import FeedList from '@/components/Social/FeedList.vue'
import FriendsList from '@/components/Social/FriendsList.vue'
import FriendRequests from '@/components/Social/FriendRequests.vue'
import UserSearchInput from '@/components/Social/UserSearchInput.vue'
import UserSearchResult from '@/components/Social/UserSearchResult.vue'
import PrivacySettingsPanel from '@/components/Social/PrivacySettingsPanel.vue'

const toast = useToast()
const { friends, pendingRequests, pendingRequestsCount, fetchFriends, fetchPendingRequests, acceptFriendRequest, rejectFriendRequest, removeFriend, sendFriendRequest } = useFriends()
const { query, searchResults, isSearching } = useUserSearch()
const socialStore = useSocialStore()

onMounted(async () => {
  await Promise.all([
    fetchFriends(),
    fetchPendingRequests(),
    socialStore.loadFeed(true)
  ])
})

const handleSendRequest = async (userId) => {
  try {
    await sendFriendRequest(userId)
    toast.add({ severity: 'success', summary: 'Solicitud enviada', life: 3000 })
    // Mark user as request_sent in results
    const user = searchResults.value.find(u => u.id === userId)
    if (user) user.request_sent = true
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.message, life: 4000 })
  }
}

const handleAcceptRequest = async (friendshipId) => {
  try {
    await acceptFriendRequest(friendshipId)
    toast.add({ severity: 'success', summary: 'Solicitud aceptada', life: 3000 })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.message, life: 4000 })
  }
}

const handleRejectRequest = async (friendshipId) => {
  try {
    await rejectFriendRequest(friendshipId)
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.message, life: 4000 })
  }
}

const handleRemoveFriend = async (friendId) => {
  try {
    await removeFriend(friendId)
    toast.add({ severity: 'info', summary: 'Amigo eliminado', life: 3000 })
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Error', detail: err.message, life: 4000 })
  }
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.friends-view {
  max-width: 860px;
  margin: 0 auto;
  padding: spacing(lg);

  &__header {
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

  &__tabs {
    :deep(.p-tabview-panels) { padding: 0; }
    :deep(.p-tabview-panel) { padding-top: spacing(md); }
  }

  &__tab-content {
    min-height: 200px;
  }

  &__search {
    display: flex;
    flex-direction: column;
    gap: spacing(md);
  }

  &__results {
    display: flex;
    flex-direction: column;
    gap: spacing(xs);
  }

  &__searching,
  &__no-results {
    text-align: center;
    padding: spacing(xl);
    color: var(--color-text-secondary);
  }
}
</style>
