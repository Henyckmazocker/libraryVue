# Backend Architecture Details

## Domain-Driven Design Implementation

### Domain Layer

El dominio representa el corazón de la aplicación con lógica de negocio pura, independiente de frameworks y tecnologías externas.

#### Entities (Domain Models)

```php
// src/Domain/Model/Book.php
class Book
{
    private int $id;
    private string $title;
    private string $author;
    private ?string $isbn;
    private ReadingStatus $status;
    private ?Rating $rating;
    
    public function markAsRead(Rating $rating, DateTimeInterface $dateRead): void
    {
        $this->status = ReadingStatus::READ();
        $this->rating = $rating;
        $this->dateRead = $dateRead;
        
        // Domain events could be fired here
        $this->raise(new BookCompletedEvent($this));
    }
    
    public function isValidForReading(): bool
    {
        return $this->status->equals(ReadingStatus::toRead()) ||
               $this->status->equals(ReadingStatus::reading());
    }
}
```

#### Value Objects

```php
// src/Domain/Model/Rating.php
class Rating
{
    private int $value;
    
    public function __construct(int $value)
    {
        if ($value < 1 || $value > 5) {
            throw new InvalidRatingException('Rating must be between 1 and 5');
        }
        $this->value = $value;
    }
    
    public function getValue(): int
    {
        return $this->value;
    }
    
    public function isExcellent(): bool
    {
        return $this->value >= 4;
    }
}
```

#### Repository Interfaces

```php
// src/Domain/Repository/BookRepositoryInterface.php
interface BookRepositoryInterface
{
    public function findById(int $id): ?Book;
    public function findByUser(int $userId): array;
    public function findByStatus(int $userId, ReadingStatus $status): array;
    public function save(Book $book): void;
    public function delete(int $id): void;
    public function searchBooks(SearchCriteria $criteria): BookCollection;
}
```

### Application Layer (Use Cases)

Los casos de uso orchestran la lógica de dominio y coordinan entre diferentes servicios.

#### Use Case Structure

```php
// src/Domain/UseCases/Books/AddBookUseCase.php
class AddBookUseCase
{
    public function __construct(
        private BookRepositoryInterface $bookRepository,
        private UserRepositoryInterface $userRepository,
        private DuplicateBookChecker $duplicateChecker,
        private LoggerInterface $logger
    ) {}
    
    public function execute(AddBookRequest $request): AddBookResponse
    {
        $this->logger->info('Adding new book', ['title' => $request->title]);
        
        // Validate user exists
        $user = $this->userRepository->findById($request->userId);
        if (!$user) {
            throw new UserNotFoundException();
        }
        
        // Check for duplicates
        if ($this->duplicateChecker->exists($request->isbn, $request->userId)) {
            throw new DuplicateBookException();
        }
        
        // Create domain entity
        $book = Book::create(
            $request->title,
            $request->author,
            $request->isbn,
            $request->genre,
            $request->userId
        );
        
        // Persist
        $this->bookRepository->save($book);
        
        $this->logger->info('Book added successfully', ['book_id' => $book->getId()]);
        
        return new AddBookResponse($book);
    }
}
```

#### Request/Response DTOs

```php
// src/Domain/UseCases/Books/AddBookRequest.php
class AddBookRequest
{
    public function __construct(
        public readonly string $title,
        public readonly string $author,
        public readonly ?string $isbn,
        public readonly ?string $genre,
        public readonly int $userId,
        public readonly ?int $publicationYear = null,
        public readonly ?int $pages = null
    ) {}
    
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? throw new InvalidArgumentException('Title is required'),
            author: $data['author'] ?? throw new InvalidArgumentException('Author is required'),
            isbn: $data['isbn'] ?? null,
            genre: $data['genre'] ?? null,
            userId: $data['user_id'] ?? throw new InvalidArgumentException('User ID is required'),
            publicationYear: $data['publication_year'] ?? null,
            pages: $data['pages'] ?? null
        );
    }
}
```

### Infrastructure Layer

#### Repository Implementations

