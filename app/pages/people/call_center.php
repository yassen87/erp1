<?php
$search = trim((string)($_GET['q'] ?? ''));
$customer = null;
$invoices = [];
$todayBirthdays = today_birthday_customers();
$openDebt = 0.0;

if ($search !== '') {
    $db = pdo();
    $stmt = $db->prepare('SELECT * FROM customers WHERE phone = ? OR name LIKE ? OR id = ? LIMIT 1');
    $stmt->execute([$search, "%$search%", (int)$search]);
    $customer = $stmt->fetch();

    if ($customer) {
        $stmt2 = $db->prepare('
            SELECT i.*, u.name as user_name, l.name as location_name 
            FROM invoices i 
            LEFT JOIN users u ON i.user_id = u.id 
            LEFT JOIN locations l ON i.location_id = l.id 
            WHERE i.customer_id = ? 
            ORDER BY i.id DESC LIMIT 10
        ');
        $stmt2->execute([$customer['id']]);
        $invoices = $stmt2->fetchAll();

        // Check debt
        $stmtDebt = $db->prepare('SELECT COALESCE(SUM(remaining_amount), 0) FROM customer_debts WHERE customer_id = ? AND status = "open"');
        $stmtDebt->execute([$customer['id']]);
        $openDebt = (float)$stmtDebt->fetchColumn();
    }
}
?>
<style>
/* Call Center Premium Theme */
.cc-top-hero {
    background: linear-gradient(135deg, rgba(201, 168, 76, 0.12) 0%, rgba(37, 99, 235, 0.08) 100%), var(--surface);
    border: 2px solid var(--gold-soft2, rgba(201, 168, 76, 0.3));
    border-radius: 18px;
    padding: 22px 26px;
    margin-bottom: 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
}
.cc-panel {
    background: var(--surface);
    border-radius: 16px;
    border: 2px solid var(--gold-soft2, rgba(201, 168, 76, 0.2));
    box-shadow: 0 4px 24px rgba(201, 168, 76, 0.06), 0 1px 4px rgba(0,0,0,0.05);
    padding: 24px;
    margin-bottom: 22px;
    position: relative;
}
.cc-panel::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold-dark, #9E7A2A), var(--gold, #C9A84C), var(--gold-light, #E8C96A), var(--gold, #C9A84C));
    border-radius: 16px 16px 0 0;
}
.cc-header {
    font-size: 16.5px;
    font-weight: 800;
    color: var(--gold-dark, #9E7A2A);
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1.5px solid var(--gold-soft2, rgba(201, 168, 76, 0.18));
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}
.cc-search-input {
    flex: 1; 
    padding: 12px 18px; 
    font-size: 15px; 
    border-radius: 10px; 
    border: 2px solid var(--gold-soft2, rgba(201, 168, 76, 0.4)); 
    background: var(--surface);
    color: var(--ink);
    outline: none;
    transition: all 0.2s ease;
}
.cc-search-input:focus {
    border-color: var(--gold, #C9A84C);
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.18);
}
.cc-btn {
    padding: 11px 20px;
    font-size: 14px;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
}
.cc-btn-primary {
    background: var(--gold, #C9A84C);
    color: #fff !important;
}
.cc-btn-primary:hover {
    background: var(--gold-dark, #9E7A2A);
}
.cc-btn-call-hero {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    color: #fff !important;
    width: 100%;
    padding: 14px 20px;
    font-size: 16px;
    font-weight: 800;
    border-radius: 12px;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
}
.cc-btn-call-hero:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.5);
}
.cc-btn-whatsapp {
    background: #25D366;
    color: #fff !important;
    width: 100%;
}
.cc-btn-whatsapp:hover {
    background: #128C7E;
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
}
.cc-template-btn {
    background: var(--gold-soft, rgba(201, 168, 76, 0.1));
    color: var(--gold-dark, #9E7A2A);
    border: 1px solid var(--gold, #C9A84C);
    border-radius: 6px;
    font-size: 12px;
    padding: 6px 12px;
    cursor: pointer;
    font-weight: bold;
}
.cc-template-btn:hover {
    background: var(--gold, #C9A84C);
    color: #fff;
}
.cc-layout {
    display: grid;
    grid-template-columns: 380px minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}
.cc-layout > div {
    min-width: 0;
}
@media (max-width: 992px) {
    .cc-layout {
        grid-template-columns: 1fr;
    }
}
.table-responsive {
    overflow-x: auto;
    width: 100%;
}

/* Virtual Softphone Dialpad Styling */
.dialpad-container {
    background: var(--surface);
    border: 1.5px solid var(--gold-soft, rgba(201,168,76,0.3));
    border-radius: 16px;
    padding: 20px;
    max-width: 320px;
    margin: 0 auto;
    box-shadow: 0 6px 24px rgba(0,0,0,0.06);
}
.dialpad-screen {
    background: var(--surface-soft, rgba(0,0,0,0.03));
    border: 2px solid var(--gold-soft2, rgba(201,168,76,0.4));
    border-radius: 12px;
    padding: 12px 14px;
    font-size: 22px;
    font-weight: 800;
    color: var(--ink);
    text-align: center;
    letter-spacing: 2px;
    margin-bottom: 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    direction: ltr;
}
.dialpad-screen input {
    background: transparent;
    border: none;
    outline: none;
    font-size: 20px;
    font-weight: 800;
    color: var(--ink);
    width: 100%;
    text-align: center;
    letter-spacing: 2px;
}
.dialpad-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 16px;
}
.dialpad-btn {
    background: var(--surface);
    border: 1.5px solid var(--line);
    border-radius: 12px;
    padding: 12px 0;
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    user-select: none;
}
.dialpad-btn:hover {
    background: var(--gold-soft, rgba(201, 168, 76, 0.15));
    border-color: var(--gold, #C9A84C);
    transform: scale(1.04);
}
.dialpad-btn:active {
    transform: scale(0.96);
}
.dialpad-btn .num {
    font-size: 19px;
    font-weight: 800;
    color: var(--ink);
    line-height: 1;
}
.dialpad-btn .sub {
    font-size: 9px;
    font-weight: 700;
    color: var(--muted);
    letter-spacing: 1px;
    margin-top: 3px;
}

/* Active Call Bar Animations */
@keyframes callLivePulse {
    0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7); }
    70% { transform: scale(1.1); box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); }
    100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
}
.call-pulse-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #2563eb;
    display: inline-block;
    animation: callLivePulse 1.4s infinite;
}
</style>

