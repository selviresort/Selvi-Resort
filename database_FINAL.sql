-- ============================================================
--  SELVI RESORT & LAWN — MySQL Database Schema (FIXED)
--  ✅ Includes checkout_date for multi-day bookings
--  
--  Run this in phpMyAdmin or MySQL CLI:
--  mysql -u root -p < database_FINAL.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS selvi_resort CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE selvi_resort;

-- ── ADMIN USERS ──────────────────────────────────────────────
CREATE TABLE admin_users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  username    VARCHAR(50)  NOT NULL UNIQUE,
  email       VARCHAR(100) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,   -- bcrypt hash
  full_name   VARCHAR(100) NOT NULL,
  role        ENUM('superadmin','admin') DEFAULT 'admin',
  last_login  DATETIME,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: username=admin  password=Admin@1234
INSERT INTO admin_users (username, email, password, full_name, role)
VALUES (
  'admin',
  'admin@selviresort.com',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@1234
  'Resort Administrator',
  'superadmin'
);

-- ── PACKAGES ─────────────────────────────────────────────────
CREATE TABLE packages (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(100) NOT NULL,
  slug         VARCHAR(100) NOT NULL UNIQUE,
  icon         VARCHAR(10)  DEFAULT '🎉',
  subtitle     VARCHAR(150),
  price        DECIMAL(10,2),
  price_label  VARCHAR(50)  DEFAULT 'per event',
  max_guests   INT,
  duration     VARCHAR(80),
  features     TEXT,           -- JSON array
  is_featured  TINYINT(1)   DEFAULT 0,
  is_available TINYINT(1)   DEFAULT 1,
  is_active    TINYINT(1)   DEFAULT 1,
  sort_order   INT          DEFAULT 0,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO packages (name, slug, icon, subtitle, price, price_label, max_guests, duration, features, is_featured, is_available, sort_order) VALUES
('Silver Package',   'silver',    '🌸', 'Intimate Gatherings',  45000,   'per event', 100, '6 hours',   '["Lawn or Hall booking for 6 hours","Basic floral décor & table arrangements","Welcome drink for all guests","Vegetarian buffet (2 courses)","Dedicated event coordinator","Parking for 30 vehicles"]', 0, 1, 1),
('Gold Package',     'gold',      '👑', 'Grand Celebrations',   110000,  'per event', 300, '10 hours',  '["Premium Lawn + Hall combo","Premium décor with LED backdrop","Full catering (veg & non-veg)","DJ with sound & lighting rig","Photo booth setup","Team of 5 coordinators","Parking for 100 vehicles","Bridal room access 2 hrs"]', 1, 1, 2),
('Platinum Package', 'platinum',  '💎', 'Royal Experience',     250000,  'per event', 600, 'Full Day',  '["Entire resort exclusive booking","Luxury themed décor","Multi-cuisine grand buffet","Live music performance","Photography & videography","VIP bridal & groom suites","24hr dedicated concierge","Drone aerial coverage"]', 0, 1, 3),
('Birthday Package', 'birthday',  '🎂', 'Special Occasions',   25000,   'per event',  50, '3 hours',  '["Themed lawn or room setup","Birthday cake arrangement","Snacks & drinks buffet","Balloon & banner décor","Music system & playlist","Personalized birthday banner"]', 0, 1, 4),
('Corporate Package','corporate', '💼', 'Professional Events',  75000,   'per event', 200, '8 hours',  '["Conference hall + outdoor lawn","HD projector & AV equipment","Corporate catering","Brand backdrop & signage","High-speed Wi-Fi","Tech support staff","Parking for 80 vehicles"]', 0, 1, 5),
('Wedding Package',  'wedding',   '💍', 'Dream Weddings',        0,      'custom quote', 9999, '2–3 days','["Full resort exclusive booking","Mehendi, sangeet & reception","Bridal & groom suites","Mandap décor & priest","Custom catering all ceremonies","Photography, videography & drone","Guest coordination"]', 0, 1, 6);

-- ── BOOKINGS ─────────────────────────────────────────────────
-- ✅ FIXED: Includes checkout_date for multi-day bookings
CREATE TABLE bookings (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  booking_ref     VARCHAR(20)  NOT NULL UNIQUE,  -- e.g. SR-2024-00001
  full_name       VARCHAR(100) NOT NULL,
  phone           VARCHAR(20)  NOT NULL,
  email           VARCHAR(100),
  whatsapp        VARCHAR(20),
  event_type      VARCHAR(80)  NOT NULL,
  package_id      INT,
  package_name    VARCHAR(100),
  event_date      DATE,                          -- Check-in date
  checkout_date   DATE,                          -- ✅ Check-out date (for multi-day bookings)
  alt_date        DATE,                          -- Alternative date
  time_slot       VARCHAR(50),
  guest_count     VARCHAR(30),
  addon_service   VARCHAR(100),
  heard_from      VARCHAR(80),
  special_request TEXT,
  status          ENUM('new','contacted','confirmed','completed','cancelled') DEFAULT 'new',
  admin_notes     TEXT,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL,
  INDEX idx_date_range (event_date, checkout_date, status)  -- ✅ Index for date range queries
);

-- ── CONTACT MESSAGES ─────────────────────────────────────────
CREATE TABLE contact_messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  full_name   VARCHAR(100) NOT NULL,
  phone       VARCHAR(20),
  email       VARCHAR(100),
  subject     VARCHAR(150),
  message     TEXT         NOT NULL,
  status      ENUM('unread','read','replied') DEFAULT 'unread',
  admin_reply TEXT,
  ip_address  VARCHAR(45),
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── ADMIN ACTIVITY LOG ───────────────────────────────────────
CREATE TABLE activity_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT,
  action      VARCHAR(200),
  target_type VARCHAR(50),
  target_id   INT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- ── SITE SETTINGS ────────────────────────────────────────────
CREATE TABLE settings (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(80)  NOT NULL UNIQUE,
  setting_val TEXT,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_val) VALUES
('site_name',      'Selvi Resort & Lawn'),
('site_phone1',    '+91 98765 43210'),
('site_phone2',    '+91 98765 43211'),
('site_email',     'info@selviresort.com'),
('site_address',   'Main Highway Road, Near Selvi Nagar, Tamil Nadu — 600001'),
('site_whatsapp',  '+919876543210'),
('google_maps_url','https://maps.google.com'),
('booking_email',  'bookings@selviresort.com');

-- ============================================================
--  ✅ SCHEMA COMPLETE
--  All tables ready for multi-day bookings support
-- ============================================================
