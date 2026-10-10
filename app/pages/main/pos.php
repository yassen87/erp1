<?php
$locations = sale_locations();
$userLocationId = current_user_location_id();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
}
// Use the user's branch or the first available branch for stock lookup
$posLocationId = $userLocationId ?? (int) ($locations[0]['id'] ?? 0);
$allProductsWithStock = array_values(all_products_with_stock($posLocationId ?: null, null, true));
// Filter: show only products with type != recipe
$products = array_values(array_filter($allProductsWithStock, fn($p) => $p['type'] !== 'recipe'));
$customers = all_customers();
$bottles = array_values(array_filter($products, fn($p) => $p['type'] === 'bottle'));
$perfumes = array_values(array_filter($products, fn($p) => $p['type'] === 'perfume_gram'));
$recipes = saved_recipes();
$offers = active_offers_for_pos($posLocationId ?: null);
$defaults = formula_defaults_rows();
$paymentDestinations = [
    'instapay' => payment_destination_label('instapay'),
    'vodafone_cash' => payment_destination_label('vodafone_cash'),
];
?>
<script>
    const formulaDefaults = <?= json_encode($defaults) ?>;
    const paymentDestinations = <?= json_encode($paymentDestinations) ?>;
    const ALL_OFFERS_OFFLINE = <?= json_encode($offers) ?>;
    const ALL_SAVED_RECIPES = <?= json_encode($recipes) ?>;

    // ===== Offline Data: all products + barcodes + customers =====
    const ALL_PRODUCTS_OFFLINE = <?= json_encode(array_map(fn($p) => [
        'id'               => (int)$p['id'],
        'name'             => $p['name'],
        'type'             => $p['type'],
        'sale_price'       => (float)$p['sale_price'],
        'barcode'          => $p['barcode'] ?? null,
        'sku'              => $p['sku'] ?? null,
        'size_ml'          => isset($p['size_ml']) ? (int)$p['size_ml'] : null,
        'perfume_family'   => $p['perfume_family'] ?? null,
        'quality_grade'    => $p['quality_grade'] ?? null,
        'price_per_gram'   => isset($p['price_per_gram']) ? (float)$p['price_per_gram'] : null,
        'branch_stock'     => isset($p['branch_stock']) ? (float)$p['branch_stock'] : null,
        'stock_initialized'=> isset($p['stock_initialized']) ? (int)$p['stock_initialized'] : 0,
    ], $allProductsWithStock)) ?>;

    const ALL_CUSTOMERS_OFFLINE = <?= json_encode(array_map(fn($c) => [
        'id'    => (int)$c['id'],
        'name'  => $c['name'],
        'phone' => $c['phone'] ?? '',
    ], $customers)) ?>;

    const POS_LOCATION_ID = <?= (int)$posLocationId ?>;
    const POS_CSRF = "<?= e(csrf_token()) ?>";
</script>


<style>
/* ===== ألوان الذهب من اللوجو - Hamza for Perfumes Brand Colors ===== */
:root {
    --gold: #C9A84C;
    --gold-light: #E8C96A;
    --gold-dark: #9E7A2A;
    --gold-soft: rgba(201, 168, 76, 0.10);
    --gold-soft2: rgba(201, 168, 76, 0.18);
    --dark-ink: #1A1210;
    --dark-surface: #2A1F18;
}

/* ===== POS Layout ===== */
.pos-container {
    display: grid;
    grid-template-columns: 2.2fr 1fr !important;
    gap: 22px !important;
    align-items: start;
    margin-top: 18px;
}

@media (max-width: 1024px) {
    .pos-container { grid-template-columns: 1fr !important; }
}

/* ===== Panels — بطاقات ذهبية واضحة ===== */
.pos-main-panel .panel,
.pos-side-panel .panel {
    background: var(--surface) !important;
    border-radius: 16px !important;
    border: 2px solid var(--gold-soft2) !important;
    box-shadow: 0 4px 24px rgba(201, 168, 76, 0.08), 0 1px 4px rgba(0,0,0,0.06) !important;
    padding: 24px !important;
    margin-bottom: 22px !important;
    position: relative;
    overflow: visible !important;
}

/* شريط ذهبي علوي للبطاقات */
.pos-main-panel .panel::before,
.pos-side-panel .panel::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold-dark), var(--gold), var(--gold-light), var(--gold));
    border-radius: 16px 16px 0 0;
    z-index: 0;
}

/* ===== عناوين البطاقات ===== */
.pos-main-panel .panel h3,
.pos-side-panel .panel h3 {
    font-size: 15px !important;
    font-weight: 800 !important;
    color: var(--gold-dark) !important;
    margin-top: 6px !important;
    margin-bottom: 18px !important;
    padding-bottom: 12px !important;
    border-bottom: 1.5px solid var(--gold-soft2) !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
    letter-spacing: 0.3px;
}

/* ===== شريط الباركود — بارز وواضح ===== */
.barcode-scanner-bar {
    background: linear-gradient(135deg, var(--gold-soft), transparent) !important;
    border: 2px solid var(--gold) !important;
    border-radius: 14px !important;
    padding: 13px 20px !important;
    margin-bottom: 22px !important;
    box-shadow: 0 4px 20px rgba(201, 168, 76, 0.15), 0 1px 4px rgba(0,0,0,0.05) !important;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.barcode-scanner-bar:focus-within {
    border-color: var(--gold-light) !important;
    box-shadow: 0 4px 28px rgba(201, 168, 76, 0.30) !important;
    background: linear-gradient(135deg, var(--gold-soft2), transparent) !important;
}

.barcode-scanner-bar input[type="text"] {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    font-size: 14px !important;
    color: var(--ink) !important;
    font-family: monospace;
}

.barcode-scanner-bar input[type="text"]:focus {
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
}

/* ===== حالة القارئ ===== */
.scanner-status {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    color: var(--gold-dark);
    font-size: 13px;
    white-space: nowrap;
}

.scanner-status-dot {
    width: 11px;
    height: 11px;
    background: var(--gold);
    border-radius: 50%;
    position: relative;
    box-shadow: 0 0 6px rgba(201, 168, 76, 0.5);
}

.scanner-status-dot::after {
    content: '';
    position: absolute;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: var(--gold);
    border-radius: 50%;
    animation: pulse-gold 1.8s infinite ease-in-out;
}

@keyframes pulse-gold {
    0%   { transform: scale(0.9); opacity: 0.8; }
    100% { transform: scale(2.6); opacity: 0; }
}

/* ===== POS Tabs (Multiple Invoices) ===== */
.pos-tabs-container {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    overflow-x: auto;
    padding-bottom: 6px;
    align-items: center;
}
.pos-tab {
    background: var(--surface);
    border: 2px solid var(--line);
    padding: 10px 16px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 700;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 10px;
    white-space: nowrap;
    transition: all 0.2s ease;
    font-size: 14px;
}
.pos-tab.active {
    background: var(--gold-soft);
    border-color: var(--gold);
    color: var(--gold-dark);
    box-shadow: 0 4px 12px rgba(201, 168, 76, 0.15);
}
.pos-tab:hover:not(.active) {
    background: var(--bg);
    border-color: var(--gold-soft2);
    color: var(--gold-dark);
}
.pos-tab-close {
    cursor: pointer;
    color: var(--danger);
    font-size: 16px;
    line-height: 1;
    border-radius: 4px;
    padding: 2px 4px;
    opacity: 0.7;
}
.pos-tab-close:hover {
    opacity: 1;
    background: rgba(220, 38, 38, 0.15);
}
.pos-tab-add {
    background: var(--surface);
    color: var(--primary);
    border: 2px dashed var(--primary);
    padding: 10px 18px;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 800;
    font-size: 14px;
    transition: all 0.2s ease;
}
.pos-tab-add:hover {
    background: var(--primary);
    color: #fff;
    box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.2);
}

/* ===== التبويبات ===== */
.segmented-tabs {
    display: flex;
    background: var(--gold-soft);
    padding: 4px;
    border-radius: 12px;
    border: 1.5px solid var(--gold-soft2);
    margin-bottom: 20px;
    gap: 3px;
}

.segmented-tabs button {
    flex: 1;
    border: none;
    background: transparent;
    color: var(--muted);
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 700;
    border-radius: 9px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.segmented-tabs button.active {
    background: var(--gold);
    color: #fff;
    box-shadow: 0 3px 10px rgba(201, 168, 76, 0.40);
    text-shadow: 0 1px 2px rgba(0,0,0,0.15);
}

.segmented-tabs button:not(.active):hover {
    background: var(--gold-soft2);
    color: var(--gold-dark);
}

/* ===== نموذج الإضافة — خانات واضحة جداً ===== */
.grid-form {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.grid-form label {
    display: flex;
    flex-direction: column;
    gap: 7px;
    font-size: 12.5px;
    font-weight: 800;
    color: var(--gold-dark);
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.grid-form input,
.grid-form select,
.grid-form textarea {
    padding: 11px 15px !important;
    font-size: 14px !important;
    border: 2px solid rgba(201, 168, 76, 0.35) !important;
    border-radius: 10px !important;
    background: var(--surface) !important;
    color: var(--ink) !important;
    transition: all 0.2s ease !important;
    font-family: inherit;
    outline: none;
    font-weight: 600;
}

.grid-form input:focus,
.grid-form select:focus,
.grid-form textarea:focus {
    border-color: var(--gold) !important;
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.18) !important;
    background: var(--gold-soft) !important;
}

/* ===== جدول السلة ===== */
table.cart-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 8px;
    margin-top: -8px;
}

table.cart-table th {
    font-weight: 800;
    color: var(--gold-dark);
    font-size: 11.5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 12px;
    border: none;
    text-align: right;
}

table.cart-table tbody tr {
    background: var(--surface-soft);
    transition: all 0.2s ease;
    box-shadow: 0 1px 4px rgba(201, 168, 76, 0.06);
}

table.cart-table tbody tr:hover {
    background: var(--gold-soft) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(201, 168, 76, 0.15) !important;
}

table.cart-table td {
    padding: 14px 12px;
    border: none;
    vertical-align: middle;
}

table.cart-table td:first-child { border-radius: 0 10px 10px 0; }
table.cart-table td:last-child  { border-radius: 10px 0 0 10px; }

/* ===== أزرار الكمية ===== */
.qty-control {
    display: inline-flex !important;
    align-items: center !important;
    border: 2px solid rgba(201, 168, 76, 0.40) !important;
    border-radius: 9px !important;
    overflow: hidden !important;
    background: var(--surface) !important;
    height: 34px !important;
}

.qty-control button {
    border: none !important;
    background: var(--gold-soft) !important;
    width: 34px !important;
    height: 34px !important;
    padding: 0 !important;
    font-size: 17px !important;
    font-weight: bold !important;
    cursor: pointer !important;
    color: var(--gold-dark) !important;
    transition: background 0.15s ease !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.qty-control button:hover {
    background: var(--gold) !important;
    color: #fff !important;
}

.qty-control input {
    width: 42px !important;
    height: 34px !important;
    text-align: center !important;
    border: none !important;
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
    font-weight: 800 !important;
    font-size: 14px !important;
    outline: none !important;
    color: var(--ink) !important;
    -webkit-appearance: none !important;
    -moz-appearance: textfield !important;
}

.qty-control input::-webkit-outer-spin-button,
.qty-control input::-webkit-inner-spin-button {
    -webkit-appearance: none !important;
    margin: 0 !important;
}

/* ===== ملخص الفاتورة ===== */
.receipt-box {
    background: linear-gradient(135deg, var(--gold-soft), var(--surface-soft));
    border: 1.5px solid var(--gold-soft2);
    border-radius: 14px;
    padding: 18px;
    margin-top: 15px;
    margin-bottom: 15px;
    display: grid;
    gap: 11px;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13.5px;
    font-weight: 600;
}

.receipt-row strong {
    color: var(--gold-dark);
    font-weight: 800;
}

.receipt-row.total {
    border-top: 1.5px solid var(--gold-soft2);
    padding-top: 13px;
    font-size: 17px;
    font-weight: 900;
    color: var(--gold-dark);
}

.receipt-row.total strong {
    color: var(--gold);
    font-size: 18px;
}

/* ===== شارات الأنواع ===== */
.badge-type {
    font-size: 10px !important;
    padding: 3px 9px !important;
    border-radius: 6px !important;
    font-weight: 800 !important;
    display: inline-block;
    margin-right: 6px;
}

.badge-type.direct {
    background: var(--gold-soft2);
    color: var(--gold-dark);
    border: 1px solid rgba(201,168,76,0.3);
}

.badge-type.recipe {
    background: rgba(6, 182, 212, 0.12);
    color: var(--accent);
}

.badge-type.custom {
    background: rgba(245, 158, 11, 0.12);
    color: var(--warning);
}

/* ===== حاوية الدفع المختلط ===== */
#split-payment-container {
    background: var(--gold-soft);
    border: 1.5px dashed var(--gold);
    border-radius: 12px;
    padding: 14px;
    margin-top: 10px;
    display: none;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

#split-payment-container label {
    font-size: 11px;
    font-weight: 800;
    color: var(--gold-dark);
    display: flex;
    flex-direction: column;
    gap: 4px;
    text-transform: uppercase;
}

#split-payment-container input {
    background: var(--surface) !important;
    border: 2px solid rgba(201, 168, 76, 0.35) !important;
    padding: 9px 12px !important;
    border-radius: 8px !important;
    font-size: 14px !important;
    font-weight: 800 !important;
    text-align: center;
}

#split-payment-container input:focus {
    border-color: var(--gold) !important;
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.18) !important;
}

/* ===== زر إغلاق الفاتورة — ذهبي فاخر ===== */
.btn-checkout {
    background: linear-gradient(135deg, var(--gold-dark), var(--gold), var(--gold-light)) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 13px !important;
    padding: 15px 20px !important;
    font-size: 15px !important;
    font-weight: 800 !important;
    cursor: pointer !important;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    box-shadow: 0 5px 18px rgba(201, 168, 76, 0.45) !important;
    text-shadow: 0 1px 3px rgba(0,0,0,0.20);
    letter-spacing: 0.3px;
}

.btn-checkout:hover {
    background: linear-gradient(135deg, var(--gold), var(--gold-light), var(--gold)) !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 8px 26px rgba(201, 168, 76, 0.55) !important;
}

.btn-checkout:active {
    transform: translateY(0) !important;
    box-shadow: 0 3px 10px rgba(201, 168, 76, 0.35) !important;
}

/* ===== زر حذف صنف ===== */
.btn-delete-item {
    background: rgba(220, 38, 38, 0.08) !important;
    color: var(--danger) !important;
    border: 1.5px solid rgba(220,38,38,0.2) !important;
    width: 33px !important;
    height: 33px !important;
    border-radius: 8px !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-size: 16px !important;
    font-weight: 700 !important;
    transition: all 0.15s ease !important;
}

.btn-delete-item:hover {
    background: var(--danger) !important;
    color: #fff !important;
    border-color: var(--danger) !important;
    transform: scale(1.05) !important;
}

/* ===== خانات النماذج الجانبية (إغلاق الحساب) ===== */
.pos-side-panel label {
    font-size: 12.5px !important;
    font-weight: 800 !important;
    color: var(--gold-dark) !important;
    display: flex;
    flex-direction: column;
    gap: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.pos-side-panel select,
.pos-side-panel input[type="number"],
.pos-side-panel textarea {
    padding: 11px 14px !important;
    font-size: 14px !important;
    border: 2px solid rgba(201, 168, 76, 0.35) !important;
    border-radius: 10px !important;
    background: var(--surface) !important;
    color: var(--ink) !important;
    transition: all 0.2s ease !important;
    font-weight: 600;
    width: 100%;
}

.pos-side-panel select:focus,
.pos-side-panel input[type="number"]:focus,
.pos-side-panel textarea:focus {
    border-color: var(--gold) !important;
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.18) !important;
    background: var(--gold-soft) !important;
    outline: none !important;
}

/* ===== custom-select-trigger للـ side panel ===== */
.pos-side-panel .custom-select-trigger {
    border: 2px solid rgba(201, 168, 76, 0.35) !important;
    border-radius: 10px !important;
    padding: 10px 14px !important;
    background: var(--surface) !important;
    font-weight: 600 !important;
    font-size: 14px !important;
    transition: border-color 0.2s !important;
}

.pos-side-panel .custom-select-wrapper.open .custom-select-trigger {
    border-color: var(--gold) !important;
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.18) !important;
    background: var(--gold-soft) !important;
}

/* ===== custom-select-trigger للـ main panel ===== */
.pos-main-panel .custom-select-trigger {
    border: 2px solid rgba(201, 168, 76, 0.35) !important;
    border-radius: 10px !important;
    padding: 10px 14px !important;
    background: var(--surface) !important;
    font-weight: 600 !important;
    font-size: 14px !important;
}

.pos-main-panel .custom-select-wrapper.open .custom-select-trigger {
    border-color: var(--gold) !important;
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.18) !important;
    background: var(--gold-soft) !important;
}

/* ===== عنصر بحث الباركود ===== */
#barcode-feedback {
    font-size: 13.5px !important;
    font-weight: 800 !important;
    min-width: 130px;
    text-align: center;
}

#barcode-lookup-btn {
    background: var(--gold) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 9px !important;
    padding: 10px 22px !important;
    font-size: 13.5px !important;
    font-weight: 800 !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 3px 10px rgba(201, 168, 76, 0.35) !important;
    white-space: nowrap;
}

#barcode-lookup-btn:hover {
    background: var(--gold-dark) !important;
    box-shadow: 0 5px 16px rgba(201, 168, 76, 0.50) !important;
    transform: translateY(-1px) !important;
}
/* ===== تحديد المخزون في القائمة المنسدلة ===== */
option.out-of-stock {
    color: #dc2626 !important;
    font-weight: 700;
}
option.low-stock {
    color: #d97706 !important;
    font-weight: 700;
}
#stock-warning-bar {
    display: none;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    margin-top: 6px;
    text-align: center;
}
#stock-warning-bar.out {
    background: rgba(220,38,38,0.10);
    color: #dc2626;
    border: 1.5px solid rgba(220,38,38,0.25);
    display: block;
}
#stock-warning-bar.low {
    background: rgba(217,119,6,0.10);
    color: #d97706;
    border: 1.5px solid rgba(217,119,6,0.25);
    display: block;
}
#stock-warning-bar.ok {
    background: rgba(22,163,74,0.08);
    color: #16a34a;
    border: 1.5px solid rgba(22,163,74,0.2);
    display: block;
}

/* ===== تحسينات التجاوب والهواتف المحمولة في الكاشير ===== */
@media (max-width: 900px) {
    .segmented-tabs {
        flex-direction: column !important;
        border-radius: 14px !important;
    }
    .segmented-tabs button {
        padding: 8px 10px !important;
        font-size: 12px !important;
    }
    .grid-form {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 12px !important;
    }
    #add-prod-btn, #add-recipe-btn, #add-mix-to-cart-btn {
        grid-column: span 2 !important;
    }
    .barcode-scanner-bar {
        flex-wrap: wrap !important;
        padding: 10px 14px !important;
        gap: 10px !important;
    }
    .barcode-scanner-bar input[type="text"] {
        min-width: 180px;
        flex: 1 1 auto !important;
    }
    #barcode-lookup-btn {
        width: 100% !important;
    }
}

@media (max-width: 480px) {
    .grid-form {
        grid-template-columns: 1fr !important;
    }
    .grid-form label,
    #add-prod-select, 
    #add-recipe-select, 
    #stock-warning-bar, 
    #add-prod-btn, 
    #add-recipe-btn, 
    #add-mix-to-cart-btn {
        grid-column: span 1 !important;
    }
    #split-payment-container {
        grid-template-columns: 1fr !important;
    }
}

/* ===== احتفالية نصر 6 أكتوبر المجيد 1973 ===== */
.october-pos-badge {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, rgba(201, 168, 76, 0.15) 0%, rgba(26, 18, 16, 0.08) 100%);
    border: 1.5px solid var(--gold);
    border-radius: 12px;
    padding: 5px 14px 5px 8px;
    cursor: pointer;
    transition: all 0.25s ease;
    user-select: none;
    box-shadow: 0 2px 10px rgba(201, 168, 76, 0.15);
}
.october-pos-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(201, 168, 76, 0.3);
    border-color: var(--gold-light);
    background: linear-gradient(135deg, rgba(201, 168, 76, 0.25) 0%, rgba(26, 18, 16, 0.12) 100%);
}
.october-thumb-box {
    position: relative;
    width: 38px;
    height: 44px;
    flex-shrink: 0;
    border-radius: 8px;
    overflow: hidden;
    border: 1.5px solid var(--gold);
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
}
.october-thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.october-badge-text {
    text-align: right;
    line-height: 1.35;
}
.october-badge-title {
    font-size: 13.5px;
    font-weight: 800;
    color: var(--gold-dark);
    display: flex;
    align-items: center;
    gap: 6px;
}
:root[data-theme="dark"] .october-badge-title {
    color: var(--gold-light);
}
.october-badge-sub {
    font-size: 11px;
    color: var(--muted);
    font-weight: 600;
}
.october-zoom-btn {
    background: var(--gold);
    color: #1a1210;
    font-weight: 800;
    font-size: 10.5px;
    padding: 2px 6px;
    border-radius: 6px;
    margin-right: 4px;
    display: inline-block;
}

/* Modal بوستر نصر أكتوبر التذكاري */
.october-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 999999;
    background: rgba(0, 0, 0, 0.85);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: fadeIn 0.25s ease;
}
.october-modal.show {
    display: flex;
}
.october-modal-card {
    background: #0f172a;
    border: 2px solid var(--gold);
    border-radius: 20px;
    max-width: 500px;
    width: 100%;
    max-height: 94vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0,0,0,0.8), 0 0 35px rgba(201, 168, 76, 0.3);
    animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(20px) scale(0.96); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
.october-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 20px;
    background: linear-gradient(135deg, rgba(201, 168, 76, 0.2), rgba(15, 23, 42, 0.95));
    border-bottom: 1.5px solid rgba(201, 168, 76, 0.3);
}
.october-modal-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: #fff;
    font-size: 24px;
    line-height: 1;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}
.october-modal-close:hover {
    background: rgba(239, 68, 68, 0.8);
    transform: rotate(90deg);
}
.october-modal-body {
    padding: 14px;
    display: flex;
    justify-content: center;
    align-items: center;
    overflow-y: auto;
    background: #070b14;
}
.october-full-img {
    max-width: 100%;
    max-height: 68vh;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    border: 1px solid rgba(201, 168, 76, 0.3);
    object-fit: contain;
}
.october-modal-footer {
    padding: 12px 18px;
    background: #0b1120;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12.5px;
    color: #94a3b8;
    gap: 10px;
    flex-wrap: wrap;
}

/* ===== Returns & Exchanges Styles ===== */
.btn-customer-invoices {
    width: 100%;
    background: linear-gradient(135deg, rgba(201, 168, 76, 0.12), rgba(201, 168, 76, 0.22));
    border: 1.5px solid var(--gold);
    color: var(--ink);
    padding: 7px 12px;
    border-radius: 9px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s ease;
    margin-top: 6px;
}
.btn-customer-invoices:hover {
    background: linear-gradient(135deg, rgba(201, 168, 76, 0.25), rgba(201, 168, 76, 0.35));
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(201, 168, 76, 0.2);
}

.exchange-active-card {
    background: linear-gradient(135deg, rgba(201, 168, 76, 0.14), rgba(245, 158, 11, 0.10));
    border: 2px dashed var(--gold);
    border-radius: 12px;
    padding: 12px 16px;
    margin-bottom: 14px;
    position: relative;
    animation: fadeIn 0.3s ease;
}

.customer-inv-card {
    background: var(--surface-soft);
    border: 1.5px solid rgba(201, 168, 76, 0.25);
    border-radius: 12px;
    padding: 14px;
    margin-bottom: 12px;
    transition: all 0.2s ease;
}
.customer-inv-card:hover {
    border-color: var(--gold);
    box-shadow: 0 3px 12px rgba(201, 168, 76, 0.12);
}

.customer-inv-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(201, 168, 76, 0.15);
}

.inv-line-row {
    display: grid;
    grid-template-columns: 28px 1fr 90px 90px 100px 90px;
    gap: 8px;
    align-items: center;
    padding: 8px 6px;
    border-bottom: 1px dashed rgba(201, 168, 76, 0.12);
    font-size: 12.5px;
}
.inv-line-row:last-child {
    border-bottom: none;
}
.inv-line-row.selected {
    background: rgba(201, 168, 76, 0.08);
    border-radius: 6px;
}
</style>

<section class="page-head" style="align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2>شاشة البيع والكاشير POS</h2>
        <p>إدارة المبيعات الفورية، والتركيبات الفورية والوصفات الجاهزة مع احتساب فوري للقيم والخصومات.</p>
    </div>
    <!-- احتفالية نصر 6 أكتوبر المجيد 1973 دون التأثير على ديزاين الكاشير -->
    <div class="october-pos-badge" onclick="openOctoberModal()" title="انقر لتكبير بوستر نصر 6 أكتوبر التذكاري">
        <div class="october-thumb-box">
            <img src="assets/october_victory.jpg" alt="نصر أكتوبر" class="october-thumb-img">
        </div>
        <div class="october-badge-text">
            <div class="october-badge-title">
                <span>🇪🇬 الذكرى الـ 53 لنصر أكتوبر المجيد</span>
            </div>
            <div class="october-badge-sub">
                حرب أكتوبر 1973 - فخر العزة والكرامة <span class="october-zoom-btn">🔍 عرض</span>
            </div>
        </div>
    </div>
</section>

<!-- Barcode Scanner Input -->
<div class="barcode-scanner-bar">
    <div class="scanner-status">
        <span class="scanner-status-dot"></span>
        <span>القارئ التلقائي نشط</span>
    </div>
    <input type="text" id="barcode-scanner-input" autocomplete="off" placeholder="امسح الباركود ضوئياً أو اكتبه (نشط تلقائياً من أي مكان في الصفحة)..." style="flex: 1; direction: ltr; text-align: left; font-family: monospace; letter-spacing: 1px;">
    <button type="button" class="btn small primary" id="barcode-lookup-btn" style="padding: 10px 20px; font-size: 13px; font-weight: 600; border-radius: 8px;">بحث</button>
    <div id="barcode-feedback" style="font-size: 13px; font-weight: 700; color: var(--muted); min-width: 120px; text-align: center;"></div>
</div>

