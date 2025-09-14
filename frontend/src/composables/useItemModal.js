import { ref } from 'vue'

/**
 * Composable para control de modals de edición
 */
export function useItemModal() {
  const isOpen = ref(false)
  const currentItem = ref(null)
  const itemType = ref(null)
  
  const openModal = (item, type) => {
    currentItem.value = item
    itemType.value = type
    isOpen.value = true
  }
  
  const closeModal = () => {
    isOpen.value = false
    currentItem.value = null
    itemType.value = null
  }
  
  const resetModal = () => {
    closeModal()
  }
  
  return { 
    isOpen, 
    currentItem, 
    itemType, 
    openModal, 
    closeModal,
    resetModal
  }
}
