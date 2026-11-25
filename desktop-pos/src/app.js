/**
 * Hamza for Perfumes — Offline POS Application Logic
 */

let allProducts = [];
let allCustomers = [];
let allOffers = [];
let allRecipes = [];
let formulaDefaults = [];
let activeLocationId = 3; // Default to المنوات
let activeLocationName = 'فرع المنوات';
let cart = [];
let audioCtx = null;

// Audio Synthesizer for POS Beeps
function playBeep(type = 'success') {
    try {
        if (!audioCtx) {
            audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume();
        }

        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);

        if (type === 'success') {
            // High double beep
            osc.frequency.setValueAtTime(1200, audioCtx.currentTime);
            osc.frequency.setValueAtTime(1600, audioCtx.currentTime + 0.08);
            gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.2);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.2);
        } else if (type === 'error') {
            // Low buzz
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, audioCtx.currentTime);
            gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.35);
        } else if (type === 'checkout') {
            // Cash register chime
            osc.type = 'sine';
            osc.frequency.setValueAtTime(523.25, audioCtx.currentTime); // C5
            osc.frequency.setValueAtTime(659.25, audioCtx.currentTime + 0.1); // E5
            osc.frequency.setValueAtTime(783.99, audioCtx.currentTime + 0.2); // G5
            osc.frequency.setValueAtTime(1046.50, audioCtx.currentTime + 0.3); // C6
            gain.gain.setValueAtTime(0.25, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
            osc.start(audioCtx.currentTime);
            osc.stop(audioCtx.currentTime + 0.5);
        }
    } catch (e) {
        // Audio error silent fallback
    }
}
window.playBeep = playBeep;

// Initialize POS Application
window.addEventListener('DOMContentLoaded', async () => {
    // Open DB and setup Sync Engine
    await openPOSDatabase();
    await seedInitialDataIfNeeded();
    await window.syncEngine.init();

    // Register Sync listener
    window.syncEngine.onStatusChange(updateSyncBadge);

    // Load active location
    activeLocationId = parseInt(await getSetting('active_location_id', 3), 10) || 3;

    // Prefill saved cashier name if configured
    const savedCashierName = await getSetting('saved_cashier_name', '');
    const cashierInput = document.getElementById('cashier_name_input');
    if (cashierInput && savedCashierName && !cashierInput.value) {
        cashierInput.value = savedCashierName;
    }

    // Load local catalog
    await reloadLocalData();

    // Setup Barcode input
    const barcodeInput = document.getElementById('barcode-input');
    if (barcodeInput) {
        barcodeInput.focus();
        barcodeInput.addEventListener('keydown', handleBarcodeKeydown);
    }

    // Setup Customer Search Picker
    initCustomerSearchPicker();

    // Keep barcode focused on idle, close customer dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.customer-search-box')) {
            const dropdown = document.getElementById('customer_search_results');
            if (dropdown) dropdown.style.display = 'none';
        }
        if (
            !e.target.closest('input') &&
            !e.target.closest('select') &&
            !e.target.closest('textarea') &&
            !e.target.closest('button') &&
            !e.target.closest('.customer-search-box') &&
            !e.target.closest('.modal-overlay')
        ) {
            if (barcodeInput) barcodeInput.focus();
        }
    });

    // Auto-sync pull initial data from cloud if local DB is empty
    if (allProducts.length === 0) {
        window.syncEngine.syncAll();
    }
});

// Update Header Sync Badge
function updateSyncBadge(info) {
    const badge = document.getElementById('sync-status-badge');
    const text = document.getElementById('sync-status-text');
    if (!badge || !text) return;

    badge.className = 'sync-status-badge';
    if (info.isSyncing) {
        badge.classList.add('syncing');
        text.innerText = info.message || 'جاري المزامنة...';
    } else if (info.isOnline) {
        badge.classList.add('online');
        const pendingParts = [];
        if (info.pendingCount > 0) pendingParts.push(`${info.pendingCount} فواتير`);
        if (info.pendingCustomersCount > 0) pendingParts.push(`${info.pendingCustomersCount} عملاء`);
        const pendingMsg = pendingParts.length > 0 ? ` (${pendingParts.join(' و ')} معلقة)` : '';
        text.innerText = `متصل بالسحابة${pendingMsg}`;
    } else {
        badge.classList.add('offline');
        const pendingParts = [];
        if (info.pendingCount > 0) pendingParts.push(`${info.pendingCount} فواتير`);
        if (info.pendingCustomersCount > 0) pendingParts.push(`${info.pendingCustomersCount} عملاء`);
        const pendingMsg = pendingParts.length > 0 ? ` (${pendingParts.join(' و ')} معلقة)` : '';
        text.innerText = `أوفلاين${pendingMsg}`;
    }
}

// Reload Local Data from IndexedDB
async function reloadLocalData() {
    allProducts = (await getAllFromStore('products')).sort((a, b) => (a.name || '').localeCompare(b.name || '', 'ar'));
    allCustomers = (await getAllFromStore('customers')).sort((a, b) => (a.name || '').localeCompare(b.name || '', 'ar'));
    allOffers = await getAllFromStore('offers');
    allRecipes = (await getAllFromStore('recipes')).sort((a, b) => (a.name || '').localeCompare(b.name || '', 'ar'));
    formulaDefaults = await getAllFromStore('formula_defaults');

    renderBranchSelector();
    renderDirectProductsDropdown();
    renderCustomersDropdown();
    renderRecipesDropdown();
    renderOffersDropdown();
    renderMixBuilderDropdowns();
}

window.onCatalogRefreshed = async () => {
    await reloadLocalData();
};

