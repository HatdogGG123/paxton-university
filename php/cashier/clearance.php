<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('cashier');
require_once __DIR__ . '/../../services/ClearanceService.php';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$clearanceModel = new Clearance(Database::getConnection());
$clearanceController = new ClearanceService($clearanceModel);
$notice = $_SESSION['cashier_clearance_notice'] ?? null;
unset($_SESSION['cashier_clearance_notice']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) { throw new DomainException('This form expired. Refresh the page and try again.'); }
        $clearanceController->review((int) $_SESSION['user']['id'], 'Cashier', null, $_POST);
        $_SESSION['cashier_clearance_notice'] = ['success' => true, 'message' => 'Cashier clearance updated.'];
        header('Location: clearance.php');
        exit;
    } catch (DomainException $exception) {
        $notice = ['success' => false, 'message' => $exception->getMessage()];
    } catch (Throwable $exception) {
        error_log('SSIS cashier clearance review failed: ' . $exception->getMessage());
        $notice = ['success' => false, 'message' => 'We could not update this clearance item. Please try again.'];
    }
}
try {
    $term = $clearanceModel->getCurrentTerm();
    if ($term) {
        $clearanceModel->ensureTermClearances((int) $term['id']);
        $queue = $clearanceModel->getOfficeQueue((int) $term['id'], 'Cashier');
        $summary = $clearanceModel->getSummary((int) $term['id']);
    } else { $queue = []; $summary = ['total' => 0, 'cleared' => 0, 'action_needed' => 0, 'in_progress' => 0]; }
} catch (Throwable $exception) {
    error_log('SSIS cashier clearance page failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Clearance information is temporarily unavailable. Please try again later.');
}
$esc = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$cashierQueue = [];
$cashierCounts = ['Approved' => 0, 'Rejected' => 0, 'Pending' => 0];
foreach ($queue as $item) { $cashierQueue[(int) $item['item_id']] = $item; $cashierCounts[$item['status']] = ($cashierCounts[$item['status']] ?? 0) + 1; }
$termLabel = $term ? $term['semester'] . ' ' . $term['academic_year'] : 'No current term';
$cashierName = trim((string) ($_SESSION['user']['full_name'] ?? ''));
if ($cashierName === '') { $cashierName = (string) ($_SESSION['user']['username'] ?? 'Cashier'); }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Cashier Clearance</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>


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

                <!-- Desktop Navigation Links (Cashier Role) -->
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Dashboard</a>
                    <a href="payments.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Payments & Reports</a>
                    <a href="clearance.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20">Clearance</a>
                    <a href="notifications.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Notifications</a>
                </nav>

                <!-- Action Controls -->
                <div class="flex items-center space-x-3">

                    <div class="h-5 w-px bg-gray-200 hidden md:block"></div>

                    <!-- User Info / Profile -->
                    <div class="hidden md:flex items-center space-x-2">
                        <div class="text-right">
                            <p id="nav_staff_name" class="text-xs font-semibold text-brand-dark"><?= $esc($cashierName) ?></p>
                            <p id="nav_staff_role" class="text-[10px] text-gray-500">Cashier Officer</p>
                        </div>
                        <a href="logout.php" id="nav_logout_btn" class="p-2 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 transition-colors" title="Log Out">
                            <i data-lucide="log-out" class="w-5 h-5"></i>
                        </a>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button id="mobile_menu_btn" type="button" class="md:hidden p-2 rounded-md text-gray-600 hover:text-brand-primary hover:bg-gray-100 focus:outline-none">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile_menu" class="hidden md:hidden border-t border-gray-200 bg-white px-4 pt-2 pb-4 space-y-1">
            <a href="dashboard.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Dashboard</a>
            <a href="payments.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Payments & Reports</a>
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">Clearance</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-brand-dark"><?= $esc($cashierName) ?></p>
                    <p class="text-xs text-gray-500">Cashier Officer</p>
                </div>
                <a href="logout.php" class="text-xs text-red-600 font-semibold hover:underline">Log Out</a>
            </div>
        </div>
    </header>
    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Cashier Financial Clearance</h1>
                <p class="text-sm text-gray-500">Manage student financial holds, review balance clearance requests, and issue clearance status approvals.</p>
            </div>
        </div>

        <!-- Alert Notification Container -->

        <!-- Clearance Summary Stats -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white border border-gray-200 rounded-md p-4 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Total Students Evaluated</p>
                    <h3 id="stat_total_students" class="text-xl font-bold text-brand-dark mt-1"><?= (int) $summary['total'] ?></h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">Term: <?= $esc($termLabel) ?></p>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-md p-4 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Cleared Status</p>
                    <h3 id="stat_cleared_count" class="text-xl font-bold text-emerald-700 mt-1"><?= $cashierCounts['Approved'] ?></h3>
                    <p class="text-[11px] text-emerald-700 font-semibold mt-0.5">98.8% Compliant</p>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-md p-4 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Active Financial Holds</p>
                    <h3 id="stat_holds_count" class="text-xl font-bold text-amber-700 mt-1"><?= $cashierCounts['Rejected'] ?></h3>
                    <p class="text-[11px] text-amber-700 font-semibold mt-0.5">Action Required</p>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-md p-4 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Pending Settlement Reviews</p>
                    <h3 id="stat_reviews_count" class="text-xl font-bold text-blue-700 mt-1"><?= $cashierCounts['Pending'] ?></h3>
                    <p class="text-[11px] text-blue-700 font-semibold mt-0.5">Proof Uploaded</p>
                </div>
            </div>
        </section>

        <!-- Search & Filter Controls -->
        <div class="bg-white border border-gray-200 rounded-md p-4 shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Search Student</label>
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 absolute left-3 top-2.5 text-gray-400"></i>
                    <input type="text" id="filter_search" placeholder="Student ID, Name..." class="w-full pl-9 pr-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-primary">
                </div>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Clearance Status</label>
                <select id="filter_status" class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-primary bg-white">
                    <option value="">All Statuses</option>
                    <option value="Approved">Cleared</option>
                    <option value="Rejected">Uncleared (On Hold)</option>
                    <option value="Pending">Pending Review</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Academic Program</label>
                <select id="filter_program" class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-primary bg-white">
                    <option value="">All Programs</option>
                    <option value="BSCS">BS Computer Science</option>
                    <option value="BSIT">BS Information Technology</option>
                    <option value="BSBA">BS Business Administration</option>
                </select>
            </div>
            <div class="flex items-end">
                <button id="btn_reset_filters" type="button" class="w-full py-1.5 border border-gray-200 text-xs font-semibold text-gray-600 rounded-md hover:bg-gray-50 transition-colors">
                    Reset Filters
                </button>
            </div>
        </div>

        <!-- Mobile-Optimized Clearance Table Container -->
        <div class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
            <!-- Header Summary -->
            <div class="p-3 sm:p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                <div class="flex items-center space-x-2">
                    <i data-lucide="list-checks" class="w-4 h-4 text-brand-primary"></i>
                    <h2 class="text-sm font-bold text-brand-dark">Student Cashier Clearance Directory</h2>
                </div>
                <span class="text-[11px] text-gray-500">Term: <?= $esc($termLabel) ?></span>
            </div>

            <!-- Scrollable Table Body -->
            <div class="overflow-x-auto min-w-full">
                <table id="table_clearance" class="w-full table-fixed text-left text-xs border-collapse min-w-[640px]">
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-3 w-28 whitespace-nowrap">Student ID</th>
                            <th class="py-3 px-3 min-w-[130px] max-w-[160px]">Student Name</th>
                            <th class="py-3 px-3 min-w-[140px] max-w-[180px]">Program & Year</th>
                            <th class="py-3 px-3 w-24 text-right whitespace-nowrap">Unpaid Balance</th>
                            <th class="py-3 px-3 min-w-[160px] max-w-[220px]">Hold Reason / Item</th>
                            <th class="py-3 px-3 w-28 text-center whitespace-nowrap">Clearance Status</th>
                            <th class="py-3 px-3 w-32 text-right whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                        <?php foreach ($queue as $item): $status = $item['status']; $year = (int) $item['year_level']; $yearText = $year === 1 ? '1st Yr' : ($year === 2 ? '2nd Yr' : ($year === 3 ? '3rd Yr' : $year . 'th Yr')); $statusText = $status === 'Approved' ? 'Cleared' : ($status === 'Rejected' ? 'Uncleared (Hold)' : 'Pending Review'); $statusStyle = $status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : ($status === 'Rejected' ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'); $reason = $status === 'Rejected' ? ($item['rejection_reason'] ?: $item['requirement_name']) : $item['requirement_name']; ?>
                        <tr class="hover:bg-gray-50/50 transition-colors" data-student="<?= $esc($item['student_no'] . ' ' . $item['student_name']) ?>" data-student-name="<?= $esc($item['student_name']) ?>" data-student-no="<?= $esc($item['student_no']) ?>" data-item-id="<?= (int) $item['item_id'] ?>" data-outstanding="<?= number_format((float) $item['outstanding_balance'], 2, '.', '') ?>" data-program="<?= $esc($item['program_code']) ?>" data-status="<?= $esc($status) ?>">
                            <td class="py-3 px-3 font-display font-bold text-brand-dark whitespace-nowrap"><?= $esc($item['student_no']) ?></td>
                            <td class="py-3 px-3 min-w-[130px] max-w-[160px]"><div class="font-bold text-brand-dark truncate" title="<?= $esc($item['student_name']) ?>"><?= $esc($item['student_name']) ?></div></td>
                            <td class="py-3 px-3 min-w-[140px] max-w-[180px]"><div class="truncate text-gray-600" title="<?= $esc($item['program_name'] . ' (' . $yearText . ')') ?>"><?= $esc($item['program_name']) ?> (<?= $esc($yearText) ?>)</div></td>
                            <td class="py-3 px-3 text-right font-bold <?= (float) $item['outstanding_balance'] > 0 ? 'text-red-700' : 'text-emerald-700' ?> whitespace-nowrap">₱<?= number_format((float) $item['outstanding_balance'], 2) ?></td>
                            <td class="py-3 px-3 min-w-[160px] max-w-[220px]"><div class="font-semibold text-gray-800 truncate" title="<?= $esc($reason) ?>"><?= $esc($reason) ?></div><div class="text-[10px] text-gray-500 truncate"><?= $status === 'Approved' ? 'Settlement: ' . $esc($item['remarks']) : $esc($item['description']) ?></div></td>
                            <td class="py-3 px-3 text-center whitespace-nowrap"><span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold <?= $statusStyle ?>"><?= $esc($statusText) ?></span></td>
                            <td class="py-3 px-3 text-right whitespace-nowrap space-x-1"><?php if ($status !== 'Approved'): ?><button type="button" class="js-clear-cashier-item px-2 py-1 bg-emerald-700 text-white rounded text-[11px] font-semibold hover:bg-emerald-800 transition-colors">Clear</button><?php endif; ?><?php if ($status === 'Pending'): ?><button type="button" class="js-reject-cashier-item px-2 py-1 border border-red-200 text-red-700 rounded text-[11px] font-semibold hover:bg-red-50">Reject</button><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (!$queue): ?><tr><td colspan="7" class="py-8 px-3 text-center text-gray-500">No cashier clearance items found for this term.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <form id="cashier_clearance_post" method="post" class="hidden">
            <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="item_id" id="cashier_post_item_id">
            <input type="hidden" name="status" value="Approved">
            <input type="hidden" name="rejection_reason" value="">
            <input type="hidden" name="remarks" id="cashier_post_remarks">
            <input type="hidden" name="settlement_reference" id="cashier_post_settlement_reference">
        </form>
    </main>

    <!-- MODAL 2: RELEASE / CLEAR HOLD -->
    <div id="modal_release_hold" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-md shadow-lg max-w-md w-full mx-4 overflow-hidden border border-gray-200">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-emerald-50">
                <div class="flex items-center space-x-2">
                    <div class="p-1 bg-emerald-100 text-emerald-800 rounded">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                    </div>
                    <h3 class="text-sm font-bold text-emerald-900">Release Cashier Hold</h3>
                </div>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-600 rounded p-1"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form id="form_release_hold" class="p-5 space-y-4">
                <input type="hidden" id="release_hold_id">

                <div class="bg-gray-50 p-3 rounded border border-gray-200 space-y-1 text-xs">
                    <p><span class="text-gray-500">Student:</span> <strong id="lbl_release_student" class="text-brand-dark">Pedro Penduko</strong></p>
                    <p><span class="text-gray-500">Outstanding Amount:</span> <strong id="lbl_release_amount" class="text-red-700">₱2,450.00</strong></p>
                </div>

                <div id="release_reference_group">
                    <label id="release_reference_label" class="block text-xs font-semibold text-gray-700 mb-1">Settlement Method / Reference</label>
                    <input type="text" id="release_reference" placeholder="Official Receipt No. or Payment Ref No." class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-brand-primary">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Clearance Remarks (Optional)</label>
                    <textarea id="release_remarks" rows="2" placeholder="e.g. Paid in full via Walk-in Cashier..." class="w-full p-2 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-brand-primary"></textarea>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-gray-100">
                    <button type="button" class="close-modal px-3.5 py-1.5 text-xs font-semibold text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="btn_submit_release" class="px-4 py-1.5 text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 rounded-md shadow-xs">Clear Hold Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: REJECT CLEARANCE ITEM -->
    <div id="modal_reject_clearance" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-md shadow-lg max-w-md w-full mx-4 overflow-hidden border border-gray-200">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-red-50">
                <div class="flex items-center space-x-2">
                    <div class="p-1 bg-red-100 text-red-800 rounded"><i data-lucide="circle-x" class="w-4 h-4"></i></div>
                    <h3 class="text-sm font-bold text-red-900">Reject Clearance Item</h3>
                </div>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-600 rounded p-1" aria-label="Close"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form id="form_reject_clearance" class="p-5 space-y-4">
                <input type="hidden" id="reject_clearance_item_id">
                <p class="text-xs text-gray-600">Enter a reason for rejecting <strong id="reject_clearance_student" class="text-brand-dark"></strong>'s clearance item.</p>
                <div>
                    <label for="reject_clearance_reason" class="block text-xs font-semibold text-gray-700 mb-1">Reason for rejection *</label>
                    <textarea id="reject_clearance_reason" rows="3" maxlength="500" required placeholder="Explain what needs to be corrected..." class="w-full p-2 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-brand-primary"></textarea>
                </div>
                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-gray-100">
                    <button type="button" class="close-modal px-3.5 py-1.5 text-xs font-semibold text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 rounded-md shadow-xs">Reject Clearance</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: VIEW CLEARANCE DETAILS -->
    <div id="modal_clearance_details" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-md shadow-lg max-w-md w-full mx-4 overflow-hidden border border-gray-200">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                <h3 class="text-sm font-bold text-brand-dark">Clearance Record Details</h3>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-600 rounded p-1"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-5 space-y-3 text-xs">
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500">Student ID & Name:</span>
                    <span id="detail_student" class="font-bold text-brand-dark">Pedro Penduko (2026-00012)</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500">Unpaid Balance:</span>
                    <span id="detail_balance" class="font-bold text-red-700">₱2,450.00</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500">Hold Item / Reason:</span>
                    <span id="detail_reason" class="font-medium text-gray-800">Unpaid Tuition Fee Balance</span>
                </div>
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-500">Current Status:</span>
                    <span id="detail_status" class="font-bold text-amber-800">Uncleared</span>
                </div>
            </div>
            <div class="p-4 bg-gray-50 border-t border-gray-100 flex justify-end">
                <button type="button" class="close-modal px-4 py-1.5 text-xs font-semibold text-gray-600 border border-gray-200 rounded-md hover:bg-gray-100">Close</button>
            </div>
        </div>
    </div>

    <!-- Formal Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500">
        <div class="max-w-7xl mx-auto px-4">
            <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Interactive Scripts -->
    <script>
        $(document).ready(function() {
            lucide.createIcons();
            $('#filter_search, #filter_status, #filter_program').on('input change', filterCashierTable);
            $('#btn_reset_filters').on('click', function() {
                $('#filter_search, #filter_status, #filter_program').val('');
                filterCashierTable();
            });
            $('#mobile_menu_btn').on('click', function() { $('#mobile_menu').toggleClass('hidden'); });
            $('.close-modal').on('click', function() { $('.fixed.inset-0').addClass('hidden'); });
            $('#table_clearance').on('click', '.js-clear-cashier-item', function() {
                const row = $(this).closest('tr');
                openReleaseModal(row.attr('data-item-id'), row.attr('data-student-name'), row.attr('data-student-no'), row.attr('data-outstanding'));
            });
            $('#table_clearance').on('click', '.js-reject-cashier-item', function() {
                const row = $(this).closest('tr');
                openRejectClearanceModal(row.attr('data-item-id'), row.attr('data-student-name'));
            });
            $('#form_reject_clearance').on('submit', function(event) {
                event.preventDefault();
                const reason = $('#reject_clearance_reason').val().trim();
                if (!reason) { $('#reject_clearance_reason').trigger('focus'); return; }
                $('#cashier_post_item_id').val($('#reject_clearance_item_id').val());
                const postForm = $('#cashier_clearance_post');
                postForm.find('[name="status"]').val('Rejected');
                postForm.find('[name="rejection_reason"]').val(reason);
                $('#cashier_post_remarks').val('');
                postForm.get(0).submit();
            });
            $('#form_issue_hold').on('submit', function(event) {
                event.preventDefault();
                $('#modal_issue_hold').addClass('hidden');
                showToast('error', 'Financial hold issue is not available from this page.');
            });
            $('#form_release_hold').on('submit', function(event) {
                event.preventDefault();
                const reference = $('#release_reference').val().trim();
                const balance = parseFloat($('#lbl_release_amount').attr('data-balance') || '0');
                if (balance > 0 && !reference) { $('#release_reference').trigger('focus'); return; }
                $('#cashier_post_item_id').val($('#release_hold_id').val());
                const extraRemarks = $('#release_remarks').val().trim();
                $('#cashier_post_settlement_reference').val(reference);
                $('#cashier_post_remarks').val(extraRemarks);
                const postForm = $('#cashier_clearance_post');
                postForm.find('[name="status"]').val('Approved');
                postForm.find('[name="rejection_reason"]').val('');
                postForm.get(0).submit();
            });
        });

        function filterCashierTable() {
            const query = ($('#filter_search').val() || '').toLowerCase();
            const status = $('#filter_status').val();
            const program = $('#filter_program').val();
            const table = document.getElementById('table_clearance');
            table.dataset.localPage = '1';
            $('#table_clearance tbody tr[data-student]').each(function() {
                const row = $(this);
                const visible = row.data('student').toLowerCase().includes(query)
                    && (!status || row.data('status') === status)
                    && (!program || row.data('program') === program);
                this.style.display = visible ? 'table-row' : 'none';
            });
        }

        // Global Modal Triggers
        function openReleaseModal(holdId, studentName, studentId, outstanding) {
            const balance = parseFloat(outstanding || '0');
            $('#cashier_clearance_post [name="status"]').val('Approved');
            $('#cashier_clearance_post [name="rejection_reason"]').val('');
            $('#release_hold_id').val(holdId);
            $('#lbl_release_student').text(studentName + ' (' + studentId + ')');
            $('#lbl_release_amount').text('₱' + balance.toFixed(2)).attr('data-balance', balance.toFixed(2));
            $('#release_reference_group').toggle(balance > 0);
            $('#release_reference').prop('required', balance > 0);
            $('#release_reference_label').text(balance > 0 ? 'Settlement Method / Reference *' : 'Settlement Method / Reference (Optional)');
            $('#release_reference, #release_remarks').val('');
            $('#cashier_post_settlement_reference').val('');
            $('#modal_release_hold').removeClass('hidden');
        }

        function openRejectClearanceModal(itemId, studentName) {
            $('#reject_clearance_item_id').val(itemId);
            $('#reject_clearance_student').text(studentName);
            $('#reject_clearance_reason').val('');
            $('#modal_reject_clearance').removeClass('hidden');
        }

        function openDetailsModal(studentId, studentName, balance, reason, status) {
            $('#detail_student').text(studentName + ' (' + studentId + ')');
            $('#detail_balance').text('₱' + balance);
            $('#detail_reason').text(reason);
            $('#detail_status').text(status);
            $('#modal_clearance_details').removeClass('hidden');
        }

        <?php if ($notice): ?>showToast("<?= !empty($notice['success']) ? 'success' : 'error' ?>", <?= json_encode($notice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);<?php endif; ?>
    </script>
    <script src="../../js/private-page-history.js"></script>
<script>
(function ($) {
    if (!$) return;
    $(document).on('submit', 'form[method="post"], form[method="POST"]', function (event) {
        if (event.isDefaultPrevented()) return;
        event.preventDefault();
        var form = this;
        var fields = $(form).serializeArray();
        if (event.originalEvent && event.originalEvent.submitter && event.originalEvent.submitter.name) {
            fields.push({ name: event.originalEvent.submitter.name, value: event.originalEvent.submitter.value });
        }
        if (window.location.pathname.toLowerCase().endsWith('/login.php')) fields.push({ name: 'action', value: 'handleUserLogin' });
        $.post('../../controller/clearanceController.php', $.param(fields), null, 'json')
            .done(function (response) {
                if (response && response.status === 'success') {
                    var itemId = form.querySelector('[name="item_id"]')?.value;
                    var status = form.querySelector('[name="status"]')?.value;
                    var row = document.querySelector('#table_clearance tr[data-item-id="' + Number(itemId) + '"]');
                    if (row && status) {
                        row.dataset.status = status;
                        var badge = row.cells[5]?.querySelector('span');
                        if (badge) { badge.textContent = status === 'Approved' ? 'Cleared' : 'Uncleared (Hold)'; badge.className = 'inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold ' + (status === 'Approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'); }
                        if (row.cells[6]) row.cells[6].textContent = 'Reviewed';
                    }
                    $('#modal_release_hold, #modal_reject_clearance').addClass('hidden');
                    showToast('success', response.message || 'Clearance request updated.');
                    return;
                } else {
                    var message = response && response.message ? response.message : 'The action could not be completed.';
                    showToast('error', message);
                }
            })
            .fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The action could not be completed. Please try again.';
                showToast('error', message);
                if (form.closest('body') && window.location.pathname.toLowerCase().endsWith('/login.php')) {
                    var button = document.getElementById('btn-submit');
                    if (button) { button.disabled = false; button.classList.remove('opacity-80', 'cursor-not-allowed'); button.innerHTML = 'Sign In'; }
                }
            });
    });
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if ((this.method || 'get').toLowerCase() === 'post') { this.requestSubmit(); return; }
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
