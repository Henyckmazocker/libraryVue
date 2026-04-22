-- Drop database if exists and recreate it - PRODUCTION
DROP DATABASE IF EXISTS library_db_prod;
CREATE DATABASE library_db_prod;
USE library_db_prod;

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

-- Table for allowed status types
CREATE TABLE IF NOT EXISTS book_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE -- e.g., 'owned', 'read', 'to buy', 'reading'
);

-- Populate allowed statuses (actualizado para sesiones de lectura)
INSERT INTO book_statuses (name) VALUES 
('owned'),       -- Usuario posee el libro
('read'),        -- Libro completamente leído (al menos una vez)
('to read'),     -- En lista de pendientes
('reading'),     -- Leyendo actualmente (primera vez)
('re-reading'),  -- Releyendo (no es la primera vez)
('want to buy'), -- Quiere comprarlo
('abandoned'),   -- Lectura abandonada
('paused');      -- Lectura pausada temporalmente


-- Junction table to link books with their statuses using status IDs
CREATE TABLE IF NOT EXISTS book_has_statuses (
    book_isbn VARCHAR(20) NOT NULL,
    status_id INT NOT NULL,
    PRIMARY KEY (book_isbn, status_id),
    FOREIGN KEY (book_isbn) 
        REFERENCES books(isbn) 
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (status_id) 
        REFERENCES book_statuses(id) 
        ON DELETE CASCADE
);

CREATE INDEX idx_book_has_statuses_status_id ON book_has_statuses(status_id);
CREATE INDEX idx_book_has_statuses_isbn_status ON book_has_statuses(book_isbn, status_id);

-- Recreate tables (your existing CREATE TABLE statements)
CREATE TABLE IF NOT EXISTS movie (
    isbn VARCHAR(20) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    original_title VARCHAR(255) DEFAULT NULL,
    director VARCHAR(255) DEFAULT NULL,
    author VARCHAR(255) DEFAULT NULL,
    coverUrl VARCHAR(1024) DEFAULT NULL,
    rating DECIMAL(2,1) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    genres JSON DEFAULT NULL,
    addedTimestamp INT UNSIGNED DEFAULT NULL,
    CONSTRAINT check_movie_rating CHECK (rating IS NULL OR (rating >= 0.5 AND rating <= 5.0 AND MOD(rating * 2, 1) = 0))
);

-- Índices optimizados para movies
CREATE INDEX idx_movies_title ON movie(title);
CREATE INDEX idx_movies_original_title ON movie(original_title);
CREATE INDEX idx_movies_director ON movie(director);
CREATE INDEX idx_movies_author ON movie(author);
CREATE INDEX idx_movies_rating ON movie(rating);
CREATE INDEX idx_movies_added_timestamp ON movie(addedTimestamp);
CREATE INDEX idx_movies_title_director ON movie(title, director);
CREATE INDEX idx_movies_title_author ON movie(title, author);

-- Table for allowed status types
CREATE TABLE IF NOT EXISTS movie_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO movie_statuses (name) VALUES ('owned'), ('viewed'), ('in watchlist'), ('want to buy'), ('abandoned');

-- Junction table to link books with their statuses using status IDs
CREATE TABLE IF NOT EXISTS movie_has_statuses (
    movie_isbn VARCHAR(20) NOT NULL,
    status_id INT NOT NULL,
    PRIMARY KEY (movie_isbn, status_id),
    FOREIGN KEY (movie_isbn) 
        REFERENCES movie(isbn) 
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (status_id) 
        REFERENCES movie_statuses(id) 
        ON DELETE CASCADE
);

CREATE INDEX idx_movie_has_statuses_status_id ON movie_has_statuses(status_id);
CREATE INDEX idx_movie_has_statuses_isbn_status ON movie_has_statuses(movie_isbn, status_id);

-- Table for users (Google OAuth integration)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    picture VARCHAR(1024) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    preferences JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    lastfm_username VARCHAR(255) DEFAULT NULL,
    INDEX idx_users_google_id (google_id),
    INDEX idx_users_email (email),
    INDEX idx_users_active_created (is_active, created_at),
    INDEX idx_users_last_login (last_login)
);

