<?php
// تم نقل هذه الصفحة إلى: transfers_supply.php (توريد) و transfers_branch.php (تحويل بين الفروع)
header('Location: index.php?r=transfers_supply');
exit;
$locations = stock_locations();
$userLocationId = current_user_location_id();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId || $l['type'] !== 'online'));
}
$products = all_products();
$rows = transfers_rows($userLocationId);

$daysMap = [
    'Monday' => 'الإثنين',
    'Tuesday' => 'الثلاثاء',
    'Wednesday' => 'الأربعاء',
    'Thursday' => 'الخميس',
    'Friday' => 'الجمعة',
    'Saturday' => 'السبت',
    'Sunday' => 'الأحد',
];

$statusTranslations = [
    'pending' => '<span class="badge warning">قيد الانتظار</span>',
    'received' => '<span class="badge success">تم الاستلام</span>',
    'cancelled' => '<span class="badge danger">ملغي</span>'
];
?>
<section class="page-head">
    <div>
        <h2>تحويلات المخزون بين الفروع</h2>
        <p>إنشاء أوامر التحويل، الخصم من الموقع المرسل، ثم تأكيد الاستلام لإضافة الكمية للموقع المستلم.</p>
    </div>
</section>

<form class="panel" id="transfer-form" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" id="transfer-action" value="">
    <input type="hidden" name="transfer_id" id="transfer-id" value="">
    
    <div class="grid-form" style="margin-bottom: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
        <label>الموقع المرسل (مخزن المبدأ)
            <?php if ($userLocationId === null): ?>
            <select name="from_location_id" id="from_location_id">
                <?php foreach ($locations as $l): ?>
                    <option value="<?= e($l['id']) ?>"><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
                <select name="from_location_id" id="from_location_id" disabled>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= e($l['id']) ?>" <?= $userLocationId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="from_location_id" value="<?= $userLocationId ?>">
            <?php endif; ?>
        </label>
        <label>الموقع المستلم (الفرع الوجهة)
            <select name="to_location_id" id="to_location_id">
                <?php foreach ($locations as $l): ?>
                    <?php if ($userLocationId === null || $userLocationId !== (int)$l['id']): ?>
                        <option value="<?= e($l['id']) ?>"><?= e($l['name']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </label>
        <label>اسم المرسل
            <input name="sender_name" id="sender_name" placeholder="اسم الشخص المرسل...">
        </label>
        <label>اسم المستلم
            <input name="receiver_name" id="receiver_name" placeholder="اسم الشخص المستلم...">
        </label>
        <label>تاريخ التحويل
            <input name="transfer_date" id="transfer_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
        </label>
        <label style="grid-column: span 2;">ملاحظات الشحنة
            <input name="notes" id="transfer-notes" placeholder="تفاصيل إضافية عن شحنة التحويل...">
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
                <th style="padding: 10px; width: 220px; text-align: center;">الكمية المراد نقلها</th>
                <th style="padding: 10px; width: 100px; text-align: center;">إجراء</th>
            </tr>
        </thead>
        <tbody id="transfer-items-body">
            <tr id="empty-transfer-row">
                <td colspan="3" class="muted" style="text-align: center; padding: 20px;">لم يتم إضافة أي أصناف بعد. اختر صنفاً من الأعلى وأضفه للتحويل.</td>
            </tr>
        </tbody>
    </table>
    
    <button class="btn primary" id="btn-submit-transfer">إنشاء أمر التحويل المخزني</button>
    <button type="button" class="btn secondary" id="btn-reset-transfer" style="margin-left: 10px;">إعادة تعيين النموذج</button>
</form>

<div class="panel">
    <h3>سجل التحويلات السابقة</h3>
    <table>
        <thead>
            <tr>
                <th>رقم التحويل</th>
                <th>المصدر</th>
                <th>الوجهة</th>
                <th>حالة الشحنة</th>
                <th>اسم المرسل</th>
                <th>اسم المستلم</th>
                <th>المسؤول</th>
                <th>تاريخ التحويل</th>
                <th>تاريخ الإنشاء</th>
                <th>استلم بواسطة</th>
                <th>تاريخ الاستلام</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $t): ?>
                <?php $items = get_transfer_items($t['id']); ?>
                <tr data-transfer="<?= e(json_encode([
                    'id' => $t['id'],
                    'transfer_number' => $t['transfer_number'],
                    'from_location_id' => $t['from_location_id'],
                    'to_location_id' => $t['to_location_id'],
                    'sender_name' => $t['sender_name'],
                    'receiver_name' => $t['receiver_name'],
                    'transfer_date' => $t['transfer_date'],
                    'notes' => $t['notes'],
                    'items' => array_map(fn($item) => ['product_id' => $item['product_id'], 'quantity' => (float)$item['quantity'], 'name' => $item['product_name']], $items),
                ], JSON_UNESCAPED_UNICODE)) ?>">
                    <td><strong><?= e($t['transfer_number']) ?></strong></td>
                    <td><?= e($t['from_name']) ?></td>
                    <td><?= e($t['to_name']) ?></td>
                    <td><?= $statusTranslations[$t['status']] ?? e($t['status']) ?></td>
                    <td><?= e($t['sender_name'] ?: '-') ?></td>
                    <td><?= e($t['receiver_name'] ?: '-') ?></td>
                    <td><?= e($t['created_name']) ?></td>
                    <td><?= e(date('Y-m-d', strtotime($t['transfer_date']))) ?> <br><small><?= e($daysMap[date('l', strtotime($t['transfer_date']))] ?? date('l', strtotime($t['transfer_date']))) ?></small></td>
                    <td><?= e(format_datetime($t['created_at'])) ?></td>
                    <td><?= e($t['received_name'] ?? '-') ?></td>
                    <td><?= $t['received_at'] ? e(format_datetime($t['received_at'])) . '<br><small>' . e($daysMap[date('l', strtotime($t['received_at']))] ?? date('l', strtotime($t['received_at']))) . '</small>' : '<span class="muted">-</span>' ?></td>
                    <td>
                        <?php if ($t['status'] === 'pending'): ?>
                            <button type="button" class="btn small secondary" onclick="editTransfer(this.closest('tr'))">تعديل</button>
                            <form method="post" class="inline" onsubmit="return confirm('هل تريد إلغاء أمر التحويل وإعادة الكمية للمصدر؟')">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="transfer_id" value="<?= e($t['id']) ?>">
                                <button class="btn small danger">إلغاء</button>
                            </form>
                            <form method="post" class="inline" onsubmit="return confirm('هل تؤكد استلام هذه الشحنة وإضافتها لمخزونك؟')">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="receive">
                                <input type="hidden" name="transfer_id" value="<?= e($t['id']) ?>">
                                <button class="btn small success">تأكيد الاستلام</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('transfer-product-select');
    if (select) {
        makeSelectSearchable(select);
    }
    
    const btnAdd = document.getElementById('btn-add-transfer-item');
    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            const selectEl = document.getElementById('transfer-product-select');
            const productId = selectEl.value;
            if (!productId) return;
            
            // Check if already exists
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
            
            // Reset trigger selection
            selectEl.value = '';
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
    
    const form = document.getElementById('transfer-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            if (e.submitter && e.submitter.classList.contains('success')) {
                return;
            }
            const body = document.getElementById('transfer-items-body');
            const rows = body.querySelectorAll('tr:not(#empty-transfer-row)');
            if (rows.length === 0) {
                e.preventDefault();
                alert('يرجى إضافة صنف واحد على الأقل للتحويل.');
            }
        });
    }

    const btnReset = document.getElementById('btn-reset-transfer');
    if (btnReset) {
        btnReset.addEventListener('click', resetTransferForm);
    }
});

