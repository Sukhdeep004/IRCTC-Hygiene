<?php
// ============================================
// AUTO MIGRATION - runs once, adds missing columns/tables
// Included in config.php automatically
// ============================================

function runMigration($conn) {
    // Check if already migrated
    $check = $conn->query("SHOW COLUMNS FROM complaints LIKE 'sentiment'");
    if ($check && $check->num_rows > 0) return; // Already migrated

    // Add pnr_number to ratings
    $conn->query("ALTER TABLE ratings ADD COLUMN pnr_number VARCHAR(10) AFTER vendor_id");

    // Add columns to complaints
    $conn->query("ALTER TABLE complaints ADD COLUMN pnr_number VARCHAR(10) AFTER vendor_id");
    $conn->query("ALTER TABLE complaints ADD COLUMN sentiment VARCHAR(20) AFTER description");
    $conn->query("ALTER TABLE complaints ADD COLUMN sentiment_score DECIMAL(5,2) AFTER sentiment");
    $conn->query("ALTER TABLE complaints ADD COLUMN ai_category VARCHAR(50) AFTER sentiment_score");

    // Create pnr_verifications table
    $conn->query("CREATE TABLE IF NOT EXISTS pnr_verifications (
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
    )");

    // Create ai_insights table
    $conn->query("CREATE TABLE IF NOT EXISTS ai_insights (
        id INT AUTO_INCREMENT PRIMARY KEY,
        vendor_id INT NOT NULL,
        insight_type VARCHAR(50) NOT NULL,
        insight_data TEXT,
        generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
    )");

    // Update alerts enum
    $conn->query("ALTER TABLE alerts MODIFY COLUMN alert_type 
        ENUM('low_score','complaint_spike','critical','suspended','declining_trend') NOT NULL");
}

runMigration($conn);

// Add image_path to complaints if missing
$imgCheck = $conn->query("SHOW COLUMNS FROM complaints LIKE 'image_path'");
if ($imgCheck && $imgCheck->num_rows === 0) {
    $conn->query("ALTER TABLE complaints ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER ai_category");
}

// Add image_path to ratings if missing
$ratingImgCheck = $conn->query("SHOW COLUMNS FROM ratings LIKE 'image_path'");
if ($ratingImgCheck && $ratingImgCheck->num_rows === 0) {
    $conn->query("ALTER TABLE ratings ADD COLUMN image_path VARCHAR(255) DEFAULT NULL AFTER comments");
}

// Add complaint workflow columns for officer/admin flow
$wfCheck = $conn->query("SHOW COLUMNS FROM complaints LIKE 'officer_id'");
if ($wfCheck && $wfCheck->num_rows === 0) {
    $conn->query("ALTER TABLE complaints ADD COLUMN officer_id INT DEFAULT NULL AFTER admin_note");
    $conn->query("ALTER TABLE complaints ADD COLUMN officer_note TEXT DEFAULT NULL AFTER officer_id");
    $conn->query("ALTER TABLE complaints ADD COLUMN officer_action ENUM('investigating','action_taken','no_violation','forwarded') DEFAULT NULL AFTER officer_note");
    $conn->query("ALTER TABLE complaints ADD COLUMN officer_updated_at TIMESTAMP NULL DEFAULT NULL AFTER officer_action");
    $conn->query("ALTER TABLE complaints ADD COLUMN admin_acknowledged TINYINT(1) DEFAULT 0 AFTER officer_updated_at");
    $conn->query("ALTER TABLE complaints ADD COLUMN admin_ack_note TEXT DEFAULT NULL AFTER admin_acknowledged");
    $conn->query("ALTER TABLE complaints ADD COLUMN passenger_notified TINYINT(1) DEFAULT 0 AFTER admin_ack_note");
}

// Full workflow: extended status enum, category, department, more_info_request
$catCheck = $conn->query("SHOW COLUMNS FROM complaints LIKE 'category'");
if ($catCheck && $catCheck->num_rows === 0) {
    $conn->query("ALTER TABLE complaints ADD COLUMN category VARCHAR(50) DEFAULT NULL AFTER subject");
}
$deptCheck = $conn->query("SHOW COLUMNS FROM complaints LIKE 'department'");
if ($deptCheck && $deptCheck->num_rows === 0) {
    $conn->query("ALTER TABLE complaints ADD COLUMN department VARCHAR(100) DEFAULT NULL AFTER admin_ack_note");
    $conn->query("ALTER TABLE complaints ADD COLUMN more_info_request TEXT DEFAULT NULL AFTER department");
}
$conn->query("ALTER TABLE complaints MODIFY COLUMN status ENUM('submitted','under_verification','approved','rejected','more_info_requested','forwarded_to_admin','resolved','closed','withdrawn') DEFAULT 'submitted'");

// Complaint history/audit log
$conn->query("CREATE TABLE IF NOT EXISTS complaint_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    action_by INT NOT NULL,
    role VARCHAR(20) NOT NULL,
    action VARCHAR(50) NOT NULL,
    note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
    FOREIGN KEY (action_by) REFERENCES users(id) ON DELETE CASCADE
)");

// Vendor comment + withdraw columns
$vcCheck = $conn->query("SHOW COLUMNS FROM complaints LIKE 'vendor_comment'");
if ($vcCheck && $vcCheck->num_rows === 0) {
    $conn->query("ALTER TABLE complaints ADD COLUMN vendor_comment TEXT DEFAULT NULL AFTER more_info_request");
    $conn->query("ALTER TABLE complaints ADD COLUMN vendor_commented_at TIMESTAMP NULL DEFAULT NULL AFTER vendor_comment");
    $conn->query("ALTER TABLE complaints ADD COLUMN withdrawn_at TIMESTAMP NULL DEFAULT NULL AFTER vendor_commented_at");
}

// ── complaint-analytics-communication: messages table ──────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS messages (
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
)");

