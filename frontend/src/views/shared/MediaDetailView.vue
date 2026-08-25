<template>
  <div :class="`${media}-detail-view`">
    <button
      class="back-button"
      @click="goBack"
    >
      <i class="fas fa-arrow-left" />
      <span>{{ d.backText }}</span>
    </button>

    <MediaSkeleton
      v-if="isLoading"
      variant="detail"
      :label="d.loadingText"
    />

    <div
      v-else-if="error"
      class="error-state"
    >
      <i class="fas fa-exclamation-circle" />
      <p>{{ error }}</p>
      <button
        class="action-button"
        @click="goBack"
      >
        {{ d.backText }}
      </button>
    </div>

    <div
      v-else-if="item"
      :class="`${media}-detail-content`"
    >
      <div :class="`${media}-header`">
        <!-- Película y serie llaman «poster» a lo que el resto llama «cover». -->
        <div :class="d.coverClass || `${media}-cover-large`">
          <img
            v-if="coverUrl && !imageError"
            :src="coverUrl"
            :alt="title"
            :class="d.coverImageClass || 'cover-image-large'"
            :width="d.coverAspect.width"
            :height="d.coverAspect.height"
            loading="lazy"
            decoding="async"
            @error="handleImageError"
          >
          <div
            v-else
            :class="d.placeholderClass || 'cover-placeholder'"
          >
            <i :class="d.placeholderIcon" />
          </div>
          <!-- Los vídeos superponen aquí su botón de reproducción. -->
          <slot
            name="cover-overlay"
            :item="item"
          />
        </div>

        <div :class="`${media}-main-info`">
          <!-- Los álbumes ponen su badge de tipo por encima del título. -->
          <slot
            name="meta-top"
            :item="item"
          />
          <h1 :class="`${media}-title-large`">
            {{ title }}
          </h1>
          <!-- La columna de datos, que es lo que de verdad cambia por medio. -->
          <slot
            name="meta"
            :item="item"
            :context="context"
          />
        </div>
      </div>

      <!-- Las secciones propias del medio: sinopsis, pistas, capturas… -->
      <slot
        name="extra"
        :item="item"
        :context="context"
        :existing="existing"
      />

      <div
        v-if="d.divider"
        class="section-divider"
      />

      <!-- Vídeo, álbum y juego usan `.library-section` con un `<h2>` pelado;
           película, serie y libro, `.library-form-section` con icono. -->
      <div :class="d.librarySectionClass">
        <h2
          v-if="d.libraryTitleIcon"
          class="section-title"
        >
          <i :class="['fas', existing ? 'fa-edit' : 'fa-save']" />
          {{ existing ? d.libraryTitleExisting : d.libraryTitleNew }}
        </h2>
        <h2 v-else>
          {{ existing ? d.libraryTitleExisting : d.libraryTitleNew }}
        </h2>
        <LibraryMediaItem
          ref="libraryItemRef"
          :media="d.libraryMedia || media"
          :item="itemForLibrary"
          :allowed-statuses="allowedStatuses"
          :is-new="!existing"
          :can-delete="!!existing"
          @save="handleSave"
          @edit="handleEdit"
          @delete="handleDelete"
          @show-history="(payload) => emit('show-history', payload)"
        >
          <template #after-rating>
            <slot
              name="library-after-rating"
              :item="itemForLibrary"
            />
          </template>
          <template #after-status>
            <slot
              name="library-after-status"
              :item="itemForLibrary"
            />
          </template>
        </LibraryMediaItem>
      </div>

      <div
        v-if="d.hasNotes && existing"
        class="notes-section"
      >
        <MediaNotes
          :media="media"
          :item-id="notesId"
        />
      </div>

      <!-- Atribución del proveedor. La exigen las condiciones de uso de TMDB
           en cualquier pantalla que muestre datos suyos, así que no se quita. -->
      <footer
        v-if="d.attribution"
        class="provider-attribution"
      >
        <a
          :href="d.attribution.href"
          target="_blank"
          rel="noopener noreferrer"
        >
          <img
            :src="d.attribution.logo"
            :alt="d.attribution.alt"
            class="provider-attribution__logo"
            loading="lazy"
          >
        </a>
        <p class="provider-attribution__text">
          {{ d.attribution.text }}
        </p>
      </footer>
    </div>

    <div
      v-else
      class="empty-state"
    >
      <i :class="d.placeholderIcon" />
      <p>{{ d.emptyText }}</p>
      <button
        class="action-button"
        @click="goBack"
      >
        {{ d.backText }}
      </button>
    </div>

    <EditItemModal
      v-if="editModal.isVisible"
      :item="editModal.item"
      :item-type="d.libraryMedia || media"
      :allowed-statuses="allowedStatuses"
      :is-visible="editModal.isVisible"
      v-bind="modalExtraProps"
      @close="closeEditModal"
      @saved="handleModalSaved"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, toRaw } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import LibraryMediaItem from '@/components/shared/LibraryMediaItem.vue'
