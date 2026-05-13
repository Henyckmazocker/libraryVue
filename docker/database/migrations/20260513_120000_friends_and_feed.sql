-- Migration: 20260513_120000_friends_and_feed
-- Description: Replace user_follows with reciprocal friendships + feed events + privacy settings

-- 0. Drop user_follows (replaced by friendships — reciprocal model)
DROP TABLE IF EXISTS user_follows;

-- 1. Username optional field on users (IF NOT EXISTS via INFORMATION_SCHEMA — MySQL 8.0 compatible)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'username'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN username VARCHAR(50) NULL UNIQUE',
  'SELECT ''username column already exists'''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Reciprocal friendships table
CREATE TABLE IF NOT EXISTS friendships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  requester_id INT NOT NULL,
  addressee_id INT NOT NULL,
  status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (addressee_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_friendship (requester_id, addressee_id),
  INDEX idx_friendships_addressee (addressee_id, status),
  INDEX idx_friendships_requester (requester_id, status)
);

-- 3. Feed events table
CREATE TABLE IF NOT EXISTS feed_events (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  event_type ENUM('item_added','status_changed','item_rated','notes_updated','reading_session','achievement') NOT NULL,
  entity_type ENUM('book','movie','game','album') NULL,
  entity_id VARCHAR(50) NULL,       -- isbn / tmdb_id / igdb_id / spotify_id
  entity_title VARCHAR(255) NULL,
  entity_cover VARCHAR(500) NULL,
  metadata JSON NULL,               -- {old_status, new_status, rating, achievement_name, etc.}
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_feed_user_time (user_id, created_at DESC)
);

-- 4. Per-event-type privacy settings
CREATE TABLE IF NOT EXISTS user_privacy_settings (
  user_id INT NOT NULL PRIMARY KEY,
  show_additions TINYINT(1) NOT NULL DEFAULT 1,
  show_status_changes TINYINT(1) NOT NULL DEFAULT 1,
  show_ratings TINYINT(1) NOT NULL DEFAULT 1,
  show_notes TINYINT(1) NOT NULL DEFAULT 0,
  show_reading_sessions TINYINT(1) NOT NULL DEFAULT 1,
  show_achievements TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
