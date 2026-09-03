# TeleCure — Telemedicine Platform
## Complete Setup Guide for WAMP/XAMPP

---

## 📁 Project Structure

```
telecure/
├── index.php              ← Landing page
├── register.php           ← Patient / Doctor registration
├── login.php              ← Login
├── logout.php             ← Session destroy
├── dashboard.php          ← Role-aware dashboard
├── find_doctors.php       ← Search & filter doctors (patient)
├── doctor_profile.php     ← Doctor public profile + reviews
├── appointments.php       ← Book & manage appointments
├── chat.php               ← Real-time messaging
├── video.php              ← Video consultations (Jitsi)
├── prescriptions.php      ← Digital prescriptions
├── profile.php            ← Edit profile & password
├── notifications.php      ← Notification centre
├── admin.php              ← Admin panel (doctors)
├── reset_password.php     ← Password recovery
├── 404.php                ← Custom 404 page
├── .htaccess              ← Apache security rules
├── telecure_db.sql        ← Full database schema + seed data
│
├── includes/
│   ├── config.php         ← DB connection, helpers, session
│   ├── sidebar.php        ← Sidebar navigation
│   └── topbar.php         ← Top navigation bar
│
├── api/
│   ├── messages.php       ← AJAX chat API
│   └── appointments.php   ← AJAX slots API
│
├── css/
│   ├── style.css          ← Global styles
│   ├── auth.css           ← Login / Register styles
│   ├── dashboard.css      ← Dashboard layout
│   ├── chat.css           ← Chat interface
│   ├── prescriptions.css  ← Prescription styles
│   ├── video.css          ← Video call styles
│   └── animations.css     ← All animations
│
├── js/
│   ├── main.js            ← Homepage JS (counter, slider, particles)
│   ├── dashboard.js       ← Sidebar, notifications, ripple effects
│   └── animations.js      ← Scroll animations, stagger effects
│
└── uploads/
    └── avatars/           ← User profile photos
```

---

## ⚙️ Installation Steps

### 1. Install WAMP or XAMPP
- Download from https://www.wampserver.com or https://www.apachefriends.org
- Start Apache and MySQL services

### 2. Copy Project Files
```
Copy the entire `telecure` folder to:
  WAMP:  C:\wamp64\www\telecure\
  XAMPP: C:\xampp\htdocs\telecure\
```

### 3. Create Database in phpMyAdmin
1. Open browser → http://localhost/phpmyadmin
2. Click **New** in the left sidebar
3. Database name: `telecure_db`
4. Collation: `utf8mb4_unicode_ci`
5. Click **Create**
6. Click the `telecure_db` database → **Import** tab
7. Choose file: `telecure_db.sql`
8. Click **Go**

### 4. Configure Database Connection
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Your phpMyAdmin username
define('DB_PASS', '');          // Your phpMyAdmin password (blank by default in WAMP)
define('DB_NAME', 'telecure_db');
define('SITE_URL', 'http://localhost/telecure');
```

### 5. Set PHP Version (Important!)
- The code is compatible with **PHP 5.6, 7.x, and 8.x**
- In WAMP: Left-click tray icon → PHP → PHP version → choose any version

### 6. Open in Browser
```
http://localhost/telecure
```

---

## 📝 Recent Fixes (this build)

- **Registration now requires a profile photo.** Both patients and doctors must upload a real JPG/PNG/WEBP photo (max 3MB) when creating an account — validated server-side, stored in `uploads/avatars/`, and shown everywhere the person's avatar appears.
- **Profile photos can be changed anytime** from `profile.php` (click the camera icon on your avatar).
- **The 3 demo doctor accounts now have real photos** instead of initials placeholders, seeded in `telecure_db.sql`.
- Fixed a bug where every avatar site-wide ignored the uploaded/seeded photo and always showed generated initials.
- Fixed a variable-collision bug on `doctor_profile.php` that could show the logged-in user's avatar instead of the doctor being viewed.
- `uploads/avatars/` must be writable by the web server (`chmod 755` is usually enough on WAMP/XAMPP; the app will also try to create it automatically).

---

## 🔑 Demo Accounts

After importing the SQL file, these accounts are ready:

| Name            | Email                  | Password   | Role   |
|-----------------|------------------------|------------|--------|
| Dr. Sarah Johnson | sarah@telecure.com   | password   | Doctor |
| Dr. Michael Osei  | michael@telecure.com | password   | Doctor |
| Dr. Priya Naidoo  | priya@telecure.com   | password   | Doctor |

> ⚠️ **Change these passwords** before going live!

---

## ✅ Features Checklist

| Feature                         | File(s)                          |
|---------------------------------|----------------------------------|
| Patient Registration            | register.php                     |
| Doctor Registration             | register.php                     |
| Login / Logout                  | login.php, logout.php            |
| Password Reset                  | reset_password.php               |
| Patient Dashboard               | dashboard.php                    |
| Doctor Dashboard                | dashboard.php                    |
| Find & Search Doctors           | find_doctors.php                 |
| Doctor Public Profile           | doctor_profile.php               |
| Star Ratings & Reviews          | doctor_profile.php               |
| Book Appointments               | appointments.php                 |
| Appointment Slot Picker         | api/appointments.php             |
| Video Consultation (Jitsi)      | video.php                        |
| Real-time Chat (AJAX poll)      | chat.php, api/messages.php       |
| Digital Prescriptions           | prescriptions.php                |
| Print Prescriptions             | prescriptions.php (print CSS)    |
| Notifications System            | notifications.php                |
| Edit Profile & Password         | profile.php                      |
| Admin Panel                     | admin.php                        |
| Doctor Verification             | admin.php                        |
| Animations & Particles          | js/animations.js, css/animations.css |
| White & Blue Theme              | css/style.css                    |
| Fully Responsive                | All CSS files                    |
| POPIA / Security Headers        | .htaccess                        |
| phpMyAdmin / MySQL Storage      | telecure_db.sql + config.php     |

---

## 🔒 Security Notes

- All user input is sanitized via `sanitize()` in config.php
- Passwords hashed with `password_hash()` (bcrypt)
- PDO prepared statements prevent SQL injection
- `.htaccess` blocks direct access to SQL files and includes
- Session-based authentication on every protected page

---

## 🎥 Video Consultation

TeleCure uses **Jitsi Meet** (free, open-source) for video calls:
- No account required
- Works in any modern browser
- No software download needed
- End-to-end encrypted

---

## 🛠️ Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank page | Enable PHP error display: add `ini_set('display_errors',1);` to config.php |
| DB connection failed | Check DB_USER and DB_PASS in config.php |
| Parse error `=>` | Ensure PHP is 5.6+ in WAMP settings |
| Images not showing | Check internet connection (avatars use external API) |
| Video not working | Allow camera/mic in browser permissions |

---

## 📞 Support

Built for educational/portfolio purposes. Customize freely!
