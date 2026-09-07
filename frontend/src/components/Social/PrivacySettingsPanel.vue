<template>
  <div class="privacy-settings-panel">
    <h3 class="privacy-settings-panel__title">
      {{ t('social.privacyTitle') }}
    </h3>

    <p class="privacy-settings-panel__intro">
      {{ t('social.privacyIntro') }}
    </p>

    <div
      v-if="privacySettings"
      class="privacy-settings-panel__form"
    >
      <div
        v-for="ajuste in AJUSTES"
        :key="ajuste.key"
        class="privacy-settings-panel__row privacy-settings-panel__row--toggle"
      >
        <span class="privacy-settings-panel__row-label">
          <span :id="`privacy-${ajuste.key}-label`">{{ ajuste.label }}</span>
          <small
            v-if="ajuste.hint"
            class="privacy-settings-panel__row-hint"
          >{{ ajuste.hint }}</small>
        </span>
        <ToggleSwitch
          v-model="localSettings[ajuste.key]"
          :aria-labelledby="`privacy-${ajuste.key}-label`"
        />
      </div>

      <Button
        :label="t('common.save')"
        icon="pi pi-save"
        :loading="saving"
        @click="save"
      />
    </div>

    <div
      v-else
      class="privacy-settings-panel__loading"
    >
      <i class="pi pi-spin pi-spinner" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import { usePrivacySettings } from '@/composables/usePrivacySettings'
import { useToast } from 'primevue/usetoast'
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

/**
 * Los **siete** ajustes que existen de verdad, con el mismo nombre que tienen en
 * `user_privacy_settings` y en `PrivacySettings::toArray()`.
 *
 * Hasta el 2026-08-25 este panel pintaba otra cosa: tres desplegables de
 * visibilidad y un interruptor de solicitudes de amistad —`profile_visibility`,
 * `library_visibility`, `feed_visibility` y `allow_friend_requests`—, **cuatro
 * campos que no existen ni en el backend ni en la base de datos**. Y no era
 * solo cosmético: `UpdatePrivacySettingsCommand::fromArray` (`:19-29`) lee los
 * seis reales con un `?? true` de respaldo, así que al no llegar ninguno **cada
 * «Guardar» los reseteaba todos a su valor por defecto**.
 */
// `computed` y no una constante: los rótulos salen del catálogo y tienen que
// repintarse al cambiar de idioma sin recargar.
const AJUSTES = computed(() => [
  { key: 'show_additions', label: t('privacy.additions') },
  { key: 'show_status_changes', label: t('privacy.statusChanges') },
  { key: 'show_ratings', label: t('privacy.ratings') },
  {
    key: 'show_notes',
    label: t('privacy.notes'),
    // Los dos interruptores son independientes y se confunden con facilidad:
    // este decide si el evento se VE, y el `is_private` de cada nota decide si
    // llega a emitirse. Una nota privada no genera evento ni con esto encendido.
    hint: t('privacy.notesHint')
  },
  { key: 'show_reading_sessions', label: t('privacy.readingSessions') },
  { key: 'show_achievements', label: t('privacy.achievements') },
  {
    key: 'show_journal',
    label: t('privacy.journal'),
    // El único de los siete que NO es un evento del feed: los otros seis dicen
    // qué se publica, y éste si tu diario se puede leer desde tu perfil. Sin
    // decirlo, se lee como los de arriba —el intro del panel habla del feed— y
    // el diario no se publica en ninguna parte.
    hint: t('privacy.journalHint')
  }
])

const { privacySettings, fetchPrivacySettings, updatePrivacySettings } = usePrivacySettings()
const toast = useToast()
const saving = ref(false)

/** Los defaults del backend, para no pintar interruptores vacíos mientras carga. */
const localSettings = ref({
  show_additions: true,
  show_status_changes: true,
  show_ratings: true,
  show_notes: false,
  show_reading_sessions: true,
  show_achievements: true,
  // Apagado, como `show_notes` y como la columna: el diario nace privado.
  show_journal: false
})

watch(privacySettings, (val) => {
  if (!val) return

  // `??` y no `||`: un `false` guardado es un valor legítimo y con `||` se
  // perdería en cada carga.
  localSettings.value = Object.fromEntries(
    AJUSTES.value.map(({ key }) => [key, val[key] ?? localSettings.value[key]])
  )
}, { immediate: true })

onMounted(() => {
  if (!privacySettings.value) fetchPrivacySettings()
})

const save = async () => {
  saving.value = true
  try {
    await updatePrivacySettings(localSettings.value)
    toast.add({ severity: 'success', summary: 'Guardado', detail: t('toasts.privacySaved'), life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: t('toasts.privacyFailed'), life: 4000 })
  } finally {
    saving.value = false
  }
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/forms' as *;

.privacy-settings-panel {
  &__intro {
    margin: 0 0 var(--spacing-md);
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
  }

  // Hasta aquí `__row` no tenía ninguna regla, así que la fila era un bloque y el
  // interruptor caía DEBAJO de su etiqueta en los seis ajustes.
  &__row {
    @include setting-row;
  }

  &__row-label {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-2xs);
  }

  &__row-hint {
    color: var(--color-text-secondary);
    font-size: var(--font-size-xs);
  }
}
</style>
