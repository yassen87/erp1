<?php
$userLocationId = current_user_location_id();
$locations = all_locations();
$search = trim((string) ($_GET['q'] ?? ''));
$customerFilter = trim((string) ($_GET['customer_id'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$locationFilter = trim((string) ($_GET['location_id'] ?? ''));
// إظهار عملاء وديون كل الفروع والمخزن معاً
$customers = all_customers([], null);

$debts = debts_rows(null, [
    'q' => $search,
    'customer_id' => $customerFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'location_id' => $locationFilter,
]);

$paginateRows = static function (array $rows, string $pageParam = 'page', int $perPage = 15): array {
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, (int) ($_GET[$pageParam] ?? 1));
    $page = min($page, $pages);

    return [array_slice($rows, ($page - 1) * $perPage, $perPage), $page, $pages, $total];
};

$renderPagination = static function (int $page, int $pages, int $total): void {
    $buildUrl = static function (int $target): string {
        $params = $_GET;
        $params['r'] = 'customers_debts';
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

[$pagedDebts, $debtPage, $debtPages, $debtTotal] = $paginateRows($debts);
?>

<section class="page-head">
    <h2>الديون المفتوحة</h2>
    <p>متابعة الفواتير غير المسددة مع فلترة حسب التاريخ والفرع والعميل أو رقم الفاتورة.</p>
</section>

<div class="panel toolbar" style="justify-content: space-between;">
    <div class="actions">
        <a class="btn" href="index.php?r=customers">العملاء</a>
        <a class="btn primary" href="index.php?r=customers_debts">الديون المفتوحة</a>
        <button type="button" class="btn" style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: #fff; font-weight: 700;" onclick="openDirectDebtModal()">➕ تسجيل دين مباشر لعميل</button>
    </div>
    <span class="badge">الإجمالي <?= e($debtTotal) ?></span>
</div>

<form class="panel" method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; align-items: end;">
    <input type="hidden" name="r" value="customers_debts">
    <label>بحث<input name="q" value="<?= e($search) ?>" placeholder="عميل / هاتف / فاتورة / سبب"></label>
    <label>العميل
        <select name="customer_id">
            <option value="">كل العملاء</option>
            <?php foreach ($customers as $c): ?>
                <option value="<?= e($c['id']) ?>" <?= $customerFilter === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?><?= $c['phone'] ? ' - ' . e($c['phone']) : '' ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>من تاريخ<input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
    <label>إلى تاريخ<input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
    <?php if ($userLocationId === null): ?>
        <label>الفرع
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
    <a class="btn" href="index.php?r=customers_debts">إعادة ضبط</a>
</form>

<section class="panel">
    <div class="toolbar" style="justify-content: space-between; margin-bottom: 12px;">
        <div>
            <h3 style="margin: 0;">ديون مفتوحة</h3>
            <p class="muted" style="margin: 4px 0 0;">المستخدم المرتبط بفرع يرى ويسدد ديون فرعه فقط، مع إمكانية سداد وتسجيل الديون المباشرة.</p>
        </div>
        <span class="badge">المعروض <?= e(count($pagedDebts)) ?> من <?= e($debtTotal) ?></span>
    </div>
    <table>
        <thead><tr><th>العميل</th><th>الهاتف</th><th>الفرع</th><th>الفاتورة / البيان</th><th>تاريخ الدين</th><th>الأصلي</th><th>المتبقي</th><th>دفعة</th></tr></thead>
        <tbody>
            <?php if (!$pagedDebts): ?>
                <tr><td colspan="8" class="muted" style="text-align: center;">لا توجد ديون مفتوحة مطابقة.</td></tr>
            <?php endif; ?>
            <?php foreach ($pagedDebts as $d): ?>
                <tr>
                    <td><?= e($d['customer_name']) ?></td>
                    <td><?= e($d['phone']) ?></td>
                    <td><?= e($d['location_name']) ?></td>
                    <td>
                        <?php if (empty($d['invoice_id'])): ?>
                            <span class="badge warning" style="font-size: 11.5px; font-weight: 700;">📌 دين مباشر</span>
                            <?php if (!empty($d['notes'])): ?>
                                <div style="font-size: 11.5px; color: var(--muted); margin-top: 3px; max-width: 180px;"><?= e($d['notes']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="index.php?r=invoice_view&id=<?= e($d['invoice_id']) ?>"><?= e($d['invoice_number']) ?></a>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_datetime($d['invoice_created_at'])) ?></td>
                    <td><?= money($d['original_amount']) ?></td>
                    <td><?= money($d['remaining_amount']) ?></td>
                    <td>
                        <?php if (has_permission('customers_pay_debt')): ?>
                            <form method="post" class="inline pay">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="pay_debt">
                                <input type="hidden" name="debt_id" value="<?= e($d['id']) ?>">
                                <input name="amount" type="number" step="1" min="1" max="<?= e($d['remaining_amount']) ?>" placeholder="مبلغ">
                                <select name="method"><option value="cash">كاش</option><option value="instapay">انستا</option><option value="vodafone_cash">فودافون</option></select>
                                <button class="btn small">تسديد</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">غير مصرح</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php $renderPagination($debtPage, $debtPages, $debtTotal); ?>
</section>

<!-- Modal نافذة تسجيل دين مباشر على العميل -->
<div id="direct-debt-modal" style="display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,0.75); backdrop-filter:blur(4px); align-items:center; justify-content:center; padding:16px;">
    <div class="panel" style="max-width:480px; width:100%; border-radius:18px; padding:24px; box-shadow:0 25px 50px rgba(0,0,0,0.6); position:relative; text-align:right;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--line); padding-bottom:10px;">
            <h3 style="margin:0; font-size:17px; font-weight:800; color:var(--primary);">➕ تسجيل دين مباشر على عميل</h3>
            <button type="button" onclick="closeDirectDebtModal()" style="background:none; border:none; font-size:24px; cursor:pointer; color:var(--muted); line-height:1;">&times;</button>
        </div>
        <p class="muted" style="font-size:13px; margin-bottom:18px;">
            تسجيل دين مباشر على حساب العميل دون الحاجة لإنشاء فاتورة أو اختيار منتجات (سلفة، باقي حساب سابق، إلخ).
        </p>
        <form method="post" action="index.php?r=customers_debts">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_direct_debt">

            <label style="display:block; margin-bottom:14px;">
                <span style="font-weight:700; display:block; margin-bottom:6px;">اختر العميل *</span>
                <select name="customer_id" required style="width:100%;">
                    <option value="">-- اختر العميل من القائمة --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?><?= $c['phone'] ? ' - ' . e($c['phone']) : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                <label>
                    <span style="font-weight:700; display:block; margin-bottom:6px;">مبلغ الدين (ج.م) *</span>
                    <input name="amount" type="number" step="0.5" min="1" required placeholder="المبلغ" style="width:100%;">
                </label>
                <label>
                    <span style="font-weight:700; display:block; margin-bottom:6px;">الفرع</span>
                    <select name="location_id" style="width:100%;">
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= e($l['id']) ?>" <?= (int)$l['id'] === (int)$userLocationId ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label style="display:block; margin-bottom:20px;">
                <span style="font-weight:700; display:block; margin-bottom:6px;">سبب / بيان الدين (اختياري)</span>
                <input name="notes" placeholder="مثال: باقي حساب سابق / أخذ سلفة / حساب قديم" style="width:100%;">
            </label>

            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn" onclick="closeDirectDebtModal()">إلغاء</button>
                <button type="submit" class="btn primary" style="font-weight:800;">تسجيل وحفظ الدين</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDirectDebtModal() {
    const modal = document.getElementById('direct-debt-modal');
    if (modal) modal.style.display = 'flex';
}
function closeDirectDebtModal() {
    const modal = document.getElementById('direct-debt-modal');
    if (modal) modal.style.display = 'none';
}
</script>

