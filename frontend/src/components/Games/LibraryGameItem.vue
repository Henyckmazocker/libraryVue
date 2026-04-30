<template>
  <div class="library-game-item-container">
    <div class="game-details">
      <div class="cover-image-container" v-if="game.coverUrl">
        <img :src="game.coverUrl" alt="Game Cover" class="cover-image" />
      </div>
      <div class="info-text">
        <h3 class="game-title">{{ game.title || game.name }}</h3>
        <p v-if="game.originalTitle && game.originalTitle !== (game.title || game.name)" class="game-original-title">
          <strong>Título Original:</strong> {{ game.originalTitle }}
        </p>
        <p v-if="game.developer || developerText" class="game-developer">
          <strong>Desarrollador:</strong> {{ game.developer || developerText }}
        </p>
        <p v-if="game.publisher || publisherText" class="game-publisher">
          <strong>Distribuidor:</strong> {{ game.publisher || publisherText }}
        </p>
        <p v-if="game.releaseDate || game.released" class="game-release">
          <strong>Lanzamiento:</strong> {{ game.releaseDate || game.released }}
        </p>
        <p v-if="platformsText" class="game-platforms">
          <strong>Plataformas:</strong> {{ platformsText }}
        </p>
        <p v-if="genresText" class="game-genres">
          <strong>Géneros:</strong> {{ genresText }}
        </p>
        <p v-if="game.metacriticScore || game.metacritic" class="game-metacritic">
          <strong>Metacritic:</strong> 
          <span :class="getMetacriticClass(game.metacriticScore || game.metacritic)">
            {{ game.metacriticScore || game.metacritic }}
          </span>
        </p>
        <p class="game-id"><strong>RAWG ID:</strong> {{ game.id || game.rawgId || game.gameId }}</p>
        
        <!-- Rating Component -->
        <RatingComponent
          :rating="rating"
          :editable="false"
        />
        
        <!-- Status Selector Component -->
        <StatusSelector
          v-model="selectedUserStatuses"
          :allowed-statuses="allowedUserStatuses"
          :multiple="true"
          :readonly="!isNewGame"
          :label="isNewGame ? 'Añadir con estado' : 'Status'"
          :subtitle="isNewGame ? '' : '(solo lectura - usa el modal para editar)'"
        />
        
        <!-- Game-specific fields (read-only display) -->
        <div v-if="hoursPlayed || dateStarted || dateFinished || notes || ownershipFormatLabel" class="game-specific-fields readonly-fields">
          <p v-if="hoursPlayed" class="game-field"><strong>Horas Jugadas:</strong> {{ hoursPlayed }}</p>
          <p v-if="dateStarted" class="game-field"><strong>Fecha de Inicio:</strong> {{ dateStarted }}</p>
          <p v-if="dateFinished" class="game-field"><strong>Fecha de Finalización:</strong> {{ dateFinished }}</p>
          <p v-if="notes" class="game-field"><strong>Notas:</strong> {{ notes }}</p>
          <p v-if="ownershipFormatLabel" class="game-field"><strong>Formato:</strong> <span class="ownership-format-badge">{{ ownershipFormatLabel }}</span></p>
        </div>
        
        <!-- Direct action buttons -->
        <div class="game-actions">
          <!-- Save button for new games -->
          <button 
            v-if="isNewGame"
            @click="onSaveGame" 
            :class="['action-button', 'save-button', `save-button--${saveButtonState}`]"
            :disabled="!canSave"
            title="Guardar juego"
          >
            <i v-if="saveButtonState === 'idle'" class="fas fa-save"></i>
            <i v-else-if="saveButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="saveButtonState === 'error'" class="fas fa-times"></i>
            <span>Guardar</span>
          </button>
          
          <button 
            v-if="!isNewGame"
            @click="onEditGame"
            :class="['action-button', 'edit-button', `edit-button--${editButtonState}`]"
            :disabled="editButtonState !== 'idle'"
            title="Editar juego"
          >
            <i v-if="editButtonState === 'idle'" class="fas fa-pencil-alt"></i>
            <i v-else-if="editButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="editButtonState === 'error'" class="fas fa-times"></i>
            <span>Editar</span>
          </button>
          
          <button 
            v-if="!isNewGame && canDelete"
            @click="onDeleteGame"
            class="action-button delete-button"
            title="Eliminar juego"
          >
            <i class="fas fa-trash"></i>
            <span>Eliminar</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, defineExpose, ref, computed, watch } from 'vue';
import RatingComponent from '@/components/common/RatingComponent.vue';
import StatusSelector from '@/components/common/StatusSelector.vue';
import Logger from '@/utils/logger';

