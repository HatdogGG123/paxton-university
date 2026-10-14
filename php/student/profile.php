<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('student');
require_once __DIR__ . '/../../services/StudentProfileService.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int) $_SESSION['user']['id'];

$profileModel = new StudentProfile(Database::getConnection());
$profileController = new StudentProfileService($profileModel);

$notice = $_SESSION['student_profile_notice'] ?? null;
unset($_SESSION['student_profile_notice']);
$contactFormValues = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        $notice = ['type' => 'error', 'message' => 'This form expired. Refresh the page and try again.'];
    } else {
        try {
            $action = $_POST['action'] ?? '';
            if ($action === 'update_contact') {
                $result = $profileController->updateContact($userId, $_POST);
                $contactFormValues = $result['values'];
            } elseif ($action === 'change_password') {
                $result = $profileController->changePassword($userId, $_POST);
            } else {
                $result = ['success' => false, 'message' => 'Choose a valid profile action.'];
            }

            if ($result['success']) {
                $_SESSION['student_profile_notice'] = ['type' => 'success', 'message' => $result['message']];
                header('Location: profile.php');
                exit;
            }

            $notice = ['type' => 'error', 'message' => $result['message']];
        } catch (Throwable $exception) {
            error_log('SSIS student profile update failed: ' . $exception->getMessage());
            $notice = ['type' => 'error', 'message' => 'We could not save your changes. Please try again.'];
        }
    }
}

try {
    $profile = $profileModel->findForUser($userId);
} catch (Throwable $exception) {
    error_log('SSIS student profile read failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Your profile is temporarily unavailable. Please try again later.');
}

if ($profile === null) {
    http_response_code(404);
    exit('No student profile is linked to this account. Please contact the Registrar.');
}

$escape = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$fullName = trim(implode(' ', array_filter([
    $profile['first_name'],
    $profile['middle_name'],
    $profile['last_name'],
], static fn($part): bool => $part !== null && $part !== '')));

$initials = strtoupper(substr($profile['first_name'], 0, 1) . substr($profile['last_name'], 0, 1));

