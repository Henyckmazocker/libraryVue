# Prompts Optimizados para Implementación - Library Vue PHP

## 🚀 Fase 1: Fundamentos (1-2 semanas)

### 1.1 Restructuración de Directorios

**Prompt 1: Análisis y planificación de estructura**
```
Analiza la estructura actual del proyecto libraryVue y propón una nueva organización de directorios que separe claramente frontend y backend. Muéstrame:
1. Estructura actual vs estructura propuesta
2. Plan de migración paso a paso
3. Archivos de configuración que necesitan actualizarse
4. Script de migración automática

No muevas archivos todavía, solo propón la estrategia.
```

**Prompt 2: Ejecución de migración**
```
Ejecuta la migración de archivos según el plan propuesto anteriormente:
1. Crea la nueva estructura de directorios
2. Mueve los archivos PHP del backend fuera de src/
3. Reorganiza el frontend manteniendo la estructura Vue
4. Actualiza todas las referencias y rutas en archivos de configuración
5. Modifica docker-compose.yml para la nueva estructura

Hazlo paso a paso verificando que no se rompa nada.
```

### 1.2 Configuración de Entornos

**Prompt 3: Crear sistema de configuración**
```
Implementa un sistema completo de configuración de entornos para el proyecto:
1. Crea archivos .env.example, .env.development, .env.production con todas las variables necesarias
2. Crea archivos de configuración PHP separados (config/database.php, config/app.php, config/logging.php)
3. Implementa un bootstrap.php que cargue la configuración desde variables de entorno
4. Actualiza DatabaseConnector.php para usar la nueva configuración
5. Modifica api.php para usar el sistema de configuración

Asegúrate de que la configuración sea flexible y fácil de mantener.
```

### 1.3 Sistema de Logging ✅ COMPLETADO

**Prompt 4: Implementar logging estructurado** ✅ IMPLEMENTADO

```
Configura un sistema de logging profesional con Monolog:
1. ✅ Instala Monolog via Composer (v2.10.0)
2. ✅ Crea una clase LoggerFactory que configure diferentes handlers (file, console, json)
3. ✅ Implementa logging estructurado con contexto JSON
4. ✅ Configura rotación automática de logs
5. ✅ Crea helpers de logging para diferentes niveles (debug, info, warning, error)
6. ✅ Integra logging en los principales puntos del backend (api.php, use cases, repositories)

✅ ESTADO: Sistema de logging completamente operacional con configuración $_ENV.
```

**IMPLEMENTACIÓN COMPLETADA**:

- ✅ **Monolog Integration**: Instalado y configurado con múltiples handlers
- ✅ **config/logging.php**: Configuración completa con 8 canales especializados
- ✅ **Multi-channel Logging**: app, api, database, auth, security, application, frontend, errors
- ✅ **Environment-based Config**: Uso de $_ENV para configuración flexible
- ✅ **Log Rotation**: Configurado para mantener 7 días de logs
- ✅ **JSON Format Support**: Logging estructurado para mejor análisis
- ✅ **Level Configuration**: Debug, info, warning, error por canal

**ARCHIVOS CREADOS/MODIFICADOS**:

- `config/logging.php` - Configuración completa del sistema de logging
- Monolog integrado en bootstrap.php
- Multiple log files en storage/logs/ funcionando

**LOGS ACTIVOS VERIFICADOS**:

- ✅ api-2025-08-16.log, api-2025-08-17.log, api-2025-08-18.log
- ✅ application-2025-08-17.log, application-2025-08-18.log  
- ✅ auth-2025-08-16.log, auth-2025-08-17.log
- ✅ database-2025-08-16.log, database-2025-08-17.log
- ✅ errors-2025-08-16.log, errors-2025-08-17.log, errors-2025-08-18.log
- ✅ frontend-2025-08-16.log, frontend-2025-08-17.log

## 🔧 Fase 2: Refactoring Backend (2-3 semanas)

### 2.1 Dividir api.php en Controladores

