<?php
// pages/forms.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $a = $_REQUEST['action'] ?? '';
    if ($a === 'embed') {
        verify_csrf();
        $eid   = (int)($_POST['event_id']  ?? 0);
        $title = trim($_POST['form_title'] ?? '');
        $url   = trim($_POST['form_url']   ?? '');
        if ($eid && $title && $url) {
            db_execute("INSERT INTO event_forms (event_id,form_title,form_url,created_by) VALUES (?,?,?,?)",
                [$eid,$title,$url,$_SESSION['admin_id']]);
            flash('success','Form embedded successfully!');
        }
        header('Location: index.php?page=forms'); exit;
    }
    if ($a === 'toggle') {
        verify_csrf();
        $id  = (int)$_POST['id'];
        $row = db_row("SELECT form_status FROM event_forms WHERE id=?",[$id]);
        $new = $row['form_status']==='active' ? 'inactive' : 'active';
        db_execute("UPDATE event_forms SET form_status=? WHERE id=?", [$new, $id]);
        flash('success', 'Form status updated to ' . $new . '.');
        header('Location: index.php?page=forms'); exit;
    }
    if ($a === 'delete') {
        verify_csrf();
        db_execute("DELETE FROM event_forms WHERE id=?",[(int)$_POST['id']]);
        flash('success','Form removed.'); header('Location: index.php?page=forms'); exit;
    }
    if ($a === 'sync_attendees') {
        verify_csrf();
        $form_id = (int)($_POST['id'] ?? 0);
        $csv_url = trim($_POST['csv_url'] ?? '');

        // Determine sync mode. Auto-refresh is add-only, manual is full sync.
        $is_add_only_sync = isset($_POST['add_only']) && $_POST['add_only'] === '1';

        // If a new or updated URL is provided, validate and save it.
        if (isset($_POST['csv_url'])) { // This check ensures it's a submission from the modal
            if (filter_var($csv_url, FILTER_VALIDATE_URL)) {
                db_execute("UPDATE event_forms SET sheet_csv_url=? WHERE id=?",
                    [$csv_url, $form_id]);
            } else {
                flash('error', 'The provided URL is not a valid URL. Please save a valid URL first.');
                header('Location: index.php?page=forms'); exit;
            }
        }

        $form = db_row("SELECT f.*, e.name as event_name, e.event_date, e.event_time, e.venue FROM event_forms f JOIN events e ON e.id = f.event_id WHERE f.id = ?", [$form_id]);

        // Use the URL from the form (which might have just been updated)
        $sync_url = $form['sheet_csv_url'] ?? '';

        if (!$form || !filter_var($sync_url, FILTER_VALIDATE_URL)) {
            flash('error', 'No valid Google Sheet CSV URL is configured for this form. Please save a URL in the sync settings.');
            header('Location: index.php?page=forms'); exit;
        }

        // Append a cache-busting parameter. This may help bypass some caches,
        // though Google's own caching on published sheets is aggressive.
        $sync_url .= (strpos($sync_url, '?') === false ? '?' : '&') . '_t=' . time();

        // Pre-flight check for Composer dependencies to prevent fatal errors.
        if (!class_exists(\Endroid\QrCode\QrCode::class)) {
            flash('error', "Server Configuration Error: The QR Code library is missing. Please run 'composer install'.");
            header('Location: index.php?page=forms'); exit;
        }

        $event_id = $form['event_id'];

        // Fetch and process the CSV
        try {
            $csv_data = @file_get_contents($sync_url);
            if ($csv_data === false) throw new Exception("Could not fetch data from the Google Sheet URL. Check if the URL is correct and published.");

            if (substr($csv_data, 0, 3) === "\xEF\xBB\xBF") $csv_data = substr($csv_data, 3);

            $lines = explode(PHP_EOL, $csv_data);
            if (count($lines) < 1 || empty(trim($lines[0]))) throw new Exception("The CSV file is empty or invalid.");

            $raw_headers = str_getcsv(array_shift($lines));
            $headers = array_map(function($h) { return strtolower(trim($h)); }, $raw_headers);

            // --- Flexible Name and Email Parsing Keys ---
            // This list is now more comprehensive to automatically detect more column name variations.
            $possible_name_keys = [
                'full'  => ['full name', 'name', 'pangalan', 'buong pangalan', 'complete name', 'name of attendee'],
                'first' => ['firstname', 'first name', 'unang pangalan', 'given name', 'first'],
                'last'  => ['lastname', 'last name', 'apelyido', 'surname', 'family name', 'last']
            ];

            $possible_email_keys = ['email address', 'email', 'gmail address'];
            $find_value = function(array $keys, array $data): ?string {
                foreach ($keys as $key) {
                    if (isset($data[$key]) && !empty(trim($data[$key]))) return trim($data[$key]);
                }
                return null;
            };

            // --- Step 1: Collect all valid emails from the Google Sheet ---
            $sheet_emails = [];
            $sheet_rows_by_email = [];
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $csv_row = str_getcsv($line);
                if (count($headers) !== count($csv_row)) continue;
                $row = array_combine($headers, $csv_row);
                $email = $find_value($possible_email_keys, $row);
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $sheet_emails[] = $email;
                    $sheet_rows_by_email[$email] = $row;
                }
            }

            // --- Step 2: Get all existing attendee emails from the database for this event ---
            $db_attendees = db_query("SELECT id, email FROM attendees WHERE event_id = ?", [$event_id]);
            $db_emails_by_id = array_column($db_attendees, 'email', 'id');

            // --- Step 3: Determine who to add and who to delete ---
            $emails_to_add = array_diff($sheet_emails, $db_emails_by_id);
            $emails_to_delete = array_diff($db_emails_by_id, $sheet_emails);

            $deleted_count = 0;

            // --- Step 4: Process Deletions ---
            if (!$is_add_only_sync && !empty($emails_to_delete)) { // Only delete on full sync
                $ids_to_delete = array_keys(array_intersect($db_emails_by_id, $emails_to_delete));
                if (!empty($ids_to_delete)) {
                    $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
                    db_execute("DELETE FROM attendees WHERE id IN ($placeholders)", $ids_to_delete);
                    $deleted_count = count($ids_to_delete);
                }
            }

            $synced_count = 0;
            $email_success_count = 0;
            $email_fail_count = 0;

            // --- Step 5: Process Additions ---
            foreach ($emails_to_add as $email) {
                $row = $sheet_rows_by_email[$email];

                // --- Step 5a: Parse name from the row data ---
                $firstName = $find_value($possible_name_keys['first'], $row);
                $lastName = $find_value($possible_name_keys['last'], $row);
                $name = null; // Initialize name

                if ($firstName && $lastName) {
                    $name = $lastName . ', ' . $firstName;
                } else {
                    $name = $find_value($possible_name_keys['full'], $row);
                }

                // --- Step 5b: Smarter Fallback Logic ---
                // If no name was found after checking columns, try to derive it from the email address.
                if (empty($name) || $name === 'N/A') {
                    $email_prefix = strstr($email, '@', true);
                    // Replace common separators with spaces, capitalize words, and assign as name.
                    $name = $email_prefix ? ucwords(str_replace(['.', '_', '-'], ' ', $email_prefix)) : 'N/A';
                }
                $timestamp = $row['timestamp'] ?? date('Y-m-d H:i:s');

                // Create unique IDs and insert new attendee
                $respondent_id = 'GS-' . hash('crc32b', $email . $timestamp);
                $qr_code_id    = 'QR-' . strtoupper(substr(md5($email . $event_id), 0, 12));
                db_execute(
                    "INSERT INTO attendees (event_id, respondent_id, full_name, email, qr_code_id, registration_at, email_sent, checkin_status) VALUES (?, ?, ?, ?, ?, ?, 0, 'pending')",
                    [$event_id, $respondent_id, $name, $email, $qr_code_id, date('Y-m-d H:i:s', strtotime($timestamp))]
                );
                $attendee_id = db_last_insert_id();
                $synced_count++;

                // Send QR code email
                $attendee_data = ['id' => $attendee_id, 'full_name' => $name, 'email' => $email, 'respondent_id' => $respondent_id, 'qr_code_id' => $qr_code_id];
                if (send_qr_code_email($attendee_data, $form) === true) {
                    db_execute("UPDATE attendees SET email_sent=1, email_sent_at=NOW() WHERE id=?", [$attendee_id]);
                    $email_success_count++;
                } else {
                    $email_fail_count++;
                }
            }

            // --- Step 6: Update total response count and prepare message ---
            $new_total_attendees = db_count("SELECT COUNT(*) FROM attendees WHERE event_id = ?", [$event_id]);
            db_execute("UPDATE event_forms SET responses = ? WHERE id = ?", [$new_total_attendees, $form_id]);

            $skipped_count = count($sheet_emails) - $synced_count;
            $message = "Sync complete! Added: $synced_count. Deleted: $deleted_count. Skipped: $skipped_count (already exist).";
            if ($email_fail_count > 0) $message .= " Check email_errors.log for details.";

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                send_json_response([
                    'success' => true,
                    'message' => $message,
                    'synced_count' => $synced_count,
                    'deleted_count' => $deleted_count,
                    'new_total_responses' => (int)$new_total_attendees,
                    'form_id' => (int)$form_id
                ]);
            }

            flash('success', $message);
        } catch (Exception $e) {
            $message = 'Sync failed: ' . $e->getMessage();
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                send_json_response(['success' => false, 'message' => $message, 'form_id' => (int)$form_id], 500);
            }
            flash('error', $message);
        }
        header('Location: index.php?page=forms'); exit;
    }
}

