<?php
declare(strict_types=1);

/*
 * Scheduled job: sends the reminder emails that are due.
 * Command line only (it refuses to run from a browser).
 *
 * Windows (XAMPP) - Task Scheduler, run daily at 08:00:
 *   Program : C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\presenter_management\cron_reminders.php
 *
 * Linux / cPanel / Hostinger cron (daily 08:00):
 *   0 8 * * *  /usr/bin/php /home/USER/public_html/presenter_management/cron_reminders.php
 *
 * Safe to run more than once a day: every email is only created once.
 * A one-line summary is added to logs/reminders.log each time.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once __DIR__ . '/includes/bootstrap.php';

try {
    $res  = run_email_reminders(null, false);
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $res['message'];
    if ($res['notes']) {
        $line .= ' ' . implode(' ', $res['notes']);
    }
    $code = $res['ok'] ? 0 : 1;
} catch (Throwable $ex) {
    $line = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $ex->getMessage();
    $code = 2;
}

@file_put_contents(__DIR__ . '/logs/reminders.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
echo $line, PHP_EOL;
exit($code);
