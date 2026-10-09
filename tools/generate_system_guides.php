<?php
/** Generate the four plain-language SSIS reference PDFs in output/pdf. */
declare(strict_types=1);

final class ManualPdf
{
    private const WIDTH = 595;
    private const HEIGHT = 842;
    private const LEFT = 48;
    private const RIGHT = 48;
    private const BOTTOM = 48;
    private array $pages = [];
    private string $page = '';
    private float $y = 0;
    private string $title;

    public function __construct(string $title)
    {
        $this->title = $title;
        $this->newPage();
        $this->text($title, 25, true, [0.12, 0.22, 0.19]);
        $this->y -= 8;
        $this->line([0.25, 0.39, 0.32]);
        $this->y -= 18;
    }

    public function heading(string $text, int $level = 1): void
    {
        $this->ensureSpace($level === 1 ? 32 : 23);
        $this->y -= $level === 1 ? 9 : 5;
        $this->text($text, $level === 1 ? 17 : 12, true, $level === 1 ? [0.12, 0.22, 0.19] : [0.20, 0.34, 0.27]);
        $this->y -= 3;
    }

    public function paragraph(string $text, int $size = 9, float $indent = 0): void
    {
        $text = $this->ascii($text);
        $maxChars = max(24, (int) floor((self::WIDTH - self::LEFT - self::RIGHT - $indent) / ($size * 0.51)));
        foreach (explode("\n", wordwrap($text, $maxChars, "\n", true)) as $line) {
            $this->ensureSpace($size + 5);
            $this->text($line, $size, false, [0.16, 0.18, 0.17], self::LEFT + $indent);
            $this->y -= $size + 4;
        }
        $this->y -= 3;
    }

    public function bullet(string $text, int $size = 9): void
    {
        $this->paragraph('- ' . $text, $size, 10);
    }

    public function rule(): void
    {
        $this->y -= 3;
        $this->line([0.83, 0.86, 0.83]);
        $this->y -= 8;
    }

    public function pageBreak(): void
    {
        $this->pages[] = $this->page . $this->footer(count($this->pages) + 1);
        $this->newPage();
    }

    public function save(string $path): void
    {
        if ($this->page !== '') $this->pages[] = $this->page . $this->footer(count($this->pages) + 1);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $fontRegular = 3;
        $fontBold = 4;
        $next = 5;
        foreach ($this->pages as $content) {
            $contentId = $next++;
            $pageId = $next++;
            $objects[$contentId] = '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream";
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '] /Resources << /Font << /F1 ' . $fontRegular . ' 0 R /F2 ' . $fontBold . ' 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $kids[] = $pageId . ' 0 R';
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        $objects[$fontRegular] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[$fontBold] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        ksort($objects);
        $pdf = "%PDF-1.4\n% SSIS Manual\n";
        $offsets = [0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $maxId; $i++) $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
        $pdf .= 'trailer << /Size ' . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        if (file_put_contents($path, $pdf) === false) throw new RuntimeException('Could not write PDF: ' . $path);
    }

    private function newPage(): void
    {
        $this->page = '';
        $this->y = self::HEIGHT - 42;
        $this->drawText('PAXTON UNIVERSITY  |  STUDENT SERVICES INFORMATION SYSTEM', 7, false, [0.36, 0.43, 0.38], self::LEFT, self::HEIGHT - 25);
        $this->y = self::HEIGHT - 54;
    }

    private function text(string $text, int $size, bool $bold, array $color, float $x = self::LEFT, ?float $y = null): void
    {
        $this->drawText($text, $size, $bold, $color, $x, $y ?? $this->y);
    }

    private function drawText(string $text, int $size, bool $bold, array $color, float $x, float $y): void
    {
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->ascii($text));
        $font = $bold ? 'F2' : 'F1';
        $this->page .= sprintf("%.3f %.3f %.3f rg BT /%s %d Tf %.2f %.2f Td (%s) Tj ET\n", $color[0], $color[1], $color[2], $font, $size, $x, $y, $safe);
    }

    private function line(array $color): void
    {
        $this->page .= sprintf("%.3f %.3f %.3f RG %.2f %.2f m %.2f %.2f l S\n", $color[0], $color[1], $color[2], self::LEFT, $this->y, self::WIDTH - self::RIGHT, $this->y);
    }

    private function ensureSpace(float $height): void
    {
        if ($this->y - $height < self::BOTTOM) $this->pageBreak();
    }

    private function footer(int $pageNumber): string
    {
        $safeTitle = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->ascii($this->title));
        return sprintf("0.45 0.49 0.46 rg BT /F1 7 Tf %.2f 25 Td (%s) Tj ET\n", self::LEFT, $safeTitle)
            . sprintf("0.45 0.49 0.46 rg BT /F1 7 Tf %.2f 25 Td (Page %d) Tj ET\n", self::WIDTH - self::RIGHT - 35, $pageNumber);
    }

    private function ascii(string $text): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return $converted === false ? $text : $converted;
    }
}

