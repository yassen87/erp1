<?php
$userLocationId = current_user_location_id();
$allLocations = all_locations();
$branches = array_values(array_filter($allLocations, fn($l) => $l['type'] === 'branch'));

$transfer = null;
$items = [];
$editMode = false;
if (!empty($_GET['edit'])) {
    $transfer = get_transfer((int) $_GET['edit']);
    if ($transfer && $transfer['status'] === 'pending') {
        $editMode = true;
        $items = get_transfer_items((int) $transfer['id']);
    } else {
        $transfer = null;
    }
}

// الفرع المرسل الافتراضي
$defaultFromId = $editMode ? (int)$transfer['from_location_id'] : ($userLocationId ?? ($branches[0]['id'] ?? 1));

// تحميل كافة المنتجات والأرصدة للفرع المرسل
$allProductsWithStock = all_products_with_stock($defaultFromId, null, false);
$products = array_values(array_filter($allProductsWithStock, fn($p) => $p['type'] !== 'recipe'));
$bottles = array_values(array_filter($products, fn($p) => $p['type'] === 'bottle'));
$perfumes = array_values(array_filter($products, fn($p) => $p['type'] === 'perfume_gram'));
$recipes = saved_recipes();
foreach ($recipes as &$rec) {
    $rec['components'] = recipe_components_for((int)$rec['id']);
}
unset($rec);
?>
<section class="page-head" style="margin-bottom: 20px;">
    <div>
        <h2 style="display: flex; align-items: center; gap: 8px;">
            <span>🔄 كاشير التحويل بين الفروع</span>
            <span style="font-size: 12px; font-weight: normal; background: var(--gold-soft2, rgba(201,168,76,0.2)); color: var(--gold-dark, #9E7A2A); padding: 4px 10px; border-radius: 999px; border: 1px solid var(--gold, #C9A84C);">
                <?= $editMode ? 'تعديل أمر تحويل #' . e($transfer['transfer_number']) : 'إنشاء ونقل مخزون فوري' ?>
            </span>
        </h2>
        <p>نقل مباشر للأصناف والزيوت أو خلطات عطرية مخصصة (بزجاجة أو بدون زجاجة) مع خصم فوري ومباشر من رصيد الفرع المرسل.</p>
    </div>
    <div>
        <a class="btn secondary" href="index.php?r=transfers_branch" style="display: inline-flex; align-items: center; gap: 6px;">
            <span>⬅️</span>
            <span>الرجوع لسجل التحويلات</span>
        </a>
    </div>
</section>

