<?php
// KIZZA TOURS & SAFARIS - Admin Login with OTP
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/mail.php';
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard');
    exit;
}

$error = '';
$otpSent = false;
$otpEmail = '';
$otpError = '';

// Check if OTP table exists, create if not
try {
    $db = db();
    $db->query("CREATE TABLE IF NOT EXISTS admin_otp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        otp_code VARCHAR(6) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log("OTP table creation failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $db = db();

    // STEP 1: Username + Password verification
    if (isset($_POST['login_step']) && $_POST['login_step'] === 'credentials') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!empty($username) && !empty($password)) {
            try {
                $admin = $db->fetchOne(
                    "SELECT * FROM admin_users WHERE username = ? OR email = ? LIMIT 1",
                    [$username, $username]
                );

                if ($admin && password_verify($password, $admin['password'])) {
                    // Generate 6-digit OTP
                    $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                    // Save OTP to database
                    $db->query(
                        "INSERT INTO admin_otp (admin_id, otp_code, expires_at) VALUES (?, ?, ?)",
                        [$admin['id'], $otp, $expires]
                    );

                    // Store pending admin ID in session (not logged in yet)
                    $_SESSION['otp_admin_id'] = $admin['id'];
                    $_SESSION['otp_admin_name'] = $admin['full_name'];
                    $_SESSION['otp_sent_at'] = time();

                    // Mask email for display: j***n@g***l.com
                    $emailParts = explode('@', $admin['email']);
                    $masked = substr($emailParts[0], 0, 2) . str_repeat('*', max(strlen($emailParts[0]) - 2, 3));
                    if (isset($emailParts[1])) {
                        $domainParts = explode('.', $emailParts[1]);
                        $maskedDomain = substr($domainParts[0], 0, 1) . str_repeat('*', max(strlen($domainParts[0]) - 1, 3));
                        if (isset($domainParts[1])) $maskedDomain .= '.' . $domainParts[1];
                        $masked .= '@' . $maskedDomain;
                    }
                    $otpEmail = $masked;

                    // Store OTP in session as fallback
                    $_SESSION['otp_backup_code'] = $otp;
                    $_SESSION['otp_backup_email'] = $admin['email'];

                    // Send OTP via email (non-blocking)
                    $otpBody = "
                    <div style='font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; padding: 20px;'>
                        <div style='text-align: center; padding: 20px 0;'>
                            <h2 style='color: #0A2540; margin: 0;'>Kizza Tours & Safaris</h2>
                            <p style='color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 2px;'>Admin Login Verification</p>
                        </div>
                        <div style='background: #f8f9fa; border-radius: 12px; padding: 30px; text-align: center;'>
                            <p style='color: #333; margin-bottom: 10px;'>Your one-time verification code is:</p>
                            <div style='font-size: 36px; font-weight: bold; color: #0A2540; letter-spacing: 8px; padding: 15px 0;'>" . $otp . "</div>
                            <p style='color: #666; font-size: 13px; margin-top: 15px;'>This code expires in <strong>5 minutes</strong>.</p>
                            <p style='color: #999; font-size: 12px; margin-top: 10px;'>If you didn't request this login, ignore this email.</p>
                        </div>
                    </div>";

                    $mailSent = @sendMail($admin['email'], "Your Admin Login Code: " . $otp, $otpBody);

                    $otpSent = true;
                } else {
                    $error = 'Invalid username or password';
                }
            } catch (Exception $e) {
                $error = 'An error occurred. Please try again.';
                error_log("Admin Login Error: " . $e->getMessage());
            }
        } else {
            $error = 'Please enter username and password';
        }
    }

    // STEP 2: OTP verification
    if (isset($_POST['login_step']) && $_POST['login_step'] === 'otp') {
        $otpInput = trim($_POST['otp_code'] ?? '');
        $adminId = intval($_SESSION['otp_admin_id'] ?? 0);

        if (empty($otpInput)) {
            $otpError = 'Please enter the verification code';
            $otpSent = true;
            $otpEmail = $_POST['otp_email_display'] ?? '';
        } elseif (!$adminId) {
            $error = 'Session expired. Please login again.';
        } else {
            try {
                // Find valid OTP (from DB or backup session)
                $otpRecord = $db->fetchOne(
                    "SELECT * FROM admin_otp WHERE admin_id = ? AND otp_code = ? AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
                    [$adminId, $otpInput]
                );

                // Also check session backup if DB fails
                $backupValid = (
                    !empty($_SESSION['otp_backup_code']) &&
                    $_SESSION['otp_backup_code'] === $otpInput &&
                    (time() - intval($_SESSION['otp_sent_at'] ?? 0)) < 300
                );

                if ($otpRecord || $backupValid) {
                    // Mark OTP as used
                    $db->query("UPDATE admin_otp SET used = 1 WHERE id = ?", [$otpRecord['id']]);

                    // Get admin details
                    $admin = $db->fetchOne("SELECT * FROM admin_users WHERE id = ?", [$adminId]);

                    // Clean up old OTPs
                    $db->query("DELETE FROM admin_otp WHERE admin_id = ? AND (expires_at < NOW() OR used = 1)", [$adminId]);

                    // Complete login
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_image'] = $admin['profile_image'] ?? null;

                    // Update last login
                    $db->query("UPDATE admin_users SET last_login = NOW() WHERE id = ?", [$admin['id']]);

                    // Clean up OTP session
                    unset($_SESSION['otp_admin_id'], $_SESSION['otp_admin_name'], $_SESSION['otp_sent_at'], $_SESSION['otp_backup_code'], $_SESSION['otp_backup_email']);

                    header('Location: dashboard');
                    exit;
                } else {
                    $otpError = 'Invalid or expired verification code';
                    $otpSent = true;
                    $otpEmail = $_POST['otp_email_display'] ?? '';
                }
            } catch (Exception $e) {
                $otpError = 'An error occurred. Please try again.';
                error_log("OTP Verification Error: " . $e->getMessage());
                $otpSent = true;
                $otpEmail = $_POST['otp_email_display'] ?? '';
            }
        }
    }

    // Resend OTP
    if (isset($_POST['login_step']) && $_POST['login_step'] === 'resend') {
        $adminId = intval($_SESSION['otp_admin_id'] ?? 0);
        $lastSent = intval($_SESSION['otp_sent_at'] ?? 0);

        if (!$adminId) {
            $error = 'Session expired. Please login again.';
        } elseif ((time() - $lastSent) < 30) {
            $otpError = 'Please wait 30 seconds before resending';
            $otpSent = true;
            $otpEmail = $_POST['otp_email_display'] ?? '';
        } else {
            try {
                $admin = $db->fetchOne("SELECT * FROM admin_users WHERE id = ?", [$adminId]);
                if ($admin) {
                    $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                    // Invalidate old OTPs
                    $db->query("UPDATE admin_otp SET used = 1 WHERE admin_id = ? AND used = 0", [$adminId]);

                    // Save new OTP
                    $db->query(
                        "INSERT INTO admin_otp (admin_id, otp_code, expires_at) VALUES (?, ?, ?)",
                        [$adminId, $otp, $expires]
                    );

                    $_SESSION['otp_sent_at'] = time();

                    $emailParts = explode('@', $admin['email']);
                    $masked = substr($emailParts[0], 0, 2) . str_repeat('*', max(strlen($emailParts[0]) - 2, 3));
                    if (isset($emailParts[1])) {
                        $domainParts = explode('.', $emailParts[1]);
                        $maskedDomain = substr($domainParts[0], 0, 1) . str_repeat('*', max(strlen($domainParts[0]) - 1, 3));
                        if (isset($domainParts[1])) $maskedDomain .= '.' . $domainParts[1];
                        $masked .= '@' . $maskedDomain;
                    }
                    $otpEmail = $masked;

                    $otpBody = "
                    <div style='font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; padding: 20px;'>
                        <div style='text-align: center; padding: 20px 0;'>
                            <h2 style='color: #0A2540; margin: 0;'>Kizza Tours & Safaris</h2>
                            <p style='color: #888; font-size: 12px; text-transform: uppercase; letter-spacing: 2px;'>Admin Login Verification</p>
                        </div>
                        <div style='background: #f8f9fa; border-radius: 12px; padding: 30px; text-align: center;'>
                            <p style='color: #333; margin-bottom: 10px;'>Your new verification code is:</p>
                            <div style='font-size: 36px; font-weight: bold; color: #0A2540; letter-spacing: 8px; padding: 15px 0;'>" . $otp . "</div>
                            <p style='color: #666; font-size: 13px; margin-top: 15px;'>This code expires in <strong>5 minutes</strong>.</p>
                            <p style='color: #999; font-size: 12px; margin-top: 10px;'>If you didn't request this login, ignore this email.</p>
                        </div>
                    </div>";

                    sendMail($admin['email'], "Your Admin Login Code: " . $otp, $otpBody);
                    $otpSent = true;
                }
            } catch (Exception $e) {
                $otpError = 'Failed to resend code. Please try again.';
                $otpSent = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Kizza Tours & Safaris</title>
    <link rel="icon" href="../assets/images/log.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0A2540;
            --secondary: #D4AF37;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0A2540 0%, #0D2E4A 50%, #1A3A5C 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 3rem;
            max-width: 420px;
            margin: 0 auto;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 0.5rem;
        }
        .login-title {
            text-align: center;
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 2rem;
        }
        .form-control {
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 12px;
            color: #fff;
            padding: 0.9rem 1.2rem;
        }
        .form-control:focus {
            background: rgba(255,255,255,0.12);
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(212,175,55,0.15);
            color: #fff;
        }
        .form-control::placeholder { color: rgba(255,255,255,0.4); }
        .form-label { color: rgba(255,255,255,0.7); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .btn-login {
            background: linear-gradient(135deg, var(--secondary), #C9A227);
            color: #fff;
            border: none;
            padding: 0.9rem;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 0.85rem;
            transition: all 0.3s;
            width: 100%;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(212,175,55,0.3);
            color: #fff;
        }
        .error-msg {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: #EF4444;
            font-size: 0.85rem;
            text-align: center;
        }
        .success-msg {
            background: rgba(34,197,94,0.15);
            border: 1px solid rgba(34,197,94,0.3);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            color: #22C55E;
            font-size: 0.85rem;
            text-align: center;
        }
        .otp-input {
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            letter-spacing: 12px;
            padding: 0.9rem;
        }
        .otp-input::placeholder {
            letter-spacing: 4px;
            font-size: 1rem;
            font-weight: normal;
        }
        .resend-link {
            color: var(--secondary);
            cursor: pointer;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .resend-link:hover {
            color: #C9A227;
        }
        .back-link {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 0.85rem;
        }
        .back-link:hover {
            color: rgba(255,255,255,0.8);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-logo">
                <img src="../assets/images/log.png" alt="Kizza Tours &amp; Safaris" height="50">
            </div>
            <div class="login-title">Admin Portal</div>

            <?php if ($error): ?>
                <div class="error-msg mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($otpSent): ?>
                <!-- STEP 2: OTP Verification -->
                <div class="success-msg mb-4">
                    <i class="fas fa-paper-plane me-2"></i>Verification code sent to<br>
                    <strong><?php echo htmlspecialchars($otpEmail); ?></strong>
                </div>

                <?php if ($otpError): ?>
                    <div class="error-msg mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($otpError); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="login_step" value="otp">
                    <input type="hidden" name="otp_email_display" value="<?php echo htmlspecialchars($otpEmail); ?>">
                    <div class="mb-3">
                        <label class="form-label">Enter 6-Digit Code</label>
                        <input type="text" class="form-control otp-input" name="otp_code" placeholder="000000"
                               maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code"
                               required autofocus>
                    </div>
                    <div class="mb-3 text-center">
                        <small style="color: rgba(255,255,255,0.4);">Code expires in 5 minutes</small>
                    </div>
                    <button type="submit" class="btn btn-login mb-3">
                        <i class="fas fa-check-circle me-2"></i> Verify & Login
                    </button>
                </form>

                <div class="text-center">
                    <form method="POST" style="display:inline;">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="login_step" value="resend">
                        <button type="submit" class="resend-link border-0 bg-transparent">
                            <i class="fas fa-redo me-1"></i>Resend Code
                        </button>
                    </form>
                    <span style="color: rgba(255,255,255,0.2); margin: 0 8px;">|</span>
                    <form method="POST" style="display:inline;">
                        <?php csrf_field(); ?>
                        <button type="submit" class="back-link border-0 bg-transparent" style="font-size:0.85rem;">
                            <i class="fas fa-arrow-left me-1"></i>Back to Login
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <!-- STEP 1: Credentials -->
                <form method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="login_step" value="credentials">
                    <div class="mb-3">
                        <label class="form-label">Username or Email</label>
                        <input type="text" class="form-control" name="username" placeholder="Enter username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                    </div>
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-lock me-2"></i> Sign In
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="../" class="back-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Website
                </a>
            </div>
        </div>
    </div>
</body>
</html>