function tableDescriptions(): array
{
    return [
        'departments' => 'Academic and administrative units. Programs belong to academic departments. Academic clearance requirements apply only to students in that department; administrative department requirements are shared.',
        'programs' => 'Courses or degree programs offered by a department. Student and admission records point to a program.',
        'sections' => 'Class groups within a program and year level. Used to organize enrollment and grade rosters.',
        'terms' => 'Academic periods. The current term also holds the allowed enrollment opening and closing dates.',
        'subjects' => 'Subjects offered under programs, including units, year level, semester, and active state.',
        'subject_prerequisites' => 'Links a subject to a subject that must be passed first.',
        'users' => 'Login accounts for all system roles. Holds the username, email, password hash, role, and account status.',
        'students' => 'Student profile and academic identity linked to a user account and program.',
        'admission_applications' => 'Prospective student applications, their application status, program, contact details, and applicant account link.',
        'admission_document_requirements' => 'Required documents configured by Admin for each program.',
        'admission_documents' => 'Document requirement submissions and review decisions for an application. The file can be online or recorded as face-to-face.',
        'enrollments' => 'Student enrollment requests for a term and their review status.',
        'enrollment_subjects' => 'Subjects selected for an enrollment request.',
        'grades' => 'Student subject grades entered by the academic department, reviewed by the Registrar, and released to students after approval.',
        'grade_revisions' => 'History of grade corrections, including the old/new value and reason.',
        'clearance_requirements' => 'Rules that define which offices or obligations students need to clear. Academic department requirements apply to students in the matching program department; administrative requirements apply to all students.',
        'clearances' => 'A student clearance record for a particular academic term.',
        'clearance_items' => 'Individual office decisions and evidence attached to a student clearance.',
        'document_types' => 'Catalog of documents a student may request, with fees and availability.',
        'document_requests' => 'Student requests for official documents, their processing state, purpose, quantity, and fee.',
        'payments' => 'Payments linked to document requests or clearance fees, including verification, receipt, and refund information.',
        'notifications' => 'Messages shown to a particular account, with read state and a destination link.',
        'announcements' => 'Official messages published by staff for selected audiences.',
        'audit_logs' => 'Security and administrative history: who performed an action, what changed, and when.',
        'login_attempts' => 'Individual login history entries used for security monitoring and audit.',
        'auth_attempt_counters' => 'Current failed-attempt totals and cooldown time. Identity values are stored as hashes.',
        'settings' => 'System-wide configurable key/value settings such as lockout duration and enrollment limits.',
        'number_sequences' => 'Yearly counters used to create unique application, enrollment, document-request, payment, and student numbers.',
    ];
}

