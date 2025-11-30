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
- **Frontend**: Vue.js 3 + PrimeVue + Pinia (no traditional stores/ folder - uses composables)
- **Backend**: PHP with Clean Architecture + DI Container (PHP-DI)
- **Database**: MySQL with Docker support
- **Auth**: Google OAuth integration

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
│   ├── Model/          # Domain entities (Book, Movie, User)
│   ├── Repository/     # Repository interfaces (UserRepositoryInterface)
│   └── UseCases/       # Business logic (AddBookUseCase, LoginUserUseCase)
├── Controllers/        # HTTP handlers - route actions to use cases
│   ├── BaseController.php     # Has handleRequest(), jsonResponse()
│   ├── BookController.php     # Implements handleRequest() for book actions
│   └── AuthController.php
├── Infrastructure/     # Technical implementations
│   ├── Persistence/   # MySqlBookRepository, MySqlUserRepository
│   ├── Logging/       # LoggingService (singleton), LoggerFactory
│   ├── Middleware/    # AuthMiddleware (session-based)
│   └── Database/      # DatabaseConnector (returns PDO)
└── Router/
    └── ActionRouter.php  # Maps actions to controller methods
```

### Dependency Injection Pattern

**Critical**: All dependencies are resolved via `config/dependencies.php`:

```php
// Repositories bind to interfaces
UserRepositoryInterface::class => DI\autowire(MySqlUserRepository::class)

// Use cases autowire their dependencies
AddBookUseCase::class => DI\autowire()
    ->constructorParameter('bookRepository', DI\get(BookRepositoryInterface::class))
```

**Never instantiate controllers/use cases manually** - let the DI container resolve them.

### Use Case Pattern

All business logic lives in **use cases** with a single `execute()` method:

```php
class AddBookUseCase {
    public function execute(array $bookData, int $userId): Book {
        // 1. Validate
        // 2. Execute business logic
        // 3. Return domain entity or throw exception
    }
}
```

Controllers call use cases, not repositories directly.

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
│   ├── Books/          # BookSearch.vue, BookCard.vue
│   ├── Movies/         # MovieSearch.vue
│   ├── Dashboard/      # BooksDashboard.vue, MoviesDashboard.vue
│   └── LibraryX.vue    # Main library view
├── composables/        # Vue composables (NO stores/ folder)
├── router/index.js     # Routes with meta.requiresAuth
├── services/           # API clients (axios)
└── main.js             # App entry, PrimeVue setup with custom theme
```

### State Management

**NO Pinia stores folder** - state management via:
1. **Composables** for shared state
2. **Component props/emits** for parent-child communication
3. **Session storage** for auth state

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

## Development Workflows

### Running the Application

**Docker (recommended)**:
```bash
docker-compose up -d
# Frontend: http://localhost:8080
# Backend: http://localhost:8888
# MySQL: localhost:3308
```

**Local development**:
```bash
# Frontend
cd frontend && npm run serve

# Backend
cd backend && php -S localhost:8000 -t public

# Database setup
docker-compose up mysql -d
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

1. Create use case in `Domain/UseCases/{Entity}/{ActionName}UseCase.php`
2. Register in `config/dependencies.php`
3. Add controller method in `Controllers/{Entity}Controller.php`
4. Map action in `Router/ActionRouter.php`

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
- **Session**: Session-based auth (not JWT) - `SessionManager` class

### Frontend

- **API calls**: Use axios with `VUE_APP_API_URL` env var
- **Auth check**: Router guards check `meta.requiresAuth`
- **Component naming**: PascalCase files, kebab-case in templates
- **Google OAuth**: Client ID from `VUE_APP_GOOGLE_CLIENT_ID`

## Environment Files

- Backend: `.env` (copy from `.env.docker-development` for Docker)
- Frontend: `.env.local` or env vars in `docker-compose.yml`

## Common Pitfalls

1. **Don't bypass DI container** - Use `$container->get(ClassName::class)` not `new ClassName()`
2. **Don't use REST paths** - Backend expects `action` parameter, not URL paths
3. **Don't forget logging setup** - Run `setup_logging.sh` or logs won't work
4. **Check bootstrap.php errors** - If DI fails, check `config/dependencies.php` registrations

## Key Files to Reference

- `backend/bootstrap.php` - Application initialization
- `backend/config/dependencies.php` - DI registrations
- `backend/src/Router/ActionRouter.php` - Action → controller mapping
- `frontend/src/main.js` - App setup, PrimeVue theme
- `docker-compose.yml` - Service configuration