<form class="pos-container" method="post" id="pos-main-form">
    <!-- Left Column: Basket / Products / Mixes -->
    <div class="pos-main-panel">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        
        <!-- POS Tabs Container -->
        <div id="pos-tabs-container" class="pos-tabs-container">
            <!-- Tabs injected by JS -->
        </div>

        <!-- Hidden fields generated by JavaScript for checkout -->
        <div id="hidden-cart-inputs"></div>

        <!-- Section 1: Add Items to Basket -->
        <div class="panel">
            <h3 style="border-bottom: 1px solid var(--line); padding-bottom: 6px; margin-bottom: 12px; color: var(--primary);">
                إضافة الأصناف والمبيعات</h3>

            <div class="segmented-tabs">
                <button type="button" class="active" id="tab-direct-btn" onclick="switchAddTab('direct')">📦 منتج مباشر / جاهز</button>
                <button type="button" id="tab-recipe-btn" onclick="switchAddTab('recipe')">📜 تركيبة جاهزة (وصفة)</button>
                <button type="button" id="tab-mix-btn" onclick="switchAddTab('mix')">🧪 تركيبة فورية (تفصيل)</button>
                <button type="button" id="tab-offer-btn" onclick="switchAddTab('offer')">🎁 عروض وباكدجات</button>
            </div>

            <!-- Tab Content 1: Direct Products -->
            <div id="add-direct-panel" class="grid-form">
                <label style="grid-column: span 2;">اختر المنتج
                    <select id="add-prod-select">
                        <option value="">-- اختر صنف مباشر --</option>
                        <?php foreach ($products as $p): ?>
                            <?php
                                $stock = isset($p['branch_stock']) ? (float)$p['branch_stock'] : null;
                                $isInitialized = isset($p['stock_initialized']) ? (int)$p['stock_initialized'] : 0;
                                $stockLabel = '';
                                $stockClass = '';
                                if ($stock !== null) {
                                    if (!$isInitialized) {
                                        $stockLabel = ' [ℹ️ لم يتم تسجيل الكمية]';
                                        $stockClass = 'no-stock-info';
                                    } elseif ($stock <= 0) {
                                        $stockLabel = ' [⚠️ نفد المخزون]';
                                        $stockClass = 'out-of-stock';
                                    } elseif ($stock < 3) {
                                        $stockLabel = ' [⚡ ' . qty($stock) . ' فقط]';
                                        $stockClass = 'low-stock';
                                    } else {
                                        $stockLabel = ' [📦 ' . qty($stock) . ']';
                                    }
                                }
                            ?>
                            <option value="<?= e($p['id']) ?>" data-name="<?= e($p['name']) ?>"
                                data-price="<?= e($p['sale_price']) ?>"
                                data-stock="<?= e($stock ?? '') ?>"
                                data-stock-init="<?= e($isInitialized) ?>"
                                class="<?= $stockClass ?>"><?= e($p['name']) ?> -
                                (<?= money($p['sale_price']) ?>)<?= $stockLabel ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div id="stock-warning-bar" style="grid-column: span 2;"></div>
                <label>الكمية
                    <input id="add-prod-qty" type="number" step="any" min="0.01" value="1">
                </label>
                <label>سعر الوحدة
                    <input id="add-prod-price" type="number" step="any" min="0" placeholder="0">
                </label>
                <label>نوع خصم الصنف
                    <select id="add-prod-discount-type">
                        <option value="">بدون</option>
                        <option value="amount">مبلغ</option>
                        <option value="percent">%</option>
                    </select>
                </label>
                <label>قيمة الخصم
                    <input id="add-prod-discount-value" type="number" step="any" min="0" placeholder="0">
                </label>
                <button type="button" class="btn primary" id="add-prod-btn" onclick="addProductToCart()"
                    style="grid-column: span 2; height: 36px; border-radius: 8px;">إضافة صنف للسلة</button>
            </div>

            <!-- Tab Content 2: Saved Recipes -->
            <div id="add-recipe-panel" class="grid-form" style="display: none;">
                <label style="grid-column: span 2;">اختر الوصفة الجاهزة
                    <select id="add-recipe-select" onchange="onSavedRecipeSelectChange()">
                        <option value="">-- اختر تركيبة جاهزة --</option>
                        <?php foreach ($recipes as $r): ?>
                            <option value="<?= e($r['id']) ?>" data-name="<?= e($r['name']) ?>"
                                data-price="<?= e($r['default_sale_price']) ?>"><?= e($r['name']) ?> -
                                (<?= money($r['default_sale_price']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label style="grid-column: span 2;">نوع الزجاجة المستخدمة
                    <select id="saved_recipe_bottle_id">
                        <option value="default">الزجاجة الافتراضية للتركيبة</option>
                        <option value="no_bottle" data-price="0" data-size="0">بدون زجاجة (0.00 ج.م)</option>
                        <?php foreach ($bottles as $b): ?>
                            <option value="<?= e($b['id']) ?>" data-price="<?= e($b['sale_price']) ?>" data-size="<?= e($b['size_ml']) ?>"><?= e($b['name']) ?> (<?= e($b['size_ml']) ?>ml) - (<?= money($b['sale_price']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>الكمية (عدد الزجاجات)
                    <input id="add-recipe-qty" type="number" step="any" min="0.01" value="1">
                </label>
                <label>السعر
                    <input id="add-recipe-price" type="number" step="any" min="0" placeholder="0">
                </label>
                <button type="button" class="btn primary" id="add-recipe-btn" onclick="addRecipeToCart()"
                    style="grid-column: span 2; height: 36px; border-radius: 8px;">إضافة التركيبة للسلة</button>
            </div>

            <!-- Tab Content 3: Instant Mix (تركيبة فورية) -->
            <div id="add-mix-panel" class="grid-form" style="display: none;">
                <div style="grid-column: span 2; display: flex; justify-content: flex-end; margin-bottom: 5px;">
                    <button type="button" class="btn small" onclick="openOffOrderModal()" style="background: var(--surface-soft); color: var(--gold-dark); border: 1px solid var(--gold); border-radius: 8px; font-weight: 800; font-size: 12px; padding: 6px 12px;">
                        🔄 استدعاء تركيبة مرتجعة (Off Order)
                    </button>
                </div>
                <label style="grid-column: span 2;">نوع الزجاجة المستخدمة
                    <select id="mix_bottle_id">
                        <option value="">-- اختر الزجاجة --</option>
                        <option value="no_bottle" data-price="0" data-size="0">بدون زجاجة (0.00 ج.م)</option>
                        <option value="pump_only" data-price="0" data-size="0">بمبة</option>
                        <?php foreach ($bottles as $b): ?>
                            <option value="<?= e($b['id']) ?>" data-price="<?= e($b['sale_price']) ?>" data-size="<?= e($b['size_ml']) ?>"><?= e($b['name']) ?> (<?= e($b['size_ml']) ?>ml) - (<?= money($b['sale_price']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>سعر البيع الإجمالي للتركيبة
                    <input id="mix_sale_price" type="number" step="1" min="0" placeholder="اكتب السعر النهائي" data-manual="0" oninput="markMixPriceManual(); recalculateTotals()">
                </label>
                <div style="grid-column: span 2; display: flex; justify-content: space-between; align-items: center; margin-top: 4px; margin-bottom: 4px; gap: 8px; flex-wrap: wrap;">
                    <h4 style="font-size: 12.5px; font-weight: 700; color: var(--muted); margin: 0;">مكونات الزيوت العطرية بالجرام</h4>
                    <div style="display: flex; gap: 6px;">
                        <button type="button" class="btn small primary" id="add-oil-row-btn" onclick="addOilRow()" style="padding: 2px 8px; font-size: 11px; border-radius: 6px;">+ إضافة زيت عطري</button>
                    </div>
                </div>
<style>
/* تظبيط زر وعداد جرامات الزيت في التركيبة الفورية */
.mix-gram-stepper {
    display: inline-flex !important;
    align-items: center !important;
    direction: ltr !important;
    border: 2px solid var(--primary, #b98418) !important;
    border-radius: 10px !important;
    overflow: hidden !important;
    background: var(--surface, #ffffff) !important;
    height: 40px !important;
    width: 145px !important;
    min-width: 145px !important;
    max-width: 145px !important;
    flex-shrink: 0 !important;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04) !important;
    margin: 0 !important;
    padding: 0 !important;
    box-sizing: border-box !important;
}

.mix-gram-stepper .stepper-btn {
    border: none !important;
    background: var(--primary-soft, rgba(185, 132, 24, 0.12)) !important;
    width: 38px !important;
    min-width: 38px !important;
    height: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    color: var(--primary, #b98418) !important;
    font-weight: 900 !important;
    font-size: 20px !important;
    line-height: 1 !important;
    padding: 0 !important;
    margin: 0 !important;
    transition: background 0.15s, color 0.15s !important;
    user-select: none !important;
    flex-shrink: 0 !important;
    box-shadow: none !important;
    border-radius: 0 !important;
}

.mix-gram-stepper .stepper-btn:hover {
    background: var(--primary, #b98418) !important;
    color: #ffffff !important;
}

.mix-gram-stepper .stepper-btn:active {
    opacity: 0.8 !important;
}

.mix-gram-stepper input.stepper-input {
    flex: 1 !important;
    width: 65px !important;
    min-width: 65px !important;
    height: 100% !important;
    text-align: center !important;
    border: none !important;
    border-radius: 0 !important;
    background: transparent !important;
    padding: 0 4px !important;
    margin: 0 !important;
    font-size: 16px !important;
    font-weight: 800 !important;
    color: var(--ink, #0f172a) !important;
    outline: none !important;
    box-shadow: none !important;
    -moz-appearance: textfield !important;
    appearance: none !important;
    direction: ltr !important;
}

.mix-gram-stepper input.stepper-input:focus {
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
}

.mix-gram-stepper input.stepper-input::-webkit-outer-spin-button,
.mix-gram-stepper input.stepper-input::-webkit-inner-spin-button {
    -webkit-appearance: none !important;
    margin: 0 !important;
    display: none !important;
}

.mix-perfume-row {
    grid-template-columns: 1fr auto auto !important;
    gap: 8px !important;
    align-items: center !important;
}
</style>
                <div id="mix-perfumes-container" style="grid-column: span 2; display: grid; gap: 6px;">
                    <!-- First row default -->
                    <div class="line-grid two mix-perfume-row">
                        <select name="mix_perfume_id[]" onchange="onPerfumeOrBottleChange(this)">
                            <option value="">-- اختر الزيت العطري --</option>
                            <?php foreach ($perfumes as $p): ?>
                                <option value="<?= e($p['id']) ?>" 
                                        data-price="<?= e($p['price_per_gram'] ?? $p['sale_price']) ?>"
                                        data-family="<?= e($p['perfume_family']) ?>"
                                        data-grade="<?= e($p['quality_grade']) ?>">
                                    <?= e($p['name']) ?> (<?= e($p['quality_grade'] ?: '-') ?>) - (<?= money($p['price_per_gram'] ?? $p['sale_price']) ?>/جم)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="mix-gram-stepper">
                            <button type="button" class="stepper-btn" onclick="adjustGramRow(this, -1)">–</button>
                            <input name="mix_grams[]" type="number" step="any" min="0" placeholder="جم" value="" oninput="calculateSuggestedMixPrice()" class="stepper-input">
                            <button type="button" class="stepper-btn" onclick="adjustGramRow(this, 1)">+</button>
                        </div>
                        <button type="button" class="btn small danger" onclick="removeOilRow(this)" style="padding: 6px 12px; border-radius: 8px;">حذف</button>
                    </div>
                </div>
                <small id="mix-default-note" class="muted" style="grid-column: span 2; display: block; min-height: 18px;"></small>
                <div style="grid-column: span 2; display: flex; gap: 8px;">
                    <button type="button" class="btn primary" id="add-mix-to-cart-btn" onclick="addMixToCart()" style="flex: 1.2; height: 38px; border-radius: 8px; font-weight: 800;">
                        ➕ إضافة التركيبة الفورية للسلة
                    </button>
                    <button type="button" class="btn secondary" id="add-mix-to-offer-btn" onclick="addMixDirectlyToOffer()" style="flex: 1; height: 38px; border-radius: 8px; font-weight: 700; border: 1.5px dashed var(--gold); color: var(--gold-dark); background: var(--gold-soft);" title="تجهيز هذه التركيبة وضمها مباشرة إلى عرض ترويجي">
                        🎁 ضم هذه التركيبة لعرض
                    </button>
                </div>
            </div>

            <!-- Tab Content 4: Offers / Bundles (عروض وباكدجات) -->
            <div id="add-offer-panel" class="grid-form" style="display: none;">
                <label style="grid-column: span 2;">اختر العرض / الباكدج
                    <select id="add-offer-select" onchange="onOfferSelectChange()">
                        <option value="">-- اختر عرضاً ترويجياً --</option>
                        <?php foreach ($offers as $o): ?>
                            <option value="<?= e($o['id']) ?>"
                                data-name="<?= e($o['name']) ?>"
                                data-price="<?= e($o['price_after']) ?>"
                                data-before="<?= e($o['price_before']) ?>">
                                🎁 <?= e($o['name']) ?> — (<?= money($o['price_after']) ?> بدلاً من <?= money($o['price_before']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div id="offer-contents-preview" style="grid-column: span 2; display: none; background: var(--surface-soft, rgba(0,0,0,0.03)); border: 1px solid var(--line); border-radius: 8px; padding: 10px; font-size: 12.5px;"></div>
                <label>الكمية (عدد الباكدجات)
                    <input id="add-offer-qty" type="number" step="1" min="1" value="1">
                </label>
                <label>سعر الباكدج (بعد الخصم)
                    <input id="add-offer-price" type="number" step="any" min="0" placeholder="0">
                </label>
                <div style="grid-column: span 2; display: flex; gap: 8px; margin-top: 4px;">
                    <button type="button" class="btn primary" id="btn-customize-offer" onclick="openOfferCustomizerFromTab()"
                        style="flex: 1.4; height: 38px; border-radius: 8px; background: linear-gradient(135deg, #b98418, #d4af37); font-weight: 800; font-size: 13px; color: #1a1210; box-shadow: 0 3px 10px rgba(185,132,24,0.3);">
                        ✨ تخصيص الزجاجات والتركيبة
                    </button>
                    <button type="button" class="btn secondary" id="add-offer-btn" onclick="addOfferToCart(false)"
                        style="flex: 1; height: 38px; border-radius: 8px; font-weight: 700; font-size: 12.5px;" title="إضافة العرض مباشرة بالمكونات الافتراضية">
                        🎁 إضافة سريعة
                    </button>
                </div>
            </div>
        </div>

        <!-- Section 2: Shopping Cart (السلة) -->
        <div class="panel" style="border-top: 3px solid var(--primary);">
            <h3 style="border-bottom: 1px solid var(--line); padding-bottom: 6px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                <span>سلة المبيعات الحالية</span>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button type="button" class="btn small primary" id="btn-bundle-cart-offer" onclick="openBundleOfferModal()" style="padding: 4px 12px; font-size: 11.5px; border-radius: 6px; background: linear-gradient(135deg, #b98418, #d4af37); color: #1a1210; font-weight: 800; border: none; box-shadow: 0 2px 6px rgba(185,132,24,0.3);" title="تطبيق عرض ترويجي على التركيبات والأصناف المجهزة في السلة">
                        🎁 تطبيق عرض على تركيبات السلة
                    </button>
                    <button type="button" class="btn small danger" onclick="clearCart()" style="padding: 4px 10px; font-size: 11px; border-radius: 6px;">تفريغ السلة</button>
                    <span class="badge" id="cart-count">0 أصناف</span>
                </div>
            </h3>

            <!-- Exchange Mode Active Banner -->
            <div id="exchange-active-banner" class="exchange-active-card" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 24px;">🔄</span>
                        <div>
                            <strong style="color: var(--gold-dark); font-size: 14px; display: block;">وضع الاستبدال نشط (فاتورة #<span id="exchange-orig-inv-number"></span>)</strong>
                            <span style="font-size: 12px; color: var(--ink); opacity: 0.85;" id="exchange-items-desc"></span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="text-align: left; background: var(--surface); padding: 4px 12px; border-radius: 8px; border: 1px solid var(--gold);">
                            <span style="font-size: 11px; color: var(--muted); display: block;">رصيد الاستبدال المتاح</span>
                            <strong id="exchange-credit-badge" style="font-size: 16px; color: #16a34a; font-weight: 900;">0.00 ج.م</strong>
                        </div>
                        <button type="button" class="btn small danger" onclick="cancelExchange()" style="padding: 6px 12px; font-size: 12px; border-radius: 8px; font-weight: 700;" title="إلغاء وضع الاستبدال">✖ إلغاء الاستبدال</button>
                    </div>
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>اسم الصنف</th>
                            <th style="width: 110px; text-align: center;">الكمية</th>
                            <th style="width: 100px; text-align: center;">السعر</th>
                            <th style="width: 160px; text-align: center;">الخصم</th>
                            <th style="width: 100px; text-align: right;">الإجمالي</th>
                            <th style="width: 40px;"></th>
                        </tr>
                    </thead>
                    <tbody id="cart-tbody">
                        <tr>
                            <td colspan="6" class="muted" style="text-align: center; padding: 20px;">السلة فارغة. قم باختيار صنف وإضافته بالأعلى.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Right Column: Invoice Options & Actions -->
    <div class="pos-side-panel">
        <div class="panel" style="position: sticky; top: 66px; border-top: 4px solid var(--primary);">
            <h3 style="border-bottom: 1px solid var(--line); padding-bottom: 6px; margin-bottom: 12px; font-size: 15px;">
                إغلاق الحساب والدفع</h3>

            <div style="display: grid; gap: 10px;">
                <label>موقع البيع والقناة
                    <?php if ($userLocationId !== null): ?>
                        <select name="location_id" disabled>
                            <?php foreach ($locations as $l): ?>
                                <option value="<?= e($l['id']) ?>" selected><?= e($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="location_id" value="<?= e($userLocationId) ?>">
                    <?php else: ?>
                        <select name="location_id" required>
                            <?php foreach ($locations as $l): ?>
                                <option value="<?= e($l['id']) ?>"><?= e($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </label>

                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <label style="margin: 0;">العميل</label>
                        <button type="button" class="btn small primary" id="open-new-customer-modal-btn"
                            onclick="openCustomerModal()" style="padding: 2px 8px; font-size: 11px; border-radius: 6px;">+ عميل جديد</button>
                    </div>
                    <select name="customer_id" id="customer_id_select" onchange="onCustomerSelectChange(this.value)">
                        <option value="">زبون عابر</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?> - <?= e($c['phone'] ?: 'بدون هاتف') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Dedicated Return & Exchange Section inside Checkout Side-Panel -->
                <div class="pos-exchange-side-widget" style="background: linear-gradient(135deg, rgba(201, 168, 76, 0.14), rgba(245, 158, 11, 0.08)); border: 1.5px solid var(--gold); border-radius: 12px; padding: 10px 12px; margin-top: 2px; margin-bottom: 2px;">
                    <button type="button" class="btn-customer-invoices" onclick="openCustomerInvoicesModal()" style="width: 100%; margin: 0; background: linear-gradient(135deg, var(--gold-dark), var(--gold), var(--gold-light)); color: #000; font-weight: 800; font-size: 13px; border-radius: 9px; padding: 9px 12px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 8px; box-shadow: 0 3px 12px rgba(201, 168, 76, 0.35);">
                        <span style="display: flex; align-items: center; gap: 6px;">
                            <span style="font-size: 17px;">🔄</span>
                            <span>استرجاع أو استبدال فواتير</span>
                        </span>
                        <span class="badge" id="customer-invoices-count-badge" style="background: #000; color: #fff; font-size: 11px; padding: 2px 7px; border-radius: 12px; font-weight: 800;">فواتير</span>
                    </button>

                    <!-- Active Exchange Badge in Side Panel -->
                    <div id="side-exchange-active-box" style="display: none; margin-top: 10px; padding-top: 8px; border-top: 1px dashed rgba(201,168,76,0.4); font-size: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; color: #16a34a; font-weight: 800;">
                            <span>رصيد الاستبدال المفعل:</span>
                            <strong id="side-exchange-credit-val" style="font-size: 14px;">0.00 ج.م</strong>
                        </div>
                        <div style="font-size: 11px; color: var(--muted); margin-top: 3px;" id="side-exchange-invoice-info"></div>
                        <button type="button" onclick="cancelExchange()" style="margin-top: 6px; width: 100%; background: rgba(220,38,38,0.12); border: 1px solid rgba(220,38,38,0.35); color: #dc2626; border-radius: 6px; padding: 4px; font-size: 11px; font-weight: 700; cursor: pointer;">✖ إلغاء وضع الاستبدال</button>
                    </div>
                </div>

                <label>طريقة الدفع
                    <select name="payment_method" id="payment_method_select" onchange="togglePaymentFields()">
                        <option value="cash">كاش (نقداً)</option>
                        <option value="instapay">إنستا باي (InstaPay)</option>
                        <option value="vodafone_cash">فودافون كاش</option>
                        <option value="salary_deduction">خصم من الراتب (للموظفين)</option>
                        <option value="mixed_cash_instapay">مختلط (كاش + إنستا باي)</option>
                        <option value="mixed_cash_vodafone">مختلط (كاش + فودافون كاش)</option>
                    </select>
                </label>
                <small class="muted" id="payment_destination_note"></small>
                
                <div id="salary-deduction-employee-container" style="display: none;">
                    <label>اختر الموظف المخصوم منه
                        <select name="deduction_user_id" id="deduction_user_id_select">
                            <option value="">-- اختر الموظف --</option>
                            <?php foreach (pdo()->query('SELECT u.id, u.name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 AND r.code != \'admin\' ORDER BY u.name')->fetchAll() as $emp): ?>
                                <option value="<?= e($emp['id']) ?>"><?= e($emp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <!-- Split Payment Fields Container -->
                <div id="split-payment-container">
                    <label style="position: relative;">
                        <span>المبلغ كاش (ج.م)</span>
                        <span onclick="fillRemainder('cash')" style="position: absolute; left: 0; top: 0; color: var(--primary); cursor: pointer; font-size: 10px; font-weight: 700; text-decoration: underline;">⚡ باقي الحساب</span>
                        <input name="paid_cash" id="paid_cash_input" type="number" step="1" min="0" placeholder="0" oninput="recalculateTotals()">
                    </label>
                    <label style="position: relative;">
                        <span id="secondary_payment_label">المبلغ إنستا باي (ج.م)</span>
                        <span onclick="fillRemainder('secondary')" style="position: absolute; left: 0; top: 0; color: var(--primary); cursor: pointer; font-size: 10px; font-weight: 700; text-decoration: underline;">⚡ باقي الحساب</span>
                        <input name="paid_secondary" id="paid_secondary_input" type="number" step="1" min="0" placeholder="0" oninput="recalculateTotals()">
                    </label>
                </div>

                <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 8px;">
                    <label>خصم إضافي للفاتورة
                        <select name="discount_type" id="invoice_discount_type" onchange="recalculateTotals()">
                            <option value="">بدون خصم</option>
                            <option value="amount">مبلغ ثابت</option>
                            <option value="percent">نسبة مئوية %</option>
                        </select>
                    </label>
                    <label>قيمة الخصم
                        <input name="discount_value" id="invoice_discount_value" type="number" step="1" min="0" placeholder="0"
                            oninput="recalculateTotals()">
                    </label>
                </div>

                <label>ملاحظة الفاتورة
                    <textarea name="notes" placeholder="ملاحظات إضافية للفاتورة..."
                        style="min-height: 50px; border-radius: 8px;"></textarea>
                </label>

                <label>اسم مقفل الأوردر (اختياري)
                    <input name="packer_name" id="packer_name_input" type="text" placeholder="اسم الشخص الذي جهز الفاتورة...">
                </label>

                <!-- Totals Section -->
                <div class="receipt-box">
                    <div class="receipt-row">
                        <span>إجمالي الأصناف:</span>
                        <strong id="lbl-subtotal">0.00 ج.م</strong>
                    </div>
                    <div class="receipt-row" style="color: var(--danger);">
                        <span>خصم الفاتورة:</span>
                        <strong id="lbl-discount">0.00 ج.م</strong>
                    </div>
                    <div class="receipt-row" id="row-exchange-credit" style="color: #16a34a; display: none;">
                        <span>رصيد الاستبدال (المرتجع):</span>
                        <strong id="lbl-exchange-credit">- 0.00 ج.م</strong>
                    </div>
                    <div class="receipt-row" id="row-exchange-diff" style="color: var(--gold-dark); display: none; font-weight: 800; border-top: 1px dashed var(--line); padding-top: 6px;">
                        <span id="lbl-exchange-diff-title">فرق الاستبدال المطلوب:</span>
                        <strong id="lbl-exchange-diff-val" style="font-size: 15px;">0.00 ج.م</strong>
                    </div>
                    <div class="receipt-row total">
                        <span id="lbl-total-title">المطلوب دفعه:</span>
                        <strong id="lbl-total" style="color: var(--primary-dark); font-size: 17px;">0.00 ج.م</strong>
                    </div>

                    <label style="margin-top: 8px; display: flex; flex-direction: column; gap: 6px; font-weight: 700;">المبلغ المدفوع
                        <input name="paid_total" id="paid_total_input" type="number" step="1" min="0" required
                            placeholder="0.00" oninput="recalculateTotals()" style="padding: 10px 14px; font-size: 15px; border-radius: 8px; border: 1.5px solid var(--line); font-weight: bold; text-align: center;">
                    </label>

                    <div class="receipt-row" style="border-top: 1px dashed var(--line); padding-top: 8px; margin-top: 4px;">
                        <span>متبقي (دين على العميل):</span>
                        <strong id="lbl-due" style="color: var(--danger);">0.00 ج.م</strong>
                    </div>
                    <div class="receipt-row">
                        <span>الفكة (المتبقي للعميل):</span>
                        <strong id="lbl-change" style="color: var(--success);">0.00 ج.م</strong>
                    </div>
                </div>

                <button class="btn-checkout" style="margin-top: 10px; width: 100%;">
                    <span>💵 إغلاق الفاتورة وطباعة</span>
                </button>
            </div>
        </div>
    </div>
</form>

<!-- Modal: Customer Invoices (Returns & Exchanges) -->
<div id="customer-invoices-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(4px); z-index: 9999; justify-content: center; align-items: center;">
    <div class="modal-content" style="background: var(--surface); border: 2px solid var(--gold); border-radius: 18px; width: 95%; max-width: 850px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="padding: 16px 22px; background: linear-gradient(135deg, rgba(201, 168, 76, 0.2), rgba(15, 23, 42, 0.95)); border-bottom: 1.5px solid rgba(201, 168, 76, 0.3); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 22px;">📋</span>
                <div>
                    <h3 style="margin: 0; font-size: 16px; color: var(--gold); font-weight: 800;">فواتير ومشتريات العميل (استرجاع / استبدال)</h3>
                    <span id="cust-invoices-modal-subtitle" style="font-size: 12px; color: #cbd5e1;"></span>
                </div>
            </div>
            <button type="button" onclick="closeCustomerInvoicesModal()" style="background: rgba(255,255,255,0.1); border: none; color: #fff; font-size: 22px; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>
        
        <!-- Search bar inside modal -->
        <div style="padding: 14px 20px 0 20px; background: var(--surface);">
            <div style="display: flex; gap: 8px;">
                <input type="text" id="cust-invoices-search-input" placeholder="🔍 ابحث برقم الفاتورة أو اسم العميل أو رقم الهاتف..." style="flex: 1; padding: 9px 14px; border: 1.5px solid rgba(201,168,76,0.4); border-radius: 10px; font-size: 13px; background: var(--surface-soft);" onkeydown="if(event.key==='Enter'){event.preventDefault();searchInvoicesFromModal();}">
                <button type="button" class="btn small primary" onclick="searchInvoicesFromModal()" style="padding: 8px 16px; border-radius: 10px; font-weight: 700; background: var(--gold); color: #000;">بحث</button>
                <button type="button" class="btn small secondary" onclick="resetInvoiceSearchModal()" style="padding: 8px 12px; border-radius: 10px;">إعادة تعيين</button>
            </div>
        </div>

        <div id="cust-invoices-modal-body" style="padding: 16px 20px 20px 20px; overflow-y: auto; flex: 1; min-height: 250px;">
            <div style="text-align: center; padding: 40px; color: var(--muted);">جاري تحميل فواتير العميل...</div>
        </div>

        <div id="cust-invoices-modal-footer" style="padding: 14px 22px; background: var(--surface-soft); border-top: 1.5px solid rgba(201, 168, 76, 0.2); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div>
                    <span style="font-size: 11.5px; color: var(--muted); display: block;">الأصناف المحددة</span>
                    <strong id="selected-return-items-count" style="font-size: 14px; color: var(--ink);">0 أصناف</strong>
                </div>
                <div>
                    <span style="font-size: 11.5px; color: var(--muted); display: block;">إجمالي قيمة المرتجع / المستبدل</span>
                    <strong id="selected-return-items-total" style="font-size: 17px; color: #16a34a; font-weight: 900;">0.00 ج.م</strong>
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn danger" id="btn-direct-return" onclick="promptDirectReturn()" style="padding: 10px 18px; border-radius: 10px; font-weight: 800; font-size: 13px;" disabled>
                    🔴 استرجاع مباشر
                </button>
                <button type="button" class="btn primary" id="btn-apply-exchange" onclick="applyExchangeToCart()" style="padding: 10px 20px; border-radius: 10px; font-weight: 800; font-size: 13px; background: linear-gradient(135deg, var(--gold-dark), var(--gold)); color: #000;" disabled>
                    🔄 استبدال في الكاشير
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Direct Return Confirmation -->
<div id="direct-return-confirm-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); z-index: 10000; justify-content: center; align-items: center;">
    <div class="modal-content" style="background: var(--surface); border: 2px solid #ef4444; border-radius: 16px; width: 92%; max-width: 460px; padding: 22px; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; border-bottom: 1px solid rgba(239,68,68,0.3); padding-bottom: 8px;">
            <h3 style="margin: 0; color: #ef4444; font-size: 16px; font-weight: 800;">تأكيد الاسترجاع المباشر</h3>
            <span onclick="closeDirectReturnModal()" style="font-size: 24px; cursor: pointer; color: var(--muted);">&times;</span>
        </div>
        <div style="display: grid; gap: 12px;">
            <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 10px; padding: 12px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                    <span>إجمالي قيمة المرتجع:</span>
                    <strong id="direct-return-total-amount" style="color: #ef4444; font-size: 15px;">0.00 ج.م</strong>
                </div>
                <div style="font-size: 11.5px; color: var(--muted);" id="direct-return-summary-text"></div>
            </div>

            <label style="display: flex; flex-direction: column; gap: 5px; font-size: 12.5px; font-weight: 700; color: var(--ink);">
                طريقة رد المبلغ للعميل
                <select id="direct-return-method-select" style="padding: 10px 12px; border: 1.5px solid var(--line); border-radius: 8px; font-size: 13px;">
                    <option value="cash">كاش (نقداً من الخزينة)</option>
                    <option value="instapay">إنستا باي (InstaPay)</option>
                    <option value="vodafone_cash">فودافون كاش</option>
                    <option value="customer_credit">رصيد للعميل</option>
                </select>
            </label>

            <label style="display: flex; flex-direction: column; gap: 5px; font-size: 12.5px; font-weight: 700; color: var(--ink);">
                المبلغ المسترجع فعلياً (ج.م)
                <input type="number" step="any" min="0" id="direct-return-paid-input" style="padding: 10px 12px; border: 1.5px solid var(--line); border-radius: 8px; font-size: 13px;">
            </label>

            <label style="display: flex; flex-direction: column; gap: 5px; font-size: 12.5px; font-weight: 700; color: var(--ink);">
                سبب الإرجاع
                <input type="text" id="direct-return-reason-input" value="مرتجع مباشر من الكاشير" placeholder="سبب الإرجاع..." style="padding: 10px 12px; border: 1.5px solid var(--line); border-radius: 8px; font-size: 13px;">
            </label>

            <div style="display: flex; gap: 10px; margin-top: 8px;">
                <button type="button" class="btn danger" id="btn-confirm-execute-return" onclick="executeDirectReturn()" style="flex: 1; padding: 12px; font-weight: 800; font-size: 14px; border-radius: 8px;">
                    تأكيد الإرجاع واستعادة المخزون
                </button>
                <button type="button" class="btn secondary" onclick="closeDirectReturnModal()" style="padding: 12px 18px; border-radius: 8px;">
                    إلغاء
                </button>
            </div>
            <div id="direct-return-error" style="color: var(--danger); font-size: 12px; font-weight: 700; display: none;"></div>
        </div>
    </div>
</div>

<!-- Modal: Quick Add Customer -->
<div id="customer-modal" class="modal">
    <div class="modal-content" style="border-radius: 16px; padding: 24px; max-width: 450px;">
        <span class="close-modal" onclick="closeCustomerModal()">&times;</span>
        <h3 style="margin-top: 0; margin-bottom: 18px; color: var(--primary); font-weight: 700;">إضافة عميل جديد سريع</h3>
        <div style="display: grid; gap: 14px;">
            <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; font-size: 12px; color: var(--muted);">اسم العميل الكامل<input id="new-cust-name" required placeholder="مثال: أحمد محمد علي" style="padding: 10px 14px; border: 1px solid var(--line); border-radius: 8px; font-size: 13px;" onkeydown="if(event.key==='Enter'){event.preventDefault();saveQuickCustomer();}"></label>
            <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; font-size: 12px; color: var(--muted);">رقم هاتف العميل<input id="new-cust-phone" type="tel" placeholder="مثال: 01012345678" style="padding: 10px 14px; border: 1px solid var(--line); border-radius: 8px; font-size: 13px;" onkeydown="if(event.key==='Enter'){event.preventDefault();saveQuickCustomer();}"></label>
            <label style="display: flex; flex-direction: column; gap: 6px; font-weight: 600; font-size: 12px; color: var(--muted);">تاريخ الميلاد (يوم/شهر/سنة كاملة)
                <input id="new-cust-birthdate" type="text" placeholder="مثال: 15/06/1995" style="padding: 10px 14px; border: 1px solid var(--line); border-radius: 8px; font-size: 13px; text-align: left;" dir="ltr" onkeydown="if(event.key==='Enter'){event.preventDefault();saveQuickCustomer();}">
            </label>
            <button type="button" class="btn primary" id="save-customer-btn" onclick="saveQuickCustomer()" style="border-radius: 8px; padding: 12px; font-weight: 700; font-size: 14px;">حفظ العميل وتحديده</button>
            <div id="modal-error" style="color: var(--danger); font-weight: 700; font-size: 12.5px; display: none;"></div>
        </div>
    </div>
</div>

<!-- Modal: Offer Customizer (استبدال الزجاجة وتجهيز التركيبة) -->
<div id="offer-customizer-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(5px); z-index: 99999; justify-content: center; align-items: center;">
    <div class="modal-content" style="background: var(--surface); border: 2px solid var(--gold); border-radius: 18px; width: 95%; max-width: 820px; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.6);">
        <div style="padding: 16px 22px; background: linear-gradient(135deg, rgba(201, 168, 76, 0.25), rgba(15, 23, 42, 0.95)); border-bottom: 1.5px solid rgba(201, 168, 76, 0.3); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🎁</span>
                <div>
                    <h3 style="margin: 0; font-size: 16px; color: var(--gold); font-weight: 800;" id="offer-customizer-title">تخصيص وتجهيز محتويات العرض</h3>
                    <span id="offer-customizer-subtitle" style="font-size: 12px; color: #cbd5e1;">استبدال الزجاجات وتجهيز التركيبات العطرية الخاصة بالعرض</span>
                </div>
            </div>
            <button type="button" onclick="closeOfferCustomizerModal()" style="background: rgba(255,255,255,0.1); border: none; color: #fff; font-size: 22px; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>

        <div id="offer-customizer-body" style="padding: 18px 22px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 16px; min-height: 280px; max-height: 65vh;">
            <!-- Rendered dynamically -->
        </div>

        <div id="offer-customizer-footer" style="padding: 14px 22px; background: var(--surface-soft); border-top: 1.5px solid rgba(201, 168, 76, 0.2); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div>
                    <span style="font-size: 11.5px; color: var(--muted); display: block;">سعر العرض المحدد</span>
                    <strong id="offer-customizer-price-display" style="font-size: 18px; color: var(--gold-dark); font-weight: 900;">0.00 ج.م</strong>
                </div>
                <div id="offer-customizer-saving-badge" style="background: rgba(16,185,129,0.15); color: #10b981; padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 800; border: 1px solid rgba(16,185,129,0.3);">
                </div>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn secondary" onclick="closeOfferCustomizerModal()" style="padding: 10px 18px; border-radius: 10px; font-weight: 700;">إلغاء</button>
                <button type="button" class="btn primary" id="btn-save-offer-customization" onclick="saveOfferCustomizationToCart()" style="padding: 10px 24px; border-radius: 10px; font-weight: 800; font-size: 13.5px; background: linear-gradient(135deg, var(--gold), var(--gold-light), var(--gold)); color: #1a1210; box-shadow: 0 4px 12px rgba(201,168,76,0.35);">
                    ➕ تأكيد وإضافة للسلة
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Bundle Cart Items into Offer (تطبيق عرض على تركيبات السلة) -->
<div id="bundle-offer-modal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.75); backdrop-filter: blur(5px); z-index: 99999; justify-content: center; align-items: center;">
    <div class="modal-content" style="background: var(--surface); border: 2px solid var(--gold); border-radius: 18px; width: 95%; max-width: 720px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.6);">
        <div style="padding: 16px 22px; background: linear-gradient(135deg, rgba(201, 168, 76, 0.25), rgba(15, 23, 42, 0.95)); border-bottom: 1.5px solid rgba(201, 168, 76, 0.3); display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🎁</span>
                <div>
                    <h3 style="margin: 0; font-size: 16px; color: var(--gold); font-weight: 800;">تطبيق عرض ترويجي على تركيبات السلة</h3>
                    <span style="font-size: 12px; color: #cbd5e1;">اختر العرض وحدد التركيبات المجهزة في السلة لضمها في باكدج واحد بسعر العرض</span>
                </div>
            </div>
            <button type="button" onclick="closeBundleOfferModal()" style="background: rgba(255,255,255,0.1); border: none; color: #fff; font-size: 22px; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;">&times;</button>
        </div>

        <div style="padding: 18px 22px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 14px;">
            <!-- Step 1: Select Offer -->
            <label style="font-size: 13px; font-weight: 800; color: var(--ink);">
                1️⃣ اختر العرض / الباكدج الترويجي المطلوب تطبيقه:
                <select id="bundle-target-offer-select" onchange="onBundleTargetOfferChange()" style="margin-top: 6px; padding: 10px 14px; border: 2px solid var(--gold); border-radius: 10px; font-size: 14px; font-weight: 700; width: 100%; background: var(--surface); color: var(--ink);">
                    <option value="">-- اختر عرضاً لتطبيقه على التركيبات --</option>
                    <?php foreach ($offers as $o): ?>
                        <option value="<?= e($o['id']) ?>" data-price="<?= e($o['price_after']) ?>" data-before="<?= e($o['price_before']) ?>">
                            🎁 <?= e($o['name']) ?> — (<?= money($o['price_after']) ?> بدلاً من <?= money($o['price_before']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <!-- Step 2: Checkboxes of Cart Mixes & Products -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <span style="font-size: 13px; font-weight: 800; color: var(--ink);">2️⃣ حدد التركيبات والأصناف المجهزة في السلة لضمها داخل العرض:</span>
                    <span id="bundle-selection-count-badge" class="badge" style="background: var(--gold-soft); color: var(--gold-dark); font-weight: 800;">0 محددة</span>
                </div>
                <div id="bundle-cart-items-list" style="display: flex; flex-direction: column; gap: 8px; max-height: 280px; overflow-y: auto; padding: 4px;">
                    <!-- Rendered dynamically -->
                </div>
            </div>

            <div id="bundle-offer-preview-box" style="display: none; background: rgba(16,185,129,0.08); border: 1.5px solid rgba(16,185,129,0.3); border-radius: 10px; padding: 12px; font-size: 13px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong style="color: #059669; font-size: 14px;" id="bundle-preview-title"></strong>
                        <div style="font-size: 12px; color: var(--muted);" id="bundle-preview-desc"></div>
                    </div>
                    <div style="text-align: left;">
                        <span style="font-size: 11px; color: var(--muted); display: block;">سعر الباكدج المخفض</span>
                        <strong style="font-size: 17px; color: #059669;" id="bundle-preview-price"></strong>
                    </div>
                </div>
            </div>
        </div>

        <div style="padding: 14px 22px; background: var(--surface-soft); border-top: 1.5px solid rgba(201, 168, 76, 0.2); display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="btn secondary" onclick="closeBundleOfferModal()" style="padding: 10px 18px; border-radius: 10px; font-weight: 700;">إلغاء</button>
            <button type="button" class="btn primary" id="btn-confirm-bundle-offer" onclick="applyBundleOfferToCart()" style="padding: 10px 24px; border-radius: 10px; font-weight: 800; font-size: 14px; background: linear-gradient(135deg, var(--gold), var(--gold-light), var(--gold)); color: #1a1210; box-shadow: 0 4px 12px rgba(201,168,76,0.35);">
                ✨ تأكيد وتطبيق العرض على التركيبات
            </button>
        </div>
    </div>
</div>

<script>
    // ===== POS Tabs Logic =====
    let posTabs = [];
    let activeTabId = 1;
    let tabCounter = 1;
    let cart = []; // references the active tab's cart

    function getActiveTab() {
        return posTabs.find(t => t.id === activeTabId) || posTabs[0];
    }

    function initTabs() {
        const storedTabs = localStorage.getItem('pos_tabs');
        if (storedTabs) {
            try {
                posTabs = JSON.parse(storedTabs);
                if (posTabs.length === 0) throw new Error("Empty tabs");
                activeTabId = parseInt(localStorage.getItem('pos_active_tab')) || posTabs[0].id;
                tabCounter = Math.max(...posTabs.map(t => t.id)) + 1;
            } catch(e) {
                resetTabs();
            }
        } else {
            const legacyCart = localStorage.getItem('pos_cart');
            let initialCart = [];
            if (legacyCart) {
                try { initialCart = JSON.parse(legacyCart); } catch(e){}
                localStorage.removeItem('pos_cart');
            }
            posTabs = [{ id: 1, name: 'فاتورة 1', cart: initialCart, customer_id: '', discountType: '', discountValue: 0, paymentMethod: 'cash', paymentDestination: 'safe' }];
            activeTabId = 1;
            tabCounter = 2;
        }
        
        // Ensure active tab exists
        if (!posTabs.find(t => t.id === activeTabId)) {
            activeTabId = posTabs[0].id;
        }
        
        // Ensure minimum 2 tabs
        while (posTabs.length < 2) {
            const newId = tabCounter++;
            posTabs.push({ id: newId, name: 'فاتورة ' + newId, cart: [], customer_id: '', discountType: '', discountValue: 0, paymentMethod: 'cash', paymentDestination: 'safe' });
        }
        
        cart = getActiveTab().cart;
        renderTabsUI();
        restoreTabState();
        renderCart();
    }

    function resetTabs() {
        posTabs = [
            { id: 1, name: 'فاتورة 1', cart: [], customer_id: '', discountType: '', discountValue: 0, paymentMethod: 'cash', paymentDestination: 'safe' },
            { id: 2, name: 'فاتورة 2', cart: [], customer_id: '', discountType: '', discountValue: 0, paymentMethod: 'cash', paymentDestination: 'safe' }
        ];
        activeTabId = 1;
        tabCounter = 3;
        localStorage.setItem('pos_tabs', JSON.stringify(posTabs));
        localStorage.setItem('pos_active_tab', activeTabId);
    }

    function saveCurrentTabState() {
        const tab = getActiveTab();
        if (!tab) return;
        const custSelect = document.getElementById('customer_id_select');
        tab.customer_id = custSelect ? custSelect.value : '';
        tab.discountType = document.querySelector('select[name="discount_type"]')?.value || '';
        tab.discountValue = parseInt(document.querySelector('input[name="discount_value"]')?.value) || 0;
        tab.paymentMethod = document.querySelector('select[name="payment_method"]')?.value || 'cash';
        tab.paymentDestination = document.querySelector('select[name="payment_destination"]')?.value || 'safe';
        
        localStorage.setItem('pos_tabs', JSON.stringify(posTabs));
        localStorage.setItem('pos_active_tab', activeTabId);
    }

    function restoreTabState() {
        const tab = getActiveTab();
        if (!tab) return;
        
        const custSelect = document.getElementById('customer_id_select');
        if (custSelect) {
            custSelect.value = tab.customer_id;
            // Update custom select trigger text and list
            const trigger = custSelect.closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
            if (trigger) {
                const opt = custSelect.options[custSelect.selectedIndex];
                trigger.textContent = opt ? opt.textContent : '-- اختر عميل --';
            }
            custSelect.dispatchEvent(new Event('change', { bubbles: true }));
            if (tab.customer_id) {
                if (typeof loadCustomerInvoices === 'function') loadCustomerInvoices(tab.customer_id);
            } else {
                const qb = document.getElementById('customer-invoices-quickbar');
                if (qb) qb.style.display = 'none';
            }
        }
        
        const discType = document.querySelector('select[name="discount_type"]');
        if (discType) discType.value = tab.discountType;
        
        const discVal = document.querySelector('input[name="discount_value"]');
        if (discVal) discVal.value = tab.discountValue || '';
        
        const pmSelect = document.querySelector('select[name="payment_method"]');
        if (pmSelect) pmSelect.value = tab.paymentMethod;
        
        const pdSelect = document.querySelector('select[name="payment_destination"]');
        if (pdSelect) pdSelect.value = tab.paymentDestination;
        
        if (typeof updateExchangeBannerUI === 'function') updateExchangeBannerUI();
        if (typeof togglePaymentMethodOptions === 'function') togglePaymentMethodOptions();
    }

    function renderTabsUI() {
        const container = document.getElementById('pos-tabs-container');
        if (!container) return;
        
        container.innerHTML = '';
        posTabs.forEach(tab => {
            const btn = document.createElement('div');
            btn.className = 'pos-tab' + (tab.id === activeTabId ? ' active' : '');
            btn.innerHTML = `<span>${tab.name}</span>`;
            btn.onclick = () => switchTab(tab.id);
            
            // Allow deleting if more than 1 tab
            if (posTabs.length > 1) {
                const closeBtn = document.createElement('span');
                closeBtn.className = 'pos-tab-close';
                closeBtn.innerHTML = '✖';
                closeBtn.onclick = (e) => {
                    e.stopPropagation();
                    closeTab(tab.id);
                };
                btn.appendChild(closeBtn);
            }
            container.appendChild(btn);
        });
        
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'pos-tab-add';
        addBtn.innerHTML = '+ فاتورة جديدة';
        addBtn.onclick = addNewTab;
        container.appendChild(addBtn);
    }

    function switchTab(tabId) {
        if (tabId === activeTabId) return;
        saveCurrentTabState(); // save current before switching
        activeTabId = tabId;
        cart = getActiveTab().cart; // Switch the pointer
        renderTabsUI();
        restoreTabState();
        renderCart();
    }

    function addNewTab() {
        saveCurrentTabState();
        const newId = tabCounter++;
        posTabs.push({
            id: newId,
            name: 'فاتورة ' + newId,
            cart: [],
            customer_id: '',
            discountType: '',
            discountValue: 0,
            paymentMethod: 'cash',
            paymentDestination: 'safe'
        });
        activeTabId = newId;
        cart = getActiveTab().cart;
        renderTabsUI();
        restoreTabState();
        renderCart();
    }

    function closeTab(tabId, force = false) {
        if (posTabs.length <= 1) {
            if (force) {
                // Clear this tab's cart and add a fresh tab to maintain minimum 2
                cart.length = 0;
                getActiveTab().cart = [];
                saveCurrentTabState();
                renderCart();
                addTab();
            }
            return;
        }
        if (!force && !confirm('هل أنت متأكد من إغلاق هذه الفاتورة ومسح سلتها؟')) return;
        
        const idx = posTabs.findIndex(t => t.id === tabId);
        if (idx === -1) return;
        
        posTabs.splice(idx, 1);
        if (activeTabId === tabId) {
            activeTabId = posTabs[0].id;
        }
        cart = getActiveTab().cart;
        renderTabsUI();
        restoreTabState();
        renderCart();

        // After forced close ensure minimum 2 tabs
        if (force && posTabs.length < 2) {
            addTab();
        }
    }

    // Initialize tabs on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        openPrintableInvoice();

        // Bind form inputs to save tab state
        const formInputs = [
            document.getElementById('customer_id_select'),
            document.querySelector('select[name="discount_type"]'),
            document.querySelector('input[name="discount_value"]'),
        ];
        
        formInputs.forEach(el => {
            if (el) {
                el.addEventListener('change', saveCurrentTabState);
                el.addEventListener('input', saveCurrentTabState);
            }
        });
        
        document.querySelectorAll('input[name="payment_method"], input[name="payment_destination"]').forEach(el => {
            el.addEventListener('change', saveCurrentTabState);
        });

        // تاريخ الميلاد — إدخال يدوي كامل بدون auto-slash
    });


    // Prevent negative values and keep decimal grams available for split defaults
    document.addEventListener('input', function(e) {
        if (e.target.tagName === 'INPUT' && e.target.type === 'number') {
            const allowDecimal = e.target.name === 'mix_grams[]';
            // Remove any minus sign or decimal point typed
            if (e.target.value.startsWith('-')) {
                e.target.value = e.target.value.replace('-', '');
            }
            if (allowDecimal && e.target.value.includes(',')) {
                e.target.value = e.target.value.replace(',', '.');
            }
            // Remove decimal points
            if (!allowDecimal && (e.target.value.includes('.') || e.target.value.includes(','))) {
                e.target.value = e.target.value.replace(/[.,]/g, '');
            }
        }
    });
    
    // Also prevent keypress of minus sign
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' && e.target.type === 'number') {
            if (e.key === '-' || e.key === 'e' || e.key === 'E') {
                e.preventDefault();
            }
        }
    });

    // Tab switching logic for adding products
    function switchAddTab(tab) {
        const tabDirectBtn = document.getElementById('tab-direct-btn');
        const tabRecipeBtn = document.getElementById('tab-recipe-btn');
        const tabMixBtn = document.getElementById('tab-mix-btn');
        const tabOfferBtn = document.getElementById('tab-offer-btn');
        const addDirectPanel = document.getElementById('add-direct-panel');
        const addRecipePanel = document.getElementById('add-recipe-panel');
        const addMixPanel = document.getElementById('add-mix-panel');
        const addOfferPanel = document.getElementById('add-offer-panel');

        // Reset all
        tabDirectBtn.classList.remove('active');
        tabRecipeBtn.classList.remove('active');
        tabMixBtn.classList.remove('active');
        if (tabOfferBtn) tabOfferBtn.classList.remove('active');
        addDirectPanel.style.display = 'none';
        addRecipePanel.style.display = 'none';
        addMixPanel.style.display = 'none';
        if (addOfferPanel) addOfferPanel.style.display = 'none';

        // Activate selected
        if (tab === 'direct') {
            tabDirectBtn.classList.add('active');
            addDirectPanel.style.display = 'grid';
        } else if (tab === 'recipe') {
            tabRecipeBtn.classList.add('active');
            addRecipePanel.style.display = 'grid';
        } else if (tab === 'mix') {
            tabMixBtn.classList.add('active');
            addMixPanel.style.display = 'grid';
        } else if (tab === 'offer') {
            if (tabOfferBtn) tabOfferBtn.classList.add('active');
            if (addOfferPanel) addOfferPanel.style.display = 'grid';
        }
    }

    // Auto-fill price when product/recipe is changed
    document.getElementById('add-prod-select').addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        const price = option.getAttribute('data-price') || 0;
        document.getElementById('add-prod-price').value = price;
        // Show stock indicator
        const stockBar = document.getElementById('stock-warning-bar');
        const stock = option.getAttribute('data-stock');
        const isInit = option.getAttribute('data-stock-init') || '0';
        stockBar.className = '';
        if (!this.value || stock === null || stock === '') {
            stockBar.textContent = '';
            stockBar.style.display = 'none';
        } else {
            const qty = parseFloat(stock);
            if (isInit === '0') {
                stockBar.className = 'low';
                stockBar.textContent = 'ℹ️ معلومة: لم يتم تسجيل كمية هذا المنتج في الفرع — يمكنك إضافته';
            } else if (qty <= 0) {
                stockBar.className = 'out';
                stockBar.textContent = '⚠️ تحذير: هذا المنتج نفد من مخزون الفرع الحالي!';
            } else if (qty < 3) {
                stockBar.className = 'low';
                stockBar.textContent = '⚡ تنبيه: المخزون منخفض — المتاح: ' + qty + ' فقط';
            } else {
                stockBar.className = 'ok';
                stockBar.textContent = '✅ المخزون متاح: ' + qty + ' وحدة في الفرع';
            }
        }
    });

    document.getElementById('add-recipe-select').addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        const price = option.getAttribute('data-price') || 0;
        document.getElementById('add-recipe-price').value = price;
    });

    // Change handler for saved recipe selection
    function onSavedRecipeSelectChange() {
        const select = document.getElementById('add-recipe-select');
        if (!select || !select.value) return;
        const option = select.options[select.selectedIndex];
        const price = option.getAttribute('data-price') || 0;
        const priceInput = document.getElementById('add-recipe-price');
        if (priceInput) priceInput.value = price;

        const bottleSelect = document.getElementById('saved_recipe_bottle_id');
        if (bottleSelect) {
            bottleSelect.value = 'default';
            if (typeof refreshSearchableSelect === 'function') refreshSearchableSelect(bottleSelect);
        }
    }

    // Add direct product to cart array
    function addProductToCart() {
        const select = document.getElementById('add-prod-select');
        const id = select.value;
        if (!id) return alert('الرجاء اختيار صنف أولاً.');

        const option = select.options[select.selectedIndex];
        const name = option.getAttribute('data-name');
        const qty = parseFloat(document.getElementById('add-prod-qty').value) || 0;
        const price = parseFloat(document.getElementById('add-prod-price').value) || 0;
        const discountType = document.getElementById('add-prod-discount-type').value;
        const discountValue = parseFloat(document.getElementById('add-prod-discount-value').value) || 0;

        if (qty <= 0) return alert('الرجاء إدخال كمية صحيحة.');

        // Warn only if truly out of stock (not just uninitialized)
        const stockAttr = option.getAttribute('data-stock');
        const isInitAttr = option.getAttribute('data-stock-init') || '0';
        if (isInitAttr === '1' && stockAttr !== null && stockAttr !== '') {
            const availableStock = parseFloat(stockAttr);
            if (availableStock <= 0) {
                if (!confirm('⚠️ تحذير: هذا المنتج نفد من مخزون الفرع الحالي!\nهل تريد إضافته للسلة على مسؤوليتك؟')) {
                    return;
                }
            }
        }

        // Check if item exists in cart
        const existingIndex = cart.findIndex(item => item.id === id && item.type === 'product' && item.discountType === discountType && item.discountValue === discountValue);

        if (existingIndex > -1) {
            cart[existingIndex].qty += qty;
        } else {
            cart.push({
                type: 'product',
                id: id,
                name: name,
                qty: qty,
                price: price,
                discountType: discountType,
                discountValue: discountValue
            });
        }

        // Reset inputs
        select.value = '';
        const trigger = select.closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
        if (trigger) trigger.textContent = '-- اختر صنف مباشر --';

        document.getElementById('add-prod-qty').value = '1';
        document.getElementById('add-prod-price').value = '';
        document.getElementById('add-prod-discount-type').value = '';
        document.getElementById('add-prod-discount-value').value = '';

        renderCart();
    }

    // Add recipe to cart array
    function addRecipeToCart() {
        const select = document.getElementById('add-recipe-select');
        const id = select.value;
        if (!id) return alert('الرجاء اختيار تركيبة جاهزة أولاً.');

        const option = select.options[select.selectedIndex];
        const name = option.getAttribute('data-name');
        const qty = parseFloat(document.getElementById('add-recipe-qty').value) || 0;
        const price = parseFloat(document.getElementById('add-recipe-price').value) || 0;

        if (qty <= 0) return alert('الرجاء إدخال كمية صحيحة.');

        const bottleSelect = document.getElementById('saved_recipe_bottle_id');
        const bottleVal = bottleSelect ? bottleSelect.value : 'default';
        const isWithoutBottle = (bottleVal === 'no_bottle');
        let bottleDesc = '';
        if (isWithoutBottle) {
            bottleDesc = ' (بدون زجاجة)';
        } else if (bottleVal && bottleVal !== 'default') {
            const bottleText = bottleSelect.options[bottleSelect.selectedIndex]?.textContent?.split(' - ')[0] || '';
            bottleDesc = ' (' + bottleText + ')';
        }

        const displayName = name + bottleDesc;

        cart.push({
            type: 'recipe',
            id: id,
            name: displayName,
            bottle_id: bottleVal,
            without_bottle: isWithoutBottle,
            qty: qty,
            price: price,
            discountType: '',
            discountValue: 0
        });

        select.value = '';
        const trigger = select.closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
        if (trigger) trigger.textContent = '-- اختر تركيبة جاهزة --';

        document.getElementById('add-recipe-qty').value = '1';
        document.getElementById('add-recipe-price').value = '';
        if (bottleSelect) {
            bottleSelect.value = 'default';
            if (typeof refreshSearchableSelect === 'function') refreshSearchableSelect(bottleSelect);
        }

        renderCart();
    }

    // ================================================================
    // POS Offer Customizer & Bottle Substitution System
    // ================================================================
    let currentCustomOffer = null; // { offerId, offerName, price, price_before, cartIndex, items: [...] }

    function onOfferSelectChange() {
        const select = document.getElementById('add-offer-select');
        const preview = document.getElementById('offer-contents-preview');
        if (!select || !select.value) {
            if (preview) { preview.innerHTML = ''; preview.style.display = 'none'; }
            document.getElementById('add-offer-price').value = '';
            return;
        }

        const option = select.options[select.selectedIndex];
        const price = option.getAttribute('data-price') || 0;
        const before = option.getAttribute('data-before') || 0;
        document.getElementById('add-offer-price').value = price;

        const offerId = parseInt(select.value);
        const offerData = (ALL_OFFERS_OFFLINE || []).find(o => parseInt(o.id) === offerId);

        if (offerData && preview) {
            let html = `<div style="font-weight:700; color:var(--gold-dark, #b98418); margin-bottom:4px;">🎁 محتويات العرض:</div><ul style="margin:0; padding-right:16px; line-height:1.4;">`;
            (offerData.items || []).forEach(it => {
                if (it.item_type === 'product') {
                    html += `<li>${it.quantity} × ${it.product_name}</li>`;
                } else {
                    const oils = (it.components || []).map(c => `${c.perfume_name} ${c.grams}جم`).join(' + ');
                    html += `<li>${it.quantity} × تركيبة: ${it.recipe_name} (${it.bottle_name || 'بدون زجاجة'}) ${oils ? ' [' + oils + ']' : ''}</li>`;
                }
            });
            html += `</ul>`;
            if (parseFloat(before) > parseFloat(price)) {
                html += `<div style="margin-top:6px; font-size:11.5px; color:#059669; font-weight:700;">✨ السعر الأصلي: ${before} ج.م — السعر في العرض: ${price} ج.م</div>`;
            }
            preview.innerHTML = html;
            preview.style.display = 'block';
        }
    }

    function openOfferCustomizerFromTab() {
        const select = document.getElementById('add-offer-select');
        const offerId = parseInt(select?.value) || 0;
        if (!offerId) return alert('الرجاء اختيار عرض من القائمة أولاً.');
        openOfferCustomizer(offerId, null);
    }

    function openOfferCustomizer(offerId, existingCartIndex = null) {
        const offerData = (ALL_OFFERS_OFFLINE || []).find(o => parseInt(o.id) === parseInt(offerId));
        if (!offerData) return alert('بيانات العرض غير متوفرة.');

        const priceInput = document.getElementById('add-offer-price');

        let initialItems = [];

        if (existingCartIndex !== null && cart[existingCartIndex] && cart[existingCartIndex].custom_data && Array.isArray(cart[existingCartIndex].custom_data.items)) {
            // Load from existing cart item
            initialItems = JSON.parse(JSON.stringify(cart[existingCartIndex].custom_data.items));
        } else {
            // Build default items from offer definition
            (offerData.items || []).forEach(it => {
                if (it.item_type === 'product') {
                    initialItems.push({
                        item_type: 'product',
                        product_id: parseInt(it.product_id),
                        product_name: it.product_name || 'منتج',
                        quantity: parseFloat(it.quantity) || 1
                    });
                } else {
                    const oils = (it.components || []).map(c => ({
                        perfume_id: parseInt(c.perfume_product_id),
                        perfume_name: c.perfume_name,
                        grams: parseFloat(c.grams) || 0,
                        price_per_gram: parseFloat(c.price_per_gram || c.sale_price || 0)
                    }));
                    initialItems.push({
                        item_type: 'recipe',
                        recipe_name: it.recipe_name || 'تركيبة خاصة',
                        bottle_id: it.bottle_product_id ? parseInt(it.bottle_product_id) : 'no_bottle',
                        quantity: parseFloat(it.quantity) || 1,
                        oils: oils.length > 0 ? oils : [{ perfume_id: 0, perfume_name: '', grams: 15, price_per_gram: 0 }]
                    });
                }
            });
        }

        currentCustomOffer = {
            offerId: parseInt(offerId),
            offerName: offerData.name,
            price: (existingCartIndex !== null && cart[existingCartIndex]) ? cart[existingCartIndex].price : (parseFloat(priceInput?.value) || parseFloat(offerData.price_after) || 0),
            price_before: parseFloat(offerData.price_before) || 0,
            cartIndex: existingCartIndex,
            items: initialItems
        };

        const modal = document.getElementById('offer-customizer-modal');
        const titleEl = document.getElementById('offer-customizer-title');
        const priceEl = document.getElementById('offer-customizer-price-display');
        const saveBadge = document.getElementById('offer-customizer-saving-badge');
        const submitBtn = document.getElementById('btn-save-offer-customization');

        if (titleEl) titleEl.textContent = '🎁 تخصيص محتويات العرض: ' + offerData.name;
        if (priceEl) priceEl.textContent = parseFloat(currentCustomOffer.price).toFixed(2) + ' ج.م';
        if (saveBadge) {
            const saving = currentCustomOffer.price_before - currentCustomOffer.price;
            if (saving > 0) {
                saveBadge.textContent = 'توفير ' + saving.toFixed(2) + ' ج.م';
                saveBadge.style.display = 'inline-block';
            } else {
                saveBadge.style.display = 'none';
            }
        }
        if (submitBtn) {
            submitBtn.textContent = existingCartIndex !== null ? '💾 حفظ التعديلات بالسلة' : '➕ تأكيد وإضافة للسلة';
        }

        renderOfferCustomizer();
        if (modal) modal.style.display = 'flex';
    }

    function closeOfferCustomizerModal() {
        const modal = document.getElementById('offer-customizer-modal');
        if (modal) modal.style.display = 'none';
        currentCustomOffer = null;
    }

    function renderOfferCustomizer() {
        const body = document.getElementById('offer-customizer-body');
        if (!body || !currentCustomOffer) return;

        body.innerHTML = '';

        const allBottles = (ALL_PRODUCTS_OFFLINE || []).filter(p => p.type === 'bottle');
        const allPerfumes = (ALL_PRODUCTS_OFFLINE || []).filter(p => p.type === 'perfume_gram');

        currentCustomOffer.items.forEach((item, itemIdx) => {
            const card = document.createElement('div');
            card.className = 'panel';
            card.style.cssText = 'background: var(--surface-soft, rgba(0,0,0,0.02)); border: 1.5px solid var(--gold-soft2, rgba(201,168,76,0.4)); border-radius: 12px; padding: 14px; margin-bottom: 8px;';

            if (item.item_type === 'product') {
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong style="color: var(--gold-dark); font-size: 14px;">📦 منتج جاهز مشمول بالعرض (${item.quantity}×)</strong>
                        <span class="badge" style="background: var(--gold-soft); color: var(--gold-dark); font-weight: 700;">جاهز</span>
                    </div>
                    <div style="margin-top: 8px; font-size: 13.5px; font-weight: 600;">
                        ${item.product_name}
                    </div>
                `;
            } else {
                const selectedBottleId = item.bottle_id;
                const bottleProduct = allBottles.find(b => b.id == selectedBottleId);
                const bottleStock = bottleProduct ? (bottleProduct.branch_stock !== null ? parseFloat(bottleProduct.branch_stock) : null) : null;
                const isOutOfStock = bottleStock !== null && bottleStock <= 0;

                let bottleOptionsHtml = `
                    <option value="no_bottle" ${selectedBottleId === 'no_bottle' ? 'selected' : ''}>بدون زجاجة (0.00 ج.م)</option>
                `;

                allBottles.forEach(b => {
                    const st = b.branch_stock !== null ? parseFloat(b.branch_stock) : null;
                    let stLabel = '';
                    if (st !== null) {
                        stLabel = st <= 0 ? ' [⚠️ نفد المخزون]' : (st < 3 ? ` [⚡ ${st} فقط]` : ` [📦 ${st} متاح]`);
                    }
                    const isSel = (b.id == selectedBottleId);
                    bottleOptionsHtml += `
                        <option value="${b.id}" ${isSel ? 'selected' : ''} data-size="${b.size_ml || ''}" data-stock="${st !== null ? st : ''}">
                            🍾 ${b.name} (${b.size_ml || '-'}ml) ${stLabel}
                        </option>
                    `;
                });

                let savedRecipesOptions = `<option value="">-- أو اختر تركيبة جاهزة سريعة --</option>`;
                (ALL_SAVED_RECIPES || []).forEach(r => {
                    savedRecipesOptions += `<option value="${r.id}">${r.name} - (${parseFloat(r.default_sale_price || 0).toFixed(2)} ج.م)</option>`;
                });

                let oilsHtml = '';
                (item.oils || []).forEach((oil, oilIdx) => {
                    let perfOptions = `<option value="">-- اختر الزيت العطري --</option>`;
                    allPerfumes.forEach(p => {
                        const isPerfSel = (p.id == oil.perfume_id);
                        perfOptions += `
                            <option value="${p.id}" ${isPerfSel ? 'selected' : ''} data-name="${p.name}" data-price="${p.price_per_gram || p.sale_price}">
                                ${p.name} (${p.quality_grade || '-'}) - (${(p.price_per_gram || p.sale_price || 0)} ج.م/جم)
                            </option>
                        `;
                    });

                    oilsHtml += `
                        <div class="line-grid two mix-perfume-row" style="margin-bottom: 8px; align-items: center;">
                            <select onchange="onCustomizerOilSelectChange(${itemIdx}, ${oilIdx}, this)" style="padding: 8px 10px; border-radius: 8px; border: 1.5px solid var(--line); font-size: 13px;">
                                ${perfOptions}
                            </select>
                            <div class="mix-gram-stepper" style="height: 38px; width: 135px;">
                                <button type="button" class="stepper-btn" onclick="adjustCustomizerGram(${itemIdx}, ${oilIdx}, -1)">–</button>
                                <input type="number" step="any" min="0" value="${oil.grams || ''}" placeholder="جم" class="stepper-input" oninput="onCustomizerGramInput(${itemIdx}, ${oilIdx}, this.value)">
                                <button type="button" class="stepper-btn" onclick="adjustCustomizerGram(${itemIdx}, ${oilIdx}, 1)">+</button>
                            </div>
                            <button type="button" class="btn small danger" onclick="removeCustomizerOilRow(${itemIdx}, ${oilIdx})" style="padding: 6px 10px; border-radius: 8px;" title="حذف الزيت">×</button>
                        </div>
                    `;
                });

                const totalGrams = (item.oils || []).reduce((s, o) => s + (parseFloat(o.grams) || 0), 0);
                const bottleSize = bottleProduct ? (bottleProduct.size_ml || '-') : '-';

                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; border-bottom: 1px dashed var(--line); padding-bottom: 8px;">
                        <div>
                            <strong style="color: var(--gold-dark); font-size: 14px;">🧪 ${item.recipe_name || 'تركيبة عطرية'}</strong>
                            <span style="font-size: 12px; color: var(--muted); margin-right: 6px;">(الكمية في العرض: ${item.quantity}×)</span>
                        </div>
                        <span class="badge" style="background: rgba(185,132,24,0.15); color: var(--gold-dark); font-weight: 700;">تركيبة زجاجة</span>
                    </div>

                    <!-- 1. Bottle Selector & Substitution -->
                    <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 10px; margin-bottom: 12px;">
                        <label style="font-size: 12px; font-weight: 700; color: var(--ink);">
                            نوع الزجاجة المستخدمة (استبدال الزجاجة)
                            <select onchange="onCustomizerBottleChange(${itemIdx}, this.value)" style="margin-top: 4px; padding: 8px 10px; border: 1.5px solid ${isOutOfStock ? 'var(--danger)' : 'var(--gold)'}; border-radius: 8px; font-size: 13px; font-weight: 600; width: 100%;">
                                ${bottleOptionsHtml}
                            </select>
                        </label>

                        <label style="font-size: 12px; font-weight: 700; color: var(--ink);">
                            وصفة جاهزة سريعة (اختياري)
                            <select onchange="onCustomizerSavedRecipeChange(${itemIdx}, this.value)" style="margin-top: 4px; padding: 8px 10px; border: 1.5px solid var(--line); border-radius: 8px; font-size: 13px; width: 100%;">
                                ${savedRecipesOptions}
                            </select>
                        </label>
                    </div>

                    ${isOutOfStock ? `
                        <div style="background: rgba(220,38,38,0.1); color: var(--danger); border: 1px solid rgba(220,38,38,0.3); border-radius: 6px; padding: 6px 10px; font-size: 11.5px; font-weight: 700; margin-bottom: 10px;">
                            ⚠️ تنبيه: الزجاجة المختارة نفدت من مخزون الفرع! يرجى اختيار نوع زجاجة آخر متاح من القائمة أعلاه.
                        </div>
                    ` : ''}

                    <!-- 2. Fragrance Oils List -->
                    <div style="background: var(--surface); border: 1px solid var(--line); border-radius: 8px; padding: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-size: 12px; font-weight: 700; color: var(--muted);">مكونات الزيوت العطرية بالجرام:</span>
                            <div style="font-size: 11.5px; font-weight: 700; color: var(--gold-dark);">
                                الإجمالي: <span style="color: var(--primary); font-size: 13px;">${totalGrams.toFixed(1)} جم</span> ${bottleSize !== '-' ? `(سعة الزجاجة: ${bottleSize} مل)` : ''}
                            </div>
                        </div>

                        <div id="customizer-oils-container-${itemIdx}">
                            ${oilsHtml || '<div style="font-size: 12px; color: var(--muted); text-align: center; padding: 8px;">لا توجد زيوت مضافة بعد. اضغط على زر إضافة زيت بالأسفل.</div>'}
                        </div>

                        <button type="button" class="btn small primary" onclick="addCustomizerOilRow(${itemIdx})" style="margin-top: 6px; padding: 4px 10px; font-size: 11.5px; border-radius: 6px;">
                            + إضافة زيت عطري للتركيبة
                        </button>
                    </div>
                `;
            }

            body.appendChild(card);
        });
    }

    function onCustomizerBottleChange(itemIdx, newBottleId) {
        if (!currentCustomOffer || !currentCustomOffer.items[itemIdx]) return;
        currentCustomOffer.items[itemIdx].bottle_id = (newBottleId === 'no_bottle') ? 'no_bottle' : parseInt(newBottleId);
        renderOfferCustomizer();
    }

    function onCustomizerSavedRecipeChange(itemIdx, recipeId) {
        if (!currentCustomOffer || !currentCustomOffer.items[itemIdx] || !recipeId) return;
        const rId = parseInt(recipeId);
        const recipe = (ALL_SAVED_RECIPES || []).find(r => parseInt(r.id) === rId);
        if (!recipe) return;

        if (recipe.items && Array.isArray(recipe.items)) {
            currentCustomOffer.items[itemIdx].oils = recipe.items.map(it => ({
                perfume_id: parseInt(it.perfume_product_id),
                perfume_name: it.perfume_name || '',
                grams: parseFloat(it.grams) || 0,
                price_per_gram: parseFloat(it.price_per_gram || it.sale_price || 0)
            }));
            currentCustomOffer.items[itemIdx].recipe_name = recipe.name;
        }
        renderOfferCustomizer();
    }

    function addCustomizerOilRow(itemIdx) {
        if (!currentCustomOffer || !currentCustomOffer.items[itemIdx]) return;
        if (!Array.isArray(currentCustomOffer.items[itemIdx].oils)) {
            currentCustomOffer.items[itemIdx].oils = [];
        }
        currentCustomOffer.items[itemIdx].oils.push({
            perfume_id: 0,
            perfume_name: '',
            grams: 10,
            price_per_gram: 0
        });
        renderOfferCustomizer();
    }

    function removeCustomizerOilRow(itemIdx, oilIdx) {
        if (!currentCustomOffer || !currentCustomOffer.items[itemIdx]) return;
        currentCustomOffer.items[itemIdx].oils.splice(oilIdx, 1);
        renderOfferCustomizer();
    }

    function onCustomizerOilSelectChange(itemIdx, oilIdx, select) {
        if (!currentCustomOffer || !currentCustomOffer.items[itemIdx] || !currentCustomOffer.items[itemIdx].oils[oilIdx]) return;
        const opt = select.options[select.selectedIndex];
        currentCustomOffer.items[itemIdx].oils[oilIdx].perfume_id = parseInt(select.value) || 0;
        currentCustomOffer.items[itemIdx].oils[oilIdx].perfume_name = opt?.getAttribute('data-name') || '';
        currentCustomOffer.items[itemIdx].oils[oilIdx].price_per_gram = parseFloat(opt?.getAttribute('data-price')) || 0;
        renderOfferCustomizer();
    }

    function onCustomizerGramInput(itemIdx, oilIdx, val) {
        if (!currentCustomOffer || !currentCustomOffer.items[itemIdx] || !currentCustomOffer.items[itemIdx].oils[oilIdx]) return;
        currentCustomOffer.items[itemIdx].oils[oilIdx].grams = parseFloat(val) || 0;
    }

    function adjustCustomizerGram(itemIdx, oilIdx, delta) {
        if (!currentCustomOffer || !currentCustomOffer.items[itemIdx] || !currentCustomOffer.items[itemIdx].oils[oilIdx]) return;
        let g = parseFloat(currentCustomOffer.items[itemIdx].oils[oilIdx].grams) || 0;
        g = Math.max(0, g + delta);
        currentCustomOffer.items[itemIdx].oils[oilIdx].grams = parseFloat(g.toFixed(1));
        renderOfferCustomizer();
    }

    function saveOfferCustomizationToCart() {
        if (!currentCustomOffer) return;

        for (let i = 0; i < currentCustomOffer.items.length; i++) {
            const it = currentCustomOffer.items[i];
            if (it.item_type === 'recipe') {
                if (!it.bottle_id) {
                    alert(`الرجاء اختيار زجاجة لـ ${it.recipe_name || 'التركيبة ' + (i+1)}`);
                    return;
                }
                const validOils = (it.oils || []).filter(o => o.perfume_id > 0 && o.grams > 0);
                if (validOils.length === 0) {
                    alert(`الرجاء إضافة زيت عطري واحد على الأقل وتحديد الجرامات لـ ${it.recipe_name || 'التركيبة ' + (i+1)}`);
                    return;
                }
            }
        }

        const customDataPayload = {
            items: currentCustomOffer.items
        };

        if (currentCustomOffer.cartIndex !== null && cart[currentCustomOffer.cartIndex]) {
            cart[currentCustomOffer.cartIndex].custom_data = customDataPayload;
        } else {
            const qty = parseFloat(document.getElementById('add-offer-qty')?.value) || 1;
            const price = parseFloat(currentCustomOffer.price) || 0;

            cart.push({
                type: 'offer',
                id: String(currentCustomOffer.offerId),
                name: '🎁 ' + currentCustomOffer.offerName,
                qty: qty,
                price: price,
                discountType: '',
                discountValue: 0,
                custom_data: customDataPayload
            });

            const select = document.getElementById('add-offer-select');
            if (select) select.value = '';
            const preview = document.getElementById('offer-contents-preview');
            if (preview) { preview.innerHTML = ''; preview.style.display = 'none'; }
            const addQtyInput = document.getElementById('add-offer-qty');
            if (addQtyInput) addQtyInput.value = '1';
            const addPriceInput = document.getElementById('add-offer-price');
            if (addPriceInput) addPriceInput.value = '';
        }

        closeOfferCustomizerModal();
        renderCart();
    }

    // ================================================================
    // Bundle Cart Mixes & Recipes into an Offer
    // ================================================================
    function openBundleOfferModal(precheckedCartIndex = null) {
        const modal = document.getElementById('bundle-offer-modal');
        const listContainer = document.getElementById('bundle-cart-items-list');
        const selectEl = document.getElementById('bundle-target-offer-select');

        // Find eligible items from cart (recipes, mixes, products)
        const eligibleItems = [];
        cart.forEach((it, idx) => {
            if (it.type === 'custom_recipe' || it.type === 'recipe' || it.type === 'product') {
                eligibleItems.push({ item: it, index: idx });
            }
        });

        if (eligibleItems.length === 0) {
            return alert('لا توجد تركيبات أو أصناف مجهزة في السلة حالياً.\nقم بتجهيز التركيبة وإضافتها للسلة أولاً ثم اضغط تطبيق العرض.');
        }

        if (listContainer) {
            listContainer.innerHTML = '';
            eligibleItems.forEach(({ item, index }) => {
                const isPrechecked = (precheckedCartIndex === index) || (precheckedCartIndex === null && eligibleItems.length <= 4);
                const row = document.createElement('label');
                row.style.cssText = 'display: flex; align-items: center; justify-content: space-between; background: var(--surface); border: 1.5px solid var(--line); border-radius: 10px; padding: 10px 14px; cursor: pointer; transition: all 0.2s;';
                row.className = 'bundle-cart-item-row';

                let descText = '';
                if (item.type === 'custom_recipe') {
                    const bObj = (ALL_PRODUCTS_OFFLINE || []).find(p => p.id == item.bottle_id);
                    const bName = bObj ? `${bObj.name} (${bObj.size_ml || '-'}ml)` : (item.without_bottle ? 'بدون زجاجة' : 'زجاجة');
                    const oils = (item.components || []).map(c => `${c.name} ${c.grams}جم`).join(' + ');
                    descText = `🍾 ${bName} ${oils ? ' | ' + oils : ''}`;
                }

                row.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" class="bundle-item-check" data-cart-index="${index}" ${isPrechecked ? 'checked' : ''} onchange="updateBundleSelectionSummary()" style="width: 18px; height: 18px; accent-color: var(--gold); cursor: pointer;">
                        <div>
                            <strong style="font-size: 13.5px; color: var(--ink); display: block;">${item.name}</strong>
                            ${descText ? `<span style="font-size: 11.5px; color: var(--muted);">${descText}</span>` : ''}
                        </div>
                    </div>
                    <div style="text-align: left;">
                        <strong style="color: var(--primary); font-size: 14px;">${item.price} ج.م</strong>
                    </div>
                `;
                listContainer.appendChild(row);
            });
        }

        if (selectEl && selectEl.options.length > 1 && !selectEl.value) {
            selectEl.selectedIndex = 1;
        }
        onBundleTargetOfferChange();
        updateBundleSelectionSummary();

        if (modal) modal.style.display = 'flex';
    }

    function closeBundleOfferModal() {
        const modal = document.getElementById('bundle-offer-modal');
        if (modal) modal.style.display = 'none';
    }

    function updateBundleSelectionSummary() {
        const checked = document.querySelectorAll('.bundle-item-check:checked');
        const badge = document.getElementById('bundle-selection-count-badge');
        if (badge) badge.textContent = `${checked.length} محددة`;
    }

    function onBundleTargetOfferChange() {
        const select = document.getElementById('bundle-target-offer-select');
        const previewBox = document.getElementById('bundle-offer-preview-box');
        const titleEl = document.getElementById('bundle-preview-title');
        const descEl = document.getElementById('bundle-preview-desc');
        const priceEl = document.getElementById('bundle-preview-price');

        if (!select || !select.value) {
            if (previewBox) previewBox.style.display = 'none';
            return;
        }

        const offerId = parseInt(select.value);
        const offer = (ALL_OFFERS_OFFLINE || []).find(o => parseInt(o.id) === offerId);
        if (!offer) return;

        if (titleEl) titleEl.textContent = '🎁 ' + offer.name;
        if (priceEl) priceEl.textContent = parseFloat(offer.price_after).toFixed(2) + ' ج.م';
        if (descEl) descEl.textContent = `سيتم استبدال التركيبات المحددة في السلة بهذا الباكدج بسعر ${offer.price_after} ج.م بدلاً من ${offer.price_before} ج.م`;
        if (previewBox) previewBox.style.display = 'block';
    }

    function applyBundleOfferToCart() {
        const select = document.getElementById('bundle-target-offer-select');
        const offerId = parseInt(select?.value) || 0;
        if (!offerId) return alert('الرجاء اختيار العرض المطلوب تطبيقه.');

        const offer = (ALL_OFFERS_OFFLINE || []).find(o => parseInt(o.id) === offerId);
        if (!offer) return alert('العرض غير موجود.');

        const checkedBoxes = Array.from(document.querySelectorAll('.bundle-item-check:checked'));
        if (checkedBoxes.length === 0) {
            return alert('الرجاء تحديد تركيبة واحدة على الأقل من القائمة لضمها داخل العرض.');
        }

        const selectedIndices = checkedBoxes.map(cb => parseInt(cb.getAttribute('data-cart-index'))).sort((a, b) => b - a);

        // Build custom_data.items from the selected cart items
        const customItems = [];
        selectedIndices.slice().reverse().forEach(idx => {
            const it = cart[idx];
            if (it.type === 'custom_recipe') {
                customItems.push({
                    item_type: 'recipe',
                    recipe_name: it.name.replace(/^🧪\s*تركيبة فورية:\s*/, ''),
                    bottle_id: it.bottle_id || 'no_bottle',
                    quantity: it.qty || 1,
                    oils: (it.components || []).map(c => ({
                        perfume_id: parseInt(c.product_id),
                        perfume_name: c.name || '',
                        grams: parseFloat(c.grams) || 0,
                        price_per_gram: 0
                    }))
                });
            } else if (it.type === 'recipe') {
                customItems.push({
                    item_type: 'recipe',
                    recipe_name: it.name,
                    bottle_id: it.bottle_id || 'default',
                    quantity: it.qty || 1,
                    oils: []
                });
            } else if (it.type === 'product') {
                customItems.push({
                    item_type: 'product',
                    product_id: parseInt(it.id),
                    product_name: it.name,
                    quantity: it.qty || 1
                });
            }
        });

        // Remove original individual items from cart (descending index order)
        selectedIndices.forEach(idx => {
            cart.splice(idx, 1);
        });

        // Add consolidated Offer item
        cart.push({
            type: 'offer',
            id: String(offer.id),
            name: '🎁 ' + offer.name,
            qty: 1,
            price: parseFloat(offer.price_after) || 0,
            discountType: '',
            discountValue: 0,
            custom_data: { items: customItems }
        });

        closeBundleOfferModal();
        renderCart();
        alert('✅ تم تطبيق العرض بنجاح وضم التركيبات المحددة داخل الباكدج!');
    }

    function addMixDirectlyToOffer() {
        const mixBottleSelect = document.getElementById('mix_bottle_id');
        const bottleId = mixBottleSelect?.value;
        if (!bottleId) return alert('الرجاء اختيار زجاجة للتركيبة أولاً.');

        const perfumeRows = document.querySelectorAll('.mix-perfume-row');
        let hasOil = false;
        perfumeRows.forEach(row => {
            const sel = row.querySelector('select[name="mix_perfume_id[]"]');
            const gramsInput = row.querySelector('input[name="mix_grams[]"]');
            if (sel && sel.value && parseFloat(gramsInput?.value) > 0) {
                hasOil = true;
            }
        });

        if (!hasOil) return alert('الرجاء اختيار زيت عطري واحد على الأقل وتحديد الجرامات.');

        // Add mix to cart first
        addMixToCart();

        // Immediately open the Bundle Offer Modal with this newly added mix selected
        const newMixIndex = cart.length - 1;
        openBundleOfferModal(newMixIndex);
    }

    function addOfferToCart(promptCustomize = true) {
        const select = document.getElementById('add-offer-select');
        const id = select.value;
        if (!id) return alert('الرجاء اختيار عرض أولاً.');

        if (promptCustomize) {
            openOfferCustomizer(id, null);
            return;
        }

        const option = select.options[select.selectedIndex];
        const name = option.getAttribute('data-name');
        const qty = parseFloat(document.getElementById('add-offer-qty').value) || 1;
        const price = parseFloat(document.getElementById('add-offer-price').value) || 0;

        if (qty <= 0) return alert('الرجاء إدخال كمية صحيحة.');

        const offerData = (ALL_OFFERS_OFFLINE || []).find(o => parseInt(o.id) === parseInt(id));
        let defaultCustomItems = [];
        if (offerData && Array.isArray(offerData.items)) {
            offerData.items.forEach(it => {
                if (it.item_type === 'product') {
                    defaultCustomItems.push({
                        item_type: 'product',
                        product_id: parseInt(it.product_id),
                        product_name: it.product_name,
                        quantity: parseFloat(it.quantity) || 1
                    });
                } else {
                    const oils = (it.components || []).map(c => ({
                        perfume_id: parseInt(c.perfume_product_id),
                        perfume_name: c.perfume_name,
                        grams: parseFloat(c.grams) || 0,
                        price_per_gram: parseFloat(c.price_per_gram || c.sale_price || 0)
                    }));
                    defaultCustomItems.push({
                        item_type: 'recipe',
                        recipe_name: it.recipe_name || 'تركيبة خاصة',
                        bottle_id: it.bottle_product_id ? parseInt(it.bottle_product_id) : 'no_bottle',
                        quantity: parseFloat(it.quantity) || 1,
                        oils: oils
                    });
                }
            });
        }

        cart.push({
            type: 'offer',
            id: id,
            name: '🎁 ' + name,
            qty: qty,
            price: price,
            discountType: '',
            discountValue: 0,
            custom_data: { items: defaultCustomItems }
        });

        select.value = '';
        const preview = document.getElementById('offer-contents-preview');
        if (preview) { preview.innerHTML = ''; preview.style.display = 'none'; }
        document.getElementById('add-offer-qty').value = '1';
        document.getElementById('add-offer-price').value = '';

        renderCart();
    }

    // Remove item from cart array
    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
    }

    // Update cart item quantity
    function updateQty(index, val) {
        const qty = parseFloat(val) || 0;
        if (qty > 0) {
            cart[index].qty = qty;
            renderCart();
        }
    }

    // Update cart item price
    function updatePrice(index, val) {
        const price = parseFloat(val) || 0;
        if (price >= 0) {
            cart[index].price = price;
            renderCart();
        }
    }

    // Update cart item line discount
    function updateLineDiscountType(index, val) {
        cart[index].discountType = val;
        renderCart();
    }

    // Update cart item line discount value
    function updateLineDiscountValue(index, val) {
        cart[index].discountValue = parseFloat(val) || 0;
        renderCart();
    }

    // Render cart table and populate hidden form fields
    function renderCart() {
        const tbody = document.getElementById('cart-tbody');
        const cartCount = document.getElementById('cart-count');
        const hiddenContainer = document.getElementById('hidden-cart-inputs');

        tbody.innerHTML = '';
        hiddenContainer.innerHTML = '';

        // Save to localStorage
        saveCurrentTabState();

        if (cart.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="muted" style="text-align: center; padding: 20px;">السلة فارغة. قم باختيار صنف وإضافته بالأعلى.</td></tr>`;
            cartCount.textContent = '0 أصناف';
            recalculateTotals();
            return;
        }

        cartCount.textContent = cart.length + ' أصناف';

        cart.forEach((item, index) => {
            let gross = item.qty * item.price;
            let discountText = 'بدون';
            let lineTotal = gross;

            if (item.discountType === 'percent') {
                const discAmt = gross * (item.discountValue / 100);
                discountText = `%${item.discountValue} (${discAmt} ج.م)`;
                lineTotal = gross - discAmt;
            } else if (item.discountType === 'amount') {
                discountText = `${item.discountValue} ج.م`;
                lineTotal = gross - item.discountValue;
            }

            lineTotal = Math.max(0, lineTotal);

            const badgeClass = item.type === 'product' ? 'direct' : (item.type === 'custom_recipe' ? 'custom' : (item.type === 'offer' ? 'gold' : 'recipe'));
            const badgeLabel = item.type === 'product' ? 'جاهز' : (item.type === 'custom_recipe' ? '🧪 فورية' : (item.type === 'offer' ? '🎁 عرض' : 'تركيبة'));

            let warningBadge = '';
            if (item.type === 'custom_recipe') {
                const priceChanged = item.defaultPrice && item.price !== item.defaultPrice;
                const gramsChanged = (item.components || []).some(c => c.default_grams > 0 && Math.abs(c.grams - c.default_grams) > 0.01);
                if (priceChanged || gramsChanged) {
                    const tips = [];
                    if (priceChanged) tips.push('سعر معدّل: ' + item.defaultPrice + ' → ' + item.price + ' ج.م');
                    if (gramsChanged) tips.push('جرامات معدّلة');
                    warningBadge = `<span title="${tips.join(' | ')}" style="display:inline-flex;align-items:center;gap:3px;background:rgba(245,158,11,0.15);color:#b45309;border:1px solid rgba(245,158,11,0.4);border-radius:6px;padding:1px 7px;font-size:10px;font-weight:800;margin-right:5px;cursor:help;">⚠️ معدّل</span>`;
                }
            }

            let offerCustomHtml = '';
            if (item.type === 'offer') {
                if (item.custom_data && Array.isArray(item.custom_data.items) && item.custom_data.items.length > 0) {
                    offerCustomHtml += `<div style="margin-top: 5px; font-size: 11.5px; line-height: 1.45; color: var(--ink); background: rgba(201,168,76,0.08); border-radius: 6px; padding: 5px 8px; border-right: 3px solid var(--gold);">`;
                    item.custom_data.items.forEach((cIt, cIdx) => {
                        if (cIt.item_type === 'recipe') {
                            const bObj = (ALL_PRODUCTS_OFFLINE || []).find(p => p.id == cIt.bottle_id);
                            const bName = bObj ? `${bObj.name} (${bObj.size_ml || '-'}ml)` : (cIt.bottle_id === 'no_bottle' ? 'بدون زجاجة' : 'زجاجة مخصصة');
                            const oilsText = (cIt.oils || []).map(o => `${o.perfume_name || 'زيت'} (${o.grams}جم)`).join(' + ');
                            offerCustomHtml += `<div style="margin-bottom: 2px;">🧪 <strong>${cIt.recipe_name || 'تركيبة ' + (cIdx+1)}:</strong> 🍾 ${bName} ${oilsText ? ' ‹ ' + oilsText + ' ›' : ''}</div>`;
                        } else {
                            offerCustomHtml += `<div style="margin-bottom: 2px;">📦 <strong>${cIt.product_name || 'منتج جاهز'}</strong> (${cIt.quantity}×)</div>`;
                        }
                    });
                    offerCustomHtml += `</div>`;
                }
                offerCustomHtml += `<div style="margin-top: 4px;"><button type="button" class="btn small secondary" onclick="openOfferCustomizer(${item.id}, ${index})" style="padding: 2px 8px; font-size: 11px; border-radius: 6px; border: 1px dashed var(--gold); background: var(--surface);">✏️ استبدال الزجاجة وتعديل التركيبة</button></div>`;
            } else if (item.type === 'custom_recipe' || item.type === 'recipe') {
                offerCustomHtml += `<div style="margin-top: 3px;"><button type="button" class="btn small secondary" onclick="openBundleOfferModal(${index})" style="padding: 1px 7px; font-size: 10px; border-radius: 5px; border: 1px dashed var(--gold); background: var(--surface); color: var(--gold-dark); font-weight: 700;" title="ضم هذه التركيبة إلى عرض ترويجي">🎁 ضم لعرض</button></div>`;
            }

            const row = document.createElement('tr');
            if (item.type === 'custom_recipe' && warningBadge) {
                row.style.background = 'rgba(245,158,11,0.06)';
            }
            row.innerHTML = `
            <td>
                <strong>${item.name}</strong> 
                <span class="badge-type ${badgeClass}">${badgeLabel}</span>
                ${warningBadge}
                ${offerCustomHtml}
            </td>
            <td>
                <div class="qty-control">
                    <button type="button" onclick="adjustQty(${index}, -1)">–</button>
                    <input type="number" step="any" min="0.01" value="${item.qty}" onchange="updateQty(${index}, this.value)">
                    <button type="button" onclick="adjustQty(${index}, 1)">+</button>
                </div>
                ${(item.type === 'custom_recipe' && item.without_bottle) ? (() => { const totalG = (item.components || []).reduce((s, c) => s + (parseFloat(c.grams) || 0), 0); return totalG > 0 ? `<div style="font-size:10px;color:var(--muted);text-align:center;margin-top:2px">${parseFloat(totalG.toFixed(2))}جم</div>` : ''; })() : ''}
            </td>
            <td>
                <input type="number" step="any" min="0" value="${item.price}" onchange="updatePrice(${index}, this.value)" style="width: 80px; text-align: center; padding: 6px; border: 1px solid var(--line); border-radius: 6px;" ${item.type === 'recipe' ? 'readonly' : ''}>
            </td>
            <td>
                ${item.type === 'product' ? `
                    <div style="display: flex; gap: 4px; align-items: center;">
                        <select onchange="updateLineDiscountType(${index}, this.value)" style="padding: 4px 6px; font-size: 12px; border: 1px solid var(--line); border-radius: 6px;">
                            <option value="" ${item.discountType === '' ? 'selected' : ''}>بدون</option>
                            <option value="amount" ${item.discountType === 'amount' ? 'selected' : ''}>مبلغ</option>
                            <option value="percent" ${item.discountType === 'percent' ? 'selected' : ''}>%</option>
                        </select>
                        <input type="number" step="any" min="0" value="${item.discountValue}" onchange="updateLineDiscountValue(${index}, this.value)" style="width: 65px; text-align: center; padding: 4px; font-size: 12px; border: 1px solid var(--line); border-radius: 6px;">
                    </div>
                ` : `<span class="muted">-</span>`}
            </td>
            <td><strong>${lineTotal} ج.م</strong></td>
            <td>
                <button type="button" class="btn-delete-item" onclick="removeItem(${index})" title="حذف">×</button>
            </td>
        `;
            tbody.appendChild(row);

            if (item.type === 'product') {
                hiddenContainer.innerHTML += `
                <input type="hidden" name="product_id[]" value="${item.id}">
                <input type="hidden" name="quantity[]" value="${item.qty}">
                <input type="hidden" name="unit_price[]" value="${item.price}">
                <input type="hidden" name="line_discount_type[]" value="${item.discountType}">
                <input type="hidden" name="line_discount_value[]" value="${item.discountValue}">
            `;
            } else if (item.type === 'offer') {
                const customDataStr = JSON.stringify(item.custom_data || {});
                hiddenContainer.innerHTML += `
                <input type="hidden" name="offer_id[]" value="${item.id}">
                <input type="hidden" name="offer_qty[]" value="${item.qty}">
                <input type="hidden" name="offer_price[]" value="${item.price}">
                <input type="hidden" name="offer_custom_data[]" value='${customDataStr.replace(/'/g, '&#39;')}'>
            `;
            } else if (item.type === 'recipe') {
                const isWithoutBottle = item.without_bottle || (item.bottle_id === 'no_bottle');
                const bottleIdToSend = item.bottle_id || 'default';
                const wholeCount = Math.max(1, Math.round(item.qty));
                for (let q = 0; q < wholeCount; q++) {
                    hiddenContainer.innerHTML += `
                    <input type="hidden" name="recipe_id[]" value="${item.id}">
                    <input type="hidden" name="recipe_bottle_id[]" value="${bottleIdToSend}">
                    <input type="hidden" name="recipe_without_bottle[]" value="${isWithoutBottle ? '1' : '0'}">
                `;
                }
            } else if (item.type === 'custom_recipe') {
                const mixData = JSON.stringify({
                    bottle_id: item.bottle_id,
                    without_bottle: item.without_bottle || false,
                    sale_price: item.price,
                    default_price: item.defaultPrice || item.price,
                    components: item.components
                });
                for (let q = 0; q < item.qty; q++) {
                    hiddenContainer.innerHTML += `
                    <input type="hidden" name="mix_data[]" value='${mixData.replace(/'/g, '&#39;')}'>
                `;
                }
            }
        });

        // Add exchange data hidden input if active on current tab
        const activeTab = getActiveTab();
        if (activeTab && activeTab.exchangeData && activeTab.exchangeData.credit_amount > 0) {
            hiddenContainer.innerHTML += `
                <input type="hidden" name="exchange_data" value='${JSON.stringify(activeTab.exchangeData).replace(/'/g, '&#39;')}'>
            `;
        }

        updateExchangeBannerUI();
        recalculateTotals();
    }

    // Toggle split payment inputs and manage defaults
    function togglePaymentFields() {
        const methodSelect = document.getElementById('payment_method_select');
        const splitContainer = document.getElementById('split-payment-container');
        const employeeContainer = document.getElementById('salary-deduction-employee-container');
        const paidInput = document.getElementById('paid_total_input');
        const secondaryLabel = document.getElementById('secondary_payment_label');
        const secondaryInput = document.getElementById('paid_secondary_input');
        const destinationNote = document.getElementById('payment_destination_note');
        const methodDestination = methodSelect.value.includes('instapay') ? paymentDestinations.instapay : (methodSelect.value.includes('vodafone') ? paymentDestinations.vodafone_cash : '');
        destinationNote.textContent = methodDestination ? 'بيانات التحويل: ' + methodDestination : '';
        
        if (employeeContainer) {
            employeeContainer.style.display = (methodSelect.value === 'salary_deduction') ? 'block' : 'none';
        }
        
        if (methodSelect.value === 'mixed_cash_instapay' || methodSelect.value === 'mixed_cash_vodafone') {
            splitContainer.style.display = 'grid';
            paidInput.readOnly = true;
            paidInput.style.background = 'var(--surface-soft)';
            paidInput.style.cursor = 'not-allowed';
            
            if (methodSelect.value === 'mixed_cash_instapay') {
                secondaryLabel.textContent = 'المبلغ إنستا باي (ج.م)';
                secondaryInput.setAttribute('name', 'paid_instapay');
            } else {
                secondaryLabel.textContent = 'المبلغ فودافون كاش (ج.م)';
                secondaryInput.setAttribute('name', 'paid_vodafone_cash');
            }
            
            // Auto balance to start
            const totalNeeded = parseInt(document.getElementById('lbl-total').textContent) || 0;
            document.getElementById('paid_cash_input').value = totalNeeded;
            secondaryInput.value = '';
            paidInput.value = totalNeeded;
        } else {
            splitContainer.style.display = 'none';
            paidInput.readOnly = false;
            paidInput.style.background = 'var(--surface)';
            paidInput.style.cursor = 'auto';
            
            const totalNeeded = parseInt(document.getElementById('lbl-total').textContent) || 0;
            paidInput.value = totalNeeded;
        }
        recalculateTotals();
    }

    // Fill remaining balance to a split payment field
    function fillRemainder(field) {
        const totalNeeded = parseInt(document.getElementById('lbl-total').textContent) || 0;
        const cashInput = document.getElementById('paid_cash_input');
        const secondaryInput = document.getElementById('paid_secondary_input');
        
        let paidCash = parseInt(cashInput.value) || 0;
        let paidSecondary = parseInt(secondaryInput.value) || 0;
        
        if (field === 'cash') {
            cashInput.value = Math.max(0, totalNeeded - paidSecondary);
        } else if (field === 'secondary') {
            secondaryInput.value = Math.max(0, totalNeeded - paidCash);
        }
        recalculateTotals();
    }

    function recalculateTotals() {
        let subtotal = 0;

        // Sum cart items
        cart.forEach(item => {
            let gross = item.qty * item.price;
            let discount = 0;
            if (item.discountType === 'percent') {
                discount = gross * (item.discountValue / 100);
            } else if (item.discountType === 'amount') {
                discount = item.discountValue;
            }
            subtotal += Math.max(0, gross - discount);
        });

        // Calculate invoice discount
        const discountTypeSelect = document.getElementById('invoice_discount_type');
        const discountValueInput = document.getElementById('invoice_discount_value');
        let invDiscount = 0;
        if (discountTypeSelect.value === 'percent') {
            invDiscount = subtotal * ((parseInt(discountValueInput.value) || 0) / 100);
        } else if (discountTypeSelect.value === 'amount') {
            invDiscount = parseInt(discountValueInput.value) || 0;
        }

        let finalTotal = Math.max(0, subtotal - invDiscount);

        // Exchange credit computation
        const activeTab = getActiveTab();
        const exchangeCredit = (activeTab && activeTab.exchangeData) ? (parseFloat(activeTab.exchangeData.credit_amount) || 0) : 0;

        const rowExchangeCredit = document.getElementById('row-exchange-credit');
        const lblExchangeCredit = document.getElementById('lbl-exchange-credit');
        const rowExchangeDiff = document.getElementById('row-exchange-diff');
        const lblExchangeDiffTitle = document.getElementById('lbl-exchange-diff-title');
        const lblExchangeDiffVal = document.getElementById('lbl-exchange-diff-val');
        const lblTotalTitle = document.getElementById('lbl-total-title');

        let netPayable = finalTotal;

        if (exchangeCredit > 0) {
            if (rowExchangeCredit) {
                rowExchangeCredit.style.display = 'flex';
                lblExchangeCredit.textContent = '- ' + exchangeCredit.toFixed(2) + ' ج.م';
            }
            const diff = finalTotal - exchangeCredit;
            if (rowExchangeDiff) {
                rowExchangeDiff.style.display = 'flex';
                if (diff > 0) {
                    lblExchangeDiffTitle.textContent = 'فرق الاستبدال (مطلوب دفعه):';
                    lblExchangeDiffVal.textContent = diff.toFixed(2) + ' ج.م';
                    lblExchangeDiffVal.style.color = 'var(--primary-dark)';
                    netPayable = diff;
                } else if (diff < 0) {
                    lblExchangeDiffTitle.textContent = 'فرق الاستبدال (مسترجع للعميل):';
                    lblExchangeDiffVal.textContent = Math.abs(diff).toFixed(2) + ' ج.م';
                    lblExchangeDiffVal.style.color = '#16a34a';
                    netPayable = 0;
                } else {
                    lblExchangeDiffTitle.textContent = 'فرق الاستبدال:';
                    lblExchangeDiffVal.textContent = '0.00 ج.م (استبدال متطابق)';
                    lblExchangeDiffVal.style.color = 'var(--ink)';
                    netPayable = 0;
                }
            }
            if (lblTotalTitle) lblTotalTitle.textContent = 'المطلوب دفعه كفرق:';
        } else {
            if (rowExchangeCredit) rowExchangeCredit.style.display = 'none';
            if (rowExchangeDiff) rowExchangeDiff.style.display = 'none';
            if (lblTotalTitle) lblTotalTitle.textContent = 'المطلوب دفعه:';
            netPayable = finalTotal;
        }

        const paidInput = document.getElementById('paid_total_input');
        const methodSelect = document.getElementById('payment_method_select');
        
        if (methodSelect && (methodSelect.value === 'mixed_cash_instapay' || methodSelect.value === 'mixed_cash_vodafone')) {
            const cashInput = document.getElementById('paid_cash_input');
            const secondaryInput = document.getElementById('paid_secondary_input');
            
            let paidCash = parseInt(cashInput.value) || 0;
            let paidSecondary = parseInt(secondaryInput.value) || 0;
            
            paidInput.value = paidCash + paidSecondary;
        }

        let paid = parseInt(paidInput.value) || 0;
        let due = Math.max(0, netPayable - paid);
        let change = Math.max(0, paid - netPayable);

        // Update label displays
        document.getElementById('lbl-subtotal').textContent = subtotal + ' ج.م';
        document.getElementById('lbl-discount').textContent = invDiscount + ' ج.م';
        document.getElementById('lbl-total').textContent = netPayable + ' ج.م';
        document.getElementById('lbl-due').textContent = due + ' ج.م';
        document.getElementById('lbl-change').textContent = change + ' ج.م';
    }

    // Adjust cart quantity with buttons (+ / -)
    function adjustQty(index, delta) {
        let current = parseFloat(cart[index].qty) || 0;
        let newQty = parseFloat((current + delta).toFixed(3));
        if (newQty < 0.01) newQty = 0.01;
        cart[index].qty = newQty;
        renderCart();
    }

    // Adjust grams on row with buttons (+ / -)
    function adjustGramRow(btn, delta) {
        const input = btn.closest('div').querySelector('input[name="mix_grams[]"]');
        let val = parseFloat(input.value) || 0;
        val += delta;
        if (val < 0) val = 0;
        input.value = val > 0 ? parseFloat(val.toFixed(2)) : '';
        calculateSuggestedMixPrice();
    }

    function findFormulaDefault(bottleId, perfumeId) {
        return formulaDefaults.find(d =>
            parseInt(d.bottle_product_id) === bottleId &&
            parseInt(d.perfume_product_id) === perfumeId
        );
    }

    function formatMixGrams(value) {
        const rounded = Math.round(value * 10) / 10;
        return String(rounded);
    }

    function updateMixDefaultNote(linkedCount, unlinkedCount) {
        const note = document.getElementById('mix-default-note');
        if (!note) return;

        const messages = [];
        if (linkedCount > 1) {
            messages.push('تم توزيع الجرامات على ' + linkedCount + ' عطور ومتوسط السعر محسوب من الأسعار المربوطة.');
        }
        if (unlinkedCount > 0) {
            messages.push('بعض العطور غير مربوطة بهذه الزجاجة.');
        }

        note.textContent = messages.join(' ');
        note.style.color = unlinkedCount > 0 ? 'var(--warning)' : 'var(--muted)';
    }

    function getSelectedMixRowsWithDefaults() {
        const mixBottleSelect = document.getElementById('mix_bottle_id');
        const isWithoutBottle = mixBottleSelect?.value === 'no_bottle';
        const bottleId = isWithoutBottle ? -1 : (parseInt(mixBottleSelect?.value) || 0);
        const linked = [];
        const unlinked = [];

        if (bottleId === 0) {
            return { bottleId, linked, unlinked };
        }

        document.querySelectorAll('.mix-perfume-row').forEach(row => {
            const perfumeSelect = row.querySelector('select[name="mix_perfume_id[]"]');
            const gramsInput = row.querySelector('input[name="mix_grams[]"]');
            const perfumeId = parseInt(perfumeSelect?.value) || 0;
            if (perfumeId <= 0) return;

            const def = (bottleId === -1) ? null : findFormulaDefault(bottleId, perfumeId);
            const item = { row, perfumeSelect, gramsInput, perfumeId, def };
            if (def) {
                linked.push(item);
            } else {
                unlinked.push(item);
            }
        });

        return { bottleId, linked, unlinked };
    }

    function updateMixAverageDefaults(forceSingleDefault = false) {
        const { bottleId, linked, unlinked } = getSelectedMixRowsWithDefaults();
        const linkedCount = linked.length;

        if (linkedCount > 1) {
            linked.forEach(item => {
                const defaultGrams = parseFloat(item.def.default_grams) || 0;
                item.gramsInput.value = defaultGrams > 0 ? formatMixGrams(defaultGrams / linkedCount) : '';
            });
        } else if (linkedCount === 1) {
            const item = linked[0];
            const currentGrams = parseFloat(item.gramsInput.value) || 0;
            const defaultGrams = parseFloat(item.def.default_grams) || 0;
            if (defaultGrams > 0 && (forceSingleDefault || currentGrams <= 0)) {
                item.gramsInput.value = formatMixGrams(defaultGrams);
            }
        }

        const isWithoutBottle = document.getElementById('mix_bottle_id')?.value === 'no_bottle';
        updateMixDefaultNote(linkedCount, isWithoutBottle ? 0 : unlinked.length);
    }

    // When a perfume option changes or bottle size changes
    function onPerfumeOrBottleChange(select) {
        updateMixAverageDefaults(true);
        calculateSuggestedMixPrice();
    }

    // Apply default grams to all rows when bottle size changes
    function applyDefaultsToAllRows() {
        updateMixAverageDefaults(true);
    }

    function markMixPriceManual() {
        const mixPriceInput = document.getElementById('mix_sale_price');
        if (mixPriceInput) {
            mixPriceInput.dataset.manual = '1';
        }
    }

    function resetMixPriceManual() {
        const mixPriceInput = document.getElementById('mix_sale_price');
        if (mixPriceInput) {
            mixPriceInput.dataset.manual = '0';
        }
    }

    let currentMixProducts = null;

    function formatPosMoney(value) {
        const amount = parseFloat(value) || 0;
        const parts = amount.toFixed(2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.join('.') + ' ج.م';
    }

    function getSellableMixProducts(products, type) {
        return (products || []).filter(p =>
            p.type === type &&
            String(p.stock_initialized || 0) === '1' &&
            parseFloat(p.branch_stock || 0) > 0
        );
    }

    function buildMixBottleOptions(products) {
        const options = [
            '<option value="">-- اختر الزجاجة --</option>',
            '<option value="no_bottle" data-price="0" data-size="0">بدون زجاجة (0.00 ج.م)</option>'
        ];
        getSellableMixProducts(products, 'bottle').forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.setAttribute('data-price', p.sale_price || 0);
            opt.setAttribute('data-size', p.size_ml || '');
            opt.textContent = `${p.name || ''} (${p.size_ml || '-'}ml) - (${formatPosMoney(p.sale_price)})`;
            options.push(opt.outerHTML);
        });
        return options.join('');
    }

    function buildMixPerfumeOptions(products) {
        const options = ['<option value="">-- اختر الزيت العطري --</option>'];
        getSellableMixProducts(products, 'perfume_gram').forEach(p => {
            const pricePerGram = p.price_per_gram ?? p.sale_price ?? 0;
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.setAttribute('data-price', pricePerGram);
            opt.setAttribute('data-family', p.perfume_family || '');
            opt.setAttribute('data-grade', p.quality_grade || '');
            opt.textContent = `${p.name || ''} (${p.quality_grade || '-'}) - (${formatPosMoney(pricePerGram)}/جم)`;
            options.push(opt.outerHTML);
        });
        return options.join('');
    }

    function refreshSearchableSelect(select) {
        if (!select) return;
        if (select.dataset.searchableInitialized) {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        } else if (typeof makeSelectSearchable === 'function') {
            makeSelectSearchable(select);
        }
    }

    function updateMixProductDropdowns(products) {
        currentMixProducts = products || [];
        const mixBottleSelect = document.getElementById('mix_bottle_id');
        if (mixBottleSelect) {
            const currentBottleId = mixBottleSelect.value;
            mixBottleSelect.innerHTML = buildMixBottleOptions(products);
            mixBottleSelect.value = Array.from(mixBottleSelect.options).some(opt => opt.value === currentBottleId) ? currentBottleId : '';
            refreshSearchableSelect(mixBottleSelect);
        }

        const savedBottleSelect = document.getElementById('saved_recipe_bottle_id');
        if (savedBottleSelect) {
            const currentSavedBottleId = savedBottleSelect.value;
            const bottleOptions = [
                '<option value="default">الزجاجة الافتراضية للتركيبة</option>',
                '<option value="no_bottle" data-price="0" data-size="0">بدون زجاجة (0.00 ج.م)</option>'
            ];
            getSellableMixProducts(products, 'bottle').forEach(p => {
                bottleOptions.push(`<option value="${p.id}" data-price="${p.sale_price || 0}" data-size="${p.size_ml || ''}">${p.name || ''} (${p.size_ml || '-'}ml) - (${formatPosMoney(p.sale_price)})</option>`);
            });
            savedBottleSelect.innerHTML = bottleOptions.join('');
            savedBottleSelect.value = Array.from(savedBottleSelect.options).some(opt => opt.value === currentSavedBottleId) ? currentSavedBottleId : 'default';
            refreshSearchableSelect(savedBottleSelect);
        }

        const perfumeOptions = buildMixPerfumeOptions(products);
        document.querySelectorAll('select[name="mix_perfume_id[]"]').forEach(select => {
            const currentPerfumeId = select.value;
            select.innerHTML = perfumeOptions;
            select.value = Array.from(select.options).some(opt => opt.value === currentPerfumeId) ? currentPerfumeId : '';
            refreshSearchableSelect(select);
        });

        resetMixPriceManual();
        updateMixAverageDefaults();
        calculateSuggestedMixPrice();
    }

    // Calculate the suggested total price of the custom mix
    function calculateSuggestedMixPrice() {
        const mixBottleSelect = document.getElementById('mix_bottle_id');
        const mixPriceInput = document.getElementById('mix_sale_price');

        if (!mixBottleSelect || mixBottleSelect.value === '') {
            if (mixPriceInput && mixPriceInput.dataset.manual !== '1') mixPriceInput.value = '0';
            updateMixDefaultNote(0, 0);
            recalculateTotals();
            return;
        }

        const isWithoutBottle = mixBottleSelect.value === 'no_bottle';

        if (isWithoutBottle) {
            if (mixPriceInput && mixPriceInput.dataset.manual !== '1') {
                mixPriceInput.value = '0';
                mixPriceInput.dataset.defaultPrice = '0';
            }
            updateMixDefaultNote(0, 0);
            recalculateTotals();
            return;
        }

        const bottleId = parseInt(mixBottleSelect.value) || 0;
        const optionBottle = mixBottleSelect.options[mixBottleSelect.selectedIndex];
        const bottlePrice = parseInt(optionBottle.getAttribute('data-price')) || 0;

        const { linked, unlinked } = getSelectedMixRowsWithDefaults();
        updateMixDefaultNote(linked.length, unlinked.length);

        const linkedPrices = linked
            .map(item => parseFloat(item.def.price) || 0)
            .filter(price => price > 0);

        if (linkedPrices.length > 0) {
            // أخذ أعلى سعر من العطور المربوطة (بدل من المتوسط)
            const defaultFormulaPrice = Math.max(...linkedPrices);
            if (mixPriceInput && mixPriceInput.dataset.manual !== '1') {
                mixPriceInput.value = Math.round(defaultFormulaPrice);
                mixPriceInput.dataset.defaultPrice = Math.round(defaultFormulaPrice);
            }
            recalculateTotals();
            return;
        }

        let total = bottlePrice;
        let computedTotal = 0;
        document.querySelectorAll('.mix-perfume-row').forEach(row => {
            const perfumeSelect = row.querySelector('select[name="mix_perfume_id[]"]');
            const gramsInput = row.querySelector('input[name="mix_grams[]"]');

            if (perfumeSelect && perfumeSelect.value !== '') {
                const optPerfume = perfumeSelect.options[perfumeSelect.selectedIndex];
                const pricePerGram = parseFloat(optPerfume.getAttribute('data-price')) || 0;
                const grams = parseFloat(gramsInput.value) || 0;
                total += pricePerGram * grams;
                computedTotal += pricePerGram * grams;
            }
        });
        computedTotal += bottlePrice;

        if (mixPriceInput && mixPriceInput.dataset.manual !== '1') {
            mixPriceInput.value = Math.round(total);
            mixPriceInput.dataset.defaultPrice = Math.round(total);
        }
        recalculateTotals();
    }

    // Add instant mix to cart array
    function addMixToCart() {
        const mixBottleSelect = document.getElementById('mix_bottle_id');
        const bottleId = mixBottleSelect.value;
        if (!bottleId) return alert('الرجاء اختيار زجاجة للتركيبة.');

        const isWithoutBottle = (bottleId === 'no_bottle' || bottleId === 'pump_only');
        const isPumpOnly = (bottleId === 'pump_only');

        const bottleOption = mixBottleSelect.options[mixBottleSelect.selectedIndex];
        let bottleName = bottleOption.textContent.split(' (')[0];
        if (bottleId === 'no_bottle') bottleName = 'بدون زجاجة';
        if (bottleId === 'pump_only') bottleName = 'بمبة';
        
        const bottlePrice = isWithoutBottle ? 0 : (parseInt(bottleOption.getAttribute('data-price')) || 0);
        const mixPriceInput = document.getElementById('mix_sale_price');
        const salePrice = parseInt(mixPriceInput.value) || 0;
        
        if (isWithoutBottle) {
            if (salePrice < 0) return alert('الرجاء إدخال سعر بيع صحيح للتركيبة.');
        } else {
            if (salePrice <= 0) return alert('الرجاء إدخال سعر بيع صحيح للتركيبة.');
        }

        // تخزين السعر المحتسب (default) للمقارنة لاحقاً
        const defaultPrice = parseInt(mixPriceInput.dataset.defaultPrice) || salePrice;
        const wasManual = mixPriceInput.dataset.manual === '1';

        const perfumeRows = document.querySelectorAll('.mix-perfume-row');
        let components = [];
        let hasOil = false;
        let descriptionParts = isWithoutBottle ? [] : [bottleName];
        if (isPumpOnly) {
            descriptionParts.push('بمبة');
        }

        perfumeRows.forEach(row => {
            const perfumeSelect = row.querySelector('select[name="mix_perfume_id[]"]');
            const gramsInput = row.querySelector('input[name="mix_grams[]"]');
            if (perfumeSelect && perfumeSelect.value !== '') {
                const perfumeOption = perfumeSelect.options[perfumeSelect.selectedIndex];
                const perfumeId = perfumeSelect.value;
                const perfumeName = perfumeOption.textContent.split(' (')[0];
                const grams = parseFloat(gramsInput.value) || 0;
                if (grams > 0) {
                    hasOil = true;
                    
                    const defaultDef = isWithoutBottle ? null : formulaDefaults.find(d => 
                        parseInt(d.bottle_product_id) === parseInt(bottleId) && 
                        parseInt(d.perfume_product_id) === parseInt(perfumeId)
                    );
                    const defaultGrams = defaultDef ? parseFloat(defaultDef.default_grams) : 0;
                    
                    components.push({
                        product_id: parseInt(perfumeId),
                        name: perfumeName,
                        grams: grams,
                        default_grams: defaultGrams
                    });
                    descriptionParts.push(perfumeName + ' ' + grams + 'جم');
                }
            }
        });

        if (!hasOil) return alert('الرجاء إضافة زيت عطري واحد على الأقل مع الجرامات.');

        const mixItem = {
            type: 'custom_recipe',
            id: 'mix_' + Date.now(),
            name: '🧪 تركيبة فورية: ' + (isWithoutBottle && !isPumpOnly ? 'بدون زجاجة + ' : '') + descriptionParts.join(' + '),
            qty: 1,
            price: salePrice,
            defaultPrice: defaultPrice,
            priceModified: wasManual && salePrice !== defaultPrice,
            discountType: '',
            discountValue: 0,
            bottle_id: isWithoutBottle ? 0 : parseInt(bottleId),
            without_bottle: isWithoutBottle,
            bottle_name: bottleName,
            bottle_price: bottlePrice,
            components: components
        };

        cart.push(mixItem);
        renderCart();
        resetMixBuilder();
    }

    function resetMixBuilder() {
        document.getElementById('mix_bottle_id').value = '';
        const bottleTrigger = document.getElementById('mix_bottle_id').closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
        if (bottleTrigger) bottleTrigger.textContent = '-- اختر الزجاجة --';

        document.getElementById('mix_sale_price').value = '';
        resetMixPriceManual();

        const container = document.getElementById('mix-perfumes-container');
        const rows = container.querySelectorAll('.mix-perfume-row');
        for (let i = 1; i < rows.length; i++) {
            rows[i].remove();
        }
        const firstRow = container.querySelector('.mix-perfume-row');
        if (firstRow) {
            firstRow.querySelector('select').value = '';
            const firstRowTrigger = firstRow.querySelector('select').closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
            if (firstRowTrigger) firstRowTrigger.textContent = '-- اختر الزيت العطري --';

            firstRow.querySelector('input[name="mix_grams[]"]').value = '';
        }

        updateMixDefaultNote(0, 0);
        recalculateTotals();
    }

    // Clear cart manually
    function clearCart() {
        if (confirm('هل أنت متأكد من تفريغ السلة؟')) {
            cart.length = 0; // modify array in place to preserve reference
            saveCurrentTabState();
            renderCart();
        }
    }

    // Instant Mix: Dynamic perfume oil rows
    function addOilRow() {
        const container = document.getElementById('mix-perfumes-container');
        const row = document.createElement('div');
        row.className = 'line-grid two mix-perfume-row';

        row.innerHTML = `
        <select name="mix_perfume_id[]" onchange="onPerfumeOrBottleChange(this)">
            <option value="">-- اختر الزيت العطري --</option>
            <?php foreach ($perfumes as $p): ?>
                <option value="<?= e($p['id']) ?>" 
                        data-price="<?= e($p['price_per_gram'] ?? $p['sale_price']) ?>"
                        data-family="<?= e($p['perfume_family']) ?>"
                        data-grade="<?= e($p['quality_grade']) ?>">
                    <?= e($p['name']) ?> (<?= e($p['quality_grade'] ?: '-') ?>) - (<?= money($p['price_per_gram'] ?? $p['sale_price']) ?>/جم)
                </option>
            <?php endforeach; ?>
        </select>
        <div class="mix-gram-stepper">
            <button type="button" class="stepper-btn" onclick="adjustGramRow(this, -1)">–</button>
            <input name="mix_grams[]" type="number" step="any" min="0" placeholder="جم" value="" oninput="calculateSuggestedMixPrice()" class="stepper-input">
            <button type="button" class="stepper-btn" onclick="adjustGramRow(this, 1)">+</button>
        </div>
        <button type="button" class="btn small danger" onclick="removeOilRow(this)" style="padding: 6px 12px; border-radius: 8px;">حذف</button>
    `;
        container.appendChild(row);
        const select = row.querySelector('select');
        if (currentMixProducts) {
            select.innerHTML = buildMixPerfumeOptions(currentMixProducts);
        }
        makeSelectSearchable(select);
        updateMixAverageDefaults();
        calculateSuggestedMixPrice();
    }

    function removeOilRow(btn) {
        btn.closest('.mix-perfume-row').remove();
        updateMixAverageDefaults(true);
        calculateSuggestedMixPrice();
    }

    function selectAllOils() {
        const container = document.getElementById('mix-perfumes-container');
        const rows = container.querySelectorAll('.mix-perfume-row');
        
        if (rows.length === 0) {
            alert('لا توجد صفوف زيوت لتحديدها.');
            return;
        }

        const allSelected = Array.from(rows).every(row => {
            const select = row.querySelector('select[name="mix_perfume_id[]"]');
            return select && select.value !== '';
        });

        rows.forEach(row => {
            const select = row.querySelector('select[name="mix_perfume_id[]"]');
            if (select) {
                if (allSelected) {
                    select.value = '';
                } else {
                    if (select.options.length > 1) {
                        select.value = select.options[1].value;
                    }
                }
            }
        });

        updateMixAverageDefaults(true);
        calculateSuggestedMixPrice();
    }

    // Wire up bottle change event listener
    document.getElementById('mix_bottle_id').addEventListener('change', function() {
        resetMixPriceManual();
        applyDefaultsToAllRows();
        calculateSuggestedMixPrice();
    });

    // Customer Modal controls
    function openCustomerModal() {
        document.getElementById('customer-modal').classList.add('open');
        document.getElementById('new-cust-name').focus();
    }

    function closeCustomerModal() {
        document.getElementById('customer-modal').classList.remove('open');
        document.getElementById('modal-error').style.display = 'none';
        document.getElementById('new-cust-name').value = '';
        document.getElementById('new-cust-phone').value = '';
        document.getElementById('new-cust-birthdate').value = '';
    }

    // Quick Add Customer via AJAX/Fetch
    function saveQuickCustomer() {
        const name = document.getElementById('new-cust-name').value.trim();
        const phone = document.getElementById('new-cust-phone').value.trim();
        const birthdate = document.getElementById('new-cust-birthdate').value.trim();
        const errorDiv = document.getElementById('modal-error');

        if (!name) {
            errorDiv.textContent = 'الرجاء إدخال اسم العميل بالكامل.';
            errorDiv.style.display = 'block';
            return;
        }

        errorDiv.style.display = 'none';

        const params = new URLSearchParams();
        params.append('csrf', document.querySelector('input[name="csrf"]').value);
        params.append('name', name);
        params.append('phone', phone);
        const locationField = document.querySelector('select[name="location_id"]:not([disabled])') || document.querySelector('input[name="location_id"]');
        if (locationField && locationField.value) {
            params.append('location_id', locationField.value);
        }
        if (birthdate) {
            params.append('birthdate', birthdate);
        }

        const targetTabId = activeTabId; // Capture tab ID before async fetch
        
        fetch('index.php?r=quick_add_customer', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: params
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const select = document.getElementById('customer_id_select');
                    const opt = document.createElement('option');
                    opt.value = data.id;
                    opt.textContent = data.name + (data.phone ? ' - ' + data.phone : ' - بدون هاتف');
                    select.appendChild(opt);
                    
                    // Assign customer strictly to the tab that initiated the add
                    const tab = posTabs.find(t => t.id === targetTabId);
                    if (tab) tab.customer_id = data.id;

                    // Only visually update dropdown if the initiator tab is still active
                    if (activeTabId === targetTabId) {
                        opt.selected = true;
                        const trigger = select.closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
                        if (trigger) trigger.textContent = opt.textContent;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    closeCustomerModal();
                    alert(data.message || 'تمت إضافة العميل وتحديده بنجاح.');
                } else {
                    errorDiv.textContent = data.message || 'حدث خطأ أثناء حفظ العميل.';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(err => {
                console.error(err);
                errorDiv.textContent = 'حدث خطأ في الاتصال بالخادم.';
                errorDiv.style.display = 'block';
            });
    }

    // Form validation before submit
    document.getElementById('pos-main-form').addEventListener('submit', function (e) {
        if (cart.length === 0) {
            e.preventDefault();
            alert('سلة المبيعات فارغة! أضف منتجات أو تركيبات قبل إغلاق الفاتورة.');
            return;
        }

        sessionStorage.setItem('pos_invoice_submit_pending', '1');
    });

    if (sessionStorage.getItem('pos_invoice_submit_pending') === '1') {
        sessionStorage.removeItem('pos_invoice_submit_pending');
        const urlParams = new URLSearchParams(window.location.search);
        if (posTabs.some(t => t.cart.length > 0) && !urlParams.has('print_invoice')) {
            alert('لم يتم تقفيل الفاتورة بالكامل، تم الاحتفاظ بالبيانات.');
        }
    }

    // Printable invoice trigger
    function openPrintableInvoice() {
        const params = new URLSearchParams(window.location.search);
        const inv = params.get('print_invoice');
        if (inv) {
            if (history.replaceState) {
                const cleanUrl = window.location.pathname + window.location.search.replace(/([&?])?print_invoice=[^&]*/, '').replace(/\?&/, '?').replace(/\?$/, '');
                history.replaceState({}, document.title, cleanUrl);
            }
            const url = 'index.php?r=invoice_view&id=' + encodeURIComponent(inv) + '&print=1';
            
            // Create hidden iframe to trigger silent printing without opening new tabs
            let printFrame = document.getElementById('pos-print-iframe');
            if (!printFrame) {
                printFrame = document.createElement('iframe');
                printFrame.id = 'pos-print-iframe';
                printFrame.style.position = 'fixed';
                printFrame.style.bottom = '100%';
                printFrame.style.right = '100%';
                printFrame.style.width = '1px';
                printFrame.style.height = '1px';
                printFrame.style.opacity = '0.01';
                printFrame.style.border = 'none';
                document.body.appendChild(printFrame);
            }
            printFrame.src = url;

            const clearFlag = params.get('clear_cart');
            if (clearFlag === '1') {
                try {
                    closeTab(activeTabId, true);
                } catch (e) {
                    console.error('Failed to clear POS cart', e);
                }
            }
        }
    }

    // Initialize dropdowns
    document.addEventListener('DOMContentLoaded', () => {
        const addProdSelect = document.getElementById('add-prod-select');
        if (addProdSelect) makeSelectSearchable(addProdSelect);

        const addRecipeSelect = document.getElementById('add-recipe-select');
        if (addRecipeSelect) makeSelectSearchable(addRecipeSelect);

        const customerSelect = document.getElementById('customer_id_select');
        if (customerSelect) makeSelectSearchable(customerSelect);

        const mixBottleSelect = document.getElementById('mix_bottle_id');
        if (mixBottleSelect) makeSelectSearchable(mixBottleSelect);

        document.querySelectorAll('select[name="mix_perfume_id[]"]').forEach(sel => {
            makeSelectSearchable(sel);
        });
    });

    setTimeout(() => {
        const addProdSelect = document.getElementById('add-prod-select');
        if (addProdSelect && !addProdSelect.dataset.searchableInitialized) {
            makeSelectSearchable(addProdSelect);
            const addRecipeSelect = document.getElementById('add-recipe-select');
            if (addRecipeSelect) makeSelectSearchable(addRecipeSelect);
            const customerSelect = document.getElementById('customer_id_select');
            if (customerSelect) makeSelectSearchable(customerSelect);
            const mixBottleSelect = document.getElementById('mix_bottle_id');
            if (mixBottleSelect) makeSelectSearchable(mixBottleSelect);
            document.querySelectorAll('select[name="mix_perfume_id[]"]').forEach(sel => {
                makeSelectSearchable(sel);
            });
        }
    }, 100);
</script>

<script>
// ========== Focus-Free Barcode Scanner Logic ==========
const barcodeInput = document.getElementById('barcode-scanner-input');
const barcodeFeedback = document.getElementById('barcode-feedback');
const barcodeLookupBtn = document.getElementById('barcode-lookup-btn');

// Automatically focus barcode input when clicking anywhere on the page
document.addEventListener('click', function(e) {
    const isInteractive = e.target.closest('input, select, textarea, button, a, [role="button"], .custom-select-trigger, .custom-select-options-box, .custom-select-item, .close-modal, .modal-content');
    if (!isInteractive) {
        if (barcodeInput) {
            barcodeInput.focus();
        }
    }
});

// Capture keystrokes from physical scanner globally in the background
let barcodeBuffer = '';
let lastKeyTime = Date.now();

document.addEventListener('keydown', function(e) {
    const activeEl = document.activeElement;
    const isInputField = activeEl && (
        activeEl.tagName === 'INPUT' ||
        activeEl.tagName === 'TEXTAREA' ||
        activeEl.tagName === 'SELECT' ||
        activeEl.isContentEditable
    );
    
    if (!isInputField) {
        const currentTime = Date.now();
        if (currentTime - lastKeyTime > 200) {
            barcodeBuffer = '';
        }
        lastKeyTime = currentTime;

        if (e.key.length === 1) {
            barcodeBuffer += e.key;
        } else if (e.key === 'Enter') {
            if (barcodeBuffer.trim().length > 0) {
                e.preventDefault();
                const barcode = barcodeBuffer.trim();
                barcodeBuffer = '';
                lookupBarcode(barcode);
            }
        }
    }
});

function lookupBarcode(barcode) {
    barcode = barcode.trim();
    if (!barcode) {
        barcodeFeedback.textContent = '❌ أدخل باركود';
        barcodeFeedback.style.color = 'var(--danger)';
        return;
    }
    
    barcodeFeedback.textContent = '⏳ جاري البحث...';
    barcodeFeedback.style.color = 'var(--muted)';
    
    // Get current selected location for stock check
    const locationSelect = document.querySelector('select[name="location_id"]') ||
                           document.querySelector('input[name="location_id"]');
    const currentLocationId = locationSelect ? locationSelect.value : '';

    fetch('index.php?r=barcode_lookup&barcode=' + encodeURIComponent(barcode) + (currentLocationId ? '&location_id=' + currentLocationId : ''))
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.kind === 'offer' && data.offer) {
                const off = data.offer;
                const existingIndex = cart.findIndex(item => String(item.id) === String(off.id) && item.type === 'offer');
                if (existingIndex > -1) {
                    cart[existingIndex].qty += 1;
                } else {
                    let defaultCustomItems = [];
                    if (off.items && Array.isArray(off.items)) {
                        off.items.forEach(it => {
                            if (it.item_type === 'product') {
                                defaultCustomItems.push({
                                    item_type: 'product',
                                    product_id: parseInt(it.product_id),
                                    product_name: it.product_name,
                                    quantity: parseFloat(it.quantity) || 1
                                });
                            } else {
                                const oils = (it.components || []).map(c => ({
                                    perfume_id: parseInt(c.perfume_product_id),
                                    perfume_name: c.perfume_name,
                                    grams: parseFloat(c.grams) || 0,
                                    price_per_gram: parseFloat(c.price_per_gram || c.sale_price || 0)
                                }));
                                defaultCustomItems.push({
                                    item_type: 'recipe',
                                    recipe_name: it.recipe_name || 'تركيبة خاصة',
                                    bottle_id: it.bottle_product_id ? parseInt(it.bottle_product_id) : 'no_bottle',
                                    quantity: parseFloat(it.quantity) || 1,
                                    oils: oils
                                });
                            }
                        });
                    }
                    cart.push({
                        type: 'offer',
                        id: String(off.id),
                        name: '🎁 ' + off.name,
                        qty: 1,
                        price: parseFloat(off.price_after) || 0,
                        discountType: '',
                        discountValue: 0,
                        custom_data: { items: defaultCustomItems }
                    });
                }
                renderCart();
                barcodeFeedback.textContent = '✅ تمت إضافة العرض للسلة: ' + off.name;
                barcodeFeedback.style.color = 'var(--success)';
                if (barcodeInput) { barcodeInput.value = ''; barcodeInput.focus(); }
                return;
            }

            if (data.status === 'success' && data.product) {
                const p = data.product;
                
                if (p.type === 'bottle') {
                    // زجاجة → الانتقال لتاب التركيبة الفورية وتحديد الزجاجة
                    switchAddTab('mix');
                    const mixBottleSelect = document.getElementById('mix_bottle_id');
                    if (mixBottleSelect) {
                        let found = false;
                        for (let i = 0; i < mixBottleSelect.options.length; i++) {
                            if (mixBottleSelect.options[i].value == p.id) {
                                mixBottleSelect.selectedIndex = i;
                                mixBottleSelect.dispatchEvent(new Event('change'));
                                const trigger = mixBottleSelect.closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
                                if (trigger) trigger.textContent = mixBottleSelect.options[i].textContent;
                                found = true;
                                break;
                            }
                        }
                        barcodeFeedback.textContent = found
                            ? '✅ تم اختيار الزجاجة: ' + p.name
                            : '⚠️ الزجاجة غير مجودة في قائمة التركيبة';
                        barcodeFeedback.style.color = found ? 'var(--success)' : 'var(--warning)';
                    }

                } else if (p.type === 'perfume_gram') {
                    // زيت عطري → الانتقال لتاب التركيبة الفورية وتحديده في أول صف فارغ
                    switchAddTab('mix');
                    const rows = document.querySelectorAll('.mix-perfume-row');
                    let targetRow = null;
                    for (let row of rows) {
                        const sel = row.querySelector('select[name="mix_perfume_id[]"]');
                        if (sel && sel.value === '') { targetRow = row; break; }
                    }
                    if (!targetRow) {
                        addOilRow();
                        const newRows = document.querySelectorAll('.mix-perfume-row');
                        targetRow = newRows[newRows.length - 1];
                    }
                    const sel = targetRow?.querySelector('select[name="mix_perfume_id[]"]');
                    let found = false;
                    if (sel) {
                        for (let i = 0; i < sel.options.length; i++) {
                            if (sel.options[i].value == p.id) {
                                sel.selectedIndex = i;
                                sel.dispatchEvent(new Event('change'));
                                const trigger = sel.closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
                                if (trigger) trigger.textContent = sel.options[i].textContent;
                                found = true;
                                break;
                            }
                        }
                    }
                    barcodeFeedback.textContent = found
                        ? '✅ تم إضافة الزيت: ' + p.name
                        : '⚠️ الزيت غير موجود في قائمة التركيبة';
                    barcodeFeedback.style.color = found ? 'var(--success)' : 'var(--warning)';

                } else if (p.type === 'recipe') {
                    // تركيبة جاهزة → أضفها مباشرة للسلة
                    const recipeSelect = document.getElementById('add-recipe-select');
                    let recipeName = p.name;
                    let recipePrice = Math.round(p.sale_price) || 0;
                    for (let i = 0; i < recipeSelect.options.length; i++) {
                        if (recipeSelect.options[i].value == p.id) {
                            recipeName = recipeSelect.options[i].getAttribute('data-name') || p.name;
                            recipePrice = parseFloat(recipeSelect.options[i].getAttribute('data-price')) || recipePrice;
                            break;
                        }
                    }
                    const existingRecipe = cart.findIndex(item => String(item.id) === String(p.id) && item.type === 'recipe');
                    if (existingRecipe > -1) {
                        cart[existingRecipe].qty += 1;
                    } else {
                        cart.push({ type: 'recipe', id: String(p.id), name: recipeName, qty: 1, price: recipePrice, discountType: '', discountValue: 0 });
                    }
                    renderCart();
                    barcodeFeedback.textContent = '✅ تمت الإضافة للسلة: ' + recipeName;
                    barcodeFeedback.style.color = 'var(--success)';

                } else {
                    // منتج مباشر (fixed وغيره) → تحقق من المخزون أولاً
                    const branchStock = p.branch_stock !== undefined && p.branch_stock !== null ? parseFloat(p.branch_stock) : null;
                    
                    if (branchStock !== null && branchStock <= 0) {
                        // منتج نفد من المخزون
                        if (!confirm('⚠️ تحذير: "' + p.name + '" نفد من مخزون الفرع الحالي!\nهل تريد إضافته للسلة على مسؤوليتك؟')) {
                            barcodeInput.value = '';
                            barcodeInput.focus();
                            barcodeFeedback.textContent = '⚠️ لم تتم الإضافة - مخزون فارغ';
                            barcodeFeedback.style.color = 'var(--warning)';
                            return;
                        }
                    }

                    const existingProd = cart.findIndex(item => String(item.id) === String(p.id) && item.type === 'product' && item.discountType === '' && item.discountValue === 0);
                    if (existingProd > -1) {
                        cart[existingProd].qty += 1;
                    } else {
                        cart.push({
                            type: 'product',
                            id: String(p.id),
                            name: p.name,
                            qty: 1,
                            price: Math.round(p.sale_price) || 0,
                            discountType: '',
                            discountValue: 0
                        });
                    }
                    renderCart();
                    let stockInfo = '';
                    if (branchStock !== null) {
                        stockInfo = branchStock <= 0 ? ' ⚠️ مخزون فارغ!' : (branchStock < 3 ? ' ⚡ (متبقي ' + branchStock + ')' : ' 📦 (' + branchStock + ' متاح)');
                    }
                    barcodeFeedback.textContent = '✅ تمت الإضافة للسلة: ' + p.name + stockInfo;
                    barcodeFeedback.style.color = branchStock !== null && branchStock <= 0 ? 'var(--warning)' : 'var(--success)';
                }
                
                barcodeInput.value = '';
                barcodeInput.focus();
            } else {
                barcodeFeedback.textContent = '❌ ' + (data.message || 'غير موجود');
                barcodeFeedback.style.color = 'var(--danger)';
                barcodeInput.style.borderColor = 'var(--danger)';
                setTimeout(() => {
                    barcodeInput.style.borderColor = 'var(--line)';
                    barcodeInput.focus();
                    barcodeInput.select();
                }, 300);
            }
        })
        .catch(err => {
            console.error(err);
            barcodeFeedback.textContent = '❌ خطأ في الاتصال';
            barcodeFeedback.style.color = 'var(--danger)';
        });
}

barcodeInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        lookupBarcode(this.value);
    }
});

barcodeLookupBtn.addEventListener('click', function() {
    lookupBarcode(barcodeInput.value);
});

// Handle location change and reload products
const locationSelect = document.querySelector('select[name="location_id"]:not([disabled])');
if (locationSelect) {
    locationSelect.addEventListener('change', function() {
        const newLocationId = this.value;
        if (!newLocationId) return;
        
        // Fetch products for the new location
        fetch('index.php?r=pos_products&location_id=' + encodeURIComponent(newLocationId))
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update products in select dropdowns
                    const prodSelect = document.getElementById('add-prod-select');
                    const recipeSelect = document.getElementById('add-recipe-select');
                    
                    if (prodSelect) {
                        prodSelect.innerHTML = '<option value="">-- اختر صنف مباشر --</option>';
                        data.products.forEach(p => {
                            const option = document.createElement('option');
                            option.value = p.id;
                            option.setAttribute('data-name', p.name);
                            option.setAttribute('data-price', p.sale_price);
                            option.setAttribute('data-stock', p.branch_stock || 0);
                            option.setAttribute('data-stock-init', p.stock_initialized || 0);
                            option.textContent = p.name;
                            prodSelect.appendChild(option);
                        });
                    }
                    
                    if (recipeSelect) {
                        recipeSelect.innerHTML = '<option value="">-- اختر تركيبة جاهزة --</option>';
                        data.recipes.forEach(r => {
                            const option = document.createElement('option');
                            option.value = r.id;
                            option.setAttribute('data-name', r.name);
                            option.setAttribute('data-price', r.default_sale_price);
                            option.textContent = r.name;
                            recipeSelect.appendChild(option);
                        });
                    }

                    updateMixProductDropdowns(data.products || []);
                }
            })
            .catch(err => console.error('خطأ في تحديث المنتجات:', err));
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => {
        if(barcodeInput) barcodeInput.focus();
    }, 300);
});
</script>

<script>
// ========== التنقل بالكيبورد بين حقول POS ==========
(function() {
    // قائمة الحقول التي يمكن التنقل بينها بالـ Enter (بالترتيب)
    const navFieldIds = [
        'barcode-scanner-input',
        'add-prod-qty',
        'add-prod-price',
        'add-prod-discount-value',
        'add-recipe-qty',
        'mix_sale_price',
        'paid-total-input',
        'paid-cash-input',
        'paid-instapay-input',
        'paid-vodafone-input',
    ];

    // إضافة tabindex لكل حقل
    navFieldIds.forEach((id, idx) => {
        const el = document.getElementById(id);
        if (el) el.setAttribute('tabindex', idx + 1);
    });

    // ====== Smart Enter Key Handling ======
    // 1) Enter in add-product panel → addProductToCart()
    ['add-prod-qty', 'add-prod-price', 'add-prod-discount-value'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addProductToCart();
                }
            });
        }
    });

    // 2) Enter in add-recipe panel → addRecipeToCart()
    const recipeQtyEl = document.getElementById('add-recipe-qty');
    if (recipeQtyEl) {
        recipeQtyEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); addRecipeToCart(); }
        });
    }

    // 3) Enter in mix panel (sale price) → addMixToCart()
    const mixPriceEl = document.getElementById('mix_sale_price');
    if (mixPriceEl) {
        mixPriceEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); addMixToCart(); }
        });
    }
    // Also Enter in mix grams fields
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && e.target.name === 'mix_grams[]') {
            e.preventDefault();
            addMixToCart();
        }
    });

    // 4) Enter in paid_total → submit form (close invoice)
    const paidTotalEl = document.getElementById('paid_total_input');
    if (paidTotalEl) {
        paidTotalEl.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const form = document.getElementById('pos-main-form');
                if (form) form.requestSubmit ? form.requestSubmit() : form.submit();
            }
        });
    }
    // Also handle split payment fields Enter → submit
    ['paid_secondary'].forEach(name => {
        document.querySelectorAll(`input[name="${name}"]`).forEach(el => {
            el.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const form = document.getElementById('pos-main-form');
                    if (form) form.requestSubmit ? form.requestSubmit() : form.submit();
                }
            });
        });
    });

    // 5) Prevent Enter from accidentally submitting the main POS form from other inputs
    document.getElementById('pos-main-form')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const tag = e.target.tagName;
            const type = e.target.type;
            if (tag === 'TEXTAREA') return;
            if (tag === 'BUTTON' || (tag === 'INPUT' && type === 'submit')) return;
            // If not handled above specifically, just prevent default submit
            e.preventDefault();
        }
    });
})();
</script>

