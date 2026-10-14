<?php
require_once __DIR__ . '/../includes/auth.php';

ssis_require_role('department');

require_once __DIR__ . '/../../services/ClearanceService.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$clearanceModel = new Clearance(Database::getConnection());
$clearanceController = new ClearanceService($clearanceModel);

$notice = $_SESSION['department_clearance_notice'] ?? null;

unset($_SESSION['department_clearance_notice']);

$departmentId = $clearanceModel->getDepartmentId((int) $_SESSION['user']['id']);
$departmentName = $clearanceModel->getDepartmentName($departmentId) ?? 'Department';

$staffName = $_SESSION['user']['full_name'] ?: $_SESSION['user']['username'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';

        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new DomainException('This form expired. Refresh the page and try again.');
        }

        $clearanceController->review((int) $_SESSION['user']['id'], 'Department', $departmentId, $_POST);
        $_SESSION['department_clearance_notice'] = ['success' => true, 'message' => 'Clearance decision saved and student notified.'];

        header('Location: clearance.php');
        exit;
    } catch (DomainException $exception) {
        $notice = ['success' => false, 'message' => $exception->getMessage()];
    } catch (Throwable $exception) {
        error_log('SSIS department clearance review failed: ' . $exception->getMessage());
        $notice = ['success' => false, 'message' => 'We could not update this clearance item. Please try again.'];
    }
}
try {
    $term = $clearanceModel->getCurrentTerm();

    if ($term && $departmentId !== null) {
        $clearanceModel->ensureTermClearances((int) $term['id']);
        $queue = $clearanceModel->getOfficeQueue((int) $term['id'], 'Department', $departmentId);
        $requirements = $clearanceModel->getDepartmentRequirements($departmentId);
    } else {
        $queue = [];
        $requirements = [];
    }
} catch (Throwable $exception) {
    error_log('SSIS department clearance page failed: ' . $exception->getMessage());
    http_response_code(503);

    exit('Clearance information is temporarily unavailable. Please try again later.');
}

$esc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$queueData = [];
$pendingCount = $approvedCount = $rejectedCount = 0;

