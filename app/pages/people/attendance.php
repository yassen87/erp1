<?php
$users = all_users();
$locations = attendance_locations();
$userLocationId = current_user_location_id();
if ($userLocationId !== null) {
    $locations = array_values(array_filter($locations, fn ($l) => (int) $l['id'] === $userLocationId));
    $users = array_values(array_filter($users, fn ($u) => (int) $u['id'] === (int) $user['id']));
}
$tab = $_GET['tab'] ?? 'scan';

$canAction = get_next_attendance_action((int) $user['id']);
?>
<section class="page-head">
    <div>
        <h2>تسجيل الحضور بالكاميرا</h2>
        <p>مسح رمز QR لتسجيل الحضور والانصراف تلقائياً.</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a class="btn <?= $tab === 'scan' ? 'primary' : '' ?>" href="index.php?r=attendance&tab=scan">📷 مسح QR</a>
        <?php if (has_permission('users_permissions')): ?>
            <a class="btn <?= $tab === 'qrcodes' ? 'primary' : '' ?>" href="index.php?r=attendance&tab=qrcodes">رموز QR</a>
        <?php endif; ?>
        <a class="btn primary" href="index.php?r=attendance_log">📋 سجل الحضور</a>
    </div>
</section>

<?php if ($tab === 'qrcodes' && has_permission('users_permissions')): ?>
    <!-- Branch QR Codes List -->
    <div class="panel">
        <h3>رموز QR الخاصة بالفروع لتسجيل الحضور</h3>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--line); text-align: right;">
                    <th style="padding:10px;">الفرع / الموقع</th>
                    <th style="padding:10px;">النوع</th>
                    <th style="padding:10px; text-align:center;">رمز الـ QR</th>
                    <th style="padding:10px;">الموقع الجغرافي (GPS)</th>
                    <th style="padding:10px; text-align:center;">إجراء</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($locations as $l): ?>
                    <?php if ($l['type'] === 'online') continue; ?>
                    <tr style="border-bottom:1px solid var(--line);">
                        <td style="padding:10px; font-weight:700;"><?= e($l['name']) ?></td>
                        <td style="padding:10px;"><span class="badge"><?= e($l['type'] === 'warehouse' ? 'مخزن رئيسي' : 'فرع مبيعات') ?></span></td>
                        <td style="padding:10px; text-align:center;">
                            <?php if ($l['qr_code']): ?>
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120&data=<?= urlencode($l['qr_code']) ?>" alt="QR" style="border: 1px solid var(--line); border-radius: 6px; padding: 4px; background: #fff; width:80px; height:80px;">
                            <?php else: ?>
                                <span class="muted">لا يوجد رمز حالياً</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:10px;">
                            <form method="post" class="inline" style="display:flex; gap:6px; align-items:center; margin:0;">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update_location_geo">
                                <input type="hidden" name="location_id" value="<?= e($l['id']) ?>">
                                <input type="number" step="0.0000001" name="latitude" value="<?= e($l['latitude'] ?? '') ?>" placeholder="Latitude" style="width:100px; padding:4px;" required>
                                <input type="number" step="0.0000001" name="longitude" value="<?= e($l['longitude'] ?? '') ?>" placeholder="Longitude" style="width:100px; padding:4px;" required>
                                <button type="button" class="btn small" onclick="getCurrentLocationForBranch(this)">📍 موقعي</button>
                                <button class="btn small primary">حفظ</button>
                            </form>
                        </td>
                        <td style="padding:10px; text-align:center;">
                            <form method="post" style="display:inline-block; margin:0 4px;">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="generate_qr">
                                <input type="hidden" name="location_id" value="<?= e($l['id']) ?>">
                                <button class="btn small"><?= $l['qr_code'] ? 'إعادة توليد الرمز' : 'توليد رمز QR' ?></button>
                            </form>
                            <?php if ($l['qr_code']): ?>
                                <button type="button" class="btn small success" onclick="printQRCode('<?= e(addslashes($l['name'])) ?>', '<?= e(addslashes($l['qr_code'])) ?>')">طباعة الرمز 🖨️</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
    function printQRCode(name, qrCode) {
        const url = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrCode)}`;
        const win = window.open('', '_blank');
        win.document.write(`
            <html>
            <head>
                <title>طباعة QR - ${name}</title>
                <style>
                    body { display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh; font-family:'Cairo', Arial, sans-serif; margin:0; text-align:center; }
                    img { width: 320px; height: 320px; margin-bottom: 25px; border: 2px solid #000; padding:10px; border-radius: 12px; }
                    h1 { font-size: 26px; margin: 0; color: #111; }
                    p { font-size: 14px; color: #666; margin-top: 5px; }
                </style>
            </head>
            <body onload="setTimeout(() => { window.print(); window.close(); }, 500);">
                <img src="${url}">
                <h1>بوابة الحضور والانصراف الذكي</h1>
                <h1>الفرع: ${name}</h1>
                <p>قم بمسح هذا الرمز عبر هاتفك لتسجيل الحضور أو الانصراف</p>
            </body>
            </html>
        `);
        win.document.close();
    }

    function getCurrentLocationForBranch(btn) {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((pos) => {
                const form = btn.closest('form');
                form.querySelector('input[name="latitude"]').value = pos.coords.latitude.toFixed(7);
                form.querySelector('input[name="longitude"]').value = pos.coords.longitude.toFixed(7);
            }, (err) => {
                alert("خطأ في تحديد الموقع الجغرافي: " + err.message);
            });
        } else {
            alert("متصفحك لا يدعم تحديد الموقع الجغرافي.");
        }
    }
    </script>

<?php else: ?>
    <!-- QR Scanning Camera Tab -->
    <div class="panel" style="text-align: center; max-width: 500px; margin: 0 auto;">
        <h3>مسح الـ QR لتسجيل حضور/انصراف</h3>
        <p class="muted" style="margin-bottom: 20px;">افتح الكاميرا وقم بمسح رمز الـ QR المعلق بالفرع أو المخزن.</p>
        
        <div style="margin-bottom: 20px;">
            <label style="display:inline-block; font-weight:bold; margin-bottom:8px;">العملية الحالية:</label><br>
            <div style="display:inline-flex; gap: 20px; font-size:16px;">
                <?php if ($canAction === 'check_in'): ?>
                    <span class="badge success" style="font-size: 16px; padding: 6px 16px;">تسجيل حضور 🟢</span>
                    <input type="hidden" id="scan_action_select_val" value="check_in">
                <?php else: ?>
                    <span class="badge warning" style="font-size: 16px; padding: 6px 16px;">تسجيل انصراف 🔴</span>
                    <input type="hidden" id="scan_action_select_val" value="check_out">
                <?php endif; ?>
            </div>
        </div>
        
        <button type="button" class="btn primary" id="btn-start-scanner" style="padding: 10px 24px; font-size:14px; margin-bottom: 15px;">📷 فتح كاميرا الهاتف للمسح</button>
        
        <div id="scanner-container" style="display:none; margin: 20px 0;">
            <div id="reader" style="width: 100%; max-width: 400px; margin: 0 auto; border: 2px solid var(--line); border-radius: 12px; overflow: hidden; background:#000;"></div>
            <button type="button" class="btn danger small" id="btn-stop-scanner" style="margin-top:10px;">إغلاق الكاميرا</button>
        </div>

        <form id="qr-scan-form" method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="qr_scan">
            <input type="hidden" name="qr_token" id="qr-token-input">
            <input type="hidden" name="scan_action" id="scan-action-input">
            <input type="hidden" name="latitude" id="lat-input">
            <input type="hidden" name="longitude" id="lng-input">
        </form>
    </div>

    <script src="assets/html5-qrcode.min.js"></script>
    <script>
    let html5QrcodeScanner = null;

    function fetchGeolocation(callback) {
        if (!navigator.geolocation) {
            console.warn("Geolocation not supported by this browser.");
            if (callback) callback(null);
            return;
        }

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                document.getElementById('lat-input').value = pos.coords.latitude.toFixed(7);
                document.getElementById('lng-input').value = pos.coords.longitude.toFixed(7);
                if (callback) callback(pos);
            },
            (err) => {
                console.warn("Geolocation high accuracy failed, falling back:", err.message);
                navigator.geolocation.getCurrentPosition(
                    (pos) => {
                        document.getElementById('lat-input').value = pos.coords.latitude.toFixed(7);
                        document.getElementById('lng-input').value = pos.coords.longitude.toFixed(7);
                        if (callback) callback(pos);
                    },
                    (err2) => {
                        console.error("Geolocation failed completely:", err2.message);
                        if (callback) callback(null);
                    },
                    { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 }
                );
            },
            options
        );
    }

    function startScanner() {
        const container = document.getElementById('scanner-container');
        const startBtn = document.getElementById('btn-start-scanner');

        if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
            alert("تنبيه هام: المتصفحات تحظر استخدام الكاميرا عبر بروتوكول HTTP غير الآمن. لتشغيل الكاميرا من الهاتف، يرجى تفعيل HTTPS على السيرفر، أو تجربة مسح الـ QR من الكمبيوتر نفسه عبر localhost.");
        }

        if (container) container.style.display = 'block';
        if (startBtn) startBtn.style.display = 'none';

        // Refresh location when starting scanner
        fetchGeolocation();

        if (html5QrcodeScanner) return; // Already running

        html5QrcodeScanner = new Html5Qrcode("reader");
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            },
            onScanSuccess,
            onScanFailure
        ).then(() => {
            localStorage.setItem('camera_allowed', 'true');
        }).catch(err => {
            console.error("خطأ في تشغيل الكاميرا: ", err);
            localStorage.removeItem('camera_allowed');
            stopScanner(false);
        });
    }

    function stopScanner(userInitiated = false) {
        const container = document.getElementById('scanner-container');
        const startBtn = document.getElementById('btn-start-scanner');
        if (container) container.style.display = 'none';
        if (startBtn) startBtn.style.display = 'inline-block';

        if (userInitiated) {
            localStorage.removeItem('camera_allowed');
        }

        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                html5QrcodeScanner = null;
            }).catch(err => {
                console.error("Failed to stop scanner", err);
            });
        }
    }

    document.getElementById('btn-start-scanner')?.addEventListener('click', () => {
        startScanner();
    });

    document.getElementById('btn-stop-scanner')?.addEventListener('click', () => {
        stopScanner(true);
    });

    function onScanSuccess(decodedText, decodedResult) {
        stopScanner(false); // Do not clear camera_allowed on successful scan
        
        document.getElementById('qr-token-input').value = decodedText;
        
        const selectedAction = document.getElementById('scan_action_select_val').value;
        document.getElementById('scan-action-input').value = selectedAction;
        document.getElementById('qr-scan-form').submit();
    }

    function onScanFailure(error) {
        // Silent
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Fetch geolocation immediately on load to pre-populate inputs and request permission early
        fetchGeolocation();

        // Auto start scanner if previously allowed
        if (localStorage.getItem('camera_allowed') === 'true') {
            startScanner();
        }
    });
    </script>
<?php endif; ?>
