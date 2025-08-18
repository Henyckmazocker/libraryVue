<template>
  <div class="library-management-example">
    <div class="header">
      <h1>📚 Ejemplo de Gestión de Biblioteca</h1>
      <p>Demostración de los composables de gestión de biblioteca</p>
    </div>

    <!-- Tabs para diferentes secciones -->
    <div class="tabs">
      <button 
        v-for="tab in tabs" 
        :key="tab.id"
        @click="activeTab = tab.id"
        :class="['tab', { active: activeTab === tab.id }]"
      >
        {{ tab.icon }} {{ tab.label }}
      </button>
    </div>

    <!-- Tab: Libros -->
    <div v-if="activeTab === 'books'" class="tab-content">
      <h2>📖 Gestión de Libros</h2>
      
      <!-- Búsqueda de libros -->
      <div class="search-section">
        <h3>Buscar Libros</h3>
        <input 
          v-model="bookSearch.query.value"
          placeholder="Buscar por ISBN o título..."
          type="text"
          class="search-input"
        />
        <div v-if="bookSearch.isSearching.value" class="loading">
          🔍 Buscando...
        </div>
        <div v-if="bookSearch.hasResults.value" class="results">
          <h4>Resultados de búsqueda ({{ bookSearch.results.value.length }})</h4>
          <div v-for="book in bookSearch.results.value" :key="book.isbn" class="result-item">
            <div class="book-info">
              <img v-if="book.coverUrl" :src="book.coverUrl" :alt="book.title" class="book-cover" />
              <div>
                <h5>{{ book.title }}</h5>
                <p>{{ book.author }}</p>
                <p><strong>ISBN:</strong> {{ book.isbn }}</p>
              </div>
            </div>
            <button @click="addBookToLibrary(book)" class="add-button">
              ➕ Agregar a mi biblioteca
            </button>
          </div>
        </div>
      </div>

      <!-- Mi biblioteca de libros -->
      <div class="library-section">
        <h3>Mi Biblioteca de Libros</h3>
        <div class="stats">
          <span class="stat">📚 Total: {{ booksComposable.totalBooks.value }}</span>
          <span class="stat">⭐ Con calificación: {{ booksComposable.booksWithRating.value.length }}</span>
        </div>
        
        <div v-if="booksComposable.isLoading.value" class="loading">
          Cargando libros...
        </div>
        
        <div v-else-if="booksComposable.hasBooks.value" class="items-grid">
          <div v-for="book in booksComposable.books.value" :key="book.isbn" class="item-card">
            <img v-if="book.coverUrl" :src="book.coverUrl" :alt="book.title" class="item-image" />
            <div class="item-info">
              <h4>{{ book.title }}</h4>
              <p>{{ book.author }}</p>
              
              <!-- Rating -->
              <div class="rating">
                <span 
                  v-for="star in 5" 
                  :key="star"
                  @click="updateBookRating(book.isbn, star)"
                  :class="['star', { filled: star <= (book.user_rating || 0) }]"
                >
                  ⭐
                </span>
              </div>
              
              <!-- Estados -->
              <div class="statuses">
                <span 
                  v-for="status in book.userStatuses || []" 
                  :key="status"
                  class="status-tag"
                >
                  {{ status }}
                </span>
              </div>
              
              <button @click="removeBook(book.isbn)" class="remove-button">
                🗑️ Eliminar
              </button>
            </div>
          </div>
        </div>
        
        <div v-else class="empty-state">
          <p>📚 No tienes libros en tu biblioteca</p>
          <p>Usa la búsqueda para agregar algunos</p>
        </div>
      </div>
    </div>

    <!-- Tab: Películas -->
    <div v-if="activeTab === 'movies'" class="tab-content">
      <h2>🎬 Gestión de Películas</h2>
      
      <!-- Búsqueda de películas -->
      <div class="search-section">
        <h3>Buscar Películas</h3>
        <input 
          v-model="movieSearch.query.value"
          placeholder="Buscar por título..."
          type="text"
          class="search-input"
        />
        <div v-if="movieSearch.isSearching.value" class="loading">
          🔍 Buscando...
        </div>
        <div v-if="movieSearch.hasResults.value" class="results">
          <h4>Resultados de búsqueda ({{ movieSearch.results.value.length }})</h4>
          <div v-for="movie in movieSearch.results.value" :key="movie.tmdbId" class="result-item">
            <div class="movie-info">
              <img v-if="movie.posterUrl" :src="movie.posterUrl" :alt="movie.title" class="movie-poster" />
              <div>
                <h5>{{ movie.title }}</h5>
                <p><strong>Director:</strong> {{ movie.director }}</p>
                <p><strong>Año:</strong> {{ movie.releaseDate }}</p>
                <p><strong>Género:</strong> {{ movie.genre }}</p>
              </div>
            </div>
            <button @click="addMovieToLibrary(movie)" class="add-button">
              ➕ Agregar a mi biblioteca
            </button>
          </div>
        </div>
      </div>

      <!-- Mi biblioteca de películas -->
      <div class="library-section">
        <h3>Mi Biblioteca de Películas</h3>
        <div class="stats">
          <span class="stat">🎬 Total: {{ moviesComposable.totalMovies.value }}</span>
          <span class="stat">⭐ Con calificación: {{ moviesComposable.moviesWithRating.value.length }}</span>
        </div>
        
        <div v-if="moviesComposable.isLoading.value" class="loading">
          Cargando películas...
        </div>
        
        <div v-else-if="moviesComposable.hasMovies.value" class="items-grid">
          <div v-for="movie in moviesComposable.movies.value" :key="movie.tmdbId" class="item-card">
            <img v-if="movie.posterUrl" :src="movie.posterUrl" :alt="movie.title" class="item-image" />
            <div class="item-info">
              <h4>{{ movie.title }}</h4>
              <p>{{ movie.director }}</p>
              
              <!-- Rating -->
              <div class="rating">
                <span 
                  v-for="star in 5" 
                  :key="star"
                  @click="updateMovieRating(movie.tmdbId, star)"
                  :class="['star', { filled: star <= (movie.user_rating || 0) }]"
                >
                  ⭐
                </span>
              </div>
              
              <!-- Estados -->
              <div class="statuses">
                <span 
                  v-for="status in movie.userStatuses || []" 
                  :key="status"
                  class="status-tag"
                >
                  {{ status }}
                </span>
              </div>
              
              <button @click="removeMovie(movie.tmdbId)" class="remove-button">
                🗑️ Eliminar
              </button>
            </div>
          </div>
        </div>
        
        <div v-else class="empty-state">
          <p>🎬 No tienes películas en tu biblioteca</p>
          <p>Usa la búsqueda para agregar algunas</p>
        </div>
      </div>
    </div>

    <!-- Tab: Importación -->
    <div v-if="activeTab === 'import'" class="tab-content">
      <h2>📁 Importación de Archivos</h2>
      
      <div class="import-section">
        <!-- Selector de servicio -->
        <div class="service-selector">
          <h3>Seleccionar Servicio</h3>
          <div class="services-grid">
            <div 
              v-for="service in fileImport.availableServices.value" 
              :key="service.id"
              @click="fileImport.setService(service.id)"
              :class="['service-card', { selected: fileImport.selectedService.value === service.id }]"
            >
              <i :class="service.icon"></i>
              <h4>{{ service.name }}</h4>
              <p>{{ service.description }}</p>
              <small>Tipos: {{ service.acceptedTypes }}</small>
            </div>
          </div>
        </div>

        <!-- Selector de archivo -->
        <div v-if="fileImport.selectedService.value" class="file-selector">
          <h3>Seleccionar Archivo</h3>
          <input 
            type="file" 
            :accept="fileImport.acceptedFileTypes.value"
            @change="handleFileSelect"
            class="file-input"
          />
          <div v-if="fileImport.selectedFile.value" class="file-info">
            <p><strong>Archivo:</strong> {{ fileImport.selectedFile.value.name }}</p>
            <p><strong>Tamaño:</strong> {{ formatFileSize(fileImport.selectedFile.value.size) }}</p>
          </div>
        </div>

        <!-- Botón de importación -->
        <div v-if="fileImport.canImport.value" class="import-actions">
          <button @click="startImport" class="import-button">
            📤 Iniciar Importación
          </button>
        </div>

        <!-- Progreso de importación -->
        <div v-if="fileImport.isProcessing.value" class="import-progress">
          <h3>Importando...</h3>
          <div class="progress-bar">
            <div 
              class="progress-fill" 
              :style="{ width: fileImport.importProgress.value + '%' }"
            ></div>
          </div>
          <p>{{ fileImport.importProgress.value }}% completado</p>
        </div>

        <!-- Resultado de importación -->
        <div v-if="fileImport.isSuccess.value" class="import-success">
          <h3>✅ Importación Exitosa</h3>
          <div v-if="importStats" class="import-stats">
            <p><strong>Total de elementos:</strong> {{ importStats.totalItems }}</p>
            <p><strong>Elementos exitosos:</strong> {{ importStats.successfulItems }}</p>
            <p><strong>Elementos fallidos:</strong> {{ importStats.failedItems }}</p>
            <p><strong>Elementos duplicados:</strong> {{ importStats.duplicateItems }}</p>
          </div>
        </div>

        <!-- Error de importación -->
        <div v-if="fileImport.isError.value" class="import-error">
          <h3>❌ Error en la Importación</h3>
          <p>{{ fileImport.error.value }}</p>
          <button @click="fileImport.resetImport()" class="retry-button">
            🔄 Intentar de nuevo
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useBooks } from '@/composables/useBooks';
import { useMovies } from '@/composables/useMovies';
import { useFileImport } from '@/composables/useFileImport';
import { useSearch } from '@/composables/useSearch';

