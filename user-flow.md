# How SSIS Works

This guide explains the main user journeys and how information moves through the Student Services Information System (SSIS). It is written for students, staff, and anyone learning how the project is organized.

## The system in a few steps

1. A person opens a page or submits a form.
2. A PHP page displays information from the database. When a form is submitted, it sends the data to a controller.
3. The controller checks the user's role, validates the request, and calls the matching service or model.
4. The service and model apply the workflow rules and read or change database records.
5. When an action succeeds, the system may also create notifications or audit records. The page receives a response and updates the related parts of its interface.

In short: **page to controller to service/model to database to response and related UI updates**.

## People and their journeys

### Applicant

1. From the public **Student Application** page, the applicant enters their contact details, address, and chosen program.
2. On successful submission, SSIS saves an application and creates an Applicant login. The application gets an application number; the temporary password is shown once, so the applicant should save it.
3. The applicant signs in and opens the applicant dashboard to see the application status and the document checklist for their program.
4. For each required document, the applicant uploads a file online or records that it was submitted in person. A document starts as pending review.
5. The Registrar confirms a document or asks for a resubmission. The applicant sees the decision and any instructions in the dashboard and notifications.
6. The Registrar can review the application after checking its documents. Approval requires every active required document to be confirmed. Rejected or approved status is shown to the applicant.
7. After approval, the applicant confirms the approval in the dashboard. SSIS creates a student profile, assigns a student number, changes the account role to Student, and marks the application enrolled.

### Student

After signing in, a student can use the dashboard and sidebar to access their records and services.

- **Enrollment:** The student chooses eligible subjects for the current term and submits an enrollment request. The request appears in the Registrar's queue. The Registrar can review it, adjust the selected subjects, approve it, or reject it with a reason. The student can see the updated status and any reason in the enrollment page and notifications.
- **Grades:** The student views grades that have been released by the Registrar. Grades still being reviewed are not shown as final grades.
- **Clearance:** The student checks clearance requirements for the term and submits any requested information. The appropriate office reviews each requirement. The student can see approved items, pending items, and items that need attention.
- **Document requests:** The student chooses an active document type, purpose, and copy count. SSIS creates the request and, when a fee applies, a linked unpaid payment record. The Registrar processes the request after payment is settled. The student can track request and payment status, submit a payment reference, or view a receipt when available.
- **Profile and notifications:** The student can maintain profile contact details, read notifications, and see their unread notification count.

### Registrar

The Registrar handles academic and records workflows from the Registrar sidebar.

- **Admissions:** Review applications and applicant documents, request corrected documents, make application decisions, and maintain program document requirements when permitted.
- **Enrollment queue:** Review current-term requests, inspect or adjust subject selections, then approve or reject each request. Students receive the resulting status.
- **Grades:** Review grades submitted by departments, approve them for release, or return them for correction. Approved grades become visible to students.
- **Document requests:** Review student requests, check payment status, and move each request through its available processing states. Record release or collection details when completing a request.
- **Clearance:** Review Registrar-owned clearance items and update them with a decision or reason.
- **Announcements and reports:** Publish announcements for their audience and view Registrar reports. Notifications help staff see new work.

### Department staff

Department accounts are associated with a department. Their work is limited to the records for that department where the workflow applies.

- **Clearance:** Open the clearance queue, find student items assigned to the department, and approve or reject them. A rejection can include a reason for the student.
- **Grade entry:** Choose a class and term, enter grades for the roster, and submit them. Submitted grades go to the Registrar for final review and release.
- **Reports and notifications:** Review department reports and read notices about relevant work.

### Cashier

The Cashier handles payment verification and cashier-owned clearance items.

- **Payment verification:** Inspect payment references submitted by students. Verify a valid payment or reject it with an explanation. A verified payment gets a receipt number and makes the related document request available for Registrar processing.
- **Walk-in payments:** Record an eligible in-person payment. The amount must match the amount due. SSIS records the method and receipt and updates the linked request.
- **Refunds:** Refund eligible paid requests that have been cancelled or rejected, with a recorded reason.
- **Clearance and reports:** Review cashier-owned clearance items and use cashier reports or notifications as available.

