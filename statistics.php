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
$groupBy = $_GET['group_by'] ?? 'category';

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

$groupByOptions = [
    'category' => ['field' => "COALESCE(c.name, 'بدون دسته‌بندی')", 'label' => 'دسته‌بندی'],
    'type' => ['field' => 't.type', 'label' => 'نوع تراکنش'],
    'tag' => ['field' => "COALESCE(tg.name, 'بدون برچسب')", 'label' => 'برچسب'],
    'account' => ['field' => 'a.name', 'label' => 'حساب'],
    'month' => ['field' => "DATE_FORMAT(t.date, '%Y-%m')", 'label' => 'ماه'],
    'day' => ['field' => 't.date', 'label' => 'روز'],
];

$groupInfo = $groupByOptions[$groupBy] ?? $groupByOptions['category'];

$sql = "SELECT {$groupInfo['field']} as group_name,
    SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END) as total_income,
    SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expense,
    COUNT(*) as transaction_count
    FROM transactions t
    LEFT JOIN accounts a ON t.account_id = a.id
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN tags tg ON t.tag_id = tg.id
    $whereClause GROUP BY group_name ORDER BY total_expense DESC, total_income DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$stats = $stmt->fetchAll();

$totalIncome = array_sum(array_column($stats, 'total_income'));
$totalExpense = array_sum(array_column($stats, 'total_expense'));
$totalTransactions = array_sum(array_column($stats, 'transaction_count'));
$netAmount = $totalIncome - $totalExpense;

$maxAmount = max($totalIncome, $totalExpense, 1);

$typeLabels = ['income' => 'درآمد', 'expense' => 'هزینه'];

