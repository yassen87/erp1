<?php

declare(strict_types=1);

require_login();
if (!has_permission('customers_view')) {
    http_response_code(403);
    echo '<div class="alert danger">غير مصرح لك بالوصول لهذه الصفحة.</div>';
    return;
}

$tab = trim((string)($_GET['tab'] ?? 'today'));
$allowedTabs = ['today', 'month', 'settings', 'logs'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'today';
}

$defaultTemplate = "كل عام وأنت بخير وبصحة وسعادة أستاذ/ة {name} 🎂🌹\nأسرة *{shop_name}* تتمنى لك عاماً سعيداً مليئاً بالبهجة والنجاح والبركة! ✨\n🎁 بهذه المناسبة السعيدة، يسعدنا أن نهديك خصم خاص 20% على أي زجاجة عطر من اختيارك عند زيارتك القادمة لنا! 🎉";

// Settings
$birthdayEnabled = setting_value('whatsapp_birthday_enabled', '1') !== '0';
$birthdaySendTime = (string) setting_value('whatsapp_birthday_send_time', '12:00');
$birthdayMessage = (string) setting_value('whatsapp_birthday_message', $defaultTemplate);
if (empty(trim($birthdayMessage))) {
    $birthdayMessage = $defaultTemplate;
}
$shopName = (string) setting_value('shop_name', 'حمزة للعطور');

// Selected hour
$targetHour = 12;
if (preg_match('/^(\d{1,2})/', trim($birthdaySendTime), $m)) {
    $targetHour = (int)$m[1];
}

// Data for Today
$todayCustomers = today_birthday_customers();
$totalToday = count($todayCustomers);
$sentTodayCount = 0;
$pendingTodayCount = 0;

foreach ($todayCustomers as $c) {
    if (!empty($c['already_sent'])) {
        $sentTodayCount++;
    } else {
        $pendingTodayCount++;
    }
}

// Data for Month (if tab == 'month')
$selectedMonth = max(1, min(12, (int)($_GET['month'] ?? date('n'))));
$monthCustomers = ($tab === 'month') ? month_birthday_customers($selectedMonth) : [];

// Data for Logs (if tab == 'logs')
$birthdayLogs = ($tab === 'logs') ? birthday_logs_history(60) : [];

// Helper to format time in Arabic (down to exact minute)
function format_time_ar(string $timeStr): string {
    $timeStr = trim($timeStr);
    if (!preg_match('/^(\d{1,2}):(\d{2})/', $timeStr, $m)) {
        return $timeStr;
    }
    $hour = (int)$m[1];
    $minute = (int)$m[2];
    $minStr = sprintf('%02d', $minute);
    
    if ($hour === 0) return "12:{$minStr} منتصف الليل";
    if ($hour < 12) return sprintf('%02d:%s صباحاً', $hour, $minStr);
    if ($hour === 12) return "12:{$minStr} ظهراً";
    return sprintf('%02d:%s مساءً', $hour - 12, $minStr);
}

// Helper to calculate age
function calculate_age(?string $birthdate): string {
    if (empty($birthdate)) return '-';
    try {
        $dob = new DateTime($birthdate);
        $today = new DateTime();
        $age = $today->diff($dob)->y;
        return $age > 0 ? "{$age} سنة" : '-';
    } catch (Throwable $e) {
        return '-';
    }
}
?>

