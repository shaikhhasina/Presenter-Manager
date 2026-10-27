<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('presentations.php');
}

csrf_verify();

$action = (string)($_POST['action'] ?? '');

// ---- Record a completed presentation for a member that has no open cycle ----
if ($action === 'record_new') {
    $memberId = (int)($_POST['member_id'] ?? 0);
    $member   = $memberId > 0 ? load_member($memberId) : null;

    if (!$member) {
        http_response_code(404);
        exit('Member not found, or you do not have permission to change this record.');
    }

    [$ok, $msg] = record_completed_presentation(
        $memberId,
        (string)($_POST['completed_date'] ?? ''),
        (string)($_POST['venue'] ?? ''),
        (string)($_POST['notes'] ?? ''),
        null,
        ((int)($_POST['completed_chapter_id'] ?? 0)) ?: null
    );
    flash($ok ? 'success' : 'danger', $msg);
    redirect('member_view.php?id=' . $memberId);
}

// ---- Actions on an existing presentation ----
$id = (int)($_POST['id'] ?? 0);
$p  = $id > 0 ? load_presentation($id) : null;

if (!$p) {
    http_response_code(404);
    exit('Presentation not found, or you do not have permission to change this record.');
}

switch ($action) {
    case 'schedule':
        [$ok, $msg] = schedule_presentation(
            $id,
            (string)($_POST['scheduled_date'] ?? ''),
            (string)($_POST['venue'] ?? ''),
            isset($_POST['details_confirmed']),
            (string)($_POST['notes'] ?? '')
        );
        flash($ok ? 'success' : 'danger', $msg);
        redirect('presentation.php?id=' . $id);

    case 'clear_schedule':
        [$ok, $msg] = clear_presentation_schedule($id);
        flash($ok ? 'success' : 'danger', $msg);
        redirect('presentation.php?id=' . $id);

    case 'complete':
        [$ok, $msg] = record_completed_presentation(
            (int)$p['member_id'],
            (string)($_POST['completed_date'] ?? ''),
            (string)($_POST['venue'] ?? ''),
            (string)($_POST['notes'] ?? ''),
            $id,
            ((int)($_POST['completed_chapter_id'] ?? 0)) ?: null
        );
        flash($ok ? 'success' : 'danger', $msg);
        redirect($ok ? 'member_view.php?id=' . (int)$p['member_id'] : 'presentation.php?id=' . $id);

    default:
        flash('danger', 'Unknown action.');
        redirect('presentation.php?id=' . $id);
}
