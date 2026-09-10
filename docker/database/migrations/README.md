# Database Migrations

This folder contains incremental SQL migration files applied on top of the baseline schema (`init.sql` / `init.prod.sql`).

## Naming Convention

```
YYYYMMDD_HHMMSS_description.sql
```

Examples:
```
20260511_120000_add_albums_table.sql
20260512_090000_add_user_game_playtime_index.sql
20260515_143000_alter_movies_add_runtime_column.sql
```

- **Date+time prefix** ensures deterministic alphabetical ordering.
- **Description** uses lowercase underscores, brief and action-oriented.
- Files are applied **exactly once** and tracked in the `schema_migrations` table.

## Rules

1. **Never modify a migration that has already been applied** (committed and deployed). Add a new
   migration instead. The runner enforces this: it stores each file's `sha256sum` in
   `schema_migrations.checksum` and compares it with the file on disk on every run, so an edited
   migration shows up as `(MODIFICADA después de aplicarse)` and the run **aborts before applying
   anything**, printing both sums (`run_migrations.sh:256-264,279-293`).
2. **Use `IF NOT EXISTS` / `IF EXISTS` only where MySQL 8.0 really supports it.** It works on
   `CREATE TABLE ... IF NOT EXISTS` and `DROP TABLE ... IF EXISTS`, and on nothing else a migration
   usually needs: `ALTER TABLE ... ADD COLUMN`, `ADD INDEX`, `DROP COLUMN` and `DROP INDEX` do
   **not** take `IF [NOT] EXISTS` — those are MariaDB extensions and fail with syntax error 1064.
   `DROP INDEX ... IF EXISTS` included: checked against MySQL 8.0.44 and written down in
   `20260910_120000_drop_consumption_dates.sql:32-37`. Guard all four with an `INFORMATION_SCHEMA`
   block instead — see the template below.
3. **Each file must be idempotent** where possible (safe to re-check manually).
4. **No rollback files** — design migrations carefully. Use `ALTER TABLE ... MODIFY` conservatively.
5. The baseline is `init.sql` (dev) / `init.prod.sql` (prod). Migrations are additive changes from that point.

## Running Migrations

### Production: code first, schema second

The order is **mandatory**, not a preference:

1. Merge `dev` into `master` and `git pull` in the production checkout (`libraryVue_prod`).
2. `./prod-deploy.sh --rebuild` — builds the images from that checkout and deploys them. The
   backend image is stamped with the commit it was built from, as the OCI label
   `org.opencontainers.image.revision` (`docker/backend/Dockerfile.backend.prod:70-71`, filled from
   the `GIT_SHA` that `deploy_services()` exports, `prod-deploy.sh:464-466`).
3. `./prod-deploy.sh --migrate` — only afterwards. It runs `check_image_revision()` →
   `backup_prod_db()` → this runner, in that order (`prod-deploy.sh:876-887`):
   it **refuses to migrate** when the stamp on `libraryvue_prod-backend:latest` is not the
   checkout's `HEAD`, or is missing (`prod-deploy.sh:847-874`), and it **dumps the database** to
   `docker/database/backups/library_db_prod_<timestamp>.sql.gz` before touching it, aborting if the
   dump fails or comes out under 1 KB and keeping only the 5 most recent
   (`prod-deploy.sh:756-818`).

Why the order: a migration changes the schema underneath a backend that is already running.
Applying `20260910_120000_drop_consumption_dates.sql` before deploying the new code would remove
six columns (`user_book_editions.consumed_at` and `.last_session_completed_at`,
`user_movies.consumed_at`, `user_games.completed_at`, `user_albums.completed_at`,
`user_videos.watched_at`) that the deployed backend still names in its queries — every read of
those tables would start failing with `1054 Unknown column`. Deploy the code that no longer needs
them first, then drop them.

Step 3's guard is what makes the wrong order impossible: without step 2 the live image still
carries the old commit, so `--migrate` aborts before dumping anything and before touching the
database.

### Commands

```bash
# Development — applies pending migrations without resetting the DB
./dev-setup.sh --migrate

# Production — backs up, checks the deployed code, then applies (see the order above)
./prod-deploy.sh --migrate

# Directly (from project root)
./docker/database/run_migrations.sh
./docker/database/run_migrations.sh --env-file .env.prod --compose-file docker-compose.prod.yml
./docker/database/run_migrations.sh --service mysql-test   # another compose service (default: mysql)
```

## Migration Template

```sql
-- Migration: YYYYMMDD_HHMMSS_description.sql
-- Author: <name>
-- Description: <what this migration does>

-- Example: add a column idempotently (MySQL 8.0 has no ADD COLUMN IF NOT EXISTS)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_games' AND COLUMN_NAME = 'new_field'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE user_games ADD COLUMN new_field VARCHAR(100) NULL COMMENT ''Description of the field''',
  'SELECT ''new_field column already exists'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Example: add an index idempotently (same reason)
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_games' AND INDEX_NAME = 'idx_new_field'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE user_games ADD INDEX idx_new_field (new_field)',
  'SELECT ''idx_new_field already exists'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Example: drop an index idempotently (MySQL 8.0 has no DROP INDEX IF EXISTS
-- either — it fails with error 1064). Drop the index BEFORE its column when the
-- two go together: composite indexes are rewritten on the fly otherwise.
-- COUNT(*) on STATISTICS returns one row PER COLUMN of the index (two, on a
-- composite one), which is why the comparison is `> 0` and not `= 1`.
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_games' AND INDEX_NAME = 'idx_old_field'
);
SET @sql = IF(@idx_exists > 0,
  'ALTER TABLE user_games DROP INDEX idx_old_field',
  'SELECT ''idx_old_field already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Example: drop a column idempotently (MySQL 8.0 has no DROP COLUMN IF EXISTS)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_games' AND COLUMN_NAME = 'old_field'
);
SET @sql = IF(@col_exists > 0,
  'ALTER TABLE user_games DROP COLUMN old_field',
  'SELECT ''user_games.old_field already dropped'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Example: create a new table
CREATE TABLE IF NOT EXISTS new_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- ... columns
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Tracking Table

The runner automatically creates `schema_migrations` in the database:

```sql
CREATE TABLE schema_migrations (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    filename   VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    checksum   VARCHAR(64)  NOT NULL
);
```

To inspect which migrations have been applied:
```bash
docker compose exec mysql mysql -u library_user -plibrary_pass library_db \
  -e "SELECT filename, applied_at FROM schema_migrations ORDER BY applied_at;"
```
