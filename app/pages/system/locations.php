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

<form method="post" class="panel grid-form" style="border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:var(--shadow);">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <?php if ($editLocation): ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= e($editLocation['id']) ?>">
    <?php else: ?>
        <input type="hidden" name="action" value="add">
    <?php endif; ?>

    <label><?= e(__('اسم الفرع / الموقع')) ?>
        <input name="name" required value="<?= e($editLocation['name'] ?? '') ?>" placeholder="مثال: ام خنان أو المنوات" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;">
    </label>

    <label><?= e(__('نوع الموقع')) ?>
        <select name="type" required style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit; outline:none;">
            <option value="branch" <?= ($editLocation['type'] ?? '') === 'branch' ? 'selected' : '' ?>>فرع بيع (كاشير ومخزون)</option>
            <option value="warehouse" <?= ($editLocation['type'] ?? '') === 'warehouse' ? 'selected' : '' ?>>مخزن رئيسي</option>
            <option value="online" <?= ($editLocation['type'] ?? '') === 'online' ? 'selected' : '' ?>>أونلاين</option>
        </select>
    </label>

    <label><?= e(__('خط العرض (Latitude)')) ?>
        <input name="latitude" type="text" value="<?= e($editLocation['latitude'] ?? '') ?>" placeholder="اختياري للحضور الجغرافي" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;">
    </label>

    <label><?= e(__('خط الطول (Longitude)')) ?>
        <input name="longitude" type="text" value="<?= e($editLocation['longitude'] ?? '') ?>" placeholder="اختياري للحضور الجغرافي" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;">
    </label>

    <label><?= e(__('نطاق الحضور (بالمتر)')) ?>
        <input name="geo_radius_m" type="number" min="10" step="10" value="<?= e($editLocation['geo_radius_m'] ?? 100) ?>" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;">
    </label>

    <div style="display: flex; gap: 8px; align-self: end;">
        <button class="btn primary" style="height:38px; border-radius:8px; font-weight:bold;">
            <?= $editLocation ? e(__('حفظ تعديلات الفرع')) : e(__('إضافة فرع / موقع جديد')) ?>
        </button>
        <?php if ($editLocation): ?>
            <a href="index.php?r=locations" class="btn secondary" style="height:38px; line-height:22px; border-radius:8px;"><?= e(__('إلغاء')) ?></a>
        <?php endif; ?>
    </div>
</form>


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
