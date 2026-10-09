# SSIS System Flow

This file turns the main SSIS workflows into flowcharts. Each chart is designed to be copied into a diagram tool or used as a starting point for a more detailed design. The labels name the decision and the branch conditions; database writes are shown in square nodes.

## 1. How a request travels through the application

```mermaid
flowchart LR
    U[User action in a page] --> P[PHP page gathers input]
    P --> C[Controller receives request]
    C --> A{Signed in, correct role,<br/>POST and CSRF valid?}
    A -- No --> E[Reject with access or validation error]
    A -- Yes --> V[Validate fields and current workflow state]
    V --> B{Valid and allowed?}
    B -- No --> E
    B -- Yes --> S[Service applies business rules]
    S --> M[Model reads or changes records]
    M --> D[(MySQL database)]
    D --> N[Create related notification/audit rows if needed]
    N --> R[Controller returns success data]
    R --> UI[Page updates affected row, badge, modal, or status]
    D -. database/transaction failure .-> F[Rollback where transaction is used;<br/>return friendly error]
    F --> E
```

### What the boxes mean

- **Page:** the screen the user sees under `php/<role>/`, or the public `application.php` form.
- **Controller:** checks the request and sends it to the relevant workflow. Controllers live in `controller/`.
- **Service:** validates and coordinates workflow-specific work. Services live in `services/`.
- **Model:** reads and writes application records. Models live in `models/`.
- **Database:** stores the authoritative state. The next action is decided from the saved state, not from the appearance of a button.
- **UI update:** a successful response updates the relevant parts of the current screen. If validation fails, the operation should not be presented as successful.

## 2. Sign-in and role routing

```mermaid
flowchart TD
    Start([Open sign-in]) --> Enter[Enter username and password]
    Enter --> Check[Authenticate account and password]
    Check --> Valid{Credentials valid,<br/>account Active,<br/>attempt not locked?}
    Valid -- No --> Fail[Show sign-in error;<br/>record failed attempt]
    Fail --> Enter
    Valid -- Yes --> Session[Create authenticated session and CSRF token]
    Session --> Role{Account role}
    Role -- Applicant --> Applicant[Applicant dashboard]
    Role -- Student --> Student[Student dashboard]
    Role -- Registrar --> Registrar[Registrar enrollment queue]
    Role -- Department --> Department[Department clearance queue]
    Role -- Cashier --> Cashier[Cashier dashboard]
    Role -- Admin --> Admin[Admin user management]
    Applicant --> Request[Each protected page checks role,<br/>account status, and session timeout]
    Student --> Request
    Registrar --> Request
    Department --> Request
    Cashier --> Request
    Admin --> Request
    Request --> Allowed{Page allows this role<br/>and account/session valid?}
    Allowed -- No --> Deny[Block action or redirect to sign-in]
    Allowed -- Yes --> Page[Show role-specific page and sidebar]
```

## 3. Admission: public form to Student account

