<?php
$familyLabels = perfume_family_labels();
$qualityLabels = quality_grade_labels();
unset($qualityLabels['']);
?>
<section class="page-head">
    <div>
        <h2>⚡ إضافة متعددة سريعة</h2>
        <p>قم بإضافة العديد من المنتجات والزيوت المختلفة في نفس الوقت بضغطة زر واحدة.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button type="button" class="btn success" onclick="saveBulkProducts()">💾 حفظ جميع المنتجات</button>
        <a class="btn" href="index.php?r=product_create">← إضافة منتج مفرد</a>
        <a class="btn" href="index.php?r=products">← المنتجات</a>
    </div>
</section>

<div class="panel" style="overflow-x: auto; padding: 20px;">
    <table class="data-table" style="min-width: 1200px;">
        <thead>
            <tr>
                <th style="width: 120px;">النوع</th>
                <th style="width: 180px;">الاسم</th>
                <th style="width: 100px;" title="الحجم (مل) أو رقم الكوتة (الزيت)">الحجم/الكوتة</th>
                <th style="width: 100px;">سعر البيع</th>
                <th style="width: 100px;">سعر التكلفة</th>
                <th style="width: 100px;">تنبيه المخزون</th>
                <th style="width: 150px;">العائلة (زيت)</th>
                <th style="width: 100px;">الجودة (زيت)</th>
                <th style="width: 100px;">سعر הגرام (زيت)</th>
                <th style="width: 120px;">الباركود</th>
                <th style="width: 50px;">🗑️</th>
            </tr>
        </thead>
        <tbody id="bulk-add-tbody">
            <!-- Rows injected by JS -->
        </tbody>
    </table>
    
    <div style="margin-top: 15px;">
        <button type="button" class="btn primary small" onclick="addBulkRow()">+ إضافة صف جديد</button>
    </div>
</div>

<script>
const familyLabels = <?= json_encode($familyLabels) ?>;
const qualityLabels = <?= json_encode($qualityLabels) ?>;

let rowCount = 0;

function addBulkRow() {
    rowCount++;
    const tbody = document.getElementById('bulk-add-tbody');
    const tr = document.createElement('tr');
    tr.id = `bulk-row-${rowCount}`;
    
    let familyOptions = '<option value="">-- العائلة --</option>';
    for(let k in familyLabels) familyOptions += `<option value="${k}">${familyLabels[k]}</option>`;
    
    let qualityOptions = '';
    for(let k in qualityLabels) qualityOptions += `<option value="${k}">${qualityLabels[k]}</option>`;

    tr.innerHTML = `
        <td>
            <select name="type[]" class="bulk-type-select" style="width:100%;" onchange="toggleBulkFields(this)">
                <option value="bottle">🧴 زجاجة</option>
                <option value="perfume_gram">🧪 عطر بالجرام</option>
                <option value="fixed">📦 منتج جاهز</option>
            </select>
        </td>
        <td><input type="text" name="name[]" required style="width:100%;" placeholder="الاسم"></td>
        <td><input type="text" name="quota_size[]" style="width:100%;" placeholder="الكوتة / الحجم"></td>
        <td><input type="number" step="0.01" min="0" name="sale_price[]" style="width:100%;" placeholder="0"></td>
        <td><input type="number" step="0.01" min="0" name="cost_price[]" style="width:100%;" placeholder="0"></td>
        <td><input type="number" step="0.01" min="0" name="min_stock[]" style="width:100%;" value="0"></td>
        <td>
            <select name="family[]" class="bulk-oil-field" style="width:100%; display:none;">${familyOptions}</select>
        </td>
        <td>
            <select name="quality[]" class="bulk-oil-field" style="width:100%; display:none;">${qualityOptions}</select>
        </td>
        <td><input type="number" step="0.01" min="0" name="price_per_gram[]" class="bulk-oil-field" style="width:100%; display:none;" placeholder="سعر الجرام"></td>
        <td><input type="text" name="barcode[]" style="width:100%;" placeholder="تلقائي" class="bulk-barcode-field"></td>
        <td><button type="button" class="btn small danger" onclick="this.closest('tr').remove()" style="padding: 2px 6px;">x</button></td>
    `;
    tbody.appendChild(tr);
    toggleBulkFields(tr.querySelector('.bulk-type-select'));
}

function toggleBulkFields(select) {
    const tr = select.closest('tr');
    const type = select.value;
    const oilFields = tr.querySelectorAll('.bulk-oil-field');
    const barcodeField = tr.querySelector('.bulk-barcode-field');
    const quotaSizeField = tr.querySelector('input[name="quota_size[]"]');
    
    if(type === 'perfume_gram') {
        oilFields.forEach(el => el.style.display = 'block');
        barcodeField.disabled = true;
        barcodeField.placeholder = "تلقائي للزيت";
        quotaSizeField.placeholder = "رقم الكوتة (اختياري)";
    } else {
        oilFields.forEach(el => el.style.display = 'none');
        barcodeField.disabled = false;
        barcodeField.placeholder = "باركود (اختياري)";
        quotaSizeField.placeholder = type === 'bottle' ? "الحجم بالمل (مهم)" : "الحجم (اختياري)";
    }
}

function saveBulkProducts() {
    const tbody = document.getElementById('bulk-add-tbody');
    const rows = tbody.querySelectorAll('tr');
    
    if(rows.length === 0) return alert('أضف صنف واحد على الأقل');
    
    const products = [];
    let hasError = false;
    
    rows.forEach(tr => {
        const type = tr.querySelector('select[name="type[]"]').value;
        const name = tr.querySelector('input[name="name[]"]').value.trim();
        const quotaSize = tr.querySelector('input[name="quota_size[]"]').value.trim();
        const salePrice = tr.querySelector('input[name="sale_price[]"]').value;
        const costPrice = tr.querySelector('input[name="cost_price[]"]').value;
        const minStock = tr.querySelector('input[name="min_stock[]"]').value;
        
        if(!name) {
            tr.style.background = '#ffebee';
            hasError = true;
            return;
        } else {
            tr.style.background = '';
        }
        
        const prod = { type, name, min_stock: minStock };
        
        if(type === 'perfume_gram') {
            prod.quota = quotaSize;
            prod.sale_price = salePrice;
            prod.cost_price = costPrice;
            prod.family = tr.querySelector('select[name="family[]"]').value;
            prod.quality = tr.querySelector('select[name="quality[]"]').value;
            prod.price_per_gram = tr.querySelector('input[name="price_per_gram[]"]').value;
        } else {
            prod.size = quotaSize;
            prod.sale_price = salePrice;
            prod.cost_price = costPrice;
            prod.barcode = tr.querySelector('input[name="barcode[]"]').value.trim();
        }
        products.push(prod);
    });
    
    if(hasError) return alert('يرجى كتابة اسم المنتج في جميع الصفوف المظللة');
    
    fetch('index.php?r=api_products_quick_add', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({products})
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('تم إضافة جميع المنتجات بنجاح!');
            window.location.href = 'index.php?r=products';
        } else {
            alert('حدث خطأ: ' + (data.message || 'غير معروف'));
        }
    })
    .catch(err => alert('خطأ في الاتصال بالخادم.'));
}

// Add 3 empty rows initially
document.addEventListener('DOMContentLoaded', () => {
    addBulkRow();
    addBulkRow();
    addBulkRow();
});
</script>
