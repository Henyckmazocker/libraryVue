<template>
  <div class="book-search-container">
    <!-- Search Section -->
    <GenericSearch :config="searchConfig" />
    <!-- Trending Books Section -->
    <TrendingCarousel
      :items="trendingBooks"
      :is-loading="isLoadingTrending"
      :error="errorTrending"
      type="books"
      :item-component="BookCarouselItem"
      :title="t('search.popularBooks')"
      :subtitle="t('search.popularBooksHint')"
      @item-click="handleTrendingClick"
    />
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import GenericSearch from '@/components/shared/GenericSearch.vue';
import TrendingCarousel from '@/components/TrendingCarousel.vue';
import BookCarouselItem from '@/components/Books/BookCarouselItem.vue';
import { useBooks } from '@/composables/useBooks';
import { useWorkSearch } from '@/composables/useWorkSearch';
import { useTrending } from '@/composables/useTrending';
import { useAuthStore } from '@/store/auth';
import { useBooksStore } from '@/store/books';
import { useUIStore } from '@/store/ui';
import { storeToRefs } from 'pinia';
import Logger from '@/utils/logger';
import { useI18n } from '@/composables/useI18n';
import { mediaRegistry, bookCoverUrl } from '@/config/mediaRegistry';

const { t } = useI18n();

// Router
const router = useRouter();

// Composables & Stores
const booksComposable = useBooks();
const { searchWorks, stale: worksStale, cachedAt: worksCachedAt } = useWorkSearch();
const authStore = useAuthStore();
const booksStore = useBooksStore();
const uiStore = useUIStore();
const { isAuthenticated } = storeToRefs(authStore);
const { 
  trendingBooks, 
  isLoadingBooks: isLoadingTrending, 
  errorBooks: errorTrending,
  fetchTrendingBooks 
} = useTrending();

// Cargar libros del usuario y trending al montar (solo si está autenticado)
onMounted(async () => {
  if (isAuthenticated.value) {
    // Cargar libros de la biblioteca para poder verificar qué items tiene el usuario
    if (booksStore.books.length === 0) {
      await booksStore.fetchBooks();
    }
    // Cargar trending books
    fetchTrendingBooks(10, 90); // 10 libros, últimos 90 días
  }
});

// Cargar libros cuando se autentique
watch(isAuthenticated, async (newValue) => {
  if (newValue) {
    // Cargar biblioteca del usuario
    if (booksStore.books.length === 0) {
      Logger.debug('[BookSearch] User authenticated, fetching user books...');
      await booksStore.fetchBooks();
    }
    // Cargar trending books
    if (trendingBooks.value.length === 0) {
      Logger.debug('[BookSearch] Fetching trending books...');
      fetchTrendingBooks(10, 90);
    }
  }
});

// Función para detectar si es ISBN o búsqueda por nombre
const detectSearchType = (query) => {
  const cleanQuery = query.replace(/[-\s]/g, ''); // Remover guiones y espacios
  const isNumeric = /^\d+$/.test(cleanQuery);
  const isValidISBN = isNumeric && (cleanQuery.length === 10 || cleanQuery.length === 13);
  
  return {
    type: isValidISBN ? 'direct' : 'name',
    isDirect: isValidISBN
  };
};

// Función para obtener URL de portada
// Handler de búsqueda para libros usando el composable
const searchBooks = async (query, searchType) => {
  if (searchType === 'name') {
    try {
      Logger.debug("Searching works by name using composable...");
      
      // Usar el composable para la búsqueda
      const works = await searchWorks(query, { 
        limit: 20, 
        enrich: false // No enriquecer con Google Books en búsqueda inicial
      });
      
      Logger.debug(`Found ${works.length} works from composable`);
      
      // El sobre, no la lista pelada: `GenericSearch` necesita la frescura para
      // decidir si pinta la franja de degradación.
      return {
        stale: worksStale.value,
        cached_at: worksCachedAt.value,
        results: works.map(work => ({
          isbn: work.sample_isbn || work.work_key,
          title: work.title || t('media.book.titleUnavailable'),
          author: Array.isArray(work.authors) ? work.authors : [work.authors_display || t('media.book.authorUnavailable')],
          cover_i: work.cover_url || work.cover_id || '', // Use cover_url (Google Books) or cover_id (OpenLibrary)
          coverUrl: work.cover_url || '',
          publisher: [],
          pages: null,
          genres: work.subjects || [],
          key: work.work_key,
          // Additional work metadata
          work_key: work.work_key,
          editions_count: work.editions_count || 0,
          first_publish_year: work.first_publish_year || null,
          languages: work.languages || []
        }))
      };

    } catch (error) {
      Logger.error("Work search failed:", error);
      throw new Error(t('bookSearch.searchFailed'));
    }
  }
  
  return [];
};


