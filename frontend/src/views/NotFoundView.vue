<template>
  <div class="not-found">
    <div class="not-found-content">
      <h1 class="not-found-code">
        404
      </h1>
      <h2 class="not-found-title">
        Página no encontrada
      </h2>
      <p class="not-found-message">
        La página que buscas no existe o ha sido movida.
      </p>
      <router-link
        to="/"
        class="not-found-link"
      >
        Volver al inicio
      </router-link>
    </div>
  </div>
</template>

<script setup>
// No logic needed
</script>

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.not-found {
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 60vh;
  text-align: center;
  padding: spacing(xl);
}

.not-found-content {
  max-width: 480px;
}

.not-found-code {
  font-size: 6rem;
  font-weight: 700;
  color: var(--color-primary, var(--color-primary));
  margin: 0;
  line-height: 1;
}

.not-found-title {
  font-size: var(--font-size-xl);
  margin: spacing(xs) 0 spacing(md);
  color: var(--color-text, var(--color-text-secondary));
}

.not-found-message {
  color: var(--color-text-muted, var(--color-text-secondary));
  margin-bottom: spacing(xl);
}

.not-found-link {
  display: inline-block;
  padding: spacing(sm) spacing(lg);
  background-color: var(--color-primary, var(--color-primary));
  color: var(--color-text-light);
  border-radius: radius(sm);
  text-decoration: none;
  transition: opacity 0.2s;
}

.not-found-link:hover {
  opacity: 0.85;
}
</style>
