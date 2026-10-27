<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_role(['super_admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('masters.php');
}
csrf_verify();

$action = (string)($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? 0);
$name   = trim(preg_replace('/\s+/', ' ', (string)($_POST['name'] ?? '')) ?? '');
$code   = normalize_chapter_code((string)($_POST['code'] ?? ''));

/** True if a value already exists in a table column (ignoring one row). */
function master_exists(string $table, string $column, string $value, int $ignoreId = 0): bool
{
    $stmt = db()->prepare("SELECT id FROM `$table` WHERE `$column` = :v AND id <> :id LIMIT 1");
    $stmt->execute(['v' => $value, 'id' => $ignoreId]);
    return (bool)$stmt->fetch();
}

function master_row(string $table, int $id): ?array
{
    $stmt = db()->prepare("SELECT * FROM `$table` WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

$valid = ['add_chapter', 'update_chapter', 'toggle_chapter', 'add_category', 'update_category', 'toggle_category'];
if (!in_array($action, $valid, true)) {
    flash('danger', 'Unknown action.');
    redirect('masters.php');
}

$isChapter = str_ends_with($action, '_chapter');
$table     = $isChapter ? 'chapters' : 'categories';
$label     = $isChapter ? 'Chapter' : 'Category';
$entity    = $isChapter ? 'chapter' : 'category';

try {
    if (str_starts_with($action, 'add_') || str_starts_with($action, 'update_')) {
        $isAdd = str_starts_with($action, 'add_');
        $row   = null;

        if (!$isAdd) {
            $row = $id > 0 ? master_row($table, $id) : null;
            if (!$row) {
                flash('danger', $label . ' not found.');
                redirect('masters.php');
            }
        }

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $errors[] = $label . ' name must be 2 to 100 characters.';
        } elseif (master_exists($table, 'name', $name, $isAdd ? 0 : $id)) {
            $errors[] = 'A ' . strtolower($label) . ' named "' . $name . '" already exists.';
        }
        if ($isChapter) {
            if ($code === '' || strlen($code) > 20) {
                $errors[] = 'Chapter code is required (letters, numbers and dashes, up to 20 characters).';
            } elseif (master_exists('chapters', 'code', $code, $isAdd ? 0 : $id)) {
                $errors[] = 'Chapter code "' . $code . '" is already used.';
            }
        }

        if ($errors) {
            foreach ($errors as $err) { flash('danger', $err); }
            redirect('masters.php');
        }

        if ($isAdd) {
            if ($isChapter) {
                db()->prepare('INSERT INTO chapters (name, code) VALUES (:n, :c)')->execute(['n' => $name, 'c' => $code]);
            } else {
                db()->prepare('INSERT INTO categories (name) VALUES (:n)')->execute(['n' => $name]);
            }
            $newId = (int)db()->lastInsertId();
            audit_log(strtolower($label) . '_created', $entity, $newId, $name . ($isChapter ? ' (' . $code . ')' : ''));
            flash('success', $label . ' added.');
        } else {
            if ($isChapter) {
                db()->prepare('UPDATE chapters SET name = :n, code = :c WHERE id = :id')->execute(['n' => $name, 'c' => $code, 'id' => $id]);
            } else {
                db()->prepare('UPDATE categories SET name = :n WHERE id = :id')->execute(['n' => $name, 'id' => $id]);
            }
            $notes = array_filter([
                change_note('Name', $row['name'], $name),
                $isChapter ? change_note('Code', $row['code'], $code) : null,
            ]);
            audit_log(strtolower($label) . '_updated', $entity, $id, implode('; ', $notes) ?: 'No change');
            flash('success', $label . ' saved.');
        }
    } else { // toggle
        $row = $id > 0 ? master_row($table, $id) : null;
        if (!$row) {
            flash('danger', $label . ' not found.');
            redirect('masters.php');
        }
        $new = (int)$row['is_active'] === 1 ? 0 : 1;

        db()->prepare("UPDATE `$table` SET is_active = :a WHERE id = :id")->execute(['a' => $new, 'id' => $id]);
        audit_log(strtolower($label) . ($new ? '_activated' : '_deactivated'), $entity, $id, $row['name']);
        flash('success', $label . ($new ? ' activated.' : ' deactivated. Existing members keep it; it is hidden from new records.'));
    }
} catch (PDOException $ex) {
    error_log('masters_action error: ' . $ex->getMessage());
    flash('danger', 'Could not save. Please try again.');
}

redirect('masters.php');
