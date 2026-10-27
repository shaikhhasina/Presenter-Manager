<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle  = 'Presentations';
$activePage = 'presentations';

$pdo  = db();
$user = current_user();

$q         = trim((string)($_GET['q'] ?? ''));
$chapterId = (int)($_GET['chapter_id'] ?? 0);
$status    = (string)($_GET['status'] ?? '');
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;

$validStatuses = ['upcoming', 'approaching', 'due', 'overdue', 'scheduled', 'completed'];
if (!in_array($status, $validStatuses, true)) {
    $status = '';
}

if (!is_super_admin()) {
    $chapterId = (int)($user['chapter_id'] ?? 0);
}

$where  = [];
$params = [];

// Chapter scope (Chapter Coordinators only see their own chapter)
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

if ($q !== '') {
    $where[] = '(m.member_code LIKE :q1 OR m.full_name LIKE :q2 OR m.email LIKE :q3)';
    $like = like_contains($q);
    $params['q1'] = $like;
    $params['q2'] = $like;
    $params['q3'] = $like;
}

// Snapshot (chapter + search) used for the tab counts
$baseWhere  = $where;
$baseParams = $params;

// Status filter (open cycles by default; "completed" shows history)
$filter  = presentation_filter_sql($status);
$where[] = $filter['sql'];
$params  = array_merge($params, $filter['params']);

if ($status !== 'completed') {
    $where[] = "m.status = 'active'";   // inactive members are excluded from the open list
}

$whereSql = implode(' AND ', $where);
$from = 'FROM presentations p
         JOIN members m ON m.id = p.member_id
         JOIN chapters c ON c.id = m.chapter_id';

$countStmt = $pdo->prepare("SELECT COUNT(*) {$from} WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;

$orderBy = $status === 'completed'
    ? 'p.completed_date DESC, p.id DESC'
    : 'p.calculated_due_date ASC, p.id ASC';

$listStmt = $pdo->prepare(
    "SELECT p.*, m.member_code, m.full_name, m.email, c.name AS chapter_name
     {$from}
     WHERE {$whereSql}
     ORDER BY {$orderBy}
     LIMIT {$perPage} OFFSET {$offset}"
);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

$chapters = fetch_active_chapters();

// Count for every tab (same chapter + search filters as the list)
$tabCounts = [];
foreach (['overdue', 'due', 'approaching', 'upcoming', 'scheduled', 'completed', ''] as $key) {
    $f = presentation_filter_sql($key);
    $w = array_merge($baseWhere, [$f['sql']]);
    if ($key !== 'completed') {
        $w[] = "m.status = 'active'";
    }
    $c = $pdo->prepare('SELECT COUNT(*) ' . $from . ' WHERE ' . implode(' AND ', $w));
    $c->execute(array_merge($baseParams, $f['params']));
    $tabCounts[$key] = (int)$c->fetchColumn();
}
$sumFive = $tabCounts['overdue'] + $tabCounts['due'] + $tabCounts['approaching'] + $tabCounts['upcoming'] + $tabCounts['scheduled'];

$keep = array_filter([
    'q'          => $q,
    'chapter_id' => (is_super_admin() && $chapterId > 0) ? $chapterId : '',
    'status'     => $status,
], static fn($v) => $v !== '' && $v !== null);

$pills = ['' => 'All open', 'overdue' => 'Overdue', 'due' => 'Due', 'approaching' => 'Approaching',
          'upcoming' => 'Upcoming', 'scheduled' => 'Scheduled', 'completed' => 'Completed'];

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <p class="text-muted mb-0">
        <?= (int)$total ?> record<?= $total === 1 ? '' : 's' ?> found.
        Due date = last completed presentation + <?= (int)PRESENTATION_INTERVAL_DAYS ?> calendar days.
    </p>
</div>

<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    <?php foreach ($pills as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link py-1 <?= $status === $key ? 'active' : 'bg-white border' ?>"
               href="<?= e(url('presentations.php?' . query_with($keep, 'status', $key))) ?>">
                <?= e($label) ?> <span class="badge rounded-pill text-bg-light border"><?= (int)$tabCounts[$key] ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
<p class="small text-muted mb-3">
    All open (<?= (int)$tabCounts[''] ?>) = Overdue + Due + Approaching + Upcoming + Scheduled
    (<?= (int)$sumFive ?>)<?= $sumFive === $tabCounts[''] ? ' &#10003;' : ' — counts differ, please report this' ?>.
    Completed is history and is not part of All open.
</p>

<div class="card stat-card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('presentations.php')) ?>" class="row g-2 align-items-end">
            <input type="hidden" name="status" value="<?= e($status) ?>">

            <div class="col-12 col-md-5 col-xl-4">
                <label class="form-label" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q"
                       value="<?= e($q) ?>" placeholder="Name, Member ID, email">
            </div>

            <?php if (is_super_admin()): ?>
            <div class="col-6 col-md-4 col-xl-3">
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

            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i> Filter</button>
                <a href="<?= e(url('presentations.php')) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Member</th>
                    <th>Chapter</th>
                    <th>Calculated due</th>
                    <th>Confirmed date</th>
                    <th>Completed</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No presentations match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <?php $st = presentation_status($r); ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($r['full_name']) ?></div>
                        <div class="small text-muted"><?= e($r['member_code']) ?></div>
                    </td>
                    <td><?= e($r['chapter_name']) ?></td>
                    <td>
                        <?= e(format_date($r['calculated_due_date'])) ?>
                        <?php if (in_array($st, ['overdue', 'due', 'approaching', 'upcoming'], true)): ?>
                            <div class="small text-muted"><?= e(due_in_text($r['calculated_due_date'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e(format_date($r['scheduled_date'])) ?>
                        <?php if ($r['scheduled_date'] && !$r['details_confirmed'] && $r['status'] === 'scheduled'): ?>
                            <div class="small text-warning-emphasis">details not confirmed</div>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_date($r['completed_date'])) ?></td>
                    <td><?= e($r['venue'] ?: '—') ?></td>
                    <td><?= presentation_status_badge($st) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('presentation.php?id=' . (int)$r['id'])) ?>">
                            <?= in_array($r['status'], ['pending', 'scheduled'], true) ? 'Manage' : 'View' ?>
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('member_view.php?id=' . (int)$r['member_id'])) ?>">Member</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3" aria-label="Presentation pages">
    <ul class="pagination justify-content-center mb-0">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(url('presentations.php?' . query_with($keep, 'page', $i))) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