// Estado del componente
const activeTab = ref('books');

const tabs = [
  { id: 'books', label: 'Libros', icon: '📖' },
  { id: 'movies', label: 'Películas', icon: '🎬' },
  { id: 'import', label: 'Importación', icon: '📁' }
];

// Composables
const booksComposable = useBooks();
const moviesComposable = useMovies();
const fileImport = useFileImport();

// Búsqueda de libros
const bookSearch = useSearch({
  debounceDelay: 500,
  minQueryLength: 3
});

// Búsqueda de películas
const movieSearch = useSearch({
  debounceDelay: 500,
  minQueryLength: 3
});

// Configurar funciones de búsqueda
bookSearch.setSearchFunction(booksComposable.searchBooks);
movieSearch.setSearchFunction(moviesComposable.searchMovies);

// Estados computados
const importStats = computed(() => fileImport.getImportStats());

// Métodos
const addBookToLibrary = async (book) => {
  const result = await booksComposable.addBook(book, ['to-read']);
  if (result.success) {
    // Opcional: Mostrar notificación de éxito
    console.log('Libro agregado exitosamente');
  }
};

const addMovieToLibrary = async (movie) => {
  const result = await moviesComposable.addMovie(movie, ['to-watch']);
  if (result.success) {
    // Opcional: Mostrar notificación de éxito
    console.log('Película agregada exitosamente');
  }
};

