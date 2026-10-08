<?php
$product = find_product((int)($_GET['id'] ?? 0));
if (!$product) { echo '<div class="alert danger">المنتج غير موجود.</div>'; return; }
$familyLabels  = perfume_family_labels();
$qualityLabels = quality_grade_labels();
$typeLabels    = array_filter(product_type_labels(), fn($k) => in_array($k, ['bottle','perfume_gram','fixed'], true), ARRAY_FILTER_USE_KEY);
?>
<section class="page-head"><div><h2>تعديل منتج</h2><p>يمكنك تعديل بيانات المنتج بما فيها نوعه.</p></div><a class="btn" href="index.php?r=products">رجوع للمنتجات</a></section>
<form class="panel grid-form product-form-advanced" method="post" id="product-edit-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e($product['id']) ?>">

    <label style="grid-column: span 2;">نوع المنتج
        <select name="type" id="edit-type-select">
            <?php foreach ($typeLabels as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= $product['type'] === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>اسم المنتج<input name="name" required value="<?= e($product['name']) ?>"></label>
    <label>باركود<input name="barcode" maxlength="13" value="<?= e($product['barcode'] ?? '') ?>"></label>

    <!-- حقول زجاجة / منتج جاهز -->
    <label class="edit-field-bottle edit-field-fixed">الحجم ml
        <input name="size_ml" type="number" step="1" min="0" placeholder="" value="<?= e($product['size_ml'] ?? '') ?>">
    </label>
    <label class="edit-field-bottle edit-field-fixed">سعر البيع
        <input name="sale_price" type="number" step="any" min="0" placeholder="0" value="<?= e((float)($product['sale_price'] ?? 0)) ?>">
    </label>
    <label class="edit-field-bottle edit-field-fixed">تكلفة الشراء
        <input name="cost_price" type="number" step="any" min="0" placeholder="" value="<?= $product['cost_price'] !== null ? e((float)$product['cost_price']) : '' ?>">
    </label>
    <label class="edit-field-bottle edit-field-fixed">حد تنبيه المخزون
        <input name="min_stock" type="number" step="any" min="0" placeholder="0" value="<?= e((float)($product['min_stock'] ?? 0)) ?>">
    </label>

    <!-- حقول عطر بالجرام -->
    <label class="edit-field-perfume_gram">عائلة العطر
        <select name="perfume_family">
            <?php foreach ($familyLabels as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= ($product['perfume_family'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="edit-field-perfume_gram">الكوتة
        <select name="quality_grade">
            <?php foreach ($qualityLabels as $v => $l): ?>
                <option value="<?= e($v) ?>" <?= ($product['quality_grade'] ?? '') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="edit-field-perfume_gram">سعر الجرام
        <input name="price_per_gram" type="number" step="any" min="0" placeholder="0" value="<?= e((float)($product['price_per_gram'] ?? 0)) ?>">
    </label>
    <label class="edit-field-perfume_gram">تكلفة الشراء
        <input name="cost_price_gram" type="number" step="any" min="0" placeholder="" value="<?= $product['cost_price'] !== null ? e((float)$product['cost_price']) : '' ?>">
    </label>
    <label class="edit-field-perfume_gram">حد تنبيه المخزون
        <input name="min_stock_gram" type="number" step="any" min="0" placeholder="0" value="<?= e((float)($product['min_stock'] ?? 0)) ?>">
    </label>

    <button class="btn primary" style="grid-column: span 2;">حفظ التعديل</button>
</form>

<script>
(function() {
    const typeSelect = document.getElementById('edit-type-select');
    if (!typeSelect) return;

    function toggleFields() {
        const t = typeSelect.value;
        // bottle / fixed fields
        document.querySelectorAll('.edit-field-bottle, .edit-field-fixed').forEach(el => {
            const show = el.classList.contains('edit-field-' + t);
            el.style.display = show ? '' : 'none';
            el.querySelectorAll('input, select').forEach(f => f.disabled = !show);
        });
        // perfume_gram fields
        document.querySelectorAll('.edit-field-perfume_gram').forEach(el => {
            const show = (t === 'perfume_gram');
            el.style.display = show ? '' : 'none';
            el.querySelectorAll('input, select').forEach(f => f.disabled = !show);
        });
    }

    typeSelect.addEventListener('change', toggleFields);
    toggleFields();
})();
</script>
