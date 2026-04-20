# Library Vue - AI Coding Instructions

## Request Processing Rules

### Special Prompt Prefixes

**IMPORTANT**: Before processing any user request, check for these special prefixes:

#### 1. "Prompt:" Prefix
When a user request starts with **"Prompt:"**, follow this workflow:

1. **Extract the prompt content** (everything after "Prompt:")
2. **Apply prompt improvement guidelines** from `.github/Prompt_improvement.md`:
   - Clarify ambiguities and vague terms
   - Add project-specific context (architecture, patterns, file paths)
   - Break down complex requests into numbered steps
   - Use correct terminology from the codebase
   - Validate requirements and identify missing information
3. **Present the improved prompt** to the user with:
   - Clear objective
   - Specific files/components to modify
   - Expected outcome
   - Relevant architectural considerations
4. **Wait for user confirmation** before executing any changes

**Example:**
```
User: "Prompt: Add a feature to track user favorites"
Assistant: [Analyzes and presents improved prompt with specific steps]
Assistant: "¿Deseas que proceda con esta implementación?"
User: "Sí" / "No" / [modifications]
```

#### 2. "Result:" Prefix
When a user request starts with **"Result:"**, process it **normally** without prompt improvement:

1. Execute the request directly
2. Follow standard coding instructions below
3. No confirmation needed (unless the task is complex or destructive)

**Example:**
```
User: "Result: Show me the current BookController implementation"
Assistant: [Reads and displays the file directly]
```

### Default Behavior
If no special prefix is detected, process the request **normally** (same as "Result:" prefix).

---

## Architecture Overview

This is a **full-stack personal library management system** with:
- **Frontend**: Vue.js 3 + PrimeVue + Pinia stores + Composables
- **Backend**: PHP with Clean Architecture + CQRS + DI Container (PHP-DI)
- **Database**: MySQL with Docker support
- **Auth**: Google OAuth integration
- **External APIs**: IGDB (games via Twitch), GoogleBooks, OpenLibrary
- **Cache**: File-based caching with TTL for external API responses

## Backend Architecture (Clean Architecture + Hexagonal)

### Core Pattern: Action-Based Routing
The backend uses **action-based routing**, not RESTful routes. All requests go through `public/index.php`:

```php
// Request format: POST with 'action' parameter
{
  "action": "login",           // Action name determines which controller method runs
  "inputData": { ... }         // Payload
}
```

### Directory Structure & Layers

```
backend/src/
├── Domain/              # Pure business logic (entities, interfaces, use cases)
│   ├── Model/          # Domain entities (Book, Movie, Game, User)
│   ├── Repository/     # Repository interfaces (BookRepositoryInterface)
│   ├── DTO/            # CQRS pattern data transfer objects
│   │   ├── Commands/   # Write operations (AddBookCommand, UpdateGameRatingCommand)
│   │   └── Queries/    # Read operations (GetBooksByUserQuery, GetTrendingGamesQuery)
│   ├── Services/       # Domain services (IGDBService, GoogleBooksService, OpenLibraryService)
│   └── UseCases/       # Business logic (AddBookUseCase, LoginUserUseCase)
├── Controllers/        # HTTP handlers - route actions to use cases
│   ├── BaseController.php     # Has handleRequest(), jsonResponse()
│   ├── BookController.php     # Implements BookControllerInterface
│   ├── GameController.php     # Handles IGDB integration
│   ├── Contracts/      # Controller interfaces
│   └── AuthController.php
├── Infrastructure/     # Technical implementations
│   ├── Persistence/   # MySqlBookRepository, MySqlGameRepository, etc.
│   ├── Cache/         # CacheService (file-based with TTL)
│   ├── Logging/       # LoggingService (singleton), LoggerFactory
│   ├── Middleware/    # MiddlewarePipeline, AuthenticationMiddleware, CSRFMiddleware, etc.
│   ├── Auth/          # Authentication implementations
│   ├── Session/       # SessionManager
│   └── Database/      # DatabaseConnector (returns PDO)
└── Router/
    └── ActionRouter.php  # Maps actions to controller methods
```

### Dependency Injection Pattern

**Critical**: All dependencies are resolved via `config/container.php`:

```php
// Repositories: MUST have BOTH entries (interface mapping + implementation autowire)
GameRepositoryInterface::class => DI\get(MySqlGameRepository::class),
MySqlGameRepository::class => DI\autowire(),

// Use cases autowire their dependencies
AddGameUseCase::class => DI\autowire()
    ->constructorParameter('gameRepository', DI\get(GameRepositoryInterface::class))
```

