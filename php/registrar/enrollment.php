<?php
require_once __DIR__ . '/../includes/auth.php';

ssis_require_role('registrar');

require_once __DIR__ . '/../../services/EnrollmentService.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$model = new Enrollment(Database::getConnection());
$controller = new EnrollmentService($model);

$notice = $_SESSION['registrar_enrollment_notice'] ?? null;

unset($_SESSION['registrar_enrollment_notice']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';

        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new DomainException('This form expired. Refresh the page and try again.');
        }

        if (($_POST['action'] ?? '') === 'approve_and_update') {
            $controller->updateReviewedSubjects((int) $_SESSION['user']['id'], $_POST);
            $reviewResult = $controller->reviewRequest((int) $_SESSION['user']['id'], $_POST + ['new_status' => 'Approved']);
            $message = 'The subject load was saved and the enrollment request was approved.';
        } elseif (($_POST['action'] ?? '') === 'update_subjects') {
            $controller->updateReviewedSubjects((int) $_SESSION['user']['id'], $_POST);
            $message = 'The subject list was updated and the student was notified.';
        } else {
            $reviewResult = $controller->reviewRequest((int) $_SESSION['user']['id'], $_POST);
            $message = $reviewResult['message'];
        }

        if (isset($reviewResult) && $reviewResult['status'] === 'Cancelled') {
            $message = $reviewResult['message'];
        }

        $_SESSION['registrar_enrollment_notice'] = ['type' => 'success', 'message' => $message];
        header('Location: enrollment.php' . (!empty($_GET['status']) ? '?status=' . rawurlencode((string) $_GET['status']) : ''));
        exit;
    } catch (DomainException $exception) {
        $notice = ['type' => 'error', 'message' => $exception->getMessage()];
    } catch (Throwable $exception) {
        error_log('SSIS registrar enrollment action failed: ' . $exception->getMessage());
        $notice = ['type' => 'error', 'message' => 'We could not update the enrollment request. Please try again.'];
    }
}

