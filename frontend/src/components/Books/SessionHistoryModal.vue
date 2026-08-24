<template>
  <!-- Modal Overlay -->
  <!-- El overlay cierra al pulsar fuera, pero no es un control: envuelve al propio
       diálogo. El cierre por teclado es Escape, en useFocusTrap. -->
  <!-- eslint-disable-next-line vuejs-accessibility/click-events-have-key-events, vuejs-accessibility/no-static-element-interactions -->
  <div
    v-if="dialogVisible"
    class="modal-overlay"
    @click="handleClose"
  >
    <div
      ref="dialogRef"
      class="modal-content"
      @click.stop
    >
      <!-- Header -->
      <div class="modal-header">
        <h2><i class="fas fa-history" /> Historial de lectura - {{ book.title }}</h2>
        <button
          class="close-button"
          @click="handleClose"
        >
          <i class="fas fa-times" />
        </button>
      </div>

      <div class="modal-body">
        <!-- Estadísticas generales -->
        <div
          v-if="statistics"
          class="statistics-section"
        >
          <div class="stat-item">
            <span class="stat-label">Sesiones completadas:</span>
            <span class="stat-value">{{ statistics.totalCompleted }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Duración promedio:</span>
            <span class="stat-value">{{ statistics.averageDuration }}</span>
          </div>
          <div class="stat-item">
            <span class="stat-label">Páginas totales leídas:</span>
            <span class="stat-value">{{ statistics.totalPagesRead }}</span>
          </div>
        </div>

        <!-- Acordeón de sesiones -->
        <div
          v-if="sessions && sessions.length > 0"
          class="sessions-container"
        >
          <Accordion :multiple="false">
            <AccordionTab
              v-for="item in timelineEvents"
              :key="item.sessionId"
            >
              <template #header>
                <div class="session-accordion-header">
                  <div class="session-title-group">
                    <i
                      :class="getMarkerIcon(item.status)"
                      class="session-icon"
                      :style="{ color: getStatusColor(item.status) }"
                    />
                    <span class="session-number">Sesión #{{ item.sessionNumber }}</span>
                  </div>
                  <span
                    class="session-badge"
                    :class="getBadgeClass(item.status)"
                  >
                    {{ getStatusLabel(item.status) }}
                  </span>
                </div>
              </template>

              <!-- Contenido de la sesión -->
              <div class="session-content">
                <!-- Información principal en líneas -->
                <div class="info-line">
                  <i class="fas fa-calendar-alt info-icon" />
                  <span class="info-label">Inicio:</span>
                  <span class="info-value">{{ formatDate(item.startedAt) }}</span>
                </div>

                <div
                  v-if="item.completedAt"
                  class="info-line"
                >
                  <i class="fas fa-calendar-check info-icon" />
                  <span class="info-label">Fin:</span>
                  <span class="info-value">{{ formatDate(item.completedAt) }}</span>
                </div>

                <div
                  v-if="item.duration"
                  class="info-line"
                >
                  <i class="fas fa-clock info-icon" />
                  <span class="info-label">Duración:</span>
                  <span class="info-value">{{ item.duration }}</span>
                </div>

                <div
                  v-if="item.finalPage"
                  class="info-line"
                >
                  <i class="fas fa-bookmark info-icon" />
                  <span class="info-label">Progreso:</span>
                  <span class="info-value">{{ item.finalPage }} / {{ book.total_pages }} páginas ({{ item.progressPercentage }}%)</span>
                </div>

                <!-- Barra de progreso -->
                <div
                  v-if="item.progressPercentage"
                  class="progress-bar-wrapper"
                >
                  <div class="progress-bar-bg">
                    <div
                      class="progress-bar-fill"
                      :class="getProgressBarClass(item.status)"
                      :style="{ width: item.progressPercentage + '%' }"
                    />
                  </div>
                </div>

                <!-- Notas de sesión -->
                <div
                  v-if="item.sessionNotes"
                  class="session-notes-section"
                >
                  <div class="notes-header">
                    <i class="fas fa-comment-alt" />
                    <span>Notas</span>
                  </div>
                  <p class="notes-content">
                    {{ item.sessionNotes }}
                  </p>
                </div>

                <!-- Actualizaciones de progreso -->
                <div
                  v-if="item.progressUpdates && item.progressUpdates.length > 0"
                  class="progress-updates-section"
                >
                  <div class="updates-header">
                    <i class="fas fa-list-ul" />
                    <span>Actualizaciones de progreso ({{ item.progressUpdates.length }})</span>
                  </div>
                  <div class="updates-list">
                    <div
                      v-for="(update, index) in item.progressUpdates"
                      :key="index"
                      class="update-item"
                    >
                      <div class="update-line">
                        <i class="fas fa-clock update-icon" />
                        <span class="update-date">{{ formatDate(update.logged_at) }}</span>
                        <span
                          class="update-badge"
                          :class="getProgressTypeBadgeClass(update.progress_type)"
                        >
                          {{ getProgressTypeLabel(update.progress_type) }}
                        </span>
                      </div>
                      <div class="update-pages-line">
                        <span class="page-info">
                          <span class="page-label">Pág. anterior:</span>
                          <span class="page-number">{{ update.previous_page }}</span>
                        </span>
                        <i class="fas fa-arrow-right arrow-icon" />
                        <span class="page-info">
                          <span class="page-label">Pág. actual:</span>
                          <span class="page-number highlight">{{ update.current_page }}</span>
                        </span>
                        <span
                          v-if="update.progress_type === 'advance'"
                          class="pages-diff advance"
                        >
                          +{{ update.current_page - update.previous_page }}
                        </span>
                        <span
                          v-else
                          class="pages-diff other"
                        >
                          {{ update.current_page - update.previous_page }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </AccordionTab>
          </Accordion>
        </div>

        <!-- Estado vacío -->
        <div
          v-else
          class="empty-state"
        >
          <i class="fas fa-book empty-icon" />
          <p class="empty-message">
            No hay sesiones de lectura registradas para este libro
          </p>
        </div>
      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <button
          class="cancel-button"
          @click="handleClose"
        >
          <i class="fas fa-times" /> Cerrar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, defineProps, defineEmits, onMounted } from 'vue'
import { useReadingSessions } from '@/composables/useReadingSessions'
import { useFocusTrap } from '@/composables/useFocusTrap'
import Logger from '@/utils/logger'
import Accordion from 'primevue/accordion'
import AccordionTab from 'primevue/accordiontab'

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
const progressHistory = ref([])
const statistics = ref(null)
const isLoading = ref(false)

// Watchers
watch(() => props.visible, (newValue) => {
  dialogVisible.value = newValue
  if (newValue) {
    loadSessionHistory()
  }
})

// Computed properties
const timelineEvents = computed(() => {
  return sessions.value.map(session => {
    // Get progress updates for this session
    const updates = getProgressUpdatesForSession(session.id)

    // Calculate the actual final page from progress updates
    // The most recent update (first in the sorted array) has the current page
    let actualFinalPage = session.final_page
    if (updates && updates.length > 0) {
      // Get the latest current_page from progress updates
      actualFinalPage = updates[0].current_page || session.final_page
    }

    const progressPercentage = calculateProgressPercentage(actualFinalPage)

    Logger.debug(`[SessionHistoryModal] Session ${session.session_number}:`, {
      sessionId: session.id,
      end_page_from_db: session.final_page,
      actualFinalPage,
      total_pages: props.book.total_pages,
      progressPercentage,
      updates_count: updates.length
    })

    return {
      sessionId: session.id,
      sessionNumber: session.session_number,
      status: session.status,
      startedAt: session.started_at,
      completedAt: session.completed_at,
      finalPage: actualFinalPage,
      sessionNotes: session.session_notes,
      duration: calculateDuration(session.started_at, session.completed_at),
      progressPercentage,
      progressUpdates: updates
    }
  })
})

// Computed property to group progress by session
const progressBySession = computed(() => {
  const grouped = {}
  progressHistory.value.forEach(progress => {
    const sessionId = progress.reading_session_id
    if (!grouped[sessionId]) {
      grouped[sessionId] = []
    }
    grouped[sessionId].push(progress)
  })
  // Sort each session's progress by date (newest first)
  Object.keys(grouped).forEach(sessionId => {
    grouped[sessionId].sort((a, b) => new Date(b.logged_at) - new Date(a.logged_at))
  })

  Logger.debug('[SessionHistoryModal] progressBySession grouped:', {
    sessionIds: Object.keys(grouped),
    totalGroups: Object.keys(grouped).length,
    grouped
  })

  return grouped
})

// Métodos
const loadSessionHistory = async () => {
  try {
    if (!props.book?.isbn) {
      Logger.error('[SessionHistoryModal] No ISBN available for book')
      return
    }

    Logger.debug('[SessionHistoryModal] Book data:', {
      isbn: props.book.isbn,
      title: props.book.title,
      total_pages: props.book.total_pages
    })

    isLoading.value = true

    // Create composable with current book ISBN
    const { loadHistory, loadProgressHistory } = useReadingSessions(props.book.isbn)

    // Load both session history and progress history in parallel
    const [historyResult, progressResult] = await Promise.all([
      loadHistory(),
      loadProgressHistory()
    ])

    Logger.debug('[SessionHistoryModal] History result:', historyResult)
    Logger.debug('[SessionHistoryModal] Progress result:', progressResult)

    if (historyResult && historyResult.success && Array.isArray(historyResult.history)) {
      sessions.value = historyResult.history.sort((a, b) => b.session_number - a.session_number)
      Logger.debug('[SessionHistoryModal] Sessions loaded:', sessions.value.length)
      Logger.debug('[SessionHistoryModal] First session data:', sessions.value[0])
    } else {
      Logger.warn('[SessionHistoryModal] No sessions found or invalid format')
    }

    if (progressResult && progressResult.success) {
      progressHistory.value = progressResult.history || []
      Logger.debug('[SessionHistoryModal] Progress history loaded:', progressHistory.value.length, 'entries')
      if (progressHistory.value.length > 0) {
        Logger.debug('[SessionHistoryModal] First progress entry:', progressHistory.value[0])
        Logger.debug('[SessionHistoryModal] Progress session IDs:', progressHistory.value.map(p => p.reading_session_id))
      }
    }

    calculateStatistics()
  } catch (error) {
    Logger.error('[SessionHistoryModal] Error loading session history:', error)
  } finally {
    isLoading.value = false
  }
}

const getProgressUpdatesForSession = (sessionId) => {
  if (!sessionId) return []
  return progressBySession.value[sessionId] || []
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

  // Calculate total pages read using the actual final pages from timeline events
  const totalPagesRead = timelineEvents.value.reduce((sum, event) => {
    return sum + (event.finalPage || 0)
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
  const totalPages = props.book?.total_pages

  Logger.debug('[SessionHistoryModal] calculateProgressPercentage:', {
    finalPage,
    totalPages,
    finalPageType: typeof finalPage,
    totalPagesType: typeof totalPages,
    result: finalPage && totalPages && totalPages !== 0 ? Math.round((finalPage / totalPages) * 100) : 0
  })

  if (!finalPage || !totalPages || totalPages === 0) return 0
  return Math.round((finalPage / totalPages) * 100)
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

const getMarkerIcon = (status) => {
  const icons = {
    'completed': 'fas fa-check-circle',
    'active': 'fas fa-play-circle',
    'paused': 'fas fa-pause-circle',
    'abandoned': 'fas fa-times-circle'
  }
  return icons[status] || 'fas fa-circle'
}

const getStatusColor = (status) => {
  const colors = {
    'completed': 'var(--color-success)',
    'active': 'var(--color-info)',
    'paused': 'var(--color-warning)',
    'abandoned': 'var(--color-error)'
  }
  return colors[status] || 'var(--color-text-muted)'
}

const getBadgeClass = (status) => {
  const classes = {
    'completed': 'badge-success',
    'active': 'badge-info',
    'paused': 'badge-warning',
    'abandoned': 'badge-danger'
  }
  return classes[status] || 'badge-default'
}

const getProgressTypeBadgeClass = (progressType) => {
  const classes = {
    'advance': 'badge-success',
    'backtrack': 'badge-warning',
    'restart': 'badge-danger'
  }
  return classes[progressType] || 'badge-default'
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

const getProgressTypeLabel = (progressType) => {
  const labels = {
    'advance': 'Avance',
    'backtrack': 'Retroceso',
    'restart': 'Reinicio'
  }
  return labels[progressType] || progressType
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

const handleClose = () => {
  dialogVisible.value = false
  emit('close')
}

const dialogRef = ref(null)
useFocusTrap(dialogRef, { isOpen: dialogVisible, onEscape: handleClose })

onMounted(() => {
  if (props.visible) {
    loadSessionHistory()
  }
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

/* Modal Overlay */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 1000;
  backdrop-filter: blur(2px);
}

.modal-content {
  background: var(--color-background-mute);
  border-radius: 20px;
  width: 90%;
  max-width: 900px;
  max-height: 90vh;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  display: flex;
  flex-direction: column;
}

/* Modal Header */
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 25px 30px;
  border-bottom: 1px solid var(--color-background-mute);
  background: var(--color-background-mute);
}

.modal-header h2 {
  color: var(--color-text);
  font-size: 1.5rem;
  font-weight: 600;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}

.close-button {
  background: none;
  border: none;
  color: var(--color-text-muted);
  font-size: 1.5rem;
  cursor: pointer;
  padding: 5px;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: all 0.2s ease;
}

.close-button:hover {
  color: var(--color-text);
  background: rgba(255, 255, 255, 0.1);
}

/* Modal Body */
.modal-body {
  padding: 25px 30px;
  overflow-y: auto;
  flex: 1;
}

/* Estadísticas Section */
.statistics-section {
  background: var(--color-background-soft);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 25px;
  border: 1px solid var(--color-background-mute);
}

.stat-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-background-mute);
}

.stat-item:last-child {
  border-bottom: none;
}

.stat-label {
  color: var(--color-text-muted);
  font-size: 0.95rem;
  font-weight: 500;
}

.stat-value {
  color: var(--color-text);
  font-size: 1.1rem;
  font-weight: 700;
}

/* Sessions Container */
.sessions-container {
  margin-top: 20px;
}

/* Accordion Header Customization */
.session-accordion-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  gap: 15px;
}

.session-title-group {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.session-icon {
  font-size: 1.2rem;
}

.session-number {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--color-text);
}

/* Badges */
.session-badge,
.update-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
}

.badge-success {
  background: var(--color-success);
  color: var(--color-on-status);
}

.badge-info {
  background: var(--color-info);
  color: var(--color-on-status);
}

.badge-warning {
  background: var(--color-warning);
  color: var(--color-text);
}

.badge-danger {
  background: var(--color-error);
  color: var(--color-on-status);
}

.badge-default {
  background: var(--color-border);
  color: white;
}

/* Session Content */
.session-content {
  padding: 20px;
  background: var(--color-background-soft);
  border-radius: 8px;
}

/* Info Lines */
.info-line {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-background-mute);
}

.info-line:last-of-type {
  border-bottom: none;
}

.info-icon {
  color: var(--color-info);
  font-size: 1rem;
  min-width: 20px;
}

.info-label {
  color: var(--color-text-muted);
  font-size: 0.9rem;
  font-weight: 600;
  min-width: 80px;
}

.info-value {
  color: var(--color-text);
  font-size: 0.95rem;
  flex: 1;
}

/* Progress Bar */
.progress-bar-wrapper {
  margin: 15px 0;
  padding: 10px 0;
}

.progress-bar-bg {
  height: 10px;
  background: var(--color-background-mute);
  border-radius: 5px;
  overflow: hidden;
  position: relative;
}

.progress-bar-fill {
  height: 100%;
  border-radius: 5px;
  transition: width 0.3s ease;
}

.progress-bar-fill.progress-completed {
  background: var(--color-success);
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10));
}

