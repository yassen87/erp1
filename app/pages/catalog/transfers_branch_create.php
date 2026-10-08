<?php
$userLocationId = current_user_location_id();
$allLocations = all_locations();
$branches = array_values(array_filter($allLocations, fn($l) => $l['type'] === 'branch'));
$products = all_products();
$transfer = null;
$items = [];
$editMode = false;
if (!empty($_GET['edit'])) {
    $transfer = get_transfer((int) $_GET['edit']);
    if ($transfer && $transfer['status'] === 'pending') {
        $editMode = true;
        $items = get_transfer_items((int) $transfer['id']);
    } else {
        $transfer = null;
    }
}
?>
<section class="page-head">
    <div>
        <h2>🔄 إنشاء تحويل بين الفروع</h2>
        <p>أنشئ تحويل منتج بين فرع وآخر من هنا.</p>
    </div>
    <div>
        <a class="btn" href="index.php?r=transfers_branch">رجوع إلى سجل التحويلات</a>
    </div>
</section>

<form class="panel" id="transfer-form" method="post" action="index.php?r=transfers_branch">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" id="transfer-action" value="<?= $editMode ? 'update' : '' ?>">
    <input type="hidden" name="transfer_id" id="transfer-id" value="<?= $editMode ? e($transfer['id']) : '' ?>">

    <div class="grid-form" style="margin-bottom: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
        <label>الفرع المرسل
            <?php if ($userLocationId !== null): ?>
                <select name="from_location_id" id="from_location_id" disabled>
                    <?php foreach ($branches as $l): ?>
                        <option value="<?= e($l['id']) ?>" <?= (int)$userLocationId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="from_location_id" value="<?= e($userLocationId) ?>">
            <?php else: ?>
                <select name="from_location_id" id="from_location_id" required>
                    <?php foreach ($branches as $l): ?>
                        <option value="<?= e($l['id']) ?>" <?= $editMode && (int)$transfer['from_location_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </label>
        <label>الفرع المستلم
            <select name="to_location_id" id="to_location_id" required>
                <?php foreach ($branches as $l): ?>
                    <?php if ($userLocationId === null || (int)$l['id'] !== (int)$userLocationId): ?>
                        <option value="<?= e($l['id']) ?>" <?= $editMode && (int)$transfer['to_location_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>
        <label>اسم المرسل
            <input name="sender_name" id="sender_name" placeholder="اسم الشخص المرسل..." value="<?= $editMode ? e($transfer['sender_name']) : '' ?>">
        </label>
        <label>اسم المستلم
            <input name="receiver_name" id="receiver_name" placeholder="اسم الشخص المستلم..." value="<?= $editMode ? e($transfer['receiver_name']) : '' ?>">
        </label>
        <label>تاريخ التحويل
            <input name="transfer_date" id="transfer_date" type="date" value="<?= e($editMode ? $transfer['transfer_date'] : date('Y-m-d')) ?>" required>
        </label>
        <label style="grid-column: span 2;">ملاحظات الشحنة
            <input name="notes" id="transfer-notes" placeholder="تفاصيل إضافية عن شحنة التحويل..." value="<?= $editMode ? e($transfer['notes']) : '' ?>">
        </label>
    </div>

    <h3 style="margin-top: 14px; margin-bottom: 8px; font-size: 13.5px; border-bottom: 1px solid var(--line); padding-bottom: 4px;">الأصناف والكميات المراد نقلها</h3>

    <div style="display: flex; gap: 10px; align-items: end; margin-bottom: 16px; max-width: 600px;">
        <label style="flex: 1; display: block;">اختر صنفاً لإضافته
            <select id="transfer-product-select">
                <option value="">-- اختر صنفاً --</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> (<?= e($p['unit'] === 'gram' ? 'جرام' : 'قطعة') ?>)</option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="button" class="btn secondary" id="btn-add-transfer-item" style="height: 38px;">أضف للتحويل</button>
    </div>

    <table class="panel" style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
        <thead>
            <tr style="border-bottom: 2px solid var(--line); text-align: right;">
                <th style="padding: 10px;">الصنف</th>
                <th style="padding: 10px; width: 220px; text-align: center;">الكمية</th>
                <th style="padding: 10px; width: 100px; text-align: center;">إجراء</th>
            </tr>
        </thead>
        <tbody id="transfer-items-body">
            <?php if (empty($items)): ?>
                <tr id="empty-transfer-row">
                    <td colspan="3" class="muted" style="text-align: center; padding: 20px;">لم يتم إضافة أي أصناف بعد.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr data-product-id="<?= e($item['product_id']) ?>" style="border-bottom: 1px solid var(--line);">
                        <td style="padding: 10px; font-weight:600;">
                            <?= e($item['product_name']) ?>
                            <input type="hidden" name="product_id[]" value="<?= e($item['product_id']) ?>">
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <div style="display: inline-flex; gap: 4px; align-items: center;">
                                <button type="button" class="btn small" onclick="adjustQty(this, -1)">-</button>
                                <input type="number" name="quantity[]" value="<?= e((float)$item['quantity']) ?>" step="any" min="0.001" style="width: 100px; text-align: center; padding: 4px; border: 1px solid var(--line); border-radius:6px; outline:none;">
                                <button type="button" class="btn small" onclick="adjustQty(this, 1)">+</button>
                            </div>
                        </td>
                        <td style="padding: 10px; text-align: center;">
                            <button type="button" class="btn small danger" onclick="removeTransferRow(this)">حذف</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <button class="btn primary" id="btn-submit-transfer"><?= $editMode ? 'حفظ التعديلات' : 'إنشاء أمر التحويل' ?></button>
    <button type="button" class="btn secondary" id="btn-reset-transfer" style="margin-right: 10px;">إعادة تعيين النموذج</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('transfer-product-select');
    if (select) makeSelectSearchable(select);

    document.getElementById('btn-add-transfer-item')?.addEventListener('click', () => {
        const selectEl = document.getElementById('transfer-product-select');
        const productId = selectEl.value;
        if (!productId) return;

        const existingRow = document.querySelector(`#transfer-items-body tr[data-product-id="${productId}"]`);
        if (existingRow) {
            const qtyInput = existingRow.querySelector('input[name="quantity[]"]');
            qtyInput.value = (parseInt(qtyInput.value, 10) || 0) + 1;
            return;
        }

        const option = selectEl.options[selectEl.selectedIndex];
        const productName = option.textContent;

        const emptyRow = document.getElementById('empty-transfer-row');
        if (emptyRow) emptyRow.style.display = 'none';

        const tr = document.createElement('tr');
        tr.dataset.productId = productId;
        tr.style.borderBottom = '1px solid var(--line)';
        tr.innerHTML = `
            <td style="padding: 10px; font-weight:600;">
                ${productName}
                <input type="hidden" name="product_id[]" value="${productId}">
            </td>
            <td style="padding: 10px; text-align: center;">
                <div style="display: inline-flex; gap: 4px; align-items: center;">
                    <button type="button" class="btn small" onclick="adjustQty(this, -1)">-</button>
                    <input type="number" name="quantity[]" value="1" step="any" min="0.001" style="width: 100px; text-align: center; padding: 4px; border: 1px solid var(--line); border-radius:6px; outline:none;">
                    <button type="button" class="btn small" onclick="adjustQty(this, 1)">+</button>
                </div>
            </td>
            <td style="padding: 10px; text-align: center;">
                <button type="button" class="btn small danger" onclick="removeTransferRow(this)">حذف</button>
            </td>
        `;

        document.getElementById('transfer-items-body').appendChild(tr);
        selectEl.value = '';
        selectEl.dispatchEvent(new Event('change', { bubbles: true }));
    });

    document.getElementById('transfer-form')?.addEventListener('submit', (e) => {
        const body = document.getElementById('transfer-items-body');
        const rows = body.querySelectorAll('tr:not(#empty-transfer-row)');
        if (rows.length === 0) {
            e.preventDefault();
            alert('يرجى إضافة صنف واحد على الأقل.');
        }
    });

    document.getElementById('btn-reset-transfer')?.addEventListener('click', resetTransferForm);
});

