<?php
$locations = all_locations_with_inactive();
$editLocation = isset($_GET['edit']) ? find_location_any((int) $_GET['edit']) : null;
$typeLabels = [
    'branch' => 'فرع بيع',
    'warehouse' => 'مخزن',
    'online' => 'أونلاين',
];
?>
<section class="page-head">
    <div>
        <h2>الفروع والمواقع</h2>
        <p>إضافة وتعديل الفروع والمخازن وموقع الأونلاين المستخدمين في البيع والمخزون والحضور.</p>
    </div>
</section>


<div class="panel">
    <table>
        <thead>
            <tr>
                <th>الاسم</th>
                <th>النوع</th>
                <th>إحداثيات الحضور</th>
                <th>النطاق</th>
                <th>الحالة</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($locations as $location): ?>
                <tr>
                    <td><strong><?= e($location['name']) ?></strong></td>
                    <td><?= e($typeLabels[$location['type']] ?? $location['type']) ?></td>
                    <td><?= $location['latitude'] !== null && $location['longitude'] !== null ? e($location['latitude'] . ', ' . $location['longitude']) : '<span class="muted">غير محدد</span>' ?></td>
                    <td><?= e($location['geo_radius_m']) ?> متر</td>
                    <td><span class="badge"><?= (int) $location['is_active'] === 1 ? 'نشط' : 'معطل' ?></span></td>
                    <td class="actions">
                        <a class="btn small" href="index.php?r=locations&edit=<?= e($location['id']) ?>">تعديل</a>
                        <form method="post" class="inline" onsubmit="return confirm('هل تريد تغيير حالة هذا الفرع/الموقع؟')">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= e($location['id']) ?>">
                            <input type="hidden" name="action" value="<?= (int) $location['is_active'] === 1 ? 'deactivate' : 'activate' ?>">
                            <button class="btn small <?= (int) $location['is_active'] === 1 ? 'danger' : 'primary' ?>"><?= (int) $location['is_active'] === 1 ? 'تعطيل' : 'تفعيل' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