```php
// src/Infrastructure/Persistence/MySqlBookRepository.php
class MySqlBookRepository implements BookRepositoryInterface
{
    public function __construct(
        private PDO $pdo,
        private BookMapper $mapper
    ) {}
    
    public function findById(int $id): ?Book
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM books WHERE id = :id
        ');
        $stmt->execute(['id' => $id]);
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $data ? $this->mapper->toDomain($data) : null;
    }
    
    public function save(Book $book): void
    {
        if ($book->getId()) {
            $this->update($book);
        } else {
            $this->insert($book);
        }
    }
    
    private function insert(Book $book): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO books (title, author, isbn, genre, user_id, status, created_at)
            VALUES (:title, :author, :isbn, :genre, :user_id, :status, NOW())
        ');
        
        $data = $this->mapper->toDatabase($book);
        $stmt->execute($data);
        
        $book->setId((int) $this->pdo->lastInsertId());
    }
}
```

#### Database Mapper

```php
// src/Infrastructure/Persistence/BookMapper.php
class BookMapper
{
    public function toDomain(array $data): Book
    {
        return new Book(
            id: (int) $data['id'],
            title: $data['title'],
            author: $data['author'],
            isbn: $data['isbn'],
            genre: $data['genre'],
            status: ReadingStatus::fromString($data['status']),
            rating: $data['rating'] ? new Rating((int) $data['rating']) : null,
            userId: (int) $data['user_id'],
            createdAt: new DateTimeImmutable($data['created_at'])
        );
    }
    
    public function toDatabase(Book $book): array
    {
        return [
            'title' => $book->getTitle(),
            'author' => $book->getAuthor(),
            'isbn' => $book->getIsbn(),
            'genre' => $book->getGenre(),
            'status' => $book->getStatus()->getValue(),
            'rating' => $book->getRating()?->getValue(),
            'user_id' => $book->getUserId()
        ];
    }
}
```

### Presentation Layer (Controllers)

#### Controller Structure

```php
// src/Controllers/BookController.php
class BookController extends BaseController
{
    public function __construct(
        private AddBookUseCase $addBookUseCase,
        private GetBooksUseCase $getBooksUseCase,
        private UpdateBookUseCase $updateBookUseCase,
        private DeleteBookUseCase $deleteBookUseCase,
        private SearchBooksUseCase $searchBooksUseCase,
        private SessionManager $sessionManager,
        private LoggerInterface $logger
    ) {}
    
    public function handleRequest(string $action, array $data): array
    {
        $this->logger->info('Book controller handling request', [
            'action' => $action,
            'user_id' => $this->sessionManager->getUserId()
        ]);
        
        return match ($action) {
            'add' => $this->addBook($data),
            'list' => $this->getBooks($data),
            'get' => $this->getBook($data),
            'update' => $this->updateBook($data),
            'delete' => $this->deleteBook($data),
            'search' => $this->searchBooks($data),
            default => throw new InvalidActionException("Unknown action: $action")
        };
    }
    
    private function addBook(array $data): array
    {
        try {
            $this->validateAuthentication();
            
            $request = AddBookRequest::fromArray([
                ...$data,
                'user_id' => $this->sessionManager->getUserId()
            ]);
            
            $response = $this->addBookUseCase->execute($request);
            
            return $this->successResponse($response->toArray(), 'Book added successfully');
            
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 'VALIDATION_ERROR', 422);
        } catch (DuplicateBookException $e) {
            return $this->errorResponse('Book already exists', 'DUPLICATE_BOOK', 409);
        } catch (Exception $e) {
            $this->logger->error('Error adding book', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->errorResponse('Internal server error', 'INTERNAL_ERROR', 500);
        }
    }
}
```

#### Base Controller

```php
// src/Controllers/BaseController.php
abstract class BaseController
{
    protected function successResponse(array $data, string $message = ''): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => date('c')
        ];
    }
    
    protected function errorResponse(
        string $error, 
        string $code = 'ERROR', 
        int $statusCode = 400
    ): array {
        http_response_code($statusCode);
        
        return [
            'success' => false,
            'error' => $error,
            'code' => $code,
            'timestamp' => date('c')
        ];
    }
    
    protected function validateAuthentication(): void
    {
        if (!$this->sessionManager->isAuthenticated()) {
            throw new UnauthenticatedException('User not authenticated');
        }
    }
    
    protected function validatePermission(string $permission): void
    {
        if (!$this->sessionManager->hasPermission($permission)) {
            throw new UnauthorizedException('Insufficient permissions');
        }
    }
}
```