import MediaNotes from '@/components/shared/MediaNotes.vue'
import MediaSkeleton from '@/components/shared/MediaSkeleton.vue'
import EditItemModal from '@/components/EditItemModal.vue'
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import CoverService from '@/services/CoverService'
import { useAuthStore } from '@/store/auth'
import { useUIStore } from '@/store/ui'
import Logger from '@/utils/logger'

/**
 * Ficha de detalle única para los cinco medios.
 *
 * Se queda con todo lo que las seis vistas repetían: el botón de volver, los
 * tres estados (cargando / error / vacío), la carcasa de la cabecera, el
 * formulario de biblioteca con su `LibraryMediaItem`, el modal de edición, las
 * notas y el ciclo de vida completo —incluido el patrón «datos eager por
 * `history.state`, enriquecimiento en segundo plano»—.
 *
 * Lo que de verdad cambia por medio va por dos vías: lo declarativo, al bloque
 * `detail` de `mediaRegistry` (textos, ruta de vuelta, cómo se busca el ítem en
 * el store, qué se enriquece y qué campos se mezclan); y lo visual, a los slots
 * `#meta` y `#extra`, que rellena el wrapper de cada medio.
 *
 * Las clases de los estados se unifican en `loading-state` / `error-state` /
 * `action-button`: el mixin `detail-view-page` ya estilizaba por igual las dos
 * convenciones que convivían (`_detail-view.scss:61-65,273-274`), así que no
 * hay cambio visual.
 */
