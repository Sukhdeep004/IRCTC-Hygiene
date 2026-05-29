<?php
// ============================================
// AI MODULE - IRCTC Hygiene Rating System
// AI-powered features for sentiment analysis,
// predictions, and intelligent alerts
// ============================================

/**
 * Sentiment Analysis for complaint text
 * Returns: positive, neutral, negative with confidence score
 */
function analyzeSentiment($text) {
    $text = strtolower($text);
    
    // Negative keywords
    $negativeWords = ['bad', 'poor', 'terrible', 'worst', 'horrible', 'disgusting', 'dirty', 
                      'unhygienic', 'sick', 'cockroach', 'insect', 'expired', 'stale', 'rotten',
                      'unacceptable', 'pathetic', 'awful', 'nasty', 'filthy', 'contaminated'];
    
    // Positive keywords
    $positiveWords = ['good', 'great', 'excellent', 'best', 'amazing', 'wonderful', 'clean',
                      'fresh', 'hygienic', 'delicious', 'tasty', 'quality', 'satisfied', 'happy'];
    
    $negativeCount = 0;
    $positiveCount = 0;
    
    foreach ($negativeWords as $word) {
        $negativeCount += substr_count($text, $word);
    }
    
    foreach ($positiveWords as $word) {
        $positiveCount += substr_count($text, $word);
    }
    
    $totalWords = str_word_count($text);
    $sentimentScore = $positiveCount - $negativeCount;
    
    if ($sentimentScore > 0) {
        $sentiment = 'positive';
        $confidence = min(100, ($positiveCount / max($totalWords, 1)) * 100 + 50);
    } elseif ($sentimentScore < 0) {
        $sentiment = 'negative';
        $confidence = min(100, ($negativeCount / max($totalWords, 1)) * 100 + 50);
    } else {
        $sentiment = 'neutral';
        $confidence = 50;
    }
    
    return [
        'sentiment' => $sentiment,
        'confidence' => round($confidence, 2),
        'score' => $sentimentScore,
        'positive_count' => $positiveCount,
        'negative_count' => $negativeCount
    ];
}

/**
 * Predict vendor hygiene score trend
 * Based on recent ratings pattern
 */
function predictVendorTrend($vendor_id) {
    global $conn;
    
    // Get last 10 ratings
    $result = $conn->query("SELECT final_score, created_at FROM ratings 
        WHERE vendor_id=$vendor_id ORDER BY created_at DESC LIMIT 10");
    
    if ($result->num_rows < 3) {
        return ['trend' => 'insufficient_data', 'prediction' => null];
    }
    
    $scores = [];
    while ($row = $result->fetch_assoc()) {
        $scores[] = (float)$row['final_score'];
    }
    
    // Calculate trend using simple linear regression
    $n = count($scores);
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = $scores[$i];
        $sumX += $x;
        $sumY += $y;
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }
    
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
    
    // Predict next score
    $nextScore = $slope * ($n + 1) + $intercept;
    $nextScore = max(0, min(5, $nextScore)); // Clamp between 0 and 5
    
    $trend = $slope > 0.1 ? 'improving' : ($slope < -0.1 ? 'declining' : 'stable');
    
    return [
        'trend' => $trend,
        'slope' => round($slope, 3),
        'prediction' => round($nextScore, 2),
        'current_avg' => round(array_sum($scores) / $n, 2),
        'sample_size' => $n
    ];
}

/**
 * Generate intelligent alert based on vendor performance
 */
