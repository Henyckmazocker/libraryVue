# 🚀 ROADMAP TO OPTIMAL ARCHITECTURE - LibraryVue

**Fecha de creación**: 30 de noviembre de 2025  
**Basado en**: 10 documentos de análisis arquitectónico  
**Objetivo**: Transformar LibraryVue de arquitectura caótica a arquitectura hexagonal limpia

---

## 📊 ESTADO ACTUAL vs OBJETIVO

### Métricas Globales

| Métrica | Estado Actual | Estado Óptimo | Reducción |
|---------|---------------|---------------|-----------|
| **Backend - Líneas en repositorios** | 3,681 líneas | ~1,200 líneas | **-67%** |
| **Backend - Repositorios** | 3 monolíticos | 15+ especializados | **+400%** |
| **Backend - Métodos por interface** | 78 métodos total | ~5 métodos c/u | **-84%** |
| **Frontend - God Components** | 2 (1,034L + 958L) | 0 | **-100%** |
| **Frontend - Código duplicado** | ~900 líneas (25%) | ~100 líneas (6%) | **-89%** |
| **Frontend - Líneas en composables** | 4,200 líneas | ~2,000 líneas | **-52%** |
| **Value Objects implementados** | 0 | 15+ necesarios | **N/A** |
| **Mappers/Hydrators** | 0 | 8+ necesarios | **N/A** |

### Problemas Críticos Identificados

#### Backend (7 problemas críticos)
1. 🔴 **Repositorios monolíticos** - MySqlBookRepository con 2,435 líneas y 8+ responsabilidades
2. 🔴 **Violación de Clean Architecture** - Controllers llaman repositories directamente
3. 🔴 **Código duplicado masivo** - Logging, validaciones, status management en 3 repositorios
4. 🔴 **Falta de Value Objects** - Primitive obsession en todas las entidades
5. 🔴 **ActionRouter duplicado** - 280 líneas de código repetido (auth + CSRF checks)
6. 🔴 **Use Cases con lógica duplicada** - 67% de duplicación entre casos de uso
7. 🔴 **Entidades anémicas vs sobrecargadas** - Movie sin validación, Book con 23 propiedades

#### Frontend (6 problemas críticos)
1. 🔴 **God Composable** - useBooks.js con 1,014 líneas y 28 funciones
2. 🔴 **God Component** - LibraryX.vue con 1,034 líneas
3. 🔴 **Dual State Management** - Pinia + Composables singleton (inconsistencia)
4. 🔴 **Código duplicado** - 80% similitud entre useBooks/useMovies, BookSearch/MovieSearch
5. 🔴 **API calls sin abstracción** - 40+ llamadas con error handling duplicado
6. 🔴 **Componentes de búsqueda duplicados** - 70% similitud estructural

---

## 🎯 FASES DE REFACTORIZACIÓN

### **FASE 0: PREPARACIÓN** (1 semana)

**Objetivo**: Establecer base sólida antes de refactorizar

#### Tareas:
- [ ] **Setup de testing** - PHPUnit + Jest configurados
- [ ] **Baseline de tests** - Tests de integración para flujos críticos
- [ ] **Documentación de API actual** - Mapear todas las actions del backend
- [ ] **Feature flags** - Sistema para activar/desactivar nueva arquitectura
- [ ] **Backup de base de datos** - Snapshot del estado actual
- [ ] **Code freeze parcial** - Solo bugfixes críticos durante refactorización

**Entregables**:
- ✅ PHPUnit configurado con ~20 tests de integración
- ✅ Jest configurado con ~30 tests de componentes clave
- ✅ Documento de API actions (40+ endpoints documentados)
- ✅ Feature flags en `.env` y frontend config
- ✅ Script de backup automatizado

---

### **FASE 1: BACKEND - VALUE OBJECTS Y TRAITS** (2 semanas)

**Objetivo**: Crear componentes compartidos que eliminen duplicación

#### 1.1 Value Objects Core (Semana 1)

**Prioridad CRÍTICA** - Estos VOs se usan en TODO el dominio

```php
Domain/Model/ValueObjects/
├── Rating.php                    # Compartido por Book, Movie, User
├── Genre.php                     # Compartido por Book, Movie
├── ISBN.php                      # Identificador único de Book
├── MovieIdentifier.php           # Identificador único de Movie (IMDB/TMDB/ISBN)
├── Email.php                     # User email con validación
├── GoogleId.php                  # User Google OAuth ID
└── Timestamp.php                 # Timestamps consistentes
```

**Implementación Rating (ejemplo clave)**:
```php
final class Rating {
    private float $value;
    
    private function __construct(float $value) {
        if ($value < 0.5 || $value > 5.0) {
            throw new InvalidArgumentException('Rating must be between 0.5 and 5.0');
        }
        if (floor($value * 2) != $value * 2) {
            throw new InvalidArgumentException('Rating must be a multiple of 0.5');
        }
        $this->value = $value;
    }
    
    public static function fromFloat(float $value): self {
        return new self($value);
    }
    
    public static function fromNullableFloat(?float $value): ?self {
        return $value !== null ? new self($value) : null;
    }
    
    public function toFloat(): float {
        return $this->value;
    }
    
    public function equals(Rating $other): bool {
        return $this->value === $other->value;
    }
}
```

**Impacto**: Elimina validación duplicada en:
- Book constructor (15 líneas)
- Movie setters (10 líneas)
- AddBookUseCase (6 líneas)
- AddMovieUseCase (6 líneas)
- UpdateBookRatingUseCase (6 líneas)
- UpdateMovieRatingUseCase (6 líneas)
- **Total eliminado**: ~59 líneas de validación duplicada

#### 1.2 Infrastructure Traits (Semana 2)

```php
Infrastructure/Persistence/Concerns/
├── LoggableTrait.php             # Logging unificado (4 métodos)
├── StatusManagementTrait.php     # getStatusId(), fetchStatusNames()
└── HydrationHelpersTrait.php     # extractInt(), extractBool(), extractJson()
```

