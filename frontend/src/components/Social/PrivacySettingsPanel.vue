<template>
  <div class="privacy-settings-panel">
    <h3 class="privacy-settings-panel__title">
      Configuración de privacidad
    </h3>

    <div
      v-if="privacySettings"
      class="privacy-settings-panel__form"
    >
      <div class="privacy-settings-panel__row">
        <!-- El <Select> de PrimeVue no es un control nativo al que un <label for>
             pueda apuntar: se etiqueta por aria-labelledby. -->
        <span
          :id="'privacy-profile-label'"
          class="privacy-settings-panel__row-label"
        >Visibilidad del perfil</span>
        <Select
          v-model="localSettings.profileVisibility"
          :aria-labelledby="'privacy-profile-label'"
          :options="visibilityOptions"
          option-label="label"
          option-value="value"
        />
      </div>

      <div class="privacy-settings-panel__row">
        <!-- El <Select> de PrimeVue no es un control nativo al que un <label for>
             pueda apuntar: se etiqueta por aria-labelledby. -->
        <span
          :id="'privacy-library-label'"
          class="privacy-settings-panel__row-label"
        >Visibilidad de la biblioteca</span>
        <Select
          v-model="localSettings.libraryVisibility"
          :aria-labelledby="'privacy-library-label'"
          :options="visibilityOptions"
          option-label="label"
          option-value="value"
        />
      </div>

      <div class="privacy-settings-panel__row">
        <!-- El <Select> de PrimeVue no es un control nativo al que un <label for>
             pueda apuntar: se etiqueta por aria-labelledby. -->
        <span
          :id="'privacy-feed-label'"
          class="privacy-settings-panel__row-label"
        >Visibilidad del feed</span>
        <Select
          v-model="localSettings.feedVisibility"
          :aria-labelledby="'privacy-feed-label'"
          :options="visibilityOptions"
          option-label="label"
          option-value="value"
        />
      </div>

      <div class="privacy-settings-panel__row privacy-settings-panel__row--toggle">
        <span
          id="privacy-requests-label"
          class="privacy-settings-panel__row-label"
        >Permitir solicitudes de amistad</span>
        <ToggleSwitch
          v-model="localSettings.allowFriendRequests"
          aria-labelledby="privacy-requests-label"
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
import Select from 'primevue/select'
import ToggleSwitch from 'primevue/toggleswitch'
import Button from 'primevue/button'
import { usePrivacySettings } from '@/composables/usePrivacySettings'
import { useToast } from 'primevue/usetoast'

const { privacySettings, fetchPrivacySettings, updatePrivacySettings } = usePrivacySettings()
const toast = useToast()
const saving = ref(false)

const visibilityOptions = [
  { label: 'Público', value: 'public' },
  { label: 'Solo amigos', value: 'friends_only' },
  { label: 'Privado', value: 'private' }
]

const localSettings = ref({
  profileVisibility: 'public',
  libraryVisibility: 'public',
  feedVisibility: 'friends_only',
  allowFriendRequests: true
})

watch(privacySettings, (val) => {
  if (val) {
    localSettings.value = {
      profileVisibility: val.profile_visibility ?? 'public',
      libraryVisibility: val.library_visibility ?? 'public',
      feedVisibility: val.feed_visibility ?? 'friends_only',
      allowFriendRequests: val.allow_friend_requests ?? true
    }
  }
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
  &__title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: spacing(lg);
    color: var(--color-text);
  }

  &__form {
    display: flex;
    flex-direction: column;
    gap: spacing(md);
  }

  &__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: spacing(md);

    // Las etiquetas son <span> porque los controles de PrimeVue no aceptan
    // un <label for>: se asocian por aria-labelledby.
    label,
    &-label {
      font-size: 0.9rem;
      color: var(--color-text);
    }

    &--toggle {
      padding: spacing(sm) 0;
      border-bottom: 1px solid var(--color-border);
    }
  }

  &__loading {
    text-align: center;
    padding: spacing(2xl);
    color: var(--color-text-secondary);
  }
}
</style>
