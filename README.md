# Selvi Resort & Lawn — PHP + MySQL Setup Guide

## 📁 Folder Structure
```
selvi-resort/              ← Put this inside htdocs (XAMPP) or www (WAMP)
├── index.php              ← Main website (replaces index.html)
├── database.sql           ← Run this FIRST in phpMyAdmin
├── includes/
│   └── config.php         ← DB credentials & shared functions
├── api/
│   ├── booking.php        ← Handles booking form POST
│   └── contact.php        ← Handles contact form POST
└── admin/
    ├── login.php          ← Admin login page
    ├── dashboard.php      ← Admin dashboard
    ├── bookings.php       ← View & manage all bookings
    ├── booking_view.php   ← View/update a single booking
    ├── messages.php       ← Contact messages inbox
    ├── packages.php       ← Add/edit/delete packages
    ├── settings.php       ← Site info & password change
    ├── logout.php
    ├── sidebar.php
    ├── topbar.php
    └── admin_style.css.php
```

---

## 🚀 Step-by-Step Setup (XAMPP)

### Step 1 — Copy Files
Copy the entire `selvi-resort` folder to:
```
C:\xampp\htdocs\selvi-resort\
```

### Step 2 — Start XAMPP
Open XAMPP Control Panel → Start **Apache** and **MySQL**

### Step 3 — Create Database
1. Open browser → go to `http://localhost/phpmyadmin`
2. Click **"New"** on the left sidebar
3. Name it `selvi_resort` → click Create
4. Click your new database → click **Import** tab
5. Choose file: `selvi-resort/database.sql` → click **Go**

### Step 4 — Configure DB (if needed)
Open `includes/config.php` and update if your MySQL password is different:
```php
define('DB_USER', 'root');
define('DB_PASS', '');      // ← Add your MySQL password here if set
```

### Step 5 — Open Website
```
http://localhost/selvi-resort/
```

### Step 6 — Open Admin Panel
```
http://localhost/selvi-resort/admin/login.php
```
**Default credentials:**
- Username: `admin`
- Password: `Admin@1234`

⚠️ **Change this password immediately** after first login via Settings page!

---

## 🔑 Admin Panel Features

| Page | URL | What it does |
|------|-----|-------------|
| Login | `/admin/login.php` | Secure admin login |
| Dashboard | `/admin/dashboard.php` | Stats overview + recent bookings |
| Bookings | `/admin/bookings.php` | View all enquiries, filter, export CSV |
| Booking Detail | `/admin/booking_view.php?id=X` | Update status, add notes, call/WhatsApp |
| Messages | `/admin/messages.php` | Inbox for contact form messages |
| Packages | `/admin/packages.php` | Add/edit/delete/disable packages |
| Settings | `/admin/settings.php` | Update phone, email, address, password |

---

## 📋 Booking Statuses
- **New** — Just received, needs follow-up
- **Contacted** — Team has called/emailed the customer
- **Confirmed** — Booking is confirmed & deposit received
- **Completed** — Event has taken place
- **Cancelled** — Booking was cancelled

---

## 📧 Email Notifications (Optional — Advanced)
To send email confirmations, add this to `api/booking.php` after the INSERT:
```php
mail($email, "Booking Confirmed - $ref", "Dear $fullName, ...", "From: bookings@selviresort.com");
```
For production, use **PHPMailer** with SMTP for reliable email delivery.

---

## 🔒 Security Notes for Production
1. Change DB password from empty to strong password
2. Change admin password immediately
3. Use HTTPS (SSL certificate)
4. Edit `includes/config.php` — set proper DB credentials
5. Consider moving `includes/config.php` above webroot

---

## ❓ Troubleshooting

**"Database connection failed"**
→ Check DB_USER and DB_PASS in `includes/config.php`
→ Make sure MySQL is running in XAMPP

**Blank page or PHP errors**
→ In XAMPP, enable error reporting or check `php_error_log`

**Forms not submitting**
→ Make sure Apache is running
→ Check that the `api/` folder exists with correct files
