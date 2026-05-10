<?php
// ============================================================
//  AttendQR — Configuration
// ============================================================

define('BASE_PATH', dirname(__DIR__));

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;
use Dotenv\Dotenv;

// The Composer autoloader is required for sending emails with PHPMailer.
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Load environment variables from .env file
if (class_exists(Dotenv::class) && file_exists(BASE_PATH . '/.env')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

define('APP_NAME',    ' UB AttendQR');
define('APP_VERSION', '1.0');
define('BASE_URL',    '');  // e.g. http://localhost/attendqr

// ---- Database ----
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'attendqr');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');

// ---- Email (SMTP) ----
define('MAIL_HOST',      $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com');
define('MAIL_PORT',      $_ENV['MAIL_PORT'] ?? 587);
define('MAIL_USERNAME',  $_ENV['MAIL_USERNAME'] ?? '');
define('MAIL_PASSWORD',  $_ENV['MAIL_PASSWORD'] ?? '');
define('MAIL_FROM',      $_ENV['MAIL_FROM'] ?? '');
define('MAIL_FROM_NAME', $_ENV['MAIL_FROM_NAME'] ?? 'AttendQR System');

// ---- DKIM (for better email deliverability) ----
// To generate DKIM keys, you can use a tool like: https://www.port25.com/dkim-wizard/
// The public key needs to be added as a TXT record to your DNS (e.g., for ub.edu.ph).
define('DKIM_DOMAIN', ''); // Your domain, e.g., ub.edu.ph
define('DKIM_SELECTOR', 'default'); // A selector, e.g., 'default' or 'phpmailer'
define('DKIM_PRIVATE_KEY', ''); // The full path to your private key file, e.g., '/var/www/dkim/private.key'
define('DKIM_PASSPHRASE', ''); // The passphrase for your private key, if you have one.

// ---- QR Code settings ----
define('QR_SIZE',   300);
define('QR_MARGIN', 10);

// ---- Email Debugging ----
define('EMAIL_DEBUG_MODE', false); // Set to true to display emails on screen instead of sending.

// ---- Webhook secret (Google Forms integration) ----
// This should be a long, random, and unpredictable string to secure your webhook endpoint.
define('WEBHOOK_SECRET', 'whsec_9aKjLpWn3RzXvYtSgVbQfGjHnMbQeThWmYq3t6w9z$C&F)J@NcRf');

// ---- Session timeout (seconds) ----
define('SESSION_TIMEOUT', 3600);

// ---- Timezone ----
date_default_timezone_set('Asia/Manila');

// ---- Logging ----
// Email errors are now logged to the `email_logs` database table.

// Secure session cookie settings
session_set_cookie_params(
    SESSION_TIMEOUT,
    '/',
    '', // domain
    isset($_SERVER['HTTPS']), // secure
    true // httponly
);

session_start();

// Session timeout logic. This should run on every request for a logged-in user.
if (isset($_SESSION['admin_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        // Last request was more than SESSION_TIMEOUT ago, destroy the session.
        session_unset();
        session_destroy();
        // The subsequent requireLogin() call will catch that the user is no longer logged in
        // and will handle the redirect or AJAX 401 response appropriately.
    } else {
        $_SESSION['last_activity'] = time(); // Update last activity time stamp on each request.
    }
}

// ============================================================
//  Helper Functions
// ============================================================

// Redirect to login if not authenticated
function requireLogin() {
    if (!isset($_SESSION['admin_id'])) {
        // If this is an AJAX request, send a 401 Unauthorized response instead of redirecting.
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(401);
            die(json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']));
        } else {
            // For normal page loads, redirect to the login page.
            header('Location: index.php?page=login');
            exit;
        }
    }
}

/**
 * Sends a JSON response and terminates the script.
 * Cleans any output buffers to prevent corrupting the JSON output.
 *
 * @param array $data The data to encode as JSON.
 * @param int $http_code The HTTP status code to send.
 */
function send_json_response(array $data, $http_code = 200) {
    // Clear any previously buffered output (like PHP notices)
    if (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code($http_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// CSRF token helpers
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    // Use hash_equals for timing-attack-safe string comparison.
    $token_ok = isset($_POST['csrf_token']) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);

    if (!$token_ok) {
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

        // Determine a more helpful error message. A missing session ID suggests a timeout.
        $message = !isset($_SESSION['admin_id'])
            ? 'Your session has expired. Please refresh the page and try again.'
            : 'Security token mismatch. Your request could not be verified.';

        if ($is_ajax) {
            header('Content-Type: application/json');
            // If session expired, 401 is more appropriate. Otherwise, 403 Forbidden for a CSRF failure.
            http_response_code(!isset($_SESSION['admin_id']) ? 401 : 403);
            die(json_encode(['success' => false, 'message' => $message]));
        } else {
            // For regular form submissions, handle session expiry by redirecting to login.
            if (!isset($_SESSION['admin_id'])) {
                flash('info', $message); // Use 'info' flash for non-error messages
                $redirect_url = 'index.php?page=login';
            } else {
                flash('error', $message);
                $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php?page=dashboard';
            }
            header('Location: ' . $redirect_url);
            exit;
        }
    }
}

// Flash messages
function flash($key, $msg = null) {
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
    } else {
        $val = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $val;
    }
}

/**
 * Formats a timestamp into a human-readable "time ago" string.
 * @param string $datetime The timestamp to format.
 * @param bool $full If true, returns all time units.
 * @return string The formatted time string, e.g., "5 minutes ago".
 */
function time_elapsed_string($datetime, $full = false) {
    try {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        // To avoid IDE warnings about adding a dynamic 'w' property to the DateInterval object,
        // we'll calculate the parts and store them in a separate array.
        $time_parts = [
            'y' => $diff->y,
            'm' => $diff->m,
            'w' => floor($diff->d / 7),
            'd' => $diff->d % 7, // Use modulo for the remaining days
            'h' => $diff->h,
            'i' => $diff->i,
            's' => $diff->s,
        ];

        $string = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
        foreach ($string as $k => &$v) {
            if ($time_parts[$k]) {
                $v = $time_parts[$k] . ' ' . $v . ($time_parts[$k] > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    } catch (Exception $e) {
        return $datetime; // Fallback to original datetime on error
    }
}

// Email sending helper
function send_email($to, $subject, $body, $alt_body = '', $attachment = null) {
    if (EMAIL_DEBUG_MODE === true) {
        // In debug mode, display the email on screen instead of sending it.
        $debug_output = "
            <div class='card' style='margin-top:20px; border:2px solid var(--accent);'>
                <div class='card-header' style='background:rgba(255,193,7,0.1);'>
                    <span style='font-size:1.2rem;'>📧</span>
                    <div class='card-title'>Email Debug Preview (Not Sent)</div>
                </div>
                <div class='card-body'>
                    <p><strong>To:</strong> " . htmlspecialchars($to) . "</p>
                    <p><strong>From:</strong> " . htmlspecialchars(MAIL_FROM_NAME . ' <' . MAIL_FROM . '>') . "</p>
                    <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                    <hr style='border-color:var(--border); margin: 16px 0;'>
                    <div>" . $body . "</div>
                </div>
            </div>
        ";
        flash('email_debug', $debug_output);
        return true; // Pretend it was sent successfully.
    }
    // Add a check for the OpenSSL extension, which is required for SMTP over TLS/SSL.
    if (!extension_loaded('openssl')) {
        $error_message = "Server Configuration Error: The 'openssl' PHP extension is not enabled, which is required for sending emails securely.";
        return $error_message;
    }

    // Prevent fatal error if Composer dependencies are not installed.
    if (!class_exists(PHPMailer::class)) {
        $error_message = "PHPMailer library not found. Please run 'composer install'.";
        return $error_message;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->setLanguage('en'); // Use English for more detailed error messages
        $mail->CharSet = PHPMailer::CHARSET_UTF8; // Use UTF-8 encoding

        // Embed logos
        $ub_logo_path = BASE_PATH . '/[full-color]-UB-Master-Logo.png';
        if (file_exists($ub_logo_path)) {
            $mail->addEmbeddedImage($ub_logo_path, 'ub_logo');
        }
        $anniversary_logo_path = BASE_PATH . '/80th logo.png';
        if (file_exists($anniversary_logo_path)) {
            $mail->addEmbeddedImage($anniversary_logo_path, '80th_logo');
        }

        // For deep debugging, uncomment the following line to see the full SMTP transaction.
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        // Enable TLS encryption; `PHPMailer::ENCRYPTION_STARTTLS` is the modern recommended value.
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        // --- DKIM Configuration ---
        if (defined('DKIM_DOMAIN') && !empty(DKIM_DOMAIN) &&
            defined('DKIM_SELECTOR') && !empty(DKIM_SELECTOR) &&
            defined('DKIM_PRIVATE_KEY') && !empty(DKIM_PRIVATE_KEY) &&
            file_exists(DKIM_PRIVATE_KEY)) {

            $mail->DKIM_domain = DKIM_DOMAIN;
            $mail->DKIM_selector = DKIM_SELECTOR;
            $mail->DKIM_private = DKIM_PRIVATE_KEY;
            $mail->DKIM_passphrase = DKIM_PASSPHRASE;
            $mail->DKIM_identity = $mail->From;
        }

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // Generate a unique Message-ID. This can help with deliverability and prevent threading issues.
        // A well-formed Message-ID makes the email look more professional to mail servers.
        $mail->MessageID = sprintf(
            "<%s.%s@%s>",
            base_convert(microtime(true), 10, 36),
            bin2hex(random_bytes(8)),
            'attendqr.system' // A pseudo-domain for the app
        );

        // Set email priority to high.
        // Note: This is a suggestion to mail clients and does not guarantee inbox placement.
        // Sender reputation (SPF, DKIM) is far more important.
        $mail->Priority = 1;

        $mail->isHTML(true);

        // Handle attachment
        if (is_array($attachment) && isset($attachment['string'], $attachment['name'])) {
            $mail->addStringAttachment(
                $attachment['string'],
                $attachment['name']
            );
        }

        $mail->Subject = $subject;
        $mail->Body = $body;
        if (!empty($alt_body)) {
            $mail->AltBody = $alt_body; // Add plain-text version for email clients
        }

        if ($mail->send()) {
            // Log successful email to the database
            db_execute("INSERT INTO email_logs (recipient, subject, body_html, status) VALUES (?, ?, ?, 'sent')", [$to, $subject, $body]);
            return true;
        }
        // This part is unlikely to be reached if exceptions are on, but as a fallback.
        $error_message = "An unknown error occurred with PHPMailer.";
        db_execute("INSERT INTO email_logs (recipient, subject, body_html, status, error_message) VALUES (?, ?, ?, 'failed', ?)", [$to, $subject, $body, $error_message]);
        return $error_message;
    } catch (Throwable $e) {
        // Catching Throwable is more robust than just Exception in PHP 7+
        $error_message = "Mailer Error: " . $e->getMessage();

        // Log the detailed error to the database.
        db_execute(
            "INSERT INTO email_logs (recipient, subject, body_html, status, error_message) VALUES (?, ?, ?, 'failed', ?)",
            [$to, $subject, $body, $e->getMessage()]
        );

        return $error_message;
    }
}

/**
 * Renders a reusable pagination control.
 * @param int $current_page The current active page.
 * @param int $total_pages The total number of pages.
 * @param string $base_url The base URL for page links (without the '&p=' part).
 * @return string The generated HTML for the pagination control.
 */
function render_pagination($current_page, $total_pages, $base_url) {
    if ($total_pages <= 1) {
        return '';
    }

    $html = '<div class="pagination">';

    // Previous button
    $html .= $current_page > 1
        ? '<a href="' . $base_url . '&p=' . ($current_page - 1) . '" class="page-btn"><i class="bi bi-chevron-left"></i> Previous</a>'
        : '<span class="page-btn" style="opacity:0.5;cursor:not-allowed;"><i class="bi bi-chevron-left"></i> Previous</span>';

    // Page numbers with ellipsis
    $links = []; $window = 1;
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == 1 || $i == $total_pages || ($i >= $current_page - $window && $i <= $current_page + $window)) $links[$i] = $i;
    }
    $last = 0;
    foreach ($links as $k => $v) {
        if ($k > $last + 1) $html .= '<span class="page-btn" style="border:none;background:none;">...</span>';
        $active_class = ($v == $current_page) ? 'active' : '';
        $html .= "<a href='{$base_url}&p={$v}' class='page-btn {$active_class}'>{$v}</a>";
        $last = $k;
    }

    // Next button
    $html .= $current_page < $total_pages
        ? '<a href="' . $base_url . '&p=' . ($current_page + 1) . '" class="page-btn">Next <i class="bi bi-chevron-right"></i></a>'
        : '<span class="page-btn" style="opacity:0.5;cursor:not-allowed;">Next <i class="bi bi-chevron-right"></i></span>';

    return $html . '</div>';
}

/**
 * Wraps email content in a branded HTML template.
 *
 * @param string $content The HTML content of the email body.
 * @param string $title The title of the email.
 * @return string The full HTML email document.
 */
function create_email_template($content, $title = 'InnovEd 2026') {
    $year = date('Y');
    // Using heredoc for readability. Inline styles are required for email client compatibility.
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="dark">
    <meta name="supported-color-schemes" content="dark">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700;800&display=swap');
        :root { color-scheme: dark; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #862334; font-family: 'Poppins', sans-serif; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td style="padding: 20px 10px;">
                <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; border-collapse: collapse; background-color: #FFFFFF; border-radius: 16px; border: 1px solid #eeeeee; overflow: hidden;">
                    <tr>
                        <td style="padding: 30px 20px; border-bottom: 1px solid #eeeeee; background-color: #FFFFFF;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td width="50%" align="left" valign="middle">
                                        <img src="cid:80th_logo" alt="80th Anniversary Logo" style="max-width: 150px; height: auto; display: block;">
                                    </td>
                                    <td width="50%" align="right" valign="middle">
                                        <img src="cid:ub_logo" alt="University of Batangas Logo" style="max-width: 250px; height: auto; display: block;">
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr><td style="padding: 35px 30px; color: #333333; font-size: 16px; line-height: 1.6;">$content</td></tr>
                    <tr><td style="padding: 30px; text-align: center; font-size: 12px; color: #666666; border-top: 1px solid #eeeeee;"><p style="margin: 0;">&copy; $year University of Batangas. All rights reserved.</p><p style="margin: 5px 0 0;">This is an automated message. Please do not reply.</p></td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Generates a QR code and sends it as an embedded image in an email.
 * This is a high-level wrapper for easier use across the application.
 *
 * @param array $attendee The attendee's data (id, full_name, email, respondent_id).
 * @param array $event The event's data (name).
 * @return bool|string True on success, or an error message string on failure.
 */
function send_qr_code_email(array $attendee, array $event)
{
    // Pre-flight check for Composer dependencies.
    if (!class_exists(\Endroid\QrCode\QrCode::class) || !class_exists(PHPMailer::class)) {
        $error = "Server Configuration Error: Required libraries (PHPMailer/QR-Code) are missing. Please run 'composer install'.";
        error_log($error); // Log this critical error.
        return $error;
    }

    try {
        // 1. Generate QR code data
        $qr_json_data = json_encode([
            'rid'   => $attendee['respondent_id'],
            'name'  => $attendee['full_name'],
            'email' => $attendee['email'],
            'event' => $event['name'],
            'ts'    => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z')
        ]);

        // 2. Create QR code image as a raw string
        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $qr_code = \Endroid\QrCode\QrCode::create($qr_json_data)
            ->setErrorCorrectionLevel(\Endroid\QrCode\ErrorCorrectionLevel::Quartile)
            ->setSize(QR_SIZE)
            ->setMargin(QR_MARGIN);
        $qr_image_string = $writer->write($qr_code)->getString();

        // 3. Create email body content
        // Normalize event data keys that might come from different sources for robustness.
        $event_name = htmlspecialchars($event['name'] ?? $event['event_name'] ?? 'the event');
        $event_venue = htmlspecialchars($event['venue'] ?? 'To be announced');
        $event_date = $event['event_date'] ?? null;
        $event_time = $event['event_time'] ?? null;

        $subject = "Your QR Code for " . $event_name;

        // Format date and time for display
        $event_datetime_str = '';
        if (!empty($event_date)) {
            $date = new DateTime($event_date);
            $event_datetime_str = $date->format('F j, Y');
            if (!empty($event_time)) {
                $time = new DateTime($event_time);
                $event_datetime_str .= ' at ' . $time->format('g:i A');
            }
        }

        $content_html = "
            <h2 style='text-align: center; color: #cc7b00; font-size: 26px; font-weight: bold; margin-top: 5px; margin-bottom: 20px;'>Your Registration is Approved!</h2>
            <p style='margin-top:0;'>Hi " . htmlspecialchars($attendee['full_name']) . ",</p>
            <p>We are pleased to inform you that your registration for <strong>" . $event_name . "</strong> has been officially approved. We are excited to have you join us.</p>
            <div style='padding: 15px; background-color: rgba(255,255,255,0.05); border-radius: 10px; margin: 20px 0; border: 1px solid rgba(255,255,255,0.1);'>
                <h3 style='margin-top:0; margin-bottom: 12px; color: #cc7b00;'>Event Details:</h3>
                <p style='margin: 5px 0;'><strong>Date & Time:</strong> " . ($event_datetime_str ?: 'To be announced') . "</p>
                <p style='margin: 5px 0;'><strong>Venue:</strong> " . $event_venue . "</p>
                <p style='margin: 5px 0;'><strong>QR Code ID:</strong> " . (htmlspecialchars($attendee['qr_code_id'] ?? 'N/A')) . "</p>
            </div>
            <p style='font-size: 16px; color: #000000; border-left: 3px solid #ffc107; padding-left: 15px; margin-top: 20px;'>
                Kindly keep this email, or download or take a screenshot of the attached QR code, which will be required for attendance verification and event entry.
            </p>
            <p>We look forward to seeing you there!</p>
            <p style='margin-bottom:0;'>Regards,<br>The Event Committee</p>
        ";

        // Create a plain-text version of the email (AltBody). This is crucial for deliverability.
        $body_plain = "
Hi " . $attendee['full_name'] . ",\n\n" .
"We are pleased to inform you that your registration for " . $event_name . " has been officially approved. We are excited to have you join us.\n\n" .
"Event Details:\n" .
"Date & Time: " . ($event_datetime_str ?: 'To be announced') . "\n" .
"Venue: " . $event_venue . "\n" .
"QR Code ID: " . ($attendee['qr_code_id'] ?? 'N/A') . "\n\n" .
"Kindly keep this email, or download or take a screenshot of the attached QR code, which will be required for attendance verification and event entry.\n\n" .
"We look forward to seeing you!\n\n" .
"Regards,\nThe Event Committee
        ";

        // 4. Wrap content in the template and call the generic email sender with the attachment
        $body_html = create_email_template($content_html, $subject);
        return send_email($attendee['email'], $subject, $body_html, $body_plain, [
            'string' => $qr_image_string,
            'name'   => 'qr.png'
        ]);

    } catch (Exception $e) {
        $error_message = "QR Email Error: " . $e->getMessage();
        return $error_message;
    }
}