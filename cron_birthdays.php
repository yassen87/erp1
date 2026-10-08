<?php

declare(strict_types=1);

// ضبط التوقيت على توقيت مصر
date_default_timezone_set('Africa/Cairo');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/services.php';

// التأكد من تطبيق تحديثات قاعدة البيانات إن لم تكن مطبقة
try {
    ensure_database_schema();
} catch (Throwable $e) {
    // Schema already exists or connection ok
}

$isCli = (php_sapi_name() === 'cli');
$force = isset($_GET['force']) || (isset($argv[1]) && $argv[1] === '--force');

$logHeader = "[{$timestamp}] [WhatsApp Cron] ";

// 1. معالجة طابور استبيانات رضا العملاء بعد الفاتورة (تعمل في أي وقت حان موعده)
try {
    $surveyResult = process_survey_queue(25);
    if ($surveyResult['sent'] > 0) {
        echo "{$logHeader}تم إرسال {$surveyResult['sent']} رسالة استبيان رضا عملاء بعد الفاتورة.\n";
    }
} catch (Throwable $surveyErr) {
    echo "{$logHeader}تنبيه استبيانات العملاء: " . $surveyErr->getMessage() . "\n";
}

// 2. التحقق من تفعيل ميزة أعياد الميلاد في الإعدادات
if (setting_value('whatsapp_birthday_enabled', '1') === '0') {
    echo "{$logHeader}خدمة رسائل عيد الميلاد معطلة من إعدادات النظام.\n";
    if (!$isCli) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'خدمة رسائل عيد الميلاد معطلة']);
    }
    exit(0);
}

// التحقق من موعد الإرسال المحدد (إذا لم يكن هناك إجبار فوري)
if (!$force) {
    $sendTime = (string) setting_value('whatsapp_birthday_send_time', '12:00');
    if (preg_match('/^(\d{1,2}):(\d{2})/', trim($sendTime), $m)) {
        $targetTime = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    } elseif (preg_match('/^(\d{1,2})/', trim($sendTime), $m)) {
        $targetTime = sprintf('%02d:00', (int)$m[1]);
    } else {
        $targetTime = '12:00';
    }
    
    $currentTime = date('H:i'); // توقيت 24 ساعة بالساعة والدقيقة
    if ($currentTime < $targetTime) {
        echo "{$logHeader}الوقت الحالي ({$currentTime}) يسبق موعد الإرسال اليومي المحدد ({$targetTime}). بانتظار حلول الموعد.\n";
        if (!$isCli) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => "الوقت الحالي ({$currentTime}) يسبق موعد الإرسال اليومي المحدد ({$targetTime})",
                'skipped_time' => true,
                'current_time' => $currentTime,
                'scheduled_time' => $targetTime
            ], JSON_UNESCAPED_UNICODE);
        }
        exit(0);
    }
}

try {
    $result = process_daily_birthday_whatsapp($force);
    $summary = sprintf(
        "إجمالي عملاء اليوم: %d | تم الإرسال: %d | تم التخطي (أُرسل مسبقاً): %d | فشل: %d",
        $result['total_today'],
        $result['sent_count'],
        $result['skipped_count'],
        $result['failed_count']
    );

    echo "{$logHeader}{$summary}\n";
    if (!empty($result['sent_names'])) {
        echo "{$logHeader}العملاء الذين تم إرسال التهنئة لهم: " . implode('، ', $result['sent_names']) . "\n";
    }

    if (!$isCli) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => true, 'summary' => $summary], $result), JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    $errorMsg = "{$logHeader}خطأ أثناء المعالجة: " . $e->getMessage();
    echo "{$errorMsg}\n";
    if (!$isCli) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit(1);
}
