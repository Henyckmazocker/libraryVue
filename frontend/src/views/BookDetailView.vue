<template>
  <div class="book-detail-view">
      <!-- Botón de volver -->
      <button @click="goBack" class="back-button">
        <i class="fas fa-arrow-left"></i>
        <span>Volver a búsqueda</span>
      </button>

      <!-- Estado de carga -->
      <div v-if="isLoading" class="loading-state">
        <i class="fas fa-spinner fa-spin"></i>
        <p>Cargando información del libro...</p>
      </div>

      <!-- Mensaje de error -->
      <div v-else-if="error" class="error-state">
        <i class="fas fa-exclamation-circle"></i>
        <p>{{ error }}</p>
        <button @click="goBack" class="action-button">Volver a búsqueda</button>
      </div>

      <!-- Detalles del libro -->
      <div v-else-if="book" class="book-detail-content">
        <!-- Cabecera principal con portada y datos -->
        <div class="book-header">
          <div class="book-cover-large">
            <img v-if="book.coverUrl" :src="book.coverUrl" :alt="book.title" class="cover-image-large" />
            <div v-else class="cover-placeholder">
              <i class="fas fa-book"></i>
            </div>
          </div>
          
          <div class="book-main-info">
            <h1 class="book-title-large">{{ book.title }}</h1>
            
            <div v-if="book.author" class="book-author-large">
              <i class="fas fa-user"></i>
              <span>por {{ book.author }}</span>
            </div>
            
            <div class="book-metadata">
              <span v-if="book.publisher" class="metadata-item">
                <i class="fas fa-building"></i>
                {{ book.publisher }}
              </span>
              <span v-if="book.publicationDate" class="metadata-item">
                <i class="fas fa-calendar"></i>
                {{ book.publicationDate }}
              </span>
              <span v-if="book.pages" class="metadata-item">
                <i class="fas fa-file-alt"></i>
                {{ book.pages }} páginas
              </span>
            </div>
            
            <div v-if="book.language" class="book-language">
              <i class="fas fa-globe"></i>
              <span>{{ getLanguageName(book.language) }}</span>
            </div>
            
            <div v-if="book.isbn" class="book-isbn-display">
              <strong>ISBN:</strong> {{ book.isbn }}
              <span v-if="book.isbn10" class="isbn-secondary"> • ISBN-10: {{ book.isbn10 }}</span>
            </div>
            
            <!-- Categorías/Géneros -->
            <div v-if="book.genres && book.genres.length > 0" class="book-categories">
              <i class="fas fa-tags"></i>
              <div class="category-tags">
                <span v-for="(genre, index) in book.genres" :key="index" class="category-tag">
                  {{ genre }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Descripción del libro -->
        <div v-if="book.description" class="book-description-section">
          <h2 class="section-title">
            <i class="fas fa-book-open"></i>
            Descripción
          </h2>
          <div class="book-description-content" v-html="formatDescription(book.description)"></div>
        </div>

        <!-- Selector de Ediciones -->
        <EditionSelector 
          v-if="book.work_key"
          :work-key="book.work_key"
          :initial-selected-edition="book"
          :saved-isbn="existingBook ? existingBook.isbn : null"
          @edition-selected="handleEditionSelected"
        />

        <!-- Temas y materias (OpenLibrary) -->
        <div v-if="book.subjects && book.subjects.length > 0" class="book-subjects-section">
          <h2 class="section-title">
            <i class="fas fa-bookmark"></i>
            Temas y Materias
          </h2>
          <div class="subject-tags">
            <a 
              v-for="(subject, index) in book.subjects.slice(0, 15)" 
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

        <!-- Enlaces externos -->
        <div v-if="book.previewLink || book.infoLink || book.openLibraryUrl" class="book-links-section">
          <h2 class="section-title">
            <i class="fas fa-external-link-alt"></i>
            Enlaces Externos
          </h2>
          <div class="external-links">
            <a v-if="book.previewLink" :href="book.previewLink" target="_blank" rel="noopener noreferrer" class="external-link">
              <i class="fab fa-google"></i>
              Vista previa en Google Books
            </a>
            <a v-if="book.infoLink" :href="book.infoLink" target="_blank" rel="noopener noreferrer" class="external-link">
              <i class="fab fa-google"></i>
              Más información en Google Books
            </a>
            <a v-if="book.openLibraryUrl" :href="book.openLibraryUrl" target="_blank" rel="noopener noreferrer" class="external-link">
              <i class="fas fa-book"></i>
              Ver en OpenLibrary
            </a>
          </div>
        </div>

        <!-- Clasificaciones (si existen) -->
        <div v-if="book.classifications" class="book-classifications-section">
          <h2 class="section-title">
            <i class="fas fa-list-ol"></i>
            Clasificaciones
          </h2>
          <div class="classifications-content">
            <span v-if="book.classifications.lc" class="classification-item">
              <strong>LC:</strong> {{ book.classifications.lc.join(', ') }}
            </span>
          </div>
        </div>

        <!-- Separador visual -->
        <div class="section-divider"></div>

        <!-- Formulario de biblioteca -->
        <div class="library-form-section">
          <h2 class="section-title">
            <i :class="['fas', existingBook ? 'fa-edit' : 'fa-save']"></i>
            {{ existingBook ? 'Editar en Mi Biblioteca' : 'Añadir a Mi Biblioteca' }}
          </h2>
          <LibraryBookItem
            v-if="allowedStatuses.length > 0"
            ref="libraryBookItemRef"
            :book="book"
            :allowedUserStatuses="allowedStatuses"
            :editable="!!existingBook"
            @delete-book="handleDeleteBook"
            @save-book="handleSaveBook"
            @edit-item="handleEditBook"
            @show-session-history="handleShowSessionHistory"
          />
        </div>
      </div>

      <!-- Estado inicial sin libro -->
      <div v-else class="empty-state">
        <i class="fas fa-book"></i>
        <p>No se encontró información del libro</p>
        <button @click="goBack" class="action-button">Volver a búsqueda</button>
      </div>

      <!-- Session History Modal -->
      <SessionHistoryModal
        :visible="sessionHistoryModal.isVisible"
        :book="sessionHistoryModal.book"
        @close="closeSessionHistoryModal"
      />

      <!-- Edit Item Modal -->
      <EditItemModal
        v-if="editModal.isVisible"
        :item="editModal.item"
        :item-type="editModal.itemType"
        :allowed-statuses="allowedStatuses"
        :is-visible="editModal.isVisible"
        @close="closeEditModal"
        @saved="handleModalSaved"
      />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, toRaw } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import LibraryBookItem from '@/components/Books/LibraryBookItem.vue';
