<?php
$filterSupplierId = (int) ($_GET['supplier_id'] ?? 0);
$rows = supplier_rows();
$invoices = supplier_invoice_rows($filterSupplierId > 0 ? $filterSupplierId : null);
$canManage = has_permission('suppliers_add');
?>
<section class="page-head">
    <h2>الموردين</h2>
    <p>بيانات الموردين، فواتير المشتريات، رفع صور الفواتير من الجهاز، وحذف الفواتير مع تحديث الأرصدة تلقائياً.</p>
</section>

<?php if ($canManage): ?>
    <form class="panel grid-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="add_supplier">
        <input type="hidden" name="filter_supplier_id" value="<?= e($filterSupplierId) ?>">
        <label>اسم المورد<input name="name" required></label>
        <label>الهاتف<input name="phone"></label>
        <label>نوع المنتجات<input name="product_type"></label>
        <label>رقم فاتورة واردة<input name="invoice_number"></label>
        <label>قيمة الفاتورة<input name="invoice_total" type="number" step="any" min="0" placeholder="0"></label>
        <label>المدفوع<input name="invoice_paid" type="number" step="any" min="0" placeholder="0"></label>
        <label>تاريخ الفاتورة<input name="invoice_date" type="date" value="<?= e(date('Y-m-d')) ?>"></label>
        <label>ملاحظات<input name="notes"></label>
        <label>صورة الفاتورة (من الجهاز)<input type="file" name="invoice_image" accept="image/*"></label>
        <button class="btn primary">حفظ المورد</button>
    </form>

    <?php if (!empty($rows)): ?>
        <form class="panel grid-form" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="add_invoice">
            <input type="hidden" name="filter_supplier_id" value="<?= e($filterSupplierId) ?>">
            <label>إضافة فاتورة للمورد
                <select name="supplier_id" required>
                    <option value="">اختر المورد</option>
                    <?php foreach ($rows as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $filterSupplierId === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>رقم الفاتورة<input name="invoice_number"></label>
            <label>قيمة الفاتورة<input name="invoice_total" type="number" step="any" min="0.01" placeholder="0" required></label>
            <label>المدفوع<input name="invoice_paid" type="number" step="any" min="0" placeholder="0"></label>
            <label>تاريخ الفاتورة<input name="invoice_date" type="date" value="<?= e(date('Y-m-d')) ?>" required></label>
            <label>ملاحظات<input name="notes"></label>
            <label>صورة الفاتورة (من الجهاز)<input type="file" name="invoice_image" accept="image/*"></label>
            <button class="btn primary">إضافة فاتورة</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

<div class="panel">
    <div class="toolbar" style="justify-content: space-between; margin-bottom: 12px;">
        <h3 style="margin: 0;">ملخص الموردين</h3>
        <span class="badge">عدد الموردين: <?= count($rows) ?></span>
    </div>
    <table>
        <thead>
            <tr>
                <th>المورد</th>
                <th>الهاتف</th>
                <th>نوع المنتجات</th>
                <th>عدد الفواتير</th>
                <th>إجمالي الفواتير</th>
                <th>الرصيد المتبقي</th>
                <th>الفواتير</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="muted" style="text-align: center;">لا يوجد موردين مسجلين بعد.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $s): ?>
                <tr>
                    <td><?= e($s['name']) ?></td>
                    <td><?= e($s['phone'] ?: '-') ?></td>
                    <td><?= e($s['product_type'] ?: '-') ?></td>
                    <td><?= (int) ($s['invoices_count'] ?? 0) ?></td>
                    <td><?= money($s['total_invoices']) ?></td>
                    <td><?= money($s['total_due']) ?></td>
                    <td>
                        <a class="btn small" href="index.php?r=suppliers&supplier_id=<?= (int) $s['id'] ?>#supplier-invoices">عرض الفواتير</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="panel" id="supplier-invoices">
    <div class="toolbar" style="justify-content: space-between; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
        <div class="actions" style="align-items: center;">
            <h3 style="margin: 0;">سجل فواتير الموردين</h3>
            <span class="badge">الإجمالي: <?= count($invoices) ?></span>
        </div>
        <form method="get" class="actions" style="align-items: center;">
            <input type="hidden" name="r" value="suppliers">
            <select name="supplier_id" onchange="this.form.submit()">
                <option value="0">كل الموردين</option>
                <?php foreach ($rows as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $filterSupplierId === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterSupplierId > 0): ?>
                <a class="btn small" href="index.php?r=suppliers#supplier-invoices">عرض الكل</a>
            <?php endif; ?>
        </form>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>المورد</th>
                <th>رقم الفاتورة</th>
                <th>التاريخ</th>
                <th>القيمة</th>
                <th>المدفوع</th>
                <th>المتبقي</th>
                <th>ملاحظات</th>
                <th>صورة الفاتورة</th>
                <?php if ($canManage): ?><th>إجراءات</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
                <tr><td colspan="<?= $canManage ? 10 : 9 ?>" class="muted" style="text-align: center;">لا توجد فواتير موردين مطابقة للعرض.</td></tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): ?>
                <?php
                    $invNumberDisplay = !empty($inv['invoice_number']) ? $inv['invoice_number'] : ('#' . (int) $inv['id']);
                    $invLabel = $inv['supplier_name'] . ' — فاتورة ' . $invNumberDisplay;
                    $hasImage = !empty($inv['invoice_image']);
                ?>
                <tr>
                    <td><?= (int) $inv['id'] ?></td>
                    <td><?= e($inv['supplier_name']) ?></td>
                    <td><?= e($inv['invoice_number'] ?: '-') ?></td>
                    <td><?= e(format_date($inv['invoice_date'] ?? null)) ?></td>
                    <td><?= money($inv['total']) ?></td>
                    <td><?= money($inv['paid']) ?></td>
                    <td><?= money($inv['due']) ?></td>
                    <td><?= e($inv['notes'] ?: '-') ?></td>
                    <td>
                        <div class="actions" style="align-items: center; gap: 6px; flex-wrap: wrap;">
                            <?php if ($hasImage): ?>
                                <img
                                    src="<?= e($inv['invoice_image']) ?>"
                                    alt="صورة الفاتورة"
                                    onclick="openSupplierInvoiceImage(<?= e(json_encode($inv['invoice_image'])) ?>, <?= e(json_encode($invLabel)) ?>)"
                                    style="width: 42px; height: 42px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(255,255,255,0.18); cursor: pointer;"
                                >
                                <button
                                    type="button"
                                    class="btn small"
                                    onclick="openSupplierInvoiceImage(<?= e(json_encode($inv['invoice_image'])) ?>, <?= e(json_encode($invLabel)) ?>)"
                                >عرض الصورة</button>
                            <?php else: ?>
                                <span class="muted">لا توجد صورة</span>
                            <?php endif; ?>
                            <?php if ($canManage): ?>
                                <form method="post" enctype="multipart/form-data" style="display: inline-flex; margin: 0;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="upload_invoice_image">
                                    <input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>">
                                    <input type="hidden" name="filter_supplier_id" value="<?= e($filterSupplierId) ?>">
                                    <label class="btn small" style="cursor: pointer; margin: 0;">
                                        <?= $hasImage ? 'تغيير الصورة' : 'رفع صورة' ?>
                                        <input type="file" name="invoice_image" accept="image/*" style="display: none;" onchange="if(this.files && this.files.length) this.form.submit();">
                                    </label>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                    <?php if ($canManage): ?>
                        <td>
                            <form
                                method="post"
                                style="display: inline;"
                                onsubmit="return confirm('هل أنت متأكد من حذف هذه الفاتورة نهائياً؟ سيتم تحديث رصيد المورد تلقائياً.');"
                            >
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete_invoice">
                                <input type="hidden" name="invoice_id" value="<?= (int) $inv['id'] ?>">
                                <input type="hidden" name="filter_supplier_id" value="<?= e($filterSupplierId) ?>">
                                <button class="btn danger small" type="submit">حذف</button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="supplier-invoice-modal" onclick="if(event.target === this) closeSupplierInvoiceImage();" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.84); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #141414; border: 1px solid rgba(255,255,255,0.16); border-radius: 12px; max-width: 95vw; max-height: 92vh; padding: 14px; display: flex; flex-direction: column; gap: 10px;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
            <strong id="supplier-invoice-modal-title" style="color: #fff;">صورة الفاتورة</strong>
            <div class="actions" style="gap: 8px;">
                <a id="supplier-invoice-modal-open" class="btn small" href="#" target="_blank" rel="noopener">فتح في نافذة جديدة</a>
                <a id="supplier-invoice-modal-download" class="btn small" href="#" download>تحميل</a>
                <button type="button" class="btn danger small" onclick="closeSupplierInvoiceImage()">إغلاق</button>
            </div>
        </div>
        <div style="display: flex; align-items: center; justify-content: center; overflow: auto;">
            <img id="supplier-invoice-modal-img" src="" alt="صورة الفاتورة" style="max-width: 90vw; max-height: 78vh; object-fit: contain; border-radius: 8px;">
        </div>
    </div>
</div>

<script>
function openSupplierInvoiceImage(src, title) {
    var modal = document.getElementById('supplier-invoice-modal');
    var img = document.getElementById('supplier-invoice-modal-img');
    var titleEl = document.getElementById('supplier-invoice-modal-title');
    var openLink = document.getElementById('supplier-invoice-modal-open');
    var downloadLink = document.getElementById('supplier-invoice-modal-download');
    if (!modal || !img) return;
    img.src = src;
    titleEl.textContent = title || 'صورة الفاتورة';
    openLink.href = src;
    downloadLink.href = src;
    modal.style.display = 'flex';
}

function closeSupplierInvoiceImage() {
    var modal = document.getElementById('supplier-invoice-modal');
    var img = document.getElementById('supplier-invoice-modal-img');
    if (!modal) return;
    modal.style.display = 'none';
    if (img) img.src = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeSupplierInvoiceImage();
    }
});
</script>