**LoggableTrait** (elimina 120 líneas duplicadas):
```php
trait LoggableTrait {
    abstract protected function getLogger(): ?LoggerInterface;
    
    protected function logError(string $message, \Exception $e, array $context = []): void {
        if ($this->getLogger()) {
            $this->getLogger()->error($message, [
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
    
    protected function logInfo(string $message, array $context = []): void { /* ... */ }
    protected function logDebug(string $message, array $context = []): void { /* ... */ }
    protected function logWarning(string $message, array $context = []): void { /* ... */ }
}
```

**StatusManagementTrait** (elimina 60 líneas duplicadas):
```php
trait StatusManagementTrait {
    abstract protected function getStatusTableName(): string;
    abstract protected function getEntityStatusTableName(): string;
    abstract protected function getEntityIdColumnName(): string;
    
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
```

**Checklist de tareas**:
- [ ] Implementar 7 Value Objects core
- [ ] Tests unitarios para cada VO (100% coverage)
- [ ] Implementar 3 Traits de infraestructura
- [ ] Documentar uso de VOs en Wiki

**Tests esperados**: 35 tests (7 VOs × 5 tests/VO)

---

### **FASE 2: BACKEND - REFACTORIZACIÓN DE REPOSITORIOS** (6 semanas)

**Estrategia**: Refactorizar de menor a mayor complejidad

#### 2.1 Users Module (Semana 3-4) - **PROYECTO PILOTO**

**Por qué primero**: Es el más limpio (415 líneas), menos responsabilidades mezcladas

**Refactorización**:

```
ANTES:
MySqlUserRepository (415 líneas, 14 métodos)
├── Métodos de User ✅
├── Métodos de UserBooks ❌ (mover)
├── Métodos de UserMovies ❌ (mover)
└── Métodos de Stats ❌ (mover)

DESPUÉS:
Infrastructure/Persistence/User/
├── MySqlUserRepository.php (150 líneas, 6 métodos)
│   ├── findByGoogleId()
│   ├── findById()
│   ├── findByEmail()
│   ├── save()
│   ├── update()
│   └── Uses: LoggableTrait, UserDataMapper
│
├── MySqlUserBookRepository.php (120 líneas, 5 métodos)
│   ├── findByUser()
│   ├── hasBook()
│   ├── add()
│   ├── remove()
│   └── Uses: LoggableTrait
│
├── MySqlUserMovieRepository.php (120 líneas, 5 métodos)
│   └── (similar a UserBook)
│
└── Mappers/
    └── UserDataMapper.php (80 líneas)
        ├── toDomain(array $dbRow): User
        ├── toPersistence(User $user): array
        └── Uses: HydrationHelpersTrait

Domain/Services/
└── UserLibraryStatisticsService.php (100 líneas)
    └── getUserLibraryStats(int $userId): UserLibraryStatistics
```

**Actualizar entidad User**:
```php
Domain/Model/User.php (refactorizado)

class User {
    public function __construct(
        private ?int $id,
        private GoogleId $googleId,        // ✅ VO
        private Email $email,              // ✅ VO
        private string $name,
        private ?string $picture,
        private Timestamp $createdAt,      // ✅ VO
        private ?Timestamp $updatedAt,     // ✅ VO
        private ?Timestamp $lastLogin,     // ✅ VO
        private ?UserPreferences $preferences, // ✅ VO
        private bool $isActive = true
    ) {
        // Validaciones de negocio (no primitivos)
        if (empty($name)) {
            throw new InvalidArgumentException('User name cannot be empty');
        }
    }
    
    // Getters + métodos de negocio
    public function updateLastLogin(): void {
        $this->lastLogin = Timestamp::now();
    }
    
    public function activate(): void { $this->isActive = true; }
    public function deactivate(): void { $this->isActive = false; }
}
```

**Checklist**:
- [ ] Implementar Email, GoogleId, UserPreferences VOs
- [ ] Crear UserDataMapper
- [ ] Dividir MySqlUserRepository en 3 repositories
- [ ] Implementar UserLibraryStatisticsService
- [ ] Migrar 100% de tests existentes
- [ ] Actualizar Use Cases de Users
- [ ] Registrar nuevos repositories en DI
- [ ] Feature flag: `USE_NEW_USER_REPOSITORY=true`

**Tests esperados**: 25 tests (repositorios + mapper + service)

#### 2.2 Movies Module (Semana 5-6) - **APLICAR APRENDIZAJES**

**Por qué segundo**: Mediana complejidad (831 líneas), usar como plantilla para Books

**Refactorización**:

```
ANTES:
MySqlMovieRepository (831 líneas, 24 métodos)

DESPUÉS:
Infrastructure/Persistence/Movie/
├── MySqlMovieRepository.php (180 líneas, 6 métodos)
│   ├── findById()
│   ├── findAll()
│   ├── save()
│   ├── deleteByIsbn()
│   ├── fetchAllowedStatuses()
│   └── Uses: LoggableTrait, StatusManagementTrait, MovieDataMapper
│
├── MySqlUserMovieRepository.php (150 líneas, 8 métodos)
│   ├── findByUser()
│   ├── hasMovie()
│   ├── add()
│   ├── remove()
│   ├── updateStatuses()
│   ├── updateRating()
│   └── Uses: LoggableTrait
│
├── MySqlMovieTagRepository.php (120 líneas, 6 métodos)
│   ├── getByUser()
│   ├── getByMovie()
│   ├── create()
│   ├── assign()
│   ├── removeAll()
│   └── Uses: LoggableTrait
│
├── MySqlMovieNoteRepository.php (80 líneas, 2 métodos)
│   ├── add()
│   └── getByMovie()
│
└── Mappers/
    └── MovieDataMapper.php (100 líneas)
```

