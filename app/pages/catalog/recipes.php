<?php
$bottles  = all_products('bottle');
$perfumes = all_products('perfume_gram');
$recipes  = saved_recipes();

// Editing mode
$editId     = (int) ($_GET['edit_id'] ?? 0);
$editRecipe = $editId > 0 ? (function() use ($editId) {
    $stmt = pdo()->prepare('SELECT r.*, p.name AS bottle_name, b.size_ml FROM recipe_headers r JOIN products p ON p.id = r.bottle_product_id LEFT JOIN product_bottle_details b ON b.product_id = r.bottle_product_id WHERE r.id = ? AND r.is_active = 1');
    $stmt->execute([$editId]);
    return $stmt->fetch() ?: null;
})() : null;
$editComponents = $editRecipe ? recipe_components_for($editId) : [];
?>

<section class="page-head">
    <div>
        <h2><?= __('إعدادات التركيبات') ?></h2>
        <p><?= __('حفظ وصفات العطور الجاهزة لتسريع عمليات البيع في شاشة الكاشير.') ?></p>
    </div>

</section>

<section>
    <!-- Recipe Form -->
    <form class="panel" id="recipe-form" method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php if ($editRecipe): ?>
            <input type="hidden" name="id" value="<?= e($editRecipe['id']) ?>">
        <?php endif; ?>

        <h3 style="margin-bottom: 8px;">
            <?= $editRecipe ? __('تعديل التركيبة') : __('حفظ تركيبة جديدة') ?>
        </h3>

        <div class="grid-form" style="margin-bottom: 12px; display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px;">
            <label><?= __('اسم التركيبة') ?>
                <input name="name" required placeholder="<?= __('مثال: تركيبتي المميزة') ?>"
                    value="<?= e($editRecipe['name'] ?? '') ?>">
            </label>
            <label><?= __('الزجاجة الافتراضية') ?>
                <select name="bottle_product_id" required id="recipe-bottle-select">
                    <?php foreach ($bottles as $b): ?>
                        <option value="<?= e($b['id']) ?>" <?= $editRecipe && (int)$editRecipe['bottle_product_id'] === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= e($b['name']) ?> (<?= e($b['size_ml']) ?>ml)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= __('سعر البيع المقترح') ?>
                <input name="default_sale_price" type="number" step="any" min="0" required placeholder="150"
                    value="<?= e($editRecipe['default_sale_price'] ?? '') ?>">
            </label>
        </div>

        <h4 style="margin-top: 12px; margin-bottom: 8px; font-size: 13px; font-weight: 700;">
            <?= __('مكونات التركيبة (الزيوت العطرية بالجرام)') ?>
        </h4>

        <div style="display: flex; gap: 10px; align-items: end; margin-bottom: 16px; flex-wrap: wrap;">
            <label style="flex: 2; min-width: 200px; display: block;"><?= __('اختر الزيت العطري') ?>
                <select id="recipe-oil-select">
                    <option value="">-- <?= __('اختر زيتاً عطرياً') ?> --</option>
                    <?php foreach ($perfumes as $p): ?>
                        <option value="<?= e($p['id']) ?>"><?= e($p['name']) ?> (<?= e($p['quality_grade'] ?: '-') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label style="flex: 1; min-width: 100px; display: block;"><?= __('الوزن بالجرام') ?>
                <input type="number" step="any" min="0" id="recipe-oil-grams" placeholder="<?= __('مثال: 12.5') ?>">
            </label>
            <button type="button" class="btn secondary" id="btn-add-oil-component" style="height: 38px;">
                <?= __('أضف الزيت') ?>
            </button>
        </div>

        <table class="panel" style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
            <thead>
                <tr style="border-bottom: 2px solid var(--line); text-align: right;">
                    <th style="padding: 6px;"><?= __('الزيت العطري') ?></th>
                    <th style="padding: 6px; width: 130px; text-align: center;"><?= __('الوزن (جرام)') ?></th>
                    <th style="padding: 6px; width: 80px; text-align: center;"><?= __('إجراء') ?></th>
                </tr>
            </thead>
            <tbody id="recipe-components-body">
                <?php if ($editComponents): ?>
                    <?php foreach ($editComponents as $comp): ?>
                        <tr data-oil-id="<?= e($comp['perfume_product_id']) ?>" style="border-bottom: 1px solid var(--line);">
                            <td style="padding: 6px; font-weight:600;">
                                <?= e($comp['perfume_name']) ?>
                                <input type="hidden" name="perfume_product_id[]" value="<?= e($comp['perfume_product_id']) ?>">
                            </td>
                            <td style="padding: 6px; text-align: center;">
                                <strong><?= e($comp['grams']) ?> <?= __('جرام') ?></strong>
                                <input type="hidden" name="grams[]" value="<?= e($comp['grams']) ?>">
                            </td>
                            <td style="padding: 6px; text-align: center;">
                                <button type="button" class="btn small danger" onclick="removeRecipeComponent(this)"><?= __('حذف') ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr id="empty-recipe-row">
                        <td colspan="3" class="muted" style="text-align: center; padding: 15px;">
                            <?= __('لم يتم إضافة مكونات زيتية بعد.') ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn primary" id="btn-submit-recipe">
                <?= $editRecipe ? __('حفظ التعديلات') : __('حفظ تركيبة جاهزة') ?>
            </button>
            <?php if ($editRecipe): ?>
                <a href="?r=recipes" class="btn secondary"><?= __('إلغاء') ?></a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Saved Recipes Table -->
    <div class="panel">
        <h3><?= __('التركيبات الجاهزة المحفوظة') ?></h3>
        <table>
            <thead>
                <tr>
                    <th><?= __('اسم التركيبة') ?></th>
                    <th><?= __('الزجاجة') ?></th>
                    <th><?= __('سعر البيع') ?></th>
                    <th style="text-align: center;"><?= __('إجراءات') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recipes): ?>
                    <?php foreach ($recipes as $r): ?>
                        <tr class="<?= $editId === (int)$r['id'] ? 'highlight-row' : '' ?>">
                            <td><strong><?= e($r['name']) ?></strong></td>
                            <td><?= e($r['bottle_name']) ?> (<?= e($r['size_ml']) ?>ml)</td>
                            <td style="color: var(--primary-dark); font-weight: 700;"><?= money($r['default_sale_price']) ?></td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="?r=recipes&amp;edit_id=<?= e($r['id']) ?>" class="btn small secondary">
                                    ✏️ <?= __('تعديل') ?>
                                </a>
                                <form action="index.php?r=recipes" method="post" style="display:inline;" onsubmit="return confirm('<?= __('هل أنت متأكد من حذف هذه التركيبة؟') ?>')">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                                    <button type="submit" class="btn small danger">🗑️ <?= __('حذف') ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="muted" style="text-align: center; padding: 20px;">
                            <?= __('لا توجد تركيبات محفوظة حتى الآن.') ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
