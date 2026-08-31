<template>
  <div class="book-detail-page">
    <!-- Raíz ÚNICA, y un elemento: el <Transition mode="out-in"> de App.vue no puede animar
         un fragment, y su transición de salida no llega a terminar nunca, así que el
         <router-view> deja de montar nada y la app se queda en blanco. Ojo: un comentario
         aquí arriba, fuera de este div, también cuenta como nodo raíz y reproduce el fallo. -->
    <MediaDetailView
      ref="detalle"
      media="book"
      :store="booksStore"
      @show-history="abrirHistorial"
    >
      <template #meta="{ item }">
        <div
          v-if="item.author"
          class="book-author-large"
        >
          <i class="fas fa-user" />
          <span>{{ t('book.by', { name: item.author }) }}</span>
        </div>

        <div class="book-metadata">
          <span
            v-if="item.publisher"
            class="metadata-item"
          >
            <i class="fas fa-building" />
            {{ item.publisher }}
          </span>
          <span
            v-if="item.publicationDate"
            class="metadata-item"
          >
            <i class="fas fa-calendar" />
            {{ item.publicationDate }}
          </span>
          <span
            v-if="item.pages"
            class="metadata-item"
          >
            <i class="fas fa-file-alt" />
            {{ t('book.pages', { n: item.pages }) }}
          </span>
        </div>

        <div
          v-if="item.language"
          class="book-language"
        >
          <i class="fas fa-globe" />
          <span>{{ getLanguageName(item.language) }}</span>
        </div>

        <div
          v-if="item.isbn"
          class="book-isbn-display"
        >
          <strong>{{ t('book.isbn') }}</strong> {{ item.isbn }}
          <span
            v-if="item.isbn10"
            class="isbn-secondary"
          >{{ t('book.isbn10', { n: item.isbn10 }) }}</span>
        </div>
      </template>

      <template #extra="{ item, existing }">
        <div
          v-if="item.description"
          class="book-description-section"
        >
          <h2 class="section-title">
            <i class="fas fa-book-open" />
            {{ t('book.description') }}
          </h2>
          <!-- eslint-disable vue/no-v-html -- saneado con utils/sanitize.js -->
          <div
            class="book-description-content"
            v-html="sanitizeRich(formatDescription(item.description))"
          />
          <!-- eslint-enable vue/no-v-html -->
        </div>

        <!-- Selector de ediciones: al elegir otra, cambia el ítem de la ficha. -->
        <EditionSelector
          v-if="item.work_key"
          :work-key="item.work_key"
          :initial-selected-edition="item"
          :saved-isbn="existing ? existing.isbn : null"
          @edition-selected="(edicion) => seleccionarEdicion(item, edicion)"
        />

        <div
          v-if="item.subjects && item.subjects.length > 0"
          class="book-subjects-section"
        >
          <h2 class="section-title">
            <i class="fas fa-bookmark" />
            {{ t('book.subjects') }}
          </h2>
          <div
            v-for="grupo in materiasAgrupadas(item.subjects)"
            :key="grupo.clave"
            class="subject-group"
          >
            <h3
              v-if="grupo.rotulo"
              class="subject-group__label"
            >
              {{ grupo.rotulo }}
            </h3>
            <div class="subject-tags">
              <a
                v-for="materia in grupo.materias"
                :key="materia.name"
                :href="materia.url"
                target="_blank"
                rel="noopener noreferrer"
                class="subject-tag"
              >
                {{ materia.texto }}
              </a>
            </div>
          </div>
        </div>

        <div
          v-if="item.previewLink || item.infoLink || item.openLibraryUrl"
          class="book-links-section"
        >
          <h2 class="section-title">
            <i class="fas fa-external-link-alt" />
            {{ t('book.links') }}
          </h2>
          <div class="external-links">
            <a
              v-if="item.previewLink"
              :href="item.previewLink"
              target="_blank"
              rel="noopener noreferrer"
              class="external-link"
            >
              <i class="fab fa-google" />
              {{ t('book.googlePreview') }}
            </a>
            <a
              v-if="item.infoLink"
              :href="item.infoLink"
              target="_blank"
              rel="noopener noreferrer"
              class="external-link"
            >
              <i class="fab fa-google" />
              {{ t('book.googleInfo') }}
            </a>
            <a
              v-if="item.openLibraryUrl"
              :href="item.openLibraryUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="external-link"
            >
              <i class="fas fa-book" />
              {{ t('book.openLibrary') }}
            </a>
          </div>
        </div>

        <div
          v-if="item.classifications"
          class="book-classifications-section"
        >
          <h2 class="section-title">
            <i class="fas fa-list-ol" />
            {{ t('book.classifications') }}
          </h2>
          <div class="classifications-content">
            <span
              v-if="item.classifications.lc"
              class="classification-item"
            >
              <strong>{{ t('book.lc') }}</strong> {{ item.classifications.lc.join(', ') }}
            </span>
          </div>
        </div>
      </template>

      <!-- Lo irreductible del libro dentro de la ficha de biblioteca. -->
      <template #library-after-rating="{ item }">
        <ReadingProgressBar
          :current-page="item.currentPage || 0"
          :total-pages="item.pages || 0"
          :editable="false"
          theme="blue"
        />
      </template>

      <template #library-after-status="{ item }">
        <ReadingStatusWidget
          v-if="detalle?.existing"
          :book="item"
        />
      </template>
    </MediaDetailView>

    <SessionHistoryModal
      :visible="historial.isVisible"
      :book="historial.book"
      @close="cerrarHistorial"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import MediaDetailView from '@/views/shared/MediaDetailView.vue';
