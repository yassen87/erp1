<?php
$userLocationId = current_user_location_id();
$view = ($_GET['view'] ?? 'list') === 'add' ? 'add' : 'list';
$locations = sale_locations();
$filters = $_GET;

if ($userLocationId !== null) {
    $selectedLocationId = $userLocationId;
    $filters['location_id'] = $userLocationId;
} else {
    $selectedLocationId = !empty($_GET['location_id']) ? (int) $_GET['location_id'] : null;
}

$balances = branch_treasury_balances($selectedLocationId);
$transfers = branch_cash_transfer_rows($filters);
$methodOptions = payment_method_labels();
$createMethodOptions = ['cash' => $methodOptions['cash']];
$statusOptions = ['pending' => 'قيد التحويل', 'received' => 'تم الاستلام', 'cancelled' => 'ملغي'];

$paginateTransfers = static function (array $rows, int $perPage = 15): array {
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $page = min($page, $pages);

    return [array_slice($rows, ($page - 1) * $perPage, $perPage), $page, $pages, $total];
};

$renderTransferPagination = static function (int $page, int $pages, int $total): void {
    $buildUrl = static function (int $target): string {
        $params = $_GET;
        $params['r'] = 'branch_cash_transfers';
        $params['view'] = 'list';
        if ($target <= 1) {
            unset($params['page']);
        } else {
            $params['page'] = $target;
        }
        return 'index.php?' . http_build_query($params);
    };
    ?>
    <div class="toolbar" style="justify-content: space-between; margin-top: 12px;">
        <span class="badge">صفحة <?= e($page) ?> من <?= e($pages) ?> - الإجمالي <?= e($total) ?></span>
        <div class="actions">
            <?php if ($page > 1): ?>
                <a class="btn small" href="<?= e($buildUrl($page - 1)) ?>">السابق</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a class="btn small" href="<?= e($buildUrl($page + 1)) ?>">التالي</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
};

[$pagedTransfers, $page, $pages, $total] = $paginateTransfers($transfers);
?>

<section class="page-head">
    <h2>تحويل خزينة الفروع</h2>
    <p>تسجيل تحويل الكاش فقط من الفرع للمدير. إنستا باي وفودافون كاش تظهر تلقائياً في خزينة المدير.</p>
</section>

<div class="panel toolbar" style="justify-content: space-between;">
    <div class="actions">
        <a class="btn <?= $view === 'list' ? 'primary' : '' ?>" href="index.php?r=branch_cash_transfers&view=list">سجل التحويلات</a>
        <a class="btn <?= $view === 'add' ? 'primary' : '' ?>" href="index.php?r=branch_cash_transfers&view=add<?= $selectedLocationId !== null ? '&location_id=' . e($selectedLocationId) : '' ?>">تسجيل تحويل</a>
    </div>
    <span class="badge">التحويلات <?= e($total) ?></span>
</div>

<section class="panel grid-form">
    <?php foreach ($methodOptions as $method => $label): ?>
        <div>
            <span class="muted"><?= e($label) ?></span>
            <h3><?= money($balances[$method]['balance'] ?? 0) ?></h3>
            <small>وارد اليوم: <?= money($balances[$method]['today_in'] ?? 0) ?> | منصرف: <?= money($balances[$method]['out'] ?? 0) ?></small>
        </div>
    <?php endforeach; ?>
</section>

<?php if ($view === 'add'): ?>
    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; margin-bottom: 12px;">
            <div>
                <h3 style="margin: 0;">تسجيل تحويل خزينة</h3>
                <p class="muted" style="margin: 4px 0 0;">التحويل اليدوي متاح للكاش فقط، والمدفوعات الإلكترونية تتحول تلقائياً لخزينة المدير.</p>
            </div>
            <a class="btn" href="index.php?r=branch_cash_transfers&view=list">رجوع للسجل</a>
        </div>
        <form class="grid-form" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">
            <label>الفرع
                <select name="location_id" <?= $userLocationId !== null ? 'disabled' : '' ?>>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= e($location['id']) ?>" <?= (int) $location['id'] === (int) $selectedLocationId ? 'selected' : '' ?>><?= e($location['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($userLocationId !== null): ?>
                <input type="hidden" name="location_id" value="<?= e($userLocationId) ?>">
            <?php endif; ?>
            <label>طريقة التحويل
                <select name="method">
                    <?php foreach ($createMethodOptions as $method => $label): ?>
                        <option value="<?= e($method) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>المبلغ<input name="amount" type="number" step="0.01" min="0.01" value="<?= e($_GET['amount'] ?? '') ?>" required></label>
            <label>تاريخ التحويل<input name="transfer_date" type="date" value="<?= e(date('Y-m-d')) ?>" required></label>
            <label>ملاحظات<input name="notes" placeholder="اختياري"></label>
            <button class="btn primary">تسجيل التحويل</button>
        </form>
    </section>
