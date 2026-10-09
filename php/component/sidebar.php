<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_start_session();
$role = strtolower((string) ($_SESSION['user']['role'] ?? ''));
$menus = [
    'admin' => ['User Management' => 'admin/userManagement.php', 'System Settings' => 'admin/systemSettings.php', 'System Monitoring' => 'admin/monitoring.php', 'Reports' => 'admin/report.php', 'Notifications' => 'admin/notifications.php', 'Admissions' => 'registrar/admissions.php'],
    'registrar' => ['Enrollment Queue' => 'registrar/enrollment.php', 'Admissions' => 'registrar/admissions.php', 'Grades' => 'registrar/grades.php', 'Document Requests' => 'registrar/documentRequest.php', 'Clearance' => 'registrar/clearance.php', 'Reports' => 'registrar/report.php', 'Notifications' => 'registrar/notifications.php', 'Announcements' => 'registrar/announcements.php'],
    'student' => ['Dashboard' => 'student/dashboard.php', 'Enrollment' => 'student/enrollment.php', 'Grades' => 'student/grades.php', 'Profile' => 'student/profile.php', 'Requests' => 'student/requests.php', 'Clearance' => 'student/clearance.php', 'Notifications' => 'student/notifications.php'],
    'department' => ['Clearance' => 'department/clearance.php', 'Grade Entry' => 'department/grades.php', 'Reports' => 'department/report.php', 'Notifications' => 'department/notifications.php'],
    'cashier' => ['Dashboard' => 'cashier/dashboard.php', 'Payments' => 'cashier/payments.php', 'Clearance' => 'cashier/clearance.php', 'Notifications' => 'cashier/notifications.php'],
    'applicant' => ['Dashboard' => 'applicant/dashboard.php', 'Notifications' => "applicant/notifications.php"]
];
$items = $menus[$role] ?? [];
$newApplicantCount = 0;
$unreadNotificationCount = 0;
if (!empty($_SESSION['user']['id'])) {
    try {
        require_once __DIR__ . '/../../models/Notifications.php';
        $unreadNotificationCount = (new Notifications(Database::getConnection()))->getUnreadCount((int) $_SESSION['user']['id']);
    } catch (Throwable $exception) {
        error_log('SSIS sidebar notification count failed: ' . $exception->getMessage());
    }
}
if (in_array($role, ['admin', 'registrar'], true)) {
    try {
        require_once __DIR__ . '/../../models/Admission.php';
        $newApplicantCount = (new Admission(Database::getConnection()))->newApplicantCount();
    } catch (Throwable $exception) {
        error_log('SSIS sidebar admission count failed: ' . $exception->getMessage());
    }
}
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$base = ssis_app_base_path();
$name = $_SESSION['user']['full_name'] ?? $_SESSION['user']['username'] ?? 'User';
?>
<style>
    body>header:not(.header) {
        display: none !important
    }

    .ssis-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: 248px;
        background: #fff;
        border-right: 1px solid #e5e7eb;
        z-index: 45;
        display: flex;
        flex-direction: column;
        transform: translateX(-100%);
        transition: transform .2s ease
    }

    .ssis-sidebar.is-open {
        transform: translateX(0)
    }

    .ssis-sidebar-backdrop {
        position: fixed;
        inset: 0;
        background: #11182780;
        z-index: 40;
        display: none
    }

    .ssis-sidebar-backdrop.is-open {
        display: block
    }

    .ssis-sidebar-link {
        display: flex;
        align-items: center;
        padding: 11px 14px;
        margin: 2px 10px;
        border-radius: 7px;
        color: #4b5563;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none
    }

    .ssis-sidebar-link:hover,
    .ssis-sidebar-link.is-active {
        background: #dad7cd80;
        color: #344e41;
        font-weight: 700
    }

    .ssis-sidebar-toggle {
        position: fixed;
        top: 12px;
        left: 12px;
        z-index: 35;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 9px;
        color: #344e41;
        box-shadow: 0 1px 4px #0001
    }

    @media(min-width:1024px) {
        body {
            padding-left: 248px
        }

        .ssis-sidebar {
            transform: translateX(0)
        }

        .ssis-sidebar-backdrop,
        .ssis-sidebar-toggle {
            display: none !important
        }

        body>main {
            max-width: none !important
        }
    }
