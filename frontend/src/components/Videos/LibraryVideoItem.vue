<template>
  <div class="library-video-item-container">
    <div class="video-details">
      <div class="cover-image-container" v-if="video.cover_url || video.coverUrl">
        <img :src="video.cover_url || video.coverUrl" alt="Video Thumbnail" class="cover-image" />
      </div>
      <div class="info-text">
        <h3 class="video-title">{{ video.title }}</h3>

        <p v-if="video.channel_name || video.channelName" class="video-channel">
          <strong>Canal:</strong> {{ video.channel_name || video.channelName }}
        </p>
        <p v-if="video.duration" class="video-duration">
          <strong>Duración:</strong> {{ video.duration }}
        </p>
        <p v-if="video.published_at || video.publishedAt" class="video-published">
          <strong>Publicado:</strong> {{ video.published_at || video.publishedAt }}
        </p>
        <p v-if="video.youtube_id || video.youtubeId" class="video-id">
          <strong>YouTube ID:</strong> {{ video.youtube_id || video.youtubeId }}
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
          :readonly="!isNewVideo"
          :label="isNewVideo ? 'Añadir con estado' : 'Estado'"
          :subtitle="isNewVideo ? '' : '(solo lectura - usa el modal para editar)'"
        />

        <!-- Video-specific fields (read-only display) -->
        <div v-if="personalNotes || watchCount" class="video-specific-fields readonly-fields">
          <p v-if="watchCount" class="video-field"><strong>Veces visto:</strong> {{ watchCount }}</p>
          <p v-if="personalNotes" class="video-field"><strong>Notas:</strong> {{ personalNotes }}</p>
        </div>

        <!-- Action buttons -->
        <div class="video-actions">
          <!-- Save button for new videos -->
          <button
            v-if="isNewVideo"
            @click="onSaveVideo"
            :class="['action-button', 'save-button', `save-button--${saveButtonState}`]"
            :disabled="!canSave"
            title="Guardar vídeo"
          >
            <i v-if="saveButtonState === 'idle'" class="fas fa-save"></i>
            <i v-else-if="saveButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="saveButtonState === 'error'" class="fas fa-times"></i>
            <span>Guardar</span>
          </button>

          <button
            v-if="!isNewVideo"
            @click="onEditVideo"
            :class="['action-button', 'edit-button', `edit-button--${editButtonState}`]"
            :disabled="editButtonState !== 'idle'"
            title="Editar vídeo"
          >
            <i v-if="editButtonState === 'idle'" class="fas fa-pencil-alt"></i>
            <i v-else-if="editButtonState === 'success'" class="fas fa-check"></i>
            <i v-else-if="editButtonState === 'error'" class="fas fa-times"></i>
            <span>Editar</span>
          </button>

          <button
            v-if="!isNewVideo && canDelete"
            @click="onDeleteVideo"
            class="action-button delete-button"
            title="Eliminar vídeo"
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
  video: {
    type: Object,
    required: true,
    default: () => ({
      youtube_id: '',
      title: '',
      cover_url: '',
      user_rating: null,
      personalNotes: ''
    })
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  },
  isNewVideo: {
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
  if (props.video?.userStatuses && props.video.userStatuses.length > 0) {
    return [...props.video.userStatuses];
  }
  return [];
};
const rating = ref(props.video?.user_rating ?? null);
const selectedUserStatuses = ref(getInitialStatuses());
const watchCount = ref(props.video?.watchCount ?? props.video?.watch_count ?? null);
const personalNotes = ref(props.video?.personalNotes ?? props.video?.personal_notes ?? '');

const saveButtonState = ref('idle');
const editButtonState = ref('idle');

// Sync with prop changes
watch(() => props.video, (newVideo) => {
  if (newVideo) {
    rating.value = newVideo.user_rating ?? null;
    selectedUserStatuses.value = Array.isArray(newVideo.userStatuses) && newVideo.userStatuses.length > 0
      ? [...newVideo.userStatuses]
      : [];
    watchCount.value = newVideo.watchCount ?? newVideo.watch_count ?? null;
    personalNotes.value = newVideo.personalNotes ?? newVideo.personal_notes ?? '';
  }
}, { immediate: true });

const allowedUserStatuses = computed(() => props.allowedStatuses || []);

const canSave = computed(() => saveButtonState.value === 'idle');

// Methods
async function onSaveVideo() {
  saveButtonState.value = 'saving';
  try {
    emit('save', {
      ...props.video,
      userStatuses: [...selectedUserStatuses.value],
      user_rating: rating.value
    });
    saveButtonState.value = 'success';
    setTimeout(() => { saveButtonState.value = 'idle'; }, 1500);
  } catch (err) {
    Logger.error('[LibraryVideoItem] Error saving video:', err);
    saveButtonState.value = 'error';
    setTimeout(() => { saveButtonState.value = 'idle'; }, 2000);
  }
}

async function onEditVideo() {
  editButtonState.value = 'loading';
  try {
    emit('edit', props.video);
    editButtonState.value = 'idle';
  } catch (err) {
    Logger.error('[LibraryVideoItem] Error editing video:', err);
    editButtonState.value = 'error';
    setTimeout(() => { editButtonState.value = 'idle'; }, 2000);
  }
}

function onDeleteVideo() {
  const youtubeId = props.video?.youtube_id || props.video?.youtubeId || props.video?.id;
  emit('delete', youtubeId);
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/library-item' as *;

// Cover 16:9 (miniatura de vídeo de YouTube)
.library-video-item-container {
  @include library-item('video', '16/9', 120px, 'video');
}
</style>