<!-- Page Header Hero -->
<section class="page-head hero" style="margin-bottom: 20px;">
    <div>
        <p class="eyebrow">إدارة العملاء والولاء</p>
        <h2>🎂 أعياد ميلاد العملاء وتهاني الواتساب</h2>
        <p>متابعة أعياد ميلاد العملاء يومياً، وتحديد موعد إرسال رسائل التهنئة بحرية كاملة عبر الواتساب مع العروض والخصومات.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <a class="btn <?= $tab === 'today' ? 'primary' : '' ?>" href="index.php?r=customer_birthdays&tab=today">🎂 أعياد ميلاد اليوم (<?= $totalToday ?>)</a>
        <a class="btn <?= $tab === 'month' ? 'primary' : '' ?>" href="index.php?r=customer_birthdays&tab=month">📅 أعياد ميلاد الشهر</a>
        <a class="btn <?= $tab === 'settings' ? 'primary' : '' ?>" href="index.php?r=customer_birthdays&tab=settings">⚙️ ضبط موعد الإرسال والرسالة</a>
        <a class="btn <?= $tab === 'logs' ? 'primary' : '' ?>" href="index.php?r=customer_birthdays&tab=logs">📜 سجل الرسائل</a>
    </div>
</section>

<!-- KPI Stats Bar -->
<section class="cards" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 22px;">
    <div class="dashboard-card-link card-customers-today" style="cursor: default;">
        <span class="card-icon">🎂</span>
        <span class="card-label">أعياد ميلاد اليوم</span>
        <strong class="card-value" style="color: var(--primary);"><?= $totalToday ?></strong>
    </div>

    <div class="dashboard-card-link card-sales" style="cursor: default;">
        <span class="card-icon">✅</span>
        <span class="card-label">تمت التهنئة اليوم</span>
        <strong class="card-value" style="color: #16a34a;"><?= $sentTodayCount ?></strong>
    </div>

    <div class="dashboard-card-link card-debts" style="cursor: default;">
        <span class="card-icon">⏳</span>
        <span class="card-label">بانتظار الإرسال</span>
        <strong class="card-value" style="color: <?= $pendingTodayCount > 0 ? '#eab308' : 'var(--muted)' ?>;"><?= $pendingTodayCount ?></strong>
    </div>

    <div class="dashboard-card-link card-invoices" style="cursor: default;">
        <span class="card-icon">⏰</span>
        <span class="card-label">موعد الإرسال اليومي</span>
        <strong class="card-value" style="font-size: 1.15rem; color: #6366f1;">
            <?= format_time_ar($birthdaySendTime) ?>
        </strong>
    </div>

    <div class="dashboard-card-link card-stock" style="cursor: default;">
        <span class="card-icon">🤖</span>
        <span class="card-label">الإرسال التلقائي</span>
        <strong class="card-value" style="font-size: 1.1rem; color: <?= $birthdayEnabled ? '#16a34a' : '#dc2626' ?>;">
            <?= $birthdayEnabled ? 'مفعل ومجدول ✅' : 'معطل مؤقتاً ⏸️' ?>
        </strong>
    </div>
</section>

