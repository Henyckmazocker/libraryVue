<template>
  <!-- El `<Dialog>` de PrimeVue trae su propio atrapador de foco: no se envuelve
       en `useFocusTrap`, que es para los cuatro modales propios del proyecto. -->
  <Dialog
    v-model:visible="visible"
    header="Ponerlo en un club"
    :modal="true"
    :dismissable-mask="true"
    class="add-to-club-dialog"
    :pt="{ mask: { style: 'z-index: 2500' } }"
  >
    <div class="add-to-club-dialog__body">
      <p class="add-to-club-dialog__item">
        El club empezará con <strong>{{ entityTitle }}</strong>
      </p>

      <div
        v-if="isLoading"
        class="add-to-club-dialog__state"
      >
        <i class="pi pi-spin pi-spinner" />
      </div>

      <p
        v-else-if="elegibles.length === 0"
        class="add-to-club-dialog__state"
      >
        No hay ningún club tuyo libre ahora mismo. Solo puedes elegir ítem en los
        clubs que organizas y que no tengan uno activo.
      </p>

      <fieldset
        v-else
        class="add-to-club-dialog__clubs"
      >
        <legend class="add-to-club-dialog__legend">
          Elige el club
        </legend>
        <!-- Botones con `aria-pressed`, no `<div @click>`: las 20 reglas de
             accesibilidad están en `error` y esto es un selector de verdad. -->
        <button
          v-for="club in elegibles"
          :key="club.id"
          type="button"
          class="add-to-club-dialog__club"
          :class="{ 'add-to-club-dialog__club--selected': clubId === club.id }"
          :aria-pressed="clubId === club.id"
          @click="clubId = club.id"
        >
          <span class="add-to-club-dialog__club-name">{{ club.name }}</span>
          <span class="add-to-club-dialog__club-count">
            {{ club.member_count }} {{ club.member_count === 1 ? 'miembro' : 'miembros' }}
          </span>
        </button>
      </fieldset>

      <p
        v-if="error"
        class="add-to-club-dialog__error"
        role="alert"
      >
        {{ error }}
      </p>
    </div>

    <template #footer>
      <button
        type="button"
        class="add-to-club-dialog__action"
        :disabled="isSaving"
        @click="visible = false"
      >
        Cancelar
      </button>
      <button
        type="button"
        class="add-to-club-dialog__action add-to-club-dialog__action--primary"
        :disabled="!clubId || isSaving"
        @click="submit"
      >
        <i
          v-if="isSaving"
          class="pi pi-spin pi-spinner"
        />
        Empezar
      </button>
    </template>
  </Dialog>
</template>

<script setup>
import { computed, inject, onMounted, ref } from 'vue'
import Dialog from 'primevue/dialog'
import { storeToRefs } from 'pinia'
import { useClubsStore } from '@/store/clubs'

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

const clubs = useClubsStore()
const { clubs: allClubs, isLoading, isSaving, error } = storeToRefs(clubs)
const notifications = inject('notifications', null)

const clubId = ref(null)
const conActivo = ref(new Set())

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

/**
 * Solo los que organizo **y** no tienen ítem activo.
 *
 * Lo primero porque elegir es del dueño; lo segundo porque `set_club_pick`
 * devuelve 409 con uno activo, y ofrecer un botón que va a fallar es peor que
 * no ofrecerlo. `get_my_clubs` no dice si hay activo, así que se pregunta club
 * a club al abrir — son pocos y solo los propios.
 */
const elegibles = computed(
  () => allClubs.value.filter((c) => c.is_owner && !conActivo.value.has(c.id))
)

onMounted(async () => {
  await clubs.fetchMyClubs()

  const propios = allClubs.value.filter((c) => c.is_owner)
  const ocupados = new Set()

  await Promise.all(propios.map(async (club) => {
    // Se mira el progreso y no el club entero: es la respuesta más pequeña que
    // distingue «tiene activo» de «no lo tiene» — sin ítem devuelve
    // `axis: null` y lista vacía, que es un contrato estable y no un error.
    //
    // Se lee del RESULTADO y no de `clubs.progressMembers`: estas llamadas van
    // en paralelo sobre el mismo store, y el estado compartido lo deja la
    // última que termine, no la de este club.
    const resultado = await clubs.fetchProgress(club.id)
    if (resultado.success && resultado.members.length > 0) ocupados.add(club.id)
  }))

  conActivo.value = ocupados
})

const submit = async () => {
  const result = await clubs.setPick(clubId.value, {
    entityType: props.entityType,
    entityId: props.entityId,
    entityTitle: props.entityTitle,
    entityCover: props.entityCover
  })

  if (!result.success) {
    // El 409 del club que ya tiene ítem viene traducido por código desde el
    // store: el backend responde en inglés y no se lee su texto.
    notifications?.showError?.(result.message || 'No se pudo elegir el ítem')
    return
  }

  visible.value = false
  notifications?.showSuccess?.('El club ya tiene su siguiente ítem')
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.add-to-club-dialog {
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
    font-size: 0.875rem;
  }

  &__clubs {
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

  &__club {
    @include button-reset;

    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: spacing(sm);
    padding: spacing(2xs) spacing(sm);
    border-radius: radius(sm);
    border: 1px solid var(--color-border-light);
    text-align: left;

    &:hover { background: var(--color-background-mute); }

    &--selected {
      border-color: var(--color-primary);
      background: var(--color-background-mute);
    }
  }

  &__club-name {
    color: var(--color-text);
    font-size: 0.9375rem;
  }

  &__club-count {
    font-size: 0.75rem;
    color: var(--color-text-secondary);
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
