<?php
$userLocationId = current_user_location_id();
$locations = sale_locations();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
}

$selectedDate = (string) ($_GET['date'] ?? $_GET['target_date'] ?? date('Y-m-d'));
$selectedLocation = isset($_GET['location_id']) && $_GET['location_id'] !== '' ? (int) $_GET['location_id'] : null;
if ($userLocationId !== null) {
    $selectedLocation = $userLocationId;
}

$tiers = target_commission_tiers(false);
$tierRows = $tiers;
$tierRows[] = ['min_sales' => '', 'max_sales' => '', 'commission_amount' => '', 'is_active' => 1];
$tierRows[] = ['min_sales' => '', 'max_sales' => '', 'commission_amount' => '', 'is_active' => 1];
$branchRows = target_branch_summary_rows($selectedDate, $selectedLocation);
$employeeRows = target_employee_commission_rows($selectedDate, $selectedLocation);

function target_minutes_label(int $minutes): string
{
    $hours = intdiv($minutes, 60);
    $mins = $minutes % 60;
    if ($hours > 0) {
        return $hours . ' ساعة ' . $mins . ' دقيقة';
    }
    return $mins . ' دقيقة';
}
?>
<section class="page-head">
    <div>
        <h2>شرائح التارجت والعمولات</h2>
        <p>يتم احتساب عمولة الموظف من مبيعات الفواتير المكتملة أثناء فترات حضوره في نفس الفرع.</p>
    </div>
</section>

<form class="panel" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="save_tiers">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
        <div>
            <h3 style="margin:0 0 4px;">شرائح العمولة العامة</h3>
            <p class="muted" style="margin:0;">اترك الحد الأعلى فارغاً للشريحة المفتوحة. يتم تجاهل الصفوف غير الصالحة.</p>
        </div>
        <button class="btn primary">حفظ الشرائح</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>من مبيعات</th>
                <th>إلى أقل من</th>
                <th>مبلغ العمولة</th>
                <th style="text-align:center;">نشطة</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tierRows as $idx => $tier): ?>
                <tr>
                    <td><input name="min_sales[]" type="number" step="0.01" min="0" value="<?= e($tier['min_sales']) ?>" style="width:130px;"></td>
                    <td><input name="max_sales[]" type="number" step="0.01" min="0" value="<?= e($tier['max_sales']) ?>" placeholder="مفتوح" style="width:130px;"></td>
                    <td><input name="commission_amount[]" type="number" step="0.01" min="0" value="<?= e($tier['commission_amount']) ?>" style="width:130px;"></td>
                    <td style="text-align:center;"><input type="checkbox" name="is_active[<?= e($idx) ?>]" value="1" <?= (int) ($tier['is_active'] ?? 1) === 1 ? 'checked' : '' ?>></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</form>

<div class="panel toolbar" style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
    <form method="get" style="display:flex; align-items:end; gap:10px; flex-wrap:wrap; margin:0;">
        <input type="hidden" name="r" value="targets">
        <label>التاريخ
            <input name="date" type="date" value="<?= e($selectedDate) ?>">
        </label>
        <label>الفرع
            <select name="location_id" <?= $userLocationId !== null ? 'disabled' : '' ?>>
                <option value="">كل الفروع</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?= e($location['id']) ?>" <?= $selectedLocation === (int) $location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($userLocationId !== null): ?>
            <input type="hidden" name="location_id" value="<?= e($userLocationId) ?>">
        <?php endif; ?>
        <button class="btn">تصفية</button>
    </form>
    <p class="muted" style="margin:0;">إذا كان موظفان حاضرين وقت نفس البيع، يتم احتساب قيمة البيع كاملة لكل موظف في عمولته.</p>
</div>

<div class="panel">
    <h3>ملخص الفروع ليوم <?= e($selectedDate) ?></h3>
    <table>
        <thead>
            <tr>
                <th>الفرع</th>
                <th>مبيعات اليوم</th>
                <th>عدد الموظفين الحاضرين</th>
                <th>إجمالي عمولات اليوم</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$branchRows): ?>
                <tr><td colspan="4" class="muted" style="text-align:center;">لا توجد بيانات لهذا الفلتر.</td></tr>
            <?php endif; ?>
            <?php foreach ($branchRows as $row): ?>
                <tr>
                    <td><?= e($row['location_name']) ?></td>
                    <td><?= money($row['daily_sales']) ?></td>
                    <td><?= e($row['present_employees']) ?></td>
                    <td><strong><?= money($row['total_commissions']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="panel">
    <h3>عمولات الموظفين حسب الحضور</h3>
    <table>
        <thead>
            <tr>
                <th>الموظف</th>
                <th>الفرع</th>
                <th>مدة الحضور</th>
                <th>عدد الفترات</th>
                <th>مبيعات أثناء الحضور</th>
                <th>العمولة المستحقة</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$employeeRows): ?>
                <tr><td colspan="6" class="muted" style="text-align:center;">لا يوجد موظفون حاضرون لهذا اليوم.</td></tr>
            <?php endif; ?>
            <?php foreach ($employeeRows as $row): ?>
                <tr>
                    <td><strong><?= e($row['user_name']) ?></strong></td>
                    <td><?= e($row['location_name']) ?></td>
                    <td><?= e(target_minutes_label((int) $row['present_minutes'])) ?></td>
                    <td><?= e($row['intervals_count']) ?></td>
                    <td><?= money($row['attended_sales']) ?></td>
                    <td><strong><?= money($row['commission_amount']) ?></strong></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
