<?php
// ============================================
// Smoke-test: migration verification
// Requirements: 4.1, 8.2
// Run: php tests/smoke_migration.php
// ============================================

// Bootstrap DB connection (and run migration)
require_once __DIR__ . '/../includes/config.php';

$pass = true;

// ── Check 1: messages table columns ──────────────────────────────────────────
$result = $conn->query("SHOW CREATE TABLE messages");
if (!$result) {
    echo "FAIL: Could not query SHOW CREATE TABLE messages — " . $conn->error . PHP_EOL;
    $pass = false;
} else {
    $row = $result->fetch_assoc();
    $ddl = $row['Create Table'] ?? '';

    $requiredColumns = [
        'id',
        'sender_id',
        'receiver_id',
        'message_body',
        'context_type',
        'context_id',
        'is_read',
        'created_at',
    ];

    $missingColumns = [];
    foreach ($requiredColumns as $col) {
        // Match column name as a word boundary in the DDL
        if (!preg_match('/\b' . preg_quote($col, '/') . '\b/', $ddl)) {
            $missingColumns[] = $col;
        }
    }

    if (empty($missingColumns)) {
        echo "PASS: messages table contains all required columns." . PHP_EOL;
    } else {
        echo "FAIL: messages table is missing columns: " . implode(', ', $missingColumns) . PHP_EOL;
        $pass = false;
    }

    // Check FKs: sender_id, receiver_id, context_id references
    $requiredFKRefs = ['users', 'complaints'];
    $missingFKRefs = [];
    foreach ($requiredFKRefs as $ref) {
        if (stripos($ddl, 'REFERENCES `' . $ref . '`') === false &&
            stripos($ddl, 'REFERENCES ' . $ref) === false) {
            $missingFKRefs[] = $ref;
        }
    }

    if (empty($missingFKRefs)) {
        echo "PASS: messages table contains all required foreign keys." . PHP_EOL;
    } else {
        echo "FAIL: messages table is missing FK references to: " . implode(', ', $missingFKRefs) . PHP_EOL;
        $pass = false;
    }
}

// ── Check 2: vendors.status enum includes 'removed' ──────────────────────────
$result = $conn->query("SHOW COLUMNS FROM vendors LIKE 'status'");
if (!$result) {
    echo "FAIL: Could not query SHOW COLUMNS FROM vendors — " . $conn->error . PHP_EOL;
    $pass = false;
} else {
    $row = $result->fetch_assoc();
    if (!$row) {
        echo "FAIL: Column 'status' not found in vendors table." . PHP_EOL;
        $pass = false;
    } elseif (strpos($row['Type'], 'removed') !== false) {
        echo "PASS: vendors.status Type includes 'removed' (" . $row['Type'] . ")." . PHP_EOL;
    } else {
        echo "FAIL: vendors.status Type does NOT include 'removed'. Got: " . $row['Type'] . PHP_EOL;
        $pass = false;
    }
}

// ── Summary ───────────────────────────────────────────────────────────────────
echo PHP_EOL . ($pass ? "All checks PASSED." : "One or more checks FAILED.") . PHP_EOL;
exit($pass ? 0 : 1);
