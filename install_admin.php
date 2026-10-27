<?php
require_once __DIR__ . '/includes/bootstrap.php';

$pdo = db();

// Only works while the users table is EMPTY
if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    http_response_code(403);
    exit('Setup is already complete. Please delete install_admin.php from your project folder.');
}

$errors = [];
$old = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $fullName = trim((string)($_POST['full_name'] ?? ''));
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    $old = ['full_name' => $fullName, 'email' => $email];

    if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
        $errors[] = 'Full name must be 2 to 100 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 10 || strlen($password) > 72) {
        $errors[] = 'Password must be 10 to 72 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must contain at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Password and confirmation do not match.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, role)
                 VALUES (:name, :email, :hash, :role)'
            );
            $stmt->execute([
                'name'  => $fullName,
                'email' => $email,
                'hash'  => password_hash($password, PASSWORD_DEFAULT),
                'role'  => 'super_admin',
            ]);

            flash('success', 'Super Admin created. You can log in now. IMPORTANT: delete install_admin.php.');
            redirect('login.php');
        } catch (PDOException $ex) {
            error_log('install_admin error: ' . $ex->getMessage());
            $errors[] = 'Could not create the account. Check that you imported database/schema.sql.';
        }
    }
}

$pageTitle = 'Create Super Admin (one-time setup)';
require __DIR__ . '/includes/auth_header.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="" novalidate>
    <?= csrf_field() ?>

    <div class="mb-3">
        <label for="full_name" class="form-label">Full name</label>
        <input type="text" class="form-control" id="full_name" name="full_name"
               value="<?= e($old['full_name']) ?>" required>
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email"
               value="<?= e($old['email']) ?>" required>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password"
               autocomplete="new-password" required>
        <div class="form-text">Minimum 10 characters, with letters and numbers.</div>
    </div>

    <div class="mb-4">
        <label for="confirm_password" class="form-label">Confirm password</label>
        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
               autocomplete="new-password" required>
    </div>

    <button type="submit" class="btn btn-primary w-100">Create Super Admin</button>
</form>

<?php require __DIR__ . '/includes/auth_footer.php'; ?>