<form id="transfer-form" method="post" action="index.php?r=transfers_branch">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" id="transfer-action" value="<?= $editMode ? 'update' : '' ?>">
    <input type="hidden" name="transfer_id" id="transfer-id" value="<?= $editMode ? e($transfer['id']) : '' ?>">

    <!-- لوحة بيانات الشحنة العلوية -->
    <div class="panel" style="border-radius: 14px; padding: 18px; margin-bottom: 20px; border: 1.5px solid var(--line); box-shadow: var(--shadow);">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 15px; color: var(--gold, #C9A84C); display: flex; align-items: center; gap: 6px;">
            <span>📍</span>
            <span>بيانات الفروع وتفاصيل النقل</span>
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px;">
            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px;">
                    الفرع المرسل (المصدر - يتم الخصم منه):
                </label>
                <?php if ($userLocationId !== null): ?>
                    <select name="from_location_id" id="from_location_id" disabled style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface-soft); font-family: inherit; font-weight: bold;">
                        <?php foreach ($branches as $l): ?>
                            <option value="<?= e($l['id']) ?>" <?= (int)$userLocationId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="from_location_id" id="hidden_from_location_id" value="<?= e($userLocationId) ?>">
                <?php else: ?>
                    <select name="from_location_id" id="from_location_id" required onchange="handleFromLocationChange(this.value)" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1.5px solid var(--gold, #C9A84C); background: var(--surface-soft); font-family: inherit; font-weight: bold;">
                        <?php foreach ($branches as $l): ?>
                            <option value="<?= e($l['id']) ?>" <?= (int)$defaultFromId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <span class="muted" style="font-size: 11px; display: block; margin-top: 3px;">يتم خصم الأصناف المحولة من مخزون هذا الفرع.</span>
            </div>

            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px;">
                    الفرع المستلم (الوجهة - يتم الاستلام عنده):
                </label>
                <select name="to_location_id" id="to_location_id" required style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface-soft); font-family: inherit; font-weight: bold;">
                    <option value="">-- اختر الفرع المستلم --</option>
                    <?php foreach ($branches as $l): ?>
                        <?php if ($userLocationId === null || (int)$l['id'] !== (int)$userLocationId): ?>
                            <option value="<?= e($l['id']) ?>" <?= $editMode && (int)$transfer['to_location_id'] === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <span class="muted" style="font-size: 11px; display: block; margin-top: 3px;">الفرع الذي ستضاف البضاعة لرصيده عند الاستلام.</span>
            </div>

            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px;">اسم مسؤول الإرسال:</label>
                <input name="sender_name" id="sender_name" placeholder="مثال: أحمد (كاشير أم خنان)" value="<?= $editMode ? e($transfer['sender_name']) : '' ?>" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface-soft); font-family: inherit;">
            </div>

            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px;">اسم مسؤول الاستلام:</label>
                <input name="receiver_name" id="receiver_name" placeholder="مثال: محمود (كاشير المنوات)" value="<?= $editMode ? e($transfer['receiver_name']) : '' ?>" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface-soft); font-family: inherit;">
            </div>

            <div>
                <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px;">تاريخ التحويل:</label>
                <input name="transfer_date" id="transfer_date" type="date" value="<?= e($editMode ? $transfer['transfer_date'] : date('Y-m-d')) ?>" required style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface-soft); font-family: inherit;">
            </div>

            <div style="grid-column: span 1;">
                <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px;">ملاحظات التحويل:</label>
                <input name="notes" id="transfer-notes" placeholder="ملاحظات أو سبب التحويل..." value="<?= $editMode ? e($transfer['notes']) : '' ?>" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface-soft); font-family: inherit;">
            </div>
        </div>
    </div>

    <!-- شبكة الكاشير للتحويل: لوحة الإدخال السريع (يمين) + سلة البنود (يسار) -->
    <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 20px; align-items: start;">

        <!-- اللوحة الأولى: كاشير الإضافة السريعة (سكانر + صنف مباشر + تركيبة بدون زجاجة / بزجاجة) -->
        <div class="panel" style="border-radius: 14px; padding: 20px; border: 2px solid var(--gold-soft2, rgba(201,168,76,0.3)); box-shadow: var(--shadow);">
            
            <!-- أزرار التبويبات الفاخرة (مثل شاشة POS) -->
            <div style="display: flex; gap: 6px; background: rgba(0,0,0,0.15); padding: 5px; border-radius: 10px; margin-bottom: 18px; border: 1px solid var(--line);">
                <button type="button" class="tab-btn active" id="tab-btn-direct" onclick="switchTransferTab('direct')" style="flex: 1; padding: 10px; border: none; border-radius: 7px; font-weight: bold; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s;">
                    <span>📦 صنف مباشر / زيت / زجاجة</span>
                </button>
                <button type="button" class="tab-btn" id="tab-btn-mix" onclick="switchTransferTab('mix')" style="flex: 1; padding: 10px; border: none; border-radius: 7px; font-weight: bold; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s;">
                    <span>🧪 عمل تركيبة (بزجاجة أو بدون)</span>
                </button>
                <button type="button" class="tab-btn" id="tab-btn-recipe" onclick="switchTransferTab('recipe')" style="flex: 0.9; padding: 10px; border: none; border-radius: 7px; font-weight: bold; font-size: 13px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s;">
                    <span>📋 من الوصفات</span>
                </button>
            </div>

            <!-- تبويب 1: إضافة صنف مباشر (مع سكانر الباركود والبحث الفوري) -->
            <div id="tab-content-direct">
                <!-- باركود سكانر -->
                <div style="background: rgba(201,168,76,0.08); padding: 12px 14px; border-radius: 10px; border: 1.5px dashed var(--gold, #C9A84C); margin-bottom: 16px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px; color: var(--gold, #C9A84C);">
                        📟 ماسح الباركود (Barcode Scanner):
                    </label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="barcode-scanner-input" placeholder="امسح الباركود هنا بالماسح الضوئي ثم Enter..." style="flex: 1; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); font-family: monospace; font-size: 14px; direction: ltr; text-align: left;" onkeydown="handleBarcodeEnter(event)">
                        <button type="button" class="btn small primary" onclick="processBarcodeScan()" style="padding: 0 16px; font-weight: bold;">
                            إضافة الصنف
                        </button>
                    </div>
                    <span class="muted" style="font-size: 11px; display: block; margin-top: 4px;">اضغط على الخانة وامسح باركود الزيت أو الزجاجة أو المنتج لإضافته فوراً للتحويل.</span>
                </div>

                <!-- البحث النصي واختيار الصنف -->
                <div style="margin-bottom: 14px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px;">
                        🔍 بحث بالاسم أو الكود واختيار الصنف:
                    </label>
                    <input type="text" id="direct-search-filter" placeholder="اكتب للبحث السريع (مثال: يارا، مسك، زجاجة 50)..." oninput="filterDirectProducts(this.value)" style="width: 100%; padding: 9px 12px; border-radius: 8px; border: 1px solid var(--line); margin-bottom: 8px; font-family: inherit;">
                    
                    <select id="direct-product-select" size="6" onchange="onDirectProductSelected(this.value)" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface-soft); font-family: inherit; font-size: 13px;">
                        <!-- يتم ملؤها عبر جافاسكريبت -->
                    </select>
                </div>

                <!-- تفاصيل الصنف المختار وكمية التحويل -->
                <div id="direct-item-preview" style="background: var(--surface-soft); padding: 14px; border-radius: 10px; border: 1px solid var(--line); margin-bottom: 16px; display: none;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <div>
                            <strong id="preview-prod-name" style="font-size: 15px; color: var(--ink);"></strong>
                            <span id="preview-prod-type" class="badge" style="margin-right: 6px; font-size: 11px;"></span>
                        </div>
                        <div id="preview-stock-badge" style="font-weight: bold; font-size: 13px;"></div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <label style="font-weight: bold; display: block; margin-bottom: 4px; font-size: 12px;">
                                الكمية المراد تحويلها (<span id="preview-unit-label"></span>):
                            </label>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <button type="button" class="btn small" onclick="stepDirectQty(-1)" style="width: 36px; height: 36px;">-</button>
                                <input type="number" id="direct-qty-input" value="1" step="any" min="0.001" style="flex: 1; text-align: center; padding: 8px; font-weight: bold; font-size: 15px; border-radius: 6px; border: 1.5px solid var(--gold, #C9A84C);">
                                <button type="button" class="btn small" onclick="stepDirectQty(1)" style="width: 36px; height: 36px;">+</button>
                            </div>
                        </div>
                        <button type="button" class="btn primary" onclick="addDirectItemToTransfer()" style="height: 38px; padding: 0 20px; font-weight: bold;">
                            ➕ أضف للتحويل
                        </button>
                    </div>
                    <div id="direct-stock-warning" style="margin-top: 8px; color: #f59e0b; font-size: 12px; display: none; font-weight: bold;">
                        ⚠️ تنبيه: الكمية المطلوبة تتجاوز الرصيد المتوفر حالياً في الفرع المرسل!
                    </div>
                </div>
            </div>

            <!-- تبويب 2: عمل تركيبة مخصصة (بزجاجة أو بدون زجاجة) -->
            <div id="tab-content-mix" style="display: none;">
                <div style="background: rgba(59,130,246,0.06); padding: 12px 14px; border-radius: 10px; border: 1.5px dashed rgba(59,130,246,0.4); margin-bottom: 14px;">
                    <div>
                        <label style="font-weight: bold; display: block; margin-bottom: 4px; font-size: 12px;">اسم أو وصف التركيبة (اختياري):</label>
                        <input type="text" id="mix-title-input" placeholder="مثال: خلطة مسك الختام + يارا خاص" style="width: 100%; padding: 8px 12px; border-radius: 7px; border: 1px solid var(--line); font-family: inherit;">
                    </div>
                </div>

                <!-- اختيار الزجاجة مع خيار "بدون زجاجة" البارز -->
                <div style="margin-bottom: 16px; background: var(--surface-soft); padding: 14px; border-radius: 10px; border: 1px solid var(--line);">
                    <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px;">
                        🍾 زجاجة التركيبة (هل التركيبة بزجاجة أم زيت خام بدون زجاجة؟):
                    </label>
                    <select id="mix-bottle-select" onchange="onMixBottleChanged(this.value)" style="width: 100%; padding: 10px; border-radius: 8px; border: 1.5px solid var(--gold, #C9A84C); background: var(--surface); font-family: inherit; font-weight: bold; font-size: 13px;">
                        <option value="none" selected>🚫 بدون زجاجة (نقل زيت خام / سائل فقط - لا تخصم زجاجة)</option>
                        <?php foreach ($bottles as $b): ?>
                            <option value="<?= e($b['id']) ?>" data-stock="<?= e((float)$b['branch_stock']) ?>" data-size="<?= e($b['size_ml'] ?? 50) ?>">
                                🍾 <?= e($b['name']) ?> (حجم <?= e($b['size_ml'] ?? 'غير محدد') ?> مل) - [رصيد الفرع: <?= e((float)$b['branch_stock']) ?> قطعة]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="mix-bottle-info" style="font-size: 11px; margin-top: 4px; color: #10b981; font-weight: 600;">
                        ✅ سيتم نقل الزيوت العطرية بالجرام فقط ولن يتم خصم أي زجاجة من المخزن.
                    </div>
                </div>

                <!-- اختيار مكونات الزيوت العطرية -->
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label style="font-weight: bold; font-size: 13px;">💧 الزيوت العطرية المكونة للتركيبة (بالجرام):</label>
                        <span id="mix-total-grams-indicator" style="font-weight: bold; font-size: 13px; color: var(--gold, #C9A84C); background: rgba(201,168,76,0.12); padding: 2px 8px; border-radius: 6px;">
                            إجمالي الزيوت: 0 جم
                        </span>
                    </div>

                    <div id="mix-oil-rows-container" style="display: flex; flex-direction: column; gap: 8px;">
                        <!-- صفوف الزيوت تضاف عبر JavaScript -->
                    </div>

                    <button type="button" class="btn small secondary" onclick="addMixOilRow()" style="margin-top: 10px; width: 100%; font-weight: bold; border-style: dashed;">
                        + إضافة زيت آخر للخلطة
                    </button>
                </div>

                <!-- تكرار عدد العبوات وزر الإضافة -->
                <div style="background: rgba(0,0,0,0.12); padding: 12px; border-radius: 8px; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label style="font-weight: bold; font-size: 12px; white-space: nowrap;">عدد العبوات/التكرار:</label>
                        <input type="number" id="mix-repeat-qty" value="1" min="1" step="1" style="width: 70px; text-align: center; padding: 6px; font-weight: bold; border-radius: 6px; border: 1px solid var(--line);">
                    </div>
                    <button type="button" class="btn primary" onclick="addCustomMixToTransfer()" style="padding: 10px 20px; font-weight: bold;">
                        🧪 إضافة التركيبة للتحويل
                    </button>
                </div>
            </div>

            <!-- تبويب 3: اختيار من الوصفات الجاهزة (Saved Recipes) -->
            <div id="tab-content-recipe" style="display: none;">
                <div style="margin-bottom: 14px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 6px; font-size: 13px;">
                        📋 اختر تركيبة جاهزة من النظام:
                    </label>
                    <select id="preset-recipe-select" onchange="onPresetRecipeSelected(this.value)" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--line); background: var(--surface-soft); font-family: inherit; font-size: 13px;">
                        <option value="">-- اختر تركيبة جاهزة لتحميل مكوناتها --</option>
                        <?php foreach ($recipes as $r): ?>
                            <option value="<?= e($r['id']) ?>"><?= e($r['name']) ?> (زجاجة: <?= e($r['bottle_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="preset-recipe-box" style="display: none; background: var(--surface-soft); padding: 14px; border-radius: 10px; border: 1px solid var(--line);">
                    <div id="preset-recipe-details" style="margin-bottom: 12px;"></div>
                    <div style="display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
                        <button type="button" class="btn primary" onclick="applyPresetRecipeToMixTab()" style="font-weight: bold;">
                            تعديل واستخدام في خانة التركيبات 🧪
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- اللوحة الثانية: سلة أصناف التحويل (Transfer Items Cart) -->
        <div class="panel" style="border-radius: 14px; padding: 20px; border: 1.5px solid var(--line); box-shadow: var(--shadow); position: sticky; top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1.5px solid var(--line); padding-bottom: 10px;">
                <h3 style="margin: 0; font-size: 16px; color: var(--gold, #C9A84C); display: flex; align-items: center; gap: 6px;">
                    <span>📋</span>
                    <span>قائمة الأصناف المحولة (<span id="cart-items-count">0</span>)</span>
                </h3>
                <button type="button" class="btn small danger" onclick="clearTransferCart()" style="font-size: 11px;">
                    تفريغ القائمة
                </button>
            </div>

            <!-- جدول البنود -->
            <div style="max-height: 400px; overflow-y: auto; margin-bottom: 16px; border: 1px solid var(--line); border-radius: 10px;">
                <table style="width: 100%; border-collapse: collapse; text-align: right; font-size: 13px;">
                    <thead>
                        <tr style="background: rgba(0,0,0,0.15); border-bottom: 2px solid var(--line); color: var(--muted); font-size: 12px;">
                            <th style="padding: 10px 8px;">الصنف / التركيبة</th>
                            <th style="padding: 10px 8px; width: 110px; text-align: center;">الكمية</th>
                            <th style="padding: 10px 8px; width: 90px; text-align: center;">الرصيد المتاح</th>
                            <th style="padding: 10px 8px; width: 45px; text-align: center;">حذف</th>
                        </tr>
                    </thead>
                    <tbody id="transfer-cart-tbody">
                        <tr id="empty-cart-row">
                            <td colspan="4" style="text-align: center; padding: 30px; color: var(--muted);">
                                📦 لم يتم إضافة أي أصناف أو تركيبات للتحويل بعد.<br>
                                <span style="font-size: 11px;">استخدم السكانر أو القائمة على اليمين لإضافة المنتجات.</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ملخص الإجماليات -->
            <div style="background: var(--surface-soft); padding: 14px; border-radius: 10px; border: 1px solid var(--line); margin-bottom: 18px;">
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                    <span>إجمالي الأصناف المباشرة والزجاجات:</span>
                    <strong id="summary-pieces-count" style="font-family: monospace;">0 قطعة</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px;">
                    <span>إجمالي الزيوت العطرية المحولة:</span>
                    <strong id="summary-oils-weight" style="color: var(--gold, #C9A84C); font-family: monospace;">0.000 جم</strong>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: bold; border-top: 1px solid var(--line); padding-top: 8px; margin-top: 4px;">
                    <span>إجمالي البنود المختلفة:</span>
                    <strong id="summary-total-lines" style="color: var(--primary);">0 بند</strong>
                </div>
            </div>

            <!-- زر التأكيد وإنشاء التحويل -->
            <div>
                <button type="submit" id="btn-submit-transfer" class="btn primary" style="width: 100%; padding: 14px; font-size: 16px; font-weight: 800; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(201,168,76,0.3);">
                    <span>🚀</span>
                    <span><?= $editMode ? 'حفظ تعديلات أمر التحويل' : 'إنشاء أمر التحويل وخصم المخزون فوراً' ?></span>
                </button>
            </div>
            
            <div id="transfer-hidden-inputs-container">
                <!-- يتم توليد حقول product_id[] و quantity[] و item_notes[] هنا تلقائياً -->
            </div>
        </div>

    </div>
</form>

<script>
// ===== البيانات المحملة من الخادم =====
let ALL_PRODUCTS = <?= json_encode($products) ?>;
let ALL_BOTTLES = <?= json_encode($bottles) ?>;
let ALL_PERFUMES = <?= json_encode($perfumes) ?>;
let ALL_RECIPES = <?= json_encode($recipes) ?>;
const EXISTING_ITEMS = <?= json_encode($items) ?>;

// سلة البنود في التحويل
let transferCart = [];

document.addEventListener('DOMContentLoaded', () => {
    // ملء قائمة المنتجات المباشرة أول مرة
    renderDirectProductsDropdown(ALL_PRODUCTS);
    
    // إضافة أول صفين زيت في تبويب التركيبات
    initMixOilRows();

    // في حال وضع التعديل (Edit Mode): تحميل البنود السابقة
    if (EXISTING_ITEMS && EXISTING_ITEMS.length > 0) {
        EXISTING_ITEMS.forEach(it => {
            const prod = ALL_PRODUCTS.find(p => parseInt(p.id, 10) === parseInt(it.product_id, 10));
            transferCart.push({
                type: 'direct',
                productId: parseInt(it.product_id, 10),
                name: it.product_name,
                unit: it.product_unit,
                productType: prod ? prod.type : 'product',
                quantity: parseFloat(it.quantity) || 1,
                notes: it.notes || '',
                stock: prod ? parseFloat(prod.branch_stock || 0) : 0
            });
        });
        renderTransferCart();
    }

    // التركيز التلقائي على خانة الباركود
    const barcodeInput = document.getElementById('barcode-scanner-input');
    if (barcodeInput) setTimeout(() => barcodeInput.focus(), 300);

    // التحقق عند إرسال النموذج
    document.getElementById('transfer-form').addEventListener('submit', (e) => {
        const fromLoc = document.getElementById('from_location_id').value;
        const toLoc = document.getElementById('to_location_id').value;

        if (!fromLoc || !toLoc) {
            e.preventDefault();
            alert('يرجى تحديد الفرع المرسل والفرع المستلم.');
            return;
        }

        if (parseInt(fromLoc, 10) === parseInt(toLoc, 10)) {
            e.preventDefault();
            alert('لا يمكن التحويل لنفس الفرع! يرجى اختيار فرع مستلم مختلف.');
            return;
        }

        if (transferCart.length === 0) {
            e.preventDefault();
            alert('يرجى إضافة صنف واحد على الأقل للتحويل.');
            return;
        }

        // توليد الحقول المخفية للنموذج
        buildFormHiddenInputs();
    });
});

// ===== التبديل بين التبويبات (صنف مباشر / تركيبة / وصفات) =====
function switchTransferTab(tab) {
    document.getElementById('tab-btn-direct').classList.toggle('active', tab === 'direct');
    document.getElementById('tab-btn-mix').classList.toggle('active', tab === 'mix');
    document.getElementById('tab-btn-recipe').classList.toggle('active', tab === 'recipe');

    document.getElementById('tab-content-direct').style.display = tab === 'direct' ? 'block' : 'none';
    document.getElementById('tab-content-mix').style.display = tab === 'mix' ? 'block' : 'none';
    document.getElementById('tab-content-recipe').style.display = tab === 'recipe' ? 'block' : 'none';

    if (tab === 'direct') {
        const barcodeInput = document.getElementById('barcode-scanner-input');
        if (barcodeInput) barcodeInput.focus();
    }
}

// ===== التعامل مع تغير الفرع المرسل (لتحديث المخزون) =====
function handleFromLocationChange(locationId) {
    if (!locationId) return;
    
    // إشعار جارٍ التحديث
    const previewStockBadge = document.getElementById('preview-stock-badge');
    if (previewStockBadge) previewStockBadge.innerHTML = 'جاري تحديث الرصيد... ⏳';

    fetch('index.php?r=transfer_branch_products&location_id=' + encodeURIComponent(locationId))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.products) {
                ALL_PRODUCTS = data.products;
                ALL_BOTTLES = data.products.filter(p => p.type === 'bottle');
                ALL_PERFUMES = data.products.filter(p => p.type === 'perfume_gram');
                if (data.recipes) ALL_RECIPES = data.recipes;

                // إعادة ملء القوائم
                renderDirectProductsDropdown(ALL_PRODUCTS);
                renderMixBottleDropdown();
                updateAllMixOilDropdowns();

                // تحديث أرصدة السلة
                transferCart.forEach(item => {
                    const fresh = ALL_PRODUCTS.find(p => p.id === item.productId);
                    if (fresh) item.stock = parseFloat(fresh.branch_stock || 0);
                });
                renderTransferCart();

                // تحديث الصنف المختار حالياً إن وجد
                const select = document.getElementById('direct-product-select');
                if (select && select.value) {
                    onDirectProductSelected(select.value);
                }
            }
        })
        .catch(err => console.error('خطأ في جلب بيانات الفرع:', err));
}

