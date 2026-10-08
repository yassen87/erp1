<?php $files = backup_files(); ?>
<section class="page-head"><h2>النسخ الاحتياطي والاسترجاع</h2><p>إنشاء نسخة SQL كاملة أو استرجاع بيانات من ملف موجود.</p></section>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; align-items: start;">
    <form class="panel" method="post">
        <h3>إنشاء نسخة أو إفراغ النظام</h3>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <p class="muted">النسخة تشمل الجداول والبيانات الحالية.</p>
        <div style="display:flex; gap:10px; flex-wrap: wrap; margin-top: 20px;">
            <button class="btn primary">إنشاء نسخة احتياطية الآن</button>
            <button class="btn danger" name="action" value="reset" onclick="return confirm('هل أنت متأكد؟ سيتم حذف جميع البيانات!')">إفراغ البيانات بالكامل</button>
        </div>
    </form>

    <form class="panel" method="post" enctype="multipart/form-data">
        <h3>استرجاع بيانات (Restore)</h3>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="restore">
        <p class="muted">قم برفع ملف SQL لاسترجاع كافة البيانات السابقة.</p>
        <div style="margin-top: 15px;">
            <input type="file" name="sql_file" accept=".sql" required style="display:block; margin-bottom:15px; width:100%;">
            <button class="btn primary" onclick="return confirm('استرجاع البيانات سيستبدل البيانات الحالية. هل أنت متأكد؟')">استرجاع البيانات الآن</button>
        </div>
    </form>
</div>

<div class="panel" style="margin-top: 20px;">
    <h3>النسخ المحفوظة على السيرفر</h3>
    <table><thead><tr><th>اسم الملف</th><th>الحجم</th><th>تاريخ الإنشاء</th><th>تحميل</th></tr></thead><tbody>
    <?php foreach ($files as $file): ?><tr><td><code><?= e($file['name']) ?></code></td><td><?= e(number_format($file['size'] / 1024, 1)) ?> KB</td><td><?= e($file['created_at']) ?></td><td><a class="btn small" href="index.php?r=backup&download=<?= e($file['name']) ?>">تحميل</a></td></tr><?php endforeach; ?>
    </tbody></table>
</div>
