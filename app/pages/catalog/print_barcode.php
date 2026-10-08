<?php
$id = (int) ($_GET['id'] ?? 0);
$product = find_product($id);
if (!$product) {
    exit(__('المنتج غير موجود'));
}
$lang = current_lang();
$dir = $lang === 'en' ? 'ltr' : 'rtl';

/**
 * Generates a classic Code 39 Barcode SVG with the human-readable number
 * embedded INSIDE the SVG below the bars — just like a real barcode.
 * Works 100% offline and prints crisply on all thermal label printers.
 *
 * @param string $barcode   The barcode string to encode
 * @param float  $totalH    Total SVG height (bars + text area)
 * @param float  $textH     Height reserved for the human-readable number
 */
function drawCode39SVG(string $barcode, float $totalH = 42.0, float $textH = 10.0): string {
    $barcode = strtoupper(trim($barcode));
    if ($barcode === '') {
        return '';
    }

    // Standard Code 39 characters must be wrapped in start/stop asterisks (*)
    $input = $barcode;
    if (substr($input, 0, 1) !== '*') {
        $input = '*' . $input;
    }
    if (substr($input, -1) !== '*') {
        $input = $input . '*';
    }

    // Code 39 character encoding map (n = narrow bar/space, w = wide bar/space)
    $code39_map = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '$' => 'nwnwnwnnn',
        '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn', '*' => 'nwnnwnwnn'
    ];

    $narrow = 1.30; // safe narrow bar width in pixels
    $wide   = 3.25; // 2.5:1 ratio
    $gap    = 1.30; // inter-character gap

    // Height of the bars (leave $textH at the bottom for the number)
    $barH = $totalH - $textH;

    $rects = [];
    $x = 8.0; // Quiet zone left

    for ($i = 0; $i < strlen($input); $i++) {
        $char = $input[$i];
        if (!isset($code39_map[$char])) {
            continue;
        }

        $pattern = $code39_map[$char];
        for ($j = 0; $j < 9; $j++) {
            $w = ($pattern[$j] === 'w') ? $wide : $narrow;
            if ($j % 2 === 0) {
                // Even indexes = black bars, drawn from y=0 down to $barH
                $rects[] = sprintf('<rect x="%.2f" y="0" width="%.2f" height="%.2f" fill="#000000" />', $x, $w, $barH);
            }
            $x += $w;
        }
        $x += $gap;
    }

    $totalWidth = $x + 8.0; // Quiet zone right

    // Center the human-readable text horizontally inside the SVG
    $textY    = $totalH - 1.5;           // baseline just above the bottom edge
    $textX    = $totalWidth / 2.0;       // horizontally centered
    $fontSize = $textH * 0.72;          // font-size relative to reserved area

    // Space out characters slightly for readability (letter-spacing via tspan is limited,
    // so we use letter-spacing attribute directly on the text element)
    $letterSpacing = 1.8;

    $svg  = sprintf(
        '<svg viewBox="0 0 %.2f %.2f" width="100%%" height="100%%" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg" style="display:block;">',
        $totalWidth, $totalH
    );
    // White background for the text area only (so bars remain sharp)
    $svg .= sprintf(
        '<rect x="0" y="%.2f" width="%.2f" height="%.2f" fill="#ffffff" />',
        $barH, $totalWidth, $textH
    );
    $svg .= implode("\n", $rects);
    // Embedded human-readable number
    $svg .= sprintf(
        '<text x="%.2f" y="%.2f" text-anchor="middle" font-family="\'Courier New\', Courier, monospace" font-size="%.2f" font-weight="bold" fill="#000000" letter-spacing="%.2f">%s</text>',
        $textX, $textY, $fontSize, $letterSpacing, htmlspecialchars($barcode, ENT_XML1)
    );
    $svg .= '</svg>';

    return $svg;
}
?>
<!doctype html>

