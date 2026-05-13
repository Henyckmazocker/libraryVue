import { storeToRefs } from 'pinia'
import { useSocialStore } from '@/store/social'

export function useFriends() {
  const socialStore = useSocialStore()
  const { friends, pendingRequests, pendingRequestsCount, hasFriends, isLoading } = storeToRefs(socialStore)
  const { fetchFriends, fetchPendingRequests, sendFriendRequest, acceptFriendRequest, rejectFriendRequest, removeFriend } = socialStore

  return {
    friends,
    pendingRequests,
    pendingRequestsCount,
    hasFriends,
    isLoading,
    fetchFriends,
    fetchPendingRequests,
    sendFriendRequest,
    acceptFriendRequest,
    rejectFriendRequest,
    removeFriend
  }
}