import SessionHistoryModal from '@/components/Books/SessionHistoryModal.vue';
import EditionSelector from '@/components/Books/EditionSelector.vue';
import EditItemModal from '@/components/EditItemModal.vue';
import { useBooks } from '@/composables/useBooks';
import { useUIStore } from '@/store/ui';
import { useAuth } from '@/composables/useAuth';
import { useAuthStore } from '@/store/auth';
import { getLanguageName } from '@/utils/languageConstants';
import Logger from '@/utils/logger';

const route = useRoute();
const router = useRouter();
const { isAuthenticated } = useAuth();
const authStore = useAuthStore();
const booksComposable = useBooks();
const uiStore = useUIStore();

// Estados
const book = ref((history.state && history.state.book) ? history.state.book : null);
// Si venimos con datos en el router state, no mostrar spinner (transición seamless)
const isLoading = ref(!book.value);
const error = ref(null);
const libraryBookItemRef = ref(null);
const sessionHistoryModal = ref({
  isVisible: false,
  book: {},
  history: []
});
const editModal = ref({
  isVisible: false,
  item: null,
  itemType: 'book'
});

// Computed
const allowedStatuses = computed(() => 
  Array.isArray(booksComposable.allowedStatuses.value) ? booksComposable.allowedStatuses.value : []
);

const existingBook = computed(() => {
  if (!book.value) return null;
  // Buscar por isbn actual o por el isbn original de la ruta
  // (Google Books puede devolver un isbn_13 distinto al guardado en la biblioteca)
  return booksComposable.findBookByISBN(book.value.isbn)
      || booksComposable.findBookByISBN(route.params.isbn);
});