// ===== 1. دوال الصنف المباشر والباركود =====
function renderDirectProductsDropdown(list) {
    const select = document.getElementById('direct-product-select');
    if (!select) return;
    select.innerHTML = '';
    
    list.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        const unitName = p.unit === 'gram' ? 'جم' : 'قطعة';
        const stockVal = parseFloat(p.branch_stock || 0);
        opt.textContent = `${p.name} — [الرصيد المتاح: ${stockVal} ${unitName}]`;
        select.appendChild(opt);
    });
}

function filterDirectProducts(query) {
    const q = query.toLowerCase().trim();
    const filtered = ALL_PRODUCTS.filter(p => 
        p.name.toLowerCase().includes(q) || 
        (p.barcode && p.barcode.toLowerCase().includes(q)) ||
        (p.sku && p.sku.toLowerCase().includes(q))
    );
    renderDirectProductsDropdown(filtered);
}

function onDirectProductSelected(productId) {
    const prod = ALL_PRODUCTS.find(p => parseInt(p.id, 10) === parseInt(productId, 10));
    if (!prod) return;

    document.getElementById('preview-prod-name').textContent = prod.name;
    const typeLabel = prod.type === 'perfume_gram' ? 'عطر بالجرام' : (prod.type === 'bottle' ? 'زجاجة' : 'منتج جاهز');
    document.getElementById('preview-prod-type').textContent = typeLabel;
    
    const unitName = prod.unit === 'gram' ? 'جرام' : 'قطعة';
    document.getElementById('preview-unit-label').textContent = unitName;
    
    const stockVal = parseFloat(prod.branch_stock || 0);
    const badge = document.getElementById('preview-stock-badge');
    badge.innerHTML = `الرصيد المتاح: <span style="color:${stockVal > 0 ? '#10b981' : '#ef4444'}">${stockVal} ${unitName}</span>`;

    const qtyInput = document.getElementById('direct-qty-input');
    qtyInput.value = prod.unit === 'gram' ? 10 : 1;

    document.getElementById('direct-item-preview').style.display = 'block';
    qtyInput.focus();
}

