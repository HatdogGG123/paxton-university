<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('student');
require_once __DIR__ . '/../includes/notifications-page-data.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Notifications</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- JQuery -->
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
                    <a href="profile.php" class="px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:text-brand-primary hover:bg-gray-100 transition-colors">Profile</a>
                    <a href="notifications.php" class="px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30 border border-brand-secondary/20">Notifications</a>
                </nav>

                <!-- Action Controls -->
                <div class="flex items-center space-x-3">
                    <div class="h-5 w-px bg-gray-200 hidden md:block"></div>

                    <!-- User Info / Profile -->
                    <div class="hidden lg:flex items-center space-x-2">
                        <div class="text-right">
                            <p id="nav_student_name" class="text-xs font-semibold text-brand-dark">John Doe</p>
                            <p id="nav_student_id" class="text-[10px] text-gray-500">2026-00001</p>
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
            <a href="profile.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-600 hover:bg-gray-50">Profile</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-base font-semibold text-brand-primary bg-brand-accent/30">Notifications</a>
            <div class="pt-3 border-t border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-brand-dark">John Doe</p>
                    <p class="text-xs text-gray-500">2026-00001</p>
                </div>
                <a href="logout.php" class="text-xs text-red-600 font-semibold hover:underline">Log Out</a>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">


        <!-- Notifications List Feed -->
        <section class="space-y-3" id="container_notifications_list"><?php require __DIR__ . '/../includes/notifications-list.php'; ?></section>

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
(function ($) {
    if (!$) return;
    $(document).on('submit', 'form[method="post"], form[method="POST"]', function (event) {
        if (event.isDefaultPrevented()) return;
        event.preventDefault();
        var form = this;
        var fields = $(form).serializeArray();
        if (event.originalEvent && event.originalEvent.submitter && event.originalEvent.submitter.name) {
            fields.push({ name: event.originalEvent.submitter.name, value: event.originalEvent.submitter.value });
        }
        if (window.location.pathname.toLowerCase().endsWith('/login.php')) fields.push({ name: 'action', value: 'handleUserLogin' });
        $.post('../../controller/notificationsController.php', $.param(fields), null, 'json')
            .done(function (response) {
                if (response && response.status === 'success') {
                    var readAll = $(form).find('[name="notification_action"]').val() === 'read_all';
                    var $items = readAll ? $('#container_notifications_list .notification-item.unread') : $(form).closest('.notification-item');
                    $items.each(function () {
                        var item = this;
                        item.classList.remove('unread', 'bg-brand-accent/20', 'border-l-brand-primary');
                        item.classList.add('read', 'bg-white', 'border-l-gray-200');
                        item.querySelector('[title="Unread"]')?.remove();
                        item.querySelector('form')?.remove();
                        if (!item.querySelector('.tracking-wider')) item.insertAdjacentHTML('beforeend', '<span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider shrink-0">Read</span>');
                    });
                    if (readAll) form.remove();
                    var count = Number.isFinite(Number(response.unread_count))
                        ? Math.max(0, Number(response.unread_count))
                        : $('#container_notifications_list .notification-item.unread').length;
                    $('#container_notifications_list > div:first-child p').text(count + ' unread notification' + (count === 1 ? '' : 's'));
                    var badge = document.querySelector('[data-notification-count]');
                    var badgeLink = badge && badge.closest('a');
                    if (badge) {
                        if (count > 0) badge.textContent = count > 99 ? '99+' : String(count);
                        else badge.remove();
                    }
                    if (badgeLink) {
                        if (count > 0) badgeLink.setAttribute('aria-label', 'Notifications, ' + count + ' unread');
                        else badgeLink.removeAttribute('aria-label');
                    }
                    showToast('success', response.message || 'Notification updated.');
                } else {
                    var message = response && response.message ? response.message : 'The action could not be completed.';
                    showToast('error', message);
                }
            })
            .fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'The action could not be completed. Please try again.';
                showToast('error', message);
                if (form.closest('body') && window.location.pathname.toLowerCase().endsWith('/login.php')) {
                    var button = document.getElementById('btn-submit');
                    if (button) { button.disabled = false; button.classList.remove('opacity-80', 'cursor-not-allowed'); button.innerHTML = 'Sign In'; }
                }
            });
    });
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if ((this.method || 'get').toLowerCase() === 'post') { this.requestSubmit(); return; }
        nativeSubmit.call(this);
    };
})(window.jQuery);
</script>
</body>

</html>
