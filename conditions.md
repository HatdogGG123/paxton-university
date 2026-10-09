# SSIS Conditions and Process Rules

This file records the main conditions that decide **what the system displays** and **whether an action is allowed**. It reflects the current project code and is intended as a readable reference for the team. The controller and database checks are the final authority; hiding or disabling a button in the browser alone does not grant or deny access.

## 1. Rules used across the system

### Page and action access

| Area/action | Who can use it | Conditions checked |
| --- | --- | --- |
| Protected role pages | The role allowed for that page | The user must be signed in, the account must still be Active, the session must not have expired, and the role must be allowed. |
| Data-changing AJAX/form actions | The role allowed by that controller | The request must use POST and include the current session CSRF token. Invalid roles, methods, or tokens are rejected before saving. |
| Department records | Department staff | Queries and save checks use the signed-in department ID. Academic grade actions also require an Academic department. |
| Admin-only setup actions | Admin | For example, editing admission document requirements and managing accounts/settings. Registrar access to the Admissions page does not automatically allow admin-only setup actions. |
| Audit and system reports | Admin (and the specific reporting pages assigned to other roles) | A page's role check determines whether the report or audit information is available. |

The session inactivity limit comes from `settings.session_timeout_minutes` when available, with a 30-minute fallback. A configured value is bounded between 5 and 480 minutes. A timed-out or inactive account is sent back to sign-in.

### Display and counts

- The sidebar only shows links assigned to the signed-in role.
- The Admissions badge is available to Admin and Registrar. It counts rows in `admission_applications` whose status is exactly `Pending`. The badge is hidden at zero and refreshed in the background while the page is open.
- The notification badge counts unread notifications belonging to the signed-in user. A notification is marked read only by an authorized mark-read action.
- Most lists are scoped by the signed-in user's record, current term, role, office, department, status filter, or search term as appropriate. A user cannot gain access to another person's data by changing a form field; the server verifies ownership and scope.
- Empty-state messages are displayed when the matching query returns no records. Pagination/filtering should work only on the records currently included by that page's query.

## 2. Admissions

### Public application form

The application can be submitted only when the applicant provides:

- First and last name, contact number, and address.
- A valid email address not already used by a user account or another application.
- A program ID. The form lists active programs belonging to active Academic departments; the submission service checks that the program ID is a positive integer and relies on the database relationship for existence.

Middle name is optional. A successful submission is saved with the default `Pending` application status, gets an application number, creates an Applicant user account, and notifies the applicant plus active Admin and Registrar users. The temporary password is shown once.

### Applicant dashboard and document checklist

- An Applicant must be linked to an application to access its application details and checklist.
- The checklist contains active requirements for the application's program. If there are no active requirements, the page explains that none are configured.
- A requirement is submittable only when it belongs to the applicant's program and remains active.
- Submission method must be `Online` or `Face to Face`. Online submission requires an uploaded PDF, JPG, or PNG no larger than 5 MB. Face-to-face submission does not require an uploaded file.
- A confirmed document cannot be submitted again. A document marked for resubmission can be replaced; replacement returns it to pending review and clears the previous review decision/message.
- Document review is restricted to Admin/Registrar. The decision must be `Confirmed` or `Resubmit Needed`. A resubmission decision requires instructions.
- After document activity, the application is synchronized: it becomes `Under Review` when all active required documents are confirmed; otherwise it becomes `Documents Required`. Already `Approved`, `Rejected`, or `Enrolled` applications are not overwritten by this synchronization.

### Application review and conversion to Student

- The Admissions queue excludes applications with status `Approved`, `Enrolled`, or `Completed`. Other statuses can be shown for review.
- Application decisions are `Under Review`, `Documents Required`, `Approved`, or `Rejected`.
- To approve, at least one active required document must exist for the program, and every active requirement must have a matching document whose review status is `Confirmed`.
- After the application is Approved, the applicant must confirm approval. Conversion is allowed only for that applicant's linked application when its status is `Approved`; required documents are checked again.
- Confirmation creates a Student profile and student number, changes the user's role from Applicant to Student and username to the student number, marks the application `Enrolled`, and notifies the user.
- Admin can add an Applicant through User Management only when the required applicant profile details and a valid active Academic program are supplied. The account must have a corresponding application record. Existing application-linked users must remain in the Applicant role.

## 3. Enrollment

### What is displayed

- Enrollment uses the term marked `is_current = 1`. Without a current term, the student cannot submit and the Registrar cannot review requests.
- Available subjects must be active and match the student's program, selected year level, and current term's semester.
- The Registrar queue is scoped to the current term. A status filter is applied only when it matches one of the known enrollment statuses: `Pending`, `Under Review`, `Approved`, `Rejected`, `Enrolled`, or `Cancelled`.
- Registrar subject-adjustment choices are supplied only for requests with status `Under Review`.

### Student submission/cancellation

A request can be submitted only when all of these are true:

