-- College Timetable Management System (CTMS)
-- Schema-only SQL (no CREATE/DROP DATABASE). Suitable for hosted DBs (Railway, etc.)
-- IMPORTANT: Select your target database before running, e.g.:
--   USE railway;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Core entities
CREATE TABLE faculty (
  faculty_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  department VARCHAR(80) NOT NULL,
  phone VARCHAR(30) NULL,
  max_weekly_hours INT NOT NULL DEFAULT 16,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_faculty_email (email),
  CHECK (max_weekly_hours BETWEEN 1 AND 40)
) ENGINE=InnoDB;

CREATE TABLE students (
  student_id INT AUTO_INCREMENT PRIMARY KEY,
  roll_no VARCHAR(30) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  program VARCHAR(80) NOT NULL,
  year_level TINYINT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_students_roll (roll_no),
  UNIQUE KEY uq_students_email (email),
  CHECK (year_level BETWEEN 1 AND 8)
) ENGINE=InnoDB;

CREATE TABLE rooms (
  room_id INT AUTO_INCREMENT PRIMARY KEY,
  room_code VARCHAR(30) NOT NULL,
  building VARCHAR(60) NOT NULL,
  capacity INT NOT NULL,
  room_type ENUM('Classroom','Lab','Seminar','Auditorium') NOT NULL DEFAULT 'Classroom',
  active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_rooms_code (room_code),
  CHECK (capacity BETWEEN 1 AND 1000)
) ENGINE=InnoDB;

CREATE TABLE courses (
  course_id INT AUTO_INCREMENT PRIMARY KEY,
  course_code VARCHAR(30) NOT NULL,
  course_title VARCHAR(150) NOT NULL,
  department VARCHAR(80) NOT NULL,
  year_level TINYINT NULL,
  semester_no TINYINT NULL,
  weekly_hours INT NOT NULL DEFAULT 3,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_courses_code (course_code),
  CHECK (year_level IS NULL OR year_level BETWEEN 1 AND 4),
  CHECK (semester_no IS NULL OR semester_no BETWEEN 1 AND 8),
  CHECK (weekly_hours BETWEEN 1 AND 12)
) ENGINE=InnoDB;

-- Cohorts: batch/section for a specific department + year/semester + term
CREATE TABLE cohorts (
  cohort_id INT AUTO_INCREMENT PRIMARY KEY,
  department VARCHAR(80) NOT NULL,
  year_level TINYINT NOT NULL,
  semester_no TINYINT NOT NULL,
  term VARCHAR(40) NOT NULL,
  section VARCHAR(30) NOT NULL,
  batch_year INT NOT NULL,
  expected_strength INT NOT NULL DEFAULT 40,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cohort_unique (department, year_level, semester_no, term, section, batch_year),
  CHECK (year_level BETWEEN 1 AND 4),
  CHECK (semester_no BETWEEN 1 AND 8),
  CHECK (batch_year BETWEEN 2000 AND 2100),
  CHECK (expected_strength BETWEEN 1 AND 1000)
) ENGINE=InnoDB;

