<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('student');
require_once __DIR__ . '/../../services/DocumentRequestsService.php';
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$requestModel = new DocumentRequests(Database::getConnection());
$requestController = new DocumentRequestsService($requestModel);
$userId = (int) $_SESSION['user']['id'];
$notice = $_SESSION['student_request_notice'] ?? null;
unset($_SESSION['student_request_notice']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) { throw new DomainException('This form expired. Refresh the page and try again.'); }
        $requestNo = $requestController->submitStudentRequest($userId, $_POST);
        $_SESSION['student_request_notice'] = ['success' => true, 'message' => 'Request ' . $requestNo . ' was submitted.'];
        header('Location: requests.php');
        exit;
    } catch (DomainException $exception) {
        $notice = ['success' => false, 'message' => $exception->getMessage()];
    } catch (Throwable $exception) {
        error_log('SSIS student document request failed: ' . $exception->getMessage());
        $notice = ['success' => false, 'message' => 'We could not submit your request. Please try again.'];
    }
}
try {
    $types = $requestModel->getActiveTypes();
    $requests = $requestModel->getStudentRequests($userId);
    $studentQuery = Database::getConnection()->prepare('SELECT student_no, first_name, middle_name, last_name FROM students WHERE user_id = :user_id LIMIT 1');
    $studentQuery->execute(['user_id' => $userId]);
    $studentProfile = $studentQuery->fetch();
    if (!$studentProfile) { http_response_code(404); exit('No student profile is linked to this account. Please contact the Registrar.'); }
} catch (Throwable $exception) {
    error_log('SSIS student document requests page failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Document request information is temporarily unavailable. Please try again later.');
}
$esc = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$studentName = trim(implode(' ', array_filter([$studentProfile['first_name'], $studentProfile['middle_name'], $studentProfile['last_name']], static fn ($part): bool => $part !== null && $part !== '')));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Document Requests</title>

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
                    <a href="enrollment.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Enrollment</a>
                    <a href="grades.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Grades</a>
                    <a href="clearance.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Clearance</a>
                    <a href="requests.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20">Requests</a>
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
                            <p id="nav_student_id" class="text-[10px] text-gray-500"><?= $esc($studentProfile['student_no']) ?></p>
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
            <a href="enrollment.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Enrollment</a>
            <a href="grades.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Grades</a>
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
            <a href="requests.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">Requests</a>
            <a href="profile.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Profile</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-brand-dark"><?= $esc($studentName) ?></p>
                    <p class="text-xs text-gray-500"><?= $esc($studentProfile['student_no']) ?></p>
                </div>
                <a href="logout.php" class="text-xs text-red-600 font-semibold hover:underline">Log Out</a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Page Header -->
        <div>
            <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Document Requests</h1>
            <p class="text-sm text-gray-500">Submit requests for official academic credentials and track processing status.</p>
        </div>

        <!-- New Document Request Form Card -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs">
            <div class="flex items-center space-x-3 mb-5 pb-3 border-b border-gray-100">
                <div class="p-2 rounded-md bg-brand-accent/40 text-brand-primary">
                    <i data-lucide="file-plus-2" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-base font-bold text-brand-dark">Request New Document</h2>
                    <p class="text-xs text-gray-500">Fill out the fields below to file a formal credential request</p>
                </div>
            </div>

            <form id="form_submit_request" method="POST" action="requests.php" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $esc($_SESSION['csrf_token']) ?>">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="select_document_type" class="block text-xs font-semibold text-gray-700 mb-1">Document Type *</label>
                        <!-- Added data-fee attributes to each option for easy dynamic calculation -->
                        <select id="select_document_type" name="document_type_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-xs text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none">
                            <option value="" disabled selected>Select document...</option>
                            <?php foreach ($types as $type): ?><option value="<?= (int) $type['id'] ?>" data-fee="<?= $esc($type['fee']) ?>"><?= $esc($type['name']) ?> - <?= (float) $type['fee'] === 0.0 ? 'Free' : '₱' . number_format((float) $type['fee'], 2) ?></option><?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="select_purpose" class="block text-xs font-semibold text-gray-700 mb-1">Purpose *</label>
                        <select id="select_purpose" name="purpose" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-xs text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none">
                            <option value="" disabled selected>Select purpose...</option>
                            <option value="employment">Employment</option>
                            <option value="further_studies">Further Studies / Transfer</option>
                            <option value="scholarship">Scholarship Application</option>
                            <option value="visa">Visa / Travel Requirements</option>
                        </select>
                    </div>

                    <div>
                        <label for="input_quantity" class="block text-xs font-semibold text-gray-700 mb-1">Number of Copies *</label>
                        <input type="number" id="input_quantity" name="quantity" min="1" max="10" value="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-xs text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none">
                    </div>
                </div>

                <div>
                    <label for="textarea_remarks" class="block text-xs font-semibold text-gray-700 mb-1">Additional Notes / Special Instructions</label>
                    <textarea id="textarea_remarks" name="remarks" rows="2" placeholder="Optional notes for registrar office..." class="w-full px-3 py-2 border border-gray-300 rounded-md text-xs text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none"></textarea>
                </div>

                <!-- Fee Display Paragraph -->
                <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                    <p id="fee_display" class="text-xs font-medium text-gray-600">
                        Estimated Fee: <span id="fee_amount" class="text-sm font-bold text-brand-dark">₱0.00</span>
                    </p>

                    <button type="submit" id="btn_submit_request" class="inline-flex items-center space-x-2 px-4 py-2 bg-brand-primary text-white text-xs font-semibold rounded-md hover:bg-brand-primary/90 transition-colors shadow-xs">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        <span>Submit Request</span>
                    </button>
                </div>
            </form>
        </section>

        <!-- Requests History Table Card -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-md bg-brand-accent/40 text-brand-primary">
                        <i data-lucide="history" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-brand-dark">Request History & Tracking</h2>
                        <p class="text-xs text-gray-500">Complete list of previous requests and real-time statuses</p>
                    </div>
                </div>
            </div>

            <!-- Table with All Document Request Statuses (Plain Text) -->
            <div class="overflow-x-auto">
                <table id="table_requests_history" class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-5">Request Ref</th>
                            <th class="py-3 px-5">Document Title</th>
                            <th class="py-3 px-5">Date Filed</th>
                            <th class="py-3 px-5 text-center">Payment Status</th>
                            <th class="py-3 px-5 text-center">Request Status</th>
                            <th class="py-3 px-5 text-right">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                        <?php foreach ($requests as $request): $paymentStatus = $request['payment_status'] ?? ((float) $request['fee_amount'] > 0 ? 'Unpaid' : 'No payment required'); $details = $request['status'] === 'Rejected' ? $request['rejection_reason'] : ($request['remarks'] ?: '—'); if ($request['status'] === 'For Payment') { $details = 'Amount due: ₱' . number_format((float) $request['fee_amount'] * (int) $request['copies'], 2); } elseif ($request['receipt_no']) { $details = 'Receipt: ' . $request['receipt_no']; } ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="py-3.5 px-5 font-bold text-brand-dark"><?= $esc($request['request_no']) ?></td>
                            <td class="py-3.5 px-5"><?= $esc($request['document_name']) ?><?php if ((int) $request['copies'] > 1): ?><span class="text-gray-500"> (<?= (int) $request['copies'] ?> copies)</span><?php endif; ?></td>
                            <td class="py-3.5 px-5 text-gray-500"><?= $esc(date('M d, Y', strtotime($request['created_at']))) ?></td>
                            <td class="py-3.5 px-5 text-center text-gray-700"><?= $esc($paymentStatus) ?></td>
                            <td class="py-3.5 px-5 text-center text-gray-700"><?= $esc($request['status']) ?></td>
                            <td class="py-3.5 px-5 text-right text-gray-500"><?= $esc($details) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if ($requests === []): ?><tr><td colspan="6" class="py-8 px-5 text-center text-gray-500">You have not submitted any document requests.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer Policy Note -->
            <div class="p-4 bg-gray-50/80 border-t border-gray-100 flex items-center space-x-2 text-xs text-gray-500">
                <i data-lucide="info" class="w-4 h-4 text-brand-secondary shrink-0"></i>
            </div>
        </section>

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

            $('#select_document_type, #input_quantity').on('change input', updateEstimatedFee);
            updateEstimatedFee();
            <?php if ($notice): ?>showToast(<?= json_encode($notice['success'] ? 'success' : 'error') ?>, <?= json_encode($notice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);<?php endif; ?>

            $('#mobile_menu_btn').on('click', function() {
                $('#mobile_menu').toggleClass('hidden');
            });
        });

        function updateEstimatedFee() {
            const fee = parseFloat($('#select_document_type option:selected').data('fee')) || 0;
            const copies = parseInt($('#input_quantity').val(), 10) || 1;
            $('#fee_amount').text(fee === 0 ? 'Free' : '₱' + (fee * copies).toFixed(2));
        }
    </script>
    <script src="../../js/private-page-history.js"></script>
