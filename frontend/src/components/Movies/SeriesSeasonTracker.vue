<template>
  <div class="series-season-tracker">
    <div class="tracker-header">
      <h3 class="tracker-title">
        <i class="fas fa-layer-group"></i>
        Progreso de temporadas
      </h3>
      <div class="tracker-summary">
        <span class="progress-text">
          {{ watchedCount }} / {{ totalSeasons }} temporada{{ totalSeasons !== 1 ? 's' : '' }} vista{{ watchedCount !== 1 ? 's' : '' }}
        </span>
        <button
          v-if="watchedCount < totalSeasons"
          class="mark-all-btn"
          @click="markAllViewed"
          :disabled="isSaving"
          title="Marcar todas como vistas"
        >
          <i class="fas fa-check-double"></i>
          Marcar todas
        </button>
      </div>

      <!-- Barra de progreso -->
      <div class="progress-bar-wrapper">
        <div
          class="progress-bar-fill"
          :style="{ width: progressPercent + '%' }"
          :class="progressClass"
        ></div>
      </div>
    </div>

    <!-- Grid de temporadas -->
    <div class="seasons-grid">
      <div
        v-for="n in totalSeasons"
        :key="n"
        class="season-card"
        :class="seasonCardClass(n)"
        @click="toggleSeason(n)"
      >
        <div class="season-number">T{{ n }}</div>
        <div class="season-status-icon">
          <i :class="seasonIcon(n)"></i>
        </div>
        <div class="season-label">{{ seasonLabel(n) }}</div>
      </div>
    </div>

    <!-- Formulario de edición (aparece al expandir una temporada) -->
    <transition name="fade">
      <div v-if="editing !== null" class="season-editor">
        <div class="editor-header">
          <span><i class="fas fa-edit"></i> Temporada {{ editing }}</span>
          <button class="close-btn" @click="closeEditor"><i class="fas fa-times"></i></button>
        </div>

        <div class="editor-fields">
          <!-- Estado -->
          <div class="field-group">
            <label>Estado</label>
            <div class="status-options">
              <button
                v-for="opt in statusOptions"
                :key="opt.value"
                class="status-opt-btn"
                :class="{ active: editForm.status === opt.value }"
                @click="editForm.status = opt.value"
              >
                <i :class="opt.icon"></i> {{ opt.label }}
              </button>
            </div>
          </div>

          <!-- Fecha -->
          <div class="field-group">
            <label>Fecha de finalización</label>
            <input type="date" v-model="editForm.dateViewed" class="date-input" />
          </div>

          <!-- Rating -->
          <div class="field-group">
            <label>Valoración</label>
            <RatingComponent
              :rating="editForm.personalRating"
              :editable="true"
              :size="'medium'"
              @update:rating="editForm.personalRating = $event"
            />
          </div>

          <!-- Notas -->
          <div class="field-group">
            <label>Notas</label>
            <textarea v-model="editForm.notes" class="notes-input" rows="3" placeholder="Tus notas sobre esta temporada..."></textarea>
          </div>

          <!-- Acciones -->
          <div class="editor-actions">
            <button class="save-btn" @click="saveSeason" :disabled="isSaving">
              <i class="fas fa-save"></i> {{ isSaving ? 'Guardando...' : 'Guardar' }}
            </button>
            <button class="cancel-btn" @click="closeEditor">Cancelar</button>
          </div>
        </div>

        <!-- Lista de episodios (lazy, solo UI) -->
        <div v-if="episodes[editing]" class="episodes-list">
          <h4><i class="fas fa-list"></i> Episodios</h4>
          <div v-for="ep in episodes[editing]" :key="ep.Episode" class="episode-item">
            <span class="ep-number">{{ ep.Episode }}.</span>
            <span class="ep-title">{{ ep.Title }}</span>
            <span v-if="ep.imdbRating && ep.imdbRating !== 'N/A'" class="ep-rating">
              <i class="fab fa-imdb"></i> {{ ep.imdbRating }}
            </span>
          </div>
        </div>
        <button
          v-else-if="imdbId && editing !== null"
          class="load-episodes-btn"
          @click="loadEpisodes(editing)"
          :disabled="loadingEpisodes"
        >
          <i :class="loadingEpisodes ? 'fas fa-spinner fa-spin' : 'fas fa-list-ul'"></i>
          {{ loadingEpisodes ? 'Cargando episodios...' : 'Ver episodios' }}
        </button>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import RatingComponent from '@/components/common/RatingComponent.vue';

const props = defineProps({
  imdbId:      { type: String, required: true },
  totalSeasons:{ type: Number, required: true },
  progress:    { type: Object, default: () => ({}) }, // { seasonNumber: { status, date_viewed, personal_rating, notes } }
  isSaving:    { type: Boolean, default: false },
});

const emit = defineEmits(['season-updated']);

// ── Estado local ──────────────────────────────────────────
const localProgress = ref({ ...props.progress });
const editing       = ref(null);
const editForm      = ref({ status: 'viewed', dateViewed: null, personalRating: null, notes: null });
const episodes      = ref({});
const loadingEpisodes = ref(false);

