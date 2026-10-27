<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Logout only works through a POST form with a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

csrf_verify();
if (is_logged_in()) {
    audit_log('logout', 'user', (int)current_user()['id'], (string)current_user()['email']);
}
logout_user();

start_secure_session();
flash('success', 'You have been logged out.');
redirect('login.php');
