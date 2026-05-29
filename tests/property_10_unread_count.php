<?php
// Feature: complaint-analytics-communication, Property 10: Unread count accuracy
// Validates: Requirements 4.6, 6.4, 7.4
//
// Property 10: For any user U, getUnreadMessageCount(U) SHALL return a value equal to
// SELECT COUNT(*) FROM messages WHERE receiver_id=U AND is_read=0.
//
// Run: php tests/property_10_unread_count.php

require_once __DIR__ . '/../includes/config.php';

// ── Test user IDs (high values unlikely to conflict with real data) ────────────
const TEST_SENDER_ID   = 99998;
const TEST_RECEIVER_ID = 99999;
const MARKER           = 'PBT_P10_TEST';
const ITERATIONS       = 100;

// ── Setup: insert test users if they don't exist ──────────────────────────────
function ensureTestUser(mysqli $conn, int $uid, string $name, string $email): void {
    $existing = $conn->query("SELECT id FROM users WHERE id = $uid");
    if ($existing && $existing->num_rows === 0) {
        $conn->query(
            "INSERT INTO users (id, name, email, phone, password, role)
             VALUES ($uid, '$name', '$email', '0000000000',
                     '\$2y\$10\$placeholder_hash_for_pbt_test_only', 'passenger')"
        );
    }
}

// ── Cleanup: remove all test messages for the test receiver ──────────────────
function cleanupTestMessages(mysqli $conn): void {
    $conn->query(
        "DELETE FROM messages
         WHERE (receiver_id = " . TEST_RECEIVER_ID . " OR sender_id = " . TEST_SENDER_ID . ")
           AND message_body LIKE '%" . MARKER . "%'"
    );
}

// ── Insert N unread messages addressed to TEST_RECEIVER_ID ───────────────────
function insertUnreadMessages(mysqli $conn, int $n): void {
    for ($i = 0; $i < $n; $i++) {
        $body = MARKER . "_msg_$i";
        $conn->query(
            "INSERT INTO messages (sender_id, receiver_id, message_body, is_read)
             VALUES (" . TEST_SENDER_ID . ", " . TEST_RECEIVER_ID . ", '$body', 0)"
        );
    }
}

// ── Mark a subset of test messages as read ───────────────────────────────────
function markSubsetRead(mysqli $conn, int $markCount): void {
    $conn->query(
        "UPDATE messages
         SET is_read = 1
         WHERE receiver_id = " . TEST_RECEIVER_ID . "
           AND message_body LIKE '%" . MARKER . "%'
           AND is_read = 0
         LIMIT $markCount"
    );
}

// ── Main ──────────────────────────────────────────────────────────────────────
ensureTestUser($conn, TEST_SENDER_ID,   'PBT_Sender',   'pbt_sender_p10@test.invalid');
ensureTestUser($conn, TEST_RECEIVER_ID, 'PBT_Receiver', 'pbt_receiver_p10@test.invalid');

$totalPass = 0;
$totalFail = 0;
$failures  = [];

for ($iter = 1; $iter <= ITERATIONS; $iter++) {
    cleanupTestMessages($conn);

    // Random N in [1, 20]
    $n = rand(1, 20);

    // (b) Insert N unread messages
    insertUnreadMessages($conn, $n);

    // (c) Assert getUnreadMessageCount === N
    $countAfterInsert = getUnreadMessageCount(TEST_RECEIVER_ID);
    if ($countAfterInsert !== $n) {
        $msg = "Iter $iter: FAIL (insert) — expected $n unread, got $countAfterInsert";
        echo $msg . PHP_EOL;
        $failures[] = $msg;
        $totalFail++;
        cleanupTestMessages($conn);
        continue;
    }

    // (d) Pick a random subset to mark as read: 0 to N
    $markCount = rand(0, $n);
    markSubsetRead($conn, $markCount);

    // (e) Assert count decremented correctly
    $expectedAfterMark = $n - $markCount;
    $countAfterMark    = getUnreadMessageCount(TEST_RECEIVER_ID);

    if ($countAfterMark !== $expectedAfterMark) {
        $msg = "Iter $iter: FAIL (mark-read) — N=$n, marked=$markCount, expected=$expectedAfterMark, got=$countAfterMark";
        echo $msg . PHP_EOL;
        $failures[] = $msg;
        $totalFail++;
    } else {
        echo "Iter $iter: PASS (N=$n, marked=$markCount, remaining=$countAfterMark)" . PHP_EOL;
        $totalPass++;
    }

    // (f) Clean up
    cleanupTestMessages($conn);
}

// ── Cleanup test users ────────────────────────────────────────────────────────
cleanupTestMessages($conn);
$conn->query("DELETE FROM users WHERE id IN (" . TEST_SENDER_ID . ", " . TEST_RECEIVER_ID . ")");

// ── Summary ───────────────────────────────────────────────────────────────────
echo PHP_EOL;
echo "=== Property 10: Unread count accuracy ===" . PHP_EOL;
echo "Iterations : " . ITERATIONS . PHP_EOL;
echo "PASS       : $totalPass" . PHP_EOL;
echo "FAIL       : $totalFail" . PHP_EOL;

if ($totalFail === 0) {
    echo PHP_EOL . "RESULT: ALL PASSED" . PHP_EOL;
    exit(0);
} else {
    echo PHP_EOL . "RESULT: FAILED" . PHP_EOL;
    foreach ($failures as $f) {
        echo "  - $f" . PHP_EOL;
    }
    exit(1);
}