<!-- ============================================================
     🔌 OFFLINE ENGINE — Barcode | Queue | Print | Sync
     ============================================================ -->
<script>
// ================================================================
// 1. OFFLINE BARCODE LOOKUP (محلي بدون نت)
// ================================================================
function lookupBarcodeOffline(barcode) {
    barcode = String(barcode).trim();
    if (!barcode) return null;
    const bc = barcode.toLowerCase();

    // 1. Check active offers
    const offer = (ALL_OFFERS_OFFLINE || []).find(o =>
        (o.barcode && String(o.barcode).toLowerCase() === bc)
    );
    if (offer) {
        return Object.assign({}, offer, { is_offer: true });
    }

    // 2. Check products
    return (ALL_PRODUCTS_OFFLINE || []).find(p =>
        (p.barcode && String(p.barcode).toLowerCase() === bc) ||
        (p.sku    && String(p.sku).toLowerCase()    === bc)
    ) || null;
}

// Override the existing lookupBarcode to fall back to offline when no network
const _origLookupBarcode = typeof lookupBarcode === 'function' ? lookupBarcode : null;
window.lookupBarcode = function(barcode) {
    if (navigator.onLine) {
        if (_origLookupBarcode) _origLookupBarcode(barcode);
        return;
    }
    // ===== OFFLINE PATH =====
    const barcodeInput    = document.getElementById('barcode-scanner-input');
    const barcodeFeedback = document.getElementById('barcode-feedback');

    barcode = String(barcode).trim();
    if (!barcode) {
        if (barcodeFeedback) { barcodeFeedback.textContent = '❌ أدخل باركود'; barcodeFeedback.style.color = 'var(--danger)'; }
        return;
    }

    const p = lookupBarcodeOffline(barcode);
    if (!p) {
        if (barcodeFeedback) { barcodeFeedback.textContent = '❌ الباركود غير موجود (أوفلاين)'; barcodeFeedback.style.color = 'var(--danger)'; }
        if (barcodeInput) { barcodeInput.value = ''; barcodeInput.focus(); }
        return;
    }

    if (p.is_offer) {
        const existingOffer = cart.findIndex(item => String(item.id) === String(p.id) && item.type === 'offer');
        if (existingOffer > -1) {
            cart[existingOffer].qty += 1;
        } else {
            cart.push({
                type: 'offer',
                id: String(p.id),
                name: '🎁 ' + p.name,
                qty: 1,
                price: parseFloat(p.price_after) || 0,
                discountType: '',
                discountValue: 0
            });
        }
        renderCart();
        if (barcodeFeedback) {
            barcodeFeedback.textContent = '✅ (أوفلاين) تم إضافة العرض: ' + p.name;
            barcodeFeedback.style.color = 'var(--success)';
        }
        if (barcodeInput) { barcodeInput.value = ''; barcodeInput.focus(); }
        return;
    }

    if (p.type === 'bottle') {
        switchAddTab('mix');
        const mixBottleSelect = document.getElementById('mix_bottle_id');
        if (mixBottleSelect) {
            for (let i = 0; i < mixBottleSelect.options.length; i++) {
                if (parseInt(mixBottleSelect.options[i].value) === p.id) {
                    mixBottleSelect.selectedIndex = i;
                    mixBottleSelect.dispatchEvent(new Event('change'));
                    const trigger = mixBottleSelect.closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
                    if (trigger) trigger.textContent = mixBottleSelect.options[i].textContent;
                    break;
                }
            }
        }
        if (barcodeFeedback) { barcodeFeedback.textContent = '✅ (أوفلاين) الزجاجة: ' + p.name; barcodeFeedback.style.color = 'var(--success)'; }
    } else if (p.type === 'perfume_gram') {
        switchAddTab('mix');
        const rows = document.querySelectorAll('.mix-perfume-row');
        let targetRow = null;
        for (let row of rows) { const sel = row.querySelector('select[name="mix_perfume_id[]"]'); if (sel && sel.value === '') { targetRow = row; break; } }
        if (!targetRow) { addOilRow(); const newRows = document.querySelectorAll('.mix-perfume-row'); targetRow = newRows[newRows.length - 1]; }
        const sel = targetRow?.querySelector('select[name="mix_perfume_id[]"]');
        if (sel) {
            for (let i = 0; i < sel.options.length; i++) {
                if (parseInt(sel.options[i].value) === p.id) {
                    sel.selectedIndex = i; sel.dispatchEvent(new Event('change'));
                    const trigger = sel.closest('.custom-select-wrapper')?.querySelector('.custom-select-trigger');
                    if (trigger) trigger.textContent = sel.options[i].textContent;
                    break;
                }
            }
        }
        if (barcodeFeedback) { barcodeFeedback.textContent = '✅ (أوفلاين) الزيت: ' + p.name; barcodeFeedback.style.color = 'var(--success)'; }
    } else if (p.type === 'recipe') {
        const existing = cart.findIndex(item => String(item.id) === String(p.id) && item.type === 'recipe');
        if (existing > -1) { cart[existing].qty += 1; }
        else { cart.push({ type: 'recipe', id: String(p.id), name: p.name, qty: 1, price: Math.round(p.sale_price) || 0, discountType: '', discountValue: 0 }); }
        renderCart();
        if (barcodeFeedback) { barcodeFeedback.textContent = '✅ (أوفلاين) تركيبة: ' + p.name; barcodeFeedback.style.color = 'var(--success)'; }
    } else {
        if (p.branch_stock !== null && p.branch_stock <= 0 && p.stock_initialized) {
            if (!confirm('⚠️ (أوفلاين) "' + p.name + '" نفد من المخزون!\nهل تريد إضافته على مسؤوليتك؟')) {
                if (barcodeInput) { barcodeInput.value = ''; barcodeInput.focus(); }
                return;
            }
        }
        const existing = cart.findIndex(item => String(item.id) === String(p.id) && item.type === 'product' && item.discountType === '' && item.discountValue === 0);
        if (existing > -1) { cart[existing].qty += 1; }
        else { cart.push({ type: 'product', id: String(p.id), name: p.name, qty: 1, price: Math.round(p.sale_price) || 0, discountType: '', discountValue: 0 }); }
        renderCart();
        if (barcodeFeedback) { barcodeFeedback.textContent = '✅ (أوفلاين) ' + p.name; barcodeFeedback.style.color = 'var(--success)'; }
    }
    if (barcodeInput) { barcodeInput.value = ''; barcodeInput.focus(); }
};

