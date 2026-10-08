<?php
$user = require_login();
$userLocationId = current_user_location_id();
$locations = attendance_locations();
$allUsers = array_values(array_filter(all_users(), fn($u) => $u['role_code'] !== 'admin'));

if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn($l) => (int)$l['id'] === $userLocationId));
    $allUsers = array_values(array_filter($allUsers, fn($u) => (int)$u['location_id'] === $userLocationId));
}

// Build filters from GET
$filters = [];
$selectedUserId = !empty($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$selectedLocId = !empty($_GET['location_id']) ? (int)$_GET['location_id'] : null;
$selectedAction = $_GET['action'] ?? '';
$selectedSource = $_GET['source'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

if ($userLocationId !== null) {
    $selectedLocId = $userLocationId;
}

// Non-admin can only see their own records
if (!has_permission('users_permissions')) {
    $selectedUserId = (int)$user['id'];
}

if ($selectedUserId) $filters['user_id'] = $selectedUserId;
if ($selectedLocId) $filters['location_id'] = $selectedLocId;
if ($selectedAction) $filters['action'] = $selectedAction;
if ($selectedSource) $filters['source'] = $selectedSource;
if ($dateFrom) $filters['date_from'] = $dateFrom;
if ($dateTo) $filters['date_to'] = $dateTo;

$rows = attendance_rows_filtered($filters);

// Pagination
$perPage = 30;
$total = count($rows);
$pages = max(1, (int)ceil($total / $perPage));
$page = max(1, (int)($_GET['page'] ?? 1));
$page = min($page, $pages);
$pagedRows = array_slice($rows, ($page - 1) * $perPage, $perPage);

$actionOptions = ['' => 'الكل', 'check_in' => 'حضور', 'check_out' => 'انصراف'];
$sourceOptions = ['' => 'الكل', 'qr' => 'QR', 'manual' => 'يدوي'];
?>
<section class="page-head">
    <div>
        <h2>سجل الحضور والانصراف</h2>
        <p>استعراض وتصفية جميع سجلات الحضور والانصراف.</p>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a class="btn" href="index.php?r=attendance">📷 تسجيل بالكاميرا</a>
        <?php if (has_permission('attendance')): ?>
            <button type="button" class="btn primary" onclick="openAddAttendanceModal()">➕ إضافة يدوي</button>
        <?php endif; ?>
    </div>
</section>

<div class="panel toolbar" style="background:#ffffff; border:1px solid var(--line); border-radius:12px; padding:16px;">
    <form method="get" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; width:100%; margin:0;">
        <input type="hidden" name="r" value="attendance_log">
        
        <?php if ($userLocationId === null): ?>
        <label style="display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--ink);">الفروع
            <select name="location_id" style="min-width:140px; margin:0;">
                <option value="">كل الفروع</option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= e($l['id']) ?>" <?= $selectedLocId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        
        <?php if (has_permission('users_permissions')): ?>
        <label style="display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--ink);">الموظفين
            <select name="user_id" style="min-width:160px; margin:0;">
                <option value="">كل الموظفين</option>
                <?php foreach ($allUsers as $u): ?>
                    <option value="<?= e($u['id']) ?>" <?= $selectedUserId === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        
        <label style="display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--ink);">العملية
            <select name="action" style="min-width:110px; margin:0;">
                <?php foreach ($actionOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $selectedAction === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <label style="display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--ink);">المصدر
            <select name="source" style="min-width:110px; margin:0;">
                <?php foreach ($sourceOptions as $val => $label): ?>
                    <option value="<?= e($val) ?>" <?= $selectedSource === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        
        <label style="display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--ink);">من تاريخ
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" style="max-width:140px; margin:0;" placeholder="dd/mm/yyyy">
        </label>
        
        <label style="display:flex; flex-direction:column; gap:4px; font-size:12px; font-weight:700; color:var(--ink);">إلى تاريخ
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" style="max-width:140px; margin:0;" placeholder="dd/mm/yyyy">
        </label>
        
        <div style="display:flex; gap:8px; margin-bottom: 2px;">
            <button class="btn primary small" style="height:36px; padding:0 16px;">تصفية</button>
            <a class="btn small" href="index.php?r=attendance_log" style="height:36px; display:inline-flex; align-items:center; justify-content:center;">إعادة ضبط</a>
        </div>
        
        <span class="badge" style="margin-right:auto; margin-bottom:8px; font-size:12px; padding:6px 12px;"><?= e($total) ?> سجل</span>
    </form>
</div>

<div class="panel" style="padding-bottom:0;">
    <div style="overflow-x:auto;">
    <table style="width:100%; border-collapse:collapse; min-width:800px;">
        <thead>
            <tr style="border-bottom:2px solid var(--line); text-align:right; background:var(--surface-soft);">
                <th style="padding:10px 12px;">التاريخ والوقت</th>
                <th style="padding:10px 12px;">الموظف</th>
                <th style="padding:10px 12px;">الموقع</th>
                <th style="padding:10px 12px;">العملية</th>
                <th style="padding:10px 12px;">مدة العمل</th>
                <th style="padding:10px 12px;">المصدر</th>
                <th style="padding:10px 12px;">الموقع الجغرافي</th>
                <?php if (has_permission('users_permissions')): ?>
                    <th style="padding:10px 12px; text-align:center;">الإجراءات</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!$pagedRows): ?>
                <tr><td colspan="<?= has_permission('users_permissions') ? 8 : 7 ?>" class="muted" style="text-align:center; padding:24px;">لا توجد سجلات مطابقة للفلاتر المحددة.</td></tr>
            <?php endif; ?>
            <?php foreach ($pagedRows as $a): ?>
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:10px 12px;"><?= format_datetime($a['created_at']) ?></td>
                    <td style="padding:10px 12px;"><strong><?= e($a['user_name']) ?></strong></td>
                    <td style="padding:10px 12px;"><?= e($a['location_name']) ?></td>
                    <td style="padding:10px 12px;">
                        <span class="badge" style="<?= $a['action'] === 'check_in' ? 'background:#e7f6eb;color:#16803d;' : 'background:#fff2cf;color:#b76a00;' ?>">
                            <?= $a['action'] === 'check_in' ? 'حضور' : 'انصراف' ?>
                        </span>
                    </td>
                    <td style="padding:10px 12px;">
                        <?php
                        $durationText = '-';
                        if ($a['action'] === 'check_out' && !empty($a['matching_check_in'])) {
                            $in = new DateTime($a['matching_check_in']);
                            $out = new DateTime($a['created_at']);
                            $diff = $in->diff($out);
                            $hours = $diff->h + ($diff->days * 24);
                            $minutes = $diff->i;
                            $durationText = "";
                            if ($hours > 0) $durationText .= $hours . " ساعة ";
                            if ($minutes > 0 || $hours === 0) $durationText .= $minutes . " دقيقة";
                        }
                        echo e($durationText);
                        ?>
                    </td>
                    <td style="padding:10px 12px;"><span class="chip"><?= e($a['source'] === 'qr' ? 'QR' : 'يدوي') ?></span></td>
                    <td style="padding:10px 12px;">
                        <?php if ($a['latitude'] && $a['longitude']): ?>
                            <a href="https://maps.google.com/?q=<?= e($a['latitude']) ?>,<?= e($a['longitude']) ?>" target="_blank" style="color:var(--primary); font-weight:700;">📍</a>
                        <?php else: ?>
                            <span class="muted">-</span>
                        <?php endif; ?>
                    </td>
                    <?php if (has_permission('users_permissions')): ?>
                        <td style="padding:10px 12px; text-align:center; white-space:nowrap;">
                            <button type="button" class="btn small" onclick="openEditAttendance(<?= e(json_encode([
                                'id' => $a['id'],
                                'user_id' => $a['user_id'],
                                'user_name' => $a['user_name'],
                                'action' => $a['action'],
                                'date' => date('Y-m-d', strtotime($a['created_at'])),
                                'time' => date('H:i', strtotime($a['created_at'])),
                                'notes' => $a['notes']
                            ])) ?>)">تعديل</button>
                            <form method="post" class="inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل؟')">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_attendance">
                                <input type="hidden" name="attendance_id" value="<?= e($a['id']) ?>">
                                <button class="btn small danger">حذف</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    
    <?php if ($pages > 1): ?>
    <div class="toolbar" style="justify-content:space-between; margin-top:12px;">
        <span class="badge">صفحة <?= e($page) ?> من <?= e($pages) ?></span>
        <div class="actions">
            <?php if ($page > 1): ?>
                <a class="btn small" href="index.php?r=attendance_log&page=<?= e($page - 1) ?><?= $selectedUserId ? '&user_id=' . e($selectedUserId) : '' ?><?= $selectedLocId ? '&location_id=' . e($selectedLocId) : '' ?><?= $selectedAction ? '&action=' . e($selectedAction) : '' ?><?= $selectedSource ? '&source=' . e($selectedSource) : '' ?><?= $dateFrom ? '&date_from=' . e($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . e($dateTo) : '' ?>">السابق</a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
                <a class="btn small" href="index.php?r=attendance_log&page=<?= e($page + 1) ?><?= $selectedUserId ? '&user_id=' . e($selectedUserId) : '' ?><?= $selectedLocId ? '&location_id=' . e($selectedLocId) : '' ?><?= $selectedAction ? '&action=' . e($selectedAction) : '' ?><?= $selectedSource ? '&source=' . e($selectedSource) : '' ?><?= $dateFrom ? '&date_from=' . e($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . e($dateTo) : '' ?>">التالي</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Manual Attendance Modal -->