<script>
(function ($) {
    if (!$) return;
    $('#form_submit_request').on('submit', function (event) {
        event.preventDefault();
        var form = this;
        var button = document.getElementById('btn_submit_request');
        if (button.disabled) return;
        var originalButtonHtml = button.innerHTML;
        button.disabled = true;
        button.classList.add('opacity-60', 'cursor-not-allowed');
        button.innerHTML = '<span>Submitting...</span>';

        $.ajax({
            url: '../../controller/documentRequestController.php',
            method: 'POST',
            data: $(form).serialize(),
            dataType: 'json'
        }).done(function (response) {
                if (response && response.status === 'success') {
                    showToast('success', response.message || 'Document request submitted.');
                    var selected = form.querySelector('#select_document_type option:checked');
                    var quantity = Math.max(1, parseInt(form.querySelector('#input_quantity').value, 10) || 1);
                    var fee = parseFloat(selected && selected.dataset.fee) || 0;
                    var body = document.querySelector('#table_requests_history tbody');
                    body.querySelector('tr td[colspan="6"]')?.closest('tr')?.remove();
                    var row = document.createElement('tr');
                    row.className = 'hover:bg-gray-50/50 transition-colors';
                    var cells = [response.data && response.data.request_no || response.message.match(/DR-[A-Z0-9-]+/)?.[0] || 'New request', (selected ? selected.textContent.split(' - ')[0] : 'Document') + (quantity > 1 ? ' (' + quantity + ' copies)' : ''), new Date().toLocaleDateString('en-US', {month:'short', day:'2-digit', year:'numeric'}), fee > 0 ? 'Unpaid' : 'No payment required', 'Pending', form.querySelector('[name="remarks"]').value.trim() || '—'];
                    cells.forEach((value, index) => { var cell = document.createElement('td'); cell.className = 'py-3.5 px-5' + (index === 0 ? ' font-bold text-brand-dark' : '') + (index === 2 ? ' text-gray-500' : '') + (index === 3 || index === 4 ? ' text-center text-gray-700' : '') + (index === 5 ? ' text-right text-gray-500' : ''); cell.textContent = value; row.appendChild(cell); });
                    body.prepend(row);
                    // The shared table pager may have the history table on a later page.
                    // Return it to page one so the newly submitted request is visible.
                    document.querySelector('#table_requests_history').dataset.localPage = '1';
                    form.reset();
                    updateEstimatedFee();
                } else {
                    var message = response && response.message ? response.message : 'The action could not be completed.';
                    showToast('error', message);
                }
            }).fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The action could not be completed. Please try again.';
                showToast('error', message);
            }).always(function () {
                button.disabled = false;
                button.classList.remove('opacity-60', 'cursor-not-allowed');
                button.innerHTML = originalButtonHtml;
            });
    });
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
