<?php
$users = all_users();
$roles = all_roles();
$locations = all_locations();
$tab = $_GET['tab'] ?? 'list';
$lang = current_lang();
$editId = (int) ($_GET['edit_id'] ?? 0);
$editUser = $editId > 0 ? find_user($editId) : null;
?>
<section class="page-head">
    <div>
        <h2><?= e(__('الموظفون والصلاحيات')) ?></h2>
        <p><?= e(__('حساب مستقل لكل موظف مع دور وموقع عمل، وضبط صلاحيات أدوار النظام.')) ?></p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a class="btn <?= $tab === 'list' ? 'primary' : '' ?>" href="index.php?r=users&tab=list"><?= e(__('الموظفون')) ?></a>
        <a class="btn <?= $tab === 'permissions' ? 'primary' : '' ?>" href="index.php?r=users&tab=permissions"><?= e(__('صلاحيات الأدوار')) ?></a>
    </div>
</section>

<?php if ($tab === 'permissions'): ?>
    <?php
    $editable_roles = [];
    foreach ($roles as $r) {
        if ($r['code'] !== 'admin') {
            $editable_roles[] = $r;
        }
    }
    
    $db = pdo();
    $perms_rows = $db->query('SELECT * FROM role_permissions')->fetchAll();
    $active_perms = [];
    foreach ($perms_rows as $row) {
        $active_perms[$row['role_id']][$row['permission_code']] = true;
    }
    
    $permission_definitions = [
        'المبيعات والكاشير' => [
            'pos' => 'الوصول لشاشة البيع الكاشير (POS)',
            'invoices' => 'عرض فواتير المبيعات وسجل المبيعات',
            'invoices_notes' => 'إضافة وتعديل الملاحظات على الفواتير اليومية',
            'returns' => 'تنفيذ وإدارة عمليات المرتجع',
        ],
        'إدارة المنتجات' => [
            'products_view' => 'عرض قائمة المنتجات المخزنة',
            'products_add' => 'إضافة منتجات جديدة للنظام',
            'products_edit' => 'تعديل بيانات وأسعار المنتجات',
            'products_delete' => 'تعطيل أو حذف المنتجات نهائياً',
        ],
        'إدارة التركيبات' => [
            'recipes_view' => 'عرض التركيبات والجرامات الافتراضية',
            'recipes_add' => 'إضافة تركيبات وإعدادات جرامات جديدة',
            'recipes_edit' => 'تعديل أو حذف إعدادات التركيبات الجاهزة',
        ],
        'المخزون والحركات' => [
            'inventory_view' => 'عرض أرصدة وحركات المخزن المركزي والفروع',
            'inventory_add' => 'إضافة واستلام بضاعة للمخزن المركزي',
            'inventory_adjust' => 'تسوية وتعديل رصيد المخزن يدوياً دفعة واحدة',
            'transfers_supply' => 'إنشاء واستلام توريد (من المخزن للفرع)',
            'transfers_branch' => 'إنشاء واستلام تحويل (بين الفروع)',
        ],
        'العملاء والديون' => [
            'customers_view' => 'عرض قائمة العملاء والديون المستحقة',
            'customers_add' => 'إضافة عميل جديد بالنظام',
            'customers_edit' => 'تعديل بيانات العملاء وسجل الملاحظات',
            'customers_pay_debt' => 'تحصيل مبالغ وسداد ديون العملاء',
        ],
        'الموظفون والرواتب' => [
            'users_view' => 'عرض قائمة الموظفين والأدوار',
            'users_add' => 'إنشاء حساب موظف جديد وتعيين موقعه',
            'users_permissions' => 'إدارة الأدوار وتعديل صلاحيات العمليات التفصيلية',
            'attendance' => 'تسجيل وعرض حضور وانصراف الموظفين وطباعة QR',
            'shifts_close' => 'إغلاق الشيفت والوردية اليومية',
            'shifts_record' => 'عرض سجل وتفاصيل إغلاق الشيفتات السابقة',
            'targets' => 'إدارة ومتابعة التارجت الشهري للفروع',
        ],
        'المالية والموردين' => [
            'expenses_view' => 'عرض سجل المصاريف والمستلزمات',
            'expenses_add' => 'تسجيل وإضافة المصاريف اليومية',
            'branch_cash_transfers' => 'تحويل خزينة الفروع واستلامها أو إلغاؤها',
            'manager_treasury' => 'عرض خزينة المدير وتأكيد استلام التحصيلات الإلكترونية وتحويلات الفروع',
            'financial_ledger' => 'عرض السجل المالي الموحد للحركات النقدية',
            'suppliers_view' => 'عرض قائمة الموردين والمديونيات',
            'suppliers_add' => 'إضافة مورد وفواتير مشتريات جديدة',
        ],
        'التقارير الإحصائية' => [
            'reports_sales_by_location' => 'تقرير مبيعات الفروع والمواقع',
            'reports_sales_by_payment' => 'تقرير طرق الدفع والخزينة',
            'reports_sales_by_user' => 'تقرير أداء مبيعات الموظفين',
            'reports_top_products' => 'تقرير المنتجات الأكثر مبيعاً',
            'reports_perfume_usage' => 'تقرير استهلاك الزيوت والعطور بالجرام',
            'reports_new_customers' => 'تقرير العملاء الجدد',
        ],
        'إعدادات النظام والتقنيات' => [
            'online_orders' => 'متابعة وإدارة طلبات الأونلاين والموقع الإلكتروني',
            'backup' => 'النسخ الاحتياطي لقاعدة البيانات وتنزيلها',
            'settings' => 'إعدادات النظام العامة',
            'audit' => 'عرض سجل العمليات والتحركات الحساسة بالنظام',
        ]
    ];
    ?>
    <div class="panel" style="margin-bottom: 20px; padding: 20px; border-radius: 12px; box-shadow: var(--shadow);">
        <h3 style="margin-top:0; font-size:15px; font-weight:700; color:var(--ink); border-bottom:1px solid var(--line); padding-bottom:8px; margin-bottom:15px;">
            ➕ <?= e(__('إضافة دور جديد بالنظام وتحديد صلاحياته')) ?>
        </h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_role">
            
            <div class="grid-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom:15px;">
                <label style="font-weight:600; font-size:12px; color:var(--muted);">
                    <?= e(__('اسم الدور بالعربية')) ?>
                    <input name="name" required placeholder="مثال: كاشير مساعد" style="width:100%; padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;">
                </label>
                <label style="font-weight:600; font-size:12px; color:var(--muted);">
                    <?= e(__('كود الدور بالإنجليزية')) ?>
                    <input name="code" required placeholder="مثال: assistant_cashier" style="width:100%; padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;">
                </label>
            </div>
            
            <div style="background: var(--surface-soft); padding: 12px; border-radius: 8px; border: 1px solid var(--line); margin-bottom:15px;">
                <h4 style="margin: 0 0 10px 0; font-size:13px; font-weight:700; color:var(--ink);"><?= e(__('تحديد الصلاحيات للدور الجديد')) ?>:</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; max-height: 250px; overflow-y: auto; padding: 5px;">
                    <?php 
                    $catIdx = 0;
                    foreach ($permission_definitions as $category => $perms): 
                        $catIdx++;
                    ?>
                        <div style="background: var(--surface); padding: 10px; border-radius: 8px; border: 1px solid var(--line);">
                            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--line); padding-bottom:4px; margin-bottom:6px;">
                                <strong style="font-size:12px; color:var(--primary);"><?= e(__($category)) ?></strong>
                                <button type="button" onclick="toggleCatCheckboxGroup(this, <?= $catIdx ?>)" style="background:none; border:0; color:var(--muted); font-size:11px; font-weight:bold; cursor:pointer;" data-all="0"><?= e(__('تحديد الكل')) ?></button>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:6px;">
                                <?php foreach ($perms as $code => $lbl): ?>
                                    <label style="display:flex; align-items:flex-start; gap:6px; font-size:11px; color:var(--ink); cursor:pointer; font-weight:500;">
                                        <input type="checkbox" name="new_role_perms[<?= e($code) ?>]" class="new-role-perm-cb-<?= $catIdx ?>" value="1" style="margin-top:2px;">
                                        <span><?= e(__($lbl)) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button class="btn primary" style="padding:10px 20px; border-radius:8px; font-weight:bold;"><?= e(__('إضافة الدور الجديد')) ?></button>
        </form>
    </div>

    <form class="panel" method="post" style="border-radius:12px; padding:20px; box-shadow:var(--shadow);">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_permissions">
        
        <h3 style="margin-top:0; font-size:15px; font-weight:700; color:var(--ink);"><?= e(__('جدول صلاحيات العمليات للأدوار')) ?></h3>
        <table style="width:100%; border-collapse:collapse; text-align:right;">
            <thead>
                <tr style="border-bottom: 2px solid var(--line); color: var(--muted); font-weight:700;">
                    <th style="padding:12px 10px; font-size:13px;"><?= e(__('نوع الصلاحية / العملية')) ?></th>
                    <?php foreach ($editable_roles as $r): ?>
                        <th style="padding:12px 10px; text-align:center; font-size:13px; min-width: 120px;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 6px;">
                                <strong><?= e($r['name']) ?></strong>
                                <button type="button" onclick="triggerDeleteRole(<?= $r['id'] ?>, '<?= e(addslashes($r['name'])) ?>')" style="background:none; border:0; color:var(--danger); font-size:11px; cursor:pointer; padding:2px 6px; border-radius:4px; background-color:var(--surface-soft);" title="<?= e(__('حذف الدور')) ?>">🗑️ <?= e(__('حذف')) ?></button>
                            </div>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permission_definitions as $category => $perms): ?>
                    <tr style="background:var(--surface-soft); font-weight:bold; border-bottom:1px solid var(--line);">
                        <td colspan="<?= count($editable_roles) + 1 ?>" style="padding:8px 10px; color:var(--primary); font-size:12px; font-weight:700;"><?= e(__($category)) ?></td>
                    </tr>
                    <?php foreach ($perms as $code => $label): ?>
                        <tr style="border-bottom:1px solid var(--line); transition: background 0.15s ease;" onmouseover="this.style.background='var(--surface-soft)'" onmouseout="this.style.background='transparent'">
                            <td style="padding:10px; font-weight:600; color:var(--ink);"><?= e(__($label)) ?> <code style="font-size:10px; color:var(--muted); font-weight:normal; margin-right:5px;">(<?= e($code) ?>)</code></td>
                            <?php foreach ($editable_roles as $r): ?>
                                <td style="padding:10px; text-align:center;">
                                    <input type="checkbox" name="perms[<?= $r['id'] ?>][<?= $code ?>]" value="1" <?= isset($active_perms[$r['id']][$code]) ? 'checked' : '' ?> style="transform: scale(1.15); cursor:pointer;">
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div style="margin-top:20px; text-align:left;">
            <button class="btn primary" style="padding:10px 20px; border-radius:8px; font-weight:bold;"><?= e(__('حفظ تعديلات صلاحيات الأدوار')) ?></button>
        </div>
    </form>

    <!-- Hidden form for deleting roles -->
    <form id="delete-role-form" method="post" style="display:none;">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="delete_role">
        <input type="hidden" name="role_id" id="delete-role-id">
    </form>

    <script>
    function toggleCatCheckboxGroup(btn, idx) {
        const checkAll = btn.getAttribute('data-all') === '0';
        const checkboxes = document.querySelectorAll('.new-role-perm-cb-' + idx);
        checkboxes.forEach(cb => cb.checked = checkAll);
        btn.setAttribute('data-all', checkAll ? '1' : '0');
        btn.textContent = checkAll ? '<?= e(__('إلغاء التحديد')) ?>' : '<?= e(__('تحديد الكل')) ?>';
    }
    
    function triggerDeleteRole(id, name) {
        if (confirm('<?= e(__('هل أنت متأكد من حذف هذا الدور؟')) ?>: ' + name)) {
            document.getElementById('delete-role-id').value = id;
            document.getElementById('delete-role-form').submit();
        }
    }
    </script>

