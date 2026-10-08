<?php
$user = require_login();
$locations = stock_locations();

// Determine target location:
$userLocationId = current_user_location_id();
$selectedLocationId = $_GET['location_id'] ?? null;
$showAllLocations = false;

if ($userLocationId !== null) {
    $locationId = $userLocationId;
} elseif ($selectedLocationId === 'all') {
    $locationId = null;
    $showAllLocations = true;
} else {
    $locationId = (int) $selectedLocationId ?: ($locations[0]['id'] ?? 0);
}

// Find location name when a specific location is selected
$locationName = 'غير محدد';
if ($locationId !== null) {
    foreach ($locations as $l) {
        if ((int)$l['id'] === $locationId) {
            $locationName = $l['name'];
            break;
        }
    }
} else {
    $locationName = 'كل المواقع';
}

// Get rows for the chosen location or all locations
$rows = array_filter(inventory_rows($locationId), fn($r) => (float)$r['quantity'] > 0);

// Add barcode to each row via a separate batched lookup
$barcodeMap = [];
$productIds = array_unique(array_column(array_values($rows), 'product_id'));
if ($productIds) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = pdo()->prepare("SELECT id, barcode FROM products WHERE id IN ($placeholders)");
    $stmt->execute($productIds);
    foreach ($stmt->fetchAll() as $pb) {
        $barcodeMap[(int)$pb['id']] = (string)($pb['barcode'] ?? '');
    }
}

// Stats
$totalItems = count($rows);
$lowStockCount = count(array_filter($rows, fn($r) => (float)$r['min_stock'] > 0 && (float)$r['quantity'] <= (float)$r['min_stock']));

$typeTranslations = [
    'bottle' => 'زجاجة',
    'perfume_gram' => 'عطر بالجرام',
    'recipe' => 'تركيبة',
    'fixed' => 'منتج جاهز'
];

$treasuryBalances = branch_treasury_balances($locationId);
?>

<style>
/* Custom enhancements for Branch Inventory and Treasury */
.branch-dashboard {
    display: grid;
    gap: 14px;
    margin-top: 5px;
}

/* Section Titles */
.section-title {
    font-size: 13.5px;
    font-weight: 800;
    color: var(--primary);
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    margin-bottom: 4px;
}

/* Treasury Cards Design */
.treasury-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 12px;
    margin-bottom: 5px;
}

.treasury-card {
    background: var(--surface);
    border: 1.5px solid var(--line);
    border-radius: 12px;
    padding: 14px 16px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.01);
}

.treasury-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -8px rgba(0, 0, 0, 0.05);
    border-color: var(--line-active);
}

.treasury-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 4px;
    height: 100%;
}

.treasury-card.cash::before { background: var(--success); }
.treasury-card.instapay::before { background: var(--primary); }
.treasury-card.vodafone::before { background: var(--danger); }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.card-title {
    font-size: 11px;
    font-weight: 700;
    color: var(--muted);
}

.card-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.treasury-card.cash .card-icon { background: rgba(16, 185, 129, 0.08); color: var(--success); }
.treasury-card.instapay .card-icon { background: rgba(124, 58, 237, 0.08); color: var(--primary); }
.treasury-card.vodafone .card-icon { background: rgba(239, 68, 68, 0.08); color: var(--danger); }

.card-amount-wrapper {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

/* Stats Cards (المربعات الأصلية) */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 12px;
    margin-bottom: 5px;
}

.stat-card {
    background: var(--surface);
    border: 1.5px solid var(--line);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.stat-icon {
    font-size: 26px;
    line-height: 1;
}

.stat-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.stat-label {
    font-size: 11.5px;
    font-weight: 700;
    color: var(--muted);
}

.stat-value {
    font-size: 22px;
    font-weight: 900;
    color: var(--ink);
}

.badge-status {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.badge-status.available { background: rgba(16, 185, 129, 0.08); color: var(--success); }
.badge-status.low { background: rgba(239, 68, 68, 0.08); color: var(--danger); }

/* Toolbar & Filters */
.dashboard-toolbar {
    background: var(--surface);
    border: 1.5px solid var(--line);
    border-radius: 16px;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    box-shadow: 0 2px 4px rgba(0,0,0,0.01);
}

.search-wrapper {
    position: relative;
    flex: 1;
    min-width: 240px;
}

.search-wrapper input {
    width: 100%;
    padding: 10px 38px 10px 14px !important;
    font-size: 13px !important;
    border: 1.5px solid var(--line) !important;
    border-radius: 10px !important;
    background: var(--surface-soft) !important;
    color: var(--ink) !important;
    outline: none;
    transition: all 0.2s ease;
}

.search-wrapper input:focus {
    border-color: var(--primary) !important;
    background: var(--surface) !important;
    box-shadow: 0 0 0 3px rgba(185, 132, 24, 0.1) !important;
}

.search-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--muted);
    font-size: 14px;
    pointer-events: none;
}

