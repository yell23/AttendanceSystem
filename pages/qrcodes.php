<?php
// pages/qrcodes.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Pre-flight check for Composer dependencies to prevent fatal errors.
    if (!class_exists(\Endroid\QrCode\QrCode::class)) {
        send_json_response([
            'success' => false,
            'message' => "Server Configuration Error: The QR Code library is missing. Please run 'composer install' in your project directory."
        ], 500);
    }

    verify_csrf();
    $a = $_REQUEST['action'] ?? '';

    if ($a === 'send_to_all_pending') {
        try {
            // Increase execution time and memory limit for this potentially long-running task.
            // The '@' suppresses errors if these functions are disabled in php.ini.
            @set_time_limit(300); // 5 minutes
            @ini_set('memory_limit', '256M');

            $event_id = (int)($_POST['event_id'] ?? 0);
            if (!$event_id) {
                send_json_response(['success' => false, 'message' => 'Event ID is required.']);
            }

            $event = db_row("SELECT name, event_date, event_time, venue FROM events WHERE id = ?", [$event_id]);
            if (!$event) {
                send_json_response(['success' => false, 'message' => 'Event not found.']);
            }

            $pending_attendees = db_query("SELECT * FROM attendees WHERE event_id = ? AND email_sent = 0", [$event_id]);

            if (empty($pending_attendees)) {
                send_json_response(['success' => true, 'message' => 'No pending emails to send for this event.']);
            }

            $success_count = 0;
            $fail_count = 0;

            foreach ($pending_attendees as $attendee) {
                // Use the new high-level function to generate and send the email.
                // The $attendee array already contains all the necessary info.
                // The $event array also has the event name.
                if (send_qr_code_email($attendee, $event) === true) {
                    db_execute("UPDATE attendees SET email_sent=1, email_sent_at=NOW() WHERE id=?", [$attendee['id']]);
                    $success_count++;
                } else {
                    $fail_count++;
                }
            }

            $message = "Bulk send complete. Sent: $success_count. Failed: $fail_count.";
            if ($fail_count > 0) $message .= " Check email_errors.log for details.";
            send_json_response(['success' => true, 'message' => $message]);
        } catch (Exception $e) {
            send_json_response(['success' => false, 'message' => 'A server error occurred: ' . $e->getMessage()], 500);
        }
    }

    if ($a === 'send_email') {
        try {
            $rid = trim($_POST['respondent_id'] ?? '');

            // Data from the form, needed for creating a new attendee if they don't exist
            $post_name = trim($_POST['name'] ?? '');
            $post_email = trim($_POST['email'] ?? '');
            $post_event_id = (int)($_POST['event_id'] ?? 0);

            if (empty($rid)) {
                send_json_response(['success' => false, 'message' => 'Respondent ID is required.']);
            }

            $attendee = db_row("SELECT * FROM attendees WHERE respondent_id = ?", [$rid]);

            if (!$attendee) {
                // Attendee not found, assume it's a new one from the generator.
                // Validate required data for creation.
                if (empty($post_name) || empty($post_email) || empty($post_event_id)) {
                    send_json_response(['success' => false, 'message' => 'New attendee requires Name, Email, and Event to be specified.']);
                }
                if (!filter_var($post_email, FILTER_VALIDATE_EMAIL)) {
                    send_json_response(['success' => false, 'message' => 'Invalid email address provided for new attendee.']);
                }

                // Check for duplicates (email + event) before inserting
                $existing = db_count("SELECT COUNT(*) FROM attendees WHERE email = ? AND event_id = ?", [$post_email, $post_event_id]);
                if ($existing > 0) {
                    send_json_response(['success' => false, 'message' => 'An attendee with this email already exists for this event. Please check the logs.']);
                }

                // Create a new QR code ID for the new attendee
                $qr_code_id = 'QR-' . strtoupper(substr(md5($post_email . $post_event_id), 0, 12));

                db_execute(
                    "INSERT INTO attendees (event_id, respondent_id, full_name, email, qr_code_id, registration_at, checkin_status, email_sent) VALUES (?, ?, ?, ?, ?, NOW(), 'pending', 0)",
                    [$post_event_id, $rid, $post_name, $post_email, $qr_code_id]
                );
                $attendee_id = db_last_insert_id();
                // Re-fetch the complete record
                $attendee = db_row("SELECT * FROM attendees WHERE id = ?", [$attendee_id]);

                if (!$attendee) { // Should not happen
                    send_json_response(['success' => false, 'message' => 'Failed to create and retrieve new attendee record.']);
                }
            }

            // By this point, $attendee is a valid record, either pre-existing or newly created.
            $event = db_row("SELECT name, event_date, event_time, venue FROM events WHERE id = ?", [$attendee['event_id']]);
            if (!$event) {
                // This can happen if an event is deleted but the attendee record remains
                send_json_response(['success' => false, 'message' => 'Associated event not found for this attendee. Cannot send email.']);
            }

            // Use the new high-level function to generate and send the email.
            // This works for both new and existing attendees.
            // The server will always generate the QR code to ensure consistency.
            $email_result = send_qr_code_email($attendee, $event);

            if ($email_result === true) {
                db_execute("UPDATE attendees SET email_sent=1, email_sent_at=NOW() WHERE id=?", [$attendee['id']]);
                send_json_response([
                    'success' => true,
                    'message' => 'Email sent successfully to ' . htmlspecialchars($attendee['email']),
                    'attendee_id' => $attendee['id']
                ]);
            } else {
                $error_message = 'Failed to send email. ' . $email_result;
                if (strpos($email_result, 'Could not authenticate') !== false) {
                    $error_message = 'Failed to send email: SMTP Authentication Failed. Please verify your email credentials in the configuration. If using Gmail, ensure you are using an App Password.';
                }
                send_json_response(['success' => false, 'message' => $error_message]);
            }
        } catch (Exception $e) {
            // Catch any unexpected errors (like PDOExceptions) and return a proper JSON response.
            send_json_response(['success' => false, 'message' => 'A server error occurred: ' . $e->getMessage()], 500);
        }
    }
}