const props = defineProps({
  media: {
    type: String,
    required: true,
    validator: (value) => mediaKeys.includes(value)
  },
  /** Store del medio, ya instanciado por el wrapper. */
  store: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['show-history', 'loaded'])

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const uiStore = useUIStore()

const config = computed(() => getMediaConfig(props.media))
const d = computed(() => config.value.detail)

// ─── Estado ──────────────────────────────────────────────────────────────
const stateKey = getMediaConfig(props.media).detail.stateKey
const item = ref(history.state?.[stateKey] ?? null)
// Con datos eager no se enseña el spinner: la transición es continua.
const isLoading = ref(!item.value)
const error = ref(null)
const allowedStatuses = ref([])
const context = ref({})
const libraryItemRef = ref(null)
const editModal = ref({ isVisible: false, item: null })
// Una portada que no carga pinta el placeholder del medio en vez de dejar el
// icono de imagen rota del navegador.
const imageError = ref(false)

const isAuthenticated = computed(() => authStore.isAuthenticated)
const routeId = computed(() => route.params[d.value.routeParam])

const existing = computed(() => (item.value ? d.value.existingOf(props.store, item.value, routeId.value) : null))

const itemForLibrary = computed(() => {
  if (!existing.value) return item.value ?? {}
  return d.value.mergeForLibrary
    ? d.value.mergeForLibrary(item.value, existing.value)
    : { ...item.value, ...existing.value }
})

// La ficha de detalle puede tener su propia portada: los juegos caen a
// `background_image` cuando IGDB no manda `coverUrl`.
const remoteCoverUrl = computed(() => {
  if (!item.value) return null
  const of = d.value.coverOf ?? config.value.libraryItem.coverOf
  return of(item.value)
})

// La clave con la que el backend registró la portada de este ítem, o `null` si
// no hay copia local que pedir. Sale de `existing` —la fila de la biblioteca—,
// no de `item`: lo que llega de la búsqueda tiene otra forma y no está
// guardado, así que pedirlo daría un 404. Y es un `computed` a propósito:
// `existing` no está en el primer render, así que cachearlo en un `ref` o
// calcularlo en `onMounted` dejaría la ficha con la URL remota para siempre.
const coverKey = computed(() =>
  (existing.value ? config.value.libraryItem.idOf(existing.value) ?? null : null)
)

// Mismo escalón doble que `MediaListItem`: la copia local primero, la URL del
// CDN si esa falla, y solo entonces el placeholder. Así el peor caso es lo que
// ya se veía antes de este cambio.
const localFailed = ref(false)

// Leído en frío parece un error, y no lo es: la serie pide su portada como si
// fuera una película. El backend guarda las series con `AddMovieUseCase`, así
// que su fila de `cover_file` lleva `media_type = 'movie'`. `cover.php:36`
// acepta `'series'` como medio válido, pero no hay ni una fila con ese
// `media_type`: medido el 2026-08-25, `?cover=series/tt0386676` responde 404 y
// `?cover=movie/tt0386676` responde 200 con la misma imagen.
// `coverKey` no necesita rama equivalente: `mediaRegistry.js:1488` le asigna a
// `series` el `libraryItem` de `movie`, así que su `idOf` ya es `i => i.imdbID`.
const coverMedia = computed(() => (props.media === 'series' ? 'movie' : props.media))

const coverUrl = computed(() => {
  if (!remoteCoverUrl.value || localFailed.value) return remoteCoverUrl.value
  return CoverService.localCoverUrl(coverMedia.value, coverKey.value) || remoteCoverUrl.value
})

const handleImageError = () => {
  if (!localFailed.value && coverUrl.value !== remoteCoverUrl.value) {
    localFailed.value = true
    return
  }
  imageError.value = true
}

// `title` no siempre está: álbumes y juegos caen a `name`.
const title = computed(() => (item.value ? config.value.libraryItem.titleOf(item.value) : ''))
const notesId = computed(() => config.value.libraryItem.idOf(existing.value ?? item.value ?? {}) ?? routeId.value)

// Los álbumes pasan además sus pistas al modal (`:album-tracks`).
const modalExtraProps = computed(() => (d.value.modalProps ? d.value.modalProps(context.value) : {}))

// ─── Navegación ──────────────────────────────────────────────────────────
function goBack () {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push({ name: d.value.backRoute })
  }
}

// ─── Carga ───────────────────────────────────────────────────────────────
/** Mezcla en el ítem lo que el usuario ya tiene guardado de él. */
function mergeExisting () {
  if (!existing.value || !item.value) return
  Logger.debug(`[MediaDetailView] Merging ${props.media} with library data`)
  item.value = { ...item.value, ...d.value.mergeFields(existing.value) }
}

async function loadData () {
  const hasEagerData = !!item.value
  if (hasEagerData) isLoading.value = false

  await Promise.all([
    props.store.items.length === 0 ? props.store.fetch() : Promise.resolve(),
    props.store.allowedStatuses.length === 0 ? props.store.fetchAllowedStatuses() : Promise.resolve()
  ])
  const todos = d.value.statusesAsNames
    ? props.store.allowedStatuses.map((s) => (typeof s === 'object' && s !== null ? s.name : s))
    : props.store.allowedStatuses
  // Películas y series comparten estados en el backend pero no los enseñan
  // todos: cada una descarta los de la otra.
  allowedStatuses.value = d.value.allowedStatusesFilter
    ? d.value.allowedStatusesFilter(todos)
    : todos

  if (!d.value.enrich) {
    // Medios sin API externa: el ítem sale del propio store.
    if (!item.value && routeId.value) {
      const found = d.value.existingOf(props.store, {}, routeId.value)
      if (found) item.value = found
      else error.value = d.value.notFoundText
      isLoading.value = false
    }
    mergeExisting()
    emit('loaded', item.value)
    return
  }

  const enriching = enrich(hasEagerData)
  if (!hasEagerData) await enriching
  mergeExisting()
  emit('loaded', item.value)
}