$yearLabels = [1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year', 6 => '6th Year'];
$yearLabel = $yearLabels[(int) $profile['year_level']] ?? ((int) $profile['year_level'] . ' Year');

$admissionTerm = $profile['admission_semester'] && $profile['admission_academic_year']
    ? $profile['admission_semester'] . ', ' . $profile['admission_academic_year']
    : 'Not assigned';

$formEmail = $contactFormValues['email'] ?? $profile['email'] ?? '';
$formPhone = $contactFormValues['phone_number'] ?? $profile['contact_number'] ?? '';
$formAddress = $contactFormValues['address'] ?? $profile['address'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Student Profile</title>

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
                    <a href="requests.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Requests</a>
                    <a href="profile.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20">Profile</a>
                    <a href="notifications.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Notifications</a>
                </nav>

                <!-- Action Controls -->
                <div class="flex items-center space-x-3">
                    <div class="h-5 w-px bg-gray-200 hidden md:block"></div>

                    <!-- User Info / Profile -->
                    <div class="hidden lg:flex items-center space-x-2">
                        <div class="text-right">
                            <p id="nav_student_name" class="text-xs font-semibold text-brand-dark"><?= $escape($fullName) ?></p>
                            <p id="nav_student_id" class="text-[10px] text-gray-500"><?= $escape($profile['student_no']) ?></p>
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
            <a href="requests.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Requests</a>
            <a href="profile.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">Profile</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-brand-dark"><?= $escape($fullName) ?></p>
                    <p class="text-xs text-gray-500"><?= $escape($profile['student_no']) ?></p>
                </div>
                <a href="logout.php" class="text-xs text-red-600 font-semibold hover:underline">Log Out</a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Page Header -->
        <div>
            <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Student Profile</h1>
            <p class="text-sm text-gray-500">View your student information and update your contact details or password.</p>
        </div>

        <?php if ($notice !== null): ?><script>
                showToast(<?= json_encode($notice['type'] === 'success' ? 'success' : 'error') ?>, <?= json_encode($notice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
            </script><?php endif; ?>

        <!-- Profile Header Card -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center space-x-4">
                <div class="w-20 h-20 bg-brand-primary/10 border-2 border-brand-primary/20 rounded-full flex items-center justify-center text-brand-primary font-bold text-2xl shrink-0">
                    <?= $escape($initials) ?>
                </div>
                <div>
                    <div class="flex items-center space-x-3">
                        <h2 id="lbl_profile_fullname" class="text-xl font-bold text-brand-dark"><?= $escape($fullName) ?></h2>
                        <span id="badge_academic_status" class="px-2.5 py-0.5 rounded-md text-[11px] font-semibold bg-emerald-100 text-emerald-800">
                            <?= $escape($profile['student_type']) ?> Student
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">Student ID: <strong id="lbl_profile_id" class="text-brand-dark"><?= $escape($profile['student_no']) ?></strong></p>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-gray-600">
                        <span class="flex items-center space-x-1">
                            <i data-lucide="graduation-cap" class="w-4 h-4 text-brand-secondary"></i>
                            <span id="lbl_profile_program"><?= $escape($profile['program_name']) ?></span>
                        </span>
                        <span class="flex items-center space-x-1">
                            <i data-lucide="calendar" class="w-4 h-4 text-brand-secondary"></i>
                            <span id="lbl_profile_year"><?= $escape($yearLabel) ?></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="border-t md:border-t-0 md:border-l border-gray-100 pt-4 md:pt-0 md:pl-6 flex flex-col justify-center text-xs text-gray-500 space-y-1">
                <p>Admission Term: <strong id="lbl_admission_term" class="text-brand-dark"><?= $escape($admissionTerm) ?></strong></p>
                <p>Curriculum Version: <strong id="lbl_curriculum_ver" class="text-brand-dark"><?= $escape($profile['curriculum_version'] ?: 'Not assigned') ?></strong></p>
            </div>
        </section>

        <!-- Information Forms Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-full gap-8">

            <!-- Personal Contact Details -->
            <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="user" class="w-5 h-5 text-brand-primary"></i>
                        <h3 class="text-base font-bold text-brand-dark">Personal Details</h3>
                    </div>
                </div>

                <form id="form_update_personal" method="POST" action="profile.php" class="space-y-4 text-xs">
                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="update_contact">
                    <div>
                        <label for="input_email" class="block font-semibold text-gray-700 mb-1">Email</label>
                        <input type="email" id="input_email" name="email" value="<?= $escape($formEmail) ?>" maxlength="120" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none">
                    </div>

                    <div>
                        <label for="input_phone" class="block font-semibold text-gray-700 mb-1">Mobile Contact Number *</label>
                        <input type="text" id="input_phone" name="phone_number" value="<?= $escape($formPhone) ?>" maxlength="20" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none">
                    </div>

                    <div>
                        <label for="textarea_address" class="block font-semibold text-gray-700 mb-1">Residential Address *</label>
                        <textarea id="textarea_address" name="address" rows="3" maxlength="255" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none"><?= $escape($formAddress) ?></textarea>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" id="btn_save_personal" class="inline-flex items-center space-x-1.5 px-3 py-2 bg-brand-primary text-white font-semibold rounded-md hover:bg-brand-primary/90 transition-colors shadow-xs">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span>Save Contact Info</span>
                        </button>
                    </div>
                </form>
            </section>

        </div>

        <!-- Security / Change Password Form Card -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs space-y-4">
            <div class="flex items-center space-x-2 pb-3 border-b border-gray-100">
                <i data-lucide="lock" class="w-5 h-5 text-brand-primary"></i>
                <h3 class="text-base font-bold text-brand-dark">Account Security & Password</h3>
            </div>

            <form id="form_change_password" method="POST" action="profile.php" class="space-y-4 text-xs">
                <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="change_password">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="input_current_password" class="block font-semibold text-gray-700 mb-1">Current Password *</label>
                        <input type="password" id="input_current_password" name="current_password" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none">
                    </div>

                    <div>
                        <label for="input_new_password" class="block font-semibold text-gray-700 mb-1">New Password *</label>
                        <input type="password" id="input_new_password" name="new_password" minlength="8" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none">
                    </div>

                    <div>
                        <label for="input_confirm_password" class="block font-semibold text-gray-700 mb-1">Confirm New Password *</label>
                        <input type="password" id="input_confirm_password" name="confirm_password" minlength="8" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-brand-dark bg-white focus:ring-2 focus:ring-brand-primary focus:border-brand-primary outline-none">
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" id="btn_update_password" class="inline-flex items-center space-x-1.5 px-4 py-2 bg-brand-primary text-white font-semibold rounded-md hover:bg-brand-primary/90 transition-colors shadow-xs">
                        <i data-lucide="shield-check" class="w-4 h-4"></i>
                        <span>Update Password</span>
                    </button>
                </div>
            </form>
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
        (function($) {
            if (!$) return;
            $(document).on('submit', 'form[method="post"], form[method="POST"]', function(event) {
                if (event.isDefaultPrevented()) return;
                event.preventDefault();
                var form = this;
                var fields = $(form).serializeArray();
                if (event.originalEvent && event.originalEvent.submitter && event.originalEvent.submitter.name) {
                    fields.push({
                        name: event.originalEvent.submitter.name,
                        value: event.originalEvent.submitter.value
                    });
                }
                if (window.location.pathname.toLowerCase().endsWith('/login.php')) fields.push({
                    name: 'action',
                    value: 'handleUserLogin'
                });
                $.post('../../controller/studentProfileController.php', $.param(fields), null, 'json')
                    .done(function(response) {
                        if (response && response.status === 'success') {
                            var action = form.querySelector('[name="action"]')?.value || '';
                            if (action.toLowerCase().includes('password')) {
                                form.querySelectorAll('input[type="password"]').forEach(function(input) {
                                    input.value = '';
                                });
                            }
                            showToast('success', response.message || 'Profile updated successfully.');
                        } else {
                            var message = response && response.message ? response.message : 'The action could not be completed.';
                            showToast('error', message);
                        }
                    })
                    .fail(function(xhr) {
                        var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The action could not be completed. Please try again.';
                        showToast('error', message);
                        if (form.closest('body') && window.location.pathname.toLowerCase().endsWith('/login.php')) {
                            var button = document.getElementById('btn-submit');
                            if (button) {
                                button.disabled = false;
                                button.classList.remove('opacity-80', 'cursor-not-allowed');
                                button.innerHTML = 'Sign In';
                            }
                        }
                    });
            });
            var nativeSubmit = HTMLFormElement.prototype.submit;
            HTMLFormElement.prototype.submit = function() {
                if ((this.method || 'get').toLowerCase() === 'post') {
                    this.requestSubmit();
                    return;
                }
                nativeSubmit.call(this);
            };
        })(window.jQuery);
    </script>
</body>

</html>