try {
    $term = $model->getCurrentTerm();
    $allRequests = $term ? $model->getRegistrarQueue((int) $term['id']) : [];
} catch (Throwable $exception) {
    error_log('SSIS registrar enrollment load failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('The enrollment queue is temporarily unavailable. Please try again later.');
}

$statuses = ['Pending', 'Under Review', 'Approved', 'Enrolled', 'Rejected', 'Cancelled'];
$requestedFilter = $_GET['status'] ?? null;

$filter = is_string($requestedFilter) && in_array($requestedFilter, $statuses, true) ? $requestedFilter : '';
$requests = $filter === ''

    ? array_values(array_filter($allRequests, static fn($row): bool => $row['status'] !== 'Enrolled'))
    : array_values(array_filter($allRequests, static fn($row): bool => $row['status'] === $filter));

$counts = array_fill_keys($statuses, 0);

foreach ($allRequests as $row) {
    $counts[$row['status']]++;
}

$esc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$statusStyle = ['Pending' => 'bg-amber-100 text-amber-800', 'Under Review' => 'bg-blue-100 text-blue-800', 'Approved' => 'bg-teal-100 text-teal-800', 'Enrolled' => 'bg-emerald-100 text-emerald-800', 'Rejected' => 'bg-red-100 text-red-800', 'Cancelled' => 'bg-gray-100 text-gray-700'];

$staffName = $_SESSION['user']['full_name'] ?: $_SESSION['user']['username'];

$unitLimit = $model->getUnitLimit();

$requestsById = [];

foreach ($requests as $row) {
    $requestsById[(int) $row['id']] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Registrar Enrollment Queue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif']
                    },
                    colors: {
                        brand: {
                            primary: '#344e41',
                            secondary: '#588157',
                            accent: '#dad7cd',
                            dark: '#202020'
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 font-sans text-brand-dark antialiased min-h-screen flex flex-col">
    <?php include __DIR__ . '/../component/sidebar.php'; ?>

    <!-- Toast Notification Container -->
    <div id="toast_container" class="fixed top-5 right-5 z-[10000] flex flex-col space-y-2 max-w-sm w-full pointer-events-none px-4 sm:px-0"></div>

    <!-- Navigation Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 bg-brand-primary rounded-md flex items-center justify-center text-white font-bold text-lg">P</div>
                    <div>
                        <span class="text-base font-bold text-brand-dark tracking-tight block leading-none">PAXTON</span>
                        <span class="text-[10px] uppercase font-semibold text-gray-500 tracking-widest block mt-0.5">University SSIS</span>
                    </div>
                </div>

                <nav class="hidden lg:flex items-center space-x-1 text-xs">
                    <a href="enrollment.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Enrollment Queue</a>
                    <a href="grades.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Grades</a>
                    <a href="documentRequest.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Document Requests</a>
                    <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
                    <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Report</a>
                    <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
                    <a href="announcements.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Announcements</a>
                </nav>

                <div class="flex items-center space-x-3">
                    <div class="hidden lg:flex items-center space-x-2">
                        <div class="text-right">
                            <p class="text-xs font-semibold text-brand-dark"><?= $esc($staffName) ?></p>
                            <p class="text-[10px] text-gray-500 uppercase font-bold">Registrar Module</p>
                        </div>
                    </div>
                    <button type="button" id="btn_mobile_menu" class="lg:hidden p-2 rounded-md text-gray-500 hover:text-brand-dark hover:bg-gray-100 focus:outline-none">
                        <i data-lucide="menu" id="icon_menu_open" class="w-6 h-6"></i>
                        <i data-lucide="x" id="icon_menu_close" class="w-6 h-6 hidden"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile_nav_menu" class="hidden lg:hidden border-t border-gray-200 bg-white px-4 pt-2 pb-4 space-y-1 text-xs shadow-md">
            <a href="enrollment.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Enrollment Queue</a>
            <a href="grades.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Grades</a>
            <a href="documentRequest.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Document Requests</a>
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Report</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <a href="announcements.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Announcements</a>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <!-- Header Metrics -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col lg:flex-row lg:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Enrollment Request Queue</h1>
                <p class="text-sm text-gray-500">Review student subject loads, make necessary subject adjustments, and approve/enroll students.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3">
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Pending</span>
                    <span class="text-base font-bold text-amber-700"><?= (int) $counts['Pending'] ?></span>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Under Review</span>
                    <span class="text-base font-bold text-blue-700"><?= (int) $counts['Under Review'] ?></span>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Approved</span>
                    <span class="text-base font-bold text-teal-700"><?= (int) $counts['Approved'] ?></span>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Enrolled</span>
                    <span class="text-base font-bold text-emerald-700"><?= (int) $counts['Enrolled'] ?></span>
                </div>
            </div>
        </section>

        <!-- Queue Table Section with Clean Horizontal Scroll -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <h2 class="text-base font-bold text-brand-dark">Applications Queue<?= $term ? ' (' . $esc($term['semester']) . ', A.Y. ' . $esc($term['academic_year']) . ')' : ' (No Active Term)' ?></h2>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <select id="filter_status" onchange="window.location='enrollment.php'+(this.value?'?status='+encodeURIComponent(this.value):'')" class="px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary bg-white">
                        <option value="" <?= $filter === '' ? 'selected' : '' ?>>All Statuses</option>
                        <?php foreach ($statuses as $status): ?><option value="<?= $esc($status) ?>" <?= $filter === $status ? 'selected' : '' ?>><?= $esc($status) ?></option><?php endforeach; ?>
                    </select>
                    <input id="search_enrollment" type="text" placeholder="Search Student No or Name..." class="px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary">
                </div>
            </div>

            <!-- Table Wrapper with horizontal scrolling & fixed minimum width -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse min-w-[640px]">
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase font-semibold whitespace-nowrap">
                            <th class="py-3 px-4 sm:px-5 sticky left-0 bg-gray-50 z-10 shadow-[1px_0_0_0_#f3f4f6]">Student Number & Name</th>
                            <th class="py-3 px-4 sm:px-5">Program</th>
                            <th class="py-3 px-4 sm:px-5">Year Level</th>
                            <th class="py-3 px-4 sm:px-5 text-center">Status</th>
                            <th class="py-3 px-4 sm:px-5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">

                        <?php foreach ($requests as $request): $rid = (int) $request['id'];
                            $yearLabel = (int) $request['year_level'] . ((int) $request['year_level'] === 1 ? 'st' : ((int) $request['year_level'] === 2 ? 'nd' : ((int) $request['year_level'] === 3 ? 'rd' : 'th'))) . ' Year'; ?>
                            <tr class="hover:bg-gray-50/50 group" data-search="<?= $esc(strtolower($request['student_name'] . ' ' . $request['student_no'])) ?>">
                                <td class="py-3.5 px-4 sm:px-5 whitespace-nowrap sticky left-0 bg-white group-hover:bg-gray-50/50 z-10 shadow-[1px_0_0_0_#f3f4f6]">
                                    <p class="font-bold text-brand-dark"><?= $esc($request['student_name']) ?></p>
                                    <p class="text-gray-400 text-[11px] font-display"><?= $esc($request['student_no']) ?></p>
                                </td>
                                <td class="py-3.5 px-4 sm:px-5 whitespace-nowrap"><?= $esc($request['program_name']) ?></td>
                                <td class="py-3.5 px-4 sm:px-5 whitespace-nowrap"><?= $esc($yearLabel) ?></td>
                                <td class="py-3.5 px-4 sm:px-5 text-center whitespace-nowrap"><span class="px-2.5 py-0.5 rounded-md text-[11px] font-semibold <?= $statusStyle[$request['status']] ?? 'bg-gray-100 text-gray-700' ?>"><?= $esc($request['status']) ?></span></td>
                                <td class="py-3.5 px-4 sm:px-5 text-right whitespace-nowrap space-x-1">
                                    <?php if ($request['status'] === 'Pending'): ?><form method="post" class="inline"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="enrollment_id" value="<?= $rid ?>"><input type="hidden" name="new_status" value="Under Review"><button class="px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white hover:bg-brand-primary/90">Review &amp; Adjust</button></form>
                                    <?php elseif ($request['status'] === 'Under Review'): ?><button onclick="openReviewModal(<?= $rid ?>)" class="px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white hover:bg-brand-primary/90">Evaluate Load</button><button onclick="openRejectModal(<?= $rid ?>)" class="px-2.5 py-1.5 rounded-md text-xs font-semibold border border-red-200 text-red-600 bg-white hover:bg-red-50">Reject</button>
                                    <?php elseif ($request['status'] === 'Approved'): ?><form method="post" class="inline" onsubmit="return confirm('Confirm this student as enrolled?');"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="enrollment_id" value="<?= $rid ?>"><input type="hidden" name="new_status" value="Enrolled"><button class="px-2.5 py-1.5 rounded-md text-xs font-semibold bg-emerald-700 text-white hover:bg-emerald-800">Confirm Enrolled</button></form>
                                    <?php else: ?><button onclick="openReviewModal(<?= $rid ?>, true)" class="px-2.5 py-1.5 rounded-md text-xs font-semibold border border-gray-200 text-gray-700 bg-white hover:bg-gray-50">View Subject Load</button><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- EVALUATE & ADJUST SUBJECTS MODAL -->
    <div id="modal_review_subjects" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs hidden z-[9998] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full p-5 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-base font-bold text-brand-dark" id="modal_student_name">Review Student Request</h3>
                    <p class="text-xs text-gray-500" id="modal_student_info">Enrollment request details</p>
                </div>
                <button onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form id="form_subject_review" method="post" action="enrollment.php" onsubmit="return true">
                <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="approve_and_update"><input type="hidden" name="enrollment_id" id="review_enrollment_id" value="">
                <!-- Subject Load Table -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-gray-700">Requested Subject Load</span>
                        <span class="text-xs font-bold text-brand-primary">Total Units: <span id="lbl_total_units">0</span> / <?= $unitLimit ?> Max</span>
                    </div>
                    <div class="border border-gray-200 rounded-md overflow-x-auto">
                        <table class="w-full text-left text-xs min-w-[320px]">
                            <thead class="bg-gray-50 border-b border-gray-200 text-gray-600 uppercase font-semibold">
                                <tr>
                                    <th class="py-2 px-3">Subject Code</th>
                                    <th class="py-2 px-3">Subject Name</th>
                                    <th class="py-2 px-3 text-center">Units</th>
                                    <th class="py-2 px-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_subject_list" class="divide-y divide-gray-100 font-medium"></tbody>
                        </table>
                    </div>

                    <!-- Add Extra Subject Selector -->
                    <div id="container_add_subject" class="flex flex-col sm:flex-row gap-2 pt-1">
                        <select id="select_add_subject" class="flex-1 px-3 py-1.5 text-xs border border-gray-300 rounded-md bg-white outline-none">
                            <option value="" disabled selected>Add additional subject from curriculum...</option>
                        </select>
                        <button type="button" onclick="addSubjectFromSelect()" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-md text-xs font-semibold text-gray-700">Add Subject</button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-2 pt-3 border-t border-gray-100">
                    <button type="button" onclick="closeReviewModal()" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50">Close</button>
                    <button id="btn_approve_modal" type="submit" class="px-4 py-1.5 bg-brand-primary text-white rounded-md text-xs font-semibold hover:bg-brand-primary/90">Approve Application</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MANDATORY REJECTION REASON MODAL -->
    <div id="modal_reject_reason" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs hidden z-[9999] flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 space-y-4">
            <h3 class="text-base font-bold text-brand-dark">Reject Enrollment Application</h3>
            <p class="text-xs text-gray-500">In accordance with Section 7 & 12, a specific reason is required when rejecting an enrollment request.</p>

            <form id="form_reject" method="post" action="enrollment.php">
                <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="enrollment_id" id="reject_enrollment_id"><input type="hidden" name="new_status" value="Rejected">
                <div>
                    <label for="txt_rejection_reason" class="block text-xs font-semibold text-gray-700 mb-1">Rejection Reason *</label>
                    <textarea id="txt_rejection_reason" name="rejection_reason" rows="3" maxlength="500" required placeholder="e.g., Failed prerequisite subjects, missing clearance, or exceeded maximum unit limits..." class="w-full px-3 py-2 border border-gray-300 rounded-md text-xs outline-none focus:ring-1 focus:ring-red-500"></textarea>
                </div>

                <div class="flex justify-end space-x-2 pt-2">
                    <button onclick="closeRejectModal()" class="px-3 py-1.5 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 bg-red-600 text-white rounded-md text-xs font-semibold hover:bg-red-700">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500">
        <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
    </footer>

    <script>
        lucide.createIcons();

        const enrollmentRequests = <?= json_encode($requestsById, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const enrollmentCsrfToken = <?= json_encode($_SESSION['csrf_token'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const unitLimit = <?= (int) $unitLimit ?>;
        let currentTotalUnits = 0;
        let selectedRequestId = null;

        $('#btn_mobile_menu').on('click', function() {
            $('#mobile_nav_menu').toggleClass('hidden');
            $('#icon_menu_open').toggleClass('hidden');
            $('#icon_menu_close').toggleClass('hidden');
        });

        function openReviewModal(requestId, isReadOnly = false) {
            const request = enrollmentRequests[requestId];
            if (!request) return;
            selectedRequestId = requestId;
            const modalUrl = new URL(window.location.href);
            modalUrl.searchParams.set('review_load', String(requestId));
            if (isReadOnly) modalUrl.searchParams.set('review_readonly', '1');
            else modalUrl.searchParams.delete('review_readonly');
            window.history.replaceState(window.history.state, '', modalUrl);
            $('#review_enrollment_id').val(requestId);
            $('#modal_student_name').text('Review Request: ' + request.student_name);
            $('#modal_student_info').text(request.student_no + ' • ' + request.program_name + ' (' + request.year_level + ' Year)');
            const editable = request.status === 'Under Review' && !isReadOnly;
            const submittedSubjects = (request.subjects || []).map(subject => ({
                ...subject,
                is_selected: 1
            }));
            let subjects = submittedSubjects;
            if (editable) {
                const subjectsById = new Map();
                (request.eligible_subjects || []).forEach(subject => {
                    subjectsById.set(String(subject.id), subject);
                });
                submittedSubjects.forEach(subject => {
                    const subjectId = String(subject.id);
                    const eligibleSubject = subjectsById.get(subjectId);
                    subjectsById.set(subjectId, eligibleSubject ? {
                            ...eligibleSubject,
                            is_selected: 1
                        } :
                        subject);
                });
                subjects = Array.from(subjectsById.values());
            }
            $('#container_add_subject').toggleClass('hidden', !editable);
            $('#btn_approve_modal').toggleClass('hidden', !editable);
            $('#select_add_subject').html('<option value="" disabled selected>Add additional subject from curriculum...</option>');
            $('#tbody_subject_list').empty();
            currentTotalUnits = 0;
            subjects.forEach(subject => {
                const selected = Number(subject.is_selected) === 1;
                const row = $('<tr>');
                row.append(`<td class="py-2 px-3 font-display font-bold">${escapeHtml(subject.code)}</td><td class="py-2 px-3">${escapeHtml(subject.name)}</td><td class="py-2 px-3 text-center">${Number(subject.units)}</td>`);
                const selection = editable ? `<input type="checkbox" name="subject_ids[]" value="${Number(subject.id)}" data-units="${Number(subject.units)}" ${selected ? 'checked' : ''} onchange="updateLoadTotal()">` : '';
                row.append(`<td class="py-2 px-3 text-right">${selection}</td>`);
                $('#tbody_subject_list').append(row);
                if (editable) $('#select_add_subject').append($('<option>').val(String(subject.id)).text(`${subject.code} - ${subject.name} (${subject.units} Units)`).prop('disabled', selected));
            });
            $('#lbl_total_units').text((editable ? subjects.filter(s => Number(s.is_selected) === 1) : subjects).reduce((sum, subject) => sum + Number(subject.units), 0).toFixed(1));
            updateLoadTotal();
            $('#modal_review_subjects').removeClass('hidden');
        }

        function escapeHtml(value) {
            return $('<div>').text(value ?? '').html();
        }

        function updateLoadTotal() {
            currentTotalUnits = $('#form_subject_review input[name="subject_ids[]"]:checked').toArray().reduce((sum, input) => sum + Number(input.dataset.units || 0), 0);
            $('#lbl_total_units').text(currentTotalUnits.toFixed(1));
            $('#btn_approve_modal').prop('disabled', currentTotalUnits <= 0 || currentTotalUnits > unitLimit).toggleClass('opacity-50 cursor-not-allowed', currentTotalUnits <= 0 || currentTotalUnits > unitLimit);
        }

        function closeReviewModal() {
            $('#modal_review_subjects').addClass('hidden');
            const modalUrl = new URL(window.location.href);
            modalUrl.searchParams.delete('review_load');
            modalUrl.searchParams.delete('review_readonly');
            window.history.replaceState(window.history.state, '', modalUrl);
        }

        const reviewUrl = new URLSearchParams(window.location.search);
        const reviewOnLoadId = Number(reviewUrl.get('review_load'));
        if (reviewOnLoadId > 0 && enrollmentRequests[reviewOnLoadId]) {
            openReviewModal(reviewOnLoadId, reviewUrl.get('review_readonly') === '1');
        }

        function addSubjectFromSelect() {
            const val = $('#select_add_subject').val();
            if (!val) {
                showToast('warning', 'Please select a subject to add.');
                return;
            }

            const subjectId = Number(val);
            const subject = enrollmentRequests[selectedRequestId]?.eligible_subjects.find(item => Number(item.id) === subjectId);
            if (!subject) return;
            const units = Number(subject.units);
            if (currentTotalUnits + units > unitLimit) {
                showToast('error', `Cannot add subject. Maximum limit of ${unitLimit} units exceeded.`);
                return;
            }
            $('#tbody_subject_list').append(`<tr><td class="py-2 px-3 font-display font-bold">${escapeHtml(subject.code)}</td><td class="py-2 px-3">${escapeHtml(subject.name)}</td><td class="py-2 px-3 text-center">${units}</td><td class="py-2 px-3 text-right"><input type="checkbox" name="subject_ids[]" value="${subjectId}" data-units="${units}" checked onchange="updateLoadTotal()"></td></tr>`);
            $('#select_add_subject option:selected').prop('disabled', true);
            $('#select_add_subject').val('');
            updateLoadTotal();
        }

        function openRejectModal(requestId) {
            selectedRequestId = requestId;
            $('#reject_enrollment_id').val(requestId);
            $('#txt_rejection_reason').val('');
            $('#modal_reject_reason').removeClass('hidden');
        }

        function closeRejectModal() {
            $('#modal_reject_reason').addClass('hidden');
        }

        $('#search_enrollment').on('input', function() {
            const term = this.value.toLowerCase();
            $('tbody tr[data-search]').each(function() {
                $(this).toggle($(this).data('search').includes(term));
            });
        });
        <?php if ($notice): ?>showToast('<?= $notice['type'] === 'success' ? 'success' : 'error' ?>', <?= json_encode($notice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        <?php endif; ?>
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
                            var enrollmentId = form.querySelector('[name="enrollment_id"]')?.value || document.getElementById('review_enrollment_id')?.value || document.getElementById('reject_enrollment_id')?.value;
                            var row = form.closest('tr') || Array.from(document.querySelectorAll('tbody tr')).find(function(candidate) {
                                return candidate.querySelector('button[onclick*="' + Number(enrollmentId) + '"]') || candidate.querySelector('input[name="enrollment_id"][value="' + Number(enrollmentId) + '"]');
                            });
                            var status = response.data?.status || form.querySelector('[name="new_status"], [name="status"]')?.value || (form.querySelector('[name="action"]')?.value === 'approve_and_update' ? 'Approved' : (form.querySelector('[name="action"]')?.value === 'update_subjects' ? 'Under Review' : ''));
                            if (status && enrollmentRequests[enrollmentId]) enrollmentRequests[enrollmentId].status = status;
                            if (row && status) {
                                row.dataset.status = status;
                                var badge = row.querySelector('[data-status-badge]') || row.cells[3]?.querySelector('span');
                                if (badge) {
                                    var statusClassMap = {
                                        'Pending': ['bg-amber-100', 'text-amber-800'],
                                        'Under Review': ['bg-blue-100', 'text-blue-800'],
                                        'Approved': ['bg-teal-100', 'text-teal-800'],
                                        'Enrolled': ['bg-emerald-100', 'text-emerald-800'],
                                        'Rejected': ['bg-red-100', 'text-red-800'],
                                        'Cancelled': ['bg-gray-100', 'text-gray-700']
                                    };
                                    badge.classList.remove('bg-amber-100', 'text-amber-800', 'bg-blue-100', 'text-blue-800', 'bg-teal-100', 'text-teal-800', 'bg-emerald-100', 'text-emerald-800', 'bg-red-100', 'text-red-800', 'bg-gray-100', 'text-gray-700');
                                    badge.classList.add(...(statusClassMap[status] || statusClassMap['Cancelled']));
                                    badge.textContent = status;
                                }
                                var actions = row.cells[4];
                                if (actions && status === 'Under Review') actions.innerHTML = '<button type="button" onclick="openReviewModal(' + Number(enrollmentId) + ')" class="px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white">Evaluate Load</button> <button type="button" onclick="openRejectModal(' + Number(enrollmentId) + ')" class="px-2.5 py-1.5 rounded-md text-xs font-semibold border border-red-200 text-red-600 bg-white">Reject</button>';
                                else if (actions && status === 'Approved') actions.innerHTML = '<form method="post" class="inline" onsubmit="return confirm(\'Confirm this student as enrolled?\');"><input type="hidden" name="csrf_token" value="' + escapeHtml(enrollmentCsrfToken) + '"><input type="hidden" name="enrollment_id" value="' + Number(enrollmentId) + '"><input type="hidden" name="new_status" value="Enrolled"><button type="submit" class="px-2.5 py-1.5 rounded-md text-xs font-semibold bg-emerald-700 text-white hover:bg-emerald-800">Confirm Enrolled</button></form>';
                                else if (actions && status === 'Rejected') actions.textContent = 'Rejected';
                                else if (actions && status === 'Cancelled') actions.textContent = 'Cancelled';
                                else if (actions && status === 'Enrolled') row.remove();
                            }
                            closeReviewModal();
                            closeRejectModal();
                            showToast('success', response.message || 'Enrollment request updated.');
                            return;
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
        (function() {
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
                allRows.forEach(row => {
                    row.hidden = true;
                });
                const start = (page - 1) * pageSize;
                rows.slice(start, start + pageSize).forEach(row => {
                    row.hidden = false;
                });
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
                window.requestAnimationFrame(() => {
                    refreshPending = false;
                    refreshAll();
                });
            }

            function refreshAll() {
                tables.forEach(refresh);
            }
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
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            refreshAll();
        })();
    </script>
</body>

</html>