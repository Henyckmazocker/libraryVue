# Testing Documentation - Library Vue

## Overview

Esta documentación cubre las estrategias de testing, configuraciones y mejores prácticas para el proyecto Library Vue, incluyendo testing unitario, de integración y end-to-end.

## Testing Strategy

### Testing Pyramid

```
    /\
   /E2E\     <- Few, high-level tests
  /______\
 /        \
/Integration\  <- Some integration tests
\____________/
\            /
 \ Unit     /   <- Many, fast unit tests
  \________/
```

### Test Categories

1. **Unit Tests** - Testear componentes individuales en aislamiento
2. **Integration Tests** - Testear interacciones entre componentes
3. **E2E Tests** - Testear flujos completos de usuario
4. **API Tests** - Testear endpoints de la API

## Backend Testing (PHP)

### PHPUnit Setup

#### Configuration

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         processIsolation="false"
         stopOnFailure="false"
         cacheDirectory=".phpunit.cache">
    
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    
    <source>
        <include>
            <directory>src</directory>
        </include>
        <exclude>
            <directory>src/Infrastructure/Database/Migrations</directory>
        </exclude>
    </source>
    
    <php>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="APP_ENV" value="testing"/>
    </php>
    
    <logging>
        <log type="coverage-html" target="tests/coverage"/>
        <log type="coverage-clover" target="tests/coverage/clover.xml"/>
    </logging>
</phpunit>
```

#### Test Bootstrap

```php
<?php
// tests/bootstrap.php

require_once __DIR__ . '/../vendor/autoload.php';

// Set up test environment
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

// Initialize test database
$pdo = new PDO('sqlite::memory:');
$pdo->exec('PRAGMA foreign_keys = ON');

// Run migrations for testing
require_once __DIR__ . '/database/TestSchema.php';
TestSchema::create($pdo);
```

### Unit Testing

#### Testing Use Cases

```php
<?php
// tests/Unit/UseCases/Books/AddBookUseCaseTest.php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Books;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\UseCases\Books\AddBookRequest;
use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Model\Book;
use App\Domain\Model\User;
use App\Domain\Exceptions\ValidationException;
use App\Domain\Exceptions\UserNotFoundException;

class AddBookUseCaseTest extends TestCase
{
    private AddBookUseCase $useCase;
    private MockObject|BookRepositoryInterface $bookRepository;
    private MockObject|UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        $this->bookRepository = $this->createMock(BookRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $this->useCase = new AddBookUseCase(
            $this->bookRepository,
            $this->userRepository
        );
    }

    public function testExecuteWithValidData(): void
    {
        // Arrange
        $user = new User(1, 'test@example.com', 'Test User');
        $request = new AddBookRequest(
            title: 'Test Book',
            author: 'Test Author',
            isbn: '9781234567890',
            genre: 'Fiction',
            userId: 1
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($user);

        $this->bookRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Book $book) {
                return $book->getTitle() === 'Test Book' &&
                       $book->getAuthor() === 'Test Author' &&
                       $book->getUserId() === 1;
            }));

        // Act
        $response = $this->useCase->execute($request);

        // Assert
        $this->assertNotNull($response);
        $this->assertEquals('Test Book', $response->getBook()->getTitle());
        $this->assertEquals('Test Author', $response->getBook()->getAuthor());
    }

    public function testExecuteWithInvalidUser(): void
    {
        // Arrange
        $request = new AddBookRequest(
            title: 'Test Book',
            author: 'Test Author',
            isbn: null,
            genre: null,
            userId: 999
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->bookRepository
            ->expects($this->never())
            ->method('save');

        // Act & Assert
        $this->expectException(UserNotFoundException::class);
        $this->useCase->execute($request);
    }

    public function testExecuteWithEmptyTitle(): void
    {
        // Arrange
        $request = new AddBookRequest(
            title: '',
            author: 'Test Author',
            isbn: null,
            genre: null,
            userId: 1
        );

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Title cannot be empty');
        
        $this->useCase->execute($request);
    }

    /**
     * @dataProvider invalidISBNProvider
     */
    public function testExecuteWithInvalidISBN(string $isbn): void
    {
        // Arrange
        $user = new User(1, 'test@example.com', 'Test User');
        $request = new AddBookRequest(
            title: 'Test Book',
            author: 'Test Author',
            isbn: $isbn,
            genre: null,
            userId: 1
        );

        $this->userRepository
            ->method('findById')
            ->willReturn($user);

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid ISBN format');
        
        $this->useCase->execute($request);
    }

    public function invalidISBNProvider(): array
    {
        return [
            'too short' => ['123'],
            'too long' => ['12345678901234567890'],
            'invalid characters' => ['978-ABC-DEF-GHI'],
            'invalid checksum' => ['9781234567891'],
        ];
    }
}
```

#### Testing Repositories

```php
<?php
// tests/Unit/Infrastructure/Persistence/MySqlBookRepositoryTest.php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use PHPUnit\Framework\TestCase;
use PDO;
use App\Infrastructure\Persistence\MySqlBookRepository;
use App\Infrastructure\Persistence\BookMapper;
use App\Domain\Model\Book;
use App\Domain\Model\ReadingStatus;

