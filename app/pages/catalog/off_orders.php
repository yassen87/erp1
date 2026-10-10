<?php
$formulas = get_off_order_formulas(current_user_location_id());
?>
<section class="page-head">
    <h2>المرتجعات الجاهزة (Off Order)</h2>
    <p>جميع التركيبات والزجاجات التي تم استرجاعها من العملاء وهي متوفرة حالياً بالفرع لإعادة البيع.</p>
</section>

<div class="panel">
    <?php if (empty($formulas)): ?>
        <p class="muted" style="text-align: center; padding: 30px;">لا توجد تركيبات مرتجعة حالياً.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>تاريخ المرتجع</th>
                    <th>رقم الفاتورة الأصلية</th>
                    <th>وصف التركيبة</th>
                    <th>الفرع</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($formulas as $f): ?>
                    <tr>
                        <td><span dir="ltr"><?= e(date('Y-m-d H:i', strtotime($f['invoice_date']))) ?></span></td>
                        <td><?= e($f['invoice_number']) ?></td>
                        <td><?= e($f['description']) ?></td>
                        <td><?= e($f['location_name'] ?? 'غير محدد') ?></td>
                        <td>
                            <a href="index.php?r=pos&load_off_order=<?= (int)$f['id'] ?>" class="btn primary small" onclick="return confirm('هل تريد تحميل هذه التركيبة إلى الكاشير لإعادة بيعها؟')">
                                تعديل وإعادة بيع
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