// Métodos
const goBack = () => {
  if (window.history.length > 1) {
    router.back();
  } else {
    router.push({ name: 'Books' });
  }
};

const fetchBookDetails = async (isbn) => {
  // Solo mostrar spinner si no hay datos previos (evitar flash en enrichment)
  const isBackgroundEnrichment = !!book.value;
  if (!isBackgroundEnrichment) {
    isLoading.value = true;
  }
  error.value = null;

  try {
    Logger.debug(`[BookDetailView] Fetching details for ISBN: ${isbn}`);
    
    // Usar el endpoint del backend que maneja Google Books API
    // Esto evita problemas de cuota al hacer llamadas directas desde el cliente
    const response = await authStore.apiCall('search_google_books_isbn', { isbn });

    if (response.data && response.data.status === 'success' && response.data.data) {
      const bookData = response.data.data;
      Logger.debug(`[BookDetailView] Found book in Google Books:`, bookData.title);
      
      // Preservar datos de usuario que ya tenía book.value (vienen eager desde history.state)
      // para evitar parpadeo a 0 estrellas durante el enriquecimiento en background.
      const previousUserData = book.value || {};

      // Transformar datos de Google Books al formato esperado
      book.value = {
        isbn: bookData.isbn_13 || isbn,
        isbn10: bookData.isbn_10 || null,
        title: bookData.title || "Título no disponible",
        author: (bookData.authors && bookData.authors.length > 0) ? bookData.authors.join(', ') : "Autor no disponible",
        publisher: bookData.publisher || "",
        publicationDate: bookData.published_date || "",
        coverUrl: bookData.cover_url_large || bookData.cover_url_medium || bookData.cover_url_small || "",
        pages: bookData.page_count || null,
        description: bookData.description || "",
        genres: bookData.categories || [],
        publishers: bookData.publisher ? [bookData.publisher] : [],
        language: bookData.language || null,
        previewLink: bookData.preview_link || null,
        infoLink: bookData.info_link || null,
        rating: null,
        // Preservar datos de usuario si ya existían (eager load)
        user_rating: previousUserData.user_rating ?? null,
        userStatuses: previousUserData.userStatuses ?? [],
        currentPage: previousUserData.currentPage,
        totalPages: previousUserData.totalPages,
        ownershipFormat: previousUserData.ownershipFormat ?? previousUserData.ownership_format ?? null,
        ownership_format: previousUserData.ownership_format ?? previousUserData.ownershipFormat ?? null,
        ownership_format_id: previousUserData.ownership_format_id ?? null,
        tags: previousUserData.tags ?? null
      };
      
      // Intentar enriquecer con datos de OpenLibrary (subjects, clasificaciones)
      try {
        await enrichWithOpenLibrary(isbn);
      } catch (err) {
        Logger.warn('[BookDetailView] Could not enrich with OpenLibrary data:', err);
      }
      
      Logger.debug(`[BookDetailView] Book loaded successfully:`, book.value.title);
    } else {
      // Intentar con OpenLibrary como fallback
      await fetchFromOpenLibrary(isbn);
    }
  } catch (err) {
    Logger.error(`[BookDetailView] Error fetching book details:`, err);
    // Intentar con OpenLibrary como fallback
    try {
      await fetchFromOpenLibrary(isbn);
    } catch (fallbackErr) {
      error.value = 'No se pudo obtener información del libro. Verifica el ISBN.';
      Logger.error(`[BookDetailView] Fallback also failed:`, fallbackErr);
    }
  } finally {
    if (!isBackgroundEnrichment) {
      isLoading.value = false;
    }
  }
};

