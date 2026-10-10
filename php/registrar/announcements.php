<?php
require_once __DIR__ . '/../includes/auth.php';
ssis_require_role('registrar');
require_once __DIR__ . '/../../models/RegistrarCommunications.php';
$communications = new RegistrarCommunications(Database::getConnection());
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$communicationNotice = $_SESSION['registrar_communications_notice'] ?? null;
unset($_SESSION['registrar_communications_notice']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
            throw new DomainException('This form expired. Refresh the page and try again.');
        }
        $action = (string) ($_POST['communication_action'] ?? '');
        $registrarId = (int) $_SESSION['user']['id'];
        if ($action === 'publish') {
            $communications->publishAnnouncement($registrarId, (string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? ''), (string) ($_POST['audience'] ?? ''));
            $communicationNotice = ['success' => true, 'message' => 'Announcement published.'];
        } elseif ($action === 'send_message') {
            $communications->sendDirectMessage($registrarId, (string) ($_POST['student_no'] ?? ''), (string) ($_POST['category'] ?? ''), (string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? ''));
            $communicationNotice = ['success' => true, 'message' => 'Direct student message sent.'];
        } elseif ($action === 'deactivate') {
            $communications->deactivateAnnouncement($registrarId, (int) ($_POST['announcement_id'] ?? 0));
            $communicationNotice = ['success' => true, 'message' => 'Announcement deactivated.'];
        } else {
            throw new DomainException('Choose a valid announcement action.');
        }
    } catch (DomainException $exception) {
        $communicationNotice = ['success' => false, 'message' => $exception->getMessage()];
    } catch (Throwable $exception) {
        error_log('SSIS registrar communication failed: ' . $exception->getMessage());
        $communicationNotice = ['success' => false, 'message' => 'The action could not be completed. Please try again.'];
    }
    $_SESSION['registrar_communications_notice'] = $communicationNotice;
    header('Location: announcements.php');
    exit;
}
$announcements = $communications->getAnnouncements();
$directMessages = $communications->getDirectMessages();
$communicationCounts = $communications->getCounts();
$communicationEsc = static fn($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paxton University - Announcements & Notifications</title>
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

<body class="bg-gray-50 font-sans text-brand-dark antialiased min-h-screen flex flex-col relative">
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
                    <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Report</a>
                    <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
                    <a href="announcements.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Announcements</a>
                </nav>

                <!-- Profile Info & Mobile Toggle -->
                <div class="flex items-center space-x-3">
                    <div class="hidden lg:flex items-center space-x-2">
                        <div class="text-right">
                            <p class="text-xs font-semibold text-brand-dark"><?= $communicationEsc($_SESSION['user']['full_name'] ?? $_SESSION['user']['username'] ?? 'Registrar Staff') ?></p>
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
            <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Report</a>
            <a href="notifications.php" class="block px-3 py-2 rounded-md text-sm font-medium text-gray-600 hover:bg-gray-50">Notifications</a>
            <a href="announcements.php" class="block px-3 py-2 rounded-md text-sm font-semibold text-brand-primary bg-brand-accent/30">Announcements</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <?php if ($communicationNotice): ?><script>
                showToast(<?= json_encode(!empty($communicationNotice['success']) ? 'success' : 'error') ?>, <?= json_encode($communicationNotice['message'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>);
            </script><?php endif; ?>

        <!-- Header Banner -->
        <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col lg:flex-row lg:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-brand-dark tracking-tight">Announcements & Notifications</h1>
                <p class="text-sm text-gray-500">Publish general broadcast announcements or send targeted direct messages to specific students.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-50 border border-gray-200 rounded-md px-4 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Active Announcements</span>
                    <span data-announcement-count class="text-base font-bold text-brand-primary"><?= number_format($communicationCounts['announcements']) ?></span>
                </div>
                <div class="bg-gray-50 border border-gray-200 rounded-md px-4 py-2 text-center">
                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Direct Messages Sent</span>
                    <span data-direct-message-count class="text-base font-bold text-brand-secondary"><?= number_format($communicationCounts['messages']) ?></span>
                </div>
            </div>
        </section>

        <!-- Composition Forms Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- SECTION 1: Post Announcement Form -->
            <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center space-x-2 border-b border-gray-100 pb-3 mb-4">
                        <i data-lucide="megaphone" class="w-5 h-5 text-brand-primary"></i>
                        <h2 class="text-base font-bold text-brand-dark">Post System Announcement</h2>
                    </div>

                    <form id="form_post_announcement" method="post" class="space-y-4 text-xs">
                        <input type="hidden" name="csrf_token" value="<?= $communicationEsc($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="communication_action" value="publish">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-1">Announcement Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" maxlength="150" required placeholder="e.g. Schedule for 1st Trimester Final Examinations" class="w-full border border-gray-200 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-brand-secondary">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 font-semibold mb-1">Target Audience <span class="text-red-500">*</span></label>
                                <select name="audience" required class="w-full border border-gray-200 rounded-md px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-brand-secondary">
                                    <option value="Students">All Enrolled Students</option>
                                    <option value="All">All Users</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-1">Announcement Details <span class="text-red-500">*</span></label>
                            <textarea name="body" rows="4" maxlength="60000" required placeholder="Write the full announcement message content here..." class="w-full border border-gray-200 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-brand-secondary"></textarea>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="px-4 py-2 font-semibold bg-brand-primary text-white rounded-md hover:bg-brand-primary/90 flex items-center space-x-2">
                                <i data-lucide="send" class="w-4 h-4"></i>
                                <span>Publish Announcement</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- SECTION 2: Send Direct Student Message Form -->
            <section class="bg-white border border-gray-200 rounded-md p-6 shadow-xs flex flex-col justify-between">
                <div>
                    <div class="flex items-center space-x-2 border-b border-gray-100 pb-3 mb-4">
                        <i data-lucide="mail" class="w-5 h-5 text-brand-secondary"></i>
                        <h2 class="text-base font-bold text-brand-dark">Send Student Message</h2>
                    </div>

                    <form id="form_send_direct_message" method="post" class="space-y-4 text-xs">
                        <input type="hidden" name="csrf_token" value="<?= $communicationEsc($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="communication_action" value="send_message">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 font-semibold mb-1">Student Number / ID <span class="text-red-500">*</span></label>
                                <input type="text" name="student_no" required placeholder="e.g. 2026-00001" class="w-full border border-gray-200 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-brand-secondary">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-semibold mb-1">Message Category</label>
                                <select name="category" class="w-full border border-gray-200 rounded-md px-3 py-2 bg-white focus:outline-none focus:ring-1 focus:ring-brand-secondary">
                                    <option value="Clearance">Clearance Requirement Notice</option>
                                    <option value="Requests">Document Request Update</option>
                                    <option value="Enrollment">Enrollment Assessment Alert</option>
                                    <option value="System">General Notice</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-1">Subject / Header <span class="text-red-500">*</span></label>
                            <input type="text" name="title" maxlength="150" required placeholder="e.g. Missing Form 137 Credential Clearance" class="w-full border border-gray-200 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-brand-secondary">
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-1">Message Body <span class="text-red-500">*</span></label>
                            <textarea name="body" rows="4" maxlength="450" required placeholder="Type direct private message to student..." class="w-full border border-gray-200 rounded-md p-2.5 focus:outline-none focus:ring-1 focus:ring-brand-secondary"></textarea>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <button type="submit" class="px-4 py-2 font-semibold bg-brand-secondary text-white rounded-md hover:bg-brand-secondary/90 flex items-center space-x-2">
                                <i data-lucide="send" class="w-4 h-4"></i>
                                <span>Send Direct Message</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- SECTION 3: Tables Section -->
        <div class="space-y-8">

            <!-- Table 1: Recent Published Announcements -->
            <section class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="bell" class="w-4 h-4 text-brand-primary"></i>
                        <h3 class="text-base font-bold text-brand-dark">Active & Recent Announcements</h3>
                    </div>
                    <div class="flex items-center space-x-2">
                        <input id="search_announcements" type="text" placeholder="Search Announcements..." class="px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary">
                    </div>
                </div>

                <!-- Scrollable Table Container -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[750px] text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase font-semibold">
                                <th class="py-3 px-5 min-w-[200px]">Title & Content Preview</th>
                                <th class="py-3 px-5 text-center whitespace-nowrap">Audience</th>
                                <th class="py-3 px-5 whitespace-nowrap">Date Published</th>
                                <th class="py-3 px-5 text-right whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                            <?php foreach ($announcements as $announcement): $audienceLabel = $announcement['audience'] === 'Students' ? 'All Students' : ($announcement['audience'] === 'All' ? 'All Users' : $announcement['audience']); ?>
                                <tr class="hover:bg-gray-50/50" data-announcement-id="<?= (int) $announcement['id'] ?>" data-announcement-search="<?= $communicationEsc($announcement['title'] . ' ' . $announcement['body'] . ' ' . $audienceLabel) ?>">
                                    <td class="py-3.5 px-5">
                                        <p class="font-bold text-brand-dark"><?= $communicationEsc($announcement['title']) ?></p>
                                        <p class="text-gray-400 text-[11px] truncate max-w-xs"><?= $communicationEsc($announcement['body']) ?></p>
                                    </td>
                                    <td class="py-3.5 px-5 text-center whitespace-nowrap"><span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-gray-100 text-gray-700"><?= $communicationEsc($audienceLabel) ?></span></td>
                                    <td class="py-3.5 px-5 whitespace-nowrap text-gray-500"><?= $communicationEsc(date('M d, Y', strtotime($announcement['created_at']))) ?></td>
                                    <td class="py-3.5 px-5 text-right space-x-1 whitespace-nowrap">
                                        <button type="button" class="js-view-announcement px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white hover:bg-brand-primary/90" data-title="<?= $communicationEsc($announcement['title']) ?>" data-audience="<?= $communicationEsc($audienceLabel) ?>" data-date="<?= $communicationEsc(date('M d, Y', strtotime($announcement['created_at']))) ?>" data-body="<?= $communicationEsc($announcement['body']) ?>">View</button>
                                        <?php if (!empty($announcement['is_active'])): ?><button type="button" class="px-2.5 py-1.5 rounded-md text-xs font-semibold border border-red-200 text-red-600 bg-white hover:bg-red-50" onclick="promptDeleteRecord('announcement', <?= htmlspecialchars(json_encode($announcement['title'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>, <?= (int) $announcement['id'] ?>)">Delete</button><?php else: ?><span class="text-[10px] font-semibold text-gray-400">Inactive</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$announcements): ?><tr>
                                    <td colspan="4" class="py-8 px-5 text-center text-gray-500">No announcements have been published yet.</td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Table 2: Direct Student Messages Log -->
            <section class="bg-white border border-gray-200 rounded-md shadow-xs overflow-hidden">
                <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="message-square" class="w-4 h-4 text-brand-secondary"></i>
                        <h3 class="text-base font-bold text-brand-dark">Direct Student Messages Log</h3>
                    </div>
                    <div class="flex items-center space-x-2">
                        <input id="search_direct_messages" type="text" placeholder="Search Student or Subject..." class="px-3 py-1.5 text-xs border border-gray-200 rounded-md focus:outline-none focus:ring-1 focus:ring-brand-secondary">
                    </div>
                </div>

                <!-- Scrollable Table Container -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[750px] text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-50/80 border-b border-gray-100 text-gray-500 uppercase font-semibold">
                                <th class="py-3 px-5 whitespace-nowrap">Student</th>
                                <th class="py-3 px-5 min-w-[180px]">Subject / Title</th>
                                <th class="py-3 px-5 text-center whitespace-nowrap">Category</th>
                                <th class="py-3 px-5 text-center whitespace-nowrap">Status</th>
                                <th class="py-3 px-5 whitespace-nowrap">Sent Date</th>
                                <th class="py-3 px-5 text-right whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                            <?php foreach ($directMessages as $directMessage): $messageParts = explode('] ', (string) $directMessage['message'], 2);
                                $messageCategory = ltrim($messageParts[0] ?? '', '[');
                                $messageBody = $messageParts[1] ?? (string) $directMessage['message']; ?>
                                <tr class="hover:bg-gray-50/50" data-message-search="<?= $communicationEsc($directMessage['student_name'] . ' ' . $directMessage['student_no'] . ' ' . $directMessage['title']) ?>">
                                    <td class="py-3.5 px-5 whitespace-nowrap">
                                        <p class="font-bold text-brand-dark"><?= $communicationEsc($directMessage['student_name']) ?></p>
                                        <p class="text-gray-400 text-[11px] font-display"><?= $communicationEsc($directMessage['student_no']) ?></p>
                                    </td>
                                    <td class="py-3.5 px-5">
                                        <p class="font-semibold text-brand-dark"><?= $communicationEsc($directMessage['title']) ?></p>
                                    </td>
                                    <td class="py-3.5 px-5 text-center whitespace-nowrap"><span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200"><?= $communicationEsc($messageCategory) ?></span></td>
                                    <td class="py-3.5 px-5 text-center whitespace-nowrap"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= empty($directMessage['is_read']) ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' ?>"><?= empty($directMessage['is_read']) ? 'Unread' : 'Read' ?></span></td>
                                    <td class="py-3.5 px-5 whitespace-nowrap text-gray-500"><?= $communicationEsc(date('M d, Y', strtotime($directMessage['created_at']))) ?></td>
                                    <td class="py-3.5 px-5 text-right space-x-1 whitespace-nowrap"><button type="button" class="js-view-direct-message px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white hover:bg-brand-primary/90" data-student="<?= $communicationEsc($directMessage['student_name']) ?>" data-student-no="<?= $communicationEsc($directMessage['student_no']) ?>" data-title="<?= $communicationEsc($directMessage['title']) ?>" data-category="<?= $communicationEsc($messageCategory) ?>" data-date="<?= $communicationEsc(date('M d, Y', strtotime($directMessage['created_at']))) ?>" data-body="<?= $communicationEsc($messageBody) ?>">View Message</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$directMessages): ?><tr>
                                    <td colspan="6" class="py-8 px-5 text-center text-gray-500">No direct student messages have been sent yet.</td>
                                </tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <form id="deactivate_announcement_form" method="post" class="hidden">
        <input type="hidden" name="csrf_token" value="<?= $communicationEsc($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="communication_action" value="deactivate">
        <input type="hidden" name="announcement_id" id="deactivate_announcement_id">
    </form>

    <!-- Modal 1: View Announcement Modal -->
    <div id="modal_view_announcement" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
            <div class="p-5 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <i data-lucide="megaphone" class="w-5 h-5 text-brand-primary"></i>
                    <h3 class="text-base font-bold text-brand-dark">Announcement Details</h3>
                </div>
                <button type="button" onclick="closeModal('modal_view_announcement')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 space-y-4 text-xs">
                <div>
                    <h4 id="view_announcement_title" class="text-base font-bold text-brand-dark"></h4>
                    <div class="flex md:items-center items-start md:space-x-2 mt-1 flex-col md:flex-row gap-2">
                        <span id="view_announcement_audience" class="px-2 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-700"></span>
                        <span id="view_announcement_priority" class="px-2 py-0.5 rounded text-[10px] font-bold"></span>
                        <span id="view_announcement_date" class="text-gray-400 text-[11px]"></span>
                    </div>
                </div>
                <div class="bg-gray-50 border border-gray-100 rounded-md p-4 text-gray-700 leading-relaxed text-xs" id="view_announcement_body"></div>
            </div>
            <div class="p-4 border-t border-gray-100 bg-gray-50/50 flex justify-end">
                <button type="button" onclick="closeModal('modal_view_announcement')" class="px-4 py-2 font-semibold text-xs border border-gray-200 bg-white text-gray-700 rounded-md hover:bg-gray-50">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal 2: View Direct Message Modal -->
    <div id="modal_view_message" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-lg overflow-hidden flex flex-col">
            <div class="p-5 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <i data-lucide="mail" class="w-5 h-5 text-brand-secondary"></i>
                    <h3 class="text-base font-bold text-brand-dark">Direct Student Message</h3>
                </div>
                <button type="button" onclick="closeModal('modal_view_message')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <div class="p-6 space-y-4 text-xs">
                <div class="p-3 bg-gray-50 border border-gray-200 rounded-md flex justify-between items-center">
                    <div>
                        <p id="view_msg_student_name" class="font-bold text-brand-dark"></p>
                        <p id="view_msg_student_id" class="text-gray-500 font-display text-[11px]"></p>
                    </div>
                    <span id="view_msg_category" class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200"></span>
                </div>
                <div>
                    <p class="text-gray-400 text-[10px] uppercase font-bold">Subject</p>
                    <p id="view_msg_subject" class="font-bold text-sm text-brand-dark"></p>
                    <p id="view_msg_date" class="text-gray-400 text-[11px] mt-0.5"></p>
                </div>
                <div>
                    <p class="text-gray-400 text-[10px] uppercase font-bold mb-1">Message Content</p>
                    <div id="view_msg_body" class="bg-gray-50 border border-gray-100 rounded-md p-3.5 text-gray-700 text-xs leading-relaxed"></div>
                </div>
            </div>
            <div class="p-4 border-t border-gray-100 bg-gray-50/50 flex justify-end">
                <button type="button" onclick="closeModal('modal_view_message')" class="px-4 py-2 font-semibold text-xs border border-gray-200 bg-white text-gray-700 rounded-md hover:bg-gray-50">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal 3: Confirm Delete Modal -->
    <div id="modal_confirm_delete" class="fixed inset-0 z-50 hidden bg-gray-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-md border border-gray-200 shadow-xl w-full max-w-sm overflow-hidden flex flex-col p-5 space-y-4">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 bg-red-100 text-red-600 rounded-full flex items-center justify-center flex-shrink-0">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-brand-dark">Delete Entry</h3>
                    <p class="text-xs text-gray-500">Are you sure you want to remove this record?</p>
                </div>
            </div>
            <p id="delete_confirm_text" class="text-xs text-gray-600 bg-gray-50 p-3 rounded-md border border-gray-100"></p>
            <div class="flex items-center justify-end space-x-2 pt-2">
                <button type="button" onclick="closeModal('modal_confirm_delete')" class="px-3.5 py-1.5 font-semibold text-xs border border-gray-200 bg-white text-gray-700 rounded-md hover:bg-gray-50">Cancel</button>
                <button type="button" onclick="confirmDeleteAction()" class="px-3.5 py-1.5 font-semibold text-xs bg-red-600 text-white rounded-md hover:bg-red-700">Confirm Delete</button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-12 py-6 text-center text-xs text-gray-500">
        <p>Paxton University Student Services Information System (SSIS) &copy; 2026. All Rights Reserved.</p>
    </footer>

    <!-- JavaScript Handling -->

    <script>
        let itemToDelete = null;

        $(document).ready(function() {
            lucide.createIcons();
            $('.js-view-announcement').on('click', function() {
                const button = $(this);
                viewAnnouncementModal(button.attr('data-title'), button.attr('data-audience'), 'Announcement', button.attr('data-date'), button.attr('data-body'));
            });
            $('.js-view-direct-message').on('click', function() {
                const button = $(this);
                viewDirectMessageModal(button.attr('data-student'), button.attr('data-student-no'), button.attr('data-title'), button.attr('data-category'), button.attr('data-date'), button.attr('data-body'));
            });
            $('#search_announcements').on('input', function() {
                const query = $(this).val().toLowerCase();
                $('[data-announcement-search]').each(function() {
                    $(this).toggle($(this).attr('data-announcement-search').toLowerCase().includes(query));
                });
            });
            $('#search_direct_messages').on('input', function() {
                const query = $(this).val().toLowerCase();
                $('[data-message-search]').each(function() {
                    $(this).toggle($(this).attr('data-message-search').toLowerCase().includes(query));
                });
            });
        });


        // Form Submission Handlers
        // View Announcement Modal Trigger
        function viewAnnouncementModal(title, audience, priority, date, content) {
            $('#view_announcement_title').text(title);
            $('#view_announcement_audience').text(audience);

            let priorityClass = priority.includes('High') ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700';
            $('#view_announcement_priority').attr('class', `px-2 py-0.5 rounded text-[10px] font-bold ${priorityClass}`).text(priority);

            $('#view_announcement_date').text(`Published on ${date}`);
            $('#view_announcement_body').text(content);
            $('#modal_view_announcement').removeClass('hidden');
        }

        // View Direct Message Modal Trigger
        function viewDirectMessageModal(studentName, studentId, subject, category, date, body) {
            $('#view_msg_student_name').text(studentName);
            $('#view_msg_student_id').text(`Student ID: ${studentId}`);
            $('#view_msg_subject').text(subject);
            $('#view_msg_category').text(category);
            $('#view_msg_date').text(`Sent on ${date}`);
            $('#view_msg_body').text(body);
            $('#modal_view_message').removeClass('hidden');
        }

        // Delete Confirmations
        function promptDeleteRecord(type, name, id) {
            itemToDelete = {
                type,
                name,
                id
            };
            $('#delete_confirm_text').text(`Are you sure you want to deactivate the ${type}: "${name}"?`);
            $('#modal_confirm_delete').removeClass('hidden');
        }

        function confirmDeleteAction() {
            if (itemToDelete) {
                if (itemToDelete.type === 'announcement' && itemToDelete.id) {
                    $('#deactivate_announcement_id').val(itemToDelete.id);
                    $('#deactivate_announcement_form').trigger('submit');
                    return;
                }
                closeModal('modal_confirm_delete');
                itemToDelete = null;
            }
        }

        function closeModal(modalId) {
            $(`#${modalId}`).addClass('hidden');
        }

        // Mobile Menu Toggle
        $('#btn_mobile_menu').on('click', function() {
            $('#mobile_nav_menu').toggleClass('hidden');
            $('#icon_menu_open').toggleClass('hidden');
            $('#icon_menu_close').toggleClass('hidden');
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
                $.post('../../controller/communicationsController.php', $.param(fields), null, 'json')
                    .done(function(response) {
                        if (response && response.status === 'success') {
                            var action = form.querySelector('[name="communication_action"]')?.value || '';
                            var data = response.data || {};
                            if (action === 'deactivate') {
                                var announcementId = Number(data.announcement_id || form.querySelector('[name="announcement_id"]')?.value);
                                var announcementRow = Array.from(document.querySelectorAll('tr[data-announcement-id]'))
                                    .find(function(row) { return Number(row.dataset.announcementId) === announcementId; });
                                var deleteButton = announcementRow?.cells[3]?.querySelector('button:not(.js-view-announcement)');
                                if (deleteButton) {
                                    var inactive = document.createElement('span');
                                    inactive.className = 'text-[10px] font-semibold text-gray-400';
                                    inactive.textContent = 'Inactive';
                                    deleteButton.replaceWith(inactive);
                                    var announcementCount = document.querySelector('[data-announcement-count]');
                                    if (announcementCount) announcementCount.textContent = String(Math.max(0, Number(announcementCount.textContent.replace(/,/g, '')) - 1));
                                }
                                // Keep the hidden form in the DOM so subsequent deletes still use this delegated handler.
                                form.querySelector('[name="announcement_id"]').value = '';
                                closeModal('modal_confirm_delete');
                                itemToDelete = null;
                            } else if (action === 'publish') {
                                var tableBody = document.querySelectorAll('table tbody')[0];
                                tableBody?.querySelector('tr td[colspan]')?.closest('tr')?.remove();
                                var audience = data.audience === 'Students' ? 'All Students' : 'All Users';
                                var date = new Date(String(data.created_at || '').replace(' ', 'T')).toLocaleDateString('en-US', {
                                    month: 'short',
                                    day: '2-digit',
                                    year: 'numeric'
                                });
                                var row = document.createElement('tr');
                                row.className = 'hover:bg-gray-50/50';
                                row.dataset.announcementId = data.announcement_id;
                                row.dataset.announcementSearch = [data.title, data.body, audience].join(' ').toLowerCase();
                                var titleCell = document.createElement('td');
                                titleCell.className = 'py-3.5 px-5';
                                var title = document.createElement('p');
                                title.className = 'font-bold text-brand-dark';
                                title.textContent = data.title;
                                var preview = document.createElement('p');
                                preview.className = 'text-gray-400 text-[11px] truncate max-w-xs';
                                preview.textContent = data.body;
                                titleCell.append(title, preview);
                                var audienceCell = document.createElement('td');
                                audienceCell.className = 'py-3.5 px-5 text-center whitespace-nowrap';
                                var audienceBadge = document.createElement('span');
                                audienceBadge.className = 'px-2 py-0.5 rounded-md text-[10px] font-semibold bg-gray-100 text-gray-700';
                                audienceBadge.textContent = audience;
                                audienceCell.appendChild(audienceBadge);
                                var dateCell = document.createElement('td');
                                dateCell.className = 'py-3.5 px-5 whitespace-nowrap text-gray-500';
                                dateCell.textContent = date;
                                var actions = document.createElement('td');
                                actions.className = 'py-3.5 px-5 text-right space-x-1 whitespace-nowrap';
                                var view = document.createElement('button');
                                view.type = 'button';
                                view.className = 'js-view-announcement px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white';
                                view.textContent = 'View';
                                view.dataset.title = data.title;
                                view.dataset.audience = audience;
                                view.dataset.date = date;
                                view.dataset.body = data.body;
                                view.addEventListener('click', function() {
                                    viewAnnouncementModal(data.title, audience, 'Announcement', date, data.body);
                                });
                                var deactivate = document.createElement('button');
                                deactivate.type = 'button';
                                deactivate.className = 'px-2.5 py-1.5 rounded-md text-xs font-semibold border border-red-200 text-red-600 bg-white';
                                deactivate.textContent = 'Delete';
                                deactivate.addEventListener('click', function() {
                                    promptDeleteRecord('announcement', data.title, Number(data.announcement_id));
                                });
                                actions.append(view, deactivate);
                                row.append(titleCell, audienceCell, dateCell, actions);
                                tableBody?.prepend(row);
                                row.hidden = ![data.title, data.body, audience].join(' ').toLowerCase().includes(($('#search_announcements').val() || '').toLowerCase());
                                var count = document.querySelector('[data-announcement-count]');
                                if (count) count.textContent = String(Number(count.textContent.replace(/,/g, '')) + 1);
                                if (data.audience === 'All' && Number.isFinite(Number(data.unread_count))) {
                                    window.updateNotificationsBadge?.(data.unread_count);
                                }
                                form.reset();
                            } else if (action === 'send_message') {
                                var tbody = document.querySelectorAll('table tbody')[1];
                                tbody?.querySelector('tr td[colspan]')?.closest('tr')?.remove();
                                var categoryLabels = {
                                    'Clearance': 'Clearance Notice',
                                    'Requests': 'Document Request Update',
                                    'Enrollment': 'Enrollment Assessment Alert',
                                    'System': 'General Notice'
                                };
                                var studentName = data.student_name || data.student_no;
                                var date = new Date(String(data.created_at || '').replace(' ', 'T')).toLocaleDateString('en-US', {
                                    month: 'short',
                                    day: '2-digit',
                                    year: 'numeric'
                                });
                                var row = document.createElement('tr');
                                row.className = 'hover:bg-gray-50/50';
                                row.dataset.messageSearch = [studentName, data.student_no, data.title].join(' ').toLowerCase();
                                var cells = [studentName + '|' + data.student_no, data.title, categoryLabels[data.category] || data.category, 'Unread', date];
                                cells.forEach(function(value, index) {
                                    var cell = document.createElement('td');
                                    cell.className = 'py-3.5 px-5' + (index === 2 || index === 3 ? ' text-center whitespace-nowrap' : '') + (index === 4 ? ' whitespace-nowrap text-gray-500' : '');
                                    if (index === 0) {
                                        var n = document.createElement('p');
                                        n.className = 'font-bold text-brand-dark';
                                        n.textContent = studentName;
                                        var no = document.createElement('p');
                                        no.className = 'text-gray-400 text-[11px] font-display';
                                        no.textContent = data.student_no;
                                        cell.append(n, no);
                                    } else if (index === 2 || index === 3) {
                                        var badge = document.createElement('span');
                                        badge.className = index === 2 ? 'px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200' : 'px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800';
                                        badge.textContent = value;
                                        cell.appendChild(badge);
                                    } else cell.textContent = value;
                                    row.appendChild(cell);
                                });
                                var actions = document.createElement('td');
                                actions.className = 'py-3.5 px-5 text-right space-x-1 whitespace-nowrap';
                                var view = document.createElement('button');
                                view.type = 'button';
                                view.className = 'js-view-direct-message px-2.5 py-1.5 rounded-md text-xs font-semibold bg-brand-primary text-white';
                                view.textContent = 'View Message';
                                view.addEventListener('click', function() {
                                    viewDirectMessageModal(studentName, data.student_no, data.title, categoryLabels[data.category] || data.category, date, data.body);
                                });
                                actions.appendChild(view);
                                row.appendChild(actions);
                                tbody?.prepend(row);
                                var count = document.querySelector('[data-direct-message-count]');
                                if (count) count.textContent = String(Number(count.textContent.replace(/,/g, '')) + 1);
                                form.reset();
                            }
                            if (window.lucide) lucide.createIcons();
                            showToast('success', response.message || 'Communication updated.');
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
