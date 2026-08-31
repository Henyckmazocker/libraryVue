<template>
  <div class="reading-status-widget">
    <!-- Badge de sesión activa -->
    <div
      v-if="hasActiveSession"
      class="active-session-badge"
    >
      <div class="session-info">
        <i class="fas fa-book-reader" />
        <span class="session-text">
          {{ t('reading.activeSession', { n: book.current_session_number || 1 }) }}
        </span>
      </div>
      <div class="session-details">
        <span
          v-if="book.session_started_at"
          class="session-date"
        >
          {{ t('reading.startedAt', { date: formatDate(book.session_started_at) }) }}
        </span>
        <span
          v-if="book.current_page && book.pages"
          class="session-progress"
        >
          {{ t('reading.percentComplete', { n: progressPercentage }) }}
        </span>
      </div>
    </div>

    <!-- Información para libros sin sesión -->
    <div
      v-else-if="book.total_sessions_completed > 0"
      class="completed-info"
    >
      <i class="fas fa-check-circle" />
      <span>{{ t('reading.completedTimes', { n: book.total_sessions_completed }) }}</span>
    </div>

    <!-- Enlace al historial de sesiones -->
    <button 
      v-if="book.total_sessions_completed > 0 || hasActiveSession"
      class="btn btn--ghost btn--sm history-link"
      @click="showHistoryModal = true"
    >
      <i class="fas fa-history" />
      {{ t('reading.viewHistory') }}
    </button>

    <!-- Modal de historial de sesiones -->
    <SessionHistoryModal
      v-if="showHistoryModal"
      :book="book"
      :visible="showHistoryModal"
      @close="showHistoryModal = false"
    />
  </div>
</template>

<script setup>
import { ref, computed, defineProps, onMounted, watch } from 'vue';
import SessionHistoryModal from './SessionHistoryModal.vue';
import Logger from '@/utils/logger';
import { useI18n } from '@/composables/useI18n';
import { intlLocale } from '@/config/i18n';

const { t } = useI18n();

const props = defineProps({
  book: {
    type: Object,
    required: true
  }
});

// Estado local
const showHistoryModal = ref(false);

// Debug logging
onMounted(() => {
  Logger.debug('[ReadingStatusWidget] Component mounted with book:', {
    isbn: props.book.isbn,
    title: props.book.title,
    active_reading_session_id: props.book.active_reading_session_id,
    total_sessions_completed: props.book.total_sessions_completed,
    current_session_number: props.book.current_session_number,
    session_started_at: props.book.session_started_at,
    current_page: props.book.current_page,
    pages: props.book.pages
  });
});

watch(() => props.book, (newBook) => {
  Logger.debug('[ReadingStatusWidget] Book data changed:', {
    isbn: newBook.isbn,
    active_reading_session_id: newBook.active_reading_session_id,
    total_sessions_completed: newBook.total_sessions_completed
  });
}, { deep: true });

// Computed properties
const hasActiveSession = computed(() => {
  const hasSession = !!props.book.active_reading_session_id;
  Logger.debug('[ReadingStatusWidget] hasActiveSession:', hasSession, 'session_id:', props.book.active_reading_session_id);
  return hasSession;
});

const progressPercentage = computed(() => {
  if (!props.book.pages || props.book.pages === 0) return 0;
  return Math.round((props.book.current_page / props.book.pages) * 100);
});

// Métodos
const formatDate = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleDateString(intlLocale(), { 
    day: 'numeric', 
    month: 'short', 
    year: 'numeric' 
  });
};

</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.reading-status-widget {
  display: flex;
  flex-direction: column;
  gap: spacing(sm);
  padding: spacing(sm);
  background: linear-gradient(135deg, rgba(29, 78, 74, 0.3) 0%, rgba(29, 78, 74, 0.15) 100%);
  border-radius: radius(md);
  border-left: 3px solid var(--color-primary);
  margin: spacing(sm) 0;
}

.active-session-badge {
  display: flex;
  flex-direction: column;
  gap: spacing(xs);
}

.session-info {
  display: flex;
  align-items: center;
  gap: spacing(sm);
  font-size: var(--font-size-sm);
  font-weight: 600;
  color: var(--color-primary);
}

.session-info i {
  font-size: var(--font-size-md);
  color: var(--color-primary);
}

.session-text {
  color: var(--color-text);
}

.session-details {
  display: flex;
  flex-direction: column;
  gap: spacing(2xs);
  padding-left: spacing(lg);
  font-size: var(--font-size-xs);
  color: var(--color-text-muted);
}

.session-date,
.session-progress {
  display: flex;
  align-items: center;
}

.completed-info {
  display: flex;
  align-items: center;
  gap: spacing(xs);
  font-size: var(--font-size-xs);
  color: var(--color-success);
  font-weight: 500;
}

.completed-info i {
  font-size: var(--font-size-base);
}

.history-link {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: spacing(xs);
  padding: spacing(xs) spacing(sm);
  background: transparent;
  border: 1px solid var(--color-border);
  border-radius: radius(sm);
  color: var(--color-text-secondary);
  font-size: var(--font-size-xs);
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.history-link:hover {
  background: rgba(29, 78, 74, 0.1);
  border-color: var(--color-primary);
  color: var(--color-primary);
}

.history-link i {
  font-size: var(--font-size-xs);
}

/* Responsive */
@include responsive-below(md) {
  .reading-status-widget {
    padding: spacing(sm);
  }

  .session-info {
    font-size: var(--font-size-xs);
  }

  .session-details {
    font-size: var(--font-size-xs);
  }
}
</style>
