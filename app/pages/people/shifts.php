<?php
$user       = require_login();
$locations  = sale_locations();
$userLocId  = current_user_location_id();

if ($userLocId !== null) {
    $locations = array_values(array_filter($locations, fn($l) => (int)$l['id'] === $userLocId));
}

$editId    = (int)($_GET['edit_id'] ?? 0);
$editShift = $editId > 0 ? find_shift_closure($editId) : null;
if ($editShift && $userLocId !== null && (int)$editShift['location_id'] !== $userLocId) {
    $editShift = null;
}

$selectedLocId = (int)($_GET['location_id'] ?? ($locations[0]['id'] ?? 0));
if ($userLocId === null && $selectedLocId > 0) {
    $activeLocations = array_values(array_filter($locations, fn($l) => (int)$l['id'] === $selectedLocId));
} else {
    $activeLocations = $locations;
}

$liveData = [];
foreach ($activeLocations as $loc) {
    $locId       = (int)$loc['id'];
    $lastClosure = get_last_shift_closure_time($locId);
    $closureTime = ($lastClosure === '1970-01-01 00:00:00') ? null : $lastClosure;

    // Determine actual shift start: first check-in, or first invoice, or last closure
    $firstCheckin = shift_first_checkin($locId, $lastClosure);
    $firstInvoice = shift_first_invoice($locId, $lastClosure);
    
    if ($firstCheckin) {
        $shiftStart = $firstCheckin['created_at'];
        $shiftStartType = 'checkin';
    } elseif ($firstInvoice) {
        $shiftStart = $firstInvoice['created_at'];
        $shiftStartType = 'invoice';
    } else {
        $shiftStart = $closureTime;
        $shiftStartType = 'closure';
    }

    $paymentTotals = shift_payment_totals($locId, $lastClosure, date('Y-m-d H:i:s'));
    $returnsOut    = shift_cash_returns($locId, $lastClosure, date('Y-m-d H:i:s'));
    $expensesOut   = shift_cash_expenses($locId, $lastClosure, date('Y-m-d H:i:s'));
    $debtPayments  = shift_debt_payments($locId, $lastClosure, date('Y-m-d H:i:s'));
    $expectedCash  = $paymentTotals['cash'] - $returnsOut - $expensesOut;

    $attendance    = shift_attendance_records($locId, $lastClosure, date('Y-m-d H:i:s'));
    $commissions   = shift_employee_commissions($locId, $lastClosure, date('Y-m-d H:i:s'));
    $invoices      = shift_invoices($locId, $lastClosure, date('Y-m-d H:i:s'));

    $invCountStmt = pdo()->prepare("SELECT COUNT(*) FROM invoices WHERE location_id = ? AND created_at >= ? AND created_at <= NOW()");
    $invCountStmt->execute([$locId, $lastClosure]);
    $invCount = (int)$invCountStmt->fetchColumn();

    $liveData[$locId] = [
        'shift_start'        => $shiftStart,
        'shift_start_type'   => $shiftStartType,
        'total_cash_sales'   => $paymentTotals['cash'],
        'total_instapay'     => $paymentTotals['instapay'],
        'total_vodafone_cash' => $paymentTotals['vodafone_cash'],
        'returns_out'        => $returnsOut,
        'expenses_out'       => $expensesOut,
        'expected_cash'      => $expectedCash,
        'debt_cash'          => $debtPayments['cash'],
        'debt_instapay'      => $debtPayments['instapay'],
        'debt_vodafone'      => $debtPayments['vodafone_cash'],
        'location_name'      => $loc['name'],
        'first_checkin'      => $firstCheckin,
        'attendance'         => $attendance,
        'commissions'        => $commissions,
        'invoices'           => $invoices,
        'invoice_count'      => $invCount,
    ];
}
?>
<section class="page-head">
    <div>
        <h2>اقفال الشيفت</h2>
        <p>تسجيل نهاية الوردية — يحسب الكاش المتوقع من مبيعات الفترة منذ آخر اغلاق.</p>
    </div>
