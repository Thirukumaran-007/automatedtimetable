-- One-click seed for CTMS (inlined; phpMyAdmin-friendly)
-- Generated: 2026-03-27
-- This script DROPS and recreates database ctms.

-- -----------------------------------------------------------------------------
-- Schema
-- -----------------------------------------------------------------------------

-- College Timetable Management System (CTMS)
-- MySQL schema for XAMPP (MariaDB/MySQL)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

DROP DATABASE IF EXISTS ctms;
CREATE DATABASE ctms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ctms;

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


-- -----------------------------------------------------------------------------
-- Seed: faculty + courses
-- -----------------------------------------------------------------------------

-- Optional sample seed data for CTMS
-- 30 Faculty + 45 Courses across departments:
-- IT, CSE, ECE, EEE, MECH, CIVIL, MBA, MCA, CSBS, CSY
--
-- Usage (phpMyAdmin): select `ctms` DB -> Import this file.

USE ctms;

START TRANSACTION;

-- Faculty (30)
INSERT INTO faculty(full_name,email,department,phone,max_weekly_hours) VALUES
('Dr. Arvind Kumar','arvind.kumar@college.edu','CSE','9000000001',18),
('Dr. Priya Nair','priya.nair@college.edu','CSE','9000000002',16),
('Mr. Karthik Raj','karthik.raj@college.edu','CSE','9000000003',16),
('Ms. Ananya Iyer','ananya.iyer@college.edu','CSE','9000000004',14),
('Dr. Suresh Babu','suresh.babu@college.edu','IT','9000000005',16),
('Ms. Nandhini S','nandhini.s@college.edu','IT','9000000006',14),
('Mr. Vivek Anand','vivek.anand@college.edu','IT','9000000007',16),
('Dr. Meera Menon','meera.menon@college.edu','IT','9000000008',18),
('Prof. Rahul Mehta','rahul.mehta2@college.edu','ECE','9000000009',16),
('Dr. Kavitha R','kavitha.r@college.edu','ECE','9000000010',18),
('Mr. Praveen S','praveen.s@college.edu','ECE','9000000011',14),
('Ms. Deepa K','deepa.k@college.edu','ECE','9000000012',14),
('Dr. Ganesh I','ganesh.i@college.edu','EEE','9000000013',18),
('Ms. Lakshmi P','lakshmi.p@college.edu','EEE','9000000014',16),
('Mr. Dinesh V','dinesh.v@college.edu','EEE','9000000015',14),
('Ms. Shalini R','shalini.r@college.edu','EEE','9000000016',14),
('Dr. Senthil Kumar','senthil.kumar@college.edu','MECH','9000000017',18),
('Mr. Bala Murugan','bala.murugan@college.edu','MECH','9000000018',16),
('Ms. Preethi S','preethi.s@college.edu','MECH','9000000019',14),
('Mr. Raghav S','raghav.s@college.edu','MECH','9000000020',14),
('Dr. Uma Devi','uma.devi@college.edu','CIVIL','9000000021',18),
('Mr. Kishore M','kishore.m@college.edu','CIVIL','9000000022',16),
('Ms. Janani S','janani.s@college.edu','CIVIL','9000000023',14),
('Mr. Naveen K','naveen.k@college.edu','CIVIL','9000000024',14),
('Dr. Farah Khan','farah.khan@college.edu','MBA','9000000025',16),
('Mr. Ajay Sharma','ajay.sharma@college.edu','MBA','9000000026',14),
('Ms. Riya Bose','riya.bose@college.edu','MBA','9000000027',14),
('Dr. Joseph Mathew','joseph.mathew@college.edu','MCA','9000000028',16),
('Ms. Shruthi V','shruthi.v@college.edu','MCA','9000000029',14),
('Mr. Imran Ali','imran.ali@college.edu','CSBS','9000000030',14)
ON DUPLICATE KEY UPDATE
full_name=VALUES(full_name), department=VALUES(department), phone=VALUES(phone), max_weekly_hours=VALUES(max_weekly_hours);

