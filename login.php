<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        [$ok, $message] = attempt_login($email, $password);
        if ($ok) {
            audit_log('login', 'user', (int)current_user()['id'], $email);
            redirect('dashboard.php');
        }
        audit_log('login_failed', 'user', null, $email);
        $error = $message;
    }
}

// If no admin exists yet, show a link to the one-time setup page
$noUsersYet = ((int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);

$pageTitle = 'Admin Login';
require __DIR__ . '/includes/auth_header.php';
?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($noUsersYet): ?>
    <div class="alert alert-info">
        No admin account exists yet.
        <a href="<?= e(url('install_admin.php')) ?>" class="alert-link">Create the Super Admin</a>.
    </div>
<?php endif; ?>

<form method="post" action="" novalidate>
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control form-control-lg" id="email" name="email"
               value="<?= e($email) ?>" autocomplete="username" required autofocus>
    </div>

    <div class="mb-4">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control form-control-lg" id="password" name="password"
               autocomplete="current-password" required>
    </div>

    <button type="submit" class="btn btn-primary btn-lg w-100">
        <i class="bi bi-box-arrow-in-right me-1"></i> Log in
    </button>
</form>

<p class="text-center small mt-4 mb-0">New member? <a href="<?= e(url('register.php')) ?>">Register here</a></p>

<?php require __DIR__ . '/includes/auth_footer.php'; ?>
