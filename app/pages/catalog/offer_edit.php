<?php
$id = (int)($_GET['id'] ?? 0);
$offer = find_offer_with_items($id);

if (!$offer) {
    echo '<section class="page-head"><h2>العرض غير موجود</h2><p>لم يتم العثور على العرض المطلوب تعديله.</p></section>';
    return;
}

$directProducts = array_values(array_filter(all_products(), fn($p) => in_array($p['type'], ['fixed', 'bottle'], true)));
$bottles = all_products('bottle');
$perfumes = all_products('perfume_gram');

// Split existing items into direct and recipe arrays for JavaScript
$initDirect = [];
$initRecipes = [];

foreach ($offer['items'] as $it) {
    if ($it['item_type'] === 'product') {
        $initDirect[] = [
            'product_id'  => (int)$it['product_id'],
            'name'        => $it['product_name'] ?: 'منتج',
            'quantity'    => (float)$it['quantity'],
            'unit_price'  => (float)$it['product_price'],
            'total_price' => (float)$it['product_price'] * (float)$it['quantity'],
        ];
    } else {
        $oils = [];
        $oilsPrice = 0;
        foreach ($it['components'] as $c) {
            $grams = (float)$c['grams'];
            $pricePerGram = (float)$c['price_per_gram'];
            $oilsPrice += ($grams * $pricePerGram);
            $oils[] = [
                'perfume_id'     => (int)$c['perfume_product_id'],
                'name'           => $c['perfume_name'] ?: 'زيت عطري',
                'grams'          => $grams,
                'price_per_gram' => $pricePerGram,
                'grade'          => $c['quality_grade'] ?? '',
                'family'         => $c['perfume_family'] ?? '',
            ];
        }
        $bottlePrice = (float)($it['bottle_price'] ?? 0);
        $singlePrice = $bottlePrice + $oilsPrice;
        $qty = (float)$it['quantity'];

        $initRecipes[] = [
            'name'             => $it['recipe_name'] ?: 'تركيبة خاصة',
            'bottle_id'        => $it['bottle_product_id'] ? (int)$it['bottle_product_id'] : null,
            'bottle_name'      => $it['bottle_name'] ?: 'بدون زجاجة',
            'quantity'         => $qty,
            'unit_price'       => $singlePrice,
            'total_price'      => $singlePrice * $qty,
            'oil_category'     => $it['oil_category'] ?? 'any',
            'allowed_families' => $it['allowed_families'] ?? null,
            'oils'             => $oils,
        ];
    }
}
?>
<section class="page-head">
    <div>
        <h2>✏️ تعديل العرض: <?= e($offer['name']) ?></h2>
        <p>تعديل بيانات الباكدج أو الأسعار أو الأصناف والتركيبات المشمولة في العرض.</p>
    </div>
    <a class="btn" href="index.php?r=offers">← رجوع للعروض</a>
</section>