**Never instantiate controllers/use cases manually** - let the DI container resolve them.

### Use Case Pattern

All business logic lives in **use cases** extending `AbstractUseCase` (Template Method pattern):

```php
class AddGameUseCase extends AbstractUseCase {
    public function __construct(
        private GameRepositoryInterface $gameRepository,
        private UserGameRepositoryInterface $userGameRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($logger);
    }

    // REQUIRED — missing this causes silent DI failure
    protected function getLogContext(): string { return 'AddGame'; }

    protected function doExecute(mixed ...$args): mixed {
        $command = $args[0]; // AddGameCommand DTO
        // 1. Validate command
        // 2. Execute business logic
        // 3. Return domain entity or throw exception
    }
}
```

**Note**: `execute()` is `final` in AbstractUseCase — override `doExecute()` instead.
Controllers call use cases, not repositories directly.

### CQRS Pattern with DTOs

The backend uses **Command Query Responsibility Segregation** via readonly DTOs:

**Commands** (Domain/DTO/Commands/) - Write operations:
```php
final readonly class AddBookCommand {
    public function __construct(
        public ISBN $isbn,
        public string $title,
        public int $userId,
        public array $statuses = [],
        public ?Rating $userRating = null,
        // ... typed properties with Value Objects
    ) {}

    public static function fromArray(array $data, int $userId): self {
        // Handles both camelCase and snake_case keys
    }
}
```

**Queries** (Domain/DTO/Queries/) - Read operations:
```php
final readonly class GetBooksByUserQuery {
    public function __construct(public int $userId) {}
}
```

Use cases accept DTOs, not raw arrays — ensures type safety and immutability.
Commands use Value Objects (`ISBN`, `Rating`, `Genre`) for validation, not plain arrays.

### Cache System

File-based caching (`Infrastructure/Cache/CacheService.php`) with TTL:

```php
// Cache external API responses
$cache->set('key', $data, 'namespace', 3600); // 1 hour TTL
$cached = $cache->get('key', 'namespace');
```

**Namespaces**: `googlebooks/`, `openlibrary/`, `igdb_access_token`
**Storage**: `backend/storage/cache/`
**Use case**: Reduce external API calls (IGDB, GoogleBooks, OpenLibrary)

### External API Integrations

**IGDB (Internet Game Database)** - via Twitch OAuth:
- Service: `Domain/Services/IGDBService.php`
- Auth: Client credentials flow with cached access token (60-day TTL)
- Endpoints: Game search, details, covers via proxy (avoids CORS)

**GoogleBooks API**:
- Service: `Domain/Services/GoogleBooksService.php`
- Features: ISBN search, book metadata

**OpenLibrary API**:
- Service: `Domain/Services/OpenLibraryService.php`
- Features: Work search, cover images

All services use `CacheService` to minimize API calls.

### Middleware Pipeline

Routes configured in `config/routes.php` with declarative middleware stacks:

```php
'add_book' => [
    'controller' => ['BookController', 'addBook'],
    'middleware' => [
        AuthenticationMiddleware::class,  // Check session
        CSRFMiddleware::class,            // Validate CSRF token
        ValidationMiddleware::class,      // Validate required fields
        LoggingMiddleware::class          // Log request
    ],
    'validation' => ['title', 'isbn']     // Required fields
]
```

**Available middleware**:
- `AuthenticationMiddleware` - Session validation
- `CSRFMiddleware` - CSRF protection
- `ValidationMiddleware` - Input validation
- `LoggingMiddleware` - Request/response logging
- `MiddlewarePipeline` - Executes middleware chain

### Logging System

Uses **Monolog** with structured logging via `LoggingService` (singleton):

```php
$logger = LoggingService::getInstance()->getLogger('books');
$logger->info('Book added', ['book_id' => $book->getId()]);
```

Channels: `app`, `books`, `auth`, `movies`, `database`
Config: `config/logging.php` (environment-specific settings)

## Frontend Architecture

### Component Organization