function fieldDescription(string $field, string $table): string
{
    $map = [
        'id' => 'Unique row ID.', 'username' => 'Login name; for students this is the student number.',
        'email' => 'Contact/login email address.', 'password_hash' => 'One-way password hash; never stores a readable password.',
        'role' => 'Access role that decides which pages and actions are allowed.', 'status' => 'Current account or workflow state.',
        'department_id' => 'Department linked to this row.', 'program_id' => 'Program/course linked to this row.',
        'user_id' => 'Account linked to this row.', 'student_id' => 'Student profile linked to this row.',
        'term_id' => 'Academic term linked to this row.', 'section_id' => 'Class section linked to this row.',
        'subject_id' => 'Subject linked to this row.', 'enrollment_id' => 'Enrollment request linked to this row.',
        'application_id' => 'Admission application linked to this row.', 'requirement_id' => 'Required document linked to this submission.',
        'reviewer_id' => 'Staff account that reviewed or changed this row.', 'created_by' => 'Account that created this configuration.',
        'updated_by' => 'Account that last changed this configuration.', 'posted_by' => 'Account that published this announcement.',
        'handled_by' => 'Staff account that handled this item.', 'created_at' => 'When the row was created.',
        'updated_at' => 'When the row was last changed.', 'submitted_at' => 'When the user submitted it.',
        'reviewed_at' => 'When staff reviewed it.', 'read_at' => 'When the user read the notification.',
        'last_login' => 'Most recent successful login time.', 'password_changed_at' => 'Most recent password change time.',
        'is_active' => 'Whether this option is currently available.', 'is_current' => 'Whether this is the selected current academic term.',
        'full_name' => 'Person name displayed in the system.', 'first_name' => 'Person first/given name.',
        'middle_name' => 'Optional middle name.', 'last_name' => 'Person family name.', 'student_no' => 'Unique student number.',
        'application_no' => 'Unique application reference shown to applicant and staff.', 'academic_year' => 'Academic year label.',
        'semester' => 'Term part, such as first semester or summer.', 'year_level' => 'Student or subject year level.',
        'units' => 'Credit units assigned to the subject.', 'code' => 'Short identifier used in lists and forms.',
        'name' => 'Human-readable name.', 'title' => 'Short heading shown to users.', 'body' => 'Main message or description.',
        'remarks' => 'Optional notes entered by the user or reviewer.', 'reason' => 'Explanation for a rejection, correction, or refund.',
        'review_message' => 'Instructions shown when a document needs resubmission.', 'file_path' => 'Private stored filename for an uploaded document.',
        'submission_method' => 'Whether the document was uploaded online or received face-to-face.',
        'review_status' => 'Document state such as Pending, Confirmed, or Resubmit Needed.',
        'reviewed_by' => 'Account that made the review decision.', 'contact_number' => 'Phone number for contact.',
        'address' => 'Mailing or residence address.', 'setting_key' => 'Unique name used to look up a system setting.',
        'setting_value' => 'Stored value for that system setting.', 'description' => 'Plain explanation of a setting or item.',
        'failed_attempts' => 'Number of failed actions in the current counting period.', 'locked_until' => 'Time when the temporary lockout ends.',
        'window_started_at' => 'Start time of the current attempt counting period.', 'attempt_type' => 'Whether the counter is for login or password reset.',
        'email_hash' => 'Hashed account or session identity used to avoid storing the raw identity in the limiter.',
        'was_successful' => 'Whether the login attempt succeeded.', 'ip_address' => 'Network address recorded for security auditing.',
        'action' => 'Short name of the action recorded in the audit log.', 'entity' => 'Type of record affected by an audit action.',
        'entity_id' => 'ID of the affected record, when available.', 'notification_type' => 'Category of notification.',
        'is_read' => 'Whether the notification has been read.', 'audience' => 'Role or group that should receive an announcement.',
        'priority' => 'Importance level for an announcement.', 'enrollment_open_at' => 'Earliest date/time students may submit enrollment.',
        'enrollment_close_at' => 'Last date/time students may submit enrollment.', 'receipt_no' => 'Receipt reference given for a payment.',
        'request_no' => 'Unique document request reference.', 'payment_method' => 'How the payment was made.',
        'reference_no' => 'Payment reference supplied or recorded.', 'amount' => 'Money value for the payment or fee.',
        'copies' => 'Number of requested copies.', 'purpose' => 'Reason the student needs the requested document.',
        'is_released' => 'Whether the grade is visible to the student.', 'grade' => 'Current grade value.',
        'old_grade' => 'Grade value before a correction.', 'new_grade' => 'Grade value after a correction.',
        'message' => 'Message or explanation displayed to a user.', 'link' => 'Page destination opened from the notification.',
        'quantity' => 'Number of items or copies requested.', 'is_current' => 'Marks the current term or active selection.',
    ];
    if (isset($map[$field])) return $map[$field];
    if (str_ends_with($field, '_id')) return 'ID of the related ' . str_replace('_', ' ', substr($field, 0, -3)) . '.';
    if (str_ends_with($field, '_at')) return 'Date and time when this event occurred.';
    if (str_starts_with($field, 'is_')) return 'Flag showing whether this item is ' . str_replace('_', ' ', substr($field, 3)) . '.';
    return 'Stores the ' . str_replace('_', ' ', $field) . ' for this ' . str_replace('_', ' ', rtrim($table, 's')) . '.';
}

function buildSchemaPdf(string $root, string $out): void
{
    $pdf = new ManualPdf('SSIS Database Schema Guide');
    $pdf->paragraph('This guide explains the tables and fields in ssis-schema.sql in plain language. The schema is the structure; the PHP code uses it to store applications, enrollment, student services, and system activity.');
    $pdf->heading('How to read a table');
    $pdf->paragraph('An ID is a row identifier. A field ending in _id usually connects one table to another. Status fields show where a workflow is. created_at and updated_at record when a row was made or changed. Primary keys identify rows; foreign keys protect links between rows.');
    $sql = file_get_contents($root . '/ssis-schema.sql');
    preg_match_all('/CREATE TABLE\s+(\w+)\s*\((.*?)\)\s*ENGINE\s*=\s*InnoDB\s*;/is', $sql, $matches, PREG_SET_ORDER);
    foreach ($matches as $tableMatch) {
        $table = $tableMatch[1];
        $pdf->heading($table, 2);
        $pdf->paragraph(tableDescriptions()[$table] ?? 'Stores records used by the ' . str_replace('_', ' ', $table) . ' part of the system.');
        $fields = [];
        foreach (preg_split('/\R/', $tableMatch[2]) as $line) {
            $line = trim($line);
            if (preg_match('/^(PRIMARY|UNIQUE|KEY|CONSTRAINT|CHECK|FOREIGN)\b/i', $line)) continue;
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s+(.+?)(?:,)?$/', $line, $field)) {
                $name = $field[1];
                if (in_array(strtoupper($name), ['PRIMARY', 'UNIQUE', 'KEY', 'CONSTRAINT', 'CHECK'], true)) continue;
                $type = preg_replace('/\s+NOT NULL.*$|\s+NULL.*$|\s+DEFAULT.*$|\s+AUTO_INCREMENT.*$|\s+ON UPDATE.*$/i', '', rtrim($field[2], ','));
                $type = trim($type);
                if (preg_match('/^[A-Z][A-Z0-9_]*(?:\s*\([^)]*\))?/i', $type, $typeMatch)) $type = $typeMatch[0];
                $fields[] = [$name, $type, fieldDescription($name, $table)];
            }
        }
        foreach ($fields as [$name, $type, $purpose]) $pdf->paragraph($name . ' (' . $type . '): ' . $purpose, 8, 8);
        $pdf->rule();
    }
    $pdf->heading('Relationships in brief');
    $pdf->bullet('A user can have a student profile; both share one login account.');
    $pdf->bullet('An application belongs to a program and can link to an Applicant user.');
    $pdf->bullet('Document submissions link an application to an Admin-configured requirement.');
    $pdf->bullet('Enrollment requests link students, terms, and selected subjects.');
    $pdf->bullet('Clearance records group office decisions for a student and term.');
    $pdf->bullet('Academic department clearance items are limited to students in that department; administrative department requirements are shared across programs.');
    $pdf->bullet('Document requests can have payments; payments can create receipts.');
    $pdf->save($out);
}