<form method="post" action="index.php?r=offer_edit" id="offer-edit-form" class="grid-layout" style="gap: 20px;">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= e($offer['id']) ?>">

    <!-- 1. بيانات العرض الأساسية -->
    <div class="panel">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--gold-dark, #b98418); border-bottom: 1.5px solid var(--line); padding-bottom: 8px;">
            1️⃣ البيانات الأساسية للعرض
        </h3>
        <div class="grid-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
            <label style="grid-column: span 2;">اسم العرض / الباكدج *
                <input name="name" required value="<?= e($offer['name']) ?>" autocomplete="off">
            </label>

            <label>باركود العرض
                <div style="display: flex; gap: 6px;">
                    <input name="barcode" id="offer-barcode-input" value="<?= e($offer['barcode'] ?? '') ?>" style="font-family: monospace;">
                    <button type="button" class="btn small secondary" onclick="generateRandomBarcode()" title="توليد باركود تلقائي">⚡ توليد</button>
                </div>
            </label>

            <label>حالة العرض
                <select name="is_active">
                    <option value="1" <?= (int)$offer['is_active'] === 1 ? 'selected' : '' ?>>🟢 نشط ومتاح بالكاشير</option>
                    <option value="0" <?= (int)$offer['is_active'] === 0 ? 'selected' : '' ?>>⚪ متوقف / غير نشط</option>
                </select>
            </label>

            <label>تاريخ بدء العرض *
                <input name="start_date" type="date" required value="<?= e($offer['start_date']) ?>">
            </label>

            <label>تاريخ نهاية العرض *
                <input name="end_date" type="date" required value="<?= e($offer['end_date']) ?>">
            </label>

            <label style="grid-column: span 2;">ملاحظات أو وصف إضافي للعرض
                <input name="notes" value="<?= e($offer['notes'] ?? '') ?>" placeholder="ملاحظات داخلية أو نص تسويقي...">
            </label>
        </div>
    </div>

    <!-- 2. محتويات العرض -->
    <div class="panel">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--gold-dark, #b98418); border-bottom: 1.5px solid var(--line); padding-bottom: 8px;">
            2️⃣ مكونات ومحتويات الباكدج
        </h3>

        <div class="segmented-tabs" style="margin-bottom: 15px;">
            <button type="button" class="active" id="btn-tab-direct" onclick="switchItemTab('direct')">📦 إضافة منتج جاهز</button>
            <button type="button" id="btn-tab-recipe" onclick="switchItemTab('recipe')">🧪 إضافة تركيبة عطرية مخصصة</button>
        </div>

        <!-- أداة إضافة منتج جاهز -->
        <div id="add-direct-item-box" style="background: var(--surface-soft, rgba(0,0,0,0.02)); border: 1.5px dashed var(--line); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end;">
                <label>اختر المنتج الجاهز
                    <select id="direct-product-select">
                        <option value="">-- اختر صنف جاهز --</option>
                        <?php foreach ($directProducts as $p): ?>
                            <option value="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>" data-price="<?= e($p['sale_price']) ?>">
                                <?= e($p['name']) ?> (<?= money($p['sale_price']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>الكمية في الباكدج
                    <input type="number" id="direct-product-qty" min="1" step="1" value="1">
                </label>
                <label>سعر البيع الأصلي
                    <input type="text" id="direct-product-price" readonly placeholder="0.00 ج.م" style="background: var(--surface);">
                </label>
                <button type="button" class="btn primary" onclick="addDirectProductToOffer()" style="height: 38px;">+ أضف للباكدج</button>
            </div>
        </div>

        <!-- أداة إضافة تركيبة عطرية مخصصة -->
        <div id="add-recipe-item-box" style="display: none; background: var(--surface-soft, rgba(0,0,0,0.02)); border: 1.5px dashed var(--line); border-radius: 10px; padding: 15px; margin-bottom: 20px;">
            <h4 style="margin: 0 0 10px 0; font-size: 13.5px; color: var(--primary);">تجهيز تركيبة عطرية مشمولة بالعرض:</h4>
            
            <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 10px; margin-bottom: 12px;">
                <label>اسم وصفة التركيبة
                    <input id="builder-recipe-name" placeholder="مثال: برفان عود ملكي خاص" value="تركيبة عطرية خاصة">
                </label>
                <label>نوع الزجاجة المستخدمة
                    <select id="builder-bottle-select">
                        <option value="">-- بدون زجاجة --</option>
                        <?php foreach ($bottles as $b): ?>
                            <option value="<?= e($b['id']) ?>" data-name="<?= e($b['name']) ?>" data-price="<?= e($b['sale_price']) ?>">
                                <?= e($b['name']) ?> (<?= e($b['size_ml']) ?>ml) - (<?= money($b['sale_price']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>عدد الزجاجات
                    <input type="number" id="builder-recipe-qty" min="1" step="1" value="1">
                </label>
            </div>

            <!-- تصنيف الزيت العطري المطلوب في هذا العرض -->
            <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 15px; align-items: start;">
                    <label style="font-weight: 700;">تصنيف الزيت المسموح به في هذا العرض *
                        <select id="builder-oil-category" onchange="toggleMixedCategorySelection()" style="font-weight: 600; margin-top: 5px;">
                            <option value="any">🌐 شامل (أي تصنيف زيوت عطرية)</option>
                            <option value="oriental">🏺 زيوت شرقية فقط (شرقي)</option>
                            <option value="french">🌸 زيوت فرنسية فقط (فرنسي)</option>
                            <option value="niche">💎 زيوت نيش فقط (نيش)</option>
                            <option value="niche_liter">👑 زيوت الترا نيش فقط (الترا نيش)</option>
                            <option value="mixed">🔀 تصنيف مختلط (اختار أكثر من صنف مع بعض)</option>
                        </select>
                        <small class="muted" style="display: block; margin-top: 4px;">في الكاشير: إذا حاول الموظف اختيار زيت غير مطابق لشروط هذا التصنيف سيتم تنبيهه ومنعه.</small>
                    </label>

                    <div id="builder-mixed-box" style="display: none; background: var(--surface-soft, rgba(201,168,76,0.06)); border: 1.5px dashed var(--gold, #c9a84c); border-radius: 8px; padding: 10px;">
                        <div style="font-size: 12px; font-weight: 700; color: var(--gold-dark, #b98418); margin-bottom: 6px;">
                            حدد الأصناف المسموحة معاً في هذه التركيبة:
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap; font-size: 13px; font-weight: 600;">
                            <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer; background: var(--surface); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--line);">
                                <input type="checkbox" name="mixed_category" value="oriental" checked> شرقي
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer; background: var(--surface); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--line);">
                                <input type="checkbox" name="mixed_category" value="french" checked> فرنسي
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer; background: var(--surface); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--line);">
                                <input type="checkbox" name="mixed_category" value="niche"> نيش
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer; background: var(--surface); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--line);">
                                <input type="checkbox" name="mixed_category" value="niche_liter"> الترا نيش
                            </label>
                            <label style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer; background: var(--surface); padding: 4px 8px; border-radius: 6px; border: 1px solid var(--line);">
                                <input type="checkbox" name="mixed_category" value="unilateral"> احادي
                            </label>
                        </div>
                        <div style="margin-top: 8px; display: flex; gap: 6px; align-items: center;">
                            <span style="font-size: 11px; color: var(--muted);">اختيارات سريعة:</span>
                            <button type="button" class="btn small secondary" style="padding: 2px 8px; font-size: 11px;" onclick="setMixedPresets(['oriental', 'french'])">شرقي + فرنسي</button>
                            <button type="button" class="btn small secondary" style="padding: 2px 8px; font-size: 11px;" onclick="setMixedPresets(['french', 'niche'])">فرنسي + نيش</button>
                            <button type="button" class="btn small secondary" style="padding: 2px 8px; font-size: 11px;" onclick="setMixedPresets(['oriental', 'niche'])">شرقي + نيش</button>
                        </div>
                    </div>
                </div>
            </div>

            <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 8px; padding: 10px; margin-bottom: 12px;">
                <div style="font-size: 12px; font-weight: 700; color: var(--muted); margin-bottom: 8px;">مكونات الزيوت العطرية بالجرام:</div>
                <div style="display: flex; gap: 10px; align-items: end; flex-wrap: wrap;">
                    <label style="flex: 2; min-width: 200px;">اختر الزيت العطري
                        <select id="builder-oil-select">
                            <option value="">-- اختر زيتاً عطرياً --</option>
                            <?php foreach ($perfumes as $p): ?>
                                <option value="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>" data-price="<?= e($p['price_per_gram']) ?>" data-grade="<?= e($p['quality_grade']) ?>" data-family="<?= e($p['perfume_family']) ?>">
                                    <?= e($p['name']) ?> [<?= e(perfume_family_label($p['perfume_family'])) ?>] (<?= e($p['quality_grade'] ?: '-') ?>) - <?= money($p['price_per_gram']) ?>/جم
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="flex: 1; min-width: 100px;">الوزن بالجرام
                        <input type="number" id="builder-oil-grams" step="any" min="0.1" placeholder="مثال: 15">
                    </label>
                    <button type="button" class="btn secondary small" onclick="addOilToCurrentBuilder()" style="height: 38px;">+ أضف الزيت</button>
                </div>

                <div id="builder-oils-list" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px;"></div>
            </div>

            <button type="button" class="btn primary" onclick="addCustomRecipeToOffer()">+ حفظ التركيبة وإضافتها للباكدج</button>
        </div>

        <!-- جدول محتويات العرض -->
        <h4 style="margin: 15px 0 8px 0; font-size: 13.5px; font-weight: 700;">الأصناف المضافة للباكدج:</h4>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; background: var(--surface); border-radius: 8px; border: 1px solid var(--line);">
                <thead>
                    <tr style="border-bottom: 2px solid var(--line); text-align: right; background: var(--surface-soft, rgba(0,0,0,0.02));">
                        <th style="padding: 8px 10px;">النوع</th>
                        <th style="padding: 8px 10px;">الصنف / التركيبة</th>
                        <th style="padding: 8px 10px; text-align: center;">الكمية</th>
                        <th style="padding: 8px 10px; text-align: center;">السعر المفترض</th>
                        <th style="padding: 8px 10px; text-align: center;">إجراء</th>
                    </tr>
                </thead>
                <tbody id="offer-items-tbody"></tbody>
            </table>
        </div>
        <div id="hidden-offer-inputs"></div>
    </div>

    <!-- 3. تسعير العرض -->
    <div class="panel" style="background: linear-gradient(135deg, var(--surface), rgba(201,168,76,0.05)); border: 2px solid var(--gold-soft2, rgba(201,168,76,0.3));">
        <h3 style="margin-top: 0; margin-bottom: 15px; color: var(--gold-dark, #b98418); border-bottom: 1.5px solid var(--line); padding-bottom: 8px;">
            3️⃣ تحديد سعر العرض والخصم
        </h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: center;">
            <label>إجمالي السعر الأصلي للمكونات (قبل الخصم)
                <input type="number" step="any" min="0" name="price_before" id="offer-price-before" value="<?= e($offer['price_before']) ?>" style="font-size: 16px; font-weight: 700; background: var(--surface);" oninput="recalculateOfferDiscount()">
            </label>

            <label>سعر البيع النهائي للعرض (بعد الخصم) *
                <input type="number" step="any" min="0" name="price_after" id="offer-price-after" required value="<?= e($offer['price_after']) ?>" style="font-size: 18px; font-weight: 800; color: var(--primary);" oninput="recalculateOfferDiscount()">
            </label>

            <div style="background: var(--surface); padding: 15px; border-radius: 10px; border: 1px solid var(--line); text-align: center;">
                <div style="font-size: 12px; color: var(--muted); margin-bottom: 4px;">نسبة التوفير للعميل:</div>
                <div id="discount-badge" style="font-size: 16px; font-weight: 800; color: var(--success, #10b981);">
                    0.00 ج.م (0%)
                </div>
            </div>
        </div>

        <div style="margin-top: 25px; display: flex; gap: 12px; justify-content: flex-end;">
            <a href="index.php?r=offers" class="btn secondary">إلغاء</a>
            <button type="submit" class="btn primary" style="min-width: 180px; font-size: 15px; font-weight: bold;">
                💾 حفظ التعديلات
            </button>
        </div>
    </div>
