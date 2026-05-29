<?php
// AI Chat Widget - Passenger only
// Include this at the bottom of passenger pages before </body>

function getAIChatResponse($message) {
    $msg = strtolower(trim($message));

    // Greetings
    if (preg_match('/\b(hi|hello|hey|namaste)\b/', $msg))
        return isLoggedIn()
            ? "Hello! 👋 I'm your IRCTC Hygiene Assistant. I can help you file complaints, check vendor ratings, understand your complaint status, or answer hygiene-related questions. What do you need?"
            : "Hello! 👋 I'm the IRCTC Hygiene AI Assistant. I can help you understand how to file complaints, check vendor ratings, or learn about food safety on trains. <br><br>To file a complaint or rate a vendor, <a href='register.php' style='color:#fff;text-decoration:underline;'>Register as a Passenger →</a> or <a href='login.php' style='color:#fff;text-decoration:underline;'>Login</a> if you already have an account.";

    // Register / login intent
    if (strpos($msg, 'register') !== false || strpos($msg, 'sign up') !== false || strpos($msg, 'signup') !== false || strpos($msg, 'create account') !== false)
        return "Creating an account is free and quick! <a href='register.php' style='color:#fff;text-decoration:underline;'>Register as a Passenger →</a><br><br>Once registered you can: file complaints, rate vendors, and track your complaint status in real time.";

    if (strpos($msg, 'login') !== false || strpos($msg, 'log in') !== false || strpos($msg, 'sign in') !== false)
        return "Already have an account? <a href='login.php' style='color:#fff;text-decoration:underline;'>Login here →</a><br><br>If you don't have an account yet, <a href='register.php' style='color:#fff;text-decoration:underline;'>Register as a Passenger</a> — it's free!";

    // Complaint status
    if (strpos($msg, 'complaint') !== false && (strpos($msg, 'status') !== false || strpos($msg, 'track') !== false || strpos($msg, 'check') !== false))
        return isLoggedIn() && isPassenger()
            ? "To track your complaint, go to <a href='complaint.php' style='color:#fff;text-decoration:underline;'>File Complaint</a> page and scroll down to 'My Complaints'. Your complaint code (e.g. CMP-2026-XXXX) shows the current status and any officer/admin updates."
            : "To track a complaint you need to be logged in. <a href='login.php' style='color:#fff;text-decoration:underline;'>Login here →</a> or <a href='register.php' style='color:#fff;text-decoration:underline;'>Register as Passenger</a> to get started.";

    // How to file complaint
    if ((strpos($msg, 'file') !== false || strpos($msg, 'submit') !== false || strpos($msg, 'raise') !== false) && strpos($msg, 'complaint') !== false)
        return isLoggedIn() && isPassenger()
            ? "To file a complaint: 1️⃣ Verify your PNR number → 2️⃣ Select the vendor → 3️⃣ Describe the issue (min 20 chars) → 4️⃣ Optionally attach an image → 5️⃣ Submit. Our AI will analyze your complaint automatically. <a href='complaint.php' style='color:#fff;text-decoration:underline;'>Go to complaint form →</a>"
            : "To file a complaint, you need a passenger account. <a href='register.php' style='color:#fff;text-decoration:underline;'>Register here →</a> — it's free and takes less than a minute! Then verify your PNR and submit your complaint.";

    // Rating
    if (strpos($msg, 'rate') !== false || strpos($msg, 'rating') !== false)
        return isLoggedIn() && isPassenger()
            ? "You can rate a vendor on 5 parameters: Cleanliness, Food Quality, Packaging, Staff Hygiene, and Timeliness. <a href='rate.php' style='color:#fff;text-decoration:underline;'>Rate a vendor →</a>"
            : "Passengers can rate vendors on 5 hygiene parameters after their journey. <a href='register.php' style='color:#fff;text-decoration:underline;'>Register as a Passenger →</a> to submit ratings.";

    // Vendor / hygiene score
    if (strpos($msg, 'vendor') !== false || strpos($msg, 'score') !== false || strpos($msg, 'hygiene') !== false)
        return "Vendor hygiene scores are calculated from passenger ratings and officer inspections. Scores range from 1–5: ⭐ 4.5+ Excellent | 3.5+ Good | 2.5+ Average | below 2.5 triggers an alert. <a href='../vendors_list.php' style='color:#fff;text-decoration:underline;'>Browse vendors →</a>";

    // PNR
    if (strpos($msg, 'pnr') !== false)
        return "Your PNR is a 10-digit number found on your train ticket or IRCTC booking confirmation. It's required to verify your journey before filing a complaint. Demo PNRs for testing: 1234567890 or 9876543210.";

    // Officer / action
    if (strpos($msg, 'officer') !== false || strpos($msg, 'action') !== false || strpos($msg, 'investigation') !== false)
        return "Once you file a complaint, an inspection officer reviews it and takes action — they may investigate, take action against the vendor, or mark no violation found. You'll see their response in your complaint timeline.";

    // Admin
    if (strpos($msg, 'admin') !== false)
        return "After the officer acts on your complaint, the admin reviews and acknowledges the entire process. This ensures full accountability at every level.";

    // Food safety / hygiene tips
    if (strpos($msg, 'food') !== false || strpos($msg, 'safe') !== false || strpos($msg, 'tip') !== false)
        return "🍱 Food Safety Tips on trains: Check packaging seal before eating. Avoid food that smells off or looks discolored. Report unhygienic staff or dirty utensils immediately via a complaint.";

    // Help
    if (strpos($msg, 'help') !== false || strpos($msg, 'what can you') !== false)
        return isLoggedIn()
            ? "I can help you with: <br>• 📝 How to file a complaint<br>• 🔍 Tracking complaint status<br>• ⭐ Rating vendors<br>• 🏪 Understanding vendor scores<br>• 🎫 PNR verification<br>• 🛡️ What officers & admins do<br><br>Just ask me anything!"
            : "I can help you with: <br>• 📝 How to file a complaint<br>• ⭐ How vendor ratings work<br>• 🏪 Understanding hygiene scores<br>• 🎫 What is PNR verification<br>• 🛡️ How complaints are processed<br>• 🔐 How to register or login<br><br>Just ask me anything!";

    // Thank you
    if (preg_match('/\b(thanks|thank you|thx|shukriya)\b/', $msg))
        return "You're welcome! 😊 If you face any hygiene issues on your journey, don't hesitate to file a complaint. Safe travels! 🚂";

    // Default
    return "I'm not sure about that. I can help with complaints, vendor ratings, PNR verification, or hygiene tips. Try asking something like 'how do I file a complaint?' or 'what is my complaint status?'";
}

