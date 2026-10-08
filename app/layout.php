<?php

declare(strict_types=1);

require_once __DIR__ . '/navigation.php';

function lang_switch_url(string $lang): string
{
    $params = $_GET;
    $params['lang'] = $lang;
    return 'index.php?' . http_build_query($params);
}

function render_login(): void
{
    $flash = flash();
    $lang = current_lang();
    $dir = $lang === 'en' ? 'ltr' : 'rtl';
    ?>
    <!doctype html>
    <html lang="<?= $lang ?>" dir="<?= $dir ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e(__('تسجيل الدخول')) ?></title>
        <link rel="icon" type="image/png" href="assets/logo.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
        <script>
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        </script>
    </head>
    <body class="login-page">
    <main class="login-card">
        <div class="brand-mark"><img src="<?= e(APP_BASE) ?>/assets/logo.png" alt="logo" /></div>
        <h1><?= e(APP_NAME) ?></h1>
        <p><?= e(__('نظام إدارة براند العطور')) ?></p>
        
        <div style="margin-bottom: 15px; display: flex; gap: 8px; justify-content: center; font-size: 12px; align-items: center;">
            <a href="?lang=ar" style="font-weight: <?= $lang === 'ar' ? 'bold' : 'normal' ?>; text-decoration: none;">العربية</a>
            <span>|</span>
            <a href="?lang=en" style="font-weight: <?= $lang === 'en' ? 'bold' : 'normal' ?>; text-decoration: none;">English</a>
            <span>|</span>
            <button onclick="toggleTheme()" style="background: none; border: none; cursor: pointer; padding: 0; font-size: 14px;" title="Theme">🌓</button>
        </div>

        <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e(__($flash['message'])) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label><?= e(__('اسم المستخدم')) ?><input name="username" required autofocus></label>
            <label><?= e(__('كلمة المرور')) ?><input name="password" type="password" required></label>
            <button class="btn primary"><?= e(__('دخول')) ?></button>
        </form>
    </main>
    <script>
        function toggleTheme() {
            const root = document.documentElement;
            const current = root.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        }
    </script>
    </body>
    </html>
    <?php
}