</form>

<script>
let offerDirectItems = <?= json_encode($initDirect) ?>;
let offerRecipeItems = <?= json_encode($initRecipes) ?>;
let currentRecipeOils = [];

function switchItemTab(tab) {
    const btnDirect = document.getElementById('btn-tab-direct');
    const btnRecipe = document.getElementById('btn-tab-recipe');
    const boxDirect = document.getElementById('add-direct-item-box');
    const boxRecipe = document.getElementById('add-recipe-item-box');

    if (tab === 'direct') {
        btnDirect.classList.add('active');
        btnRecipe.classList.remove('active');
        boxDirect.style.display = 'block';
        boxRecipe.style.display = 'none';
    } else {
        btnDirect.classList.remove('active');
        btnRecipe.classList.add('active');
        boxDirect.style.display = 'none';
        boxRecipe.style.display = 'block';
    }
}

document.getElementById('direct-product-select').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const price = parseFloat(opt.getAttribute('data-price')) || 0;
    document.getElementById('direct-product-price').value = price > 0 ? price.toFixed(2) + ' ج.م' : '0.00 ج.م';
});

function addDirectProductToOffer() {
    const sel = document.getElementById('direct-product-select');
    const id = parseInt(sel.value);
    if (!id) return alert('الرجاء اختيار منتج جاهز أولاً.');

    const opt = sel.options[sel.selectedIndex];
    const name = opt.getAttribute('data-name');
    const price = parseFloat(opt.getAttribute('data-price')) || 0;
    const qty = parseFloat(document.getElementById('direct-product-qty').value) || 1;

    offerDirectItems.push({
        product_id: id,
        name: name,
        quantity: qty,
        unit_price: price,
        total_price: price * qty
    });

    sel.value = '';
    document.getElementById('direct-product-qty').value = '1';
    document.getElementById('direct-product-price').value = '0.00 ج.م';

    renderOfferItemsTable(true);
}

