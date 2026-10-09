<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('student');
require_once __DIR__ . '/../../models/Clearance.php';
$clearanceModel = new Clearance(Database::getConnection());
try {
    $term = $clearanceModel->getCurrentTerm();
    if ($term !== null) {
        $clearanceModel->ensureTermClearances((int) $term['id']);
        $clearanceRecord = $clearanceModel->getStudentClearance((int) $_SESSION['user']['id'], (int) $term['id']);
    } else {
        $clearanceRecord = null;
    }
} catch (Throwable $exception) {
    error_log('SSIS student clearance page failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Clearance information is temporarily unavailable. Please try again later.');
}
$esc = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$studentName = $clearanceRecord ? trim(implode(' ', array_filter([$clearanceRecord['first_name'], $clearanceRecord['middle_name'], $clearanceRecord['last_name']], static fn ($part): bool => $part !== null && $part !== ''))) : ($_SESSION['user']['full_name'] ?? $_SESSION['user']['username']);
$approvedCount = $clearanceRecord ? count(array_filter($clearanceRecord['items'], static fn ($item): bool => $item['status'] === 'Approved')) : 0;
$totalRequirements = $clearanceRecord ? count($clearanceRecord['items']) : 0;
$overallStyle = ['In Progress' => 'bg-amber-100 text-amber-800', 'Action Needed' => 'bg-red-100 text-red-800', 'Cleared' => 'bg-emerald-100 text-emerald-800'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Student Clearance</title>

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
                    <a href="clearance.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20">Clearance</a>
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
                            <p id="nav_student_id" class="text-[10px] text-gray-500"><?= $esc($clearanceRecord['student_no'] ?? '') ?></p>
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
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">Clearance</a>
            <a href="requests.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Requests</a>
            <a href="profile.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Profile</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-brand-dark"><?= $esc($studentName) ?></p>
                    <p class="text-xs text-gray-500"><?= $esc($clearanceRecord['student_no'] ?? '') ?></p>
                </div>
                <a href="logout.php" class="text-xs text-red-600 font-semibold hover:underline">Log Out</a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Student Clearance</h1>
                <p class="text-sm text-gray-500">Track clearance progress across university departments and offices.</p>
            </div>
            <div class="inline-flex items-center gap-2 bg-white border border-gray-200 rounded-md px-3 py-2 text-xs font-semibold text-gray-700 shadow-xs">
                <i data-lucide="shield-check" class="w-4 h-4 text-brand-secondary"></i>
                <span>Academic Term: <strong id="lbl_clearance_term" class="text-brand-dark"><?= $term ? $esc($term['semester'] . ', AY ' . $term['academic_year']) : 'No active term' ?></strong></span>
            </div>
        </div>

        <!-- Clearance Status Banner -->
        <section id="banner_overall_clearance" class="bg-white border border-gray-200 rounded-md p-6 shadow-xs space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 pb-4">
                <div class="flex items-start space-x-3">
                    <div class="p-2.5 rounded-md bg-amber-50 text-amber-600 shrink-0 mt-0.5">
                        <i data-lucide="clock" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Overall Clearance Status</span>
                            <span id="badge_overall_status" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold <?= $overallStyle[$clearanceRecord['overall_status'] ?? 'In Progress'] ?>"><?= $esc($clearanceRecord['overall_status'] ?? 'In Progress') ?></span>
                        </div>
                        <p class="text-sm font-bold text-brand-dark mt-1"><span id="lbl_approved_count"><?= $approvedCount ?></span> of <span id="lbl_total_offices"><?= $totalRequirements ?></span> Clearances Approved</p>
                    </div>
                </div>

                <!-- Print Action (Disabled until status is Cleared) -->
                <div>
                    <button id="btn_print_clearance" type="button" <?= !$clearanceRecord || $clearanceRecord['overall_status'] !== 'Cleared' ? 'disabled' : '' ?> onclick="window.print()" class="inline-flex items-center space-x-2 px-3 py-2 border border-gray-200 rounded-md text-xs font-semibold <?= $clearanceRecord && $clearanceRecord['overall_status'] === 'Cleared' ? 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100' : 'text-gray-400 bg-gray-100 cursor-not-allowed' ?> transition-colors">
                        <i data-lucide="printer" class="w-4 h-4"></i>
                        <span>Print Clearance Form</span>
                    </button>
                </div>
            </div>

            <div class="text-xs text-gray-600">
                <p>All institutional liabilities must be cleared by respective department heads before official documents or enrollment permissions can be granted.</p>
            </div>
        </section>

        <!-- Department Clearance Breakdown Table -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-2 rounded-md bg-brand-accent/40 text-brand-primary">
                        <i data-lucide="building-2" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-brand-dark">Department Approvals & Requirements</h2>
                        <p class="text-xs text-gray-500">Individual status updates from assigned administrative offices</p>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table id="table_clearance_offices" class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-5">Department / Office</th>
                            <th class="py-3 px-5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                        <?php foreach (($clearanceRecord['items'] ?? []) as $item): $badge = $item['status'] === 'Approved' ? 'bg-emerald-100 text-emerald-800' : ($item['status'] === 'Rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'); ?>
                        <tr class="hover:bg-gray-50/50 transition-colors"><td class="py-3.5 px-5 font-bold text-brand-dark"><?= $esc($item['department_name'] ?: $item['office']) ?> — <?= $esc($item['name']) ?><?php if ($item['description']): ?><p class="mt-1 text-[11px] text-gray-500 font-normal"><?= $esc($item['description']) ?></p><?php endif; ?><?php if ($item['rejection_reason']): ?><p class="mt-1 text-[11px] text-red-600 font-normal">Reason: <?= $esc($item['rejection_reason']) ?></p><?php elseif ($item['remarks']): ?><p class="mt-1 text-[11px] text-gray-500 font-normal"><?= $esc($item['remarks']) ?></p><?php endif; ?></td><td class="py-3.5 px-5 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold <?= $badge ?>"><?= $esc($item['status']) ?></span></td></tr>
                        <?php endforeach; ?>
                        <?php if ($clearanceRecord === null || $clearanceRecord['items'] === []): ?><tr><td colspan="2" class="py-6 px-5 text-center text-gray-500">No active clearance requirements are available for this term.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Footer Policy Note -->
            <div class="p-4 bg-gray-50/80 border-t border-gray-100 flex items-center space-x-2 text-xs text-gray-500">
                <i data-lucide="info" class="w-4 h-4 text-brand-secondary shrink-0"></i>
                <p>Clearance statuses are updated manually by designated office staff members upon fulfilling requirements.</p>
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
