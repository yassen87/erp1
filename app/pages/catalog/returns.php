<?php
$userLocationId = current_user_location_id();
$locations      = all_locations();
$view           = $_GET['view'] ?? 'list';

/* ─────────────────────────────────────────────
   ADD VIEW  –  Step 1 data fetching
───────────────────────────────────────────── */
if ($view === 'add') {

    $db = pdo();

    /* Customer search */
    $customerSearch = trim((string)($_GET['customer_search'] ?? ''));
    $filterCustId   = isset($_GET['customer_id']) && (int)$_GET['customer_id'] ? (int)$_GET['customer_id'] : null;
    $filterInvId    = isset($_GET['invoice_id'])  && (int)$_GET['invoice_id']  ? (int)$_GET['invoice_id']  : null;

    // location restriction
    if ($userLocationId !== null) {
        $filterLocId = $userLocationId;
    } else {
        $filterLocId = isset($_GET['location_id']) && (int)$_GET['location_id'] ? (int)$_GET['location_id'] : null;
    }

    /* Search customers by name or phone */
    $matchedCustomers = [];
    if ($customerSearch !== '') {
        $like = '%' . $customerSearch . '%';
        $stmt = $db->prepare("SELECT id, name, phone FROM customers WHERE (name LIKE ? OR phone LIKE ?) ORDER BY name LIMIT 30");
        $stmt->execute([$like, $like]);
        $matchedCustomers = $stmt->fetchAll();
    }

    /* Invoices for selected customer */
    $customerInvoices = [];
    $selectedCustomer = null;
    if ($filterCustId) {
        $stmt = $db->prepare("SELECT id, name, phone FROM customers WHERE id = ?");
        $stmt->execute([$filterCustId]);
        $selectedCustomer = $stmt->fetch();

        $invSql  = "SELECT i.id, i.invoice_number, i.total, i.created_at, l.name AS location_name
                    FROM invoices i
                    JOIN locations l ON l.id = i.location_id
                    WHERE i.customer_id = ? AND i.status = 'completed'
                      AND i.id NOT IN (SELECT original_invoice_id FROM return_invoices)";
        $invArgs = [$filterCustId];
        if ($filterLocId) { $invSql .= " AND i.location_id = ?"; $invArgs[] = $filterLocId; }
        $invSql .= " ORDER BY i.created_at DESC LIMIT 100";
        $stmt = $db->prepare($invSql);
        $stmt->execute($invArgs);
        $customerInvoices = $stmt->fetchAll();
    }

    /* Lines for selected invoice */
    $invoiceLines    = [];
    $selectedInvoice = null;
    if ($filterInvId) {
        $stmt = $db->prepare("SELECT i.id, i.invoice_number, i.total, i.created_at, l.name AS location_name, c.name AS customer_name
                              FROM invoices i JOIN locations l ON l.id=i.location_id LEFT JOIN customers c ON c.id=i.customer_id
                              WHERE i.id = ?");
        $stmt->execute([$filterInvId]);
        $selectedInvoice = $stmt->fetch();

        $stmt = $db->prepare("SELECT il.id, il.description, il.quantity, il.unit_price, il.discount_amount, il.line_total, il.line_type FROM invoice_lines il WHERE il.invoice_id = ? ORDER BY il.id");
        $stmt->execute([$filterInvId]);
        $invoiceLines = $stmt->fetchAll();
    }

} else {
    /* ─────────────────────────────────────────────
       LIST VIEW
    ───────────────────────────────────────────── */
    $search          = trim((string)($_GET['q']           ?? ''));
    $locationIdFilter= trim((string)($_GET['location_id'] ?? ''));
    $customerIdFilter= trim((string)($_GET['customer_id'] ?? ''));
    $startDate       = trim((string)($_GET['start_date']  ?? ''));
    $endDate         = trim((string)($_GET['end_date']    ?? ''));
    $customers       = all_customers();

    if ($userLocationId !== null) $locationIdFilter = (string)$userLocationId;

    $returns = returns_rows([
        'q'           => $search,
        'location_id' => $locationIdFilter,
        'customer_id' => $customerIdFilter,
        'start_date'  => $startDate,
        'end_date'    => $endDate,
    ]);
}

$refundMethods = [
    'cash'            => 'كاش (نقداً)',
    'instapay'        => 'انستا باي',
    'vodafone_cash'   => 'فودافون كاش',
    'customer_credit' => 'رصيد حساب للعميل',
];
?>

<?php if ($view === 'add'): ?>

<section class="page-head">
    <div>
        <h2>إضافة مرتجع جديد</h2>
        <p>ابحث عن العميل، اختر الفاتورة، ثم ضع علامة على الأصناف المُرادة للإرجاع.</p>
    </div>
    <div>
        <a class="btn" href="index.php?r=returns">رجوع للمرتجعات</a>
    </div>
</section>

<!-- ── Step 1: Customer Search ─────────────────── -->
<div class="panel" style="margin-bottom:16px;">
    <h4 style="margin:0 0 12px; color:var(--primary); font-weight:700;">📋 خطوة 1: البحث عن العميل</h4>
    <form method="get" action="index.php" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="r"    value="returns">
        <input type="hidden" name="view" value="add">
        <?php if ($filterLocId && $userLocationId === null): ?>
            <input type="hidden" name="location_id" value="<?= e($filterLocId) ?>">
        <?php endif; ?>

        <?php if ($userLocationId === null): ?>
        <label style="flex:1; min-width:160px;">الفرع
            <select name="location_id" onchange="this.form.submit()" style="margin-top:4px;">
                <option value="">-- كل الفروع --</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= e($l['id']) ?>" <?= $filterLocId == $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>

        <label style="flex:2; min-width:220px;">ابحث بالاسم أو رقم الهاتف
            <input name="customer_search" value="<?= e($customerSearch) ?>"
                   placeholder="مثال: محمد أو 01012345678"
                   style="margin-top:4px;">
        </label>
        <button class="btn primary" type="submit">🔍 بحث</button>
        <?php if ($customerSearch || $filterCustId): ?>
            <a class="btn" href="index.php?r=returns&view=add">إعادة تعيين</a>
        <?php endif; ?>
    </form>

    <?php if ($customerSearch && !$matchedCustomers): ?>
        <p style="margin-top:12px; color:var(--muted);">لم يتم العثور على عميل بهذا الاسم أو الرقم.</p>
    <?php endif; ?>

    <?php if ($matchedCustomers): ?>
        <div style="margin-top:14px; display:flex; flex-wrap:wrap; gap:10px;">
            <?php foreach ($matchedCustomers as $mc): ?>
                <a href="index.php?r=returns&view=add&customer_id=<?= e($mc['id']) ?><?= $filterLocId ? '&location_id='.e($filterLocId) : '' ?>"
                   class="btn <?= $filterCustId == $mc['id'] ? 'primary' : 'secondary' ?>">
                    👤 <?= e($mc['name']) ?><?= $mc['phone'] ? ' · '.e($mc['phone']) : '' ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($selectedCustomer): ?>
<!-- ── Step 2: Invoice Selection ──────────────── -->
<div class="panel" style="margin-bottom:16px;">
    <h4 style="margin:0 0 12px; color:var(--primary); font-weight:700;">
        🧾 خطوة 2: فواتير العميل — <?= e($selectedCustomer['name']) ?>
        <?= $selectedCustomer['phone'] ? '<small style="font-weight:400; color:var(--muted);">· '.e($selectedCustomer['phone']).'</small>' : '' ?>
    </h4>

    <?php if (!$customerInvoices): ?>
        <p style="color:var(--muted);">لا توجد فواتير قابلة للمرتجع لهذا العميل.</p>
    <?php else: ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:10px;">
            <?php foreach ($customerInvoices as $inv): ?>
                <a href="index.php?r=returns&view=add&customer_id=<?= e($filterCustId) ?>&invoice_id=<?= e($inv['id']) ?><?= $filterLocId ? '&location_id='.e($filterLocId) : '' ?><?= $customerSearch ? '&customer_search='.urlencode($customerSearch) : '' ?>"
                   class="panel <?= $filterInvId == $inv['id'] ? 'selected-invoice' : '' ?>"
                   style="display:block; text-decoration:none; padding:12px 14px; cursor:pointer; border:2px solid <?= $filterInvId == $inv['id'] ? 'var(--primary)' : 'var(--line)' ?>; transition:border 0.15s;">
                    <strong style="display:block; color:var(--ink);"><?= e($inv['invoice_number']) ?></strong>
                    <span style="font-size:12px; color:var(--muted);"><?= e($inv['location_name']) ?> · <?= e(format_datetime($inv['created_at'])) ?></span>
                    <span style="display:block; font-weight:700; color:var(--primary); margin-top:4px;"><?= money($inv['total']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($selectedInvoice && $invoiceLines): ?>
<!-- ── Step 3: Line Selection + Form ──────────── -->
<form method="post" action="index.php?r=returns" id="return-lines-form">
    <input type="hidden" name="csrf"         value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="return_type"  value="lines">
    <input type="hidden" name="invoice_id"   value="<?= e($selectedInvoice['id']) ?>">

    <div class="panel" style="margin-bottom:16px;">
        <h4 style="margin:0 0 14px; color:var(--primary); font-weight:700;">
            ✅ خطوة 3: اختر الأصناف المراد إرجاعها
            <small style="font-weight:400; color:var(--muted);">من فاتورة <?= e($selectedInvoice['invoice_number']) ?></small>
        </h4>

        <div style="margin-bottom:12px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <button type="button" onclick="selectAllLines(true)"  class="btn small secondary">تحديد الكل</button>
            <button type="button" onclick="selectAllLines(false)" class="btn small">إلغاء تحديد الكل</button>
            <span id="selected-count" style="font-size:12.5px; color:var(--muted);"></span>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:36px;"></th>
                    <th>الصنف / الوصف</th>
                    <th>الكمية الأصلية</th>
                    <th style="width:140px;">الكمية المرتجعة</th>
                    <th>السعر</th>
                    <th>الإجمالي الأصلي</th>
                    <th>إجمالي المرتجع</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoiceLines as $line): ?>
                <tr class="return-line-row" style="cursor:pointer;" onclick="toggleLine(this)">
                    <td style="text-align:center;">
                        <input type="checkbox" name="line_ids[]" value="<?= e($line['id']) ?>"
                               class="line-check" onclick="event.stopPropagation();"
                               onchange="updateTotal()">
                    </td>
                    <td><?= e($line['description']) ?></td>
                    <td class="original-qty" data-qty="<?= e($line['quantity']) ?>"><?= qty($line['quantity']) ?></td>
                    <td onclick="event.stopPropagation();">
                        <input type="number" name="returned_quantities[<?= e($line['id']) ?>]" 
                               class="returned-qty" 
                               value="<?= e($line['quantity']) ?>" 
                               max="<?= e($line['quantity']) ?>" 
                               min="0.001" 
                               step="any" 
                               oninput="updateTotal()" 
                               style="width:90px; text-align:center; padding: 4px; border: 1.5px solid var(--line); border-radius: 6px; font-weight: bold;">
                    </td>
                    <td><?= money($line['unit_price']) ?></td>
                    <td data-amount="<?= e($line['line_total']) ?>"><?= money($line['line_total']) ?></td>
                    <td class="line-return-total"><strong><?= money($line['line_total']) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h4 style="margin:0 0 14px; color:var(--primary); font-weight:700;">💳 بيانات المرتجع</h4>
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:14px; align-items:end;">
            <label>طريقة رد المبلغ
                <select name="refund_method" required style="margin-top:4px;">
                    <?php foreach ($refundMethods as $val => $lbl): ?>
                        <option value="<?= e($val) ?>"><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>المبلغ المُرجَع فعلياً للعميل
                <input type="number" name="refund_paid" id="refund-paid-input"
                       step="0.01" min="0" value="" placeholder="يُعبأ تلقائياً من المجموع"
                       style="margin-top:4px;">
                <small style="color:var(--muted);">اتركه فارغاً لإرجاع المبلغ كاملاً، أو أدخل مبلغاً أقل</small>
            </label>

            <label>سبب المرتجع
                <input name="reason" required placeholder="سبب الإرجاع..." style="margin-top:4px;">
            </label>
        </div>
        <div style="display:flex; align-items:center; gap:16px; margin-top:14px;">
            <div style="display:flex; flex-direction:column; gap:4px;">
                <span style="font-size:12.5px; color:var(--muted);">إجمالي المُرتجع</span>
                <span id="return-total" style="font-size:20px; font-weight:700; color:var(--primary);">0 ج.م</span>
            </div>
            <button class="btn primary" type="submit" id="submit-return-btn" disabled style="flex:1;">
                ✅ تنفيذ المرتجع
            </button>
        </div>
    </div>
</form>
<?php elseif ($selectedInvoice && !$invoiceLines): ?>
<div class="panel"><p style="color:var(--muted);">هذه الفاتورة لا تحتوي على بنود.</p></div>
<?php endif; ?>

<style>
.return-line-row:hover { background: var(--surface-alt, #f5f5f5); }
.return-line-row.checked-row { background: #fef9ec; }
</style>
<script>
function toggleLine(row) {
    const cb = row.querySelector('.line-check');
    cb.checked = !cb.checked;
    updateTotal();
}
function selectAllLines(state) {
    document.querySelectorAll('.line-check').forEach(cb => cb.checked = state);
    updateTotal();
}
function updateTotal() {
    let total = 0, count = 0;
    document.querySelectorAll('.line-check').forEach(cb => {
        const row = cb.closest('tr');
        const qtyInput = row.querySelector('.returned-qty');
        const origQty = parseFloat(row.querySelector('.original-qty').dataset.qty) || 0;
        let retQty = parseFloat(qtyInput.value) || 0;
        
        // Clamp returned quantity between 0 and original quantity
        if (retQty < 0) {
            retQty = 0;
            qtyInput.value = 0;
        } else if (retQty > origQty) {
            retQty = origQty;
            qtyInput.value = origQty;
        }
        
        const originalTotal = parseFloat(row.querySelector('[data-amount]').dataset.amount) || 0;
        const lineReturn = origQty > 0 ? (originalTotal * (retQty / origQty)) : 0;
        
        const fmt = new Intl.NumberFormat('ar-EG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        row.querySelector('.line-return-total').innerHTML = `<strong>${fmt.format(lineReturn)} ج.م</strong>`;
        
        if (cb.checked) {
            total += lineReturn;
            count++;
            row.classList.add('checked-row');
        } else {
            row.classList.remove('checked-row');
        }
    });
    const fmt = new Intl.NumberFormat('ar-EG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('return-total').textContent = fmt.format(total) + ' ج.م';
    document.getElementById('submit-return-btn').disabled = count === 0;
    
    // Auto-update refund paid input if empty or set to auto
    const refundInp = document.getElementById('refund-paid-input');
    if (refundInp) {
        refundInp.placeholder = total.toFixed(2);
    }
    
    const el = document.getElementById('selected-count');
    if (el) el.textContent = count > 0 ? `تم تحديد ${count} صنف` : '';
}
document.getElementById('return-lines-form')?.addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.line-check:checked');
    if (!checked.length) { e.preventDefault(); alert('يرجى تحديد صنف واحد على الأقل للإرجاع.'); }
});
</script>

<?php else: ?>
<!-- ══════════════════════════════════════════
     LIST VIEW
═══════════════════════════════════════════ -->
<section class="page-head">
    <div>
        <h2>المرتجعات وسجل العمليات</h2>
        <p>استعراض عمليات المرتجع والفواتير الملغاة.</p>
    </div>
    <div>
        <a class="btn primary" href="index.php?r=returns&view=add">➕ إضافة مرتجع جديد</a>
    </div>
</section>

<form class="panel" method="get" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(165px, 1fr)); gap:10px; align-items:end; overflow:visible; position:relative; z-index:10;">
    <input type="hidden" name="r" value="returns">
    <label>بحث في المرتجعات
        <input name="q" value="<?= e($search) ?>" placeholder="رقم الفاتورة أو المرتجع...">
    </label>
    <?php if ($userLocationId === null): ?>
    <label>الفرع
        <select name="location_id" id="ret-location">
            <option value="">كل الفروع</option>
            <?php foreach ($locations as $l): ?>
                <option value="<?= e($l['id']) ?>" <?= $locationIdFilter === (string)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php endif; ?>
    <label>العميل
        <select name="customer_id" id="ret-customer">
            <option value="">كل العملاء</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= e($c['id']) ?>" <?= $customerIdFilter === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?><?= $c['phone'] ? ' - '.e($c['phone']) : '' ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>من تاريخ
        <input type="date" name="start_date" value="<?= e($startDate) ?>">
    </label>
    <label>إلى تاريخ
        <input type="date" name="end_date" value="<?= e($endDate) ?>">
    </label>
    <div style="display:flex; gap:6px; align-items:end;">
        <button class="btn primary" style="flex:1;">تصفية</button>
        <a class="btn" href="index.php?r=returns" style="text-align:center; flex:1;">إعادة ضبط</a>
    </div>
</form>

<div class="panel" style="margin-top:0;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <strong style="font-size:13px; color:var(--muted);">إجمالي النتائج: <span style="color:var(--ink);"><?= count($returns) ?></span></strong>
    </div>
    <table>
        <thead>
            <tr>
                <th>رقم المرتجع</th>
                <th>رقم الفاتورة</th>
                <th>الفرع</th>
                <th>العميل</th>
                <th>قيمة المرتجع</th>
                <th>المدفوع فعلياً</th>
                <th>طريقة الرد</th>
                <th>السبب</th>
                <th>المسؤول</th>
                <th>التاريخ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($returns): ?>
                <?php foreach ($returns as $r): ?>
                    <?php
                        $amountVal = (float)$r['amount'];
                        $paidVal   = isset($r['refund_paid']) ? (float)$r['refund_paid'] : $amountVal;
                        $isPaidFull= abs($amountVal - $paidVal) < 0.01;
                    ?>
                    <tr>
                        <td><strong><?= e($r['return_number']) ?></strong></td>
                        <td><?= e($r['invoice_number']) ?></td>
                        <td><?= e($r['location_name']) ?></td>
                        <td><?= e($r['customer_name'] ?: 'زبون عابر') ?></td>
                        <td style="color:var(--danger); font-weight:700;"><?= money($amountVal) ?></td>
                        <td>
                            <?php if ($isPaidFull): ?>
                                <span style="color:#16a34a; font-weight:700;"><?= money($paidVal) ?> ✅</span>
                            <?php else: ?>
                                <span style="color:#dc2626; font-weight:700;"><?= money($paidVal) ?></span>
                                <small style="display:block; color:var(--muted);">(متبقي: <?= money($amountVal - $paidVal) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge"><?= e($refundMethods[$r['refund_method']] ?? $r['refund_method']) ?></span></td>
                        <td><?= e($r['reason'] ?: '-') ?></td>
                        <td><?= e($r['user_name']) ?></td>
                        <td><?= e(format_datetime($r['created_at'])) ?></td>
                        <td>
                            <button type="button" class="btn small secondary"
                                onclick="openEditPaid(<?= e($r['id']) ?>, <?= e($amountVal) ?>, <?= e($paidVal) ?>)">
                                ✏️ تعديل المدفوع
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11" style="text-align:center; padding:30px; color:var(--muted);">لا توجد عمليات مرتجع مطابقة.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal for editing refund paid amount -->
<div id="edit-paid-modal" class="modal">
    <div class="modal-content" style="border-radius: 16px; padding: 24px; max-width: 400px;">
        <span class="close-modal" onclick="closeEditPaidModal()" style="float: left; cursor: pointer; font-size: 24px;">&times;</span>
        <h3 style="margin-top: 0; color: var(--primary); font-weight: 700; border-bottom: 1.5px solid var(--line); padding-bottom: 8px;">تعديل المبلغ المدفوع</h3>
        
        <form method="post" action="index.php?r=returns">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_paid">
            <input type="hidden" name="return_id" id="edit-return-id">
            
            <div style="margin: 16px 0;">
                <p style="margin: 0 0 8px; font-size: 13.5px; color: var(--muted);">
                    إجمالي قيمة المرتجع: <strong id="edit-amount-text" style="color: var(--ink);"></strong>
                </p>
                <label style="display: block; margin-bottom: 6px; font-weight: 600;">المبلغ المدفوع للعميل فعلياً
                    <input type="number" name="refund_paid" id="edit-refund-paid-input" step="0.01" min="0" required style="margin-top: 6px; width: 100%;">
                </label>
            </div>
            
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn" onclick="closeEditPaidModal()">إلغاء</button>
                <button type="submit" class="btn primary">حفظ التعديل</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const locSelect  = document.getElementById('ret-location');
    const custSelect = document.getElementById('ret-customer');
    if (locSelect)  makeSelectSearchable(locSelect);
    if (custSelect) makeSelectSearchable(custSelect);
});

function openEditPaid(returnId, amount, paid) {
    document.getElementById('edit-return-id').value = returnId;
    document.getElementById('edit-amount-text').textContent = amount.toFixed(2) + ' ج.م';
    document.getElementById('edit-refund-paid-input').value = paid;
    document.getElementById('edit-refund-paid-input').max = amount; // Do not exceed return amount
    document.getElementById('edit-paid-modal').classList.add('open');
}

function closeEditPaidModal() {
    document.getElementById('edit-paid-modal').classList.remove('open');
}

// Close modal if clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('edit-paid-modal');
    if (event.target === modal) {
        closeEditPaidModal();
    }
});
</script>
<?php endif; ?>
