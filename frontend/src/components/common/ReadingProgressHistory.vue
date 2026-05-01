<template>
  <div class="reading-progress-history">
    <div class="history-header">
      <h3>
        <i class="fas fa-chart-line"></i>
        Historial de Progreso
      </h3>
      <p class="subtitle">
        Registro de tu avance en la lectura de este libro
      </p>
    </div>

    <!-- Estadísticas resumidas -->
    <div v-if="stats && progressHistory.length > 0" class="stats-summary">
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
    <div v-if="isLoading" class="loading-state">
      <i class="fas fa-spinner fa-spin"></i>
      <span>Cargando historial...</span>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-state">
      <i class="fas fa-exclamation-triangle"></i>
      <span>{{ error }}</span>
    </div>

    <!-- Lista del historial -->
    <div v-else-if="progressHistory.length > 0" class="history-list">
      <div 
        v-for="entry in progressHistory" 
        :key="entry.id"
        class="history-entry"
      >
        <div class="entry-icon">
          <i class="fas fa-book-open"></i>
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
    <div v-else class="empty-state">
      <i class="fas fa-book"></i>
      <p>No hay historial de progreso aún</p>
      <p class="empty-subtitle">
        El historial se creará automáticamente cuando actualices tu progreso de lectura
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, defineProps, defineExpose } from 'vue';
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
.reading-progress-history {
  background: var(--card-background, #272729);
  border-radius: 12px;
  padding: 20px;
  border: 1px solid var(--border-color, #343536);
}

.history-header {
  margin-bottom: 20px;
  text-align: center;
}

.history-header h3 {
  color: var(--text-primary, #d7dadc);
  margin: 0 0 8px 0;
  font-size: 1.2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.history-header h3 i {
  color: #4CAF50;
}

.subtitle {
  color: var(--text-secondary, #b3b3b3);
  margin: 0;
  font-size: 0.9rem;
}

.stats-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: 15px;
  margin-bottom: 25px;
  padding: 15px;
  background: var(--background-secondary, #23252a);
  border-radius: 8px;
}

.stat-item {
  text-align: center;
}

.stat-number {
  display: block;
  font-size: 1.5rem;
  font-weight: bold;
  color: #4CAF50;
  margin-bottom: 4px;
}

.stat-label {
  display: block;
  font-size: 0.8rem;
  color: var(--text-secondary, #b3b3b3);
}

.loading-state,
.error-state,
.empty-state {
  text-align: center;
  padding: 30px 20px;
  color: var(--text-secondary, #b3b3b3);
}

.loading-state i,
.error-state i,
.empty-state i {
  font-size: 2rem;
  margin-bottom: 10px;
  display: block;
}

.loading-state i {
  color: #4CAF50;
}

.error-state i {
  color: #f44336;
}

.empty-state i {
  color: var(--text-secondary, #666);
}

.empty-state p {
  margin: 8px 0;
}

.empty-subtitle {
  font-size: 0.9rem;
  opacity: 0.8;
}

.history-list {
  max-height: 300px;
  overflow-y: auto;
}

.history-entry {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  border-bottom: 1px solid var(--border-color, #343536);
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
  background: #4CAF50;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.1rem;
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
  margin-bottom: 4px;
  gap: 10px;
}

.pages-info {
  color: var(--text-primary, #d7dadc);
  font-weight: 500;
}

.pages-advanced {
  color: #4CAF50;
  font-weight: bold;
  font-size: 0.9rem;
}

.entry-meta {
  display: flex;
  gap: 15px;
  font-size: 0.8rem;
  color: var(--text-secondary, #b3b3b3);
}

/* Scrollbar personalizado */
.history-list::-webkit-scrollbar {
  width: 6px;
}

.history-list::-webkit-scrollbar-track {
  background: var(--background-secondary, #23252a);
  border-radius: 3px;
}

.history-list::-webkit-scrollbar-thumb {
  background: var(--border-color, #343536);
  border-radius: 3px;
}

.history-list::-webkit-scrollbar-thumb:hover {
  background: #4CAF50;
}

/* Responsive */
@media (max-width: 480px) {
  .stats-summary {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }
  
  .entry-main {
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
  
  .entry-meta {
    gap: 10px;
  }
}
</style>