
<footer>
<div class="container">
    <div class="row g-3">
        <div class="col-md-5">
            <h6>🚂 IRCTC Food Hygiene Rating System</h6>
            <p style="font-size:0.83rem;margin-top:8px;">A centralized digital platform for monitoring railway catering hygiene standards. Empowering passengers, vendors, and administrators.</p>
        </div>
        <div class="col-md-2">
            <h6>Links</h6>
            <ul class="list-unstyled mt-2" style="font-size:0.83rem;">
                <li><a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>index.php">Home</a></li>
                <li><a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>vendors_list.php">Vendors</a></li>
                <li><a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>login.php">Login</a></li>
            </ul>
        </div>
        <div class="col-md-3">
            <h6>⚡ Our Commitment</h6>
            <div style="margin-top:8px;padding:12px;background:rgba(255,255,255,0.07);border-radius:8px;border-left:3px solid #f5a623;">
                <div style="font-size:1.1rem;font-weight:700;color:#ffd700;letter-spacing:0.5px;">⏱️ 8-Hour Promise</div>
                <p style="font-size:0.8rem;margin:6px 0 0;line-height:1.6;opacity:0.9;">Every complaint is reviewed and action is taken <strong>within 8 hours</strong> — because your safety can't wait.</p>
            </div>
        </div>
        <div class="col-md-2">
            <h6>Contact</h6>
            <p style="font-size:0.83rem;margin-top:8px;">
                <i class="fas fa-phone me-1"></i>1800-111-139<br>
                <i class="fas fa-envelope me-1"></i>hygiene@irctc.in
            </p>
        </div>
    </div>
    <hr style="border-color:rgba(255,255,255,0.1);margin:20px 0 12px;">
    <div class="text-center" style="font-size:0.78rem;">© <?= date('Y') ?> IRCTC Hygiene Rating System | All Rights Reserved</div>
</div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(msg, type='success') {
    const id = 'toast_' + Date.now();
    const isSuccess = type === 'success';
    const t = document.createElement('div');
    t.className = 'toast-card';
    t.id = id;
    t.innerHTML = `
        <div class="toast-bar ${isSuccess ? 'success' : 'danger'}"></div>
        <div class="toast-body">
            <div class="d-flex align-items-start gap-3">
                <div class="toast-icon ${isSuccess ? 'success' : 'danger'}">
                    <i class="fas fa-${isSuccess ? 'check-circle' : 'exclamation-circle'}"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="toast-title">${isSuccess ? 'Success' : 'Error'}</div>
                        <button class="toast-close ms-2" onclick="dismissToast('${id}')"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="toast-msg">${msg}</div>
                </div>
            </div>
        </div>`;
    document.getElementById('toastArea').appendChild(t);
    setTimeout(() => { t.classList.add('hiding'); setTimeout(() => t.remove(), 300); }, 4500);
}
</script>
<?php if (isLoggedIn() && isPassenger() || !isLoggedIn()): require_once __DIR__ . '/ai_chat.php'; endif; ?>
</body>
</html>