-- Courses (45)
INSERT INTO courses(course_code,course_title,department,weekly_hours) VALUES
-- CSE (10)
('CS301','Design and Analysis of Algorithms','CSE',4),
('CS302','Operating Systems','CSE',4),
('CS303','Database Management Systems','CSE',4),
('CS304','Computer Networks','CSE',4),
('CS305','Software Engineering','CSE',3),
('CS306','Artificial Intelligence','CSE',3),
('CS307','Machine Learning','CSE',3),
('CS308','Compiler Design','CSE',3),
('CS309','Web Technologies','CSE',3),
('CS310','Cloud Computing','CSE',3),

-- IT (8)
('IT301','Python Programming','IT',4),
('IT302','Web Application Development','IT',4),
('IT303','Data Warehousing and Mining','IT',3),
('IT304','Mobile Application Development','IT',3),
('IT305','Internet of Things','IT',3),
('IT306','Cyber Security Fundamentals','IT',3),
('IT307','DevOps Essentials','IT',3),
('IT308','UI/UX Design Basics','IT',2),

-- ECE (7)
('EC301','Analog Circuits','ECE',4),
('EC302','Digital Signal Processing','ECE',4),
('EC303','Microprocessors and Microcontrollers','ECE',4),
('EC304','Communication Systems','ECE',3),
('EC305','VLSI Design','ECE',3),
('EC306','Embedded Systems','ECE',3),
('EC307','Antenna and Wave Propagation','ECE',3),

-- EEE (5)
('EE301','Electrical Machines','EEE',4),
('EE302','Power Systems','EEE',4),
('EE303','Control Systems','EEE',3),
('EE304','Power Electronics','EEE',3),
('EE305','Renewable Energy Systems','EEE',3),

-- MECH (5)
('ME301','Thermodynamics','MECH',4),
('ME302','Fluid Mechanics and Machinery','MECH',4),
('ME303','Manufacturing Processes','MECH',3),
('ME304','Machine Design','MECH',3),
('ME305','Heat and Mass Transfer','MECH',3),

-- CIVIL (4)
('CE301','Structural Analysis','CIVIL',4),
('CE302','Concrete Technology','CIVIL',3),
('CE303','Geotechnical Engineering','CIVIL',3),
('CE304','Transportation Engineering','CIVIL',3),

-- MBA (3)
('MB301','Principles of Management','MBA',3),
('MB302','Financial Management','MBA',3),
('MB303','Marketing Management','MBA',3),

-- MCA (2)
('MC301','Advanced Java Programming','MCA',4),
('MC302','Data Structures and Algorithms','MCA',4),

-- CSBS (1)
('CB301','Business Analytics for Engineers','CSBS',3)
ON DUPLICATE KEY UPDATE
course_title=VALUES(course_title), department=VALUES(department), weekly_hours=VALUES(weekly_hours);

-- Add year/semester tags for easier cohort filtering (sample mapping)
UPDATE courses SET year_level=2, semester_no=4 WHERE department IN ('CSE','IT') AND course_code IN ('CS301','CS302','CS303','CS304','CS305','IT301','IT302','IT303');
UPDATE courses SET year_level=2, semester_no=4 WHERE department='ECE' AND course_code IN ('EC301','EC302');
UPDATE courses SET year_level=2, semester_no=4 WHERE department='EEE' AND course_code IN ('EE301','EE302');
UPDATE courses SET year_level=2, semester_no=4 WHERE department='MECH' AND course_code IN ('ME301','ME302');
UPDATE courses SET year_level=2, semester_no=4 WHERE department='CIVIL' AND course_code IN ('CE301','CE302');

COMMIT;


-- -----------------------------------------------------------------------------
-- Seed: cohorts + rooms + class offerings + starter timetable
-- -----------------------------------------------------------------------------

-- Sample data for Year/Semester Cohort flow (ALL departments)
-- Creates cohorts, selects subjects for them, assigns faculty to each subject.
-- Usage: Apply migration first, then import this file into ctms.

USE ctms;

START TRANSACTION;

