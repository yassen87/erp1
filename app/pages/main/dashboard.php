<?php
$userLocationId = current_user_location_id();
$stats     = dashboard_stats($userLocationId);
$shiftStats = dashboard_shift_stats($userLocationId);
$invoices  = recent_invoices(8, $userLocationId);
$shiftLocations = dashboard_shift_location_sales($userLocationId);
$lowStock  = low_stock_rows($userLocationId);
$today     = date('Y-m-d');
$todayInvoicesUrl = 'index.php?r=invoices&start_date=' . urlencode($today) . '&end_date=' . urlencode($today);
$todayCustomersUrl = 'index.php?r=customers&date_from=' . urlencode($today) . '&date_to=' . urlencode($today);
?>

<!-- Dashboard Hero -->
<section class="page-head hero" style="margin-bottom: 18px;">
    <div>
        <p class="eyebrow"><?= e(__('نظرة مباشرة')) ?></p>
        <h2><?= e(__('لوحة التحكم العامة')) ?></h2>
        <p><?= e(__('مبيعات الفروع والأونلاين، المخزون، الديون، والمصاريف في شاشة واحدة.')) ?></p>
    </div>
    <a class="btn primary" href="index.php?r=pos" style="white-space: nowrap;">
        🛒 <?= e(__('فتح الكاشير')) ?>
    </a>
</section>

<!-- Stats Cards -->
<section class="cards" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
    <a class="dashboard-card-link card-sales" href="<?= e($todayInvoicesUrl) ?>" title="مبيعات الشيف الحالي" aria-label="مبيعات الشيف الحالي">
        <span class="card-icon">💰</span>
        <span class="card-label">مبيعات الشيف الحالي</span>
        <strong class="card-value" style="color: #16a34a;"><?= money($shiftStats['shift_sales']) ?></strong>
    </a>
    <a class="dashboard-card-link card-invoices" href="index.php?r=invoices" title="فواتير الشيف الحالي" aria-label="فواتير الشيف الحالي">
        <span class="card-icon">🧾</span>
        <span class="card-label">فواتير الشيف الحالي</span>
        <strong class="card-value" style="color: var(--primary);"><?= e($shiftStats['shift_invoices']) ?></strong>
    </a>
    <a class="dashboard-card-link card-customers-today" href="<?= e($todayCustomersUrl) ?>" title="عملاء جدد بالشيفت الحالي" aria-label="عملاء جدد بالشيفت الحالي">
        <span class="card-icon">🆕</span>
        <span class="card-label">عملاء جدد بالشيفت</span>
        <strong class="card-value" style="color: var(--accent);"><?= e($shiftStats['shift_customers']) ?></strong>
    </a>
    <a class="dashboard-card-link card-customers" href="index.php?r=customers" title="<?= e(__('الانتقال إلى إجمالي العملاء')) ?>" aria-label="<?= e(__('الانتقال إلى إجمالي العملاء')) ?>">
        <span class="card-icon">👥</span>
        <span class="card-label"><?= e(__('إجمالي العملاء')) ?></span>
        <strong class="card-value" style="color: var(--primary-dark);"><?= e($stats['customers']) ?></strong>
    </a>
    <a class="dashboard-card-link card-debts" href="index.php?r=customers_debts" title="<?= e(__('الانتقال إلى العملاء ذوي الديون المفتوحة')) ?>" aria-label="<?= e(__('الانتقال إلى العملاء ذوي الديون المفتوحة')) ?>">
        <span class="card-icon">⚠️</span>
        <span class="card-label"><?= e(__('ديون مفتوحة')) ?></span>
        <strong class="card-value" style="color: #dc2626;"><?= money($stats['open_debts']) ?></strong>
    </a>
    <a class="dashboard-card-link card-expenses" href="index.php?r=expenses" title="<?= e(__('الانتقال إلى مصاريف الشهر')) ?>" aria-label="<?= e(__('الانتقال إلى مصاريف الشهر')) ?>">
        <span class="card-icon">📤</span>
        <span class="card-label"><?= e(__('مصاريف الشهر')) ?></span>
        <strong class="card-value" style="color: #f59e0b;"><?= money($stats['month_expenses']) ?></strong>
    </a>
    <a class="dashboard-card-link card-stock" href="index.php?r=inventory" title="<?= e(__('الانتقال إلى تنبيهات المخزون')) ?>" aria-label="<?= e(__('الانتقال إلى تنبيهات المخزون')) ?>">
        <span class="card-icon">📦</span>
        <span class="card-label"><?= e(__('تنبيهات مخزون')) ?></span>
        <strong class="card-value" style="color: var(--primary-dark);"><?= e($stats['low_stock_count']) ?></strong>
    </a>
