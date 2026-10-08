<?php

declare(strict_types=1);

session_start();

// ضبط التوقيت على توقيت القاهرة
date_default_timezone_set('Africa/Cairo');

require_once __DIR__ . '/app/router.php';
require_once __DIR__ . '/app/layout.php';

$route = route();
handle_download($route);
handle_ajax_requests($route);

if ($route === 'install') {
    install_database();
    flash('تم تجهيز قاعدة بيانات MySQL. بيانات الدخول: admin / admin123');
    redirect('login');
}

if (!database_exists()) {
    ?>
    <!doctype html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>تثبيت النظام</title>
        <link rel="stylesheet" href="assets/style.css">
    </head>
    <body class="login-page">
    <main class="login-card">
        <div class="brand-mark">ERP</div>
        <h1><?= e(APP_NAME) ?></h1>
        <p>لم يتم تثبيت قاعدة البيانات بعد.</p>
        <a class="btn primary" href="index.php?r=install">تثبيت MySQL الآن</a>
    </main>
    </body>
    </html>
    <?php
    exit;
}

if ($route === 'login') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (login_user(post_string('username'), post_string('password'))) {
            redirect('dashboard');
        }
        flash('بيانات الدخول غير صحيحة.', 'danger');
    }
    render_login();
    exit;
}

if ($route === 'logout') {
    logout_user();
    redirect('login');
}

if ($route === 'print_barcode') {
    $user = require_login();
    $file = page_path_for_route($route);
    if ($file && is_file($file)) {
        require $file;
    } else {
        exit('الملف غير موجود');
    }
    exit;
}

if ($route === 'quick_add_customer') {
    $user = require_login();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = post_string('name');
        $phone = post_string('phone');
        $birthdate = post_string('birthdate');
        $locationId = current_user_location_id();
        if ($locationId === null && post_string('location_id') !== '') {
            $locationId = (int) post_string('location_id');
            if ($locationId > 0) {
                require_location_access($locationId);
            } else {
                $locationId = null;
            }
        }
        if (!$name) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'الاسم مطلوب']);
            exit;
        }
        $db = pdo();
        if ($phone !== '') {
            $stmt = $db->prepare('SELECT id, name, phone, is_active FROM customers WHERE phone = ? ORDER BY is_active DESC, id DESC LIMIT 1');
            $stmt->execute([$phone]);
            $existing = $stmt->fetch();
            if ($existing) {
                if ((int) $existing['is_active'] === 0) {
                    $stmt = $db->prepare('UPDATE customers SET name = ?, source = "offline", location_id = COALESCE(location_id, ?), created_by = COALESCE(created_by, ?), is_active = 1 WHERE id = ?');
                    $stmt->execute([$name, $locationId, (int) $user['id'], (int) $existing['id']]);
                    $existing['name'] = $name;
                }
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'id' => (int) $existing['id'],
                    'name' => $existing['name'],
                    'phone' => $existing['phone'],
                    'message' => 'العميل مسجل مسبقاً'
                ]);
                exit;
            }
        }
        $id = add_customer([
            'name' => $name,
            'phone' => $phone,
            'source' => 'offline',
            'notes' => '',
            'birthdate' => $birthdate,
            'location_id' => $locationId,
            'created_by' => (int) $user['id'],
        ]);
        
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'id' => $id,
            'name' => $name,
            'phone' => $phone
        ]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'طلب غير صالح']);
    exit;
}

if ($route === 'barcode_lookup') {
    $user = require_login();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $barcode = trim((string) ($_GET['barcode'] ?? ''));
        $locationId = (int) ($_GET['location_id'] ?? 0);
        header('Content-Type: application/json');
        if ($barcode === '') {
            echo json_encode(['status' => 'error', 'message' => 'الباركود مطلوب']);
            exit;
        }
        $product = find_product_by_barcode($barcode);
        if ($product) {
            $branchStock = $locationId > 0 ? get_stock($locationId, (int) $product['id']) : null;
            echo json_encode([
                'status' => 'success',
                'product' => [
                    'id' => (int) $product['id'],
                    'name' => $product['name'],
                    'type' => $product['type'],
                    'sale_price' => (float) $product['sale_price'],
                    'size_ml' => $product['size_ml'] ? (int) $product['size_ml'] : null,
                    'perfume_family' => $product['perfume_family'] ?? null,
                    'quality_grade' => $product['quality_grade'] ?? null,
                    'price_per_gram' => $product['price_per_gram'] ? (float) $product['price_per_gram'] : null,
                    'branch_stock' => $branchStock,
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'لا يوجد منتج بهذا الباركود']);
        }
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'طلب غير صالح']);
    exit;
}

