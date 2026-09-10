<template>
  <div :class="`${media}-detail-view`">
    <!-- Volver a la izquierda; a la derecha la acción que se viene a hacer y,
         detrás de `⋯`, las tres colaterales. Recomendar, añadir a una lista y
         ponerlo en un club estaban aquí como botones de igual peso que volver:
         eran cuatro decisiones antes de ver la ficha. -->
    <div class="detail-actions">
      <button
        class="btn btn--ghost back-button"
        @click="goBack"
      >
        <i
          class="fas fa-arrow-left"
          aria-hidden="true"
        />
        <!-- El rótulo se oculta a la vista por debajo de `sm` sin dejar de
             existir para el lector: es lo que deja sitio al CTA en una fila. -->
        <span class="back-button__text">{{ d.backText }}</span>
      </button>

      <div
        v-if="item"
        class="detail-actions__end"
      >
        <button
          class="btn btn--primary detail-cta"
          :class="{
            'is-success': ctaState === 'success',
            'is-error': ctaState === 'error'
          }"
          :disabled="ctaState !== 'idle'"
          @click="onCta"
        >
          <i
            :class="ctaIcon"
            aria-hidden="true"
          />
          <span>{{ ctaLabel }}</span>
        </button>

        <!-- Solo con sesión: las tres acciones del menú la exigen —`send_recommendation`
             pide amistad y las de listas y clubs llevan `Auth`—, así que sin ella el
             menú se quedaría vacío y `menuItems` lo devuelve vacío. -->
        <template v-if="menuItems.length > 0">
          <button
            class="btn btn--ghost btn--icon detail-more"
            aria-haspopup="true"
            :aria-controls="menuId"
            @click="abrirMenu"
          >
            <i
              class="fas fa-ellipsis-h"
              aria-hidden="true"
            />
            <span class="u-sr-only">{{ t('edit.moreActions') }}</span>
          </button>
          <Menu
            :id="menuId"
            ref="menuRef"
            :model="menuItems"
            :popup="true"
          />
        </template>
      </div>
    </div>

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
        class="btn btn--ghost"
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

      <!-- Dos columnas en ≥lg: el catálogo a la izquierda y lo tuyo a la derecha,
           pegajoso. En el DOM el panel va **antes** que `#extra` a propósito: en
           móvil no hay rejilla y se lee en ese orden —lo tuyo primero, que es lo
           que hoy queda enterrado bajo la sinopsis—, y así el orden de lectura y
           el de tabulación coinciden. Con `order` no coincidirían. En ≥lg la
           rejilla los recoloca con `grid-column` sin tocar el DOM. -->
      <div class="detail-body">
        <!-- Una sola forma para los seis medios. Hasta el 2026-09-02 esto se bifurcaba
           con tres banderas del registry —`librarySectionClass`, `libraryTitleIcon` y
           `divider`— que partían los medios en dos grupos idénticos sin que nadie lo
           hubiera decidido: venían de respetar la divergencia que ya había al unificar
           las seis vistas. Se queda la variante con icono porque el icono conmuta con
           el acento del medio y es lo único de la sección que dice de qué ficha es. -->
        <div class="library-section detail-panel">
          <h2 class="section-title">
            <i :class="['fas', existing ? 'fa-edit' : 'fa-save']" />
            {{ existing ? d.libraryTitleExisting : d.libraryTitleNew }}
          </h2>
          <!-- ⚠ El panel va con `media`, NO con `d.libraryMedia`. Series declara
               `libraryMedia: 'movie'` (`mediaRegistry.js:1833`) y eso es correcto para
               el modal de edición de abajo —despacha por medio y series no tiene
               store—, pero aquí hacía que la ficha de serie pintara el panel de
               PELÍCULA: su `extraActions` propio, el botón que abre el seguimiento por
               temporadas, no se consultaba nunca. `series.libraryItem` hereda de
               `movie.libraryItem` por prototipo, así que todo lo demás sigue igual, y
               `library-series-item-container` ya existía en el SCSS. -->
          <LibraryMediaItem
            ref="libraryItemRef"
            :media="media"
            :item="itemForLibrary"
            :allowed-statuses="allowedStatuses"
            :is-new="!existing"
            :editable="!!(onStatus || onRate)"
            @save="handleSave"
            @rate="guardarValoracion"
            @set-statuses="guardarEstados"
            @show-history="(payload) => emit('show-history', payload)"
            @show-editions="(payload) => emit('show-editions', payload)"
            @show-seasons="(payload) => emit('show-seasons', payload)"
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

        <!-- Las secciones propias del medio: sinopsis, pistas, capturas… -->
        <div class="detail-extra">
          <slot
            name="extra"
            :item="item"
            :context="context"
            :existing="existing"
          />

          <!-- Los identificadores, plegados y al final. Hasta el 2026-09-03 vivían
               en `#meta`, entre el título y la sinopsis: un ISBN o un id de IMDb no
               se leen, se copian, y ocupaban el sitio de lo que sí se lee. Se pinta
               solo si el medio llena el slot, que hoy son tres de seis. -->
          <details
            v-if="$slots.technical"
            class="detail-technical"
          >
            <summary>{{ t('detail.technical') }}</summary>
            <!-- ⚠ El contenido va envuelto, y no es decoración. El navegador oculta
                 lo que cuelga de un `<details>` cerrado con un `display: none` de la
                 hoja de usuario, que es la de MENOR prioridad: el `.meta-identifier`
                 del mixin declara `display: flex` y le ganaba, así que el ISBN se
                 veía con el plegable cerrado —medido: `offsetHeight: 15` con `open`
                 ausente—. Este `<div>` no declara `display`, así que la regla del
                 navegador sí le aplica y el que se oculta es él, con todo dentro. -->
            <div class="detail-technical__body">
              <slot
                name="technical"
                :item="item"
              />
            </div>
          </details>
        </div>
      </div>

      <!-- Las notas, a ancho completo y fuera de la rejilla: son de escribir y
           de releer, y en la columna estrecha no cabe ni un párrafo. Hasta el
           2026-09-02 solo las tenían aquí juego, álbum y vídeo —lo decidía un
           `hasNotes` del registry—; en libro, película y serie había que abrir el
           modal de edición para escribir una, y ese modal las llevaba **también**
           en los tres que ya las tenían aquí: dos sitios para lo mismo. -->
      <div
        v-if="existing"
        class="notes-section"
      >
        <MediaNotes
          :media="media"
          :item-id="notesId"
        />
      </div>

      <!-- El medio que viaja es `coverMedia`, no `media`: una serie se guarda con
           `AddMovieUseCase`, así que se recomienda como `movie`. Y la clave es
           `routeId`, la misma con la que la bandeja pedirá su portada.

           Con `v-if` y no solo con `v-model`: el diálogo usa dos stores de Pinia
           en su `setup`, y sin esto toda ficha visitada los instanciaría para un
           diálogo que casi nunca se abre. -->
      <RecommendDialog
        v-if="showRecommendDialog"
        v-model="showRecommendDialog"
        :entity-type="coverMedia"
        :entity-id="routeId"
        :entity-title="title"
        :entity-cover="remoteCoverUrl"
      />

      <!-- Mismo criterio y mismas claves que el de recomendar: el medio que
           viaja es `coverMedia` y la clave es `routeId`, la misma con la que la
           tarjeta de la lista pedirá su portada.

           Con `v-if` y no solo con `v-model`: el diálogo usa un store de Pinia
           en su `setup` y pide `get_my_lists` al montar, así que sin esto toda
           ficha visitada haría esa llamada para un diálogo que casi nunca se
           abre. -->
      <AddToListDialog
        v-if="showAddToListDialog"
        v-model="showAddToListDialog"
        :entity-type="coverMedia"
        :entity-id="routeId"
        :entity-title="title"
        :entity-cover="remoteCoverUrl"
      />

      <!-- Con `v-if` y no solo con `v-model`, como los otros dos: usa el store
           de clubs en su `setup`, así que instanciarlo siempre haría que toda
           ficha visitada lo levantara. -->
      <AddToClubDialog
        v-if="showAddToClubDialog"
        v-model="showAddToClubDialog"
        :entity-type="coverMedia"
        :entity-id="routeId"
        :entity-title="title"
        :entity-cover="remoteCoverUrl"
      />

      <!-- Con `v-if` además del `v-model`, como los tres de arriba: el modal usa
           el store del diario en su `setup`, y sin esto toda ficha visitada lo
           levantaría. El ítem llega ya decidido, así que no pinta el buscador.
           Va con `media` y NO con `coverMedia`: aquel mapea series→película para
           el endpoint de portadas, pero el diario sí distingue las dos —una
           entrada de serie es una temporada— y su ruta de detalle es otra.
           Y el id va con `coverKey`, NO con `routeId` pelado: el diario guarda
           la identidad con la que el resto de la app conoce el ítem, que es la
           de `libraryItem.idOf` —la misma con la que se registra la portada—, y
           en álbum esa es el PK de `albums` mientras que el parámetro de la ruta
           es el MBID del mirror. En los otros cinco medios las dos coinciden, así
           que esto no los cambia. El `?? routeId` cubre el ítem que aún no está
           en la biblioteca, donde `existing` es `null` y no hay más id que el de
           la ruta: ese lo normaliza el backend en `JournalItemResolver`. -->
      <JournalEntryModal
        v-if="showJournalDialog"
        v-model="showJournalDialog"
        :item="{ media, entityId: coverKey ?? routeId, title }"
      />

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

    <EmptyState
      v-else
      :icon="d.placeholderIcon"
      :title="d.emptyText"
    >
      <button
        class="btn btn--ghost"
        @click="goBack"
      >
        {{ d.backText }}
      </button>
    </EmptyState>

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
import { ref, computed, onMounted, watch, toRaw, useId } from 'vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { useRoute, useRouter } from 'vue-router'
import LibraryMediaItem from '@/components/shared/LibraryMediaItem.vue'
import MediaNotes from '@/components/shared/MediaNotes.vue'
import MediaSkeleton from '@/components/shared/MediaSkeleton.vue'
import EditItemModal from '@/components/EditItemModal.vue'
// Importado aquí y no registrado en `main.js`: es la convención del repo para
// todo PrimeVue salvo `MultiSelect` —`MediaNotes.vue:188-192` importa cinco así—,
// y el registro global no llegaría a `tests/unit/helpers/mount.js`, que no da de
// alta ni un componente: los tests de esta vista lo verían sin resolver, que es
// exactamente cómo `v-tooltip` estuvo roto tres meses.
import Menu from 'primevue/menu'
import RecommendDialog from '@/components/Social/RecommendDialog.vue'
import AddToListDialog from '@/components/Lists/AddToListDialog.vue'
import AddToClubDialog from '@/components/Clubs/AddToClubDialog.vue'
import JournalEntryModal from '@/components/Journal/JournalEntryModal.vue'
import { getMediaConfig, mediaKeys } from '@/config/mediaRegistry'
import CoverService from '@/services/CoverService'
import { useAuthStore } from '@/store/auth'
import { useUIStore } from '@/store/ui'
import { useConfirmationModal } from '@/composables/useConfirmationModal'
import Logger from '@/utils/logger'
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

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
  },
  /**
   * Guardar el estado y la valoración desde el panel, sin abrir el modal.
   *
   * Son dos **funciones ya resueltas** y no una prop `composable` a propósito: el
   * método que orquesta no se llama igual en los seis medios —`createMediaComposable`
   * lo publica como `update<One>Statuses`, y **no existe ningún `updateStatuses`
   * pelado**—, así que una vista genérica tendría que armar el nombre con cadenas y
   * fallaría en silencio el día que el registry cambiara `One`. Con dos props, cada
   * wrapper dice explícitamente con qué guarda, que es donde vive la diferencia:
   * en libros `updateBookStatuses` es la versión de `useBooks` con confirmación de
   * sesión, y en los otros cinco la delegación de tres líneas al store.
   *
   * La valoración va por `update<One>Rating` y no por `editItem`: desde el 2026-09-09
   * las cinco acciones de rating funcionan y comparten criterio —`ActionRouter.php:306`,
   * `routes.php:360`, los cinco comandos con `?Rating`—, y las cubre
   * `backend/tests/Integration/RatingActionsTest.php`. `edit_user_*` se queda como el
   * camino del modal de edición, que es el que guarda varios campos a la vez.
   */
  onStatus: {
    type: Function,
    default: null
  },
  onRate: {
    type: Function,
    default: null
  }
})

