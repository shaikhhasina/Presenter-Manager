<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$member = $id > 0 ? load_member($id) : null;

if ($id > 0 && !$member) {
    http_response_code(404);
    exit('Member not found, or you do not have permission to edit this record.');
}

$isEdit     = $member !== null;
$pageTitle  = $isEdit ? 'Edit member' : 'Add member';
$activePage = 'members';
$user       = current_user();

$errors = [];
$old = [
    'member_code'            => $member['member_code'] ?? next_member_code(),
    'full_name'              => $member['full_name'] ?? '',
    'mobile'                 => $member['mobile'] ?? '',
    'email'                  => $member['email'] ?? '',
    'category_id'            => $member['category_id'] ?? '',
    'chapter_id'             => $member['chapter_id'] ?? ($user['chapter_id'] ?? ''),
    'last_presentation_date' => $member['last_presentation_date'] ?? '',
    'venue'                  => $member['venue'] ?? '',
    'last_presentation_chapter_id' => $member['last_presentation_chapter_id'] ?? '',
    'status'                 => $member['status'] ?? 'active',
    'remarks'                => $member['remarks'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    [$data, $errors] = validate_member_input($_POST, $isEdit ? (int)$member['id'] : null);
    $old = array_merge($old, [
        'full_name'              => (string)($_POST['full_name'] ?? ''),
        'mobile'                 => (string)($_POST['mobile'] ?? ''),
        'email'                  => (string)($_POST['email'] ?? ''),
        'category_id'            => (string)($_POST['category_id'] ?? ''),
        'chapter_id'             => (string)($_POST['chapter_id'] ?? ''),
        'last_presentation_date' => (string)($_POST['last_presentation_date'] ?? $old['last_presentation_date']),
        'venue'                  => (string)($_POST['venue'] ?? ''),
        'last_presentation_chapter_id' => (string)($_POST['last_presentation_chapter_id'] ?? ''),
        'status'                 => (string)($_POST['status'] ?? 'active'),
        'remarks'                => (string)($_POST['remarks'] ?? ''),
    ]);

    if (!$errors) {
        try {
            $savedId = save_member($data, $isEdit ? (int)$member['id'] : null);
            flash('success', $isEdit ? 'Member updated.' : 'Member added.');
            redirect('member_view.php?id=' . $savedId);
        } catch (PDOException $ex) {
            error_log('save_member: ' . $ex->getMessage());
            $errors[] = 'Could not save the member. If you just upgraded from Module 1, import database/module2_alter.sql in phpMyAdmin.';
        }
    }
}

$chapters     = fetch_active_chapters();
$categories   = fetch_active_categories();

require __DIR__ . '/includes/header.php';
?>

<div class="mb-3">
    <a href="<?= e(url('members.php')) ?>" class="text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to members
    </a>
</div>

<?php if (!is_super_admin() && empty($user['chapter_id'])): ?>
    <div class="alert alert-warning">
        Your account has no chapter assigned. Ask a Super Admin to assign you to a chapter before adding members.
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card stat-card">
    <div class="card-body">
        <form method="post" action="" novalidate>
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="member_code">Member ID</label>
                    <input type="text" class="form-control" id="member_code" value="<?= e((string)$old['member_code']) ?>" readonly>
                    <div class="form-text">Auto-generated. Cannot be changed.</div>
                </div>

                <div class="col-md-8">
                    <label class="form-label" for="full_name">Full name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?= e((string)$old['full_name']) ?>" maxlength="150" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="mobile">Mobile number</label>
                    <input type="tel" class="form-control" id="mobile" name="mobile"
                           value="<?= e((string)$old['mobile']) ?>" required>
                </div>

                <div class="col-md-8">
                    <label class="form-label" for="email">Email address</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?= e((string)$old['email']) ?>" maxlength="190" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="category_id">Category</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Choose…</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int)$cat['id'] ?>" <?= (int)$old['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="chapter_id">Chapter name</label>
                    <?php if (is_super_admin()): ?>
                        <select class="form-select" id="chapter_id" name="chapter_id" required>
                            <option value="">Choose…</option>
                            <?php foreach ($chapters as $ch): ?>
                                <option value="<?= (int)$ch['id'] ?>" <?= (int)$old['chapter_id'] === (int)$ch['id'] ? 'selected' : '' ?>>
                                    <?= e($ch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="hidden" name="chapter_id" value="<?= (int)($user['chapter_id'] ?? 0) ?>">
                        <input type="text" class="form-control" value="<?php
                            $label = 'No chapter assigned';
                            foreach ($chapters as $ch) {
                                if ((int)$ch['id'] === (int)($user['chapter_id'] ?? 0)) {
                                    $label = $ch['name'];
                                    break;
                                }
                            }
                            echo e($label);
                        ?>" disabled>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="last_presentation_date">Last presentation date</label>
                    <?php $lockDate = $isEdit && member_has_history((int)$member['id']); ?>
                    <input type="date" class="form-control" id="last_presentation_date" name="last_presentation_date"
                           value="<?= e((string)$old['last_presentation_date']) ?>" max="<?= e(date('Y-m-d')) ?>"
                           <?= $lockDate ? 'disabled' : '' ?>>
                    <?php if ($lockDate): ?>
                        <div class="form-text">Locked: this member has presentation history. Use
                            <a href="<?= e(url('member_view.php?id=' . (int)$member['id'])) ?>">Record completed</a> to add a new one.</div>
                    <?php else: ?>
                        <div class="form-text">Optional. Next due date = this date + 90 calendar days.</div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="venue">Last presentation venue</label>
                    <input type="text" class="form-control" id="venue" name="venue"
                           value="<?= e((string)$old['venue']) ?>" maxlength="190">
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="last_presentation_chapter_id">Last presentation chapter</label>
                    <select class="form-select" id="last_presentation_chapter_id" name="last_presentation_chapter_id">
                        <option value="">Choose…</option>
                        <?php foreach ($chapters as $ch): ?>
                            <option value="<?= (int)$ch['id'] ?>" <?= (int)$old['last_presentation_chapter_id'] === (int)$ch['id'] ? 'selected' : '' ?>>
                                <?= e($ch['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="status">Member status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="3" maxlength="2000"><?= e((string)$old['remarks']) ?></textarea>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <?= $isEdit ? 'Save changes' : 'Add member' ?>
                </button>
                <a href="<?= e($isEdit ? url('member_view.php?id=' . (int)$member['id']) : url('members.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