if ($route === 'stock_check') {
    $user = require_login();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $locationId = (int) ($_GET['location_id'] ?? 0);
        $productId  = (int) ($_GET['product_id'] ?? 0);
        header('Content-Type: application/json');
        if ($locationId <= 0 || $productId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'بيانات غير صالحة']);
            exit;
        }
        $stock = get_stock($locationId, $productId);
        echo json_encode(['status' => 'success', 'stock' => $stock]);
        exit;
    }
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'طلب غير صالح']);
    exit;
}

// ===== Offline POS Invoice Sync =====
if ($route === 'pos_sync_offline') {
    $user = require_login();
    header('Content-Type: application/json');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'POST required']);
        exit;
    }
    if (!has_permission('pos')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'غير مصرح']);
        exit;
    }
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    if (!is_array($payload) || empty($payload['invoices'])) {
        echo json_encode(['status' => 'error', 'message' => 'لا توجد فواتير للمزامنة']);
        exit;
    }
    $results = [];
    foreach ($payload['invoices'] as $inv) {
        $localId = $inv['local_id'] ?? ('offline_' . uniqid());
        try {
            $data = [];
            $data['location_id']       = $inv['location_id'] ?? '';
            $data['customer_id']       = $inv['customer_id'] ?? '';
            $data['payment_method']    = $inv['payment_method'] ?? 'cash';
            $data['paid_total']        = $inv['paid_total'] ?? 0;
            $data['discount_type']     = $inv['discount_type'] ?? '';
            $data['discount_value']    = $inv['discount_value'] ?? 0;
            $data['notes']             = $inv['notes'] ?? '';
            $data['deduction_user_id'] = $inv['deduction_user_id'] ?? '';
            $data['paid_cash']         = $inv['paid_cash'] ?? 0;
            $data['paid_instapay']     = $inv['paid_instapay'] ?? 0;
            $data['paid_vodafone_cash']= $inv['paid_vodafone_cash'] ?? 0;
            $data['product_id']         = [];
            $data['quantity']           = [];
            $data['unit_price']         = [];
            $data['line_discount_type'] = [];
            $data['line_discount_value']= [];
            $data['recipe_id']          = [];
            $data['mix_data']           = [];
            foreach ($inv['cart'] ?? [] as $item) {
                if ($item['type'] === 'product') {
                    $data['product_id'][]           = $item['id'];
                    $data['quantity'][]              = $item['qty'];
                    $data['unit_price'][]            = $item['price'];
                    $data['line_discount_type'][]    = $item['discountType'] ?? '';
                    $data['line_discount_value'][]   = $item['discountValue'] ?? 0;
                } elseif ($item['type'] === 'recipe') {
                    for ($q = 0; $q < (int)($item['qty'] ?? 1); $q++) {
                        $data['recipe_id'][] = $item['id'];
                    }
                } elseif ($item['type'] === 'custom_recipe') {
                    $mixData = [
                        'bottle_id'      => $item['bottle_id'] ?? 0,
                        'without_bottle' => $item['without_bottle'] ?? false,
                        'sale_price'     => $item['price'] ?? 0,
                        'default_price'  => $item['defaultPrice'] ?? $item['price'] ?? 0,
                        'components'     => $item['components'] ?? [],
                    ];
                    $data['mix_data'][] = json_encode($mixData);
                }
            }
            $invoiceId = create_invoice($data, $user);
            $results[] = ['local_id' => $localId, 'status' => 'ok', 'invoice_id' => $invoiceId];
        } catch (Throwable $e) {
            $results[] = ['local_id' => $localId, 'status' => 'error', 'message' => $e->getMessage()];
        }
    }
    echo json_encode(['status' => 'ok', 'results' => $results]);
    exit;
}

$user = require_login();

// Bypassing layout for fast invoice printing (Task 5)
if ($route === 'invoice_view' && isset($_GET['print']) && $_GET['print'] === '1') {
    ?>
    <!doctype html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>طباعة فاتورة</title>
        <link rel="stylesheet" href="assets/style.css">
        <style>
            body {
                background: #fff !important;
                margin: 0;
                padding: 0;
            }
            .screen-only {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
        </style>
    </head>
    <body>
    <?php
    require __DIR__ . '/app/pages/main/invoice_view.php';
    ?>
    </body>
    </html>
    <?php
    exit;
}

// Auto close shifts if needed (Task 1)
auto_close_shifts_if_needed();

verify_csrf();

try {
    handle_post($route, $user);
} catch (Throwable $e) {
    flash($e->getMessage(), 'danger');
    redirect($route);
}

render_layout($route, $user);