function stepDirectQty(delta) {
    const input = document.getElementById('direct-qty-input');
    let val = parseFloat(input.value) || 0;
    val = Math.max(0.001, parseFloat((val + delta).toFixed(3)));
    input.value = val;
}

function addDirectItemToTransfer() {
    const select = document.getElementById('direct-product-select');
    const productId = parseInt(select.value, 10);
    if (!productId) {
        alert('يرجى اختيار صنف أولاً.');
        return;
    }

    const prod = ALL_PRODUCTS.find(p => parseInt(p.id, 10) === productId);
    if (!prod) return;

    const qty = parseFloat(document.getElementById('direct-qty-input').value) || 1;
    if (qty <= 0) {
        alert('الكمية يجب أن تكون أكبر من صفر.');
        return;
    }

    // إضافة للسلة أو زيادة الكمية
    const existing = transferCart.find(it => it.type === 'direct' && it.productId === productId);
    if (existing) {
        existing.quantity = parseFloat((existing.quantity + qty).toFixed(3));
    } else {
        transferCart.push({
            type: 'direct',
            productId: prod.id,
            name: prod.name,
            unit: prod.unit,
            productType: prod.type,
            quantity: qty,
            notes: '',
            stock: parseFloat(prod.branch_stock || 0)
        });
    }

    renderTransferCart();

    // تنظيف الحقل والتركيز على الباركود
    const barcodeInput = document.getElementById('barcode-scanner-input');
    if (barcodeInput) {
        barcodeInput.value = '';
        barcodeInput.focus();
    }
}

