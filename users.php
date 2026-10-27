<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['super_admin']);

$pageTitle  = 'Admin Users';
$activePage = 'users';

$q        = trim((string)($_GET['q'] ?? ''));
$role     = (string)($_GET['role'] ?? '');
$status   = (string)($_GET['status'] ?? '');
$chapterId = (int)($_GET['chapter_id'] ?? 0);
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;

if (!isset(user_roles()[$role])) { $role = ''; }
if (!in_array($status, ['active', 'inactive', 'locked'], true)) { $status = ''; }

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = '(u.full_name LIKE :q1 OR u.email LIKE :q2)';
    $params['q1'] = like_contains($q);
    $params['q2'] = like_contains($q);
}
if ($role !== '') {
    $where[] = 'u.role = :role';
    $params['role'] = $role;
}
if ($chapterId > 0) {
    $where[] = 'u.chapter_id = :cid';
    $params['cid'] = $chapterId;
}
if ($status === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($status === 'inactive') {
    $where[] = 'u.is_active = 0';
} elseif ($status === 'locked') {
    $where[] = 'u.is_active = 1 AND u.locked_until IS NOT NULL AND u.locked_until > NOW()';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare("SELECT COUNT(*) FROM users u $whereSql");
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page  = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare(
    "SELECT u.*, c.name AS chapter_name,
            (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked,
            (SELECT COUNT(*) FROM members m WHERE m.coordinator_id = u.id) AS member_count
     FROM users u
     LEFT JOIN chapters c ON c.id = u.chapter_id
     $whereSql
     ORDER BY u.is_active DESC, u.full_name
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$chapters = fetch_active_chapters();
$keep = array_filter(['q' => $q, 'role' => $role, 'status' => $status, 'chapter_id' => $chapterId ?: ''], fn($v) => $v !== '');
$me = (int)current_user()['id'];

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0"><?= (int)$total ?> user(s). Super Admins see everything; Chapter Coordinators only see their own chapter.</p>
    <a href="<?= e(url('user_form.php')) ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i> Add user</a>
</div>

<div class="card stat-card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Name or email">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="role">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All</option>
                    <?php foreach (user_roles() as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $role === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="chapter_id">Chapter</label>
                <select class="form-select" id="chapter_id" name="chapter_id">
                    <option value="">All</option>
                    <?php foreach ($chapters as $ch): ?>
                        <option value="<?= (int)$ch['id'] ?>" <?= $chapterId === (int)$ch['id'] ? 'selected' : '' ?>><?= e($ch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="locked" <?= $status === 'locked' ? 'selected' : '' ?>>Locked</option>
                </select>
            </div>
            <div class="col-6 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i> Filter</button>
                <a href="<?= e(url('users.php')) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Chapter</th>
                    <th>Members assigned</th>
                    <th>Last login</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$users): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No users match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="fw-semibold">
                            <?= e($u['full_name']) ?>
                            <?php if ((int)$u['id'] === $me): ?><span class="badge text-bg-light border ms-1">You</span><?php endif; ?>
                        </div>
                        <div class="small text-muted"><?= e($u['email']) ?></div>
                    </td>
                    <td><?= e(role_label($u['role'])) ?></td>
                    <td><?= $u['chapter_name'] ? e($u['chapter_name']) : '<span class="text-muted">All chapters</span>' ?></td>
                    <td><?= (int)$u['member_count'] ?></td>
                    <td><?= $u['last_login_at'] ? e(date('d M Y, h:i A', strtotime($u['last_login_at']))) : '<span class="text-muted">Never</span>' ?></td>
                    <td><?= user_status_badge($u) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('user_form.php?id=' . (int)$u['id'])) ?>">Manage</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3" aria-label="User pages">
    <ul class="pagination justify-content-center mb-0">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(url('users.php?' . query_with($keep, 'page', $i))) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
