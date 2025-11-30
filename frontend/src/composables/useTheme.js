import { ref, onMounted } from 'vue';

export function useTheme() {
  const isDark = ref(false);

  // Cargar preferencia guardada o del sistema
  const loadTheme = () => {
    const savedTheme = localStorage.getItem('theme');
    
    if (savedTheme) {
      isDark.value = savedTheme === 'dark';
    } else {
      // Detectar preferencia del sistema
      isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    
    applyTheme();
  };

  // Aplicar el tema al documento
  const applyTheme = () => {
    if (isDark.value) {
      document.documentElement.classList.add('app-dark');
    } else {
      document.documentElement.classList.remove('app-dark');
    }
  };

  // Cambiar entre modo claro y oscuro
  const toggleTheme = () => {
    isDark.value = !isDark.value;
    localStorage.setItem('theme', isDark.value ? 'dark' : 'light');
    applyTheme();
  };

  // Establecer tema específico
  const setTheme = (theme) => {
    isDark.value = theme === 'dark';
    localStorage.setItem('theme', theme);
    applyTheme();
  };

  // Cargar tema inmediatamente (sin esperar a onMounted)
  if (typeof window !== 'undefined') {
    loadTheme();
  }

  // Inicializar al montar y escuchar cambios del sistema
  onMounted(() => {
    // Escuchar cambios en la preferencia del sistema
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    mediaQuery.addEventListener('change', (e) => {
      if (!localStorage.getItem('theme')) {
        isDark.value = e.matches;
        applyTheme();
      }
    });
  });

  return {
    isDark,
    toggleTheme,
    setTheme,
    loadTheme
  };
}
