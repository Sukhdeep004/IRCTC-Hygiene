-- ============================================
-- IRCTC FOOD HYGIENE RATING SYSTEM DATABASE
-- Import this file in phpMyAdmin first!
-- ============================================

CREATE DATABASE IF NOT EXISTS irctc_hygiene_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE irctc_hygiene_db;

-- USERS TABLE (Passengers)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15),
    password VARCHAR(255) NOT NULL,
    role ENUM('passenger','vendor','admin','officer') DEFAULT 'passenger',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- VENDORS TABLE
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    vendor_name VARCHAR(150) NOT NULL,
    license_number VARCHAR(50) UNIQUE NOT NULL,
    zone VARCHAR(100),
    station VARCHAR(150),
    train_number VARCHAR(20),
    contact_email VARCHAR(100),
    contact_phone VARCHAR(15),
    current_score DECIMAL(4,2) DEFAULT 0.00,
    status ENUM('active','under_review','suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- RATINGS TABLE (Passenger Hygiene Ratings)
CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    passenger_id INT NOT NULL,
    vendor_id INT NOT NULL,
    pnr_number VARCHAR(10),
    train_number VARCHAR(20),
    travel_date DATE,
    cleanliness TINYINT NOT NULL CHECK (cleanliness BETWEEN 1 AND 5),
    food_quality TINYINT NOT NULL CHECK (food_quality BETWEEN 1 AND 5),
    packaging TINYINT NOT NULL CHECK (packaging BETWEEN 1 AND 5),
    staff_hygiene TINYINT NOT NULL CHECK (staff_hygiene BETWEEN 1 AND 5),
    timeliness TINYINT NOT NULL CHECK (timeliness BETWEEN 1 AND 5),
    final_score DECIMAL(4,2) NOT NULL,
    comments TEXT,
    image_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_pnr (pnr_number)
);

-- COMPLAINTS TABLE
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    passenger_id INT NOT NULL,
    vendor_id INT NOT NULL,
    pnr_number VARCHAR(10),
    complaint_code VARCHAR(20) UNIQUE,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    sentiment VARCHAR(20),
    sentiment_score DECIMAL(5,2),
    ai_category VARCHAR(50),
    image_path VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','in_progress','resolved','rejected') DEFAULT 'pending',
    vendor_response TEXT,
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (passenger_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    INDEX idx_pnr (pnr_number),
    INDEX idx_sentiment (sentiment)
);

-- INSPECTION REPORTS TABLE
CREATE TABLE IF NOT EXISTS inspection_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    officer_id INT NOT NULL,
    vendor_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    cleanliness TINYINT NOT NULL CHECK (cleanliness BETWEEN 1 AND 5),
    food_quality TINYINT NOT NULL CHECK (food_quality BETWEEN 1 AND 5),
    packaging TINYINT NOT NULL CHECK (packaging BETWEEN 1 AND 5),
    staff_hygiene TINYINT NOT NULL CHECK (staff_hygiene BETWEEN 1 AND 5),
    timeliness TINYINT NOT NULL CHECK (timeliness BETWEEN 1 AND 5),
    inspection_score DECIMAL(4,2) NOT NULL,
    violations TEXT,
    recommendations TEXT,
    evidence_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (officer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- ALERTS TABLE
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    alert_type ENUM('low_score','complaint_spike','critical','suspended','declining_trend') NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- PNR VERIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS pnr_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pnr_number VARCHAR(10) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    train_number VARCHAR(20) NOT NULL,
    train_name VARCHAR(100),
    travel_date DATE NOT NULL,
    from_station VARCHAR(100),
    to_station VARCHAR(100),
    passenger_name VARCHAR(100),
    booking_status VARCHAR(20),
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pnr (pnr_number),
    INDEX idx_user (user_id)
);

-- AI INSIGHTS TABLE
CREATE TABLE IF NOT EXISTS ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    insight_type ENUM('trend_prediction','inspection_priority','risk_assessment') NOT NULL,
    insight_data JSON,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- ============================================
