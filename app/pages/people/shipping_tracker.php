<?php

declare(strict_types=1);

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$govFilter    = trim((string)($_GET['governorate'] ?? ''));
$searchQuery  = trim((string)($_GET['search'] ?? ''));
$dateFrom     = trim((string)($_GET['date_from'] ?? ''));
$dateTo       = trim((string)($_GET['date_to'] ?? ''));

$filters = [
    'status'      => $statusFilter,
    'governorate' => $govFilter,
    'search'      => $searchQuery,
    'date_from'   => $dateFrom,
    'date_to'     => $dateTo,
];

$orders = all_shipping_orders($filters, 150);
$governorates = egypt_governorates();
$statusMap = shipping_status_labels();

// Counters
$counts = [
    'total'     => 0,
    'preparing' => 0,
    'shipped'   => 0,
    'delivered' => 0,
    'returned'  => 0,
    'cancelled' => 0,
];
$allOrdersForCounts = all_shipping_orders([], 1000);
foreach ($allOrdersForCounts as $o) {
    $counts['total']++;
    $st = $o['shipping_status'] ?? 'preparing';
    if (isset($counts[$st])) {
        $counts[$st]++;
    }
}
?>

<div class="page-head" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div>
            <h2 style="font-weight: 800; font-size: 22px; margin-bottom: 4px;">📦 تتبع شحنات الأونلاين ومسح الباركود</h2>
            <p style="color: var(--muted); margin: 0;">مسح وتحديث حالات أوردرات الشحن وبوليصات الاستيكر وطباعتها بسهولة.</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="index.php?r=online_pos" class="btn primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>🛵</span> كاشير أونلاين جديد
            </a>
        </div>
    </div>
</div>

<!-- Quick Statistics Bar -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
    <a href="index.php?r=shipping_tracker" class="card" style="padding: 14px; text-decoration: none; color: inherit; text-align: center; border-right: 4px solid var(--primary, #c9a84c);">
        <div style="font-size: 11.5px; color: var(--muted); font-weight: bold;">إجمالي الشحنات</div>
        <div style="font-size: 22px; font-weight: 900; margin-top: 4px;"><?= $counts['total'] ?></div>
    </a>
    <a href="index.php?r=shipping_tracker&status=preparing" class="card" style="padding: 14px; text-decoration: none; color: inherit; text-align: center; border-right: 4px solid #f59e0b;">
        <div style="font-size: 11.5px; color: #f59e0b; font-weight: bold;">⏳ قيد التجهيز</div>
        <div style="font-size: 22px; font-weight: 900; margin-top: 4px;"><?= $counts['preparing'] ?></div>
    </a>
    <a href="index.php?r=shipping_tracker&status=shipped" class="card" style="padding: 14px; text-decoration: none; color: inherit; text-align: center; border-right: 4px solid #0284c7;">
        <div style="font-size: 11.5px; color: #0284c7; font-weight: bold;">🚚 مع المندوب / شُحنت</div>
        <div style="font-size: 22px; font-weight: 900; margin-top: 4px;"><?= $counts['shipped'] ?></div>
    </a>
    <a href="index.php?r=shipping_tracker&status=delivered" class="card" style="padding: 14px; text-decoration: none; color: inherit; text-align: center; border-right: 4px solid #10b981;">
        <div style="font-size: 11.5px; color: #10b981; font-weight: bold;">✅ تم التسليم بنجاح</div>
        <div style="font-size: 22px; font-weight: 900; margin-top: 4px;"><?= $counts['delivered'] ?></div>
    </a>
    <a href="index.php?r=shipping_tracker&status=returned" class="card" style="padding: 14px; text-decoration: none; color: inherit; text-align: center; border-right: 4px solid #ef4444;">
        <div style="font-size: 11.5px; color: #ef4444; font-weight: bold;">❌ المرتجعات</div>
        <div style="font-size: 22px; font-weight: 900; margin-top: 4px;"><?= $counts['returned'] ?></div>
    </a>
</div>