const fetchFromOpenLibrary = async (isbn) => {
  const apiUrl = process.env.VUE_APP_API_URL || '/index.php';
  const response = await axios.post(apiUrl, { action: 'get_openlibrary_book_by_isbn', isbn });

  if (response.data?.status !== 'success' || !response.data?.data) {
    throw new Error('Book not found in OpenLibrary');
  }

  const { edition, book: bookData } = response.data.data;

  // Extract work_key from edition
  let workKey = null;
  if (edition?.works && edition.works.length > 0) {
    const workPath = edition.works[0].key;
    workKey = workPath.split('/').pop();
    Logger.debug('[BookDetailView] Extracted work_key from OpenLibrary edition:', workKey);
  }

  if (!bookData) {
    throw new Error('Book not found in OpenLibrary');
  }

  // Extraer ISBN-10 e ISBN-13
  const isbn13 = bookData.identifiers?.isbn_13?.[0] || isbn;
  const isbn10 = bookData.identifiers?.isbn_10?.[0] || null;
    
  book.value = {
    isbn: isbn13,
    isbn10: isbn10,
    work_key: workKey,
    title: bookData.title || "Título no disponible",
    author: (bookData.authors && bookData.authors.length > 0) 
      ? bookData.authors.map(a => a.name).join(', ') 
      : "Autor no disponible",
    publisher: (bookData.publishers && bookData.publishers.length > 0)
      ? bookData.publishers.map(p => p.name).join(', ')
      : "",
    publicationDate: bookData.publish_date || "",
    coverUrl: bookData.cover?.large || bookData.cover?.medium || bookData.cover?.small || "",
    pages: bookData.number_of_pages || (bookData.pagination ? (parseInt(String(bookData.pagination)) || null) : null) || null,
    description: bookData.notes || "",
    genres: bookData.subjects ? bookData.subjects.slice(0, 5).map(s => s.name) : [],
    subjects: bookData.subjects || [],
    publishers: (bookData.publishers && bookData.publishers.length > 0)
      ? bookData.publishers.map(p => p.name)
      : [],
    openLibraryUrl: bookData.url || null,
    classifications: bookData.classifications?.lc_classifications ? {
      lc: bookData.classifications.lc_classifications
    } : null,
    rating: null,
    user_rating: null,
    userStatuses: []
  };
  Logger.debug(`[BookDetailView] Book loaded from OpenLibrary:`, book.value.title);
};

const enrichWithOpenLibrary = async (isbn) => {
  Logger.debug(`[BookDetailView] Enriching with OpenLibrary data for ISBN: ${isbn}`);
  try {
    const apiUrl = process.env.VUE_APP_API_URL || '/index.php';
    const response = await axios.post(apiUrl, { action: 'get_openlibrary_book_by_isbn', isbn });

    if (response.data?.status !== 'success' || !response.data?.data) return;

    const { edition, book: olData } = response.data.data;

    // Extract work_key from edition
    if (edition?.works && edition.works.length > 0 && book.value) {
      const workPath = edition.works[0].key; // e.g., "/works/OL123456W"
      book.value.work_key = workPath.split('/').pop(); // Extract "OL123456W"
      Logger.debug('[BookDetailView] Extracted work_key:', book.value.work_key);
    }

    if (olData && book.value) {
      // Enriquecer con subjects si no los tenemos
      if (olData.subjects && olData.subjects.length > 0) {
        book.value.subjects = olData.subjects;
        // Si no teníamos géneros, usar los primeros subjects
        if (!book.value.genres || book.value.genres.length === 0) {
          book.value.genres = olData.subjects.slice(0, 5).map(s => s.name);
        }
      }
      
      // Añadir URL de OpenLibrary
      if (olData.url) {
        book.value.openLibraryUrl = olData.url;
      }
      
      // Añadir clasificaciones
      if (olData.classifications?.lc_classifications) {
        book.value.classifications = {
          lc: olData.classifications.lc_classifications
        };
      }

      // Actualizar páginas desde OpenLibrary si Google Books no las tenía
      if (!book.value.pages) {
        const olPages = olData.number_of_pages
          || (olData.pagination ? (parseInt(String(olData.pagination)) || null) : null);
        if (olPages) {
          book.value.pages = olPages;
        }
      }
      
      Logger.debug('[BookDetailView] Successfully enriched with OpenLibrary data');
    }
  } catch (err) {
    Logger.warn('[BookDetailView] Could not enrich with OpenLibrary:', err);
  }
};

const handleDeleteBook = async ({ isbn }) => {
  if (!confirm(`Are you sure you want to delete the book with ISBN: ${isbn}?`)) return;
  
  try {
    const result = await booksComposable.deleteBook(isbn);
    
    if (result.success) {
      Logger.debug('[BookDetailView] Book deleted successfully');
      // Navigate back to books list or library
      router.push({ name: 'Books' });
    } else {
      Logger.error('[BookDetailView] Failed to delete book:', result.message);
      alert(result.message || 'Failed to delete book.');
    }
  } catch (error) {
    Logger.error('[BookDetailView] Error deleting book:', error);
    alert('Error connecting to backend to delete book.');
  }
};

