<?php
$users = all_users();

// Fetch filter values
$search = trim((string) ($_GET['q'] ?? ''));
$user_id = trim((string) ($_GET['user_id'] ?? ''));
$action_type = trim((string) ($_GET['action_type'] ?? ''));
$start_date = trim((string) ($_GET['start_date'] ?? ''));
$end_date = trim((string) ($_GET['end_date'] ?? ''));

$filters = [
    'q' => $search,
    'user_id' => $user_id,
    'action_type' => $action_type,
    'start_date' => $start_date,
    'end_date' => $end_date,
];

// We display up to 300 rows for performance
$rows = audit_rows($filters, 300);

// Helper for Arabic translation of action types
$action_labels = [
    'login' => 'تسجيل دخول',
    'logout' => 'تسجيل خروج',
    'create' => 'إضافة / إنشاء',
    'update' => 'تعديل بيانات',
    'delete' => 'حذف نهائي',
    'deactivate' => 'تعطيل / إخفاء',
    'adjust' => 'تسوية مخزون',
    'receive' => 'استلام شحنة',
    'return_line' => 'مرتجع بند مبيعات',
    'update_status' => 'تحديث حالة',
    'backup' => 'نسخ احتياطي',
];

// Helper for Arabic translation of entity types
$entity_labels = [
    'product' => 'منتج',
    'settings' => 'إعدادات النظام',
    'inventory' => 'مخزون / مستودع',
    'customer' => 'عميل',
    'invoice' => 'فاتورة مبيعات',
    'transfer' => 'تحويل مخزني',
    'invoice_line' => 'بند فاتورة مبيعات',
    'online_order' => 'طلب أونلاين',
    'database' => 'قاعدة البيانات',
    'user' => 'موظف / مستخدم',
];

// Function to return custom styled badges inline
function get_action_badge_markup(string $action) {
    global $action_labels;
    $label = $action_labels[$action] ?? $action;
    $translatedLabel = __($label);
    
    switch ($action) {
        case 'login':
        case 'logout':
            $style = 'background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569;';
            break;
        case 'create':
        case 'receive':
            $style = 'background: #dcfce7; border: 1px solid #bbf7d0; color: #15803d;';
            break;
        case 'update':
        case 'update_status':
            $style = 'background: #dbeafe; border: 1px solid #bfdbfe; color: #1d4ed8;';
            break;
        case 'delete':
        case 'deactivate':
            $style = 'background: #fee2e2; border: 1px solid #fecaca; color: #b91c1c;';
            break;
        case 'adjust':
        case 'return_line':
            $style = 'background: #fef3c7; border: 1px solid #fde68a; color: #b45309;';
            break;
        case 'backup':
            $style = 'background: #f3e8ff; border: 1px solid #e9d5ff; color: #6b21a8;';
            break;
        default:
            $style = 'background: #f4f4f5; border: 1px solid #e4e4e7; color: #71717a;';
            break;
    }
    
    return '<span style="' . $style . ' padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; display: inline-block;">' . htmlspecialchars($translatedLabel, ENT_QUOTES, 'UTF-8') . '</span>';
}
?>

<section class="page-head">
    <h2><?= e(__('سجل أنشطة وعمليات النظام')) ?></h2>
    <p><?= e(__('تتبع وتحليل كافة العمليات الحساسة والتغييرات التي تمت بالنظام من مبيعات، تحديثات المنتجات، تسويات المخزون، الحضور، وتغيير الصلاحيات.')) ?></p>
</section>

