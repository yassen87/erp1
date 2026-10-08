<?php
$invoice = find_invoice((int) ($_GET['id'] ?? 0));
if (!$invoice) {
    echo '<section class="page-head"><h2>الفاتورة غير موجودة</h2><p>لم يتم العثور على الفاتورة المطلوبة.</p></section>';
    return;
}
$lines = invoice_lines_rows((int) $invoice['id']);
$payments = invoice_payments_rows((int) $invoice['id']);
$allFormulaDefaults = formula_defaults_rows();

function invoice_component_modified_info(array $component, array $allFormulaDefaults, ?int $bottleSizeMl = null): array
{
    $result = ['isModified' => false, 'defaultQty' => 0];
    if (($component['unit'] ?? '') !== 'gram' || !empty($component['size_ml'])) {
        return $result;
    }

    $perfumeId = (int) $component['component_product_id'];
    $qty = (float) $component['quantity'];

    foreach ($allFormulaDefaults as $fd) {
        if ((int)$fd['perfume_product_id'] === $perfumeId
            && ($bottleSizeMl === null || (int) $fd['bottle_size_ml'] === $bottleSizeMl)
        ) {
            $result['defaultQty'] = (float) $fd['default_grams'];
            break;
        }
    }

    if ($result['defaultQty'] > 0 && abs($qty - $result['defaultQty']) > 0.01) {
        $result['isModified'] = true;
    }

    return $result;
}

function invoice_line_bottle_size(array $components): ?int
{
    foreach ($components as $c) {
        if (!empty($c['size_ml'])) {
            return (int) $c['size_ml'];
        }
    }
    return null;
}

function clean_print_description(string $desc): string
{
    // إزالة البادئة مثل "تركيبة فورية:" أو "تركيبة جاهزة:"
    $desc = preg_replace('/^(?:🧪|📜)?\s*(?:تركيبة فورية|تركيبة جاهزة|saved_recipe|custom_recipe):\s*/ui', '', $desc);
    
    // إزالة الجرامات مثل " 15جم" أو " 15.5جم"
    $desc = preg_replace('/\s+\d+(?:\.\d+)?\s*(?:جم|جم\.| g|gm|grams)\b/ui', '', $desc);
    
    return trim($desc);
}

// تنظيف التحذيرات (الأساسي) من الوصف للعرض الشاشة والطباعة
function strip_warning_annotations(string $desc): string
{
    // إزالة "(الأساسي Xjm)" - تحذير الجرامات
    $desc = preg_replace('/\s*\(الأساسي[^)]*\)/u', '', $desc);
    // إزالة "[سعر: X ج.م ، الأساسي: Y ج.م]" - تحذير السعر
    $desc = preg_replace('/\s*\[سعر:[^\]]*\]/u', '', $desc);
    return trim($desc);
}

