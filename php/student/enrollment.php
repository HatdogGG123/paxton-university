<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('student');
require_once __DIR__ . '/../../services/EnrollmentService.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$userId = (int) $_SESSION['user']['id'];
$enrollment = new Enrollment(Database::getConnection());
$controller = new EnrollmentService($enrollment);
$notice = $_SESSION['enrollment_notice'] ?? null;
unset($_SESSION['enrollment_notice']);
$submittedSubjects = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new DomainException('This form expired. Refresh the page and try again.');
        }
        if (($_POST['action'] ?? '') === 'submit_request') {
            $number = $controller->submitStudentRequest($userId, $_POST);
            $_SESSION['enrollment_notice'] = ['type' => 'success', 'message' => 'Enrollment request ' . $number . ' was submitted.'];
            header('Location: enrollment.php');
            exit;
        }
        if (($_POST['action'] ?? '') === 'cancel_request') {
            $controller->cancelStudentRequest($userId, $_POST['enrollment_id'] ?? null);
            $_SESSION['enrollment_notice'] = ['type' => 'success', 'message' => 'Your pending enrollment request was cancelled.'];
            header('Location: enrollment.php');
            exit;
        }
        throw new DomainException('Choose a valid enrollment action.');
    } catch (DomainException $exception) {
        $notice = ['type' => 'error', 'message' => $exception->getMessage()];
        $submittedSubjects = is_array($_POST['subject_ids'] ?? null) ? $_POST['subject_ids'] : [];
    } catch (Throwable $exception) {
        error_log('SSIS enrollment action failed: ' . $exception->getMessage());
        $notice = ['type' => 'error', 'message' => 'We could not process this enrollment action. Please try again.'];
    }
}
try {
    $student = $enrollment->getStudentContext($userId);
    $term = $enrollment->getCurrentTerm();
    $unitLimit = $enrollment->getUnitLimit();
    if ($student === null) {
        http_response_code(404);
        exit('No student profile is linked to this account. Please contact the Registrar.');
    }
    $yearLevel = filter_input(INPUT_GET, 'year_level', FILTER_VALIDATE_INT);
    $yearLevel = ($yearLevel && $yearLevel >= 1 && $yearLevel <= (int) $student['year_level']) ? $yearLevel : (int) $student['year_level'];
    $request = $term ? $enrollment->getLatestStudentRequest((int) $student['student_id'], (int) $term['id']) : null;
    $subjects = $term ? $enrollment->getAvailableSubjects((int) $student['program_id'], $yearLevel, $term['semester']) : [];
} catch (Throwable $exception) {
    error_log('SSIS enrollment page load failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Enrollment is temporarily unavailable. Please try again later.');
}
$esc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$studentName = trim(implode(' ', array_filter([$student['first_name'], $student['middle_name'], $student['last_name']], static fn($part): bool => $part !== null && $part !== '')));
$enrollmentWindowOpen = $term !== null && !empty($term['enrollment_open_at']) && !empty($term['enrollment_close_at']) && time() >= strtotime($term['enrollment_open_at']) && time() <= strtotime($term['enrollment_close_at']);
$canSubmit = $enrollmentWindowOpen && ($request === null || in_array($request['status'], ['Rejected', 'Cancelled'], true));
$statusStyle = ['Pending' => 'bg-blue-100 text-blue-800', 'Under Review' => 'bg-blue-100 text-blue-800', 'Approved' => 'bg-teal-100 text-teal-800', 'Rejected' => 'bg-red-100 text-red-800', 'Enrolled' => 'bg-emerald-100 text-emerald-800', 'Cancelled' => 'bg-gray-100 text-gray-700'];
$selectedIds = $submittedSubjects ?: ($request && $request['status'] === 'Rejected' ? array_map(static fn($s): string => (string) $s['id'], $request['subjects']) : []);
$selectedUnits = 0.0;
foreach ($subjects as $subject) {
    if (in_array((string) $subject['id'], array_map('strval', $selectedIds), true)) {
        $selectedUnits += (float) $subject['units'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Course Enrollment</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-4.0.0.min.js" integrity="sha256-OaVG6prZf4v69dPg6PhVattBXkcOWQB62pdZ3ORyrao=" crossorigin="anonymous"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            primary: '#344e41',
                            secondary: '#588157',
                            accent: '#dad7cd',
                            dark: '#202020',
                        }
                    }
                },
            },
        }
    </script>
