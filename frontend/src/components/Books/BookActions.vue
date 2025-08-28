<template>
  <div class="book-actions">
    <!-- Save button for new books -->
    <button 
      v-if="showSaveButton"
      @click="handleSave" 
      class="action-button save-button" 
      :disabled="!canSave"
      :title="saveButtonTitle"
    >
      <i class="fas fa-save"></i>
      <span v-if="showLabels">{{ saveButtonLabel }}</span>
    </button>
    <!-- Botón de editar que abre el popup -->
    <button v-if="showEditButton" @click="openEditPopup" class="action-button edit-popup-button" :title="'Editar libro'">
      <i class="fas fa-pencil-alt"></i>
      <span v-if="showLabels">Editar</span>
    </button>
    <EditBookPopup
      v-if="showEditPopup"
      :item="item"
      :allowed-statuses="allowedStatuses"
      @close="closeEditPopup"
    />
    <!-- Update button for existing books -->
    <button 
      v-if="showUpdateButton"
      @click="handleUpdate" 
      class="action-button update-button"
      :disabled="!canUpdate"
      :title="updateButtonTitle"
    >
      <i class="fas fa-edit"></i>
      <span v-if="showLabels">{{ updateButtonLabel }}</span>
    </button>

    <!-- Delete button -->
    <button 
      v-if="showDeleteButton"
      @click="handleDelete" 
      class="action-button delete-button"
      :title="deleteButtonTitle"
    >
      <i class="fas fa-trash"></i>
      <span v-if="showLabels">{{ deleteButtonLabel }}</span>
    </button>

    <!-- Custom actions -->
    <button
      v-for="action in customActions"
      :key="action.key"
      @click="handleCustomAction(action)"
      class="action-button custom-button"
      :class="action.class"
      :disabled="action.disabled"
      :title="action.title"
    >
      <i :class="action.icon"></i>
      <span v-if="showLabels && action.label">{{ action.label }}</span>
    </button>

    <!-- Loading state -->
    <div v-if="loading" class="action-loading">
      <i class="fas fa-spinner fa-spin"></i>
      <span v-if="showLabels">{{ loadingText }}</span>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';
import { ref } from 'vue';
import EditBookPopup from './EditBookPopup.vue';

// Props
const props = defineProps({
  // Book/item data
  item: {
    type: Object,
    required: true
  },
  
  allowedStatuses: {
    type: Array,
    default: () => []
  },
  // State flags
  isNew: {
    type: Boolean,
    default: true
  },
  canSave: {
    type: Boolean,
    default: true
  },
  canUpdate: {
    type: Boolean,
    default: true
  },
  canDelete: {
    type: Boolean,
    default: true
  },
  
  // Button visibility
  showSaveButton: {
    type: Boolean,
    default: true
  },
  showUpdateButton: {
    type: Boolean,
    default: true
  },
  showDeleteButton: {
    type: Boolean,
    default: true
  },
  showEditButton: {
    type: Boolean,
    default: true
  },
  
  // Button labels and titles
  showLabels: {
    type: Boolean,
    default: false
  },
  saveButtonLabel: {
    type: String,
    default: 'Guardar'
  },
  saveButtonTitle: {
    type: String,
    default: 'Guardar en biblioteca'
  },
  updateButtonLabel: {
    type: String,
    default: 'Actualizar'
  },
  updateButtonTitle: {
    type: String,
    default: 'Actualizar información'
  },
  deleteButtonLabel: {
    type: String,
    default: 'Eliminar'
  },
  deleteButtonTitle: {
    type: String,
    default: 'Eliminar de biblioteca'
  },
  
  // Custom actions
  customActions: {
    type: Array,
    default: () => []
  },
  
  // Loading state
  loading: {
    type: Boolean,
    default: false
  },
  loadingText: {
    type: String,
    default: 'Procesando...'
  },
  
  // Layout options
  direction: {
    type: String,
    default: 'horizontal', // 'horizontal' | 'vertical'
    validator: (value) => ['horizontal', 'vertical'].includes(value)
  },
  size: {
    type: String,
    default: 'medium', // 'small' | 'medium' | 'large'
    validator: (value) => ['small', 'medium', 'large'].includes(value)
  }
});

