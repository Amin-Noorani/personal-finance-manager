<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jalali.php';
requireLogin();

$db = getDB();
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'add' || $postAction === 'edit') {
            $name = trim($_POST['name'] ?? '');
            $initialBalance = floatval($_POST['initial_balance'] ?? 0);
            $accountId = intval($_POST['account_id'] ?? 0);

            if (empty($name)) {
                $error = 'نام حساب الزامی است.';
            } else {
                try {
                    if ($postAction === 'edit' && $accountId > 0) {
                        $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
                        $stmt->execute([$accountId]);
                        $old = $stmt->fetch();
                        if (!$old) throw new Exception('حساب یافت نشد.');
                        $balanceDiff = $initialBalance - $old['initial_balance'];
                        $newCurrent = $old['current_balance'] + $balanceDiff;
                        $stmt = $db->prepare("UPDATE accounts SET name = ?, initial_balance = ?, current_balance = ? WHERE id = ?");
                        $stmt->execute([$name, $initialBalance, $newCurrent, $accountId]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO accounts (name, initial_balance, current_balance) VALUES (?, ?, ?)");
                        $stmt->execute([$name, $initialBalance, $initialBalance]);
                    }
                    $success = 'حساب با موفقیت ذخیره شد.';
                    $action = 'list';
                } catch (Exception $e) {
                    $error = 'خطا در ذخیره حساب: ' . $e->getMessage();
                }
            }
        } elseif ($postAction === 'delete') {
            $accountId = intval($_POST['account_id'] ?? 0);
            if ($accountId > 0) {
                $stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE account_id = ?");
                $stmt->execute([$accountId]);
                $txnCount = $stmt->fetchColumn();
                if ($txnCount > 0) {
                    $error = 'امکان حذف حساب با تراکنش وجود ندارد.';
                } else {
                    $stmt = $db->prepare("DELETE FROM accounts WHERE id = ?");
                    $stmt->execute([$accountId]);
                    $success = 'حساب حذف شد.';
                    $action = 'list';
                }
            }
        }
    }
}

$accounts = [];
if ($action === 'list') {
    $accounts = $db->query("SELECT * FROM accounts ORDER BY name")->fetchAll();
}

$editData = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([intval($id)]);
    $editData = $stmt->fetch();
    if (!$editData) { $action = 'list'; $error = 'حساب یافت نشد.'; }
}

$pageTitle = 'حساب‌ها';
$activePage = 'accounts';
require_once __DIR__ . '/includes/header.php';
?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($action === 'list'): ?>
        <div class="page-header">
            <h1>حساب‌ها</h1>
            <a href="/pfm/accounts.php?action=add" class="btn btn-primary">+ افزودن حساب</a>
        </div>

        <?php if (!empty($accounts)): ?>
        <div class="accounts-grid">
            <?php foreach ($accounts as $row): ?>
            <div class="account-card">
                <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                <div class="account-balances">
                    <p>اولیه: <?php echo toPersianDigits(number_format($row['initial_balance'], 0)); ?> تومان</p>
                    <p class="stat-value <?php echo $row['current_balance'] >= 0 ? 'text-income' : 'text-expense'; ?>">
                        فعلی: <?php echo toPersianDigits(number_format($row['current_balance'], 0)); ?> تومان
                    </p>
                </div>
                <div class="account-actions">
                    <a href="/pfm/accounts.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-small">ویرایش</a>
                    <form method="POST" style="display:inline" onsubmit="return confirm('آیا از حذف این حساب اطمینان دارید؟')">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="account_id" value="<?php echo $row['id']; ?>">
                        <button type="submit" class="btn btn-small btn-danger">حذف</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted">هنوز حسابی ایجاد نشده است. اولین حساب خود را بسازید.</p>
        <?php endif; ?>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <div class="page-header">
            <h1><?php echo $action === 'edit' ? 'ویرایش حساب' : 'افزودن حساب'; ?></h1>
            <a href="/pfm/accounts.php" class="btn">انصراف</a>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $action === 'edit' ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
            <input type="hidden" name="account_id" value="<?php echo $editData['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name">نام حساب *</label>
                <input type="text" id="name" name="name" required maxlength="100" value="<?php echo htmlspecialchars($editData['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="initial_balance">مانده اولیه (تومان)</label>
                <input type="number" id="initial_balance" name="initial_balance" step="1" value="<?php echo $editData['initial_balance'] ?? '0'; ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'بروزرسانی' : 'ایجاد'; ?> حساب</button>
                <a href="/pfm/accounts.php" class="btn">انصراف</a>
            </div>
        </form>
        <?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
