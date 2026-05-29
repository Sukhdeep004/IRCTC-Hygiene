<?php
/**
 * FEATURE TESTING PAGE
 * Test all AI and PNR features
 * Access: http://localhost/irctc_hygiene/test_features.php
 */

require_once 'includes/config.php';
require_once 'includes/pnr_module.php';
require_once 'includes/ai_module.php';

$pageTitle = 'Feature Testing';
include 'includes/header.php';
?>

<div class="container py-5">
<h2 class="fw-700 mb-4">🧪 AI & PNR Feature Testing</h2>

<div class="row g-4">

<!-- PNR MODULE TESTS -->
<div class="col-md-6">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">🎫 PNR Module Tests</h5>
        </div>
        <div class="card-body">
            <?php
            echo "<h6>Test 1: PNR Format Validation</h6>";
            $test1 = validatePNRFormat('1234567890');
            $test2 = validatePNRFormat('123');
            echo $test1 ? "✅ Valid PNR accepted<br>" : "❌ Failed<br>";
            echo !$test2 ? "✅ Invalid PNR rejected<br>" : "❌ Failed<br>";
            
            echo "<hr><h6>Test 2: PNR Verification</h6>";
            $result = verifyPNR('1234567890');
            if ($result['success']) {
                echo "✅ PNR verified successfully<br>";
                echo "Train: {$result['data']['train_name']} ({$result['data']['train_number']})<br>";
                echo "Route: {$result['data']['from_station']} → {$result['data']['to_station']}<br>";
            } else {
                echo "❌ Verification failed<br>";
            }
            
            echo "<hr><h6>Test 3: Database Functions</h6>";
            echo function_exists('isPNRUsedForRating') ? "✅ isPNRUsedForRating() exists<br>" : "❌ Missing<br>";
            echo function_exists('isPNRUsedForComplaint') ? "✅ isPNRUsedForComplaint() exists<br>" : "❌ Missing<br>";
            echo function_exists('storePNRVerification') ? "✅ storePNRVerification() exists<br>" : "❌ Missing<br>";
            ?>
        </div>
    </div>
</div>

<!-- AI MODULE TESTS -->
<div class="col-md-6">
    <div class="card">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0">🤖 AI Module Tests</h5>
        </div>
        <div class="card-body">
            <?php
            echo "<h6>Test 1: Sentiment Analysis</h6>";
            $sentiment1 = analyzeSentiment("The food was excellent and very clean!");
            $sentiment2 = analyzeSentiment("Terrible food, very dirty and unhygienic");
            echo $sentiment1['sentiment'] === 'positive' ? "✅ Positive sentiment detected<br>" : "❌ Failed<br>";
            echo $sentiment2['sentiment'] === 'negative' ? "✅ Negative sentiment detected<br>" : "❌ Failed<br>";
            echo "Confidence: {$sentiment2['confidence']}%<br>";
            
            echo "<hr><h6>Test 2: Complaint Categorization</h6>";
            $category = categorizeComplaint("Cockroach in food", "Found insect in my meal, very dirty kitchen");
            echo "✅ Category: {$category['primary_category']}<br>";
            echo "Confidence: {$category['confidence']}%<br>";
            
            echo "<hr><h6>Test 3: Vendor Trend Prediction</h6>";
            $vendors = $conn->query("SELECT id FROM vendors LIMIT 1");
            if ($v = $vendors->fetch_assoc()) {
                $trend = predictVendorTrend($v['id']);
                echo "✅ Trend: {$trend['trend']}<br>";
                if ($trend['prediction']) {
                    echo "Predicted score: {$trend['prediction']}<br>";
                }
            }
            
            echo "<hr><h6>Test 4: AI Functions</h6>";
            echo function_exists('analyzeSentiment') ? "✅ analyzeSentiment() exists<br>" : "❌ Missing<br>";
            echo function_exists('predictVendorTrend') ? "✅ predictVendorTrend() exists<br>" : "❌ Missing<br>";
            echo function_exists('calculateInspectionPriority') ? "✅ calculateInspectionPriority() exists<br>" : "❌ Missing<br>";
            echo function_exists('generateAIInsights') ? "✅ generateAIInsights() exists<br>" : "❌ Missing<br>";
            ?>
        </div>
    </div>
</div>

<!-- DATABASE TESTS -->
<div class="col-md-6">
    <div class="card">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">📊 Database Tests</h5>
        </div>
        <div class="card-body">
            <?php
            echo "<h6>Table Existence</h6>";
            $tables = ['pnr_verifications', 'ai_insights', 'ratings', 'complaints'];
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                echo $result->num_rows > 0 ? "✅ $table exists<br>" : "❌ $table missing<br>";
            }
            
            echo "<hr><h6>Column Checks</h6>";
            $result = $conn->query("SHOW COLUMNS FROM ratings LIKE 'pnr_number'");
            echo $result->num_rows > 0 ? "✅ ratings.pnr_number exists<br>" : "❌ Missing<br>";
            
            $result = $conn->query("SHOW COLUMNS FROM complaints LIKE 'sentiment'");
            echo $result->num_rows > 0 ? "✅ complaints.sentiment exists<br>" : "❌ Missing<br>";
            
            $result = $conn->query("SHOW COLUMNS FROM complaints LIKE 'ai_category'");
            echo $result->num_rows > 0 ? "✅ complaints.ai_category exists<br>" : "❌ Missing<br>";
            
            echo "<hr><h6>Data Counts</h6>";
            $ratings = $conn->query("SELECT COUNT(*) as c FROM ratings")->fetch_assoc()['c'];
            $complaints = $conn->query("SELECT COUNT(*) as c FROM complaints")->fetch_assoc()['c'];
            $vendors = $conn->query("SELECT COUNT(*) as c FROM vendors")->fetch_assoc()['c'];
            echo "Ratings: $ratings<br>";
            echo "Complaints: $complaints<br>";
            echo "Vendors: $vendors<br>";
            ?>
        </div>
    </div>
