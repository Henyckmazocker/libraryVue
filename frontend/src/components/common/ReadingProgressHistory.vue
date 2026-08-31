<template>
  <div class="reading-progress-history">
    <div class="history-header">
      <h3>
        <i class="fas fa-chart-line" />
        Historial de Progreso
      </h3>
      <p class="subtitle">
        Registro de tu avance en la lectura de este libro
      </p>
    </div>

    <!-- Estadísticas resumidas -->
    <div
      v-if="stats && progressHistory.length > 0"
      class="stats-summary"
    >
      <div class="stat-item">
        <span class="stat-number">{{ stats.totalSessions }}</span>
        <span class="stat-label">Sesiones</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">{{ stats.totalPagesRead }}</span>
        <span class="stat-label">Páginas Leídas</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">{{ stats.averagePagesPerSession }}</span>
        <span class="stat-label">Promedio/Sesión</span>
      </div>
      <div class="stat-item">
        <span class="stat-number">{{ stats.readingSpeed }}</span>
        <span class="stat-label">Páginas/Día</span>
      </div>
    </div>

    <!-- Estado de carga -->
    <div
      v-if="isLoading"
      class="loading-state"
    >
      <i class="fas fa-spinner fa-spin" />
      <span>Cargando historial...</span>
    </div>

    <!-- Error -->
    <div
      v-else-if="error"
      class="error-state"
    >
      <i class="fas fa-exclamation-triangle" />
      <span>{{ error }}</span>
    </div>

    <!-- Lista del historial -->
    <div
      v-else-if="progressHistory.length > 0"
      class="history-list"
    >
      <div 
        v-for="entry in progressHistory" 
        :key="entry.id"
        class="history-entry"
      >
        <div class="entry-icon">
          <i class="fas fa-book-open" />
        </div>
        <div class="entry-content">
          <div class="entry-main">
            <span class="pages-info">
              Páginas {{ entry.previous_page }} → {{ entry.current_page }}
            </span>
            <span class="pages-advanced">
              +{{ entry.pagesAdvanced }} páginas
            </span>
          </div>
          <div class="entry-meta">
            <span class="date">{{ entry.date }}</span>
            <span class="time">{{ entry.time }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Estado vacío -->
    <EmptyState
      v-else
      icon="fas fa-book"
      title="No hay historial de progreso aún"
      message="El historial se creará automáticamente cuando actualices tu progreso de lectura."
    />
  </div>
</template>

<script setup>
import { computed, onMounted, defineProps, defineExpose } from 'vue';
import EmptyState from '@/components/common/EmptyState.vue'
import { useReadingProgress } from '@/composables/useReadingProgress';
import Logger from '@/utils/logger';

const props = defineProps({
  isbn: {
    type: String,
    required: true
  },
  visible: {
    type: Boolean,
    default: true
  }
});

const { 
  isLoading, 
  error, 
  progressHistory, 
  getProgressHistory, 
  calculateStats 
} = useReadingProgress();

// Estadísticas computadas
const stats = computed(() => {
  return calculateStats(progressHistory.value);
});

// Cargar historial al montar el componente
onMounted(async () => {
  if (props.visible && props.isbn) {
    try {
      await getProgressHistory(props.isbn);
    } catch (err) {
      Logger.error('[ReadingProgressHistory] Error cargando historial:', err);
    }
  }
});

// Método público para refrescar
const refresh = async () => {
  if (props.isbn) {
    await getProgressHistory(props.isbn);
  }
};

// Exponer método para componente padre
defineExpose({
  refresh
});
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.reading-progress-history {
  background: var(--card-background, var(--color-background-mute));
  border-radius: radius(lg);
  padding: spacing(md);
  border: 1px solid var(--border-color, var(--color-border));
}

.history-header {
  margin-bottom: spacing(md);
  text-align: center;
}

.history-header h3 {
  color: var(--text-primary, var(--color-text));
  margin: 0 0 spacing(xs) 0;
  font-size: var(--font-size-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: spacing(sm);
}

.history-header h3 i {
  color: var(--color-success);
}

.subtitle {
  color: var(--text-secondary, var(--color-text-muted));
  margin: 0;
  font-size: var(--font-size-sm);
}

.stats-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: spacing(md);
  margin-bottom: spacing(lg);
  padding: spacing(md);
  background: var(--background-secondary, var(--color-background-mute));
  border-radius: radius(md);
}

.stat-item {
  text-align: center;
}

.stat-number {
  display: block;
  font-size: var(--font-size-xl);
  font-weight: bold;
  color: var(--color-success);
  margin-bottom: spacing(2xs);
}

.stat-label {
  display: block;
  font-size: var(--font-size-xs);
  color: var(--text-secondary, var(--color-text-muted));
}

.loading-state,
.error-state,
.loading-state i,
.error-state i,
.loading-state i {
  color: var(--color-success);
}

.error-state i {
  color: var(--color-error);
}
.history-list {
  max-height: 300px;
  overflow-y: auto;
}

.history-entry {
  display: flex;
  align-items: center;
  gap: spacing(md);
  padding: spacing(md);
  border-bottom: 1px solid var(--border-color, var(--color-border));
  transition: background-color 0.2s ease;
}

.history-entry:last-child {
  border-bottom: none;
}

.history-entry:hover {
  background: var(--background-hover, rgba(255, 255, 255, 0.05));
}

.entry-icon {
  width: 40px;
  height: 40px;
  background: var(--color-success);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: var(--font-size-md);
  flex-shrink: 0;
}

.entry-content {
  flex: 1;
  min-width: 0;
}

.entry-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: spacing(2xs);
  gap: spacing(sm);
}

.pages-info {
  color: var(--text-primary, var(--color-text));
  font-weight: 500;
}

.pages-advanced {
  color: var(--color-success);
  font-weight: bold;
  font-size: var(--font-size-sm);
}

.entry-meta {
  display: flex;
  gap: spacing(md);
  font-size: var(--font-size-xs);
  color: var(--text-secondary, var(--color-text-muted));
}

/* Scrollbar personalizado */
.history-list::-webkit-scrollbar {
  width: 6px;
}

.history-list::-webkit-scrollbar-track {
  background: var(--background-secondary, var(--color-background-mute));
  border-radius: radius(sm);
}

.history-list::-webkit-scrollbar-thumb {
  background: var(--border-color, var(--color-border));
  border-radius: radius(sm);
}

.history-list::-webkit-scrollbar-thumb:hover {
  background: var(--color-success);
}

/* Responsive */
@include responsive-below(sm) {
  .stats-summary {
    grid-template-columns: repeat(2, 1fr);
    gap: spacing(sm);
  }
  
  .entry-main {
    flex-direction: column;
    align-items: flex-start;
    gap: spacing(2xs);
  }
  
  .entry-meta {
    gap: spacing(sm);
  }
}
</style>