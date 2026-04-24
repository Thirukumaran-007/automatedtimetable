-- Migration: add year/semester + cohort flow
-- Apply this to the CURRENTLY SELECTED database (local: select `ctms`; hosted: select `railway`).

-- 1) Add year/semester to courses (if not already present)
-- NOTE: Some MySQL/MariaDB versions don't support `ADD COLUMN IF NOT EXISTS`.
-- This pattern is compatible across more versions.
SET @db := DATABASE();

SELECT IF(COUNT(*) = 0,
  'ALTER TABLE courses ADD COLUMN year_level TINYINT NULL AFTER department',
  'SELECT 1'
) INTO @sql
FROM information_schema.columns
WHERE table_schema = @db AND table_name = 'courses' AND column_name = 'year_level';
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT IF(COUNT(*) = 0,
  'ALTER TABLE courses ADD COLUMN semester_no TINYINT NULL AFTER year_level',
  'SELECT 1'
) INTO @sql
FROM information_schema.columns
WHERE table_schema = @db AND table_name = 'courses' AND column_name = 'semester_no';
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Cohorts table
CREATE TABLE IF NOT EXISTS cohorts (
  cohort_id INT AUTO_INCREMENT PRIMARY KEY,
  department VARCHAR(80) NOT NULL,
  year_level TINYINT NOT NULL,
  semester_no TINYINT NOT NULL,
  term VARCHAR(40) NOT NULL,
  section VARCHAR(30) NOT NULL,
  batch_year INT NOT NULL,
  expected_strength INT NOT NULL DEFAULT 40,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cohort_unique (department, year_level, semester_no, term, section, batch_year)
) ENGINE=InnoDB;

-- 3) Link class_offerings to cohort
SELECT IF(COUNT(*) = 0,
  'ALTER TABLE class_offerings ADD COLUMN cohort_id INT NULL AFTER course_id',
  'SELECT 1'
) INTO @sql
FROM information_schema.columns
WHERE table_schema = @db AND table_name = 'class_offerings' AND column_name = 'cohort_id';
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add FK only if it doesn't exist (MariaDB doesn't support IF NOT EXISTS for FK)
-- If this fails, ignore or add manually.

-- 4) Cohort subject selection + faculty mapping
CREATE TABLE IF NOT EXISTS cohort_courses (
  cohort_course_id INT AUTO_INCREMENT PRIMARY KEY,
  cohort_id INT NOT NULL,
  course_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cohort_course (cohort_id, course_id),
  CONSTRAINT fk_cc_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(cohort_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_cc_course FOREIGN KEY (course_id) REFERENCES courses(course_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cohort_faculty (
  cohort_faculty_id INT AUTO_INCREMENT PRIMARY KEY,
  cohort_id INT NOT NULL,
  course_id INT NOT NULL,
  faculty_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cohort_course_faculty (cohort_id, course_id),
  CONSTRAINT fk_cf_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(cohort_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_cf_course FOREIGN KEY (course_id) REFERENCES courses(course_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_cf_faculty FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
