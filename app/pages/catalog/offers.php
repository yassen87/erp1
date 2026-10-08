<?php
$offers = all_offers_list();
$activeCount = count(array_filter($offers, fn($o) => (int)$o['is_active'] === 1 && $o['days_left'] >= 0 && $o['start_date'] <= date('Y-m-d')));
$expiringCount = count(array_filter($offers, fn($o) => (int)$o['is_active'] === 1 && $o['days_left'] >= 0 && $o['days_left'] <= 3));
$inactiveCount = count($offers) - $activeCount;
?>
<section class="page-head">
    <div>
        <h2>🎁 عروض المنتجات والباكدجات</h2>
        <p>إدارة الباكدجات والعروض الترويجية والخصومات المتاحة للبيع المباشر في شاشة الكاشير.</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a class="btn primary" href="index.php?r=offer_create">➕ إضافة عرض جديد</a>
    </div>
</section>

<section class="cards">
    <article>
        <span>إجمالي العروض</span>
        <strong><?= e(count($offers)) ?></strong>
    </article>
    <article>
        <span>عروض نشطة حالياً</span>
        <strong style="color: var(--success);"><?= e($activeCount) ?></strong>
    </article>
    <article>
        <span>عروض تنتهي قريباً (≤ 3 أيام)</span>
        <strong style="color: var(--warning);"><?= e($expiringCount) ?></strong>
    </article>
    <article>
        <span>عروض متوقفة / منتهية</span>
        <strong style="color: var(--muted);"><?= e($inactiveCount) ?></strong>
    </article>
</section>

<?php if ($expiringCount > 0): ?>
<div class="alert warning" style="margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; gap: 10px;">
    <div>
        <strong>⏳ تنبيه انتهاء العروض:</strong>
        يوجد <b><?= e($expiringCount) ?></b> عرض ترويجي على وشك الانتهاء خلال 3 أيام أو أقل.
    </div>
    <a href="index.php?r=notifications" class="btn small" style="background: rgba(0,0,0,0.1); color: inherit;">عرض التنبيهات</a>
</div>
<?php endif; ?>

