<?php
$user = require_login();
$month = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');
$canManagePayroll = has_permission('users_permissions');
$tab = $_GET['tab'] ?? 'payroll';
if (!in_array($tab, ['payroll', 'history'])) $tab = 'payroll';

$payrollData = payroll_rows_for_month($month);
$paidStatuses = payroll_paid_statuses_for_month($month);

// For history tab
$historyFilters = ['month' => $month];
if (!empty($_GET['history_month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['history_month'])) {
    $historyFilters['month'] = $_GET['history_month'];
}
$salaryHistory = salary_payments_list($historyFilters);

$paymentMethodLabels = [
    'cash'          => 'نقدي',
    'instapay'      => 'إنستاباي',
    'vodafone_cash' => 'فودافون كاش',
    'bank_transfer' => 'تحويل بنكي',
];
?>
<style>
/* Payroll Page Custom Styles */
.payroll-container {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.payroll-header {
    background: linear-gradient(135deg, rgba(185, 132, 24, 0.08) 0%, rgba(111, 74, 8, 0.03) 100%);
    border: 1px solid rgba(185, 132, 24, 0.25);
    border-radius: 12px;
    padding: 16px 20px;
    box-shadow: 0 6px 20px rgba(111, 74, 8, 0.03);
}
.payroll-header h2 {
    font-size: 20px;
    color: var(--primary-dark);
    margin: 0 0 6px 0;
    font-weight: 800;
}
.payroll-header p {
    color: var(--muted);
    font-size: 12.5px;
    margin: 0;
}
.tab-nav-panel {
    background: #ffffff;
    border: 1.5px solid rgba(192, 148, 53, 0.25);
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    box-shadow: var(--shadow);
}
.tab-nav-panel .btn {
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 700;
    transition: all 0.2s ease;
}
.tab-nav-panel .btn.primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: #fff;
    box-shadow: 0 4px 10px rgba(185, 132, 24, 0.2);
}
.tab-nav-panel .btn:not(.primary) {
    background: var(--surface-soft);
    color: var(--ink);
}
.tab-nav-panel .btn:not(.primary):hover {
    background: var(--line);
}
.add-adj-panel {
    background: #ffffff;
    border: 1.5px solid rgba(192, 148, 53, 0.25);
    border-radius: 12px;
    padding: 16px;
    box-shadow: var(--shadow);
}
.add-adj-panel h4 {
    font-size: 14px;
    font-weight: 800;
    color: var(--primary-dark);
    margin-top: 0;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.payroll-table-panel {
    background: #ffffff;
    border: 1.5px solid rgba(192, 148, 53, 0.25);
    border-radius: 12px;
    padding: 16px;
    box-shadow: var(--shadow);
}
.payroll-table-panel h3 {
    font-size: 15px;
    font-weight: 800;
    color: var(--ink);
    margin-top: 0;
    margin-bottom: 15px;
    border-bottom: 2px solid var(--surface-soft);
    padding-bottom: 10px;
}
.payroll-table {
    width: 100%;
    border-collapse: collapse;
}
.payroll-table th {
    background: var(--surface-soft);
    color: var(--primary-dark);
    font-weight: 700;
    padding: 12px 10px;
    font-size: 12px;
    border-bottom: 2px solid var(--line);
}
.payroll-table td {
    padding: 12px 10px;
    font-size: 12.5px;
    border-bottom: 1px solid var(--line);
    vertical-align: middle;
    transition: background 0.2s ease;
}
.payroll-table tbody tr:hover td {
    background: rgba(185, 132, 24, 0.03);
}
.payroll-table tbody tr.active-row td {
    background: rgba(185, 132, 24, 0.06);
}
.badge-attendance {
    background: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
}
.badge-delay {
    background: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.badge-status-paid {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.badge-status-unpaid {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
}
.detail-panel-wrapper {
    background: #ffffff;
    border: 1.5px solid var(--primary);
    border-top: 4px solid var(--primary);
    border-radius: 12px;
    padding: 20px;
    margin-top: -8px;
    margin-bottom: 15px;
    box-shadow: 0 10px 25px rgba(111, 74, 8, 0.08);
    animation: slideDown 0.2s ease-out;
}
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.detail-card {
    background: var(--surface-soft);
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 16px;
}
.detail-card h5 {
    font-size: 13px;
    font-weight: 800;
    color: var(--primary-dark);
    margin-top: 0;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    border-bottom: 1px solid var(--line);
    padding-bottom: 6px;
}
.detail-card table {
    min-width: auto !important;
    width: 100% !important;
    border-collapse: collapse !important;
    margin: 0 !important;
}
.detail-card th, .detail-card td {
    padding: 8px 6px !important;
    font-size: 12px !important;
    border-bottom: 1px solid var(--line) !important;
    background: transparent !important;
}
.detail-card tr:last-child td {
    border-bottom: 0 !important;
}
</style>

<div class="payroll-container">

<section class="payroll-header">
    <div>
        <h2>🪙 مسيرات الرواتب والمستحقات (HR)</h2>
        <p>تفاصيل الراتب الأساسي، مبيعات الحضور، عمولة الشرائح اليومية، الحوافز، والخصومات الشهرية للموظفين.</p>
    </div>
</section>

<!-- Tabs -->
<div class="tab-nav-panel">
    <div class="actions" style="display:flex; gap:8px;">
        <a class="btn <?= $tab === 'payroll' ? 'primary' : '' ?>" href="index.php?r=payroll&month=<?= e($month) ?>&tab=payroll">مسيرة الشهر</a>
        <a class="btn <?= $tab === 'history' ? 'primary' : '' ?>" href="index.php?r=payroll&month=<?= e($month) ?>&tab=history">سجل الرواتب المصروفة</a>
    </div>
    <form method="get" style="display:flex; align-items:center; gap:10px; margin:0;">
        <input type="hidden" name="r" value="payroll">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <label style="margin:0; display:flex; align-items:center; gap:8px; font-weight:700;">اختر الشهر:
            <input type="month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()" style="padding:6px 12px; border-radius:8px; border:1px solid var(--line); font-family:inherit;">
        </label>
    </form>
</div>

<?php if ($tab === 'payroll'): ?>

<?php if ($canManagePayroll): ?>
    <!-- Add Adjustment Form -->
    <div class="add-adj-panel">
        <h4>➕ إضافة حافز أو خصم جديد للموظف</h4>
        <form class="grid-form" method="post" style="align-items:end; margin:0;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_adjustment">
            <input type="hidden" name="month" value="<?= e($month) ?>">
            <input type="hidden" name="adjustment_month" value="<?= e($month) ?>">
            <label>الموظف
                <select name="user_id" required>
                    <option value="">اختر الموظف</option>
                    <?php foreach ($payrollData as $row): $employee = $row['user']; ?>
                        <option value="<?= e($employee['id']) ?>"><?= e($employee['name']) ?> - <?= e($employee['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>النوع
                <select name="type" required>
                    <option value="bonus">حافز</option>
                    <option value="deduction">خصم</option>
                </select>
            </label>
            <label>المبلغ
                <input type="number" name="amount" step="0.01" min="0.01" required>
            </label>
            <label>السبب
                <input name="reason" maxlength="255" placeholder="سبب الحافز أو الخصم">
            </label>
            <button class="btn primary" style="height:38px;">إضافة للشهر <?= e($month) ?></button>
        </form>
    </div>
<?php endif; ?>

<!-- Payroll Table — summary rows only, NO detail rows inside -->
<div class="payroll-table-panel">
    <h3>📊 سجل الأجور والعمولات لشهر (<?= e($month) ?>)</h3>
    <div style="overflow-x:auto;">
    <table class="payroll-table">
        <thead>
            <tr style="border-bottom:2px solid var(--line); text-align:right;">
                <th style="padding:12px 10px;">الموظف</th>
                <th style="padding:12px 10px; text-align:center;">الحضور</th>
                <th style="padding:12px 10px;">الراتب الأساسي</th>
                <th style="padding:12px 10px;">مبيعات الحضور</th>
                <th style="padding:12px 10px;">العمولة</th>
                <th style="padding:12px 10px;">إضافات</th>
                <th style="padding:12px 10px;">خصومات</th>
                <th style="padding:12px 10px; font-size:13px;">صافي المستحق</th>
                <th style="padding:12px 10px; text-align:center;">الحالة</th>
                <th style="padding:12px 10px; text-align:center;">إجراء</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$payrollData): ?>
                <tr><td colspan="10" class="muted" style="text-align:center; padding:24px;">لا توجد بيانات موظفين نشطين.</td></tr>
            <?php endif; ?>
            <?php foreach ($payrollData as $row):
                $u = $row['user'];
                $detailId = 'payroll-detail-' . (int) $u['id'];
                $isPaid = isset($paidStatuses[(int)$u['id']]);
                $paidInfo = $isPaid ? $paidStatuses[(int)$u['id']] : null;
            ?>
                <tr data-employee-row="<?= e($detailId) ?>">
                    <td style="padding:12px 10px;">
                        <strong><?= e($u['name']) ?></strong><br>
                        <span class="muted" style="font-size:11px;"><?= e($u['role_name']) ?> · <?= e($u['location_name'] ?: 'إدارة') ?></span>
                    </td>
                    <td style="padding:12px 10px; text-align:center;">
                        <span class="badge badge-attendance"><?= e($row['days_present']) ?> يوم</span>
                        <?php if ($row['delays'] > 0): ?>
                            <span class="badge badge-delay" style="margin-right:3px;"><?= e($row['delays']) ?> تأخير</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px 10px;">
                        <?php if ($canManagePayroll): ?>
                            <form method="post" id="form-rates-<?= e($u['id']) ?>" style="margin:0; display:flex; align-items:center; gap:4px;">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_rates">
                                <input type="hidden" name="month" value="<?= e($month) ?>">
                                <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                <input type="hidden" name="commission_percent" value="<?= e($u['commission_percent'] ?? 0) ?>">
                                <input type="number" name="basic_salary" value="<?= e((float) $u['basic_salary']) ?>" step="1" min="0" style="width:85px; padding:4px 6px; border:1px solid var(--line); border-radius:6px; text-align:center; font-family:inherit;">
                            </form>
                        <?php else: ?>
                            <strong><?= money($u['basic_salary']) ?></strong>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px 10px;"><strong><?= money($row['attended_sales']) ?></strong></td>
                    <td style="padding:12px 10px;">
                        <strong><?= money($row['commission_final']) ?></strong>
                        <?php if ((float)$row['commission_final'] !== (float)$row['commission_calculated']): ?>
                            <br><small class="muted" style="font-size:10px;"><?= money($row['commission_calculated']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px 10px; color:#16a34a;"><strong><?= money($row['additions_final']) ?></strong></td>
                    <td style="padding:12px 10px; color:#dc2626;"><strong><?= money($row['deductions_final']) ?></strong></td>
                    <td style="padding:12px 10px; font-weight:800; font-size:14px; color:var(--primary-dark);"><?= money($row['total_payout']) ?></td>
                    <td style="padding:12px 10px; text-align:center;">
                        <?php if ($isPaid): ?>
                            <span class="badge badge-status-paid">✅ مصروف</span>
                            <small style="display:block; color:var(--muted); font-size:10px; margin-top:3px;"><?= e(format_date($paidInfo['paid_at'])) ?></small>
                        <?php else: ?>
                            <span class="badge badge-status-unpaid">⏳ لم يُصرف</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:12px 10px; text-align:center; white-space:nowrap;">
                        <div style="display:inline-flex; gap:5px;">
                            <button type="button" class="btn small" data-toggle-detail="<?= e($detailId) ?>">التفاصيل ▼</button>
                            <?php if ($canManagePayroll): ?>
                                <button type="submit" form="form-rates-<?= e($u['id']) ?>" class="btn small success" style="font-size:11px;">حفظ</button>
                                <?php if (!$isPaid): ?>
                                    <button type="button" class="btn small primary"
                                        onclick="openPaySalaryModal(<?= e(json_encode([
                                            'user_id'    => $u['id'],
                                            'user_name'  => $u['name'],
                                            'basic_salary'=> $u['basic_salary'],
                                            'commission' => $row['commission_final'],
                                            'additions'  => $row['additions_final'],
                                            'deductions' => $row['deductions_final'],
                                            'net_payout' => $row['total_payout'],
                                            'month'      => $month,
                                        ])) ?>)">
                                        💰 صرف
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div><!-- /overflow-x:auto -->
</div><!-- /payroll-table-panel -->

<!-- ===== Detail panels — OUTSIDE the scrollable table ===== -->
<?php foreach ($payrollData as $row):
    $u = $row['user'];
    $detailId = 'payroll-detail-' . (int) $u['id'];
    $override = $row['override'];
    $hasOverride = $override !== null && ($override['commission_total'] !== null || $override['additions_total'] !== null || $override['deductions_total'] !== null || $override['notes'] !== null);
    $dailyRows = array_values(array_filter($row['daily_rows'], fn(array $d): bool => (float)$d['attended_sales'] > 0 || (float)$d['target_commission'] > 0));
?>
<div id="<?= e($detailId) ?>" class="detail-panel-wrapper" style="display:none;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
        <h4 style="margin:0; color:var(--primary-dark); font-size:14px; font-weight:800;">📄 تفاصيل مستحقات الموظف: <?= e($u['name']) ?> — شهر (<?= e($month) ?>)</h4>
        <button type="button" class="btn small" data-toggle-detail="<?= e($detailId) ?>">إغلاق التفاصيل ✕</button>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:16px;">

        <!-- Daily Commission -->
        <div class="detail-card">
            <h5>📅 تفاصيل العمولات اليومية</h5>
            <?php if ($dailyRows): ?>
                <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
                    <thead><tr style="border-bottom:1.5px solid var(--line);">
                        <th style="padding:6px; text-align:right; background:transparent;">التاريخ</th>
                        <th style="padding:6px; text-align:right; background:transparent;">المبيعات</th>
                        <th style="padding:6px; text-align:right; background:transparent;">العمولة اليومية</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($dailyRows as $daily): ?>
                            <tr style="border-bottom:1px solid var(--line);">
                                <td style="padding:6px;"><?= e($daily['date']) ?></td>
                                <td style="padding:6px;"><?= money($daily['attended_sales']) ?></td>
                                <td style="padding:6px; font-weight:700; color:#2563eb;"><?= money($daily['target_commission']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="muted" style="font-size:12px; margin:0; text-align:center; padding:10px;">لا توجد مبيعات مسجلة في أيام الحضور.</p>
            <?php endif; ?>
        </div>

        <!-- Adjustments -->
        <div class="detail-card">
            <h5>⚡ الحوافز والخصومات المسجلة</h5>
            <?php if ($row['adjustments']): ?>
                <table style="width:100%; border-collapse:collapse; font-size:12.5px;">
                    <thead><tr style="border-bottom:1.5px solid var(--line);">
                        <th style="padding:6px; text-align:right; background:transparent;">النوع</th>
                        <th style="padding:6px; text-align:right; background:transparent;">المبلغ</th>
                        <th style="padding:6px; text-align:right; background:transparent;">السبب</th>
                        <?php if ($canManagePayroll): ?><th style="padding:6px; background:transparent;"></th><?php endif; ?>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($row['adjustments'] as $adj): ?>
                            <tr style="border-bottom:1px solid var(--line);">
                                <td style="padding:6px;">
                                    <span class="badge" style="background:<?= $adj['type'] === 'bonus' ? '#dcfce7' : '#fee2e2' ?>; color:<?= $adj['type'] === 'bonus' ? '#15803d' : '#b91c1c' ?>; border-color:transparent;">
                                        <?= $adj['type'] === 'bonus' ? 'حافز' : 'خصم' ?>
                                    </span>
                                </td>
                                <td style="padding:6px; font-weight:700;"><?= money($adj['amount']) ?></td>
                                <td style="padding:6px; word-break:break-word; max-width:120px;"><?= e($adj['reason'] ?: '—') ?></td>
                                <?php if ($canManagePayroll): ?>
                                    <td style="padding:6px; text-align:center;">
                                        <form method="post" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا التعديل؟')" style="margin:0;">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_adjustment">
                                            <input type="hidden" name="month" value="<?= e($month) ?>">
                                            <input type="hidden" name="id" value="<?= e($adj['id']) ?>">
                                            <button class="btn small danger" style="padding:2px 6px; font-size:10px;">حذف</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="muted" style="font-size:12px; margin:0; text-align:center; padding:10px;">لا توجد حوافز أو خصومات مضافة.</p>
            <?php endif; ?>
        </div>

        <!-- Net Summary -->
        <div class="detail-card">
            <h5>💼 ملخص صافي المستحقات</h5>
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <tr>
                    <td style="padding:8px 4px; color:var(--muted); border:0;">الراتب الأساسي</td>
                    <td style="padding:8px 4px; font-weight:700; text-align:left; border:0;"><?= money($u['basic_salary']) ?></td>
                </tr>
                <tr>
                    <td style="padding:8px 4px; color:var(--muted); border:0;">العمولة التراكمية</td>
                    <td style="padding:8px 4px; font-weight:700; color:#2563eb; text-align:left; border:0;">+ <?= money($row['commission_final']) ?></td>
                </tr>
                <tr>
                    <td style="padding:8px 4px; color:var(--muted); border:0;">إجمالي الإضافات</td>
                    <td style="padding:8px 4px; font-weight:700; color:#16a34a; text-align:left; border:0;">+ <?= money($row['additions_final']) ?></td>
                </tr>
                <tr>
                    <td style="padding:8px 4px; color:var(--muted); border:0;">إجمالي الخصومات</td>
                    <td style="padding:8px 4px; font-weight:700; color:#dc2626; text-align:left; border:0;">- <?= money($row['deductions_final']) ?></td>
                </tr>
                <tr style="border-top:2px solid var(--line);">
                    <td style="padding:10px 4px; font-weight:800; font-size:14px; border:0;">الصافي النهائي</td>
                    <td style="padding:10px 4px; font-weight:800; font-size:16px; color:var(--primary-dark); text-align:left; border:0;"><?= money($row['total_payout']) ?></td>
                </tr>
            </table>
        </div>

        <!-- Override form -->
        <?php if ($canManagePayroll): ?>
        <div class="detail-card">
            <h5>✏️ تعديل يدوي للقيم النهائية</h5>
            <?php if ($hasOverride): ?>
                <p class="muted" style="font-size:11px; margin-bottom:8px;">يوجد تعديل يدوي نشط حالياً.</p>
            <?php else: ?>
                <p class="muted" style="font-size:11px; margin-bottom:8px;">تُستخدم القيم المحسوبة تلقائياً من النظام.</p>
            <?php endif; ?>
            <form method="post" style="display:flex; flex-direction:column; gap:8px; margin:0;">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save_override">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="payroll_month" value="<?= e($month) ?>">
                <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <label style="font-size:11px;">إجمالي العمولة
                        <input type="number" name="commission_total" step="0.01" min="0" value="<?= e($override['commission_total'] ?? '') ?>" placeholder="<?= e(number_format((float)$row['commission_calculated'], 2, '.', '')) ?>" style="padding:4px 8px; font-size:12px;">
                    </label>
                    <label style="font-size:11px;">إجمالي الإضافات
                        <input type="number" name="additions_total" step="0.01" min="0" value="<?= e($override['additions_total'] ?? '') ?>" placeholder="<?= e(number_format((float)$row['additions_calculated'], 2, '.', '')) ?>" style="padding:4px 8px; font-size:12px;">
                    </label>
                </div>
                <div style="display:grid; grid-template-columns:1fr; gap:8px;">
                    <label style="font-size:11px;">إجمالي الخصومات
                        <input type="number" name="deductions_total" step="0.01" min="0" value="<?= e($override['deductions_total'] ?? '') ?>" placeholder="<?= e(number_format((float)$row['deductions_calculated'], 2, '.', '')) ?>" style="padding:4px 8px; font-size:12px;">
                    </label>
                </div>
                <label style="font-size:11px;">ملاحظات التعديل
                    <textarea name="notes" rows="1" placeholder="أدخل سبب التعديل" style="padding:4px 8px; font-size:12px; min-height:40px;"><?= e($override['notes'] ?? '') ?></textarea>
                </label>
                <button class="btn primary" style="font-size:12px; padding:6px 12px; margin-top:4px;">حفظ التعديلات اليدوية</button>
            </form>
        </div>
        <?php endif; ?>

    </div><!-- /grid -->
</div><!-- /detail panel -->
<?php endforeach; ?>

<!-- Pay Salary Modal -->
<div id="pay-salary-modal" class="modal">
    <div class="modal-content" style="border-radius:16px; padding:24px; max-width:440px;">
        <span class="close-modal" onclick="closePaySalaryModal()" style="float:left; cursor:pointer; font-size:24px;">&times;</span>
        <h3 style="margin-top:0; color:var(--primary); font-weight:700; border-bottom:1.5px solid var(--line); padding-bottom:8px;">💰 صرف الراتب</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="pay_salary">
            <input type="hidden" name="month" value="<?= e($month) ?>">
            <input type="hidden" name="payroll_month" value="<?= e($month) ?>">
            <input type="hidden" name="user_id" id="pay-user-id">
            <input type="hidden" name="basic_salary" id="pay-basic-salary">
            <input type="hidden" name="commission" id="pay-commission">
            <input type="hidden" name="additions" id="pay-additions">
            <input type="hidden" name="deductions" id="pay-deductions">
            <input type="hidden" name="net_payout" id="pay-net-payout">
            <div style="margin:16px 0; display:flex; flex-direction:column; gap:12px;">
                <p style="margin:0; font-size:13.5px; color:var(--muted);">الموظف: <strong id="pay-user-name" style="color:var(--ink);"></strong></p>
                <div style="background:var(--surface-soft); border-radius:10px; padding:14px; display:flex; flex-direction:column; gap:6px; font-size:13px;">
                    <div style="display:flex; justify-content:space-between;"><span style="color:var(--muted);">الراتب الأساسي</span><strong id="pay-display-basic"></strong></div>
                    <div style="display:flex; justify-content:space-between;"><span style="color:var(--muted);">العمولة</span><strong id="pay-display-commission" style="color:#2563eb;"></strong></div>
                    <div style="display:flex; justify-content:space-between;"><span style="color:var(--muted);">الإضافات</span><strong id="pay-display-additions" style="color:#16a34a;"></strong></div>
                    <div style="display:flex; justify-content:space-between;"><span style="color:var(--muted);">الخصومات</span><strong id="pay-display-deductions" style="color:#dc2626;"></strong></div>
                    <div style="display:flex; justify-content:space-between; border-top:1.5px solid var(--line); padding-top:8px; margin-top:4px;">
                        <strong>الصافي المستحق</strong>
                        <strong id="pay-display-net" style="font-size:18px; color:var(--primary-dark);"></strong>
                    </div>
                </div>
                <label style="font-weight:600;">مصدر الدفع (الخزينة)
                    <select name="payment_source" style="margin-top:6px; margin-bottom:10px; width:100%;">
                        <option value="branch">خزينة فرع الموظف</option>
                        <option value="manager_treasury">خزينة المدير</option>
                    </select>
                </label>
                <label style="font-weight:600;">طريقة الدفع
                    <select name="payment_method" style="margin-top:6px; width:100%;">
                        <option value="cash">نقدي</option>
                        <option value="instapay">إنستاباي</option>
                        <option value="vodafone_cash">فودافون كاش</option>
                        <option value="bank_transfer">تحويل بنكي</option>
                    </select>
                </label>
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" class="btn" onclick="closePaySalaryModal()">إلغاء</button>
                <button type="submit" class="btn primary">✅ تأكيد الصرف</button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($tab === 'history'): ?>

<!-- Salary History Tab -->
<div class="payroll-table-panel">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
        <h3 style="margin:0; border:0; padding:0;">📜 سجل الرواتب المصروفة لشهر (<?= e($historyFilters['month']) ?>)</h3>
        <div>
            <span style="color:var(--muted); font-size:13px;">الإجمالي المصروف:</span>
            <strong style="font-size:18px; color:var(--primary-dark); margin-right:6px;">
                <?= money(array_sum(array_column($salaryHistory, 'net_payout'))) ?>
            </strong>
        </div>
    </div>
    <div style="overflow-x:auto;">
    <table class="payroll-table">
        <thead>
            <tr style="border-bottom:2px solid var(--line); text-align:right;">
                <th style="padding:12px 10px;">الموظف</th>
                <th style="padding:12px 10px;">الشهر</th>
                <th style="padding:12px 10px;">الراتب الأساسي</th>
                <th style="padding:12px 10px;">العمولة</th>
                <th style="padding:12px 10px;">الإضافات</th>
                <th style="padding:12px 10px;">الخصومات</th>
                <th style="padding:12px 10px; font-size:13px;">الصافي المصروف</th>
                <th style="padding:12px 10px;">طريقة الدفع</th>
                <th style="padding:12px 10px;">الفرع</th>
                <th style="padding:12px 10px;">صُرف بواسطة</th>
                <th style="padding:12px 10px;">تاريخ الصرف</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($salaryHistory): foreach ($salaryHistory as $sp): ?>
                <tr>
                    <td style="padding:12px 10px;"><strong><?= e($sp['user_name']) ?></strong></td>
                    <td style="padding:12px 10px;"><span class="badge"><?= e($sp['payroll_month']) ?></span></td>
                    <td style="padding:12px 10px;"><?= money($sp['basic_salary']) ?></td>
                    <td style="padding:12px 10px; color:#2563eb;"><?= money($sp['commission']) ?></td>
                    <td style="padding:12px 10px; color:#16a34a;"><?= money($sp['additions']) ?></td>
                    <td style="padding:12px 10px; color:#dc2626;"><?= money($sp['deductions']) ?></td>
                    <td style="padding:12px 10px; font-weight:700; font-size:14px; color:var(--primary-dark);"><?= money($sp['net_payout']) ?></td>
                    <td style="padding:12px 10px;"><span class="badge"><?= e($paymentMethodLabels[$sp['payment_method']] ?? $sp['payment_method']) ?></span></td>
                    <td style="padding:12px 10px;"><?= $sp['location_name'] ? e($sp['location_name']) : '<span style="color:var(--primary); font-weight:700;">خزينة المدير</span>' ?></td>
                    <td style="padding:12px 10px;"><?= e($sp['admin_name']) ?></td>
                    <td style="padding:12px 10px;"><?= e(format_datetime($sp['paid_at'])) ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="11" class="muted" style="text-align:center; padding:30px;">لا توجد رواتب مصروفة لهذا الشهر.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php endif; ?>

</div><!-- /payroll-container -->

<script>
// Toggle detail panels (outside the table)
document.querySelectorAll('[data-toggle-detail]').forEach(btn => {
    btn.addEventListener('click', () => {
        const panel = document.getElementById(btn.dataset.toggleDetail);
        if (!panel) return;
        const isOpen = panel.style.display !== 'none';
        panel.style.display = isOpen ? 'none' : 'block';
        
        // Find the table row and toggle .active-row class
        const row = document.querySelector(`tr[data-employee-row="${btn.dataset.toggleDetail}"]`);
        if (row) {
            if (isOpen) {
                row.classList.remove('active-row');
            } else {
                row.classList.add('active-row');
            }
        }
        
        // Update all buttons that control this panel
        document.querySelectorAll('[data-toggle-detail="' + btn.dataset.toggleDetail + '"]').forEach(b => {
            b.textContent = isOpen ? 'التفاصيل ▼' : 'إغلاق التفاصيل ✕';
        });
        if (!isOpen) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
});

function openPaySalaryModal(data) {
    document.getElementById('pay-user-id').value = data.user_id;
    document.getElementById('pay-user-name').textContent = data.user_name;
    document.getElementById('pay-basic-salary').value = data.basic_salary;
    document.getElementById('pay-commission').value = data.commission;
    document.getElementById('pay-additions').value = data.additions;
    document.getElementById('pay-deductions').value = data.deductions;
    document.getElementById('pay-net-payout').value = data.net_payout;
    const fmt = v => parseFloat(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' ج.م';
    document.getElementById('pay-display-basic').textContent = fmt(data.basic_salary);
    document.getElementById('pay-display-commission').textContent = '+ ' + fmt(data.commission);
    document.getElementById('pay-display-additions').textContent = '+ ' + fmt(data.additions);
    document.getElementById('pay-display-deductions').textContent = '- ' + fmt(data.deductions);
    document.getElementById('pay-display-net').textContent = fmt(data.net_payout);
    document.getElementById('pay-salary-modal').classList.add('open');
}
function closePaySalaryModal() {
    document.getElementById('pay-salary-modal').classList.remove('open');
}
window.addEventListener('click', e => {
    const modal = document.getElementById('pay-salary-modal');
    if (e.target === modal) closePaySalaryModal();
});
</script>