const handleEditionSelected = (edition) => {
  Logger.debug('[BookDetailView] Edition selected, updating book info:', edition);
  
  if (!book.value || !edition) return;
  
  // Obtener el nuevo ISBN de la edición seleccionada
  const newIsbn = edition.isbn_13 || edition.isbn_10;
  
  // Verificar si esta edición específica existe en la biblioteca
  const existingEditionInLibrary = newIsbn ? booksComposable.findBookByISBN(newIsbn) : null;
  
  Logger.debug('[BookDetailView] Checking for existing edition in library:', {
    newIsbn,
    found: !!existingEditionInLibrary
  });
  
  // Determinar qué datos de usuario usar
  let userData;
  if (existingEditionInLibrary) {
    // Si la nueva edición ya está en la biblioteca, usar sus datos
    Logger.debug('[BookDetailView] Using user data from library for this edition');
    userData = {
      user_rating: existingEditionInLibrary.user_rating,
      userStatuses: existingEditionInLibrary.userStatuses || [],
      currentPage: existingEditionInLibrary.currentPage,
      totalPages: existingEditionInLibrary.totalPages
    };
  } else {
    // Si es una edición nueva, resetear los datos de usuario
    Logger.debug('[BookDetailView] Edition not in library, resetting user data');
    userData = {
      user_rating: null,
      userStatuses: [],
      currentPage: 0,
      totalPages: edition.number_of_pages || book.value.pages
    };
  }
  
  // Actualizar la información del libro con los datos de la edición seleccionada
  book.value = {
    ...book.value,
    // Mantener work_key para el selector de ediciones
    work_key: book.value.work_key,
    // Actualizar con datos de la edición
    isbn: newIsbn || book.value.isbn,
    isbn10: edition.isbn_10 || book.value.isbn10,
    title: edition.title || book.value.title,
    publisher: edition.publishers && edition.publishers.length > 0 
      ? edition.publishers[0] 
      : book.value.publisher,
    publishers: edition.publishers || book.value.publishers,
    publicationDate: edition.publish_date || book.value.publicationDate,
    pages: edition.number_of_pages || book.value.pages,
    coverUrl: edition.cover_url || book.value.coverUrl,
    language: (edition.languages && edition.languages.length > 0) 
      ? (typeof edition.languages[0] === 'string' ? edition.languages[0] : (edition.languages[0].key || 'en'))
      : book.value.language,
    physical_format: edition.physical_format || null,
    // Mantener datos que no están en la edición
    author: book.value.author,
    description: book.value.description,
    genres: book.value.genres,
    subjects: book.value.subjects,
    rating: book.value.rating,
    // Aplicar los datos de usuario determinados
    user_rating: userData.user_rating,
    userStatuses: userData.userStatuses,
    currentPage: userData.currentPage,
    totalPages: userData.totalPages
  };
  
  Logger.debug('[BookDetailView] Book info updated with edition data:', {
    isbn: book.value.isbn,
    hasUserData: !!existingEditionInLibrary,
    userStatuses: userData.userStatuses
  });
  
  const message = existingEditionInLibrary 
    ? 'Edición seleccionada. Esta edición ya está en tu biblioteca.'
    : 'Edición seleccionada. Los datos del libro se han actualizado.';
  uiStore.showSuccess(message);
};

const handleSaveBook = async (bookData) => {
  try {
    Logger.debug('[BookDetailView] Saving book to library:', bookData);
    
    const result = await booksComposable.addBook(bookData.book, bookData.statuses);
    
    if (result.success) {
      // Llamar al método de éxito del componente hijo
      if (libraryBookItemRef.value) {
        libraryBookItemRef.value.setSaveSuccess();
      }
      // Actualizar el libro local con los datos guardados
      if (book.value) {
        book.value.userStatuses = bookData.statuses;
      }
    } else {
      // Llamar al método de error del componente hijo
      if (libraryBookItemRef.value) {
        libraryBookItemRef.value.setSaveError();
      }
    }
  } catch (err) {
    Logger.error('[BookDetailView] Error saving book:', err);
    // Llamar al método de error del componente hijo
    if (libraryBookItemRef.value) {
      libraryBookItemRef.value.setSaveError();
    }
  }
};