const FAMILIES_AR = {
    'oriental': 'شرقي',
    'french': 'فرنسي',
    'niche': 'نيش',
    'niche_liter': 'الترا نيش',
    'unilateral': 'احادي'
};

function toggleMixedCategorySelection() {
    const cat = document.getElementById('builder-oil-category')?.value;
    const mixedBox = document.getElementById('builder-mixed-box');
    if (mixedBox) {
        mixedBox.style.display = (cat === 'mixed') ? 'block' : 'none';
    }
}

function setMixedPresets(list) {
    const checkboxes = document.querySelectorAll('#builder-mixed-box input[name="mixed_category"]');
    checkboxes.forEach(cb => {
        cb.checked = list.includes(cb.value);
    });
}

function addOilToCurrentBuilder() {
    const sel = document.getElementById('builder-oil-select');
    const perfumeId = parseInt(sel.value);
    if (!perfumeId) return alert('الرجاء اختيار زيت عطري أولاً.');

    const grams = parseFloat(document.getElementById('builder-oil-grams').value) || 0;
    if (grams <= 0) return alert('الرجاء إدخال وزن الجرامات.');

    const opt = sel.options[sel.selectedIndex];
    const name = opt.getAttribute('data-name');
    const pricePerGram = parseFloat(opt.getAttribute('data-price')) || 0;
    const grade = opt.getAttribute('data-grade') || '';
    const family = opt.getAttribute('data-family') || '';

    currentRecipeOils.push({
        perfume_id: perfumeId,
        name: name,
        grams: grams,
        price_per_gram: pricePerGram,
        grade: grade,
        family: family
    });

    sel.value = '';
    document.getElementById('builder-oil-grams').value = '';
    renderCurrentRecipeOilsList();
}

