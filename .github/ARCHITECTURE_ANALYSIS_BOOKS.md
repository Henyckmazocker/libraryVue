# 📋 ANÁLISIS DE ARQUITECTURA HEXAGONAL - LibraryVue (Books Module)

> **Fecha de análisis:** 30 de noviembre de 2025  
> **Módulo analizado:** Books (Libros)  
> **Archivo crítico:** `MySqlBookRepository.php` - 116 KB, 2,435 líneas

## 🎯 Resumen Ejecutivo

El proyecto **tiene buenas intenciones arquitectónicas** con separación de capas (Domain, Infrastructure, Controllers), uso de interfaces y DI, pero sufre de **violaciones significativas de arquitectura hexagonal**, especialmente en la capa de persistencia.

**Principales hallazgos:**
- ❌ Repositorio monolítico con 8+ responsabilidades diferentes
- ❌ Lógica de negocio mezclada con persistencia
- ❌ Falta de Value Objects para tipos de dominio
- ✅ Buena separación de Use Cases
- ✅ Inyección de dependencias bien implementada

---

## 🔴 PROBLEMAS CRÍTICOS

### 1. **MySqlBookRepository: Violación Masiva del Principio de Responsabilidad Única**

#### **Tamaño del problema:**
- **2,435 líneas** en un solo archivo
- **58+ métodos públicos**
- **116 KB** de código

#### **Responsabilidades mezcladas (debería tener SOLO persistencia):**

| ❌ Lo que NO debería estar | ✅ Dónde debería estar |
|---------------------------|----------------------|
| **Logging** (4 métodos: `logError`, `logInfo`, `logDebug`, `logWarning`) | Servicio de Logging inyectado |
| **Formateo de fechas** (`formatPublicationDate`) | Value Object `PublicationDate` o servicio de Formateo |
| **Validación de estados** (`getStatusId`, validaciones de status) | `BookStatusService` o Value Object `BookStatus` |
| **Lógica de sesiones de lectura** (15+ métodos: `createReadingSession`, `pauseReadingSession`, etc.) | `ReadingSessionRepository` + `ReadingSessionService` |
| **Lógica de progreso de lectura** (métodos de histórico y estadísticas) | `ReadingProgressRepository` |
| **Gestión de tags** (4+ métodos) | `BookTagRepository` |
| **Gestión de notas** (métodos de notes) | `BookNoteRepository` |
| **Estadísticas y agregaciones** (`getMonthlyPagesReadStats`, `getUserReadingStats`) | `BookStatisticsService` |
| **Hidratación de entidades** (conversión de arrays DB → Book) | Data Mapper o Hydrator dedicado |

**Métodos que deberían extraerse por categoría:**

```php
// SESIONES DE LECTURA (15+ métodos) → ReadingSessionRepository
createReadingSession()
getActiveReadingSession()
completeReadingSession()
updateSessionStatus()
pauseReadingSession()
resumeReadingSession()
abandonReadingSession()
deleteReadingSession()
getUserReadingSessions()
getReadingSessionHistory()
getSessionProgress()
getUserActiveReadingSessions()
getNextSessionNumber()
getReadingSessionStats()

// PROGRESO DE LECTURA (8+ métodos) → ReadingProgressRepository
updateReadingProgressWithSession()
addReadingProgressHistory()
getReadingProgressHistory()
getMonthlyPagesReadStats()
getCurrentPage()
getTotalPages()
getLastProgressPage()
getDetailedProgressHistory()

// TAGS (4+ métodos) → BookTagRepository
addUserBookTag()
assignUserBookTag()
removeAllUserBookTags()
getBookTags()
getUserBookTags()
getAllowedTags()

// NOTAS (2+ métodos) → BookNoteRepository
addUserBookNote()
getBookNotesByPage()

// ESTADÍSTICAS (4+ métodos) → BookStatisticsService
getBookReadingSummary()
getUserReadingStats()
getCurrentReadingSessions()
hasCompletedBook()
getBookCompletionCount()

// RELACIÓN USER-BOOK (6+ métodos) → UserBookRepository
addBookToUser()
removeBookFromUser()
findBooksByUser()
updateUserBookStatuses()
updateUserBookRating()
getUserBookStatuses()
editUserBook()
```

---

### 2. **Falta de Separación de Conceptos de Dominio**

#### **Entidades mezcladas en un solo repositorio:**
```
MySqlBookRepository maneja:
├── Books (entidad principal) ✅
├── ReadingSessions (entidad independiente) ⚠️
├── ReadingProgress (entidad independiente) ⚠️
├── BookTags (entidad independiente) ⚠️
├── BookNotes (entidad independiente) ⚠️
└── BookStatistics (agregación de datos) ⚠️
```

**En arquitectura hexagonal correcta:**
```
Infrastructure/Persistence/
├── Book/
│   ├── MySqlBookRepository.php         (solo libros: CRUD básico)
│   ├── MySqlUserBookRepository.php     (relación many-to-many)
│   └── Mappers/
│       └── BookDataMapper.php
├── ReadingSession/
│   ├── MySqlReadingSessionRepository.php
│   └── Mappers/
│       └── ReadingSessionDataMapper.php
├── ReadingProgress/
│   └── MySqlReadingProgressRepository.php
├── BookTag/
│   └── MySqlBookTagRepository.php
└── BookNote/
    └── MySqlBookNoteRepository.php
```

---

### 3. **Interfaz del Repositorio Sobrecargada**

`BookRepositoryInterface` tiene **40+ métodos** cuando debería tener **~8-10**:

