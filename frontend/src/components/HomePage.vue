<template>
  <div class="home-container">
    <!-- Icono de ayuda flotante -->
    <button
      type="button"
      class="help-icon"
      title="Ayuda y documentación"
      aria-label="Abrir la ayuda y documentación"
      @click="openHelpPage"
    >
      <i class="fas fa-question-circle" />
    </button>
    
    <!-- Hero Section -->
    <div class="hero-section">
      <h1 class="home-title">
        Bienvenido a tu Biblioteca Personal
      </h1>
      <p class="home-description">
        Organiza y gestiona tu colección de libros, películas y más desde un solo lugar.
        Utiliza el menú lateral para navegar entre las diferentes secciones.
      </p>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <h2 class="section-title">
        Accesos Rápidos
      </h2>
      <div class="action-grid">
        <router-link
          class="action-card"
          to="/library"
        >
          <i class="fas fa-bookmark" />
          <h3>Mi Biblioteca</h3>
          <p>Ver toda tu colección</p>
        </router-link>
        
        <router-link
          class="action-card"
          to="/books"
        >
          <i class="fas fa-search" />
          <h3>Buscar Libros</h3>
          <p>Encuentra nuevos libros</p>
        </router-link>
        
        <router-link
          class="action-card"
          to="/movies"
        >
          <i class="fas fa-film" />
          <h3>Buscar Películas</h3>
          <p>Descubre nuevas películas</p>
        </router-link>
        
        <router-link
          class="action-card"
          to="/games"
        >
          <i class="fas fa-gamepad" />
          <h3>Buscar Videojuegos</h3>
          <p>Explora nuevos juegos</p>
        </router-link>
        
        <div class="action-card action-card--disabled">
          <i class="fas fa-music" />
          <h3>Música</h3>
          <p>Próximamente</p>
        </div>
      </div>
    </div>

    <!-- Sync Button -->
    <div class="sync-section">
      <button
        class="sync-button"
        @click="saveBooksToBackend"
      >
        <i class="fas fa-sync-alt" />
        <span>Sincronizar con servidor</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/store/auth';
// import { useAuth } from '@/composables'; // Por ahora mantener authStore.apiCall
import Logger from '@/utils/logger';

const authStore = useAuthStore();
// const { authenticatedApiCall } = useAuth(); // Por ahora mantener authStore.apiCall

const saveBooksToBackend = async () => {
  try {
    const response = await authStore.apiCall('get_library', {
      type: 'books'
    });
    const books = Array.isArray(response.data.data) ? response.data.data : [];
    // Ahora enviamos los libros al backend para sobrescribir el archivo
    const saveResponse = await authStore.apiCall('save_library', {
      books
    });
    if (saveResponse.data && saveResponse.data.status === 'success') {
      alert('Biblioteca guardada correctamente en el backend.');
    } else {
      alert('Error al guardar la biblioteca en el backend.');
    }
  } catch (error) {
    Logger.error("Error al guardar libros en backend:", error);
    alert("No se pudo guardar la biblioteca en el backend.");
  }
};

const openHelpPage = () => {
  // Abrir la página de ayuda en una nueva ventana
  window.open('/help.html', 'help', 'width=1200,height=800,scrollbars=yes,resizable=yes');
};
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.home-container {
  max-width: 1000px;
  margin: 0 auto;
  padding: 40px 20px;
  position: relative;
}

/* Icono de ayuda flotante */
.help-icon {
  @include button-reset;
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 50px;
  height: 50px;
  background: var(--color-info);
  color: var(--color-on-status);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 1.4rem;
  box-shadow: 0 4px 12px rgba(0, 121, 211, 0.3);
  transition: all 0.3s ease;
  z-index: 1000;
}

.help-icon:hover {
  background: var(--color-info);
  transform: scale(1.1);
  box-shadow: 0 6px 16px rgba(0, 121, 211, 0.4);
}

/* Hero Section */
.hero-section {
  text-align: center;
  margin-bottom: 60px;
}

.home-title {
  font-size: 3rem;
  font-weight: 700;
  color: var(--color-text);
  margin-bottom: 20px;
  line-height: 1.2;
}

.home-description {
  color: var(--color-text-muted);
  font-size: 1.2rem;
  line-height: 1.6;
  max-width: 600px;
  margin: 0 auto;
}

/* Quick Actions */
.quick-actions {
  margin-bottom: 60px;
}

.section-title {
  font-size: 1.8rem;
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: 30px;
  text-align: center;
}

.action-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 24px;
  max-width: 800px;
  margin: 0 auto;
}

.action-card {
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: 12px;
  padding: 30px 24px;
  text-align: center;
  text-decoration: none;
  color: var(--color-text);
  transition: all 0.3s ease;
  cursor: pointer;
}

.action-card:hover:not(.action-card--disabled) {
  background: var(--color-background-mute);
  border-color: var(--color-info);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 121, 211, 0.15);
}

.action-card i {
  font-size: 2.5rem;
  color: var(--color-info);
  margin-bottom: 16px;
  display: block;
}

.action-card h3 {
  font-size: 1.3rem;
  font-weight: 600;
  margin: 0 0 8px 0;
  color: var(--color-text);
}

.action-card p {
  font-size: 0.95rem;
  color: var(--color-text-muted);
  margin: 0;
  line-height: 1.4;
}

.action-card--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-card--disabled:hover {
  transform: none;
  background: var(--color-background-soft);
  border-color: var(--color-border);
  box-shadow: none;
}

.action-card--disabled i {
  color: var(--color-text-muted);
}

/* Sync Section */
.sync-section {
  text-align: center;
}

.sync-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 16px 32px;
  background: var(--color-info);
  color: var(--color-on-status);
  border: none;
  border-radius: 8px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(0, 121, 211, 0.2);
}

.sync-button:hover {
  background: var(--color-info);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 121, 211, 0.3);
}

.sync-button i {
  font-size: 1.2rem;
}

/* Responsive Design */
@include responsive-below(md) {
  .home-container {
    padding: 20px 15px;
  }
  
  .home-title {
    font-size: 2.2rem;
  }
  
  .home-description {
    font-size: 1.1rem;
  }
  
  .action-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
  
  .action-card {
    padding: 24px 20px;
  }
  
  .action-card i {
    font-size: 2rem;
  }
}

@include responsive-below(sm) {
  .home-title {
    font-size: 1.8rem;
  }
  
  .home-description {
    font-size: 1rem;
  }
  
  .help-icon {
    width: 45px;
    height: 45px;
    font-size: 1.2rem;
  }
}
</style>
