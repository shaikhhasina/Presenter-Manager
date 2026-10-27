PRESENTER MANAGER

=================================================
MODULE 1 — Setup + secure login
=================================================
1. Folder: C:\xampp\htdocs\presenter_management\
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin -> Import -> database/schema.sql -> Go.
4. Open http://localhost/presenter_management/install_admin.php and create the Super Admin.
5. DELETE install_admin.php afterwards.
6. Log in at http://localhost/presenter_management/

Unlock an account (phpMyAdmin SQL, database selected):
UPDATE users SET failed_attempts = 0, locked_until = NULL;

If MySQL root has a password, edit config/database.php (DB_PASS).

=================================================
MODULE 2 — Member management
=================================================
If you already imported schema.sql from Module 1, also import:
  database/module2_alter.sql
(This adds coordinator_id. Skip this if you imported a fresh schema.sql that already includes it.)

Then log in and open Members in the sidebar, or:
  http://localhost/presenter_management/members.php

You can:
- Add, edit, and view members
- Search by name, Member ID, email, or mobile
- Filter by chapter, category, status, and coordinator
- Deactivate / reactivate a member (records are never deleted)
- Chapter Coordinators only see and edit their own chapter

Next due date on the member record = last presentation date + 90 calendar days.
Presentation history records (one row per cycle) are Module 3.

=================================================
MODULE 3 — Presentation history + 90-day cycle
=================================================
1. phpMyAdmin -> select presenter_management -> SQL tab -> paste and run:
     database/module3_upgrade.sql
   (Works on fresh and existing databases. It creates history rows for members
   you added in Module 2. If Part B says "Duplicate column", it was already applied.)
