<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['super_admin']);

$pageTitle  = 'Settings';
$activePage = 'settings';

$errors = [];
$s      = email_settings_all(true);

// Values shown in the form (replaced by the submitted values if validation fails)
$form = [
    'enable_monthly_reminders' => ($s['enable_monthly_reminders'] ?? '1') === '1',
    'enable_30day_reminders'   => ($s['enable_30day_reminders'] ?? '0') === '1',
    'enable_followup_emails'   => ($s['enable_followup_emails'] ?? '1') === '1',
    'followup_after_days'      => (string)($s['followup_after_days'] ?? '7'),
    'admin_alert_email'        => (string)($s['admin_alert_email'] ?? ''),
    'email_from_name'          => (string)($s['email_from_name'] ?? APP_NAME),
    'email_from_address'       => (string)($s['email_from_address'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $form['enable_monthly_reminders'] = isset($_POST['enable_monthly_reminders']);
    $form['enable_30day_reminders']   = isset($_POST['enable_30day_reminders']);
    $form['enable_followup_emails']   = isset($_POST['enable_followup_emails']);
    $form['followup_after_days']      = trim((string)($_POST['followup_after_days'] ?? ''));
    $form['admin_alert_email']        = trim((string)($_POST['admin_alert_email'] ?? ''));
    $form['email_from_name']          = trim(preg_replace('/[\x00-\x1F\x7F"\\\\]+/', ' ', (string)($_POST['email_from_name'] ?? '')) ?? '');
    $form['email_from_address']       = trim((string)($_POST['email_from_address'] ?? ''));

    if (!ctype_digit($form['followup_after_days']) || (int)$form['followup_after_days'] < 1 || (int)$form['followup_after_days'] > 60) {
        $errors[] = 'Follow-up delay must be a whole number from 1 to 60 days.';
    }
    foreach (['admin_alert_email' => 'Admin alert email', 'email_from_address' => 'Reply-to address'] as $key => $label) {
        if ($form[$key] !== '' && (!filter_var($form[$key], FILTER_VALIDATE_EMAIL) || mb_strlen($form[$key]) > 190)) {
            $errors[] = $label . ' is not a valid email address.';
        }
    }
    if ($form['email_from_name'] === '') {
        $form['email_from_name'] = APP_NAME;
    }
    if (mb_strlen($form['email_from_name']) > 100) {
        $errors[] = 'Sender name is too long (max 100 characters).';
    }

    if (!$errors) {
        $save = [
            'enable_monthly_reminders' => $form['enable_monthly_reminders'] ? '1' : '0',
            'enable_30day_reminders'   => $form['enable_30day_reminders'] ? '1' : '0',
            'enable_followup_emails'   => $form['enable_followup_emails'] ? '1' : '0',
            'followup_after_days'      => (string)(int)$form['followup_after_days'],
            'admin_alert_email'        => $form['admin_alert_email'],
            'email_from_name'          => $form['email_from_name'],
            'email_from_address'       => $form['email_from_address'],
        ];

        with_transaction(function () use ($save) {
            $stmt = db()->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v1)
                 ON DUPLICATE KEY UPDATE setting_value = :v2'
            );
            foreach ($save as $key => $value) {
                $stmt->execute(['k' => $key, 'v1' => $value, 'v2' => $value]);
            }
            return null;
        });

        flash('success', 'Settings saved.');
        redirect('settings.php');
    }

    foreach ($errors as $err) {
        flash('danger', $err);
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <form method="post" class="card stat-card">
            <div class="card-body">
                <?= csrf_field() ?>
                <h2 class="h6 fw-bold mb-3">Automatic emails</h2>

                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="m" name="enable_monthly_reminders" value="1"
                           <?= $form['enable_monthly_reminders'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="m">
                        <strong>Monthly reminder</strong><br>
                        <span class="small text-muted">Once a month, email members whose due date falls in that month.</span>
                    </label>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="t" name="enable_30day_reminders" value="1"
                           <?= $form['enable_30day_reminders'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="t">
                        <strong>30-day reminder</strong> <span class="badge text-bg-secondary">optional</span><br>
                        <span class="small text-muted">One early email when the due date is within the next 30 days.</span>
                    </label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="f" name="enable_followup_emails" value="1"
                           <?= $form['enable_followup_emails'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="f">
                        <strong>Follow-up</strong><br>
                        <span class="small text-muted">If date and venue are still unconfirmed after a reminder, send one follow-up.</span>
                    </label>
                </div>

                <div class="mb-4" style="max-width:260px">
                    <label class="form-label" for="followup_after_days">Send the follow-up after (days)</label>
                    <input type="number" class="form-control" id="followup_after_days" name="followup_after_days"
                           min="1" max="60" required value="<?= e($form['followup_after_days']) ?>">
                </div>

                <h2 class="h6 fw-bold mb-3">Sender &amp; alerts</h2>

                <div class="mb-3">
                    <label class="form-label" for="email_from_name">Sender name</label>
                    <input type="text" class="form-control" id="email_from_name" name="email_from_name" maxlength="100"
                           value="<?= e($form['email_from_name']) ?>">
                    <div class="form-text">Shown as the "From" name in the member's inbox.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email_from_address">Reply-to address (optional)</label>
                    <input type="email" class="form-control" id="email_from_address" name="email_from_address" maxlength="190"
                           value="<?= e($form['email_from_address']) ?>">
                    <div class="form-text">
                        Where member replies go. Leave empty to receive replies at <?= e(mail_sender_address()) ?>.
                        Gmail always sends <em>from</em> <?= e(mail_sender_address()) ?>.
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="admin_alert_email">Admin alert email (optional)</label>
                    <input type="email" class="form-control" id="admin_alert_email" name="admin_alert_email" maxlength="190"
                           value="<?= e($form['admin_alert_email']) ?>">
                    <div class="form-text">Receives one summary of all overdue members per week. Leave empty to switch it off.</div>
                </div>

                <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="card stat-card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Email connection</h2>
                <dl class="mb-2 small">
                    <dt>Sending account</dt>
                    <dd><?= e(mail_sender_address()) ?></dd>
                    <dt>Server</dt>
                    <dd><?= e(MAIL_HOST) ?>:<?= (int)MAIL_PORT ?> (<?= e(strtoupper(MAIL_SECURE)) ?>)</dd>
                    <dt>App Password</dt>
                    <dd>
                        <?php if (mail_configured()): ?>
                            <span class="badge text-bg-success">Added</span>
                        <?php else: ?>
                            <span class="badge text-bg-danger">Missing</span>
                        <?php endif; ?>
                    </dd>
                </dl>
                <p class="small text-muted mb-3">
                    The password is kept in <code>config/mail.php</code> on the server, never in the database or on this page.
                </p>
                <a href="<?= e(url('emails.php')) ?>" class="btn btn-outline-primary btn-sm">Open Email Center</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
