<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('department');
require_once __DIR__ . '/../includes/notifications-page-data.php';
require_once __DIR__ . '/../../models/Clearance.php';
$departmentModel = new Clearance(Database::getConnection());
$departmentId = $departmentModel->getDepartmentId((int) $_SESSION['user']['id']);
$departmentName = $departmentModel->getDepartmentName($departmentId) ?? 'Department';
$staffName = $_SESSION['user']['full_name'] ?: $_SESSION['user']['username'];
$esc = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Department Clearance Review</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif']
                    },
                    colors: {
                        brand: {
                            primary: '#344e41',
                            secondary: '#588157',
                            accent: '#dad7cd',
                            dark: '#202020'
                        }
                    }
                }
            }
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
                    <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Clearance Queue</a>
                    <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Reports</a>
                    <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Notifications</a>
                </nav>

                <!-- Profile Info & Mobile Toggle -->
                <div class="flex items-center space-x-3">
                    <div class="hidden lg:flex items-center space-x-2">
                        <div class="text-right">
                            <p class="text-xs font-semibold text-brand-dark"><?= $esc($staffName) ?></p>
                            <p class="text-[10px] text-gray-500 uppercase font-bold"><?= $esc($departmentName) ?></p>
                        </div>
                    </div>

                    <!-- Mobile Menu Button -->
                    <a href="../logout.php" id="nav_logout_btn" class="p-2 text-gray-400 hover:text-red-600 rounded-md hover:bg-gray-100 transition-colors" title="Log Out">
                            <i data-lucide="log-out" class="w-5 h-5"></i>
                        </a>
                    <button type="button" id="btn_mobile_menu" class="lg:hidden p-2 rounded-md text-gray-500 hover:text-brand-dark hover:bg-gray-100 focus:outline-none">
                        <i data-lucide="menu" id="icon_menu_open" class="w-6 h-6"></i>
                        <i data-lucide="x" id="icon_menu_close" class="w-6 h-6 hidden"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Dropdown Container -->
        <div id="mobile_nav_menu" class="hidden lg:hidden border-t border-gray-200 bg-white px-4 pt-2 pb-4 space-y-1 text-xs shadow-md">
            <a href="clearance.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Clearance Approval</a>
            <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Reports</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Notifications</a>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between px-3">
                <div>
                    <p class="text-xs font-semibold text-brand-dark"><?= $esc($staffName) ?></p>
                    <p class="text-[10px] text-gray-500 uppercase font-bold"><?= $esc($departmentName) ?></p>
                </div>
                <a href="../logout.php" class="text-xs text-red-600 font-semibold hover:underline">Log Out</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">


        <!-- Notifications List Feed -->
        <section class="space-y-3" id="container_notifications_list"><?php require __DIR__ . '/../includes/notifications-list.php'; ?></section>


    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500">
        <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
    </footer>

    <!-- Interactive Scripts -->
    
    <script>
        $(document).ready(function() {
            lucide.createIcons();

            // Mobile Navigation Toggle Handler
            $('#btn_mobile_menu').on('click', function(e) {
                e.preventDefault();

                // Toggle dropdown visibility
                $('#mobile_nav_menu').toggleClass('hidden');

                // Toggle hamburger and close icons
                $('#icon_menu_open').toggleClass('hidden');
                $('#icon_menu_close').toggleClass('hidden');
            });
        });
    </script>
    <script src="../../js/private-page-history.js"></script>
    <script>
    $(document).on('submit', '#container_notifications_list form[method="post"], #container_notifications_list form[method="POST"]', function(event) {
        event.preventDefault();
        var form = this;
        var $button = $(form).find('button[type="submit"], button:not([type])').first();
        $button.prop('disabled', true).addClass('opacity-60');
        $.post('../../controller/notificationsController.php', $(form).serialize(), null, 'json')
            .done(function(response) {
                if (response && response.status === 'success') {
                    var readAll = $(form).find('[name="notification_action"]').val() === 'read_all';
                    var $items = readAll ? $('#container_notifications_list .notification-item.unread') : $(form).closest('.notification-item');
                    $items.each(function () {
                        this.classList.remove('unread', 'bg-brand-accent/20', 'border-l-brand-primary');
                        this.classList.add('read', 'bg-white', 'border-l-gray-200');
                        this.querySelector('[title="Unread"]')?.remove();
                        this.querySelector('form')?.remove();
                        if (!this.querySelector('.tracking-wider')) this.insertAdjacentHTML('beforeend', '<span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider shrink-0">Read</span>');
                    });
                    if (readAll) form.remove();
                    var count = $('#container_notifications_list .notification-item.unread').length;
                    $('#container_notifications_list > div:first-child p').text(count + ' unread notification' + (count === 1 ? '' : 's'));
                    showToast('success', response.message || 'Notification updated.');
                }
                showToast('error', response && response.message ? response.message : 'The notification action could not be completed.');
                $button.prop('disabled', false).removeClass('opacity-60');
            })
            .fail(function(xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The notification action could not be completed. Please try again.';
                showToast('error', message);
                $button.prop('disabled', false).removeClass('opacity-60');
            });
    });
    </script>
</body>

</html>