// ── complaint-analytics-communication: vendors.status enum add 'removed' ───
$vendorStatusCheck = $conn->query("SHOW COLUMNS FROM vendors LIKE 'status'");
if ($vendorStatusCheck && $vendorStatusCheck->num_rows > 0) {
    $vendorStatusRow = $vendorStatusCheck->fetch_assoc();
    if (strpos($vendorStatusRow['Type'], 'removed') === false) {
        $conn->query("ALTER TABLE vendors MODIFY COLUMN status
            ENUM('active','under_review','suspended','removed') NOT NULL DEFAULT 'active'");
    }
}

// ── passenger_additional_info column for more_info_requested flow ───────────
$paiCheck = $conn->query("SHOW COLUMNS FROM complaints LIKE 'passenger_additional_info'");
if ($paiCheck && $paiCheck->num_rows === 0) {
    $conn->query("ALTER TABLE complaints ADD COLUMN passenger_additional_info TEXT DEFAULT NULL AFTER more_info_request");
}

// ── Make complaint_history.complaint_id nullable for non-complaint admin actions ──
$chCheck = $conn->query("SHOW COLUMNS FROM complaint_history LIKE 'complaint_id'");
if ($chCheck && $chCheck->num_rows > 0) {
    $chRow = $chCheck->fetch_assoc();
    if (strpos($chRow['Null'], 'YES') === false) {
        // Find and drop the FK on complaint_id
        $fkRes = $conn->query("
            SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'complaint_history'
              AND COLUMN_NAME = 'complaint_id'
              AND REFERENCED_TABLE_NAME = 'complaints'
            LIMIT 1
        ");
        if ($fkRes && $fkRow = $fkRes->fetch_assoc()) {
            $fkName = $fkRow['CONSTRAINT_NAME'];
            $conn->query("ALTER TABLE complaint_history DROP FOREIGN KEY `$fkName`");
        }
        $conn->query("ALTER TABLE complaint_history MODIFY COLUMN complaint_id INT NULL DEFAULT NULL");
        $conn->query("ALTER TABLE complaint_history ADD CONSTRAINT fk_ch_complaint
            FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE");
    }
}

// ── complaint priority column ────────────────────────────────────────────────
$prioCheck = $conn->query("SHOW COLUMNS FROM complaints LIKE 'priority'");
if ($prioCheck && $prioCheck->num_rows === 0) {
    $conn->query("ALTER TABLE complaints ADD COLUMN priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium' AFTER status");
}

// ── journey details columns (train_no, source, destination) ─────────────────
$jCheck = $conn->query("SHOW COLUMNS FROM complaints LIKE 'train_number'");
if ($jCheck && $jCheck->num_rows === 0) {
    $conn->query("ALTER TABLE complaints ADD COLUMN train_number VARCHAR(20) DEFAULT NULL AFTER pnr_number");
    $conn->query("ALTER TABLE complaints ADD COLUMN from_station VARCHAR(100) DEFAULT NULL AFTER train_number");
    $conn->query("ALTER TABLE complaints ADD COLUMN to_station VARCHAR(100) DEFAULT NULL AFTER from_station");
}

// ── passenger contact information column ─────────────────────────────────────
$contactCheck = $conn->query("SHOW COLUMNS FROM complaints LIKE 'passenger_contact'");
if ($contactCheck && $contactCheck->num_rows === 0) {
    $conn->query("ALTER TABLE complaints ADD COLUMN passenger_contact VARCHAR(100) DEFAULT NULL AFTER description");
}
