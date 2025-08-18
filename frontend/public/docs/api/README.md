# API Documentation - Library Vue

## Overview

Esta documentación describe la API REST del sistema Library Vue, que permite gestionar una biblioteca personal de libros y películas con funcionalidades de autenticación, búsqueda e importación de archivos.

## Base URL

```
Local Development: http://localhost:8080/api
Production: [URL del servidor de producción]
```

## Authentication

El sistema utiliza autenticación basada en sesiones con soporte para Google OAuth.

### Headers Requeridos

```http
Content-Type: application/json
```

### Session Management

Las sesiones se mantienen automáticamente mediante cookies. El estado de autenticación se verifica en cada request protegido.

## API Endpoints

### 📚 [Books API](./books.md)
- Gestión completa de libros
- Búsqueda y filtrado
- CRUD operations

### 🎬 [Movies API](./movies.md) 
- Gestión de películas
- Búsqueda y categorización
- CRUD operations

### 🔐 [Authentication API](./auth.md)
- Login/Logout
- Google OAuth integration
- Session management

### 📂 [Library API](./library.md)
- Vista unificada de biblioteca
- Importación de archivos
- Estadísticas generales

## Error Handling

Todos los endpoints devuelven respuestas JSON estructuradas:

### Success Response
```json
{
  "success": true,
  "data": {},
  "message": "Operación exitosa"
}
```

### Error Response
```json
{
  "success": false,
  "error": "Descripción del error",
  "code": "ERROR_CODE",
  "details": {}
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | OK - Operación exitosa |
| 201 | Created - Recurso creado |
| 400 | Bad Request - Error en parámetros |
| 401 | Unauthorized - No autenticado |
| 403 | Forbidden - Sin permisos |
| 404 | Not Found - Recurso no encontrado |
| 409 | Conflict - Conflicto de datos |
| 422 | Unprocessable Entity - Error de validación |
| 500 | Internal Server Error - Error del servidor |

## Rate Limiting

- **Requests por minuto**: 60 por IP
- **Requests por usuario**: 100 por minuto
- **Headers de respuesta**:
  - `X-RateLimit-Limit`: Límite total
  - `X-RateLimit-Remaining`: Requests restantes
  - `X-RateLimit-Reset`: Timestamp de reset

## Data Formats

### Dates
Todas las fechas se devuelven en formato ISO 8601:
```json
{
  "created_at": "2025-08-18T10:30:00Z",
  "updated_at": "2025-08-18T15:45:30Z"
}
```

### Pagination
Los endpoints que devuelven listas soportan paginación:

```json
{
  "data": [],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "total_pages": 8,
    "has_next_page": true,
    "has_prev_page": false
  }
}
```

## Development Tools

### Testing the API

```bash
# Health check
curl -X GET http://localhost:8080/api/health

# Login example
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password"}'
```

### Postman Collection

Importa la colección de Postman desde: `./postman/Library-Vue-API.postman_collection.json`

## Changelog

### Version 2.0 (August 2025)
- ✅ Dependency Injection refactoring completed
- ✅ Improved error handling and logging
- ✅ Enhanced session management
- ✅ Better response structures

### Version 1.0 (Previous)
- Initial API implementation
- Basic CRUD operations
- Google OAuth integration

---

*Documentación actualizada: 18 de Agosto de 2025*
