<template>
  <div class="home-container">
    <h1 class="home-title">Bienvenido a tu Biblioteca Personal</h1>
    <p class="home-description">
      Gestiona tus libros, películas, música y más desde un solo lugar.
    </p>
    <div class="home-links">
      <router-link class="home-link" to="/books">Libros (Buscador ISBN)</router-link>
      <router-link class="home-link" to="/library">Mi Biblioteca</router-link>
      <router-link class="home-link" to="/movies">Películas (Buscador OMDb)</router-link>
      <router-link class="home-link disabled" to="#" @click.prevent>Música (próximamente)</router-link>
    </div>
  </div>
</template>

<script>
export default {
  name: 'HomePage',
};
</script>

<style scoped>
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
</style>
