<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/services.php';
require_once __DIR__ . '/navigation.php';

function handle_ajax_requests(string $route): void
{
    // Handle AJAX requests
    if ($route === 'pos_products') {
        require_login();
        if (!has_permission('pos')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ØºÙŠØ± Ù…ØµØ±Ø­']);
            exit;
        }
        
        $locationId = (int) ($_GET['location_id'] ?? 0);
        if ($locationId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ø§Ù„ÙØ±Ø¹ Ù…Ø·Ù„ÙˆØ¨']);
            exit;
        }
        
        // Check if user can access this location
        $userLocationId = current_user_location_id();
        if ($userLocationId !== null && $userLocationId !== $locationId) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ù„ÙˆØµÙˆÙ„ Ù„Ù‡Ø°Ø§ Ø§Ù„ÙØ±Ø¹']);
            exit;
        }
        
        $allProducts = all_products_with_stock($locationId, null, true);
        // Filter: show only products with type != recipe
        $products = array_filter($allProducts, fn($p) => $p['type'] !== 'recipe');
        $recipes = saved_recipes();
        $offers = active_offers_for_pos($locationId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'products' => array_values($products),
            'recipes' => array_values($recipes),
            'offers' => array_values($offers),
        ]);
        exit;
    }

    if ($route === 'api_customer_invoices') {
        require_login();
        if (!has_permission('pos') && !has_permission('invoices_view')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'ØºÙŠØ± Ù…ØµØ±Ø­']);
            exit;
        }

        $customerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
        $locationId = (int) ($_GET['location_id'] ?? $_POST['location_id'] ?? 0);
        $search = trim((string) ($_GET['search'] ?? $_POST['search'] ?? ''));

        if ($customerId <= 0 && $search === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Ø§Ù„Ø¹Ù…ÙŠÙ„ Ø£Ùˆ Ù†Øµ Ø§Ù„Ø¨Ø­Ø« Ù…Ø·Ù„ÙˆØ¨']);
            exit;
        }

        $invoices = customer_recent_invoices_for_pos($customerId > 0 ? $customerId : null, $locationId > 0 ? $locationId : null, 25, $search !== '' ? $search : null);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'invoices' => $invoices,
        ]);
        exit;
    }

    if ($route === 'api_get_off_orders') {
        require_login();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $user = current_user();
            $loc = isset($user['location_id']) ? $user['location_id'] : 0;
            $locationId = (int)($_GET['location_id'] ?? $loc);
            
            $formulas = get_off_order_formulas($locationId);
            foreach ($formulas as &$f) {
                $f['components'] = get_invoice_line_components((int)$f['id']);
            }
            unset($f);
            
            echo json_encode(['success' => true, 'formulas' => $formulas]);
        } catch (Throwable $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($route === 'api_products_quick_add' && has_permission('products_add')) {
        require_login();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true);
            if (!$data) $data = [];
            if (empty($data['products']) || !is_array($data['products'])) {
                throw new Exception('Ù„Ø§ ØªÙˆØ¬Ø¯ Ù…Ù†ØªØ¬Ø§Øª Ù„Ù„Ø¥Ø¶Ø§ÙØ©');
            }

            $db = pdo();
            $db->beginTransaction();
            foreach ($data['products'] as $p) {
                $type = (string)($p['type'] ?? '');
                $name = trim((string)($p['name'] ?? ''));
                if (!$name || !$type) continue;
                
                $minStock = (float)($p['min_stock'] ?? 0);
                
                if ($type === 'bottle' || $type === 'fixed') {
                    $size = (float)($p['size'] ?? 0);
                    $salePrice = (float)($p['sale_price'] ?? 0);
                    $costPrice = (float)($p['cost_price'] ?? 0);
                    $barcode = trim((string)($p['barcode'] ?? ''));
                    
                    if (!$barcode) {
                        $barcode = generate_unique_ean13($db);
                    }
                    
                    $fullName = $name;
                    if ($size > 0 && $type === 'bottle') $fullName .= " ($size ml)";
                    if ($size > 0 && $type === 'fixed') $fullName .= " ($size)";

                    $stmt = $db->prepare('INSERT INTO products (name, type, unit, min_stock, sale_price, cost_price, barcode) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $fullName,
                        $type,
                        'unit',
                        $minStock,
                        $salePrice,
                        $costPrice > 0 ? $costPrice : null,
                        $barcode
                    ]);
                    $productId = (int)$db->lastInsertId();
                    
                    save_product_details($db, $productId, $type, ['size_ml' => $size > 0 ? $size : null]);
                } else if ($type === 'perfume_gram') {
                    $quota = trim((string)($p['quota'] ?? ''));
                    $salePrice = (float)($p['sale_price'] ?? 0); 
                    $costPrice = (float)($p['cost_price'] ?? 0);
                    $pricePerGram = (float)($p['price_per_gram'] ?? 0);
                    $family = trim((string)($p['family'] ?? ''));
                    $quality = trim((string)($p['quality'] ?? ''));
                    
                    $fullName = $name;
                    if ($quota !== '') $fullName .= " - $quota";
                    
                    $barcode = generate_unique_ean13($db);
                    
                    $stmt = $db->prepare('INSERT INTO products (name, type, unit, min_stock, sale_price, cost_price, barcode, sku) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $fullName,
                        $type,
                        'gram',
                        $minStock,
                        $pricePerGram, 
                        $costPrice > 0 ? $costPrice : null,
                        $barcode,
                        $quota !== '' ? $quota : null
                    ]);
                    $productId = (int)$db->lastInsertId();
                    
                    save_product_details($db, $productId, $type, [
                        'perfume_family' => $family ?: null,
                        'quality_grade' => $quality ?: null,
                        'price_per_gram' => $pricePerGram
                    ]);
                }
            }
            $user = current_user();
            log_audit($user ? (int)$user['id'] : null, 'create', 'product', null, 'Ø¥Ø¶Ø§ÙØ© Ø³Ø±ÙŠØ¹Ø© Ù…ØªØ¹Ø¯Ø¯Ø© (' . count($data['products']) . ')');
            $db->commit();
            
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($route === 'api_pos_return') {
        require_login();
        if (!has_permission('pos') && !has_permission('manage_returns')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'ØºÙŠØ± Ù…ØµØ±Ø­ Ø¨Ø¹Ù…Ù„ Ù…Ø±ØªØ¬Ø¹']);
            exit;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true) ?? $_POST;

        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $refundMethod = (string) ($data['refund_method'] ?? 'cash');
        $refundPaid = isset($data['refund_paid']) ? (float) $data['refund_paid'] : -1.0;
        $reason = trim((string) ($data['reason'] ?? 'Ù…Ø±ØªØ¬Ø¹ Ù…Ø¨Ø§Ø´Ø± Ù…Ù† Ø§Ù„ÙƒØ§Ø´ÙŠØ±'));
        $linesInput = $data['lines'] ?? [];

        if ($invoiceId <= 0 || empty($linesInput)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…Ø±ØªØ¬Ø¹ ØºÙŠØ± Ù…ÙƒØªÙ…Ù„Ø©']);
            exit;
        }

        $lineIds = [];
        $returnedQuantities = [];
        foreach ($linesInput as $l) {
            $lId = (int) ($l['line_id'] ?? 0);
            $qty = (float) ($l['quantity'] ?? 0);
            if ($lId > 0 && $qty > 0) {
                $lineIds[] = $lId;
                $returnedQuantities[$lId] = $qty;
            }
        }

        if (empty($lineIds)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Ù„Ù… ÙŠØªÙ… ØªØ­Ø¯ÙŠØ¯ Ø£ØµÙ†Ø§Ù ØµØ§Ù„Ø­Ø© Ù„Ù„Ø¥Ø±Ø¬Ø§Ø¹']);
            exit;
        }

        try {
            $user = current_user();
            create_return_selected_lines($invoiceId, $lineIds, $returnedQuantities, $refundMethod, $reason, (int) $user['id'], $refundPaid);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù…Ø±ØªØ¬Ø¹ ÙˆØ§Ø³ØªØ¹Ø§Ø¯Ø© Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø¨Ù†Ø¬Ø§Ø­',
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
        exit;
    }
    
    if ($route === 'api_whatsapp') {
        require_login();
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || empty($data['phone']) || empty($data['message'])) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Ø¨ÙŠØ§Ù†Ø§Øª Ù†Ø§Ù‚ØµØ©']);
            exit;
        }

        $sent = send_whatsapp_message((string)$data['phone'], (string)$data['message']);
        header('Content-Type: application/json; charset=utf-8');
        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'ØªÙ… Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„Ø±Ø³Ø§Ù„Ø© Ø¨Ù†Ø¬Ø§Ø­']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'ØªØ¹Ø°Ø± Ø§Ù„Ø§ØªØµØ§Ù„ Ø¨Ù…Ø­Ø±Ùƒ Ø§Ù„ÙˆØ§ØªØ³Ø§Ø¨ØŒ ÙŠØ±Ø¬Ù‰ Ø§Ù„ØªØ£ÙƒØ¯ Ù…Ù† ØªØ´ØºÙŠÙ„ Ø§Ù„Ø®Ø¯Ù…Ø©']);
        }
        exit;
    }

    if ($route === 'send_invoice_whatsapp') {
        require_login();
        $invoiceId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $result = send_invoice_whatsapp($invoiceId);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    if ($route === 'send_birthday_whatsapp') {
        require_login();
        $force = !empty($_GET['force']) || !empty($_POST['force']);
        $result = process_daily_birthday_whatsapp($force);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => true], $result));
        exit;
    }

    if ($route === 'api_latest_update') {
        header('Content-Type: application/json; charset=utf-8');
        $latest = get_latest_system_update();
        if ($latest) {
            echo json_encode([
                'success' => true,
                'has_update' => true,
                'update' => [
                    'id' => (int)$latest['id'],
                    'title' => $latest['title'],
                    'version' => $latest['version'] ?? '',
                    'content' => $latest['content'],
                    'urgency' => $latest['urgency'],
                    'created_at' => $latest['created_at'],
                    'formatted_date' => format_datetime($latest['created_at']),
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'has_update' => false,
                'update' => null
            ]);
        }
        exit;
    }

    if ($route === 'cron_birthdays') {
        $result = process_daily_birthday_whatsapp(false);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => true], $result));
        exit;
    }

    if ($route === 'process_survey_queue') {
        $result = process_survey_queue(50);
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => true], $result));
        exit;
    }
    
    if ($route === 'barcode_lookup') {
        require_login();
        if (!has_permission('pos')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false]);
            exit;
        }
        
        $barcode = trim((string) ($_GET['barcode'] ?? $_POST['barcode'] ?? ''));
        $locationId = (int) ($_GET['location_id'] ?? $_POST['location_id'] ?? current_user_location_id() ?? 0);
        
        if (!$barcode) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ø¨ÙŠØ§Ù†Ø§Øª Ù†Ø§Ù‚ØµØ©']);
            exit;
        }
        
        // Check if user can access this location
        $userLocationId = current_user_location_id();
        if ($userLocationId !== null && $locationId > 0 && $userLocationId !== $locationId) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ØºÙŠØ± Ù…ØµØ±Ø­']);
            exit;
        }
        
        // Find product by barcode
        $db = pdo();
        $stmt = $db->prepare('SELECT * FROM products WHERE barcode = ? OR sku = ? LIMIT 1');
        $stmt->execute([$barcode, $barcode]);
        $product = $stmt->fetch();
        
        if (!$product) {
            // Check if it is an offer barcode
            $stmtOffer = $db->prepare('SELECT * FROM offers WHERE barcode = ? AND is_active = 1 AND start_date <= CURDATE() AND end_date >= CURDATE() LIMIT 1');
            $stmtOffer->execute([$barcode]);
            $offerRow = $stmtOffer->fetch();
            if ($offerRow) {
                $offer = find_offer_with_items((int)$offerRow['id']);
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'kind'   => 'offer',
                    'offer'  => $offer,
                ]);
                exit;
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Ø§Ù„Ù…Ù†ØªØ¬ Ø£Ùˆ Ø§Ù„Ø¹Ø±Ø¶ ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯']);
            exit;
        }
        
        // Get stock for this location
        $stockStmt = $db->prepare('SELECT COALESCE(ib.quantity, 0) as branch_stock, CASE WHEN ib.id IS NOT NULL THEN 1 ELSE 0 END AS stock_initialized
                                   FROM products p
                                   LEFT JOIN inventory_balances ib ON ib.product_id = p.id AND ib.location_id = ?
                                   WHERE p.id = ?');
        $stockStmt->execute([$locationId, $product['id']]);
        $stockData = $stockStmt->fetch();
        
        // If product is not initialized in this branch, still return it with a warning flag
        // (same behavior as the product dropdown in POS which shows uninitialized products)
        $product['branch_stock'] = $stockData ? $stockData['branch_stock'] : 0;
        $product['stock_initialized'] = $stockData ? $stockData['stock_initialized'] : 0;
        
        header('Content-Type: application/json');
        echo json_encode([
            'status'            => 'success',
            'product'           => [
                'id'                => $product['id'],
                'name'              => $product['name'],
                'type'              => $product['type'],
                'sale_price'        => $product['sale_price'],
                'branch_stock'      => $product['branch_stock'],
                'stock_initialized' => $product['stock_initialized'],
            ]
        ]);
        exit;
    }

    if ($route === 'get_stock') {
        require_login();
        $productId = (int) ($_GET['product_id'] ?? 0);
        $locationId = (int) ($_GET['location_id'] ?? current_user_location_id() ?? 0);
        
        if ($productId <= 0 || $locationId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'stock' => 0]);
            exit;
        }
        
        $db = pdo();
        $stmt = $db->prepare('SELECT quantity FROM inventory_balances WHERE product_id = ? AND location_id = ?');
        $stmt->execute([$productId, $locationId]);
        $stock = (float) ($stmt->fetchColumn() ?: 0);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'stock' => $stock]);
        exit;
    }

    // AJAX: Ø§Ù„Ø¨Ø­Ø« Ø¹Ù† Ø£Ø³Ù…Ø§Ø¡ Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ù„Ø¹Ø±Ø¶ Ø§Ù„Ù€ autocomplete
    if ($route === 'product_name_search') {
        require_login();
        $q = trim((string) ($_GET['q'] ?? ''));
        if (strlen($q) < 2) {
            header('Content-Type: application/json');
            echo json_encode(['results' => []]);
            exit;
        }
        $typeLabels = product_type_labels();
        $stmt = pdo()->prepare(
            "SELECT DISTINCT p.name, p.type FROM products p
             WHERE p.is_active = 1 AND p.name LIKE ?
             ORDER BY p.name ASC LIMIT 15"
        );
        $stmt->execute(['%' . $q . '%']);
        $rows = $stmt->fetchAll();
        $results = array_map(fn($r) => [
            'name'       => $r['name'],
            'type_label' => $typeLabels[$r['type']] ?? $r['type'],
        ], $rows);
        header('Content-Type: application/json');
        echo json_encode(['results' => $results]);
        exit;
    }
}