import EditionSelector from '@/components/Books/EditionSelector.vue';
import SessionHistoryModal from '@/components/Books/SessionHistoryModal.vue';
import ReadingProgressBar from '@/components/common/ReadingProgressBar.vue';
import ReadingStatusWidget from '@/components/Books/ReadingStatusWidget.vue';
import { useBooksStore } from '@/store/books';
import { useUIStore } from '@/store/ui';
import { getLanguageName } from '@/utils/languageConstants';
import Logger from '@/utils/logger';
import { sanitizeRich } from '@/utils/sanitize';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

/**
 * Ficha de libro. El esqueleto —estados, cabecera, formulario de biblioteca,
 * modal y ciclo de vida, incluido el enriquecimiento con Google Books y
 * OpenLibrary— vive en MediaDetailView, configurado desde mediaRegistry.
 *
 * Aquí queda lo que solo tienen los libros: el selector de ediciones (que
 * **reemplaza el ítem de la ficha**), las materias de OpenLibrary, las
 * clasificaciones, la barra de progreso y el widget de estado de lectura, y el
 * historial de sesiones.
 */
const booksStore = useBooksStore();
const uiStore = useUIStore();
const detalle = ref(null);

const historial = ref({ isVisible: false, book: {} });

const abrirHistorial = (book) => {
  Logger.debug('[BookDetailView] Showing session history for book:', book?.title);
  historial.value = { isVisible: true, book };
};

/**
 * Las materias de OpenLibrary vienen de un vocabulario controlado y la clave
 * viaja sin partir: `form:novel`, `series:The Mistborn Saga`, `genre:high
 * fantasy`. Se enseña solo lo de la derecha, y lo de la izquierda agrupa.
 *
 * Las que no llevan `:` —que son la mayoría— caen en un grupo sin rótulo, y ese
 * va **el último**: es el cajón de sastre, no el encabezamiento de la sección.
 */
const materiasAgrupadas = (materias) => {
  const grupos = new Map();

  for (const materia of (materias ?? []).slice(0, 15)) {
    const corte = materia.name.indexOf(':');
    const clave = corte > 0 ? materia.name.slice(0, corte) : '';
    const texto = corte > 0 ? materia.name.slice(corte + 1).trim() : materia.name;

    if (!grupos.has(clave)) grupos.set(clave, []);
    grupos.get(clave).push({ ...materia, texto });
  }

  return [...grupos]
    .sort(([a], [b]) => (a === '' ? 1 : b === '' ? -1 : 0))
    .map(([clave, materias]) => ({
      clave,
      // Cae al propio vocabulario si el catálogo no lo conoce, como `statusLabel`
      // con un estado nuevo del backend: OpenLibrary puede inventarse uno.
      rotulo: clave ? (t(`book.subjectGroups.${clave}`) === `book.subjectGroups.${clave}` ? clave : t(`book.subjectGroups.${clave}`)) : '',
      materias
    }));
};

const cerrarHistorial = () => {
  historial.value = { isVisible: false, book: {} };
};