// Emits
const emit = defineEmits([
  'save', 
  'update', 
  'delete', 
  'custom-action',
  'edit-popup',
  'close'
]);

// Methods
const handleSave = () => {
  if (props.canSave && !props.loading) {
    emit('save', props.item);
  }
};

const handleUpdate = () => {
  if (props.canUpdate && !props.loading) {
    emit('update', props.item);
  }
};

const handleDelete = () => {
  if (props.canDelete && !props.loading) {
    emit('delete', props.item);
  }
};

const handleCustomAction = (action) => {
  if (!action.disabled && !props.loading) {
    emit('custom-action', { action, item: props.item });
  }
};
// Estado y métodos para el popup de edición
const showEditPopup = ref(false);
const openEditPopup = () => {
  showEditPopup.value = true;
};
const closeEditPopup = (updatedBook) => {
  showEditPopup.value = false;
  if (updatedBook) {
    emit('close', updatedBook);
  }
};
</script>

<style scoped>
/* Botón azul para guardar */
.save-button {
  background: none;
  color: #fff;
  padding: 8px 15px;
  font-size: 0.85rem;
  font-weight: 500;
  border-radius: 20px;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
  margin-top: 15px;
  align-self: flex-start;
  position: relative;
  z-index: 1;
}
.save-button:disabled {
  color: #888;
  cursor: not-allowed;
}

.delete-button {
  padding: 8px 15px; /* Adjusted padding */
  font-size: 0.85rem; /* Adjusted font size */
  font-weight: 500;
  color: #ffffff;
  background: none;
  border: 1px solid #dc3545;
  border-radius: 20px;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
  margin-top: 15px;
  align-self: flex-start; /* Aligns button to the left in the flex column */
}

.delete-button:disabled {
  color: #888;
  cursor: not-allowed;
}

.edit-popup-button {
  padding: 8px 15px; /* Adjusted padding */
  font-size: 0.85rem; /* Adjusted font size */
  font-weight: 500;
  color: #ffffff;
  background: none;
  border-radius: 20px;
  cursor: pointer;
  outline: none;
  transition: background-color 0.3s ease, border-color 0.3s ease;
  margin-top: 15px;
  align-self: flex-start; /* Aligns button to the left in the flex column */
}

.edit-popup-button:disabled {
  color: #888;
  cursor: not-allowed;
}

.book-actions {
  display: flex;
  gap: 10px;
  align-items: center;
}

.book-actions.vertical {
  flex-direction: column;
}

.action-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: var(--button-padding);
  font-size: var(--button-font-size);
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  font-weight: 500;
  min-width: var(--button-min-width);
  justify-content: center;
}

.action-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.action-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* Button types */

.edit-popup-button {
  color: white;
}

.save-button {
  color: white;
}
.update-button {
  color: white;
}

.delete-button {
  color: white;
}

.custom-button {
  color: white;
}

/* Size variations */
:root {
  --button-padding: 10px 15px;
  --button-font-size: 0.9rem;
  --button-min-width: auto;
}

.book-actions.small {
  --button-padding: 6px 10px;
  --button-font-size: 0.8rem;
}

.book-actions.large {
  --button-padding: 15px 20px;
  --button-font-size: 1rem;
  --button-min-width: 100px;
}

/* Loading state */
.action-loading {
  display: flex;
  align-items: center;
  gap: 8px;
  color: #007bff;
  font-size: var(--button-font-size);
  padding: var(--button-padding);
}

/* Direction variations */
.book-actions.vertical .action-button {
  width: 100%;
}

/* Responsive design */
@media (max-width: 768px) {
  .book-actions {
    flex-direction: column;
    gap: 8px;
  }
  
  .action-button {
    width: 100%;
    justify-content: center;
  }
  
  :root {
    --button-padding: 12px 16px;
    --button-font-size: 1rem;
  }
}
</style>
