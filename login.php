<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jalali.php';

if (isLoggedIn()) {
    header('Location: /pfm/dashboard.php');
    exit;
}

$error = '';
$locked = isLoginLocked();

if ($locked) {
    $remaining = getRemainingLockoutSeconds();
    $minutes = ceil($remaining / 60);
    $error = "تعداد تلاش‌های ناموفق بیش از حد مجاز است. لطفاً {$minutes} دقیقه صبر کنید.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'لطفاً نام کاربری و رمز عبور را وارد کنید.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                clearLoginAttempts();
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                if (!empty($_POST['remember'])) {
                    setRememberCookie($user['id']);
                }
                header('Location: /pfm/dashboard.php');
                exit;
            } else {
                recordFailedLogin();
                $error = 'نام کاربری یا رمز عبور اشتباه است.';
            }
        } catch (Exception $e) {
            $error = 'خطا در ورود. لطفاً دوباره تلاش کنید.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود - مدیریت مالی شخصی</title>
    <link rel="stylesheet" href="/pfm/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h1>مدیریت مالی شخصی</h1>
        <?php if( $locked ) { ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php } else { ?>
            <form method="POST" action="">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <div class="form-group">
                    <label for="username">نام کاربری</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">رمز عبور</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="checkbox" id="remember" name="remember" value="1" style="width:auto;">
                    <label for="remember" style="margin-bottom:0;font-size:0.875rem;">مرا به خاطر بسپار</label>
                </div>
                <button type="submit" class="btn btn-primary btn-block"<?php if ($locked): ?> disabled<?php endif; ?>>ورود</button>
            </form>
            <p class="setup-link">اولین بار است؟ <a href="/pfm/setup.php">ایجاد حساب</a></p>
        <?php } ?>

    </div>
</body>
</html>