// Sincronizar cuando el prop cambia (p.ej. carga inicial)
watch(() => props.progress, (val) => {
  localProgress.value = { ...val };
}, { deep: true });

// ── Computed ──────────────────────────────────────────────
const watchedCount = computed(() =>
  Object.values(localProgress.value).filter(s => s.status === 'viewed').length
);

const progressPercent = computed(() =>
  props.totalSeasons > 0 ? Math.round((watchedCount.value / props.totalSeasons) * 100) : 0
);

const progressClass = computed(() => {
  const p = progressPercent.value;
  if (p === 100) return 'complete';
  if (p >= 50)   return 'half';
  return 'low';
});

// ── Helpers ───────────────────────────────────────────────
const statusOptions = [
  { value: 'viewed',  label: 'Vista',   icon: 'fas fa-check-circle' },
  { value: 'partial', label: 'Parcial', icon: 'fas fa-adjust' },
  { value: 'skipped', label: 'Saltada', icon: 'fas fa-forward' },
];

function getSeasonData(n) {
  return localProgress.value[n] ?? null;
}

function seasonCardClass(n) {
  const d = getSeasonData(n);
  if (!d) return 'unseen';
  return { viewed: 'seen', partial: 'partial', skipped: 'skipped' }[d.status] ?? 'unseen';
}

function seasonIcon(n) {
  const d = getSeasonData(n);
  if (!d) return 'fas fa-circle';
  return {
    viewed:  'fas fa-check-circle',
    partial: 'fas fa-adjust',
    skipped: 'fas fa-forward',
  }[d.status] ?? 'fas fa-circle';
}

function seasonLabel(n) {
  const d = getSeasonData(n);
  if (!d) return 'Sin ver';
  return { viewed: 'Vista', partial: 'Parcial', skipped: 'Saltada' }[d.status] ?? 'Sin ver';
}

// ── Interacciones ─────────────────────────────────────────
function toggleSeason(n) {
  if (editing.value === n) {
    closeEditor();
    return;
  }
  const d = getSeasonData(n);
  editing.value = n;
  editForm.value = {
    status:         d?.status         ?? 'viewed',
    dateViewed:     d?.date_viewed    ?? null,
    personalRating: d?.personal_rating ?? null,
    notes:          d?.notes          ?? null,
  };
}

function closeEditor() {
  editing.value = null;
}

function saveSeason() {
  const n = editing.value;
  if (n === null) return;

  // Actualización optimista local
  localProgress.value = {
    ...localProgress.value,
    [n]: {
      status:          editForm.value.status,
      date_viewed:     editForm.value.dateViewed || null,
      personal_rating: editForm.value.personalRating || null,
      notes:           editForm.value.notes || null,
    },
  };

  emit('season-updated', {
    seasonNumber:   n,
    status:         editForm.value.status,
    dateViewed:     editForm.value.dateViewed || null,
    personalRating: editForm.value.personalRating || null,
    notes:          editForm.value.notes || null,
  });

  closeEditor();
}

function markAllViewed() {
  for (let n = 1; n <= props.totalSeasons; n++) {
    const existing = getSeasonData(n);
    if (!existing || existing.status !== 'viewed') {
      localProgress.value = {
        ...localProgress.value,
        [n]: { status: 'viewed', date_viewed: null, personal_rating: null, notes: null },
      };
      emit('season-updated', { seasonNumber: n, status: 'viewed', dateViewed: null, personalRating: null, notes: null });
    }
  }
}

// ── Episodios (solo UI) ───────────────────────────────────
async function loadEpisodes(seasonNumber) {
  if (!props.imdbId) return;
  loadingEpisodes.value = true;
  try {
    const apiKey = process.env.VUE_APP_OMDB_API_KEY;
    const url = `https://www.omdbapi.com/?apikey=${apiKey}&i=${props.imdbId}&Season=${seasonNumber}`;
    const { data } = await axios.get(url);
    if (data.Response === 'True' && data.Episodes) {
      episodes.value = { ...episodes.value, [seasonNumber]: data.Episodes };
    }
  } finally {
    loadingEpisodes.value = false;
  }
}
</script>

