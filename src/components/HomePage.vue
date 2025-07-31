<template>
  <div class="home-container">
    <h1 class="home-title">Bienvenido a tu Biblioteca Personal</h1>
    <p class="home-description">
      Gestiona tus libros, películas, música y más desde un solo lugar.
    </p>
    <div class="home-links">
      <router-link class="home-link" to="/books">Libros (Google Books + OpenLibrary)</router-link>
      <router-link class="home-link" to="/library">Mi Biblioteca</router-link>
      <router-link class="home-link" to="/movies">Películas (Buscador OMDb)</router-link>
      <router-link class="home-link disabled" to="#" @click.prevent>Música (próximamente)</router-link>
    </div>
    <button @click="saveBooksToBackend" class="export-button">
      Guardar cambios en la biblioteca
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
</script>

<style>
.home-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  padding: 40px 20px;
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
  display: block;
  background: #252525;
  color: #88aaff;
  text-decoration: none;
  padding: 18px 0;
  border-radius: 12px;
  font-size: 1.2rem;
  font-weight: 600;
  text-align: center;
  transition: background 0.2s, color 0.2s;
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