// فحص الباركود
function handleBarcodeEnter(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        processBarcodeScan();
    }
}

function processBarcodeScan() {
    const input = document.getElementById('barcode-scanner-input');
    const code = input.value.trim();
    if (!code) return;

    const prod = ALL_PRODUCTS.find(p => (p.barcode && p.barcode.trim() === code) || (p.sku && p.sku.trim() === code));
    if (!prod) {
        alert('لم يتم العثور على أي صنف مطابق للباركود: ' + code);
        input.select();
        return;
    }

    // اختيار الصنف وعرضه
    const select = document.getElementById('direct-product-select');
    if (select) {
        select.value = prod.id;
        onDirectProductSelected(prod.id);
    }
    
    // إضافة فورية بوحدة 1 أو بالجرام الافتراضي
    const addQty = prod.unit === 'gram' ? 10 : 1;
    const existing = transferCart.find(it => it.type === 'direct' && it.productId === prod.id);
    if (existing) {
        existing.quantity = parseFloat((existing.quantity + addQty).toFixed(6));
    } else {
        transferCart.push({
            type: 'direct',
            productId: prod.id,
            name: prod.name,
            unit: prod.unit,
            productType: prod.type,
            quantity: addQty,
            notes: '',
            stock: parseFloat(prod.branch_stock || 0)
        });
    }

    renderTransferCart();
    input.value = '';
    input.focus();
}