foreach ($queue as $item) {
    $queueData[(int) $item['item_id']] = $item;

    if ($item['status'] === 'Pending') {
        $pendingCount++;
    } elseif ($item['status'] === 'Approved') {
        $approvedCount++;
    } else {
        $rejectedCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Department Clearance Review</title>
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
                    <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Clearance Approval</a>
                    <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Reports</a>
                    <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
                </nav>

                <!-- Profile Info & Mobile Toggle -->
                <div class="flex items-center space-x-3">
                    <div class="hidden lg:flex items-center space-x-2">
                        <div class="text-right">
                            <p class="text-xs font-semibold text-brand-dark"><?= $esc($staffName) ?></p>
                            <p class="text-[10px] text-gray-500 uppercase font-bold"><?= $esc($departmentName) ?></p>
                        </div>
                    </div>

                    <!-- Mobile Menu Button -->
                    <a href="../logout.php" id="nav_logout_btn" class="p-2 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 transition-colors" title="Log Out">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                    </a>
                    <button type="button" id="btn_mobile_menu" class="lg:hidden p-2 rounded-md text-gray-500 hover:text-brand-dark hover:bg-gray-100 focus:outline-none">
                        <i data-lucide="menu" id="icon_menu_open" class="w-6 h-6"></i>
                        <i data-lucide="x" id="icon_menu_close" class="w-6 h-6 hidden"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Dropdown Container -->
        <div id="mobile_nav_menu" class="hidden lg:hidden border-t border-gray-200 bg-white px-4 pt-2 pb-4 space-y-1 text-xs shadow-md">
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Clearance Queue</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Reports</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between px-3">
                <div>
                    <p class="text-xs font-semibold text-brand-dark"><?= $esc($staffName) ?></p>
                    <p class="text-[10px] text-gray-500 uppercase font-bold"><?= $esc($departmentName) ?></p>
                </div>
                <a href="../logout.php" class="text-xs text-red-600 font-semibold hover:underline">Log Out</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 ">
        <!-- Header & Quick Dashboard Stat -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-6">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Department Clearance Queue</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-brand-accent/40 text-brand-primary border border-brand-secondary/30"><?= $esc($departmentName) ?></span>
                </div>
                <p class="text-sm text-gray-500 mt-1">Review student clearance requests assigned to your department.</p>
            </div>

            <!-- Dashboard Metric Section -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:w-auto shrink-0">
                <div class="bg-amber-50 border border-amber-200 rounded-md px-4 py-2.5 text-center min-w-[120px]">
                    <span class="text-[9px] uppercase font-bold text-amber-700 tracking-wider block">Pending Review</span>
                    <span id="stat_pending_count" class="text-xl font-bold text-amber-800"><?= $pendingCount ?></span>
                </div>
                <div class="bg-emerald-50 border border-emerald-200 rounded-md px-4 py-2.5 text-center min-w-[120px]">
                    <span class="text-[9px] uppercase font-bold text-emerald-700 tracking-wider block">Approved</span>
                    <span id="stat_approved_count" class="text-xl font-bold text-emerald-800"><?= $approvedCount ?></span>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-md px-4 py-2.5 text-center min-w-[120px]">
                    <span class="text-[9px] uppercase font-bold text-red-700 tracking-wider block">Rejected</span>
                    <span id="stat_rejected_count" class="text-xl font-bold text-red-800"><?= $rejectedCount ?></span>
                </div>
            </div>
        </section>

        <!-- Department Active Requirements Accordion / Banner -->
        <section class="bg-white border border-gray-200 rounded-md p-5 shadow-xs">
            <div class="flex items-center justify-between cursor-pointer" id="toggle_req_panel">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-brand-accent/30 text-brand-primary rounded-md">
                        <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-brand-dark">Active Clearance Requirements (<?= $esc($departmentName) ?>)</h2>
                        <p class="text-xs text-gray-500">Requirements evaluated for students in your department</p>
                    </div>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="chevron-down" id="icon_req_chevron" class="w-5 h-5 transition-transform duration-200"></i>
                </button>
            </div>

            <div id="req_panel_content" class="mt-4 pt-4 border-t border-gray-100 hidden">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                    <?php foreach ($requirements as $requirement): ?>
                        <div class="p-3 bg-gray-50 border border-gray-200 rounded-md">
                            <p class="font-bold text-brand-dark"><?= $esc($requirement['name']) ?></p>
                            <p class="text-[11px] text-gray-500 mt-0.5"><?= $esc($requirement['description']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Search, Filter & Clearance Action List -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
            <!-- Filter Toolbar -->
            <div class="p-5 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
                <div>
                    <h2 class="text-base font-bold text-brand-dark">Student Clearance Requests</h2>
                    <p class="text-xs text-gray-500">Filtered strictly by programs under <?= $esc($departmentName) ?></p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Search Input -->
                    <div class="relative min-w-[220px]">
                        <i data-lucide="search" class="w-3.5 h-3.5 text-gray-400 absolute left-3 top-2.5"></i>
                        <input type="text" id="input_search" placeholder="Search ID or Name..." class="w-full pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary bg-white">
                    </div>

                    <!-- Program Filter -->
                    <select id="filter_program" class="px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary bg-white">
                        <option value="">All Programs</option>
                        <option value="BSCS">BS Computer Science</option>
                        <option value="BSIT">BS Information Technology</option>
                        <option value="BSIS">BS Information Systems</option>
                    </select>

                    <!-- Year Level Filter -->
                    <select id="filter_year" class="px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary bg-white">
                        <option value="">All Year Levels</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                    </select>

                    <!-- Clearance Status Filter -->
                    <select id="filter_status" class="px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary bg-white">
                        <option value="">All Statuses</option>
                        <option value="Pending" selected>Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
            </div>

            <!-- Student Clearance Table (Responsive horizontal scroll with fixed column layout) -->
            <div class="overflow-x-auto w-full">
                <table class="w-full text-left text-xs border-collapse min-w-[900px]" style="table-layout:fixed;min-width:1100px">
                    <colgroup>
                        <col style="width:22%">
                        <col style="width:21%">
                        <col style="width:26%">
                        <col style="width:16%">
                        <col style="width:15%">
                    </colgroup>
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase font-semibold">
                            <th class="py-3.5 px-5 whitespace-nowrap">Student Information</th>
                            <th class="py-3.5 px-5 whitespace-nowrap">Program & Year</th>
                            <th class="py-3.5 px-5">Requirement Item</th>
                            <th class="py-3.5 px-5 text-center whitespace-nowrap">Department Status</th>
                            <th class="py-3.5 px-5 text-right whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="clearance_table_body" class="divide-y divide-gray-100 font-medium text-gray-700">
                        <?php foreach ($queue as $item): $year = (int) $item['year_level'] . 'th Year';
                            $year = match ((int) $item['year_level']) {
                                1 => '1st Year',
                                2 => '2nd Year',
                                3 => '3rd Year',
                                default => $year
                            };
                            $status = $item['status'];
                            $statusStyle = $status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : ($status === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'); ?>
                            <tr class="hover:bg-gray-50/50 student-row" data-item-id="<?= (int) $item['item_id'] ?>" data-program="<?= $esc($item['program_code']) ?>" data-year="<?= $esc($year) ?>" data-status="<?= $esc($status) ?>">
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <p class="font-bold text-brand-dark"><?= $esc($item['student_name']) ?></p>
                                    <p class="text-gray-400 text-[11px] font-display"><?= $esc($item['student_no']) ?></p>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <p class="font-semibold text-brand-dark"><?= $esc($item['program_name']) ?></p>
                                    <p class="text-gray-400 text-[11px]"><?= $esc($year) ?></p>
                                </td>
                                <td class="py-3.5 px-5 whitespace-nowrap">
                                    <p class="font-medium text-gray-800"><?= $esc($item['requirement_name']) ?></p>
                                    <p data-clearance-reviewed class="text-gray-400 text-[10px]"><?= $status === 'Pending' ? 'Awaiting department review' : $esc($item['reviewed_at'] ? date('M j, Y', strtotime($item['reviewed_at'])) : '') ?></p>
                                </td>
                                <td class="py-3.5 px-5 text-center whitespace-nowrap"><span data-clearance-status class="px-2.5 py-0.5 rounded-full text-[10px] font-bold <?= $statusStyle ?>"><?= $status === 'Pending' ? 'Pending Review' : $esc($status) ?></span></td>
                                <td class="py-3.5 px-5 text-right space-x-1 whitespace-nowrap"><button type="button" onclick="openReviewModal(<?= (int) $item['item_id'] ?>)" class="px-2.5 py-1.5 rounded-md text-xs font-semibold <?= $status === 'Pending' ? 'bg-brand-primary text-white hover:bg-brand-primary/90' : 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50' ?>"><?= $status === 'Pending' ? 'Review' : 'View Details' ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$queue): ?><tr>
                                <td colspan="5" class="py-8 px-5 text-center text-gray-500">No clearance requests are assigned to your department.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

        </section>
        <form id="clearance_post_form" method="post" class="hidden">
            <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="item_id" id="post_item_id">
            <input type="hidden" name="status" id="post_status">
            <input type="hidden" name="rejection_reason" id="post_rejection_reason">
            <input type="hidden" name="remarks" value="">
        </form>
    </main>

    <!-- Clearance Review / Action Modal -->
    <div id="modal_review" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">

            <!-- Modal Header -->
            <div class="p-5 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-brand-dark">Department Clearance Decision</h3>
                    <p class="text-xs text-gray-500">Review departmental submission and approve or reject request</p>
                </div>
                <button type="button" onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Modal Content Body -->
            <form id="form_clearance_action" onsubmit="handleClearanceSubmit(event)" class="p-6 space-y-5 text-xs">

                <!-- Student Header Card (Strictly scoped fields) -->
                <div class="p-4 bg-gray-50 border border-gray-200 rounded-md space-y-1">
                    <div class="flex items-center justify-between">
                        <p id="modal_student_name" class="font-bold text-sm text-brand-dark">Alex Santos</p>
                        <span id="modal_current_badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">Pending</span>
                    </div>
                    <p id="modal_student_id_prog" class="text-gray-500 font-display">2026-01042 • BS Computer Science (4th Year)</p>
                </div>

                <!-- Clearance Requirement Detail -->
                <div>
                    <label class="block text-[11px] uppercase font-bold text-gray-400 mb-1">Clearance Requirement Item</label>
                    <div id="modal_req_title" class="p-3 bg-white border border-gray-200 rounded-md font-semibold text-brand-dark">
                        Capstone Source Code & Documentation
                    </div>
                </div>

                <!-- Rejection Reason Input Field (Required when Rejecting) -->
                <div id="rejection_reason_container" class="space-y-1">
                    <label for="reject_reason" class="block font-bold text-gray-700">
                        Reason for Rejection <span class="text-red-500">*</span>
                    </label>
                    <textarea id="reject_reason" rows="3" placeholder="Provide a detailed explanation for why this item is rejected (e.g. Missing lab kit, incomplete files)..." class="w-full p-2.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary bg-white"></textarea>
                    <p id="reject_error_msg" class="text-[11px] text-red-600 hidden">Please provide a reason before rejecting this clearance request.</p>
                </div>

                <!-- Privacy Disclaimer Guard -->
                <div class="p-3 bg-blue-50/60 border border-blue-100 rounded-md flex items-start space-x-2 text-[11px] text-blue-800">
                    <i data-lucide="shield-check" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5"></i>
                    <span>Department access is strictly restricted to department clearance verification. Grades and tuition payment records are withheld under policy regulations.</span>
                </div>

                <!-- Modal Actions -->
                <div class="pt-3 border-t border-gray-200 flex items-center justify-end space-x-2">
                    <button type="button" onclick="closeReviewModal()" class="px-4 py-2 font-semibold text-xs border border-gray-200 bg-white text-gray-700 rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="button" id="btn_reject" onclick="submitDecision('Rejected')" class="px-4 py-2 font-semibold text-xs bg-red-600 text-white rounded-md hover:bg-red-700">Reject Request</button>
                    <button type="button" id="btn_approve" onclick="submitDecision('Approved')" class="px-4 py-2 font-semibold text-xs bg-brand-primary text-white rounded-md hover:bg-brand-primary/90">Approve Clearance</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500">
        <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
    </footer>

    <!-- Interactive Scripts -->

    <script>
        const departmentQueue = <?= json_encode($queueData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        let currentDecision = '';
        let currentItemId = null;
        <?php if ($notice): ?>showToast("<?= !empty($notice['success']) ? 'success' : 'error' ?>", <?= json_encode($notice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        <?php endif; ?>

        $(document).ready(function() {
            lucide.createIcons();

            // Toggle Requirements accordion
            $('#toggle_req_panel').on('click', function() {
                $('#req_panel_content').toggleClass('hidden');
                $('#icon_req_chevron').toggleClass('rotate-180');
            });

            // Filter functionality
            $('#input_search, #filter_program, #filter_year, #filter_status').on('input change', function() {
                filterTable();
            });

            // Mobile Navigation Toggle Handler
            $('#btn_mobile_menu').on('click', function(e) {
                e.preventDefault();

                // Toggle dropdown visibility
                $('#mobile_nav_menu').toggleClass('hidden');

                // Toggle hamburger and close icons
                $('#icon_menu_open').toggleClass('hidden');
                $('#icon_menu_close').toggleClass('hidden');
            });
        });

        function filterTable() {
            const searchVal = $('#input_search').val().toLowerCase();
            const programVal = $('#filter_program').val();
            const yearVal = $('#filter_year').val();
            const statusVal = $('#filter_status').val();

            $('.student-row').each(function() {
                const text = $(this).text().toLowerCase();
                const prog = $(this).data('program');
                const year = $(this).data('year');
                const status = $(this).attr('data-status');

                const matchesSearch = text.includes(searchVal);
                const matchesProg = !programVal || prog === programVal;
                const matchesYear = !yearVal || year === yearVal;
                const matchesStatus = !statusVal || status === statusVal;

                this.dataset.filteredOut = String(!(matchesSearch && matchesProg && matchesYear && matchesStatus));
            });
            document.dispatchEvent(new Event('local-table-refresh'));
        }

        function openReviewModal(itemId) {
            const item = departmentQueue[itemId];
            if (!item) return;
            currentItemId = Number(itemId);
            const year = Number(item.year_level) === 1 ? '1st Year' : (Number(item.year_level) === 2 ? '2nd Year' : (Number(item.year_level) === 3 ? '3rd Year' : `${item.year_level}th Year`));
            const studentId = item.student_no;
            const name = item.student_name;
            const program = item.program_name;
            const requirementTitle = item.requirement_name;
            const status = item.status;
            const existingReason = item.rejection_reason || '';
            $('#modal_student_name').text(name);
            $('#modal_student_id_prog').text(`${studentId} • ${program} (${year})`);
            $('#modal_req_title').text(requirementTitle);
            $('#reject_reason').val(existingReason);
            $('#reject_error_msg').addClass('hidden');

            const badge = $('#modal_current_badge');
            badge.removeClass('bg-amber-100 text-amber-800 bg-emerald-100 text-emerald-800 bg-red-100 text-red-800');

            if (status === 'Approved') {
                badge.addClass('bg-emerald-100 text-emerald-800').text('Approved');
            } else if (status === 'Rejected') {
                badge.addClass('bg-red-100 text-red-800').text('Rejected');
            } else {
                badge.addClass('bg-amber-100 text-amber-800').text('Pending Review');
            }

            $('#modal_review').removeClass('hidden');
        }

        function closeReviewModal() {
            $('#modal_review').addClass('hidden');
        }

        function updateClearanceRow(itemId, status, rejectionReason) {
            const item = departmentQueue[itemId];
            if (!item || !['Approved', 'Rejected'].includes(status)) return;

            const previousStatus = item.status;
            if (previousStatus !== status) {
                const countIds = {
                    Pending: 'stat_pending_count',
                    Approved: 'stat_approved_count',
                    Rejected: 'stat_rejected_count'
                };
                const previousCount = document.getElementById(countIds[previousStatus]);
                const nextCount = document.getElementById(countIds[status]);
                if (previousCount) previousCount.textContent = String(Math.max(0, Number(previousCount.textContent) - 1));
                if (nextCount) nextCount.textContent = String(Number(nextCount.textContent) + 1);
            }

            item.status = status;
            item.rejection_reason = status === 'Rejected' ? rejectionReason : '';
            const row = document.querySelector(`.student-row[data-item-id="${Number(itemId)}"]`);
            if (row) {
                row.dataset.status = status;
                const badge = row.querySelector('[data-clearance-status]');
                if (badge) {
                    badge.className = 'px-2.5 py-0.5 rounded-full text-[10px] font-bold ' + (status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800');
                    badge.textContent = status;
                }
                const reviewed = row.querySelector('[data-clearance-reviewed]');
                if (reviewed) reviewed.textContent = 'Reviewed just now';
                const action = row.querySelector('button[onclick^="openReviewModal"]');
                if (action) {
                    action.textContent = 'View Details';
                    action.className = 'px-2.5 py-1.5 rounded-md text-xs font-semibold border border-gray-200 bg-white text-gray-700 hover:bg-gray-50';
                }
            }
            filterTable();
        }

        function submitDecision(type) {
            currentDecision = type;
            const reason = $('#reject_reason').val().trim();

            // Hide previous error message
            $('#reject_error_msg').addClass('hidden');

            // Require reason if rejected[cite: 11]
            if (type === 'Rejected' && !reason) {
                $('#reject_error_msg').removeClass('hidden');
                return;
            }

            $('#post_item_id').val(currentItemId);
            $('#post_status').val(type);
            $('#post_rejection_reason').val(type === 'Rejected' ? reason : '');
            $('#clearance_post_form').trigger('submit');
        }
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
                            updateClearanceRow(form.querySelector('[name="item_id"]').value, form.querySelector('[name="status"]').value, form.querySelector('[name="rejection_reason"]').value);
                            $('#modal_review').addClass('hidden');
                            showToast('success', response.message || 'Clearance request updated.');
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
                return Array.from(table.tBodies[0]?.rows || []).filter(row => !row.classList.contains('table-empty-state') && !row.querySelector('td[colspan]') && row.dataset.filteredOut !== 'true');
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
                const dataRows = allRows.filter(row => !row.classList.contains('table-empty-state') && !row.querySelector('td[colspan]'));
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
                if (!rows.length) {
                    let emptyRow = allRows.find(row => row.classList.contains('table-empty-state') || row.querySelector('td[colspan]'));
                    if (!emptyRow) {
                        emptyRow = document.createElement('tr');
                        emptyRow.className = 'table-empty-state';
                        const cell = document.createElement('td');
                        cell.className = 'py-8 px-5 text-center text-gray-500';
                        cell.colSpan = table.tHead?.rows[0]?.cells.length || 1;
                        emptyRow.appendChild(cell);
                        body.appendChild(emptyRow);
                    }
                    emptyRow.hidden = false;
                    const cell = emptyRow.querySelector('td');
                    if (cell) cell.textContent = dataRows.length ? 'No clearance requests match the selected filters.' : 'No clearance requests are assigned to your department.';
                } else {
                    allRows.filter(row => row.classList.contains('table-empty-state') || row.querySelector('td[colspan]')).forEach(row => row.remove());
                }
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
            document.addEventListener('local-table-refresh', scheduleRefresh);
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