class MySqlBookRepositoryTest extends TestCase
{
    private PDO $pdo;
    private MySqlBookRepository $repository;
    private BookMapper $mapper;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        
        // Create test schema
        $this->createSchema();
        
        $this->mapper = new BookMapper();
        $this->repository = new MySqlBookRepository($this->pdo, $this->mapper);
    }

    public function testSaveNewBook(): void
    {
        // Arrange
        $book = new Book(
            id: null,
            title: 'Test Book',
            author: 'Test Author',
            isbn: '9781234567890',
            genre: 'Fiction',
            status: ReadingStatus::toRead(),
            userId: 1
        );

        // Act
        $this->repository->save($book);

        // Assert
        $this->assertNotNull($book->getId());
        
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM books WHERE title = ?');
        $stmt->execute(['Test Book']);
        $count = $stmt->fetchColumn();
        
        $this->assertEquals(1, $count);
    }

    public function testFindById(): void
    {
        // Arrange
        $this->insertTestBook(1, 'Test Book', 'Test Author', 1);

        // Act
        $book = $this->repository->findById(1);

        // Assert
        $this->assertNotNull($book);
        $this->assertEquals('Test Book', $book->getTitle());
        $this->assertEquals('Test Author', $book->getAuthor());
    }

    public function testFindByIdNotFound(): void
    {
        // Act
        $book = $this->repository->findById(999);

        // Assert
        $this->assertNull($book);
    }

    public function testFindByUser(): void
    {
        // Arrange
        $this->insertTestBook(1, 'Book 1', 'Author 1', 1);
        $this->insertTestBook(2, 'Book 2', 'Author 2', 1);
        $this->insertTestBook(3, 'Book 3', 'Author 3', 2);

        // Act
        $books = $this->repository->findByUser(1);

        // Assert
        $this->assertCount(2, $books);
        $this->assertEquals('Book 1', $books[0]->getTitle());
        $this->assertEquals('Book 2', $books[1]->getTitle());
    }

    private function createSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE books (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                author TEXT NOT NULL,
                isbn TEXT,
                genre TEXT,
                status TEXT DEFAULT "to-read",
                rating INTEGER,
                user_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }

    private function insertTestBook(int $id, string $title, string $author, int $userId): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO books (id, title, author, user_id) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$id, $title, $author, $userId]);
    }
}
```

### Integration Testing

#### Testing Controllers

```php
<?php
// tests/Integration/Controllers/BookControllerTest.php

declare(strict_types=1);

namespace Tests\Integration\Controllers;

use Tests\Integration\IntegrationTestCase;
use App\Controllers\BookController;
use App\Infrastructure\Session\SessionManager;

class BookControllerTest extends IntegrationTestCase
{
    private BookController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->controller = $this->container->get(BookController::class);
        
