<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('registrations.php');
}
csrf_verify();

$id  = (int)($_POST['id'] ?? 0);
$reg = $id > 0 ? load_registration($id) : null;

if (!$reg) {
    http_response_code(404);
    exit('Registration not found, or you do not have permission to change it.');
}

switch ((string)($_POST['action'] ?? '')) {
    case 'approve':
        [$ok, $msg] = approve_registration($id);
        break;
    case 'reject':
        [$ok, $msg] = reject_registration($id, (string)($_POST['reason'] ?? ''));
        break;
    default:
        [$ok, $msg] = [false, 'Unknown action.'];
}

flash($ok ? 'success' : 'danger', $msg);
redirect('registrations.php');