$events = db_query("SELECT id, name FROM events ORDER BY event_date DESC");

// Get event_id from URL to filter the log
$filter_event_id = (int) ($_GET['event_id'] ?? 0);
$pg   = max(1, (int)($_GET['p'] ?? 1));
$per  = 20; // Items per page
$off  = ($pg - 1) * $per;

$where_sql = "";
$params = [];

if ($filter_event_id) {
    $where_sql = " WHERE a.event_id = ?";
    $params[] = $filter_event_id;
}

$total = db_count("SELECT COUNT(*) FROM attendees a" . $where_sql, $params);
$pages = (int)ceil($total / $per);

$qr_logs = db_query("SELECT a.*, e.name as event_name FROM attendees a JOIN events e ON e.id = a.event_id {$where_sql} ORDER BY a.registration_at DESC LIMIT $per OFFSET $off", $params);

$prefill = null;
if (isset($_GET['prefill_id'])) {
    $prefill = db_row("SELECT a.*,e.name as event_name FROM attendees a JOIN events e ON e.id=a.event_id WHERE a.id=?",[(int)$_GET['prefill_id']]);
}
?>
<div class="page-hero"><h1>QR Code Generator</h1><p>Generate, preview, and send QR codes to attendees.</p></div>

<div class="grid-2">
  <!-- Generator -->
  <div class="card">
    <div class="card-header"><span><i class="bi bi-qr-code"></i></span><div class="card-title">Generate QR Code</div></div>
    <div class="card-body">
      <div class="form-group">
        <label class="form-label">Select Event</label>
        <select class="form-select" id="qr-event">
          <option value="">-- Select & Filter --</option>
          <?php foreach ($events as $ev): ?>
            <option value="<?= $ev['id'] ?>"
              <?= (($prefill && $prefill['event_id'] == $ev['id']) || (!$prefill && $filter_event_id == $ev['id'])) ? 'selected' : '' ?>>
              <?= htmlspecialchars($ev['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Attendee Name</label>
          <input type="text" class="form-input" id="qr-name"
            value="<?= htmlspecialchars($prefill['full_name']??'') ?>" placeholder="Juan dela Cruz">
        </div>
        <div class="form-group">
          <label class="form-label">Gmail Address</label>
          <input type="email" class="form-input" id="qr-email"
            value="<?= htmlspecialchars($prefill['email']??'') ?>" placeholder="juan@gmail.com">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Respondent ID</label>
        <input type="text" class="form-input" id="qr-rid" readonly style="opacity:.6;"
          value="<?= htmlspecialchars($prefill['respondent_id']??'RID-'.strtoupper(substr(md5(uniqid()),0,8))) ?>">
      </div>
      <div style="display:flex;gap:10px;">
        <button class="btn btn-primary" onclick="generateQR()" style="flex:1;"><i class="bi bi-qr-code"></i> Generate</button>
        <button class="btn btn-ghost" onclick="sendQREmail()"><i class="bi bi-envelope"></i> Send Email</button>
        <button class="btn btn-ghost" onclick="downloadQR()" id="qr-download-btn" disabled><i class="bi bi-download"></i> PNG</button>
      </div>
    </div>
  </div>

  <!-- Preview -->
  <div class="card">
    <div class="card-header"><span><i class="bi bi-eye"></i></span><div class="card-title">QR Preview</div></div>
    <div class="card-body">
      <div class="qr-preview-container">
        <div id="qr-output">
          <div style="width:180px;height:180px;background:var(--surface3);border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--text3);font-size:3rem;"><i class="bi bi-qr-code"></i></div>
        </div>
        <div class="qr-info">
          <div class="qr-name" id="qr-preview-name">—</div>
          <div class="qr-meta" id="qr-preview-meta">Fill in the form to generate a QR code</div>
        </div>
        <p class="text-xs text-muted" style="text-align:center;max-width:240px;">
          QR code encodes: Respondent ID, Name, Event, Email, Timestamp
        </p>
      </div>
    </div>
  </div>
</div>

<!-- QR Log -->
<div class="card">
  <div class="card-header">
    <span><i class="bi bi-list-ul"></i></span><div class="card-title">Generated QR Codes (<?= number_format($total) ?>)</div>
    <?php if ($filter_event_id): ?>
      <button class="btn btn-primary btn-sm" style="margin-left:auto;margin-right:10px;" onclick="sendToAllPending(<?= $filter_event_id ?>)"><i class="bi bi-envelope"></i> Send to All Pending</button>
      <a href="index.php?page=qrcodes" class="btn btn-ghost btn-sm"><i class="bi bi-x"></i> Clear Filter</a>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Respondent</th><th>Event</th><th>QR Code ID</th><th>Generated At</th><th>Email Sent</th><th>Scan Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($qr_logs as $q): ?>
        <tr id="qr-log-row-<?= $q['id'] ?>">
          <td>
            <div style="font-weight:500;color:var(--text);"><?= htmlspecialchars($q['full_name']) ?></div>
            <div style="font-size:.73rem;color:var(--text3);"><?= htmlspecialchars($q['email']) ?></div>
          </td>
          <td><?= htmlspecialchars($q['event_name']) ?></td>
          <td><code><?= htmlspecialchars($q['qr_code_id']) ?></code></td>
          <td><?= date('M j, g:i A', strtotime($q['registration_at'])) ?></td>
          <td class="email-status-cell">
            <?= $q['email_sent']
              ? '<span class="badge badge-success"><i class="bi bi-check-circle"></i> Sent</span>'
              : '<span class="badge badge-neutral"><i class="bi bi-x-circle"></i> Pending</span>' ?>
          </td>
          <td>
            <?php
            switch($q['checkin_status']) {
              case 'checked_in':
                echo '<span class="badge badge-success"><i class="bi bi-check-circle"></i> Scanned</span>';
                break;
              case 'pending':
                echo '<span class="badge badge-warning"><i class="bi bi-hourglass-split"></i> Pending</span>';
                break;
              default:
                echo '<span class="badge badge-neutral">—</span>';
            }
            ?>
          </td>
          <td>
            <button class="action-btn" title="Regenerate QR"
              onclick="document.getElementById('qr-name').value='<?= htmlspecialchars(addslashes($q['full_name'])) ?>'; document.getElementById('qr-email').value='<?= htmlspecialchars(addslashes($q['email'])) ?>'; document.getElementById('qr-event').value='<?= $q['event_id'] ?>'; document.getElementById('qr-rid').value='<?= htmlspecialchars(addslashes($q['respondent_id'])) ?>'; generateQR();window.scrollTo(0,0);">
              <i class="bi bi-qr-code"></i>
            </button>
            <button class="action-btn" title="Resend Email" onclick='resendEmail(<?= json_encode([
                "id" => $q["id"],
                "name" => $q["full_name"],
                "email" => $q["email"],
                "rid" => $q["respondent_id"],
                "event" => $q["event_name"]
            ], JSON_HEX_APOS | JSON_HEX_QUOT)
            ?>)'><i class="bi bi-envelope"></i></button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <?php
      $base_url = "index.php?page=qrcodes" . ($filter_event_id ? "&event_id=" . $filter_event_id : '');
      echo render_pagination($pg, $pages, $base_url);
    ?>
  <?php endif; ?>
</div>
