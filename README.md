# Library Vue - Sistema de Gestión de Biblioteca Personal

Una aplicación web completa para gestionar tu biblioteca personal de libros y películas, construida con Vue.js en el frontend y PHP en el backend.

## 🚀 Características

- **📚 Gestión de Libros**: Añadir, buscar, calificar y gestionar el estado de tus libros
- **🎬 Gestión de Películas**: Organizar y calificar tu colección de películas
- **🎮 Gestión de Videojuegos**: Administra tu biblioteca de juegos con información de IGDB
- **🔍 Búsqueda Avanzada**: Buscar por título, autor, género y más
- **📊 Importación de Datos**: Importar libros desde archivos CSV
- **🔐 Autenticación Google OAuth**: Login seguro con tu cuenta de Google
- **📱 Interfaz Responsive**: Diseño adaptado para móviles y escritorio
- **📝 Sistema de Logging**: Logging estructurado profesional con Monolog

## 🛠️ Tecnologías

### Frontend
- **Vue.js 3** - Framework progresivo de JavaScript
- **Pinia** - Gestión de estado para Vue
- **Vue Router** - Enrutamiento SPA
- **Axios** - Cliente HTTP

### Backend
- **PHP 7.4+** - Lenguaje del servidor
- **MySQL** - Base de datos
- **Google OAuth API** - Autenticación
- **Monolog** - Sistema de logging estructurado
- **Arquitectura Hexagonal** - Separación clara de responsabilidades

## 📋 Requisitos

- **Node.js** 14+ y npm
- **PHP** 7.4+
- **MySQL** 5.7+
- **Composer** para dependencias PHP
- **Docker** (opcional, para desarrollo con contenedores)

## 🚀 Instalación

### 1. Clonar el repositorio
```bash
git clone https://github.com/Henyckmazocker/libraryVue.git
cd libraryVue
```

### 2. Configurar el Frontend
```bash
cd frontend
npm install
```

### 3. Configurar el Backend
```bash
cd ../backend
composer install

# Configurar el sistema de logging
./setup_logging.sh development
# O en Windows: setup_logging.bat development

# Copiar y configurar variables de entorno
cp .env.example .env
# Editar .env con tus configuraciones
```

### 4. Configurar Base de Datos
```sql
CREATE DATABASE library_db;
-- Ejecutar scripts de migración según sea necesario
```

