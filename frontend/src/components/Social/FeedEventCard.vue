<template>
  <div
    class="feed-event-card"
    :class="`feed-event-card--${event.entity_type}`"
  >
    <component
      :is="tagFor(profileRoute, 'div')"
      class="feed-event-card__avatar"
      :to="profileRoute"
    >
      <img
        v-if="event.user?.picture"
        :src="event.user.picture"
        :alt="event.user?.username"
        loading="lazy"
        decoding="async"
      >
      <i
        v-else
        class="pi pi-user"
      />
    </component>

    <component
      :is="tagFor(itemRoute, 'div')"
      class="feed-event-card__cover"
      :to="itemRoute"
    >
      <img
        v-if="coverSrc"
        :src="coverSrc"
        :alt="event.entity_title"
        width="48"
        height="64"
        loading="lazy"
        decoding="async"
        @error="onCoverError"
      >
      <div
        v-else
        class="feed-event-card__cover-placeholder"
      >
        <i :class="entityIcon" />
      </div>
    </component>

    <div class="feed-event-card__content">
      <div class="feed-event-card__header">
        <component
          :is="tagFor(profileRoute, 'span')"
          class="feed-event-card__user"
          :to="profileRoute"
        >
          {{ event.user?.username || event.user?.name || 'Usuario' }}
        </component>
        <span class="feed-event-card__time">{{ relativeTime }}</span>
      </div>
      <p class="feed-event-card__description">
        <component
          :is="tagFor(itemRoute, 'span')"
          class="feed-event-card__title"
          :to="itemRoute"
        >
          <strong>{{ event.entity_title }}</strong>
        </component>
        {{ eventDescription }}
      </p>

      <!-- El texto de la nota va SIEMPRE entero en el DOM y se recorta con CSS
           (`-webkit-line-clamp`), no con JavaScript: así un lector de pantalla
           lo lee completo aunque visualmente esté cortado. Y con `{{ }}`, nunca
           `v-html`: esto lo escribe una persona y es un feed social. -->
      <template v-if="noteText">
        <p
          class="feed-event-card__note"
          :class="{ 'feed-event-card__note--open': noteOpen }"
        >
          {{ noteText }}
        </p>
        <button
          v-if="noteIsLong"
          type="button"
          class="feed-event-card__note-more"
          :aria-expanded="noteOpen"
          @click="noteOpen = !noteOpen"
        >
          {{ noteOpen ? 'Ver menos' : 'Ver más' }}
        </button>
      </template>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { detailRouteFor } from '@/config/mediaRegistry'
import CoverService from '@/services/CoverService'

const props = defineProps({
  event: {
    type: Object,
    required: true
  }
})

/**
 * El medio del registry al que va este evento, que **no** es siempre su
 * `entity_type`.
 *
 * Una serie se guarda con `AddMovieUseCase` y por tanto emite
 * `entity_type = 'movie'`: el ENUM de `feed_events` no tiene `'series'` y no
 * puede tenerlo, porque en el backend series y películas son la misma entidad.
 * Lo que las separa es `movie.media_type`, que llega en `entity_media_type` por
 * el `LEFT JOIN` de `MySqlFeedEventRepository`. Sin esto, *Twin Peaks* abre
 * `/movies/tt0098936`: una ruta que existe y carga, pero pinta la ficha de
 * película sin temporadas.
 *
 * La ausencia se lee como «película»: es el `DEFAULT` de la columna
 * (`init.sql:385`) y es lo que llega para todo lo que no es película.
 */
const registryKey = computed(() =>
  props.event.entity_type === 'movie' && props.event.entity_media_type === 'series'
    ? 'series'
    : props.event.entity_type
)

/**
 * Los dos destinos de la tarjeta: el ítem y la persona.
 *
 * Los dos pueden ser `null`, y no como caso raro: `feed_events.entity_type` y
 * `entity_id` son NULLables, y en el ENUM de `event_type` hay tipos que hoy no
 * emite nadie (`achievement`) y que el día que se emitan no traerán entidad.
 * Cuando el destino falta, la zona se pinta con la etiqueta neutra en vez de
 * con un enlace: nada de un `<a>` sin `href` ni de un `<div>` con `@click`,
 * que además tumbaría el lint (20 reglas de accesibilidad en `error`).
 */
