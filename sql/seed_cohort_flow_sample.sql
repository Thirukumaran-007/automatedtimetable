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