-- -----------------------------------------------------------------------------
-- Notes
-- - This seed assumes the schema from `sql/timetable.sql`.
-- - It works best if you import `sql/seed_sample_data.sql` first (faculty + courses).
-- - It creates: cohorts -> cohort_courses -> cohort_faculty -> rooms -> class_offerings
--   -> faculty_assignments -> timetable_slots (conflict-free starter view)
-- -----------------------------------------------------------------------------

-- Create sample cohorts (2nd year / 4th sem) for all requested departments
INSERT INTO cohorts(department, year_level, semester_no, term, section, batch_year, expected_strength)
VALUES
('IT',   2, 4, '2025-26 Even', 'A', 2024, 60),
('CSE',  2, 4, '2025-26 Even', 'A', 2024, 63),
('ECE',  2, 4, '2025-26 Even', 'A', 2024, 60),
('EEE',  2, 4, '2025-26 Even', 'A', 2024, 60),
('MECH', 2, 4, '2025-26 Even', 'A', 2024, 60),
('CIVIL',2, 4, '2025-26 Even', 'A', 2024, 60),
('MBA',  2, 4, '2025-26 Even', 'A', 2024, 60),
('MCA',  2, 4, '2025-26 Even', 'A', 2024, 60),
('CSBS', 2, 4, '2025-26 Even', 'A', 2024, 60),
('CSY',  2, 4, '2025-26 Even', 'A', 2024, 60)
ON DUPLICATE KEY UPDATE expected_strength=VALUES(expected_strength);

-- Cohort ids
SET @it_cohort_id    = (SELECT cohort_id FROM cohorts WHERE department='IT'    AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);
SET @cse_cohort_id   = (SELECT cohort_id FROM cohorts WHERE department='CSE'   AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);
SET @ece_cohort_id   = (SELECT cohort_id FROM cohorts WHERE department='ECE'   AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);
SET @eee_cohort_id   = (SELECT cohort_id FROM cohorts WHERE department='EEE'   AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);
SET @mech_cohort_id  = (SELECT cohort_id FROM cohorts WHERE department='MECH'  AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);
SET @civil_cohort_id = (SELECT cohort_id FROM cohorts WHERE department='CIVIL' AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);
SET @mba_cohort_id   = (SELECT cohort_id FROM cohorts WHERE department='MBA'   AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);
SET @mca_cohort_id   = (SELECT cohort_id FROM cohorts WHERE department='MCA'   AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);
SET @csbs_cohort_id  = (SELECT cohort_id FROM cohorts WHERE department='CSBS'  AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);
SET @csy_cohort_id   = (SELECT cohort_id FROM cohorts WHERE department='CSY'   AND year_level=2 AND semester_no=4 AND term='2025-26 Even' AND section='A' AND batch_year=2024 LIMIT 1);

-- Ensure some courses are tagged for Year2/Sem4 (safe updates)
UPDATE courses SET year_level=2, semester_no=4 WHERE course_code IN (
	'CS301','CS302','CS303','CS304','CS305',
	'IT301','IT302','IT303',
	'EC301','EC302','EC303',
	'EE301','EE302','EE303',
	'ME301','ME302','ME303',
	'CE301','CE302','CE303',
	'MB301','MB302','MB303',
	'MC301','MC302','MC303',
	'CB301','CB302','CB303',
	'CY301','CY302','CY303'
);

