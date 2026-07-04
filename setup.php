<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    header('Location: /pfm/dashboard.php');
    exit;
}

$error = '';
$success = '';

try {
    $db = getDB();
    $count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $error = 'پایگاه داده راه‌اندازی نشده است. ابتدا install.php را اجرا کنید.';
    $count = -1;
}

if ($count > 0 && empty($success)) {
    $error = 'حساب قبلاً ایجاد شده است. لطفاً وارد شوید.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($username) || strlen($username) < 3) {
        $error = 'نام کاربری باید حداقل ۳ کاراکتر باشد.';
    } elseif (strlen($password) < 8) {
        $error = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    } elseif ($password !== $confirm) {
        $error = 'رمزهای عبور مطابقت ندارند.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$username, $hash]);
            $success = 'حساب ایجاد شد! در حال انتقال به صفحه ورود...';
            header('Refresh: 2; url=/pfm/login.php');
        } catch (Exception $e) {
            $error = 'نام کاربری قبلاً استفاده شده است.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ایجاد حساب - مدیریت مالی شخصی</title>
    <link rel="stylesheet" href="/pfm/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <h1>ایجاد حساب کاربری</h1>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error && !$success): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($count === 0 || !empty($success)): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">نام کاربری</label>
                <input type="text" id="username" name="username" required minlength="3" autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">رمز عبور</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="confirm_password">تأیید رمز عبور</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">ایجاد حساب</button>
        </form>
        <?php endif; ?>
        <p class="setup-link"><a href="/pfm/login.php">بازگشت به ورود</a></p>
    </div>
</body>
</html>