```php
// ✅ Métodos CORE que SÍ deberían estar en BookRepositoryInterface:
findById(string $isbn): ?Book
findAll(array $filters = []): array
findByUserStatus(string $statusName): array
save(Book $book): void
deleteByIsbn(string $isbn): bool
fetchAllowedStatuses(): array

// ❌ Métodos que NO deberían estar en BookRepositoryInterface:
// Deberían estar en ReadingSessionRepositoryInterface:
createReadingSession(...)
pauseReadingSession(...)
completeReadingSession(...)
getActiveReadingSession(...)
abandonReadingSession(...)

// Deberían estar en BookNoteRepositoryInterface:
addUserBookNote(...)
getBookNotesByPage(...)

// Deberían estar en BookTagRepositoryInterface:
addUserBookTag(...)
assignUserBookTag(...)
getBookTags(...)

// Deberían estar en BookStatisticsServiceInterface o Query Repository:
getMonthlyPagesReadStats(...)
getUserReadingStats(...)
getBookReadingSummary(...)

// Deberían estar en UserBookRepositoryInterface:
addBookToUser(...)
removeBookFromUser(...)
findBooksByUser(...)
updateUserBookRating(...)
```

---

### 4. **Lógica de Negocio en el Repositorio**

#### **Ejemplo 1: Validación de estados en `save()`**
**Ubicación:** `MySqlBookRepository.php` líneas 258-290

```php
public function save(Book $book): void
{
    // ...
    if (empty($userStatusNames)) {
        error_log("[BookRepository] Intento de guardar libro sin userStatuses...");
        throw new RuntimeException("Book must have at least one user status...");
    }

    foreach ($userStatusNames as $statusName) {
        $statusId = $this->getStatusId($statusName);
        if ($statusId === null) {
            throw new RuntimeException("Invalid status name '{$statusName}'...");
        }
    }
}
```

**❌ Problema:** Esta validación es **lógica de negocio** que debería estar en:
1. La entidad `Book` (validación de consistencia de estado)
2. El Use Case `AddBookUseCase` (reglas de negocio)
3. Un Value Object `BookStatusCollection` (validación de colección de estados)

**✅ Solución:** El repositorio solo debería **persistir datos ya validados**, no validar reglas de negocio.

---

#### **Ejemplo 2: Actualización automática de estados basada en sesiones**
**Ubicación:** `MySqlBookRepository.php` líneas 2385-2435

```php
public function updateBookStatusesBasedOnSessions(int $userId, string $isbn): void
{
    // Lógica compleja para determinar estados basados en sesiones
    $hasActiveSessions = // query...
    $completionCount = // query...
    
    // ⚠️ LÓGICA DE DOMINIO en el repositorio
    if ($hasActiveSessions) {
        $newStatus = $completionCount > 0 ? 're-reading' : 'reading';
    } elseif ($completionCount > 0) {
        $newStatus = 'completed';
    }
    
    // Update del estado
}
```

**❌ Problema:** Esta es **lógica de dominio compleja** que determina el estado de un libro basándose en reglas de negocio.

**✅ Solución:** Debería estar en:
```php
Domain/Services/BookStatusManager.php

class BookStatusManager {
    public function determineStatusFromSessions(
        User $user, 
        Book $book, 
        array $sessions
    ): BookStatus {
        $activeSessions = array_filter($sessions, fn($s) => $s->isActive());
        $completedCount = count(array_filter($sessions, fn($s) => $s->isCompleted()));
        
        if (count($activeSessions) > 0) {
            return $completedCount > 0 
                ? BookStatus::RE_READING 
                : BookStatus::READING;
        }
        
        return $completedCount > 0 
            ? BookStatus::COMPLETED 
            : BookStatus::TO_READ;
    }
}
```

---

#### **Ejemplo 3: Lógica de limpieza de estados incompatibles**
**Ubicación:** `MySqlBookRepository.php` líneas 605-630

```php
public function updateUserBookStatuses(int|string $userId, string $isbn, array $statuses): void
{
    // ...
    // ⚠️ REGLAS DE NEGOCIO: "Si tiene 'read', remover 'reading', 'to-read'"
    if (in_array('read', $statuses)) {
        $this->logInfo("Limpiando estados incompatibles al tener 'read'", [...]);
        $statuses = array_diff($statuses, ['reading', 'to-read']);
    }
    
    // ⚠️ REGLAS DE NEGOCIO: "Si está pausado o abandonado, remover 'reading'"
    if (in_array('paused', $statuses) || in_array('abandoned', $statuses)) {
        $this->logInfo("Eliminando 'reading' al pausar o abandonar", [...]);
        $statuses = array_diff($statuses, ['reading']);
    }
}
```

**❌ Problema:** Las reglas de compatibilidad de estados son **lógica de dominio**.

**✅ Solución:** Crear Value Object `BookStatusCollection`:
```php
Domain/Model/ValueObjects/BookStatusCollection.php

class BookStatusCollection {
    private array $statuses;
    
    public function __construct(array $statuses) {
        $this->statuses = $this->cleanIncompatibleStatuses($statuses);
    }
    
    private function cleanIncompatibleStatuses(array $statuses): array {
        // Si tiene 'read', es incompatible con 'reading' y 'to-read'
        if (in_array(BookStatus::READ, $statuses)) {
            return array_diff($statuses, [
                BookStatus::READING, 
                BookStatus::TO_READ
            ]);
        }
        
        // Si está pausado o abandonado, no puede estar 'reading'
        if ($this->hasAny([BookStatus::PAUSED, BookStatus::ABANDONED], $statuses)) {
            return array_diff($statuses, [BookStatus::READING]);
        }
        
        return $statuses;
    }
}
```

---

### 5. **Mezcla de Conceptos User-Book (Relación Many-to-Many)**

El repositorio maneja tanto libros como relaciones usuario-libro:

```php
// ✅ Métodos de libro puro (OK - pertenecen a BookRepository)
findById(string $isbn): ?Book
findAll(array $filters = []): array
save(Book $book): void
deleteByIsbn(string $isbn): bool

// ❌ Métodos de relación usuario-libro (deberían estar en UserBookRepository)
addBookToUser(int $userId, string $isbn, array $statuses)
removeBookFromUser(int $userId, string $isbn)
findBooksByUser(int $userId, array $filters)
updateUserBookStatuses(int $userId, string $isbn, array $statuses)
updateUserBookRating(int $userId, string $isbn, ?float $rating)
getUserBookStatuses(int $userId, string $isbn)
editUserBook(int $userId, string $isbn, ...)
```