        // Setup authenticated user session
        $sessionManager = $this->container->get(SessionManager::class);
        $sessionManager->setUserId(1);
    }

    public function testAddBook(): void
    {
        // Arrange
        $data = [
            'title' => 'Integration Test Book',
            'author' => 'Test Author',
            'isbn' => '9781234567890',
            'genre' => 'Fiction'
        ];

        // Act
        $response = $this->controller->handleRequest('add', $data);

        // Assert
        $this->assertTrue($response['success']);
        $this->assertEquals('Book added successfully', $response['message']);
        $this->assertArrayHasKey('data', $response);
        $this->assertEquals('Integration Test Book', $response['data']['book']['title']);
    }

    public function testGetBooks(): void
    {
        // Arrange - add some test books
        $this->insertTestBooks();

        // Act
        $response = $this->controller->handleRequest('list', []);

        // Assert
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('books', $response['data']);
        $this->assertGreaterThan(0, count($response['data']['books']));
    }

    public function testAddBookWithoutAuthentication(): void
    {
        // Arrange
        $sessionManager = $this->container->get(SessionManager::class);
        $sessionManager->destroy(); // Remove authentication

        $data = [
            'title' => 'Test Book',
            'author' => 'Test Author'
        ];

        // Act
        $response = $this->controller->handleRequest('add', $data);

        // Assert
        $this->assertFalse($response['success']);
        $this->assertEquals('UNAUTHORIZED', $response['code']);
    }

    private function insertTestBooks(): void
    {
        $books = [
            ['title' => 'Book 1', 'author' => 'Author 1', 'user_id' => 1],
            ['title' => 'Book 2', 'author' => 'Author 2', 'user_id' => 1],
            ['title' => 'Book 3', 'author' => 'Author 3', 'user_id' => 1],
        ];

        $stmt = $this->pdo->prepare('
            INSERT INTO books (title, author, user_id) 
            VALUES (?, ?, ?)
        ');

        foreach ($books as $book) {
            $stmt->execute([$book['title'], $book['author'], $book['user_id']]);
        }
    }
}
```

#### Base Integration Test

```php
<?php
// tests/Integration/IntegrationTestCase.php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use DI\Container;
use PDO;

abstract class IntegrationTestCase extends TestCase
{
    protected Container $container;
    protected PDO $pdo;

    protected function setUp(): void
    {
        // Setup test database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        
        // Create test schema
        $this->createSchema();
        
        // Setup DI container for testing
        $this->container = $this->createTestContainer();
        
        // Seed test data
        $this->seedTestData();
    }

    protected function createTestContainer(): Container
    {
        $builder = new \DI\ContainerBuilder();
        
        // Override PDO for testing
        $builder->addDefinitions([
            PDO::class => $this->pdo,
            // Other test-specific definitions
        ]);
        
        // Load main configuration
        $builder->addDefinitions(__DIR__ . '/../../config/dependencies.php');
        
        return $builder->build();
    }

    protected function createSchema(): void
    {
        // Create tables
        $this->pdo->exec('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                name TEXT NOT NULL,
                password_hash TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->pdo->exec('
            CREATE TABLE books (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                author TEXT NOT NULL,
                isbn TEXT,
                genre TEXT,
                status TEXT DEFAULT "to-read",
                rating INTEGER,
                user_id INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');
    }

    protected function seedTestData(): void
    {
        // Insert test user
        $stmt = $this->pdo->prepare('
            INSERT INTO users (id, email, name) 
            VALUES (?, ?, ?)
        ');
        $stmt->execute([1, 'test@example.com', 'Test User']);
    }
}
```

## Frontend Testing (Vue.js)

### Vitest Setup

#### Configuration

```javascript
// vitest.config.js
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['tests/setup.js'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      exclude: [
        'node_modules/',
        'tests/',
        '**/*.d.ts',
      ]
    }
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src')
    }
  }
})
```

#### Test Setup

```javascript
// tests/setup.js
import { config } from '@vue/test-utils'
import { vi } from 'vitest'

// Mock global properties
config.global.mocks = {
  $t: (key) => key, // Mock i18n
  $router: {
    push: vi.fn(),
    replace: vi.fn()
  },
  $route: {
    params: {},
    query: {},
    path: '/'
  }
}

// Mock IntersectionObserver
global.IntersectionObserver = vi.fn(() => ({
  disconnect: vi.fn(),
  observe: vi.fn(),
  unobserve: vi.fn(),
}))

// Mock ResizeObserver
global.ResizeObserver = vi.fn(() => ({
  observe: vi.fn(),
  unobserve: vi.fn(),
  disconnect: vi.fn(),
}))
```

### Unit Testing Components

#### Component Testing

```javascript
// tests/unit/components/BookCard.test.js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import BookCard from '@/components/library/BookCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