const updateBookRating = async (isbn, rating) => {
  await booksComposable.updateBookRating(isbn, rating);
};

const updateMovieRating = async (tmdbId, rating) => {
  await moviesComposable.updateMovieRating(tmdbId, rating);
};

const removeBook = async (isbn) => {
  if (confirm('¿Eliminar este libro de tu biblioteca?')) {
    await booksComposable.deleteBook(isbn);
  }
};

const removeMovie = async (tmdbId) => {
  if (confirm('¿Eliminar esta película de tu biblioteca?')) {
    await moviesComposable.deleteMovie(tmdbId);
  }
};

const handleFileSelect = (event) => {
  const file = event.target.files[0];
  if (file) {
    fileImport.setFile(file);
  }
};

const startImport = async () => {
  const result = await fileImport.startImport();
  if (result.success) {
    // Recargar las bibliotecas después de una importación exitosa
    if (activeTab.value === 'books') {
      await booksComposable.fetchBooks();
    } else if (activeTab.value === 'movies') {
      await moviesComposable.fetchMovies();
    }
  }
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Cargar datos al montar el componente
onMounted(async () => {
  await Promise.all([
    booksComposable.fetchBooks(),
    moviesComposable.fetchMovies()
  ]);
});
</script>

<style scoped>
.library-management-example {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.header {
  text-align: center;
  margin-bottom: 30px;
}

.header h1 {
  color: #2c3e50;
  margin-bottom: 10px;
}

.header p {
  color: #7f8c8d;
}

/* Tabs */
.tabs {
  display: flex;
  gap: 10px;
  margin-bottom: 30px;
  border-bottom: 2px solid #ecf0f1;
}

.tab {
  padding: 12px 24px;
  background: none;
  border: none;
  cursor: pointer;
  color: #7f8c8d;
  font-size: 16px;
  border-bottom: 3px solid transparent;
  transition: all 0.3s ease;
}

.tab:hover {
  color: #3498db;
}

.tab.active {
  color: #3498db;
  border-bottom-color: #3498db;
  font-weight: 600;
}

/* Content sections */
.tab-content {
  min-height: 400px;
}

.search-section {
  background: #f8f9fa;
  padding: 20px;
  border-radius: 10px;
  margin-bottom: 30px;
}

.search-input {
  width: 100%;
  padding: 12px;
  border: 2px solid #ddd;
  border-radius: 8px;
  font-size: 16px;
  margin-bottom: 15px;
}

.search-input:focus {
  outline: none;
  border-color: #3498db;
}

.library-section h3 {
  color: #2c3e50;
  margin-bottom: 15px;
}

.stats {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}

.stat {
  background: #3498db;
  color: white;
  padding: 8px 15px;
  border-radius: 20px;
  font-size: 14px;
  font-weight: 500;
}

/* Items grid */
.items-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px;
}

.item-card {
  background: white;
  border-radius: 10px;
  padding: 15px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  transition: transform 0.2s ease;
}

.item-card:hover {
  transform: translateY(-2px);
}

.item-image {
  width: 100%;
  height: 200px;
  object-fit: cover;
  border-radius: 8px;
  margin-bottom: 10px;
}

.item-info h4 {
  margin: 0 0 5px 0;
  color: #2c3e50;
}

.item-info p {
  margin: 0 0 10px 0;
  color: #7f8c8d;
  font-size: 14px;
}

/* Rating */
.rating {
  margin: 10px 0;
}

.star {
  cursor: pointer;
  font-size: 18px;
  margin-right: 2px;
  opacity: 0.3;
  transition: opacity 0.2s ease;
}

.star.filled {
  opacity: 1;
}

.star:hover {
  opacity: 0.7;
}

/* Status tags */
.statuses {
  margin: 10px 0;
}

.status-tag {
  display: inline-block;
  background: #e74c3c;
  color: white;
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 12px;
  margin-right: 5px;
}

/* Buttons */
.add-button, .remove-button, .import-button, .retry-button {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  transition: all 0.2s ease;
}

.add-button {
  background: #27ae60;
  color: white;
}

.add-button:hover {
  background: #2ecc71;
}

.remove-button {
  background: #e74c3c;
  color: white;
  width: 100%;
  margin-top: 10px;
}

.remove-button:hover {
  background: #c0392b;
}

.import-button {
  background: #3498db;
  color: white;
  font-size: 16px;
  padding: 12px 24px;
}

.import-button:hover {
  background: #2980b9;
}

.retry-button {
  background: #f39c12;
  color: white;
}

.retry-button:hover {
  background: #e67e22;
}

/* Import specific styles */
.services-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 15px;
  margin-bottom: 20px;
}

