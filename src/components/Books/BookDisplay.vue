<template>
  <div class="book-display-container" v-if="book && book.title">
    <div class="book-details">
      <div class="cover-image-container" v-if="book.coverUrl">
        <img :src="book.coverUrl" alt="Book Cover" class="cover-image-large" />
      </div>
      <div class="info-text-large">
        <h2 class="book-title-large">{{ book.title }}</h2>
        <p v-if="book.author" class="book-author-large"><strong>Author:</strong> {{ book.author }}</p>
        <p class="book-isbn-large"><strong>ISBN:</strong> {{ book.isbn }}</p>

        <!-- Status Selector - Using Vue Multiselect -->
        <div v-if="showAddButton && normalizedAllowedUserStatuses.length > 0" class="status-selector-container">
          <p class="status-selector-title"><strong>Status:</strong> (select one or more)</p>
          <vue-multiselect
            v-model="selectedUserStatuses"
            :options="normalizedAllowedUserStatuses"
            :multiple="true"
            :close-on-select="false"
            :clear-on-select="false"
            placeholder="Pick statuses"
            class="status-vue-multiselect" 
          >
            <template #option="{ option }">
              {{ option.charAt(0).toUpperCase() + option.slice(1) }}
            </template>
            <template #tag="{ option }">
              <span class="multiselect__tag">
                <span>{{ option.charAt(0).toUpperCase() + option.slice(1) }}</span>
              </span>
            </template>
          </vue-multiselect>
        </div>

        <div class="actions-container">
          <button 
            v-if="showAddButton" 
            @click="onAddBook" 
            class="add-button"
            :disabled="!book.title || book.title === 'Title not found' || selectedUserStatuses.length === 0"
          >
            Add to My Library
          </button>
          <button 
            v-if="showDeleteButton" 
            @click="onDeleteBook" 
            class="delete-button"
          >
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, ref, watch, computed } from 'vue';
import VueMultiselect from 'vue-multiselect'; // Import the component

const props = defineProps({
  book: {
    type: Object,
    required: true,
    default: () => ({ isbn: "", title: "", author: "", coverUrl: "" })
  },
  showAddButton: {
    type: Boolean,
    default: true
  },
  showDeleteButton: { 
    type: Boolean,
    default: false
  },
  allowedUserStatuses: { // Added prop
    type: Array,
    required: true
  }
});

const emit = defineEmits(['add-book-to-library', 'delete-book']);

// Initialize selectedUserStatuses with a default value, e.g., 'to buy'
// Ensure the default is part of the allowedUserStatuses passed or the hardcoded default here
const selectedUserStatuses = ref([]);

// Computed property to normalize allowedUserStatuses
const normalizedAllowedUserStatuses = computed(() => {
  return Array.isArray(props.allowedUserStatuses)
    ? props.allowedUserStatuses.map(s => typeof s === 'string' ? s : String(s))
    : [];
});

// Watch for changes in the book prop to reset statuses if a new book is displayed
watch(() => props.book, (newBook, oldBook) => {
  if (newBook && newBook.isbn !== oldBook?.isbn) {
    // Reset to default when a new book is loaded to ensure a clean state
    // Or, if you prefer to keep last selection, remove this reset logic
    const defaultStatus = props.allowedUserStatuses.includes('want to buy') ? 'want to buy' : props.allowedUserStatuses[0];
    selectedUserStatuses.value = defaultStatus ? [defaultStatus] : [];
  }
}, { deep: true });

// Asegurar que siempre haya al menos un status seleccionado por defecto si allowedUserStatuses tiene elementos
watch(() => props.allowedUserStatuses, (newAllowed) => {
  if (Array.isArray(newAllowed) && newAllowed.length > 0 && selectedUserStatuses.value.length === 0) {
    // Preferir 'want to buy' si existe, si no el primero
    const defaultStatus = newAllowed.includes('want to buy') ? 'want to buy' : newAllowed[0];
    selectedUserStatuses.value = [defaultStatus];
  }
}, { immediate: true });

const onAddBook = () => {
  // Garantizar que nunca se emita un array vacío o nulo
  if (!Array.isArray(selectedUserStatuses.value) || selectedUserStatuses.value.length === 0) {
    alert("Please select at least one status for the book.");
    return;
  }
  // Emitir siempre un array válido
  emit('add-book-to-library', { book: props.book, statuses: [...selectedUserStatuses.value] });
};

const onDeleteBook = () => {
  emit('delete-book', props.book.isbn);
};
</script>