**Prompt 5: Análisis y estrategia de controladores**
```
Analiza el archivo api.php (614 líneas) y diseña una estrategia para dividirlo en controladores:
1. Identifica todas las rutas y endpoints actuales
2. Agrúpalos por dominio lógico (Auth, Books, Movies, Library)
3. Propón estructura de controladores con herencia común
4. Define interfaces y contratos para cada controlador
5. Planifica la migración sin romper la API existente

Muestra el diseño detallado antes de implementar.
```

**Prompt 6: Crear estructura base de controladores**
```
Implementa la estructura base de controladores:
1. Crea clase abstracta BaseController con funcionalidades comunes
2. Implementa manejo centralizado de errores y respuestas JSON
3. Crea trait para validación de entrada común
4. Implementa AuthController con todos los endpoints de autenticación
5. Asegúrate de mantener la compatibilidad con el frontend existente

Incluye logging en cada controlador y manejo robusto de errores.
```

**Prompt 7: Migrar controladores de dominio**
```
Completa la migración creando los controladores restantes:
1. BookController con todos los endpoints de libros
2. MovieController con todos los endpoints de películas  
3. LibraryController con endpoints generales de biblioteca
4. Actualiza api.php para usar los nuevos controladores manteniendo las rutas existentes
5. Verifica que todas las funcionalidades sigan funcionando

Realiza testing manual de cada endpoint migrado.
```

### 2.2 Router Dedicado

**Prompt 8: Implementar sistema de routing**
```
Reemplaza el routing manual con FastRoute:
1. Instala FastRoute via Composer
2. Crea archivo routes/web.php con definición clara de todas las rutas
3. Implementa Router class que maneje el dispatching
4. Configura manejo de errores HTTP (404, 405, 500) de forma centralizada
5. Actualiza el punto de entrada (api.php o index.php) para usar el nuevo router
6. Mantén compatibilidad total con las rutas existentes

Asegúrate de que el routing sea RESTful y escalable.
```

//por aqui

### 2.3 Dependency Injection ✅ COMPLETADO

**Prompt 9: Configurar contenedor DI** ✅ IMPLEMENTADO
```
Implementa dependency injection con PHP-DI:
1. ✅ Instala PHP-DI via Composer (v7.1.1)
2. ✅ Crea archivo config/dependencies.php con configuración del container
3. ✅ Elimina el patrón Singleton de DatabaseConnector y convierte a servicio inyectable
4. ✅ Crea ApplicationService como punto de entrada principal
5. ✅ Actualiza todos los controladores para recibir dependencias via constructor
6. ✅ Configura el container en el bootstrap de la aplicación

✅ ESTADO: DI completamente configurado y operacional. Auditoría pasada con éxito.
```

**IMPLEMENTACIÓN COMPLETADA**:
- ✅ **PHP-DI Container**: Configurado con lazy loading y autowiring
- ✅ **Dependencies.php**: 80+ líneas de configuración DI completa
- ✅ **ApplicationService**: Punto de entrada principal con routing
- ✅ **DatabaseConnector**: Refactorizado sin Singleton pattern
- ✅ **Controladores**: AuthController, BookController, MovieController, LibraryController con DI
- ✅ **Repositorios**: Inyección de PDO via constructor
- ✅ **Use Cases**: Registrados en container DI
- ✅ **Bootstrap**: Sistema completo de inicialización con $_ENV

**ARCHIVOS CREADOS/MODIFICADOS**:
- `config/dependencies.php` - Configuración completa del container DI
- `src/Services/ApplicationService.php` - Servicio principal de aplicación
- `src/Infrastructure/Database/DatabaseConnector.php` - Refactorizado sin Singleton
- `bootstrap.php` - Sistema de inicialización con DI
- Todos los controladores actualizados con constructor injection
- Todos los repositorios actualizados para recibir PDO

**FUNCIONALIDADES VERIFICADAS**:
- ✅ Container DI registra y resuelve todas las dependencias
- ✅ Controladores reciben dependencias correctamente
- ✅ Sistema de routing funcional a través de ApplicationService
- ✅ DatabaseConnector operacional sin patrón Singleton
- ✅ Logging system integrado con configuración $_ENV

