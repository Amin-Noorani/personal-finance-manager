<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        initial_balance DECIMAL(12,2) DEFAULT 0.00,
        current_balance DECIMAL(12,2) DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        parent_category_id INT DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        type ENUM('income', 'expense', 'both') DEFAULT 'both',
        FOREIGN KEY (parent_category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS tags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) UNIQUE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('income', 'expense') NOT NULL,
        date DATE NOT NULL,
        time TIME NOT NULL,
        account_id INT NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        category_id INT DEFAULT NULL,
        tag_id INT DEFAULT NULL,
        description VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE SET NULL,
        INDEX idx_date (date),
        INDEX idx_type (type),
        INDEX idx_account (account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip (ip_address),
        INDEX idx_time (attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaultCategories = [
        // Income categories
        ['حقوق', null, 'income'],
        ['آزاد', null, 'income'],
        ['سرمایه‌گذاری', null, 'income'],
        // Expense categories
        ['غذا و خوراکی', null, 'expense'],
        ['حمل و نقل', null, 'expense'],
        ['خرید', null, 'expense'],
        ['قبض و خدمات', null, 'expense'],
        ['سرگرمی', null, 'expense'],
        ['سلامت', null, 'expense'],
        // Both
        ['سایر', null, 'both'],
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO categories (name, parent_category_id, type) VALUES (?, ?, ?)");
    foreach ($defaultCategories as $cat) {
        $stmt->execute([$cat[0], $cat[1], $cat[2]]);
    }

    // Add type column if missing (upgrade from old schema)
    try { $db->exec("ALTER TABLE categories ADD COLUMN type ENUM('income', 'expense', 'both') DEFAULT 'both' AFTER is_active"); } catch (Exception $e) {}

    echo "<h2>پایگاه داده با موفقیت راه‌اندازی شد!</h2>";
    echo "<p><a href='/pfm/login.php'>رفتن به ورود</a></p>";
} catch (Exception $e) {
    echo "<h2>خطا در راه‌اندازی پایگاه داده</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