describe('BookCard', () => {
  let wrapper
  const mockBook = {
    id: 1,
    title: 'Test Book',
    author: 'Test Author',
    status: 'read',
    rating: 4,
    cover_image: 'https://example.com/cover.jpg'
  }

  beforeEach(() => {
    wrapper = mount(BookCard, {
      props: { book: mockBook },
      global: {
        components: { BaseButton }
      }
    })
  })

  it('renders book information correctly', () => {
    expect(wrapper.find('[data-testid="book-title"]').text()).toBe('Test Book')
    expect(wrapper.find('[data-testid="book-author"]').text()).toBe('Test Author')
    expect(wrapper.find('[data-testid="book-cover"]').attributes('src')).toBe('https://example.com/cover.jpg')
  })

  it('displays correct status text', () => {
    expect(wrapper.find('[data-testid="status-button"]').text()).toBe('Mark as Unread')
  })

  it('emits updated event when status changes', async () => {
    const statusButton = wrapper.find('[data-testid="status-button"]')
    await statusButton.trigger('click')
    
    expect(wrapper.emitted('updated')).toBeTruthy()
    expect(wrapper.emitted('updated')).toHaveLength(1)
  })

  it('emits details event when details button is clicked', async () => {
    const detailsButton = wrapper.find('[data-testid="details-button"]')
    await detailsButton.trigger('click')
    
    expect(wrapper.emitted('details')).toBeTruthy()
    expect(wrapper.emitted('details')[0]).toEqual([mockBook])
  })

  it('shows loading state when updating', async () => {
    await wrapper.setData({ updating: true })
    
    const statusButton = wrapper.find('[data-testid="status-button"]')
    expect(statusButton.attributes('disabled')).toBeDefined()
  })
})
```

#### Form Component Testing

```javascript
// tests/unit/components/forms/BookForm.test.js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import BookForm from '@/components/forms/BookForm.vue'

describe('BookForm', () => {
  it('validates required fields', async () => {
    const wrapper = mount(BookForm)
    
    // Try to submit without filling required fields
    await wrapper.find('form').trigger('submit.prevent')
    
    expect(wrapper.find('[data-testid="title-error"]').text()).toBe('Title is required')
    expect(wrapper.find('[data-testid="author-error"]').text()).toBe('Author is required')
  })

  it('emits submit event with form data', async () => {
    const wrapper = mount(BookForm)
    
    // Fill form fields
    await wrapper.find('[data-testid="title-input"]').setValue('Test Book')
    await wrapper.find('[data-testid="author-input"]').setValue('Test Author')
    await wrapper.find('[data-testid="isbn-input"]').setValue('9781234567890')
    await wrapper.find('[data-testid="genre-select"]').setValue('Fiction')
    
    // Submit form
    await wrapper.find('form').trigger('submit.prevent')
    
    expect(wrapper.emitted('submit')).toBeTruthy()
    expect(wrapper.emitted('submit')[0][0]).toEqual({
      title: 'Test Book',
      author: 'Test Author',
      isbn: '9781234567890',
      genre: 'Fiction'
    })
  })

  it('validates ISBN format', async () => {
    const wrapper = mount(BookForm)
    
    await wrapper.find('[data-testid="isbn-input"]').setValue('invalid-isbn')
    await wrapper.find('[data-testid="isbn-input"]').trigger('blur')
    
    expect(wrapper.find('[data-testid="isbn-error"]').text()).toBe('Invalid ISBN format')
  })

  it('populates form when editing existing book', () => {
    const existingBook = {
      title: 'Existing Book',
      author: 'Existing Author',
      isbn: '9781234567890',
      genre: 'Fiction'
    }
    
    const wrapper = mount(BookForm, {
      props: { book: existingBook, mode: 'edit' }
    })
    
    expect(wrapper.find('[data-testid="title-input"]').element.value).toBe('Existing Book')
    expect(wrapper.find('[data-testid="author-input"]').element.value).toBe('Existing Author')
    expect(wrapper.find('[data-testid="isbn-input"]').element.value).toBe('9781234567890')
    expect(wrapper.find('[data-testid="genre-select"]').element.value).toBe('Fiction')
  })
})
```

### Testing Composables

```javascript
// tests/unit/composables/useBooks.test.js
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useBooks } from '@/composables/useBooks'

