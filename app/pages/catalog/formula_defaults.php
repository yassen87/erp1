<?php
$defaults = formula_defaults_rows();

$filterBottle = (int)($_GET['filter_bottle'] ?? 0);
$filterPerfume = (int)($_GET['filter_perfume'] ?? 0);

if ($filterBottle > 0) {
    $defaults = array_values(array_filter($defaults, fn($d) => (int)$d['bottle_product_id'] === $filterBottle));
}
if ($filterPerfume > 0) {
    $defaults = array_values(array_filter($defaults, fn($d) => (int)$d['perfume_product_id'] === $filterPerfume));
}

$editId = (int) ($_GET['edit_id'] ?? 0);
$editDefault = $editId > 0 ? find_formula_default($editId) : null;
$editBottleId = (int) ($_GET['edit_bottle_id'] ?? 0);
$editQualityGrade = $_GET['quality_grade'] ?? null;
$editBottleDefaults = $editBottleId > 0 ? formula_defaults_for_bottle($editBottleId, $editQualityGrade) : [];
$view = $editBottleDefaults ? 'edit_group' : ($editDefault ? 'edit' : (string) ($_GET['view'] ?? 'list'));
if (!in_array($view, ['list', 'add', 'edit', 'edit_group'], true)) {
    $view = 'list';
}
if ($view === 'edit' && !$editDefault) {
    $view = 'list';
}
if ($view === 'edit_group' && !$editBottleDefaults) {
    $view = 'list';
}

$allProducts = all_products();
$bottleProducts = array_values(array_filter($allProducts, fn($p) => $p['type'] === 'bottle'));
$perfumeProducts = array_values(array_filter($allProducts, fn($p) => $p['type'] === 'perfume_gram'));

$renderBottleOptions = static function (array $bottleProducts, ?int $selectedId = null): void {
    foreach ($bottleProducts as $bp): ?>
        <option value="<?= e($bp['id']) ?>"
                data-size="<?= e($bp['size_ml']) ?>"
                <?= $selectedId !== null && (int)$bp['id'] === $selectedId ? 'selected' : '' ?>>
            <?= e($bp['name']) ?> (<?= e($bp['size_ml']) ?>ml)
        </option>
    <?php endforeach;
};

$renderPerfumeOptions = static function (array $perfumeProducts, ?int $selectedId = null): void {
    foreach ($perfumeProducts as $pp): ?>
        <option value="<?= e($pp['id']) ?>" <?= $selectedId !== null && (int)$pp['id'] === $selectedId ? 'selected' : '' ?>>
            <?= e($pp['name']) ?> (<?= e($pp['quality_grade'] ?: '-') ?>)
        </option>
    <?php endforeach;
};
?>

<section class="page-head">
    <div>
        <h2><?= __('الجرامات الافتراضية للتركيبات') ?></h2>
        <p><?= __('ربط زجاجة واحدة بعطر أو أكثر، وتحديد الجرامات الافتراضية وسعر التركيبة التلقائي لتسريع الكاشير.') ?></p>
    </div>
    <div class="formula-toolbar">
        <a href="?r=formula_defaults&amp;view=add" class="btn <?= $view === 'add' ? 'primary' : 'secondary' ?>"><?= __('إضافة روابط زجاجة') ?></a>
        <a href="?r=formula_defaults" class="btn <?= $view === 'list' ? 'primary' : 'secondary' ?>"><?= __('جدول الروابط') ?></a>
    </div>
</section>

