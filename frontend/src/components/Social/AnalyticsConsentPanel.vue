<template>
  <!-- El interruptor de analítica de `/profile` (Plan «Consentimiento de Analítica», M3). Un solo
       campo, así que guarda al cambiar, sin botón. Sin clave de Augur en esta build no se pinta:
       preguntar por algo que no se va a usar es peor que no preguntar. -->
  <div
    v-if="visible"
    class="analytics-consent-panel"
  >
    <h3 class="analytics-consent-panel__title">
      {{ t('analytics.consent.panelTitle') }}
    </h3>

    <div class="analytics-consent-panel__row">
      <span class="analytics-consent-panel__label">
        <span id="analytics-consent-label">{{ t('analytics.consent.panelLabel') }}</span>
        <small class="analytics-consent-panel__hint">{{ t('analytics.consent.panelHint') }}</small>
      </span>
      <ToggleSwitch
        v-model="local"
        :disabled="saving"
        aria-labelledby="analytics-consent-label"
        @update:model-value="onChange"
      />
    </div>

    <router-link
      to="/privacy"
      class="analytics-consent-panel__link"
    >
      {{ t('analytics.consent.moreInfo') }}
    </router-link>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import ToggleSwitch from 'primevue/toggleswitch'
import { useAuthStore } from '@/store/auth'
import { useUIStore } from '@/store/ui'
import { isAnalyticsAvailable } from '@/analytics'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()
const auth = useAuthStore()
const uiStore = useUIStore()
const saving = ref(false)

// Oculto también si el campo no llega (`undefined`: backend sin migrar), porque la action
// daría 500. `null` (sin decidir) sí se pinta, apagado.
const visible = computed(() =>
  isAnalyticsAvailable() && !!auth.user && auth.user.analytics_consent !== undefined
)

// Solo `1` es sí, igual que en `syncAnalytics`: null (sin decidir) sale apagado.
const enabled = computed(() => auth.user?.analytics_consent === 1)

// Copia local para el `v-model`: el ToggleSwitch de PrimeVue guarda su propio valor al pulsarlo,
// así que si el guardado falla hay que devolverle el del store a mano, o se quedaría movido.
const local = ref(enabled.value)
watch(enabled, (v) => { local.value = v })

const failed = () => {
  local.value = enabled.value
  uiStore.showError(t('analytics.consent.saveFailed'))
}

const onChange = async (value) => {
  saving.value = true
  try {
    const response = await auth.updateAnalyticsConsent(value === true)
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
@use '@/assets/styles/components/forms' as *;

.analytics-consent-panel {
  &__title {
    margin: 0 0 spacing(xs);
  }

  &__row {
    @include setting-row;
  }

  &__label {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
  }

  &__hint {
    color: var(--color-text-secondary);
    font-size: var(--font-size-xs);
  }

  &__link {
    display: inline-block;
    margin-top: spacing(xs);
    color: var(--color-link);
    font-size: var(--font-size-sm);
  }
}
</style>
