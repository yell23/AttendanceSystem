<?php
// pages/email_logs.php
$q      = trim($_GET['q']      ?? '');
$status = $_GET['status']   ?? '';
$pg     = max(1, (int)($_GET['p'] ?? 1));
$per    = 20;
$off    = ($pg - 1) * $per;

$where  = "WHERE 1=1";
$params = [];
if ($q)      { $where .= " AND (recipient LIKE ? OR subject LIKE ?)"; $params = array_merge($params, ["%$q%", "%$q%"]); }
if ($status) { $where .= " AND status = ?"; $params[] = $status; }

$total = db_count("SELECT COUNT(*) FROM email_logs $where", $params);
$pages = (int)ceil($total / $per);
$logs  = db_query("SELECT * FROM email_logs $where ORDER BY sent_at DESC LIMIT $per OFFSET $off", $params);
?>
<div class="page-hero"><h1>Email Logs</h1><p>A record of all emails sent or attempted by the system.</p></div>

<div class="card">
  <div class="card-header">
    <form method="GET" action="index.php" class="filter-bar">
      <input type="hidden" name="page" value="email_logs">
      <div class="search-bar">
        <span class="search-icon"><i class="bi bi-search"></i></span>
        <input type="text" name="q" placeholder="Search recipient or subject..." value="<?= htmlspecialchars($q) ?>">
      </div>
      <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent</option>
        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
      </select>
      <button type="submit" class="btn btn-ghost btn-sm"><i class="bi bi-search"></i></button>
    </form>
    <div style="margin-left:auto;font-size:.8rem;color:var(--text3);"><?= number_format($total) ?> entries</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Timestamp</th><th>Recipient</th><th>Subject</th><th>Status</th><th>Details</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">No email logs found.</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td style="font-size:.78rem;color:var(--text3);white-space:nowrap;"><?= date('M j, Y g:i:s A', strtotime($log['sent_at'])) ?></td>
            <td><?= htmlspecialchars($log['recipient']) ?></td>
            <td style="max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($log['subject']) ?>"><?= htmlspecialchars($log['subject']) ?></td>
            <td>
              <?php if ($log['status'] === 'sent'): ?>
                <span class="badge badge-success"><i class="bi bi-check-circle"></i> Sent</span>
              <?php else: ?>
                <span class="badge badge-error"><i class="bi bi-x-circle"></i> Failed</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.78rem;color:var(--text3);max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($log['error_message'] ?? '') ?>">
              <?= htmlspecialchars($log['error_message'] ?? '—') ?>
            </td>
            <td>
              <button class="action-btn" title="Preview Email" onclick='previewEmail(<?= json_encode($log, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-eye"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <?php
    $base_url = "index.php?page=email_logs" . ($q ? "&q=" . urlencode($q) : '') . ($status ? "&status=" . urlencode($status) : '');
    echo render_pagination($pg, $pages, $base_url);
  ?>
  <?php endif; ?>
</div>

<script>
function previewEmail(log) {
    const modal = document.getElementById('email-preview-modal');
    const title = document.getElementById('email-preview-title');
    const iframe = document.getElementById('email-preview-iframe');
    title.textContent = 'Preview: ' + log.subject;
    iframe.srcdoc = log.body_html;
    openModal('email-preview-modal');
}
</script>