1. The Registrar has configured both enrollment opening and closing timestamps for the current term, and the current time is within that inclusive window.
2. A Student profile exists for the signed-in account.
3. The selected year level is at least 1, no greater than 6 in form validation, and no greater than the student's current year level.
4. At least one subject is selected; duplicate IDs are removed; every ID is a positive integer; at most 100 subjects are selected.
5. No active request exists for that student and term. `Rejected` and `Cancelled` requests do not block a new request; other statuses do.
6. Every selected subject is active and matches the student's program, chosen year level, and current semester.
7. The total units do not exceed the configured `max_units_per_enrollment` value.
8. All prerequisites have been met according to completed/released grade records.

Only the owner of a request with status `Pending` can cancel it. Cancellation changes the status to `Cancelled`.

### Registrar decisions

- Registrar actions are limited to requests in the current term.
- Status transitions allowed by the model are: `Pending` to `Under Review`; `Under Review` to `Approved`; `Pending` or `Under Review` to `Rejected`; and `Approved` to `Enrolled`.
- Rejecting requires a reason. Other transitions are rejected, even if a client submits a manually constructed request.
- Subject adjustments validate that each chosen subject is eligible for the request/student/current term and comply with the applicable course/unit rules. The adjustment save is allowed while the request is `Under Review`.
- `Approve and Update` saves the adjusted subjects first, then attempts approval. If approval fails, the update/review flow must surface the error rather than treating the request as approved.

## 4. Grades

### Department grade entry

- The current term, an Academic department account, an active section, and an active subject are required to produce a valid class roster.
- A student appears on the roster only when they have an `Enrolled` enrollment for that term and the selected subject is attached to that enrollment and matches the program/section curriculum.
- A grade may be entered only for a student in the department's class roster.
- A grade value must pass grade validation. The saved grade status is `Passed` for values at or below 3.0 and `Failed` above 3.0.
- A new grade is saved with workflow status `Registrar Review`.
- An existing grade can be resubmitted by the department only when its workflow status is `Department Revision`. Grades already waiting for Registrar review or already released cannot be overwritten from department entry.

### Registrar grade review and student display

- The Registrar approval queue shows grades with workflow status `Registrar Review` and requires a valid enrolled class/section context.
- The Registrar decision must be `release` or `return`. Returning a grade requires a reason.
- Releasing changes workflow status to `Released` and records reviewer/time. Returning changes it to `Department Revision` and notifies the entering department user.
- A Student sees only their own grades whose workflow status is exactly `Released`. Term filtering is optional; an invalid term ID is rejected.
- The older direct Registrar grade-entry API is disabled; grade entry goes through Department submission and Registrar review.

## 5. Clearance

### What appears in queues

- Clearance pages use the current term. Each student's clearance summary is based on active requirements and their corresponding clearance items.
- Office queues accept only `Registrar`, `Cashier`, or `Department` office types.
- Department staff must have a department ID. Their queue is limited to active requirements for that department and the eligible students/programs associated with it.
- Cashier queue may include the tuition/fees item when the student has a document request in `For Payment` with payment status `Unpaid`, `Pending Verification`, or `Rejected`.
- Inactive clearance requirements are excluded from the queues and student item list.

### Decisions and summary status

- A clearance decision must be `Approved` or `Rejected`.
- Rejection requires a reason. Remarks, rejection reason, and settlement reference have length limits enforced by the model.
- The item must belong to the office/dept queue being acted on. The server rechecks ownership and eligibility before changing it.
- For Cashier approval of a tuition/fees clearance, outstanding document-request payment balances must be settled. If a settlement reference is supplied, it must match an eligible paid payment reference for that student.
- The item's reviewer, decision, timestamp, remarks, and any rejection reason are stored. The student is notified.
- Overall clearance is recalculated from active items: all approved means `Cleared`; any rejected means `Action Needed`; otherwise it remains `In Progress`.

## 6. Student document requests and payments

### Request creation

- Only a Student can create a request, and the signed-in account must have a Student profile.
- The chosen document type must still be active.
- Purpose must be one of: Employment, Further Studies/Transfer, Scholarship Application, Visa/Travel Requirements, or Other.
- Copies must be from 1 through 10; remarks are optional and limited to 500 characters.
- SSIS stores the request as `Pending`. If the document type has a fee, a linked Payment is created as `Unpaid`, with amount equal to fee times copy count. Free document requests do not need a payment.

### Registrar processing transitions

The status path depends on request state and fee:

| Current status | Allowed next status |
| --- | --- |
| `Pending` | `Under Review` |
| `Under Review`, fee above zero | `For Payment` |
| `Under Review`, no fee | `Processing` |
| `Paid` | `Processing` (payment must actually be Paid) |
| `Processing` | `Ready for Release` |
| `Ready for Release` | `Completed` |
| Any other state | No normal transition |

- Rejection is a separate action and requires a reason. The reason is limited to 500 characters.
- A paid request cannot move to `Processing` until its linked payment is verified/recorded as `Paid`.
- If a fee-bearing request reaches `For Payment` without a payment row, the transition creates the unpaid payment record.
- Recording a completed request can include a claimant name of up to 120 characters.

