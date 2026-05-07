# Sistema de Logging Estructurado

## Descripción

Este proyecto implementa un sistema de logging estructurado usando **Monolog** que proporciona logging profesional con contexto JSON, rotación automática de archivos y configuración flexible para diferentes entornos.

## Características

- ✅ **Logging estructurado** con contexto JSON
- ✅ **Rotación automática** de archivos de log
- ✅ **Múltiples canales** (API, Database, Auth, Security, Application)
- ✅ **Configuración por entorno** (development, testing, staging, production)
- ✅ **Handlers múltiples** (archivo, consola, navegador)
- ✅ **Helpers de conveniencia** para diferentes tipos de eventos
- ✅ **Integración con manejo de errores** de PHP
- ✅ **Logging de performance** y métricas

## Instalación

El sistema ya está instalado y configurado. Solo necesitas:

1. Copiar el archivo de configuración de entorno:
```bash
cp .env.example .env.development
```

2. Ajustar las variables de entorno según tu necesidad:
```bash
LOG_LEVEL=debug
LOG_CONSOLE=true
LOG_JSON_FORMAT=false
```

## Estructura de Archivos

```
backend/
├── config/
│   ├── logging.php          # Configuración de logging
│   └── helpers.php          # Funciones helper para configuración
├── src/Infrastructure/Logging/
│   ├── LoggerFactory.php    # Factory para crear loggers
│   ├── LogHelper.php        # Helper con métodos de conveniencia
│   ├── LoggingService.php   # Servicio centralizado
│   └── functions.php        # Funciones globales de logging
├── storage/logs/            # Directorio de archivos de log
├── .env.example            # Variables de entorno de ejemplo
├── .env.development        # Configuración para desarrollo
└── logging_examples.php    # Ejemplos de uso
```

## Configuración

### Variables de Entorno

```bash
# Configuración básica
APP_ENV=development
LOG_LEVEL=debug
LOG_PATH=./storage/logs
LOG_MAX_FILES=7
LOG_CONSOLE=true
LOG_JSON_FORMAT=false

# Configuración por canal
LOG_LEVEL_API=debug
LOG_LEVEL_DATABASE=info
LOG_LEVEL_AUTH=debug
LOG_LEVEL_SECURITY=warning
LOG_LEVEL_APPLICATION=debug
```

### Niveles de Log

- `debug` - Información detallada para debugging
- `info` - Información general de funcionamiento
- `notice` - Eventos normales pero significativos
- `warning` - Advertencias que no son errores
- `error` - Errores que no detienen la aplicación
- `critical` - Errores críticos
- `alert` - Acción inmediata requerida
- `emergency` - Sistema inutilizable

## Uso Básico

### Funciones de Conveniencia

```php
// Logging básico
log_info('Usuario autenticado exitosamente');
log_error('Error al conectar con la base de datos');
log_debug('Variables de configuración cargadas');
log_warning('Memoria alta detectada');

// Logging de excepciones
try {
    // código que puede fallar
} catch (\Exception $e) {
    log_exception($e, 'Error en procesamiento de datos');
}
```

### Logging con Contexto

```php
// Obtener logger específico
$logger = logger('api');

$logger->info('Nueva búsqueda realizada', [
    'user_id' => 123,
    'search_term' => 'Harry Potter',
    'results_count' => 15,
    'filters' => ['author' => 'J.K. Rowling']
]);
```

## Logging Especializado

### HTTP Requests y Responses

```php
$logger = logger('api');

// Log de request
$logger->httpRequest('POST', '/api/books', [
    'content_length' => 1024,
    'auth_type' => 'google_oauth'
]);

// Log de response
$logger->httpResponse(200, $duration, [
    'action' => 'add_book',
    'response_size' => 512
]);
```

### Base de Datos

```php
$logger = logger('database');

$logger->database('SELECT', 'books', $duration, [
    'query' => 'SELECT * FROM books WHERE user_id = ?',
    'params' => [123],
    'result_count' => 42
]);
```

### Autenticación

```php
$logger = logger('auth');

// Login exitoso
$logger->auth('login', $userId, true, [
    'provider' => 'google',
    'first_login' => false
]);

// Login fallido
$logger->auth('login', null, false, [
    'error' => 'invalid_credentials'
]);
```

### Seguridad

```php
$logger = logger('security');

$logger->security('multiple_failed_login_attempts', 'medium', [
    'attempts_count' => 5,
    'time_window' => '5 minutes',
    'ip_address' => '192.168.1.100'
]);
```

### Performance

```php
$logger = logger('application');

$logger->performance('book_search', $duration, [
    'database_queries' => 3,
    'external_api_calls' => 1,
    'cache_hits' => 5
]);
```

## Canales de Logging

### Canal API (`api.log`)
- Requests HTTP
- Responses HTTP
- Errores de API
- Validaciones de entrada

