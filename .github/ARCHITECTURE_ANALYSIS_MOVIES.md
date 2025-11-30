# 📋 ANÁLISIS DE ARQUITECTURA HEXAGONAL - LibraryVue (Movies Module)

> **Fecha de análisis:** 30 de noviembre de 2025  
> **Módulo analizado:** Movies (Películas)  
> **Archivo crítico:** `MySqlMovieRepository.php` - 38 KB, 831 líneas  
> **Análisis previo:** [Books Module](./ARCHITECTURE_ANALYSIS_BOOKS.md)

## 🎯 Resumen Ejecutivo

El módulo de Movies **replica los mismos problemas arquitectónicos** identificados en el módulo de Books, pero en menor escala. Aunque el archivo es significativamente más pequeño (831 líneas vs 2,435), **sufre de las mismas violaciones de arquitectura hexagonal**.

**Principales hallazgos:**
- ❌ Mismos problemas que Books pero a menor escala
- ❌ Código duplicado con BookRepository (logging, validaciones)
- ❌ Mezcla de responsabilidades (Movie + UserMovie + Tags + Notes)
- ⚠️ **Ventaja:** No tiene sesiones de lectura (más simple que Books)
- ✅ Estructura de Use Cases bien definida
- ✅ Menor complejidad que Books (buena oportunidad para refactorizar primero)

---

## 📊 COMPARATIVA: Movies vs Books

| Aspecto | Books Module | Movies Module | Observación |
|---------|-------------|---------------|-------------|
| **Tamaño del repositorio** | 2,435 líneas (116 KB) | 831 líneas (38 KB) | 📉 Movies es 66% más pequeño |
| **Métodos públicos** | 58+ | 24+ | 📉 Movies tiene menos métodos |
| **Responsabilidades** | 8+ (incluye ReadingSessions) | 5+ | 📉 Más simple, sin sesiones |
| **Interfaz sobrecargada** | 40+ métodos | 24+ métodos | 📉 Menos sobrecarga |
| **Logging duplicado** | ✅ Presente | ✅ Presente | ⚠️ Mismo problema |
| **Value Objects** | ❌ Ausentes | ❌ Ausentes | ⚠️ Mismo problema |
| **Mappers** | ❌ Ausentes | ❌ Ausentes | ⚠️ Mismo problema |
| **Lógica de negocio en repo** | ✅ Presente | ✅ Presente | ⚠️ Mismo problema |

**Conclusión:** Movies es una **versión más simple de Books** con los mismos problemas arquitectónicos. Es un **buen candidato para refactorizar primero** y usar como plantilla para refactorizar Books.

---

## 🔴 PROBLEMAS CRÍTICOS

### 1. **MySqlMovieRepository: Violación del Principio de Responsabilidad Única**

#### **Tamaño del problema:**
- **831 líneas** en un solo archivo
- **24+ métodos públicos**
- **38 KB** de código

#### **Responsabilidades mezcladas:**

| ❌ Lo que NO debería estar | ✅ Dónde debería estar |
|---------------------------|----------------------|
| **Logging** (1 método: `logError`) | Trait `LoggableTrait` o `AbstractMySqlRepository` |
| **Validación de estados** (`getStatusId`, `fetchMovieStatusNames`) | `MovieStatusService` o Value Object `MovieStatus` |
| **Gestión de tags** (4 métodos) | `MovieTagRepository` |
| **Gestión de notas** (1 método) | `MovieNoteRepository` |
| **Relación User-Movie** (6+ métodos) | `UserMovieRepository` |
| **Hidratación de entidades** | `MovieDataMapper` |

**Métodos que deberían extraerse por categoría:**

```php
// TAGS (4 métodos) → MovieTagRepository
getMovieTags(int $userId, string $movieIsbn): array
getUserMovieTags(int $userId): array
getAllowedTags(int $userId, string $isbn = null): array
addUserMovieTag(int $userId, string $name, string $color): int
assignUserMovieTag(int $userId, string $movieIsbn, int $tagId): void
removeAllUserMovieTags(int $userId, string $movieIsbn): void

// NOTAS (1 método) → MovieNoteRepository
getMovieNotesByPage(int $userId, string $movieIsbn): array
addUserMovieNote(int $userId, string $movieIsbn, string $noteText, ...): int

// RELACIÓN USER-MOVIE (8 métodos) → UserMovieRepository
addMovieToUser(int $userId, string $movieId, array $statuses): void
removeMovieFromUser(int $userId, string $movieId): bool
findMoviesByUser(int $userId, array $filters): array
updateUserMovieStatuses(int $userId, string $movieId, array $statuses): void
updateUserMovieRating(int $userId, string $movieId, ?float $rating): void
getUserMovieStatuses(int $userId, string $movieId): array
editUserMovie(int $userId, string $movieIsbn, ...): void

// VALIDACIÓN DE ESTADOS (métodos privados) → MovieStatusService o VO
getStatusId(string $statusName): ?int
fetchMovieStatusNames(string $isbn): array
```

