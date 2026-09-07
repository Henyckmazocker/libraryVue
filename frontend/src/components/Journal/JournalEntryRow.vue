<template>
  <div
    class="journal-row"
    :class="'journal-row--' + entry.media"
  >
    <component
      :is="itemRoute ? RouterLink : 'div'"
      class="journal-row__cover"
      :to="itemRoute"
    >
      <img
        v-if="coverSrc"
        :src="coverSrc"
        :alt="entry.entity_title || t('common.untitled')"
        :width="aspect.width"
        :height="aspect.height"
        loading="lazy"
        decoding="async"
        @error="onCoverError"
      >
      <div
        v-else
        class="journal-row__cover-placeholder"
      >
        <i :class="placeholderIcon" />
      </div>
    </component>

    <div class="journal-row__main">
      <!-- Con `{{ }}`, nunca `v-html`: el título viene de un catálogo ajeno. -->
      <component
        :is="itemRoute ? RouterLink : 'span'"
        class="journal-row__title"
        :to="itemRoute"
      >
        {{ entry.entity_title || t('common.untitled') }}
      </component>

      <span class="journal-row__media">
        <i :class="placeholderIcon" />
        {{ mediaLabel }}
      </span>
    </div>

    <span
      v-if="entry.is_repeat"
      class="journal-row__repeat"
      :title="t('journal.repeatTitle')"
    >
      <i class="fas fa-rotate-right" />
      <span class="u-sr-only">{{ t('journal.repeat') }}</span>
    </span>

    <RatingComponent
      v-if="entry.rating !== null"
      class="journal-row__rating"
      :rating="entry.rating"
      :editable="false"
      size="small"
    />

    <template v-if="!readonly">
      <button
        type="button"
        class="journal-row__menu"
        :aria-label="t('journal.actionsFor', { title: entry.entity_title || t('common.untitled') })"
        @click="abrirMenu"
      >
        <i class="fas fa-ellipsis" />
      </button>

      <Menu
        ref="menuRef"
        :model="opcionesMenu"
        :popup="true"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import Menu from 'primevue/menu'