// Mock the book service
vi.mock('@/services/bookService', () => ({
  bookService: {
    getBooks: vi.fn(),
    addBook: vi.fn(),
    updateBook: vi.fn(),
    deleteBook: vi.fn(),
    searchBooks: vi.fn()
  }
}))

describe('useBooks', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('fetches books successfully', async () => {
    const { bookService } = await import('@/services/bookService')
    const mockResponse = {
      data: {
        books: [
          { id: 1, title: 'Book 1', author: 'Author 1' },
          { id: 2, title: 'Book 2', author: 'Author 2' }
        ],
        pagination: {
          current_page: 1,
          total: 2,
          has_next_page: false
        }
      }
    }
    
    bookService.getBooks.mockResolvedValue(mockResponse)
    
    const { fetchBooks, books, loading, error } = useBooks()
    
    expect(loading.value).toBe(false)
    expect(books.value).toEqual([])
    
    await fetchBooks()
    
    expect(loading.value).toBe(false)
    expect(error.value).toBe(null)
    expect(books.value).toHaveLength(2)
    expect(books.value[0].title).toBe('Book 1')
  })

  it('handles fetch error gracefully', async () => {
    const { bookService } = await import('@/services/bookService')
    const mockError = new Error('Network error')
    
    bookService.getBooks.mockRejectedValue(mockError)
    
    const { fetchBooks, books, loading, error } = useBooks()
    
    await expect(fetchBooks()).rejects.toThrow('Network error')
    
    expect(loading.value).toBe(false)
    expect(error.value).toBe('Failed to fetch books')
    expect(books.value).toEqual([])
  })

  it('adds book successfully', async () => {
    const { bookService } = await import('@/services/bookService')
    const newBook = { title: 'New Book', author: 'New Author' }
    const mockResponse = {
      data: {
        book: { id: 3, ...newBook }
      }
    }
    
    bookService.addBook.mockResolvedValue(mockResponse)
    
    const { addBook, books } = useBooks()
    
    const result = await addBook(newBook)
    
    expect(bookService.addBook).toHaveBeenCalledWith(newBook)
    expect(result.id).toBe(3)
    expect(books.value).toContainEqual({ id: 3, ...newBook })
  })

  it('searches books with debouncing', async () => {
    const { bookService } = await import('@/services/bookService')
    const mockResponse = {
      data: {
        books: [{ id: 1, title: 'Search Result', author: 'Author' }],
        pagination: { current_page: 1, total: 1 }
      }
    }
    
    bookService.searchBooks.mockResolvedValue(mockResponse)
    
    const { searchBooks, books } = useBooks()
    
    await searchBooks('test query')
    
    expect(bookService.searchBooks).toHaveBeenCalledWith({ q: 'test query' })
    expect(books.value).toHaveLength(1)
    expect(books.value[0].title).toBe('Search Result')
  })
})
```

### Testing Stores

```javascript
// tests/unit/stores/books.test.js
import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useBooksStore } from '@/stores/books'