// Render Branch Selector
async function renderBranchSelector() {
    const locations = (await getSetting('locations')) || [
        { id: 3, name: 'فرع المنوات' },
        { id: 2, name: 'فرع أم خنان' }
    ];

    const select = document.getElementById('pos-branch-select');
    if (!select) return;

    select.innerHTML = '';
    for (const loc of locations) {
        const opt = document.createElement('option');
        opt.value = loc.id;
        opt.innerText = `📍 ${loc.name}`;
        if (parseInt(loc.id, 10) === activeLocationId) {
            opt.selected = true;
            activeLocationName = loc.name;
        }
        select.appendChild(opt);
    }
}

async function onBranchChange(newLocId) {
    activeLocationId = parseInt(newLocId, 10);
    await setSetting('active_location_id', activeLocationId);
    // Find name
    const select = document.getElementById('pos-branch-select');
    if (select && select.selectedOptions[0]) {
        activeLocationName = select.selectedOptions[0].innerText.replace('📍', '').trim();
    }
    // Pull fresh data for selected branch
    window.syncEngine.syncAll();
}

// Render Direct Products in Dropdown
function renderDirectProductsDropdown() {
    const select = document.getElementById('add-prod-select');
    if (!select) return;

    select.innerHTML = '<option value="">-- اختر صنف مباشر أو ابحث --</option>';
    const nonRecipes = allProducts.filter(p => p.type !== 'recipe');

    for (const p of nonRecipes) {
        const opt = document.createElement('option');
        opt.value = p.id;
        const stock = parseFloat(p.branch_stock || 0);
        let stockLabel = ` [📦 ${stock.toFixed(2)}]`;
        if (stock <= 0) stockLabel = ' [⚠️ نفد المخزون]';

        opt.innerText = `${p.name} - (${parseFloat(p.sale_price).toFixed(2)} ج.م)${stockLabel}`;
        select.appendChild(opt);
    }
}

let customerSearchHighlightIdx = -1;

function normalizeArabicText(str) {
    return String(str || '')
        .replace(/[\u064B-\u065F\u0670]/g, '')
        .replace(/[أإآ]/g, 'ا')
        .replace(/ة/g, 'ه')
        .replace(/ى/g, 'ي')
        .toLowerCase()
        .trim();
}

