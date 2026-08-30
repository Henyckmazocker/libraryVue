<template>
  <!-- El chasis lo pone `BaseModal`: overlay, trampa de foco, Escape, cabecera
       y pie ordenado, igual que en los cuatro modales propios. -->
  <BaseModal
    v-model="visible"
    title="Invitar a colaborar"
    class="invite-collaborator-dialog"
  >
    <div class="invite-collaborator-dialog__body">
      <p class="invite-collaborator-dialog__hint">
        Quien acepte podrá añadir y quitar ítems. Renombrar la lista o borrarla
        sigue siendo cosa tuya.
      </p>

      <div
        v-if="isLoadingFriends"
        class="invite-collaborator-dialog__state"
      >
        <i class="pi pi-spin pi-spinner" />
      </div>

      <p
        v-else-if="invitables.length === 0"
        class="invite-collaborator-dialog__state"
      >
        No tienes amigos a quien invitar que no colaboren ya.
      </p>

      <fieldset
        v-else
        class="invite-collaborator-dialog__friends"
      >
        <legend class="invite-collaborator-dialog__legend">
          Elige a quién
        </legend>
        <!-- Botones con `aria-pressed`, no `<div @click>`: las 20 reglas de
             accesibilidad están en `error` y esto es un selector de verdad. -->
        <button
          v-for="friend in invitables"
          :key="friend.id"
          type="button"
          class="invite-collaborator-dialog__friend"
          :class="{ 'invite-collaborator-dialog__friend--selected': inviteeId === friend.id }"
          :aria-pressed="inviteeId === friend.id"
          @click="inviteeId = friend.id"
        >
          <img
            v-if="friend.picture"
            :src="friend.picture"
            alt=""
            class="invite-collaborator-dialog__avatar"
            loading="lazy"
            decoding="async"
          >
          <i
            v-else
            class="pi pi-user"
            aria-hidden="true"
          />
          <span>{{ friend.username || friend.name }}</span>
        </button>
      </fieldset>

      <p
        v-if="error"
        class="invite-collaborator-dialog__error"
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
        :disabled="!inviteeId || isSaving"
        @click="submit"
      >
        <i
          v-if="isSaving"
          class="pi pi-spin pi-spinner"
        />
        Invitar
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import BaseModal from '@/components/common/BaseModal.vue'
import { storeToRefs } from 'pinia'
import { useSocialStore } from '@/store/social'
import { useListsStore } from '@/store/lists'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  listId: { type: [String, Number], required: true },
  /** Quién colabora ya, para no ofrecerlo otra vez. */
  collaborators: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:modelValue', 'invited'])

const social = useSocialStore()
const lists = useListsStore()
const { isSaving, error } = storeToRefs(lists)
const notifications = inject('notifications', null)

const isLoadingFriends = ref(true)
const friends = ref([])
const inviteeId = ref(null)

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Quien ya colabora no vuelve a ofrecerse: invitarle daría un 409 que la
// interfaz podía haber evitado.
const invitables = computed(() => {
  const yaEstan = new Set(props.collaborators.map((c) => c.user_id))
  return friends.value.filter((f) => !yaEstan.has(f.id))
})

onMounted(async () => {
  try {
    await social.fetchFriends()
    friends.value = social.friends ?? []
  } finally {
    isLoadingFriends.value = false
  }
})

const submit = async () => {
  const result = await lists.inviteCollaborator(Number(props.listId), inviteeId.value)

  if (!result.success) {
    // El mensaje se queda DENTRO del diálogo: hay que poder leerlo mientras se
    // elige a otra persona.
    return
  }

  visible.value = false
  notifications?.showSuccess?.('Invitación enviada')
  emit('invited')
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.invite-collaborator-dialog {
  &__body {
    display: flex;
    flex-direction: column;
    gap: spacing(md);
    min-width: min(380px, 80vw);
  }

  &__hint {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
  }

  &__state {
    text-align: center;
    padding: spacing(md);
    color: var(--color-text-secondary);
  }

  &__friends {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    border: 0;
    padding: 0;
  }

  &__legend {
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--color-text);
  }

  &__friend {
    @include button-reset;

    display: flex;
    align-items: center;
    gap: spacing(sm);
    width: 100%;
    padding: spacing(sm);
    border-radius: radius(sm);
    border: 1px solid var(--color-border);
    color: var(--color-text);
    text-align: left;

    &--selected {
      border-color: var(--color-primary);
      background: var(--color-background-mute);
    }
  }

  &__avatar {
    width: 28px;
    height: 28px;
    border-radius: radius(full);
    object-fit: cover;
  }

  &__error {
    color: var(--color-error);
  }

}
</style>
