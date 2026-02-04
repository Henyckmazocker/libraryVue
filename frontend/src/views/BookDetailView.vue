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
            ref="libraryBookItemRef"
            :book="book"
            :allowedUserStatuses="allowedStatuses"
            :editable="!!existingBook"
            :readonly="false"
            @delete-book="handleDeleteBook"
            @update-rating="handleUpdateRating"
            @update-statuses="handleUpdateStatuses"
            @update-progress="handleUpdateProgress"
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
        :is-visible="sessionHistoryModal.isVisible"
        :book="sessionHistoryModal.book"
        :history="sessionHistoryModal.history"
        @close="closeSessionHistoryModal"
      />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import LibraryBookItem from '@/components/Books/LibraryBookItem.vue';
import SessionHistoryModal from '@/components/Books/SessionHistoryModal.vue';
import EditionSelector from '@/components/Books/EditionSelector.vue';
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
const book = ref(null);
const isLoading = ref(true);
const error = ref(null);
const libraryBookItemRef = ref(null);
const sessionHistoryModal = ref({
  isVisible: false,
  book: {},
  history: []
});

// Computed
const allowedStatuses = computed(() => 
  Array.isArray(booksComposable.allowedStatuses.value) ? booksComposable.allowedStatuses.value : []
);

const existingBook = computed(() => {
  if (!book.value?.isbn) return null;
  return booksComposable.findBookByISBN(book.value.isbn);
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
  isLoading.value = true;
  error.value = null;

  try {
    Logger.debug(`[BookDetailView] Fetching details for ISBN: ${isbn}`);
    
    // Usar el endpoint del backend que maneja Google Books API
    // Esto evita problemas de cuota al hacer llamadas directas desde el cliente
    const response = await authStore.apiCall('search_google_books_isbn', { isbn });

    if (response.data && response.data.status === 'success' && response.data.data) {
      const bookData = response.data.data;
      Logger.debug(`[BookDetailView] Found book in Google Books:`, bookData.title);
      
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
        user_rating: null,
        userStatuses: []
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
    isLoading.value = false;
  }
};

const fetchFromOpenLibrary = async (isbn) => {
  Logger.debug(`[BookDetailView] Trying OpenLibrary for ISBN: ${isbn}`);
  
  // First get edition to extract work_key
  let workKey = null;
  try {
    const editionUrl = `https://openlibrary.org/isbn/${isbn}.json`;
    const editionResponse = await axios.get(editionUrl);
    const editionData = editionResponse.data;
    
    if (editionData.works && editionData.works.length > 0) {
      const workPath = editionData.works[0].key;
      workKey = workPath.split('/').pop();
      Logger.debug('[BookDetailView] Extracted work_key from OpenLibrary edition:', workKey);
    }
  } catch (editionErr) {
    Logger.debug('[BookDetailView] Could not fetch edition for work_key:', editionErr.message);
  }
  
  const openLibraryUrl = `https://openlibrary.org/api/books?bibkeys=ISBN:${isbn}&format=json&jscmd=data`;
  const response = await axios.get(openLibraryUrl);
  const data = response.data;
  const bookKey = `ISBN:${isbn}`;

  if (data[bookKey]) {
    const bookData = data[bookKey];
    
    // Extraer ISBN-10 e ISBN-13
    const isbn13 = bookData.identifiers?.isbn_13?.[0] || isbn;
    const isbn10 = bookData.identifiers?.isbn_10?.[0] || null;
    
    book.value = {
      isbn: isbn13,
      isbn10: isbn10,
      work_key: workKey, // Add work_key here
      title: bookData.title || "Título no disponible",
      author: (bookData.authors && bookData.authors.length > 0) 
        ? bookData.authors.map(a => a.name).join(', ') 
        : "Autor no disponible",
      publisher: (bookData.publishers && bookData.publishers.length > 0)
        ? bookData.publishers.map(p => p.name).join(', ')
        : "",
      publicationDate: bookData.publish_date || "",
      coverUrl: bookData.cover?.large || bookData.cover?.medium || bookData.cover?.small || "",
      pages: bookData.number_of_pages || null,
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
  } else {
    throw new Error('Book not found in OpenLibrary');
  }
};

const enrichWithOpenLibrary = async (isbn) => {
  Logger.debug(`[BookDetailView] Enriching with OpenLibrary data for ISBN: ${isbn}`);
  try {
    // First, try to get edition info to extract work_key
    const editionUrl = `https://openlibrary.org/isbn/${isbn}.json`;
    try {
      const editionResponse = await axios.get(editionUrl);
      const editionData = editionResponse.data;
      
      // Extract work_key from edition
      if (editionData.works && editionData.works.length > 0 && book.value) {
        const workPath = editionData.works[0].key; // e.g., "/works/OL123456W"
        book.value.work_key = workPath.split('/').pop(); // Extract "OL123456W"
        Logger.debug('[BookDetailView] Extracted work_key:', book.value.work_key);
      }
    } catch (editionErr) {
      Logger.debug('[BookDetailView] Could not fetch edition data:', editionErr.message);
    }
    
    // Now get full book data
    const openLibraryUrl = `https://openlibrary.org/api/books?bibkeys=ISBN:${isbn}&format=json&jscmd=data`;
    const response = await axios.get(openLibraryUrl);
    const data = response.data;
    const bookKey = `ISBN:${isbn}`;

    if (data[bookKey] && book.value) {
      const olData = data[bookKey];
      
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

const handleUpdateRating = async ({ rating }) => {
  if (book.value) {
    book.value.user_rating = rating;
  }
};

const handleUpdateStatuses = async ({ statuses }) => {
  if (book.value) {
    book.value.userStatuses = [...statuses];
  }
};

const handleUpdateProgress = async ({ isbn, updates }) => {
  try {
    if (Object.keys(updates).length === 0) {
      Logger.debug('[BookDetailView] Refreshing book after session change');
      await booksComposable.fetchBooks();
      return;
    }
    
    if (book.value && book.value.isbn === isbn) {
      Object.keys(updates).forEach(key => {
        book.value[key] = updates[key];
      });
      Logger.debug('[BookDetailView] Book progress updated locally:', { isbn, updates });
    }
  } catch (error) {
    Logger.error('[BookDetailView] Error updating book progress:', error);
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
  try {
    Logger.debug('[BookDetailView] Editing book in library:', bookData);
    
    // Prepare the data object with current values
    const data = {
      personalRating: book.value.user_rating,
      statuses: book.value.userStatuses,
      currentPage: book.value.currentPage
    };
    
    // Call editUserBook with separate parameters: isbn, userId, data, tags, notes
    const result = await booksComposable.editUserBook(
      book.value.isbn,
      null, // userId will be taken from auth on backend
      data,
      [], // tags
      []  // notes
    );
    
    if (result.success) {
      // Llamar al método de éxito del componente hijo
      if (libraryBookItemRef.value) {
        libraryBookItemRef.value.setEditSuccess();
      }
      Logger.debug('[BookDetailView] Book edited successfully');
    } else {
      // Llamar al método de error del componente hijo
      if (libraryBookItemRef.value) {
        libraryBookItemRef.value.setEditError();
      }
      Logger.error('[BookDetailView] Failed to edit book:', result.message);
    }
  } catch (err) {
    Logger.error('[BookDetailView] Error editing book:', err);
    // Llamar al método de error del componente hijo
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
  
  // Cargar libros y estados permitidos
  await Promise.all([
    booksComposable.books.value.length === 0 ? booksComposable.fetchBooks() : Promise.resolve(),
    allowedStatuses.value.length === 0 ? booksComposable.fetchAllowedStatuses() : Promise.resolve()
  ]);

  Logger.debug('[BookDetailView] After loading:', {
    totalBooksInStore: booksComposable.books.value.length,
    allowedStatusesCount: allowedStatuses.value.length,
    allowedStatuses: allowedStatuses.value
  });

  // Si hay datos en el state del router, usarlos
  if (route.state && route.state.book) {
    Logger.debug('[BookDetailView] Using book data from router state');
    book.value = route.state.book;
    isLoading.value = false;
  } else {
    // Si no, buscar el libro por ISBN
    await fetchBookDetails(route.params.isbn);
  }
  
  Logger.debug('[BookDetailView] Book loaded, checking if exists in library:', {
    bookIsbn: book.value?.isbn,
    existingBook: existingBook.value ? 'FOUND' : 'NOT FOUND'
  });
  
  // Si el libro ya existe en la biblioteca, mezclar los datos
  if (existingBook.value && book.value) {
    Logger.debug('[BookDetailView] Merging with existing book data from library:', {
      existingUserStatuses: existingBook.value.userStatuses,
      existingRating: existingBook.value.user_rating,
      existingRating_personal: existingBook.value.personal_rating,
      existingRating_rating: existingBook.value.rating,
      existingRating_userRating: existingBook.value.userRating,
      existingCurrentPage: existingBook.value.currentPage,
      existingBook_keys: Object.keys(existingBook.value)
    });
    
    // Usar la información de la edición guardada en la biblioteca
    book.value = {
      ...book.value,
      // Datos de usuario
      user_rating: existingBook.value.user_rating,
      userStatuses: existingBook.value.userStatuses || [],
      currentPage: existingBook.value.currentPage,
      totalPages: existingBook.value.totalPages || book.value.pages,
      
      // Datos de la edición específica guardada (si están disponibles)
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
      
      // Mantener work_key para el selector de ediciones
      work_key: book.value.work_key || existingBook.value.work_key
    };
    
    Logger.debug('[BookDetailView] Loaded saved edition data:', {
      isbn: book.value.isbn,
      title: book.value.title,
      publisher: book.value.publisher,
      publicationDate: book.value.publicationDate,
      userStatuses: book.value.userStatuses,
      user_rating: book.value.user_rating
    });
  } else {
    Logger.debug('[BookDetailView] No existing book found in library or book not loaded');
  }
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

<style scoped>
.book-detail-view {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background-color: var(--color-background-mute);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  cursor: pointer;
  font-size: 0.95rem;
  transition: all 0.2s ease;
  margin-bottom: 30px;
}

.back-button:hover {
  background-color: var(--color-background-soft);
  border-color: var(--color-border-hover);
  transform: translateX(-4px);
}

.back-button i {
  font-size: 1rem;
}

.book-detail-content {
  animation: fadeIn 0.3s ease-in;
}

/* Cabecera del libro */
.book-header {
  display: flex;
  gap: 30px;
  margin-bottom: 40px;
  padding: 30px;
  background: var(--color-background-mute);
  border-radius: 16px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.book-cover-large {
  flex-shrink: 0;
  width: 220px;
}

.cover-image-large {
  width: 100%;
  height: auto;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  border: 1px solid var(--color-border);
}

.cover-placeholder {
  width: 100%;
  aspect-ratio: 2/3;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-background-soft);
  border-radius: 12px;
  border: 2px dashed var(--color-border);
}

.cover-placeholder i {
  font-size: 4rem;
  color: var(--color-text-muted);
}

.book-main-info {
  flex: 1;
  min-width: 0;
}

.book-title-large {
  font-size: 2.2rem;
  font-weight: 700;
  color: var(--color-heading);
  margin: 0 0 15px 0;
  line-height: 1.3;
}

.book-author-large {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.2rem;
  color: var(--color-text-secondary);
  margin-bottom: 20px;
}

.book-author-large i {
  color: var(--color-primary);
}

.book-metadata {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  margin-bottom: 15px;
}

.metadata-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background: var(--color-background-soft);
  border-radius: 6px;
  font-size: 0.9rem;
  color: var(--color-text);
}

.metadata-item i {
  color: var(--color-primary);
  font-size: 0.85rem;
}

.book-language {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  color: var(--color-text-secondary);
  font-size: 0.95rem;
}

.book-language i {
  color: var(--color-primary);
}

.book-isbn-display {
  margin-bottom: 15px;
  padding: 10px 15px;
  background: var(--color-background-soft);
  border-left: 3px solid var(--color-primary);
  border-radius: 4px;
  font-size: 0.9rem;
  font-family: 'Courier New', monospace;
}

.isbn-secondary {
  color: var(--color-text-muted);
  margin-left: 5px;
}

.book-categories {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-top: 15px;
}

.book-categories i {
  color: var(--color-primary);
  margin-top: 6px;
  flex-shrink: 0;
}

.category-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.category-tag {
  padding: 4px 12px;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
  color: white;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}

/* Secciones */
.section-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 1.4rem;
  font-weight: 600;
  color: var(--color-heading);
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid var(--color-border);
}

.section-title i {
  color: var(--color-primary);
}

.book-description-section,
.book-subjects-section,
.book-links-section,
.book-classifications-section,
.library-form-section {
  margin-bottom: 35px;
  padding: 25px;
  background: var(--color-background-mute);
  border-radius: 12px;
}

.book-description-content {
  line-height: 1.8;
  color: var(--color-text);
  font-size: 1rem;
  text-align: justify;
}

/* Temas */
.subject-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.subject-tag {
  padding: 6px 14px;
  background: var(--color-background-soft);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  border-radius: 6px;
  font-size: 0.85rem;
  text-decoration: none;
  transition: all 0.2s ease;
}

.subject-tag:hover {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
  transform: translateY(-2px);
}

/* Enlaces externos */
.external-links {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.external-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 12px 18px;
  background: var(--color-background-soft);
  color: var(--color-text);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  text-decoration: none;
  font-size: 0.95rem;
  transition: all 0.2s ease;
  max-width: fit-content;
}

.external-link:hover {
  background: var(--color-primary);
  color: white;
  border-color: var(--color-primary);
  transform: translateX(4px);
}

.external-link i {
  font-size: 1.1rem;
}

/* Clasificaciones */
.classifications-content {
  display: flex;
  gap: 15px;
  flex-wrap: wrap;
}

.classification-item {
  padding: 8px 16px;
  background: var(--color-background-soft);
  border-radius: 6px;
  font-size: 0.9rem;
}

/* Separador */
.section-divider {
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--color-border), transparent);
  margin: 40px 0;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Estados de carga, error y vacío */
.loading-state,
.error-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  gap: 16px;
}

.loading-state i,
.error-state i,
.empty-state i {
  font-size: 3rem;
  color: var(--color-text-mute);
}

.loading-state i {
  color: var(--color-primary);
}

.error-state i {
  color: #ff6b6b;
}

.loading-state p,
.error-state p,
.empty-state p {
  font-size: 1.1rem;
  color: var(--color-text-mute);
  margin: 0;
}

.action-button {
  padding: 10px 20px;
  background-color: var(--color-primary);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 1rem;
  transition: background-color 0.2s ease;
}

.action-button:hover {
  background-color: var(--color-primary-dark);
}

/* Responsive */
@media (max-width: 768px) {
  .book-detail-view {
    padding: 15px;
  }

  .back-button {
    font-size: 0.9rem;
    padding: 8px 12px;
  }
  
  .book-header {
    flex-direction: column;
    padding: 20px;
    gap: 20px;
  }
  
  .book-cover-large,
  .cover-placeholder {
    width: 100%;
    max-width: 250px;
    margin: 0 auto;
  }
  
  .book-title-large {
    font-size: 1.6rem;
  }
  
  .book-author-large {
    font-size: 1rem;
  }
  
  .book-metadata {
    gap: 8px;
  }
  
  .metadata-item {
    font-size: 0.85rem;
    padding: 5px 10px;
  }
  
  .book-description-section,
  .book-subjects-section,
  .book-links-section,
  .book-classifications-section,
  .library-form-section {
    padding: 15px;
    margin-bottom: 25px;
  }
  
  .section-title {
    font-size: 1.2rem;
  }
  
  .category-tag,
  .subject-tag {
    font-size: 0.8rem;
    padding: 4px 10px;
  }
}
</style>
