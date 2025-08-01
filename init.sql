-- Drop database if exists and recreate it
DROP DATABASE IF EXISTS library_db;
CREATE DATABASE library_db;
USE library_db;

-- Recreate tables (your existing CREATE TABLE statements)
CREATE TABLE IF NOT EXISTS books (
    isbn VARCHAR(20) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) DEFAULT NULL,
    coverUrl VARCHAR(1024) DEFAULT NULL,
    rating DECIMAL(2,1) DEFAULT NULL, -- e.g., 3.5 (precision 2, 1 decimal place)
    addedTimestamp INT UNSIGNED DEFAULT NULL,
    CONSTRAINT check_book_rating CHECK (rating IS NULL OR (rating >= 0.5 AND rating <= 5.0 AND MOD(rating * 2, 1) = 0))
);

-- Índices optimizados para books
CREATE INDEX idx_books_title ON books(title); -- Para búsquedas por título
CREATE INDEX idx_books_author ON books(author); -- Para búsquedas por autor
CREATE INDEX idx_books_rating ON books(rating); -- Para filtros y ordenación por rating
CREATE INDEX idx_books_added_timestamp ON books(addedTimestamp); -- Para ordenar por fecha de adición
CREATE INDEX idx_books_title_author ON books(title, author); -- Búsquedas combinadas

-- Table for allowed status types
CREATE TABLE IF NOT EXISTS book_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE -- e.g., 'owned', 'read', 'to buy', 'reading'
);

-- Populate allowed statuses
-- This ensures that only valid statuses can be referenced.
-- The Book::ALLOWED_STATUSES array should ideally be in sync with these values.
INSERT INTO book_statuses (name) VALUES ('owned'), ('read'), ('to read'), ('reading'), ('want to buy');


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
    author VARCHAR(255) DEFAULT NULL,
    coverUrl VARCHAR(1024) DEFAULT NULL,
    rating DECIMAL(2,1) DEFAULT NULL, -- e.g., 3.5 (precision 2, 1 decimal place)
    addedTimestamp INT UNSIGNED DEFAULT NULL,
    CONSTRAINT check_movie_rating CHECK (rating IS NULL OR (rating >= 0.5 AND rating <= 5.0 AND MOD(rating * 2, 1) = 0))
);

-- Índices optimizados para movies
CREATE INDEX idx_movies_title ON movie(title); -- Para búsquedas por título
CREATE INDEX idx_movies_author ON movie(author); -- Para búsquedas por director
CREATE INDEX idx_movies_rating ON movie(rating); -- Para filtros y ordenación por rating
CREATE INDEX idx_movies_added_timestamp ON movie(addedTimestamp); -- Para ordenar por fecha de adición
CREATE INDEX idx_movies_title_author ON movie(title, author); -- Búsquedas combinadas título-director

-- Table for allowed status types
CREATE TABLE IF NOT EXISTS movie_statuses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE -- e.g., 'owned', 'read', 'to buy', 'reading'
);

-- Populate allowed statuses
-- This ensures that only valid statuses can be referenced.
-- The Book::ALLOWED_STATUSES array should ideally be in sync with these values.
INSERT INTO movie_statuses (name) VALUES ('owned'), ('viewed'), ('in watchlist'), ('want to buy');

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
    personal_rating DECIMAL(2,1) DEFAULT NULL, -- Rating personal del usuario (puede diferir del global)
    personal_notes TEXT DEFAULT NULL,          -- Notas personales sobre el libro
    PRIMARY KEY (user_id, book_isbn),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_isbn) REFERENCES books(isbn) ON DELETE CASCADE,
    INDEX idx_user_books_user_added (user_id, added_at), -- Para obtener libros de un usuario ordenados por fecha
    INDEX idx_user_books_rating (user_id, personal_rating), -- Para filtros por rating personal
    CONSTRAINT check_user_book_rating CHECK (personal_rating IS NULL OR (personal_rating >= 0.5 AND personal_rating <= 5.0 AND MOD(personal_rating * 2, 1) = 0))
);

-- Relación users -> movies (cada usuario tiene su propia biblioteca de películas)
CREATE TABLE IF NOT EXISTS user_movies (
    user_id INT NOT NULL,
    movie_isbn VARCHAR(20) NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    personal_rating DECIMAL(2,1) DEFAULT NULL, -- Rating personal del usuario
    personal_notes TEXT DEFAULT NULL,          -- Notas personales sobre la película
    PRIMARY KEY (user_id, movie_isbn),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_isbn) REFERENCES movie(isbn) ON DELETE CASCADE,
    INDEX idx_user_movies_user_added (user_id, added_at), -- Para obtener películas de un usuario ordenadas por fecha
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

-- You can add some initial data if you want for testing:
-- INSERT INTO books (isbn, title, author, rating, addedTimestamp) VALUES 
--   ('978-0321765723', 'Test Book 1: SQL', 'Author A', 4.5, UNIX_TIMESTAMP()),
--   ('978-0321765724', 'Test Book 2: More SQL', 'Author B', 3.0, UNIX_TIMESTAMP()); 
--
-- -- Example of linking books to statuses:
-- -- Assuming '978-0321765723' is book1 and 'owned' has id 1, 'read' has id 2:
-- -- INSERT INTO book_has_statuses (book_isbn, status_id) VALUES ('978-0321765723', 1);
-- -- INSERT INTO book_has_statuses (book_isbn, status_id) VALUES ('978-0321765723', 2); 