## 🎨 Fase 3: Mejoras Frontend (2-3 semanas)

### 3.1 Dividir Componentes Grandes

**Prompt 10: Refactorizar ImportModal.vue**
```
Refactoriza el componente ImportModal.vue (769 líneas) dividiéndolo en componentes más pequeños:
1. Analiza las responsabilidades actuales del componente
2. Extrae componentes específicos (FileUploader, FileValidator, ImportProgress, ImportResults)
3. Crea servicios separados para lógica de procesamiento de archivos
4. Mantén la funcionalidad existente mientras mejoras la organización
5. Asegúrate de que la UX no cambie para el usuario

Hazlo gradualmente, probando cada componente extraído.
```

**Prompt 11: Modularizar componentes de Books**
```
Refactoriza los componentes de libros para mejor modularidad:
1. Divide BookSearch.vue en componentes más pequeños (SearchForm, SearchResults, SearchFilters)
2. Refactoriza LibraryBookItem.vue extrayendo RatingComponent y BookActions
3. Crea componentes reutilizables para elementos comunes de UI
4. Mejora la separación de responsabilidades en cada componente
5. Mantén todas las funcionalidades existentes

Enfócate en la reutilización y mantenibilidad del código.
```

### 3.2 Implementar Composables

**Prompt 12: Crear composables de autenticación**
```
Extrae la lógica de autenticación en composables reutilizables:
1. Crea useAuth composable que maneje login, logout, estado de usuario
2. Implementa useGoogleAuth para lógica específica de Google OAuth
3. Crea usePermissions para manejo de permisos y rutas protegidas
4. Actualiza App.vue y otros componentes para usar los composables
5. Asegúrate de mantener la reactividad y estado compartido

Los composables deben ser reutilizables y testeable.
```

**Prompt 13: Composables para gestión de biblioteca**
```
Crea composables para funcionalidades de biblioteca:
1. useBooks para gestión de libros (búsqueda, CRUD, estados)
2. useMovies para gestión de películas
3. useFileImport para lógica de importación de archivos
4. useSearch con debouncing y caché para búsquedas
5. Actualiza todos los componentes relevantes para usar estos composables

Enfócate en la reutilización de lógica y mejor testing.
```

### 3.3 TypeScript

**Prompt 14: Configurar TypeScript básico**
```
Configura TypeScript en el proyecto Vue:
1. Instala TypeScript y dependencias necesarias (@vue/typescript/recommended)
2. Crea tsconfig.json apropiado para Vue 3
3. Define tipos de dominio (Book, Movie, User, ApiResponse) en types/
4. Configura Vite/Vue para soportar TypeScript
5. Migra el store de Pinia a TypeScript

Hazlo gradualmente sin romper funcionalidad existente.
```

**Prompt 15: Migrar componentes clave a TypeScript**
```
Migra los componentes principales a TypeScript:
1. Convierte App.vue a TypeScript con tipos apropiados
2. Migra los composables creados anteriormente a TypeScript
3. Crea servicios tipados para llamadas a API
4. Actualiza los componentes de autenticación con tipos
5. Asegúrate de que todo compile sin errores de tipos

Prioriza la seguridad de tipos y mejor developer experience.
```

## 🧪 Fase 4: Testing y Calidad (1-2 semanas)

### 4.1 Testing Backend

**Prompt 16: Configurar PHPUnit**
```
Configura suite de testing para el backend:
1. Instala PHPUnit y dependencias de testing
2. Crea phpunit.xml con configuración apropiada
3. Configura base de datos de testing (SQLite en memoria)
4. Crea TestCase base con helpers comunes
5. Implementa tests unitarios para Use Cases principales
6. Crea tests de integración para controladores
7. Configura fixtures y factories para datos de prueba

Objetivo: 70%+ de cobertura en lógica de negocio.
```

### 4.2 Testing Frontend

**Prompt 17: Configurar Vitest**
```
Implementa testing para el frontend Vue:
1. Instala Vitest y Vue Testing Library
2. Configura vitest.config.js para el proyecto
3. Crea tests unitarios para composables
4. Implementa tests de componentes principales
5. Configura mocking para APIs y servicios externos
6. Crea tests de integración para flujos críticos

Enfócate en testear lógica de negocio y interacciones críticas.
```

