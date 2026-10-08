<?php
$userLocationId = current_user_location_id();
$allLocations = all_locations();
$branches = array_values(array_filter($allLocations, fn($l) => $l['type'] === 'branch'));
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'from_location_id' => trim((string) ($_GET['from_location_id'] ?? '')),
    'to_location_id' => trim((string) ($_GET['to_location_id'] ?? '')),
    'date_from' => trim((string) ($_GET['date_from'] ?? '')),
    'date_to' => trim((string) ($_GET['date_to'] ?? '')),
];
$rows = branch_transfers_rows($userLocationId, $filters);

// Load all transfer items for the listed transfers in ONE batch query to optimize performance
$transferIds = array_column($rows, 'id');
$transferItemsMap = [];
if (!empty($transferIds)) {
    $db = pdo();
    $inClause = implode(',', array_fill(0, count($transferIds), '?'));
    $stmt = $db->prepare("SELECT iti.*, p.name AS product_name, p.unit AS product_unit
                          FROM inventory_transfer_items iti
                          JOIN products p ON p.id = iti.product_id
                          WHERE iti.transfer_id IN ($inClause)");
    $stmt->execute($transferIds);
    $allItems = $stmt->fetchAll();
    foreach ($allItems as $item) {
        $transferItemsMap[$item['transfer_id']][] = [
            'product_name' => $item['product_name'],
            'product_unit' => $item['product_unit'],
            'quantity' => (float)$item['quantity']
        ];
    }
}

$daysMap = [
    'Monday' => 'الإثنين',
    'Tuesday' => 'الثلاثاء',
    'Wednesday' => 'الأربعاء',
    'Thursday' => 'الخميس',
    'Friday' => 'الجمعة',
    'Saturday' => 'السبت',
    'Sunday' => 'الأحد',
];

$statusTranslations = [
    'pending' => '<span class="badge warning">قيد الانتظار</span>',
    'received' => '<span class="badge success">تم الاستلام</span>',
    'cancelled' => '<span class="badge danger">ملغي</span>'
];
?>

<style>
/* Modal and Row styling */
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
    max-width: 600px;
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
        <h2>🔄 تحويل بين الفروع</h2>
        <p>تحويل المنتجات بين الفروع فقط.</p>
    </div>
    <div>
        <a class="btn primary" href="index.php?r=transfers_branch_create">إنشاء تحويل جديد</a>
    </div>
</section>

<form class="panel product-filter-grid" method="get" action="index.php">
    <input type="hidden" name="r" value="transfers_branch">
    <label>بحث<input name="q" value="<?= e($filters['q']) ?>" placeholder="رقم/فرع/مرسل"></label>
    <label>حالة<select name="status">
            <option value="">الكل</option>
            <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>قيد الانتظار</option>
            <option value="received" <?= $filters['status'] === 'received' ? 'selected' : '' ?>>تم الاستلام</option>
            <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>ملغي</option>
        </select></label>
    <label>الفرع المرسل<select name="from_location_id">
            <option value="">الكل</option>
            <?php foreach ($branches as $l): ?>
                <option value="<?= e($l['id']) ?>" <?= $filters['from_location_id'] === (string) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
            <?php endforeach; ?>
        </select></label>
    <label>الفرع المستلم<select name="to_location_id">
            <option value="">الكل</option>
            <?php foreach ($branches as $l): ?>
                <option value="<?= e($l['id']) ?>" <?= $filters['to_location_id'] === (string) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
            <?php endforeach; ?>
        </select></label>
    <label>من تاريخ<input name="date_from" type="date" value="<?= e($filters['date_from']) ?>"></label>
    <label>إلى تاريخ<input name="date_to" type="date" value="<?= e($filters['date_to']) ?>"></label>
    <div style="display: flex; gap: 8px; align-items: end;">
        <button class="btn primary" type="submit">تطبيق الفلاتر</button>
        <a class="btn" href="index.php?r=transfers_branch">إعادة ضبط</a>
    </div>
</form>

<div class="panel">
    <h3>سجل التحويلات السابقة</h3>
    <table>
        <thead>
            <tr>
                <th>رقم التحويل</th>
                <th>الفرع المرسل</th>
                <th>الفرع المستلم</th>
                <th>الحالة</th>
                <th style="text-align: center;">الأصناف</th>
                <th>المرسل</th>
                <th>المستلم</th>
                <th>المسؤول</th>
                <th>تاريخ التحويل</th>
                <th>تاريخ الإنشاء</th>
                <th>استلم بواسطة</th>
                <th>تاريخ الاستلام</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $t): 
                $itemCount = count($transferItemsMap[$t['id']] ?? []);
            ?>
                <tr>
                    <td><strong><?= e($t['transfer_number']) ?></strong></td>
                    <td><?= e($t['from_name']) ?></td>
                    <td><?= e($t['to_name']) ?></td>
                    <td><?= $statusTranslations[$t['status']] ?? e($t['status']) ?></td>
                    <td style="text-align: center;">
                        <button type="button" class="btn small primary" onclick="showTransferItems(<?= $t['id'] ?>)" style="padding: 4px 10px; font-size: 11px; border-radius: 6px; font-weight: 700;">
                            👁️ عرض الأصناف (<?= $itemCount ?>)
                        </button>
                    </td>
                    <td><?= e($t['sender_name'] ?: '-') ?></td>
                    <td><?= e($t['receiver_name'] ?: '-') ?></td>
                    <td><?= e($t['created_name']) ?></td>
                    <td><?= e(date('Y-m-d', strtotime($t['transfer_date']))) ?> <br><small><?= e($daysMap[date('l', strtotime($t['transfer_date']))] ?? date('l', strtotime($t['transfer_date']))) ?></small></td>
                    <td><?= e(format_datetime($t['created_at'])) ?></td>
                    <td><?= e($t['received_name'] ?? '-') ?></td>
                    <td><?= $t['received_at'] ? e(format_datetime($t['received_at'])) . '<br><small>' . e($daysMap[date('l', strtotime($t['received_at']))] ?? date('l', strtotime($t['received_at']))) . '</small>' : '<span class="muted">-</span>' ?></td>
                    <td>
                        <?php if ($t['status'] === 'pending'): ?>
                            <a class="btn small secondary" href="index.php?r=transfers_branch_create&edit=<?= e($t['id']) ?>">تعديل</a>
                            <form method="post" class="inline" onsubmit="return confirm('هل تريد حذف أمر التحويل وإعادة الكمية للفرع المرسل؟')">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="transfer_id" value="<?= e($t['id']) ?>">
                                <button class="btn small danger">حذف</button>
                            </form>
                            <form method="post" class="inline" onsubmit="return confirm('هل تؤكد استلام هذه الشحنة وإضافتها لمخزون الفرع المستلم؟')">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="receive">
                                <input type="hidden" name="transfer_id" value="<?= e($t['id']) ?>">
                                <button class="btn small success">تأكيد الاستلام</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Dialog for viewing transfer items -->
