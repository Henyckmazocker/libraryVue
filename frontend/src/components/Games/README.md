# Configuración de la Sección de Videojuegos

## API de RAWG

La sección de videojuegos utiliza la [RAWG Video Games Database API](https://rawg.io/apidocs) para buscar información de juegos.

### Obtener una API Key

1. Ve a [https://rawg.io/apidocs](https://rawg.io/apidocs)
2. Crea una cuenta gratuita
3. Navega a la sección "Get API Key"
4. Copia tu API key

### Configurar la API Key

Debes reemplazar `YOUR_RAWG_API_KEY_HERE` con tu API key en los siguientes archivos:

#### GameSearch.vue
```javascript
// Línea 20
const RAWG_API_KEY = 'YOUR_RAWG_API_KEY_HERE'; // Reemplaza con tu API key
```

#### GameDetailView.vue
```javascript
// Línea 237
const RAWG_API_KEY = 'YOUR_RAWG_API_KEY_HERE'; // Reemplaza con tu API key
```

### Límites de la API Gratuita

- **20,000 requests por mes**
- Acceso a más de **500,000 videojuegos**
- Información detallada: desarrolladores, géneros, plataformas, capturas de pantalla, metacritic scores, etc.

### Consideración para Producción

Para proyectos en producción, considera:

1. **Variables de entorno**: Mueve la API key a un archivo `.env`
   ```
   VUE_APP_RAWG_API_KEY=tu_api_key_aqui
   ```

2. **Backend proxy**: Crea un endpoint en tu backend que maneje las llamadas a RAWG para ocultar la API key del frontend

3. **Cache**: Implementa caching para reducir el número de requests a la API

## Estructura de la Sección

### Componentes

- **GameSearch.vue**: Página de búsqueda con integración a RAWG API
- **GameCarouselItem.vue**: Tarjeta de juego en el carrusel de resultados
- **GameDetailView.vue**: Vista detallada de un juego con formulario para añadir a la biblioteca
- **LibraryGameItem.vue**: Formulario para gestionar juegos en la biblioteca
- **GamesDashboard.vue**: Dashboard con estadísticas de videojuegos

### Store (Pinia)

- **games.js**: Gestión del estado de la biblioteca de juegos
  - `fetchGames()`: Obtener juegos de la biblioteca
  - `searchGames()`: Buscar juegos en el backend
  - `addGame()`: Añadir juego a la biblioteca
  - `deleteGame()`: Eliminar juego
  - `updateGameRating()`: Actualizar calificación
  - `updateGameStatuses()`: Actualizar estados
  - `editGame()`: Editar información del juego

### Backend

La sección de videojuegos está completamente integrada con el backend PHP:

- **11 acciones disponibles**:
  - `add_game`: Añadir juego
  - `delete_game`: Eliminar juego
  - `update_game_rating`: Actualizar calificación
  - `update_game_user_statuses`: Actualizar estados
  - `edit_user_game`: Editar juego
  - `get_game_allowed_statuses`: Obtener estados permitidos
  - `get_user_game_tags`: Obtener tags del usuario
  - `create_user_game_tag`: Crear tag
  - `delete_user_game_tag`: Eliminar tag
  - `assign_tag_to_game`: Asignar tag a juego
  - `remove_tag_from_game`: Eliminar tag de juego

### Campos Específicos de Juegos

Los juegos tienen campos adicionales comparados con libros y películas:

- **hoursPlayed**: Horas jugadas
- **dateStarted**: Fecha de inicio (YYYY-MM-DD)
- **dateFinished**: Fecha de finalización (YYYY-MM-DD)
- **notes**: Notas personales
- **platforms**: Plataformas en las que está disponible
- **metacriticScore**: Puntuación de Metacritic
- **esrbRating**: Clasificación ESRB
- **userStatuses**: Estados del juego (owned, playing, completed, 100-completed, in-wishlist, etc.)

## Uso

1. **Buscar juegos**: Ve a `/games` o usa el botón de acceso rápido en la página principal
2. **Ver detalles**: Haz clic en cualquier juego para ver información completa
3. **Añadir a biblioteca**: Completa el formulario con tus preferencias y guarda
4. **Ver estadísticas**: Accede al dashboard en `/dashboard/games`

## Testing

Para probar la funcionalidad:

1. Busca un juego popular como "The Witcher 3" o "GTA V"
2. También puedes buscar por ID de RAWG (por ejemplo: `3498` para GTA V)
3. Añade el juego a tu biblioteca con diferentes estados
4. Visita el dashboard para ver las estadísticas

## Errores Comunes

### "No se pudo buscar en RAWG. Verifica tu API key"
- Asegúrate de haber configurado la API key correctamente
- Verifica que la key sea válida en [RAWG API Dashboard](https://rawg.io/apidocs)

### "Network error"
- Verifica tu conexión a internet
- Comprueba que RAWG API esté disponible (status.rawg.io)

### "No se encontró información del juego"
- El ID del juego puede ser incorrecto
- Intenta buscar por nombre en lugar de ID

## Contribuir

Para añadir nuevas funcionalidades a la sección de videojuegos:

1. Actualiza el backend en `backend/src/Application/GameController.php`
2. Añade las acciones correspondientes en el store `frontend/src/store/games.js`
3. Actualiza los componentes según sea necesario
4. Documenta los cambios en este README
