<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id     = (int)($_GET['id'] ?? 0);
$member = $id > 0 ? load_member($id) : null;

if (!$member) {
    http_response_code(404);
    exit('Member not found, or you do not have permission to view this record.');
}

$pageTitle  = $member['full_name'];
$activePage = 'members';

$history = fetch_member_presentations((int)$member['id']);
$open    = get_open_presentation((int)$member['id']);
$cycleNo = count($history);

require __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <a href="<?= e(url('members.php')) ?>" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to members
    </a>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="<?= e(url('member_form.php?id=' . (int)$member['id'])) ?>">
            <i class="bi bi-pencil me-1"></i> Edit
        </a>
        <form method="post" action="<?= e(url('member_action.php')) ?>" class="d-inline"
              onsubmit="return confirm('<?= $member['status'] === 'active' ? 'Deactivate this member? They will be excluded from reminders later.' : 'Reactivate this member?' ?>');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
            <input type="hidden" name="action" value="<?= $member['status'] === 'active' ? 'deactivate' : 'activate' ?>">
            <?php if ($member['status'] === 'active'): ?>
                <button type="submit" class="btn btn-outline-danger">Deactivate</button>
            <?php else: ?>
                <button type="submit" class="btn btn-outline-success">Reactivate</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card stat-card">
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h4 mb-1"><?= e($member['full_name']) ?></h2>
                <div class="text-muted"><?= e($member['member_code']) ?></div>
            </div>
            <?= member_status_badge($member['status']) ?>
        </div>

        <dl class="row mb-0 member-dl">
            <dt class="col-sm-4 col-lg-3">Mobile</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($member['mobile']) ?></dd>

            <dt class="col-sm-4 col-lg-3">Email</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($member['email']) ?></dd>

            <dt class="col-sm-4 col-lg-3">Category</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($member['category_name']) ?></dd>

            <dt class="col-sm-4 col-lg-3">Chapter</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($member['chapter_name']) ?></dd>

            <dt class="col-sm-4 col-lg-3">Assigned coordinator</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($member['coordinator_name'] ?: '—') ?></dd>

            <dt class="col-sm-4 col-lg-3">Last presentation date</dt>
            <dd class="col-sm-8 col-lg-9"><?= e(format_date($member['last_presentation_date'])) ?></dd>

            <dt class="col-sm-4 col-lg-3">Calculated next due date</dt>
            <dd class="col-sm-8 col-lg-9">
                <?= e(format_date($member['next_due_date'])) ?>
                <div class="small text-muted">Last completed presentation + 90 calendar days. Confirmed dates are tracked separately in the history below.</div>
            </dd>

            <dt class="col-sm-4 col-lg-3">Last presentation venue</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($member['venue'] ?: '—') ?></dd>

            <dt class="col-sm-4 col-lg-3">Last presentation chapter</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($member['last_chapter_name'] ?: '—') ?></dd>

            <dt class="col-sm-4 col-lg-3">Remarks</dt>
            <dd class="col-sm-8 col-lg-9"><?= nl2br(e($member['remarks'] ?: '—')) ?></dd>

            <dt class="col-sm-4 col-lg-3">Created by</dt>
            <dd class="col-sm-8 col-lg-9"><?= e($member['created_by_name'] ?: '—') ?></dd>
        </dl>
    </div>
</div>

<div class="card stat-card mt-3">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h3 class="h6 fw-bold mb-0">Presentation history</h3>
            <?php if ($open): ?>
                <a class="btn btn-sm btn-primary" href="<?= e(url('presentation.php?id=' . (int)$open['id'])) ?>">
                    Manage current cycle
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$history): ?>
            <p class="text-muted">No presentations recorded yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Calculated due</th>
                        <th>Confirmed date</th>
                        <th>Completed</th>
                        <th>Venue</th>
                        <th>Chapter</th>
                        <th>Status</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr class="<?= in_array($h['status'], ['pending', 'scheduled'], true) ? 'open-cycle' : '' ?>">
                        <td><?= $cycleNo-- ?></td>
                        <td><?= e(format_date($h['calculated_due_date'])) ?></td>
                        <td><?= e(format_date($h['scheduled_date'])) ?></td>
                        <td><?= e(format_date($h['completed_date'])) ?></td>
                        <td><?= e($h['venue'] ?: '—') ?></td>
                        <td><?= e($h['completed_chapter_name'] ?: '—') ?></td>
                        <td><?= presentation_status_badge(presentation_status($h)) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('presentation.php?id=' . (int)$h['id'])) ?>">Open</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!$open): ?>
        <hr>
        <h4 class="h6 fw-bold">Record a completed presentation</h4>
        <?php if ($member['status'] !== 'active'): ?>
            <p class="small text-muted">This member is inactive, so no next cycle will be created. Reactivate to resume the 90-day cycle.</p>
        <?php else: ?>
            <p class="small text-muted">The next due date will be the date you enter + 90 calendar days.</p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('presentation_action.php')) ?>" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="record_new">
            <input type="hidden" name="member_id" value="<?= (int)$member['id'] ?>">
            <div class="col-md-2">
                <label class="form-label" for="completed_date">Completion date</label>
                <input type="date" class="form-control" id="completed_date" name="completed_date" max="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="rec_venue">Venue</label>
                <input type="text" class="form-control" id="rec_venue" name="venue" maxlength="190">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="rec_chapter">Chapter</label>
                <select class="form-select" id="rec_chapter" name="completed_chapter_id" required>
                    <option value="">Choose…</option>
                    <?php $recDefault = (int)($member['last_presentation_chapter_id'] ?: $member['chapter_id']); ?>
                    <?php foreach (fetch_active_chapters() as $ch): ?>
                        <option value="<?= (int)$ch['id'] ?>" <?= $recDefault === (int)$ch['id'] ? 'selected' : '' ?>><?= e($ch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="rec_notes">Note</label>
                <input type="text" class="form-control" id="rec_notes" name="notes" maxlength="1000">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100">Record</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