function buildCodePdf(string $root, string $out): void
{
    $pdf = new ManualPdf('SSIS Code and Function Guide');
    $pdf->paragraph('This catalog explains what each main source file is for and what its named functions do. Inline page scripts also respond to button clicks and form submissions; those are described as page actions rather than listed as separate functions.');
    $pdf->heading('Typical request flow');
    $pdf->paragraph('A page displays a form. The browser sends it to a controller. The controller checks the request, access role, and CSRF token. A service validates the business rules. A model reads or changes the database. The controller or page then shows a toast or redirects the user.');
    $pdf->paragraph('Clearance department rule: academic department requirements are created and displayed only for students whose program belongs to that department. Administrative requirements, such as Library and Student Affairs, remain shared. Department queues, review permissions, dashboard totals, and overall status use the same rule.');

    $overview = [
        'index.php' => 'Public home page; links to sign-in and student application.',
        'login.php' => 'Sign-in screen; submits to authController and displays remaining-attempt or lockout feedback.',
        'forgotPassword.php' => 'Email lookup, new-password fields, show/hide control, and AJAX calls to the reset controller.',
        'application.php' => 'Public application form and one-time applicant credential receipt.',
        'php/includes/auth.php' => 'Starts sessions, defines application path, records auth audit events, and protects role pages.',
        'php/component/sidebar.php' => 'Shared responsive role-aware sidebar and mobile navigation.',
        'js/helperFunction.js' => 'Shared toast display and dismissal functions.',
        'js/private-page-history.js' => 'Prevents browser back navigation from showing a protected page after sign-out.',
        'models/Database.php' => 'Creates the shared PDO database connection.',
        'models/User.php' => 'Checks credentials, counts login failures, updates emails, and records login history.',
        'models/AuthAttemptLimiter.php' => 'Stores hashed identity counters, cooldown state, and user-facing attempt messages.',
        'models/Admission.php' => 'Reads and writes applications, applicant documents, requirements, reviews, and applicant-to-student conversion.',
        'models/Enrollment.php' => 'Reads subjects and terms, creates enrollment requests, checks eligibility, and records review decisions.',
        'models/Clearance.php' => 'Creates term clearances with shared requirements and only the student\'s own academic department requirement. It removes legacy mismatches, scopes queues and review actions, and recalculates overall status.',
        'models/DocumentRequests.php' => 'Creates and moves official document requests through registrar and cashier stages.',
        'models/Payments.php' => 'Handles payment verification, walk-in payments, refunds, receipts, and payment reports.',
        'models/Grades.php' => 'Lists department-owned active classes, validates grade entry and resubmission, and enforces Registrar approval and student release.',
        'models/Notifications.php' => 'Loads a user\'s notifications and marks them read.',
        'models/RegistrarCommunications.php' => 'Publishes announcements, sends direct messages, and returns communication summaries.',
        'models/AuditLog.php' => 'Returns filtered audit rows and summary counts.',
        'models/StudentProfile.php' => 'Loads and updates student contact details and password changes.',
        'models/StudentDashboard.php' => 'Builds student dashboard summaries and recent announcements.',
        'models/CashierDashboard.php' => 'Builds the cashier dashboard queue and totals.',
        'models/RegistrarReports.php' => 'Returns terms, programs, and registrar report data.',
        'models/DepartmentReports.php' => 'Returns department-scoped programs and clearance reports.',
    ];
    $controllers = [
        'controller/authController.php' => 'Accepts login POSTs, calls User::authenticate, returns redirect JSON or an error with attempt/cooldown information.',
        'controller/forgotPasswordController.php' => 'Handles check_email and reset_password actions; validates CSRF, email, password rules, lockout, then updates the hash.',
        'controller/accountEmailController.php' => 'Admin-only email update endpoint; validates CSRF and delegates uniqueness and synchronization to User.',
        'controller/admissionController.php' => 'Routes application submission, applicant confirmation, file submission, document review, requirement maintenance, and application review actions.',
        'controller/admissionDocumentController.php' => 'Serves uploaded admission documents only after checking staff role and document authorization.',
        'controller/enrollmentController.php' => 'Routes student request submission/cancellation and registrar subject review/approval/rejection.',
        'controller/enrollmentWindowController.php' => 'Admin-only handler for the current term enrollment opening/closing dates.',
        'controller/clearanceController.php' => 'Routes student clearance review actions and checks the office/department allowed to act.',
        'controller/documentRequestController.php' => 'Routes student document requests and registrar status/rejection changes.',
        'controller/paymentsController.php' => 'Routes cashier verification, rejection, walk-in payment, refund, and receipt actions.',
        'controller/gradesController.php' => 'Rejects the retired direct-to-student grade release endpoint.',
        'controller/gradeWorkflowController.php' => 'Routes department grade entry/resubmission and registrar release/return with role and CSRF checks.',
        'controller/studentProfileController.php' => 'Routes contact updates and password changes for the signed-in student.',
        'controller/notificationsController.php' => 'Loads and marks notifications for the currently signed-in account.',
        'controller/communicationsController.php' => 'Routes announcement publishing, direct messages, and announcement deactivation.',
        'controller/departmentReportController.php' => 'Returns report data filtered to the signed-in department.',
    ];

    $pdf->heading('Root pages and shared browser files');
    foreach (['index.php', 'login.php', 'forgotPassword.php', 'application.php', 'php/includes/auth.php', 'php/component/sidebar.php', 'js/helperFunction.js', 'js/private-page-history.js'] as $file) {
        $pdf->heading($file, 2);
        $pdf->paragraph($overview[$file] ?? 'Shared page support.');
        $functions = namedFunctions($root . '/' . $file);
        if ($functions) foreach ($functions as $name) $pdf->bullet($name . ': ' . functionPurpose($name, $file), 8);
        else $pdf->bullet('Page flow: display controls, send form/action to its controller, then show a response or navigate.', 8);
    }

    $documented = ['index.php', 'login.php', 'forgotPassword.php', 'application.php', 'php/includes/auth.php', 'php/component/sidebar.php', 'js/helperFunction.js', 'js/private-page-history.js'];
    $supportFiles = array_merge(glob($root . '/*.php') ?: [], glob($root . '/php/includes/*.php') ?: [], glob($root . '/php/component/*.php') ?: [], glob($root . '/js/*.js') ?: []);
    foreach ($supportFiles as $file) {
        $relative = relativePath($root, $file);
        if (in_array($relative, $documented, true)) continue;
        $pdf->heading($relative, 2);
        $pdf->paragraph(supportFilePurpose($relative));
        $functions = namedFunctions($file);
        if ($functions) foreach ($functions as $name) $pdf->bullet($name . ': ' . functionPurpose($name, $relative), 8);
    }

    $pdf->heading('Controllers');
    foreach ($controllers as $file => $purpose) {
        $pdf->heading($file, 2);
        $pdf->paragraph($purpose);
        $actions = controllerActions($root . '/' . $file);
        if ($actions) $pdf->paragraph('Request actions found: ' . implode(', ', $actions) . '.', 8, 8);
        else $pdf->bullet('Flow: validate request and role -> call model/service -> return JSON or redirect.', 8);
    }

    $pdf->heading('Services and their functions');
    foreach (glob($root . '/services/*.php') as $file) {
        $relative = relativePath($root, $file);
        $pdf->heading($relative, 2);
        $pdf->paragraph('Validates module input and coordinates the related model operations. It keeps business rules out of page markup.');
        foreach (namedFunctions($file) as $name) $pdf->bullet($name . ': ' . functionPurpose($name, $relative), 8);
    }

    $pdf->heading('Models and their functions');
    foreach (glob($root . '/models/*.php') as $file) {
        $relative = relativePath($root, $file);
        $pdf->heading($relative, 2);
        $pdf->paragraph($overview[$relative] ?? 'Reads or changes database records for its named area of the system. Functions are called by controllers or services.');
        foreach (namedFunctions($file) as $name) $pdf->bullet($name . ': ' . functionPurpose($name, $relative), 8);
    }

    $pdf->heading('Module pages and support files');
    $pageFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/php', FilesystemIterator::SKIP_DOTS));
    foreach ($pageFiles as $entry) {
        if (!$entry->isFile() || strtolower($entry->getExtension()) !== 'php') continue;
        $file = $entry->getPathname();
        $relative = relativePath($root, $file);
        if (in_array($relative, ['php/includes/auth.php', 'php/component/sidebar.php'], true)) continue;
        $pdf->heading($relative, 2);
        $pdf->paragraph(pagePurpose($relative));
        $functions = namedFunctions($file);
        if ($functions) foreach ($functions as $name) $pdf->bullet($name . ': ' . functionPurpose($name, $relative), 8);
        else $pdf->bullet('Page flow: read data for the signed-in role, render the module view, and submit supported actions to the matching controller.', 8);
    }

    $pdf->heading('Database and setup files');
    foreach (['ssis-schema.sql' => 'Creates the database tables, columns, indexes, and relationships.', 'ssis-seed.sql' => 'Adds sample departments, accounts, terms, subjects, and demonstration records.', 'ssis-applicant-account-migration.sql' => 'Adds Applicant account role/link fields to an older database.', 'ssis-document-admissions-migration.sql' => 'Converts the older examination admission setup to document-based admissions and adds enrollment dates.', 'ssis-auth-security-migration.sql' => 'Adds account email uniqueness and the table used for login/password-reset attempt cooldowns.', 'ssis-department-grading-migration.sql' => 'Replaces faculty grade entry with Department entry and Registrar review in a database that already has the retired faculty workflow.'] as $file => $purpose) {
        $pdf->heading($file, 2);
        $pdf->paragraph($purpose);
    }
    $pdf->heading('tools/create_demo_accounts.php', 2);
    $pdf->paragraph('Development helper that creates demonstration accounts for local setup. It is not part of normal student or staff requests.');
    $pdf->save($out);
}