</section>

<?php if (!$locations): ?>
    <div class="alert danger">اغلاق الشيفت مسموح للفروع فقط.</div>
<?php else: ?>

<?php if ($userLocId === null && count($locations) >= 1): ?>
<div class="panel" style="margin-bottom:14px; display:flex; gap:10px; align-items:center;">
    <strong style="white-space:nowrap;">اختر الفرع للإغلاق:</strong>
    <select onchange="window.location.href='index.php?r=shifts&location_id='+this.value" style="max-width:300px; width:100%;">
        <?php foreach ($locations as $l): ?>
            <option value="<?= e($l['id']) ?>" <?= $l['id'] == $selectedLocId ? 'selected' : '' ?>><?= e($l['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>

<?php foreach ($liveData as $locId => $ld):
    $startLabel = $ld['shift_start']
        ? date('d/m/Y h:i A', strtotime($ld['shift_start']))
        : 'بداية التشغيل';
    $startIcon = $ld['shift_start_type'] === 'checkin' ? '👤' : ($ld['shift_start_type'] === 'invoice' ? '🧾' : '⏱');
?>
<div class="panel" style="border-right:4px solid var(--primary); margin-bottom:14px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:14px;">
        <h3 style="margin:0; font-size:15px; color:var(--primary);">
            🏪 <?= e($ld['location_name']) ?> — الشيفت الحالي
        </h3>
        <span style="background:var(--surface-soft); border:1px solid var(--line); border-radius:20px; padding:4px 12px; font-size:11px; color:var(--muted);">
            <?= e($startIcon) ?> من: <?= e($startLabel) ?> &nbsp;→&nbsp; الآن: <?= date('d/m/Y h:i A') ?>
        </span>
    </div>

    <?php if ($ld['first_checkin']): ?>
    <div style="background:var(--surface-soft); border:1px solid var(--primary); border-radius:10px; padding:8px 14px; margin-bottom:14px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <span style="font-size:13px;">👤 بداية الوردية بواسطة:</span>
        <strong style="font-size:14px;"><?= e($ld['first_checkin']['user_name']) ?></strong>
        <span style="color:var(--muted);">⏰ الساعة: <?= format_datetime($ld['first_checkin']['created_at']) ?></span>
    </div>
    <?php elseif ($ld['shift_start_type'] === 'invoice' && !empty($ld['invoices'])): 
        $firstInv = $ld['invoices'][0]; ?>
    <div style="background:var(--surface-soft); border:1px solid #f59e0b; border-radius:10px; padding:8px 14px; margin-bottom:14px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <span style="font-size:13px;">🧾 بداية الشيفت بأول فاتورة:</span>
        <strong style="font-size:14px;">#<?= e($firstInv['invoice_number'] ?: $firstInv['id']) ?></strong>
        <span style="color:var(--muted);">بواسطة: <?= e($firstInv['user_name']) ?> - ⏰ <?= format_datetime($firstInv['created_at']) ?></span>
    </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:10px; margin-bottom:16px;">
        <div style="background:var(--surface-soft); border-radius:10px; padding:12px; text-align:center; border:1px solid var(--line);">
            <div style="font-size:11px; color:var(--muted); margin-bottom:4px;">💰 مبيعات كاش</div>
            <strong style="font-size:15px; color:var(--success);"><?= money($ld['total_cash_sales']) ?></strong>
        </div>
        <div style="background:var(--surface-soft); border-radius:10px; padding:12px; text-align:center; border:1px solid var(--line);">
            <div style="font-size:11px; color:var(--muted); margin-bottom:4px;">📱 إنستا باي</div>
            <strong style="font-size:15px; color:var(--primary);"><?= money($ld['total_instapay']) ?></strong>
        </div>
        <div style="background:var(--surface-soft); border-radius:10px; padding:12px; text-align:center; border:1px solid var(--line);">
            <div style="font-size:11px; color:var(--muted); margin-bottom:4px;">📞 فودافون كاش</div>
            <strong style="font-size:15px; color:var(--primary);"><?= money($ld['total_vodafone_cash']) ?></strong>
        </div>
        <div style="background:var(--surface-soft); border-radius:10px; padding:12px; text-align:center; border:1px solid var(--line);">
            <div style="font-size:11px; color:var(--muted); margin-bottom:4px;">↩ مرتجعات كاش</div>
            <strong style="font-size:15px; color:var(--danger);">– <?= money($ld['returns_out']) ?></strong>
        </div>
        <div style="background:var(--surface-soft); border-radius:10px; padding:12px; text-align:center; border:1px solid var(--line);">
            <div style="font-size:11px; color:var(--muted); margin-bottom:4px;">💸 مصاريف كاش</div>
            <strong style="font-size:15px; color:var(--danger);">– <?= money($ld['expenses_out']) ?></strong>
        </div>
        <div style="background:rgba(79,70,229,0.08); border:2px solid var(--primary); border-radius:10px; padding:12px; text-align:center;">
            <div style="font-size:11px; color:var(--primary); font-weight:700; margin-bottom:4px;">🎯 المتوقع في الدرج</div>
            <strong style="font-size:18px; color:var(--primary-dark);"><?= money($ld['expected_cash']) ?></strong>
        </div>
    </div>

    <?php 
    $totalDebt = $ld['debt_cash'] + $ld['debt_instapay'] + $ld['debt_vodafone'];
    if ($totalDebt > 0): ?>
    <div style="background:rgba(245,158,11,0.07); border:1.5px solid #f59e0b; border-radius:10px; padding:12px 16px; margin-bottom:14px;">
        <div style="font-size:12px; font-weight:700; color:#92400e; margin-bottom:10px;">🏦 مدفوعات من مديونية العملاء خلال الشيفت</div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px,1fr)); gap:8px;">
            <?php if ($ld['debt_cash'] > 0): ?>
            <div style="background:#fff7ed; border-radius:8px; padding:10px; text-align:center; border:1px solid #fed7aa;">
                <div style="font-size:11px; color:#92400e; margin-bottom:3px;">💵 كاش من المديونية</div>
                <strong style="font-size:14px; color:#b45309;"><?= money($ld['debt_cash']) ?></strong>
            </div>
            <?php endif; ?>
            <?php if ($ld['debt_instapay'] > 0): ?>
            <div style="background:#fff7ed; border-radius:8px; padding:10px; text-align:center; border:1px solid #fed7aa;">
                <div style="font-size:11px; color:#92400e; margin-bottom:3px;">📱 إنستا باي من المديونية</div>
                <strong style="font-size:14px; color:#b45309;"><?= money($ld['debt_instapay']) ?></strong>
            </div>
            <?php endif; ?>
            <?php if ($ld['debt_vodafone'] > 0): ?>
            <div style="background:#fff7ed; border-radius:8px; padding:10px; text-align:center; border:1px solid #fed7aa;">
                <div style="font-size:11px; color:#92400e; margin-bottom:3px;">📞 فودافون من المديونية</div>
                <strong style="font-size:14px; color:#b45309;"><?= money($ld['debt_vodafone']) ?></strong>
            </div>
            <?php endif; ?>
            <div style="background:#fef3c7; border-radius:8px; padding:10px; text-align:center; border:1px solid #fbbf24;">
                <div style="font-size:11px; color:#92400e; font-weight:700; margin-bottom:3px;">📊 إجمالي تحصيل الديون</div>
                <strong style="font-size:15px; color:#b45309;"><?= money($totalDebt) ?></strong>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($ld['commissions'])): ?>
    <h4 style="margin:12px 0 6px; font-size:13px;">👥 الحضور خلال الشيفت</h4>
    <div style="overflow-x:auto; margin-bottom:12px;">
    <table style="width:100%; border-collapse:collapse; font-size:12px;">
        <thead>
            <tr style="border-bottom:2px solid var(--line); text-align:right; background:var(--surface-soft);">
                <th style="padding:8px 10px;">الموظف</th>
                <th style="padding:8px 10px;">أول حضور</th>
                <th style="padding:8px 10px;">آخر انصراف</th>
                <th style="padding:8px 10px;">العمولة %</th>
                <th style="padding:8px 10px;">مبيعاته</th>
                <th style="padding:8px 10px;">العمولة</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ld['commissions'] as $c): ?>
            <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:8px 10px;"><strong><?= e($c['user_name']) ?></strong></td>
                <td style="padding:8px 10px;"><?= format_datetime($c['first_check_in']) ?></td>
                <td style="padding:8px 10px;"><?= format_datetime($c['last_check_out']) ?> <?= !$c['last_check_out'] ? '<span class="badge" style="background:#f59e0b;font-size:10px;">لم ينصرف بعد</span>' : '' ?></td>
                <td style="padding:8px 10px;"><?= e($c['commission_percent']) ?>%</td>
                <td style="padding:8px 10px;"><?= money($c['sales']) ?></td>
                <td style="padding:8px 10px;"><?= money($c['commission']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if (!empty($ld['invoices'])): ?>
    <h4 style="margin:12px 0 6px; font-size:13px;">🧾 فواتير الشيفت (<?= e($ld['invoice_count']) ?>)</h4>
    <div style="overflow-x:auto; max-height:300px; overflow-y:auto; margin-bottom:12px; border:1px solid var(--line); border-radius:6px;">
    <table style="width:100%; border-collapse:collapse; font-size:12px;">
        <thead>
            <tr style="border-bottom:2px solid var(--line); text-align:right; background:var(--surface-soft); position:sticky; top:0;">
                <th style="padding:8px 10px;">#</th>
                <th style="padding:8px 10px;">رقم الفاتورة</th>
                <th style="padding:8px 10px;">العميل</th>
                <th style="padding:8px 10px;">الموظف</th>
                <th style="padding:8px 10px;">الإجمالي</th>
                <th style="padding:8px 10px;">طريقة الدفع</th>
                <th style="padding:8px 10px;">التوقيت</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ld['invoices'] as $inv): ?>
            <tr style="border-bottom:1px solid var(--line);">
                <td style="padding:8px 10px; color:var(--muted);"><?= e($inv['id']) ?></td>
                <td style="padding:8px 10px;"><a href="index.php?r=invoice_view&id=<?= e($inv['id']) ?>">#<?= e($inv['invoice_number'] ?: $inv['id']) ?></a></td>
                <td style="padding:8px 10px;"><?= e($inv['customer_name'] ?: 'زبون عابر') ?></td>
                <td style="padding:8px 10px;"><?= e($inv['user_name']) ?></td>
                <td style="padding:8px 10px;"><?= money($inv['total']) ?></td>
                <td style="padding:8px 10px; font-size:11px;">
                    <?php 
                    $pmLabels = ['cash' => 'كاش', 'instapay' => 'إنستا باي', 'vodafone_cash' => 'فودافون كاش', 'bank_transfer' => 'تحويل بنكي'];
                    $methods = array_map(fn($m) => $pmLabels[trim($m)] ?? trim($m), explode(',', $inv['payment_methods'] ?? ''));
                    echo e(implode(' - ', $methods) ?: '—');
                    ?>
                </td>
                <td style="padding:8px 10px; font-size:11px;"><?= format_datetime($inv['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if (!$editShift): ?>
    <form method="post" style="padding-top:10px; border-top:1px dashed var(--line);">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="location_id" value="<?= e($locId) ?>">
        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
            <label style="flex:1; min-width:180px;">
                <span style="font-size:12px; font-weight:700; display:block; margin-bottom:5px;">الكاش الفعلي في الدرج (ج.م)</span>
                <input name="actual_cash" type="number" step="1" min="0" required placeholder="0"
                    style="width:100%; padding:10px 14px; border:1.5px solid var(--line); border-radius:8px; font-size:16px; font-weight:800; text-align:center;">
            </label>
        </div>
        <div style="margin:12px 0;">
            <span style="font-size:12px; font-weight:700; display:block; margin-bottom:8px;">💰 تحويل الكاش إلى خزينة المدير:</span>
            <label style="display:inline-flex; align-items:center; gap:6px; margin-left:16px; cursor:pointer;">
                <input type="radio" name="cash_transfer_action" value="all" checked>
                <span style="font-size:13px;">تحويل الكل للمدير</span>
                <span id="transfer-all-info" style="font-size:11px; color:var(--muted);"></span>
            </label>
            <label style="display:inline-flex; align-items:center; gap:6px; margin-left:16px; cursor:pointer;">
                <input type="radio" name="cash_transfer_action" value="partial">
                <span style="font-size:13px;">تحويل جزء</span>
            </label>
            <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                <input type="radio" name="cash_transfer_action" value="none">
                <span style="font-size:13px;">الإبقاء على الكاش في الدرج</span>
            </label>
        </div>
        <div id="partial-transfer-amount" style="display:none; margin-bottom:12px;">
            <label style="flex:1; min-width:180px;">
                <span style="font-size:12px; font-weight:700; display:block; margin-bottom:5px;">المبلغ المراد تحويله (ج.م)</span>
                <input name="cash_transferred_amount" type="number" step="1" min="0" placeholder="0"
                    style="width:100%; max-width:250px; padding:8px 12px; border:1.5px solid var(--line); border-radius:8px; font-size:14px; text-align:center;">
            </label>
        </div>
        <label style="display:block; margin-bottom:12px;">
            <span style="font-size:12px; font-weight:700; display:block; margin-bottom:5px;">ملاحظة (اختياري)</span>
            <input name="notes" placeholder="مثال: فيه عجز أو زيادة بسبب..." style="width:100%; max-width:400px; padding:10px 14px; border:1.5px solid var(--line); border-radius:8px; font-size:13px;">
        </label>
        <button class="btn primary" style="padding:10px 22px; font-size:14px; font-weight:800;">
            🔒 اغلاق الشيفت الآن
        </button>
    </form>
    <script>
    document.querySelectorAll('input[name="cash_transfer_action"]').forEach(el => {
        el.addEventListener('change', function() {
            document.getElementById('partial-transfer-amount').style.display = this.value === 'partial' ? 'block' : 'none';
        });
    });
    document.querySelector('input[name="actual_cash"]')?.addEventListener('input', function() {
        var info = document.getElementById('transfer-all-info');
        if (info) info.textContent = 'سيتم تحويل ' + (parseFloat(this.value) || 0).toFixed(2) + ' ج.م إلى خزينة المدير';
    });
    </script>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<?php if ($editShift): ?>
<div class="panel" style="border-right:4px solid #f59e0b; margin-bottom:14px;">
    <h4 style="margin-top:0; color:#b45309;">✏️ تعديل شيفت #<?= e($editShift['id']) ?></h4>
    <form method="post" style="display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= e($editShift['id']) ?>">
        <label style="flex:1; min-width:180px;">
            <span style="font-size:12px; font-weight:700; display:block; margin-bottom:5px;">الكاش الفعلي (ج.م)</span>
            <input name="actual_cash" type="number" step="1" min="0" required
                value="<?= e($editShift['actual_cash']) ?>"
                style="width:100%; padding:10px 14px; border:1.5px solid var(--line); border-radius:8px; font-size:16px; font-weight:800; text-align:center;">
        </label>
        <label style="flex:2; min-width:220px;">
            <span style="font-size:12px; font-weight:700; display:block; margin-bottom:5px;">ملاحظة</span>
            <input name="notes" value="<?= e($editShift['notes'] ?? '') ?>"
                style="width:100%; padding:10px 14px; border:1.5px solid var(--line); border-radius:8px; font-size:13px;">
        </label>
        <div style="display:flex; gap:8px;">
            <button class="btn primary" style="padding:10px 18px; font-weight:700;">حفظ التعديل</button>
            <a class="btn" href="index.php?r=shifts" style="padding:10px 18px;">إلغاء</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php endif; ?>
