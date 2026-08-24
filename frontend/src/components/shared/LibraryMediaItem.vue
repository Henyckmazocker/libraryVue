<template>
  <div :class="`library-${media}-item-container`">
    <div :class="`${media}-details`">
      <div
        v-if="coverUrl"
        class="cover-image-container"
      >
        <img
          :src="coverUrl"
          :alt="cfg.coverAlt"
          class="cover-image"
          :width="cfg.coverAspect.width"
          :height="cfg.coverAspect.height"
          loading="lazy"
          decoding="async"
          @error="onCoverError"
        >
      </div>

      <div class="info-text">
        <h3 :class="`${media}-title`">
          {{ title }}
        </h3>

        <p
          v-for="field in visibleFields"
          :key="field.cls"
          :class="field.cls"
        >
          <strong>{{ field.label }}:</strong>
          <span
            v-if="field.valueClass"
            :class="field.valueClass(item)"
          >{{ field.text }}</span>
          <template v-else>
            {{ ' ' + field.text }}
          </template>
        </p>

        <RatingComponent
          :rating="rating"
          :editable="false"
        />

        <!-- Libros meten aquí su barra de progreso de lectura. -->
        <slot name="after-rating" />

        <StatusSelector
          v-model="selectedUserStatuses"
          :allowed-statuses="allowedStatuses"
          :multiple="true"
          :readonly="!isNew"
          :label="isNew ? 'Añadir con estado' : cfg.statusLabel"
          :subtitle="isNew ? '' : '(solo lectura - usa el modal para editar)'"
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

        <div :class="`${media}-actions`">
          <button
            v-if="isNew"
            :class="['action-button', 'save-button', `save-button--${saveButtonState}`]"
            :disabled="!canSave"
            :title="`Guardar ${config.label.toLowerCase()}`"
            @click="onSave"
          >
            <i
              v-if="saveButtonState === 'idle'"
              class="fas fa-save"
            />
            <i
              v-else-if="saveButtonState === 'success'"
              class="fas fa-check"
            />
            <i
              v-else-if="saveButtonState === 'error'"
              class="fas fa-times"
            />
            <span>Guardar</span>
          </button>

          <!-- Acciones propias de un medio: hoy solo el historial de libros. -->
          <button
            v-for="action in visibleExtraActions"
            :key="action.event"
            :class="['action-button', action.cls]"
            :title="action.title"
            @click="emit(action.event, item)"
          >
            <i :class="action.icon" />
            <span>{{ action.label }}</span>
          </button>

          <button
            v-if="!isNew"
            :class="['action-button', 'edit-button', `edit-button--${editButtonState}`]"
            :disabled="editButtonState !== 'idle'"
            :title="`Editar ${config.label.toLowerCase()}`"
            @click="onEdit"
          >
            <i
              v-if="editButtonState === 'idle'"
              class="fas fa-pencil-alt"
            />
            <i
              v-else-if="editButtonState === 'success'"
              class="fas fa-check"
            />
            <i
              v-else-if="editButtonState === 'error'"
              class="fas fa-times"
            />
            <span>Editar</span>
          </button>

          <button
            v-if="!isNew && canDelete"
            class="action-button delete-button"
            :title="`Eliminar ${config.label.toLowerCase()}`"
            @click="onDelete"
          >
            <i class="fas fa-trash" />
            <span>Eliminar</span>
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
import CoverService from '@/services/CoverService'
import Logger from '@/utils/logger'

