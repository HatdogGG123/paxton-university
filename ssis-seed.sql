-- Every account uses the password Paxton2026

USE ssis_db;
-- Empty the tables first so this file can be run again
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE number_sequences; TRUNCATE TABLE settings;
TRUNCATE TABLE login_attempts;
TRUNCATE TABLE auth_attempt_counters;
TRUNCATE TABLE admission_documents; TRUNCATE TABLE admission_document_requirements; TRUNCATE TABLE admission_applications;
TRUNCATE TABLE audit_logs; TRUNCATE TABLE announcements;
TRUNCATE TABLE notifications; TRUNCATE TABLE payments;
TRUNCATE TABLE document_requests; TRUNCATE TABLE document_types;
TRUNCATE TABLE clearance_items; TRUNCATE TABLE clearances;
TRUNCATE TABLE clearance_requirements; TRUNCATE TABLE grade_revisions;
TRUNCATE TABLE grades; TRUNCATE TABLE enrollment_subjects;
TRUNCATE TABLE enrollments; TRUNCATE TABLE subject_prerequisites;
TRUNCATE TABLE subjects; TRUNCATE TABLE students;
TRUNCATE TABLE users; TRUNCATE TABLE sections;
TRUNCATE TABLE terms; TRUNCATE TABLE programs;
TRUNCATE TABLE departments;
SET FOREIGN_KEY_CHECKS = 1;

-- Run only when hosting it live
SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE `admission_applications` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `admission_documents` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `admission_document_requirements` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `announcements` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `audit_logs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `auth_attempt_counters` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `clearances` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `clearance_items` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `clearance_requirements` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `departments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `document_requests` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `document_types` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `enrollments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `enrollment_subjects` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `grades` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `grade_revisions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `login_attempts` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `notifications` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `number_sequences` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `payments` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `programs` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `sections` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `settings` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `students` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `subjects` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `subject_prerequisites` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `terms` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
SET FOREIGN_KEY_CHECKS = 1;

-- Departments
INSERT INTO departments (id, code, name, dept_type) VALUES
  (1, 'CCS', 'College of Computer Studies', 'Academic'),
  (2, 'CBA', 'College of Business and Accountancy', 'Academic'),
  (3, 'LIB', 'University Library', 'Administrative'),
  (4, 'OSA', 'Office of Student Affairs', 'Administrative');

-- Programs
INSERT INTO programs (id, department_id, code, name) VALUES
  (1, 1, 'BSCS', 'BS Computer Science'),
  (2, 1, 'BSIT', 'BS Information Technology'),
  (3, 1, 'BSIS', 'BS Information Systems'),
  (4, 2, 'BSBA', 'BS Business Administration'),
  (5, 2, 'BSA', 'BS Accountancy');

-- Sections
INSERT INTO sections (id, program_id, year_level, code) VALUES
  (1, 1, 1, '1A'),
  (2, 1, 1, '1B'),
  (3, 1, 2, '2A'),
  (4, 1, 2, '2B'),
  (5, 1, 3, '3A'),
  (6, 1, 3, '3B'),
  (7, 1, 4, '4A'),
  (8, 1, 4, '4B'),
  (9, 2, 1, '1A'),
  (10, 2, 1, '1B'),
  (11, 2, 2, '2A'),
  (12, 2, 2, '2B'),
  (13, 2, 3, '3A'),
  (14, 2, 3, '3B'),
  (15, 2, 4, '4A'),
  (16, 2, 4, '4B'),
  (17, 3, 1, '1A'),
  (18, 3, 1, '1B'),
  (19, 3, 2, '2A'),
  (20, 3, 2, '2B'),
  (21, 3, 3, '3A'),
  (22, 3, 3, '3B'),
  (23, 3, 4, '4A'),
  (24, 3, 4, '4B'),
  (25, 4, 1, '1A'),
  (26, 4, 1, '1B'),
  (27, 4, 2, '2A'),
  (28, 4, 2, '2B'),
  (29, 4, 3, '3A'),
  (30, 4, 3, '3B'),
  (31, 4, 4, '4A'),
  (32, 4, 4, '4B'),
  (33, 5, 1, '1A'),
  (34, 5, 1, '1B'),
  (35, 5, 2, '2A'),
  (36, 5, 2, '2B'),
  (37, 5, 3, '3A'),
  (38, 5, 3, '3B'),
  (39, 5, 4, '4A'),
  (40, 5, 4, '4B');

-- Terms. Term 5 is the current one
INSERT INTO terms (id, academic_year, semester, is_current) VALUES
  (1, '2024-2025', '1st Semester', 0),
  (2, '2024-2025', '2nd Semester', 0),
  (3, '2025-2026', '1st Semester', 0),
  (4, '2025-2026', '2nd Semester', 0),
  (5, '2026-2027', '1st Semester', 1),
  (6, '2026-2027', '2nd Semester', 0);

