<?php

declare(strict_types=1);

require_login();
if (!has_permission('settings')) {
    http_response_code(403);
    echo '<div class="alert danger">غير مصرح لك بالوصول لإدارة تحديثات النظام.</div>';
    return;
}

$currentUser = current_user();
$updates = all_system_updates(50);
$latest = get_latest_system_update();
?>

<section class="page-head hero" style="margin-bottom: 22px;">
    <div>
        <p class="eyebrow">إدارة النظام والبث المباشر</p>
        <h2>📢 تحديثات النظام وتنبيهات الأجهزة المفتوحة</h2>
        <p>عند نشر أي تحديث جديد هنا، ستصل نافذة تنبيه فورية مباشرة على شاشات جميع الأجهزة المفتوحة في الفروع والكاشير تطلب منهم تحديث الصفحة.</p>
    </div>
</section>

<!-- البطاقات الإحصائية -->
<section class="cards" style="margin-bottom: 22px;">
    <article>
        <span>إجمالي التحديثات المنشورة</span>
        <strong><?= e(count($updates)) ?></strong>
    </article>
    <article>
        <span>آخر تحديث معتمد</span>
        <strong style="font-size: 16px;"><?= $latest ? e($latest['title']) : 'لا يوجد بعد' ?></strong>
    </article>
    <article>
        <span>تاريخ آخر بث</span>
        <strong><?= $latest ? e(format_datetime($latest['created_at'])) : '-' ?></strong>
    </article>
</section>

<div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 24px; align-items: start;">
    <!-- النموذج: نشر تحديث جديد -->
    <div class="panel">
        <h3 style="margin-top: 0; margin-bottom: 16px; display: flex; align-items: center; gap: 8px;">
            <span>🚀 نشر تحديث جديد وبثه لجميع الشاشات</span>
        </h3>
        <p class="muted" style="font-size: 13px; margin-bottom: 18px;">
            بمجرد الضغط على زر النشر، سيظهر إشعار فوري لجميع الكاشيرات والموظفين المفتوح لديهم السيستم حالياً يوضح ما تم تعديله ويحثهم على عمل تحديث للصفحة (F5).
        </p>

        <form method="post" action="index.php?r=system_updates">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="publish_update">

            <label style="display: block; margin-bottom: 14px;">
                <span style="font-weight: 700; display: block; margin-bottom: 6px;">عنوان التحديث *</span>
                <input name="title" required placeholder="مثال: إضافة ميزة تسجيل الديون المباشرة / تحديث نصر أكتوبر" style="width: 100%;">
            </label>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;">
                <label>
                    <span style="font-weight: 700; display: block; margin-bottom: 6px;">رقم الإصدار (اختياري)</span>
                    <input name="version" placeholder="v2.5 أو 2026.10" style="width: 100%;">
                </label>
                <label>
                    <span style="font-weight: 700; display: block; margin-bottom: 6px;">مستوى التحديث</span>
                    <select name="urgency" style="width: 100%;">
                        <option value="important">تحديث مهم (موصى به)</option>
                        <option value="critical">تحديث جوهري / عاجل</option>
                        <option value="normal">تحديث عادي / تحسينات عامة</option>
                    </select>
                </label>
            </div>

            <label style="display: block; margin-bottom: 18px;">
                <span style="font-weight: 700; display: block; margin-bottom: 6px;">تفاصيل التعديلات والجديد في النظام *</span>
                <textarea name="content" rows="5" required placeholder="اكتب هنا التعديلات التي قمت بها بالتفصيل، مثل:&#10;1- إتاحة تسجيل ديون العملاء مباشرة بدون فاتورة أو منتج.&#10;2- إضافة بانر نصر أكتوبر في شاشة الكاشير.&#10;3- تحسين سرعة السيستم." style="width: 100%; font-family: inherit; line-height: 1.5; padding: 10px;"></textarea>
            </label>

            <button type="submit" class="btn primary" style="width: 100%; padding: 14px; font-weight: 800; font-size: 15px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <span>📢 نشر التحديث وتنبيه كل الأجهزة المفتوحة الآن</span>
            </button>
        </form>
    </div>

    <!-- سجل التحديثات السابقة -->
    <div class="panel">
        <h3 style="margin-top: 0; margin-bottom: 16px;">
            <span>📋 سجل التحديثات السابقة (<?= e(count($updates)) ?>)</span>
        </h3>

        <?php if (!$updates): ?>
            <div class="muted" style="text-align: center; padding: 30px;">لم يتم تسجيل أو بث أي تحديثات حتى الآن.</div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 14px; max-height: 650px; overflow-y: auto; padding-left: 6px;">
                <?php foreach ($updates as $up): ?>
                    <div style="border: 1px solid var(--line); border-radius: 12px; padding: 14px; background: rgba(0,0,0,0.02); position: relative;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                            <div>
                                <strong style="font-size: 15px; color: var(--primary);"><?= e($up['title']) ?></strong>
                                <?php if (!empty($up['version'])): ?>
                                    <span class="badge small" style="margin-right: 6px;"><?= e($up['version']) ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="badge <?= $up['urgency'] === 'critical' ? 'danger' : ($up['urgency'] === 'important' ? 'warning' : '') ?>" style="font-size: 11px;">
                                <?= $up['urgency'] === 'critical' ? 'جوهري' : ($up['urgency'] === 'important' ? 'مهم' : 'عادي') ?>
                            </span>
                        </div>

                        <div style="font-size: 13.5px; color: var(--ink); line-height: 1.6; white-space: pre-line; margin-bottom: 10px; background: rgba(255,255,255,0.03); padding: 8px 12px; border-radius: 8px;">
                            <?= e($up['content']) ?>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: var(--muted);">
                            <span>بواسطة: <?= e($up['author_name'] ?: 'الإدارة') ?> | <?= e(format_datetime($up['created_at'])) ?></span>
                            
                            <form method="post" action="index.php?r=system_updates" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا التحديث؟');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_update">
                                <input type="hidden" name="id" value="<?= e($up['id']) ?>">
                                <button class="btn small danger" style="padding: 2px 8px; font-size: 11px;">حذف</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