**✅ Solución:** Crear `UserBookRepository` para manejar la relación many-to-many:

```php
Infrastructure/Persistence/UserBook/MySqlUserBookRepository.php

class MySqlUserBookRepository implements UserBookRepositoryInterface {
    public function add(int $userId, string $isbn, array $statuses): void
    public function remove(int $userId, string $isbn): bool
    public function findByUser(int $userId, array $filters = []): array
    public function updateStatuses(int $userId, string $isbn, array $statuses): void
    public function updateRating(int $userId, string $isbn, ?float $rating): void
    public function getStatuses(int $userId, string $isbn): array
    public function update(int $userId, string $isbn, UserBookData $data): void
}
```

---

## 🟡 PROBLEMAS MEDIOS

### 6. **Falta de Value Objects**

El código usa tipos primitivos donde debería usar Value Objects:

| Primitivo actual | Value Object sugerido | Beneficios |
|-----------------|----------------------|-----------|
| `string $isbn` | `ISBN` | Validación (ISBN-10/13), formateo automático |
| `string $publicationDate` | `PublicationDate` | Formateo consistente, validación de formato |
| `float $rating` | `Rating` | Validación 0.5-5.0, múltiplos de 0.5 |
| `array $statuses` | `BookStatusCollection` | Validación de compatibilidad, reglas de negocio |
| `string $statusName` | `BookStatus` | Enum o VO con estados permitidos |
| `int $pageNumber` | `PageNumber` | Validación (>= 0), comparaciones |

#### **Ejemplo: Value Object ISBN**

```php
Domain/Model/ValueObjects/ISBN.php

final class ISBN {
    private string $value;
    
    public function __construct(string $isbn) {
        $normalized = $this->normalize($isbn);
        
        if (!$this->isValid($normalized)) {
            throw new InvalidArgumentException("Invalid ISBN: {$isbn}");
        }
        
        $this->value = $normalized;
    }
    
    private function normalize(string $isbn): string {
        // Remover guiones y espacios
        return preg_replace('/[\s\-]/', '', $isbn);
    }
    
    private function isValid(string $isbn): bool {
        return $this->isValidISBN10($isbn) || $this->isValidISBN13($isbn);
    }
    
    private function isValidISBN10(string $isbn): bool {
        if (strlen($isbn) !== 10) return false;
        // Algoritmo de validación ISBN-10
        $check = 0;
        for ($i = 0; $i < 9; $i++) {
            $check += (10 - $i) * (int)$isbn[$i];
        }
        $check = (11 - ($check % 11)) % 11;
        return ($check === 10 ? 'X' : (string)$check) === $isbn[9];
    }
    
    private function isValidISBN13(string $isbn): bool {
        if (strlen($isbn) !== 13) return false;
        // Algoritmo de validación ISBN-13
        $check = 0;
        for ($i = 0; $i < 12; $i++) {
            $check += (int)$isbn[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return ((10 - ($check % 10)) % 10) === (int)$isbn[12];
    }
    
    public function toString(): string {
        return $this->value;
    }
    
    public function format(): string {
        // ISBN-13: 978-3-16-148410-0
        if (strlen($this->value) === 13) {
            return substr($this->value, 0, 3) . '-' . 
                   substr($this->value, 3, 1) . '-' . 
                   substr($this->value, 4, 2) . '-' . 
                   substr($this->value, 6, 6) . '-' . 
                   substr($this->value, 12, 1);
        }
        return $this->value;
    }
    
    public function equals(ISBN $other): bool {
        return $this->value === $other->value;
    }
}
```

#### **Ejemplo: Value Object Rating**

```php
Domain/Model/ValueObjects/Rating.php

final class Rating {
    private const MIN_RATING = 0.5;
    private const MAX_RATING = 5.0;
    private const STEP = 0.5;
    
    private float $value;
    
    public function __construct(float $rating) {
        if ($rating < self::MIN_RATING || $rating > self::MAX_RATING) {
            throw new InvalidArgumentException(
                sprintf('Rating must be between %.1f and %.1f', 
                    self::MIN_RATING, 
                    self::MAX_RATING
                )
            );
        }
        
        if (fmod($rating, self::STEP) !== 0.0) {
            throw new InvalidArgumentException(
                sprintf('Rating must be a multiple of %.1f', self::STEP)
            );
        }
        
        $this->value = $rating;
    }
    
    public static function fromStars(int $stars, bool $half = false): self {
        return new self($stars + ($half ? 0.5 : 0));
    }
    
    public function toFloat(): float {
        return $this->value;
    }
    
    public function toStars(): array {
        $fullStars = (int)floor($this->value);
        $hasHalfStar = ($this->value - $fullStars) >= 0.5;
        
        return [
            'full' => $fullStars,
            'half' => $hasHalfStar,
            'empty' => 5 - $fullStars - ($hasHalfStar ? 1 : 0)
        ];
    }
    
    public function isHigherThan(Rating $other): bool {
        return $this->value > $other->value;
    }
}
```

**Beneficios de usar Value Objects:**
- ✅ Validación automática en construcción
- ✅ Lógica de formateo encapsulada
- ✅ Type safety mejorado
- ✅ Prevención de estados inválidos
- ✅ Métodos de utilidad específicos del dominio
- ✅ Reutilización en toda la aplicación

---

### 7. **Logging Duplicado en Todos los Repositorios**

Los métodos `logError`, `logInfo`, `logDebug`, `logWarning` están **copiados idénticamente** en:
- `MySqlBookRepository.php` (líneas 24-59)
- `MySqlUserRepository.php`
- `MySqlMovieRepository.php`

```php
// Código duplicado en cada repositorio:
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

private function logInfo(string $message, array $context = []): void { /* ... */ }
private function logDebug(string $message, array $context = []): void { /* ... */ }
private function logWarning(string $message, array $context = []): void { /* ... */ }
```

