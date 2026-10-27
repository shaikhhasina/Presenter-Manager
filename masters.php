<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['super_admin']);

$pageTitle  = 'Chapters & Categories';
$activePage = 'masters';

$chapters   = fetch_all_chapters();
$categories = fetch_all_categories();

require __DIR__ . '/includes/header.php';
?>

<p class="text-muted">Manage the chapters and member categories used across the app. Items are never deleted, only deactivated, so existing members and history stay intact. Inactive items disappear from dropdowns for new records.</p>

<div class="row g-3">
    <!-- ================= CHAPTERS ================= -->
    <div class="col-xl-7">
        <div class="card stat-card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Chapters</h2>

                <form method="post" action="<?= e(url('masters_action.php')) ?>" class="row g-2 align-items-end mb-3">
                    <?= csrf_field() ?><input type="hidden" name="action" value="add_chapter">
                    <div class="col-sm-6">
                        <label class="form-label small mb-1" for="new_chapter_name">New chapter name</label>
                        <input type="text" class="form-control" id="new_chapter_name" name="name" maxlength="100" required>
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label small mb-1" for="new_chapter_code">Code</label>
                        <input type="text" class="form-control text-uppercase" id="new_chapter_code" name="code" maxlength="20" required placeholder="e.g. NAG">
                    </div>
                    <div class="col-sm-3"><button class="btn btn-primary w-100">Add</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Code</th><th>Members</th><th>Users</th><th>Status</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($chapters as $c): $fid = 'ch' . (int)$c['id']; ?>
                            <tr class="<?= (int)$c['is_active'] ? '' : 'text-muted' ?>">
                                <td>
                                    <form id="<?= e($fid) ?>" method="post" action="<?= e(url('masters_action.php')) ?>">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="update_chapter">
                                    </form>
                                    <input form="<?= e($fid) ?>" type="text" class="form-control form-control-sm" name="name" maxlength="100" required value="<?= e($c['name']) ?>">
                                </td>
                                <td style="width:110px">
                                    <input form="<?= e($fid) ?>" type="text" class="form-control form-control-sm text-uppercase" name="code" maxlength="20" required value="<?= e($c['code']) ?>">
                                </td>
                                <td><?= (int)$c['member_count'] ?></td>
                                <td><?= (int)$c['user_count'] ?></td>
                                <td><?= (int)$c['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                                <td class="text-end text-nowrap">
                                    <button form="<?= e($fid) ?>" class="btn btn-sm btn-outline-primary">Save</button>
                                    <form method="post" action="<?= e(url('masters_action.php')) ?>" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="toggle_chapter">
                                        <button class="btn btn-sm <?= (int)$c['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                            <?= (int)$c['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= CATEGORIES ================= -->
    <div class="col-xl-5">
        <div class="card stat-card">
            <div class="card-body">
                <h2 class="h6 fw-bold mb-3">Member categories</h2>

                <form method="post" action="<?= e(url('masters_action.php')) ?>" class="row g-2 align-items-end mb-3">
                    <?= csrf_field() ?><input type="hidden" name="action" value="add_category">
                    <div class="col-sm-8">
                        <label class="form-label small mb-1" for="new_category_name">New category name</label>
                        <input type="text" class="form-control" id="new_category_name" name="name" maxlength="100" required>
                    </div>
                    <div class="col-sm-4"><button class="btn btn-primary w-100">Add</button></div>
                </form>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Members</th><th>Status</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($categories as $c): $fid = 'ct' . (int)$c['id']; ?>
                            <tr class="<?= (int)$c['is_active'] ? '' : 'text-muted' ?>">
                                <td>
                                    <form id="<?= e($fid) ?>" method="post" action="<?= e(url('masters_action.php')) ?>">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="update_category">
                                    </form>
                                    <input form="<?= e($fid) ?>" type="text" class="form-control form-control-sm" name="name" maxlength="100" required value="<?= e($c['name']) ?>">
                                </td>
                                <td><?= (int)$c['member_count'] ?></td>
                                <td><?= (int)$c['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td>
                                <td class="text-end text-nowrap">
                                    <button form="<?= e($fid) ?>" class="btn btn-sm btn-outline-primary">Save</button>
                                    <form method="post" action="<?= e(url('masters_action.php')) ?>" class="d-inline">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="toggle_category">
                                        <button class="btn btn-sm <?= (int)$c['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                            <?= (int)$c['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
