# Prompt Improvement Guidelines

This guide helps transform vague user requests into clear, actionable prompts that align with the libraryVue project architecture and conventions.

## Core Principles

### 1. Clarify Ambiguities
Transform vague terms into specific technical requirements:

**Vague:** "Add search functionality"
**Improved:** "Implement book title search using the existing `GoogleBooksService` with results cached via `CacheService`, exposing a new action `search_books` through `BookController`"

### 2. Add Project-Specific Context

Always reference the actual project architecture:

- **Backend**: Use cases, DTOs (Commands/Queries), repositories, services
- **Frontend**: Pinia stores, composables, PrimeVue components
- **Patterns**: CQRS, DI container, action-based routing, middleware pipeline
- **Testing**: PHPUnit tests (743 tests, 74 files) — every backend change must include test updates

**Example:**
- Instead of: "Add a button to delete books"
- Use: "Add delete functionality in `BookCard.vue` component that calls `useBooks` composable's `deleteBook` method, which triggers the `delete_book` action via `BookController`, executing `DeleteBookCommand` through `DeleteBookUseCase`"

### 3. Break Down Complex Requests

Structure multi-step work into numbered, dependency-aware steps:

**Vague:** "Add game rating support"
**Improved:**
1. Create `UpdateGameRatingCommand` DTO in `backend/src/Domain/DTO/Commands/`
2. Implement `UpdateGameRatingUseCase` with validation (1-10 scale)
3. Register use case in `config/container.php`
4. Add `update_game_rating` action to `GameController`
5. Add route in `config/routes.php` with `AuthenticationMiddleware` and `ValidationMiddleware`
6. Update `useGames` composable with `updateRating` method
7. Add rating UI to `GameCard.vue` using PrimeVue Rating component

### 4. Use Correct Terminology

Match the exact naming conventions from the codebase:

**Backend:**
- ✅ "Use case" (not "service" for business logic)
- ✅ "Action" (not "endpoint" or "route")
- ✅ "Command/Query DTO" (not "request object")
- ✅ "Repository interface" (not "data access layer")

**Frontend:**
- ✅ "Composable" (for `composables/` directory)
- ✅ "Store" (for Pinia stores in `store/`)
- ✅ "Action" (for Pinia store methods)
- ✅ "PrimeVue component" (not "UI library")

### 5. Identify Missing Information

Before implementing, validate requirements:

**Checklist:**
- [ ] Which entity? (Book, Movie, Game, User)
- [ ] Read or write operation? (Query vs Command)
- [ ] Authentication required? (Add `AuthenticationMiddleware`)
- [ ] Input validation needed? (Define required fields)
- [ ] External API involved? (Use appropriate service + caching)
- [ ] UI placement? (Which component, dashboard, or modal)
- [ ] Tests affected? (Which existing tests need updating? What new tests are needed?)
- [ ] Constructor changes? (Update ALL tests that construct modified classes)

## Architecture-Specific Patterns

### Backend Feature Implementation

**Template:**
```
To add [feature name]:

1. **Domain Layer**
   - Create `[Action]Command/Query` DTO in `Domain/DTO/`
   - Create `[Action]UseCase` in `Domain/UseCases/[Entity]/`
   - Define validation rules and error handling

2. **Infrastructure Layer**
   - Update repository if new data access needed: `[Entity]RepositoryInterface`
   - Implement in `Infrastructure/Persistence/MySql[Entity]Repository`

3. **Controller Layer**
   - Add method to `[Entity]Controller` implementing `[Entity]ControllerInterface`
   - Use BaseController::jsonResponse() for output

4. **Configuration**
   - Register use case in `config/container.php`
   - Add route in `config/routes.php` with middleware:
     - AuthenticationMiddleware (if auth required)
     - CSRFMiddleware (for state-changing operations)
     - ValidationMiddleware (with required fields)
     - LoggingMiddleware (for auditing)

5. **Router**
   - Map action in `src/Router/ActionRouter.php`

6. **Testing**
   - Create UseCase test in `tests/Unit/Domain/UseCases/[Entity]/[Action]UseCaseTest.php`
   - Add DTO tests in `tests/Unit/Domain/DTO/Commands/` or `Queries/`
   - If new Value Object: add test in `tests/Unit/Domain/Model/ValueObjects/`
   - If mapper modified: verify mapper tests in `tests/Unit/Infrastructure/Persistence/`
   - Run full suite: `docker compose exec -T backend vendor/bin/phpunit --testdox`
```

### Frontend Feature Implementation

**Template:**
```
To add [feature name]:

1. **Store Layer** (business logic)
   - Add state properties to `store/[entity].js`
   - Create actions for API communication
   - Add getters for computed state

2. **Composable Layer** (UI logic)
   - Create or update `composables/use[Entity].js`
   - Wrap store actions with confirmations/validations
   - Use `storeToRefs()` for reactive state access

3. **Component Layer**
   - Create/update component in `components/[Entity]/`
   - Use PrimeVue components for UI
   - Emit events for parent communication
   - Import composable: `const { action } = use[Entity]()`

4. **Service Layer** (if new API call)
   - Update API service for backend communication
   - Use axios with `VUE_APP_API_URL`
   - Send action parameter in POST body
```

### External API Integration

When integrating external APIs:
- Use existing services: `IGDBService`, `GoogleBooksService`, `OpenLibraryService`
- Always cache responses: `$cache->set($key, $data, $namespace, $ttl)`
- Proxy through backend (avoid CORS issues)
- Handle rate limits and errors gracefully

