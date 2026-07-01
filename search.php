<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jalali.php';
requireLogin();

$db = getDB();
$error = '';

$accounts = $db->query("SELECT id, name FROM accounts ORDER BY name")->fetchAll();
$categories = $db->query("SELECT id, name, parent_category_id, type FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();
$tags = $db->query("SELECT id, name FROM tags ORDER BY name")->fetchAll();

$where = [];
$params = [];

if (!empty($_GET['search'])) {
    $where[] = "(t.description LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
}
if (!empty($_GET['type']) && in_array($_GET['type'], ['income', 'expense'])) {
    $where[] = "t.type = ?";
    $params[] = $_GET['type'];
}
if (!empty($_GET['date_from'])) {
    $where[] = "t.date >= ?";
    $params[] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where[] = "t.date <= ?";
    $params[] = $_GET['date_to'];
}
if (!empty($_GET['amount_min'])) {
    $where[] = "t.amount >= ?";
    $params[] = floatval($_GET['amount_min']);
}
if (!empty($_GET['amount_max'])) {
    $where[] = "t.amount <= ?";
    $params[] = floatval($_GET['amount_max']);
}
if (!empty($_GET['account_id'])) {
    $where[] = "t.account_id = ?";
    $params[] = intval($_GET['account_id']);
}
if (!empty($_GET['category_id'])) {
    $where[] = "t.category_id = ?";
    $params[] = intval($_GET['category_id']);
}
if (!empty($_GET['tag_id'])) {
    $where[] = "t.tag_id = ?";
    $params[] = intval($_GET['tag_id']);
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$validSorts = ['date', 'amount', 'type', 'category', 'account'];
$sortBy = in_array($_GET['sort'] ?? '', $validSorts) ? $_GET['sort'] : 'date';
$sortDir = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$sortMap = [
    'date' => 't.date', 'amount' => 't.amount', 'type' => 't.type',
    'category' => 'c.name', 'account' => 'a.name',
];

$orderBy = $sortMap[$sortBy] . ' ' . $sortDir;
if ($sortBy !== 'date') $orderBy .= ', t.date DESC, t.time DESC';

$sql = "SELECT t.*, a.name as account_name, c.name as category_name, tg.name as tag_name
    FROM transactions t
    LEFT JOIN accounts a ON t.account_id = a.id
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN tags tg ON t.tag_id = tg.id
    $whereClause ORDER BY $orderBy";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$totalIncome = 0;
$totalExpense = 0;
foreach ($transactions as $t) {
    if ($t['type'] === 'income') $totalIncome += $t['amount'];
    else $totalExpense += $t['amount'];
}

function currentSort($field, $currentSort, $currentDir) {
    return ($field === $currentSort) ? ($currentDir === 'asc' ? 'desc' : 'asc') : 'asc';
}
function sortIcon($field, $currentSort, $currentDir) {
    if ($field !== $currentSort) return ' ↕';
    return $currentDir === 'asc' ? ' ↑' : ' ↓';
}

$typeLabels = ['income' => 'درآمد', 'expense' => 'هزینه'];
$sortLabels = ['date' => 'تاریخ', 'amount' => 'مبلغ', 'type' => 'نوع', 'category' => 'دسته‌بندی', 'account' => 'حساب'];

// Jalali defaults for filter inputs
$jalaliFrom = !empty($_GET['date_from']) ? formatJalali($_GET['date_from']) : '';
$jalaliTo = !empty($_GET['date_to']) ? formatJalali($_GET['date_to']) : '';
$pageTitle = 'جستجو و فیلتر';
$activePage = 'search';
$includeDatepicker = true;
require_once __DIR__ . '/includes/header.php';
?>

        <form method="GET" class="filter-card" id="filterForm">
            <div class="filter-row">
                <div class="form-group">
                    <label for="search">جستجوی توضیحات</label>
                    <input type="text" id="search" name="search" placeholder="جستجو..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="type">نوع</label>
                    <select id="type" name="type">
                        <option value="">همه</option>
                        <option value="income" <?php echo ($_GET['type'] ?? '') === 'income' ? 'selected' : ''; ?>>درآمد</option>
                        <option value="expense" <?php echo ($_GET['type'] ?? '') === 'expense' ? 'selected' : ''; ?>>هزینه</option>
                    </select>
                </div>
            </div>

            <div class="filter-row">
                <div class="form-group">
                    <label for="jalali_from">از تاریخ</label>
                    <input type="text" id="jalali_from" class="pwt-datepicker-input" data-alt="date_from" readonly style="cursor:pointer;background:#fff;">
                    <input type="hidden" id="date_from" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="jalali_to">تا تاریخ</label>
                    <input type="text" id="jalali_to" class="pwt-datepicker-input" data-alt="date_to" readonly style="cursor:pointer;background:#fff;">
                    <input type="hidden" id="date_to" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                </div>
            </div>

            <div class="filter-row">
                <div class="form-group">
                    <label for="amount_min">کمترین مبلغ</label>
                    <input type="number" id="amount_min" name="amount_min" step="1" min="0" value="<?php echo htmlspecialchars($_GET['amount_min'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="amount_max">بیشترین مبلغ</label>
                    <input type="number" id="amount_max" name="amount_max" step="1" min="0" value="<?php echo htmlspecialchars($_GET['amount_max'] ?? ''); ?>">
                </div>
            </div>

            <div class="filter-row">
                <div class="form-group">
                    <label for="account_id">حساب</label>
                    <select id="account_id" name="account_id">
                        <option value="">همه حساب‌ها</option>
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc['id']; ?>" <?php echo ($_GET['account_id'] ?? '') == $acc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($acc['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="category_id">دسته‌بندی</label>
                    <select id="category_id" name="category_id">
                        <option value="">همه دسته‌بندی‌ها</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" data-cattype="<?php echo $cat['type']; ?>" <?php echo ($_GET['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-row">
                <div class="form-group">
                    <label for="tag_id">برچسب</label>
                    <select id="tag_id" name="tag_id">
                        <option value="">همه برچسب‌ها</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo $tag['id']; ?>" <?php echo ($_GET['tag_id'] ?? '') == $tag['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sort">مرتب‌سازی</label>
                    <select id="sort" name="sort">
                        <?php foreach ($validSorts as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo ($sortBy ?? '') === $s ? 'selected' : ''; ?>><?php echo $sortLabels[$s]; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">اعمال فیلترها</button>
                <a href="/pfm/search.php" class="btn">پاک کردن همه</a>
            </div>
        </form>

        <div class="filter-summary">
            <span>یافت شد: <strong><?php echo toPersianDigits(count($transactions)); ?></strong> تراکنش</span>
            <span class="text-income">درآمد: <?php echo toPersianDigits(number_format($totalIncome, 0)); ?> تومان</span>
            <span class="text-expense">هزینه: <?php echo toPersianDigits(number_format($totalExpense, 0)); ?> تومان</span>
            <span>خالص: <strong class="<?php ($totalIncome - $totalExpense) >= 0 ? 'text-income' : 'text-expense'; ?>">
                <?php echo toPersianDigits(number_format($totalIncome - $totalExpense, 0)); ?> تومان
            </strong></span>
        </div>

        <?php if (!empty($transactions)): ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'date', 'dir' => currentSort('date', $sortBy, $sortDir)])); ?>">تاریخ<?php echo sortIcon('date', $sortBy, $sortDir); ?></a></th>
                    <th>زمان</th>
                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'type', 'dir' => currentSort('type', $sortBy, $sortDir)])); ?>">نوع<?php echo sortIcon('type', $sortBy, $sortDir); ?></a></th>
                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'category', 'dir' => currentSort('category', $sortBy, $sortDir)])); ?>">دسته‌بندی<?php echo sortIcon('category', $sortBy, $sortDir); ?></a></th>
                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'account', 'dir' => currentSort('account', $sortBy, $sortDir)])); ?>">حساب<?php echo sortIcon('account', $sortBy, $sortDir); ?></a></th>
                    <th>برچسب</th>
                    <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'amount', 'dir' => currentSort('amount', $sortBy, $sortDir)])); ?>">مبلغ<?php echo sortIcon('amount', $sortBy, $sortDir); ?></a></th>
                    <th>توضیحات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $row): ?>
                <tr>
                    <td><?php echo toPersianDigits(formatJalali($row['date'])); ?></td>
                    <td><?php echo htmlspecialchars($row['time']); ?></td>
                    <td><span class="badge badge-<?php echo $row['type']; ?>"><?php echo $typeLabels[$row['type']] ?? $row['type']; ?></span></td>
                    <td><?php echo htmlspecialchars($row['category_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($row['account_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['tag_name'] ?? '-'); ?></td>
                    <td class="<?php echo $row['type'] === 'income' ? 'text-income' : 'text-expense'; ?>">
                        <?php echo $row['type'] === 'income' ? '+' : '-'; ?><?php echo toPersianDigits(number_format($row['amount'], 0)); ?> تومان
                    </td>
                    <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <p class="text-muted">تراکنشی با فیلترهای انتخابی یافت نشد.</p>
        <?php endif; ?>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