<?php if ($tab === 'today'): ?>
    <!-- TAB 1: TODAY'S BIRTHDAYS -->
    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                    <span>🎉 عملاء يصادف عيد ميلادهم اليوم</span>
                    <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #6366f1; font-weight: 700;">
                        <?= date('d/m/Y') ?>
                    </span>
                </h3>
                <p class="muted" style="margin: 4px 0 0;">
                    الموعد المحدد للإرسال اليومي هو: <strong><?= format_time_ar($birthdaySendTime) ?></strong>.
                    يمكنك أيضاً الإرسال فوراً لجميع العملاء أو لعميل بعينه بالضغط على الأزرار أدناه دون أي انتظار.
                </p>
            </div>

            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <?php if ($totalToday > 0): ?>
                    <form method="post" style="display: inline-flex; align-items: center; gap: 8px;" onsubmit="return confirm('هل تريد بالتأكيد إرسال رسائل التهنئة لعملاء اليوم عبر الواتساب الآن؟');">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="send_all_today">
                        <?php if ($pendingTodayCount === 0 && $sentTodayCount > 0): ?>
                            <label style="font-size: 12px; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; margin: 0;">
                                <input type="checkbox" name="force_resend" value="1">
                                <span>إعادة إرسال لمن أُرسل لهم</span>
                            </label>
                        <?php endif; ?>
                        <button type="submit" class="btn primary" style="box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25); background: #16a34a; border-color: #16a34a;">
                            🚀 إرسال التهنئة للجميع الآن (<?= $pendingTodayCount > 0 ? $pendingTodayCount : $totalToday ?>)
                        </button>
                    </form>
                <?php endif; ?>
                <a class="btn" href="index.php?r=customer_birthdays&tab=settings">⚙️ تغيير الموعد أو نص الرسالة</a>
            </div>
        </div>

        <?php if (empty($todayCustomers)): ?>
            <div style="text-align: center; padding: 45px 20px; background: rgba(0,0,0,0.02); border-radius: 12px; border: 1px dashed var(--line);">
                <div style="font-size: 42px; margin-bottom: 12px;">🎈</div>
                <h4 style="margin: 0 0 6px 0;">لا يوجد عملاء يصادف عيد ميلادهم اليوم</h4>
                <p class="muted" style="max-width: 480px; margin: 0 auto 16px auto;">
                    لم يتم تسجيل أي عميل يحمل تاريخ ميلاد بتاريخ اليوم (<?= date('d/m') ?>). 
                    يمكنك استعراض أعياد ميلاد باقي أيام الشهر من تبويب "أعياد ميلاد الشهر".
                </p>
                <a class="btn" href="index.php?r=customer_birthdays&tab=month">استعراض أعياد ميلاد الشهر 📅</a>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>العميل</th>
                            <th>رقم الواتساب</th>
                            <th>تاريخ الميلاد</th>
                            <th>السن</th>
                            <th>الفرع التابع</th>
                            <th>حالة الإرسال لعام <?= date('Y') ?></th>
                            <th style="text-align: center;">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayCustomers as $c): ?>
                            <tr>
                                <td>
                                    <strong><a href="index.php?r=customer_view&id=<?= e($c['id']) ?>"><?= e($c['name']) ?></a></strong>
                                </td>
                                <td>
                                    <span dir="ltr" style="font-family: monospace; font-weight: 600;"><?= e($c['phone']) ?></span>
                                    <a href="https://wa.me/2<?= preg_replace('/\D/', '', $c['phone']) ?>" target="_blank" title="محادثة واتساب ويب مباشرة" style="text-decoration: none; margin-right: 4px;">💬</a>
                                </td>
                                <td><?= date('d/m/Y', strtotime($c['birthdate'])) ?></td>
                                <td><?= calculate_age($c['birthdate']) ?></td>
                                <td><?= e($c['location_name'] ?? 'غير محدد') ?></td>
                                <td>
                                    <?php if (!empty($c['already_sent'])): ?>
                                        <span class="badge" style="background: rgba(22, 163, 74, 0.15); color: #16a34a; font-weight: 700;" title="<?= e($c['last_message'] ?? '') ?>">
                                            ✅ تم الإرسال <?= !empty($c['last_sent_at']) ? '('.date('h:i A', strtotime($c['last_sent_at'])).')' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(234, 179, 8, 0.15); color: #b45309; font-weight: 700;">
                                            ⏳ بانتظار الإرسال (<?= format_time_ar($birthdaySendTime) ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div style="display: inline-flex; gap: 6px; align-items: center;">
                                        <!-- زر إرسال فوري لهذا العميل -->
                                        <button type="button" class="btn small primary" onclick="openSendModal(<?= (int)$c['id'] ?>, '<?= e(addslashes($c['name'])) ?>', '<?= e(addslashes($c['phone'])) ?>')">
                                            💌 إرسال تهنئة
                                        </button>
                                        <a class="btn small" href="index.php?r=customer_view&id=<?= e($c['id']) ?>" title="عرض ملف العميل">
                                            ملف العميل
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($tab === 'settings'): ?>
    <!-- TAB 2: SETTINGS & SCHEDULE HOUR -->
    <section class="panel" style="max-width: 900px; margin: 0 auto;">
        <div class="toolbar" style="margin-bottom: 20px; border-bottom: 1px solid var(--line); padding-bottom: 12px;">
            <div>
                <h3 style="margin: 0;">⚙️ إعدادات الإرسال التلقائي وموعد الساعة اليومي</h3>
                <p class="muted" style="margin: 4px 0 0;">حدد الساعة التي يقوم فيها النظام بإرسال رسائل التهنئة لعملاء اليوم تلقائياً عبر الواتساب.</p>
            </div>
        </div>

        <form method="post" class="grid-form" style="grid-template-columns: 1fr;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_settings">

            <!-- Instant Send Alert Card -->
            <div style="background: rgba(22, 163, 74, 0.07); border: 1px solid rgba(22, 163, 74, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h4 style="margin: 0 0 4px 0; color: #16a34a; display: flex; align-items: center; gap: 6px;">
                        <span>⚡ هل تريد الإرسال فوراً الآن دون انتظار أي موعد؟</span>
                    </h4>
                    <p class="muted" style="margin: 0; font-size: 13px;">
                        يمكنك دائماً إرسال رسائل التهنئة لجميع عملاء اليوم حالاً بضغطة زر واحدة دون انتظار وقت الجدولة اليومي.
                    </p>
                </div>
                <div>
                    <button type="submit" form="instantSendForm" class="btn primary" style="background: #16a34a; border-color: #16a34a; font-weight: 700; box-shadow: 0 4px 10px rgba(22, 163, 74, 0.2);">
                        🚀 إرسال لعملاء اليوم حالاً (<?= $totalToday ?>)
                    </button>
                </div>
            </div>

            <!-- Card 1: Toggle & Unrestricted Time Picker -->
            <div style="background: var(--field-bg, rgba(0,0,0,0.02)); border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 16px;">
                <h4 style="margin: 0 0 14px 0; display: flex; align-items: center; gap: 8px;">
                    <span>⏰ موعد وساعة الإرسال اليومي التلقائي (حر بالكامل دون تقييد)</span>
                </h4>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; align-items: start;">
                    <label style="cursor: pointer; display: flex; align-items: center; gap: 10px; padding: 12px; background: var(--surface); border: 1px solid var(--line); border-radius: 8px;">
                        <input type="checkbox" name="whatsapp_birthday_enabled" value="1" <?= $birthdayEnabled ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                        <div>
                            <strong>تفعيل إرسال رسائل أعياد الميلاد تلقائياً</strong>
                            <div class="muted" style="font-size: 12px;">إذا تم تعطيلها، لن يقوم السيرفر بالإرسال التلقائي في الموعد المحدد.</div>
                        </div>
                    </label>

                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <label for="sendTimeInput">
                            <strong>حدد أي وقت تريده بالساعة والدقيقة:</strong>
                        </label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="time" name="whatsapp_birthday_send_time" id="sendTimeInput" value="<?= e($birthdaySendTime) ?>" required style="font-size: 18px; font-weight: bold; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); flex: 1;" oninput="updateTimePreview(this.value)">
                            <button type="button" class="btn small" onclick="setCurrentTime()" title="ضبط على توقيت الساعة الحالي الآن">🕒 الآن</button>
                        </div>
                        
                        <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-top: 4px;">
                            <span class="muted" style="font-size: 11px;">أوقات شائعة سريعة:</span>
                            <button type="button" class="btn small" onclick="setTimeVal('09:00')">09:00 ص</button>
                            <button type="button" class="btn small" onclick="setTimeVal('11:30')">11:30 ص</button>
                            <button type="button" class="btn small" onclick="setTimeVal('13:00')">01:00 م</button>
                            <button type="button" class="btn small" onclick="setTimeVal('14:30')">02:30 م</button>
                            <button type="button" class="btn small" onclick="setTimeVal('17:00')">05:00 م</button>
                            <button type="button" class="btn small" onclick="setTimeVal('20:00')">08:00 م</button>
                            <button type="button" class="btn small" onclick="setTimeVal('22:00')">10:00 م</button>
                        </div>
                        <small class="muted" style="font-size: 12px; line-height: 1.4;">
                            💡 لست مقيداً بأي اختيارات؛ يمكنك كتابة أو اختيار أي دقيقة وأي ساعة ترغب بها على مدار الـ 24 ساعة.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Card 2: Message Template -->
            <div style="background: var(--field-bg, rgba(0,0,0,0.02)); border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 16px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                    <h4 style="margin: 0; display: flex; align-items: center; gap: 8px;">
                        <span>✍️ قالب رسالة التهنئة والخصم</span>
                    </h4>
                    <button type="button" class="btn small" onclick="resetToDefaultMessage()">استعادة النص الافتراضي</button>
                </div>

                <div style="margin-bottom: 10px; font-size: 13px;">
                    <span>المتغيرات المتاحة للاستخدام في النص (اضغط للنسخ والإدراج):</span>
                    <div style="display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap;">
                        <button type="button" class="badge" onclick="insertVariable('{name}')" style="cursor: pointer; background: rgba(99, 102, 241, 0.15); color: #6366f1; border: none; padding: 4px 10px;">{name} : اسم العميل</button>
                        <button type="button" class="badge" onclick="insertVariable('{shop_name}')" style="cursor: pointer; background: rgba(22, 163, 74, 0.15); color: #16a34a; border: none; padding: 4px 10px;">{shop_name} : اسم المتجر (<?= e($shopName) ?>)</button>
                        <button type="button" class="badge" onclick="insertVariable('{phone}')" style="cursor: pointer; background: rgba(234, 179, 8, 0.15); color: #b45309; border: none; padding: 4px 10px;">{phone} : رقم الهاتف</button>
                    </div>
                </div>

                <label style="display: block;">
                    <textarea name="whatsapp_birthday_message" id="birthdayMessageTextarea" rows="6" style="width: 100%; font-size: 14px; line-height: 1.6; border-radius: 8px;" oninput="updateLivePreview()"><?= e($birthdayMessage) ?></textarea>
                </label>

                <!-- Live WhatsApp Preview Box -->
                <div style="margin-top: 14px; background: #e5ddd5; border-radius: 12px; padding: 16px; border: 1px solid rgba(0,0,0,0.1);">
                    <div style="font-size: 12px; font-weight: 700; color: #075e54; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                        <span>📱 معاينة شكل الرسالة على هاتف العميل (واتساب):</span>
                    </div>
                    <div style="background: #ffffff; border-radius: 8px; padding: 12px 14px; max-width: 480px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); font-size: 14px; line-height: 1.6; color: #111827; white-space: pre-wrap;" id="livePreviewBox"></div>
                </div>
            </div>

            <!-- Card 3: Cron Server Instructions -->
            <div style="background: rgba(99, 102, 241, 0.05); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                <h4 style="margin: 0 0 6px 0; color: #4f46e5;">ℹ️ كيف يعمل الإرسال التلقائي في الموعد المحدد؟</h4>
                <p style="margin: 0 0 10px 0; font-size: 13px; line-height: 1.6;">
                    لكي يرسل السيرفر تلقائياً في أي دقيقة أو ساعة تحددها أعلاه، ضع سطر الفحص التالي في كرون تاب السيرفر:
                </p>
                <div style="background: #1e1e2d; color: #a6accd; padding: 10px 14px; border-radius: 6px; font-family: monospace; font-size: 13px; direction: ltr; text-align: left; user-select: all;">
                    * * * * * php /var/www/html/erp/cron_birthdays.php > /dev/null 2>&1
                </div>
                <small class="muted" style="display: block; margin-top: 6px;">
                    يفحص السيرفر في كل دقيقة، وعند وصول الوقت إلى الموعد الذي حددته أنت (<strong id="cronHourPreview" style="color: #4f46e5;"><?= format_time_ar($birthdaySendTime) ?></strong>) أو بعده مباشرة، يرسل التهنئة فوراً لعملاء اليوم ويسجلهم لمنع تكرار الإرسال لنفس العميل في نفس العام.
                </small>
            </div>

            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a class="btn" href="index.php?r=customer_birthdays&tab=today">إلغاء</a>
                <button type="submit" class="btn primary" style="min-width: 160px; font-weight: 700;">💾 حفظ الإعدادات والموعد</button>
            </div>
        </form>

        <!-- Hidden form for instant send button -->
        <form id="instantSendForm" method="post" onsubmit="return confirm('هل تريد إرسال التهنئة فوراً لجميع عملاء اليوم عبر الواتساب؟');" style="display: none;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="send_all_today">
        </form>
    </section>

<?php elseif ($tab === 'month'): ?>
    <!-- TAB 3: MONTHLY BIRTHDAYS -->
    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0;">📅 استعراض أعياد ميلاد الشهر</h3>
                <p class="muted" style="margin: 4px 0 0;">استعراض جميع عملاء المتجر الذين يصادف عيد ميلادهم في أي يوم من هذا الشهر للتجهيز والمتابعة.</p>
            </div>

            <form method="get" style="display: inline-flex; align-items: center; gap: 8px;">
                <input type="hidden" name="r" value="customer_birthdays">
                <input type="hidden" name="tab" value="month">
                <label style="margin: 0; font-weight: 600;">اختر الشهر:
                    <select name="month" onchange="this.form.submit()" style="font-weight: 600; padding: 6px 10px;">
                        <?php
                        $monthsNames = [
                            1 => 'يناير (01)', 2 => 'فبراير (02)', 3 => 'مارس (03)', 4 => 'أبريل (04)',
                            5 => 'مايو (05)', 6 => 'يونيو (06)', 7 => 'يوليو (07)', 8 => 'أغسطس (08)',
                            9 => 'سبتمبر (09)', 10 => 'أكتوبر (10)', 11 => 'نوفمبر (11)', 12 => 'ديسمبر (12)'
                        ];
                        foreach ($monthsNames as $num => $mName):
                        ?>
                            <option value="<?= $num ?>" <?= $selectedMonth === $num ? 'selected' : '' ?>><?= $mName ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
        </div>

        <?php if (empty($monthCustomers)): ?>
            <div style="text-align: center; padding: 40px 20px; background: rgba(0,0,0,0.02); border-radius: 12px;">
                <p class="muted" style="margin: 0;">لا يوجد عملاء مسجلين بتاريخ ميلاد في هذا الشهر.</p>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 12px;">
                <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;">
                    إجمالي عملاء شهر <?= $monthsNames[$selectedMonth] ?>: <?= count($monthCustomers) ?> عميل
                </span>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>يوم الميلاد</th>
                            <th>العميل</th>
                            <th>رقم الواتساب</th>
                            <th>تاريخ الميلاد</th>
                            <th>السن</th>
                            <th>الفرع</th>
                            <th>حالة الإرسال لعام <?= date('Y') ?></th>
                            <th style="text-align: center;">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthCustomers as $c): 
                            $isToday = ((int)date('n') === $selectedMonth && (int)date('j') === (int)$c['birth_day']);
                        ?>
                            <tr style="<?= $isToday ? 'background: rgba(99, 102, 241, 0.08); font-weight: 600;' : '' ?>">
                                <td>
                                    <span class="badge" style="<?= $isToday ? 'background: #6366f1; color: #fff;' : 'background: rgba(0,0,0,0.05);' ?>">
                                        <?= sprintf('%02d', (int)$c['birth_day']) ?> <?= $isToday ? '🎉 اليوم!' : '' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="index.php?r=customer_view&id=<?= e($c['id']) ?>"><?= e($c['name']) ?></a>
                                </td>
                                <td dir="ltr" style="font-family: monospace;"><?= e($c['phone']) ?></td>
                                <td><?= date('d/m/Y', strtotime($c['birthdate'])) ?></td>
                                <td><?= calculate_age($c['birthdate']) ?></td>
                                <td><?= e($c['location_name'] ?? 'غير محدد') ?></td>
                                <td>
                                    <?php if (!empty($c['already_sent'])): ?>
                                        <span class="badge" style="background: rgba(22, 163, 74, 0.15); color: #16a34a;">✅ تم الإرسال</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(0,0,0,0.05); color: var(--muted);">لم يُرسل بعد</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn small" onclick="openSendModal(<?= (int)$c['id'] ?>, '<?= e(addslashes($c['name'])) ?>', '<?= e(addslashes($c['phone'])) ?>')">
                                        💌 تهنئة
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($tab === 'logs'): ?>
    <!-- TAB 4: SENT LOGS -->
    <section class="panel">
        <div class="toolbar" style="justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div>
                <h3 style="margin: 0;">📜 سجل رسائل أعياد الميلاد المرسلة</h3>
                <p class="muted" style="margin: 4px 0 0;">آخر رسائل تهنئة تم إرسالها للعملاء عبر خدمة الواتساب وتاريخ إرسالها بدقة.</p>
            </div>
            <span class="badge">المعروض: <?= count($birthdayLogs) ?> رسالة</span>
        </div>

        <?php if (empty($birthdayLogs)): ?>
            <div style="text-align: center; padding: 40px 20px; background: rgba(0,0,0,0.02); border-radius: 12px;">
                <p class="muted" style="margin: 0;">لا يوجد سجل رسائل مرسلة حتى الآن.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>العميل</th>
                            <th>رقم الهاتف</th>
                            <th>سنة الإرسال</th>
                            <th>تاريخ ووقت الإرسال</th>
                            <th>الفرع</th>
                            <th>نص الرسالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($birthdayLogs as $log): ?>
                            <tr>
                                <td>
                                    <strong><a href="index.php?r=customer_view&id=<?= e($log['customer_id']) ?>"><?= e($log['customer_name'] ?? 'عميل #' . $log['customer_id']) ?></a></strong>
                                </td>
                                <td dir="ltr" style="font-family: monospace;"><?= e($log['phone']) ?></td>
                                <td><span class="badge"><?= e($log['sent_year']) ?></span></td>
                                <td><?= date('d/m/Y h:i A', strtotime($log['sent_at'])) ?></td>
                                <td><?= e($log['location_name'] ?? '-') ?></td>
                                <td>
                                    <div style="max-width: 380px; font-size: 13px; color: var(--muted); white-space: pre-wrap; line-height: 1.4; background: rgba(0,0,0,0.02); padding: 6px 10px; border-radius: 6px;">
                                        <?= e($log['message'] ?? '-') ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<!-- Send Single Modal -->