// ===== 2. دوال عمل التركيبة (Custom Mix) =====
let mixOilRowIdCounter = 0;

function initMixOilRows() {
    const container = document.getElementById('mix-oil-rows-container');
    if (!container) return;
    container.innerHTML = '';
    addMixOilRow();
    addMixOilRow();
}

function addMixOilRow(perfumeId = null, grams = 0) {
    mixOilRowIdCounter++;
    const rowId = 'mix-oil-row-' + mixOilRowIdCounter;
    const container = document.getElementById('mix-oil-rows-container');
    
    const div = document.createElement('div');
    div.id = rowId;
    div.className = 'mix-oil-row';
    div.style = 'display:flex; gap:8px; align-items:center; background:var(--surface); padding:8px 10px; border-radius:8px; border:1px solid var(--line);';

    let optionsHtml = '<option value="">-- اختر الزيت العطري --</option>';
    ALL_PERFUMES.forEach(p => {
        const stockVal = parseFloat(p.branch_stock || 0);
        const sel = perfumeId && parseInt(perfumeId, 10) === parseInt(p.id, 10) ? 'selected' : '';
        optionsHtml += `<option value="${p.id}" data-stock="${stockVal}" ${sel}>${escapeHtml(p.name)} [المتاح: ${stockVal} جم]</option>`;
    });

    div.innerHTML = `
        <select class="mix-oil-select" style="flex:1.8; padding:8px; border-radius:6px; border:1px solid var(--line); background:var(--surface-soft); font-family:inherit; font-size:13px;" onchange="calcMixTotalGrams()">
            ${optionsHtml}
        </select>
        <div style="display:flex; align-items:center; gap:4px; flex:1;">
            <input type="number" class="mix-oil-grams" placeholder="الجرام" value="${grams > 0 ? grams : ''}" step="any" min="0.000001" style="width:100%; text-align:center; padding:7px; border-radius:6px; border:1px solid var(--line); font-weight:bold;" oninput="calcMixTotalGrams()">
            <span style="font-size:12px; color:var(--muted);">جم</span>
        </div>
        <button type="button" class="btn small danger" onclick="removeMixOilRow('${rowId}')" style="width:30px; height:30px; padding:0; line-height:1;">✕</button>
    `;

    container.appendChild(div);
    calcMixTotalGrams();
}

function removeMixOilRow(rowId) {
    const el = document.getElementById(rowId);
    if (el) el.remove();
    calcMixTotalGrams();
}

function calcMixTotalGrams() {
    let total = 0;
    document.querySelectorAll('.mix-oil-grams').forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });
    const indicator = document.getElementById('mix-total-grams-indicator');
    if (indicator) indicator.textContent = `إجمالي الزيوت: ${total.toFixed(2)} جم`;
}

function onMixBottleChanged(bottleVal) {
    const info = document.getElementById('mix-bottle-info');
    if (!info) return;
    if (bottleVal === 'none') {
        info.innerHTML = '✅ <span style="color:#10b981;">سيتم نقل الزيوت العطرية بالجرام فقط ولن يتم خصم أي زجاجة من المخزن.</span>';
    } else {
        const select = document.getElementById('mix-bottle-select');
        const opt = select.options[select.selectedIndex];
        const stock = opt.getAttribute('data-stock') || '0';
        info.innerHTML = `🍾 <span style="color:var(--gold,#C9A84C);">سيتم خصم زجاجة واحدة من رصيد الفرع (المتاح: ${stock} قطعة) بجانب خصم الزيوت.</span>`;
    }
}

function renderMixBottleDropdown() {
    const select = document.getElementById('mix-bottle-select');
    if (!select) return;
    const currentVal = select.value;
    
    let html = '<option value="none" ' + (currentVal === 'none' ? 'selected' : '') + '>🚫 بدون زجاجة (نقل زيت خام / سائل فقط - لا تخصم زجاجة)</option>';
    ALL_BOTTLES.forEach(b => {
        const stockVal = parseFloat(b.branch_stock || 0);
        const sel = currentVal == b.id ? 'selected' : '';
        html += `<option value="${b.id}" data-stock="${stockVal}" data-size="${b.size_ml || 50}" ${sel}>🍾 ${escapeHtml(b.name)} (حجم ${b.size_ml || ''} مل) - [رصيد الفرع: ${stockVal} قطعة]</option>`;
    });
    select.innerHTML = html;
}

function updateAllMixOilDropdowns() {
    document.querySelectorAll('.mix-oil-select').forEach(sel => {
        const currentVal = sel.value;
        let html = '<option value="">-- اختر الزيت العطري --</option>';
        ALL_PERFUMES.forEach(p => {
            const stockVal = parseFloat(p.branch_stock || 0);
            const isSel = currentVal == p.id ? 'selected' : '';
            html += `<option value="${p.id}" data-stock="${stockVal}" ${isSel}>${escapeHtml(p.name)} [المتاح: ${stockVal} جم]</option>`;
        });
        sel.innerHTML = html;
    });
}

