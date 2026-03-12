<?php
// pages/logs.php
$q    = trim($_GET['q']      ?? '');
$type = $_GET['type']         ?? '';
$pg   = max(1,(int)($_GET['p']??1));
$per  = 20;
$off  = ($pg-1)*$per;

$where  = "WHERE 1=1";
$params = [];
if ($q)    { $where .= " AND (al.target LIKE ? OR al.actor_name LIKE ? OR al.action LIKE ?)"; $params=array_merge($params,["%$q%","%$q%","%$q%"]); }
if ($type) { $where .= " AND al.action = ?"; $params[] = $type; }

$total = db_count("SELECT COUNT(*) FROM audit_logs al $where", $params);
$pages = (int)ceil($total/$per);
$logs  = db_query("SELECT * FROM audit_logs al $where ORDER BY al.created_at DESC LIMIT $per OFFSET $off", $params);
$actions = db_query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
?>
<div class="page-hero-row">
  <div class="page-hero"><h1>Audit Logs</h1><p>Complete trail of all system events and scan attempts.</p></div>
  <a href="index.php?page=logs&export=csv" class="btn btn-ghost"><i class="bi bi-download"></i> Export CSV</a>
</div>

<?php
if (isset($_GET['export'])) {
    $all = db_query("SELECT created_at,action,actor_name,target,result,event_name,ip_address FROM audit_logs ORDER BY created_at DESC");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="audit_logs_'.date('Ymd').'.csv"');
    $fp = fopen('php://output','w');
    fputcsv($fp,['Timestamp','Action','Actor','Target','Result','Event','IP']);
    foreach ($all as $r) fputcsv($fp,array_values($r));
    fclose($fp); exit;
}
?>

<div class="card">
  <div class="card-header">
    <form method="GET" action="index.php" class="filter-bar">
      <input type="hidden" name="page" value="logs">
      <div class="search-bar">
        <span class="search-icon"><i class="bi bi-search"></i></span>
        <input type="text" name="q" placeholder="Search logs..." value="<?= htmlspecialchars($q) ?>">
      </div>
      <select name="type" class="form-select" onchange="this.form.submit()">
        <option value="">All Actions</option>
        <?php foreach ($actions as $a): ?>
          <option value="<?= $a['action'] ?>" <?= $type===$a['action']?'selected':'' ?>><?= htmlspecialchars($a['action']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-ghost btn-sm"><i class="bi bi-search"></i></button>
    </form>
    <div style="margin-left:auto;font-size:.8rem;color:var(--text3);"><?= number_format($total) ?> entries</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Timestamp</th><th>Action</th><th>Actor</th><th>Target</th><th>Result</th><th>Event</th><th>IP</th></tr></thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No logs found.</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $l): ?>
          <tr>
            <td style="font-size:.78rem;color:var(--text3);white-space:nowrap;"><?= date('M j, g:i:s A', strtotime($l['created_at'])) ?></td>
            <td><span class="badge badge-info"><?= htmlspecialchars($l['action']) ?></span></td>
            <td><?= htmlspecialchars($l['actor_name']??'—') ?></td>
            <td><?= htmlspecialchars($l['target']??'—') ?></td>
            <td>
              <?php
              $result = $l['result'] ?? '';
              switch ($result) {
                  case 'checked_in':
                      $icon_class = 'bi bi-check-circle-fill text-success';
                      break;
                  case 'already_checked_in':
                      $icon_class = 'bi bi-exclamation-triangle-fill text-warning';
                      break;
                  case 'invalid':
                  case 'fail':
                      $icon_class = 'bi bi-x-circle-fill text-error';
                      break;
                  default:
                      $icon_class = 'bi bi-info-circle-fill text-info';
              }
              echo "<i class=\"$icon_class\"></i> " . htmlspecialchars($result);
              ?>
            </td>
            <td><?= htmlspecialchars($l['event_name']??'—') ?></td>
            <td style="font-size:.78rem;color:var(--text3);"><?= htmlspecialchars($l['ip_address']??'—') ?></td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <?php
    $base_url = "index.php?page=logs" . ($q?"&q=".urlencode($q):'') . ($type?"&type=".urlencode($type):'');
    echo render_pagination($pg, $pages, $base_url);
  ?>
  <?php endif; ?>
</div>
