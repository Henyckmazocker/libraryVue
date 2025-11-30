<template>
  <Dialog
    v-model:visible="dialogVisible"
    :header="`Historial de lectura - ${book.title}`"
    :modal="true"
    :closable="true"
    :draggable="false"
    class="session-history-modal"
    :style="{ width: '90vw', maxWidth: '800px' }"
    @hide="handleClose"
  >
    <!-- Estadísticas generales -->
    <div v-if="statistics" class="statistics-section">
      <div class="stat-card">
        <i class="pi pi-check-circle stat-icon"></i>
        <div class="stat-content">
          <span class="stat-value">{{ statistics.totalCompleted }}</span>
          <span class="stat-label">Completadas</span>
        </div>
      </div>
      
      <div class="stat-card">
        <i class="pi pi-clock stat-icon"></i>
        <div class="stat-content">
          <span class="stat-value">{{ statistics.averageDuration }}</span>
          <span class="stat-label">Promedio</span>
        </div>
      </div>
      
      <div class="stat-card">
        <i class="pi pi-book stat-icon"></i>
        <div class="stat-content">
          <span class="stat-value">{{ statistics.totalPagesRead }}</span>
          <span class="stat-label">Páginas leídas</span>
        </div>
      </div>
    </div>

    <!-- Timeline de sesiones -->
    <div v-if="sessions && sessions.length > 0" class="timeline-container">
      <Timeline :value="timelineEvents" align="alternate" class="sessions-timeline">
        <template #marker="{ item }">
          <div class="timeline-marker" :class="getMarkerClass(item.status)">
            <i :class="getMarkerIcon(item.status)"></i>
          </div>
        </template>

        <template #content="{ item }">
          <Card class="session-card" :class="getCardClass(item.status)">
            <!-- Encabezado de la sesión -->
            <template #title>
              <div class="session-header">
                <span class="session-number">Sesión #{{ item.sessionNumber }}</span>
                <Tag :value="getStatusLabel(item.status)" :severity="getStatusSeverity(item.status)" />
              </div>
            </template>

            <!-- Contenido de la sesión -->
            <template #content>
              <div class="session-details">
                <!-- Fechas -->
                <div class="detail-row">
                  <i class="pi pi-calendar detail-icon"></i>
                  <div class="detail-content">
                    <span class="detail-label">Inicio:</span>
                    <span class="detail-value">{{ formatDate(item.startedAt) }}</span>
                  </div>
                </div>

                <div v-if="item.completedAt" class="detail-row">
                  <i class="pi pi-calendar-times detail-icon"></i>
                  <div class="detail-content">
                    <span class="detail-label">Fin:</span>
                    <span class="detail-value">{{ formatDate(item.completedAt) }}</span>
                  </div>
                </div>

                <!-- Duración -->
                <div v-if="item.duration" class="detail-row">
                  <i class="pi pi-clock detail-icon"></i>
                  <div class="detail-content">
                    <span class="detail-label">Duración:</span>
                    <span class="detail-value">{{ item.duration }}</span>
                  </div>
                </div>

                <!-- Progreso -->
                <div v-if="item.finalPage" class="detail-row">
                  <i class="pi pi-bookmark detail-icon"></i>
                  <div class="detail-content">
                    <span class="detail-label">Progreso:</span>
                    <span class="detail-value">
                      {{ item.finalPage }} / {{ book.total_pages }} páginas ({{ item.progressPercentage }}%)
                    </span>
                  </div>
                </div>

                <!-- Barra de progreso visual -->
                <div v-if="item.progressPercentage" class="progress-bar-container">
                  <div 
                    class="progress-bar" 
                    :class="getProgressBarClass(item.status)"
                    :style="{ width: item.progressPercentage + '%' }"
                  ></div>
                </div>

                <!-- Notas de sesión -->
                <div v-if="item.sessionNotes" class="detail-row notes-row">
                  <i class="pi pi-comment detail-icon"></i>
                  <div class="detail-content">
                    <span class="detail-label">Notas:</span>
                    <p class="session-notes">{{ item.sessionNotes }}</p>
                  </div>
                </div>
              </div>
            </template>
          </Card>
        </template>
      </Timeline>
    </div>

    <!-- Estado vacío -->
    <div v-else class="empty-state">
      <i class="pi pi-book empty-icon"></i>
      <p class="empty-message">No hay sesiones de lectura registradas para este libro</p>
    </div>

    <!-- Footer del diálogo -->
    <template #footer>
      <Button 
        label="Cerrar" 
        icon="pi pi-times" 
        @click="handleClose" 
        class="p-button-text"
      />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch, defineProps, defineEmits, onMounted } from 'vue'