const itemRoute = computed(() =>
  detailRouteFor(registryKey.value, props.event.entity_id)
)

// El perfil se resuelve por `username`, que es lo que pide `/user/:username`.
// El `name` sirve para pintar la tarjeta, pero no para navegar: quien no tiene
// username no tiene perfil al que ir.
const profileRoute = computed(() => {
  const username = props.event.user?.username
  return username ? { name: 'PublicProfile', params: { username } } : null
})

// La etiqueta neutra cambia según dónde caiga: dentro del `<p>` de la
// descripción un `<div>` sería HTML inválido, así que ahí es un `<span>`.
const tagFor = (route, fallbackTag) => (route ? RouterLink : fallbackTag)

/**
 * La portada, con el escalón doble que ya usan los otros cinco consumidores.
 *
 * `event.entity_cover` es una URL de CDN **congelada**: se rellenó en el momento
 * del evento con la que el ítem tuviera entonces y nada la refresca nunca, así
 * que además de salir fuera puede haber caducado. La copia local la sirve el
 * propio backend por `?cover=`, y el endpoint degrada solo —302 al origen si aún
 * no hay fichero—, así que apuntar aquí nunca es peor.
 *
 * La clave es `entity_type` **y no `registryKey`**: una serie se guarda con
 * `AddMovieUseCase`, así que su fila de `cover_file` lleva `media_type = 'movie'`.
 * Es la misma regla que ya aplica `MediaDetailView`. Y no hay que traducir el id:
 * los cinco emisores pasan el mismo valor a `recordItemAdded()` y a
 * `recordCover()` (`AddAlbumUseCase.php:149` y `:174`, y las otras cuatro igual).
 */
const localCover = computed(() =>
  CoverService.localCoverUrl(props.event.entity_type, props.event.entity_id)
)

const localFailed = ref(false)
const remoteFailed = ref(false)

// Hay que saber **qué** se está pintando para decidir a dónde caer: sin esto, un
// evento sin copia local gastaría su primer `@error` en marcar un fallo local que
// no ha ocurrido, y como el `src` no cambiaría, el navegador no reintentaría y el
// placeholder no llegaría nunca.
const usingLocal = computed(() => Boolean(localCover.value) && !localFailed.value)

const coverSrc = computed(() => {
  if (usingLocal.value) {
    return localCover.value
  }

  return remoteFailed.value ? null : (props.event.entity_cover || null)
})

/** Local → remota → placeholder, un escalón por error. */
const onCoverError = () => {
  if (usingLocal.value) {
    localFailed.value = true
    return
  }

  remoteFailed.value = true
}

// El reset se ancla a la portada y no al evento: `FeedList` teclea las tarjetas
// por `event.id`, así que al paginar un componente se reutiliza con otro evento
// y arrastraría el fallo del anterior.
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
  return icons[props.event.entity_type] ?? 'pi pi-star'
})

/**
 * Lo que la tarjeta sabe del evento vive en `metadata`, no en columnas sueltas.
 *
 * Hasta el 2026-08-25 esto leía `props.event.new_value`, **campo que no existe
 * en `feed_events`**: los datos van en `metadata` (`status_changed` lo usa desde
 * la migración de mayo, e `item_rated` igual). El resultado era que un evento de
 * valoración decía literalmente «recibió una valoración de **undefined**» y uno
 * de cambio de estado, «cambió de estado a "undefined"». Es un fallo
 * preexistente, no de este plan, pero estaba en el mismo `computed` que había
 * que tocar.
 */
const metadata = computed(() => props.event.metadata ?? {})

const eventDescription = computed(() => {
  switch (props.event.event_type) {
    case 'item_added': return 'se añadió a la biblioteca'
    case 'item_rated': return `recibió una valoración de ${metadata.value.rating ?? '—'}`
    case 'status_changed': return `cambió de estado a "${metadata.value.new_status ?? '—'}"`
    case 'notes_updated': return 'tiene una nota nueva'
    case 'reading_session': return 'tiene una sesión de lectura registrada'
    default: return ''
  }
})

