<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('admin');
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$currentTermWindow = Database::getConnection()->query('SELECT id, academic_year, semester, enrollment_open_at, enrollment_close_at FROM terms WHERE is_current = 1 LIMIT 1')->fetch() ?: null;
$settingsNotice = $_SESSION['settings_notice'] ?? null;
unset($_SESSION['settings_notice']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <title>Paxton University - System Management</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- jQuery -->
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

<body class="bg-gray-50 font-sans text-brand-dark antialiased min-h-screen flex flex-col relative">
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
                    <a href="systemSettings.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20 transition-colors">System Settings</a>
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
            <a href="userManagement.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">User Management</a>
            <a href="systemSettings.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">System Settings</a>
            <a href="monitoring.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">System Monitoring</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Reports</a>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">


        <!-- Page Header Banner -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">System Configuration Management</h1>
                <p class="text-sm text-gray-500">Configure global parameters, academic structures, document requests, and clearance requirements.</p>
            </div>
        </section>

        <!-- Section 1: Academic Structure Management -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs p-6 space-y-6">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-lg font-bold text-brand-dark">1. Academic Structure Management</h2>
                <p class="text-xs text-gray-500">Manage institutional departments, degree programs, and course subjects.</p>
            </div>

            <!-- Tab Switching Controls -->
            <div class="border-b border-gray-200">
                <nav class="flex space-x-6 text-xs font-semibold" aria-label="Academic Subsections">
                    <button type="button" class="tab-btn py-2 border-b-2 border-brand-primary text-brand-primary" data-target="#tab_departments">Departments</button>
                    <button type="button" class="tab-btn py-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-target="#tab_programs">Programs</button>
                    <button type="button" class="tab-btn py-2 border-b-2 border-transparent text-gray-500 hover:text-gray-700" data-target="#tab_subjects">Subjects</button>
                </nav>
            </div>

            <!-- Tab Content: Departments -->
            <div id="tab_departments" class="tab-content space-y-4">
                <div class="flex items-start gap-2 justify-between flex-col md:flex-row">
                    <h3 class="text-sm font-bold text-gray-800">Departments List</h3>
                    <button type="button" class="w-full md:w-fit btn-open-modal px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded hover:bg-brand-dark transition-colors" data-modal="modal_add_department">+ Add Department</button>
                </div>
                <div class="overflow-x-auto border border-gray-200 rounded">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-4">Code</th>
                                <th class="py-2.5 px-4">Department Name</th>
                                <th class="py-2.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr>
                                <td class="py-2.5 px-4 font-semibold row-code">DCS</td>
                                <td class="py-2.5 px-4 row-name">Department of Computer Studies</td>
                                <td class="py-2.5 px-4 text-right space-x-2">
                                    <button class="btn-edit-dept text-brand-primary hover:underline font-semibold text-xs">Edit</button>
                                    <button class="btn-disable-item text-red-600 hover:underline font-semibold text-xs" data-item="Department of Computer Studies">Disable</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-4 font-semibold row-code">DENG</td>
                                <td class="py-2.5 px-4 row-name">Department of Engineering</td>
                                <td class="py-2.5 px-4 text-right space-x-2">
                                    <button class="btn-edit-dept text-brand-primary hover:underline font-semibold text-xs">Edit</button>
                                    <button class="btn-disable-item text-red-600 hover:underline font-semibold text-xs" data-item="Department of Engineering">Disable</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab Content: Programs -->
            <div id="tab_programs" class="tab-content hidden space-y-4">
                <div class="flex items-start gap-2 justify-between flex-col md:flex-row ">
                    <h3 class="text-sm font-bold text-gray-800">Degree Programs</h3>
                    <button type="button" class="w-full md:w-fit btn-open-modal px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded hover:bg-brand-dark transition-colors" data-modal="modal_add_program">+ Add Program</button>
                </div>
                <div class="overflow-x-auto border border-gray-200 rounded">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-4">Program Code</th>
                                <th class="py-2.5 px-4">Program Title</th>
                                <th class="py-2.5 px-4">Department</th>
                                <th class="py-2.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr>
                                <td class="py-2.5 px-4 font-semibold row-code">BSCS</td>
                                <td class="py-2.5 px-4 row-name">BS Computer Science</td>
                                <td class="py-2.5 px-4 row-dept" data-dept-id="1">Department of Computer Studies</td>
                                <td class="py-2.5 px-4 text-right space-x-2">
                                    <button class="btn-edit-program text-brand-primary hover:underline font-semibold text-xs">Edit</button>
                                    <button class="btn-disable-item text-red-600 hover:underline font-semibold text-xs" data-item="BS Computer Science">Disable</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-4 font-semibold row-code">BSIT</td>
                                <td class="py-2.5 px-4 row-name">BS Information Technology</td>
                                <td class="py-2.5 px-4 row-dept" data-dept-id="1">Department of Computer Studies</td>
                                <td class="py-2.5 px-4 text-right space-x-2">
                                    <button class="btn-edit-program text-brand-primary hover:underline font-semibold text-xs">Edit</button>
                                    <button class="btn-disable-item text-red-600 hover:underline font-semibold text-xs" data-item="BS Information Technology">Disable</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tab Content: Subjects -->
            <div id="tab_subjects" class="tab-content hidden space-y-4">
                <div class="flex items-start gap-2 justify-between flex-col md:flex-row">
                    <h3 class="text-sm font-bold text-gray-800">Course Subjects</h3>
                    <button type="button" class="w-full md:w-fit btn-open-modal px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded hover:bg-brand-dark transition-colors" data-modal="modal_add_subject">+ Add Subject</button>
                </div>
                <div class="overflow-x-auto border border-gray-200 rounded">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-200">
                            <tr>
                                <th class="py-2.5 px-4">Subject Code</th>
                                <th class="py-2.5 px-4">Descriptive Title</th>
                                <th class="py-2.5 px-4 text-center">Units</th>
                                <th class="py-2.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            <tr>
                                <td class="py-2.5 px-4 font-semibold row-code">CS101</td>
                                <td class="py-2.5 px-4 row-title">Introduction to Computing</td>
                                <td class="py-2.5 px-4 text-center row-units">3</td>
                                <td class="py-2.5 px-4 text-right space-x-2">
                                    <button class="btn-edit-subject text-brand-primary hover:underline font-semibold text-xs">Edit</button>
                                    <button class="btn-disable-item text-red-600 hover:underline font-semibold text-xs" data-item="CS101 - Introduction to Computing">Disable</button>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2.5 px-4 font-semibold row-code">CS102</td>
                                <td class="py-2.5 px-4 row-title">Computer Programming 1</td>
                                <td class="py-2.5 px-4 text-center row-units">3</td>
                                <td class="py-2.5 px-4 text-right space-x-2">
                                    <button class="btn-edit-subject text-brand-primary hover:underline font-semibold text-xs">Edit</button>
                                    <button class="btn-disable-item text-red-600 hover:underline font-semibold text-xs" data-item="CS102 - Computer Programming 1">Disable</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </section>

        <!-- Section 2: Document Types Management -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs p-6 space-y-6">
            <div class="flex items-start gap-2 justify-between flex-col md:flex-row">
                <div>
                    <h2 class="text-lg font-bold text-brand-dark">2. Document Request Types</h2>
                    <p class="text-xs text-gray-500">Manage available registrars or official documents for request, associated processing fees, and active availability.</p>
                </div>
                <button type="button" class="w-full md:w-fit btn-open-modal px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded hover:bg-brand-dark transition-colors" data-modal="modal_add_document">+ Add Document Type</button>
            </div>

            <div class="overflow-x-auto border border-gray-200 rounded">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-4">Document Name</th>
                            <th class="py-2.5 px-4">Processing Fee (PHP)</th>
                            <th class="py-2.5 px-4 text-center">Status</th>
                            <th class="py-2.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <tr>
                            <td class="py-2.5 px-4 font-semibold row-doc-name">Official Transcript of Records (TOR)</td>
                            <td class="py-2.5 px-4 font-display row-doc-fee" data-raw-fee="150.00">₱150.00</td>
                            <td class="py-2.5 px-4 text-center row-doc-status" data-status="1">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-right space-x-2">
                                <button class="btn-edit-document text-brand-primary hover:underline font-semibold">Edit</button>
                                <button class="btn-disable-item text-red-600 hover:underline font-semibold" data-item="Official Transcript of Records (TOR)">Disable</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-4 font-semibold row-doc-name">Certificate of Grades (COG)</td>
                            <td class="py-2.5 px-4 font-display row-doc-fee" data-raw-fee="50.00">₱50.00</td>
                            <td class="py-2.5 px-4 text-center row-doc-status" data-status="1">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-right space-x-2">
                                <button class="btn-edit-document text-brand-primary hover:underline font-semibold">Edit</button>
                                <button class="btn-disable-item text-red-600 hover:underline font-semibold" data-item="Certificate of Grades (COG)">Disable</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 3: Clearance Requirements Management -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs p-6 space-y-6">
            <div class="flex items-start gap-2 justify-between flex-col md:flex-row">
                <div>
                    <h2 class="text-lg font-bold text-brand-dark">3. Clearance Requirements</h2>
                    <p class="text-xs text-gray-500">Define student clearance check items and assign reviewing administrative offices.</p>
                </div>
                <button type="button" class="w-full md:w-fit btn-open-modal px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded hover:bg-brand-dark transition-colors" data-modal="modal_add_clearance">+ Add Requirement</button>
            </div>

            <div class="overflow-x-auto border border-gray-200 rounded">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-500 font-semibold border-b border-gray-200">
                        <tr>
                            <th class="py-2.5 px-4">Requirement Name</th>
                            <th class="py-2.5 px-4">Reviewing Office / Role</th>
                            <th class="py-2.5 px-4 text-center">Status</th>
                            <th class="py-2.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <tr>
                            <td class="py-2.5 px-4 font-semibold row-clr-name">Library Books & Dues Settlement</td>
                            <td class="py-2.5 px-4 row-clr-office">Library</td>
                            <td class="py-2.5 px-4 text-center row-clr-status" data-status="1">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-right space-x-2">
                                <button class="btn-edit-clearance text-brand-primary hover:underline font-semibold">Edit</button>
                                <button class="btn-disable-item text-red-600 hover:underline font-semibold" data-item="Library Books & Dues Settlement">Disable</button>
                            </td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-4 font-semibold row-clr-name">Tuition Balance Clearance</td>
                            <td class="py-2.5 px-4 row-clr-office">Cashier</td>
                            <td class="py-2.5 px-4 text-center row-clr-status" data-status="1">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-right space-x-2">
                                <button class="btn-edit-clearance text-brand-primary hover:underline font-semibold">Edit</button>
                                <button class="btn-disable-item text-red-600 hover:underline font-semibold" data-item="Tuition Balance Clearance">Disable</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 4: System Global Settings -->
        <section class="bg-white border border-gray-200 rounded-md shadow-xs p-6 space-y-6">
            <div class="border-b border-gray-100 pb-3">
                <h2 class="text-lg font-bold text-brand-dark">4. System Global Settings</h2>
                <p class="text-xs text-gray-500">Configure core institution branding, academic constraints, and access security limits.</p>
            </div>

            <form id="form_system_settings" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- School Name -->
                    <div class="md:col-span-2">
                        <label for="setting_school_name" class="block text-xs font-bold text-gray-700 mb-1">School / Institution Name <span class="text-red-500">*</span></label>
                        <input type="text" id="setting_school_name" name="school_name" value="Paxton University" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                    </div>

                    <!-- Max Units Allowed -->
                    <div>
                        <label for="setting_max_units" class="block text-xs font-bold text-gray-700 mb-1">Maximum Units Allowed per Enrollment <span class="text-red-500">*</span></label>
                        <input type="number" id="setting_max_units" name="max_units_per_enrollment" value="26" min="1" max="40" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                        <p class="text-[10px] text-gray-400 mt-1">Prevents over-enrollment beyond maximum credit threshold.</p>
                    </div>

                    <!-- Login Attempt Limit -->
                    <div>
                        <label for="setting_login_attempts" class="block text-xs font-bold text-gray-700 mb-1">Failed Login Attempt Limit <span class="text-red-500">*</span></label>
                        <input type="number" id="setting_login_attempts" name="login_attempt_limit" value="5" min="1" max="10" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                        <p class="text-[10px] text-gray-400 mt-1">Number of failed entries before triggering user lockout.</p>
                    </div>

                    <!-- Lockout Duration -->
                    <div>
                        <label for="setting_lockout_time" class="block text-xs font-bold text-gray-700 mb-1">Account Lockout Time (Minutes) <span class="text-red-500">*</span></label>
                        <input type="number" id="setting_lockout_time" name="lockout_time_minutes" value="15" min="1" max="1440" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                        <p class="text-[10px] text-gray-400 mt-1">Duration locked out users must wait before re-attempting login.</p>
                    </div>

                    <!-- Session Timeout -->
                    <div>
                        <label for="setting_session_timeout" class="block text-xs font-bold text-gray-700 mb-1">Inactivity Session Timeout (Minutes) <span class="text-red-500">*</span></label>
                        <input type="number" id="setting_session_timeout" name="session_timeout_minutes" value="30" min="5" max="480" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                        <p class="text-[10px] text-gray-400 mt-1">Automatic session termination following inactivity.</p>
                    </div>

                </div>

                <div class="pt-4 border-t border-gray-100 flex justify-end">
                    <button type="submit" id="btn_save_settings" class="px-5 py-2 bg-brand-primary hover:bg-brand-dark text-white text-xs font-semibold rounded-md shadow-xs transition-colors">
                        Save System Settings
                    </button>
                </div>
            </form>
            <form id="form_enrollment_window" action="../../controller/enrollmentWindowController.php" method="post" class="mt-6 border-t pt-5 space-y-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <h3 class="font-bold text-sm">Enrollment period<?= $currentTermWindow ? ' · ' . htmlspecialchars($currentTermWindow['academic_year'] . ' ' . $currentTermWindow['semester'], ENT_QUOTES, 'UTF-8') : '' ?></h3>
                <?php if ($currentTermWindow): ?>
                    <div class="grid sm:grid-cols-2 gap-3"><label class="block text-xs font-semibold">Opens<input name="enrollment_open_at" type="datetime-local" required value="<?= htmlspecialchars($currentTermWindow['enrollment_open_at'] ? date('Y-m-d\\TH:i', strtotime($currentTermWindow['enrollment_open_at'])) : '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm"></label><label class="block text-xs font-semibold">Closes<input name="enrollment_close_at" type="datetime-local" required value="<?= htmlspecialchars($currentTermWindow['enrollment_close_at'] ? date('Y-m-d\\TH:i', strtotime($currentTermWindow['enrollment_close_at'])) : '', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm"></label></div>
                    <button class="px-5 py-2 bg-brand-primary text-white text-xs font-semibold rounded-md">Save enrollment period</button>
                <?php else: ?><p class="text-xs text-amber-700">Set an active academic term before configuring enrollment dates.</p><?php endif; ?>
            </form>
        </section>

    </main>

    <!-- Modal: Add/Edit Department -->
    <div id="modal_add_department" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="modal_dept_title" class="text-sm font-bold text-brand-dark">Add New Department</h3>
                <button type="button" class="btn-close-modal text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="form_add_department" class="p-4 space-y-4">
                <div>
                    <label for="dept_code" class="block text-xs font-bold text-gray-700 mb-1">Department Code</label>
                    <input type="text" id="dept_code" name="department_code" required placeholder="e.g., DCS" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div>
                    <label for="dept_name" class="block text-xs font-bold text-gray-700 mb-1">Department Name</label>
                    <input type="text" id="dept_name" name="department_name" required placeholder="e.g., Department of Computer Studies" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div class="pt-2 flex justify-end space-x-2">
                    <button type="button" class="btn-close-modal px-3 py-1.5 border border-gray-300 rounded text-xs text-gray-600">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded">Save Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add/Edit Program -->
    <div id="modal_add_program" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="modal_prog_title" class="text-sm font-bold text-brand-dark">Add New Program</h3>
                <button type="button" class="btn-close-modal text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="form_add_program" class="p-4 space-y-4">
                <div>
                    <label for="prog_code" class="block text-xs font-bold text-gray-700 mb-1">Program Code</label>
                    <input type="text" id="prog_code" name="program_code" required placeholder="e.g., BSCS" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div>
                    <label for="prog_name" class="block text-xs font-bold text-gray-700 mb-1">Program Name / Title</label>
                    <input type="text" id="prog_name" name="program_name" required placeholder="e.g., Bachelor of Science in Computer Science" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div>
                    <label for="prog_dept_id" class="block text-xs font-bold text-gray-700 mb-1">Assigned Department</label>
                    <select id="prog_dept_id" name="department_id" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                        <option value="">-- Select Department --</option>
                        <option value="1">Department of Computer Studies</option>
                        <option value="2">Department of Engineering</option>
                    </select>
                </div>
                <div class="pt-2 flex justify-end space-x-2">
                    <button type="button" class="btn-close-modal px-3 py-1.5 border border-gray-300 rounded text-xs text-gray-600">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded">Save Program</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add/Edit Subject -->
    <div id="modal_add_subject" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="modal_sub_title" class="text-sm font-bold text-brand-dark">Add New Subject</h3>
                <button type="button" class="btn-close-modal text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="form_add_subject" class="p-4 space-y-4">
                <div>
                    <label for="sub_code" class="block text-xs font-bold text-gray-700 mb-1">Subject Code</label>
                    <input type="text" id="sub_code" name="subject_code" required placeholder="e.g., CS101" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div>
                    <label for="sub_title" class="block text-xs font-bold text-gray-700 mb-1">Descriptive Title</label>
                    <input type="text" id="sub_title" name="subject_title" required placeholder="e.g., Introduction to Computing" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div>
                    <label for="sub_units" class="block text-xs font-bold text-gray-700 mb-1">Unit Value</label>
                    <input type="number" id="sub_units" name="units" required min="1" max="6" value="3" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div class="grid grid-cols-3 gap-2"><label class="text-xs font-bold">Program<select name="program_id" id="sub_program_id" required class="mt-1 w-full border rounded p-2 text-xs"></select></label><label class="text-xs font-bold">Year<select name="year_level" required class="mt-1 w-full border rounded p-2 text-xs">
                            <option>1</option>
                            <option>2</option>
                            <option>3</option>
                            <option>4</option>
                            <option>5</option>
                            <option>6</option>
                        </select></label><label class="text-xs font-bold">Semester<select name="semester" required class="mt-1 w-full border rounded p-2 text-xs">
                            <option>1st Semester</option>
                            <option>2nd Semester</option>
                            <option>Summer</option>
                        </select></label></div>
                <div class="pt-2 flex justify-end space-x-2">
                    <button type="button" class="btn-close-modal px-3 py-1.5 border border-gray-300 rounded text-xs text-gray-600">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded">Save Subject</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add/Edit Term -->
    <div id="modal_add_term" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="modal_term_title" class="text-sm font-bold text-brand-dark">Add Academic Term</h3>
                <button type="button" class="btn-close-modal text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="form_add_term" class="p-4 space-y-4">
                <div>
                    <label for="term_name" class="block text-xs font-bold text-gray-700 mb-1">Academic Year & Semester</label>
                    <input type="text" id="term_name" name="term_name" required placeholder="e.g., A.Y. 2026-2027, 1st Semester" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div class="flex items-center space-x-2">
                    <input type="checkbox" id="term_is_current" name="is_current" value="1" class="rounded border-gray-300 text-brand-primary focus:ring-brand-primary">
                    <label for="term_is_current" class="text-xs font-semibold text-gray-700">Set as Current Active Term</label>
                </div>
                <div class="pt-2 flex justify-end space-x-2">
                    <button type="button" class="btn-close-modal px-3 py-1.5 border border-gray-300 rounded text-xs text-gray-600">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded">Save Term</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add/Edit Document Type -->
    <div id="modal_add_document" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="modal_doc_title" class="text-sm font-bold text-brand-dark">Add Document Type</h3>
                <button type="button" class="btn-close-modal text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="form_add_document" class="p-4 space-y-4">
                <div>
                    <label for="doc_name" class="block text-xs font-bold text-gray-700 mb-1">Document Name</label>
                    <input type="text" id="doc_name" name="document_name" required placeholder="e.g., Diploma Certificate" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div>
                    <label for="doc_fee" class="block text-xs font-bold text-gray-700 mb-1">Processing Fee (PHP)</label>
                    <input type="number" step="0.01" id="doc_fee" name="fee" required placeholder="0.00" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div>
                    <label for="doc_status" class="block text-xs font-bold text-gray-700 mb-1">Status</label>
                    <select id="doc_status" name="is_active" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="pt-2 flex justify-end space-x-2">
                    <button type="button" class="btn-close-modal px-3 py-1.5 border border-gray-300 rounded text-xs text-gray-600">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded">Save Document Type</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add/Edit Clearance Requirement -->
    <div id="modal_add_clearance" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="modal_clr_title" class="text-sm font-bold text-brand-dark">Add Clearance Requirement</h3>
                <button type="button" class="btn-close-modal text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="form_add_clearance" class="p-4 space-y-4">
                <div>
                    <label for="clearance_name" class="block text-xs font-bold text-gray-700 mb-1">Requirement Name</label>
                    <input type="text" id="clearance_name" name="requirement_name" required placeholder="e.g., Laboratory Equipment Check" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary">
                </div>
                <div>
                    <label for="clearance_office" class="block text-xs font-bold text-gray-700 mb-1">Reviewing Office / Authority</label>
                    <select id="clearance_office" name="reviewing_office" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                        <option value="">-- Select Reviewing Office --</option>
                        <option value="Cashier">Cashier / Finance</option>
                        <option value="Department">Department Head</option>
                        <option value="Registrar">Registrar Office</option>
                    </select>
                </div>
                <div id="clearance_department_wrapper" class="hidden"><label for="clearance_department_id" class="block text-xs font-bold text-gray-700 mb-1">Responsible Department</label><select id="clearance_department_id" name="department_id" class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md bg-white"></select></div>
                <div>
                    <label for="clearance_status" class="block text-xs font-bold text-gray-700 mb-1">Status</label>
                    <select id="clearance_status" name="is_active" required class="w-full text-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-brand-primary bg-white">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="pt-2 flex justify-end space-x-2">
                    <button type="button" class="btn-close-modal px-3 py-1.5 border border-gray-300 rounded text-xs text-gray-600">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 bg-brand-primary text-white text-xs font-semibold rounded">Save Requirement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Confirmation / Disable Item Modal -->
    <div id="modal_confirm_disable" class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl max-w-sm w-full overflow-hidden p-5 space-y-4">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Update Item Status</h3>
                    <p class="text-xs text-gray-500 mt-0.5"><span data-toggle-prompt>Disable this record?</span> <span id="disable_item_name" class="font-semibold text-gray-800">this record</span></p>
                </div>
            </div>
            <div class="flex justify-end space-x-2 pt-2 border-t border-gray-100">
                <button type="button" class="btn-close-modal px-3 py-1.5 border border-gray-300 rounded text-xs text-gray-600 hover:bg-gray-50">Cancel</button>
                <button type="button" id="btn_confirm_disable_action" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-semibold rounded">Yes, Disable</button>
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

    <?php if ($settingsNotice): ?><script>
            showToast(<?= json_encode(is_array($settingsNotice) ? ($settingsNotice['type'] ?? 'info') : 'info') ?>, <?= json_encode(is_array($settingsNotice) ? ($settingsNotice['message'] ?? '') : $settingsNotice, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
        </script><?php endif; ?>
    <script>
        $(document).ready(function() {



            // Mobile Navigation Toggle
            $('#mobile_menu_btn').on('click', function() {
                $('#mobile_menu').toggleClass('hidden');
            });

            // Academic Structure Tabs
            $('.tab-btn').on('click', function() {
                $('.tab-btn').removeClass('border-brand-primary text-brand-primary').addClass('border-transparent text-gray-500');
                $(this).addClass('border-brand-primary text-brand-primary').removeClass('border-transparent text-gray-500');

                const target = $(this).attr('data-target');
                $('.tab-content').addClass('hidden');
                $(target).removeClass('hidden');
            });

            // Open Create Modals (Reset Form)
            $('.btn-open-modal').on('click', function() {
                const modalId = $(this).attr('data-modal');
                const modal = $('#' + modalId);

                modal.find('form')[0].reset();
                modal.find('h3').text(modal.find('h3').text().replace('Edit', 'Add New'));
                modal.removeClass('hidden');
            });

            // Close Modals
            $('.btn-close-modal').on('click', function() {
                $(this).closest('.fixed').addClass('hidden');
            });

            // Edit Department
            $(document).on('click', '.btn-edit-dept', function() {
                const tr = $(this).closest('tr');
                $('#dept_code').val(tr.find('.row-code').text().trim());
                $('#dept_name').val(tr.find('.row-name').text().trim());
                $('#modal_dept_title').text('Edit Department');
                $('#modal_add_department').removeClass('hidden');
            });

            // Edit Program
            $(document).on('click', '.btn-edit-program', function() {
                const tr = $(this).closest('tr');
                $('#prog_code').val(tr.find('.row-code').text().trim());
                $('#prog_name').val(tr.find('.row-name').text().trim());
                const deptId = tr.find('.row-dept').attr('data-dept-id') || "1";
                $('#prog_dept_id').val(deptId);
                $('#modal_prog_title').text('Edit Program');
                $('#modal_add_program').removeClass('hidden');
            });

            // Edit Subject
            $(document).on('click', '.btn-edit-subject', function() {
                const tr = $(this).closest('tr');
                $('#sub_code').val(tr.find('.row-code').text().trim());
                $('#sub_title').val(tr.find('.row-title').text().trim());
                $('#sub_units').val(tr.find('.row-units').text().trim());
                $('#modal_sub_title').text('Edit Subject');
                $('#modal_add_subject').removeClass('hidden');
            });

            // Edit Academic Term
            $(document).on('click', '.btn-edit-term', function() {
                const tr = $(this).closest('tr');
                $('#term_code').val(tr.find('.row-code').text().trim());
                $('#term_name').val(tr.find('.row-name').text().trim());
                const isCurrent = tr.find('.row-status').attr('data-current') === 'true';
                $('#term_is_current').prop('checked', isCurrent);
                $('#modal_term_title').text('Edit Academic Term');
                $('#modal_add_term').removeClass('hidden');
            });

            // Edit Document Type
            $(document).on('click', '.btn-edit-document', function() {
                const tr = $(this).closest('tr');
                $('#doc_name').val(tr.find('.row-doc-name').text().trim());
                $('#doc_fee').val(tr.find('.row-doc-fee').attr('data-raw-fee'));
                $('#doc_status').val(tr.find('.row-doc-status').attr('data-status'));
                $('#modal_doc_title').text('Edit Document Type');
                $('#modal_add_document').removeClass('hidden');
            });

            // Edit Clearance Requirement
            $(document).on('click', '.btn-edit-clearance', function() {
                const tr = $(this).closest('tr');
                $('#clearance_name').val(tr.find('.row-clr-name').text().trim());
                $('#clearance_office').val(tr.find('.row-clr-office').text().trim());
                $('#clearance_status').val(tr.find('.row-clr-status').attr('data-status'));
                $('#modal_clr_title').text('Edit Clearance Requirement');
                $('#modal_add_clearance').removeClass('hidden');
            });

            // The dedicated page handler below replaces the previous success-only placeholders.
        });
    </script>
    <script src="../../js/adminSettingsPage.js"></script>
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