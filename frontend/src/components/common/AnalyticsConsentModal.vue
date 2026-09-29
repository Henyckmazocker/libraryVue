<template>
  <!-- Pregunta UNA decisión: si se puede medir el uso de la app. Plan «Consentimiento de
       Analítica», M3. Cerrar (X, Escape, overlay) no guarda nada: cuenta como «no» mientras
       dure esta carga de la app y vuelve a salir al volver a entrar. -->
  <BaseModal
    :model-value="isOpen"
    :title="t('analytics.consent.modalTitle')"
    icon="fas fa-chart-line"
    size="sm"
    class="analytics-consent-modal"
    @close="dismiss"
  >
    <p class="analytics-consent-modal__text">
      {{ t('analytics.consent.modalBody') }}
    </p>
    <p class="analytics-consent-modal__hint">
      {{ t('analytics.consent.modalHint') }}
    </p>
    <router-link
      to="/privacy"
      class="analytics-consent-modal__link"
    >
      {{ t('analytics.consent.moreInfo') }}
    </router-link>

    <template #footer>
      <button
        type="button"
        class="btn btn--ghost analytics-consent-modal__decline"
        :disabled="saving"
        @click="decide(false)"
      >
        {{ t('analytics.consent.decline') }}
      </button>
      <button
        type="button"
        class="btn btn--primary analytics-consent-modal__accept"
        :disabled="saving"
        @click="decide(true)"
      >
        {{ t('analytics.consent.accept') }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import BaseModal from '@/components/common/BaseModal.vue'
import { useAuthStore } from '@/store/auth'
import { useUIStore } from '@/store/ui'
import { isAnalyticsAvailable } from '@/analytics'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()
const auth = useAuthStore()
const uiStore = useUIStore()
const route = useRoute()

// «Cerrado» vive en memoria y no en localStorage: tiene que volver a preguntar en la siguiente
// entrada (recarga o nuevo login). Se olvida al cambiar de usuario, para que un logout/login en
// la misma pestaña vuelva a preguntar.
const dismissed = ref(false)
const saving = ref(false)

watch(() => auth.user?.id ?? null, () => { dismissed.value = false })

// `=== null` estricto: `undefined` es un backend sin migrar (el campo no llega) y ahí no se
// pregunta, porque la action daría 500. Solo `auth.user` existente: mientras `initializeAuth`
// resuelve no hay usuario y el modal no parpadea.
const isOpen = computed(() =>
  !!auth.user &&
  auth.user.analytics_consent === null &&
  isAnalyticsAvailable() &&
  !dismissed.value &&
  route.path !== '/privacy'
)

const dismiss = () => { dismissed.value = true }

const decide = async (consent) => {
  saving.value = true
  // Si no se pudo guardar, se cierra igual por esta sesión: insistir con el mismo modal no
  // arregla el backend. Queda en NULL y volverá a preguntar.
  const failed = () => {
    dismissed.value = true
    uiStore.showError(t('analytics.consent.saveFailed'))
  }
  try {
    const response = await auth.updateAnalyticsConsent(consent)
    if (response?.data?.status !== 'success') failed()
  } catch {
    failed()
  } finally {
    saving.value = false
  }
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.analytics-consent-modal__text {
  margin: 0 0 spacing(sm);
  color: var(--color-text);
  line-height: 1.5;
}

.analytics-consent-modal__hint {
  margin: 0 0 spacing(md);
  color: var(--color-text-secondary);
  font-size: var(--font-size-sm);
  line-height: 1.5;
}

.analytics-consent-modal__link {
  color: var(--color-link);
  font-size: var(--font-size-sm);
}
</style>