const formatDescription = (description) => {
  if (!description) return '';
  // Decodifica las entidades HTML que devuelve Google Books.
  const textarea = document.createElement('textarea');
  textarea.innerHTML = description;
  return textarea.value.replace(/\n/g, '<br>');
};

/**
 * Cambiar de edición cambia el ISBN y, con él, los datos de usuario: si la
 * edición elegida ya está en la biblioteca se toman los suyos; si no, se
 * resetean.
 */
const seleccionarEdicion = (book, edition) => {
  if (!book || !edition) return;

  const nuevoIsbn = edition.isbn_13 || edition.isbn_10;
  const enBiblioteca = nuevoIsbn ? booksStore.getBookByIsbn(nuevoIsbn) : null;

  const delUsuario = enBiblioteca
    ? {
        user_rating: enBiblioteca.user_rating,
        userStatuses: enBiblioteca.userStatuses || [],
        currentPage: enBiblioteca.currentPage,
        totalPages: enBiblioteca.totalPages
      }
    : {
        user_rating: null,
        userStatuses: [],
        currentPage: 0,
        totalPages: edition.number_of_pages || book.pages
      };

  detalle.value?.setItem({
    ...book,
    work_key: book.work_key,
    isbn: nuevoIsbn || book.isbn,
    isbn10: edition.isbn_10 || book.isbn10,
    title: edition.title || book.title,
    publisher: edition.publishers?.length > 0 ? edition.publishers[0] : book.publisher,
    publishers: edition.publishers || book.publishers,
    publicationDate: edition.publish_date || book.publicationDate,
    pages: edition.number_of_pages || book.pages,
    coverUrl: edition.cover_url || book.coverUrl,
    language: edition.languages?.length > 0
      ? (typeof edition.languages[0] === 'string' ? edition.languages[0] : (edition.languages[0].key || 'en'))
      : book.language,
    physical_format: edition.physical_format || null,
    author: book.author,
    description: book.description,
    genres: book.genres,
    subjects: book.subjects,
    rating: book.rating,
    ...delUsuario
  });

  uiStore.showSuccess(enBiblioteca
    ? 'Edición seleccionada. Esta edición ya está en tu biblioteca.'
    : 'Edición seleccionada. Los datos del libro se han actualizado.');
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;
@use '@/assets/styles/components/detail-view' as *;

.book-detail-view {
  @include detail-view-page('book');

  .book-description-section,
  .book-subjects-section,
  .book-links-section,
  .book-classifications-section,
  .library-form-section {
    @include detail-section-card;
  }

  .book-cover-large {
    flex-shrink: 0;
    width: 220px;
  }

  .book-main-info {
    flex: 1;
    min-width: 0;
  }

  .book-author-large {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    font-size: var(--font-size-lg);
    color: var(--color-text-secondary);
    margin-bottom: spacing(md);

    i { color: var(--color-card-book-accent); }

    @include responsive-below(md) {
      font-size: var(--font-size-base);
    }
  }

  .book-metadata {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(sm);
    margin-bottom: spacing(sm);

    @include responsive-below(md) {
      gap: spacing(2xs);
    }
  }

  .book-language {
    display: flex;
    align-items: center;
    gap: spacing(xs);
    margin-bottom: spacing(sm);
    color: var(--color-text-secondary);
    font-size: var(--font-size-base);

    i { color: var(--color-card-book-accent); }
  }

  .subject-tags {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(xs);
  }

  .subject-group + .subject-group {
    margin-top: spacing(md);
  }

  .subject-group__label {
    margin: 0 0 spacing(2xs);
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--color-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }

  .book-description-content {
    line-height: 1.8;
    color: var(--color-text);
    font-size: var(--font-size-base);
    text-align: justify;
  }

  .classifications-content {
    display: flex;
    gap: spacing(sm);
    flex-wrap: wrap;
  }

  .classification-item {
    padding: spacing(xs) spacing(md);
    background: var(--color-background-soft);
    border-radius: radius(sm);
    font-size: var(--font-size-sm);
  }

  .isbn-secondary {
    color: var(--color-text-muted);
    margin-left: spacing(2xs);
  }

  @include responsive-below(md) {
    .book-cover-large,
    .cover-placeholder {
      width: 100%;
      max-width: 250px;
      margin: 0 auto;
    }
  }
}
</style>

