/**
 * Hamza for Perfumes — Offline POS Background Sync Engine
 * Handles ping, pushing offline invoices, and pulling latest product catalogs & stock.
 */

class SyncEngine {
    constructor() {
        this.isOnline = false;
        this.isSyncing = false;
        this.listeners = [];
        this.timer = null;
        this.serverUrl = 'https://erp1.hamzaforperfumes.com'; // Default production VPS ERP
    }

    async init() {
        // Load server URL from local settings
        const savedUrl = await getSetting('server_url');
        if (savedUrl) {
            this.serverUrl = savedUrl.replace(/\/+$/, '');
        }

        // Listen to browser network state
        window.addEventListener('online', () => this.checkConnectivity());
        window.addEventListener('offline', () => this.setOnlineState(false));

        // Initial connection check
        await this.checkConnectivity();

        // Start periodic sync interval (every 30 seconds)
        this.startPeriodicSync();
    }

    setServerUrl(url) {
        this.serverUrl = (url || '').trim().replace(/\/+$/, '');
        setSetting('server_url', this.serverUrl);
        this.checkConnectivity();
    }

    async getServerUrl() {
        return this.serverUrl;
    }

    async getAuthToken() {
        return await getSetting('sync_token', '');
    }

    async setAuthToken(token) {
        return await setSetting('sync_token', token);
    }

    startPeriodicSync() {
        if (this.timer) clearInterval(this.timer);
        this.timer = setInterval(() => {
            if (!this.isSyncing) {
                this.syncAll();
            }
        }, 30000);
    }

    onStatusChange(fn) {
        this.listeners.push(fn);
    }

    notifyStatus(info) {
        for (const fn of this.listeners) {
            try { fn(info); } catch (e) { console.error(e); }
        }
    }

    setOnlineState(online) {
        const changed = this.isOnline !== online;
        this.isOnline = online;
        this.notifyStatus({ isOnline: this.isOnline, isSyncing: this.isSyncing });
        if (changed && online) {
            this.syncAll();
        }
    }

    /**
     * Pings server to test real HTTP connectivity
     */
    async checkConnectivity() {
        if (!navigator.onLine) {
            this.setOnlineState(false);
            return false;
        }

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 4000);
            const res = await fetch(`${this.serverUrl}/index.php?r=api_sync_ping`, {
                method: 'GET',
                signal: controller.signal,
                headers: { 'Accept': 'application/json' }
            });
            clearTimeout(timeoutId);

            if (res.ok) {
                const data = await res.json();
                if (data && data.success) {
                    this.setOnlineState(true);
                    return true;
                }
            }
        } catch (err) {
            // Server unreachable
        }

        this.setOnlineState(false);
        return false;
    }

    /**
     * Master sync: Pushes pending invoices then pulls fresh catalog
     */
    async syncAll(manual = false) {
        if (this.isSyncing) return;
        this.isSyncing = true;
        this.notifyStatus({ isOnline: this.isOnline, isSyncing: true, message: 'جاري التحقق من الاتصال والمزامنة...' });

        try {
            const isConnected = await this.checkConnectivity();
            if (!isConnected) {
                this.isSyncing = false;
                const pendingCount = (await getAllFromStore('pending_invoices')).length;
                this.notifyStatus({ 
                    isOnline: false, 
                    isSyncing: false, 
                    pendingCount,
                    message: `أوفلاين (${pendingCount} فواتير بانتظار المزامنة)` 
                });
                return;
            }

            // 1. Push pending offline invoices
            const pushResult = await this.pushPendingInvoices();

            // 2. Pull latest catalog and stock for current branch
            const locationId = (await getSetting('active_location_id', 3)) || 3;
            const pullResult = await this.pullCatalog(locationId);

            const pendingCount = (await getAllFromStore('pending_invoices')).length;
            this.isSyncing = false;
            this.notifyStatus({ 
                isOnline: true, 
                isSyncing: false, 
                pendingCount,
                pushedCount: pushResult ? pushResult.synced_count : 0,
                message: 'متصل بالسحابة — تم التحديث بنجاح' 
            });

            if (manual && window.playBeep) {
                window.playBeep('success');
            }

        } catch (err) {
            console.error('Sync error:', err);
            this.isSyncing = false;
            const pendingCount = (await getAllFromStore('pending_invoices')).length;
            this.notifyStatus({ 
                isOnline: this.isOnline, 
                isSyncing: false, 
                pendingCount,
                message: 'حدث خطأ أثناء المزامنة: ' + err.message 
            });
        }
    }

    /**
     * Pushes pending invoices from local IndexedDB to cloud ERP
     */
    async pushPendingInvoices() {
        const pending = await getAllFromStore('pending_invoices');
        if (!pending || pending.length === 0) {
            return { synced_count: 0 };
        }

        const token = await this.getAuthToken();
        const locationId = (await getSetting('active_location_id', 3)) || 3;

        const payload = {
            token: token,
            location_id: locationId,
            invoices: pending
        };

        const res = await fetch(`${this.serverUrl}/index.php?r=api_sync_push`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        if (!res.ok) {
            throw new Error(`Push failed with HTTP status ${res.status}`);
        }

        const data = await res.json();
        if (data && data.success && Array.isArray(data.results)) {
            for (const r of data.results) {
                if (r.status === 'synced' || r.status === 'already_synced') {
                    // Find invoice in pending store
                    const inv = await getFromStore('pending_invoices', r.offline_uuid);
                    if (inv) {
                        inv.server_invoice_id = r.invoice_id;
                        inv.server_invoice_number = r.invoice_number;
                        inv.synced_at = new Date().toISOString();
                        // Save to synced store
                        await putInStore('synced_invoices', inv);
                        // Remove from pending
                        await deleteFromStore('pending_invoices', r.offline_uuid);
                    }
                }
            }
        }

        return data;
    }

    /**
     * Pulls latest products, balances, customers, offers, recipes from cloud ERP
     */
    async pullCatalog(locationId) {
        const token = await this.getAuthToken();
        const url = `${this.serverUrl}/index.php?r=api_sync_pull&location_id=${locationId}${token ? '&token=' + encodeURIComponent(token) : ''}`;

        const res = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        if (!res.ok) {
            throw new Error(`Pull failed with HTTP status ${res.status}`);
        }

        const data = await res.json();
        if (data && data.success) {
            await saveCatalogData(data);
            if (window.onCatalogRefreshed) {
                window.onCatalogRefreshed(data);
            }
            return data;
        } else {
            throw new Error(data.message || 'Unknown pull error');
        }
    }
}

// Global instance
window.syncEngine = new SyncEngine();
