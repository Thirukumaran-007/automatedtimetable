-- Optional sample seed data for CTMS
-- 30 Faculty + 45 Courses across departments:
-- IT, CSE, ECE, EEE, MECH, CIVIL, MBA, MCA, CSBS, CSY
--
-- Hosted usage:
--   1) Import schema first: sql/timetable_schema.sql
--   2) Select your target DB (often `railway`): USE railway;
--   3) Then run this file.

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