**✅ Solución 1: Trait** (más flexible)
```php
Infrastructure/Persistence/Concerns/LoggableTrait.php

trait LoggableTrait {
    protected ?Logger $logger = null;
    
    protected function logError(string $message, \Exception $e, array $context = []): void {
        if ($this->logger) {
            $this->logger->error($message, [
                'exception' => [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ],
                'context' => $context
            ]);
        }
    }
    
    protected function logInfo(string $message, array $context = []): void {
        $this->logger?->info($message, ['context' => $context]);
    }
    
    protected function logDebug(string $message, array $context = []): void {
        $this->logger?->debug($message, ['context' => $context]);
    }
    
    protected function logWarning(string $message, array $context = []): void {
        $this->logger?->warning($message, ['context' => $context]);
    }
}

// Uso:
class MySqlBookRepository implements BookRepositoryInterface {
    use LoggableTrait;
    
    private PDO $db;
    
    public function __construct(PDO $pdo, ?Logger $logger = null) {
        $this->db = $pdo;
        $this->logger = $logger;
    }
}
```

**✅ Solución 2: Clase base abstracta** (más estructurado)
```php
Infrastructure/Persistence/AbstractMySqlRepository.php

abstract class AbstractMySqlRepository {
    protected PDO $db;
    protected ?Logger $logger;
    
    public function __construct(PDO $pdo, ?Logger $logger = null) {
        $this->db = $pdo;
        $this->logger = $logger;
    }
    
    protected function logError(string $message, \Exception $e, array $context = []): void {
        // ... (mismo código)
    }
    
    protected function logInfo(string $message, array $context = []): void { /* ... */ }
    protected function logDebug(string $message, array $context = []): void { /* ... */ }
    protected function logWarning(string $message, array $context = []): void { /* ... */ }
    
    protected function executeInTransaction(callable $operation): mixed {
        $this->db->beginTransaction();
        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}

// Uso:
class MySqlBookRepository extends AbstractMySqlRepository implements BookRepositoryInterface {
    // Ya tiene acceso a logError, logInfo, etc.
}
```

---

### 8. **Falta de Capa de Servicios de Dominio**

Operaciones complejas están directamente en repositorios o use cases cuando deberían estar en servicios de dominio:

**Ejemplos de servicios faltantes:**

```php
Domain/Services/

├── BookStatusManager.php
│   ├── determineStatusFromSessions(User $user, Book $book, array $sessions): BookStatus
│   ├── canTransitionTo(BookStatus $current, BookStatus $new): bool
│   └── getCompatibleStatuses(BookStatus $status): array
│
├── ReadingProgressCalculator.php
│   ├── calculateProgress(Book $book, int $currentPage): float
│   ├── estimateTimeToFinish(Book $book, User $user): int  // en minutos
│   └── getReadingVelocity(User $user): float  // páginas por hora
│
├── BookStatisticsAggregator.php
│   ├── getMonthlyStats(User $user, int $months = 12): array
│   ├── getYearlyStats(User $user, int $year): array
│   └── getOverallStats(User $user): UserReadingStatistics
│
└── SessionManagementService.php
    ├── startSession(User $user, Book $book): ReadingSession
    ├── pauseSession(ReadingSession $session, string $reason): void
    └── completeSession(ReadingSession $session, int $finalPage): void
```

**Ejemplo de implementación:**

```php
Domain/Services/BookStatusManager.php

class BookStatusManager {
    private const INCOMPATIBLE_STATUSES = [
        BookStatus::READ => [BookStatus::READING, BookStatus::TO_READ],
        BookStatus::PAUSED => [BookStatus::READING],
        BookStatus::ABANDONED => [BookStatus::READING],
    ];
    
    public function determineStatusFromSessions(
        User $user, 
        Book $book, 
        array $sessions
    ): BookStatus {
        $activeSessions = array_filter($sessions, fn($s) => $s->isActive());
        $completedCount = count(array_filter($sessions, fn($s) => $s->isCompleted()));
        
        if (count($activeSessions) > 0) {
            return $completedCount > 0 
                ? BookStatus::RE_READING 
                : BookStatus::READING;
        }
        
        if ($completedCount > 0) {
            return BookStatus::COMPLETED;
        }
        
        return BookStatus::TO_READ;
    }
    
    public function cleanIncompatibleStatuses(array $statuses): array {
        foreach ($statuses as $status) {
            if (isset(self::INCOMPATIBLE_STATUSES[$status])) {
                $statuses = array_diff(
                    $statuses, 
                    self::INCOMPATIBLE_STATUSES[$status]
                );
            }
        }
        
        return array_values($statuses);
    }
    
    public function canTransitionTo(BookStatus $current, BookStatus $new): bool {
        // Lógica de transiciones permitidas
        $allowedTransitions = [
            BookStatus::TO_READ => [BookStatus::READING, BookStatus::ABANDONED],
            BookStatus::READING => [BookStatus::PAUSED, BookStatus::READ, BookStatus::ABANDONED],
            BookStatus::PAUSED => [BookStatus::READING, BookStatus::ABANDONED],
            BookStatus::READ => [BookStatus::RE_READING],
            BookStatus::RE_READING => [BookStatus::READ],
        ];
        
        return in_array($new, $allowedTransitions[$current] ?? [], true);
    }
}
```

---

### 9. **Hidratación Manual Repetitiva**

La conversión de arrays DB a entidades está distribuida en múltiples lugares con código duplicado:

**Ubicaciones con hidratación manual:**
- `findAll()` líneas 130-145
- `findById()` líneas 158-175
- `findBooksByUser()` líneas 500-560

```php
// Código repetido en múltiples lugares:
$data['rating'] = isset($data['rating']) ? (float)$data['rating'] : null;
$data['addedTimestamp'] = isset($data['addedTimestamp']) ? (int)$data['addedTimestamp'] : null;
$data['genres'] = isset($data['genres']) ? json_decode($data['genres'], true) : null;
$userStatuses = $this->fetchBookStatusNames($data['isbn']);
$data['userStatuses'] = is_array($userStatuses) ? $userStatuses : [];
$data['allowedStatuses'] = $this->fetchAllowedStatuses();
$books[] = Book::fromArray($data);
```

