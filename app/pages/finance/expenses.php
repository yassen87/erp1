<?php
$userLocationId = current_user_location_id();
$view = $_GET['view'] ?? 'list';
if (!in_array($view, ['add', 'edit', 'list'])) $view = 'list';

$expense = null;
if ($view === 'edit') {
    if (!has_permission('expenses_add')) redirect('expenses');
    $expense = get_expense_by_id((int) ($_GET['id'] ?? 0));
    if (!$expense) redirect('expenses');
}
$paymentMethods = payment_method_labels();

$categories = expense_categories();
$locations = all_locations();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
}

$filters = $_GET;
$dateFrom = trim((string) ($filters['date_from'] ?? ''));
$dateTo = trim((string) ($filters['date_to'] ?? ''));
$selectedLocation = $userLocationId !== null ? $userLocationId : (!empty($filters['location_id']) ? (int) $filters['location_id'] : null);
$rows = expense_rows($userLocationId);

if ($selectedLocation !== null && $userLocationId === null) {
    $rows = array_values(array_filter($rows, fn ($row) => isset($row['location_id']) && (int) $row['location_id'] === $selectedLocation));
}
if ($dateFrom !== '') {
    $rows = array_values(array_filter($rows, fn ($row) => (string) $row['expense_date'] >= $dateFrom));
}
if ($dateTo !== '') {
    $rows = array_values(array_filter($rows, fn ($row) => (string) $row['expense_date'] <= $dateTo));
}

$paginateExpenses = static function (array $rows, int $perPage = 15): array {
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $page = min($page, $pages);

    return [array_slice($rows, ($page - 1) * $perPage, $perPage), $page, $pages, $total];
};