.service-card {
  background: white;
  border: 2px solid #ecf0f1;
  border-radius: 10px;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s ease;
}

.service-card:hover {
  border-color: #3498db;
}

.service-card.selected {
  border-color: #3498db;
  background: #ebf3fd;
}

.service-card i {
  font-size: 24px;
  color: #3498db;
  margin-bottom: 10px;
}

.file-input {
  width: 100%;
  padding: 10px;
  border: 2px dashed #ddd;
  border-radius: 8px;
  margin-bottom: 15px;
}

.file-info {
  background: #f8f9fa;
  padding: 10px;
  border-radius: 6px;
}

.progress-bar {
  width: 100%;
  height: 20px;
  background: #ecf0f1;
  border-radius: 10px;
  overflow: hidden;
  margin: 10px 0;
}

.progress-fill {
  height: 100%;
  background: #3498db;
  transition: width 0.3s ease;
}

.import-success {
  background: #d5f4e6;
  border: 1px solid #27ae60;
  padding: 20px;
  border-radius: 8px;
  color: #27ae60;
}

.import-error {
  background: #fdeaea;
  border: 1px solid #e74c3c;
  padding: 20px;
  border-radius: 8px;
  color: #e74c3c;
}

.loading {
  text-align: center;
  color: #7f8c8d;
  padding: 20px;
  font-style: italic;
}

.empty-state {
  text-align: center;
  color: #7f8c8d;
  padding: 40px;
}

.results {
  margin-top: 20px;
}

.result-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: white;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 10px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.book-info, .movie-info {
  display: flex;
  align-items: center;
  gap: 15px;
}

.book-cover, .movie-poster {
  width: 60px;
  height: 90px;
  object-fit: cover;
  border-radius: 4px;
}

.book-info h5, .movie-info h5 {
  margin: 0 0 5px 0;
  color: #2c3e50;
}

.book-info p, .movie-info p {
  margin: 2px 0;
  color: #7f8c8d;
  font-size: 14px;
}
</style>
