import { ref, computed } from 'vue';
// import yaml from 'js-yaml'; // Para futuro uso cuando carguemos YAML dinámicamente

const menuData = ref(null);
const loading = ref(false);
const error = ref(null);

export function useSidebarMenu() {
  const loadMenu = async () => {
    if (menuData.value) return menuData.value;

    loading.value = true;
    error.value = null;

    try {
      // Cargar el archivo JSON desde la carpeta public
      const response = await fetch('/config/sidebar-menu.json');
      
      if (!response.ok) {
        throw new Error(`Error al cargar el menú: ${response.status}`);
      }
      
      const menuConfig = await response.json();

      menuData.value = menuConfig;
      return menuConfig;
    } catch (err) {
      error.value = err.message;
      console.error('Error loading sidebar menu:', err);
      
      // Fallback al menú hardcodeado si falla la carga
      const fallbackConfig = {
        menu: [
          {
            title: "Principal",
            items: [
              {
                name: "Biblioteca",
                path: "/library",
                icon: "fas fa-bookmark",
                description: "Tu biblioteca personal"
              }
            ]
          },
          {
            title: "Libros",
            items: [
              {
                name: "Buscar Libros",
                path: "/books",
                icon: "fas fa-search",
                description: "Buscar nuevos libros"
              },
              {
                name: "Mis Libros",
                path: "/library?filter=books",
                icon: "fas fa-book",
                description: "Libros en tu biblioteca"
              }
            ]
          },
          {
            title: "Películas",
            items: [
              {
                name: "Buscar Películas",
                path: "/movies",
                icon: "fas fa-search",
                description: "Buscar nuevas películas"
              },
              {
                name: "Mis Películas",
                path: "/library?filter=movies",
                icon: "fas fa-film",
                description: "Películas en tu biblioteca"
              }
            ]
          },
          {
            title: "Próximamente",
            items: [
              {
                name: "Música",
                path: "#",
                icon: "fas fa-music",
                description: "Próximamente disponible",
                disabled: true
              }
            ]
          }
        ]
      };
      
      menuData.value = fallbackConfig;
      return fallbackConfig;
    } finally {
      loading.value = false;
    }
  };

  const menuItems = computed(() => {
    return menuData.value?.menu || [];
  });

  const isLoading = computed(() => loading.value);
  const hasError = computed(() => error.value !== null);

  return {
    menuItems,
    isLoading,
    hasError,
    error: computed(() => error.value),
    loadMenu
  };
}
