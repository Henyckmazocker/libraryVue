<template>
  <div class="library-album-item-container">
    <div class="album-details">
      <div class="cover-image-container" v-if="album.cover_url || album.coverUrl">
        <img :src="album.cover_url || album.coverUrl" alt="Album Cover" class="cover-image" />
      </div>
      <div class="info-text">
        <h3 class="album-title">{{ album.title || album.name }}</h3>

        <p v-if="artistName" class="album-artist">
          <strong>Artista:</strong> {{ artistName }}
        </p>
        <p v-if="album.release_date || album.releaseDate" class="album-release">
          <strong>Lanzamiento:</strong> {{ album.release_date || album.releaseDate }}
        </p>
        <p v-if="genresText" class="album-genres">
          <strong>Géneros:</strong> {{ genresText }}
        </p>
        <p v-if="album.label" class="album-label">
          <strong>Sello:</strong> {{ album.label }}
        </p>
        <p v-if="album.total_tracks || album.totalTracks" class="album-tracks">
          <strong>Pistas:</strong> {{ album.total_tracks || album.totalTracks }}
        </p>
        <p v-if="formattedDuration" class="album-duration">
          <strong>Duración:</strong> {{ formattedDuration }}
        </p>
        <p v-if="album.spotify_id" class="album-id">
          <strong>Spotify ID:</strong> {{ album.spotify_id }}
        </p>

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
          :readonly="!isNewAlbum"
          :label="isNewAlbum ? 'Añadir con estado' : 'Estado'"
          :subtitle="isNewAlbum ? '' : '(solo lectura - usa el modal para editar)'"
        />

        <!-- Album-specific fields (read-only display) -->
        <div v-if="favoriteTrack || dateStarted || dateFinished || personalNotes || ownershipFormatLabel" class="album-specific-fields readonly-fields">
          <p v-if="favoriteTrack" class="album-field"><strong>Canción favorita:</strong> {{ favoriteTrack }}</p>
          <p v-if="dateStarted" class="album-field"><strong>Primera escucha:</strong> {{ dateStarted }}</p>
          <p v-if="dateFinished" class="album-field"><strong>Última escucha:</strong> {{ dateFinished }}</p>
          <p v-if="personalNotes" class="album-field"><strong>Notas:</strong> {{ personalNotes }}</p>
          <p v-if="ownershipFormatLabel" class="album-field"><strong>Formato:</strong> <span class="ownership-format-badge">{{ ownershipFormatLabel }}</span></p>
        </div>

        <!-- Action buttons -->
        <div class="album-actions">
          <!-- Save button for new albums -->
          <button
            v-if="isNewAlbum"
            @click="onSaveAlbum"
            :class="['action-button', 'save-button', `save-button--${saveButtonState}`]"
            :disabled="!canSave"
            title="Guardar álbum"
          >
            <i v-if="saveButtonState === 'idle'" class="fas fa-save"></i>
            <i v-else-if="saveButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="saveButtonState === 'error'" class="fas fa-times"></i>
            <span>Guardar</span>
          </button>

          <button
            v-if="!isNewAlbum"
            @click="onEditAlbum"
            :class="['action-button', 'edit-button', `edit-button--${editButtonState}`]"
            :disabled="editButtonState !== 'idle'"
            title="Editar álbum"
          >
            <i v-if="editButtonState === 'idle'" class="fas fa-pencil-alt"></i>
            <i v-else-if="editButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="editButtonState === 'error'" class="fas fa-times"></i>
            <span>Editar</span>
          </button>

          <button
            v-if="!isNewAlbum && canDelete"
            @click="onDeleteAlbum"
            class="action-button delete-button"
            title="Eliminar álbum"
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
import { ref, computed, watch } from 'vue';
import RatingComponent from '@/components/common/RatingComponent.vue';
import StatusSelector from '@/components/common/StatusSelector.vue';
import Logger from '@/utils/logger';

const props = defineProps({
  album: {
    type: Object,
    required: true,
    default: () => ({
      id: '',
      title: '',
      name: '',
      cover_url: '',
      user_rating: null,
      listenCount: 0,
      personalNotes: ''
    })
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  },
  isNewAlbum: {
    type: Boolean,
    default: false
  },
  canDelete: {
    type: Boolean,
    default: true
  }
});

const emit = defineEmits(['save', 'edit', 'delete', 'update:rating', 'update:statuses']);

// Local reactive state
const getInitialStatuses = () => {
  if (props.album?.userStatuses && props.album.userStatuses.length > 0) {
    return [...props.album.userStatuses];
  } else {
    return props.allowedStatuses.includes('owned') ? ['owned'] : [];
  }
};
const rating = ref(props.album?.user_rating ?? null);
const selectedUserStatuses = ref(getInitialStatuses());
const listenCount = ref(props.album?.listenCount ?? props.album?.listen_count ?? null);
const favoriteTrack = ref(props.album?.favoriteTrack ?? props.album?.favorite_track ?? '');
const dateStarted = ref(props.album?.dateStarted ?? props.album?.date_started ?? '');
const dateFinished = ref(props.album?.dateFinished ?? props.album?.date_finished ?? '');
const personalNotes = ref(props.album?.personalNotes ?? props.album?.personal_notes ?? '');
const ownershipFormatLabel = ref(props.album?.ownershipFormat?.label ?? props.album?.ownership_format?.label ?? '');

