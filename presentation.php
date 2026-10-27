<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$p  = $id > 0 ? load_presentation($id) : null;

if (!$p) {
    http_response_code(404);
    exit('Presentation not found, or you do not have permission to view this record.');
}

$pageTitle  = 'Presentation: ' . $p['full_name'];
$activePage = 'presentations';

$status   = presentation_status($p);
$isOpen   = in_array($p['status'], ['pending', 'scheduled'], true);
$isActive = $p['member_status'] === 'active';
$today    = date('Y-m-d');

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <a href="<?= e(url('presentations.php')) ?>" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to presentations
    </a>
    <div class="d-flex flex-wrap gap-2">
        <?php if ($isOpen && $isActive && trim((string)$p['email']) !== ''): ?>
            <form method="post" action="<?= e(url('email_action.php')) ?>"
                  onsubmit="return confirm('Email a reminder to <?= e(addslashes($p['full_name'])) ?> now?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_reminder">
                <input type="hidden" name="presentation_id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-envelope"></i> Email reminder now</button>
            </form>
        <?php endif; ?>
        <a href="<?= e(url('member_view.php?id=' . (int)$p['member_id'])) ?>" class="btn btn-outline-secondary btn-sm">
            View member &amp; full history
        </a>
    </div>
</div>

<div class="card stat-card mb-3">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-1"><?= e($p['full_name']) ?></h2>
                <div class="text-muted"><?= e($p['member_code']) ?> · <?= e($p['chapter_name']) ?></div>
            </div>
            <?= presentation_status_badge($status) ?>
        </div>

        <dl class="row mb-0 member-dl">
            <dt class="col-sm-4 col-lg-3">Calculated due date</dt>
            <dd class="col-sm-8 col-lg-9">
                <?= e(format_date($p['calculated_due_date'])) ?>
                <?php if ($isOpen && $p['calculated_due_date']): ?>
                    <span class="text-muted">(<?= e(due_in_text($p['calculated_due_date'])) ?>)</span>
                <?php endif; ?>
                <div class="small text-muted">Last completed presentation + <?= (int)PRESENTATION_INTERVAL_DAYS ?> calendar days. Never changed by scheduling.</div>
            </dd>

            <dt class="col-sm-4 col-lg-3">Confirmed schedule</dt>
            <dd class="col-sm-8 col-lg-9">
                <?= e(format_date($p['scheduled_date'])) ?>
                <?php if ($p['scheduled_date']): ?>
                    <span class="badge <?= $p['details_confirmed'] ? 'text-bg-success' : 'text-bg-warning' ?> ms-1">
                        <?= $p['details_confirmed'] ? 'Details confirmed' : 'Details not confirmed' ?>
                    </span>
                <?php endif; ?>
                <?php if ($status === 'overdue' && $p['status'] === 'scheduled'): ?>
                    <div class="small text-danger">The scheduled date has passed. Record the completion once it happened, or reschedule.</div>
                <?php endif; ?>
            </dd>

            <dt class="col-sm-4 col-lg-3">Completed on</dt>
            <dd class="col-sm-8 col-lg-9"><?= e(format_date($p['completed_date'])) ?></dd>

            <dt class="col-sm-4 col-lg-3">Venue</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($p['venue'] ?: '—') ?></dd>

            <dt class="col-sm-4 col-lg-3">Chapter</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($p['completed_chapter_name'] ?: '—') ?></dd>
        </dl>
    </div>
</div>

<?php if ($isOpen && !$isActive): ?>
    <div class="alert alert-warning">
        This member is inactive. Reactivate them on the member page before scheduling.
        You can still record a completed presentation.
    </div>
<?php endif; ?>

<?php if ($isOpen): ?>
<div class="row g-3 mb-3">
    <?php if ($isActive): ?>
    <div class="col-lg-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h3 class="h6 fw-bold mb-3"><?= $p['status'] === 'scheduled' ? 'Reschedule' : 'Schedule' ?> presentation</h3>
                <form method="post" action="<?= e(url('presentation_action.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="schedule">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label" for="scheduled_date">Scheduled date</label>
                        <input type="date" class="form-control" id="scheduled_date" name="scheduled_date"
                               value="<?= e((string)$p['scheduled_date']) ?>" min="<?= e($today) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="s_venue">Venue</label>
                        <input type="text" class="form-control" id="s_venue" name="venue" maxlength="190"
                               value="<?= e((string)$p['venue']) ?>">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="details_confirmed" name="details_confirmed" value="1"
                               <?= $p['details_confirmed'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="details_confirmed">Date and venue are confirmed with the member</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="s_notes">Note (optional)</label>
                        <textarea class="form-control" id="s_notes" name="notes" rows="2" maxlength="1000"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save schedule</button>
                </form>

                <?php if ($p['status'] === 'scheduled'): ?>
                    <form method="post" action="<?= e(url('presentation_action.php')) ?>" class="mt-2"
                          onsubmit="return confirm('Clear the confirmed schedule? The calculated due date stays the same.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear_schedule">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm">Clear schedule</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-lg-6">
        <div class="card stat-card h-100">
            <div class="card-body">
                <h3 class="h6 fw-bold mb-1">Record completed</h3>
                <p class="small text-muted">
                    Use the <strong>actual</strong> date it happened. The next due date will be that date + <?= (int)PRESENTATION_INTERVAL_DAYS ?> days,
                    and this record stays in the history.
                </p>
                <form method="post" action="<?= e(url('presentation_action.php')) ?>"
                      onsubmit="return confirm('Record this presentation as completed? This cannot be undone.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="complete">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label" for="completed_date">Actual completion date</label>
                        <input type="date" class="form-control" id="completed_date" name="completed_date"
                               value="<?= e($p['scheduled_date'] && $p['scheduled_date'] <= $today ? $p['scheduled_date'] : '') ?>"
                               max="<?= e($today) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="c_venue">Venue</label>
                        <input type="text" class="form-control" id="c_venue" name="venue" maxlength="190"
                               value="<?= e((string)$p['venue']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="c_chapter">Chapter where it took place</label>
                        <select class="form-select" id="c_chapter" name="completed_chapter_id" required>
                            <option value="">Choose…</option>
                            <?php $cDefault = (int)($p['completed_chapter_id'] ?: $p['chapter_id']); ?>
                            <?php foreach (fetch_active_chapters() as $ch): ?>
                                <option value="<?= (int)$ch['id'] ?>" <?= $cDefault === (int)$ch['id'] ? 'selected' : '' ?>><?= e($ch['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="c_notes">Note (optional)</label>
                        <textarea class="form-control" id="c_notes" name="notes" rows="2" maxlength="1000"></textarea>
                    </div>
                    <button type="submit" class="btn btn-success">Record completed</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card stat-card">
    <div class="card-body">
        <h3 class="h6 fw-bold">Activity log</h3>
        <?php if (trim((string)$p['notes']) === ''): ?>
            <p class="text-muted mb-0">No activity yet.</p>
        <?php else: ?>
            <p class="notes-log"><?= e($p['notes']) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