<?php else: ?>
    <!-- Users list tab -->
    <form class="panel grid-form" method="post" style="border-radius:12px; padding:20px; box-shadow:var(--shadow);">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php if ($editUser): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= e($editUser['id']) ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="add">
        <?php endif; ?>
        <input type="hidden" name="commission_percent" value="<?= e($editUser['commission_percent'] ?? 0) ?>">
        <label><?= e(__('الاسم')) ?><input name="name" required value="<?= e($editUser['name'] ?? '') ?>" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;"></label>
        <label><?= e(__('اسم المستخدم')) ?><input name="username" required value="<?= e($editUser['username'] ?? '') ?>" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;"></label>
        <label><?= e(__('كلمة المرور')) ?><input name="password" type="password" <?= $editUser ? '' : 'required' ?> placeholder="<?= $editUser ? __('اتركها فارغة للبقاء كما هي') : '' ?>" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;"></label>
        <label><?= e(__('الدور')) ?>
            <select name="role_id" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit; outline:none;">
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r['id']) ?>" <?= $editUser && (int)$editUser['role_id'] === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(__('الموقع')) ?>
            <select name="location_id" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit; outline:none;">
                <option value="" <?= $editUser && $editUser['location_id'] === null ? 'selected' : '' ?>><?= e(__('عام / أدمن')) ?></option>
                <?php foreach ($locations as $l): ?>
                    <option value="<?= e($l['id']) ?>" <?= $editUser && (int)$editUser['location_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?><?= $l['type'] === 'online' ? ' - لا حضور / ليس مخزن' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(__('الراتب الأساسي')) ?>
            <input name="basic_salary" type="number" step="1" min="0" value="<?= e($editUser['basic_salary'] ?? '0.00') ?>" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;">
        </label>
        <label><?= e(__('أيام العمل')) ?>
            <input name="working_days" type="number" min="0" value="<?= e($editUser['working_days'] ?? 0) ?>" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit;">
        </label>
        <label><?= e(__('الحالة')) ?>
            <select name="is_active" style="padding:8px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-soft); font-family:inherit; outline:none;">
                <option value="1" <?= $editUser && (int)$editUser['is_active'] === 1 ? 'selected' : '' ?>><?= e(__('نشط')) ?></option>
                <option value="0" <?= $editUser && (int)$editUser['is_active'] === 0 ? 'selected' : '' ?>><?= e(__('موقوف')) ?></option>
            </select>
        </label>
        <button class="btn primary" style="height: 38px; align-self: end; border-radius:8px; font-weight:bold;">
            <?= $editUser ? e(__('حفظ التعديلات')) : e(__('إضافة موظف')) ?>
        </button>
    </form>
    
    <div class="panel" style="border-radius:12px; padding:20px; box-shadow:var(--shadow);">
        <table style="width:100%; border-collapse:collapse; text-align:right;">
            <thead>
                <tr style="border-bottom: 2px solid var(--line); color: var(--muted); font-weight:700;">
                    <th style="padding:12px 10px; font-size:13px;"><?= e(__('الاسم')) ?></th>
                    <th style="padding:12px 10px; font-size:13px;"><?= e(__('المستخدم')) ?></th>
                    <th style="padding:12px 10px; font-size:13px;"><?= e(__('الدور')) ?></th>
                    <th style="padding:12px 10px; font-size:13px;"><?= e(__('الموقع')) ?></th>
                    <th style="padding:12px 10px; text-align: center; font-size:13px;"><?= e(__('الراتب الأساسي')) ?></th>
                    <th style="padding:12px 10px; text-align: center; font-size:13px;"><?= e(__('أيام العمل')) ?></th>
                    <th style="padding:12px 10px; font-size:13px;"><?= e(__('الحالة')) ?></th>
                    <th style="padding:12px 10px; text-align:center; font-size:13px;"><?= e(__('إجراءات')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid var(--line); transition: background 0.15s ease;" onmouseover="this.style.background='var(--surface-soft)'" onmouseout="this.style.background='transparent'">
                        <td style="padding:12px 10px;"><strong><?= e($u['name']) ?></strong></td>
                        <td style="padding:12px 10px;"><?= e($u['username']) ?></td>
                        <td style="padding:12px 10px;"><?= e($u['role_name']) ?></td>
                        <td style="padding:12px 10px;"><?= e($u['location_name'] ?: __('عام')) ?></td>
                        <td style="padding:12px 10px; text-align: center;"><strong><?= money($u['basic_salary']) ?></strong></td>
                        <td style="padding:12px 10px; text-align: center;"><strong><?= (int)($u['working_days'] ?? 0) ?></strong></td>
                        <td style="padding:12px 10px;">
                            <?php if ($u['is_active']): ?>
                                <span class="badge" style="background:#dcfce7; border:1px solid #bbf7d0; color:#15803d;"><?= e(__('نشط')) ?></span>
                            <?php else: ?>
                                <span class="badge" style="background:#fee2e2; border:1px solid #fecaca; color:#b91c1c;"><?= e(__('موقوف')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px 10px; text-align: center; white-space:nowrap;">
                            <a href="?r=users&amp;edit_id=<?= e($u['id']) ?>" class="btn small secondary">✏️ <?= e(__('تعديل')) ?></a>
                            <form action="index.php?r=users" method="post" style="display:inline;" class="confirm-deactivate">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="deactivate">
                                <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                <button type="submit" class="btn small warning">⏸️ <?= e(__('تعطيل')) ?></button>
                            </form>
                            <form action="index.php?r=users" method="post" style="display:inline;" class="confirm-delete">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                                <button type="submit" class="btn small danger">🗑️ <?= e(__('حذف نهائي')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const deactivateMsg = <?= json_encode(__('هل أنت متأكد من تعطيل هذا الموظف؟')) ?>;
        const deleteMsg = <?= json_encode(__('هل أنت متأكد من حذف هذا الموظف نهائياً؟ لا يمكن التراجع إذا لم تكن له سجلات مرتبطة.')) ?>;
        document.querySelectorAll('form.confirm-deactivate').forEach(f => {
            f.addEventListener('submit', (e) => {
                if (!confirm(deactivateMsg)) e.preventDefault();
            });
        });
        document.querySelectorAll('form.confirm-delete').forEach(f => {
            f.addEventListener('submit', (e) => {
                if (!confirm(deleteMsg)) e.preventDefault();
            });
        });
    });
    </script>
<?php endif; ?>
