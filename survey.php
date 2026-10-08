<?php

declare(strict_types=1);

// Prevent blank screen under any circumstances
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
header('Content-Type: text/html; charset=UTF-8');

// Safely bootstrap database and services without crashing
try {
    if (file_exists(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/config.php';
    }
    if (file_exists(__DIR__ . '/app/helpers.php')) {
        require_once __DIR__ . '/app/helpers.php';
    }
    if (file_exists(__DIR__ . '/app/db.php')) {
        require_once __DIR__ . '/app/db.php';
    }
    if (file_exists(__DIR__ . '/app/services.php')) {
        require_once __DIR__ . '/app/services.php';
    }
} catch (\Throwable $e) {}

// Safe HTML escaper fallback
if (!function_exists('e')) {
    function e(mixed $value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$dbOk = false;
$survey = null;
$storeName = 'حمزة للعطور';
$alreadyRated = false;
$attemptedSwitch = false;
$newlyRated = false;

try {

    if (function_exists('setting_value')) {
        $cfgStore = (string)setting_value('shop_name', 'حمزة للعطور');
        if (trim($cfgStore) !== '') {
            $storeName = trim($cfgStore);
        }
    }

    if (function_exists('pdo')) {
        $db = pdo();
        $dbOk = true;
    }
} catch (\Throwable $e) {
    $dbOk = false;
}

$token = trim((string)($_GET['t'] ?? $_POST['t'] ?? ''));
$ratingParam = trim((string)($_GET['r'] ?? $_POST['r'] ?? ''));
$notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : null;
$submitted = !empty($_POST['is_submit']);

$customerName = 'عميلنا العزيز';
$branchName = $storeName;
$isSatisfied = null;

if ($dbOk && $token !== '') {
    try {
        $stmt = $db->prepare("
            SELECT q.*, 
                   i.invoice_number, 
                   c.name AS customer_name, 
                   loc.name AS location_name
            FROM customer_survey_queue q
            LEFT JOIN invoices i ON i.id = q.invoice_id
            LEFT JOIN customers c ON c.id = COALESCE(q.customer_id, i.customer_id)
            LEFT JOIN locations loc ON loc.id = i.location_id
            WHERE q.token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $survey = $stmt->fetch();

        if ($survey) {
            if (!empty($survey['customer_name'])) {
                $customerName = trim($survey['customer_name']);
            }
            if (!empty($survey['location_name'])) {
                $branchName = trim($survey['location_name']);
            }

            // فحص هل العميل قد اختار تقييمه مسبقاً لمنع تغيير التقييم
            if (!empty($survey['rating'])) {
                $alreadyRated = true;
                $isSatisfied = ($survey['rating'] === 'satisfied');

                // إذا حاول العميل إرسال تقييم مختلف (مثلاً اختار راضي سابقاً والآن فتح رابط غير راضي أو العكس)
                if ($ratingParam !== '') {
                    $incomingSatisfied = ($ratingParam === '1' || $ratingParam === 'satisfied');
                    if ($incomingSatisfied !== $isSatisfied) {
                        $attemptedSwitch = true;
                    }
                }

                // حفظ الملاحظات الإضافية إن وجدت دون تغيير التقييم الأصلي
                if ($notes !== null && $submitted && trim($notes) !== '') {
                    record_customer_survey_feedback($token, $survey['rating'], $notes);
                    $survey['feedback_notes'] = $notes;
                }
            } else {
                // العميل لم يسجل تقييمه بعد
                if ($ratingParam === '1' || $ratingParam === 'satisfied') {
                    $isSatisfied = true;
                    $survey = record_customer_survey_feedback($token, 'satisfied', $notes);
                    $newlyRated = true;
                } elseif ($ratingParam === '0' || $ratingParam === 'unsatisfied') {
                    $isSatisfied = false;
                    $survey = record_customer_survey_feedback($token, 'unsatisfied', $notes);
                    $newlyRated = true;
                } elseif ($submitted && $notes !== null) {
                    // لم يحدد خياراً من الرابط لكن أرسل النموذج
                    $fallbackRating = ($notes !== '' ? 'unsatisfied' : 'satisfied');
                    $survey = record_customer_survey_feedback($token, $fallbackRating, $notes);
                    $isSatisfied = ($fallbackRating === 'satisfied');
                    $newlyRated = true;
                }
            }
        }
    } catch (\Throwable $e) {
        $survey = null;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($storeName) ?> - استبيان رأي العميل</title>
    <link rel="icon" type="image/png" href="assets/whatsapp_logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #c5a059;
            --primary-light: #dfbe7d;
            --primary-dark: #9a7833;
            --bg-dark: #0b1120;
            --card-bg: #151f32;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: rgba(197, 160, 89, 0.25);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.2);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.2);
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Cairo', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: radial-gradient(circle at top, #1e293b 0%, #0b1120 70%, #050811 100%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            direction: rtl;
        }
        .container {
            width: 100%;
            max-width: 480px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 34px 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 30px rgba(197, 160, 89, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
        }
        .logo-wrap {
            margin-bottom: 22px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .logo-img {
            max-height: 85px;
            width: auto;
            object-fit: contain;
            filter: drop-shadow(0 4px 14px rgba(197, 160, 89, 0.35));
        }
        .logo-text {
            font-size: 26px;
            font-weight: 900;
            color: var(--primary);
            letter-spacing: 0.5px;
            text-shadow: 0 2px 10px rgba(197, 160, 89, 0.4);
        }
        .badge-branch {
            display: inline-block;
            background: rgba(197, 160, 89, 0.12);
            color: var(--primary-light);
            padding: 5px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 18px;
            border: 1px solid rgba(197, 160, 89, 0.3);
        }
        .status-icon {
            font-size: 60px;
            margin-bottom: 16px;
            display: inline-block;
            animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn {
            0% { transform: scale(0.5); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        h1, h2 {
            font-size: 23px;
            font-weight: 800;
            margin-bottom: 12px;
            line-height: 1.4;
            color: #ffffff;
        }
        .title-satisfied {
            color: #34d399;
            text-shadow: 0 2px 12px rgba(16, 185, 129, 0.3);
        }
        .title-unsatisfied {
            color: #fca5a5;
            text-shadow: 0 2px 12px rgba(239, 68, 68, 0.3);
        }
        p {
            color: var(--text-muted);
            font-size: 14.5px;
            line-height: 1.7;
            margin-bottom: 22px;
        }
        .alert-box {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: right;
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-locked {
            background: rgba(197, 160, 89, 0.12);
            border: 1px solid rgba(197, 160, 89, 0.35);
            color: var(--primary-light);
        }
        .alert-success {
            background: var(--success-glow);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
        }
        .action-card {
            background: rgba(11, 17, 32, 0.65);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.07);
        }
        .rating-btn-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-top: 16px;
        }
        .btn-choice {
            padding: 16px 12px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 800;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 2px solid transparent;
            transition: all 0.25s ease;
            cursor: pointer;
        }
        .btn-choice:active {
            transform: scale(0.97);
        }
        .btn-satisfied {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border-color: rgba(16, 185, 129, 0.35);
        }
        .btn-satisfied:hover {
            background: #10b981;
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }
        .btn-unsatisfied {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.35);
        }
        .btn-unsatisfied:hover {
            background: #ef4444;
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }
        textarea {
            width: 100%;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 14px;
            color: #ffffff;
            font-family: inherit;
            font-size: 14px;
            line-height: 1.5;
            resize: none;
            margin-bottom: 14px;
            transition: border-color 0.2s ease;
        }
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(197, 160, 89, 0.2);
        }
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #ffffff;
            border: none;
            padding: 14px 20px;
            font-size: 15px;
            font-weight: 800;
            border-radius: 12px;
            cursor: pointer;
            box-shadow: 0 6px 16px rgba(197, 160, 89, 0.3);
            transition: all 0.2s ease;
        }
        .btn-submit:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }
        .btn-submit-danger {
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
        }
        .footer-note {
            margin-top: 24px;
            font-size: 12.5px;
            color: #64748b;
        }
        .vote-badge-locked {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.06);
            color: var(--text-muted);
            margin-top: 6px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="logo-wrap">
        <img class="logo-img" src="assets/whatsapp_logo.png" alt="<?= e($storeName) ?>" onerror="this.onerror=null;this.src='assets/logo.png';">
    </div>

    <?php if (!$survey): ?>
        <!-- حالة الرابط غير الصالح أو عدم توفر الفاتورة -->
        <div class="status-icon">✨</div>
        <h2>شكراً لاهتمامك - <?= e($storeName) ?></h2>
        <p>
            عميلنا العزيز، نسعد دائماً بزيارتك لفروعنا ونتطلع لخدمتك بأرقى المعايير العطرية الفاخرة دائماً.
        </p>
    <?php else: ?>
        <span class="badge-branch">فرع: <?= e($branchName) ?></span>

        <?php if ($attemptedSwitch): ?>
            <!-- تنبيه منع تعديل التقييم -->
            <div class="alert-box alert-locked">
                <span>🔒</span>
                <span>لقد قمت بتسجيل تقييمك مسبقاً لهذا الطلب، ولا يمكن تغيير التقييم. شكراً لاهتمامك!</span>
            </div>
        <?php endif; ?>

        <?php if ($isSatisfied === true): ?>
            <!-- اختيار العميل: راضي -->
            <div class="status-icon">💚</div>
            <h1 class="title-satisfied">شكراً لاختيارك حمزة للعطور</h1>
            
            <p>
                أهلاً بك أستاذ/ة <strong><?= e($customerName) ?></strong> 🌹<br>
                سعداء جداً بأن خدمتنا في فرع <strong><?= e($branchName) ?></strong> نالت رضاك واستحسانك. نسعى دائماً لتقديم تجربة عطرية فاخرة تليق بذوقك الرفيع!
            </p>

            <div class="vote-badge-locked">
                <span>✅ تم تسجيل تقييمك (راضٍ) - شكراً لثقتك بنا</span>
            </div>

            <?php if ($submitted): ?>
                <div class="alert-box alert-success" style="margin-top: 18px;">
                    <span>💌</span>
                    <span>تم استلام تعليقك بنجاح، شكراً لك على هذه الكلمات الطيبة!</span>
                </div>
            <?php else: ?>
                <form method="post" class="action-card" style="margin-top: 20px;">
                    <input type="hidden" name="t" value="<?= e($token) ?>">
                    <input type="hidden" name="r" value="satisfied">
                    <input type="hidden" name="is_submit" value="1">
                    <label style="display: block; text-align: right; font-size: 13px; font-weight: 700; margin-bottom: 8px; color: var(--primary-light);">
                        هل تود كتابة كلمة لطيفة أو اقتراح لخدمة أفضل؟ (اختياري):
                    </label>
                    <textarea name="notes" rows="3" placeholder="اكتب كلمتك هنا..."><?= e($survey['feedback_notes'] ?? '') ?></textarea>
                    <button type="submit" class="btn-submit">إرسال التعليق ✨</button>
                </form>
            <?php endif; ?>

        <?php elseif ($isSatisfied === false): ?>
            <!-- اختيار العميل: غير راضي -->
            <div class="status-icon">🤝</div>
            <h1 class="title-unsatisfied">سوف يتم التواصل معك في أقرب وقت لحل المشكلة</h1>
            
            <p>
                أستاذ/ة <strong><?= e($customerName) ?></strong> 🙏<br>
                نعتذر بشدة عن أي تقصير أو إزعاج واجهته في زيارتك لفرع <strong><?= e($branchName) ?></strong>. رضاك وراحتك هي غايتنا الأولى، ونعمل على متابعة الأمر وتصحيحه فوراً.
            </p>

            <div class="vote-badge-locked">
                <span>⚠️ تم تسجيل طلبك - فريق خدمة العملاء يتابع حالتك</span>
            </div>

            <?php if ($submitted): ?>
                <div class="alert-box alert-success" style="margin-top: 18px;">
                    <span>✅</span>
                    <span>تم إرسال تفاصيل المشكلة بنجاح، وسيتواصل معك مشرف الفرع والإدارة في أقرب وقت لحل الأمر.</span>
                </div>
            <?php else: ?>
                <form method="post" class="action-card" style="margin-top: 20px;">
                    <input type="hidden" name="t" value="<?= e($token) ?>">
                    <input type="hidden" name="r" value="unsatisfied">
                    <input type="hidden" name="is_submit" value="1">
                    <label style="display: block; text-align: right; font-size: 13.5px; font-weight: 700; margin-bottom: 8px; color: #fca5a5;">
                        فضلاً وضح لنا ما هي المشكلة التي واجهتك بالتفصيل:
                    </label>
                    <textarea name="notes" rows="4" required placeholder="اكتب تفاصيل ما حدث لنتمكن من حله والتواصل معك..."><?= e($survey['feedback_notes'] ?? '') ?></textarea>
                    <button type="submit" class="btn-submit btn-submit-danger">إرسال تفاصيل المشكلة للإدارة 🚀</button>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <!-- صفحة الخيارين في حال فتح الرابط دون باراميتر ولم يتم التقييم بعد -->
            <h2>أهلاً بك أستاذ/ة <?= e($customerName) ?> 🌹</h2>
            <p>يهمنا جداً معرفة رأيك في زيارتك اليوم لفرع <strong><?= e($branchName) ?></strong>:</p>

            <div class="action-card">
                <span style="font-weight: 800; font-size: 15.5px; color: #ffffff; display: block; margin-bottom: 12px;">
                    هل كانت الخدمة وتجربة الشراء مرضية لك؟
                </span>
                <div class="rating-btn-group">
                    <a href="survey.php?t=<?= e($token) ?>&r=1" class="btn-choice btn-satisfied">
                        <span>🟢 راضٍ 👍</span>
                    </a>
                    <a href="survey.php?t=<?= e($token) ?>&r=0" class="btn-choice btn-unsatisfied">
                        <span>🔴 غير راضٍ 👎</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <div class="footer-note">
        جميع الحقوق محفوظة &copy; <?= date('Y') ?> - <?= e($storeName) ?>
    </div>
</div>

</body>
</html>