```
frontend/src/
├── components/
│   ├── Books/          # BookSearch.vue, EditionSelector.vue, EditionNotes.vue, etc.
│   ├── Movies/         # MovieSearch.vue, MovieNotes.vue, etc.
│   ├── Games/          # GameSearch.vue, GameNotes.vue, etc.
│   ├── Dashboard/      # UnifiedDashboard.vue, *Dashboard.vue, *DashboardContent.vue
│   │   └── shared/     # ChartCard.vue, StatCard.vue, DashboardHeader.vue
│   ├── common/         # Header.vue, Sidebar.vue, Layout.vue, StatusSelector.vue, TagSelector.vue
│   ├── shared/         # GenericSearch.vue, HorizontalCarousel.vue
│   ├── import/         # FileUploader.vue, ImportStatus.vue, ServiceSelector.vue
│   ├── EditItemModal.vue   # Shared edit modal for ALL entity types
│   ├── ImportModal.vue     # CSV/XML import
│   ├── MyLibrary.vue       # Unified library view
│   └── HomePage.vue        # Landing page
├── composables/        # 24 composables (UI wrappers around stores)
├── store/              # 8 Pinia stores (auth, books, movies, games, sessions, ui, menu)
├── views/              # BookDetailView.vue, MovieDetailView.vue, GameDetailView.vue, NotFoundView.vue
├── services/           # StatsService.js, ImportService.js, FileProcessorService.js
├── utils/              # logger.js, storeHelpers.js, languageConstants.js
├── router/index.js     # Routes with meta.requiresAuth
└── main.js             # App entry, PrimeVue setup with custom theme
```

### State Management

**Hybrid approach** - state management via:
1. **Pinia stores** (`store/`) - Core state logic (books, movies, games, auth, sessions)
2. **Composables** (`composables/`) - UI-specific wrappers around stores, reusable logic
3. **Component props/emits** - Parent-child communication
4. **Session storage** - Auth persistence

**Pattern**: Composables wrap store actions with UI-specific logic (confirmations, validations). Business logic stays in stores, UI helpers in composables.

### PrimeVue Custom Theme

Custom color preset defined in `main.js`:

```javascript
const CustomPreset = definePreset(Lara, {
  semantic: {
    primary: { 500: '#1D4E4A', ... }  // Teal color scheme
  }
});
```

Use PrimeVue components (MultiSelect, DataTable) - they're pre-configured.

## Key Application Features

### Reading Progress Tracking
- **Sessions**: Track reading sessions with start/end times
- **Progress**: Update current page and track reading speed
- **Statistics**: User reading stats and analytics
- **Use Cases**: `CreateReadingSessionCommand`, `UpdateReadingProgressCommand`
- **Frontend**: `useReadingProgress`, `useReadingSessions` composables

### CSV Import
- **Service**: `FileProcessorService.js` handles CSV parsing
- **Modal**: `ImportModal.vue` provides UI
- **Validation**: Backend validates book data before import
- **Composable**: `useFileImport` manages import workflow

### Multi-Status System
- **Books/Movies/Games**: Each item can have multiple user statuses
- **Examples**: "reading", "completed", "wishlist", "owned"
- **Management**: Status updates via dedicated commands (`UpdateBookStatusesCommand`)

### Trending Items
- **Feature**: Discover trending books, movies, games
- **Queries**: `GetTrendingBooksQuery`, `GetTrendingMoviesQuery`, `GetTrendingGamesQuery`
- **Frontend**: `useTrending` composable manages trending data

## Testing

### Test Suite Overview

The backend has a comprehensive PHPUnit test suite: **743 tests, 2,071 assertions** across **74 test files**.

- **Framework**: PHPUnit 11.5, PHP 8.2
- **Test attributes**: Uses `#[Test]` attributes (not `@test` annotations)
- **Config**: `backend/phpunit.xml`
- **Suites**: `Unit` (`tests/Unit/`) and `Integration` (`tests/Integration/`)

### Test Directory Structure

```
backend/tests/
├── Unit/
│   ├── Domain/
│   │   ├── Model/              # 12 tests: Book, Movie, Game, User, Work, Edition, EditionNote, UserBookEdition
│   │   │   └── ValueObjects/    # 9 tests: Email, ISBN, Rating, Genre, Status, GoogleId, GameIdentifier, MovieIdentifier, Timestamp
│   │   ├── DTO/
│   │   │   ├── Commands/        # 6 tests: BookCommands, GameCommands, MovieCommands, NoteCommands, ReadingSessionCommands, LoginUserCommand
│   │   │   └── Queries/         # 5 tests: BookQueries, MovieQueries, EditionQueries, ReadingQueries, LibraryQueries
│   │   └── UseCases/            # 38 tests organized by entity:
│   │       ├── Books/ (15)      # Add, Delete, Edit, Get, GetAll, GetAllowedStatuses, GetTrending, UpdateRating, UpdateStatuses
│   │       │                    # + EditionNotes (Add, Delete, Update, Get, GetAll) + UpdateReadingProgress
│   │       ├── Games/ (8)       # Add, Delete, Edit, Get, GetAllowedStatuses, GetTrending, UpdateRating, UpdateStatuses
│   │       ├── Movies/ (12)     # Add, Delete, Edit, Get, GetAllowedStatuses, GetTrending, UpdateRating, UpdateStatuses
│   │       │                    # + MovieNotes (Add, Delete, Update, GetNotes)
│   │       ├── Auth/ (1)        # LoginUserUseCase
│   │       └── Library/ (2)     # GetLibraryUseCase, GetLibraryItemsUseCase
│   └── Infrastructure/
│       └── Persistence/         # 8 mapper tests: Book (5 mappers), Game (1), Movie (1), User (1)
└── Integration/                 # (placeholder for future integration tests)
```