<!-- Filter Toolbar (Form GET) -->
<form class="panel toolbar" method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; align-items: end; margin-bottom: 20px; background: var(--surface); border-radius: 12px; box-shadow: var(--shadow);">
    <input type="hidden" name="r" value="audit">
    
    <label style="display: flex; flex-direction: column; gap: 4px; font-weight: 600; font-size: 12px; color: var(--muted);">
        <?= e(__('بحث في التفاصيل والكيانات')) ?>
        <input type="text" name="q" id="audit-search" value="<?= e($search) ?>" placeholder="<?= e(__('بحث عن كلمة مفتاحية...')) ?>" style="padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit; font-size: 13px; background: var(--surface-soft); outline: none;">
    </label>
    
    <label style="display: flex; flex-direction: column; gap: 4px; font-weight: 600; font-size: 12px; color: var(--muted);">
        <?= e(__('الموظف / المستخدم')) ?>
        <select name="user_id" id="audit-user" style="padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit; font-size: 13px; background: var(--surface-soft); outline: none;">
            <option value=""><?= e(__('كل المستخدمين')) ?></option>
            <?php foreach ($users as $u): ?>
                <option value="<?= e($u['id']) ?>" <?= $user_id === (string)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?> (<?= e(__($u['role_name'])) ?>)</option>
            <?php endforeach; ?>
        </select>
    </label>
    
    <label style="display: flex; flex-direction: column; gap: 4px; font-weight: 600; font-size: 12px; color: var(--muted);">
        <?= e(__('نوع العملية')) ?>
        <select name="action_type" id="audit-action" style="padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit; font-size: 13px; background: var(--surface-soft); outline: none;">
            <option value=""><?= e(__('كل العمليات')) ?></option>
            <?php foreach ($action_labels as $key => $lbl): ?>
                <option value="<?= e($key) ?>" <?= $action_type === $key ? 'selected' : '' ?>><?= e(__($lbl)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    
    <label style="display: flex; flex-direction: column; gap: 4px; font-weight: 600; font-size: 12px; color: var(--muted);">
        <?= e(__('من تاريخ')) ?>
        <input type="date" name="start_date" value="<?= e($start_date) ?>" style="padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit; font-size: 13px; background: var(--surface-soft); outline: none;">
    </label>
    
    <label style="display: flex; flex-direction: column; gap: 4px; font-weight: 600; font-size: 12px; color: var(--muted);">
        <?= e(__('إلى تاريخ')) ?>
        <input type="date" name="end_date" value="<?= e($end_date) ?>" style="padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-family: inherit; font-size: 13px; background: var(--surface-soft); outline: none;">
    </label>
    
    <div style="display: flex; gap: 8px; justify-content: flex-end;">
        <button class="btn primary" style="flex: 1; padding: 10px 16px; border-radius: 8px; font-weight: bold; background: var(--primary); border: 0; color: #fff; cursor: pointer; transition: all 0.2s ease;"><?= e(__('تصفية')) ?></button>
        <a class="btn" href="index.php?r=audit" style="text-align: center; display: inline-flex; align-items: center; justify-content: center; padding: 8px 14px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface-soft); color: var(--ink); text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.2s ease;"><?= e(__('إعادة ضبط')) ?></a>
    </div>
</form>

<!-- Logs Panel -->
<div class="panel" style="background: var(--surface); border-radius: 12px; box-shadow: var(--shadow); padding: 20px; overflow-x: auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0; font-size: 15px; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 6px;">
            <span>📜</span> <?= e(__('سجل العمليات الأخير')) ?>
        </h3>
        <span class="badge" style="background: var(--primary-soft); color: var(--primary); border: 1px solid var(--line); padding: 4px 10px; border-radius: 8px; font-weight: bold; font-size: 12px;" id="logs-count">
            <?= e(__('تم العثور على')) ?> <?= count($rows) ?> <?= e(__('سجل')) ?>
        </span>
    </div>
    
    <table style="width: 100%; border-collapse: collapse; text-align: right;">
        <thead>
            <tr style="border-bottom: 2px solid var(--line); color: var(--muted); font-weight: 700;">
                <th style="padding: 12px 10px; font-size: 13px;"><?= e(__('التاريخ والوقت')) ?></th>
                <th style="padding: 12px 10px; font-size: 13px;"><?= e(__('المستخدم / الموظف')) ?></th>
                <th style="padding: 12px 10px; font-size: 13px;"><?= e(__('نوع العملية')) ?></th>
                <th style="padding: 12px 10px; font-size: 13px;"><?= e(__('الكيان المتأثر')) ?></th>
                <th style="padding: 12px 10px; font-size: 13px;"><?= e(__('رقم الكيان')) ?></th>
                <th style="padding: 12px 10px; font-size: 13px;"><?= e(__('تفاصيل العملية')) ?></th>
            </tr>
        </thead>
        <tbody id="audit-table-body">
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="6" style="padding: 30px; text-align: center; color: var(--muted); font-size: 14px;">
                        <?= e(__('لا توجد سجلات مطابقة للبحث أو الفلترة المحددة.')) ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): 
                    $entityLabel = $entity_labels[$row['entity_type']] ?? $row['entity_type'];
                    $formattedDate = format_datetime($row['created_at']);
                ?>
                    <tr style="border-bottom: 1px solid var(--line); transition: background 0.15s ease;" 
                        class="audit-row"
                        data-user-id="<?= e($row['user_id']) ?>"
                        data-action="<?= e($row['action']) ?>"
                        onmouseover="this.style.background='var(--surface-soft)'"
                        onmouseout="this.style.background='transparent'">
                        
                        <td style="padding: 12px 10px; font-family: monospace; color: var(--muted); font-size: 12.5px;" dir="ltr"><?= e($formattedDate) ?></td>
                        <td style="padding: 12px 10px; font-weight: 600; color: var(--ink);">
                            <?php if ($row['user_id']): ?>
                                <span style="display: inline-flex; align-items: center; gap: 4px;">
                                    👤 <?= e($row['user_name']) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--muted); display: inline-flex; align-items: center; gap: 4px;">
                                    ⚙️ <?= e(__('سيستم تلقائي')) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px 10px;"><?= get_action_badge_markup($row['action']) ?></td>
                        <td class="col-entity" style="padding: 12px 10px; font-weight: 500; color: var(--ink);"><?= e(__($entityLabel)) ?></td>
                        <td style="padding: 12px 10px; font-family: monospace; color: var(--muted); font-size: 13px;">
                            <?= $row['entity_id'] ? '#' . $row['entity_id'] : '-' ?>
                        </td>
                        <td class="col-details" style="padding: 12px 10px; color: var(--ink); max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($row['details']) ?>">
                            <?= e($row['details'] ?: '-') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('audit-search');
    const userSelect = document.getElementById('audit-user');
    const actionSelect = document.getElementById('audit-action');
    const rows = document.querySelectorAll('#audit-table-body .audit-row');
    const logsCountBadge = document.getElementById('logs-count');
    const isEn = document.documentElement.lang === 'en';

    function filterTable() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedUser = userSelect.value;
        const selectedAction = actionSelect.value;
        let visibleCount = 0;

        rows.forEach(row => {
            const detailsText = row.querySelector('.col-details').textContent.toLowerCase();
            const entityText = row.querySelector('.col-entity').textContent.toLowerCase();
            const actionText = row.getAttribute('data-action');
            const userId = row.getAttribute('data-user-id');

            const matchesQuery = !query || detailsText.includes(query) || entityText.includes(query);
            const matchesUser = !selectedUser || userId === selectedUser;
            const matchesAction = !selectedAction || actionText === selectedAction;

            if (matchesQuery && matchesUser && matchesAction) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        if (logsCountBadge) {
            logsCountBadge.textContent = isEn 
                ? 'Showing ' + visibleCount + ' filtered records' 
                : 'تم عرض ' + visibleCount + ' سجل مفلتر';
        }
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (userSelect) userSelect.addEventListener('change', filterTable);
    if (actionSelect) actionSelect.addEventListener('change', filterTable);
});
</script>
