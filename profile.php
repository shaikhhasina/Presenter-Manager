<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle  = 'My Profile';
$activePage = '';

$me = current_user();
$id = (int)$me['id'];

$stmt = db()->prepare(
    'SELECT u.*, c.name AS chapter_name FROM users u LEFT JOIN chapters c ON c.id = u.chapter_id WHERE u.id = :id LIMIT 1'
);
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $errors  = [];

    if (!password_verify($current, $row['password_hash'])) {
        $errors[] = 'Your current password is not correct.';
    }
    if ($problem = password_problem($new)) {
        $errors[] = $problem;
    } elseif ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    } elseif ($new === $current) {
        $errors[] = 'Please choose a password different from the current one.';
    }

    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
            ->execute(['h' => password_hash($new, PASSWORD_DEFAULT), 'id' => $id]);
        session_regenerate_id(true);
        audit_log('password_changed', 'user', $id, (string)$me['email']);
        flash('success', 'Your password has been changed.');
        redirect('profile.php');
    }

    foreach ($errors as $err) {
        flash('danger', $err);
    }
}

require __DIR__ . '/includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card stat-card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Your account</h2>
                <dl class="mb-0">
                    <dt>Name</dt><dd><?= e($row['full_name']) ?></dd>
                    <dt>Email</dt><dd><?= e($row['email']) ?></dd>
                    <dt>Role</dt><dd><?= e(role_label($row['role'])) ?></dd>
                    <dt>Chapter</dt><dd><?= $row['chapter_name'] ? e($row['chapter_name']) : 'All chapters' ?></dd>
                    <dt>Last login</dt><dd class="mb-0"><?= $row['last_login_at'] ? e(date('d M Y, h:i A', strtotime($row['last_login_at']))) : '—' ?></dd>
                </dl>
                <p class="small text-muted mt-3 mb-0">To change your name or email, ask a Super Admin.</p>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <form method="post" class="card stat-card" novalidate>
            <div class="card-body">
                <?= csrf_field() ?>
                <h2 class="h6 fw-bold mb-3">Change password</h2>
                <div class="mb-3">
                    <label class="form-label" for="current_password">Current password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="new_password">New password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password" required>
                    <div class="form-text">Minimum 10 characters, with letters and numbers.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="confirm_password">Confirm new password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-primary">Change password</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
