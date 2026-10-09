<?php foreach ($auditEntries as $entry): ?><tr class="audit-row" data-role="<?= $auditEsc($entry['role'] ?? 'System') ?>" data-action="<?= $auditEsc($auditFilterAction((string) $entry['action'])) ?>" data-date="<?= $auditEsc(date('Y-m-d', strtotime($entry['created_at']))) ?>">
    <td class="py-3.5 px-5 font-display text-[11px] text-gray-500 whitespace-nowrap"><?= $auditEsc($entry['created_at']) ?></td>
    <td class="py-3.5 px-5 font-semibold text-brand-dark break-words"><?= $auditEsc($entry['username'] ?: 'System') ?></td>
    <td class="py-3.5 px-5 break-words"><?= $auditEsc($entry['role'] ?: 'System') ?></td>
    <td class="py-3.5 px-5 break-words"><span class="font-display text-[11px] font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200"><?= $auditEsc($entry['action']) ?></span><span class="text-gray-500 ml-1"><?= $auditEsc($entry['details']) ?></span></td>
    <td class="py-3.5 px-5 text-right font-display text-[11px] text-gray-500 whitespace-nowrap"><?= !empty($entry['ip_address']) ? $auditEsc($entry['ip_address']) : '&mdash;' ?></td>
</tr><?php endforeach; ?>
<?php if (!$auditEntries): ?><tr><td colspan="5" class="py-8 px-5 text-center text-gray-500">No audit events have been recorded.</td></tr><?php endif; ?>
