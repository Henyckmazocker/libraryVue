# 📋 ANÁLISIS DE ARQUITECTURA HEXAGONAL - LibraryVue (Users Module)

> **Fecha de análisis:** 30 de noviembre de 2025  
> **Módulo analizado:** Users (Usuarios)  
> **Archivo crítico:** `MySqlUserRepository.php` - 16 KB, 415 líneas  
> **Análisis previos:** [Books](./ARCHITECTURE_ANALYSIS_BOOKS.md) | [Movies](./ARCHITECTURE_ANALYSIS_MOVIES.md)

## 🎯 Resumen Ejecutivo

El módulo de Users es el **más limpio de los tres repositorios principales**, pero aún sufre de **violaciones de separación de responsabilidades**. A diferencia de Books y Movies, UserRepository tiene responsabilidades **que no le corresponden** relacionadas con la biblioteca del usuario (Books y Movies).

**Principales hallazgos:**
- ✅ **Tamaño razonable** - 415 líneas (vs 2,435 Books, 831 Movies)
- ✅ **Entidad User bien diseñada** - Con validaciones en setters
- ❌ **Responsabilidades de Books/Movies** mezcladas en UserRepository
- ❌ **Lógica de agregación** (estadísticas) en repositorio
- ❌ **Mismo logging duplicado** que Books y Movies
- ⚠️ **Falta de Value Objects** para email, googleId
- 📊 **Queries complejas** que deberían estar en Query Repositories

---

## 📊 COMPARATIVA: Users vs Books vs Movies

| Aspecto | Books | Movies | Users | Observación |
|---------|-------|--------|-------|-------------|
| **Tamaño** | 2,435 líneas | 831 líneas | 415 líneas | ✅ Users es el más pequeño |
| **Responsabilidades** | 8+ | 5+ | 4+ | ✅ Menos responsabilidades |
| **Métodos públicos** | 58+ | 24+ | 14 | ✅ Interfaz más limpia |
| **Entidad** | Validación básica | Sin validación | ✅ Con validación | Users mejor diseñado |
| **Logging duplicado** | ✅ Presente | ✅ Presente | ✅ Presente | ⚠️ Mismo problema |
| **Value Objects** | ❌ Ausentes | ❌ Ausentes | ❌ Ausentes | ⚠️ Mismo problema |
| **Mappers** | ❌ Ausentes | ❌ Ausentes | ❌ Ausentes | ⚠️ Mismo problema |
| **Query Repository** | ❌ No | ❌ No | ❌ No | ⚠️ Necesario para stats |

**Conclusión:** Users es el **repositorio más limpio**, pero tiene **responsabilidades que pertenecen a Books y Movies** (getUserBooks, getUserMovies, getUserLibraryStats).

---

## 🔴 PROBLEMAS CRÍTICOS

### 1. **Responsabilidades de Books/Movies en UserRepository**

#### **El problema:**
`MySqlUserRepository` tiene métodos que **NO deberían estar aquí**:

```php
// ❌ Métodos que pertenecen a UserBookRepository:
public function getUserBooks(int $userId, array $filters = []): array

// ❌ Métodos que pertenecen a UserMovieRepository:
public function getUserMovies(int $userId, array $filters = []): array
public function addUserMovie(int $userId, string $movieIsbn, ...): void

// ❌ Métodos que pertenecen a UserLibraryStatisticsService:
public function getUserLibraryStats(int $userId): array

// ❌ Métodos que pertenecen a UserBookRepository/UserMovieRepository:
public function hasUserBook(int $userId, string $isbn): bool
public function hasUserMovie(int $userId, string $movieId): bool
```

#### **Ubicación de violaciones:**

