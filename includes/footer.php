<?php
// $includeDatepicker - whether to include datepicker JS (default false)
// $includeChart - whether to include Chart.js (default false)
// $extraScripts - array of additional inline scripts or JS paths

$includeDatepicker = $includeDatepicker ?? false;
$includeChart = $includeChart ?? false;
$extraScripts = $extraScripts ?? [];
?>
    </main>

    <?php if ($includeDatepicker): ?>
    <script src="/pfm/lib/jquery.min.js"></script>
    <script src="/pfm/lib/persian-date.min.js"></script>
    <script src="/pfm/lib/persian-datepicker/js/persian-datepicker.min.js"></script>
    <?php endif; ?>
    <?php if ($includeChart): ?>
    <script src="/pfm/lib/chart.min.js"></script>
    <?php endif; ?>
    <script src="/pfm/js/app.js?ver=<?php echo APP_VERSION ?>"></script>
    <?php foreach ($extraScripts as $script): ?>
    <?php if (strpos($script, '<') === 0): ?>
    <?php echo $script; ?>
    <?php else: ?>
    <script src="<?php echo $script . '?ver=' . APP_VERSION ?>"></script>
    <?php endif; ?>
    <?php endforeach; ?>
</body>
</html>
