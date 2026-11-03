<?php
$user = require_login();

// إعداد الفلاتر العامة
$activeTab = trim((string)($_GET['tab'] ?? 'matrix'));
$selectedProductId = (int)($_GET['product_id'] ?? 0);

if ($selectedProductId > 0 && empty($_GET['tab'])) {
    $activeTab = 'timeline';
}

$filters = [
    'q'           => trim((string)($_GET['q'] ?? '')),
    'type'        => trim((string)($_GET['type'] ?? 'all')),
    'status'      => trim((string)($_GET['status'] ?? 'all')),
    'location_id' => trim((string)($_GET['location_id'] ?? 'all')),
    'date_from'   => trim((string)($_GET['date_from'] ?? '')),
    'date_to'     => trim((string)($_GET['date_to'] ?? '')),
    'sort'        => trim((string)($_GET['sort'] ?? 'last_replenished')),
    'movement_type' => trim((string)($_GET['movement_type'] ?? 'all')),
];

$locations = stock_locations();
$stats = get_replenishment_statistics();

// جلب بيانات التبويب النشط
$matrixRows = [];
$selectedProduct = null;
$productHistory = [];
$recentAdditions = [];

if ($activeTab === 'matrix') {
    $matrixRows = get_replenishment_overview($filters);
} elseif ($activeTab === 'timeline') {
    if ($selectedProductId > 0) {
        $selectedProduct = get_product_detailed_info($selectedProductId);
        $locIdFilter = !empty($filters['location_id']) && $filters['location_id'] !== 'all' ? (int)$filters['location_id'] : null;
        $productHistory = get_product_movement_history($selectedProductId, $locIdFilter, $filters);
    }
} elseif ($activeTab === 'recent') {
    $recentAdditions = get_recent_central_additions(80, $filters);
}

// قائمة كل المنتجات النشطة للاختيار السريع في تبويب السجل الزمني
$allProducts = pdo()->query("SELECT id, name, barcode, type FROM products WHERE is_active = 1 ORDER BY (type = 'perfume_gram') DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

$productTypes = [
    'all'          => 'كل الأنواع',
    'perfume_gram' => '🌿 زيوت عطرية (بالجرام)',
    'bottle'       => '🧴 زجاجات عطر',
    'product'      => '📦 منتجات جاهزة',
    'recipe'       => '🧪 تركيبات',
];
?>

<style>
.stock-monitor-wrapper {
    display: flex;
    flex-direction: column;
    gap: 18px;
    margin-bottom: 30px;
}

/* بطاقات الإحصائيات KPI */
.stock-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 12px;
}

