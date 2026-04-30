<template>
  <div class="listening-stats">
    <!-- No Last.fm configured -->
    <div v-if="!hasLastFmUsername" class="lastfm-not-configured">
      <i class="fas fa-music" style="font-size:2rem; color:#d51007; opacity:.5;"></i>
      <p>Configura tu usuario de Last.fm en tu <router-link to="/profile">perfil</router-link> para ver estadísticas de escucha.</p>
    </div>

    <template v-else>
      <!-- Controls -->
      <div class="stats-controls">
        <div class="control-group">
          <label>Tipo</label>
          <select v-model="selectedType" @change="load" class="stats-select">
            <option value="user_info">Resumen</option>
            <option value="top_albums">Top Álbumes</option>
            <option value="top_artists">Top Artistas</option>
            <option value="top_tracks">Top Canciones</option>
            <option value="recent_tracks">Recientes</option>
            <option value="loved_tracks">Favoritas</option>
          </select>
        </div>

        <div v-if="showPeriod" class="control-group">
          <label>Período</label>
          <select v-model="selectedPeriod" @change="load" class="stats-select">
            <option value="overall">Todo el tiempo</option>
            <option value="12month">Último año</option>
            <option value="6month">6 meses</option>
            <option value="3month">3 meses</option>
            <option value="1month">1 mes</option>
            <option value="7day">7 días</option>
          </select>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="isLoading" class="stats-loading">
        <i class="fas fa-spinner fa-spin"></i>
        <span>Cargando estadísticas de Last.fm…</span>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="stats-error">
        <i class="fas fa-exclamation-triangle"></i>
        <span>{{ error }}</span>
      </div>

      <!-- User info summary -->
      <div v-else-if="stats && selectedType === 'user_info'" class="user-info-card">
        <div class="user-info-stat">
          <span class="stat-value">{{ formatNumber(stats.data?.playcount) }}</span>
          <span class="stat-label">Scrobbles</span>
        </div>
        <div class="user-info-stat">
          <span class="stat-value">{{ formatNumber(stats.data?.artist_count) }}</span>
          <span class="stat-label">Artistas</span>
        </div>
        <div class="user-info-stat">
          <span class="stat-value">{{ formatNumber(stats.data?.album_count) }}</span>
          <span class="stat-label">Álbumes</span>
        </div>
        <div class="user-info-stat">
          <span class="stat-value">{{ formatNumber(stats.data?.track_count) }}</span>
          <span class="stat-label">Canciones</span>
        </div>
        <a v-if="stats.data?.url" :href="stats.data.url" target="_blank" rel="noopener noreferrer" class="lastfm-link">
          <i class="fas fa-external-link-alt"></i> Ver perfil en Last.fm
        </a>
      </div>

      <!-- List results (top_albums / top_artists / top_tracks / recent / loved) -->
      <div v-else-if="stats && Array.isArray(stats.data) && stats.data.length > 0" class="stats-list">
        <div v-for="(item, idx) in stats.data" :key="idx" class="stats-item">
          <span class="item-rank">#{{ idx + 1 }}</span>
          <img
            v-if="item.image"
            :src="item.image"
            :alt="item.name"
            class="item-image"
            @error="handleImgError($event)"
          />
          <div v-else class="item-image-placeholder">
            <i class="fas fa-music"></i>
          </div>
          <div class="item-info">
            <a
              v-if="item.url"
              :href="item.url"
              target="_blank"
              rel="noopener noreferrer"
              class="item-name"
            >{{ item.name }}</a>
            <span v-else class="item-name">{{ item.name }}</span>
            <span v-if="item.artist" class="item-sub">{{ item.artist }}</span>
            <span v-if="item.now_playing" class="now-playing-badge">
              <i class="fas fa-volume-up"></i> Escuchando ahora
            </span>
          </div>
          <span v-if="item.playcount" class="item-playcount">
            {{ formatNumber(item.playcount) }} plays
          </span>
          <span v-else-if="item.date_text && !item.now_playing" class="item-date">
            {{ item.date_text }}
          </span>
        </div>
      </div>

      <div v-else-if="stats && !isLoading" class="stats-empty">
        <i class="fas fa-music"></i>
        <p>No hay datos disponibles para esta selección.</p>
      </div>
    </template>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useListeningStats } from '@/composables/useListeningStats'