```mermaid
flowchart TD
    Start([Applicant opens application form]) --> Input[Enter name, email, contact,<br/>address, and program]
    Input --> Valid{Required fields present,<br/>email valid and unused,<br/>program ID valid?}
    Valid -- No --> Error[Return field-friendly error;<br/>do not create application]
    Error --> Input
    Valid -- Yes --> Save[Begin transaction]
    Save --> App[(Insert admission_applications<br/>with Pending status and application number)]
    App --> User[(Create Applicant user account<br/>and link it to application)]
    User --> Notify[Notify applicant and active<br/>Admin/Registrar accounts]
    Notify --> Commit[Commit transaction; sign applicant in]
    Commit --> Checklist[Applicant opens dashboard<br/>and sees program requirements]
    Checklist --> Upload{Requirement still active<br/>and belongs to this program?}
    Upload -- No --> BadReq[Reject submission]
    Upload -- Yes --> Method{Choose submission method}
    Method -- Online --> File{PDF/JPG/PNG and <= 5 MB?}
    File -- No --> FileError[Reject file and keep requirement incomplete]
    FileError --> Method
    File -- Yes --> DocSave[(Save admission_documents<br/>as Pending review)]
    Method -- Face to Face --> DocSave
    DocSave --> Sync{All active requirements<br/>confirmed?}
    Sync -- No --> DocsRequired[Application status becomes<br/>Documents Required]
    Sync -- Yes --> UnderReview[Application status becomes<br/>Under Review]
    DocsRequired --> RegistrarReview[Admin/Registrar reviews documents/application]
    UnderReview --> RegistrarReview
    RegistrarReview --> Decision{Document decision}
    Decision -- Resubmit Needed --> AskResubmit[Save instructions;<br/>notify applicant]
    AskResubmit --> Checklist
    Decision -- Confirmed --> AllDocs{All active requirements confirmed?}
    AllDocs -- No --> RegistrarReview
    AllDocs -- Yes --> AppDecision{Application decision}
    AppDecision -- Documents Required --> DocsRequired
    AppDecision -- Under Review --> UnderReview
    AppDecision -- Rejected --> Rejected[(Save Rejected;<br/>notify applicant)]
    AppDecision -- Approved --> Approved[(Save Approved;<br/>notify applicant)]
    Approved --> Confirm{Applicant confirms<br/>and documents remain confirmed?}
    Confirm -- No --> Wait[Remain Approved; wait for applicant]
    Wait --> Confirm
    Confirm -- Yes --> Convert[(Create student profile and student number;<br/>change user role/username;<br/>set application Enrolled)]
    Convert --> StudentHome[Open Student dashboard]
```

**Records involved:** `admission_applications`, `users`, `admission_document_requirements`, `admission_documents`, `students`, `number_sequences`, `notifications`.

## 4. Enrollment request and Registrar decision

```mermaid
flowchart TD
    Start([Student opens Enrollment]) --> Term{Current term exists?}
    Term -- No --> NoTerm[Show that enrollment is unavailable]
    Term -- Yes --> Window{Opening and closing times configured<br/>and current time is within window?}
    Window -- No --> Closed[Show enrollment closed/unconfigured state]
    Window -- Yes --> Subjects[Show active subjects for program,<br/>year level, and current semester]
    Subjects --> Select[Student selects subjects]
    Select --> Check{Student profile exists;<br/>year level valid;<br/>one or more eligible subjects;<br/>units within limit;<br/>prerequisites passed;<br/>no active request for term?}
    Check -- No --> SubmitError[Show reason; do not create request]
    SubmitError --> Select
    Check -- Yes --> Request[(Create Pending enrollment<br/>and enrollment_subjects rows)]
    Request --> Notify[Notify Registrar and student]
    Notify --> Queue[Registrar opens current-term queue]
    Queue --> Decision{Registrar action}
    Decision -- Reject --> Reason{Reason supplied<br/>and <= 500 characters?}
    Reason -- No --> RejectError[Reject action; keep request unchanged]
    RejectError --> Queue
    Reason -- Yes --> Rejected[(Set Rejected and save reason;<br/>notify student)]
    Decision -- Review --> UnderReview[(Pending to Under Review;<br/>notify student)]
    UnderReview --> Adjust{Need to adjust subjects?}
    Adjust -- Yes --> SaveSubjects[(Save eligible subject selection;<br/>notify student)]
    Adjust -- No --> Approve
    SaveSubjects --> Approve[Registrar approves request]
    Approve --> Approved[(Under Review to Approved;<br/>notify student)]
    Approved --> Finalize{Registrar confirms final enrollment?}
    Finalize -- Yes --> Enrolled[(Approved to Enrolled;<br/>record enrolled time and notify student)]
    Finalize -- No --> Wait[Request remains Approved]
    Wait --> Finalize
    Enrolled --> Done([Student is enrolled for the term])
    Rejected --> Retry{Student wants to try again?}
    Retry -- Yes --> Term
    Retry -- No --> Done
```

