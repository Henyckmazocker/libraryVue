<template>
  <div class="profile-container">
    <h1 class="profile-title">
      <i class="fas fa-user-cog" />
      {{ t('profile.title') }}
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
          {{ t('profile.googleLinked') }}
        </p>
      </div>
    </div>

    <!-- El diario. Aquí y en el sidebar: la barra de navegación móvil se queda
         con sus cinco pestañas, que a 360 px ya iban justas. -->
    <RouterLink
      class="profile-journal-link"
      :to="{ name: 'Journal' }"
    >
      <i class="fas fa-book-open" />
      <span class="profile-journal-link__text">
        <strong>{{ t('journal.title') }}</strong>
        <small>{{ t('journal.subtitle') }}</small>
      </span>
      <i class="fas fa-chevron-right" />
    </RouterLink>

    <!-- Settings sections -->
    <div class="settings-section">
      <h3 class="section-title">
        <i
          class="fas fa-lastfm u-brand-lastfm"
        />
        {{ t('profile.lastfm') }}
      </h3>
      <p class="section-description">
        {{ t('profile.lastfmDescription') }}
      </p>

      <div class="form-group">
        <label
          class="form-label"
          for="lastfm-input"
        >{{ t('profile.lastfmUsername') }}</label>
        <div class="input-row">
          <input
            id="lastfm-input"
            v-model="lastfmUsername"
            type="text"
            class="form-input"
            :placeholder="t('profile.lastfmPlaceholder')"
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
            {{ isSaving ? t('common.saving') : t('common.save') }}
          </button>
        </div>

        <p
          v-if="saveSuccess"
          class="feedback-success"
        >
          <i class="fas fa-check-circle" />
          {{ t('profile.saved') }}
        </p>
        <p
          v-if="saveError"
          class="feedback-error"
        >
          <i class="fas fa-exclamation-circle" />
          {{ saveError }}
        </p>

        <p class="form-hint">
          {{ t('profile.hintBefore') }}<a
            href="https://www.last.fm"
            target="_blank"
            rel="noopener noreferrer"
          >{{ t('profile.hintLink') }}</a>{{ t('profile.hintAfter') }}
        </p>
      </div>
    </div>

    <!-- El idioma se elige aquí y se recuerda en el navegador, no en la cuenta:
         es una preferencia del dispositivo, como el tema. El `<select>` nativo y
         no un desplegable de PrimeVue porque son dos opciones y el nativo ya trae
         teclado, lector de pantalla y el widget del sistema en móvil. -->
    <div class="settings-section">
      <h3 class="section-title">
        <i class="fas fa-language" />
        {{ t('profile.language.title') }}
      </h3>
      <p class="section-description">
        {{ t('profile.language.hint') }}
      </p>

      <div class="form-group">
        <label
          class="form-label"
          for="locale-select"
        >{{ t('profile.language.label') }}</label>
        <select
          id="locale-select"
          class="form-input"
          :value="locale"
          @change="setLocale($event.target.value)"
        >
          <option
            v-for="l in availableLocales"
            :key="l.code"
            :value="l.code"
          >
            {{ l.native }}
          </option>
        </select>
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
import { useI18n } from '@/composables/useI18n'

export default {
  name: 'UserProfileView',

  components: { PrivacySettingsPanel },

  setup() {
    const authStore = useAuthStore()
    const { t, locale, setLocale, availableLocales } = useI18n()
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
        saveError.value = err?.response?.data?.message || err.message || t('toasts.saveFailed')
      } finally {
        isSaving.value = false
      }
    }

    return {
      t,
      locale,
      setLocale,
      availableLocales,
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

.profile-journal-link {
  display: flex;
  align-items: center;
  gap: spacing(sm);
  padding: spacing(md);
  margin-bottom: spacing(lg);
  border: 1px solid var(--color-border);
  border-radius: radius(lg);
  background: var(--color-background-soft);
  color: var(--color-text);
  text-decoration: none;
  transition: var(--transition-fast);

  &:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
  }

  &__text {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    min-width: 0;

    small {
      color: var(--color-text-light);
    }
  }
}


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