function addCustomMixToTransfer() {
    const title = document.getElementById('mix-title-input').value.trim() || 'تركيبة عطرية مخصصة';
    const bottleSelect = document.getElementById('mix-bottle-select');
    const bottleId = bottleSelect.value;
    const repeatQty = parseInt(document.getElementById('mix-repeat-qty').value, 10) || 1;

    // جمع الزيوت
    const oils = [];
    document.querySelectorAll('.mix-oil-row').forEach(row => {
        const select = row.querySelector('.mix-oil-select');
        const gramsInput = row.querySelector('.mix-oil-grams');
        const perfumeId = parseInt(select.value, 10);
        const grams = parseFloat(gramsInput.value) || 0;

        if (perfumeId > 0 && grams > 0) {
            const prod = ALL_PERFUMES.find(p => p.id === perfumeId);
            oils.push({
                productId: perfumeId,
                name: prod ? prod.name : 'زيت عطري',
                grams: grams,
                totalGrams: parseFloat((grams * repeatQty).toFixed(3)),
                stock: prod ? parseFloat(prod.branch_stock || 0) : 0
            });
        }
    });

    if (oils.length === 0) {
        alert('يرجى اختيار زيت عطري واحد على الأقل وتحديد وزنه بالجرام.');
        return;
    }

    let bottleObj = null;
    if (bottleId !== 'none') {
        const bProd = ALL_BOTTLES.find(b => parseInt(b.id, 10) === parseInt(bottleId, 10));
        if (bProd) {
            bottleObj = {
                productId: bProd.id,
                name: bProd.name,
                quantity: repeatQty,
                stock: parseFloat(bProd.branch_stock || 0)
            };
        }
    }

    // إضافة التركيبة كعنصر تركيبة في السلة
    transferCart.push({
        type: 'mix',
        title: title,
        bottle: bottleObj,
        isWithoutBottle: (bottleId === 'none'),
        oils: oils,
        repeatQty: repeatQty
    });

    renderTransferCart();

    // إعادة تعيين نموذج التركيبة
    document.getElementById('mix-title-input').value = '';
    document.getElementById('mix-repeat-qty').value = '1';
    initMixOilRows();
    bottleSelect.value = 'none';
    onMixBottleChanged('none');
    alert('تمت إضافة التركيبة لقائمة التحويل بنجاح! 🎉');
}

// ===== 3. دوال الوصفات الجاهزة =====
function onPresetRecipeSelected(recipeId) {
    const box = document.getElementById('preset-recipe-box');
    const details = document.getElementById('preset-recipe-details');
    if (!recipeId) {
        box.style.display = 'none';
        return;
    }

    const rec = ALL_RECIPES.find(r => parseInt(r.id, 10) === parseInt(recipeId, 10));
    if (!rec) return;

    let compHtml = `<h4 style="margin:0 0 8px 0; color:var(--gold,#C9A84C);">📋 ${escapeHtml(rec.name)}</h4>`;
    compHtml += `<p style="margin:2px 0 8px 0; font-size:12px;"><strong>الزجاجة الموصى بها:</strong> ${escapeHtml(rec.bottle_name || 'غير محدد')}</p>`;
    compHtml += '<div style="font-size:12px; margin-bottom:8px;"><strong>مكونات الزيوت:</strong><ul style="margin:4px 0; padding-right:20px;">';
    
    (rec.components || []).forEach(c => {
        compHtml += `<li>${escapeHtml(c.perfume_name)}: <strong>${c.grams} جم</strong></li>`;
    });
    compHtml += '</ul></div>';

    details.innerHTML = compHtml;
    box.style.display = 'block';
}

function applyPresetRecipeToMixTab() {
    const select = document.getElementById('preset-recipe-select');
    const rec = ALL_RECIPES.find(r => parseInt(r.id, 10) === parseInt(select.value, 10));
    if (!rec) return;

    // نقل البيانات لتبويب التركيبة
    switchTransferTab('mix');
    document.getElementById('mix-title-input').value = rec.name;
    
    // محاولة اختيار نفس الزجاجة إن وجدت
    const bottleSelect = document.getElementById('mix-bottle-select');
    if (bottleSelect && rec.bottle_product_id) {
        bottleSelect.value = rec.bottle_product_id;
        onMixBottleChanged(bottleSelect.value);
    }

    // تفريغ وملء صفوف الزيوت
    const container = document.getElementById('mix-oil-rows-container');
    container.innerHTML = '';
    (rec.components || []).forEach(c => {
        addMixOilRow(c.perfume_product_id, c.grams);
    });
}