<div id="add-attendance-modal" class="modal">
    <div class="modal-content" style="border-radius: 16px; padding: 24px; max-width: 420px;">
        <span class="close-modal" onclick="closeAddAttendanceModal()" style="float: left; cursor: pointer; font-size: 24px;">&times;</span>
        <h3 style="margin-top: 0; color: var(--primary); font-weight: 700; border-bottom: 1.5px solid var(--line); padding-bottom: 8px;">➕ إضافة حضور يدوي</h3>
        <p style="margin: 0 0 16px; font-size: 13px; color: var(--muted);">تسجيل حضور أو انصراف موظف يدوياً بدون مسح QR.</p>

        <form method="post" action="index.php?r=attendance">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <!-- no "action" field → falls into add_attendance else-branch in router -->

            <div style="display:flex; flex-direction:column; gap:14px;">

                <label style="display:block; font-weight:600;">الموظف
                    <select name="user_id" required style="margin-top:6px; width:100%;">
                        <option value="">— اختر الموظف —</option>
                        <?php foreach ($allUsers as $u): ?>
                            <option value="<?= e($u['id']) ?>"><?= e($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label style="display:block; font-weight:600;">العملية
                    <select name="action" required style="margin-top:6px; width:100%;">
                        <option value="check_in">🟢 حضور (Check-in)</option>
                        <option value="check_out">🔴 انصراف (Check-out)</option>
                    </select>
                </label>

                <label style="display:block; font-weight:600;">التاريخ
                    <input type="date" name="attendance_date" required
                           value="<?= date('Y-m-d') ?>"
                           style="margin-top:6px; width:100%;">
                </label>

                <label style="display:block; font-weight:600;">الوقت
                    <input type="time" name="attendance_time" required
                           value="<?= date('H:i') ?>"
                           style="margin-top:6px; width:100%;">
                </label>

                <label style="display:block; font-weight:600;">ملاحظة <span style="font-weight:400; color:var(--muted);">(اختياري)</span>
                    <input name="notes" placeholder="سبب التسجيل اليدوي..." style="margin-top:6px; width:100%;">
                </label>
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:20px;">
                <button type="button" class="btn" onclick="closeAddAttendanceModal()">إلغاء</button>
                <button type="submit" class="btn primary">✅ حفظ السجل</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div id="edit-attendance-modal" class="modal">
    <div class="modal-content" style="border-radius: 16px; padding: 24px; max-width: 400px;">
        <span class="close-modal" onclick="closeEditAttendanceModal()" style="float: left; cursor: pointer; font-size: 24px;">&times;</span>
        <h3 style="margin-top: 0; color: var(--primary); font-weight: 700; border-bottom: 1.5px solid var(--line); padding-bottom: 8px;">تعديل سجل الحضور</h3>
        
        <form method="post" action="index.php?r=attendance">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_attendance">
            <input type="hidden" name="attendance_id" id="edit-attendance-id">
            
            <div style="margin: 16px 0; display:flex; flex-direction:column; gap:12px;">
                <p style="margin: 0; font-size: 13.5px; color: var(--muted);">
                    الموظف: <strong id="edit-user-name-text" style="color: var(--ink);"></strong>
                </p>
                
                <label style="display: block; font-weight: 600;">العملية
                    <select name="action" id="edit-attendance-action" required style="margin-top: 6px; width: 100%;">
                        <option value="check_in">حضور</option>
                        <option value="check_out">انصراف</option>
                    </select>
                </label>
                
                <label style="display: block; font-weight: 600;">اليوم (التاريخ)
                    <input type="date" name="attendance_date" id="edit-attendance-date" required style="margin-top: 6px; width: 100%;">
                </label>
                
                <label style="display: block; font-weight: 600;">الوقت
                    <input type="time" name="attendance_time" id="edit-attendance-time" required style="margin-top: 6px; width: 100%;">
                </label>
                
                <label style="display: block; font-weight: 600;">ملاحظة
                    <input name="notes" id="edit-attendance-notes" placeholder="ملاحظات..." style="margin-top: 6px; width: 100%;">
                </label>
            </div>
            
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" class="btn" onclick="closeEditAttendanceModal()">إلغاء</button>
                <button type="submit" class="btn primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Add Modal ─────────────────────────────────────
function openAddAttendanceModal() {
    document.getElementById('add-attendance-modal').classList.add('open');
}
function closeAddAttendanceModal() {
    document.getElementById('add-attendance-modal').classList.remove('open');
}

// ── Edit Modal ─────────────────────────────────────
function openEditAttendance(data) {
    document.getElementById('edit-attendance-id').value = data.id;
    document.getElementById('edit-user-name-text').textContent = data.user_name;
    document.getElementById('edit-attendance-action').value = data.action;
    document.getElementById('edit-attendance-date').value = data.date;
    document.getElementById('edit-attendance-time').value = data.time;
    document.getElementById('edit-attendance-notes').value = data.notes || '';
    document.getElementById('edit-attendance-modal').classList.add('open');
}
function closeEditAttendanceModal() {
    document.getElementById('edit-attendance-modal').classList.remove('open');
}

// ── Close on backdrop click ────────────────────────
window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('add-attendance-modal')) {
        closeAddAttendanceModal();
    }
    if (event.target === document.getElementById('edit-attendance-modal')) {
        closeEditAttendanceModal();
    }
});
</script>
