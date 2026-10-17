<?php

declare(strict_types=1);

require_login();
if (!has_permission('customers_view')) {
    http_response_code(403);
    echo '<div class="alert danger">غير مصرح لك بالوصول لهذه الصفحة.</div>';
    return;
}

$currentUser = current_user();
$userLocationId = current_user_location_id();
$locations = all_locations();

$tab = trim((string) ($_GET['tab'] ?? 'pending'));
$allowedTabs = ['pending', 'contacted', 'all'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'pending';
}

$search = trim((string) ($_GET['q'] ?? ''));
$locationFilter = trim((string) ($_GET['location_id'] ?? ''));
$sourceFilter = trim((string) ($_GET['source'] ?? ''));

$filterLocationId = null;
if ($userLocationId !== null) {
    $filterLocationId = $userLocationId;
} elseif ($locationFilter !== '') {
    $filterLocationId = (int) $locationFilter;
}

$stats = get_customer_todo_stats($filterLocationId);

$filters = [
    'status' => $tab,
    'q' => $search,
    'location_id' => $filterLocationId,
    'source' => $sourceFilter,
];

$allRows = get_customer_todo_rows($filters, $filterLocationId);

// Pagination
$perPage = 30;
$totalCount = count($allRows);
$totalPages = max(1, (int) ceil($totalCount / $perPage));
$page = max(1, min((int) ($_GET['page'] ?? 1), $totalPages));
$pagedRows = array_slice($allRows, ($page - 1) * $perPage, $perPage);

$buildPaginationUrl = function (int $targetPage) use ($tab, $search, $locationFilter, $sourceFilter): string {
    $params = [
        'r' => 'customer_todo',
        'tab' => $tab,
    ];
    if ($search !== '') $params['q'] = $search;
    if ($locationFilter !== '') $params['location_id'] = $locationFilter;
    if ($sourceFilter !== '') $params['source'] = $sourceFilter;
    if ($targetPage > 1) $params['page'] = $targetPage;
    return 'index.php?' . http_build_query($params);
};
?>