### Running Tests

```bash
# Run full test suite inside Docker
docker compose exec -T backend vendor/bin/phpunit --testdox

# Run specific test class
docker compose exec -T backend vendor/bin/phpunit --filter="AddBookUseCaseTest"

# Run tests in a directory
docker compose exec -T backend vendor/bin/phpunit tests/Unit/Domain/UseCases/Books/

# Run with coverage (requires Xdebug)
docker compose exec -T backend vendor/bin/phpunit --coverage-text
```

### Testing Patterns

#### UseCase Tests (Mock Dependencies)

UseCases follow Template Method pattern (`execute()` is `final`, override `doExecute()`).
All repository interfaces are mocked, logger uses `NullLogger`:

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

#### Testing UseCases that Depend on Other UseCases

When a UseCase depends on another UseCase (e.g., `GetLibraryItemsUseCase` depends on `GetBooksUseCase`),
you CANNOT use `createMock()` because `execute()` is `final`. Use Reflection to set the logger:

```php
$mock = $this->getMockBuilder(GetBooksUseCase::class)
    ->disableOriginalConstructor()
    ->onlyMethods(['doExecute', 'getLogContext'])
    ->getMock();
$mock->method('doExecute')->willReturn([]);
$mock->method('getLogContext')->willReturn('Test');

// Set logger via reflection so final execute() works
$ref = new \ReflectionProperty(AbstractUseCase::class, 'logger');
$ref->setValue($mock, new NullLogger());
```

#### Final Classes (e.g., BookImportService)

Final classes cannot be mocked directly. They MUST have interfaces:
- `BookImportService` → `BookImportServiceInterface`
- UseCases depend on the interface, not the concrete class
- Tests mock the interface

#### Standard Test Cases per UseCase

Every UseCase test should cover at minimum:
1. **Invalid command** — throws `InvalidArgumentException` on wrong type
2. **Success path** — happy path with expected return value
3. **Validation failures** — user not found, entity not found, duplicates, etc.

#### Domain Model / VO / DTO Tests

These test pure logic without mocks:
- Construction with valid/invalid data
- Value Object validation rules (ISBN format, rating range, etc.)
- `fromArray()` / `toArray()` round-trips
- Edge cases (null handling, both camelCase and snake_case keys)

#### Mapper Tests

Test `toDomain()` and `toDatabase()` conversions:
- All fields mapped correctly
- Null/optional field handling
- Round-trip (toDomain → toDatabase → toDomain preserves data)

### Test Maintenance Rules

**CRITICAL**: When modifying backend code, always ensure existing tests still pass:

1. **After any change**, run: `docker compose exec -T backend vendor/bin/phpunit --testdox`
2. **Adding a new UseCase** → Create corresponding test file in `tests/Unit/Domain/UseCases/{Entity}/`
3. **Adding a new DTO** → Add tests in the corresponding Commands/Queries test file
4. **Adding a new Value Object** → Create test in `tests/Unit/Domain/Model/ValueObjects/`
5. **Modifying a Domain Model** → Update model test + verify mapper tests still pass
6. **Changing constructor signatures** → Update ALL tests that construct that class
7. **Adding repository methods** → Mock the new method in affected UseCase tests
8. **Changing `fromArray()`** → Verify the method signature (most use `fromArray(array $data, int $userId)`)

### Common Test Pitfalls