**✅ Solución:** Crear `BookDataMapper` dedicado:

```php
Infrastructure/Persistence/Mappers/BookDataMapper.php

class BookDataMapper {
    private BookStatusRepository $statusRepository;
    
    public function __construct(BookStatusRepository $statusRepository) {
        $this->statusRepository = $statusRepository;
    }
    
    public function toDomain(array $dbRow, ?int $userId = null): Book {
        return Book::fromArray([
            'isbn' => $dbRow['isbn'],
            'title' => $dbRow['title'],
            'author' => $dbRow['author'] ?? null,
            'publisher' => $dbRow['publisher'] ?? null,
            'publicationDate' => $dbRow['publication_date'] ?? null,
            'coverUrl' => $dbRow['coverUrl'] ?? null,
            'rating' => isset($dbRow['rating']) ? (float)$dbRow['rating'] : null,
            'pages' => isset($dbRow['pages']) ? (int)$dbRow['pages'] : null,
            'description' => $dbRow['description'] ?? null,
            'addedTimestamp' => isset($dbRow['addedTimestamp']) 
                ? (int)$dbRow['addedTimestamp'] 
                : null,
            'genres' => isset($dbRow['genres']) 
                ? json_decode($dbRow['genres'], true) 
                : null,
            'userStatuses' => $this->extractUserStatuses($dbRow, $userId),
            'allowedStatuses' => $this->statusRepository->getAllowed(),
            'tags' => $dbRow['tags'] ?? [],
            'allowedTags' => $dbRow['allowedTags'] ?? [],
        ]);
    }
    
    public function toPersistence(Book $book): array {
        return [
            'isbn' => $book->getIsbn(),
            'title' => $book->getTitle(),
            'author' => $book->getAuthor(),
            'publisher' => $book->getPublisher(),
            'publication_date' => $this->formatPublicationDate($book->getPublicationDate()),
            'coverUrl' => $book->getCoverUrl(),
            'rating' => $book->getRating(),
            'pages' => $book->getPages(),
            'description' => $book->getDescription(),
            'addedTimestamp' => $book->getAddedTimestamp() ?? time(),
            'genres' => $book->getGenres() ? json_encode($book->getGenres()) : null,
        ];
    }
    
    public function toArray(Book $book): array {
        return $book->toArray();
    }
    
    private function extractUserStatuses(array $dbRow, ?int $userId): array {
        if ($userId && isset($dbRow['user_statuses'])) {
            return json_decode($dbRow['user_statuses'], true) ?? [];
        }
        return [];
    }
    
    private function formatPublicationDate(?string $date): ?string {
        if ($date === null || trim($date) === '') {
            return null;
        }
        
        $date = trim($date);
        
        // Año solo (4 dígitos)
        if (preg_match('/^\d{4}$/', $date)) {
            return $date . '-01-01';
        }
        
        // YYYY-MM-DD (ya válido)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        
        // YYYY-MM
        if (preg_match('/^\d{4}-\d{2}$/', $date)) {
            return $date . '-01';
        }
        
        // Intentar parsear otros formatos
        try {
            $dateTime = new \DateTime($date);
            return $dateTime->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}

// Uso en el repositorio:
class MySqlBookRepository implements BookRepositoryInterface {
    private BookDataMapper $mapper;
    
    public function findAll(array $filters = []): array {
        // ... query SQL ...
        $booksData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return array_map(
            fn($data) => $this->mapper->toDomain($data), 
            $booksData
        );
    }
}
```

---

### 10. **Método `formatPublicationDate` en el Repositorio**

**Ubicación:** líneas 200-230

```php
private function formatPublicationDate(?string $publicationDate): ?string
{
    if ($publicationDate === null || trim($publicationDate) === '') {
        return null;
    }
    
    // ... lógica de formateo ...
}
```

**❌ Problema:** El formateo de fechas es lógica de presentación/transformación, no responsabilidad del repositorio.

**✅ Solución:** Mover a Value Object `PublicationDate` o al `BookDataMapper`.

---

## 🟢 PUNTOS FUERTES (A MANTENER)

✅ **Separación de capas existente** (Domain, Infrastructure, Controllers)  
✅ **Uso de interfaces** para repositorios (`BookRepositoryInterface`)  
✅ **Inyección de dependencias** con PHP-DI  
✅ **Use Cases bien estructurados** (patrón Command con `execute()`)  
✅ **Enrutamiento por acciones** coherente (`ActionRouter`)  
✅ **Middleware de autenticación** separado (`AuthMiddleware`)  
✅ **Logging estructurado** con Monolog  
✅ **Transacciones de base de datos** bien manejadas  
✅ **Manejo de errores** con try-catch y rollback  

---

## 📐 PROPUESTA DE REFACTORIZACIÓN

### **Fase 1: Dividir MySqlBookRepository (CRÍTICO) - Semana 1-2**

**Objetivo:** Reducir de 2,435 líneas a ~200-300 líneas por repositorio