**`getUserBooks()` - líneas 199-242:**
```php
public function getUserBooks(int $userId, array $filters = []): array
{
    try {
        // ⚠️ Query compleja que une books + user_books + book_statuses
        $sql = "
            SELECT b.*, ub.added_at as user_added_at,
                   GROUP_CONCAT(bs.name SEPARATOR ', ') as user_statuses
            FROM books b
            INNER JOIN user_books ub ON b.isbn = ub.book_isbn
            LEFT JOIN user_book_statuses ubs ON b.isbn = ubs.book_isbn 
                AND ubs.user_id = :userId
            LEFT JOIN book_statuses bs ON ubs.status_id = bs.id
            WHERE ub.user_id = :userId
        ";
        
        // ⚠️ Lógica de filtrado
        if (isset($filters['status']) && !empty($filters['status'])) {
            $sql .= " AND bs.name = :status";
        }
        
        // ...
    }
}
```

**❌ Problema:**
1. UserRepository accede a tablas de Books (`books`, `user_books`, `book_statuses`)
2. Lógica de filtrado que no le corresponde
3. Acoplamiento entre User y Book

**`getUserLibraryStats()` - líneas 289-343:**
```php
public function getUserLibraryStats(int $userId): array
{
    try {
        $stats = [];
        
        // ⚠️ Count books by status
        $sqlBooks = "
            SELECT bs.name as status, COUNT(*) as count
            FROM user_books ub
            LEFT JOIN user_book_statuses ubs ON ...
            GROUP BY bs.name
        ";
        
        // ⚠️ Count movies by status
        $sqlMovies = "
            SELECT ms.name as status, COUNT(*) as count
            FROM user_movies um
            LEFT JOIN user_movie_statuses ums ON ...
            GROUP BY ms.name
        ";
        
        // ⚠️ Cálculos de agregación
        $stats['total_books'] = ...;
        $stats['total_movies'] = ...;
        
        return $stats;
    }
}
```

**❌ Problema:**
1. **Lógica de agregación** compleja en repositorio
2. UserRepository accede a tablas de Books Y Movies
3. Debería estar en un **Query Repository** o **Service**

---

#### **✅ Solución: Separar responsabilidades**

```php
// 1. UserRepository solo maneja Users:
interface UserRepositoryInterface {
    public function findByGoogleId(string $googleId): ?User;
    public function findById(int $id): ?User;
    public function findByEmail(string $email): ?User;
    public function save(User $user): User;
    public function update(User $user): User;
}

// 2. UserBookRepository maneja relación User-Book:
interface UserBookRepositoryInterface {
    public function findByUser(int $userId, array $filters): array;
    public function hasBook(int $userId, ISBN $isbn): bool;
    public function add(int $userId, ISBN $isbn, array $statuses): void;
    public function remove(int $userId, ISBN $isbn): bool;
}

// 3. UserMovieRepository maneja relación User-Movie:
interface UserMovieRepositoryInterface {
    public function findByUser(int $userId, array $filters): array;
    public function hasMovie(int $userId, MovieIdentifier $movieId): bool;
    public function add(int $userId, MovieIdentifier $movieId, array $statuses): void;
    public function remove(int $userId, MovieIdentifier $movieId): bool;
}

// 4. UserLibraryStatisticsService para agregaciones:
Domain/Services/UserLibraryStatisticsService.php

class UserLibraryStatisticsService {
    private UserBookRepository $userBookRepo;
    private UserMovieRepository $userMovieRepo;
    
    public function getUserLibraryStats(int $userId): UserLibraryStatistics {
        $bookStats = $this->userBookRepo->getStatsByUser($userId);
        $movieStats = $this->userMovieRepo->getStatsByUser($userId);
        
        return new UserLibraryStatistics(
            totalBooks: $bookStats['total'],
            totalMovies: $movieStats['total'],
            booksByStatus: $bookStats['by_status'],
            moviesByStatus: $movieStats['by_status']
        );
    }
}
```

---

### 2. **Logging Duplicado (Tercera Vez)**

**Ubicación:** `MySqlUserRepository.php` líneas 24-38

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

**❌ Problema:** Este es el **mismo código** en:
1. `MySqlBookRepository`
2. `MySqlMovieRepository`
3. `MySqlUserRepository` ← estamos aquí

