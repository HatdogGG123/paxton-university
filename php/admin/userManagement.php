<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('admin');
$accountEmailUsers = Database::getConnection()->query('SELECT u.id, u.username, u.email, u.role, u.department_id, u.status, d.name AS department_name, a.first_name, a.middle_name, a.last_name, a.contact_number, a.address, a.program_id FROM users u LEFT JOIN departments d ON d.id = u.department_id LEFT JOIN admission_applications a ON a.applicant_user_id = u.id ORDER BY u.role, u.username')->fetchAll();
$accountEmailPrograms = Database::getConnection()->query("SELECT p.id,p.name FROM programs p JOIN departments d ON d.id=p.department_id WHERE p.is_active=1 AND d.is_active=1 AND d.dept_type='Academic' ORDER BY d.name,p.name")->fetchAll();
$accountEmailDepartments = Database::getConnection()->query('SELECT id, code, name FROM departments WHERE is_active = 1 ORDER BY name')->fetchAll();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$accountEmailNotice = $_SESSION['account_email_notice'] ?? null;
unset($_SESSION['account_email_notice']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - User Management</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

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
                        <span class="text-[10px] uppercase font-semibold text-gray-500 tracking-widest block mt-0.5">University SSIS - Admin</span>
                    </div>
                </div>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1">
                    <a href="userManagement.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20 transition-colors">User Management</a>
                    <a href="systemSettings.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">System Settings</a>
                    <a href="monitoring.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">System Monitoring</a>
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
            <a href="userManagement.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">User Management</a>
            <a href="systemSettings.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">System Settings</a>
            <a href="monitoring.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">System Monitoring</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Reports</a>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Page Header Banner -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center space-x-2">
                    <h1 class="text-2xl font-bold text-brand-dark tracking-tight">User Account Management</h1>
                </div>
                <p class="text-sm text-gray-500">Manage staff and student accounts, assign departments, and maintain account email addresses.</p>
            </div>

            <!-- Action Button -->
            <div>
                <button id="btn_open_add_user" class="inline-flex items-center px-4 py-2 bg-brand-primary hover:bg-brand-dark text-white text-xs font-semibold rounded-md shadow-xs transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add New User
                </button>
            </div>
        </section>

        <!-- Main Content Area: User List -->
        <div class="grid grid-cols-1 gap-8">

            <!-- Left 2 Columns: User Accounts Table & Search/Filters -->
            <div class="space-y-6">

                <div class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">

                    <!-- Search & Filter Controls -->
                    <div class="p-5 border-b border-gray-100 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <h2 class="text-base font-bold text-brand-dark">System Accounts</h2>
                            <span id="txt_total_users" class="text-xs font-medium text-gray-500">Showing <?= count($accountEmailUsers) ?> User Records</span>
                        </div>

                        <!-- Filter Inputs -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <input type="text" id="filter_search" placeholder="Search Username or ID..." class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                            </div>
                            <div>
                                <select id="filter_role" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                                    <option value="">All Roles</option>
                                    <option value="Student">Student</option>
                                    <option value="Applicant">Applicant</option>
                                    <option value="Registrar">Registrar</option>
                                    <option value="Cashier">Cashier</option>
                                    <option value="Department">Department</option>
                                    <option value="Admin">Admin</option>
                                </select>
                            </div>
                            <div>
                                <select id="filter_status" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                                    <option value="">All Statuses</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Clean & Simplified Table Container -->
                    <div class="bg-white border border-gray-200 rounded-lg shadow-xs overflow-hidden">
                        <div class="overflow-x-auto">
                            <table id="table_users" class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-gray-50/80 border-b border-gray-200 text-gray-500 uppercase tracking-wider font-semibold">
                                        <th class="py-3 px-4">User</th>
                                        <th class="py-3 px-4">Email</th>
                                        <th class="py-3 px-4">Role</th>
                                        <th class="py-3 px-4">Department</th>
                                        <th class="py-3 px-4 text-center">Status</th>
                                        <th class="py-3 px-4 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="user_table_body" class="divide-y divide-gray-100 text-gray-700">
                                    <?php foreach ($accountEmailUsers as $user):
                                        $userRole = (string) $user['role'];
                                        $userStatus = (string) ($user['status'] ?? 'Active');
                                        $roleStyle = ['Registrar' => 'text-blue-700 bg-blue-50 border-blue-200/60', 'Department' => 'text-indigo-700 bg-indigo-50 border-indigo-200/60', 'Student' => 'text-gray-600 bg-gray-100 border-gray-200', 'Applicant' => 'text-amber-700 bg-amber-50 border-amber-200', 'Cashier' => 'text-emerald-700 bg-emerald-50 border-emerald-200', 'Admin' => 'text-purple-700 bg-purple-50 border-purple-200'][$userRole] ?? 'text-gray-600 bg-gray-100 border-gray-200';
                                        $departmentId = $user['department_id'] === null ? '' : (string) $user['department_id'];
                                    ?>
                                        <tr class="hover:bg-gray-50/60 transition-colors" data-user-id="<?= (int) $user['id'] ?>" data-role="<?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>" data-status="<?= htmlspecialchars($userStatus, ENT_QUOTES, 'UTF-8') ?>">
                                            <td class="py-3 px-4">
                                                <div class="font-semibold text-gray-900"><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <div class="text-[11px] text-gray-400">ID: <?= (int) $user['id'] ?></div>
                                            </td>
                                            <td class="py-3 px-4 user-email"><?= htmlspecialchars($user['email'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-3 px-4"><span class="text-xs font-medium px-2 py-0.5 rounded border <?= $roleStyle ?>"><?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td class="py-3 px-4 <?= $user['department_name'] ? 'font-medium text-gray-700' : 'text-gray-400' ?>"><?= htmlspecialchars($user['department_name'] ?: '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="py-3 px-4 text-center"><span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $userStatus === 'Active' ? 'text-emerald-700' : 'text-gray-500' ?>"><span class="w-1.5 h-1.5 rounded-full <?= $userStatus === 'Active' ? 'bg-emerald-500' : 'bg-gray-400' ?>"></span><?= htmlspecialchars($userStatus, ENT_QUOTES, 'UTF-8') ?></span></td>
                                            <td class="py-3 px-4 text-right"><button type="button" class="btn-edit-user p-1.5 text-gray-500 hover:text-brand-primary rounded-md hover:bg-gray-100" title="Edit Account" data-id="<?= (int) $user['id'] ?>" data-username="<?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>" data-email="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-role="<?= htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8') ?>" data-department="<?= htmlspecialchars($departmentId, ENT_QUOTES, 'UTF-8') ?>" data-first-name="<?= htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-middle-name="<?= htmlspecialchars($user['middle_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-last-name="<?= htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-contact-number="<?= htmlspecialchars($user['contact_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-address="<?= htmlspecialchars($user['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-program="<?= (int) ($user['program_id'] ?? 0) ?>"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                                    </svg></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

            </div>

        </div>

    </main>

    <!-- Modal Form: Add / Edit User -->
    <div id="modal_user_form" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-5 border-b border-gray-100 flex items-center justify-between">
                <h3 id="modal_title" class="text-base font-bold text-brand-dark">Add New Staff / User Account</h3>
                <button type="button" id="btn_close_modal" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form id="form_user" class="p-5 space-y-4">
                <input type="hidden" id="form_user_id" name="user_id" value="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div>
                    <label for="form_username" class="block text-xs font-bold text-brand-dark mb-1">Username / Account ID <span class="text-red-500">*</span></label>
                    <input type="text" id="form_username" name="username" required placeholder="e.g., registrar_02 or 2026-00002" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                    <p class="text-[10px] text-gray-400 mt-1">Students use Student No; staff use assigned username.</p>
                </div>

                <div id="wrapper_email">
                    <label for="form_email" class="block text-xs font-bold text-brand-dark mb-1">Account Email <span class="text-red-500">*</span></label>
                    <input type="email" id="form_email" name="email" maxlength="120" required placeholder="account@example.com" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                    <p class="text-[10px] text-gray-400 mt-1">This email is used for sign-in and password recovery.</p>
                </div>

                <div>
                    <label for="form_role" class="block text-xs font-bold text-brand-dark mb-1">System Role <span class="text-red-500">*</span></label>
                    <select id="form_role" name="role" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                        <option value="">-- Select Role --</option>
                        <option value="Registrar">Registrar</option>
                        <option value="Cashier">Cashier</option>
                        <option value="Department">Department</option>
                        <option value="Admin">Admin</option>
                        <option value="Student">Student</option>
                        <option value="Applicant">Applicant</option>
                    </select>
                </div>

                <div id="wrapper_applicant_fields" class="hidden space-y-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <p class="text-xs font-bold text-brand-dark">Applicant Details</p>
                    <div class="grid grid-cols-2 gap-3"><label class="block text-xs font-semibold">First Name <span class="text-red-500">*</span><input name="first_name" maxlength="60" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-xs"></label><label class="block text-xs font-semibold">Middle Name<input name="middle_name" maxlength="60" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-xs"></label></div>
                    <label class="block text-xs font-semibold">Last Name <span class="text-red-500">*</span><input name="last_name" maxlength="60" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-xs"></label>
                    <label class="block text-xs font-semibold">Contact Number <span class="text-red-500">*</span><input name="contact_number" maxlength="20" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-xs"></label>
                    <label class="block text-xs font-semibold">Address <span class="text-red-500">*</span><input name="address" maxlength="255" class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-xs"></label>
                    <label class="block text-xs font-semibold">Program <span class="text-red-500">*</span><select name="program_id" class="mt-1 w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-xs">
                            <option value="">-- Select Program --</option><?php foreach ($accountEmailPrograms as $program): ?><option value="<?= (int) $program['id'] ?>"><?= htmlspecialchars($program['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select></label>
                    <p class="text-[10px] text-gray-500">Saving this account also creates a Pending application in the admissions queue.</p>
                </div>

                <!-- Department Selector (Conditional: required only for Department role) -->
                <div id="wrapper_department" class="hidden">
                    <label for="form_department_id" class="block text-xs font-bold text-brand-dark mb-1">Assigned Department <span class="text-red-500">*</span></label>
                    <select id="form_department_id" name="department_id" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                        <option value="">-- Select Department --</option>
                        <?php foreach ($accountEmailDepartments as $department): ?>
                            <option value="<?= (int) $department['id'] ?>"><?= htmlspecialchars($department['name'] . ' (' . $department['code'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[10px] text-gray-400 mt-1">Department staff can only view students belonging to their department's programs.</p>
                </div>

                <!-- Password Input (Only active for creation) -->
                <div id="wrapper_password">
                    <label for="form_password" class="block text-xs font-bold text-brand-dark mb-1">Initial Password <span class="text-red-500">*</span></label>
                    <input type="password" id="form_password" name="password" placeholder="At least 8 chars with letters & numbers" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                    <p class="text-[10px] text-gray-400 mt-1">Must be at least 8 characters long with letters and numbers.</p>
                </div>

                <div class="pt-3 border-t border-gray-100 flex items-center justify-end space-x-2">
                    <button type="button" id="btn_cancel_modal" class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
                    <button type="submit" id="btn_save_user" class="px-4 py-2 bg-brand-primary hover:bg-brand-dark text-white text-xs font-semibold rounded-md shadow-xs transition-colors">Save Account</button>
                </div>
            </form>
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
        <?php if (is_array($accountEmailNotice)): ?>showToast(<?= json_encode($accountEmailNotice['type'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, <?= json_encode($accountEmailNotice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        <?php endif; ?>
        $(document).ready(function() {

            // Mobile navigation toggle
            $('#mobile_menu_btn').on('click', function() {
                $('#mobile_menu').toggleClass('hidden');
            });

            // Toggle department selection based on role
            $('#form_role').on('change', function() {
                const role = $(this).val();
                if (role === 'Department') {
                    $('#wrapper_department').removeClass('hidden');
                    $('#form_department_id').prop('required', true);
                } else {
                    $('#wrapper_department').addClass('hidden');
                    $('#form_department_id').prop('required', false).val('');
                }
                const applicant = role === 'Applicant';
                $('#wrapper_applicant_fields').toggleClass('hidden', !applicant);
                $('#wrapper_applicant_fields [name="first_name"], #wrapper_applicant_fields [name="last_name"], #wrapper_applicant_fields [name="contact_number"], #wrapper_applicant_fields [name="address"], #wrapper_applicant_fields [name="program_id"]').prop('required', applicant);
            });

            // Search & Filter Handler
            function filterUsers() {
                const searchVal = $('#filter_search').val().toLowerCase().trim();
                const roleVal = $('#filter_role').val();
                const statusVal = $('#filter_status').val();

                let visibleCount = 0;

                $('#user_table_body tr').each(function() {
                    const row = $(this);
                    const text = row.text().toLowerCase();
                    const role = row.attr('data-role');
                    const status = row.attr('data-status');

                    const matchesSearch = !searchVal || text.includes(searchVal);
                    const matchesRole = !roleVal || role === roleVal;
                    const matchesStatus = !statusVal || status === statusVal;

                    if (matchesSearch && matchesRole && matchesStatus) {
                        row.removeClass('hidden');
                        visibleCount++;
                    } else {
                        row.addClass('hidden');
                    }
                });

                $('#txt_total_users').text(`Showing ${visibleCount} User Records`);
            }

            $('#filter_search').on('input', filterUsers);
            $('#filter_role, #filter_status').on('change', filterUsers);

            // Open Add User Modal
            $('#btn_open_add_user').on('click', function() {
                $('#modal_title').text('Add New Staff / User Account');
                $('#form_user_id').val('');
                $('#form_username').val('').prop('readonly', false);
                $('#form_role').val('').trigger('change');
                $('#wrapper_email').removeClass('hidden');
                $('#form_email').prop('required', true).val('');
                $('#wrapper_applicant_fields').find('input, select').prop('disabled', false);
                $('#wrapper_department').addClass('hidden');
                $('#wrapper_applicant_fields').addClass('hidden').find('input, select').val('').prop('required', false);
                $('#wrapper_applicant_fields').find('input, select').prop('disabled', false);
                $('#wrapper_password').removeClass('hidden');
                $('#form_password').prop('required', true).val('');
                $('#modal_user_form').removeClass('hidden');
            });

            // Open Edit User Modal
            $(document).on('click', '.btn-edit-user', function() {
                const button = $(this);
                const id = button.attr('data-id') || '';
                const username = button.attr('data-username') || '(unknown account)';
                const email = button.attr('data-email') || '';
                const role = button.attr('data-role') || '';
                const dept = button.attr('data-department') || '';

                $('#modal_title').text('Edit User Account: ' + username);
                $('#form_user_id').val(id);
                $('#form_username').val(username).prop('readonly', true);
                $('#form_role').val(role).trigger('change');
                if (role !== 'Applicant') $('#wrapper_applicant_fields').find('input, select').val('');
                $('#wrapper_email').removeClass('hidden');
                $('#form_email').val(email).prop('required', true);
                $('#wrapper_applicant_fields [name="first_name"]').val(button.attr('data-first-name') || '');
                $('#wrapper_applicant_fields [name="middle_name"]').val(button.attr('data-middle-name') || '');
                $('#wrapper_applicant_fields [name="last_name"]').val(button.attr('data-last-name') || '');
                $('#wrapper_applicant_fields [name="contact_number"]').val(button.attr('data-contact-number') || '');
                $('#wrapper_applicant_fields [name="address"]').val(button.attr('data-address') || '');
                $('#wrapper_applicant_fields [name="program_id"]').val(button.attr('data-program') || '');
                $('#wrapper_applicant_fields').find('input, select').prop('disabled', role === 'Applicant');

                if (role === 'Department') {
                    $('#form_department_id').val(dept);
                }

                $('#wrapper_password').addClass('hidden');
                $('#form_password').prop('required', false).val('');
                $('#modal_user_form').removeClass('hidden');
            });

            // Close Modals
            $('#btn_close_modal, #btn_cancel_modal').on('click', function() {
                $('#modal_user_form').addClass('hidden');
            });

            // Handle Form Submission (Save User)
            $('#form_user').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                const button = $('#btn_save_user').prop('disabled', true).text('Saving...');
                $.ajax({
                        url: '../../controller/adminUserController.php',
                        method: 'POST',
                        data: $(form).serialize(),
                        dataType: 'json'
                    })
                    .done(function(response) {
                        if (!response || response.status !== 'success' || !response.data) {
                            showToast('error', response?.message || 'The account could not be saved.');
                            return;
                        }
                        const user = response.data;
                        let row = document.querySelector('#user_table_body tr[data-user-id="' + Number(user.id) + '"]');
                        const isNew = !row;
                        if (!row) row = document.createElement('tr');
                        row.className = 'hover:bg-gray-50/60 transition-colors';
                        row.dataset.userId = user.id;
                        row.dataset.role = user.role;
                        row.dataset.status = user.status;
                        row.replaceChildren();
                        const userCell = document.createElement('td');
                        userCell.className = 'py-3 px-4';
                        const username = document.createElement('div');
                        username.className = 'font-semibold text-gray-900';
                        username.textContent = user.username;
                        const id = document.createElement('div');
                        id.className = 'text-[11px] text-gray-400';
                        id.textContent = 'ID: ' + user.id;
                        userCell.append(username, id);
                        const email = document.createElement('td');
                        email.className = 'py-3 px-4 user-email';
                        email.textContent = user.email || '—';
                        const roleCell = document.createElement('td');
                        roleCell.className = 'py-3 px-4';
                        const roleBadge = document.createElement('span');
                        roleBadge.className = 'text-xs font-medium px-2 py-0.5 rounded border text-gray-600 bg-gray-100 border-gray-200';
                        roleBadge.textContent = user.role;
                        roleCell.append(roleBadge);
                        const department = document.createElement('td');
                        department.className = 'py-3 px-4 ' + (user.department_name ? 'font-medium text-gray-700' : 'text-gray-400');
                        department.textContent = user.department_name || '—';
                        const status = document.createElement('td');
                        status.className = 'py-3 px-4 text-center';
                        const statusLabel = document.createElement('span');
                        statusLabel.className = 'inline-flex items-center gap-1.5 text-xs font-medium ' + (user.status === 'Active' ? 'text-emerald-700' : 'text-gray-500');
                        const dot = document.createElement('span');
                        dot.className = 'w-1.5 h-1.5 rounded-full ' + (user.status === 'Active' ? 'bg-emerald-500' : 'bg-gray-400');
                        statusLabel.append(dot, document.createTextNode(user.status));
                        status.append(statusLabel);
                        const actions = document.createElement('td');
                        actions.className = 'py-3 px-4 text-right';
                        const edit = document.createElement('button');
                        edit.type = 'button';
                        edit.className = 'btn-edit-user p-1.5 text-gray-500 hover:text-brand-primary rounded-md hover:bg-gray-100';
                        edit.title = 'Edit Account';
                        edit.dataset.id = user.id;
                        edit.dataset.username = user.username;
                        edit.dataset.email = user.email || '';
                        edit.dataset.role = user.role;
                        edit.dataset.department = user.department_id || '';
                        edit.dataset.firstName = user.first_name || '';
                        edit.dataset.middleName = user.middle_name || '';
                        edit.dataset.lastName = user.last_name || '';
                        edit.dataset.contactNumber = user.contact_number || '';
                        edit.dataset.address = user.address || '';
                        edit.dataset.program = user.program_id || '';
                        edit.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 1 1 3.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>';
                        actions.append(edit);
                        row.append(userCell, email, roleCell, department, status, actions);
                        if (isNew) document.getElementById('user_table_body').prepend(row);
                        if (Number.isInteger(Number(response.new_applicant_count)) && typeof window.updateAdmissionsBadge === 'function') {
                            window.updateAdmissionsBadge(response.new_applicant_count);
                        }
                        $('#modal_user_form').addClass('hidden');
                        form.reset();
                        filterUsers();
                        showToast('success', response.message || 'Account saved.');
                    })
                    .fail(function(xhr) {
                        showToast('error', xhr.responseJSON?.message || 'The account could not be saved. Please try again.');
                    })
                    .always(function() {
                        button.text('Save Account').prop('disabled', false);
                    });
            });

            // Toggle Account Status (Enable/Disable Rule: Admin cannot disable self)
            $(document).on('click', '.btn-toggle-status', function() {
                const userId = $(this).attr('data-id');
                const username = $(this).attr('data-username');
                const action = $(this).attr('data-action');

                if (userId === '1') {
                    showToast('error', 'Security Restriction: Administrators are strictly prohibited from disabling their own logged-in account.');
                    return;
                }

                if (confirm(`Are you sure you want to ${action.toLowerCase()} account "${username}"?`)) {
                    showToast('success', `Account "${username}" has been successfully set to ${action === 'Disable' ? 'Inactive' : 'Active'}.`);
                }
            });

        });
    </script>
    <script src="../../js/private-page-history.js"></script>
    <script>
        (function() {
            const pageSize = 10;
            const tables = Array.from(document.querySelectorAll('table'));

            function visibleRows(table) {
                return Array.from(table.tBodies[0]?.rows || []).filter(row => !row.classList.contains('table-empty-state') && !row.querySelector('td[colspan]') && row.style.display !== 'none' && !row.classList.contains('hidden'));
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
                } else allRows.filter(row => row.classList.contains('table-empty-state') || row.querySelector('td[colspan]')).forEach(row => row.remove());
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