**Conditions to keep beside the chart:** a rejected/cancelled prior request does not block a new request; other existing request statuses do. Only the student who owns a `Pending` request can cancel it. Prerequisites count only when a grade is both `Released` and `Passed`.

**Records involved:** `terms`, `students`, `subjects`, `subject_prerequisites`, `grades`, `enrollments`, `enrollment_subjects`, `notifications`, `audit_logs`.

## 5. Grade submission, review, and release

```mermaid
flowchart TD
    Start([Department staff selects term, class,<br/>section, and subject]) --> Class{Academic department;<br/>active section/subject;<br/>current term?}
    Class -- No --> NoClass[Do not show an eligible roster]
    Class -- Yes --> Roster[Show students enrolled in the class<br/>and attached to the subject]
    Roster --> Enter[Enter grade from 1.00 through 5.00]
    Enter --> Allowed{Student belongs to this department/class<br/>and grade is new or in Department Revision?}
    Allowed -- No --> EntryError[Reject grade save]
    EntryError --> Enter
    Allowed -- Yes --> Save[(Save grade as Registrar Review;<br/>Passed <= 3.00, Failed > 3.00)]
    Save --> Notify[Notify Registrar; write audit event]
    Notify --> Queue[Registrar grade review queue]
    Queue --> Review{Release or return?}
    Review -- Return --> HasReason{Reason supplied?}
    HasReason -- No --> ReviewError[Reject return action]
    ReviewError --> Queue
    HasReason -- Yes --> Revision[(Set Department Revision;<br/>notify entering department user)]
    Revision --> Enter
    Review -- Release --> Released[(Set Released and record reviewer/time;<br/>notify student)]
    Released --> Visible[Grade appears in student's grades<br/>because status is Released]
```

**Records involved:** `enrollments`, `enrollment_subjects`, `sections`, `subjects`, `grades`, `grade_revisions`, `notifications`, `audit_logs`.

## 6. Clearance creation, office review, and summary

Clearance rows/items are generated for the current term when the Student clearance page ensures term clearances. The Student page displays the items and overall status; the approval/rejection actions belong to the office assigned to each requirement.

```mermaid
flowchart TD
    Start([Student or staff opens clearance page]) --> Term{Current term exists?}
    Term -- No --> NoTerm[No current-term clearance to show]
    Term -- Yes --> Ensure[(Ensure a clearance per student;<br/>create items for active requirements;<br/>remove inapplicable Academic department items)]
    Ensure --> Display[Student sees own items and overall status;<br/>staff sees its office queue]
    Display --> Office{Which office owns item?}
    Office -- Department --> DeptScope{Requirement matches staff department<br/>and student program scope?}
    Office -- Registrar --> RegistrarScope{Item assigned to Registrar?}
    Office -- Cashier --> CashierScope{Item assigned to Cashier?}
    DeptScope -- No --> Hide[Do not include item in queue]
    RegistrarScope -- No --> Hide
    CashierScope -- No --> Hide
    DeptScope -- Yes --> Review
    RegistrarScope -- Yes --> Review
    CashierScope -- Yes --> Review[Authorized staff reviews item]
    Review --> Decision{Approve or reject?}
    Decision -- Reject --> Reason{Reason provided?}
    Reason -- No --> Error[Reject action; no save]
    Error --> Review
    Reason -- Yes --> SaveReject[(Save Rejected, reason,<br/>reviewer and time)]
    Decision -- Approve --> CashierApproval{Cashier tuition item<br/>with outstanding balance?}
    CashierApproval -- Yes --> Settled{Balance settled and any supplied<br/>reference matches eligible paid payment?}
    Settled -- No --> Error
    Settled -- Yes --> SaveApprove[(Save Approved, remarks,<br/>reviewer and time)]
    CashierApproval -- No --> SaveApprove
    SaveReject --> Recalc[Recalculate clearance summary]
    SaveApprove --> Recalc
    Recalc --> Status{All active items approved?}
    Status -- Yes --> Cleared[Overall status Cleared]
    Status -- No --> AnyRejected{Any active item rejected?}
    AnyRejected -- Yes --> ActionNeeded[Overall status Action Needed]
    AnyRejected -- No --> InProgress[Overall status In Progress]
    Cleared --> Notify[Notify student and update page]
    ActionNeeded --> Notify
    InProgress --> Notify
```

