<template>
  <div class="club-notes">
    <p
      v-if="notes.length === 0"
      class="club-notes__empty"
    >
      Todavía nadie ha escrito una nota pública sobre esto.
    </p>

    <ul
      v-else
      class="club-notes__list"
    >
      <li
        v-for="note in notes"
        :key="note.noteId"
        class="club-notes__item"
        :class="{ 'club-notes__item--hidden': note.isSpoiler }"
      >
        <div class="club-notes__meta">
          <span class="club-notes__author">
            {{ note.author }}<span v-if="note.isMine"> (tú)</span>
          </span>
          <!-- `atPoint` viaja aunque la nota esté oculta: decir «hay una nota en
               la página 180» no destripa nada, y es lo que da sentido a la
               espera. -->
          <span
            v-if="note.atPoint !== null"
            class="club-notes__point"
          >{{ unitLabel }}{{ note.atPoint }}</span>
        </div>

        <!-- El texto oculto NO está aquí porque NO ESTÁ EN LA RESPUESTA: el
             servidor lo manda como `null`. No se difumina con CSS — un texto
             en el DOM está enseñado, se lea con «inspeccionar elemento» o con
             un lector de pantalla. -->
        <p
          v-if="note.isSpoiler"
          class="club-notes__spoiler"
        >
          <i
            class="pi pi-eye-slash"
            aria-hidden="true"
          />
          {{ spoilerLabel(note) }}
        </p>

        <p
          v-else
          class="club-notes__text"
        >
          {{ note.text }}
        </p>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { mediaRegistry, mediaKeys } from '@/config/mediaRegistry'

const props = defineProps({
  /** `'page'`, `'season'` o `null`. Lo manda resuelto el servidor. */
  axis: { type: String, default: null },
  /** `[{ noteId, author, isMine, isSpoiler, text, atPoint, createdAt }]`. */
  notes: { type: Array, default: () => [] }
})

/**
 * La unidad sale del registry, buscando la entrada que declara este eje. Se
 * comprueba contra `mediaKeys` —los SEIS— y no con `getMediaConfig`, que lanza
 * con un medio desconocido.
 */
const unitLabel = computed(() => {
  if (!props.axis) return ''

  const key = mediaKeys.find((k) => mediaRegistry[k].progress?.axis === props.axis)

  return key ? (mediaRegistry[key].progress.unit ?? '') : ''
})

const etiquetaEje = computed(() => {
  if (!props.axis) return ''

  const key = mediaKeys.find((k) => mediaRegistry[k].progress?.axis === props.axis)

  return key ? mediaRegistry[key].progress.label.toLowerCase() : ''
})

/**
 * Se dice POR QUÉ está oculta, no solo que lo está. Con eje se puede ser
 * concreto —«llega a la página 180»— y sin él solo cabe «cuando lo termines»,
 * que es exactamente lo que la regla comprueba.
 */
const spoilerLabel = (note) => (
  note.atPoint !== null
    ? `Oculta hasta que llegues a la ${etiquetaEje.value} ${note.atPoint}`
    : 'Oculta hasta que lo termines'
)
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.club-notes {
  &__empty {
    padding: spacing(md) 0;
    color: var(--color-text-secondary);
    font-size: 0.875rem;
  }

  &__list {
    display: flex;
    flex-direction: column;
    gap: spacing(sm);
    list-style: none;
    padding: 0;
    margin: 0;
  }

  &__item {
    padding: spacing(sm);
    border-radius: radius(sm);
    background: var(--color-background-mute);
    border: 1px solid var(--color-border-light);

    &--hidden {
      // Sin fondo propio ni opacidad sobre el texto: no hay texto que atenuar.
      // La diferencia visual es el borde discontinuo, que dice «aquí hay algo»
      // sin insinuar qué.
      border-style: dashed;
      background: transparent;
    }
  }

  &__meta {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: spacing(sm);
    margin-bottom: spacing(3xs);
  }

  &__author {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--color-text);
  }

  &__point {
    font-size: 0.75rem;
    color: var(--color-text-secondary);
    font-variant-numeric: tabular-nums;
  }

  &__text {
    font-size: 0.875rem;
    color: var(--color-text);
    white-space: pre-wrap;
  }

  &__spoiler {
    display: flex;
    align-items: center;
    gap: spacing(2xs);
    font-size: 0.8125rem;
    font-style: italic;
    color: var(--color-text-secondary);
  }
}
</style>