/** El texto de la nota, cuando el evento es de nota. */
const noteText = computed(() =>
  props.event.event_type === 'notes_updated' ? (metadata.value.note_text ?? '') : ''
)

// El umbral es de caracteres y no de líneas a propósito: contar líneas exige
// medir el DOM, y aquí basta con saber si merece la pena ofrecer el botón.
const noteIsLong = computed(() => noteText.value.length > 180)
const noteOpen = ref(false)

const relativeTime = computed(() => {
  const diff = Date.now() - new Date(props.event.created_at).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'ahora mismo'
  if (mins < 60) return `hace ${mins} min`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `hace ${hours} h`
  const days = Math.floor(hours / 24)
  return `hace ${days} d`
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.feed-event-card {
  display: flex;
  gap: spacing(md);
  padding: spacing(md);
  border-radius: radius(md);
  background: var(--color-background-mute);
  border-left: 3px solid var(--color-primary);

  &--book { border-left-color: var(--color-card-book-accent); }
  &--movie { border-left-color: var(--color-card-movie-accent); }
  &--game { border-left-color: var(--color-card-game-accent); }
  &--album { border-left-color: var(--color-card-album-accent); }
  &--video { border-left-color: var(--color-card-video-accent); }

  &__avatar {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: radius(full);
    overflow: hidden;
    background: var(--color-background-soft);
    display: flex;
    align-items: center;
    justify-content: center;
    align-self: center;
    font-size: 1.1rem;
    color: var(--color-text-secondary);

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__cover {
    flex-shrink: 0;
    width: 48px;
    height: 64px;
    border-radius: radius(sm);
    overflow: hidden;
    background: var(--color-background-soft);
    display: flex;
    align-items: center;
    justify-content: center;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
  }

  &__cover-placeholder {
    font-size: 1.5rem;
    color: var(--color-text-secondary);
  }

  &__content {
    flex: 1;
    min-width: 0;
  }

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: spacing(sm);
    margin-bottom: spacing(2xs);
  }

  // Los dos enlaces de texto no se subrayan en reposo —dentro de una tarjeta
  // serían dos rayas compitiendo con el texto— y sí al pasar por encima. El
  // foco no se toca: lo pone el anillo global de `base/_globals.scss:10-12`
  // sobre cualquier `a`, y `:where()` lo deja a especificidad cero justo para
  // que nadie tenga que redefinirlo.
  &__user {
    font-weight: 600;
    color: var(--color-primary);
    text-decoration: none;

    &:hover { text-decoration: underline; }
  }

  // El título hereda el color del párrafo: es un enlace, pero lo que lo
  // distingue en la tarjeta es la negrita que ya tenía, no un color nuevo.
  &__title {
    color: inherit;
    text-decoration: none;

    &:hover { text-decoration: underline; }
  }

  &__time {
    font-size: 0.75rem;
    color: var(--color-text-secondary);
    white-space: nowrap;
  }

  &__description {
    font-size: 0.875rem;
    color: var(--color-text);
    margin: 0;
  }

  // El recorte es CSS y no JavaScript a propósito: el texto completo está
  // siempre en el DOM, así que un lector de pantalla lo lee entero aunque
  // visualmente se vean tres líneas.
  &__note {
    margin: spacing(xs) 0 0;
    font-size: 0.875rem;
    color: var(--color-text-secondary);
    white-space: pre-line;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
    overflow: hidden;

    &--open {
      -webkit-line-clamp: unset;
      overflow: visible;
    }
  }

  // `button-reset` va sin `width` a propósito (ver la nota del mixin), así que
  // este se ajusta a su texto y no ocupa la fila entera.
  &__note-more {
    @include button-reset;

    margin-top: spacing(2xs);
    font-size: 0.8125rem;
    color: var(--color-accent);
    text-decoration: underline;
    cursor: pointer;
  }
}
</style>