-- If your DB doesn't already contain these course codes, create them quickly.
-- Uses a lightweight pattern: 3 subjects/department + sensible weekly hours.
INSERT IGNORE INTO courses(course_code, course_title, department, weekly_hours, year_level, semester_no)
VALUES
('IT301','IT Subject 1','IT',3,2,4),('IT302','IT Subject 2','IT',3,2,4),('IT303','IT Lab 1','IT',2,2,4),
('CS301','CSE Subject 1','CSE',3,2,4),('CS302','CSE Subject 2','CSE',3,2,4),('CS303','CSE Lab 1','CSE',2,2,4),
('EC301','ECE Subject 1','ECE',3,2,4),('EC302','ECE Subject 2','ECE',3,2,4),('EC303','ECE Lab 1','ECE',2,2,4),
('EE301','EEE Subject 1','EEE',3,2,4),('EE302','EEE Subject 2','EEE',3,2,4),('EE303','EEE Lab 1','EEE',2,2,4),
('ME301','MECH Subject 1','MECH',3,2,4),('ME302','MECH Subject 2','MECH',3,2,4),('ME303','MECH Lab 1','MECH',2,2,4),
('CE301','CIVIL Subject 1','CIVIL',3,2,4),('CE302','CIVIL Subject 2','CIVIL',3,2,4),('CE303','CIVIL Lab 1','CIVIL',2,2,4),
('MB301','MBA Subject 1','MBA',3,2,4),('MB302','MBA Subject 2','MBA',3,2,4),('MB303','MBA Seminar','MBA',2,2,4),
('MC301','MCA Subject 1','MCA',3,2,4),('MC302','MCA Subject 2','MCA',3,2,4),('MC303','MCA Lab 1','MCA',2,2,4),
('CB301','CSBS Subject 1','CSBS',3,2,4),('CB302','CSBS Subject 2','CSBS',3,2,4),('CB303','CSBS Lab 1','CSBS',2,2,4),
('CY301','CSY Subject 1','CSY',3,2,4),('CY302','CSY Subject 2','CSY',3,2,4),('CY303','CSY Lab 1','CSY',2,2,4);

-- Select subjects for each cohort (3 per department)
DELETE FROM cohort_courses WHERE cohort_id IN (@it_cohort_id,@cse_cohort_id,@ece_cohort_id,@eee_cohort_id,@mech_cohort_id,@civil_cohort_id,@mba_cohort_id,@mca_cohort_id,@csbs_cohort_id,@csy_cohort_id);

INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @it_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='IT' AND course_code IN ('IT301','IT302','IT303');
INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @cse_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='CSE' AND course_code IN ('CS301','CS302','CS303');
INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @ece_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='ECE' AND course_code IN ('EC301','EC302','EC303');
INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @eee_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='EEE' AND course_code IN ('EE301','EE302','EE303');
INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @mech_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='MECH' AND course_code IN ('ME301','ME302','ME303');
INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @civil_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='CIVIL' AND course_code IN ('CE301','CE302','CE303');
INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @mba_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='MBA' AND course_code IN ('MB301','MB302','MB303');
INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @mca_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='MCA' AND course_code IN ('MC301','MC302','MC303');
INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @csbs_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='CSBS' AND course_code IN ('CB301','CB302','CB303');
INSERT INTO cohort_courses(cohort_id, course_id)
SELECT @csy_cohort_id, course_id FROM courses WHERE year_level=2 AND semester_no=4 AND department='CSY' AND course_code IN ('CY301','CY302','CY303');

-- Assign faculty to each subject.
-- Note: We prefer faculty of the same department; if none exist, fallback to ANY faculty.
DELETE FROM cohort_faculty WHERE cohort_id IN (@it_cohort_id,@cse_cohort_id,@ece_cohort_id,@eee_cohort_id,@mech_cohort_id,@civil_cohort_id,@mba_cohort_id,@mca_cohort_id,@csbs_cohort_id,@csy_cohort_id);

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @it_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='IT' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('IT301','IT302','IT303');

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @cse_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='CSE' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('CS301','CS302','CS303');

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @ece_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='ECE' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('EC301','EC302','EC303');

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @eee_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='EEE' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('EE301','EE302','EE303');

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @mech_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='MECH' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('ME301','ME302','ME303');

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @civil_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='CIVIL' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('CE301','CE302','CE303');

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @mba_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='MBA' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('MB301','MB302','MB303');

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @mca_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='MCA' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('MC301','MC302','MC303');

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @csbs_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='CSBS' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('CB301','CB302','CB303');

