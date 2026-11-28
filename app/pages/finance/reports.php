<?php
$labels = [
    'sales_by_location' => 'مبيعات حسب الموقع',
    'sales_by_payment' => 'طرق الدفع',
    'sales_by_user' => 'أداء الموظفين',
    'top_products' => 'أكثر 5 منتجات',
    'perfume_usage' => 'استهلاك العطور بالجرام',
    'new_customers' => 'العملاء الجدد (آخر 10)',
];

$allowed_reports = [];
foreach ($labels as $key => $label) {
    if (has_permission('reports_' . $key)) {
        $allowed_reports[$key] = $label;
    }
}

if (empty($allowed_reports)) {
    echo '<div class="alert danger">غير مصرح لك بعرض أي تقارير.</div>';
    return;
}

$activeTab = $_GET['tab'] ?? array_key_first($allowed_reports);
if (!array_key_exists($activeTab, $allowed_reports)) {
    $activeTab = array_key_first($allowed_reports);
}

$userLocationId = current_user_location_id();
$locations = all_locations();
$reportFilters = $_GET;
if ($userLocationId !== null) {
    $reportFilters['location_id'] = (string) $userLocationId;
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
}
$r = report_stats($reportFilters);

$selectedMonth = date('Y-m');
if (!empty($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['month'])) {
    $selectedMonth = (string) $_GET['month'];
} elseif (!empty($_GET['start_date']) && preg_match('/^(\d{4}-\d{2})/', (string) $_GET['start_date'], $mm)) {
    $selectedMonth = $mm[1];
}
$selectedExportLoc = $userLocationId !== null ? (string) $userLocationId : (string) ($_GET['location_id'] ?? '');
?>
<section class="page-head">
    <div>
        <h2>التقارير الإحصائية</h2>
        <p>عرض تفصيلي لكل تقارير النظام من مبيعات ومخزون وأداء فروع وموظفين وتصدير التقارير الشهرية الشاملة.</p>
    </div>
    <button class="btn" onclick="window.print()">طباعة التقرير الحالي / PDF</button>
</section>

<!-- Monthly Comprehensive Report Export Card (Excel & PDF) -->
<section class="panel" style="border: 1.5px solid var(--primary); background: linear-gradient(135deg, rgba(185, 132, 24, 0.08) 0%, rgba(111, 74, 8, 0.02) 100%), var(--surface);">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 12px;">
        <div>
            <h3 style="margin: 0 0 4px; color: var(--primary-dark); font-size: 16px;">📊 التقرير الشهري الشامل (Excel &amp; PDF)</h3>
            <p class="muted" style="margin: 0; font-size: 12.5px;">
                يجمع كل ما تم في الشهر المحدد: <strong>المبيعات والفواتير</strong>، <strong>المصاريف</strong>، <strong>المرتجعات</strong>، <strong>الرواتب المنصرفة</strong>، <strong>المنتجات والزيوت</strong>، <strong>أداء الموظفين</strong>، <strong>العملاء الجدد</strong>، و<strong>التحويلات والهالك والشيفتات</strong>.
            </p>
        </div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; align-items: end;">
        <label style="margin: 0;">اختر الشهر المطلوب
            <input type="month" id="monthly-export-month" value="<?= e($selectedMonth) ?>" style="font-weight: 700;">
        </label>
        <label style="margin: 0;">الفرع / الموقع
            <select id="monthly-export-location" <?= $userLocationId !== null ? 'disabled' : '' ?>>
                <?php if ($userLocationId === null): ?>
                    <option value="">كل الفروع والمستودعات</option>
                <?php endif; ?>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= e($l['id']) ?>" <?= $selectedExportLoc === (string) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="button" class="btn" onclick="exportMonthlyReport('monthly_excel')" style="height: 38px; background: linear-gradient(135deg, #059669, #10B981); color: #fff; border: none; font-weight: 800;">
            📊 تصدير شيت Excel احترافي (.xlsx)
        </button>
        <button type="button" class="btn primary" onclick="exportMonthlyReport('monthly_pdf')" style="height: 38px; font-weight: 800;">
            📄 تصدير تقرير PDF بتصميم احترافي
        </button>
    </div>
</section>
<script>
function exportMonthlyReport(type) {
    const monthEl = document.getElementById('monthly-export-month');
    const locEl   = document.getElementById('monthly-export-location');
    const month   = (monthEl && monthEl.value) ? monthEl.value : '<?= e($selectedMonth) ?>';
    const loc     = locEl ? locEl.value : '';
    let url = 'index.php?r=reports&export=' + encodeURIComponent(type) + '&month=' + encodeURIComponent(month);
    if (loc) {
        url += '&location_id=' + encodeURIComponent(loc);
    }
    if (type === 'monthly_pdf') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}
</script>

<form class="panel toolbar" method="get">
    <input type="hidden" name="r" value="reports">
    <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
    <label>من تاريخ<input type="date" name="start_date" value="<?= e($_GET['start_date'] ?? '') ?>"></label>
    <label>إلى تاريخ<input type="date" name="end_date" value="<?= e($_GET['end_date'] ?? '') ?>"></label>
    <label>الموقع<select name="location_id" <?= $userLocationId !== null ? 'disabled' : '' ?>>
        <?php if ($userLocationId === null): ?>
            <option value="">كل الفروع والمستودعات</option>
        <?php endif; ?>
        <?php foreach ($locations as $l): ?>
            <option value="<?= e($l['id']) ?>" <?= (($reportFilters['location_id'] ?? '') == $l['id']) ? 'selected' : '' ?>><?= e($l['name']) ?></option>
        <?php endforeach; ?>
    </select><?php if ($userLocationId !== null): ?><input type="hidden" name="location_id" value="<?= e($userLocationId) ?>"><?php endif; ?></label>
    <button class="btn primary">تصفية</button>
    <a class="btn" href="index.php?r=reports&tab=<?= e($activeTab) ?>">إعادة ضبط</a>
</form>

<div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
    <?php foreach ($allowed_reports as $key => $label): ?>
        <?php 
        $query = array_merge($_GET, ['tab' => $key]);
        $href = 'index.php?' . http_build_query($query);
        ?>
        <a class="btn <?= $activeTab === $key ? 'primary' : '' ?>" href="<?= $href ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <div class="panel-title" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px; border-bottom: 1px solid var(--line); padding-bottom: 8px;">
        <h3 style="margin:0;"><?= e($allowed_reports[$activeTab]) ?></h3>
        <?php
        $exportQuery = array_merge($_GET, ['export' => $activeTab]);
        ?>
        <a class="btn small" href="index.php?<?= http_build_query($exportQuery) ?>">تصدير CSV</a>
    </div>
    <?= simple_table($r[$activeTab] ?? []) ?>
</div>