export default {
  name: 'ListeningStats',

  setup() {
    const { stats, isLoading, error, hasLastFmUsername, fetchStats } = useListeningStats()

    const selectedType = ref('user_info')
    const selectedPeriod = ref('overall')

    const showPeriod = computed(() =>
      ['top_albums', 'top_artists', 'top_tracks'].includes(selectedType.value)
    )

    function load() {
      fetchStats({
        statsType: selectedType.value,
        period: selectedPeriod.value,
        limit: 20
      })
    }

    function formatNumber(n) {
      if (!n) return '0'
      return Number(n).toLocaleString()
    }

    function handleImgError(event) {
      event.target.style.display = 'none'
    }

    onMounted(() => {
      if (hasLastFmUsername.value) load()
    })

    return {
      stats,
      isLoading,
      error,
      hasLastFmUsername,
      selectedType,
      selectedPeriod,
      showPeriod,
      load,
      formatNumber,
      handleImgError
    }
  }
}
</script>

<style scoped>
.listening-stats {
  font-size: 0.9rem;
}

/* ─── Not configured ─── */
.lastfm-not-configured {
  text-align: center;
  padding: 2rem 1rem;
  color: var(--text-color-secondary, #9ca3af);
}

.lastfm-not-configured p {
  margin-top: 0.5rem;
}

.lastfm-not-configured a {
  color: #d51007;
}

/* ─── Controls ─── */
.stats-controls {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}

.control-group {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.control-group label {
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--text-color-secondary, #9ca3af);
  text-transform: uppercase;
}

.stats-select {
  padding: 0.35rem 0.6rem;
  border: 1px solid var(--surface-border, #2d3141);
  border-radius: 6px;
  font-size: 0.88rem;
  background: var(--surface-section, #2a2d36);
  color: var(--text-color, #e0e0e0);
  cursor: pointer;
}

.stats-select:focus {
  outline: none;
  border-color: var(--primary-color, #1D4E4A);
}

/* ─── Loading / error / empty ─── */
.stats-loading,
.stats-error,
.stats-empty {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 1.5rem 0;
  color: var(--text-color-secondary, #9ca3af);
}

.stats-error {
  color: var(--color-error, #ef5350);
}

/* ─── User info summary ─── */
.user-info-card {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
  align-items: flex-end;
  padding: 0.5rem 0;
}

.user-info-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-value {
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--text-color, #e0e0e0);
}

.stat-label {
  font-size: 0.75rem;
  color: var(--text-color-secondary, #9ca3af);
  text-transform: uppercase;
}

.lastfm-link {
  margin-left: auto;
  font-size: 0.82rem;
  color: #d51007;
  text-decoration: none;
  align-self: center;
}

.lastfm-link:hover {
  text-decoration: underline;
}

/* ─── List items ─── */
.stats-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 480px;
  overflow-y: auto;
  padding-right: 0.25rem;
}

.stats-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.4rem 0.5rem;
  border-radius: 8px;
  background: var(--surface-section, #2a2d36);
}

.stats-item:hover {
  background: var(--surface-hover, #252836);
}

.item-rank {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-color-secondary, #9ca3af);
  min-width: 2rem;
  text-align: right;
}

.item-image {
  width: 40px;
  height: 40px;
  border-radius: 4px;
  object-fit: cover;
  flex-shrink: 0;
}

.item-image-placeholder {
  width: 40px;
  height: 40px;
  border-radius: 4px;
  background: var(--surface-hover, #252836);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-color-secondary, #9ca3af);
  flex-shrink: 0;
}

.item-info {
  flex: 1;
  overflow: hidden;
}

.item-name {
  display: block;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  color: var(--text-color, #e0e0e0);
  text-decoration: none;
}

a.item-name:hover {
  text-decoration: underline;
  color: var(--primary-color, #1D4E4A);
}

.item-sub {
  display: block;
  font-size: 0.8rem;
  color: var(--text-color-secondary, #9ca3af);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.now-playing-badge {
  font-size: 0.75rem;
  color: #16a34a;
  font-weight: 600;
}

.item-playcount,
.item-date {
  font-size: 0.78rem;
  color: var(--text-color-secondary, #9ca3af);
  white-space: nowrap;
  flex-shrink: 0;
}
</style>
