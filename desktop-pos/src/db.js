/**
 * Hamza for Perfumes — Offline POS IndexedDB Storage Engine
 * Provides persistent local storage that works 100% offline.
 */

const DB_NAME = 'HamzaPerfumesPOS';
const DB_VERSION = 1;

let dbInstance = null;

function openPOSDatabase() {
    return new Promise((resolve, reject) => {
        if (dbInstance) {
            resolve(dbInstance);
            return;
        }

        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            // Products store
            if (!db.objectStoreNames.contains('products')) {
                const prodStore = db.createObjectStore('products', { keyPath: 'id' });
                prodStore.createIndex('barcode', 'barcode', { unique: false });
                prodStore.createIndex('sku', 'sku', { unique: false });
                prodStore.createIndex('type', 'type', { unique: false });
            }

            // Customers store
            if (!db.objectStoreNames.contains('customers')) {
                const custStore = db.createObjectStore('customers', { keyPath: 'id', autoIncrement: true });
                custStore.createIndex('phone', 'phone', { unique: false });
            }

            // Offers store
            if (!db.objectStoreNames.contains('offers')) {
                db.createObjectStore('offers', { keyPath: 'id' });
            }

            // Recipes store
            if (!db.objectStoreNames.contains('recipes')) {
                db.createObjectStore('recipes', { keyPath: 'id' });
            }

            // Formula Defaults store
            if (!db.objectStoreNames.contains('formula_defaults')) {
                db.createObjectStore('formula_defaults', { keyPath: 'id', autoIncrement: true });
            }

            // Pending (Unsynced) Invoices queue
            if (!db.objectStoreNames.contains('pending_invoices')) {
                const pendingStore = db.createObjectStore('pending_invoices', { keyPath: 'offline_uuid' });
                pendingStore.createIndex('created_at', 'created_at', { unique: false });
            }

            // Synced Invoices archive
            if (!db.objectStoreNames.contains('synced_invoices')) {
                const syncedStore = db.createObjectStore('synced_invoices', { keyPath: 'offline_uuid' });
                syncedStore.createIndex('synced_at', 'synced_at', { unique: false });
            }

            // App Settings
            if (!db.objectStoreNames.contains('settings')) {
                db.createObjectStore('settings', { keyPath: 'key' });
            }
        };

        request.onsuccess = (event) => {
            dbInstance = event.target.result;
            resolve(dbInstance);
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };
    });
}

// ===== Generic Store Helpers =====

async function getAllFromStore(storeName) {
    const db = await openPOSDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readonly');
        const store = tx.objectStore(storeName);
        const req = store.getAll();
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => reject(req.error);
    });
}

async function getFromStore(storeName, key) {
    const db = await openPOSDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readonly');
        const store = tx.objectStore(storeName);
        const req = store.get(key);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function putInStore(storeName, item) {
    const db = await openPOSDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        const req = store.put(item);
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function bulkPutInStore(storeName, items) {
    const db = await openPOSDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        for (const item of items) {
            store.put(item);
        }
        tx.oncomplete = () => resolve(true);
        tx.onerror = () => reject(tx.error);
    });
}

async function deleteFromStore(storeName, key) {
    const db = await openPOSDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        const req = store.delete(key);
        req.onsuccess = () => resolve(true);
        req.onerror = () => reject(req.error);
    });
}

async function clearStore(storeName) {
    const db = await openPOSDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(storeName, 'readwrite');
        const store = tx.objectStore(storeName);
        const req = store.clear();
        req.onsuccess = () => resolve(true);
        req.onerror = () => reject(req.error);
    });
}

// ===== High-level Domain Methods =====

/**
 * Saves all pulled catalog data from the server into local storage
 */
async function saveCatalogData(data) {
    if (data.products && Array.isArray(data.products)) {
        await bulkPutInStore('products', data.products);
    }
    if (data.customers && Array.isArray(data.customers)) {
        await bulkPutInStore('customers', data.customers);
    }
    if (data.offers && Array.isArray(data.offers)) {
        await bulkPutInStore('offers', data.offers);
    }
    if (data.recipes && Array.isArray(data.recipes)) {
        await bulkPutInStore('recipes', data.recipes);
    }
    if (data.formula_defaults && Array.isArray(data.formula_defaults)) {
        await clearStore('formula_defaults');
        await bulkPutInStore('formula_defaults', data.formula_defaults);
    }
    if (data.locations) {
        await setSetting('locations', data.locations);
    }
    if (data.server_time) {
        await setSetting('last_catalog_sync', data.server_time);
    }
}

/**
 * Decrements local stock in IndexedDB immediately upon making an offline sale
 */
async function decrementLocalStock(productId, qty) {
    try {
        const prod = await getFromStore('products', productId);
        if (prod) {
            const currentStock = parseFloat(prod.branch_stock || 0);
            prod.branch_stock = Math.max(0, currentStock - parseFloat(qty));
            await putInStore('products', prod);
        }
    } catch (e) {
        console.warn('Could not decrement local stock for product ' + productId, e);
    }
}

/**
 * Finds a product in local storage by barcode or SKU
 */
async function findProductByBarcodeLocal(code) {
    if (!code) return null;
    const clean = String(code).trim();
    const products = await getAllFromStore('products');
    for (const p of products) {
        if (p.barcode && String(p.barcode).trim() === clean) return p;
        if (p.sku && String(p.sku).trim() === clean) return p;
    }
    // Also check offers by barcode
    const offers = await getAllFromStore('offers');
    for (const o of offers) {
        if (o.barcode && String(o.barcode).trim() === clean) {
            return { is_offer: true, ...o };
        }
    }
    return null;
}

/**
 * Settings helpers
 */
async function getSetting(key, defaultValue = null) {
    const item = await getFromStore('settings', key);
    return item ? item.value : defaultValue;
}

async function setSetting(key, value) {
    return putInStore('settings', { key, value });
}

// Generate unique UUID for offline invoices
function generateUUID() {
    return 'inv_' + Date.now().toString(36) + '_' + Math.random().toString(36).substr(2, 9);
}