// ===== 4. إدارة سلة التحويل وحساب الإجماليات =====
function renderTransferCart() {
    const tbody = document.getElementById('transfer-cart-tbody');
    const emptyRow = document.getElementById('empty-cart-row');
    tbody.innerHTML = '';

    if (transferCart.length === 0) {
        tbody.appendChild(emptyRow);
        updateSummaryCounters(0, 0, 0);
        return;
    }

    let totalPieces = 0;
    let totalOilsGrams = 0;

    transferCart.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--line)';

        if (item.type === 'direct') {
            const isGram = item.unit === 'gram';
            const unitLabel = isGram ? 'جم' : 'قطعة';
            if (isGram) {
                totalOilsGrams += item.quantity;
            } else {
                totalPieces += item.quantity;
            }

            const isStockLow = item.quantity > item.stock;
            const stockColor = isStockLow ? '#ef4444' : '#10b981';

            tr.innerHTML = `
                <td style="padding:10px 8px;">
                    <div style="font-weight:bold; color:var(--ink);">${escapeHtml(item.name)}</div>
                    <span class="badge" style="font-size:10px; margin-top:2px;">${item.productType === 'perfume_gram' ? 'عطر بالجرام' : 'صنف مباشر'}</span>
                </td>
                <td style="padding:10px 8px; text-align:center;">
                    <div style="display:inline-flex; align-items:center; gap:4px;">
                        <button type="button" class="btn small" onclick="changeCartDirectQty(${index}, -1)">-</button>
                        <input type="number" value="${item.quantity}" step="any" min="0.000001" style="width:88px; text-align:center; padding:3px; border-radius:4px; border:1px solid var(--line); font-weight:bold;" onchange="updateCartDirectQtyManual(${index}, this.value)">
                        <button type="button" class="btn small" onclick="changeCartDirectQty(${index}, 1)">+</button>
                    </div>
                    <div style="font-size:10px; color:var(--muted);">${unitLabel}</div>
                </td>
                <td style="padding:10px 8px; text-align:center; font-weight:bold; color:${stockColor}; font-size:12px;">
                    ${item.stock} ${unitLabel}
                    ${isStockLow ? '<br><span style="font-size:10px; color:#ef4444;">(عجز)</span>' : ''}
                </td>
                <td style="padding:10px 8px; text-align:center;">
                    <button type="button" class="btn small danger" onclick="removeCartItem(${index})" style="padding:3px 8px;">🗑️</button>
                </td>
            `;
        } else if (item.type === 'mix') {
            // تركيبة
            let mixOilsHtml = '';
            item.oils.forEach(o => {
                totalOilsGrams += o.totalGrams;
                mixOilsHtml += `<div style="font-size:11px; margin-top:1px;">💧 ${escapeHtml(o.name)}: <strong>${o.totalGrams} جم</strong> <span class="muted">(متاح: ${o.stock} جم)</span></div>`;
            });

            let bottleHtml = '';
            if (item.bottle) {
                totalPieces += item.bottle.quantity;
                bottleHtml = `<div style="font-size:11px; color:var(--gold,#C9A84C); margin-top:2px;">🍾 الزجاجة: <strong>${escapeHtml(item.bottle.name)}</strong> (${item.bottle.quantity} قطعة) [متاح: ${item.bottle.stock}]</div>`;
            } else {
                bottleHtml = `<div style="font-size:11px; color:#10b981; margin-top:2px;">🚫 <strong>بدون زجاجة (نقل زيت خام فقط)</strong></div>`;
            }

            tr.innerHTML = `
                <td style="padding:10px 8px;">
                    <div style="font-weight:bold; color:var(--gold-dark,#9E7A2A); font-size:14px;">🧪 ${escapeHtml(item.title)}</div>
                    ${bottleHtml}
                    <div style="margin-top:4px; padding-right:6px; border-right:2px solid var(--gold,#C9A84C);">${mixOilsHtml}</div>
                </td>
                <td style="padding:10px 8px; text-align:center;">
                    <span style="font-weight:bold; font-size:14px; color:var(--primary);">${item.repeatQty} عبوة</span>
                </td>
                <td style="padding:10px 8px; text-align:center; font-size:11px; color:#10b981; font-weight:bold;">
                    مخصصة
                </td>
                <td style="padding:10px 8px; text-align:center;">
                    <button type="button" class="btn small danger" onclick="removeCartItem(${index})" style="padding:3px 8px;">🗑️</button>
                </td>
            `;
        }

        tbody.appendChild(tr);
    });

    updateSummaryCounters(transferCart.length, totalPieces, totalOilsGrams);
}

function updateSummaryCounters(totalLines, totalPieces, totalOilsGrams) {
    document.getElementById('cart-items-count').textContent = totalLines;
    document.getElementById('summary-total-lines').textContent = totalLines + ' بند';
    document.getElementById('summary-pieces-count').textContent = totalPieces + ' قطعة';
    document.getElementById('summary-oils-weight').textContent = totalOilsGrams.toFixed(3) + ' جم';
}

function changeCartDirectQty(index, delta) {
    if (!transferCart[index]) return;
    let newQty = parseFloat((transferCart[index].quantity + delta).toFixed(6));
    if (newQty < 0.000001) newQty = 0.000001;
    transferCart[index].quantity = newQty;
    renderTransferCart();
}

function updateCartDirectQtyManual(index, val) {
    if (!transferCart[index]) return;
    let newQty = parseFloat(val) || 0;
    if (newQty < 0.000001) newQty = 0.000001;
    transferCart[index].quantity = newQty;
    renderTransferCart();
}

function removeCartItem(index) {
    transferCart.splice(index, 1);
    renderTransferCart();
}

function clearTransferCart() {
    if (transferCart.length === 0) return;
    if (confirm('هل أنت متأكد من تفريغ كافة بنود التحويل؟')) {
        transferCart = [];
        renderTransferCart();
    }
}

// ===== 5. بناء الحقول المخفية لـ POST لخصم المخزون بدقة في السيرفر =====
function buildFormHiddenInputs() {
    const container = document.getElementById('transfer-hidden-inputs-container');
    container.innerHTML = '';

    transferCart.forEach(item => {
        if (item.type === 'direct') {
            container.appendChild(createHiddenInput('product_id[]', item.productId));
            container.appendChild(createHiddenInput('quantity[]', item.quantity));
            container.appendChild(createHiddenInput('item_notes[]', item.notes || ''));
        } else if (item.type === 'mix') {
            // إذا كان للتركيبة زجاجة: نرسل الزجاجة بالكمية المطلوبة
            if (item.bottle && !item.isWithoutBottle) {
                container.appendChild(createHiddenInput('product_id[]', item.bottle.productId));
                container.appendChild(createHiddenInput('quantity[]', item.bottle.quantity));
                container.appendChild(createHiddenInput('item_notes[]', `زجاجة تركيبة: ${item.title}`));
            }
            // إرسال كل زيت بكميته المحسوبة بالجرام
            item.oils.forEach(oil => {
                container.appendChild(createHiddenInput('product_id[]', oil.productId));
                container.appendChild(createHiddenInput('quantity[]', oil.totalGrams));
                const noteText = item.isWithoutBottle 
                    ? `تركيبة (بدون زجاجة): ${item.title}` 
                    : `تركيبة: ${item.title}`;
                container.appendChild(createHiddenInput('item_notes[]', noteText));
            });
        }
    });
}

function createHiddenInput(name, value) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    return input;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
</script>

<style>
.tab-btn.active {
    background: var(--gold, #C9A84C) !important;
    color: #fff !important;
    box-shadow: 0 2px 8px rgba(201,168,76,0.35);
}
.tab-btn:not(.active) {
    background: transparent;
    color: var(--muted);
}
.tab-btn:not(.active):hover {
    background: rgba(201,168,76,0.1);
    color: var(--gold-dark, #9E7A2A);
}
</style>