describe('Books Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('initializes with empty state', () => {
    const store = useBooksStore()
    
    expect(store.books).toEqual([])
    expect(store.currentPage).toBe(1)
    expect(store.totalBooks).toBe(0)
    expect(store.hasMore).toBe(false)
  })

  it('sets books correctly', () => {
    const store = useBooksStore()
    const testBooks = [
      { id: 1, title: 'Book 1', status: 'read' },
      { id: 2, title: 'Book 2', status: 'reading' }
    ]
    
    store.setBooks(testBooks)
    
    expect(store.books).toEqual(testBooks)
  })

  it('adds new book to beginning of list', () => {
    const store = useBooksStore()
    const existingBooks = [
      { id: 1, title: 'Book 1' }
    ]
    const newBook = { id: 2, title: 'Book 2' }
    
    store.setBooks(existingBooks)
    store.addBook(newBook)
    
    expect(store.books).toHaveLength(2)
    expect(store.books[0]).toEqual(newBook)
    expect(store.totalBooks).toBe(2)
  })

  it('updates existing book', () => {
    const store = useBooksStore()
    const books = [
      { id: 1, title: 'Original Title', status: 'reading' },
      { id: 2, title: 'Book 2', status: 'read' }
    ]
    
    store.setBooks(books)
    
    const updatedBook = { id: 1, title: 'Updated Title', status: 'read' }
    store.updateBook(updatedBook)
    
    expect(store.books[0].title).toBe('Updated Title')
    expect(store.books[0].status).toBe('read')
    expect(store.books[1]).toEqual(books[1]) // Other books unchanged
  })

  it('removes book correctly', () => {
    const store = useBooksStore()
    const books = [
      { id: 1, title: 'Book 1' },
      { id: 2, title: 'Book 2' },
      { id: 3, title: 'Book 3' }
    ]
    
    store.setBooks(books)
    store.setPagination({ total: 3 })
    
    store.removeBook(2)
    
    expect(store.books).toHaveLength(2)
    expect(store.books.find(b => b.id === 2)).toBeUndefined()
    expect(store.totalBooks).toBe(2)
  })

  it('calculates statistics correctly', () => {
    const store = useBooksStore()
    const books = [
      { id: 1, status: 'read', rating: 5, pages: 300 },
      { id: 2, status: 'read', rating: 4, pages: 250 },
      { id: 3, status: 'reading', pages: 400 },
      { id: 4, status: 'to-read', pages: 350 }
    ]
    
    store.setBooks(books)
    
    const stats = store.statistics
    
    expect(stats.total).toBe(4)
    expect(stats.read).toBe(2)
    expect(stats.reading).toBe(1)
    expect(stats.toRead).toBe(1)
    expect(stats.averageRating).toBe(4.5)
    expect(stats.totalPages).toBe(1300)
  })

  it('groups books by status correctly', () => {
    const store = useBooksStore()
    const books = [
      { id: 1, title: 'Read Book', status: 'read' },
      { id: 2, title: 'Reading Book', status: 'reading' },
      { id: 3, title: 'To Read Book', status: 'to-read' },
      { id: 4, title: 'Another Read Book', status: 'read' }
    ]
    
    store.setBooks(books)
    
    expect(store.readBooks).toHaveLength(2)
    expect(store.readingBooks).toHaveLength(1)
    expect(store.toReadBooks).toHaveLength(1)
    
    expect(store.readBooks[0].title).toBe('Read Book')
    expect(store.readingBooks[0].title).toBe('Reading Book')
    expect(store.toReadBooks[0].title).toBe('To Read Book')
  })
})
```

## End-to-End Testing

### Playwright Setup

```javascript
// playwright.config.js
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 1 : undefined,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:3000',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      use: { ...devices['Desktop Safari'] },
    },
    {
      name: 'Mobile Chrome',
      use: { ...devices['Pixel 5'] },
    },
  ],

  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:3000',
    reuseExistingServer: !process.env.CI,
  },
})
```

### E2E Test Examples

```javascript
// tests/e2e/auth.spec.js
import { test, expect } from '@playwright/test'

test.describe('Authentication', () => {
  test('should login with valid credentials', async ({ page }) => {
    await page.goto('/login')
    
    await page.fill('[data-testid="email-input"]', 'test@example.com')
    await page.fill('[data-testid="password-input"]', 'password123')
    await page.click('[data-testid="login-button"]')
    
    await expect(page).toHaveURL('/')
    await expect(page.locator('[data-testid="user-menu"]')).toBeVisible()
  })

  test('should show error with invalid credentials', async ({ page }) => {
    await page.goto('/login')
    
    await page.fill('[data-testid="email-input"]', 'invalid@example.com')
    await page.fill('[data-testid="password-input"]', 'wrongpassword')
    await page.click('[data-testid="login-button"]')
    
    await expect(page.locator('[data-testid="error-message"]')).toContainText('Invalid credentials')
  })

  test('should logout successfully', async ({ page }) => {
    // Login first
    await page.goto('/login')
    await page.fill('[data-testid="email-input"]', 'test@example.com')
    await page.fill('[data-testid="password-input"]', 'password123')
    await page.click('[data-testid="login-button"]')
    
    // Logout
    await page.click('[data-testid="user-menu"]')
    await page.click('[data-testid="logout-button"]')
    
    await expect(page).toHaveURL('/login')
  })
})
```

```javascript
// tests/e2e/books.spec.js
import { test, expect } from '@playwright/test'