.progress-bar-fill.progress-active {
  background: var(--color-info);
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10));
}

.progress-bar-fill.progress-paused {
  background: var(--color-warning);
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10));
}

.progress-bar-fill.progress-abandoned {
  background: var(--color-error);
  background-image: linear-gradient(90deg, rgba(0, 0, 0, 0.18), rgba(255, 255, 255, 0.10));
}

/* Session Notes */
.session-notes-section {
  margin-top: 20px;
  padding: 15px;
  background: var(--color-background-mute);
  border-radius: 8px;
  border-left: 4px solid var(--color-info);
}

.notes-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  color: var(--color-info);
  font-weight: 600;
  font-size: 0.9rem;
}

.notes-content {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.9rem;
  line-height: 1.6;
}

/* Progress Updates Section */
.progress-updates-section {
  margin-top: 20px;
  padding: 15px;
  background: var(--color-background-mute);
  border-radius: 8px;
}

.updates-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 15px;
  color: var(--color-warning);
  font-weight: 600;
  font-size: 0.95rem;
}

.updates-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.update-item {
  padding: 12px;
  background: var(--color-background-soft);
  border-radius: 8px;
  border: 1px solid var(--color-background-mute);
}

.update-line {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 10px;
  flex-wrap: wrap;
}

.update-icon {
  color: var(--color-text-muted);
  font-size: 0.85rem;
}

