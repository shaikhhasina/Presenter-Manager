<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('users.php');
}
csrf_verify();

$id     = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
$user   = $id > 0 ? load_user($id) : null;

if (!$user) {
    http_response_code(404);
    exit('User not found.');
}
if (!in_array($action, ['activate', 'deactivate', 'unlock', 'reset_password'], true)) {
    flash('danger', 'Unknown action.');
    redirect('user_form.php?id=' . $id);
}

$me = (int)current_user()['id'];

switch ($action) {
    case 'deactivate':
        if ($id === $me) {
            flash('danger', 'You cannot deactivate your own account.');
            break;
        }
        if ($user['role'] === 'super_admin' && count_active_super_admins($id) === 0) {
            flash('danger', 'You cannot deactivate the last active Super Admin.');
            break;
        }
        db()->prepare('UPDATE users SET is_active = 0 WHERE id = :id')->execute(['id' => $id]);
        audit_log('user_deactivated', 'user', $id, $user['email']);
        flash('success', 'User deactivated. They can no longer log in.');
        break;

    case 'activate':
        db()->prepare('UPDATE users SET is_active = 1, failed_attempts = 0, locked_until = NULL WHERE id = :id')->execute(['id' => $id]);
        audit_log('user_activated', 'user', $id, $user['email']);
        flash('success', 'User reactivated.');
        break;

    case 'unlock':
        db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = :id')->execute(['id' => $id]);
        audit_log('user_unlocked', 'user', $id, $user['email']);
        flash('success', 'Account unlocked.');
        break;

    case 'reset_password':
        $temp = generate_password(12);
        db()->prepare('UPDATE users SET password_hash = :h, failed_attempts = 0, locked_until = NULL WHERE id = :id')
            ->execute(['h' => password_hash($temp, PASSWORD_DEFAULT), 'id' => $id]);
        $_SESSION['temp_password'][$id] = $temp;
        audit_log('password_reset', 'user', $id, $user['email']);
        flash('success', 'Password reset. The temporary password is shown below (once).');
        break;
}

redirect('user_form.php?id=' . $id);