/** Trae la ficha completa de la API externa del medio. */
async function enrich (isBackground) {
  if (!isBackground) isLoading.value = true
  error.value = null

  try {
    const result = await d.value.enrich(routeId.value, authStore.apiCall.bind(authStore), item.value)
    if (result?.item) {
      item.value = result.item
      context.value = result.context ?? {}
      mergeExisting()
    } else if (!isBackground) {
      error.value = d.value.notFoundText
    }
  } catch (err) {
    Logger.error(`[MediaDetailView] Error enriching ${props.media}:`, err)
    if (!isBackground) error.value = d.value.errorText
  } finally {
    if (!isBackground) isLoading.value = false
  }
}

// ─── Guardar, editar y borrar ────────────────────────────────────────────
async function handleSave (payload) {
  try {
    const [data, statuses] = d.value.unwrapSave(payload)
    const result = await props.store.add(data, statuses)

    if (result.success) {
      if (item.value) item.value = { ...item.value, userStatuses: statuses }
      await props.store.fetch()
      libraryItemRef.value?.setSaveSuccess()
    } else {
      Logger.error(`[MediaDetailView] Error saving ${props.media}:`, result.message)
      libraryItemRef.value?.setSaveError()
    }
  } catch (err) {
    Logger.error(`[MediaDetailView] Error saving ${props.media}:`, err)
    libraryItemRef.value?.setSaveError()
  }
}

async function handleEdit () {
  // El store puede no estar cargado todavía: con datos eager, `loadData` corre
  // en segundo plano y el usuario puede pulsar Editar antes de que termine.
  if (props.store.items.length === 0) await props.store.fetch()

  const stored = existing.value ? toRaw(existing.value) : null
  editModal.value = {
    isVisible: true,
    item: stored ? d.value.itemForModal(item.value, stored) : item.value
  }
}

function closeEditModal () {
  editModal.value = { isVisible: false, item: null }
}

async function handleModalSaved (updatedItem) {
  closeEditModal()

  try {
    if (item.value && updatedItem) {
      item.value = { ...item.value, ...updatedItem }
    }
    const stored = existing.value
    if (stored) Object.assign(stored, updatedItem)

    libraryItemRef.value?.setEditSuccess()
    if (d.value.savedMessage) uiStore.showSuccess(d.value.savedMessage)

    // Resincronizar con el backend sin bloquear la interfaz.
    setTimeout(() => {
      props.store.fetch().catch((err) =>
        Logger.error('[MediaDetailView] Background refresh failed:', err))
    }, 500)
  } catch (err) {
    Logger.error(`[MediaDetailView] Error updating ${props.media}:`, err)
    libraryItemRef.value?.setEditError()
  }
}

async function handleDelete (payload) {
  if (d.value.deleteConfirm && !confirm(d.value.deleteConfirm)) return

  try {
    const result = await props.store.remove(d.value.unwrapDelete(payload))
    if (result.success) {
      if (d.value.deletedMessage) uiStore.showSuccess(d.value.deletedMessage)
      goBack()
    } else if (d.value.deleteErrorMessage) {
      uiStore.showError(d.value.deleteErrorMessage)
    }
  } catch (err) {
    Logger.error(`[MediaDetailView] Error deleting ${props.media}:`, err)
    if (d.value.deleteErrorMessage) uiStore.showError(d.value.deleteErrorMessage)
  }
}

// ─── Ciclo de vida ───────────────────────────────────────────────────────
onMounted(async () => {
  if (isAuthenticated.value) await loadData()
})

watch(isAuthenticated, async (value) => {
  if (value && !item.value) await loadData()
})

// La vista se reutiliza al cambiar de ítem —la ficha de libro cambia de edición
// con `setItem`—, así que el fallo de la portada anterior no puede quedarse
// pegado: sin esto, la edición nueva se pintaría con el placeholder.
//
// Se vigila la portada, no `item`: el enriquecimiento muta `item` a los pocos
// milisegundos de montar (`mergeExisting`), y colgar el reset de ahí anulaba un
// fallback legítimo recién decidido —la ficha volvía a pedir la copia local que
// acababa de dar 404 y gastaba una segunda petición fallida—. Cuando lo que
// cambia es la imagen de verdad, `remoteCoverUrl` cambia con ella.
watch(remoteCoverUrl, () => {
  imageError.value = false
  localFailed.value = false
})

