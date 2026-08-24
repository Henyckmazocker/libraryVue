<template>
  <div class="rating-section">
    <div
      v-if="editable"
      class="stars-input"
    >
      <div
        v-for="starPosition in 5"
        :key="'star-' + starPosition"
        class="star-wrapper"
      >
        <!-- Single button that detects left/right hover -->
        <button
          type="button"
          class="star-button"
          :aria-label="`Valorar con ${starPosition} ${starPosition === 1 ? 'estrella' : 'estrellas'}`"
          :class="{ 'active': getVisualRating() >= starPosition - 0.5 }"
          @click="handleStarClick($event, starPosition)"
          @mousemove="handleStarHover($event, starPosition)"
          @mouseleave="hoverRating = 0"
          @focus="hoverRating = starPosition"
          @blur="hoverRating = 0"
          @keydown.left.prevent="focusStar(starPosition - 1)"
          @keydown.right.prevent="focusStar(starPosition + 1)"
        >
          <i
            :class="getEditableStarClass(starPosition)"
            aria-hidden="true"
          />
        </button>
      </div>
    </div>
    <div
      v-else
      class="stars-display"
    >
      <div
        v-for="starPosition in 5"
        :key="'display-star-' + starPosition"
        class="star-display-wrapper"
      >
        <i 
          class="star-icon"
          :class="getStarClass(starPosition)"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, defineProps, defineEmits, watch } from 'vue';

// Props
const props = defineProps({
  rating: {
    type: Number,
    default: null,
    validator: (value) => value === null || (value >= 0 && value <= 5)
  },
  editable: {
    type: Boolean,
    default: true
  },
  label: {
    type: String,
    default: 'Rating'
  },
  size: {
    type: String,
    default: 'medium',
    validator: (value) => ['small', 'medium', 'large'].includes(value)
  }
});

// Emits
const emit = defineEmits(['update:rating', 'rating-changed']);

// Reactive data
const hoverRating = ref(0);
const currentRating = ref(props.rating);

// Methods
const setRating = (rating) => {
  if (!props.editable) return;
  
  currentRating.value = rating;
  emit('update:rating', rating);
  emit('rating-changed', rating);
};

// Flechas izquierda/derecha entre estrellas: con Tab se sale del grupo, que es
// lo que se espera de un widget de valoración.
const focusStar = (starPosition) => {
  if (starPosition < 1 || starPosition > 5) return;
  const buttons = document.querySelectorAll('.stars-input .star-button');
  buttons[starPosition - 1]?.focus();
};

const handleStarHover = (event, starPosition) => {
  if (!props.editable) return;
  
  const rect = event.target.getBoundingClientRect();
  const x = event.clientX - rect.left;
  const width = rect.width;
  const isLeftHalf = x < width / 2;
  
  if (isLeftHalf) {
    hoverRating.value = starPosition - 0.5; // Media estrella
  } else {
    hoverRating.value = starPosition; // Estrella completa
  }
};

const handleStarClick = (event, starPosition) => {
  if (!props.editable) return;
  
  const rect = event.target.getBoundingClientRect();
  const x = event.clientX - rect.left;
  const width = rect.width;
  const isLeftHalf = x < width / 2;
  
  const newRating = isLeftHalf ? starPosition - 0.5 : starPosition;
  setRating(newRating);
};

const getVisualRating = () => {
  return hoverRating.value > 0 ? hoverRating.value : (currentRating.value || 0);
};

const getEditableStarClass = (starPosition) => {
  const visualRating = getVisualRating();
  
  if (visualRating >= starPosition) {
    return 'fas fa-star'; // Full star
  } else if (visualRating >= starPosition - 0.5) {
    return 'fas fa-star-half-alt'; // Half star
  } else {
    return 'far fa-star'; // Empty star
  }
};

const getStarClass = (starPosition) => {
  const currentRatingValue = props.rating || 0;
  
  if (currentRatingValue >= starPosition) {
    return 'fas fa-star filled'; // Full star
  } else if (currentRatingValue >= starPosition - 0.5) {
    return 'fas fa-star-half-alt half-filled'; // Half star
  } else {
    return 'far fa-star empty'; // Empty star
  }
};

// Watch for external changes
watch(() => props.rating, (newValue) => {
  currentRating.value = newValue;
});
</script>

<style scoped lang="scss">
.rating-section {
  margin: 15px 0;
}

.current-rating {
  margin: 0 0 8px 0;
  font-weight: 500;
  color: var(--color-text);
}

.stars-input,
.stars-display {
  display: flex;
  align-items: center;
  gap: 4px;
}

/* Editable stars */
.star-wrapper {
  display: inline-block;
  margin: 0 2px;
}

.star-button {
  background: none;
  border: none;
  padding: 4px;
  cursor: pointer;
  font-size: var(--star-size, 1.2rem);
  color: var(--color-text-secondary);
  transition: all 0.2s ease;
  line-height: 1;
  border-radius: 4px;
}

.star-button:hover {
  color: var(--color-rating-star);
  transform: scale(1.1);
  background-color: rgba(255, 215, 0, 0.1);
}

.star-button.active {
  color: var(--color-rating-star);
  text-shadow: 0 0 3px rgba(255, 215, 0, 0.3);
}

/* Display-only stars */
.star-display-wrapper {
  display: inline-block;
}

.star-icon {
  font-size: var(--star-size, 1.2rem);
  margin: 0 2px;
  transition: all 0.2s ease;
}

.star-icon.filled {
  color: var(--color-rating-star);
  text-shadow: 0 0 3px rgba(255, 215, 0, 0.3);
}

.star-icon.half-filled {
  color: var(--color-rating-star);
  text-shadow: 0 0 3px rgba(255, 215, 0, 0.3);
}

.star-icon.empty {
  color: var(--color-text-secondary);
}

/* Size variations */
:root {
  --star-size: 1.2rem;
}

.rating-section.small {
  --star-size: 1rem;
}

.rating-section.large {
  --star-size: 1.5rem;
}
</style>
