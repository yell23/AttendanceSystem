<?php
// pages/attendees.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $a = $_REQUEST['action'] ?? '';
    if ($a === 'mark_no_show') {
        $event_id_to_update = (int)($_POST['event_id'] ?? 0);
        if ($event_id_to_update) {
            $updated_count = db_execute("UPDATE attendees SET checkin_status = 'no_show' WHERE event_id = ? AND checkin_status = 'pending'", [$event_id_to_update]);
            flash('success', "$updated_count attendees marked as 'No Show'.");
        } else {
            flash('error', 'No event specified for the update.');
        }
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php?page=attendees')); exit;
    }
}

$q      = trim($_GET['q']         ?? '');
$eid    = (int)($_GET['event_id'] ?? 0);
$status = $_GET['status']          ?? '';
$page   = max(1,(int)($_GET['p']  ?? 1));
$per    = 15;
$offset = ($page - 1) * $per;

$where  = "WHERE 1=1";
$params = [];
if ($q)      { $where .= " AND (a.full_name LIKE ? OR a.email LIKE ? OR a.qr_code_id LIKE ?)"; $params = array_merge($params,["%$q%","%$q%","%$q%"]); }
if ($eid)    { $where .= " AND a.event_id = ?";         $params[] = $eid; }
if ($status) { $where .= " AND a.checkin_status = ?";   $params[] = $status; }

$total = db_count("SELECT COUNT(*) FROM attendees a $where", $params);
$pages = (int)ceil($total / $per);

