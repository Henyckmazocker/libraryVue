<template>
  <div class="privacy-settings-panel">
    <h3 class="privacy-settings-panel__title">
      Configuración de privacidad
    </h3>

    <p class="privacy-settings-panel__intro">
      Elige qué actividad tuya ven tus amigos en su feed. Lo que apagues aquí deja de mostrarse,
      aunque siga registrado.
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
        label="Guardar"
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
import { ref, watch, onMounted } from 'vue'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import { usePrivacySettings } from '@/composables/usePrivacySettings'
import { useToast } from 'primevue/usetoast'

/**
 * Los **seis** ajustes que existen de verdad, con el mismo nombre que tienen en
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
const AJUSTES = [
  { key: 'show_additions', label: 'Cuando añado algo a mi biblioteca' },
  { key: 'show_status_changes', label: 'Cuando cambio el estado de algo' },
  { key: 'show_ratings', label: 'Cuando valoro algo' },
  {
    key: 'show_notes',
    label: 'Cuando escribo una nota',
    // Los dos interruptores son independientes y se confunden con facilidad:
    // este decide si el evento se VE, y el `is_private` de cada nota decide si
    // llega a emitirse. Una nota privada no genera evento ni con esto encendido.
    hint: 'Solo las notas que marques como públicas. Las privadas no se publican nunca.'
  },
  { key: 'show_reading_sessions', label: 'Cuando registro una sesión de lectura' },
  { key: 'show_achievements', label: 'Cuando consigo un logro' }
]

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
  show_achievements: true
})

watch(privacySettings, (val) => {
  if (!val) return

  // `??` y no `||`: un `false` guardado es un valor legítimo y con `||` se
  // perdería en cada carga.
  localSettings.value = Object.fromEntries(
    AJUSTES.map(({ key }) => [key, val[key] ?? localSettings.value[key]])
  )
}, { immediate: true })

onMounted(() => {
  if (!privacySettings.value) fetchPrivacySettings()
})

const save = async () => {
  saving.value = true
  try {
    await updatePrivacySettings(localSettings.value)
    toast.add({ severity: 'success', summary: 'Guardado', detail: 'Configuración actualizada', life: 3000 })
  } catch {
    toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo guardar la configuración', life: 4000 })
  } finally {
    saving.value = false
  }
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.privacy-settings-panel {
  &__intro {
    margin: 0 0 var(--spacing-md);
    color: var(--color-text-secondary);
    font-size: var(--font-size-sm);
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
