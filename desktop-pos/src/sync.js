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
     * Authenticates with cloud ERP to get/refresh sync token
     */
    async login(username, password) {
        const res = await fetch(`${this.serverUrl}/index.php?r=api_sync_login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ username, password })
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data || !data.success || !data.token) {
            throw new Error((data && data.message) || `HTTP ${res.status}`);
        }

        await this.setAuthToken(data.token);
        const cashierName = (data.user && data.user.name) ? data.user.name : username;
        await setSetting('saved_cashier_name', cashierName);
        if (data.user && data.user.location_id) {
            await setSetting('active_location_id', parseInt(data.user.location_id, 10));
        }
        return data;
    }

    /**
     * Master sync: Pushes pending customers & invoices then pulls fresh catalog
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
                const pendingCustomersCount = ((await getSetting('pending_customers', [])) || []).length;
                const offlineMsg = `أوفلاين (${pendingCount} فواتير بانتظار المزامنة)`;
                this.notifyStatus({
                    isOnline: false,
                    isSyncing: false,
                    pendingCount,
                    pendingCustomersCount,
                    message: offlineMsg
                });
                if (manual) {
                    alert(`⚠️ لا يمكن الوصول للسيرفر السحابي حالياً (${this.serverUrl}).\nتأكد من اتصال الإنترنت ورابط السيرفر في الإعدادات.`);
                }
                return;
            }

            // 1. Push pending offline customers first so invoices get linked properly
            await this.pushPendingCustomers();

            // 2. Push pending offline invoices
            const pushResult = await this.pushPendingInvoices();

            // 3. Pull latest catalog and stock for current branch
            const locationId = (await getSetting('active_location_id', 3)) || 3;
            await this.pullCatalog(locationId);

            const pendingCount = (await getAllFromStore('pending_invoices')).length;
            const pendingCustomersCount = ((await getSetting('pending_customers', [])) || []).length;
            const failedMsgs = (pushResult && Array.isArray(pushResult.failed_messages)) ? pushResult.failed_messages : [];
            const errorSummary = failedMsgs.length > 0 ? failedMsgs.join(' | ') : null;

            this.isSyncing = false;
            this.notifyStatus({
                isOnline: true,
                isSyncing: false,
                pendingCount,
                pendingCustomersCount,
                pushedCount: pushResult ? pushResult.synced_count : 0,
                errorMessage: errorSummary,
                message: errorSummary ? `متصل — تعذر رفع ${pendingCount} فواتير` : 'متصل بالسحابة — تم التحديث بنجاح'
            });

            if (manual) {
                if (failedMsgs.length > 0) {
                    alert('⚠️ لم يتم رفع بعض الفواتير المعلقة:\n\n' + failedMsgs.join('\n'));
                } else if (pushResult && pushResult.synced_count > 0 && pendingCount === 0) {
                    if (window.playBeep) window.playBeep('success');
                    alert(`✅ تمت مزامنة ورفع ${pushResult.synced_count} فاتورة بنجاح!`);
                } else if (window.playBeep) {
                    window.playBeep('success');
                }
            }

        } catch (err) {
            console.error('Sync error:', err);
            this.isSyncing = false;
            const pendingCount = (await getAllFromStore('pending_invoices')).length;
            const pendingCustomersCount = ((await getSetting('pending_customers', [])) || []).length;
            const authRequired = err && err.code === 'AUTH_REQUIRED';
            this.notifyStatus({
                isOnline: this.isOnline,
                isSyncing: false,
                pendingCount,
                pendingCustomersCount,
                authRequired,
                errorMessage: err.message,
                message: 'حدث خطأ أثناء المزامنة: ' + err.message
            });

            if (manual) {
                if (authRequired) {
                    alert('⚠️ ' + err.message + '\nسيتم فتح نافذة الإعدادات لتسجيل الدخول لرفع الفواتير المعلقة.');
                    if (typeof window.openSettingsModal === 'function') {
                        window.openSettingsModal();
                    }
                } else {
                    alert('❌ تعذر إتمام المزامنة:\n' + err.message);
                }
            }
        }
    }

    /**
     * Pushes standalone offline customers to cloud ERP before invoices
     */
    async pushPendingCustomers() {
        const pendingCustomers = (await getSetting('pending_customers', [])) || [];
        if (!pendingCustomers || pendingCustomers.length === 0) {
            return { synced_count: 0 };
        }

        const token = await this.getAuthToken();
        if (!token) {
            const authErr = new Error('يلزم تسجيل الدخول بحساب النظام من الإعدادات لإتمام المزامنة');
            authErr.code = 'AUTH_REQUIRED';
            throw authErr;
        }
        const locationId = (await getSetting('active_location_id', 3)) || 3;

        const payload = {
            token: token,
            location_id: locationId,
            customers: pendingCustomers
        };

        const res = await fetch(`${this.serverUrl}/index.php?r=api_sync_push`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json().catch(() => ({}));
        if (res.status === 401) {
            const authErr = new Error((data && data.message) || 'انتهت جلسة المزامنة — يرجى تسجيل الدخول من الإعدادات');
            authErr.code = 'AUTH_REQUIRED';
            throw authErr;
        }
        if (!res.ok) {
            throw new Error((data && data.message) || `Customer push failed with HTTP status ${res.status}`);
        }

        if (data && data.success && Array.isArray(data.customer_results)) {
            const syncedKeys = new Set();
            const idMap = new Map();

            for (const cr of data.customer_results) {
                if ((cr.status === 'synced' || cr.status === 'matched') && cr.server_id) {
                    if (cr.local_uuid) syncedKeys.add(String(cr.local_uuid));
                    if (cr.local_id !== undefined && cr.local_id !== null) {
                        idMap.set(String(cr.local_id), parseInt(cr.server_id, 10));
                        if (Number(cr.local_id) !== Number(cr.server_id)) {
                            await deleteFromStore('customers', cr.local_id).catch(() => {});
                        }
                    }
                }
            }

            // Update any pending invoices referencing these local customer IDs
            if (idMap.size > 0) {
                const pendingInvs = await getAllFromStore('pending_invoices');
                for (const inv of pendingInvs) {
                    const localCid = inv.customer_local_id !== undefined && inv.customer_local_id !== null
                        ? String(inv.customer_local_id)
                        : '';
                    if (localCid && idMap.has(localCid)) {
                        inv.customer_id = idMap.get(localCid);
                        inv.customer_is_local_new = false;
                        await putInStore('pending_invoices', inv);
                    }
                }
            }

            const remaining = pendingCustomers.filter(c => {
                const uuidMatch = c.local_uuid && syncedKeys.has(String(c.local_uuid));
                const idMatch = c.id !== undefined && idMap.has(String(c.id));
                return !uuidMatch && !idMatch;
            });
            await setSetting('pending_customers', remaining);
        }

        return data;
    }

    /**
     * Pushes pending invoices from local IndexedDB to cloud ERP
     */
    async pushPendingInvoices() {
        const pending = await getAllFromStore('pending_invoices');
        if (!pending || pending.length === 0) {
            return { synced_count: 0, errors_count: 0, failed_messages: [], results: [] };
        }

        const token = await this.getAuthToken();
        if (!token) {
            const authErr = new Error('يلزم تسجيل الدخول بحساب النظام من الإعدادات لإتمام المزامنة');
            authErr.code = 'AUTH_REQUIRED';
            throw authErr;
        }
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

        const data = await res.json().catch(() => ({}));
        if (res.status === 401) {
            const authErr = new Error((data && data.message) || 'انتهت جلسة المزامنة — يرجى تسجيل الدخول من الإعدادات');
            authErr.code = 'AUTH_REQUIRED';
            throw authErr;
        }
        if (!res.ok) {
            throw new Error((data && data.message) || `Push failed with HTTP status ${res.status}`);
        }

        const failedMessages = [];
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
                } else if (r.status === 'error') {
                    const inv = await getFromStore('pending_invoices', r.offline_uuid);
                    const invLabel = (inv && inv.offline_number) ? inv.offline_number : r.offline_uuid;
                    failedMessages.push(`${invLabel}: ${r.message || 'خطأ في الحفظ على السيرفر'}`);
                }
            }
        } else if (data && data.success === false) {
            throw new Error(data.message || 'فشل رفع الفواتير على السيرفر');
        }

        data.failed_messages = failedMessages;
        return data;
    }

    /**
     * Pulls latest products, balances, customers, offers, recipes from cloud ERP
     */
    async pullCatalog(locationId) {
        const token = await this.getAuthToken();
        if (!token) {
            const authErr = new Error('يلزم تسجيل الدخول بحساب النظام من الإعدادات لإتمام المزامنة');
            authErr.code = 'AUTH_REQUIRED';
            throw authErr;
        }
        const url = `${this.serverUrl}/index.php?r=api_sync_pull&location_id=${locationId}&token=${encodeURIComponent(token)}`;

        const res = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await res.json().catch(() => ({}));
        if (res.status === 401) {
            const authErr = new Error((data && data.message) || 'انتهت جلسة المزامنة — يرجى تسجيل الدخول من الإعدادات');
            authErr.code = 'AUTH_REQUIRED';
            throw authErr;
        }
        if (!res.ok) {
            throw new Error((data && data.message) || `Pull failed with HTTP status ${res.status}`);
        }

        if (data && data.success) {
            await saveCatalogData(data);
            if (window.onCatalogRefreshed) {
                window.onCatalogRefreshed(data);
            }
            return data;
        } else {
            throw new Error((data && data.message) || 'Unknown pull error');
        }
    }
}

// Global instance
window.syncEngine = new SyncEngine();
