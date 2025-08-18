<template>
  <div class="movie-actions-container">
    <!-- Save button (for new movies) -->
    <button 
      v-if="showSaveButton && isNew" 
      @click="onSave" 
      class="save-button" 
      :disabled="!canSave || loading"
    >
      <i class="fas fa-save"></i>
      <span v-if="!loading">{{ saveButtonText }}</span>
      <span v-else>Guardando...</span>
    </button>

    <!-- Update button (for existing movies) -->
    <button 
      v-if="showUpdateButton && !isNew" 
      @click="onUpdate" 
      class="update-button" 
      :disabled="loading"
    >
      <i class="fas fa-edit"></i>
      <span v-if="!loading">{{ updateButtonText }}</span>
      <span v-else>Actualizando...</span>
    </button>

    <!-- Delete button (for existing movies) -->
    <button 
      v-if="showDeleteButton && !isNew" 
      @click="onDelete" 
      class="delete-button" 
      :disabled="loading"
    >
      <i class="fas fa-trash"></i>
      <span v-if="!loading">{{ deleteButtonText }}</span>
      <span v-else>Eliminando...</span>
    </button>

    <!-- Custom actions slot -->
    <div v-if="$slots.customActions" class="custom-actions">
      <slot name="customActions"></slot>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
  // Item data
  item: {
    type: Object,
    required: true
  },
  
  // States
  isNew: {
    type: Boolean,
    default: false
  },
  canSave: {
    type: Boolean,
    default: true
  },
  canDelete: {
    type: Boolean,
    default: true
  },
  loading: {
    type: Boolean,
    default: false
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
  
  // Button texts
  saveButtonText: {
    type: String,
    default: 'Guardar'
  },
  updateButtonText: {
    type: String,
    default: 'Actualizar'
  },
  deleteButtonText: {
    type: String,
    default: 'Eliminar'
  }
});

const emit = defineEmits(['save', 'update', 'delete']);

// Methods
const onSave = () => {
  emit('save', props.item);
};

const onUpdate = () => {
  emit('update', props.item);
};

const onDelete = () => {
  emit('delete', props.item);
};
</script>

<style scoped>
.movie-actions-container {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 15px;
  align-items: center;
}

/* Base button styles */
.save-button,
.update-button,
.delete-button {
  padding: 8px 15px;
  font-size: 0.85rem;
  font-weight: 500;
  border-radius: 20px;
  cursor: pointer;
  border: none;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 120px;
  justify-content: center;
}

.save-button:disabled,
.update-button:disabled,
.delete-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

/* Save button - Blue gradient */
.save-button {
  background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
  color: #fff;
  box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3);
}

.save-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
  box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
  transform: translateY(-1px);
}

/* Update button - Green gradient */
.update-button {
  background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
  color: #fff;
  box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
}

.update-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #1e7e34 0%, #155724 100%);
  box-shadow: 0 4px 12px rgba(40, 167, 69, 0.4);
  transform: translateY(-1px);
}

/* Delete button - Red gradient */
.delete-button {
  background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
  color: #fff;
  box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

.delete-button:hover:not(:disabled) {
  background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
  box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
  transform: translateY(-1px);
}

/* Custom actions */
.custom-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

/* Responsive design */
@media (max-width: 768px) {
  .movie-actions-container {
    justify-content: center;
  }
  
  .save-button,
  .update-button,
  .delete-button {
    min-width: 100px;
    font-size: 0.8rem;
    padding: 6px 12px;
  }
}

@media (max-width: 480px) {
  .movie-actions-container {
    flex-direction: column;
    align-items: stretch;
  }
  
  .save-button,
  .update-button,
  .delete-button {
    width: 100%;
    min-width: auto;
  }
}

/* Icon styles */
.fas {
  font-size: 0.9em;
}

/* Loading state */
.save-button:disabled span,
.update-button:disabled span,
.delete-button:disabled span {
  opacity: 0.8;
}
</style>
