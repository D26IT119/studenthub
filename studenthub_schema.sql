-- StudentHub Practical 8: normalized MySQL schema and demo data
-- Import this file in phpMyAdmin (it creates/selects the database).

CREATE DATABASE IF NOT EXISTS studenthub_practical8
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE studenthub_practical8;

-- Practical 9 account credentials are kept separate from academic profiles.
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  username VARCHAR(30) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'student',
  last_login DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_tokens (
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

CREATE TABLE IF NOT EXISTS audit_logs (
  log_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  action VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (log_id),
  KEY ix_audit_logs_user_id (user_id),
  CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS courses (
  course_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_name VARCHAR(100) NOT NULL,
  PRIMARY KEY (course_id),
  UNIQUE KEY uq_courses_name (course_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  mobile VARCHAR(15) NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  year TINYINT UNSIGNED NOT NULL,
  gender ENUM('Male', 'Female', 'Other') NOT NULL,
  password_hash VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_students_email (email),
  CONSTRAINT chk_students_year CHECK (year BETWEEN 1 AND 4),
  CONSTRAINT fk_students_course FOREIGN KEY (course_id)
    REFERENCES courses (course_id)
    ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  event_date DATE NOT NULL,
  location VARCHAR(100) NOT NULL,
  category VARCHAR(50) NOT NULL,
  PRIMARY KEY (event_id),
  UNIQUE KEY uq_events_title_date (title, event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
  reg_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  event_id INT UNSIGNED NOT NULL,
  registered_on TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (reg_id),
  UNIQUE KEY uq_registration_student_event (student_id, event_id),
  CONSTRAINT fk_registrations_student FOREIGN KEY (student_id)
    REFERENCES students (id)
    ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_registrations_event FOREIGN KEY (event_id)
    REFERENCES events (event_id)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- INSERT IGNORE keeps re-importing this teaching seed file safe.
INSERT IGNORE INTO courses (course_id, course_name) VALUES
  (1, 'Information Technology'),
  (2, 'Computer Science'),
  (3, 'Computer Engineering'),
  (4, 'Data Science');

INSERT IGNORE INTO students (id, name, email, mobile, course_id, year, gender) VALUES
  (1, 'Jash Tanna', 'jash.tanna@studenthub.edu', '9000000001', 1, 2, 'Male'),
  (2, 'Aarav Shah', 'aarav.shah@studenthub.edu', '9000000002', 2, 3, 'Male'),
  (3, 'Diya Patel', 'diya.patel@studenthub.edu', '9000000003', 1, 1, 'Female'),
  (4, 'Riya Mehta', 'riya.mehta@studenthub.edu', '9000000004', 4, 2, 'Female');

INSERT IGNORE INTO events (event_id, title, event_date, location, category) VALUES
  (1, 'Career Guidance Session', '2026-10-12', 'Seminar Hall', 'Career'),
  (2, 'Sports Meet', '2026-10-15', 'College Ground', 'Sports'),
  (3, 'Photography Workshop', '2026-10-20', 'Media Room', 'Workshop'),
  (4, 'Coding Hackathon', '2026-10-22', 'Innovation Lab', 'Technology');

INSERT IGNORE INTO registrations (reg_id, student_id, event_id) VALUES
  (1, 1, 1),
  (2, 1, 4),
  (3, 2, 4),
  (4, 3, 2);