function render_layout(string $route, array $user): void
{
    $flash = flash();
    $activeSection = section_for_route($route);
    $routeLabel = label_for_route($route);
    $sectionLabel = nav_sections()[$activeSection]['label'] ?? 'الرئيسية';
    $lang = current_lang();
    $dir = $lang === 'en' ? 'ltr' : 'rtl';
    ?>
    <!doctype html>
    <html lang="<?= $lang ?>" dir="<?= $dir ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e(APP_NAME) ?></title>
        <link rel="icon" type="image/png" href="assets/logo.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Noto+Naskh+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
        <script>
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        </script>
        <script src="assets/app.js?v=<?= filemtime(__DIR__ . '/../assets/app.js') ?>" defer></script>
        <!-- Service Worker for Offline POS -->
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('/test/sw.js', { scope: '/test/' })
                        .then(function(reg) {

                            // Function to send POS page HTML snapshot to SW for caching
                            function cachePOSSnapshot() {
                                var sw = navigator.serviceWorker.controller;
                                if (!sw) return;
                                // Only cache on the POS page itself
                                if (!window.location.href.includes('r=pos')) return;
                                // Send the full rendered HTML to the SW
                                var html = '<!DOCTYPE html>' + document.documentElement.outerHTML;
                                sw.postMessage({ type: 'CACHE_POS_HTML', html: html });
                            }

                            // Wait for SW to become active/controlling
                            if (navigator.serviceWorker.controller) {
                                // SW already controlling — cache immediately
                                cachePOSSnapshot();
                            } else {
                                // SW just installed — wait for it to take control
                                navigator.serviceWorker.addEventListener('controllerchange', function() {
                                    cachePOSSnapshot();
                                });
                                // Also trigger a new fetch so SW caches the page via network
                                if (window.location.href.includes('r=pos')) {
                                    reg.active && reg.active.postMessage({
                                        type: 'CACHE_POS_PAGE',
                                        url: window.location.pathname + '?r=pos'
                                    });
                                }
                            }

                            // Re-cache every 5 minutes while on POS page (keeps cache fresh)
                            if (window.location.href.includes('r=pos')) {
                                setInterval(cachePOSSnapshot, 5 * 60 * 1000);
                            }
                        })
                        .catch(function(err) { console.warn('SW registration failed:', err); });

                    // Listen for sync trigger from SW
                    navigator.serviceWorker.addEventListener('message', function(event) {
                        if (event.data && event.data.type === 'SW_SYNC_TRIGGER') {
                            if (typeof syncPendingInvoices === 'function') syncPendingInvoices();
                        }
                    });
                });
            }
        </script>

        <!-- Flatpickr Datepicker CSS/JS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
        <style>
            .flatpickr-input[readonly], .flatpickr-input {
                background-color: var(--field-bg) !important;
                color: var(--ink) !important;
                border: 1px solid var(--line) !important;
            }
            .flatpickr-calendar {
                font-family: 'Cairo', sans-serif !important;
            }
        </style>
    </head>
    <body data-active-section="<?= e($activeSection) ?>">
    <!-- Offline status banner (only visible when offline) -->
    <div id="offline-banner" style="display:none; position:fixed; top:0; left:0; right:0; z-index:9999; background:linear-gradient(135deg,#7c1d1d,#991b1b); color:#fff; text-align:center; padding:10px 16px; font-family:'Cairo',sans-serif; font-weight:700; font-size:13px; box-shadow:0 2px 12px rgba(0,0,0,0.4); direction:rtl;">
        📡 أنت الآن في وضع الأوفلاين — ستُحفظ الفواتير محلياً وتُرسَل تلقائياً عند عودة الإنترنت
        <span id="offline-pending-count" style="background:rgba(255,255,255,0.2);border-radius:12px;padding:1px 8px;margin-right:8px;"></span>
    </div>
    <!-- Online restored banner -->
    <div id="online-banner" style="display:none; position:fixed; top:0; left:0; right:0; z-index:9999; background:linear-gradient(135deg,#065f46,#059669); color:#fff; text-align:center; padding:10px 16px; font-family:'Cairo',sans-serif; font-weight:700; font-size:13px; box-shadow:0 2px 12px rgba(0,0,0,0.4); direction:rtl;">
        ✅ عاد الإنترنت — جاري مزامنة الفواتير المعلقة...
    </div>
    <script>
        (function() {
            var offlineBanner = null, onlineBanner = null;
            var onlineTimer = null;
            function initBanners() {
                offlineBanner = document.getElementById('offline-banner');
                onlineBanner  = document.getElementById('online-banner');
                if (!navigator.onLine) showOffline();
            }
            function showOffline() {
                if (offlineBanner) offlineBanner.style.display = 'block';
                if (onlineBanner)  onlineBanner.style.display  = 'none';
                document.body.style.paddingTop = '44px';
            }
            function showOnline() {
                if (offlineBanner) offlineBanner.style.display = 'none';
                if (onlineBanner)  { onlineBanner.style.display = 'block'; clearTimeout(onlineTimer); onlineTimer = setTimeout(() => { onlineBanner.style.display = 'none'; document.body.style.paddingTop = ''; }, 4000); }
                else { document.body.style.paddingTop = ''; }
            }
            window.addEventListener('offline', function() { showOffline(); });
            window.addEventListener('online',  function() { showOnline(); if (typeof syncPendingInvoices === 'function') syncPendingInvoices(); });
            window.addEventListener('DOMContentLoaded', initBanners);
            window.updateOfflinePendingCount = function(count) {
                var el = document.getElementById('offline-pending-count');
                if (el) el.textContent = count > 0 ? count + ' فاتورة معلقة' : '';
            };
        })();
    </script>
    <button class="mobile-menu-button" type="button" data-menu-toggle aria-label="<?= e(__('القائمة')) ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
        <span><?= e(__('القائمة')) ?></span>
    </button>
    <div class="page-backdrop" data-menu-backdrop></div>
    <aside class="sidebar">
        <button class="sidebar-close-button" type="button" data-menu-close aria-label="Close">×</button>
        <div class="side-brand">
            <div class="brand-mark"><img src="<?= e(APP_BASE) ?>/assets/logo.png" alt="logo" /></div>
            <div>
                <h1><?= e(APP_NAME) ?></h1>
                <small><?= e(__('نظام إدارة براند العطور')) ?></small>
            </div>
        </div>

        <div class="side-search">
            <input type="search" placeholder="<?= e(__('بحث سريع داخل القائمة')) ?>" data-nav-search>
        </div>

        <nav class="nav-accordion" aria-label="Main Navigation">
            <?php foreach (nav_sections() as $key => $section): 
                $visibleItems = array_filter($section['items'], fn($item) => empty($item['hidden']) && has_permission($item['route']));
                if (empty($visibleItems)) { continue; }
            ?>
                <section class="nav-section <?= $key === $activeSection ? 'open' : '' ?>" data-section="<?= e($key) ?>">
                    <button type="button" class="nav-section-toggle" aria-expanded="<?= $key === $activeSection ? 'true' : 'false' ?>">
                        <span><?= e(__($section['label'])) ?></span>
                        <b>⌄</b>
                    </button>
                    <div class="nav-section-panel">
                        <?php foreach ($visibleItems as $item): ?>
                            <?= nav_link($item['route'], $item['label'], $route, $item['url'] ?? null) ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </nav>

        <div class="user-box">
            <strong><?= e($user['name']) ?></strong>
            <span><?= e(__($user['role_name'])) ?><?= !empty($user['location_name']) ? ' - ' . e(__($user['location_name'])) : '' ?></span>
            <a href="index.php?r=logout"><?= e(__('خروج')) ?></a>
        </div>
    </aside>

    <main class="content">
        <header class="topbar">
            <div>
                <div class="breadcrumbs">
                    <span><?= e(__($sectionLabel)) ?></span>
                    <b>/</b>
                    <strong><?= e(__($routeLabel)) ?></strong>
                </div>
                <h2><?= e(__($routeLabel)) ?></h2>
            </div>
            <div class="topbar-actions" style="display: flex; gap: 8px; align-items: center;">
                <a class="quick-action" href="index.php?r=pos"><?= e(__('بيع جديد')) ?></a>
                <a class="quick-action" href="index.php?r=notifications"><?= e(__('التنبيهات')) ?></a>
                <a class="quick-action primary" href="index.php?r=reports"><?= e(__('التقارير')) ?></a>
                
                <!-- Theme Toggle Button -->
                <button class="quick-action" onclick="toggleTheme()" type="button" title="🌓 Toggle Theme" style="border: 0; cursor: pointer; font-size: 14px; padding: 6px 10px; display: inline-flex; align-items: center; justify-content: center; height: 32px; border-radius: 8px; background: rgba(0,0,0,0.05);">🌓</button>
                
                <!-- Language Switcher Button -->
                <?php if ($lang === 'en'): ?>
                    <a class="quick-action" href="<?= e(lang_switch_url('ar')) ?>" style="font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 32px; padding: 6px 10px; border-radius: 8px;">العربية</a>
                <?php else: ?>
                    <a class="quick-action" href="<?= e(lang_switch_url('en')) ?>" style="font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; height: 32px; padding: 6px 10px; border-radius: 8px;">English</a>
                <?php endif; ?>
            </div>
        </header>
        <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e(__($flash['message'])) ?></div><?php endif; ?>
        <section class="page-shell">
            <?php render_page($route, $user); ?>
        </section>
    </main>
    <script>
        function toggleTheme() {
            const root = document.documentElement;
            const current = root.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        }

        // Initialize Flatpickr on all date inputs for a consistent dd/mm/yyyy format
        document.addEventListener('DOMContentLoaded', () => {
            flatpickr('input[type="date"]', {
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "d/m/Y",
                locale: "<?= current_lang() === 'ar' ? 'ar' : 'en' ?>",
                allowInput: true
            });
        });

        // ===== نظام التنبيه الفوري بالتحديثات الجديدة على كل الأجهزة المفتوحة =====
        let currentBroadcastUpdateId = 0;

        function playUpdateChime() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                const now = ctx.currentTime;
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(523.25, now);
                osc.frequency.setValueAtTime(659.25, now + 0.12);
                osc.frequency.setValueAtTime(783.99, now + 0.24);
                gain.gain.setValueAtTime(0.25, now);
                gain.gain.exponentialRampToValueAtTime(0.01, now + 0.6);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now);
                osc.stop(now + 0.6);
            } catch(e) {}
        }

        function checkSystemUpdates() {
            fetch('index.php?r=api_latest_update')
                .then(res => res.json())
                .then(data => {
                    if (!data || !data.has_update || !data.update) return;
                    const update = data.update;
                    const lastSeenId = parseInt(localStorage.getItem('erp_last_seen_update_id') || '0', 10);
                    if (update.id > lastSeenId) {
                        currentBroadcastUpdateId = update.id;
                        showUpdateModal(update);
                    }
                })
                .catch(() => {});
        }

        function showUpdateModal(update) {
            const modal = document.getElementById('system-update-broadcast-modal');
            if (!modal) return;
            document.getElementById('sub-update-title').textContent = update.title + (update.version ? (' (' + update.version + ')') : '');
            document.getElementById('sub-update-content').textContent = update.content;
            const urgencyBadge = document.getElementById('sub-urgency-badge');
            if (update.urgency === 'critical') {
                urgencyBadge.textContent = '🚨 تحديث عاجل وجوهري';
                urgencyBadge.style.background = '#ef4444';
                urgencyBadge.style.color = '#ffffff';
            } else if (update.urgency === 'important') {
                urgencyBadge.textContent = '⭐ تحديث مهم';
                urgencyBadge.style.background = '#c5a059';
                urgencyBadge.style.color = '#0f172a';
            } else {
                urgencyBadge.textContent = '✨ تحسينات جديدة';
                urgencyBadge.style.background = '#10b981';
                urgencyBadge.style.color = '#ffffff';
            }
            modal.style.display = 'flex';
            playUpdateChime();
        }

        function reloadForUpdate() {
            if (currentBroadcastUpdateId > 0) {
                localStorage.setItem('erp_last_seen_update_id', currentBroadcastUpdateId);
            }
            window.location.reload();
        }

        function dismissUpdateBroadcast() {
            if (currentBroadcastUpdateId > 0) {
                localStorage.setItem('erp_last_seen_update_id', currentBroadcastUpdateId);
            }
            const modal = document.getElementById('system-update-broadcast-modal');
            if (modal) modal.style.display = 'none';
        }

        // فحص التحديثات عند فتح الصفحة وبشكل دوري كل 20 ثانية وعند رجوع المستخدم للتبويب
        setTimeout(checkSystemUpdates, 2000);
        setInterval(checkSystemUpdates, 20000);
        window.addEventListener('focus', checkSystemUpdates);
    </script>

    <!-- Modal نافذة التنبيه الفوري بالتحديث الجديد لجميع الأجهزة -->
    <div id="system-update-broadcast-modal" style="display:none; position:fixed; inset:0; z-index:9999999; background:rgba(0,0,0,0.85); backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); align-items:center; justify-content:center; padding:16px;">
        <div style="background:#0f172a; border:2px solid #c5a059; border-radius:20px; max-width:520px; width:100%; box-shadow:0 25px 50px rgba(0,0,0,0.8), 0 0 35px rgba(197, 160, 89, 0.3); overflow:hidden; text-align:right; color:#f8fafc; font-family:'Cairo', sans-serif;">
            <div style="padding:16px 20px; background:linear-gradient(135deg, rgba(197, 160, 89, 0.25), rgba(15, 23, 42, 0.95)); border-bottom:1.5px solid rgba(197, 160, 89, 0.3); display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:28px;">📢</span>
                    <div>
                        <h3 style="margin:0; font-size:16.5px; font-weight:800; color:#ffffff;">تنبيه: تم إصدار تحديث جديد في النظام!</h3>
                        <p style="margin:2px 0 0; font-size:12px; color:#cbd5e1;">يرجى مراجعة الجديد وتحديث الصفحة</p>
                    </div>
                </div>
                <span id="sub-urgency-badge" style="background:#c5a059; color:#0f172a; padding:3px 10px; border-radius:12px; font-size:12px; font-weight:800;">تحديث مهم</span>
            </div>
            <div style="padding:20px;">
                <div style="margin-bottom:12px;">
                    <span style="font-size:12.5px; color:#94a3b8;">عنوان التحديث:</span>
                    <h4 id="sub-update-title" style="margin:4px 0 0; font-size:16px; font-weight:800; color:#38bdf8;"></h4>
                </div>
                <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.1); border-radius:12px; padding:14px; margin-bottom:16px; max-height:220px; overflow-y:auto; line-height:1.7; font-size:14px; white-space:pre-line;" id="sub-update-content">
                </div>
                <div style="background:rgba(197, 160, 89, 0.1); border:1px solid rgba(197, 160, 89, 0.3); border-radius:10px; padding:10px 14px; font-size:12.5px; color:#e2e8f0; margin-bottom:18px;">
                    💡 <strong>تنبيه:</strong> لتطبيق التعديلات على هذا الجهاز فوراً، يُرجى الضغط على زر التحديث أدناه.
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="dismissUpdateBroadcast()" style="background:transparent; border:1px solid rgba(255,255,255,0.2); color:#cbd5e1; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer;">
                        إغلاق (لاحقاً)
                    </button>
                    <button type="button" onclick="reloadForUpdate()" style="background:linear-gradient(135deg, #c5a059, #a07a34); color:#fff; border:none; padding:10px 22px; border-radius:10px; font-size:14px; font-weight:800; cursor:pointer; box-shadow:0 4px 15px rgba(197, 160, 89, 0.4); display:flex; align-items:center; gap:6px;">
                        <span>🔄 تحديث الصفحة الآن</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
}

