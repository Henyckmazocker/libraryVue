# Library API

## Overview

Endpoints para gestión general de la biblioteca, incluyendo vistas unificadas, importación de archivos y estadísticas generales.

## Endpoints

### Get Library Overview

**GET** `/api/library`

Obtiene una vista unificada de toda la biblioteca con resumen de libros y películas.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | integer | 10 | Elementos por categoría |
| `include_recent` | boolean | true | Incluir elementos recientes |
| `include_stats` | boolean | true | Incluir estadísticas |

#### Response Example

```json
{
  "success": true,
  "data": {
    "overview": {
      "recent_books": [
        {
          "id": 1,
          "title": "The Hobbit",
          "author": "J.R.R. Tolkien",
          "status": "read",
          "rating": 4,
          "date_read": "2025-08-15"
        }
      ],
      "recent_movies": [
        {
          "id": 1,
          "title": "Inception",
          "director": "Christopher Nolan",
          "status": "watched",
          "rating": 5,
          "date_watched": "2025-08-10"
        }
      ],
      "stats": {
        "total_books": 150,
        "total_movies": 89,
        "books_read_this_month": 4,
        "movies_watched_this_month": 6,
        "total_reading_time": "2450 hours",
        "total_watch_time": "178 hours"
      },
      "reading_goals": {
        "books_goal_this_year": 50,
        "books_completed": 23,
        "progress_percentage": 46
      }
    }
  }
}
```

### Search All Library

**GET** `/api/library/search`

Búsqueda unificada en toda la biblioteca (libros y películas).

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Término de búsqueda general |
| `type` | string | Tipo de contenido: 'books', 'movies', 'all' |
| `limit` | integer | Límite de resultados por tipo |

#### Search Response

```json
{
  "success": true,
  "data": {
    "results": {
      "books": [
        {
          "id": 1,
          "type": "book",
          "title": "Harry Potter and the Sorcerer's Stone",
          "author": "J.K. Rowling",
          "genre": "Fantasy",
          "status": "read",
          "rating": 5
        }
      ],
      "movies": [
        {
          "id": 1,
          "type": "movie",
          "title": "Harry Potter and the Philosopher's Stone",
          "director": "Chris Columbus",
          "year": 2001,
          "status": "watched",
          "rating": 4
        }
      ],
      "total_results": 2,
      "search_term": "harry potter"
    }
  }
}
```

### Import from File

**POST** `/api/library/import`

Importa libros o películas desde un archivo CSV o JSON.

#### Request (Multipart Form)

```
Content-Type: multipart/form-data

file: [archivo CSV/JSON]
type: "books" | "movies"
options: {
  "skip_duplicates": true,
  "update_existing": false,
  "dry_run": false
}
```

#### Import Response

```json
{
  "success": true,
  "data": {
    "import_summary": {
      "total_rows": 150,
      "imported": 125,
      "skipped": 20,
      "errors": 5,
      "duplicates": 15,
      "processing_time": "2.34 seconds"
    },
    "errors": [
      {
        "row": 12,
        "error": "Invalid ISBN format",
        "data": {"title": "Invalid Book", "isbn": "invalid-isbn"}
      }
    ],
    "imported_items": [
      {
        "id": 151,
        "title": "Newly Imported Book",
        "author": "New Author",
        "status": "to-read"
      }
    ]
  },
  "message": "Importación completada: 125 elementos importados"
}
```

### Export Library

**GET** `/api/library/export`

Exporta la biblioteca completa o filtrada en formato CSV o JSON.

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `format` | string | Formato: 'csv', 'json' |
| `type` | string | Tipo: 'books', 'movies', 'all' |
| `status` | string | Filtrar por estado |
| `include_notes` | boolean | Incluir notas personales |

#### Export Response

```
Content-Type: application/csv / application/json
Content-Disposition: attachment; filename="library_export_2025-08-18.csv"

[Archivo CSV/JSON con los datos]
```

### Get Reading/Watching Activity

**GET** `/api/library/activity`

Obtiene actividad reciente de lectura y visualización.

#### Activity Response