<style scoped>
.series-season-tracker {
  background: var(--surface-card, #1e1e2e);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid rgba(139, 92, 246, 0.2);
}

.tracker-header { margin-bottom: 1.5rem; }

.tracker-title {
  font-size: 1.1rem;
  font-weight: 600;
  color: var(--text-color, #fff);
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}
.tracker-title i { color: #a78bfa; }

.tracker-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}

.progress-text { font-size: 0.9rem; color: var(--text-color-secondary, #aaa); }

.mark-all-btn {
  background: rgba(139, 92, 246, 0.15);
  color: #a78bfa;
  border: 1px solid rgba(139, 92, 246, 0.3);
  border-radius: 6px;
  padding: 0.3rem 0.75rem;
  cursor: pointer;
  font-size: 0.82rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  transition: background 0.2s;
}
.mark-all-btn:hover:not(:disabled) { background: rgba(139, 92, 246, 0.3); }
.mark-all-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.progress-bar-wrapper {
  height: 6px;
  background: rgba(255,255,255,0.1);
  border-radius: 3px;
  overflow: hidden;
}
.progress-bar-fill {
  height: 100%;
  border-radius: 3px;
  transition: width 0.4s ease;
}
.progress-bar-fill.low      { background: #a78bfa; }
.progress-bar-fill.half     { background: #60a5fa; }
.progress-bar-fill.complete { background: #34d399; }

/* Grid de temporadas */
.seasons-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.season-card {
  background: rgba(255,255,255,0.05);
  border: 1.5px solid rgba(255,255,255,0.1);
  border-radius: 10px;
  padding: 0.75rem 0.5rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  user-select: none;
}
.season-card:hover { border-color: rgba(139, 92, 246, 0.5); background: rgba(139, 92, 246, 0.1); }
.season-card.seen   { border-color: #34d399; background: rgba(52, 211, 153, 0.1); }
.season-card.partial{ border-color: #fbbf24; background: rgba(251, 191, 36, 0.1); }
.season-card.skipped{ border-color: #6b7280; background: rgba(107, 114, 128, 0.1); }

.season-number { font-weight: 700; font-size: 1rem; color: var(--text-color, #fff); }

.season-status-icon { font-size: 1.2rem; margin: 0.3rem 0; }
.seen   .season-status-icon { color: #34d399; }
.partial .season-status-icon { color: #fbbf24; }
.skipped .season-status-icon { color: #6b7280; }
.unseen .season-status-icon  { color: rgba(255,255,255,0.25); }

.season-label { font-size: 0.72rem; color: var(--text-color-secondary, #aaa); }

/* Editor */
.season-editor {
  background: rgba(139, 92, 246, 0.06);
  border: 1px solid rgba(139, 92, 246, 0.25);
  border-radius: 10px;
  padding: 1rem;
  margin-top: 0.75rem;
}

.editor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-weight: 600;
  color: #a78bfa;
  margin-bottom: 1rem;
}
.close-btn {
  background: none;
  border: none;
  color: var(--text-color-secondary, #aaa);
  cursor: pointer;
  font-size: 1rem;
}

.field-group { margin-bottom: 0.875rem; }
.field-group label {
  display: block;
  font-size: 0.82rem;
  color: var(--text-color-secondary, #aaa);
  margin-bottom: 0.35rem;
}

.status-options { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.status-opt-btn {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 6px;
  color: var(--text-color, #fff);
  padding: 0.35rem 0.75rem;
  cursor: pointer;
  font-size: 0.82rem;
  display: flex;
  align-items: center;
  gap: 0.35rem;
  transition: all 0.15s;
}
.status-opt-btn.active {
  border-color: #a78bfa;
  background: rgba(139, 92, 246, 0.2);
  color: #a78bfa;
}
.status-opt-btn:hover:not(.active) { border-color: rgba(139,92,246,0.4); }

.date-input {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 6px;
  color: var(--text-color, #fff);
  padding: 0.4rem 0.6rem;
  font-size: 0.9rem;
  width: 100%;
  box-sizing: border-box;
}

.notes-input {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 6px;
  color: var(--text-color, #fff);
  padding: 0.5rem;
  font-size: 0.9rem;
  width: 100%;
  box-sizing: border-box;
  resize: vertical;
}

.editor-actions { display: flex; gap: 0.75rem; margin-top: 0.5rem; }
.save-btn {
  background: rgba(139, 92, 246, 0.2);
  border: 1px solid rgba(139, 92, 246, 0.5);
  border-radius: 6px;
  color: #a78bfa;
  padding: 0.45rem 1rem;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  transition: background 0.2s;
}
.save-btn:hover:not(:disabled) { background: rgba(139, 92, 246, 0.35); }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.cancel-btn {
  background: none;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 6px;
  color: var(--text-color-secondary, #aaa);
  padding: 0.45rem 0.85rem;
  cursor: pointer;
}
.cancel-btn:hover { border-color: rgba(255,255,255,0.25); }

/* Episodios */
.load-episodes-btn {
  margin-top: 0.75rem;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 6px;
  color: var(--text-color-secondary, #aaa);
  padding: 0.4rem 0.85rem;
  cursor: pointer;
  font-size: 0.85rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  transition: border-color 0.2s;
}
.load-episodes-btn:hover:not(:disabled) { border-color: rgba(139,92,246,0.4); }

.episodes-list { margin-top: 0.75rem; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 0.75rem; }
.episodes-list h4 { font-size: 0.9rem; color: #a78bfa; margin-bottom: 0.5rem; }
.episode-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0;
  font-size: 0.85rem;
  color: var(--text-color, #fff);
  border-bottom: 1px solid rgba(255,255,255,0.04);
}
.ep-number { color: var(--text-color-secondary, #aaa); min-width: 28px; }
.ep-rating { margin-left: auto; color: #fbbf24; font-size: 0.8rem; }

/* Transición */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s, transform 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
