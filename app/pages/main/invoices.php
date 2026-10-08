<?php
$locations  = all_locations();
$users      = all_users();
$customers  = all_customers();
$userLocationId = current_user_location_id();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
    $users = array_values(array_filter($users, fn ($u) => (int) ($u['location_id'] ?? 0) === $userLocationId || (int) $u['id'] === (int) current_user()['id']));
}

$today = date('Y-m-d');

$search         = trim((string) ($_GET['q']              ?? ''));
$location_id    = trim((string) ($_GET['location_id']    ?? ''));
$user_id        = trim((string) ($_GET['user_id']        ?? ''));
$customer_id    = trim((string) ($_GET['customer_id']    ?? ''));
$payment_method = trim((string) ($_GET['payment_method'] ?? ''));
// Default to today if no date is provided (first load) — empty string = no filter on explicit reset
$start_date = isset($_GET['start_date']) ? trim((string) $_GET['start_date']) : $today;
$end_date   = isset($_GET['end_date'])   ? trim((string) $_GET['end_date'])   : $today;

if ($userLocationId !== null) {
    $location_id = (string)$userLocationId;
}

$invoices = search_invoices([
    'q'              => $search,
    'location_id'    => $location_id,
    'user_id'        => $user_id,
    'customer_id'    => $customer_id,
    'payment_method' => $payment_method,
    'start_date'     => $start_date,
    'end_date'       => $end_date,
]);

$paymentMethods = [
    'cash'          => __('كاش / نقداً'),
    'instapay'      => 'InstaPay',
    'vodafone_cash' => 'Vodafone Cash',
];
?>
<section class="page-head">
    <div>
        <h2><?= e(__('الفواتير وسجل المبيعات')) ?></h2>
        <p><?= e(__('بحث واستعراض الفواتير مع التصفية بالموظف والعميل وطريقة الدفع والتاريخ.')) ?></p>
    </div>
</section>

