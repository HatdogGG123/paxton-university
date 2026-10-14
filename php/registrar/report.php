<?php
require_once __DIR__ . '/../includes/auth.php';

ssis_require_role('registrar');

require_once __DIR__ . '/../../models/RegistrarReports.php';

$reportModel = new RegistrarReports(Database::getConnection());
$reportTerms = $reportModel->getTerms();
$reportPrograms = $reportModel->getPrograms();
$currentReportTerm = null;

foreach ($reportTerms as $candidateTerm) {
    if (!empty($candidateTerm['is_current'])) {
        $currentReportTerm = $candidateTerm;
        break;
    }
}

$reportFilters = [
    'term_id' => filter_input(INPUT_GET, 'term_id', FILTER_VALIDATE_INT) ?: (int) ($currentReportTerm['id'] ?? ($reportTerms[0]['id'] ?? 0)),
    'program_id' => filter_input(INPUT_GET, 'program_id', FILTER_VALIDATE_INT) ?: 0,
    'status' => is_string($_GET['status'] ?? null) ? (string) $_GET['status'] : '',
    'date_from' => is_string($_GET['date_from'] ?? null) ? (string) $_GET['date_from'] : '',
    'date_to' => is_string($_GET['date_to'] ?? null) ? (string) $_GET['date_to'] : '',
];

$allowedEnrollmentStatuses = ['', 'Pending', 'Under Review', 'Approved', 'Rejected', 'Enrolled', 'Cancelled'];

if (!in_array($reportFilters['status'], $allowedEnrollmentStatuses, true)) {
    $reportFilters['status'] = '';
}

foreach (['date_from', 'date_to'] as $dateField) {
    if ($reportFilters[$dateField] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reportFilters[$dateField])) {
        $reportFilters[$dateField] = '';
    }
}

$validTermIds = array_map(static fn($term): int => (int) $term['id'], $reportTerms);
if (!in_array($reportFilters['term_id'], $validTermIds, true)) {
    $reportFilters['term_id'] = (int) ($currentReportTerm['id'] ?? ($reportTerms[0]['id'] ?? 0));
}

$validProgramIds = array_map(static fn($program): int => (int) $program['id'], $reportPrograms);
if (!in_array($reportFilters['program_id'], $validProgramIds, true)) {
    $reportFilters['program_id'] = 0;
}

$reportRows = $reportModel->getRows($reportFilters);

$reportTotalEnrolled = array_sum(array_map(static fn($row): int => (int) $row['enrollment_count'], $reportRows));
$reportPendingRequests = array_sum(array_map(static fn($row): int => (int) $row['pending_request_count'], $reportRows));

$reportGradeCount = array_sum(array_map(static fn($row): int => (int) $row['grade_count'], $reportRows));
$reportReleasedGrades = array_sum(array_map(static fn($row): int => (int) $row['released_grade_count'], $reportRows));

$reportGradePercent = $reportGradeCount > 0 ? round(($reportReleasedGrades / $reportGradeCount) * 100, 1) : 0;

