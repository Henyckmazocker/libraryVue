# Architecture Documentation - Library Vue

## Overview

Library Vue es una aplicación full-stack para gestión de biblioteca personal que combina un backend PHP con dependency injection y un frontend Vue.js moderno. La arquitectura sigue principios de Clean Architecture y Domain-Driven Design.

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Frontend (Vue.js)                    │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Views      │  │  Components  │  │ Composables  │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Router     │  │    Store     │  │   Services   │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                              │ HTTP/REST API
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                     Backend (PHP)                          │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │ Controllers  │  │ Middleware   │  │    Router    │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  Use Cases   │  │    Domain    │  │    Services  │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │Repositories  │  │Infrastructure│  │   Database   │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Database (MySQL)                        │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │    Users     │  │    Books     │  │   Movies     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
```

## Backend Architecture

### Architectural Patterns

#### Clean Architecture
La aplicación sigue los principios de Clean Architecture con capas claramente definidas:

1. **Domain Layer** - Lógica de negocio pura
2. **Application Layer** - Casos de uso y orchestración
3. **Infrastructure Layer** - Implementaciones técnicas
4. **Presentation Layer** - Controllers y API endpoints

#### Dependency Injection
Utiliza PHP-DI para gestión de dependencias:

- Container configurado en `config/dependencies.php`
- Autowiring para resolución automática
- Lazy loading para optimización de recursos
- Factory patterns para servicios complejos

### Directory Structure

```
backend/
├── config/                    # Configuration files
│   ├── dependencies.php       # DI container configuration
│   ├── logging.php            # Logging configuration
│   └── helpers.php            # Helper functions
├── src/
│   ├── Controllers/           # HTTP request handlers
│   │   ├── BaseController.php
│   │   ├── AuthController.php
│   │   ├── BookController.php
│   │   ├── MovieController.php
│   │   └── LibraryController.php
│   ├── Domain/                # Business logic layer
│   │   ├── Model/             # Domain entities
│   │   ├── Repository/        # Repository interfaces
│   │   └── UseCases/          # Business use cases
│   ├── Infrastructure/        # Technical implementations
│   │   ├── Database/          # Database connections
│   │   ├── Logging/           # Logging implementations
│   │   ├── Middleware/        # HTTP middleware
│   │   ├── Persistence/       # Repository implementations
│   │   └── Session/           # Session management
│   ├── Router/                # Routing system
│   └── Services/              # Application services
├── storage/                   # Storage directory
│   ├── logs/                  # Application logs
│   ├── cache/                 # Cache files
│   └── uploads/               # File uploads
└── bootstrap.php              # Application bootstrap
```

### Dependency Injection Container

#### Service Registration

```php
// PDO Database Connection (Lazy Loading)
PDO::class => function (ContainerInterface $c) {
    $dbConnector = $c->get(DatabaseConnector::class);
    return $dbConnector->getConnection();
},

// Repository Interfaces
UserRepositoryInterface::class => autowire(MySqlUserRepository::class),
BookRepositoryInterface::class => autowire(MySqlBookRepository::class),
MovieRepositoryInterface::class => autowire(MySqlMovieRepository::class),

// Use Cases
LoginUserUseCase::class => autowire(),
AddBookUseCase::class => autowire(),
GetBooksUseCase::class => autowire(),