function generateIntelligentAlert($vendor_id) {
    global $conn;
    
    $vendor = $conn->query("SELECT * FROM vendors WHERE id=$vendor_id")->fetch_assoc();
    if (!$vendor) return null;
    
    $trend = predictVendorTrend($vendor_id);
    $recentComplaints = $conn->query("SELECT COUNT(*) as c FROM complaints 
        WHERE vendor_id=$vendor_id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
    
    $alerts = [];
    
    // Critical score alert
    if ($vendor['current_score'] < 2.0) {
        $alerts[] = [
            'type' => 'critical',
            'priority' => 'high',
            'message' => 'CRITICAL: Vendor score below 2.0. Immediate suspension recommended.',
            'action' => 'suspend_vendor'
        ];
    }
    
    // Declining trend alert
    if ($trend['trend'] === 'declining' && $trend['slope'] < -0.2) {
        $alerts[] = [
            'type' => 'declining_trend',
            'priority' => 'medium',
            'message' => 'Vendor showing declining trend. Predicted score: ' . $trend['prediction'],
            'action' => 'schedule_inspection'
        ];
    }
    
    // Complaint spike alert
    if ($recentComplaints >= 3) {
        $alerts[] = [
            'type' => 'complaint_spike',
            'priority' => 'high',
            'message' => "$recentComplaints complaints in last 7 days. Investigation needed.",
            'action' => 'investigate_complaints'
        ];
    }
    
    return $alerts;
}

/**
 * Recommend inspection priority for vendors
 */
function calculateInspectionPriority() {
    global $conn;
    
    $vendors = $conn->query("SELECT v.*, 
        COUNT(DISTINCT c.id) as complaint_count,
        COUNT(DISTINCT r.id) as rating_count,
        MAX(ir.inspection_date) as last_inspection
        FROM vendors v
        LEFT JOIN complaints c ON v.id = c.vendor_id AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN ratings r ON v.id = r.vendor_id
        LEFT JOIN inspection_reports ir ON v.id = ir.vendor_id
        WHERE v.status != 'suspended'
        GROUP BY v.id");
    
    if (!$vendors) return [];
    
    $priorities = [];
    
    while ($vendor = $vendors->fetch_assoc()) {
        $score = 0;
        
        // Low hygiene score (higher priority)
        if ($vendor['current_score'] < 2.5) $score += 50;
        elseif ($vendor['current_score'] < 3.5) $score += 30;
        
        // Recent complaints
        $score += $vendor['complaint_count'] * 10;
        
        // No recent inspection
        if (!$vendor['last_inspection']) {
            $score += 40;
        } else {
            $daysSinceInspection = (strtotime('now') - strtotime($vendor['last_inspection'])) / 86400;
            if ($daysSinceInspection > 90) $score += 30;
            elseif ($daysSinceInspection > 60) $score += 20;
        }
        
        // Trend analysis
        $trend = predictVendorTrend($vendor['id']);
        if ($trend['trend'] === 'declining') $score += 25;
        
        $priorities[] = [
            'vendor_id' => $vendor['id'],
            'vendor_name' => $vendor['vendor_name'],
            'priority_score' => $score,
            'priority_level' => $score >= 70 ? 'urgent' : ($score >= 40 ? 'high' : 'medium'),
            'reasons' => [
                'current_score' => $vendor['current_score'],
                'complaints' => $vendor['complaint_count'],
                'trend' => $trend['trend'],
                'last_inspection' => $vendor['last_inspection']
            ]
        ];
    }
    
    // Sort by priority score
    usort($priorities, function($a, $b) {
        return $b['priority_score'] - $a['priority_score'];
    });
    
    return $priorities;
}

/**
 * AI-powered complaint categorization
 */
function categorizeComplaint($subject, $description) {
    $text = strtolower($subject . ' ' . $description);
    
    $categories = [
        'food_quality' => ['stale', 'expired', 'rotten', 'smell', 'taste', 'cold', 'undercooked', 'overcooked'],
        'hygiene' => ['dirty', 'unclean', 'cockroach', 'insect', 'fly', 'unhygienic', 'contaminated', 'filthy'],
        'staff_behavior' => ['rude', 'behavior', 'staff', 'attitude', 'unprofessional', 'disrespectful'],
        'packaging' => ['packaging', 'sealed', 'broken', 'damaged', 'leaking', 'torn'],
        'delivery' => ['late', 'delay', 'slow', 'waiting', 'time', 'never arrived']
    ];
    
    $scores = [];
    foreach ($categories as $category => $keywords) {
        $count = 0;
        foreach ($keywords as $keyword) {
            $count += substr_count($text, $keyword);
        }
        $scores[$category] = $count;
    }
    
    arsort($scores);
    $primaryCategory = key($scores);
    
    return [
        'primary_category' => $primaryCategory,
        'confidence' => min(100, $scores[$primaryCategory] * 30),
        'all_scores' => $scores
    ];
}

/**
 * Generate AI insights for admin dashboard
 */
function generateAIInsights() {
    global $conn;
    
    $insights = [];
    
    // Overall system health
    $avgScore = $conn->query("SELECT AVG(final_score) as avg FROM ratings 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['avg'];
    
    $avgScore = $avgScore ?? 0;
    
    $insights['system_health'] = [
        'score' => round($avgScore, 2),
        'status' => $avgScore >= 4.0 ? 'excellent' : ($avgScore >= 3.0 ? 'good' : 'needs_attention')
    ];
    
    // Top performing vendors
    $topVendors = $conn->query("SELECT vendor_name, current_score FROM vendors 
        WHERE status='active' ORDER BY current_score DESC LIMIT 3");
    $insights['top_performers'] = [];
    while ($v = $topVendors->fetch_assoc()) {
        $insights['top_performers'][] = $v;
    }
    
    // Vendors needing attention
    $atRiskVendors = $conn->query("SELECT vendor_name, current_score FROM vendors 
        WHERE current_score < 3.0 AND status!='suspended' ORDER BY current_score ASC LIMIT 3");
    $insights['at_risk'] = [];
    while ($v = $atRiskVendors->fetch_assoc()) {
        $insights['at_risk'][] = $v;
    }
    
    return $insights;
}
?>