**Actualizar entidad Movie**:
```php
Domain/Model/Movie.php (refactorizado)

class Movie {
    public function __construct(
        private MovieIdentifier $id,      // ✅ VO (crítico para Movies)
        private string $title,
        private array $director,
        private ?int $year,
        private ?Genre $genre,            // ✅ VO (compartido con Book)
        private ?int $runtime,
        private ?string $plot,
        private ?string $poster,
        private ?Rating $rating,          // ✅ VO (compartido con Book)
        private ?Rating $userRating,      // ✅ VO
        private array $userStatuses = []
    ) {
        // ✅ Validación en constructor (no anémico)
        if (empty($title)) {
            throw new InvalidArgumentException('Movie title cannot be empty');
        }
        if ($year !== null && ($year < 1888 || $year > date('Y') + 5)) {
            throw new InvalidArgumentException('Invalid movie year');
        }
    }
    
    // ✅ Métodos de negocio
    public function updateRating(Rating $rating): void {
        $this->userRating = $rating;
    }
    
    public function addStatus(string $status): void {
        if (!in_array($status, $this->userStatuses)) {
            $this->userStatuses[] = $status;
        }
    }
}
```

**Checklist**:
- [ ] Implementar MovieIdentifier VO (crítico)
- [ ] Reutilizar Rating, Genre VOs
- [ ] Crear MovieDataMapper
- [ ] Dividir MySqlMovieRepository en 4 repositorios
- [ ] Migrar tests
- [ ] Actualizar Use Cases de Movies
- [ ] Registrar en DI
- [ ] Feature flag: `USE_NEW_MOVIE_REPOSITORY=true`

**Tests esperados**: 35 tests

#### 2.3 Books Module (Semana 7-8) - **EL MÁS COMPLEJO**

**Por qué último**: Mayor complejidad (2,435 líneas), incluye ReadingSessions

**Refactorización**:

```
ANTES:
MySqlBookRepository (2,435 líneas, 58+ métodos)

DESPUÉS:
Infrastructure/Persistence/Book/
├── MySqlBookRepository.php (200 líneas, 8 métodos)
│   ├── findById()
│   ├── findAll()
│   ├── findByUserStatus()
│   ├── save()
│   ├── deleteByIsbn()
│   ├── fetchAllowedStatuses()
│   └── Uses: LoggableTrait, StatusManagementTrait, BookDataMapper
│
├── MySqlUserBookRepository.php (180 líneas, 10 métodos)
│   ├── findByUser()
│   ├── hasBook()
│   ├── add()
│   ├── remove()
│   ├── updateStatuses()
│   ├── updateRating()
│   ├── edit()
│   └── Uses: LoggableTrait
│
├── MySqlBookTagRepository.php (150 líneas, 6 métodos)
│   └── (similar a MovieTag)
│
├── MySqlBookNoteRepository.php (100 líneas, 2 métodos)
│   └── (similar a MovieNote)
│
├── MySqlReadingSessionRepository.php (350 líneas, 15 métodos) ← NUEVO
│   ├── create()
│   ├── getActive()
│   ├── complete()
│   ├── pause()
│   ├── resume()
│   ├── abandon()
│   ├── delete()
│   ├── getHistory()
│   ├── getProgress()
│   ├── getUserActiveSessions()
│   └── Uses: LoggableTrait, ReadingSessionDataMapper
│
├── MySqlReadingProgressRepository.php (250 líneas, 8 métodos) ← NUEVO
│   ├── updateWithSession()
│   ├── addHistory()
│   ├── getHistory()
│   ├── getMonthlyStats()
│   ├── getCurrentPage()
│   └── Uses: LoggableTrait
│
└── Mappers/
    ├── BookDataMapper.php (120 líneas)
    └── ReadingSessionDataMapper.php (100 líneas)

Domain/Services/
├── BookStatusManager.php (80 líneas)
├── ReadingProgressCalculator.php (100 líneas)
└── BookStatisticsService.php (120 líneas)
```

**Actualizar entidad Book**:
```php
Domain/Model/Book.php (refactorizado)

class Book {
    public function __construct(
        private ISBN $isbn,                    // ✅ VO
        private string $title,
        private string $author,
        private ?PublicationDate $publicationDate, // ✅ VO
        private ?int $pages,
        private ?Genre $genre,                 // ✅ VO compartido
        private ?string $description,
        private ?string $coverUrl,
        private ?Rating $rating,               // ✅ VO compartido
        private ?Rating $userRating,           // ✅ VO compartido
        private array $userStatuses = []       // ⚠️ Considerar BookStatus VO collection
    ) {
        if (empty($title)) {
            throw new InvalidArgumentException('Book title cannot be empty');
        }
        if (empty($author)) {
            throw new InvalidArgumentException('Book author cannot be empty');
        }
        if ($pages !== null && $pages <= 0) {
            throw new InvalidArgumentException('Book pages must be positive');
        }
    }
    
    // ✅ Métodos de negocio (no getters/setters anémicos)
    public function updateUserRating(Rating $rating): void {
        $this->userRating = $rating;
    }
    
    public function addStatus(string $status): void {
        if (!in_array($status, $this->userStatuses)) {
            $this->userStatuses[] = $status;
        }
    }
    
    public function removeStatus(string $status): void {
        $this->userStatuses = array_filter(
            $this->userStatuses, 
            fn($s) => $s !== $status
        );
    }
}
```

**Nueva entidad ReadingSession**:
```php
Domain/Model/ReadingSession.php (NUEVO)

class ReadingSession {
    public function __construct(
        private ?int $id,
        private ISBN $bookIsbn,
        private int $userId,
        private int $sessionNumber,
        private ?int $startPage,
        private ?int $endPage,
        private Timestamp $startedAt,
        private ?Timestamp $completedAt,
        private ReadingSessionStatus $status,  // ✅ VO (active, paused, completed, abandoned)
        private ?string $completionReason
    ) {}
    
    public function complete(int $endPage, string $reason): void {
        $this->endPage = $endPage;
        $this->completedAt = Timestamp::now();
        $this->status = ReadingSessionStatus::completed();
        $this->completionReason = $reason;
    }
    
    public function pause(): void {
        $this->status = ReadingSessionStatus::paused();
    }
    
    public function resume(): void {
        $this->status = ReadingSessionStatus::active();
    }
    
    public function getPagesRead(): int {
        if ($this->endPage === null || $this->startPage === null) {
            return 0;
        }
        return max(0, $this->endPage - $this->startPage);
    }
}
```