-- Tabla para sesiones de lectura (permite relecturas)
CREATE TABLE IF NOT EXISTS reading_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    session_number INT NOT NULL DEFAULT 1,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('active', 'completed', 'abandoned', 'paused') DEFAULT 'active',
    final_page INT UNSIGNED NULL,
    session_notes TEXT NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_isbn) REFERENCES books(isbn) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_book_session (user_id, book_isbn, session_number),
    INDEX idx_reading_sessions_active (user_id, status),
    INDEX idx_reading_sessions_user_book (user_id, book_isbn),
    INDEX idx_reading_sessions_status (status),
    INDEX idx_reading_sessions_dates (started_at, completed_at)
);

-- Tablas para bibliotecas personales por usuario
CREATE TABLE IF NOT EXISTS user_books (
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consumed_at TIMESTAMP NULL DEFAULT NULL,
    current_page INT UNSIGNED DEFAULT 0,
    active_reading_session_id INT NULL,
    personal_rating DECIMAL(2,1) DEFAULT NULL,
    personal_notes TEXT DEFAULT NULL,
    total_sessions_completed INT DEFAULT 0,
    last_session_completed_at TIMESTAMP NULL,
    PRIMARY KEY (user_id, book_isbn),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_isbn) REFERENCES books(isbn) ON DELETE CASCADE,
    FOREIGN KEY (active_reading_session_id) REFERENCES reading_sessions(id) ON DELETE SET NULL,
    INDEX idx_user_books_user_added (user_id, added_at),
    INDEX idx_user_books_consumed (user_id, consumed_at),
    INDEX idx_user_books_rating (user_id, personal_rating),
    INDEX idx_user_books_progress (user_id, current_page),
    INDEX idx_user_books_active_session (active_reading_session_id),
    INDEX idx_user_books_sessions_count (user_id, total_sessions_completed),
    INDEX idx_user_books_last_session (user_id, last_session_completed_at),
    CONSTRAINT check_user_book_rating CHECK (personal_rating IS NULL OR (personal_rating >= 0.5 AND personal_rating <= 5.0 AND MOD(personal_rating * 2, 1) = 0)),
    CONSTRAINT check_user_book_page CHECK (current_page >= 0),
    CONSTRAINT check_sessions_completed CHECK (total_sessions_completed >= 0)
);

-- Relación users -> movies
CREATE TABLE IF NOT EXISTS user_movies (
    user_id INT NOT NULL,
    movie_isbn VARCHAR(20) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consumed_at TIMESTAMP NULL DEFAULT NULL,
    personal_rating DECIMAL(2,1) DEFAULT NULL,
    personal_notes TEXT DEFAULT NULL,
    PRIMARY KEY (user_id, movie_isbn),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_isbn) REFERENCES movie(isbn) ON DELETE CASCADE,
    INDEX idx_user_movies_user_added (user_id, added_at),
    INDEX idx_user_movies_consumed (user_id, consumed_at),
    INDEX idx_user_movies_rating (user_id, personal_rating),
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
    INDEX idx_user_book_statuses_user_status (user_id, status_id),
    INDEX idx_user_book_statuses_updated (user_id, updated_at)
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
    INDEX idx_user_movie_statuses_user_status (user_id, status_id),
    INDEX idx_user_movie_statuses_updated (user_id, updated_at)
);

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT NOT NULL,
    preferences JSON DEFAULT NULL,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_preferences_user (user_id)
);

CREATE TABLE IF NOT EXISTS versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(50) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sistema de tags personalizados por usuario
CREATE TABLE IF NOT EXISTS user_book_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#007bff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_tag (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_book_tags_user (user_id),
    INDEX idx_user_book_tags_name (user_id, name)
);

CREATE TABLE IF NOT EXISTS user_movie_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#007bff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_movie_tag (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_movie_tags_user (user_id),
    INDEX idx_user_movie_tags_name (user_id, name)
);

CREATE TABLE IF NOT EXISTS user_book_tag_assignments (
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    tag_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, book_isbn, tag_id),
    FOREIGN KEY (user_id, book_isbn) REFERENCES user_books(user_id, book_isbn) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES user_book_tags(id) ON DELETE CASCADE,
    INDEX idx_book_tag_assignments_tag (tag_id),
    INDEX idx_book_tag_assignments_book (user_id, book_isbn),
    INDEX idx_book_tag_assignments_user (user_id)
);

