<template>
  <div class="listening-stats">
    <!-- No Last.fm configured -->
    <div
      v-if="!hasLastFmUsername"
      class="lastfm-not-configured"
    >
      <i
        class="fas fa-music u-brand-lastfm"
        style="font-size:2rem; opacity:.5;"
      />
      <p>
        {{ t('listening.needsUser.before') }}<router-link to="/profile">
          {{ t('listening.needsUser.link') }}
        </router-link>{{ t('listening.needsUser.after') }}
      </p>
    </div>

    <template v-else>
      <!-- Controls -->
      <div class="stats-controls">
        <div class="control-group">
          <label for="listening-stats-type">{{ t('listening.typeLabel') }}</label>
          <select
            id="listening-stats-type"
            v-model="selectedType"
            class="stats-select"
            @change="load"
          >
            <option value="user_info">
              {{ t('listening.type.user_info') }}
            </option>
            <option value="top_albums">
              {{ t('listening.type.top_albums') }}
            </option>
            <option value="top_artists">
              {{ t('listening.type.top_artists') }}
            </option>
            <option value="top_tracks">
              {{ t('listening.type.top_tracks') }}
            </option>
            <option value="recent_tracks">
              {{ t('listening.type.recent_tracks') }}
            </option>
            <option value="loved_tracks">
              {{ t('listening.type.loved_tracks') }}
            </option>
          </select>
        </div>

        <div
          v-if="showPeriod"
          class="control-group"
        >
          <label for="listening-stats-period">{{ t('listening.periodLabel') }}</label>
          <select
            id="listening-stats-period"
            v-model="selectedPeriod"
            class="stats-select"
            @change="load"
          >
            <option value="overall">
              {{ t('listening.period.overall') }}
            </option>
            <option value="12month">
              {{ t('listening.period.12month') }}
            </option>
            <option value="6month">
              {{ t('listening.period.6month') }}
            </option>
            <option value="3month">
              {{ t('listening.period.3month') }}
            </option>
            <option value="1month">
              {{ t('listening.period.1month') }}
            </option>
            <option value="7day">
              {{ t('listening.period.7day') }}
            </option>
          </select>
        </div>
      </div>

      <!-- Degradación: las gráficas salen de una caché caducada -->
      <StaleNotice
        :stale="stale"
        :cached-at="cachedAt"
        provider="Last.fm"
      />

      <!-- Loading -->
      <div
        v-if="isLoading"
        class="stats-loading"
      >
        <i class="fas fa-spinner fa-spin" />
        <span>{{ t('listening.loading') }}</span>
      </div>

      <!-- Error -->
      <div
        v-else-if="error"
        class="stats-error"
      >
        <i class="fas fa-exclamation-triangle" />
        <span>{{ error }}</span>
      </div>

      <!-- User info summary -->
      <div
        v-else-if="stats && selectedType === 'user_info'"
        class="user-info-card"
      >
        <div class="user-info-stat">
          <span class="stat-value">{{ formatNumber(stats.data?.playcount) }}</span>
          <span class="stat-label">{{ t('listening.stats.scrobbles') }}</span>
        </div>
        <div class="user-info-stat">
          <span class="stat-value">{{ formatNumber(stats.data?.artist_count) }}</span>
          <span class="stat-label">{{ t('listening.stats.artists') }}</span>
        </div>
        <div class="user-info-stat">
          <span class="stat-value">{{ formatNumber(stats.data?.album_count) }}</span>
          <span class="stat-label">{{ t('listening.stats.albums') }}</span>
        </div>
        <div class="user-info-stat">
          <span class="stat-value">{{ formatNumber(stats.data?.track_count) }}</span>
          <span class="stat-label">{{ t('listening.stats.tracks') }}</span>
        </div>
        <a
          v-if="stats.data?.url"
          :href="stats.data.url"
          target="_blank"
          rel="noopener noreferrer"
          class="lastfm-link"
        >
          <i class="fas fa-external-link-alt" /> {{ t('listening.profileLink') }}
        </a>
      </div>

      <!-- List results (top_albums / top_artists / top_tracks / recent / loved) -->
      <div
        v-else-if="stats && Array.isArray(stats.data) && stats.data.length > 0"
        class="stats-list"
      >
        <div
          v-for="(item, idx) in stats.data"
          :key="idx"
          class="stats-item"
        >
          <span class="item-rank">#{{ idx + 1 }}</span>
          <img
            v-if="item.image"
            :src="item.image"
            :alt="item.name"
            class="item-image"
            loading="lazy"
            decoding="async"
            @error="handleImgError($event)"
          >
          <div
            v-else
            class="item-image-placeholder"
          >
            <i class="fas fa-music" />
          </div>
          <div class="item-info">
            <a
              v-if="item.url"
              :href="item.url"
              target="_blank"
              rel="noopener noreferrer"
              class="item-name"
            >{{ item.name }}</a>
            <span
              v-else
              class="item-name"
            >{{ item.name }}</span>
            <span
              v-if="item.artist"
              class="item-sub"
            >{{ item.artist }}</span>
            <span
              v-if="item.now_playing"
              class="now-playing-badge"
            >
              <i class="fas fa-volume-up" /> {{ t('listening.nowPlaying') }}
            </span>
          </div>
          <span
            v-if="item.playcount"
            class="item-playcount"
          >
            {{ t('listening.plays', { n: formatNumber(item.playcount) }) }}
          </span>
          <span
            v-else-if="item.date_text && !item.now_playing"
            class="item-date"
          >
            {{ item.date_text }}
          </span>
        </div>
      </div>

      <EmptyState
        v-else-if="stats && !isLoading"
        icon="fas fa-music"
        :title="t('listening.empty')"
      />
    </template>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { useListeningStats } from '@/composables/useListeningStats'
