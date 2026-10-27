<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['super_admin']);

$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$isEdit = $id > 0;
$user   = $isEdit ? load_user($id) : null;

if ($isEdit && !$user) {
    http_response_code(404);
    exit('User not found.');
}

$me       = (int)current_user()['id'];
$isSelf   = $isEdit && $id === $me;
$chapters = fetch_active_chapters();
$errors   = [];

$form = [
    'full_name'  => $user['full_name'] ?? '',
    'email'      => $user['email'] ?? '',
    'role'       => $user['role'] ?? 'chapter_coordinator',
    'chapter_id' => (int)($user['chapter_id'] ?? 0),
];

// A temporary password created by "Reset password" is shown once, then forgotten.
$tempPassword = null;
if ($isEdit && isset($_SESSION['temp_password'][$id])) {
    $tempPassword = (string)$_SESSION['temp_password'][$id];
    unset($_SESSION['temp_password'][$id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Your own role is fixed here so you can never lock yourself out.
    $input = $_POST;
    if ($isSelf) {
        $input['role']       = $user['role'];
        $input['chapter_id'] = (string)($user['chapter_id'] ?? 0);
    }

    [$data, $errors] = validate_user_input($input, $isEdit ? $id : null);
    $form = $data;

    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm_password'] ?? '');

    if (!$isEdit) {
        if ($problem = password_problem($password)) {
            $errors[] = $problem;
        } elseif ($password !== $confirm) {
            $errors[] = 'Password and confirmation do not match.';
        }
    }

    // Do not remove the last active Super Admin.
    if ($isEdit && !$errors && $user['role'] === 'super_admin' && $data['role'] !== 'super_admin'
        && (int)$user['is_active'] === 1 && count_active_super_admins($id) === 0) {
        $errors[] = 'There must be at least one active Super Admin. Create another one before changing this role.';
    }

    if (!$errors) {
        $cid = $data['chapter_id'] > 0 ? $data['chapter_id'] : null;

        try {
            if ($isEdit) {
                db()->prepare(
                    'UPDATE users SET full_name = :n, email = :e, role = :r, chapter_id = :c WHERE id = :id'
                )->execute(['n' => $data['full_name'], 'e' => $data['email'], 'r' => $data['role'], 'c' => $cid, 'id' => $id]);

                $notes = array_filter([
                    change_note('Name', $user['full_name'], $data['full_name']),
                    change_note('Email', $user['email'], $data['email']),
                    change_note('Role', role_label($user['role']), role_label($data['role'])),
                    change_note('Chapter ID', $user['chapter_id'], $cid),
                ]);
                audit_log('user_updated', 'user', $id, $data['email'] . ($notes ? ' | ' . implode('; ', $notes) : ''));
                flash('success', 'User updated.');
                redirect('user_form.php?id=' . $id);
            }

            db()->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, chapter_id)
                 VALUES (:n, :e, :h, :r, :c)'
            )->execute([
                'n' => $data['full_name'], 'e' => $data['email'],
                'h' => password_hash($password, PASSWORD_DEFAULT),
                'r' => $data['role'], 'c' => $cid,
            ]);
            $newId = (int)db()->lastInsertId();
            audit_log('user_created', 'user', $newId, $data['email'] . ' as ' . role_label($data['role']));
            flash('success', 'User created. Share the login details with them securely, and ask them to change the password after the first login.');
            redirect('users.php');
        } catch (PDOException $ex) {
            error_log('user_form error: ' . $ex->getMessage());
            $errors[] = 'Could not save the user. Please try again.';
        }
    }

    foreach ($errors as $err) {
        flash('danger', $err);
    }
}

$pageTitle  = $isEdit ? 'Manage user' : 'Add user';
$activePage = 'users';
require __DIR__ . '/includes/header.php';
?>

