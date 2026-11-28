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
        <link rel="manifest" href="manifest.json">
        <meta name="theme-color" content="#c9a84c">
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
                    navigator.serviceWorker.register('sw.js')
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
                <button type="button" class="btn-global-back" onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = 'index.php?r=dashboard'; }" title="<?= e(__('رجوع للصفحة السابقة')) ?>" aria-label="<?= e(__('رجوع')) ?>">
                    <span class="back-icon" aria-hidden="true">↩️</span>
                    <span class="back-text"><?= e(__('رجوع')) ?></span>
                </button>
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
    <!-- Global 30-Minute Call Center Callback Alarm System -->
    <style>
        @keyframes ccAlarmPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.55), 0 24px 60px rgba(0, 0, 0, 0.85); }
            50% { box-shadow: 0 0 0 12px rgba(239, 68, 68, 0), 0 24px 60px rgba(0, 0, 0, 0.85); }
        }
        #cc-alarms-floating-wrap {
            position: fixed;
            left: 18px;
            bottom: 18px;
            z-index: 999990;
            display: none;
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
            font-family: 'Cairo', sans-serif;
            direction: rtl;
        }
        #cc-alarms-floating-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #b45309, #92400e);
            color: #fff;
            border: 1.5px solid #fbbf24;
            border-radius: 999px;
            padding: 9px 15px;
            font-size: 12.5px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
            font-family: inherit;
        }
        #cc-alarms-floating-drawer {
            display: none;
            width: 330px;
            max-width: calc(100vw - 32px);
            max-height: 360px;
            overflow-y: auto;
            background: #0f172a;
            color: #f8fafc;
            border: 1.5px solid #c5a059;
            border-radius: 14px;
            padding: 12px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.55);
        }
        #cc-alarm-toast {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999999;
            display: none;
            background: #0f172a;
            color: #fef3c7;
            border: 1.5px solid #f59e0b;
            border-radius: 12px;
            padding: 11px 18px;
            font-size: 13.5px;
            font-weight: 700;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.45);
            direction: rtl;
            max-width: 92vw;
            text-align: center;
        }
    </style>

    <div id="cc-alarms-floating-wrap">
        <div id="cc-alarms-floating-drawer"></div>
        <button type="button" id="cc-alarms-floating-pill" onclick="ERPCallAlarms.toggleDrawer()">
            <span>⏰</span>
            <span id="cc-alarms-pill-text">منبهات إعادة الاتصال</span>
        </button>
    </div>

    <div id="cc-alarm-toast"></div>

    <div id="cc-callback-alarm-modal" style="display:none; position:fixed; inset:0; z-index:9999998; background:rgba(0,0,0,0.82); backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); align-items:center; justify-content:center; padding:16px;">
        <div style="background:#0f172a; border:2px solid #ef4444; border-radius:20px; max-width:500px; width:100%; overflow:hidden; text-align:right; color:#f8fafc; font-family:'Cairo', sans-serif; animation: ccAlarmPulse 1.5s infinite;">
            <div style="padding:16px 20px; background:linear-gradient(135deg, rgba(239,68,68,0.28), rgba(15,23,42,0.98)); border-bottom:1.5px solid rgba(239,68,68,0.45); display:flex; justify-content:space-between; align-items:center; gap:10px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size:28px;">🔔</span>
                    <div>
                        <h3 style="margin:0; font-size:17px; font-weight:800; color:#fecaca;">منبه الكول سنتر — حان موعد إعادة الاتصال!</h3>
                        <p style="margin:2px 0 0; font-size:12px; color:#cbd5e1;">مر نصف ساعة على محاولة الاتصال السابقة دون رد</p>
                    </div>
                </div>
            </div>
            <div style="padding:20px;">
                <div style="background:rgba(245,158,11,0.12); border:1.5px solid rgba(245,158,11,0.45); border-radius:14px; padding:16px; margin-bottom:16px; text-align:center;">
                    <div style="font-size:12.5px; color:#fcd34d; font-weight:700; margin-bottom:4px;">اتصل الآن على العميل:</div>
                    <div id="cc-alarm-cust-name" style="font-size:22px; font-weight:900; color:#ffffff; margin-bottom:6px;">-</div>
                    <div id="cc-alarm-cust-phone" dir="ltr" style="font-size:18px; font-weight:800; color:#60a5fa; letter-spacing:1px; margin-bottom:8px;">-</div>
                    <div id="cc-alarm-reason" style="font-size:13px; color:#fde68a; background:rgba(0,0,0,0.28); display:inline-block; padding:4px 12px; border-radius:999px;"></div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:10px;">
                    <button type="button" id="cc-alarm-call-btn" style="background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border:none; border-radius:10px; padding:11px 14px; font-size:14px; font-weight:800; cursor:pointer; font-family:inherit;">
                        📞 اتصال الآن
                    </button>
                    <a id="cc-alarm-wa-btn" href="#" target="_blank" rel="noopener" style="background:#25D366; color:#fff; border:none; border-radius:10px; padding:11px 14px; font-size:14px; font-weight:800; text-decoration:none; text-align:center; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                        💬 شات واتساب
                    </a>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap; justify-content:space-between; margin-top:8px;">
                    <button type="button" id="cc-alarm-snooze15-btn" style="flex:1; background:rgba(245,158,11,0.18); color:#fcd34d; border:1px solid rgba(245,158,11,0.5); border-radius:10px; padding:9px 10px; font-size:12.5px; font-weight:700; cursor:pointer; font-family:inherit;">
                        ⏰ تأجيل 15 د
                    </button>
                    <button type="button" id="cc-alarm-snooze30-btn" style="flex:1; background:rgba(245,158,11,0.18); color:#fcd34d; border:1px solid rgba(245,158,11,0.5); border-radius:10px; padding:9px 10px; font-size:12.5px; font-weight:700; cursor:pointer; font-family:inherit;">
                        🔁 تأجيل 30 د
                    </button>
                    <button type="button" id="cc-alarm-dismiss-btn" style="flex:1; background:rgba(16,185,129,0.2); color:#6ee7b7; border:1px solid rgba(16,185,129,0.55); border-radius:10px; padding:9px 10px; font-size:12.5px; font-weight:800; cursor:pointer; font-family:inherit;">
                        ✅ تم / إغلاق
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const STORAGE_KEY = 'erp_call_callback_alarms_v1';
            const REASON_LABELS = {
                no_answer: 'لم يرد / لا إجابة',
                busy: 'الخط مشغول أو مغلق',
                wrong_number: 'رقم غير متاح / خارج الخدمة'
            };

            let sharedAudioCtx = null;
            let ringtoneInterval = null;
            let ringtoneTimeout = null;
            let titleBlinkInterval = null;
            let baseDocumentTitle = document.title;
            let activeAlarmId = null;
            let notifiedIds = {};
            let drawerOpen = false;
            let toastTimer = null;

            function cleanDial(phone) {
                return String(phone || '').replace(/[^\d+]/g, '');
            }

            function cleanWhatsApp(phone) {
                let digits = String(phone || '').replace(/\D/g, '');
                if (digits.startsWith('0') && digits.length >= 10) {
                    digits = '2' + digits;
                }
                return digits;
            }

            function unlockAudio() {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) return null;
                    if (!sharedAudioCtx) {
                        sharedAudioCtx = new AudioCtx();
                    }
                    if (sharedAudioCtx.state === 'suspended') {
                        sharedAudioCtx.resume().catch(() => {});
                    }
                    return sharedAudioCtx;
                } catch (e) {
                    return null;
                }
            }

            function playAlarmPattern() {
                try {
                    const ctx = unlockAudio();
                    if (!ctx) return;
                    const now = ctx.currentTime;
                    const notes = [
                        { freq: 880, start: 0.0, dur: 0.16 },
                        { freq: 1174.66, start: 0.19, dur: 0.16 },
                        { freq: 880, start: 0.42, dur: 0.16 },
                        { freq: 1174.66, start: 0.61, dur: 0.24 }
                    ];
                    notes.forEach(n => {
                        const osc = ctx.createOscillator();
                        const gain = ctx.createGain();
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(n.freq, now + n.start);
                        gain.gain.setValueAtTime(0.001, now + n.start);
                        gain.gain.exponentialRampToValueAtTime(0.32, now + n.start + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + n.start + n.dur);
                        osc.connect(gain);
                        gain.connect(ctx.destination);
                        osc.start(now + n.start);
                        osc.stop(now + n.start + n.dur + 0.02);
                    });
                } catch (e) {}
            }

            function stopAlarmRingtone() {
                if (ringtoneInterval) {
                    clearInterval(ringtoneInterval);
                    ringtoneInterval = null;
                }
                if (ringtoneTimeout) {
                    clearTimeout(ringtoneTimeout);
                    ringtoneTimeout = null;
                }
                if (titleBlinkInterval) {
                    clearInterval(titleBlinkInterval);
                    titleBlinkInterval = null;
                    document.title = baseDocumentTitle;
                }
            }

            function startAlarmRingtone(customerName) {
                stopAlarmRingtone();
                baseDocumentTitle = document.title.replace(/^🔔\s.*?\|\s/, '');
                playAlarmPattern();
                ringtoneInterval = setInterval(playAlarmPattern, 3500);
                ringtoneTimeout = setTimeout(() => {
                    if (ringtoneInterval) {
                        clearInterval(ringtoneInterval);
                        ringtoneInterval = null;
                    }
                }, 60000);

                let flag = false;
                titleBlinkInterval = setInterval(() => {
                    flag = !flag;
                    document.title = flag ? ('🔔 اتصل على ' + customerName + '!') : baseDocumentTitle;
                }, 900);
            }

            function loadAlarms() {
                try {
                    const raw = localStorage.getItem(STORAGE_KEY);
                    if (!raw) return [];
                    const list = JSON.parse(raw);
                    return Array.isArray(list) ? list : [];
                } catch (e) {
                    return [];
                }
            }

            function saveAlarms(list) {
                try {
                    localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
                } catch (e) {}
                renderFloatingWidget();
                window.dispatchEvent(new CustomEvent('erp-call-alarms-updated'));
            }

            function formatCountdown(ms) {
                if (ms <= 0) return 'الآن 🔔';
                const totalSec = Math.ceil(ms / 1000);
                const mins = Math.floor(totalSec / 60);
                const secs = totalSec % 60;
                return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
            }

            function formatClockTime(ts) {
                try {
                    return new Date(ts).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
                } catch (e) {
                    return '';
                }
            }

            function showToast(msg) {
                const toast = document.getElementById('cc-alarm-toast');
                if (!toast) return;
                toast.textContent = msg;
                toast.style.display = 'block';
                clearTimeout(toastTimer);
                toastTimer = setTimeout(() => {
                    toast.style.display = 'none';
                }, 4800);
            }

            function requestBrowserPermission() {
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission().catch(() => {});
                }
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = String(text || '');
                return div.innerHTML;
            }

            function renderFloatingWidget() {
                const wrap = document.getElementById('cc-alarms-floating-wrap');
                const pillText = document.getElementById('cc-alarms-pill-text');
                const drawer = document.getElementById('cc-alarms-floating-drawer');
                if (!wrap || !pillText || !drawer) return;

                const alarms = getAll();
                if (alarms.length === 0) {
                    wrap.style.display = 'none';
                    drawer.style.display = 'none';
                    drawerOpen = false;
                    return;
                }

                wrap.style.display = 'flex';
                const nextAlarm = alarms[0];
                const remainingMs = nextAlarm.dueAt - Date.now();
                pillText.textContent = 'منبهات الكول سنتر (' + alarms.length + ') • ' + (remainingMs <= 0 ? 'حان الموعد!' : 'بعد ' + formatCountdown(remainingMs));

                drawer.style.display = drawerOpen ? 'block' : 'none';
                if (!drawerOpen) return;

                let html = '<div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(255,255,255,0.12);padding-bottom:8px;margin-bottom:8px;">'
                    + '<strong style="color:#fbbf24;font-size:13px;">⏰ مكالمات مجدولة للإعادة</strong>'
                    + '<button type="button" onclick="ERPCallAlarms.toggleDrawer()" style="background:none;border:none;color:#94a3b8;font-size:18px;cursor:pointer;">&times;</button>'
                    + '</div>';

                alarms.forEach(a => {
                    const rem = a.dueAt - Date.now();
                    const qParam = encodeURIComponent(a.phone || a.customerName || '');
                    html += '<div style="background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;padding:9px;margin-bottom:8px;">'
                        + '<div style="display:flex;justify-content:space-between;align-items:center;gap:6px;">'
                        + '<strong style="font-size:13px;color:#fff;">' + escapeHtml(a.customerName) + '</strong>'
                        + '<span style="font-size:11.5px;font-weight:800;color:' + (rem <= 0 ? '#f87171' : '#fcd34d') + ';">' + escapeHtml(formatCountdown(rem)) + '</span>'
                        + '</div>'
                        + '<div style="font-size:11.5px;color:#93c5fd;margin:3px 0;" dir="ltr">' + escapeHtml(a.phone || '-') + '</div>'
                        + '<div style="font-size:11px;color:#cbd5e1;margin-bottom:7px;">السبب: ' + escapeHtml(a.reasonLabel) + '</div>'
                        + '<div style="display:flex;gap:6px;">'
                        + '<a href="index.php?r=call_center&q=' + qParam + '" style="flex:1;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;padding:4px 8px;font-size:11px;font-weight:700;text-align:center;">🔍 فتح</a>'
                        + '<button type="button" onclick="ERPCallAlarms.dismiss(\'' + escapeHtml(a.id) + '\')" style="background:rgba(239,68,68,0.22);color:#fca5a5;border:1px solid rgba(239,68,68,0.45);border-radius:6px;padding:4px 8px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;">إلغاء</button>'
                        + '</div></div>';
                });
                drawer.innerHTML = html;
            }

            function showDueModal(alarm) {
                const modal = document.getElementById('cc-callback-alarm-modal');
                if (!modal) return;

                activeAlarmId = alarm.id;
                const elapsedMin = Math.max(1, Math.round((Date.now() - alarm.createdAt) / 60000));
                document.getElementById('cc-alarm-cust-name').textContent = alarm.customerName || 'عميل';
                document.getElementById('cc-alarm-cust-phone').textContent = alarm.phone || '-';
                document.getElementById('cc-alarm-reason').textContent =
                    'السبب: ' + (alarm.reasonLabel || 'لم يرد') + ' • منذ ' + elapsedMin + ' دقيقة (الساعة ' + formatClockTime(alarm.createdAt) + ')';

                const callBtn = document.getElementById('cc-alarm-call-btn');
                const waBtn = document.getElementById('cc-alarm-wa-btn');
                const snooze15Btn = document.getElementById('cc-alarm-snooze15-btn');
                const snooze30Btn = document.getElementById('cc-alarm-snooze30-btn');
                const dismissBtn = document.getElementById('cc-alarm-dismiss-btn');

                const dialPhone = cleanDial(alarm.phone);
                const waPhone = cleanWhatsApp(alarm.phone);

                callBtn.onclick = function () {
                    dismiss(alarm.id);
                    if (dialPhone) {
                        try { window.location.href = 'tel:' + dialPhone; } catch (e) {}
                    }
                    const qTarget = encodeURIComponent(alarm.phone || alarm.customerName || '');
                    setTimeout(() => {
                        window.location.href = 'index.php?r=call_center&q=' + qTarget;
                    }, 350);
                };

                if (waPhone) {
                    waBtn.style.display = 'inline-flex';
                    waBtn.href = 'https://wa.me/' + waPhone;
                } else {
                    waBtn.style.display = 'none';
                }

                snooze15Btn.onclick = function () { snooze(alarm.id, 15); };
                snooze30Btn.onclick = function () { snooze(alarm.id, 30); };
                dismissBtn.onclick = function () { dismiss(alarm.id); };

                modal.style.display = 'flex';
                startAlarmRingtone(alarm.customerName || 'العميل');

                if ('Notification' in window && Notification.permission === 'granted' && !notifiedIds[alarm.id]) {
                    notifiedIds[alarm.id] = true;
                    try {
                        const n = new Notification('🔔 اتصل على العميل: ' + (alarm.customerName || 'عميل'), {
                            body: 'اتصل على ' + (alarm.customerName || 'العميل') + (alarm.phone ? ' (' + alarm.phone + ')' : '') + ' — ' + (alarm.reasonLabel || 'لم يرد') + ' منذ نصف ساعة',
                            icon: 'assets/whatsapp_logo.png',
                            tag: alarm.id,
                            requireInteraction: true
                        });
                        n.onclick = function () {
                            window.focus();
                            const qTarget = encodeURIComponent(alarm.phone || alarm.customerName || '');
                            window.location.href = 'index.php?r=call_center&q=' + qTarget;
                            n.close();
                        };
                    } catch (e) {}
                }
            }

            function hideDueModal() {
                const modal = document.getElementById('cc-callback-alarm-modal');
                if (modal) modal.style.display = 'none';
                activeAlarmId = null;
                stopAlarmRingtone();
            }

            function checkDueAlarms() {
                const alarms = getAll();
                const now = Date.now();
                const due = alarms.find(a => a.dueAt <= now);
                if (due) {
                    if (activeAlarmId !== due.id) {
                        showDueModal(due);
                    }
                } else if (activeAlarmId) {
                    hideDueModal();
                }
                renderFloatingWidget();
            }

            function getAll() {
                return loadAlarms()
                    .filter(a => a && a.dueAt)
                    .sort((a, b) => a.dueAt - b.dueAt);
            }

            function schedule(opts) {
                const customerId = opts && opts.customerId ? Number(opts.customerId) : null;
                const customerName = (opts && opts.customerName ? String(opts.customerName) : '').trim() || 'عميل';
                const phone = (opts && opts.phone ? String(opts.phone) : '').trim();
                const reasonCode = (opts && opts.reasonCode) || 'no_answer';
                const reasonLabel = (opts && opts.reasonLabel) || REASON_LABELS[reasonCode] || 'لم يرد';
                const delayMinutes = Number(opts && opts.delayMinutes) > 0 ? Number(opts.delayMinutes) : 30;

                unlockAudio();
                requestBrowserPermission();

                const now = Date.now();
                const alarms = loadAlarms().filter(a => {
                    if (customerId && Number(a.customerId) === customerId) return false;
                    if (!customerId && phone && cleanDial(a.phone) === cleanDial(phone)) return false;
                    return true;
                });

                alarms.push({
                    id: 'cca_' + (customerId || 'p') + '_' + now,
                    customerId: customerId,
                    customerName: customerName,
                    phone: phone,
                    reasonCode: reasonCode,
                    reasonLabel: reasonLabel,
                    createdAt: now,
                    dueAt: now + delayMinutes * 60 * 1000
                });

                saveAlarms(alarms);
                showToast('⏰ تم ضبط منبه للاتصال بـ "' + customerName + '" بعد ' + delayMinutes + ' دقيقة (' + reasonLabel + ')');
            }

            function dismiss(alarmId) {
                const alarms = loadAlarms().filter(a => a.id !== alarmId);
                if (activeAlarmId === alarmId) {
                    hideDueModal();
                }
                saveAlarms(alarms);
                checkDueAlarms();
            }

            function cancelByCustomer(customerId) {
                if (!customerId) return;
                const cid = Number(customerId);
                const before = loadAlarms();
                const after = before.filter(a => Number(a.customerId) !== cid);
                if (after.length !== before.length) {
                    if (activeAlarmId && !after.some(a => a.id === activeAlarmId)) {
                        hideDueModal();
                    }
                    saveAlarms(after);
                }
            }

            function snooze(alarmId, minutes) {
                const mins = Number(minutes) > 0 ? Number(minutes) : 15;
                const alarms = loadAlarms();
                const target = alarms.find(a => a.id === alarmId);
                if (!target) {
                    hideDueModal();
                    return;
                }
                target.dueAt = Date.now() + mins * 60 * 1000;
                delete notifiedIds[alarmId];
                hideDueModal();
                saveAlarms(alarms);
                showToast('⏰ تم تأجيل منبه "' + target.customerName + '" لمدة ' + mins + ' دقيقة');
            }

            function testSound() {
                unlockAudio();
                requestBrowserPermission();
                playAlarmPattern();
                const permText = ('Notification' in window)
                    ? (Notification.permission === 'granted' ? 'وإشعارات المتصفح مفعّلة ✅' : 'يُرجى السماح بإشعارات المتصفح من النافذة')
                    : '';
                showToast('🔔 تم تشغيل صوت المنبه التجريبي ' + permText);
            }

            function isNoAnswerOutcome(outcome) {
                return outcome === 'no_answer' || outcome === 'busy' || outcome === 'wrong_number';
            }

            function toggleDrawer() {
                drawerOpen = !drawerOpen;
                renderFloatingWidget();
            }

            window.ERPCallAlarms = {
                schedule: schedule,
                dismiss: dismiss,
                cancelByCustomer: cancelByCustomer,
                snooze: snooze,
                getAll: getAll,
                testSound: testSound,
                isNoAnswerOutcome: isNoAnswerOutcome,
                toggleDrawer: toggleDrawer,
                formatCountdown: formatCountdown,
                formatClockTime: formatClockTime
            };

            document.addEventListener('pointerdown', unlockAudio, { once: true });
            window.addEventListener('storage', function (e) {
                if (e.key === STORAGE_KEY) {
                    checkDueAlarms();
                    window.dispatchEvent(new CustomEvent('erp-call-alarms-updated'));
                }
            });
            window.addEventListener('focus', checkDueAlarms);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) checkDueAlarms();
            });
            document.addEventListener('DOMContentLoaded', function () {
                checkDueAlarms();
                setInterval(checkDueAlarms, 5000);
                setInterval(renderFloatingWidget, 1000);
            });
        })();
    </script>
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
