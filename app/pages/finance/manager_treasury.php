<?php
$filters = $_GET;
$locations = sale_locations();
$methodOptions = payment_method_labels();
$statusOptions = ['pending' => 'مستحق الاستلام', 'received' => 'تم الاستلام', 'cancelled' => 'ملغي'];
$sourceOptions = ['auto_payment' => 'دفع تلقائي', 'branch_transfer' => 'تحويل فرع'];

// دايماً زامن أمس واليوم تلقائياً حتى لو المدير ما حدد تاريخ
$syncDates = [date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))];

if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
    try {
        $from = new DateTimeImmutable((string) $filters['date_from']);
        $to = new DateTimeImmutable((string) $filters['date_to']);
        if ($from <= $to) {
            $days = min(31, (int) $from->diff($to)->days + 1);
            for ($i = 0; $i < $days; $i++) {
                $syncDates[] = $from->modify('+' . $i . ' days')->format('Y-m-d');
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
} elseif (!empty($filters['date_from'])) {
    $syncDates[] = (string) $filters['date_from'];
} elseif (!empty($filters['date_to'])) {
    $syncDates[] = (string) $filters['date_to'];
}

$syncLocationId = !empty($filters['location_id']) ? (int) $filters['location_id'] : null;
foreach (array_unique($syncDates) as $syncDate) {
    sync_manager_auto_collections($syncDate, $syncLocationId);
}

$rows = manager_collection_rows($filters);
$summary = manager_treasury_summary($filters);
?>
<section class="page-head">
    <h2>خزينة المدير</h2>
    <p>متابعة المستحقات المحولة تلقائياً من إنستا باي وفودافون كاش، وتحويلات الكاش اليدوية من الفروع، مع خصم مصروفات خزينة المدير من الرصيد.</p>
</section>

<form class="panel grid-form" method="get">
    <input type="hidden" name="r" value="manager_treasury">
    <label>الفرع
        <select name="location_id">
            <option value="">كل الفروع</option>
            <?php foreach ($locations as $location): ?>
                <option value="<?= e($location['id']) ?>" <?= (string) ($filters['location_id'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>الطريقة
        <select name="method">
            <option value="">كل الطرق</option>
            <?php foreach ($methodOptions as $method => $label): ?>
                <option value="<?= e($method) ?>" <?= ($filters['method'] ?? '') === $method ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>الحالة
        <select name="status">
            <option value="">كل الحالات</option>
            <?php foreach ($statusOptions as $status => $label): ?>
                <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>من<input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>"></label>
    <label>إلى<input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>"></label>
    <button class="btn primary">تصفية</button>
    <a class="btn" href="index.php?r=manager_treasury">إعادة ضبط</a>
</form>

<section class="panel grid-form">
    <?php foreach ($methodOptions as $method => $label): ?>
        <div>
            <span class="muted"><?= e($label) ?></span>
            <h3><?= money(($summary[$method]['pending'] ?? 0) + ($summary[$method]['received'] ?? 0)) ?></h3>
            <small>مستحق: <?= money($summary[$method]['pending'] ?? 0) ?> | مستلم: <?= money($summary[$method]['received'] ?? 0) ?> | ملغي: <?= money($summary[$method]['cancelled'] ?? 0) ?></small>
        </div>
    <?php endforeach; ?>
    <div>
        <span class="muted">مصاريف خزينة المدير</span>
        <h3><?= money($summary['expense_total'] ?? 0) ?></h3>
        <small>المصاريف المسجلة بدون فرع فقط</small>
    </div>
    <div>
        <span class="muted">إجمالي المستلم</span>
        <h3><?= money($summary['received_total'] ?? 0) ?></h3>
        <small>حسب الفلاتر الحالية</small>
    </div>
    <div>
        <span class="muted">صافي الكاش</span>
        <h3><?= money($summary['net_cash'] ?? 0) ?></h3>
        <small>الكاش المستلم ناقص المصاريف</small>
    </div>
    <div>
        <span class="muted">صافي خزينة المدير</span>
        <h3><?= money($summary['net_total'] ?? 0) ?></h3>
        <small>إجمالي المستلم بكل الطرق ناقص المصاريف</small>
    </div>
</section>

<section class="panel">
    <div class="toolbar" style="justify-content: space-between; margin-bottom: 12px;">
        <div>
            <h3 style="margin: 0;">تحصيلات خزينة المدير</h3>
            <p class="muted" style="margin: 4px 0 0;">التحصيلات التلقائية تحدث عند فتح الصفحة لليوم أو لنطاق تاريخ حتى 31 يوماً. مصروفات خزينة المدير تخصم من الصافي عند تسجيلها بدون فرع.</p>
        </div>
        <span class="badge">الإجمالي <?= e(count($rows)) ?></span>
    </div>
    <table>
        <thead>
            <tr>
                <th>الرقم</th>
                <th>الفرع</th>
                <th>الطريقة</th>
                <th>المصدر</th>
                <th>التاريخ</th>
                <th>المبلغ</th>
                <th>الحالة</th>
                <th>المستلم</th>
                <th>ملاحظات</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['collection_number']) ?></td>
                    <td><?= e($row['location_name']) ?></td>
                    <td><?= e($methodOptions[$row['method']] ?? $row['method']) ?></td>
                    <td><?= e($sourceOptions[$row['source_type']] ?? $row['source_type']) ?></td>
                    <td><?= e($row['collection_date']) ?></td>
                    <td><?= money($row['amount']) ?></td>
                    <td><span class="badge"><?= e($statusOptions[$row['status']] ?? $row['status']) ?></span></td>
                    <td><?= e($row['received_name'] ?? '-') ?></td>
                    <td><?= e($row['notes'] ?? '-') ?></td>
                    <td>
                        <?php if ($row['status'] === 'pending'): ?>
                            <form class="inline" method="post">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                <button class="btn small" name="action" value="receive">تأكيد استلام</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="10" class="muted" style="text-align: center;">لا توجد تحصيلات مطابقة.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