<div id="sendSingleModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 15px;">
    <div style="background: var(--surface, #ffffff); border-radius: 16px; max-width: 520px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.3); overflow: hidden; border: 1px solid var(--line);">
        <div style="padding: 18px 20px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 17px; display: flex; align-items: center; gap: 8px;">
                <span>💌 إرسال تهنئة عيد ميلاد عبر الواتساب</span>
            </h3>
            <button type="button" onclick="closeSendModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--muted);">×</button>
        </div>

        <form method="post" style="padding: 20px;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="send_single">
            <input type="hidden" name="customer_id" id="modalCustomerId" value="">

            <div style="margin-bottom: 14px; background: rgba(99, 102, 241, 0.06); padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(99, 102, 241, 0.15);">
                <div style="font-size: 13px;">العميل: <strong id="modalCustomerName" style="color: var(--primary);"></strong></div>
                <div style="font-size: 13px; margin-top: 3px;">رقم الواتساب: <strong id="modalCustomerPhone" dir="ltr" style="font-family: monospace;"></strong></div>
            </div>

            <label style="display: block; margin-bottom: 16px;">
                <span style="font-weight: 600; display: block; margin-bottom: 6px;">نص الرسالة التي ستصل للعميل:</span>
                <textarea name="custom_message" id="modalMessageTextarea" rows="6" style="width: 100%; font-size: 14px; line-height: 1.5; padding: 10px; border-radius: 8px; border: 1px solid var(--line);"></textarea>
                <small class="muted" style="display: block; margin-top: 4px;">يمكنك تعديل الرسالة كما تحب قبل الضغط على إرسال.</small>
            </label>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn" onclick="closeSendModal()">إلغاء</button>
                <button type="submit" class="btn primary" style="background: #16a34a; border-color: #16a34a;">🚀 إرسال الرسالة الآن</button>
            </div>
        </form>
    </div>
