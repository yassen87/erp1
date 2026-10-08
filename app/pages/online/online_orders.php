<?php
$orders = online_order_rows();
$customers = all_customers();
$regularProducts = array_values(array_filter(all_products(), fn ($p) => $p['type'] !== 'recipe'));
$recipeProducts = array_values(array_filter(all_products(), fn ($p) => $p['type'] === 'recipe'));
$statuses = ['new' => 'جديد', 'preparing' => 'قيد التجهيز', 'shipped' => 'تم الشحن', 'delivered' => 'تم التسليم', 'cancelled' => 'ملغي'];
$primaryStatuses = ['preparing' => 'قيد التجهيز', 'shipped' => 'تم الشحن', 'delivered' => 'تم التسليم', 'cancelled' => 'ملغي'];
$paymentLabels = payment_method_labels();

$editOrder = null;
$editItems = [];
if (!empty($_GET['edit_id']) && ctype_digit($_GET['edit_id'])) {
    $editOrder = find_online_order((int) $_GET['edit_id']);
    if ($editOrder) {
        $editItems = $editOrder['items'];
    }
}

function render_product_options(array $recipeProducts, array $regularProducts, int $selectedId = 0): string
{
    ob_start();
    ?>
    <option value="">اختر منتج أو تركيبة جاهزة</option>
    <?php if ($recipeProducts): ?>
        <optgroup label="تركيبات جاهزة">
            <?php foreach ($recipeProducts as $p): ?>
                <option value="<?= e($p['id']) ?>" <?= $selectedId === $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> - <?= money($p['sale_price']) ?></option>
            <?php endforeach; ?>
        </optgroup>
    <?php endif; ?>
    <optgroup label="منتجات">
        <?php foreach ($regularProducts as $p): ?>
            <option value="<?= e($p['id']) ?>" <?= $selectedId === $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?> - <?= money($p['sale_price']) ?></option>
        <?php endforeach; ?>
    </optgroup>
    <?php
    return ob_get_clean();
}

