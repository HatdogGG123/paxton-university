<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('registrar');
require_once __DIR__ . '/../../services/DocumentRequestsService.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$requestModel = new DocumentRequests(Database::getConnection());
$requestController = new DocumentRequestsService($requestModel);
$notice = $_SESSION['registrar_request_notice'] ?? null;
unset($_SESSION['registrar_request_notice']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new DomainException('This form expired. Refresh the page and try again.');
        }
        if (($_POST['request_action'] ?? '') === 'reject') {
            $requestController->reject((int) $_SESSION['user']['id'], $_POST);
        } elseif (($_POST['request_action'] ?? '') === 'transition') {
            $requestController->updateStatus((int) $_SESSION['user']['id'], $_POST);
        } else {
            throw new DomainException('Choose a valid request action.');
        }
        $_SESSION['registrar_request_notice'] = ['success' => true, 'message' => 'Document request updated.'];
        header('Location: documentRequest.php');
        exit;
    } catch (DomainException $exception) {
        $notice = ['success' => false, 'message' => $exception->getMessage()];
    } catch (Throwable $exception) {
        error_log('SSIS registrar document request update failed: ' . $exception->getMessage());
        $notice = ['success' => false, 'message' => 'We could not update this document request. Please try again.'];
    }
}
try {
    $queue = $requestModel->getRegistrarQueue();
    $types = $requestModel->getActiveTypes();
} catch (Throwable $exception) {
    error_log('SSIS registrar document requests page failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Document request information is temporarily unavailable. Please try again later.');
}
$esc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$registrarName = $_SESSION['user']['full_name'] ?: $_SESSION['user']['username'];
$pendingCount = count(array_filter($queue, static fn($row): bool => $row['status'] === 'Pending'));
$processingCount = count(array_filter($queue, static fn($row): bool => in_array($row['status'], ['Processing', 'Paid'], true)));
$readyCount = count(array_filter($queue, static fn($row): bool => $row['status'] === 'Ready for Release'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Document Request Management</title>
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

    <!-- Navigation Header with Mobile Menu Support -->
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

                <!-- Desktop Navigation Links (Hidden on Mobile) -->
                <nav class="hidden lg:flex items-center space-x-1 text-xs">
                    <a href="enrollment.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Enrollment</a>
                    <a href="grades.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Grades</a>
                    <a href="documentRequest.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Document Requests</a>
                    <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
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

                    <!-- Mobile Menu Button (Visible only on small screens) -->
                    <a href="logout.php" id="nav_logout_btn" class="p-2 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 transition-colors" title="Log Out">
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
            <a href="enrollment.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Enrollment</a>
            <a href="grades.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Grades</a>
            <a href="documentRequest.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Document Requests</a>
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Report</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <a href="announcements.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Announcements</a>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between px-3">
                <div>
                    <p class="text-xs font-semibold text-brand-dark"><?= $esc($registrarName) ?></p>
                    <p class="text-[10px] text-gray-500 uppercase font-bold">Registrar Module</p>
                </div>
                <a href="logout.php" class="text-xs text-red-600 font-semibold hover:underline">Log Out</a>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col xl:flex-row xl:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center space-x-2">
                    <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Document Request Queue</h1>
                </div>
                <p class="text-sm text-gray-500">Process transcripts, certifications, and diplomas through authorized statuses, manage rejections, and confirm pickups.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Pending</span>
                    <span class="text-base font-bold text-amber-700" id="cnt_pending"><?= $pendingCount ?></span>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Processing</span>
                    <span class="text-base font-bold text-blue-700" id="cnt_processing"><?= $processingCount ?></span>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Ready / Pickup</span>
                    <span class="text-base font-bold text-purple-700" id="cnt_ready"><?= $readyCount ?></span>
                </div>
            </div>
        </section>

        <section class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
            <div class="space-y-4">
                <!-- Filter Bar (Required by rule: "queue with filters") -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white p-3 rounded-lg border border-gray-100 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                        <!-- Filter by Status (Section 12) -->
                        <select id="filter_status" class="text-xs bg-gray-50 border border-gray-200 text-gray-700 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                            <option value="">All Statuses</option>
                            <?php foreach (['Pending', 'Under Review', 'For Payment', 'Paid', 'Processing', 'Ready for Release', 'Completed', 'Rejected', 'Cancelled'] as $statusOption): ?><option value="<?= $esc($statusOption) ?>"><?= $esc($statusOption) ?></option><?php endforeach; ?>
                        </select>

                        <!-- Filter by Document -->
                        <select id="filter_doc" class="text-xs bg-gray-50 border border-gray-200 text-gray-700 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-primary/20">
                            <option value="">All Documents</option>
                            <?php foreach ($types as $type): ?><option value="<?= $esc($type['code']) ?>"><?= $esc($type['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="w-full sm:w-64">
                        <input type="text" id="search_queue" placeholder="Search Ref # or Student..." class="w-full text-xs bg-gray-50 border border-gray-200 text-gray-700 rounded-md px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-brand-primary/20" />
                    </div>
                </div>

                <!-- Document Requests Table -->
                <div class="overflow-x-auto border border-gray-100 rounded-lg bg-white">
                    <table id="table_documentRequests" class="w-full min-w-[1120px] table-fixed text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase font-semibold">
                                <th class="py-3 px-5 w-[16%] whitespace-nowrap">Ref # & Date</th>
                                <th class="py-3 px-5 w-[21%] whitespace-nowrap">Student Information</th>
                                <th class="py-3 px-5 w-[27%]">Requested Document</th>
                                <th class="py-3 px-5 text-center w-[15%] whitespace-nowrap">Status (Section 12)</th>
                                <th class="py-3 px-5 text-right w-[21%] whitespace-nowrap">Queue Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_documentRequests" class="divide-y divide-gray-100 text-gray-700 font-medium">
                            <?php foreach ($queue as $request): $status = $request['status'];
                                $badgeStyle = match ($status) {
                                    'Pending', 'Under Review' => 'bg-amber-100 text-amber-800',
                                    'For Payment' => 'bg-orange-100 text-orange-800',
                                    'Paid', 'Completed' => 'bg-emerald-100 text-emerald-800',
                                    'Processing' => 'bg-blue-100 text-blue-800',
                                    'Ready for Release' => 'bg-purple-100 text-purple-800',
                                    'Rejected' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-700'
                                };
                                $jsonFlags = JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP;
                                $jsRef = htmlspecialchars(json_encode($request['request_no'], $jsonFlags), ENT_QUOTES, 'UTF-8');
                                $jsName = htmlspecialchars(json_encode($request['student_name'], $jsonFlags), ENT_QUOTES, 'UTF-8');
                                $jsDoc = htmlspecialchars(json_encode($request['document_name'], $jsonFlags), ENT_QUOTES, 'UTF-8');
                                $jsStatus = htmlspecialchars(json_encode($status, $jsonFlags), ENT_QUOTES, 'UTF-8');
                                $jsFeeAmount = htmlspecialchars(json_encode((float) $request['fee_amount'], $jsonFlags), ENT_QUOTES, 'UTF-8');
                                $jsPaymentStatus = htmlspecialchars(json_encode($request['payment_status'], $jsonFlags), ENT_QUOTES, 'UTF-8'); ?>
                                <tr class="hover:bg-gray-50/50 <?= $status === 'Ready for Release' ? 'bg-purple-50/10' : '' ?>" data-request-no="<?= $esc($request['request_no']) ?>" data-student-name="<?= $esc($request['student_name']) ?>" data-document-name="<?= $esc($request['document_name']) ?>" data-fee="<?= (float) $request['fee_amount'] ?>" data-payment-status="<?= $esc($request['payment_status']) ?>" data-status="<?= $esc($status) ?>" data-doc="<?= $esc($request['document_code']) ?>" data-search="<?= $esc($request['request_no'] . ' ' . $request['student_name'] . ' ' . $request['student_no']) ?>">
                                    <td class="py-3.5 px-5 space-y-0.5 whitespace-nowrap">
                                        <p class="font-display font-bold text-brand-primary"><?= $esc($request['request_no']) ?></p>
                                        <p class="text-gray-400 text-[11px]"><?= $esc(date('M d, Y • h:i A', strtotime($request['created_at']))) ?></p>
                                    </td>
                                    <td class="py-3.5 px-5 space-y-0.5 whitespace-nowrap">
                                        <p class="font-bold text-brand-dark"><?= $esc($request['student_name']) ?></p>
                                        <p class="text-gray-400 text-[11px] font-display"><?= $esc($request['student_no']) ?> (<?= $esc($request['program_code']) ?>)</p>
                                    </td>
                                    <td class="py-3.5 px-5 space-y-0.5 min-w-[200px]">
                                        <p class="font-semibold text-brand-dark"><?= $esc($request['document_name']) ?></p>
                                        <p class="text-gray-400 text-[11px]">Qty: <?= (int) $request['copies'] ?> <?= (int) $request['copies'] === 1 ? 'Copy' : 'Copies' ?> • <?= (float) $request['fee_amount'] > 0 ? '₱' . number_format((float) $request['fee_amount'] * (int) $request['copies'], 2) : 'Free' ?><?php if ($request['payment_status']): ?> • Payment: <?= $esc($request['payment_status']) ?><?php endif; ?></p>
                                    </td>
                                    <td class="py-3.5 px-5 text-center whitespace-nowrap"><span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-semibold <?= $badgeStyle ?>"><?= $esc($status) ?></span></td>
                                    <td class="py-3.5 px-5 text-right space-x-1 whitespace-nowrap">
                                        <?php if (in_array($status, ['Pending', 'Under Review', 'Processing'], true) || ($status === 'Paid' && $request['payment_status'] === 'Paid')): ?><button type="button" onclick="openWorkflowModal(<?= $jsRef ?>, <?= $jsName ?>, <?= $jsDoc ?>, <?= $jsStatus ?>, <?= $jsFeeAmount ?>, <?= $jsPaymentStatus ?>)" class="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white hover:bg-brand-primary/90"><i data-lucide="play" class="w-3.5 h-3.5"></i><span>Update Status</span></button><?php endif; ?>
                                        <?php if ($status === 'Ready for Release'): ?><button type="button" onclick="openPickupCompletionModal(<?= $jsRef ?>, <?= $jsName ?>)" class="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-md text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i><span>Mark Completed</span></button><?php endif; ?>
                                        <?php if (in_array($status, ['Pending', 'Under Review'], true)): ?><button type="button" onclick="openRejectModal(<?= $jsRef ?>)" class="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-md text-xs font-semibold border border-red-200 text-red-600 bg-white hover:bg-red-50"><i data-lucide="x-circle" class="w-3.5 h-3.5"></i><span>Reject</span></button><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($queue === []): ?><tr>
                                    <td colspan="5" class="py-8 px-5 text-center text-gray-500">No document requests are waiting in the queue.</td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <form id="registrar_request_post" method="post" class="hidden">
            <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="request_action" id="post_request_action">
            <input type="hidden" name="request_no" id="post_request_no">
            <input type="hidden" name="status" id="post_request_status">
            <input type="hidden" name="rejection_reason" id="post_rejection_reason">
            <input type="hidden" name="received_by" id="post_received_by">
        </form>
    </main>

    <!-- Modal 1: Update Status -->
    <div id="modal_status_workflow" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
            <div class="p-5 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
                <h3 class="text-base font-bold text-brand-dark">Update Request Status</h3>
                <button type="button" onclick="closeModal('modal_status_workflow')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 space-y-4 text-xs">
                <div class="p-3 bg-gray-50 border border-gray-200 rounded-md space-y-1">
                    <p id="wf_ref_num" class="font-display font-bold text-brand-primary text-sm">REQ-2026-0891</p>
                    <p id="wf_student_name" class="font-bold text-brand-dark">John Doe</p>
                </div>
                <div class="space-y-2">
                    <label for="select_next_status" class="block font-bold text-brand-dark">Next Allowed Status:</label>
                    <select id="select_next_status" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-md bg-white"></select>
                </div>
            </div>
            <div class="p-5 border-t border-gray-200 bg-gray-50/50 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modal_status_workflow')" class="px-4 py-2 font-semibold text-gray-600">Cancel</button>
                <button type="button" onclick="confirmStatusUpdate()" class="px-4 py-2 font-semibold text-xs bg-brand-primary text-white rounded-md">Update Status</button>
            </div>
        </div>
    </div>

    <!-- Modal 2: Reject -->
    <div id="modal_reject_request" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
            <div class="p-5 border-b border-gray-200 bg-red-50/60 flex items-center justify-between">
                <h3 class="text-base font-bold text-red-900">Reject Document Request</h3>
                <button type="button" onclick="closeModal('modal_reject_request')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 space-y-4 text-xs">
                <div class="space-y-1">
                    <label for="input_reject_reason_text" class="block font-bold text-red-800">Rejection Reason *</label>
                    <textarea id="input_reject_reason_text" rows="3" placeholder="Specify why this document request cannot be processed..." class="w-full p-2.5 text-xs border border-red-300 rounded-md"></textarea>
                </div>
            </div>
            <div class="p-5 border-t border-gray-200 bg-gray-50/50 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modal_reject_request')" class="px-4 py-2 font-semibold text-gray-600">Cancel</button>
                <button type="button" onclick="confirmRejection()" class="px-4 py-2 font-semibold text-xs bg-red-600 text-white rounded-md">Confirm Rejection</button>
            </div>
        </div>
    </div>

    <!-- Modal 3: Mark Completed -->
    <div id="modal_pickup_completion" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
            <div class="p-5 border-b border-gray-200 bg-emerald-50/60 flex items-center justify-between">
                <h3 class="text-base font-bold text-emerald-900">Confirm Pickup & Complete</h3>
                <button type="button" onclick="closeModal('modal_pickup_completion')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 space-y-4 text-xs">
                <div class="space-y-1">
                    <label for="input_received_by" class="block font-bold text-brand-dark">Claimant / Receiver Name:</label>
                    <input type="text" id="input_received_by" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-md">
                </div>
            </div>
            <div class="p-5 border-t border-gray-200 bg-gray-50/50 flex items-center justify-end space-x-2">
                <button type="button" onclick="closeModal('modal_pickup_completion')" class="px-4 py-2 font-semibold text-gray-600">Cancel</button>
                <button type="button" onclick="confirmPickupCompletion()" class="px-4 py-2 font-semibold text-xs bg-emerald-600 text-white rounded-md">Mark Completed</button>
            </div>
        </div>
    </div>

    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500">
        <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
    </footer>


    <script>
        let currentTargetRef = '';
        let currentRequestData = {};
        $(document).ready(function() {
            lucide.createIcons();
            $('#filter_status, #filter_doc, #search_queue').on('input change', filterQueueTable);
            <?php if ($notice): ?>showToast(<?= json_encode($notice['success'] ? 'success' : 'error') ?>, <?= json_encode($notice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        <?php endif; ?>
        });

        function closeModal(id) {
            $(`#${id}`).addClass('hidden');
        }

        function openWorkflowModal(refNum, studentName, docTitle, currentStatus, feeAmount, paymentStatus) {
            currentTargetRef = refNum;
            currentRequestData = {
                currentStatus,
                feeAmount: Number(feeAmount),
                paymentStatus
            };
            $('#wf_ref_num').text(refNum);
            $('#wf_student_name').text(studentName);
            const $select = $('#select_next_status').empty();
            const nextStatus = currentStatus === 'Pending' ? 'Under Review' :
                currentStatus === 'Under Review' ? (Number(feeAmount) > 0 ? 'For Payment' : 'Processing') :
                currentStatus === 'Paid' && paymentStatus === 'Paid' ? 'Processing' :
                currentStatus === 'Processing' ? 'Ready for Release' : '';
            if (nextStatus) $select.append($('<option>').val(nextStatus).text(nextStatus));
            $('#modal_status_workflow').removeClass('hidden');
        }

        function confirmStatusUpdate() {
            const status = $('#select_next_status').val();
            if (!status) return;
            $('#post_request_action').val('transition');
            $('#post_request_no').val(currentTargetRef);
            $('#post_request_status').val(status);
            document.getElementById('registrar_request_post').submit();
        }

        function openRejectModal(refNum) {
            currentTargetRef = refNum;
            $('#input_reject_reason_text').val('');
            $('#modal_reject_request').removeClass('hidden');
        }

        function confirmRejection() {
            const reason = $('#input_reject_reason_text').val().trim();
            if (!reason) {
                $('#input_reject_reason_text').trigger('focus');
                return;
            }
            $('#post_request_action').val('reject');
            $('#post_request_no').val(currentTargetRef);
            $('#post_rejection_reason').val(reason);
            document.getElementById('registrar_request_post').submit();
        }

        function openPickupCompletionModal(refNum, studentName) {
            currentTargetRef = refNum;
            $('#input_received_by').val(studentName);
            $('#modal_pickup_completion').removeClass('hidden');
        }

        function confirmPickupCompletion() {
            $('#post_request_action').val('transition');
            $('#post_request_no').val(currentTargetRef);
            $('#post_request_status').val('Completed');
            $('#post_received_by').val($('#input_received_by').val().trim());
            document.getElementById('registrar_request_post').submit();
        }

        function filterQueueTable() {
            const status = $('#filter_status').val();
            const doc = $('#filter_doc').val();
            const query = ($('#search_queue').val() || '').toLowerCase();
            $('#tbody_documentRequests tr[data-status]').each(function() {
                const row = $(this);
                const matches = (!status || row.attr('data-status') === status) &&
                    (!doc || row.attr('data-doc') === doc) &&
                    String(row.attr('data-search') || '').toLowerCase().includes(query);
                this.dataset.filterVisible = matches ? 'true' : 'false';
            });
            window.refreshRegistrarDocumentRequestTable?.();
        }

        $('#btn_mobile_menu').on('click', function() {
            $('#mobile_nav_menu').toggleClass('hidden');
            $('#icon_menu_open').toggleClass('hidden');
            $('#icon_menu_close').toggleClass('hidden');
        });
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
                $.post('../../controller/documentRequestController.php', $.param(fields), null, 'json')
                    .done(function(response) {
                        if (response && response.status === 'success') {
                            var requestNo = form.querySelector('[name="request_no"]')?.value;
                            var action = form.querySelector('[name="request_action"]')?.value;
                            var status = action === 'reject' ? 'Rejected' : (form.querySelector('[name="status"]')?.value || '');
                            var row = Array.from(document.querySelectorAll('#tbody_documentRequests tr[data-status]')).find(function (candidate) { return candidate.dataset.requestNo === requestNo; });
                            if (row) {
                                var previousStatus = row.dataset.status;
                                row.dataset.status = status;
                                var badge = row.cells[3]?.querySelector('span');
                                var badgeColors = {'Pending':'bg-amber-100 text-amber-800','Under Review':'bg-amber-100 text-amber-800','For Payment':'bg-orange-100 text-orange-800','Paid':'bg-emerald-100 text-emerald-800','Processing':'bg-blue-100 text-blue-800','Ready for Release':'bg-purple-100 text-purple-800','Completed':'bg-emerald-100 text-emerald-800','Rejected':'bg-red-100 text-red-800','Cancelled':'bg-gray-100 text-gray-700'};
                                if (badge) { badge.textContent = status; badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-semibold ' + (badgeColors[status] || 'bg-gray-100 text-gray-700'); }
                                var cell = row.cells[4];
                                if (cell) {
                                    cell.replaceChildren();
                                    var makeButton = function (text, callback, className) { var button = document.createElement('button'); button.type = 'button'; button.className = className; button.textContent = text; button.addEventListener('click', callback); cell.appendChild(button); };
                                    var ref = row.dataset.requestNo, student = row.dataset.studentName, doc = row.dataset.documentName, fee = Number(row.dataset.fee), payment = row.dataset.paymentStatus;
                                    if (['Pending','Under Review','Processing','Paid'].includes(status) && (status !== 'Paid' || payment === 'Paid')) makeButton('Update Status', function () { openWorkflowModal(ref, student, doc, status, fee, payment); }, 'inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white');
                                    if (status === 'Ready for Release') makeButton('Mark Completed', function () { openPickupCompletionModal(ref, student); }, 'inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-semibold bg-emerald-600 text-white');
                                    if (['Pending','Under Review'].includes(status)) makeButton('Reject', function () { openRejectModal(ref); }, 'inline-flex items-center px-2.5 py-1.5 rounded-md text-xs font-semibold border border-red-200 text-red-600');
                                }
                                var category = function (value) { return value === 'Pending' ? 'pending' : (['Paid','Processing'].includes(value) ? 'processing' : (value === 'Ready for Release' ? 'ready' : '')); };
                                var oldGroup = category(previousStatus), newGroup = category(status);
                                if (oldGroup !== newGroup) { if (oldGroup) document.getElementById('cnt_' + oldGroup).textContent = Math.max(0, Number(document.getElementById('cnt_' + oldGroup).textContent) - 1); if (newGroup) document.getElementById('cnt_' + newGroup).textContent = Number(document.getElementById('cnt_' + newGroup).textContent) + 1; }
                            }
                            closeModal('modal_status_workflow'); closeModal('modal_reject_request'); closeModal('modal_pickup_completion');
                            filterQueueTable();
                            showToast('success', response.message || 'Document request updated.');
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
        return Array.from(table.tBodies[0]?.rows || []).filter(row => row.dataset.filterVisible !== 'false' && !row.classList.contains('hidden'));
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
        allRows.forEach(row => { row.style.display = ''; row.hidden = true; });
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
    window.refreshRegistrarDocumentRequestTable = function () {
        const table = document.getElementById('table_documentRequests');
        if (!table) return;
        table.dataset.localPage = '1';
        refresh(table);
    };
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
