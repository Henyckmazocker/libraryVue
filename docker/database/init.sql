-- Drop database if exists and recreate it
DROP DATABASE IF EXISTS library_db;
CREATE DATABASE library_db;
USE library_db;

-- Recreate tables (your existing CREATE TABLE statements)
CREATE TABLE IF NOT EXISTS books (
    isbn VARCHAR(20) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    publisher VARCHAR(255) DEFAULT NULL, -- Editorial o casa publicadora
    publication_date DATE DEFAULT NULL,  -- Fecha de publicación
    coverUrl VARCHAR(1024) DEFAULT NULL,
    rating DECIMAL(2,1) DEFAULT NULL, -- e.g., 3.5 (precision 2, 1 decimal place)
    pages INT UNSIGNED DEFAULT NULL, -- Número total de páginas del libro
    description TEXT DEFAULT NULL,   -- Descripción o sinopsis del libro
    genres JSON DEFAULT NULL,        -- Géneros del libro (array JSON)
    addedTimestamp INT UNSIGNED DEFAULT NULL,
    CONSTRAINT check_book_rating CHECK (rating IS NULL OR (rating >= 0.5 AND rating <= 5.0 AND MOD(rating * 2, 1) = 0)),
    CONSTRAINT check_book_pages CHECK (pages IS NULL OR pages > 0)
);

-- Índices optimizados para books
CREATE INDEX idx_books_title ON books(title); -- Para búsquedas por título
CREATE INDEX idx_books_author ON books(author); -- Para búsquedas por autor
CREATE INDEX idx_books_publisher ON books(publisher); -- Para búsquedas por editorial
CREATE INDEX idx_books_publication_date ON books(publication_date); -- Para filtros por fecha de publicación
CREATE INDEX idx_books_rating ON books(rating); -- Para filtros y ordenación por rating
CREATE INDEX idx_books_pages ON books(pages); -- Para filtros y ordenación por número de páginas
CREATE INDEX idx_books_added_timestamp ON books(addedTimestamp); -- Para ordenar por fecha de adición
CREATE INDEX idx_books_title_author ON books(title, author); -- Búsquedas combinadas
CREATE INDEX idx_books_title_publisher ON books(title, publisher); -- Búsquedas combinadas título-editorial
-- Índice funcional para géneros JSON (MySQL 5.7+)
-- CREATE INDEX idx_books_genres ON books((CAST(genres AS CHAR(255) ARRAY))); -- Para búsquedas por géneros

-- Table for allowed status types
CREATE TABLE IF NOT EXISTS book_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE -- e.g., 'owned', 'read', 'to buy', 'reading'
);

-- Populate allowed statuses
-- This ensures that only valid statuses can be referenced.
-- The Book::ALLOWED_STATUSES array should ideally be in sync with these values.
INSERT INTO book_statuses (name) VALUES ('owned'), ('read'), ('to read'), ('reading'), ('want to buy'), ('abandoned');


-- Junction table to link books with their statuses using status IDs
CREATE TABLE IF NOT EXISTS book_has_statuses (
    book_isbn VARCHAR(20) NOT NULL, -- Match the type/length of books.isbn
    status_id INT NOT NULL,
    PRIMARY KEY (book_isbn, status_id),
    FOREIGN KEY (book_isbn) 
        REFERENCES books(isbn) 
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (status_id) 
        REFERENCES book_statuses(id) 
        ON DELETE CASCADE -- If a status type is somehow deleted, remove links
);

-- Optional: Add an index on status_id in book_has_statuses for faster filtering if you frequently query by status
CREATE INDEX idx_book_has_statuses_status_id ON book_has_statuses(status_id);
-- Índice adicional para búsquedas inversas (encontrar todos los libros de un usuario con cierto estado)
CREATE INDEX idx_book_has_statuses_isbn_status ON book_has_statuses(book_isbn, status_id);

-- Recreate tables (your existing CREATE TABLE statements)
CREATE TABLE IF NOT EXISTS movie (
    isbn VARCHAR(20) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    original_title VARCHAR(255) DEFAULT NULL, -- Título original de la película
    director VARCHAR(255) DEFAULT NULL,       -- Director (equivalente a author en books)
    author VARCHAR(255) DEFAULT NULL,         -- Mantenemos para compatibilidad
    coverUrl VARCHAR(1024) DEFAULT NULL,
    rating DECIMAL(2,1) DEFAULT NULL, -- e.g., 3.5 (precision 2, 1 decimal place)
    description TEXT DEFAULT NULL,   -- Sinopsis de la película
    genres JSON DEFAULT NULL,        -- Géneros de la película (array JSON)
    addedTimestamp INT UNSIGNED DEFAULT NULL,
    CONSTRAINT check_movie_rating CHECK (rating IS NULL OR (rating >= 0.5 AND rating <= 5.0 AND MOD(rating * 2, 1) = 0))
);

