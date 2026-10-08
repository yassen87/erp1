<?php
$view = ($_GET['view'] ?? 'list') === 'add' ? 'add' : 'list';
$userLocationId = current_user_location_id();
$locations = all_locations();
$search = trim((string) ($_GET['q'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
// إظهار جميع العملاء من كل الفروع والمخزن — لا تفلتر حسب موقع المستخدم
$locationFilter = trim((string) ($_GET['location_id'] ?? ''));

$customers = customer_rows([
    'q' => $search,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'location_id' => $locationFilter,
], null);


$paginateRows = static function (array $rows, string $pageParam = 'page', int $perPage = 15): array {
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, (int) ($_GET[$pageParam] ?? 1));
    $page = min($page, $pages);

    return [array_slice($rows, ($page - 1) * $perPage, $perPage), $page, $pages, $total];
};

$renderPagination = static function (string $pageParam, int $page, int $pages, int $total): void {
    $buildUrl = static function (int $target) use ($pageParam): string {
        $params = $_GET;
        $params['r'] = 'customers';
        $params['view'] = 'list';
        if ($target <= 1) {
            unset($params[$pageParam]);
        } else {
            $params[$pageParam] = $target;
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

[$pagedCustomers, $customerPage, $customerPages, $customerTotal] = $paginateRows($customers);
?>

<section class="page-head">
    <h2>العملاء</h2>
    <p>سجل العملاء مع فلترة حسب البحث وتاريخ الإضافة والفرع التابع للعميل.</p>
</section>

<div class="panel toolbar" style="justify-content: space-between;">
    <div class="actions">
        <a class="btn <?= $view === 'list' ? 'primary' : '' ?>" href="index.php?r=customers&view=list">سجل العملاء</a>
        <a class="btn" href="index.php?r=customers_debts">الديون المفتوحة</a>
        <?php if (has_permission('customers_add')): ?>
            <a class="btn <?= $view === 'add' ? 'primary' : '' ?>" href="index.php?r=customers&view=add">إضافة عميل</a>
        <?php endif; ?>
    </div>
    <span class="badge">العملاء: <?= e($customerTotal) ?></span>
</div>

<?php if ($view === 'add'): ?>
    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; margin-bottom: 12px;">
            <div>
                <h3 style="margin: 0;">إضافة عميل جديد</h3>
                <p class="muted" style="margin: 4px 0 0;">سيتم تسجيل فرع الإضافة والمستخدم الحالي تلقائياً.</p>
            </div>
            <a class="btn" href="index.php?r=customers&view=list">رجوع للسجل</a>
        </div>
        <?php if (has_permission('customers_add')): ?>
            <form class="grid-form" method="post">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <label>اسم العميل<input name="name" required></label>
                <label>الهاتف<input name="phone"></label>
                <label>تاريخ الميلاد<input type="date" name="birthdate" lang="en-GB"></label>
                <label>المصدر<select name="source"><option value="offline">أوف لاين</option><option value="online">أونلاين</option></select></label>
                <label>ملاحظات<input name="notes"></label>
                <button class="btn primary">إضافة عميل</button>
            </form>
        <?php else: ?>
            <p class="muted">غير مصرح لك بإضافة عملاء.</p>
        <?php endif; ?>
    </section>
<?php else: ?>
    <form class="panel" method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; align-items: end;">
        <input type="hidden" name="r" value="customers">
        <input type="hidden" name="view" value="list">
        <label>بحث<input name="q" value="<?= e($search) ?>" placeholder="اسم أو هاتف"></label>
        <label>من تاريخ<input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
        <label>إلى تاريخ<input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
        <?php if ($userLocationId === null): ?>
            <label>الفرع التابع
                <select name="location_id">
                    <option value="">كل الفروع</option>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= e($l['id']) ?>" <?= $locationFilter === (string) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php else: ?>
            <input type="hidden" name="location_id" value="<?= e($userLocationId) ?>">
        <?php endif; ?>
        <button class="btn primary">تطبيق الفلاتر</button>
        <a class="btn" href="index.php?r=customers&view=list">إعادة ضبط</a>
    </form>

    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; margin-bottom: 12px;">
            <div>
                <h3 style="margin: 0;">سجل العملاء</h3>
                <p class="muted" style="margin: 4px 0 0;">الديون المفتوحة أصبحت في صفحة مستقلة من إدارة العملاء.</p>
            </div>
            <span class="badge">المعروض <?= e(count($pagedCustomers)) ?> من <?= e($customerTotal) ?></span>
        </div>
        <table>
            <thead><tr><th>الاسم</th><th>الهاتف</th><th>تاريخ الميلاد</th><th>المصدر</th><th>الفرع التابع</th><th>تاريخ الإضافة</th><th>ملاحظات</th><th>إجراء</th></tr></thead>
            <tbody>
                <?php if (!$pagedCustomers): ?>
                    <tr><td colspan="7" class="muted" style="text-align: center;">لا توجد نتائج مطابقة.</td></tr>
                <?php endif; ?>
                <?php foreach ($pagedCustomers as $c): ?>
                    <tr>
                        <td><?= e($c['name']) ?></td>
                        <td><?= e($c['phone']) ?></td>
                        <td><?= e($c['birthdate'] ? date('d-m-Y', strtotime($c['birthdate'])) : '-') ?></td>
                        <td><?= e($c['source']) ?></td>
                        <td><?= e($c['location_name'] ?? 'غير محدد') ?></td>
                        <td><?= e(format_datetime($c['created_at'])) ?></td>
                        <td><?= e($c['notes']) ?></td>
                        <td>
                            <a class="btn small" href="index.php?r=customer_view&id=<?= e($c['id']) ?>">ملف العميل</a>
                            <?php if (has_permission('customers_edit')): ?>
                                <form method="post" class="inline" onsubmit="return confirm('هل أنت متأكد من رغبتك في حذف هذا العميل؟')">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                                    <button class="btn small danger">حذف</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php $renderPagination('page', $customerPage, $customerPages, $customerTotal); ?>
    </section>
<?php endif; ?>