function namedFunctions(string $path): array
{
    if (!is_file($path)) return [];
    $source = file_get_contents($path);
    preg_match_all('/\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $matches);
    return array_values(array_unique($matches[1]));
}

function functionPurpose(string $name, string $file): string
{
    $label = strtolower(preg_replace('/(?<!^)[A-Z]/', ' $0', $name));
    if ($name === '__construct') return 'receives the database connection or model dependency used by this class.';
    if ($file === 'models/Clearance.php') {
        $clearanceFunctions = [
            'ensureTermClearances' => 'creates term records, assigns shared requirements and only matching academic-department requirements, removes legacy mismatches, then recalculates each overall status.',
            'getStudentClearance' => 'loads one student’s current-term clearance and attaches the requirements that apply to the student’s program department.',
            'getItemsForClearance' => 'returns active shared requirements and matching academic-department items for a student clearance.',
            'getOfficeQueue' => 'loads the selected office queue and limits academic department staff to students in their department.',
            'reviewItem' => 'checks the reviewer’s office and department scope before saving a decision, updating overall status, and notifying the student.',
            'refreshOverallStatus' => 'calculates the student’s overall result using only active requirements that apply to the student.',
        ];
        if (isset($clearanceFunctions[$name])) return $clearanceFunctions[$name];
    }
    if (preg_match('/^(get|find|list|queue|programs|requirements|documents|counts|summary)/i', $name)) return 'loads or returns ' . str_replace('_', ' ', $label) . ' data.';
    if (preg_match('/^(submit|create|save|update|review|approve|reject|cancel|delete|record|transition|publish|send|confirm|verify|refund|mark|deactivate|change)/i', $name)) return 'checks the requested change and saves it through the related model or database transaction.';
    if (preg_match('/^(validate|positive|nonNegative|string|text)/i', $name)) return 'checks and normalizes input before it is used.';
    if (preg_match('/^(notify|audit|writeAudit|recordLogin)/i', $name)) return 'records an audit event or sends the related notification.';
    if (preg_match('/^(status|fail|clear|message|key|lockout|combine)/i', $name)) return 'supports authentication attempt counting and cooldown decisions.';
    if (str_contains(strtolower($file), 'report')) return 'prepares the requested report data and applies its scope/filters.';
    return 'supports the ' . str_replace('_', ' ', basename($file, '.php')) . ' workflow by handling ' . str_replace('_', ' ', $label) . '.';
}

function controllerActions(string $path): array
{
    if (!is_file($path)) return [];
    $source = file_get_contents($path);
    preg_match_all("/\$_POST\[['\"]action['\"]\]\s*\)?\s*===?\s*['\"]([^'\"]+)/", $source, $matches);
    preg_match_all("/\$action\s*===?\s*['\"]([^'\"]+)/", $source, $more);
    return array_values(array_unique(array_merge($matches[1] ?? [], $more[1] ?? [])));
}

function pagePurpose(string $path): string
{
    $file = basename($path, '.php');
    $module = basename(dirname($path));
    $areas = [
        'admin' => 'Admin workspace for account setup, admission requirements, system configuration, audit monitoring, reports, and notifications.',
        'registrar' => 'Registrar workspace for admissions, enrollment reviews, grades, clearance, document requests, communications, reports, and notifications.',
        'student' => 'Student workspace for dashboard, enrollment, grades, clearance, document requests, profile, and notifications.',
        'applicant' => 'Applicant workspace for application progress and required-document submissions.',
        'cashier' => 'Cashier workspace for payment queues, receipts, clearance payments, and notifications.',
        'department' => 'Department workspace for its clearance queue, scoped reports, and notifications.',
    ];
    return ($areas[$module] ?? 'Shared page for sign-in, sign-out, or access support.') . ' This page renders controls and sends supported changes to its matching controller.';
}

function supportFilePurpose(string $path): string
{
    $name = basename($path);
    $known = [
        'logout.php' => 'Ends the signed-in session and returns the user to the public entry point.',
        'head.php' => 'Shared page metadata, fonts, JavaScript library, and brand colors.',
        'notifications-page-data.php' => 'Loads shared role-aware notification data used by notification pages.',
        'notifications-list.php' => 'Renders the shared notification list and read controls.',
        'audit-page-data.php' => 'Collects the filters and data shared by audit pages.',
        'audit-monitoring-rows.php' => 'Renders the filtered audit rows for system monitoring.',
        'audit-report-rows.php' => 'Renders filtered audit rows for report pages.',
    ];
    if (isset($known[$name])) return $known[$name];
    if (str_ends_with($path, '.js')) return 'Shared browser-side behavior used by public or role-specific pages.';
    return pagePurpose($path);
}

function relativePath(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen(rtrim($root, '/\\')) + 1));
}

