<?php
$search = trim((string)($_GET['q'] ?? ''));
$customer = null;
$invoices = [];
$todayBirthdays = today_birthday_customers();

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
    }
}
?>
<style>
/* Call Center Premium Styling */
.cc-panel {
    background: var(--surface);
    border-radius: 16px;
    border: 2px solid var(--gold-soft2, rgba(201, 168, 76, 0.18));
    box-shadow: 0 4px 24px rgba(201, 168, 76, 0.08), 0 1px 4px rgba(0,0,0,0.06);
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
    font-size: 16px;
    font-weight: 800;
    color: var(--gold-dark, #9E7A2A);
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1.5px solid var(--gold-soft2, rgba(201, 168, 76, 0.18));
    display: flex;
    justify-content: space-between;
    align-items: center;
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
    padding: 12px 24px;
    font-size: 15px;
    border-radius: 10px;
    font-weight: bold;
    cursor: pointer;
    border: none;
    transition: all 0.2s ease;
}
.cc-btn-primary {
    background: var(--gold, #C9A84C);
    color: #fff;
}
.cc-btn-primary:hover {
    background: var(--gold-dark, #9E7A2A);
}
.cc-btn-whatsapp {
    background: #25D366;
    color: #fff;
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
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 24px;
    align-items: start;
}
.cc-layout > div {
    min-width: 0;
}
@media (max-width: 900px) {
    .cc-layout {
        grid-template-columns: 1fr;
    }
}
.table-responsive {
    overflow-x: auto;
    width: 100%;
}
</style>

<!-- تنبيه وبانر أعياد ميلاد اليوم -->
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
                    <th style="padding: 10px; text-align: center;">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($todayBirthdays as $bCust): ?>
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
                    <td style="padding: 10px; text-align: center; display: flex; gap: 6px; justify-content: center;">
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

<div class="cc-panel">
    <div class="cc-header">
        البحث في الكول سنتر 🎧
    </div>
    
    <form method="get" action="index.php" style="display: flex; gap: 8px; max-width: 600px;">
        <input type="hidden" name="r" value="call_center">
        <input type="text" name="q" value="<?= e($search) ?>" class="cc-search-input" placeholder="ابحث برقم الهاتف أو اسم العميل..." autofocus>
        <button type="submit" class="cc-btn cc-btn-primary">بحث 🔍</button>
    </form>

    <?php if ($search && !$customer): ?>
        <div class="alert danger" style="margin-top: 20px;">لم يتم العثور على أي عميل بهذا البحث. جرب رقم هاتف آخر.</div>
    <?php endif; ?>
</div>

<?php if ($customer): ?>
<div class="cc-layout">
    
    <!-- بيانات العميل وإرسال الرسائل -->
    <div class="cc-panel" style="align-self: start;">
        <div class="cc-header">معلومات العميل</div>
        <table class="details-table" style="width: 100%; margin-bottom: 20px;">
            <tr><th style="text-align: right; padding: 8px; width: 40%;">الاسم:</th><td style="padding: 8px;"><strong><?= e($customer['name']) ?></strong></td></tr>
            <tr><th style="text-align: right; padding: 8px;">رقم الهاتف:</th><td style="padding: 8px;"><strong id="cc-phone" style="color: var(--primary);"><?= e($customer['phone']) ?></strong></td></tr>
            <tr><th style="text-align: right; padding: 8px;">تاريخ الميلاد:</th><td style="padding: 8px;"><?= e($customer['birthdate'] ?: 'غير مسجل') ?></td></tr>
            <tr><th style="text-align: right; padding: 8px;">ملاحظات:</th><td style="padding: 8px;"><?= e($customer['notes'] ?: '-') ?></td></tr>
        </table>
        
        <hr style="border: 0; border-top: 1px solid var(--line); margin: 20px 0;">
        
        <h4 style="margin-bottom: 10px; color: #16a34a;">إرسال رسالة ترويجية (WhatsApp) 💬</h4>
        <textarea id="cc-message" rows="5" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line); margin-bottom: 10px;" placeholder="اكتب رسالة العروض أو الترحيب هنا..."></textarea>
        
        <!-- أزرار القوالب الجاهزة -->
        <div style="display: flex; gap: 8px; margin-bottom: 15px; flex-wrap: wrap;">
            <button class="cc-template-btn" onclick="setTemplate('ترحيب')">قالب ترحيب</button>
            <button class="cc-template-btn" onclick="setTemplate('عروض')">قالب عروض</button>
            <button class="cc-template-btn" onclick="setTemplate('كل عام')">تهنئة</button>
        </div>
        
        <button class="cc-btn cc-btn-whatsapp" onclick="sendPromoMessage()">إرسال الرسالة الآن 🚀</button>
    </div>

    <!-- سجل فواتير العميل -->
    <div class="cc-panel" style="align-self: start;">
        <div class="cc-header">
            سجل مشتريات العميل
            <a href="index.php?r=customer_view&id=<?= e($customer['id']) ?>" class="cc-btn cc-btn-primary" style="font-size: 12px; padding: 6px 12px; text-decoration: none;">الذهاب لملف العميل الكامل</a>
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

function setTemplate(type) {
    const msgBox = document.getElementById('cc-message');
    if (type === 'ترحيب') {
        msgBox.value = `مرحباً بك أستاذ/ة ${customerName} 🌹\nشكراً لزيارتك لـ *حمزة للعطور*، نتشرف بخدمتك دائماً ونتمنى لك يوماً سعيداً! ✨`;
    } else if (type === 'عروض') {
        msgBox.value = `أهلاً بك ${customerName} 🌹\nيسعدنا في *حمزة للعطور* أن نخبرك بأحدث عروضنا لهذا الأسبوع:\n\n- عرض 1\n- عرض 2\n\nننتظر زيارتك للاستفادة من العرض! 🎁`;
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

<script>
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