</div>

<script>
const defaultMsgTemplate = <?= json_encode($defaultTemplate, JSON_UNESCAPED_UNICODE) ?>;
const shopBrandName = <?= json_encode($shopName, JSON_UNESCAPED_UNICODE) ?>;

function updateLivePreview() {
    const textarea = document.getElementById('birthdayMessageTextarea');
    const preview = document.getElementById('livePreviewBox');
    if (!textarea || !preview) return;

    let text = textarea.value || '';
    text = text.replace(/{name}/g, 'أحمد محمد');
    text = text.replace(/{shop_name}/g, shopBrandName);
    text = text.replace(/{phone}/g, '01234567890');
    preview.textContent = text;
}

function insertVariable(variable) {
    const textarea = document.getElementById('birthdayMessageTextarea');
    if (!textarea) return;

    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const current = textarea.value;

    textarea.value = current.substring(0, start) + variable + current.substring(end);
    textarea.focus();
    textarea.selectionStart = textarea.selectionEnd = start + variable.length;
    updateLivePreview();
}

function resetToDefaultMessage() {
    if (confirm('هل أنت متأكد من استعادة نص الرسالة الافتراضي؟')) {
        const textarea = document.getElementById('birthdayMessageTextarea');
        if (textarea) {
            textarea.value = defaultMsgTemplate;
            updateLivePreview();
        }
    }
}