### 5. Configurar Google OAuth
1. Crear proyecto en [Google Cloud Console](https://console.cloud.google.com/)
2. Habilitar Google+ API
3. Crear credenciales OAuth 2.0
4. Configurar las variables `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET` en `.env`

### 6. Configurar IGDB API (para Videojuegos)
La aplicación utiliza IGDB (Internet Game Database), la base de datos de videojuegos de Twitch, para obtener información de juegos.

1. **Crear cuenta de Twitch Developer**
   - Ve a [dev.twitch.tv](https://dev.twitch.tv/) y regístrate/inicia sesión

2. **Registrar una aplicación**
   - En el panel de Twitch Developer, ve a "Your Console"
   - Click en "Register Your Application"
   - Nombre: `Library Vue` (o el que prefieras)
   - OAuth Redirect URL: `http://localhost` (no se usa para client credentials)
   - Category: `Application Integration`
   - Click en "Create"

3. **Obtener credenciales**
   - Una vez creada la app, verás tu **Client ID**
   - Click en "New Secret" para generar un **Client Secret**
   - Guarda ambos valores de forma segura

4. **Configurar en el backend (seguro)**
   - Abre `backend/.env`
   - Configura las siguientes variables:
   ```bash
   IGDB_CLIENT_ID=tu_client_id_aqui
   IGDB_CLIENT_SECRET=tu_client_secret_aqui
   ```
   - El backend generará automáticamente los tokens de acceso cuando sea necesario

**Nota:** Las credenciales se almacenan de forma segura en el backend y nunca se exponen en el frontend. El backend gestiona automáticamente la generación y renovación de tokens de acceso.

Para más información, consulta la [documentación oficial de IGDB API](https://api-docs.igdb.com/).

## 🏃‍♂️ Desarrollo

### Frontend (Puerto 8080)
```bash
cd frontend
npm run serve
```

### Backend (Puerto según configuración)
```bash
cd backend
php -S localhost:8000 -t public
```

### Con Docker
```bash
docker-compose up -d
```

## 📖 Documentación

La documentación completa está disponible en la carpeta [`/docs`](docs/):

- [📝 Sistema de Logging](docs/LOGGING_SYSTEM.md) - Documentación del sistema de logging implementado
- [🚀 Prompts de Implementación](docs/PROMPTS_IMPLEMENTACION.md) - Guía para implementar mejoras
- [📊 Análisis de Mejoras](docs/ANÁLISIS_MEJORAS_PROYECTO.md) - Análisis completo del proyecto

## 🧪 Testing

### Frontend
```bash
cd frontend
npm run test
```

### Backend
```bash
cd backend
vendor/bin/phpunit
```

## 📝 Sistema de Logging

El proyecto incluye un sistema de logging estructurado profesional:

- **Múltiples canales**: API, Database, Auth, Security, Application
- **Rotación automática** de archivos de log
- **Configuración por entorno** (development, production, testing)
- **Logging estructurado** con contexto JSON
- **Helpers de conveniencia** para diferentes tipos de eventos

Ver [documentación completa del sistema de logging](docs/LOGGING_SYSTEM.md).

## 🔧 Scripts Disponibles

### Frontend
```bash
npm run serve      # Desarrollo
npm run build      # Producción
npm run lint       # Linting
npm run test       # Testing
```

### Backend
```bash
composer install           # Instalar dependencias
php logging_examples.php   # Probar sistema de logging
./setup_logging.sh         # Configurar logging
```

## 🐳 Docker

El proyecto incluye configuración Docker completa:

```bash
# Desarrollo
docker-compose -f docker-compose.yml up -d

# Producción
docker-compose -f docker-compose.prod.yml up -d
```

## 🤝 Contribuir

1. Fork el proyecto
2. Crear una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abrir un Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

## 🔄 Estado del Proyecto

### ✅ Completado
- Sistema de logging estructurado con Monolog
- Autenticación Google OAuth
- CRUD básico de libros y películas
- Importación de archivos CSV
- Interfaz responsive

### 🚧 En Progreso
- Refactoring de controladores
- Sistema de routing dedicado
- Dependency injection
- Testing suite

### 📋 Próximas Mejoras
- Composables Vue
- TypeScript
- CI/CD pipeline
- API documentation
- Performance optimizations

## 📖 Documentación

La documentación completa del proyecto se encuentra centralizada en la carpeta `/docs`:

### 📚 Documentación por Módulo
- **[Backend Documentation](./docs/backend/)** - API, arquitectura y configuración del servidor
- **[Frontend Documentation](./docs/frontend/)** - Componentes, composables y estructura del cliente
- **[Database Documentation](./docs/database/)** - Esquemas, migraciones y estructura de datos
- **[Deployment Documentation](./docs/deployment/)** - Guías de despliegue y configuración

### 🔧 Documentación Técnica
- **[API Reference](./docs/api/)** - Endpoints, autenticación y ejemplos de uso
- **[Architecture Overview](./docs/architecture/)** - Diseño del sistema y patrones utilizados
- **[Development Guide](./docs/development/)** - Configuración del entorno de desarrollo
- **[Testing Guide](./docs/testing/)** - Estrategias de testing y ejecución de pruebas

### 📋 Guías Específicas
- **[Authentication System](./docs/auth/)** - Google OAuth y gestión de sesiones
- **[Logging System](./docs/logging/)** - Configuración y uso del sistema de logs
- **[Import/Export Features](./docs/import-export/)** - Funcionalidades de importación y exportación
- **[Performance Optimization](./docs/performance/)** - Optimizaciones y mejores prácticas

---

*Proyecto creado con ❤️ para gestionar bibliotecas personales*