const saveButtonState = ref('idle');
const editButtonState = ref('idle');

// Sync with prop changes
watch(() => props.album, (newAlbum) => {
  if (newAlbum) {
    rating.value = newAlbum.user_rating ?? null;
    selectedUserStatuses.value = Array.isArray(newAlbum.userStatuses) && newAlbum.userStatuses.length > 0
      ? [...newAlbum.userStatuses]
      : (props.allowedStatuses.includes('owned') ? ['owned'] : []);
    listenCount.value = newAlbum.listenCount ?? newAlbum.listen_count ?? null;
    favoriteTrack.value = newAlbum.favoriteTrack ?? newAlbum.favorite_track ?? '';
    dateStarted.value = newAlbum.dateStarted ?? newAlbum.date_started ?? '';
    dateFinished.value = newAlbum.dateFinished ?? newAlbum.date_finished ?? '';
    personalNotes.value = newAlbum.personalNotes ?? newAlbum.personal_notes ?? '';
    ownershipFormatLabel.value = newAlbum.ownershipFormat?.label ?? newAlbum.ownership_format?.label ?? '';
  }
}, { immediate: true });

// Computed
const artistName = computed(() => {
  return props.album?.artist || props.album?.artists?.[0]?.name || '';
});

const genresText = computed(() => {
  const genres = props.album?.genres;
  if (!genres) return '';
  if (Array.isArray(genres)) return genres.join(', ');
  return genres;
});

const formattedDuration = computed(() => {
  const ms = props.album?.duration_ms || props.album?.durationMs;
  if (!ms) return '';
  const totalSec = Math.floor(ms / 1000);
  const hours = Math.floor(totalSec / 3600);
  const minutes = Math.floor((totalSec % 3600) / 60);
  const seconds = totalSec % 60;
  if (hours > 0) {
    return `${hours}h ${minutes}m`;
  }
  return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

const allowedUserStatuses = computed(() => {
  return props.allowedStatuses || [];
});

const canSave = computed(() => saveButtonState.value === 'idle');

// Methods
async function onSaveAlbum() {
  saveButtonState.value = 'saving';
  try {
    // Incluir estados y rating seleccionados localmente: props.album no los contiene
    // hasta que se guarde por primera vez
    emit('save', {
      ...props.album,
      userStatuses: [...selectedUserStatuses.value],
      user_rating: rating.value
    });
    saveButtonState.value = 'success';
    setTimeout(() => { saveButtonState.value = 'idle'; }, 1500);
  } catch (err) {
    Logger.error('[LibraryAlbumItem] Error saving album:', err);
    saveButtonState.value = 'error';
    setTimeout(() => { saveButtonState.value = 'idle'; }, 2000);
  }
}

async function onEditAlbum() {
  editButtonState.value = 'loading';
  try {
    emit('edit', props.album);
    editButtonState.value = 'idle';
  } catch (err) {
    Logger.error('[LibraryAlbumItem] Error editing album:', err);
    editButtonState.value = 'error';
    setTimeout(() => { editButtonState.value = 'idle'; }, 2000);
  }
}

function onDeleteAlbum() {
  emit('delete', props.album?.id || props.album?.spotify_id);
}
</script>

<style scoped>
.library-album-item-container {
  padding: 10px 0;
}

.album-details {
  display: flex;
  gap: 20px;
  align-items: flex-start;
}

.cover-image-container {
  flex-shrink: 0;
  width: 120px;
  height: 120px;
  border-radius: 8px;
  overflow: hidden;
}

.cover-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.info-text {
  flex: 1;
  min-width: 0;
}

.album-title {
  font-size: 1.1rem;
  font-weight: 700;
  margin: 0 0 8px;
  color: var(--text-color, #e0e0e0);
}

.album-artist,
.album-release,
.album-genres,
.album-label,
.album-tracks,
.album-duration,
.album-id {
  font-size: 0.85rem;
  margin: 3px 0;
  color: var(--text-color-secondary, #9ca3af);
}

.album-specific-fields {
  margin-top: 10px;
  padding: 8px 12px;
  background: var(--surface-card, #2a2d36);
  border-radius: 6px;
}

.album-field {
  font-size: 0.82rem;
  margin: 3px 0;
  color: var(--text-color-secondary, #9ca3af);
}

.album-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  flex-wrap: wrap;
}

.action-button {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.82rem;
  font-weight: 500;
  transition: opacity 0.2s, background 0.2s;
}

.action-button:disabled { opacity: 0.5; cursor: not-allowed; }

.save-button { background: var(--primary-color, #1D4E4A); color: white; }
.save-button--success { background: #16a34a; }
.save-button--error { background: #dc2626; }

.edit-button { background: var(--surface-card, #2a2d36); color: var(--text-color, #e0e0e0); border: 1px solid var(--surface-border, #3f4451); }
.edit-button--success { border-color: #16a34a; color: #16a34a; }
.edit-button--error { border-color: #dc2626; color: #dc2626; }

.delete-button { background: transparent; color: #f87171; border: 1px solid #f87171; }
.delete-button:hover { background: #f87171; color: white; }
</style>