**Records involved:** `terms`, `students`, `programs`, `clearances`, `clearance_requirements`, `clearance_items`, `document_requests`, `payments`, `notifications`, `audit_logs`.

## 7. Document request and payment transaction

```mermaid
flowchart TD
    Start([Student requests document]) --> Validate{Student profile exists;<br/>document type active;<br/>purpose valid;<br/>1-10 copies;<br/>remarks <= 500 chars?}
    Validate -- No --> Error[Show error; do not save]
    Error --> Start
    Validate -- Yes --> Create[(Create Pending document request)]
    Create --> Fee{Document fee > 0?}
    Fee -- No --> FreePath[No payment row required]
    Fee -- Yes --> Unpaid[(Create linked Unpaid payment<br/>for fee x copies)]
    FreePath --> NotifyReg[Notify Registrar]
    Unpaid --> NotifyReg
    NotifyReg --> Registrar[Registrar reviews request]
    Registrar --> ReviewState{Valid current status transition?}
    ReviewState -- No --> Error
    ReviewState -- Yes --> Next{Request state and fee}
    Next -- Pending --> UnderReview[(Set Under Review)]
    UnderReview --> FeeStep{Fee > 0?}
    FeeStep -- Yes --> ForPayment[(Set For Payment;<br/>notify Cashier)]
    FeeStep -- No --> Processing[(Set Processing)]
    ForPayment --> PaymentChoice{Payment path}
    PaymentChoice -- Student reference --> SubmitRef[Student sends method and reference]
    SubmitRef --> PendingVerification[(Payment Pending Verification)]
    PendingVerification --> CashierReview{Cashier verifies or rejects?}
    CashierReview -- Reject --> PaymentRejected[(Payment Rejected;<br/>save reason and notify student)]
    PaymentRejected --> SubmitRef
    CashierReview -- Verify --> Paid[(Payment Paid;<br/>issue receipt;<br/>request becomes Paid)]
    PaymentChoice -- Walk-in --> WalkIn{Payment Unpaid/Rejected;<br/>method valid;<br/>amount exactly due;<br/>reference if non-cash?}
    WalkIn -- No --> Error
    WalkIn -- Yes --> Paid
    Paid --> Processing
    Processing --> Ready[(Registrar sets Ready for Release)]
    Ready --> Complete[Registrar marks Completed;<br/>optionally records claimant name]
    Complete --> Done([Student sees completed request/receipt])
```

Registrar status transitions are also state-checked: `Pending` to `Under Review`; `Under Review` to `For Payment` if fee-bearing or `Processing` if free; `Paid` to `Processing`; `Processing` to `Ready for Release`; and `Ready for Release` to `Completed`. Rejection is a separate action requiring a reason. Refunds are allowed only for Paid payments attached to requests that are Cancelled or Rejected, and require a reason.

**Records involved:** `document_types`, `students`, `document_requests`, `payments`, `number_sequences`, `notifications`, `audit_logs`.

## 8. Notifications and badges

```mermaid
flowchart LR
    Event[Workflow event succeeds] --> N[(Insert notification for a user<br/>or active role members)]
    N --> Inbox[Recipient opens notifications]
    Inbox --> Read{Mark one or all as read?}
    Read -- No --> Unread[Keep is_read = 0;<br/>count remains in badge]
    Read -- One --> Own{Notification belongs<br/>to signed-in user?}
    Read -- All --> Own
    Own -- No --> Denied[Reject action]
    Own -- Yes --> Update[(Set is_read = 1 and read_at)]
    Update --> Count[Recount that user's unread rows]
    Count --> UI[Update notification list and badge]
    N --> AdmissionBadge[If a Pending admission is created,<br/>Admin/Registrar badge count increases]
    AdmissionBadge --> Poll[Sidebar refreshes count periodically<br/>from the database]
```

