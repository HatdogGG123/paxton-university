<?php
    require_once __DIR__ . '/../includes/auth.php';
    ssis_require_role('cashier');
    require_once __DIR__ . '/../../services/PaymentsService.php';
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    $paymentModel = new Payments(Database::getConnection());
    $paymentController = new PaymentsService($paymentModel);
    $notice = $_SESSION['cashier_payment_notice'] ?? null;
    unset($_SESSION['cashier_payment_notice']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $token = $_POST['csrf_token'] ?? '';
            if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) { throw new DomainException('This form expired. Refresh the page and try again.'); }
            $receipt = $paymentController->handle((int) $_SESSION['user']['id'], $_POST);
            $receiptPaymentId = $receipt !== '' ? (int) ($_POST['payment_id'] ?? 0) : 0;
            $_SESSION['cashier_payment_notice'] = [
                'success' => true,
                'message' => $receipt !== '' ? 'Payment processed successfully. Receipt ' . $receipt . ' generated.' : 'Payment status updated successfully.',
                'receipt_payment_id' => $receiptPaymentId > 0 ? $receiptPaymentId : null,
            ];
            header('Location: payments.php'); exit;
        } catch (DomainException $exception) { $notice = ['success' => false, 'message' => $exception->getMessage()]; }
        catch (Throwable $exception) { error_log('SSIS cashier payment action failed: ' . $exception->getMessage()); $notice = ['success' => false, 'message' => 'We could not process this payment. Please try again.']; }
    }
    try {
        $paymentQueue = $paymentModel->getQueue();
        $walkInEligible = $paymentModel->getWalkInEligible();
        $transactions = $paymentModel->getTransactions();
        $isValidDate = static function (mixed $value): bool {
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { return false; }
            $date = DateTime::createFromFormat('!Y-m-d', $value);
            return $date !== false && $date->format('Y-m-d') === $value;
        };
        $reportFrom = $isValidDate($_GET['date_from'] ?? null) ? $_GET['date_from'] : date('Y-m-01');
        $reportTo = $isValidDate($_GET['date_to'] ?? null) ? $_GET['date_to'] : date('Y-m-d');
        if ($reportTo < $reportFrom) { $reportTo = $reportFrom; }
        $report = $paymentModel->getCollectionReport($reportFrom, $reportTo);
        $reportTransactions = $paymentModel->getCollectionReportTransactions($reportFrom, $reportTo);
    } catch (Throwable $exception) { error_log('SSIS cashier payments page failed: ' . $exception->getMessage()); http_response_code(503); exit('Payment information is temporarily unavailable. Please try again later.'); }
    $esc = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    $cashierName = trim((string) ($_SESSION['user']['full_name'] ?? ''));
    if ($cashierName === '') { $cashierName = (string) ($_SESSION['user']['username'] ?? 'Cashier'); }
    if (($_GET['export'] ?? '') === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ssis-collection-report-' . $reportFrom . '-to-' . $reportTo . '.csv"');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Receipt No', 'Request No', 'Student No', 'Student Name', 'Method', 'Reference No', 'Amount', 'Verified At']);
        foreach ($reportTransactions as $row) {
            fputcsv($output, [$row['receipt_no'], $row['request_no'], $row['student_no'], $row['student_name'], $row['method'], $row['reference_number'], $row['amount'], $row['verified_at']]);
        }
        fclose($output);
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Cashier Payments & Reports</title>

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

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Dashboard</a>
                    <a href="payments.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20">Payments & Reports</a>
                    <a href="clearance.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Clearance</a>
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
            <a href="payments.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">Payments & Reports</a>
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
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
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Payments & Financial Reports</h1>
                <p class="text-sm text-gray-500">Record walk-in transactions, verify online payments, issue refunds, and generate collection reports.</p>
            </div>
            <div class="flex items-center gap-3">
                <button id="btn_open_walkin_modal" type="button" class="inline-flex items-center space-x-2 px-3.5 py-2 bg-brand-primary hover:bg-brand-primary/90 text-white rounded-md text-xs font-semibold shadow-xs transition-colors">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>Record Walk-in Payment</span>
                </button>
            </div>
        </div>

        <!-- System Alerts Placeholder -->

        <!-- Section Navigation Tabs -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-6 text-xs font-semibold" aria-label="Tabs">
                <button id="tab_btn_verification" class="w-full sm:w-fit tab-btn py-3 px-1 border-b-2 border-brand-primary text-brand-primary flex items-center space-x-2">
                    <p class="text-center">Payment Verification</p>
                </button>
                <button id="tab_btn_transactions" class="w-full sm:w-fit tab-btn py-3 px-1 border-b-2 border-transparent text-gray-500 hover:text-brand-primary hover:border-gray-300 flex items-center space-x-2">
                    <p class="text-center">Transaction History</p>
                </button>
                <button id="tab_btn_reports" class="w-full sm:w-fit tab-btn py-3 px-1 border-b-2 border-transparent text-gray-500 hover:text-brand-primary hover:border-gray-300 flex items-center space-x-2">
                    <p class="text-center">Collection Reports</p>
                </button>
            </nav>
        </div>

        <!-- TAB 1: PAYMENT VERIFICATION & PROCESSING -->
        <div id="tab_content_verification" class="tab-content space-y-4">

            <!-- Filters & Search Bar -->
            <div class="bg-white border border-gray-200 rounded-md p-4 shadow-xs grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Search Record</label>
                <input type="text" id="filter_search" placeholder="Student ID, Name, or DR No..." class="w-full pl-3 pr-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-primary">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Payment Status</label>
                    <select id="filter_status" class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-primary bg-white">
                        <option value="">All Statuses</option>
                        <option value="Unpaid">Unpaid</option>
                        <option value="Pending Verification">Pending Verification</option>
                        <option value="Paid">Paid</option>
                        <option value="Rejected">Rejected</option>
                        <option value="Refunded">Refunded</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-1">Payment Method</label>
                    <select id="filter_method" class="w-full px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-primary bg-white">
                        <option value="">All Methods</option>
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button id="btn_reset_filters" type="button" class="w-full py-1.5 border border-gray-200 text-xs font-semibold text-gray-600 rounded-md hover:bg-gray-50 transition-colors">
                        Reset Filters
                    </button>
                </div>
            </div>

            <!-- Student Clearance Directory Table -->
            <div class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
                <div class="p-3 sm:p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="list-checks" class="w-4 h-4 text-brand-primary"></i>
                        <h2 class="text-sm font-bold text-brand-dark">Document Payment Queue</h2>
                    </div>
                    <span class="text-[11px] text-gray-500">Payment records from document requests</span>
                </div>

                <div class="overflow-x-auto min-w-full">
                    <table id="table_clearance" class="w-full table-fixed text-left text-xs border-collapse min-w-[640px]">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                                <th class="py-3 px-3 w-28 whitespace-nowrap">Request No.</th>
                                <th class="py-3 px-3 min-w-[130px] max-w-[160px]">Student Name</th>
                                <th class="py-3 px-3 min-w-[130px] max-w-[160px]">Document</th>
                                <th class="py-3 px-3 min-w-[140px] max-w-[180px]">Payment Method</th>
                                <th class="py-3 px-3 min-w-[140px] max-w-[180px]">Amount</th>
                                <th class="py-3 px-3 w-32 text-right whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                            <?php foreach ($paymentQueue as $payment): $studentName = trim($payment['first_name'] . ' ' . $payment['middle_name'] . ' ' . $payment['last_name']); $docLabel = $payment['document_name'] . ' (' . $payment['copies'] . ' copies)'; $filterText = strtolower($payment['request_no'] . ' ' . $payment['student_no'] . ' ' . $studentName); ?>
                            <tr class="hover:bg-gray-50/50 transition-colors" data-payment-id="<?= (int) $payment['payment_id'] ?>" data-record="<?= $esc($filterText) ?>" data-status="<?= $esc($payment['payment_status']) ?>" data-method="<?= $esc($payment['method']) ?>">
                                <td class="py-3 px-3 w-28 font-display font-bold text-brand-dark whitespace-nowrap"><?= $esc($payment['request_no']) ?></td>
                                <td class="py-3 px-3 min-w-[130px] max-w-[160px]"><div class="font-bold text-brand-dark truncate"><?= $esc($studentName) ?></div><div class="text-[10px] text-gray-500"><?= $esc($payment['student_no']) ?></div></td>
                                <td class="py-3 px-3 min-w-[130px] max-w-[160px]"><div class="truncate text-gray-600"><?= $esc($docLabel) ?></div></td>
                                <td class="py-3 px-3 min-w-[140px] max-w-[180px]"><div class="truncate text-gray-600"><?= $esc($payment['method'] ?: 'Not submitted') ?><?= $payment['reference_number'] ? ' · ' . $esc($payment['reference_number']) : '' ?></div><div class="text-[10px] text-gray-500"><?= $esc($payment['payment_status']) ?></div></td>
                                <td class="py-3 px-3 min-w-[140px] max-w-[180px]">₱<?= number_format((float) $payment['amount'], 2) ?></td>
                                <td class="py-3 px-3 w-32 text-right whitespace-nowrap space-x-1"><?php if ($payment['payment_status'] === 'Pending Verification'): ?><button type="button" onclick='openVerifyModal(<?= (int) $payment['payment_id'] ?>, <?= json_encode($payment['request_no'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= json_encode($studentName . ' (' . $payment['student_no'] . ')', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= json_encode($docLabel, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= json_encode($payment['method'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= json_encode($payment['reference_number'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>, <?= (float) $payment['amount'] ?>)' class="px-2 py-1 bg-blue-600 text-white rounded text-[11px] font-semibold">Review</button><?php elseif (in_array($payment['payment_status'], ['Unpaid', 'Rejected'], true) && $payment['request_status'] === 'For Payment'): ?><button type="button" onclick="openWalkInForPayment(<?= (int) $payment['payment_id'] ?>)" class="px-2 py-1 bg-brand-primary text-white rounded text-[11px] font-semibold">Record Payment</button><?php elseif ($payment['payment_status'] === 'Paid' && $payment['receipt_no']): ?><a href="receipt.php?id=<?= (int) $payment['payment_id'] ?>" target="_blank" rel="noopener" class="px-2 py-1 border border-gray-200 text-brand-primary rounded text-[11px] font-semibold">Print Receipt</a><?php else: ?><span class="text-[10px] text-gray-500"><?= $esc($payment['payment_status']) ?></span><?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$paymentQueue): ?><tr><td colspan="6" class="py-8 px-3 text-center text-gray-500">No document payments found.</td></tr><?php endif; ?></tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- TAB 2: TRANSACTIONS & RECEIPTS -->
        <div id="tab_content_transactions" class="tab-content space-y-4 hidden">
            <div class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-brand-dark">Official Receipt Log</h2>
                    <span class="text-xs text-gray-500">All recorded payments with system receipt numbers</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                                <th class="py-3 px-4">Receipt No</th>
                                <th class="py-3 px-4">Request No</th>
                                <th class="py-3 px-4">Student Name</th>
                                <th class="py-3 px-4">Reference No</th>
                                <th class="py-3 px-4">Method</th>
                                <th class="py-3 px-4 text-right">Amount</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                            <?php foreach ($transactions as $transaction): ?>
                            <tr class="hover:bg-gray-50/50">
                                <td class="py-3 px-4 font-display font-bold text-brand-dark"><?= $esc($transaction['receipt_no']) ?></td>
                                <td class="py-3 px-4"><?= $esc($transaction['request_no']) ?></td>
                                <td class="py-3 px-4"><?= $esc($transaction['student_name']) ?></td>
                                <td class="py-3 px-4 font-display text-gray-500"><?= $esc($transaction['reference_number'] ?: 'N/A (Walk-in)') ?></td>
                                <td class="py-3 px-4"><?= $esc($transaction['method']) ?></td>
                                <td class="py-3 px-4 text-right font-bold">₱<?= number_format((float) $transaction['amount'], 2) ?></td>
                                <td class="py-3 px-4 text-right space-x-2"><a href="receipt.php?id=<?= (int) $transaction['payment_id'] ?>" target="_blank" rel="noopener" class="text-brand-primary font-semibold hover:underline">Print</a><?php if ($transaction['payment_status'] === 'Paid' && in_array($transaction['request_status'], ['Cancelled', 'Rejected'], true)): ?><button type="button" onclick="openRefundModal(<?= (int) $transaction['payment_id'] ?>, <?= json_encode($transaction['request_no'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($transaction['receipt_no'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= (float) $transaction['amount'] ?>)" class="text-red-600 font-semibold hover:underline">Refund</button><?php endif; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$transactions): ?><tr><td colspan="7" class="py-8 px-4 text-center text-gray-500">No completed payment transactions yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: COLLECTION REPORTS -->
        <div id="tab_content_reports" class="tab-content space-y-4 hidden">
            <!-- Report Controls -->
            <form id="report_filter" method="get" class="bg-white border border-gray-200 rounded-md p-4 shadow-xs flex flex-col md:flex-row items-md-center justify-between gap-4">
                <input type="hidden" name="tab" value="reports">
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-0.5">Date From</label>
                        <input type="date" name="date_from" required value="<?= $esc($reportFrom) ?>" class="px-2.5 py-1 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 uppercase tracking-wider mb-0.5">Date To</label>
                        <input type="date" name="date_to" required value="<?= $esc($reportTo) ?>" class="px-2.5 py-1 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-primary">
                    </div>
                    <button type="submit" class="mt-4 px-3 py-1 bg-brand-primary text-white rounded text-xs font-semibold hover:bg-brand-primary/90">Generate Report</button>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" onclick="window.print()" class="px-3 py-1 border border-gray-200 text-gray-700 rounded text-xs font-semibold hover:bg-gray-50">Print Report</button>
                <button type="submit" name="export" value="csv" class="px-3 py-1 border border-gray-200 text-gray-700 rounded text-xs font-semibold hover:bg-gray-50">Export CSV</button>
                </div>
            </form>

            <!-- Report Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white border border-gray-200 p-4 rounded-md">
                    <p class="text-xs text-gray-500 font-semibold uppercase">Total Collection</p>
                    <h3 class="text-xl font-bold text-brand-dark mt-1">₱<?= number_format((float) $report['total'], 2) ?></h3>
                </div>
                <div class="bg-white border border-gray-200 p-4 rounded-md">
                    <p class="text-xs text-gray-500 font-semibold uppercase">Cash Collections</p>
                    <h3 class="text-xl font-bold text-brand-dark mt-1">₱<?= number_format((float) $report['cash'], 2) ?></h3>
                </div>
                <div class="bg-white border border-gray-200 p-4 rounded-md">
                    <p class="text-xs text-gray-500 font-semibold uppercase">Online / Bank Transfers</p>
                    <h3 class="text-xl font-bold text-brand-dark mt-1">₱<?= number_format((float) $report['online'], 2) ?></h3>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-brand-dark">Collection Details</h2>
                    <span class="text-xs text-gray-500"><?= count($reportTransactions) ?> payments from <?= $esc($reportFrom) ?> to <?= $esc($reportTo) ?></span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead><tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold"><th class="py-3 px-4">Receipt</th><th class="py-3 px-4">Request</th><th class="py-3 px-4">Student</th><th class="py-3 px-4">Method</th><th class="py-3 px-4">Reference</th><th class="py-3 px-4">Verified</th><th class="py-3 px-4 text-right">Amount</th></tr></thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                            <?php foreach ($reportTransactions as $row): ?><tr><td class="py-3 px-4 font-bold"><?= $esc($row['receipt_no']) ?></td><td class="py-3 px-4"><?= $esc($row['request_no']) ?></td><td class="py-3 px-4"><?= $esc($row['student_name']) ?> <span class="text-gray-500">(<?= $esc($row['student_no']) ?>)</span></td><td class="py-3 px-4"><?= $esc($row['method']) ?></td><td class="py-3 px-4"><?= $esc($row['reference_number'] ?: 'Walk-in') ?></td><td class="py-3 px-4"><?= $esc($row['verified_at']) ?></td><td class="py-3 px-4 text-right font-bold">₱<?= number_format((float) $row['amount'], 2) ?></td></tr><?php endforeach; ?>
                            <?php if (!$reportTransactions): ?><tr><td colspan="7" class="py-8 px-4 text-center text-gray-500">No paid transactions in this date range.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <!-- MODAL 1: Payment Verification -->
    <div id="modal_verify_payment" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-md shadow-lg max-w-lg w-full mx-4 overflow-hidden border border-gray-200">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-emerald-50/60">
                <div class="flex items-center space-x-2">
                    <div class="p-1.5 bg-emerald-100 text-emerald-800 rounded">
                        <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-brand-dark">Verify Payment Submission</h3>
                        <p class="text-[11px] text-gray-500">Confirm proof of payment and generate official receipt</p>
                    </div>
                </div>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-600 rounded p-1 transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form id="form_verify_payment" method="post" class="p-5 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="verify">
                <input type="hidden" id="verify_request_id" name="payment_id">

                <div class="bg-gray-50/80 border border-gray-200/80 rounded-md p-3 space-y-2 text-xs">
                    <div class="flex justify-between border-b border-gray-200/60 pb-1.5">
                        <span class="text-gray-500">Request Number:</span>
                        <span id="verify_display_req_no" class="font-bold text-brand-dark">DR-2026-0012</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200/60 pb-1.5">
                        <span class="text-gray-500">Student Name:</span>
                        <span id="verify_display_student" class="font-semibold text-brand-dark">Juan Dela Cruz (2026-00045)</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200/60 pb-1.5">
                        <span class="text-gray-500">Document Requested:</span>
                        <span id="verify_display_document" class="font-medium text-gray-800">Transcript of Records (2 copies)</span>
                    </div>
                    <div class="flex justify-between border-b border-gray-200/60 pb-1.5">
                        <span class="text-gray-500">Submitted Method & Ref:</span>
                        <span id="verify_display_method_ref" class="font-display text-gray-800">GCash - 9021849201</span>
                    </div>
                    <div class="flex justify-between pt-0.5">
                        <span class="text-gray-500 font-semibold">Total Amount Due:</span>
                        <span id="verify_display_amount" class="font-bold text-emerald-700 text-sm">₱150.00</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Generated Receipt No.</label>
                        <input type="text" id="verify_or_number" readonly value="Generated after verification" class="w-full px-3 py-1.5 text-xs bg-gray-100 font-display font-bold text-brand-primary border border-gray-200 rounded-md focus:outline-none cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Verification Date</label>
                        <input type="text" readonly value="October 3, 2026" class="w-full px-3 py-1.5 text-xs bg-gray-100 font-medium text-gray-600 border border-gray-200 rounded-md focus:outline-none cursor-not-allowed">
                    </div>
                </div>

                <div class="p-3 bg-blue-50/70 border border-blue-100 rounded-md flex items-start space-x-2 text-[11px] text-blue-800">
                    <i data-lucide="info" class="w-4 h-4 text-blue-600 shrink-0 mt-0.5"></i>
                    <p>Confirming verification will set the document status to <strong>Paid</strong> and notify the Registrar to process the order.</p>
                </div>

                <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                    <button type="button" onclick="openRejectModal($('#verify_request_id').val(), $('#verify_display_req_no').text())" class="px-3 py-1.5 text-xs font-semibold text-red-600 border border-red-200 rounded-md hover:bg-red-50 transition-colors">
                        Reject Proof
                    </button>
                    <div class="flex items-center space-x-2">
                        <button type="button" class="close-modal px-3.5 py-1.5 text-xs font-semibold text-gray-600 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" id="btn_confirm_verify" class="inline-flex items-center space-x-1.5 px-4 py-1.5 text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 rounded-md shadow-xs transition-colors">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            <span>Confirm & Verify Payment</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Record Walk-In Payment -->
    <div id="modal_walkin_payment" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-md shadow-lg max-w-md w-full mx-4 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                <h3 class="text-sm font-bold text-brand-dark">Record Walk-in Payment</h3>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form id="form_walkin_payment" method="post" class="p-4 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="walkin">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Select Document Request *</label>
                    <select id="walkin_request_id" name="payment_id" required class="w-full px-3 py-2 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-brand-primary bg-white" onchange="$('#walkin_amount').val($(this).find(':selected').data('amount') || '')">
                        <option value="">-- Choose For-Payment Request --</option>
                        <?php foreach ($walkInEligible as $eligible): ?><option value="<?= (int) $eligible['payment_id'] ?>" data-amount="<?= number_format((float) $eligible['amount'], 2, '.', '') ?>"><?= $esc($eligible['request_no'] . ' | ' . $eligible['student_name'] . ' - ' . $eligible['document_name'] . ' (₱' . number_format((float) $eligible['amount'], 2) . ')') ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Payment Method *</label>
                    <select id="walkin_method" name="method" required class="w-full px-3 py-2 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-brand-primary bg-white">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Amount (Must match fee) *</label>
                    <input type="number" step="0.01" name="amount" id="walkin_amount" required placeholder="0.00" class="w-full px-3 py-2 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-brand-primary">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Reference Number (Optional for Cash)</label>
                    <input type="text" name="reference" id="walkin_reference" placeholder="e.g. GCash Ref No." class="w-full px-3 py-2 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-brand-primary">
                </div>
                <div class="p-3 bg-amber-50 border border-amber-100 rounded text-[11px] text-amber-800">
                    <p>Submitting will generate an official receipt number automatically.</p>
                </div>
                <div class="flex justify-end space-x-2 pt-2 border-t border-gray-100">
                    <button type="button" class="close-modal px-3 py-1.5 text-xs border border-gray-200 rounded font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="btn_submit_walkin" class="px-3 py-1.5 text-xs bg-brand-primary text-white rounded font-semibold hover:bg-brand-primary/90">Record & Print Receipt</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: Reject Payment -->
    <div id="modal_reject_payment" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-md shadow-lg max-w-md w-full mx-4 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-red-50">
                <h3 class="text-sm font-bold text-red-800">Reject Payment Submission</h3>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form id="form_reject_payment" method="post" class="p-4 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="reject">
                <input type="hidden" id="reject_request_no" name="payment_id">
                <p class="text-xs text-gray-600">Rejecting payment for request <strong id="lbl_reject_req_no">DR-2026-XXXX</strong> will send it back to the student to re-submit valid proof.</p>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Rejection Reason *</label>
                    <textarea id="reject_reason" name="reason" required rows="3" placeholder="Specify why the payment reference was rejected..." class="w-full p-2 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-red-600"></textarea>
                </div>
                <div class="flex justify-end space-x-2 pt-2 border-t border-gray-100">
                    <button type="button" class="close-modal px-3 py-1.5 text-xs border border-gray-200 rounded font-semibold text-gray-600">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 text-xs bg-red-600 text-white rounded font-semibold hover:bg-red-700">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 4: Refund Payment -->
    <div id="modal_refund_payment" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-md shadow-lg max-w-md w-full mx-4 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                <h3 class="text-sm font-bold text-brand-dark">Process Payment Refund</h3>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form id="form_refund_payment" method="post" class="p-4 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>"><input type="hidden" name="action" value="refund">
                <input type="hidden" id="refund_request_no" name="payment_id">
                <p class="text-xs text-gray-600">Refunding payment for <strong id="lbl_refund_req_no">DR-2026-XXXX</strong> (Amount: <span id="lbl_refund_amount">₱0.00</span>).</p>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Reason for Refund *</label>
                    <textarea id="refund_reason" name="reason" required rows="3" placeholder="State reason (e.g., document request cancelled or rejected)..." class="w-full p-2 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-brand-primary"></textarea>
                </div>
                <div class="flex justify-end space-x-2 pt-2 border-t border-gray-100">
                    <button type="button" class="close-modal px-3 py-1.5 text-xs border border-gray-200 rounded font-semibold text-gray-600">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 text-xs bg-red-600 text-white rounded font-semibold hover:bg-red-700">Process Refund</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 5: [ADDED] Release Cashier Clearance / Hold -->
    <div id="modal_clearance_release" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-md shadow-lg max-w-md w-full mx-4 overflow-hidden border border-gray-200">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-emerald-50">
                <div class="flex items-center space-x-2">
                    <i data-lucide="shield-check" class="w-5 h-5 text-emerald-700"></i>
                    <h3 class="text-sm font-bold text-brand-dark">Release Cashier Clearance Hold</h3>
                </div>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form id="form_release_clearance" class="p-4 space-y-4 text-xs">
                <input type="hidden" id="release_clearance_id">
                <p class="text-gray-600">
                    Are you sure you want to clear student <strong id="release_student_name" class="text-brand-dark">Pedro Penduko</strong> (<span id="release_student_id" class="font-display">2026-00012</span>)?
                </p>
                <div class="p-3 bg-gray-50 border border-gray-200 rounded-md">
                    <span class="text-gray-500 block">Outstanding Balance:</span>
                    <span id="release_amount" class="text-lg font-bold text-emerald-700">₱2,450.00</span>
                </div>
                <div>
                    <label class="block text-gray-700 font-semibold mb-1">Cashier Remarks / OR Number *</label>
                    <input type="text" id="release_remarks" required placeholder="e.g. Cleared via Walk-in Payment OR-2026-0043" class="w-full px-3 py-2 border border-gray-200 rounded-md focus:ring-1 focus:ring-brand-primary">
                </div>
                <div class="flex justify-end space-x-2 pt-2 border-t border-gray-100">
                    <button type="button" class="close-modal px-3.5 py-1.5 border border-gray-200 rounded font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-3.5 py-1.5 bg-emerald-700 text-white rounded font-semibold hover:bg-emerald-800">Clear Student Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 6: [ADDED] View Clearance Details -->
    <div id="modal_clearance_details" class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-md shadow-lg max-w-md w-full mx-4 overflow-hidden border border-gray-200">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between bg-gray-50">
                <div class="flex items-center space-x-2">
                    <i data-lucide="file-text" class="w-5 h-5 text-brand-primary"></i>
                    <h3 class="text-sm font-bold text-brand-dark">Clearance Record Info</h3>
                </div>
                <button type="button" class="close-modal text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-5 space-y-4 text-xs">
                <div class="bg-gray-50 p-3 rounded-md border border-gray-200 space-y-1.5">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Student:</span>
                        <span id="detail_student_name" class="font-bold text-brand-dark">Juan Dela Cruz</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Student ID:</span>
                        <span id="detail_student_id" class="font-display text-gray-700">2026-00045</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Unpaid Balance:</span>
                        <span id="detail_amount" class="font-bold text-brand-primary">₱150.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Status:</span>
                        <span id="detail_status" class="font-semibold text-blue-700">Pending Review</span>
                    </div>
                </div>
                <div>
                    <span class="text-gray-500 block uppercase font-bold text-[10px]">Hold / Item Reason</span>
                    <p id="detail_hold_reason" class="text-gray-800 font-medium mt-1 p-2 bg-gray-50 border rounded-md">Document Processing Fee</p>
                </div>
            </div>
            <div class="p-3 bg-gray-50 border-t border-gray-100 flex justify-end">
                <button type="button" class="close-modal px-4 py-1.5 border border-gray-200 text-xs font-semibold rounded bg-white hover:bg-gray-50">Close</button>
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
    
    <?php if ($notice): $paymentToastMessage = $esc($notice['message']); if (!empty($notice['receipt_payment_id'])) $paymentToastMessage .= ' <a class="underline font-bold" target="_blank" rel="noopener" href="receipt.php?id=' . (int) $notice['receipt_payment_id'] . '">Print receipt</a>'; ?>showToast(<?= json_encode(!empty($notice['success']) ? 'success' : 'error') ?>, <?= json_encode($paymentToastMessage, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);<?php endif; ?>
    <script>
        $(document).ready(function() {
            lucide.createIcons();

            // Mobile menu toggle
            $('#mobile_menu_btn').on('click', function() {
                $('#mobile_menu').toggleClass('hidden');
            });

            // Section Tabs Switching
            $('.tab-btn').on('click', function() {
                $('.tab-btn').removeClass('border-brand-primary text-brand-primary').addClass('border-transparent text-gray-500');
                $(this).removeClass('border-transparent text-gray-500').addClass('border-brand-primary text-brand-primary');

                $('.tab-content').addClass('hidden');
                if (this.id === 'tab_btn_verification') $('#tab_content_verification').removeClass('hidden');
                if (this.id === 'tab_btn_transactions') $('#tab_content_transactions').removeClass('hidden');
                if (this.id === 'tab_btn_reports') $('#tab_content_reports').removeClass('hidden');
            });

            if (new URLSearchParams(window.location.search).get('tab') === 'reports') {
                $('#tab_btn_reports').trigger('click');
            }
            if (new URLSearchParams(window.location.search).get('tab') === 'transactions') {
                $('#tab_btn_transactions').trigger('click');
            }

            // Modal Trigger Actions
            $('#btn_open_walkin_modal').on('click', function() {
                $('#modal_walkin_payment').removeClass('hidden');
            });

            $('.close-modal').on('click', function() {
                const modal = $(this).closest('.fixed.inset-0');
                modal.addClass('hidden');
                if (modal.attr('id') === 'modal_walkin_payment') resetWalkInPaymentForm();
            });

            // Reset filters logic
            $('#btn_reset_filters').on('click', function() {
                $('#filter_search').val('');
                $('#filter_status').val('');
                $('#filter_method').val('');
                $('#filter_search').trigger('input');
            });

            $('#filter_search, #filter_status, #filter_method').on('input change', function() {
                const query = ($('#filter_search').val() || '').toLowerCase();
                const status = $('#filter_status').val();
                const method = $('#filter_method').val();
                const table = document.getElementById('table_clearance');
                table.dataset.localPage = '1';
                $('#table_clearance tbody tr[data-record]').each(function() {
                    const row = $(this);
                    const matches = row.data('record').toLowerCase().includes(query) && (!status || row.data('status') === status) && (!method || row.data('method') === method);
                    this.style.display = matches ? 'table-row' : 'none';
                });
            });

            $('#form_release_clearance').on('submit', function(e) {
                e.preventDefault();
                const student = $('#release_student_name').text();
                $('#modal_clearance_release').addClass('hidden');
                showToast('success', 'Clearance hold released successfully for student <strong>' + student + '</strong>.');
            });
        });

        // Global Helper Functions
        function openVerifyModal(paymentId, reqNo, studentInfo, docType, method, refNo, amount) {
            $('#verify_request_id').val(paymentId);
            $('#verify_display_req_no').text(reqNo);
            $('#verify_display_student').text(studentInfo);
            $('#verify_display_document').text(docType);
            $('#verify_display_method_ref').text(method + ' - ' + refNo);
            $('#verify_display_amount').text('₱' + parseFloat(amount).toFixed(2));
            $('#modal_verify_payment').removeClass('hidden');
            lucide.createIcons();
        }

        function openWalkInForPayment(paymentId) {
            const option = $('#walkin_request_id option[value="' + paymentId + '"]');
            if (!option.length) {
                showToast('error', 'This request is no longer eligible for a walk-in payment. Refresh the page and try again.');
                return;
            }
            $('#walkin_request_id').val(String(paymentId)).trigger('change');
            $('#modal_walkin_payment').removeClass('hidden');
        }

        function resetWalkInPaymentForm() {
            const form = document.getElementById('form_walkin_payment');
            if (form) form.reset();
        }

        function openReleaseModal(clearanceId, studentName, studentId, amount) {
            $('#release_clearance_id').val(clearanceId);
            $('#release_student_name').text(studentName);
            $('#release_student_id').text(studentId);
            $('#release_amount').text('₱' + parseFloat(amount).toFixed(2));
            $('#release_remarks').val('');
            $('#modal_clearance_release').removeClass('hidden');
            lucide.createIcons();
        }

        function openDetailsModal(studentId, name, amount, reason, status) {
            $('#detail_student_id').text(studentId);
            $('#detail_student_name').text(name);
            $('#detail_amount').text('₱' + amount);
            $('#detail_hold_reason').text(reason);
            $('#detail_status').text(status);
            $('#modal_clearance_details').removeClass('hidden');
            lucide.createIcons();
        }

        function openRejectModal(paymentId, reqNo) {
            $('#reject_request_no').val(paymentId);
            $('#lbl_reject_req_no').text(reqNo);
            $('#modal_reject_payment').removeClass('hidden');
            lucide.createIcons();
        }

        function openRefundModal(paymentId, reqNo, receiptNo, amount) {
            $('#refund_request_no').val(paymentId);
            $('#lbl_refund_req_no').text(reqNo + ' (' + receiptNo + ')');
            $('#lbl_refund_amount').text('₱' + parseFloat(amount).toFixed(2));
            $('#modal_refund_payment').removeClass('hidden');
            lucide.createIcons();
        }

        function printReceipt(receiptNo) {
            window.open('receipt.php?id=' + encodeURIComponent(receiptNo), '_blank', 'noopener');
        }
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
        $.post('../../controller/paymentsController.php', $.param(fields), null, 'json')
            .done(function (response) {
                if (response && response.status === 'success') {
                    var redirect = response.data && response.data.redirect;
                    var action = form.querySelector('[name="payment_action"], [name="action"]')?.value || '';
                    var paymentId = form.querySelector('[name="payment_id"]')?.value;
                    var row = document.querySelector('#table_clearance tr[data-payment-id="' + Number(paymentId) + '"]');
                    if (row) {
                        var status = /reject/i.test(action) ? 'Rejected' : (/refund/i.test(action) ? 'Refunded' : 'Paid');
                        row.dataset.status = status;
                        row.cells[3]?.querySelector('div:last-child')?.replaceChildren(document.createTextNode(status));
                        var actions = row.cells[5];
                        if (actions) {
                            actions.replaceChildren();
                            if (status === 'Paid' && response.data?.receipt_payment_id) {
                                var link = document.createElement('a'); link.href = 'receipt.php?id=' + encodeURIComponent(response.data.receipt_payment_id); link.target = '_blank'; link.rel = 'noopener'; link.className = 'px-2 py-1 border border-gray-200 text-brand-primary rounded text-[11px] font-semibold'; link.textContent = 'Print Receipt'; actions.appendChild(link);
                            } else actions.textContent = status;
                        }
                    }
                    $('#modal_verify_payment, #modal_walkin_payment, #modal_reject_payment, #modal_refund_payment').addClass('hidden');
                    if (form.id === 'form_walkin_payment') resetWalkInPaymentForm();
                    if (response.data?.receipt_payment_id) {
                        window.location.assign('payments.php?tab=transactions');
                        return;
                    }
                    showToast('success', response.message || 'Payment updated successfully.');
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
