<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jalali.php';
requireLogin();

$db = getDB();

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$type = $_GET['type'] ?? '';
$accountId = intval($_GET['account_id'] ?? 0) ?: null;
$categoryId = intval($_GET['category_id'] ?? 0) ?: null;
$tagId = intval($_GET['tag_id'] ?? 0) ?: null;

$accounts = $db->query("SELECT id, name FROM accounts ORDER BY name")->fetchAll();
$categories = $db->query("SELECT id, name, type FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();
$tags = $db->query("SELECT id, name FROM tags ORDER BY name")->fetchAll();

$where = ["t.date >= ?", "t.date <= ?"];
$params = [$dateFrom, $dateTo];

if ($type && in_array($type, ['income', 'expense'])) { $where[] = "t.type = ?"; $params[] = $type; }
if ($accountId) { $where[] = "t.account_id = ?"; $params[] = $accountId; }
if ($categoryId) { $where[] = "t.category_id = ?"; $params[] = $categoryId; }
if ($tagId) { $where[] = "t.tag_id = ?"; $params[] = $tagId; }

$whereClause = 'WHERE ' . implode(' AND ', $where);

$catSql = "SELECT COALESCE(c.name, 'بدون دسته‌بندی') as name,
    SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as expense,
    SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as income
    FROM transactions t LEFT JOIN categories c ON t.category_id = c.id
    $whereClause GROUP BY name ORDER BY expense DESC";
$stmt = $db->prepare($catSql); $stmt->execute($params); $categoryData = $stmt->fetchAll();

$monthlySql = "SELECT DATE_FORMAT(t.date, '%Y-%m') as month,
    SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as income,
    SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as expense
    FROM transactions t $whereClause GROUP BY month ORDER BY month ASC";
$stmt = $db->prepare($monthlySql); $stmt->execute($params); $monthlyData = $stmt->fetchAll();

foreach ($monthlyData as &$m) {
    $parts = explode('-', $m['month']);
    if (count($parts) === 2) {
        list($jy, $jm) = gregorianToJalali(intval($parts[0]), intval($parts[1]), 15);
        $m['month_fa'] = toPersianDigits($jy . '/' . str_pad($jm, 2, '0', STR_PAD_LEFT));
    }
}
unset($m);

$accountSql = "SELECT a.name as name,
    SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as expense,
    SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as income
    FROM transactions t LEFT JOIN accounts a ON t.account_id = a.id
    $whereClause GROUP BY a.name ORDER BY expense DESC";
$stmt = $db->prepare($accountSql); $stmt->execute($params); $accountData = $stmt->fetchAll();

$tagSql = "SELECT COALESCE(tg.name, 'بدون برچسب') as name,
    SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as expense,
    SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as income
    FROM transactions t LEFT JOIN tags tg ON t.tag_id = tg.id
    $whereClause GROUP BY name ORDER BY expense DESC";
$stmt = $db->prepare($tagSql); $stmt->execute($params); $tagData = $stmt->fetchAll();

$avgSql = "SELECT
    ROUND(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) / NULLIF(DATEDIFF(?, ?), 0), 0) as daily_avg,
    ROUND(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) / NULLIF(DATEDIFF(?, ?) / 7, 0), 0) as weekly_avg,
    ROUND(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) / NULLIF(TIMESTAMPDIFF(MONTH, ?, ?) + 1, 0), 0) as monthly_avg
    FROM transactions t $whereClause AND t.type = 'expense'";
$stmt = $db->prepare($avgSql);
$stmt->execute(array_merge([$dateTo, $dateFrom, $dateTo, $dateFrom, $dateFrom, $dateTo], $params));
$avgData = $stmt->fetch();

// Summary stats
$totalIncome = array_sum(array_column($categoryData, 'income'));
$totalExpense = array_sum(array_column($categoryData, 'expense'));
$netAmount = $totalIncome - $totalExpense;

$categoryJson = json_encode($categoryData);
$monthlyJson = json_encode($monthlyData);
$accountJson = json_encode($accountData);
$tagJson = json_encode($tagData);

$pageTitle = 'تحلیل';
$activePage = 'analytics';
$includeDatepicker = true;
$includeChart = true;