INSERT INTO cohort_faculty(cohort_id, course_id, faculty_id)
SELECT @csy_cohort_id, c.course_id, COALESCE((SELECT faculty_id FROM faculty WHERE department='CSY' ORDER BY faculty_id LIMIT 1),(SELECT faculty_id FROM faculty ORDER BY faculty_id LIMIT 1))
FROM courses c WHERE c.course_code IN ('CY301','CY302','CY303');

-- -----------------------------------------------------------------------------
-- Rooms (starter set)
-- -----------------------------------------------------------------------------
INSERT INTO rooms(room_code, building, capacity, room_type, active) VALUES
('B1-101','Block 1',60,'Classroom',1),
('B1-102','Block 1',60,'Classroom',1),
('B2-LAB1','Block 2',40,'Lab',1),
('B2-LAB2','Block 2',40,'Lab',1),
('AUD-01','Main',300,'Auditorium',1)
ON DUPLICATE KEY UPDATE
building=VALUES(building), capacity=VALUES(capacity), room_type=VALUES(room_type), active=VALUES(active);

-- -----------------------------------------------------------------------------
-- Class offerings + faculty assignments (for IT + CSE cohorts, section A)
-- This gives the UI a concrete timetable to display even before auto-generation.
-- -----------------------------------------------------------------------------
SET @term = '2025-26 Even';
SET @batch = 2024;

-- IT offerings
INSERT IGNORE INTO class_offerings(course_id, cohort_id, section, term, batch_year, expected_strength)
SELECT c.course_id, @it_cohort_id, 'A', @term, @batch, 60
FROM courses c
WHERE c.course_code IN ('IT301','IT302','IT303');

-- CSE offerings
INSERT IGNORE INTO class_offerings(course_id, cohort_id, section, term, batch_year, expected_strength)
SELECT c.course_id, @cse_cohort_id, 'A', @term, @batch, 63
FROM courses c
WHERE c.course_code IN ('CS301','CS302','CS303');

-- Assign faculty based on cohort_faculty mapping
INSERT IGNORE INTO faculty_assignments(class_id, faculty_id, role)
SELECT co.class_id, cf.faculty_id, 'Primary'
FROM class_offerings co
JOIN courses c ON c.course_id = co.course_id
JOIN cohort_faculty cf ON cf.cohort_id = co.cohort_id AND cf.course_id = co.course_id
WHERE co.term = @term AND co.batch_year = @batch AND co.section = 'A'
	AND c.course_code IN ('IT301','IT302','IT303','CS301','CS302','CS303');

-- -----------------------------------------------------------------------------
-- Starter timetable slots (conflict-free)
-- day_of_week: 1=Mon ... 5=Fri
-- Uses different rooms/times to avoid overlap triggers.
-- -----------------------------------------------------------------------------
SET @room_101 = (SELECT room_id FROM rooms WHERE room_code='B1-101' LIMIT 1);
SET @room_102 = (SELECT room_id FROM rooms WHERE room_code='B1-102' LIMIT 1);
SET @room_lab1 = (SELECT room_id FROM rooms WHERE room_code='B2-LAB1' LIMIT 1);

-- IT301 (Mon/Wed)
SET @it301_class = (SELECT co.class_id FROM class_offerings co JOIN courses c ON c.course_id=co.course_id WHERE co.cohort_id=@it_cohort_id AND c.course_code='IT301' AND co.term=@term AND co.batch_year=@batch LIMIT 1);
SET @it301_fac = (SELECT cf.faculty_id FROM cohort_faculty cf JOIN courses c ON c.course_id=cf.course_id WHERE cf.cohort_id=@it_cohort_id AND c.course_code='IT301' LIMIT 1);
INSERT IGNORE INTO timetable_slots(class_id, faculty_id, room_id, day_of_week, start_time, end_time)
VALUES
(@it301_class, @it301_fac, @room_101, 1, '09:00:00', '10:00:00'),
(@it301_class, @it301_fac, @room_101, 3, '09:00:00', '10:00:00');