-- Subjects
INSERT INTO subjects (id, program_id, code, name, units, year_level, semester) VALUES
  (1, 1, 'GE101', 'Understanding the Self', 3.0, 1, '1st Semester'),
  (2, 1, 'CC101', 'Introduction to Computing', 3.0, 1, '1st Semester'),
  (3, 1, 'CC102', 'Computer Programming 1', 3.0, 1, '1st Semester'),
  (4, 1, 'MATH101', 'Calculus I', 3.0, 1, '1st Semester'),
  (5, 1, 'PE101', 'Physical Education 1', 2.0, 1, '1st Semester'),
  (6, 1, 'GE102', 'Readings in Philippine History', 3.0, 1, '2nd Semester'),
  (7, 1, 'CC103', 'Computer Programming 2', 3.0, 1, '2nd Semester'),
  (8, 1, 'CC104', 'Data Structures and Algorithms', 3.0, 1, '2nd Semester'),
  (9, 1, 'MATH102', 'Discrete Mathematics', 3.0, 1, '2nd Semester'),
  (10, 1, 'PE102', 'Physical Education 2', 2.0, 1, '2nd Semester'),
  (11, 1, 'CC105', 'Information Management', 3.0, 2, '1st Semester'),
  (12, 1, 'CS201', 'Object-Oriented Programming', 3.0, 2, '1st Semester'),
  (13, 1, 'CS202', 'Computer Organization', 3.0, 2, '1st Semester'),
  (14, 1, 'MATH201', 'Linear Algebra', 3.0, 2, '1st Semester'),
  (15, 1, 'GE103', 'Mathematics in the Modern World', 3.0, 2, '1st Semester'),
  (16, 1, 'CC107', 'Web Systems and Technologies', 3.0, 2, '2nd Semester'),
  (17, 1, 'CS203', 'Algorithms and Complexity', 3.0, 2, '2nd Semester'),
  (18, 1, 'CS204', 'Networks and Communications', 3.0, 2, '2nd Semester'),
  (19, 1, 'MATH202', 'Statistics and Probability', 3.0, 2, '2nd Semester'),
  (20, 1, 'GE104', 'Purposive Communication', 3.0, 2, '2nd Semester'),
  (21, 1, 'CCS109', 'System Analysis & Design', 3.0, 3, '1st Semester'),
  (22, 1, 'CC106', 'Applications Development & Emerging Tech', 3.0, 3, '1st Semester'),
  (23, 1, 'CS301', 'Operating Systems', 3.0, 3, '1st Semester'),
  (24, 1, 'CS302', 'Automata Theory & Formal Languages', 3.0, 3, '1st Semester'),
  (25, 1, 'GE108', 'Ethics', 3.0, 3, '1st Semester'),
  (26, 1, 'CS303', 'Software Engineering', 3.0, 3, '2nd Semester'),
  (27, 1, 'CS304', 'Programming Languages', 3.0, 3, '2nd Semester'),
  (28, 1, 'CS305', 'Information Assurance and Security', 3.0, 3, '2nd Semester'),
  (29, 1, 'CS306', 'Human-Computer Interaction', 3.0, 3, '2nd Semester'),
  (30, 1, 'GE105', 'Art Appreciation', 3.0, 3, '2nd Semester'),
  (31, 1, 'CS401', 'Thesis 1', 3.0, 4, '1st Semester'),
  (32, 1, 'CS402', 'Artificial Intelligence', 3.0, 4, '1st Semester'),
  (33, 1, 'CS403', 'Parallel and Distributed Computing', 3.0, 4, '1st Semester'),
  (34, 1, 'CS404', 'Social Issues and Professional Practice', 3.0, 4, '1st Semester'),
  (35, 1, 'CS405', 'Thesis 2', 3.0, 4, '2nd Semester'),
  (36, 1, 'CS499', 'Practicum', 6.0, 4, '2nd Semester'),
  (37, 2, 'GE101', 'Understanding the Self', 3.0, 1, '1st Semester'),
  (38, 2, 'IT101', 'Introduction to Computing', 3.0, 1, '1st Semester'),
  (39, 2, 'IT102', 'Computer Programming 1', 3.0, 1, '1st Semester'),
  (40, 2, 'MATH111', 'College Algebra', 3.0, 1, '1st Semester'),
  (41, 2, 'PE101', 'Physical Education 1', 2.0, 1, '1st Semester'),
  (42, 2, 'GE102', 'Readings in Philippine History', 3.0, 1, '2nd Semester'),
  (43, 2, 'IT103', 'Computer Programming 2', 3.0, 1, '2nd Semester'),
  (44, 2, 'IT104', 'Discrete Mathematics', 3.0, 1, '2nd Semester'),
  (45, 2, 'IT105', 'Networking Fundamentals', 3.0, 1, '2nd Semester'),
  (46, 2, 'PE102', 'Physical Education 2', 2.0, 1, '2nd Semester'),
  (47, 2, 'IT201', 'Data Structures and Algorithms', 3.0, 2, '1st Semester'),
  (48, 2, 'IT202', 'Database Management Systems', 3.0, 2, '1st Semester'),
  (49, 2, 'IT203', 'Web Development', 3.0, 2, '1st Semester'),
  (50, 2, 'IT204', 'Web Architecture', 3.0, 2, '1st Semester'),
  (51, 2, 'GE103', 'Mathematics in the Modern World', 3.0, 2, '1st Semester'),
  (52, 2, 'IT301', 'Systems Integration and Architecture', 3.0, 3, '1st Semester'),
  (53, 2, 'IT302', 'Information Assurance and Security', 3.0, 3, '1st Semester'),
  (54, 2, 'IT303', 'Mobile Application Development', 3.0, 3, '1st Semester'),
  (55, 2, 'GE108', 'Ethics', 3.0, 3, '1st Semester'),
  (56, 3, 'GE101', 'Understanding the Self', 3.0, 1, '1st Semester'),
  (57, 3, 'IS101', 'Introduction to Information Systems', 3.0, 1, '1st Semester'),
  (58, 3, 'IS102', 'Computer Programming 1', 3.0, 1, '1st Semester'),
  (59, 3, 'PE101', 'Physical Education 1', 2.0, 1, '1st Semester'),
  (60, 3, 'IS201', 'Business Process Management', 3.0, 2, '1st Semester'),
  (61, 3, 'IS202', 'Database Systems', 3.0, 2, '1st Semester'),
  (62, 3, 'IS203', 'Systems Analysis and Design', 3.0, 2, '1st Semester'),
  (63, 3, 'GE103', 'Mathematics in the Modern World', 3.0, 2, '1st Semester'),
  (64, 4, 'GE101', 'Understanding the Self', 3.0, 1, '1st Semester'),
  (65, 4, 'BA101', 'Principles of Management', 3.0, 1, '1st Semester'),
  (66, 4, 'BA102', 'Basic Microeconomics', 3.0, 1, '1st Semester'),
  (67, 4, 'MATH111', 'Business Mathematics', 3.0, 1, '1st Semester'),
  (68, 4, 'PE101', 'Physical Education 1', 2.0, 1, '1st Semester'),
  (69, 4, 'BA401', 'Strategic Management', 3.0, 4, '1st Semester'),
  (70, 4, 'BA402', 'Business Research', 3.0, 4, '1st Semester'),
  (71, 4, 'BA403', 'International Business', 3.0, 4, '1st Semester'),
  (72, 4, 'GE108', 'Ethics', 3.0, 4, '1st Semester'),
  (73, 5, 'GE101', 'Understanding the Self', 3.0, 1, '1st Semester'),
  (74, 5, 'ACC101', 'Financial Accounting and Reporting', 3.0, 1, '1st Semester'),
  (75, 5, 'BA102', 'Basic Microeconomics', 3.0, 1, '1st Semester'),
  (76, 5, 'MATH111', 'Business Mathematics', 3.0, 1, '1st Semester'),
  (77, 5, 'PE101', 'Physical Education 1', 2.0, 1, '1st Semester'),
  (78, 5, 'ACC301', 'Intermediate Accounting 1', 3.0, 3, '1st Semester'),
  (79, 5, 'ACC302', 'Auditing Theory', 3.0, 3, '1st Semester'),
  (80, 5, 'ACC303', 'Taxation', 3.0, 3, '1st Semester'),
  (81, 5, 'GE108', 'Ethics', 3.0, 3, '1st Semester');