function editTransfer(row) {
    const data = row.dataset.transfer;
    if (!data) {
        return;
    }

    const transfer = JSON.parse(data);
    const form = document.getElementById('transfer-form');
    if (!form) return;

    document.getElementById('transfer-action').value = 'update';
    document.getElementById('transfer-id').value = transfer.id;
    document.getElementById('transfer-notes').value = transfer.notes || '';
    document.getElementById('sender_name').value = transfer.sender_name || '';
    document.getElementById('receiver_name').value = transfer.receiver_name || '';
    document.getElementById('transfer_date').value = transfer.transfer_date || '';
    const submitButton = document.getElementById('btn-submit-transfer');
    submitButton.textContent = 'حفظ التعديلات';

    const fromSelect = document.getElementById('from_location_id');
    if (fromSelect) {
        fromSelect.value = transfer.from_location_id;
    }
    const toSelect = document.getElementById('to_location_id');
    if (toSelect) {
        toSelect.value = transfer.to_location_id;
    }

    const body = document.getElementById('transfer-items-body');
    body.innerHTML = '';
    transfer.items.forEach((item) => {
        addTransferItem(item.product_id, item.name, item.quantity);
    });
    checkEmptyTransferTable();
}

function resetTransferForm() {
    document.getElementById('transfer-action').value = '';
    document.getElementById('transfer-id').value = '';
    document.getElementById('transfer-notes').value = '';
    document.getElementById('btn-submit-transfer').textContent = 'إنشاء أمر التحويل المخزني';

    const fromSelect = document.getElementById('from_location_id');
    if (fromSelect) {
        fromSelect.selectedIndex = 0;
    }
    const toSelect = document.getElementById('to_location_id');
    if (toSelect) {
        toSelect.selectedIndex = 0;
    }

    const body = document.getElementById('transfer-items-body');
    body.innerHTML = '<tr id="empty-transfer-row"><td colspan="3" class="muted" style="text-align: center; padding: 20px;">لم يتم إضافة أي أصناف بعد. اختر صنفاً من الأعلى وأضفه للتحويل.</td></tr>';
}

function addTransferItem(productId, productName, quantity = 1) {
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
                <input type="number" name="quantity[]" value="${quantity}" step="any" min="0.001" style="width: 100px; text-align: center; padding: 4px; border: 1px solid var(--line); border-radius:6px; outline:none;">
                <button type="button" class="btn small" onclick="adjustQty(this, 1)">+</button>
            </div>
        </td>
        <td style="padding: 10px; text-align: center;">
            <button type="button" class="btn small danger" onclick="removeTransferRow(this)">حذف</button>
        </td>
    `;
    document.getElementById('transfer-items-body').appendChild(tr);
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
        emptyRow.style.display = '';
    } else {
        emptyRow.style.display = 'none';
    }
}
</script>
