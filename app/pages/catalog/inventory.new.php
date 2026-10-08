<?php
$locations = stock_locations();
$userLocationId = current_user_location_id();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
}
$products = all_products();
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'location_id' => trim((string) ($_GET['location_id'] ?? '')),
    'product_id' => trim((string) ($_GET['product_id'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$additions = inventory_addition_rows($filters, $userLocationId);
$editMovement = null;
if (!empty($_GET['edit'])) {
    $editMovement = find_inventory_movement((int) $_GET['edit']);
}
?>
<section class="page-head">
    <div>
        <h2>إضافات المخزون المركزي</h2>
        <p>سجل إضافات المخزون منفصل مع فلاتر بحث وتحرير وحذف لكل إضافة.</p>
    </div>
    <?php if ($editMovement): ?>
        <a class="btn" href="index.php?r=inventory">إضافة جديدة</a>
    <?php endif; ?>
</section>

<form class="panel product-filter-grid" method="get" style="margin-bottom: 18px;">
    <input type="hidden" name="r" value="inventory">
    <label>بحث<input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="منتج، موقع، ملاحظة"></label>
    <label>الموقع<select name="location_id">
        <option value="">الكل</option>
        <?php foreach ($locations as $location): ?>
            <option value="<?= e($location['id']) ?>" <?= $filters['location_id'] === (string) $location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option>
        <?php endforeach; ?>
    </select></label>
    <label>الصنف<select name="product_id">
        <option value="">الكل</option>
        <?php foreach ($products as $product): ?>
            <option value="<?= e($product['id']) ?>" <?= $filters['product_id'] === (string) $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?></option>
        <?php endforeach; ?>
    </select></label>
    <label>من تاريخ<input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label>
    <label>إلى تاريخ<input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label>
    <div style="display:flex; gap:8px; align-items:end;">
        <button class="btn primary">تطبيق الفلاتر</button>
        <a class="btn" href="index.php?r=inventory">إعادة ضبط</a>
    </div>
</form>

<div class="panel">
    <h3><?= $editMovement ? 'تعديل إضافة مخزون' : 'إضافة مخزون جديد' ?></h3>
    <?php if (has_permission('inventory_adjust')): ?>
        <form class="product-filter-grid" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $editMovement ? 'update' : 'create' ?>">
            <?php if ($editMovement): ?>
                <input type="hidden" name="movement_id" value="<?= e($editMovement['id']) ?>">
            <?php endif; ?>

            <label>الموقع<select name="location_id" required>
                <?php foreach ($locations as $location): ?>
                    <option value="<?= e($location['id']) ?>" <?= $editMovement && $editMovement['location_id'] === $location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option>
                <?php endforeach; ?>
            </select></label>

            <label>الصنف<select id="inventory-product-select" name="product_id" required>
                <option value="">-- اختر صنفاً --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e($product['id']) ?>" data-sale="<?= e($product['sale_price']) ?>" data-cost="<?= e($product['cost_price'] ?? '') ?>" <?= $editMovement && $editMovement['product_id'] === $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?> (<?= e($product['unit'] === 'gram' ? 'جرام' : 'قطعة') ?>)</option>
                <?php endforeach; ?>
            </select></label>

            <label>الكمية<input name="quantity" type="number" step="1" min="1" value="<?= e($editMovement['quantity_delta'] ?? '') ?>" required></label>
            <label>سعر البيع<input id="sale-price-input" name="sale_price" type="number" step="1" min="0" value="<?= e($editMovement['sale_price'] ?? '') ?>" placeholder="سعر البيع"></label>
            <label>تكلفة الشراء<input id="cost-price-input" name="cost_price" type="number" step="1" min="0" value="<?= e($editMovement['cost_price'] ?? '') ?>" placeholder="تكلفة الشراء"></label>
            <label style="grid-column: span 2;">ملاحظة البند<textarea name="notes" rows="2"><?= e($editMovement['notes'] ?? 'إضافة مخزون') ?></textarea></label>

            <div style="display:flex; gap:8px; align-items:end;">
                <button class="btn primary" type="submit"><?= $editMovement ? 'حفظ التعديل' : 'حفظ الإضافة' ?></button>
                <?php if ($editMovement): ?>
                    <button class="btn danger" type="submit" name="action" value="delete" onclick="return confirm('هل تريد حذف هذه الإضافة؟')">حذف الإضافة</button>
                <?php endif; ?>
            </div>
            <p class="muted" id="product-price-notice">اختر المنتج لعرض السعر الحالي ويمكن تعديله قبل الحفظ.</p>
        </form>
    <?php else: ?>
        <p class="muted">ليس لديك صلاحية تسجيل أو تعديل إضافات المخزون.</p>
    <?php endif; ?>
</div>

<div class="panel">
    <h3>جدول إضافات المخزون</h3>
    <table>
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>الموقع</th>
                <th>الصنف</th>
                <th>الوحدة</th>
                <th>الكمية</th>
                <th>سعر البيع</th>
                <th>تكلفة الشراء</th>
                <th>الملاحظة</th>
                <th>المسؤول</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($additions)): ?>
                <tr>
                    <td colspan="10" class="muted" style="text-align:center; padding: 20px;">لا توجد إضافات مخزون مطابقة للفلاتر.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($additions as $addition): ?>
                    <tr>
                        <td><?= e($addition['created_at']) ?></td>
                        <td><?= e($addition['location_name']) ?></td>
                        <td><?= e($addition['product_name']) ?></td>
                        <td><?= e($addition['unit'] === 'gram' ? 'جرام' : 'قطعة') ?></td>
                        <td><?= e(qty($addition['quantity_delta'])) ?></td>
                        <td><?= money($addition['sale_price']) ?></td>
                        <td><?= money($addition['cost_price']) ?></td>
                        <td><?= e($addition['notes']) ?></td>
                        <td><?= e($addition['user_name']) ?></td>
                        <td class="actions">
                            <a class="btn small secondary" href="index.php?r=inventory&edit=<?= e($addition['id']) ?>">تعديل</a>
                            <form method="post" class="inline" onsubmit="return confirm('هل تريد حذف هذه الإضافة؟')">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="movement_id" value="<?= e($addition['id']) ?>">
                                <button class="btn small danger">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const productSelect = document.getElementById('inventory-product-select');
    const salePriceInput = document.getElementById('sale-price-input');
    const costPriceInput = document.getElementById('cost-price-input');
    const priceNotice = document.getElementById('product-price-notice');

    function fillPrices() {
        if (!productSelect) {
            return;
        }
        const option = productSelect.options[productSelect.selectedIndex];
        if (!option || !option.value) {
            salePriceInput.value = '';
            costPriceInput.value = '';
            priceNotice.textContent = 'اختر المنتج لعرض السعر الحالي.';
            return;
        }
        salePriceInput.value = option.dataset.sale ?? '';
        costPriceInput.value = option.dataset.cost ?? '';
        priceNotice.textContent = 'أسعار البيع والشراء محملة من بيانات المنتج ويمكن تعديلها قبل الحفظ.';
    }

    if (productSelect) {
        if (typeof makeSelectSearchable === 'function') {
            makeSelectSearchable(productSelect);
        }
        productSelect.addEventListener('change', fillPrices);
        fillPrices();
    }
});
</script>
