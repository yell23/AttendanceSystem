<?php
// pages/settings.php

// Handle POST for sending a test email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $a = $_REQUEST['action'] ?? '';

    if ($a === 'send_test_email') {
        // Fetch the current admin's email from the database
        $admin = db_row("SELECT email FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
        $to_email = $admin['email'] ?? null;

        if ($to_email) {
            $subject = "✅ Test Email from AttendQR";
            $content_html = "<p style='margin-top:0;'>This is a test email to confirm your SMTP configuration is working correctly.</p>"
                          . "<p>If you received this message, your settings in <code>config.php</code> are correct!</p>"
                          . "<p style='margin-bottom:0;'>This is a great sign that your attendees will receive their QR codes.</p>";

            $body_plain = "This is a test email to confirm your SMTP configuration is working correctly. "
                        . "If you received this message, your settings in config.php are correct!";

            $body_html = create_email_template($content_html, $subject);

            $result = send_email($to_email, $subject, $body_html, $body_plain);

            if ($result === true) {
                flash('success', "Test email sent successfully to " . htmlspecialchars($to_email));
            } else {
                $error_message = "Failed to send test email. Error: " . htmlspecialchars($result);
                // Provide a more helpful message for the most common SMTP error.
                if (strpos($result, 'Could not authenticate') !== false) {
                    $error_message .= " | Troubleshooting Tip: This usually means your email credentials in includes/config.php are incorrect. If using Gmail, make sure you are using a 16-character App Password, not your regular password.";
                }
                flash('error', $error_message);
            }
        } else {
            flash('error', "Could not find your email address in the database.");
        }
        header('Location: index.php?page=settings');
        exit;
    }

    if ($a === 'change_password') {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $admin_id = $_SESSION['admin_id'];

        if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
            flash('error', 'All password fields are required.');
        } elseif (strlen($new_pass) < 8) {
            flash('error', 'New password must be at least 8 characters long.');
        } elseif ($new_pass !== $confirm_pass) {
            flash('error', 'New password and confirmation do not match.');
        } else {
            $admin = db_row("SELECT password FROM admins WHERE id = ?", [$admin_id]);

            if (!$admin || !password_verify($current_pass, $admin['password'])) {
                flash('error', 'Incorrect current password.');
            } else {
                // All checks passed, update the password
                $new_pass_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                db_execute("UPDATE admins SET password = ? WHERE id = ?", [$new_pass_hashed, $admin_id]);

                flash('success', 'Your password has been updated successfully.');
            }
        }
        header('Location: index.php?page=settings');
        exit;
    }

    if ($a === 'delete_database') {
        $db_name = DB_NAME;
        // This query is specific to MySQL/MariaDB.
        $tables_result = db_query("SELECT table_name FROM information_schema.tables WHERE table_schema = ?", [$db_name]);

        if (!empty($tables_result)) {
            db_execute("SET FOREIGN_KEY_CHECKS = 0;");
            foreach ($tables_result as $table) {
                // Do not truncate the admins table to preserve user accounts.
                if ($table['table_name'] === 'admins') {
                    continue;
                }
                // Use TRUNCATE to delete all data from the table but keep the structure.
                db_execute("TRUNCATE TABLE `".$table['table_name']."`");
            }
            db_execute("SET FOREIGN_KEY_CHECKS = 1;");
        }

        // Set a success message and redirect back to the settings page.
        // The user remains logged in.
        flash('success', 'All application data (events, attendees, logs, etc.) has been deleted. Admin accounts are preserved.');
        header('Location: index.php?page=settings');
        exit;
    }
}
?>
<div class="page-hero"><h1>Settings</h1><p>Configure system settings and verify integrations.</p></div>

<div class="card" style="max-width: 680px;">
    <div class="card-header">
        <span><i class="bi bi-envelope-at"></i></span>
        <div class="card-title">Email (SMTP) Configuration</div>
    </div>
    <div class="card-body">
        <p class="text-muted text-sm" style="margin-bottom: 20px;">
            These settings are defined in <code>includes/config.php</code> and are read-only. To send emails, you must configure your Gmail account with an App Password.
        </p>
        <div class="form-group"><label class="form-label">SMTP Host</label><input type="text" class="form-input" value="<?= MAIL_HOST ?>" readonly></div>
        <div class="form-group"><label class="form-label">SMTP Username</label><input type="text" class="form-input" value="<?= MAIL_USERNAME ?>" readonly></div>
        <div class="form-group"><label class="form-label">From Address</label><input type="text" class="form-input" value="<?= MAIL_FROM ?>" readonly></div>
    </div>
    <div class="card-footer" style="justify-content: space-between; align-items: center;">
        <p class="text-xs text-muted">A test email will be sent to your logged-in email address.</p>
        <form method="POST" action="index.php?page=settings">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_test_email">
            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> Send Test Email</button>
        </form>
    </div>
</div>

<div class="card" style="max-width: 680px; margin-top: 20px;">
    <div class="card-header">
        <span><i class="bi bi-key"></i></span>
        <div class="card-title">Change Password</div>
    </div>
    <form method="POST" action="index.php?page=settings">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-input" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-input" required minlength="8">
                    <div class="form-hint">Minimum 8 characters.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-input" required>
                </div>
            </div>
        </div>
        <div class="card-footer"><button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Update Password</button></div>
    </form>
</div>

<div class="card" style="max-width: 680px; margin-top: 20px; border-color: var(--danger);">
    <div class="card-header" style="background: rgba(255, 87, 87, 0.1);">
        <span><i class="bi bi-exclamation-octagon"></i></span>
        <div class="card-title">Danger Zone</div>
    </div>
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <h4 style="margin:0; font-size: 1rem; color: var(--text);">Delete All Data</h4>
                <p class="text-muted text-sm" style="margin-top: 5px; max-width: 450px;">
                    This will permanently delete all events, attendees, logs, and form data from the database. Admin accounts will <strong>not</strong> be deleted.
                    <br><strong>This action cannot be undone.</strong>
                </p>
            </div>
            <form method="POST" action="index.php?page=settings" id="delete-db-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_database">
                <button type="button" class="btn btn-danger" onclick="confirm('Delete All Application Data?', 'Are you sure you want to delete all data? This will remove all events, attendees, and logs, but will keep your admin account. This action cannot be undone.', () => { document.getElementById('delete-db-form').submit(); })">
                    <i class="bi bi-trash"></i> Delete All Data
                </button>
            </form>
        </div>
    </div>
</div>