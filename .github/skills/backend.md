# Skill: Backend (PHP) — Library Vue

## Scope

This skill covers all backend development: Clean Architecture, CQRS with DTOs, DI container, controllers, use cases, repositories, middleware, external API integrations, and caching.

## Tech Stack

- **Language**: PHP 8.1+
- **Architecture**: Clean Architecture + Hexagonal + CQRS
- **DI Container**: PHP-DI (autowiring)
- **Logging**: Monolog (structured, multi-channel)
- **External APIs**: IGDB (Twitch OAuth), GoogleBooks, OpenLibrary
- **Cache**: File-based with TTL (`backend/storage/cache/`)
- **Auth**: Google OAuth + server-side sessions
- **No framework** — custom routing, middleware pipeline, DI setup

## Directory Structure

```
backend/
├── bootstrap.php                  # Loads .env, autoloader, error reporting, timezone, logging
├── public/index.php               # Entry point → Application::run()
├── src/
│   ├── Application.php            # CORS, session init, DI container, dispatches to ActionRouter
│   ├── Controllers/               # 7 controllers + Contracts/ interfaces
│   │   ├── BaseController.php     # successResponse(), errorResponse(), validateRequiredFields()
│   │   ├── BookController.php     # 15+ use case dependencies
│   │   ├── MovieController.php    # 8 use cases + tag/note repos
│   │   ├── GameController.php     # 8 use cases + IGDB service
│   │   ├── AuthController.php     # Login/logout/session
│   │   ├── LibraryController.php  # Cross-entity library operations
│   │   ├── LibraryXController.php # URL management
│   │   └── StatsController.php    # Statistics endpoints
│   ├── Domain/
│   │   ├── Model/                 # Entities: Book, Movie, Game, User, Work, Edition, etc.
│   │   │   └── ValueObjects/      # Email, ISBN, Rating, Genre, Status, Timestamp, etc.
│   │   ├── Repository/            # Interfaces organized by entity (Book/, Movie/, Game/, User/)
│   │   ├── DTO/
│   │   │   ├── Commands/          # 26 write DTOs (final readonly class + fromArray())
│   │   │   └── Queries/           # 14 read DTOs
│   │   ├── Services/              # External: IGDBService, GoogleBooksService, OpenLibraryService
│   │   │                          # Domain: WorkSearchService, BookImportService, UserLibraryStatisticsService
│   │   └── UseCases/              # 37 use cases organized by entity
│   │       ├── Books/ (15)
│   │       ├── Movies/ (12)
│   │       ├── Games/ (8)
│   │       ├── Auth/ (1)
│   │       └── Users/ (empty)
│   ├── Infrastructure/
│   │   ├── Persistence/           # MySQL repositories + Mappers/ per entity
│   │   │   ├── Book/ (10 repos + 4 mappers)
│   │   │   ├── Movie/ (4 repos + 1 mapper)
│   │   │   ├── Game/ (4 repos + 1 mapper)
│   │   │   └── User/ (1 repo + 1 mapper)
│   │   ├── Cache/CacheService.php # File-based caching with TTL
│   │   ├── Database/              # DatabaseConnector (PDO factory)
│   │   ├── Middleware/            # Auth, CSRF, Validation, Logging, Pipeline
│   │   ├── Auth/                  # GoogleOAuthVerifier
│   │   ├── Session/SessionManager.php
│   │   └── Logging/              # LoggingService (singleton), LoggerFactory
│   ├── Router/ActionRouter.php    # Maps actions → controller methods via match()
│   └── Services/ApplicationService.php
├── config/
│   ├── container.php              # DI container factory (returns closure) + all DI bindings
│   ├── routes.php                 # ~80 route definitions with middleware stacks
│   ├── logging.php                # Monolog configuration
│   └── helpers.php                # Helper functions (env(), config())
└── storage/
    ├── cache/                     # googlebooks/, openlibrary/, igdb_access_token.json
    ├── logs/                      # {channel}-YYYY-MM-DD.log
    ├── sessions/                  # PHP sessions
    └── uploads/                   # User uploads
```

## Request Flow

