<?php
$user       = require_login();
$locations  = sale_locations();
$userLocId  = current_user_location_id();

if ($userLocId !== null) {
    $locations = array_values(array_filter($locations, fn($l) => (int)$l['id'] === $userLocId));
}

$filters = [];
$selectedLocId = !empty($_GET['location_id']) ? (int)$_GET['location_id'] : null;
$selectedUserId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

if ($userLocId !== null) {
    $selectedLocId = $userLocId;
}

if ($selectedLocId) $filters['location_id'] = $selectedLocId;
if ($selectedUserId) $filters['user_id'] = $selectedUserId;
if ($dateFrom) $filters['date_from'] = $dateFrom;
if ($dateTo) $filters['date_to'] = $dateTo;

$rows = shift_rows_filtered($filters);
$allShiftUsers = shift_users();

$paginate = static function (array $data, int $perPage = 20): array {
    $total = count($data);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $page = min($page, $pages);
    return [array_slice($data, ($page - 1) * $perPage, $perPage), $page, $pages, $total];
};

[$pagedRows, $page, $pages, $total] = $paginate($rows);

$detailId = (int)($_GET['detail_id'] ?? 0);
$detailShift = $detailId > 0 ? get_shift_details($detailId) : null;
?>
<section class="page-head">
    <div>
        <h2>سجل الشيفتات</h2>
        <p>استعراض وتفاصيل جميع إغلاقات الشيفتات.</p>
    </div>
</section>

