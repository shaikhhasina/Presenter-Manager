<?php
// PUBLIC page: anyone can open this. It never shows member data and never logs anyone in.
require_once __DIR__ . '/includes/bootstrap.php';

$errors = [];
$done   = isset($_GET['done']);
$old    = ['full_name' => '', 'mobile' => '', 'email' => '', 'category_id' => '', 'chapter_id' => '',
           'last_presentation_date' => '', 'venue' => '', 'last_presentation_chapter_id' => '', 'member_status' => 'active', 'remarks' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $old = array_merge($old, array_map(static fn($v) => is_string($v) ? $v : '', array_intersect_key($_POST, $old)));
    $ip  = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    if (trim((string)($_POST['website'] ?? '')) !== '') {
        // Hidden "honeypot" field filled in = a bot. Pretend it worked, save nothing.
        redirect('register.php?done=1');
    }

    if (registration_rate_limited($ip)) {
        $errors[] = 'Too many registrations from your network. Please try again later.';
    } else {
        [$data, $errors] = validate_registration_input($_POST);
        if (!$errors) {
            try {
                $code = save_registration($data, $ip);
                redirect('register.php?done=1&id=' . urlencode($code));
            } catch (PDOException $ex) {
                error_log('save_registration: ' . $ex->getMessage());
                $errors[] = 'Could not save your registration. Please try again later.';
            }
        }
    }
}

try {
    $chapters   = fetch_active_chapters();
    $categories = fetch_active_categories();
} catch (PDOException $ex) {
    $chapters = $categories = [];
}

$pageTitle = 'Member Registration';
$authWide  = true;
require __DIR__ . '/includes/auth_header.php';
?>

<?php if ($done): ?>
    <div class="alert alert-success">
        <strong>Thank you!</strong> Your registration was received.
        <?php $newId = (string)($_GET['id'] ?? ''); if (preg_match('/^MEM-\d{4,}$/', $newId)): ?>
            Your Member ID is <strong><?= e($newId) ?></strong>.
        <?php endif; ?>
        An administrator will review it and contact you.
    </div>
    <a href="<?= e(url('register.php')) ?>" class="btn btn-outline-primary w-100">Register another member</a>
<?php else: ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" action="" novalidate>
    <?= csrf_field() ?>
    <div style="position:absolute;left:-9999px" aria-hidden="true">
        <label for="website">Leave this empty</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="member_code">Member ID</label>
            <input type="text" class="form-control" id="member_code" value="Auto-generated" disabled>
            <div class="form-text">Shown to you after you submit.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="member_status">Member status</label>
            <select class="form-select" id="member_status" name="member_status">
                <option value="active" <?= $old['member_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $old['member_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="full_name">Full name *</label>
            <input type="text" class="form-control" id="full_name" name="full_name" maxlength="150"
                   value="<?= e($old['full_name']) ?>" required>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="mobile">Mobile number *</label>
            <input type="tel" class="form-control" id="mobile" name="mobile" value="<?= e($old['mobile']) ?>" required>
        </div>
        <div class="col-md-7">
            <label class="form-label" for="email">Email address *</label>
            <input type="email" class="form-control" id="email" name="email" maxlength="190"
                   value="<?= e($old['email']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="chapter_id">Chapter name *</label>
            <select class="form-select" id="chapter_id" name="chapter_id" required>
                <option value="">Choose…</option>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?= (int)$ch['id'] ?>" <?= (int)$old['chapter_id'] === (int)$ch['id'] ? 'selected' : '' ?>><?= e($ch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="category_id">Category *</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Choose…</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= (int)$old['category_id'] === (int)$cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="last_presentation_date">Last presentation date</label>
            <input type="date" class="form-control" id="last_presentation_date" name="last_presentation_date"
                   value="<?= e($old['last_presentation_date']) ?>" max="<?= e(date('Y-m-d')) ?>">
            <div class="form-text">Leave empty if you have not presented yet.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="venue">Last presentation venue</label>
            <input type="text" class="form-control" id="venue" name="venue" maxlength="190" value="<?= e($old['venue']) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="last_presentation_chapter_id">Last presentation chapter</label>
            <select class="form-select" id="last_presentation_chapter_id" name="last_presentation_chapter_id">
                <option value="">Choose…</option>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?= (int)$ch['id'] ?>" <?= (int)$old['last_presentation_chapter_id'] === (int)$ch['id'] ? 'selected' : '' ?>><?= e($ch['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">Chapter where it took place. Leave empty if you have not presented yet.</div>
        </div>
        <div class="col-12">
            <label class="form-label" for="remarks">Remarks</label>
            <textarea class="form-control" id="remarks" name="remarks" rows="2" maxlength="1000"><?= e($old['remarks']) ?></textarea>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg w-100 mt-4">
        <i class="bi bi-person-plus me-1"></i> Submit registration
    </button>
    <p class="small text-muted text-center mt-3 mb-0">Your details are reviewed by an administrator before you become a member.</p>
</form>
<?php endif; ?>

<?php require __DIR__ . '/includes/auth_footer.php'; ?>