// ================================================================
// 2. OFFLINE INVOICE QUEUE (قائمة انتظار الفواتير في LocalStorage)
// ================================================================
const OFFLINE_QUEUE_KEY = 'pos_offline_queue';

function getOfflineQueue() {
    try { return JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]'); } catch(e) { return []; }
}

function saveOfflineQueue(queue) {
    localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    if (typeof window.updateOfflinePendingCount === 'function') window.updateOfflinePendingCount(queue.length);
}

function addToOfflineQueue(invoiceData) {
    const queue = getOfflineQueue();
    const localId = 'offline_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
    queue.push({ ...invoiceData, local_id: localId, queued_at: new Date().toISOString() });
    saveOfflineQueue(queue);
    return localId;
}

function removeFromOfflineQueue(localId) {
    const queue = getOfflineQueue().filter(inv => inv.local_id !== localId);
    saveOfflineQueue(queue);
}

// ================================================================
// 3. OFFLINE PRINT — same look as online receipt
// ================================================================
function printOfflineReceipt(invoiceData, localId) {
    const APP_NAME   = 'حمزة للعطور';
    const LOGO_URL   = window.location.origin + '/test/assets/logo.png';
    const now        = new Date();
    const dateStr    = now.toLocaleDateString('ar-EG', { day:'2-digit', month:'2-digit', year:'numeric' })
                     + ' ' + now.toLocaleTimeString('ar-EG', { hour:'2-digit', minute:'2-digit' });

    const cartItems  = invoiceData.cart || [];
    const payLabels  = {
        cash:'كاش', instapay:'انستا باي', vodafone_cash:'فودافون كاش',
        salary_deduction:'خصم من الراتب',
        mixed_cash_instapay:'كاش + إنستا باي',
        mixed_cash_vodafone:'كاش + فودافون كاش'
    };

    // ---- حساب الإجماليات ----
    let subtotal = 0;
    const linesHtml = cartItems.map(item => {
        const qty   = Number(item.qty)   || 1;
        const price = Number(item.price) || 0;
        const gross = qty * price;
        let disc = 0;
        if (item.discountType === 'percent') disc = gross * ((Number(item.discountValue) || 0) / 100);
        else if (item.discountType === 'amount') disc = Number(item.discountValue) || 0;
        const lineTotal = Math.max(0, gross - disc);
        subtotal += lineTotal;

        // حساب الكمية المعروضة: للتركيب الفوري بدون زجاجة نعرض إجمالي الجرامات
        let displayQty = qty;
        let qtyLabel = String(qty);
        if (item.type === 'custom_recipe' && item.without_bottle) {
            const totalGrams = (item.components || []).reduce((s, c) => s + (parseFloat(c.grams) || 0), 0);
            if (totalGrams > 0) {
                displayQty = parseFloat((totalGrams * qty).toFixed(2));
                qtyLabel = displayQty + 'جم';
            }
        }

        const fmt = n => Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ج.م';
        return `
        <div class="receipt-item">
            <div class="item-name">${item.name || '-'}</div>
            <div class="item-calc">
                <span>${qtyLabel} × ${fmt(price)}</span>
                <strong>${fmt(lineTotal)}</strong>
            </div>
        </div>`;
    }).join('');

    let discAmt = 0;
    const discType  = invoiceData.discount_type  || '';
    const discValue = Number(invoiceData.discount_value) || 0;
    if (discType === 'percent') discAmt = subtotal * (discValue / 100);
    else if (discType === 'amount') discAmt = discValue;
    discAmt = Math.max(0, discAmt);

    const total  = Math.max(0, subtotal - discAmt);
    const paid   = Number(invoiceData.paid_total) || 0;
    const change = Math.max(0, paid - total);
    const due    = Math.max(0, total - paid);

    const fmt = n => Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ج.م';

    // اسم طريقة الدفع
    const method = invoiceData.payment_method || 'cash';
    const payLabel = payLabels[method] || method;

    // العميل
    const customerId = invoiceData.customer_id;
    let customerName = 'زبون عابر';
    if (customerId) {
        const cust = (ALL_CUSTOMERS_OFFLINE || []).find(c => String(c.id) === String(customerId));
        if (cust) customerName = cust.name;
    }

    // رقم مؤقت مختصر
    const shortId = localId.replace('offline_', '').substring(0, 12);

    // ---- HTML مطابق للأونلاين ----
    const html = `<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إيصال - ${APP_NAME}</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&family=Noto+Naskh+Arabic:wght@700;900&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    width: 72mm;
    max-width: 72mm;
    background: #fff;
    color: #000;
    font-family: 'Cairo', 'Noto Naskh Arabic', Tahoma, Arial, sans-serif;
    direction: rtl;
    text-align: right;
}
.receipt-container {
    width: 72mm;
    max-width: 72mm;
    margin: 0 auto;
    padding: 0 1mm 0 1mm;
    color: #000;
    direction: rtl;
    text-align: right;
    overflow: hidden;
}
.receipt-header {
    display: flex;
    gap: 6px;
    align-items: center;
    justify-content: center;
    margin-bottom: 2px;
    padding-top: 2px;
}
.receipt-header h2 { font-size: 16px; margin: 0 0 2px 0; font-weight: 900; }
.receipt-header p  { font-size: 11px; margin: 2px 0; }
.receipt-logo img  { max-width: 50px; height: auto; display: block; filter: grayscale(100%) contrast(250%) brightness(0.95); }
.receipt-meta {
    font-size: 11px;
    margin-bottom: 6px;
    line-height: 1.25;
    display: grid;
    gap: 3px;
}
.receipt-meta div {
    display: flex;
    justify-content: space-between;
    border-bottom: 1px dotted #999;
    padding: 2px 0;
    font-weight: 600;
}
.receipt-divider { border-top: 2px dashed #222; margin: 7px 0; }
.receipt-items   { font-size: 11px; }
.receipt-item    { margin-bottom: 3px; }
.receipt-item .item-name  { font-weight: 700; font-size: 11px; margin-bottom: 1px; }
.receipt-item .item-calc  { display: flex; justify-content: space-between; font-size: 10.5px; color: #111; font-weight: 600; }
.receipt-summary { font-size: 11px; line-height: 1.5; }
.receipt-summary div { display: flex; justify-content: space-between; padding: 2px 0; }
.receipt-total {
    font-size: 14px;
    font-weight: 900;
    border-top: 2px solid #111;
    border-bottom: 2px solid #111;
    padding: 5px 0 !important;
    margin: 5px 0;
}
.receipt-due   { color: red; font-weight: bold; }
.receipt-barcode { text-align: center; margin: 10px 0; }
.receipt-barcode code { font-size: 9px; display: block; margin-top: 2px; letter-spacing: 1px; }
.receipt-footer {
    text-align: center;
    font-size: 10.5px;
    font-weight: 700;
    margin-top: 12px;
    line-height: 1.5;
    color: #111;
}
.receipt-addresses {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 10px;
    border-top: 1px dashed #c9a84c;
    padding-top: 9px;
}
.receipt-address-item {
    display: flex;
    align-items: flex-start;
    gap: 5px;
    font-size: 10px;
    line-height: 1.55;
    text-align: right;
    color: #555;
}
.receipt-address-item .addr-icon { font-size: 11px; flex-shrink: 0; margin-top: 1px; }
.receipt-print-spacer { height: 30mm; display: block; }
.offline-note {
    text-align: center;
    font-size: 9px;
    color: #888;
    border: 1px dashed #ccc;
    border-radius: 4px;
    padding: 3px 6px;
    margin-bottom: 6px;
}
@media print {
    html, body, .receipt-container {
        width: 72mm !important;
        max-width: 72mm !important;
        padding-left: 2.5mm !important;
        padding-right: 1mm !important;
        background: #fff !important;
    }
    body, .receipt-container, .receipt-container * {
        font-family: 'Noto Naskh Arabic', 'Cairo', Tahoma, Arial, sans-serif !important;
        font-weight: 900 !important;
        color: #000000 !important;
    }
    .receipt-logo img { filter: brightness(0) !important; }
    .receipt-divider  { border-top: 2px solid #000 !important; }
    .receipt-meta div { border-bottom: 1.2px solid #000 !important; }
    .receipt-total    { border-top: 2.5px solid #000 !important; border-bottom: 2.5px solid #000 !important; }
    .receipt-container { padding-bottom: 40mm !important; }
    .receipt-print-spacer { height: 35mm !important; }
    .offline-note { display: none !important; }
}
</style>
</head>
<body>
<div class="receipt-container">

    <!-- شارة أوفلاين (تختفي عند الطباعة) -->
    <div class="offline-note">📡 إيصال مؤقت — سيُزامَن مع النظام عند عودة الإنترنت</div>

    <!-- الرأس -->
    <div class="receipt-header">
        <div class="receipt-logo"><img src="${LOGO_URL}" alt="logo" onerror="this.style.display='none'"></div>
        <div>
            <h2>${APP_NAME}</h2>
            <p>التاريخ: ${dateStr}</p>
        </div>
    </div>

    <!-- بيانات الفاتورة -->
    <div class="receipt-meta">
        <div><strong>رقم الفاتورة:</strong> <span>${shortId}</span></div>
        <div><strong>العميل:</strong> <span>${customerName}</span></div>
    </div>

    <div class="receipt-divider"></div>

    <!-- الأصناف -->
    <div class="receipt-items">
        ${linesHtml}
    </div>

    <div class="receipt-divider"></div>

    <!-- الإجماليات -->
    <div class="receipt-summary">
        <div><span>إجمالي الأصناف:</span> <strong>${fmt(subtotal)}</strong></div>
        ${discAmt > 0 ? `<div><span>الخصم:</span> <strong>${fmt(discAmt)}</strong></div>` : ''}
        <div class="receipt-total"><span>المطلوب:</span> <strong>${fmt(total)}</strong></div>
        <div><span>المدفوع:</span> <strong>${fmt(paid)}</strong></div>
        ${change > 0 ? `<div><span>الفكة:</span> <strong>${fmt(change)}</strong></div>` : ''}
        ${due > 0 ? `<div class="receipt-due"><span>المتبقي (دين):</span> <strong>${fmt(due)}</strong></div>` : ''}
        <!-- طريقة الدفع -->
        <div style="margin-top:6px; padding-top:6px; border-top:1px dashed #ccc; font-size:11px;">
            <strong style="display:block; margin-bottom:3px;">طريقة الدفع:</strong>
            <div style="display:flex; justify-content:space-between;">
                <span>${payLabel}</span>
                <strong>${fmt(paid)}</strong>
            </div>
        </div>
    </div>

    <div class="receipt-divider"></div>

    <!-- الباركود (رقم مؤقت) -->
    <div class="receipt-barcode">
        <code>${shortId}</code>
    </div>

    <!-- الفوتر -->
    <div class="receipt-footer">
        <p>شكراً لزيارتكم! نتشرف بلقائكم دائماً</p>
        <div class="receipt-addresses">
            <div class="receipt-address-item">
                <span class="addr-icon">📍</span>
                <span>المنوات · جيزة · شارع المدرسة الثانوية · بجوار كوبري بهجات</span>
            </div>
            <div class="receipt-address-item">
                <span class="addr-icon">📍</span>
                <span>أم خنان · جيزة · شارع الوحدة المحلية · بميدان عزت عاشور</span>
            </div>
        </div>
    </div>

    <div class="receipt-print-spacer"></div>
</div>
<script>
window.onload = function() {
    setTimeout(function() { window.print(); }, 400);
};
<\/script>
</body>
</html>`;


    // Use hidden iframe instead of window.open to avoid popup blocker
    let printFrame = document.getElementById('pos-offline-print-iframe');
    if (!printFrame) {
        printFrame = document.createElement('iframe');
        printFrame.id = 'pos-offline-print-iframe';
        printFrame.style.cssText = 'position:fixed;bottom:100%;right:100%;width:1px;height:1px;opacity:0.01;border:none;';
        document.body.appendChild(printFrame);
    }

    // Write HTML into iframe and trigger print
    const blob = new Blob([html], { type: 'text/html; charset=utf-8' });
    const blobUrl = URL.createObjectURL(blob);
    printFrame.onload = function() {
        try {
            printFrame.contentWindow.focus();
            printFrame.contentWindow.print();
        } catch(err) {
            // fallback: open new window if iframe print fails
            const w = window.open('', '_blank');
            if (w) { w.document.write(html); w.document.close(); }
        }
        // Revoke blob URL after a delay
        setTimeout(() => URL.revokeObjectURL(blobUrl), 10000);
    };
    printFrame.src = blobUrl;
}

