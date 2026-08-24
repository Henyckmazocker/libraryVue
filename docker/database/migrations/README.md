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

1. **Never modify a migration that has already been applied** (committed and deployed). Add a new migration instead.
2. **Use `IF NOT EXISTS` / `IF EXISTS` where MySQL 8.0 supports it.** It works on `CREATE TABLE`,
   `DROP TABLE` and `DROP INDEX`, but **not** on `ALTER TABLE ... ADD COLUMN` or `ADD INDEX` — those
   are MariaDB extensions and fail with a syntax error. See the template below.
3. **Each file must be idempotent** where possible (safe to re-check manually).
4. **No rollback files** — design migrations carefully. Use `ALTER TABLE ... MODIFY` conservatively.
5. The baseline is `init.sql` (dev) / `init.prod.sql` (prod). Migrations are additive changes from that point.

## Running Migrations

```bash
# Development — applies pending migrations without resetting the DB
./dev-setup.sh --migrate

# Production — applies pending migrations without resetting the DB
./prod-deploy.sh --migrate

# Directly (from project root)
./docker/database/run_migrations.sh
./docker/database/run_migrations.sh --env-file .env.prod --compose-file docker-compose.prod.yml
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