1. **Edition constructor order**: `new Edition(int $workId, ?string $openlibraryEditionKey, string $title, ?int $editionId)` — NOT `(title, workId, editionId)`
2. **`fromArray()` requires 2 args**: Most Command DTOs use `fromArray(array $data, int $userId)` — userId is the second parameter, not inside the array
3. **Game/Movie `fromArray()` requires `userStatuses`**: Domain models `Game::fromArray()` and `Movie::fromArray()` require a non-empty `userStatuses` array
4. **Final `execute()` method**: Cannot mock `execute()` on UseCases — mock `doExecute()` + set logger via Reflection
5. **Final classes**: Cannot mock `final class` — extract interface first (e.g., `BookImportServiceInterface`)
6. **NullLogger**: Always use `Psr\Log\NullLogger` for logger dependencies in tests

## Development Workflows

### Running the Application

**All development uses Docker** - no local PHP/Node.js required:

```bash
# Start all services (frontend, backend, MySQL)
docker compose up -d

# Access points:
# Frontend: http://localhost:8080
# Backend: http://localhost:8888
# MySQL: localhost:3308

# View logs
docker compose logs -f [service_name]

# Stop services
docker compose down
```

### Backend Setup Commands

```bash
cd backend
composer install

# Setup logging (creates log directories, sets permissions)
./setup_logging.sh development
# OR on Windows: setup_logging.bat development

# Bootstrap initializes DI container
# Check bootstrap.php - it loads .env, sets error reporting, initializes LoggingService
```

### Adding New Features

**Backend (Use Case Approach)**:

1. Create DTO in `Domain/DTO/Commands/` or `Domain/DTO/Queries/`
2. Create use case in `Domain/UseCases/{Entity}/{ActionName}UseCase.php`
3. Register in `config/container.php` (use case + any new repository bindings)
4. Add controller method in `Controllers/{Entity}Controller.php`
5. Add route in `config/routes.php` with appropriate middleware stack
6. Map action in `Router/ActionRouter.php`
7. **Create unit tests**:
   - Add DTO tests in `tests/Unit/Domain/DTO/Commands/` or `Queries/`
   - Add UseCase test in `tests/Unit/Domain/UseCases/{Entity}/{ActionName}UseCaseTest.php`
   - If new Value Object created, add test in `tests/Unit/Domain/Model/ValueObjects/`
   - If mapper modified, verify mapper tests pass
8. **Run test suite**: `docker compose exec -T backend vendor/bin/phpunit --testdox`

**Frontend (Component Approach)**:

1. Create component in `components/{Entity}/`
2. Add route in `router/index.js`
3. Create API service method for backend communication

## Critical Conventions

### Backend

- **Action names**: lowercase with underscores (`add_book`, `check_auth`)
- **Response format**: Always JSON via `BaseController::jsonResponse()`
- **Error handling**: Controllers catch exceptions, return `['error' => true, 'message' => ...]`
- **Database**: PDO with prepared statements (lazy-loaded via DI)
- **Auth**: Hybrid session + JWT — PHP sessions (`SessionManager`) for backend state, JWT in `localStorage` + `Authorization` header for API calls
- **DTOs**: Use readonly Command/Query objects for use case parameters
- **Routes**: Define in `config/routes.php` with middleware stacks (not in ActionRouter)
- **Controller interfaces**: Controllers implement interfaces in `Controllers/Contracts/`

### Frontend

- **API calls**: Use axios with `VUE_APP_API_URL` env var
- **Auth check**: Router guards check `meta.requiresAuth`
- **Component naming**: PascalCase files, kebab-case in templates
- **Google OAuth**: Client ID from `VUE_APP_GOOGLE_CLIENT_ID`
- **State management**: Business logic in Pinia stores, UI helpers in composables
- **Store pattern**: Use `storeToRefs()` for reactive state access in composables
- **Item identifiers**: 
  - Books use `isbn` (ISBN-13 or ISBN-10)
  - Movies use `isbn` (TMDb ID or IMDb ID - legacy naming kept for consistency)
  - Games use `id` (IGDB ID - legacy naming kept for consistency across API calls)
  - **IMPORTANT**: Although semantically incorrect for Movies, `isbn` is used in all API calls to avoid massive refactoring

## Environment Files

- Backend: `.env` (copy from `.env.docker-development` for Docker)
- Frontend: `.env.local` or env vars in `docker compose.yml`

## Common Pitfalls

