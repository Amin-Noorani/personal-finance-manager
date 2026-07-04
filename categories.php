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
$typeFilter = $_GET['type'] ?? '';

$allCategories = $db->query("SELECT id, name, parent_category_id, type FROM categories ORDER BY name")->fetchAll();

$typeLabels = ['income' => 'درآمد', 'expense' => 'هزینه', 'both' => 'هر دو'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'add' || $postAction === 'edit') {
            $name = trim($_POST['name'] ?? '');
            $parentId = intval($_POST['parent_category_id'] ?? 0) ?: null;
            $catType = $_POST['type'] ?? 'both';
            if (!in_array($catType, ['income', 'expense', 'both'])) $catType = 'both';
            $isActive = $postAction === 'add' ? 1 : (isset($_POST['is_active']) ? 1 : 0);
            $catId = intval($_POST['category_id'] ?? 0);

            if (empty($name)) {
                $error = 'نام دسته‌بندی الزامی است.';
            } elseif ($parentId == $catId && $postAction === 'edit') {
                $error = 'یک دسته‌بندی نمی‌تواند والد خودش باشد.';
            } else {
                try {
                    if ($postAction === 'edit' && $catId > 0) {
                        $stmt = $db->prepare("UPDATE categories SET name = ?, parent_category_id = ?, is_active = ?, type = ? WHERE id = ?");
                        $stmt->execute([$name, $parentId, $isActive, $catType, $catId]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO categories (name, parent_category_id, is_active, type) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $parentId, $isActive, $catType]);
                    }
                    $success = 'دسته‌بندی با موفقیت ذخیره شد.';
                    $action = 'list';
                } catch (Exception $e) {
                    $error = 'خطا در ذخیره دسته‌بندی.';
                }
            }
        } elseif ($postAction === 'deactivate') {
            $catId = intval($_POST['category_id'] ?? 0);
            if ($catId > 0) {
                $stmt = $db->prepare("UPDATE categories SET is_active = 0 WHERE id = ?");
                $stmt->execute([$catId]);
                $success = 'دسته‌بندی غیرفعال شد.';
                $action = 'list';
            }
        } elseif ($postAction === 'activate') {
            $catId = intval($_POST['category_id'] ?? 0);
            if ($catId > 0) {
                $stmt = $db->prepare("UPDATE categories SET is_active = 1 WHERE id = ?");
                $stmt->execute([$catId]);
                $success = 'دسته‌بندی فعال شد.';
                $action = 'list';
            }
        } elseif ($postAction === 'merge') {
            $sourceId = intval($_POST['source_id'] ?? 0);
            $targetId = intval($_POST['target_id'] ?? 0);
            if ($sourceId <= 0 || $targetId <= 0) {
                $error = 'لطفاً هر دو دسته‌بندی مبدأ و مقصد را انتخاب کنید.';
            } elseif ($sourceId === $targetId) {
                $error = 'مبدأ و مقصد باید متفاوت باشند.';
            } else {
                try {
                    $db->beginTransaction();
                    $db->prepare("UPDATE transactions SET category_id = ? WHERE category_id = ?")->execute([$targetId, $sourceId]);
                    $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$sourceId]);
                    $db->commit();
                    $success = 'دسته‌بندی‌ها با موفقیت ادغام شدند.';
                    $action = 'list';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'خطا در ادغام دسته‌بندی‌ها.';
                }
            }
        }
    }
}

$editData = null;
if ($action === 'edit' && $id) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([intval($id)]);
    $editData = $stmt->fetch();
    if (!$editData) { $action = 'list'; $error = 'دسته‌بندی یافت نشد.'; }
}