// Los tres `show-*` los origina `extraActions` del registry en el panel y los
// consume el wrapper del medio, que es quien tiene el modal: esta vista solo hace de
// puente. Cada uno va declarado porque su destino es distinto.
const emit = defineEmits(['show-history', 'show-editions', 'show-seasons', 'loaded'])

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
const showRecommendDialog = ref(false)
const showAddToListDialog = ref(false)
const showAddToClubDialog = ref(false)
const showJournalDialog = ref(false)
// Una portada que no carga pinta el placeholder del medio en vez de dejar el
// icono de imagen rota del navegador.
const imageError = ref(false)

// El estado del CTA vive aquí y no en `LibraryMediaItem` porque el botón subió a
// la barra: el panel ya no tiene ninguno que pintar de verde.
const ctaState = ref('idle')
const menuRef = ref(null)
const menuId = `detail-actions-menu-${useId()}`

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
// Normalmente la nota cuelga del mismo identificador que la ficha, y por eso el
// resolutor por defecto es el del panel. Los libros son la excepción —la nota es de
// TU edición, no del ISBN— y lo declaran con `notesIdOf`.
const notesId = computed(() => {
  const fila = existing.value ?? item.value ?? {}
  const resolver = d.value.notesIdOf ?? config.value.libraryItem.idOf
  return resolver(fila) ?? routeId.value
})

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
    // El store va como cuarto argumento porque un medio puede necesitar resolver
    // su id de catálogo contra lo que ya está guardado: la ruta de álbum admite el
    // entero de la tabla `albums` (llega así desde trending) y el catálogo solo
    // entiende MBID o base62. Los otros cinco medios lo ignoran.
    const result = await d.value.enrich(
      routeId.value, authStore.apiCall.bind(authStore), item.value, props.store
    )
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

