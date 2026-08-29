<template>
  <ul class="club-progress">
    <li
      v-for="member in members"
      :key="member.user_id"
      class="club-progress__row"
      :class="{ 'club-progress__row--done': member.completed }"
    >
      <span class="club-progress__name">{{ member.username }}</span>

      <!-- Con eje: la posición en su unidad. `point` es `null` en quien no ha
           empezado, y ese miembro SALE igualmente: es quien bloquea el cierre
           automático, así que esconderlo sería esconder el motivo. -->
      <template v-if="axis && member.point !== null">
        <span class="club-progress__bar">
          <span
            class="club-progress__fill"
            :style="{ width: fillFor(member) }"
          />
        </span>
        <span class="club-progress__point">{{ unitLabel }}{{ member.point }}</span>
      </template>

      <span
        v-else-if="axis"
        class="club-progress__point club-progress__point--none"
      >Sin empezar</span>

      <!-- Sin eje, la marca es binaria y no hay número que enseñar. -->
      <span
        v-else
        class="club-progress__point"
      >{{ member.completed ? 'Sí' : 'Todavía no' }}</span>

      <i
        v-if="member.completed"
        class="pi pi-check-circle club-progress__done-icon"
        aria-hidden="true"
      />
      <span
        v-if="member.completed"
        class="u-sr-only"
      >Lo ha terminado</span>
    </li>
  </ul>
</template>

<script setup>
import { computed } from 'vue'
import { mediaRegistry, mediaKeys } from '@/config/mediaRegistry'

const props = defineProps({
  /**
   * `'page'`, `'season'` o `null`. Lo manda RESUELTO el servidor y no se deduce
   * del medio: una serie viaja con `entity_type: 'movie'` y solo el backend
   * sabe distinguirla, mirando `movie.media_type`.
   */
  axis: { type: String, default: null },
  /** `[{ user_id, username, point, completed }]` de `get_club_progress`. */
  members: { type: Array, default: () => [] }
})

/**
 * La unidad sale del registry, buscando la entrada que declara este eje. Se
 * comprueba contra `mediaKeys` —los SEIS— y no llamando a `getMediaConfig`, que
 * lanza con un medio desconocido: `series` es la única entrada con eje `season`
 * y no tiene store.
 */
const unitLabel = computed(() => {
  if (!props.axis) return ''

  const key = mediaKeys.find((k) => mediaRegistry[k].progress?.axis === props.axis)

  return key ? (mediaRegistry[key].progress.unit ?? '') : ''
})

/**
 * La barra es RELATIVA al que más ha avanzado, no a un total: ni los libros
 * tienen siempre `pages` ni las series un `total_seasons` fiable, así que un
 * porcentaje sobre el total mentiría la mitad de las veces. Comparar entre
 * miembros es además lo que la pantalla quiere contar.
 */
const maxPoint = computed(() => {
  const puntos = props.members.map((m) => m.point ?? 0)

  return Math.max(1, ...puntos)
})

const fillFor = (member) => `${Math.round(((member.point ?? 0) / maxPoint.value) * 100)}%`
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.club-progress {
  display: flex;
  flex-direction: column;
  gap: spacing(2xs);
  list-style: none;
  padding: 0;
  margin: 0;

  &__row {
    display: grid;
    grid-template-columns: minmax(0, 8rem) 1fr auto auto;
    align-items: center;
    gap: spacing(sm);
    padding: spacing(2xs) 0;

    @include responsive-below(md) {
      grid-template-columns: minmax(0, 1fr) auto auto;
    }
  }

  &__name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.875rem;
    color: var(--color-text);
  }

  &__bar {
    height: 0.5rem;
    border-radius: radius(sm);
    background: var(--color-background-mute);
    overflow: hidden;

    @include responsive-below(md) {
      display: none;
    }
  }

  &__fill {
    display: block;
    height: 100%;
    background: var(--color-primary);
  }

  &__point {
    font-size: 0.8125rem;
    color: var(--color-text-secondary);
    font-variant-numeric: tabular-nums;

    &--none { font-style: italic; }
  }

  &__done-icon {
    color: var(--color-success);
  }
}
</style>
