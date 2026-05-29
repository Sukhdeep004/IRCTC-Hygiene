<?php
// ============================================
// PNR MODULE - IRCTC Hygiene Rating System
// Handles PNR verification and validation
// ============================================

/**
 * Validate PNR format (10-digit number)
 */
function validatePNRFormat($pnr) {
    return preg_match('/^\d{10}$/', $pnr);
}

/**
 * Mock PNR verification (simulates IRCTC API call)
 * In production, integrate with actual IRCTC PNR API
 */
function verifyPNR($pnr) {
    if (!validatePNRFormat($pnr)) {
        return ['success' => false, 'message' => 'Invalid PNR format. Must be 10 digits.'];
    }
    
    // Mock data - In production, call IRCTC API
    $mockPNRData = [
        '1234567890' => [
            'train_number' => '12301',
            'train_name' => 'Rajdhani Express',
            'travel_date' => '2026-03-08',
            'from_station' => 'New Delhi',
            'to_station' => 'Mumbai Central',
            'passenger_name' => 'Test Passenger',
            'status' => 'CNF',
            'coach' => 'A1',
            'seat' => '45'
        ],
        '9876543210' => [
            'train_number' => '12951',
            'train_name' => 'Mumbai Rajdhani',
            'travel_date' => '2026-03-09',
            'from_station' => 'Mumbai Central',
            'to_station' => 'New Delhi',
            'passenger_name' => 'Demo User',
            'status' => 'CNF',
            'coach' => 'B2',
            'seat' => '12'
        ]
    ];
    
    if (isset($mockPNRData[$pnr])) {
        return [
            'success' => true,
            'data' => $mockPNRData[$pnr],
            'message' => 'PNR verified successfully'
        ];
    }
    
    // Deterministic mock for any other valid 10-digit PNR — consistent result every time
    $seed = array_sum(str_split($pnr));
    $trains   = ['Shatabdi Express','Duronto Express','Garib Rath','Jan Shatabdi','Superfast Express'];
    $froms    = ['New Delhi','Mumbai CST','Chennai Central','Kolkata Howrah','Bangalore City'];
    $tos      = ['Ahmedabad Jn','Pune Junction','Hyderabad Deccan','Patna Junction','Jaipur Junction'];
    return [
        'success' => true,
        'data'    => [
            'train_number'   => '1' . str_pad(($seed % 9000) + 1000, 4, '0', STR_PAD_LEFT),
            'train_name'     => $trains[$seed % count($trains)],
            'travel_date'    => date('Y-m-d', strtotime('-' . ($seed % 20) . ' days')),
            'from_station'   => $froms[$seed % count($froms)],
            'to_station'     => $tos[$seed % count($tos)],
            'passenger_name' => 'Verified Passenger',
            'status'         => 'CNF',
            'coach'          => chr(65 + ($seed % 6)) . (($seed % 5) + 1),
            'seat'           => ($seed % 72) + 1,
        ],
        'message' => 'PNR verified successfully'
    ];
}

/**
 * Check if PNR has already been used for rating
 */
function isPNRUsedForRating($pnr, $vendor_id) {
    global $conn;
    $pnr = sanitize($pnr);
    $result = $conn->query("SELECT id FROM ratings WHERE pnr_number='$pnr' AND vendor_id=$vendor_id");
    return $result->num_rows > 0;
}

/**
 * Check if PNR has already been used for complaint
 */
function isPNRUsedForComplaint($pnr, $vendor_id) {
    global $conn;
    $pnr = sanitize($pnr);
    $result = $conn->query("SELECT id FROM complaints WHERE pnr_number='$pnr' AND vendor_id=$vendor_id");
    return $result->num_rows > 0;
}

/**
 * Get PNR details from database
 */
function getPNRDetails($pnr) {
    global $conn;
    $pnr = sanitize($pnr);
    $result = $conn->query("SELECT * FROM pnr_verifications WHERE pnr_number='$pnr' ORDER BY verified_at DESC LIMIT 1");
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Store PNR verification in database
 */
function storePNRVerification($pnr, $data, $user_id) {
    global $conn;
    $pnr = sanitize($pnr);
    $train_number = sanitize($data['train_number']);
    $train_name = sanitize($data['train_name']);
    $travel_date = sanitize($data['travel_date']);
    $from_station = sanitize($data['from_station']);
    $to_station = sanitize($data['to_station']);
    $passenger_name = sanitize($data['passenger_name']);
    $status = sanitize($data['status']);
    
    $conn->query("INSERT INTO pnr_verifications 
        (pnr_number, user_id, train_number, train_name, travel_date, from_station, to_station, passenger_name, booking_status) 
        VALUES ('$pnr', $user_id, '$train_number', '$train_name', '$travel_date', '$from_station', '$to_station', '$passenger_name', '$status')
        ON DUPLICATE KEY UPDATE verified_at=CURRENT_TIMESTAMP");
}

/**
 * Get vendor by train number and station
 */
function getVendorByTrainAndStation($train_number, $station) {
    global $conn;
    $train_number = sanitize($train_number);
    $station = sanitize($station);
    
    $result = $conn->query("SELECT * FROM vendors 
        WHERE (train_number='$train_number' OR train_number IS NULL OR train_number='') 
        AND station LIKE '%$station%' 
        AND status='active' 
        LIMIT 1");
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}
?>