.filter-select {
    padding: 9px 12px !important;
    border-radius: 10px !important;
    border: 1.5px solid var(--line) !important;
    font-size: 13px !important;
    font-weight: 600;
    color: var(--ink) !important;
    background: var(--surface) !important;
    cursor: pointer;
    outline: none;
}
</style>

<div class="branch-dashboard">
    <section class="page-head" style="margin: 0; padding-bottom: 10px;">
        <div>
            <h2>حالة الفرع - المخزون والخزائن المالية</h2>
            <p>متابعة رصيد الأصناف والنقدية المتوفرة للتحويل وحركة إنستا باي وفودافون كاش اليومية لـ: <strong><?= e($locationName) ?></strong></p>
        </div>
    </section>

    <!-- Financial Treasury Section -->
    <div>
        <h3 class="section-title">النقدية المتوفرة وحركة الدفع اليومية بالفرع <a class="btn small" href="?r=branch_cash_transfers&view=add<?= $locationId !== null ? '&location_id=' . e($locationId) : '' ?>&amount=<?= (float)($treasuryBalances['cash']['balance'] ?? 0) ?>">تحويل للمدير</a></h3>
        <div class="treasury-grid">
            <div class="treasury-card cash">
                <div class="card-header">
                    <span class="card-title">النقدية المتوفرة للتحويل (كاش)</span>
                    <div class="card-icon">كاش</div>
                </div>
                <div class="card-amount-wrapper">
                    <strong class="amount-main"><?= money($treasuryBalances['cash']['balance'] ?? 0) ?></strong>
                    <div class="amount-divider"></div>
                    <div class="amount-sub">
                        <span>وارد اليوم:</span>
                        <strong><?= money($treasuryBalances['cash']['today_in'] ?? 0) ?></strong>
                        <span>مصاريف/تحويلات:</span>
                        <strong><?= money($treasuryBalances['cash']['out'] ?? 0) ?></strong>
                    </div>
                </div>
            </div>

            <div class="treasury-card instapay">
                <div class="card-header">
                    <span class="card-title">حركة اليوم إنستا باي (InstaPay)</span>
                    <div class="card-icon">IP</div>
                </div>
                <div class="card-amount-wrapper">
                    <strong class="amount-main"><?= money($treasuryBalances['instapay']['today_in'] ?? 0) ?></strong>
                    <div class="amount-divider"></div>
                    <div class="amount-sub">
                        <span>حركة اليوم:</span>
                        <strong><?= money($treasuryBalances['instapay']['today_in'] ?? 0) ?></strong>
                        <span>منصرف:</span>
                        <strong><?= money($treasuryBalances['instapay']['out'] ?? 0) ?></strong>
                    </div>
                </div>
            </div>

            <div class="treasury-card vodafone">
                <div class="card-header">
                    <span class="card-title">حركة اليوم فودافون كاش</span>
                    <div class="card-icon">VF</div>
                </div>
                <div class="card-amount-wrapper">
                    <strong class="amount-main"><?= money($treasuryBalances['vodafone_cash']['today_in'] ?? 0) ?></strong>
                    <div class="amount-divider"></div>
                    <div class="amount-sub">
                        <span>حركة اليوم:</span>
                        <strong><?= money($treasuryBalances['vodafone_cash']['today_in'] ?? 0) ?></strong>
                        <span>منصرف:</span>
                        <strong><?= money($treasuryBalances['vodafone_cash']['out'] ?? 0) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Overview Stats -->
    <div>
        <h3 class="section-title">جرد المنتجات وحالة المخزون</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <span class="stat-label">إجمالي بنود المخزون بالفرع</span>
                    <strong class="stat-value"><?= $totalItems ?></strong>
                </div>
            </div>
            <div class="stat-card" style="border-right: 4px solid var(--danger);">
                <div class="stat-icon" style="color: var(--danger);">⚠️</div>
                <div class="stat-info">
                    <span class="stat-label">أصناف منخفضة (تحتاج توريد)</span>
                    <strong class="stat-value" style="color: var(--danger);"><?= $lowStockCount ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Toolbar: Search & Location Switcher -->
    <div class="dashboard-toolbar">
        <div class="search-wrapper">
            <input type="text" id="branch-search" placeholder="فلترة سريعة لجدول الأصناف بالاسم أو الباركود...">
            <span class="search-icon">🔍</span>
        </div>
        <div style="display:flex; align-items:center; gap:8px; background:var(--surface-soft); border:1.5px solid var(--line); border-radius:10px; padding:8px 12px;">
            <span style="font-size:16px;">📷</span>
            <input type="text" id="barcode-scan-branch" placeholder="مسح باركود للفلترة..." autocomplete="off"
                   style="border:none; background:transparent; outline:none; font-size:13px; min-width:140px;">
        </div>
        
        <?php if ($userLocationId === null): ?>
            <div>
                <form method="get" style="display: flex; align-items: center; gap: 10px; margin: 0;">
                    <input type="hidden" name="r" value="branch_inventory">
                    <label style="margin: 0; font-size: 13px; font-weight: 700; color: var(--muted); white-space: nowrap;">عرض فرع:</label>
                    <select name="location_id" id="current-location-select" onchange="this.form.submit()" class="filter-select">
                        <option value="all" <?= $showAllLocations ? 'selected' : '' ?>>كل الفروع والمستودعات</option>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= e($l['id']) ?>" <?= !$showAllLocations && (int)$l['id'] === $locationId ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Inventory Table Panel -->
    <div class="custom-table-container">
        <table class="branch-table" id="branch-inventory-table">
            <thead>
                <tr>
                    <th>اسم المنتج</th>
                    <th>نوع الصنف</th>
                    <?php if ($showAllLocations): ?>
                        <th>الموقع</th>
                    <?php endif; ?>
                    <th>الرصيد المتوفر</th>
                    <th>حد الأمان</th>
                    <th style="width: 130px; text-align: center;">حالة المخزون</th>
                    <?php if (has_permission('inventory_adjust') && !$showAllLocations): ?>
                        <th style="text-align:center;">إجراءات</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="muted" style="text-align: center; padding: 30px;">لا توجد أي منتجات معرفة في هذا الفرع حالياً.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): 
                        $isLow = (float)$r['min_stock'] > 0 && (float)$r['quantity'] <= (float)$r['min_stock'];
                        $barcodeVal = $barcodeMap[(int)$r['product_id']] ?? '';
                    ?>
                        <tr class="inventory-item-row <?= $isLow ? 'warn-row' : '' ?>" data-name="<?= e(mb_strtolower($r['product_name'])) ?>" data-type="<?= e(mb_strtolower($typeTranslations[$r['type']] ?? $r['type'])) ?>" data-barcode="<?= e($barcodeVal) ?>">
                            <td>
                                <strong><?= e($r['product_name']) ?></strong>
                                <?php if ($barcodeVal): ?>
                                    <br><small style="color: var(--muted); font-size: 10.5px;"><code><?= e($barcodeVal) ?></code></small>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge-type <?= e($r['type']) ?>"><?= e($typeTranslations[$r['type']] ?? $r['type']) ?></span></td>
                            <?php if ($showAllLocations): ?>
                                <td><span style="font-weight: 600; color: var(--muted);"><?= e($r['location_name']) ?></span></td>
                            <?php endif; ?>
                            <td>
                                <strong style="font-size: 15px; color: <?= (float)$r['quantity'] > 0 ? 'var(--ink)' : 'var(--danger)' ?>;">
                                    <?= e(qty($r['quantity'])) ?>
                                </strong>
                                <span class="muted" style="font-size: 11px;"><?= e($r['unit'] === 'gram' ? 'جرام' : 'قطعة') ?></span>
                            </td>
                            <td><span style="font-weight: 600; color: var(--muted);"><?= e(qty($r['min_stock'])) ?></span></td>
                            <td style="text-align: center;">
                                <?php if ($isLow): ?>
                                    <span class="badge-status low">⚠️ منخفض</span>
                                <?php else: ?>
                                    <span class="badge-status available">✅ متوفر</span>
                                <?php endif; ?>
                            </td>
                            <?php if (has_permission('inventory_adjust') && !$showAllLocations): ?>
                                <td style="text-align:center; white-space: nowrap;">
                                    <button type="button" class="btn small" onclick="openEditQty(<?= e($r['product_id']) ?>, <?= e(json_encode($r['product_name'])) ?>, <?= e($r['quantity']) ?>, <?= e($locationId) ?>)" title="تعديل الكمية">✏️</button>
                                    <form method="post" action="index.php?r=inventory" style="display:inline;" onsubmit="return confirm('حذف <?= e($r['product_name']) ?> من مخزون الفرع؟ سيتم تصفير الرصيد للصفر.');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="set_zero">
                                        <input type="hidden" name="location_id" value="<?= e($locationId) ?>">
                                        <input type="hidden" name="product_id" value="<?= e($r['product_id']) ?>">
                                        <button class="btn small danger" type="submit" title="حذف/تصفير">🗑</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal تعديل كمية المنتج في الفرع -->