```
POST /index.php { "action": "add_game", "inputData": {...} }
  → bootstrap.php (env, autoload, logging)
  → Application::run()
    → CORS headers
    → Session start (LIBRARY_SESSION cookie, 7-day)
    → DI container from config/container.php
    → ActionRouter::dispatch(action, inputData)
      → Lookup route in config/routes.php
      → Build MiddlewarePipeline (Auth → CSRF → Validation → Logging)
      → Pipeline executes → final handler calls executeController()
      → match($action) → Controller::method(Command::fromArray($data))
        → UseCase::execute(Command) → Repository → DB
      → JSON response
```

## Action-Based Routing

All requests are `POST` with a JSON body containing `"action"`. No REST paths.

### Route Definition (`config/routes.php`)

```php
'add_game' => [
    'controller' => ['GameController', 'addGame'],
    'middleware' => [
        AuthenticationMiddleware::class,
        CSRFMiddleware::class,
        ValidationMiddleware::class,
        LoggingMiddleware::class,
    ],
    'validation' => ['gameData'],  // Required fields
],
```

### ActionRouter Dispatch (`Router/ActionRouter.php`)

```php
// Uses PHP 8.1 match expression
match ($action) {
    'add_game' => $this->gameController->addGame(
        AddGameCommand::fromArray($inputData)
    ),
    // ...80+ routes
};
```

## CQRS Pattern

### Commands (Write Operations)

All Commands are `final readonly class` with a static `fromArray()` factory:

```php
final readonly class AddGameCommand
{
    public function __construct(
        public array $gameData,
        public int $userId,
        public ?array $statuses = null,  // null = don't touch, [] = clear all
        public ?array $tags = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gameData: $data['gameData'] ?? $data['game_data'] ?? [],
            userId: (int) ($data['userId'] ?? $data['user_id']),
            statuses: $data['statuses'] ?? null,
            tags: $data['tags'] ?? null,
        );
    }
}
```

**Key conventions**:
- Handle both `camelCase` and `snake_case` in `fromArray()`
- Use `?array $statuses = null` (NOT `[]`) to distinguish "not sent" from "clear all"
- Frontend edit payloads nest data inside `$data['data']` sub-array

### Queries (Read Operations)

```php
final readonly class GetGamesByUserQuery
{
    public function __construct(public int $userId) {}
}
```

### Complete DTO Inventory

**Commands (26)**: AddBook, DeleteBook, UpdateBookRating, UpdateBookStatuses, EditUserBook, AddEditionNote, UpdateEditionNote, DeleteEditionNote, AddMovie, DeleteMovie, UpdateMovieRating, UpdateMovieStatuses, EditUserMovie, AddMovieNote, UpdateMovieNote, DeleteMovieNote, AddGame, DeleteGame, UpdateGameRating, UpdateGameStatuses, EditUserGame, CreateReadingSession, CompleteReadingSession, UpdateReadingProgress, ManageReadingSession, LoginUser

**Queries (14)**: GetBooksByUser, GetMoviesByUser, GetAllBooks, GetAllowedStatuses, GetTrendingBooks, GetTrendingMovies, GetTrendingGames, GetReadingSession, GetUserReadingStats, GetEditionNotes, GetEditionNote, GetMovieNotes, GetLibraryItems, GetLibrary

## Use Case Pattern

All use cases extend `AbstractUseCase` with Template Method:

```php
class AddGameUseCase extends AbstractUseCase
{
    public function __construct(
        private GameRepositoryInterface $gameRepository,
        private UserGameRepositoryInterface $userGameRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    // REQUIRED — missing this causes silent DI failure
    protected function getLogContext(): string
    {
        return 'AddGame';
    }

    protected function doExecute(mixed ...$args): mixed
    {
        $command = $args[0]; // AddGameCommand
        // 1. Validate
        // 2. Business logic
        // 3. Repository calls
        // 4. Return result
    }
}
```

**Critical**: `execute()` is `final` in AbstractUseCase — override `doExecute()` instead.

## Dependency Injection

### Configuration (`config/container.php`)

