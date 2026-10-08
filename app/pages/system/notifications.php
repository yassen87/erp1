<?php $rows = notification_rows(); ?>
<section class="page-head"><h2>التنبيهات</h2><p>تنبيهات المخزون، الديون، التارجت، وحضور الموظفين حسب بيانات التشغيل الحالية.</p></section>
<section class="cards">
    <article><span>إجمالي التنبيهات</span><strong><?= e(count($rows)) ?></strong></article>
    <article><span>عروض تنتهي قريباً ⏳</span><strong><?= e(count(array_filter($rows, fn($r) => $r['type'] === 'عروض'))) ?></strong></article>
    <article><span>مخزون منخفض</span><strong><?= e(count(array_filter($rows, fn($r) => $r['type'] === 'مخزون'))) ?></strong></article>
    <article><span>ديون متأخرة</span><strong><?= e(count(array_filter($rows, fn($r) => $r['type'] === 'ديون'))) ?></strong></article>
</section>
<div class="panel"><table><thead><tr><th>النوع</th><th>العنوان</th><th>التفاصيل</th><th>الأهمية</th><th>التاريخ</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['type']) ?></td><td><?= e($row['title']) ?></td><td><?= e($row['details']) ?></td><td><span class="badge"><?= e($row['severity']) ?></span></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
