<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('admin');
require_once __DIR__ . '/../includes/audit-page-data.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin System Audit Reports - Paxton SSIS</title>

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
                        <span class="text-[10px] uppercase font-semibold text-gray-500 tracking-widest block mt-0.5">University SSIS - Admin</span>
                    </div>
                </div>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="userManagement.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">User Management</a>
                    <a href="systemSettings.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">System Settings</a>
                    <a href="monitoring.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">System Monitoring</a>
                    <a href="notifications.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Notifications</a>
                    <a href="report.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20 transition-colors">Reports</a>
                </nav>

                <!-- Action Controls -->
                <div class="flex items-center space-x-3">
                    <div class="h-5 w-px bg-gray-200 hidden md:block"></div>

                    <!-- User Info / Profile -->
                    <div class="hidden md:flex items-center space-x-2">
                        <div class="text-right">
                            <p id="nav_admin_name" class="text-xs font-semibold text-brand-dark">System Administrator</p>
                            <p id="nav_admin_role" class="text-[10px] text-gray-500">Admin (admin_01)</p>
                        </div>
                        <a href="logout.php" id="nav_logout_btn" class="p-2 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 transition-colors" title="Log Out">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                        </a>
                    </div>

                    <!-- Mobile Menu Button -->
                    <button id="mobile_menu_btn" type="button" class="md:hidden p-2 rounded-md text-gray-600 hover:text-brand-primary hover:bg-gray-100 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu Dropdown -->
        <div id="mobile_menu" class="hidden md:hidden border-t border-gray-200 bg-white px-4 pt-2 pb-4 space-y-1">
            <a href="userManagement.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">User Management</a>
            <a href="systemSettings.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">System Settings</a>
            <a href="monitoring.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">System Monitoring</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">Reports</a>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Banner Section -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">System Activity & Audit Trail</h1>
                <p class="text-sm text-gray-500">Comprehensive log of system interactions, active user roles, and security events.</p>
            </div>

            <div class="flex items-center space-x-2 print:hidden">
                <button onclick="window.print()" class="px-3 py-2 bg-white border border-gray-200 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors flex items-center space-x-1.5 shadow-xs">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                    <span>Print Log</span>
                </button>
                <button onclick="exportTableToCSV('admin_audit_log.csv')" class="px-3 py-2 bg-brand-primary text-white rounded-md text-xs font-semibold hover:bg-brand-secondary transition-colors flex items-center space-x-1.5 shadow-xs">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>Export CSV</span>
                </button>
            </div>
        </section>

        <!-- Filters Section -->
        <section class="bg-white border border-gray-200 rounded-md p-5 shadow-xs space-y-4 print:hidden">
            <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Audit Trail Filters</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Role Filter</label>
                    <select id="report_filter_role" class="w-full text-xs border border-gray-200 rounded-md p-2 focus:ring-brand-primary focus:border-brand-primary bg-gray-50/50">
                        <option value="">All User Roles</option><option>Admin</option>
                        <option>Registrar</option>
                        <option>Cashier</option>
                        <option>Department</option>
                        <option>Student</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">User Search</label>
                    <input id="report_filter_search" type="text" placeholder="Search Username or ID..." class="w-full text-xs border border-gray-200 rounded-md p-2 focus:ring-brand-primary focus:border-brand-primary bg-gray-50/50">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Action Type</label>
                    <select id="report_filter_action" class="w-full text-xs border border-gray-200 rounded-md p-2 focus:ring-brand-primary focus:border-brand-primary bg-gray-50/50">
                        <option value="">All Actions</option><option value="Login">Login / Authentication</option><option value="UPDATE">Update / Edit</option><option value="CREATE">Create / Add</option><option value="PAYMENT">Payment</option><option value="DISABLE">Disable / Delete / Reject</option>
                        
                        
                        
                        
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Date Range</label>
                    <input id="report_filter_date" type="date" class="w-full text-xs border border-gray-200 rounded-md p-2 focus:ring-brand-primary focus:border-brand-primary bg-gray-50/50">
                </div>
            </div>
        </section>

        <!-- Summary Metrics -->
        <section class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white border border-gray-200 rounded-md p-5 shadow-xs space-y-2">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Active Users</span>
                <p class="text-2xl font-bold text-brand-dark"><?= number_format((int) $auditSummary['active_users']) ?> Accounts</p>
                <p class="text-xs text-gray-400">across 4 core roles</p>
            </div>
            <div class="bg-white border border-gray-200 rounded-md p-5 shadow-xs space-y-2">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Failed Auth Alerts</span>
                <p class="text-2xl font-bold text-red-600"><?= number_format((int) $auditSummary['failed_logins']) ?> Alerts</p>
                <p class="text-xs text-red-500 font-medium">Requires review</p>
            </div>
        </section>

        <!-- Table Container -->
        <div class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-brand-dark">Detailed Audit Trail Log</h2>
                    <p class="text-xs text-gray-500">Real-time system transaction history</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table id="report_table" class="w-full text-left text-xs border-collapse" style="table-layout:fixed;min-width:780px">
                    <colgroup><col style="width:18%"><col style="width:15%"><col style="width:12%"><col style="width:37%"><col style="width:18%"></colgroup>
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                            <th class="py-3 px-5">Timestamp</th>
                            <th class="py-3 px-5">User</th>
                            <th class="py-3 px-5">Role</th>
                            <th class="py-3 px-5">Action Executed</th>
                            <th class="py-3 px-5 text-right">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 font-medium"><?php require __DIR__ . '/../includes/audit-report-rows.php'; ?></tbody>
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
            $('#mobile_menu_btn').on('click', function() {
                $('#mobile_menu').toggleClass('hidden');
            });
            function filterAuditRows() {
                const search = ($('#report_filter_search').val() || '').toLowerCase();
                const role = $('#report_filter_role').val();
                const action = $('#report_filter_action').val();
                const date = $('#report_filter_date').val();
                $('.audit-row').each(function() {
                    const row = $(this);
                    const matches = (!search || row.text().toLowerCase().includes(search))
                        && (!role || row.attr('data-role') === role)
                        && (!action || row.attr('data-action') === action)
                        && (!date || row.attr('data-date') === date);
                    // Keep rows in the table formatting context; display toggles can
                    // make a <tr> render as a block and collapse its columns.
                    row[0].dataset.filteredOut = matches ? 'false' : 'true';
                });
            }
            $('#report_filter_search').on('input', filterAuditRows);
            $('#report_filter_role, #report_filter_action, #report_filter_date').on('change', filterAuditRows);
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
(function () {
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
        allRows.forEach(row => { row.hidden = true; });
        const start = (page - 1) * pageSize;
        rows.slice(start, start + pageSize).forEach(row => { row.hidden = false; });
        if (!rows.length) {
            let emptyRow = allRows.find(row => row.classList.contains('table-empty-state') || row.querySelector('td[colspan]'));
            if (!emptyRow) { emptyRow = document.createElement('tr'); emptyRow.className = 'table-empty-state'; const cell = document.createElement('td'); cell.className = 'py-12 px-5 text-center text-gray-500'; cell.colSpan = table.tHead?.rows[0]?.cells.length || 1; emptyRow.appendChild(cell); body.appendChild(emptyRow); }
            emptyRow.hidden = false; const emptyCell = emptyRow.querySelector('td'); if (emptyCell) { emptyCell.textContent = dataRows.length ? 'No records match the selected filters.' : 'No records to show.'; emptyCell.classList.add('text-center'); }
        } else { allRows.filter(row => row.classList.contains('table-empty-state') || row.querySelector('td[colspan]')).forEach(row => row.remove()); }
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