$reportEsc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Reports - Paxton SSIS</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- JQuery & Lucide Icons -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

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
                    <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
                    <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Report</a>
                    <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
                    <a href="announcements.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Announcements</a>
                </nav>

                <!-- Profile Info & Mobile Toggle -->
                <div class="flex items-center space-x-3">
                    <div class="hidden lg:flex items-center space-x-2">
                        <div class="text-right">
                            <p class="text-xs font-semibold text-brand-dark"><?= htmlspecialchars((string) ($_SESSION['user']['full_name'] ?? $_SESSION['user']['username'] ?? 'Registrar Staff'), ENT_QUOTES, 'UTF-8') ?></p>
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
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Clearance</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Report</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <a href="announcements.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Announcements</a>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Title Banner & Action Controls -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Registrar Reports & Analytics</h1>
                <p class="text-sm text-gray-500">Enrollment totals, document fulfillment status, and term grade completion.</p>
            </div>

            <!-- Export Buttons -->
            <div class="flex items-center space-x-2 print:hidden">
                <button onclick="window.print()" class="px-3 py-2 bg-white border border-gray-200 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors flex items-center space-x-1.5 shadow-xs">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Print Report</span>
                </button>
                <button onclick="exportTableToCSV('registrar_reports.csv')" class="px-3 py-2 bg-brand-primary text-white rounded-md text-xs font-semibold hover:bg-brand-secondary transition-colors flex items-center space-x-1.5 shadow-xs">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>Export CSV</span>
                </button>
            </div>
        </section>

        <!-- Filters Section -->
        <section class="bg-white border border-gray-200 rounded-md p-5 shadow-xs space-y-4 print:hidden">
            <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Report Filters</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <form id="report_filters" method="get" class="contents">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Academic Term</label>
                        <select name="term_id" class="w-full text-xs border border-gray-200 rounded-md p-2 focus:ring-brand-primary focus:border-brand-primary bg-gray-50/50" onchange="this.form.submit()">
                            <?php foreach ($reportTerms as $term): ?><option value="<?= (int) $term['id'] ?>" <?= (int) $reportFilters['term_id'] === (int) $term['id'] ? 'selected' : '' ?>><?= $reportEsc($term['semester'] . ' ' . $term['academic_year']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Program</label>
                        <select name="program_id" class="w-full text-xs border border-gray-200 rounded-md p-2 focus:ring-brand-primary focus:border-brand-primary bg-gray-50/50">
                            <option value="">All Programs</option>
                            <?php foreach ($reportPrograms as $program): ?><option value="<?= (int) $program['id'] ?>" <?= $reportFilters['program_id'] === (int) $program['id'] ? 'selected' : '' ?>><?= $reportEsc($program['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                        <select name="status" class="w-full text-xs border border-gray-200 rounded-md p-2 focus:ring-brand-primary focus:border-brand-primary bg-gray-50/50">
                            <option value="">All Statuses</option>
                            <?php foreach (['Pending', 'Under Review', 'Approved', 'Rejected', 'Enrolled', 'Cancelled'] as $statusOption): ?><option value="<?= $reportEsc($statusOption) ?>" <?= $reportFilters['status'] === $statusOption ? 'selected' : '' ?>><?= $reportEsc($statusOption) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Date Range</label>
                        <input type="date" name="date_from" value="<?= $reportEsc($reportFilters['date_from']) ?>" aria-label="Date from" class="w-full text-xs border border-gray-200 rounded-md p-2 focus:ring-brand-primary focus:border-brand-primary bg-gray-50/50">
                        <input type="date" name="date_to" value="<?= $reportEsc($reportFilters['date_to']) ?>" aria-label="Date to" class="mt-1 w-full text-xs border border-gray-200 rounded-md p-2 focus:ring-brand-primary focus:border-brand-primary bg-gray-50/50">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4 flex justify-end"><button type="submit" class="px-3 py-2 bg-brand-primary text-white rounded-md text-xs font-semibold hover:bg-brand-secondary">Apply Filters</button></div>
                </form>
            </div>
        </section>

        <!-- Summary Metrics -->
        <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white border border-gray-200 rounded-md p-5 shadow-xs space-y-2">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Enrollment Requests</span>
                <p class="text-2xl font-bold text-brand-dark"><?= number_format($reportTotalEnrolled) ?></p>
                <p class="text-xs text-gray-500 font-medium">For selected term and status</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-md p-5 shadow-xs space-y-2">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Document Requests</span>
                <p class="text-2xl font-bold text-brand-dark"><?= number_format($reportPendingRequests) ?> Pending</p>
                <p class="text-xs text-amber-600 font-medium">Across matching programs</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-md p-5 shadow-xs space-y-2">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Grades Released</span>
                <p class="text-2xl font-bold text-brand-dark"><?= number_format($reportGradePercent, 1) ?>%</p>
                <p class="text-xs text-brand-secondary font-medium"><?= number_format(max(0, $reportGradeCount - $reportReleasedGrades)) ?> grades remaining</p>
            </div>
        </section>

        <!-- Main Report Table Card -->
        <div class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-brand-dark">Enrollment & Document Summary Report</h2>
                    <p class="text-xs text-gray-500">Live breakdown per program and academic status</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="report_table" class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-5">Program</th>
                            <th class="py-3 px-5 text-center">Enrollment Count</th>
                            <th class="py-3 px-5">Document Requests Status</th>
                            <th class="py-3 px-5 text-center">Grades Released</th>
                            <th class="py-3 px-5 text-right">Program Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                        <?php foreach ($reportRows as $row): $gradePercent = (int) $row['grade_count'] > 0 ? round(((int) $row['released_grade_count'] / (int) $row['grade_count']) * 100, 1) : 0; ?>
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="py-3.5 px-5 font-bold text-brand-dark"><?= $reportEsc($row['name']) ?></td>
                                <td class="py-3.5 px-5 text-center font-semibold"><?= number_format((int) $row['enrollment_count']) ?></td>
                                <td class="py-3.5 px-5"><?= number_format((int) $row['pending_request_count']) ?> pending / <?= number_format((int) $row['request_count']) ?> total</td>
                                <td class="py-3.5 px-5 text-center font-bold <?= $gradePercent >= 90 ? 'text-emerald-700' : 'text-brand-secondary' ?>"><?= number_format($gradePercent, 1) ?>%</td>
                                <td class="py-3.5 px-5 text-right"><span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold <?= !empty($row['is_active']) ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700' ?>"><?= !empty($row['is_active']) ? 'Active' : 'Inactive' ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$reportRows): ?><tr>
                                <td colspan="5" class="py-8 px-5 text-center text-gray-500">No programs match these report filters.</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Formal Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500 print:hidden">
        <div class="max-w-7xl mx-auto px-4">
            <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
        $(document).ready(function() {
            lucide.createIcons();
            $('#btn_mobile_menu').on('click', function() {
                $('#mobile_nav_menu').toggleClass('hidden');
            });
        });

        function exportTableToCSV(filename) {
            let csv = [];
            let rows = document.querySelectorAll("#report_table tr");
            for (let i = 0; i < rows.length; i++) {
                let row = [],
                    cols = rows[i].querySelectorAll("td, th");
                for (let j = 0; j < cols.length; j++) row.push('"' + cols[j].innerText + '"');
                csv.push(row.join(","));
            }
            let csvFile = new Blob([csv.join("\n")], {
                type: "text/csv"
            });
            let downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
        }
    </script>
    <script src="../../js/private-page-history.js"></script>
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