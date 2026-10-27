<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle  = 'Members';
$activePage = 'members';

$pdo  = db();
$user = current_user();

$q          = trim((string)($_GET['q'] ?? ''));
$chapterId  = (int)($_GET['chapter_id'] ?? 0);
$categoryId = (int)($_GET['category_id'] ?? 0);
$status     = (string)($_GET['status'] ?? '');
$coordId    = (int)($_GET['coordinator_id'] ?? 0);
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 15;

if (!is_super_admin()) {
    $chapterId = (int)($user['chapter_id'] ?? 0);
}

$where  = ['1=1'];
$params = [];

if (!is_super_admin()) {
    if ($chapterId < 1) {
        $where[] = '1=0';
    } else {
        $where[] = 'm.chapter_id = :scope_chapter';
        $params['scope_chapter'] = $chapterId;
    }
} elseif ($chapterId > 0) {
    $where[] = 'm.chapter_id = :chapter_id';
    $params['chapter_id'] = $chapterId;
}

if ($categoryId > 0) {
    $where[] = 'm.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

if (in_array($status, ['active', 'inactive'], true)) {
    $where[] = 'm.status = :status';
    $params['status'] = $status;
}

if ($coordId > 0) {
    $where[] = 'm.coordinator_id = :coordinator_id';
    $params['coordinator_id'] = $coordId;
}

if ($q !== '') {
    $where[] = '(m.member_code LIKE :q1 OR m.full_name LIKE :q2 OR m.email LIKE :q3 OR m.mobile LIKE :q4)';
    $like = like_contains($q);
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
    $params['q4'] = $like;
}

$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM members m WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;

$listSql = "SELECT m.*,
                   c.name AS chapter_name,
                   cat.name AS category_name,
                   u.full_name AS coordinator_name
            FROM members m
            JOIN chapters c ON c.id = m.chapter_id
            JOIN categories cat ON cat.id = m.category_id
            LEFT JOIN users u ON u.id = m.coordinator_id
            WHERE {$whereSql}
            ORDER BY m.full_name
            LIMIT {$perPage} OFFSET {$offset}";
$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$members = $listStmt->fetchAll();

$chapters    = fetch_active_chapters();
$categories  = fetch_active_categories();
$coordinators = fetch_coordinators(is_super_admin() ? null : ($user['chapter_id'] !== null ? (int)$user['chapter_id'] : null));

$keep = array_filter([
    'q'              => $q,
    'chapter_id'     => $chapterId > 0 ? $chapterId : '',
    'category_id'    => $categoryId > 0 ? $categoryId : '',
    'status'         => $status,
    'coordinator_id' => $coordId > 0 ? $coordId : '',
], static fn($v) => $v !== '' && $v !== null);

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <p class="text-muted mb-0"><?= (int)$total ?> member<?= $total === 1 ? '' : 's' ?> found</p>
    <a href="<?= e(url('member_form.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add member
    </a>
</div>

<?php if (!is_super_admin() && empty($user['chapter_id'])): ?>
    <div class="alert alert-warning">
        Your account has no chapter assigned, so you cannot see or add members yet.
        Ask a Super Admin to assign you to a chapter.
    </div>
<?php endif; ?>

<div class="card stat-card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('members.php')) ?>" class="row g-2 align-items-end">
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q"
                       value="<?= e($q) ?>" placeholder="Name, ID, email, mobile">
            </div>

            <?php if (is_super_admin()): ?>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="chapter_id">Chapter</label>
                <select class="form-select" id="chapter_id" name="chapter_id">
                    <option value="">All</option>
                    <?php foreach ($chapters as $ch): ?>
                        <option value="<?= (int)$ch['id'] ?>" <?= $chapterId === (int)$ch['id'] ? 'selected' : '' ?>>
                            <?= e($ch['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="category_id">Category</label>
                <select class="form-select" id="category_id" name="category_id">
                    <option value="">All</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label" for="coordinator_id">Coordinator</label>
                <select class="form-select" id="coordinator_id" name="coordinator_id">
                    <option value="">All</option>
                    <?php foreach ($coordinators as $coord): ?>
                        <option value="<?= (int)$coord['id'] ?>" <?= $coordId === (int)$coord['id'] ? 'selected' : '' ?>>
                            <?= e($coord['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-xl-auto d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search me-1"></i> Filter
                </button>
                <a href="<?= e(url('members.php')) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Member ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Chapter</th>
                    <th>Category</th>
                    <th>Last presented</th>
                    <th>Next due</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$members): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No members match these filters.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td class="fw-semibold"><?= e($m['member_code']) ?></td>
                    <td>
                        <div><?= e($m['full_name']) ?></div>
                        <div class="small text-muted"><?= e($m['email']) ?></div>
                    </td>
                    <td><?= e($m['mobile']) ?></td>
                    <td><?= e($m['chapter_name']) ?></td>
                    <td><?= e($m['category_name']) ?></td>
                    <td><?= e(format_date($m['last_presentation_date'])) ?></td>
                    <td><?= e(format_date($m['next_due_date'])) ?></td>
                    <td><?= member_status_badge($m['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('member_view.php?id=' . (int)$m['id'])) ?>">View</a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('member_form.php?id=' . (int)$m['id'])) ?>">Edit</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3" aria-label="Member pages">
    <ul class="pagination justify-content-center mb-0">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(url('members.php?' . query_with($keep, 'page', $i))) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