CREATE TABLE IF NOT EXISTS user_movie_tag_assignments (
    user_id INT NOT NULL,
    movie_isbn VARCHAR(20) NOT NULL,
    tag_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, movie_isbn, tag_id),
    FOREIGN KEY (user_id, movie_isbn) REFERENCES user_movies(user_id, movie_isbn) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES user_movie_tags(id) ON DELETE CASCADE,
    INDEX idx_movie_tag_assignments_tag (tag_id),
    INDEX idx_movie_tag_assignments_movie (user_id, movie_isbn),
    INDEX idx_movie_tag_assignments_user (user_id)
);

CREATE TABLE IF NOT EXISTS user_book_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    page_number INT UNSIGNED NOT NULL,
    note_text TEXT DEFAULT NULL,
    note_type ENUM('note', 'quote', 'thought', 'question', 'summary', 'progress') DEFAULT 'progress',
    is_private TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id, book_isbn) REFERENCES user_books(user_id, book_isbn) ON DELETE CASCADE,
    
    INDEX idx_user_book_notes_user_book (user_id, book_isbn),
    INDEX idx_user_book_notes_page (user_id, book_isbn, page_number),
    INDEX idx_user_book_notes_type (user_id, note_type),
    INDEX idx_user_book_notes_created (user_id, created_at),
    INDEX idx_user_book_notes_updated (user_id, updated_at),
    
    CONSTRAINT check_page_number CHECK (page_number > 0),
    CONSTRAINT check_note_text CHECK (CHAR_LENGTH(note_text) > 0)
);

CREATE TABLE IF NOT EXISTS user_follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    followed_id INT NOT NULL,
    followed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    
    UNIQUE KEY unique_follow (follower_id, followed_id),
    
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (followed_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_user_follows_follower (follower_id),
    INDEX idx_user_follows_followed (followed_id),
    INDEX idx_user_follows_active (follower_id, is_active),
    INDEX idx_user_follows_recent (followed_at),
    INDEX idx_user_follows_mutual (follower_id, followed_id),
    
    CONSTRAINT check_not_self_follow CHECK (follower_id != followed_id)
);

CREATE TABLE IF NOT EXISTS reading_progress_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_isbn VARCHAR(20) NOT NULL,
    reading_session_id INT NULL,
    current_page INT UNSIGNED NOT NULL,
    previous_page INT UNSIGNED DEFAULT 0,
    progress_type ENUM('advance', 'backtrack', 'restart') DEFAULT 'advance',
    notes TEXT NULL,
    logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_isbn) REFERENCES books(isbn) ON DELETE CASCADE,
    FOREIGN KEY (user_id, book_isbn) REFERENCES user_books(user_id, book_isbn) ON DELETE CASCADE,
    FOREIGN KEY (reading_session_id) REFERENCES reading_sessions(id) ON DELETE CASCADE,
    
    INDEX idx_progress_user_book (user_id, book_isbn),
    INDEX idx_progress_user_date (user_id, logged_at),
    INDEX idx_progress_book_date (book_isbn, logged_at),
    INDEX idx_progress_recent (logged_at),
    INDEX idx_progress_session (reading_session_id),
    INDEX idx_progress_type (progress_type),
    INDEX idx_progress_user_session (user_id, reading_session_id),
    
    CONSTRAINT check_progress_pages CHECK (current_page >= 0 AND previous_page >= 0),
    CONSTRAINT check_progress_type_valid CHECK (progress_type IN ('advance', 'backtrack', 'restart'))
);

-- Índices adicionales para performance
CREATE INDEX idx_user_books_status_lookup ON user_book_statuses(user_id, book_isbn);
CREATE INDEX idx_progress_monthly_stats ON reading_progress_history(user_id, logged_at, progress_type);
CREATE INDEX idx_sessions_user_status ON reading_sessions(user_id, status);
CREATE INDEX idx_books_search_full ON books(title, author, isbn);
CREATE INDEX idx_user_books_completed_sessions ON user_books(user_id, total_sessions_completed);
CREATE INDEX idx_user_books_rating_filter ON user_books(user_id, personal_rating);

-- ============================================================================
-- SISTEMA DE VIDEOJUEGOS
-- ============================================================================