</section>

<!-- Two-column: Location Sales + Low Stock -->
<section class="split" style="margin-bottom: 14px;">
    <!-- Location Shift Sales -->
    <div class="panel" style="padding: 0; overflow: hidden;">
        <div style="padding: 12px 16px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 13.5px;">📊 مبيعات الشيفتات حسب الفرع</h3>
            <a href="index.php?r=reports" class="btn small" style="font-size: 11px;">التقارير →</a>
        </div>
        <table style="min-width: 0;">
            <thead>
                <tr>
                    <th>الفرع</th>
                    <th>بداية الشيفت</th>
                    <th style="text-align: center;">الموظفين</th>
                    <th style="text-align: end;">المبيعات</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($shiftLocations): ?>
                    <?php foreach ($shiftLocations as $l): ?>
                        <tr>
                            <td><strong><?= e($l['name']) ?></strong></td>
                            <td style="font-size:11px; color:var(--muted);"><?= $l['actual_shift_start'] ? format_datetime($l['actual_shift_start']) : '—' ?></td>
                            <td style="text-align: center;"><?= e($l['employees_count']) ?> 👤</td>
                            <td style="text-align: end; font-weight: 700; color: #16a34a;"><?= money($l['shift_sales']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px; color: var(--muted);">
                            لا توجد مبيعات شيفتات حالياً.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Low Stock Alerts -->
    <div class="panel" style="padding: 0; overflow: hidden;">
        <div style="padding: 12px 16px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 13.5px;">
                🚨 <?= e(__('أصناف وصلت للحد الأدنى')) ?>
                <?php if ($lowStock): ?>
                    <span style="background: #dc2626; color: #fff; border-radius: 99px; padding: 1px 8px; font-size: 10.5px; font-weight: 700; margin-inline-start: 6px;">
                        <?= count($lowStock) ?>
                    </span>
                <?php endif; ?>
            </h3>
            <a href="index.php?r=inventory" class="btn small" style="font-size: 11px;"><?= e(__('المخزون')) ?> →</a>
        </div>
        <table style="min-width: 0;">
            <thead>
                <tr>
                    <th><?= e(__('الموقع')) ?></th>
                    <th><?= e(__('الصنف')) ?></th>
                    <th style="text-align: center;"><?= e(__('الرصيد')) ?></th>
                    <th style="text-align: center;"><?= e(__('الحد')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lowStock): ?>
                    <?php foreach ($lowStock as $r): ?>
                        <tr>
                            <td style="font-size: 11.5px;"><?= e($r['location_name']) ?></td>
                            <td><strong style="font-size: 12px;"><?= e($r['product_name']) ?></strong></td>
                            <td style="text-align: center;">
                                <span style="color: #dc2626; font-weight: 800;"><?= e(qty($r['quantity'])) ?></span>
                            </td>
                            <td style="text-align: center; color: var(--muted); font-weight: 700;"><?= e(qty($r['min_stock'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 20px; color: #16a34a;">
                            ✅ <?= e(__('المخزون بمستويات جيدة')) ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Recent Invoices -->
<div class="panel" style="padding: 0; overflow: hidden;">
    <div style="padding: 12px 16px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 13.5px;">🧾 <?= e(__('آخر الفواتير')) ?></h3>
        <a href="index.php?r=invoices" class="btn small primary" style="font-size: 11px;"><?= e(__('كل الفواتير')) ?> →</a>
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
                <th><?= e(__('المدفوع')) ?></th>
                <th><?= e(__('المتبقي')) ?></th>
                <th><?= e(__('التاريخ')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($invoices): ?>
                <?php foreach ($invoices as $i): ?>
                    <tr>
                        <td><a href="index.php?r=invoice_view&id=<?= e($i['id']) ?>" style="font-weight: 700; color: var(--primary-dark);"><?= e($i['invoice_number']) ?></a></td>
                        <td><span class="badge"><?= e(__($i['status'])) ?></span></td>
                        <td><?= e($i['location_name']) ?></td>
                        <td><?= e($i['user_name']) ?></td>
                        <td><?= e($i['customer_name'] ?: __('زبون عابر')) ?></td>
                        <td style="font-weight: 700;"><?= money($i['total']) ?></td>
                        <td style="color: #16a34a; font-weight: 700;"><?= money($i['paid_total']) ?></td>
                        <td style="color: <?= (float)$i['due_total'] > 0 ? '#dc2626' : 'var(--muted)' ?>; font-weight: 700;"><?= money($i['due_total']) ?></td>
                        <td style="color: var(--muted); font-size: 11.5px;"><?= e(format_datetime($i['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 30px; color: var(--muted);">
                        <?= e(__('لا توجد فواتير حتى الآن.')) ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.card-sales { border-inline-start: 0 !important; }
.cards .dashboard-card-link {
    background: rgba(253, 250, 244, .97);
    border: 1.5px solid rgba(192, 148, 53, 0.28);
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(14, 11, 7, .09);
    color: inherit;
    cursor: pointer;
    display: block;
    min-height: auto;
    overflow: hidden;
    padding: 10px 12px;
    position: relative;
    text-decoration: none;
    transition: transform .2s ease, box-shadow .2s ease;
}
.cards .dashboard-card-link:hover,
.cards .dashboard-card-link:focus-visible {
    box-shadow: 0 16px 40px rgba(15, 23, 42, .13);
    outline: none;
    text-decoration: none;
    transform: translateY(-3px);
}
.cards .dashboard-card-link:focus-visible {
    box-shadow: 0 0 0 3px rgba(192, 148, 53, .28), 0 16px 40px rgba(15, 23, 42, .13);
}
.cards .dashboard-card-link:after {
    content: '';
    position: absolute;
    left: -16px;
    top: -16px;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-soft), var(--accent-soft));
    z-index: 0;
    opacity: 0.85;
}
:root[data-theme="dark"] .cards .dashboard-card-link {
    background: var(--surface);
    border: 1.5px solid var(--line);
    box-shadow: var(--shadow);
}
.card-sales.dashboard-card-link:after { background: linear-gradient(135deg, rgba(22, 163, 74, .15), rgba(16, 185, 129, .1)); }
.card-debts.dashboard-card-link:after  { background: linear-gradient(135deg, rgba(220, 38, 38, .15), rgba(239, 68, 68, .1)); }
.dashboard-trend {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    font-size: 10.5px;
    font-weight: 700;
    margin-top: 4px;
    padding: 2px 6px;
    border-radius: 99px;
    position: relative;
    z-index: 1;
}
.trend-up   { background: rgba(22, 163, 74, .12); color: #16a34a; }
.trend-down { background: rgba(220, 38, 38, .12); color: #dc2626; }
@keyframes pulseGreen {
    0%  { box-shadow: 0 0 0 0 rgba(22,163,74,.25); }
    70% { box-shadow: 0 0 0 6px rgba(22,163,74,0); }
    100%{ box-shadow: 0 0 0 0 rgba(22,163,74,0); }
}
.card-sales { animation: pulseGreen 3s ease infinite; }
</style>
