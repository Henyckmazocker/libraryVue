<template>
  <div class="home-container">
    <!-- Icono de ayuda flotante -->
    <button
      type="button"
      class="btn btn--primary btn--icon help-icon"
      :title="t('home.helpTitle')"
      :aria-label="t('home.helpAria')"
      @click="openHelpPage"
    >
      <i class="fas fa-question-circle" />
    </button>
    
    <!-- Hero Section -->
    <div class="hero-section">
      <h1 class="home-title">
        {{ t('home.title') }}
      </h1>
      <p class="home-description">
        {{ t('home.description') }}
      </p>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <h2 class="section-title">
        {{ t('home.quickActions') }}
      </h2>
      <div class="action-grid">
        <router-link
          class="action-card"
          to="/library"
        >
          <i class="fas fa-bookmark" />
          <h3>{{ t('home.cards.library') }}</h3>
          <p>{{ t('home.cards.libraryHint') }}</p>
        </router-link>
        
        <router-link
          class="action-card"
          to="/books"
        >
          <i class="fas fa-search" />
          <h3>{{ t('home.cards.books') }}</h3>
          <p>{{ t('home.cards.booksHint') }}</p>
        </router-link>
        
        <router-link
          class="action-card"
          to="/movies"
        >
          <i class="fas fa-film" />
          <h3>{{ t('home.cards.movies') }}</h3>
          <p>{{ t('home.cards.moviesHint') }}</p>
        </router-link>
        
        <router-link
          class="action-card"
          to="/games"
        >
          <i class="fas fa-gamepad" />
          <h3>{{ t('home.cards.games') }}</h3>
          <p>{{ t('home.cards.gamesHint') }}</p>
        </router-link>
        
        <router-link
          class="action-card"
          to="/albums"
        >
          <i class="fas fa-music" />
          <h3>{{ t('home.cards.music') }}</h3>
          <p>{{ t('home.cards.musicHint') }}</p>
        </router-link>
      </div>
    </div>

    <!-- Sync Button -->
    <div class="sync-section">
      <button
        class="btn btn--primary btn--lg sync-button"
        @click="saveBooksToBackend"
      >
        <i class="fas fa-sync-alt" />
        <span>{{ t('home.sync') }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '@/store/auth';
// import { useAuth } from '@/composables'; // Por ahora mantener authStore.apiCall
import Logger from '@/utils/logger';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

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
  padding: spacing(xl) spacing(md);
  position: relative;
}

/* Icono de ayuda flotante */
.help-icon {
  position: fixed;
  bottom: spacing(md);
  right: spacing(md);
  width: 50px;
  height: 50px;
  border-radius: 50%;
  font-size: var(--font-size-xl);
  box-shadow: shadow(medium);
  z-index: z(sticky);

  &:hover { transform: scale(1.1); }
}

/* Hero Section */
.hero-section {
  text-align: center;
  margin-bottom: spacing(3xl);
}

.home-title {
  font-size: var(--font-size-4xl);
  font-weight: 700;
  color: var(--color-text);
  margin-bottom: spacing(md);
  line-height: 1.2;
}

.home-description {
  color: var(--color-text-muted);
  font-size: var(--font-size-lg);
  line-height: 1.6;
  max-width: 600px;
  margin: 0 auto;
}

/* Quick Actions */
.quick-actions {
  margin-bottom: spacing(3xl);
}

.section-title {
  font-size: var(--font-size-2xl);
  font-weight: 600;
  color: var(--color-text);
  margin-bottom: spacing(xl);
  text-align: center;
}

.action-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: spacing(lg);
  max-width: 800px;
  margin: 0 auto;
}

.action-card {
  background: var(--color-background-soft);
  border: 1px solid var(--color-border);
  border-radius: radius(lg);
  padding: spacing(xl) spacing(lg);
  text-align: center;
  text-decoration: none;
  color: var(--color-text);
  transition: all 0.3s ease;
  cursor: pointer;
}

.action-card:hover {
  background: var(--color-background-mute);
  border-color: var(--color-info);
  transform: translateY(-2px);
  box-shadow: shadow(heavy);
}

.action-card i {
  font-size: var(--font-size-3xl);
  color: var(--color-info);
  margin-bottom: spacing(md);
  display: block;
}

.action-card h3 {
  font-size: var(--font-size-lg);
  font-weight: 600;
  margin: 0 0 spacing(xs) 0;
  color: var(--color-text);
}

.action-card p {
  font-size: var(--font-size-base);
  color: var(--color-text-muted);
  margin: 0;
  line-height: 1.4;
}

/* Sync Section */
.sync-section {
  text-align: center;
}

.sync-button {
  box-shadow: shadow(light);

  &:hover { transform: translateY(-1px); }
}

.sync-button i {
  font-size: var(--font-size-lg);
}

/* Responsive Design */
@include responsive-below(md) {
  .home-container {
    padding: spacing(md) spacing(md);
  }
  
  .home-title {
    font-size: var(--font-size-3xl);
  }
  
  .home-description {
    font-size: var(--font-size-md);
  }
  
  .action-grid {
    grid-template-columns: 1fr;
    gap: spacing(md);
  }
  
  .action-card {
    padding: spacing(lg) spacing(md);
  }
  
  .action-card i {
    font-size: var(--font-size-2xl);
  }
}

@include responsive-below(sm) {
  .home-title {
    font-size: var(--font-size-2xl);
  }
  
  .home-description {
    font-size: var(--font-size-base);
  }
  
  .help-icon {
    width: 45px;
    height: 45px;
    font-size: var(--font-size-lg);
  }
}
</style>
