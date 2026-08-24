<template>
  <MediaNotes
    media="game"
    :item-id="gameId"
  />
</template>

<script setup>
// Wrapper de configuración sobre MediaNotes, igual que los cinco *Search.vue lo
// son de GenericSearch. Conserva el nombre del fichero y de la prop para que
// ningún consumidor cambie; lo propio de los juegos vive en mediaRegistry.
import MediaNotes from '@/components/shared/MediaNotes.vue'

defineProps({
  gameId: {
    type: Number,
    required: true
  }
})
</script>
