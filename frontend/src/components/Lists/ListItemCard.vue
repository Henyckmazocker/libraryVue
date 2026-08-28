<template>
  <div
    class="list-card"
    :class="'list-card--' + item.entity_type"
  >
    <component
      :is="itemRoute ? RouterLink : 'div'"
      class="list-card__cover"
      :to="itemRoute"
    >
      <img
        v-if="coverSrc"
        :src="coverSrc"
        :alt="item.entity_title || 'Portada'"
        :width="aspect.width"
        :height="aspect.height"
        loading="lazy"
        decoding="async"
        @error="onCoverError"
      >
      <div
        v-else
        class="list-card__cover-placeholder"
      >
        <i :class="placeholderIcon" />
      </div>
    </component>

    <div class="list-card__content">
      <!-- Con `{{ }}`, nunca `v-html`: el título viene de un catálogo ajeno. -->
      <component
        :is="itemRoute ? RouterLink : 'span'"
        class="list-card__title"
        :to="itemRoute"
      >
        {{ item.entity_title || 'Sin título' }}
      </component>

      <span class="list-card__media">
        <i :class="placeholderIcon" />
        {{ mediaLabel }}
      </span>
    </div>

    <button
      v-if="canEdit"
      type="button"
      class="list-card__remove"
      :disabled="busy"
      @click="$emit('remove', item)"
    >
      <i :class="busy ? 'pi pi-spin pi-spinner' : 'pi pi-times'" />
      <span class="u-sr-only">Quitar {{ item.entity_title || 'este ítem' }} de la lista</span>
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { detailRouteFor, getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import CoverService from '@/services/CoverService'

/**
 * Una fila de `media_list_item`, pintada.
 *
 * **No se reutiliza `shared/MediaListItem.vue` y no es por capricho**: aquel lee
 * el ítem a través de los accessors del registry, y esos leen campos propios de
 * cada medio (`idOf: (i) => i.isbn` en libros, `i.imdbID` en películas,
 * `subtitleOf` con `i.author` y `i.publicationDate`). Una fila de lista tiene
 * `entity_type`, `entity_id`, `entity_title` y `entity_cover`, y ninguno de
 * esos: con un adaptador, TODAS las tarjetas de libro dirían «Autor
 * desconocido». Del registry sí sale lo que de verdad es declarativo — el icono
 * del placeholder, las dimensiones de la caja y la etiqueta del medio —.
 */
const props = defineProps({
  item: {
    type: Object,
    required: true
  },
  // Lo decide el servidor (`can_edit` de `get_list`), no esta tarjeta.
  canEdit: {
    type: Boolean,
    default: false
  },
  busy: {
    type: Boolean,
    default: false
  }
})

defineEmits(['remove'])

// `entity_type` es un ENUM del backend, pero la tarjeta puede recibir una fila
// vieja o un medio que el registry no conozca: `getMediaConfig` LANZA con uno
// desconocido, así que se comprueba contra `mediaKeys` antes de llamarlo.
const config = computed(() =>
  mediaKeys.includes(props.item.entity_type) ? getMediaConfig(props.item.entity_type) : null
)

const mediaLabel = computed(() => config.value?.label ?? 'Ítem')

const placeholderIcon = computed(() => config.value?.list?.iconOf?.({}) ?? 'fas fa-star')

// Las dimensiones intrínsecas reservan la caja antes de que llegue la portada, y
// salen del mismo sitio que las de la fila de biblioteca.
const aspect = computed(() => config.value?.list?.coverAspect ?? { width: 50, height: 75 })

/**
 * El destino de la ficha. `detailRouteFor` devuelve `null` con un medio
 * desconocido o un id vacío en vez de reventar, y entonces se pinta con la
 * etiqueta neutra: nada de un `<a>` sin `href` ni de un `<div>` con `@click`,
 * que además tumbaría el lint.
 */
const itemRoute = computed(() => detailRouteFor(props.item.entity_type, props.item.entity_id))

/**
 * La portada, con el mismo escalón doble que la tarjeta del feed y la de la
 * bandeja. `entity_cover` es una URL de CDN congelada al añadir el ítem; la
 * copia local la sirve el backend por `?cover=`, que degrada solo con un 302 al
 * origen si aún no hay fichero.
 */
const localCover = computed(() =>
  CoverService.localCoverUrl(props.item.entity_type, props.item.entity_id)
)

const localFailed = ref(false)
const remoteFailed = ref(false)

// Hacen falta LOS DOS indicadores: sin saber qué se está pintando, un ítem sin
// copia local gastaría su primer `@error` marcando un fallo local que no ha
// ocurrido y, como el `src` no cambia, el navegador no reintentaría y el
// placeholder no llegaría nunca.
const usingLocal = computed(() => Boolean(localCover.value) && !localFailed.value)

const coverSrc = computed(() => {
  if (usingLocal.value) {
    return localCover.value
  }

  return remoteFailed.value ? null : (props.item.entity_cover || null)
})

/** Local → remota → placeholder, un escalón por error. */
const onCoverError = () => {
  if (usingLocal.value) {
    localFailed.value = true
    return
  }

  remoteFailed.value = true
}

// Anclado a la portada y no al ítem: la lista teclea por `id`, así que al quitar
// uno el componente se reutiliza con otro y arrastraría el fallo.
watch(localCover, () => {
  localFailed.value = false
  remoteFailed.value = false
})
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.list-card {
  display: flex;
  align-items: center;
  gap: spacing(md);
  padding: spacing(sm) spacing(md);
  border-radius: radius(md);
  background: var(--color-background-mute);
  // Hairline suave de tarjeta decorativa, no el borde de inputs y botones.
  border: 1px solid var(--color-border-light);
  // La franja de acento es lo que distingue un medio de otro de un vistazo,
  // que es justo lo que una lista mezclada necesita.
  border-left: 3px solid var(--color-border-light);

  &__cover {
    flex-shrink: 0;
    width: 50px;
    height: 75px;
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
    gap: spacing(3xs);
  }

  &__title {
    color: var(--color-text);
    font-weight: 600;
    text-decoration: none;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__media {
    display: inline-flex;
    align-items: center;
    gap: spacing(2xs);
    font-size: 0.8125rem;
    color: var(--color-text-secondary);
  }

  &__remove {
    @include button-reset;

    flex-shrink: 0;
    padding: spacing(2xs);
    border-radius: radius(sm);
    color: var(--color-text-secondary);

    &:hover:not(:disabled) { color: var(--color-error); }
    &:disabled { opacity: 0.6; }
  }
}

// El acento por medio sale de los tokens de entidad, que son los mismos cinco
// que valida el script de la skill `dataviz`. Ni un hex aquí.
.list-card--book  { border-left-color: var(--color-card-book-accent); }
.list-card--movie { border-left-color: var(--color-card-movie-accent); }
.list-card--game  { border-left-color: var(--color-card-game-accent); }
.list-card--album { border-left-color: var(--color-card-album-accent); }
.list-card--video { border-left-color: var(--color-card-video-accent); }
</style>
