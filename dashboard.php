<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jalali.php';
requireLogin();

$db = getDB();
$currentUser = date('Y-m');

$stmt = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM accounts");
$totalBalance = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type='income' AND DATE_FORMAT(date, '%Y-%m') = ?");
$stmt->execute([$currentUser]);
$currentMonthIncome = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type='expense' AND DATE_FORMAT(date, '%Y-%m') = ?");
$stmt->execute([$currentUser]);
$currentMonthExpense = $stmt->fetchColumn();

$stmt = $db->query("
    SELECT t.*, a.name as account_name, c.name as category_name, tg.name as tag_name
    FROM transactions t
    LEFT JOIN accounts a ON t.account_id = a.id
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN tags tg ON t.tag_id = tg.id
    ORDER BY t.date DESC, t.time DESC
    LIMIT 10
");
$recentTransactions = $stmt->fetchAll();

$typeLabels = ['income' => 'درآمد', 'expense' => 'هزینه'];

$pageTitle = 'داشبورد';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

        <h1>داشبورد</h1>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>مانده کل</h3>
                <p class="stat-value"><?php echo toPersianDigits(number_format($totalBalance, 0)); ?> تومان</p>
            </div>
            <div class="stat-card income">
                <h3>درآمد ماه</h3>
                <p class="stat-value"><?php echo toPersianDigits(number_format($currentMonthIncome, 0)); ?> تومان</p>
            </div>
            <div class="stat-card expense">
                <h3>هزینه ماه</h3>
                <p class="stat-value"><?php echo toPersianDigits(number_format($currentMonthExpense, 0)); ?> تومان</p>
            </div>
        </div>

        <div class="section">
            <h2>آخرین تراکنش‌ها</h2>
            <?php if (!empty($recentTransactions)): ?>
            <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>نوع</th>
                        <th>دسته‌بندی</th>
                        <th>حساب</th>
                        <th>مبلغ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentTransactions as $row): ?>
                    <tr>
                        <td><?php echo toPersianDigits(formatJalali($row['date'])); ?></td>
                        <td><span class="badge badge-<?php echo $row['type']; ?>"><?php echo $typeLabels[$row['type']] ?? $row['type']; ?></span></td>
                        <td><?php echo htmlspecialchars($row['category_name'] ?? 'بدون دسته‌بندی'); ?></td>
                        <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                        <td class="<?php echo $row['type'] === 'income' ? 'text-income' : 'text-expense'; ?>">
                            <?php echo $row['type'] === 'income' ? '+' : '-'; ?><?php echo toPersianDigits(number_format($row['amount'], 0)); ?> تومان
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
            <p class="text-muted">هنوز تراکنشی ثبت نشده است. <a href="/pfm/transactions.php?action=add">اولین تراکنش خود را اضافه کنید</a></p>
            <?php endif; ?>
        </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