<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="utf-8">
    <title><?= e(__('طباعة باركود')) ?> - <?= e($product['name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        
        * {
            box-sizing: border-box;
        }
        
        body {
            background: #f5f0e8;
            color: #2a2318;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            margin: 0;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            gap: 20px;
        }
        
        /* Barcode Sticker Preview Box */
        .barcode-container {
            border: 2px dashed #C9A84C;
            padding: 3px 5px 2px 5px;
            text-align: center;
            width: 50mm;
            height: 25mm;
            background: #fff;
            box-shadow: 0 10px 25px rgba(26, 18, 16, 0.08);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            overflow: hidden;
            position: relative;
            border-radius: 6px;
        }
        
        .barcode-brand {
            font-size: 6px;
            font-weight: 900;
            color: #c09435;
            margin: 0;
            line-height: 1;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        /* اسم المنتج: أكبر ما يمكن بشكل تلقائي */
        .barcode-title {
            font-size: 22px;
            font-weight: 700;
            font-family: 'Noto Naskh Arabic', 'Cairo', Tahoma, Arial, sans-serif;
            color: #0d0a08;
            margin: 0;
            width: 100%;
            white-space: nowrap;
            overflow: hidden;
            line-height: 1.1;
            letter-spacing: 0;
            display: block;
        }
        
        /* الباركود: أصغر حجماً */
        .barcode-svg-wrapper {
            width: 95%;
            height: 28px;
            margin: 0;
            display: block;
        }
        
        /* Interactive controls for screen */
        .controls-card {
            background: #fdfaf4;
            padding: 24px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(192, 148, 53, .10);
            border: 1.5px solid rgba(192, 148, 53, 0.28);
            text-align: center;
            width: 340px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        
        .controls-card h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #2a2318;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #c09435, #835f11);
            color: #eeece8;
            border: 0;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            width: 100%;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            font-family: inherit;
            box-shadow: 0 4px 14px rgba(192, 148, 53, 0.4);
        }
        
        .btn-print:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(192, 148, 53, 0.5);
        }
        
        .btn-print:active {
            transform: translateY(1px);
        }
        
        .btn-back {
            color: #64748b;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: color 0.15s ease;
        }
        
        .btn-back:hover {
            color: #1a1210;
        }
        
        @media print {
            @page {
                size: 50mm 25mm;
                margin: 0;
            }
            body {
                background: #fff;
                padding: 0;
                margin: 0;
                min-height: auto;
                width: 50mm;
                height: 25mm;
                display: flex;
                justify-content: center;
                align-items: center;
                overflow: hidden;
                font-family: 'Noto Naskh Arabic', 'Cairo', Tahoma, Arial, sans-serif !important;
                font-weight: 700 !important;
            }
            .barcode-container {
                border: 0;
                padding: 3px 5px 2px 5px;
                width: 50mm;
                height: 25mm;
                box-shadow: none;
                background: #fff;
                border-radius: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    
    <div class="no-print" style="text-align: center; margin-bottom: 5px; color: #64748b; font-size: 13px; font-weight: 700;">
        ⚙️ <?= e(__('معاينة ملصق باركود حمزة للعطور (50مم × 25مم)')) ?>
    </div>

    <!-- The actual sticker container -->
    <div class="barcode-container">
        <div class="barcode-brand"><?= e(__('حمزة للعطور')) ?></div>
        <div class="barcode-title"><?= e($product['name']) ?></div>
        <div class="barcode-svg-wrapper">
            <?= drawCode39SVG($product['barcode'], 28.0, 7.0) ?>
        </div>
    </div>
    
    <div class="controls-card no-print">
        <h3><?= e($product['name']) ?></h3>
        <button class="btn-print" onclick="window.print()"><?= e(__('طباعة الملصق الآن')) ?></button>
        <button type="button" class="btn-back" onclick="window.close(); if (!window.closed) history.back();">← <?= e(__('العودة إلى إدارة المنتجات')) ?></button>
    </div>

    <script>
        // Auto-fit product name font size to fill the label width
        function fitBarcodeTitle() {
            const el = document.querySelector('.barcode-title');
            if (!el) return;
            const maxSize = 22;
            const minSize = 7;
            let size = maxSize;
            el.style.fontSize = size + 'px';
            // Shrink until text fits within the container width
            while (el.scrollWidth > el.offsetWidth && size > minSize) {
                size -= 0.5;
                el.style.fontSize = size + 'px';
            }
        }

        // Auto trigger print preview faster
        let printTriggered = false;
        function triggerPrint() {
            if (printTriggered) return;
            printTriggered = true;
            setTimeout(function() { window.print(); }, 150);
        }
        document.addEventListener('DOMContentLoaded', function() {
            fitBarcodeTitle();
            setTimeout(triggerPrint, 500); // 500ms max wait
        });
        window.addEventListener('load', function() {
            fitBarcodeTitle();
            triggerPrint();
        });
    </script>
</body>
</html>