$attendees = db_query("SELECT a.*,e.name as event_name FROM attendees a
  JOIN events e ON e.id = a.event_id
  $where ORDER BY a.registration_at DESC LIMIT $per OFFSET $offset", $params);

$events = db_query("SELECT id,name FROM events ORDER BY event_date DESC");

// Get the event status if a specific event is filtered, to show the "Mark No Shows" button
$event_status = '';
if ($eid) {
    $event_details = db_row("SELECT status FROM events WHERE id = ?", [$eid]);
    $event_status = $event_details['status'] ?? '';
}
?>
<div class="page-hero-row">
  <div class="page-hero"><h1>Attendees</h1><p><?= number_format($total) ?> registrations found.</p></div>
  <div style="display:flex;gap:10px;">
    <?php if ($eid && $event_status === 'completed'): ?>
        <form method="POST" action="index.php?page=attendees" id="no-show-form" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_no_show">
            <input type="hidden" name="event_id" value="<?= $eid ?>">
            <button type="button" class="btn btn-warning" onclick="confirm('Mark as No Show?', 'This will mark all remaining PENDING attendees for this event as No Show. This is useful for accurate reporting. Continue?', () => { document.getElementById('no-show-form').submit(); }, false)">
                <i class="bi bi-person-x"></i> Mark No Shows
            </button>
        </form>
    <?php endif; ?>
    <a href="index.php?page=attendees&export=csv<?= $eid ? "&event_id=$eid" : '' ?>" class="btn btn-ghost"><i class="bi bi-download"></i> Export CSV</a>
    <a href="index.php?page=attendees&export=excel<?= $eid?"&event_id=$eid":'' ?>" class="btn btn-ghost"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
  </div>
</div>

<?php
// CSV export
if (isset($_GET['export'])) {
    $all = db_query("SELECT a.respondent_id,a.full_name,a.email,a.phone,e.name as event,a.registration_at,a.checkin_status,a.checkin_at,a.qr_code_id
        FROM attendees a JOIN events e ON e.id=a.event_id $where ORDER BY a.registration_at DESC", $params);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendees_'.date('Ymd').'.csv"');
    $fp = fopen('php://output','w');
    fputcsv($fp,['Respondent ID','Full Name','Email','Phone','Event','Registration Time','Status','Check-in Time','QR Code ID']);
    foreach ($all as $r) fputcsv($fp,array_values($r));
    fclose($fp); exit;
}
?>

<div class="card">
  <div class="card-header">
    <form method="GET" action="index.php" class="filter-bar">
      <input type="hidden" name="page" value="attendees">
      <div class="search-bar">
        <span class="search-icon"><i class="bi bi-search"></i></span>
        <input type="text" name="q" placeholder="Search name, email, QR..." value="<?= htmlspecialchars($q) ?>">
      </div>
      <select name="event_id" class="form-select" onchange="this.form.submit()">
        <option value="">All Events</option>
        <?php foreach ($events as $ev): ?>
          <option value="<?= $ev['id'] ?>" <?= $eid==$ev['id']?'selected':'' ?>><?= htmlspecialchars($ev['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-select" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="checked_in" <?= $status==='checked_in'?'selected':'' ?>>Checked In</option>
        <option value="pending"    <?= $status==='pending'   ?'selected':'' ?>>Pending</option>
        <option value="no_show"    <?= $status==='no_show'   ?'selected':'' ?>>No Show</option>
      </select>
      <button type="submit" class="btn btn-ghost btn-sm"><i class="bi bi-search"></i></button>
      <?php if ($q||$eid||$status): ?>
        <a href="index.php?page=attendees" class="btn btn-ghost btn-sm"><i class="bi bi-x"></i> Clear</a>
      <?php endif; ?>
    </form>
    <div style="margin-left:auto;font-size:.8rem;color:var(--text3);">
      Showing <?= count($attendees) ?> of <?= number_format($total) ?>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Event</th>
          <th>Registered</th>
          <th>Check-in</th>
          <th>Status</th>
          <th>Email Sent</th>
          <th>QR Code</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($attendees)): ?>
          <tr><td colspan="9" class="text-center text-muted" style="padding:40px;">No attendees found.</td></tr>
        <?php else: ?>
          <?php foreach ($attendees as $a):
            $initials = implode('',array_map(function($w){return strtoupper($w[0]);},array_slice(explode(' ',$a['full_name']),0,2)));
          ?>
          <tr id="attendee-row-<?= $a['id'] ?>">
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div class="avatar" style="background:linear-gradient(135deg,var(--accent),var(--accent2));font-size:.7rem;"><?= $initials ?></div>
                <div>
                  <div style="font-weight:500;color:var(--text);font-size:.875rem;"><?= htmlspecialchars($a['full_name']) ?></div>
                  <div style="font-size:.73rem;color:var(--text3);"><?= htmlspecialchars($a['respondent_id']) ?></div>
                </div>
              </div>
            </td>
            <td><?= htmlspecialchars($a['email']) ?></td>
            <td><?= htmlspecialchars($a['event_name']) ?></td>
            <td><?= date('M j, g:i A', strtotime($a['registration_at'])) ?></td>
            <td><?= $a['checkin_at'] ? date('M j, g:i A', strtotime($a['checkin_at'])) : '—' ?></td>
            <td>
              <?php
              switch($a['checkin_status']) {
                case 'checked_in':
                  echo '<span class="badge badge-success"><i class="bi bi-check-circle"></i> Checked In</span>';
                  break;
                case 'pending':
                  echo '<span class="badge badge-warning"><i class="bi bi-hourglass-split"></i> Pending</span>';
                  break;
                case 'no_show':
                  echo '<span class="badge badge-error"><i class="bi bi-x-circle"></i> No Show</span>';
                  break;
              }
              ?>
            </td>
            <td class="email-status-cell">
              <?= $a['email_sent']
                ? '<span class="badge badge-success"><i class="bi bi-check-circle"></i> Sent</span>'
                : '<span class="badge badge-neutral"><i class="bi bi-x-circle"></i> Pending</span>' ?>
            </td>
            <td><code><?= htmlspecialchars($a['qr_code_id']) ?></code></td>
            <td>
              <a href="index.php?page=qrcodes&prefill_id=<?= $a['id'] ?>" class="action-btn" title="View QR"><i class="bi bi-qr-code"></i></a>
              <button class="action-btn" title="Send QR Email" onclick='resendEmail(<?= json_encode([
                  "id" => $a["id"],
                  "name" => $a["full_name"],
                  "email" => $a["email"],
                  "rid" => $a["respondent_id"],
                  "event" => $a["event_name"]
              ], JSON_HEX_APOS | JSON_HEX_QUOT)
              ?>)'><i class="bi bi-envelope"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <?php
    $base_url = "index.php?page=attendees" . ($q?"&q=".urlencode($q):'') . ($eid?"&event_id=$eid":'') . ($status?"&status=$status":'');
    echo render_pagination($page, $pages, $base_url);
  ?>
  <?php endif; ?>
</div>
