<?php

declare(strict_types=1);

$customer = find_customer((int) ($_GET['id'] ?? 0));
if (!$customer) {
    echo '<section class="page-head"><h2>العميل غير موجود</h2><p>لم يتم العثور على العميل المطلوب.</p></section>';
    return;
}
$invoices = customer_invoices((int) $customer['id']);
$debts = customer_debts_rows((int) $customer['id']);
$totalSales = array_sum(array_map(fn($i) => (float) $i['total'], $invoices));
$locations = all_locations();
$userLocationId = current_user_location_id();
?>
<section class="page-head">
    <div>
        <h2>ملف العميل: <?= e($customer['name']) ?></h2>
        <p>فواتير العميل، الديون، والملاحظات مع إمكانية تسجيل ديون مباشرة وسدادها.</p>
    </div>
    <div class="actions" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <button type="button" class="btn primary" onclick="openDirectDebtModal()" style="font-weight: 700;">➕ تسجيل دين مباشر</button>
        <a class="btn" href="index.php?r=customers">رجوع للعملاء</a>
        <?php if (has_permission('customers_edit')): ?>
            <form method="post" action="index.php?r=customers" class="inline" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا العميل؟')">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= e($customer['id']) ?>">
                <button class="btn danger">حذف العميل</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="cards">
    <article><span>إجمالي المشتريات</span><strong><?= money($totalSales) ?></strong></article>
    <article><span>عدد الفواتير</span><strong><?= e(count($invoices)) ?></strong></article>
    <article><span>ديون مفتوحة</span><strong style="color: #ef4444;"><?= money(array_sum(array_map(fn($d) => (float) $d['remaining_amount'], array_filter($debts, fn($d) => $d['status'] === 'open')))) ?></strong></article>
</section>

<form class="panel grid-form" method="post" action="index.php?r=customers">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= e($customer['id']) ?>">
    <label>الاسم<input name="name" value="<?= e($customer['name']) ?>" required></label>
    <label>الهاتف<input name="phone" value="<?= e($customer['phone']) ?>"></label>
    <label>تاريخ الميلاد<input type="date" lang="en-GB" name="birthdate" value="<?= e(!empty($customer['birthdate']) ? date('Y-m-d', strtotime($customer['birthdate'])) : '') ?>"></label>
    <label>المصدر<select name="source"><option value="offline" <?= $customer['source'] === 'offline' ? 'selected' : '' ?>>أوف لاين</option><option value="online" <?= $customer['source'] === 'online' ? 'selected' : '' ?>>أونلاين</option></select></label>
    <label>ملاحظات<input name="notes" value="<?= e($customer['notes']) ?>"></label>
    <button class="btn primary">حفظ التعديل</button>
</form>

<section class="split">
    <div class="panel">
        <h3>فواتير العميل</h3>
        <?php table_invoices($invoices); ?>
    </div>
    <div class="panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 style="margin: 0;">ديون العميل</h3>
            <button type="button" class="btn small primary" onclick="openDirectDebtModal()">➕ إضافة دين مباشر</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>الفاتورة / البيان</th>
                    <th>الأصلي</th>
                    <th>المدفوع</th>
                    <th>المتبقي</th>
                    <th>الحالة</th>
                    <th>سداد</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$debts): ?>
                    <tr><td colspan="6" class="muted" style="text-align: center;">لا توجد ديون مسجلة على هذا العميل.</td></tr>
                <?php endif; ?>
                <?php foreach ($debts as $d): ?>
                    <tr>
                        <td>
                            <?php if (empty($d['invoice_id'])): ?>
                                <span class="badge warning" style="font-size: 11px;">📌 دين مباشر</span>
                                <?php if (!empty($d['notes'])): ?>
                                    <div style="font-size: 11.5px; color: var(--muted); margin-top: 2px;"><?= e($d['notes']) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="index.php?r=invoice_view&id=<?= e($d['invoice_id']) ?>"><?= e($d['invoice_number']) ?></a>
                            <?php endif; ?>
                        </td>
                        <td><?= money($d['original_amount']) ?></td>
                        <td><?= money($d['paid_amount']) ?></td>
                        <td><strong><?= money($d['remaining_amount']) ?></strong></td>
                        <td>
                            <span class="badge <?= $d['status'] === 'open' ? 'danger' : 'success' ?>">
                                <?= $d['status'] === 'open' ? 'مفتوح' : 'مسدد' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($d['status'] === 'open' && has_permission('customers_pay_debt')): ?>
                                <form method="post" action="index.php?r=customers_debts" class="inline pay">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="pay_debt">
                                    <input type="hidden" name="debt_id" value="<?= e($d['id']) ?>">
                                    <input name="amount" type="number" step="1" min="1" max="<?= e($d['remaining_amount']) ?>" placeholder="مبلغ" style="width: 70px;">
                                    <select name="method"><option value="cash">كاش</option><option value="instapay">انستا</option><option value="vodafone_cash">فودافون</option></select>
                                    <button class="btn small">سداد</button>
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
</section>

<!-- Modal نافذة تسجيل دين مباشر على العميل الحالي -->
<div id="direct-debt-modal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:16px;">
    <div class="panel" style="max-width:460px; width:100%; border-radius:18px; padding:24px; box-shadow:0 25px 50px rgba(0,0,0,0.6); position:relative; text-align:right;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--line); padding-bottom:10px;">
            <h3 style="margin:0; font-size:17px; font-weight:800; color:var(--primary);">➕ تسجيل دين مباشر على <?= e($customer['name']) ?></h3>
            <button type="button" onclick="closeDirectDebtModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--muted); line-height:1;">&times;</button>
        </div>
        <p class="muted" style="font-size:13px; margin-bottom:18px;">
            تسجيل دين مباشر على حساب هذا العميل بدون الحاجة لإنشاء فاتورة أو اختيار أصناف.
        </p>
        <form method="post" action="index.php?r=customers">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_direct_debt">
            <input type="hidden" name="customer_id" value="<?= e($customer['id']) ?>">
            <input type="hidden" name="redirect_to" value="customer_view">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <label>
                    <span style="font-weight:700; display:block; margin-bottom:6px;">مبلغ الدين (ج.م) *</span>
                    <input name="amount" type="number" step="0.5" min="1" required placeholder="المبلغ" autofocus style="width:100%;">
                </label>
                <label>
                    <span style="font-weight:700; display:block; margin-bottom:6px;">الفرع</span>
                    <select name="location_id" style="width:100%;">
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= e($l['id']) ?>" <?= (int)$l['id'] === (int)$userLocationId ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label style="display:block; margin-bottom:20px;">
                <span style="font-weight:700; display:block; margin-bottom:6px;">سبب / بيان الدين (اختياري)</span>
                <input name="notes" placeholder="مثال: باقي حساب سابق / أخذ سلفة / حساب قديم" style="width:100%;">
            </label>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn" onclick="closeDirectDebtModal()">إلغاء</button>
                <button type="submit" class="btn primary" style="font-weight:800;">تسجيل وحفظ الدين</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDirectDebtModal() {
    const modal = document.getElementById('direct-debt-modal');
    if (modal) modal.style.display = 'flex';
}
function closeDirectDebtModal() {
    const modal = document.getElementById('direct-debt-modal');
    if (modal) modal.style.display = 'none';
}
</script>