<div class="panel">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; gap: 8px; flex: 1; max-width: 400px;">
            <input type="text" id="offerSearchInput" placeholder="بحث باسم العرض أو الباركود..." style="width: 100%;" oninput="filterOffersTable()">
        </div>
        <div style="display: flex; gap: 6px;">
            <button type="button" class="btn small active" id="filter-all-btn" onclick="filterByStatus('all')">الكل (<?= count($offers) ?>)</button>
            <button type="button" class="btn small secondary" id="filter-active-btn" onclick="filterByStatus('active')">النشطة (<?= $activeCount ?>)</button>
            <button type="button" class="btn small secondary" id="filter-expiring-btn" onclick="filterByStatus('expiring')">تنتهي قريباً (<?= $expiringCount ?>)</button>
            <button type="button" class="btn small secondary" id="filter-inactive-btn" onclick="filterByStatus('inactive')">المتوقفة (<?= $inactiveCount ?>)</button>
        </div>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;" id="offersTable">
            <thead>
                <tr style="border-bottom: 2px solid var(--line); text-align: right;">
                    <th style="padding: 10px;">العرض والباركود</th>
                    <th style="padding: 10px;">مكونات العرض</th>
                    <th style="padding: 10px;">السعر الأصلي</th>
                    <th style="padding: 10px;">سعر العرض</th>
                    <th style="padding: 10px;">فترة السريان</th>
                    <th style="padding: 10px; text-align: center;">الحالة</th>
                    <th style="padding: 10px; text-align: center;">الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($offers)): ?>
                    <tr>
                        <td colspan="7" class="muted" style="text-align: center; padding: 30px;">
                            لا توجد عروض مضافة حالياً. اضغط على «إضافة عرض جديد» بالأعلى لإنشاء أول باكدج.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($offers as $o): 
                        $fullOffer = find_offer_with_items((int)$o['id']);
                        $isExpired = ($o['days_left'] < 0);
                        $isUpcoming = ($o['start_date'] > date('Y-m-d'));
                        $isExpiringSoon = ((int)$o['is_active'] === 1 && $o['days_left'] >= 0 && $o['days_left'] <= 3 && !$isUpcoming);
                        $isActiveNow = ((int)$o['is_active'] === 1 && !$isExpired && !$isUpcoming);
                        
                        $statusCategory = 'inactive';
                        if ($isActiveNow) {
                            $statusCategory = $isExpiringSoon ? 'expiring' : 'active';
                        }
                        
                        $priceBefore = (float)$o['price_before'];
                        $priceAfter = (float)$o['price_after'];
                        $savingAmount = max(0, $priceBefore - $priceAfter);
                        $savingPercent = $priceBefore > 0 ? round(($savingAmount / $priceBefore) * 100) : 0;
                    ?>
                    <tr class="offer-row" data-status="<?= $statusCategory ?>" data-search="<?= e(strtolower($o['name'] . ' ' . $o['barcode'])) ?>" style="border-bottom: 1px solid var(--line);">
                        <td style="padding: 12px 10px;">
                            <div style="font-weight: 800; font-size: 14.5px; color: var(--gold-dark, #b98418); margin-bottom: 4px;">
                                🎁 <?= e($o['name']) ?>
                            </div>
                            <?php if ($o['barcode']): ?>
                                <div style="font-family: monospace; font-size: 12px; color: var(--muted); display: inline-flex; align-items: center; gap: 4px;">
                                    <span><?= e($o['barcode']) ?></span>
                                    <a href="index.php?r=print_barcode&barcode=<?= urlencode($o['barcode']) ?>&name=<?= urlencode($o['name']) ?>&price=<?= urlencode((string)$o['price_after']) ?>" target="_blank" title="طباعة الباركود" style="text-decoration:none; font-size: 13px;">🖨️</a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($o['notes'])): ?>
                                <div style="font-size: 11.5px; color: var(--muted); margin-top: 3px;">
                                    <?= e($o['notes']) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td style="padding: 12px 10px; max-width: 260px;">
                            <?php if ($fullOffer && !empty($fullOffer['items'])): ?>
                                <ul style="margin: 0; padding-right: 16px; font-size: 12px; line-height: 1.5;">
                                    <?php foreach ($fullOffer['items'] as $it): ?>
                                        <li>
                                            <?php if ($it['item_type'] === 'product'): ?>
                                                <span><?= e(qty($it['quantity'])) ?> × <?= e($it['product_name'] ?: 'منتج') ?></span>
                                            <?php else: ?>
                                                <span><?= e(qty($it['quantity'])) ?> × تركيبة: <b><?= e($it['recipe_name'] ?: 'مخصصة') ?></b> (<?= e($it['bottle_name'] ?: 'بدون زجاجة') ?>)</span>
                                                <?php if (!empty($it['components'])): ?>
                                                    <span style="color: var(--muted); font-size: 11px;">
                                                        [<?php 
                                                            $oilNames = array_map(fn($c) => $c['perfume_name'] . ' ' . qty($c['grams']) . 'جم', $it['components']);
                                                            echo e(implode(', ', $oilNames));
                                                        ?>]
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <span class="muted" style="font-size: 12px;">بدون أصناف محددة</span>
                            <?php endif; ?>
                        </td>

                        <td style="padding: 12px 10px; font-size: 13px; text-decoration: line-through; color: var(--muted);">
                            <?= money($priceBefore) ?>
                        </td>

                        <td style="padding: 12px 10px;">
                            <div style="font-weight: 800; font-size: 14.5px; color: var(--primary);">
                                <?= money($priceAfter) ?>
                            </div>
                            <?php if ($savingAmount > 0): ?>
                                <div style="display: inline-block; background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 700; font-size: 11px; padding: 1px 6px; border-radius: 4px; margin-top: 2px;">
                                    توفير <?= money($savingAmount) ?> (<?= $savingPercent ?>%)
                                </div>
                            <?php endif; ?>
                        </td>

                        <td style="padding: 12px 10px; font-size: 12px;">
                            <div>من: <?= e($o['start_date']) ?></div>
                            <div>إلى: <?= e($o['end_date']) ?></div>
                            <div style="margin-top: 3px;">
                                <?php if ($isExpired): ?>
                                    <span class="badge danger">❌ منتهي الصلاحية</span>
                                <?php elseif ($isUpcoming): ?>
                                    <span class="badge info">📅 يبدأ قريباً</span>
                                <?php elseif ($isExpiringSoon): ?>
                                    <span class="badge warning" style="animation: pulse 1.5s infinite;">
                                        ⏳ <?= $o['days_left'] == 0 ? 'ينتهي اليوم!' : ($o['days_left'] == 1 ? 'ينتهي غداً!' : 'متبقي ' . $o['days_left'] . ' أيام') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge success">✅ ساري (متبقي <?= $o['days_left'] ?> يوم)</span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td style="padding: 12px 10px; text-align: center;">
                            <form method="post" action="index.php?r=offers" style="display: inline;">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                                <button type="submit" class="btn small <?= (int)$o['is_active'] === 1 ? 'success' : 'secondary' ?>" style="font-size: 11px; padding: 4px 8px;" title="اضغط للتغيير">
                                    <?= (int)$o['is_active'] === 1 ? '🟢 نشط' : '⚪ متوقف' ?>
                                </button>
                            </form>
                        </td>

                        <td style="padding: 12px 10px; text-align: center;">
                            <div style="display: flex; gap: 6px; justify-content: center;">
                                <a class="btn small" href="index.php?r=offer_edit&id=<?= e($o['id']) ?>" title="تعديل العرض">✏️ تعديل</a>
                                <form method="post" action="index.php?r=offers" onsubmit="return confirm('هل أنت متأكد من حذف هذا العرض نهائياً؟');" style="display: inline;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($o['id']) ?>">
                                    <button type="submit" class="btn small danger" title="حذف العرض">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let currentStatusFilter = 'all';

function filterByStatus(status) {
    currentStatusFilter = status;
    
    // Update button classes
    document.querySelectorAll('[id^="filter-"]').forEach(btn => {
        btn.className = 'btn small secondary';
    });
    const activeBtn = document.getElementById('filter-' + status + '-btn');
    if (activeBtn) activeBtn.className = 'btn small active';

    filterOffersTable();
}

function filterOffersTable() {
    const searchVal = document.getElementById('offerSearchInput').value.trim().toLowerCase();
    const rows = document.querySelectorAll('.offer-row');

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        const rowSearch = row.getAttribute('data-search') || '';

        let matchesStatus = true;
        if (currentStatusFilter === 'active') {
            matchesStatus = (rowStatus === 'active' || rowStatus === 'expiring');
        } else if (currentStatusFilter === 'expiring') {
            matchesStatus = (rowStatus === 'expiring');
        } else if (currentStatusFilter === 'inactive') {
            matchesStatus = (rowStatus === 'inactive');
        }

        const matchesSearch = !searchVal || rowSearch.includes(searchVal);

        if (matchesStatus && matchesSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>