<!-- ========================================================================= -->
<!--                   قسم مسح الباركود السريع (Fast Scanner)                   -->
<!-- ========================================================================= -->
<div class="card" style="margin-bottom: 24px; border: 2px solid var(--primary, #c9a84c); padding: 20px; background: #fff;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 24px;">🏷️</span>
            <div>
                <h3 style="margin: 0; font-size: 17px; font-weight: 800;">سكانر مسح الباركود الفوري</h3>
                <small style="color: var(--muted);">قم بمسح الباركود من بوليصة الشحن أو كتابة رقم الفاتورة / الهاتف واضغط Enter</small>
            </div>
        </div>
        <span id="scan-feedback-badge" class="badge" style="display:none; font-size: 12px; padding: 6px 12px;"></span>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
        <div style="position: relative; flex-grow: 1;">
            <input type="text" id="barcode-scan-input" 
                   placeholder="امسح باركود البوليصة هنا بالجهاز أو اكتب رقم الأوردر / الهاتف..." 
                   autocomplete="off" autofocus
                   style="width: 100%; height: 50px; font-size: 16px; font-weight: bold; padding: 0 45px 0 16px; border: 2px solid #cbd5e1; border-radius: 8px;">
            <span style="position: absolute; right: 14px; top: 14px; font-size: 20px; opacity: 0.6;">🔍</span>
        </div>
        <button type="button" class="btn primary" id="btn-manual-scan" style="height: 50px; padding: 0 24px; font-weight: 800; font-size: 15px;">
            بحث بالباركود
        </button>
    </div>

    <!-- Active Scanned Order Card (Hidden by default, shown on scan) -->
    <div id="scanned-order-container" style="display: none; margin-top: 20px; border-top: 1.5px dashed #cbd5e1; padding-top: 18px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px; background: #f8fafc; padding: 18px; border-radius: 10px; border: 1px solid #e2e8f0;">
            
            <!-- Order & Customer Details -->
            <div style="flex: 1 1 350px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <span id="scanned-status-badge" class="badge" style="font-size: 13px; font-weight: 800; padding: 6px 14px;"></span>
                    <h3 id="scanned-inv-number" style="margin: 0; font-size: 20px; font-family: monospace; font-weight: 900; color: #0f172a;"></h3>
                </div>

                <div style="font-size: 16px; font-weight: 800; color: #1e293b; margin-bottom: 6px;" id="scanned-customer-name"></div>

                <div style="display: flex; gap: 12px; font-size: 14px; font-weight: 700; margin-bottom: 8px;">
                    <span id="scanned-phone1" style="background:#fff; border:1px solid #cbd5e1; padding:3px 8px; border-radius:4px; font-family:monospace;"></span>
                    <span id="scanned-phone2" style="background:#fff; border:1px solid #cbd5e1; padding:3px 8px; border-radius:4px; font-family:monospace;"></span>
                </div>

                <div style="font-size: 13.5px; color: #334155; margin-bottom: 8px; line-height: 1.4;">
                    <span id="scanned-gov-city" class="badge muted" style="margin-left: 6px;"></span>
                    <span id="scanned-address"></span>
                </div>

                <div style="display: flex; gap: 15px; margin-top: 12px; font-size: 14px; font-weight: 800;">
                    <div>المطلوب تحصيله (COD): <span id="scanned-cod-amount" style="color: #059669; font-size: 17px;"></span></div>
                    <div>إجمالي الأوردر: <span id="scanned-total-amount"></span></div>
                </div>
            </div>

            <!-- Status Changer & Action Controls -->
            <div style="flex: 1 1 300px; background: #fff; padding: 16px; border-radius: 8px; border: 1.5px solid #cbd5e1;">
                <h4 style="margin: 0 0 10px 0; font-size: 14.5px; font-weight: 800;">تحديث حالة الشحن:</h4>
                
                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 4px;">الحالة الجديدة:</label>
                    <select id="scanned-status-select" style="width: 100%; height: 42px; font-size: 14px; font-weight: bold; border-radius: 6px;">
                        <option value="preparing">⏳ قيد التجهيز</option>
                        <option value="shipped">🚚 تم الشحن مع المندوب</option>
                        <option value="delivered">✅ تم التسليم بنجاح</option>
                        <option value="returned">❌ مرتجع</option>
                        <option value="cancelled">🚫 ملغي</option>
                    </select>
                </div>

                <div style="margin-bottom: 12px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 4px;">ملاحظات المندوب / التوصيل:</label>
                    <textarea id="scanned-notes-input" rows="2" placeholder="أية ملاحظات جديدة على الشحنة..." style="width: 100%; font-size: 12.5px; border-radius: 6px; padding: 6px;"></textarea>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn primary" id="btn-update-scanned-status" style="flex: 1; font-weight: 800;">
                        💾 حفظ وتحديث الحالة
                    </button>
                    <a id="btn-print-scanned-waybill" href="#" target="_blank" class="btn secondary" style="display: inline-flex; align-items: center; gap: 4px;">
                        <span>🏷️</span> طباعة البوليصة
                    </a>
                </div>
            </div>

        </div>

        <!-- Scanned Order Lines Preview -->
        <div style="margin-top: 14px; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 12px;">
            <div style="font-weight: 800; font-size: 13px; margin-bottom: 8px;">🛍️ تفاصيل محتويات الشحنة:</div>
            <div id="scanned-items-list" style="display: flex; flex-wrap: wrap; gap: 8px;"></div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!--                         جدول شحنات وأوردرات الأونلاين                     -->