// ================================================================
// 4. INTERCEPT FORM SUBMIT — queue offline, print offline
// ================================================================
(function() {
    const posForm = document.getElementById('pos-main-form');
    if (!posForm) return;

    posForm.addEventListener('submit', function(e) {
        if (navigator.onLine) return; // let normal submission handle it

        // ===== OFFLINE: prevent normal form submit =====
        e.preventDefault();
        e.stopImmediatePropagation();

        if (!cart || cart.length === 0) {
            alert('السلة فارغة! أضف منتجات أولاً.');
            return;
        }

        // Collect form data
        const formData = new FormData(posForm);
        const locationId = formData.get('location_id') || POS_LOCATION_ID;
        const customerId = formData.get('customer_id') || '';
        const paymentMethod = formData.get('payment_method') || 'cash';
        const paidTotal = formData.get('paid_total') || '0';
        const discountType = formData.get('discount_type') || '';
        const discountValue = formData.get('discount_value') || '0';
        const notes = formData.get('notes') || '';
        const deductionUserId = formData.get('deduction_user_id') || '';
        const paidCash = formData.get('paid_cash') || '0';
        const paidInstapay = formData.get('paid_instapay') || '0';
        const paidVodafoneCash = formData.get('paid_vodafone_cash') || '0';

        const invoiceData = {
            location_id:       locationId,
            customer_id:       customerId,
            payment_method:    paymentMethod,
            paid_total:        parseFloat(paidTotal) || 0,
            discount_type:     discountType,
            discount_value:    parseFloat(discountValue) || 0,
            notes:             notes,
            deduction_user_id: deductionUserId,
            paid_cash:         parseFloat(paidCash) || 0,
            paid_instapay:     parseFloat(paidInstapay) || 0,
            paid_vodafone_cash:parseFloat(paidVodafoneCash) || 0,
            cart:              JSON.parse(JSON.stringify(cart)), // deep copy
        };

        const localId = addToOfflineQueue(invoiceData);
        printOfflineReceipt(invoiceData, localId);

        // Clear the current tab
        try { closeTab(activeTabId, true); } catch(err) { cart.length = 0; renderCart(); }

        // Show success notification
        const notif = document.createElement('div');
        notif.style.cssText = 'position:fixed;top:60px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#065f46,#059669);color:#fff;padding:14px 28px;border-radius:14px;font-family:Cairo,sans-serif;font-weight:700;font-size:14px;z-index:99999;box-shadow:0 8px 24px rgba(0,0,0,0.3);text-align:center;direction:rtl;';
        notif.innerHTML = '✅ تم حفظ الفاتورة محلياً وسيُزامن عند عودة الإنترنت<br><small style="font-weight:400;opacity:0.85;">رقم مؤقت: ' + localId + '</small>';
        document.body.appendChild(notif);
        setTimeout(() => { if (notif.parentNode) notif.parentNode.removeChild(notif); }, 5000);
    }, true); // capture phase to run before the existing submit listener
})();

