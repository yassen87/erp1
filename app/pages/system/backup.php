<?php $files = backup_files(); ?>
<section class="page-head"><h2>النسخ الاحتياطي</h2><p>إنشاء نسخة SQL كاملة من قاعدة البيانات وحفظها محلياً داخل storage/backups.</p></section>
<form class="panel" method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <p class="muted">النسخة تشمل الجداول والبيانات الحالية. احفظ مجلد backups خارج الجهاز بشكل دوري للحماية.</p>
    <div style="display:flex; gap:10px; flex-wrap: wrap; align-items:center;">
        <button class="btn primary">إنشاء نسخة احتياطية الآن</button>
        <button class="btn danger" name="action" value="reset" onclick="return confirm('هل أنت متأكد؟ سيتم حذف جميع البيانات وإعادة إنشاء النظام من البداية.')">إفراغ البيانات وإعادة التهيئة</button>
    </div>
</form>
<div class="panel"><table><thead><tr><th>اسم الملف</th><th>الحجم</th><th>تاريخ الإنشاء</th><th>تحميل</th></tr></thead><tbody>
<?php foreach ($files as $file): ?><tr><td><code><?= e($file['name']) ?></code></td><td><?= e(number_format($file['size'] / 1024, 1)) ?> KB</td><td><?= e($file['created_at']) ?></td><td><a class="btn small" href="index.php?r=backup&download=<?= e($file['name']) ?>">تحميل</a></td></tr><?php endforeach; ?>
</tbody></table></div>
