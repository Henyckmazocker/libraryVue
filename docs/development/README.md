# Development Guide - Library Vue

## Getting Started

Esta guía te ayudará a configurar el entorno de desarrollo para contribuir al proyecto Library Vue.

## Prerequisites

### Required Software

- **Docker Desktop** 4.15+ (includes Docker Compose)
- **Git** 2.40+
- **Node.js** 18+ (for local frontend development)
- **PHP** 8.1+ (for local backend development)
- **Composer** 2.5+ (PHP dependency manager)
- **Visual Studio Code** (recommended IDE)

### Recommended VS Code Extensions

```json
{
  "recommendations": [
    "vue.volar",
    "bradlc.vscode-tailwindcss",
    "ms-vscode.vscode-typescript-next",
    "bmewburn.vscode-intelephense-client",
    "ms-vscode-remote.remote-containers",
    "esbenp.prettier-vscode",
    "ms-vscode.vscode-eslint"
  ]
}
```

## Project Setup

### 1. Clone Repository

```bash
git clone https://github.com/your-org/library-vue.git
cd library-vue
```

### 2. Environment Configuration

```bash
# Copy environment template
cp .env.example .env.development

# Edit configuration as needed
nano .env.development
```

### 3. Docker Development Setup

```bash
# Start all services
docker-compose up -d

# Check services status
docker-compose ps

# View logs
docker-compose logs -f
```

### 4. Access Application

- **Frontend**: http://localhost:3000
- **Backend API**: http://localhost:8080/api
- **Database**: localhost:3306
- **Nginx**: http://localhost

## Development Workflow

### Frontend Development

#### Local Development Server

```bash
cd frontend

# Install dependencies
npm install

# Start development server with hot reload
npm run dev

# Run in different port
npm run dev -- --port 3001
```

#### Available Scripts

```bash
# Development
npm run dev              # Start dev server
npm run build           # Build for production
npm run preview         # Preview production build

# Code Quality
npm run lint            # Run ESLint
npm run lint:fix        # Fix ESLint issues
npm run format          # Format with Prettier
npm run type-check      # TypeScript type checking

# Testing
npm run test:unit       # Run unit tests
npm run test:e2e        # Run e2e tests
npm run test:coverage   # Generate coverage report
```

#### Component Development

```vue
<!-- components/ExampleComponent.vue -->
<template>
  <div class="example-component">
    <h2>{{ title }}</h2>
    <p>{{ description }}</p>
    <BaseButton @click="handleClick" :loading="loading">
      {{ buttonText }}
    </BaseButton>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'

// Props with TypeScript
interface Props {
  title: string
  description?: string
  buttonText?: string
}

const props = withDefaults(defineProps<Props>(), {
  description: '',
  buttonText: 'Click me'
})

// Emits
const emit = defineEmits<{
  clicked: [value: string]
}>()

// Reactive state
const loading = ref(false)

// Computed properties
const computedValue = computed(() => {
  return `${props.title} - ${props.description}`
})

// Methods
const handleClick = async () => {
  loading.value = true
  try {
    // Simulate async operation
    await new Promise(resolve => setTimeout(resolve, 1000))
    emit('clicked', computedValue.value)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.example-component {
  @apply p-4 border rounded-lg;
}
</style>
```

### Backend Development

#### Local Development with Docker

```bash
# Access backend container
docker-compose exec backend bash

# Install new PHP dependencies
docker-compose exec backend composer require package/name

# Run PHP commands
docker-compose exec backend php artisan migrate
```

#### Local Development without Docker

```bash
cd backend

# Install dependencies
composer install

# Start PHP development server
php -S localhost:8000 -t public

# Run migrations
php migrations/run.php
```

#### Creating New Features

#### 1. Create Use Case

```php
// src/Domain/UseCases/Books/GetBookDetailsUseCase.php
<?php
declare(strict_types=1);

namespace App\Domain\UseCases\Books;

use App\Domain\Repository\BookRepositoryInterface;
use App\Domain\Exceptions\BookNotFoundException;

class GetBookDetailsUseCase
{
    public function __construct(
        private BookRepositoryInterface $bookRepository
    ) {}

    public function execute(int $bookId, int $userId): GetBookDetailsResponse
    {
        $book = $this->bookRepository->findByIdAndUser($bookId, $userId);
        
        if (!$book) {
            throw new BookNotFoundException("Book with ID {$bookId} not found");
        }

        return new GetBookDetailsResponse($book);
    }
}
```

#### 2. Register in DI Container

```php
// config/dependencies.php
GetBookDetailsUseCase::class => autowire(),
```

#### 3. Add Controller Method

```php
// src/Controllers/BookController.php
private function getBookDetails(array $data): array
{
    $bookId = (int) ($data['id'] ?? 0);
    $userId = $this->sessionManager->getUserId();

    try {
        $response = $this->getBookDetailsUseCase->execute($bookId, $userId);
        return $this->successResponse($response->toArray());
    } catch (BookNotFoundException $e) {
        return $this->errorResponse($e->getMessage(), 'BOOK_NOT_FOUND', 404);
    }
}
```

