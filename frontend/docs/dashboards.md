# 📊 Dashboards de la Biblioteca Personal

Esta documentación describe los dashboards implementados para mostrar estadísticas y gráficas de la biblioteca personal.

## 🏗️ Arquitectura

### Librerías Utilizadas
- **Chart.js v4.4.0**: Librería principal para gráficas
- **vue-chartjs v5.3.1**: Wrapper de Chart.js para Vue 3
- **Vue 3**: Framework base de la aplicación

### Estructura de Componentes
```
src/components/Dashboard/
├── BooksDashboard.vue    # Dashboard de libros
└── MoviesDashboard.vue   # Dashboard de películas
```

## 📚 Dashboard de Libros (`/dashboard/books`)

### Características
- **Estadísticas generales**: Total de libros, leídos, pendientes, calificación promedio
- **Gráficas interactivas**:
  - Estado de lectura (Doughnut Chart)
  - Distribución de calificaciones (Bar Chart)
  - Géneros favoritos (Pie Chart)
  - Progreso mensual (Line Chart)
- **Acceso directo**: Botón para ver biblioteca filtrada solo por libros

### Navegación
- **Menú lateral**: "Mis Libros" → `/dashboard/books`
- **Desde dashboard**: "Ver Mi Biblioteca de Libros" → `/library?filter=books`

### Datos Mock
Los datos actualmente son estáticos para demostración:
```javascript
const mockStats = {
  totalBooks: 156,
  readBooks: 89,
  pendingBooks: 34,
  currentlyReading: 3,
  averageRating: 4.2,
  favoriteAuthor: 'J.K. Rowling',
  favoriteGenre: 'Fantasía',
  totalPages: 34567,
  averageReadingTime: '12 días'
}
```

## 🎬 Dashboard de Películas (`/dashboard/movies`)

### Características
- **Estadísticas generales**: Total de películas, vistas, pendientes, calificación promedio
- **Gráficas interactivas**:
  - Estado de visualización (Doughnut Chart)
  - Distribución de calificaciones (Bar Chart)
  - Géneros favoritos (Pie Chart)
  - Duración de películas (Bar Chart)
  - Progreso mensual (Line Chart)
  - Películas por década (Bar Chart)
- **Acceso directo**: Botón para ver biblioteca filtrada solo por películas

### Navegación
- **Menú lateral**: "Mis Películas" → `/dashboard/movies`
- **Desde dashboard**: "Ver Mi Biblioteca de Películas" → `/library?filter=movies`

### Datos Mock
Los datos actualmente son estáticos para demostración:
```javascript
const mockStats = {
  totalMovies: 287,
  watchedMovies: 165,
  pendingMovies: 78,
  currentlyWatching: 2,
  abandonedMovies: 42,
  averageRating: 3.8,
  favoriteDirector: 'Christopher Nolan',
  favoriteGenre: 'Acción',
  favoriteActor: 'Leonardo DiCaprio',
  totalWatchTime: '456 horas',
  averageDuration: '118 minutos',
  topRatedMovie: 'El Padrino'
}
```

## 🎨 Diseño y Estilo

### Paleta de Colores
**Dashboard de Libros:**
- Principal: `#4CAF50` (Verde)
- Estados: Verde, Azul, Naranja, Rojo

**Dashboard de Películas:**
- Principal: `#FF6B35` (Naranja)
- Estados: Verde, Azul, Naranja, Rojo

### Responsive Design
- **Desktop**: Grid de 2 columnas para gráficas
- **Tablet**: Gráficas apiladas
- **Mobile**: Layout completamente vertical

## 🔧 Configuración Técnica

### Chart.js Setup
```javascript
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  ArcElement,
  CategoryScale,
  LinearScale,
  BarElement,
  PointElement,
  LineElement
} from 'chart.js';

ChartJS.register(
  Title, Tooltip, Legend, ArcElement,
  CategoryScale, LinearScale, BarElement,
  PointElement, LineElement
);
```

### Tipos de Gráficas Implementadas
1. **Doughnut Chart**: Estados de lectura/visualización
2. **Bar Chart**: Calificaciones, duración, décadas
3. **Pie Chart**: Distribución de géneros
4. **Line Chart**: Progreso temporal

### Opciones de Configuración
```javascript
const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        color: '#d7dadc',
        font: { size: 12 }
      }
    }
  }
};
```

## 🚀 Futuras Mejoras

### Integración con Backend
- [ ] Conectar con APIs del backend para datos reales
- [ ] Implementar carga dinámica de estadísticas
- [ ] Agregar filtros por fechas y categorías

### Nuevas Funcionalidades
- [ ] Gráficas de tendencias anuales
- [ ] Comparativas entre años
- [ ] Exportación de reportes
- [ ] Gráficas de autores/directores más leídos/vistos

### Optimizaciones
- [ ] Lazy loading de gráficas
- [ ] Caching de datos estadísticos
- [ ] Animaciones más fluidas

## 📱 Uso

### Para Libros
1. Ir al menú lateral → "Mis Libros"
2. Ver estadísticas generales en las tarjetas superiores
3. Explorar gráficas interactivas
4. Usar botón "Ver Mi Biblioteca de Libros" para acceso detallado

### Para Películas
1. Ir al menú lateral → "Mis Películas"
2. Ver estadísticas generales en las tarjetas superiores
3. Explorar gráficas interactivas incluyendo análisis por décadas
4. Usar botón "Ver Mi Biblioteca de Películas" para acceso detallado

## 🔗 Enlaces Relacionados
- [Chart.js Documentation](https://www.chartjs.org/docs/)
- [Vue-ChartJS Documentation](https://vue-chartjs.org/)
- [Configuración del Router](../router/index.js)
- [Configuración del Menú](../composables/useSidebarMenu.js)