```json
{
  "success": true,
  "data": {
    "activity": [
      {
        "date": "2025-08-18",
        "type": "book_completed",
        "item": {
          "id": 45,
          "title": "The Martian",
          "author": "Andy Weir"
        },
        "rating": 4,
        "notes": "Great science fiction read"
      },
      {
        "date": "2025-08-17",
        "type": "movie_watched",
        "item": {
          "id": 23,
          "title": "Blade Runner 2049",
          "director": "Denis Villeneuve"
        },
        "rating": 5
      }
    ],
    "timeline": {
      "books_this_week": 2,
      "movies_this_week": 3,
      "pages_read_this_week": 850,
      "hours_watched_this_week": 8.5
    }
  }
}
```

### Get Library Statistics

**GET** `/api/library/stats`

Estadísticas completas y detalladas de toda la biblioteca.

#### Complete Stats Response

```json
{
  "success": true,
  "data": {
    "stats": {
      "totals": {
        "books": 150,
        "movies": 89,
        "total_items": 239
      },
      "books": {
        "read": 89,
        "reading": 3,
        "to_read": 58,
        "total_pages": 45280,
        "average_rating": 4.2,
        "top_genres": ["Fantasy", "Science Fiction", "Mystery"]
      },
      "movies": {
        "watched": 56,
        "to_watch": 33,
        "total_runtime": 8940,
        "average_rating": 4.1,
        "top_genres": ["Science Fiction", "Action", "Drama"]
      },
      "yearly_progress": {
        "2025": {
          "books_read": 23,
          "movies_watched": 18,
          "pages_read": 7850,
          "hours_watched": 36
        }
      },
      "reading_goals": {
        "yearly_book_goal": 50,
        "books_completed": 23,
        "books_remaining": 27,
        "days_remaining": 135,
        "books_per_week_needed": 1.4
      }
    }
  }
}
```

### Set Reading Goals

**POST** `/api/library/goals`

Establece o actualiza metas de lectura/visualización.

#### Goals Request

```json
{
  "yearly_books_goal": 50,
  "yearly_movies_goal": 40,
  "monthly_books_goal": 4,
  "pages_per_day_goal": 25
}
```

#### Goals Response

```json
{
  "success": true,
  "data": {
    "goals": {
      "yearly_books_goal": 50,
      "yearly_movies_goal": 40,
      "monthly_books_goal": 4,
      "pages_per_day_goal": 25,
      "created_at": "2025-08-18T10:30:00Z"
    },
    "current_progress": {
      "books_progress": 46,
      "movies_progress": 45,
      "on_track": true
    }
  },
  "message": "Metas actualizadas exitosamente"
}
```

## Import File Formats

### CSV Format for Books

```csv
title,author,isbn,genre,publication_year,pages,status,rating
"The Hobbit","J.R.R. Tolkien","9780547928227","Fantasy",1937,366,"read",4
"1984","George Orwell","9780451524935","Dystopian",1949,328,"to-read",
```

### CSV Format for Movies

```csv
title,director,year,genre,duration,status,rating
"Inception","Christopher Nolan",2010,"Science Fiction",148,"watched",5
"The Matrix","The Wachowskis",1999,"Science Fiction",136,"to-watch",
```

### JSON Format

```json
{
  "books": [
    {
      "title": "The Hobbit",
      "author": "J.R.R. Tolkien",
      "isbn": "9780547928227",
      "genre": "Fantasy",
      "publication_year": 1937,
      "pages": 366,
      "status": "read",
      "rating": 4
    }
  ]
}
```

## Activity Types

| Type | Description |
|------|-------------|
| `book_added` | Libro añadido a la biblioteca |
| `book_started` | Comenzó a leer un libro |
| `book_completed` | Terminó de leer un libro |
| `book_rated` | Calificó un libro |
| `movie_added` | Película añadida a la biblioteca |
| `movie_watched` | Vio una película |
| `movie_rated` | Calificó una película |
| `goal_set` | Estableció una nueva meta |
| `goal_achieved` | Alcanzó una meta |

## Error Codes

| Code | Description |
|------|-------------|
| `IMPORT_FAILED` | Error durante la importación |
| `INVALID_FILE_FORMAT` | Formato de archivo no válido |
| `FILE_TOO_LARGE` | Archivo excede tamaño máximo |
| `INVALID_CSV_STRUCTURE` | Estructura CSV inválida |
| `EXPORT_FAILED` | Error durante la exportación |
| `GOAL_VALIDATION_ERROR` | Error en validación de metas |

---

*Última actualización: 18 de Agosto de 2025*
