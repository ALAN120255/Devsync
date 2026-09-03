# Devsync

**Code. Sync. Innovate.**

Devsync is a fintech & healthtech studio formed on 20 April 2026 in Benoni, Gauteng, South Africa. This repository contains the Devsync company website plus **TeleCure**, a full telemedicine web application built by the team.

🔗 Live-ready structure — clone, install locally with WAMP/XAMPP, and run.

---

## 📁 Repository Structure

```
Devsync/
├── index.php           ← Company landing page (About, Team, Products)
├── images/              ← Landing page assets (team photos, logo, product screenshots)
│
└── Telecure/             ← TeleCure telemedicine platform (full PHP/MySQL app)
    ├── index.php         ← App landing page
    ├── register.php / login.php / logout.php
    ├── dashboard.php     ← Role-aware dashboard (patient / doctor / admin)
    ├── find_doctors.php / doctor_profile.php
    ├── appointments.php  ← Booking & slot management
    ├── chat.php          ← Real-time messaging (AJAX)
    ├── video.php         ← Video consultations (Jitsi Meet)
    ├── prescriptions.php ← Digital prescriptions
    ├── profile.php / notifications.php / admin.php
    ├── includes/         ← DB connection, session & helper functions
    ├── api/               ← AJAX endpoints (messages, appointment slots)
    ├── css/ · js/          ← Styles and client-side scripts
    ├── uploads/            ← User-uploaded avatars
    └── telecure_db.sql    ← Database schema + seed data
```

See [`Telecure/README.md`](Telecure/README.md) for the app's full setup guide, features checklist, and demo accounts.

---

## 🏢 About Devsync

We specialize in fintech and healthtech solutions using modern PHP (Laravel), mobile apps (Flutter & React Native), and full-stack cloud infrastructure. Our mission is syncing technology with real human needs — from budget trackers that help families save money to telemedicine platforms that save lives.

**Products built by the team:**
- **TeleCure** — telemedicine platform (this repo)
- **SpendWise** — local-first personal finance Android app

### The Team

| Name | Role |
|---|---|
| Lwanda Godlwana | CEO |
| Koketso Rabothata | Project Owner |
| Alan Chauke | Software Designer |
| Given Mudau | Mobile Development Lead |
| Lindokuhle Mphenyeke | Software Testing |

---

## 🛠️ Tech Stack

- **Landing page:** PHP, Tailwind CSS (CDN), vanilla JS, Font Awesome
- **TeleCure app:** PHP (5.6 / 7.x / 8.x compatible), MySQL/MariaDB, PDO, vanilla JS, Jitsi Meet SDK (video)
- **Local environment:** WAMP or XAMPP

---

## ⚙️ Getting Started

### 1. Prerequisites
Install [WAMP](https://www.wampserver.com) or [XAMPP](https://www.apachefriends.org) and start Apache + MySQL.

### 2. Clone / copy the project
```
WAMP:  C:\wamp64\www\Devsync\
XAMPP: C:\xampp\htdocs\Devsync\
```

### 3. Run the landing page
Open:
```
http://localhost/Devsync
```

### 4. Set up TeleCure
The TeleCure app needs its own database. Full instructions (creating `telecure_db` in phpMyAdmin, importing `telecure_db.sql`, and configuring `includes/config.php`) are in [`Telecure/README.md`](Telecure/README.md).

Once set up, TeleCure runs at:
```
http://localhost/Devsync/Telecure
```

---

## 📞 Contact

Devsync (Pty) Ltd · Benoni, Gauteng, South Africa

Built for educational/portfolio purposes.