/** Reemplaza el ítem cargado. Lo usa la ficha de libro al cambiar de edición. */
function setItem (nuevo) {
  item.value = nuevo
}

defineExpose({ item, context, existing, reload: loadData, setItem })
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/detail-view' as *;

/**
 * El CSS con `scoped` del wrapper alcanza la raíz de este componente y el
 * contenido de sus slots, pero **no el marcado que pinta este fichero**: el
 * botón de volver, los tres estados, la cabecera y la portada se quedaban sin
 * estilo. Por eso el mixin se emite también aquí, en sus cinco variantes, igual
 * que hacen MediaListItem y LibraryMediaItem.
 *
 * Los wrappers conservan su `@include detail-view-page(...)` porque el mismo
 * mixin estiliza además clases que viven en los slots (`.metadata-item`,
 * `.section-title`, `.genre-tag`, `.platform-tag`, `.external-link`).
 */
.book-detail-view   { @include detail-view-page('book'); }
.movie-detail-view  { @include detail-view-page('movie'); }
.game-detail-view   { @include detail-view-page('game'); }
.album-detail-view  { @include detail-view-page('album'); }
.video-detail-view  { @include detail-view-page('video'); }

// Las secciones que pinta este componente, no el wrapper.
.library-section,
.library-form-section,
.notes-section {
  @include detail-section-card;
}

// ── Atribución del proveedor (TMDB) ───────────────────────────────────────
// Discreta pero legible: es un requisito de uso de la API, no una firma.
.provider-attribution {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid var(--color-border-light);
  text-align: center;

  &__logo {
    width: 6.5rem;
    height: auto;
  }

  &__text {
    margin: 0;
    max-width: 32rem;
    font-size: 0.8rem;
    color: var(--color-text-muted);
  }
}

// ── Portadas: cada medio tiene su tamaño y su relleno ──────────────────────
.video-detail-view {
  .video-cover-large {
    position: relative; // CRITICAL: contiene el youtube-play-btn inset:0
    flex-shrink: 0;
    width: 320px;

    @include responsive-below(md) {
      width: 100%;
      max-width: 480px;
      margin: 0 auto;
    }
  }

  .cover-placeholder {
    aspect-ratio: 16 / 9;
    background: linear-gradient(135deg, var(--color-card-video-bg) 0%, var(--color-card-video-border) 100%);
    border: none;

    i {
      font-size: 4rem;
      color: var(--color-card-video-accent);
    }
  }
}

.album-detail-view {
  .album-cover-large {
    flex-shrink: 0;
    width: 240px;
    height: 240px;
    border-radius: radius(md);
    overflow: hidden;
    box-shadow: shadow(heavy);

    @include responsive-below(md) {
      width: 180px;
      height: 180px;
    }
  }

  .cover-placeholder {
    aspect-ratio: 1 / 1;
    background: var(--color-background-soft);
    border: none;
    font-size: 4rem;
    color: var(--color-text-muted);
  }
}

.movie-detail-view {
  .movie-poster-large {
    flex-shrink: 0;
    width: 220px;
  }

  .poster-placeholder {
    width: 220px;
    height: 330px;

    @include responsive-below(md) {
      width: 100%;
      max-width: 250px;
      height: auto;
      margin: 0 auto;
    }
  }
}

.series-detail-view {
  @include detail-view-page('movie', 'series');

  .series-poster-large {
    flex-shrink: 0;
    width: 220px;
  }

  .poster-placeholder {
    background: rgba(139, 92, 246, 0.15);
    border: 2px dashed rgba(139, 92, 246, 0.3);
    color: rgba(139, 92, 246, 0.4);
    font-size: 3rem;

    @include responsive-below(md) {
      width: 100%;
      max-width: 250px;
      margin: 0 auto;
    }
  }
}

.game-detail-view {
  .game-cover-large {
    flex-shrink: 0;
    width: 280px;

    @include responsive-below(md) {
      width: 100%;
      max-width: 250px;
      margin: 0 auto;
    }
  }

  .cover-placeholder {
    aspect-ratio: 3 / 4;
    background: linear-gradient(135deg, var(--color-card-movie-accent) 0%, var(--color-card-movie-accent) 100%);
    border: none;
    color: white;
    font-size: 4rem;
  }
}
</style>