#### 4. Update Router

```php
// src/Services/ApplicationService.php
case 'details':
    return $this->getBookDetails($data);
```

## Database Management

### Migrations

```php
// migrations/001_create_books_table.php
<?php

return [
    'up' => '
        CREATE TABLE books (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            author VARCHAR(255) NOT NULL,
            isbn VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ',
    'down' => 'DROP TABLE books;'
];
```

### Running Migrations

```bash
# Run all pending migrations
docker-compose exec backend php migrations/run.php

# Rollback last migration
docker-compose exec backend php migrations/rollback.php

# Create new migration
docker-compose exec backend php migrations/create.php CreateBooksTable
```

### Database Seeding

```php
// seeders/BookSeeder.php
<?php

class BookSeeder
{
    public function run(PDO $pdo): void
    {
        $stmt = $pdo->prepare('
            INSERT INTO books (user_id, title, author, isbn) 
            VALUES (?, ?, ?, ?)
        ');

        $books = [
            [1, 'The Hobbit', 'J.R.R. Tolkien', '9780547928227'],
            [1, '1984', 'George Orwell', '9780451524935'],
            // Add more books...
        ];

        foreach ($books as $book) {
            $stmt->execute($book);
        }
    }
}
```

## Testing

### Backend Testing with PHPUnit

#### Test Structure

```php
// tests/Unit/UseCases/Books/AddBookUseCaseTest.php
<?php

use PHPUnit\Framework\TestCase;
use App\Domain\UseCases\Books\AddBookUseCase;
use App\Domain\Repository\BookRepositoryInterface;

class AddBookUseCaseTest extends TestCase
{
    private AddBookUseCase $useCase;
    private BookRepositoryInterface $mockRepository;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(BookRepositoryInterface::class);
        $this->useCase = new AddBookUseCase($this->mockRepository);
    }

    public function testExecuteWithValidData(): void
    {
        // Arrange
        $request = new AddBookRequest('Test Book', 'Test Author', null, null, 1);
        
        $this->mockRepository
            ->expects($this->once())
            ->method('save')
            ->willReturn(true);

        // Act
        $response = $this->useCase->execute($request);

        // Assert
        $this->assertInstanceOf(AddBookResponse::class, $response);
        $this->assertEquals('Test Book', $response->getBook()->getTitle());
    }

    public function testExecuteWithInvalidData(): void
    {
        // Test error scenarios
        $this->expectException(ValidationException::class);
        
        $request = new AddBookRequest('', '', null, null, 1);
        $this->useCase->execute($request);
    }
}
```

#### Running Tests

```bash
# Run all tests
docker-compose exec backend vendor/bin/phpunit

# Run specific test suite
docker-compose exec backend vendor/bin/phpunit --testsuite=Unit

# Run with coverage
docker-compose exec backend vendor/bin/phpunit --coverage-html coverage/
```

### Frontend Testing with Vitest

#### Unit Test Example

```javascript
// tests/unit/components/BookCard.test.js
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import BookCard from '@/components/library/BookCard.vue'

describe('BookCard', () => {
  const mockBook = {
    id: 1,
    title: 'Test Book',
    author: 'Test Author',
    status: 'read',
    rating: 4
  }

  it('renders book information correctly', () => {
    const wrapper = mount(BookCard, {
      props: { book: mockBook }
    })

    expect(wrapper.text()).toContain('Test Book')
    expect(wrapper.text()).toContain('Test Author')
  })

  it('emits updated event when status changes', async () => {
    const wrapper = mount(BookCard, {
      props: { book: mockBook }
    })

    await wrapper.find('[data-testid="status-button"]').trigger('click')
    
    expect(wrapper.emitted('updated')).toBeTruthy()
  })
})
```

#### Composable Testing

```javascript
// tests/unit/composables/useBooks.test.js
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { useBooks } from '@/composables/useBooks'
import { createPinia, setActivePinia } from 'pinia'

describe('useBooks', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('fetches books successfully', async () => {
    // Mock API response
    vi.mock('@/services/bookService', () => ({
      bookService: {
        getBooks: vi.fn().mockResolvedValue({
          data: {
            books: [{ id: 1, title: 'Test Book' }],
            pagination: { current_page: 1, total: 1 }
          }
        })
      }
    }))

    const { fetchBooks, books, loading } = useBooks()

    expect(loading.value).toBe(false)
    
    await fetchBooks()
    
    expect(books.value).toHaveLength(1)
    expect(books.value[0].title).toBe('Test Book')
  })
})
```

## Code Style & Standards

### PHP Code Standards

#### PSR-12 Compliance

```php
<?php

declare(strict_types=1);

namespace App\Domain\Model;

class Book
{
    public function __construct(
        private int $id,
        private string $title,
        private string $author,
        private ?string $isbn = null
    ) {}

    public function getTitle(): string
    {
        return $this->title;
    }

    public function updateTitle(string $title): void
    {
        if (empty($title)) {
            throw new \InvalidArgumentException('Title cannot be empty');
        }
        
        $this->title = $title;
    }
}
```