.highlight-row {
    background: var(--primary-alpha, rgba(99, 102, 241, 0.08)) !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Make selects searchable
    const oilSelect = document.getElementById('recipe-oil-select');
    if (oilSelect) makeSelectSearchable(oilSelect);

    const bottleSelect = document.getElementById('recipe-bottle-select');
    if (bottleSelect) makeSelectSearchable(bottleSelect);

    // If components already exist (edit mode), hide the empty row
    checkEmptyRecipeTable();

    const btnAdd = document.getElementById('btn-add-oil-component');
    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            const selectEl = document.getElementById('recipe-oil-select');
            const oilId    = selectEl.value;
            const gramsVal = parseFloat(document.getElementById('recipe-oil-grams').value);

            if (!oilId) {
                alert('<?= __('يرجى اختيار زيت عطري أولاً.') ?>');
                return;
            }
            if (isNaN(gramsVal) || gramsVal <= 0) {
                alert('<?= __('يرجى إدخال وزن صحيح بالجرام.') ?>');
                return;
            }

            const existingRow = document.querySelector(`#recipe-components-body tr[data-oil-id="${oilId}"]`);
            if (existingRow) {
                alert('<?= __('هذا الزيت مضاف بالفعل للتركيبة.') ?>');
                return;
            }

            const option  = selectEl.options[selectEl.selectedIndex];
            const oilName = option.textContent.trim();

            const emptyRow = document.getElementById('empty-recipe-row');
            if (emptyRow) emptyRow.style.display = 'none';

            const tr = document.createElement('tr');
            tr.dataset.oilId = oilId;
            tr.style.borderBottom = '1px solid var(--line)';
            tr.innerHTML = `
                <td style="padding: 6px; font-weight:600;">
                    ${oilName}
                    <input type="hidden" name="perfume_product_id[]" value="${oilId}">
                </td>
                <td style="padding: 6px; text-align: center;">
                    <strong>${gramsVal} <?= __('جرام') ?></strong>
                    <input type="hidden" name="grams[]" value="${gramsVal}">
                </td>
                <td style="padding: 6px; text-align: center;">
                    <button type="button" class="btn small danger" onclick="removeRecipeComponent(this)"><?= __('حذف') ?></button>
                </td>
            `;

            document.getElementById('recipe-components-body').appendChild(tr);

            document.getElementById('recipe-oil-grams').value = '';
            selectEl.value = '';
            selectEl.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    const form = document.getElementById('recipe-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const body = document.getElementById('recipe-components-body');
            const rows = body.querySelectorAll('tr:not(#empty-recipe-row)');
            if (rows.length === 0) {
                e.preventDefault();
                alert('<?= __('يرجى إضافة مكون زيتي واحد على الأقل للتركيبة.') ?>');
            }
        });
    }
});

function removeRecipeComponent(btn) {
    btn.closest('tr').remove();
    checkEmptyRecipeTable();
}

function checkEmptyRecipeTable() {
    const body = document.getElementById('recipe-components-body');
    if (!body) return;
    const rows     = body.querySelectorAll('tr:not(#empty-recipe-row)');
    const emptyRow = document.getElementById('empty-recipe-row');
    if (emptyRow) {
        emptyRow.style.display = rows.length === 0 ? '' : 'none';
    }
}
</script>
