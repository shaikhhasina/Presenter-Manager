<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$pageTitle  = 'Reports';
$activePage = 'reports';

$type = (string)($_GET['type'] ?? 'upcoming');
if (!isset(REPORT_TYPES[$type])) {
    $type = 'upcoming';
}

$chapterId = (is_super_admin()) ? (int)($_GET['chapter_id'] ?? 0) : 0;
$days  = in_array((int)($_GET['days'] ?? 30), [30, 60, 90], true) ? (int)($_GET['days'] ?? 30) : 30;
$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}
$export = ($_GET['export'] ?? '') === 'csv';
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$opts = ['chapter_id' => $chapterId, 'days' => $days, 'month' => $month];
$stamp = date('Ymd');

// ---------- Build data ----------
if ($type === 'chapter') {
    $chapterRows = fetch_chapter_report();

    if ($export) {
        $csv = [];
        foreach ($chapterRows as $r) {
            $csv[] = [$r['name'], $r['total'], $r['active'], $r['overdue'], $r['due'], $r['approaching'],
                      $r['upcoming'], $r['scheduled'], $r['completed12']];
        }
        send_csv("chapter-report-{$stamp}.csv",
            ['Chapter', 'Total members', 'Active members', 'Overdue', 'Due', 'Approaching', 'Upcoming', 'Scheduled', 'Completed (last 12 months)'],
            $csv);
    }
} else {
    $allRows = fetch_report_rows($type, $opts);

    if ($export) {
        $name = $type === 'monthly' ? "monthly-report-{$month}.csv" : "{$type}-report-{$stamp}.csv";
        send_csv($name, report_list_columns(), array_map('report_list_row', $allRows));
    }

    $total = count($allRows);
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min($page, $pages);
    $rows  = array_slice($allRows, ($page - 1) * $perPage, $perPage);

    // Month summary
    $summary = ['due' => 0, 'confirmed' => 0, 'completed' => 0];
    if ($type === 'monthly') {
        $ms = $month . '-01';
        $me = date('Y-m-t', strtotime($ms));
        foreach ($allRows as $r) {
            if ($r['status'] === 'completed') {
                $summary['completed']++;
                continue;
            }
            if ($r['calculated_due_date'] >= $ms && $r['calculated_due_date'] <= $me) {
                $summary['due']++;
            }
            if ($r['scheduled_date'] && $r['scheduled_date'] >= $ms && $r['scheduled_date'] <= $me) {
                $summary['confirmed']++;
            }
        }
    }
}

$keep = array_filter([
    'type' => $type,
    'chapter_id' => $chapterId > 0 ? $chapterId : '',
    'days' => $type === 'upcoming' ? $days : '',
    'month' => $type === 'monthly' ? $month : '',
], static fn($v) => $v !== '' && $v !== null);

$chapters = fetch_active_chapters();

require __DIR__ . '/includes/header.php';
?>

<ul class="nav nav-pills mb-3 flex-wrap gap-1">
    <?php foreach (REPORT_TYPES as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link py-1 <?= $type === $key ? 'active' : 'bg-white border' ?>"
               href="<?= e(url('reports.php?' . query_with(array_diff_key($keep, ['type' => 1]), 'type', $key))) ?>"><?= e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card stat-card mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('reports.php')) ?>" class="row g-2 align-items-end">
            <input type="hidden" name="type" value="<?= e($type) ?>">

            <?php if (is_super_admin() && $type !== 'chapter'): ?>
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

            <?php if ($type === 'upcoming'): ?>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="days">Next</label>
                <select class="form-select" id="days" name="days">
                    <?php foreach ([30, 60, 90] as $d): ?>
                        <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>><?= $d ?> days</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php elseif ($type === 'monthly'): ?>
            <div class="col-6 col-md-3 col-xl-2">
                <label class="form-label" for="month">Month</label>
                <input type="month" class="form-control" id="month" name="month" value="<?= e($month) ?>">
            </div>
            <?php endif; ?>

            <div class="col-12 col-md-auto d-flex gap-2">
                <?php if ($type !== 'chapter'): ?>
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i> Run report</button>
                <?php endif; ?>
                <a class="btn btn-success" href="<?= e(url('reports.php?' . query_with($keep, 'export', 'csv'))) ?>">
                    <i class="bi bi-download me-1"></i> Export CSV
                </a>
            </div>
        </form>
    </div>
</div>

<?php if ($type === 'chapter'): ?>
<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Chapter</th><th class="text-end">Members</th><th class="text-end">Active</th>
                    <th class="text-end">Overdue</th><th class="text-end">Due</th><th class="text-end">Approaching</th>
                    <th class="text-end">Upcoming</th><th class="text-end">Scheduled</th><th class="text-end">Completed (12 mo)</th></tr>
            </thead>
            <tbody>
            <?php if (!$chapterRows): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No chapters to show.</td></tr>
            <?php endif; ?>
            <?php foreach ($chapterRows as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['name']) ?></td>
                    <td class="text-end"><?= (int)$r['total'] ?></td>
                    <td class="text-end"><?= (int)$r['active'] ?></td>
                    <td class="text-end <?= $r['overdue'] ? 'text-danger fw-semibold' : '' ?>"><?= (int)$r['overdue'] ?></td>
                    <td class="text-end"><?= (int)$r['due'] ?></td>
                    <td class="text-end"><?= (int)$r['approaching'] ?></td>
                    <td class="text-end"><?= (int)$r['upcoming'] ?></td>
                    <td class="text-end"><?= (int)$r['scheduled'] ?></td>
                    <td class="text-end"><?= (int)$r['completed12'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>

<p class="text-muted">
    <?= (int)$total ?> row<?= $total === 1 ? '' : 's' ?>.
    <?php if ($type === 'upcoming'): ?>Active members due or confirmed in the next <?= (int)$days ?> days.<?php endif; ?>
    <?php if ($type === 'overdue'): ?>Active members whose due date (or confirmed date) has passed without a recorded completion.<?php endif; ?>
    <?php if ($type === 'monthly'): ?>
        <?= e(date('F Y', strtotime($month . '-01'))) ?>:
        <strong><?= (int)$summary['due'] ?></strong> due,
        <strong><?= (int)$summary['confirmed'] ?></strong> confirmed,
        <strong><?= (int)$summary['completed'] ?></strong> completed.
    <?php endif; ?>
</p>

<div class="card stat-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Member</th><th>Chapter</th><th>Category</th><th>Due date</th><th>Confirmed</th>
                    <th>Completed</th><th>Venue</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No records for this report.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($r['full_name']) ?></div>
                        <div class="small text-muted"><?= e($r['member_code']) ?> · <?= e($r['mobile']) ?></div>
                    </td>
                    <td><?= e($r['chapter_name']) ?></td>
                    <td><?= e($r['category_name']) ?></td>
                    <td>
                        <?= e(format_date($r['calculated_due_date'])) ?>
                        <?php if (in_array($r['status'], ['pending', 'scheduled'], true)): ?>
                            <div class="small text-muted"><?= e(due_in_text($r['calculated_due_date'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_date($r['scheduled_date'])) ?></td>
                    <td><?= e(format_date($r['completed_date'])) ?></td>
                    <td><?= e($r['venue'] ?: '—') ?></td>
                    <td><?= presentation_status_badge(presentation_status($r)) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('presentation.php?id=' . (int)$r['id'])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3" aria-label="Report pages">
    <ul class="pagination justify-content-center mb-0">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= e(url('reports.php?' . query_with($keep, 'page', $i))) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