function initCustomerSearchPicker() {
    const searchInput = document.getElementById('customer_search_input');
    const dropdown = document.getElementById('customer_search_results');
    if (!searchInput || !dropdown) return;

    searchInput.addEventListener('focus', () => {
        renderCustomerSearchResults(searchInput.value);
    });

    searchInput.addEventListener('input', () => {
        customerSearchHighlightIdx = -1;
        renderCustomerSearchResults(searchInput.value);
    });

    searchInput.addEventListener('keydown', (e) => {
        const items = dropdown.querySelectorAll('.cust-search-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (dropdown.style.display === 'none') {
                renderCustomerSearchResults(searchInput.value);
                return;
            }
            if (items.length > 0) {
                customerSearchHighlightIdx = Math.min(customerSearchHighlightIdx + 1, items.length - 1);
                updateCustomerSearchHighlight(items);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (items.length > 0) {
                customerSearchHighlightIdx = Math.max(customerSearchHighlightIdx - 1, 0);
                updateCustomerSearchHighlight(items);
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (dropdown.style.display !== 'none' && items.length > 0) {
                const targetIdx = customerSearchHighlightIdx >= 0 ? customerSearchHighlightIdx : 0;
                if (items[targetIdx]) items[targetIdx].click();
            } else {
                renderCustomerSearchResults(searchInput.value);
            }
        } else if (e.key === 'Escape') {
            dropdown.style.display = 'none';
            customerSearchHighlightIdx = -1;
        }
    });
}

function updateCustomerSearchHighlight(items) {
    items.forEach((el, idx) => {
        if (idx === customerSearchHighlightIdx) {
            el.classList.add('active');
            el.scrollIntoView({ block: 'nearest' });
        } else {
            el.classList.remove('active');
        }
    });
}

// Refresh selected customer badge & open dropdown if visible
function renderCustomersDropdown() {
    const hiddenInput = document.getElementById('customer_id_select');
    const labelEl = document.getElementById('customer_selected_label');
    const badgeEl = document.getElementById('customer_selected_badge');
    const clearBtn = document.getElementById('customer_clear_btn');
    if (!hiddenInput) return;

    const currentVal = hiddenInput.value;
    if (currentVal) {
        const found = allCustomers.find(c => String(c.id) === String(currentVal));
        if (found) {
            if (labelEl) {
                labelEl.textContent = `${found.name}${found.phone ? ' (' + found.phone + ')' : ''}`;
            }
            if (badgeEl) badgeEl.classList.add('has-customer');
            if (clearBtn) clearBtn.style.display = 'flex';
        }
    } else {
        if (labelEl) labelEl.textContent = 'زبون عابر (نقدي)';
        if (badgeEl) badgeEl.classList.remove('has-customer');
        if (clearBtn) clearBtn.style.display = 'none';
    }

    const dropdown = document.getElementById('customer_search_results');
    const searchInput = document.getElementById('customer_search_input');
    if (dropdown && dropdown.style.display !== 'none' && searchInput) {
        renderCustomerSearchResults(searchInput.value);
    }
}

function renderCustomerSearchResults(query = '') {
    const dropdown = document.getElementById('customer_search_results');
    if (!dropdown) return;

    const rawQuery = String(query || '').trim();
    const normQuery = normalizeArabicText(rawQuery);
    const digitsQuery = rawQuery.replace(/\D+/g, '');

    dropdown.innerHTML = '';

    // Show walk-in option at top when query is empty
    if (rawQuery === '') {
        const walkInEl = document.createElement('div');
        walkInEl.className = 'cust-search-item';
        const leftSpan = document.createElement('span');
        leftSpan.textContent = '🚶 زبون عابر (نقدي)';
        const rightSpan = document.createElement('span');
        rightSpan.style.cssText = 'font-size: 11px; color: var(--ink-muted);';
        rightSpan.textContent = 'افتراضي';
        walkInEl.appendChild(leftSpan);
        walkInEl.appendChild(rightSpan);
        walkInEl.addEventListener('click', () => clearSelectedCustomer());
        dropdown.appendChild(walkInEl);
    }

    let matches = allCustomers;
    if (normQuery !== '') {
        matches = allCustomers.filter(c => {
            const normName = normalizeArabicText(c.name);
            const rawPhone = String(c.phone || '');
            const cleanPhone = rawPhone.replace(/\D+/g, '');
            if (normName.includes(normQuery)) return true;
            if (rawPhone.includes(rawQuery)) return true;
            if (digitsQuery !== '' && cleanPhone.includes(digitsQuery)) return true;
            return false;
        });
    }

    const limitedMatches = matches.slice(0, 50);
    for (const c of limitedMatches) {
        const item = document.createElement('div');
        item.className = 'cust-search-item';

        const nameSpan = document.createElement('span');
        nameSpan.style.fontWeight = '700';
        nameSpan.textContent = `${c.name}${c.birthdate ? ' 🎂' : ''}${c.is_local_new ? ' (جديد أوفلاين)' : ''}`;

        const phoneSpan = document.createElement('span');
        phoneSpan.style.cssText = 'font-size: 11.5px; color: var(--gold-light); direction: ltr;';
        phoneSpan.textContent = c.phone || 'بدون هاتف';

        item.appendChild(nameSpan);
        item.appendChild(phoneSpan);
        item.addEventListener('click', () => selectCustomer(c.id));
        dropdown.appendChild(item);
    }

    if (rawQuery !== '') {
        const addEl = document.createElement('div');
        addEl.className = 'cust-search-item cust-add-action';
        addEl.textContent = `➕ إضافة عميل جديد: "${rawQuery}"`;
        addEl.addEventListener('click', () => {
            dropdown.style.display = 'none';
            openNewCustomerModal(rawQuery);
        });
        dropdown.appendChild(addEl);
    } else if (limitedMatches.length === 0) {
        const emptyEl = document.createElement('div');
        emptyEl.style.cssText = 'padding: 12px; text-align: center; font-size: 12px; color: var(--ink-muted);';
        emptyEl.textContent = 'لا يوجد عملاء مسجلون بعد';
        dropdown.appendChild(emptyEl);
    }

    dropdown.style.display = 'block';
    customerSearchHighlightIdx = -1;
}

function selectCustomer(custId) {
    const hiddenInput = document.getElementById('customer_id_select');
    const searchInput = document.getElementById('customer_search_input');
    const dropdown = document.getElementById('customer_search_results');
    const labelEl = document.getElementById('customer_selected_label');
    const badgeEl = document.getElementById('customer_selected_badge');
    const clearBtn = document.getElementById('customer_clear_btn');

    const found = allCustomers.find(c => String(c.id) === String(custId));
    if (!found) {
        clearSelectedCustomer();
        return;
    }

    if (hiddenInput) hiddenInput.value = String(found.id);
    if (searchInput) searchInput.value = '';
    if (dropdown) dropdown.style.display = 'none';
    if (labelEl) {
        labelEl.textContent = `${found.name}${found.phone ? ' (' + found.phone + ')' : ''}${found.birthdate ? ' 🎂' : ''}`;
    }
    if (badgeEl) badgeEl.classList.add('has-customer');
    if (clearBtn) clearBtn.style.display = 'flex';
    customerSearchHighlightIdx = -1;
}

function clearSelectedCustomer() {
    const hiddenInput = document.getElementById('customer_id_select');
    const searchInput = document.getElementById('customer_search_input');
    const dropdown = document.getElementById('customer_search_results');
    const labelEl = document.getElementById('customer_selected_label');
    const badgeEl = document.getElementById('customer_selected_badge');
    const clearBtn = document.getElementById('customer_clear_btn');

    if (hiddenInput) hiddenInput.value = '';
    if (searchInput) searchInput.value = '';
    if (dropdown) dropdown.style.display = 'none';
    if (labelEl) labelEl.textContent = 'زبون عابر (نقدي)';
    if (badgeEl) badgeEl.classList.remove('has-customer');
    if (clearBtn) clearBtn.style.display = 'none';
    customerSearchHighlightIdx = -1;
}

// Render Recipes in Dropdown
function renderRecipesDropdown() {
    const select = document.getElementById('add-recipe-select');
    if (!select) return;

    select.innerHTML = '<option value="">-- اختر تركيبة جاهزة --</option>';
    for (const r of allRecipes) {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.innerText = `${r.name} - (${parseFloat(r.default_sale_price || r.sale_price || 0).toFixed(2)} ج.م)`;
        select.appendChild(opt);
    }
}

// Render Offers in Dropdown
function renderOffersDropdown() {
    const select = document.getElementById('add-offer-select');
    if (!select) return;

    select.innerHTML = '<option value="">-- اختر عرضاً ترويجياً --</option>';
    for (const o of allOffers) {
        const opt = document.createElement('option');
        opt.value = o.id;
        opt.innerText = `🎁 ${o.name} - (${parseFloat(o.price_after).toFixed(2)} بدلاً من ${parseFloat(o.price_before).toFixed(2)})`;
        select.appendChild(opt);
    }
}

// Render Bottles and Oils for Mix Builder
function renderMixBuilderDropdowns() {
    const bottleSelect = document.getElementById('mix-bottle-select');
    const oilSelect = document.getElementById('mix-oil-select');

    if (bottleSelect) {
        bottleSelect.innerHTML = '<option value="">-- اختر الزجاجة --</option><option value="no_bottle">بدون زجاجة (زيت فقط)</option>';
        const bottles = allProducts.filter(p => p.type === 'bottle');
        for (const b of bottles) {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.innerText = `${b.name} (${b.size_ml ? b.size_ml + ' مل' : ''}) - (${parseFloat(b.sale_price).toFixed(2)} ج.م)`;
            bottleSelect.appendChild(opt);
        }
    }

    if (oilSelect) {
        oilSelect.innerHTML = '<option value="">-- اختر زيت عطري --</option>';
        const oils = allProducts.filter(p => p.type === 'perfume_gram');
        for (const o of oils) {
            const opt = document.createElement('option');
            opt.value = o.id;
            const price = parseFloat(o.price_per_gram || o.sale_price || 0);
            opt.innerText = `${o.name} - (${price.toFixed(2)} ج.م/جم)`;
            oilSelect.appendChild(opt);
        }
    }
}

// ===== Barcode Scanner Handler =====
async function handleBarcodeKeydown(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = e.target.value.trim();
        e.target.value = '';
        if (!code) return;

        const found = await findProductByBarcodeLocal(code);
        if (found) {
            playBeep('success');
            if (found.is_offer) {
                addOfferToCartDirect(found);
            } else {
                addProductToCartDirect(found, 1);
            }
            showBarcodeFeedback(`✅ تم مسح: ${found.name}`, 'success');
        } else {
            playBeep('error');
            showBarcodeFeedback(`⚠️ صنف غير موجود: ${code}`, 'error');
        }
    }
}