-- Índices optimizados para movies
CREATE INDEX idx_movies_title ON movie(title); -- Para búsquedas por título
CREATE INDEX idx_movies_original_title ON movie(original_title); -- Para búsquedas por título original
CREATE INDEX idx_movies_director ON movie(director); -- Para búsquedas por director
CREATE INDEX idx_movies_author ON movie(author); -- Para búsquedas por director (compatibilidad)
CREATE INDEX idx_movies_rating ON movie(rating); -- Para filtros y ordenación por rating
CREATE INDEX idx_movies_added_timestamp ON movie(addedTimestamp); -- Para ordenar por fecha de adición
CREATE INDEX idx_movies_title_director ON movie(title, director); -- Búsquedas combinadas título-director
CREATE INDEX idx_movies_title_author ON movie(title, author); -- Búsquedas combinadas título-director (compatibilidad)
-- Índice funcional para géneros JSON (MySQL 5.7+)
-- CREATE INDEX idx_movies_genres ON movie((CAST(genres AS CHAR(255) ARRAY))); -- Para búsquedas por géneros

-- Table for allowed status types
CREATE TABLE IF NOT EXISTS movie_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE -- e.g., 'owned', 'read', 'to buy', 'reading'
);

-- Populate allowed statuses
-- This ensures that only valid statuses can be referenced.
-- The Book::ALLOWED_STATUSES array should ideally be in sync with these values.
INSERT INTO movie_statuses (name) VALUES ('owned'), ('viewed'), ('in watchlist'), ('want to buy'), ('abandoned');

-- Junction table to link books with their statuses using status IDs
CREATE TABLE IF NOT EXISTS movie_has_statuses (
    movie_isbn VARCHAR(20) NOT NULL, -- Match the type/length of books.isbn
    status_id INT NOT NULL,
    PRIMARY KEY (movie_isbn, status_id),
    FOREIGN KEY (movie_isbn) 
        REFERENCES movie(isbn) 
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (status_id) 
        REFERENCES movie_statuses(id) 
        ON DELETE CASCADE -- If a status type is somehow deleted, remove links
);

-- Optional: Add an index on status_id in book_has_statuses for faster filtering if you frequently query by status
CREATE INDEX idx_movie_has_statuses_status_id ON movie_has_statuses(status_id);
-- Índice adicional para búsquedas inversas (encontrar todas las películas de un usuario con cierto estado)
CREATE INDEX idx_movie_has_statuses_isbn_status ON movie_has_statuses(movie_isbn, status_id);

-- Table for users (Google OAuth integration)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) NOT NULL UNIQUE, -- Google user ID (sub claim from JWT)
    email VARCHAR(255) NOT NULL UNIQUE,     -- User email from Google
    name VARCHAR(255) NOT NULL,             -- User full name from Google
    picture VARCHAR(1024) DEFAULT NULL,     -- User profile picture URL from Google
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL, -- Track last login time
    preferences JSON DEFAULT NULL,          -- Store user preferences as JSON
    is_active TINYINT(1) DEFAULT 1,        -- Allow deactivating users if needed
    INDEX idx_users_google_id (google_id),  -- Para autenticación rápida
    INDEX idx_users_email (email),          -- Para búsquedas por email
    INDEX idx_users_active_created (is_active, created_at), -- Para listados de usuarios activos
    INDEX idx_users_last_login (last_login) -- Para estadísticas de actividad
);

-- Tablas para bibliotecas personales por usuario
-- Relación users -> books (cada usuario tiene su propia biblioteca de libros)
CREATE TABLE IF NOT EXISTS user_books (
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consumed_at TIMESTAMP NULL DEFAULT NULL,   -- Fecha cuando el usuario leyó el libro
    current_page INT UNSIGNED DEFAULT 0,       -- Página actual en la que va el usuario (0 = no empezado)
    personal_rating DECIMAL(2,1) DEFAULT NULL, -- Rating personal del usuario (puede diferir del global)
    personal_notes TEXT DEFAULT NULL,          -- Notas personales sobre el libro
    PRIMARY KEY (user_id, book_isbn),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_isbn) REFERENCES books(isbn) ON DELETE CASCADE,
    INDEX idx_user_books_user_added (user_id, added_at), -- Para obtener libros de un usuario ordenados por fecha
    INDEX idx_user_books_consumed (user_id, consumed_at), -- Para obtener libros leídos ordenados por fecha de lectura
    INDEX idx_user_books_rating (user_id, personal_rating), -- Para filtros por rating personal
    INDEX idx_user_books_progress (user_id, current_page), -- Para filtros por progreso de lectura
    CONSTRAINT check_user_book_rating CHECK (personal_rating IS NULL OR (personal_rating >= 0.5 AND personal_rating <= 5.0 AND MOD(personal_rating * 2, 1) = 0)),
    CONSTRAINT check_user_book_page CHECK (current_page >= 0)
);