-- Tabla principal de videojuegos
CREATE TABLE IF NOT EXISTS games (
    id INT UNSIGNED PRIMARY KEY,  -- ID de RAWG API
    slug VARCHAR(255) NOT NULL UNIQUE, -- Identificador único tipo "the-witcher-3"
    title VARCHAR(255) NOT NULL,
    release_date DATE DEFAULT NULL,
    developer VARCHAR(255) DEFAULT NULL,  -- Desarrollador principal
    publisher VARCHAR(255) DEFAULT NULL,  -- Distribuidor/Editorial
    coverUrl VARCHAR(1024) DEFAULT NULL,  -- URL de imagen de portada
    backgroundUrl VARCHAR(1024) DEFAULT NULL, -- URL de imagen de fondo
    rating DECIMAL(2,1) DEFAULT NULL, -- Rating general (0.5-5.0)
    description TEXT DEFAULT NULL, -- Sinopsis del juego
    platforms JSON DEFAULT NULL, -- Array de plataformas ["PC", "PS4", "Xbox One"]
    genres JSON DEFAULT NULL, -- Array de géneros ["Action", "RPG", "Adventure"]
    esrb_rating VARCHAR(20) DEFAULT NULL, -- Clasificación ESRB (E, T, M, AO)
    playtime INT UNSIGNED DEFAULT NULL, -- Tiempo de juego en horas (estimado)
    metacritic_score INT UNSIGNED DEFAULT NULL, -- Puntuación Metacritic (0-100)
    tags JSON DEFAULT NULL, -- Tags adicionales ["Singleplayer", "Multiplayer", "Open World"]
    addedTimestamp INT UNSIGNED DEFAULT NULL, -- Timestamp de cuándo se añadió a la biblioteca
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT check_game_rating CHECK (rating IS NULL OR (rating >= 0.5 AND rating <= 5.0 AND MOD(rating * 2, 1) = 0)),
    CONSTRAINT check_game_metacritic CHECK (metacritic_score IS NULL OR (metacritic_score >= 0 AND metacritic_score <= 100)),
    CONSTRAINT check_game_playtime CHECK (playtime IS NULL OR playtime >= 0)
);

-- Índices optimizados para búsquedas y filtros
CREATE INDEX idx_games_title ON games(title);
CREATE INDEX idx_games_slug ON games(slug);
CREATE INDEX idx_games_developer ON games(developer);
CREATE INDEX idx_games_publisher ON games(publisher);
CREATE INDEX idx_games_release_date ON games(release_date);
CREATE INDEX idx_games_rating ON games(rating);
CREATE INDEX idx_games_esrb_rating ON games(esrb_rating);
CREATE INDEX idx_games_metacritic ON games(metacritic_score);
CREATE INDEX idx_games_added_timestamp ON games(addedTimestamp);
CREATE INDEX idx_games_title_developer ON games(title, developer);
CREATE INDEX idx_games_created_at ON games(created_at);

-- Tabla de estados permitidos para videojuegos
CREATE TABLE IF NOT EXISTS game_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

-- Insertar estados predefinidos para videojuegos (kebab-case)
INSERT INTO game_statuses (name) VALUES 
('owned'),          -- Usuario posee el juego
('played'),         -- Ha jugado al juego
('completed'),      -- Ha completado el juego (historia principal)
('100-completed'),  -- Ha completado el juego al 100%
('playing'),        -- Jugando actualmente
('in-wishlist'),    -- En lista de deseos
('abandoned'),      -- Juego abandonado
('want-to-buy'),    -- Quiere comprarlo
('backlog');        -- En lista de pendientes