$renderExpensesPagination = static function (int $page, int $pages, int $total): void {
    $buildUrl = static function (int $target): string {
        $params = $_GET;
        $params['r'] = 'expenses';
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

[$pagedRows, $page, $pages, $total] = $paginateExpenses($rows);
?>

<section class="page-head">
    <h2>المصاريف</h2>
    <p>تسجيل مصروف حسب الفئة والموقع؛ مصروف الفرع يخصم من نقدية الفرع، والمصروف العام يخصم من خزينة المدير.</p>
</section>

<div class="panel toolbar" style="justify-content: space-between;">
    <div class="actions">
        <a class="btn <?= $view === 'list' ? 'primary' : '' ?>" href="index.php?r=expenses&view=list">سجل المصاريف</a>
        <?php if (has_permission('expenses_add')): ?>
            <a class="btn <?= $view === 'add' ? 'primary' : '' ?>" href="index.php?r=expenses&view=add">تسجيل مصروف</a>
        <?php endif; ?>
    </div>
    <span class="badge">الإجمالي <?= e($total) ?></span>
</div>

<?php if ($view === 'add' || $view === 'edit'): ?>
    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; margin-bottom: 12px;">
            <div>
                <h3 style="margin: 0;"><?= $view === 'edit' ? 'تعديل مصروف' : 'تسجيل مصروف' ?></h3>
                <p class="muted" style="margin: 4px 0 0;">اختر فرعاً ليخصم المصروف من نقدية الفرع، أو اتركه عام / خزينة المدير ليخصم من خزينة المدير.</p>
            </div>
            <a class="btn" href="index.php?r=expenses&view=list">رجوع للسجل</a>
        </div>
        <?php if (has_permission('expenses_add')): ?>
            <form class="grid-form" method="post" action="index.php?r=expenses">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="<?= $view ?>">
                <?php if ($view === 'edit'): ?>
                    <input type="hidden" name="id" value="<?= e($expense['id']) ?>">
                <?php endif; ?>
                <label>الفئة
                    <select name="category_id">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= e($c['id']) ?>" <?= ($expense['category_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>الموقع
                    <?php if ($userLocationId === null): ?>
                        <select name="location_id">
                            <option value="">عام / خزينة المدير</option>
                            <?php foreach ($locations as $l): ?>
                                <option value="<?= e($l['id']) ?>" <?= ($expense['location_id'] ?? '') == $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <select name="location_id" disabled>
                            <?php foreach ($locations as $l): ?>
                                <option value="<?= e($l['id']) ?>" <?= $userLocationId === (int) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="location_id" value="<?= e($userLocationId) ?>">
                    <?php endif; ?>
                </label>
                <label>طريقة الدفع
                    <select name="payment_method">
                        <?php foreach ($paymentMethods as $pmValue => $pmLabel): ?>
                            <option value="<?= e($pmValue) ?>" <?= ($expense['payment_method'] ?? 'cash') === $pmValue ? 'selected' : '' ?>><?= e($pmLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>المبلغ<input name="amount" type="number" step="1" min="1" value="<?= e($expense['amount'] ?? '') ?>" required></label>
                <label>التاريخ<input name="expense_date" type="date" value="<?= e($expense['expense_date'] ?? date('Y-m-d')) ?>" required></label>
                <label>ملاحظة<input name="notes" value="<?= e($expense['notes'] ?? '') ?>"></label>
                <button class="btn primary"><?= $view === 'edit' ? 'حفظ التعديلات' : 'تسجيل مصروف' ?></button>
            </form>
        <?php else: ?>
            <p class="muted">غير مصرح لك بتسجيل مصاريف.</p>
        <?php endif; ?>
    </section>
<?php else: ?>
    <form class="panel grid-form" method="get">
        <input type="hidden" name="r" value="expenses">
        <input type="hidden" name="view" value="list">
        <label>من<input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
        <label>إلى<input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
        <?php if ($userLocationId === null): ?>
            <label>الفرع
                <select name="location_id">
                    <option value="">كل المواقع / خزينة المدير</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= e($l['id']) ?>" <?= (string) ($filters['location_id'] ?? '') === (string) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <button class="btn primary">تصفية</button>
        <a class="btn" href="index.php?r=expenses&view=list">إعادة ضبط</a>
        <?php if (has_permission('expenses_add')): ?>
            <a class="btn" href="index.php?r=expenses&view=add">تسجيل مصروف</a>
        <?php endif; ?>
    </form>

    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; margin-bottom: 12px;">
            <div>
                <h3 style="margin: 0;">سجل المصاريف</h3>
                <p class="muted" style="margin: 4px 0 0;">جدول مصاريف مفلتر ومقسم إلى صفحات.</p>
            </div>
            <span class="badge">المعروض <?= e(count($pagedRows)) ?> من <?= e($total) ?></span>
        </div>
        <table>
            <thead>
                <tr><th>التاريخ</th><th>الفئة</th><th>الموقع</th><th>طريقة الدفع</th><th>المبلغ</th><th>المسؤول</th><th>ملاحظة</th><th>الإجراءات</th></tr>
            </thead>
            <tbody>
                <?php if (empty($pagedRows)): ?>
                    <tr><td colspan="8" class="muted" style="text-align: center;">لا توجد مصاريف مسجلة.</td></tr>
                <?php else: ?>
                    <?php foreach ($pagedRows as $eRow): ?>
                        <tr>
                            <td><?= e($eRow['expense_date']) ?></td>
                            <td><?= e($eRow['category_name']) ?></td>
                            <td><?= e($eRow['location_name'] ?: 'خزينة المدير') ?></td>
                            <td><?= e($paymentMethods[$eRow['payment_method']] ?? 'كاش') ?></td>
                            <td><?= money($eRow['amount']) ?></td>
                            <td><?= e($eRow['user_name']) ?></td>
                            <td><?= e($eRow['notes']) ?></td>
                            <td>
                                <?php if (has_permission('expenses_add')): ?>
                                    <div style="display: flex; gap: 4px;">
                                        <a href="index.php?r=expenses&view=edit&id=<?= e($eRow['id']) ?>" class="btn small">تعديل</a>
                                        <form method="post" action="index.php?r=expenses" onsubmit="return confirm('هل أنت متأكد من حذف هذا المصروف؟');" style="margin:0;">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= e($eRow['id']) ?>">
                                            <button type="submit" class="btn small danger">حذف</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php $renderExpensesPagination($page, $pages, $total); ?>
    </section>
<?php endif; ?>
