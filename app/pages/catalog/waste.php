<?php
$products = all_products();
$locations = stock_locations();
$userLocationId = current_user_location_id();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
}
$rows = all_wasted_products($userLocationId);
?>
<section class="page-head">
    <h2>الهالك والمنتجات التالفة</h2>
    <p>تسجيل وإدارة المنتجات التالفة وخصمها من رصيد الفرع أو المخزن.</p>
</section>

<?php if (has_permission('inventory_adjust')): ?>
    <form class="panel grid-form" method="post" onsubmit="return confirm('هل أنت متأكد من رغبتك في تسجيل هذا الهالك وخصمه من رصيد الموقع؟')">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>الموقع / الفرع
            <?php if ($userLocationId === null): ?>
            <select name="location_id" required>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= e($l['id']) ?>"><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
                <select name="location_id" disabled>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= e($l['id']) ?>" <?= $userLocationId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="location_id" value="<?= $userLocationId ?>">
            <?php endif; ?>
        </label>
        <label>المنتج التالف
            <select name="product_id" required>
                <?php foreach ($products as $p): ?>
                    <option value="<?= e($p['id']) ?>">
                        <?= e($p['name']) ?> (<?= e($p['type'] === 'bottle' ? 'زجاجة' : ($p['type'] === 'perfume_gram' ? 'زيت عطر' : 'منتج')) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>الكمية التالفة
            <input name="quantity" type="number" step="any" min="0.001" placeholder="مثال: 0.5 أو 5" required>
        </label>
        <label>سبب التلف / الهالك
            <input name="reason" placeholder="مثال: كسر، انسكاب، انتهاء صلاحية" required>
        </label>
        <button class="btn primary">تسجيل هالك وتنزيل الرصيد</button>
    </form>
<?php endif; ?>

<div class="panel">
    <h3>سجل الهالك والتالف</h3>
    <table>
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>المنتج</th>
                <th>الموقع / الفرع</th>
                <th>الكمية التالفة</th>
                <th>سبب التلف</th>
                <th>المسؤول</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6" class="muted" style="text-align: center;">لا يوجد أي سجلات هالك حتى الآن.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e($r['created_at']) ?></td>
                        <td><strong><?= e($r['product_name']) ?></strong></td>
                        <td><?= e($r['location_name']) ?></td>
                        <td style="color: var(--danger); font-weight: bold;">-<?= e(qty($r['quantity'])) ?></td>
                        <td><?= e($r['reason']) ?></td>
                        <td><?= e($r['user_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