// Controllers
AuthController::class => autowire(),
BookController::class => autowire(),
MovieController::class => autowire(),
```

#### Benefits of DI Implementation

1. **Testability** - Easy mocking of dependencies
2. **Maintainability** - Loose coupling between components
3. **Scalability** - Easy to add new services
4. **Configuration** - Centralized dependency configuration

### Request Flow

1. **HTTP Request** arrives at `bootstrap.php`
2. **ApplicationService** initializes DI container
3. **Router** matches request to controller action
4. **Controller** handles request using injected dependencies
5. **Use Cases** execute business logic
6. **Repositories** handle data persistence
7. **Response** returned as JSON

### Logging System

#### Multi-Channel Logging

- **app**: General application logs
- **api**: API request/response logs
- **database**: Database query logs
- **auth**: Authentication logs
- **security**: Security-related logs
- **errors**: Error and exception logs
- **frontend**: Frontend-related logs

#### Log Structure

```json
{
  "timestamp": "2025-08-18T10:30:00Z",
  "level": "INFO",
  "channel": "api",
  "message": "User login successful",
  "context": {
    "user_id": 123,
    "ip_address": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "request_id": "req_abc123"
  }
}
```

## Frontend Architecture

### Vue.js 3 Architecture

#### Composition API
Utiliza Composition API para mejor reutilización de lógica:

```javascript
// Composable example
export function useAuth() {
  const user = ref(null)
  const isAuthenticated = computed(() => !!user.value)
  
  const login = async (credentials) => {
    // Login logic
  }
  
  return { user, isAuthenticated, login }
}
```

#### State Management with Pinia

```javascript
// Store example
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const isLoggedIn = computed(() => !!user.value)
  
  const login = async (credentials) => {
    // Login implementation
  }
  
  return { user, isLoggedIn, login }
})
```

### Frontend Directory Structure

```
frontend/
├── src/
│   ├── components/            # Reusable components
│   │   ├── ui/               # Basic UI components
│   │   ├── forms/            # Form components
│   │   └── layout/           # Layout components
│   ├── composables/          # Composition functions
│   │   ├── useAuth.js
│   │   ├── useBooks.js
│   │   └── useMovies.js
│   ├── router/               # Vue Router configuration
│   ├── services/             # API service layer
│   ├── stores/               # Pinia stores
│   ├── types/                # TypeScript type definitions
│   ├── utils/                # Utility functions
│   └── views/                # Page components
├── public/                   # Static assets
└── tests/                    # Test files
```

## Database Design

### Entity Relationship Diagram

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│      Users      │       │      Books      │       │     Movies      │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id (PK)         │       │ id (PK)         │       │ id (PK)         │
│ email           │◄──────│ user_id (FK)    │       │ user_id (FK)    │──────►│
│ name            │       │ title           │       │ title           │
│ google_id       │       │ author          │       │ director        │
│ password_hash   │       │ isbn            │       │ year            │
│ created_at      │       │ genre           │       │ genre           │
│ updated_at      │       │ publication_year│       │ duration        │
└─────────────────┘       │ pages           │       │ status          │
                          │ status          │       │ rating          │
                          │ rating          │       │ date_watched    │
                          │ date_read       │       │ poster          │
                          │ cover_image     │       │ synopsis        │
                          │ summary         │       │ notes           │
                          │ notes           │       │ created_at      │
                          │ created_at      │       │ updated_at      │
                          │ updated_at      │       └─────────────────┘
                          └─────────────────┘
```

### Database Schema

#### Users Table
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    google_id VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### Books Table
```sql
CREATE TABLE books (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    isbn VARCHAR(20),
    genre VARCHAR(100),
    publication_year INT,
    pages INT,
    status ENUM('to-read', 'reading', 'read', 'abandoned') DEFAULT 'to-read',
    rating INT CHECK (rating >= 1 AND rating <= 5),
    date_read DATE,
    cover_image TEXT,
    summary TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### Movies Table
```sql
CREATE TABLE movies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    director VARCHAR(255) NOT NULL,
    year INT NOT NULL,
    genre VARCHAR(100),
    duration INT,
    status ENUM('to-watch', 'watched', 'abandoned') DEFAULT 'to-watch',
    rating INT CHECK (rating >= 1 AND rating <= 5),
    date_watched DATE,
    poster TEXT,
    synopsis TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Security Architecture

### Authentication & Authorization

1. **Session-based Authentication**
   - HTTP-only cookies
   - CSRF protection
   - Session timeout (24 hours)

2. **Google OAuth Integration**
   - OAuth 2.0 flow
   - Token validation
   - User profile sync

3. **Password Security**
   - bcrypt hashing
   - Salt generation
   - Minimum complexity requirements

### API Security

1. **Input Validation**
   - Request sanitization
   - Type checking
   - Length limitations

2. **Rate Limiting**
   - Per-IP limits
   - Per-user limits
   - Endpoint-specific limits

3. **CORS Configuration**
   - Allowed origins
   - Preflight handling
   - Credential support

## Performance Considerations

### Backend Optimizations

1. **Database Optimization**
   - Proper indexing
   - Query optimization
   - Connection pooling

2. **Caching Strategy**
   - Application-level caching
   - Database query caching
   - Static asset caching

3. **Lazy Loading**
   - Service instantiation
   - Database connections
   - Heavy dependencies

### Frontend Optimizations

1. **Code Splitting**
   - Route-based splitting
   - Component lazy loading
   - Dynamic imports

2. **Asset Optimization**
   - Image compression
   - Bundle minimization
   - Tree shaking

3. **State Management**
   - Efficient updates
   - Computed properties
   - Reactive optimization

## Deployment Architecture

### Development Environment
- Docker Compose setup
- Hot reloading
- Debug logging
- Development database

### Production Environment
- Container orchestration
- Load balancing
- SSL termination
- Production database with replication

### CI/CD Pipeline
- Automated testing
- Code quality checks
- Security scanning
- Automated deployment

---

*Documentación actualizada: 18 de Agosto de 2025*
