-- Practical 12: event management fields for an existing StudentHub database.
-- Safe to re-run: each column is added only when it is missing.
USE studenthub_practical8;

SET @has_event_time = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'event_time');
SET @sql = IF(@has_event_time = 0, 'ALTER TABLE events ADD COLUMN event_time TIME NOT NULL DEFAULT ''09:00:00'' AFTER event_date', 'SELECT 1');
PREPARE event_migration FROM @sql; EXECUTE event_migration; DEALLOCATE PREPARE event_migration;

SET @has_capacity = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'capacity');
SET @sql = IF(@has_capacity = 0, 'ALTER TABLE events ADD COLUMN capacity INT UNSIGNED NOT NULL DEFAULT 100 AFTER category', 'SELECT 1');
PREPARE event_migration FROM @sql; EXECUTE event_migration; DEALLOCATE PREPARE event_migration;

SET @has_description = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'description');
SET @sql = IF(@has_description = 0, 'ALTER TABLE events ADD COLUMN description TEXT DEFAULT NULL AFTER capacity', 'SELECT 1');
PREPARE event_migration FROM @sql; EXECUTE event_migration; DEALLOCATE PREPARE event_migration;

SET @has_status = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'status');
SET @sql = IF(@has_status = 0, 'ALTER TABLE events ADD COLUMN status ENUM(''open'', ''closed'') NOT NULL DEFAULT ''open'' AFTER description', 'SELECT 1');
PREPARE event_migration FROM @sql; EXECUTE event_migration; DEALLOCATE PREPARE event_migration;

SET @has_poster = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'poster_path');
SET @sql = IF(@has_poster = 0, 'ALTER TABLE events ADD COLUMN poster_path VARCHAR(255) DEFAULT NULL AFTER status', 'SELECT 1');
PREPARE event_migration FROM @sql; EXECUTE event_migration; DEALLOCATE PREPARE event_migration;

-- Existing event records remain intact. Their new status defaults to open.
