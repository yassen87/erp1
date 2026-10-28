<?php
$products = all_products();
$locations = stock_locations();
$userLocationId = current_user_location_id();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
}

// Selected location for stock display
$selectedLocationId = (int)($_GET['location_id'] ?? ($userLocationId ?: ($locations[0]['id'] ?? 0)));

// Pre-load stock for all products in this location
$stockMap = [];
if ($selectedLocationId > 0) {
    try {
        $stmtStock = pdo()->prepare('SELECT product_id, quantity FROM inventory_balances WHERE location_id = ?');
        $stmtStock->execute([$selectedLocationId]);
        $stockMap = $stmtStock->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    } catch (Throwable $e) {
        $stockMap = [];
    }
}

// Build structured products list with category and live stock
$catalogData = [];
foreach ($products as $p) {
    $pType = $p['type'] ?? 'fixed';
    $cat = 'product';
    $catLabel = 'منتج جاهز';
    $unit = 'قطعة';
    $step = '1';

    if ($pType === 'perfume_gram') {
        $cat = 'oil';
        $catLabel = 'زيت عطري';
        $unit = 'جم';
        $step = 'any';
    } elseif ($pType === 'bottle') {
        $cat = 'bottle';
        $catLabel = 'زجاجة فارغة';
        $unit = 'قطعة';
        $step = '1';
    }

    $stk = (float)($stockMap[$p['id']] ?? 0);

    $catalogData[] = [
        'id'         => (int)$p['id'],
        'name'       => $p['name'],
        'category'   => $cat,
        'cat_label'  => $catLabel,
        'type'       => $pType,
        'unit'       => $unit,
        'step'       => $step,
        'stock'      => $stk,
        'barcode'    => $p['barcode'] ?? '',
        'sku'        => $p['sku'] ?? '',
    ];
}

$rows = all_wasted_products($userLocationId);

// Calculate KPI totals
$totalWasteCount = count($rows);
$oilWasteGrams = 0.0;
$bottleWasteUnits = 0.0;
$productWasteUnits = 0.0;

foreach ($rows as $r) {
    $q = (float)$r['quantity'];
    // We can check product type if available
    $pType = $r['product_type'] ?? '';
    if (!$pType) {
        $foundP = array_filter($catalogData, fn($c) => $c['id'] === (int)$r['product_id']);
        if ($foundP) {
            $foundP = reset($foundP);
            $pType = $foundP['type'];
        }
    }
    if ($pType === 'perfume_gram') {
        $oilWasteGrams += $q;
    } elseif ($pType === 'bottle') {
        $bottleWasteUnits += $q;
    } else {
        $productWasteUnits += $q;
    }
}
?>
<style>
.waste-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}
.waste-kpi-card {
    background: var(--surface);
    border: 1.5px solid var(--line);
    border-radius: 14px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}
.waste-kpi-card.alert {
    border-color: rgba(239, 68, 68, 0.35);
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.04) 0%, var(--surface) 100%);
}
.waste-kpi-icon {
    font-size: 28px;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--surface-soft, rgba(0,0,0,0.04));
}
.waste-kpi-num {
    font-size: 20px;
    font-weight: 800;
    color: var(--ink);
    line-height: 1.2;
}
.waste-kpi-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    margin-top: 2px;
}