function removeCurrentRecipeOil(idx) {
    currentRecipeOils.splice(idx, 1);
    renderCurrentRecipeOilsList();
}

function renderCurrentRecipeOilsList() {
    const container = document.getElementById('builder-oils-list');
    container.innerHTML = '';
    currentRecipeOils.forEach((oil, idx) => {
        const famText = FAMILIES_AR[oil.family] ? ` [${FAMILIES_AR[oil.family]}]` : '';
        const tag = document.createElement('div');
        tag.style.cssText = 'display: inline-flex; align-items: center; gap: 6px; background: var(--surface-soft, rgba(0,0,0,0.06)); border: 1px solid var(--line); border-radius: 6px; padding: 3px 8px; font-size: 12px; font-weight: 600;';
        tag.innerHTML = `
            <span>🧪 ${oil.name}${famText} (${oil.grams}جم)</span>
            <button type="button" onclick="removeCurrentRecipeOil(${idx})" style="background:none; border:none; color:var(--danger); cursor:pointer; padding:0 2px; font-weight:bold;">×</button>
        `;
        container.appendChild(tag);
    });
}

function addCustomRecipeToOffer() {
    const name = document.getElementById('builder-recipe-name').value.trim() || 'تركيبة عطرية خاصة';
    const bottleSel = document.getElementById('builder-bottle-select');
    const bottleId = bottleSel.value ? parseInt(bottleSel.value) : null;
    let bottleName = 'بدون زجاجة';
    let bottlePrice = 0;
    if (bottleId) {
        const bOpt = bottleSel.options[bottleSel.selectedIndex];
        bottleName = bOpt.getAttribute('data-name');
        bottlePrice = parseFloat(bOpt.getAttribute('data-price')) || 0;
    }
    const qty = parseFloat(document.getElementById('builder-recipe-qty').value) || 1;

    if (currentRecipeOils.length === 0 && !bottleId) {
        return alert('الرجاء إضافة مكونات زيتية أو اختيار زجاجة للتركيبة.');
    }

    const catSel = document.getElementById('builder-oil-category');
    const oilCategory = catSel ? catSel.value : 'any';
    let allowedFamilies = [];

    if (oilCategory === 'mixed') {
        const checked = document.querySelectorAll('#builder-mixed-box input[name="mixed_category"]:checked');
        checked.forEach(cb => allowedFamilies.push(cb.value));
        if (allowedFamilies.length === 0) {
            return alert('الرجاء اختيار تصنيف واحد على الأقل للمختلط (مثلاً شرقي وفرنسي).');
        }
    } else if (oilCategory === 'any') {
        allowedFamilies = ['oriental', 'french', 'niche', 'niche_liter', 'unilateral'];
    } else {
        allowedFamilies = [oilCategory];
    }

    let oilsPrice = currentRecipeOils.reduce((sum, o) => sum + (o.grams * o.price_per_gram), 0);
    let singleRecipePrice = bottlePrice + oilsPrice;

    offerRecipeItems.push({
        name: name,
        bottle_id: bottleId,
        bottle_name: bottleName,
        quantity: qty,
        unit_price: singleRecipePrice,
        total_price: singleRecipePrice * qty,
        oil_category: oilCategory,
        allowed_families: allowedFamilies.join(','),
        oils: [...currentRecipeOils]
    });

    document.getElementById('builder-recipe-name').value = 'تركيبة عطرية خاصة';
    bottleSel.value = '';
    document.getElementById('builder-recipe-qty').value = '1';
    if (catSel) {
        catSel.value = 'any';
        toggleMixedCategorySelection();
    }
    currentRecipeOils = [];
    renderCurrentRecipeOilsList();

    renderOfferItemsTable(true);
}