-- DEFAULT DATA
-- ============================================

-- Admin User (password: admin123)
INSERT INTO users (name, email, phone, password, role) VALUES
('System Admin', 'admin@irctc.com', '9800000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Rajesh Kumar', 'officer1@irctc.com', '9800000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer'),
('Vendor One', 'vendor1@irctc.com', '9800000003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor'),
('Vendor Two', 'vendor2@irctc.com', '9800000004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor'),
('Vendor Three', 'vendor3@irctc.com', '9800000005', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'vendor'),
('Anita Singh', 'passenger1@gmail.com', '9900000001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'passenger'),
('Rahul Sharma', 'passenger2@gmail.com', '9900000002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'passenger');

-- Vendors
INSERT INTO vendors (user_id, vendor_name, license_number, zone, station, train_number, contact_email, contact_phone, current_score, status) VALUES
(3, 'Sharma Catering Services', 'LIC-001-NR', 'Northern Railway', 'New Delhi', '12301', 'vendor1@irctc.com', '9800000003', 4.20, 'active'),
(4, 'Mumbai Food Hub', 'LIC-002-WR', 'Western Railway', 'Mumbai Central', '12951', 'vendor2@irctc.com', '9800000004', 2.10, 'under_review'),
(5, 'Southern Delights', 'LIC-003-SR', 'Southern Railway', 'Chennai Central', '12163', 'vendor3@irctc.com', '9800000005', 3.50, 'active');

-- Sample Ratings
INSERT INTO ratings (passenger_id, vendor_id, train_number, travel_date, cleanliness, food_quality, packaging, staff_hygiene, timeliness, final_score, comments) VALUES
(6, 1, '12301', '2026-02-15', 5, 4, 4, 5, 4, 4.55, 'Excellent food and cleanliness!'),
(7, 1, '12301', '2026-02-16', 4, 5, 3, 4, 5, 4.30, 'Good overall, packaging could improve'),
(6, 2, '12951', '2026-02-14', 2, 2, 2, 2, 2, 2.00, 'Very poor hygiene, unhappy'),
(7, 2, '12951', '2026-02-13', 2, 3, 1, 2, 3, 2.25, 'Staff not clean, food was cold'),
(6, 3, '12163', '2026-02-17', 4, 3, 4, 3, 4, 3.55, 'Average service, can improve food quality');

-- Sample Complaints
INSERT INTO complaints (passenger_id, vendor_id, complaint_code, subject, description, status) VALUES
(6, 2, 'CMP-2026-001', 'Cockroach found in food', 'I found a cockroach in my meal. This is completely unacceptable and a serious hygiene violation.', 'pending'),
(7, 2, 'CMP-2026-002', 'Expired food served', 'The food served appeared to be stale and had foul smell. I fell sick after consuming it.', 'in_progress'),
(6, 1, 'CMP-2026-003', 'Late delivery of food', 'Food was delivered 45 minutes after expected time. Staff was not responsive.', 'resolved');

-- Sample Inspection
INSERT INTO inspection_reports (officer_id, vendor_id, inspection_date, cleanliness, food_quality, packaging, staff_hygiene, timeliness, inspection_score, violations, recommendations) VALUES
(2, 2, '2026-02-10', 2, 2, 2, 1, 3, 2.05, 'Staff not wearing gloves, food storage below standards, kitchen area unclean', 'Immediate deep cleaning required. Staff must wear PPE. Food storage must follow FSSAI guidelines.'),
(2, 1, '2026-02-12', 5, 4, 4, 5, 4, 4.50, 'None', 'Maintain current standards. Excellent hygiene practices observed.');

-- Alerts
INSERT INTO alerts (vendor_id, alert_type, message) VALUES
(2, 'low_score', 'Vendor Mumbai Food Hub has scored below 2.5 for 2 consecutive evaluation cycles. Immediate action required.'),
(2, 'complaint_spike', 'Multiple complaints received against Mumbai Food Hub in last 7 days.');