## 9. Admin configuration and account management

```mermaid
flowchart TD
    Start([Admin opens User Management or System Settings]) --> Auth{Admin role and active session?}
    Auth -- No --> Deny[Block action]
    Auth -- Yes --> Choice{Choose action}
    Choice -- Add/Edit user --> UserInput[Enter account details and role]
    UserInput --> Role{Selected role}
    Role -- Department --> Dept[Require active department]
    Role -- Applicant --> ApplicantFields[Require applicant profile and active Academic program;<br/>create linked application]
    Role -- Other --> General[Validate username, email,<br/>password for new account]
    Dept --> SaveUser[(Save account)]
    ApplicantFields --> SaveUser
    General --> SaveUser
    SaveUser --> Sync[Update user table and, for Applicant,<br/>pending Admissions badge]
    Choice -- Add/enable reference data --> RefInput[Add or activate supported<br/>department/program/subject/document/clearance data]
    RefInput --> RefRules{Required fields valid;<br/>parent record active where needed?}
    RefRules -- No --> Deny
    RefRules -- Yes --> SaveRef[(Save reference record)]
    Choice -- Select current term --> SelectTerm[Choose existing term]
    SelectTerm --> SaveTerm[(Clear prior is_current flag<br/>and set chosen term current)]
    Choice -- Disable reference row --> Disable{Supported record type<br/>and valid ID/state?}
    Disable -- No --> Deny
    Disable -- Yes --> UpdateActive[(Set is_active)]
    SaveRef --> Refresh[Refresh affected table]
    SaveTerm --> Refresh
    UpdateActive --> Refresh
    Sync --> Refresh
```

## 10. Failure and retry paths to show in diagrams

When drawing a workflow, include these branches wherever a save can happen:

1. **Access failure:** wrong role, expired session, inactive account, wrong method, or invalid CSRF token. Stop before the write.
2. **Business-rule failure:** invalid fields, wrong ownership, inactive reference data, or a disallowed status transition. Return an error and keep the record unchanged.
3. **Stale state:** the row has changed since the page loaded. Re-read/lock the current row and reject a transition that no longer matches its expected status.
4. **Database failure:** roll back any open transaction; do not show a success state.
5. **Success:** commit changes, write any related notification/audit data, return the saved identifiers/status, and update the associated UI.

## 11. Code landmarks for diagram validation

| Flow | Page | Controller/service/model |
| --- | --- | --- |
| Application and admissions | `application.php`, `php/applicant/dashboard.php`, `php/registrar/admissions.php` | `controller/admissionController.php`, `services/AdmissionService.php`, `models/Admission.php` |
| Enrollment | `php/student/enrollment.php`, `php/registrar/enrollment.php` | `controller/enrollmentController.php`, `services/EnrollmentService.php`, `models/Enrollment.php` |
| Grades | `php/department/grades.php`, `php/registrar/grades.php`, `php/student/grades.php` | `controller/gradeWorkflowController.php`, `services/GradesService.php`, `models/Grades.php` |
| Clearance | `php/student/clearance.php`, `php/department/clearance.php`, `php/cashier/clearance.php`, `php/registrar/clearance.php` | `controller/clearanceController.php`, `services/ClearanceService.php`, `models/Clearance.php` |
| Document requests and payments | `php/student/requests.php`, `php/registrar/documentRequest.php`, `php/cashier/payments.php` | `controller/documentRequestController.php`, `controller/paymentsController.php`, related services and models |
| User and settings administration | `php/admin/userManagement.php`, `php/admin/systemSettings.php` | `controller/adminUserController.php`, `controller/adminSettingsController.php` |

For the full field rules and display conditions, see [conditions.md](conditions.md). For each role's broader journey, see [user-flow.md](user-flow.md).
