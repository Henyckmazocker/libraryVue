# Skill: Database (MySQL) — Library Vue

## Scope

This skill covers all database-related tasks: schema design, queries, migrations, data integrity, and Docker MySQL management for the Library Vue application.

## Environment

- **Engine**: MySQL 8.0 via Docker
- **Database**: `library_db`
- **Credentials**: `library_user` / password from `$DB_PASSWORD` env var (dev default: `library_pass`)
- **Ports**: `3308` (host) → `3306` (container)
- **Schema source**: `docker/database/init.sql` (development), `docker/database/init.prod.sql` (production)
- **Migrations folder**: `backend/database/migrations/` (currently unused — schema managed via init.sql)

## Access Commands

```bash
# Interactive MySQL shell
docker compose exec mysql mysql -u library_user -plibrary_pass library_db

# Execute a single query
docker compose exec mysql mysql -u library_user -plibrary_pass library_db \
  -e "SELECT * FROM users LIMIT 5;"

# Describe a table
docker compose exec mysql mysql -u library_user -plibrary_pass library_db \
  -e "DESCRIBE user_book_editions;"

# Dump current schema
docker compose exec mysql mysqldump -u library_user -plibrary_pass --no-data library_db
```

## Schema Architecture

### Design Principle: Work/Edition Model (Books)

Books use a **Work/Edition architecture** inspired by OpenLibrary:

- **`book_works`** — Abstract concept of a book (title, authors, subjects)
- **`book_editions`** — Specific published edition of a work (ISBN, publisher, pages, cover)
- **`user_book_editions`** — User's relationship with a specific edition (rating, progress, ownership)

This differs from the **production schema** which uses a flat `books` table with `isbn` as PK.

### Entity Tables (Catalog)

| Table | PK | Purpose | Notable Columns |
|---|---|---|---|
| `book_works` | `work_id` AUTO_INCREMENT | Abstract works | openlibrary_work_key, synthetic_work_key, title, authors JSON, subjects JSON |
| `book_editions` | `edition_id` AUTO_INCREMENT | Specific editions (FK → work_id) | isbn_13, isbn_10, google_books_id, publisher, pages, format ENUM, cover_url_*, languages JSON, series JSON |
| `movie` | `isbn` VARCHAR(20) | Movie catalog (isbn = TMDb/IMDb ID) | title, original_title, director, coverUrl, rating, description, genres JSON |
| `games` | `id` INT UNSIGNED | Game catalog (IGDB ID) | slug, title, developer, publisher, coverUrl, backgroundUrl, platforms JSON, genres JSON, metacritic_score |

### User Library Tables (User ↔ Entity)

| Table | Composite PK | Purpose | Notable Columns |
|---|---|---|---|
| `user_book_editions` | user_id + edition_id | User's book collection | current_page, edition_rating, work_rating, ownership_type ENUM, condition ENUM, location |
| `user_movies` | user_id + movie_isbn | User's movie collection | personal_rating DECIMAL(2,1), personal_notes TEXT, consumed_at |
| `user_games` | user_id + game_id | User's game collection | date_started DATE, date_finished DATE, personal_rating DECIMAL(2,1), hours_played DECIMAL(8,2), platform_played |

### Status System (Many-to-Many)

Each entity type has three tables for statuses:

1. **`{entity}_statuses`** — Allowed status definitions (id, name, description)
2. **`{entity}_has_statuses`** — Which statuses apply to which entity type
3. **`user_{entity}_statuses`** — User's assigned statuses for their library items

**Book statuses**: owned, read, to-read, reading, re-reading, want-to-buy, abandoned, paused  
**Movie statuses**: owned, viewed, in-watchlist, want-to-buy, abandoned  
**Game statuses**: owned, played, completed, 100-completed, playing, in-wishlist, abandoned, want-to-buy, backlog

### Tags System

Per-entity custom tags:

| Tables | Pattern |
|---|---|
| `user_book_tags` + `user_book_tag_assignments` | name, color per user |
| `user_movie_tags` + `user_movie_tag_assignments` | Same pattern |
| `user_game_tags` + `user_game_tag_assignments` | Same pattern |

### Notes System