function buildTestsPdf(string $root, string $out): void
{
    $pdf = new ManualPdf('SSIS Manual Test Cases');
    $pdf->paragraph('These are manual checks for a development/test database. They are test instructions and expected outcomes, not evidence that tests were executed. Use separate accounts for each role and record Pass, Fail, or Blocked.');
    $pdf->heading('Before testing');
    $pdf->bullet('Load ssis-schema.sql and ssis-seed.sql, or apply the needed migrations to a backed-up development database.');
    $pdf->bullet('Prepare active Admin, Registrar, Cashier, Department, Student, and Applicant accounts, plus an active term and program.');
    $pdf->bullet('For admissions, configure active requirements and have valid and invalid sample upload files. For enrollment, prepare open and closed date windows.');
    $pdf->bullet('Keep the browser console/network panel open and record the tester, date, outcome, and issue notes.');
    $markdown = file_get_contents($root . '/docs/TEST_CASES.md');
    $section = '';
    foreach (preg_split('/\R/', $markdown) as $line) {
        if (preg_match('/^##\s+(.+)/', trim($line), $heading)) {
            $section = trim($heading[1]);
            $pdf->heading($section);
            continue;
        }
        if (preg_match('/^\|\s*([A-Z][A-Z0-9]+-\d+)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*$/', trim($line), $case)) {
            $pdf->heading($case[1], 2);
            $pdf->paragraph('Try this: ' . trim($case[2]));
            $pdf->paragraph('Expected: ' . trim($case[3]), 8, 8);
        }
    }
    $pdf->save($out);
}