// ─── La barra: el CTA y el menú `⋯` ──────────────────────────────────────
// Una sola acción primaria, y conmuta con `existing`: mientras el ítem no esté en
// tu biblioteca lo único que tiene sentido es meterlo; en cuanto lo está, editarlo.
const ctaLabel = computed(() => (existing.value ? t('common.edit') : t('edit.saveToLibrary')))

const ctaIcon = computed(() => {
  if (ctaState.value === 'success') return 'fas fa-check'
  if (ctaState.value === 'error') return 'fas fa-times'
  return existing.value ? 'fas fa-pencil-alt' : 'fas fa-save'
})

/** El acuse lo sigue dando el padre; lo que cambió es dónde se pinta. */
function flashCta (estado) {
  ctaState.value = estado
  setTimeout(() => { ctaState.value = 'idle' }, 2000)
}

function onCta () {
  if (existing.value) handleEdit()
  // El payload del alta lo arma el panel: los estados y la valoración elegidos
  // son suyos, y subirlos aquí duplicaría `savePayload` del registry.
  else libraryItemRef.value?.guardar()
}

// Las tres colaterales, más el borrado cuando hay algo que borrar. `separator` lo
// entiende PrimeVue: es lo que despega «Eliminar» de las otras tres para que no se
// pulse por inercia.
const menuItems = computed(() => {
  if (!item.value || !isAuthenticated.value) return []

  const acciones = [
    {
      label: t('recommend.action'),
      icon: 'fas fa-share',
      command: () => { showRecommendDialog.value = true }
    },
    {
      label: t('lists.addToList'),
      icon: 'fas fa-list-ul',
      command: () => { showAddToListDialog.value = true }
    },
    {
      label: t('addToClub.title'),
      icon: 'fas fa-users',
      command: () => { showAddToClubDialog.value = true }
    }
  ]

  // Solo con el ítem en tu biblioteca: el diario registra lo que consumiste, y
  // eso es algo que tienes. Va en el menú `⋯` y no en `libraryItem.extraActions`
  // del registry porque es idéntico en los seis medios —declararlo ahí serían
  // seis copias, y una séptima en el `extraActions` propio de series, que
  // sustituye al de películas en vez de añadirse—.
  if (existing.value) {
    acciones.push({
      label: t('journal.add'),
      icon: 'fas fa-book-open',
      command: () => { showJournalDialog.value = true }
    })
  }

  if (existing.value) {
    acciones.push({ separator: true })
    acciones.push({
      label: t('common.delete'),
      icon: 'fas fa-trash',
      command: () => handleDelete(config.value.libraryItem.deletePayload(itemForLibrary.value))
    })
  }

  return acciones
})

