<template>
  <!-- El chasis lo pone `BaseModal`: overlay, trampa de foco, Escape, cabecera
       y pie ordenado, igual que en los cuatro modales propios. -->
  <BaseModal
    v-model="visible"
    :title="isEdit ? 'Editar lista' : 'Nueva lista'"
    class="list-form-dialog"
  >
    <div class="list-form-dialog__body">
      <div class="list-form-dialog__field">
        <label for="list-name">Nombre</label>
        <InputText
          id="list-name"
          v-model="name"
          maxlength="120"
          placeholder="Para el verano"
        />
      </div>

      <div class="list-form-dialog__field">
        <label for="list-description">Descripción (opcional)</label>
        <Textarea
          id="list-description"
          v-model="description"
          rows="2"
          maxlength="500"
        />
      </div>

      <fieldset class="list-form-dialog__field">
        <legend>Quién la ve</legend>
        <!-- Botones con `aria-pressed`, no `<div @click>`: las 20 reglas de
             accesibilidad están en `error` y esto es un selector de verdad. -->
        <button
          v-for="option in VISIBILITY_OPTIONS"
          :key="option.value"
          type="button"
          class="list-form-dialog__option"
          :class="{ 'list-form-dialog__option--selected': visibility === option.value }"
          :aria-pressed="visibility === option.value"
          @click="visibility = option.value"
        >
          <span class="list-form-dialog__option-label">
            <i :class="option.icon" />
            {{ option.label }}
          </span>
          <!-- El matiz que se lee mal: `collaborative` NO es pública. -->
          <span class="list-form-dialog__option-hint">{{ option.hint }}</span>
        </button>
      </fieldset>

      <!-- Se avisa ANTES de guardar, no después: bajar de colaborativa borra
           las filas de `media_list_collaborator` y eso no se deshace. -->
      <p
        v-if="losesCollaborators"
        class="list-form-dialog__warning"
        role="alert"
      >
        <i class="pi pi-exclamation-triangle" />
        Al dejar de ser colaborativa, quienes colaboran perderán el acceso.
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
        {{ isEdit ? 'Guardar' : 'Crear' }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, ref } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import { VISIBILITY_OPTIONS } from './visibility'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  /** Con lista, el diálogo edita; sin ella, crea. */
  list: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'submit'])

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const isEdit = computed(() => Boolean(props.list))

// Se siembran una vez, al montar. El diálogo se monta con `v-if`, así que abrir
// otra vez construye el componente de nuevo y los valores vuelven a leerse.
const name = ref(props.list?.name ?? '')
const description = ref(props.list?.description ?? '')
const visibility = ref(props.list?.visibility ?? 'private')

const canSubmit = computed(() => name.value.trim().length > 0)

const losesCollaborators = computed(
  () => props.list?.visibility === 'collaborative' && visibility.value !== 'collaborative'
)

const submit = () => {
  if (!canSubmit.value) return

  emit('submit', {
    name: name.value.trim(),
    // Cadena vacía y no `null`: el backend distingue «no lo toques» (ausente) de
    // «bórrala» (presente y vacía), y desde aquí el campo siempre se manda.
    description: description.value.trim(),
    visibility: visibility.value
  })
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.list-form-dialog {
  &__body {
    display: flex;
    flex-direction: column;
    gap: spacing(md);
    min-width: min(420px, 80vw);
  }

  &__field {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    border: 0;
    padding: 0;

    label,
    legend {
      font-size: var(--font-size-sm);
      font-weight: 600;
      color: var(--color-text);
    }
  }

  &__option {
    @include button-reset;

    display: flex;
    flex-direction: column;
    gap: spacing(3xs);
    width: 100%;
    padding: spacing(sm);
    margin-bottom: spacing(2xs);
    border-radius: radius(sm);
    border: 1px solid var(--color-border);
    text-align: left;

    &--selected {
      border-color: var(--color-primary);
      background: var(--color-background-mute);
    }
  }

  &__option-label {
    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    font-weight: 600;
    color: var(--color-text);
  }

  &__option-hint {
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
  }

  &__warning {
    display: flex;
    align-items: center;
    gap: spacing(2xs);
    font-size: var(--font-size-sm);
    color: var(--color-warning);
  }

}
</style>
