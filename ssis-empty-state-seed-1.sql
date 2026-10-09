-- Empty-state demo reset for SSIS.
-- Run ssis-schema.sql and ssis-seed.sql first. This script removes demo and
-- operational records while keeping reference/configuration data and the
-- seeded staff accounts needed to sign in and browse the modules.
-- It only changes database rows; it does not remove files from uploads/.

USE ssis_db;

-- Clear dependent activity tables, then remove student/applicant profiles.
-- Foreign-key checks are restored before the script ends.
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE admission_documents;
TRUNCATE TABLE admission_applications;
TRUNCATE TABLE grade_revisions;
TRUNCATE TABLE grades;
TRUNCATE TABLE enrollment_subjects;
TRUNCATE TABLE enrollments;
TRUNCATE TABLE clearance_items;
TRUNCATE TABLE clearances;
TRUNCATE TABLE payments;
TRUNCATE TABLE document_requests;
TRUNCATE TABLE students;

TRUNCATE TABLE audit_logs;
TRUNCATE TABLE announcements;
TRUNCATE TABLE notifications;
TRUNCATE TABLE login_attempts;
TRUNCATE TABLE auth_attempt_counters;
TRUNCATE TABLE number_sequences;

-- Keep staff accounts so each staff module remains accessible. Replace all
-- seeded student/applicant accounts with dedicated empty-state viewer logins.
DELETE FROM users WHERE role IN ('Student', 'Applicant');
UPDATE users SET last_login = NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- Quick verification: activity counts should be zero; viewer account counts should be one.
SELECT
  (SELECT COUNT(*) FROM admission_applications) AS applications,
  (SELECT COUNT(*) FROM students WHERE student_no = 'EMPTY-0001') AS student_viewer_profiles,
  (SELECT COUNT(*) FROM users WHERE username = 'empty.applicant' AND role = 'Applicant') AS applicant_viewer_accounts,
  (SELECT COUNT(*) FROM enrollments) AS enrollments,
  (SELECT COUNT(*) FROM grades) AS grades,
  (SELECT COUNT(*) FROM clearances) AS clearances,
  (SELECT COUNT(*) FROM document_requests) AS document_requests,
  (SELECT COUNT(*) FROM payments) AS payments,
  (SELECT COUNT(*) FROM announcements) AS announcements,
  (SELECT COUNT(*) FROM notifications) AS notifications,
  (SELECT COUNT(*) FROM audit_logs) AS audit_logs,
  (SELECT COUNT(*) FROM login_attempts) AS login_attempts;