// Navegación a detalle
const navigateToDetail = (router, book) => {
  const isbn = Array.isArray(book.isbn) ? book.isbn[0] : book.isbn;
  
  if (!isbn) {
    Logger.warn('[BookSearch] Book has no ISBN, cannot navigate to detail');
    uiStore.showError(t('bookSearch.noIsbn'));
    return;
  }
  
  Logger.debug('[BookSearch] Navigating to book detail:', isbn);
  
  const bookData = {
    isbn: isbn,
    title: book.title || t('media.book.titleUnavailable'),
    authors: Array.isArray(book.author) ? book.author : (book.author ? [book.author] : [t('book.unknownAuthor')]),
    publicationDate: book.publicationDate || '',
    coverUrl: bookCoverUrl(book.cover_i),
    pages: book.pages || null,
    description: book.description || '',
    publishers: Array.isArray(book.publisher) ? book.publisher : (book.publisher ? [book.publisher] : []),
    rating: null,
    user_rating: null,
    userStatuses: [],
    genres: book.genres || []
  };
  
  router.push({
    name: 'BookDetail',
    params: { isbn: isbn },
    state: { book: JSON.parse(JSON.stringify(bookData)) }
  });
};

// Handler para clicks en trending
const handleTrendingClick = (book) => {
  Logger.debug('Trending book clicked:', book);
  
  // Navegar a detalle del libro trending
  const bookData = {
    isbn: book.isbn,
    title: book.title,
    authors: [book.author],
    publicationDate: '',
    coverUrl: book.cover_url,
    pages: null,
    description: '',
    publishers: [],
    rating: book.avg_rating || null,
    user_rating: null,
    userStatuses: [],
    genres: []
  };
  
  router.push({
    name: 'BookDetail',
    params: { isbn: book.isbn },
    state: { book: JSON.parse(JSON.stringify(bookData)) }
  });
};

// Obtener clave única del resultado
const getResultKey = (result) => {
  return result.key || result.isbn || `book-${Date.now()}-${Math.random()}`;
};

// Cargar estados permitidos
const fetchAllowedStatuses = async () => {
  await booksComposable.fetchAllowedStatuses();
  return Array.isArray(booksComposable.allowedStatuses.value) 
    ? booksComposable.allowedStatuses.value 
    : [];
};

// Configuración del componente genérico
const searchConfig = computed(() => ({
  title: t('searchPage.books.title'),
  inputs: [
    {
      type: 'auto',
      placeholder: t('searchPage.books.placeholder'),
      buttonText: '',
      idField: 'isbn',
      emptyMessage: t('searchPage.books.empty'),
      errorMessage: t('searchPage.books.error')
    }
  ],
  carouselItemComponent: BookCarouselItem,
  itemProp: 'book',
  media: 'book',
  staleProvider: t('bookSearch.openLibrary'),
  searchHandler: searchBooks,
  // La transformación vive en el registry desde el M1: la comparte con
  // el buscador general en vez de existir dos veces.
  transformResult: mediaRegistry.book.api.search.transform,
  navigateToDetail: navigateToDetail,
  getResultKey: getResultKey,
  fetchAllowedStatuses: fetchAllowedStatuses,
  detectSearchType: detectSearchType
}));
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

@use '@/assets/styles/components/search' as *;

.book-search-container {
  @include search-page;
}
</style>