<div id="edit-qty-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1000; justify-content:center; align-items:center;">
    <div style="background:var(--surface,#fff); border-radius:16px; padding:28px 24px; min-width:320px; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,0.2);">
        <h3 style="margin:0 0 16px; color:var(--primary);">✏️ تعديل كمية المنتج</h3>
        <p id="edit-qty-product-name" style="font-weight:800; font-size:15px; margin:0 0 16px; color:var(--ink);"></p>
        <form method="post" action="index.php?r=inventory">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="edit_balance">
            <input type="hidden" name="product_id" id="edit-qty-product-id">
            <input type="hidden" name="location_id" id="edit-qty-location-id">
            <label style="display:block; margin-bottom:14px;">
                الكمية الجديدة
                <input type="number" name="new_quantity" id="edit-qty-value" step="any" min="0" style="margin-top:6px; width:100%; font-size:18px; font-weight:800; text-align:center; padding:10px;" placeholder="0">
            </label>
            <div style="display:flex; gap:10px;">
                <button class="btn primary" type="submit" style="flex:1;">💾 حفظ التعديل</button>
                <button type="button" class="btn" onclick="closeEditQtyModal()" style="flex:1;">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('branch-search');
    const barcodeScan = document.getElementById('barcode-scan-branch');

    function filterRows(query) {
        const q = query.trim().toLowerCase();
        const rows = document.querySelectorAll('#branch-inventory-table tbody tr.inventory-item-row');
        rows.forEach(row => {
            const name    = row.getAttribute('data-name')    || '';
            const type    = row.getAttribute('data-type')    || '';
            const barcode = row.getAttribute('data-barcode') || '';
            const show = !q || name.includes(q) || type.includes(q) || barcode.includes(q);
            row.style.display = show ? '' : 'none';
        });
    }

    if (searchInput) searchInput.addEventListener('input', function() { filterRows(this.value); });

    if (barcodeScan) {
        barcodeScan.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter' && e.key !== 'Tab') return;
            e.preventDefault();
            filterRows(this.value);
            if (searchInput) searchInput.value = this.value;
        });
        barcodeScan.addEventListener('input', function() {
            filterRows(this.value);
            if (searchInput) searchInput.value = this.value;
        });
    }
});

function openEditQty(productId, productName, currentQty, locationId) {
    document.getElementById('edit-qty-product-id').value = productId;
    document.getElementById('edit-qty-location-id').value = locationId;
    document.getElementById('edit-qty-product-name').textContent = productName;
    document.getElementById('edit-qty-value').value = currentQty;
    document.getElementById('edit-qty-modal').style.display = 'flex';
    document.getElementById('edit-qty-value').focus();
    document.getElementById('edit-qty-value').select();
}

function closeEditQtyModal() {
    document.getElementById('edit-qty-modal').style.display = 'none';
}
</script>
