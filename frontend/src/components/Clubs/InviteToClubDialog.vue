<template>
  <!-- El `<Dialog>` de PrimeVue trae su propio atrapador de foco: no se envuelve
       en `useFocusTrap`, que es para los cuatro modales propios del proyecto. -->
  <Dialog
    v-model:visible="visible"
    header="Invitar al club"
    :modal="true"
    :dismissable-mask="true"
    class="invite-to-club-dialog"
    :pt="{ mask: { style: 'z-index: 2500' } }"
  >
    <div class="invite-to-club-dialog__body">
      <!-- Se dice ANTES de invitar: entrar en el club ES el consentimiento de
           que sus miembros vean tu progreso, y no hay interruptor en Privacidad
           que lo gobierne. Quien invita tiene que saber qué está pidiendo. -->
      <p class="invite-to-club-dialog__hint">
        Quien acepte verá el progreso de los demás sobre el ítem activo, y ellos
        el suyo. Se deja de compartir saliendo del club.
      </p>

      <div
        v-if="isLoadingFriends"
        class="invite-to-club-dialog__state"
      >
        <i class="pi pi-spin pi-spinner" />
      </div>

      <p
        v-else-if="invitables.length === 0"
        class="invite-to-club-dialog__state"
      >
        No tienes amigos a quien invitar que no estén ya en el club.
      </p>

      <fieldset
        v-else
        class="invite-to-club-dialog__friends"
      >
        <legend class="invite-to-club-dialog__legend">
          Elige a quién
        </legend>
        <!-- Botones con `aria-pressed`, no `<div @click>`: las 20 reglas de
             accesibilidad están en `error` y esto es un selector de verdad. -->
        <button
          v-for="friend in invitables"
          :key="friend.id"
          type="button"
          class="invite-to-club-dialog__friend"
          :class="{ 'invite-to-club-dialog__friend--selected': inviteeId === friend.id }"
          :aria-pressed="inviteeId === friend.id"
          @click="inviteeId = friend.id"
        >
          <img
            v-if="friend.picture"
            :src="friend.picture"
            alt=""
            class="invite-to-club-dialog__avatar"
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
        class="invite-to-club-dialog__error"
        role="alert"
      >
        {{ error }}
      </p>
    </div>

    <template #footer>
      <button
        type="button"
        class="invite-to-club-dialog__action"
        :disabled="isSaving"
        @click="visible = false"
      >
        Cancelar
      </button>
      <button
        type="button"
        class="invite-to-club-dialog__action invite-to-club-dialog__action--primary"
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
  </Dialog>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import Dialog from 'primevue/dialog'
import { storeToRefs } from 'pinia'
import { useSocialStore } from '@/store/social'
import { useClubsStore } from '@/store/clubs'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  clubId: { type: [String, Number], required: true },
  /** Quién está ya dentro, para no ofrecerlo otra vez. */
  members: { type: Array, default: () => [] }
})

const emit = defineEmits(['update:modelValue', 'invited'])

const social = useSocialStore()
const clubs = useClubsStore()
const { isSaving, error } = storeToRefs(clubs)
const notifications = inject('notifications', null)

const isLoadingFriends = ref(true)
const friends = ref([])
const inviteeId = ref(null)

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

// Quien ya es miembro no vuelve a ofrecerse: invitarle daría un error que la
// interfaz podía haber evitado.
const invitables = computed(() => {
  const yaEstan = new Set(props.members.map((m) => m.user_id))
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
  const result = await clubs.inviteToClub(Number(props.clubId), inviteeId.value)

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

.invite-to-club-dialog {
  &__body {
    display: flex;
    flex-direction: column;
    gap: spacing(md);
    min-width: min(380px, 80vw);
  }

  &__hint {
    padding: spacing(sm);
    border-radius: radius(sm);
    background: var(--color-background-mute);
    font-size: 0.8125rem;
    color: var(--color-text-secondary);
  }

  &__state {
    text-align: center;
    padding: spacing(md);
    color: var(--color-text-secondary);
    font-size: 0.875rem;
  }

  &__friends {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
    border: 0;
    padding: 0;
    margin: 0;
  }

  &__legend {
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    margin-bottom: spacing(2xs);
  }

  &__friend {
    @include button-reset;

    display: flex;
    align-items: center;
    gap: spacing(sm);
    padding: spacing(2xs) spacing(sm);
    border-radius: radius(sm);
    border: 1px solid var(--color-border-light);
    text-align: left;
    color: var(--color-text);
    font-size: 0.9375rem;

    &:hover { background: var(--color-background-mute); }

    &--selected {
      border-color: var(--color-primary);
      background: var(--color-background-mute);
    }
  }

  &__avatar {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    object-fit: cover;
  }

  &__error {
    color: var(--color-error);
    font-size: 0.875rem;
  }

  &__action {
    @include button-reset;

    padding: spacing(2xs) spacing(md);
    border-radius: radius(sm);
    border: 1px solid var(--color-border-light);
    color: var(--color-text-secondary);
    font-size: 0.875rem;

    &:hover { background: var(--color-background-mute); }

    &--primary {
      border-color: var(--color-primary);
      color: var(--color-primary);
    }

    &:disabled { opacity: 0.5; cursor: not-allowed; }
  }
}
</style>
