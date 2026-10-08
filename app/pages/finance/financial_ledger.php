<?php
$filters = $_GET;
// إذا لم يحدد المستخدم تواريخ، اعرض اليوم بالافتراضي
if (empty($filters['date_from']) && empty($filters['date_to'])) {
    $filters['date_from'] = date('Y-m-d');
    $filters['date_to']   = date('Y-m-d');
}
$rows = financial_ledger_rows($filters);
$summary = financial_ledger_summary($filters);
$locations = sale_locations();
$methodOptions = payment_method_labels();
$directionOptions = ['in' => 'وارد', 'out' => 'منصرف'];
$sourceOptions = ['payment' => 'مدفوعات', 'return' => 'مرتجعات', 'expense' => 'مصروفات', 'transfer' => 'تحويل خزينة'];
?>
<section class="page-head">
    <h2>السجل المالي</h2>
    <p>كل الحركات المالية المسجلة على النظام من مبيعات ومرتجعات ومصاريف وتحويلات خزينة.</p>
</section>

<form class="panel grid-form" method="get">
    <input type="hidden" name="r" value="financial_ledger">
    <label>
        من
        <input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
    </label>
    <label>
        إلى
        <input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
    </label>
    <?php if (current_user_location_id() === null): ?>
        <label>
            الفرع
            <select name="location_id">
                <option value="">كل الفروع</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?= e($location['id']) ?>" <?= (string) ($filters['location_id'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>>
                        <?= e($location['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>
    <label>
        الطريقة
        <select name="method">
            <option value="">كل الطرق</option>
            <?php foreach ($methodOptions as $method => $label): ?>
                <option value="<?= e($method) ?>" <?= ($filters['method'] ?? '') === $method ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>
        المصدر
        <select name="source_type">
            <option value="">كل المصادر</option>
            <?php foreach ($sourceOptions as $source => $label): ?>
                <option value="<?= e($source) ?>" <?= ($filters['source_type'] ?? '') === $source ? 'selected' : '' ?>>
                    <?= e($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <button class="btn primary">تصفية</button>
</form>

<div class="panel grid-form">
    <div>
        <span class="muted">الوارد</span>
        <h3><?= money($summary['in']) ?></h3>
    </div>
    <div>
        <span class="muted">المنصرف</span>
        <h3><?= money($summary['out']) ?></h3>
    </div>
    <div>
        <span class="muted">الصافي</span>
        <h3><?= money($summary['net']) ?></h3>
    </div>
</div>

<div class="panel">
    <table>
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>الفرع</th>
                <th>الاتجاه</th>
                <th>الطريقة</th>
                <th>المبلغ</th>
                <th>المصدر</th>
                <th>الوصف</th>
                <th>المستخدم</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e(format_datetime($row['created_at'])) ?></td>
                    <td><?= e($row['location_name'] ?? '-') ?></td>
                    <td><span class="badge"><?= e($directionOptions[$row['direction']] ?? $row['direction']) ?></span></td>
                    <td><?= e($methodOptions[$row['method']] ?? $row['method']) ?></td>
                    <td><?= money($row['amount']) ?></td>
                    <td>
                        <?= e($sourceOptions[$row['source_type']] ?? $row['source_type']) ?><?= $row['source_id'] ? ' #' . e($row['source_id']) : '' ?>
                    </td>
                    <td><?= e($row['description']) ?></td>
                    <td><?= e($row['user_name'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="8" class="muted">لا توجد حركات مالية.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
