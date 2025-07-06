<template>
  <div class="library-book-item-container">
    <div class="book-details">
      <div class="cover-image-container" v-if="book.coverUrl">
        <img :src="book.coverUrl" alt="Book Cover" class="cover-image" />
      </div>
      <div class="info-text">
        <h3 class="book-title">{{ book.title }}</h3>
        <p v-if="book.author" class="book-author"><strong>Author:</strong> {{ book.author }}</p>
        <p class="book-isbn"><strong>ISBN:</strong> {{ book.isbn }}</p>
        
        <div class="rating-section">
          <p class="current-rating">Rating: {{ book.rating !== null ? book.rating + '/5' : 'Not Rated' }}</p>
          <div class="stars-input">
            <!-- Loop through 5 star positions -->
            <div v-for="starPosition in 5" :key="'star-' + starPosition" class="star-container">
              <!-- Left half of the star -->
              <span
                class="star-half left-half"
                @click="setRating(starPosition - 0.5)"
                @mouseover="hoverRating = starPosition - 0.5"
                @mouseleave="hoverRating = 0"
                :class="{ 
                  'filled': (currentVisualRating >= starPosition - 0.5),
                  'hovered': (hoverRating >= starPosition - 0.5) && (hoverRating < starPosition)
                }"
              >★</span>
              <!-- Right half of the star -->
              <span
                class="star-half right-half"
                @click="setRating(starPosition)"
                @mouseover="hoverRating = starPosition"
                @mouseleave="hoverRating = 0"
                :class="{
                  'filled': (currentVisualRating >= starPosition),
                  'hovered': (hoverRating >= starPosition)
                }"
              >★</span>
            </div>
          </div>
        </div>
        
        <button @click="onDeleteBook" class="delete-button">Delete</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, ref, computed } from 'vue';

const props = defineProps({
  book: {
    type: Object,
    required: true,
    default: () => ({ isbn: "", title: "", author: "", coverUrl: "", rating: null })
  }
});

const emit = defineEmits(['delete-book', 'update-rating']);
const hoverRating = ref(0); // For hover effect on stars

const currentVisualRating = computed(() => {
  // If hoverRating is active (not 0), it takes precedence.
  // Otherwise, use the book's rating, defaulting to 0 if null (not rated).
  return hoverRating.value || (props.book.rating === null ? 0 : props.book.rating);
});

const onDeleteBook = () => {
  emit('delete-book', props.book.isbn);
};

const setRating = (ratingValue) => {
  // The comment about not supporting unrating by clicking the same star is noted.
  // We will always emit the new rating value, which can now be a 0.5 increment.
  emit('update-rating', { isbn: props.book.isbn, rating: ratingValue });
};

</script>

<style scoped>
.library-book-item-container {
  padding: 20px;
  background-color: #2c2c2c;
  border-radius: 15px; /* Slightly less rounded than main display */
  box-shadow: 0 4px 10px rgba(0,0,0,0.25);
  width: 100%;
}

.book-details {
  display: flex;
  align-items: flex-start;
  gap: 20px;
}

.cover-image-container {
  flex-shrink: 0;
}

.cover-image {
  width: 100px; /* Consistent with previous library list */
  height: auto;
  border-radius: 8px;
  border: 1px solid #444;
}

.info-text {
  text-align: left;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
}

.book-title {
  font-size: 1.3rem; /* Slightly smaller for list items */
  color: #e0e0e0;
  margin-top: 0;
  margin-bottom: 8px;
}

.book-author,
.book-isbn {
  font-size: 0.95rem; /* Slightly smaller */
  color: #bbb;
  margin-top: 0;
  margin-bottom: 4px;
}

.book-author strong,
.book-isbn strong {
  font-weight: 500;
  color: #888;
  margin-right: 6px;
}

.rating-section {
  margin-top: 10px;
  margin-bottom: 10px;
}

.current-rating {
  font-size: 0.9em;
  color: #ccc;
  margin-bottom: 5px;
}

.stars-input {
  display: flex;
  /* cursor: pointer; // Moved to individual star-half elements */
}

.star-container {
  position: relative;
  display: inline-block; /* Each container is one star unit */
  width: 1em;  /* Relative to its own font-size */
  height: 1em; /* Relative to its own font-size */
  font-size: 1.8em; /* This defines the actual size of the star symbol */
  line-height: 1;
  margin-right: 3px; /* Spacing between star units */
}

.star-half {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%; /* Takes full width of star-container */
  height: 100%; /* Takes full height of star-container */
  line-height: 1; /* Vertically centers the ★ character */
  text-align: center; /* Horizontally centers the ★ character */
  color: #555; /* Default empty star color for the ★ character */
  transition: color 0.2s ease-in-out;
  cursor: pointer;
  -webkit-font-smoothing: antialiased; /* Optional: for better text rendering */
  -moz-osx-font-smoothing: grayscale; /* Optional: for better text rendering */
}

.star-half.left-half {
  clip-path: polygon(0% 0%, 50% 0%, 50% 100%, 0% 100%);
}

.star-half.right-half {
  clip-path: polygon(50% 0%, 100% 0%, 100% 100%, 50% 100%);
}

.star-half.filled {
  color: #f5c518; /* IMDb yellow for filled stars */
}

.star-half.hovered {
  color: #f5b508; /* Slightly different for hover to show intent */
}

.delete-button {
  padding: 8px 15px; /* Adjusted padding */
  font-size: 0.85rem; /* Adjusted font size */
  font-weight: 500;
  color: #ffffff;
  background-color: #dc3545;
  border: 1px solid #dc3545;
  border-radius: 20px;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
  margin-top: 15px;
  align-self: flex-start; /* Aligns button to the left in the flex column */
}

.delete-button:hover {
  background-color: #c82333;
  border-color: #bd2130;
}
</style> 