/* Category Tabs */
.waste-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.waste-tab-btn {
    background: var(--surface);
    border: 1.5px solid var(--line);
    border-radius: 10px;
    padding: 8px 16px;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    color: var(--ink);
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.waste-tab-btn:hover {
    border-color: var(--gold, #C9A84C);
    background: var(--gold-soft, rgba(201,168,76,0.1));
}
.waste-tab-btn.active {
    background: var(--gold, #C9A84C);
    color: #fff;
    border-color: var(--gold-dark, #9E7A2A);
    box-shadow: 0 2px 8px rgba(201,168,76,0.3);
}

/* Search and Catalog Grid */
.waste-search-box {
    width: 100%;
    padding: 12px 16px;
    font-size: 14px;
    border-radius: 10px;
    border: 1.5px solid var(--line);
    background: var(--surface);
    color: var(--ink);
    outline: none;
    transition: all 0.2s ease;
    margin-bottom: 12px;
}
.waste-search-box:focus {
    border-color: var(--gold, #C9A84C);
    box-shadow: 0 0 0 3px rgba(201,168,76,0.18);
}
.waste-catalog-list {
    max-height: 280px;
    overflow-y: auto;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--surface);
    padding: 6px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 6px;
}
.waste-item-card {
    padding: 10px 12px;
    border: 1.5px solid var(--line);
    border-radius: 8px;
    background: var(--surface-soft, rgba(0,0,0,0.02));
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.waste-item-card:hover {
    border-color: #2563eb;
    background: rgba(37,99,235,0.06);
    transform: translateY(-1px);
}
.waste-item-card.selected {
    border-color: #2563eb;
    background: rgba(37,99,235,0.12);
    box-shadow: 0 0 0 2px rgba(37,99,235,0.3);
}

/* Selected item preview panel */
.waste-selected-panel {
    background: linear-gradient(135deg, rgba(37,99,235,0.06) 0%, rgba(201,168,76,0.04) 100%), var(--surface);
    border: 2px solid #2563eb;
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

/* Quick chips */
.waste-chip {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 6px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    color: var(--ink);
    transition: all 0.15s;
}
.waste-chip:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border-color: #dc2626;
}
</style>

<section class="page-head">
    <div>
        <h2>🗑️ الهالك والمنتجات التالفة</h2>
        <p>تسجيل وإدارة المنتجات أو الزيوت أو الزجاجات التالفة وخصمها آلياً من رصيد الفرع بالجرام أو بالقطعة.</p>
    </div>
    <div style="display: flex; gap: 8px; align-items: center;">
        <button type="button" onclick="if(window.history.length > 1) { window.history.back(); } else { window.location.href='index.php?r=dashboard'; }" class="btn-global-back" title="رجوع للصفحة السابقة">
            <span>↩️</span>
            <span>رجوع</span>
        </button>
        <a class="btn" href="index.php?r=branch_inventory">📦 رصيد الفرع</a>
    </div>
</section>

<!-- KPI Cards -->
<div class="waste-kpi-grid">
    <div class="waste-kpi-card alert">
        <div class="waste-kpi-icon">🗑️</div>
        <div>
            <div class="waste-kpi-num" style="color: #dc2626;"><?= number_format($totalWasteCount) ?></div>
            <div class="waste-kpi-label">إجمالي عمليات الهالك المسجلة</div>
        </div>
    </div>
    <div class="waste-kpi-card">
        <div class="waste-kpi-icon">🧪</div>
        <div>
            <div class="waste-kpi-num" style="color: #d97706;"><?= qty($oilWasteGrams) ?> جم</div>
            <div class="waste-kpi-label">إجمالي هالك الزيوت العطرية</div>
        </div>
    </div>
    <div class="waste-kpi-card">
        <div class="waste-kpi-icon">🍾</div>
        <div>
            <div class="waste-kpi-num" style="color: #2563eb;"><?= qty($bottleWasteUnits) ?> قطعة</div>
            <div class="waste-kpi-label">إجمالي هالك الزجاجات</div>
        </div>
    </div>
    <div class="waste-kpi-card">
        <div class="waste-kpi-icon">📦</div>
        <div>
            <div class="waste-kpi-num" style="color: #059669;"><?= qty($productWasteUnits) ?> قطعة</div>
            <div class="waste-kpi-label">إجمالي هالك المنتجات الجاهزة</div>
        </div>
    </div>
</div>

<?php if (has_permission('inventory_adjust')): ?>
<div class="panel" style="margin-bottom: 22px;">
    <h3 style="margin-top: 0; margin-bottom: 16px; color: var(--gold-dark); border-bottom: 1.5px solid var(--line); padding-bottom: 8px;">
        ✍️ تسجيل هالك جديد وتنزيل الرصيد من الفرع
    </h3>

    <form method="post" action="index.php?r=waste" id="waste-form" onsubmit="return confirmWasteSubmit()">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="product_id" id="selected-product-id" required>

        <!-- 1. اختيار الموقع / الفرع -->
        <div style="margin-bottom: 16px;">
            <label style="font-weight: 700; font-size: 13.5px; display: block; margin-bottom: 6px;">
                الموقع / الفرع المراد خصم الهالك منه:
            </label>
            <?php if ($userLocationId === null): ?>
                <select name="location_id" id="waste-location-select" onchange="onLocationChange(this.value)" style="max-width: 380px; padding: 10px 14px; border-radius: 8px; border: 1.5px solid var(--line); font-weight: 700; font-size: 14px;" required>
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= e($l['id']) ?>" <?= $selectedLocationId === (int)$l['id'] ? 'selected' : '' ?>>
                            📍 <?= e($l['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <select name="location_id" disabled style="max-width: 380px; padding: 10px 14px; border-radius: 8px; border: 1.5px solid var(--line); font-weight: 700; font-size: 14px;">
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= e($l['id']) ?>" <?= $userLocationId === (int)$l['id'] ? 'selected' : '' ?>>
                            📍 <?= e($l['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="location_id" value="<?= $userLocationId ?>">
            <?php endif; ?>
        </div>

        <!-- 2. تبويبات الفرز والبحث عن الصنف -->
        <label style="font-weight: 700; font-size: 13.5px; display: block; margin-bottom: 8px;">
            اختر نوع الصنف التالف ثم ابحث عنه:
        </label>
        
        <div class="waste-tabs">
            <button type="button" class="waste-tab-btn active" onclick="switchWasteTab('all', this)">
                <span>🔍 الكل</span>
                <span style="font-size: 11px; opacity: 0.8;">(<?= count($catalogData) ?>)</span>
            </button>
            <button type="button" class="waste-tab-btn" onclick="switchWasteTab('oil', this)">
                <span>🧪 زيت عطري (بالجرام)</span>
            </button>
            <button type="button" class="waste-tab-btn" onclick="switchWasteTab('bottle', this)">
                <span>🍾 زجاجة فارغة (بالقطعة)</span>
            </button>
            <button type="button" class="waste-tab-btn" onclick="switchWasteTab('product', this)">
                <span>📦 منتج جاهز</span>
            </button>
        </div>

        <!-- صندوق البحث الفوري -->
        <input type="search" id="waste-search-input" class="waste-search-box" placeholder="🔎 ابحث بالاسم أو الباركود أو الكود..." oninput="filterWasteCatalog()">

        <!-- شبكة الأصناف المتاحة للاختيار -->
        <div class="waste-catalog-list" id="waste-catalog-list">
            <!-- Rendered by JavaScript -->
        </div>

        <!-- الصنف المحدد حالياً -->
        <div id="selected-product-box" style="display: none; margin-top: 16px;">
            <div class="waste-selected-panel">
                <div>
                    <div style="font-size: 11.5px; font-weight: 700; color: #2563eb; margin-bottom: 4px;">✅ الصنف التالف المحدد:</div>
                    <div style="font-size: 16px; font-weight: 800; color: var(--ink);" id="sel-prod-name">-</div>
                    <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                        النوع: <span id="sel-prod-cat" style="font-weight: 700; color: var(--ink);">-</span>
                    </div>
                </div>

                <div style="text-align: left; background: #fff; border: 1.5px solid rgba(37,99,235,0.3); border-radius: 10px; padding: 8px 16px;">
                    <div style="font-size: 11px; font-weight: 700; color: var(--muted);">المخزون المتاح في الفرع:</div>
                    <div style="font-size: 18px; font-weight: 800; color: #059669;" id="sel-prod-stock">0.00</div>
                </div>
            </div>

            <!-- إدخال الكمية وسبب الهالك -->
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 14px; margin-bottom: 16px;">
                <label style="font-weight: 700; font-size: 13.5px;">
                    الكمية التالفة * (<span id="sel-prod-unit">وحدة</span>)
                    <input type="number" name="quantity" id="waste-qty-input" min="0.000001" step="any" required placeholder="أدخل الكمية..." style="margin-top: 6px; font-size: 16px; font-weight: 800; padding: 10px 14px; border-radius: 8px; border: 1.5px solid var(--line);">
                    <small class="muted" id="qty-limit-hint" style="font-size: 11px; display: block; margin-top: 4px;">لا يمكن تجاوز الرصيد المتاح.</small>
                </label>

                <label style="font-weight: 700; font-size: 13.5px;">
                    سبب التلف / الهالك *
                    <input type="text" name="reason" id="waste-reason-input" required placeholder="اكتب السبب أو اختر من الاختصارات أدناه..." style="margin-top: 6px; font-size: 14px; padding: 10px 14px; border-radius: 8px; border: 1.5px solid var(--line);">
                    
                    <!-- اختصارات سريعة للأسباب -->
                    <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px;">
                        <button type="button" class="waste-chip" onclick="setWasteReason('كسر زجاجة أثناء العمل')">💥 كسر زجاجة</button>
                        <button type="button" class="waste-chip" onclick="setWasteReason('انسكاب زيت عطري')">💧 انسكاب زيت</button>
                        <button type="button" class="waste-chip" onclick="setWasteReason('بلف بخاخ تالف / عيب تصنيع')">⚠️ بلف تالف</button>
                        <button type="button" class="waste-chip" onclick="setWasteReason('انتهاء صلاحية / تلف المادة')">⏳ انتهاء صلاحية</button>
                        <button type="button" class="waste-chip" onclick="setWasteReason('هالك تجارب واختبار')">🔬 هالك تجارب</button>
                    </div>
                </label>
            </div>

            <div style="text-align: left;">
                <button type="submit" class="btn danger" style="padding: 12px 28px; font-size: 15px; font-weight: 800; border-radius: 10px; box-shadow: 0 4px 12px rgba(220,38,38,0.3);">
                    🗑️ تأكيد تسجيل الهالك وخصم الرصيد الآن
                </button>
            </div>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- سجل الهالك والتالف -->
<div class="panel">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
        <h3 style="margin: 0; color: var(--ink);">📋 سجل الهالك والتالف السابق</h3>
        <span class="badge" style="font-weight: 700;">عدد السجلات: <?= count($rows) ?></span>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr style="background: var(--surface-soft, rgba(0,0,0,0.02)); border-bottom: 2px solid var(--line);">
                    <th>التاريخ والوقت</th>
                    <th>المنتج / الصنف</th>
                    <th>الموقع / الفرع</th>
                    <th>الكمية المخصومة</th>
                    <th>سبب التلف</th>
                    <th>المسؤول</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="6" class="muted" style="text-align: center; padding: 24px;">لا توجد أي سجلات هالك حتى الآن.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e(format_datetime($r['created_at'])) ?></td>
                            <td><strong><?= e($r['product_name']) ?></strong></td>
                            <td><?= e($r['location_name']) ?></td>
                            <td style="color: #dc2626; font-weight: 800; font-size: 14px;" dir="ltr">
                                -<?= e(qty($r['quantity'])) ?>
                            </td>
                            <td><?= e($r['reason']) ?></td>
                            <td><?= e($r['user_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const CATALOG = <?= json_encode($catalogData) ?>;
let currentTab = 'all';
let selectedProduct = null;

function renderWasteCatalog() {
    const list = document.getElementById('waste-catalog-list');
    const q = (document.getElementById('waste-search-input')?.value || '').trim().toLowerCase();
    if (!list) return;

    list.innerHTML = '';

    const filtered = CATALOG.filter(item => {
        const matchTab = (currentTab === 'all' || item.category === currentTab);
        if (!matchTab) return false;
        if (!q) return true;
        return (item.name || '').toLowerCase().includes(q) ||
               (item.barcode || '').toLowerCase().includes(q) ||
               (item.sku || '').toLowerCase().includes(q);
    });

    if (filtered.length === 0) {
        list.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; padding: 24px; color: var(--muted); font-size: 13px;">لا توجد أصناف مطابقة للبحث في هذا القسم.</div>`;
        return;
    }

    filtered.forEach(item => {
        const isSelected = selectedProduct && selectedProduct.id === item.id;
        const card = document.createElement('div');
        card.className = `waste-item-card ${isSelected ? 'selected' : ''}`;
        card.onclick = () => selectWasteProduct(item);

        let icon = '📦';
        if (item.category === 'oil') icon = '🧪';
        else if (item.category === 'bottle') icon = '🍾';

        const hasStock = item.stock > 0;
        const stockColor = hasStock ? '#059669' : '#dc2626';

        card.innerHTML = `
            <div>
                <div style="font-weight: 700; font-size: 13px; color: var(--ink);">
                    <span>${icon}</span> ${item.name}
                </div>
                <div style="font-size: 11px; color: var(--muted); margin-top: 2px;">
                    ${item.cat_label}
                </div>
            </div>
            <div style="text-align: left;">
                <span class="badge" style="background: ${hasStock ? 'rgba(16,185,129,0.1)' : 'rgba(239,68,68,0.1)'}; color: ${stockColor}; font-weight: 800; font-size: 11px;">
                    ${item.stock} ${item.unit}
                </span>
            </div>
        `;
        list.appendChild(card);
    });
}

function switchWasteTab(tab, btn) {
    currentTab = tab;
    document.querySelectorAll('.waste-tab-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    renderWasteCatalog();
}

function filterWasteCatalog() {
    renderWasteCatalog();
}

function selectWasteProduct(item) {
    selectedProduct = item;
    document.getElementById('selected-product-id').value = item.id;
    document.getElementById('sel-prod-name').textContent = item.name;
    document.getElementById('sel-prod-cat').textContent = item.cat_label;
    document.getElementById('sel-prod-stock').textContent = `${item.stock} ${item.unit}`;
    document.getElementById('sel-prod-unit').textContent = item.unit;

    const qtyInput = document.getElementById('waste-qty-input');
    qtyInput.step = 'any';
    qtyInput.min = '0.000001';
    qtyInput.value = '';
    qtyInput.max = item.stock > 0 ? item.stock : 0;
    document.getElementById('qty-limit-hint').textContent = `الرصيد المتاح حالياً: ${item.stock} ${item.unit}`;

    document.getElementById('selected-product-box').style.display = 'block';
    renderWasteCatalog();

    // Scroll to input smoothly
    qtyInput.focus();
}

function setWasteReason(reason) {
    const input = document.getElementById('waste-reason-input');
    if (input) {
        input.value = reason;
    }
}

function onLocationChange(locId) {
    window.location.href = `index.php?r=waste&location_id=${encodeURIComponent(locId)}`;
}

function confirmWasteSubmit() {
    if (!selectedProduct) {
        alert('الرجاء اختيار الصنف التالف أولاً من القائمة!');
        return false;
    }
    const qty = parseFloat(document.getElementById('waste-qty-input')?.value) || 0;
    if (qty <= 0) {
        alert('الرجاء إدخال كمية تالفة صحيحة أكبر من صفر!');
        return false;
    }
    if (qty > selectedProduct.stock) {
        alert(`الكمية المدخلة (${qty} ${selectedProduct.unit}) أكبر من المخزون المتاح في الفرع (${selectedProduct.stock} ${selectedProduct.unit})!`);
        return false;
    }
    return confirm(`هل أنت متأكد من تسجيل هالك للصنف:\n\n• ${selectedProduct.name}\n• الكمية: ${qty} ${selectedProduct.unit}\n\nسيتم خصم هذه الكمية فوراً من رصيد الفرع.`);
}

document.addEventListener('DOMContentLoaded', () => {
    renderWasteCatalog();
});
</script>