test.describe('Books Management', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto('/login')
    await page.fill('[data-testid="email-input"]', 'test@example.com')
    await page.fill('[data-testid="password-input"]', 'password123')
    await page.click('[data-testid="login-button"]')
    await page.waitForURL('/')
  })

  test('should add a new book', async ({ page }) => {
    await page.goto('/books')
    await page.click('[data-testid="add-book-button"]')
    
    // Fill form
    await page.fill('[data-testid="title-input"]', 'Test Book')
    await page.fill('[data-testid="author-input"]', 'Test Author')
    await page.fill('[data-testid="isbn-input"]', '9781234567890')
    await page.selectOption('[data-testid="genre-select"]', 'Fiction')
    
    await page.click('[data-testid="submit-button"]')
    
    // Verify book was added
    await expect(page.locator('[data-testid="book-card"]').first()).toContainText('Test Book')
    await expect(page.locator('[data-testid="success-message"]')).toContainText('Book added successfully')
  })

  test('should search for books', async ({ page }) => {
    await page.goto('/books')
    
    await page.fill('[data-testid="search-input"]', 'Harry Potter')
    await page.press('[data-testid="search-input"]', 'Enter')
    
    // Wait for search results
    await page.waitForLoadState('networkidle')
    
    // Verify search results
    const bookCards = page.locator('[data-testid="book-card"]')
    await expect(bookCards.first()).toContainText('Harry Potter')
  })

  test('should update book status', async ({ page }) => {
    await page.goto('/books')
    
    // Find first book and change status
    const firstBookCard = page.locator('[data-testid="book-card"]').first()
    await firstBookCard.locator('[data-testid="status-button"]').click()
    
    // Verify status changed
    await expect(firstBookCard.locator('[data-testid="status-badge"]')).toContainText('Read')
    await expect(page.locator('[data-testid="success-message"]')).toContainText('Book updated')
  })

  test('should filter books by status', async ({ page }) => {
    await page.goto('/books')
    
    // Apply filter
    await page.selectOption('[data-testid="status-filter"]', 'read')
    
    // Verify filtered results
    const bookCards = page.locator('[data-testid="book-card"]')
    const count = await bookCards.count()
    
    for (let i = 0; i < count; i++) {
      await expect(bookCards.nth(i).locator('[data-testid="status-badge"]')).toContainText('Read')
    }
  })

  test('should handle infinite scroll', async ({ page }) => {
    await page.goto('/books')
    
    // Get initial book count
    const initialCount = await page.locator('[data-testid="book-card"]').count()
    
    // Scroll to bottom
    await page.evaluate(() => {
      window.scrollTo(0, document.body.scrollHeight)
    })
    
    // Wait for more books to load
    await page.waitForFunction(
      (initialCount) => {
        return document.querySelectorAll('[data-testid="book-card"]').length > initialCount
      },
      initialCount
    )
    
    // Verify more books loaded
    const newCount = await page.locator('[data-testid="book-card"]').count()
    expect(newCount).toBeGreaterThan(initialCount)
  })
})
```

## API Testing

### Testing API Endpoints

```javascript
// tests/api/books.api.test.js
import { describe, it, expect, beforeAll, afterAll } from 'vitest'

const API_BASE_URL = 'http://localhost:8080/api'