function showBarcodeFeedback(msg, type) {
    const el = document.getElementById('barcode-feedback');
    if (!el) return;
    el.innerText = msg;
    el.style.color = type === 'success' ? '#10B981' : '#EF4444';
    setTimeout(() => {
        if (el.innerText === msg) el.innerText = '';
    }, 3000);
}

// ===== Cart Management =====
function addProductToCartDirect(product, qty = 1) {
    const existingIndex = cart.findIndex(c => c.type === 'product' && c.product_id === product.id && !c.is_gift);
    if (existingIndex > -1) {
        cart[existingIndex].quantity += qty;
    } else {
        cart.push({
            type: 'product',
            product_id: product.id,
            name: product.name,
            quantity: qty,
            unit_price: parseFloat(product.sale_price || 0),
            discount_type: '',
            discount_value: 0,
            is_gift: false
        });
    }
    renderCart();
}

function addProductFromForm() {
    const select = document.getElementById('add-prod-select');
    const pId = parseInt(select.value, 10);
    if (!pId) {
        alert('يرجى اختيار صنف أولاً');
        return;
    }
    const product = allProducts.find(p => p.id === pId);
    if (!product) return;

    const qty = parseFloat(document.getElementById('add-prod-qty').value || 1);
    const customPrice = document.getElementById('add-prod-price').value;
    const unitPrice = customPrice !== '' ? parseFloat(customPrice) : parseFloat(product.sale_price || 0);
    const discountType = document.getElementById('add-prod-discount-type').value;
    const discountVal = parseFloat(document.getElementById('add-prod-discount-value').value || 0);
    const isGift = document.getElementById('add-prod-is-gift').checked;

    cart.push({
        type: 'product',
        product_id: product.id,
        name: product.name,
        quantity: qty,
        unit_price: isGift ? 0 : unitPrice,
        discount_type: isGift ? '' : discountType,
        discount_value: isGift ? 0 : discountVal,
        is_gift: isGift
    });

    playBeep('success');
    renderCart();
    select.value = '';
    document.getElementById('add-prod-qty').value = '1';
    document.getElementById('add-prod-price').value = '';
    document.getElementById('add-prod-discount-value').value = '';
    document.getElementById('add-prod-is-gift').checked = false;
}

function addRecipeFromForm() {
    const select = document.getElementById('add-recipe-select');
    const rId = parseInt(select.value, 10);
    if (!rId) {
        alert('يرجى اختيار تركيبة أولاً');
        return;
    }
    const recipe = allRecipes.find(r => r.id === rId);
    if (!recipe) return;

    const customPrice = document.getElementById('add-recipe-price').value;
    const price = customPrice !== '' ? parseFloat(customPrice) : parseFloat(recipe.default_sale_price || recipe.sale_price || 0);

    cart.push({
        type: 'recipe',
        recipe_id: recipe.id,
        name: `وصفة: ${recipe.name}`,
        quantity: 1,
        unit_price: price,
        discount_type: '',
        discount_value: 0
    });

    playBeep('success');
    renderCart();
    select.value = '';
    document.getElementById('add-recipe-price').value = '';
}

function addOfferToCartDirect(offer) {
    cart.push({
        type: 'offer',
        offer_id: offer.id,
        name: `عرض: ${offer.name}`,
        quantity: 1,
        unit_price: parseFloat(offer.price_after || 0),
        discount_type: '',
        discount_value: 0
    });
    renderCart();
}

function addOfferFromForm() {
    const select = document.getElementById('add-offer-select');
    const oId = parseInt(select.value, 10);
    if (!oId) {
        alert('يرجى اختيار عرض أولاً');
        return;
    }
    const offer = allOffers.find(o => o.id === oId);
    if (!offer) return;

    const qty = parseFloat(document.getElementById('add-offer-qty').value || 1);
    const customPrice = document.getElementById('add-offer-price').value;
    const price = customPrice !== '' ? parseFloat(customPrice) : parseFloat(offer.price_after || 0);

    cart.push({
        type: 'offer',
        offer_id: offer.id,
        name: `عرض: ${offer.name}`,
        quantity: qty,
        unit_price: price,
        discount_type: '',
        discount_value: 0
    });

    playBeep('success');
    renderCart();
    select.value = '';
    document.getElementById('add-offer-qty').value = '1';
    document.getElementById('add-offer-price').value = '';
}

// ===== Custom Instant Mix Builder =====
let currentMixOils = [];

function addOilToMixBuilder() {
    const oilSelect = document.getElementById('mix-oil-select');
    const oId = parseInt(oilSelect.value, 10);
    if (!oId) return;

    const oil = allProducts.find(p => p.id === oId);
    if (!oil) return;

    const gramsInput = document.getElementById('mix-oil-grams');
    const grams = parseFloat(gramsInput.value || 1);

    currentMixOils.push({
        product_id: oil.id,
        name: oil.name,
        grams: grams,
        price_per_gram: parseFloat(oil.price_per_gram || oil.sale_price || 0)
    });

    oilSelect.value = '';
    gramsInput.value = '';
    renderMixOilsList();
    recalculateMixPrice();
}

