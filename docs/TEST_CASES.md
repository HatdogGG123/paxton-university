# SSIS Manual Test Cases

These cases are intended for manual execution in a non-production database. Record **Pass/Fail**, tester, date, and issue reference in the Result column. Run cases with separate role accounts so role enforcement is exercised as well as the screens.

## Test environment and fixtures

Before running the cases:

1. Import `ssis-schema.sql` and `ssis-seed.sql`, or apply the applicable migrations to a backed-up development database.
2. Ensure there is an active term, at least one active program, active subjects for that program, and a student with completed prerequisite grades for prerequisite tests.
3. Create or identify active Admin, Registrar, Cashier, Department, Student, and Applicant accounts. Department accounts must belong to an academic department with enrolled classes.
4. For admissions, configure at least two active document requirements for the test program while signed in as Admin. Have a PDF/JPG/PNG under 5 MB and a file with a disallowed type or over 5 MB.
5. For enrollment, configure a future/current date window and a past/closed date window in a development term. Keep test student/request records identifiable for cleanup.
6. Use a supported modern browser and keep the browser console/network panel available for API-backed screens.

### Result record template

|   Case ID | Result (Pass/Fail/Blocked) | Tester/date | Notes/issue |
| --- | --- | --- | --- |
|  |  |  |  |

## Public access and authentication

| ID    | Scenario and steps | Expected result |
| ---   | --- | --- |
| AUTH-01 | Open the home page, select Login, enter valid credentials for each supported role, and sign in. | Sign-in succeeds and redirects to the correct role landing page: Student, Applicant, Registrar, Cashier, Department, or Admin. |
| AUTH-02 | Enter an invalid username/password combination. | Sign-in is rejected without revealing whether the username exists; no authenticated session is created. |
| AUTH-03 | Sign in as an inactive account. | Access is rejected. |
| AUTH-04 | Open a protected module URL while signed out. | The request redirects to Login and does not display protected data. |
| AUTH-05 | While signed in, submit a protected form with a missing or altered CSRF token. | The write is rejected and no data changes. |
| AUTH-06 | Sign in, wait beyond the configured inactivity timeout, then revisit a protected page. | The expired session is rejected and the user must sign in again. |
| AUTH-07 | On Login, submit an incorrect password once using a username tied to a stored account email. | `showToast()` reports an invalid credential and the number of login attempts remaining (4). |
| AUTH-08 | In one browser session, submit five wrong passwords while changing the username/student number each time. | Failures share the session counter; the fifth attempt locks login for the configured cooldown and the page shows a live countdown. |
| AUTH-09 | After the fifth failure, submit incorrect credentials again, then submit the correct credentials for an active account. | Incorrect credentials remain blocked; correct credentials sign in successfully and clear the session and matching account login counters. |
| AUTH-10 | In a new browser session, make failed login attempts for a different account. | The new session has its own counter; the first session's failures do not consume its five attempts. Per-account limits still apply. |
| AUTH-11 | Try login with a valid account email instead of its username/student number. | The account is found, login works on correct password, and the account-level rate limit applies equally to username and email sign-in. |
| AUTH-12 | As Admin, set/update an account email in User Management, then try to assign that same email to a different account. | First update persists and can be used for login/reset; duplicate assignment is rejected. |

## Applicant application and admissions

