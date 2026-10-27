<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';

$pdo   = db();
$scope = chapter_scope_sql('m');
$today = date('Y-m-d');

// Pending public registrations waiting for approval
$pendingReg = 0;
try {
    $pr = $pdo->prepare("SELECT COUNT(*) FROM member_registrations r WHERE r.status = 'pending' AND " . chapter_scope_sql('r')['sql']);
    $pr->execute(chapter_scope_sql('r')['params']);
    $pendingReg = (int)$pr->fetchColumn();
} catch (PDOException $ex) {
    // table not created yet: ignore
}

/** Run a COUNT query and return an int. */
function count_query(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

// ---- Members ----
$totalMembers  = count_query($pdo, "SELECT COUNT(*) FROM members m WHERE {$scope['sql']}", $scope['params']);
$activeMembers = count_query($pdo, "SELECT COUNT(*) FROM members m WHERE m.status = 'active' AND {$scope['sql']}", $scope['params']);

// ---- Open presentations of ACTIVE members, counted by live status ----
$openFrom = "FROM presentations p JOIN members m ON m.id = p.member_id
             WHERE m.status = 'active' AND {$scope['sql']}";

$counts = [];
foreach (['overdue', 'due', 'approaching', 'upcoming', 'scheduled'] as $st) {
    $f = presentation_filter_sql($st);
    $counts[$st] = count_query($pdo, "SELECT COUNT(*) {$openFrom} AND {$f['sql']}", array_merge($scope['params'], $f['params']));
}
$upcomingSoon = $counts['due'] + $counts['approaching'];   // due within the next 30 days

// ---- Due this month / next month (by calculated due date) ----
$thisStart = date('Y-m-01');
$thisEnd   = date('Y-m-t');
$nextFirst = new DateTimeImmutable('first day of next month');
$nextStart = $nextFirst->format('Y-m-01');
$nextEnd   = $nextFirst->format('Y-m-t');

$dueSql = "SELECT COUNT(*) {$openFrom} AND p.status IN ('pending','scheduled')
           AND p.calculated_due_date BETWEEN :d1 AND :d2";
$dueThisMonth = count_query($pdo, $dueSql, array_merge($scope['params'], ['d1' => $thisStart, 'd2' => $thisEnd]));
$dueNextMonth = count_query($pdo, $dueSql, array_merge($scope['params'], ['d1' => $nextStart, 'd2' => $nextEnd]));

// ---- Emails sent / failed (filled by the Email Center, Module 6) ----
if (is_super_admin()) {
    $emailStmt = $pdo->query('SELECT status, COUNT(*) AS c FROM email_logs GROUP BY status');
} else {
    $emailStmt = $pdo->prepare(
        "SELECT e.status, COUNT(*) AS c FROM email_logs e
         JOIN members m ON m.id = e.member_id
         WHERE {$scope['sql']} GROUP BY e.status"
    );
    $emailStmt->execute($scope['params']);
}
$emailCounts = ['sent' => 0, 'failed' => 0];
foreach ($emailStmt->fetchAll() as $row) {
    if (isset($emailCounts[$row['status']])) {
        $emailCounts[$row['status']] = (int)$row['c'];
    }
}

// ---- Chart 2: presentations due per month (next 6 months) + overdue bucket ----
$months = [];
for ($i = 0; $i < 6; $i++) {
    $d = new DateTimeImmutable('first day of this month +' . $i . ' month');
    $months[$d->format('Y-m')] = ['label' => $d->format('M Y'), 'count' => 0];
}
$rangeEnd = (new DateTimeImmutable('first day of this month +6 month'))->format('Y-m-d');
$mStmt = $pdo->prepare(
    "SELECT DATE_FORMAT(p.calculated_due_date, '%Y-%m') AS ym, COUNT(*) AS c
     {$openFrom} AND p.status IN ('pending','scheduled')
     AND p.calculated_due_date >= :r1 AND p.calculated_due_date < :r2
     GROUP BY ym"
);
$mStmt->execute(array_merge($scope['params'], ['r1' => $thisStart, 'r2' => $rangeEnd]));
foreach ($mStmt->fetchAll() as $row) {
    if (isset($months[$row['ym']])) {
        $months[$row['ym']]['count'] = (int)$row['c'];
    }
}
$barLabels = array_merge(['Overdue'], array_column($months, 'label'));
$barValues = array_merge([$counts['overdue']], array_column($months, 'count'));

// ---- Chart 3: active members per category ----
$cStmt = $pdo->prepare(
    "SELECT cat.name, COUNT(*) AS c
     FROM members m JOIN categories cat ON cat.id = m.category_id
     WHERE m.status = 'active' AND {$scope['sql']}
     GROUP BY cat.id, cat.name ORDER BY cat.name"
);
$cStmt->execute($scope['params']);
$catRows = $cStmt->fetchAll();

// ---- Needs attention: overdue first, then soonest due (next 30 days) ----
$aStmt = $pdo->prepare(
    "SELECT p.*, m.member_code, m.full_name, c.name AS chapter_name
     FROM presentations p
     JOIN members m ON m.id = p.member_id
     JOIN chapters c ON c.id = m.chapter_id
     WHERE m.status = 'active' AND {$scope['sql']}
       AND ((p.status = 'pending' AND p.calculated_due_date <= :lim)
         OR (p.status = 'scheduled' AND p.scheduled_date < :today))
     ORDER BY p.calculated_due_date ASC, p.id ASC
     LIMIT 8"
);
$aStmt->execute(array_merge($scope['params'], [
    'lim'   => (string)add_calendar_days($today, approaching_days()),
    'today' => $today,
]));
$attention = $aStmt->fetchAll();

$cards = [
    ['Total members',          $totalMembers,            'bi-people',               'primary', 'members.php'],
    ['Active members',         $activeMembers,           'bi-person-check',         'success', 'members.php?status=active'],
    ['Due this month',         $dueThisMonth,            'bi-calendar-event',       'info',    'calendar.php'],
    ['Due next month',         $dueNextMonth,            'bi-calendar-plus',        'secondary','calendar.php?month=' . $nextFirst->format('Y-m')],
    ['Upcoming (next 30 days)',$upcomingSoon,            'bi-hourglass-split',      'warning', 'presentations.php?status=approaching'],
    ['Overdue',                $counts['overdue'],       'bi-exclamation-triangle', 'danger',  'presentations.php?status=overdue'],
    ['Emails sent',            $emailCounts['sent'],     'bi-envelope-check',       'success', 'emails.php?status=sent'],
    ['Emails failed',          $emailCounts['failed'],   'bi-envelope-x',           'danger',  'emails.php?status=failed'],
];

$chartData = [
    'status' => [
        'labels' => ['Overdue', 'Due', 'Approaching', 'Upcoming', 'Scheduled'],
        'values' => [$counts['overdue'], $counts['due'], $counts['approaching'], $counts['upcoming'], $counts['scheduled']],
    ],
    'months'   => ['labels' => $barLabels, 'values' => $barValues],
    'category' => ['labels' => array_column($catRows, 'name'), 'values' => array_map('intval', array_column($catRows, 'c'))],
];

require __DIR__ . '/includes/header.php';
?>

<div class="mb-4">
    <h2 class="h4 fw-bold mb-1">Welcome, <?= e(current_user()['full_name']) ?></h2>
    <p class="text-muted mb-0">
        <?= e(role_label(current_user()['role'])) ?> · live figures for <?= e(date('d M Y')) ?>
        <?= is_super_admin() ? '(all chapters)' : '(your chapter)' ?>
    </p>
</div>

<?php if ($pendingReg > 0): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-plus me-1"></i> <strong><?= (int)$pendingReg ?></strong> new registration<?= $pendingReg === 1 ? '' : 's' ?> waiting for approval.</span>
        <a class="btn btn-sm btn-primary" href="<?= e(url('registrations.php')) ?>">Review</a>
    </div>
<?php endif; ?>

<?php if (!is_super_admin() && empty(current_user()['chapter_id'])): ?>
    <div class="alert alert-warning">Your account has no chapter assigned, so all figures are zero. Ask a Super Admin to assign you.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <?php foreach ($cards as [$label, $value, $icon, $color, $link]): ?>
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="stat-icon bg-<?= e($color) ?>-subtle text-<?= e($color) ?>"><i class="bi <?= e($icon) ?>"></i></div>
                    <div>
                        <div class="stat-value"><?= (int)$value ?></div>
                        <div class="text-muted small">
                            <?php if ($link !== ''): ?>
                                <a class="text-muted text-decoration-none" href="<?= e(url($link)) ?>"><?= e($label) ?></a>
                            <?php else: ?>
                                <?= e($label) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card stat-card h-100"><div class="card-body">
            <h3 class="h6 fw-bold">Open presentations by status</h3>
            <div class="chart-box"><canvas id="statusChart"></canvas></div>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card stat-card h-100"><div class="card-body">
            <h3 class="h6 fw-bold">Due per month (next 6 months)</h3>
            <div class="chart-box"><canvas id="monthChart"></canvas></div>
        </div></div>
    </div>
    <div class="col-lg-3">
        <div class="card stat-card h-100"><div class="card-body">
            <h3 class="h6 fw-bold">Active members by category</h3>
            <div class="chart-box"><canvas id="categoryChart"></canvas></div>
        </div></div>
    </div>
</div>

<div class="card stat-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 fw-bold mb-0">Needs attention</h3>
            <a href="<?= e(url('presentations.php')) ?>" class="small">View all</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Member</th><th>Chapter</th><th>Due date</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (!$attention): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Nothing overdue or due in the next 30 days.</td></tr>
                <?php endif; ?>
                <?php foreach ($attention as $r): ?>
                    <tr>
                        <td><?= e($r['full_name']) ?> <span class="small text-muted"><?= e($r['member_code']) ?></span></td>
                        <td><?= e($r['chapter_name']) ?></td>
                        <td><?= e(format_date($r['calculated_due_date'])) ?>
                            <span class="small text-muted">(<?= e(due_in_text($r['calculated_due_date'])) ?>)</span></td>
                        <td><?= presentation_status_badge(presentation_status($r)) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('presentation.php?id=' . (int)$r['id'])) ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
    var data = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (typeof Chart === 'undefined') { return; }

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: { labels: data.status.labels, datasets: [{
            data: data.status.values,
            backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#0dcaf0', '#0d6efd']
        }]},
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    new Chart(document.getElementById('monthChart'), {
        type: 'bar',
        data: { labels: data.months.labels, datasets: [{
            label: 'Presentations',
            data: data.months.values,
            backgroundColor: data.months.labels.map(function (l) { return l === 'Overdue' ? '#dc3545' : '#6366f1'; })
        }]},
        options: { maintainAspectRatio: false, plugins: { legend: { display: false } },
                   scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'pie',
        data: { labels: data.category.labels, datasets: [{
            data: data.category.values,
            backgroundColor: ['#6366f1', '#0dcaf0', '#198754', '#ffc107', '#fd7e14', '#6c757d']
        }]},
        options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
})();
</script>
<?php
$extraScripts = ob_get_clean();
require __DIR__ . '/includes/footer.php';