function removeOilFromMix(idx) {
    currentMixOils.splice(idx, 1);
    renderMixOilsList();
    recalculateMixPrice();
}

function renderMixOilsList() {
    const list = document.getElementById('mix-oils-list');
    if (!list) return;

    list.innerHTML = '';
    currentMixOils.forEach((oil, idx) => {
        const item = document.createElement('div');
        item.style.cssText = 'display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.25); padding: 5px 10px; border-radius: 6px; margin-top: 4px; font-size: 12.5px;';
        item.innerHTML = `
            <span>🧴 ${oil.name} — <strong>${oil.grams} جم</strong> (${(oil.grams * oil.price_per_gram).toFixed(2)} ج.م)</span>
            <button type="button" onclick="removeOilFromMix(${idx})" style="background: none; border: none; color: #EF4444; cursor: pointer; font-size: 14px;">✖</button>
        `;
        list.appendChild(item);
    });
}

function recalculateMixPrice() {
    let total = 0;
    const bottleSelect = document.getElementById('mix-bottle-select');
    const bottleId = bottleSelect.value;
    if (bottleId && bottleId !== 'no_bottle') {
        const bottle = allProducts.find(p => p.id === parseInt(bottleId, 10));
        if (bottle) {
            total += parseFloat(bottle.sale_price || 0);
        }
    }
    for (const o of currentMixOils) {
        total += o.grams * o.price_per_gram;
    }
    const priceInput = document.getElementById('mix-sale-price');
    if (priceInput && priceInput.value === '') {
        priceInput.placeholder = total.toFixed(2);
    }
}

function addMixToCart() {
    if (currentMixOils.length === 0) {
        alert('يرجى إضافة زيت عطري واحد على الأقل للتركيبة');
        return;
    }
    const bottleSelect = document.getElementById('mix-bottle-select');
    const bottleId = bottleSelect.value;
    if (!bottleId) {
        alert('يرجى اختيار الزجاجة أو تحديد بدون زجاجة');
        return;
    }

    let bottleName = 'بدون زجاجة';
    let bottleObj = null;
    if (bottleId !== 'no_bottle') {
        bottleObj = allProducts.find(p => p.id === parseInt(bottleId, 10));
        bottleName = bottleObj ? bottleObj.name : 'زجاجة';
    }

    const priceInput = document.getElementById('mix-sale-price');
    let salePrice = parseFloat(priceInput.value);
    if (isNaN(salePrice) || salePrice <= 0) {
        salePrice = parseFloat(priceInput.placeholder || 0);
    }

    const oilParts = currentMixOils.map(o => `${o.name} (${o.grams}جم)`).join(' + ');
    const desc = `تركيبة فورية: ${bottleName} [${oilParts}]`;

    cart.push({
        type: 'mix',
        name: desc,
        quantity: 1,
        unit_price: salePrice,
        discount_type: '',
        discount_value: 0,
        mix_data: {
            bottle_id: bottleId !== 'no_bottle' ? parseInt(bottleId, 10) : 0,
            without_bottle: bottleId === 'no_bottle',
            sale_price: salePrice,
            components: currentMixOils.map(o => ({ product_id: o.product_id, grams: o.grams }))
        }
    });

    playBeep('success');
    renderCart();

    // Reset mix builder
    currentMixOils = [];
    renderMixOilsList();
    bottleSelect.value = '';
    priceInput.value = '';
    priceInput.placeholder = '0.00';
}

