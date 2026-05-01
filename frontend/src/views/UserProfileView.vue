<template>
    <div class="profile-container">
      <h1 class="profile-title">
        <i class="fas fa-user-cog"></i>
        Mi Perfil
      </h1>

      <!-- User info card -->
      <div class="profile-card">
        <div class="profile-avatar">
          <img
            v-if="userPicture"
            :src="userPicture"
            :alt="userName"
            class="avatar-image"
          />
          <div v-else class="avatar-placeholder">
            <i class="fas fa-user"></i>
          </div>
        </div>

        <div class="profile-info">
          <h2 class="profile-name">{{ userName }}</h2>
          <p class="profile-email">
            <i class="fas fa-envelope"></i>
            {{ userEmail }}
          </p>
          <p class="profile-auth-note">
            <i class="fab fa-google"></i>
            Cuenta vinculada con Google
          </p>
        </div>
      </div>

      <!-- Settings sections -->
      <div class="settings-section">
        <h3 class="section-title">
          <i class="fas fa-lastfm" style="color: #d51007;"></i>
          Last.fm
        </h3>
        <p class="section-description">
          Vincula tu cuenta de Last.fm para ver estadísticas de escucha: tus álbumes, artistas y canciones más escuchados.
        </p>

        <div class="form-group">
          <label class="form-label" for="lastfm-input">Nombre de usuario en Last.fm</label>
          <div class="input-row">
            <input
              id="lastfm-input"
              v-model="lastfmUsername"
              type="text"
              class="form-input"
              placeholder="Tu usuario de Last.fm"
              :disabled="isSaving"
              @keyup.enter="saveLastFmUsername"
            />
            <button
              class="btn-save"
              :disabled="isSaving || !lastfmUsernameChanged"
              @click="saveLastFmUsername"
            >
              <i v-if="isSaving" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-save"></i>
              {{ isSaving ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>

          <p v-if="saveSuccess" class="feedback-success">
            <i class="fas fa-check-circle"></i>
            Nombre de usuario guardado correctamente.
          </p>
          <p v-if="saveError" class="feedback-error">
            <i class="fas fa-exclamation-circle"></i>
            {{ saveError }}
          </p>

          <p class="form-hint">
            Puedes encontrar tu nombre de usuario en
            <a href="https://www.last.fm" target="_blank" rel="noopener noreferrer">last.fm</a>.
            Déjalo en blanco para desvincular.
          </p>
        </div>
      </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/store/auth'
import { storeToRefs } from 'pinia'

export default {
  name: 'UserProfileView',

  setup() {
    const authStore = useAuthStore()
    const { userName, userEmail, userPicture, userLastFmUsername } = storeToRefs(authStore)

    const lastfmUsername = ref('')
    const originalLastfmUsername = ref('')
    const isSaving = ref(false)
    const saveSuccess = ref(false)
    const saveError = ref(null)

    const lastfmUsernameChanged = computed(
      () => lastfmUsername.value !== originalLastfmUsername.value
    )

    onMounted(() => {
      lastfmUsername.value = userLastFmUsername.value || ''
      originalLastfmUsername.value = lastfmUsername.value
    })

    async function saveLastFmUsername() {
      if (!lastfmUsernameChanged.value || isSaving.value) return

      isSaving.value = true
      saveSuccess.value = false
      saveError.value = null

      try {
        await authStore.updateProfile({
          lastfm_username: lastfmUsername.value.trim()
        })
        originalLastfmUsername.value = lastfmUsername.value.trim()
        lastfmUsername.value = originalLastfmUsername.value
        saveSuccess.value = true
        setTimeout(() => { saveSuccess.value = false }, 3000)
      } catch (err) {
        saveError.value = err?.response?.data?.message || err.message || 'Error al guardar'
      } finally {
        isSaving.value = false
      }
    }

    return {
      userName,
      userEmail,
      userPicture,
      lastfmUsername,
      lastfmUsernameChanged,
      isSaving,
      saveSuccess,
      saveError,
      saveLastFmUsername
    }
  }
}
</script>

<style scoped lang="scss">

.profile-container {
  max-width: 700px;
  margin: 0 auto;
}

.profile-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--text-color, #e0e0e0);
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

/* ─── Profile card ─── */
.profile-card {
  background: var(--surface-card, #2a2d36);
  border-radius: 12px;
  padding: 1.75rem;
  display: flex;
  align-items: center;
  gap: 1.5rem;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
  margin-bottom: 1.5rem;
}

.profile-avatar {
  flex-shrink: 0;
}

.avatar-image {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--primary-color, #1D4E4A);
}

.avatar-placeholder {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--primary-color, #1D4E4A);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 2rem;
}

.profile-info {
  flex: 1;
}

.profile-name {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 0 0 0.4rem;
  color: var(--text-color, #e0e0e0);
}

.profile-email,
.profile-auth-note {
  font-size: 0.9rem;
  color: var(--text-color-secondary, #9ca3af);
  margin: 0.25rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* ─── Settings sections ─── */
.settings-section {
  background: var(--surface-card, #2a2d36);
  border-radius: 12px;
  padding: 1.75rem;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
  margin-bottom: 1.5rem;
}

.section-title {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0 0 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--text-color, #e0e0e0);
}

.section-description {
  font-size: 0.9rem;
  color: var(--text-color-secondary, #9ca3af);
  margin-bottom: 1.25rem;
  line-height: 1.5;
}

/* ─── Form ─── */
.form-group {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.form-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-color, #e0e0e0);
}

.input-row {
  display: flex;
  gap: 0.75rem;
}

.form-input {
  flex: 1;
  padding: 0.6rem 0.85rem;
  background: var(--surface-ground, #1e2127);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  font-size: 0.95rem;
  color: var(--text-color, #e0e0e0);
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input::placeholder {
  color: var(--text-color-secondary, #9ca3af);
}

.form-input:focus {
  border-color: var(--primary-color, #1D4E4A);
  box-shadow: 0 0 0 3px rgba(29, 78, 74, 0.25);
}

.form-input:disabled {
  background: rgba(255, 255, 255, 0.04);
  color: var(--text-color-secondary, #9ca3af);
  cursor: not-allowed;
}

.btn-save {
  padding: 0.6rem 1.2rem;
  background: var(--primary-color, #1D4E4A);
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: opacity 0.2s;
  white-space: nowrap;
}

.btn-save:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-save:not(:disabled):hover {
  opacity: 0.85;
}

.form-hint {
  font-size: 0.82rem;
  color: var(--text-color-secondary, #9ca3af);
  margin-top: 0.25rem;
}

.form-hint a {
  color: var(--primary-color, #1D4E4A);
  text-decoration: none;
}

.form-hint a:hover {
  text-decoration: underline;
}

.feedback-success {
  color: #4ade80;
  font-size: 0.88rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.feedback-error {
  color: #f87171;
  font-size: 0.88rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

@media (max-width: 600px) {

  .profile-card {
    flex-direction: column;
    text-align: center;
  }

  .profile-email,
  .profile-auth-note {
    justify-content: center;
  }

  .input-row {
    flex-direction: column;
  }

  .btn-save {
    justify-content: center;
  }
}
</style>