import { useReadingSessions } from '@/composables/useReadingSessions'
import Dialog from 'primevue/dialog'
import Timeline from 'primevue/timeline'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import Button from 'primevue/button'

const props = defineProps({
  book: {
    type: Object,
    required: true
  },
  visible: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['close'])

// Estado local
const dialogVisible = ref(props.visible)
const sessions = ref([])
const statistics = ref(null)
const isLoading = ref(false)

// Composable
const { loadHistory } = useReadingSessions(props.book.isbn)

// Watchers
watch(() => props.visible, (newValue) => {
  dialogVisible.value = newValue
  if (newValue) {
    loadSessionHistory()
  }
})

// Computed properties
const timelineEvents = computed(() => {
  return sessions.value.map(session => ({
    sessionNumber: session.session_number,
    status: session.status,
    startedAt: session.started_at,
    completedAt: session.completed_at,
    finalPage: session.final_page,
    sessionNotes: session.session_notes,
    duration: calculateDuration(session.started_at, session.completed_at),
    progressPercentage: calculateProgressPercentage(session.final_page)
  }))
})

// Métodos
const loadSessionHistory = async () => {
  try {
    isLoading.value = true
    const history = await loadHistory()
    
    if (history && Array.isArray(history)) {
      sessions.value = history.sort((a, b) => b.session_number - a.session_number)
      calculateStatistics()
    }
  } catch (error) {
    console.error('Error al cargar historial de sesiones:', error)
  } finally {
    isLoading.value = false
  }
}

const calculateStatistics = () => {
  if (!sessions.value || sessions.value.length === 0) {
    statistics.value = null
    return
  }

  const completedSessions = sessions.value.filter(s => s.status === 'completed')
  const totalCompleted = completedSessions.length

  let averageDuration = 'N/A'
  if (completedSessions.length > 0) {
    const totalDays = completedSessions.reduce((sum, session) => {
      const days = calculateDurationInDays(session.started_at, session.completed_at)
      return sum + days
    }, 0)
    
    const avgDays = Math.round(totalDays / completedSessions.length)
    averageDuration = `${avgDays} día${avgDays !== 1 ? 's' : ''}`
  }

  const totalPagesRead = sessions.value.reduce((sum, session) => {
    return sum + (session.final_page || 0)
  }, 0)

  statistics.value = {
    totalCompleted,
    averageDuration,
    totalPagesRead
  }
}

const calculateDurationInDays = (startDate, endDate) => {
  if (!startDate || !endDate) return 0
  const start = new Date(startDate)
  const end = new Date(endDate)
  return Math.ceil((end - start) / (1000 * 60 * 60 * 24))
}

const calculateDuration = (startDate, endDate) => {
  if (!endDate) {
    const days = calculateDurationInDays(startDate, new Date())
    return `${days} día${days !== 1 ? 's' : ''} (en curso)`
  }
  
  const days = calculateDurationInDays(startDate, endDate)
  return `${days} día${days !== 1 ? 's' : ''}`
}

const calculateProgressPercentage = (finalPage) => {
  if (!finalPage || !props.book.total_pages || props.book.total_pages === 0) return 0
  return Math.round((finalPage / props.book.total_pages) * 100)
}

const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('es-ES', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getMarkerClass = (status) => {
  const classes = {
    'completed': 'marker-completed',
    'active': 'marker-active',
    'paused': 'marker-paused',
    'abandoned': 'marker-abandoned'
  }
  return classes[status] || 'marker-default'
}

const getMarkerIcon = (status) => {
  const icons = {
    'completed': 'pi pi-check',
    'active': 'pi pi-play',
    'paused': 'pi pi-pause',
    'abandoned': 'pi pi-times'
  }
  return icons[status] || 'pi pi-circle'
}

const getCardClass = (status) => {
  const classes = {
    'completed': 'card-completed',
    'active': 'card-active',
    'paused': 'card-paused',
    'abandoned': 'card-abandoned'
  }
  return classes[status] || ''
}

const getProgressBarClass = (status) => {
  const classes = {
    'completed': 'progress-completed',
    'active': 'progress-active',
    'paused': 'progress-paused',
    'abandoned': 'progress-abandoned'
  }
  return classes[status] || ''
}

const getStatusLabel = (status) => {
  const labels = {
    'completed': 'Completada',
    'active': 'En curso',
    'paused': 'Pausada',
    'abandoned': 'Abandonada'
  }
  return labels[status] || status
}

const getStatusSeverity = (status) => {
  const severities = {
    'completed': 'success',
    'active': 'info',
    'paused': 'warning',
    'abandoned': 'danger'
  }
  return severities[status] || null
}

const handleClose = () => {
  dialogVisible.value = false
  emit('close')
}

onMounted(() => {
  if (props.visible) {
    loadSessionHistory()
  }
})
</script>

<style scoped>
.session-history-modal .statistics-section {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
  padding: 1rem;
  background: var(--surface-50);
  border-radius: 8px;
}

.session-history-modal .stat-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  background: white;
  border-radius: 8px;
  border: 1px solid var(--surface-200);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.session-history-modal .stat-card .stat-icon {
  font-size: 2rem;
  color: var(--primary-500);
}

.session-history-modal .stat-card .stat-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.session-history-modal .stat-card .stat-content .stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-color);
}