Returns a **closure** that builds the container:

```php
return function(): Container {
    $builder = new ContainerBuilder();
    $builder->addDefinitions([
        // PDO
        PDO::class => DI\factory([DatabaseConnector::class, 'getConnection']),
        
        // Repositories (MUST have BOTH entries)
        GameRepositoryInterface::class => DI\get(MySqlGameRepository::class),
        MySqlGameRepository::class => DI\autowire(),
        
        // Use Cases
        AddGameUseCase::class => DI\autowire()
            ->constructorParameter('gameRepository', DI\get(GameRepositoryInterface::class)),
        
        // Controllers with all dependencies
        GameController::class => DI\autowire()
            ->constructorParameter('addGameUseCase', DI\get(AddGameUseCase::class)),
    ]);
    return $builder->build();
};
```

### DI Resolution

```php
// In Application.php
$factory = require 'config/container.php';
$container = $factory();  // MUST invoke the closure
$router = $container->get(ActionRouter::class);
```

### DI Binding Rules

1. Every `*RepositoryInterface` needs **TWO entries**: interface → `DI\get(Implementation)` AND `Implementation → DI\autowire()`
2. **Never instantiate manually** — always `$container->get(ClassName::class)`
3. PHP-DI resolves ALL constructor dependencies **eagerly** — one broken dependency kills the entire controller

## Repository & Mapper Pattern

### Repository Interface (Domain layer)

```php
interface GameRepositoryInterface
{
    public function findByUser(int $userId): array;
    public function findById(int $gameId): ?Game;
    public function add(array $gameData, int $userId): Game;
    public function delete(int $gameId, int $userId): bool;
    public function updateRating(int $gameId, int $userId, float $rating): bool;
}
```

### MySQL Repository (Infrastructure layer)

```php
class MySqlGameRepository implements GameRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
        private GameDataMapper $mapper,
        private LoggerInterface $logger
    ) {}

    public function findByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT g.*, ug.* FROM games g JOIN user_games ug ...");
        $stmt->execute([$userId]);
        return $this->mapper->toDomainCollection($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
```

### Data Mapper

```php
class GameDataMapper
{
    public function toDomain(array $row): Game
    {
        return new Game(
            id: $this->extractInt($row, 'id'),
            title: $this->extractString($row, 'title'),
            dateStarted: $this->extractString($row, 'date_started', null),
            // EVERY column in SQL SELECT must be extracted here
        );
    }

    public function toDomainCollection(array $rows): array
    {
        return array_map([$this, 'toDomain'], $rows);
    }
}
```

**Critical**: If a column is in the SQL query but NOT extracted in the mapper, the field will be silently lost.

## Middleware Pipeline

### Available Middleware

| Middleware | Purpose | Interface |
|---|---|---|
| `AuthenticationMiddleware` | Validates `$_SESSION['user_data']['id']`, adds `user_id` to request | `MiddlewareInterface` |
| `CSRFMiddleware` | Validates CSRF token | `MiddlewareInterface` |
| `ValidationMiddleware` | Checks required fields (configured via route `validation` key) | `MiddlewareInterface` |
| `LoggingMiddleware` | Logs request/response | `MiddlewareInterface` |

### Pipeline Execution

```php
// MiddlewarePipeline uses array_reduce
// Each middleware calls $next($request) to continue or returns early
public function handle(array $request, callable $next): array
{
    // Pre-processing
    if (!valid) return ['error' => true, 'message' => '...'];
    
    $response = $next($request); // Continue pipeline
    
    // Post-processing
    return $response;
}
```

## External API Integrations

### IGDB (Games)

- **Service**: `Domain/Services/IGDBService.php`
- **Auth**: Twitch client credentials OAuth (token cached 60 days in `storage/cache/igdb_access_token.json`)
- **Endpoints**: Search, get by ID, get details (covers, platforms, genres)
- **Routes**: `igdb_config`, `igdb_token`, `igdb_search`, `igdb_get_by_id`, `igdb_get_details`

### GoogleBooks API