<div id="transfer-items-modal" class="inventory-modal">
    <div class="inventory-modal-content">
        <div class="inventory-modal-header">
            <h3>📦 الأصناف والمنتجات بالتحويل</h3>
            <span class="inventory-modal-close" onclick="closeTransferModal()">&times;</span>
        </div>
        
        <div class="modal-info-bar">
            <span>رقم التحويل: <strong id="modal-transfer-number"></strong></span>
            <span>من: <strong id="modal-transfer-from"></strong></span>
            <span>إلى: <strong id="modal-transfer-to"></strong></span>
        </div>
        
        <div style="max-height: 350px; overflow-y: auto; border: 1px solid var(--line); border-radius: 8px;">
            <table class="modal-table">
                <thead>
                    <tr>
                        <th>اسم المنتج</th>
                        <th style="width: 100px;">الوحدة</th>
                        <th style="width: 120px; text-align: center;">الكمية المحولة</th>
                    </tr>
                </thead>
                <tbody id="modal-transfer-items-tbody">
                    <!-- Dynamic Rows Inserted by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const transferItemsData = <?= json_encode($transferItemsMap) ?>;
const transferHeadersData = <?= json_encode(array_column($rows, null, 'id')) ?>;

function showTransferItems(transferId) {
    const header = transferHeadersData[transferId];
    const items = transferItemsData[transferId] || [];

    if (!header) return;

    document.getElementById('modal-transfer-number').textContent = header.transfer_number;
    document.getElementById('modal-transfer-from').textContent = header.from_name;
    document.getElementById('modal-transfer-to').textContent = header.to_name;

    const tbody = document.getElementById('modal-transfer-items-tbody');
    tbody.innerHTML = '';

    items.forEach(item => {
        const tr = document.createElement('tr');
        const unitLabel = item.product_unit === 'gram' ? 'جرام' : 'قطعة';
        tr.innerHTML = `
            <td style="font-weight:700; color:var(--ink);">${escapeHtml(item.product_name)}</td>
            <td><span class="badge" style="font-size:10px; padding:2px 6px;">${escapeHtml(unitLabel)}</span></td>
            <td style="font-weight:800; text-align:center; color:var(--primary); font-size:14px;">${item.quantity}</td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('transfer-items-modal').classList.add('active');
}

function closeTransferModal() {
    document.getElementById('transfer-items-modal').classList.remove('active');
}

// Close modal when clicking outside content
window.addEventListener('click', function(event) {
    const modal = document.getElementById('transfer-items-modal');
    if (event.target === modal) {
        closeTransferModal();
    }
});

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