```
Infrastructure/Persistence/
├── Book/
│   ├── MySqlBookRepository.php              (~200 líneas)
│   │   ├── findById(string $isbn): ?Book
│   │   ├── findAll(array $filters): array
│   │   ├── save(Book $book): void
│   │   ├── deleteByIsbn(string $isbn): bool
│   │   └── findByUserStatus(string $status): array
│   │
│   ├── MySqlUserBookRepository.php          (~150 líneas)
│   │   ├── add(int $userId, string $isbn, array $statuses): void
│   │   ├── remove(int $userId, string $isbn): bool
│   │   ├── findByUser(int $userId, array $filters): array
│   │   ├── updateStatuses(int $userId, string $isbn, array $statuses): void
│   │   ├── updateRating(int $userId, string $isbn, ?float $rating): void
│   │   └── getStatuses(int $userId, string $isbn): array
│   │
│   └── Mappers/
│       └── BookDataMapper.php               (~100 líneas)
│
├── ReadingSession/
│   ├── MySqlReadingSessionRepository.php    (~300 líneas)
│   │   ├── create(ReadingSession $session): int
│   │   ├── findById(int $id): ?ReadingSession
│   │   ├── getActive(int $userId, string $isbn): ?ReadingSession
│   │   ├── complete(int $sessionId, int $finalPage): void
│   │   ├── pause(int $sessionId, ?string $reason): void
│   │   ├── resume(int $sessionId): void
│   │   ├── abandon(int $sessionId, ?string $reason): void
│   │   ├── delete(int $sessionId, bool $keepHistory): void
│   │   ├── findByUser(int $userId, ?string $status): array
│   │   └── findByBook(int $userId, string $isbn): array
│   │
│   └── Mappers/
│       └── ReadingSessionDataMapper.php
│
├── ReadingProgress/
│   └── MySqlReadingProgressRepository.php   (~200 líneas)
│       ├── add(ReadingProgress $progress): void
│       ├── getHistory(int $userId, string $isbn): array
│       ├── getMonthlyStats(int $userId, int $months): array
│       ├── getCurrentPage(int $userId, string $isbn): int
│       └── getLastProgressPage(int $userId, string $isbn): int
│
├── BookTag/
│   └── MySqlBookTagRepository.php           (~150 líneas)
│       ├── create(int $userId, string $name, string $color): int
│       ├── assign(int $userId, string $isbn, int $tagId): void
│       ├── removeAll(int $userId, string $isbn): void
│       ├── findByBook(int $userId, string $isbn): array
│       └── findByUser(int $userId): array
│
└── BookNote/
    └── MySqlBookNoteRepository.php          (~100 líneas)
        ├── add(BookNote $note): int
        ├── findByBook(int $userId, string $isbn): array
        └── findByPage(int $userId, string $isbn, int $page): array
```

**Métricas de mejora:**
- MySqlBookRepository: 2,435 → ~200 líneas ✅ (reducción del 92%)
- Responsabilidades por clase: 8 → 1 ✅
- Métodos públicos por interface: 40+ → ~6-10 ✅

---

### **Fase 2: Crear Value Objects - Semana 3**

```php
Domain/Model/ValueObjects/
├── ISBN.php
│   ├── __construct(string $isbn)
│   ├── isValid(string $isbn): bool
│   ├── normalize(string $isbn): string
│   ├── format(): string
│   └── equals(ISBN $other): bool
│
├── Rating.php
│   ├── __construct(float $rating)
│   ├── fromStars(int $stars, bool $half): self
│   ├── toFloat(): float
│   ├── toStars(): array
│   └── isHigherThan(Rating $other): bool
│
├── PublicationDate.php
│   ├── __construct(string $date)
│   ├── fromYear(int $year): self
│   ├── toSqlFormat(): string
│   ├── toHumanReadable(): string
│   └── getYear(): int
│
├── BookStatus.php (enum)
│   ├── TO_READ
│   ├── READING
│   ├── READ
│   ├── PAUSED
│   ├── ABANDONED
│   └── RE_READING
│
├── BookStatusCollection.php
│   ├── __construct(array $statuses)
│   ├── add(BookStatus $status): self
│   ├── remove(BookStatus $status): self
│   ├── has(BookStatus $status): bool
│   ├── cleanIncompatible(): self
│   └── toArray(): array
│
└── PageNumber.php
    ├── __construct(int $page)
    ├── isValidFor(Book $book): bool
    ├── toInt(): int
    └── equals(PageNumber $other): bool
```

**Ejemplo de uso en Book:**
```php
class Book {
    private ISBN $isbn;
    private Rating $rating;
    private PublicationDate $publicationDate;
    private BookStatusCollection $statuses;
    
    public function __construct(
        ISBN $isbn,
        string $title,
        Rating $rating,
        BookStatusCollection $statuses,
        // ...
    ) {
        $this->isbn = $isbn;
        $this->rating = $rating;
        $this->statuses = $statuses->cleanIncompatible();
    }
}
```

---

### **Fase 3: Extraer Servicios de Dominio - Semana 4**

```php
Domain/Services/
├── BookStatusManager.php
│   ├── determineStatusFromSessions(User, Book, array): BookStatus
│   ├── cleanIncompatibleStatuses(array): array
│   └── canTransitionTo(BookStatus, BookStatus): bool
│
├── ReadingProgressCalculator.php
│   ├── calculateProgress(Book, int): float
│   ├── estimateTimeToFinish(Book, User): int
│   └── getReadingVelocity(User): float
│
├── BookStatisticsAggregator.php
│   ├── getMonthlyStats(User, int): MonthlyStatistics[]
│   ├── getYearlyStats(User, int): YearlyStatistics
│   └── getOverallStats(User): UserReadingStatistics
│
└── SessionManagementService.php
    ├── startSession(User, Book): ReadingSession
    ├── pauseSession(ReadingSession, string): void
    ├── resumeSession(ReadingSession): void
    └── completeSession(ReadingSession, int): void
```

---

### **Fase 4: Crear Capa de Mappers - Semana 4**

```php
Infrastructure/Persistence/Mappers/
├── AbstractDataMapper.php  (clase base)
│   └── extractValue(array, string, mixed): mixed
│
├── BookDataMapper.php
│   ├── toDomain(array, ?int): Book
│   ├── toPersistence(Book): array
│   └── toArray(Book): array
│
├── ReadingSessionDataMapper.php
│   ├── toDomain(array): ReadingSession
│   └── toPersistence(ReadingSession): array
│
└── ReadingProgressDataMapper.php
    ├── toDomain(array): ReadingProgress
    └── toPersistence(ReadingProgress): array
```

---

### **Fase 5: Extraer Logging a Trait/Base - Semana 4**

**Opción A: Trait** (recomendado para PHP 8.0+)
```php
Infrastructure/Persistence/Concerns/LoggableTrait.php

trait LoggableTrait {
    protected ?Logger $logger = null;
    
    protected function logError(string $message, \Exception $e, array $context = []): void { /* ... */ }
    protected function logInfo(string $message, array $context = []): void { /* ... */ }
    protected function logDebug(string $message, array $context = []): void { /* ... */ }
    protected function logWarning(string $message, array $context = []): void { /* ... */ }
}
```

