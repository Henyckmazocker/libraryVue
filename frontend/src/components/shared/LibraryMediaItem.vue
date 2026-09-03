<template>
  <div :class="`library-${media}-item-container`">
    <!-- Aquí NO se pinta nada del catálogo. Hasta el 2026-09-02 este componente
         repetía la portada, el título y los 4-6 campos de `libraryItem.fields` que
         la cabecera ya enseña dos dedos más arriba. `fields` sigue en el registry:
         lo usa `MediaListItem` para `/library` y `/search`; lo que se fue es su uso
         aquí. Este panel es solo lo TUYO. -->
    <div :class="`${media}-details`">
      <div class="info-text">
        <RatingComponent
          :rating="rating"
          :editable="editable"
          :label="editable ? t('edit.rating') : ''"
          @update:rating="alValorar"
        />

        <!-- Libros meten aquí su barra de progreso de lectura. -->
        <slot name="after-rating" />

        <!-- Editable siempre que el consumidor sepa guardar. Antes era `!isNew`:
             con el ítem ya en tu biblioteca el estado era de solo lectura y había
             que abrir el modal para cambiarlo, que es el paso más frecuente. -->
        <StatusSelector
          v-model="selectedUserStatuses"
          :allowed-statuses="allowedStatuses"
          :multiple="true"
          :readonly="!isNew && !editable"
          :label="isNew ? t('edit.addWithStatus') : cfg.statusLabel"
          :subtitle="isNew || editable ? '' : t('edit.readOnlyHint')"
          @update:model-value="alCambiarEstados"
        />

        <!-- Libros meten aquí su widget de estado de lectura. -->
        <slot name="after-status" />

        <!-- Libros y películas sacan el formato como `<p>` suelto; los otros
             tres lo agrupan en un bloque de solo lectura. -->
        <template v-if="!cfg.extrasWrapped">
          <p
            v-for="extra in visibleExtras"
            :key="extra.label"
            :class="extra.cls"
          >
            <strong>{{ extra.label }}:</strong>
            <span
              v-if="extra.badge"
              class="ownership-format-badge"
            >{{ extra.text }}</span>
            <template v-else>
              {{ ' ' + extra.text }}
            </template>
          </p>
        </template>
        <div
          v-else-if="visibleExtras.length > 0"
          :class="[`${media}-specific-fields`, 'readonly-fields']"
        >
          <p
            v-for="extra in visibleExtras"
            :key="extra.label"
            :class="extra.cls"
          >
            <strong>{{ extra.label }}:</strong>
            <span
              v-if="extra.badge"
              class="ownership-format-badge"
            >{{ extra.text }}</span>
            <template v-else>
              {{ ' ' + extra.text }}
            </template>
          </p>
        </div>

        <!-- Guardar, editar y eliminar se fueron a la barra de la ficha el
             2026-09-02: eran la acción principal escondida al final del panel,
             debajo de la sinopsis. Aquí queda lo que es propio de un medio y no
             de la barra: hoy solo el historial de libros. -->
        <div
          v-if="visibleExtraActions.length > 0"
          :class="`${media}-actions`"
        >
          <button
            v-for="action in visibleExtraActions"
            :key="action.event"
            :class="['btn', 'action-button', action.cls]"
            :title="action.title"
            @click="emit(action.event, item)"
          >
            <i :class="action.icon" />
            <span>{{ action.label }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import RatingComponent from '@/components/common/RatingComponent.vue'
import StatusSelector from '@/components/common/StatusSelector.vue'
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import Logger from '@/utils/logger'
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

/**
 * Ficha de biblioteca única para los cinco medios.
 *
 * Sustituye a LibraryBookItem / LibraryMovieItem / LibraryGameItem /
 * LibraryAlbumItem / LibraryVideoItem, que quedan como wrappers traduciendo su
 * contrato viejo. Todo lo que cambiaba entre ellos —los campos, los textos, el
 * estado por defecto, la forma de los payloads— sale de `mediaRegistry`.
 *
 * **Aquí no hay ni un botón de la barra**: guardar, editar y eliminar viven en
 * `MediaDetailView`, que es quien tiene el store. De guardar solo queda `guardar()`,
 * expuesto para que el CTA lo dispare, porque el payload necesita los estados y la
 * valoración que se eligen en este panel.
 */
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
  },
  isNew: {
    type: Boolean,
    default: false
  },
  /**
   * Si el consumidor sabe guardar al vuelo. Con esto, la valoración y el estado se
   * cambian aquí mismo sin abrir el modal; sin esto, el panel se comporta como
   * siempre. Lo pone `MediaDetailView` cuando su wrapper le pasó `onStatus`/`onRate`.
   */
  editable: {
    type: Boolean,
    default: false
  }
})

