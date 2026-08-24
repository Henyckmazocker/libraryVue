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
          Sesión activa #{{ book.current_session_number || 1 }}
        </span>
      </div>
      <div class="session-details">
        <span
          v-if="book.session_started_at"
          class="session-date"
        >
          Iniciada: {{ formatDate(book.session_started_at) }}
        </span>
        <span
          v-if="book.current_page && book.pages"
          class="session-progress"
        >
          {{ progressPercentage }}% completado
        </span>
      </div>
    </div>

    <!-- Información para libros sin sesión -->
    <div
      v-else-if="book.total_sessions_completed > 0"
      class="completed-info"
    >
      <i class="fas fa-check-circle" />
      <span>Completado {{ book.total_sessions_completed }} vez{{ book.total_sessions_completed !== 1 ? 'es' : '' }}</span>
    </div>

    <!-- Enlace al historial de sesiones -->
    <button 
      v-if="book.total_sessions_completed > 0 || hasActiveSession"
      class="history-link"
      @click="showHistoryModal = true"
    >
      <i class="fas fa-history" />
      Ver historial de sesiones
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
  return date.toLocaleDateString('es-ES', { 
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
  gap: 12px;
  padding: 14px;
  background: linear-gradient(135deg, rgba(29, 78, 74, 0.3) 0%, rgba(29, 78, 74, 0.15) 100%);
  border-radius: 8px;
  border-left: 3px solid var(--color-primary);
  margin: 12px 0;
}

.active-session-badge {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.session-info {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-primary);
}

.session-info i {
  font-size: 18px;
  color: var(--color-primary);
}

.session-text {
  color: var(--color-text);
}

.session-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding-left: 28px;
  font-size: 12px;
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
  gap: 8px;
  font-size: 13px;
  color: var(--color-success);
  font-weight: 500;
}

.completed-info i {
  font-size: 16px;
}

.history-link {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 8px 12px;
  background: transparent;
  border: 1px solid var(--color-border);
  border-radius: 6px;
  color: var(--color-text-secondary);
  font-size: 12px;
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
  font-size: 13px;
}

/* Responsive */
@include responsive-below(md) {
  .reading-status-widget {
    padding: 12px;
  }

  .session-info {
    font-size: 13px;
  }

  .session-details {
    font-size: 11px;
  }
}
</style>