<!-- Top Hero & Navigation -->
<div class="cc-top-hero">
    <div>
        <h2 style="margin: 0; color: var(--gold-dark); font-size: 22px; display: flex; align-items: center; gap: 10px;">
            <span>🎧</span> مركز اتصالات الكول سنتر (Call Center Workstation)
        </h2>
        <p style="margin: 6px 0 0; color: var(--muted); font-size: 13.5px;">
            اتصال صوتي مباشر من الكمبيوتر عبر <strong>Windows Phone Link</strong> • شات واتساب • متابعة العملاء وأعياد الميلاد
        </p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button type="button" class="cc-btn" onclick="openPhoneLinkGuide()" style="background: rgba(37,99,235,0.12); color: #2563eb; border: 1.5px solid rgba(37,99,235,0.35);">
            ℹ️ كيف تتصل من الكمبيوتر؟
        </button>
        <button type="button" class="cc-btn" onclick="toggleDialpad()" style="background: rgba(201,168,76,0.15); color: var(--gold-dark); border: 1.5px solid var(--gold);">
            📱 لوحة الاتصال الرقمية
        </button>
        <a href="index.php?r=customer_todo" class="cc-btn cc-btn-primary">
            📋 قائمة المهام (To-Do List)
        </a>
    </div>
</div>

