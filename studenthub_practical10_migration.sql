-- Apply once to an existing StudentHub Practical 8/9 database.
USE studenthub_practical8;

ALTER TABLE users
  ADD COLUMN last_login DATETIME NULL;

CREATE TABLE auth_tokens (
  token_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (token_id),
  UNIQUE KEY uq_auth_tokens_hash (token_hash),
  KEY ix_auth_tokens_user_id (user_id),
  KEY ix_auth_tokens_expires_at (expires_at),
  CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Promote the lab's named admin account if it has already been registered.
-- Change the email here to match the account you want to use as the admin.
UPDATE users SET role = 'admin' WHERE email = 'admin@gmail.com';
