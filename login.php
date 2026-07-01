<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jalali.php';

if (isLoggedIn()) {
    header('Location: /pfm/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: /pfm/dashboard.php');
                exit;
            } else {
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
            <button type="submit" class="btn btn-primary btn-block">ورود</button>
        </form>
        <p class="setup-link">اولین بار است؟ <a href="/pfm/setup.php">ایجاد حساب</a></p>
    </div>
</body>
</html>
