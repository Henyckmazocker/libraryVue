<template>
  <div class="album-lastfm-card">
    <!-- Loading -->
    <div v-if="isLoading" class="lastfm-state">
      <i class="fas fa-spinner fa-spin"></i>
      <span>Buscando en Last.fm…</span>
    </div>

    <!-- Not found on Last.fm -->
    <div v-else-if="!isLoading && !info && !error" class="lastfm-state lastfm-state--muted">
      <i class="fas fa-search"></i>
      <span>Este álbum no se encontró en Last.fm</span>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="lastfm-state lastfm-state--error">
      <i class="fas fa-exclamation-triangle"></i>
      <span>{{ error }}</span>
    </div>

    <!-- Data -->
    <div v-else-if="info" class="lastfm-data">
      <!-- Stats row -->
      <div class="lastfm-stats-row">
        <div class="lastfm-stat">
          <span class="lastfm-stat__value">{{ formatNumber(info.listeners) }}</span>
          <span class="lastfm-stat__label">Oyentes</span>
        </div>
        <div class="lastfm-stat">
          <span class="lastfm-stat__value">{{ formatNumber(info.playcount) }}</span>
          <span class="lastfm-stat__label">Reproducciones globales</span>
        </div>
        <div v-if="info.userplaycount !== null && info.userplaycount !== undefined" class="lastfm-stat lastfm-stat--personal">
          <span class="lastfm-stat__value">{{ formatNumber(info.userplaycount) }}</span>
          <span class="lastfm-stat__label">Tus plays</span>
        </div>
      </div>

      <!-- Tags -->
      <div v-if="info.tags && info.tags.length > 0" class="lastfm-tags">
        <a
          v-for="tag in info.tags"
          :key="tag.name"
          :href="tag.url || '#'"
          target="_blank"
          rel="noopener noreferrer"
          class="lastfm-tag"
        >{{ tag.name }}</a>
      </div>

      <!-- Configure Last.fm note (no personal playcount) -->
      <p v-if="!hasLastFmUsername" class="lastfm-configure-note">
        <router-link to="/profile">Configura tu usuario de Last.fm</router-link>
        para ver cuántas veces has escuchado este álbum.
      </p>

      <!-- Wiki summary -->
      <p v-if="info.wiki_summary" class="lastfm-wiki">{{ info.wiki_summary }}</p>

      <!-- External link -->
      <a v-if="info.url" :href="info.url" target="_blank" rel="noopener noreferrer" class="lastfm-external-link">
        <i class="fas fa-external-link-alt"></i>
        Ver en Last.fm
      </a>
    </div>
  </div>
</template>

<script>
import { computed, onMounted, watch } from 'vue'
import { useListeningStats } from '@/composables/useListeningStats'

export default {
  name: 'AlbumLastFmCard',

  props: {
    artistName: { type: String, required: true },
    albumName:  { type: String, required: true }
  },

  setup(props) {
    const { stats, isLoading, error, hasLastFmUsername, fetchStats } = useListeningStats()

    const info = computed(() => stats.value?.data ?? null)

    async function load() {
      if (!props.artistName || !props.albumName) return
      await fetchStats({
        statsType: 'album_info',
        artist: props.artistName,
        album: props.albumName
      })
    }

    function formatNumber(n) {
      if (!n && n !== 0) return '–'
      return Number(n).toLocaleString()
    }

    onMounted(() => load())
    watch(() => [props.artistName, props.albumName], () => load())

    return { info, isLoading, error, hasLastFmUsername, formatNumber }
  }
}
</script>

<style scoped>
.album-lastfm-card {
  /* Inherits the container's dark theme */
}

/* ─── State messages ─── */
.lastfm-state {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  color: var(--text-color-secondary, #9ca3af);
  font-size: 0.88rem;
  padding: 0.25rem 0;
}

.lastfm-state--muted {
  opacity: 0.7;
}

.lastfm-state--error {
  color: var(--color-error, #ef5350);
}

/* ─── Data layout ─── */
.lastfm-data {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

/* ─── Stats row ─── */
.lastfm-stats-row {
  display: flex;
  gap: 2.5rem;
  flex-wrap: wrap;
}

.lastfm-stat {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.lastfm-stat__value {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-color, #e0e0e0);
}

.lastfm-stat--personal .lastfm-stat__value {
  color: #d51007;
}

.lastfm-stat__label {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--text-color-secondary, #9ca3af);
}

/* ─── Tags ─── */
.lastfm-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.lastfm-tag {
  font-size: 0.76rem;
  padding: 3px 10px;
  border-radius: 12px;
  background: var(--surface-section, #2a2d36);
  color: var(--text-color-secondary, #9ca3af);
  border: 1px solid var(--surface-border, #2d3141);
  text-decoration: none;
  transition: border-color 0.15s, color 0.15s;
}

.lastfm-tag:hover {
  border-color: #d51007;
  color: var(--text-color, #e0e0e0);
}

/* ─── Notes ─── */
.lastfm-configure-note {
  font-size: 0.82rem;
  color: var(--text-color-secondary, #9ca3af);
  margin: 0;
}

.lastfm-configure-note a {
  color: #d51007;
  text-decoration: none;
}

.lastfm-configure-note a:hover {
  text-decoration: underline;
}

.lastfm-wiki {
  font-size: 0.84rem;
  color: var(--text-color-secondary, #9ca3af);
  line-height: 1.5;
  margin: 0;
  max-height: 4.5em;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
}

/* ─── External link ─── */
.lastfm-external-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: #d51007;
  text-decoration: none;
  align-self: flex-start;
}

.lastfm-external-link:hover {
  text-decoration: underline;
}
</style>