</style>
<script src="../../js/helperFunction.js"></script>
<button type="button" class="ssis-sidebar-toggle" id="ssisSidebarToggle" aria-label="Open navigation"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M4 6h16M4 12h16M4 18h16" />
    </svg></button>
<div class="ssis-sidebar-backdrop" id="ssisSidebarBackdrop"></div>
<aside class="ssis-sidebar" id="ssisSidebar" aria-label="Main navigation">
    <div class="px-5 py-6 border-b">
        <div class="font-bold tracking-widest text-brand-primary">PAXTON</div>
        <div class="text-[10px] uppercase tracking-widest text-gray-500 mt-1">University SSIS · <?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
    <nav class="py-4 flex-1 overflow-y-auto">
        <?php foreach ($items as $label => $url): ?>
                <a class="ssis-sidebar-link <?= $current === basename($url) ? 'is-active' : '' ?>" href="<?= htmlspecialchars($base . '/php/' . $url, ENT_QUOTES, 'UTF-8') ?>"<?= $label === 'Admissions' && $newApplicantCount > 0 ? ' aria-label="Admissions, ' . $newApplicantCount . ' new applicants"' : ($label === 'Notifications' && $unreadNotificationCount > 0 ? ' aria-label="Notifications, ' . $unreadNotificationCount . ' unread"' : '') ?>>
                    <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($label === 'Admissions'): ?><span data-admissions-count class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white<?= $newApplicantCount > 0 ? '' : ' hidden' ?>"<?= $newApplicantCount > 0 ? '' : ' hidden' ?>><?= $newApplicantCount > 99 ? '99+' : $newApplicantCount ?></span><?php endif; ?>
                    <?php if ($label === 'Notifications' && $unreadNotificationCount > 0): ?><span data-notification-count class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white"><?= $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount ?></span><?php endif; ?>
                </a>
        <?php endforeach; ?></nav>
    <div class="border-t p-4">
        <div class="text-xs font-semibold truncate"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></div><a class="text-xs text-red-700 inline-block mt-2" href="<?= htmlspecialchars($base . '/php/logout.php', ENT_QUOTES, 'UTF-8') ?>">Log out</a>
    </div>
</aside>
<script>
    (function() {
        var panel = document.getElementById('ssisSidebar'),
            shade = document.getElementById('ssisSidebarBackdrop'),
            button = document.getElementById('ssisSidebarToggle');

        function close() {
            panel.classList.remove('is-open');
            shade.classList.remove('is-open')
        }
        button.addEventListener('click', function() {
            panel.classList.toggle('is-open');
            shade.classList.toggle('is-open')
        });
        shade.addEventListener('click', close);
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') close()
        })
    })();
</script>
<?php if (in_array($role, ['admin', 'registrar'], true)): ?>
<script>
    (function() {
        var countEndpoint = <?= json_encode($base . '/controller/admissionCountController.php', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        var refreshInProgress = false;

        window.updateAdmissionsBadge = function(value) {
            var badge = document.querySelector('[data-admissions-count]');
            if (!badge) return;

            var count = Math.max(0, Number.parseInt(value, 10) || 0);
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.hidden = count === 0;
            badge.classList.toggle('hidden', count === 0);

            var link = badge.closest('a');
            if (link) {
                if (count > 0) link.setAttribute('aria-label', 'Admissions, ' + count + ' new applicants');
                else link.removeAttribute('aria-label');
            }
        };

        async function refreshAdmissionsBadge() {
            if (refreshInProgress || document.hidden) return;
            refreshInProgress = true;
            try {
                var response = await fetch(countEndpoint, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) return;
                var result = await response.json();
                if (result.status === 'success' && Number.isFinite(Number(result.count))) {
                    window.updateAdmissionsBadge(result.count);
                }
            } catch (error) {
                // A background count refresh should not interrupt the current page.
            } finally {
                refreshInProgress = false;
            }
        }

        refreshAdmissionsBadge();
        window.setInterval(refreshAdmissionsBadge, 10000);
        window.addEventListener('focus', refreshAdmissionsBadge);
        document.addEventListener('visibilitychange', refreshAdmissionsBadge);
    })();
</script>
<?php endif; ?>