<style>
/* Customer To-Do List Styles */
.todo-hero {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(5, 150, 105, 0.02) 100%);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 16px;
    padding: 20px 24px;
    margin-bottom: 20px;
    position: relative;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
}
.todo-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}
.todo-stat-card {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    transition: transform 0.2s, box-shadow 0.2s;
}
.todo-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
}
.todo-stat-icon {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.todo-stat-card.total .todo-stat-icon { background: rgba(59, 130, 246, 0.12); color: #2563eb; }
.todo-stat-card.pending .todo-stat-icon { background: rgba(245, 158, 11, 0.12); color: #d97706; }
.todo-stat-card.contacted .todo-stat-icon { background: rgba(16, 185, 129, 0.12); color: #059669; }
.todo-stat-card.progress .todo-stat-icon { background: rgba(139, 92, 246, 0.12); color: #7c3aed; }

.todo-stat-num {
    font-size: 24px;
    font-weight: 800;
    line-height: 1.1;
    color: var(--ink);
}
.todo-stat-label {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--muted);
    margin-top: 3px;
}
.todo-progress-bar-wrapper {
    width: 100%;
    height: 8px;
    background: var(--line);
    border-radius: 10px;
    overflow: hidden;
    margin-top: 8px;
}
.todo-progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #10b981, #059669);
    border-radius: 10px;
    transition: width 0.4s ease;
}

/* Tabs */
.todo-tabs {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid var(--line);
    margin-bottom: 18px;
    overflow-x: auto;
    padding-bottom: 2px;
}
.todo-tab-btn {
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 700;
    color: var(--muted);
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.2s ease;
    white-space: nowrap;
}
.todo-tab-btn:hover {
    color: var(--primary);
}
.todo-tab-btn.active {
    color: #059669;
    border-bottom-color: #059669;
}
.todo-tab-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 800;
}
.todo-tab-btn.active .todo-tab-badge {
    background: #059669;
    color: #fff;
}
.todo-tab-btn:not(.active) .todo-tab-badge {
    background: var(--surface-soft, rgba(0,0,0,0.06));
    color: var(--muted);
}

/* Row states */
.row-contacted {
    background: rgba(16, 185, 129, 0.04) !important;
}
.row-pending {
    background: var(--surface);
}

/* Action toggle button */
.btn-toggle-todo {
    border-radius: 8px;
    padding: 6px 14px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    border: 1.5px solid;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
    user-select: none;
}
.btn-toggle-todo.is-pending {
    background: var(--surface);
    color: #b45309;
    border-color: #f59e0b;
}
.btn-toggle-todo.is-pending:hover {
    background: #10b981;
    color: #fff;
    border-color: #10b981;
    box-shadow: 0 2px 8px rgba(16,185,129,0.3);
}
.btn-toggle-todo.is-contacted {
    background: #059669;
    color: #fff;
    border-color: #047857;
    box-shadow: 0 2px 8px rgba(5,150,105,0.25);
}
.btn-toggle-todo.is-contacted:hover {
    background: #dc2626;
    border-color: #b91c1c;
    color: #fff;
}

/* WhatsApp Button */
.btn-whatsapp-direct {
    background: #25D366;
    color: #fff !important;
    font-weight: 700;
    font-size: 12px;
    padding: 5px 11px;
    border-radius: 8px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: transform 0.15s, background 0.15s, box-shadow 0.15s;
    box-shadow: 0 2px 6px rgba(37, 211, 102, 0.3);
    white-space: nowrap;
}
.btn-whatsapp-direct:hover {
    background: #128C7E;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(37, 211, 102, 0.4);
}

.note-preview {
    font-size: 11.5px;
    color: var(--muted);
    max-width: 220px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
}
.note-preview:hover {
    color: var(--ink);
    text-decoration: underline;
}
</style>

<!-- Top Hero & Overview -->
<section class="todo-hero">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
        <div>
            <h2 style="margin: 0; display: flex; align-items: center; gap: 10px; color: #065f46; font-size: 22px;">
                <span>📋</span> قائمة مهام ومتابعة العملاء (To-Do List)
            </h2>
            <p style="margin: 6px 0 0; color: #047857; font-size: 13.5px; font-weight: 500;">
                تابع وتواصل مع عملائك بسهولة، اضغط <strong>(صح)</strong> على كل عميل بعد محادثته، وتعرف على العملاء المتبقين فوراً.
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <a href="index.php?r=customers" class="btn" style="background: var(--surface); color: var(--ink); border: 1px solid var(--line); font-weight: 700;">
                👥 سجل العملاء الرئيسي
            </a>
            <?php if ($currentUser['role_code'] === 'admin' || $currentUser['role_code'] === 'branch_manager'): ?>
                <form method="post" class="inline" onsubmit="return confirm('هل أنت متأكد من إعادة ضبط قائمة المتابعة لجميع العملاء؟\nسيتم تحويل الجميع إلى (متبقي للاتصال) لبدء دورة جديدة.')">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn small" style="background: rgba(239, 68, 68, 0.1); color: #dc2626; border: 1px solid rgba(239, 68, 68, 0.3); font-weight: 700;" title="إعادة تعيين القائمة لبدء حملة اتصال جديدة">
                        ♻️ بدء دورة جديدة
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- KPI Stats Cards -->
<div class="todo-stats-grid">
    <div class="todo-stat-card total">
        <div class="todo-stat-icon">👥</div>
        <div>
            <div class="todo-stat-num" id="stat-total"><?= number_format($stats['total']) ?></div>
            <div class="todo-stat-label">إجمالي العملاء في القائمة</div>
        </div>
    </div>
    <div class="todo-stat-card pending">
        <div class="todo-stat-icon">⏳</div>
        <div>
            <div class="todo-stat-num" id="stat-pending" style="color: #d97706;"><?= number_format($stats['pending']) ?></div>
            <div class="todo-stat-label">العملاء المتبقية (فاضل مين)</div>
        </div>
    </div>
    <div class="todo-stat-card contacted">
        <div class="todo-stat-icon">✅</div>
        <div>
            <div class="todo-stat-num" id="stat-contacted" style="color: #059669;"><?= number_format($stats['contacted']) ?></div>
            <div class="todo-stat-label">تم التواصل معهم (خلصت مين)</div>
        </div>
    </div>
    <div class="todo-stat-card progress">
        <div class="todo-stat-icon">📈</div>
        <div style="flex: 1;">
            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                <div class="todo-stat-num" id="stat-percent" style="color: #7c3aed;"><?= $stats['percent'] ?>%</div>
                <span style="font-size: 11px; font-weight: 700; color: var(--muted);">نسبة الإنجاز</span>
            </div>
            <div class="todo-progress-bar-wrapper">
                <div class="todo-progress-bar-fill" id="stat-progress-bar" style="width: <?= $stats['percent'] ?>%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<div class="todo-tabs">
    <a href="index.php?r=customer_todo&tab=pending<?= $search !== '' ? '&q=' . urlencode($search) : '' ?><?= $locationFilter !== '' ? '&location_id=' . urlencode($locationFilter) : '' ?>" 
       class="todo-tab-btn <?= $tab === 'pending' ? 'active' : '' ?>">
        <span>⏳ العملاء المتبقية (فاضل مين)</span>
        <span class="todo-tab-badge"><?= number_format($stats['pending']) ?></span>
    </a>
    <a href="index.php?r=customer_todo&tab=contacted<?= $search !== '' ? '&q=' . urlencode($search) : '' ?><?= $locationFilter !== '' ? '&location_id=' . urlencode($locationFilter) : '' ?>" 
       class="todo-tab-btn <?= $tab === 'contacted' ? 'active' : '' ?>">
        <span>✅ تم التواصل معهم (خلصت مين)</span>
        <span class="todo-tab-badge"><?= number_format($stats['contacted']) ?></span>
    </a>
    <a href="index.php?r=customer_todo&tab=all<?= $search !== '' ? '&q=' . urlencode($search) : '' ?><?= $locationFilter !== '' ? '&location_id=' . urlencode($locationFilter) : '' ?>" 
       class="todo-tab-btn <?= $tab === 'all' ? 'active' : '' ?>">
        <span>📋 جميع العملاء</span>
        <span class="todo-tab-badge"><?= number_format($stats['total']) ?></span>
    </a>
</div>

<!-- Filters Panel -->
<form class="panel" method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px; align-items: end; margin-bottom: 18px;">
    <input type="hidden" name="r" value="customer_todo">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    
    <label>بحث بالاسم أو الهاتف
        <input name="q" value="<?= e($search) ?>" placeholder="اكتب اسم العميل أو الهاتف...">
    </label>

    <?php if ($userLocationId === null): ?>
        <label>الفرع
            <select name="location_id">
                <option value="">جميع الفروع</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= e($l['id']) ?>" <?= $locationFilter === (string)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    <?php endif; ?>

    <label>المصدر
        <select name="source">
            <option value="">كل المصادر</option>
            <option value="offline" <?= $sourceFilter === 'offline' ? 'selected' : '' ?>>أوفلاين (الفرع)</option>
            <option value="online" <?= $sourceFilter === 'online' ? 'selected' : '' ?>>أونلاين</option>
        </select>
    </label>

    <div style="display: flex; gap: 8px;">
        <button type="submit" class="btn primary" style="flex: 1;">تطبيق الفلتر</button>
        <a href="index.php?r=customer_todo&tab=<?= e($tab) ?>" class="btn" title="إعادة تعيين الفلاتر">إعادة ضبط</a>
    </div>
</form>

<!-- Main Table -->
<section class="panel">
    <div class="toolbar" style="justify-content: space-between; margin-bottom: 14px;">
        <div>
            <h3 style="margin: 0; font-size: 16px;">
                <?php if ($tab === 'pending'): ?>
                    ⏳ قائمة العملاء المتبقية للاتصال
                <?php elseif ($tab === 'contacted'): ?>
                    ✅ سجل العملاء الذين تم التواصل معهم
                <?php else: ?>
                    📋 السجل الشامل لعملاء المتابعة
                <?php endif; ?>
            </h3>
            <p class="muted" style="margin: 4px 0 0; font-size: 12px;">اضغط على علامة (صح) أو الزر بجوار العميل فور إتمام المكالمة معه لتحديث حالته مباشرة.</p>
        </div>
        <span class="badge" style="font-weight: 700;">المعروض <?= e(count($pagedRows)) ?> من <?= e($totalCount) ?></span>
    </div>

    <div class="table-responsive">
        <table id="todo-table">
            <thead>
                <tr>
                    <th style="width: 170px; text-align: center;">إتمام المتابعة (صح)</th>
                    <th>العميل</th>
                    <th>الهاتف والواتساب</th>
                    <th>الفرع</th>
                    <th>آخر فاتورة</th>
                    <th>حالة المتابعة</th>
                    <th>ملاحظات المكالمة</th>
                    <th>إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$pagedRows): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 32px 16px; color: var(--muted);">
                            <div style="font-size: 32px; margin-bottom: 8px;">🎉</div>
                            <div style="font-size: 15px; font-weight: 700;">لا توجد عملاء في هذا التبويب حالياً</div>
                            <div style="font-size: 12px; margin-top: 4px;">
                                <?= $tab === 'pending' ? 'رائع جداً! تم الانتهاء من التواصل مع جميع العملاء في هذه القائمة.' : 'لم يتم العثور على أي نتائج مطابقة للبحث أو الفلتر.' ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($pagedRows as $row): ?>
                    <?php 
                        $isContacted = ($row['followup_status'] === 'contacted');
                        $waPhone = clean_whatsapp_phone($row['phone']);
                        $greeting = 'أهلاً بك أستاذ ' . $row['name'] . ' 🌹 نتواصل معك من حمزة للعطور.';
                    ?>
                    <tr id="row-cust-<?= (int)$row['id'] ?>" class="<?= $isContacted ? 'row-contacted' : 'row-pending' ?>">
                        <td style="text-align: center; vertical-align: middle;">
                            <button type="button" 
                                    class="btn-toggle-todo <?= $isContacted ? 'is-contacted' : 'is-pending' ?>" 
                                    data-customer-id="<?= (int)$row['id'] ?>"
                                    onclick="toggleTodoStatus(<?= (int)$row['id'] ?>)"
                                    id="btn-status-<?= (int)$row['id'] ?>"
                                    title="<?= $isContacted ? 'تم التواصل بنجاح - اضغط للإلغاء' : 'اضغط لوضع علامة (صح) وإتمام التواصل' ?>">
                                <span class="status-icon"><?= $isContacted ? '✅ تم التواصل' : '⚪ تم الاتصال؟' ?></span>
                            </button>
                        </td>
                        <td>
                            <div>
                                <a href="index.php?r=customer_view&id=<?= (int)$row['id'] ?>" style="font-weight: 800; font-size: 14.5px; color: var(--ink); text-decoration: none;">
                                    <?= e($row['name']) ?>
                                </a>
                                <?php if (!empty($row['open_debt']) && (float)$row['open_debt'] > 0): ?>
                                    <span class="badge" style="background: rgba(239, 68, 68, 0.15); color: #dc2626; font-size: 10px; margin-right: 4px;" title="عليه مديونية مفتوحة">
                                        دين: <?= money((float)$row['open_debt']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <small class="muted" style="font-size: 11px;">مضاف: <?= e(date('d-m-Y', strtotime($row['created_at']))) ?></small>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                <span dir="ltr" style="font-weight: 700; font-size: 13.5px; color: var(--ink);"><?= e($row['phone'] ?: '-') ?></span>
                                <?php if ($waPhone): ?>
                                    <a href="https://wa.me/<?= e($waPhone) ?>?text=<?= urlencode($greeting) ?>" 
                                       target="_blank" 
                                       class="btn-whatsapp-direct" 
                                       onclick="onWhatsAppClick(<?= (int)$row['id'] ?>)"
                                       title="فتح محادثة واتساب ويب / الموبايل مباشرة">
                                        <span>💬 واتساب</span>
                                    </a>
                                    <a href="tel:<?= e($row['phone']) ?>" 
                                       class="btn small" 
                                       style="padding: 4px 8px; border-radius: 6px; border: 1px solid var(--line); font-size: 11px;" 
                                       title="اتصال هاتفي">
                                        📞
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge" style="background: var(--surface-soft); font-size: 11.5px;"><?= e($row['location_name'] ?? 'غير محدد') ?></span>
                        </td>
                        <td>
                            <?php if ($row['last_invoice_date']): ?>
                                <span style="font-weight: 600; font-size: 12px;"><?= e(date('d-m-Y', strtotime($row['last_invoice_date']))) ?></span>
                                <div style="font-size: 10.5px; color: var(--muted);">(<?= (int)$row['invoices_count'] ?> فواتير)</div>
                            <?php else: ?>
                                <span class="muted" style="font-size: 12px;">بدون فواتير</span>
                            <?php endif; ?>
                        </td>
                        <td id="status-info-<?= (int)$row['id'] ?>">
                            <?php if ($isContacted): ?>
                                <span style="color: #059669; font-weight: 800; font-size: 12px; display: block;">✅ تم التواصل</span>
                                <small class="muted" style="font-size: 10.5px;">
                                    <?= e($row['contacted_by_name'] ? ($row['contacted_by_name'] . ' | ') : '') ?>
                                    <?= e($row['contacted_at'] ? date('d-m H:i', strtotime($row['contacted_at'])) : '') ?>
                                </small>
                            <?php else: ?>
                                <span style="color: #d97706; font-weight: 700; font-size: 12px;">⏳ بانتظار الاتصال</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <div id="notes-text-<?= (int)$row['id'] ?>" class="note-preview" onclick="openNoteModal(<?= (int)$row['id'] ?>, '<?= e(addslashes($row['name'])) ?>')" title="انقر لتعديل الملاحظة">
                                    <?= e($row['followup_notes'] ?: '➕ إضافة ملاحظة...') ?>
                                </div>
                                <button type="button" class="btn small" onclick="openNoteModal(<?= (int)$row['id'] ?>, '<?= e(addslashes($row['name'])) ?>')" style="padding: 2px 6px; font-size: 10px; border-radius: 4px;" title="كتابة ملاحظة المكالمة">
                                    ✏️
                                </button>
                            </div>
                        </td>
                        <td>
                            <a class="btn small" href="index.php?r=customer_view&id=<?= (int)$row['id'] ?>" title="عرض ملف العميل" style="padding: 4px 8px; font-size: 11px;">
                                👤 الملف
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="toolbar" style="justify-content: space-between; margin-top: 16px; border-top: 1px solid var(--line); padding-top: 12px;">
            <span class="badge">صفحة <?= e($page) ?> من <?= e($totalPages) ?> - الإجمالي <?= e($totalCount) ?> عميل</span>
            <div class="actions">
                <?php if ($page > 1): ?>
                    <a class="btn small" href="<?= e($buildPaginationUrl($page - 1)) ?>">السابق</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn small" href="<?= e($buildPaginationUrl($page + 1)) ?>">التالي</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<!-- Quick Notes Modal -->
<div id="todoNoteModal" style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.5); backdrop-filter: blur(3px); align-items: center; justify-content: center; padding: 16px;">
    <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 16px; max-width: 480px; width: 100%; padding: 22px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 style="margin: 0; font-size: 16px; color: var(--primary);" id="noteModalTitle">ملاحظة مكالمة العميل</h3>
            <button type="button" onclick="closeNoteModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--muted);">&times;</button>
        </div>
        <p style="font-size: 12.5px; color: var(--muted); margin: 0 0 12px;">سجل ملاحظات المكالمة مثل: (طلب عرض، مهتم بتركيبة محددة، سيزور الفرع، لم يرد، الخ...)</p>
        
        <input type="hidden" id="modalCustomerId" value="">
        <textarea id="modalNoteText" rows="4" style="width: 100%; border: 1.5px solid var(--line); border-radius: 10px; padding: 10px; font-family: inherit; font-size: 13.5px; outline: none;" placeholder="اكتب الملاحظة هنا..."></textarea>
        
        <!-- Quick templates -->
        <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 10px; margin-bottom: 16px;">
            <button type="button" class="btn small" onclick="setQuickNote('تم التواصل وطلب زيارة الفرع')" style="font-size: 11px;">🏬 سيزور الفرع</button>
            <button type="button" class="btn small" onclick="setQuickNote('مهتم بعروض التخفيضات والباكدجات')" style="font-size: 11px;">🎁 مهتم بالعروض</button>
            <button type="button" class="btn small" onclick="setQuickNote('تم إرسال العروض عبر الواتساب')" style="font-size: 11px;">💬 أرسلت له واتس</button>
            <button type="button" class="btn small" onclick="setQuickNote('العميل لم يقم بالرد على المكالمة')" style="font-size: 11px;">📵 لم يرد</button>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn" onclick="closeNoteModal()">إلغاء</button>
            <button type="button" class="btn primary" onclick="saveNoteModal()" id="btnSaveNote">حفظ الملاحظة ✅</button>
        </div>
    </div>
</div>

<script>
// Interactive To-Do Toggle Logic
async function toggleTodoStatus(customerId) {
    const btn = document.getElementById(`btn-status-${customerId}`);
    const row = document.getElementById(`row-cust-${customerId}`);
    const infoCell = document.getElementById(`status-info-${customerId}`);
    if (!btn) return;

    btn.disabled = true;
    btn.style.opacity = '0.6';

    try {
        const formData = new FormData();
        formData.append('customer_id', customerId);

        const response = await fetch('index.php?r=api_toggle_customer_todo', {
            method: 'POST',
            body: formData
        });

        const res = await response.json();
        if (!res.success) {
            alert(res.message || 'حدث خطأ أثناء تحديث الحالة.');
            return;
        }

        const data = res.data;
        const isContacted = (data.status === 'contacted');

        // Update button
        btn.className = `btn-toggle-todo ${isContacted ? 'is-contacted' : 'is-pending'}`;
        btn.title = isContacted ? 'تم التواصل بنجاح - اضغط للإلغاء' : 'اضغط لوضع علامة (صح) وإتمام التواصل';
        btn.querySelector('.status-icon').textContent = isContacted ? '✅ تم التواصل' : '⚪ تم الاتصال؟';

        // Update row style
        if (row) {
            row.className = isContacted ? 'row-contacted' : 'row-pending';
        }

        // Update status cell
        if (infoCell) {
            if (isContacted) {
                infoCell.innerHTML = `
                    <span style="color: #059669; font-weight: 800; font-size: 12px; display: block;">✅ تم التواصل</span>
                    <small class="muted" style="font-size: 10.5px;">${data.contacted_by_name ? data.contacted_by_name + ' | ' : ''}${data.contacted_at}</small>
                `;
            } else {
                infoCell.innerHTML = `<span style="color: #d97706; font-weight: 700; font-size: 12px;">⏳ بانتظار الاتصال</span>`;
            }
        }

        // Live update stats cards
        if (data.stats) {
            const elTotal = document.getElementById('stat-total');
            const elPending = document.getElementById('stat-pending');
            const elContacted = document.getElementById('stat-contacted');
            const elPercent = document.getElementById('stat-percent');
            const elBar = document.getElementById('stat-progress-bar');

            if (elTotal) elTotal.textContent = Number(data.stats.total).toLocaleString('en-US');
            if (elPending) elPending.textContent = Number(data.stats.pending).toLocaleString('en-US');
            if (elContacted) elContacted.textContent = Number(data.stats.contacted).toLocaleString('en-US');
            if (elPercent) elPercent.textContent = data.stats.percent + '%';
            if (elBar) elBar.style.width = data.stats.percent + '%';
        }

        // If currently in 'pending' tab and marked contacted, give smooth fade effect
        const currentTab = '<?= e($tab) ?>';
        if (currentTab === 'pending' && isContacted) {
            row.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            row.style.opacity = '0.35';
        } else if (currentTab === 'contacted' && !isContacted) {
            row.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
            row.style.opacity = '0.35';
        }

    } catch (err) {
        console.error(err);
        alert('تعذر الاتصال بالخادم.');
    } finally {
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

// When WhatsApp link is clicked, ask if user wants to mark customer as contacted automatically
function onWhatsAppClick(customerId) {
    const btn = document.getElementById(`btn-status-${customerId}`);
    if (btn && btn.classList.contains('is-pending')) {
        setTimeout(() => {
            if (confirm('هل تريد تسجيل هذا العميل كـ (تم التواصل معه بنجاح) الآن؟')) {
                toggleTodoStatus(customerId);
            }
        }, 800);
    }
}

// Notes Modal Logic
function openNoteModal(customerId, customerName) {
    document.getElementById('modalCustomerId').value = customerId;
    document.getElementById('noteModalTitle').textContent = `ملاحظة مكالمة: ${customerName}`;
    const curText = document.getElementById(`notes-text-${customerId}`)?.textContent?.trim() || '';
    document.getElementById('modalNoteText').value = (curText === '➕ إضافة ملاحظة...') ? '' : curText;
    document.getElementById('todoNoteModal').style.display = 'flex';
    document.getElementById('modalNoteText').focus();
}

function closeNoteModal() {
    document.getElementById('todoNoteModal').style.display = 'none';
}

function setQuickNote(text) {
    const txtArea = document.getElementById('modalNoteText');
    if (txtArea) {
        txtArea.value = txtArea.value ? (txtArea.value + ' - ' + text) : text;
    }
}

async function saveNoteModal() {
    const customerId = document.getElementById('modalCustomerId').value;
    const notes = document.getElementById('modalNoteText').value.trim();
    const btnSave = document.getElementById('btnSaveNote');

    if (!customerId) return;
    btnSave.disabled = true;

    try {
        const formData = new FormData();
        formData.append('customer_id', customerId);
        formData.append('notes', notes);

        const response = await fetch('index.php?r=api_toggle_customer_todo', {
            method: 'POST',
            body: formData
        });

        const res = await response.json();
        if (res.success) {
            const preview = document.getElementById(`notes-text-${customerId}`);
            if (preview) {
                preview.textContent = notes || '➕ إضافة ملاحظة...';
            }
            closeNoteModal();
        } else {
            alert(res.message || 'حدث خطأ أثناء حفظ الملاحظة.');
        }
    } catch (e) {
        alert('تعذر الحفظ.');
    } finally {
        btnSave.disabled = false;
    }
}

// Close modal when clicking outside
document.getElementById('todoNoteModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeNoteModal();
});
</script>