**Checklist**:
- [ ] Implementar ISBN, PublicationDate VOs
- [ ] Reutilizar Rating, Genre VOs
- [ ] Crear ReadingSession, ReadingSessionStatus entidades
- [ ] Crear BookDataMapper, ReadingSessionDataMapper
- [ ] Dividir MySqlBookRepository en 6 repositorios
- [ ] Implementar 3 servicios de dominio
- [ ] Migrar tests (60+ tests esperados)
- [ ] Actualizar Use Cases de Books (8 use cases)
- [ ] Actualizar Use Cases de ReadingSessions (12 use cases)
- [ ] Registrar en DI
- [ ] Feature flag: `USE_NEW_BOOK_REPOSITORY=true`

**Tests esperados**: 60 tests

---

### **FASE 3: BACKEND - REFACTORIZACIÓN DE USE CASES** (2 semanas)

**Objetivo**: Eliminar duplicación en Use Cases, crear DTOs

#### 3.1 DTOs Command/Query (Semana 9)

**Crear estructura de DTOs**:
```php
Domain/DTO/
├── Commands/
│   ├── AddBookCommand.php
│   ├── UpdateBookRatingCommand.php
│   ├── DeleteBookCommand.php
│   ├── CreateReadingSessionCommand.php
│   └── ... (20+ commands)
│
└── Queries/
    ├── GetBooksByUserQuery.php
    ├── GetReadingSessionHistoryQuery.php
    └── ... (15+ queries)
```

**Ejemplo AddBookCommand**:
```php
final class AddBookCommand {
    public function __construct(
        public readonly ISBN $isbn,
        public readonly string $title,
        public readonly string $author,
        public readonly int $userId,
        public readonly array $statuses = [],
        public readonly ?Rating $userRating = null,
        public readonly ?int $pages = null,
        public readonly ?Genre $genre = null
    ) {}
    
    public static function fromArray(array $data, int $userId): self {
        return new self(
            isbn: ISBN::fromString($data['isbn']),
            title: $data['title'],
            author: $data['author'],
            userId: $userId,
            statuses: $data['statuses'] ?? [],
            userRating: isset($data['user_rating']) 
                ? Rating::fromFloat($data['user_rating']) 
                : null,
            pages: $data['pages'] ?? null,
            genre: isset($data['genre']) 
                ? Genre::fromString($data['genre']) 
                : null
        );
    }
}
```

**Beneficios**:
- ✅ Type safety (PHP 8.1+ readonly properties)
- ✅ Validación centralizada (en VO constructors)
- ✅ Documentación explícita de inputs
- ✅ Facilita testing (objetos mock)

#### 3.2 Base Use Case con Template Method (Semana 9)

**Problema**: Código duplicado en 15 use cases (validación, logging, error handling)

**Solución**: Abstract base class con template method

```php
Domain/UseCases/AbstractUseCase.php

abstract class AbstractUseCase {
    protected LoggerInterface $logger;
    
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }
    
    // ✅ Template method (elimina duplicación)
    final public function execute($command) {
        try {
            $this->logger->debug($this->getLogContext(), [
                'command' => get_class($command)
            ]);
            
            $result = $this->doExecute($command);
            
            $this->logger->info($this->getSuccessMessage(), [
                'result' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error($this->getErrorMessage(), [
                'exception' => $e,
                'command' => $command
            ]);
            throw $e;
        }
    }
    
    // ✅ Hook methods (implementar en subclases)
    abstract protected function doExecute($command);
    abstract protected function getLogContext(): string;
    
    protected function getSuccessMessage(): string {
        return 'Use case executed successfully';
    }
    
    protected function getErrorMessage(): string {
        return 'Use case execution failed';
    }
}
```

**Uso refactorizado**:
```php
// ANTES: AddBookUseCase (99 líneas con logging/error handling)
class AddBookUseCase {
    public function execute(array $bookData, int $userId): Book {
        // ❌ 6 líneas de validación de input
        if (empty($bookData['isbn'])) throw new InvalidArgumentException(...);
        if (empty($bookData['title'])) throw new InvalidArgumentException(...);
        // ... 4 más
        
        // ❌ 4 líneas de validación de usuario
        $user = $this->userRepository->findById($userId);
        if (!$user) throw new InvalidArgumentException(...);
        
        // ❌ 4 líneas de check duplicado
        if ($this->userRepository->hasUserBook(...)) throw new InvalidArgumentException(...);
        
        // ✅ 30 líneas de lógica real
        $book = Book::fromArray($bookData);
        $this->bookRepository->save($book);
        return $book;
    }
}

// DESPUÉS: AddBookUseCase (35 líneas solo lógica)
class AddBookUseCase extends AbstractUseCase {
    public function __construct(
        private UserRepository $userRepo,
        private BookRepository $bookRepo,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }
    
    protected function doExecute(AddBookCommand $command): Book {
        // ✅ Validación automática (VOs en command ya validados)
        
        // ✅ Validación de usuario (podría ser Middleware/Guard)
        $user = $this->userRepo->findById($command->userId);
        if (!$user) {
            throw new UserNotFoundException("User {$command->userId} not found");
        }
        
        // ✅ Check duplicado
        if ($this->bookRepo->existsForUser($command->userId, $command->isbn)) {
            throw new DuplicateBookException("Book already in library");
        }
        
        // ✅ Lógica de negocio
        $book = Book::create(
            isbn: $command->isbn,
            title: $command->title,
            author: $command->author,
            userRating: $command->userRating,
            pages: $command->pages,
            genre: $command->genre
        );
        
        $this->bookRepo->save($book);
        
        return $book;
    }
    
    protected function getLogContext(): string {
        return 'AddBookUseCase';
    }
}
```