<!-- Virtual Softphone Dialpad Drawer / Panel -->
<div id="dialpadCard" class="cc-panel" style="display: none; border-color: #2563eb; background: linear-gradient(180deg, rgba(37,99,235,0.04) 0%, var(--surface) 100%);">
    <div class="cc-header" style="color: #1d4ed8;">
        <span>📱 لوحة الاتصال السريع المباشر (Virtual Softphone)</span>
        <button type="button" onclick="toggleDialpad()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--muted);">&times;</button>
    </div>

    <div style="display: flex; gap: 24px; flex-wrap: wrap; align-items: center; justify-content: center;">
        <div class="dialpad-container">
            <div class="dialpad-screen">
                <input type="text" id="dialpadInput" placeholder="اكتب أو الصق الرقم..." autofocus>
                <button type="button" onclick="dialpadBackspace()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: var(--muted);" title="مسح رقم">⌫</button>
            </div>

            <div class="dialpad-grid">
                <button type="button" class="dialpad-btn" onclick="dialpadPress('1')"><span class="num">1</span><span class="sub">&nbsp;</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('2')"><span class="num">2</span><span class="sub">ABC</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('3')"><span class="num">3</span><span class="sub">DEF</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('4')"><span class="num">4</span><span class="sub">GHI</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('5')"><span class="num">5</span><span class="sub">JKL</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('6')"><span class="num">6</span><span class="sub">MNO</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('7')"><span class="num">7</span><span class="sub">PQRS</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('8')"><span class="num">8</span><span class="sub">TUV</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('9')"><span class="num">9</span><span class="sub">WXYZ</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('*')"><span class="num">*</span><span class="sub">&nbsp;</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('0')"><span class="num">0</span><span class="sub">+</span></button>
                <button type="button" class="dialpad-btn" onclick="dialpadPress('#')"><span class="num">#</span><span class="sub">&nbsp;</span></button>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="button" class="cc-btn" onclick="dialpadCallNow()" style="flex: 1; background: #2563eb; color: #fff; font-size: 15px; font-weight: 800; border-radius: 10px; box-shadow: 0 4px 12px rgba(37,99,235,0.35);">
                    📞 اتصال
                </button>
                <button type="button" class="cc-btn" onclick="dialpadWhatsAppNow()" style="background: #25D366; color: #fff; font-size: 15px; border-radius: 10px;" title="شات واتساب">
                    💬
                </button>
                <button type="button" class="cc-btn" onclick="dialpadSearchNow()" style="background: var(--surface-soft); color: var(--ink); border: 1px solid var(--line); font-size: 15px; border-radius: 10px;" title="بحث بالسيستم">
                    🔍
                </button>
            </div>
        </div>

        <div style="max-width: 360px; font-size: 13px; line-height: 1.6; color: var(--ink);">
            <h4 style="margin: 0 0 8px; color: #1d4ed8; font-size: 15px;">💡 الاتصال الصوتي عبر Phone Link</h4>
            <p style="margin: 0 0 10px;">
                عند الضغط على <strong>📞 اتصال</strong>، يفتح تطبيق Phone Link بويندوز تلقائياً ويتصل برقم العميل، وتتحدث من سماعة ومايك الكمبيوتر عبر شريحة الموبايل مباشرة!
            </p>
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div>✔️ لا حاجة لمسك الهاتف أثناء المكالمة.</div>
                <div>✔️ تسجيل وقت ونتيجة المكالمة تلقائياً.</div>
                <div>✔️ متوافق مع كافة السماعات والمايكات السلكية واللاسلكية.</div>
            </div>
        </div>
    </div>
</div>

<!-- أعياد ميلاد اليوم -->
<?php if (!empty($todayBirthdays)): ?>
<div class="cc-panel" style="border: 2px solid rgba(234, 179, 8, 0.5); background: linear-gradient(180deg, rgba(234, 179, 8, 0.08) 0%, rgba(0,0,0,0) 100%);">
    <div class="cc-header" style="color: #eab308; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 24px;">🎂</span>
            <span style="font-size: 17px;">أعياد ميلاد العملاء اليوم (<?= count($todayBirthdays) ?>)</span>
        </div>
        <button type="button" class="cc-btn" onclick="sendAllBirthdaysNow(this)" style="background: #eab308; color: #000; font-size: 13px; padding: 7px 16px; font-weight: bold; cursor: pointer; border-radius: 8px;">
            🚀 إرسال التهنئة للجميع الآن
        </button>
    </div>
    
    <div class="table-responsive" style="margin-top: 10px;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--line); text-align: right;">
                    <th style="padding: 10px;">العميل</th>
                    <th style="padding: 10px;">رقم الهاتف</th>
                    <th style="padding: 10px;">حالة التهنئة</th>
                    <th style="padding: 10px; text-align: center;">إجراءات الاتصال والتهنئة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todayBirthdays as $bCust): ?>
                <?php $bPhoneClean = clean_dial_phone($bCust['phone']); ?>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <td style="padding: 10px;"><strong><?= e($bCust['name']) ?></strong></td>
                    <td style="padding: 10px;" dir="ltr"><?= e($bCust['phone']) ?></td>
                    <td style="padding: 10px;">
                        <?php if (!empty($bCust['already_sent'])): ?>
                            <span style="color: #25D366; font-weight: bold;">✅ تم الإرسال</span>
                        <?php else: ?>
                            <span style="color: #f59e0b; font-weight: bold;">⏳ بانتظار الإرسال</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 10px; text-align: center; display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                        <?php if ($bPhoneClean): ?>
                            <button type="button" class="cc-btn" onclick="startCallCenterCall('<?= e(addslashes($bPhoneClean)) ?>', '<?= e(addslashes($bCust['name'])) ?>', <?= (int)$bCust['id'] ?>)" style="padding: 5px 12px; font-size: 12px; background: #2563eb; color: #fff; border-radius: 6px;" title="اتصال صوتي فوري من الكمبيوتر">
                                📞 اتصال
                            </button>
                        <?php endif; ?>
                        <button type="button" class="cc-btn" onclick="sendIndividualBirthday(this, <?= (int)$bCust['id'] ?>, '<?= e(addslashes($bCust['name'])) ?>', '<?= e($bCust['phone']) ?>')" style="padding: 5px 12px; font-size: 12px; background: #25D366; color: #fff; cursor: pointer; border-radius: 6px;">
                            تهنئة 💬
                        </button>
                        <a href="index.php?r=call_center&q=<?= urlencode($bCust['phone']) ?>" class="cc-btn cc-btn-primary" style="padding: 5px 12px; font-size: 12px; text-decoration: none; border-radius: 6px;">
                            عرض 🎧
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- صندوق البحث -->
<div class="cc-panel">
    <div class="cc-header">
        <span>البحث عن عميل للاتصال به أو مراسلته 🎧</span>
        <?php if ($customer): ?>
            <span class="badge" style="background: rgba(16,185,129,0.15); color: #059669; font-size: 12px;">عميل محدد: <?= e($customer['name']) ?></span>
        <?php endif; ?>
    </div>
    
    <form method="get" action="index.php" style="display: flex; gap: 8px; max-width: 650px;">
        <input type="hidden" name="r" value="call_center">
        <input type="text" name="q" value="<?= e($search) ?>" class="cc-search-input" placeholder="ابحث برقم الهاتف أو اسم العميل..." autofocus>
        <button type="submit" class="cc-btn cc-btn-primary">بحث 🔍</button>
        <?php if ($search !== ''): ?>
            <a href="index.php?r=call_center" class="cc-btn" style="background: var(--surface-soft); color: var(--muted); border: 1px solid var(--line);">مسح</a>
        <?php endif; ?>
    </form>

    <?php if ($search && !$customer): ?>
        <div class="alert danger" style="margin-top: 20px;">لم يتم العثور على أي عميل بهذا البحث. جرب رقم هاتف آخر أو اضغط على (لوحة الاتصال الرقمية) بالأعلى للاتصال المباشر.</div>
    <?php endif; ?>