ob_start();
?>
<option value="">اختر منتج أو تركيبة جاهزة</option>
<?php if ($recipeProducts): ?><optgroup label="تركيبات جاهزة"><?php foreach ($recipeProducts as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> - <?= money($p['sale_price']) ?></option><?php endforeach; ?></optgroup><?php endif; ?><optgroup label="منتجات"><?php foreach ($regularProducts as $p): ?><option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> - <?= money($p['sale_price']) ?></option><?php endforeach; ?></optgroup>
<?php
$productOptionsHtml = ob_get_clean();
?>
<section class="page-head"><h2>طلبات الأونلاين</h2><p>إنشاء ومتابعة طلبات الأونلاين بحالاتها وربطها بسجل العملاء.</p></section>
<form class="panel" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php if ($editOrder): ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="order_id" value="<?= e($editOrder['id']) ?>">
    <?php endif; ?>
    <div class="grid-form">
        <label>العميل<select name="customer_id" required><option value="">اختر عميل</option><?php foreach ($customers as $c): ?><option value="<?= e($c['id']) ?>" <?= $editOrder && $editOrder['customer_id'] === $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?> - <?= e($c['phone']) ?></option><?php endforeach; ?></select></label>
        <label>طريقة الدفع<select name="payment_method"><option value="cash" <?= (!$editOrder || $editOrder['payment_method'] === 'cash') ? 'selected' : '' ?>>كاش</option><option value="instapay" <?= $editOrder && $editOrder['payment_method'] === 'instapay' ? 'selected' : '' ?>>انستا باي</option><option value="vodafone_cash" <?= $editOrder && $editOrder['payment_method'] === 'vodafone_cash' ? 'selected' : '' ?>>فودافون كاش</option></select><small class="muted">إنستا: <?= e(payment_destination_label('instapay') ?: '-') ?> | فودافون: <?= e(payment_destination_label('vodafone_cash') ?: '-') ?></small></label>
        <label>حالة الطلب<select name="status"><option value="preparing" <?= (!$editOrder || $editOrder['status'] === 'preparing') ? 'selected' : '' ?>>قيد التجهيز</option><option value="shipped" <?= $editOrder && $editOrder['status'] === 'shipped' ? 'selected' : '' ?>>تم الشحن</option><option value="delivered" <?= $editOrder && $editOrder['status'] === 'delivered' ? 'selected' : '' ?>>تم التسلم</option><option value="cancelled" <?= $editOrder && $editOrder['status'] === 'cancelled' ? 'selected' : '' ?>>ملغي</option></select></label>
        <label>ملاحظات<input name="notes" value="<?= e($editOrder['notes'] ?? '') ?>"></label>
    </div>
    <h3>المنتجات</h3>
    <div id="online-order-items">
        <?php if ($editItems): ?>
            <?php foreach ($editItems as $item): ?>
                <div class="line-grid online-order-item-row">
                    <select name="product_id[]"><?= render_product_options($recipeProducts, $regularProducts, (int) $item['product_id']) ?></select>
                    <input name="quantity[]" type="number" step="1" min="1" placeholder="كمية" value="<?= e($item['quantity']) ?>">
                    <input name="unit_price[]" type="number" step="1" min="0" placeholder="سعر" value="<?= e($item['unit_price']) ?>">
                    <button type="button" class="btn small danger" onclick="removeOnlineOrderRow(this)">حذف</button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="line-grid online-order-item-row">
                <select name="product_id[]"><?= render_product_options($recipeProducts, $regularProducts) ?></select>
                <input name="quantity[]" type="number" step="1" min="1" placeholder="كمية">
                <input name="unit_price[]" type="number" step="1" min="0" placeholder="سعر">
                <button type="button" class="btn small danger" onclick="removeOnlineOrderRow(this)">حذف</button>
            </div>
        <?php endif; ?>
    </div>
    <div style="margin-bottom: 12px;">
        <button type="button" class="btn secondary small" id="add-online-order-item">إضافة صنف</button>
    </div>
    <div class="grid-form">
        <label>نوع الخصم<select name="discount_type"><option value="" <?= empty($editOrder['discount_type']) ? 'selected' : '' ?>>بدون خصم</option><option value="amount" <?= $editOrder && $editOrder['discount_type'] === 'amount' ? 'selected' : '' ?>>مبلغ</option><option value="percent" <?= $editOrder && $editOrder['discount_type'] === 'percent' ? 'selected' : '' ?>>نسبة %</option></select></label>
        <label>قيمة الخصم<input name="discount_value" type="number" step="1" min="0" value="<?= e($editOrder['discount_value'] ?? 0) ?>"></label>
    </div>
    <button class="btn primary"><?= $editOrder ? 'تحديث طلب الأونلاين' : 'إنشاء طلب أونلاين' ?></button>
    <?php if ($editOrder): ?>
        <a class="btn danger" href="index.php?r=online_orders" style="margin-left: 8px; display: inline-flex; align-items: center; justify-content: center;">إلغاء التعديل</a>
    <?php endif; ?>
</form>
<div class="panel"><table><thead><tr><th>رقم الطلب</th><th>العميل</th><th>الهاتف</th><th>الحالة</th><th>الإجمالي</th><th>الدفع</th><th>التاريخ</th><th>تحديث</th></tr></thead><tbody>
<?php foreach ($orders as $o): ?><tr><td><?= e($o['order_number']) ?></td><td><?= e($o['customer_name']) ?></td><td><?= e($o['phone']) ?></td><td><span class="badge"><?= e($statuses[$o['status']] ?? $o['status']) ?></span></td><td><?= money($o['total']) ?></td><td><?= e($paymentLabels[$o['payment_method']] ?? $o['payment_method']) ?><?php $dest = payment_destination_label((string) $o['payment_method']); if ($dest): ?><br><small class="muted"><?= e($dest) ?></small><?php endif; ?></td><td><?= e($o['created_at']) ?></td><td><form class="inline pay" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="order_id" value="<?= e($o['id']) ?>"><select name="status"><?php foreach ($primaryStatuses as $code => $label): ?><option value="<?= e($code) ?>" <?= $o['status'] === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="btn small">حفظ</button></form>
            <div style="margin-top: 6px; display: flex; gap: 6px;">
                <a class="btn small secondary" href="index.php?r=online_orders&edit_id=<?= e($o['id']) ?>">تعديل</a>
                <form class="inline" method="post" onsubmit="return confirm('هل أنت متأكد من حذف هذا الطلب؟');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="order_id" value="<?= e($o['id']) ?>">
                    <button class="btn small danger">حذف</button>
                </form>
            </div>
        </td></tr><?php endforeach; ?>
</tbody></table></div>
<script>
(function() {
    const productOptions = <?= json_encode($productOptionsHtml) ?>;

    function makeOnlineOrderRow() {
        const row = document.createElement('div');
        row.className = 'line-grid online-order-item-row';
        row.innerHTML = `
            <select name="product_id[]">${productOptions}</select>
            <input name="quantity[]" type="number" step="1" min="1" placeholder="كمية">
            <input name="unit_price[]" type="number" step="1" min="0" placeholder="سعر">
            <button type="button" class="btn small danger" onclick="removeOnlineOrderRow(this)">حذف</button>
        `;
        const select = row.querySelector('select');
        makeSelectSearchable(select);
        return row;
    }

    window.addOnlineOrderRow = function() {
        document.getElementById('online-order-items').appendChild(makeOnlineOrderRow());
    };

    window.removeOnlineOrderRow = function(button) {
        const row = button.closest('.online-order-item-row');
        if (!row) {
            return;
        }
        const container = document.getElementById('online-order-items');
        row.remove();
        if (container.querySelectorAll('.online-order-item-row').length === 0) {
            container.appendChild(makeOnlineOrderRow());
        }
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#online-order-items select[name="product_id[]"]').forEach(makeSelectSearchable);
        const addButton = document.getElementById('add-online-order-item');
        if (addButton) {
            addButton.addEventListener('click', window.addOnlineOrderRow);
        }
    });
})();
</script>
