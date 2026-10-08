<?php
$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'type' => $_GET['type'] ?? '',
    'unit' => $_GET['unit'] ?? '',
    'perfume_family' => $_GET['perfume_family'] ?? '',
    'quality_grade' => $_GET['quality_grade'] ?? '',
    'size_ml' => $_GET['size_ml'] ?? '',
    'barcode_status' => $_GET['barcode_status'] ?? '',
];
$products = product_rows($filters);
$typeLabels = product_type_labels();
$familyLabels = perfume_family_labels();
$qualityLabels = quality_grade_labels();
?>
<section class="page-head">
    <div>
        <h2>المنتجات</h2>
        <p>عرض وفلترة المنتجات. كل حجم أو كوتة يعتبر منتج منفصل وله باركود خاص.</p>
    </div>
    <?php if (has_permission('products_add')): ?>
        <a class="btn primary" href="index.php?r=product_create">+ إضافة منتج جديد</a>
    <?php endif; ?>
</section>

<form class="panel product-filter-grid" method="get">
    <input type="hidden" name="r" value="products">
    <label>بحث<input name="q" value="<?= e($filters['q']) ?>" placeholder="اسم / باركود"></label>
    <label>النوع<select name="type"><option value="">الكل</option><?php foreach ($typeLabels as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['type'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label>الوحدة<select name="unit"><option value="">الكل</option><option value="unit" <?= $filters['unit'] === 'unit' ? 'selected' : '' ?>>قطعة</option><option value="gram" <?= $filters['unit'] === 'gram' ? 'selected' : '' ?>>جرام</option></select></label>
    <label>عائلة العطر<select name="perfume_family"><option value="">الكل</option><?php foreach ($familyLabels as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['perfume_family'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label>الكوتة<select name="quality_grade"><option value="">الكل</option><?php foreach ($qualityLabels as $v => $l): ?><option value="<?= e($v) ?>" <?= $filters['quality_grade'] === $v ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    <label>الحجم ml<input name="size_ml" type="number" step="1" min="0" value="<?= e($filters['size_ml']) ?>" placeholder="مثال 100"></label>
    <label>الباركود<select name="barcode_status"><option value="">الكل</option><option value="with_barcode" <?= $filters['barcode_status'] === 'with_barcode' ? 'selected' : '' ?>>له باركود</option><option value="without_barcode" <?= $filters['barcode_status'] === 'without_barcode' ? 'selected' : '' ?>>بدون باركود</option></select></label>
    <div style="display:flex; gap:8px; align-items:end;"><button class="btn primary">تصفية</button><a class="btn" href="index.php?r=products">إعادة ضبط</a></div>
</form>

<div class="panel product-list-panel">
    <table>
        <thead><tr><th>المنتج</th><th>النوع</th><th>الوحدة</th><th>العائلة</th><th>الكوتة</th><th>الحجم</th><th>السعر</th><th>حد المخزون</th><th>الباركود</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php if (!$products): ?>
            <tr><td colspan="10" class="muted" style="text-align:center; padding:20px;">لا توجد منتجات مطابقة للفلاتر.</td></tr>
        <?php else: ?>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td><strong><?= e($p['name']) ?></strong></td>
                    <td><span class="badge"><?= e(product_type_label($p['type'])) ?></span></td>
                    <td><?= e(product_unit_label($p['unit'])) ?></td>
                    <td><?= $p['perfume_family'] ? e(perfume_family_label($p['perfume_family'])) : '-' ?></td>
                    <td><?= e($p['quality_grade'] ?: '-') ?></td>
                    <td><?= $p['size_ml'] ? e((int)$p['size_ml'] . 'ml') : '-' ?></td>
                    <td><?= money($p['sale_price']) ?></td>
                    <td><?= e(qty($p['min_stock'] ?? 0)) ?></td>
                    <td><code><?= e($p['barcode'] ?: '-') ?></code></td>
                    <td class="actions">
                        <?php if (has_permission('products_edit')): ?><a class="btn small" href="index.php?r=product_edit&id=<?= e($p['id']) ?>">تعديل</a><?php endif; ?>
                        <?php if (has_permission('products_delete')): ?><form method="post" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المنتج؟')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($p['id']) ?>"><button class="btn small danger">حذف</button></form><?php endif; ?>
                        <a class="btn small primary" href="index.php?r=print_barcode&id=<?= e($p['id']) ?>">طباعة</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