// ================================================================
// 5. AUTO-SYNC when back online (مزامنة تلقائية)
// ================================================================
async function syncPendingInvoices() {
    const queue = getOfflineQueue();
    if (!queue.length) return;

    // Update badge
    if (typeof window.updateOfflinePendingCount === 'function') window.updateOfflinePendingCount(queue.length);

    // Show sync progress notification
    let syncNotif = document.getElementById('pos-sync-notif');
    if (!syncNotif) {
        syncNotif = document.createElement('div');
        syncNotif.id = 'pos-sync-notif';
        syncNotif.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;padding:12px 24px;border-radius:12px;font-family:Cairo,sans-serif;font-weight:700;font-size:13px;z-index:99999;box-shadow:0 6px 20px rgba(0,0,0,0.3);text-align:center;direction:rtl;min-width:280px;';
        document.body.appendChild(syncNotif);
    }
    syncNotif.textContent = '🔄 جاري مزامنة ' + queue.length + ' فاتورة معلقة...';
    syncNotif.style.display = 'block';

    try {
        const response = await fetch('index.php?r=pos_sync_offline', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ invoices: queue, csrf: POS_CSRF })
        });

        if (!response.ok) throw new Error('HTTP ' + response.status);
        const result = await response.json();

        let synced = 0, failed = 0;
        (result.results || []).forEach(r => {
            if (r.status === 'ok') { removeFromOfflineQueue(r.local_id); synced++; }
            else { failed++; console.warn('فاتورة فشلت:', r); }
        });

        // If server returned ok but no results array, clear all
        if (!result.results && result.status === 'ok') {
            queue.forEach(inv => removeFromOfflineQueue(inv.local_id));
            synced = queue.length;
        }

        const remaining = getOfflineQueue().length;
        if (typeof window.updateOfflinePendingCount === 'function') window.updateOfflinePendingCount(remaining);

        if (synced > 0) {
            syncNotif.style.background = 'linear-gradient(135deg,#065f46,#059669)';
            syncNotif.textContent = '✅ تمت مزامنة ' + synced + ' فاتورة بنجاح!' + (failed > 0 ? ' ⚠️ ' + failed + ' فشلت.' : '');
        } else if (failed > 0) {
            syncNotif.style.background = 'linear-gradient(135deg,#7c1d1d,#991b1b)';
            syncNotif.textContent = '⚠️ فشلت مزامنة ' + failed + ' فاتورة. ستعاد المحاولة لاحقاً.';
        }
        setTimeout(() => { if (syncNotif) syncNotif.style.display = 'none'; }, 6000);

    } catch(err) {
        console.error('فشلت المزامنة:', err);
        if (syncNotif) {
            syncNotif.style.background = 'linear-gradient(135deg,#7c1d1d,#991b1b)';
            syncNotif.textContent = '❌ فشل الاتصال بالسيرفر. ستعاد المحاولة تلقائياً.';
            setTimeout(() => { if (syncNotif) syncNotif.style.display = 'none'; }, 5000);
        }
    }
}