$events = db_query("SELECT id, name FROM events ORDER BY event_date DESC");

// --- Pagination ---
$items_per_page = 10;
$current_page = (int)($_GET['p'] ?? 1);
if ($current_page < 1) $current_page = 1;

$total_forms = db_count("SELECT COUNT(*) FROM event_forms");
$total_pages = ceil($total_forms / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages; // Go to last page if page number is too high
}
$offset = ($current_page - 1) * $items_per_page;

$forms  = db_query(
    "SELECT f.*, e.name as event_name FROM event_forms f JOIN events e ON e.id=f.event_id ORDER BY f.created_at DESC LIMIT ? OFFSET ?",
    [$items_per_page, $offset]
);

$prefill_event = (int)($_GET['event_id'] ?? 0);
?>
<div class="page-hero"><h1>Google Forms Integration</h1><p>Embed and manage Google Forms for event registration.</p></div>

<div class="grid-2">
  <!-- Embed Form -->
  <div class="card">
    <div class="card-header"><span>🔗</span><div class="card-title">Embed Google Form</div></div>
    <form method="POST" action="index.php?page=forms">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="embed">
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Select Event *</label>
          <select name="event_id" class="form-select" required>
            <option value="">Choose event...</option>
            <?php foreach ($events as $ev): ?>
              <option value="<?= $ev['id'] ?>" <?= $prefill_event==$ev['id']?'selected':'' ?>><?= htmlspecialchars($ev['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Form Title *</label>
          <input type="text" name="form_title" id="embed-form-title" class="form-input" placeholder="e.g. Tech Summit 2025 Registration" required>
        </div>
        <div class="form-group">
          <label class="form-label">Google Form URL *</label>
          <input type="url" name="form_url" id="form-url-input" class="form-input"
                 placeholder="https://docs.google.com/forms/d/e/..." required>
          <div class="form-hint">Paste the shareable form URL from Google Forms.</div>
        </div>
        <div style="display:flex;gap:10px;">
          <button type="button" class="btn btn-ghost" onclick="previewGoogleForm()"><i class="bi bi-eye"></i> Preview</button>
          <button type="submit" class="btn btn-primary" style="flex:1;"><i class="bi bi-link-45deg"></i> Embed &amp; Save</button>
        </div>
      </div>
    </form>
  </div>

  <!-- Preview -->
  <div class="card">
    <div class="card-header">
      <span><i class="bi bi-eye"></i></span><div class="card-title">Form Preview</div>
      <span class="badge badge-neutral" id="form-status-badge">Not Configured</span>
    </div>
    <div class="card-body" id="form-preview-area">
      <div class="empty-state">
        <div class="empty-icon"><i class="bi bi-file-earmark-text"></i></div>
        <div class="empty-text">Paste a Google Form URL and click Preview to validate it.</div>
      </div>
    </div>
  </div>
</div>

<!-- Forms Table -->
<div class="card">
  <div class="card-header"><span><i class="bi bi-table"></i></span><div class="card-title">Embedded Forms (<?= $total_forms ?>)</div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Form Title</th><th>Event</th><th>Status</th><th>Auto-Refresh</th><th>Responses</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($forms)): ?>
          <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">No forms embedded yet.</td></tr>
        <?php else: ?>
          <?php foreach ($forms as $f): ?>
          <tr>
            <td data-label="ID" style="color:var(--text3);font-size:.8rem;"><?= $f['id'] ?></td>
            <td data-label="Form Title" style="font-weight:500;color:var(--text);"><?= htmlspecialchars($f['form_title']) ?></td>
            <td data-label="Event"><?= htmlspecialchars($f['event_name']) ?></td>
            <td data-label="Status">
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                <label class="toggle" title="Toggle status">
                  <input type="checkbox" <?= $f['form_status']==='active'?'checked':'' ?> onchange="this.form.submit()">
                  <span class="toggle-slider"></span>
                </label>
              </form>
              <span class="badge <?= $f['form_status']==='active'?'badge-success':'badge-neutral' ?>" style="margin-left:8px;">
                <?= ucfirst($f['form_status']) ?>
              </span>
            </td>
            <td data-label="Auto-Refresh">
              <div style="display:flex; align-items:center; gap: 8px;">
              <?php if (empty($f['sheet_csv_url'])): ?>
                  <span class="text-muted text-xs" title="Set up sync from sheet to enable auto-refresh.">—</span>
              <?php else: ?>
                  <div class="auto-refresh-container" data-form-id="<?= $f['id'] ?>">
                      <span id="auto-refresh-status-<?= $f['id'] ?>" style="width:10px;"></span>
                  </div>
              <?php endif; ?>
              </div>
            </td>
            <td data-label="Responses" id="responses-cell-<?= $f['id'] ?>"><?= (int)$f['responses'] ?></td>
            <td data-label="Created"><?= date('M j, Y', strtotime($f['created_at'])) ?></td>
            <td data-label="Actions">
              <a href="<?= htmlspecialchars($f['form_url']) ?>" target="_blank" class="action-btn" title="Open Form"><i class="bi bi-box-arrow-up-right"></i></a>
              <a href="index.php?page=attendees&event_id=<?= $f['event_id'] ?>" class="action-btn" title="View Attendees"><i class="bi bi-people"></i></a>
              <form method="POST" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                <button type="button" class="action-btn" title="Remove Form" onclick="const deleteFormEl = this.closest('form'); confirm('Remove Form?', 'Are you sure you want to remove this embedded form? This does not delete the form in Google Forms.', () => { deleteFormEl.submit(); })"><i class="bi bi-trash"></i></button>
              </form>
              <?php if (!empty($f['sheet_csv_url'])): ?>
                <form method="POST" action="index.php?page=forms" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="sync_attendees">
                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                    <button type="button" class="action-btn" title="Perform Full Sync" onclick="const formEl = this.closest('form'); confirm('Perform Full Sync?', 'This will add new attendees AND remove any that are no longer on the sheet. This is the definitive way to match your sheet. Continue?', () => { formEl.submit(); }, false)"><i class="bi bi-arrow-clockwise"></i></button>
                </form>
                <button type="button" class="action-btn" title="Edit Sync Settings" onclick="openSyncModal(<?= $f['id'] ?>, '<?= htmlspecialchars($f['sheet_csv_url'] ?? '', ENT_QUOTES) ?>')"><i class="bi bi-gear"></i></button>
              <?php else: ?>
                <button type="button" class="action-btn" title="Set up Sync from Sheet" onclick="openSyncModal(<?= $f['id'] ?>, '')"><i class="bi bi-arrow-clockwise"></i></button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
    <?php
      $base_url = "index.php?page=forms";
      echo render_pagination($current_page, $total_pages, $base_url);
    ?>
  <?php endif; ?>
</div>

<!-- Sync Attendees Modal -->
<div class="modal-overlay" id="sync-modal">
  <div class="modal" style="max-width:500px;">
    <div class="modal-header">
      <h3 class="modal-title">Sync Settings</h3>
      <button class="modal-close" onclick="closeModal('sync-modal')"><i class="bi bi-x-lg"></i></button>
    </div>
    <form method="POST" action="index.php?page=forms" id="sync-form">
      <div class="modal-body">
        <p class="text-muted text-sm" style="margin-bottom:16px;">
          Publish your Google Sheet to the web as a CSV and paste the URL below.
        </p>
        <ol class="text-sm" style="margin-left:20px;margin-bottom:20px;line-height:1.6;">
          <li>In Google Sheets, go to <strong>File > Share > Publish to web</strong>.</li>
          <li>Under "Link", select your responses sheet.</li>
          <li>Choose <strong>Comma-separated values (.csv)</strong>.</li>
          <li>Click <strong>Publish</strong> and copy the generated URL.</li>
        </ol>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="sync_attendees">
        <input type="hidden" name="id" id="sync-form-id">
        <div class="form-group">
          <label class="form-label">Published Google Sheet CSV URL</label>
          <input type="url" name="csv_url" id="sync-csv-url" class="form-input" placeholder="https://docs.google.com/spreadsheets/d/e/.../pub?output=csv" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('sync-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="sync-start-btn"><i class="bi bi-save"></i> Save Settings & Sync</button>
      </div>
    </form>
  </div>
</div>

<script>
/* global apiPost, toast, openModal */

const autoRefreshIntervals = {};

function startAutoRefresh(formId) {
    const statusCell = document.getElementById(`auto-refresh-status-${formId}`);

    // If it's already running, don't start another one.
    if (autoRefreshIntervals[formId]) return;

    toast(`Auto-refresh is active for form #${formId}.`, 'info');
    if (statusCell) statusCell.innerHTML = '<span class="live-dot" title="Actively refreshing every 10 seconds"></span>';

    // Immediately run the sync once, but don't show toast unless there are updates
    runAjaxSync(formId, true);

    autoRefreshIntervals[formId] = setInterval(() => {
        runAjaxSync(formId, false); // Subsequent runs show toast on updates
    }, 10000); // 10 seconds
}

async function runAjaxSync(formId, isFirstRun = false) {
    try {
        // We use apiPost from app.js which is already loaded
        const data = await apiPost('index.php?page=forms', {
            action: 'sync_attendees',
            id: formId,
            add_only: '1' // Tell the backend this is an add-only sync
        });

        if (data.success) {
            const responseCell = document.getElementById(`responses-cell-${formId}`);
            if (responseCell) {
                const currentCount = parseInt(responseCell.textContent, 10);
                const newCount = data.new_total_responses;

                responseCell.textContent = newCount;

                if (data.synced_count > 0 || (data.deleted_count && data.deleted_count > 0)) {
                    // Flash effect
                    responseCell.style.transition = 'none';
                    responseCell.style.backgroundColor = 'rgba(255, 193, 7, 0.25)';
                    responseCell.style.transform = 'scale(1.1)';
                    setTimeout(() => {
                        responseCell.style.transition = 'all .5s ease';
                        responseCell.style.backgroundColor = '';
                        responseCell.style.transform = '';
                    }, 200);

                    let toastParts = [];
                    if (data.synced_count > 0) {
                        toastParts.push(`Synced ${data.synced_count} new`);
                    }
                    if (data.deleted_count > 0) {
                        toastParts.push(`removed ${data.deleted_count}`);
                    }
                    toast(`${toastParts.join(' and ')} attendee(s) for form #${formId}.`, 'success');
                }
            }
        } else {
            toast(data.message || `Sync failed for form #${formId}.`, 'error');
            // The refresh will automatically try again on the next interval.
        }
    } catch (e) {
        toast(e.message || `Sync failed for form #${formId}.`, 'error');
        // The refresh will automatically try again on the next interval.
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.auto-refresh-container').forEach(container => {
        const formId = container.dataset.formId;
        if (formId) startAutoRefresh(formId);
    });
});

function openSyncModal(formId, sheetUrl = '') {
  document.getElementById('sync-form-id').value = formId;
  const urlInput = document.getElementById('sync-csv-url');
  urlInput.value = sheetUrl;

  const syncBtn = document.getElementById('sync-start-btn');
  if (sheetUrl) {
    syncBtn.innerHTML = '<i class="bi bi-save"></i> Save Settings & Sync';
  } else {
    syncBtn.innerHTML = '<i class="bi bi-save"></i> Save Settings & Sync';
  }

  openModal('sync-modal');
}
</script>
