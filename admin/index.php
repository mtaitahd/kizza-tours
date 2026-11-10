<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin-auth.php';
session_start();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard');
    exit;
}

$siteKey = env('RECAPTCHA_SITE_KEY', '');
$secretKey = env('RECAPTCHA_SECRET_KEY', '');
$configured = $siteKey !== '' && $secretKey !== '' && function_exists('curl_init');
$error = $configured ? '' : 'Admin sign-in is unavailable until reCAPTCHA is configured.';

function verifyAdminCaptcha(string $token, string $secret): bool {
    $request = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $token]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $response = curl_exec($request);
    $result = is_string($response) ? json_decode($response, true) : null;
    return ($result['success'] ?? false) === true
        && ($result['hostname'] ?? '') === parse_url(SITE_URL, PHP_URL_HOST);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($configured) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $captcha = $_POST['g-recaptcha-response'] ?? '';

        try {
            if (!is_string($username) || !is_string($password) || trim($username) === '' || $password === '') {
                $error = 'Please enter username and password.';
            } elseif (!is_string($captcha) || $captcha === '' || !verifyAdminCaptcha($captcha, $secretKey)) {
                $error = 'Please complete the reCAPTCHA check and try again.';
            } else {
                $username = trim($username);
                $db = db();
                $admin = $db->fetchOne(
                    'SELECT * FROM admin_users WHERE username = ? OR email = ? LIMIT 1',
                    [$username, $username]
                );

                if (!$admin || (isset($admin['is_active']) && (int)$admin['is_active'] !== 1) || !password_verify($password, $admin['password'])) {
                    $error = 'Invalid username or password.';
                } else {
                    $db->query('UPDATE admin_users SET last_login = NOW() WHERE id = ?', [$admin['id']]);
                    session_regenerate_id(true);
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_image'] = $admin['profile_image'] ?? null;
                    $_SESSION['admin_auth_version'] = (int)($admin['session_version'] ?? 0);
                    adminLogActivity('login', 'login');

                    header('Location: dashboard');
                    exit;
                }
            }
        } catch (Throwable $e) {
            error_log('Admin login error: ' . $e->getMessage());
            $error = 'An error occurred. Please try again.';
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
        .login-logo { text-align: center; margin-bottom: 0.5rem; }
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
        .back-link {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 0.85rem;
        }
        .back-link:hover { color: rgba(255,255,255,0.8); }
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

            <?php if ($configured): ?>
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Username or Email</label>
                        <input type="text" class="form-control" name="username" placeholder="Enter username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Enter password" required>
                    </div>
                    <div class="g-recaptcha mb-3" data-sitekey="<?php echo htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
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
    <?php if ($configured): ?>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
</body>
</html>