const handleEditBook = async (bookData) => {
  Logger.debug('[BookDetailView] Opening edit modal for book:', bookData);

  // Ensure books are loaded in the store before opening the modal.
  if (booksComposable.books.value.length === 0) {
    await booksComposable.fetchBooks();
  }

  const storeBook = existingBook.value ? toRaw(existingBook.value) : null;

  const itemData = storeBook
    ? {
        ...book.value,
        user_edition_id: storeBook.user_edition_id,
        user_rating: storeBook.user_rating ?? null,
        userStatuses: Array.isArray(storeBook.userStatuses) ? [...storeBook.userStatuses] : [],
        currentPage: storeBook.currentPage ?? book.value?.currentPage,
        totalPages: storeBook.totalPages ?? book.value?.totalPages ?? book.value?.pages,
        ownershipFormat: storeBook.ownershipFormat ?? storeBook.ownership_format ?? null,
        ownership_format: storeBook.ownership_format ?? storeBook.ownershipFormat ?? null,
        ownership_format_id: storeBook.ownershipFormat?.id ?? storeBook.ownership_format?.id ?? null,
        tags: storeBook.tags ?? null,
      }
    : book.value;

  editModal.value = {
    isVisible: true,
    item: itemData,
    itemType: 'book'
  };
};

const closeEditModal = () => {
  editModal.value = {
    isVisible: false,
    item: null,
    itemType: 'book'
  };
};

const handleModalSaved = async (updatedItem) => {
  Logger.debug('[BookDetailView] Book saved from modal, updating local data', updatedItem);
  
  // Cerrar el modal
  closeEditModal();
  
  try {
    // Actualizar inmediatamente con datos del evento (optimista)
    if (book.value && updatedItem) {
      book.value = {
        ...book.value,
        ...updatedItem,
        user_rating: updatedItem.user_rating,
        userStatuses: updatedItem.userStatuses,
        currentPage: updatedItem.currentPage ?? book.value.currentPage
      };
    }
    
    // Actualizar en el store local de books también
    const bookInStore = booksComposable.findBookByISBN(book.value.isbn);
    if (bookInStore) {
      Object.assign(bookInStore, updatedItem);
    }
    
    // Llamar al método de éxito del componente hijo
    if (libraryBookItemRef.value) {
      libraryBookItemRef.value.setEditSuccess();
    }
    
    uiStore.showSuccess('Libro actualizado correctamente');
    
    // Recargar en segundo plano para sincronizar con cambios que el backend
    // pueda haber aplicado automáticamente (p. ej. añadir 'read' al llegar al 100%).
    // Tras refrescar, re-mezclar con book.value para que la tarjeta refleje los cambios.
    setTimeout(() => {
      booksComposable.fetchBooks()
        .then(() => _mergeExistingBookData())
        .catch(err => {
          Logger.error('[BookDetailView] Background refresh failed:', err);
        });
    }, 500);
  } catch (err) {
    Logger.error('[BookDetailView] Error updating book data:', err);
    if (libraryBookItemRef.value) {
      libraryBookItemRef.value.setEditError();
    }
  }
};

const handleShowSessionHistory = async (data) => {
  try {
    Logger.debug('[BookDetailView] Showing session history for book:', data.book.title);
    
    sessionHistoryModal.value = {
      isVisible: true,
      book: data.book,
      history: data.history || []
    };
  } catch (err) {
    Logger.error('[BookDetailView] Error showing session history:', err);
    uiStore.showError('Error al cargar el historial de sesiones');
  }
};

const closeSessionHistoryModal = () => {
  sessionHistoryModal.value = {
    isVisible: false,
    book: {},
    history: []
  };
};

// Métodos de formateo
const formatDescription = (description) => {
  if (!description) return '';
  
  // Crear un elemento temporal para decodificar HTML entities
  const textarea = document.createElement('textarea');
  textarea.innerHTML = description;
  const decodedText = textarea.value;
  
  // Convertir saltos de línea a <br> y mantener formato básico
  return decodedText.replace(/\n/g, '<br>');
};