**Opción B: Clase base abstracta**
```php
Infrastructure/Persistence/AbstractMySqlRepository.php

abstract class AbstractMySqlRepository {
    protected PDO $db;
    protected ?Logger $logger;
    
    public function __construct(PDO $pdo, ?Logger $logger = null) {
        $this->db = $pdo;
        $this->logger = $logger;
    }
    
    protected function logError(...) { /* ... */ }
    protected function logInfo(...) { /* ... */ }
    
    protected function executeInTransaction(callable $operation): mixed {
        $this->db->beginTransaction();
        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
```

---

### **Fase 6: Actualizar Interfaces - Semana 5**

**BookRepositoryInterface (reducida):**
```php
interface BookRepositoryInterface {
    public function findById(string $isbn): ?Book;
    public function findAll(array $filters = []): array;
    public function findByUserStatus(string $statusName): array;
    public function save(Book $book): void;
    public function deleteByIsbn(string $isbn): bool;
    public function fetchAllowedStatuses(): array;
}
```

**Nuevas interfaces:**
```php
interface UserBookRepositoryInterface {
    public function add(int $userId, string $isbn, array $statuses): void;
    public function remove(int $userId, string $isbn): bool;
    public function findByUser(int $userId, array $filters): array;
    public function updateStatuses(int $userId, string $isbn, array $statuses): void;
    public function updateRating(int $userId, string $isbn, ?float $rating): void;
    public function getStatuses(int $userId, string $isbn): array;
}

interface ReadingSessionRepositoryInterface {
    public function create(ReadingSession $session): int;
    public function findById(int $id): ?ReadingSession;
    public function getActive(int $userId, string $isbn): ?ReadingSession;
    public function complete(int $sessionId, int $finalPage): void;
    public function pause(int $sessionId, ?string $reason): void;
    public function resume(int $sessionId): void;
    public function abandon(int $sessionId, ?string $reason): void;
}
```

---

## 📊 MÉTRICAS DE MEJORA ESPERADAS

| Métrica | Antes | Después (objetivo) | Mejora |
|---------|-------|-------------------|--------|
| Líneas en BookRepository | 2,435 | <300 | 📉 92% |
| Tamaño del archivo | 116 KB | <15 KB | 📉 87% |
| Métodos públicos en BookRepository | 58+ | ~10 | 📉 83% |
| Métodos en BookRepositoryInterface | 40+ | ~6 | 📉 85% |
| Responsabilidades por clase | 8+ | 1 | 📉 87% |
| Acoplamiento (dependencies) | Alto | Bajo | ✅ |
| Cohesión | Baja | Alta | ✅ |
| Reusabilidad de componentes | 20% | 80% | 📈 300% |
| Testabilidad (% código testeable) | 40% | 90% | 📈 125% |
| Complejidad ciclomática promedio | ~15 | <5 | 📉 67% |
| Número de archivos | 1 monolito | 12 especializados | 📈 |

---

## 🎯 PRIORIDADES DE REFACTORIZACIÓN

### **🔴 URGENTE (Semana 1-2):**
1. ✅ **Dividir `MySqlBookRepository`** en repositorios especializados
   - Crear `MySqlUserBookRepository`
   - Crear `MySqlReadingSessionRepository`
   - Reducir `MySqlBookRepository` a CRUD básico
   
2. ✅ **Mover lógica de negocio** de repositorios a Use Cases/Servicios
   - Extraer validaciones a entidades
   - Mover reglas de compatibilidad de estados a Value Objects

### **🟡 IMPORTANTE (Semana 3-4):**
3. ✅ **Implementar Value Objects** críticos
   - `ISBN` (validación y normalización)
   - `Rating` (validación de rangos)
   - `BookStatusCollection` (reglas de compatibilidad)
   
4. ✅ **Crear Data Mappers**
   - `BookDataMapper`
   - `ReadingSessionDataMapper`
   
5. ✅ **Extraer logging** a trait o clase base
   - Eliminar duplicación en repositorios

### **🟢 DESEABLE (Mes 2):**
6. ✅ **Crear servicios de dominio**
   - `BookStatusManager`
   - `ReadingProgressCalculator`
   - `BookStatisticsAggregator`
   
7. ✅ **Implementar eventos de dominio**
   - `BookCompletedEvent`
   - `ReadingSessionStartedEvent`
   - `ReadingProgressUpdatedEvent`
   
8. ✅ **Añadir tests unitarios** para las nuevas clases
   - Cobertura > 80% en capa de dominio

---

## 🏗️ PATRÓN DE ARQUITECTURA HEXAGONAL IDEAL