</div>

<?php if ($customer): ?>
<?php 
    $cleanPhone = clean_dial_phone($customer['phone']); 
    $waPhone = clean_whatsapp_phone($customer['phone']);
?>
<div class="cc-layout">
    
    <!-- بيانات العميل، الاتصال الصوتي، والرسائل -->
    <div class="cc-panel" style="align-self: start;">
        <div class="cc-header">
            <span>ملف العميل والاتصال الصوتي</span>
            <span style="font-size: 12px; color: var(--muted);">ID: #<?= (int)$customer['id'] ?></span>
        </div>

        <table class="details-table" style="width: 100%; margin-bottom: 18px;">
            <tr><th style="text-align: right; padding: 7px; width: 35%;">الاسم:</th><td style="padding: 7px;"><strong><?= e($customer['name']) ?></strong></td></tr>
            <tr>
                <th style="text-align: right; padding: 7px;">رقم الهاتف:</th>
                <td style="padding: 7px;">
                    <strong id="cc-phone" dir="ltr" style="color: #2563eb; font-size: 15px; font-weight: 800;"><?= e($customer['phone']) ?></strong>
                </td>
            </tr>
            <tr><th style="text-align: right; padding: 7px;">تاريخ الميلاد:</th><td style="padding: 7px;"><?= e($customer['birthdate'] ?: 'غير مسجل') ?></td></tr>
            <tr>
                <th style="text-align: right; padding: 7px;">الديون الحالية:</th>
                <td style="padding: 7px;">
                    <?php if ($openDebt > 0): ?>
                        <span style="color: #dc2626; font-weight: 800;"><?= money($openDebt) ?> (مديونية مفتوحة)</span>
                    <?php else: ?>
                        <span style="color: #059669; font-weight: 700;">لا توجد ديون مسجلة</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr><th style="text-align: right; padding: 7px;">ملاحظات:</th><td style="padding: 7px; font-size: 12.5px;"><?= e($customer['notes'] ?: '-') ?></td></tr>
        </table>

        <!-- Hero Voice Call Action Section -->
        <div style="background: linear-gradient(135deg, rgba(37,99,235,0.08), rgba(29,78,216,0.03)); border: 1.5px solid rgba(37,99,235,0.3); border-radius: 14px; padding: 16px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <span style="font-weight: 800; font-size: 13.5px; color: #1e3a8a;">📞 الاتصال الصوتي عبر الكمبيوتر:</span>
                <span style="font-size: 11px; background: rgba(37,99,235,0.15); color: #1d4ed8; padding: 2px 8px; border-radius: 10px; font-weight: 700;">Phone Link</span>
            </div>

            <?php if ($cleanPhone): ?>
                <button type="button" class="cc-btn-call-hero" onclick="startCallCenterCall('<?= e(addslashes($cleanPhone)) ?>', '<?= e(addslashes($customer['name'])) ?>', <?= (int)$customer['id'] ?>)">
                    <span style="font-size: 20px;">📞</span>
                    <span>اتصال صوتي فوري من الكمبيوتر</span>
                </button>
            <?php else: ?>
                <div class="alert danger" style="padding: 8px; font-size: 12px;">لا يوجد رقم هاتف صالح للاتصال.</div>
            <?php endif; ?>

            <!-- Action shortcuts -->
            <div style="display: flex; gap: 8px; margin-top: 12px;">
                <?php if ($waPhone): ?>
                    <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" class="cc-btn" style="flex: 1; background: #25D366; color: #fff; font-size: 12.5px; padding: 8px 12px; border-radius: 8px;">
                        💬 شات واتساب
                    </a>
                <?php endif; ?>
                <a href="index.php?r=customer_todo&q=<?= urlencode($customer['phone'] ?: $customer['name']) ?>" class="cc-btn" style="flex: 1; background: rgba(16,185,129,0.12); color: #047857; border: 1px solid rgba(16,185,129,0.3); font-size: 12.5px; padding: 8px 12px; border-radius: 8px;">
                    📋 إضافة للـ To-Do
                </a>
            </div>
        </div>

        <!-- Active Call Control Widget (In-Page) -->
        <div id="inPageCallWidget" style="display: none; background: #fff; border: 2px solid #2563eb; border-radius: 14px; padding: 16px; margin-bottom: 20px; box-shadow: 0 8px 24px rgba(37,99,235,0.18);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; border-bottom: 1px solid var(--line); padding-bottom: 8px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span class="call-pulse-dot"></span>
                    <strong style="color: #1e3a8a; font-size: 13.5px;">مكالمة جارية الآن عبر الكمبيوتر</strong>
                </div>
                <div style="font-family: monospace; font-size: 17px; font-weight: 800; color: #2563eb;" id="inPageCallTimer">00:00</div>
            </div>

            <div style="margin-bottom: 12px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: var(--ink); margin-bottom: 6px;">تسجيل نتيجة المكالمة بنقرة واحدة:</label>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px;">
                    <button type="button" class="cc-btn" onclick="saveInPageCallOutcome('answered_agreed')" style="background: #059669; color: #fff; font-size: 12px; padding: 7px 4px; border-radius: 6px;">
                        ✅ تم الرد والاتفاق
                    </button>
                    <button type="button" class="cc-btn" onclick="saveInPageCallOutcome('no_answer')" style="background: #d97706; color: #fff; font-size: 12px; padding: 7px 4px; border-radius: 6px;">
                        ⏳ لم يرد / رن فقط
                    </button>
                    <button type="button" class="cc-btn" onclick="saveInPageCallOutcome('busy')" style="background: #64748b; color: #fff; font-size: 12px; padding: 7px 4px; border-radius: 6px;">
                        📴 مشغول أو مغلق
                    </button>
                    <button type="button" class="cc-btn" onclick="saveInPageCallOutcome('wrong_number')" style="background: #dc2626; color: #fff; font-size: 12px; padding: 7px 4px; border-radius: 6px;">
                        ❌ رقم غير متاح
                    </button>
                </div>
            </div>

            <input type="text" id="inPageCallNotes" placeholder="ملاحظة سريعة إضافية عن المكالمة..." style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--line); font-size: 12px; margin-bottom: 10px;">
            
            <div style="display: flex; justify-content: flex-end; gap: 6px;">
                <button type="button" class="cc-btn" onclick="closeInPageCallWidget()" style="background: var(--surface-soft); color: var(--muted); font-size: 11.5px; padding: 6px 12px; border-radius: 6px;">
                    إغلاق العداد
                </button>
                <button type="button" class="cc-btn cc-btn-primary" onclick="saveInPageCallOutcome('general')" style="font-size: 11.5px; padding: 6px 14px; border-radius: 6px;">
                    💾 حفظ الملاحظة
                </button>
            </div>
        </div>
        
        <hr style="border: 0; border-top: 1px solid var(--line); margin: 20px 0;">
        
        <!-- WhatsApp Messaging Box -->
        <h4 style="margin-bottom: 10px; color: #16a34a; font-size: 14.5px;">إرسال رسالة ترويجية (WhatsApp) 💬</h4>
        <textarea id="cc-message" rows="4" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line); margin-bottom: 10px; font-size: 13px;" placeholder="اكتب رسالة العروض أو الترحيب هنا..."></textarea>
        
        <!-- أزرار القوالب الجاهزة -->
        <div style="display: flex; gap: 6px; margin-bottom: 15px; flex-wrap: wrap;">
            <button class="cc-template-btn" onclick="setTemplate('ترحيب')">قالب ترحيب</button>
            <button class="cc-template-btn" onclick="setTemplate('عروض')">قالب عروض</button>
            <button class="cc-template-btn" onclick="setTemplate('كل عام')">تهنئة</button>
        </div>
        
        <button class="cc-btn cc-btn-whatsapp" onclick="sendPromoMessage()">إرسال الرسالة عبر الواتساب الآن 🚀</button>
    </div>

    <!-- سجل فواتير العميل -->
    <div class="cc-panel" style="align-self: start;">
        <div class="cc-header">
            <span>سجل مشتريات وفواتير العميل (آخر 10 فواتير)</span>
            <a href="index.php?r=customer_view&id=<?= e($customer['id']) ?>" class="cc-btn cc-btn-primary" style="font-size: 12px; padding: 6px 12px; text-decoration: none;">
                ملف العميل الكامل 👤
            </a>
        </div>
        
        <?php if ($invoices): ?>
            <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>التاريخ</th>
                        <th>الفرع</th>
                        <th>الكاشير</th>
                        <th>الإجمالي</th>
                        <th>المدفوع</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><a href="index.php?r=invoice_view&id=<?= e($inv['id']) ?>"><strong><?= e($inv['invoice_number']) ?></strong></a></td>
                        <td><?= e(format_datetime($inv['created_at'])) ?></td>
                        <td><?= e($inv['location_name']) ?></td>
                        <td><?= e($inv['user_name']) ?></td>
                        <td><strong><?= e(money($inv['total'])) ?></strong></td>
                        <td><span style="color: <?= (float)$inv['due_total'] > 0 ? 'red' : 'green' ?>;"><?= e(money($inv['paid_total'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <div class="alert info">هذا العميل ليس لديه أي فواتير سابقة في النظام.</div>
        <?php endif; ?>
    </div>
</div>

<script>
const customerName = "<?= addslashes($customer['name']) ?>";
const customerIdGlobal = <?= (int)$customer['id'] ?>;

function setTemplate(type) {
    const msgBox = document.getElementById('cc-message');
    if (type === 'ترحيب') {
        msgBox.value = `مرحباً بك أستاذ/ة ${customerName} 🌹\nشكراً لزيارتك لـ *حمزة للعطور*، نتشرف بخدمتك دائماً ونتمنى لك يوماً سعيداً! ✨`;
    } else if (type === 'عروض') {
        msgBox.value = `أهلاً بك ${customerName} 🌹\nيسعدنا في *حمزة للعطور* أن نخبرك بأحدث عروضنا لهذا الأسبوع:\n\n- باكدج العطور الملكية 🎁\n- خصم خاص 20% على التركيبات الخاصة ✨\n\nننتظر زيارتك للاستفادة من العرض!`;
    } else if (type === 'كل عام') {
        msgBox.value = `كل عام وأنت بخير أستاذ/ة ${customerName} 🌹\nأسرة *حمزة للعطور* تتمنى لك أوقاتاً سعيدة ومباركة! ✨`;
    }
}

function sendPromoMessage() {
    const phone = document.getElementById('cc-phone').innerText.trim();
    const message = document.getElementById('cc-message').value.trim();
    
    if (!phone) {
        alert("هذا العميل ليس لديه رقم هاتف مسجل!");
        return;
    }
    
    if (!message) {
        alert("يرجى كتابة الرسالة أولاً!");
        document.getElementById('cc-message').focus();
        return;
    }

    const btn = event.target;
    const oldText = btn.innerHTML;
    btn.innerHTML = "جاري الإرسال ⏳...";
    btn.disabled = true;

    fetch('index.php?r=api_whatsapp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone: phone, message: message })
    })
    .then(res => res.json())
    .then(data => {
        btn.innerHTML = oldText;
        btn.disabled = false;
        
        if(data.success) {
            alert("✅ تم إرسال الرسالة بنجاح للعميل!");
            document.getElementById('cc-message').value = '';
        } else {
            alert("❌ فشل الإرسال: " + data.error);
        }
    })
    .catch(err => {
        btn.innerHTML = oldText;
        btn.disabled = false;
        console.error("Connection Error:", err);
        alert("❌ لا يمكن الوصول لبرنامج الواتساب! تأكد من أن (شغل_البرنامج) يعمل الآن على هذا الجهاز.");
    });
}
</script>
<?php endif; ?>

<!-- Phone Link Setup Guide Modal -->
<div id="phoneLinkGuideModal" style="display: none; position: fixed; inset: 0; z-index: 99999; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 16px;">
    <div style="background: var(--surface); border: 2px solid var(--gold); border-radius: 20px; max-width: 560px; width: 100%; padding: 26px; box-shadow: 0 20px 45px rgba(0,0,0,0.3); position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1.5px solid var(--line); padding-bottom: 12px; margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 26px;">💻📱</span>
                <h3 style="margin: 0; font-size: 18px; color: var(--gold-dark);">كيف تتصل من الكمبيوتر عبر Phone Link؟</h3>
            </div>
            <button type="button" onclick="closePhoneLinkGuide()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>

        <p style="font-size: 13.5px; line-height: 1.6; color: var(--ink); margin-bottom: 16px;">
            تطبيق <strong>Phone Link (ربط الهاتف)</strong> هو برنامج مجاني ورسمي مدمج في ويندوز (10 و 11)، يتيح لك ربط موبايل الشغل بالكمبيوتر والتحدث من سماعة ومايك الكمبيوتر مباشرة:
        </p>

        <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
            <div style="display: flex; gap: 12px; background: rgba(37,99,235,0.06); border: 1px solid rgba(37,99,235,0.2); border-radius: 12px; padding: 12px;">
                <span style="font-size: 22px; font-weight: 800; color: #2563eb;">1</span>
                <div>
                    <strong style="color: #1e3a8a; font-size: 14px;">افتح تطبيق Phone Link على جهاز الكمبيوتر:</strong>
                    <div style="font-size: 12.5px; color: var(--muted); margin-top: 2px;">ابحث في قائمة ابدأ (Start) عن <strong>Phone Link</strong> أو <strong>ربط الهاتف</strong>.</div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: rgba(16,185,129,0.06); border: 1px solid rgba(16,185,129,0.2); border-radius: 12px; padding: 12px;">
                <span style="font-size: 22px; font-weight: 800; color: #059669;">2</span>
                <div>
                    <strong style="color: #065f46; font-size: 14px;">اربط موبايلك بالكمبيوتر:</strong>
                    <div style="font-size: 12.5px; color: var(--muted); margin-top: 2px;">افتح تطبيق Link to Windows على الموبايل وامسح كود الـ QR، وتأكد من تفعيل البلوتوث لإجراء المكالمات.</div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; background: rgba(201,168,76,0.08); border: 1px solid var(--gold-soft); border-radius: 12px; padding: 12px;">
                <span style="font-size: 22px; font-weight: 800; color: var(--gold-dark);">3</span>
                <div>
                    <strong style="color: var(--gold-dark); font-size: 14px;">اضغط على (📞 اتصال) في السيستم:</strong>
                    <div style="font-size: 12.5px; color: var(--muted); margin-top: 2px;">بمجرد الضغط على أي زر اتصال بالسيستم، سيرن الموبايل تلقائياً وتتحدث من سماعة ومايك الكمبيوتر بدون لمس الموبايل!</div>
                </div>
            </div>
        </div>

        <div style="text-align: center;">
            <button type="button" class="cc-btn cc-btn-primary" onclick="closePhoneLinkGuide()" style="padding: 9px 26px;">
                فهمت، شكراً لك! 👍
            </button>
        </div>
    </div>
</div>

<script>
// Call Center Direct Computer Calling Logic
let inPageCallTimerInterval = null;
let inPageCallDuration = 0;
let inPageCallCustomerId = null;

function startCallCenterCall(phone, name, customerId) {
    if (!phone) {
        alert('لا يوجد رقم هاتف متاح للاتصال!');
        return;
    }

    inPageCallCustomerId = customerId || (typeof customerIdGlobal !== 'undefined' ? customerIdGlobal : null);
    inPageCallDuration = 0;

    // Trigger Phone Link / Windows dialer
    window.location.href = 'tel:' + phone;

    // Show in-page live call widget
    const widget = document.getElementById('inPageCallWidget');
    if (widget) {
        widget.style.display = 'block';
        document.getElementById('inPageCallTimer').textContent = '00:00';
        document.getElementById('inPageCallNotes').value = '';

        if (inPageCallTimerInterval) clearInterval(inPageCallTimerInterval);
        inPageCallTimerInterval = setInterval(() => {
            inPageCallDuration++;
            const mins = String(Math.floor(inPageCallDuration / 60)).padStart(2, '0');
            const secs = String(inPageCallDuration % 60).padStart(2, '0');
            document.getElementById('inPageCallTimer').textContent = `${mins}:${secs}`;
        }, 1000);
    }
}

function closeInPageCallWidget() {
    if (inPageCallTimerInterval) {
        clearInterval(inPageCallTimerInterval);
        inPageCallTimerInterval = null;
    }
    const widget = document.getElementById('inPageCallWidget');
    if (widget) widget.style.display = 'none';
}

async function saveInPageCallOutcome(outcome) {
    if (!inPageCallCustomerId) {
        alert('لم يتم تحديد العميل.');
        return;
    }

    const notes = document.getElementById('inPageCallNotes').value.trim();
    const duration = inPageCallDuration;

    try {
        const formData = new FormData();
        formData.append('customer_id', inPageCallCustomerId);
        formData.append('outcome', outcome);
        formData.append('notes', notes);
        formData.append('duration', duration);

        const res = await fetch('index.php?r=api_log_customer_call', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            alert('✅ تم حفظ نتيجة المكالمة وتحديث سجل العميل بنجاح!');
            closeInPageCallWidget();
        } else {
            alert('❌ فشل الحفظ: ' + (data.message || 'خطأ غير معروف'));
        }
    } catch (e) {
        alert('❌ خطأ في الاتصال بالسيرفر.');
    }
}

// Dialpad Operations
function toggleDialpad() {
    const card = document.getElementById('dialpadCard');
    if (card) {
        const isHidden = (card.style.display === 'none' || card.style.display === '');
        card.style.display = isHidden ? 'block' : 'none';
        if (isHidden) {
            document.getElementById('dialpadInput')?.focus();
        }
    }
}

function dialpadPress(char) {
    const input = document.getElementById('dialpadInput');
    if (input) {
        input.value += char;
    }
}

function dialpadBackspace() {
    const input = document.getElementById('dialpadInput');
    if (input && input.value.length > 0) {
        input.value = input.value.slice(0, -1);
    }
}

function dialpadCallNow() {
    const input = document.getElementById('dialpadInput');
    const phone = input ? input.value.trim().replace(/[^\d+]/g, '') : '';
    if (!phone) {
        alert('يرجى كتابة رقم الهاتف أولاً!');
        return;
    }
    startCallCenterCall(phone, 'مكالمة رقم مباشر', null);
}

function dialpadWhatsAppNow() {
    const input = document.getElementById('dialpadInput');
    let phone = input ? input.value.trim().replace(/\D/g, '') : '';
    if (!phone) {
        alert('يرجى كتابة رقم الهاتف أولاً!');
        return;
    }
    if (phone.startsWith('0')) {
        phone = '2' + phone;
    }
    window.open(`https://wa.me/${phone}`, '_blank');
}

function dialpadSearchNow() {
    const input = document.getElementById('dialpadInput');
    const phone = input ? input.value.trim() : '';
    if (!phone) {
        alert('يرجى كتابة رقم الهاتف للبحث!');
        return;
    }
    window.location.href = `index.php?r=call_center&q=${encodeURIComponent(phone)}`;
}

// Phone Link Guide Modal
function openPhoneLinkGuide() {
    document.getElementById('phoneLinkGuideModal').style.display = 'flex';
}
function closePhoneLinkGuide() {
    document.getElementById('phoneLinkGuideModal').style.display = 'none';
}

// Birthday Actions
function sendIndividualBirthday(btn, id, name, phone) {
    if (!confirm('هل تريد إرسال رسالة تهنئة عيد الميلاد إلى ' + name + '؟')) return;
    const oldText = btn.innerHTML;
    btn.innerHTML = 'جاري الإرسال... ⏳';
    btn.disabled = true;

    fetch('index.php?r=api_whatsapp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            phone: phone, 
            message: `كل عام وأنت بخير وبصحة وسعادة أستاذ/ة ${name} 🎂🌹\nأسرة *حمزة للعطور* تتمنى لك عاماً سعيداً مليئاً بالبهجة والنجاح والبركة! ✨\n🎁 بهذه المناسبة السعيدة، يسعدنا أن نهديك خصم خاص 20% على أي زجاجة عطر من اختيارك عند زيارتك القادمة لنا! 🎉`
        })
    })
    .then(r => r.json())
    .then(res => {
        btn.innerHTML = oldText;
        btn.disabled = false;
        if (res.success) {
            alert('✅ تم إرسال رسالة التهنئة بنجاح إلى ' + name);
            location.reload();
        } else {
            alert('❌ فشل الإرسال: ' + (res.error || 'خطأ غير معروف'));
        }
    })
    .catch(e => {
        btn.innerHTML = oldText;
        btn.disabled = false;
        alert('❌ خطأ في الاتصال: ' + e.message);
    });
}

function sendAllBirthdaysNow(btn) {
    if (!confirm('هل تريد إرسال التهنئة لجميع العملاء الذين يصادف عيد ميلادهم اليوم؟')) return;
    const oldText = btn.innerHTML;
    btn.innerHTML = 'جاري إرسال التهاني... ⏳';
    btn.disabled = true;

    fetch('index.php?r=send_birthday_whatsapp')
        .then(r => r.json())
        .then(res => {
            btn.innerHTML = oldText;
            btn.disabled = false;
            if (res.success) {
                alert(`✅ تم فحص وإرسال أعياد الميلاد بنجاح!\n\n• إجمالي عملاء اليوم: ${res.total_today}\n• تم الإرسال: ${res.sent_count}\n• تم التخطي (أُرسل مسبقاً): ${res.skipped_count}\n• فشل: ${res.failed_count}`);
                location.reload();
            } else {
                alert('❌ فشل الإرسال: ' + (res.error || 'خطأ غير معروف'));
            }
        })
        .catch(e => {
            btn.innerHTML = oldText;
            btn.disabled = false;
            alert('❌ خطأ في الاتصال: ' + e.message);
        });
}
</script>
