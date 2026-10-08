<?php
$typeLabels = array_filter(product_type_labels(), fn($k) => in_array($k, ['bottle','perfume_gram','fixed'], true), ARRAY_FILTER_USE_KEY);
$familyLabels = perfume_family_labels();
$qualityLabels = quality_grade_labels();
unset($qualityLabels['']);
?>
<section class="page-head product-create-head">
    <div>
        <h2>➕ إضافة منتج جديد</h2>
        <p>كل حجم أو كوتة يتم إنشاؤها كمنتج مستقل وله باركود خاص وسعر بيع وشراء مستقل.</p>
    </div>
    <a class="btn" href="index.php?r=products">← رجوع للمنتجات</a>
</section>

<form class="product-create-layout" method="post" id="product-create-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="panel product-create-card product-type-card">
        <h3>1) اختر نوع المنتج</h3>
        <p class="muted">الخانات غير المسموح بها تختفي بالكامل حسب نوع المنتج.</p>
        <div class="product-type-options">
            <?php foreach ($typeLabels as $v => $l): ?>
                <label class="product-type-option" data-type-card="<?= e($v) ?>">
                    <input type="radio" name="type" value="<?= e($v) ?>" <?= $v === 'bottle' ? 'checked' : '' ?>>
                    <span class="product-type-icon"><?= $v === 'bottle' ? '🧴' : ($v === 'perfume_gram' ? '🧪' : '📦') ?></span>
                    <strong><?= e($l) ?></strong>
                    <small><?= $v === 'perfume_gram' ? 'سيتم إنشاء منتج وباركود مستقل لكل كوتة' : 'أضف أكثر من حجم بأسعار مختلفة' ?></small>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="panel product-create-card">
        <h3>2) البيانات الأساسية</h3>
        <div class="grid-form product-create-grid">
            <label style="position:relative;">اسم المنتج الأساسي
                <input name="name" id="product-name-input" required placeholder="مثال: كلاسيك / دهن عود / عبوة جاهزة" autocomplete="off">
                <small class="muted">سيتم إضافة الحجم أو الكوتة تلقائياً لاسم كل منتج.</small>
            </label>
            <label>حد تنبيه المخزون
                <input name="min_stock" type="number" step="any" min="0" placeholder="0">
            </label>
        </div>
    </div>
<!-- قائمة اقتراحات اسم المنتج - خارج الـ card لتجنب مشاكل overflow -->
<div id="product-name-suggestions" style="display:none; position:fixed; z-index:9999; background:var(--surface,#fff); border:1.5px solid #C9A84C; border-radius:10px; box-shadow:0 8px 24px rgba(0,0,0,0.18); max-height:220px; overflow-y:auto; min-width:260px;"></div>

    <div class="panel product-create-card type-section" data-product-field="bottle fixed">
        <h3>3) الأحجام والأسعار</h3>
        <p class="muted">كل صف = منتج مستقل بباركود مستقل. اكتب سعر بيع وسعر شراء لكل حجم. الباركود اختياري للمنتج الجاهز ويُترك فارغاً للتوليد التلقائي.</p>
        <div class="variant-table-wrap">
            <table class="variant-table" id="size-variants-table">
                <thead><tr><th>الحجم ml</th><th>سعر البيع</th><th>سعر الشراء</th><th>باركود اختياري</th><th></th></tr></thead>
                <tbody id="size-variants-body">
                    <tr>
                        <td><input name="variants[size][]" type="number" step="1" min="1" placeholder="100" required></td>
                        <td><input name="variants[sale_price][]" type="number" step="any" min="0" placeholder="0" required></td>
                        <td><input name="variants[cost_price][]" type="number" step="any" min="0" placeholder="0"></td>
                        <td><input name="variants[barcode][]" maxlength="40" placeholder="اتركه فارغاً للتوليد"></td>
                        <td><button type="button" class="btn small danger" onclick="removeVariantRow(this)">حذف</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn secondary" onclick="addSizeVariantRow()">+ إضافة حجم آخر</button>
    </div>

    <div class="panel product-create-card type-section" data-product-field="perfume_gram">
        <h3>3) الكوتات والأسعار</h3>
        <p class="muted">سيتم إنشاء منتج وباركود مستقل لكل كوتة. اكتب سعر الجرام وسعر الشراء لكل كوتة.</p>
        <div class="grid-form product-create-grid" style="margin-bottom:10px;">
            <label>عائلة العطر
                <select name="perfume_family">
                    <?php foreach ($familyLabels as $v => $l): ?>
                        <option value="<?= e($v) ?>"><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="variant-table-wrap">
            <table class="variant-table" id="quality-variants-table">
                <thead><tr><th>الكوتة</th><th>سعر الجرام</th><th>سعر الشراء</th></tr></thead>
                <tbody id="quality-variants-body">
                    <?php foreach ($qualityLabels as $v => $l): ?>
                    <tr>
                        <td>
                            <input type="hidden" name="variants[quality][]" value="<?= e($v) ?>">
                            <strong><?= e($l) ?></strong>
                        </td>
                        <td><input name="variants[price_per_gram][]" type="number" step="any" min="0" placeholder="0" required></td>
                        <td><input name="variants[cost_price][]" type="number" step="any" min="0" placeholder="0"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="product-create-actions panel">
        <button class="btn primary" style="min-width:180px;">حفظ وإنشاء المنتجات</button>
        <a class="btn" href="index.php?r=products">إلغاء</a>
    </div>
</form>

<script>
function currentProductType() {
    const checked = document.querySelector('input[name="type"]:checked');
    return checked ? checked.value : 'bottle';
}