<div class="panel toolbar" style="flex-wrap:wrap; gap:8px;">
    <form method="get" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center; width:100%;">
        <input type="hidden" name="r" value="shifts_record">
        <?php if ($userLocId === null): ?>
        <select name="location_id" style="max-width:200px;">
            <option value="">كل الفروع</option>
            <?php foreach ($locations as $l): ?>
                <option value="<?= e($l['id']) ?>" <?= $selectedLocId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="user_id" style="max-width:200px;">
            <option value="">كل الموظفين</option>
            <?php foreach ($allShiftUsers as $su): ?>
                <option value="<?= e($su['id']) ?>" <?= $selectedUserId === (int)$su['id'] ? 'selected' : '' ?>><?= e($su['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= e($dateFrom) ?>" style="max-width:160px;" placeholder="من تاريخ">
        <input type="date" name="date_to" value="<?= e($dateTo) ?>" style="max-width:160px;" placeholder="إلى تاريخ">
        <button class="btn primary small">تصفية</button>
        <a class="btn small" href="index.php?r=shifts_record">إعادة ضبط</a>
    </form>
    <span class="badge"><?= e($total) ?> شيفت</span>
</div>

<?php if ($detailShift): ?>
<div class="panel" style="border-right:4px solid var(--primary); margin-bottom:14px;">
    <div class="toolbar" style="justify-content:space-between; flex-wrap:wrap;">
        <h3 style="margin:0;">تفاصيل الشيفت #<?= e($detailShift['id']) ?></h3>
        <a class="btn small" href="index.php?r=shifts_record<?= e(!empty($_GET['location_id']) ? '&location_id=' . $_GET['location_id'] : '') ?><?= e(!empty($_GET['user_id']) ? '&user_id=' . $_GET['user_id'] : '') ?>">إغلاق التفاصيل</a>
    </div>
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:10px; margin:12px 0;">
        <div><span class="muted">الفرع:</span> <strong><?= e($detailShift['location_name']) ?></strong></div>
        <div><span class="muted">تم الإغلاق بواسطة:</span> <strong><?= e($detailShift['user_name']) ?></strong></div>
        <div><span class="muted">بداية الشيفت:</span> <strong><?= format_datetime($detailShift['shift_start_time']) ?></strong></div>
        <div><span class="muted">إغلاق الشيفت:</span> <strong><?= format_datetime($detailShift['created_at']) ?></strong></div>
        <?php if ($detailShift['shift_start_user_name']): ?>
        <div><span class="muted">بداية الوردية بواسطة:</span> <strong><?= e($detailShift['shift_start_user_name']) ?></strong></div>
        <?php endif; ?>
        <div><span class="muted">عدد الفواتير:</span> <strong><?= e($detailShift['total_invoices']) ?></strong></div>
        <div><span class="muted">مبيعات كاش:</span> <strong><?= money($detailShift['total_cash_sales']) ?></strong></div>
        <div><span class="muted">إنستا باي:</span> <strong><?= money($detailShift['total_instapay']) ?></strong></div>
        <div><span class="muted">فودافون كاش:</span> <strong><?= money($detailShift['total_vodafone_cash']) ?></strong></div>
        <div><span class="muted" style="color:var(--danger);">مرتجعات كاش:</span> <strong><?= money($detailShift['total_returns_cash']) ?></strong></div>
        <div><span class="muted" style="color:var(--danger);">مصاريف كاش:</span> <strong><?= money($detailShift['total_expenses_cash']) ?></strong></div>
        <div><span class="muted">المتوقع:</span> <strong><?= money($detailShift['expected_cash']) ?></strong></div>
        <div><span class="muted">الفعلي:</span> <strong><?= money($detailShift['actual_cash']) ?></strong></div>
        <div><span class="muted">الفرق:</span> <strong style="color:<?= (float)$detailShift['difference'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>"><?= money($detailShift['difference']) ?></strong></div>
        <div><span class="muted">تحويل كاش:</span> <strong><?= money($detailShift['cash_transferred']) ?> (<?= e($detailShift['cash_transfer_action']) ?>)</strong></div>
        <div><span class="muted">المتبقي بالدرج:</span> <strong><?= money($detailShift['cash_remaining_in_drawer']) ?></strong></div>
        <div><span class="muted">صافي الكاش:</span> <strong><?= money($detailShift['net_cash']) ?></strong></div>
        <div><span class="muted">ملاحظة:</span> <strong><?= e($detailShift['notes'] ?: '—') ?></strong></div>
    </div>

    <?php if (!empty($detailShift['commissions'])): ?>
    <h4 style="margin:12px 0 6px;">👥 الحضور والعمولات</h4>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr>
            <th>الموظف</th>
            <th>أول حضور</th>
            <th>آخر انصراف</th>
            <th>نسبة العمولة</th>
            <th>مبيعاته</th>
            <th>العمولة</th>
        </tr></thead>
        <tbody>
        <?php foreach ($detailShift['commissions'] as $c): ?>
            <tr>
                <td><strong><?= e($c['user_name']) ?></strong></td>
                <td><?= format_datetime($c['first_check_in']) ?></td>
                <td><?= format_datetime($c['last_check_out']) ?> <?= !$c['last_check_out'] ? '<span class="badge" style="background:#f59e0b;">لم ينصرف بعد</span>' : '' ?></td>
                <td><?= e($c['commission_percent']) ?>%</td>
                <td><?= money($c['sales']) ?></td>
                <td><?= money($c['commission']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($detailShift['attendance'])): ?>
    <h4 style="margin:12px 0 6px;">📋 سجل الحضور</h4>
    <div style="overflow-x:auto;">
    <table>
        <thead><tr>
            <th>الموظف</th>
            <th>الإجراء</th>
            <th>التوقيت</th>
            <th>المصدر</th>
        </tr></thead>
        <tbody>
        <?php foreach ($detailShift['attendance'] as $a): ?>
            <tr>
                <td><?= e($a['user_name']) ?></td>
                <td><?= $a['action'] === 'check_in' ? '✅ حضور' : '🔴 انصراف' ?></td>
                <td><?= format_datetime($a['created_at']) ?></td>
                <td><?= e($a['source'] ?: '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($detailShift['invoices'])): ?>
    <h4 style="margin:12px 0 6px;">🧾 فواتير الشيفت</h4>
    <div style="overflow-x:auto; max-height:400px; overflow-y:auto;">
    <table>
        <thead><tr>
            <th>#</th>
            <th>رقم الفاتورة</th>
            <th>العميل</th>
            <th>الموظف</th>
            <th>الإجمالي</th>
            <th>التوقيت</th>
        </tr></thead>
        <tbody>
        <?php foreach ($detailShift['invoices'] as $inv): ?>
            <tr>
                <td><?= e($inv['id']) ?></td>
                <td><a href="index.php?r=invoice_view&id=<?= e($inv['id']) ?>">#<?= e($inv['invoice_number'] ?: $inv['id']) ?></a></td>
                <td><?= e($inv['customer_name'] ?: 'زبون عابر') ?></td>
                <td><?= e($inv['user_name']) ?></td>
                <td><?= money($inv['total']) ?></td>
                <td><?= format_datetime($inv['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="panel" style="padding-bottom:0;">
    <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:1000px;">
        <thead>
            <tr style="border-bottom:2px solid var(--line); text-align:right; background:var(--surface-soft);">
                <th style="padding:10px 12px;">#</th>
                <th style="padding:10px 12px;">الموظف</th>
                <th style="padding:10px 12px;">الفرع</th>
                <th style="padding:10px 12px;">بداية الشيفت</th>
                <th style="padding:10px 12px;">الإغلاق</th>
                <th style="padding:10px 12px; text-align:center;">عدد الفواتير</th>
                <th style="padding:10px 12px; text-align:center;">كاش</th>
                <th style="padding:10px 12px; text-align:center;">إنستا باي</th>
                <th style="padding:10px 12px; text-align:center;">فودافون كاش</th>
                <th style="padding:10px 12px; text-align:center;">صافي الكاش</th>
                <th style="padding:10px 12px; text-align:center;">تم التحويل</th>
                <th style="padding:10px 12px; text-align:center;">الفرق</th>
                <th style="padding:10px 12px; text-align:center;">تفاصيل</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$pagedRows): ?>
                <tr><td colspan="13" class="muted" style="text-align:center; padding:24px;">لا توجد شيفتات مسجلة بعد.</td></tr>
            <?php endif; ?>
            <?php foreach ($pagedRows as $s):
                $diff = (float)$s['difference'];
                $diffColor = $diff > 0 ? 'var(--success)' : ($diff < 0 ? 'var(--danger)' : 'var(--muted)');
                $dfx = $diff > 0 ? '▲ +' : ($diff < 0 ? '▼ ' : '');
                $startLabel = (!empty($s['shift_start_time']) && $s['shift_start_time'] !== '1970-01-01 00:00:00')
                    ? date('d/m/Y h:i A', strtotime($s['shift_start_time']))
                    : '—';
                $endLabel = date('d/m/Y h:i A', strtotime($s['created_at']));
                $isAdmin = $user['role_code'] === 'admin';
            ?>
            <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:10px 12px; color:var(--muted); font-size:12px;">#<?= e($s['id']) ?></td>
                <td style="padding:10px 12px;"><strong><?= e($s['user_name']) ?></strong></td>
                <td style="padding:10px 12px;"><?= e($s['location_name']) ?></td>
                <td style="padding:10px 12px; font-size:12px; color:var(--muted);"><?= e($startLabel) ?></td>
                <td style="padding:10px 12px; font-size:12px; color:var(--muted);"><?= e($endLabel) ?></td>
                <td style="padding:10px 12px; text-align:center;"><?= e($s['total_invoices'] ?? 0) ?></td>
                <td style="padding:10px 12px; text-align:center;"><?= money($s['total_cash_sales'] ?? 0) ?></td>
                <td style="padding:10px 12px; text-align:center;"><?= money($s['total_instapay'] ?? 0) ?></td>
                <td style="padding:10px 12px; text-align:center;"><?= money($s['total_vodafone_cash'] ?? 0) ?></td>
                <td style="padding:10px 12px; text-align:center; font-weight:700;"><?= money($s['net_cash'] ?? 0) ?></td>
                <td style="padding:10px 12px; text-align:center;"><?= money($s['cash_transferred'] ?? 0) ?></td>
                <td style="padding:10px 12px; text-align:center; font-weight:800; color:<?= $diffColor ?>;">
                    <?= $dfx . money(abs($diff)) ?>
                </td>
                <td style="padding:10px 12px; text-align:center;">
                    <a class="btn small" href="index.php?r=shifts_record&detail_id=<?= e($s['id']) ?><?= $selectedLocId ? '&location_id=' . e($selectedLocId) : '' ?><?= $selectedUserId ? '&user_id=' . e($selectedUserId) : '' ?><?= $dateFrom ? '&date_from=' . e($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . e($dateTo) : '' ?>">عرض</a>
                    <?php if ($isAdmin): ?>
                    <a class="btn small" href="index.php?r=shifts&edit_id=<?= e($s['id']) ?>">تعديل</a>
                    <form method="post" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا الشيفت؟')">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                        <button class="btn small danger">حذف</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($pages > 1): ?>
    <div class="toolbar" style="justify-content:space-between; margin-top:12px;">
        <span class="badge">صفحة <?= e($page) ?> من <?= e($pages) ?></span>
        <div class="actions">
            <?php if ($page > 1): ?>
                <a class="btn small" href="index.php?r=shifts_record&page=<?= e($page - 1) ?><?= $selectedLocId ? '&location_id=' . e($selectedLocId) : '' ?><?= $selectedUserId ? '&user_id=' . e($selectedUserId) : '' ?><?= $dateFrom ? '&date_from=' . e($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . e($dateTo) : '' ?>">السابق</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a class="btn small" href="index.php?r=shifts_record&page=<?= e($page + 1) ?><?= $selectedLocId ? '&location_id=' . e($selectedLocId) : '' ?><?= $selectedUserId ? '&user_id=' . e($selectedUserId) : '' ?><?= $dateFrom ? '&date_from=' . e($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . e($dateTo) : '' ?>">التالي</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
