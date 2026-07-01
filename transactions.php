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

$typeLabels = ['income' => 'درآمد', 'expense' => 'هزینه'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'add' || $postAction === 'edit') {
            $type = $_POST['type'] ?? '';
            $date = $_POST['date'] ?? '';
            $time = $_POST['time'] ?? date('H:i:s');
            $account_id = intval($_POST['account_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $category_id = intval($_POST['category_id'] ?? 0) ?: null;
            $tag_id = intval($_POST['tag_id'] ?? 0) ?: null;
            $description = trim($_POST['description'] ?? '');
            $txnId = intval($_POST['transaction_id'] ?? 0);

            if (empty($type) || !in_array($type, ['income', 'expense'])) {
                $error = 'نوع تراکنش نامعتبر است.';
            } elseif (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $error = 'فرمت تاریخ نامعتبر است.';
            } elseif ($account_id <= 0) {
                $error = 'لطفاً یک حساب انتخاب کنید.';
            } elseif ($amount <= 0) {
                $error = 'مبلغ باید بزرگتر از صفر باشد.';
            } else {
                try {
                    $db->beginTransaction();

                    if ($postAction === 'edit' && $txnId > 0) {
                        $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
                        $stmt->execute([$txnId]);
                        $old = $stmt->fetch();

                        if (!$old) {
                            throw new Exception('تراکنش یافت نشد.');
                        }

                        $stmt = $db->prepare("SELECT current_balance FROM accounts WHERE id = ?");
                        $stmt->execute([$old['account_id']]);
                        $oldAccount = $stmt->fetch();

                        if ($old['type'] === 'income') {
                            $newBal = $oldAccount['current_balance'] - $old['amount'];
                        } else {
                            $newBal = $oldAccount['current_balance'] + $old['amount'];
                        }

                        $stmt = $db->prepare("UPDATE accounts SET current_balance = ? WHERE id = ?");
                        $stmt->execute([$newBal, $old['account_id']]);

                        $stmt = $db->prepare("UPDATE transactions SET type=?, date=?, time=?, account_id=?, amount=?, category_id=?, tag_id=?, description=? WHERE id=?");
                        $stmt->execute([$type, $date, $time, $account_id, $amount, $category_id, $tag_id, $description, $txnId]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO transactions (type, date, time, account_id, amount, category_id, tag_id, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$type, $date, $time, $account_id, $amount, $category_id, $tag_id, $description]);
                    }

                    $stmt = $db->prepare("SELECT current_balance FROM accounts WHERE id = ?");
                    $stmt->execute([$account_id]);
                    $acc = $stmt->fetch();

                    if ($type === 'income') {
                        $newBal = $acc['current_balance'] + $amount;
                    } else {
                        $newBal = $acc['current_balance'] - $amount;
                    }

                    $stmt = $db->prepare("UPDATE accounts SET current_balance = ? WHERE id = ?");
                    $stmt->execute([$newBal, $account_id]);

                    $db->commit();
                    $success = 'تراکنش با موفقیت ذخیره شد.';
                    $action = 'list';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'خطا در ذخیره تراکنش: ' . $e->getMessage();
                }
            }
        } elseif ($postAction === 'delete') {
            $txnId = intval($_POST['transaction_id'] ?? 0);
            if ($txnId > 0) {
                try {
                    $db->beginTransaction();

                    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
                    $stmt->execute([$txnId]);
                    $old = $stmt->fetch();

                    if ($old) {
                        $stmt = $db->prepare("SELECT current_balance FROM accounts WHERE id = ?");
                        $stmt->execute([$old['account_id']]);
                        $acc = $stmt->fetch();

                        if ($old['type'] === 'income') {
                            $newBal = $acc['current_balance'] - $old['amount'];
                        } else {
                            $newBal = $acc['current_balance'] + $old['amount'];
                        }

                        $stmt = $db->prepare("UPDATE accounts SET current_balance = ? WHERE id = ?");
                        $stmt->execute([$newBal, $old['account_id']]);

                        $stmt = $db->prepare("DELETE FROM transactions WHERE id = ?");
                        $stmt->execute([$txnId]);
                    }

                    $db->commit();
                    $success = 'تراکنش حذف شد.';
                    $action = 'list';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'خطا در حذف تراکنش.';
                }
            }
        }
    }
}

$accounts = $db->query("SELECT id, name FROM accounts ORDER BY name")->fetchAll();
$categories = $db->query("SELECT id, name, type FROM categories WHERE is_active=1 ORDER BY name")->fetchAll();
$tags = $db->query("SELECT id, name FROM tags ORDER BY name")->fetchAll();

$editData = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->execute([intval($id)]);
    $editData = $stmt->fetch();
    if (!$editData) {
        $action = 'list';
        $error = 'تراکنش یافت نشد.';
    }
}