**Checklist**:
- [ ] Crear 20+ Commands
- [ ] Crear 15+ Queries
- [ ] Implementar AbstractUseCase
- [ ] Refactorizar 19 Use Cases
- [ ] Tests unitarios para DTOs
- [ ] Tests para Use Cases refactorizados

**Reducción esperada**: 1,360L → ~800L (-41%)

---

### **FASE 4: BACKEND - CONTROLLERS Y ROUTING** (2 semanas)

**Objetivo**: Eliminar duplicación en controllers, crear middleware pattern

#### 4.1 Middleware Pattern (Semana 10)

**Problema**: ActionRouter con 280 líneas de auth/CSRF duplicado

**Solución**: Pipeline de middleware

```php
Infrastructure/Middleware/MiddlewarePipeline.php

class MiddlewarePipeline {
    private array $middlewares = [];
    
    public function add(MiddlewareInterface $middleware): self {
        $this->middlewares[] = $middleware;
        return $this;
    }
    
    public function execute(Request $request, callable $handler): Response {
        $pipeline = array_reduce(
            array_reverse($this->middlewares),
            fn($next, $middleware) => fn($req) => $middleware->handle($req, $next),
            $handler
        );
        
        return $pipeline($request);
    }
}
```

**Middleware implementations**:
```php
Infrastructure/Middleware/
├── AuthenticationMiddleware.php   # Verifica sesión activa
├── CSRFMiddleware.php             # Valida CSRF token
├── ValidationMiddleware.php       # Valida DTOs
└── LoggingMiddleware.php          # Log de requests
```

**ActionRouter refactorizado**:
```php
// ANTES: 280 líneas de código duplicado
switch ($action) {
    case 'add_book':
        $authResult = $this->authMiddleware->requireAuthAndCSRF(...); // ❌ Duplicado 40 veces
        if ($authResult['status'] === 'error') return $authResult;
        return $this->bookController->addBook(...);
}

// DESPUÉS: 80 líneas con middleware pipeline
class ActionRouter {
    private array $routes = [];
    
    public function register(string $action, string $controller, string $method, array $middlewares = []): void {
        $this->routes[$action] = [
            'controller' => $controller,
            'method' => $method,
            'middlewares' => $middlewares
        ];
    }
    
    public function dispatch(string $action, array $inputData): array {
        if (!isset($this->routes[$action])) {
            throw new RouteNotFoundException("Action {$action} not found");
        }
        
        $route = $this->routes[$action];
        $request = new Request($action, $inputData);
        
        // ✅ Middleware pipeline (elimina duplicación)
        $pipeline = new MiddlewarePipeline();
        foreach ($route['middlewares'] as $middleware) {
            $pipeline->add($this->container->get($middleware));
        }
        
        $response = $pipeline->execute($request, function($req) use ($route) {
            $controller = $this->container->get($route['controller']);
            return $controller->{$route['method']}($req->getData());
        });
        
        return $response->toArray();
    }
}

// ✅ Configuración declarativa en config/routes.php
return [
    'add_book' => [
        'controller' => BookController::class,
        'method' => 'addBook',
        'middlewares' => [AuthenticationMiddleware::class, CSRFMiddleware::class]
    ],
    'get_books' => [
        'controller' => BookController::class,
        'method' => 'getBooks',
        'middlewares' => [AuthenticationMiddleware::class]
    ],
    // ... 40+ routes
];
```

**Reducción**: ActionRouter.php 280L → 80L (-71%)

#### 4.2 Dividir Controllers Sobrecargados (Semana 11)

**Problema**: BookController con 488 líneas y 4 responsabilidades

**Refactorización**:
```
ANTES:
BookController.php (488 líneas, 28 métodos)
├── Books CRUD (8 métodos)
├── Tags (3 métodos)
├── Reading Sessions (12 métodos)
└── Stats (2 métodos)

DESPUÉS:
Controllers/Books/
├── BookController.php (150 líneas, 8 métodos)
│   ├── addBook()
│   ├── deleteBook()
│   ├── updateRating()
│   ├── updateStatuses()
│   ├── getBooks()
│   ├── editBook()
│   └── Uses: AddBookUseCase, DeleteBookUseCase, etc.
│
├── BookTagController.php (100 líneas, 3 métodos)
│   ├── getUserTags()
│   ├── createTag()
│   └── updateBookTags()
│
├── ReadingSessionController.php (200 líneas, 12 métodos)
│   ├── create()
│   ├── getActive()
│   ├── complete()
│   ├── pause()
│   └── ... (8 más)
│
└── BookStatsController.php (80 líneas, 2 métodos)
    ├── getUserReadingStats()
    └── getCurrentReadingSessions()
```

**Checklist**:
- [ ] Implementar MiddlewarePipeline
- [ ] Crear 4 middlewares core
- [ ] Configurar routes en `config/routes.php`
- [ ] Dividir BookController en 4 controllers
- [ ] Dividir StatsController (crear MovieStatsController)
- [ ] Tests de integración para middleware
- [ ] Actualizar ActionRouter

**Reducción**: Controllers 1,833L → ~1,000L (-45%)

---

### **FASE 5: FRONTEND - STATE MANAGEMENT** (2 semanas)

**Objetivo**: Migrar de Composables Singleton a Pinia completo

#### 5.1 Crear Pinia Stores (Semana 12)

**Estructura target**:
```
frontend/src/stores/
├── auth.js          ✅ (Ya existe - 192 líneas)
├── books.js         🆕 (Migrar desde useBooks)
├── movies.js        🆕 (Migrar desde useMovies)
├── sessions.js      🆕 (Migrar desde useReadingSessions)
└── ui.js            🆕 (Modales, notificaciones, tema)
```