### Administrator

The Administrator manages access and system configuration.

- **User Management:** Create or edit staff accounts and supported student or applicant accounts. A manually created Applicant account also has an application record so it can use the applicant journey. Creating an applicant through this form increases the same pending-applicant count used by Admissions.
- **System Settings:** Update configurable system values and reference records presented on the settings page.
- **System Monitoring and Reports:** Review system activity, audit history, and reports.
- **Admissions and Notifications:** Open the admissions queue and read system notifications. The Admissions badge represents applications whose status is `Pending` and refreshes while the page is open.

## Where the information is stored

These are the main database records behind the journeys above:

| Information | Main records |
| --- | --- |
| Sign-in accounts and roles | `users` |
| Student profile and student number | `students` |
| Applicant details and application status | `admission_applications` |
| Required and submitted admission documents | `admission_document_requirements`, `admission_documents` |
| Departments, programs, terms, classes, and subjects | `departments`, `programs`, `terms`, `sections`, `subjects` |
| Enrollment requests and their selected subjects | `enrollments`, `enrollment_subjects` |
| Grades and grade corrections | `grades`, `grade_revisions` |
| Clearance requirements and decisions | `clearance_requirements`, `clearances`, `clearance_items` |
| Student document requests and payments | `document_types`, `document_requests`, `payments` |
| Announcements and user notifications | `announcements`, `notifications` |
| Security and change history | `login_attempts`, `audit_logs` |
| Application numbering and configurable values | `number_sequences`, `settings` |

## How a save reaches the database

For example, when a student submits a document request:

1. The student fills in the request form on the Student **Requests** page.
2. The page sends the form to `controller/documentRequestController.php`.
3. The controller checks the session, role, form token, and input, then hands the work to the document request service/model.
4. SSIS saves the request. If the document has a fee, it also saves an unpaid payment record. These related changes are grouped so an incomplete save can be rolled back.
5. SSIS creates notifications for relevant staff and an audit entry.
6. The controller returns success information. The page updates the request list and related counts without needing a full page reload.

Other workflows follow the same general path, though each has its own validation and state rules. A successful response means the relevant save operation completed; the page should then update the parts that depend on those saved records.

## Important status handoffs

- **Admission documents:** Applicant submits → Registrar confirms or requests resubmission.
- **Admission application:** Pending/review → Registrar decision → applicant confirms approval → Student profile and student number are created.
- **Enrollment:** Student submits → Registrar reviews/adjusts → approved or rejected result appears to student.
- **Grades:** Department submits → Registrar approves or returns → approved grade is released to student.
- **Clearance:** Student submits information → responsible department, Cashier, or Registrar reviews its own item → overall clearance updates from its items.
- **Document request:** Student requests → payment is recorded or verified when a fee applies → Registrar processes and releases the document.

## Sign-in and access

Sign-in checks the account and password, then sends each role to its starting page. Pages and controllers check the signed-in role before allowing protected actions. The session has an inactivity timeout. Signing out ends the session.

Use the sidebar to move between the pages available to your role. If an action is not shown or access is denied, that feature may be reserved for another role or department.

## Project files to explore

- `application.php` — public application form.
- `php/<role>/` — user-facing pages grouped by role, such as `php/student/` and `php/registrar/`.
- `php/component/sidebar.php` — shared role-based navigation.
- `controller/` — form and AJAX request entry points.
- `services/` — workflow validation and coordination.
- `models/` — database reads and writes.
- `models/Database.php` — database connection.
- `ssis-schema.sql` — database structure.
- `ssis-seed.sql` — example data for a populated system.
- `ssis-empty-state-seed-*.sql` — scripts for viewing empty-state screens with demo sign-in accounts.

For a fresh local database, create the schema first, then choose the populated seed or an empty-state seed depending on which screens you want to explore. Seed scripts are for development/demo data; they are not part of a user's normal workflow.

