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

<style scoped lang="scss">
@use '@/assets/styles/abstracts' as *;

.dashboard-header-wrapper {
  margin-bottom: spacing(xl);
}

.dashboard-header {
  text-align: center;
  margin-bottom: spacing(lg);
}

.dashboard-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-text);
  margin: 0 0 spacing(xs) 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: spacing(sm);

  i {
    color: var(--color-primary);
    font-size: 1.75rem;
  }
}

.dashboard-subtitle {
  font-size: 1rem;
  color: var(--color-text-secondary);
  margin: 0;
}

.quick-actions {
  display: flex;
  justify-content: center;
  gap: spacing(md);
}

// .btn / .btn--primary se proveen globalmente desde components/_buttons.scss
.btn--large {
  padding: spacing(md) spacing(xl);
  font-size: 1rem;
}

@include responsive-below(md) {
  .dashboard-header-wrapper {
    margin-bottom: spacing(lg);
  }

  .dashboard-title {
    font-size: 1.5rem;
    flex-wrap: wrap;

    i { font-size: 1.25rem; }
  }

  .dashboard-subtitle {
    font-size: 0.9rem;
  }

  .quick-actions .btn {
    width: 100%;
    justify-content: center;
  }

  .btn--large {
    padding: spacing(sm) spacing(lg);
  }
}
</style>