**stores/books.js** (350 líneas):
```javascript
import { defineStore } from 'pinia';
import { useAuthStore } from './auth';

export const useBooksStore = defineStore('books', {
  state: () => ({
    books: [],
    allowedStatuses: [],
    userTags: [],
    isLoading: false,
    error: null,
    searchResults: [],
    isSearching: false
  }),

  getters: {
    totalBooks: (state) => state.books.length,
    hasBooks: (state) => state.books.length > 0,
    booksWithRating: (state) => state.books.filter(b => b.user_rating > 0),
    booksByStatus: (state) => {
      // Agrupación por estado
    },
    averageRating: (state) => {
      // Cálculo de promedio
    }
  },

  actions: {
    async fetchBooks() {
      this.isLoading = true;
      this.error = null;
      
      try {
        const authStore = useAuthStore();
        const response = await authStore.authenticatedApiCall('get_library_items');
        
        if (response.data.status === 'success') {
          this.books = response.data.data.books || [];
        }
      } catch (err) {
        this.error = this._handleError(err);
      } finally {
        this.isLoading = false;
      }
    },

    async addBook(bookData, statuses = []) {
      // Implementación similar
    },

    _handleError(err, defaultMessage = 'Operation failed') {
      // Lógica centralizada de error handling
      if (err.response?.status === 401) return 'Authentication required';
      if (err.response?.status === 403) return 'Invalid CSRF token';
      return err.response?.data?.message || defaultMessage;
    }
  }
});
```

**stores/sessions.js** (250 líneas):
```javascript
export const useSessionsStore = defineStore('sessions', {
  state: () => ({
    activeSessions: [],
    sessionHistory: [],
    isLoading: false,
    error: null
  }),

  getters: {
    activeSessionsCount: (state) => state.activeSessions.length,
    hasActiveSessions: (state) => state.activeSessions.length > 0,
    getSessionByBook: (state) => (isbn) => 
      state.activeSessions.find(s => s.book_isbn === isbn)
  },

  actions: {
    async createSession(userId, isbn, startPage) {
      // Implementación
    },

    async completeSession(sessionId, endPage, reason) {
      // Implementación
    }
  }
});
```

**Checklist**:
- [ ] Crear stores/books.js (migrar desde useBooks)
- [ ] Crear stores/movies.js (migrar desde useMovies)
- [ ] Crear stores/sessions.js (migrar desde useReadingSessions)
- [ ] Crear stores/ui.js (estado UI global)
- [ ] Tests unitarios para stores (Vitest)
- [ ] Feature flag: `USE_PINIA_STORES=true`

#### 5.2 Refactorizar Composables (Semana 13)

**Convertir composables de "estado global" a "wrappers de Pinia"**:

```javascript
// composables/useBooks.js (refactorizado - 200 líneas)
import { storeToRefs } from 'pinia';
import { useBooksStore } from '@/stores/books';

export function useBooks() {
  const booksStore = useBooksStore();
  
  // ✅ Estado reactivo via storeToRefs
  const {
    books,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    totalBooks,
    hasBooks,
    booksWithRating
  } = storeToRefs(booksStore);

  // ✅ Actions del store
  const {
    fetchBooks,
    addBook,
    deleteBook,
    updateBookRating
  } = booksStore;

  // ✅ Helpers específicos (NO estado - pueden quedar aquí)
  const validateBookData = (bookData) => {
    if (!bookData.isbn) throw new Error('ISBN required');
    if (!bookData.title) throw new Error('Title required');
  };

  const transformGoogleBookData = (googleBook) => {
    return {
      isbn: googleBook.id,
      title: googleBook.volumeInfo.title,
      // ... transformación
    };
  };

  return {
    // Estado (reactivo)
    books,
    allowedStatuses,
    userTags,
    isLoading,
    error,
    
    // Getters
    totalBooks,
    hasBooks,
    booksWithRating,
    
    // Actions
    fetchBooks,
    addBook,
    deleteBook,
    updateBookRating,
    
    // Helpers (sin estado)
    validateBookData,
    transformGoogleBookData
  };
}
```

**Reducción**: useBooks.js 1,014L → 200L (-80%)

**Checklist**:
- [ ] Refactorizar useBooks como wrapper
- [ ] Refactorizar useMovies como wrapper
- [ ] Refactorizar useReadingSessions como wrapper
- [ ] Mantener composables utilitarios (useDebounce, useClipboard, etc.)
- [ ] Migrar componentes para usar stores directamente (opcional)
- [ ] Tests de integración

**Reducción total composables**: 4,200L → ~2,000L (-52%)

---

### **FASE 6: FRONTEND - COMPONENTES** (3 semanas)

**Objetivo**: Eliminar God Components, crear componentes reutilizables

#### 6.1 Refactorizar LibraryX (Semana 14)

**Dividir en subcomponentes**:

```
components/LibraryX/
├── LibraryX.vue (200 líneas) - Componente orquestador
├── SearchBar.vue (50 líneas)
├── Filters.vue (80 líneas)
├── SortingControls.vue (60 líneas)
├── UrlList.vue (150 líneas)
├── UrlAccordionItem.vue (100 líneas)
└── Pagination.vue (80 líneas)

composables/
├── useLibraryXData.js (100 líneas)
├── useLibraryXFilters.js (120 líneas)
└── useLibraryXPagination.js (80 líneas)
```