.update-date {
  color: var(--color-text-muted);
  font-size: 0.85rem;
  flex: 1;
}

.update-pages-line {
  display: flex;
  align-items: center;
  gap: 15px;
  flex-wrap: wrap;
  padding: 8px 0;
}

.page-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-label {
  color: var(--color-text-muted);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.page-number {
  color: var(--color-text);
  font-size: 1.2rem;
  font-weight: 700;
}

.page-number.highlight {
  color: var(--color-info);
}

.arrow-icon {
  color: var(--color-text-secondary);
  font-size: 1rem;
}

.pages-diff {
  margin-left: auto;
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 0.85rem;
  font-weight: 600;
}

.pages-diff.advance {
  background: var(--color-success);
  color: var(--color-on-status);
}

.pages-diff.other {
  background: var(--color-border);
  color: white;
}

/* Modal Footer */
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 15px;
  padding: 20px 30px;
  border-top: 1px solid var(--color-background-mute);
  background: var(--color-background-mute);
}

.cancel-button {
  padding: 10px 20px;
  font-size: 1rem;
  background: transparent;
  color: var(--color-text-muted);
  border: 1px solid var(--color-background-mute);
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.cancel-button:hover {
  color: var(--color-text);
  border-color: var(--color-border);
  background: rgba(255, 255, 255, 0.05);
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
}

.empty-icon {
  font-size: 4rem;
  color: var(--color-text-secondary);
  margin-bottom: 20px;
}

.empty-message {
  font-size: 1.1rem;
  color: var(--color-text-muted);
  margin: 0;
}

/* Responsive */
@include responsive-below(md) {
  .modal-content {
    width: 95%;
    max-width: none;
    border-radius: 15px;
  }

  .modal-header,
  .modal-body,
  .modal-footer {
    padding: 20px;
  }

  .modal-header h2 {
    font-size: 1.2rem;
  }

  .session-accordion-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .info-line {
    flex-wrap: wrap;
  }

  .info-label {
    min-width: auto;
  }

  .update-pages-line {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .arrow-icon {
    transform: rotate(90deg);
  }

  .pages-diff {
    margin-left: 0;
  }
}
</style>
