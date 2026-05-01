<template>
  <div class="list-item list-item--book" @click="handleClick">
    <img
      v-if="book.coverUrl && !imageError"
      :src="book.coverUrl"
      alt="Portada"
      class="list-item__cover"
      @error="handleImageError"
      loading="lazy"
    />
    <div v-else class="list-item__cover-placeholder">
      <i class="fas fa-book"></i>
    </div>

    <div class="list-item__info">
      <div class="list-item__title">{{ book.title }}</div>
      <div class="list-item__subtitle">{{ subtitle }}</div>

      <div v-if="book.user_rating && book.user_rating > 0" class="list-item__rating">
        <RatingComponent :rating="book.user_rating" :readonly="true" :size="'small'" />
      </div>

      <div v-if="book.userStatuses && book.userStatuses.length > 0" class="list-item__statuses">
        <span
          v-for="status in book.userStatuses"
          :key="status"
          class="list-item__status-badge"
        >
          {{ getStatusLabel(status) }}
        </span>
      </div>
    </div>

    <i class="fas fa-chevron-right list-item__arrow"></i>
  </div>
</template>

<script setup>
import { ref, computed, defineProps, defineEmits } from 'vue';
import RatingComponent from '@/components/common/RatingComponent.vue';

const props = defineProps({
  book: {
    type: Object,
    required: true
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['click']);

const imageError = ref(false);

const handleClick = () => {
  emit('click', props.book);
};

const handleImageError = () => {
  imageError.value = true;
};

// Subtitle unificado: "Autor • Año" cuando ambos existen
const subtitle = computed(() => {
  const author = props.book.author || 'Autor desconocido';
  const year = props.book.publicationDate
    ? props.book.publicationDate.toString().substring(0, 4)
    : '';
  return year ? `${author} • ${year}` : author;
});

// Función para obtener el label legible del status
const getStatusLabel = (statusKey) => {
  const status = props.allowedStatuses.find(s => s.key === statusKey);
  return status ? status.name : statusKey;
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/list-item' as *;

.list-item--book {
  @include list-item('book', '2/3', 75px);
}
</style>