### Cashier actions

- The Cashier can verify or reject only a submitted payment reference that is still `Pending Verification` and whose document request remains `For Payment`.
- Verification requires both a method and reference. A successful payment is marked `Paid`, receives a receipt number, updates the linked request, and notifies student/Registrar.
- Rejection requires a reason (up to 500 characters) and leaves the student able to submit a corrected reference.
- Walk-in payment is available only for a `For Payment` request whose payment is `Unpaid` or `Rejected`.
- Walk-in method must be Cash, GCash, Bank Transfer, or Other. Amount must be a valid nonnegative decimal format and exactly match the amount due. Non-cash methods require a reference.
- Refund requires a reason and is allowed only for a `Paid` payment whose request is `Cancelled` or `Rejected`.

## 7. Accounts, notifications, and announcements

### Account management

- Account management actions require Admin role.
- Username must be 1–50 characters and contain letters, numbers, dots, underscores, or hyphens. Email must be valid and at most 120 characters; usernames/emails must not conflict with another user or application.
- New accounts require an initial password of at least 8 characters, including a letter and a number.
- Department accounts require an active department. Other roles must not submit a department ID.
- Applicant account creation requires first/last name, contact, address, and a valid active Academic program, and creates the linked application record. An application-linked account cannot be changed to a non-Applicant role through this form.
- Existing user ID 1 is protected from disable/enable actions in the User Management UI.

### System settings and reference lists

- Only Admin can read or change settings through the settings controller.
- Enable/disable actions are restricted to departments, programs, subjects, document types, and clearance requirements. The record ID and new state must be valid; a missing record is rejected.
- Setting the current term clears the old current flag and assigns the selected existing term. Current enrollment and clearance workflows use this term.
- New department codes must be 2-10 uppercase letters, digits, or hyphens; the name is required and limited to 120 characters. Added departments are Academic.
- A new program requires a code, name, and existing active department.
- A new subject requires a code, name, active program, positive units, year level 1-6, and semester `1st Semester`, `2nd Semester`, or `Summer`.
- A new term name must include a year range such as `2026-2027` and a supported semester name. It can optionally become the current term.
- A document type requires a name up to 120 characters, a fee of zero or more, and an active flag of 0 or 1.
- A clearance requirement requires a name up to 120 characters, a reviewing office of Department, Cashier, or Registrar, and an active flag of 0 or 1. Department-owned requirements also require a department ID.
- General settings are saved as key/value pairs. Each setting's value-specific limits are validated by the settings action/page that owns that value.

### Notifications

- Each notification belongs to a user. Users can view and mark only their own notifications as read.
- “Mark all read” affects only that signed-in user's unread notifications.
- Unread count is based on `is_read = 0`; reading a notification changes its read state and updates the badge/UI.

### Announcements

- Announcement visibility is governed by its audience and the page's role/audience filtering. Registrar/Admin tools can create or delete announcements according to the controller role checks.
- Deleting an announcement removes it from later queries; a successful delete should also remove its row from the current table without a page reload.

## 8. Shared request safety conditions

Before a database change is accepted, controllers generally check:

- The current session and allowed role.
- POST method for a write action.
- A valid CSRF token.
- Correctly formatted IDs, action names, statuses, and required text.
- Current database state, ownership, and workflow status. This prevents stale pages or repeated clicks from applying an invalid transition.

Related multi-record changes are often wrapped in database transactions. If validation or a database operation fails, the transaction is rolled back and the page should show a user-friendly error. On success, the related list, badge, modal, and status display should reflect the saved database result.

## 9. Where to verify a rule in code

| Rule area | Main implementation locations |
| --- | --- |
| Sign-in, role/session checks | `php/includes/auth.php`, `controller/authController.php` |
| Applicant submission/review/conversion | `services/AdmissionService.php`, `models/Admission.php`, `controller/admissionController.php` |
| User accounts and role-specific fields | `controller/adminUserController.php`, `php/admin/userManagement.php` |
| Enrollment eligibility and transitions | `services/EnrollmentService.php`, `models/Enrollment.php`, `controller/enrollmentController.php` |
| Grade roster and review/release | `models/Grades.php`, `controller/gradeWorkflowController.php`, `php/department/grades.php`, `php/registrar/grades.php` |
| Clearance queue and decisions | `services/ClearanceService.php`, `models/Clearance.php`, `controller/clearanceController.php` |
| Document requests | `services/DocumentRequestsService.php`, `models/DocumentRequests.php`, `controller/documentRequestController.php` |
| Payments | `services/PaymentsService.php`, `models/Payments.php`, `controller/paymentsController.php` |
| Notification ownership/read state | `models/Notifications.php`, `controller/notificationsController.php` |
| Database constraints and available states | `ssis-schema.sql` |

When a rule changes, update both the user-facing page behavior and the server-side validation, then revise this file so it remains a reliable guide.


