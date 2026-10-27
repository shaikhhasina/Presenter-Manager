<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('emails.php');
}

csrf_verify();

$action = (string)($_POST['action'] ?? '');
$userId = (int)(current_user()['id'] ?? 0);

$superOnly = ['test', 'preview', 'run_now', 'retry_all'];
if (in_array($action, $superOnly, true) && !is_super_admin()) {
    http_response_code(403);
    exit('403 - Only the Super Admin can do this.');
}

// Everything except "preview" actually sends mail.
if ($action !== 'preview' && !mail_configured()) {
    flash('danger', 'Email is not set up yet: add the Gmail App Password in config/mail.php.');
    redirect($action === 'send_reminder' ? 'presentation.php?id=' . (int)($_POST['presentation_id'] ?? 0) : 'emails.php');
}

switch ($action) {
    // ---- Test email -------------------------------------------------------
    case 'test':
        $to = trim((string)($_POST['to'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || mb_strlen($to) > 190) {
            flash('danger', 'Please enter a valid email address.');
            redirect('emails.php');
        }

        $html = email_layout(
            'Test email',
            '<p style="margin:0 0 12px;">If you can read this, email sending works.</p>'
            . email_details_table([
                'Sent from' => mail_sender_address(),
                'Server'    => MAIL_HOST . ':' . MAIL_PORT,
                'Time'      => date('d M Y, h:i A'),
            ])
        );

        $id = email_log_insert([
            'recipient' => $to,
            'type'      => 'test',
            'subject'   => APP_NAME . ': test email',
            'body'      => $html,
            'sent_by'   => $userId,
        ]);
        $out = $id !== null ? email_deliver($id) : ['ok' => false, 'error' => 'Could not save the email record.'];

        flash($out['ok'] ? 'success' : 'danger', $out['ok']
            ? 'Test email sent to ' . $to . '. Check the inbox (and Spam).'
            : 'Test email failed: ' . $out['error']);
        redirect('emails.php');

    // ---- What is waiting? (nothing is sent) -------------------------------
    case 'preview':
        $res = run_email_reminders(null, true);
        $parts = [];
        foreach ($res['by_type'] as $t => $c) {
            if ($c['would_send'] > 0) {
                $parts[] = email_type_label($t) . ': ' . $c['would_send'];
            }
        }
        flash('info', $res['message'] . ($parts ? ' (' . implode(', ', $parts) . ')' : ''));
        redirect('emails.php');

    // ---- Run all due reminders now ---------------------------------------
    case 'run_now':
        $res = run_email_reminders($userId);
        $msg = $res['message'];
        if ($res['notes']) {
            $msg .= ' ' . implode(' ', $res['notes']);
        }
        flash($res['ok'] ? 'success' : 'warning', $msg);
        redirect('emails.php');

    // ---- Retry every failed email -----------------------------------------
    case 'retry_all':
        $ids = db()->query("SELECT id FROM email_logs WHERE status = 'failed' ORDER BY id LIMIT " . (int)MAIL_MAX_PER_RUN)
                   ->fetchAll(PDO::FETCH_COLUMN);
        $sent = 0;
        $failed = 0;
        $stopNote = '';
        foreach ($ids as $rid) {
            $out = email_deliver((int)$rid);
            if ($out['ok']) {
                $sent++;
            } else {
                $failed++;
                if ($out['fatal']) {
                    $stopNote = ' Stopped: ' . $out['error'];
                    break;
                }
            }
        }
        smtp_mailer()->close();
        flash($failed === 0 ? 'success' : 'warning', "Retry finished: {$sent} sent, {$failed} failed." . $stopNote);
        redirect('emails.php');

    // ---- Retry one failed email -------------------------------------------
    case 'retry':
        $log = load_email_log((int)($_POST['id'] ?? 0));
        if (!$log || $log['status'] !== 'failed') {
            flash('danger', 'That email cannot be retried.');
            redirect('emails.php');
        }
        $out = email_deliver((int)$log['id']);
        flash($out['ok'] ? 'success' : 'danger', $out['ok'] ? 'Email sent to ' . $log['recipient_email'] . '.' : 'Failed again: ' . $out['error']);
        redirect('emails.php');

    // ---- Send a copy of an email that was already sent --------------------
    case 'resend':
        $log = load_email_log((int)($_POST['id'] ?? 0));
        if (!$log || $log['status'] !== 'sent') {
            flash('danger', 'That email cannot be resent.');
            redirect('emails.php');
        }
        $newId = email_log_insert([
            'member_id'       => $log['member_id'] !== null ? (int)$log['member_id'] : null,
            'presentation_id' => $log['presentation_id'] !== null ? (int)$log['presentation_id'] : null,
            'recipient'       => $log['recipient_email'],
            'type'            => 'manual_resend',
            'subject'         => $log['subject'],
            'body'            => $log['body'],
            'sent_by'         => $userId,
        ]);
        $out = $newId !== null ? email_deliver($newId) : ['ok' => false, 'error' => 'Could not save the email record.'];
        flash($out['ok'] ? 'success' : 'danger', $out['ok'] ? 'Email sent again to ' . $log['recipient_email'] . '.' : 'Resend failed: ' . $out['error']);
        redirect('emails.php');

    // ---- "Send reminder now" button on the presentation page --------------
    case 'send_reminder':
        $pid = (int)($_POST['presentation_id'] ?? 0);
        $p   = $pid > 0 ? load_presentation($pid) : null;   // also checks chapter access
        if (!$p) {
            http_response_code(404);
            exit('Presentation not found, or you do not have permission to use it.');
        }

        $row = email_row_for_presentation($pid);   // null if closed, inactive member or no email
        if (!$row) {
            flash('danger', 'A reminder can only be sent for an open presentation of an active member who has an email address.');
            redirect('presentation.php?id=' . $pid);
        }

        $mail  = build_reminder_email($row, 'manual_resend');
        $newId = email_log_insert([
            'member_id'       => (int)$row['member_id'],
            'presentation_id' => $pid,
            'recipient'       => (string)$row['email'],
            'type'            => 'manual_resend',
            'subject'         => $mail['subject'],
            'body'            => $mail['html'],
            'sent_by'         => $userId,
        ]);
        $out = $newId !== null ? email_deliver($newId) : ['ok' => false, 'error' => 'Could not save the email record.'];
        flash($out['ok'] ? 'success' : 'danger', $out['ok']
            ? 'Reminder sent to ' . $row['email'] . '.'
            : 'Reminder failed: ' . $out['error']);
        redirect('presentation.php?id=' . $pid);

    default:
        flash('danger', 'Unknown action.');
        redirect('emails.php');
}
