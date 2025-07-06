-- Make sure we're using the right database
USE library_db;

-- Drop dependent table first
DROP TABLE IF EXISTS book_has_statuses;
-- Drop the statuses table
DROP TABLE IF EXISTS book_statuses;
-- Optionally, if you want to reset the books table too for a full clean slate:
-- DROP TABLE IF EXISTS books;

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


-- Drop dependent table first
DROP TABLE IF EXISTS movie_has_statuses;
-- Drop the statuses table
DROP TABLE IF EXISTS movie_statuses;
-- Optionally, if you want to reset the books table too for a full clean slate:
-- DROP TABLE IF EXISTS books;

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

-- You can add some initial data if you want for testing:
-- INSERT INTO books (isbn, title, author, rating, addedTimestamp) VALUES 
--   ('978-0321765723', 'Test Book 1: SQL', 'Author A', 4.5, UNIX_TIMESTAMP()),
--   ('978-0321765724', 'Test Book 2: More SQL', 'Author B', 3.0, UNIX_TIMESTAMP()); 
--
-- -- Example of linking books to statuses:
-- -- Assuming '978-0321765723' is book1 and 'owned' has id 1, 'read' has id 2:
-- -- INSERT INTO book_has_statuses (book_isbn, status_id) VALUES ('978-0321765723', 1);
-- -- INSERT INTO book_has_statuses (book_isbn, status_id) VALUES ('978-0321765723', 2); 