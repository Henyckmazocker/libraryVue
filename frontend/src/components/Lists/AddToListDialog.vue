<template>
  <!-- El chasis lo pone `BaseModal`: overlay, trampa de foco, Escape, cabecera
       y pie ordenado, igual que en los cuatro modales propios. -->
  <BaseModal
    v-model="visible"
    title="Añadir a una lista"
    class="add-to-list-dialog"
  >
    <div class="add-to-list-dialog__body">
      <p class="add-to-list-dialog__item">
        Vas a añadir <strong>{{ entityTitle }}</strong>
      </p>

      <div
        v-if="isLoading"
        class="add-to-list-dialog__state"
      >
        <i class="pi pi-spin pi-spinner" />
      </div>

      <p
        v-else-if="editable.length === 0"
        class="add-to-list-dialog__state"
      >
        No tienes ninguna lista que puedas editar. Crea una desde «Mis listas».
      </p>

      <fieldset
        v-else
        class="add-to-list-dialog__lists"
      >
        <legend class="add-to-list-dialog__legend">
          Elige la lista
        </legend>
        <!-- Botones con `aria-pressed`, no `<div @click>`: las 20 reglas de
             accesibilidad están en `error` y esto es un selector de verdad. -->
        <button
          v-for="list in editable"
          :key="list.id"
          type="button"
          class="add-to-list-dialog__list"
          :class="{ 'add-to-list-dialog__list--selected': listId === list.id }"
          :aria-pressed="listId === list.id"
          @click="listId = list.id"
        >
          <span class="add-to-list-dialog__list-name">{{ list.name }}</span>
          <span class="add-to-list-dialog__list-count">
            {{ list.item_count }} {{ list.item_count === 1 ? 'ítem' : 'ítems' }}
          </span>
        </button>
      </fieldset>

      <p
        v-if="error"
        class="add-to-list-dialog__error"
        role="alert"
      >
        {{ error }}
      </p>
    </div>

    <template #footer>
      <button
        type="button"
        class="btn btn--ghost"
        :disabled="isSaving"
        @click="visible = false"
      >
        Cancelar
      </button>
      <button
        type="button"
        class="btn btn--primary"
        :disabled="!listId || isSaving"
        @click="submit"
      >
        <i
          v-if="isSaving"
          class="pi pi-spin pi-spinner"
        />
        Añadir
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { storeToRefs } from 'pinia'
import { useListsStore } from '@/store/lists'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  /**
   * El medio con el que el BACKEND guarda el ítem, no el del registry: una serie
   * se guarda con `AddMovieUseCase`, así que viaja como `movie`. Quien llama lo
   * resuelve con `coverMedia`.
   */
  entityType: { type: String, required: true },
  entityId: { type: [String, Number], default: null },
  entityTitle: { type: String, default: '' },
  entityCover: { type: String, default: null }
})

const emit = defineEmits(['update:modelValue'])

const lists = useListsStore()
const { lists: allLists, isLoading, isSaving, error } = storeToRefs(lists)
const notifications = inject('notifications', null)

const listId = ref(null)

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Solo las que se pueden editar. Una lista pública de la que no eres dueño se
// ve pero no se toca, y ofrecerla sería prometer un 403.
const editable = computed(() => allLists.value.filter((l) => l.is_owner))

onMounted(() => lists.fetchMyLists())

const submit = async () => {
  const result = await lists.addItem(listId.value, {
    entityType: props.entityType,
    entityId: props.entityId,
    entityTitle: props.entityTitle,
    entityCover: props.entityCover
  })

  if (!result.success) {
    // El 409 del ítem repetido ya viene traducido por código desde el store.
    notifications?.showError?.(result.message || 'No se pudo añadir a la lista')
    return
  }

  visible.value = false
  notifications?.showSuccess?.('Añadido a la lista')
  // El contador de la tarjeta cambió; se relee para no dejarlo desfasado.
  lists.fetchMyLists()
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.add-to-list-dialog {
  &__body {
    display: flex;
    flex-direction: column;
    gap: spacing(md);
    min-width: min(380px, 80vw);
  }

  &__item {
    color: var(--color-text);
  }

  &__state {
    text-align: center;
    padding: spacing(md);
    color: var(--color-text-secondary);
  }

  &__lists {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    border: 0;
    padding: 0;
  }

  &__legend {
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--color-text);
  }

  &__list {
    @include button-reset;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: spacing(sm);
    width: 100%;
    padding: spacing(sm);
    border-radius: radius(sm);
    border: 1px solid var(--color-border);
    text-align: left;

    &--selected {
      border-color: var(--color-primary);
      background: var(--color-background-mute);
    }
  }

  &__list-name {
    font-weight: 600;
    color: var(--color-text);
  }

  &__list-count {
    flex-shrink: 0;
    font-size: var(--font-size-xs);
    color: var(--color-text-secondary);
  }

  &__error {
    color: var(--color-error);
  }

}
</style>