- **Service**: `Domain/Services/GoogleBooksService.php`
- **Auth**: API key from `GOOGLE_BOOKS_API_KEY` env var
- **Cache**: Responses cached in `storage/cache/googlebooks/`
- **Use**: ISBN lookup, book metadata enrichment

### OpenLibrary API

- **Service**: `Domain/Services/OpenLibraryService.php`
- **Auth**: None (public API)
- **Cache**: Responses cached in `storage/cache/openlibrary/`
- **Use**: Work/edition search, cover images

### Cache Service

```php
$cache = new CacheService('backend/storage/cache');
$cache->set('key', $data, 'namespace', 3600);  // namespace: googlebooks/, openlibrary/
$cached = $cache->get('key', 'namespace');       // Returns null if expired
```

## Adding a New Feature (Step by Step)

### 1. Create Command DTO

```php
// src/Domain/DTO/Commands/NewFeatureCommand.php
final readonly class NewFeatureCommand
{
    public function __construct(
        public int $entityId,
        public int $userId,
        public ?string $newField = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            entityId: (int) ($data['entityId'] ?? $data['entity_id']),
            userId: (int) ($data['userId'] ?? $data['user_id']),
            newField: $data['newField'] ?? $data['new_field'] ?? null,
        );
    }
}
```

### 2. Create Use Case

```php
// src/Domain/UseCases/{Entity}/NewFeatureUseCase.php
class NewFeatureUseCase extends AbstractUseCase
{
    public function __construct(
        private EntityRepositoryInterface $repository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    protected function getLogContext(): string { return 'NewFeature'; }

    protected function doExecute(mixed ...$args): mixed
    {
        $command = $args[0];
        return $this->repository->doSomething($command->entityId, $command->userId);
    }
}
```

### 3. Register in DI Container (`config/container.php`)

```php
NewFeatureUseCase::class => DI\autowire()
    ->constructorParameter('repository', DI\get(EntityRepositoryInterface::class)),
```

### 4. Add Controller Method

```php
// In EntityController.php
public function newFeature(NewFeatureCommand $command): array
{
    try {
        $result = $this->newFeatureUseCase->execute($command);
        return $this->successResponse(['data' => $result]);
    } catch (\Exception $e) {
        return $this->errorResponse($e->getMessage());
    }
}
```

### 5. Add Route (`config/routes.php`)

```php
'new_feature' => [
    'controller' => ['EntityController', 'newFeature'],
    'middleware' => [AuthenticationMiddleware::class, CSRFMiddleware::class, LoggingMiddleware::class],
],
```

### 6. Add to ActionRouter Match

```php
// In Router/ActionRouter.php executeController()
'new_feature' => $this->entityController->newFeature(
    NewFeatureCommand::fromArray($inputData)
),
```

## Data Flow for New Fields (Full Checklist)

When adding a field to an entity, update **every layer**:

1. **Database**: `ALTER TABLE` (see database skill)
2. **Repository SQL**: Add column to SELECT, INSERT, UPDATE queries
3. **Mapper `toDomain()`**: Extract column — **most commonly forgotten**
4. **Domain Model**: Add constructor parameter + include in `toArray()` with BOTH formats
5. **Command DTO**: Add parameter + handle in `fromArray()` with both camelCase/snake_case
6. **Use Case**: Pass new field from command to repository
7. **Frontend store**: Include in API payload
8. **Frontend component**: Create ref, watcher with `{ immediate: true }`

## Testing

### Overview

- **Framework**: PHPUnit 11.5, PHP 8.2, Docker
- **Test attributes**: `#[Test]` (NOT `@test` annotations)
- **Config**: `backend/phpunit.xml` (suites: Unit, Integration)
- **Current stats**: 743 tests, 2,071 assertions, 74 test files — ALL PASSING

### Running Tests

```bash
# Full test suite
docker compose exec -T backend vendor/bin/phpunit --testdox

# Specific test class
docker compose exec -T backend vendor/bin/phpunit --filter="AddBookUseCaseTest"

# Tests in a directory
docker compose exec -T backend vendor/bin/phpunit tests/Unit/Domain/UseCases/Books/

# Coverage (requires Xdebug)
docker compose exec -T backend vendor/bin/phpunit --coverage-text
```

