<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['super_admin']);

$pageTitle  = 'Activity Log';
$activePage = 'audit';

$q      = trim((string)($_GET['q'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$from   = (string)($_GET['from'] ?? '');
$to     = (string)($_GET['to'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

if ($from !== '' && !is_valid_date($from)) { $from = ''; }
if ($to !== '' && !is_valid_date($to)) { $to = ''; }

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(user_name LIKE :q1 OR details LIKE :q2)';
    $params['q1'] = like_contains($q);
    $params['q2'] = like_contains($q);
}
if ($action !== '') {
    $where[] = 'action = :action';
    $params['action'] = $action;
}
if ($from !== '') {
    $where[] = 'created_at >= :from';
    $params['from'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'created_at <= :to';
    $params['to'] = $to . ' 23:59:59';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare("SELECT COUNT(*) FROM audit_logs $whereSql");
$stmt->execute($params);
$total  = (int)$stmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

$stmt = db()->prepare("SELECT * FROM audit_logs $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$actions = db()->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$keep = array_filter(['q' => $q, 'action' => $action, 'from' => $from, 'to' => $to], fn($v) => $v !== '');

require __DIR__ . '/includes/header.php';
?>

<div class="card stat-card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label" for="q">Search</label>
                <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Person or details">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="action">Action</label>
                <select class="form-select" id="action" name="action">
                    <option value="">All</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $a)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="from">From</label>
                <input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="to">To</label>
                <input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>">
            </div>
            <div class="col-6 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i> Filter</button>
                <a href="<?= e(url('audit_log.php')) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No activity recorded yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-nowrap small"><?= e(date('d M Y, h:i A', strtotime($r['created_at']))) ?></td>
                    <td><?= $r['user_name'] !== null ? e($r['user_name']) : '<span class="text-muted">System / deleted user</span>' ?></td>
                    <td><span class="badge text-bg-light border"><?= e(str_replace('_', ' ', $r['action'])) ?></span></td>
                    <td class="small"><?= e((string)$r['details']) ?></td>
                    <td class="small text-muted"><?= e((string)$r['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3" aria-label="Log pages">
    <ul class="pagination justify-content-center mb-0 flex-wrap">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(url('audit_log.php?' . query_with($keep, 'page', $i))) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