// Handle AJAX request
if (isset($_POST['ai_chat_message'])) {
    header('Content-Type: application/json');
    $msg = isset($_POST['message']) ? trim($_POST['message']) : '';
    if (!$msg || strlen($msg) > 500) {
        echo json_encode(['reply' => 'Please enter a valid message.']);
    } else {
        echo json_encode(['reply' => getAIChatResponse($msg)]);
    }
    exit;
}
?>

<!-- AI Chat Widget HTML + JS -->
<style>
#ai-chat-btn {
    position: fixed; bottom: 28px; right: 28px; z-index: 9999;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #1a56db, #0e9f6e);
    border: none; color: #fff; font-size: 1.4rem;
    box-shadow: 0 4px 16px rgba(0,0,0,0.25); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: transform 0.2s;
}
#ai-chat-btn:hover { transform: scale(1.1); }
#ai-chat-box {
    position: fixed; bottom: 96px; right: 28px; z-index: 9998;
    width: 340px; max-height: 480px;
    background: #1a56db; border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.22);
    display: none; flex-direction: column; overflow: hidden;
}
#ai-chat-header {
    background: linear-gradient(135deg, #1a56db, #0e9f6e);
    color: #fff; padding: 14px 16px;
    display: flex; align-items: center; justify-content: space-between;
    font-weight: 600; font-size: 0.95rem;
}
#ai-chat-messages {
    flex: 1; overflow-y: auto; padding: 12px;
    background: #f0f5ff; max-height: 320px;
    display: flex; flex-direction: column; gap: 8px;
}
.ai-msg, .user-msg {
    max-width: 85%; padding: 8px 12px; border-radius: 12px;
    font-size: 0.83rem; line-height: 1.45;
}
.ai-msg { background: #1a56db; color: #fff; align-self: flex-start; border-bottom-left-radius: 4px; }
.user-msg { background: #e2e8f0; color: #1a202c; align-self: flex-end; border-bottom-right-radius: 4px; }
#ai-chat-input-row {
    display: flex; gap: 6px; padding: 10px 12px;
    background: #fff; border-top: 1px solid #e2e8f0;
}
#ai-chat-input {
    flex: 1; border: 1px solid #cbd5e0; border-radius: 20px;
    padding: 7px 14px; font-size: 0.83rem; outline: none;
}
#ai-chat-send {
    background: #1a56db; color: #fff; border: none;
    border-radius: 50%; width: 34px; height: 34px;
    cursor: pointer; font-size: 0.9rem; flex-shrink: 0;
}
.ai-typing { font-size: 0.78rem; color: #888; padding: 4px 8px; }
</style>

<button id="ai-chat-btn" title="Ask AI Assistant">
    <i class="fas fa-robot"></i>
</button>

<div id="ai-chat-box">
    <div id="ai-chat-header">
        <span><i class="fas fa-robot me-2"></i>IRCTC AI Assistant</span>
        <button onclick="toggleChat()" style="background:none;border:none;color:#fff;font-size:1.1rem;cursor:pointer;">✕</button>
    </div>
    <div id="ai-chat-messages">
        <div class="ai-msg">
            <?php if (!isLoggedIn()): ?>
            👋 Hi! I'm the IRCTC Hygiene AI Assistant. Ask me about filing complaints, vendor ratings, food safety, or how to get started!
            <?php else: ?>
            👋 Hi! I'm your IRCTC Hygiene Assistant. Ask me about complaints, vendor ratings, PNR, or food safety tips!
            <?php endif; ?>
        </div>
    </div>
    <div id="ai-chat-input-row">
        <input type="text" id="ai-chat-input" placeholder="Type your question..." maxlength="300" autocomplete="off">
        <button id="ai-chat-send" onclick="sendChatMessage()"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<script>
function toggleChat() {
    const box = document.getElementById('ai-chat-box');
    box.style.display = box.style.display === 'flex' ? 'none' : 'flex';
    if (box.style.display === 'flex') document.getElementById('ai-chat-input').focus();
}
document.getElementById('ai-chat-btn').addEventListener('click', toggleChat);
document.getElementById('ai-chat-input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') sendChatMessage();
});