-- A class offering (e.g., CS101 - Section A - 2025-26 Even)
CREATE TABLE class_offerings (
  class_id INT AUTO_INCREMENT PRIMARY KEY,
  course_id INT NOT NULL,
  cohort_id INT NULL,
  section VARCHAR(30) NOT NULL,
  term VARCHAR(40) NOT NULL,
  batch_year INT NOT NULL,
  expected_strength INT NOT NULL DEFAULT 40,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_class_unique (course_id, section, term, batch_year),
  CONSTRAINT fk_class_course FOREIGN KEY (course_id) REFERENCES courses(course_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_class_cohort FOREIGN KEY (cohort_id) REFERENCES cohorts(cohort_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CHECK (batch_year BETWEEN 2000 AND 2100),
  CHECK (expected_strength BETWEEN 1 AND 1000)
) ENGINE=InnoDB;

-- Per-cohort subject selection (which subjects run for that cohort)
CREATE TABLE cohort_courses (
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

-- Per-cohort faculty assignment per subject
CREATE TABLE cohort_faculty (
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

-- Faculty assigned to a class offering
CREATE TABLE faculty_assignments (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  faculty_id INT NOT NULL,
  role ENUM('Primary','Co-Teacher','Guest') NOT NULL DEFAULT 'Primary',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_faculty_class_role (class_id, faculty_id, role),
  CONSTRAINT fk_assign_class FOREIGN KEY (class_id) REFERENCES class_offerings(class_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_assign_faculty FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Students enrolled in class offering
CREATE TABLE enrollments (
  enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  student_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_enroll (class_id, student_id),
  CONSTRAINT fk_enroll_class FOREIGN KEY (class_id) REFERENCES class_offerings(class_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES students(student_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Meeting slots (timetable)
-- One row per meeting instance (e.g., Mon 10:00-11:00)
CREATE TABLE timetable_slots (
  slot_id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  faculty_id INT NOT NULL,
  room_id INT NOT NULL,
  day_of_week TINYINT NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_slot_class FOREIGN KEY (class_id) REFERENCES class_offerings(class_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_slot_faculty FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_slot_room FOREIGN KEY (room_id) REFERENCES rooms(room_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CHECK (day_of_week BETWEEN 1 AND 7),
  CHECK (start_time < end_time)
) ENGINE=InnoDB;

-- Prevent time overlaps for a given room/day
CREATE INDEX idx_room_day_time ON timetable_slots(room_id, day_of_week, start_time, end_time);
-- Prevent time overlaps for a given faculty/day
CREATE INDEX idx_faculty_day_time ON timetable_slots(faculty_id, day_of_week, start_time, end_time);
-- Helpful for class view
CREATE INDEX idx_class_day_time ON timetable_slots(class_id, day_of_week, start_time);

DELIMITER $$

CREATE TRIGGER trg_timetable_no_room_overlap
BEFORE INSERT ON timetable_slots
FOR EACH ROW
BEGIN
  IF EXISTS (
    SELECT 1
    FROM timetable_slots t
    WHERE t.room_id = NEW.room_id
      AND t.day_of_week = NEW.day_of_week
      AND (NEW.start_time < t.end_time AND NEW.end_time > t.start_time)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room is already booked for the selected time slot.';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM timetable_slots t
    WHERE t.faculty_id = NEW.faculty_id
      AND t.day_of_week = NEW.day_of_week
      AND (NEW.start_time < t.end_time AND NEW.end_time > t.start_time)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Faculty has a conflicting assignment for the selected time slot.';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM timetable_slots t
    WHERE t.class_id = NEW.class_id
      AND t.day_of_week = NEW.day_of_week
      AND (NEW.start_time < t.end_time AND NEW.end_time > t.start_time)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class already has a lecture scheduled at the selected time.';
  END IF;

  IF NEW.start_time < '08:00:00' OR NEW.end_time > '18:00:00' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Slots must be within 08:00 to 18:00.';
  END IF;
END$$

CREATE TRIGGER trg_timetable_no_room_overlap_upd
BEFORE UPDATE ON timetable_slots
FOR EACH ROW
BEGIN
  IF EXISTS (
    SELECT 1
    FROM timetable_slots t
    WHERE t.slot_id <> OLD.slot_id
      AND t.room_id = NEW.room_id
      AND t.day_of_week = NEW.day_of_week
      AND (NEW.start_time < t.end_time AND NEW.end_time > t.start_time)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Room is already booked for the selected time slot.';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM timetable_slots t
    WHERE t.slot_id <> OLD.slot_id
      AND t.faculty_id = NEW.faculty_id
      AND t.day_of_week = NEW.day_of_week
      AND (NEW.start_time < t.end_time AND NEW.end_time > t.start_time)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Faculty has a conflicting assignment for the selected time slot.';
  END IF;

  IF EXISTS (
    SELECT 1
    FROM timetable_slots t
    WHERE t.slot_id <> OLD.slot_id
      AND t.class_id = NEW.class_id
      AND t.day_of_week = NEW.day_of_week
      AND (NEW.start_time < t.end_time AND NEW.end_time > t.start_time)
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Class already has a lecture scheduled at the selected time.';
  END IF;

  IF NEW.start_time < '08:00:00' OR NEW.end_time > '18:00:00' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Slots must be within 08:00 to 18:00.';
  END IF;
END$$

-- Seed data
INSERT INTO faculty(full_name,email,department,phone,max_weekly_hours) VALUES
('Dr. Aisha Khan','aisha.khan@college.edu','CSE','9990001111',18),
('Prof. Rahul Mehta','rahul.mehta@college.edu','ECE','9990002222',16),
('Ms. Neha Sharma','neha.sharma@college.edu','CSE','9990003333',14);

INSERT INTO students(roll_no,full_name,email,program,year_level) VALUES
('CSE-001','Arjun Verma','arjun.verma@student.edu','B.Tech CSE',2),
('CSE-002','Sana Ali','sana.ali@student.edu','B.Tech CSE',2),
('ECE-001','Kabir Singh','kabir.singh@student.edu','B.Tech ECE',2);

INSERT INTO rooms(room_code,building,capacity,room_type,active) VALUES
('C-101','Main',60,'Classroom',1),
('C-102','Main',50,'Classroom',1),
('LAB-1','Tech',35,'Lab',1);

INSERT INTO courses(course_code,course_title,department,year_level,semester_no,weekly_hours) VALUES
('CS201','Database Management Systems','CSE',NULL,NULL,4),
('CS202','Data Structures','CSE',NULL,NULL,4),
('EC201','Digital Electronics','ECE',NULL,NULL,3);

INSERT INTO class_offerings(course_id,section,term,batch_year,expected_strength)
SELECT course_id,'A','2025-26 Even',2024,50 FROM courses WHERE course_code='CS201';
INSERT INTO class_offerings(course_id,section,term,batch_year,expected_strength)
SELECT course_id,'A','2025-26 Even',2024,50 FROM courses WHERE course_code='CS202';

-- Assign faculty to classes
INSERT INTO faculty_assignments(class_id,faculty_id,role)
SELECT co.class_id, f.faculty_id, 'Primary'
FROM class_offerings co
JOIN courses c ON c.course_id=co.course_id
JOIN faculty f ON f.email='aisha.khan@college.edu'
WHERE c.course_code='CS201' AND co.section='A';

INSERT INTO faculty_assignments(class_id,faculty_id,role)
SELECT co.class_id, f.faculty_id, 'Primary'
FROM class_offerings co
JOIN courses c ON c.course_id=co.course_id
JOIN faculty f ON f.email='neha.sharma@college.edu'
WHERE c.course_code='CS202' AND co.section='A';

-- Enroll students
INSERT INTO enrollments(class_id,student_id)
SELECT co.class_id, s.student_id
FROM class_offerings co
JOIN courses c ON c.course_id=co.course_id
JOIN students s ON s.roll_no IN ('CSE-001','CSE-002')
WHERE c.course_code='CS201' AND co.section='A';

INSERT INTO enrollments(class_id,student_id)
SELECT co.class_id, s.student_id
FROM class_offerings co
JOIN courses c ON c.course_id=co.course_id
JOIN students s ON s.roll_no IN ('CSE-001','CSE-002')
WHERE c.course_code='CS202' AND co.section='A';

-- Sample timetable slots
INSERT INTO timetable_slots(class_id,faculty_id,room_id,day_of_week,start_time,end_time)
SELECT co.class_id, f.faculty_id, r.room_id, 1, '10:00:00', '11:00:00'
FROM class_offerings co
JOIN courses c ON c.course_id=co.course_id
JOIN faculty f ON f.email='aisha.khan@college.edu'
JOIN rooms r ON r.room_code='C-101'
WHERE c.course_code='CS201' AND co.section='A';

INSERT INTO timetable_slots(class_id,faculty_id,room_id,day_of_week,start_time,end_time)
SELECT co.class_id, f.faculty_id, r.room_id, 3, '11:00:00', '12:00:00'
FROM class_offerings co
JOIN courses c ON c.course_id=co.course_id
JOIN faculty f ON f.email='neha.sharma@college.edu'
JOIN rooms r ON r.room_code='C-102'
WHERE c.course_code='CS202' AND co.section='A';

DELIMITER ;