## Services Layer

### Application Service

```php
// src/Services/ApplicationService.php
class ApplicationService
{
    private ContainerInterface $container;
    
    public function __construct()
    {
        $this->container = $this->buildContainer();
    }
    
    public function handleRequest(): array
    {
        try {
            $requestData = $this->parseRequest();
            $controller = $this->getController($requestData['controller']);
            
            return $controller->handleRequest(
                $requestData['action'], 
                $requestData['data']
            );
            
        } catch (NotFoundException $e) {
            return $this->errorResponse('Not found', 'NOT_FOUND', 404);
        } catch (Exception $e) {
            error_log("Application error: " . $e->getMessage());
            return $this->errorResponse('Internal server error', 'INTERNAL_ERROR', 500);
        }
    }
    
    private function getController(string $controllerName): BaseController
    {
        return match ($controllerName) {
            'auth' => $this->container->get(AuthController::class),
            'books' => $this->container->get(BookController::class),
            'movies' => $this->container->get(MovieController::class),
            'library' => $this->container->get(LibraryController::class),
            default => throw new NotFoundException("Controller not found: $controllerName")
        };
    }
}
```

### Service Configuration

```php
// config/dependencies.php - Service definitions
return [
    // Core Infrastructure
    PDO::class => function (ContainerInterface $c) {
        $dbConnector = $c->get(DatabaseConnector::class);
        return $dbConnector->getConnection();
    },
    
    DatabaseConnector::class => autowire(),
    SessionManager::class => autowire(),
    
    // Mappers
    BookMapper::class => autowire(),
    MovieMapper::class => autowire(),
    UserMapper::class => autowire(),
    
    // Repositories
    BookRepositoryInterface::class => autowire(MySqlBookRepository::class),
    MovieRepositoryInterface::class => autowire(MySqlMovieRepository::class),
    UserRepositoryInterface::class => autowire(MySqlUserRepository::class),
    
    // Domain Services
    DuplicateBookChecker::class => autowire(),
    PasswordHasher::class => autowire(),
    GoogleAuthValidator::class => autowire(),
    
    // Use Cases
    AddBookUseCase::class => autowire(),
    GetBooksUseCase::class => autowire(),
    UpdateBookUseCase::class => autowire(),
    DeleteBookUseCase::class => autowire(),
    SearchBooksUseCase::class => autowire(),
    
    // Controllers
    AuthController::class => autowire(),
    BookController::class => autowire(),
    MovieController::class => autowire(),
    LibraryController::class => autowire(),
    
    // Logging
    LoggerInterface::class => function (ContainerInterface $c) {
        $loggerFactory = $c->get(LoggerFactory::class);
        return $loggerFactory->createLogger('app');
    },
    
    LoggerFactory::class => autowire(),
];
```

## Error Handling Strategy

### Exception Hierarchy

```php
// Custom exceptions with proper inheritance
abstract class DomainException extends Exception {}
abstract class ApplicationException extends Exception {}
abstract class InfrastructureException extends Exception {}

// Domain exceptions
class InvalidRatingException extends DomainException {}
class InvalidBookStatusException extends DomainException {}

// Application exceptions
class BookNotFoundException extends ApplicationException {}
class DuplicateBookException extends ApplicationException {}
class ValidationException extends ApplicationException {}

// Infrastructure exceptions
class DatabaseConnectionException extends InfrastructureException {}
class SessionException extends InfrastructureException {}
```

### Global Error Handler

```php
// Error handling in ApplicationService
private function handleException(Throwable $e): array
{
    $this->logger->error('Application error', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return match (true) {
        $e instanceof ValidationException => 
            $this->errorResponse($e->getMessage(), 'VALIDATION_ERROR', 422),
        $e instanceof NotFoundException => 
            $this->errorResponse('Resource not found', 'NOT_FOUND', 404),
        $e instanceof UnauthorizedException => 
            $this->errorResponse('Unauthorized', 'UNAUTHORIZED', 401),
        $e instanceof DuplicateBookException => 
            $this->errorResponse('Resource already exists', 'DUPLICATE', 409),
        default => 
            $this->errorResponse('Internal server error', 'INTERNAL_ERROR', 500)
    };
}
```

---

*Documentación actualizada: 18 de Agosto de 2025*
