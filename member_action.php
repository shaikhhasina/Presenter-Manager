<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('members.php');
}

csrf_verify();

$id     = (int)($_POST['id'] ?? 0);
$action = (string)($_POST['action'] ?? '');
$member = $id > 0 ? load_member($id) : null;

if (!$member) {
    http_response_code(404);
    exit('Member not found, or you do not have permission to change this record.');
}

if (!in_array($action, ['activate', 'deactivate'], true)) {
    flash('danger', 'Unknown action.');
    redirect('member_view.php?id=' . $id);
}

$newStatus = $action === 'activate' ? 'active' : 'inactive';

$stmt = db()->prepare('UPDATE members SET status = :status WHERE id = :id');
$stmt->execute(['status' => $newStatus, 'id' => $id]);

if ($action === 'activate') {
    ensure_open_cycle($id);
}

flash('success', $action === 'activate' ? 'Member reactivated.' : 'Member deactivated.');
redirect('member_view.php?id=' . $id);
