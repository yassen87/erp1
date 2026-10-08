<?php
$settings = [];
foreach (settings_rows() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$defaultBirthdayMsg = "كل عام وأنت بخير وبصحة وسعادة أستاذ/ة {name} 🎂🌹\nأسرة *{shop_name}* تتمنى لك عاماً سعيداً مليئاً بالبهجة والنجاح والبركة! ✨\n🎁 بهذه المناسبة السعيدة، يسعدنا أن نهديك خصم خاص 20% على أي زجاجة عطر من اختيارك عند زيارتك القادمة لنا! 🎉";

$generalLabels = [
    'shop_name' => 'اسم المحل / العلامة التجارية (يظهر في الرسائل والفواتير)',
    'debt_alert_days' => 'عدد أيام تنبيه الدين المتأخر',
    'target_midmonth_threshold' => 'نسبة التارجت المطلوبة في منتصف الشهر (%)',
    'allow_negative_stock' => 'السماح بالمخزون السالب (1 مسموح / 0 ممنوع)',
    'payment_vodafone_number' => 'رقم فودافون كاش الموحد لكل الفروع',
    'payment_instapay_account' => 'حساب/رقم إنستا باي الموحد لكل الفروع',
];
?>
<section class="page-head">
    <h2>الإعدادات العامة وإعدادات الواتساب</h2>
    <p>إدارة تنبيهات النظام، وسائل الدفع، والرسائل التلقائية للفواتير وأعياد الميلاد.</p>
</section>

<form method="post" style="display: flex; flex-direction: column; gap: 20px;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <!-- كارت إعدادات الواتساب وإرسال الفواتير وأعياد الميلاد -->
    <div class="panel" style="border: 2px solid rgba(37, 211, 102, 0.4); border-radius: 14px; position: relative;">
        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 14px; margin-bottom: 18px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 26px;">💬</span>
                <div>
                    <h3 style="margin: 0; color: #25D366; font-size: 18px;">إعدادات الواتساب التلقائي (الفواتير وأعياد الميلاد)</h3>
                    <p class="muted" style="margin: 2px 0 0 0; font-size: 13px;">التحكم في إرسال الفاتورة تلقائياً للعميل ورسائل تهنئة عيد الميلاد المخصصة.</p>
                </div>
            </div>
            <a href="index.php?r=call_center" class="btn small" style="background: rgba(37, 211, 102, 0.15); color: #25D366; border: 1px solid #25D366;">
                فتح الكول سنتر 📞
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 18px;">
            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 6px;">
                    إرسال الفاتورة تلقائياً بعد كل عملية بيع في الكاشير:
                </label>
                <select name="settings[whatsapp_invoice_enabled]" style="width: 100%; padding: 10px; border-radius: 8px; background: var(--bg-card, #1f2937); color: #fff; border: 1px solid rgba(255,255,255,0.15);">
                    <option value="1" <?= ($settings['whatsapp_invoice_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>✅ مفعل (إرسال تلقائي فوري)</option>
                    <option value="0" <?= ($settings['whatsapp_invoice_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>❌ معطل</option>
                </select>
                <span class="muted" style="font-size: 12px;">يرسل تفاصيل الأصناف، الأسعار، الإجمالي، والمدفوع إلى رقم العميل.</span>
            </div>

            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 6px;">
                    تفعيل إرسال تهنئة أعياد ميلاد العملاء تلقائياً:
                </label>
                <select name="settings[whatsapp_birthday_enabled]" style="width: 100%; padding: 10px; border-radius: 8px; background: var(--bg-card, #1f2937); color: #fff; border: 1px solid rgba(255,255,255,0.15);">
                    <option value="1" <?= ($settings['whatsapp_birthday_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>✅ مفعل (إرسال تلقائي)</option>
                    <option value="0" <?= ($settings['whatsapp_birthday_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>❌ معطل</option>
                </select>
                <span class="muted" style="font-size: 12px;">يرسل الرسالة المخصصة في يوم ميلاد العميل (يمنع التكرار لنفس العميل في نفس العام).</span>
            </div>
            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 6px;">
                    إرفاق لوجو البراند تلقائياً مع الرسائل والفواتير:
                </label>
                <select name="settings[whatsapp_attach_logo]" style="width: 100%; padding: 10px; border-radius: 8px; background: var(--bg-card, #1f2937); color: #fff; border: 1px solid rgba(255,255,255,0.15);">
                    <option value="1" <?= ($settings['whatsapp_attach_logo'] ?? '1') === '1' ? 'selected' : '' ?>>✅ مفعل (إرفاق لوجو حمزة للعطور تلقائياً)</option>
                    <option value="0" <?= ($settings['whatsapp_attach_logo'] ?? '1') === '0' ? 'selected' : '' ?>>❌ معطل (نص فقط)</option>
                </select>
                <span class="muted" style="font-size: 12px; display: flex; align-items: center; gap: 8px; margin-top: 4px;">
                    <img src="assets/whatsapp_logo.png?v=<?= time() ?>" alt="لوجو حمزة" style="height: 28px; width: auto; object-fit: contain; background: #fff; border-radius: 4px; padding: 2px;">
                    <span>يتم إرسال اللوجو الذهبي تلقائياً كصورة مع نص الفاتورة أو التهنئة.</span>
                </span>
            </div>
            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 6px;">
                    رابط خدمة الواتساب (API URL):
                </label>
                <input name="settings[whatsapp_api_url]" value="<?= e($settings['whatsapp_api_url'] ?? 'https://erp.transyshub.tech/api/send-message') ?>" placeholder="https://erp.transyshub.tech/api/send-message" style="width: 100%; padding: 10px; border-radius: 8px; background: var(--bg-card, #1f2937); color: #fff; border: 1px solid rgba(255,255,255,0.15); direction: ltr; text-align: left; font-family: monospace;">
                <span class="muted" style="font-size: 12px;">رابط خدمة الواتساب الآمن عبر HTTPS.</span>
            </div>
        </div>

        <!-- نص رسالة عيد الميلاد المخصصة -->
        <div style="margin-top: 14px; background: rgba(0,0,0,0.2); padding: 16px; border-radius: 10px; border: 1px dashed rgba(201, 168, 76, 0.4);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <label style="font-weight: bold; color: var(--gold, #C9A84C); font-size: 15px;">
                    ✍️ نص رسالة عيد الميلاد (اكتب الرسالة التي تريدها بيدك هنا):
                </label>
                <span style="font-size: 12px; color: #9ca3af;">
                    الكلمات المتغيرة التلقائية: <code>{name}</code> = اسم العميل &bull; <code>{shop_name}</code> = اسم المحل
                </span>
            </div>
            <textarea name="settings[whatsapp_birthday_message]" rows="5" style="width: 100%; padding: 12px; border-radius: 8px; background: rgba(15,23,42,0.8); color: #fff; border: 1px solid rgba(255,255,255,0.15); font-family: inherit; font-size: 14px; line-height: 1.6; resize: vertical;" placeholder="اكتب رسالة التهنئة هنا..."><?= e($settings['whatsapp_birthday_message'] ?? $defaultBirthdayMsg) ?></textarea>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; flex-wrap: wrap; gap: 8px;">
                <span style="font-size: 13px; color: #9ca3af;">
                    💡 نصيحة: يمكنك كتابة أي عرض خاص أو كود خصم تقدمه للعميل في يوم ميلاده.
                </span>
                <button type="button" class="btn small" onclick="testBirthdayGreetings()" style="background: rgba(37,211,102,0.2); color: #25D366; border: 1px solid #25D366;">
                    🚀 فحص وإرسال تهاني اليوم الآن
                </button>
            </div>
            <div id="bday-test-result" style="margin-top: 10px; font-weight: bold; font-size: 14px;"></div>
        </div>
    </div>

    <!-- كارت الإعدادات العامة -->
    <div class="panel">
        <h3 style="margin-top: 0; margin-bottom: 16px;">⚙️ الإعدادات العامة</h3>
        <div class="grid-form">
            <?php foreach ($generalLabels as $key => $label): ?>
                <label>
                    <?= e($label) ?>
                    <input name="settings[<?= e($key) ?>]" value="<?= e($settings[$key] ?? ($key === 'shop_name' ? 'حمزة للعطور' : '')) ?>">
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div>
        <button class="btn primary" style="font-size: 16px; padding: 12px 32px;">💾 حفظ جميع الإعدادات</button>
    </div>
</form>

<script>
function testBirthdayGreetings() {
    if (!confirm('هل تريد تشغيل فحص أعياد ميلاد اليوم وإرسال التهنئة فوراً للعملاء المطابقين؟')) return;
    const resDiv = document.getElementById('bday-test-result');
    resDiv.innerHTML = '<span style="color:#eab308;">جاري فحص قاعدة البيانات وإرسال الرسائل... ⏳</span>';

    fetch('index.php?r=send_birthday_whatsapp')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.total_today === 0) {
                    resDiv.innerHTML = '<span style="color:#60a5fa;">ℹ️ لا يوجد عملاء يصادف تاريخ ميلادهم اليوم.</span>';
                } else {
                    resDiv.innerHTML = `<span style="color:#25D366;">✅ تم الفحص بنجاح: إجمالي عملاء اليوم (${data.total_today}) | تم إرسال (${data.sent_count}) تهنئة | تم تخطي (${data.skipped_count}) لأنهم استلموا التهنئة مسبقاً.</span>`;
                }
            } else {
                resDiv.innerHTML = '<span style="color:#ef4444;">❌ خطأ: ' + (data.error || 'فشل الإرسال') + '</span>';
            }
        })
        .catch(err => {
            resDiv.innerHTML = '<span style="color:#ef4444;">❌ فشل الاتصال: ' + err.message + '</span>';
        });
}
</script>