const props = defineProps({
  game: {
    type: Object,
    required: true,
    default: () => ({ 
      id: '', 
      title: '', 
      name: '',
      coverUrl: '', 
      rating: null,
      hoursPlayed: 0,
      notes: ''
    })
  },
  allowedUserStatuses: {
    type: Array,
    required: true
  },
  editable: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['delete-game', 'save-game', 'edit-item']);

// Estados seleccionados (editable)
const getInitialStatuses = () => {
  if (props.game.userStatuses && props.game.userStatuses.length > 0) {
    return [...props.game.userStatuses];
  } else {
    return props.allowedUserStatuses.includes('owned') ? ['owned'] : [];
  }
};

const selectedUserStatuses = ref(getInitialStatuses());
const rating = ref(props.game.user_rating || 0);
const hoursPlayed = ref(props.game.hoursPlayed || props.game.hours_played || 0);
const notes = ref(props.game.notes || '');
const dateStarted = ref(props.game.dateStarted || props.game.date_started || '');
const dateFinished = ref(props.game.dateFinished || props.game.date_finished || '');

// Estado de botones
const saveButtonState = ref('idle');
const editButtonState = ref('idle');

// Watchers para actualizar valores cuando cambien los props
watch(() => props.game.user_rating, (newVal) => {
  rating.value = newVal || 0;
}, { immediate: true });

watch(() => [props.game.dateStarted, props.game.date_started], ([camelCase, snakeCase]) => {
  dateStarted.value = camelCase || snakeCase || '';
}, { immediate: true, deep: true });

watch(() => [props.game.dateFinished, props.game.date_finished], ([camelCase, snakeCase]) => {
  dateFinished.value = camelCase || snakeCase || '';
}, { immediate: true, deep: true });

watch(() => [props.game.hoursPlayed, props.game.hours_played], ([camelCase, snakeCase]) => {
  hoursPlayed.value = camelCase || snakeCase || 0;
}, { immediate: true });

watch(() => [props.game.notes, props.game.personalNotes, props.game.personal_notes], ([notes1, notes2, notes3]) => {
  notes.value = notes1 || notes2 || notes3 || '';
}, { immediate: true });

const ownershipFormatLabel = ref(
  props.game.ownershipFormat?.label || props.game.ownership_format?.label || ''
)
watch(() => [props.game.ownershipFormat, props.game.ownership_format], ([fmt1, fmt2]) => {
  ownershipFormatLabel.value = fmt1?.label || fmt2?.label || ''
}, { immediate: true, deep: true })

watch(() => props.game.userStatuses, (newVal) => {
  if (newVal && newVal.length > 0) {
    selectedUserStatuses.value = [...newVal];
  }
}, { deep: true, immediate: true });


// Computed: el juego es nuevo si NO es editable
const isNewGame = computed(() => !props.editable);

// Computed properties
const canDelete = computed(() => {
  return props.editable;
});

const canSave = computed(() => {
  return true;
});

const platformsText = computed(() => {
  if (typeof props.game.platforms === 'string') {
    return props.game.platforms;
  }
  if (Array.isArray(props.game.platforms)) {
    return props.game.platforms
      .map(p => typeof p === 'string' ? p : p.platform?.name || p.name)
      .join(', ');
  }
  return '';
});

const genresText = computed(() => {
  if (typeof props.game.genres === 'string') {
    return props.game.genres;
  }
  if (Array.isArray(props.game.genres)) {
    return props.game.genres
      .map(g => typeof g === 'string' ? g : g.name)
      .join(', ');
  }
  return '';
});

const developerText = computed(() => {
  if (Array.isArray(props.game.developers)) {
    return props.game.developers
      .map(d => typeof d === 'string' ? d : d.name)
      .join(', ');
  }
  return '';
});

const publisherText = computed(() => {
  if (Array.isArray(props.game.publishers)) {
    return props.game.publishers
      .map(p => typeof p === 'string' ? p : p.name)
      .join(', ');
  }
  return '';
});

// Methods
const onDeleteGame = () => {
  Logger.debug('Deleting game:', props.game.id);
  emit('delete-game', { 
    gameId: props.game.id || props.game.rawgId || props.game.gameId, 
    itemType: 'game' 
  });
};

const onSaveGame = () => {
  Logger.debug('Saving game:', props.game.id);
  saveButtonState.value = 'idle';
  
  // Incluir campos adicionales en el objeto del juego
  const gameData = {
    ...props.game,
    hoursPlayed: hoursPlayed.value,
    notes: notes.value,
    dateStarted: dateStarted.value,
    dateFinished: dateFinished.value
  };
  
  emit('save-game', { 
    game: gameData, 
    statuses: selectedUserStatuses.value, 
    itemType: 'game' 
  });
};

const onEditGame = () => {
  const gameData = {
    ...props.game,
    user_rating: rating.value,
    hoursPlayed: hoursPlayed.value,
    notes: notes.value,
    dateStarted: dateStarted.value,
    dateFinished: dateFinished.value
  };
  
  emit('edit-item', gameData, 'game');
};

const getMetacriticClass = (score) => {
  if (score >= 75) return 'score-high';
  if (score >= 50) return 'score-medium';
  return 'score-low';
};

// Métodos públicos
const setSaveSuccess = () => {
  saveButtonState.value = 'success';
  setTimeout(() => {
    saveButtonState.value = 'idle';
  }, 2000);
};

const setSaveError = () => {
  saveButtonState.value = 'error';
  setTimeout(() => {
    saveButtonState.value = 'idle';
  }, 2000);
};

const setEditSuccess = () => {
  editButtonState.value = 'success';
  setTimeout(() => {
    editButtonState.value = 'idle';
  }, 2000);
};

const setEditError = () => {
  editButtonState.value = 'error';
  setTimeout(() => {
    editButtonState.value = 'idle';
  }, 2000);
};

// Exponer métodos
defineExpose({
  setSaveSuccess,
  setSaveError,
  setEditSuccess,
  setEditError
});
</script>

<style scoped>
.library-game-item-container {
  background: var(--background-secondary, #f8f9fa);
  border-radius: 8px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.game-details {
  display: flex;
  gap: 20px;
}

.cover-image-container {
  flex-shrink: 0;
  width: 200px;
}

.cover-image {
  width: 100%;
  height: auto;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.info-text {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.game-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary, #333);
  margin: 0 0 10px 0;
}

.game-details p {
  margin: 0;
  color: var(--text-secondary, #666);
  line-height: 1.6;
}

.game-details strong {
  color: var(--text-primary, #333);
  margin-right: 8px;
}

.score-high {
  background: #6c3;
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 700;
}

.score-medium {
  background: #fc3;
  color: #333;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 700;
}

.score-low {
  background: #f00;
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 700;
}

.game-specific-fields {
  display: flex;
  flex-direction: column;
  gap: 15px;
  padding: 15px;
  background: var(--background-primary, #fff);
  border-radius: 8px;
  border: 1px solid var(--border-color, #dee2e6);
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-weight: 600;
  color: var(--text-primary, #333);
  font-size: 0.9rem;
}

.form-control {
  padding: 8px 12px;
  border: 1px solid var(--border-color, #ced4da);
  border-radius: 4px;
  font-size: 0.95rem;
  transition: border-color 0.2s ease;
}

.form-control:focus {
  outline: none;
  border-color: var(--primary-color, #007bff);
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
}

.game-actions {
  display: flex;
  gap: 10px;
  margin-top: 15px;
}

.action-button {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.95rem;
}

.save-button {
  background: #28a745;
  color: white;
}

.save-button:hover:not(:disabled) {
  background: #218838;
}

.save-button--success {
  background: #28a745;
}

.save-button--error {
  background: #dc3545;
}

.edit-button {
  background: #007bff;
  color: white;
}

.edit-button:hover:not(:disabled) {
  background: #0056b3;
}

.edit-button--success {
  background: #28a745;
}

.edit-button--error {
  background: #dc3545;
}

.delete-button {
  background: #dc3545;
  color: white;
}

.delete-button:hover {
  background: #c82333;
}

.action-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (prefers-color-scheme: dark) {
  .library-game-item-container {
    background: var(--background-secondary, #1e1e1e);
  }
  
  .game-title,
  .game-details strong,
  .form-group label {
    color: var(--text-primary, #e0e0e0);
  }
  
  .game-details p {
    color: var(--text-secondary, #aaa);
  }
  
  .game-specific-fields {
    background: var(--background-primary, #2a2a2a);
    border-color: var(--border-color, #444);
  }
  
  .form-control {
    background: var(--background-primary, #333);
    color: var(--text-primary, #e0e0e0);
    border-color: var(--border-color, #555);
  }
}

@media (max-width: 768px) {
  .game-details {
    flex-direction: column;
  }
  
  .cover-image-container {
    width: 100%;
    max-width: 300px;
    margin: 0 auto;
  }
  
  .game-actions {
    flex-direction: column;
  }
  
  .action-button {
    width: 100%;
    justify-content: center;
  }
}
</style>