<?php if ($tempPassword !== null): ?>
    <div class="alert alert-warning">
        <div class="fw-bold mb-1"><i class="bi bi-key me-1"></i> Temporary password (shown only once)</div>
        <code class="fs-5 user-select-all"><?= e($tempPassword) ?></code>
        <div class="small mt-2">Give it to <?= e($user['full_name']) ?> securely. They can change it from <strong>My Profile</strong> after logging in.</div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-7">
        <form method="post" class="card stat-card" novalidate>
            <div class="card-body">
                <?= csrf_field() ?>
                <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

                <div class="mb-3">
                    <label class="form-label" for="full_name">Full name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" maxlength="100" required
                           value="<?= e($form['full_name']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">Email (used to log in)</label>
                    <input type="email" class="form-control" id="email" name="email" maxlength="190" required
                           value="<?= e($form['email']) ?>">
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="role">Role</label>
                        <select class="form-select" id="role" name="role" <?= $isSelf ? 'disabled' : '' ?>>
                            <?php foreach (user_roles() as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $form['role'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isSelf): ?><div class="form-text">You cannot change your own role.</div><?php endif; ?>
                    </div>
                    <div class="col-md-6" id="chapterWrap">
                        <label class="form-label" for="chapter_id">Chapter</label>
                        <select class="form-select" id="chapter_id" name="chapter_id" <?= $isSelf ? 'disabled' : '' ?>>
                            <option value="">Select chapter</option>
                            <?php foreach ($chapters as $ch): ?>
                                <option value="<?= (int)$ch['id'] ?>" <?= (int)$form['chapter_id'] === (int)$ch['id'] ? 'selected' : '' ?>><?= e($ch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Coordinators only see and manage this chapter.</div>
                    </div>
                </div>

                <?php if (!$isEdit): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="confirm_password">Confirm password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                        </div>
                        <div class="col-12 d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="genPw"><i class="bi bi-magic me-1"></i>Generate</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="showPw">Show</button>
                            <span class="form-text mt-0">Minimum 10 characters, with letters and numbers.</span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create user' ?></button>
                    <a href="<?= e(url('users.php')) ?>" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </form>
    </div>

    <?php if ($isEdit): ?>
    <div class="col-lg-5">
        <div class="card stat-card mb-3">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Account</h2>
                <dl class="small mb-3">
                    <dt>Status</dt><dd><?= user_status_badge($user) ?></dd>
                    <dt>Last login</dt><dd><?= $user['last_login_at'] ? e(date('d M Y, h:i A', strtotime($user['last_login_at']))) : 'Never' ?></dd>
                    <dt>Created</dt><dd><?= e(date('d M Y', strtotime($user['created_at']))) ?></dd>
                    <dt>Members assigned</dt><dd><?= (int)$user['member_count'] ?></dd>
                </dl>

                <div class="d-grid gap-2">
                    <?php if (!empty($user['is_locked'])): ?>
                        <form method="post" action="<?= e(url('user_action.php')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="unlock">
                            <button class="btn btn-outline-warning w-100"><i class="bi bi-unlock me-1"></i>Unlock account</button>
                        </form>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url('user_action.php')) ?>"
                          onsubmit="return confirm('Create a new temporary password for this user? The old password will stop working.');">
                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="reset_password">
                        <button class="btn btn-outline-primary w-100"><i class="bi bi-key me-1"></i>Reset password</button>
                    </form>

                    <?php if (!$isSelf): ?>
                        <?php if ((int)$user['is_active'] === 1): ?>
                            <form method="post" action="<?= e(url('user_action.php')) ?>"
                                  onsubmit="return confirm('Deactivate this user? They will be logged out and cannot log in again until reactivated.');">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="deactivate">
                                <button class="btn btn-outline-danger w-100"><i class="bi bi-person-x me-1"></i>Deactivate user</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('user_action.php')) ?>">
                                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$id ?>"><input type="hidden" name="action" value="activate">
                                <button class="btn btn-outline-success w-100"><i class="bi bi-person-check me-1"></i>Reactivate user</button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <p class="small text-muted mt-3 mb-0">Users are never deleted, so history and member assignments stay intact.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var role = document.getElementById('role'), wrap = document.getElementById('chapterWrap');
    function sync() { if (role && wrap) { wrap.style.display = role.value === 'super_admin' ? 'none' : ''; } }
    if (role) { role.addEventListener('change', sync); sync(); }

    var gen = document.getElementById('genPw'), show = document.getElementById('showPw');
    var pw = document.getElementById('password'), cf = document.getElementById('confirm_password');
    if (gen && pw && cf) {
        gen.addEventListener('click', function () {
            var L = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ', D = '23456789', A = L + D;
            var buf = new Uint32Array(12); crypto.getRandomValues(buf);
            var out = [L[buf[0] % L.length], D[buf[1] % D.length]];
            for (var i = 2; i < 12; i++) { out.push(A[buf[i] % A.length]); }
            for (var j = out.length - 1; j > 0; j--) { var k = buf[j] % (j + 1); var t = out[j]; out[j] = out[k]; out[k] = t; }
            pw.value = cf.value = out.join('');
            pw.type = cf.type = 'text';
            show.textContent = 'Hide';
        });
        show.addEventListener('click', function () {
            var hide = pw.type === 'text';
            pw.type = cf.type = hide ? 'password' : 'text';
            show.textContent = hide ? 'Show' : 'Hide';
        });
    }
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