<section class="formula-defaults-page">
    <?php if ($view === 'add'): ?>
        <form class="panel formula-form" method="post" id="formula-add-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="formula-form-head">
                <div>
                    <h3><?= __('إضافة روابط زجاجة') ?></h3>
                    <p class="muted"><?= __('اختر الزجاجة والكوتة، ثم حدد العطور وأدخل الجرامات والسعر الموحد.') ?></p>
                </div>
                <a href="?r=formula_defaults" class="btn secondary"><?= __('رجوع للجدول') ?></a>
            </div>

            <div class="formula-bottle-grid">
                <label><?= __('الزجاجة') ?>
                    <select name="bottle_id" id="bottle-select" required>
                        <option value="">-- <?= __('اختر الزجاجة') ?> --</option>
                        <?php $renderBottleOptions($bottleProducts); ?>
                    </select>
                </label>
                <label><?= __('حجم الزجاجة ml') ?>
                    <input name="bottle_size_ml" id="bottle-size-display" type="number" readonly style="background: var(--surface-soft); color: var(--muted);">
                </label>
            </div>

            <!-- الكوتة + الجرامات + السعر الموحد -->
            <div class="formula-shared-inputs">
                <label style="grid-column: 1 / -1;"><?= __('الكوتة (اختر كوتة واحدة)') ?>
                    <select name="quality_grade" id="quality-grade-select" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface-soft); font-family: inherit;">
                        <option value="">-- <?= __('اختر الكوتة') ?> --</option>
                        <?php foreach (array_filter(quality_grade_labels(), fn($k) => $k !== '', ARRAY_FILTER_USE_KEY) as $v => $l): ?>
                            <option value="<?= e($v) ?>"><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?= __('الجرامات الافتراضية الأساسية') ?>
                    <input id="shared-grams" type="number" step="0.1" min="0.1" placeholder="12" value="">
                </label>
                <label><?= __('سعر البيع الأساسي للتركيبة') ?>
                    <input id="shared-price" type="number" step="1" min="0" placeholder="150" value="0">
                </label>
            </div>

            <!-- قائمة العطور بالكوتة المختارة -->
            <div class="formula-items-card">
                <div class="formula-items-head" style="flex-wrap:wrap; gap:10px;">
                    <h4 id="perfume-list-title"><?= __('اختر العطور') ?></h4>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <div class="perfume-search-wrap">
                            <span class="perfume-search-icon">🔍</span>
                            <input type="text" id="perfume-checklist-search" placeholder="<?= __('ابحث عن عطر...') ?>" autocomplete="off">
                        </div>
                        <button type="button" class="btn small secondary" id="select-all-perfumes"><?= __('تحديد الكل') ?></button>
                        <button type="button" class="btn small" id="deselect-all-perfumes"><?= __('إلغاء الكل') ?></button>
                        <span class="badge" id="selected-perfume-count" style="min-width:80px; text-align:center;">0 <?= __('محدد') ?></span>
                    </div>
                </div>

                <div class="perfume-checklist" id="perfume-checklist">
                    <?php 
                    $perfumeNames = [];
                    foreach ($perfumeProducts as $pp) {
                        $name = trim($pp['name']);
                        $lowName = mb_strtolower($name);
                        if (!isset($perfumeNames[$lowName])) {
                            $perfumeNames[$lowName] = ['name' => $name, 'products' => []];
                        }
                        $q = $pp['quality_grade'] ?: '';
                        $perfumeNames[$lowName]['products'][$q] = $pp['id'];
                    }
                    foreach ($perfumeNames as $lowName => $data): ?>
                    <label class="perfume-chip-label" data-name="<?= e($lowName) ?>" data-products="<?= e(json_encode($data['products'])) ?>">
                        <input type="checkbox" class="perfume-checkbox">
                        <span class="perfume-chip-text">
                            <strong><?= e($data['name']) ?></strong>
                        </span>
                    </label>
                    <?php endforeach; ?>
                    <p class="muted" id="perfume-no-results" style="display:none; padding:12px;"><?= __('لا توجد عطور بهذه المواصفات.') ?></p>
                </div>
                <!-- الحقول المخفية تُملأ بالجافاسكريبت قبل الإرسال -->
                <div id="formula-hidden-fields"></div>
            </div>

            <!-- الاستثناءات -->
            <div class="formula-items-card" style="margin-top: 20px;">
                <div class="formula-items-head">
                    <h4><?= __('استثناءات (عطور لها جرامات أو سعر مختلف)') ?></h4>
                    <button type="button" class="btn secondary small" id="add-exception-row"><?= __('+ إضافة استثناء') ?></button>
                </div>
                <div class="responsive-table">
                    <table class="formula-items-table">
                        <thead>
                            <tr>
                                <th><?= __('العطر') ?></th>
                                <th><?= __('الجرامات الافتراضية') ?></th>
                                <th><?= __('سعر بيع التركيبة') ?></th>
                                <th><?= __('حذف') ?></th>
                            </tr>
                        </thead>
                        <tbody id="exception-rows">
                            <!-- Rows added dynamically via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <template id="exception-row-template">
                <tr class="formula-perfume-row">
                    <td>
                        <select class="exception-perfume-select" multiple style="min-width: 150px;" required>
                        </select>
                    </td>
                    <td><input class="exception-grams" type="number" step="0.1" min="0.1" placeholder="مثال: 15"></td>
                    <td><input class="exception-price" type="number" step="1" min="0" placeholder="مثال: 200"></td>
                    <td><button type="button" class="btn small danger remove-exception-row"><?= __('حذف') ?></button></td>
                </tr>
            </template>

            <div class="formula-actions">
                <button class="btn primary" type="submit"><?= __('حفظ كل الروابط') ?></button>
                <a href="?r=formula_defaults" class="btn secondary"><?= __('إلغاء') ?></a>
            </div>
        </form>
    <?php elseif ($view === 'edit_group' && $editBottleDefaults): ?>
        <?php
        $groupBottle = $editBottleDefaults[0];
        
        // Calculate the mode (most frequent combination of grams and price)
        $frequencies = [];
        foreach ($editBottleDefaults as $row) {
            $key = $row['default_grams'] . '_' . $row['price'];
            if (!isset($frequencies[$key])) {
                $frequencies[$key] = [
                    'grams' => $row['default_grams'],
                    'price' => $row['price'],
                    'count' => 0,
                    'perfume_ids' => []
                ];
            }
            $frequencies[$key]['count']++;
            $frequencies[$key]['perfume_ids'][] = $row['perfume_product_id'];
        }
        
        $modeKey = null;
        $maxCount = 0;
        foreach ($frequencies as $key => $data) {
            if ($data['count'] > $maxCount) {
                $maxCount = $data['count'];
                $modeKey = $key;
            }
        }
        
        $sharedGrams = '';
        $sharedPrice = '';
        $sharedPerfumeIds = [];
        $existingExceptions = [];
        
        if ($modeKey) {
            $sharedGrams = $frequencies[$modeKey]['grams'];
            $sharedPrice = $frequencies[$modeKey]['price'];
            $sharedPerfumeIds = $frequencies[$modeKey]['perfume_ids'];
            
            foreach ($frequencies as $key => $data) {
                if ($key !== $modeKey) {
                    $existingExceptions[] = [
                        'grams' => $data['grams'],
                        'price' => $data['price'],
                        'perfume_ids' => $data['perfume_ids']
                    ];
                }
            }
        }
        ?>
        <form class="panel formula-form" method="post" id="formula-edit-group-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="replace_bottle_group">
            <input type="hidden" name="bottle_id" value="<?= e($groupBottle['bottle_product_id']) ?>">
            
            <div class="formula-form-head">
                <div>
                    <h3><?= __('تعديل / إضافة عطور للزجاجة') ?></h3>
                    <p class="muted"><?= __('العطور المحفوظة مسبقاً تظهر في جدول الاستثناءات بالأسفل. يمكنك إضافة عطور جديدة عبر القائمة.') ?></p>
                </div>
                <a href="?r=formula_defaults" class="btn secondary"><?= __('رجوع للجدول') ?></a>
            </div>

            <div class="formula-bottle-grid">
                <label><?= __('الزجاجة') ?>
                    <select id="bottle-select" disabled aria-disabled="true">
                        <?php $renderBottleOptions($bottleProducts, (int) $groupBottle['bottle_product_id']); ?>
                    </select>
                </label>
                <label><?= __('حجم الزجاجة ml') ?>
                    <input name="bottle_size_ml" id="bottle-size-display" type="number" readonly
                           value="<?= e($groupBottle['bottle_size_ml'] ?? '') ?>"
                           style="background: var(--surface-soft); color: var(--muted);">
                </label>
            </div>

            <!-- الكوتة + الجرامات + السعر الموحد -->
            <div class="formula-shared-inputs">
                <label style="grid-column: 1 / -1;"><?= __('الكوتة') ?>
                    <select id="quality-grade-select" disabled aria-disabled="true" style="width: 100%; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; background: var(--surface-soft); color: var(--muted); font-family: inherit;">
                        <option value="<?= e($editQualityGrade) ?>"><?= e(quality_grade_labels()[$editQualityGrade] ?? $editQualityGrade) ?></option>
                    </select>
                    <input type="hidden" name="quality_grade" value="<?= e($editQualityGrade) ?>">
                </label>
                <label><?= __('الجرامات الافتراضية للعطور الجديدة') ?>
                    <input id="shared-grams" type="number" step="0.1" min="0.1" placeholder="12" value="<?= e($sharedGrams) ?>">
                </label>
                <label><?= __('سعر البيع للعطور الجديدة') ?>
                    <input id="shared-price" type="number" step="1" min="0" placeholder="150" value="<?= e($sharedPrice) ?>">
                </label>
            </div>

            <!-- قائمة العطور بالكوتة المختارة -->
            <div class="formula-items-card">
                <div class="formula-items-head" style="flex-wrap:wrap; gap:10px;">
                    <h4 id="perfume-list-title"><?= __('إضافة عطور جديدة') ?></h4>
                    <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <div class="perfume-search-wrap">
                            <span class="perfume-search-icon">🔍</span>
                            <input type="text" id="perfume-checklist-search" placeholder="<?= __('ابحث عن عطر...') ?>" autocomplete="off">
                        </div>
                        <button type="button" class="btn small secondary" id="select-all-perfumes"><?= __('تحديد الكل') ?></button>
                        <button type="button" class="btn small" id="deselect-all-perfumes"><?= __('إلغاء الكل') ?></button>
                        <span class="badge" id="selected-perfume-count" style="min-width:80px; text-align:center;">0 <?= __('محدد') ?></span>
                    </div>
                </div>

                <div class="perfume-checklist" id="perfume-checklist">
                    <?php foreach ($perfumeProducts as $pp): ?>
                    <?php $isChecked = in_array($pp['id'], $sharedPerfumeIds); ?>
                    <label class="perfume-chip-label <?= $isChecked ? 'chip-checked' : '' ?>" data-quality="<?= e($pp['quality_grade'] ?? '') ?>" data-name="<?= e(mb_strtolower($pp['name'])) ?>">
                        <input type="checkbox" class="perfume-checkbox" value="<?= e($pp['id']) ?>" <?= $isChecked ? 'checked' : '' ?>>
                        <span class="perfume-chip-text">
                            <strong><?= e($pp['name']) ?></strong>
                            <?php if ($pp['quality_grade']): ?><span class="chip-badge"><?= e($pp['quality_grade']) ?></span><?php endif; ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                    <p class="muted" id="perfume-no-results" style="display:none; padding:12px;"><?= __('لا توجد عطور بهذه الكوتة.') ?></p>
                </div>
                <div id="formula-hidden-fields"></div>
            </div>

            <!-- الاستثناءات -->
            <div class="formula-items-card" style="margin-top: 20px;">
                <div class="formula-items-head">
                    <h4><?= __('العطور المحفوظة سابقاً أو الاستثناءات') ?></h4>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <button type="button" class="btn secondary small" id="add-exception-row"><?= __('+ إضافة من المحدد أعلاه') ?></button>
                        <button type="button" class="btn small" id="add-blank-exception-row"><?= __('+ إضافة فارغة') ?></button>
                    </div>
                </div>
                <div class="responsive-table">
                    <table class="formula-items-table">
                        <thead>
                            <tr>
                                <th><?= __('العطور') ?></th>
                                <th><?= __('الجرامات الافتراضية') ?></th>
                                <th><?= __('سعر بيع التركيبة') ?></th>
                                <th><?= __('إجراءات') ?></th>
                            </tr>
                        </thead>
                        <tbody id="exception-rows">
                            <?php foreach ($existingExceptions as $ex): ?>
                                <tr class="formula-perfume-row">
                                    <td>
                                        <select class="exception-perfume-select" multiple style="min-width: 150px;" required>
                                            <?php foreach ($perfumeProducts as $pp): ?>
                                                <option value="<?= e($pp['id']) ?>"
                                                    <?= in_array($pp['id'], $ex['perfume_ids']) ? 'selected' : '' ?>>
                                                    <?= e($pp['name']) ?><?= $pp['quality_grade'] ? ' (' . e($pp['quality_grade']) . ')' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><input class="exception-grams" type="number" step="0.1" min="0.1" value="<?= e($ex['grams']) ?>"></td>
                                    <td><input class="exception-price" type="number" step="1" min="0" value="<?= e($ex['price']) ?>"></td>
                                    <td><button type="button" class="btn small danger remove-exception-row"><?= __('حذف') ?></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="formula-actions">
                <button class="btn primary" type="submit"><?= __('حفظ روابط الزجاجة') ?></button>
                <a href="?r=formula_defaults" class="btn secondary"><?= __('إلغاء') ?></a>
            </div>

            <template id="exception-row-template">
                <tr class="formula-perfume-row">
                    <td>
                        <select class="exception-perfume-select" multiple style="min-width: 180px; height: 90px;" required>
                            <?php foreach ($perfumeProducts as $pp): ?>
                                <option value="<?= e($pp['id']) ?>">
                                    <?= e($pp['name']) ?><?= $pp['quality_grade'] ? ' (' . e($pp['quality_grade']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input class="exception-grams" type="number" step="0.1" min="0.1" placeholder="مثال: 15"></td>
                    <td><input class="exception-price" type="number" step="1" min="0" placeholder="مثال: 200"></td>
                    <td><button type="button" class="btn small danger remove-exception-row"><?= __('حذف') ?></button></td>
                </tr>
            </template>
        </form>
    <?php elseif ($view === 'edit' && $editDefault): ?>
        <form class="panel formula-form" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e($editDefault['id']) ?>">
            <div class="formula-form-head">
                <div>
                    <h3><?= __('تعديل ربط واحد') ?></h3>
                    <p class="muted"><?= __('يعدل هذا السجل فقط ولا يمس باقي الروابط لنفس الزجاجة.') ?></p>
                </div>
                <a href="?r=formula_defaults" class="btn secondary"><?= __('رجوع للجدول') ?></a>
            </div>

            <div class="grid-form formula-edit-grid">
                <label style="grid-column: span 2;"><?= __('الزجاجة') ?>
                    <select name="bottle_id" id="bottle-select" required>
                        <option value="">-- <?= __('اختر الزجاجة') ?> --</option>
                        <?php $renderBottleOptions($bottleProducts, (int) $editDefault['bottle_product_id']); ?>
                    </select>
                </label>

                <label><?= __('حجم الزجاجة ml') ?>
                    <input name="bottle_size_ml" id="bottle-size-display" type="number" readonly
                           value="<?= e($editDefault['bottle_size_ml'] ?? '') ?>"
                           style="background: var(--surface-soft); color: var(--muted);">
                </label>

                <label style="grid-column: span 2;"><?= __('الزيت العطري') ?>
                    <select name="perfume_product_id" id="perfume-select" required>
                        <option value="">-- <?= __('اختر الزيت العطري') ?> --</option>
                        <?php $renderPerfumeOptions($perfumeProducts, (int)$editDefault['perfume_product_id']); ?>
                    </select>
                </label>

                <label><?= __('الجرامات الافتراضية') ?>
                    <input name="default_grams" type="number" step="0.1" min="0.1" required placeholder="12"
                           value="<?= e($editDefault['default_grams'] ?? '') ?>">
                </label>

                <label><?= __('سعر بيع التركيبة (ج.م)') ?>
                    <input name="price" type="number" step="1" min="0" required placeholder="150"
                           value="<?= e($editDefault['price'] ?? '0') ?>">
                </label>
            </div>

            <div class="formula-actions">
                <button class="btn primary"><?= __('حفظ التعديل') ?></button>
                <a href="?r=formula_defaults" class="btn secondary"><?= __('إلغاء') ?></a>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($view === 'list'): ?>
        <form class="panel" method="get" style="margin-bottom: 16px;">
            <input type="hidden" name="r" value="formula_defaults">
            <div class="product-filter-bar" style="margin-bottom: 0; padding-bottom: 0;">
                <label><?= __('تصفية بالزجاجة') ?>
                    <select name="filter_bottle" class="filter-select" onchange="this.form.submit()">
                        <option value="">-- <?= __('الكل') ?> --</option>
                        <?php $renderBottleOptions($bottleProducts, $filterBottle ?: null); ?>
                    </select>
                </label>
                <label><?= __('تصفية بالعطر') ?>
                    <select name="filter_perfume" class="filter-select" onchange="this.form.submit()">
                        <option value="">-- <?= __('الكل') ?> --</option>
                        <?php $renderPerfumeOptions($perfumeProducts, $filterPerfume ?: null); ?>
                    </select>
                </label>
                <div style="flex: 1;"></div>
                <?php if ($filterBottle || $filterPerfume): ?>
                <a href="?r=formula_defaults" class="btn small secondary"><?= __('إلغاء الفلتر') ?></a>
                <?php endif; ?>
            </div>
        </form>

        <div class="panel">
            <div class="formula-table-head">
                <div>
                    <h3><?= __('جدول إعدادات التركيبات الفورية') ?></h3>
                    <p class="muted"><?= __('كل صف يمثل ربطاً مستقلاً يمكن تعديله أو حذفه بدون التأثير على الروابط الأخرى.') ?></p>
                </div>
                <a href="?r=formula_defaults&amp;view=add" class="btn primary"><?= __('إضافة روابط زجاجة') ?></a>
            </div>
            <div class="responsive-table">
            <table>
                <thead>
                    <tr>
                        <th><?= __('الزجاجة') ?></th>
                        <th><?= __('حجم الزجاجة') ?></th>
                        <th><?= __('العطور المرتبطة (السعر / الجرامات)') ?></th>
                        <th style="text-align: center;"><?= __('إجراءات') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $groupedDefaults = [];
                    if ($defaults) {
                        foreach ($defaults as $d) {
                            $grade = $d['perfume_quality_grade'] ?: '';
                            $groupedDefaults[$d['bottle_product_id'] . '_' . $grade][] = $d;
                        }
                    }
                    ?>
                    <?php if ($groupedDefaults): ?>
                        <?php foreach ($groupedDefaults as $key => $group): ?>
                            <?php 
                            $first = $group[0]; 
                            $grade = $first['perfume_quality_grade'] ?: '';
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e($first['bottle_name'] ?: __('زجاجة #') . $first['bottle_product_id']) ?> (<?= e($grade ?: __('بدون كوتة')) ?>)</strong>
                                </td>
                                <td>
                                    <strong><?= e($first['bottle_size_ml']) ?>ml</strong>
                                </td>
                                <td>
                                    <?php 
                                        $perfumesData = [];
                                        foreach ($group as $d) {
                                            $perfumesData[] = [
                                                'id' => $d['id'],
                                                'perfume_name' => $d['perfume_name'] ?: __('زيت #') . $d['perfume_product_id'],
                                                'grams' => qty($d['default_grams']),
                                                'price' => money($d['price'])
                                            ];
                                        }
                                    ?>
                                    <button type="button" class="btn small secondary open-perfumes-modal" data-perfumes="<?= e(json_encode($perfumesData)) ?>">
                                        <?= __('عرض العطور') ?> (<?= count($group) ?>)
                                    </button>
                                </td>
                                <td style="text-align: center; white-space: nowrap;">
                                    <?php if (has_permission('recipes_add') || has_permission('recipes_edit')): ?>
                                    <a href="?r=formula_defaults&amp;view=edit_group&amp;edit_bottle_id=<?= e($first['bottle_product_id']) ?>&amp;quality_grade=<?= e($grade) ?>" class="btn small primary"><?= __('تعديل المجموعه') ?></a>
                                    <?php endif; ?>
                                    <?php if (has_permission('recipes_edit')): ?>
                                    <form action="index.php?r=formula_defaults" method="post" style="display:inline;" class="confirm-delete" data-confirm="<?= e(__('هل أنت متأكد من حذف هذه المجموعة بالكامل؟')) ?>">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_group">
                                        <input type="hidden" name="bottle_id" value="<?= e($first['bottle_product_id']) ?>">
                                        <input type="hidden" name="quality_grade" value="<?= e($grade) ?>">
                                        <button type="submit" class="btn small danger"><?= __('حذف المجموعة') ?></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="muted" style="text-align: center; padding: 20px;">
                                <?= __('لا توجد إعدادات تركيبات محفوظة حتى الآن.') ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Perfumes Modal -->
        <div id="perfumes-modal" class="modal">
            <div class="modal-content" style="border-radius: 12px; padding: 20px; max-width: 900px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3 style="margin: 0;"><?= __('العطور المرتبطة') ?></h3>
                    <span class="close-modal" id="close-perfumes-modal" style="font-size: 24px; cursor: pointer;">&times;</span>
                </div>
                
                <div class="search-wrap" style="margin-bottom: 15px;">
                    <input type="text" id="modal-perfume-search" placeholder="<?= __('ابحث عن عطر في القائمة...') ?>" style="width: 100%;" autocomplete="off">
                </div>

                <div class="responsive-table" style="max-height: 400px; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th><?= __('العطر') ?></th>
                                <th><?= __('الجرامات الافتراضية') ?></th>
                                <th><?= __('السعر') ?></th>
                                <th><?= __('إجراءات') ?></th>
                            </tr>
                        </thead>
                        <tbody id="modal-perfumes-tbody">
                            <!-- Populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>

<style>
.formula-toolbar,
.formula-form-head,
.formula-items-head,
.formula-table-head,
.formula-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.formula-defaults-page {
    display: grid;
    gap: 16px;
}
.formula-form {
    display: grid;
    gap: 18px;
    border: 1px solid rgba(201,168,76,0.22);
}
.formula-bottle-grid {
    display: grid;
    grid-template-columns: minmax(260px, 2fr) minmax(160px, 1fr);
    gap: 14px;
}
.formula-items-card {
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px;
    background: var(--surface-soft, rgba(0,0,0,0.02));
}
.formula-items-head {
    margin-bottom: 12px;
}
.formula-items-head h4,
.formula-form-head h3,
.formula-table-head h3 {
    margin: 0;
}
.formula-items-table td,
.formula-items-table th {
    vertical-align: middle;
}
.formula-items-table select,
.formula-items-table input {
    min-width: 150px;
}
.formula-edit-grid {
    display: grid;
}
.bottle-group-end td {
    border-bottom: 2px solid color-mix(in srgb, var(--primary) 30%, var(--line)) !important;
}
.bottle-group-cell {
    vertical-align: top;
    border-inline-end: 1px solid color-mix(in srgb, var(--line) 40%, transparent);
}
:root[data-theme="dark"] .bottle-group-cell {
    border-inline-end-color: rgba(214, 168, 68, .2);
}
:root[data-theme="dark"] .bottle-group-end td {
    border-bottom-color: rgba(214, 168, 68, .3) !important;
}
.formula-bottle-grid:has(.custom-select-wrapper.open) {
    position: relative;
    z-index: 99999 !important;
}
.responsive-table:has(.custom-select-wrapper.open) {
    overflow: visible !important;
}
.formula-items-table tr:has(.custom-select-wrapper.open) td {
    position: relative;
    z-index: 99999 !important;
}
@media (max-width: 760px) {
    .formula-bottle-grid {
        grid-template-columns: 1fr;
    }
    .formula-toolbar,
    .formula-form-head,
    .formula-actions {
        align-items: stretch;
    }
    .formula-toolbar .btn,
    .formula-form-head .btn,
    .formula-actions .btn {
        width: 100%;
    }
}
/* ===== formula shared inputs row ===== */
.formula-shared-inputs {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
}
/* ===== Perfume checklist ===== */
.perfume-checklist {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 10px 4px;
    max-height: 300px;
    overflow-y: auto;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--surface);
    margin-top: 10px;
}
.perfume-chip-label {
    display: flex;
    align-items: center;
    gap: 7px;
    background: var(--surface-soft);
    border: 1.5px solid var(--line);
    border-radius: 20px;
    padding: 5px 12px 5px 8px;
    cursor: pointer;
    font-size: 13px;
    transition: all .15s;
    user-select: none;
}
.perfume-chip-label:hover {
    border-color: var(--primary);
    background: var(--primary-soft);
}
.perfume-chip-label input[type=checkbox] {
    width: 15px;
    height: 15px;
    accent-color: var(--primary);
    cursor: pointer;
    flex-shrink: 0;
}
.perfume-chip-label.chip-checked {
    border-color: var(--primary);
    background: var(--primary-soft);
}
.perfume-chip-text {
    display: flex;
    align-items: center;
    gap: 5px;
}
.chip-badge {
    background: var(--primary);
    color: #fff;
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
}
.perfume-search-wrap {
    position: relative;
    min-width: 180px;
}
.perfume-search-wrap input {
    padding-right: 30px !important;
    width: 100%;
    font-size: 13px;
}
.perfume-search-icon {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 13px;
    pointer-events: none;
    color: var(--muted);
}
.formula-quality-select {
    font-weight: 700;
}
.perfume-chip-label[hidden] { display: none !important; }