-- Relación users -> movies (cada usuario tiene su propia biblioteca de películas)
CREATE TABLE IF NOT EXISTS user_movies (
    user_id INT NOT NULL,
    movie_isbn VARCHAR(20) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consumed_at TIMESTAMP NULL DEFAULT NULL,   -- Fecha cuando el usuario vio la película
    personal_rating DECIMAL(2,1) DEFAULT NULL, -- Rating personal del usuario
    personal_notes TEXT DEFAULT NULL,          -- Notas personales sobre la película
    PRIMARY KEY (user_id, movie_isbn),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_isbn) REFERENCES movie(isbn) ON DELETE CASCADE,
    INDEX idx_user_movies_user_added (user_id, added_at), -- Para obtener películas de un usuario ordenadas por fecha
    INDEX idx_user_movies_consumed (user_id, consumed_at), -- Para obtener películas vistas ordenadas por fecha de visualización
    INDEX idx_user_movies_rating (user_id, personal_rating), -- Para filtros por rating personal
    CONSTRAINT check_user_movie_rating CHECK (personal_rating IS NULL OR (personal_rating >= 0.5 AND personal_rating <= 5.0 AND MOD(personal_rating * 2, 1) = 0))
);

-- Estados personales de libros por usuario
CREATE TABLE IF NOT EXISTS user_book_statuses (
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    status_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, book_isbn, status_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_isbn) REFERENCES books(isbn) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES book_statuses(id) ON DELETE CASCADE,
    INDEX idx_user_book_statuses_user_status (user_id, status_id), -- Para filtrar libros del usuario por estado
    INDEX idx_user_book_statuses_updated (user_id, updated_at)     -- Para ver cambios recientes
);

-- Estados personales de películas por usuario
CREATE TABLE IF NOT EXISTS user_movie_statuses (
    user_id INT NOT NULL,
    movie_isbn VARCHAR(20) NOT NULL,
    status_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, movie_isbn, status_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_isbn) REFERENCES movie(isbn) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES movie_statuses(id) ON DELETE CASCADE,
    INDEX idx_user_movie_statuses_user_status (user_id, status_id), -- Para filtrar películas del usuario por estado
    INDEX idx_user_movie_statuses_updated (user_id, updated_at)     -- Para ver cambios recientes
);

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT NOT NULL,
    preferences JSON DEFAULT NULL, -- Almacena preferencias del usuario como JSON
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_preferences_user (user_id) -- Para búsquedas rápidas por usuario
);

CREATE TABLE IF NOT EXISTS versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(50) NOT NULL UNIQUE, -- e.g., '1.0.0'
    description TEXT DEFAULT NULL,         -- Descripción de la versión
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sistema de tags personalizados por usuario
-- Tags personalizados para libros (cada usuario puede crear sus propios tags)
CREATE TABLE IF NOT EXISTS user_book_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,           -- Nombre del tag (ej: "favoritos", "ciencia ficción", "pendientes")
    color VARCHAR(7) DEFAULT '#007bff',   -- Color hex para el tag (opcional)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_tag (user_id, name), -- Un usuario no puede tener tags duplicados
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_book_tags_user (user_id),
    INDEX idx_user_book_tags_name (user_id, name)
);

-- Tags personalizados para películas
CREATE TABLE IF NOT EXISTS user_movie_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,           -- Nombre del tag
    color VARCHAR(7) DEFAULT '#007bff',   -- Color hex para el tag (opcional)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_movie_tag (user_id, name), -- Un usuario no puede tener tags duplicados
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_movie_tags_user (user_id),
    INDEX idx_user_movie_tags_name (user_id, name)
);

-- Relación muchos a muchos: user_books -> user_book_tags
CREATE TABLE IF NOT EXISTS user_book_tag_assignments (
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    tag_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, book_isbn, tag_id),
    FOREIGN KEY (user_id, book_isbn) REFERENCES user_books(user_id, book_isbn) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES user_book_tags(id) ON DELETE CASCADE,
    INDEX idx_book_tag_assignments_tag (tag_id),           -- Para buscar todos los libros con un tag específico
    INDEX idx_book_tag_assignments_book (user_id, book_isbn), -- Para buscar todos los tags de un libro específico
    INDEX idx_book_tag_assignments_user (user_id)          -- Para buscar todas las asignaciones de un usuario
);