| ID | Scenario and steps | Expected result |
| --- | --- | --- |
| ADM-01 | As a visitor, submit a valid application with a program that has active requirements. | An application number and Applicant account are created; the applicant is signed in and shown the account credentials once. |
| ADM-02 | Submit an application with a missing required field, invalid email, or invalid program. | Submission is rejected with a validation message; no incomplete application/account is created. |
| ADM-03 | Inspect the application receipt immediately after ADM-01 and then open Applicant Dashboard. | Both show the program-specific required document list, each initially marked Not submitted. |
| ADM-04 | On Applicant Dashboard, upload a valid PDF smaller than 5 MB for a missing requirement. | The document is stored privately, displayed as Pending/Online, and shown in the staff checklist. |
| ADM-05 | Upload a valid JPG and PNG for separate requirements. | Both are accepted and stored with appropriate media handling. |
| ADM-06 | Upload an executable/unsupported file or a file larger than 5 MB. | Upload is rejected with a clear message; it is not added as a submitted document. |
| ADM-07 | As Applicant, attempt to submit a requirement that belongs to another program or an inactive requirement. | The controller/model rejects the submission and does not create a document record. |
| ADM-08 | As Applicant, try to submit a document after it is confirmed. | The submission is rejected; the confirmed document remains unchanged. |
| ADM-09 | As Registrar, open Admissions and search by applicant name and application number. Expand the applicantâ€™s Document checklist. | Matching applications remain visible; the checklist expands and lists each active requirement and its submission state. |
| ADM-10 | For a submitted online document, select Approve document and save. | The document changes to Confirmed, reviewer/time are stored, and the applicant sees the updated status. |
| ADM-11 | For a submitted document, select Request resubmission, choose a preset and enter extra guidance, then save. | Status becomes Resubmit Needed; the applicant sees the combined instructions and can upload a replacement. |
| ADM-12 | Request resubmission with no guidance text or preset. | The action is rejected; document status remains unchanged. |
| ADM-13 | Request resubmission using Custom message and provide custom instructions. | The custom instructions are saved and visible to the applicant. |
| ADM-14 | As Registrar, record a missing document as received face-to-face. | The requirement is recorded as Pending / Face to Face without a file; staff can subsequently approve it. |
| ADM-15 | Try to approve an application while one or more requirements are missing or not confirmed. | Approval is rejected and the application remains unapproved. |
| ADM-16 | Confirm all active requirements, then as Registrar approve the application. | Application status becomes Approved; Applicant dashboard displays the approval confirmation action. |
| ADM-17 | As Applicant, attempt the confirmation action before staff approval or before all requirements are confirmed. | The request is rejected and the user remains an Applicant. |
| ADM-18 | As Applicant, confirm an approved application with all documents confirmed. | A Student profile is created for the applied program, role changes from Applicant to Student, username becomes the student number, and the existing session opens the Student module. |
| ADM-19 | As Registrar, attempt to add/edit/delete an active document requirement. | The server denies the action; the active checklist is unchanged. |
| ADM-20 | As Admin, add a requirement to a program, edit its name/program, then delete it. | Changes are persisted; after deletion it no longer appears on active applicant checklists, and prior submission records remain retained. |
| ADM-21 | As a user without an Admin/Registrar role, attempt to open Admissions or call its controller directly. | Access is denied; no application or requirement data is exposed/changed. |
| ADM-22 | Open a submitted document using View document; then try a nonexistent ID or a path traversal-style ID. | A valid authorized file is streamed with a safe content type; invalid files/IDs are not exposed. |
| ADM-23 | Submit an application using an email already used by an account or previous application. | Application is rejected before credentials are issued; the user is prompted to sign in or use a different email. |

## Forgot Password

| ID | Scenario and steps | Expected result |
| --- | --- | --- |
| PWD-01 | Enter a registered active account email and select Continue. | The account is found, password and confirmation fields appear, and `showToast()` confirms the email check. |
| PWD-02 | Enter a malformed or unknown email. | No password fields appear; `showToast()` reports failure and remaining reset attempts. |
| PWD-03 | Enter a password shorter than 8 characters or without both a letter and a number. | Password is not changed; `showToast()` explains the rule and remaining attempts decrease. |
| PWD-04 | Enter a valid password but a mismatched confirmation. | Password is not changed and a toast reports the mismatch and attempts remaining. |
| PWD-05 | Use the show/hide password control. | Both new-password fields switch between masked and visible text; the controlâ€™s accessible pressed state changes. |
| PWD-06 | After a successful email lookup, enter a valid new password twice and save. | Password hash changes, `must_change_password` clears, reset failures clear, success toast appears, and the new password works at Login while the old one fails. |
| PWD-07 | Cause five failed reset attempts for one email. | Failures show decreasing counts; the fifth locks password reset for that email during the cooldown. Other emails remain unaffected. |
| PWD-08 | Try a password reset for an inactive account or after the email on the account has changed. | Reset is denied and no password or account status changes. |
| PWD-09 | Confirm the reset behavior after email lookup. | Current requested flow allows reset after confirming that the email exists; record the security limitation that it does not verify mailbox ownership. |

## Enrollment