function handle_download(string $route): void
{
    if ($route === 'reports' && !empty($_GET['export'])) {
        require_login();
        $key = preg_replace('/[^a-z_]/', '', (string) $_GET['export']);
        if (!has_permission('reports_' . $key)) {
            http_response_code(403);
            exit('ØºÙŠØ± Ù…ØµØ±Ø­');
        }
        if (current_user_location_id() !== null) {
            $_GET['location_id'] = (string) current_user_location_id();
        }
        output_csv('report-' . $key . '.csv', report_rows($key, $_GET));
    }

    if ($route !== 'backup' || empty($_GET['download'])) {
        return;
    }
    require_login();
    if (!has_permission('backup')) {
        http_response_code(403);
        exit('ØºÙŠØ± Ù…ØµØ±Ø­');
    }
    $file = backup_file_path((string) $_GET['download']);
    if (!$file) {
        http_response_code(404);
        exit('Ø§Ù„Ù…Ù„Ù ØºÙŠØ± Ù…ÙˆØ¬ÙˆØ¯');
    }
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
}

function handle_post(string $route, array $user): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if ($route === 'products' && has_permission('products_view')) {
        if (post_string('action') === 'delete') {
            if (!has_permission('products_delete')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø­Ø°Ù Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª.');
            }
            $result = delete_product((int) post_string('id'));
            if ($result === 'permanently_deleted') {
                flash('ØªÙ… Ø­Ø°Ù Ø§Ù„Ù…Ù†ØªØ¬ Ù†Ù‡Ø§Ø¦ÙŠØ§Ù‹ Ø¨Ù†Ø¬Ø§Ø­.');
            } else {
                flash('ØªÙ… Ø¥Ø®ÙØ§Ø¡ Ø§Ù„Ù…Ù†ØªØ¬ ÙˆØªØ¹Ø·ÙŠÙ„Ù‡ Ù„ÙˆØ¬ÙˆØ¯ ÙÙˆØ§ØªÙŠØ± Ø£Ùˆ Ø­Ø±ÙƒØ§Øª Ù…Ø®Ø²Ù† Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ù‡.');
            }
            redirect('products');
        }
        throw new RuntimeException('Ø·Ù„Ø¨ ØºÙŠØ± ØµØ§Ù„Ø­.');
    }

    if ($route === 'product_create' && has_permission('products_add')) {
        add_products_batch($_POST);
        flash('ØªÙ… Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ù…Ù†ØªØ¬/Ø§Ù„Ù…Ù†ØªØ¬Ø§Øª Ø¨Ù†Ø¬Ø§Ø­.');
        redirect('products');
    }

    if ($route === 'product_edit' && has_permission('products_edit')) {
        update_product($_POST);
        flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…Ù†ØªØ¬.');
        redirect('products');
    }

    if ($route === 'recipes' && has_permission('recipes_view')) {
        if (post_string('action') === 'delete') {
            if (!has_permission('recipes_edit')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø­Ø°Ù Ø§Ù„ØªØ±ÙƒÙŠØ¨Ø§Øª.');
            }
            delete_recipe((int) post_string('id'));
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„ØªØ±ÙƒÙŠØ¨Ø© Ø¨Ù†Ø¬Ø§Ø­.');
        } else {
            if (!has_permission('recipes_add')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø¥Ø¶Ø§ÙØ© Ø£Ùˆ ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØªØ±ÙƒÙŠØ¨Ø§Øª.');
            }
            $recipe_id = (int) post_string('id');
            if ($recipe_id > 0) {
                update_recipe($recipe_id, $_POST);
                flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØªØ±ÙƒÙŠØ¨Ø© Ø¨Ù†Ø¬Ø§Ø­.');
            } else {
                add_recipe($_POST);
                flash('ØªÙ… Ø­ÙØ¸ Ø§Ù„ØªØ±ÙƒÙŠØ¨Ø© Ø§Ù„Ø¬Ø§Ù‡Ø²Ø©.');
            }
        }
        redirect('recipes');
    }

    if ($route === 'offers' && has_permission('products_view')) {
        $action = post_string('action');
        if ($action === 'delete') {
            if (!has_permission('products_edit') && !has_permission('products_add')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø­Ø°Ù Ø§Ù„Ø¹Ø±ÙˆØ¶.');
            }
            delete_offer((int) post_string('id'), (int)$user['id']);
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„Ø¹Ø±Ø¶ Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('offers');
        }
        if ($action === 'toggle_status') {
            if (!has_permission('products_edit')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ¹Ø¯ÙŠÙ„ Ø­Ø§Ù„Ø© Ø§Ù„Ø¹Ø±ÙˆØ¶.');
            }
            $newStatus = toggle_offer_status((int) post_string('id'), (int)$user['id']);
            flash($newStatus ? 'ØªÙ… ØªÙØ¹ÙŠÙ„ Ø§Ù„Ø¹Ø±Ø¶ Ø¨Ù†Ø¬Ø§Ø­.' : 'ØªÙ… Ø¥ÙŠÙ‚Ø§Ù/ØªØ¹Ø·ÙŠÙ„ Ø§Ù„Ø¹Ø±Ø¶ Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('offers');
        }
    }

    if ($route === 'offer_create' && has_permission('products_add')) {
        $offerId = create_offer($_POST, (int)$user['id']);
        flash('ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ø§Ù„Ø¹Ø±Ø¶ Ø¨Ù†Ø¬Ø§Ø­ ðŸŽ');
        redirect('offers');
    }

    if ($route === 'offer_edit' && has_permission('products_edit')) {
        $offerId = (int) post_string('id');
        update_offer($offerId, $_POST, (int)$user['id']);
        flash('ØªÙ… Ø­ÙØ¸ ØªØ¹Ø¯ÙŠÙ„Ø§Øª Ø§Ù„Ø¹Ø±Ø¶ Ø¨Ù†Ø¬Ø§Ø­.');
        redirect('offers');
    }

    if ($route === 'formula_defaults' && has_permission('recipes_view')) {
        $action = post_string('action');
        if ($action === 'delete') {
            if (!has_permission('recipes_edit')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø­Ø°Ù Ø§Ù„Ø¬Ø±Ø§Ù…Ø§Øª Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©.');
            }
            delete_formula_default((int) post_string('id'));
            flash('ØªÙ… Ø­Ø°Ù Ø¥Ø¹Ø¯Ø§Ø¯ Ø§Ù„Ø¬Ø±Ø§Ù…Ø§Øª Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©.');
        } elseif ($action === 'delete_group') {
            if (!has_permission('recipes_edit')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø­Ø°Ù Ø§Ù„Ø¬Ø±Ø§Ù…Ø§Øª Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©.');
            }
            $qualityGrade = post_string('quality_grade');
            delete_formula_defaults_by_bottle((int) post_string('bottle_id'), $qualityGrade !== '' ? $qualityGrade : null);
            flash('ØªÙ… Ø­Ø°Ù Ù…Ø¬Ù…ÙˆØ¹Ø© Ø§Ù„Ø¬Ø±Ø§Ù…Ø§Øª Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ© Ù„Ù„Ø²Ø¬Ø§Ø¬Ø©.');
        } else {
            if (!has_permission('recipes_add')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø¥Ø¶Ø§ÙØ© Ø£Ùˆ ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø¬Ø±Ø§Ù…Ø§Øª Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©.');
            }
            if ($action === 'replace_bottle_group') {
                replace_formula_defaults_for_bottle($_POST);
                flash('ØªÙ… Ø­ÙØ¸ Ø±ÙˆØ§Ø¨Ø· Ø§Ù„Ø²Ø¬Ø§Ø¬Ø© Ø¨Ù†Ø¬Ø§Ø­.');
            } else {
                upsert_formula_default($_POST);
                flash('ØªÙ… Ø­ÙØ¸ Ø¥Ø¹Ø¯Ø§Ø¯ Ø§Ù„Ø¬Ø±Ø§Ù…Ø§Øª Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ© Ø¨Ù†Ø¬Ø§Ø­.');
            }
        }
        redirect('formula_defaults');
    }

    if ($route === 'inventory' && has_permission('inventory_view')) {
        if (!has_permission('inventory_adjust')) {
            throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ³ÙˆÙŠØ© Ø§Ù„Ù…Ø®Ø²ÙˆÙ†.');
        }
        $action = post_string('action');
        $locationId = (int) post_string('location_id');
        require_location_access($locationId);
        require_location_type($locationId, ['warehouse', 'branch'], 'Ø§Ù„Ø£ÙˆÙ†Ù„Ø§ÙŠÙ† Ù„ÙŠØ³ Ù…Ø®Ø²Ù†Ø§Ù‹ ÙˆÙ„Ø§ ÙŠÙ…ÙƒÙ† ØªØ¹Ø¯ÙŠÙ„ Ø±ØµÙŠØ¯Ù‡.');

        if ($action === 'delete') {
            $movementIdStr = post_string('movement_id');
            $movementIds = explode(',', $movementIdStr);
            foreach ($movementIds as $id) {
                if (is_numeric($id)) {
                    delete_inventory_addition((int) $id, (int) $user['id']);
                }
            }
            flash('ØªÙ… Ø­Ø°Ù Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø¨Ù†Ø¬Ø§Ø­.');
        } elseif ($action === 'set_zero') {
            // ØªØµÙÙŠØ± Ø±ØµÙŠØ¯ Ù…Ù†ØªØ¬ ÙÙŠ Ø§Ù„ÙØ±Ø¹
            $productId = (int) post_string('product_id');
            $db = pdo();
            $stmt = $db->prepare('UPDATE inventory_balances SET quantity = 0 WHERE product_id = ? AND location_id = ?');
            $stmt->execute([$productId, $locationId]);
            log_audit((int)$user['id'], 'update', 'inventory_balance', $productId, 'ØªØµÙÙŠØ± Ø±ØµÙŠØ¯ Ø§Ù„Ù…Ù†ØªØ¬ ÙÙŠ Ø§Ù„ÙØ±Ø¹ Ø±Ù‚Ù… ' . $locationId);
            flash('ØªÙ… ØªØµÙÙŠØ± Ø§Ù„Ø±ØµÙŠØ¯ Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('branch_inventory&location_id=' . $locationId);
        } elseif ($action === 'edit_balance') {
            // ØªØ¹Ø¯ÙŠÙ„ Ø±ØµÙŠØ¯ Ù…Ù†ØªØ¬ Ù…Ø¨Ø§Ø´Ø±Ø©
            $productId   = (int) post_string('product_id');
            $newQuantity = (float) post_string('new_quantity');
            $db = pdo();
            $stmt = $db->prepare('UPDATE inventory_balances SET quantity = ? WHERE product_id = ? AND location_id = ?');
            $stmt->execute([$newQuantity, $productId, $locationId]);
            log_audit((int)$user['id'], 'update', 'inventory_balance', $productId, 'ØªØ¹Ø¯ÙŠÙ„ Ø±ØµÙŠØ¯ Ù…Ø¨Ø§Ø´Ø± Ù„Ù„Ù…Ù†ØªØ¬ ÙÙŠ Ø§Ù„ÙØ±Ø¹: ' . $newQuantity);
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ÙƒÙ…ÙŠØ© Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('branch_inventory&location_id=' . $locationId);
        } elseif ($action === 'update') {
            update_inventory_addition($_POST, (int) $user['id']);
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø¨Ù†Ø¬Ø§Ø­.');
        } else {
            create_inventory_addition($_POST, (int) $user['id']);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø¥Ø¶Ø§ÙØ© Ù…Ø®Ø²ÙˆÙ† Ø¬Ø¯ÙŠØ¯Ø© Ø¨Ù†Ø¬Ø§Ø­.');
        }
        redirect('inventory');
    }

    if ($route === 'inventory_add' && has_permission('inventory_view')) {
        if (!has_permission('inventory_adjust')) {
            throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ³ÙˆÙŠØ© Ø§Ù„Ù…Ø®Ø²ÙˆÙ†.');
        }
        $locationId = (int) post_string('location_id');
        require_location_access($locationId);
        require_location_type($locationId, ['warehouse', 'branch'], 'Ø§Ù„Ø£ÙˆÙ†Ù„Ø§ÙŠÙ† Ù„ÙŠØ³ Ù…Ø®Ø²Ù†Ø§Ù‹ ÙˆÙ„Ø§ ÙŠÙ…ÙƒÙ† ØªØ¹Ø¯ÙŠÙ„ Ø±ØµÙŠØ¯Ù‡.');
        create_inventory_additions($_POST, (int) $user['id']);
        flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø¥Ø¶Ø§ÙØ§Øª Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø¨Ù†Ø¬Ø§Ø­.');
        redirect('inventory');
    }

    if ($route === 'transfers_supply' && has_permission('transfers')) {
        if (post_string('action') === 'receive') {
            receive_transfer((int) post_string('transfer_id'), (int) $user['id'], ['warehouse'], ['branch']);
            flash('ØªÙ… Ø§Ø³ØªÙ„Ø§Ù… Ø§Ù„ØªÙˆØ±ÙŠØ¯ ÙˆØ¥Ø¶Ø§ÙØ© Ø§Ù„ÙƒÙ…ÙŠØ© Ù„Ù„ÙØ±Ø¹ Ø§Ù„Ù…Ø³ØªÙ„Ù….');
        } elseif (post_string('action') === 'cancel') {
            cancel_transfer((int) post_string('transfer_id'), (int) $user['id'], ['warehouse'], ['branch']);
            flash('ØªÙ… Ø¥Ù„ØºØ§Ø¡ Ø£Ù…Ø± Ø§Ù„ØªÙˆØ±ÙŠØ¯ ÙˆØ¥Ø¹Ø§Ø¯Ø© Ø§Ù„ÙƒÙ…ÙŠØ© Ù„Ù„Ù…Ø®Ø²Ù†.');
        } elseif (post_string('action') === 'update') {
            update_supply_transfer($_POST, (int) $user['id']);
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø£Ù…Ø± Ø§Ù„ØªÙˆØ±ÙŠØ¯ Ø¨Ù†Ø¬Ø§Ø­.');
        } else {
            create_supply_transfer($_POST, (int) $user['id']);
            flash('ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ø£Ù…Ø± Ø§Ù„ØªÙˆØ±ÙŠØ¯ ÙˆØ®ØµÙ… Ø§Ù„ÙƒÙ…ÙŠØ© Ù…Ù† Ø§Ù„Ù…Ø®Ø²Ù†.');
        }
        redirect('transfers_supply');
    }

    if ($route === 'transfers_branch' && has_permission('transfers')) {
        if (post_string('action') === 'receive') {
            receive_transfer((int) post_string('transfer_id'), (int) $user['id'], ['branch'], ['branch']);
            flash('ØªÙ… Ø§Ø³ØªÙ„Ø§Ù… Ø§Ù„ØªØ­ÙˆÙŠÙ„ ÙˆØ¥Ø¶Ø§ÙØ© Ø§Ù„ÙƒÙ…ÙŠØ© Ù„Ù„ÙØ±Ø¹ Ø§Ù„Ù…Ø³ØªÙ„Ù….');
        } elseif (post_string('action') === 'cancel') {
            cancel_transfer((int) post_string('transfer_id'), (int) $user['id'], ['branch'], ['branch']);
            flash('ØªÙ… Ø¥Ù„ØºØ§Ø¡ Ø§Ù„ØªØ­ÙˆÙŠÙ„ ÙˆØ¥Ø¹Ø§Ø¯Ø© Ø§Ù„ÙƒÙ…ÙŠØ© Ù„Ù„ÙØ±Ø¹ Ø§Ù„Ù…Ø±Ø³Ù„.');
        } elseif (post_string('action') === 'update') {
            update_branch_transfer($_POST, (int) $user['id']);
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø£Ù…Ø± Ø§Ù„ØªØ­ÙˆÙŠÙ„ Ø¨Ù†Ø¬Ø§Ø­.');
        } else {
            create_branch_transfer($_POST, (int) $user['id']);
            flash('ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ø£Ù…Ø± Ø§Ù„ØªØ­ÙˆÙŠÙ„ ÙˆØ®ØµÙ… Ø§Ù„ÙƒÙ…ÙŠØ© Ù…Ù† Ø§Ù„ÙØ±Ø¹ Ø§Ù„Ù…Ø±Ø³Ù„.');
        }
        redirect('transfers_branch');
    }

    if ($route === 'returns' && has_permission('invoices')) {
        $action = post_string('action', 'create');

        if ($action === 'update_paid') {
            $returnId = (int) post_string('return_id');
            $newPaid  = post_float('refund_paid');
            if (!$returnId) throw new RuntimeException('Ù…Ø¹Ø±Ù Ø§Ù„Ù…Ø±ØªØ¬Ø¹ Ù…Ø·Ù„ÙˆØ¨.');
            update_return_paid($returnId, $newPaid, (int) $user['id']);
            flash('ØªÙ… ØªØ­Ø¯ÙŠØ« Ù…Ø¨Ù„Øº Ø§Ù„Ø±Ø¯ Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('returns');
        }

        $returnType = post_string('return_type');
        $refundPaid = post_float('refund_paid', -1);
        if ($returnType === 'lines') {
            // New flow: selected line checkboxes from one invoice
            $invoiceId = (int) post_string('invoice_id');
            $lineIds   = array_map('intval', (array) ($_POST['line_ids'] ?? []));
            $method    = post_string('refund_method', 'cash');
            $reason    = post_string('reason');
            if (!$invoiceId || !$reason) {
                throw new RuntimeException('Ø§Ù„ÙØ§ØªÙˆØ±Ø© ÙˆØ§Ù„Ø³Ø¨Ø¨ Ù…Ø·Ù„ÙˆØ¨Ø§Ù†.');
            }
            $returnedQuantities = $_POST['returned_quantities'] ?? [];
            create_return_selected_lines($invoiceId, $lineIds, $returnedQuantities, $method, $reason, (int) $user['id'], $refundPaid);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù…Ø±ØªØ¬Ø¹ Ø¨Ù†Ø¬Ø§Ø­.');
        } elseif ($returnType === 'invoice') {
            $invoiceId = (int) post_string('invoice_id');
            $method    = post_string('refund_method', 'cash');
            $reason    = post_string('reason');
            if (!$invoiceId || !$reason) {
                throw new RuntimeException('Ø§Ù„ÙØ§ØªÙˆØ±Ø© ÙˆØ§Ù„Ø³Ø¨Ø¨ Ù…Ø·Ù„ÙˆØ¨Ø§Ù†.');
            }
            create_return_invoice($invoiceId, $method, $reason, (int) $user['id'], $refundPaid);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ù…Ø±ØªØ¬Ø¹ Ø§Ù„ÙØ§ØªÙˆØ±Ø© Ø¨Ù†Ø¬Ø§Ø­.');
        } elseif ($returnType === 'line') {
            $lineId = (int) post_string('line_id');
            $method = post_string('refund_method', 'cash');
            $reason = post_string('reason');
            if (!$lineId || !$reason) {
                throw new RuntimeException('Ø¨Ù†Ø¯ Ø§Ù„ÙØ§ØªÙˆØ±Ø© ÙˆØ§Ù„Ø³Ø¨Ø¨ Ù…Ø·Ù„ÙˆØ¨Ø§Ù†.');
            }
            create_return_line_invoice($lineId, $method, $reason, (int) $user['id']);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ù…Ø±ØªØ¬Ø¹ Ø§Ù„Ø¨Ù†Ø¯ Ø¨Ù†Ø¬Ø§Ø­.');
        } else {
            throw new RuntimeException('Ù†ÙˆØ¹ Ø§Ù„Ù…Ø±ØªØ¬Ø¹ ØºÙŠØ± Ù…Ø¹Ø±ÙˆÙ.');
        }
        redirect('returns');
    }

    if ($route === 'waste' && has_permission('inventory_view')) {
        if (!has_permission('inventory_adjust')) {
            throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ³Ø¬ÙŠÙ„ Ù‡Ø§Ù„Ùƒ.');
        }
        $locationId = (int) post_string('location_id');
        require_location_access($locationId);
        require_location_type($locationId, ['warehouse', 'branch'], 'Ø§Ù„Ø£ÙˆÙ†Ù„Ø§ÙŠÙ† Ù„ÙŠØ³ Ù…Ø®Ø²Ù†Ø§Ù‹ ÙˆÙ„Ø§ ÙŠÙ…ÙƒÙ† ØªØ³Ø¬ÙŠÙ„ Ù‡Ø§Ù„Ùƒ Ø¹Ù„ÙŠÙ‡.');
        add_wasted_product([
            'location_id' => $locationId,
            'product_id' => (int) post_string('product_id'),
            'quantity' => post_float('quantity'),
            'reason' => post_string('reason'),
        ], (int) $user['id']);
        flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù‡Ø§Ù„Ùƒ ÙˆØ®ØµÙ…Ù‡ Ù…Ù† Ù…Ø®Ø²ÙˆÙ† Ø§Ù„Ù…ÙˆÙ‚Ø¹ Ø¨Ù†Ø¬Ø§Ø­.');
        redirect('waste');
    }

    if ($route === 'customers_debts' && has_permission('customers_view')) {
        $action = post_string('action');
        if ($action === 'pay_debt') {
            if (!has_permission('customers_pay_debt')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ³Ø¬ÙŠÙ„ Ø³Ø¯Ø§Ø¯ Ø§Ù„Ø¯ÙŠÙˆÙ†.');
            }
            add_customer_payment((int) post_string('debt_id'), post_float('amount'), post_string('method', 'cash'), (int) $user['id']);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø¯ÙØ¹Ø© Ø§Ù„Ø¯ÙŠÙ† Ø¨Ù†Ø¬Ø§Ø­.');
        } elseif ($action === 'add_direct_debt') {
            $customerId = (int) post_string('customer_id');
            $amount = post_float('amount');
            $notes = post_string('notes');
            $locationId = (int) post_string('location_id') ?: current_user_location_id();
            add_direct_customer_debt($customerId, $amount, $notes, $locationId, (int) $user['id']);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø¯ÙŠÙ† Ø§Ù„Ù…Ø¨Ø§Ø´Ø± Ø¹Ù„Ù‰ Ø§Ù„Ø¹Ù…ÙŠÙ„ Ø¨Ù†Ø¬Ø§Ø­.');
        } else {
            throw new RuntimeException('Ø·Ù„Ø¨ ØºÙŠØ± ØµØ§Ù„Ø­ Ù„ØµÙØ­Ø© Ø§Ù„Ø¯ÙŠÙˆÙ† Ø§Ù„Ù…ÙØªÙˆØ­Ø©.');
        }
        $query = $_GET;
        unset($query['r']);
        redirect('customers_debts' . ($query ? '&' . http_build_query($query) : ''));
    }

    if ($route === 'customers' && has_permission('customers_view')) {
        if (post_string('action') === 'pay_debt') {
            if (!has_permission('customers_pay_debt')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ³Ø¬ÙŠÙ„ Ø³Ø¯Ø§Ø¯ Ø§Ù„Ø¯ÙŠÙˆÙ†.');
            }
            add_customer_payment((int) post_string('debt_id'), post_float('amount'), post_string('method', 'cash'), (int) $user['id']);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø¯ÙØ¹Ø© Ø§Ù„Ø¯ÙŠÙ†.');
        } elseif (post_string('action') === 'add_direct_debt') {
            $customerId = (int) post_string('customer_id');
            $amount = post_float('amount');
            $notes = post_string('notes');
            $locationId = (int) post_string('location_id') ?: current_user_location_id();
            add_direct_customer_debt($customerId, $amount, $notes, $locationId, (int) $user['id']);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø¯ÙŠÙ† Ø§Ù„Ù…Ø¨Ø§Ø´Ø± Ø¹Ù„Ù‰ Ø§Ù„Ø¹Ù…ÙŠÙ„ Ø¨Ù†Ø¬Ø§Ø­.');
            $redirectTo = post_string('redirect_to');
            if ($redirectTo === 'customer_view') {
                redirect('customer_view&id=' . $customerId);
            }
            redirect('customers');
        } elseif (post_string('action') === 'update') {
            if (!has_permission('customers_edit')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ¹Ø¯ÙŠÙ„ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡.');
            }
            update_customer($_POST);
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¹Ù…ÙŠÙ„.');
        } elseif (post_string('action') === 'delete') {
            if (!has_permission('customers_edit')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø­Ø°Ù Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡.');
            }
            $result = delete_customer((int) post_string('id'));
            if ($result === 'permanently_deleted') {
                flash('ØªÙ… Ø­Ø°Ù Ø§Ù„Ø¹Ù…ÙŠÙ„ Ù†Ù‡Ø§Ø¦ÙŠØ§Ù‹ Ø¨Ù†Ø¬Ø§Ø­.');
            } else {
                flash('ØªÙ… Ø¥Ø®ÙØ§Ø¡ Ø§Ù„Ø¹Ù…ÙŠÙ„ ÙˆØªØ¹Ø·ÙŠÙ„Ù‡ Ù„ÙˆØ¬ÙˆØ¯ ÙÙˆØ§ØªÙŠØ± Ø£Ùˆ Ø­Ø±ÙƒØ§Øª Ù…Ø±ØªØ¨Ø·Ø© Ø¨Ù‡.');
            }
        } else {
            if (!has_permission('customers_add')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø¥Ø¶Ø§ÙØ© Ø¹Ù…Ù„Ø§Ø¡.');
            }
            add_customer([
                'name' => post_string('name'),
                'phone' => post_string('phone'),
                'source' => post_string('source', 'offline'),
                'notes' => post_string('notes'),
                'location_id' => current_user_location_id(),
                'created_by' => (int) $user['id'],
            ]);
            flash('ØªÙ… Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ø¹Ù…ÙŠÙ„.');
        }
        redirect('customers');
    }

    if ($route === 'customer_birthdays' && has_permission('customers_view')) {
        $action = post_string('action');
        if ($action === 'save_settings') {
            save_setting('whatsapp_birthday_enabled', post_string('whatsapp_birthday_enabled') === '1' ? '1' : '0');
            save_setting('whatsapp_birthday_send_time', post_string('whatsapp_birthday_send_time', '12:00'));
            save_setting('whatsapp_birthday_message', post_string('whatsapp_birthday_message'));
            flash('ØªÙ… Ø­ÙØ¸ Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ø£Ø¹ÙŠØ§Ø¯ Ø§Ù„Ù…ÙŠÙ„Ø§Ø¯ ÙˆÙ…ÙˆØ¹Ø¯ Ø§Ù„Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„ÙŠÙˆÙ…ÙŠ Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('customer_birthdays&tab=settings');
        } elseif ($action === 'send_single') {
            $customerId = (int) post_string('customer_id');
            $customMsg = post_string('custom_message');
            $res = send_single_customer_birthday_whatsapp($customerId, $customMsg ?: null);
            if ($res['success']) {
                flash($res['message']);
            } else {
                flash($res['error'], 'error');
            }
            redirect('customer_birthdays' . (!empty($_GET['tab']) ? '&tab=' . urlencode($_GET['tab']) : ''));
        } elseif ($action === 'send_all_today') {
            $force = post_string('force_resend') === '1';
            $res = process_daily_birthday_whatsapp($force);
            $msg = sprintf(
                'ØªÙ… Ø§Ù„Ø¥Ø±Ø³Ø§Ù„ Ù„Ù€ %d Ø¹Ù…ÙŠÙ„ | ØªÙ… ØªØ®Ø·ÙŠ %d | ÙØ´Ù„ %d (Ø¥Ø¬Ù…Ø§Ù„ÙŠ Ø§Ù„ÙŠÙˆÙ…: %d)',
                $res['sent_count'],
                $res['skipped_count'],
                $res['failed_count'],
                $res['total_today']
            );
            if ($res['sent_count'] > 0) {
                flash('ØªÙ… Ø¥Ø±Ø³Ø§Ù„ ØªÙ‡Ø§Ù†ÙŠ Ø£Ø¹ÙŠØ§Ø¯ Ø§Ù„Ù…ÙŠÙ„Ø§Ø¯ Ø¨Ù†Ø¬Ø§Ø­! ' . $msg);
            } elseif ($res['skipped_count'] > 0 && $res['total_today'] > 0) {
                flash('ØªÙ… Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„ØªÙ‡Ù†Ø¦Ø© Ù„Ø¬Ù…ÙŠØ¹ Ø¹Ù…Ù„Ø§Ø¡ Ø§Ù„ÙŠÙˆÙ… Ù…Ø³Ø¨Ù‚Ø§Ù‹! ' . $msg, 'warning');
            } else {
                flash($msg, $res['failed_count'] > 0 ? 'error' : 'warning');
            }
            redirect('customer_birthdays');
        }
        redirect('customer_birthdays');
    }

    if ($route === 'customer_surveys' && has_permission('customers_view')) {
        $action = post_string('action');
        if ($action === 'save_settings') {
            save_setting('whatsapp_survey_enabled', post_string('whatsapp_survey_enabled') === '1' ? '1' : '0');
            save_setting('whatsapp_survey_delay_minutes', (string) max(1, (int)post_string('whatsapp_survey_delay_minutes', '10')));
            save_setting('whatsapp_survey_message', post_string('whatsapp_survey_message'));
            flash('ØªÙ… Ø­ÙØ¸ Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ø§Ø³ØªØ¨ÙŠØ§Ù† Ø±Ø¶Ø§ Ø§Ù„Ø¹Ù…Ù„Ø§Ø¡ ÙˆÙˆÙ‚Øª Ø§Ù„ØªØ£Ø®ÙŠØ± Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('customer_surveys&tab=settings');
        } elseif ($action === 'send_now') {
            $queueId = (int) post_string('queue_id');
            $res = send_single_queued_survey($queueId);
            if ($res['success']) {
                flash($res['message']);
            } else {
                flash($res['error'], 'error');
            }
            redirect('customer_surveys' . (!empty($_GET['tab']) ? '&tab=' . urlencode($_GET['tab']) : ''));
        } elseif ($action === 'process_queue_now') {
            $res = process_survey_queue(50);
            flash("ØªÙ…Øª Ù…Ø¹Ø§Ù„Ø¬Ø© Ø§Ù„Ø·Ø§Ø¨ÙˆØ± Ø¨Ù†Ø¬Ø§Ø­: ØªÙ… Ø¥Ø±Ø³Ø§Ù„ {$res['sent']} Ø±Ø³Ø§Ù„Ø© Ø§Ø³ØªØ¨ÙŠØ§Ù† (ÙØ´Ù„ {$res['failed']}).");
            redirect('customer_surveys');
        } elseif ($action === 'cancel') {
            $queueId = (int) post_string('queue_id');
            $db = pdo();
            $stmt = $db->prepare("UPDATE customer_survey_queue SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$queueId]);
            flash('ØªÙ… Ø¥Ù„ØºØ§Ø¡ Ø¥Ø±Ø³Ø§Ù„ Ø±Ø³Ø§Ù„Ø© Ø§Ù„Ø§Ø³ØªØ¨ÙŠØ§Ù† Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.');
            redirect('customer_surveys&tab=queue');
        }
        redirect('customer_surveys');
    }

    if ($route === 'pos' && has_permission('pos')) {
        $invoiceId = create_invoice($_POST, $user);
        // Ø¥Ø±Ø³Ø§Ù„ Ø§Ù„ÙØ§ØªÙˆØ±Ø© ØªÙ„Ù‚Ø§Ø¦ÙŠØ§Ù‹ Ù„Ù„Ø¹Ù…ÙŠÙ„ Ø¹Ø¨Ø± Ø§Ù„ÙˆØ§ØªØ³Ø§Ø¨
        try {
            send_invoice_whatsapp($invoiceId);
        } catch (Throwable $waErr) {}
        // Ø¬Ø¯ÙˆÙ„Ø© Ø±Ø³Ø§Ù„Ø© Ø§Ø³ØªØ¨ÙŠØ§Ù† Ø±Ø¶Ø§ Ø§Ù„Ø¹Ù…ÙŠÙ„ Ø¨Ø¹Ø¯ ÙˆÙ‚Øª Ù…Ø­Ø¯Ø¯
        try {
            schedule_invoice_satisfaction_survey($invoiceId);
        } catch (Throwable $surveyErr) {}
        flash('ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ø§Ù„ÙØ§ØªÙˆØ±Ø© Ø±Ù‚Ù… #' . $invoiceId . ' ÙˆØ®ØµÙ… Ø§Ù„Ù…Ø®Ø²ÙˆÙ†.');
        // Redirect back to POS and request the client to open the printable invoice in a new window
        // also instruct client to clear the POS cart
        redirect('pos&print_invoice=' . $invoiceId . '&clear_cart=1');
    }

    if ($route === 'shifts' && has_permission('shifts')) {
        $action = post_string('action');
        if ($action === 'delete') {
            delete_shift_closure((int) post_string('id'));
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„Ø´ÙŠÙØª Ø¨Ù†Ø¬Ø§Ø­.');
        } elseif ($action === 'update') {
            update_shift_closure((int) post_string('id'), post_float('actual_cash'), post_string('notes'));
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø´ÙŠÙØª Ø¨Ù†Ø¬Ø§Ø­.');
        } else {
            $locationId = (int) post_string('location_id');
            $actualCash = post_float('actual_cash');
            $notes = post_string('notes');
            $cashAction = post_string('cash_transfer_action', 'none');
            $cashAmount = post_float('cash_transferred_amount', 0);
            
            close_shift_with_details($locationId, $actualCash, $notes, $user, $cashAction, $cashAmount);
            flash('âœ… ØªÙ… Ø¥ØºÙ„Ø§Ù‚ Ø§Ù„Ø´ÙŠÙØª ÙˆØªØ³Ø¬ÙŠÙ„ Ø§Ù„ÙƒØ§Ø´ Ø¨Ù†Ø¬Ø§Ø­.');
        }
        redirect('shifts');
    }

    if ($route === 'invoices' && has_permission('invoices_notes')) {
        if (post_string('action') === 'update_notes') {
            $invoiceId = (int) post_string('invoice_id');
            $notes = post_string('notes');
            $db = pdo();
            $stmt = $db->prepare('UPDATE invoices SET notes = ? WHERE id = ?');
            $stmt->execute([$notes, $invoiceId]);
            flash('ØªÙ… ØªØ­Ø¯ÙŠØ« Ù…Ù„Ø§Ø­Ø¸Ø§Øª Ø§Ù„ÙØ§ØªÙˆØ±Ø©.');
            redirect('invoices');
        }
        if (post_string('action') === 'delete_invoice') {
            if (!has_permission('manager')) {
                throw new RuntimeException('Ø­Ø°Ù Ø§Ù„ÙÙˆØ§ØªÙŠØ± Ù…ØªØ§Ø­ Ù„Ù„Ù…Ø¯ÙŠØ±ÙŠÙ† ÙÙ‚Ø·.');
            }
            $invoiceId = (int) post_string('invoice_id');
            delete_invoice_with_restore($invoiceId, (int) $user['id']);
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„ÙØ§ØªÙˆØ±Ø© ÙˆØ¥Ø±Ø¬Ø§Ø¹ Ø§Ù„Ù…Ø®Ø²ÙˆÙ† Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('invoices');
        }
    }

    if ($route === 'returns' && has_permission('returns')) {
        if (post_string('return_type') === 'line') {
            create_return_line_invoice((int) post_string('line_id'), post_string('refund_method', 'cash'), post_string('reason'), (int) $user['id']);
            flash('ØªÙ… ØªÙ†ÙÙŠØ° Ù…Ø±ØªØ¬Ø¹ Ø§Ù„Ø¨Ù†Ø¯ ÙˆØ¥Ø±Ø¬Ø§Ø¹ Ø§Ù„Ù…Ø®Ø²ÙˆÙ†.');
        } else {
            create_return_invoice((int) post_string('invoice_id'), post_string('refund_method', 'cash'), post_string('reason'), (int) $user['id']);
            flash('ØªÙ… ØªÙ†ÙÙŠØ° Ø§Ù„Ù…Ø±ØªØ¬Ø¹ ÙˆØ¥Ø±Ø¬Ø§Ø¹ Ø§Ù„Ù…Ø®Ø²ÙˆÙ†.');
        }
        redirect('returns');
    }

    if ($route === 'attendance') {
        if (post_string('action') === 'generate_qr') {
            if (!has_permission('attendance')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªÙˆÙ„ÙŠØ¯ Ø±Ù…Ø² QR.');
            }
            $locationId = (int) post_string('location_id');
            require_location_type($locationId, ['warehouse', 'branch'], 'Ø§Ù„Ø£ÙˆÙ†Ù„Ø§ÙŠÙ† Ù„Ø§ ÙŠØªÙ… ØªÙˆÙ„ÙŠØ¯ QR Ø­Ø¶ÙˆØ± Ù„Ù‡.');
            $token = 'LOC-' . $locationId . '-' . bin2hex(random_bytes(8));
            $db = pdo();
            $stmt = $db->prepare('UPDATE locations SET qr_code = ? WHERE id = ?');
            $stmt->execute([$token, $locationId]);
            flash('ØªÙ… ØªÙˆÙ„ÙŠØ¯ Ø±Ù…Ø² QR Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('attendance&tab=qrcodes');
        } elseif (post_string('action') === 'update_location_geo') {
            if (!has_permission('users_permissions')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ¹Ø¯ÙŠÙ„ Ù…ÙˆÙ‚Ø¹ Ø§Ù„ÙØ±Ø¹.');
            }
            $locationId = (int) post_string('location_id');
            require_location_type($locationId, ['warehouse', 'branch'], 'Ø§Ù„Ø£ÙˆÙ†Ù„Ø§ÙŠÙ† Ù„Ø§ ÙŠØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø­Ø¶ÙˆØ± Ø£Ùˆ Ø§Ù†ØµØ±Ø§Ù Ù„Ù‡.');
            $lat = post_float('latitude');
            $lng = post_float('longitude');
            $db = pdo();
            $stmt = $db->prepare('UPDATE locations SET latitude = ?, longitude = ? WHERE id = ?');
            $stmt->execute([$lat, $lng, $locationId]);
            flash('ØªÙ… ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø¥Ø­Ø¯Ø§Ø«ÙŠØ§Øª Ø§Ù„Ø¬ØºØ±Ø§ÙÙŠØ© Ù„Ù„Ù…ÙˆÙ‚Ø¹ Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('attendance&tab=qrcodes');
        } elseif (post_string('action') === 'qr_scan') {
            $token = post_string('qr_token');
            $scanAction = post_string('scan_action');
            $lat = post_string('latitude') !== '' ? post_float('latitude') : null;
            $lng = post_string('longitude') !== '' ? post_float('longitude') : null;
            
            $db = pdo();
            $stmt = $db->prepare('SELECT * FROM locations WHERE qr_code = ? AND is_active = 1');
            $stmt->execute([$token]);
            $loc = $stmt->fetch();
            if (!$loc) {
                flash('Ø±Ù…Ø² QR ØºÙŠØ± ØµØ§Ù„Ø­ Ø£Ùˆ Ø§Ù„Ù…ÙˆÙ‚Ø¹ ØºÙŠØ± Ù†Ø´Ø·.', 'danger');
                redirect('attendance');
            }
            if ($loc['type'] === 'online') {
                throw new RuntimeException('Ø§Ù„Ø£ÙˆÙ†Ù„Ø§ÙŠÙ† Ù„Ø§ ÙŠØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø­Ø¶ÙˆØ± Ø£Ùˆ Ø§Ù†ØµØ±Ø§Ù Ù„Ù‡.');
            }
            require_location_access((int) $loc['id']);

            // GPS Lock verification (must be within 10 meters)
            if ($loc['latitude'] === null || $loc['longitude'] === null) {
                throw new RuntimeException('Ø¥Ø­Ø¯Ø§Ø«ÙŠØ§Øª Ù‡Ø°Ø§ Ø§Ù„ÙØ±Ø¹ ØºÙŠØ± Ù…Ø³Ø¬Ù„Ø© Ø¨Ø§Ù„Ù†Ø¸Ø§Ù…. ÙŠØ±Ø¬Ù‰ Ù…Ø±Ø§Ø¬Ø¹Ø© Ø§Ù„Ø¥Ø¯Ø§Ø±Ø©.');
            }
            if ($lat === null || $lng === null) {
                throw new RuntimeException('ÙŠØ±Ø¬Ù‰ ØªÙØ¹ÙŠÙ„ Ø§Ù„Ù€ GPS ÙˆØ§Ù„Ø³Ù…Ø§Ø­ Ù„Ù„Ù…ØªØµÙØ­ Ø¨Ø§Ù„ÙˆØµÙˆÙ„ Ù„Ù…ÙˆÙ‚Ø¹Ùƒ Ø§Ù„Ø¬ØºØ±Ø§ÙÙŠ Ù„ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø­Ø¶ÙˆØ±.');
            }
            $distance = calculate_distance((float)$lat, (float)$lng, (float)$loc['latitude'], (float)$loc['longitude']);
            if ($distance > 20.0) {
                throw new RuntimeException('Ø£Ù†Øª Ø¨Ø¹ÙŠØ¯ Ø¬Ø¯Ø§Ù‹ Ø¹Ù† Ø§Ù„ÙØ±Ø¹. Ø§Ù„Ù…Ø³Ø§ÙØ© Ø§Ù„Ø­Ø§Ù„ÙŠØ©: ' . round($distance, 1) . ' Ù…ØªØ±. ÙŠØ¬Ø¨ Ø£Ù† ØªÙƒÙˆÙ† Ø¹Ù„Ù‰ Ø¨Ø¹Ø¯ 20 Ù…ØªØ±Ø§Ù‹ Ø¹Ù„Ù‰ Ø§Ù„Ø£ÙƒØ«Ø± Ù„ØªØ³Ø¬ÙŠÙ„ Ø­Ø¶ÙˆØ±/Ø§Ù†ØµØ±Ø§Ù.');
            }

            // Double scan / status check verification
            $expectedAction = get_next_attendance_action((int) $user['id']);
            if ($scanAction !== $expectedAction) {
                if ($scanAction === 'check_in') {
                    throw new RuntimeException('Ù„Ù‚Ø¯ Ù‚Ù…Øª Ø¨ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø­Ø¶ÙˆØ± Ø¨Ø§Ù„ÙØ¹Ù„.');
                } else {
                    throw new RuntimeException('ÙŠØ¬Ø¨ ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø­Ø¶ÙˆØ± Ø£ÙˆÙ„Ø§Ù‹ Ù‚Ø¨Ù„ ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø§Ù†ØµØ±Ø§Ù.');
                }
            }
            
            $stmt = $db->prepare('INSERT INTO attendance_records (user_id, location_id, action, source, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], $loc['id'], $scanAction, 'qr', $lat, $lng]);
            
            flash(($scanAction === 'check_in' ? 'ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø­Ø¶ÙˆØ±Ùƒ Ø¨Ù†Ø¬Ø§Ø­ ÙÙŠ ' : 'ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø§Ù†ØµØ±Ø§ÙÙƒ Ø¨Ù†Ø¬Ø§Ø­ Ù…Ù† ') . $loc['name']);
            redirect('attendance');
        } else {
            if (!has_permission('attendance')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø§Ù„ØªØ­ÙƒÙ… ÙÙŠ Ø§Ù„Ø­Ø¶ÙˆØ± ÙˆØ§Ù„Ø§Ù†ØµØ±Ø§Ù.');
            }
            $action = post_string('action');
            if ($action === 'delete_attendance') {
                delete_attendance((int) post_string('attendance_id'), (int) $user['id']);
                flash('ØªÙ… Ø­Ø°Ù Ø³Ø¬Ù„ Ø§Ù„Ø­Ø¶ÙˆØ± Ø¨Ù†Ø¬Ø§Ø­.');
            } elseif ($action === 'update_attendance') {
                update_attendance((int) post_string('attendance_id'), $_POST, (int) $user['id']);
                flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø³Ø¬Ù„ Ø§Ù„Ø­Ø¶ÙˆØ± Ø¨Ù†Ø¬Ø§Ø­.');
            } else {
                add_attendance($_POST, (int) $user['id']);
                flash('ØªÙ… Ø¥Ø¶Ø§ÙØ© Ø³Ø¬Ù„ Ø§Ù„Ø­Ø¶ÙˆØ± Ø§Ù„ÙŠØ¯ÙˆÙŠ Ø¨Ù†Ø¬Ø§Ø­.');
            }
            redirect('attendance_log');
        }
    }

    if ($route === 'targets' && has_permission('targets')) {
        $action = post_string('action', 'save_tiers');
        if ($action === 'save_tiers') {
            save_target_commission_tiers($_POST, (int) $user['id']);
            flash('ØªÙ… Ø­ÙØ¸ Ø´Ø±Ø§Ø¦Ø­ Ø¹Ù…ÙˆÙ„Ø§Øª Ø§Ù„ØªØ§Ø±Ø¬Øª.');
            redirect('targets');
        }
        if ($action === 'delete') {
            delete_target((int) post_string('id'));
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„ØªØ§Ø±Ø¬Øª Ø§Ù„ÙŠÙˆÙ…ÙŠ.');
        } elseif ($action === 'update') {
            $locationId = (int) post_string('location_id');
            update_target((int) post_string('id'), $locationId, post_string('target_date'), post_float('target_amount'), (int) $user['id']);
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØªØ§Ø±Ø¬Øª Ø§Ù„ÙŠÙˆÙ…ÙŠ.');
        } else {
            $locationId = (int) post_string('location_id');
            require_location_access($locationId);
            upsert_target($locationId, post_string('target_date'), post_float('target_amount'), (int) $user['id']);
            flash('ØªÙ… Ø­ÙØ¸ Ø§Ù„ØªØ§Ø±Ø¬Øª Ø§Ù„ÙŠÙˆÙ…ÙŠ.');
        }
        redirect('targets');
    }

    if ($route === 'expenses' && has_permission('expenses_view')) {
        if (!has_permission('expenses_add')) {
            throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ³Ø¬ÙŠÙ„ Ù…ØµØ§Ø±ÙŠÙ.');
        }
        $action = post_string('action', 'add');
        if ($action === 'edit') {
            edit_expense((int) $_POST['id'], $_POST, (int) $user['id']);
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…ØµØ±ÙˆÙ.');
        } elseif ($action === 'delete') {
            delete_expense((int) $_POST['id'], (int) $user['id']);
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„Ù…ØµØ±ÙˆÙ.');
        } else {
            add_expense($_POST, (int) $user['id']);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù…ØµØ±ÙˆÙ.');
        }
        redirect('expenses');
    }

    if ($route === 'branch_cash_transfers' && has_permission('branch_cash_transfers')) {
        $action = post_string('action', 'create');
        if ($action === 'receive') {
            if (!has_permission('manager_treasury') && current_user()['role_code'] !== 'admin') {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ£ÙƒÙŠØ¯ Ø§Ø³ØªÙ„Ø§Ù… Ø§Ù„ØªØ­ÙˆÙŠÙ„ØŒ Ù…ØªØ§Ø­ Ù„Ù…Ø¯ÙŠØ± Ø§Ù„Ù†Ø¸Ø§Ù… ÙÙ‚Ø·.');
            }
            receive_branch_cash_transfer((int) post_string('id'), (int) $user['id']);
            flash('ØªÙ… Ø§Ø³ØªÙ„Ø§Ù… ØªØ­ÙˆÙŠÙ„ Ø§Ù„Ø®Ø²ÙŠÙ†Ø©.');
        } elseif ($action === 'cancel') {
            cancel_branch_cash_transfer((int) post_string('id'), (int) $user['id']);
            flash('ØªÙ… Ø¥Ù„ØºØ§Ø¡ ØªØ­ÙˆÙŠÙ„ Ø§Ù„Ø®Ø²ÙŠÙ†Ø© ÙˆØ¥Ø±Ø¬Ø§Ø¹ Ø§Ù„Ø±ØµÙŠØ¯.');
        } else {
            if (!has_permission('expenses_add') && !has_permission('branch_cash_transfers')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø¥Ù†Ø´Ø§Ø¡ ØªØ­ÙˆÙŠÙ„ Ø®Ø²ÙŠÙ†Ø©.');
            }
            create_branch_cash_transfer($_POST, (int) $user['id']);
            flash('ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ ØªØ­ÙˆÙŠÙ„ Ø§Ù„Ø®Ø²ÙŠÙ†Ø©.');
        }
        redirect('branch_cash_transfers');
    }

    if ($route === 'manager_treasury' && has_permission('manager_treasury')) {
        $action = post_string('action', 'receive');
        if ($action === 'receive') {
            receive_manager_collection((int) post_string('id'), (int) $user['id']);
            flash('ØªÙ… ØªØ£ÙƒÙŠØ¯ Ø§Ø³ØªÙ„Ø§Ù… ØªØ­ØµÙŠÙ„ Ø®Ø²ÙŠÙ†Ø© Ø§Ù„Ù…Ø¯ÙŠØ±.');
        } elseif ($action === 'cancel') {
            cancel_manager_collection((int) post_string('id'), (int) $user['id']);
            flash('ØªÙ… Ø¥Ù„ØºØ§Ø¡ ØªØ­ØµÙŠÙ„ Ø®Ø²ÙŠÙ†Ø© Ø§Ù„Ù…Ø¯ÙŠØ±.');
        }
        redirect('manager_treasury');
    }

    if ($route === 'suppliers' && has_permission('suppliers_view')) {
        if (!has_permission('suppliers_add')) {
            throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ³Ø¬ÙŠÙ„ Ù…ÙˆØ±Ø¯ÙŠÙ†.');
        }
        add_supplier($_POST, (int) $user['id']);
        flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ù…ÙˆØ±Ø¯.');
        redirect('suppliers');
    }

    if ($route === 'users' && has_permission('users_view')) {
        if (post_string('action') === 'save_permissions') {
            if (!has_permission('users_permissions')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª.');
            }
            $db = pdo();
            $db->beginTransaction();
            try {
                $db->exec('DELETE FROM role_permissions');
                $posted_perms = $_POST['perms'] ?? [];
                $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_code) VALUES (?, ?)');
                foreach ($posted_perms as $role_id => $perms) {
                    foreach (array_keys($perms) as $perm_code) {
                        $stmt->execute([(int) $role_id, $perm_code]);
                    }
                }
                $db->commit();
                unset($_SESSION['permissions']);
                flash('ØªÙ… Ø­ÙØ¸ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª Ø¨Ù†Ø¬Ø§Ø­.');
                redirect('users&tab=permissions');
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
        } elseif (post_string('action') === 'add_role') {
            if (!has_permission('users_permissions')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø¥Ø¶Ø§ÙØ© Ø£Ø¯ÙˆØ§Ø± Ø¬Ø¯ÙŠØ¯Ø©.');
            }
            $name = post_string('name');
            $code = preg_replace('/[^a-z0-9_]/', '', strtolower(post_string('code')));
            if ($name && $code) {
                $db = pdo();
                $db->beginTransaction();
                try {
                    $stmt = $db->prepare('INSERT IGNORE INTO roles (code, name) VALUES (?, ?)');
                    $stmt->execute([$code, $name]);
                    $role_id = (int) $db->lastInsertId();
                    
                    if ($role_id > 0) {
                        $selected_perms = $_POST['new_role_perms'] ?? [];
                        $stmt_perm = $db->prepare('INSERT INTO role_permissions (role_id, permission_code) VALUES (?, ?)');
                        foreach (array_keys($selected_perms) as $perm_code) {
                            $stmt_perm->execute([$role_id, $perm_code]);
                        }
                        $db->commit();
                        unset($_SESSION['permissions']);
                        flash('ØªÙ… Ø¥Ø¶Ø§ÙØ© Ø§Ù„Ø¯ÙˆØ± Ø§Ù„Ø¬Ø¯ÙŠØ¯ Ø¨Ù†Ø¬Ø§Ø­ Ù…Ø¹ Ø§Ù„ØµÙ„Ø§Ø­ÙŠØ§Øª Ø§Ù„Ù…Ø­Ø¯Ø¯Ø©.');
                    } else {
                        $db->rollBack();
                        flash('ÙƒÙˆØ¯ Ø§Ù„Ø¯ÙˆØ± Ù…Ø³Ø¬Ù„ Ù…Ø³Ø¨Ù‚Ø§Ù‹ØŒ ÙŠØ±Ø¬Ù‰ Ø§Ø®ØªÙŠØ§Ø± ÙƒÙˆØ¯ Ø¢Ø®Ø±.', 'danger');
                    }
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }
            } else {
                flash('Ø§Ø³Ù… Ø§Ù„Ø¯ÙˆØ± Ø£Ùˆ Ø§Ù„ÙƒÙˆØ¯ ØºÙŠØ± ØµØ§Ù„Ø­.', 'danger');
            }
            redirect('users&tab=permissions');
        } elseif (post_string('action') === 'delete_role') {
            if (!has_permission('users_permissions')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø­Ø°Ù Ø§Ù„Ø£Ø¯ÙˆØ§Ø±.');
            }
            $role_id = (int) post_string('role_id');
            $db = pdo();
            
            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
            $stmt->execute([$role_id]);
            $count = (int) $stmt->fetchColumn();
            
            if ($count > 0) {
                flash('Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø­Ø°Ù Ù‡Ø°Ø§ Ø§Ù„Ø¯ÙˆØ± Ù„ÙˆØ¬ÙˆØ¯ Ù…ÙˆØ¸ÙÙŠÙ† Ù…Ø³Ø¬Ù„ÙŠÙ† Ø¨Ù‡ Ø­Ø§Ù„ÙŠØ§Ù‹.', 'danger');
            } else {
                $db->beginTransaction();
                try {
                    $db->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$role_id]);
                    $db->prepare('DELETE FROM roles WHERE id = ?')->execute([$role_id]);
                    $db->commit();
                    unset($_SESSION['permissions']);
                    flash('ØªÙ… Ø­Ø°Ù Ø§Ù„Ø¯ÙˆØ± Ø¨Ù†Ø¬Ø§Ø­.');
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
            redirect('users&tab=permissions');
        } elseif (post_string('action') === 'deactivate') {
            if (!has_permission('users_add')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ¹Ø·ÙŠÙ„ Ø§Ù„Ù…ÙˆØ¸ÙÙŠÙ†.');
            }
            $id = (int) post_string('id');
            if ($id === (int) $user['id']) {
                throw new RuntimeException('Ù„Ø§ ÙŠÙ…ÙƒÙ† ØªØ¹Ø·ÙŠÙ„ Ø­Ø³Ø§Ø¨Ùƒ Ø§Ù„Ø­Ø§Ù„ÙŠ.');
            }
            deactivate_user($id);
            flash('ØªÙ… ØªØ¹Ø·ÙŠÙ„ Ø§Ù„Ù…ÙˆØ¸Ù.');
        } elseif (post_string('action') === 'delete') {
            if (!has_permission('users_add')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø­Ø°Ù Ø§Ù„Ù…ÙˆØ¸ÙÙŠÙ†.');
            }
            $id = (int) post_string('id');
            if ($id === (int) $user['id']) {
                throw new RuntimeException('Ù„Ø§ ÙŠÙ…ÙƒÙ† Ø­Ø°Ù Ø­Ø³Ø§Ø¨Ùƒ Ø§Ù„Ø­Ø§Ù„ÙŠ Ù†Ù‡Ø§Ø¦ÙŠØ§Ù‹.');
            }
            delete_user_permanently($id);
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„Ù…ÙˆØ¸Ù Ù†Ù‡Ø§Ø¦ÙŠØ§Ù‹.');
        } elseif (post_string('action') === 'update') {
            if (!has_permission('users_add')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ù…ÙˆØ¸ÙÙŠÙ†.');
            }
            update_user($_POST);
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ù…ÙˆØ¸Ù.');
        } else {
            if (!has_permission('users_add')) {
                throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø¥Ø¶Ø§ÙØ© Ù…ÙˆØ¸ÙÙŠÙ†.');
            }
            add_user($_POST);
            flash('ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ø­Ø³Ø§Ø¨ Ø§Ù„Ù…ÙˆØ¸Ù.');
        }
    }

    if ($route === 'payroll' && has_permission('users_view')) {
        $action = post_string('action');
        $month = post_string('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        if (in_array($action, ['update_rates', 'add_adjustment', 'delete_adjustment', 'save_override', 'pay_salary'], true) && !has_permission('users_permissions')) {
            throw new RuntimeException('ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨ØªØ¹Ø¯ÙŠÙ„ Ø§Ù„Ø±ÙˆØ§ØªØ¨.');
        }

        if ($action === 'add_adjustment') {
            add_payroll_adjustment($_POST, (int) $user['id']);
            flash('ØªÙ… ØªØ³Ø¬ÙŠÙ„ Ø§Ù„Ø­Ø§ÙØ² Ø£Ùˆ Ø§Ù„Ø®ØµÙ….');
            redirect('payroll&month=' . $month);
        }

        if ($action === 'delete_adjustment') {
            delete_payroll_adjustment((int) post_string('id'), (int) $user['id']);
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„Ø­Ø§ÙØ² Ø£Ùˆ Ø§Ù„Ø®ØµÙ….');
            redirect('payroll&month=' . $month);
        }

        if ($action === 'save_override') {
            save_payroll_override($_POST, (int) $user['id']);
            flash('ØªÙ… Ø­ÙØ¸ Ø¥Ø¬Ù…Ø§Ù„ÙŠØ§Øª Ø§Ù„Ø±Ø§ØªØ¨ Ø§Ù„Ù†Ù‡Ø§Ø¦ÙŠØ©.');
            redirect('payroll&month=' . $month);
        }

        if ($action === 'update_rates') {
            $userId = (int) post_string('user_id');
            $salary = post_float('basic_salary');
            $comm = post_float('commission_percent');
            $db = pdo();
            $stmt = $db->prepare('UPDATE users SET basic_salary = ?, commission_percent = ? WHERE id = ?');
            $stmt->execute([$salary, $comm, $userId]);
            flash('ØªÙ… ØªØ­Ø¯ÙŠØ« Ø§Ù„Ø±Ø§ØªØ¨ Ø§Ù„Ø£Ø³Ø§Ø³ÙŠ.');
            redirect('payroll&month=' . $month);
        }

        if ($action === 'pay_salary') {
            pay_salary($_POST, (int) $user['id']);
            flash('âœ… ØªÙ… ØªØ³Ø¬ÙŠÙ„ ØµØ±Ù Ø§Ù„Ø±Ø§ØªØ¨ Ø¨Ù†Ø¬Ø§Ø­.');
            redirect('payroll&month=' . $month);
        }
    }

    if ($route === 'online_orders' && has_permission('online_orders')) {
        if (post_string('action') === 'status') {
            update_online_order_status((int) post_string('order_id'), post_string('status'), (int) $user['id']);
            flash('ØªÙ… ØªØ­Ø¯ÙŠØ« Ø­Ø§Ù„Ø© Ø§Ù„Ø·Ù„Ø¨.');
        } elseif (post_string('action') === 'delete') {
            delete_online_order((int) post_string('order_id'), (int) $user['id']);
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„Ø·Ù„Ø¨ Ø§Ù„Ø£ÙˆÙ†Ù„Ø§ÙŠÙ†.');
        } elseif (post_string('action') === 'update') {
            update_online_order((int) post_string('order_id'), $_POST, (int) $user['id']);
            flash('ØªÙ… ØªØ­Ø¯ÙŠØ« Ø·Ù„Ø¨ Ø§Ù„Ø£ÙˆÙ†Ù„Ø§ÙŠÙ†.');
        } else {
            create_online_order($_POST, (int) $user['id']);
            flash('ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ø·Ù„Ø¨ Ø£ÙˆÙ†Ù„Ø§ÙŠÙ†.');
        }
        redirect('online_orders');
    }

    if ($route === 'locations' && has_permission('settings')) {
        $action = post_string('action');
        if ($action === 'update') {
            update_location_data($_POST, (int) $user['id']);
            flash('ØªÙ… ØªØ¹Ø¯ÙŠÙ„ Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„ÙØ±Ø¹/Ø§Ù„Ù…ÙˆÙ‚Ø¹.');
        } elseif ($action === 'deactivate') {
            set_location_active((int) post_string('id'), false, (int) $user['id']);
            flash('ØªÙ… ØªØ¹Ø·ÙŠÙ„ Ø§Ù„ÙØ±Ø¹/Ø§Ù„Ù…ÙˆÙ‚Ø¹.');
        } elseif ($action === 'activate') {
            set_location_active((int) post_string('id'), true, (int) $user['id']);
            flash('ØªÙ… ØªÙØ¹ÙŠÙ„ Ø§Ù„ÙØ±Ø¹/Ø§Ù„Ù…ÙˆÙ‚Ø¹.');
        } else {
            add_location($_POST, (int) $user['id']);
            flash('ØªÙ… Ø¥Ø¶Ø§ÙØ© Ø§Ù„ÙØ±Ø¹/Ø§Ù„Ù…ÙˆÙ‚Ø¹.');
        }
        redirect('locations');
    }

    if ($route === 'backup' && has_permission('backup')) {
        $action = post_string('action');
        if ($action === 'reset') {
            reset_database();
            flash('ØªÙ… ØªÙØ±ÙŠØº Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª ÙˆØ¥Ø¹Ø§Ø¯Ø© ØªÙ‡ÙŠØ¦Ø© Ø§Ù„Ù†Ø¸Ø§Ù… Ù…Ù† Ø§Ù„Ø¨Ø¯Ø§ÙŠØ©. Ø¨ÙŠØ§Ù†Ø§Øª Ø§Ù„Ø¯Ø®ÙˆÙ„ Ø§Ù„Ø§ÙØªØ±Ø§Ø¶ÙŠØ©: admin / admin123');
        } elseif ($action === 'restore') {
            if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
                $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
                if ($sql) {
                    // Ø¥Ø²Ø§Ù„Ø© Ø§Ù„ÙƒÙ„Ù…Ø§Øª ØºÙŠØ± Ø§Ù„Ù…Ø¯Ø¹ÙˆÙ…Ø© ÙÙŠ MySQL 8+ Ø¨Ø§Ø³ØªØ®Ø¯Ø§Ù… regex Ù„Ø¶Ù…Ø§Ù† Ø§Ù„ØªÙ‚Ø§Ø· ÙƒÙ„ Ø§Ù„Ù…Ø³Ø§ÙØ§Øª
                    $sql = preg_replace('/DEFAULT\s+(CURRENT_DATE|curdate\(\))/i', '', $sql);
                    
                    $db = pdo(true);
                    // ØªØ¹Ø·ÙŠÙ„ Ø§Ù„Ù‚ÙˆØ§Ø¹Ø¯ Ø§Ù„ØµØ§Ø±Ù…Ø© Ù…Ø¤Ù‚ØªØ§Ù‹ Ù„ØªÙ…Ø±ÙŠØ± Ø§Ù„ØªÙˆØ§Ø±ÙŠØ® Ø§Ù„Ù‚Ø¯ÙŠÙ…Ø© Ù…Ø«Ù„ 0000-00-00
                    $db->exec("SET sql_mode = '';");
                    $db->exec('SET FOREIGN_KEY_CHECKS=0;');
                    try {
                        $db->exec($sql);
                        log_audit((int) $user['id'], 'restore', 'database', null, 'Restored from uploaded file');
                        flash('ØªÙ… Ø§Ø³ØªØ±Ø¬Ø§Ø¹ Ù‚Ø§Ø¹Ø¯Ø© Ø§Ù„Ø¨ÙŠØ§Ù†Ø§Øª Ø¨Ù†Ø¬Ø§Ø­.');
                    } catch (Throwable $e) {
                        flash('Ø­Ø¯Ø« Ø®Ø·Ø£ Ø£Ø«Ù†Ø§Ø¡ Ø§Ù„Ø§Ø³ØªØ±Ø¬Ø§Ø¹: ' . $e->getMessage(), 'danger');
                    }
                    $db->exec('SET FOREIGN_KEY_CHECKS=1');
                }
            } else {
                flash('Ù„Ù… ÙŠØªÙ… Ø±ÙØ¹ Ù…Ù„Ù ØµØ§Ù„Ø­.', 'danger');
            }
        } else {
            $file = backup_database((int) $user['id']);
            flash('ØªÙ… Ø¥Ù†Ø´Ø§Ø¡ Ù†Ø³Ø®Ø© Ø§Ø­ØªÙŠØ§Ø·ÙŠØ©: ' . basename($file));
        }
        redirect('backup');
    }

    if ($route === 'settings' && has_permission('settings')) {
        update_settings($_POST, (int) $user['id']);
        flash('ØªÙ… Ø­ÙØ¸ Ø¥Ø¹Ø¯Ø§Ø¯Ø§Øª Ø§Ù„Ù†Ø¸Ø§Ù….');
        redirect('settings');
    }

    if ($route === 'system_updates' && has_permission('settings')) {
        $action = post_string('action');
        if ($action === 'publish_update') {
            $title = post_string('title');
            $version = post_string('version');
            $urgency = post_string('urgency', 'important');
            $content = post_string('content');
            create_system_update($title, $content, $version, $urgency, (int) $user['id']);
            flash('ðŸš€ ØªÙ… Ù†Ø´Ø± Ø§Ù„ØªØ­Ø¯ÙŠØ« Ø¨Ù†Ø¬Ø§Ø­ ÙˆØ¨Ø« Ø§Ù„ØªÙ†Ø¨ÙŠÙ‡ Ù„Ø¬Ù…ÙŠØ¹ Ø§Ù„Ø´Ø§Ø´Ø§Øª Ø§Ù„Ù…ÙØªÙˆØ­Ø© ÙÙˆØ±Ø§Ù‹.');
            redirect('system_updates');
        } elseif ($action === 'delete_update') {
            $id = (int) post_string('id');
            delete_system_update($id, (int) $user['id']);
            flash('ØªÙ… Ø­Ø°Ù Ø§Ù„ØªØ­Ø¯ÙŠØ« Ù…Ù† Ø§Ù„Ø³Ø¬Ù„.');
            redirect('system_updates');
        }
    }
}

function render_page(string $route, array $user): void
{
    $allowed = all_routes();
    $allowed[] = 'products_bulk_add';

    if (!in_array($route, $allowed, true) && $route !== 'call_center') {
        $route = 'dashboard';
    }

    if (!has_permission($route)) {
        echo '<div class="alert danger">ØºÙŠØ± Ù…ØµØ±Ø­ Ù„Ùƒ Ø¨Ø¯Ø®ÙˆÙ„ Ù‡Ø°Ù‡ Ø§Ù„ØµÙØ­Ø©.</div>';
        return;
    }

    $file = page_path_for_route($route);
    if (!is_file($file)) {
        $file = __DIR__ . '/pages/main/dashboard.php';
    }

    require $file;
}