import { useI18n } from '@/composables/useI18n'
import StaleNotice from '@/components/shared/StaleNotice.vue'

export default {
  name: 'ListeningStats',

  components: { StaleNotice, EmptyState },

  setup() {
    const { stats, isLoading, error, stale, cachedAt, hasLastFmUsername, fetchStats } = useListeningStats()
    const { t } = useI18n()

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
      t,
      stats,
      isLoading,
      error,
      stale,
      cachedAt,
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

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.listening-stats {
  font-size: var(--font-size-sm);
}

/* ─── Not configured ─── */
.lastfm-not-configured {
  text-align: center;
  padding: spacing(xl) spacing(md);
  color: var(--text-color-secondary, var(--color-text-muted));
}

.lastfm-not-configured p {
  margin-top: spacing(xs);
}

.lastfm-not-configured a {
  /* stylelint-disable-next-line color-no-hex -- Last.fm: color de marca, drift intencional (styles.md) */
  color: #d51007;
}

/* ─── Controls ─── */
.stats-controls {
  display: flex;
  gap: spacing(md);
  margin-bottom: spacing(md);
  flex-wrap: wrap;
}

.control-group {
  display: flex;
  flex-direction: column;
  gap: spacing(2xs);
}

.control-group label {
  font-size: var(--font-size-xs);
  font-weight: 600;
  color: var(--text-color-secondary, var(--color-text-muted));
  text-transform: uppercase;
}

.stats-select {
  padding: spacing(xs) spacing(sm);
  border: 1px solid var(--surface-border, var(--color-border));
  border-radius: radius(sm);
  font-size: var(--font-size-sm);
  background: var(--surface-section, var(--color-background-mute));
  color: var(--text-color, var(--color-text));
  cursor: pointer;
}

.stats-select:focus {
  outline: none;
  border-color: var(--primary-color, var(--color-primary));
}

/* ─── Loading / error / empty ─── */
.stats-loading,
.stats-error,
.stats-error {
  color: var(--color-error, var(--color-error));
}

/* ─── User info summary ─── */
.user-info-card {
  display: flex;
  gap: spacing(lg);
  flex-wrap: wrap;
  align-items: flex-end;
  padding: spacing(xs) 0;
}

.user-info-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.stat-value {
  font-size: var(--font-size-xl);
  font-weight: 700;
  color: var(--text-color, var(--color-text));
}

.stat-label {
  font-size: var(--font-size-xs);
  color: var(--text-color-secondary, var(--color-text-muted));
  text-transform: uppercase;
}

.lastfm-link {
  margin-left: auto;
  font-size: var(--font-size-sm);
  /* stylelint-disable-next-line color-no-hex -- Last.fm: color de marca, drift intencional (styles.md) */
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
  gap: spacing(xs);
  max-height: 480px;
  overflow-y: auto;
  padding-right: spacing(2xs);
}

.stats-item {
  display: flex;
  align-items: center;
  gap: spacing(sm);
  padding: spacing(xs) spacing(xs);
  border-radius: radius(md);
  background: var(--surface-section, var(--color-background-mute));
}

.stats-item:hover {
  background: var(--surface-hover, var(--color-background-mute));
}

.item-rank {
  font-size: var(--font-size-xs);
  font-weight: 600;
  color: var(--text-color-secondary, var(--color-text-muted));
  min-width: min(2rem, 100%);
  text-align: right;
}

.item-image {
  width: 40px;
  height: 40px;
  border-radius: radius(sm);
  object-fit: cover;
  flex-shrink: 0;
}

.item-image-placeholder {
  width: 40px;
  height: 40px;
  border-radius: radius(sm);
  background: var(--surface-hover, var(--color-background-mute));
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-color-secondary, var(--color-text-muted));
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
  color: var(--text-color, var(--color-text));
  text-decoration: none;
}

a.item-name:hover {
  text-decoration: underline;
  color: var(--primary-color, var(--color-primary));
}

.item-sub {
  display: block;
  font-size: var(--font-size-xs);
  color: var(--text-color-secondary, var(--color-text-muted));
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.now-playing-badge {
  font-size: var(--font-size-xs);
  color: var(--color-success);
  font-weight: 600;
}

.item-playcount,
.item-date {
  font-size: var(--font-size-xs);
  color: var(--text-color-secondary, var(--color-text-muted));
  white-space: nowrap;
  flex-shrink: 0;
}

/* stylelint-disable-next-line color-no-hex -- rojo de Last.fm: color de marca, drift intencional (styles.md) */
.u-brand-lastfm { color: #d51007; }
</style>