// Build category query with type filter
$where = [];
$params = [];
if ($typeFilter && in_array($typeFilter, ['income', 'expense', 'both'])) {
    $where[] = "(c.type = ? OR c.type = 'both')";
    $params[] = $typeFilter;
}
$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$categories = $db->prepare("
    SELECT c.*, p.name as parent_name,
    (SELECT COUNT(*) FROM transactions WHERE category_id = c.id) as transaction_count
    FROM categories c
    LEFT JOIN categories p ON c.parent_category_id = p.id
    $whereClause
    ORDER BY c.type, COALESCE(p.name, c.name), c.parent_category_id, c.name
");
$categories->execute($params);
$categories = $categories->fetchAll();
$pageTitle = 'دسته‌بندی‌ها';
$activePage = 'categories';
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
            <h1>دسته‌بندی‌ها</h1>
            <div class="btn-group">
                <a href="/pfm/categories.php?action=add" class="btn btn-primary">+ افزودن دسته‌بندی</a>
                <a href="/pfm/categories.php?action=merge" class="btn">ادغام دسته‌بندی‌ها</a>
            </div>
        </div>

        <!-- Type filter tabs -->
        <div class="filter-tabs">
            <a href="/pfm/categories.php" class="filter-tab <?php echo empty($typeFilter) ? 'active' : ''; ?>">همه</a>
            <a href="/pfm/categories.php?type=income" class="filter-tab <?php echo $typeFilter === 'income' ? 'active' : ''; ?>">درآمد</a>
            <a href="/pfm/categories.php?type=expense" class="filter-tab <?php echo $typeFilter === 'expense' ? 'active' : ''; ?>">هزینه</a>
            <a href="/pfm/categories.php?type=both" class="filter-tab <?php echo $typeFilter === 'both' ? 'active' : ''; ?>">هر دو</a>
        </div>

        <?php if (!empty($categories)): ?>
        <div class="card-list">
            <?php foreach ($categories as $cat): ?>
            <div class="card-item <?php echo $cat['is_active'] ? '' : 'card-item--inactive'; ?>">
                <div class="card-accent card-accent--<?php echo $cat['type'] === 'income' ? 'income' : ($cat['type'] === 'expense' ? 'expense' : 'neutral'); ?>"></div>
                <div class="card-body">
                    <div class="card-fields">
                        <div class="card-field" style="flex:1;min-width:200px;">
                            <div class="card-field-label">نام</div>
                            <div class="card-field-value">
                                <?php if ($cat['parent_category_id']): ?>
                                <span class="indent"><?php echo htmlspecialchars($cat['parent_name']); ?> ← </span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </div>
                        </div>
                        <div class="card-field">
                            <div class="card-field-label">نوع</div>
                            <div class="card-field-value">
                                <span class="badge badge-<?php echo $cat['type'] === 'income' ? 'income' : ($cat['type'] === 'expense' ? 'expense' : 'active'); ?>">
                                    <?php echo $typeLabels[$cat['type']] ?? $cat['type']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-field">
                            <div class="card-field-label">وضعیت</div>
                            <div class="card-field-value">
                                <span class="badge <?php echo $cat['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo $cat['is_active'] ? 'فعال' : 'غیرفعال'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-field">
                            <div class="card-field-label">تراکنش‌ها</div>
                            <div class="card-field-value"><?php echo toPersianDigits($cat['transaction_count']); ?></div>
                        </div>
                    </div>
                </div>
                <div class="card-actions">
                    <a href="/pfm/categories.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-small">ویرایش</a>
                    <?php if ($cat['is_active']): ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="deactivate">
                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                        <button type="submit" class="btn btn-small btn-warning">غیرفعال</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                        <button type="submit" class="btn btn-small btn-success">فعال</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted">دسته‌بندی‌ای یافت نشد.</p>
        <?php endif; ?>

        <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <div class="page-header">
            <h1><?php echo $action === 'edit' ? 'ویرایش دسته‌بندی' : 'افزودن دسته‌بندی'; ?></h1>
            <a href="/pfm/categories.php" class="btn">انصراف</a>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $action === 'edit' ? 'edit' : 'add'; ?>">
            <?php if ($editData): ?>
            <input type="hidden" name="category_id" value="<?php echo $editData['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="name">نام دسته‌بندی *</label>
                <input type="text" id="name" name="name" required maxlength="100" value="<?php echo htmlspecialchars($editData['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="type">نوع *</label>
                <select id="type" name="type" required>
                    <option value="expense" <?php echo ($editData['type'] ?? '') === 'expense' ? 'selected' : ''; ?>>هزینه</option>
                    <option value="income" <?php echo ($editData['type'] ?? '') === 'income' ? 'selected' : ''; ?>>درآمد</option>
                    <option value="both" <?php echo ($editData['type'] ?? 'both') === 'both' ? 'selected' : ''; ?>>هر دو</option>
                </select>
            </div>

            <div class="form-group">
                <label for="parent_category_id">دسته‌بندی والد</label>
                <select id="parent_category_id" name="parent_category_id">
                    <option value="">ندارد (سطح بالا)</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <?php if (!$editData || $cat['id'] != $editData['id']): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($editData['parent_category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($action === 'edit'): ?>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" value="1" <?php echo ($editData['is_active'] ?? 1) ? 'checked' : ''; ?>>
                    فعال
                </label>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $action === 'edit' ? 'بروزرسانی' : 'ایجاد'; ?> دسته‌بندی</button>
                <a href="/pfm/categories.php" class="btn">انصراف</a>
            </div>
        </form>

        <?php elseif ($action === 'merge'): ?>
        <div class="page-header">
            <h1>ادغام دسته‌بندی‌ها</h1>
            <a href="/pfm/categories.php" class="btn">انصراف</a>
        </div>

        <form method="POST" class="form-card">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="merge">

            <p class="text-muted">تمام تراکنش‌های دسته‌بندی مبدأ به دسته‌بندی مقصد منتقل می‌شوند. دسته‌بندی مبدأ حذف خواهد شد.</p>

            <div class="form-group">
                <label for="source_id">ادغام از (مبدأ) *</label>
                <select id="source_id" name="source_id" required>
                    <option value="">انتخاب دسته‌بندی مبدأ</option>
                    <?php foreach ($allCategories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?> (<?php echo $typeLabels[$cat['type']] ?? ''; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="target_id">ادغام به (مقصد) *</label>
                <select id="target_id" name="target_id" required>
                    <option value="">انتخاب دسته‌بندی مقصد</option>
                    <?php foreach ($allCategories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?> (<?php echo $typeLabels[$cat['type']] ?? ''; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">ادغام دسته‌بندی‌ها</button>
                <a href="/pfm/categories.php" class="btn">انصراف</a>
            </div>
        </form>
        <?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
