import { storeToRefs } from 'pinia'
import { useSocialStore } from '@/store/social'
import { onMounted, onUnmounted, ref } from 'vue'

export function useFeed() {
  const socialStore = useSocialStore()
  const { feed, feedHasMore, feedLoading, hasFeed } = storeToRefs(socialStore)
  const { loadFeed } = socialStore

  // IntersectionObserver for infinite scroll
  const sentinel = ref(null)
  let observer = null

  const initInfiniteScroll = () => {
    if (!sentinel.value) return
    observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && feedHasMore.value && !feedLoading.value) {
          loadFeed()
        }
      },
      { threshold: 0.1 }
    )
    observer.observe(sentinel.value)
  }

  onMounted(() => {
    initInfiniteScroll()
  })

  onUnmounted(() => {
    observer?.disconnect()
  })

  return {
    feed,
    feedHasMore,
    feedLoading,
    hasFeed,
    loadFeed,
    sentinel
  }
}
