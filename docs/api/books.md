# Books API

## Overview

Endpoints para la gestión completa de libros en la biblioteca personal.

## Endpoints

### Get All Books

**GET** `/api/books`

Obtiene lista paginada de todos los libros.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Número de página |
| `limit` | integer | 20 | Elementos por página |
| `search` | string | - | Búsqueda por título o autor |
| `genre` | string | - | Filtrar por género |
| `status` | string | - | Filtrar por estado (read, reading, to-read) |

#### Example Request

```
GET /api/books?page=1&limit=10&search=harry&genre=fantasy
```

#### Response

```json
{
  "success": true,
  "data": {
    "books": [
      {
        "id": 1,
        "title": "Harry Potter and the Sorcerer's Stone",
        "author": "J.K. Rowling",
        "isbn": "9780439708180",
        "genre": "Fantasy",
        "publication_year": 1997,
        "pages": 309,
        "status": "read",
        "rating": 5,
        "cover_image": "https://example.com/covers/hp1.jpg",
        "summary": "A young wizard's journey begins...",
        "date_read": "2025-01-15",
        "created_at": "2025-01-10T10:30:00Z",
        "updated_at": "2025-01-16T15:45:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "total": 150,
      "total_pages": 15,
      "has_next_page": true,
      "has_prev_page": false
    }
  }
}
```

### Get Book by ID

**GET** `/api/books/{id}`

Obtiene un libro específico por su ID.

#### Response

```json
{
  "success": true,
  "data": {
    "book": {
      "id": 1,
      "title": "Harry Potter and the Sorcerer's Stone",
      "author": "J.K. Rowling",
      "isbn": "9780439708180",
      "genre": "Fantasy",
      "publication_year": 1997,
      "pages": 309,
      "status": "read",
      "rating": 5,
      "cover_image": "https://example.com/covers/hp1.jpg",
      "summary": "A young wizard's journey begins...",
      "date_read": "2025-01-15",
      "notes": "Excellent book, great start to the series",
      "created_at": "2025-01-10T10:30:00Z",
      "updated_at": "2025-01-16T15:45:00Z"
    }
  }
}
```

### Create Book

**POST** `/api/books`

Crea un nuevo libro en la biblioteca.

#### Request Body

```json
{
  "title": "The Hobbit",
  "author": "J.R.R. Tolkien",
  "isbn": "9780547928227",
  "genre": "Fantasy",
  "publication_year": 1937,
  "pages": 366,
  "status": "to-read",
  "cover_image": "https://example.com/covers/hobbit.jpg",
  "summary": "A hobbit's unexpected journey..."
}
```

#### Response

```json
{
  "success": true,
  "data": {
    "book": {
      "id": 2,
      "title": "The Hobbit",
      "author": "J.R.R. Tolkien",
      "isbn": "9780547928227",
      "genre": "Fantasy",
      "publication_year": 1937,
      "pages": 366,
      "status": "to-read",
      "rating": null,
      "cover_image": "https://example.com/covers/hobbit.jpg",
      "summary": "A hobbit's unexpected journey...",
      "date_read": null,
      "created_at": "2025-08-18T10:30:00Z",
      "updated_at": "2025-08-18T10:30:00Z"
    }
  },
  "message": "Libro creado exitosamente"
}
```

### Update Book

**PUT** `/api/books/{id}`

Actualiza un libro existente.

#### Request Body

```json
{
  "status": "read",
  "rating": 4,
  "date_read": "2025-08-18",
  "notes": "Great adventure story, perfect introduction to Middle-earth"
}
```

#### Response

```json
{
  "success": true,
  "data": {
    "book": {
      "id": 2,
      "title": "The Hobbit",
      "author": "J.R.R. Tolkien",
      "status": "read",
      "rating": 4,
      "date_read": "2025-08-18",
      "notes": "Great adventure story, perfect introduction to Middle-earth",
      "updated_at": "2025-08-18T14:20:00Z"
    }
  },
  "message": "Libro actualizado exitosamente"
}
```

### Delete Book

**DELETE** `/api/books/{id}`

Elimina un libro de la biblioteca.

#### Response

```json
{
  "success": true,
  "message": "Libro eliminado exitosamente"
}
```

### Search Books

**GET** `/api/books/search`

Búsqueda avanzada de libros.

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Término de búsqueda general |
| `title` | string | Búsqueda específica por título |
| `author` | string | Búsqueda específica por autor |
| `isbn` | string | Búsqueda por ISBN |
| `genre` | string | Filtrar por género |
| `year_from` | integer | Año de publicación desde |
| `year_to` | integer | Año de publicación hasta |
| `rating_min` | integer | Rating mínimo (1-5) |
| `status` | string | Estado del libro |

#### Example Request

```
GET /api/books/search?q=tolkien&genre=fantasy&rating_min=4
```

### Get Book Statistics

**GET** `/api/books/stats`

Obtiene estadísticas de la colección de libros.

#### Response

```json
{
  "success": true,
  "data": {
    "stats": {
      "total_books": 150,
      "books_read": 89,
      "books_reading": 3,
      "books_to_read": 58,
      "total_pages": 45280,
      "average_rating": 4.2,
      "genres": {
        "Fantasy": 45,
        "Science Fiction": 32,
        "Mystery": 28,
        "Romance": 20,
        "Non-Fiction": 25
      },
      "reading_stats": {
        "books_this_year": 23,
        "pages_this_year": 7850,
        "average_pages_per_book": 342
      }
    }
  }
}
```

## Book Status Values

| Status | Description |
|--------|-------------|
| `to-read` | Libro pendiente de leer |
| `reading` | Libro actualmente leyendo |
| `read` | Libro ya leído |
| `abandoned` | Libro abandonado/no terminado |

## Validation Rules

### Required Fields
- `title`: Mínimo 1 carácter, máximo 255
- `author`: Mínimo 1 carácter, máximo 255

### Optional Fields
- `isbn`: Debe ser ISBN-10 o ISBN-13 válido
- `genre`: Máximo 100 caracteres
- `publication_year`: Entre 1000 y año actual + 1
- `pages`: Número entero positivo
- `rating`: Entre 1 y 5
- `status`: Uno de los valores válidos

## Error Codes

| Code | Description |
|------|-------------|
| `BOOK_NOT_FOUND` | Libro no encontrado |
| `DUPLICATE_ISBN` | ISBN ya existe en la biblioteca |
| `INVALID_ISBN` | Formato de ISBN inválido |
| `INVALID_RATING` | Rating debe estar entre 1 y 5 |
| `INVALID_STATUS` | Estado de libro inválido |
| `VALIDATION_ERROR` | Error en validación de campos |

---

*Última actualización: 18 de Agosto de 2025*