function sendChatMessage() {
    const input = document.getElementById('ai-chat-input');
    const msgs  = document.getElementById('ai-chat-messages');
    const text  = input.value.trim();
    if (!text) return;

    // Show user message
    const userDiv = document.createElement('div');
    userDiv.className = 'user-msg';
    userDiv.textContent = text;
    msgs.appendChild(userDiv);
    input.value = '';

    // Typing indicator
    const typing = document.createElement('div');
    typing.className = 'ai-typing';
    typing.textContent = 'AI is typing...';
    msgs.appendChild(typing);
    msgs.scrollTop = msgs.scrollHeight;

    fetch('<?= defined('BASEPATH') ? BASEPATH : '' ?>includes/ai_chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ai_chat_message=1&message=' + encodeURIComponent(text)
    })
    .then(r => r.json())
    .then(data => {
        msgs.removeChild(typing);
        const aiDiv = document.createElement('div');
        aiDiv.className = 'ai-msg';
        aiDiv.innerHTML = data.reply;
        msgs.appendChild(aiDiv);
        msgs.scrollTop = msgs.scrollHeight;
    })
    .catch(() => {
        msgs.removeChild(typing);
        const errDiv = document.createElement('div');
        errDiv.className = 'ai-msg';
        errDiv.textContent = 'Sorry, something went wrong. Please try again.';
        msgs.appendChild(errDiv);
    });
}
</script>
