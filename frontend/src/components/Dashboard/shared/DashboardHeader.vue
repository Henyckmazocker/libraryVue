<template>
  <div class="dashboard-header-wrapper">
    <div class="dashboard-header">
      <h1 class="dashboard-title">
        <i v-if="icon" :class="icon"></i>
        {{ title }}
      </h1>
      <p v-if="subtitle" class="dashboard-subtitle">
        {{ subtitle }}
      </p>
    </div>

    <div v-if="libraryLink" class="quick-actions">
      <router-link 
        :to="libraryLink" 
        class="btn btn--primary btn--large"
      >
        <i class="fas fa-library"></i>
        {{ linkText }}
      </router-link>
    </div>
  </div>
</template>

<script setup>
// eslint-disable-next-line no-undef
defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  },
  icon: {
    type: String,
    default: ''
  },
  libraryLink: {
    type: String,
    default: ''
  },
  linkText: {
    type: String,
    default: 'Ver Mi Biblioteca'
  }
});
</script>

<style scoped>
.dashboard-header-wrapper {
  margin-bottom: 2rem;
}

.dashboard-header {
  text-align: center;
  margin-bottom: 1.5rem;
}

.dashboard-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-text-dark);
  margin: 0 0 0.5rem 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
}

.dashboard-title i {
  color: var(--color-primary);
  font-size: 1.75rem;
}

.dashboard-subtitle {
  font-size: 1rem;
  color: var(--color-text-muted);
  margin: 0;
}

.quick-actions {
  display: flex;
  justify-content: center;
  gap: 1rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 600;
  text-decoration: none;
  transition: var(--transition-fast);
  border: none;
  cursor: pointer;
}

.btn--primary {
  background: var(--btn-primary-bg);
  color: var(--btn-primary-text);
}

.btn--primary:hover {
  background: var(--btn-primary-bg-hover);
  transform: translateY(-2px);
  box-shadow: var(--shadow-medium);
}

.btn--large {
  padding: 1rem 2rem;
  font-size: 1rem;
}

/* Dark mode */
:global(.app-dark) .dashboard-title {
  color: var(--color-text);
}

:global(.app-dark) .dashboard-subtitle {
  color: var(--color-text-secondary);
}
</style>