| ID | Scenario and steps | Expected result |
| --- | --- | --- |
| ENR-01 | As Admin, save a valid opening and closing date/time for the current term. | The period is persisted and displayed on the Student Enrollment page. |
| ENR-02 | As Admin, submit an end time earlier than or equal to the opening time. | The date range is rejected and the stored range is not changed. |
| ENR-03 | As Student, open Enrollment before the opening time or after close. | The active term/range is shown, but subject request submission is unavailable. |
| ENR-04 | As Student, attempt a direct enrollment POST outside the configured range. | The server rejects it; no enrollment request is created. |
| ENR-05 | During the allowed window, submit eligible subjects for the studentâ€™s program/year/semester. | A numbered Pending request and its selected subjects are stored; the student sees the request status. |
| ENR-06 | Submit no subjects, an inactive subject, or a subject from another program/term/year. | Submission is rejected and no request is created. |
| ENR-07 | Select subjects exceeding the configured maximum units. | Submission is rejected with the limit indicated. |
| ENR-08 | Select a subject whose prerequisite is not passed. | Submission is rejected with the unmet prerequisite identified. |
| ENR-09 | Submit a second active enrollment request for the same term. | Duplicate active request is rejected. |
| ENR-10 | As Student, cancel a Pending request, then inspect the current term. | Request becomes Cancelled; the student may submit a replacement if the enrollment window is still open. |
| ENR-11 | As Registrar, review a request, adjust its eligible subject list, and approve. | Updated subject list and Approved/Enrolled transition are saved; student receives an updated status/notification. |
| ENR-12 | As Registrar, reject a request with a reason. | Request is rejected with the reason visible to the student; a replacement can be submitted during the open window. |
| ENR-13 | As Registrar, attempt to add an ineligible subject or update a request outside permitted states. | The action is rejected and the request remains unchanged. |

## Clearance

| ID | Scenario and steps | Expected result |
| --- | --- | --- |
| CLR-01 | As Student, open Clearance for a current term with generated requirements. | The page lists office requirements and current item/overall status. |
| CLR-02 | As Registrar, approve an eligible Registrar clearance item. | Item decision is stored, overall status recalculates, and the student receives an update. |
| CLR-03 | As Registrar, reject an item with a reason. | Rejected status and reason are visible to the student. |
| CLR-04 | As Cashier, update a financial clearance item with appropriate payment/settlement information. | Financial clearance changes only after the required validation; student-facing state updates. |
| CLR-05 | As Department user, approve/reject a clearance item belonging to the assigned department. | Decision is accepted and audited/notified as implemented. |
| CLR-06 | As Department user, try to change another departmentâ€™s item by modifying the submitted ID. | The server denies the action; no other departmentâ€™s item changes. |
| CLR-07 | Submit a clearance decision with invalid status, missing required rejection reason, invalid record ID, or bad CSRF token. | The request is rejected and no unauthorized/invalid update is written. |
| CLR-08 | Use a CCS student account and open current-term Clearance when CCS and CBA academic clearance requirements are active. | The student sees the CCS requirement, does not see CBA Department Clearance, and still sees shared Library, Student Affairs, Cashier, and Registrar requirements. |
| CLR-09 | Use a CBA student account and open current-term Clearance with the same requirements. | The student sees the CBA requirement and does not see the CCS Laboratory and Project Clearance. |
| CLR-10 | As a CCS department user, open the Department clearance queue and try to submit a CBA studentâ€™s clearance item ID. | The queue contains only CCS students for the CCS academic requirement, and the server rejects the out-of-scope item update. |
| CLR-11 | In a development database, create a legacy current-term CCS clearance item for the CBA academic requirement, then open the CCS studentâ€™s Clearance page. | The incorrect item is removed, the student view excludes it, and overall status is recalculated without it. |

## Student document requests and cashier payments

| ID | Scenario and steps | Expected result |
| --- | --- | --- |
| DOC-01 | As Student, submit a request for an active document type with valid purpose/copies. | Request number is generated, fee is calculated, and request appears in Student Requests. |
| DOC-02 | Submit invalid document type, invalid quantity, or missing purpose. | Request is rejected and no request record is created. |
| DOC-03 | As Registrar, move a request through its allowed processing statuses. | Each allowed transition persists, status is visible to Student, and invalid transitions are rejected. |
| DOC-04 | As Registrar, reject a request with a reason. | Request is rejected and the student can see the reason. |
| DOC-05 | As Cashier, verify a valid submitted payment reference. | Payment status changes, linked request/clearance updates as applicable, and receipt number is generated. |
| DOC-06 | As Cashier, reject an invalid payment with a reason. | Payment is rejected and the student sees the status/reason; no receipt is issued. |
| DOC-07 | As Cashier, record a walk-in payment for an eligible request. | Payment is recorded with method/reference/amount, linked status updates, and receipt is generated. |
| DOC-08 | Attempt walk-in payment for a request not eligible for payment or use an invalid amount/reference. | The request is rejected without creating a payment. |
| DOC-09 | As Cashier, issue a permitted refund with a reason. | Payment becomes Refunded with audit details and the transaction history reflects the refund. |
| DOC-10 | Open a valid receipt and try an invalid receipt/payment ID. | Valid receipt details print/display; invalid ID is denied without exposing another studentâ€™s receipt. |

