<?php

declare(strict_types=1);

/**
 * سكريبت صيانة يدوي لضبط توقيت فواتير 30 أكتوبر 2026 بعد التحويل للتوقيت الشتوي في مصر
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/db.php';

echo "=== فحص وضبط التوقيت الشتوي للنظام والفواتير ===\n";

$cairoTz = new DateTimeZone('Africa/Cairo');
$cairoOffset = (new DateTime('now', $cairoTz))->format('P');
echo "توقيت PHP الحالي: " . date('Y-m-d H:i:s') . " (Zone: " . date_default_timezone_get() . ", Offset: {$cairoOffset})\n";

try {
    $db = pdo();
    $mysqlNow = $db->query("SELECT NOW()")->fetchColumn();
    $mysqlTz = $db->query("SELECT @@session.time_zone")->fetchColumn();
    echo "توقيت MySQL الحالي: {$mysqlNow} (Timezone: {$mysqlTz})\n\n";

    // فحص عدد الفواتير من يوم 30 أكتوبر 2026
    $stmtCount = $db->query("SELECT COUNT(*) FROM invoices WHERE created_at >= '2026-10-30 00:00:00'");
    $count = (int)$stmtCount->fetchColumn();
    echo "عدد الفواتير المسجلة من يوم 30 أكتوبر 2026: {$count}\n";

    $stmtCheck = $db->query("SELECT setting_value FROM app_settings WHERE setting_key = 'dst_winter_time_2026_10_30_adjusted' LIMIT 1");
    $alreadyDone = $stmtCheck ? $stmtCheck->fetchColumn() : null;

    if ($alreadyDone) {
        echo "تنبيه: تم تعديل توقيت الفواتير مسبقاً بنجاح في قاعدة البيانات (تم تسجيل علامة dst_winter_time_2026_10_30_adjusted).\n";
    } else {
        echo "جاري تأخير وقت الفواتير ساعة واحدة لتوافق التوقيت الشتوي لمصر...\n";

        $db->beginTransaction();
        $db->exec("UPDATE invoices SET created_at = DATE_SUB(created_at, INTERVAL 1 HOUR) WHERE created_at >= '2026-10-30 00:00:00'");
        $db->exec("UPDATE payments SET created_at = DATE_SUB(created_at, INTERVAL 1 HOUR) WHERE created_at >= '2026-10-30 00:00:00'");

        try { $db->exec("UPDATE offline_sync_invoices SET synced_at = DATE_SUB(synced_at, INTERVAL 1 HOUR) WHERE synced_at >= '2026-10-30 00:00:00'"); } catch (Throwable $e) {}
        try { $db->exec("UPDATE financial_ledger SET created_at = DATE_SUB(created_at, INTERVAL 1 HOUR) WHERE created_at >= '2026-10-30 00:00:00'"); } catch (Throwable $e) {}
        try { $db->exec("UPDATE inventory_movements SET created_at = DATE_SUB(created_at, INTERVAL 1 HOUR) WHERE created_at >= '2026-10-30 00:00:00'"); } catch (Throwable $e) {}
        try { $db->exec("UPDATE manager_collections SET created_at = DATE_SUB(created_at, INTERVAL 1 HOUR) WHERE created_at >= '2026-10-30 00:00:00'"); } catch (Throwable $e) {}
        try { $db->exec("UPDATE branch_cash_transfers SET created_at = DATE_SUB(created_at, INTERVAL 1 HOUR) WHERE created_at >= '2026-10-30 00:00:00'"); } catch (Throwable $e) {}

        $db->exec("INSERT INTO app_settings (setting_key, setting_value) VALUES ('dst_winter_time_2026_10_30_adjusted', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");
        $db->commit();

        echo "تم تعديل جميع الفواتير والحركات بنجاح وحفظ الإعداد.\n";
    }

} catch (Throwable $e) {
    echo "حدث خطأ: " . $e->getMessage() . "\n";
}