---

### 2. **Código Duplicado con BookRepository**

#### **Logging Duplicado**
**Ubicación:** `MySqlMovieRepository.php` líneas 94-110

```php
private function logError(string $message, \Exception $e, array $context = []): void
{
    if ($this->logger) {
        $this->logger->error($message, [
            'exception' => [
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ],
            'context' => $context
        ]);
    }
}
```

**❌ Problema:** Este código está **idéntico** en:
- `MySqlBookRepository`
- `MySqlUserRepository`
- `MySqlMovieRepository`

**✅ Solución:** Ver [Books Analysis - Problema #7](./ARCHITECTURE_ANALYSIS_BOOKS.md#7-logging-duplicado-en-todos-los-repositorios)

---

#### **Métodos de Status Duplicados**

Los métodos `getStatusId()` y `fetchMovieStatusNames()` son **prácticamente idénticos** a los de `BookRepository`:

```php
// MySqlMovieRepository líneas 187-206
private function getStatusId(string $statusName): ?int
{
    $stmt = $this->db->prepare("SELECT id FROM movie_statuses WHERE name = :name");
    $stmt->bindParam(':name', $statusName);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['id'] : null;
}

private function fetchMovieStatusNames(string $isbn): array
{
    $sql = "SELECT s.name FROM movie_statuses s " .
           "JOIN movie_has_statuses mhs ON s.id = mhs.status_id " .
           "WHERE mhs.movie_isbn = :isbn";
    $stmt = $this->db->prepare($sql);
    $stmt->bindParam(':isbn', $isbn);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}
```

**❌ Problema:** Lógica duplicada que solo cambia el nombre de las tablas.

**✅ Solución:** Crear trait genérico `StatusManagementTrait` o servicio `StatusResolver`:

```php
Infrastructure/Persistence/Concerns/StatusManagementTrait.php

trait StatusManagementTrait {
    abstract protected function getStatusTableName(): string;
    abstract protected function getEntityStatusTableName(): string;
    
    protected function getStatusId(string $statusName): ?int {
        $table = $this->getStatusTableName();
        $stmt = $this->db->prepare("SELECT id FROM {$table} WHERE name = :name");
        $stmt->execute(['name' => $statusName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? (int)$result['id'] : null;
    }
    
    protected function fetchStatusNames(string $entityId): array {
        $statusTable = $this->getStatusTableName();
        $entityStatusTable = $this->getEntityStatusTableName();
        $entityIdColumn = $this->getEntityIdColumnName();
        
        $sql = "SELECT s.name FROM {$statusTable} s
                JOIN {$entityStatusTable} es ON s.id = es.status_id
                WHERE es.{$entityIdColumn} = :entityId";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['entityId' => $entityId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
}

// Uso en MySqlMovieRepository:
class MySqlMovieRepository implements MovieRepositoryInterface {
    use StatusManagementTrait;
    
    protected function getStatusTableName(): string {
        return 'movie_statuses';
    }
    
    protected function getEntityStatusTableName(): string {
        return 'movie_has_statuses';
    }
    
    protected function getEntityIdColumnName(): string {
        return 'movie_isbn';
    }
}
```

---

### 3. **Falta de Separación de Conceptos de Dominio**

#### **Entidades mezcladas en un solo repositorio:**
```
MySqlMovieRepository maneja:
├── Movies (entidad principal) ✅
├── UserMovies (relación many-to-many) ⚠️
├── MovieTags (entidad independiente) ⚠️
└── MovieNotes (entidad independiente) ⚠️
```

**En arquitectura hexagonal correcta:**
```
Infrastructure/Persistence/
├── Movie/
│   ├── MySqlMovieRepository.php         (solo películas: CRUD básico)
│   ├── MySqlUserMovieRepository.php     (relación many-to-many)
│   └── Mappers/
│       └── MovieDataMapper.php
├── MovieTag/
│   └── MySqlMovieTagRepository.php
└── MovieNote/
    └── MySqlMovieNoteRepository.php
```

---

### 4. **Interfaz Sobrecargada**

`MovieRepositoryInterface` tiene **24 métodos** cuando debería tener **~6-8**:

```php
// ✅ Métodos CORE que SÍ deberían estar en MovieRepositoryInterface:
findById(string $id): ?Movie
findAll(array $filters): array
save(Movie $movie): void
deleteByIsbn(string $isbn): bool
deleteById(int $id): bool  // ⚠️ Considerar eliminar - usar solo ISBN
fetchAllowedStatuses(): array

// ❌ Métodos que NO deberían estar (mover a UserMovieRepositoryInterface):
addMovieToUser(int $userId, string $movieId, array $statuses)
removeMovieFromUser(int $userId, string $movieId)
findMoviesByUser(int $userId, array $filters)
updateUserMovieStatuses(int $userId, string $movieId, array $statuses)
updateUserMovieRating(int $userId, string $movieId, ?float $rating)
getUserMovieStatuses(int $userId, string $movieId)
editUserMovie(int $userId, string $movieIsbn, ...)

// ❌ Métodos que NO deberían estar (mover a MovieTagRepositoryInterface):
getMovieTags(int $userId, string $movieIsbn)
getUserMovieTags(int $userId)
getAllowedTags(int $userId, string $isbn)
addUserMovieTag(int $userId, string $name, string $color)
assignUserMovieTag(int $userId, string $movieIsbn, int $tagId)
removeAllUserMovieTags(int $userId, string $movieIsbn)

// ❌ Métodos que NO deberían estar (mover a MovieNoteRepositoryInterface):
getMovieNotesByPage(int $userId, string $movieIsbn)
addUserMovieNote(int $userId, string $movieIsbn, string $noteText, ...)
```

---

### 5. **Inconsistencia en Identificadores: `id` vs `isbn`**

**Problema crítico:** El repositorio usa **ambos** `id` e `isbn` de forma inconsistente:

```php
// Métodos que usan ISBN:
public function deleteByIsbn(string $isbn): bool
public function findById(string $isbn): ?array  // ⚠️ Nombre engañoso

// Métodos que usan ID:
public function deleteById(int $id): bool

// Métodos que usan "movieId" (puede ser cualquiera):
public function addMovieToUser(int $userId, string $movieId, ...)
public function updateUserMovieStatuses(int $userId, string $movieId, ...)

// Métodos que usan "movieIsbn":
public function getMovieTags(int $userId, string $movieIsbn): array
```

**❌ Problema:** 
1. Confusión sobre qué identificador usar
2. `findById()` en realidad busca por ISBN, no por ID
3. `movieId` puede ser IMDB ID, ISBN o ID interno

**✅ Solución:** Usar **Value Object `MovieIdentifier`**:

```php
Domain/Model/ValueObjects/MovieIdentifier.php

final class MovieIdentifier {
    private string $value;
    private IdentifierType $type;
    
    private function __construct(string $value, IdentifierType $type) {
        $this->value = $value;
        $this->type = $type;
    }
    
    public static function fromImdbId(string $imdbId): self {
        if (!preg_match('/^tt\d{7,8}$/', $imdbId)) {
            throw new InvalidArgumentException("Invalid IMDB ID: {$imdbId}");
        }
        return new self($imdbId, IdentifierType::IMDB);
    }
    
    public static function fromIsbn(string $isbn): self {
        // Validación ISBN (reutilizar de Book)
        return new self($isbn, IdentifierType::ISBN);
    }
    
    public static function fromInternalId(int $id): self {
        return new self((string)$id, IdentifierType::INTERNAL);
    }
    
    public function getValue(): string {
        return $this->value;
    }
    
    public function getType(): IdentifierType {
        return $this->type;
    }
    
    public function isImdb(): bool {
        return $this->type === IdentifierType::IMDB;
    }
    
    public function toString(): string {
        return $this->value;
    }
}

enum IdentifierType {
    case IMDB;
    case ISBN;
    case INTERNAL;
}

// Uso en el repositorio:
class MySqlMovieRepository {
    public function findById(MovieIdentifier $id): ?Movie {
        if ($id->isImdb()) {
            return $this->findByImdbId($id->getValue());
        }
        return $this->findByIsbn($id->getValue());
    }
}
```

---

### 6. **Lógica de Negocio en el Repositorio**

#### **Ejemplo 1: Validación de estados en `save()`**
**Ubicación:** `MySqlMovieRepository.php` líneas 259-313

```php
public function save(Movie $movie): void
{
    $this->db->beginTransaction();
    try {
        // ... INSERT/UPDATE ...
        
        $userStatusNames = $movie->getUserStatuses();
        
        // ⚠️ VALIDACIÓN DE NEGOCIO en el repositorio
        if (empty($userStatusNames)) {
            throw new RuntimeException("Movie must have at least one user status.");
        }
        
        // ⚠️ MÁS VALIDACIÓN DE NEGOCIO
        foreach ($userStatusNames as $statusName) {
            $statusId = $this->getStatusId($statusName);
            if ($statusId === null) {
                throw new RuntimeException("Invalid status name '{$statusName}'...");
            }
            // INSERT status...
        }
        
        $this->db->commit();
    } catch (PDOException $e) {
        $this->db->rollBack();
        throw new RuntimeException("Could not save movie...");
    }
}
```

**❌ Problema:** Idéntico al problema de Books - validación de reglas de negocio en persistencia.

**✅ Solución:** Mover validaciones a:
1. Entidad `Movie` (constructor)
2. Use Case `AddMovieUseCase`
3. Value Object `MovieStatusCollection`

---

#### **Ejemplo 2: Actualización de estados con lógica compleja**
**Ubicación:** `MySqlMovieRepository.php` líneas 599-653

```php
public function updateUserMovieStatuses(int $userId, string $movieId, array $statuses): void
{
    try {
        $this->db->beginTransaction();
        
        // ⚠️ LÓGICA DE NEGOCIO: Validar estados permitidos
        $allowedStatuses = Movie::ALLOWED_STATUSES;
        foreach ($statuses as $status) {
            if (!in_array($status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException("Invalid status: {$status}");
            }
        }
        
        // ⚠️ MÁS LÓGICA: Limpiar estados incompatibles
        // (Similar a Books pero más simple)
        
        // DELETE + INSERT...
        
        $this->db->commit();
    } catch (\PDOException $e) {
        $this->db->rollBack();
        throw new \RuntimeException("Could not update movie statuses...");
    }
}
```

**✅ Solución:** Usar `MovieStatusCollection` Value Object (igual que Books).

---

## 🟡 PROBLEMAS MEDIOS

### 7. **Falta de Value Objects**

El código usa tipos primitivos donde debería usar Value Objects:

| Primitivo actual | Value Object sugerido | Reutilizable de Books |
|-----------------|----------------------|----------------------|
| `string $id` / `string $isbn` | `MovieIdentifier` | ❌ Nuevo (específico) |
| `float $rating` | `Rating` | ✅ Sí (compartido con Books) |
| `array $statuses` | `MovieStatusCollection` | ⚠️ Similar a `BookStatusCollection` |
| `string $statusName` | `MovieStatus` | ⚠️ Similar a `BookStatus` |
| `array $genres` | `GenreCollection` | ✅ Sí (compartido con Books) |

**Oportunidad de reutilización:**
```php
Domain/Model/ValueObjects/
├── Shared/  (compartidos entre Books y Movies)
│   ├── Rating.php
│   ├── Genre.php
│   └── GenreCollection.php
├── Book/
│   ├── ISBN.php
│   ├── BookStatus.php
│   └── BookStatusCollection.php
└── Movie/
    ├── MovieIdentifier.php
    ├── MovieStatus.php
    └── MovieStatusCollection.php
```

---

### 8. **Entity Movie: Menos Robusta que Book**

**Comparación de validaciones:**

```php
// Book.php - Validaciones robustas:
if (empty($isbn)) {
    throw new \InvalidArgumentException('ISBN cannot be empty.');
}
if ($rating !== null && ($rating < 0.5 || $rating > 5)) {
    throw new \InvalidArgumentException('Rating must be between 0.5 and 5...');
}
if ($rating !== null && floor($rating * 2) != $rating * 2) {
    throw new \InvalidArgumentException('Rating must be a multiple of 0.5.');
}

// Movie.php - SIN validaciones:
public function __construct(
    string $id,
    string $title,
    ?float $rating,
    // ...
) {
    $this->id = $id;        // ⚠️ No valida si está vacío
    $this->rating = $rating; // ⚠️ No valida rango ni múltiplos
    // ...
}
```

**❌ Problema:** La entidad `Movie` acepta estados inválidos.

**✅ Solución:** Agregar validaciones (o mejor, usar Value Objects):

```php
class Movie {
    private MovieIdentifier $id;
    private Rating $rating;
    private Rating $userRating;
    private MovieStatusCollection $statuses;
    
    public function __construct(
        MovieIdentifier $id,
        string $title,
        Rating $rating,
        MovieStatusCollection $statuses,
        // ...
    ) {
        if (empty($title)) {
            throw new InvalidArgumentException('Title cannot be empty');
        }
        
        $this->id = $id;
        $this->title = $title;
        $this->rating = $rating;
        $this->statuses = $statuses->cleanIncompatible();
    }
}
```

---

### 9. **Métodos de Eliminación Redundantes**

```php
public function deleteByIsbn(string $isbn): bool
public function deleteById(int $id): bool
public function deleteByName(string $title): bool
```

**❌ Problema:**
1. Tres formas de eliminar la misma entidad
2. `deleteByName()` es peligroso (puede borrar múltiples películas con el mismo título)
3. Confusión sobre cuál usar

**✅ Solución:** Unificar con `MovieIdentifier`:

```php
interface MovieRepositoryInterface {
    public function delete(MovieIdentifier $id): bool;
}

// Uso:
$repo->delete(MovieIdentifier::fromImdbId('tt0111161'));
$repo->delete(MovieIdentifier::fromIsbn('978-0-123456-78-9'));
```

---

### 10. **Hidratación Manual Repetitiva**

Similar a Books, la conversión de arrays DB a entidades está distribuida:

```php
// findAll() - Hidratación manual
$data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
$data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : time();
$data['genres'] = isset($data['genres']) ? json_decode($data['genres'], true) : null;
$userStatuses = $this->fetchMovieStatusNames($data['isbn']);
$data['userStatuses'] = is_array($userStatuses) ? $userStatuses : [];
```

**✅ Solución:** `MovieDataMapper` (ver propuesta de refactorización).

---

## 🟢 PUNTOS FUERTES (A MANTENER)

✅ **Más simple que Books** - Sin sesiones de lectura (menos complejidad)  
✅ **Tamaño manejable** - 831 líneas (vs 2,435 de Books)  
✅ **Use Cases bien estructurados** - Patrón consistente con Books  
✅ **Separación de capas** - Domain, Infrastructure, Controllers  
✅ **Inyección de dependencias** - PHP-DI configurado  
✅ **Transacciones bien manejadas** - Begin/Commit/Rollback  

**Ventaja estratégica:** Movies es **ideal para refactorizar primero** y usar como plantilla para Books.

---

## 📐 PROPUESTA DE REFACTORIZACIÓN

### **Estrategia: Movies como Piloto**

Dado que Movies es más simple que Books, se propone:

1. **Refactorizar Movies primero** (2 semanas)
2. **Validar el patrón** con tests y métricas
3. **Aplicar lecciones aprendidas a Books** (4 semanas)
4. **Reutilizar componentes comunes** (Value Objects, Traits)

---

### **Fase 1: Dividir MySqlMovieRepository - Semana 1**

**Objetivo:** Reducir de 831 líneas a ~150-200 líneas por repositorio

```
Infrastructure/Persistence/
├── Movie/
│   ├── MySqlMovieRepository.php              (~150 líneas)
│   │   ├── findById(MovieIdentifier $id): ?Movie
│   │   ├── findAll(array $filters): array
│   │   ├── save(Movie $movie): void
│   │   └── delete(MovieIdentifier $id): bool
│   │
│   ├── MySqlUserMovieRepository.php          (~120 líneas)
│   │   ├── add(int $userId, MovieIdentifier $movieId, array $statuses): void
│   │   ├── remove(int $userId, MovieIdentifier $movieId): bool
│   │   ├── findByUser(int $userId, array $filters): array
│   │   ├── updateStatuses(int $userId, MovieIdentifier $movieId, array $statuses): void
│   │   ├── updateRating(int $userId, MovieIdentifier $movieId, ?float $rating): void
│   │   └── getStatuses(int $userId, MovieIdentifier $movieId): array
│   │
│   └── Mappers/
│       └── MovieDataMapper.php               (~80 líneas)
│
├── MovieTag/
│   └── MySqlMovieTagRepository.php           (~100 líneas)
│       ├── create(int $userId, string $name, string $color): int
│       ├── assign(int $userId, MovieIdentifier $movieId, int $tagId): void
│       ├── removeAll(int $userId, MovieIdentifier $movieId): void
│       ├── findByMovie(int $userId, MovieIdentifier $movieId): array
│       └── findByUser(int $userId): array
│
└── MovieNote/
    └── MySqlMovieNoteRepository.php          (~80 líneas)
        ├── add(MovieNote $note): int
        └── findByMovie(int $userId, MovieIdentifier $movieId): array
```

**Métricas de mejora:**
- MySqlMovieRepository: 831 → ~150 líneas ✅ (reducción del 82%)
- Responsabilidades por clase: 5 → 1 ✅
- Métodos públicos por interface: 24 → ~5 ✅

---

### **Fase 2: Crear Value Objects - Semana 1-2**

**Prioridad 1: Value Objects específicos de Movie**
```php
Domain/Model/ValueObjects/Movie/
├── MovieIdentifier.php
│   ├── fromImdbId(string): self
│   ├── fromIsbn(string): self
│   ├── fromInternalId(int): self
│   ├── getValue(): string
│   └── getType(): IdentifierType
│
├── MovieStatus.php (enum)
│   ├── TO_WATCH
│   ├── WATCHING
│   ├── WATCHED
│   ├── PAUSED
│   └── ABANDONED
│
└── MovieStatusCollection.php
    ├── add(MovieStatus): self
    ├── remove(MovieStatus): self
    ├── has(MovieStatus): bool
    └── cleanIncompatible(): self
```

**Prioridad 2: Reutilizar Value Objects de Books**
```php
Domain/Model/ValueObjects/Shared/
├── Rating.php              (ya existe en análisis de Books)
├── Genre.php
└── GenreCollection.php
```

---

### **Fase 3: Extraer Traits Compartidos - Semana 2**

```php
Infrastructure/Persistence/Concerns/
├── LoggableTrait.php           (compartido con Books, Users)
│   ├── logError(...)
│   └── logInfo(...)
│
└── StatusManagementTrait.php   (compartido con Books)
    ├── getStatusId(string): ?int
    └── fetchStatusNames(string): array
```

**Uso en MySqlMovieRepository:**
```php
class MySqlMovieRepository implements MovieRepositoryInterface {
    use LoggableTrait;
    use StatusManagementTrait;
    
    protected function getStatusTableName(): string {
        return 'movie_statuses';
    }
    
    protected function getEntityStatusTableName(): string {
        return 'movie_has_statuses';
    }
    
    protected function getEntityIdColumnName(): string {
        return 'movie_isbn';
    }
}
```

---

### **Fase 4: Crear MovieDataMapper - Semana 2**

```php
Infrastructure/Persistence/Mappers/MovieDataMapper.php

class MovieDataMapper {
    private MovieStatusRepository $statusRepository;
    
    public function toDomain(array $dbRow, ?int $userId = null): Movie {
        return Movie::fromArray([
            'id' => $dbRow['id'] ?? $dbRow['isbn'],
            'title' => $dbRow['title'],
            'originalTitle' => $dbRow['originalTitle'] ?? null,
            'director' => $dbRow['director'] ?? null,
            'coverUrl' => $dbRow['coverUrl'] ?? null,
            'rating' => isset($dbRow['rating']) ? (float)$dbRow['rating'] : null,
            'userRating' => isset($dbRow['user_rating']) 
                ? (float)$dbRow['user_rating'] 
                : null,
            'description' => $dbRow['description'] ?? null,
            'userStatuses' => $this->extractUserStatuses($dbRow, $userId),
            'addedTimestamp' => isset($dbRow['addedTimestamp']) 
                ? (int)$dbRow['addedTimestamp'] 
                : time(),
            'allowedStatuses' => $this->statusRepository->getAllowed(),
            'tags' => $dbRow['tags'] ?? [],
            'allowedTags' => $dbRow['allowedTags'] ?? [],
            'genres' => isset($dbRow['genres']) 
                ? json_decode($dbRow['genres'], true) 
                : null,
        ]);
    }
    
    public function toPersistence(Movie $movie): array {
        return [
            'id' => $movie->getId(),
            'title' => $movie->getTitle(),
            'originalTitle' => $movie->getOriginalTitle(),
            'director' => $movie->getDirector(),
            'coverUrl' => $movie->getCoverUrl(),
            'rating' => $movie->getRating(),
            'description' => $movie->getDescription(),
            'addedTimestamp' => $movie->getAddedTimestamp(),
            'genres' => $movie->getGenres() ? json_encode($movie->getGenres()) : null,
        ];
    }
    
    private function extractUserStatuses(array $dbRow, ?int $userId): array {
        if ($userId && isset($dbRow['user_statuses'])) {
            return json_decode($dbRow['user_statuses'], true) ?? [];
        }
        return [];
    }
}
```

---

### **Fase 5: Actualizar Interfaces - Semana 2**

**MovieRepositoryInterface (reducida):**
```php
interface MovieRepositoryInterface {
    public function findById(MovieIdentifier $id): ?Movie;
    public function findAll(array $filters = []): array;
    public function save(Movie $movie): void;
    public function delete(MovieIdentifier $id): bool;
    public function fetchAllowedStatuses(): array;
}
```

**Nuevas interfaces:**
```php
interface UserMovieRepositoryInterface {
    public function add(int $userId, MovieIdentifier $movieId, array $statuses): void;
    public function remove(int $userId, MovieIdentifier $movieId): bool;
    public function findByUser(int $userId, array $filters): array;
    public function updateStatuses(int $userId, MovieIdentifier $movieId, array $statuses): void;
    public function updateRating(int $userId, MovieIdentifier $movieId, ?float $rating): void;
    public function getStatuses(int $userId, MovieIdentifier $movieId): array;
}

interface MovieTagRepositoryInterface {
    public function create(int $userId, string $name, string $color): int;
    public function assign(int $userId, MovieIdentifier $movieId, int $tagId): void;
    public function removeAll(int $userId, MovieIdentifier $movieId): void;
    public function findByMovie(int $userId, MovieIdentifier $movieId): array;
    public function findByUser(int $userId): array;
}

interface MovieNoteRepositoryInterface {
    public function add(MovieNote $note): int;
    public function findByMovie(int $userId, MovieIdentifier $movieId): array;
}
```

---

## 📊 MÉTRICAS DE MEJORA ESPERADAS

| Métrica | Antes | Después (objetivo) | Mejora |
|---------|-------|-------------------|--------|
| Líneas en MovieRepository | 831 | <200 | 📉 76% |
| Tamaño del archivo | 38 KB | <10 KB | 📉 74% |
| Métodos públicos en MovieRepository | 24+ | ~5 | 📉 79% |
| Métodos en MovieRepositoryInterface | 24 | ~5 | 📉 79% |
| Responsabilidades por clase | 5+ | 1 | 📉 80% |
| Código duplicado con Books | Alto | Bajo (traits) | ✅ |
| Reusabilidad de componentes | 20% | 80% | 📈 300% |
| Testabilidad | 40% | 90% | 📈 125% |
| Tiempo de refactorización | - | 2 semanas | ⏱️ |

**Comparación con Books:**
- Movies es 66% más pequeño → **Refactorización 50% más rápida**
- Menos complejidad → **Menos riesgo de regresiones**
- Sirve como plantilla → **Acelera refactorización de Books**

---

## 🎯 PRIORIDADES DE REFACTORIZACIÓN

### **🔴 URGENTE (Semana 1):**
1. ✅ **Dividir `MySqlMovieRepository`**
   - Crear `MySqlUserMovieRepository`
   - Crear `MySqlMovieTagRepository`
   - Reducir `MySqlMovieRepository` a CRUD básico
   
2. ✅ **Extraer Traits compartidos**
   - `LoggableTrait`
   - `StatusManagementTrait`

### **🟡 IMPORTANTE (Semana 2):**
3. ✅ **Implementar Value Objects**
   - `MovieIdentifier` (crítico para resolver confusión id/isbn)
   - `Rating` (compartido con Books)
   - `MovieStatusCollection`
   
4. ✅ **Crear MovieDataMapper**
   - Eliminar hidratación manual

### **🟢 DESEABLE (Semana 3 - Post-refactorización):**
5. ✅ **Validar con Books**
   - Aplicar lecciones aprendidas
   - Reutilizar Value Objects y Traits
   
6. ✅ **Tests unitarios**
   - Cobertura > 80% en componentes nuevos

---

## 🔗 COMPONENTES REUTILIZABLES CON BOOKS

### **Alta reutilización:**
```php
// Value Objects compartidos:
Domain/Model/ValueObjects/Shared/
├── Rating.php              ✅ 100% reutilizable
├── Genre.php               ✅ 100% reutilizable
└── GenreCollection.php     ✅ 100% reutilizable

// Traits compartidos:
Infrastructure/Persistence/Concerns/
├── LoggableTrait.php           ✅ 100% reutilizable
└── StatusManagementTrait.php   ✅ 90% reutilizable (pequeños ajustes)
```

### **Reutilización parcial (adaptación):**
```php
// Patterns similares (adaptar para Movie):
Domain/Model/ValueObjects/Book/BookStatusCollection.php
→ Domain/Model/ValueObjects/Movie/MovieStatusCollection.php

// Mappers con estructura similar:
Infrastructure/Persistence/Mappers/BookDataMapper.php
→ Infrastructure/Persistence/Mappers/MovieDataMapper.php
```

---

## 📝 RECOMENDACIONES FINALES

### **1. Movies como Proyecto Piloto**
- ✅ Más simple que Books (sin sesiones de lectura)
- ✅ Permite validar estrategia de refactorización
- ✅ Menor riesgo (menos funcionalidad)
- ✅ Resultados visibles en 2 semanas

### **2. Aprovechar Similitudes con Books**
- ✅ Crear componentes genéricos desde el inicio
- ✅ Diseñar pensando en reutilización
- ✅ Documentar decisiones para aplicar en Books

### **3. Resolver Confusión de Identificadores**
- ✅ `MovieIdentifier` resuelve ambigüedad id/isbn/imdb
- ✅ Tipado fuerte previene errores
- ✅ API más clara y segura

### **4. Priorizar Tests**
- ✅ Escribir tests de integración ANTES
- ✅ Mantener cobertura durante refactorización
- ✅ Tests como red de seguridad

---

## 🏗️ ARQUITECTURA OBJETIVO

```
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE PRESENTACIÓN                      │
│  └── MovieController                                         │
└──────────────────────────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE APLICACIÓN                        │
│  └── UseCases/Movies/                                        │
│      ├── AddMovieUseCase                                     │
│      ├── UpdateMovieRatingUseCase                            │
│      └── UpdateMovieUserStatusesUseCase                      │
└──────────────────────────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE DOMINIO                           │
│  ├── Model/                                                  │
│  │   ├── Entities/Movie.php                                 │
│  │   └── ValueObjects/                                      │
│  │       ├── Movie/                                         │
│  │       │   ├── MovieIdentifier.php                        │
│  │       │   ├── MovieStatus.php                            │
│  │       │   └── MovieStatusCollection.php                  │
│  │       └── Shared/ (compartido con Books)                 │
│  │           ├── Rating.php                                 │
│  │           └── GenreCollection.php                        │
│  │                                                           │
│  └── Repository/ (interfaces)                               │
│      ├── MovieRepositoryInterface                           │
│      ├── UserMovieRepositoryInterface                       │
│      └── MovieTagRepositoryInterface                        │
└──────────────────────────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                 CAPA DE INFRAESTRUCTURA                      │
│  └── Persistence/                                            │
│      ├── Movie/                                              │
│      │   ├── MySqlMovieRepository.php (~150 líneas)          │
│      │   ├── MySqlUserMovieRepository.php (~120 líneas)      │
│      │   └── Mappers/MovieDataMapper.php                     │
│      ├── MovieTag/MySqlMovieTagRepository.php                │
│      ├── MovieNote/MySqlMovieNoteRepository.php              │
│      └── Concerns/ (compartido con Books)                    │
│          ├── LoggableTrait.php                               │
│          └── StatusManagementTrait.php                       │
└──────────────────────────────────────────────────────────────┘
```

---

## 📌 Conclusiones Clave

1. ✅ **Movies es más simple que Books** → Ideal como proyecto piloto
2. ✅ **Mismos problemas pero a menor escala** → Validación de soluciones
3. ✅ **Oportunidad de reutilización** → Value Objects y Traits compartidos
4. ✅ **Resolución de confusión de IDs** → `MovieIdentifier` crítico
5. 🎯 **Refactorización en 2 semanas** → Rápido retorno de inversión

**Siguiente paso:** Aplicar lecciones de Movies a Books (duplicar esfuerzo pero con menor riesgo).

---

## 🔗 Referencias

- [Books Module Analysis](./ARCHITECTURE_ANALYSIS_BOOKS.md) - Análisis base
- **Próximos análisis:**
  - [ ] Users Module
  - [ ] Use Cases
  - [ ] Domain Objects
  - [ ] Controllers
  - [ ] Frontend

---

*Fecha de última actualización: 30 de noviembre de 2025*