function nav_link(string $target, string $label, string $route, ?string $customUrl = null): string
{
    $href   = $customUrl ?? ('index.php?r=' . e($target));
    // الرابط يكون active إذا كان الـ route متطابقاً (بغض النظر عن view param)
    $active = ($target === $route) ? 'active' : '';
    return '<a class="' . $active . '" href="' . e($href) . '" data-nav-item>' . e(__($label)) . '</a>';
}

function label_for_route(string $route): string
{
    foreach (nav_sections() as $section) {
        foreach ($section['items'] as $item) {
            if ($item['route'] === $route) {
                return __($item['label']);
            }
        }
    }
    return __('لوحة التحكم');
}

function simple_table(array $rows): string
{
    if (!$rows) {
        return '<p class="muted">' . e(__('لا توجد بيانات بعد.')) . '</p>';
    }

    $html = '<table><thead><tr>';
    foreach (array_keys($rows[0]) as $key) {
        $html .= '<th>' . e(__($key)) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $value) {
            $html .= '<td>' . e($value) . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table>';
}

function table_invoices(array $invoices): void
{
    ?>
    <div class="panel">
        <table>
            <thead>
            <tr>
                <th><?= e(__('رقم الفاتورة')) ?></th>
                <th><?= e(__('الحالة')) ?></th>
                <th><?= e(__('الموقع')) ?></th>
                <th><?= e(__('الموظف')) ?></th>
                <th><?= e(__('العميل')) ?></th>
                <th><?= e(__('الإجمالي')) ?></th>
                <th><?= e(__('المدفوع')) ?></th>
                <th><?= e(__('المتبقي')) ?></th>
                <th><?= e(__('ملاحظات')) ?></th>
                <th><?= e(__('التاريخ')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($invoices as $i): ?>
                <tr>
                    <td><a href="index.php?r=invoice_view&id=<?= e($i['id']) ?>"><?= e($i['invoice_number']) ?></a></td>
                    <td><span class="badge"><?= e(__($i['status'])) ?></span></td>
                    <td><?= e($i['location_name']) ?></td>
                    <td><?= e($i['user_name']) ?></td>
                    <td><?= e($i['customer_name'] ?: __('زبون عابر')) ?></td>
                    <td><?= money($i['total']) ?></td>
                    <td><?= money($i['paid_total']) ?></td>
                    <td><?= money($i['due_total']) ?></td>
                    <td><?= e($i['notes']) ?></td>
                    <td><?= e($i['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