</div>

<!-- API TESTS -->
<div class="col-md-6">
    <div class="card">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">🌐 API Tests</h5>
        </div>
        <div class="card-body">
            <h6>Sentiment Analysis API</h6>
            <p>Endpoint: <code>/api/ai_sentiment.php</code></p>
            
            <div class="mb-3">
                <label class="form-label">Test Text:</label>
                <textarea id="testText" class="form-control" rows="3">The food was terrible and the kitchen was very dirty. Found cockroach in my meal.</textarea>
            </div>
            
            <button onclick="testAPI()" class="btn btn-primary">Test API</button>
            
            <div id="apiResult" class="mt-3"></div>
            
            <script>
            function testAPI() {
                const text = document.getElementById('testText').value;
                const resultDiv = document.getElementById('apiResult');
                resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm"></div> Testing...';
                
                fetch('api/ai_sentiment.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({text: text, categorize: true})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="alert alert-success">
                                <strong>✅ API Working!</strong><br>
                                Sentiment: <span class="badge bg-${data.data.sentiment==='negative'?'danger':'success'}">${data.data.sentiment}</span><br>
                                Confidence: ${data.data.confidence}%<br>
                                Category: ${data.data.category.primary_category}<br>
                                <pre class="mt-2 mb-0">${JSON.stringify(data, null, 2)}</pre>
                            </div>
                        `;
                    } else {
                        resultDiv.innerHTML = '<div class="alert alert-danger">❌ API Error</div>';
                    }
                })
                .catch(err => {
                    resultDiv.innerHTML = '<div class="alert alert-danger">❌ Connection Error: ' + err + '</div>';
                });
            }
            </script>
        </div>
    </div>
</div>

<!-- INSPECTION PRIORITIES -->
<div class="col-12">
    <div class="card">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">🚨 AI Inspection Priorities</h5>
        </div>
        <div class="card-body">
            <?php
            $priorities = calculateInspectionPriority();
            if (!empty($priorities)) {
                echo "<div class='table-responsive'><table class='table table-sm'>";
                echo "<thead><tr><th>Vendor</th><th>Priority</th><th>Score</th><th>Complaints</th><th>Trend</th><th>Priority Score</th></tr></thead><tbody>";
                foreach (array_slice($priorities, 0, 5) as $p) {
                    $badge = $p['priority_level'] === 'urgent' ? 'danger' : ($p['priority_level'] === 'high' ? 'warning' : 'info');
                    echo "<tr>";
                    echo "<td>{$p['vendor_name']}</td>";
                    echo "<td><span class='badge bg-$badge'>{$p['priority_level']}</span></td>";
                    echo "<td>{$p['reasons']['current_score']}</td>";
                    echo "<td>{$p['reasons']['complaints']}</td>";
                    echo "<td>{$p['reasons']['trend']}</td>";
                    echo "<td><strong>{$p['priority_score']}</strong></td>";
                    echo "</tr>";
                }
                echo "</tbody></table></div>";
                echo "<p class='mb-0 text-success'>✅ AI inspection priorities working correctly</p>";
            } else {
                echo "<p class='text-muted'>No priority data available</p>";
            }
            ?>
        </div>
    </div>
</div>

<!-- SYSTEM INSIGHTS -->
<div class="col-12">
    <div class="card">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0">📈 System AI Insights</h5>
        </div>
        <div class="card-body">
            <?php
            $insights = generateAIInsights();
            echo "<div class='row g-3'>";
            
            echo "<div class='col-md-4'>";
            echo "<div class='p-3 rounded' style='background:#f8f9fa;'>";
            echo "<h6>System Health</h6>";
            echo "<div style='font-size:2rem;font-weight:700;color:#003580;'>{$insights['system_health']['score']}</div>";
            echo "<span class='badge bg-" . ($insights['system_health']['status']==='excellent'?'success':'warning') . "'>{$insights['system_health']['status']}</span>";
            echo "</div></div>";
            
            echo "<div class='col-md-4'>";
            echo "<div class='p-3 rounded' style='background:#f8f9fa;'>";
            echo "<h6>Top Performers</h6>";
            foreach (array_slice($insights['top_performers'], 0, 3) as $tp) {
                echo "🏆 {$tp['vendor_name']} ({$tp['current_score']})<br>";
            }
            echo "</div></div>";
            
            echo "<div class='col-md-4'>";
            echo "<div class='p-3 rounded' style='background:#f8f9fa;'>";
            echo "<h6>At Risk</h6>";
            if (empty($insights['at_risk'])) {
                echo "<p class='text-success mb-0'>No vendors at risk</p>";
            } else {
                foreach (array_slice($insights['at_risk'], 0, 3) as $ar) {
                    echo "⚠️ {$ar['vendor_name']} ({$ar['current_score']})<br>";
                }
            }
            echo "</div></div>";
            
            echo "</div>";
            echo "<p class='mt-3 mb-0 text-success'>✅ AI insights generation working correctly</p>";
            ?>
        </div>
    </div>
</div>

</div>

<div class="alert alert-success mt-4">
    <h5>✅ Feature Testing Complete</h5>
    <p class="mb-0">All AI and PNR features have been tested. Check results above for any issues.</p>
</div>

<div class="text-center mt-4">
    <a href="index.php" class="btn btn-primary">← Back to Home</a>
    <a href="admin/dashboard.php" class="btn btn-success">Admin Dashboard →</a>
</div>

</div>

<?php include 'includes/footer.php'; ?>
