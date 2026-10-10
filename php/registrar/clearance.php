<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('registrar');
require_once __DIR__ . '/../../services/ClearanceService.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$clearanceModel = new Clearance(Database::getConnection());
$clearanceController = new ClearanceService($clearanceModel);
$notice = $_SESSION['registrar_clearance_notice'] ?? null;
unset($_SESSION['registrar_clearance_notice']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new DomainException('This form expired. Refresh the page and try again.');
        }
        $clearanceController->review((int) $_SESSION['user']['id'], 'Registrar', null, $_POST);
        $_SESSION['registrar_clearance_notice'] = ['success' => true, 'message' => 'The clearance decision was saved and the student was notified.'];
        header('Location: clearance.php');
        exit;
    } catch (DomainException $exception) {
        $notice = ['success' => false, 'message' => $exception->getMessage()];
    } catch (Throwable $exception) {
        error_log('SSIS registrar clearance review failed: ' . $exception->getMessage());
        $notice = ['success' => false, 'message' => 'We could not update this clearance item. Please try again.'];
    }
}
try {
    $term = $clearanceModel->getCurrentTerm();
    if ($term) {
        $clearanceModel->ensureTermClearances((int) $term['id']);
        $queue = $clearanceModel->getOfficeQueue((int) $term['id'], 'Registrar');
    } else {
        $queue = [];
    }
} catch (Throwable $exception) {
    error_log('SSIS registrar clearance page failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Clearance information is temporarily unavailable. Please try again later.');
}
$esc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$registrarName = $_SESSION['user']['full_name'] ?: $_SESSION['user']['username'];
$queueData = [];
foreach ($queue as $item) {
    $queueData[(int) $item['item_id']] = $item;
}
$clearedCount = 0;
$pendingCount = 0;
$holdCount = 0;
foreach ($queue as $item) {
    if ($item['status'] === 'Approved') {
        $clearedCount++;
    } elseif ($item['status'] === 'Rejected') {
        $holdCount++;
    } else {
        $pendingCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Student Clearance</title>
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

<body class="bg-gray-50 font-sans text-brand-dark antialiased min-h-screen flex flex-col relative">
    <?php include __DIR__ . '/../component/sidebar.php'; ?>

    <!-- Navigation Header -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 bg-brand-primary rounded-md flex items-center justify-center text-white font-bold text-lg">P</div>
                    <div>
                        <span class="text-base font-bold text-brand-dark tracking-tight block leading-none">PAXTON</span>
                        <span class="text-[10px] uppercase font-semibold text-gray-500 tracking-widest block mt-0.5">University SSIS</span>
                    </div>
                </div>

                <!-- Desktop Navigation Links -->
                <nav class="hidden lg:flex items-center space-x-1 text-xs">
                    <a href="enrollment.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Enrollment</a>
                    <a href="grades.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Grades</a>
                    <a href="documentRequest.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Document Requests</a>
                    <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Clearance</a>
                    <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Report</a>
                    <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
                    <a href="announcements.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Announcements</a>
                </nav>

                <!-- Profile Info & Mobile Toggle -->
                <div class="flex items-center space-x-3">
                    <div class="hidden lg:flex items-center space-x-2">
                        <div class="text-right">
                            <p class="text-xs font-semibold text-brand-dark"><?= $esc($registrarName) ?></p>
                            <p class="text-[10px] text-gray-500 uppercase font-bold">Registrar Module</p>
                        </div>
                    </div>

                    <a href="logout.php" class="p-2 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 transition-colors" title="Log Out">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                    </a>
                    <button type="button" id="btn_mobile_menu" class="lg:hidden p-2 rounded-md text-gray-500 hover:text-brand-dark hover:bg-gray-100 focus:outline-none">
                        <i data-lucide="menu" id="icon_menu_open" class="w-6 h-6"></i>
                        <i data-lucide="x" id="icon_menu_close" class="w-6 h-6 hidden"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div id="mobile_nav_menu" class="hidden lg:hidden border-t border-gray-200 bg-white px-4 pt-2 pb-4 space-y-1 text-xs shadow-md">
            <a href="enrollment.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Enrollment</a>
            <a href="grades.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Grades</a>
            <a href="documentRequest.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Document Requests</a>
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Clearance</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Report</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <a href="announcements.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Announcements</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col xl:flex-row xl:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Student Clearance Management</h1>
                <p class="text-sm text-gray-500">Review Registrar requirements, approve or reject submissions, and inspect overall student clearance status.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Fully Cleared</span>
                    <span data-clearance-count="Approved" class="text-base font-bold text-emerald-700"><?= $clearedCount ?></span>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Pending Review</span>
                    <span data-clearance-count="Pending" class="text-base font-bold text-amber-700"><?= $pendingCount ?></span>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Rejected / Holds</span>
                    <span data-clearance-count="Rejected" class="text-base font-bold text-red-700"><?= $holdCount ?></span>
                </div>
            </div>
        </section>

        <!-- Clearance Queue & Matrix -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <h2 class="text-base font-bold text-brand-dark">Registrar Clearance Queue</h2>
                <div class="flex items-center space-x-2">
                    <input id="search_registrar_clearance" type="search" placeholder="Search Student Name or ID..." class="px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary">
                </div>
            </div>
            <div class="overflow-x-auto">
                <table id="table_registrar_clearance" class="w-full min-w-[750px] table-fixed text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase font-semibold">
                            <th class="py-3 px-5 whitespace-nowrap">Student</th>
                            <th class="py-3 px-5 min-w-[180px]">Registrar Requirement</th>
                            <th class="py-3 px-5 text-center whitespace-nowrap">Registrar Status</th>
                            <th class="py-3 px-5 text-center whitespace-nowrap">Overall Clearance</th>
                            <th class="py-3 px-5 text-right whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        <?php foreach ($queue as $item): $overallText = $item['overall_status'] === 'Cleared' ? 'Fully Cleared' : ($item['overall_status'] === 'Action Needed' ? 'Blocked' : 'Pending Holds'); ?>
                            <tr class="hover:bg-gray-50/50" data-clearance-item-id="<?= (int) $item['item_id'] ?>" data-search="<?= $esc($item['student_name'] . ' ' . $item['student_no']) ?>">
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <p class="font-bold text-brand-dark"><?= $esc($item['student_name']) ?></p>
                                    <p class="text-gray-400 text-[11px] font-display"><?= $esc($item['student_no']) ?> (<?= $esc($item['program_code']) ?>)</p>
                                </td>
                                <td class="py-3.5 px-5">
                                    <p class="font-semibold text-brand-dark"><?= $esc($item['requirement_name']) ?></p>
                                    <p class="text-gray-400 text-[11px]"><?= $item['submitted_at'] ? 'Submitted: ' . $esc(date('M j, Y', strtotime($item['submitted_at']))) : 'Awaiting office review' ?></p>
                                </td>
                                <td class="py-3.5 px-5 text-center whitespace-nowrap"><span class="px-2.5 py-0.5 rounded-md text-[11px] font-semibold <?= $item['status'] === 'Approved' ? 'bg-emerald-100 text-emerald-800' : ($item['status'] === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') ?>"><?= $esc($item['status']) ?></span></td>
                                <td class="py-3.5 px-5 text-center whitespace-nowrap"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $item['overall_status'] === 'Cleared' ? 'bg-emerald-100 text-emerald-800' : ($item['overall_status'] === 'Action Needed' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800') ?>"><?= $esc($overallText) ?></span></td>
                                <td class="py-3.5 px-5 text-right space-x-1 whitespace-nowrap"><?php if ($item['status'] !== 'Approved'): ?><button type="button" onclick="promptApproveRequirement(<?= (int) $item['item_id'] ?>, <?= htmlspecialchars(json_encode($item['student_name'], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)" class="px-2.5 py-1.5 rounded-md text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700"><?= $item['status'] === 'Rejected' ? 'Re-evaluate' : 'Approve' ?></button><?php endif; ?><?php if ($item['status'] !== 'Approved'): ?><button type="button" onclick="openRejectModal(<?= (int) $item['item_id'] ?>, <?= htmlspecialchars(json_encode($item['student_name'], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($item['requirement_name'], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>)" class="px-2.5 py-1.5 rounded-md text-xs font-semibold border border-red-200 text-red-600 bg-white hover:bg-red-50">Reject</button><?php endif; ?><button type="button" onclick="openOverallClearanceModal(<?= (int) $item['item_id'] ?>)" class="px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white hover:bg-brand-primary/90">View Overall</button></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($queue === []): ?><tr>
                                <td colspan="5" class="py-8 px-5 text-center text-gray-500">No Registrar clearance requirements are assigned for this term.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <form id="clearance_post" method="post" action="clearance.php" class="hidden"><input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="item_id" id="post_item_id"><input type="hidden" name="status" id="post_status"><input type="hidden" name="rejection_reason" id="post_rejection_reason"><input type="hidden" name="remarks" value=""></form>
    </main>

    <!-- Modal: Approve Confirmation -->
    <div id="modal_approve_confirm" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-sm overflow-hidden flex flex-col p-5 space-y-4">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-emerald-100 text-emerald-700 rounded-full flex items-center justify-center flex-shrink-0">
                    <i data-lucide="check-circle-2" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-brand-dark">Confirm Approval</h3>
                    <p class="text-xs text-gray-500">Grant Registrar sign-off for student clearance?</p>
                </div>
            </div>
            <p id="approve_confirm_text" class="text-xs text-gray-600 bg-gray-50 p-3 rounded-md border border-gray-100"></p>
            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" onclick="closeApproveModal()" class="px-3.5 py-1.5 font-semibold text-xs border border-gray-200 bg-white text-gray-700 rounded-md hover:bg-gray-50">Cancel</button>
                <button type="button" id="btn_confirm_approve" class="px-3.5 py-1.5 font-semibold text-xs bg-emerald-600 text-white rounded-md hover:bg-emerald-700">Approve Clearance</button>
            </div>
        </div>
    </div>

    <!-- Modal: Reject with Reason -->
    <div id="modal_reject_reason" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-md overflow-hidden flex flex-col">
            <div class="p-5 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-brand-dark">Reject Clearance Requirement</h3>
                    <p class="text-xs text-gray-500">Provide a mandatory reason for rejecting this student's submission</p>
                </div>
                <button type="button" onclick="closeRejectModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form id="form_reject_clearance" onsubmit="submitRejection(event)" class="p-5 space-y-4 text-xs">
                <div>
                    <label class="block text-gray-700 font-semibold mb-1">Student</label>
                    <input type="text" id="reject_student_name" readonly class="w-full bg-gray-100 border border-gray-200 rounded-md px-3 py-2 font-medium text-gray-700">
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold mb-1">Requirement Item</label>
                    <input type="text" id="reject_requirement_item" readonly class="w-full bg-gray-100 border border-gray-200 rounded-md px-3 py-2 font-medium text-gray-700">
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold mb-1">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea id="reject_reason_text" rows="3" required placeholder="Specify why this document/requirement is rejected (e.g. Unreadable copy, incomplete seal, invalid file)..." class="w-full border border-gray-200 rounded-md p-2.5 focus:outline-none focus:ring-2 focus:ring-brand-primary/20"></textarea>
                </div>

                <div class="pt-3 border-t border-gray-100 flex items-center justify-end space-x-2">
                    <button type="button" onclick="closeRejectModal()" class="px-4 py-2 font-semibold border border-gray-200 bg-white text-gray-700 rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 font-semibold bg-red-600 text-white rounded-md hover:bg-red-700">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: View Overall Clearance Details -->
    <div id="modal_overall_clearance" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-xl overflow-hidden flex flex-col">
            <div class="p-5 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-brand-dark">Overall Student Clearance Profile</h3>
                    <p class="text-xs text-gray-500">Comprehensive view across all university departments</p>
                </div>
                <button type="button" onclick="closeOverallClearanceModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="p-6 space-y-5 text-xs">
                <!-- Student Header Info -->
                <div class="p-4 bg-gray-50 border border-gray-200 rounded-md flex items-center justify-between">
                    <div>
                        <p id="modal_student_name" class="font-bold text-sm text-brand-dark">John Doe</p>
                        <p id="modal_student_id" class="text-gray-500 font-display">2026-00001 • BS Computer Science</p>
                    </div>
                    <span id="modal_overall_badge" class="px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100 text-amber-800">Pending Holds</span>
                </div>

                <!-- Departmental Status List -->
                <div class="space-y-3">
                    <h4 class="font-bold text-xs uppercase tracking-wider text-gray-500">Departmental Clearances</h4>

                    <div class="divide-y divide-gray-100 border border-gray-200 rounded-md overflow-hidden">
                        <!-- Registrar Office -->
                        <div class="p-3 bg-white flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <i data-lucide="file-check" class="w-4 h-4 text-gray-400"></i>
                                <div>
                                    <p class="font-bold text-brand-dark">Registrar's Requirement</p>
                                    <p class="text-[10px] text-gray-400">Form 137 / Transcripts / Credentials</p>
                                </div>
                            </div>
                            <span id="badge_status_registrar" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Pending Review</span>
                        </div>

                        <!-- University Library -->
                        <div class="p-3 bg-white flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <i data-lucide="book-open" class="w-4 h-4 text-gray-400"></i>
                                <div>
                                    <p class="font-bold text-brand-dark">University Library</p>
                                    <p class="text-[10px] text-gray-400">No unreturned books or outstanding fines</p>
                                </div>
                            </div>
                            <span id="badge_status_library" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Cleared</span>
                        </div>

                        <!-- Accounting Office -->
                        <div class="p-3 bg-white flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <i data-lucide="credit-card" class="w-4 h-4 text-gray-400"></i>
                                <div>
                                    <p class="font-bold text-brand-dark">Accounting & Finance</p>
                                    <p class="text-[10px] text-gray-400">Tuition fees & university account settlement</p>
                                </div>
                            </div>
                            <span id="badge_status_accounting" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Cleared</span>
                        </div>

                        <!-- College Department -->
                        <div class="p-3 bg-white flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <i data-lucide="building" class="w-4 h-4 text-gray-400"></i>
                                <div>
                                    <p class="font-bold text-brand-dark">College Dean / Department</p>
                                    <p class="text-[10px] text-gray-400">Lab equipment & departmental clearance</p>
                                </div>
                            </div>
                            <span id="badge_status_department" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">Cleared</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 border-t border-gray-200 bg-gray-50/50 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeOverallClearanceModal()" class="px-4 py-2 font-semibold text-xs border border-gray-200 bg-white text-gray-700 rounded-md hover:bg-gray-50">Close Profile</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500">
        <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
    </footer>

    <!-- JavaScript Logic -->

    <script>
        const clearanceQueue = <?= json_encode($queueData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        let selectedStudentForApproval = null;

        $(document).ready(function() {
            lucide.createIcons();
            $('#search_registrar_clearance').on('input', function() {
                const query = (this.value || '').trim().toLowerCase();
                const table = document.getElementById('table_registrar_clearance');
                table.dataset.localPage = '1';
                $('#table_registrar_clearance tbody tr[data-search]').each(function() {
                    this.style.display = this.dataset.search.toLowerCase().includes(query) ? 'table-row' : 'none';
                });
            });
        });

        // Approve Modal Handler (Replaces browser confirm)
        function promptApproveRequirement(itemId, studentName) {
            selectedStudentForApproval = {
                id: itemId,
                name: studentName
            };
            $('#approve_confirm_text').text(`Approve Registrar requirement clearance for ${studentName}?`);
            $('#modal_approve_confirm').removeClass('hidden');
        }

        function closeApproveModal() {
            $('#modal_approve_confirm').addClass('hidden');
            selectedStudentForApproval = null;
        }

        $('#btn_confirm_approve').on('click', function() {
            if (selectedStudentForApproval) {
                submitClearanceDecision(selectedStudentForApproval.id, 'Approved', '');
            }
        });

        // Reject Modal Handler
        let selectedClearanceItemId = null;

        function openRejectModal(itemId, studentName, requirement) {
            selectedClearanceItemId = itemId;
            $('#reject_student_name').val(studentName);
            $('#reject_requirement_item').val(requirement);
            $('#reject_reason_text').val('');
            $('#modal_reject_reason').removeClass('hidden');
        }

        function closeRejectModal() {
            $('#modal_reject_reason').addClass('hidden');
        }

        function submitRejection(e) {
            e.preventDefault();
            const reason = $('#reject_reason_text').val().trim();
            if (!reason || !selectedClearanceItemId) return;
            submitClearanceDecision(selectedClearanceItemId, 'Rejected', reason);
        }

        function submitClearanceDecision(itemId, status, reason) {
            $('#post_item_id').val(itemId);
            $('#post_status').val(status);
            $('#post_rejection_reason').val(reason);
            document.getElementById('clearance_post').submit();
        }

        // View Overall Clearance Modal
        function clearanceLabel(items) {
            if (items.length && items.every(item => item.status === 'Approved')) return 'Cleared';
            if (items.some(item => item.status === 'Rejected')) return 'Rejected';
            return 'Pending Review';
        }

        function setClearanceBadge(selector, label) {
            const color = label === 'Cleared' ? 'bg-emerald-100 text-emerald-800' : (label === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800');
            $(selector).attr('class', `px-2 py-0.5 rounded-full text-[10px] font-bold ${color}`).text(label);
        }

        function openOverallClearanceModal(itemId) {
            const row = clearanceQueue[itemId];
            if (!row) return;
            const items = row.all_items;
            const reg = items.filter(item => item.office === 'Registrar');
            const cashier = items.filter(item => item.office === 'Cashier');
            const library = items.filter(item => item.office === 'Department' && (item.department_name || '').toLowerCase().includes('library'));
            const department = items.filter(item => item.office === 'Department' && !(item.department_name || '').toLowerCase().includes('library'));
            $('#modal_student_name').text(row.student_name);
            $('#modal_student_id').text(`${row.student_no} • ${row.program_name}`);
            const overall = row.overall_status === 'Cleared' ? 'Fully Cleared' : (row.overall_status === 'Action Needed' ? 'Blocked' : 'Pending Holds');
            $('#modal_overall_badge').text(overall);
            setClearanceBadge('#badge_status_registrar', clearanceLabel(reg));
            setClearanceBadge('#badge_status_library', clearanceLabel(library));
            setClearanceBadge('#badge_status_accounting', clearanceLabel(cashier));
            setClearanceBadge('#badge_status_department', clearanceLabel(department));
            $('#modal_overall_clearance').removeClass('hidden');
        }

        function closeOverallClearanceModal() {
            $('#modal_overall_clearance').addClass('hidden');
        }

        $('#btn_mobile_menu').on('click', function() {
            $('#mobile_nav_menu').toggleClass('hidden');
            $('#icon_menu_open').toggleClass('hidden');
            $('#icon_menu_close').toggleClass('hidden');
        });
        <?php if ($notice): ?>showToast(<?= json_encode($notice['success'] ? 'success' : 'error') ?>, <?= json_encode($notice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        <?php endif; ?>
    </script>
    <script src="../../js/private-page-history.js"></script>
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
                $.post('../../controller/clearanceController.php', $.param(fields), null, 'json')
                    .done(function(response) {
                        if (response && response.status === 'success') {
                            var itemId = form.querySelector('[name="item_id"]')?.value;
                            var status = form.querySelector('[name="status"]')?.value;
                            var row = document.querySelector('tr[data-clearance-item-id="' + Number(itemId) + '"]');
                            if (row && status) {
                                var previousStatus = clearanceQueue[itemId]?.status || 'Pending';
                                var badge = row.cells[2]?.querySelector('span');
                                if (badge) {
                                    badge.textContent = status;
                                    badge.className = 'px-2.5 py-0.5 rounded-md text-[11px] font-semibold ' + (status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800');
                                }
                                var reason = form.querySelector('[name="rejection_reason"]')?.value || '';
                                var overall = row.cells[3]?.querySelector('span');
                                if (overall) {
                                    var summary = clearanceQueue[itemId]?.all_items || [];
                                    var nowClear = status === 'Approved' && summary.every(function(entry) {
                                        return Number(entry.item_id) === Number(itemId) || entry.status === 'Approved';
                                    });
                                    var label = status === 'Rejected' ? 'Blocked' : (nowClear ? 'Fully Cleared' : 'Pending Holds');
                                    overall.textContent = label;
                                    overall.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold ' + (label === 'Fully Cleared' ? 'bg-emerald-100 text-emerald-800' : (label === 'Blocked' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'));
                                }
                                if (clearanceQueue[itemId]) {
                                    clearanceQueue[itemId].all_items = clearanceQueue[itemId].all_items.map(function(entry) {
                                        if (Number(entry.item_id) === Number(itemId)) {
                                            entry.status = status;
                                            entry.rejection_reason = reason;
                                        }
                                        return entry;
                                    });
                                    clearanceQueue[itemId].overall_status = status === 'Rejected' ? 'Action Needed' : (clearanceQueue[itemId].all_items.every(function(entry) {
                                        return entry.status === 'Approved';
                                    }) ? 'Cleared' : 'In Progress');
                                }
                                if (previousStatus !== status) {
                                    var oldCount = document.querySelector('[data-clearance-count="' + previousStatus + '"]');
                                    var newCount = document.querySelector('[data-clearance-count="' + status + '"]');
                                    if (oldCount) oldCount.textContent = String(Math.max(0, Number(oldCount.textContent) - 1));
                                    if (newCount) newCount.textContent = String(Number(newCount.textContent) + 1);
                                }
                                row.cells[4]?.querySelectorAll('button:not([onclick^="openOverallClearanceModal"])').forEach(function(button) {
                                    button.remove();
                                });
                            }
                            $('#modal_approve_confirm, #modal_reject_reason').addClass('hidden');
                            selectedStudentForApproval = null;
                            selectedClearanceItemId = null;
                            showToast('success', response.message || 'Clearance request updated.');
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