**✅ Solución:** Ver [Books Analysis - Problema #7](./ARCHITECTURE_ANALYSIS_BOOKS.md#7-logging-duplicado-en-todos-los-repositorios) - Usar `LoggableTrait`.

---

### 3. **Falta de Hydrator/Mapper**

**Ubicación:** `MySqlUserRepository.php` líneas 171-197

```php
private function hydrateUser(array $userData): User
{
    return new User(
        id: isset($userData['id']) ? (int)$userData['id'] : null,
        googleId: $userData['google_id'],
        email: $userData['email'],
        name: $userData['name'],
        picture: $userData['picture'] ?? null,
        createdAt: isset($userData['created_at']) ? (int)$userData['created_at'] : null,
        updatedAt: isset($userData['updated_at']) ? (int)$userData['updated_at'] : null,
        lastLogin: isset($userData['last_login']) ? (int)$userData['last_login'] : null,
        preferences: isset($userData['preferences']) 
            ? json_decode($userData['preferences'], true) 
            : null,
        isActive: isset($userData['is_active']) ? (bool)$userData['is_active'] : true
    );
}
```

**❌ Problema:**
1. Lógica de hidratación privada (no reutilizable)
2. Mezcla de conversiones de tipos (`int`, `bool`, `json_decode`)
3. Debería estar en un Mapper dedicado

**✅ Solución:** Crear `UserDataMapper`:

```php
Infrastructure/Persistence/Mappers/UserDataMapper.php

class UserDataMapper {
    public function toDomain(array $dbRow): User {
        return new User(
            id: $this->extractInt($dbRow, 'id'),
            googleId: $dbRow['google_id'],
            email: $dbRow['email'],
            name: $dbRow['name'],
            picture: $dbRow['picture'] ?? null,
            createdAt: $this->extractInt($dbRow, 'created_at'),
            updatedAt: $this->extractInt($dbRow, 'updated_at'),
            lastLogin: $this->extractInt($dbRow, 'last_login'),
            preferences: $this->extractJson($dbRow, 'preferences'),
            isActive: $this->extractBool($dbRow, 'is_active', true)
        );
    }
    
    public function toPersistence(User $user): array {
        return [
            'id' => $user->getId(),
            'google_id' => $user->getGoogleId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'picture' => $user->getPicture(),
            'created_at' => $user->getCreatedAt(),
            'updated_at' => $user->getUpdatedAt(),
            'last_login' => $user->getLastLogin(),
            'preferences' => $user->getPreferences() 
                ? json_encode($user->getPreferences()) 
                : null,
            'is_active' => $user->isActive() ? 1 : 0
        ];
    }
    
    private function extractInt(array $data, string $key): ?int {
        return isset($data[$key]) ? (int)$data[$key] : null;
    }
    
    private function extractBool(array $data, string $key, bool $default = false): bool {
        return isset($data[$key]) ? (bool)$data[$key] : $default;
    }
    
    private function extractJson(array $data, string $key): ?array {
        if (!isset($data[$key]) || $data[$key] === null) {
            return null;
        }
        return json_decode($data[$key], true);
    }
}
```

---

## 🟡 PROBLEMAS MEDIOS

### 4. **Falta de Value Objects para User**

La entidad User usa tipos primitivos donde debería usar Value Objects:

| Primitivo actual | Value Object sugerido | Beneficios |
|-----------------|----------------------|-----------|
| `string $googleId` | `GoogleId` | Validación de formato Google ID |
| `string $email` | `Email` | Validación robusta, normalización |
| `array $preferences` | `UserPreferences` | Tipado, validación de estructura |
| `bool $isActive` | `AccountStatus` (enum) | Más expresivo que boolean |

#### **Ejemplo: Value Object Email**

```php
Domain/Model/ValueObjects/Shared/Email.php

final class Email {
    private string $value;
    
    public function __construct(string $email) {
        $normalized = $this->normalize($email);
        
        if (!$this->isValid($normalized)) {
            throw new InvalidArgumentException("Invalid email: {$email}");
        }
        
        $this->value = $normalized;
    }
    
    private function normalize(string $email): string {
        return strtolower(trim($email));
    }
    
    private function isValid(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public function toString(): string {
        return $this->value;
    }
    
    public function getDomain(): string {
        return substr($this->value, strpos($this->value, '@') + 1);
    }
    
    public function getLocalPart(): string {
        return substr($this->value, 0, strpos($this->value, '@'));
    }
    
    public function equals(Email $other): bool {
        return $this->value === $other->value;
    }
}
```

#### **Ejemplo: Value Object GoogleId**

```php
Domain/Model/ValueObjects/User/GoogleId.php

final class GoogleId {
    private string $value;
    
    public function __construct(string $googleId) {
        if (empty(trim($googleId))) {
            throw new InvalidArgumentException('Google ID cannot be empty');
        }
        
        // Google IDs suelen tener un formato específico (21 dígitos numéricos)
        if (!$this->isValid($googleId)) {
            throw new InvalidArgumentException("Invalid Google ID format: {$googleId}");
        }
        
        $this->value = $googleId;
    }
    
    private function isValid(string $googleId): bool {
        // Google ID típicamente es numérico de 21 dígitos
        return preg_match('/^\d{21}$/', $googleId) === 1;
    }
    
    public function toString(): string {
        return $this->value;
    }
    
    public function equals(GoogleId $other): bool {
        return $this->value === $other->value;
    }
}
```

#### **Ejemplo: Value Object UserPreferences**

```php
Domain/Model/ValueObjects/User/UserPreferences.php

final class UserPreferences {
    private array $preferences;
    
    // Preferencias por defecto
    private const DEFAULTS = [
        'theme' => 'light',
        'language' => 'en',
        'notifications_enabled' => true,
        'books_per_page' => 20,
        'default_view' => 'grid',
    ];
    
    public function __construct(array $preferences = []) {
        $this->preferences = array_merge(self::DEFAULTS, $preferences);
        $this->validate();
    }
    
    private function validate(): void {
        // Validar que theme sea válido
        if (!in_array($this->preferences['theme'], ['light', 'dark'], true)) {
            throw new InvalidArgumentException('Invalid theme');
        }
        
        // Validar que language sea válido
        if (!in_array($this->preferences['language'], ['en', 'es', 'fr'], true)) {
            throw new InvalidArgumentException('Invalid language');
        }
        
        // Validar que books_per_page sea un número válido
        if (!is_int($this->preferences['books_per_page']) 
            || $this->preferences['books_per_page'] < 10 
            || $this->preferences['books_per_page'] > 100) {
            throw new InvalidArgumentException('Books per page must be between 10 and 100');
        }
    }
    
    public function getTheme(): string {
        return $this->preferences['theme'];
    }
    
    public function getLanguage(): string {
        return $this->preferences['language'];
    }
    
    public function areNotificationsEnabled(): bool {
        return $this->preferences['notifications_enabled'];
    }
    
    public function getBooksPerPage(): int {
        return $this->preferences['books_per_page'];
    }
    
    public function getDefaultView(): string {
        return $this->preferences['default_view'];
    }
    
    public function toArray(): array {
        return $this->preferences;
    }
    
    public function withTheme(string $theme): self {
        $new = clone $this;
        $new->preferences['theme'] = $theme;
        $new->validate();
        return $new;
    }
    
    public function withLanguage(string $language): self {
        $new = clone $this;
        $new->preferences['language'] = $language;
        $new->validate();
        return $new;
    }
}
```

**Uso en User:**
```php
class User {
    private GoogleId $googleId;
    private Email $email;
    private UserPreferences $preferences;
    private AccountStatus $status;  // enum en lugar de bool
    
    public function __construct(
        ?int $id,
        GoogleId $googleId,
        Email $email,
        string $name,
        UserPreferences $preferences,
        AccountStatus $status = AccountStatus::ACTIVE
    ) {
        $this->id = $id;
        $this->googleId = $googleId;
        $this->email = $email;
        $this->name = $name;
        $this->preferences = $preferences;
        $this->status = $status;
    }
}
```

---

### 5. **Entidad User: Buenas Validaciones pero Mejorables**

La entidad User tiene validaciones en setters ✅, pero puede mejorarse:

```php
// ✅ Bueno: Validación en setter
public function setEmail(string $email): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email format');
    }
    $this->email = $email;
}

// ⚠️ Mejorable: Usar Value Object
public function setEmail(Email $email): void
{
    $this->email = $email; // La validación ya está en Email::__construct()
}
```

**Ventajas de usar VOs:**
1. **Validación automática** en construcción
2. **No puede existir un Email inválido** (estado imposible)
3. **Métodos de utilidad** (getDomain(), getLocalPart())
4. **Reutilización** en toda la aplicación

---

### 6. **Queries Complejas sin Optimización**

#### **Problema: N+1 potencial en getUserBooks/getUserMovies**

```php
// getUserBooks() ejecuta una query con GROUP_CONCAT
SELECT b.*, ub.added_at as user_added_at,
       GROUP_CONCAT(bs.name SEPARATOR ', ') as user_statuses
FROM books b
INNER JOIN user_books ub ON b.isbn = ub.book_isbn
LEFT JOIN user_book_statuses ubs ON ...
LEFT JOIN book_statuses bs ON ...
WHERE ub.user_id = :userId
GROUP BY b.isbn
```

**❌ Problemas:**
1. `GROUP_CONCAT` puede ser lento con muchos registros
2. Query compleja que debería estar en un **Query Repository**
3. No hay paginación (puede devolver miles de libros)

**✅ Solución:** Crear **Query Repository** para lectura optimizada:

```php
Infrastructure/Persistence/Query/UserLibraryQueryRepository.php

class UserLibraryQueryRepository {
    private PDO $db;
    
    public function getUserBooksWithStatuses(
        int $userId, 
        array $filters = [],
        int $page = 1,
        int $perPage = 20
    ): PaginatedResult {
        // Query optimizada con paginación
        $offset = ($page - 1) * $perPage;
        
        $sql = "
            SELECT 
                b.*,
                ub.added_at,
                JSON_ARRAYAGG(bs.name) as statuses
            FROM books b
            INNER JOIN user_books ub ON b.isbn = ub.book_isbn
            LEFT JOIN user_book_statuses ubs ON b.isbn = ubs.book_isbn 
                AND ubs.user_id = ub.user_id
            LEFT JOIN book_statuses bs ON ubs.status_id = bs.id
            WHERE ub.user_id = :userId
            {$this->buildFiltersClause($filters)}
            GROUP BY b.isbn, ub.added_at
            ORDER BY ub.added_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        // También obtener count total para paginación
        $totalSql = "SELECT COUNT(*) FROM user_books WHERE user_id = :userId";
        
        // ...
    }
    
    public function getUserLibraryStats(int $userId): array {
        // Query optimizada para estadísticas
        $sql = "
            SELECT 
                'books' as type,
                bs.name as status,
                COUNT(*) as count
            FROM user_books ub
            LEFT JOIN user_book_statuses ubs ON ub.book_isbn = ubs.book_isbn 
                AND ub.user_id = ubs.user_id
            LEFT JOIN book_statuses bs ON ubs.status_id = bs.id
            WHERE ub.user_id = :userId
            GROUP BY bs.name
            
            UNION ALL
            
            SELECT 
                'movies' as type,
                ms.name as status,
                COUNT(*) as count
            FROM user_movies um
            LEFT JOIN user_movie_statuses ums ON um.movie_isbn = ums.movie_isbn 
                AND um.user_id = ums.user_id
            LEFT JOIN movie_statuses ms ON ums.status_id = ms.id
            WHERE um.user_id = :userId
            GROUP BY ms.name
        ";
        
        // ...
    }
}
```

---

### 7. **Falta de Enum para AccountStatus**

Actualmente usa `bool $isActive`:

```php
private bool $isActive;

public function setActive(bool $isActive): void {
    $this->isActive = $isActive;
}
```

**❌ Problema:** `bool` es limitado para estados de cuenta (solo activo/inactivo).

**✅ Solución:** Usar **Enum** (PHP 8.1+):

```php
Domain/Model/ValueObjects/User/AccountStatus.php

enum AccountStatus: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
    case BANNED = 'banned';
    case PENDING_VERIFICATION = 'pending_verification';
    
    public function isActive(): bool {
        return $this === self::ACTIVE;
    }
    
    public function canLogin(): bool {
        return match($this) {
            self::ACTIVE => true,
            self::INACTIVE, self::SUSPENDED, self::BANNED, 
            self::PENDING_VERIFICATION => false,
        };
    }
    
    public function getLabel(): string {
        return match($this) {
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::SUSPENDED => 'Suspended',
            self::BANNED => 'Banned',
            self::PENDING_VERIFICATION => 'Pending Verification',
        };
    }
}

// Uso en User:
class User {
    private AccountStatus $status;
    
    public function suspend(string $reason): void {
        $this->status = AccountStatus::SUSPENDED;
        // Emitir evento: UserSuspendedEvent
    }
    
    public function ban(string $reason): void {
        $this->status = AccountStatus::BANNED;
        // Emitir evento: UserBannedEvent
    }
    
    public function activate(): void {
        $this->status = AccountStatus::ACTIVE;
        // Emitir evento: UserActivatedEvent
    }
}
```

---

## 🟢 PUNTOS FUERTES (A MANTENER)

✅ **Tamaño más manejable** - 415 líneas (vs 2,435 Books)  
✅ **Interfaz limpia** - Solo 14 métodos (aunque 5 no deberían estar)  
✅ **Entidad con validaciones** - Mejor que Book y Movie  
✅ **Separación clara** de `save()` vs `update()`  
✅ **Queries parametrizadas** - Prevención de SQL injection  
✅ **Manejo de errores** con try-catch y logging  
✅ **Timestamps automáticos** - `updateLastLogin()`  

---

## 📐 PROPUESTA DE REFACTORIZACIÓN

### **Fase 1: Separar Responsabilidades - Semana 1**

**Objetivo:** Mover responsabilidades de Books/Movies fuera de UserRepository

```
Infrastructure/Persistence/
├── User/
│   ├── MySqlUserRepository.php              (~150 líneas)
│   │   ├── findByGoogleId(GoogleId): ?User
│   │   ├── findById(int): ?User
│   │   ├── findByEmail(Email): ?User
│   │   ├── save(User): User
│   │   └── update(User): User
│   │
│   └── Mappers/
│       └── UserDataMapper.php               (~80 líneas)
│
├── UserBook/ (MOVER desde BookRepository)
│   └── MySqlUserBookRepository.php          (~120 líneas)
│       ├── findByUser(int, array): array
│       ├── hasBook(int, ISBN): bool
│       ├── add(int, ISBN, array): void
│       └── remove(int, ISBN): bool
│
├── UserMovie/ (MOVER desde MovieRepository)
│   └── MySqlUserMovieRepository.php         (~120 líneas)
│       ├── findByUser(int, array): array
│       ├── hasMovie(int, MovieIdentifier): bool
│       ├── add(int, MovieIdentifier, array): void
│       └── remove(int, MovieIdentifier): bool
│
└── Query/
    └── UserLibraryQueryRepository.php       (~150 líneas)
        ├── getUserBooksWithStatuses(int, array, int, int): PaginatedResult
        ├── getUserMoviesWithStatuses(int, array, int, int): PaginatedResult
        └── getUserLibraryStats(int): UserLibraryStatistics
```

**Métricas de mejora:**
- MySqlUserRepository: 415 → ~150 líneas ✅ (reducción del 64%)
- Responsabilidades: 4 → 1 ✅
- Métodos públicos: 14 → 5 ✅

---

### **Fase 2: Crear Value Objects - Semana 1-2**

```php
Domain/Model/ValueObjects/
├── Shared/
│   └── Email.php                 (compartido con Books/Movies para author emails)
│
└── User/
    ├── GoogleId.php
    ├── UserPreferences.php
    └── AccountStatus.php (enum)
```

---

### **Fase 3: Crear UserDataMapper - Semana 2**

```php
Infrastructure/Persistence/Mappers/UserDataMapper.php

class UserDataMapper {
    public function toDomain(array $dbRow): User {
        return new User(
            id: $this->extractInt($dbRow, 'id'),
            googleId: new GoogleId($dbRow['google_id']),
            email: new Email($dbRow['email']),
            name: $dbRow['name'],
            picture: $dbRow['picture'] ?? null,
            createdAt: $this->extractInt($dbRow, 'created_at'),
            updatedAt: $this->extractInt($dbRow, 'updated_at'),
            lastLogin: $this->extractInt($dbRow, 'last_login'),
            preferences: new UserPreferences(
                $this->extractJson($dbRow, 'preferences') ?? []
            ),
            status: AccountStatus::from($dbRow['is_active'] ? 'active' : 'inactive')
        );
    }
    
    public function toPersistence(User $user): array {
        return [
            'id' => $user->getId(),
            'google_id' => $user->getGoogleId()->toString(),
            'email' => $user->getEmail()->toString(),
            'name' => $user->getName(),
            'picture' => $user->getPicture(),
            'created_at' => $user->getCreatedAt(),
            'updated_at' => $user->getUpdatedAt(),
            'last_login' => $user->getLastLogin(),
            'preferences' => json_encode($user->getPreferences()->toArray()),
            'is_active' => $user->getStatus()->isActive() ? 1 : 0
        ];
    }
}
```

---

### **Fase 4: Crear Query Repository - Semana 2**

```php
Infrastructure/Persistence/Query/UserLibraryQueryRepository.php

class UserLibraryQueryRepository {
    private PDO $db;
    
    public function getUserBooksWithStatuses(
        int $userId,
        array $filters = [],
        int $page = 1,
        int $perPage = 20
    ): PaginatedResult {
        // Query optimizada con paginación
        // ...
    }
    
    public function getUserLibraryStats(int $userId): UserLibraryStatistics {
        // Query optimizada para estadísticas
        // Retorna Value Object en lugar de array
        // ...
    }
}

// Value Object para estadísticas:
Domain/Model/ValueObjects/User/UserLibraryStatistics.php

class UserLibraryStatistics {
    public function __construct(
        private int $totalBooks,
        private int $totalMovies,
        private array $booksByStatus,
        private array $moviesByStatus
    ) {}
    
    public function getTotalItems(): int {
        return $this->totalBooks + $this->totalMovies;
    }
    
    public function getBooksCount(): int {
        return $this->totalBooks;
    }
    
    public function getMoviesCount(): int {
        return $this->totalMovies;
    }
    
    public function getCompletionPercentage(): float {
        $completed = ($this->booksByStatus['completed'] ?? 0) 
                   + ($this->moviesByStatus['watched'] ?? 0);
        $total = $this->getTotalItems();
        
        return $total > 0 ? ($completed / $total) * 100 : 0.0;
    }
    
    public function toArray(): array {
        return [
            'total_books' => $this->totalBooks,
            'total_movies' => $this->totalMovies,
            'books_by_status' => $this->booksByStatus,
            'movies_by_status' => $this->moviesByStatus,
            'completion_percentage' => $this->getCompletionPercentage()
        ];
    }
}
```

---

### **Fase 5: Actualizar Interfaces - Semana 2**

**UserRepositoryInterface (reducida y limpia):**
```php
interface UserRepositoryInterface {
    public function findByGoogleId(GoogleId $googleId): ?User;
    public function findById(int $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function save(User $user): User;
    public function update(User $user): User;
}
```

**Nueva interfaz para queries:**
```php
interface UserLibraryQueryRepositoryInterface {
    public function getUserBooksWithStatuses(
        int $userId,
        array $filters,
        int $page,
        int $perPage
    ): PaginatedResult;
    
    public function getUserMoviesWithStatuses(
        int $userId,
        array $filters,
        int $page,
        int $perPage
    ): PaginatedResult;
    
    public function getUserLibraryStats(int $userId): UserLibraryStatistics;
}
```

---

## 📊 MÉTRICAS DE MEJORA ESPERADAS

| Métrica | Antes | Después | Mejora |
|---------|-------|---------|--------|
| Líneas en UserRepository | 415 | ~150 | 📉 64% |
| Tamaño del archivo | 16 KB | ~8 KB | 📉 50% |
| Métodos públicos en UserRepository | 14 | 5 | 📉 64% |
| Métodos en UserRepositoryInterface | 14 | 5 | 📉 64% |
| Responsabilidades por clase | 4 | 1 | 📉 75% |
| Acoplamiento con Books/Movies | Alto | Cero | ✅ |
| Queries optimizadas (paginación) | No | Sí | ✅ |
| Value Objects usados | 0 | 4 | ✅ |

---

## 🎯 PRIORIDADES DE REFACTORIZACIÓN

### **🔴 URGENTE (Semana 1):**
1. ✅ **Mover métodos de Books/Movies**
   - `getUserBooks()` → `UserBookRepository`
   - `getUserMovies()` → `UserMovieRepository`
   - `getUserLibraryStats()` → `UserLibraryQueryRepository`
   
2. ✅ **Extraer LoggableTrait**
   - Reutilizar de Books/Movies

### **🟡 IMPORTANTE (Semana 2):**
3. ✅ **Implementar Value Objects**
   - `Email` (compartido)
   - `GoogleId`
   - `UserPreferences`
   - `AccountStatus` (enum)
   
4. ✅ **Crear UserDataMapper**
   - Mover lógica de `hydrateUser()`

### **🟢 DESEABLE (Semana 2-3):**
5. ✅ **Crear Query Repository**
   - Optimizar queries complejas
   - Agregar paginación
   
6. ✅ **Crear UserLibraryStatistics VO**
   - Retornar objetos tipados

---

## 🏗️ ARQUITECTURA OBJETIVO

```
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE PRESENTACIÓN                      │
│  └── AuthController (login, register)                       │
└──────────────────────────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE APLICACIÓN                        │
│  └── UseCases/                                               │
│      └── Auth/LoginUserUseCase                               │
└──────────────────────────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                    CAPA DE DOMINIO                           │
│  ├── Model/                                                  │
│  │   ├── Entities/User.php                                  │
│  │   └── ValueObjects/                                      │
│  │       ├── User/                                          │
│  │       │   ├── GoogleId.php                               │
│  │       │   ├── UserPreferences.php                        │
│  │       │   ├── AccountStatus.php (enum)                   │
│  │       │   └── UserLibraryStatistics.php                  │
│  │       └── Shared/                                        │
│  │           └── Email.php (compartido)                     │
│  │                                                           │
│  └── Repository/ (interfaces)                               │
│      ├── UserRepositoryInterface                            │
│      └── UserLibraryQueryRepositoryInterface                │
└──────────────────────────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────┐
│                 CAPA DE INFRAESTRUCTURA                      │
│  └── Persistence/                                            │
│      ├── User/                                               │
│      │   ├── MySqlUserRepository.php (~150 líneas)           │
│      │   └── Mappers/UserDataMapper.php                      │
│      ├── Query/                                              │
│      │   └── UserLibraryQueryRepository.php                  │
│      └── Concerns/ (compartido con Books, Movies)            │
│          └── LoggableTrait.php                               │
└──────────────────────────────────────────────────────────────┘
```

---

## 📌 Conclusiones Clave

1. ✅ **UserRepository es el más limpio** - Buen punto de partida
2. ❌ **Responsabilidades de Books/Movies mezcladas** - Principal problema
3. ✅ **Entidad User bien diseñada** - Mejor que Book/Movie
4. 🎯 **Query Repository necesario** - Para queries complejas y stats
5. 📈 **Value Objects mejorarán robustez** - Email, GoogleId, Preferences

**Ventaja estratégica:** User es simple y puede refactorizarse rápidamente (1-2 semanas).

---

## 🔗 Referencias y Próximos Análisis

- [Books Module Analysis](./ARCHITECTURE_ANALYSIS_BOOKS.md)
- [Movies Module Analysis](./ARCHITECTURE_ANALYSIS_MOVIES.md)
- **Próximos análisis:**
  - [ ] Use Cases (validación de patrones)
  - [ ] Domain Objects (entidades y VOs)
  - [ ] Controllers (capa de presentación)
  - [ ] Frontend (Vue.js)

---

*Fecha de última actualización: 30 de noviembre de 2025*
