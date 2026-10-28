-- Practical 13 event history table for an existing StudentHub installation.
-- Safe to re-run; it preserves the current events, registrations, and users.
USE studenthub_practical8;

CREATE TABLE IF NOT EXISTS event_audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED DEFAULT NULL,
  event_title VARCHAR(150) NOT NULL,
  action ENUM('created', 'updated', 'deleted') NOT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_event_audit_event_id (event_id),
  KEY ix_event_audit_occurred_at (occurred_at),
  CONSTRAINT fk_event_audit_admin FOREIGN KEY (admin_user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_event_audit_event FOREIGN KEY (event_id)
    REFERENCES events (event_id) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