| Table | Scope | Fields |
|---|---|---|
| `user_edition_notes` | Per page in book edition | page_number, note_text, note_type ENUM (note, quote, highlight, bookmark) |
| `user_game_notes` | Per game | note_text, note_type, is_private |
| `user_movie_notes` | Per movie | note_text, note_type, is_private |
| `user_movies.personal_notes` | Per movie (inline column) | TEXT field in user_movies (legacy, kept alongside separate notes table) |

### Reading Progress Tracking

| Table | Purpose |
|---|---|
| `reading_sessions` | Sessions per edition: session_number, start_date, end_date, start_page, end_page, is_active |
| `reading_progress_history` | Granular: current_page, previous_page, progress_type ENUM (manual, automatic, session_start, session_end) |

### Other Tables

| Table | Purpose |
|---|---|
| `users` | Google OAuth users: google_id, email, name, picture, preferences JSON, is_active |
| `user_preferences` | User preferences (JSON) |
| `user_follows` | Social follow system (follower_id, followed_id, is_active) |
| `admin_work_merges` | Audit log for work de-duplication |
| `versions` | Application versioning |

## Dev vs Production Schema Differences

| Aspect | Development (init.sql) | Production (init.prod.sql) |
|---|---|---|
| Books | Work/Edition architecture | Flat `books` table with isbn PK |
| User books | `user_book_editions` | `user_books` |
| Reading sessions | FK to `edition_id` | FK to `book_isbn` |
| Status naming | Kebab-case (`to-read`, `want-to-buy`) | Spaces (`to read`, `want to buy`) |
| Movies/Games | Identical | Identical |

## Common Operations

### Adding a New Column

```sql
-- 1. Add column
ALTER TABLE user_games ADD COLUMN new_field VARCHAR(100) NULL;

-- 2. Verify
DESCRIBE user_games;

-- 3. THEN update backend layers (see backend skill)
```

### Adding a New Status

```sql
-- Insert into status definition table
INSERT INTO game_statuses (name, description) VALUES ('on-hold', 'Game is on hold');

-- Link to entity
INSERT INTO game_has_statuses (game_id, status_id) 
SELECT g.id, gs.id FROM games g, game_statuses gs WHERE gs.name = 'on-hold';
```

### Querying User Library with Statuses

```sql
-- Get user's games with their statuses
SELECT g.*, ug.personal_rating, ug.hours_played,
       GROUP_CONCAT(gs.name) as statuses
FROM user_games ug
JOIN games g ON ug.game_id = g.id
LEFT JOIN user_game_statuses ugs ON ugs.user_game_id = ug.user_id AND ugs.game_id = ug.game_id
LEFT JOIN game_statuses gs ON gs.id = ugs.status_id
WHERE ug.user_id = ?
GROUP BY g.id;
```

## Rules & Conventions

1. **Always use `init.sql` as source of truth** for development schema
2. **Never modify production schema** without updating `init.prod.sql` separately
3. **JSON columns** (authors, subjects, genres, platforms, preferences) are stored as JSON type — use `JSON_EXTRACT()` for queries
4. **Ratings** use `DECIMAL(2,1)` — range 0.0 to 5.0 (stored with CHECK constraints in some tables)
5. **Timestamps**: `added_at` defaults `CURRENT_TIMESTAMP`, `completed_at` nullable
6. **Cascading deletes**: User library tables use `ON DELETE CASCADE` from users
7. **ENUM types** used for: format (hardcover, paperback, ebook, audiobook, other), ownership_type, condition, note_type, progress_type
8. **Character set**: `utf8mb4` with `unicode_ci` collation throughout
9. **After schema changes**: Restart backend container (`docker compose restart backend`) — no ORM cache to clear

## Debugging

```bash
# Check if a column exists
docker compose exec mysql mysql -u library_user -plibrary_pass library_db \
  -e "SHOW COLUMNS FROM user_games LIKE 'date_started';"

# Check foreign key constraints
docker compose exec mysql mysql -u library_user -plibrary_pass library_db \
  -e "SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_NAME='user_book_editions';"

# Check data integrity
docker compose exec mysql mysql -u library_user -plibrary_pass library_db \
  -e "SELECT COUNT(*) as orphaned FROM user_game_statuses ugs LEFT JOIN user_games ug ON ugs.user_game_id = ug.user_id AND ugs.game_id = ug.game_id WHERE ug.user_id IS NULL;"
```
