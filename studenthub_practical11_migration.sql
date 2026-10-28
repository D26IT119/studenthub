-- Practical 11 upgrade for an existing studenthub_practical8 database.
-- Safe to run repeatedly: the status column is added only when missing,
-- and sample rows use INSERT IGNORE.
USE studenthub_practical8;

SET @has_student_status = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'status'
);
SET @status_sql = IF(
  @has_student_status = 0,
  'ALTER TABLE students ADD COLUMN status ENUM(''active'', ''deleted'') NOT NULL DEFAULT ''active'' AFTER password_hash',
  'SELECT 1'
);
PREPARE status_statement FROM @status_sql;
EXECUTE status_statement;
DEALLOCATE PREPARE status_statement;

INSERT IGNORE INTO courses (course_id, course_name) VALUES
  (1, 'Information Technology'),
  (2, 'Computer Science'),
  (3, 'Computer Engineering'),
  (4, 'Data Science');

INSERT IGNORE INTO students (id, name, email, mobile, course_id, year, gender) VALUES
  (1, 'Jash Tanna', 'jash.tanna@studenthub.edu', '9000000001', 1, 2, 'Male'),
  (2, 'Aarav Shah', 'aarav.shah@studenthub.edu', '9000000002', 2, 3, 'Male'),
  (3, 'Diya Patel', 'diya.patel@studenthub.edu', '9000000003', 1, 1, 'Female'),
  (4, 'Riya Mehta', 'riya.mehta@studenthub.edu', '9000000004', 4, 2, 'Female'),
  (5, 'Reyansh Mehta', 'reyansh.mehta@studenthub.edu', '9000000005', 2, 1, 'Male'),
  (6, 'Anaya Desai', 'anaya.desai@studenthub.edu', '9000000006', 3, 2, 'Female'),
  (7, 'Vivaan Shah', 'vivaan.shah@studenthub.edu', '9000000007', 1, 3, 'Male'),
  (8, 'Myra Joshi', 'myra.joshi@studenthub.edu', '9000000008', 4, 1, 'Female'),
  (9, 'Advik Patel', 'advik.patel@studenthub.edu', '9000000009', 2, 4, 'Male'),
  (10, 'Sara Mehta', 'sara.mehta@studenthub.edu', '9000000010', 3, 3, 'Female'),
  (11, 'Arjun Shah', 'arjun.shah@studenthub.edu', '9000000011', 1, 2, 'Male'),
  (12, 'Ira Desai', 'ira.desai@studenthub.edu', '9000000012', 4, 4, 'Female'),
  (13, 'Kabir Joshi', 'kabir.joshi@studenthub.edu', '9000000013', 2, 2, 'Male'),
  (14, 'Aadhya Patel', 'aadhya.patel@studenthub.edu', '9000000014', 3, 1, 'Female'),
  (15, 'Vihaan Mehta', 'vihaan.mehta@studenthub.edu', '9000000015', 1, 4, 'Male'),
  (16, 'Kiara Shah', 'kiara.shah@studenthub.edu', '9000000016', 4, 3, 'Female'),
  (17, 'Rudra Desai', 'rudra.desai@studenthub.edu', '9000000017', 2, 1, 'Male'),
  (18, 'Navya Joshi', 'navya.joshi@studenthub.edu', '9000000018', 3, 4, 'Female'),
  (19, 'Dhruv Patel', 'dhruv.patel@studenthub.edu', '9000000019', 1, 3, 'Male'),
  (20, 'Prisha Mehta', 'prisha.mehta@studenthub.edu', '9000000020', 4, 2, 'Female');