// `toggle` necesita el evento: de su `currentTarget` saca el ancla para colocarse
// y, sobre todo, el elemento al que devolver el foco al cerrarse con Escape.
function abrirMenu (event) {
  menuRef.value?.toggle(event)
}

// ─── Guardar, editar y borrar ────────────────────────────────────────────
async function handleSave (payload) {
  try {
    const [data, statuses] = d.value.unwrapSave(payload)
    const result = await props.store.add(data, statuses)

    if (result.success) {
      if (item.value) item.value = { ...item.value, userStatuses: statuses }
      await props.store.fetch()
      flashCta('success')
    } else {
      Logger.error(`[MediaDetailView] Error saving ${props.media}:`, result.message)
      flashCta('error')
    }
  } catch (err) {
    Logger.error(`[MediaDetailView] Error saving ${props.media}:`, err)
    flashCta('error')
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

    flashCta('success')
    if (d.value.savedMessage) uiStore.showSuccess(d.value.savedMessage)

    // Resincronizar con el backend sin bloquear la interfaz.
    setTimeout(() => {
      props.store.fetch().catch((err) =>
        Logger.error('[MediaDetailView] Background refresh failed:', err))
    }, 500)
  } catch (err) {
    Logger.error(`[MediaDetailView] Error updating ${props.media}:`, err)
    flashCta('error')
  }
}