<!-- ========================================================================= -->
<div class="card" style="padding: 20px; background: #fff;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <h3 style="margin: 0; font-size: 17px; font-weight: 800;">📋 سجل بوليصات الشحن (<?= count($orders) ?>)</h3>
        
        <!-- Filter Form -->
        <form method="get" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <input type="hidden" name="r" value="shipping_tracker">
            
            <input type="text" name="search" value="<?= e($searchQuery) ?>" placeholder="بحث برقم، عميل، هاتف..." 
                   style="height: 38px; width: 170px; font-size: 13px; border-radius: 6px;">

            <select name="status" style="height: 38px; font-size: 13px; border-radius: 6px;">
                <option value="all">كل الحالات</option>
                <?php foreach ($statusMap as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e($v['label']) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="governorate" style="height: 38px; font-size: 13px; border-radius: 6px;">
                <option value="">كل المحافظات</option>
                <?php foreach ($governorates as $g): ?>
                    <option value="<?= e($g) ?>" <?= $govFilter === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn secondary" style="height: 38px;">تصفية</button>
            <?php if ($searchQuery || $statusFilter !== 'all' || $govFilter): ?>
                <a href="index.php?r=shipping_tracker" class="btn muted" style="height: 38px; display: inline-flex; align-items: center;">إلغاء</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($orders)): ?>
        <div style="text-align: center; padding: 40px; color: var(--muted);">
            <div style="font-size: 40px; margin-bottom: 10px;">📦📭</div>
            <p>لا توجد شحنات مسجلة مطابقة للبحث أو الفلتر المختار.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" style="width: 100%; font-size: 13px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th>رقم الفاتورة / الباركود</th>
                        <th>المستلم / العميل</th>
                        <th>الهواتف</th>
                        <th>المحافظة والمنطقة</th>
                        <th>المطلوب تحصيله (COD)</th>
                        <th>حالة الشحنة</th>
                        <th>التاريخ</th>
                        <th style="text-align: center;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $ord): 
                        $st = $ord['shipping_status'] ?? 'preparing';
                        $badgeInfo = $statusMap[$st] ?? ['label' => $st, 'badge' => 'muted'];
                        $cod = isset($ord['cod_amount']) ? (float)$ord['cod_amount'] : (float)$ord['due_total'];
                    ?>
                    <tr id="row-order-<?= e($ord['id']) ?>">
                        <td>
                            <strong style="font-family: monospace; font-size: 14px;"><?= e($ord['invoice_number']) ?></strong>
                            <?php if (!empty($ord['tracking_number'])): ?>
                                <br><small style="color: var(--muted);"><?= e($ord['tracking_number']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= e($ord['recipient_name'] ?: ($ord['customer_name'] ?? 'عميل')) ?></strong>
                        </td>
                        <td style="font-family: monospace; direction: ltr; text-align: right;">
                            <div>📱 <?= e($ord['phone'] ?: ($ord['customer_phone'] ?? '-')) ?></div>
                            <?php if (!empty($ord['phone2'])): ?>
                                <div style="color: #64748b; font-size: 11.5px;">📞 بديل: <?= e($ord['phone2']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge muted" style="font-size: 11px;"><?= e($ord['governorate'] ?: 'أونلاين') ?></span>
                            <?php if (!empty($ord['city'])): ?>
                                <span style="font-weight: 600; font-size: 12px;"><?= e($ord['city']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($ord['street_address'])): ?>
                                <div style="color: #64748b; font-size: 11px; max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($ord['street_address']) ?>">
                                    <?= e($ord['street_address']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($cod <= 0.001): ?>
                                <span class="badge success" style="font-size: 11px;">مدفوع بالكامل</span>
                            <?php else: ?>
                                <strong style="color: #059669; font-size: 14px;"><?= number_format($cod, 2) ?></strong> ج.م
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= e($badgeInfo['badge']) ?>" style="font-size: 11.5px; font-weight: 700;" id="badge-status-<?= e($ord['id']) ?>">
                                <?= e($badgeInfo['label']) ?>
                            </span>
                        </td>
                        <td style="font-size: 11.5px; color: #64748b;">
                            <?= date('d/m/Y h:i A', strtotime($ord['created_at'])) ?>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <button type="button" class="btn small primary" onclick="quickScanOrder('<?= e($ord['invoice_number']) ?>')" title="مسح وتحديث">
                                🔍 فحص
                            </button>
                            <a href="index.php?r=shipping_waybill&id=<?= e($ord['id']) ?>&print=1" target="_blank" class="btn small secondary" title="طباعة بوليصة الشحن (استيكر)">
                                🏷️ بوليصة
                            </a>
                            <a href="index.php?r=invoice_view&id=<?= e($ord['id']) ?>" target="_blank" class="btn small muted" title="عرض فاتورة الكاشير">
                                🧾
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
let currentScannedInvoiceId = null;

const scanInput = document.getElementById('barcode-scan-input');
const btnScan = document.getElementById('btn-manual-scan');
const feedbackBadge = document.getElementById('scan-feedback-badge');
const container = document.getElementById('scanned-order-container');

// Sound cues via Web Audio API (clean, offline, zero assets)
function playBeep(success = true) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        if (success) {
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.setValueAtTime(1200, ctx.currentTime + 0.08);
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.18);
        } else {
            osc.frequency.setValueAtTime(300, ctx.currentTime);
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            osc.start();
            osc.stop(ctx.currentTime + 0.3);
        }
    } catch(e) {}
}