$typeTranslations = [
    'product' => 'جاهز',
    'custom_recipe' => 'تركيبة فورية',
    'saved_recipe' => 'تركيبة جاهزة',
    'offer' => 'عرض / باكدج 🎁'
];
$paymentTranslations = [
    'cash' => 'كاش',
    'instapay' => 'انستا باي',
    'vodafone_cash' => 'فودافون كاش',
    'invoice_payment' => 'دفع فاتورة',
    'debt_payment' => 'سداد دين'
];
?>
<div class="screen-only">
    <section class="page-head print-hide">
        <div>
            <h2>تفاصيل الفاتورة <?= e($invoice['invoice_number']) ?></h2>
            <p>عرض كامل للبنود والمكونات والمدفوعات.</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a class="btn" href="index.php?r=pos">العودة لشاشة الكاشير</a>
            <button type="button" class="btn" style="background:#25D366; color:#fff; font-weight:bold;" onclick="sendInvoiceWhatsApp(<?= (int)$invoice['id'] ?>)">إرسال واتساب 💬</button>
            <button class="btn primary" onclick="openCashDrawer(); window.print();">طباعة الفاتورة 🖨️</button>
        </div>
    </section>

    <section class="invoice-paper">
        <header class="invoice-header">
            <div><h2><?= e(APP_NAME) ?></h2><p>فاتورة بيع</p></div>
            <div><strong><?= e($invoice['invoice_number']) ?></strong><span><?= e(format_datetime($invoice['created_at'])) ?></span></div>
        </header>
        <section class="detail-grid">
            <div><span>الفرع</span><strong><?= e(str_replace(["فرع 1","فرع 2"], ["ام خنان","المنوات"], $invoice['location_name'])) ?></strong></div>
            <div><span>الموظف</span><strong><?= e($invoice['user_name']) ?></strong></div>
            <div><span>العميل</span><strong><?= e($invoice['customer_name'] ?: 'زبون عابر') ?></strong></div>
            <div><span>الهاتف</span><strong><?= e($invoice['customer_phone'] ?: '-') ?></strong></div>
        </section>
        <table>
            <thead><tr><th>الوصف</th><th>النوع</th><th>الكمية</th><th>السعر</th><th>خصم</th><th>الإجمالي</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $line): ?>
                <?php $components = invoice_components_rows((int) $line['id']); $bottleSize = invoice_line_bottle_size($components); ?>
                <tr>
                    <td><?= e(strip_warning_annotations($line['description'])) ?></td>
                    <td><span class="badge"><?= e($typeTranslations[$line['line_type']] ?? $line['line_type']) ?></span></td>
                    <td><?php
                        if ($line['line_type'] === 'custom_recipe') {
                            $hasBottle = array_filter($components, fn($c) => $c['unit'] === 'unit' || !empty($c['size_ml']));
                            if (!$hasBottle) {
                                // بدون زجاجة: اعرض إجمالي الجرامات
                                $totalGrams = array_sum(array_map(fn($c) => $c['unit'] === 'gram' ? (float)$c['quantity'] : 0, $components));
                                echo e(qty($totalGrams) . 'جم');
                            } else {
                                // مع زجاجة: اعرض عدد التركيبات
                                echo e(qty($line['quantity']));
                            }
                        } else {
                            echo e(qty($line['quantity']));
                        }
                    ?></td>
                    <td><?= money($line['unit_price']) ?></td>
                    <td><?= money($line['discount_amount']) ?></td>
                    <td><?= money($line['line_total']) ?></td>
                </tr>
                <?php if ($components): ?>
                    <tr class="sub-row"><td colspan="6">
                        <?php foreach ($components as $c): ?>
                            <span class="chip">
                                <?= e($c['product_name']) ?><?= $c['size_ml'] ? ' (' . (int)$c['size_ml'] . 'ml)' : '' ?>
                            </span>
                        <?php endforeach; ?>
                    </td></tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        <section class="totals-box">
            <div><span>قبل الخصم</span><strong><?= money($invoice['subtotal']) ?></strong></div>
            <div><span>خصم الفاتورة</span><strong><?= money($invoice['discount_amount']) ?></strong></div>
            <div><span>الإجمالي</span><strong><?= money($invoice['total']) ?></strong></div>
            <div><span>المدفوع</span><strong><?= money($invoice['paid_total']) ?></strong></div>
            <div><span>المتبقي</span><strong><?= money($invoice['due_total']) ?></strong></div>
        </section>
        <h3>المدفوعات</h3>
        <table><thead><tr><th>الطريقة</th><th>وجهة الدفع</th><th>المبلغ</th><th>النوع</th><th>المسؤول</th><th>التاريخ</th></tr></thead><tbody><?php foreach ($payments as $p): ?><tr><td><?= e($paymentTranslations[$p['method']] ?? $p['method']) ?></td><td><?= e(payment_destination_label((string) $p['method']) ?: '-') ?></td><td><?= money($p['amount']) ?></td><td><?= e($paymentTranslations[$p['payment_type']] ?? $p['payment_type']) ?></td><td><?= e($p['user_name']) ?></td><td><?= e(format_datetime($p['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table>
        <?php if ($invoice['notes']): ?><p class="invoice-note"><strong>ملاحظة:</strong> <?= e($invoice['notes']) ?></p><?php endif; ?>
    </section>
</div>

<div class="print-only receipt-container">
    <div class="receipt-header">
        <div class="receipt-logo"><img src="<?= e(APP_BASE) ?>/assets/logo.png" alt="logo" /></div>
        <div><h2><?= e(APP_NAME) ?></h2><p>الفرع: <?= e(str_replace(["فرع 1","فرع 2"], ["ام خنان","المنوات"], $invoice['location_name'])) ?></p><p>التاريخ: <?= e(format_datetime($invoice['created_at'])) ?></p></div>
    </div>
    <div class="receipt-meta">
        <div><strong>رقم الفاتورة:</strong> <span><?= e($invoice['invoice_number']) ?></span></div>
        <div><strong>العميل:</strong> <span><?= e($invoice['customer_name'] ?: 'زبون عابر') ?></span></div>
        <?php if ($invoice['customer_phone']): ?><div><strong>الهاتف:</strong> <span><?= e($invoice['customer_phone']) ?></span></div><?php endif; ?>
    </div>
    <div class="receipt-divider"></div>
    <div class="receipt-items">
        <?php foreach ($lines as $line): ?>
            <?php $components = invoice_components_rows((int) $line['id']); $bottleSize = invoice_line_bottle_size($components); ?>
            <div class="receipt-item">
                <div class="item-name"><?= e(strip_warning_annotations(clean_print_description($line['description']))) ?></div>
                <div class="item-calc">
                    <span><?php
                        if ($line['line_type'] === 'custom_recipe') {
                            $hasBottle = array_filter($components, fn($c) => $c['unit'] === 'unit' || !empty($c['size_ml']));
                            if (!$hasBottle) {
                                $totalGrams = array_sum(array_map(fn($c) => $c['unit'] === 'gram' ? (float)$c['quantity'] : 0, $components));
                                echo e(qty($totalGrams) . 'جم');
                            } else {
                                echo e(qty($line['quantity']));
                            }
                        } else {
                            echo e(qty($line['quantity']));
                        }
                    ?> × <?= e(money($line['unit_price'])) ?></span>
                    <strong><?= e(money($line['line_total'])) ?></strong>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="receipt-divider"></div>
    <div class="receipt-summary">
        <div><span>إجمالي الأصناف:</span> <strong><?= money($invoice['subtotal']) ?></strong></div>
        <?php if ((float)$invoice['discount_amount'] > 0): ?><div><span>الخصم:</span> <strong><?= money($invoice['discount_amount']) ?></strong></div><?php endif; ?>
        <div class="receipt-total"><span>المطلوب:</span> <strong><?= money($invoice['total']) ?></strong></div>
        <div><span>المدفوع:</span> <strong><?= money($invoice['paid_total']) ?></strong></div>
        <?php if ((float)$invoice['due_total'] > 0): ?><div class="receipt-due"><span>المتبقي (دين):</span> <strong><?= money($invoice['due_total']) ?></strong></div><?php endif; ?>
        
        <!-- Payment Methods Loop -->
        <?php if (!empty($payments)): ?>
        <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ccc; font-size: 11px;">
            <strong style="display:block; margin-bottom: 4px;">طريقة الدفع:</strong>
            <?php foreach ($payments as $p): ?>
                <div style="display: flex; justify-content: space-between;">
                    <span><?= e($paymentTranslations[$p['method']] ?? $p['method']) ?></span>
                    <strong><?= money($p['amount']) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="receipt-divider"></div>
    <div class="receipt-barcode"><code><?= e($invoice['invoice_number']) ?></code></div>
    <div class="receipt-footer">
        <p>شكراً لزيارتكم! نتشرف بلقائكم دائماً</p>
        <div class="receipt-addresses">
            <div class="receipt-address-item">
                <span class="addr-icon">📍</span>
                <span>المنوات · جيزة · شارع المدرسة الثانوية · بجوار كوبري بهجات</span>
            </div>
            <div class="receipt-address-item">
                <span class="addr-icon">📍</span>
                <span>أم خنان · جيزة · شارع الوحدة المحلية · بميدان عزت عاشور</span>
            </div>
        </div>
    </div>
    <div class="receipt-print-spacer"></div>
</div>

<script>
function openCashDrawer() {
    try {
        window.dispatchEvent(new Event('cash-drawer-open'));
    } catch (e) {}
}

function sendInvoiceWhatsApp(id) {
    if (!confirm('هل تريد إرسال تفاصيل هذه الفاتورة إلى واتساب العميل الآن؟')) return;
    const btn = event.target;
    const oldText = btn.innerHTML;
    btn.innerHTML = 'جاري الإرسال ⏳...';
    btn.disabled = true;

    fetch('index.php?r=send_invoice_whatsapp&id=' + id)
        .then(res => res.json())
        .then(data => {
            btn.innerHTML = oldText;
            btn.disabled = false;
            if (data.success) {
                alert('✅ ' + (data.message || 'تم إرسال الفاتورة بنجاح عبر الواتساب!'));
            } else {
                alert('❌ ' + (data.error || 'تعذر إرسال الفاتورة عبر الواتساب'));
            }
        })
        .catch(err => {
            btn.innerHTML = oldText;
            btn.disabled = false;
            alert('❌ فشل الاتصال بخدمة الواتساب: ' + err.message);
        });
}

<?php if (isset($_GET['print']) && $_GET['print'] === '1'): ?>
let printTriggered = false;
function triggerPrint() {
    if (printTriggered) return;
    printTriggered = true;
    sessionStorage.removeItem('pos_invoice_submit_pending');
    openCashDrawer();
    if (window.self !== window.top) {
        window.focus();
    }
    setTimeout(function() { window.print(); }, 150);
}
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(triggerPrint, 500); // 500ms max wait
});
window.addEventListener('load', triggerPrint); // Or whenever resources are ready
<?php endif; ?>
</script>