**LibraryX.vue refactorizado**:
```vue
<script setup>
import { useLibraryXData } from '@/composables/useLibraryXData';
import { useLibraryXFilters } from '@/composables/useLibraryXFilters';
import { useLibraryXPagination } from '@/composables/useLibraryXPagination';
import SearchBar from './LibraryX/SearchBar.vue';
import Filters from './LibraryX/Filters.vue';
import SortingControls from './LibraryX/SortingControls.vue';
import UrlList from './LibraryX/UrlList.vue';
import Pagination from './LibraryX/Pagination.vue';

const { urlData, isLoading, error, fetchUrls } = useLibraryXData();
const { filteredUrls, searchQuery, selectedDomains, currentSort, availableDomains } = 
  useLibraryXFilters(urlData);
const { paginatedUrls, currentPage, totalPages, goToPage, goToNextPage, goToPreviousPage } = 
  useLibraryXPagination(filteredUrls);

onMounted(() => fetchUrls());
</script>

<template>
  <div class="library-x-container">
    <h1>LibraryX - URL Manager</h1>
    
    <SearchBar v-model="searchQuery" />
    
    <Filters 
      v-model:selected="selectedDomains" 
      :available-domains="availableDomains"
    />
    
    <SortingControls v-model="currentSort" />
    
    <UrlList 
      :urls="paginatedUrls" 
      :loading="isLoading"
      :error="error"
    />
    
    <Pagination
      :current-page="currentPage"
      :total-pages="totalPages"
      @update:page="goToPage"
      @next="goToNextPage"
      @previous="goToPreviousPage"
    />
  </div>
</template>

<style scoped>
/* Solo estilos del container (50 líneas) */
</style>
```

**Reducción**: 1,034L → 200L + 520L componentes + 300L composables = **1,020L (-1.4%)** 
**Pero**: Código modular, reutilizable, testeable

#### 6.2 Búsquedas Genéricas (Semana 15)

**Crear SearchComponent.vue genérico**:

```vue
<!-- components/common/SearchComponent.vue (300 líneas) -->
<script setup>
const props = defineProps({
  title: String,
  itemType: String,               // 'book' | 'movie'
  searchFunction: Function,
  transformFunction: Function,
  itemComponent: Object
});

const searchComposable = useSearch({ debounceDelay: 500 });
const selectedItem = ref(null);

const search = async () => {
  await props.searchFunction(searchComposable.query.value);
};
</script>

<template>
  <div class="search-container">
    <h1>{{ title }}</h1>
    
    <slot name="search-input" :query="searchComposable.query" :search="search">
      <div class="input-group">
        <input v-model="searchComposable.query.value" @keyup.enter="search" />
        <button @click="search">🔍</button>
      </div>
    </slot>

    <div v-if="searchComposable.results.value?.length" class="results-list">
      <div v-for="result in searchComposable.results.value" :key="result.id || result.imdbID">
        <div class="result-item" @click="toggleItem(result.id || result.imdbID)">
          <slot name="result-preview" :item="result">
            <!-- Preview por defecto -->
          </slot>
        </div>
        
        <transition name="accordion">
          <div v-if="selectedItem === (result.id || result.imdbID)">
            <component 
              :is="itemComponent" 
              v-bind="transformFunction(result)"
            />
          </div>
        </transition>
      </div>
    </div>
  </div>
</template>
```

**Uso refactorizado**:
```vue
<!-- BookSearch.vue (150 líneas) -->
<template>
  <SearchComponent
    title="Buscador de Libros (Google Books API)"
    item-type="book"
    :search-function="searchBookByName"
    :transform-function="transformBookData"
    :item-component="LibraryBookItem"
  />
</template>

<!-- MovieSearch.vue (120 líneas) -->
<template>
  <SearchComponent
    title="Buscador de Películas (OMDb)"
    item-type="movie"
    :search-function="searchMovieByName"
    :transform-function="transformMovieData"
    :item-component="LibraryMovieItem"
  />
</template>
```

**Reducción**: 1,337L → 570L (-57%)

#### 6.3 Dashboards Genéricos (Semana 16)

**Crear GenericDashboard.vue**:

```vue
<!-- components/Dashboard/GenericDashboard.vue (350 líneas) -->
<script setup>
const props = defineProps({
  itemType: String,          // 'books' | 'movies'
  title: String,
  icon: String,
  statsService: Function,
  statsTransformer: Object
});

const stats = ref(null);
const loading = ref(true);

onMounted(async () => {
  stats.value = await props.statsService();
});

const statusChartData = computed(() => 
  props.statsTransformer.transformStatusData(stats.value)
);
</script>

<template>
  <div :class="`${itemType}-dashboard`">
    <DashboardHeader :title="title" :icon="icon" />
    <StatsGrid :stats="stats" :item-type="itemType" />
    <ChartsSection 
      :status-data="statusChartData"
      :ratings-data="ratingsChartData"
      :genres-data="genresChartData"
    />
  </div>
</template>
```

**Reducción**: 1,151L → 510L (-56%)

**Checklist completa Fase 6**:
- [ ] Dividir LibraryX en 6 subcomponentes + 3 composables
- [ ] Crear SearchComponent genérico
- [ ] Refactorizar BookSearch y MovieSearch
- [ ] Crear GenericDashboard
- [ ] Refactorizar BooksDashboard y MoviesDashboard
- [ ] Tests de componentes (Jest + Testing Library)
- [ ] Storybook para componentes genéricos

**Reducción total componentes**: 3,522L → 1,760L (-50%)

---

### **FASE 7: TESTING Y DOCUMENTACIÓN** (2 semanas)

**Objetivo**: Asegurar calidad y documentar arquitectura

#### 7.1 Testing Completo (Semana 17)

**Backend**:
- [ ] Tests unitarios de Value Objects (35 tests)
- [ ] Tests unitarios de Use Cases (50 tests)
- [ ] Tests de integración de repositorios (60 tests)
- [ ] Tests de controllers (40 tests)
- [ ] Tests de middleware (15 tests)
- **Total**: 200 tests backend

**Frontend**:
- [ ] Tests unitarios de Pinia stores (40 tests)
- [ ] Tests de composables (30 tests)
- [ ] Tests de componentes genéricos (25 tests)
- [ ] Tests de integración (20 tests)
- [ ] Tests E2E con Cypress (15 tests críticos)
- **Total**: 130 tests frontend

**Coverage esperado**: 85%+ backend, 80%+ frontend

#### 7.2 Documentación (Semana 18)