### Test Directory Structure

```
backend/tests/Unit/
├── Domain/
│   ├── Model/                   # 8 entity tests (Book, Movie, Game, User, Work, Edition, etc.)
│   │   └── ValueObjects/        # 9 VO tests (Email, ISBN, Rating, Genre, Status, Timestamp, etc.)
│   ├── DTO/
│   │   ├── Commands/            # 6 command tests (BookCommands, GameCommands, MovieCommands, etc.)
│   │   └── Queries/             # 5 query tests (BookQueries, MovieQueries, EditionQueries, etc.)
│   └── UseCases/                # 38 use case tests organized by entity
│       ├── Books/ (15)          # CRUD + EditionNotes + ReadingProgress
│       ├── Games/ (8)           # CRUD + Rating + Statuses
│       ├── Movies/ (12)         # CRUD + MovieNotes + Rating + Statuses
│       ├── Auth/ (1)            # LoginUserUseCase
│       └── Library/ (2)         # GetLibrary, GetLibraryItems
└── Infrastructure/
    └── Persistence/             # 8 mapper tests (Book 5, Game 1, Movie 1, User 1)
```

### Testing Patterns by Layer

#### Value Object Tests
- Construction with valid and invalid data
- Validation rules (ISBN format, rating range 0-5, email format)
- Equality and immutability

#### Domain Model Tests
- Constructor validation
- `toArray()` / `fromArray()` round-trips
- Both camelCase and snake_case key support
- Edge cases and null handling

#### DTO Tests (Commands & Queries)
- `fromArray(array $data, int $userId)` — requires 2 arguments
- Readonly properties verified
- Both camelCase and snake_case input keys

#### UseCase Tests
Mock all repositories with `createMock()`, use `NullLogger`:

```php
class AddGameUseCaseTest extends TestCase
{
    private AddGameUseCase $useCase;
    private GameRepositoryInterface $gameRepo;

    protected function setUp(): void
    {
        $this->gameRepo = $this->createMock(GameRepositoryInterface::class);
        $this->useCase = new AddGameUseCase($this->gameRepo, new NullLogger());
    }

    #[Test]
    public function throws_on_invalid_command(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->useCase->execute(new \stdClass());
    }
}
```

**Standard test cases per UseCase**:
1. Throws `InvalidArgumentException` on wrong command type
2. Success path with expected return value
3. Validation failures (not found, duplicates, etc.)

#### Mocking UseCases with Final `execute()`
When a UseCase depends on another UseCase, `createMock()` won't work because `execute()` is `final`. Use Reflection:

```php
$mock = $this->getMockBuilder(GetBooksUseCase::class)
    ->disableOriginalConstructor()
    ->onlyMethods(['doExecute', 'getLogContext'])
    ->getMock();
$mock->method('doExecute')->willReturn([]);
$mock->method('getLogContext')->willReturn('Test');

$ref = new \ReflectionProperty(AbstractUseCase::class, 'logger');
$ref->setValue($mock, new NullLogger());
```

#### Mocking Final Classes
Final classes (e.g., `BookImportService`) cannot be mocked. Extract an interface first:
- `BookImportService` → `BookImportServiceInterface`
- UseCases depend on the interface, tests mock the interface

#### Mapper Tests
- `toDomain()` and `toDatabase()` conversions
- All fields mapped correctly
- Null/optional field handling
- Round-trip preservation (toDomain → toDatabase → toDomain)

### Test Maintenance Rules

**CRITICAL**: When modifying backend code, always ensure existing tests still pass:

| Change | Required Test Action |
|--------|---------------------|
| New UseCase | Create test in `tests/Unit/Domain/UseCases/{Entity}/` |
| New DTO | Add tests in corresponding Commands/Queries test file |
| New Value Object | Create test in `tests/Unit/Domain/Model/ValueObjects/` |
| Modified Domain Model | Update model test + verify mapper tests |
| Changed constructor | Update ALL tests that construct that class |
| New repository method | Mock in affected UseCase tests |
| Changed `fromArray()` | Verify signature: most use `fromArray(array $data, int $userId)` |
| Any change | Run: `docker compose exec -T backend vendor/bin/phpunit --testdox` |

