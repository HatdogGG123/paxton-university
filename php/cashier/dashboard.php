<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('cashier');
require_once __DIR__ . '/../../models/CashierDashboard.php';
require_once __DIR__ . '/../../models/Notifications.php';
try {
    $cashierDashboard = new CashierDashboard(Database::getConnection());
    $cashierData = $cashierDashboard->getData();
    $cashierNotifications = new Notifications(Database::getConnection());
    $cashierUserId = (int) $_SESSION['user']['id'];
    $cashierNotifications->syncCashierPaymentQueue($cashierUserId);
    $cashierUnreadCount = $cashierNotifications->getUnreadCount($cashierUserId);
} catch (Throwable $exception) {
    error_log('SSIS cashier dashboard failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Cashier information is temporarily unavailable. Please try again later.');
}
$esc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$cashierName = trim((string) ($_SESSION['user']['full_name'] ?? '')) ?: (string) ($_SESSION['user']['username'] ?? 'Cashier');
$termLabel = $cashierData['term'] ? $cashierData['term']['semester'] . ', AY ' . $cashierData['term']['academic_year'] : 'No current term';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Cashier Dashboard</title>

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
                    <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20">Dashboard</a>
                    <a href="payments.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Payments & Reports</a>
                    <a href="clearance.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Clearance</a>
                    <a href="notifications.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Notifications<?php if ($cashierUnreadCount > 0): ?> <span class="ml-1 inline-flex min-w-4 justify-center rounded-full bg-red-100 px-1 text-[10px] font-bold text-red-700"><?= $cashierUnreadCount ?></span><?php endif; ?></a>
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
            <a href="dashboard.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">Dashboard</a>
            <a href="payments.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Payments & Reports</a>
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications<?php if ($cashierUnreadCount > 0): ?> (<?= $cashierUnreadCount ?>)<?php endif; ?></a>
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
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Page Header -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Cashier Overview</h1>
                <p class="text-sm text-gray-500">Monitor daily collection, pending payment verifications, and department clearance items.</p>
            </div>
            <div class="flex items-start gap-3 flex-col w-full sm:w-fit sm:flex-row sm:items-center ">
                <div class="inline-flex items-center gap-2 bg-white border border-gray-200 rounded-md px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs">
                    <i data-lucide="calendar" class="w-4 h-4 text-brand-secondary"></i>
                    <span>Term: <strong id="lbl_current_term" class="text-brand-dark"><?= $esc($termLabel) ?></strong></span>
                </div>
            </div>
        </div>

        <!-- System Summary Cards -->
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Card 1: Total Collected Today -->
            <div class="bg-white border border-gray-200 rounded-md p-5 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Collected Today</p>
                    <h3 id="stat_collected_today" class="text-2xl font-bold text-brand-dark mt-1">₱<?= number_format($cashierData['collected_today'], 2) ?></h3>
                    <p class="text-[11px] text-emerald-700 font-semibold mt-1"><?= $cashierData['verified_today_count'] ?> Transactions Verified</p>
                </div>
            </div>

            <!-- Card 2: Pending Verifications -->
            <div class="bg-white border border-gray-200 rounded-md p-5 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pending Verification</p>
                    <h3 id="stat_pending_verifications" class="text-2xl font-bold text-blue-700 mt-1"><?= $cashierData['pending_payment_count'] ?></h3>
                    <p class="text-[11px] text-gray-500 mt-1">Online Payment Submissions</p>
                </div>
            </div>

            <!-- Card 3: Pending Clearance Reviews -->
            <div class="bg-white border border-gray-200 rounded-md p-5 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pending Clearance</p>
                    <h3 id="stat_pending_clearance" class="text-2xl font-bold text-amber-700 mt-1"><?= $cashierData['pending_clearance_count'] ?></h3>
                    <p class="text-[11px] text-gray-500 mt-1">Students Action Needed</p>
                </div>
            </div>

            <!-- Card 4: Total Unpaid Balance Requests -->
            <div class="bg-white border border-gray-200 rounded-md p-5 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Unpaid Document Requests</p>
                    <h3 id="stat_unpaid_requests" class="text-2xl font-bold text-orange-700 mt-1"><?= $cashierData['unpaid_request_count'] ?></h3>
                    <p class="text-[11px] text-gray-500 mt-1">Awaiting Reference / Walk-in</p>
                </div>
            </div>
        </section>

        <!-- Main Content Split: Pending Submissions & Recent Transactions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Left Panel: Payments Pending Verification (2 Cols) -->
            <section class="lg:col-span-2 bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden flex flex-col">
                <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="p-2 rounded-md bg-blue-50 text-blue-700">
                            <i data-lucide="credit-card" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-brand-dark">Pending Payment Verifications</h2>
                            <p class="text-xs text-gray-500">Submitted reference numbers requiring Cashier action</p>
                        </div>
                    </div>
                    <a href="payments.php?status=Pending+Verification" class="text-xs font-semibold text-brand-primary hover:underline">View All</a>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table id="table_pending_payments" class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                                <th class="py-3 px-4">Request No</th>
                                <th class="py-3 px-4">Student Info</th>
                                <th class="py-3 px-4">Method / Ref</th>
                                <th class="py-3 px-4 text-right">Amount</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                            <?php foreach ($cashierData['pending_payments'] as $payment): ?><tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3.5 px-4 font-bold text-brand-dark"><?= $esc($payment['request_no']) ?></td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-bold text-brand-dark"><?= $esc(trim(implode(' ', array_filter([$payment['first_name'], $payment['middle_name'], $payment['last_name']])))) ?></div>
                                        <div class="text-[10px] text-gray-500"><?= $esc($payment['student_no']) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <div class="font-semibold text-gray-800"><?= $esc($payment['method']) ?></div>
                                        <div class="text-[10px] font-display text-gray-500"><?= $esc($payment['reference_number']) ?></div>
                                    </td>
                                    <td class="py-3.5 px-4 text-right font-bold text-brand-dark">₱<?= number_format((float) $payment['amount'], 2) ?></td>
                                    <td class="py-3.5 px-4 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-100 text-blue-800">Pending Verification</span></td>
                                </tr><?php endforeach; ?>
                            <?php if (!$cashierData['pending_payments']): ?><tr>
                                    <td colspan="5" class="py-8 px-4 text-center text-gray-500">No payment submissions are waiting for verification.</td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 bg-gray-50/80 border-t border-gray-100 mt-auto flex items-center space-x-2 text-xs text-gray-500">
                    <i data-lucide="info" class="w-4 h-4 text-brand-secondary shrink-0"></i>
                    <p>Verifying a payment automatically marks the linked document request as <strong>Paid</strong> and alerts the Registrar office.</p>
                </div>
            </section>

            <!-- Right Panel: Clearance Overview & System Activity (1 Col) -->
            <div class="space-y-6">

                <!-- Cashier Clearance Status Widget -->
                <section class="bg-white border border-gray-200 rounded-md p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="shield-check" class="w-5 h-5 text-brand-primary"></i>
                            <h2 class="text-sm font-bold text-brand-dark">Cashier Clearance Office</h2>
                        </div>
                        <a href="clearance.php" class="text-xs font-semibold text-brand-primary hover:underline">Manage</a>
                    </div>

                    <p class="text-xs text-gray-600">Review student financial holds for current semester clearance approvals.</p>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs p-2 bg-gray-50 rounded border border-gray-100">
                            <span class="text-gray-600 font-medium">Cleared Students</span>
                            <span id="lbl_cleared_count" class="font-bold text-emerald-700"><?= $cashierData['cleared_students_count'] ?></span>
                        </div>
                        <div class="flex items-center justify-between text-xs p-2 bg-amber-50/50 rounded border border-amber-100">
                            <span class="text-amber-800 font-medium">Unpaid Holds (Action Needed)</span>
                            <span id="lbl_holds_count" class="font-bold text-amber-700"><?= $cashierData['action_needed_students_count'] ?></span>
                        </div>
                    </div>
                </section>

                <!-- Quick Activity Stream -->
                <section class="bg-white border border-gray-200 rounded-md p-5 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="activity" class="w-5 h-5 text-brand-secondary"></i>
                            <h2 class="text-sm font-bold text-brand-dark">Recent Transactions</h2>
                        </div>
                        <a href="payments.php?tab=transactions" class="text-xs font-semibold text-brand-primary hover:underline">Log</a>
                    </div>

                    <div class="space-y-3 text-xs" id="activity_stream">
                        <?php foreach ($cashierData['recent_transactions'] as $index => $transaction): ?><div class="flex items-start justify-between pb-2 <?= $index < count($cashierData['recent_transactions']) - 1 ? 'border-b border-gray-100' : '' ?>">
                                <div>
                                    <p class="font-bold <?= $transaction['payment_status'] === 'Refunded' ? 'text-red-700' : 'text-brand-dark' ?>"><?= $esc($transaction['receipt_no'] ?: $transaction['request_no']) ?> <?= $transaction['payment_status'] === 'Refunded' ? 'Refunded' : 'Issued' ?></p>
                                    <p class="text-[10px] text-gray-500"><?= $esc($transaction['student_name']) ?> · <?= $esc($transaction['method']) ?><?= $transaction['reference_number'] ? ' · Ref: ' . $esc($transaction['reference_number']) : '' ?> · ₱<?= number_format((float) $transaction['amount'], 2) ?></p>
                                </div>
                                <span class="text-[10px] text-gray-400 whitespace-nowrap"><?= $transaction['verified_at'] ? $esc(date('M j, g:i A', strtotime($transaction['verified_at']))) : '—' ?></span>
                            </div><?php endforeach; ?>
                        <?php if (!$cashierData['recent_transactions']): ?><p class="text-gray-500">No completed transactions yet.</p><?php endif; ?>
                    </div>
                </section>

            </div>
        </div>

    </main>

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

            $('#mobile_menu_btn').on('click', function() {
                $('#mobile_menu').toggleClass('hidden');
            });
        });
    </script>
    <script src="../../js/private-page-history.js"></script>
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