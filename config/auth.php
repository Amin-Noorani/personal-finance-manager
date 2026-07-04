<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
session_start();

define('CSRF_TOKEN_NAME', 'csrf_token');
define('REMEMBER_COOKIE', 'pfm_remember');
define('REMEMBER_DAYS', 30);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

function getClientIp() {
    return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isLoginLocked() {
    $ip = getClientIp();
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
        $stmt->execute([$ip, LOCKOUT_MINUTES]);
        $row = $stmt->fetch();
        return $row && intval($row['cnt']) >= MAX_LOGIN_ATTEMPTS;
    } catch (Exception $e) {
        return false;
    }
}

function getRemainingLockoutSeconds() {
    $ip = getClientIp();
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT attempted_at FROM login_attempts WHERE ip_address = ? ORDER BY attempted_at DESC LIMIT 1 OFFSET " . (MAX_LOGIN_ATTEMPTS - 1));
        $stmt->execute([$ip]);
        $row = $stmt->fetch();
        if (!$row) return 0;
        $lastAttempt = strtotime($row['attempted_at']);
        $unlockAt = $lastAttempt + (LOCKOUT_MINUTES * 60);
        $remaining = $unlockAt - time();
        return $remaining > 0 ? $remaining : 0;
    } catch (Exception $e) {
        return 0;
    }
}

function recordFailedLogin() {
    $ip = getClientIp();
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
        $stmt->execute([$ip]);
        // Cleanup old records older than lockout period
        $db->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)")->execute([LOCKOUT_MINUTES]);
    } catch (Exception $e) {
        // ignore
    }
}

function clearLoginAttempts() {
    $ip = getClientIp();
    try {
        $db = getDB();
        $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
    } catch (Exception $e) {
        // ignore
    }
}

function isLoggedIn() {
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        return true;
    }
    return autoLoginFromCookie();
}

function autoLoginFromCookie() {
    if (empty($_COOKIE[REMEMBER_COOKIE])) return false;

    $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
    if (count($parts) !== 2) return false;

    $userId = intval($parts[0]);
    $token = $parts[1];
    if ($userId <= 0 || empty($token)) return false;

    try {
        $db = getDB();
        $tokenHash = hash('sha256', $token);
        $stmt = $db->prepare("SELECT rt.id, u.id as user_id, u.username FROM remember_tokens rt JOIN users u ON rt.user_id = u.id WHERE rt.user_id = ? AND rt.token_hash = ? AND rt.expires_at > NOW()");
        $stmt->execute([$userId, $tokenHash]);
        $row = $stmt->fetch();

        if ($row) {
            // Rotate token: delete old, issue new
            $db->prepare("DELETE FROM remember_tokens WHERE id = ?")->execute([$row['id']]);
            setRememberCookie($row['user_id']);

            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            session_regenerate_id(true);
            return true;
        } else {
            // Invalid token — clear cookie
            clearRememberCookie();
        }
    } catch (Exception $e) {
        // ignore
    }
    return false;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /pfm/login.php');
        exit;
    }
}

function setRememberCookie($userId) {
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + (REMEMBER_DAYS * 24 * 60 * 60));

    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $tokenHash, $expires]);
    } catch (Exception $e) {
        // ignore
    }

    setcookie(REMEMBER_COOKIE, $userId . ':' . $token, [
        'expires' => time() + (REMEMBER_DAYS * 24 * 60 * 60),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearRememberCookie() {
    if (isset($_COOKIE[REMEMBER_COOKIE])) {
        $parts = explode(':', $_COOKIE[REMEMBER_COOKIE], 2);
        if (count($parts) === 2) {
            $userId = intval($parts[0]);
            $tokenHash = hash('sha256', $parts[1]);
            try {
                $db = getDB();
                $db->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND token_hash = ?")->execute([$userId, $tokenHash]);
            } catch (Exception $e) {}
        }
        setcookie(REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
