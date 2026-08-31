<template>
  <div class="profile-container">
    <h1 class="profile-title">
      <i class="fas fa-user-cog" />
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
          loading="lazy"
          decoding="async"
        >
        <div
          v-else
          class="avatar-placeholder"
        >
          <i class="fas fa-user" />
        </div>
      </div>

      <div class="profile-info">
        <h2 class="profile-name">
          {{ userName }}
        </h2>
        <p class="profile-email">
          <i class="fas fa-envelope" />
          {{ userEmail }}
        </p>
        <p class="profile-auth-note">
          <i class="fab fa-google" />
          Cuenta vinculada con Google
        </p>
      </div>
    </div>

    <!-- Settings sections -->
    <div class="settings-section">
      <h3 class="section-title">
        <i
          class="fas fa-lastfm u-brand-lastfm"
        />
        Last.fm
      </h3>
      <p class="section-description">
        Vincula tu cuenta de Last.fm para ver estadísticas de escucha: tus álbumes, artistas y canciones más escuchados.
      </p>

      <div class="form-group">
        <label
          class="form-label"
          for="lastfm-input"
        >Nombre de usuario en Last.fm</label>
        <div class="input-row">
          <input
            id="lastfm-input"
            v-model="lastfmUsername"
            type="text"
            class="form-input"
            placeholder="Tu usuario de Last.fm"
            :disabled="isSaving"
            @keyup.enter="saveLastFmUsername"
          >
          <button
            class="btn btn--primary btn-save"
            :disabled="isSaving || !lastfmUsernameChanged"
            @click="saveLastFmUsername"
          >
            <i
              v-if="isSaving"
              class="fas fa-spinner fa-spin"
            />
            <i
              v-else
              class="fas fa-save"
            />
            {{ isSaving ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>

        <p
          v-if="saveSuccess"
          class="feedback-success"
        >
          <i class="fas fa-check-circle" />
          Nombre de usuario guardado correctamente.
        </p>
        <p
          v-if="saveError"
          class="feedback-error"
        >
          <i class="fas fa-exclamation-circle" />
          {{ saveError }}
        </p>

        <p class="form-hint">
          Puedes encontrar tu nombre de usuario en
          <a
            href="https://www.last.fm"
            target="_blank"
            rel="noopener noreferrer"
          >last.fm</a>.
          Déjalo en blanco para desvincular.
        </p>
      </div>
    </div>

    <!-- La privacidad vive aquí y en ningún otro sitio. Estuvo en la quinta
         pestaña de `/friends`, donde nadie la buscaba: son ajustes de tu cuenta,
         no de la pantalla de amigos. NO se duplica — dos sitios para guardar los
         mismos seis ajustes ya dieron un fallo una vez. -->
    <div class="settings-section">
      <PrivacySettingsPanel />
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/store/auth'
import { storeToRefs } from 'pinia'
import PrivacySettingsPanel from '@/components/Social/PrivacySettingsPanel.vue'

export default {
  name: 'UserProfileView',

  components: { PrivacySettingsPanel },

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
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/cards' as *;


.profile-container {
  max-width: 700px;
  margin: 0 auto;
}

.profile-title {
  font-size: var(--font-size-2xl);
  font-weight: 700;
  color: var(--text-color, var(--color-text));
  margin-bottom: spacing(lg);
  display: flex;
  align-items: center;
  gap: spacing(sm);
}

/* ─── Profile card ─── */
.profile-card {
  @include card-section;

  display: flex;
  align-items: center;
  gap: spacing(lg);
  /* El ritmo de `card-section()` va por `& + &`, que no salta entre dos clases
     distintas: la separación hasta la primera sección de ajustes se dice aquí. */
  margin-bottom: spacing(lg);
}

.profile-avatar {
  flex-shrink: 0;
}

.avatar-image {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--primary-color, var(--color-primary));
}

.avatar-placeholder {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--primary-color, var(--color-primary));
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--color-text-light);
  font-size: var(--font-size-2xl);
}

.profile-info {
  flex: 1;
}

.profile-name {
  font-size: var(--font-size-lg);
  font-weight: 600;
  margin: 0 0 spacing(xs);
  color: var(--text-color, var(--color-text));
}

.profile-email,
.profile-auth-note {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin: spacing(2xs) 0;
  display: flex;
  align-items: center;
  gap: spacing(xs);
}

/* ─── Settings sections ─── */
.settings-section {
  @include card-section;
}

.section-title {
  font-size: var(--font-size-md);
  font-weight: 600;
  margin: 0 0 spacing(xs);
  display: flex;
  align-items: center;
  gap: spacing(xs);
  color: var(--text-color, var(--color-text));
}

.section-description {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin-bottom: spacing(md);
  line-height: 1.5;
}

/* ─── Form ─── */
.form-group {
  display: flex;
  flex-direction: column;
  gap: spacing(xs);
}

.form-label {
  font-size: var(--font-size-sm);
  font-weight: 600;
  color: var(--text-color, var(--color-text));
}

.input-row {
  display: flex;
  gap: spacing(sm);
}

.form-input {
  flex: 1;
  padding: spacing(sm) spacing(sm);
  background: var(--surface-ground, var(--color-background-soft));
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: radius(md);
  font-size: var(--font-size-base);
  color: var(--text-color, var(--color-text));
  outline: none;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-input::placeholder {
  color: var(--color-text-muted);
}

.form-input:focus {
  border-color: var(--primary-color, var(--color-primary));
  box-shadow: 0 0 0 3px rgba(29, 78, 74, 0.25);
}

.form-input:disabled {
  background: rgba(255, 255, 255, 0.04);
  color: var(--color-text-muted);
  cursor: not-allowed;
}

.form-hint {
  font-size: var(--font-size-sm);
  color: var(--color-text-muted);
  margin-top: spacing(2xs);
}


.form-hint a:hover {
  text-decoration: underline;
}

.feedback-success {
  color: var(--color-card-game-accent);
  font-size: var(--font-size-sm);
  display: flex;
  align-items: center;
  gap: spacing(xs);
}

.feedback-error {
  color: var(--color-error);
  font-size: var(--font-size-sm);
  display: flex;
  align-items: center;
  gap: spacing(xs);
}

@include responsive-below(sm) {

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

/* stylelint-disable-next-line color-no-hex -- rojo de Last.fm: color de marca, drift intencional (styles.md) */
.u-brand-lastfm { color: #d51007; }
</style>