-- Relación muchos a muchos: user_movies -> user_movie_tags
CREATE TABLE IF NOT EXISTS user_movie_tag_assignments (
    user_id INT NOT NULL,
    movie_isbn VARCHAR(20) NOT NULL,
    tag_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, movie_isbn, tag_id),
    FOREIGN KEY (user_id, movie_isbn) REFERENCES user_movies(user_id, movie_isbn) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES user_movie_tags(id) ON DELETE CASCADE,
    INDEX idx_movie_tag_assignments_tag (tag_id),            -- Para buscar todas las películas con un tag específico
    INDEX idx_movie_tag_assignments_movie (user_id, movie_isbn), -- Para buscar todos los tags de una película específica
    INDEX idx_movie_tag_assignments_user (user_id)           -- Para buscar todas las asignaciones de un usuario
);

-- Tabla de notas por página para libros
-- Permite a los usuarios tomar múltiples notas específicas por página en cada libro
CREATE TABLE IF NOT EXISTS user_book_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    page_number INT UNSIGNED NOT NULL,      -- Página específica a la que se refiere la nota
    note_text TEXT DEFAULT NULL,                -- Contenido de la nota
    note_type ENUM('note', 'quote', 'thought', 'question', 'summary', 'progress') DEFAULT 'progress', -- Tipo de nota
    is_private TINYINT(1) DEFAULT 1,        -- Si la nota es privada (1) o pública (0)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id, book_isbn) REFERENCES user_books(user_id, book_isbn) ON DELETE CASCADE,
    
    -- Índices para consultas eficientes
    INDEX idx_user_book_notes_user_book (user_id, book_isbn),           -- Todas las notas de un libro específico del usuario
    INDEX idx_user_book_notes_page (user_id, book_isbn, page_number),   -- Notas de una página específica
    INDEX idx_user_book_notes_type (user_id, note_type),                -- Filtrar por tipo de nota
    INDEX idx_user_book_notes_created (user_id, created_at),            -- Notas ordenadas por fecha de creación
    INDEX idx_user_book_notes_updated (user_id, updated_at),            -- Notas ordenadas por última actualización
    
    -- Constraints
    CONSTRAINT check_page_number CHECK (page_number > 0),               -- Las páginas deben ser positivas
    CONSTRAINT check_note_text CHECK (CHAR_LENGTH(note_text) > 0)       -- Las notas no pueden estar vacías
);

-- Tabla de seguimiento de usuarios (follow/following system)
-- Permite a los usuarios seguirse entre sí para ver actividades de lectura
CREATE TABLE IF NOT EXISTS user_follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,           -- Usuario que sigue (quien hace el follow)
    followed_id INT NOT NULL,           -- Usuario que es seguido (a quien siguen)
    followed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,     -- Permite "pausar" el seguimiento sin eliminarlo
    
    -- Un usuario no puede seguir al mismo usuario múltiples veces
    UNIQUE KEY unique_follow (follower_id, followed_id),
    
    -- Foreign keys
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Índices para consultas eficientes
    INDEX idx_user_follows_follower (follower_id),                    -- Todos los usuarios que sigue alguien
    INDEX idx_user_follows_followed (followed_id),                    -- Todos los seguidores de alguien
    INDEX idx_user_follows_active (follower_id, is_active),           -- Seguimientos activos de un usuario
    INDEX idx_user_follows_recent (followed_at),                      -- Seguimientos recientes
    INDEX idx_user_follows_mutual (follower_id, followed_id),         -- Para verificar seguimiento mutuo
    
    -- Constraints
    CONSTRAINT check_not_self_follow CHECK (follower_id != followed_id) -- Un usuario no puede seguirse a sí mismo
);

-- Tabla para historial de progreso de lectura
-- Registra cada vez que un usuario actualiza su progreso de lectura
CREATE TABLE IF NOT EXISTS reading_progress_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    current_page INT UNSIGNED NOT NULL,       -- Páginas leídas hasta esa fecha
    previous_page INT UNSIGNED DEFAULT 0,     -- Páginas leídas anteriormente
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora del registro
    
    -- Relaciones
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_isbn) REFERENCES books(isbn) ON DELETE CASCADE,
    FOREIGN KEY (user_id, book_isbn) REFERENCES user_books(user_id, book_isbn) ON DELETE CASCADE,
    
    -- Índices para consultas eficientes
    INDEX idx_progress_user_book (user_id, book_isbn),              -- Historial de un libro específico de un usuario
    INDEX idx_progress_user_date (user_id, logged_at),             -- Progreso de un usuario por fecha
    INDEX idx_progress_book_date (book_isbn, logged_at),           -- Progreso de un libro por fecha
    INDEX idx_progress_recent (logged_at),                         -- Progreso reciente general
    
    -- Constraints
    CONSTRAINT check_progress_pages CHECK (current_page >= 0 AND previous_page >= 0),
    CONSTRAINT check_progress_advance CHECK (current_page > previous_page) -- Solo registrar avances
);