## Grades

| ID | Scenario and steps | Expected result |
| --- | --- | --- |
| GRD-01 | As Department staff, open Grade Entry and select a section and subject in your academic department. | The roster lists only enrolled students in that class, sorted by last name. |
| GRD-02 | Search the selected roster by part of a student's name and by student number. | The roster filters matching students without changing grade data. |
| GRD-03 | Enter a valid grade for a student and save it. | Grade enters Registrar Review and remains hidden from the student. |
| GRD-04 | Enter a grade outside 1.00–5.00 or submit a student outside the selected department class. | The request is rejected and no grade is changed. |
| GRD-05 | As Registrar, approve a submitted grade. | Grade becomes Released and is visible to its student. |
| GRD-06 | As Registrar, return a grade without a reason, then with a reason. | Returning without a reason is rejected. A valid return sets Department Revision and displays the reason to the Department. |
| GRD-07 | As Department staff, correct and resubmit a returned grade. | The updated grade returns to Registrar Review and the old return reason is cleared. |
| GRD-08 | As Student, view grades and select an available term. | Only the signed-in student's Released grades are shown. |
| GRD-09 | Try to save a grade as a Student or release a grade as a non-Registrar. | The server denies each unauthorized action. |
## Communications and notifications

| ID | Scenario and steps | Expected result |
| --- | --- | --- |
| MSG-01 | As Registrar, publish a valid announcement to an available audience. | Announcement is published and notification(s) are created for the target audience. |
| MSG-02 | As Registrar, send a direct message to a valid student number. | Message is stored and appears in the target Studentâ€™s notifications. |
| MSG-03 | Send a message to an unknown student number or submit blank title/body. | Action is rejected and no message is delivered. |
| MSG-04 | As Registrar, deactivate an active announcement. | Announcement is no longer active/visible as current; existing records remain consistent. |
| MSG-05 | As any supported role, mark one notification read. | Only the current userâ€™s notification is marked read. |
| MSG-06 | Mark all notifications read. | All and only the signed-in userâ€™s notifications become read. |
| MSG-07 | Modify notification ID to another userâ€™s notification. | The action cannot mark another userâ€™s notification read. |

## Reports, monitoring, and admin screens

| ID | Scenario and steps | Expected result |
| --- | --- | --- |
| ADMINS-01 | As Admin, open Monitoring and filter by role/action/date. | Displayed rows match selected filters; summary counts remain consistent with stored audit data. |
| ADMINS-02 | As Admin, open audit reports and export a supported report. | Report rows reflect stored audit data and export is readable/consistent with the filtered view. |
| ADMINS-03 | As Registrar, open Registrar Reports and filter term/program. | Report contains only the selected scope and displays consistent aggregate values. |
| ADMINS-04 | As Department user, open Department Reports and choose a permitted program/year filter. | Results are scoped to the signed-in userâ€™s department. |
| ADMINS-05 | Open Admin User Management, add/edit/disable a test user through the visible UI, reload the page and check the database/session. | Current UI interactions are client-side simulations; verify that no persisted user operation is assumed. Record a product issue if persistence is expected. |
| ADMINS-06 | Change general settings/academic structure using the System Settings UI, reload, and inspect storage. | Most such actions are currently simulated and do not persist. The enrollment-period form is the exception and should persist. |
| ADMINS-07 | As Admin, set enrollment dates through System Settings, reload the page and inspect Student Enrollment. | Stored dates remain and are displayed to students. |
| ADMINS-08 | In User Management, set a unique email for an account and reload the page. | The email remains saved, the account can sign in using it, and Forgot Password recognizes it. |

## Navigation, session, and data safety

| ID | Scenario and steps | Expected result |
| --- | --- | --- |
| SYS-01 | On desktop and mobile viewport, open each role page and use the shared sidebar navigation. | Navigation remains usable and links only to modules allowed for that role. |
| SYS-02 | Use each roleâ€™s Sign out action, then press browser Back or reopen a protected URL. | Session is ended and protected information is not accessible from history/reload. |
| SYS-03 | Submit unexpected HTTP method or malformed IDs to each write controller. | Request is rejected with an appropriate client error; no invalid row is changed. |
| SYS-04 | Inspect applicant upload storage and request a file directly from the web root. | Uploaded files are not directly downloadable; staff access is through the authorized document controller. |
| SYS-05 | Confirm the admission checklist has no exam scheduling/questionnaire action after migration. | No entrance exam flow is presented; admissions decisions are based on documents and applicant confirmation. |
