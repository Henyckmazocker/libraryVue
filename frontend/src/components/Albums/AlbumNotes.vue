<template>
  <MediaNotes
    media="album"
    :item-id="albumId"
  />
</template>

<script setup>
// Wrapper de configuración sobre MediaNotes, igual que los cinco *Search.vue lo
// son de GenericSearch. Conserva el nombre del fichero y de la prop para que
// ningún consumidor cambie; lo propio de los álbumes vive en mediaRegistry.
import MediaNotes from '@/components/shared/MediaNotes.vue'

defineProps({
  albumId: {
    type: Number,
    required: true
  }
})
</script>
