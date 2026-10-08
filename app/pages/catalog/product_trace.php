<?php
$user = require_login();
$locations = stock_locations();

// تحديد الفرع المختار
$userLocationId = current_user_location_id();
$selectedLocationId = $_GET['location_id'] ?? ($userLocationId !== null ? $userLocationId : 'all');
$locationId = ($selectedLocationId !== 'all' && $selectedLocationId !== '') ? (int)$selectedLocationId : null;

// اسم الفرع المختار للعرض
$selectedLocationName = 'كل الفروع والمستودعات';
if ($locationId !== null) {
    foreach ($locations as $l) {
        if ((int)$l['id'] === $locationId) {
            $selectedLocationName = $l['name'];
            break;
        }
    }
}

$query = trim((string)($_GET['q'] ?? ''));
$productId = (int)($_GET['product_id'] ?? 0);

$db = pdo();
$product = null;
$multipleMatches = [];
$searched = ($query !== '' || $productId > 0);

// قائمة كل المنتجات النشطة للاقتراح التلقائي السريع أثناء الكتابة
$allProductsList = $db->query("SELECT id, name, type, barcode FROM products WHERE is_active = 1 ORDER BY (type = 'perfume_gram') DESC, name ASC")->fetchAll();

if ($productId > 0) {
    $stmt = $db->prepare("SELECT p.*, b.size_ml, d.perfume_family, d.quality_grade, d.price_per_gram 
                           FROM products p 
                           LEFT JOIN product_bottle_details b ON b.product_id = p.id 
                           LEFT JOIN product_perfume_details d ON d.product_id = p.id 
                           WHERE p.id = ? AND p.is_active = 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
} elseif ($query !== '') {
    // 1. بحث بالباركود المباشر (تطابق تام)
    $stmt = $db->prepare("SELECT p.*, b.size_ml, d.perfume_family, d.quality_grade, d.price_per_gram 
                           FROM products p 
                           LEFT JOIN product_bottle_details b ON b.product_id = p.id 
                           LEFT JOIN product_perfume_details d ON d.product_id = p.id 
                           WHERE p.barcode = ? AND p.is_active = 1 LIMIT 1");
    $stmt->execute([$query]);
    $product = $stmt->fetch();

    // 2. بحث باسم الصنف (تطابق تام أولاً)
    if (!$product) {
        $stmt = $db->prepare("SELECT p.*, b.size_ml, d.perfume_family, d.quality_grade, d.price_per_gram 
                               FROM products p 
                               LEFT JOIN product_bottle_details b ON b.product_id = p.id 
                               LEFT JOIN product_perfume_details d ON d.product_id = p.id 
                               WHERE p.name = ? AND p.is_active = 1 LIMIT 1");
        $stmt->execute([$query]);
        $product = $stmt->fetch();
    }

    // 3. بحث مرن (LIKE) إذا لم يتطابق بالكامل
    if (!$product) {
        $stmt = $db->prepare("SELECT p.*, b.size_ml, d.perfume_family, d.quality_grade, d.price_per_gram 
                               FROM products p 
                               LEFT JOIN product_bottle_details b ON b.product_id = p.id 
                               LEFT JOIN product_perfume_details d ON d.product_id = p.id 
                               WHERE (p.name LIKE ? OR p.barcode LIKE ?) AND p.is_active = 1 
                               ORDER BY 
                                 (p.name = ?) DESC,
                                 (p.name LIKE ?) DESC,
                                 (p.type = 'perfume_gram') DESC,
                                 p.name ASC 
                               LIMIT 30");
        $likeContains = '%' . $query . '%';
        $likeStarts   = $query . '%';
        $stmt->execute([$likeContains, $likeContains, $query, $likeStarts]);
        $matches = $stmt->fetchAll();

        if (count($matches) === 1) {
            $product = $matches[0];
        } elseif (count($matches) > 1) {
            $multipleMatches = $matches;
        }
    }
}

// تجهيز البيانات التفصيلية في حالة اختيار صنف محدد
$stockList = [];
$totalStockAll = 0.0;
$currentBranchStock = 0.0;
$hasBranchStock = false;
$invoices = [];
$branchSold = 0.0;
$allSold = 0.0;
$directSold = 0.0;
$mixSold = 0.0;
$totalRevenue = 0.0;
$uniqueInvoiceIds = [];
$unitLabel = 'قطعة';

if ($product) {
    $targetId = (int)$product['id'];
    $unitLabel = $product['unit'] === 'gram' ? 'جم' : 'قطعة';

    // 1. حساب الرصيد الفعلي الحالي في كل الفروع والمخازن
    $stockStmt = $db->prepare("SELECT l.id, l.name, l.type, COALESCE(ib.quantity, 0) AS qty 
                               FROM locations l 
                               LEFT JOIN inventory_balances ib ON ib.location_id = l.id AND ib.product_id = ? 
                               WHERE l.is_active = 1 
                               ORDER BY (l.type = 'warehouse') DESC, (l.id = ?) DESC, l.name ASC");
    $stockStmt->execute([$targetId, $locationId ?? 0]);
    $stockList = $stockStmt->fetchAll();

    foreach ($stockList as $stk) {
        $qVal = (float)$stk['qty'];
        $totalStockAll += $qVal;
        if ($locationId !== null && (int)$stk['id'] === $locationId) {
            $currentBranchStock = $qVal;
            $hasBranchStock = true;
        }
    }

    // 2. الفواتير والكميات المنصرفة (لكل الفروع أو للفرع المختار)
    $invSql = "SELECT 
                    i.id AS invoice_id,
                    i.invoice_number,
                    i.location_id,
                    i.created_at AS invoice_date,
                    i.status AS invoice_status,
                    l.name AS location_name,
                    u.name AS user_name,
                    COALESCE(c.name, 'عميل نقدي / عابر') AS customer_name,
                    c.phone AS customer_phone,
                    il.id AS line_id,
                    il.line_type,
                    il.description AS line_description,
                    COALESCE(ilc.quantity, il.quantity) AS item_qty,
                    il.unit_price,
                    il.line_total,
                    ilc.unit_cost
               FROM invoice_lines il
               JOIN invoices i ON i.id = il.invoice_id
               JOIN locations l ON l.id = i.location_id
               JOIN users u ON u.id = i.user_id
               LEFT JOIN customers c ON c.id = i.customer_id
               LEFT JOIN invoice_line_components ilc 
                   ON ilc.invoice_line_id = il.id AND ilc.component_product_id = ?
               WHERE (ilc.component_product_id = ? OR (il.product_id = ? AND ilc.id IS NULL))";
    
    $params = [$targetId, $targetId, $targetId];
    if ($locationId !== null) {
        $invSql .= " AND i.location_id = ?";
        $params[] = $locationId;
    }
    $invSql .= " ORDER BY i.created_at DESC, il.id DESC LIMIT 300";
    
    $stmtInv = $db->prepare($invSql);
    $stmtInv->execute($params);
    $invoices = $stmtInv->fetchAll();

    foreach ($invoices as $inv) {
        $qtyVal = (float)$inv['item_qty'];
        $isCompleted = ($inv['invoice_status'] === 'completed');

        if ($isCompleted) {
            $allSold += $qtyVal;
            $totalRevenue += (float)$inv['line_total'];
            $uniqueInvoiceIds[$inv['invoice_id']] = true;

            if ($inv['line_type'] === 'product') {
                $directSold += $qtyVal;
            } else {
                $mixSold += $qtyVal;
            }

            if ($locationId !== null && (int)$inv['location_id'] === $locationId) {
                $branchSold += $qtyVal;
            }
        }
    }
}

$typeNames = [
    'perfume_gram' => 'عطر / زيت بالجرام',
    'bottle'       => 'زجاجة عطر',
    'fixed'        => 'منتج جاهز',
    'recipe'       => 'تركيبة جاهزة'
];
?>

<style>
.trace-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 5px;
}

.search-panel {
    background: var(--surface, #ffffff);
    border: 1.5px solid var(--line, #e2e8f0);
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.search-grid {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input-box {
    flex: 1;
    min-width: 280px;
    position: relative;
}

.search-input-box input {
    width: 100%;
    padding: 11px 16px;
    font-size: 14px;
    border: 1.5px solid var(--line, #cbd5e1);
    border-radius: 10px;
    background: var(--field-bg, #ffffff);
    color: var(--ink, #0f172a);
    outline: none;
}

.search-input-box input:focus {
    border-color: var(--primary, #b98418);
    box-shadow: 0 0 0 3px rgba(185, 132, 24, 0.15);
}

.select-box select {
    padding: 11px 14px;
    border-radius: 10px;
    border: 1.5px solid var(--line, #cbd5e1);
    font-size: 13.5px;
    font-weight: 600;
    background: var(--surface, #ffffff);
    color: var(--ink, #0f172a);
    cursor: pointer;
}

/* بطاقات الإحصائيات (الأرصدة والمبيعات) */
.kpi-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 14px;
}

.kpi-box {
    background: var(--surface, #ffffff);
    border: 1.5px solid var(--line, #e2e8f0);
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.kpi-box.primary-stock { border-right: 5px solid #b98418; }
.kpi-box.all-stock { border-right: 5px solid #0284c7; }
.kpi-box.sold-stats { border-right: 5px solid #dc2626; }

.kpi-header {
    font-size: 12px;
    font-weight: 700;
    color: var(--muted, #64748b);
    margin-bottom: 6px;
    display: flex;
    justify-content: space-between;
}

.kpi-big-num {
    font-size: 28px;
    font-weight: 900;
    margin-bottom: 6px;
}

.kpi-details {
    font-size: 12px;
    color: var(--muted, #64748b);
    line-height: 1.5;
}

.kpi-details strong {
    color: var(--ink, #0f172a);
}

.location-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--surface-soft, #f8fafc);
    border: 1px solid var(--line, #e2e8f0);
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 11.5px;
    margin: 2px 4px 2px 0;
}

.qty-badge {
    font-size: 12.5px;
    font-weight: 900;
    color: #b91c1c;
    background: #fee2e2;
    padding: 2px 8px;
    border-radius: 6px;
    display: inline-block;
}

/* جدول النتائج المتطابقة عند البحث */
.matches-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.matches-table th {
    background: var(--surface-soft, #f8fafc);
    padding: 10px 14px;
    text-align: right;
    border-bottom: 1.5px solid var(--line, #e2e8f0);
    font-weight: 700;
}

.matches-table td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--line, #e2e8f0);
    vertical-align: middle;
}
</style>

<div class="trace-page">
    <section class="page-head" style="margin: 0;">
        <div>
            <h2>📊 كشف حركة ومبيعات صنف</h2>
            <p>تقرير تفصيلي لمبيعات أي صنف أو زيت عطر بالجرام: <strong>باقي منه قد إيه بالفرع وبكل الفروع، طلع منه قد إيه، وقائمة الفواتير</strong>.</p>
        </div>
    </section>

    <!-- صندوق البحث -->
    <div class="search-panel">
        <form method="get" action="index.php">
            <input type="hidden" name="r" value="product_trace">
            <div class="search-grid">
                <div class="search-input-box">
                    <input type="text" name="q" value="<?= e($query) ?>" 
                           list="products-autocomplete-list" 
                           placeholder="اكتب اسم الصنف أو الزيت، أو امسح الباركود..." 
                           autofocus autocomplete="off">
                    <datalist id="products-autocomplete-list">
                        <?php foreach ($allProductsList as $ap): ?>
                            <option value="<?= e($ap['name']) ?>"><?= !empty($ap['barcode']) ? ' | ' . e($ap['barcode']) : '' ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <?php if ($userLocationId === null): ?>
                    <div class="select-box">
                        <select name="location_id">
                            <option value="all" <?= $locationId === null ? 'selected' : '' ?>>عرض: كل الفروع والمستودعات</option>
                            <?php foreach ($locations as $l): ?>
                                <option value="<?= e($l['id']) ?>" <?= $locationId === (int)$l['id'] ? 'selected' : '' ?>>فرع: <?= e($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn primary" style="font-weight: 800; padding: 11px 24px; white-space: nowrap;">
                    🔎 عرض الكشف
                </button>

                <?php if ($searched): ?>
                    <a href="index.php?r=product_trace" class="btn" style="padding: 11px 16px;">تفريغ البحث</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- جدول اختيار الصنف إذا كان هناك أكثر من نتيجة مطابقة -->
    <?php if (!empty($multipleMatches)): ?>
        <div style="background: var(--surface, #ffffff); border: 1.5px solid #f59e0b; border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.1);">
            <div style="font-weight: 800; font-size: 14px; color: #92400e; margin-bottom: 12px;">
                ⚠️ يوجد أكثر من صنف يتطابق مع بحثك عن "<?= e($query) ?>". اختر الصنف الدقيق الذي ترغب في عرض مبيعاته وفواتيره:
            </div>
            <div style="overflow-x: auto;">
                <table class="matches-table">
                    <thead>
                        <tr>
                            <th>اسم الصنف</th>
                            <th>النوع</th>
                            <th>الباركود</th>
                            <th>الكوتة / الدرجة</th>
                            <th style="text-align: center;">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($multipleMatches as $m): ?>
                            <tr>
                                <td><strong style="color: var(--ink, #0f172a);"><?= e($m['name']) ?></strong></td>
                                <td><span class="badge"><?= e($typeNames[$m['type']] ?? $m['type']) ?></span></td>
                                <td><code><?= e($m['barcode'] ?: '-') ?></code></td>
                                <td><?= e($m['quality_grade'] ?: '-') ?></td>
                                <td style="text-align: center;">
                                    <a class="btn small primary" href="index.php?r=product_trace&product_id=<?= (int)$m['id'] ?><?= $locationId !== null ? '&location_id=' . $locationId : '' ?>" style="font-weight: 800;">
                                        📊 عرض مبيعات وفواتير هذا الصنف
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- تنبيه عند عدم العثور على أي صنف -->
    <?php if ($searched && !$product && empty($multipleMatches)): ?>
        <div class="alert danger" style="padding: 16px 20px; font-size: 13.5px;">
            ⚠️ لم يتم العثور على أي صنف يطابق البحث: "<strong><?= e($query) ?></strong>". يرجى اختيار اسم الصنف بدقة من القائمة المقترحة.
        </div>
    <?php endif; ?>

    <!-- تفاصيل الصنف والرصيد والفواتير عند تحديد الصنف -->
    <?php if ($product): ?>
        <!-- معلومات الصنف الأساسية -->
        <div style="background: var(--surface, #ffffff); border: 1.5px solid var(--line, #e2e8f0); border-radius: 14px; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div>
                <h3 style="margin: 0 0 4px 0; font-size: 18px; font-weight: 900; color: var(--ink, #0f172a);">
                    <?= e($product['name']) ?>
                </h3>
                <div style="font-size: 12.5px; color: var(--muted, #64748b);">
                    نوع الصنف: <strong><?= e($typeNames[$product['type']] ?? $product['type']) ?></strong>
                    <?php if (!empty($product['barcode'])): ?>
                        | الباركود: <code><?= e($product['barcode']) ?></code>
                    <?php endif; ?>
                    <?php if (!empty($product['quality_grade'])): ?>
                        | الدرجة/الكوتة: <strong><?= e($product['quality_grade']) ?></strong>
                    <?php endif; ?>
                    | سعر البيع: <strong><?= money($product['type'] === 'perfume_gram' ? ($product['price_per_gram'] ?: $product['sale_price']) : $product['sale_price']) ?></strong>
                </div>
            </div>
            <div>
                <button type="button" class="btn small" onclick="window.print();">🖨️ طباعة الكشف</button>
            </div>
        </div>

        <!-- بطاقات الـ KPI الثلاثة (رصيد الفرع + رصيد كل الفروع + المنصرف) -->
        <div class="kpi-row">
            <!-- 1. الرصيد في هذا الفرع -->
            <?php if ($hasBranchStock): ?>
                <div class="kpi-box primary-stock">
                    <div class="kpi-header">
                        <span>الرصيد المتبقي في (<?= e($selectedLocationName) ?>):</span>
                        <span>📍 فرعي</span>
                    </div>
                    <div class="kpi-big-num" style="color: #b98418;">
                        <?= qty($currentBranchStock) ?> <span style="font-size: 15px; font-weight: 700;"><?= e($unitLabel) ?></span>
                    </div>
                    <div class="kpi-details">
                        هذا هو الرصيد الفعلي المتوفر حالياً بالفرع للبيع المباشر والخلطات.
                    </div>
                </div>
            <?php endif; ?>

            <!-- 2. إجمالي الرصيد في كل الفروع والمخازن -->
            <div class="kpi-box all-stock">
                <div class="kpi-header">
                    <span>إجمالي الرصيد في كل الفروع والمخازن:</span>
                    <span>🌐 كلي</span>
                </div>
                <div class="kpi-big-num" style="color: #0284c7;">
                    <?= qty($totalStockAll) ?> <span style="font-size: 15px; font-weight: 700;"><?= e($unitLabel) ?></span>
                </div>
                <div class="kpi-details" style="margin-top: 6px;">
                    <div style="font-weight: 700; margin-bottom: 4px; color: var(--ink, #0f172a);">توزيع الرصيد حسب الموقع:</div>
                    <?php foreach ($stockList as $stk): ?>
                        <span class="location-pill">
                            <?= e($stk['name']) ?>: <strong style="color: <?= (float)$stk['qty'] > 0 ? '#0284c7' : '#dc2626' ?>;"><?= qty($stk['qty']) ?> <?= e($unitLabel) ?></strong>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- 3. إجمالي المنصرف والمباع -->
            <div class="kpi-box sold-stats">
                <div class="kpi-header">
                    <span>إجمالي المباع والمنصرف بالفواتير:</span>
                    <span>🧾 مبيعات</span>
                </div>
                <div class="kpi-big-num" style="color: #dc2626;">
                    <?= qty($allSold) ?> <span style="font-size: 15px; font-weight: 700;"><?= e($unitLabel) ?></span>
                </div>
                <div class="kpi-details">
                    بيع مباشر: <strong><?= qty($directSold) ?> <?= e($unitLabel) ?></strong> | داخل خلطات عطور: <strong><?= qty($mixSold) ?> <?= e($unitLabel) ?></strong>
                </div>
                <div class="kpi-details" style="margin-top: 4px;">
                    إجمالي القيمة: <strong style="color: #15803d;"><?= money($totalRevenue) ?></strong> | خرج في: <strong><?= count($uniqueInvoiceIds) ?> فاتورة</strong>
                </div>
            </div>
        </div>

        <!-- جدول الفواتير التفصيلي -->
        <div style="background: var(--surface, #ffffff); border: 1.5px solid var(--line, #e2e8f0); border-radius: 14px; padding: 18px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 14px; flex-wrap: wrap;">
                <h3 style="margin: 0; font-size: 15px; font-weight: 800; color: var(--ink, #0f172a);">
                    🧾 الفواتير التي خرج فيها الصنف (<?= count($invoices) ?> فاتورة)
                </h3>
                <div>
                    <input type="text" id="trace-table-filter" placeholder="🔍 تصفية بالعميل أو الكاشير أو رقم الفاتورة..." 
                           onkeyup="filterInvoiceRows(this.value)"
                           style="padding: 7px 14px; font-size: 12.5px; border: 1.5px solid var(--line, #cbd5e1); border-radius: 8px; min-width: 260px;">
                </div>
            </div>

            <?php if (empty($invoices)): ?>
                <div class="muted" style="text-align: center; padding: 35px 20px;">
                    لم تخرج أي مبيعات لهذا الصنف في فواتير بعد.
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="branch-table" id="invoices-trace-table" style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                        <thead>
                            <tr style="background: var(--surface-soft, #f8fafc);">
                                <th style="padding: 10px 12px; text-align: right;">#</th>
                                <th style="padding: 10px 12px; text-align: right;">رقم الفاتورة</th>
                                <th style="padding: 10px 12px; text-align: right;">التاريخ والوقت</th>
                                <th style="padding: 10px 12px; text-align: right;">الفرع</th>
                                <th style="padding: 10px 12px; text-align: right;">الكاشير</th>
                                <th style="padding: 10px 12px; text-align: right;">العميل</th>
                                <th style="padding: 10px 12px; text-align: center;">الكمية المنصرفة</th>
                                <th style="padding: 10px 12px; text-align: right;">طبيعة الصرف</th>
                                <th style="padding: 10px 12px; text-align: right;">سعر الفاتورة</th>
                                <th style="padding: 10px 12px; text-align: center;">معاينة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $idx => $inv): 
                                $isVoid = ($inv['invoice_status'] === 'void_future');
                            ?>
                                <tr style="<?= $isVoid ? 'opacity: 0.6;' : '' ?>">
                                    <td style="padding: 10px 12px;"><?= $idx + 1 ?></td>
                                    <td style="padding: 10px 12px;">
                                        <a href="index.php?r=invoice_view&id=<?= (int)$inv['invoice_id'] ?>" target="_blank" style="font-weight: 800; color: #b45309; text-decoration: underline;">
                                            <?= e($inv['invoice_number']) ?>
                                        </a>
                                        <?php if ($isVoid): ?>
                                            <span class="badge danger" style="font-size: 10px;">ملغاة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px 12px; white-space: nowrap;"><?= e(format_datetime($inv['invoice_date'])) ?></td>
                                    <td style="padding: 10px 12px;"><?= e($inv['location_name']) ?></td>
                                    <td style="padding: 10px 12px;"><?= e($inv['user_name']) ?></td>
                                    <td style="padding: 10px 12px;">
                                        <strong><?= e($inv['customer_name']) ?></strong>
                                        <?php if (!empty($inv['customer_phone'])): ?>
                                            <br><small style="color: var(--muted, #64748b); font-size: 10.5px;"><?= e($inv['customer_phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px 12px; text-align: center;">
                                        <span class="qty-badge"><?= e(qty($inv['item_qty'])) ?> <?= e($unitLabel) ?></span>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <?php if ($inv['line_type'] === 'product'): ?>
                                            <span class="badge" style="background: #e0e7ff; color: #3730a3; font-size: 10.5px;">بيع مباشر</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fef3c7; color: #92400e; font-size: 10.5px;">داخل خلطة عطر</span>
                                        <?php endif; ?>
                                        <div style="font-size: 11px; color: var(--muted, #64748b); margin-top: 3px; max-width: 280px; line-height: 1.3;">
                                            <?= e($inv['line_description']) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 10px 12px; font-weight: 800;"><?= money($inv['line_total']) ?></td>
                                    <td style="padding: 10px 12px; text-align: center;">
                                        <a class="btn small" href="index.php?r=invoice_view&id=<?= (int)$inv['invoice_id'] ?>" target="_blank" title="عرض الفاتورة">👁️ فتح</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function filterInvoiceRows(query) {
    const q = query.trim().toLowerCase();
    const rows = document.querySelectorAll('#invoices-trace-table tbody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}
</script>