$jalaliFrom = formatJalali($dateFrom);
$jalaliTo = formatJalali($dateTo);
$pageTitle = 'آمار';
$activePage = 'statistics';
$includeDatepicker = true;
require_once __DIR__ . '/includes/header.php';
?>

        <!-- Filters -->
        <form method="GET" class="filter-card">
            <div class="filter-row">
                <div class="form-group">
                    <label for="jalali_from">از تاریخ</label>
                    <input type="text" id="jalali_from" class="pwt-datepicker-input" data-alt="date_from" readonly style="cursor:pointer;background:#fff;">
                    <input type="hidden" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div class="form-group">
                    <label for="jalali_to">تا تاریخ</label>
                    <input type="text" id="jalali_to" class="pwt-datepicker-input" data-alt="date_to" readonly style="cursor:pointer;background:#fff;">
                    <input type="hidden" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
            </div>
            <div class="filter-row">
                <div class="form-group">
                    <label for="type">نوع</label>
                    <select id="type" name="type">
                        <option value="">همه</option>
                        <option value="income" <?php echo $type === 'income' ? 'selected' : ''; ?>>درآمد</option>
                        <option value="expense" <?php echo $type === 'expense' ? 'selected' : ''; ?>>هزینه</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="account_id">حساب</label>
                    <select id="account_id" name="account_id">
                        <option value="">همه حساب‌ها</option>
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc['id']; ?>" <?php echo $accountId == $acc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($acc['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-row">
                <div class="form-group">
                    <label for="category_id">دسته‌بندی</label>
                    <select id="category_id" name="category_id">
                        <option value="">همه دسته‌بندی‌ها</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" data-cattype="<?php echo $cat['type']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tag_id">برچسب</label>
                    <select id="tag_id" name="tag_id">
                        <option value="">همه برچسب‌ها</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo $tag['id']; ?>" <?php echo $tagId == $tag['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-row">
                <div class="form-group">
                    <label for="group_by">گروه‌بندی بر اساس</label>
                    <select id="group_by" name="group_by">
                        <?php foreach ($groupByOptions as $key => $opt): ?>
                        <option value="<?php echo $key; ?>" <?php echo $groupBy === $key ? 'selected' : ''; ?>><?php echo $opt['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group-btn">
                    <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                    <a href="/pfm/statistics.php" class="btn">بازنشانی</a>
                </div>
            </div>
        </form>

        <!-- Summary Cards -->
        <div class="stats-grid stats-grid-4">
            <div class="stat-card stat-card--total">
                <div class="stat-icon">📊</div>
                <div>
                    <h3>کل تراکنش‌ها</h3>
                    <p class="stat-value"><?php echo toPersianDigits($totalTransactions); ?></p>
                </div>
            </div>
            <div class="stat-card stat-card--income">
                <div class="stat-icon">📈</div>
                <div>
                    <h3>مجموع درآمد</h3>
                    <p class="stat-value"><?php echo toPersianDigits(number_format($totalIncome, 0)); ?> <small>تومان</small></p>
                </div>
            </div>
            <div class="stat-card stat-card--expense">
                <div class="stat-icon">📉</div>
                <div>
                    <h3>مجموع هزینه</h3>
                    <p class="stat-value"><?php echo toPersianDigits(number_format($totalExpense, 0)); ?> <small>تومان</small></p>
                </div>
            </div>
            <div class="stat-card <?php echo $netAmount >= 0 ? 'stat-card--income' : 'stat-card--expense'; ?>">
                <div class="stat-icon"><?php echo $netAmount >= 0 ? '💰' : '⚠️'; ?></div>
                <div>
                    <h3>خالص</h3>
                    <p class="stat-value"><?php echo toPersianDigits(number_format(abs($netAmount), 0)); ?> <small>تومان</small></p>
                </div>
            </div>
        </div>

        <?php if (!empty($stats)): ?>
        <!-- Visual Bar Chart -->
        <div class="chart-section">
            <div class="section-header">
                <h2>مقایسه بر اساس <?php echo $groupInfo['label']; ?></h2>
            </div>
            <div class="bar-chart">
                <?php foreach (array_slice($stats, 0, 8) as $row):
                    $rowTotal = $row['total_income'] + $row['total_expense'];
                    $incomePct = $maxAmount > 0 ? ($row['total_income'] / $maxAmount) * 100 : 0;
                    $expensePct = $maxAmount > 0 ? ($row['total_expense'] / $maxAmount) * 100 : 0;
                    $net = $row['total_income'] - $row['total_expense'];
                ?>
                <div class="bar-row">
                    <div class="bar-label"><?php echo htmlspecialchars($row['group_name']); ?></div>
                    <div class="bar-track">
                        <?php if ($row['total_income'] > 0): ?>
                        <div class="bar-fill bar-fill--income" style="width: <?php echo $incomePct; ?>%"></div>
                        <?php endif; ?>
                        <?php if ($row['total_expense'] > 0): ?>
                        <div class="bar-fill bar-fill--expense" style="width: <?php echo $expensePct; ?>%"></div>
                        <?php endif; ?>
                    </div>
                    <div class="bar-value">
                        <span class="bar-amount <?php echo $net >= 0 ? 'text-income' : 'text-expense'; ?>">
                            <?php echo $net >= 0 ? '+' : ''; ?><?php echo toPersianDigits(number_format($net, 0)); ?>
                        </span>
                        <span class="bar-count"><?php echo toPersianDigits($row['transaction_count']); ?> تراکنش</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot legend-dot--income"></span> درآمد</span>
                <span class="legend-item"><span class="legend-dot legend-dot--expense"></span> هزینه</span>
            </div>
        </div>

        <!-- Data Table -->
        <div class="section">
            <div class="section-header">
                <h2>جزئیات</h2>
            </div>
            <div class="card-list">
                <?php foreach ($stats as $row):
                    $net = $row['total_income'] - $row['total_expense'];
                    $sharePct = $totalTransactions > 0 ? ($row['transaction_count'] / $totalTransactions) * 100 : 0;
                ?>
                <div class="card-item">
                    <div class="card-accent card-accent--<?php echo $net >= 0 ? 'income' : 'expense'; ?>"></div>
                    <div class="card-body">
                        <div class="card-fields">
                            <div class="card-field" style="flex:1;min-width:150px;">
                                <div class="card-field-label"><?php echo $groupInfo['label']; ?></div>
                                <div class="card-field-value"><strong><?php echo htmlspecialchars($row['group_name']); ?></strong></div>
                            </div>
                            <div class="card-field">
                                <div class="card-field-label">درآمد</div>
                                <div class="card-field-value card-amount card-amount--income"><?php echo $row['total_income'] > 0 ? toPersianDigits(number_format($row['total_income'], 0)) . ' تومان' : '-'; ?></div>
                            </div>
                            <div class="card-field">
                                <div class="card-field-label">هزینه</div>
                                <div class="card-field-value card-amount card-amount--expense"><?php echo $row['total_expense'] > 0 ? toPersianDigits(number_format($row['total_expense'], 0)) . ' تومان' : '-'; ?></div>
                            </div>
                            <div class="card-field">
                                <div class="card-field-label">خالص</div>
                                <div class="card-field-value card-amount <?php echo $net >= 0 ? 'card-amount--income' : 'card-amount--expense'; ?>">
                                    <?php echo $net >= 0 ? '+' : ''; ?><?php echo toPersianDigits(number_format($net, 0)); ?> تومان
                                </div>
                            </div>
                            <div class="card-field">
                                <div class="card-field-label">تراکنش‌ها</div>
                                <div class="card-field-value"><?php echo toPersianDigits($row['transaction_count']); ?></div>
                            </div>
                            <div class="card-field">
                                <div class="card-field-label">سهم</div>
                                <div class="card-field-value">
                                    <div class="share-bar">
                                        <div class="share-bar-fill" style="width: <?php echo $sharePct; ?>%"></div>
                                        <span><?php echo toPersianDigits(number_format($sharePct, 1)); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="card-total">
                    <div class="card-field">
                        <div class="card-field-label">مجموع درآمد</div>
                        <div class="card-field-value"><strong><?php echo toPersianDigits(number_format($totalIncome, 0)); ?> تومان</strong></div>
                    </div>
                    <div class="card-field">
                        <div class="card-field-label">مجموع هزینه</div>
                        <div class="card-field-value"><strong><?php echo toPersianDigits(number_format($totalExpense, 0)); ?> تومان</strong></div>
                    </div>
                    <div class="card-field">
                        <div class="card-field-label">خالص</div>
                        <div class="card-field-value"><strong><?php echo $netAmount >= 0 ? '+' : ''; ?><?php echo toPersianDigits(number_format($netAmount, 0)); ?> تومان</strong></div>
                    </div>
                    <div class="card-field">
                        <div class="card-field-label">تراکنش‌ها</div>
                        <div class="card-field-value"><strong><?php echo toPersianDigits($totalTransactions); ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h3>داده‌ای یافت نشد</h3>
            <p>تراکنشی برای بازه زمانی و فیلترهای انتخابی یافت نشد.</p>
        </div>
        <?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