async function doBarcodeLookup(query) {
    const q = String(query || '').trim();
    if (!q) return;

    feedbackBadge.style.display = 'inline-block';
    feedbackBadge.className = 'badge info';
    feedbackBadge.textContent = 'جاري البحث...';

    try {
        const res = await fetch('index.php?r=api_shipping_scan&barcode=' + encodeURIComponent(q));
        const data = await res.json();

        if (data.success && data.order) {
            playBeep(true);
            feedbackBadge.className = 'badge success';
            feedbackBadge.textContent = '✓ تم العثور على الأوردر';
            renderScannedOrder(data.order);
            scanInput.value = '';
            scanInput.focus();
        } else {
            playBeep(false);
            feedbackBadge.className = 'badge danger';
            feedbackBadge.textContent = '✕ ' + (data.message || 'لم يتم العثور على الأوردر');
            scanInput.select();
        }
    } catch (e) {
        playBeep(false);
        feedbackBadge.className = 'badge danger';
        feedbackBadge.textContent = '✕ خطأ في الاتصال بالسيرفر';
    }
}

function renderScannedOrder(ord) {
    currentScannedInvoiceId = ord.id;
    container.style.display = 'block';

    document.getElementById('scanned-inv-number').textContent = ord.invoice_number;
    document.getElementById('scanned-customer-name').textContent = ord.recipient_name || ord.customer_name || 'عميل';
    document.getElementById('scanned-phone1').textContent = '📱 ' + (ord.shipping_phone || ord.customer_phone || '-');

    const phone2Elem = document.getElementById('scanned-phone2');
    if (ord.shipping_phone2) {
        phone2Elem.style.display = 'inline-block';
        phone2Elem.textContent = '📞 بديل: ' + ord.shipping_phone2;
    } else {
        phone2Elem.style.display = 'none';
    }

    document.getElementById('scanned-gov-city').textContent = (ord.shipping_governorate || 'أونلاين') + (ord.shipping_city ? ' - ' + ord.shipping_city : '');
    document.getElementById('scanned-address').textContent = ord.shipping_address || 'العنوان مسجل هاتفياً';

    const cod = parseFloat(ord.cod_amount !== undefined ? ord.cod_amount : ord.due_total);
    const codElem = document.getElementById('scanned-cod-amount');
    if (cod <= 0.001) {
        codElem.innerHTML = '<span class="badge success">مدفوع بالكامل (0.00 ج.م)</span>';
    } else {
        codElem.textContent = cod.toFixed(2) + ' ج.م';
    }

    document.getElementById('scanned-total-amount').textContent = parseFloat(ord.total).toFixed(2) + ' ج.م';

    // Status Select
    const stSelect = document.getElementById('scanned-status-select');
    stSelect.value = ord.shipping_status || 'preparing';

    const stBadge = document.getElementById('scanned-status-badge');
    updateStatusBadgeVisual(stBadge, ord.shipping_status || 'preparing');

    // Notes
    document.getElementById('scanned-notes-input').value = ord.shipping_notes || '';

    // Waybill Print Link
    document.getElementById('btn-print-scanned-waybill').href = 'index.php?r=shipping_waybill&id=' + ord.id + '&print=1';

    // Items list
    const itemsList = document.getElementById('scanned-items-list');
    itemsList.innerHTML = '';
    if (ord.lines && ord.lines.length) {
        ord.lines.forEach(line => {
            const tag = document.createElement('span');
            tag.className = 'badge muted';
            tag.style.fontSize = '12px';
            tag.style.padding = '4px 8px';
            tag.innerHTML = `<strong>${line.description || line.product_name}</strong> × ${parseFloat(line.quantity)}`;
            itemsList.appendChild(tag);
        });
    } else {
        itemsList.innerHTML = '<span style="color:#64748b; font-size:12px;">لم يتم تسجيل أصناف مفصلة</span>';
    }

    // Scroll smoothly to scanned order card
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function updateStatusBadgeVisual(elem, status) {
    const map = {
        'preparing': { label: '⏳ قيد التجهيز', cls: 'warning' },
        'shipped':   { label: '🚚 تم الشحن مع المندوب', cls: 'info' },
        'delivered': { label: '✅ تم التسليم بنجاح', cls: 'success' },
        'returned':  { label: '❌ مرتجع', cls: 'danger' },
        'cancelled': { label: '🚫 ملغي', cls: 'muted' },
    };
    const s = map[status] || { label: status, cls: 'muted' };
    elem.className = 'badge ' + s.cls;
    elem.textContent = s.label;
}

// Quick trigger from table
function quickScanOrder(invNum) {
    scanInput.value = invNum;
    doBarcodeLookup(invNum);
}

// Event Listeners for scanning
scanInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        doBarcodeLookup(scanInput.value);
    }
});