1. **Don't bypass DI container** - Use `$container->get(ClassName::class)` not `new ClassName()`
2. **Don't use REST paths** - Backend expects `action` parameter, not URL paths
3. **Don't forget logging setup** - Run `setup_logging.sh` or logs won't work
4. **Check bootstrap.php errors** - If DI fails, check `config/container.php` registrations
5. **AbstractUseCase requires `getLogContext()`** - Every UseCase extending `AbstractUseCase` MUST implement `protected function getLogContext(): string`. Missing this causes a **PHP Fatal Error** that silently prevents the entire controller from being instantiated via DI. The error won't show in normal logs — only in PHP CLI or Apache error.log.
6. **DI bindings for repository interfaces** - Every `*RepositoryInterface` used by a UseCase must have TWO entries in `config/container.php`: (1) interface → implementation mapping via `DI\get()`, and (2) implementation autowire via `DI\autowire()`. Missing either causes the controller to fail silently.
7. **PHP-DI resolves ALL constructor dependencies eagerly** - A broken dependency anywhere in the chain (e.g., a UseCase with a missing abstract method) kills the ENTIRE controller instantiation, not just the specific action that uses that UseCase.
8. **Frontend sends nested `data` in edit payloads** - The frontend `EditItemModal` sends `{gameId, userId, data: {personalRating, statuses, ...}, tags, notes}`. Backend Commands' `fromArray()` must extract from `$data['data']` sub-array, not from `$data` directly.
9. **Statuses nullable vs empty array** - Use `?array $statuses = null` (not `array $statuses = []`) in Commands to distinguish "user didn't send statuses" (null → don't touch) from "user cleared all statuses" (empty array → remove all).

## Data Flow & Debugging Guide

### Complete Data Flow for New Fields

When adding a new field (e.g., `date_started` to games), you must update **every layer**:

1. **Database Schema**
   - Add column to table: `ALTER TABLE user_games ADD COLUMN date_started DATE NULL;`
   - Verify with: `docker compose exec mysql mysql -u library_user -plibrary_pass library_db -e "DESCRIBE user_games;"`
   - **CRITICAL**: Code changes mean nothing if DB column doesn't exist

2. **Backend - Repository Layer**
   - Update SQL SELECT: Include new column in `MySql[Entity]Repository->findByUser()`
   - Update SQL INSERT/UPDATE: Add column to write operations
   - Example: `MySqlUserGameRepository.php` lines 44, 75, 140

3. **Backend - Mapper Layer** ⚠️ **MOST COMMONLY FORGOTTEN**
   - Update `[Entity]DataMapper->toDomain()`: Extract column from DB row
   - Example: `GameDataMapper.php` - must extract `date_started` from row and pass to domain constructor
   - Pattern: `$this->extractString($row, 'date_started', null)`

4. **Backend - Domain Model**
   - Add property to constructor: `public readonly ?string $dateStarted`
   - Update `toArray()`: Include field in serialization with BOTH formats (camelCase and snake_case)
   - Example: `'date_started' => $this->dateStarted, 'dateStarted' => $this->dateStarted`

5. **Backend - DTOs (Commands/Queries)**
   - Add parameter to Command: `public ?string $dateStarted = null`
   - Update `fromArray()`: Parse both camelCase and snake_case
   - Example: `EditUserGameCommand.php` - check both `$data['dateStarted']` and `$data['date_started']`

6. **Backend - Use Cases**
   - Pass new field from Command to Repository methods
   - Example: `AddGameUseCase->execute()` - pass `$command->dateStarted` to `repository->add()`

7. **Frontend - Store (Pinia)**
   - Update API call payload to include new field
   - Example: `games.js` - spread `updatedData` to include dates

8. **Frontend - Components**
   - Create reactive ref: `const dateStarted = ref(props.game.dateStarted || props.game.date_started || '')`
   - Add watcher with `{ immediate: true }` for prop changes
   - Update form binding: `v-model="dateStarted"`

### Debugging Data Flow Issues

When data doesn't appear in frontend after saving:

1. **Verify Database**
   ```bash
   docker compose exec mysql mysql -u library_user -plibrary_pass library_db \
     -e "SELECT * FROM user_games WHERE user_id=1 AND game_id=129964;"
   ```
   - If data is NOT in DB: Problem in Repository INSERT/UPDATE
   - If data IS in DB: Continue to next step

2. **Check Repository SQL**
   - Verify SELECT includes the column: `ug.date_started, ug.date_finished`
   - Check logs: `backend/storage/logs/database-*.log`

3. **Check Mapper Extraction** ⚠️ **CRITICAL STEP**
   - Open `Infrastructure/Persistence/[Entity]/Mappers/[Entity]DataMapper.php`
   - Verify `toDomain()` extracts the column from `$row`
   - Common mistake: Column in SQL but not extracted in mapper

4. **Check Domain Model Serialization**
   - Verify `toArray()` includes the field
   - Should have BOTH formats: `'date_started' => $this->dateStarted, 'dateStarted' => $this->dateStarted`

5. **Check API Response**
   - Browser DevTools → Network → Find the API call
   - Verify JSON response contains the field
   - If missing: Backend issue (mapper or toArray)
   - If present: Frontend issue (refs or watchers)

6. **Check Frontend Reactivity**
   - Verify ref initialization handles both formats: `props.game.dateStarted || props.game.date_started`
   - Add watcher with `{ immediate: true }` to react on mount
   - Check browser console for debug messages

### Database Credentials & Access

**Development Environment**:
- Host: `localhost` (or `mysql` from backend container)
- Port: `3308` (external) or `3306` (internal)
- Database: `library_db`
- User: `library_user`
- Password: `library_pass` (from `backend/.env`)

**Connecting to MySQL**:
```bash
# From host
docker compose exec mysql mysql -u library_user -plibrary_pass library_db

# Execute query
docker compose exec mysql mysql -u library_user -plibrary_pass library_db \
  -e "SELECT * FROM user_games LIMIT 5;"
```

### Logging Best Practices

**Backend Logging**:
- Use `LoggingService::getInstance()->getLogger('channel')` for structured logs
- Channels: `app`, `database`, `auth`, `api`, `frontend`
- `error_log()` writes to Apache error.log, NOT to structured logs
- Logs location: `backend/storage/logs/[channel]-YYYY-MM-DD.log`

**Reading Logs**:
```bash
# Recent API requests
tail -50 backend/storage/logs/api-2026-03-26.log

# Database errors
grep -i "error" backend/storage/logs/database-2026-03-26.log | tail -20

# Check timestamp - logs accumulate, old errors may not reflect current state
grep "2026-03-26T16:" backend/storage/logs/errors-2026-03-26.log
```

**Frontend Logging**:
- Use `Logger.debug()`, `Logger.info()`, `Logger.error()` from `utils/logger.js`
- Logs sent to backend endpoint for persistence
- Browser console shows frontend logs in real-time

### Database Schema Reference

**user_games table** (Games):
- `user_id` (int) - FK to users
- `game_id` (int unsigned) - FK to games
- `added_at` (timestamp) - When game was added
- `completed_at` (timestamp) - When game was completed
- `date_started` (date) - Start date
- `date_finished` (date) - Finish date
- `personal_rating` (decimal(2,1)) - User rating (0.0-5.0)
- `personal_notes` (text) - User notes
- `hours_played` (decimal(8,2)) - Hours played
- `platform_played` (varchar(100)) - Platform used

**user_game_statuses table** (Many-to-many):
- Links `user_games` to `game_statuses`
- Allows multiple statuses per game (owned, playing, completed, wishlist, etc.)

### Mapper Pattern Details

**Purpose**: Convert database rows to domain entities and vice versa

**Key Methods**:
- `toDomain(array $row): Entity` - Single row → domain object
- `toDomainCollection(array $rows): array` - Multiple rows → array of domain objects
- `toDatabase(Entity $entity): array` - Domain object → DB row (for INSERT/UPDATE)

**Critical Rules**:
1. Every column in SQL SELECT must be extracted in `toDomain()`
2. Field names use snake_case in DB, camelCase in domain
3. Use helper methods: `extractInt()`, `extractString()`, `extractFloat()`, `extractBool()`
4. Handle nulls gracefully: third parameter is default value

**Example** (GameDataMapper):
```php
public function toDomain(array $row): Game
{
    return new Game(
        // ... other parameters ...
        $this->extractString($row, 'date_started', null),    // Line 93
        $this->extractString($row, 'date_finished', null)    // Line 94
    );
}
```

### Container Restart Requirements

After backend code changes, restart to apply:
```bash
docker compose restart backend
```

After dependency or .env changes, rebuild:
```bash
docker compose down
docker compose up -d --build backend
```

**Verification**:
- Check logs: `docker compose logs backend | tail -20`
- Verify timestamp: Ensure log entries are AFTER restart time
- Test endpoint: Make API call and check response

### Useful Docker & Debugging Commands

**Docker container paths**:
- Backend source is mounted at `/var/www/html/` inside the container (NOT `/var/www/html/backend/`)
- Use `docker compose exec -T backend ls /var/www/html/` to verify structure

**PHP syntax checking inside container**:
```bash
# Check a single file
docker compose exec -T backend php -l /var/www/html/src/Domain/UseCases/Books/MyUseCase.php

# Check multiple files
for f in src/Domain/UseCases/Books/*.php; do
  docker compose exec -T backend php -l "/var/www/html/$f"
done
```

**Test DI container resolution (diagnose controller failures)**:
```bash
# IMPORTANT: container.php returns a factory closure, must invoke it with ()
docker compose exec -T backend php -r "
require '/var/www/html/bootstrap.php';
\$factory = require '/var/www/html/config/container.php';
\$container = \$factory();
echo 'Resolving BookController... ';
\$bc = \$container->get(App\Controllers\BookController::class);
echo 'OK' . PHP_EOL;
"
```

**Test all controllers at once**:
```bash
docker compose exec -T backend php -r "
require '/var/www/html/bootstrap.php';
\$factory = require '/var/www/html/config/container.php';
\$container = \$factory();
\$controllers = [
    'BookController' => App\Controllers\BookController::class,
    'MovieController' => App\Controllers\MovieController::class,
    'GameController' => App\Controllers\GameController::class,
    'AuthController' => App\Controllers\AuthController::class,
    'LibraryController' => App\Controllers\LibraryController::class,
    'LibraryXController' => App\Controllers\LibraryXController::class,
    'StatsController' => App\Controllers\StatsController::class,
];
foreach (\$controllers as \$name => \$class) {
    try { \$container->get(\$class); echo \$name . ': OK' . PHP_EOL; }
    catch (\Throwable \$e) { echo \$name . ': FAIL - ' . \$e->getMessage() . PHP_EOL; }
}
" 2>&1 | grep -E "^(Book|Movie|Game|Auth|Library|Stats)"
```

**Find UseCase classes missing abstract methods**:
```bash
# Find all classes extending AbstractUseCase that are missing getLogContext
docker compose exec -T backend bash -c "
  grep -rl 'extends AbstractUseCase' /var/www/html/src/Domain/UseCases/ | \
  xargs grep -L 'getLogContext'
"
```

**Composer commands (run inside backend container)**:
```bash
# Install dependencies
docker compose exec backend composer install

# Update dependencies
docker compose exec backend composer update

# Add a package
docker compose exec backend composer require vendor/package

# Dump autoload (after adding new classes/namespaces)
docker compose exec backend composer dump-autoload

# Check for autoloading issues
docker compose exec backend composer dump-autoload -o
```

**View Apache/PHP error logs (catches DI fatal errors not in structured logs)**:
```bash
docker compose exec backend cat /var/log/apache2/error.log | tail -30
# Or follow in real-time:
docker compose exec backend tail -f /var/log/apache2/error.log
```

### Diagnosing "Silent" Backend Failures

When an API call from the frontend gets an error response but no useful backend logs appear:

1. **Check if the controller can even be instantiated**:
   - Run the DI resolution test above
   - If it fails, the ENTIRE controller is broken — not just one action
   
2. **Common causes of DI resolution failure**:
   - Missing `getLogContext()` in a UseCase extending `AbstractUseCase`
   - Missing repository interface binding in `config/container.php`
   - Constructor parameter type mismatch
   
3. **Look at Apache error.log, not just structured logs**:
   - PHP Fatal Errors during DI resolution go to Apache error.log
   - Structured logs (`storage/logs/`) only capture errors AFTER the controller is instantiated
   
4. **The middleware pipeline still runs** even when the controller fails:
   - Auth and CSRF middleware pass → logs show "Request authorized"
   - Then controller resolution fails → no "Request completed" log
   - Pattern: auth log exists but no completion log = DI failure

## Key Files to Reference

- `backend/bootstrap.php` - Application initialization
- `backend/config/container.php` - DI registrations
- `backend/config/routes.php` - Route definitions with middleware stacks
- `backend/src/Router/ActionRouter.php` - Action → controller mapping
- `backend/src/Domain/DTO/` - Commands and Queries for CQRS
- `backend/src/Infrastructure/Cache/CacheService.php` - Caching implementation
- `backend/phpunit.xml` - PHPUnit configuration
- `backend/tests/Unit/` - All unit tests (74 files, 743 tests)
- `frontend/src/main.js` - App setup, PrimeVue theme
- `frontend/src/store/` - Pinia stores (books, games, movies, auth, sessions)
- `frontend/src/composables/` - UI-specific wrappers and reusable logic
- `docker-compose.yml` - Service configuration
