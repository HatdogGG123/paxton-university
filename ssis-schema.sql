CREATE DATABASE IF NOT EXISTS ssis_db;
USE ssis_db;

-- Complete current schema, including changes previously delivered as migrations.
-- Drop first so this file can be run again
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS entrance_exam_answers, entrance_exam_attempts, entrance_exam_questions, exam_sessions,
  admission_documents, admission_document_requirements, admission_applications, number_sequences, settings, password_resets, auth_attempt_counters, login_attempts,
  audit_logs, announcements, notifications, payments, document_requests,
  document_types, clearance_items, clearances, clearance_requirements,
  grade_revisions, grades, enrollment_subjects, enrollments,
  subject_prerequisites, subjects, students, users, sections, terms,
  programs, departments;

-- Departments. Library and OSA are here too since they sign clearance
CREATE TABLE departments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(10) NOT NULL,
  name VARCHAR(120) NOT NULL,
  dept_type ENUM('Academic','Administrative') NOT NULL DEFAULT 'Academic',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_departments_code (code),
  UNIQUE KEY uq_departments_name (name)
) ENGINE=InnoDB;

-- Programs offered by each department
CREATE TABLE programs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  department_id INT UNSIGNED NOT NULL,
  code VARCHAR(15) NOT NULL,
  name VARCHAR(150) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_programs_code (code),
  KEY idx_programs_department (department_id),
  CONSTRAINT fk_programs_department FOREIGN KEY (department_id)
    REFERENCES departments (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Class sections like 3A and 3B
CREATE TABLE sections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id INT UNSIGNED NOT NULL,
  year_level TINYINT UNSIGNED NOT NULL,
  code VARCHAR(10) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sections (program_id, year_level, code),
  CONSTRAINT fk_sections_program FOREIGN KEY (program_id)
    REFERENCES programs (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_sections_year CHECK (year_level BETWEEN 1 AND 6)
) ENGINE=InnoDB;

-- Semesters and academic years
CREATE TABLE terms (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  academic_year CHAR(9) NOT NULL,
  semester ENUM('1st Semester','2nd Semester','Summer') NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  enrollment_open_at DATETIME NULL,
  enrollment_close_at DATETIME NULL,
  -- used to allow only one current term
  current_flag TINYINT GENERATED ALWAYS AS (IF(is_current = 1, 1, NULL)) STORED,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_terms_year_sem (academic_year, semester),
  UNIQUE KEY uq_terms_one_current (current_flag)
) ENGINE=InnoDB;

-- Subjects of each program
CREATE TABLE subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id INT UNSIGNED NOT NULL,
  code VARCHAR(15) NOT NULL,
  name VARCHAR(150) NOT NULL,
  units DECIMAL(3,1) NOT NULL,
  year_level TINYINT UNSIGNED NOT NULL,
  semester ENUM('1st Semester','2nd Semester','Summer') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subjects_program_code (program_id, code),
  KEY idx_subjects_year_sem (year_level, semester),
  CONSTRAINT fk_subjects_program FOREIGN KEY (program_id)
    REFERENCES programs (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_subjects_units CHECK (units > 0),
  CONSTRAINT chk_subjects_year CHECK (year_level BETWEEN 1 AND 6)
) ENGINE=InnoDB;

-- Which subject must be taken before another
CREATE TABLE subject_prerequisites (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  subject_id INT UNSIGNED NOT NULL,
  prerequisite_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_subject_prereq (subject_id, prerequisite_id),
  KEY idx_prereq_prerequisite (prerequisite_id),
  CONSTRAINT fk_prereq_subject FOREIGN KEY (subject_id)
    REFERENCES subjects (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_prereq_prerequisite FOREIGN KEY (prerequisite_id)
    REFERENCES subjects (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_prereq_not_self CHECK (subject_id <> prerequisite_id)
) ENGINE=InnoDB;

-- Login accounts for every role
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  email VARCHAR(120) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Student','Applicant','Registrar','Cashier','Department','Admin') NOT NULL,
  department_id INT UNSIGNED NULL,
  full_name VARCHAR(120) NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  password_changed_at DATETIME NULL,
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_status (status),
  KEY idx_users_department (department_id),
  CONSTRAINT fk_users_department FOREIGN KEY (department_id)
    REFERENCES departments (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_users_dept_role CHECK (
    (role = 'Department' AND department_id IS NOT NULL) OR
    (role <> 'Department' AND department_id IS NULL))
) ENGINE=InnoDB;

-- Student profiles linked to a user account
CREATE TABLE students (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  student_no VARCHAR(10) NOT NULL,
  first_name VARCHAR(60) NOT NULL,
  middle_name VARCHAR(60) NULL,
  last_name VARCHAR(60) NOT NULL,
  email VARCHAR(120) NULL,
  contact_number VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  program_id INT UNSIGNED NOT NULL,
  year_level TINYINT UNSIGNED NOT NULL,
  student_type ENUM('Regular','Irregular') NOT NULL DEFAULT 'Regular',
  admission_term_id INT UNSIGNED NULL,
  curriculum_version VARCHAR(30) NULL,
  guardian_name VARCHAR(120) NULL,
  guardian_relationship VARCHAR(40) NULL,
  guardian_contact VARCHAR(20) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_students_user (user_id),
  UNIQUE KEY uq_students_student_no (student_no),
  KEY idx_students_program (program_id),
  KEY idx_students_admission_term (admission_term_id),
  KEY idx_students_name (last_name, first_name),
  KEY idx_students_year_level (year_level),
  CONSTRAINT fk_students_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_students_program FOREIGN KEY (program_id)
    REFERENCES programs (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_students_admission_term FOREIGN KEY (admission_term_id)
    REFERENCES terms (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_students_year CHECK (year_level BETWEEN 1 AND 6)
) ENGINE=InnoDB;

-- Prospective student applications and their uploaded admission documents
CREATE TABLE admission_applications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_no VARCHAR(20) NOT NULL,
  first_name VARCHAR(60) NOT NULL,
  middle_name VARCHAR(60) NULL,
  last_name VARCHAR(60) NOT NULL,
  email VARCHAR(120) NOT NULL,
  contact_number VARCHAR(20) NOT NULL,
  address VARCHAR(255) NOT NULL,
  program_id INT UNSIGNED NOT NULL,
  status ENUM('Pending','Under Review','Documents Required','Approved','Rejected','Enrolled') NOT NULL DEFAULT 'Pending',
  reviewer_id INT UNSIGNED NULL,
  applicant_user_id INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  remarks VARCHAR(500) NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_admission_application_no (application_no), UNIQUE KEY uq_admission_application_email (email), UNIQUE KEY uq_admission_applicant_user (applicant_user_id),
  KEY idx_admission_status (status), KEY idx_admission_program (program_id),
  CONSTRAINT fk_admission_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_admission_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_admission_applicant_user FOREIGN KEY (applicant_user_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE admission_document_requirements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  program_id INT UNSIGNED NOT NULL,
  document_name VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_admission_requirement (program_id, document_name),
  CONSTRAINT fk_admission_requirement_program FOREIGN KEY (program_id) REFERENCES programs (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_admission_requirement_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE admission_documents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  application_id INT UNSIGNED NOT NULL,
  requirement_id INT UNSIGNED NULL,
  submission_method ENUM('Online','Face to Face') NULL,
  legacy_document_type VARCHAR(80) NOT NULL DEFAULT 'Legacy document',
  file_path VARCHAR(255) NULL,
  review_status ENUM('Pending','Confirmed','Resubmit Needed') NOT NULL DEFAULT 'Pending',
  reviewer_id INT UNSIGNED NULL,
  review_message VARCHAR(500) NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_admission_document_requirement (application_id, requirement_id), KEY idx_admission_documents_application (application_id),
  CONSTRAINT fk_admission_document_application FOREIGN KEY (application_id) REFERENCES admission_applications (id) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT fk_admission_document_requirement FOREIGN KEY (requirement_id) REFERENCES admission_document_requirements (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_admission_document_reviewer FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Enrollment requests
CREATE TABLE enrollments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  enrollment_no VARCHAR(15) NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  section_id INT UNSIGNED NULL,
  year_level TINYINT UNSIGNED NOT NULL,
  status ENUM('Pending','Under Review','Approved','Rejected','Enrolled','Cancelled')
                    NOT NULL DEFAULT 'Pending',
  rejection_reason VARCHAR(500) NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  enrolled_at DATETIME NULL,
  -- used to allow only one active enrollment per term
  active_slot TINYINT GENERATED ALWAYS AS
                    (IF(status IN ('Rejected','Cancelled'), NULL, 1)) STORED,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enrollments_no (enrollment_no),
  UNIQUE KEY uq_enrollments_one_active (student_id, term_id, active_slot),
  KEY idx_enrollments_student (student_id),
  KEY idx_enrollments_term (term_id),
  KEY idx_enrollments_section (section_id),
  KEY idx_enrollments_status (status),
  KEY idx_enrollments_reviewed_by (reviewed_by),
  CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id)
    REFERENCES students (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_enrollments_term FOREIGN KEY (term_id)
    REFERENCES terms (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_enrollments_section FOREIGN KEY (section_id)
    REFERENCES sections (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_enrollments_reviewed_by FOREIGN KEY (reviewed_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_enrollments_year CHECK (year_level BETWEEN 1 AND 6)
) ENGINE=InnoDB;

-- Subjects chosen in an enrollment request
CREATE TABLE enrollment_subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  enrollment_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enrollment_subject (enrollment_id, subject_id),
  KEY idx_es_subject (subject_id),
  CONSTRAINT fk_es_enrollment FOREIGN KEY (enrollment_id)
    REFERENCES enrollments (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_es_subject FOREIGN KEY (subject_id)
    REFERENCES subjects (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Grade workflow: department entry -> Registrar review -> released to students.
CREATE TABLE grades (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  grade DECIMAL(3,2) NULL,
  grade_status ENUM('Passed','Failed','Incomplete','Dropped') NULL,
  status ENUM('Department Revision','Registrar Review','Released') NOT NULL DEFAULT 'Department Revision',
  entered_by INT UNSIGNED NOT NULL,
  released_by INT UNSIGNED NULL,
  released_at DATETIME NULL,
  review_message VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grades_student_subject_term (student_id, subject_id, term_id),
  KEY idx_grades_subject (subject_id),
  KEY idx_grades_term (term_id),
  KEY idx_grades_status (status),
  KEY idx_grades_entered_by (entered_by),
  KEY idx_grades_released_by (released_by),
  CONSTRAINT fk_grades_student FOREIGN KEY (student_id)
    REFERENCES students (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_grades_subject FOREIGN KEY (subject_id)
    REFERENCES subjects (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_grades_term FOREIGN KEY (term_id)
    REFERENCES terms (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_grades_entered_by FOREIGN KEY (entered_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_grades_released_by FOREIGN KEY (released_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_grades_range CHECK (grade IS NULL OR grade BETWEEN 1.00 AND 5.00)
) ENGINE=InnoDB;

-- History of corrected released grades
CREATE TABLE grade_revisions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  grade_id INT UNSIGNED NOT NULL,
  old_grade DECIMAL(3,2) NULL,
  new_grade DECIMAL(3,2) NULL,
  old_grade_status ENUM('Passed','Failed','Incomplete','Dropped') NULL,
  new_grade_status ENUM('Passed','Failed','Incomplete','Dropped') NULL,
  reason VARCHAR(500) NOT NULL,
  changed_by INT UNSIGNED NOT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gr_grade (grade_id),
  KEY idx_gr_changed_by (changed_by),
  CONSTRAINT fk_gr_grade FOREIGN KEY (grade_id)
    REFERENCES grades (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_gr_changed_by FOREIGN KEY (changed_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Things a student must clear
CREATE TABLE clearance_requirements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  office ENUM('Department','Cashier','Registrar') NOT NULL,
  department_id INT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clr_req_name (name),
  KEY idx_clr_req_office (office),
  KEY idx_clr_req_department (department_id),
  CONSTRAINT fk_clr_req_department FOREIGN KEY (department_id)
    REFERENCES departments (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_clr_req_office_dept CHECK (
    (office = 'Department' AND department_id IS NOT NULL) OR
    (office <> 'Department' AND department_id IS NULL))
) ENGINE=InnoDB;

-- One clearance per student per term
CREATE TABLE clearances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  term_id INT UNSIGNED NOT NULL,
  overall_status ENUM('In Progress','Action Needed','Cleared') NOT NULL DEFAULT 'In Progress',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clearances_student_term (student_id, term_id),
  KEY idx_clearances_term (term_id),
  KEY idx_clearances_status (overall_status),
  CONSTRAINT fk_clearances_student FOREIGN KEY (student_id)
    REFERENCES students (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clearances_term FOREIGN KEY (term_id)
    REFERENCES terms (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Each office answer for a clearance
CREATE TABLE clearance_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  clearance_id INT UNSIGNED NOT NULL,
  requirement_id INT UNSIGNED NOT NULL,
  status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  remarks VARCHAR(255) NULL,
  rejection_reason VARCHAR(500) NULL,
  submitted_at DATETIME NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clr_item (clearance_id, requirement_id),
  KEY idx_clr_item_requirement (requirement_id),
  KEY idx_clr_item_status (status),
  KEY idx_clr_item_reviewed_by (reviewed_by),
  CONSTRAINT fk_clr_item_clearance FOREIGN KEY (clearance_id)
    REFERENCES clearances (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clr_item_requirement FOREIGN KEY (requirement_id)
    REFERENCES clearance_requirements (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_clr_item_reviewed_by FOREIGN KEY (reviewed_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Documents students can request
CREATE TABLE document_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(10) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL,
  fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_doc_types_code (code),
  UNIQUE KEY uq_doc_types_name (name),
  CONSTRAINT chk_doc_types_fee CHECK (fee >= 0)
) ENGINE=InnoDB;

-- Document requests
CREATE TABLE document_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_no VARCHAR(15) NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  document_type_id INT UNSIGNED NOT NULL,
  purpose VARCHAR(150) NOT NULL,
  copies TINYINT UNSIGNED NOT NULL DEFAULT 1,
  remarks VARCHAR(500) NULL,
  fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('Pending','Under Review','For Payment','Paid','Processing',
                         'Ready for Release','Completed','Rejected','Cancelled')
                    NOT NULL DEFAULT 'Pending',
  rejection_reason VARCHAR(500) NULL,
  processed_by INT UNSIGNED NULL,
  received_by VARCHAR(120) NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_doc_requests_no (request_no),
  KEY idx_doc_requests_student (student_id),
  KEY idx_doc_requests_type (document_type_id),
  KEY idx_doc_requests_status (status),
  KEY idx_doc_requests_processed_by (processed_by),
  KEY idx_doc_requests_created (created_at),
  CONSTRAINT fk_doc_requests_student FOREIGN KEY (student_id)
    REFERENCES students (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_doc_requests_type FOREIGN KEY (document_type_id)
    REFERENCES document_types (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_doc_requests_processed_by FOREIGN KEY (processed_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_doc_requests_copies CHECK (copies >= 1),
  CONSTRAINT chk_doc_requests_fee CHECK (fee_amount >= 0)
) ENGINE=InnoDB;

-- Payments for document requests
CREATE TABLE payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id INT UNSIGNED NOT NULL,
  receipt_no VARCHAR(15) NULL,
  reference_number VARCHAR(60) NULL,
  amount DECIMAL(10,2) NOT NULL,
  method ENUM('Cash','GCash','Bank Transfer','Other') NULL,
  status ENUM('Unpaid','Pending Verification','Paid','Rejected','Refunded')
                    NOT NULL DEFAULT 'Unpaid',
  rejection_reason VARCHAR(500) NULL,
  refund_reason VARCHAR(500) NULL,
  submitted_at DATETIME NULL,
  recorded_by INT UNSIGNED NULL,
  verified_by INT UNSIGNED NULL,
  verified_at DATETIME NULL,
  refunded_by INT UNSIGNED NULL,
  refunded_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_request (request_id),
  UNIQUE KEY uq_payments_receipt (receipt_no),
  KEY idx_payments_reference (reference_number),
  KEY idx_payments_status (status),
  KEY idx_payments_recorded_by (recorded_by),
  KEY idx_payments_verified_by (verified_by),
  KEY idx_payments_refunded_by (refunded_by),
  KEY idx_payments_verified_at (verified_at),
  CONSTRAINT fk_payments_request FOREIGN KEY (request_id)
    REFERENCES document_requests (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_payments_recorded_by FOREIGN KEY (recorded_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_payments_verified_by FOREIGN KEY (verified_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_payments_refunded_by FOREIGN KEY (refunded_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT chk_payments_amount CHECK (amount > 0)
) ENGINE=InnoDB;

-- Messages for the bell icon
CREATE TABLE notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  category ENUM('Enrollment','Grades','Clearance','Requests','Payments',
                   'Announcements','Account','System') NOT NULL DEFAULT 'System',
  title VARCHAR(150) NOT NULL,
  message VARCHAR(500) NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notifications_user_read (user_id, is_read, created_at),
  KEY idx_notifications_category (category),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- School announcements
CREATE TABLE announcements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(150) NOT NULL,
  body TEXT NOT NULL,
  audience ENUM('All','Students','Registrar','Cashier','Department','Admin')
              NOT NULL DEFAULT 'All',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  posted_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_announcements_audience (audience, is_active, created_at),
  KEY idx_announcements_posted_by (posted_by),
  CONSTRAINT fk_announcements_posted_by FOREIGN KEY (posted_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Who did what and when
CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_user (user_id),
  KEY idx_audit_action (action),
  KEY idx_audit_entity (entity, entity_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Every login try. Used for the lockout rule
CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  was_successful TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_login_username_time (username, attempted_at),
  KEY idx_login_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

-- Consecutive login and password-reset failures use hashed account/session identities.
CREATE TABLE auth_attempt_counters (
  email_hash CHAR(64) NOT NULL,
  attempt_type ENUM('Login','Password Reset') NOT NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_until DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (email_hash, attempt_type),
  KEY idx_auth_attempt_locked_until (locked_until)
) ENGINE=InnoDB;

-- Single-use password reset credentials; store only the hash of the emailed token.
CREATE TABLE password_resets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token_hash (token_hash),
  KEY idx_password_resets_user_expires (user_id, expires_at),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- System settings
CREATE TABLE settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(60) NOT NULL,
  setting_value VARCHAR(255) NOT NULL,
  description VARCHAR(255) NULL,
  updated_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_key (setting_key),
  KEY idx_settings_updated_by (updated_by),
  CONSTRAINT fk_settings_updated_by FOREIGN KEY (updated_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Counters for the student and request and receipt numbers
CREATE TABLE number_sequences (
  seq_type ENUM('student_no','enrollment_no','request_no','receipt_no','application_no') NOT NULL,
  seq_year SMALLINT UNSIGNED NOT NULL,
  last_number INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (seq_type, seq_year)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
