<?php
$locations = stock_locations();
$userLocationId = current_user_location_id();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
}
$products = all_products();
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'location_id' => trim((string) ($_GET['location_id'] ?? '')),
    'product_id' => trim((string) ($_GET['product_id'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$additions = inventory_addition_rows($filters, $userLocationId);

// Group additions by: location_id, created_by, and created_at (since sequences in a single submit share the same timestamp)
$groupedAdditions = [];
foreach ($additions as $addition) {
    $groupKey = $addition['location_id'] . '_' . $addition['created_by'] . '_' . str_replace([' ', ':'], '_', $addition['created_at']);
    if (!isset($groupedAdditions[$groupKey])) {
        $groupedAdditions[$groupKey] = [
            'created_at' => $addition['created_at'],
            'location_id' => $addition['location_id'],
            'location_name' => $addition['location_name'],
            'user_name' => $addition['user_name'],
            'notes' => $addition['notes'],
            'items' => [],
            'movement_ids' => []
        ];
    }
    $groupedAdditions[$groupKey]['items'][] = [
        'id' => $addition['id'],
        'product_name' => $addition['product_name'],
        'unit' => $addition['unit'],
        'quantity_delta' => (float)$addition['quantity_delta'],
        'sale_price' => (float)$addition['sale_price'],
        'cost_price' => $addition['cost_price'] !== null ? (float)$addition['cost_price'] : null,
        'notes' => $addition['notes']
    ];
    $groupedAdditions[$groupKey]['movement_ids'][] = $addition['id'];
}
?>

<style>
/* Premium Inventory Adjustments Styling */
.grouped-table th {
    padding: 14px 16px;
    font-weight: 700;
    color: var(--muted);
    font-size: 12.5px;
}

.grouped-table td {
    padding: 16px;
    vertical-align: middle;
}

.preview-badge-container {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    max-width: 320px;
}

.preview-item-badge {
    background: var(--surface-soft);
    border: 1px solid var(--line);
    border-radius: 6px;
    padding: 3px 8px;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--ink);
}

/* Modal Popup Styles */
.inventory-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
}

.inventory-modal.active {
    display: flex;
}

.inventory-modal-content {
    background-color: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 24px;
    width: 90%;
    max-width: 720px;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    animation: modalFadeIn 0.2s ease-out;
}

@keyframes modalFadeIn {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

.inventory-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1.5px solid var(--line);
    padding-bottom: 14px;
    margin-bottom: 18px;
}

.inventory-modal-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 800;
    color: var(--primary);
}

.inventory-modal-close {
    font-size: 24px;
    font-weight: 700;
    color: var(--muted);
    cursor: pointer;
    line-height: 1;
    transition: color 0.15s ease;
}

.inventory-modal-close:hover {
    color: var(--danger);
}

.modal-info-bar {
    display: flex;
    gap: 20px;
    font-size: 12.5px;
    color: var(--muted);
    font-weight: 600;
    background: var(--surface-soft);
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 16px;
    border: 1px solid var(--line);
}

.modal-info-bar span strong {
    color: var(--ink);
}

.modal-table {
    width: 100%;
    border-collapse: collapse;
    background: transparent !important;
}

.modal-table th {
    padding: 12px 16px;
    font-weight: 700;
    color: var(--muted);
    font-size: 12px;
    border-bottom: 2px solid var(--line);
    text-align: right;
    background: var(--surface-soft);
}

.modal-table tbody tr {
    background: transparent !important;
    transition: background 0.15s ease;
}

.modal-table tbody tr:hover {
    background: var(--primary-soft) !important;
}

.modal-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--line);
    font-size: 13px;
    vertical-align: middle;
}
</style>

<section class="page-head">
    <div>
        <h2>إضافات المخزون المركزي</h2>
        <p>سجل عمليات إضافة المخزون المجمعة للفرع والمستودعات مع إمكانية عرض محتويات كل عملية والتعديل عليها.</p>
    </div>
    <div>
        <a class="btn primary" href="index.php?r=inventory_add">➕ إضافة مخزون جديد</a>
    </div>
</section>

<!-- Filter Form -->
<form class="panel product-filter-grid" method="get" style="margin-bottom: 20px;">
    <input type="hidden" name="r" value="inventory">
    <label>البحث العام<input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="اسم منتج، موقع، ملاحظة..."></label>
    <label>الموقع / الفرع<select name="location_id">
        <option value="">كل المواقع</option>
        <?php foreach ($locations as $location): ?>
            <option value="<?= e($location['id']) ?>" <?= $filters['location_id'] === (string) $location['id'] ? 'selected' : '' ?>><?= e($location['name']) ?></option>
        <?php endforeach; ?>
    </select></label>
    <label>الصنف المحدد<select name="product_id">
        <option value="">كل الأصناف</option>
        <?php foreach ($products as $product): ?>
            <option value="<?= e($product['id']) ?>" <?= $filters['product_id'] === (string) $product['id'] ? 'selected' : '' ?>><?= e($product['name']) ?></option>
        <?php endforeach; ?>
    </select></label>
    <label>من تاريخ<input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label>
    <label>إلى تاريخ<input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label>
    <div style="display:flex; gap:8px; align-items:end;">
        <button class="btn primary">تطبيق الفلاتر</button>
        <a class="btn" href="index.php?r=inventory">إعادة ضبط</a>
    </div>
</form>