function toggleProductFields() {
    const type = currentProductType();
    document.querySelectorAll('[data-type-card]').forEach(card => card.classList.toggle('active', card.dataset.typeCard === type));
    document.querySelectorAll('[data-product-field]').forEach(section => {
        const allowedTypes = section.dataset.productField.split(/\s+/);
        const show = allowedTypes.includes(type);
        section.hidden = !show;
        section.style.display = show ? '' : 'none';
        section.querySelectorAll('input, select, textarea, button').forEach(input => input.disabled = !show);
    });
}

function addSizeVariantRow() {
        document.getElementById('size-variants-body').insertAdjacentHTML('beforeend', `
        <tr>
            <td><input name="variants[size][]" type="number" step="1" min="1" placeholder="100" required></td>
            <td><input name="variants[sale_price][]" type="number" step="any" min="0" placeholder="0" required></td>
            <td><input name="variants[cost_price][]" type="number" step="any" min="0" placeholder="0"></td>
            <td><input name="variants[barcode][]" maxlength="40" placeholder="اتركه فارغاً للتوليد" onkeydown="preventEnterSubmit(event)"></td>
            <td><button type="button" class="btn small danger" onclick="removeVariantRow(this)">حذف</button></td>
        </tr>
    `);}

function removeVariantRow(btn) {
    const tbody = btn.closest('tbody');
    if (tbody.querySelectorAll('tr').length <= 1) {
        alert('يجب ترك صف واحد على الأقل.');
        return;
    }
    btn.closest('tr').remove();
}

// منع إرسال النموذج بالضغط على Enter في حقل الباركود
function preventEnterSubmit(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        e.stopPropagation();
        // انتقل للحقل التالي
        const inputs = Array.from(document.querySelectorAll('#product-create-form input:not([disabled]), #product-create-form select:not([disabled])'));
        const idx = inputs.indexOf(e.target);
        if (idx > -1 && idx < inputs.length - 1) inputs[idx + 1].focus();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="type"]').forEach(input => input.addEventListener('change', toggleProductFields));
    toggleProductFields();

    // منع الإرسال بالضغط على Enter في حقول الباركود
    document.querySelectorAll('input[name="variants[barcode][]"]').forEach(input => {
        input.addEventListener('keydown', preventEnterSubmit);
    });
    // منع الإرسال بالضغط على Enter في حقل الاسم
    const nameInput = document.querySelector('input[name="name"]');
    if (nameInput) {
        nameInput.addEventListener('keydown', preventEnterSubmit);
    }
});
</script>

<script>
// ===== Autocomplete: عرض المنتجات الموجودة عند الكتابة =====
(function() {
    const nameInput = document.getElementById('product-name-input');
    const suggestionsBox = document.getElementById('product-name-suggestions');
    if (!nameInput || !suggestionsBox) return;

    let debounceTimer = null;

    // تحديث موضع القائمة تحت الحقل مباشرة
    function positionDropdown() {
        const rect = nameInput.getBoundingClientRect();
        suggestionsBox.style.top    = (rect.bottom + window.scrollY + 3) + 'px';
        suggestionsBox.style.right  = 'auto';
        suggestionsBox.style.left   = (rect.left + window.scrollX) + 'px';
        suggestionsBox.style.width  = rect.width + 'px';
    }

    nameInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        if (q.length < 2) {
            suggestionsBox.style.display = 'none';
            suggestionsBox.innerHTML = '';
            return;
        }
        debounceTimer = setTimeout(() => {
            fetch('index.php?r=product_name_search&q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(data => {
                    if (!data.results || data.results.length === 0) {
                        suggestionsBox.style.display = 'none';
                        suggestionsBox.innerHTML = '';
                        return;
                    }
                    suggestionsBox.innerHTML = data.results.map(p => `
                        <div class="autocomplete-item" data-name="${p.name.replace(/"/g,'&quot;')}" style="padding:10px 14px; font-size:13px; color:var(--ink,#1a1a1a); border-bottom:1px solid rgba(201,168,76,0.15); cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition:background 0.15s;" onmousedown="event.preventDefault()" onclick="selectProductName(this)">
                            <span style="font-weight:700;">${p.name}</span>
                            <span style="font-size:11px; color:#9E7A2A; background:rgba(201,168,76,0.12); padding:2px 8px; border-radius:5px;">${p.type_label}</span>
                        </div>
                    `).join('');
                    positionDropdown();
                    suggestionsBox.style.display = 'block';
                })
                .catch(() => { suggestionsBox.style.display = 'none'; });
        }, 300);
    });

    // ملء الخانة عند الاختيار
    window.selectProductName = function(el) {
        const name = el.getAttribute('data-name');
        nameInput.value = name;
        suggestionsBox.style.display = 'none';
        nameInput.focus();
    };

    // تحديث موضع القائمة لو تغير حجم الصفحة
    window.addEventListener('resize', () => { if (suggestionsBox.style.display !== 'none') positionDropdown(); });
    window.addEventListener('scroll', () => { if (suggestionsBox.style.display !== 'none') positionDropdown(); }, true);

    // إخفاء عند الضغط خارج الحقل
    document.addEventListener('click', function(e) {
        if (!nameInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
            suggestionsBox.style.display = 'none';
        }
    });

    // Hover effect
    suggestionsBox.addEventListener('mouseover', function(e) {
        const item = e.target.closest('.autocomplete-item');
        if (item) item.style.background = 'rgba(201,168,76,0.08)';
    });
    suggestionsBox.addEventListener('mouseout', function(e) {
        const item = e.target.closest('.autocomplete-item');
        if (item) item.style.background = '';
    });
})();
</script>
