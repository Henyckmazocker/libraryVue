<template>
  <div class="recommendation-card">
    <component
      :is="tagFor(itemRoute, 'div')"
      class="recommendation-card__cover"
      :to="itemRoute"
    >
      <img
        v-if="coverSrc"
        :src="coverSrc"
        :alt="recommendation.entity_title"
        width="48"
        height="64"
        loading="lazy"
        decoding="async"
        @error="onCoverError"
      >
      <div
        v-else
        class="recommendation-card__cover-placeholder"
      >
        <i :class="entityIcon" />
      </div>
    </component>

    <div class="recommendation-card__content">
      <p class="recommendation-card__header">
        <component
          :is="tagFor(profileRoute, 'span')"
          class="recommendation-card__sender"
          :to="profileRoute"
        >
          {{ senderName }}
        </component>
        te recomienda
        <component
          :is="tagFor(itemRoute, 'span')"
          class="recommendation-card__title"
          :to="itemRoute"
        >
          <strong>{{ recommendation.entity_title || 'un ítem' }}</strong>
        </component>
        <span class="recommendation-card__time">{{ relativeTime }}</span>
      </p>

      <!-- Con `{{ }}`, nunca `v-html`: esto lo escribe una persona. -->
      <p
        v-if="recommendation.comment"
        class="recommendation-card__comment"
      >
        {{ recommendation.comment }}
      </p>

      <div class="recommendation-card__actions">
        <button
          type="button"
          class="recommendation-card__action recommendation-card__action--add"
          :disabled="busy"
          @click="$emit('add', recommendation)"
        >
          <i :class="busy ? 'pi pi-spin pi-spinner' : 'pi pi-plus'" />
          Añadir
        </button>
        <button
          type="button"
          class="recommendation-card__action"
          :disabled="busy"
          @click="$emit('dismiss', recommendation)"
        >
          <i class="pi pi-times" />
          Descartar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { detailRouteFor } from '@/config/mediaRegistry'
import CoverService from '@/services/CoverService'

const props = defineProps({
  recommendation: {
    type: Object,
    required: true
  },
  // Mientras se resuelve, los dos botones de ESTA tarjeta se desactivan; el
  // resto de la lista sigue viva.
  busy: {
    type: Boolean,
    default: false
  }
})

defineEmits(['add', 'dismiss'])

/**
 * El destino del ítem. Puede ser `null` —`detailRouteFor` devuelve `null` con un
 * medio desconocido o un id vacío en vez de reventar—, y entonces la zona se
 * pinta con la etiqueta neutra: nada de un `<a>` sin `href` ni de un `<div>` con
 * `@click`, que además tumbaría el lint.
 *
 * No hay traducción a `series` como en el feed: `recommendations` no guarda el
 * `media_type` de la película, así que una serie recomendada abre su ficha de
 * película. Se sabe y se acepta; el M4 puede mandar el dato el día que haga falta.
 */
const itemRoute = computed(() =>
  detailRouteFor(props.recommendation.entity_type, props.recommendation.entity_id)
)

const senderName = computed(() =>
  props.recommendation.sender?.username || props.recommendation.sender?.name || 'Alguien'
)

// El perfil se resuelve por `username`, que es lo que pide `/user/:username`:
// quien no tiene username no tiene perfil al que ir.
const profileRoute = computed(() => {
  const username = props.recommendation.sender?.username
  return username ? { name: 'PublicProfile', params: { username } } : null
})

const tagFor = (route, fallbackTag) => (route ? RouterLink : fallbackTag)

/**
 * La portada, con el mismo escalón doble que los otros consumidores.
 *
 * `entity_cover` es una URL de CDN congelada en el momento de mandarla; la copia
 * local la sirve el backend por `?cover=`, que degrada solo con un 302 al origen
 * si aún no hay fichero. La clave es `entity_type` tal cual: una serie se guarda
 * con `AddMovieUseCase`, así que su fila lleva `media_type = 'movie'`.
 */
const localCover = computed(() =>
  CoverService.localCoverUrl(props.recommendation.entity_type, props.recommendation.entity_id)
)

const localFailed = ref(false)
const remoteFailed = ref(false)

// Hacen falta LOS DOS indicadores: sin saber qué se está pintando, una tarjeta
// sin copia local gastaría su primer `@error` marcando un fallo local que no ha
// ocurrido y, como el `src` no cambia, el navegador no reintentaría y el
// placeholder no llegaría nunca.
const usingLocal = computed(() => Boolean(localCover.value) && !localFailed.value)

const coverSrc = computed(() => {
  if (usingLocal.value) {
    return localCover.value
  }

  return remoteFailed.value ? null : (props.recommendation.entity_cover || null)
})

/** Local → remota → placeholder, un escalón por error. */
const onCoverError = () => {
  if (usingLocal.value) {
    localFailed.value = true
    return
  }

  remoteFailed.value = true
}

// Anclado a la portada y no a la recomendación: la lista teclea por `id`, así
// que al resolver una el componente se reutiliza con otra y arrastraría el fallo.
watch(localCover, () => {
  localFailed.value = false
  remoteFailed.value = false
})

const entityIcon = computed(() => {
  const icons = {
    book: 'pi pi-book',
    movie: 'pi pi-video',
    game: 'pi pi-desktop',
    album: 'pi pi-headphones',
    video: 'pi pi-youtube'
  }
  return icons[props.recommendation.entity_type] ?? 'pi pi-star'
})

const relativeTime = computed(() => {
  const diff = Date.now() - new Date(props.recommendation.created_at).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'ahora mismo'
  if (mins < 60) return `hace ${mins} min`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `hace ${hours} h`
  const days = Math.floor(hours / 24)
  return days < 30 ? `hace ${days} d` : `hace ${Math.floor(days / 30)} m`
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.recommendation-card {
  display: flex;
  gap: spacing(md);
  padding: spacing(md);
  border-radius: radius(md);
  background: var(--color-background-mute);
  // El hairline suave de una tarjeta decorativa es -light, no --color-border,
  // que es el de inputs y botones (`tokens/_colors.scss:40-44`).
  border: 1px solid var(--color-border-light);

  &__cover {
    flex-shrink: 0;
    width: 48px;
    height: 64px;
    border-radius: radius(sm);
    overflow: hidden;
    background: var(--color-background-soft);

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__cover-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    color: var(--color-text-secondary);
  }

  &__content {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: spacing(xs);
  }

  &__header {
    color: var(--color-text);
  }

  &__sender {
    font-weight: 600;
  }

  &__time {
    margin-left: spacing(xs);
    font-size: 0.8125rem;
    color: var(--color-text-secondary);
  }

  &__comment {
    color: var(--color-text-secondary);
    font-style: italic;
  }

  &__actions {
    display: flex;
    gap: spacing(sm);
    margin-top: spacing(xs);
  }

  &__action {
    @include button-reset;

    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    padding: spacing(2xs) spacing(sm);
    border-radius: radius(sm);
    border: 1px solid var(--color-border);
    color: var(--color-text-secondary);
    font-size: 0.875rem;

    &:hover:not(:disabled) {
      color: var(--color-text);
      border-color: var(--color-text-secondary);
    }

    &:disabled {
      opacity: 0.6;
    }

    &--add {
      color: var(--color-primary);
      border-color: var(--color-primary);
    }
  }
}
</style>