// ===== Cart Rendering & Totals =====
function renderCart() {
    const tbody = document.getElementById('cart-tbody');
    if (!tbody) return;

    if (cart.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="muted" style="text-align: center; padding: 30px; color: var(--ink-muted);">السلة فارغة. قم باختيار صنف أو مسح الباركود بالأعلى.</td></tr>';
        updateTotals();
        return;
    }

    tbody.innerHTML = '';
    cart.forEach((item, idx) => {
        const gross = item.quantity * item.unit_price;
        let lineDiscount = 0;
        if (item.discount_type === 'percent') {
            lineDiscount = gross * (item.discount_value / 100);
        } else if (item.discount_type === 'amount') {
            lineDiscount = item.discount_value;
        }
        const lineTotal = Math.max(0, gross - lineDiscount);

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <div style="font-weight: 800; color: var(--ink);">${item.name}</div>
                ${item.is_gift ? '<span style="background: #10B981; color: #000; font-size: 10px; font-weight: 800; padding: 1px 6px; border-radius: 4px;">هدية</span>' : ''}
            </td>
            <td style="text-align: center;">
                <input type="number" step="any" min="0.000001" value="${item.quantity}" onchange="updateCartItemQty(${idx}, this.value)" style="width: 75px; text-align: center; padding: 4px; border-radius: 6px;">
            </td>
            <td style="text-align: center;">
                <input type="number" step="any" min="0" value="${item.unit_price}" onchange="updateCartItemPrice(${idx}, this.value)" style="width: 75px; text-align: center; padding: 4px; border-radius: 6px;">
            </td>
            <td style="text-align: center; font-size: 12px; color: var(--danger);">
                ${lineDiscount > 0 ? '-' + lineDiscount.toFixed(2) + ' ج.م' : '—'}
            </td>
            <td style="text-align: left; font-weight: 900; color: var(--gold-light);">
                ${lineTotal.toFixed(2)} ج.م
            </td>
            <td style="text-align: center;">
                <button type="button" onclick="removeCartItem(${idx})" style="background: none; border: none; color: #EF4444; font-size: 16px; cursor: pointer;">✖</button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    updateTotals();
}

function updateCartItemQty(idx, val) {
    const qty = parseFloat(val);
    if (qty > 0 && cart[idx]) {
        cart[idx].quantity = qty;
        renderCart();
    }
}

function updateCartItemPrice(idx, val) {
    const price = parseFloat(val);
    if (!isNaN(price) && cart[idx]) {
        cart[idx].unit_price = Math.max(0, price);
        renderCart();
    }
}

function removeCartItem(idx) {
    cart.splice(idx, 1);
    renderCart();
}

function updateTotals() {
    let subtotal = 0;
    cart.forEach(item => {
        const gross = item.quantity * item.unit_price;
        let lineDiscount = 0;
        if (item.discount_type === 'percent') {
            lineDiscount = gross * (item.discount_value / 100);
        } else if (item.discount_type === 'amount') {
            lineDiscount = item.discount_value;
        }
        subtotal += Math.max(0, gross - lineDiscount);
    });

    const discountType = document.getElementById('invoice_discount_type').value;
    const discountVal = parseFloat(document.getElementById('invoice_discount_value').value || 0);
    let invoiceDiscount = 0;

    if (discountType === 'percent') {
        invoiceDiscount = subtotal * (discountVal / 100);
    } else if (discountType === 'amount') {
        invoiceDiscount = discountVal;
    }
    invoiceDiscount = Math.min(subtotal, invoiceDiscount);

    const total = Math.max(0, subtotal - invoiceDiscount);
    const paidInput = document.getElementById('paid_total_input');

    if (paidInput && (paidInput.value === '' || paidInput.dataset.autoFilled === 'true')) {
        paidInput.value = Math.round(total);
        paidInput.dataset.autoFilled = 'true';
    }

    const paidTotal = parseFloat(paidInput ? paidInput.value : total) || 0;
    const due = Math.max(0, total - paidTotal);
    const change = Math.max(0, paidTotal - total);

    document.getElementById('lbl-subtotal').innerText = subtotal.toFixed(2) + ' ج.م';
    document.getElementById('lbl-discount').innerText = invoiceDiscount.toFixed(2) + ' ج.م';
    document.getElementById('lbl-total').innerText = total.toFixed(2) + ' ج.م';
    document.getElementById('lbl-due').innerText = due.toFixed(2) + ' ج.م';
    document.getElementById('lbl-change').innerText = change.toFixed(2) + ' ج.م';
}

function switchTab(tabId) {
    const tabs = ['direct', 'recipe', 'mix', 'offer'];
    tabs.forEach(t => {
        const panel = document.getElementById(`panel-${t}`);
        const btn = document.getElementById(`tab-btn-${t}`);
        if (panel) panel.style.display = t === tabId ? 'grid' : 'none';
        if (btn) btn.className = t === tabId ? 'tab-btn active' : 'tab-btn';
    });
}

// ===== Quick Add Customer Modal =====
function openNewCustomerModal(prefill = '') {
    const modal = document.getElementById('modal-new-customer');
    const nameInput = document.getElementById('new-cust-name');
    const phoneInput = document.getElementById('new-cust-phone');
    const bdateInput = document.getElementById('new-cust-birthdate');

    if (nameInput) nameInput.value = '';
    if (phoneInput) phoneInput.value = '';
    if (bdateInput) bdateInput.value = '';

    const cleanPrefill = String(prefill || '').trim();
    if (cleanPrefill) {
        if (/^[0-9+\s-]{5,}$/.test(cleanPrefill)) {
            if (phoneInput) phoneInput.value = cleanPrefill;
        } else {
            if (nameInput) nameInput.value = cleanPrefill;
        }
    }

    if (modal) modal.style.display = 'flex';
    if (cleanPrefill && /^[0-9+\s-]{5,}$/.test(cleanPrefill)) {
        if (nameInput) nameInput.focus();
    } else if (cleanPrefill) {
        if (phoneInput) phoneInput.focus();
    } else if (nameInput) {
        nameInput.focus();
    }
}

function closeNewCustomerModal() {
    document.getElementById('modal-new-customer').style.display = 'none';
}

async function saveNewCustomer() {
    const name = document.getElementById('new-cust-name').value.trim();
    const phone = document.getElementById('new-cust-phone').value.trim();
    const birthdate = (document.getElementById('new-cust-birthdate')?.value || '').trim();

    if (!name) {
        alert('يرجى إدخال اسم العميل');
        return;
    }

    const localUuid = 'cust_' + Date.now().toString(36) + '_' + Math.random().toString(36).substr(2, 6);
    const newCust = {
        name,
        phone,
        birthdate: birthdate || '',
        notes: 'تمت الإضافة من كاشير سطح المكتب (أوفلاين)',
        is_local_new: true,
        local_uuid: localUuid
    };

    const id = await putInStore('customers', newCust);
    newCust.id = id;
    allCustomers.push(newCust);
    allCustomers.sort((a, b) => (a.name || '').localeCompare(b.name || '', 'ar'));

    // Queue for cloud sync when internet returns
    const pendingCusts = (await getSetting('pending_customers', [])) || [];
    pendingCusts.push({ ...newCust });
    await setSetting('pending_customers', pendingCusts);

    renderCustomersDropdown();
    selectCustomer(id);

    closeNewCustomerModal();
    document.getElementById('new-cust-name').value = '';
    document.getElementById('new-cust-phone').value = '';
    if (document.getElementById('new-cust-birthdate')) {
        document.getElementById('new-cust-birthdate').value = '';
    }
    playBeep('success');

    // Sync immediately if online
    window.syncEngine.syncAll();
}

// ===== Checkout Flow & Offline Invoice Creation =====
async function processCheckout() {
    if (cart.length === 0) {
        alert('السلة فارغة!');
        return;
    }

    const custSelect = document.getElementById('customer_id_select');
    const custVal = custSelect ? custSelect.value : '';
    const custId = parseInt(custVal, 10) || null;
    let foundCust = null;
    let custName = 'زبون عابر';
    let custPhone = '';

    if (custVal) {
        foundCust = allCustomers.find(c => String(c.id) === String(custVal)) || null;
        if (foundCust) {
            custName = foundCust.name;
            custPhone = foundCust.phone || '';
        }
    }

    // Calculate totals
    let subtotal = 0;
    cart.forEach(item => {
        const gross = item.quantity * item.unit_price;
        let lineDiscount = 0;
        if (item.discount_type === 'percent') lineDiscount = gross * (item.discount_value / 100);
        else if (item.discount_type === 'amount') lineDiscount = item.discount_value;
        subtotal += Math.max(0, gross - lineDiscount);
    });

    const discountType = document.getElementById('invoice_discount_type').value;
    const discountVal = parseFloat(document.getElementById('invoice_discount_value').value || 0);
    let invoiceDiscount = 0;
    if (discountType === 'percent') invoiceDiscount = subtotal * (discountVal / 100);
    else if (discountType === 'amount') invoiceDiscount = discountVal;
    invoiceDiscount = Math.min(subtotal, invoiceDiscount);

    const total = Math.max(0, subtotal - invoiceDiscount);
    const paidRaw = document.getElementById('paid_total_input').value;
    const paidInput = paidRaw !== '' ? parseFloat(paidRaw) : total;
    if ((total - paidInput) > 0.01 && !foundCust) {
        alert('يجب اختيار عميل أو إضافة عميل جديد عند وجود متبقي (دين) على الفاتورة!');
        return;
    }

    const paymentMethod = document.getElementById('payment_method_select').value;
    const notes = document.getElementById('invoice_notes').value;
    const packerName = document.getElementById('packer_name_input').value;
    const cashierName = document.getElementById('cashier_name_input')?.value || '';

    const offlineUuid = generateUUID();
    const nowIso = new Date().toISOString();
    const offlineInvNumber = 'OFF-' + new Date().toISOString().replace(/\D/g, '').slice(0, 14) + '-' + Math.floor(Math.random() * 1000);

    // Build payload
    const offlineInvoice = {
        offline_uuid: offlineUuid,
        offline_number: offlineInvNumber,
        created_at: nowIso,
        location_id: activeLocationId,
        customer_id: (foundCust && !foundCust.is_local_new) ? (parseInt(foundCust.id, 10) || 0) : 0,
        customer_local_id: foundCust ? foundCust.id : null,
        customer_name: custName,
        customer_phone: custPhone,
        customer_birthdate: foundCust ? (foundCust.birthdate || '') : '',
        customer_is_local_new: !!(foundCust && foundCust.is_local_new),
        payment_method: paymentMethod,
        subtotal: subtotal,
        discount_type: discountType,
        discount_value: discountVal,
        discount_amount: invoiceDiscount,
        total: total,
        paid_total: paidInput,
        paid_cash: paymentMethod === 'cash' ? paidInput : (paymentMethod.startsWith('mixed') ? parseFloat(document.getElementById('paid_cash_input')?.value || 0) : 0),
        paid_secondary: paymentMethod.startsWith('mixed') ? parseFloat(document.getElementById('paid_secondary_input')?.value || 0) : 0,
        notes: notes,
        packer_name: packerName,
        cashier_name: cashierName,
        items: cart.filter(c => c.type === 'product'),
        mixes: cart.filter(c => c.type === 'mix').map(c => c.mix_data),
        recipes: cart.filter(c => c.type === 'recipe'),
        offers: cart.filter(c => c.type === 'offer')
    };

    // 1. Save to IndexedDB pending_invoices
    await putInStore('pending_invoices', offlineInvoice);

    // 2. Immediately decrement local stock for products so local inventory remains accurate
    for (const item of cart) {
        if (item.type === 'product') {
            await decrementLocalStock(item.product_id, item.quantity);
        } else if (item.type === 'mix' && item.mix_data) {
            if (item.mix_data.bottle_id) {
                await decrementLocalStock(item.mix_data.bottle_id, 1);
            }
            if (item.mix_data.components) {
                for (const comp of item.mix_data.components) {
                    await decrementLocalStock(comp.product_id, comp.grams);
                }
            }
        }
    }

    playBeep('checkout');

    // 3. Print 80mm thermal receipt
    printThermalReceipt(offlineInvoice, cart);

    // 4. Reset Cart & Checkout Inputs
    cart = [];
    document.getElementById('invoice_notes').value = '';
    document.getElementById('packer_name_input').value = '';
    const savedCashierName = await getSetting('saved_cashier_name', '');
    if (document.getElementById('cashier_name_input')) {
        document.getElementById('cashier_name_input').value = savedCashierName || cashierName || '';
    }
    document.getElementById('invoice_discount_value').value = '';
    document.getElementById('paid_total_input').value = '';
    clearSelectedCustomer();
    renderCart();

    // 5. Trigger sync in background if online
    window.syncEngine.syncAll();
}

// ===== 80mm Thermal Receipt Generator =====
function printThermalReceipt(invoice, items) {
    const receiptHtml = generateReceiptHtml(invoice, items);

    if (window.electronAPI && window.electronAPI.printReceipt) {
        // Direct silent thermal printing via Electron main process
        window.electronAPI.printReceipt(receiptHtml, { silent: false });
    } else {
        // Fallback for browser view
        const printDiv = document.getElementById('thermal-receipt');
        if (printDiv) {
            printDiv.innerHTML = receiptHtml;
            window.print();
        }
    }
}

function generateReceiptHtml(inv, items) {
    const dateFormatted = new Date(inv.created_at).toLocaleString('ar-EG', { timeZone: 'Africa/Cairo' });
    const itemsRows = items.map(i => `
        <tr style="border-bottom: 1px dashed #ccc;">
            <td style="padding: 4px 0; font-size: 11px; text-align: right;">${i.name}</td>
            <td style="padding: 4px 0; font-size: 11px; text-align: center;">${i.quantity}</td>
            <td style="padding: 4px 0; font-size: 11px; text-align: left;">${(i.quantity * i.unit_price).toFixed(2)}</td>
        </tr>
    `).join('');

    return `
    <div style="font-family: 'Cairo', monospace, sans-serif; direction: rtl; text-align: right; width: 72mm; margin: 0 auto; color: #000; font-size: 12px; line-height: 1.4;">
        <div style="text-align: center; border-bottom: 2px dashed #000; padding-bottom: 8px; margin-bottom: 8px;">
            <div style="font-size: 18px; font-weight: 900; letter-spacing: 0.5px;">حمزة للعطور</div>
            <div style="font-size: 12px; font-weight: bold; margin-top: 2px;">Hamza for Perfumes</div>
            <div style="font-size: 11px; margin-top: 4px;">📍 ${activeLocationName}</div>
            <div style="font-size: 10px; color: #444;">إيصال كاشير (نقطة بيع)</div>
        </div>

        <div style="font-size: 10.5px; margin-bottom: 8px; border-bottom: 1px solid #ddd; padding-bottom: 6px;">
            <div><strong>رقم الإيصال:</strong> ${inv.offline_number}</div>
            <div><strong>التاريخ:</strong> ${dateFormatted}</div>
            ${inv.cashier_name ? `<div><strong>الكاشير:</strong> ${inv.cashier_name}</div>` : ''}
            ${inv.packer_name ? `<div><strong>المقفل:</strong> ${inv.packer_name}</div>` : ''}
            <div><strong>العميل:</strong> ${inv.customer_name} ${inv.customer_phone ? '(' + inv.customer_phone + ')' : ''}</div>
            <div><strong>طريقة الدفع:</strong> ${inv.payment_method === 'cash' ? 'نقداً (كاش)' : inv.payment_method}</div>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
            <thead>
                <tr style="border-bottom: 1.5px solid #000; font-size: 11px;">
                    <th style="text-align: right; padding-bottom: 4px;">الصنف</th>
                    <th style="text-align: center; padding-bottom: 4px;">الكمية</th>
                    <th style="text-align: left; padding-bottom: 4px;">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                ${itemsRows}
            </tbody>
        </table>

        <div style="border-top: 1.5px dashed #000; padding-top: 6px; font-size: 12px;">
            <div style="display: flex; justify-content: space-between;">
                <span>المجموع:</span>
                <strong>${inv.subtotal.toFixed(2)} ج.م</strong>
            </div>
            ${inv.discount_amount > 0 ? `
            <div style="display: flex; justify-content: space-between; color: #c00;">
                <span>الخصم:</span>
                <strong>-${inv.discount_amount.toFixed(2)} ج.م</strong>
            </div>` : ''}
            <div style="display: flex; justify-content: space-between; font-size: 15px; font-weight: 900; margin-top: 4px; border-top: 1px solid #000; padding-top: 4px;">
                <span>المطلوب:</span>
                <span>${inv.total.toFixed(2)} ج.م</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 12px; margin-top: 3px;">
                <span>المدفوع:</span>
                <strong>${inv.paid_total.toFixed(2)} ج.م</strong>
            </div>
            ${(inv.total - inv.paid_total) > 0 ? `
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #c00;">
                <span>المتبقي (دين):</span>
                <strong>${(inv.total - inv.paid_total).toFixed(2)} ج.م</strong>
            </div>` : ''}
            ${(inv.paid_total - inv.total) > 0 ? `
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: #080;">
                <span>الفكة للعميل:</span>
                <strong>${(inv.paid_total - inv.total).toFixed(2)} ج.م</strong>
            </div>` : ''}
        </div>

        <div style="text-align: center; border-top: 2px dashed #000; margin-top: 12px; padding-top: 10px; font-size: 10.5px;">
            <div style="font-weight: 800; font-size: 12px;">شكراً لزيارتكم حمزة للعطور ❤️</div>
            <div>يسعدنا دائماً خدمتكم بأجود أنواع العطور</div>
        </div>
    </div>
    `;
}

// ===== Settings & Sync Modal =====
async function openSettingsModal() {
    const input = document.getElementById('settings-server-url');
    if (input) {
        input.value = window.syncEngine.serverUrl;
    }
    const statusEl = document.getElementById('settings-auth-status');
    if (statusEl) {
        const token = await window.syncEngine.getAuthToken();
        const savedCashier = await getSetting('saved_cashier_name', '');
        if (token) {
            statusEl.style.color = '#34D399';
            statusEl.textContent = `✅ الحساب مربوط للمزامنة التلقائية${savedCashier ? ' (' + savedCashier + ')' : ''}`;
        } else {
            statusEl.style.color = '#FBBF24';
            statusEl.textContent = '⚠️ أدخل اسم المستخدم وكلمة المرور لربط المزامنة مع السيرفر السحابي';
        }
    }
    document.getElementById('modal-settings').style.display = 'flex';
}

function closeSettingsModal() {
    document.getElementById('modal-settings').style.display = 'none';
}

async function saveSettingsFromModal() {
    const input = document.getElementById('settings-server-url');
    if (input && input.value) {
        window.syncEngine.setServerUrl(input.value.trim());
    }

    const username = (document.getElementById('settings-username')?.value || '').trim();
    const password = document.getElementById('settings-password')?.value || '';
    const statusEl = document.getElementById('settings-auth-status');

    if (username && password) {
        try {
            const loginRes = await window.syncEngine.login(username, password);
            if (loginRes && loginRes.success) {
                const cashierInput = document.getElementById('cashier_name_input');
                if (cashierInput && loginRes.user?.name) {
                    cashierInput.value = loginRes.user.name;
                }
                if (document.getElementById('settings-password')) {
                    document.getElementById('settings-password').value = '';
                }
            }
        } catch (err) {
            if (statusEl) {
                statusEl.style.color = '#EF4444';
                statusEl.textContent = '❌ فشل تسجيل الدخول للمزامنة: ' + err.message;
            }
            alert('تعذر تسجيل الدخول للمزامنة: ' + err.message);
            return;
        }
    }

    closeSettingsModal();
    window.syncEngine.syncAll(true);
}