</head>

<body class="bg-gray-50 font-sans text-brand-dark antialiased min-h-screen flex flex-col">
    <?php include __DIR__ . '/../component/sidebar.php'; ?>

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <!-- Brand Identity -->
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 bg-brand-primary rounded-md flex items-center justify-center text-white font-bold text-lg shadow-xs">
                        P
                    </div>
                    <div>
                        <span class="text-base font-bold text-brand-dark tracking-tight block leading-none">PAXTON</span>
                        <span class="text-[10px] uppercase font-semibold text-gray-500 tracking-widest block mt-0.5">University SSIS</span>
                    </div>
                </div>

                <!-- Desktop Navigation Links -->
                <nav class="hidden lg:flex items-center space-x-1">
                    <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Dashboard</a>
                    <a href="enrollment.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20">Enrollment</a>
                    <a href="grades.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Grades</a>
                    <a href="clearance.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Clearance</a>
                    <a href="requests.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Requests</a>
                    <a href="profile.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Profile</a>
                    <a href="notifications.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Notifications</a>
                </nav>

                <!-- Action Controls -->
                <div class="flex items-center space-x-3">
                    <div class="h-5 w-px bg-gray-200 hidden md:block"></div>

                    <!-- User Info / Profile -->
                    <div class="hidden lg:flex items-center space-x-2">
                        <div class="text-right">
                            <p id="nav_student_name" class="text-xs font-semibold text-brand-dark"><?= $esc($studentName) ?></p>
                            <p id="nav_student_id" class="text-[10px] text-gray-500"><?= $esc($student['student_no']) ?></p>
                        </div>
                        <a href="logout.php" id="nav_logout_btn" class="p-2 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 transition-colors" title="Log Out">
                            <i data-lucide="log-out" class="w-5 h-5"></i>
                        </a>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button id="mobile_menu_btn" type="button" class="lg:hidden p-2 rounded-md text-gray-600 hover:text-brand-primary hover:bg-gray-100 focus:outline-none">
                        <svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-menu preview-icon size-5">
                            <path d="M4 5h16" />
                            <path d="M4 12h16" />
                            <path d="M4 19h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile_menu" class="hidden lg:hidden border-t border-gray-200 bg-white px-4 pt-2 pb-4 space-y-1">
            <a href="dashboard.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Dashboard</a>
            <a href="enrollment.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">Enrollment</a>
            <a href="grades.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Grades</a>
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
            <a href="requests.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Requests</a>
            <a href="profile.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Profile</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-brand-dark"><?= $esc($studentName) ?></p>
                    <p class="text-xs text-gray-500"><?= $esc($student['student_no']) ?></p>
                </div>
                <a href="logout.php" class="text-xs text-red-600 font-semibold hover:underline">Log Out</a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Page Header -->
        <div class="flex flex-col lg:flex-row lg:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Course Enrollment</h1>
                <p class="text-sm text-gray-500">Select subjects for the active term and track your enrollment status.</p>
            </div>
            <div class="flex flex-col justify-start md:flex-row items-center gap-2 bg-white border border-gray-200 rounded-md px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs">
                <div class="flex gap-2 w-full justify-start">
                    <i data-lucide="calendar" class="w-4 h-4 text-brand-secondary"></i>
                    <p>Active Term: <strong id="lbl_current_term" class="text-brand-dark"><?= $term ? $esc($term['semester'] . ', AY ' . $term['academic_year']) : 'No active term' ?></strong></p>
                </div>
                <div class="flex gap-2 w-full justify-start items-center">
                    <?php if ($term && !empty($term['enrollment_open_at']) && !empty($term['enrollment_close_at'])): ?>
                        <p class="block mt-2">Enrollment: <strong><?= $esc(date('M j, Y g:i A', strtotime($term['enrollment_open_at']))) ?> – <?= $esc(date('M j, Y g:i A', strtotime($term['enrollment_close_at']))) ?></strong></p>
                    <?php else: ?>
                        <p class="block mt-2 text-amber-700">The Registrar has not set an enrollment period.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Enrollment Status Banner (Conditional View: Active Request / Rejection Reason) -->
        <?php if ($notice): ?><script>
                showToast(<?= json_encode($notice['type'] === 'success' ? 'success' : 'error') ?>, <?= json_encode($notice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
            </script><?php endif; ?>
        <?php if ($request): ?><section id="banner_enrollment_status" class="bg-white border border-gray-200 rounded-md p-6 shadow-xs space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 pb-4">
                    <div class="flex items-start space-x-3">
                        <div class="p-2.5 rounded-md bg-blue-50 text-blue-600 shrink-0 mt-0.5">
                            <i data-lucide="clock" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Current Request Status</span>
                                <span id="badge_enrollment_status" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold <?= $statusStyle[$request['status']] ?? 'bg-gray-100 text-gray-700' ?>"><?= $esc($request['status']) ?></span>
                            </div>
                            <p class="text-sm font-bold text-brand-dark mt-1">Request ID: <span id="lbl_request_no">#<?= $esc($request['enrollment_no']) ?></span></p>
                        </div>
                    </div>

                    <!-- Action Button (Allowed only while Pending) -->
                    <div>
                        <?php if ($request['status'] === 'Pending'): ?><form method="post" action="enrollment.php" onsubmit="return confirm('Cancel this pending enrollment request?');"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="cancel_request"><input type="hidden" name="enrollment_id" value="<?= (int) $request['id'] ?>"><button id="btn_cancel_request" type="submit" class="inline-flex items-center space-x-2 px-3 py-2 border border-red-200 rounded-md text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 transition-colors">
                                    <i data-lucide="x-circle" class="w-4 h-4"></i>
                                    <span>Cancel Request</span>
                                </button></form><?php endif; ?>
                    </div>
                </div>

                <div class="text-xs text-gray-600 space-y-1">
                    <p>Your request was submitted on <span id="lbl_submitted_date" class="font-semibold text-brand-dark"><?= $esc(date('F j, Y', strtotime($request['submitted_at']))) ?></span>. Current status: <?= $esc($request['status']) ?>. Selected load: <?= number_format((float) $request['total_units'], 1) ?> units.</p>
                    <?php if ($request['rejection_reason']): ?><p id="lbl_rejection_reason_container" class="text-red-600 font-medium pt-1"><strong>Rejection Reason:</strong> <span id="lbl_rejection_reason"><?= $esc($request['rejection_reason']) ?></span></p><?php endif; ?>
                </div>
            </section><?php elseif (!$term): ?><section class="bg-white border border-amber-200 rounded-md p-6 shadow-xs text-sm text-amber-900">Enrollment is unavailable because no term is currently active.</section><?php elseif (!$enrollmentWindowOpen && !$request): ?><section class="bg-white border border-amber-200 rounded-md p-6 shadow-xs text-sm text-amber-900">Enrollment is only available during the date range set by the Registrar.<?php if (!empty($term['enrollment_open_at']) && !empty($term['enrollment_close_at'])): ?> It opens <?= $esc(date('M j, Y g:i A', strtotime($term['enrollment_open_at']))) ?> and closes <?= $esc(date('M j, Y g:i A', strtotime($term['enrollment_close_at']))) ?>.<?php endif; ?></section><?php endif; ?>

        <!-- Main Enrollment Form Container -->
        <?php if ($canSubmit): ?><form id="form_enrollment" action="enrollment.php<?= $yearLevel !== (int) $student['year_level'] ? '?year_level=' . $yearLevel : '' ?>" method="POST" class="space-y-8">

                <!-- Hidden Fields for Backend -->
                <input type="hidden" name="csrf_token" id="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="submit_request">

                <!-- Subject Selection Card -->
                <div class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">

                    <!-- Card Header with Filters -->
                    <div class="p-5 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-center space-x-3">
                            <div class="p-2 rounded-md bg-brand-accent/40 text-brand-primary">
                                <i data-lucide="book-open" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-brand-dark">Available Program Subjects</h2>
                                <p class="text-xs text-gray-500">Pick subjects from your program curriculum to enroll</p>
                            </div>
                        </div>

                        <!-- Filter Controls -->
                        <div class="flex items-center space-x-3 text-xs">
                            <label for="filter_year_level" class="font-semibold text-gray-600">Year Level:</label>
                            <select id="filter_year_level" name="year_level" onchange="window.location='enrollment.php?year_level='+this.value" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none">
                                <?php for ($level = 1; $level <= (int) $student['year_level']; $level++): ?><option value="<?= $level ?>" <?= $level === $yearLevel ? 'selected' : '' ?>><?= $level ?><?= $level === 1 ? 'st' : ($level === 2 ? 'nd' : ($level === 3 ? 'rd' : 'th')) ?> Year</option><?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Subjects Table -->
                    <div class="overflow-x-auto">
                        <table id="table_available_subjects" class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                                    <th class="py-3 px-5 w-10 text-center">Select</th>
                                    <th class="py-3 px-5">Code</th>
                                    <th class="py-3 px-5">Subject Title</th>
                                    <th class="py-3 px-5 text-center">Units</th>
                                    <th class="py-3 px-5">Prerequisites</th>
                                    <th class="py-3 px-5 text-center">Semester</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                                <?php foreach ($subjects as $subject): ?><tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="py-3.5 px-5 text-center"><input type="checkbox" name="subject_ids[]" value="<?= (int) $subject['id'] ?>" data-units="<?= $esc($subject['units']) ?>" class="chk-subject w-4 h-4 text-brand-primary rounded-xs border-gray-300 focus:ring-brand-primary cursor-pointer" <?= in_array((string) $subject['id'], array_map('strval', $selectedIds), true) ? 'checked' : '' ?>></td>
                                        <td class="py-3.5 px-5 font-bold text-brand-dark"><?= $esc($subject['code']) ?></td>
                                        <td class="py-3.5 px-5"><?= $esc($subject['name']) ?></td>
                                        <td class="py-3.5 px-5 text-center font-semibold text-brand-dark"><?= number_format((float) $subject['units'], 1) ?></td>
                                        <td class="py-3.5 px-5 text-gray-500"><?= $subject['prerequisites'] ? $esc($subject['prerequisites']) : 'None' ?></td>
                                        <td class="py-3.5 px-5 text-center"><?= $esc($subject['semester']) ?></td>
                                    </tr><?php endforeach; ?>
                                <?php if ($subjects === []): ?><tr>
                                        <td colspan="6" class="py-6 px-5 text-center text-gray-500">No active subjects are scheduled for this program, year, and term.</td>
                                    </tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Summary & Submission -->
                    <div class="p-5 bg-gray-50/80 border-t border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">

                        <!-- Unit Tracker Progress -->
                        <div class="space-y-2 max-w-md w-full">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-semibold text-gray-700">Total Selected Units:</span>
                                <span class="font-bold text-brand-dark">
                                    <span id="lbl_total_units"><?= number_format($selectedUnits, 1) ?></span> / <span id="lbl_max_units"><?= $unitLimit ?></span> Units Allowed
                                </span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div id="bar_unit_progress" class="h-full bg-brand-secondary transition-all duration-300" style="width: <?= min(100, $unitLimit > 0 ? ($selectedUnits / $unitLimit * 100) : 0) ?>%;"></div>
                            </div>
                            <p id="lbl_unit_warning" class="text-[11px] text-red-600 font-semibold hidden">
                                Exceeded maximum unit limit set by administrator!
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button id="btn_submit_enrollment" type="submit" class="w-full md:w-auto inline-flex items-center justify-center space-x-2 bg-brand-primary hover:bg-brand-primary/90 text-white font-medium py-2.5 px-5 rounded-md text-sm transition-colors shadow-xs">
                                <i data-lucide="send" class="w-4 h-4"></i>
                                <span id="btn_text_enrollment">Submit Enrollment Request</span>
                            </button>
                        </div>

                    </div>

                </div>

            </form><?php elseif ($request): ?><p class="text-sm text-gray-600">An active request already exists for this term. A new request can be submitted after it is rejected or cancelled.</p><?php endif; ?>

        <!-- Confirmed Enrolled Subjects Section (Appears after status becomes 'Enrolled') -->
        <?php if ($request && $request['status'] === 'Enrolled'): ?><section id="section_official_enrolled" class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="p-2 rounded-md bg-emerald-50 text-emerald-600">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-brand-dark">Confirmed Enrolled Subjects</h2>
                            <p class="text-xs text-gray-500">Official subjects confirmed by the Registrar for this term</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-800">
                        Official Record
                    </span>
                </div>

                <!-- Enrolled List Table -->
                <div class="overflow-x-auto">
                    <table id="table_enrolled_subjects" class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                                <th class="py-3 px-5">Code</th>
                                <th class="py-3 px-5">Subject Title</th>
                                <th class="py-3 px-5 text-center">Units</th>
                                <th class="py-3 px-5 text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                            <?php foreach ($request['subjects'] as $subject): ?><tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3.5 px-5 font-bold text-brand-dark"><?= $esc($subject['code']) ?></td>
                                    <td class="py-3.5 px-5"><?= $esc($subject['name']) ?></td>
                                    <td class="py-3.5 px-5 text-center font-semibold text-brand-dark"><?= number_format((float) $subject['units'], 1) ?></td>
                                    <td class="py-3.5 px-5 text-right"><span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-100 text-emerald-800">Confirmed Enrolled</span></td>
                                </tr><?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section><?php endif; ?>

    </main>

    <!-- Formal Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500">
        <div class="max-w-7xl mx-auto px-4">
            <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Interactive Scripts & Unit Calculation -->
    <script>
        $(document).ready(function() {
            // Render Lucide Icons
            lucide.createIcons();

            // Mobile menu toggle
            $('#mobile_menu_btn').on('click', function() {
                $('#mobile_menu').toggleClass('hidden');
            });

            // Dynamic Unit Calculation & Guard Rule
            const maxUnits = <?= (int) $unitLimit ?>;

            function calculateUnits() {
                let total = 0;
                $('.chk-subject:checked').each(function() {
                    total += parseFloat($(this).data('units')) || 0;
                });

                $('#lbl_total_units').text(total.toFixed(1));
                const percentage = Math.min((total / maxUnits) * 100, 100);
                $('#bar_unit_progress').css('width', percentage + '%');

                if (total > maxUnits) {
                    $('#lbl_unit_warning').removeClass('hidden');
                    $('#bar_unit_progress').removeClass('bg-brand-secondary').addClass('bg-red-600');
                    $('#btn_submit_enrollment').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                } else {
                    $('#lbl_unit_warning').addClass('hidden');
                    $('#bar_unit_progress').removeClass('bg-red-600').addClass('bg-brand-secondary');
                    $('#btn_submit_enrollment').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                }
            }

            $('.chk-subject').on('change', calculateUnits);
            calculateUnits(); // Run initial calculation
        });
    </script>
    <script>
        (function($) {
            if (!$) return;
            $(document).on('submit', 'form[method="post"], form[method="POST"]', function(event) {
                if (event.isDefaultPrevented()) return;
                event.preventDefault();
                var form = this;
                var fields = $(form).serializeArray();
                if (event.originalEvent && event.originalEvent.submitter && event.originalEvent.submitter.name) {
                    fields.push({
                        name: event.originalEvent.submitter.name,
                        value: event.originalEvent.submitter.value
                    });
                }
                if (window.location.pathname.toLowerCase().endsWith('/login.php')) fields.push({
                    name: 'action',
                    value: 'handleUserLogin'
                });
                $.post('../../controller/enrollmentController.php', $.param(fields), null, 'json')
                    .done(function(response) {
                        if (response && response.status === 'success') {
                            var action = form.querySelector('[name="action"]')?.value || '';
                            if (action === 'cancel_request') {
                                var row = form.closest('tr');
                                if (row) { row.dataset.status = 'Cancelled'; row.querySelectorAll('button').forEach(function(button) { button.remove(); }); }
                                showToast('success', response.message || 'Enrollment request cancelled.');
                            } else {
                                var banner = document.createElement('section'); banner.id = 'banner_enrollment_status'; banner.className = 'bg-white border border-gray-200 rounded-md p-6 shadow-xs';
                                banner.innerHTML = '<span id="badge_enrollment_status" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-blue-100 text-blue-800">Pending</span><p class="mt-2 text-sm font-bold">Your enrollment request was submitted.</p><p class="text-xs text-gray-600">The Registrar will review your selected subjects.</p>';
                                form.before(banner); form.remove();
                                showToast('success', response.message || 'Enrollment request submitted.');
                            }
                        } else {
                            var message = response && response.message ? response.message : 'The action could not be completed.';
                            showToast('error', message);
                        }
                    })
                    .fail(function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The action could not be completed. Please try again.';
                        showToast('error', message);
                        if (form.closest('body') && window.location.pathname.toLowerCase().endsWith('/login.php')) {
                            var button = document.getElementById('btn-submit');
                            if (button) {
                                button.disabled = false;
                                button.classList.remove('opacity-80', 'cursor-not-allowed');
                                button.innerHTML = 'Sign In';
                            }
                        }
                    });
            });
            var nativeSubmit = HTMLFormElement.prototype.submit;
            HTMLFormElement.prototype.submit = function() {
                if ((this.method || 'get').toLowerCase() === 'post') {
                    this.requestSubmit();
                    return;
                }
                nativeSubmit.call(this);
            };
        })(window.jQuery);
    </script>
<script>
(function () {
    const pageSize = 10;
    const tables = Array.from(document.querySelectorAll('table'));
    function visibleRows(table) {
        return Array.from(table.tBodies[0]?.rows || []).filter(row => row.style.display !== 'none' && !row.classList.contains('hidden'));
    }
    function controlsFor(table) {
        let controls = table.nextElementSibling;
        if (!controls || !controls.matches('[data-local-table-pages]')) {
            controls = document.createElement('div');
            controls.dataset.localTablePages = '';
            controls.className = 'flex w-full items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-4 py-3 text-xs text-gray-600';
            controls.innerHTML = '<span data-local-page-summary></span><div class="flex gap-2"><button type="button" data-local-page-step="-1" class="rounded border border-gray-300 bg-white px-3 py-2 font-semibold hover:bg-gray-50">Previous</button><button type="button" data-local-page-step="1" class="rounded border border-gray-300 bg-white px-3 py-2 font-semibold hover:bg-gray-50">Next</button></div>';
            table.insertAdjacentElement('afterend', controls);
        }
        return controls;
    }
    function refresh(table) {
        const body = table.tBodies[0];
        if (!body) return;
        const allRows = Array.from(body.rows);
        const rows = visibleRows(table);
        const pageCount = Math.max(1, Math.ceil(rows.length / pageSize));
        const page = Math.min(Math.max(Number(table.dataset.localPage || 1), 1), pageCount);
        table.dataset.localPage = String(page);
        allRows.forEach(row => { row.hidden = true; });
        const start = (page - 1) * pageSize;
        rows.slice(start, start + pageSize).forEach(row => { row.hidden = false; });
        const controls = controlsFor(table);
        controls.hidden = rows.length <= pageSize;
        controls.querySelector('[data-local-page-summary]').textContent = rows.length ? `Showing ${start + 1} to ${Math.min(start + pageSize, rows.length)} of ${rows.length}` : 'No records to show';
        controls.querySelector('[data-local-page-step="-1"]').disabled = page <= 1;
        controls.querySelector('[data-local-page-step="1"]').disabled = page >= pageCount;
    }
    let refreshPending = false;
    function scheduleRefresh() {
        if (refreshPending) return;
        refreshPending = true;
        window.requestAnimationFrame(() => { refreshPending = false; refreshAll(); });
    }
    function refreshAll() { tables.forEach(refresh); }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-local-page-step]');
        if (!button) return;
        const controls = button.closest('[data-local-table-pages]');
        const table = controls?.previousElementSibling;
        if (!table || table.tagName !== 'TABLE') return;
        table.dataset.localPage = String(Number(table.dataset.localPage || 1) + Number(button.dataset.localPageStep));
        refresh(table);
    });
    ['input', 'change', 'keyup'].forEach(name => document.addEventListener(name, scheduleRefresh, true));
    const observer = new MutationObserver(records => {
        if (records.some(record => [...record.addedNodes, ...record.removedNodes].some(node => node.nodeType === 1 && (node.matches('tr') || node.querySelector('tr'))))) refreshAll();
    });
    observer.observe(document.body, { childList: true, subtree: true });
    refreshAll();
})();
</script>
</body>

</html>