// Los tres `show-*` son los `event` de `extraActions`. Van declarados uno a uno y no
// como un `extra-action` genérico porque el consumidor de cada uno es distinto —el
// historial abre un modal de libro, las ediciones otro, las temporadas otro— y un
// emit sin declarar suelta un warning de Vue que no rompe nada y no ve nadie.
const emit = defineEmits(['save', 'show-history', 'show-editions', 'show-seasons', 'rate', 'set-statuses'])

// El componente NO guarda: emite y deja que `MediaDetailView` aplique las guardas y
// llame al wrapper. Aquí no hay ni store ni composable, y así sigue.
const alValorar = (valor) => { if (props.editable) emit('rate', valor) }
const alCambiarEstados = (estados) => {
  if (props.editable && !props.isNew) emit('set-statuses', estados)
}

const config = computed(() => getMediaConfig(props.media))
const cfg = computed(() => config.value.libraryItem)

/** Un campo se pinta si tiene valor, salvo los marcados `always`. */
const resolve = (defs) => defs
  .map((def) => ({ ...def, text: def.value(props.item) }))
  .filter((def) => def.always || (def.text !== null && def.text !== undefined && def.text !== '' && def.text !== 0))

const visibleExtras = computed(() => resolve(cfg.value.extras || []))
// Dos filtros y no uno: `onlyExisting` mira el estado en tu biblioteca y `when`
// mira el ítem. Un libro sin `work_key` no tiene ediciones que ofrecer aunque esté
// guardado, y una serie sin `totalSeasons` no tiene temporadas que seguir.
const visibleExtraActions = computed(() => (cfg.value.extraActions || [])
  .filter((action) => (action.onlyExisting ? !props.isNew : true))
  .filter((action) => (action.when ? action.when(props.item) : true)))

// ─── Estado local ────────────────────────────────────────────────────────
const initialStatuses = () => {
  if (Array.isArray(props.item?.userStatuses) && props.item.userStatuses.length > 0) {
    return [...props.item.userStatuses]
  }
  const fallback = cfg.value.defaultStatus
  return fallback && props.allowedStatuses.includes(fallback) ? [fallback] : []
}

const rating = ref(props.item?.user_rating ?? cfg.value.ratingFallback)
const selectedUserStatuses = ref(initialStatuses())

// Los estados se recalculan solo cuando cambia el ítem **de verdad**, no en
// cada mutación: las fichas de detalle reemplazan el objeto al enriquecerlo en
// segundo plano, y un watch profundo borraría lo que el usuario acabe de elegir.
watch(() => cfg.value.idOf(props.item), () => {
  selectedUserStatuses.value = initialStatuses()
})

watch(() => props.item?.user_rating, (value) => {
  rating.value = value ?? cfg.value.ratingFallback
})

// Los estados permitidos llegan después del primer render (la ficha de detalle
// los pide en su `loadData`), y sin esto el valor por defecto `owned` no se
// preseleccionaba nunca al entrar con datos ya cargados. Solo se aplica si el
// usuario no ha elegido todavía, para no pisar su selección.
watch(() => props.allowedStatuses, () => {
  if (selectedUserStatuses.value.length === 0) {
    selectedUserStatuses.value = initialStatuses()
  }
})

// ─── Acciones ────────────────────────────────────────────────────────────
/** Los juegos añaden sus campos propios al ítem antes de emitirlo. */
const payloadItem = () => (cfg.value.withOwnFields ? cfg.value.withOwnFields(props.item) : props.item)

/**
 * Arma el payload del alta y lo emite. Lo dispara el CTA de la barra, que es
 * donde vive hoy el botón; el payload se sigue armando aquí porque los estados y
 * la valoración elegidos son de este panel.
 */
function guardar () {
  Logger.debug(`[LibraryMediaItem] Saving ${props.media}`)
  emit('save', cfg.value.savePayload(payloadItem(), [...selectedUserStatuses.value], rating.value))
}

defineExpose({ guardar })
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/library-item' as *;

// `library-item($variant, $aspect, $size, $entity)` se resuelve al compilar: se
// emiten las cinco variantes y `:class` elige la del medio, igual que en
// MediaListItem y MediaNotes.
.library-book-item-container  { @include library-item('book',  '2/3',  80px,  'book'); }
.library-movie-item-container { @include library-item('movie', '2/3',  80px,  'movie'); }
.library-game-item-container  { @include library-item('game',  '2/3',  80px,  'game'); }
.library-album-item-container { @include library-item('album', '1/1',  120px, 'album'); }
.library-video-item-container { @include library-item('video', '16/9', 120px, 'video'); }
// Las series toman el color de las películas y sus propios nombres de clase,
// igual que hace `detail-view-page('movie', 'series')` en la ficha de detalle.
.library-series-item-container { @include library-item('movie', '2/3', 80px, 'series'); }
</style>