$transactions = [];
if ($action === 'list') {
    $transactions = $db->query("
        SELECT t.*, a.name as account_name, c.name as category_name, tg.name as tag_name
        FROM transactions t
        LEFT JOIN accounts a ON t.account_id = a.id
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN tags tg ON t.tag_id = tg.id
        ORDER BY t.date DESC, t.time DESC
    ")->fetchAll();
}

// Compute datepicker initial value for add mode
$defaultJalaliDate = jalaliToday();
$editJalaliDate = '';
if ($editData) {
    $editJalaliDate = formatJalali($editData['date']);
}
$pageTitle = 'تراکنش‌ها';
$activePage = 'transactions';
$includeDatepicker = true;
require_once __DIR__ . '/includes/header.php';
?>

        <?php if ($action === 'list'): ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <div class="page-header">
            <h1>تراکنش‌ها</h1>
            <div class="btn-group">
                <a href="/pfm/transactions.php?action=add" class="btn btn-primary">+ افزودن تراکنش</a>
                <button type="button" class="btn btn-success" id="openSmsModal">پیامک افزودن</button>
            </div>
        </div>

        <?php if (!empty($transactions)): ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>تاریخ</th>
                    <th>زمان</th>
                    <th>نوع</th>
                    <th>دسته‌بندی</th>
                    <th>حساب</th>
                    <th>برچسب</th>
                    <th>مبلغ</th>
                    <th>عملیات</th>
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
                    <td class="actions">
                        <a href="/pfm/transactions.php?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-small">ویرایش</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('آیا از حذف این تراکنش اطمینان دارید؟')">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="transaction_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn btn-small btn-danger">حذف</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <p class="text-muted">تراکنشی یافت نشد. اولین تراکنش خود را اضافه کنید.</p>
        <?php endif; ?>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <div class="page-header">
            <h1><?php echo $action === 'edit' ? 'ویرایش تراکنش' : 'افزودن تراکنش'; ?></h1>
            <a href="/pfm/transactions.php" class="btn">انصراف</a>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $action === 'edit' ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
            <input type="hidden" name="transaction_id" value="<?php echo $editData['id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="type">نوع *</label>
                    <select id="type" name="type" required>
                        <option value="expense" <?php echo ($editData['type'] ?? '') === 'expense' ? 'selected' : ''; ?>>هزینه</option>
                        <option value="income" <?php echo ($editData['type'] ?? '') === 'income' ? 'selected' : ''; ?>>درآمد</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="amount">مبلغ (تومان) *</label>
                    <input type="number" id="amount" name="amount" step="1" min="1" required value="<?php echo $editData['amount'] ?? ''; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="jalali_date">تاریخ *</label>
                    <input type="text" id="jalali_date" class="pwt-datepicker-input" data-alt="date" readonly style="cursor:pointer;background:#fff;">
                    <input type="hidden" id="date" name="date" value="<?php echo $editData['date'] ?? date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="time">زمان</label>
                    <input type="time" id="time" name="time" value="<?php echo $editData['time'] ?? date('H:i'); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="account_id">حساب *</label>
                    <select id="account_id" name="account_id" required>
                        <option value="">انتخاب حساب</option>
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc['id']; ?>" <?php echo ($editData['account_id'] ?? '') == $acc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($acc['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="category_id">دسته‌بندی</label>
                    <select id="category_id" name="category_id">
                        <option value="">انتخاب دسته‌بندی</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" data-cattype="<?php echo $cat['type']; ?>" <?php echo ($editData['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="tag_id">برچسب</label>
                    <select id="tag_id" name="tag_id">
                        <option value="">انتخاب برچسب</option>
                        <?php foreach ($tags as $tag): ?>
                        <option value="<?php echo $tag['id']; ?>" <?php echo ($editData['tag_id'] ?? '') == $tag['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tag['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">توضیحات</label>
                    <input type="text" id="description" name="description" maxlength="255" value="<?php echo htmlspecialchars($editData['description'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'بروزرسانی' : 'افزودن'; ?> تراکنش</button>
                <a href="/pfm/transactions.php" class="btn">انصراف</a>
            </div>
        </form>
        <?php endif; ?>

        <!-- SMS Transaction Modal -->
        <div id="smsModal" class="modal-overlay" style="display:none;">
            <div class="modal">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="modal-header">
                    <h2>افزودن تراکنش از پیامک</h2>
                    <button type="button" class="modal-close" id="closeSmsModal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sms_account_id">حساب *</label>
                            <select id="sms_account_id" required>
                                <option value="">انتخاب حساب</option>
                                <?php foreach ($accounts as $acc): ?>
                                <option value="<?php echo $acc['id']; ?>"><?php echo htmlspecialchars($acc['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sms_type">نوع</label>
                            <select id="sms_type">
                                <option value="expense">هزینه</option>
                                <option value="income">درآمد</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="sms_category_id">دسته‌بندی</label>
                            <select id="sms_category_id">
                                <option value="">انتخاب دسته‌بندی</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" data-cattype="<?php echo $cat['type']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sms_tag_id">برچسب</label>
                            <select id="sms_tag_id">
                                <option value="">انتخاب برچسب</option>
                                <?php foreach ($tags as $tag): ?>
                                <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="sms_text">متن پیامک</label>
                        <textarea id="sms_text" rows="5" placeholder="متن پیامک بانکی را اینجا جایگذاری کنید..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-primary" id="processSms">پردازش پیامک</button>
                    </div>

                    <!-- Parsed results (hidden initially) -->
                    <div id="smsResults" style="display:none;">
                        <div class="sms-results-header">داده‌های استخراج شده</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sms_parsed_amount">مبلغ (تومان)</label>
                                <input type="number" id="sms_parsed_amount" step="1" min="1">
                            </div>
                            <div class="form-group">
                                <label for="sms_parsed_type">نوع</label>
                                <select id="sms_parsed_type">
                                    <option value="expense">هزینه</option>
                                    <option value="income">درآمد</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="sms_parsed_date">تاریخ (میلادی YYYY-MM-DD)</label>
                                <input type="text" id="sms_parsed_date" placeholder="1403/04/09 → 2024-07-01">
                            </div>
                            <div class="form-group">
                                <label for="sms_parsed_time">زمان</label>
                                <input type="time" id="sms_parsed_time" step="1">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="sms_parsed_description">توضیحات</label>
                            <input type="text" id="sms_parsed_description" maxlength="255">
                        </div>
                        <div class="form-actions">
                            <button type="button" class="btn btn-success" id="submitSmsTransaction">افزودن تراکنش</button>
                            <button type="button" class="btn" id="resetSmsForm">بازنشانی</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

<?php
require_once __DIR__ . '/includes/footer.php';
