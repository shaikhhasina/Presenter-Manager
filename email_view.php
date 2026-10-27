<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id  = (int)($_GET['id'] ?? 0);
$log = $id > 0 ? load_email_log($id) : null;

if (!$log) {
    http_response_code(404);
    exit('Email not found, or you do not have permission to view it.');
}

$pageTitle  = 'Email #' . (int)$log['id'];
$activePage = 'emails';

require __DIR__ . '/includes/header.php';
?>

<div class="mb-3">
    <a href="<?= e(url('emails.php')) ?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Email Center</a>
</div>

<div class="card stat-card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <h2 class="h5 mb-0"><?= e($log['subject']) ?></h2>
            <?= email_status_badge($log['status']) ?>
        </div>
        <dl class="row mb-0 member-dl">
            <dt class="col-sm-3">To</dt>
            <dd class="col-sm-9">
                <?= e($log['recipient_email']) ?>
                <?php if ($log['member_id']): ?>
                    (<a href="<?= e(url('member_view.php?id=' . (int)$log['member_id'])) ?>"><?= e($log['member_name']) ?>, <?= e($log['member_code']) ?></a>)
                <?php endif; ?>
            </dd>
            <dt class="col-sm-3">Type</dt>
            <dd class="col-sm-9"><?= e(email_type_label($log['email_type'])) ?></dd>
            <dt class="col-sm-3">Created</dt>
            <dd class="col-sm-9"><?= e(date('d M Y, h:i A', strtotime((string)$log['created_at']))) ?></dd>
            <dt class="col-sm-3">Sent</dt>
            <dd class="col-sm-9"><?= $log['sent_at'] ? e(date('d M Y, h:i A', strtotime((string)$log['sent_at']))) : '—' ?></dd>
            <dt class="col-sm-3">Attempts</dt>
            <dd class="col-sm-9"><?= (int)$log['attempts'] ?></dd>
            <dt class="col-sm-3">Triggered by</dt>
            <dd class="col-sm-9"><?= e($log['sent_by_name'] ?: 'Automatic (scheduled job)') ?></dd>
            <?php if ($log['error_message']): ?>
                <dt class="col-sm-3">Last error</dt>
                <dd class="col-sm-9 text-danger"><?= e($log['error_message']) ?></dd>
            <?php endif; ?>
        </dl>
    </div>
</div>

<div class="card stat-card">
    <div class="card-body">
        <h3 class="h6 fw-bold mb-3">Message</h3>
        <?php /* sandbox="" = no scripts, no forms, no links that act on this site */ ?>
        <iframe sandbox="" referrerpolicy="no-referrer" title="Email preview"
                style="width:100%;height:520px;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff"
                srcdoc="<?= e((string)$log['body']) ?>"></iframe>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
