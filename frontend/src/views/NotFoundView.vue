<template>
  <div class="not-found">
    <div class="not-found-content">
      <h1 class="not-found-code">404</h1>
      <h2 class="not-found-title">Página no encontrada</h2>
      <p class="not-found-message">
        La página que buscas no existe o ha sido movida.
      </p>
      <router-link to="/" class="not-found-link">
        Volver al inicio
      </router-link>
    </div>
  </div>
</template>

<script setup>
// No logic needed
</script>

<style scoped lang="scss">
.not-found {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
  text-align: center;
  padding: 2rem;
}

.not-found-content {
  max-width: 480px;
}

.not-found-code {
  font-size: 6rem;
  font-weight: 700;
  color: var(--color-primary, #1D4E4A);
  margin: 0;
  line-height: 1;
}

.not-found-title {
  font-size: 1.5rem;
  margin: 0.5rem 0 1rem;
  color: var(--color-text, #333);
}

.not-found-message {
  color: var(--color-text-muted, #666);
  margin-bottom: 2rem;
}

.not-found-link {
  display: inline-block;
  padding: 0.75rem 1.5rem;
  background-color: var(--color-primary, #1D4E4A);
  color: #fff;
  border-radius: 6px;
  text-decoration: none;
  transition: opacity 0.2s;
}

.not-found-link:hover {
  opacity: 0.85;
}
</style>
