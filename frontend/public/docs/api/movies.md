# Movies API

## Overview

Endpoints para la gestión completa de películas en la biblioteca personal.

## Endpoints

### Get All Movies

**GET** `/api/movies`

Obtiene lista paginada de todas las películas.

#### Query Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `page` | integer | 1 | Número de página |
| `limit` | integer | 20 | Elementos por página |
| `search` | string | - | Búsqueda por título o director |
| `genre` | string | - | Filtrar por género |
| `year` | integer | - | Filtrar por año |
| `status` | string | - | Filtrar por estado (watched, to-watch) |

#### Response Structure

```json
{
  "success": true,
  "data": {
    "movies": [
      {
        "id": 1,
        "title": "Inception",
        "director": "Christopher Nolan",
        "year": 2010,
        "genre": "Science Fiction",
        "duration": 148,
        "rating": 5,
        "status": "watched",
        "poster": "https://example.com/posters/inception.jpg",
        "synopsis": "A thief who steals corporate secrets...",
        "date_watched": "2025-01-15",
        "notes": "Mind-bending masterpiece",
        "created_at": "2025-01-10T10:30:00Z",
        "updated_at": "2025-01-16T15:45:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 89,
      "total_pages": 5,
      "has_next_page": true,
      "has_prev_page": false
    }
  }
}
```

### Get Movie by ID

**GET** `/api/movies/{id}`

Obtiene una película específica por su ID.

#### Response Example

```json
{
  "success": true,
  "data": {
    "movie": {
      "id": 1,
      "title": "Inception",
      "director": "Christopher Nolan",
      "year": 2010,
      "genre": "Science Fiction",
      "duration": 148,
      "rating": 5,
      "status": "watched",
      "poster": "https://example.com/posters/inception.jpg",
      "synopsis": "A thief who steals corporate secrets through dream-sharing technology...",
      "date_watched": "2025-01-15",
      "notes": "Mind-bending masterpiece with incredible visuals",
      "cast": ["Leonardo DiCaprio", "Marion Cotillard", "Tom Hardy"],
      "created_at": "2025-01-10T10:30:00Z",
      "updated_at": "2025-01-16T15:45:00Z"
    }
  }
}
```

### Create Movie

**POST** `/api/movies`

Añade una nueva película a la biblioteca.

#### Request Body Example

```json
{
  "title": "The Matrix",
  "director": "The Wachowskis",
  "year": 1999,
  "genre": "Science Fiction",
  "duration": 136,
  "status": "to-watch",
  "poster": "https://example.com/posters/matrix.jpg",
  "synopsis": "A computer hacker learns about the true nature of reality...",
  "cast": ["Keanu Reeves", "Laurence Fishburne", "Carrie-Anne Moss"]
}
```

#### Success Response

```json
{
  "success": true,
  "data": {
    "movie": {
      "id": 2,
      "title": "The Matrix",
      "director": "The Wachowskis",
      "year": 1999,
      "genre": "Science Fiction",
      "duration": 136,
      "rating": null,
      "status": "to-watch",
      "poster": "https://example.com/posters/matrix.jpg",
      "synopsis": "A computer hacker learns about the true nature of reality...",
      "date_watched": null,
      "cast": ["Keanu Reeves", "Laurence Fishburne", "Carrie-Anne Moss"],
      "created_at": "2025-08-18T10:30:00Z",
      "updated_at": "2025-08-18T10:30:00Z"
    }
  },
  "message": "Película creada exitosamente"
}
```

### Update Movie

**PUT** `/api/movies/{id}`

Actualiza una película existente.

#### Request Body for Status Update

```json
{
  "status": "watched",
  "rating": 5,
  "date_watched": "2025-08-18",
  "notes": "Revolutionary movie that changed cinema forever"
}
```

#### Update Response

```json
{
  "success": true,
  "data": {
    "movie": {
      "id": 2,
      "title": "The Matrix",
      "status": "watched",
      "rating": 5,
      "date_watched": "2025-08-18",
      "notes": "Revolutionary movie that changed cinema forever",
      "updated_at": "2025-08-18T14:20:00Z"
    }
  },
  "message": "Película actualizada exitosamente"
}
```

### Delete Movie

**DELETE** `/api/movies/{id}`

Elimina una película de la biblioteca.

#### Delete Response

```json
{
  "success": true,
  "message": "Película eliminada exitosamente"
}
```

### Search Movies

**GET** `/api/movies/search`

Búsqueda avanzada de películas.

#### Search Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Término de búsqueda general |
| `title` | string | Búsqueda específica por título |
| `director` | string | Búsqueda específica por director |
| `actor` | string | Búsqueda por actor en el cast |
| `genre` | string | Filtrar por género |
| `year_from` | integer | Año desde |
| `year_to` | integer | Año hasta |
| `duration_min` | integer | Duración mínima en minutos |
| `duration_max` | integer | Duración máxima en minutos |
| `rating_min` | integer | Rating mínimo (1-5) |
| `status` | string | Estado de la película |

### Get Movie Statistics

**GET** `/api/movies/stats`

Obtiene estadísticas de la colección de películas.

#### Statistics Response

```json
{
  "success": true,
  "data": {
    "stats": {
      "total_movies": 89,
      "movies_watched": 56,
      "movies_to_watch": 33,
      "total_runtime": 8940,
      "average_rating": 4.1,
      "average_duration": 118,
      "genres": {
        "Science Fiction": 18,
        "Action": 15,
        "Drama": 12,
        "Comedy": 10,
        "Thriller": 8,
        "Horror": 6
      },
      "decades": {
        "2020s": 15,
        "2010s": 25,
        "2000s": 20,
        "1990s": 15,
        "1980s": 8,
        "1970s": 4,
        "Earlier": 2
      },
      "watching_stats": {
        "movies_this_year": 18,
        "hours_watched_this_year": 36,
        "favorite_genre": "Science Fiction"
      }
    }
  }
}
```

## Movie Status Values

| Status | Description |
|--------|-------------|
| `to-watch` | Película pendiente de ver |
| `watched` | Película ya vista |
| `abandoned` | Película abandonada/no terminada |

## Genre Categories

Géneros principales soportados:

- Action
- Adventure  
- Animation
- Comedy
- Crime
- Documentary
- Drama
- Fantasy
- Horror
- Music
- Mystery
- Romance
- Science Fiction
- Thriller
- War
- Western

## Validation Rules

### Required Fields

- `title`: Mínimo 1 carácter, máximo 255
- `director`: Mínimo 1 carácter, máximo 255
- `year`: Entre 1888 y año actual + 5

### Optional Fields

- `duration`: Número entero positivo (minutos)
- `rating`: Entre 1 y 5
- `genre`: Uno de los géneros válidos
- `status`: Uno de los estados válidos
- `cast`: Array de strings con nombres de actores

## Error Codes

| Code | Description |
|------|-------------|
| `MOVIE_NOT_FOUND` | Película no encontrada |
| `DUPLICATE_MOVIE` | Película ya existe (mismo título y año) |
| `INVALID_YEAR` | Año de película inválido |
| `INVALID_DURATION` | Duración inválida |
| `INVALID_RATING` | Rating debe estar entre 1 y 5 |
| `INVALID_STATUS` | Estado de película inválido |
| `INVALID_GENRE` | Género no válido |
| `VALIDATION_ERROR` | Error en validación de campos |

---

*Última actualización: 18 de Agosto de 2025*