### Test Pitfalls

1. **Edition constructor order**: `new Edition(int $workId, ?string $openlibraryEditionKey, string $title, ?int $editionId)` — NOT `(title, workId)`
2. **`fromArray()` requires 2 args**: `fromArray(array $data, int $userId)` — userId is a separate param
3. **Game/Movie `fromArray()` requires `userStatuses`**: Must be a non-empty array
4. **Final `execute()`**: Cannot mock — mock `doExecute()` + set logger via Reflection
5. **Final classes**: Cannot mock — extract interface first (e.g., `BookImportServiceInterface`)
6. **NullLogger**: Always use `Psr\Log\NullLogger` for logger dependencies in tests

### Adding a New Feature (with Tests)

After creating DTO + UseCase + Controller + Route (steps 1-6 above):

7. **Create UseCase test**: `tests/Unit/Domain/UseCases/{Entity}/{ActionName}UseCaseTest.php`
8. **Add DTO tests**: In `tests/Unit/Domain/DTO/Commands/` or `Queries/`
9. **If new Value Object**: Create test in `tests/Unit/Domain/Model/ValueObjects/`
10. **If mapper modified**: Verify mapper tests pass
11. **Run full suite**: `docker compose exec -T backend vendor/bin/phpunit --testdox`

## Logging

```php
// Get a channel logger
$logger = LoggingService::getInstance()->getLogger('games');
$logger->info('Game added', ['game_id' => $game->getId(), 'user_id' => $userId]);

// Channels: app, books, movies, games, auth, database, api
// Files: storage/logs/{channel}-YYYY-MM-DD.log
```

## Debugging & Diagnostics

```bash
# Test all controllers resolve via DI
docker compose exec -T backend php -r "
require '/var/www/html/bootstrap.php';
\$factory = require '/var/www/html/config/container.php';
\$container = \$factory();
\$controllers = [
    'BookController' => App\Controllers\BookController::class,
    'MovieController' => App\Controllers\MovieController::class,
    'GameController' => App\Controllers\GameController::class,
    'AuthController' => App\Controllers\AuthController::class,
];
foreach (\$controllers as \$name => \$class) {
    try { \$container->get(\$class); echo \$name . ': OK' . PHP_EOL; }
    catch (\Throwable \$e) { echo \$name . ': FAIL - ' . \$e->getMessage() . PHP_EOL; }
}
"

# PHP syntax check
docker compose exec -T backend php -l /var/www/html/src/Domain/UseCases/Games/AddGameUseCase.php

# Find UseCases missing getLogContext()
docker compose exec -T backend bash -c "
  grep -rl 'extends AbstractUseCase' /var/www/html/src/Domain/UseCases/ | xargs grep -L 'getLogContext'
"

# Apache error log (catches DI fatal errors not in structured logs)
docker compose exec backend tail -30 /var/log/apache2/error.log

# Structured logs
tail -50 backend/storage/logs/games-$(date +%Y-%m-%d).log

# Restart after code changes
docker compose restart backend
```

## Critical Pitfalls

1. **Missing `getLogContext()`**: Every UseCase extending `AbstractUseCase` MUST implement it — causes silent fatal error
2. **Missing DI bindings**: Need BOTH interface mapping AND implementation autowire entry
3. **Eager DI resolution**: One broken UseCase kills the ENTIRE controller instantiation
4. **Container path**: Backend mounted at `/var/www/html/` (NOT `/var/www/html/backend/`)
5. **Container.php returns a closure**: Must invoke `$factory()` to get the container
6. **Frontend nested data**: Edit payloads send `$data['data']` sub-array — Commands must extract from it
7. **Null vs empty array**: `?array $statuses = null` means "don't touch"; `[]` means "clear all"
8. **Silent failures**: If auth log exists but no completion log → DI resolution failure
9. **Mapper extraction**: Every SQL column MUST be extracted in mapper's `toDomain()` or data is silently lost
