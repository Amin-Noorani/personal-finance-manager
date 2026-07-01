<?php
// $pageTitle - page title text
// $activePage - which nav item is active (dashboard, transactions, categories, tags, accounts, search, statistics, analytics)
// $extraCss - array of additional CSS paths to load
// $includeDatepicker - whether to include datepicker CSS (default false)

$pageTitle = $pageTitle ?? 'مدیریت مالی شخصی';
$activePage = $activePage ?? '';
$extraCss = $extraCss ?? [];
$includeDatepicker = $includeDatepicker ?? false;

$navItems = [
    'dashboard' => ['url' => '/pfm/dashboard.php', 'label' => 'داشبورد'],
    'transactions' => ['url' => '/pfm/transactions.php', 'label' => 'تراکنش‌ها'],
    'categories' => ['url' => '/pfm/categories.php', 'label' => 'دسته‌بندی‌ها'],
    'tags' => ['url' => '/pfm/tags.php', 'label' => 'برچسب‌ها'],
    'accounts' => ['url' => '/pfm/accounts.php', 'label' => 'حساب‌ها'],
    'search' => ['url' => '/pfm/search.php', 'label' => 'جستجو'],
    'statistics' => ['url' => '/pfm/statistics.php', 'label' => 'آمار'],
    'analytics' => ['url' => '/pfm/analytics.php', 'label' => 'تحلیل'],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - مدیریت مالی شخصی</title>
    <link rel="stylesheet" href="/pfm/css/style.css">
    <?php if ($includeDatepicker): ?>
    <link rel="stylesheet" href="/pfm/lib/persian-datepicker/css/persian-datepicker.min.css">
    <?php endif; ?>
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?php echo $css; ?>">
    <?php endforeach; ?>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">مدیریت مالی</div>
        <button class="nav-toggle">☰</button>
        <div class="nav-links">
            <?php foreach ($navItems as $key => $item): ?>
            <a href="<?php echo $item['url']; ?>"<?php echo $activePage === $key ? ' class="active"' : ''; ?>><?php echo $item['label']; ?></a>
            <?php endforeach; ?>
            <a href="/pfm/logout.php" class="btn-logout">خروج</a>
        </div>
    </nav>

    <main class="container">
