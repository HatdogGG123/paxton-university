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
    <title>Paxton University - System Monitoring</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- jQuery CDN -->
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
                    <a href="monitoring.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20 transition-colors">System Monitoring</a>
                    <a href="notifications.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Notifications</a>
                    <a href="report.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Reports</a>
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
            <a href="monitoring.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">System Monitoring</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Reports</a>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Page Title & Section Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">System Monitoring & Audit Logs</h1>
                <p class="text-xs text-gray-500 mt-1">Real-time oversight for user activity, system totals, pending transactions, and operational logs.</p>
            </div>
        </div>

        <!-- 1. DASHBOARD OVERVIEW: SUMMARY METRICS & TOTALS -->
        <section class="space-y-4">
            <h2 class="text-sm font-bold uppercase text-gray-500 tracking-wider">System Overview & Metric Totals</h2>

            <!-- Metrics Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

                <!-- Users Total Card with Role Breakdowns -->
                <div class="bg-white p-5 rounded-md border border-gray-200 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-500">Total Registered Users</span>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-brand-dark"><?= number_format((int) $auditSummary['active_users']) ?></div>
                        <p class="text-[11px] text-gray-400 mt-0.5">Active accounts in database</p>
                    </div>
                    <p class="pt-2 border-t border-gray-100 text-[11px] text-gray-500">Across all active SSIS roles</p>
                </div>

                <!-- Enrollments Card -->
                <div class="bg-white p-5 rounded-md border border-gray-200 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-500">Audit Events Today</span>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-brand-dark"><?= number_format((int) $auditSummary['today_events']) ?></div>
                        <p class="text-[11px] text-gray-500 font-medium mt-0.5">Recorded activity since midnight</p>
                    </div>
                    <div class="pt-2 border-t border-gray-100 flex justify-between items-center text-[11px]">
                        <span class="text-gray-500">Total loaded history:</span>
                        <span class="font-semibold text-brand-dark"><?= number_format(count($auditEntries)) ?> events</span>
                    </div>
                </div>

                <!-- Pending Requests Card -->
                <div class="bg-white p-5 rounded-md border border-gray-200 shadow-xs space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-500">Recent Audit Records</span>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-amber-600"><?= number_format(count($auditEntries)) ?></div>
                        <p class="text-[11px] text-gray-400 mt-0.5">Newest recorded events</p>
                    </div>
                    <div class="pt-2 border-t border-gray-100 flex justify-between items-center text-[11px]">
                        <span class="text-gray-500">Showing up to:</span>
                        <span class="font-semibold text-gray-800">500 events</span>
                    </div>
                </div>

                <!-- Payments Processed Card -->
                <div class="bg-white p-5 rounded-md border border-gray-200 shadow-xs space-y-3">
                    <div>
                        <div class="text-2xl font-bold text-brand-dark"><?= number_format((int) $auditSummary['today_logins']) ?></div>
                        <p class="text-[11px] text-gray-400 mt-0.5">Successful logins today</p>
                    </div>
                    <div class="pt-2 border-t border-gray-100 flex justify-between items-center text-[11px]">
                        <span class="text-gray-500">Failed login attempts:</span>
                        <span class="font-semibold text-amber-600"><?= number_format((int) $auditSummary['failed_logins']) ?> today</span>
                    </div>
                </div>

            </div>
        </section>

        <!-- 2. AUDIT LOG & USER ACTIVITY SECTION -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs p-6 space-y-6">

            <!-- Section Title & Search Header -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-gray-100 pb-4">
                <div>
                    <h2 class="text-lg font-bold text-brand-dark">User Activity & Audit Logs</h2>
                    <p class="text-xs text-gray-500">Monitor system access, authentication events, data modifications, and user activity.</p>
                </div>
            </div>

            <!-- Filter Controls Panel -->
            <div class="bg-gray-50 p-4 rounded-md border border-gray-200 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">

                <!-- Search Box -->
                <div class="lg:col-span-2">
                    <label for="filter_search" class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Search User or Keyword</label>
                    <div class="relative">
                        <input type="text" id="filter_search" placeholder="Search name, ID, or IP address..." class="w-full text-xs pl-3 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                    </div>
                </div>

                <!-- Role Filter -->
                <div>
                    <label for="filter_role" class="block text-[11px] font-bold uppercase text-gray-500 mb-1">User Role</label>
                    <select id="filter_role" class="w-full text-xs px-2.5 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                        <option value="ALL">All Roles</option>
                        <option value="Student">Student</option>
                        <option value="Department">Department</option>

                        <option value="Registrar">Registrar</option>
                        <option value="Admin">Administrator</option>
                    </select>
                </div>

                <!-- Action Filter -->
                <div>
                    <label for="filter_action" class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Action Type</label>
                    <select id="filter_action" class="w-full text-xs px-2.5 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                        <option value="ALL">All Actions</option>
                        <option value="Login">LOGIN / LOGOUT</option>
                        <option value="UPDATE">UPDATE / EDIT</option>
                        <option value="CREATE">CREATE / ADD</option>
                        <option value="PAYMENT">PAYMENT</option>
                        <option value="DISABLE">DISABLE / DELETE</option>
                    </select>
                </div>

                <!-- Date Range Filter -->
                <div>
                    <label for="filter_date" class="block text-[11px] font-bold uppercase text-gray-500 mb-1">Date Filter</label>
                    <input type="date" id="filter_date" value="" class="w-full text-xs px-2.5 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                </div>

            </div>

            <!-- Audit Logs Table -->
            <div class="overflow-x-auto border border-gray-200 rounded-md">
                <table class="w-full text-left text-xs" style="table-layout:fixed;min-width:960px">
                    <colgroup>
                        <col style="width:15%">
                        <col style="width:19%">
                        <col style="width:12%">
                        <col style="width:23%">
                        <col style="width:18%">
                        <col style="width:13%">
                    </colgroup>
                    <thead class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-200">
                        <tr>
                            <th class="py-3 px-4">Timestamp</th>
                            <th class="py-3 px-4">User Details</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Action Performing</th>
                            <th class="py-3 px-4">Module Details</th>
                            <th class="py-3 px-4 text-right">IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="audit_log_tbody" class="divide-y divide-gray-100 text-gray-700"><?php require __DIR__ . '/../includes/audit-monitoring-rows.php'; ?></tbody>
                </table>
            </div>

        </section>

    </main>

    <!-- Formal Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500">
        <div class="max-w-7xl mx-auto px-4">
            <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Interactive Filtering Scripts -->

    <script>
        $(document).ready(function() {

            // Mobile menu toggle
            $('#mobile_menu_btn').on('click', function() {
                $('#mobile_menu').toggleClass('hidden');
            });

            // Filter functionality
            function filterLogs() {
                const searchVal = $('#filter_search').val().toLowerCase();
                const roleVal = $('#filter_role').val();
                const actionVal = $('#filter_action').val();
                const dateVal = $('#filter_date').val();

                let visibleCount = 0;

                $('.log-row').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    const rowRole = $(this).attr('data-role');
                    const rowAction = $(this).attr('data-action');
                    const rowDate = $(this).attr('data-date');

                    const matchesSearch = rowText.includes(searchVal);
                    const matchesRole = (roleVal === 'ALL' || rowRole === roleVal);
                    const matchesAction = (actionVal === 'ALL' || rowAction === actionVal);
                    const matchesDate = (!dateVal || rowDate === dateVal);

                    const matches = matchesSearch && matchesRole && matchesAction && matchesDate;
                    this.dataset.filteredOut = matches ? 'false' : 'true';
                    if (matches) visibleCount++;
                });

                $('#log_count').text(visibleCount);
            }

            // Bind Event Listeners for Filters
            $('#filter_search').on('keyup', filterLogs);
            $('#filter_role, #filter_action, #filter_date').on('change', filterLogs);
        });
    </script>
    <script src="../../js/private-page-history.js"></script>
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
                        cell.className = 'py-12 px-5 text-center text-gray-500';
                        cell.colSpan = table.tHead?.rows[0]?.cells.length || 1;
                        emptyRow.appendChild(cell);
                        body.appendChild(emptyRow);
                    }
                    emptyRow.hidden = false;
                    const emptyCell = emptyRow.querySelector('td');
                    if (emptyCell) {
                        emptyCell.textContent = dataRows.length ? 'No records match the selected filters.' : 'No records to show.';
                        emptyCell.classList.add('text-center');
                    }
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