<?php

declare(strict_types=1);

$id = (int)($_GET['id'] ?? 0);
$number = trim((string)($_GET['number'] ?? ''));

$invoice = null;
if ($id > 0) {
    $invoice = find_shipping_order_by_barcode((string)$id);
} elseif ($number !== '') {
    $invoice = find_shipping_order_by_barcode($number);
}

if (!$invoice) {
    ?>
    <!doctype html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <title>بوليصة الشحن غير موجودة</title>
        <link rel="stylesheet" href="assets/style.css">
    </head>
    <body style="padding: 40px; text-align: center; font-family: 'Cairo', sans-serif;">
        <div class="card" style="max-width: 500px; margin: 40px auto; padding: 30px;">
            <div style="font-size: 50px; margin-bottom: 15px;">📦❌</div>
            <h2 style="color: var(--danger);">لم يتم العثور على بوليصة الشحن</h2>
            <p style="color: var(--muted); margin: 15px 0;">تأكد من رقم الفاتورة أو الأوردر المطلوب.</p>
            <a href="index.php?r=online_pos" class="btn primary" style="margin-top: 10px;">العودة لكاشير أونلاين</a>
            <a href="index.php?r=shipping_tracker" class="btn secondary" style="margin-top: 10px; margin-right: 8px;">تتبع الشحنات</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$recipientName = $invoice['recipient_name'] ?: ($invoice['customer_name'] ?? 'عميل محترم');
$phone1        = $invoice['shipping_phone'] ?: ($invoice['customer_phone'] ?? '-');
$phone2        = $invoice['shipping_phone2'] ?? '';
$governorate   = $invoice['shipping_governorate'] ?? '';
$city          = $invoice['shipping_city'] ?? '';
$streetAddress = $invoice['shipping_address'] ?? '';
$notes         = $invoice['shipping_notes'] ?: ($invoice['notes'] ?? '');
$codAmount     = isset($invoice['cod_amount']) ? (float)$invoice['cod_amount'] : (float)$invoice['due_total'];
$totalAmount   = (float)$invoice['total'];
$isFullyPaid   = ($codAmount <= 0.001);
$shippingCost  = (float)($invoice['shipping_cost'] ?? 0.0);
$lines         = $invoice['lines'] ?? [];
$barcodeSvg    = draw_barcode_svg($invoice['invoice_number'], 55.0, 14.0);
$autoPrint     = isset($_GET['print']) && $_GET['print'] === '1';
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بوليصة شحن - <?= e($invoice['invoice_number']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Cairo', system-ui, -apple-system, sans-serif;
            background: #eef1f5;
            color: #111;
            direction: rtl;
            font-size: 13px;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Screen Top Toolbar (Hidden in Print) */
        .no-print-bar {
            background: #1e293b;
            color: #fff;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .no-print-bar .actions {
            display: flex;
            gap: 10px;
        }

        .btn-ui {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13.5px;
            font-weight: 700;
            font-family: inherit;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
        }

        .btn-ui.print-btn {
            background: #c9a84c;
            color: #1a1210;
        }
        .btn-ui.print-btn:hover {
            background: #e5c363;
        }

        .btn-ui.back-btn {
            background: #334155;
            color: #f1f5f9;
        }
        .btn-ui.back-btn:hover {
            background: #475569;
        }

        /* Waybill Thermal Label Page (100mm x 150mm standard) */
        .waybill-wrapper {
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        .waybill-card {
            width: 100mm;
            min-height: 150mm;
            background: #fff;
            border: 2px solid #000;
            border-radius: 6px;
            padding: 8mm 7mm;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Header */
        .wb-header {
            border-bottom: 2px solid #000;
            padding-bottom: 6px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .wb-brand h1 {
            font-size: 19px;
            font-weight: 900;
            color: #000;
            letter-spacing: 0.3px;
        }

        .wb-brand p {
            font-size: 10.5px;
            font-weight: 700;
            color: #333;
        }

        .wb-badge {
            border: 2px solid #000;
            padding: 4px 8px;
            font-size: 11px;
            font-weight: 800;
            border-radius: 4px;
            background: #f8f8f8;
            text-align: center;
        }

        /* Large Barcode Section */
        .wb-barcode-section {
            border: 1.5px solid #000;
            border-radius: 4px;
            padding: 6px;
            margin-bottom: 8px;
            text-align: center;
            background: #fff;
        }

        .wb-barcode-container {
            width: 100%;
            height: 52px;
            margin: 0 auto;
        }

        .wb-barcode-container svg {
            width: 100%;
            height: 100%;
        }

        .wb-inv-number {
            font-size: 15px;
            font-weight: 900;
            letter-spacing: 1.5px;
            font-family: monospace;
            margin-top: 3px;
            color: #000;
        }

        /* Recipient Info Box */
        .wb-box {
            border: 1.5px solid #000;
            border-radius: 4px;
            padding: 7px 9px;
            margin-bottom: 7px;
        }

        .wb-box-title {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            border-bottom: 1px dashed #888;
            padding-bottom: 3px;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .wb-recipient-name {
            font-size: 16px;
            font-weight: 900;
            color: #000;
            margin-bottom: 4px;
        }

        .wb-phones {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 14.5px;
            font-weight: 800;
            margin-bottom: 6px;
            direction: ltr;
            justify-content: flex-end;
        }

        .wb-phone-tag {
            background: #f1f1f1;
            padding: 2px 7px;
            border-radius: 3px;
            border: 1px solid #ccc;
            color: #000;
            font-family: monospace;
        }

        .wb-address-line {
            font-size: 13.5px;
            font-weight: 700;
            line-height: 1.4;
            color: #111;
        }

        .wb-gov-city {
            display: inline-block;
            background: #000;
            color: #fff;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 800;
            margin-left: 6px;
        }

        /* COD & Payment Banner */
        .wb-cod-box {
            border: 2.5px solid #000;
            border-radius: 5px;
            padding: 8px;
            margin-bottom: 7px;
            text-align: center;
            background: #fff;
        }

        .wb-cod-box.paid {
            background: #f0fdf4;
            border-color: #166534;
            color: #166534;
        }

        .wb-cod-title {
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 2px;
        }

        .wb-cod-amount {
            font-size: 22px;
            font-weight: 900;
            letter-spacing: 0.5px;
        }

        /* Items Summary */
        .wb-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 4px;
        }

        .wb-items-table th, .wb-items-table td {
            padding: 3px 4px;
            border: 1px solid #ddd;
            text-align: right;
        }

        .wb-items-table th {
            background: #f5f5f5;
            font-weight: 800;
        }

        .wb-items-table td.qty {
            text-align: center;
            font-weight: 800;
            width: 38px;
        }

        /* Notes Box */
        .wb-notes {
            background: #fffbeb;
            border: 1px dashed #b45309;
            border-radius: 4px;
            padding: 5px 8px;
            font-size: 11.5px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 6px;
        }

        /* Footer */
        .wb-footer {
            border-top: 1.5px solid #000;
            padding-top: 5px;
            font-size: 10px;
            color: #444;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Print Specific Styling */
        @media print {
            @page {
                size: 100mm 150mm;
                margin: 0;
            }

            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .no-print-bar {
                display: none !important;
            }

            .waybill-wrapper {
                padding: 0 !important;
                margin: 0 !important;
                display: block !important;
            }

            .waybill-card {
                width: 100mm !important;
                min-height: 150mm !important;
                max-height: 150mm !important;
                border: 2px solid #000 !important;
                box-shadow: none !important;
                padding: 6mm 5mm !important;
                page-break-after: always;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

<div class="no-print-bar">
    <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-size:22px;">🏷️</span>
        <div>
            <div style="font-weight:800; font-size:15px;">بوليصة شحن: <?= e($invoice['invoice_number']) ?></div>
            <div style="font-size:11px; opacity:0.8;"><?= e($recipientName) ?> - <?= e($governorate ?: 'أونلاين') ?></div>
        </div>
    </div>
    <div class="actions">
        <button class="btn-ui print-btn" onclick="window.print()">
            <span>🖨️</span> طباعة البوليصة (Sticker)
        </button>
        <a href="index.php?r=invoice_view&id=<?= e($invoice['id']) ?>" target="_blank" class="btn-ui back-btn">
            <span>🧾</span> فاتورة الكاشير
        </a>
        <a href="index.php?r=shipping_tracker&search=<?= urlencode($invoice['invoice_number']) ?>" class="btn-ui back-btn">
            <span>📦</span> تتبع وحالة الشحنة
        </a>
        <a href="index.php?r=online_pos" class="btn-ui back-btn">
            <span>🛵</span> كاشير أونلاين
        </a>
    </div>
</div>

<div class="waybill-wrapper">
    <div class="waybill-card">
        
        <!-- Header -->
        <div class="wb-header">
            <div class="wb-brand">
                <h1>حمزة للعطور</h1>
                <p>HAMZA FOR PERFUMES & FRAGRANCES</p>
            </div>
            <div class="wb-badge">
                شحن أونلاين<br>
                <small style="font-size:9px;"><?= date('d/m/Y') ?></small>
            </div>
        </div>

        <!-- Barcode Section -->
        <div class="wb-barcode-section">
            <div class="wb-barcode-container">
                <?= $barcodeSvg ?>
            </div>
            <div class="wb-inv-number"><?= e($invoice['invoice_number']) ?></div>
        </div>

        <!-- Recipient Information -->
        <div class="wb-box">
            <div class="wb-box-title">
                <span>📍 بيانات العميل / المستلم</span>
                <span>DESTINATION</span>
            </div>
            <div class="wb-recipient-name"><?= e($recipientName) ?></div>
            
            <div class="wb-phones">
                <?php if ($phone2): ?>
                    <span class="wb-phone-tag" title="هاتف إضافي">📞 بديل: <?= e($phone2) ?></span>
                <?php endif; ?>
                <span class="wb-phone-tag" title="هاتف أساسي">📱 <?= e($phone1) ?></span>
            </div>

            <div class="wb-address-line">
                <?php if ($governorate): ?>
                    <span class="wb-gov-city"><?= e($governorate) ?><?= $city ? ' - ' . e($city) : '' ?></span>
                <?php endif; ?>
                <span><?= e($streetAddress ?: 'العنوان مسجل هاتفياً مع العميل') ?></span>
            </div>
        </div>

        <!-- COD / Payment Collection Box -->
        <div class="wb-cod-box <?= $isFullyPaid ? 'paid' : '' ?>">
            <?php if ($isFullyPaid): ?>
                <div class="wb-cod-title">حالة الدفع: مدفوع مسبقاً بالكامل ✓</div>
                <div class="wb-cod-amount" style="font-size: 18px; color: #166534;">
                    لا يُحصّل أي مبلغ من العميل (0.00 ج.م)
                </div>
            <?php else: ?>
                <div class="wb-cod-title">المبلغ المطلوب تحصيله من العميل (C.O.D)</div>
                <div class="wb-cod-amount">
                    <?= number_format($codAmount, 2) ?> <span style="font-size:15px;">ج.م</span>
                </div>
                <div style="font-size: 10px; font-weight: 700; color: #555; margin-top: 2px;">
                    (شامل ثمن المنتجات ومصاريف الشحن والتوصيل)
                </div>
            <?php endif; ?>
        </div>

        <!-- Shipping Notes if present -->
        <?php if ($notes): ?>
            <div class="wb-notes">
                <strong>ملاحظات التوصيل:</strong> <?= e($notes) ?>
            </div>
        <?php endif; ?>

        <!-- Package Contents Summary -->
        <div class="wb-box" style="margin-bottom:0; flex-grow:1; display:flex; flex-direction:column;">
            <div class="wb-box-title">
                <span>🛍️ محتويات الشحنة (<?= count($lines) ?> صنف)</span>
                <span>ITEMS</span>
            </div>
            <div style="max-height: 120px; overflow: hidden;">
                <table class="wb-items-table">
                    <thead>
                        <tr>
                            <th>الصنف / العطر</th>
                            <th class="qty">العدد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($lines, 0, 5) as $l): ?>
                            <tr>
                                <td><?= e($l['description'] ?: ($l['product_name'] ?? 'منتج')) ?></td>
                                <td class="qty"><?= (float)$l['quantity'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($lines) > 5): ?>
                            <tr>
                                <td colspan="2" style="text-align:center; font-style:italic; color:#666;">
                                    + <?= count($lines) - 5 ?> أصناف إضافية داخل الشحنة
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="wb-footer">
            <div>
                <strong>الراسل:</strong> حمزة للعطور | فرع: <?= e($invoice['location_name'] ?? 'أونلاين') ?>
            </div>
            <div>
                <?= date('d/m/Y - h:i A', strtotime($invoice['created_at'])) ?>
            </div>
        </div>

    </div>
</div>

<script>
    <?php if ($autoPrint): ?>
    window.addEventListener('load', () => {
        setTimeout(() => {
            window.print();
        }, 300);
    });
    <?php endif; ?>
</script>

</body>
</html>