-- Tabla de relación muchos a muchos entre juegos y estados
CREATE TABLE IF NOT EXISTS game_has_statuses (
    game_id INT UNSIGNED NOT NULL,
    status_id INT NOT NULL,
    PRIMARY KEY (game_id, status_id),
    FOREIGN KEY (game_id) 
        REFERENCES games(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    FOREIGN KEY (status_id) 
        REFERENCES game_statuses(id) 
        ON DELETE CASCADE
);

CREATE INDEX idx_game_has_statuses_status_id ON game_has_statuses(status_id);
CREATE INDEX idx_game_has_statuses_game_status ON game_has_statuses(game_id, status_id);

-- Relación users -> games (cada usuario tiene su propia biblioteca de videojuegos)
CREATE TABLE IF NOT EXISTS user_games (
    user_id INT NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,   -- Fecha cuando el usuario completó el juego
    date_started DATE NULL DEFAULT NULL,        -- Fecha cuando empezó a jugar
    date_finished DATE NULL DEFAULT NULL,       -- Fecha cuando terminó el juego
    personal_rating DECIMAL(2,1) DEFAULT NULL,  -- Rating personal del usuario
    personal_notes TEXT DEFAULT NULL,           -- Notas personales sobre el juego
    hours_played DECIMAL(8,2) DEFAULT 0,        -- Horas jugadas con 2 decimales
    platform_played VARCHAR(100) DEFAULT NULL,  -- Plataforma en la que jugó
    PRIMARY KEY (user_id, game_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    INDEX idx_user_games_user_added (user_id, added_at),
    INDEX idx_user_games_completed (user_id, completed_at),
    INDEX idx_user_games_date_started (user_id, date_started),
    INDEX idx_user_games_date_finished (user_id, date_finished),
    INDEX idx_user_games_rating (user_id, personal_rating),
    INDEX idx_user_games_hours (user_id, hours_played),
    CONSTRAINT check_user_game_rating CHECK (personal_rating IS NULL OR (personal_rating >= 0.5 AND personal_rating <= 5.0 AND MOD(personal_rating * 2, 1) = 0)),
    CONSTRAINT check_user_game_hours CHECK (hours_played >= 0)
);

-- Estados personales de videojuegos por usuario
CREATE TABLE IF NOT EXISTS user_game_statuses (
    user_id INT NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    status_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, game_id, status_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES game_statuses(id) ON DELETE CASCADE,
    INDEX idx_user_game_statuses_user_status (user_id, status_id),
    INDEX idx_user_game_statuses_updated (user_id, updated_at)
);

-- Tags personalizados para videojuegos
CREATE TABLE IF NOT EXISTS user_game_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#007bff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_game_tag (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_game_tags_user (user_id),
    INDEX idx_user_game_tags_name (user_id, name)
);

-- Relación muchos a muchos: user_games -> user_game_tags
CREATE TABLE IF NOT EXISTS user_game_tag_assignments (
    user_id INT NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    tag_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, game_id, tag_id),
    FOREIGN KEY (user_id, game_id) REFERENCES user_games(user_id, game_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES user_game_tags(id) ON DELETE CASCADE,
    INDEX idx_game_tag_assignments_tag (tag_id),
    INDEX idx_game_tag_assignments_game (user_id, game_id),
    INDEX idx_game_tag_assignments_user (user_id)
);

-- Notas detalladas para videojuegos
CREATE TABLE IF NOT EXISTS user_game_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    game_id INT UNSIGNED NOT NULL,
    note_text TEXT NOT NULL,
    note_type VARCHAR(20) DEFAULT 'note',
    is_private TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    INDEX idx_user_game_notes_user_game (user_id, game_id),
    INDEX idx_user_game_notes_created (created_at)
);

-- Comentarios a las tablas
ALTER TABLE reading_sessions COMMENT = 'Sesiones de lectura independientes - permite relecturas y seguimiento detallado';
ALTER TABLE reading_progress_history COMMENT = 'Historial completo de progreso incluyendo retrocesos y reinicios por sesión';
ALTER TABLE games COMMENT = 'Videojuegos con datos de RAWG API';
ALTER TABLE user_games COMMENT = 'Biblioteca personal de videojuegos de cada usuario';

-- ============================================================================
-- MIGRACIÓN 001: Sistema de Álbumes Musicales (Spotify + Last.fm)
-- Descripción: Crea las tablas para el sistema de álbumes musicales
-- ============================================================================

