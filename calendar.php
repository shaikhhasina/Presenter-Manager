<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle  = 'Calendar';
$activePage = 'calendar';

$pdo  = db();
$user = current_user();
$today = date('Y-m-d');

// ---- Which month? (?month=2026-11) ----
$monthParam = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthParam)) {
    $monthParam = date('Y-m');
}
$first     = new DateTimeImmutable($monthParam . '-01');
$monthStart = $first->format('Y-m-d');
$monthEnd   = $first->format('Y-m-t');
$prevMonth  = $first->modify('-1 month')->format('Y-m');
$nextMonth  = $first->modify('+1 month')->format('Y-m');

// ---- Filters ----
$chapterId = (int)($_GET['chapter_id'] ?? 0);
$status    = (string)($_GET['status'] ?? '');
if (!in_array($status, ['upcoming', 'approaching', 'due', 'overdue', 'scheduled', 'completed'], true)) {
    $status = '';
}

$scope  = chapter_scope_sql('m');
$where  = [$scope['sql']];
$params = $scope['params'];

if (is_super_admin() && $chapterId > 0) {
    $where[] = 'm.chapter_id = :chapter_id';
    $params['chapter_id'] = $chapterId;
}

$filter  = presentation_filter_sql($status);
$where[] = $filter['sql'];
$params  = array_merge($params, $filter['params']);

if ($status !== 'completed') {
    $where[] = "m.status = 'active'";
    $where[] = '((p.calculated_due_date BETWEEN :a1 AND :a2) OR (p.scheduled_date BETWEEN :b1 AND :b2))';
    $params += ['a1' => $monthStart, 'a2' => $monthEnd, 'b1' => $monthStart, 'b2' => $monthEnd];
} else {
    $where[] = 'p.completed_date BETWEEN :a1 AND :a2';
    $params += ['a1' => $monthStart, 'a2' => $monthEnd];
}

$stmt = $pdo->prepare(
    'SELECT p.*, m.member_code, m.full_name, c.name AS chapter_name
     FROM presentations p
     JOIN members m ON m.id = p.member_id
     JOIN chapters c ON c.id = m.chapter_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY m.full_name'
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ---- Put each row on its day(s) ----
$events = [];
$badgeClass = [
    'upcoming' => 'bg-info', 'approaching' => 'bg-warning', 'due' => 'badge-due',
    'overdue' => 'bg-danger', 'scheduled' => 'bg-primary', 'completed' => 'bg-success',
];
foreach ($rows as $r) {
    $st = presentation_status($r);

    if ($r['status'] === 'completed') {
        if ($r['completed_date']) {
            $events[$r['completed_date']][] = ['kind' => 'done', 'row' => $r, 'status' => 'completed'];
        }
        continue;
    }
    if ($r['calculated_due_date'] && $r['calculated_due_date'] >= $monthStart && $r['calculated_due_date'] <= $monthEnd) {
        $events[$r['calculated_due_date']][] = ['kind' => 'due', 'row' => $r, 'status' => $st];
    }
    if ($r['status'] === 'scheduled' && $r['scheduled_date'] && $r['scheduled_date'] >= $monthStart && $r['scheduled_date'] <= $monthEnd) {
        $events[$r['scheduled_date']][] = ['kind' => 'confirmed', 'row' => $r, 'status' => $st];
    }
}

// ---- Grid (weeks start on Monday) ----
$offset   = (int)$first->format('N') - 1;
$daysIn   = (int)$first->format('t');
$cells    = $offset + $daysIn;
$weeks    = (int)ceil($cells / 7);
$chapters = fetch_active_chapters();

$keep = array_filter([
    'chapter_id' => (is_super_admin() && $chapterId > 0) ? $chapterId : '',
    'status'     => $status,
], static fn($v) => $v !== '' && $v !== null);

require __DIR__ . '/includes/header.php';
?>

<div class="card stat-card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('calendar.php')) ?>" class="row g-2 align-items-end">
            <input type="hidden" name="month" value="<?= e($monthParam) ?>">
            <?php if (is_super_admin()): ?>
            <div class="col-6 col-md-4 col-xl-3">
                <label class="form-label" for="chapter_id">Chapter</label>
                <select class="form-select" id="chapter_id" name="chapter_id">
                    <option value="">All</option>
                    <?php foreach ($chapters as $ch): ?>
                        <option value="<?= (int)$ch['id'] ?>" <?= $chapterId === (int)$ch['id'] ? 'selected' : '' ?>><?= e($ch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-6 col-md-4 col-xl-3">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All open</option>
                    <?php foreach (['overdue', 'due', 'approaching', 'upcoming', 'scheduled', 'completed'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i> Filter</button>
                <a href="<?= e(url('calendar.php')) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('calendar.php?' . query_with($keep, 'month', $prevMonth))) ?>" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
        <h2 class="h5 mb-0"><?= e($first->format('F Y')) ?></h2>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('calendar.php?' . query_with($keep, 'month', $nextMonth))) ?>" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
        <a class="btn btn-outline-primary btn-sm" href="<?= e(url('calendar.php?' . query_with($keep, 'month', date('Y-m')))) ?>">Today</a>
    </div>
    <div class="small text-muted">
        <span class="cal-chip outline d-inline-block">Outlined = calculated due date</span>
        <span class="cal-chip bg-primary d-inline-block">Solid blue = confirmed date</span>
    </div>
</div>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-bordered cal-table mb-0">
            <thead class="table-light">
                <tr><?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?><th><?= e($d) ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php for ($w = 0; $w < $weeks; $w++): ?>
                <tr>
                <?php for ($dow = 0; $dow < 7; $dow++): ?>
                    <?php
                    $dayNum = $w * 7 + $dow - $offset + 1;
                    if ($dayNum < 1 || $dayNum > $daysIn):
                    ?>
                        <td class="cal-cell other-month"></td>
                    <?php
                        continue;
                    endif;
                    $dateStr = $first->format('Y-m-') . str_pad((string)$dayNum, 2, '0', STR_PAD_LEFT);
                    ?>
                    <td class="cal-cell <?= $dateStr === $today ? 'is-today' : '' ?>">
                        <div class="cal-day"><?= (int)$dayNum ?></div>
                        <div class="cal-events">
                        <?php foreach ($events[$dateStr] ?? [] as $ev): ?>
                            <?php
                            $r = $ev['row'];
                            $isDue = $ev['kind'] === 'due';
                            $cls = $isDue && $ev['status'] === 'scheduled'
                                ? 'outline'
                                : ($badgeClass[$ev['status']] ?? 'bg-secondary');
                            $prefix = $ev['kind'] === 'confirmed' ? '✔ ' : ($ev['kind'] === 'done' ? '✓ ' : '');
                            $tip = $r['full_name'] . ' · ' . ($isDue ? 'due' : ($ev['kind'] === 'done' ? 'completed' : 'confirmed'))
                                 . ($r['venue'] ? ' · ' . $r['venue'] : '') . ' · ' . $r['chapter_name'];
                            ?>
                            <a class="cal-chip <?= e($cls) ?>" title="<?= e($tip) ?>"
                               href="<?= e(url('presentation.php?id=' . (int)$r['id'])) ?>">
                                <?= e($prefix . $r['full_name']) ?>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    </td>
                <?php endfor; ?>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2 mb-0">
    Click a name to open the presentation, where you can confirm or change the date. Colours match the status badges.
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