-- Prerequisites
INSERT INTO subject_prerequisites (subject_id, prerequisite_id) VALUES
  (21, 3),
  (22, 11),
  (23, 8),
  (24, 9),
  (7, 3),
  (8, 7),
  (12, 7),
  (32, 24),
  (35, 31),
  (43, 39),
  (47, 43),
  (52, 47),
  (62, 58);

-- Users. Ids 1 to 8 are staff and 9 to 19 are students
INSERT INTO users (id, username, password_hash, role, department_id, full_name, status, must_change_password, password_changed_at, last_login) VALUES
  (1, 'admin', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Admin', NULL, 'System Administrator', 'Active', 0, '2026-08-15 08:00:00', NULL),
  (2, 'registrar1', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Registrar', NULL, 'Rosario Dela Cruz', 'Active', 0, '2026-08-15 08:00:00', NULL),
  (3, 'registrar2', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Registrar', NULL, 'Marco Villanueva', 'Active', 0, '2026-08-15 08:00:00', NULL),
  (4, 'cashier1', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Cashier', NULL, 'Elena Mercado', 'Active', 0, '2026-08-15 08:00:00', NULL),
  (5, 'dept.ccs', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Department', 1, 'Dr. Ramon Aquino', 'Active', 0, '2026-08-15 08:00:00', NULL),
  (6, 'dept.cba', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Department', 2, 'Prof. Liza Fernandez', 'Active', 0, '2026-08-15 08:00:00', NULL),
  (7, 'dept.library', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Department', 3, 'Gloria Santiago', 'Active', 0, '2026-08-15 08:00:00', NULL),
  (8, 'dept.osa', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Department', 4, 'Henry Castillo', 'Active', 0, '2026-08-15 08:00:00', NULL),
  (9, '2026-00001', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 0, '2026-08-15 08:00:00', NULL),
  (10, '2026-00002', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 0, '2026-08-15 08:00:00', NULL),
  (11, '2026-00003', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 0, '2026-08-15 08:00:00', NULL),
  (12, '2026-00004', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 0, '2026-08-15 08:00:00', NULL),
  (13, '2026-00005', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 0, '2026-08-15 08:00:00', NULL),
  (14, '2026-00006', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 0, '2026-08-15 08:00:00', NULL),
  (15, '2026-00007', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 0, '2026-08-15 08:00:00', NULL),
  (16, '2026-00008', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 0, '2026-08-15 08:00:00', NULL),
  (17, '2026-00009', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 0, '2026-08-15 08:00:00', NULL),
  (18, '2026-00010', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Inactive', 0, '2026-08-15 08:00:00', NULL),
  (19, '2026-00011', '$2y$10$8Xijm0MjXWAtj.zlBwojNerIcLu.fH0CwvpIJyZxIh91uKEVVkxNW', 'Student', NULL, NULL, 'Active', 1, NULL, NULL);

UPDATE users SET last_login = '2026-10-03 08:12:00' WHERE id IN (1,2,4,9);
UPDATE users SET last_login = '2026-10-02 15:40:00' WHERE id IN (3,5,10,16);

-- Students
INSERT INTO students (id, user_id, student_no, first_name, middle_name, last_name, email, contact_number, address, program_id, year_level, student_type, admission_term_id, curriculum_version, guardian_name, guardian_relationship, guardian_contact) VALUES
  (1, 9, '2026-00001', 'John', 'Alonzo', 'Doe', 'johndoe@paxton.edu.ph', '0917-555-0101', '123 University Avenue, Ermita, Manila', 1, 3, 'Regular', 1, '2024-BSCS-V1', 'Mary Doe', 'Mother', '0917-555-0201'),
  (2, 10, '2026-00002', 'Jane', 'Marie', 'Smith', 'janesmith@paxton.edu.ph', '0917-555-0102', '45 Mabini Street, Paco, Manila', 2, 2, 'Regular', 1, '2024-BSIT-V1', 'Robert Smith', 'Father', '0917-555-0202'),
  (3, 11, '2026-00003', 'Alex', 'Torres', 'Mercer', 'alexmercer@paxton.edu.ph', '0917-555-0103', '12 Rizal Avenue, Sta. Cruz, Manila', 3, 2, 'Regular', 3, '2024-BSIS-V1', 'Linda Mercer', 'Mother', '0917-555-0203'),
  (4, 12, '2026-00004', 'Maria', 'Isabel', 'Santos', 'mariasantos@paxton.edu.ph', '0917-555-0104', '88 Taft Avenue, Malate, Manila', 1, 1, 'Regular', 5, '2024-BSCS-V1', 'Jose Santos', 'Father', '0917-555-0204'),
  (5, 13, '2026-00005', 'Carlo', 'Miguel', 'Reyes', 'carloreyes@paxton.edu.ph', '0917-555-0105', '7 Quezon Boulevard, Quiapo, Manila', 2, 1, 'Regular', 5, '2024-BSIT-V1', 'Ana Reyes', 'Mother', '0917-555-0205'),
  (6, 14, '2026-00006', 'Angela', 'Dizon', 'Cruz', 'angelacruz@paxton.edu.ph', '0917-555-0106', '301 Espana Boulevard, Sampaloc, Manila', 4, 4, 'Regular', 1, '2024-BSBA-V1', 'Pedro Cruz', 'Father', '0917-555-0206'),
  (7, 15, '2026-00007', 'Miguel', 'Santos', 'Tan', 'migueltan@paxton.edu.ph', '0917-555-0107', '56 Binondo Street, Binondo, Manila', 5, 3, 'Regular', 1, '2024-BSA-V1', 'Grace Tan', 'Mother', '0917-555-0207'),
  (8, 16, '2026-00008', 'Sofia', 'Reyes', 'Lim', 'sofialim@paxton.edu.ph', '0917-555-0108', '19 Pasay Road, Pasay City', 1, 4, 'Irregular', 1, '2024-BSCS-V1', 'Daniel Lim', 'Father', '0917-555-0208'),
  (9, 17, '2026-00009', 'Ethan', 'Cruz', 'Bautista', 'ethanbautista@paxton.edu.ph', '0917-555-0109', '234 Aurora Boulevard, Cubao, Quezon City', 2, 3, 'Regular', 3, '2024-BSIT-V1', 'Carmen Bautista', 'Mother', '0917-555-0209'),
  (10, 18, '2026-00010', 'Paolo', 'Garcia', 'Ramos', 'paoloramos@paxton.edu.ph', '0917-555-0110', '78 Shaw Boulevard, Mandaluyong City', 1, 2, 'Regular', 3, '2024-BSCS-V1', 'Rene Ramos', 'Father', '0917-555-0210'),
  (11, 19, '2026-00011', 'Isabel', 'Lopez', 'Navarro', 'isabelnavarro@paxton.edu.ph', '0917-555-0111', '15 Roxas Boulevard, Ermita, Manila', 4, 1, 'Regular', 5, '2024-BSBA-V1', 'Teresa Navarro', 'Mother', '0917-555-0211');

UPDATE users u JOIN students s ON s.user_id = u.id SET u.email = s.email;
UPDATE users SET email = CASE id
  WHEN 1 THEN 'admin@paxton.edu.ph'
  WHEN 2 THEN 'rosario.delacruz@paxton.edu.ph'
  WHEN 3 THEN 'marco.villanueva@paxton.edu.ph'
  WHEN 4 THEN 'elena.mercado@paxton.edu.ph'
  WHEN 5 THEN 'ramon.aquino@paxton.edu.ph'
  WHEN 6 THEN 'liza.fernandez@paxton.edu.ph'
  WHEN 7 THEN 'gloria.santiago@paxton.edu.ph'
  WHEN 8 THEN 'henry.castillo@paxton.edu.ph'
END WHERE id BETWEEN 1 AND 8;

-- Default admission document checklist for each program. Admin can adjust these requirements.
INSERT INTO admission_document_requirements (program_id, document_name, created_by) VALUES
  (1, 'PSA Birth Certificate', 1),
  (1, 'Form 138 / Transcript of Records', 1),
  (1, 'Good Moral Certificate', 1),
  (1, '2x2 ID Photo', 1),
  (2, 'PSA Birth Certificate', 1),
  (2, 'Form 138 / Transcript of Records', 1),
  (2, 'Good Moral Certificate', 1),
  (2, '2x2 ID Photo', 1),
  (3, 'PSA Birth Certificate', 1),
  (3, 'Form 138 / Transcript of Records', 1),
  (3, 'Good Moral Certificate', 1),
  (3, '2x2 ID Photo', 1),
  (4, 'PSA Birth Certificate', 1),
  (4, 'Form 138 / Transcript of Records', 1),
  (4, 'Good Moral Certificate', 1),
  (4, '2x2 ID Photo', 1),
  (5, 'PSA Birth Certificate', 1),
  (5, 'Form 138 / Transcript of Records', 1),
  (5, 'Good Moral Certificate', 1),
  (5, '2x2 ID Photo', 1);

-- Enrollments. Ids 1 to 4 are old terms and the rest are the current term
INSERT INTO enrollments (id, enrollment_no, student_id, term_id, section_id, year_level, status, rejection_reason, submitted_at, reviewed_by, reviewed_at, enrolled_at) VALUES
  (1, 'ENR-2025-0001', 1, 3, NULL, 2, 'Enrolled', NULL, '2025-08-05 09:00:00', 2, '2025-08-06 10:00:00', '2025-08-07 11:00:00'),
  (2, 'ENR-2025-0002', 1, 4, NULL, 2, 'Enrolled', NULL, '2026-01-05 09:00:00', 2, '2026-01-06 10:00:00', '2026-01-07 11:00:00'),
  (3, 'ENR-2025-0003', 8, 3, NULL, 3, 'Enrolled', NULL, '2025-08-05 09:30:00', 2, '2025-08-06 10:30:00', '2025-08-07 11:30:00'),
  (4, 'ENR-2025-0004', 8, 4, NULL, 3, 'Enrolled', NULL, '2026-01-05 09:30:00', 2, '2026-01-06 10:30:00', '2026-01-07 11:30:00'),
  (5, 'ENR-2026-0001', 1, 5, 5, 3, 'Enrolled', NULL, '2026-08-10 09:00:00', 2, '2026-08-11 10:00:00', '2026-08-12 11:00:00'),
  (6, 'ENR-2026-0002', 2, 5, 12, 2, 'Enrolled', NULL, '2026-08-10 09:20:00', 2, '2026-08-11 10:20:00', '2026-08-12 11:20:00'),
  (7, 'ENR-2026-0003', 3, 5, NULL, 2, 'Pending', NULL, '2026-10-02 10:15:00', NULL, NULL, NULL),
  (8, 'ENR-2026-0004', 4, 5, NULL, 1, 'Under Review', NULL, '2026-10-01 14:00:00', 2, '2026-10-02 09:00:00', NULL),
  (9, 'ENR-2026-0005', 5, 5, NULL, 1, 'Approved', NULL, '2026-09-30 13:00:00', 3, '2026-10-02 11:00:00', NULL),
  (10, 'ENR-2026-0006', 6, 5, NULL, 4, 'Rejected', 'Selected subjects do not match your 4th year curriculum. Please re-select subjects and submit a new request.', '2026-09-29 10:00:00', 2, '2026-10-01 15:30:00', NULL),
  (11, 'ENR-2026-0007', 8, 5, NULL, 4, 'Enrolled', NULL, '2026-08-10 10:00:00', 3, '2026-08-11 11:00:00', '2026-08-12 12:00:00'),
  (12, 'ENR-2026-0008', 9, 5, NULL, 3, 'Cancelled', NULL, '2026-09-25 09:00:00', NULL, NULL, NULL),
  (13, 'ENR-2026-0009', 9, 5, NULL, 3, 'Pending', NULL, '2026-09-26 09:30:00', NULL, NULL, NULL);

-- Enrollment subjects
INSERT INTO enrollment_subjects (enrollment_id, subject_id) VALUES
  (1, 11),
  (1, 12),
  (1, 13),
  (1, 14),
  (1, 15),
  (2, 16),
  (2, 17),
  (2, 18),
  (2, 19),
  (2, 20),
  (3, 21),
  (3, 22),
  (3, 23),
  (3, 24),
  (3, 25),
  (4, 26),
  (4, 27),
  (4, 28),
  (4, 29),
  (4, 30),
  (5, 21),
  (5, 22),
  (5, 23),
  (6, 47),
  (6, 48),
  (6, 50),
  (7, 60),
  (7, 61),
  (7, 62),
  (8, 2),
  (8, 3),
  (8, 4),
  (8, 1),
  (8, 5),
  (9, 38),
  (9, 39),
  (9, 40),
  (9, 37),
  (9, 41),
  (10, 69),
  (10, 70),
  (11, 31),
  (11, 32),
  (11, 33),
  (11, 34),
  (12, 52),
  (12, 53),
  (13, 52),
  (13, 53),
  (13, 54),
  (13, 55);

-- Released grades are visible to students. Pending grades stay hidden until Registrar approval.
INSERT INTO grades (student_id, subject_id, term_id, grade, grade_status, status, entered_by, released_by, released_at) VALUES
  (1, 11, 3, 1.75, 'Passed', 'Released', 2, 2, '2026-01-03 09:00:00'),
  (1, 12, 3, 1.5, 'Passed', 'Released', 2, 2, '2026-01-03 09:00:00'),
  (1, 13, 3, 2.0, 'Passed', 'Released', 2, 2, '2026-01-03 09:00:00'),
  (1, 14, 3, 1.75, 'Passed', 'Released', 2, 2, '2026-01-03 09:00:00'),
  (1, 15, 3, 1.25, 'Passed', 'Released', 2, 2, '2026-01-03 09:00:00'),
  (1, 16, 4, 1.5, 'Passed', 'Released', 2, 2, '2026-06-02 09:00:00'),
  (1, 17, 4, 1.75, 'Passed', 'Released', 2, 2, '2026-06-02 09:00:00'),
  (1, 18, 4, 2.25, 'Passed', 'Released', 2, 2, '2026-06-02 09:00:00'),
  (1, 19, 4, 2.5, 'Passed', 'Released', 2, 2, '2026-06-02 09:00:00'),
  (1, 20, 4, 1.5, 'Passed', 'Released', 2, 2, '2026-06-02 09:00:00'),
  (1, 21, 5, 1.25, 'Passed', 'Released', 2, 2, '2026-10-03 09:30:00'),
  (1, 22, 5, 1.5, 'Passed', 'Released', 2, 2, '2026-10-03 09:30:00'),
  (1, 23, 5, 1.5, 'Passed', 'Department Revision', 5, NULL, NULL),
  (8, 21, 3, 1.25, 'Passed', 'Released', 2, 2, '2026-01-03 10:00:00'),
  (8, 22, 3, 1.75, 'Passed', 'Released', 2, 2, '2026-01-03 10:00:00'),
  (8, 23, 3, 1.5, 'Passed', 'Released', 2, 2, '2026-01-03 10:00:00'),
  (8, 24, 3, 2.0, 'Passed', 'Released', 2, 2, '2026-01-03 10:00:00'),
  (8, 25, 3, 1.5, 'Passed', 'Released', 2, 2, '2026-01-03 10:00:00'),
  (8, 26, 4, 1.75, 'Passed', 'Released', 2, 2, '2026-06-02 10:00:00'),
  (8, 27, 4, 3.0, 'Passed', 'Released', 2, 2, '2026-06-02 10:00:00'),
  (8, 28, 4, 5.0, 'Failed', 'Released', 2, 2, '2026-06-02 10:00:00'),
  (8, 29, 4, NULL, 'Incomplete', 'Released', 2, 2, '2026-06-02 10:00:00'),
  (8, 30, 4, NULL, 'Dropped', 'Released', 2, 2, '2026-06-02 10:00:00'),
  (2, 47, 5, 2.0, 'Passed', 'Released', 2, 2, '2026-10-03 09:30:00'),
  (2, 48, 5, 1.75, 'Passed', 'Released', 2, 2, '2026-10-03 09:30:00'),
  (2, 50, 5, 2.25, 'Passed', 'Department Revision', 5, NULL, NULL);

-- One corrected grade for John
INSERT INTO grade_revisions (grade_id, old_grade, new_grade, old_grade_status, new_grade_status, reason, changed_by, changed_at)
SELECT g.id, 1.75, 1.50, 'Passed', 'Passed', 'Recomputed final project score after re-evaluation.', 2, '2026-10-03 10:05:00'
FROM grades g JOIN subjects s ON s.id = g.subject_id
WHERE g.student_id = 1 AND g.term_id = 5 AND s.code = 'CC106';

-- Clearance requirements
INSERT INTO clearance_requirements (id, name, description, office, department_id) VALUES
  (1, 'Library Clearance', 'Book returns and outstanding fines', 'Department', 3),
  (2, 'Student Affairs Clearance', 'Student exit interview and Form 102', 'Department', 4),
  (3, 'CCS Laboratory and Project Clearance', 'Lab equipment returned and projects submitted', 'Department', 1),
  (4, 'CBA Department Clearance', 'Department records and requirements settled', 'Department', 2),
  (5, 'Tuition and Fees Clearance', 'No unpaid or pending payments', 'Cashier', NULL),
  (6, 'Registrar Records Clearance', 'Complete academic records and documents', 'Registrar', NULL);

-- Clearances
INSERT INTO clearances (id, student_id, term_id, overall_status) VALUES
  (1, 1, 5, 'In Progress'),
  (2, 2, 5, 'Action Needed'),
  (3, 8, 5, 'Cleared'),
  (4, 4, 5, 'In Progress');

-- Clearance items
INSERT INTO clearance_items (clearance_id, requirement_id, status, remarks, rejection_reason, submitted_at, reviewed_by, reviewed_at) VALUES
  (1, 1, 'Approved', 'All borrowed books returned. No pending fines.', NULL, '2026-09-20 09:00:00', 7, '2026-10-02 14:15:00'),
  (1, 2, 'Pending', 'Submit hard copy of Form 102 to OSA counter.', NULL, '2026-09-20 09:00:00', NULL, NULL),
  (1, 3, 'Approved', 'Lab equipment returned.', NULL, '2026-09-20 09:00:00', 5, '2026-09-28 10:00:00'),
  (1, 5, 'Approved', 'Tuition fully settled for active term.', NULL, '2026-09-20 09:00:00', 4, '2026-09-29 11:00:00'),
  (1, 6, 'Approved', 'Records complete.', NULL, '2026-09-20 09:00:00', 2, '2026-09-30 09:00:00'),
  (2, 1, 'Approved', NULL, NULL, '2026-09-20 09:00:00', 7, '2026-09-26 10:00:00'),
  (2, 2, 'Approved', NULL, NULL, '2026-09-20 09:00:00', 8, '2026-09-26 11:00:00'),
  (2, 3, 'Approved', NULL, NULL, '2026-09-20 09:00:00', 5, '2026-09-27 10:00:00'),
  (2, 5, 'Rejected', 'Settle remaining balance at the Cashier.', 'Unpaid balance of PHP 2,500.00 for preliminary examinations.', '2026-09-20 09:00:00', 4, '2026-10-01 13:00:00'),
  (2, 6, 'Pending', NULL, NULL, '2026-09-20 09:00:00', NULL, NULL),
  (3, 1, 'Approved', NULL, NULL, '2026-09-20 09:00:00', 7, '2026-09-25 10:00:00'),
  (3, 2, 'Approved', NULL, NULL, '2026-09-20 09:00:00', 8, '2026-09-25 10:00:00'),
  (3, 3, 'Approved', NULL, NULL, '2026-09-20 09:00:00', 5, '2026-09-25 10:00:00'),
  (3, 5, 'Approved', NULL, NULL, '2026-09-20 09:00:00', 4, '2026-09-25 10:00:00'),
  (3, 6, 'Approved', NULL, NULL, '2026-09-20 09:00:00', 2, '2026-09-25 10:00:00'),
  (4, 1, 'Pending', NULL, NULL, NULL, NULL, NULL),
  (4, 2, 'Pending', NULL, NULL, NULL, NULL, NULL),
  (4, 3, 'Pending', NULL, NULL, NULL, NULL, NULL),
  (4, 5, 'Pending', NULL, NULL, NULL, NULL, NULL),
  (4, 6, 'Pending', NULL, NULL, NULL, NULL, NULL);

-- Document types. GMC is free and DCP is inactive
INSERT INTO document_types (id, code, name, description, fee, is_active) VALUES
  (1, 'COE', 'Certificate of Enrollment', 'Proof of enrollment for the current term', 150.0, 1),
  (2, 'COG', 'Certificate of Grades', 'Official list of released grades', 100.0, 1),
  (3, 'TOR', 'Transcript of Records', 'Complete academic record', 300.0, 1),
  (4, 'GMC', 'Good Moral Certificate', 'Certificate of good moral character (free)', 0.0, 1),
  (5, 'HD', 'Honorable Dismissal / Transfer Credentials', 'For students transferring to another school', 500.0, 1),
  (6, 'DCP', 'Diploma (Certified True Copy)', 'Disabled for now - used to test inactive types', 200.0, 0);

-- Document requests
INSERT INTO document_requests (id, request_no, student_id, document_type_id, purpose, copies, remarks, fee_amount, status, rejection_reason, processed_by, received_by, completed_at, created_at) VALUES
  (1, 'DR-2026-0001', 1, 1, 'Employment', 1, NULL, 150.0, 'Ready for Release', NULL, 2, NULL, NULL, '2026-10-01 09:00:00'),
  (2, 'DR-2026-0002', 2, 3, 'Further Studies / Transfer', 1, 'Needed for transfer evaluation.', 300.0, 'For Payment', NULL, 2, NULL, NULL, '2026-10-02 09:30:00'),
  (3, 'DR-2026-0003', 3, 4, 'Scholarship Application', 1, NULL, 0.0, 'Processing', NULL, 3, NULL, NULL, '2026-09-28 11:00:00'),
  (4, 'DR-2026-0004', 8, 3, 'Visa / Travel Requirements', 2, 'Two copies, sealed envelope please.', 300.0, 'For Payment', NULL, 2, NULL, NULL, '2026-10-01 10:00:00'),
  (5, 'DR-2026-0005', 8, 2, 'Employment', 1, NULL, 100.0, 'Completed', NULL, 2, 'Sofia R. Lim', '2026-09-18 14:00:00', '2026-09-15 09:00:00'),
  (6, 'DR-2026-0006', 4, 1, 'Scholarship Application', 1, NULL, 150.0, 'Pending', NULL, NULL, NULL, NULL, '2026-10-03 08:45:00'),
  (7, 'DR-2026-0007', 5, 1, 'Employment', 1, NULL, 150.0, 'Under Review', NULL, 3, NULL, NULL, '2026-10-02 16:00:00'),
  (8, 'DR-2026-0008', 6, 5, 'Further Studies / Transfer', 1, NULL, 500.0, 'Rejected', 'Unresolved Library clearance. Please settle it first.', 2, NULL, NULL, '2026-09-15 09:00:00'),
  (9, 'DR-2026-0009', 9, 2, 'Employment', 1, NULL, 100.0, 'Cancelled', NULL, 2, NULL, NULL, '2026-09-20 09:00:00'),
  (10, 'DR-2026-0010', 7, 1, 'Visa / Travel Requirements', 1, NULL, 150.0, 'For Payment', NULL, 3, NULL, NULL, '2026-10-01 15:00:00');

-- Payments
INSERT INTO payments (request_id, receipt_no, reference_number, amount, method, status, rejection_reason, refund_reason, submitted_at, recorded_by, verified_by, verified_at, refunded_by, refunded_at) VALUES
  (1, 'OR-2026-0001', 'GC-20261001-7781', 150.0, 'GCash', 'Paid', NULL, NULL, '2026-10-01 09:30:00', NULL, 4, '2026-10-01 10:15:00', NULL, NULL),
  (2, NULL, NULL, 300.0, NULL, 'Unpaid', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
  (4, NULL, '1234567890', 600.0, 'GCash', 'Pending Verification', NULL, NULL, '2026-10-02 08:00:00', NULL, NULL, NULL, NULL, NULL),
  (5, 'OR-2026-0002', NULL, 100.0, 'Cash', 'Paid', NULL, NULL, NULL, 4, 4, '2026-09-16 10:00:00', NULL, NULL),
  (9, 'OR-2026-0003', 'BDO-5589021', 100.0, 'Bank Transfer', 'Refunded', NULL, 'Student cancelled the request after payment was verified.', '2026-09-20 10:00:00', NULL, 4, '2026-09-21 09:00:00', 4, '2026-09-22 09:00:00'),
  (10, NULL, 'WRONGREF123', 150.0, 'GCash', 'Rejected', 'Reference number was not found in the GCash records. Please send the correct reference.', NULL, '2026-10-02 10:00:00', NULL, 4, '2026-10-02 13:00:00', NULL, NULL);

-- Notifications
INSERT INTO notifications (user_id, category, title, message, link, is_read, created_at) VALUES
  (9, 'Grades', 'Grade Evaluation Released', 'Official grades for 1st Semester, AY 2026-2027 have been published by the Registrar.', '/modules/student/grades.php', 0, '2026-10-03 09:30:00'),
  (9, 'Clearance', 'University Library Clearance Approved', 'The Library marked your clearance item as Approved.', '/modules/student/clearance.php', 0, '2026-10-02 14:15:00'),
  (9, 'Requests', 'Document Ready for Release', 'Request DR-2026-0001 (Certificate of Enrollment) is Ready for Release at the Registrar office.', '/modules/student/requests.php', 0, '2026-10-01 11:00:00'),
  (9, 'Payments', 'Payment Verified', 'Your payment for DR-2026-0001 was verified. Receipt no. OR-2026-0001.', '/modules/student/requests.php', 1, '2026-10-01 10:15:00'),
  (9, 'Enrollment', 'Enrollment Confirmed', 'Your enrollment for 1st Semester 2026-2027 is now Enrolled.', '/modules/student/enrollment.php', 1, '2026-08-12 11:00:00'),
  (10, 'Clearance', 'Clearance Item Rejected', 'Tuition and Fees Clearance was rejected: unpaid balance of PHP 2,500.00.', '/modules/student/clearance.php', 0, '2026-10-01 13:00:00'),
  (10, 'Requests', 'Document Request For Payment', 'DR-2026-0002 needs payment of PHP 300.00 before processing.', '/modules/student/requests.php', 0, '2026-10-02 10:00:00'),
  (11, 'Enrollment', 'Enrollment Request Received', 'Your request ENR-2026-0003 is pending review.', '/modules/student/enrollment.php', 1, '2026-10-02 10:15:00'),
  (14, 'Enrollment', 'Enrollment Rejected', 'ENR-2026-0006 was rejected. Please check the reason and submit a new request.', '/modules/student/enrollment.php', 0, '2026-10-01 15:30:00'),
  (16, 'Payments', 'Payment Reference Needs Verification', 'Your reference number for DR-2026-0004 is waiting for cashier verification.', '/modules/student/requests.php', 0, '2026-10-02 08:00:00'),
  (2, 'Requests', 'New Document Request Submitted', 'Maria Santos requested a Certificate of Enrollment (DR-2026-0006).', '/modules/registrar/documentRequest.php', 0, '2026-10-03 08:45:00'),
  (3, 'Requests', 'New Document Request Submitted', 'Maria Santos requested a Certificate of Enrollment (DR-2026-0006).', '/modules/registrar/documentRequest.php', 0, '2026-10-03 08:45:00'),
  (2, 'Enrollment', 'New Enrollment Request', 'Alex Mercer submitted enrollment request ENR-2026-0003.', '/modules/registrar/enrollment.php', 1, '2026-10-02 10:15:00'),
  (3, 'Enrollment', 'New Enrollment Request', 'Alex Mercer submitted enrollment request ENR-2026-0003.', '/modules/registrar/enrollment.php', 0, '2026-10-02 10:15:00'),
  (4, 'Payments', 'Payment Reference Sent', 'Sofia Lim sent reference 1234567890 for DR-2026-0004.', '/modules/cashier/payments.php', 0, '2026-10-02 08:00:00'),
  (7, 'Clearance', 'Clearance Submitted', 'New clearance items are waiting for Library review.', '/modules/department/clearance.php', 1, '2026-09-20 09:00:00'),
  (8, 'Clearance', 'Clearance Submitted', 'John Doe resubmitted a Student Affairs clearance item.', '/modules/department/clearance.php', 0, '2026-10-02 16:00:00');

UPDATE notifications SET read_at = created_at WHERE id > 0 AND is_read = 1;

-- Announcements
INSERT INTO announcements (id, title, body, audience, posted_by, created_at) VALUES
  (1, 'Midterm Exam Schedule Posted', 'Midterm examinations for the 1st Semester will officially begin next week. Please check your clearance status before exams.', 'Students', 2, '2026-10-01 08:00:00'),
  (2, 'Campus System Maintenance', 'The Student Services Information System will undergo scheduled maintenance this Sunday from 12:00 AM to 4:00 AM.', 'All', 1, '2026-09-28 09:00:00'),
  (3, 'Grade Encoding Deadline', 'Registrar staff: please finish encoding draft grades before October 10, 2026.', 'Registrar', 1, '2026-09-30 09:00:00');

-- Login attempts
INSERT INTO login_attempts (username, ip_address, was_successful, attempted_at) VALUES
  ('2026-00001', '192.168.1.20', 1, '2026-10-03 08:12:00'),
  ('admin', '192.168.1.5', 1, '2026-10-03 08:12:30'),
  ('2026-00002', '192.168.1.33', 0, '2026-10-03 07:45:00'),
  ('2026-00002', '192.168.1.33', 0, '2026-10-03 07:46:00'),
  ('registrar1', '192.168.1.7', 1, '2026-10-03 08:05:00'),
  ('unknownuser', '10.0.0.99', 0, '2026-10-02 22:10:00');

-- Audit logs
INSERT INTO audit_logs (user_id, action, entity, entity_id, details, ip_address, created_at) VALUES
  (9, 'auth.login_success', 'users', 9, 'Student logged in.', '192.168.1.20', '2026-10-03 08:12:00'),
  (NULL, 'auth.login_failed', 'users', NULL, 'Failed login for unknown username (unknownuser).', '10.0.0.99', '2026-10-02 22:10:00'),
  (2, 'enrollment.approve', 'enrollments', 9, 'Approved ENR-2026-0005.', '192.168.1.7', '2026-10-02 11:00:00'),
  (2, 'enrollment.reject', 'enrollments', 10, 'Rejected ENR-2026-0006: curriculum mismatch.', '192.168.1.7', '2026-10-01 15:30:00'),
  (2, 'grade.release', 'grades', NULL, 'Released CCS109 and CC106 grades for 2026-00001.', '192.168.1.7', '2026-10-03 09:30:00'),
  (2, 'grade.correct', 'grades', NULL, 'CC106 for 2026-00001 changed from 1.75 to 1.50. Reason: re-evaluation.', '192.168.1.7', '2026-10-03 10:05:00'),
  (4, 'payment.verify', 'payments', 1, 'Verified payment for DR-2026-0001 (OR-2026-0001).', '192.168.1.9', '2026-10-01 10:15:00'),
  (4, 'payment.reject', 'payments', 6, 'Rejected payment for DR-2026-0010: reference not found.', '192.168.1.9', '2026-10-02 13:00:00'),
  (4, 'clearance.reject', 'clearance_items', 9, 'Rejected Tuition and Fees Clearance for 2026-00002.', '192.168.1.9', '2026-10-01 13:00:00'),
  (2, 'document.status_change', 'document_requests', 1, 'DR-2026-0001: Processing to Ready for Release.', '192.168.1.7', '2026-10-01 11:00:00'),
  (1, 'settings.update', 'settings', 2, 'max_units_per_enrollment set to 24.', '192.168.1.5', '2026-08-15 08:30:00');

-- Settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
  ('school_name', 'Paxton University', 'Shown in headers and printouts'),
  ('max_units_per_enrollment', '24', 'Maximum units a student may pick per enrollment request'),
  ('login_attempt_limit', '5', 'Failed attempts before lockout'),
  ('lockout_minutes', '15', 'Lockout length in minutes'),
  ('session_timeout_minutes', '30', 'Idle timeout in minutes');

-- Number sequences. Shows the last number already used
INSERT INTO number_sequences (seq_type, seq_year, last_number) VALUES
  ('student_no', 2026, 11),
  ('enrollment_no', 2025, 4),
  ('enrollment_no', 2026, 9),
  ('request_no', 2026, 10),
  ('receipt_no', 2026, 3),
  ('application_no', 2026, 0);
