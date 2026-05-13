import { storeToRefs } from 'pinia'
import { useSocialStore } from '@/store/social'
import { ref, watch } from 'vue'

export function useUserSearch() {
  const socialStore = useSocialStore()
  const { searchResults, isSearching } = storeToRefs(socialStore)
  const { searchUsers, clearSearchResults } = socialStore

  const query = ref('')
  let debounceTimer = null

  watch(query, (val) => {
    clearTimeout(debounceTimer)
    if (!val || val.length < 2) {
      clearSearchResults()
      return
    }
    debounceTimer = setTimeout(() => {
      searchUsers(val)
    }, 300)
  })

  const clear = () => {
    query.value = ''
    clearSearchResults()
  }

  return {
    query,
    searchResults,
    isSearching,
    clear
  }
}