### Canal Database (`database.log`)
- Consultas SQL
- Conexiones a BD
- Errores de base de datos
- Performance de queries

### Canal Auth (`auth.log`)
- Autenticación de usuarios
- Autorizaciones
- Sesiones
- Tokens JWT

### Canal Security (`security.log`)
- Intentos de acceso no autorizado
- Rate limiting
- Ataques detectados
- Eventos sospechosos

### Canal Application (`application.log`)
- Lógica de negocio
- Use cases
- Errores de aplicación
- Métricas de performance

## Integración en Controladores

```php
class BookController
{
    public function search(Request $request)
    {
        $startTime = microtime(true);
        $logger = logger('api');
        
        $logger->httpRequest($request->getMethod(), $request->getUri());
        
        try {
            $results = $this->bookService->search($request->getSearchTerm());
            
            $logger->info('Book search completed', [
                'search_term' => $request->getSearchTerm(),
                'results_count' => count($results)
            ]);
            
            $duration = microtime(true) - $startTime;
            $logger->httpResponse(200, $duration);
            
            return $this->jsonResponse(['books' => $results]);
            
        } catch (\Exception $e) {
            $logger->exception($e, 'Error in book search');
            $logger->httpResponse(500, microtime(true) - $startTime);
            
            return $this->errorResponse('Search failed');
        }
    }
}
```

## Integración en Use Cases

```php
class AddBookUseCase
{
    public function execute(array $bookData): Book
    {
        $logger = logger('application');
        
        $logger->info('Adding new book', [
            'title' => $bookData['title'],
            'author' => $bookData['author']
        ]);
        
        try {
            $book = $this->bookRepository->save(new Book($bookData));
            
            $logger->info('Book added successfully', [
                'book_id' => $book->getId(),
                'title' => $book->getTitle()
            ]);
            
            return $book;
            
        } catch (\Exception $e) {
            $logger->exception($e, 'Failed to add book', $bookData);
            throw $e;
        }
    }
}
```

## Archivos de Log

Los logs se organizan automáticamente en archivos separados:

```
storage/logs/
├── app.log              # Log general de aplicación
├── api.log              # Logs de API
├── database.log         # Logs de base de datos
├── auth.log             # Logs de autenticación
├── security.log         # Logs de seguridad
├── application.log      # Logs de lógica de negocio
└── errors.log           # Todos los errores (ERROR y superior)
```

### Rotación de Archivos

Los archivos se rotan automáticamente:
- Mantiene 7 días por defecto (configurable)
- Archivos antiguos se comprimen automáticamente
- Formato: `app.log`, `app-2024-08-15.log`, `app-2024-08-14.log`, etc.

## Configuración por Entorno

### Desarrollo
- Nivel: `DEBUG`
- Formato: Texto legible
- Salida: Archivo + Consola + Navegador

### Testing
- Nivel: `WARNING`
- Formato: JSON
- Salida: Solo archivo

### Producción
- Nivel: `INFO`
- Formato: JSON
- Salida: Solo archivo
- Rotación: 30 días

## Ejemplos de Logs

### Formato JSON (Producción)
```json
{
  "message": "Book search completed",
  "context": {
    "search_term": "Harry Potter",
    "results_count": 15,
    "user_id": 123
  },
  "level": 200,
  "level_name": "INFO",
  "channel": "api",
  "datetime": "2024-08-16T10:30:45.123456+00:00",
  "extra": {
    "memory_usage": 12345678,
    "ip": "192.168.1.100",
    "request_id": "req_66c0123456789"
  }
}
```

### Formato Texto (Desarrollo)
```
2024-08-16 10:30:45 [INFO] api: Book search completed {"search_term":"Harry Potter","results_count":15,"user_id":123}
```

## Mejores Prácticas

1. **Usa contexto estructurado** siempre que sea posible
2. **No logees información sensible** (passwords, tokens completos)
3. **Usa el canal apropiado** para cada tipo de evento
4. **Incluye IDs únicos** para trackear operaciones
5. **Logea tanto éxitos como fallos** importantes
6. **Usa niveles apropiados** (no todo es ERROR)
7. **Incluye timing** para operaciones importantes

## Monitoreo y Alertas

Puedes configurar alertas basadas en los logs:

```bash
# Buscar errores críticos
grep "CRITICAL\|EMERGENCY" storage/logs/*.log

# Buscar eventos de seguridad
grep "security" storage/logs/security.log

# Monitorear performance
grep "duration_ms.*[5-9][0-9][0-9][0-9]" storage/logs/api.log
```

## Testing

Para testing, puedes usar:

```php
// En tests
use App\Infrastructure\Logging\LoggingService;

class SomeTest extends TestCase
{
    protected function setUp(): void
    {
        LoggingService::reset(); // Limpiar estado
    }
}
```