describe('Books API', () => {
  let authCookie

  beforeAll(async () => {
    // Login to get session cookie
    const loginResponse = await fetch(`${API_BASE_URL}/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        email: 'test@example.com',
        password: 'password123'
      })
    })

    const cookies = loginResponse.headers.get('set-cookie')
    authCookie = cookies
  })

  afterAll(async () => {
    // Logout
    await fetch(`${API_BASE_URL}/auth/logout`, {
      method: 'POST',
      headers: {
        'Cookie': authCookie
      }
    })
  })

  it('should get books list', async () => {
    const response = await fetch(`${API_BASE_URL}/books`, {
      headers: {
        'Cookie': authCookie
      }
    })

    expect(response.status).toBe(200)

    const data = await response.json()
    expect(data.success).toBe(true)
    expect(data.data).toHaveProperty('books')
    expect(data.data).toHaveProperty('pagination')
    expect(Array.isArray(data.data.books)).toBe(true)
  })

  it('should add a new book', async () => {
    const newBook = {
      title: 'API Test Book',
      author: 'API Test Author',
      isbn: '9781234567890',
      genre: 'Fiction'
    }

    const response = await fetch(`${API_BASE_URL}/books`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Cookie': authCookie
      },
      body: JSON.stringify(newBook)
    })

    expect(response.status).toBe(200)

    const data = await response.json()
    expect(data.success).toBe(true)
    expect(data.data.book.title).toBe('API Test Book')
    expect(data.data.book.author).toBe('API Test Author')
    expect(data.message).toBe('Book added successfully')
  })

  it('should validate required fields', async () => {
    const invalidBook = {
      title: '',
      author: ''
    }

    const response = await fetch(`${API_BASE_URL}/books`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Cookie': authCookie
      },
      body: JSON.stringify(invalidBook)
    })

    expect(response.status).toBe(422)

    const data = await response.json()
    expect(data.success).toBe(false)
    expect(data.code).toBe('VALIDATION_ERROR')
  })

  it('should search books', async () => {
    const response = await fetch(`${API_BASE_URL}/books/search?q=test`, {
      headers: {
        'Cookie': authCookie
      }
    })

    expect(response.status).toBe(200)

    const data = await response.json()
    expect(data.success).toBe(true)
    expect(data.data).toHaveProperty('books')
  })

  it('should update book', async () => {
    // First, get a book to update
    const booksResponse = await fetch(`${API_BASE_URL}/books`, {
      headers: {
        'Cookie': authCookie
      }
    })
    const booksData = await booksResponse.json()
    const bookId = booksData.data.books[0].id

    const updates = {
      status: 'read',
      rating: 5
    }

    const response = await fetch(`${API_BASE_URL}/books/${bookId}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Cookie': authCookie
      },
      body: JSON.stringify(updates)
    })

    expect(response.status).toBe(200)

    const data = await response.json()
    expect(data.success).toBe(true)
    expect(data.data.book.status).toBe('read')
    expect(data.data.book.rating).toBe(5)
  })

  it('should return 404 for non-existent book', async () => {
    const response = await fetch(`${API_BASE_URL}/books/99999`, {
      headers: {
        'Cookie': authCookie
      }
    })

    expect(response.status).toBe(404)

    const data = await response.json()
    expect(data.success).toBe(false)
    expect(data.code).toBe('BOOK_NOT_FOUND')
  })
})
```

## Test Commands

### Running Tests

```json
{
  "scripts": {
    "test": "npm run test:unit && npm run test:api",
    "test:unit": "vitest run",
    "test:unit:watch": "vitest",
    "test:unit:coverage": "vitest run --coverage",
    "test:e2e": "playwright test",
    "test:e2e:headed": "playwright test --headed",
    "test:api": "vitest run tests/api",
    "test:backend": "cd ../backend && vendor/bin/phpunit",
    "test:backend:coverage": "cd ../backend && vendor/bin/phpunit --coverage-html coverage"
  }
}
```

### CI/CD Integration

```yaml
# .github/workflows/test.yml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  backend-tests:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: library_test
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql, zip
        coverage: xdebug
        
    - name: Install dependencies
      run: |
        cd backend
        composer install --prefer-dist --no-progress
        
    - name: Run tests
      run: |
        cd backend
        vendor/bin/phpunit --coverage-clover=coverage.xml
        
    - name: Upload coverage
      uses: codecov/codecov-action@v3
      with:
        file: backend/coverage.xml

  frontend-tests:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
        cache-dependency-path: frontend/package-lock.json
        
    - name: Install dependencies
      run: |
        cd frontend
        npm ci
        
    - name: Run unit tests
      run: |
        cd frontend
        npm run test:unit:coverage
        
    - name: Run E2E tests
      run: |
        cd frontend
        npx playwright install
        npm run test:e2e

  api-tests:
    runs-on: ubuntu-latest
    needs: [backend-tests]
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Start services
      run: |
        docker-compose -f docker-compose.test.yml up -d
        
    - name: Wait for services
      run: |
        sleep 30
        
    - name: Run API tests
      run: |
        cd frontend
        npm run test:api
```

---

*Documentación actualizada: 18 de Agosto de 2025*
