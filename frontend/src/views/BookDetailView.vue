<template>
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
        <span>por {{ item.author }}</span>
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
          {{ item.pages }} páginas
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
        <strong>ISBN:</strong> {{ item.isbn }}
        <span
          v-if="item.isbn10"
          class="isbn-secondary"
        > • ISBN-10: {{ item.isbn10 }}</span>
      </div>

      <div
        v-if="item.genres && item.genres.length > 0"
        class="book-categories"
      >
        <i class="fas fa-tags" />
        <div class="category-tags">
          <span
            v-for="(genre, index) in item.genres"
            :key="index"
            class="category-tag"
          >
            {{ genre }}
          </span>
        </div>
      </div>
    </template>

    <template #extra="{ item, existing }">
      <div
        v-if="item.description"
        class="book-description-section"
      >
        <h2 class="section-title">
          <i class="fas fa-book-open" />
          Descripción
        </h2>
        <div
          class="book-description-content"
          v-html="formatDescription(item.description)"
        />
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
          Temas y Materias
        </h2>
        <div class="subject-tags">
          <a
            v-for="(subject, index) in item.subjects.slice(0, 15)"
            :key="index"
            :href="subject.url"
            target="_blank"
            rel="noopener noreferrer"
            class="subject-tag"
          >
            {{ subject.name }}
          </a>
        </div>
      </div>

      <div
        v-if="item.previewLink || item.infoLink || item.openLibraryUrl"
        class="book-links-section"
      >
        <h2 class="section-title">
          <i class="fas fa-external-link-alt" />
          Enlaces Externos
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
            Vista previa en Google Books
          </a>
          <a
            v-if="item.infoLink"
            :href="item.infoLink"
            target="_blank"
            rel="noopener noreferrer"
            class="external-link"
          >
            <i class="fab fa-google" />
            Más información en Google Books
          </a>
          <a
            v-if="item.openLibraryUrl"
            :href="item.openLibraryUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="external-link"
          >
            <i class="fas fa-book" />
            Ver en OpenLibrary
          </a>
        </div>
      </div>

      <div
        v-if="item.classifications"
        class="book-classifications-section"
      >
        <h2 class="section-title">
          <i class="fas fa-list-ol" />
          Clasificaciones
        </h2>
        <div class="classifications-content">
          <span
            v-if="item.classifications.lc"
            class="classification-item"
          >
            <strong>LC:</strong> {{ item.classifications.lc.join(', ') }}
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
    font-size: 1.2rem;
    color: var(--color-text-secondary);
    margin-bottom: spacing(md);

    i { color: var(--color-card-book-accent); }

    @include responsive-below(md) {
      font-size: 1rem;
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
    font-size: 0.95rem;

    i { color: var(--color-card-book-accent); }
  }

  .book-categories {
    display: flex;
    align-items: flex-start;
    gap: spacing(xs);
    margin-top: spacing(sm);

    > i {
      color: var(--color-card-book-accent);
      margin-top: 6px;
      flex-shrink: 0;
    }
  }

  .category-tags,
  .subject-tags {
    display: flex;
    flex-wrap: wrap;
    gap: spacing(xs);
  }

  .book-description-content {
    line-height: 1.8;
    color: var(--color-text);
    font-size: 1rem;
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
    font-size: 0.9rem;
  }

  .isbn-secondary {
    color: var(--color-text-muted);
    margin-left: 5px;
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

