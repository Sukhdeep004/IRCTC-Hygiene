-- ============================================
-- DATABASE MIGRATION SCRIPT (MySQL 5.7+ compatible)
-- Run this in phpMyAdmin > SQL tab
-- ============================================

USE irctc_hygiene_db;

-- Step 1: Add pnr_number to ratings (ignore error if already exists)
ALTER TABLE ratings ADD COLUMN pnr_number VARCHAR(10) AFTER vendor_id;

-- Step 2: Add columns to complaints
ALTER TABLE complaints ADD COLUMN pnr_number VARCHAR(10) AFTER vendor_id;
ALTER TABLE complaints ADD COLUMN sentiment VARCHAR(20) AFTER description;
ALTER TABLE complaints ADD COLUMN sentiment_score DECIMAL(5,2) AFTER sentiment;
ALTER TABLE complaints ADD COLUMN ai_category VARCHAR(50) AFTER sentiment_score;

-- Step 3: Create pnr_verifications table
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
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Step 4: Create ai_insights table
CREATE TABLE IF NOT EXISTS ai_insights (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    insight_type ENUM('trend_prediction','inspection_priority','risk_assessment') NOT NULL,
    insight_data TEXT,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);

-- Step 5: Update alerts enum (safe to run)
ALTER TABLE alerts MODIFY COLUMN alert_type ENUM('low_score','complaint_spike','critical','suspended','declining_trend') NOT NULL;

-- Step 6: Add image_path to complaints
ALTER TABLE complaints ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER ai_category;

-- Step 7: Add image_path to ratings
ALTER TABLE ratings ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER comments;

-- Step 8: Complaint workflow columns (officer action + admin acknowledgement)
ALTER TABLE complaints ADD COLUMN officer_id INT DEFAULT NULL AFTER admin_note;
ALTER TABLE complaints ADD COLUMN officer_note TEXT DEFAULT NULL AFTER officer_id;
ALTER TABLE complaints ADD COLUMN officer_action ENUM('investigating','action_taken','no_violation','forwarded') DEFAULT NULL AFTER officer_note;
ALTER TABLE complaints ADD COLUMN officer_updated_at TIMESTAMP NULL DEFAULT NULL AFTER officer_action;
ALTER TABLE complaints ADD COLUMN admin_acknowledged TINYINT(1) DEFAULT 0 AFTER officer_updated_at;
ALTER TABLE complaints ADD COLUMN admin_ack_note TEXT DEFAULT NULL AFTER admin_acknowledged;
ALTER TABLE complaints ADD COLUMN passenger_notified TINYINT(1) DEFAULT 0 AFTER admin_ack_note;

-- Step 9: Full complaint management workflow
ALTER TABLE complaints MODIFY COLUMN status ENUM('submitted','under_verification','approved','rejected','more_info_requested','forwarded_to_admin','resolved','closed') DEFAULT 'submitted';
ALTER TABLE complaints ADD COLUMN category VARCHAR(50) DEFAULT NULL AFTER subject;
ALTER TABLE complaints ADD COLUMN department VARCHAR(100) DEFAULT NULL AFTER admin_ack_note;
ALTER TABLE complaints ADD COLUMN more_info_request TEXT DEFAULT NULL AFTER department;

-- Step 10: Complaint history/audit log table
CREATE TABLE IF NOT EXISTS complaint_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    action_by INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    action VARCHAR(50) NOT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (action_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Step 11: Withdrawn status + vendor comment + withdrawn_at
ALTER TABLE complaints MODIFY COLUMN status ENUM('submitted','under_verification','approved','rejected','more_info_requested','forwarded_to_admin','resolved','closed','withdrawn') DEFAULT 'submitted';
ALTER TABLE complaints ADD COLUMN vendor_comment TEXT DEFAULT NULL AFTER more_info_request;
ALTER TABLE complaints ADD COLUMN vendor_commented_at TIMESTAMP NULL DEFAULT NULL AFTER vendor_comment;
ALTER TABLE complaints ADD COLUMN withdrawn_at TIMESTAMP NULL DEFAULT NULL AFTER vendor_commented_at;

SELECT 'Migration done! Ignore duplicate column errors above - they mean columns already exist.' as Status;

-- ── complaint-analytics-communication: Step 12 — messages table ─────────────
CREATE TABLE IF NOT EXISTS messages (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    sender_id    INT NOT NULL,
    receiver_id  INT NOT NULL,
    message_body TEXT NOT NULL,
    context_type ENUM('complaint','general') NOT NULL DEFAULT 'general',
    context_id   INT NULL DEFAULT NULL,
    is_read      TINYINT(1) NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id)   REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (context_id)  REFERENCES complaints(id) ON DELETE SET NULL,
    INDEX idx_receiver_read (receiver_id, is_read),
    INDEX idx_thread (sender_id, receiver_id)
);

-- ── complaint-analytics-communication: Step 13 — vendors.status add 'removed'
-- Safe to re-run: only modifies if 'removed' is not already in the enum.
-- Check first with: SHOW COLUMNS FROM vendors LIKE 'status';
-- If Type does not contain 'removed', run the ALTER below:
ALTER TABLE vendors MODIFY COLUMN status
    ENUM('active','under_review','suspended','removed') NOT NULL DEFAULT 'active';
