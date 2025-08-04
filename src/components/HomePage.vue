<template>
  <div class="home-container">
    <!-- Icono de ayuda flotante -->
    <div class="help-icon" @click="openHelpPage" title="Ayuda y documentación">
      <i class="fas fa-question-circle"></i>
    </div>
    
    <h1 class="home-title">Bienvenido a tu Biblioteca Personal</h1>
    <p class="home-description">
      Gestiona tus libros, películas, música y más desde un solo lugar.
    </p>
    <div class="home-links">
      <router-link class="home-link" to="/books">
        <i class="fas fa-book"></i>
        <span class="link-text">Libros</span>
      </router-link>
      <router-link class="home-link" to="/library">
        <i class="fas fa-bookmark"></i>
        <span class="link-text">Biblioteca</span>
      </router-link>
      <router-link class="home-link" to="/movies">
        <i class="fas fa-film"></i>
        <span class="link-text">Películas</span>
      </router-link>
      <router-link class="home-link disabled" to="#" @click.prevent>
        <i class="fas fa-music"></i>
        <span class="link-text">Música</span>
      </router-link>
    </div>
    <button @click="saveBooksToBackend" class="export-button">
      <i class="fas fa-save"></i>
      <span class="button-text">Guardar cambios</span>
    </button>
  </div>
</template>

<script setup>
import axios from 'axios';

const saveBooksToBackend = async () => {
  try {
    const backendApiUrl = process.env.VUE_APP_API_URL || '/backend/api.php';
    const response = await axios.post(backendApiUrl, {
      type: 'books',
      action: 'get_library'
    });
    const books = Array.isArray(response.data.data) ? response.data.data : [];
    // Ahora enviamos los libros al backend para sobrescribir el archivo
    const saveResponse = await axios.post(backendApiUrl, {
      action: 'save_library',
      books
    });
    if (saveResponse.data && saveResponse.data.status === 'success') {
      alert('Biblioteca guardada correctamente en el backend.');
    } else {
      alert('Error al guardar la biblioteca en el backend.');
    }
  } catch (error) {
    console.error("Error al guardar libros en backend:", error);
    alert("No se pudo guardar la biblioteca en el backend.");
  }
};

const openHelpPage = () => {
  // Abrir la página de ayuda en una nueva ventana
  window.open('/help.html', 'help', 'width=1200,height=800,scrollbars=yes,resizable=yes');
};
</script>

<style>
.home-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  padding: 40px 20px;
  position: relative; /* Para posicionar el icono de ayuda */
}

/* Icono de ayuda flotante */
.help-icon {
  position: fixed;
  bottom: 20px;
  right: 20px;
  width: 50px;
  height: 50px;
  background: #007bff;
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 1.4rem;
  box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
  transition: all 0.3s ease;
  z-index: 1000;
}

.help-icon:hover {
  background: #0056b3;
  transform: scale(1.1);
  box-shadow: 0 6px 16px rgba(0, 123, 255, 0.4);
}

.help-icon i {
  margin: 0;
}

.home-title {
  font-size: 2.8rem;
  font-weight: 700;
  color: #e0e0e0;
  margin-bottom: 20px;
}
.home-description {
  color: #b0b0b0;
  font-size: 1.2rem;
  margin-bottom: 40px;
  text-align: center;
}
.home-links {
  display: flex;
  flex-direction: column;
  gap: 20px;
  width: 100%;
  max-width: 350px;
}
.home-link {
  display: flex;
  align-items: center;
  justify-content: center;
  background: #252525;
  color: #88aaff;
  text-decoration: none;
  padding: 18px 0;
  border-radius: 12px;
  font-size: 1.2rem;
  font-weight: 600;
  transition: background 0.2s, color 0.2s;
}

.home-link i {
  margin-right: 10px;
  font-size: 1.3rem;
}

.link-text {
  font-size: 1.1rem;
}
.home-link:hover:not(.disabled) {
  background: #42b983;
  color: #fff;
}
.home-link.disabled {
  color: #888;
  background: #222;
  cursor: not-allowed;
  pointer-events: none;
}
.export-button {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 32px;
  padding: 12px 24px;
  background: #1976d2;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}

.export-button i {
  margin-right: 8px;
}

.button-text {
  font-size: 1rem;
}
.export-button:hover {
  background: #1565c0;
}
.download-button {
  margin-top: 16px;
  padding: 12px 24px;
  background: #4caf50;
  color: #fff;
  border: none;
  border-radius: 6px;
  font-size: 1.1rem;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.download-button:hover {
  background: #388e3c;
}
</style>