/* ===== Modal Styles ===== */
.modal {
    display: none;
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.4);
    align-items: center;
    justify-content: center;
}
.modal.open {
    display: flex;
}
.modal-content {
    background-color: var(--surface);
    margin: auto;
    padding: 20px;
    border: 1px solid var(--line);
    width: 90%;
    max-width: 900px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.close-modal {
    color: var(--muted);
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}
.close-modal:hover {
    color: var(--danger);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // ===== bottle select + size display (shared across all views) =====
    const bottleSelect = document.getElementById('bottle-select');
    const sizeDisplay  = document.getElementById('bottle-size-display');
    if (bottleSelect && sizeDisplay) {
        if (typeof makeSelectSearchable === 'function') makeSelectSearchable(bottleSelect);
        bottleSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            sizeDisplay.value = opt ? (opt.getAttribute('data-size') || '') : '';
        });
        if (!sizeDisplay.value) bottleSelect.dispatchEvent(new Event('change'));
    }

    const initPerfumeSelect = (sel) => {
        if (sel && typeof makeSelectSearchable === 'function' && !sel.dataset.searchableInitialized)
            makeSelectSearchable(sel);
    };
    document.querySelectorAll('select.perfume-select, #perfume-select, select.filter-select').forEach(initPerfumeSelect);

    // ===== Perfumes Modal Logic =====
    const perfumesModal = document.getElementById('perfumes-modal');
    if (perfumesModal) {
        const closeModalBtn = document.getElementById('close-perfumes-modal');
        const searchInput = document.getElementById('modal-perfume-search');
        const tbody = document.getElementById('modal-perfumes-tbody');
        let currentModalData = [];

        const renderModalTable = (query = '') => {
            tbody.innerHTML = '';
            const q = query.toLowerCase().trim();
            const filtered = currentModalData.filter(d => d.perfume_name.toLowerCase().includes(q));
            
            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="muted" style="text-align: center;">${<?= json_encode(__('لا توجد عطور مطابقة للبحث.')) ?>}</td></tr>`;
                return;
            }

            filtered.forEach(d => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${d.perfume_name}</strong></td>
                    <td>${d.grams}</td>
                    <td>${d.price}</td>
                    <td style="white-space: nowrap;">
                        <a href="?r=formula_defaults&view=edit&edit_id=${d.id}" class="btn small primary">${<?= json_encode(__('تعديل')) ?>}</a>
                        <form action="index.php?r=formula_defaults" method="post" style="display:inline;" onsubmit="return confirm(this.getAttribute('data-confirm'))" data-confirm="<?= e(__('هل أنت متأكد من حذف هذا الإعداد نهائياً؟')) ?>">
                            <input type="hidden" name="csrf" value="${<?= json_encode(e(csrf_token())) ?>}">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${d.id}">
                            <button type="submit" class="btn small danger">${<?= json_encode(__('حذف')) ?>}</button>
                        </form>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        };

        document.querySelectorAll('.open-perfumes-modal').forEach(btn => {
            btn.addEventListener('click', function() {
                try {
                    currentModalData = JSON.parse(this.getAttribute('data-perfumes') || '[]');
                    renderModalTable();
                    searchInput.value = '';
                    perfumesModal.classList.add('open');
                } catch(e) {
                    console.error('Error parsing perfume data', e);
                }
            });
        });

        closeModalBtn.addEventListener('click', () => perfumesModal.classList.remove('open'));
        perfumesModal.addEventListener('click', (e) => {
            if (e.target === perfumesModal) perfumesModal.classList.remove('open');
        });

        searchInput.addEventListener('input', (e) => renderModalTable(e.target.value));
    }

    // ===== form logic (for add and edit_group views) =====
    const qualityFilters  = document.querySelectorAll('.quality-grade-filter');
    const checklistWrap   = document.getElementById('perfume-checklist');
    const searchInput     = document.getElementById('perfume-checklist-search');
    const selectAllBtn    = document.getElementById('select-all-perfumes');
    const deselectAllBtn  = document.getElementById('deselect-all-perfumes');
    const countBadge      = document.getElementById('selected-perfume-count');
    const noResults       = document.getElementById('perfume-no-results');
    const hiddenContainer = document.getElementById('formula-hidden-fields');
    const targetForm      = document.getElementById('formula-add-form') || document.getElementById('formula-edit-group-form');

    // Exceptions
    const excBody = document.getElementById('exception-rows');
    const excAddBtn = document.getElementById('add-exception-row');
    const excTemplate = document.getElementById('exception-row-template');
    
    if (excBody && excAddBtn && excTemplate) {
        excAddBtn.addEventListener('click', () => {
            // Support both chip formats:
            // Add view:        data-products='{"A":5,"A+":6}' (checkbox has no value)
            // Edit Group view: data-quality="A", checkbox value = product_id
            const checkedChips = Array.from(checklistWrap ? checklistWrap.querySelectorAll('.perfume-checkbox:checked') : []);

            // Determine selected quality from select or hidden input
            const selectEl = document.getElementById('quality-grade-select');
            const hiddenEl = document.getElementsByName('quality_grade')[0];
            const selectedQuality = selectEl ? selectEl.value : (hiddenEl ? hiddenEl.value : '');

            const matchedProducts = [];

            checkedChips.forEach(cb => {
                const chip = cb.closest('.perfume-chip-label');
                const name = chip.querySelector('strong') ? chip.querySelector('strong').textContent : '';

                if (chip.dataset.products) {
                    // Add view format: data-products JSON map
                    const products = JSON.parse(chip.dataset.products || '{}');
                    const qualitiesToAdd = selectedQuality ? [selectedQuality] : Object.keys(products);
                    qualitiesToAdd.forEach(q => {
                        if (products[q]) {
                            matchedProducts.push({
                                id: products[q],
                                name: name + (q ? ` (${q})` : '')
                            });
                        }
                    });
                } else {
                    // Edit Group view format: data-quality + checkbox value
                    const pid = cb.value;
                    const quality = chip.dataset.quality || '';
                    if (pid) {
                        matchedProducts.push({
                            id: pid,
                            name: name + (quality ? ` (${quality})` : '')
                        });
                    }
                }
            });

            if (matchedProducts.length === 0) {
                alert('<?= __('الرجاء تحديد العطور في القائمة بالأعلى أولاً قبل إضافة استثناء.') ?>');
                return;
            }

            const clone = excTemplate.content.cloneNode(true);
            const sel = clone.querySelector('.exception-perfume-select');

            const matchedIds = new Set(matchedProducts.map(c => String(c.id)));

            if (sel.options.length > 0) {
                // Edit Group view: template already has all perfumes — just select matching ones
                Array.from(sel.options).forEach(opt => {
                    opt.selected = matchedIds.has(String(opt.value));
                });
            } else {
                // Add view: template has empty select — append new options
                matchedProducts.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    opt.selected = true;
                    sel.appendChild(opt);
                });
            }

            excBody.appendChild(clone);
            const newRow = excBody.lastElementChild;
            const newSel = newRow.querySelector('.exception-perfume-select');
            if (newSel && typeof makeSelectSearchable === 'function') makeSelectSearchable(newSel);
        });
        excBody.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-exception-row')) {
                e.target.closest('tr').remove();
            }
        });

        // "إضافة فارغة" button — clones template without pre-selecting anything
        const blankExcBtn = document.getElementById('add-blank-exception-row');
        if (blankExcBtn) {
            blankExcBtn.addEventListener('click', () => {
                const clone = excTemplate.content.cloneNode(true);
                excBody.appendChild(clone);
                const newRow = excBody.lastElementChild;
                const newSel = newRow.querySelector('.exception-perfume-select');
                if (newSel && typeof makeSelectSearchable === 'function') makeSelectSearchable(newSel);
            });
        }
    }

    const qualitySelect = document.getElementById('quality-grade-select');
    if (qualitySelect || checklistWrap) {
        const allChips = () => Array.from(checklistWrap.querySelectorAll('.perfume-chip-label'));

        const updateCount = () => {
            const n = checklistWrap.querySelectorAll('.perfume-checkbox:checked').length;
            countBadge.textContent = n + ' <?= __('محدد') ?>';
            countBadge.style.background = n > 0 ? 'var(--primary)' : '';
            countBadge.style.color      = n > 0 ? '#fff' : '';
        };

        const applyFilters = () => {
            const selectEl = document.getElementById('quality-grade-select');
            const hiddenEl = document.getElementsByName('quality_grade')[0];
            const selectedQuality = selectEl ? selectEl.value : (hiddenEl ? hiddenEl.value : '');
            const selectedQualities = selectedQuality ? [selectedQuality] : [];
            
            const s = (searchInput ? searchInput.value.trim().toLowerCase() : '');
            let visible = 0;
            
            allChips().forEach(chip => {
                const cn = chip.dataset.name || '';
                let showQuality = true;
                
                if (selectedQualities.length > 0) {
                    if (chip.dataset.products) {
                        const products = JSON.parse(chip.dataset.products || '{}');
                        const chipQualities = Object.keys(products);
                        showQuality = selectedQualities.some(q => chipQualities.includes(q));
                    } else if (chip.dataset.quality !== undefined) {
                        showQuality = selectedQualities.includes(chip.dataset.quality);
                    }
                }
                
                const show = showQuality && (!s || cn.includes(s));
                chip.hidden = !show;
                if (show) visible++;
            });
            
            if (noResults) noResults.style.display = visible === 0 ? '' : 'none';
            updateCount();
        };

        // Sync chip checked state styling
        checklistWrap.addEventListener('change', e => {
            if (!e.target.classList.contains('perfume-checkbox')) return;
            e.target.closest('.perfume-chip-label').classList.toggle('chip-checked', e.target.checked);
            updateCount();
        });

        if (qualitySelect) {
            qualitySelect.addEventListener('change', () => {
                // Uncheck hidden chips when quality changes
                allChips().forEach(chip => {
                    if (chip.hidden) {
                        const cb = chip.querySelector('.perfume-checkbox');
                        if (cb) { cb.checked = false; chip.classList.remove('chip-checked'); }
                    }
                });
                applyFilters();
            });
        }
        if (searchInput) searchInput.addEventListener('input', applyFilters);

        selectAllBtn && selectAllBtn.addEventListener('click', () => {
            allChips().filter(c => !c.hidden).forEach(chip => {
                const cb = chip.querySelector('.perfume-checkbox');
                if (cb) { cb.checked = true; chip.classList.add('chip-checked'); }
            });
            updateCount();
        });
        deselectAllBtn && deselectAllBtn.addEventListener('click', () => {
            allChips().forEach(chip => {
                const cb = chip.querySelector('.perfume-checkbox');
                if (cb) { cb.checked = false; chip.classList.remove('chip-checked'); }
            });
            updateCount();
        });

        // On submit: build hidden fields from checked boxes + shared grams/price
        if (targetForm) {
            targetForm.addEventListener('submit', function (e) {
                const sharedGrams = document.getElementById('shared-grams');
                const sharedPrice = document.getElementById('shared-price');
                const checked = checklistWrap.querySelectorAll('.perfume-checkbox:checked');
                
                // Collect exceptions
                const exceptionPerfumes = new Set();
                const exceptionData = [];
                let exceptionError = false;
                
                if (excBody) {
                    excBody.querySelectorAll('tr').forEach(tr => {
                        const sel = tr.querySelector('.exception-perfume-select');
                        const pids = sel ? Array.from(sel.selectedOptions).map(o => o.value) : [];
                        const gramsInp = tr.querySelector('.exception-grams');
                        const grams = gramsInp ? gramsInp.value : '';
                        const priceInp = tr.querySelector('.exception-price');
                        const price = priceInp ? priceInp.value : '';
                        
                        if (pids.length > 0 && grams) {
                            pids.forEach(pid => {
                                exceptionPerfumes.add(pid);
                                exceptionData.push({ pid, grams, price: price || '0' });
                            });
                        } else if (pids.length > 0 || grams) {
                            exceptionError = true;
                        }
                    });
                }

                if (exceptionError) {
                    e.preventDefault();
                    alert('<?= __('يرجى استكمال بيانات الاستثناءات (العطر والجرامات مطلوبان في كل صف).') ?>');
                    return;
                }

                if (checked.length === 0 && exceptionData.length === 0) {
                    e.preventDefault();
                    alert('<?= __('يجب تحديد عطر واحد على الأقل أو إضافة استثناء.') ?>');
                    return;
                }

                // Check shared inputs if there are checked perfumes that are not in exceptions
                let hasNonExcepted = false;
                const selectEl = document.getElementById('quality-grade-select');
                const hiddenEl = document.getElementsByName('quality_grade')[0];
                const selectedQuality = selectEl ? selectEl.value : (hiddenEl ? hiddenEl.value : '');
                const selectedQualities = selectedQuality ? [selectedQuality] : [];
                const qualitiesToAdd = selectedQualities.length > 0 ? selectedQualities : null;

                checked.forEach(cb => {
                    const chip = cb.closest('.perfume-chip-label');
                    if (chip.dataset.products) {
                        const products = JSON.parse(chip.dataset.products || '{}');
                        const qList = qualitiesToAdd || Object.keys(products);
                        qList.forEach(q => {
                            if (products[q] && !exceptionPerfumes.has(products[q].toString())) {
                                hasNonExcepted = true;
                            }
                        });
                    } else {
                        const pid = cb.value;
                        if (pid && !exceptionPerfumes.has(pid.toString())) {
                            hasNonExcepted = true;
                        }
                    }
                });

                if (hasNonExcepted && (!sharedGrams.value || parseFloat(sharedGrams.value) <= 0)) {
                    e.preventDefault();
                    alert('<?= __('أدخل الجرامات الافتراضية الأساسية للعطور المحددة، أو أضفها كاستثناء.') ?>');
                    sharedGrams.focus();
                    return;
                }

                // Clear old hidden fields
                hiddenContainer.innerHTML = '';
                const mk = (name, val) => {
                    const inp = document.createElement('input');
                    inp.type = 'hidden'; inp.name = name; inp.value = val;
                    hiddenContainer.appendChild(inp);
                };
                
                let addedCount = 0;
                
                // Add exceptions
                exceptionData.forEach(ex => {
                    mk('perfume_product_id[]', ex.pid);
                    mk('default_grams[]', ex.grams);
                    mk('price[]', ex.price);
                    addedCount++;
                });

                // Add non-excepted checked items
                checked.forEach(cb => {
                    const chip = cb.closest('.perfume-chip-label');
                    if (chip.dataset.products) {
                        const products = JSON.parse(chip.dataset.products || '{}');
                        const qList = qualitiesToAdd || Object.keys(products);
                        qList.forEach(q => {
                            const pid = products[q];
                            if (pid && !exceptionPerfumes.has(pid.toString())) {
                                mk('perfume_product_id[]', pid);
                                mk('default_grams[]', sharedGrams.value);
                                mk('price[]', sharedPrice.value || '0');
                                addedCount++;
                            }
                        });
                    } else {
                        const pid = cb.value;
                        if (pid && !exceptionPerfumes.has(pid.toString())) {
                            mk('perfume_product_id[]', pid);
                            mk('default_grams[]', sharedGrams.value);
                            mk('price[]', sharedPrice.value || '0');
                            addedCount++;
                        }
                    }
                });
                
                if (addedCount === 0) {
                    e.preventDefault();
                    alert('<?= __('لم يتم إضافة أي عطور.') ?>');
                }
            });
        }

        // Init
        applyFilters();
    }

    // ===== confirm delete =====
    const msg = <?= json_encode(__('هل أنت متأكد من حذف هذا الإعداد نهائياً؟')) ?>;
    document.querySelectorAll('form.confirm-delete').forEach(f => {
        f.addEventListener('submit', (e) => {
            const customMsg = f.getAttribute('data-confirm') || msg;
            if (!confirm(customMsg)) e.preventDefault();
        });
    });
});
</script>
