<?php
// pages/notifications.php

/**
 * Formats an audit log record into a user-friendly notification.
 * @param array $log The audit log row from the database.
 * @return array A structured notification array with icon, title, description, link, and time.
 */
function format_notification(array $log): array
{
    $notification = [
        'icon' => '<i class="bi bi-info-circle"></i>',
        'title' => 'System Event',
        'description' => 'An action was performed.',
        'link' => 'index.php?page=logs',
        'time' => $log['created_at']
    ];

    $target = htmlspecialchars($log['target'] ?? 'N/A');
    $actor = htmlspecialchars($log['actor_name'] ?? 'System');
    $event = htmlspecialchars($log['event_name'] ?? '');
    $result = htmlspecialchars($log['result'] ?? '');

    // This is a sample implementation. The `action` types depend on what is being logged to the audit_logs table.
    switch ($log['action']) {
        case 'scan':
            $notification['title'] = 'QR Code Scan';
            if ($log['result'] === 'checked_in') {
                $notification['icon'] = '<i class="bi bi-check-circle-fill text-success"></i>';
                $notification['description'] = "<strong>{$target}</strong> has been checked in to the event '{$event}'.";
            } elseif ($log['result'] === 'already_checked_in') {
                $notification['icon'] = '<i class="bi bi-exclamation-triangle-fill text-warning"></i>';
                $notification['description'] = "<strong>{$target}</strong> attempted to check in again for the event '{$event}'.";
            } else {
                $notification['icon'] = '<i class="bi bi-x-circle-fill text-error"></i>';
                $notification['description'] = "A scan for '{$target}' failed for event '{$event}'. Result: {$result}";
            }
            $notification['link'] = 'index.php?page=attendees&q=' . urlencode($log['target']);
            break;

        case 'login':
            $notification['icon'] = '<i class="bi bi-key"></i>';
            $notification['title'] = 'Successful Login';
            $notification['description'] = "User <strong>{$actor}</strong> logged in from IP address {$log['ip_address']}.";
            $notification['link'] = 'index.php?page=logs&type=login';
            break;

        case 'sync_attendees':
            $notification['icon'] = '<i class="bi bi-arrow-clockwise"></i>';
            $notification['title'] = 'Attendee Sync Completed';
            $notification['description'] = "Attendee sync from Google Sheets for event '{$event}' has finished. Result: {$result}";
            $notification['link'] = 'index.php?page=forms';
            break;

        default:
            $notification['title'] = 'System Action: ' . htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action'])));
            $notification['description'] = "Details: Target `{$target}`, Result `{$result}`.";
            $notification['link'] = 'index.php?page=logs&q=' . urlencode($log['action']);
            break;
    }

    return $notification;
}

// --- For the main notifications page ---
$pg   = max(1, (int)($_GET['p'] ?? 1));
$per  = 20; // Notifications per page
$off  = ($pg - 1) * $per;
$total = db_count("SELECT COUNT(*) FROM audit_logs");
$pages = (int)ceil($total / $per);
$logs  = db_query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT $per OFFSET $off");
?>
<div class="page-hero"><h1>All Notifications</h1><p>A complete history of your notifications.</p></div>

<div class="card">
  <div class="card-body" style="padding: 0;">
    <?php if (empty($logs)): ?>
      <div class="empty-state" style="padding: 40px;">
        <div class="empty-icon" style="font-size: 3rem;"><i class="bi bi-bell-slash"></i></div>
        <div class="empty-text">You have no notifications yet.</div>
        <p class="text-muted text-sm" style="margin-top: 8px;">System events and actions will appear here as they happen.</p>
      </div>
    <?php else: ?>
      <div class="notif-body" style="max-height: none; border-radius: var(--radius-md);">
        <?php foreach ($logs as $log):
            $notification = format_notification($log);
            ?>
          <a href="<?= $notification['link'] ?>" class="notif-item">
              <div class="notif-icon"><?= $notification['icon'] ?></div>
              <div>
                <div class="notif-title"><?= $notification['title'] ?></div>
                <div class="notif-desc"><?= $notification['description'] ?></div>
                <div class="notif-time"><?= time_elapsed_string($notification['time']) ?> &bull; <?= date('M j, Y g:i A', strtotime($notification['time'])) ?></div>
              </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
    <?php if ($pages > 1): ?>
      <?= render_pagination($pg, $pages, "index.php?page=notifications") ?>
    <?php endif; ?>
</div>