<?php else: ?>
    <form class="panel grid-form" method="get">
        <input type="hidden" name="r" value="branch_cash_transfers">
        <input type="hidden" name="view" value="list">
        <?php if ($userLocationId === null): ?>
            <label>الفرع
                <select name="location_id">
                    <option value="">كل الفروع</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= e($location['id']) ?>" <?= (string) ($filters['location_id'] ?? '') === (string) $location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label>الحالة
            <select name="status">
                <option value="">كل الحالات</option>
                <?php foreach ($statusOptions as $status => $label): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['status'] ?? '') === $status ? 'selected' : '' ?>><?= e($label) ?></option>
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
        <label>من<input type="date" name="date_from" value="<?= e($filters['date_from'] ?? '') ?>"></label>
        <label>إلى<input type="date" name="date_to" value="<?= e($filters['date_to'] ?? '') ?>"></label>
        <button class="btn primary">تصفية</button>
        <a class="btn" href="index.php?r=branch_cash_transfers&view=list">إعادة ضبط</a>
        <a class="btn" href="index.php?r=branch_cash_transfers&view=add<?= $selectedLocationId !== null ? '&location_id=' . e($selectedLocationId) : '' ?>">تسجيل تحويل</a>
    </form>

    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; margin-bottom: 12px;">
            <div>
                <h3 style="margin: 0;">سجل تحويلات الخزينة</h3>
                <p class="muted" style="margin: 4px 0 0;">متابعة التحويلات مع الحفاظ على إجراءات الاستلام والإلغاء.</p>
            </div>
            <span class="badge">المعروض <?= e(count($pagedTransfers)) ?> من <?= e($total) ?></span>
        </div>
        <table>
            <thead>
                <tr><th>الرقم</th><th>الفرع</th><th>الطريقة</th><th>المبلغ</th><th>التاريخ</th><th>الحالة</th><th>المسؤول</th><th>المستلم</th><th>إجراءات</th></tr>
            </thead>
            <tbody>
                <?php foreach ($pagedTransfers as $transfer): ?>
                    <tr>
                        <td><?= e($transfer['transfer_number']) ?></td>
                        <td><?= e($transfer['location_name']) ?></td>
                        <td><?= e($methodOptions[$transfer['method']] ?? $transfer['method']) ?></td>
                        <td><?= money($transfer['amount']) ?></td>
                        <td><?= e($transfer['transfer_date']) ?></td>
                        <td><span class="badge"><?= e($statusOptions[$transfer['status']] ?? $transfer['status']) ?></span></td>
                        <td><?= e($transfer['created_name']) ?></td>
                        <td><?= e($transfer['received_name'] ?? '-') ?></td>
                        <td>
                            <?php if ($transfer['status'] === 'pending'): ?>
                                <form class="inline" method="post">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="id" value="<?= e($transfer['id']) ?>">
                                    <?php if (has_permission('manager_treasury') || current_user()['role_code'] === 'admin'): ?>
                                        <button class="btn small" name="action" value="receive">استلام</button>
                                    <?php endif; ?>
                                    <button class="btn small danger" name="action" value="cancel">إلغاء</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pagedTransfers): ?>
                    <tr><td colspan="9" class="muted" style="text-align: center;">لا توجد تحويلات مسجلة.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php $renderTransferPagination($page, $pages, $total); ?>
    </section>
<?php endif; ?>
