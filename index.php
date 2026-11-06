<?php
require_once 'config/functions.php';
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/navbar.php'; ?>

<div class="container mt-4">
    <div class="row min-vh-100 align-items-center">
        <div class="col-lg-6">
            <h1 class="display-4 fw-bold text-white mb-4">Your Voice Matters</h1>
            <p class="lead text-white mb-4">SafeVoice provides a confidential and secure platform for survivors of violence to report incidents and access support services.</p>
            <div class="d-flex gap-3">
                <?php if(!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-light btn-lg">Get Started</a>
                    <a href="login.php" class="btn btn-outline-light btn-lg">Login</a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-light btn-lg">Go to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card bg-white bg-opacity-10 text-white">
                <div class="card-body p-4">
                    <h3 class="mb-3">Emergency Help</h3>
                    <p class="mb-2"><i class="fas fa-phone-alt me-2"></i> National Helpline: <strong>911</strong></p>
                    <p class="mb-2"><i class="fas fa-comment me-2"></i> Crisis Text Line: Text <strong>SAFE</strong> to 741741</p>
                    <p class="mb-0"><i class="fas fa-globe me-2"></i> National Domestic Violence Hotline: <strong>1-800-799-7233</strong></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-5 mb-5">
        <div class="col-12 text-center text-white">
            <h2 class="mb-4">About SafeVoice</h2>
            <p class="lead">SafeVoice is committed to providing a safe, anonymous, and accessible platform for survivors of violence.</p>
        </div>
    </div>
    
    <div class="row mb-5 g-4">
        <div class="col-md-4">
            <div class="card h-100 text-center p-3">
                <div class="card-body">
                    <i class="fas fa-shield-alt fa-3x mb-3" style="color: #667eea;"></i>
                    <h5 class="card-title">Confidential & Secure</h5>
                    <p class="card-text">Your privacy is our priority. All reports are handled with utmost confidentiality and security.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 text-center p-3">
                <div class="card-body">
                    <i class="fas fa-users fa-3x mb-3" style="color: #667eea;"></i>
                    <h5 class="card-title">Professional Support</h5>
                    <p class="card-text">Connect with trained responders and support services in your area.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 text-center p-3">
                <div class="card-body">
                    <i class="fas fa-chart-line fa-3x mb-3" style="color: #667eea;"></i>
                    <h5 class="card-title">Track Your Case</h5>
                    <p class="card-text">Stay updated on the progress of your report with real-time case tracking.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-12">
            <div class="card bg-white bg-opacity-10 text-white">
                <div class="card-body text-center">
                    <h4>Need Immediate Help?</h4>
                    <p class="mb-0">Call our 24/7 Helpline: <strong>1-800-SAFE-VOICE</strong> or email: <strong>help@safevoice.com</strong></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>