btnScan.addEventListener('click', () => {
    doBarcodeLookup(scanInput.value);
});

// Update Status Button
document.getElementById('btn-update-scanned-status').addEventListener('click', async () => {
    if (!currentScannedInvoiceId) return;

    const newStatus = document.getElementById('scanned-status-select').value;
    const newNotes = document.getElementById('scanned-notes-input').value.trim();

    try {
        const res = await fetch('index.php?r=api_shipping_update_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                invoice_id: currentScannedInvoiceId,
                status: newStatus,
                notes: newNotes
            })
        });
        const data = await res.json();

        if (data.success) {
            playBeep(true);
            alert('✓ تم حفظ وتحديث حالة الأوردر بنجاح');
            updateStatusBadgeVisual(document.getElementById('scanned-status-badge'), newStatus);
            
            // Update row in table if visible
            const tableBadge = document.getElementById('badge-status-<?= "'+currentScannedInvoiceId+'" ?>');
            if (tableBadge) {
                updateStatusBadgeVisual(tableBadge, newStatus);
            }
        } else {
            playBeep(false);
            alert('✕ ' + (data.message || 'حدث خطأ أثناء التحديث'));
        }
    } catch(e) {
        playBeep(false);
        alert('✕ تعذر الاتصال بالسيرفر');
    }
});
</script>

