-- Drop database if exists and recreate it - PRODUCTION
DROP DATABASE IF EXISTS library_db_prod;
CREATE DATABASE library_db_prod;
USE library_db_prod;

-- ============================================================================
-- TABLA DE USUARIOS (debe crearse primero por las FK)
-- ============================================================================

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

-- ============================================================================
-- SISTEMA DE OBRAS Y EDICIONES (NUEVO)
-- ============================================================================

-- Tabla de obras literarias (agrupación de ediciones)
-- Implementa el patrón Work/Edition de OpenLibrary
CREATE TABLE IF NOT EXISTS book_works (
    work_id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Identificadores externos
    openlibrary_work_key VARCHAR(50) UNIQUE NULL COMMENT 'Ej: /works/OL82563W',
    synthetic_work_key VARCHAR(100) UNIQUE NULL COMMENT 'Para obras sin OL: hash(title+author)',
    
    -- Metadatos de la obra
    title VARCHAR(500) NOT NULL,
    subtitle VARCHAR(500) NULL,
    authors JSON NOT NULL COMMENT 'Array de autores: ["J.K. Rowling"]',
    description TEXT NULL,
    subjects JSON NULL COMMENT 'Géneros/categorías: ["Fantasy", "Young Adult"]',
    
    -- Información agregada
    first_publish_year INT NULL,
    original_language VARCHAR(10) NULL COMMENT 'Código ISO: "eng", "spa"',
    
    -- Control de calidad
    is_synthetic BOOLEAN DEFAULT FALSE COMMENT 'TRUE si no existe en OpenLibrary',
    needs_review BOOLEAN DEFAULT FALSE COMMENT 'TRUE si requiere revisión manual',
    manually_edited BOOLEAN DEFAULT FALSE COMMENT 'TRUE si admin editó manualmente',
    manually_edited_fields JSON NULL COMMENT 'Campos editados: ["title", "authors"]',
    
    -- Metadatos del sistema
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para búsqueda
    INDEX idx_openlibrary_key (openlibrary_work_key),
    INDEX idx_synthetic_key (synthetic_work_key),
    INDEX idx_title (title(100)),
    INDEX idx_is_synthetic (is_synthetic),
    INDEX idx_needs_review (needs_review)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Obras literarias (agrupación de ediciones)';

-- Tabla de ediciones específicas de obras
-- Reemplaza la tabla books antigua con soporte para múltiples ediciones
CREATE TABLE IF NOT EXISTS book_editions (
    edition_id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relación con obra
    work_id INT NOT NULL,
    
    -- Identificadores externos
    openlibrary_edition_key VARCHAR(50) UNIQUE NOT NULL COMMENT 'Ej: /books/OL7353617M',
    isbn_13 VARCHAR(13) UNIQUE NULL COMMENT 'ISBN-13 sin guiones',
    isbn_10 VARCHAR(10) UNIQUE NULL COMMENT 'ISBN-10 sin guiones',
    google_books_id VARCHAR(50) NULL COMMENT 'ID de Google Books si aplica',
    
    -- Metadatos de edición
    title VARCHAR(500) NOT NULL,
    subtitle VARCHAR(500) NULL,
    
    -- Información de publicación
    publisher VARCHAR(255) NULL,
    publish_date VARCHAR(50) NULL COMMENT 'Fecha como string: "October 1, 1998"',
    publish_year INT NULL,
    publish_place VARCHAR(255) NULL,
    
    -- Características físicas
    format VARCHAR(50) NULL COMMENT 'hardcover, paperback, ebook, audiobook',
    pages INT NULL,
    dimensions VARCHAR(100) NULL COMMENT 'Ej: "20 x 13 x 2 cm"',
    weight VARCHAR(50) NULL,
    
    -- Contenido
    description TEXT NULL,
    languages JSON NULL COMMENT 'Array: ["eng", "spa"]',
    
    -- Colaboradores específicos de la edición
    illustrators JSON NULL COMMENT 'Array de ilustradores',
    translators JSON NULL COMMENT 'Array de traductores',
    contributors JSON NULL COMMENT 'Otros colaboradores (editores, prólogo, etc)',
    
    -- Imágenes
    cover_url_small VARCHAR(500) NULL,
    cover_url_medium VARCHAR(500) NULL,
    cover_url_large VARCHAR(500) NULL,
    covers JSON NULL COMMENT 'Array de IDs de portadas OpenLibrary',
    
    -- Clasificaciones bibliográficas
    lc_classifications JSON NULL COMMENT 'Library of Congress classifications',
    dewey_decimal_class VARCHAR(50) NULL,
    
    -- Series y colecciones
    series JSON NULL COMMENT 'Serie a la que pertenece',
    series_position VARCHAR(50) NULL COMMENT 'Posición en la serie: "Book 1", "#3"',
    
    -- Enlaces externos
    preview_link VARCHAR(1024) NULL COMMENT 'Link de previsualización (Google Books)',
    info_link VARCHAR(1024) NULL COMMENT 'Link de información (Google Books)',
    
    -- Control de calidad
    data_source VARCHAR(50) DEFAULT 'openlibrary' COMMENT 'openlibrary, google_books, manual',
    manually_edited BOOLEAN DEFAULT FALSE,
    manually_edited_fields JSON NULL COMMENT 'Campos editados por admin',
    
    -- Enriquecimiento con Google Books (opcional)
    google_books_images JSON NULL COMMENT 'URLs de imágenes de Google Books',
    google_categories JSON NULL COMMENT 'Categorías de Google Books',
    google_average_rating DECIMAL(2,1) NULL COMMENT 'Rating promedio de Google Books',
    google_ratings_count INT NULL COMMENT 'Número de ratings en Google Books',
    enriched_with_google BOOLEAN DEFAULT FALSE,
    google_enrichment_at TIMESTAMP NULL COMMENT 'Última vez enriquecido con Google Books',
    
    -- Metadatos del sistema
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign key
    CONSTRAINT fk_edition_work FOREIGN KEY (work_id) 
        REFERENCES book_works(work_id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    
    -- Índices
    INDEX idx_work_id (work_id),
    INDEX idx_openlibrary_key (openlibrary_edition_key),
    INDEX idx_isbn_13 (isbn_13),
    INDEX idx_isbn_10 (isbn_10),
    INDEX idx_google_books_id (google_books_id),
    INDEX idx_format (format),
    INDEX idx_publish_year (publish_year),
    INDEX idx_publisher (publisher(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Ediciones específicas de obras literarias';

-- Tabla de ediciones en biblioteca personal del usuario
-- Permite que un usuario tenga múltiples ediciones de la misma obra
CREATE TABLE IF NOT EXISTS user_book_editions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relaciones
    user_id INT NOT NULL,
    edition_id INT NOT NULL,
    
    -- Tracking de lectura
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consumed_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Primera vez que completó esta edición',
    current_page INT UNSIGNED DEFAULT 0,
    active_reading_session_id INT NULL,
    
    -- Ratings separados
    edition_rating DECIMAL(2,1) NULL COMMENT 'Rating específico de esta edición',
    work_rating DECIMAL(2,1) NULL COMMENT 'Rating de la obra en general',
    
    -- Metadatos de propiedad
    ownership_type ENUM('physical', 'ebook', 'audiobook', 'borrowed', 'wishlist') DEFAULT 'physical',
    acquisition_date DATE NULL COMMENT 'Fecha de adquisición',
    acquisition_type ENUM('purchased', 'gift', 'borrowed', 'library', 'other') NULL,
    purchase_date DATE NULL,
    purchase_price DECIMAL(10,2) NULL,
    purchase_currency VARCHAR(3) NULL COMMENT 'ISO 4217: USD, EUR, etc',
    
    -- Condición física (para libros físicos)
    `condition` ENUM('mint', 'like-new', 'very-good', 'good', 'acceptable', 'poor') NULL,
    condition_notes TEXT NULL,
    
    -- Ubicación
    location VARCHAR(255) NULL COMMENT 'Ej: "Estante A, fila 2"',
    is_digital BOOLEAN DEFAULT FALSE COMMENT 'TRUE para ebooks/audiobooks',
    
    -- Estadísticas de sesiones
    total_sessions_completed INT DEFAULT 0,
    last_session_completed_at TIMESTAMP NULL,
    
    -- Constraints únicos
    UNIQUE KEY unique_user_edition (user_id, edition_id),
    
    -- Foreign keys (se crearán después de crear las tablas referenciadas)
    CONSTRAINT fk_user_edition_user FOREIGN KEY (user_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_user_edition_edition FOREIGN KEY (edition_id) 
        REFERENCES book_editions(edition_id) 
        ON DELETE CASCADE,
    
    -- Índices
    INDEX idx_user_editions_user (user_id),
    INDEX idx_user_editions_added (user_id, added_at),
    INDEX idx_user_editions_consumed (user_id, consumed_at),
    INDEX idx_user_editions_edition_rating (user_id, edition_rating),
    INDEX idx_user_editions_work_rating (user_id, work_rating),
    INDEX idx_user_editions_progress (user_id, current_page),
    INDEX idx_user_editions_sessions (user_id, total_sessions_completed),
    INDEX idx_user_editions_ownership (user_id, ownership_type),
    
    -- Constraints
    CONSTRAINT check_edition_rating CHECK (edition_rating IS NULL OR (edition_rating >= 0.5 AND edition_rating <= 5.0 AND MOD(edition_rating * 2, 1) = 0)),
    CONSTRAINT check_work_rating CHECK (work_rating IS NULL OR (work_rating >= 0.5 AND work_rating <= 5.0 AND MOD(work_rating * 2, 1) = 0)),
    CONSTRAINT check_current_page CHECK (current_page >= 0),
    CONSTRAINT check_sessions CHECK (total_sessions_completed >= 0),
    CONSTRAINT check_purchase_price CHECK (purchase_price IS NULL OR purchase_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Ediciones específicas en la biblioteca de cada usuario';

-- Tabla para sesiones de lectura (permite relecturas)
-- Cada sesión representa una lectura independiente de la misma edición
CREATE TABLE IF NOT EXISTS reading_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    edition_id INT NOT NULL,
    session_number INT NOT NULL DEFAULT 1,
    start_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    end_date DATETIME NULL,
    start_page INT UNSIGNED NOT NULL DEFAULT 0,
    end_page INT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reading_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_reading_session_edition FOREIGN KEY (edition_id) REFERENCES book_editions(edition_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_edition_session (user_id, edition_id, session_number),
    INDEX idx_reading_sessions_user (user_id),
    INDEX idx_reading_sessions_user_edition (user_id, edition_id),
    INDEX idx_reading_sessions_active (user_id, is_active),
    INDEX idx_reading_sessions_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sesiones de lectura por edición (permite relecturas)';

-- Tabla de auditoría para fusiones de obras duplicadas
CREATE TABLE IF NOT EXISTS admin_work_merges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Admin que realizó la fusión
    admin_user_id INT NOT NULL,
    
    -- Obras involucradas
    source_work_id INT NOT NULL COMMENT 'Obra que se fusionó (desaparece)',
    target_work_id INT NOT NULL COMMENT 'Obra destino (se mantiene)',
    
    -- Información de la fusión
    merge_reason TEXT NULL COMMENT 'Razón de la fusión',
    editions_moved INT DEFAULT 0 COMMENT 'Número de ediciones movidas',
    users_affected INT DEFAULT 0 COMMENT 'Número de usuarios afectados',
    
    -- Metadatos
    merged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    can_be_undone BOOLEAN DEFAULT TRUE,
    
    -- Índices
    INDEX idx_admin_merges_admin (admin_user_id),
    INDEX idx_admin_merges_target (target_work_id),
    INDEX idx_admin_merges_date (merged_at),
    INDEX idx_admin_merges_undoable (can_be_undone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Auditoría de fusiones de obras duplicadas por admins';

-- CREATE INDEX idx_books_genres ON books((CAST(genres AS CHAR(255) ARRAY))); -- Para búsquedas por géneros

-- Table for allowed status types
CREATE TABLE IF NOT EXISTS book_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE -- e.g., 'owned', 'read', 'to buy', 'reading'
);

-- Populate allowed statuses (actualizado para sesiones de lectura)
-- This ensures that only valid statuses can be referenced.
-- The Book::ALLOWED_STATUSES array should ideally be in sync with these values.
-- IMPORTANT: Status names use kebab-case format (lowercase with hyphens)
INSERT INTO book_statuses (name) VALUES 
('owned'),       -- Usuario posee el libro
('read'),        -- Libro completamente leído (al menos una vez)
('to-read'),     -- En lista de pendientes
('reading'),     -- Leyendo actualmente (primera vez)
('re-reading'),  -- Releyendo (no es la primera vez)
('want-to-buy'), -- Quiere comprarlo
('abandoned'),   -- Lectura abandonada
('paused');      -- Lectura pausada temporalmente


-- Junction table to link editions with their available statuses using status IDs
CREATE TABLE IF NOT EXISTS edition_has_statuses (
    edition_id INT NOT NULL,
    status_id INT NOT NULL,
    PRIMARY KEY (edition_id, status_id),
    FOREIGN KEY (edition_id) 
        REFERENCES book_editions(edition_id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (status_id) 
        REFERENCES book_statuses(id) 
        ON DELETE CASCADE -- If a status type is somehow deleted, remove links
);

CREATE INDEX idx_edition_has_statuses_status_id ON edition_has_statuses(status_id);
CREATE INDEX idx_edition_has_statuses_edition_status ON edition_has_statuses(edition_id, status_id);

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
-- IMPORTANT: Status names use kebab-case format (lowercase with hyphens)
INSERT INTO movie_statuses (name) VALUES ('owned'), ('viewed'), ('in-watchlist'), ('want-to-buy'), ('abandoned');

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
-- Relaciona user_book_editions con sus estados
CREATE TABLE IF NOT EXISTS user_book_statuses (
    user_edition_id INT NOT NULL,
    status_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_edition_id, status_id),
    FOREIGN KEY (user_edition_id) REFERENCES user_book_editions(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES book_statuses(id) ON DELETE CASCADE,
    INDEX idx_user_book_statuses_status (status_id), -- Para filtrar libros del usuario por estado
    INDEX idx_user_book_statuses_updated (user_edition_id, updated_at)     -- Para ver cambios recientes
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
    edition_id INT NOT NULL,
    tag_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, edition_id, tag_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (edition_id) REFERENCES book_editions(edition_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES user_book_tags(id) ON DELETE CASCADE,
    INDEX idx_book_tag_assignments_tag (tag_id),           -- Para buscar todos los libros con un tag específico
    INDEX idx_book_tag_assignments_edition (user_id, edition_id), -- Para buscar todos los tags de una edición específica
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

-- Tabla de notas por página para ediciones de libros
-- Permite a los usuarios tomar múltiples notas específicas por página en cada edición
-- ACTUALIZADA: Ahora referencia edition_id en lugar de book_isbn
CREATE TABLE IF NOT EXISTS user_edition_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    user_edition_id INT NOT NULL,           -- FK a user_book_editions
    page_number INT UNSIGNED NOT NULL,      -- Página específica a la que se refiere la nota
    note_text TEXT DEFAULT NULL,            -- Contenido de la nota
    note_type ENUM('note', 'quote', 'thought', 'question', 'summary', 'progress', 'general') DEFAULT 'progress', -- Tipo de nota
    is_private TINYINT(1) DEFAULT 1,        -- Si la nota es privada (1) o pública (0)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_edition_note_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_edition_note_user_edition FOREIGN KEY (user_edition_id) REFERENCES user_book_editions(id) ON DELETE CASCADE,
    
    -- Índices para consultas eficientes
    INDEX idx_user_edition_notes_user_edition (user_edition_id),         -- Todas las notas de una edición específica
    INDEX idx_user_edition_notes_page (user_edition_id, page_number),    -- Notas de una página específica
    INDEX idx_user_edition_notes_type (user_id, note_type),              -- Filtrar por tipo de nota
    INDEX idx_user_edition_notes_created (user_id, created_at),          -- Notas ordenadas por fecha de creación
    INDEX idx_user_edition_notes_updated (user_id, updated_at),          -- Notas ordenadas por última actualización
    INDEX idx_user_edition_notes_user (user_id),                         -- Todas las notas de un usuario
    
    -- Constraints
    CONSTRAINT check_edition_note_page_number CHECK (page_number > 0),   -- Las páginas deben ser positivas
    CONSTRAINT check_edition_note_text CHECK (CHAR_LENGTH(note_text) > 0) -- Las notas no pueden estar vacías
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Notas por página en ediciones específicas de libros';

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

-- Tabla para historial de progreso de lectura (actualizada para sesiones)
-- Registra cada vez que un usuario actualiza su progreso de lectura
-- Ahora permite progreso negativo y se vincula con sesiones
CREATE TABLE IF NOT EXISTS reading_progress_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    edition_id INT NOT NULL,
    reading_session_id INT NULL,              -- Vinculación con sesión de lectura
    current_page INT UNSIGNED NOT NULL,       -- Páginas leídas hasta esa fecha
    previous_page INT UNSIGNED DEFAULT 0,     -- Páginas leídas anteriormente
    progress_type ENUM('advance', 'backtrack', 'restart') DEFAULT 'advance', -- Tipo de progreso
    notes TEXT NULL,                          -- Notas opcionales sobre el progreso
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora del registro
    
    -- Relaciones
    CONSTRAINT fk_progress_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_edition FOREIGN KEY (edition_id) REFERENCES book_editions(edition_id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_session FOREIGN KEY (reading_session_id) REFERENCES reading_sessions(id) ON DELETE CASCADE,
    
    -- Índices para consultas eficientes
    INDEX idx_progress_user_edition (user_id, edition_id),          -- Historial de una edición específica de un usuario
    INDEX idx_progress_user_date (user_id, logged_at),             -- Progreso de un usuario por fecha
    INDEX idx_progress_edition_date (edition_id, logged_at),       -- Progreso de una edición por fecha
    INDEX idx_progress_recent (logged_at),                         -- Progreso reciente general
    INDEX idx_progress_session (reading_session_id),               -- Progreso por sesión
    INDEX idx_progress_type (progress_type),                       -- Progreso por tipo
    INDEX idx_progress_user_session (user_id, reading_session_id), -- Progreso de usuario por sesión
    
    -- Constraints actualizados para permitir progreso negativo
    CONSTRAINT check_progress_pages CHECK (current_page >= 0 AND previous_page >= 0),
    CONSTRAINT check_progress_type_valid CHECK (progress_type IN ('advance', 'backtrack', 'restart'))
);

-- ============================================================================
-- ÍNDICES ADICIONALES PARA PERFORMANCE
-- ============================================================================

-- Índices para las vistas más utilizadas
CREATE INDEX idx_progress_monthly_stats ON reading_progress_history(user_id, logged_at, progress_type);

-- Índices para queries de estadísticas
CREATE INDEX idx_user_editions_completed_sessions ON user_book_editions(user_id, total_sessions_completed);
CREATE INDEX idx_user_editions_rating_filter ON user_book_editions(user_id, edition_rating);

-- ============================================================================
-- COMENTARIOS FINALES Y DOCUMENTACIÓN
-- ============================================================================

-- Añadir comentarios a las nuevas tablas
ALTER TABLE reading_sessions COMMENT = 'Sesiones de lectura independientes - permite relecturas y seguimiento detallado';
ALTER TABLE reading_progress_history COMMENT = 'Historial completo de progreso incluyendo retrocesos y reinicios por sesión';