<style scoped>
/* CSS import for Vue Multiselect is now handled globally in main.js */

.book-display-container {
  margin-top: 20px;
  padding: 25px;
  background-color: #2c2c2c;
  border-radius: 20px;
  width: 100%;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.book-details {
  display: flex;
  align-items: flex-start;
  gap: 20px;
}

.cover-image-container {
  flex-shrink: 0;
}

.cover-image-large {
  width: 120px;
  height: auto;
  border-radius: 10px;
  border: 1px solid #444;
}

.info-text-large {
  text-align: left;
  flex-grow: 1;
  display: flex;
  flex-direction: column;
}

.book-title-large {
  font-size: 1.6rem;
  color: #e0e0e0;
  margin-top: 0;
  margin-bottom: 10px;
}

.book-author-large,
.book-isbn-large {
  font-size: 1.1rem;
  color: #bbb;
  margin-top: 0;
  margin-bottom: 5px;
}

.book-author-large strong,
.book-isbn-large strong {
  font-weight: 500;
  color: #888;
  margin-right: 8px;
}

.actions-container {
  margin-top: 20px;
  display: flex;
  gap: 10px;
}

.add-button, .delete-button {
  padding: 10px 20px;
  font-size: 0.9rem;
  font-weight: 500;
  color: #ffffff;
  border-radius: 20px;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
  border: 1px solid transparent;
}

.add-button {
  background-color: #28a745;
  border-color: #28a745;
}

.add-button:hover {
  background-color: #218838;
  border-color: #1e7e34;
}

.add-button:disabled {
  background-color: #555;
  border-color: #444;
  color: #888;
  cursor: not-allowed;
}

.delete-button {
  background-color: #dc3545;
  border-color: #dc3545;
}

.delete-button:hover {
  background-color: #c82333;
  border-color: #bd2130;
}

/* Styles for Status Selector - Updated for Vue Multiselect */
.status-selector-container {
  margin-top: 15px;
  margin-bottom: 15px;
  /* No need for padding/background here if vue-multiselect handles its own styling well */
}

.status-selector-title {
  font-size: 0.95rem;
  color: #ccc;
  margin-bottom: 8px;
}

/* Custom styling for vue-multiselect to match the dark theme */
/* Targeting classes used by vue-multiselect */
.status-vue-multiselect .multiselect__tags {
  background-color: #2c2c2c;
  border: 1px solid #555;
  border-radius: 5px;
  padding-top: 7px; /* Adjust for better vertical alignment of tags */
}

.status-vue-multiselect .multiselect__input,
.status-vue-multiselect .multiselect__single {
  background-color: #2c2c2c;
  color: #eee;
}

.status-vue-multiselect .multiselect__content-wrapper {
  background-color: #2c2c2c;
  border: 1px solid #555;
  border-top: none;
  border-radius: 0 0 5px 5px;
}

.status-vue-multiselect .multiselect__option {
  color: #eee;
  background-color: #2c2c2c;
}

.status-vue-multiselect .multiselect__option--highlight {
  background-color: #007bff; /* Highlight color from your theme */
  color: white;
}

.status-vue-multiselect .multiselect__option--selected {
  background-color: #4a4a4a; /* Slightly different for selected, or same as highlight */
  color: #eee;
  font-weight: bold;
}

.status-vue-multiselect .multiselect__tag {
  background-color: #007bff; /* Tag background */
  color: white; /* Tag text color */
  margin-bottom: 2px; /* Add a small margin below tags if they wrap */
  border-radius: 4px;
}

.status-vue-multiselect .multiselect__tag-icon {
  background: none; /* Remove default background */
  border-left: 1px solid rgba(255, 255, 255, 0.5); /* Separator for the icon */
}

.status-vue-multiselect .multiselect__tag-icon:after {
 content: "×"; /* Use a standard 'x' character */
 color: rgba(255, 255, 255, 0.7); /* Make it slightly transparent */
 font-size: 14px; /* Adjust size */
 font-weight: bold;
}

.status-vue-multiselect .multiselect__tag-icon:hover {
  background-color: #0056b3; /* Darker on hover for the remove icon background */
}

.status-vue-multiselect .multiselect__placeholder {
  color: #888; /* Placeholder text color */
  margin-bottom: 0; /* Reset margin if needed */
  padding-top: 0; /* Reset padding if needed */
}

/* Remove old native select styles */
/* .status-multiselect, .status-multiselect option, .status-multiselect option:checked ... */
</style>