CREATE TABLE IF NOT EXISTS albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    spotify_id VARCHAR(22) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    artist VARCHAR(500) DEFAULT NULL,
    artist_id VARCHAR(22) DEFAULT NULL,
    release_date VARCHAR(10) DEFAULT NULL,
    release_date_precision VARCHAR(5) DEFAULT NULL,
    cover_url VARCHAR(1024) DEFAULT NULL,
    genres JSON DEFAULT NULL,
    label VARCHAR(255) DEFAULT NULL,
    total_tracks INT UNSIGNED DEFAULT NULL,
    album_type VARCHAR(20) DEFAULT NULL,
    duration_ms INT UNSIGNED DEFAULT NULL,
    popularity INT UNSIGNED DEFAULT NULL,
    external_url VARCHAR(1024) DEFAULT NULL,
    upc VARCHAR(20) DEFAULT NULL,
    addedTimestamp INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT check_album_popularity CHECK (popularity IS NULL OR (popularity >= 0 AND popularity <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Álbumes musicales con datos de Spotify API';

CREATE INDEX idx_albums_spotify_id ON albums(spotify_id);
CREATE INDEX idx_albums_title ON albums(title);
CREATE INDEX idx_albums_artist ON albums(artist);
CREATE INDEX idx_albums_artist_id ON albums(artist_id);
CREATE INDEX idx_albums_release_date ON albums(release_date);
CREATE INDEX idx_albums_album_type ON albums(album_type);
CREATE INDEX idx_albums_popularity ON albums(popularity);
CREATE INDEX idx_albums_added_timestamp ON albums(addedTimestamp);
CREATE INDEX idx_albums_title_artist ON albums(title, artist);
CREATE INDEX idx_albums_created_at ON albums(created_at);
CREATE INDEX idx_albums_label ON albums(label);

CREATE TABLE IF NOT EXISTS album_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO album_statuses (name) VALUES 
('owned'),
('listened'),
('listening'),
('in-wishlist'),
('want-to-listen'),
('favorite'),
('abandoned'),
('re-listening');

CREATE TABLE IF NOT EXISTS album_has_statuses (
    album_id INT NOT NULL,
    status_id INT NOT NULL,
    PRIMARY KEY (album_id, status_id),
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (status_id) REFERENCES album_statuses(id) ON DELETE CASCADE
);

CREATE INDEX idx_album_has_statuses_status_id ON album_has_statuses(status_id);
CREATE INDEX idx_album_has_statuses_album_status ON album_has_statuses(album_id, status_id);

CREATE TABLE IF NOT EXISTS user_albums (
    user_id INT NOT NULL,
    album_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    date_started DATE NULL DEFAULT NULL,
    date_finished DATE NULL DEFAULT NULL,
    personal_rating DECIMAL(2,1) DEFAULT NULL,
    personal_notes TEXT DEFAULT NULL,
    listen_count INT UNSIGNED DEFAULT 0,
    favorite_track VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (user_id, album_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    INDEX idx_user_albums_user_added (user_id, added_at),
    INDEX idx_user_albums_completed (user_id, completed_at),
    INDEX idx_user_albums_date_started (user_id, date_started),
    INDEX idx_user_albums_date_finished (user_id, date_finished),
    INDEX idx_user_albums_rating (user_id, personal_rating),
    INDEX idx_user_albums_listen_count (user_id, listen_count),
    CONSTRAINT check_user_album_rating CHECK (personal_rating IS NULL OR (personal_rating >= 0.5 AND personal_rating <= 5.0 AND MOD(personal_rating * 2, 1) = 0)),
    CONSTRAINT check_user_album_listen_count CHECK (listen_count >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Biblioteca personal de álbumes musicales de cada usuario';

CREATE TABLE IF NOT EXISTS user_album_statuses (
    user_id INT NOT NULL,
    album_id INT NOT NULL,
    status_id INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, album_id, status_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES album_statuses(id) ON DELETE CASCADE,
    INDEX idx_user_album_statuses_user_status (user_id, status_id),
    INDEX idx_user_album_statuses_updated (user_id, updated_at)
);

CREATE TABLE IF NOT EXISTS user_album_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) DEFAULT '#007bff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_album_tag (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_album_tags_user (user_id),
    INDEX idx_user_album_tags_name (user_id, name)
);

CREATE TABLE IF NOT EXISTS user_album_tag_assignments (
    user_id INT NOT NULL,
    album_id INT NOT NULL,
    tag_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, album_id, tag_id),
    FOREIGN KEY (user_id, album_id) REFERENCES user_albums(user_id, album_id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES user_album_tags(id) ON DELETE CASCADE,
    INDEX idx_album_tag_assignments_tag (tag_id),
    INDEX idx_album_tag_assignments_album (user_id, album_id),
    INDEX idx_album_tag_assignments_user (user_id)
);

CREATE TABLE IF NOT EXISTS user_album_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    album_id INT NOT NULL,
    note_text TEXT NOT NULL,
    note_type VARCHAR(20) DEFAULT 'note',
    is_private TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    INDEX idx_user_album_notes_user_album (user_id, album_id),
    INDEX idx_user_album_notes_created (created_at)
);