import RatingComponent from '@/components/common/RatingComponent.vue'
import { detailRouteFor, getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import CoverService from '@/services/CoverService'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()

/**
 * Una entrada del diario, en fila densa.
 *
 * **No se reutiliza `shared/MediaListItem.vue`, por la misma razón por la que no
 * lo hace `Lists/ListItemCard.vue`**: aquel lee el ítem con los accessors del
 * registry (`idOf: (i) => i.isbn` en libros, `subtitleOf` con `i.author`), y una
 * entrada del diario es una fila denormalizada —`media`, `entity_id`,
 * `entity_title`, `entity_cover`— que no tiene ninguno de esos campos. Pintaría,
 * pero mintiendo: todos los libros dirían «Autor desconocido». Del registry sale
 * solo lo declarativo de verdad: el icono, las medidas de la caja y la etiqueta.
 *
 * Es más baja que la fila de biblioteca a propósito: un diario se recorre por
 * años, y aquí caben tres veces más entradas por pantalla.
 */
const props = defineProps({
  entry: {
    type: Object,
    required: true
  },

  /**
   * Modo lectura: sin el menú ⋯, que solo sabe editar y borrar. Es lo que pinta
   * la sección del diario en el perfil público de un amigo, y va por prop y no
   * por una copia del componente: el escalón de portadas de más abajo es lo
   * bastante delicado como para no querer dos versiones de él.
   */
  readonly: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['edit', 'remove'])

// `media` viene de la base, así que puede ser un medio que el registry no
// conozca. `getMediaConfig` **lanza** con uno desconocido: se comprueba antes.
const config = computed(() =>
  mediaKeys.includes(props.entry.media) ? getMediaConfig(props.entry.media) : null
)

const mediaLabel = computed(() => config.value?.label ?? t('misc.item'))
const placeholderIcon = computed(() => config.value?.list?.iconOf?.({}) ?? 'fas fa-star')

// Las medidas intrínsecas reservan la caja antes de que llegue la portada.
const aspect = computed(() => config.value?.list?.coverAspect ?? { width: 50, height: 75 })

// `detailRouteFor` devuelve `null` con un medio desconocido o un id vacío en vez
// de reventar; entonces la fila se pinta sin enlace, nunca con un `<div @click>`
// —que además tumbaría el lint de accesibilidad—.
const itemRoute = computed(() => detailRouteFor(props.entry.media, props.entry.entity_id))

/**
 * **Una serie pide su portada como `movie`, no como `series`.** Se guardan con
 * `AddMovieUseCase`, así que su fila de `cover_file` lleva `media_type = 'movie'`
 * y no hay ni una con `'series'`: pedir `?cover=series/tt…` devuelve 404 y la
 * fila se queda con la imagen rota. La clave sí es la suya (el imdbID). Es el
 * mismo mapeo que hace `coverMedia` en `MediaDetailView.vue:510`.
 */
const coverMedia = computed(() => (props.entry.media === 'series' ? 'movie' : props.entry.media))

const localCover = computed(() =>
  CoverService.localCoverUrl(coverMedia.value, props.entry.entity_id)
)

// Los DOS indicadores, como en la tarjeta del feed: sin saber cuál se está
// pintando, una entrada sin copia local gastaría su primer `@error` marcando un
// fallo local que no ha ocurrido y, como el `src` no cambia, el navegador no
// reintenta y el placeholder no llega nunca.
const localFailed = ref(false)
const remoteFailed = ref(false)

const usingLocal = computed(() => Boolean(localCover.value) && !localFailed.value)

const coverSrc = computed(() => {
  if (usingLocal.value) {
    return localCover.value
  }

  return remoteFailed.value ? null : (props.entry.entity_cover || null)
})

const onCoverError = () => {
  if (usingLocal.value) {
    localFailed.value = true
    return
  }

  remoteFailed.value = true
}

// El reset se ancla a `localCover`, **nunca a `coverSrc`**: aquel depende de los
// dos indicadores, así que un fallo cambiaría `coverSrc`, el watch limpiaría el
// indicador y `coverSrc` volvería a la local — un bucle en el que el escalón a la
// remota y el placeholder no llegan jamás. Se vio en el navegador: las filas de
// serie se quedaban con la imagen rota y el `src` sin moverse de la URL local.
watch(localCover, () => {
  localFailed.value = false
  remoteFailed.value = false
})

const menuRef = ref(null)

const opcionesMenu = computed(() => [
  {
    label: t('journal.edit'),
    icon: 'pi pi-pencil',
    command: () => emit('edit', props.entry)
  },
  {
    label: t('journal.remove'),
    icon: 'pi pi-trash',
    command: () => emit('remove', props.entry)
  }
])

const abrirMenu = (evento) => {
  menuRef.value?.toggle(evento)
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.journal-row {
  display: flex;
  align-items: center;
  gap: spacing(sm);
  padding: spacing(xs) spacing(sm);
  border-radius: radius(md);
  transition: background-color var(--transition-fast);

  &:hover {
    background: var(--color-background-soft);
  }

  &__cover {
    flex: 0 0 auto;
    display: block;
    width: 34px;
    line-height: 0;

    img {
      width: 100%;
      height: auto;
      border-radius: radius(sm);
      object-fit: cover;
    }
  }

  &__cover-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    aspect-ratio: 2 / 3;
    border-radius: radius(sm);
    background: var(--color-background-mute);
    color: var(--color-text-light);
  }

  &__main {
    flex: 1 1 auto;
    display: flex;
    flex-direction: column;
    gap: spacing(3xs);
    // Sin esto un título largo empuja la valoración y el menú fuera del
    // viewport: un hijo de flex no baja de su ancho de contenido por defecto.
    min-width: 0;
  }

  &__title {
    color: var(--color-text);
    font-weight: 600;
    text-decoration: none;
    overflow-wrap: break-word;

    &:hover {
      color: var(--color-primary);
    }
  }

  &__media {
    display: flex;
    align-items: center;
    gap: spacing(3xs);
    color: var(--color-text-light);
    font-size: var(--font-size-sm);
  }

  &__repeat {
    flex: 0 0 auto;
    color: var(--color-text-light);
  }

  &__rating {
    flex: 0 0 auto;
  }

  &__menu {
    @include button-reset;

    flex: 0 0 auto;
    padding: spacing(2xs);
    border-radius: radius(sm);
    color: var(--color-text-light);

    &:hover {
      color: var(--color-text);
      background: var(--color-background-mute);
    }
  }

  // A 360 px la valoración y la etiqueta del medio se llevan el ancho que
  // necesita el título, que es lo único que no se puede recortar.
  @include responsive-below(sm) {
    &__media {
      display: none;
    }

  }
}
</style>
