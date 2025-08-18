<template>
  <div class="home-container">
    <!-- Icono de ayuda flotante -->
    <div class="help-icon" @click="openHelpPage" title="Ayuda y documentación">
      <i class="fas fa-question-circle"></i>
    </div>
    
    <!-- Hero Section -->
    <div class="hero-section">
      <h1 class="home-title">Bienvenido a tu Biblioteca Personal</h1>
      <p class="home-description">
        Organiza y gestiona tu colección de libros, películas y más desde un solo lugar.
        Utiliza el menú lateral para navegar entre las diferentes secciones.
      </p>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <h2 class="section-title">Accesos Rápidos</h2>
      <div class="action-grid">
        <router-link class="action-card" to="/library">
          <i class="fas fa-bookmark"></i>
          <h3>Mi Biblioteca</h3>
          <p>Ver toda tu colección</p>
        </router-link>
        
        <router-link class="action-card" to="/books">
          <i class="fas fa-search"></i>
          <h3>Buscar Libros</h3>
          <p>Encuentra nuevos libros</p>
        </router-link>
        
        <router-link class="action-card" to="/movies">
          <i class="fas fa-film"></i>
          <h3>Buscar Películas</h3>
          <p>Descubre nuevas películas</p>
        </router-link>
        
        <div class="action-card action-card--disabled">
          <i class="fas fa-music"></i>
          <h3>Música</h3>
          <p>Próximamente</p>
        </div>
      </div>
    </div>

    <!-- Sync Button -->
    <div class="sync-section">
      <button @click="saveBooksToBackend" class="sync-button">
        <i class="fas fa-sync-alt"></i>
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

<style scoped>
.home-container {
  max-width: 1000px;
  margin: 0 auto;
  padding: 40px 20px;
  position: relative;
}

/* Icono de ayuda flotante */
.help-icon {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 50px;
  height: 50px;
  background: #0079d3;
  color: white;
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
  background: #0060a8;
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
  color: #d7dadc;
  margin-bottom: 20px;
  line-height: 1.2;
}

.home-description {
  color: #818384;
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
  color: #d7dadc;
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
  background: #1a1a1b;
  border: 1px solid #343536;
  border-radius: 12px;
  padding: 30px 24px;
  text-align: center;
  text-decoration: none;
  color: #d7dadc;
  transition: all 0.3s ease;
  cursor: pointer;
}

.action-card:hover:not(.action-card--disabled) {
  background: #272729;
  border-color: #0079d3;
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 121, 211, 0.15);
}

.action-card i {
  font-size: 2.5rem;
  color: #0079d3;
  margin-bottom: 16px;
  display: block;
}

.action-card h3 {
  font-size: 1.3rem;
  font-weight: 600;
  margin: 0 0 8px 0;
  color: #d7dadc;
}

.action-card p {
  font-size: 0.95rem;
  color: #818384;
  margin: 0;
  line-height: 1.4;
}

.action-card--disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-card--disabled:hover {
  transform: none;
  background: #1a1a1b;
  border-color: #343536;
  box-shadow: none;
}

.action-card--disabled i {
  color: #818384;
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
  background: #0079d3;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(0, 121, 211, 0.2);
}

.sync-button:hover {
  background: #0060a8;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 121, 211, 0.3);
}

.sync-button i {
  font-size: 1.2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
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

@media (max-width: 480px) {
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