// Helper function to load book data
const loadBookData = async () => {
  Logger.debug('[BookDetailView] Loading book data');

  // Datos ya cargados eagerly desde history.state, o via route.state
  const hasEagerData = !!book.value;

  if (hasEagerData || (route.state && route.state.book)) {
    if (!hasEagerData && route.state.book) {
      book.value = route.state.book;
    }
    isLoading.value = false;
    Logger.debug('[BookDetailView] Using pre-loaded book data (seamless)');

    // Cargar datos de biblioteca en segundo plano para enriquecer
    await _loadLibraryContext();

    // Enriquecer con datos completos de la API en segundo plano (sin mostrar spinner)
    fetchBookDetails(route.params.isbn)
      .then(() => _mergeExistingBookData())
      .catch(err =>
        Logger.warn('[BookDetailView] Background enrichment failed:', err)
      );
  } else {
    // Sin state: acceso directo por URL — mostrar spinner
    isLoading.value = true;
    await _loadLibraryContext();
    await fetchBookDetails(route.params.isbn);
  }

  // Mezclar con datos de biblioteca si el libro ya existe
  _mergeExistingBookData();
};

/** Carga estados permitidos y libros del usuario (necesario para detectar existencia) */
const _loadLibraryContext = async () => {
  if (allowedStatuses.value.length === 0) {
    await booksComposable.fetchAllowedStatuses();
  }
  if (booksComposable.books.value.length === 0) {
    Logger.debug('[BookDetailView] Loading user books to check if book exists');
    await booksComposable.fetchBooks();
  }
};

/** Mezcla datos del libro con la versión guardada en biblioteca (si existe) */
const _mergeExistingBookData = () => {
  if (!existingBook.value || !book.value) {
    Logger.debug('[BookDetailView] No existing book found in library or book not loaded');
    return;
  }

  Logger.debug('[BookDetailView] Merging with existing book data from library');

  book.value = {
    ...book.value,
    // Datos de usuario
    user_rating: existingBook.value.user_rating,
    userStatuses: existingBook.value.userStatuses || [],
    currentPage: existingBook.value.currentPage,
    totalPages: existingBook.value.totalPages || book.value.pages,
    ownershipFormat: existingBook.value.ownershipFormat ?? existingBook.value.ownership_format ?? null,
    ownership_format: existingBook.value.ownership_format ?? existingBook.value.ownershipFormat ?? null,
    ownership_format_id: existingBook.value.ownership_format_id ?? existingBook.value.ownershipFormat?.id ?? null,
    tags: existingBook.value.tags ?? null,
    // Datos de la edición guardada
    isbn: existingBook.value.isbn || book.value.isbn,
    isbn10: existingBook.value.isbn10 || book.value.isbn10,
    title: existingBook.value.title || book.value.title,
    author: existingBook.value.author || book.value.author,
    publisher: existingBook.value.publisher || book.value.publisher,
    publishers: existingBook.value.publishers || book.value.publishers,
    publicationDate: existingBook.value.publicationDate || book.value.publicationDate,
    pages: existingBook.value.pages || book.value.pages,
    coverUrl: existingBook.value.coverUrl || book.value.coverUrl,
    language: existingBook.value.language || book.value.language,
    physical_format: existingBook.value.physical_format || book.value.physical_format,
    work_key: book.value.work_key || existingBook.value.work_key
  };

  Logger.debug('[BookDetailView] Loaded saved edition data:', {
    isbn: book.value.isbn,
    title: book.value.title,
    userStatuses: book.value.userStatuses,
    user_rating: book.value.user_rating
  });
};

// Lifecycle
onMounted(async () => {
  Logger.debug('[BookDetailView] Component mounted');
  
  // Wait for authentication before loading data
  if (isAuthenticated.value) {
    await loadBookData();
  }
});

// Watch for authentication changes
watch(isAuthenticated, async (newValue) => {
  if (newValue && !book.value) {
    Logger.debug('[BookDetailView] User authenticated, loading book data...');
    await loadBookData();
  }
});
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