<!-- Grouped Additions Table -->
<div class="panel">
    <h3>سجل عمليات إضافة المخزون</h3>
    <table class="grouped-table">
        <thead>
            <tr>
                <th style="width: 160px;">التاريخ والوقت</th>
                <th style="width: 140px;">الموقع / الفرع</th>
                <th style="width: 150px; text-align: center;">الأصناف المضافة</th>
                <th>الملاحظات</th>
                <th style="width: 120px;">المسؤول</th>
                <th style="width: 120px; text-align: center;">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($groupedAdditions)): ?>
                <tr>
                    <td colspan="6" class="muted" style="text-align:center; padding: 25px;">لا توجد عمليات إضافة مخزون مطابقة للفلاتر.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($groupedAdditions as $key => $group): 
                    // Generate preview badges for first 3 items
                    $previewBadges = [];
                    foreach (array_slice($group['items'], 0, 3) as $item) {
                        $unit = $item['unit'] === 'gram' ? 'جم' : 'ق';
                        $previewBadges[] = e($item['product_name']) . ' (' . qty($item['quantity_delta']) . ' ' . $unit . ')';
                    }
                    $hasMore = count($group['items']) > 3;
                ?>
                    <tr>
                        <td><strong><?= e($group['created_at']) ?></strong></td>
                        <td><span class="badge"><?= e($group['location_name']) ?></span></td>
                        <td style="text-align: center;">
                            <button type="button" class="btn small primary" onclick="showTransactionItems('<?= $key ?>')" style="padding: 4px 10px; font-size: 11px; border-radius: 6px; font-weight: 700;">
                                👁️ عرض الأصناف (<?= count($group['items']) ?>)
                            </button>
                        </td>
                        <td><span style="font-size: 12px; color: var(--muted);"><?= e($group['notes']) ?></span></td>
                        <td><strong><?= e($group['user_name']) ?></strong></td>
                        <td style="text-align: center;">
                            <form method="post" class="inline" onsubmit="return confirm('هل تريد حذف هذه العملية بالكامل بجميع أصنافها وتعديل كميات المخزن؟')">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="location_id" value="<?= e($group['location_id']) ?>">
                                <input type="hidden" name="movement_id" value="<?= implode(',', $group['movement_ids']) ?>">
                                <button class="btn small danger" style="padding: 4px 10px; font-size: 11px; border-radius: 6px;">حذف العملية</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Dialog for viewing items -->
<div id="inventory-items-modal" class="inventory-modal">
    <div class="inventory-modal-content">
        <div class="inventory-modal-header">
            <h3>📦 تفاصيل الأصناف المضافة في العملية</h3>
            <span class="inventory-modal-close" onclick="closeInventoryModal()">&times;</span>
        </div>
        
        <div class="modal-info-bar">
            <span>التاريخ: <strong id="modal-title-date"></strong></span>
            <span>الموقع: <strong id="modal-title-location"></strong></span>
            <span>المسؤول: <strong id="modal-title-user"></strong></span>
        </div>
        
        <div style="max-height: 350px; overflow-y: auto; border: 1px solid var(--line); border-radius: 8px;">
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>اسم المنتج</th>
                        <th style="width: 80px;">الوحدة</th>
                        <th style="width: 90px; text-align: center;">الكمية</th>
                        <th style="width: 100px; text-align: left;">سعر البيع</th>
                        <th style="width: 100px; text-align: left;">تكلفة الشراء</th>
                        <th>ملاحظة الصنف</th>
                    </tr>
                </thead>
                <tbody id="modal-items-tbody">
                    <!-- Dynamic Rows Inserted by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const groupedTransactionsData = <?= json_encode($groupedAdditions) ?>;

function showTransactionItems(key) {
    const data = groupedTransactionsData[key];
    if (!data) return;

    document.getElementById('modal-title-date').textContent = data.created_at;
    document.getElementById('modal-title-location').textContent = data.location_name;
    document.getElementById('modal-title-user').textContent = data.user_name;

    const tbody = document.getElementById('modal-items-tbody');
    tbody.innerHTML = '';

    data.items.forEach(item => {
        const tr = document.createElement('tr');
        const unitLabel = item.unit === 'gram' ? 'جرام' : 'قطعة';
        const costLabel = item.cost_price !== null ? formatMoney(item.cost_price) : 'غير محدد';
        
        tr.innerHTML = `
            <td style="font-weight:700; color:var(--ink);">${escapeHtml(item.product_name)}</td>
            <td><span class="badge" style="font-size:10px; padding:2px 6px;">${escapeHtml(unitLabel)}</span></td>
            <td style="font-weight:800; text-align:center; color:var(--primary);">${item.quantity_delta}</td>
            <td style="color:var(--success); font-weight:700; text-align:left;">${formatMoney(item.sale_price)}</td>
            <td style="color:var(--muted); font-weight:600; text-align:left;">${costLabel}</td>
            <td style="font-size:11.5px; color:var(--muted);">${escapeHtml(item.notes)}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('inventory-items-modal').classList.add('active');
}

function closeInventoryModal() {
    document.getElementById('inventory-items-modal').classList.remove('active');
}

// Close modal when clicking outside content
window.addEventListener('click', function(event) {
    const modal = document.getElementById('inventory-items-modal');
    if (event.target === modal) {
        closeInventoryModal();
    }
});

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function formatMoney(amount) {
    return parseFloat(amount).toFixed(2) + ' ج.م';
}
</script>
