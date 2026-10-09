<div class="flex items-center justify-between gap-3 mb-3">
    <p class="text-xs text-gray-500"><?= (int) $notificationUnreadCount ?> unread notification<?= $notificationUnreadCount === 1 ? '' : 's' ?></p>
    <?php if ($notificationUnreadCount > 0): ?><form method="post">
        <input type="hidden" name="csrf_token" value="<?= $notificationEsc($_SESSION['csrf_token']) ?>">
        <input type="hidden" name="notification_action" value="read_all">
        <button type="submit" class="text-xs font-semibold text-brand-primary hover:underline">Mark all as read</button>
    </form><?php endif; ?>
</div>
<?php
$notificationScriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$notificationPhpPosition = strpos($notificationScriptPath, '/php/');
$notificationAppBase = $notificationPhpPosition === false ? '' : substr($notificationScriptPath, 0, $notificationPhpPosition);
?>
<?php foreach ($notifications as $notification): ?>
    <article class="notification-item <?= empty($notification['is_read']) ? 'unread bg-brand-accent/20 border-l-4 border-l-brand-primary' : 'read bg-white border-l-4 border-l-gray-200' ?> border-y border-r border-gray-200 rounded-r-md p-4 shadow-xs flex items-start justify-between gap-4">
        <div class="flex items-start space-x-3.5 min-w-0">
            <div class="p-2.5 bg-brand-primary/10 text-brand-primary rounded-md shrink-0 mt-0.5"><i data-lucide="bell" class="w-5 h-5"></i></div>
            <div class="space-y-1 min-w-0">
                <div class="flex items-center flex-wrap gap-2"><h2 class="text-xs font-bold text-brand-dark"><?= $notificationEsc($notification['title']) ?></h2><span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-700"><?= $notificationEsc($notification['category']) ?></span><?php if (empty($notification['is_read'])): ?><span class="w-2 h-2 rounded-full bg-red-600 inline-block" title="Unread"></span><?php endif; ?></div>
                <p class="text-xs text-gray-700"><?= nl2br($notificationEsc($notification['message'])) ?></p>
                <p class="text-[10px] text-gray-500"><?= $notificationEsc(date('M j, Y g:i A', strtotime($notification['created_at']))) ?></p>
                <?php
                $notificationLink = (string) ($notification['link'] ?? '');
                if (str_starts_with($notificationLink, '/modules/')) {
                    $notificationLink = '/php/' . substr($notificationLink, strlen('/modules/'));
                }
                if ($notificationLink !== '' && str_starts_with($notificationLink, '/') && !str_starts_with($notificationLink, '//')) {
                    $notificationLink = $notificationAppBase . $notificationLink;
                }
                ?>
                <?php if ($notificationLink !== '' && str_starts_with($notificationLink, '/') && !str_starts_with($notificationLink, '//')): ?><a href="<?= $notificationEsc($notificationLink) ?>" class="inline-block text-[11px] text-brand-primary font-semibold hover:underline">Open related page</a><?php endif; ?>
            </div>
        </div>
        <?php if (empty($notification['is_read'])): ?><form method="post" class="shrink-0">
            <input type="hidden" name="csrf_token" value="<?= $notificationEsc($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="notification_action" value="read">
            <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
            <button type="submit" class="text-xs font-semibold text-brand-primary hover:underline">Mark read</button>
        </form><?php else: ?><span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider shrink-0">Read</span><?php endif; ?>
    </article>
<?php endforeach; ?>
<?php if (!$notifications): ?><div class="bg-white border border-gray-200 rounded-md p-8 text-center text-xs text-gray-500">You have no notifications yet.</div><?php endif; ?>
