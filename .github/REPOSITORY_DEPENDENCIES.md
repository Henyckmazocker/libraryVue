# 📊 ANÁLISIS DE DEPENDENCIAS DE REPOSITORIOS

**Fecha de análisis**: 18 de diciembre de 2025  
**Objetivo**: Mapear todas las dependencias de los repositorios actuales antes de la refactorización  
**Parte de**: FASE 0 - Preparación (Tarea 0.3)

---

## 📋 ÍNDICE

1. [MySqlBookRepository - Dependencias](#1-mysqlbookrepository)
2. [MySqlMovieRepository - Dependencias](#2-mysqlmovierepository)
3. [MySqlUserRepository - Dependencias](#3-mysqluserrepository)
4. [Resumen de Impacto](#4-resumen-de-impacto)
5. [Estrategia de Migración](#5-estrategia-de-migración)

---

## 1. MySqlBookRepository

### 📊 Métricas
- **Líneas**: 2,435
- **Métodos públicos**: 52
- **Use Cases dependientes**: 8
- **Responsabilidades**: 6 (Books CRUD, UserBooks, Tags, Notes, ReadingSessions, Progress)

### 🔗 Use Cases que dependen de este repositorio

#### **Books Module** (8 Use Cases)

1. **AddBookUseCase** (`Domain/UseCases/Books/AddBookUseCase.php`)
   - **Métodos usados**:
     - `findById()` - Verificar si el libro existe
     - `save()` - Guardar nuevo libro en catálogo
     - `addBookToUser()` - Añadir libro a la biblioteca del usuario
     - `updateUserBookRating()` - Actualizar rating personal
   - **Complejidad**: Alta
   - **Líneas**: ~99

2. **DeleteBookUseCase** (`Domain/UseCases/Books/DeleteBookUseCase.php`)
   - **Métodos usados**:
     - `removeBookFromUser()` - Eliminar libro de la biblioteca del usuario
   - **Complejidad**: Media
   - **Líneas**: ~48

3. **EditUserBookUseCase** (`Domain/UseCases/Books/EditUserBookUseCase.php`)
   - **Métodos usados**:
     - `editUserBook()` - Editar datos personales del libro
     - `updateUserBookStatuses()` - Actualizar estados
     - `removeAllUserBookTags()` - Limpiar tags
     - `assignUserBookTag()` - Asignar tag existente
     - `addUserBookTag()` - Crear y asignar nuevo tag
     - `addUserBookNote()` - Añadir nota
   - **Complejidad**: Muy Alta
   - **Líneas**: ~90

4. **GetAllBooksUseCase** (`Domain/UseCases/Books/GetAllBooksUseCase.php`)
   - **Métodos usados**:
     - `findAll()` - Obtener todos los libros del catálogo
   - **Complejidad**: Baja
   - **Líneas**: ~23

5. **GetBookAllowedStatusesUseCase** (`Domain/UseCases/Books/GetBookAllowedStatusesUseCase.php`)
   - **Métodos usados**:
     - `fetchAllowedStatuses()` - Obtener estados permitidos
   - **Complejidad**: Baja
   - **Líneas**: ~28

6. **GetBooksUseCase** (`Domain/UseCases/Books/GetBooksUseCase.php`)
   - **Métodos usados**:
     - `findBooksByUser()` - Obtener libros del usuario con filtros
   - **Complejidad**: Media
   - **Líneas**: ~36

7. **UpdateBookRatingUseCase** (`Domain/UseCases/Books/UpdateBookRatingUseCase.php`)
   - **Métodos usados**:
     - `updateUserBookRating()` - Actualizar rating personal
   - **Complejidad**: Media
   - **Líneas**: ~54

8. **UpdateBookUserStatusesUseCase** (`Domain/UseCases/Books/UpdateBookUserStatusesUseCase.php`)
   - **Métodos usados**:
     - `updateUserBookStatuses()` - Actualizar estados del libro
   - **Complejidad**: Media
   - **Líneas**: ~49

#### **Users Module** (1 Use Case)

9. **AddBookToUserUseCase** (`Domain/UseCases/Users/AddBookToUserUseCase.php`)
   - **Métodos usados**:
     - `findById()` - Verificar existencia del libro
   - **Complejidad**: Media
   - **Líneas**: ~44

#### **General** (1 Use Case)

10. **GetLibraryUseCase** (`Domain/UseCases/GetLibraryUseCase.php`)
    - **Métodos usados**:
      - `findAll()` - Obtener libros con filtros
    - **Complejidad**: Media
    - **Líneas**: ~27

### 📦 Métodos públicos agrupados por responsabilidad

#### **GRUPO 1: Book CRUD** (8 métodos)
```php
fetchAllowedStatuses()          // Obtener estados permitidos
findAll()                       // Buscar todos los libros
findById()                      // Buscar libro por ISBN
findByUserStatus()              // Buscar libros por estado de usuario
save()                          // Guardar libro nuevo
deleteByIsbn()                  // Eliminar libro del catálogo
getTotalPages()                 // Obtener total de páginas del libro
hasCompletedBook()              // Verificar si el usuario completó el libro
```

#### **GRUPO 2: User-Book Relationship** (7 métodos)
```php
addBookToUser()                 // Añadir libro a biblioteca del usuario
removeBookFromUser()            // Eliminar libro de biblioteca del usuario
findBooksByUser()               // Obtener libros del usuario
updateUserBookStatuses()        // Actualizar estados del usuario
updateUserBookRating()          // Actualizar rating personal
getUserBookStatuses()           // Obtener estados del usuario para un libro
editUserBook()                  // Editar datos personales del libro
```

#### **GRUPO 3: Tags** (6 métodos)
```php
addUserBookTag()                // Crear tag nuevo
assignUserBookTag()             // Asignar tag a libro
removeAllUserBookTags()         // Eliminar todos los tags de un libro
getBookTags()                   // Obtener tags de un libro
getUserBookTags()               // Obtener todos los tags del usuario
getAllowedTags()                // Obtener tags permitidos
```

#### **GRUPO 4: Notes** (2 métodos)
```php
addUserBookNote()               // Añadir nota a un libro
getBookNotesByPage()            // Obtener notas por página
```

#### **GRUPO 5: Reading Sessions** (16 métodos) ⚠️ **SUBSISTEMA COMPLETO**
```php
createReadingSession()          // Crear nueva sesión de lectura
getActiveReadingSession()       // Obtener sesión activa
completeReadingSession()        // Completar sesión
updateSessionStatus()           // Actualizar estado de sesión
getReadingSessionHistory()      // Obtener historial de sesiones
getSessionProgress()            // Obtener progreso de una sesión
getUserActiveReadingSessions()  // Obtener sesiones activas del usuario
pauseReadingSession()           // Pausar sesión
resumeReadingSession()          // Reanudar sesión
abandonReadingSession()         // Abandonar sesión
deleteReadingSession()          // Eliminar sesión
getUserReadingSessions()        // Obtener sesiones del usuario
getNextSessionNumber()          // Obtener número de próxima sesión
getReadingSessionStats()        // Obtener estadísticas de sesiones
getBookCompletionCount()        // Obtener conteo de completaciones
updateBookStatusesBasedOnSessions() // Actualizar estados basado en sesiones
```

#### **GRUPO 6: Reading Progress** (13 métodos) ⚠️ **SUBSISTEMA COMPLETO**
```php
getCurrentPage()                // Obtener página actual
getLastProgressPage()           // Obtener última página de progreso
addReadingProgressHistory()     // Añadir historial de progreso
getReadingProgressHistory()     // Obtener historial de progreso
getMonthlyPagesReadStats()      // Obtener estadísticas mensuales
updateReadingProgressWithSession() // Actualizar progreso con sesión
getBookReadingSummary()         // Obtener resumen de lectura
getDetailedProgressHistory()    // Obtener historial detallado
getUserReadingStats()           // Obtener estadísticas del usuario
getCurrentReadingSessions()     // Obtener sesiones actuales
```

### 🎯 Plan de división propuesto

```
MySqlBookRepository (2,435L, 52 métodos)
    ↓
    ├── MySqlBookRepository           (200L, 8 métodos)   - Book CRUD
    ├── MySqlUserBookRepository       (180L, 7 métodos)   - User-Book
    ├── MySqlBookTagRepository        (150L, 6 métodos)   - Tags
    ├── MySqlBookNoteRepository       (100L, 2 métodos)   - Notes
    ├── MySqlReadingSessionRepository (350L, 16 métodos)  - Sessions
    └── MySqlReadingProgressRepository (250L, 13 métodos) - Progress
```

**Total después**: ~1,230 líneas en 6 archivos especializados

---

## 2. MySqlMovieRepository

### 📊 Métricas
- **Líneas**: 831
- **Métodos públicos**: 26
- **Use Cases dependientes**: 7
- **Responsabilidades**: 4 (Movies CRUD, UserMovies, Tags, Notes)

### 🔗 Use Cases que dependen de este repositorio

#### **Movies Module** (7 Use Cases)

1. **AddMovieUseCase** (`Domain/UseCases/Movies/AddMovieUseCase.php`)
   - **Métodos usados**:
     - `findById()` - Verificar si la película existe
     - `save()` - Guardar nueva película
     - `addMovieToUser()` - Añadir película a biblioteca del usuario
     - `updateUserMovieRating()` - Actualizar rating (si aplica)
   - **Complejidad**: Alta
   - **Líneas**: ~90

2. **DeleteMovieUseCase** (`Domain/UseCases/Movies/DeleteMovieUseCase.php`)
   - **Métodos usados**:
     - `removeMovieFromUser()` - Eliminar película de biblioteca
   - **Complejidad**: Media
   - **Líneas**: ~48

3. **EditUserMovieUseCase** (`Domain/UseCases/Movies/EditUserMovieUseCase.php`)
   - **Métodos usados**:
     - `editUserMovie()` - Editar datos personales
     - `updateUserMovieStatuses()` - Actualizar estados
     - `removeAllUserMovieTags()` - Limpiar tags
     - `assignUserMovieTag()` - Asignar tag
     - `addUserMovieTag()` - Crear tag
     - `addUserMovieNote()` - Añadir nota
   - **Complejidad**: Muy Alta
   - **Líneas**: ~80

4. **GetMovieAllowedStatusesUseCase** (`Domain/UseCases/Movies/GetMovieAllowedStatusesUseCase.php`)
   - **Métodos usados**:
     - `fetchAllowedStatuses()` - Obtener estados permitidos
   - **Complejidad**: Baja
   - **Líneas**: ~28

5. **GetMoviesUseCase** (`Domain/UseCases/Movies/GetMoviesUseCase.php`)
   - **Métodos usados**:
     - `findMoviesByUser()` - Obtener películas del usuario
   - **Complejidad**: Media
   - **Líneas**: ~36

6. **UpdateMovieRatingUseCase** (`Domain/UseCases/Movies/UpdateMovieRatingUseCase.php`)
   - **Métodos usados**:
     - `updateUserMovieRating()` - Actualizar rating personal
   - **Complejidad**: Media
   - **Líneas**: ~54

7. **UpdateMovieUserStatusesUseCase** (`Domain/UseCases/Movies/UpdateMovieUserStatusesUseCase.php`)
   - **Métodos usados**:
     - `updateUserMovieStatuses()` - Actualizar estados
   - **Complejidad**: Media
   - **Líneas**: ~46

### 📦 Métodos públicos agrupados por responsabilidad

#### **GRUPO 1: Movie CRUD** (9 métodos)
```php
fetchAllowedStatuses()          // Obtener estados permitidos
findAll()                       // Buscar todas las películas
findAllWithFilters()            // Buscar con filtros
findById()                      // Buscar película por ID
save()                          // Guardar película nueva
deleteByIsbn()                  // Eliminar por ISBN
deleteById()                    // Eliminar por ID numérico
deleteByName()                  // Eliminar por nombre
updateMovieRating()             // Actualizar rating global
```

#### **GRUPO 2: User-Movie Relationship** (7 métodos)
```php
addMovieToUser()                // Añadir película a biblioteca
removeMovieFromUser()           // Eliminar de biblioteca
findMoviesByUser()              // Obtener películas del usuario
updateUserMovieStatuses()       // Actualizar estados del usuario
updateUserMovieRating()         // Actualizar rating personal
getUserMovieStatuses()          // Obtener estados del usuario
editUserMovie()                 // Editar datos personales
```

#### **GRUPO 3: Tags** (6 métodos)
```php
addUserMovieTag()               // Crear tag nuevo
assignUserMovieTag()            // Asignar tag a película
removeAllUserMovieTags()        // Eliminar todos los tags
getMovieTags()                  // Obtener tags de película
getUserMovieTags()              // Obtener todos los tags del usuario
getAllowedTags()                // Obtener tags permitidos
```

#### **GRUPO 4: Notes** (2 métodos)
```php
addUserMovieNote()              // Añadir nota
getMovieNotesByPage()           // Obtener notas
```

#### **GRUPO 5: Legacy/Deprecated** (2 métodos)
```php
updateUserStatuses()            // ⚠️ Deprecated - usar updateUserMovieStatuses()
```

### 🎯 Plan de división propuesto

```
MySqlMovieRepository (831L, 26 métodos)
    ↓
    ├── MySqlMovieRepository          (180L, 9 métodos)  - Movie CRUD
    ├── MySqlUserMovieRepository      (150L, 7 métodos)  - User-Movie
    ├── MySqlMovieTagRepository       (120L, 6 métodos)  - Tags
    └── MySqlMovieNoteRepository      (80L, 2 métodos)   - Notes
```

**Total después**: ~530 líneas en 4 archivos especializados

---

## 3. MySqlUserRepository

### 📊 Métricas
- **Líneas**: 415
- **Métodos públicos**: 12
- **Use Cases dependientes**: 10+
- **Responsabilidades**: 4 (User CRUD, UserBooks, UserMovies, Stats)

### 🔗 Use Cases que dependen de este repositorio

#### **Auth Module** (1 Use Case)

1. **LoginUserUseCase** (`Domain/UseCases/Auth/LoginUserUseCase.php`)
   - **Métodos usados**:
     - `findByGoogleId()` - Buscar usuario por Google ID
     - `save()` - Crear usuario nuevo
     - `update()` - Actualizar última conexión
   - **Complejidad**: Alta
   - **Líneas**: ~80

#### **Users Module** (1 Use Case)

2. **AddBookToUserUseCase** (`Domain/UseCases/Users/AddBookToUserUseCase.php`)
   - **Métodos usados**:
     - `findById()` - Verificar existencia del usuario
     - `hasUserBook()` - Verificar si ya tiene el libro
   - **Complejidad**: Media
   - **Líneas**: ~44

#### **Movies Module** (2 Use Cases)

3. **EditUserMovieUseCase** (`Domain/UseCases/Movies/EditUserMovieUseCase.php`)
   - **Métodos usados**:
     - `hasUserMovie()` - Verificar si tiene la película
     - `addUserMovie()` - Añadir película si no existe
   - **Complejidad**: Media
   - **Líneas**: ~80

4. **UpdateMovieUserStatusesUseCase** (`Domain/UseCases/Movies/UpdateMovieUserStatusesUseCase.php`)
   - **Métodos usados**:
     - `findById()` - Verificar usuario
     - `hasUserMovie()` - Verificar película
   - **Complejidad**: Media
   - **Líneas**: ~46

#### **Books Module** (3 Use Cases indirectos)

5. **UpdateBookRatingUseCase** - Usa `findById()` para validación
6. **UpdateBookUserStatusesUseCase** - Usa `findById()` para validación
7. **DeleteBookUseCase** - Usa `findById()` para validación

#### **General** (1 Use Case)

8. **GetLibraryItemsUseCase** (`Domain/UseCases/GetLibraryItemsUseCase.php`)
   - **Métodos usados**:
     - `getUserBooks()` - Obtener libros del usuario
     - `getUserMovies()` - Obtener películas del usuario
     - `getUserLibraryStats()` - Obtener estadísticas
   - **Complejidad**: Alta
   - **Líneas**: ~100+

### 📦 Métodos públicos agrupados por responsabilidad

#### **GRUPO 1: User CRUD** (6 métodos)
```php
findByGoogleId()                // Buscar por Google ID (OAuth)
findById()                      // Buscar por ID numérico
findByEmail()                   // Buscar por email
save()                          // Crear nuevo usuario
update()                        // Actualizar usuario existente
delete()                        // Eliminar usuario (si existe)
```

#### **GRUPO 2: User-Book Relationship** (2 métodos)
```php
getUserBooks()                  // Obtener libros del usuario
hasUserBook()                   // Verificar si usuario tiene libro
```

#### **GRUPO 3: User-Movie Relationship** (3 métodos)
```php
getUserMovies()                 // Obtener películas del usuario
hasUserMovie()                  // Verificar si usuario tiene película
addUserMovie()                  // Añadir película a biblioteca
```

#### **GRUPO 4: Stats** (1 método)
```php
getUserLibraryStats()           // Obtener estadísticas de biblioteca
```

### 🎯 Plan de división propuesto

```
MySqlUserRepository (415L, 12 métodos)
    ↓
    ├── MySqlUserRepository           (150L, 6 métodos)  - User CRUD
    ├── MySqlUserBookRepository       (120L, 2 métodos)  - User-Book
    ├── MySqlUserMovieRepository      (120L, 3 métodos)  - User-Movie
    └── UserLibraryStatisticsService  (100L, 1 método)   - Stats (Domain Service)
```

**Total después**: ~490 líneas en 3 repos + 1 service

---

## 4. Resumen de Impacto

### 📊 Tabla comparativa

| Repositorio | Líneas | Métodos | Use Cases | Responsabilidades | Nueva Cantidad |
|-------------|--------|---------|-----------|-------------------|----------------|
| **MySqlBookRepository** | 2,435 | 52 | 10 | 6 | **6 archivos** |
| **MySqlMovieRepository** | 831 | 26 | 7 | 4 | **4 archivos** |
| **MySqlUserRepository** | 415 | 12 | 8+ | 4 | **3 archivos + 1 service** |
| **TOTAL** | **3,681** | **90** | **25+** | **14** | **15 archivos** |

### 🎯 Impacto por Use Case

#### **Alta Prioridad** (Requieren cambios en múltiples repositorios)
1. **AddBookUseCase** - 4 métodos de BookRepository
2. **EditUserBookUseCase** - 6 métodos de BookRepository
3. **AddMovieUseCase** - 4 métodos de MovieRepository
4. **EditUserMovieUseCase** - 6 métodos de MovieRepository + 2 de UserRepository
5. **GetLibraryItemsUseCase** - 3 métodos de UserRepository

#### **Media Prioridad** (Requieren 1-2 cambios)
6. **GetBooksUseCase** - 1 método de BookRepository
7. **GetMoviesUseCase** - 1 método de MovieRepository
8. **UpdateBookRatingUseCase** - 1 método de BookRepository
9. **UpdateMovieRatingUseCase** - 1 método de MovieRepository
10. **DeleteBookUseCase** - 1 método de BookRepository
11. **DeleteMovieUseCase** - 1 método de MovieRepository

#### **Baja Prioridad** (Cambios mínimos)
12. **GetAllBooksUseCase** - 1 método simple
13. **GetBookAllowedStatusesUseCase** - 1 método simple
14. **GetMovieAllowedStatusesUseCase** - 1 método simple
15. **LoginUserUseCase** - Sin cambios (interface UserRepository se mantiene)

### ⚠️ Métodos más usados (Alto impacto)

| Método | Repositorio | Veces usado | Use Cases afectados |
|--------|-------------|-------------|---------------------|
| `findById()` | BookRepository | 4 | AddBook, GetAllBooks, AddBookToUser, GetLibrary |
| `findById()` | UserRepository | 6+ | Login, UpdateBook*, UpdateMovie*, Delete* |
| `save()` | BookRepository | 2 | AddBook, (interno) |
| `save()` | MovieRepository | 2 | AddMovie, (interno) |
| `updateUserBookRating()` | BookRepository | 2 | AddBook, UpdateBookRating |
| `updateUserMovieRating()` | MovieRepository | 2 | AddMovie, UpdateMovieRating |

---

## 5. Estrategia de Migración

### 🔄 Orden de refactorización recomendado

```
FASE 1: Users (415L, 12 métodos, 8 Use Cases)
    ├── Menor complejidad
    ├── Menos responsabilidades mezcladas
    └── Sirve de plantilla para Movies y Books

FASE 2: Movies (831L, 26 métodos, 7 Use Cases)
    ├── Complejidad media
    ├── No tiene subsistemas complejos (sin Sessions/Progress)
    └── Aplica aprendizajes de Users

FASE 3: Books (2,435L, 52 métodos, 10 Use Cases)
    ├── Mayor complejidad
    ├── Incluye 2 subsistemas completos (Sessions + Progress)
    └── Requiere más tiempo y tests
```

### 📋 Checklist de migración por repositorio

#### **Para cada repositorio**:
- [ ] Crear interfaces específicas para cada nuevo repositorio
- [ ] Implementar nuevos repositorios especializados
- [ ] Crear Mappers/Hydrators
- [ ] Implementar Traits compartidos (Logging, Status Management)
- [ ] Migrar tests existentes
- [ ] Crear nuevos tests unitarios e integración
- [ ] Actualizar Use Cases para usar nuevos repositorios
- [ ] Registrar en Dependency Injection
- [ ] Activar feature flag
- [ ] Validar que no hay regresiones
- [ ] Deprecar/eliminar repositorio antiguo

### 🚨 Riesgos identificados

#### **Alto Riesgo**
1. **ReadingSessions y Progress** (BookRepository)
   - 29 métodos interdependientes
   - Lógica de negocio compleja
   - Múltiples transacciones SQL
   - **Mitigación**: Tests exhaustivos + migración gradual

2. **EditUserBookUseCase y EditUserMovieUseCase**
   - Usan 6+ métodos cada uno
   - Transacciones complejas
   - **Mitigación**: Crear DTOs + tests de integración

3. **GetLibraryItemsUseCase**
   - Depende de UserRepository para 3 métodos diferentes
   - **Mitigación**: Crear service layer intermedio

#### **Medio Riesgo**
4. **Status Management**
   - Duplicado en 3 repositorios
   - **Mitigación**: Trait compartido + validación cuidadosa

5. **Tags y Notes**
   - Similar en Books y Movies
   - **Mitigación**: Considerar abstracción genérica

#### **Bajo Riesgo**
6. **CRUD básico**
   - Métodos simples (findById, save, delete)
   - **Mitigación**: Tests unitarios básicos

### 🎯 Dependencias entre repositorios

```
UserRepository
    ↓
    ├── hasUserBook() → BookRepository.addBookToUser()
    └── hasUserMovie() → MovieRepository.addMovieToUser()

BookRepository ⟷ UserRepository
    ├── findBooksByUser() requiere userId válido
    └── addBookToUser() requiere userId válido

MovieRepository ⟷ UserRepository
    ├── findMoviesByUser() requiere userId válido
    └── addMovieToUser() requiere userId válido
```

**Nota**: No hay dependencias directas entre BookRepository y MovieRepository

### 📝 Notas para implementación

1. **Feature Flags** deben permitir:
   - Activar/desactivar por repositorio individualmente
   - Rollback inmediato en caso de problemas
   - Modo híbrido (algunos repos nuevos, otros viejos)

2. **Tests de Integración** deben cubrir:
   - Flujos completos end-to-end
   - Casos edge (transacciones, errores, concurrencia)
   - Comparación de resultados (repo viejo vs nuevo)

3. **Monitoreo** durante migración:
   - Logs detallados de errores
   - Métricas de performance (tiempo de respuesta)
   - Alertas de regresiones

4. **Documentación** actualizar:
   - Wiki de arquitectura
   - Diagramas de dependencias
   - Guías de desarrollo

---

## 📚 Referencias

- **ROADMAP_TO_OPTIMAL_ARCHITECTURE.md** - Plan general de refactorización
- **Repositorio actual**: `/backend/src/Infrastructure/Persistence/`
- **Use Cases**: `/backend/src/Domain/UseCases/`
- **Tests** (cuando existan): `/backend/tests/`

---

**Última actualización**: 18 de diciembre de 2025  
**Estado**: ✅ Análisis completo - Listo para FASE 1