<form class="panel" method="get" id="invoices-filter-form"
      style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; align-items: end; overflow: visible; position: relative; z-index: 10;">
    <input type="hidden" name="r" value="invoices">

    <!-- Search box -->
    <label><?= e(__('بحث في الفواتير')) ?>
        <input name="q" id="inv-q" value="<?= e($search) ?>"
               placeholder="<?= e(__('رقم الفاتورة / اسم العميل')) ?>">
    </label>

    <!-- Location -->
    <?php if ($userLocationId === null): ?>
    <label><?= e(__('الفرع / الموقع')) ?>
        <select name="location_id" id="inv-location">
            <option value=""><?= e(__('كل الفروع')) ?></option>
            <?php foreach ($locations as $l): ?>
                <option value="<?= e($l['id']) ?>" <?= $location_id === (string)$l['id'] ? 'selected' : '' ?>>
                    <?= e($l['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <?php else: ?>
        <input type="hidden" name="location_id" value="<?= $userLocationId ?>">
    <?php endif; ?>

    <!-- Employee / User -->
    <label><?= e(__('الموظف')) ?>
        <select name="user_id" id="inv-user">
            <option value=""><?= e(__('كل الموظفين')) ?></option>
            <?php foreach ($users as $u): ?>
                <option value="<?= e($u['id']) ?>" <?= $user_id === (string)$u['id'] ? 'selected' : '' ?>>
                    <?= e($u['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <!-- Customer -->
    <label><?= e(__('العميل')) ?>
        <select name="customer_id" id="inv-customer">
            <option value=""><?= e(__('كل العملاء')) ?></option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= e($c['id']) ?>" <?= $customer_id === (string)$c['id'] ? 'selected' : '' ?>>
                    <?= e($c['name']) ?><?= $c['phone'] ? ' - ' . e($c['phone']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <!-- Payment Method -->
    <label><?= e(__('طريقة الدفع')) ?>
        <select name="payment_method" id="inv-payment">
            <option value=""><?= e(__('كل طرق الدفع')) ?></option>
            <?php foreach ($paymentMethods as $val => $label): ?>
                <option value="<?= e($val) ?>" <?= $payment_method === $val ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <!-- Start Date -->
    <label><?= e(__('من تاريخ')) ?>
        <input type="date" name="start_date" id="inv-start" value="<?= e($start_date) ?>">
    </label>

    <!-- End Date -->
    <label><?= e(__('إلى تاريخ')) ?>
        <input type="date" name="end_date" id="inv-end" value="<?= e($end_date) ?>">
    </label>

    <!-- Actions -->
    <div style="display: flex; gap: 6px; align-items: end;">
        <button class="btn primary" style="flex: 1;"><?= e(__('تصفية')) ?></button>
        <a class="btn" href="index.php?r=invoices" style="text-align: center; flex: 1;"><?= e(__('إعادة ضبط')) ?></a>
    </div>
</form>

<div class="panel" style="margin-top: 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
        <strong style="font-size: 13px; color: var(--muted);">
            <?= e(__('إجمالي النتائج')) ?>: <span style="color: var(--ink);"><?= count($invoices) ?></span>
        </strong>
    </div>
    <table>
        <thead>
            <tr>
                <th><?= e(__('رقم الفاتورة')) ?></th>
                <th><?= e(__('الحالة')) ?></th>
                <th><?= e(__('الموقع')) ?></th>
                <th><?= e(__('الموظف')) ?></th>
                <th><?= e(__('العميل')) ?></th>
                <th><?= e(__('الإجمالي')) ?></th>
                <th><?= e(__('المتبقي')) ?></th>
                <th><?= e(__('تحذيرات')) ?></th>
                <th><?= e(__('ملاحظات')) ?></th>
                <th><?= e(__('التاريخ')) ?></th>
                <th><?= e(__('إجراء')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($invoices): ?>
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td><strong><?= e($i['invoice_number']) ?></strong></td>
                        <td><span class="badge"><?= e(__($i['status'])) ?></span></td>
                        <td><?= e($i['location_name']) ?></td>
                        <td><?= e($i['user_name']) ?></td>
                        <td><?= e($i['customer_name'] ?: __('زبون عابر')) ?></td>
                        <td><?= money($i['total']) ?></td>
                        <td><?= money($i['due_total']) ?></td>
                        <td><?php if (invoice_has_grams_warnings((int) $i['id'])): ?><span class="badge" style="background-color: var(--warning); color: white;" title="تحذير: تم التعديل على الكميات الافتراضية للجرامات">⚠ تحذير جرامات</span><?php else: ?><span style="color: var(--muted);">-</span><?php endif; ?></td>
                        <td>
                            <div class="notes-wrapper" id="notes-w-<?= $i['id'] ?>">
                                <span class="notes-text"><?= e($i['notes'] ?: '-') ?></span>
                                <?php if (date('Y-m-d', strtotime($i['created_at'])) === $today && has_permission('invoices_notes')): ?>
                                    <button type="button" class="btn-edit-note" onclick="toggleEditNote(<?= $i['id'] ?>)" title="<?= e(__('تعديل الملاحظة')) ?>">✏️</button>
                                    <form class="notes-edit-form hidden" id="notes-f-<?= $i['id'] ?>" method="post">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="update_notes">
                                        <input type="hidden" name="invoice_id" value="<?= $i['id'] ?>">
                                        <input name="notes" value="<?= e($i['notes']) ?>" class="input-inline-notes" style="width: 140px; padding: 2px 4px; font-size:11.5px;">
                                        <button class="btn primary small" style="padding: 2px 6px; font-size: 11px;"><?= e(__('حفظ')) ?></button>
                                        <button type="button" class="btn small" onclick="toggleEditNote(<?= $i['id'] ?>)" style="padding: 2px 6px; font-size: 11px;"><?= e(__('إلغاء')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= e(format_datetime($i['created_at'])) ?></td>
                        <td style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
                            <a class="btn small" href="index.php?r=invoice_view&id=<?= e($i['id']) ?>"><?= e(__('عرض/طباعة')) ?></a>
                            <?php if (has_permission('manager')): ?>
                            <form method="post" style="display:inline;" onsubmit="return confirm('⚠️ تحذير: سيتم حذف الفاتورة #<?= e($i['invoice_number']) ?> وإرجاع المخزون. هل أنت متأكد؟');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_invoice">
                                <input type="hidden" name="invoice_id" value="<?= e($i['id']) ?>">
                                <button class="btn small danger" type="submit" title="حذف الفاتورة وإرجاع المخزون">🗑 حذف</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" style="text-align: center; padding: 30px; color: var(--muted);">
                        <?= e(__('لا توجد فواتير مطابقة للفلاتر المحددة.')) ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const userSelect     = document.getElementById('inv-user');
    const customerSelect = document.getElementById('inv-customer');
    if (userSelect)     makeSelectSearchable(userSelect);
    if (customerSelect) makeSelectSearchable(customerSelect);
});
</script>