**Example:**
"Use `OpenLibraryService->searchByTitle()` with 1-hour cache TTL, proxy via `BookController->searchOpenLibrary()` action"

## Common Anti-Patterns to Avoid

### ❌ Don't Say:
- "Create a REST endpoint for books" → Use action-based routing
- "Add Vuex store" → Use Pinia stores
- "New instance of BookRepository" → Use DI container
- "Install local Node.js and PHP" → Use Docker only
- "Add JWT authentication" → Use hybrid session+JWT auth (SessionManager + JWT in localStorage)

### ✅ Do Say:
- "Add action `get_books` with `GetBooksByUserQuery`"
- "Update Pinia `useBooksStore` with new getter"
- "Register in `config/container.php` with autowiring"
- "Run `docker-compose up -d` to start services"
- "Check session via `AuthenticationMiddleware`"

## Example Transformations

### Example 1: Vague Request
**User:** "Add favorites"

**Improved Prompt:**
```
Add favorites/wishlist functionality for books:

Backend:
1. Create `AddBookToWishlistCommand` (userId, bookId) in Domain/DTO/Commands/
2. Create `AddBookToWishlistUseCase` validating book exists via BookRepositoryInterface
3. Add to multi-status system using existing status 'wishlist'
4. Register in container.php
5. Add action `add_book_to_wishlist` to BookController
6. Configure route with AuthenticationMiddleware + ValidationMiddleware

Testing:
1. Add command test in tests/Unit/Domain/DTO/Commands/BookCommandsTest.php
2. Create tests/Unit/Domain/UseCases/Books/AddBookToWishlistUseCaseTest.php
   - Test invalid command type throws InvalidArgumentException
   - Test success path with mocked BookRepositoryInterface
   - Test book not found scenario
3. Run: docker compose exec -T backend vendor/bin/phpunit --testdox

Frontend:
1. Update useBooksStore with `addToWishlist(bookId)` action
2. Update useBooks composable with confirmation modal
3. Add heart icon button to BookCard.vue using PrimeVue Button
4. Filter wishlist books in BooksDashboard via `booksByStatus.wishlist` getter

Uses existing multi-status system, no database changes needed.
```

### Example 2: Technical Request
**User:** "Integrate OpenLibrary API"

**Improved Prompt:**
```
Integrate OpenLibrary API for book metadata enrichment:

Backend:
1. OpenLibraryService already exists in Domain/Services/OpenLibraryService.php
2. Add method `getBookByISBN(string $isbn)` with CacheService (namespace: 'openlibrary/', TTL: 24h)
3. Create proxy action `search_openlibrary` in BookController
4. Add route in routes.php as public endpoint (no auth)
5. Map in ActionRouter.php

Frontend:
1. Create API method in services/api.js: `searchOpenLibrary(isbn)`
2. Add to useBooks composable as `searchOpenLibraryBooks(isbn)`
3. Display results in BookSearch.vue using existing search results pattern
4. Show cover images via OpenLibrary cover API

File paths:
- backend/src/Domain/Services/OpenLibraryService.php (modify)
- backend/src/Controllers/BookController.php (add method)
- backend/config/routes.php (add route)
- frontend/src/composables/useBooks.js (add method)
- frontend/src/components/Books/BookSearch.vue (update UI)
```

### Example 3: Bug Fix
**User:** "Import isn't working"

**Improved Prompt:**
```
Debug CSV import functionality:

Investigation checklist:
1. Check FileProcessorService.js parsing logic
2. Verify ImportModal.vue state management
3. Check useFileImport composable error handling
4. Backend: Verify action 'import_books' in routes.php
5. Check ValidationMiddleware required fields
6. Review BookController import method error responses
7. Check browser console for axios errors
8. Review backend logs: storage/logs/books.log

Expected flow:
1. User selects CSV in ImportModal.vue
2. FileProcessorService.js parses CSV
3. useFileImport sends to backend via 'import_books' action
4. BookController->importBooks() validates via AddBookCommand
5. AddBookUseCase processes each book
6. Returns success/error for each row

Provide specific error message or stack trace for targeted debugging.
```

## Validation Checklist

Before presenting an improved prompt, verify:

- [ ] All file paths reference actual project structure
- [ ] Class/method names match codebase conventions
- [ ] Dependencies are registered in config/container.php
- [ ] Routes include appropriate middleware stack
- [ ] Frontend uses correct store + composable pattern
- [ ] External APIs use caching mechanism
- [ ] No mention of local npm/php commands (Docker only)
- [ ] CQRS pattern followed (Commands for writes, Queries for reads)
- [ ] Error handling and logging considered
- [ ] **Tests included**: New UseCase → new test file; modified DTO → updated DTO tests
- [ ] **Existing tests verified**: Constructor/signature changes don't break existing tests
- [ ] **Test command included**: `docker compose exec -T backend vendor/bin/phpunit --testdox`

## Final Output Format

Present improved prompts with:

1. **Clear Objective**: What feature/fix is being implemented
2. **Backend Changes**: List files, classes, methods with specifics
3. **Frontend Changes**: Components, composables, stores affected
4. **Configuration Updates**: container.php, routes.php changes
5. **Testing**: Specific test files to create/update, mocking strategy, test commands to run
6. **Expected Outcome**: What the user should experience (including all 743+ tests passing)

Then ask: **"¿Deseas que proceda con esta implementación?"**