function removeDirectItem(idx) {
    offerDirectItems.splice(idx, 1);
    renderOfferItemsTable(true);
}

function removeRecipeItem(idx) {
    offerRecipeItems.splice(idx, 1);
    renderOfferItemsTable(true);
}

function renderOfferItemsTable(autoUpdatePrice = false) {
    const tbody = document.getElementById('offer-items-tbody');
    const hiddenContainer = document.getElementById('hidden-offer-inputs');
    tbody.innerHTML = '';
    hiddenContainer.innerHTML = '';

    const hasItems = (offerDirectItems.length > 0 || offerRecipeItems.length > 0);

    if (!hasItems) {
        tbody.innerHTML = `
            <tr id="empty-items-row">
                <td colspan="5" class="muted" style="text-align: center; padding: 20px;">
                    لم يتم إضافة أي أصناف للباكدج بعد. اختر منتجاً جاهزاً أو أنشئ تركيبة من الخيارات بالأعلى.
                </td>
            </tr>
        `;
        if (autoUpdatePrice) {
            document.getElementById('offer-price-before').value = '0.00';
        }
        recalculateOfferDiscount();
        return;
    }

    let grandOriginalTotal = 0;

    offerDirectItems.forEach((item, idx) => {
        grandOriginalTotal += item.total_price;
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--line)';
        tr.innerHTML = `
            <td style="padding: 8px 10px;"><span class="badge direct">📦 منتج جاهز</span></td>
            <td style="padding: 8px 10px; font-weight: 600;">${item.name}</td>
            <td style="padding: 8px 10px; text-align: center;"><strong>${item.quantity}</strong></td>
            <td style="padding: 8px 10px; text-align: center;">${item.total_price.toFixed(2)} ج.م</td>
            <td style="padding: 8px 10px; text-align: center;">
                <button type="button" class="btn small danger" onclick="removeDirectItem(${idx})">حذف</button>
            </td>
        `;
        tbody.appendChild(tr);

        hiddenContainer.innerHTML += `
            <input type="hidden" name="items[product_id][]" value="${item.product_id}">
            <input type="hidden" name="items[quantity][]" value="${item.quantity}">
        `;
    });

    offerRecipeItems.forEach((rec, rIdx) => {
        grandOriginalTotal += rec.total_price;
        const oilsSummary = rec.oils.map(o => `${o.name} (${o.grams}جم)`).join(', ');

        let catBadge = '';
        if (rec.oil_category === 'mixed' && rec.allowed_families) {
            const listNames = rec.allowed_families.split(',').map(f => FAMILIES_AR[f] || f).join(' + ');
            catBadge = `<span class="badge" style="background: rgba(168,85,247,0.12); color: #7e22ce; font-weight: 700; font-size: 11px;">🔀 مختلط: ${listNames}</span>`;
        } else if (rec.oil_category && rec.oil_category !== 'any') {
            catBadge = `<span class="badge" style="background: rgba(59,130,246,0.12); color: #1d4ed8; font-weight: 700; font-size: 11px;">🏷️ ${FAMILIES_AR[rec.oil_category] || rec.oil_category} فقط</span>`;
        } else {
            catBadge = `<span class="badge" style="background: rgba(16,185,129,0.12); color: #047857; font-weight: 700; font-size: 11px;">🌐 شامل أي تصنيف</span>`;
        }

        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid var(--line)';
        tr.innerHTML = `
            <td style="padding: 8px 10px;"><span class="badge custom">🧪 تركيبة</span></td>
            <td style="padding: 8px 10px;">
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span style="font-weight: 600;">${rec.name} (${rec.bottle_name})</span>
                    ${catBadge}
                </div>
                ${oilsSummary ? `<div style="font-size: 11px; color: var(--muted); margin-top: 3px;">${oilsSummary}</div>` : ''}
            </td>
            <td style="padding: 8px 10px; text-align: center;"><strong>${rec.quantity}</strong></td>
            <td style="padding: 8px 10px; text-align: center;">${rec.total_price.toFixed(2)} ج.م</td>
            <td style="padding: 8px 10px; text-align: center;">
                <button type="button" class="btn small danger" onclick="removeRecipeItem(${rIdx})">حذف</button>
            </td>
        `;
        tbody.appendChild(tr);

        hiddenContainer.innerHTML += `
            <input type="hidden" name="recipes[${rIdx}][name]" value="${rec.name.replace(/"/g, '&quot;')}">
            <input type="hidden" name="recipes[${rIdx}][bottle_id]" value="${rec.bottle_id || ''}">
            <input type="hidden" name="recipes[${rIdx}][quantity]" value="${rec.quantity}">
            <input type="hidden" name="recipes[${rIdx}][oil_category]" value="${rec.oil_category || 'any'}">
            <input type="hidden" name="recipes[${rIdx}][allowed_families]" value="${rec.allowed_families || ''}">
        `;
        rec.oils.forEach((oil, oIdx) => {
            hiddenContainer.innerHTML += `
                <input type="hidden" name="recipes[${rIdx}][oils][${oIdx}][perfume_id]" value="${oil.perfume_id}">
                <input type="hidden" name="recipes[${rIdx}][oils][${oIdx}][grams]" value="${oil.grams}">
            `;
        });
    });

    if (autoUpdatePrice) {
        document.getElementById('offer-price-before').value = grandOriginalTotal.toFixed(2);
    }
    recalculateOfferDiscount();
}

function recalculateOfferDiscount() {
    const before = parseFloat(document.getElementById('offer-price-before').value) || 0;
    const after = parseFloat(document.getElementById('offer-price-after').value) || 0;
    const badge = document.getElementById('discount-badge');

    if (before <= 0 || after <= 0) {
        badge.textContent = '0.00 ج.م (0%)';
        badge.style.color = 'var(--muted)';
        return;
    }

    const saving = Math.max(0, before - after);
    const percent = Math.round((saving / before) * 100);

    if (saving > 0) {
        badge.textContent = `وفر ${saving.toFixed(2)} ج.م (${percent}% خصم)`;
        badge.style.color = 'var(--success, #10b981)';
    } else {
        badge.textContent = `بدون خصم (0%)`;
        badge.style.color = 'var(--muted)';
    }
}

function generateRandomBarcode() {
    let rand = '622' + Math.floor(100000000 + Math.random() * 900000000);
    document.getElementById('offer-barcode-input').value = rand;
}

// Initial render
document.addEventListener('DOMContentLoaded', () => {
    renderOfferItemsTable(false);
    recalculateOfferDiscount();
});
</script>