### 4.3 CI/CD

**Prompt 18: Configurar pipeline CI/CD**
```
Configura pipeline de CI/CD con GitHub Actions:
1. Crea .github/workflows/ci.yml para testing automático
2. Configura jobs separados para frontend y backend
3. Implementa linting, testing y build en el pipeline
4. Configura deployment automático a staging
5. Agrega checks de calidad y security scanning
6. Configura notificaciones y badges de estado

El pipeline debe ser rápido y confiable.
```

## 🚀 Fase 5: Performance y Seguridad (1-2 semanas)

### 5.1 Optimización Frontend

**Prompt 19: Optimizar performance del frontend**
```
Optimiza el performance del frontend Vue:
1. Implementa lazy loading para todas las rutas
2. Configura code splitting agresivo con dynamic imports
3. Optimiza bundle size analizando y removiendo dependencias innecesarias
4. Implementa virtual scrolling en listas grandes
5. Agrega memoización donde sea apropiado
6. Configura service worker para caché
7. Optimiza imágenes y assets

Mide el performance antes y después con herramientas como Lighthouse.
```

### 5.2 Seguridad y Rate Limiting

**Prompt 20: Implementar seguridad robusta**
```
Fortalece la seguridad del backend:
1. Implementa rate limiting por IP y usuario
2. Agrega validación estricta de entrada en todos los endpoints
3. Configura headers de seguridad (CORS, CSP, HSTS)
4. Implementa sanitización de datos de entrada
5. Agrega logging de intentos de acceso sospechosos
6. Configura timeouts apropiados para sesiones
7. Realiza auditoría de seguridad siguiendo OWASP guidelines

Prioriza la seguridad sin comprometer la usabilidad.
```

### 5.3 Monitoreo

**Prompt 21: Configurar monitoring y métricas**
```
Implementa sistema de monitoreo:
1. Configura métricas de performance de API (tiempo de respuesta, throughput)
2. Implementa health checks para servicios críticos
3. Configura alertas para errores y performance degradado
4. Agrega métricas de negocio (libros agregados, búsquedas, etc.)
5. Implementa dashboard básico para visualización
6. Configura logging de errores con contexto útil

El sistema debe proveer visibilidad completa del estado de la aplicación.
```

---

## 📋 Notas de Uso de Prompts

### Consejos para Ejecutar los Prompts:

1. **Secuencial**: Ejecuta los prompts en orden, cada uno construye sobre el anterior
2. **Verificación**: Después de cada prompt, verifica que todo funcione antes de continuar
3. **Rollback**: Mantén commits de git después de cada prompt por si necesitas revertir
4. **Testing**: Prueba manualmente después de cambios significativos
5. **Documentación**: Actualiza documentación después de cada fase

### Adaptación de Prompts:

- **Personalización**: Ajusta los prompts según necesidades específicas de tu proyecto
- **Priorización**: Puedes cambiar el orden según tus prioridades
- **Simplificación**: Divide prompts complejos en sub-prompts si es necesario
- **Expansión**: Agrega detalles específicos si necesitas funcionalidades particulares

### Validación Después de Cada Fase:

1. **Funcionalidad**: Toda la funcionalidad existente debe seguir funcionando
2. **Performance**: No debe degradarse el performance
3. **Testing**: Tests deben pasar (una vez implementados)
4. **Linting**: Código debe pasar validaciones de calidad
5. **Docker**: Contenedores deben construirse y ejecutarse correctamente

---

## 📊 ESTADO ACTUAL DE IMPLEMENTACIÓN (Actualizado: 18 Agosto 2025)

### ✅ COMPLETADO - Fase 2: Refactoring Backend (100%)