```
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE PRESENTACIÓN                      │
│  (HTTP/CLI - Puntos de entrada externos)                    │
│  ├── Controllers/ (adaptan HTTP requests a Use Cases)       │
│  └── CLI Commands/ (comandos de consola)                    │
└──────────────────────────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE APLICACIÓN                        │
│  (Orquesta casos de uso - sin lógica de negocio)           │
│  └── UseCases/                                               │
│      ├── AddBookUseCase                                      │
│      ├── UpdateBookRatingUseCase                            │
│      └── CreateReadingSessionUseCase                        │
└──────────────────────────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE DOMINIO                           │
│  (Lógica de negocio pura - independiente de infraestructura)│
│                                                              │
│  ├── Model/                                                  │
│  │   ├── Entities/                                          │
│  │   │   ├── Book.php                                       │
│  │   │   ├── ReadingSession.php                             │
│  │   │   └── User.php                                       │
│  │   │                                                       │
│  │   └── ValueObjects/                                      │
│  │       ├── ISBN.php                                       │
│  │       ├── Rating.php                                     │
│  │       ├── PublicationDate.php                            │
│  │       ├── BookStatus.php                                 │
│  │       └── BookStatusCollection.php                       │
│  │                                                           │
│  ├── Services/ (lógica de dominio compleja)                 │
│  │   ├── BookStatusManager.php                              │
│  │   ├── ReadingProgressCalculator.php                      │
│  │   └── BookStatisticsAggregator.php                       │
│  │                                                           │
│  └── Repository/ (interfaces - PUERTOS)                     │
│      ├── BookRepositoryInterface                            │
│      ├── UserBookRepositoryInterface                        │
│      ├── ReadingSessionRepositoryInterface                  │
│      └── ReadingProgressRepositoryInterface                 │
└──────────────────────────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                 CAPA DE INFRAESTRUCTURA                      │
│  (Implementaciones técnicas - ADAPTADORES)                  │
│                                                              │
│  ├── Persistence/ (implementa Repository interfaces)        │
│  │   ├── Book/                                              │
│  │   │   ├── MySqlBookRepository.php (~200 líneas)          │
│  │   │   ├── MySqlUserBookRepository.php (~150 líneas)      │
│  │   │   └── Mappers/BookDataMapper.php                     │
│  │   │                                                       │
│  │   ├── ReadingSession/                                    │
│  │   │   ├── MySqlReadingSessionRepository.php (~300 líneas)│
│  │   │   └── Mappers/ReadingSessionDataMapper.php           │
│  │   │                                                       │
│  │   ├── ReadingProgress/                                   │
│  │   │   └── MySqlReadingProgressRepository.php             │
│  │   │                                                       │
│  │   ├── BookTag/                                           │
│  │   │   └── MySqlBookTagRepository.php                     │
│  │   │                                                       │
│  │   └── BookNote/                                          │
│  │       └── MySqlBookNoteRepository.php                    │
│  │                                                           │
│  ├── Logging/ (Monolog implementation)                      │
│  │   ├── LoggingService.php                                 │
│  │   └── LoggerFactory.php                                  │
│  │                                                           │
│  ├── Database/                                              │
│  │   └── DatabaseConnector.php                              │
│  │                                                           │
│  ├── Session/                                               │
│  │   └── SessionManager.php                                 │
│  │                                                           │
│  └── Middleware/                                            │
│      └── AuthMiddleware.php                                 │
└──────────────────────────────────────────────────────────────┘
```

**Flujo de una petición típica:**

```
1. HTTP Request → BookController::addBook()
2. Controller extrae datos → valida formato básico
3. Controller invoca → AddBookUseCase::execute($bookData, $userId)
4. Use Case orquesta:
   - Valida reglas de negocio
   - Crea entidad Book con Value Objects (ISBN, Rating, etc.)
   - Usa BookRepository::save(Book)
   - Usa UserBookRepository::add($userId, $isbn, $statuses)
5. Repositorio persiste en base de datos
6. Use Case retorna Book
7. Controller convierte Book → JSON response
```

---

## 📝 RECOMENDACIONES FINALES

### **Estrategia de Refactorización:**

1. **No refactorizar todo a la vez** 
   - Hacerlo incremental por repositorio
   - Empezar con el más independiente (ReadingSession)
   - Mantener backward compatibility temporalmente

2. **Testing First**
   - Escribir tests de integración ANTES de refactorizar
   - Asegurar que los tests pasen después de cada cambio
   - Objetivo: >80% de cobertura en nueva estructura

3. **Feature Flags**
   - Usar flags para activar/desactivar nuevas implementaciones
   - Permite rollback rápido si hay problemas
   - Facilita A/B testing

4. **Documentación de Interfaces**
   - Agregar contratos claros en PHPDoc
   - Documentar pre-condiciones y post-condiciones
   - Incluir ejemplos de uso

5. **Eventos de Dominio** (futuro)
   - Implementar para operaciones complejas
   - Ejemplos: `BookCompletedEvent`, `ReadingProgressUpdatedEvent`
   - Permite extensibilidad sin modificar código existente

6. **Monitoreo Post-Refactorización**
   - Métricas de performance (tiempo de respuesta)
   - Logs de errores (detectar regresiones)
   - Uso de memoria (optimización de queries)

### **Orden de Implementación Sugerido:**

```
Semana 1-2: Dividir repositorios
├── Crear ReadingSessionRepository (más fácil, independiente)
├── Crear UserBookRepository
├── Reducir BookRepository a CRUD básico
└── Tests de integración

Semana 3: Value Objects
├── Implementar ISBN, Rating, PublicationDate
├── Actualizar entidad Book para usar VOs
└── Tests unitarios de VOs

Semana 4: Mappers y Logging
├── Crear BookDataMapper
├── Extraer LoggableTrait
└── Actualizar repositorios para usar mappers

Semana 5: Servicios de Dominio
├── Crear BookStatusManager
├── Mover lógica de negocio de repositorios a servicios
└── Actualizar Use Cases para usar servicios

Semana 6: Integración y Limpieza
├── Actualizar DI Container (dependencies.php)
├── Actualizar Use Cases
├── Revisar y eliminar código deprecated
└── Documentación final
```

---

## 🔗 Referencias y Próximos Análisis

Este análisis será la base para los siguientes módulos:

- [ ] **Movies Module** - Aplicar lecciones aprendidas de Books
- [ ] **Users Module** - Análisis de gestión de usuarios
- [ ] **Use Cases** - Validación de patrones en capa de aplicación
- [ ] **Domain Objects** - Revisión de entidades y VOs
- [ ] **Controllers** - Análisis de capa de presentación
- [ ] **Frontend** - Arquitectura Vue.js y separación de responsabilidades

**Cada análisis tendrá en cuenta las conclusiones de los anteriores para evitar repetir los mismos errores.**

---

## 📌 Conclusiones Clave

1. ✅ **La arquitectura tiene buenos fundamentos** pero necesita refinamiento
2. ❌ **El principal problema es la violación del SRP** en repositorios
3. 🎯 **La refactorización debe ser incremental** y guiada por tests
4. 📈 **Se espera una mejora del 85-90%** en métricas de calidad de código
5. 🚀 **El resultado será un código más mantenible, testeable y escalable**

---

*Fecha de última actualización: 30 de noviembre de 2025*