.stock-kpi-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--line, #e2e8f0);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.stock-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.stock-kpi-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.stock-kpi-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.stock-kpi-title {
    font-size: 11.5px;
    font-weight: 700;
    color: var(--muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stock-kpi-value {
    font-size: 19px;
    font-weight: 800;
    color: var(--ink, #0f172a);
    line-height: 1.2;
}

.stock-kpi-sub {
    font-size: 11px;
    color: var(--muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* شريط التبويبات الرئيسي */
.stock-tabs-nav {
    display: flex;
    gap: 8px;
    background: var(--surface, #ffffff);
    border: 1px solid var(--line, #e2e8f0);
    border-radius: 12px;
    padding: 6px;
    flex-wrap: wrap;
}

.stock-tab-btn {
    padding: 9px 18px;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--muted, #64748b);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.15s ease;
}

.stock-tab-btn:hover {
    background: var(--surface-soft, #f8fafc);
    color: var(--ink, #0f172a);
}

.stock-tab-btn.active {
    background: var(--primary, #b98418);
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(185, 132, 24, 0.25);
}

/* صندوق الفلاتر */
.stock-filter-box {
    background: var(--surface, #ffffff);
    border: 1px solid var(--line, #e2e8f0);
    border-radius: 12px;
    padding: 14px 16px;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
}

.stock-filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 10px;
    align-items: flex-end;
}

.stock-filter-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.stock-filter-field label {
    font-size: 11.5px;
    font-weight: 700;
    color: var(--muted, #64748b);
    margin: 0;
}

.stock-filter-field input,
.stock-filter-field select {
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid var(--line, #cbd5e1);
    font-size: 13px;
    font-weight: 600;
    background: var(--surface, #ffffff);
    color: var(--ink, #0f172a);
    outline: none;
    width: 100%;
}

.stock-filter-field input:focus,
.stock-filter-field select:focus {
    border-color: var(--primary, #b98418);
    box-shadow: 0 0 0 2px rgba(185, 132, 24, 0.15);
}

/* جداول البيانات الفاخرة */
.stock-table-card {
    background: var(--surface, #ffffff);
    border: 1px solid var(--line, #e2e8f0);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
}

.stock-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    text-align: right;
}

.stock-table th {
    background: #f8fafc;
    color: #475569;
    font-weight: 800;
    font-size: 12px;
    padding: 12px 14px;
    border-bottom: 2px solid var(--line, #e2e8f0);
    white-space: nowrap;
}

.stock-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.stock-table tbody tr:hover {
    background-color: rgba(248, 250, 252, 0.85);
}

/* الشارات الزمنية والحالات */
.badge-time-recent {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-time-mid {
    background: #fef9c3;
    color: #854d0e;
    border: 1px solid #fef08a;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-time-old {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-time-never {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.badge-type-oil {
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    padding: 2px 7px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
}

.badge-type-bottle {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
    padding: 2px 7px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
}

.badge-type-prod {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
    padding: 2px 7px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 700;
}

/* كارت تفاصيل الصنف في التبويب الزمني */
.product-hero-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1.5px solid var(--line, #e2e8f0);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.product-hero-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 12px;
    border-bottom: 1px dashed var(--line, #e2e8f0);
    padding-bottom: 14px;
}

.product-hero-stocks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
}

.product-stock-pill {
    background: #ffffff;
    border: 1px solid var(--line, #e2e8f0);
    border-radius: 10px;
    padding: 10px 12px;
    text-align: center;
}

.product-stock-pill.warehouse {
    border-color: #b98418;
    background: #fffdfa;
}

.running-balance-val {
    font-weight: 800;
    font-size: 13.5px;
    direction: ltr;
    display: inline-block;
}

@media print {
    .stock-tabs-nav, .stock-filter-box, .page-head, .btn, .no-print {
        display: none !important;
    }
    .stock-table-card {
        border: none !important;
        box-shadow: none !important;
    }
    body {
        background: #ffffff !important;
        color: #000000 !important;
    }
}
</style>

<div class="stock-monitor-wrapper">

    <!-- Page Head -->
    <section class="page-head">
        <div>
            <h2>مراقبة حركات تجديد وإضافة المخزون ⏱️</h2>
            <p>متابعة دقيقة لمواعيد تجديد أرصدة الزيوت والمنتجات، آخر كميات مضافة، وكشف حساب تفصيلي لكل حركة تمت على الصنف.</p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <a class="btn primary" href="index.php?r=inventory_add">
                <span>📥</span> إضافة مخزون مركزي
            </a>
            <button type="button" class="btn" onclick="window.print()">
                <span>🖨️</span> طباعة التقرير
            </button>
        </div>
    </section>

    <!-- بطاقات الإحصائيات العامة KPI -->
    <div class="stock-kpi-grid">
        <div class="stock-kpi-card">
            <div class="stock-kpi-icon" style="background:#ecfdf5; color:#059669;">🌿</div>
            <div class="stock-kpi-info">
                <span class="stock-kpi-title">الزيوت المضافة (آخر 30 يوم)</span>
                <span class="stock-kpi-value" style="color:#059669;"><?= qty($stats['oil_grams_30']) ?> <small style="font-size:12px;">جم</small></span>
                <span class="stock-kpi-sub">إجمالي الزيوت العطرية الخام</span>
            </div>
        </div>

        <div class="stock-kpi-card">
            <div class="stock-kpi-icon" style="background:#eff6ff; color:#2563eb;">🧴</div>
            <div class="stock-kpi-info">
                <span class="stock-kpi-title">الزجاجات والمنتجات (30 يوم)</span>
                <span class="stock-kpi-value" style="color:#2563eb;"><?= qty($stats['pieces_30']) ?> <small style="font-size:12px;">قطعة</small></span>
                <span class="stock-kpi-sub">زجاجات وتغليف ومنتجات جاهزة</span>
            </div>
        </div>

        <div class="stock-kpi-card">
            <div class="stock-kpi-icon" style="background:#fef3c7; color:#d97706;">⏱️</div>
            <div class="stock-kpi-info">
                <span class="stock-kpi-title">آخر عملية تجديد رصيد</span>
                <?php if ($stats['last_movement']): ?>
                    <span class="stock-kpi-value" style="font-size:15px; color:#d97706;"><?= e($stats['last_movement']['product_name']) ?></span>
                    <span class="stock-kpi-sub"><?= time_ago_ar($stats['last_movement']['created_at']) ?> (<?= qty($stats['last_movement']['quantity_delta']) ?> <?= e($stats['last_movement']['unit'] === 'gram' ? 'جم' : 'قطعة') ?>)</span>
                <?php else: ?>
                    <span class="stock-kpi-value" style="font-size:14px; color:var(--muted);">-</span>
                    <span class="stock-kpi-sub">لا توجد حركات مسجلة</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="stock-kpi-card">
            <div class="stock-kpi-icon" style="background:#f3e8ff; color:#7c3aed;">📦</div>
            <div class="stock-kpi-info">
                <span class="stock-kpi-title">أصناف تم تجديدها (30 يوم)</span>
                <span class="stock-kpi-value" style="color:#7c3aed;"><?= (int)$stats['unique_prods_30'] ?> <small style="font-size:12px;">صنف</small></span>
                <span class="stock-kpi-sub">تم توريد وتجديد رصيدها مؤخراً</span>
            </div>
        </div>

        <div class="stock-kpi-card">
            <div class="stock-kpi-icon" style="background:#fee2e2; color:#dc2626;">⚠️</div>
            <div class="stock-kpi-info">
                <span class="stock-kpi-title">أصناف تحتاج تجديد رصيد</span>
                <span class="stock-kpi-value" style="color:#dc2626;"><?= (int)$stats['low_stock_count'] ?> <small style="font-size:12px;">صنف</small></span>
                <span class="stock-kpi-sub">رصيدها في المخزن قارب على النفاد</span>
            </div>
        </div>
    </div>

    <!-- شريط التبويبات -->
    <div class="stock-tabs-nav no-print">
        <a href="index.php?r=stock_history&tab=matrix<?= $selectedProductId ? '&product_id='.$selectedProductId : '' ?>" 
           class="stock-tab-btn <?= $activeTab === 'matrix' ? 'active' : '' ?>">
            <span>📊</span> جدول مراقبة تجديد وأرصدة الأصناف
        </a>
        <a href="index.php?r=stock_history&tab=timeline<?= $selectedProductId ? '&product_id='.$selectedProductId : '' ?>" 
           class="stock-tab-btn <?= $activeTab === 'timeline' ? 'active' : '' ?>">
            <span>📜</span> كشف الحركات والتدقيق الزمني للصنف <?= $selectedProduct ? '('.e($selectedProduct['name']).')' : '' ?>
        </a>
        <a href="index.php?r=stock_history&tab=recent" 
           class="stock-tab-btn <?= $activeTab === 'recent' ? 'active' : '' ?>">
            <span>📥</span> أحدث عمليات الإضافة والتوريد المركزي
        </a>
    </div>

    <!-- ============================================================== -->
    <!-- التبويب 1: جدول مراقبة تجديد وأرصدة الأصناف (Replenishment Matrix) -->
    <!-- ============================================================== -->
    <?php if ($activeTab === 'matrix'): ?>

        <!-- صندوق الفلاتر والبحث -->
        <div class="stock-filter-box no-print">
            <form method="get" action="index.php">
                <input type="hidden" name="r" value="stock_history">
                <input type="hidden" name="tab" value="matrix">

                <div class="stock-filter-grid">
                    <div class="stock-filter-field" style="grid-column: span 2; min-width: 240px;">
                        <label>بحث بالاسم أو الباركود أو الكود:</label>
                        <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="اكتب اسم الزيت أو الصنف أو امسح الباركود...">
                    </div>

                    <div class="stock-filter-field">
                        <label>نوع الصنف:</label>
                        <select name="type">
                            <?php foreach ($productTypes as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $filters['type'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="stock-filter-field">
                        <label>حالة التجديد والرصيد:</label>
                        <select name="status">
                            <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>كل الحالات</option>
                            <option value="replenished_recently" <?= $filters['status'] === 'replenished_recently' ? 'selected' : '' ?>>🟢 جُددت مؤخراً (آخر 7 أيام)</option>
                            <option value="never_replenished" <?= $filters['status'] === 'never_replenished' ? 'selected' : '' ?>>⚪ لم تُجدد بعد (بدون حركات إضافة)</option>
                            <option value="low_stock" <?= $filters['status'] === 'low_stock' ? 'selected' : '' ?>>🟡 منخفضة المخزون (أقل من الحد)</option>
                            <option value="out_of_stock" <?= $filters['status'] === 'out_of_stock' ? 'selected' : '' ?>>🔴 نفدت من المخزن المركزي</option>
                        </select>
                    </div>

                    <div class="stock-filter-field">
                        <label>الموقع / المخزن:</label>
                        <select name="location_id">
                            <option value="all" <?= $filters['location_id'] === 'all' ? 'selected' : '' ?>>كل المواقع</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= e($loc['id']) ?>" <?= (string)$filters['location_id'] === (string)$loc['id'] ? 'selected' : '' ?>>
                                    <?= e($loc['type'] === 'warehouse' ? '🏢 ' : '📍 ') . e($loc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="stock-filter-field">
                        <label>الترتيب حسب:</label>
                        <select name="sort">
                            <option value="last_replenished" <?= $filters['sort'] === 'last_replenished' ? 'selected' : '' ?>>⏱️ الأحدث تجديداً أولاً</option>
                            <option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>>🔤 أبجدياً بالاسم</option>
                            <option value="stock_asc" <?= $filters['sort'] === 'stock_asc' ? 'selected' : '' ?>>📉 الأقل رصيداً في المخزن</option>
                            <option value="stock_desc" <?= $filters['sort'] === 'stock_desc' ? 'selected' : '' ?>>📈 الأكثر رصيداً في المخزن</option>
                            <option value="total_added" <?= $filters['sort'] === 'total_added' ? 'selected' : '' ?>>📦 الأكثر إضافة تاريخياً</option>
                        </select>
                    </div>

                    <div style="display:flex; gap:6px;">
                        <button type="submit" class="btn primary" style="padding: 8px 16px; flex:1;">تطبيق الفلتر</button>
                        <a href="index.php?r=stock_history&tab=matrix" class="btn" title="إعادة تعيين">🔄</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- جدول الأصناف -->
        <div class="stock-table-card">
            <div style="padding:14px 18px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <div style="font-weight:800; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <span>📦 الأصناف والمخزون:</span>
                    <span class="badge info" style="font-size:12px;"><?= count($matrixRows) ?> صنف</span>
                </div>
                <div style="font-size:12px; color:var(--muted);">
                    انقر على أي صنف أو زر <strong>«كشف الحركات 📜»</strong> للاطلاع على سجله الزمني بالكامل.
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="stock-table">
                    <thead>
                        <tr>
                            <th style="width:40px; text-align:center;">#</th>
                            <th style="min-width:200px;">الصنف والباركود</th>
                            <th style="min-width:110px;">النوع</th>
                            <th style="min-width:190px;">تاريخ آخر تجديد رصيد ⏱️</th>
                            <th style="min-width:130px; text-align:center;">كمية آخر تجديد</th>
                            <th style="min-width:150px;">المسؤول والموقع</th>
                            <th style="min-width:130px; text-align:center;">المخزن المركزي</th>
                            <th style="min-width:120px; text-align:center;">إجمالي الفروع</th>
                            <th style="min-width:130px; text-align:center;">إجمالي ما أضيف</th>
                            <th style="min-width:100px; text-align:center;">التكلفة / البيع</th>
                            <th style="min-width:100px; text-align:center;" class="no-print">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($matrixRows)): ?>
                            <tr>
                                <td colspan="11" style="text-align:center; padding:40px; color:var(--muted);">
                                    <div style="font-size:32px; margin-bottom:8px;">🔍</div>
                                    <div style="font-weight:700; font-size:15px;">لا توجد أصناف تطابق شروط البحث والفلتر الحالية.</div>
                                    <div style="font-size:12.5px; margin-top:4px;">جرب تغيير كلمة البحث أو إعادة تعيين الفلاتر.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($matrixRows as $idx => $row): 
                                $isOil = ($row['type'] === 'perfume_gram');
                                $unitName = $isOil ? 'جم' : 'قطعة';
                                $whStock = (float)$row['warehouse_stock'];
                                $totStock = (float)$row['total_stock'];
                                $minStock = (float)$row['min_stock'];

                                // شارة التوقيت
                                $timeAgoBadge = 'badge-time-never';
                                $timeAgoIcon = '⚪';
                                if ($row['last_replenished_at']) {
                                    $daysOld = (time() - strtotime($row['last_replenished_at'])) / 86400;
                                    if ($daysOld <= 7) {
                                        $timeAgoBadge = 'badge-time-recent';
                                        $timeAgoIcon = '🟢';
                                    } elseif ($daysOld <= 30) {
                                        $timeAgoBadge = 'badge-time-mid';
                                        $timeAgoIcon = '🟡';
                                    } else {
                                        $timeAgoBadge = 'badge-time-old';
                                        $timeAgoIcon = '⏳';
                                    }
                                }
                            ?>
                                <tr>
                                    <td style="text-align:center; color:var(--muted); font-weight:700;"><?= $idx + 1 ?></td>
                                    <td>
                                        <div style="font-weight:800; font-size:13.5px; color:var(--ink);">
                                            <a href="index.php?r=stock_history&tab=timeline&product_id=<?= (int)$row['id'] ?>" style="color:inherit; text-decoration:none;">
                                                <?= e($row['name']) ?>
                                            </a>
                                        </div>
                                        <div style="font-size:11px; color:var(--muted); font-family:monospace; margin-top:2px;">
                                            <?= e($row['barcode'] ?: ($row['sku'] ?: 'بدون باركود')) ?>
                                            <?php if (!empty($row['perfume_family'])): ?>
                                                • <span style="color:#059669;"><?= e($row['perfume_family']) ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($row['size_ml'])): ?>
                                                • <span><?= e($row['size_ml']) ?> مل</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isOil): ?>
                                            <span class="badge-type-oil">🌿 زيت عطر</span>
                                        <?php elseif ($row['type'] === 'bottle'): ?>
                                            <span class="badge-type-bottle">🧴 زجاجة</span>
                                        <?php else: ?>
                                            <span class="badge-type-prod">📦 منتج</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['last_replenished_at']): ?>
                                            <div style="font-weight:700; font-size:12.5px; color:#0f172a;">
                                                <?= format_datetime($row['last_replenished_at']) ?>
                                            </div>
                                            <div style="margin-top:3px;">
                                                <span class="<?= $timeAgoBadge ?>">
                                                    <span><?= $timeAgoIcon ?></span> <?= time_ago_ar($row['last_replenished_at']) ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-time-never">⚪ لم يُجدد بعد</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($row['last_replenished_qty'] !== null): ?>
                                            <span style="font-weight:800; color:#166534; font-size:14px;">
                                                +<?= qty($row['last_replenished_qty']) ?>
                                            </span>
                                            <span style="font-size:11px; color:var(--muted);"><?= $unitName ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--muted);">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['last_replenished_user']): ?>
                                            <div style="font-weight:700; font-size:12px; color:var(--ink);">
                                                👤 <?= e($row['last_replenished_user']) ?>
                                            </div>
                                            <div style="font-size:11px; color:var(--muted);">
                                                🏢 <?= e($row['last_replenished_location'] ?: 'المخزن الرئيسي') ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--muted); font-size:11.5px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if ($whStock <= 0): ?>
                                            <span class="badge danger" style="font-weight:800; font-size:12px;">نفد (0)</span>
                                        <?php elseif ($minStock > 0 && $whStock <= $minStock): ?>
                                            <span class="badge warning" style="font-weight:800; font-size:12.5px;"><?= qty($whStock) ?> <?= $unitName ?></span>
                                        <?php else: ?>
                                            <span class="badge success" style="font-weight:800; font-size:12.5px;"><?= qty($whStock) ?> <?= $unitName ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span style="font-weight:700; font-size:13px; color:var(--ink);"><?= qty($totStock) ?></span>
                                        <span style="font-size:11px; color:var(--muted);"><?= $unitName ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <div style="font-weight:800; font-size:13px; color:#1e40af;">
                                            <?= qty($row['total_added_qty']) ?> <?= $unitName ?>
                                        </div>
                                        <div style="font-size:10.5px; color:var(--muted);">
                                            <?= (int)$row['replenishment_count'] ?> دفعة توريد
                                        </div>
                                    </td>
                                    <td style="text-align:center; font-size:11.5px;">
                                        <?php if ($row['cost_price'] !== null && $row['cost_price'] > 0): ?>
                                            <div style="color:var(--muted); font-size:11px;">ت: <?= money($row['cost_price']) ?></div>
                                        <?php endif; ?>
                                        <div style="font-weight:700; color:var(--ink);"><?= money($row['sale_price']) ?></div>
                                    </td>
                                    <td style="text-align:center;" class="no-print">
                                        <a href="index.php?r=stock_history&tab=timeline&product_id=<?= (int)$row['id'] ?>" 
                                           class="btn small" style="padding:4px 10px; font-size:12px; font-weight:700; white-space:nowrap;"
                                           title="عرض كشف الحركات الكامل للصنف">
                                            🔍 كشف الحركات
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- ============================================================== -->
    <!-- التبويب 2: كشف الحركات والتدقيق الزمني للصنف (Timeline & Ledger) -->
    <!-- ============================================================== -->
    <?php elseif ($activeTab === 'timeline'): ?>

        <!-- اختيار الصنف السريع -->
        <div class="stock-filter-box no-print">
            <form method="get" action="index.php" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="r" value="stock_history">
                <input type="hidden" name="tab" value="timeline">

                <div class="stock-filter-field" style="flex:2; min-width:280px;">
                    <label>اختر صنفاً أو زيتاً لمراجعة كافة حركاته وتاريخ تجديده:</label>
                    <select name="product_id" onchange="this.form.submit()" required>
                        <option value="">-- اضغط لاختيار الصنف من القائمة --</option>
                        <?php foreach ($allProducts as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $selectedProductId === (int)$p['id'] ? 'selected' : '' ?>>
                                <?= e($p['name']) ?> (<?= e($p['type'] === 'perfume_gram' ? '🌿 زيت بالجرام' : ($p['type'] === 'bottle' ? '🧴 زجاجة' : '📦 منتج')) ?>) <?= $p['barcode'] ? ' - ['.e($p['barcode']).']' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="stock-filter-field" style="flex:1; min-width:180px;">
                    <label>تصفية بحسب الموقع:</label>
                    <select name="location_id" onchange="this.form.submit()">
                        <option value="all">كل المواقع والفروع</option>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= e($loc['id']) ?>" <?= (string)$filters['location_id'] === (string)$loc['id'] ? 'selected' : '' ?>>
                                <?= e($loc['type'] === 'warehouse' ? '🏢 ' : '📍 ') . e($loc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="stock-filter-field" style="flex:1; min-width:160px;">
                    <label>نوع الحركة:</label>
                    <select name="movement_type" onchange="this.form.submit()">
                        <option value="all" <?= $filters['movement_type'] === 'all' ? 'selected' : '' ?>>كل الحركات</option>
                        <option value="additions" <?= $filters['movement_type'] === 'additions' ? 'selected' : '' ?>>📥 إضافات وتجديد فقط (+)</option>
                        <option value="sales" <?= $filters['movement_type'] === 'sales' ? 'selected' : '' ?>>🛒 مبيعات فواتير (-)</option>
                        <option value="transfers" <?= $filters['movement_type'] === 'transfers' ? 'selected' : '' ?>>🚚 توريدات وتحويلات</option>
                        <option value="waste" <?= $filters['movement_type'] === 'waste' ? 'selected' : '' ?>>🗑️ هالك وتالف (-)</option>
                        <option value="returns" <?= $filters['movement_type'] === 'returns' ? 'selected' : '' ?>>↩️ مرتجعات (+)</option>
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn primary" style="padding:8px 16px;">عرض</button>
                </div>
            </form>
        </div>

        <?php if ($selectedProduct): 
            $isOil = ($selectedProduct['type'] === 'perfume_gram');
            $unitName = $isOil ? 'جم' : 'قطعة';
            $pStats = $selectedProduct['stats'] ?? [];
        ?>
            <!-- بطاقة ملخص الصنف الشاملة (Hero Card) -->
            <div class="product-hero-card">
                <div class="product-hero-header">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <h3 style="margin:0; font-size:18px; font-weight:800; color:var(--ink);"><?= e($selectedProduct['name']) ?></h3>
                            <?php if ($isOil): ?>
                                <span class="badge-type-oil">🌿 زيت عطر خام</span>
                            <?php elseif ($selectedProduct['type'] === 'bottle'): ?>
                                <span class="badge-type-bottle">🧴 زجاجة عطر</span>
                            <?php else: ?>
                                <span class="badge-type-prod">📦 منتج جاهز</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:12px; color:var(--muted); margin-top:4px; font-family:monospace;">
                            الباركود: <strong><?= e($selectedProduct['barcode'] ?: 'غير محدد') ?></strong>
                            <?php if (!empty($selectedProduct['sku'])): ?> • الكود: <strong><?= e($selectedProduct['sku']) ?></strong><?php endif; ?>
                            <?php if (!empty($selectedProduct['perfume_family'])): ?> • العائلة: <strong style="color:#059669;"><?= e($selectedProduct['perfume_family']) ?></strong><?php endif; ?>
                            <?php if (!empty($selectedProduct['quality_grade'])): ?> • الفئة: <strong><?= e($selectedProduct['quality_grade']) ?></strong><?php endif; ?>
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; align-items:center;">
                        <div style="text-align:left;">
                            <span style="font-size:11px; color:var(--muted); display:block;">تاريخ آخر تجديد رصيد:</span>
                            <?php if (!empty($pStats['last_replenished_at'])): ?>
                                <span style="font-weight:800; font-size:13.5px; color:#166534;">
                                    <?= format_datetime($pStats['last_replenished_at']) ?>
                                </span>
                                <span class="badge-time-recent" style="display:inline-block; margin-right:4px;">
                                    <?= time_ago_ar($pStats['last_replenished_at']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge-time-never">لم يُسجل تجديد بعد</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات حركة الصنف -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:10px;">
                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; text-align:center;">
                        <span style="font-size:11px; color:var(--muted); font-weight:700;">إجمالي الوارد (+)</span>
                        <div style="font-size:16px; font-weight:800; color:#166534; margin-top:2px;">
                            +<?= qty($pStats['total_in'] ?? 0) ?> <small style="font-size:11px;"><?= $unitName ?></small>
                        </div>
                    </div>

                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; text-align:center;">
                        <span style="font-size:11px; color:var(--muted); font-weight:700;">إجمالي المنصرف (-)</span>
                        <div style="font-size:16px; font-weight:800; color:#dc2626; margin-top:2px;">
                            -<?= qty($pStats['total_out'] ?? 0) ?> <small style="font-size:11px;"><?= $unitName ?></small>
                        </div>
                    </div>

                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; text-align:center;">
                        <span style="font-size:11px; color:var(--muted); font-weight:700;">إجمالي المبيعات (فواتير)</span>
                        <div style="font-size:16px; font-weight:800; color:#7c3aed; margin-top:2px;">
                            <?= qty($pStats['total_sold'] ?? 0) ?> <small style="font-size:11px;"><?= $unitName ?></small>
                        </div>
                    </div>

                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:10px; text-align:center;">
                        <span style="font-size:11px; color:var(--muted); font-weight:700;">الهالك والتالف</span>
                        <div style="font-size:16px; font-weight:800; color:#991b1b; margin-top:2px;">
                            <?= qty($pStats['total_wasted'] ?? 0) ?> <small style="font-size:11px;"><?= $unitName ?></small>
                        </div>
                    </div>

                    <div style="background:#ffffff; border:1.5px solid #b98418; border-radius:10px; padding:10px; text-align:center;">
                        <span style="font-size:11px; color:#b98418; font-weight:800;">الرصيد الفعلي الحالي</span>
                        <div style="font-size:17px; font-weight:800; color:var(--ink); margin-top:2px;">
                            <?= qty($selectedProduct['total_stock']) ?> <small style="font-size:11px;"><?= $unitName ?></small>
                        </div>
                    </div>
                </div>

                <!-- توزيع الرصيد الحالي على المواقع والفروع -->
                <div>
                    <span style="font-size:11.5px; font-weight:800; color:var(--muted); display:block; margin-bottom:6px;">
                        📍 توزيع الرصيد الحالي على الفروع والمخازن:
                    </span>
                    <div class="product-hero-stocks-grid">
                        <?php foreach ($selectedProduct['stocks'] as $stk): ?>
                            <div class="product-stock-pill <?= $stk['location_type'] === 'warehouse' ? 'warehouse' : '' ?>">
                                <div style="font-size:11px; color:var(--muted); font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= e($stk['location_type'] === 'warehouse' ? '🏢 ' : '📍 ') . e($stk['location_name']) ?>
                                </div>
                                <div style="font-size:14px; font-weight:800; color:var(--ink); margin-top:3px;">
                                    <?= qty($stk['stock_qty']) ?> <small style="font-size:10.5px; color:var(--muted);"><?= $unitName ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- جدول كشف الحركات والتدقيق الزمني للصنف -->
            <div class="stock-table-card">
                <div style="padding:14px 18px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div style="font-weight:800; font-size:14px; display:flex; align-items:center; gap:8px;">
                        <span>📜 سجل الحركات وكشف الحساب الزمني:</span>
                        <span class="badge info" style="font-size:12px;"><?= count($productHistory) ?> حركة مسجلة</span>
                    </div>
                    <div style="font-size:12px; color:var(--muted);">
                        يتم حساب <strong>الرصيد التراكمي</strong> بعد كل حركة مباشرة لمعرفة الرصيد لحظة بلحظة.
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table class="stock-table">
                        <thead>
                            <tr>
                                <th style="width:40px; text-align:center;">#</th>
                                <th style="min-width:160px;">التاريخ والوقت ⏱️</th>
                                <th style="min-width:150px;">نوع الحركة</th>
                                <th style="min-width:140px;">الموقع / الفرع</th>
                                <th style="min-width:110px; text-align:center;">الكمية (وارد / صادر)</th>
                                <th style="min-width:130px; text-align:center;">الرصيد التراكمي بعد الحركة</th>
                                <th style="min-width:160px;">المرجع والمستند</th>
                                <th style="min-width:120px;">القائم بالحركة</th>
                                <th style="min-width:200px;">الملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productHistory)): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding:35px; color:var(--muted);">
                                        <div style="font-size:28px;">📭</div>
                                        <div style="font-weight:700; font-size:14px; margin-top:6px;">لا توجد حركات مسجلة لهذا الصنف بالشروط المحددة.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($productHistory as $idx => $m): 
                                    $delta = (float)$m['quantity_delta'];
                                    $mTypeInfo = movement_type_ar($m['movement_type'], $delta, $m['reference_type']);
                                    $isPositive = ($delta > 0);
                                ?>
                                    <tr>
                                        <td style="text-align:center; color:var(--muted); font-weight:700;"><?= $idx + 1 ?></td>
                                        <td>
                                            <div style="font-weight:700; font-size:12.5px; color:var(--ink);">
                                                <?= format_datetime($m['created_at']) ?>
                                            </div>
                                            <div style="font-size:11px; color:var(--muted);">
                                                <?= time_ago_ar($m['created_at']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= e($mTypeInfo['badge']) ?>" style="font-size:11px; font-weight:700;">
                                                <span><?= $mTypeInfo['icon'] ?></span> <?= e($mTypeInfo['label']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight:700; font-size:12px; color:var(--ink);">
                                                <?= e($m['location_type'] === 'warehouse' ? '🏢 ' : '📍 ') . e($m['location_name']) ?>
                                            </div>
                                        </td>
                                        <td style="text-align:center;">
                                            <span style="font-weight:800; font-size:14px; color:<?= $isPositive ? '#166534' : '#dc2626' ?>;">
                                                <?= $isPositive ? '+' : '' ?><?= qty($delta) ?>
                                            </span>
                                            <span style="font-size:11px; color:var(--muted);"><?= $unitName ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="running-balance-val" style="color:#0f172a; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px; padding:3px 8px;">
                                                <?= qty($m['running_balance']) ?> <?= $unitName ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($m['reference_type'] === 'invoice' && !empty($m['reference_id'])): ?>
                                                <a href="index.php?r=invoice_view&id=<?= (int)$m['reference_id'] ?>" 
                                                   style="font-weight:700; color:#b98418; text-decoration:underline;">
                                                    فاتورة #<?= e($m['invoice_number'] ?: $m['reference_id']) ?>
                                                </a>
                                                <?php if (!empty($m['customer_name'])): ?>
                                                    <div style="font-size:11px; color:var(--muted);"><?= e($m['customer_name']) ?></div>
                                                <?php endif; ?>
                                            <?php elseif ($m['reference_type'] === 'transfer' && !empty($m['reference_id'])): ?>
                                                <span style="font-weight:700; color:#2563eb;">
                                                    تحويل #<?= e($m['transfer_number'] ?: $m['reference_id']) ?>
                                                </span>
                                                <div style="font-size:10.5px; color:var(--muted);">
                                                    من <?= e($m['from_location_name']) ?> إلى <?= e($m['to_location_name']) ?>
                                                </div>
                                            <?php elseif ($m['reference_type'] === 'waste'): ?>
                                                <span class="badge danger" style="font-size:10.5px;">إذن هالك</span>
                                            <?php elseif ($m['movement_type'] === 'initial'): ?>
                                                <span class="badge success" style="font-size:10.5px;">إذن إضافة مركزي</span>
                                            <?php else: ?>
                                                <span style="color:var(--muted); font-size:11.5px;">يدوي #<?= (int)$m['id'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size:12px; font-weight:700; color:var(--ink);">
                                                👤 <?= e($m['user_name']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size:12px; color:var(--ink); max-width:260px; line-height:1.4;">
                                                <?= e($m['notes'] ?: '-') ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="stock-table-card" style="padding:50px; text-align:center;">
                <div style="font-size:42px; margin-bottom:12px;">👆</div>
                <h3 style="font-weight:800; font-size:17px; margin-bottom:6px;">يرجى اختيار صنف أو زيت من القائمة أعلاه</h3>
                <p style="color:var(--muted); font-size:13px; max-width:450px; margin:0 auto;">
                    بمجرد اختيار الصنف، سيتم عرض بطاقة تحليله الكاملة وكشف حساب تفصيلي لكافة الحركات التي تمت عليه منذ أول إضافة وحتى اللحظة مع الرصيد التراكمي.
                </p>
            </div>
        <?php endif; ?>

    <!-- ============================================================== -->
    <!-- التبويب 3: أحدث عمليات الإضافة والتوريد المركزي (Recent Feed) -->
    <!-- ============================================================== -->
    <?php elseif ($activeTab === 'recent'): ?>

        <!-- صندوق الفلاتر والبحث في الإضافات -->
        <div class="stock-filter-box no-print">
            <form method="get" action="index.php">
                <input type="hidden" name="r" value="stock_history">
                <input type="hidden" name="tab" value="recent">

                <div class="stock-filter-grid">
                    <div class="stock-filter-field" style="grid-column: span 2;">
                        <label>بحث بالصنف أو الملاحظات أو الفرع:</label>
                        <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="ابحث باسم الزيت أو المنتج...">
                    </div>

                    <div class="stock-filter-field">
                        <label>الموقع:</label>
                        <select name="location_id">
                            <option value="all">كل المواقع</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= e($loc['id']) ?>" <?= (string)$filters['location_id'] === (string)$loc['id'] ? 'selected' : '' ?>>
                                    <?= e($loc['type'] === 'warehouse' ? '🏢 ' : '📍 ') . e($loc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="stock-filter-field">
                        <label>من تاريخ:</label>
                        <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
                    </div>

                    <div class="stock-filter-field">
                        <label>إلى تاريخ:</label>
                        <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
                    </div>

                    <div>
                        <button type="submit" class="btn primary" style="padding:8px 16px; width:100%;">تطبيق</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="stock-table-card">
            <div style="padding:14px 18px; border-bottom:1px solid var(--line); display:flex; justify-content:space-between; align-items:center;">
                <div style="font-weight:800; font-size:14px; display:flex; align-items:center; gap:8px;">
                    <span>📥 أحدث عمليات الإضافة والتوريد المركزي:</span>
                    <span class="badge success" style="font-size:12px;"><?= count($recentAdditions) ?> عملية</span>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="stock-table">
                    <thead>
                        <tr>
                            <th style="width:40px; text-align:center;">#</th>
                            <th style="min-width:160px;">تاريخ ووقت الإضافة ⏱️</th>
                            <th style="min-width:200px;">الصنف والباركود</th>
                            <th style="min-width:100px;">النوع</th>
                            <th style="min-width:140px;">موقع الإضافة</th>
                            <th style="min-width:120px; text-align:center;">الكمية المضافة</th>
                            <th style="min-width:130px;">المسؤول عن الإضافة</th>
                            <th style="min-width:200px;">الملاحظات</th>
                            <th style="min-width:90px; text-align:center;" class="no-print">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAdditions)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:35px; color:var(--muted);">
                                    لا توجد عمليات إضافة مطابقة.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentAdditions as $idx => $add): 
                                $isOil = ($add['product_type'] === 'perfume_gram');
                                $unitName = $isOil ? 'جم' : 'قطعة';
                            ?>
                                <tr>
                                    <td style="text-align:center; color:var(--muted); font-weight:700;"><?= $idx + 1 ?></td>
                                    <td>
                                        <div style="font-weight:700; font-size:12.5px; color:var(--ink);">
                                            <?= format_datetime($add['created_at']) ?>
                                        </div>
                                        <div style="font-size:11px; color:var(--muted);">
                                            <?= time_ago_ar($add['created_at']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight:800; font-size:13.5px; color:var(--ink);">
                                            <a href="index.php?r=stock_history&tab=timeline&product_id=<?= (int)$add['product_id'] ?>" style="color:inherit; text-decoration:none;">
                                                <?= e($add['product_name']) ?>
                                            </a>
                                        </div>
                                        <div style="font-size:11px; color:var(--muted); font-family:monospace;">
                                            <?= e($add['product_barcode'] ?: 'بدون باركود') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isOil): ?>
                                            <span class="badge-type-oil">🌿 زيت عطر</span>
                                        <?php elseif ($add['product_type'] === 'bottle'): ?>
                                            <span class="badge-type-bottle">🧴 زجاجة</span>
                                        <?php else: ?>
                                            <span class="badge-type-prod">📦 منتج</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:700; font-size:12px; color:var(--ink);">
                                            <?= e($add['location_type'] === 'warehouse' ? '🏢 ' : '📍 ') . e($add['location_name']) ?>
                                        </div>
                                    </td>
                                    <td style="text-align:center;">
                                        <span style="font-weight:800; font-size:14px; color:#166534;">
                                            +<?= qty($add['quantity_delta']) ?>
                                        </span>
                                        <span style="font-size:11px; color:var(--muted);"><?= $unitName ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight:700; font-size:12px; color:var(--ink);">
                                            👤 <?= e($add['user_name']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size:12px; color:var(--ink);">
                                            <?= e($add['notes'] ?: 'إضافة مخزون') ?>
                                        </div>
                                    </td>
                                    <td style="text-align:center;" class="no-print">
                                        <a href="index.php?r=stock_history&tab=timeline&product_id=<?= (int)$add['product_id'] ?>" 
                                           class="btn small" style="padding:3px 8px; font-size:11.5px; font-weight:700;">
                                            🔍 كشف
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>

</div>
