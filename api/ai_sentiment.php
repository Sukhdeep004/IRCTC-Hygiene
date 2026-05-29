<?php
// ============================================
// AI SENTIMENT ANALYSIS API
// IRCTC Hygiene Rating System
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/config.php';
require_once '../includes/ai_module.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['text']) || empty(trim($input['text']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Text parameter is required']);
    exit();
}

$text = trim($input['text']);

// Perform sentiment analysis
$result = analyzeSentiment($text);

// Add additional metadata
$response = [
    'success' => true,
    'data' => [
        'text' => $text,
        'sentiment' => $result['sentiment'],
        'confidence' => $result['confidence'],
        'score' => $result['score'],
        'details' => [
            'positive_keywords' => $result['positive_count'],
            'negative_keywords' => $result['negative_count']
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]
];

// Optional: categorize if it's a complaint
if (isset($input['categorize']) && $input['categorize'] === true) {
    $category = categorizeComplaint($text, '');
    $response['data']['category'] = $category;
}

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT);
?>