// Auto-sync on page load if online and there are pending invoices
document.addEventListener('DOMContentLoaded', function() {
    const pending = getOfflineQueue();
    if (pending.length > 0) {
        if (typeof window.updateOfflinePendingCount === 'function') window.updateOfflinePendingCount(pending.length);
        if (navigator.onLine) {
            setTimeout(syncPendingInvoices, 2000);
        }
    }

    // Register Background Sync if supported
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
        navigator.serviceWorker.ready.then(reg => {
            return reg.sync.register('sync-pending-invoices').catch(() => {});
        });
    }

    // ================================================================
    // SNAPSHOT: Send full page HTML to SW for reliable offline caching
    // ================================================================
    function sendPageSnapshotToSW() {
        if (!('serviceWorker' in navigator)) return;
        const sw = navigator.serviceWorker.controller;
        if (!sw) return;
        const html = '<!DOCTYPE html>' + document.documentElement.outerHTML;
        sw.postMessage({ type: 'CACHE_POS_HTML', html: html });
        return true;
    }

    // Also cache via SW fetch (double strategy)
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({
            type: 'CACHE_POS_PAGE',
            url: window.location.pathname + '?r=pos'
        });
        sendPageSnapshotToSW();
        showOfflineReadyBadge(true);
    } else if ('serviceWorker' in navigator) {
        // SW not yet controlling — wait for it
        navigator.serviceWorker.ready.then(reg => {
            // force SW to take control
            if (reg.active) {
                reg.active.postMessage({ type: 'CACHE_POS_PAGE', url: window.location.pathname + '?r=pos' });
            }
        });
        navigator.serviceWorker.addEventListener('controllerchange', function() {
            sendPageSnapshotToSW();
            showOfflineReadyBadge(true);
        });
        // Show "not cached yet" state
        showOfflineReadyBadge(false);
    }

    // Re-send snapshot every 3 minutes to keep cache fresh
    setInterval(function() {
        if (navigator.onLine) sendPageSnapshotToSW();
    }, 3 * 60 * 1000);

    // ================================================================
    // OFFLINE-READY BADGE in topbar
    // ================================================================
    function showOfflineReadyBadge(ready) {
        // Don't add duplicate
        if (document.getElementById('pos-offline-badge')) {
            document.getElementById('pos-offline-badge').className = ready ? 'pos-offline-badge ready' : 'pos-offline-badge pending';
            document.getElementById('pos-offline-badge').title = ready ? 'الصفحة محفوظة — تعمل بدون نت ✅' : 'جاري الحفظ للأوفلاين...';
            document.getElementById('pos-offline-badge').querySelector('span').textContent = ready ? '📶 جاهز أوفلاين' : '⏳ يحفظ...';
            return;
        }
        const style = document.createElement('style');
        style.textContent = `
            .pos-offline-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; cursor:default; transition:all 0.3s; }
            .pos-offline-badge.ready { background:rgba(5,150,105,0.15); color:#059669; border:1px solid rgba(5,150,105,0.3); }
            .pos-offline-badge.pending { background:rgba(245,158,11,0.15); color:#d97706; border:1px solid rgba(245,158,11,0.3); }
            .pos-offline-badge.error { background:rgba(220,38,38,0.12); color:#dc2626; border:1px solid rgba(220,38,38,0.25); }
        `;
        document.head.appendChild(style);

        const badge = document.createElement('div');
        badge.id = 'pos-offline-badge';
        badge.className = ready ? 'pos-offline-badge ready' : 'pos-offline-badge pending';
        badge.title = ready ? 'الصفحة محفوظة — تعمل بدون نت ✅' : 'جاري الحفظ للأوفلاين...';
        badge.innerHTML = '<span>' + (ready ? '📶 جاهز أوفلاين' : '⏳ يحفظ...') + '</span>';

        // Clicking re-saves manually
        badge.style.cursor = 'pointer';
        badge.addEventListener('click', function() {
            badge.querySelector('span').textContent = '⏳ يحفظ...';
            badge.className = 'pos-offline-badge pending';
            const ok = sendPageSnapshotToSW();
            setTimeout(() => {
                badge.className = ok ? 'pos-offline-badge ready' : 'pos-offline-badge error';
                badge.querySelector('span').textContent = ok ? '📶 جاهز أوفلاين' : '❌ تعذّر الحفظ';
            }, 800);
        });

        // Insert into POS topbar actions if found, else floating
        const topbarActions = document.querySelector('.pos-topbar-actions') || document.querySelector('.topbar-actions');
        if (topbarActions) {
            topbarActions.prepend(badge);
        } else {
            badge.style.cssText += 'position:fixed;bottom:16px;left:16px;z-index:8888;';
            document.body.appendChild(badge);
        }
    }
});

    // ================================================================
    // POS Returns & Exchanges System
    // ================================================================
    let customerInvoicesCache = {};
    let selectedReturnItems = {}; // { [lineId]: { invoice_id, line_id, description, unit_price, max_qty, quantity, return_amount } }
    let activeReturnInvoiceId = null;

    function onCustomerSelectChange(customerId) {
        saveCurrentTabState();
        const badge = document.getElementById('customer-invoices-count-badge');
        if (!customerId) {
            if (badge) badge.textContent = 'فواتير';
            return;
        }
        loadCustomerInvoices(customerId);
    }

    async function loadCustomerInvoices(customerId) {
        const badge = document.getElementById('customer-invoices-count-badge');
        if (!customerId) {
            if (badge) badge.textContent = 'فواتير';
            return;
        }
        try {
            const res = await fetch(`index.php?r=api_customer_invoices&customer_id=${customerId}&location_id=${POS_LOCATION_ID}`);
            const data = await res.json();
            if (data.success && Array.isArray(data.invoices)) {
                customerInvoicesCache[customerId] = data.invoices;
                if (badge) badge.textContent = `${data.invoices.length} فواتير`;
            }
        } catch (err) {
            console.error('Error fetching customer invoices:', err);
        }
    }

    function openCustomerInvoicesModal() {
        const custSelect = document.getElementById('customer_id_select');
        const customerId = custSelect ? custSelect.value : '';
        const custName = (custSelect && customerId) ? (custSelect.options[custSelect.selectedIndex]?.text || '') : '';
        const modal = document.getElementById('customer-invoices-modal');
        const subtitle = document.getElementById('cust-invoices-modal-subtitle');
        const body = document.getElementById('cust-invoices-modal-body');
        const searchInput = document.getElementById('cust-invoices-search-input');

        if (searchInput) searchInput.value = '';
        if (subtitle) {
            subtitle.textContent = customerId ? `العميل: ${custName}` : 'بحث واسترجاع من جميع الفواتير السابقة';
        }
        if (modal) modal.style.display = 'flex';

        // Clear previous selection
        selectedReturnItems = {};
        activeReturnInvoiceId = null;
        updateReturnSelectionSummary();

        if (customerId) {
            if (customerInvoicesCache[customerId]) {
                renderCustomerInvoices(customerInvoicesCache[customerId]);
            } else {
                if (body) body.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--muted);"><span style="font-size: 24px;">⏳</span><br>جاري تحميل فواتير العميل...</div>';
                loadCustomerInvoices(customerId).then(() => {
                    renderCustomerInvoices(customerInvoicesCache[customerId] || []);
                });
            }
        } else {
            // No customer selected -> fetch recent invoices
            if (body) body.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--muted);"><span style="font-size: 24px;">⏳</span><br>جاري تحميل آخر الفواتير...</div>';
            fetch(`index.php?r=api_customer_invoices&search=all&location_id=${POS_LOCATION_ID}`)
                .then(r => r.json())
                .then(data => {
                    renderCustomerInvoices(data.invoices || []);
                })
                .catch(() => {
                    if (body) body.innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted);">اكتب رقم الفاتورة أو اسم العميل في شريط البحث أعلاه.</div>';
                });
        }
    }

    async function searchInvoicesFromModal() {
        const searchInput = document.getElementById('cust-invoices-search-input');
        const query = searchInput ? searchInput.value.trim() : '';
        const body = document.getElementById('cust-invoices-modal-body');

        if (!query) {
            resetInvoiceSearchModal();
            return;
        }

        if (body) body.innerHTML = '<div style="text-align: center; padding: 40px; color: var(--muted);"><span style="font-size: 24px;">⏳</span><br>جاري البحث في الفواتير...</div>';

        try {
            const res = await fetch(`index.php?r=api_customer_invoices&search=${encodeURIComponent(query)}&location_id=${POS_LOCATION_ID}`);
            const data = await res.json();
            renderCustomerInvoices(data.invoices || []);
        } catch (err) {
            if (body) body.innerHTML = `<div style="text-align: center; padding: 30px; color: var(--danger);">حدث خطأ أثناء البحث: ${err.message}</div>`;
        }
    }

    function resetInvoiceSearchModal() {
        const searchInput = document.getElementById('cust-invoices-search-input');
        if (searchInput) searchInput.value = '';
        const custSelect = document.getElementById('customer_id_select');
        const customerId = custSelect ? custSelect.value : '';
        if (customerId && customerInvoicesCache[customerId]) {
            renderCustomerInvoices(customerInvoicesCache[customerId]);
        } else if (customerId) {
            loadCustomerInvoices(customerId).then(() => {
                renderCustomerInvoices(customerInvoicesCache[customerId] || []);
            });
        } else {
            openCustomerInvoicesModal();
        }
    }

    function closeCustomerInvoicesModal() {
        const modal = document.getElementById('customer-invoices-modal');
        if (modal) modal.style.display = 'none';
    }

    function renderCustomerInvoices(invoices) {
        const body = document.getElementById('cust-invoices-modal-body');
        if (!body) return;

        if (!invoices || invoices.length === 0) {
            body.innerHTML = `
                <div style="text-align: center; padding: 40px; color: var(--muted);">
                    <span style="font-size: 32px; display: block; margin-bottom: 8px;">🛒</span>
                    <strong>لا توجد فواتير مطابقة للبحث.</strong>
                </div>
            `;
            return;
        }

        let html = '';
        invoices.forEach(inv => {
            const lines = inv.lines || [];
            const custInfo = inv.customer_name ? ` (العميل: ${inv.customer_name}${inv.customer_phone ? ' - ' + inv.customer_phone : ''})` : ' (زبون عابر)';
            html += `
                <div class="customer-inv-card" id="inv-card-${inv.id}">
                    <div class="customer-inv-header" onclick="toggleInvoiceAccordion(${inv.id})">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="font-size: 16px;">📄</span>
                            <div>
                                <strong style="color: var(--gold-dark); font-size: 14px;">فاتورة #${inv.invoice_number}</strong>
                                <span style="font-size: 11px; color: var(--muted); margin-right: 8px;">(${inv.formatted_date}) - ${inv.location_name || ''}${custInfo}</span>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <strong style="color: var(--primary-dark); font-size: 14px;">${parseFloat(inv.total).toFixed(2)} ج.م</strong>
                            <span id="accordion-arrow-${inv.id}" style="font-size: 13px; color: var(--muted); transition: transform 0.2s;">▼</span>
                        </div>
                    </div>
                    <div id="inv-lines-container-${inv.id}" style="margin-top: 10px; display: block;">
                        <div style="display: grid; grid-template-columns: 28px 1fr 80px 80px 100px 90px; gap: 8px; font-size: 11px; font-weight: 800; color: var(--muted); padding: 4px 6px; border-bottom: 1px solid rgba(201,168,76,0.15);">
                            <span></span>
                            <span>الصنف</span>
                            <span style="text-align: center;">الكمية</span>
                            <span style="text-align: center;">السعر</span>
                            <span style="text-align: center;">كمية الإرجاع</span>
                            <span style="text-align: right;">المجموع</span>
                        </div>
            `;

            lines.forEach(line => {
                const qty = parseFloat(line.quantity) || 1;
                const total = parseFloat(line.line_total) || 0;
                const unitPrice = qty > 0 ? (total / qty) : parseFloat(line.unit_price) || 0;
                const lineId = line.id;

                html += `
                    <div class="inv-line-row" id="inv-line-row-${lineId}">
                        <div>
                            <input type="checkbox" id="chk-line-${lineId}" onchange="toggleInvoiceItemCheck(${inv.id}, ${lineId}, '${encodeURIComponent(line.description)}', ${unitPrice}, ${qty})" style="transform: scale(1.2); cursor: pointer;">
                        </div>
                        <div>
                            <strong>${line.description}</strong>
                            <span style="font-size: 10px; color: var(--muted); display: block;">${line.line_type === 'product' ? 'منتج جاهز' : (line.line_type === 'offer' ? 'عرض' : 'تركيبة')}</span>
                        </div>
                        <div style="text-align: center;">${qty}</div>
                        <div style="text-align: center;">${unitPrice.toFixed(2)}</div>
                        <div style="text-align: center;">
                            <input type="number" id="qty-line-${lineId}" min="0.01" max="${qty}" step="any" value="${qty}" oninput="updateReturnItemQty(${lineId})" style="width: 70px; padding: 4px; border: 1px solid var(--line); border-radius: 6px; text-align: center; font-size: 12px;" disabled>
                        </div>
                        <div style="text-align: right;"><strong id="total-line-${lineId}">${total.toFixed(2)} ج.م</strong></div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });

        body.innerHTML = html;
    }

    function toggleInvoiceAccordion(invId) {
        const container = document.getElementById(`inv-lines-container-${invId}`);
        const arrow = document.getElementById(`accordion-arrow-${invId}`);
        if (!container) return;
        if (container.style.display === 'none') {
            container.style.display = 'block';
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        } else {
            container.style.display = 'none';
            if (arrow) arrow.style.transform = 'rotate(-90deg)';
        }
    }

    function toggleInvoiceItemCheck(invId, lineId, encodedDesc, unitPrice, maxQty) {
        const chk = document.getElementById(`chk-line-${lineId}`);
        const qtyInput = document.getElementById(`qty-line-${lineId}`);
        const row = document.getElementById(`inv-line-row-${lineId}`);
        const desc = decodeURIComponent(encodedDesc);

        if (chk && chk.checked) {
            // Check if user is switching invoice
            if (activeReturnInvoiceId && activeReturnInvoiceId !== invId && Object.keys(selectedReturnItems).length > 0) {
                if (!confirm('لقد قمت بتحديد أصناف من فاتورة أخرى. هل تريد إلغاء التحديد السابق والبدء من هذه الفاتورة؟')) {
                    chk.checked = false;
                    return;
                }
                // Clear other invoice selections
                for (const k in selectedReturnItems) {
                    const otherChk = document.getElementById(`chk-line-${k}`);
                    const otherQty = document.getElementById(`qty-line-${k}`);
                    const otherRow = document.getElementById(`inv-line-row-${k}`);
                    if (otherChk) otherChk.checked = false;
                    if (otherQty) otherQty.disabled = true;
                    if (otherRow) otherRow.classList.remove('selected');
                }
                selectedReturnItems = {};
            }

            activeReturnInvoiceId = invId;
            if (qtyInput) qtyInput.disabled = false;
            if (row) row.classList.add('selected');

            const qty = parseFloat(qtyInput ? qtyInput.value : maxQty) || maxQty;
            selectedReturnItems[lineId] = {
                invoice_id: invId,
                line_id: lineId,
                description: desc,
                unit_price: unitPrice,
                max_qty: maxQty,
                quantity: qty,
                return_amount: qty * unitPrice
            };
        } else {
            if (qtyInput) qtyInput.disabled = true;
            if (row) row.classList.remove('selected');
            delete selectedReturnItems[lineId];
            if (Object.keys(selectedReturnItems).length === 0) {
                activeReturnInvoiceId = null;
            }
        }

        updateReturnSelectionSummary();
    }

    function updateReturnItemQty(lineId) {
        const qtyInput = document.getElementById(`qty-line-${lineId}`);
        const totalSpan = document.getElementById(`total-line-${lineId}`);
        if (!selectedReturnItems[lineId] || !qtyInput) return;

        let val = parseFloat(qtyInput.value) || 0;
        const max = selectedReturnItems[lineId].max_qty;
        if (val > max) {
            val = max;
            qtyInput.value = max;
        }
        if (val < 0) {
            val = 0;
            qtyInput.value = 0;
        }

        selectedReturnItems[lineId].quantity = val;
        selectedReturnItems[lineId].return_amount = val * selectedReturnItems[lineId].unit_price;
        if (totalSpan) totalSpan.textContent = (val * selectedReturnItems[lineId].unit_price).toFixed(2) + ' ج.م';

        updateReturnSelectionSummary();
    }

    function updateReturnSelectionSummary() {
        const countSpan = document.getElementById('selected-return-items-count');
        const totalSpan = document.getElementById('selected-return-items-total');
        const btnDirect = document.getElementById('btn-direct-return');
        const btnExchange = document.getElementById('btn-apply-exchange');

        let count = 0;
        let total = 0.0;

        for (const k in selectedReturnItems) {
            count++;
            total += selectedReturnItems[k].return_amount;
        }

        if (countSpan) countSpan.textContent = count + ' أصناف';
        if (totalSpan) totalSpan.textContent = total.toFixed(2) + ' ج.م';

        const hasItems = count > 0 && total > 0;
        if (btnDirect) btnDirect.disabled = !hasItems;
        if (btnExchange) btnExchange.disabled = !hasItems;
    }

    function promptDirectReturn() {
        const items = Object.values(selectedReturnItems);
        if (items.length === 0) return;

        const total = items.reduce((sum, it) => sum + it.return_amount, 0);
        const modal = document.getElementById('direct-return-confirm-modal');
        const totalSpan = document.getElementById('direct-return-total-amount');
        const summarySpan = document.getElementById('direct-return-summary-text');
        const paidInput = document.getElementById('direct-return-paid-input');

        if (totalSpan) totalSpan.textContent = total.toFixed(2) + ' ج.م';
        if (paidInput) paidInput.value = total.toFixed(2);
        if (summarySpan) {
            summarySpan.textContent = `الأصناف: ` + items.map(it => `${it.description} (${it.quantity}×)`).join('، ');
        }

        if (modal) modal.style.display = 'flex';
    }

    function closeDirectReturnModal() {
        const modal = document.getElementById('direct-return-confirm-modal');
        if (modal) modal.style.display = 'none';
    }

    async function executeDirectReturn() {
        const items = Object.values(selectedReturnItems);
        if (items.length === 0 || !activeReturnInvoiceId) return;

        const method = document.getElementById('direct-return-method-select')?.value || 'cash';
        const paid = parseFloat(document.getElementById('direct-return-paid-input')?.value) || 0;
        const reason = document.getElementById('direct-return-reason-input')?.value || 'مرتجع مباشر من الكاشير';
        const errBox = document.getElementById('direct-return-error');
        const btnConfirm = document.getElementById('btn-confirm-execute-return');

        if (errBox) errBox.style.display = 'none';
        if (btnConfirm) btnConfirm.disabled = true;

        try {
            const payload = {
                invoice_id: activeReturnInvoiceId,
                refund_method: method,
                refund_paid: paid,
                reason: reason,
                lines: items.map(it => ({ line_id: it.line_id, quantity: it.quantity }))
            };

            const res = await fetch('index.php?r=api_pos_return', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (data.success) {
                alert('✅ ' + (data.message || 'تم تسجيل المرتجع واستعادة المخزون بنجاح.'));
                closeDirectReturnModal();
                closeCustomerInvoicesModal();
                // Refresh customer invoices cache
                const custSelect = document.getElementById('customer_id_select');
                if (custSelect && custSelect.value) {
                    delete customerInvoicesCache[custSelect.value];
                    loadCustomerInvoices(custSelect.value);
                }
            } else {
                if (errBox) {
                    errBox.textContent = data.message || 'حدث خطأ أثناء تنفيذ الإرجاع.';
                    errBox.style.display = 'block';
                }
            }
        } catch (err) {
            if (errBox) {
                errBox.textContent = 'تعذر الاتصال بالسيرفر: ' + err.message;
                errBox.style.display = 'block';
            }
        } finally {
            if (btnConfirm) btnConfirm.disabled = false;
        }
    }

    function applyExchangeToCart() {
        const items = Object.values(selectedReturnItems);
        if (items.length === 0 || !activeReturnInvoiceId) return;

        const custSelect = document.getElementById('customer_id_select');
        const customerId = custSelect ? custSelect.value : '';
        const totalCredit = items.reduce((sum, it) => sum + it.return_amount, 0);

        // Find invoice number
        let invNumber = String(activeReturnInvoiceId);
        if (customerId && customerInvoicesCache[customerId]) {
            const found = customerInvoicesCache[customerId].find(i => i.id === activeReturnInvoiceId);
            if (found) invNumber = found.invoice_number;
        }

        const tab = getActiveTab();
        if (tab) {
            tab.exchangeData = {
                original_invoice_id: activeReturnInvoiceId,
                invoice_number: invNumber,
                credit_amount: totalCredit,
                lines: items.map(it => ({
                    line_id: it.line_id,
                    description: it.description,
                    quantity: it.quantity,
                    unit_price: it.unit_price,
                    return_amount: it.return_amount
                }))
            };
            saveCurrentTabState();
            closeCustomerInvoicesModal();
            updateExchangeBannerUI();
            recalculateTotals();
            renderCart();

            alert(`🔄 تم تفعيل وضع الاستبدال برصيد ${totalCredit.toFixed(2)} ج.م!\nيمكنك الآن إضافة الأصناف أو التركيبات الجديدة للسلة وسيتم احتساب فرق الحساب تلقائياً.`);
        }
    }

    function cancelExchange() {
        if (!confirm('هل تريد بالتأكيد إلغاء وضع الاستبدال؟')) return;
        const tab = getActiveTab();
        if (tab) {
            tab.exchangeData = null;
            saveCurrentTabState();
            updateExchangeBannerUI();
            recalculateTotals();
            renderCart();
        }
    }

    function updateExchangeBannerUI() {
        const tab = getActiveTab();
        const banner = document.getElementById('exchange-active-banner');
        const origNumberSpan = document.getElementById('exchange-orig-inv-number');
        const itemsDescSpan = document.getElementById('exchange-items-desc');
        const creditBadge = document.getElementById('exchange-credit-badge');
        
        const sideBox = document.getElementById('side-exchange-active-box');
        const sideCreditVal = document.getElementById('side-exchange-credit-val');
        const sideInvInfo = document.getElementById('side-exchange-invoice-info');

        if (tab && tab.exchangeData && tab.exchangeData.credit_amount > 0) {
            if (banner) banner.style.display = 'block';
            if (origNumberSpan) origNumberSpan.textContent = tab.exchangeData.invoice_number || tab.exchangeData.original_invoice_id;
            if (itemsDescSpan) {
                const desc = (tab.exchangeData.lines || []).map(l => `${l.description} (${l.quantity}×)`).join('، ');
                itemsDescSpan.textContent = desc;
            }
            if (creditBadge) creditBadge.textContent = parseFloat(tab.exchangeData.credit_amount).toFixed(2) + ' ج.م';

            if (sideBox) sideBox.style.display = 'block';
            if (sideCreditVal) sideCreditVal.textContent = parseFloat(tab.exchangeData.credit_amount).toFixed(2) + ' ج.م';
            if (sideInvInfo) sideInvInfo.textContent = `فاتورة #${tab.exchangeData.invoice_number || tab.exchangeData.original_invoice_id} (${parseFloat(tab.exchangeData.credit_amount).toFixed(2)} ج.م)`;
        } else {
            if (banner) banner.style.display = 'none';
            if (sideBox) sideBox.style.display = 'none';
        }
    }

// التحكم في نافذة بوستر نصر 6 أكتوبر
function openOctoberModal() {
    const modal = document.getElementById('october-modal');
    if (modal) {
        modal.classList.add('show');
    }
}

function closeOctoberModal() {
    const modal = document.getElementById('october-modal');
    if (modal) {
        modal.classList.remove('show');
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeOctoberModal();
    }
});
</script>

<!-- Modal بوستر نصر 6 أكتوبر التذكاري المجيد -->
<div id="october-modal" class="october-modal" onclick="if(event.target === this) closeOctoberModal();">
    <div class="october-modal-card">
        <div class="october-modal-header">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 26px;">🇪🇬</span>
                <div style="text-align: right;">
                    <h3 style="margin: 0; font-size: 16px; font-weight: 800; color: #fff;">الذكرى الـ 53 - حرب أكتوبر 1973</h3>
                    <p style="margin: 2px 0 0; font-size: 11.5px; color: #cbd5e1;">يوم العزة والكرامة والشجاعة المصرية الخالدة</p>
                </div>
            </div>
            <button type="button" class="october-modal-close" onclick="closeOctoberModal()" title="إغلاق">&times;</button>
        </div>
        <div class="october-modal-body">
            <img src="assets/october_victory.jpg" alt="حرب أكتوبر 1973" class="october-full-img">
        </div>
        <div class="october-modal-footer">
            <span>🇪🇬 كل عام ومصر وقواتنا المسلحة الباسلة وشعبنا العظيم بخير وعزة ونصر دائماً 🌟</span>
            <button type="button" class="btn small primary" onclick="closeOctoberModal()">إغلاق ومتابعة الكاشير</button>
        </div>
    </div>
</div>



<!-- Modal Off Order -->
<div id="off-order-modal" class="october-modal">
    <div class="october-modal-card" style="max-width: 600px;">
        <div class="october-modal-header">
            <h3 style="margin: 0; font-size: 16px; color: var(--gold-light); display: flex; align-items: center; gap: 8px;">
                🔄 اختيار تركيبة مرتجعة (Off Order)
            </h3>
            <button type="button" class="october-modal-close" onclick="closeOffOrderModal()">&times;</button>
        </div>
        <div class="october-modal-body" style="flex-direction: column; align-items: stretch; padding: 16px; max-height: 70vh; overflow-y: auto; background: var(--surface);">
            <div id="off-order-list-container">
                <div style="text-align: center; color: var(--muted); padding: 20px;">جاري التحميل...</div>
            </div>
        </div>
    </div>
</div>

<script>
window.offOrderDataCache = [];

function openOffOrderModal() {
    document.getElementById('off-order-modal').classList.add('show');
    document.getElementById('off-order-list-container').innerHTML = '<div style="text-align: center; color: var(--muted); padding: 20px;">جاري التحميل... ⏳</div>';
    
    fetch('index.php?r=api_get_off_orders')
    .then(res => res.json())
    .then(data => {
        if (!data.success) return alert(data.message || 'خطأ في تحميل المرتجعات');
        
        let html = '';
        if (data.formulas.length === 0) {
            html = '<div style="padding: 20px; text-align: center; color: var(--muted);">لا توجد تركيبات مرتجعة (Off Orders) حالياً في هذا الفرع.</div>';
        } else {
            html = '<div style="display: grid; gap: 10px;">';
            data.formulas.forEach(f => {
                const totalGrams = f.components.reduce((sum, c) => c.type === 'perfume_gram' ? sum + parseFloat(c.quantity) : sum, 0);
                const bottle = f.components.find(c => c.type === 'bottle');
                const bName = bottle ? bottle.name : 'بدون زجاجة';
                
                html += `<div class="customer-inv-card" onclick="loadOffOrderIntoMixBuilder(${f.id})" style="cursor:pointer; display: flex; justify-content: space-between; align-items: center; border: 1.5px solid var(--gold); background: var(--surface); padding: 12px; border-radius: 8px; transition: 0.2s;">
                    <div>
                        <strong style="color: var(--gold-dark); font-size: 14px;">تركيبة مرتجعة من فاتورة #${f.invoice_number}</strong>
                        <div style="font-size: 11.5px; color: var(--muted); margin-top: 4px;">🍾 ${bName} &nbsp;|&nbsp; 💧 ${totalGrams} جرام زيت</div>
                    </div>
                    <div style="text-align: left;">
                        <strong style="font-size: 16px; color: #16a34a; font-weight: 900;">${parseFloat(f.line_total).toFixed(2)} ج.م</strong>
                        <div style="font-size: 10px; color: var(--muted); margin-top: 2px;">${f.invoice_date.split(' ')[0]}</div>
                    </div>
                </div>`;
            });
            html += '</div>';
        }
        
        document.getElementById('off-order-list-container').innerHTML = html;
        window.offOrderDataCache = data.formulas;
    })
    .catch(err => {
        document.getElementById('off-order-list-container').innerHTML = '<div style="color: red; text-align: center; padding: 20px;">فشل الاتصال بالخادم.</div>';
    });
}

function loadOffOrderIntoMixBuilder(offOrderId) {
    const f = window.offOrderDataCache.find(x => parseInt(x.id) === parseInt(offOrderId));
    if (!f) return;
    
    closeOffOrderModal();
    
    let bottle = f.components.find(c => c.type === 'bottle');
    let oils = f.components.filter(c => c.type === 'perfume_gram');
    let totalPrice = parseFloat(f.line_total);

    // 1. Set Bottle
    const mixBottleSelect = document.getElementById('mix_bottle_id');
    const bottleId = bottle ? bottle.component_product_id : 'no_bottle';
    if (mixBottleSelect) {
        mixBottleSelect.value = bottleId;
        if (typeof refreshSearchableSelect === 'function') refreshSearchableSelect(mixBottleSelect);
    }

    // 2. Clear existing oils and add the new ones
    const container = document.getElementById('mix-perfumes-container');
    if (container) {
        const rows = container.querySelectorAll('.mix-perfume-row');
        for (let i = 1; i < rows.length; i++) rows[i].remove();
        
        oils.forEach((oil, index) => {
            let row;
            if (index === 0) {
                row = container.querySelector('.mix-perfume-row');
            } else {
                if (typeof addOilRow === 'function') addOilRow();
                const newRows = container.querySelectorAll('.mix-perfume-row');
                row = newRows[newRows.length - 1];
            }
            
            if (row) {
                const select = row.querySelector('select[name="mix_perfume_id[]"]');
                const input = row.querySelector('input[name="mix_grams[]"]');
                if (select) {
                    select.value = oil.component_product_id;
                    if (typeof refreshSearchableSelect === 'function') refreshSearchableSelect(select);
                }
                if (input) {
                    input.value = parseFloat(oil.quantity);
                }
            }
        });
    }

    // 3. Set Price
    const priceInput = document.getElementById('mix_sale_price');
    if (priceInput) {
        priceInput.value = Math.round(totalPrice);
        priceInput.dataset.manual = "1";
    }
    
    if (typeof calculateSuggestedMixPrice === 'function') calculateSuggestedMixPrice();
}

function closeOffOrderModal() {
    document.getElementById('off-order-modal').classList.remove('show');
}
</script>

