<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle  = 'Registrations';
$activePage = 'registrations';

$status = (string)($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $status = 'pending';
}

$scope  = chapter_scope_sql('r');
$stmt = db()->prepare(
    "SELECT r.*, c.name AS chapter_name, cat.name AS category_name
     FROM member_registrations r
     JOIN chapters c ON c.id = r.chapter_id
     JOIN categories cat ON cat.id = r.category_id
     WHERE r.status = :status AND {$scope['sql']}
     ORDER BY r.created_at DESC
     LIMIT 200"
);
$stmt->execute(['status' => $status] + $scope['params']);
$rows = $stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <ul class="nav nav-pills gap-1">
        <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $label): ?>
            <li class="nav-item">
                <a class="nav-link py-1 <?= $status === $k ? 'active' : 'bg-white border' ?>"
                   href="<?= e(url('registrations.php?status=' . $k)) ?>"><?= e($label) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="small">
        Public form link (share this):
        <a href="<?= e(url('register.php')) ?>" target="_blank"><code><?= e(url('register.php')) ?></code></a>
    </div>
</div>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Applicant</th><th>Chapter</th><th>Category</th><th>Last presented</th><th>Submitted</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No <?= e($status) ?> registrations.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($r['full_name']) ?> <?php if (!empty($r['member_code'])): ?><code class="small"><?= e($r['member_code']) ?></code><?php endif; ?></div>
                        <div class="small text-muted"><?= e($r['email']) ?> · <?= e($r['mobile']) ?></div>
                        <?php if ($r['remarks']): ?><div class="small text-muted fst-italic"><?= e($r['remarks']) ?></div><?php endif; ?>
                    </td>
                    <td><?= e($r['chapter_name']) ?></td>
                    <td><?= e($r['category_name']) ?></td>
                    <td><?= e(format_date($r['last_presentation_date'])) ?></td>
                    <td class="small"><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
                    <td class="text-end">
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" action="<?= e(url('registration_action.php')) ?>" class="d-inline"
                              onsubmit="return confirm('Approve and create this member?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-sm btn-success">Approve</button>
                        </form>
                        <form method="post" action="<?= e(url('registration_action.php')) ?>" class="d-inline-flex gap-1 mt-1 mt-xl-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="text" name="reason" class="form-control form-control-sm" style="width:130px"
                                   placeholder="Reason (optional)" maxlength="255">
                            <button class="btn btn-sm btn-outline-danger">Reject</button>
                        </form>
                    <?php elseif ($r['status'] === 'approved' && $r['member_id']): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('member_view.php?id=' . (int)$r['member_id'])) ?>">View member</a>
                    <?php elseif ($r['rejection_reason']): ?>
                        <span class="small text-muted"><?= e($r['rejection_reason']) ?></span>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