2. Sidebar -> Presentations  (http://localhost/presenter_management/presentations.php)

How it works
- Each member has ONE open presentation (pending/scheduled).
- Calculated due date = last COMPLETED date + 90 calendar days (weekends/holidays included).
- Confirmed (scheduled) date is stored separately and never changes the due date.
- "Record completed" closes the open one, keeps it in history, updates the member's
  last/next dates, and creates the next pending cycle from the ACTUAL completion date.
- Status badges (calculated live, never stored):
    Overdue     due date passed (or scheduled date passed but not completed)
    Due         due within the next 7 days (or today)
    Approaching due in 8-30 days (settings.approaching_days)
    Upcoming    due in more than 30 days
    Scheduled   a future confirmed date exists
    Completed   finished (history rows)
- Once a member has history, "Last presentation date" on the member form is locked;
  use Record completed instead. Inactive members are excluded from open lists.

New files
  presentations.php         list + filters (status, chapter, search)
  presentation.php          one cycle: schedule, reschedule, clear, record completed, activity log
  presentation_action.php   POST handler for the forms above
  includes/presentations.php  all cycle logic (status, scheduling, completion)
  database/module3_upgrade.sql

Fixed in this update: member search in members.php used the same named parameter
4 times, which PDO (native prepares) rejects. It now uses :q1 to :q4.

=================================================
MODULE 4 — Dashboard + Calendar
=================================================
No database changes. Just replace the files.

Dashboard (dashboard.php): total/active members, due this month, due next month,
upcoming (due within 30 days), overdue, emails sent/failed (filled by Module 6),
3 Chart.js charts and a "Needs attention" list. All numbers come from the database;
Chapter Coordinators only see their own chapter.
Charts load Chart.js from a CDN, so the browser needs internet access.

Calendar (calendar.php): monthly view (Mon-Sun). Outlined chip = calculated due date,
solid blue chip with a tick = confirmed date, green = completed. Filter by chapter
and status; click a name to confirm/change the schedule on presentation.php.
Previous/next/today buttons keep your filters.

Changed: includes/footer.php (extra scripts hook), includes/header.php (Calendar link
enabled), includes/presentations.php (chapter_scope_sql), assets/css/style.css.

=================================================
MODULE 5 — Reports + CSV export
=================================================
No database changes. Replace the files, then open Reports in the sidebar.

Reports (reports.php): Upcoming (next 30/60/90 days), Overdue, Chapter-wise summary,
Monthly (pick a month: due, confirmed and completed). Super Admin can filter by chapter;
Chapter Coordinators only ever see their own chapter. Inactive members are excluded
from Upcoming, Overdue and open items in Monthly.
"Export CSV" downloads ALL rows of the report you are viewing (not just the current page),
with the same filters. Files open in Excel (UTF-8) and cells starting with = + - @ are
neutralised so they cannot run as formulas.

New: includes/reports.php, reports.php.  Changed: includes/bootstrap.php, includes/header.php.

Update: presentation tabs now show counts; All open = Overdue + Due + Approaching + Upcoming + Scheduled.

=================================================
MODULE 6 — Email system (sender: 360sociale@gmail.com)
=================================================
STEP 1 - Database
  phpMyAdmin -> presenter_management -> SQL -> run database/module6_email.sql

STEP 2 - Gmail App Password (Gmail will NOT accept the normal password)
  a. Sign in to 360sociale@gmail.com -> Google Account -> Security -> turn ON 2-Step Verification.
  b. Open https://myaccount.google.com/apppasswords -> create one named "Presenter Manager".
  c. Copy the 16-character code into config/mail.php:   const MAIL_PASSWORD = 'abcd efgh ijkl mnop';
     (spaces are ignored). Keep this file private; config/.htaccess already blocks it from the web.

STEP 3 - Test
  Log in as Super Admin -> Email Center -> "Send a test email". Check the inbox and Spam folder.
  If you see "certificate verify failed" on XAMPP, set in config/mail.php:
     const MAIL_CA_FILE = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
  (or set openssl.cafile in php.ini and restart Apache). The PHP "openssl" extension must be enabled
  (it is by default in XAMPP).

STEP 4 - Settings (sidebar -> Settings, Super Admin)
  Turn each automatic email on/off, choose the follow-up delay, sender name, reply-to address and
  the admin alert address (optional, e.g. 360sociale@gmail.com).

STEP 5 - Run it automatically once a day
  Windows (XAMPP) Task Scheduler -> Create Basic Task -> Daily 08:00 -> Start a program:
     Program : C:\xampp\php\php.exe
     Arguments: C:\xampp\htdocs\presenter_management\cron_reminders.php
  Live server (Hostinger/cPanel) cron job, daily:  /usr/bin/php /path/to/presenter_management/cron_reminders.php
  Each run adds a line to logs/reminders.log. You can also press "Send due reminders now" in Email Center.

What gets sent
  Monthly reminder   Once per month, to active members whose due date falls in that month.
  30-day reminder    (off by default) once per cycle when the due date is within 30 days.
  Follow-up          Once per cycle if a reminder went out N days ago and date/venue are still
                     unconfirmed.
  Overdue summary    Once per week to the admin alert address, listing overdue members.
  Not emailed        Inactive members, members without an email, and members who already confirmed a
                     future date + venue.
  Manual             "Email reminder now" on a presentation page; "Resend" / "Retry" in Email Center.

Safety
  - Every automatic email has a unique key (email_logs.dedupe_key): it can never be sent twice for the
    same member + cycle, even if the job runs twice.
  - Failed emails are retried automatically up to 3 times (within 7 days). A wrong password / lost
    connection / Gmail daily limit stops the run instead of failing every email, and does not use up retries.
  - Max 100 emails per run (MAIL_MAX_PER_RUN in config/mail.php). Gmail allows about 500 per day.
    Anything left over goes out on the next run.
  - Chapter Coordinators only see emails of their own chapter. Test emails, bulk send and Settings are
    Super Admin only. All forms use CSRF tokens; message previews are shown in a sandboxed frame.

New files
  config/mail.php, includes/smtp.php, includes/emails.php, emails.php, email_view.php,
  email_action.php, settings.php, cron_reminders.php, database/module6_email.sql
Changed
  includes/bootstrap.php, includes/header.php (Email Center + Settings links on), dashboard.php
  (email cards link to the Email Center), presentation.php ("Email reminder now" button).

=================================================
PUBLIC MEMBER REGISTRATION
=================================================
1. phpMyAdmin -> presenter_management -> SQL -> run database/module_registration.sql
2. Share this link (no login needed):  http://localhost/presenter_management/register.php
3. Admins review submissions at Registrations in the sidebar: Approve creates the member
   (new Member ID, history and 90-day cycle) ; Reject keeps a record with an optional reason.

Who can open what
  PUBLIC (no login): register.php, login.php           - nothing else
  LOGGED-IN admins : dashboard, members, presentations, calendar, reports, registrations
  Chapter Coordinators only see/approve their own chapter's data.

Spam protection on the public form: CSRF token, hidden honeypot field, max 5 sign-ups per
IP address per hour, duplicate-email check, server-side validation. Nothing from this form
becomes a member until an admin approves it. Remember to DELETE install_admin.php.

=================================================
Default files
=================================================
members.php        Member list, search, filters
member_form.php    Add / edit form
member_view.php    Member details
member_action.php  Deactivate / reactivate (POST only)
includes/members.php  Shared helper functions
database/module2_alter.sql  Upgrade for existing Module 1 databases

=================================================
MODULE 7 — Admin Users, Chapters & Categories, Activity Log
=================================================
STEP 1 - Database
  phpMyAdmin -> presenter_management -> SQL -> run database/module7_admin.sql
  (creates the audit_logs table; safe to run twice)

STEP 2 - Copy the new/changed files over your project (do NOT overwrite config/mail.php)

Sidebar (Super Admin only)
  Admin Users            users.php        list, search, filter by role / chapter / status
                         user_form.php    add user, edit name/email/role/chapter,
                                          reset password (temporary password shown once),
                                          unlock account, deactivate / reactivate
  Chapters & Categories  masters.php      add, rename, deactivate / activate (never deleted)
  Activity Log           audit_log.php    who did what and when; filter by person, action, dates
Everyone (top-right menu -> My Profile)
  profile.php            change your own password

Safety rules built in
  - Only Super Admin can open these pages (Chapter Coordinators get 403).
  - You cannot deactivate yourself, change your own role, or remove / deactivate the last
    active Super Admin.
  - A Chapter Coordinator must have a chapter; Super Admin works across all chapters.
  - Passwords: 10-72 characters, letters + numbers, stored hashed. Users are never deleted.
  - Deactivated users are logged out on their next click.
  - Logged actions: login, failed login, logout, user created/updated/activated/deactivated/
    unlocked, password reset/changed, chapter and category changes.

New files
  includes/users.php, users.php, user_form.php, user_action.php, masters.php,
  masters_action.php, audit_log.php, profile.php, database/module7_admin.sql
Changed
  includes/bootstrap.php, includes/header.php, login.php, logout.php