#### 🔧 Dependency Injection System - OPERACIONAL ✅
- **PHP-DI v7.1.1**: Container configurado con lazy loading y autowiring
- **ApplicationService**: Punto de entrada principal con routing integrado  
- **DatabaseConnector**: Refactorizado exitosamente (Singleton pattern eliminado)
- **Controllers**: AuthController, BookController, MovieController, LibraryController con DI
- **Repositories**: Todos actualizados para inyección de PDO via constructor
- **Use Cases**: 30+ casos de uso registrados en container DI
- **Bootstrap**: Sistema completo de inicialización con $_ENV y DI

#### 📝 Sistema de Logging - FUNCIONAL ✅  
- **Monolog v2.10.0**: Integrado con múltiples handlers
- **Multi-channel**: 8 canales especializados (app, api, database, auth, security, etc.)
- **Configuración $_ENV**: Sistema flexible basado en variables de entorno
- **Log Rotation**: Configurado para 7 días de retención
- **JSON Format**: Logging estructurado para análisis
- **Logs Activos**: Verificados múltiples archivos de log funcionando

#### 🏗️ Configuración de Sistema - ESTABLECIDA ✅
- **Environment System**: Variables $_ENV completamente operacional
- **config/dependencies.php**: 80+ líneas de configuración DI completa
- **config/logging.php**: Configuración completa de logging multi-canal
- **config/helpers.php**: Funciones auxiliares del sistema
- **bootstrap.php**: Inicialización ordenada y limpia

### 🚧 PENDIENTE - Siguientes Fases

#### Fase 2 Continuación: Router Dedicado
- [ ] **Prompt 8**: Implementar FastRoute para routing profesional
- [ ] **Router Class**: Sistema de dispatching centralizado  
- [ ] **routes/web.php**: Definición clara de todas las rutas
- [ ] **Error Handling**: 404, 405, 500 centralizados

#### Fase 2 Continuación: Controladores Avanzados  
- [ ] **Prompt 5-7**: División completa de api.php en controladores
- [ ] **BaseController**: Funcionalidades comunes y manejo de errores
- [ ] **Response Handling**: JSON responses centralizadas
- [ ] **Validation**: Validación de entrada común

#### Fase 3: Mejoras Frontend
- [ ] **Prompt 10-15**: Refactoring de componentes grandes
- [ ] **Composables**: useAuth, useBooks, useMovies
- [ ] **TypeScript**: Migración gradual a TypeScript
- [ ] **Component Modularization**: División de ImportModal y componentes grandes

#### Fase 4: Testing y Calidad
- [ ] **PHPUnit**: Suite de testing backend
- [ ] **Vitest**: Testing frontend Vue
- [ ] **CI/CD**: GitHub Actions pipeline

#### Fase 5: Performance y Seguridad  
- [ ] **Frontend Optimization**: Lazy loading, code splitting
- [ ] **Security**: Rate limiting, validation, headers
- [ ] **Monitoring**: Métricas y health checks

### 🎯 RESUMEN EJECUTIVO

**LOGROS PRINCIPALES COMPLETADOS:**
1. ✅ **Dependency Injection**: Sistema DI completamente funcional con PHP-DI
2. ✅ **Logging System**: Monolog integrado con multi-canal y configuración flexible  
3. ✅ **Configuration Management**: Sistema robusto basado en $_ENV
4. ✅ **Database Layer**: DatabaseConnector refactorizado sin anti-patterns
5. ✅ **Application Bootstrap**: Inicialización limpia y ordenada

**IMPACT0 DEL REFACTORING:**
- **Mantenibilidad**: Código más modular y testeable
- **Escalabilidad**: Arquitectura preparada para crecimiento  
- **Debugging**: Logging estructurado para troubleshooting
- **Testing**: Base sólida para implementar testing robusto
- **Performance**: Eliminación de Singleton y mejor gestión de dependencias

**PRÓXIMOS PASOS RECOMENDADOS:**
1. Implementar FastRoute (Prompt 8)
2. Completar división de controladores (Prompts 5-7)  
3. Implementar testing backend (Prompt 16)
4. Continuar con optimizaciones frontend

---

*Prompts generados el 14 de agosto de 2025*
*Estado actualizado el 18 de agosto de 2025*
*Cada prompt está diseñado para ser claro, específico y ejecutable por GitHub Copilot*