#### Type Declarations

```php
// Always use strict types
declare(strict_types=1);

// Use type hints for all parameters and return types
public function findBooks(string $query, int $limit = 20): array
{
    // Implementation
}

// Use nullable types when appropriate
public function getBook(?int $id): ?Book
{
    return $id ? $this->repository->find($id) : null;
}
```

### JavaScript/Vue Standards

#### ESLint Configuration

```javascript
// .eslintrc.js
module.exports = {
  env: {
    browser: true,
    es2021: true,
    node: true
  },
  extends: [
    'eslint:recommended',
    '@vue/eslint-config-typescript',
    '@vue/eslint-config-prettier'
  ],
  rules: {
    // Vue specific rules
    'vue/component-name-in-template-casing': ['error', 'PascalCase'],
    'vue/component-definition-name-casing': ['error', 'PascalCase'],
    'vue/prop-name-casing': ['error', 'camelCase'],
    
    // General rules
    'prefer-const': 'error',
    'no-var': 'error',
    'object-shorthand': 'error'
  }
}
```

#### Naming Conventions

```javascript
// Components: PascalCase
const BookCard = defineComponent({})

// Composables: camelCase with 'use' prefix
function useBooks() {}

// Constants: SCREAMING_SNAKE_CASE
const API_BASE_URL = 'https://api.example.com'

// Variables and functions: camelCase
const bookTitle = 'Example Book'
const fetchBookData = () => {}
```

## Debugging

### Backend Debugging

#### Xdebug Setup

```dockerfile
# Add to Dockerfile.backend.dev
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY docker/backend/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
```

```ini
; docker/backend/xdebug.ini
zend_extension=xdebug
xdebug.mode=debug
xdebug.client_host=host.docker.internal
xdebug.client_port=9003
xdebug.start_with_request=yes
```

#### Error Logging

```php
// Enable detailed error logging in development
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/html/storage/logs/php-errors.log');
```

### Frontend Debugging

#### Vue DevTools

Install the Vue DevTools browser extension for component inspection and state debugging.

#### Console Debugging

```javascript
// Development-only debugging
if (import.meta.env.DEV) {
  console.log('Debug info:', data)
  console.table(books)
}

// Better debugging with labels
console.group('API Request')
console.log('URL:', url)
console.log('Data:', data)
console.groupEnd()
```

## Performance Optimization

### Backend Performance

#### Database Optimization

```sql
-- Add indexes for common queries
CREATE INDEX idx_books_user_status ON books(user_id, status);
CREATE INDEX idx_books_title ON books(title);
CREATE INDEX idx_movies_user_year ON movies(user_id, year);
```

#### Caching Strategy

```php
// Simple file-based caching
class SimpleCache
{
    private string $cacheDir;

    public function get(string $key, callable $callback, int $ttl = 3600)
    {
        $file = $this->cacheDir . '/' . md5($key) . '.cache';
        
        if (file_exists($file) && (time() - filemtime($file)) < $ttl) {
            return unserialize(file_get_contents($file));
        }
        
        $data = $callback();
        file_put_contents($file, serialize($data));
        
        return $data;
    }
}
```

### Frontend Performance

#### Code Splitting

```javascript
// Lazy load routes
const routes = [
  {
    path: '/books',
    component: () => import('@/views/BooksView.vue')
  },
  {
    path: '/movies',
    component: () => import('@/views/MoviesView.vue')
  }
]
```

#### Optimized Images

```vue
<template>
  <!-- Use modern image formats -->
  <picture>
    <source srcset="image.webp" type="image/webp">
    <source srcset="image.avif" type="image/avif">
    <img src="image.jpg" alt="Book cover" loading="lazy">
  </picture>
</template>
```

## Troubleshooting

### Common Issues

#### Docker Issues

```bash
# Reset Docker environment
docker-compose down -v
docker system prune -f
docker-compose up --build

# Check container logs
docker-compose logs backend
docker-compose logs frontend

# Access container shell
docker-compose exec backend bash
docker-compose exec frontend sh
```

#### Database Connection Issues

```bash
# Test database connection
docker-compose exec backend php -r "
  try {
    \$pdo = new PDO('mysql:host=database;dbname=library_db', 'library_user', 'library_pass');
    echo 'Database connection successful\n';
  } catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage() . '\n';
  }
"
```

#### Frontend Build Issues

```bash
# Clear node modules and reinstall
cd frontend
rm -rf node_modules package-lock.json
npm install

# Clear Vite cache
npx vite --force
```

## Contributing

### Git Workflow

```bash
# Create feature branch
git checkout -b feature/add-book-ratings

# Make changes and commit
git add .
git commit -m "feat: add book rating system"

# Push branch
git push origin feature/add-book-ratings

# Create pull request on GitHub
```

### Commit Message Convention

```
type(scope): description

feat(books): add rating system for books
fix(auth): resolve session timeout issue
docs(api): update authentication endpoints
style(frontend): improve button component styling
refactor(backend): extract validation logic
test(books): add unit tests for BookService
```

---

*Documentación actualizada: 18 de Agosto de 2025*