$extraCss[] = '/pfm/css/analytics.css';
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Summary Stats -->
        <div class="stats-row">
            <div class="stats-item">
                <div class="stats-icon stats-icon--success">📈</div>
                <div class="stats-info">
                    <h3>درآمد</h3>
                    <div class="stat-value"><?php echo toPersianDigits(number_format($totalIncome, 0)); ?> <small style="font-size:.7em;font-weight:400;opacity:.6">تومان</small></div>
                </div>
            </div>
            <div class="stats-item">
                <div class="stats-icon stats-icon--danger">📉</div>
                <div class="stats-info">
                    <h3>هزینه</h3>
                    <div class="stat-value"><?php echo toPersianDigits(number_format($totalExpense, 0)); ?> <small style="font-size:.7em;font-weight:400;opacity:.6">تومان</small></div>
                </div>
            </div>
            <div class="stats-item">
                <div class="stats-icon <?php echo $netAmount >= 0 ? 'stats-icon--success' : 'stats-icon--danger'; ?>">💰</div>
                <div class="stats-info">
                    <h3>خالص</h3>
                    <div class="stat-value"><?php echo $netAmount >= 0 ? '+' : ''; ?><?php echo toPersianDigits(number_format(abs($netAmount), 0)); ?> <small style="font-size:.7em;font-weight:400;opacity:.6">تومان</small></div>
                </div>
            </div>
            <div class="stats-item">
                <div class="stats-icon stats-icon--primary">📊</div>
                <div class="stats-info">
                    <h3>میانگین روزانه</h3>
                    <div class="stat-value"><?php echo toPersianDigits(number_format($avgData['daily_avg'] ?? 0, 0)); ?> <small style="font-size:.7em;font-weight:400;opacity:.6">تومان</small></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="analytics-filters">
            <div class="form-group">
                <label>از تاریخ</label>
                <input type="text" class="pwt-datepicker-input" data-alt="date_from" readonly style="cursor:pointer;background:#fff;width:100%;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-family:'Vazir',sans-serif;font-size:.9rem">
                <input type="hidden" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
            </div>
            <div class="form-group">
                <label>تا تاریخ</label>
                <input type="text" class="pwt-datepicker-input" data-alt="date_to" readonly style="cursor:pointer;background:#fff;width:100%;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-family:'Vazir',sans-serif;font-size:.9rem">
                <input type="hidden" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
            </div>
            <div class="form-group">
                <label>نوع</label>
                <select name="type" style="width:100%;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-family:'Vazir',sans-serif;font-size:.9rem;background:#fff">
                    <option value="">همه</option>
                    <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>درآمد</option>
                    <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>هزینه</option>
                </select>
            </div>
            <div class="form-group">
                <label>حساب</label>
                <select name="account_id" style="width:100%;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-family:'Vazir',sans-serif;font-size:.9rem;background:#fff">
                    <option value="">همه</option>
                    <?php foreach ($accounts as $acc): ?>
                    <option value="<?php echo $acc['id']; ?>" <?php echo $accountId == $acc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($acc['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>دسته‌بندی</label>
                <select name="category_id" id="category_id" style="width:100%;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:8px;font-family:'Vazir',sans-serif;font-size:.9rem;background:#fff">
                    <option value="">همه</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" data-cattype="<?php echo $cat['type']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="width:100%">اعمال</button>
            </div>
            <div class="form-group">
                <a href="/pfm/analytics.php" class="btn" style="width:100%">بازنشانی</a>
            </div>
        </form>

        <?php if (!empty($categoryData) || !empty($monthlyData)): ?>
        <!-- Charts -->
        <div class="chart-grid">
            <!-- Monthly Trend -->
            <div class="chart-card chart-full">
                <div class="chart-card-header">
                    <h3>📈 روند درآمد و هزینه</h3>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container" style="height:320px"><canvas id="monthlyChart"></canvas></div>
                </div>
            </div>

            <!-- Category Donut -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>📊 هزینه بر اساس دسته‌بندی</h3>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container"><canvas id="categoryChart"></canvas></div>
                </div>
            </div>

            <!-- Account Bar -->
            <div class="chart-card">
                <div class="chart-card-header">
                    <h3>🏦 بر اساس حساب</h3>
                </div>
                <div class="chart-card-body">
                    <div class="chart-container"><canvas id="accountChart"></canvas></div>
                </div>
            </div>

            <!-- Tag Pie -->
            <div class="chart-card chart-full">
                <div class="chart-card-header">
                    <h3>🏷️ بر اساس برچسب</h3>
                </div>
                <div class="chart-card-body" style="display:flex;justify-content:center">
                    <div class="chart-container" style="max-width:500px;width:100%"><canvas id="tagChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Average Expenses -->
        <div class="avg-section">
            <div class="section-title">
                <h2>میانگین هزینه‌ها</h2>
                <span style="font-size:.8rem;color:#94a3b8">بر اساس تراکنش‌های هزینه</span>
            </div>
            <div class="avg-grid">
                <div class="avg-card">
                    <div class="avg-card-icon">📅</div>
                    <h4>روزانه</h4>
                    <div class="value"><?php echo toPersianDigits(number_format($avgData['daily_avg'] ?? 0, 0)); ?> <small style="font-size:.65em;font-weight:400;color:#94a3b8">تومان</small></div>
                    <small>میانگین هر روز</small>
                </div>
                <div class="avg-card">
                    <div class="avg-card-icon">📆</div>
                    <h4>هفتگی</h4>
                    <div class="value"><?php echo toPersianDigits(number_format($avgData['weekly_avg'] ?? 0, 0)); ?> <small style="font-size:.65em;font-weight:400;color:#94a3b8">تومان</small></div>
                    <small>میانگین هر هفته</small>
                </div>
                <div class="avg-card">
                    <div class="avg-card-icon">🗓️</div>
                    <h4>ماهانه</h4>
                    <div class="value"><?php echo toPersianDigits(number_format($avgData['monthly_avg'] ?? 0, 0)); ?> <small style="font-size:.65em;font-weight:400;color:#94a3b8">تومان</small></div>
                    <small>میانگین هر ماه</small>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-analytics">
            <div class="empty-icon">📊</div>
            <h3>داده‌ای یافت نشد</h3>
            <p>تراکنشی برای بازه زمانی انتخابی وجود ندارد.</p>
        </div>
        <?php endif; ?>

<?php
$extraScripts[] = '<script>var analyticsData={category:' . $categoryJson . ',monthly:' . $monthlyJson . ',account:' . $accountJson . ',tag:' . $tagJson . '}</script>';
$extraScripts[] = '/pfm/js/analytics.js';
require_once __DIR__ . '/includes/footer.php';
?>
