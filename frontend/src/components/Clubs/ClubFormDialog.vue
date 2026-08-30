<template>
  <!-- El chasis lo pone `BaseModal`: overlay, trampa de foco, Escape, cabecera
       y pie ordenado, igual que en los cuatro modales propios. -->
  <BaseModal
    v-model="visible"
    title="Nuevo club"
    class="club-form-dialog"
  >
    <div class="club-form-dialog__body">
      <div class="club-form-dialog__field">
        <label for="club-name">Nombre</label>
        <InputText
          id="club-name"
          v-model="name"
          maxlength="120"
          placeholder="Los del jueves"
        />
      </div>

      <div class="club-form-dialog__field">
        <label for="club-description">Descripción (opcional)</label>
        <Textarea
          id="club-description"
          v-model="description"
          rows="2"
          maxlength="500"
        />
      </div>

      <!-- Se dice ANTES de crear, no después: entrar en un club es el
           consentimiento, y quien lo monta tiene que saber qué está pidiendo a
           los que invite. No hay interruptor en Privacidad que lo gobierne. -->
      <p class="club-form-dialog__notice">
        <i class="pi pi-info-circle" />
        Quien entre en el club verá el progreso de los demás sobre el ítem
        activo, y ellos el suyo. Se deja de compartir saliendo del club.
      </p>
    </div>

    <template #footer>
      <button
        type="button"
        class="btn btn--ghost"
        @click="visible = false"
      >
        Cancelar
      </button>
      <button
        type="button"
        class="btn btn--primary"
        :disabled="!canSubmit"
        @click="submit"
      >
        Crear
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'

const props = defineProps({
  modelValue: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'submit'])

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// El diálogo se monta con `v-if`, así que abrirlo otra vez construye el
// componente de nuevo y los campos vuelven a nacer vacíos.
const name = ref('')
const description = ref('')

const canSubmit = computed(() => name.value.trim().length > 0)

const submit = () => {
  if (!canSubmit.value) return

  emit('submit', {
    name: name.value.trim(),
    description: description.value.trim()
  })
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.club-form-dialog {
  &__body {
    display: flex;
    flex-direction: column;
    gap: spacing(md);
    min-width: min(420px, 80vw);
  }

  &__field {
    display: flex;
    flex-direction: column;
    gap: spacing(3xs);

    label {
      font-size: 0.875rem;
      color: var(--color-text-secondary);
    }
  }

  &__notice {
    display: flex;
    align-items: flex-start;
    gap: spacing(2xs);
    padding: spacing(sm);
    border-radius: radius(sm);
    background: var(--color-background-mute);
    font-size: 0.8125rem;
    color: var(--color-text-secondary);

    i { color: var(--color-primary); }
  }

}
</style>