/**
 * Ficha de biblioteca única para los cinco medios.
 *
 * Sustituye a LibraryBookItem / LibraryMovieItem / LibraryGameItem /
 * LibraryAlbumItem / LibraryVideoItem, que quedan como wrappers traduciendo su
 * contrato viejo. Todo lo que cambiaba entre ellos —los campos, los textos, el
 * estado por defecto, la forma de los payloads— sale de `mediaRegistry`.
 *
 * **El guardado va siempre en modo «el padre confirma»**: el componente expone
 * setSaveSuccess/setSaveError/setEditSuccess/setEditError y el padre los llama
 * tras la respuesta del backend. Antes, álbumes y vídeos se daban el guardado
 * por bueno solos y los otros tres esperaban al padre.
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
  canDelete: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['save', 'edit', 'delete', 'show-history'])

const config = computed(() => getMediaConfig(props.media))
const cfg = computed(() => config.value.libraryItem)

// La portada la sirve el backend desde su copia local, no el CDN del proveedor:
// es lo que hace que la biblioteca se vea sin salida a internet. `remoteUrl` es
// el respaldo, y se usa cuando el ítem no tiene fila en `cover_file` (guardado
// antes de que existiera esto y sin sembrar) o cuando la imagen no carga.
const remoteUrl = computed(() => cfg.value.coverOf(props.item))
const localFailed = ref(false)

const coverUrl = computed(() => {
  // Sin portada remota no hay fila en `cover_file` y no hay nada que servir: el
  // hueco se queda vacío, igual que antes de existir el endpoint.
  if (!remoteUrl.value || localFailed.value) {
    return remoteUrl.value
  }

  return CoverService.localCoverUrl(props.media, cfg.value.idOf(props.item)) || remoteUrl.value
})

/** Un 404 del endpoint significa «no hay fila»: a la URL de siempre. */
const onCoverError = () => {
  localFailed.value = true
}
const title = computed(() => cfg.value.titleOf(props.item))

/** Un campo se pinta si tiene valor, salvo los marcados `always`. */
const resolve = (defs) => defs
  .map((def) => ({ ...def, text: def.value(props.item) }))
  .filter((def) => def.always || (def.text !== null && def.text !== undefined && def.text !== '' && def.text !== 0))

const visibleFields = computed(() => resolve(cfg.value.fields))
const visibleExtras = computed(() => resolve(cfg.value.extras || []))
const visibleExtraActions = computed(() => (cfg.value.extraActions || [])
  .filter((action) => (action.onlyExisting ? !props.isNew : true)))

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
const saveButtonState = ref('idle')
const editButtonState = ref('idle')

const canSave = computed(() => saveButtonState.value === 'idle')

// Los estados se recalculan solo cuando cambia el ítem **de verdad**, no en
// cada mutación: las fichas de detalle reemplazan el objeto al enriquecerlo en
// segundo plano, y un watch profundo borraría lo que el usuario acabe de elegir.
watch(() => cfg.value.idOf(props.item), () => {
  selectedUserStatuses.value = initialStatuses()
  localFailed.value = false
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

function onSave () {
  Logger.debug(`[LibraryMediaItem] Saving ${props.media}`)
  saveButtonState.value = 'idle'
  emit('save', cfg.value.savePayload(payloadItem(), [...selectedUserStatuses.value], rating.value))
}

function onEdit () {
  const item = cfg.value.withOwnFields
    ? { ...cfg.value.withOwnFields(props.item), user_rating: rating.value }
    : props.item
  emit('edit', ...cfg.value.editPayload(item))
}

function onDelete () {
  Logger.debug(`[LibraryMediaItem] Deleting ${props.media}`)
  emit('delete', cfg.value.deletePayload(props.item))
}

// ─── Feedback que confirma el padre ──────────────────────────────────────
const flash = (state, value, ms) => {
  state.value = value
  setTimeout(() => { state.value = 'idle' }, ms)
}

const setSaveSuccess = () => flash(saveButtonState, 'success', 2000)
const setSaveError = () => flash(saveButtonState, 'error', 2000)
const setEditSuccess = () => flash(editButtonState, 'success', 2000)
const setEditError = () => flash(editButtonState, 'error', 2000)

defineExpose({ setSaveSuccess, setSaveError, setEditSuccess, setEditError })
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