- [ ] **Architecture Decision Records (ADRs)** - 10 documentos
- [ ] **API Documentation** - Swagger/OpenAPI para 40+ actions
- [ ] **Component Library** - Storybook para componentes genéricos
- [ ] **Migration Guide** - Guía para futuros desarrolladores
- [ ] **Developer Onboarding** - README actualizado + setup scripts
- [ ] **Code Guidelines** - Estándares de código (PSR-12 backend, ESLint frontend)

---

## 📊 RESUMEN DE RESULTADOS ESPERADOS

### Métricas Finales

| Categoría | Antes | Después | Mejora |
|-----------|-------|---------|--------|
| **Backend - Repositorios (líneas)** | 3,681 | ~1,200 | **-67%** |
| **Backend - Use Cases (líneas)** | 1,360 | ~800 | **-41%** |
| **Backend - Controllers (líneas)** | 1,833 | ~1,000 | **-45%** |
| **Frontend - Components (líneas)** | 9,500 | ~5,000 | **-47%** |
| **Frontend - Composables (líneas)** | 4,200 | ~2,000 | **-52%** |
| **Tests totales** | ~50 | ~330 | **+560%** |
| **Code coverage** | ~40% | ~85% | **+112%** |
| **Código duplicado** | ~25% | <5% | **-80%** |
| **God Objects** | 4 | 0 | **-100%** |

### Tiempo Total Estimado

| Fase | Duración | Calendario |
|------|----------|-----------|
| Fase 0 - Preparación | 1 semana | Semana 1 |
| Fase 1 - VOs y Traits | 2 semanas | Semanas 2-3 |
| Fase 2 - Repositorios | 6 semanas | Semanas 4-9 |
| Fase 3 - Use Cases | 2 semanas | Semanas 10-11 |
| Fase 4 - Controllers | 2 semanas | Semanas 12-13 |
| Fase 5 - State Management | 2 semanas | Semanas 14-15 |
| Fase 6 - Componentes | 3 semanas | Semanas 16-18 |
| Fase 7 - Testing + Docs | 2 semanas | Semanas 19-20 |
| **TOTAL** | **20 semanas** | **~5 meses** |

### Equipo Recomendado

- **1 Backend Developer Senior** (Phases 1-4)
- **1 Frontend Developer Senior** (Phases 5-6)
- **1 Full-Stack Developer** (Support + Phase 7)
- **1 QA Engineer** (Phase 7 + continuous testing)

### Riesgos y Mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|--------|--------------|---------|-----------|
| Breaking changes en producción | Media | Alto | Feature flags + rollback plan |
| Regresiones no detectadas | Media | Alto | 330 tests + E2E coverage |
| Scope creep | Alta | Medio | Roadmap estricto + sprint planning |
| Pérdida de conocimiento | Baja | Alto | ADRs + documentación completa |
| Performance degradation | Baja | Medio | Benchmarks antes/después |

---

## 🎯 CRITERIOS DE ÉXITO

### Objetivos Técnicos
- ✅ 0 God Objects (componentes >1,000 líneas)
- ✅ 0 violaciones de SRP en repositorios
- ✅ 15+ Value Objects implementados
- ✅ <5% código duplicado
- ✅ 85%+ test coverage backend
- ✅ 80%+ test coverage frontend
- ✅ 330+ tests automatizados

### Objetivos de Negocio
- ✅ 0 bugs críticos introducidos
- ✅ Performance ±5% (no degradación significativa)
- ✅ Tiempo de onboarding nuevos devs: -50%
- ✅ Velocidad de desarrollo nuevas features: +30%

### Objetivos de Calidad
- ✅ Clean Architecture compliant
- ✅ SOLID principles en todo el código
- ✅ DRY (Don't Repeat Yourself) <5%
- ✅ Documentación completa (ADRs, API docs, Storybook)

---

## 📚 REFERENCIAS

### Documentos de Análisis
1. [ARCHITECTURE_ANALYSIS_INDEX.md](.github/ARCHITECTURE_ANALYSIS_INDEX.md)
2. [ARCHITECTURE_ANALYSIS_BOOKS.md](.github/ARCHITECTURE_ANALYSIS_BOOKS.md)
3. [ARCHITECTURE_ANALYSIS_MOVIES.md](.github/ARCHITECTURE_ANALYSIS_MOVIES.md)
4. [ARCHITECTURE_ANALYSIS_USERS.md](.github/ARCHITECTURE_ANALYSIS_USERS.md)
5. [ARCHITECTURE_ANALYSIS_DOMAIN.md](.github/ARCHITECTURE_ANALYSIS_DOMAIN.md)
6. [ARCHITECTURE_ANALYSIS_CONTROLLERS.md](.github/ARCHITECTURE_ANALYSIS_CONTROLLERS.md)
7. [ARCHITECTURE_ANALYSIS_FRONTEND_OVERVIEW.md](.github/ARCHITECTURE_ANALYSIS_FRONTEND_OVERVIEW.md)
8. [ARCHITECTURE_ANALYSIS_FRONTEND_STATE.md](.github/ARCHITECTURE_ANALYSIS_FRONTEND_STATE.md)
9. [ARCHITECTURE_ANALYSIS_FRONTEND_COMPONENTS.md](.github/ARCHITECTURE_ANALYSIS_FRONTEND_COMPONENTS.md)
10. [ARCHITECTURE_ANALYSIS_FRONTEND_COMPOSABLES.md](.github/ARCHITECTURE_ANALYSIS_FRONTEND_COMPOSABLES.md)

### Libros y Recursos
- "Clean Architecture" - Robert C. Martin
- "Domain-Driven Design" - Eric Evans
- "Refactoring" - Martin Fowler
- "Vue.js Design Patterns and Best Practices" - Paul Halliday
- PHP-DI Documentation: https://php-di.org/
- Pinia Documentation: https://pinia.vuejs.org/

---

**Última actualización**: 30 de noviembre de 2025  
**Versión**: 1.0  
**Estado**: Draft para revisión