function buildFlowsPdf(string $out): void
{
    $pdf = new ManualPdf('SSIS Module Flows: Happy and Unhappy Paths');
    $pdf->paragraph('This is a plain-language companion to README.md. Each module has two separate paths: the happy path shows a normal successful task; the unhappy path shows a common problem and how the system should respond.');
    $pdf->heading('How the application is organized');
    $pdf->paragraph('Pages show information and forms. Controllers receive requests and check access. Services validate the business rules. Models read and write database records. The shared sidebar provides role-aware navigation; helperFunction.js provides the common toast messages.');
    $pdf->heading('Role entry pages', 2);
    foreach ([
        'Visitor: index.php, login.php, forgotPassword.php, application.php.',
        'Applicant: php/applicant/dashboard.php for application state and document checklist.',
        'Student: php/student/dashboard.php; enrollment, grades, clearance, requests, profile, and notifications.',
        'Registrar: php/registrar/enrollment.php; admissions, enrollment review, grades, clearance, document requests, communications, and reports.',
        'Cashier: php/cashier/dashboard.php; payments, receipts, clearance payments, and notifications.',
        'Department: php/department/clearance.php; assigned-department clearance and reports.',
        'Admin: php/admin/userManagement.php; account email, admissions requirements, settings, monitoring, and reports.',
    ] as $role) $pdf->bullet($role);
    $pdf->rule();
    $modules = [
        ['Public access, login, and password reset',
            ['Visitor opens the home page and chooses Login.', 'User enters a valid username/student number or email and password.', 'The system checks account status and routes the user to the correct role page.', 'For forgot password, the user enters an active account email, chooses a valid new password, and signs in with it.'],
            ['Unknown, inactive, or wrong credentials are rejected with a toast.', 'Failed login attempts are counted per account and browser session; after five wrong tries, the page shows a cooldown countdown.', 'Correct credentials can still sign in during a login lockout.', 'Unknown email, weak password, or mismatched confirmation stops a password reset.']],
        ['Applicant application and admissions',
            ['Visitor completes the application with a unique email and program.', 'The system creates an application and Applicant account, then shows its credentials once.', 'Applicant uploads required files or submits documents face-to-face.', 'Admin/Registrar checks each document, confirms it or asks for resubmission with guidance.', 'When all active requirements are confirmed, staff approves the application; applicant confirms and becomes a Student.'],
            ['Missing/invalid fields, invalid program, or duplicate email prevents application creation.', 'Disallowed/oversized files are rejected.', 'Staff cannot approve while any required document is missing or unconfirmed.', 'An unauthorized role cannot review or change requirements.']],
        ['Student enrollment',
            ['Admin sets the current term enrollment opening/closing times.', 'Student chooses eligible subjects for their program, year level, and term.', 'System checks prerequisite, unit limit, duplicate request, and date range.', 'Registrar reviews the request and approves it; the student sees the latest status.'],
            ['Submission before opening or after closing is blocked on the server.', 'Empty, ineligible, duplicate, or over-limit subject selections are rejected.', 'Registrar cannot add an ineligible subject or change a request in a disallowed state.']],
        ['Clearance',
            ['Student views current-term shared office requirements and the academic clearance requirement for the student\'s own program department.', 'Each office reviews only items assigned to it; academic department queues include only students in that department.', 'Staff approves or rejects with required reason/reference.', 'The system recalculates overall clearance using only requirements that apply to the student and notifies the student.'],
            ['A CCS student does not receive a CBA academic clearance requirement, and a CBA student does not receive CCS academic clearance.', 'Opening current-term clearance removes legacy academic requirements assigned to students in another department.', 'Missing rejection explanation or required payment reference blocks the decision.', 'Department users cannot change another department\'s items or another academic department\'s student clearance.', 'Bad ID, role, or CSRF token causes rejection without a database change.']],
        ['Student document requests',
            ['Student selects an active document type and enters purpose and copy count.', 'System creates a numbered request and calculates its fee.', 'Registrar advances the request through processing or rejects with a reason.', 'Cashier verifies the related payment; Registrar completes processing/pickup when allowed.'],
            ['Invalid document type, quantity, or missing purpose is rejected.', 'Out-of-order status transitions and invalid payment states are blocked.', 'Student cannot view another student\'s request.']],
        ['Cashier payments and receipts',
            ['Cashier opens a payment queue item or eligible walk-in item.', 'Cashier verifies a valid reference or records an eligible walk-in payment.', 'The system records the transaction, updates linked request/clearance status, and creates a receipt.', 'Cashier opens or prints the receipt; student sees the updated status.'],
            ['Invalid references, amounts, or ineligible items are rejected with no receipt.', 'Refund requires a permitted state and a reason.', 'Invalid or another student\'s receipt ID is denied.']],
        ['Grades',
            ['Department staff select an active section and subject from their department and term.', 'The system lists the enrolled students in last-name order; staff may search by name or student number.', 'Department staff enter and save a grade, which goes directly to Registrar review.', 'The Registrar releases the grade or returns it to the Department with a reason.', 'Department staff correct and resubmit returned grades; students see grades only after release.'],
            ['Out-of-range grades and students outside the selected department class are rejected.', 'Grades waiting for Registrar review or already released cannot be overwritten by the Department.', 'A Registrar return requires a reason.', 'A grade is not shown to a student until Registrar release.']],
        ['Announcements and notifications',
            ['Registrar publishes an announcement to an allowed audience or sends a direct message to a student.', 'The system creates notifications for recipients.', 'Each user reads or marks only their own notifications as read.'],
            ['Blank message data or unknown student number is rejected.', 'A user cannot mark another account\'s notification as read.']],
        ['Reports and monitoring',
            ['Admin filters audit/monitoring views; Registrar and Department users open reports for their allowed scope.', 'Supported exports use the selected report rows.'],
            ['Role/department filters prevent access to out-of-scope data.', 'Reports may be empty when module workflows have not yet created records.']],
        ['Admin settings and account email',
            ['Admin saves a unique account email for an existing account.', 'Admin configures enrollment dates for the current term; students then see the saved date range.'],
            ['Duplicate or malformed emails are rejected.', 'Most general account and academic-structure controls remain simulated; do not assume they persist. The email section and enrollment window are the working exceptions.']],
    ];
    foreach ($modules as [$name, $happy, $unhappy]) {
        $pdf->heading($name);
        $pdf->heading('Happy path', 2);
        foreach ($happy as $step) $pdf->bullet($step);
        $pdf->heading('Unhappy path', 2);
        foreach ($unhappy as $step) $pdf->bullet($step);
        $pdf->rule();
    }
    $pdf->heading('Current known limitation');
    $pdf->paragraph('The forgot-password flow checks that an email exists but does not verify mailbox ownership through an email link or code. Anyone who knows a registered email could reset that account.');
    $pdf->heading('Database setup notes');
    $pdf->paragraph('For a new local database, import ssis-schema.sql and then ssis-seed.sql. For an existing database, apply the applicant-account, document-admissions, and auth-security migrations when each is needed. If the retired faculty grading migration was already applied, apply ssis-department-grading-migration.sql once to convert the database to department grade entry.');
    $pdf->paragraph('Admin email management and enrollment dates are connected to database controllers. Some general account and academic-structure controls in the Admin screens are still client-side simulations and do not save changes.');
    $pdf->save($out);
}

$root = dirname(__DIR__);
$output = $root . '/output';
if (!is_dir($output) && !mkdir($output, 0755, true) && !is_dir($output)) throw new RuntimeException('Cannot create output folder.');
buildSchemaPdf($root, $output . '/SSIS_Schema_Tables_and_Fields.pdf');
buildCodePdf($root, $output . '/SSIS_Code_and_Function_Explanation.pdf');
buildTestsPdf($root, $output . '/SSIS_Test_Cases.pdf');
buildFlowsPdf($output . '/SSIS_ReadMe_Module_Flows.pdf');
echo "Generated four SSIS guide PDFs in output." . PHP_EOL;