.session-history-modal .stat-card .stat-content .stat-label {
  font-size: 0.875rem;
  color: var(--text-color-secondary);
}

.session-history-modal .timeline-container {
  padding: 1rem 0;
}

.session-history-modal .sessions-timeline .timeline-marker {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

.session-history-modal .sessions-timeline .timeline-marker.marker-completed {
  background: var(--green-500);
}

.session-history-modal .sessions-timeline .timeline-marker.marker-active {
  background: var(--blue-500);
}

.session-history-modal .sessions-timeline .timeline-marker.marker-paused {
  background: var(--orange-500);
}

.session-history-modal .sessions-timeline .timeline-marker.marker-abandoned {
  background: var(--red-500);
}

.session-history-modal .sessions-timeline .timeline-marker.marker-default {
  background: var(--surface-400);
}

.session-history-modal .session-card.card-completed {
  border-left: 4px solid var(--green-500);
}

.session-history-modal .session-card.card-active {
  border-left: 4px solid var(--blue-500);
}

.session-history-modal .session-card.card-paused {
  border-left: 4px solid var(--orange-500);
}

.session-history-modal .session-card.card-abandoned {
  border-left: 4px solid var(--red-500);
}

.session-history-modal .session-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.session-history-modal .session-header .session-number {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--primary-700);
}

.session-history-modal .session-details {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.session-history-modal .detail-row {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
}

.session-history-modal .detail-row .detail-icon {
  color: var(--primary-500);
  font-size: 1rem;
  margin-top: 0.125rem;
}

.session-history-modal .detail-row .detail-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.session-history-modal .detail-row .detail-content .detail-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-color-secondary);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.session-history-modal .detail-row .detail-content .detail-value {
  font-size: 0.875rem;
  color: var(--text-color);
}

.session-history-modal .detail-row.notes-row .session-notes {
  margin: 0;
  padding: 0.75rem;
  background: var(--surface-50);
  border-radius: 6px;
  font-size: 0.875rem;
  color: var(--text-color);
  line-height: 1.5;
  border-left: 3px solid var(--primary-300);
}

.session-history-modal .progress-bar-container {
  height: 8px;
  background: var(--surface-300);
  border-radius: 4px;
  overflow: hidden;
  margin-top: 0.5rem;
}

.session-history-modal .progress-bar-container .progress-bar {
  height: 100%;
  border-radius: 4px;
  transition: width 0.3s ease;
}

.session-history-modal .progress-bar-container .progress-bar.progress-completed {
  background: linear-gradient(90deg, var(--green-400), var(--green-600));
}

.session-history-modal .progress-bar-container .progress-bar.progress-active {
  background: linear-gradient(90deg, var(--blue-400), var(--blue-600));
}

.session-history-modal .progress-bar-container .progress-bar.progress-paused {
  background: linear-gradient(90deg, var(--orange-400), var(--orange-600));
}

.session-history-modal .progress-bar-container .progress-bar.progress-abandoned {
  background: linear-gradient(90deg, var(--red-400), var(--red-600));
}

.session-history-modal .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  text-align: center;
}

.session-history-modal .empty-state .empty-icon {
  font-size: 4rem;
  color: var(--surface-400);
  margin-bottom: 1rem;
}

.session-history-modal .empty-state .empty-message {
  font-size: 1rem;
  color: var(--text-color-secondary);
  margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
  .session-history-modal .statistics-section {
    grid-template-columns: 1fr;
  }

  .session-history-modal .stat-card .stat-icon {
    font-size: 1.5rem;
  }

  .session-history-modal .stat-card .stat-value {
    font-size: 1.25rem;
  }
}
</style>