-- IT302 (Tue/Thu)
SET @it302_class = (SELECT co.class_id FROM class_offerings co JOIN courses c ON c.course_id=co.course_id WHERE co.cohort_id=@it_cohort_id AND c.course_code='IT302' AND co.term=@term AND co.batch_year=@batch LIMIT 1);
SET @it302_fac = (SELECT cf.faculty_id FROM cohort_faculty cf JOIN courses c ON c.course_id=cf.course_id WHERE cf.cohort_id=@it_cohort_id AND c.course_code='IT302' LIMIT 1);
INSERT IGNORE INTO timetable_slots(class_id, faculty_id, room_id, day_of_week, start_time, end_time)
VALUES
(@it302_class, @it302_fac, @room_102, 2, '10:00:00', '11:00:00'),
(@it302_class, @it302_fac, @room_102, 4, '10:00:00', '11:00:00');

-- IT303 Lab (Fri)
SET @it303_class = (SELECT co.class_id FROM class_offerings co JOIN courses c ON c.course_id=co.course_id WHERE co.cohort_id=@it_cohort_id AND c.course_code='IT303' AND co.term=@term AND co.batch_year=@batch LIMIT 1);
SET @it303_fac = (SELECT cf.faculty_id FROM cohort_faculty cf JOIN courses c ON c.course_id=cf.course_id WHERE cf.cohort_id=@it_cohort_id AND c.course_code='IT303' LIMIT 1);
INSERT IGNORE INTO timetable_slots(class_id, faculty_id, room_id, day_of_week, start_time, end_time)
VALUES
(@it303_class, @it303_fac, @room_lab1, 5, '11:00:00', '13:00:00');

-- CS301 (Mon/Wed)
SET @cs301_class = (SELECT co.class_id FROM class_offerings co JOIN courses c ON c.course_id=co.course_id WHERE co.cohort_id=@cse_cohort_id AND c.course_code='CS301' AND co.term=@term AND co.batch_year=@batch LIMIT 1);
SET @cs301_fac = (SELECT cf.faculty_id FROM cohort_faculty cf JOIN courses c ON c.course_id=cf.course_id WHERE cf.cohort_id=@cse_cohort_id AND c.course_code='CS301' LIMIT 1);
INSERT IGNORE INTO timetable_slots(class_id, faculty_id, room_id, day_of_week, start_time, end_time)
VALUES
(@cs301_class, @cs301_fac, @room_102, 1, '09:00:00', '10:00:00'),
(@cs301_class, @cs301_fac, @room_102, 3, '09:00:00', '10:00:00');

-- CS302 (Tue/Thu)
SET @cs302_class = (SELECT co.class_id FROM class_offerings co JOIN courses c ON c.course_id=co.course_id WHERE co.cohort_id=@cse_cohort_id AND c.course_code='CS302' AND co.term=@term AND co.batch_year=@batch LIMIT 1);
SET @cs302_fac = (SELECT cf.faculty_id FROM cohort_faculty cf JOIN courses c ON c.course_id=cf.course_id WHERE cf.cohort_id=@cse_cohort_id AND c.course_code='CS302' LIMIT 1);
INSERT IGNORE INTO timetable_slots(class_id, faculty_id, room_id, day_of_week, start_time, end_time)
VALUES
(@cs302_class, @cs302_fac, @room_101, 2, '10:00:00', '11:00:00'),
(@cs302_class, @cs302_fac, @room_101, 4, '10:00:00', '11:00:00');

-- CS303 Lab (Fri)
SET @cs303_class = (SELECT co.class_id FROM class_offerings co JOIN courses c ON c.course_id=co.course_id WHERE co.cohort_id=@cse_cohort_id AND c.course_code='CS303' AND co.term=@term AND co.batch_year=@batch LIMIT 1);
SET @cs303_fac = (SELECT cf.faculty_id FROM cohort_faculty cf JOIN courses c ON c.course_id=cf.course_id WHERE cf.cohort_id=@cse_cohort_id AND c.course_code='CS303' LIMIT 1);
INSERT IGNORE INTO timetable_slots(class_id, faculty_id, room_id, day_of_week, start_time, end_time)
VALUES
(@cs303_class, @cs303_fac, @room_lab1, 5, '14:00:00', '16:00:00');

COMMIT;


