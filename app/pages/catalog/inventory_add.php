<?php
$locations = stock_locations();
$userLocationId = current_user_location_id();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
}
$initialLocationId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : (int)($locations[0]['id'] ?? 0);
$initialLocationName = $locations[0]['name'] ?? 'غير محدد';
foreach ($locations as $loc) {
    if ((int)$loc['id'] === $initialLocationId) {
        $initialLocationName = $loc['name'];
        break;
    }
}
$products = all_products();
?>
<style>
.branch-alert-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    padding: 14px 18px;
    margin-bottom: 16px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(185, 132, 24, 0.16), rgba(185, 132, 24, 0.06));
    border: 2px solid var(--primary, #b98418);
    box-shadow: 0 4px 16px rgba(185, 132, 24, 0.14);
}
.branch-alert-main {
    display: flex;
    align-items: center;
    gap: 12px;
}
.branch-alert-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: var(--primary, #b98418);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(185, 132, 24, 0.3);
}
.branch-alert-text {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.branch-alert-label {
    font-size: 12px;
    font-weight: 700;
    color: var(--muted);
}
.branch-alert-title {
    font-size: 16px;
    font-weight: 900;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.branch-name-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: var(--primary, #b98418);
    color: #fff;
    padding: 3px 14px;
    border-radius: 999px;
    font-size: 15px;
    font-weight: 900;
    box-shadow: 0 2px 8px rgba(185, 132, 24, 0.25);
}
:root[data-theme="dark"] .branch-name-pill {
    color: #140d04;
}
.branch-quick-switcher {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.branch-switch-btn {
    padding: 6px 12px;
    border-radius: 8px;
    border: 1.5px solid var(--line);
    background: var(--surface);
    color: var(--ink);
    font-family: inherit;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}
.branch-switch-btn:hover {
    border-color: var(--primary);
}
.branch-switch-btn.active {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
    box-shadow: 0 2px 8px rgba(185, 132, 24, 0.28);
}
:root[data-theme="dark"] .branch-switch-btn.active {
    color: #140d04;
}
.branch-floating-toast {
    position: fixed;
    top: 18px;
    left: 50%;
    transform: translateX(-50%) translateY(-20px);
    z-index: 99999;
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: #fff;
    border: 2px solid var(--primary, #d6a844);
    border-radius: 14px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 14px 34px rgba(0, 0, 0, 0.45);
    font-family: 'Cairo', sans-serif;
    direction: rtl;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.28s ease, transform 0.28s ease;
}
.branch-floating-toast.show {
    opacity: 1;
    pointer-events: auto;
    transform: translateX(-50%) translateY(0);
}
</style>

<section class="page-head">
    <div>
        <h2>إضافة مخزون مركزي</h2>
        <p>أضف عدة أصناف دفعة واحدة إلى موقع المخزون.</p>
    </div>
    <div>
        <a class="btn" href="index.php?r=inventory">رجوع إلى سجل الإضافات</a>
    </div>
</section>

<!-- إشعار الفرع الحالي العلوي -->
<div class="branch-alert-banner" id="active-branch-banner">
    <div class="branch-alert-main">
        <div class="branch-alert-icon">🔔</div>
        <div class="branch-alert-text">
            <span class="branch-alert-label">تنبيه الموقع / الفرع المختار حالياً للإضافة:</span>
            <div class="branch-alert-title">
                <span>أنت الآن في فرع:</span>
                <span class="branch-name-pill" id="active-branch-pill">📍 <?= e($initialLocationName) ?></span>
            </div>
        </div>
    </div>
    <?php if (count($locations) > 1): ?>
        <div class="branch-quick-switcher">
            <span style="font-size:12px; font-weight:700; color:var(--muted); margin-left:4px;">تبديل سريع للفرع:</span>
            <?php foreach ($locations as $location): ?>
                <button type="button"
                        class="branch-switch-btn <?= (int)$location['id'] === $initialLocationId ? 'active' : '' ?>"
                        data-loc-id="<?= e($location['id']) ?>"
                        data-loc-name="<?= e($location['name']) ?>">
                    📍 <?= e($location['name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- إشعار منبثق (Toast Notification) لتنبيه المستخدم بالفرع الحالي -->
<div id="branch-floating-toast" class="branch-floating-toast" role="status" aria-live="polite">
    <span style="font-size:22px;">🔔</span>
    <div>
        <div style="font-size:11.5px; color:#cbd5e1; font-weight:600;">تنبيه موقع إضافة المخزون</div>
        <div style="font-size:14.5px; font-weight:900; color:#fff;">
            أنت الآن في فرع: <span id="toast-branch-name" style="color:#fbbf24; text-decoration:underline;"><?= e($initialLocationName) ?></span>
        </div>
    </div>
    <button type="button" onclick="document.getElementById('branch-floating-toast').classList.remove('show')" style="background:rgba(255,255,255,0.12); border:none; color:#fff; width:26px; height:26px; border-radius:50%; cursor:pointer; font-weight:800; margin-right:6px;">✕</button>
</div>

<form class="panel" id="inventory-add-form" method="post" action="index.php?r=inventory_add">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="grid-form" style="margin-bottom: 18px; display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
        <label>الموقع<select name="location_id" id="inventory-location-select" required>
            <?php foreach ($locations as $location): ?>
                <option value="<?= e($location['id']) ?>" <?= (int)$location['id'] === $initialLocationId ? 'selected' : '' ?>><?= e($location['name']) ?></option>
            <?php endforeach; ?>
        </select></label>
        <div style="display:flex; align-items:flex-end; gap:8px;">
            <button type="button" class="btn secondary" id="inventory-add-row">أضف صنف</button>
            <button type="button" class="btn" id="inventory-reset-form">إعادة تعيين</button>
        </div>
    </div>

    <!-- بحث بالباركود -->
    <div style="display:flex; align-items:center; gap:10px; background:var(--surface-soft); border:1.5px solid var(--line); border-radius:10px; padding:10px 14px; margin-bottom:4px;">
        <span style="font-size:20px;">📷</span>
        <label style="margin:0; font-weight:700; white-space:nowrap; color:var(--muted);">مسح باركود:</label>
        <input type="text" id="barcode-scan-input" placeholder="امسح الباركود أو اكتبه ثم Enter..." autocomplete="off"
               style="flex:1; padding:8px 12px; border:1.5px solid var(--line); border-radius:8px; font-size:14px;">
        <span id="barcode-scan-msg" style="font-size:13px; color:var(--muted);"></span>
    </div>

    <div class="panel" style="padding: 0; overflow-x: auto;">
        <table style="width:100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="padding: 12px; text-align:right; min-width: 180px;">الصنف</th>
                    <th style="padding: 12px; text-align:right; min-width: 100px;">المخزون الحالي</th>
                    <th style="padding: 12px; text-align:right; min-width: 120px;">الكمية المضافة</th>
                    <th style="padding: 12px; text-align:right; min-width: 120px;">سعر البيع</th>
                    <th style="padding: 12px; text-align:right; min-width: 120px;">تكلفة الشراء</th>
                    <th style="padding: 12px; text-align:right; min-width: 220px;">ملاحظة</th>
                    <th style="padding: 12px; text-align:center; width: 80px;">إجراء</th>
                </tr>
            </thead>
            <tbody id="inventory-add-items">
                <tr class="inventory-row">
                    <td style="padding: 10px;">
                        <select name="product_id[]" class="inventory-product-select" required>
                            <option value="">-- اختر صنفاً --</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= e($product['id']) ?>" data-sale="<?= e($product['sale_price']) ?>" data-cost="<?= e($product['cost_price'] ?? '') ?>"><?= e($product['name']) ?> (<?= e($product['unit'] === 'gram' ? 'جرام' : 'قطعة') ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="padding: 10px; text-align:center;">
                        <span class="current-stock-val" style="font-weight:700; color:var(--muted);">-</span>
                    </td>
                    <td style="padding: 10px; text-align:center;">
                        <input type="number" name="quantity[]" value="1" min="0.001" step="any" required style="width:100px; text-align:center;">
                    </td>
                    <td style="padding: 10px; text-align:center;">
                        <input type="number" name="sale_price[]" placeholder="0" min="0" step="any" style="width:120px; text-align:center;">
                    </td>
                    <td style="padding: 10px; text-align:center;">
                        <input type="number" name="cost_price[]" value="" min="0" step="0.01" style="width:120px; text-align:center;">
                    </td>
                    <td style="padding: 10px;">
                        <input type="text" name="notes[]" placeholder="ملاحظة لكل صنف" style="width:100%;">
                    </td>
                    <td style="padding: 10px; text-align:center;">
                        <button type="button" class="btn small danger inventory-remove-row">حذف</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <p class="muted" id="inventory-add-hint" style="margin: 12px;">أضف صنفاً واحداً على الأقل ثم اضغط حفظ.</p>
    </div>

    <div style="display:flex; gap:12px; justify-content:flex-end; margin-top: 14px;">
        <button type="submit" class="btn primary">حفظ الإضافات</button>
    </div>
</form>

<template id="inventory-row-template">
    <tr class="inventory-row">
        <td style="padding: 10px;">
            <select name="product_id[]" class="inventory-product-select" required>
                <option value="">-- اختر صنفاً --</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e($product['id']) ?>" data-sale="<?= e($product['sale_price']) ?>" data-cost="<?= e($product['cost_price'] ?? '') ?>"><?= e($product['name']) ?> (<?= e($product['unit'] === 'gram' ? 'جرام' : 'قطعة') ?>)</option>
                <?php endforeach; ?>
            </select>
        </td>
        <td style="padding: 10px; text-align:center;">
            <span class="current-stock-val" style="font-weight:700; color:var(--muted);">-</span>
        </td>
        <td style="padding: 10px; text-align:center;">
            <input type="number" name="quantity[]" value="1" min="0.001" step="any" required style="width:100px; text-align:center;">
        </td>
        <td style="padding: 10px; text-align:center;">
            <input type="number" name="sale_price[]" placeholder="0" min="0" step="any" style="width:120px; text-align:center;">
        </td>
        <td style="padding: 10px; text-align:center;">
            <input type="number" name="cost_price[]" value="" min="0" step="0.01" style="width:120px; text-align:center;">
        </td>
        <td style="padding: 10px;">
            <input type="text" name="notes[]" placeholder="ملاحظة لكل صنف" style="width:100%;">
        </td>
        <td style="padding: 10px; text-align:center;">
            <button type="button" class="btn small danger inventory-remove-row">حذف</button>
        </td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const addRowButton = document.getElementById('inventory-add-row');
    const resetButton = document.getElementById('inventory-reset-form');
    const itemsBody = document.getElementById('inventory-add-items');
    const rowTemplate = document.getElementById('inventory-row-template');
    const form = document.getElementById('inventory-add-form');

    const locationSelect = document.querySelector('select[name="location_id"]');
    const branchPill = document.getElementById('active-branch-pill');
    const toastEl = document.getElementById('branch-floating-toast');
    const toastBranchName = document.getElementById('toast-branch-name');
    const quickSwitchBtns = document.querySelectorAll('.branch-switch-btn');
    let toastTimer = null;

    if (toastEl && toastEl.parentNode !== document.body) {
        document.body.appendChild(toastEl);
    }

    function showBranchToast(branchName) {
        if (!toastEl || !toastBranchName) return;
        toastBranchName.textContent = branchName;
        toastEl.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toastEl.classList.remove('show');
        }, 5500);
    }

    function syncBranchNotification() {
        if (!locationSelect) return;
        const selectedOption = locationSelect.options[locationSelect.selectedIndex];
        const branchName = selectedOption ? selectedOption.textContent.trim() : '';
        if (branchPill && branchName) {
            branchPill.textContent = '📍 ' + branchName;
        }
        quickSwitchBtns.forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-loc-id') === locationSelect.value);
        });
        if (branchName) {
            showBranchToast(branchName);
        }
    }

    quickSwitchBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const locId = btn.getAttribute('data-loc-id');
            if (locationSelect && locId) {
                locationSelect.value = locId;
                locationSelect.dispatchEvent(new Event('change'));
                const wrapper = locationSelect.closest('.custom-select-wrapper');
                if (wrapper) {
                    const trigger = wrapper.querySelector('.custom-select-trigger');
                    if (trigger && locationSelect.options[locationSelect.selectedIndex]) {
                        trigger.textContent = locationSelect.options[locationSelect.selectedIndex].text;
                    }
                }
            }
        });
    });

    // Show notification immediately upon entering the page
    setTimeout(syncBranchNotification, 150);

    function updateRowStock(row) {
        const productSelect = row.querySelector('.inventory-product-select');
        const stockValSpan = row.querySelector('.current-stock-val');
        if (!locationSelect || !productSelect || !stockValSpan) return;

        const locationId = locationSelect.value;
        const productId = productSelect.value;

        if (!locationId || !productId) {
            stockValSpan.textContent = '-';
            stockValSpan.style.color = 'var(--muted)';
            return;
        }

        stockValSpan.textContent = '...';
        fetch(`index.php?r=get_stock&product_id=${productId}&location_id=${locationId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    stockValSpan.textContent = data.stock;
                    stockValSpan.style.color = data.stock <= 0 ? 'var(--danger, red)' : 'var(--success, green)';
                } else {
                    stockValSpan.textContent = '0';
                    stockValSpan.style.color = 'var(--muted)';
                }
            })
            .catch(() => {
                stockValSpan.textContent = 'خطأ';
                stockValSpan.style.color = 'var(--danger, red)';
            });
    }

    if (locationSelect) {
        locationSelect.addEventListener('change', () => {
            syncBranchNotification();
            itemsBody.querySelectorAll('.inventory-row').forEach(updateRowStock);
        });
    }

    function attachRowListeners(row) {
        const productSelect = row.querySelector('.inventory-product-select');
        const saleInput = row.querySelector('input[name="sale_price[]"]');
        const costInput = row.querySelector('input[name="cost_price[]"]');
        const removeButton = row.querySelector('.inventory-remove-row');

        if (typeof makeSelectSearchable === 'function') {
            makeSelectSearchable(productSelect);
        }

        if (productSelect) {
            productSelect.addEventListener('change', () => {
                const option = productSelect.options[productSelect.selectedIndex];
                if (!option || !option.value) {
                    saleInput.value = '0';
                    costInput.value = '';
                    updateRowStock(row);
                    return;
                }
                saleInput.value = option.dataset.sale ?? '0';
                costInput.value = option.dataset.cost ?? '';
                updateRowStock(row);
            });
        }

        removeButton.addEventListener('click', () => {
            if (itemsBody.querySelectorAll('.inventory-row').length <= 1) {
                resetInventoryRows();
                return;
            }
            row.remove();
        });
    }

    function resetInventoryRows() {
        itemsBody.innerHTML = '';
        const clone = rowTemplate.content.cloneNode(true);
        itemsBody.appendChild(clone);
        const newRow = itemsBody.querySelector('.inventory-row');
        attachRowListeners(newRow);
    }

    addRowButton.addEventListener('click', () => {
        const clone = rowTemplate.content.cloneNode(true);
        itemsBody.appendChild(clone);
        const newRow = itemsBody.querySelector('.inventory-row:last-child');
        attachRowListeners(newRow);
    });

    resetButton.addEventListener('click', (e) => {
        e.preventDefault();
        resetInventoryRows();
    });

    form.addEventListener('submit', (e) => {
        const selectedProductIds = Array.from(form.querySelectorAll('select[name="product_id[]"]'))
            .map(select => select.value)
            .filter(Boolean);
        if (selectedProductIds.length === 0) {
            e.preventDefault();
            alert('يرجى إضافة صنف واحد على الأقل قبل حفظ الإضافات.');
            return;
        }
    });

    const initialRow = itemsBody.querySelector('.inventory-row');
    if (initialRow) {
        attachRowListeners(initialRow);
    }

    // ===== Barcode scanner =====
    const barcodeScanInput = document.getElementById('barcode-scan-input');
    const barcodeScanMsg   = document.getElementById('barcode-scan-msg');

    if (barcodeScanInput) {
        barcodeScanInput.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            const barcode = this.value.trim();
            if (!barcode) return;
            barcodeScanMsg.textContent = 'جاري البحث...';
            barcodeScanMsg.style.color = 'var(--muted)';

            const locationId = locationSelect ? locationSelect.value : '';
            fetch('index.php?r=barcode_lookup&barcode=' + encodeURIComponent(barcode) + '&location_id=' + locationId)
                .then(r => r.json())
                .then(data => {
                    if (data.status !== 'success' || !data.product) {
                        barcodeScanMsg.textContent = '❌ لا يوجد منتج بهذا الباركود';
                        barcodeScanMsg.style.color = 'var(--danger)';
                        return;
                    }
                    const p = data.product;
                    // Try to find an empty row first
                    let targetRow = null;
                    const rows = itemsBody.querySelectorAll('.inventory-row');
                    for (const row of rows) {
                        const sel = row.querySelector('select[name="product_id[]"]');
                        if (sel && !sel.value) { targetRow = row; break; }
                    }
                    // No empty row → add one
                    if (!targetRow) {
                        const clone = rowTemplate.content.cloneNode(true);
                        itemsBody.appendChild(clone);
                        targetRow = itemsBody.querySelector('.inventory-row:last-child');
                        attachRowListeners(targetRow);
                    }
                    // Select the product
                    const sel = targetRow.querySelector('select[name="product_id[]"]');
                    if (sel) {
                        sel.value = p.id;
                        sel.dispatchEvent(new Event('change'));
                        // If makeSelectSearchable is wrapping it, update the display
                        const wrapper = sel.closest('.custom-select-wrapper');
                        if (wrapper) {
                            const display = wrapper.querySelector('.custom-select-display');
                            if (display) display.textContent = sel.options[sel.selectedIndex]?.text || '';
                        }
                    }
                    barcodeScanMsg.textContent = '✅ ' + p.name;
                    barcodeScanMsg.style.color = 'var(--success, green)';
                    barcodeScanInput.value = '';
                    setTimeout(() => { barcodeScanMsg.textContent = ''; }, 3000);
                })
                .catch(() => {
                    barcodeScanMsg.textContent = '❌ خطأ في الاتصال';
                    barcodeScanMsg.style.color = 'var(--danger)';
                });
        });
    }
});
</script>
