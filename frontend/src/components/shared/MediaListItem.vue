<template>
  <button
    type="button"
    class="list-item"
    :class="'list-item--' + media"
    :aria-label="`${config.label}: ${title}`"
    @click="handleClick"
  >
    <img
      v-if="coverUrl && !imageError"
      :src="coverUrl"
      :alt="config.label"
      class="list-item__cover"
      :width="cover.width"
      :height="cover.height"
      loading="lazy"
      decoding="async"
      @error="handleImageError"
    >
    <div
      v-else
      class="list-item__cover-placeholder"
    >
      <i :class="config.list.iconOf(item)" />
    </div>

    <div class="list-item__info">
      <!-- Solo películas traen badge: distingue serie de película. -->
      <div
        v-if="badge"
        class="list-item__header"
      >
        <div class="list-item__title">
          {{ title }}
        </div>
        <span
          class="list-item__type-badge"
          :class="badge.modifier"
        >
          <i :class="badge.icon" />
          {{ badge.text }}
        </span>
      </div>
      <div
        v-else
        class="list-item__title"
      >
        {{ title }}
      </div>

      <div class="list-item__subtitle">
        {{ subtitle }}
      </div>

      <!-- Solo juegos traen línea extra: las plataformas. -->
      <div
        v-if="extra"
        class="list-item__extra"
      >
        <i :class="extra.icon" />
        <span>{{ extra.text }}</span>
      </div>

      <div
        v-if="item.user_rating && item.user_rating > 0"
        class="list-item__rating"
      >
        <RatingComponent
          :rating="item.user_rating"
          :readonly="true"
          :size="'small'"
        />
      </div>

      <div
        v-if="item.userStatuses && item.userStatuses.length > 0"
        class="list-item__statuses"
      >
        <span
          v-for="status in item.userStatuses"
          :key="status"
          class="list-item__status-badge"
        >
          {{ getStatusLabel(status) }}
        </span>
      </div>
    </div>

    <i class="fas fa-chevron-right list-item__arrow" />
  </button>
</template>

<script setup>
import { ref, computed } from 'vue';
import RatingComponent from '@/components/common/RatingComponent.vue';
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry';
import CoverService from '@/services/CoverService';

const props = defineProps({
  media: {
    type: String,
    required: true,
    validator: (value) => mediaKeys.includes(value)
  },
  item: {
    type: Object,
    required: true
  },
  allowedStatuses: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['click']);
const imageError = ref(false);

const config = computed(() => getMediaConfig(props.media));

const handleClick = () => emit('click', props.item);

// Dos pasos, no uno: el primer fallo significa «no hay copia local» y se cae a
// la URL remota; solo si esa también falla se pinta el placeholder.
const handleImageError = () => {
  if (!localFailed.value && coverUrl.value !== remoteUrl.value) {
    localFailed.value = true;
    return;
  }
  imageError.value = true;
};

// Esta lista es la biblioteca del usuario (MyLibrary.vue), así que sus portadas
// las sirve el backend desde su copia local en vez del CDN del proveedor: es lo
// que hace que la biblioteca se vea sin salida a internet. La URL remota queda
// de respaldo para los ítems sin fila en `cover_file`.
const localFailed = ref(false);
const remoteUrl = computed(() => config.value.list.coverOf(props.item));

const coverUrl = computed(() => {
  // Sin portada remota no hay fila que pedir: al placeholder, como siempre.
  if (!remoteUrl.value || localFailed.value) {
    return remoteUrl.value;
  }

  return CoverService.localCoverUrl(props.media, config.value.list.idOf(props.item)) || remoteUrl.value;
});
// Dimensiones declaradas del mixin `list-item` de este medio: reservan la caja
// antes de que llegue la portada.
const cover = computed(() => config.value.list.coverAspect);
const title = computed(() => config.value.list.titleOf(props.item));
const subtitle = computed(() => config.value.list.subtitleOf(props.item));

// `badgeOf` y `extraOf` solo existen en los medios que los usan.
const badge = computed(() => config.value.list.badgeOf?.(props.item) ?? null);
const extra = computed(() => config.value.list.extraOf?.(props.item) ?? null);

// De las dos implementaciones que había, se toma la de AlbumListItem: acepta
// `name`, `id` o `key` y cae a `label`, `name` o el propio valor.
const getStatusLabel = (status) => {
  const found = props.allowedStatuses.find(s => s.name === status || s.id === status || s.key === status);
  return found?.label || found?.name || status;
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/list-item' as *;

// La raíz es un <button> para que la tarjeta reciba foco y responda a Enter y
// Espacio sin manejadores propios. `button-reset` le quita el estilo nativo; el
// mixin `list-item` de cada variante vuelve a poner el `display: flex`.
.list-item {
  @include button-reset;
}

// `list-item($entity, $aspect, $width)` se resuelve al compilar: se emiten las
// cinco variantes y `:class` elige la del medio, igual que en MediaNotes.
.list-item--book  { @include list-item('book',  '2/3',  75px); }
.list-item--game  { @include list-item('game',  '1/1',  60px); }
.list-item--album { @include list-item('album', '1/1',  56px); }
.list-item--video { @include list-item('video', '16/9', 56px); }

.list-item--movie {
  @include list-item('movie', '2/3', 75px);

  .list-item__type-badge {
    @include list-item-type-badge;
  }
}
</style>
