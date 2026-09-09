<template>
  <BaseModal
    :model-value="modelValue"
    :title="entry ? t('journal.editTitle') : t('journal.addTitle')"
    size="md"
    icon="fas fa-book-open"
    :accent="acentoDelMedio"
    :dismissible="!isSaving"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <form
      class="journal-form"
      @submit.prevent="guardar"
    >
      <!-- Qué. Con el ítem ya decidido —desde una ficha, o editando— se pinta y
           no se toca: cambiar de ítem es borrar esta entrada y hacer otra. -->
      <div class="journal-form__field">
        <span class="journal-form__label">{{ t('journal.what') }}</span>

        <div
          v-if="itemElegido"
          class="journal-form__chosen"
        >
          <i :class="iconoDelMedio" />
          <span class="journal-form__chosen-title">{{ itemElegido.title }}</span>
          <button
            v-if="!entry && !item"
            type="button"
            class="btn btn--ghost btn--sm"
            @click="itemElegido = null"
          >
            {{ t('journal.change') }}
          </button>
        </div>

        <div v-else>
          <label
            for="journal-search"
            class="journal-form__hint"
          >{{ t('journal.pickHint') }}</label>
          <input
            id="journal-search"
            v-model="busqueda"
            type="search"
            class="journal-form__input"
            :placeholder="t('journal.searchPlaceholder')"
            autocomplete="off"
          >

          <ul
            v-if="busqueda.trim()"
            class="journal-form__results"
          >
            <li
              v-for="candidato in candidatos"
              :key="candidato.media + '-' + candidato.entityId"
            >
              <button
                type="button"
                class="journal-form__result"
                @click="itemElegido = candidato"
              >
                <i :class="candidato.icon" />
                <span>{{ candidato.title }}</span>
              </button>
            </li>
            <li
              v-if="candidatos.length === 0"
              class="journal-form__no-results"
            >
              {{ t('journal.noMatches') }}
            </li>
          </ul>
        </div>
      </div>

      <div class="journal-form__field">
        <label
          for="journal-date"
          class="journal-form__label"
        >{{ t('journal.date') }}</label>
        <input
          id="journal-date"
          v-model="fecha"
          type="date"
          class="journal-form__input"
          required
        >
      </div>

      <div class="journal-form__field">
        <span class="journal-form__label">{{ t('journal.rating') }}</span>
        <RatingComponent
          :rating="valoracion"
          @update:rating="valoracion = $event"
        />
      </div>

      <p
        v-if="error"
        class="journal-form__error"
      >
        {{ error }}
      </p>
    </form>

    <template #footer>
      <button
        type="button"
        class="btn btn--secondary"
        :disabled="isSaving"
        @click="$emit('update:modelValue', false)"
      >
        {{ t('common.cancel') }}
      </button>
      <button
        type="button"
        class="btn btn--primary"
        :class="{ 'is-loading': isSaving }"
        :disabled="isSaving || !itemElegido || !fecha"
        @click="guardar"
      >
        {{ t('common.save') }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { storeToRefs } from 'pinia'
import BaseModal from '@/components/common/BaseModal.vue'
import RatingComponent from '@/components/common/RatingComponent.vue'
import { useJournalStore } from '@/store/journal'
import { getMediaConfig, mediaKeys, storeMediaKeys } from '@/config/mediaRegistry'
import { useBooks } from '@/composables/useBooks'
import { useMovies } from '@/composables/useMovies'
import { useGames } from '@/composables/useGames'
import { useAlbums } from '@/composables/useAlbums'
import { useVideos } from '@/composables/useVideos'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()

/**
 * Alta y edición de una entrada del diario: qué, cuándo y qué te pareció.
 *
 * El formulario es el mismo que ya existe en
 * `components/Movies/SeriesSeasonTracker.vue:102-127` —fecha con
 * `<input type="date">`, `RatingComponent` y poco más—, que es el único sitio de
 * la app donde ya se apuntaba una fecha de consumo a mano.
 *
 * **El buscador mira TU BIBLIOTECA, no el catálogo.** Un diario registra lo que
 * consumiste, y eso lo tienes guardado; buscar contra el catálogo abriría la
 * puerta a apuntar algo que no tienes, que es una funcionalidad distinta. Además
 * el backend resuelve título y portada contra el catálogo al guardar, así que
 * aquí no hace falta acertar con ninguno de los dos.
 */
const props = defineProps({
  modelValue: { type: Boolean, default: false },
  // La entrada que se está editando, o `null` para un alta.
  entry: { type: Object, default: null },
  // Ítem ya decidido (`{ media, entityId, title }`), cuando se abre desde una ficha.
  item: { type: Object, default: null }
})

const emit = defineEmits(['update:modelValue', 'saved'])

const journalStore = useJournalStore()
const { isSaving, error } = storeToRefs(journalStore)

const fecha = ref('')
const valoracion = ref(null)
const busqueda = ref('')
const itemElegido = ref(null)

/**
 * Los cinco stores con biblioteca, aplanados a `{ media, entityId, title }`.
 *
 * `storeMediaKeys` y no `mediaKeys`: series no tiene store propio —comparte el
 * de películas porque en el backend son la misma entidad— y `mediaStores`
 * **lanza** con ella. Iterar sobre los seis es el error fácil aquí.
 */
/**
 * Los cinco composables, como los usa `MyLibrary.vue:160-164`. **No se usa
 * `mediaStores` de la factoría**: desde este componente el mapa llegaba vacío y
 * los `fetch()` no se disparaban nunca —sin error, solo cero resultados—, que es
 * la peor forma de fallar. Los composables son el camino que ya recorre la app.
 */
const composables = {
  book: useBooks(),
  movie: useMovies(),
  game: useGames(),
  album: useAlbums(),
  video: useVideos()
}

const deMiBiblioteca = computed(() => {
  const items = []

  for (const media of storeMediaKeys) {
    const config = getMediaConfig(media)
    const coleccion = composables[media]?.[config.store.collection]?.value ?? []

    for (const item of coleccion) {
      const entityId = config.list?.idOf?.(item)

      if (entityId) {
        items.push({
          media,
          entityId: String(entityId),
          title: config.list?.titleOf?.(item) ?? item.title ?? '',
          icon: config.list?.iconOf?.(item) ?? 'fas fa-star'
        })
      }
    }
  }

  return items
})

/**
 * Llena los cinco stores si están vacíos.
 *
 * **Hace falta**: nada en `/journal` carga la biblioteca, así que al llegar
 * directo a la ruta —o recargar en ella— los stores están a cero y el buscador
 * decía «nada casa con eso» con la biblioteca llena. Solo se veía abriendo el
 * modal en el navegador; los tests montan el componente con Pinia recién creado
 * y no distinguen «vacío porque no se pidió» de «vacío porque no hay».
 *
 * Los cinco `fetch()` van a la vez y **cuestan una sola petición**: los que leen
 * de `get_library_items` comparten la que ya esté en vuelo
 * (`store/_libraryCache.js`). Los errores se tragan: no poder ofrecer el
 * buscador no puede impedir editar la fecha de una entrada.
 */
const cargarBiblioteca = () => {
  for (const media of storeMediaKeys) {
    const config = getMediaConfig(media)
    const composable = composables[media]
    const coleccion = composable?.[config.store.collection]?.value ?? []

    if (composable && coleccion.length === 0) {
      composable[`fetch${config.store.Many}`]?.().catch(() => {})
    }
  }
}

/** Diez como mucho: la lista vive dentro de un modal, no es una pantalla. */
const candidatos = computed(() => {
  const q = busqueda.value.trim().toLowerCase()

  if (!q) {
    return []
  }

  return deMiBiblioteca.value
    .filter((i) => i.title.toLowerCase().includes(q))
    .slice(0, 10)
})

const mediaActual = computed(() => itemElegido.value?.media ?? null)

const iconoDelMedio = computed(() => {
  if (!mediaActual.value || !mediaKeys.includes(mediaActual.value)) {
    return 'fas fa-star'
  }

  return itemElegido.value?.icon ?? getMediaConfig(mediaActual.value).list?.iconOf?.({}) ?? 'fas fa-star'
})

// El filete del modal se pasa RESUELTO, no como nombre de medio: `BaseModal` no
// tiene por qué saber qué medios existen.
const acentoDelMedio = computed(() =>
  mediaActual.value ? `var(--color-card-${mediaActual.value}-accent)` : ''
)

/**
 * Al abrir, el formulario se rellena desde lo que le pasen. Va en un `watch` de
 * `modelValue` y no en `onMounted` porque el modal se monta una vez y se abre
 * muchas: sin esto, la segunda apertura conservaría lo tecleado en la primera.
 *
 * **`immediate: true` no es opcional, y es lo que lo hace servir en los dos
 * consumidores.** `JournalView` lo monta con `v-model` a secas —nace cerrado y
 * luego se abre, así que hay cambio y el watch dispara—, pero `MediaDetailView`
 * lo monta con `v-if` además del `v-model`, como los tres diálogos vecinos: ahí
 * el componente **se crea ya abierto**, `modelValue` nunca cambia y sin
 * `immediate` el watch no corría nunca. Se vio en el navegador: desde una ficha
 * el modal salía con el buscador en vez del ítem fijado y con la fecha vacía.
 */
watch(() => props.modelValue, (abierto) => {
  if (!abierto) {
    return
  }

  journalStore.error = null
  busqueda.value = ''
  cargarBiblioteca()

  if (props.entry) {
    itemElegido.value = {
      media: props.entry.media,
      entityId: props.entry.entity_id,
      title: props.entry.entity_title
    }
    fecha.value = props.entry.entry_date
    valoracion.value = props.entry.rating
    return
  }

  itemElegido.value = props.item ? { ...props.item } : null
  // Hoy en la zona del navegador. `toISOString()` daría UTC, y en cualquier
  // franja al oeste eso es ayer durante buena parte del día.
  fecha.value = new Date().toLocaleDateString('sv-SE')
  valoracion.value = null
}, { immediate: true })

const guardar = async () => {
  if (!itemElegido.value || !fecha.value) {
    return
  }

  const ok = props.entry
    ? await journalStore.update(props.entry.id, {
      entryDate: fecha.value,
      rating: valoracion.value
    })
    : await journalStore.add({
      media: itemElegido.value.media,
      entityId: itemElegido.value.entityId,
      entryDate: fecha.value,
      rating: valoracion.value
    })

  if (ok) {
    emit('saved')
    emit('update:modelValue', false)
  }
}
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.journal-form {
  display: flex;
  flex-direction: column;
  gap: spacing(md);

  &__field {
    display: flex;
    flex-direction: column;
    gap: spacing(2xs);
  }

  &__label {
    font-weight: 600;
    color: var(--color-text);
  }

  &__hint {
    color: var(--color-text-muted);
    font-size: var(--font-size-sm);
  }

  &__input {
    width: 100%;
    padding: spacing(xs) spacing(sm);
    border: 1px solid var(--color-border);
    border-radius: radius(md);
    background: var(--color-background);
    color: var(--color-text);
    font-size: var(--font-size-base);
  }

  &__chosen {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    padding: spacing(xs) spacing(sm);
    border: 1px solid var(--color-border);
    border-radius: radius(md);
    background: var(--color-background-soft);
  }

  &__chosen-title {
    flex: 1 1 auto;
    min-width: 0;
    overflow-wrap: break-word;
    font-weight: 600;
  }

  &__results {
    list-style: none;
    margin: spacing(2xs) 0 0;
    padding: 0;
    max-height: 240px;
    overflow-y: auto;
    border: 1px solid var(--color-border);
    border-radius: radius(md);
  }

  &__result {
    @include button-reset;

    display: flex;
    align-items: center;
    gap: spacing(xs);
    width: 100%;
    padding: spacing(xs) spacing(sm);
    text-align: left;
    color: var(--color-text);

    &:hover {
      background: var(--color-background-soft);
    }
  }

  &__no-results {
    padding: spacing(xs) spacing(sm);
    color: var(--color-text-muted);
  }

  &__error {
    margin: 0;
    color: var(--color-error);
  }
}
</style>
