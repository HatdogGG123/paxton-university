<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('cashier');
require_once __DIR__ . '/../../models/Payments.php';

$paymentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($paymentId === false || $paymentId === null) {
    http_response_code(400);
    exit('A valid receipt was not selected.');
}

try {
    $receipt = (new Payments(Database::getConnection()))->getReceipt((int) $paymentId);
} catch (Throwable $exception) {
    error_log('SSIS cashier receipt lookup failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Receipt information is temporarily unavailable.');
}
if (!$receipt) {
    http_response_code(404);
    exit('The requested payment receipt was not found.');
}
$esc = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$studentName = trim(implode(' ', array_filter([$receipt['first_name'], $receipt['middle_name'], $receipt['last_name']], static fn ($part): bool => $part !== null && $part !== '')));
$cashierName = trim((string) ($receipt['cashier_name'] ?? '')) ?: (string) ($receipt['cashier_username'] ?? 'Cashier');
$verifiedAt = $receipt['verified_at'] ? date('F j, Y g:i A', strtotime($receipt['verified_at'])) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Receipt <?= $esc($receipt['receipt_no']) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; background: #f3f4f6; color: #202020; font: 14px Arial, sans-serif; }
        .receipt { width: min(760px, 100%); margin: 0 auto; padding: 36px; background: #fff; border: 1px solid #d1d5db; }
        .header { text-align: center; border-bottom: 2px solid #344e41; padding-bottom: 20px; }
        .header h1 { margin: 0 0 6px; color: #344e41; font-size: 22px; }
        .header p { margin: 3px 0; color: #555; }
        .title { margin: 24px 0; text-align: center; font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .meta, .student, .items { width: 100%; border-collapse: collapse; margin: 14px 0; }
        .meta td, .student td, .items td, .items th { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        .meta td:first-child, .student td:first-child { width: 35%; color: #666; }
        .items th { background: #f9fafb; color: #555; font-size: 12px; text-transform: uppercase; }
        .items .amount { text-align: right; }
        .total { display: flex; justify-content: flex-end; margin-top: 22px; font-size: 18px; font-weight: bold; }
        .status { margin-top: 18px; padding: 10px; background: #ecfdf5; color: #065f46; text-align: center; font-weight: bold; }
        .footer { margin-top: 35px; color: #666; font-size: 12px; text-align: center; }
        .actions { display: flex; justify-content: center; gap: 10px; margin: 18px auto; }
        .actions button { padding: 9px 16px; border: 0; border-radius: 4px; background: #344e41; color: white; cursor: pointer; }
        .actions a { padding: 9px 16px; border: 1px solid #ccc; border-radius: 4px; color: #333; text-decoration: none; }
        @media print { body { padding: 0; background: #fff; } .receipt { width: 100%; border: 0; padding: 12mm; } .actions { display: none; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Print Receipt</button><a href="payments.php">Back to Payments</a></div>
    <main class="receipt">
        <header class="header">
            <h1>Paxton University</h1>
            <p>Student Services Information System</p>
            <p>Cashier Official Receipt</p>
        </header>
        <div class="title">OFFICIAL RECEIPT</div>
        <table class="meta">
            <tr><td>Receipt Number</td><td><strong><?= $esc($receipt['receipt_no']) ?></strong></td></tr>
            <tr><td>Date Verified</td><td><?= $esc($verifiedAt) ?></td></tr>
            <tr><td>Cashier</td><td><?= $esc($cashierName) ?></td></tr>
            <tr><td>Payment Status</td><td><?= $esc($receipt['payment_status']) ?></td></tr>
        </table>
        <table class="student">
            <tr><td>Student</td><td><?= $esc($studentName) ?></td></tr>
            <tr><td>Student Number</td><td><?= $esc($receipt['student_no']) ?></td></tr>
            <tr><td>Program</td><td><?= $esc($receipt['program_name']) ?></td></tr>
            <tr><td>Document Request</td><td><?= $esc($receipt['request_no']) ?></td></tr>
        </table>
        <table class="items">
            <thead><tr><th>Description</th><th>Method / Reference</th><th class="amount">Amount</th></tr></thead>
            <tbody><tr><td><?= $esc($receipt['document_name']) ?> (<?= (int) $receipt['copies'] ?> copies)</td><td><?= $esc($receipt['method']) ?><?= $receipt['reference_number'] ? ' / ' . $esc($receipt['reference_number']) : '' ?></td><td class="amount">₱<?= number_format((float) $receipt['amount'], 2) ?></td></tr></tbody>
        </table>
        <div class="total">Total Paid: ₱<?= number_format((float) $receipt['amount'], 2) ?></div>
        <div class="status"><?= $receipt['payment_status'] === 'Refunded' ? 'REFUNDED PAYMENT RECORD' : 'PAYMENT RECEIVED' ?></div>
        <footer class="footer">Request <?= $esc($receipt['request_no']) ?> · This receipt was generated by the SSIS cashier system.</footer>
    </main>
    <script>window.addEventListener('load', function() { window.print(); });</script>
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
