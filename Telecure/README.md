# TeleCure – Telehealth Platform

PHP + MySQL telehealth app: patient/doctor accounts, appointment booking, chat,
video consultations (Jitsi), prescriptions, reviews and an admin panel.

## Requirements
- PHP **7.1+** (tested on 8.3) with PDO MySQL (mbstring optional)
- MySQL / MariaDB, Apache (WAMP/XAMPP) – any folder name works (e.g. `htdocs/Devsync/Telecure`)

## Setup
1. Copy the project into your web root, e.g. `C:\wamp64\www\Devsync\Telecure`.
2. In phpMyAdmin, import `telecure_db.sql` (creates `telecure_db` with demo data).
   - **Already have the old database?** Run `migrations/001_fixes.sql` once instead.
3. Check DB settings in `includes/config.php` (`DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`).
4. Make sure `uploads/avatars/` is writable.
5. Open `http://localhost/Devsync/Telecure/`.
6. Optional: uncomment and edit the `ErrorDocument 404` line in `.htaccess` for your folder.

## Demo accounts (password for all: `password`)
| Role | Email |
|---|---|
| Doctor + Administrator | sarah@telecure.com |
| Doctor | michael@telecure.com, priya@telecure.com |
| Patient | patient@telecure.com |

## Time zone
All appointment times use `APP_TIMEZONE` (default `Africa/Johannesburg`, set at the top of
`includes/config.php`), for both PHP and MySQL, so it no longer depends on `php.ini`. Change it if
you are in another time zone.

## Using a real e-mail address
The demo accounts use fake addresses (`@telecure.com`), so e-mails to them go nowhere. To receive real
e-mails: sign in, open **My Profile**, type your real address in *Email*, enter your current password
under *Change Password* and save. Use the new address to sign in from then on (the administrator keeps
admin rights). Or simply register a new account with your real address.

## Passwords and live booking
- New passwords (register, forgot-password, My Profile) need **8+ characters, at least one number and one special character**. A live checklist shows the rules as the person types, and the server enforces them. The demo accounts keep the old password `password` until it is changed.
- Booking is live: time slots are re-checked every 5 seconds (and when the tab regains focus) on both the Book Appointment window and the doctor's profile page, a slot someone else just took disappears or is un-selected with a notice, and the appointment list refreshes itself when a request is made/confirmed/cancelled. Double-bookings are blocked on the server.

## Roles
- **Patient:** find doctors, book/cancel appointments, chat, join video calls, view prescriptions, review doctors after a completed consultation.
- **Doctor:** confirm / complete / cancel appointments, chat, video, write prescriptions (only once verified).
- **Administrator** (`users.is_admin = 1`): verify doctors, activate/deactivate users.

## Notes
- New doctors are hidden until an administrator verifies them.
- Password reset e-mails are sent over SMTP (see **E-mail setup** below). If sending fails and you are browsing from localhost, the page shows the technical reason and a direct reset link so you can keep testing. Visitors on a live site only ever see a generic message.
- Video uses the public Jitsi server (meet.jit.si); this is not a guarantee of end-to-end encryption. Review privacy/POPIA requirements before real patient use.
- Consultation notes in the video page are saved in the browser only.

## E-mail setup (password reset) – done on the website
Sign in as an administrator (`sarah@telecure.com`) and open **Email Settings** in the sidebar
(or Admin Panel > Email Settings). No files to edit.

1. Pick a provider under *Quick setup* (Gmail, Outlook, Brevo, Mailtrap) or enter your own SMTP details.
2. **Gmail:** turn on 2-Step Verification, create an *App password* at
   <https://myaccount.google.com/apppasswords> and paste it as the password
   (your normal Gmail password will not work).
3. Check the *website address* (e.g. `http://localhost/Devsync/Telecure`) – it is used inside e-mailed links.
4. Tick **Enable sending e-mails**, then click **Save & send test e-mail to me**.
   A readable error is shown if the server refuses the details.
5. Test the real thing: *Forgot password* on the login page.

The SMTP password is stored **encrypted** in the database (`app_settings` table); the key is the
auto-created file `includes/.mail_secret_key` (blocked from the web and ignored by Git). If you
delete that file you simply re-enter the password. If you move the site to another server, copy the
key file too or re-enter the password.

`includes/mail_config.php` is now only an optional set of defaults; anything saved on the
Email Settings page overrides it. Reset e-mails use the bundled PHPMailer (`lib/PHPMailer/`).

Troubleshooting: on WAMP make sure `extension=openssl` is enabled in `php.ini`. A "certificate verify
failed" error means PHP lacks a CA bundle: set `openssl.cafile` in `php.ini`, or as a last resort on your
own laptop untick *Verify the mail server's security certificate* on the Email Settings page.
