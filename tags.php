<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jalali.php';
requireLogin();

$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'add') {
            $name = trim($_POST['name'] ?? '');
            if (empty($name)) { $error = 'نام برچسب الزامی است.'; }
            else {
                try {
                    $stmt = $db->prepare("INSERT INTO tags (name) VALUES (?)");
                    $stmt->execute([$name]);
                    $success = 'برچسب با موفقیت ایجاد شد.';
                } catch (Exception $e) { $error = 'برچسب قبلاً وجود دارد یا خطا در ایجاد.'; }
            }
        } elseif ($postAction === 'rename') {
            $tagId = intval($_POST['tag_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($tagId <= 0 || empty($name)) { $error = 'برچسب یا نام نامعتبر.'; }
            else {
                try {
                    $stmt = $db->prepare("UPDATE tags SET name = ? WHERE id = ?");
                    $stmt->execute([$name, $tagId]);
                    $success = 'نام برچسب تغییر کرد.';
                } catch (Exception $e) { $error = 'نام برچسب قبلاً وجود دارد.'; }
            }
        } elseif ($postAction === 'delete') {
            $tagId = intval($_POST['tag_id'] ?? 0);
            if ($tagId > 0) {
                $db->prepare("UPDATE transactions SET tag_id = NULL WHERE tag_id = ?")->execute([$tagId]);
                $db->prepare("DELETE FROM tags WHERE id = ?")->execute([$tagId]);
                $success = 'برچسب حذف شد.';
            }
        } elseif ($postAction === 'merge') {
            $sourceId = intval($_POST['source_id'] ?? 0);
            $targetId = intval($_POST['target_id'] ?? 0);
            if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
                $error = 'لطفاً برچسب‌های مبدأ و مقصد متفاوت انتخاب کنید.';
            } else {
                try {
                    $db->beginTransaction();
                    $db->prepare("UPDATE transactions SET tag_id = ? WHERE tag_id = ?")->execute([$targetId, $sourceId]);
                    $db->prepare("DELETE FROM tags WHERE id = ?")->execute([$sourceId]);
                    $db->commit();
                    $success = 'برچسب‌ها با موفقیت ادغام شدند.';
                } catch (Exception $e) { $db->rollBack(); $error = 'خطا در ادغام برچسب‌ها.'; }
            }
        }
    }
}

$tags = $db->query("SELECT t.*, (SELECT COUNT(*) FROM transactions WHERE tag_id = t.id) as transaction_count FROM tags t ORDER BY t.name")->fetchAll();
$allTags = $tags;

$pageTitle = 'برچسب‌ها';
$activePage = 'tags';
require_once __DIR__ . '/includes/header.php';
?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="page-header">
            <h1>برچسب‌ها</h1>
            <div class="btn-group">
                <form method="POST" style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add">
                    <input type="text" name="name" placeholder="نام برچسب جدید" required maxlength="50" style="padding:0.5rem;border:1px solid var(--gray-300);border-radius:6px;font-family:'Vazir',sans-serif;min-width:150px;">
                    <button type="submit" class="btn btn-primary">افزودن</button>
                </form>
                <a href="/pfm/tags.php?action=merge" class="btn">ادغام برچسب‌ها</a>
            </div>
        </div>

        <?php if (!empty($tags)): ?>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>نام</th>
                    <th>تراکنش‌ها</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tags as $tag): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($tag['name']); ?></strong></td>
                    <td><?php echo toPersianDigits($tag['transaction_count']); ?></td>
                    <td class="actions">
                        <form method="POST" style="display:inline-flex;gap:0.25rem;align-items:center;flex-wrap:wrap;">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="rename">
                            <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                            <input type="text" name="name" value="<?php echo htmlspecialchars($tag['name']); ?>" style="width:120px;padding:0.25rem;border:1px solid var(--gray-300);border-radius:4px;font-size:0.85rem;font-family:'Vazir',sans-serif;">
                            <button type="submit" class="btn btn-small">تغییر نام</button>
                        </form>
                        <form method="POST" style="display:inline" onsubmit="return confirm('آیا از حذف این برچسب اطمینان دارید؟ تراکنش‌ها داده‌شان را حفظ می‌کنند.')">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                            <button type="submit" class="btn btn-small btn-danger">حذف</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php else: ?>
        <p class="text-muted">هنوز برچسبی وجود ندارد. اولین برچسب خود را ایجاد کنید.</p>
        <?php endif; ?>

        <?php if (isset($_GET['action']) && $_GET['action'] === 'merge'): ?>
        <div class="section" style="margin-top:1.5rem;">
            <h2>ادغام برچسب‌ها</h2>
            <form method="POST" class="form-card">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="merge">
                <p class="text-muted">تمام تراکنش‌های برچسب مبدأ به برچسب مقصد منتقل می‌شوند. برچسب مبدأ حذف خواهد شد.</p>
                <div class="form-group">
                    <label for="source_id">ادغام از (مبدأ) *</label>
                    <select id="source_id" name="source_id" required>
                        <option value="">انتخاب برچسب مبدأ</option>
                        <?php foreach ($allTags as $tag): ?>
                        <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="target_id">ادغام به (مقصد) *</label>
                    <select id="target_id" name="target_id" required>
                        <option value="">انتخاب برچسب مقصد</option>
                        <?php foreach ($allTags as $tag): ?>
                        <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">ادغام برچسب‌ها</button>
                    <a href="/pfm/tags.php" class="btn">انصراف</a>
                </div>
            </form>
        </div>
        <?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