async function handleDelete (payload) {
  // Antes esto era el `confirm()` NATIVO del navegador: el único modal del
  // proyecto que no era un componente. No seguía el sistema visual, no conmutaba
  // con el tema y bloqueaba cualquier prueba de navegador (se descubrió el
  // 2026-08-29, al intentar verificar `BaseModal`, porque WebDriver se atasca en
  // un diálogo nativo).
  //
  // Se usa `showConfirmation` y no `confirmDelete` a propósito: aquel exige
  // escribir «ELIMINAR», que es la fricción que pide el borrado desde
  // `/library` (`createMediaComposable.js:122`), y esta ficha llevaba un sí/no.
  // Cambiar las dos a lo mismo es una decisión de producto, no de este plan.
  if (d.value.deleteConfirm) {
    const { showConfirmation } = useConfirmationModal()
    const confirmado = await showConfirmation({
      title: t('confirm.deleteFromLibrary'),
      message: d.value.deleteConfirm,
      type: 'danger',
      confirmText: t('common.delete')
    })
    if (!confirmado) return
  }

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

// ─── Guardado al vuelo desde el panel ────────────────────────────────────
// Las tres guardas son las que `EditItemModal.vue:636-648` lleva aplicando meses, y
// viven aquí y no en cada wrapper porque son las mismas para los seis.
//
// Lo que NO se copia del modal es su paso final: él llama a `updateReadingProgress`
// después de guardar, porque está guardando un formulario con su página actual. Aquí
// se cambia un estado de un clic, y llamarlo CREARÍA una sesión de lectura en un
// libro que no la tiene (`UpdateReadingProgressUseCase.php:79-97`).

/** Guarda la valoración. Optimista: se pinta ya y se revierte si falla. */
async function guardarValoracion (valor) {
  if (!props.onRate || !existing.value) return
  const id = config.value.libraryItem.idOf(existing.value)
  // `store?` no es cosmético: **`series` es el único medio sin bloque `store`** en el
  // registry —comparte el de `movie`—, así que sin el opcional esta línea lanzaba
  // «config.value.store is undefined» y reventaba ANTES de llamar a `onRate`. La ficha
  // de serie pintaba la estrella y no guardaba nada, por este camino y por el viejo de
  // `editItem`. Verificado en navegador el 2026-09-09.
  const previo = existing.value[config.value.store?.ratingField || 'user_rating']

  try {
    const r = await props.onRate(id, valor)
    if (r && r.success === false) throw new Error(r.message)
    if (item.value) item.value = { ...item.value, user_rating: valor }
    Object.assign(existing.value, { user_rating: valor })
  } catch (err) {
    Logger.error(`[MediaDetailView] Error guardando la valoración de ${props.media}:`, err)
    if (item.value) item.value = { ...item.value, user_rating: previo }
    uiStore.showError(t('storeError.rating'))
  }
}

/** Guarda los estados. Con las tres guardas del modal, incluida `cancelled`. */
async function guardarEstados (estados) {
  if (!props.onStatus || !existing.value) return

  // (1) El ítem tiene que estar en el store: el guardado orquestado lo busca ahí
  //     para leer su sesión, y sin él responde «not found».
  if (props.store.items.length === 0) await props.store.fetch()

  const id = config.value.libraryItem.idOf(existing.value)
  const previos = existing.value.userStatuses || []

  // (2) Solo si cambian de verdad.
  const cambian = estados.length !== previos.length ||
    estados.some((e) => !previos.includes(e))
  if (!cambian) return

  try {
    const r = await props.onStatus(id, estados)
    // (3) El usuario dijo que no en la confirmación de sesión: no se guarda nada
    //     y el control vuelve a lo que había.
    if (r && r.cancelled) {
      Object.assign(existing.value, { userStatuses: [...previos] })
      // El panel guarda su propia copia y solo la resincroniza cuando cambia el id
      // del ítem (`LibraryMediaItem`, watch sobre `idOf`), así que revertir aquí no
      // le llegaba y el chip se quedaba pintado hasta recargar.
      libraryItemRef.value?.revertirEstados(previos)
      return
    }
    if (r && r.success === false) throw new Error(r.message)
    // Los efectivos, no los pedidos: en libros, terminar uno retira la lectura en
    // curso (`useBooks`), así que lo guardado puede ser un subconjunto de `estados`.
    const efectivos = Array.isArray(r?.statuses) ? r.statuses : estados
    Object.assign(existing.value, { userStatuses: efectivos })
    if (item.value) item.value = { ...item.value, userStatuses: efectivos }
    if (efectivos.length !== estados.length) libraryItemRef.value?.revertirEstados(efectivos)
  } catch (err) {
    Logger.error(`[MediaDetailView] Error guardando los estados de ${props.media}:`, err)
    Object.assign(existing.value, { userStatuses: [...previos] })
    libraryItemRef.value?.revertirEstados(previos)
    uiStore.showError(t('storeError.statuses'))
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

// ── La barra de acciones ──────────────────────────────────────────────────
// La disposición la pone `detail-view-page`, que la comparten las seis fichas.
// Aquí solo queda lo del CTA: puede ceder ancho, y su rótulo se recorta antes que
// empujar el menú fuera de la fila.
.detail-cta {
  min-width: 0;

  span { @include truncate(1); }
}

.detail-more { flex-shrink: 0; }

// Las secciones que pinta este componente, no el wrapper.
.library-section,
.notes-section {
  @include detail-section-card;
}

// ── Atribución del proveedor (TMDB) ───────────────────────────────────────
// Discreta pero legible: es un requisito de uso de la API, no una firma.
.provider-attribution {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: spacing(xs);
  margin-top: spacing(xl);
  padding-top: spacing(lg);
  border-top: 1px solid var(--color-border-light);
  text-align: center;

  &__logo {
    width: 6.5rem;
    height: auto;
  }

  &__text {
    margin: 0;
    max-width: 32rem;
    font-size: var(--font-size-xs);
    color: var(--color-text-muted);
  }
}

// ── Portadas: cada medio tiene su tamaño y su relleno ──────────────────────
.video-detail-view {
  .video-cover-large {
    position: relative; // CRITICAL: contiene el youtube-play-btn inset:0
    flex-shrink: 0;
    width: 320px;

    // Miniatura, no portada a pantalla completa: 16/9 es el más ancho de los seis.
    @include responsive-below(md) {
      width: 160px;
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
      width: 110px;
      height: 110px;
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

// El bloque del libro vivía en `BookDetailView.vue` y **nunca se aplicó**: ese wrapper
// tiene el CSS `scoped`, y el `scoped` no alcanza el marcado que pinta ESTE fichero —
// solo su raíz y el contenido de sus slots—. La portada quedaba a merced del
// `flex-shrink` por defecto (medido: 52 px a 390 px, con `flex-shrink: 1`). Los otros
// cinco medios ya tenían su bloque aquí; el libro era el único fuera.
.book-detail-view {
  .book-cover-large,
  .cover-placeholder {
    flex-shrink: 0;
    width: 220px;

    @include responsive-below(md) {
      width: 110px;
    }
  }
}

.movie-detail-view {
  .movie-poster-large,
  .poster-placeholder {
    flex-shrink: 0;
    width: 220px;

    // Miniatura en móvil, 2/3 como el resto de pósteres.
    @include responsive-below(md) {
      width: 110px;
    }
  }

  .poster-placeholder {
    height: 330px;

    @include responsive-below(md) {
      height: auto;
      aspect-ratio: 2 / 3;
    }
  }
}

.series-detail-view {
  @include detail-view-page('movie', 'series');

  .series-poster-large,
  .poster-placeholder {
    flex-shrink: 0;
    width: 220px;

    @include responsive-below(md) {
      width: 110px;
    }
  }

  .poster-placeholder {
    background: rgba(139, 92, 246, 0.15);
    border: 2px dashed rgba(139, 92, 246, 0.3);
    color: rgba(139, 92, 246, 0.4);
    font-size: var(--font-size-4xl);
  }
}

.game-detail-view {
  .game-cover-large {
    flex-shrink: 0;
    width: 280px;

    @include responsive-below(md) {
      width: 110px;
    }
  }

  .cover-placeholder {
    aspect-ratio: 3 / 4;
    background: linear-gradient(135deg, var(--color-card-movie-accent) 0%, var(--color-card-movie-accent) 100%);
    border: none;
    color: var(--color-on-overlay);
    font-size: 4rem;
  }
}
</style>