function resetTransferForm() {
    document.getElementById('transfer-action').value = '';
    document.getElementById('transfer-id').value = '';
    document.getElementById('transfer-notes').value = '';
    document.getElementById('sender_name').value = '';
    document.getElementById('receiver_name').value = '';
    document.getElementById('transfer_date').value = '<?= e(date('Y-m-d')) ?>';
    document.getElementById('btn-submit-transfer').textContent = 'إنشاء أمر التحويل';
    document.getElementById('from_location_id').selectedIndex = 0;
    document.getElementById('to_location_id').selectedIndex = 0;
    document.getElementById('transfer-items-body').innerHTML = '<tr id="empty-transfer-row"><td colspan="3" class="muted" style="text-align: center; padding: 20px;">لم يتم إضافة أي أصناف بعد.</td></tr>';
}

function adjustQty(btn, delta) {
    const input = btn.parentNode.querySelector('input[name="quantity[]"]');
    let val = parseFloat(input.value) || 0;
    val = parseFloat((val + delta).toFixed(3));
    if (val < 0.001) val = 0.001;
    input.value = val;
}

function removeTransferRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    checkEmptyTransferTable();
}

function checkEmptyTransferTable() {
    const body = document.getElementById('transfer-items-body');
    const rows = body.querySelectorAll('tr:not(#empty-transfer-row)');
    const emptyRow = document.getElementById('empty-transfer-row');
    if (rows.length === 0) {
        if (emptyRow) emptyRow.style.display = '';
    } else {
        if (emptyRow) emptyRow.style.display = 'none';
    }
}
</script>
