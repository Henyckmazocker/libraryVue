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
        <strong>{{ entityTitle }}</strong>
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
        No hay ningún club donde puedas poner esto ahora mismo. Se puede proponer
        en los clubs que estén eligiendo, y elegir directamente solo en los que
        organizas y no tengan ítem activo.
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
          <!-- Qué va a pasar con ESTE club, dicho antes de pulsar: proponer y
               elegir no son lo mismo y el usuario tiene que saber cuál hace. -->
          <span class="add-to-club-dialog__club-count">
            {{ acciones.get(club.id) === 'propose' ? 'Se propone y se vota' : 'Empieza directamente' }}
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
        {{ acciones.get(clubId) === 'propose' ? 'Proponerlo' : 'Empezar' }}
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

/**
 * Qué acción admite cada club: `'propose'` o `'pick'`. Los que no aparecen no
 * admiten ninguna y no se ofrecen.
 *
 * Es un mapa y no dos listas porque el botón tiene que decir cuál de las dos
 * hace, y el texto se lee de aquí.
 */
const acciones = ref(new Map())

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

/** Los que admiten algo. El orden es el de `get_my_clubs`. */
const elegibles = computed(
  () => allClubs.value.filter((c) => acciones.value.has(c.id))
)

/**
 * Con la votación, un club sin ítem activo **siempre** tiene ronda abierta, así
 * que ya no basta con «lo organizo y está libre»: hay que mirar la ronda.
 *
 * Se pide el club entero y no el progreso, como se hacía antes. `get_club` es
 * la única respuesta que trae el bloque `round` con su `canPropose` y su
 * `reasonBlocked` **ya resueltos** por el servidor, y con la rotación en juego
 * no hay forma de deducir aquí si te toca proponer sin copiar la regla.
 *
 * Ojo: `get_club` ESCRIBE —abre la ronda, y puede cerrarla—, así que abrir este
 * diálogo hace avanzar los clubs que estuvieran listos. Es el mismo efecto que
 * tiene mirar la pantalla del club, que es la semántica del proyecto entero.
 */
onMounted(async () => {
  await clubs.fetchMyClubs()

  const mios = [...allClubs.value]
  const mapa = new Map()

  await Promise.all(mios.map(async (club) => {
    // Se lee del RESULTADO y no del store: estas llamadas van en paralelo sobre
    // el mismo estado compartido, y lo deja la última que termine.
    const resultado = await clubs.fetchClubSnapshot(club.id)
    if (!resultado.success) return

    if (resultado.round?.phase === 'proposing' && resultado.round.canPropose) {
      mapa.set(club.id, 'propose')
      return
    }

    // La vía de escape del dueño: sigue existiendo, pero ya no es lo normal.
    if (club.is_owner && !resultado.pick) mapa.set(club.id, 'pick')
  }))

  acciones.value = mapa
})

const submit = async () => {
  const datos = {
    entityType: props.entityType,
    entityId: props.entityId,
    entityTitle: props.entityTitle,
    entityCover: props.entityCover
  }

  const proponer = acciones.value.get(clubId.value) === 'propose'
  const result = proponer
    ? await clubs.proposeItem(clubId.value, datos)
    : await clubs.setPick(clubId.value, datos)

  if (!result.success) {
    // Traducido por código desde el store: el backend responde en inglés y no
    // se lee su texto.
    notifications?.showError?.(result.message || 'No se pudo añadir al club')
    return
  }

  visible.value = false
  notifications?.showSuccess?.(
    proponer ? 'Propuesta enviada al club' : 'El club ya tiene su siguiente ítem'
  )
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