function formatTimeAr(timeStr) {
    if (!timeStr) return '';
    const parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    let hour = parseInt(parts[0], 10);
    let min = parts[1];
    if (isNaN(hour)) return timeStr;
    if (hour === 0) return '12:' + min + ' منتصف الليل';
    if (hour < 12) return (hour < 10 ? '0' : '') + hour + ':' + min + ' صباحاً';
    if (hour === 12) return '12:' + min + ' ظهراً';
    return (hour - 12 < 10 ? '0' : '') + (hour - 12) + ':' + min + ' مساءً';
}

function updateTimePreview(val) {
    const cronPreview = document.getElementById('cronHourPreview');
    if (cronPreview) cronPreview.textContent = formatTimeAr(val);
}

function setTimeVal(time) {
    const input = document.getElementById('sendTimeInput');
    if (input) {
        input.value = time;
        updateTimePreview(time);
    }
}

function setCurrentTime() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    setTimeVal(h + ':' + m);
}

// Modal handling
function openSendModal(id, name, phone) {
    document.getElementById('modalCustomerId').value = id;
    document.getElementById('modalCustomerName').textContent = name;
    document.getElementById('modalCustomerPhone').textContent = phone;

    // Build message template with this customer's name
    let tpl = document.getElementById('birthdayMessageTextarea') ? document.getElementById('birthdayMessageTextarea').value : defaultMsgTemplate;
    let msg = tpl.replace(/{name}/g, name)
                 .replace(/{shop_name}/g, shopBrandName)
                 .replace(/{phone}/g, phone);

    document.getElementById('modalMessageTextarea').value = msg;
    const modal = document.getElementById('sendSingleModal');
    modal.style.display = 'flex';
}

function closeSendModal() {
    const modal = document.getElementById('sendSingleModal');
    modal.style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    const modal = document.getElementById('sendSingleModal');
    if (e.target === modal) {
        closeSendModal();
    }
});

// Run live preview on page load if settings tab
document.addEventListener('DOMContentLoaded', function() {
    updateLivePreview();
});
</script>
