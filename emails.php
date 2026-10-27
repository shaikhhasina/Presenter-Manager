<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle  = 'Email Center';
$activePage = 'emails';

$pdo   = db();
$user  = current_user();
$scope = chapter_scope_sql('m');   // Coordinators only see emails of their own chapter's members

$q         = trim((string)($_GET['q'] ?? ''));
$status    = (string)($_GET['status'] ?? '');
$type      = (string)($_GET['type'] ?? '');
$chapterId = is_super_admin() ? (int)($_GET['chapter_id'] ?? 0) : 0;
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;

$statuses = ['sent', 'failed', 'pending'];
$types    = ['monthly_reminder', 'thirty_day_reminder', 'followup', 'overdue_admin', 'manual_resend', 'test'];
if (!in_array($status, $statuses, true)) {
    $status = '';
}
if (!in_array($type, $types, true)) {
    $type = '';
}

$where  = [$scope['sql']];
$params = $scope['params'];

if ($chapterId > 0) {
    $where[] = 'm.chapter_id = :chapter_id';
    $params['chapter_id'] = $chapterId;
}
if ($status !== '') {
    $where[] = 'e.status = :status';
    $params['status'] = $status;
}
if ($type !== '') {
    $where[] = 'e.email_type = :type';
    $params['type'] = $type;
}
if ($q !== '') {
    $where[] = '(e.recipient_email LIKE :q1 OR e.subject LIKE :q2 OR m.full_name LIKE :q3)';
    $like = like_contains($q);
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

$whereSql = implode(' AND ', $where);
$from = 'FROM email_logs e LEFT JOIN members m ON m.id = e.member_id';

// ---- Totals (ignore the filters, respect the chapter scope) ----
$totStmt = $pdo->prepare("SELECT e.status, COUNT(*) AS c {$from} WHERE {$scope['sql']} GROUP BY e.status");
$totStmt->execute($scope['params']);
$totals = ['sent' => 0, 'failed' => 0, 'pending' => 0];
foreach ($totStmt->fetchAll() as $row) {
    if (isset($totals[$row['status']])) {
        $totals[$row['status']] = (int)$row['c'];
    }
}

// ---- List ----
$countStmt = $pdo->prepare("SELECT COUNT(*) {$from} WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    "SELECT e.id, e.member_id, e.recipient_email, e.email_type, e.subject, e.status, e.error_message,
            e.attempts, e.sent_at, e.created_at, m.full_name AS member_name, m.member_code
     {$from}
     WHERE {$whereSql}
     ORDER BY e.id DESC
     LIMIT {$perPage} OFFSET {$offset}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$chapters = is_super_admin() ? fetch_active_chapters() : [];

$keep = array_filter([
    'q'          => $q,
    'status'     => $status,
    'type'       => $type,
    'chapter_id' => $chapterId > 0 ? $chapterId : '',
]);

require __DIR__ . '/includes/header.php';
?>

<?php if (!mail_configured()): ?>
    <div class="alert alert-warning">
        <strong>Email sending is not switched on yet.</strong>
        Sender account: <code><?= e(mail_sender_address()) ?></code>. Gmail needs an <em>App Password</em>:
        <ol class="mb-0 mt-2">
            <li>Sign in to <?= e(mail_sender_address()) ?> &rarr; Google Account &rarr; Security &rarr; turn on <strong>2-Step Verification</strong>.</li>
            <li>Open <strong>myaccount.google.com/apppasswords</strong> and create one called "Presenter Manager".</li>
            <li>Paste the 16-character code into <code>MAIL_PASSWORD</code> in <code>config/mail.php</code>, save, and refresh this page.</li>
        </ol>
    </div>
<?php else: ?>
    <div class="alert alert-light border small mb-3">
        <i class="bi bi-envelope-check text-success"></i>
        Emails are sent from <strong><?= e(mail_sender_address()) ?></strong> through <?= e(MAIL_HOST) ?>.
        Automatic reminders run daily from the scheduled job (see README, Module 6).
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach ([['Sent', $totals['sent'], 'success', 'bi-envelope-check', 'sent'],
                    ['Failed', $totals['failed'], 'danger', 'bi-envelope-x', 'failed'],
                    ['Pending', $totals['pending'], 'warning', 'bi-hourglass-split', 'pending']] as [$label, $val, $color, $icon, $st]): ?>
        <div class="col-6 col-md-4">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-<?= e($color) ?>-subtle text-<?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                    <div>
                        <div class="stat-value"><?= (int)$val ?></div>
                        <a class="text-muted small text-decoration-none" href="<?= e(url('emails.php?status=' . $st)) ?>"><?= e($label) ?></a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if (is_super_admin()): ?>
<div class="card stat-card mb-3">
    <div class="card-body">
        <h2 class="h6 fw-bold mb-3">Tools</h2>
        <div class="row g-3">
            <div class="col-lg-6">
                <form method="post" action="<?= e(url('email_action.php')) ?>" class="d-flex flex-wrap gap-2 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="test">
                    <div class="flex-grow-1">
                        <label class="form-label small mb-1" for="test_to">Send a test email to</label>
                        <input type="email" class="form-control" id="test_to" name="to" maxlength="190" required
                               value="<?= e($user['email']) ?>">
                    </div>
                    <button type="submit" class="btn btn-outline-primary" <?= mail_configured() ? '' : 'disabled' ?>>
                        <i class="bi bi-send"></i> Send test
                    </button>
                </form>
            </div>
            <div class="col-lg-6">
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" action="<?= e(url('email_action.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="preview">
                        <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-eye"></i> Check what is waiting</button>
                    </form>
                    <form method="post" action="<?= e(url('email_action.php')) ?>"
                          onsubmit="return confirm('Send all due reminder emails to members now?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="run_now">
                        <button type="submit" class="btn btn-primary" <?= mail_configured() ? '' : 'disabled' ?>>
                            <i class="bi bi-lightning-charge"></i> Send due reminders now
                        </button>
                    </form>
                    <?php if ($totals['failed'] > 0): ?>
                    <form method="post" action="<?= e(url('email_action.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="retry_all">
                        <button type="submit" class="btn btn-outline-danger" <?= mail_configured() ? '' : 'disabled' ?>>
                            <i class="bi bi-arrow-repeat"></i> Retry all failed
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="small text-muted mt-2">
                    Each reminder is only ever sent once per member and cycle, so pressing the button twice is safe.
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<form method="get" class="card stat-card mb-3">
    <div class="card-body row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small mb-1" for="q">Search</label>
            <input type="text" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Member, email or subject">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All</option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="type">Type</label>
            <select class="form-select" id="type" name="type">
                <option value="">All types</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= e($t) ?>" <?= $type === $t ? 'selected' : '' ?>><?= e(email_type_label($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (is_super_admin()): ?>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="chapter_id">Chapter</label>
            <select class="form-select" id="chapter_id" name="chapter_id">
                <option value="">All chapters</option>
                <?php foreach ($chapters as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $chapterId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?= e(url('emails.php')) ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </div>
</form>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Recipient</th>
                    <th>Type</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No emails found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-nowrap small">
                        <?= e(date('d M Y', strtotime((string)($r['sent_at'] ?: $r['created_at'])))) ?><br>
                        <span class="text-muted"><?= e(date('h:i A', strtotime((string)($r['sent_at'] ?: $r['created_at'])))) ?></span>
                    </td>
                    <td>
                        <?php if ($r['member_id']): ?>
                            <a class="text-decoration-none fw-semibold" href="<?= e(url('member_view.php?id=' . (int)$r['member_id'])) ?>"><?= e($r['member_name']) ?></a><br>
                        <?php endif; ?>
                        <span class="small text-muted"><?= e($r['recipient_email']) ?></span>
                    </td>
                    <td><span class="badge text-bg-light border"><?= e(email_type_label($r['email_type'])) ?></span></td>
                    <td class="small"><?= e($r['subject']) ?></td>
                    <td>
                        <?= email_status_badge($r['status']) ?>
                        <?php if ($r['status'] === 'failed' && $r['error_message']): ?>
                            <div class="small text-danger mt-1" style="max-width:260px"><?= e(mb_substr($r['error_message'], 0, 160)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('email_view.php?id=' . (int)$r['id'])) ?>">View</a>
                        <?php if ($r['status'] === 'failed'): ?>
                            <form method="post" action="<?= e(url('email_action.php')) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="retry">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" <?= mail_configured() ? '' : 'disabled' ?>>Retry</button>
                            </form>
                        <?php elseif ($r['status'] === 'sent'): ?>
                            <form method="post" action="<?= e(url('email_action.php')) ?>" class="d-inline"
                                  onsubmit="return confirm('Send this email again?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="resend">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary" <?= mail_configured() ? '' : 'disabled' ?>>Resend</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3" aria-label="Email pages">
    <ul class="pagination justify-content-center mb-0">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(url('emails.php?' . query_with($keep, 'page', $i))) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
