<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('applicant');
require_once __DIR__ . '/../includes/notifications-page-data.php';
$pageTitle = 'Applicant Notifications | Paxton University';
?>
<!doctype html>
<html lang="en">
    <?php include __DIR__ . '/../includes/head.php'; ?>

<body class="bg-gray-50 font-sans text-brand-dark antialiased min-h-screen flex flex-col">
    <?php include __DIR__ . '/../component/sidebar.php'; ?>
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4 sm:px-6">
            <div>
                <p class="font-bold tracking-wide">PAXTON UNIVERSITY</p>
                <p class="text-xs text-gray-500">Applicant Portal</p>
            </div>
            <div class="flex items-center gap-4"><a href="dashboard.php" class="text-sm font-semibold text-brand-primary hover:underline">Application status</a><a href="../logout.php" class="text-sm text-red-700">Sign out</a></div>
        </div>
    </header>
    <main class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
        <section>
            <p class="text-xs font-bold uppercase tracking-widest text-brand-secondary">Applicant portal</p>
            <h1 class="mt-1 text-2xl font-bold">Notifications</h1>
            <p class="mt-2 text-sm text-gray-500">Application updates and document review messages appear here.</p>
        </section>
        <section id="container_notifications_list" class="space-y-3"><?php require __DIR__ . '/../includes/notifications-list.php'; ?></section>
    </main>
</body>

</html>