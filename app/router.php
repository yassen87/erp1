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
            echo json_encode(['success' => false, 'message' => 'غير مصرح']);
            exit;
        }
        
        $locationId = (int) ($_GET['location_id'] ?? 0);
        if ($locationId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'الفرع مطلوب']);
            exit;
        }
        
        // Check if user can access this location
        $userLocationId = current_user_location_id();
        if ($userLocationId !== null && $userLocationId !== $locationId) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'غير مصرح للوصول لهذا الفرع']);
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
            echo json_encode(['success' => false, 'message' => 'غير مصرح']);
            exit;
        }

        $customerId = (int) ($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
        $locationId = (int) ($_GET['location_id'] ?? $_POST['location_id'] ?? 0);
        $search = trim((string) ($_GET['search'] ?? $_POST['search'] ?? ''));

        if ($customerId <= 0 && $search === '') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'العميل أو نص البحث مطلوب']);
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
            $data = json_decode($json, true) ?? [];
            if (empty($data['products']) || !is_array($data['products'])) {
                throw new Exception('لا توجد منتجات للإضافة');
            }

            db_begin_transaction();
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
                    
                    if (!$barcode && $type === 'fixed') {
                        $barcode = generate_product_barcode();
                    } elseif (!$barcode) {
                        $barcode = generate_unique_ean13(pdo());
                    }
                    
                    $fullName = $name;
                    if ($size > 0 && $type === 'bottle') $fullName .= " ($size ml)";
                    if ($size > 0 && $type === 'fixed') $fullName .= " ($size)";

                    $productId = db_insert('products', [
                        'name' => $fullName,
                        'type' => $type,
                        'unit' => 'unit',
                        'min_stock' => $minStock,
                        'sale_price' => $salePrice,
                        'cost_price' => $costPrice > 0 ? $costPrice : null,
                        'barcode' => $barcode ?: null
                    ]);
                    
                    save_product_details(pdo(), $productId, $type, ['size_ml' => $size > 0 ? $size : null]);
                } else if ($type === 'perfume_gram') {
                    $quota = trim((string)($p['quota'] ?? ''));
                    $salePrice = (float)($p['sale_price'] ?? 0); 
                    $costPrice = (float)($p['cost_price'] ?? 0);
                    $pricePerGram = (float)($p['price_per_gram'] ?? 0);
                    $family = trim((string)($p['family'] ?? ''));
                    $quality = trim((string)($p['quality'] ?? ''));
                    
                    $fullName = $name;
                    if ($quota !== '') $fullName .= " - $quota";
                    
                    $barcode = generate_unique_ean13(pdo());
                    
                    $productId = db_insert('products', [
                        'name' => $fullName,
                        'type' => $type,
                        'unit' => 'gram',
                        'min_stock' => $minStock,
                        'sale_price' => $pricePerGram, // fallback to gram price
                        'cost_price' => $costPrice > 0 ? $costPrice : null,
                        'barcode' => $barcode,
                        'sku' => $quota !== '' ? $quota : null
                    ]);
                    
                    save_product_details(pdo(), $productId, $type, [
                        'perfume_family' => $family ?: null,
                        'quality_grade' => $quality ?: null,
                        'price_per_gram' => $pricePerGram
                    ]);
                }
            }
            $user = current_user();
            log_audit($user ? (int)$user['id'] : null, 'create', 'product', null, 'إضافة سريعة متعددة (' . count($data['products']) . ')');
            db_commit();
            
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            db_rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($route === 'api_pos_return') {
        require_login();
        if (!has_permission('pos') && !has_permission('manage_returns')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'غير مصرح بعمل مرتجع']);
            exit;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true) ?? $_POST;

        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $refundMethod = (string) ($data['refund_method'] ?? 'cash');
        $refundPaid = isset($data['refund_paid']) ? (float) $data['refund_paid'] : -1.0;
        $reason = trim((string) ($data['reason'] ?? 'مرتجع مباشر من الكاشير'));
        $linesInput = $data['lines'] ?? [];

        if ($invoiceId <= 0 || empty($linesInput)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'بيانات المرتجع غير مكتملة']);
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
            echo json_encode(['success' => false, 'message' => 'لم يتم تحديد أصناف صالحة للإرجاع']);
            exit;
        }

        try {
            $user = current_user();
            create_return_selected_lines($invoiceId, $lineIds, $returnedQuantities, $refundMethod, $reason, (int) $user['id'], $refundPaid);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'تم تسجيل المرتجع واستعادة المخزون بنجاح',
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
            echo json_encode(['success' => false, 'error' => 'بيانات ناقصة']);
            exit;
        }

        $sent = send_whatsapp_message((string)$data['phone'], (string)$data['message']);
        header('Content-Type: application/json; charset=utf-8');
        if ($sent) {
            echo json_encode(['success' => true, 'message' => 'تم إرسال الرسالة بنجاح']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'تعذر الاتصال بمحرك الواتساب، يرجى التأكد من تشغيل الخدمة']);
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
            echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']);
            exit;
        }
        
        // Check if user can access this location
        $userLocationId = current_user_location_id();
        if ($userLocationId !== null && $locationId > 0 && $userLocationId !== $locationId) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'غير مصرح']);
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
            echo json_encode(['success' => false, 'message' => 'المنتج أو العرض غير موجود']);
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

    // AJAX: البحث عن أسماء المنتجات لعرض الـ autocomplete
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
            exit('غير مصرح');
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
        exit('غير مصرح');
    }
    $file = backup_file_path((string) $_GET['download']);
    if (!$file) {
        http_response_code(404);
        exit('الملف غير موجود');
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
                throw new RuntimeException('غير مصرح لك بحذف المنتجات.');
            }
            $result = delete_product((int) post_string('id'));
            if ($result === 'permanently_deleted') {
                flash('تم حذف المنتج نهائياً بنجاح.');
            } else {
                flash('تم إخفاء المنتج وتعطيله لوجود فواتير أو حركات مخزن مرتبطة به.');
            }
            redirect('products');
        }
        throw new RuntimeException('طلب غير صالح.');
    }

    if ($route === 'product_create' && has_permission('products_add')) {
        add_products_batch($_POST);
        flash('تم إضافة المنتج/المنتجات بنجاح.');
        redirect('products');
    }

    if ($route === 'product_edit' && has_permission('products_edit')) {
        update_product($_POST);
        flash('تم تعديل المنتج.');
        redirect('products');
    }

    if ($route === 'recipes' && has_permission('recipes_view')) {
        if (post_string('action') === 'delete') {
            if (!has_permission('recipes_edit')) {
                throw new RuntimeException('غير مصرح لك بحذف التركيبات.');
            }
            delete_recipe((int) post_string('id'));
            flash('تم حذف التركيبة بنجاح.');
        } else {
            if (!has_permission('recipes_add')) {
                throw new RuntimeException('غير مصرح لك بإضافة أو تعديل التركيبات.');
            }
            $recipe_id = (int) post_string('id');
            if ($recipe_id > 0) {
                update_recipe($recipe_id, $_POST);
                flash('تم تعديل التركيبة بنجاح.');
            } else {
                add_recipe($_POST);
                flash('تم حفظ التركيبة الجاهزة.');
            }
        }
        redirect('recipes');
    }

    if ($route === 'offers' && has_permission('products_view')) {
        $action = post_string('action');
        if ($action === 'delete') {
            if (!has_permission('products_edit') && !has_permission('products_add')) {
                throw new RuntimeException('غير مصرح لك بحذف العروض.');
            }
            delete_offer((int) post_string('id'), (int)$user['id']);
            flash('تم حذف العرض بنجاح.');
            redirect('offers');
        }
        if ($action === 'toggle_status') {
            if (!has_permission('products_edit')) {
                throw new RuntimeException('غير مصرح لك بتعديل حالة العروض.');
            }
            $newStatus = toggle_offer_status((int) post_string('id'), (int)$user['id']);
            flash($newStatus ? 'تم تفعيل العرض بنجاح.' : 'تم إيقاف/تعطيل العرض بنجاح.');
            redirect('offers');
        }
    }

    if ($route === 'offer_create' && has_permission('products_add')) {
        $offerId = create_offer($_POST, (int)$user['id']);
        flash('تم إنشاء العرض بنجاح 🎁');
        redirect('offers');
    }

    if ($route === 'offer_edit' && has_permission('products_edit')) {
        $offerId = (int) post_string('id');
        update_offer($offerId, $_POST, (int)$user['id']);
        flash('تم حفظ تعديلات العرض بنجاح.');
        redirect('offers');
    }

    if ($route === 'formula_defaults' && has_permission('recipes_view')) {
        $action = post_string('action');
        if ($action === 'delete') {
            if (!has_permission('recipes_edit')) {
                throw new RuntimeException('غير مصرح لك بحذف الجرامات الافتراضية.');
            }
            delete_formula_default((int) post_string('id'));
            flash('تم حذف إعداد الجرامات الافتراضية.');
        } elseif ($action === 'delete_group') {
            if (!has_permission('recipes_edit')) {
                throw new RuntimeException('غير مصرح لك بحذف الجرامات الافتراضية.');
            }
            $qualityGrade = post_string('quality_grade');
            delete_formula_defaults_by_bottle((int) post_string('bottle_id'), $qualityGrade !== '' ? $qualityGrade : null);
            flash('تم حذف مجموعة الجرامات الافتراضية للزجاجة.');
        } else {
            if (!has_permission('recipes_add')) {
                throw new RuntimeException('غير مصرح لك بإضافة أو تعديل الجرامات الافتراضية.');
            }
            if ($action === 'replace_bottle_group') {
                replace_formula_defaults_for_bottle($_POST);
                flash('تم حفظ روابط الزجاجة بنجاح.');
            } else {
                upsert_formula_default($_POST);
                flash('تم حفظ إعداد الجرامات الافتراضية بنجاح.');
            }
        }
        redirect('formula_defaults');
    }

    if ($route === 'inventory' && has_permission('inventory_view')) {
        if (!has_permission('inventory_adjust')) {
            throw new RuntimeException('غير مصرح لك بتسوية المخزون.');
        }
        $action = post_string('action');
        $locationId = (int) post_string('location_id');
        require_location_access($locationId);
        require_location_type($locationId, ['warehouse', 'branch'], 'الأونلاين ليس مخزناً ولا يمكن تعديل رصيده.');

        if ($action === 'delete') {
            $movementIdStr = post_string('movement_id');
            $movementIds = explode(',', $movementIdStr);
            foreach ($movementIds as $id) {
                if (is_numeric($id)) {
                    delete_inventory_addition((int) $id, (int) $user['id']);
                }
            }
            flash('تم حذف إضافة المخزون بنجاح.');
        } elseif ($action === 'set_zero') {
            // تصفير رصيد منتج في الفرع
            $productId = (int) post_string('product_id');
            $db = pdo();
            $stmt = $db->prepare('UPDATE inventory_balances SET quantity = 0 WHERE product_id = ? AND location_id = ?');
            $stmt->execute([$productId, $locationId]);
            log_audit((int)$user['id'], 'update', 'inventory_balance', $productId, 'تصفير رصيد المنتج في الفرع رقم ' . $locationId);
            flash('تم تصفير الرصيد بنجاح.');
            redirect('branch_inventory&location_id=' . $locationId);
        } elseif ($action === 'edit_balance') {
            // تعديل رصيد منتج مباشرة
            $productId   = (int) post_string('product_id');
            $newQuantity = (float) post_string('new_quantity');
            $db = pdo();
            $stmt = $db->prepare('UPDATE inventory_balances SET quantity = ? WHERE product_id = ? AND location_id = ?');
            $stmt->execute([$newQuantity, $productId, $locationId]);
            log_audit((int)$user['id'], 'update', 'inventory_balance', $productId, 'تعديل رصيد مباشر للمنتج في الفرع: ' . $newQuantity);
            flash('تم تعديل الكمية بنجاح.');
            redirect('branch_inventory&location_id=' . $locationId);
        } elseif ($action === 'update') {
            update_inventory_addition($_POST, (int) $user['id']);
            flash('تم تعديل إضافة المخزون بنجاح.');
        } else {
            create_inventory_addition($_POST, (int) $user['id']);
            flash('تم تسجيل إضافة مخزون جديدة بنجاح.');
        }
        redirect('inventory');
    }

    if ($route === 'inventory_add' && has_permission('inventory_view')) {
        if (!has_permission('inventory_adjust')) {
            throw new RuntimeException('غير مصرح لك بتسوية المخزون.');
        }
        $locationId = (int) post_string('location_id');
        require_location_access($locationId);
        require_location_type($locationId, ['warehouse', 'branch'], 'الأونلاين ليس مخزناً ولا يمكن تعديل رصيده.');
        create_inventory_additions($_POST, (int) $user['id']);
        flash('تم تسجيل إضافات المخزون بنجاح.');
        redirect('inventory');
    }

    if ($route === 'transfers_supply' && has_permission('transfers')) {
        if (post_string('action') === 'receive') {
            receive_transfer((int) post_string('transfer_id'), (int) $user['id'], ['warehouse'], ['branch']);
            flash('تم استلام التوريد وإضافة الكمية للفرع المستلم.');
        } elseif (post_string('action') === 'cancel') {
            cancel_transfer((int) post_string('transfer_id'), (int) $user['id'], ['warehouse'], ['branch']);
            flash('تم إلغاء أمر التوريد وإعادة الكمية للمخزن.');
        } elseif (post_string('action') === 'update') {
            update_supply_transfer($_POST, (int) $user['id']);
            flash('تم تعديل أمر التوريد بنجاح.');
        } else {
            create_supply_transfer($_POST, (int) $user['id']);
            flash('تم إنشاء أمر التوريد وخصم الكمية من المخزن.');
        }
        redirect('transfers_supply');
    }

    if ($route === 'transfers_branch' && has_permission('transfers')) {
        if (post_string('action') === 'receive') {
            receive_transfer((int) post_string('transfer_id'), (int) $user['id'], ['branch'], ['branch']);
            flash('تم استلام التحويل وإضافة الكمية للفرع المستلم.');
        } elseif (post_string('action') === 'cancel') {
            cancel_transfer((int) post_string('transfer_id'), (int) $user['id'], ['branch'], ['branch']);
            flash('تم إلغاء التحويل وإعادة الكمية للفرع المرسل.');
        } elseif (post_string('action') === 'update') {
            update_branch_transfer($_POST, (int) $user['id']);
            flash('تم تعديل أمر التحويل بنجاح.');
        } else {
            create_branch_transfer($_POST, (int) $user['id']);
            flash('تم إنشاء أمر التحويل وخصم الكمية من الفرع المرسل.');
        }
        redirect('transfers_branch');
    }

    if ($route === 'returns' && has_permission('invoices')) {
        $action = post_string('action', 'create');

        if ($action === 'update_paid') {
            $returnId = (int) post_string('return_id');
            $newPaid  = post_float('refund_paid');
            if (!$returnId) throw new RuntimeException('معرف المرتجع مطلوب.');
            update_return_paid($returnId, $newPaid, (int) $user['id']);
            flash('تم تحديث مبلغ الرد بنجاح.');
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
                throw new RuntimeException('الفاتورة والسبب مطلوبان.');
            }
            $returnedQuantities = $_POST['returned_quantities'] ?? [];
            create_return_selected_lines($invoiceId, $lineIds, $returnedQuantities, $method, $reason, (int) $user['id'], $refundPaid);
            flash('تم تسجيل المرتجع بنجاح.');
        } elseif ($returnType === 'invoice') {
            $invoiceId = (int) post_string('invoice_id');
            $method    = post_string('refund_method', 'cash');
            $reason    = post_string('reason');
            if (!$invoiceId || !$reason) {
                throw new RuntimeException('الفاتورة والسبب مطلوبان.');
            }
            create_return_invoice($invoiceId, $method, $reason, (int) $user['id'], $refundPaid);
            flash('تم تسجيل مرتجع الفاتورة بنجاح.');
        } elseif ($returnType === 'line') {
            $lineId = (int) post_string('line_id');
            $method = post_string('refund_method', 'cash');
            $reason = post_string('reason');
            if (!$lineId || !$reason) {
                throw new RuntimeException('بند الفاتورة والسبب مطلوبان.');
            }
            create_return_line_invoice($lineId, $method, $reason, (int) $user['id']);
            flash('تم تسجيل مرتجع البند بنجاح.');
        } else {
            throw new RuntimeException('نوع المرتجع غير معروف.');
        }
        redirect('returns');
    }

    if ($route === 'waste' && has_permission('inventory_view')) {
        if (!has_permission('inventory_adjust')) {
            throw new RuntimeException('غير مصرح لك بتسجيل هالك.');
        }
        $locationId = (int) post_string('location_id');
        require_location_access($locationId);
        require_location_type($locationId, ['warehouse', 'branch'], 'الأونلاين ليس مخزناً ولا يمكن تسجيل هالك عليه.');
        add_wasted_product([
            'location_id' => $locationId,
            'product_id' => (int) post_string('product_id'),
            'quantity' => post_float('quantity'),
            'reason' => post_string('reason'),
        ], (int) $user['id']);
        flash('تم تسجيل الهالك وخصمه من مخزون الموقع بنجاح.');
        redirect('waste');
    }

    if ($route === 'customers_debts' && has_permission('customers_view')) {
        $action = post_string('action');
        if ($action === 'pay_debt') {
            if (!has_permission('customers_pay_debt')) {
                throw new RuntimeException('غير مصرح لك بتسجيل سداد الديون.');
            }
            add_customer_payment((int) post_string('debt_id'), post_float('amount'), post_string('method', 'cash'), (int) $user['id']);
            flash('تم تسجيل دفعة الدين بنجاح.');
        } elseif ($action === 'add_direct_debt') {
            $customerId = (int) post_string('customer_id');
            $amount = post_float('amount');
            $notes = post_string('notes');
            $locationId = (int) post_string('location_id') ?: current_user_location_id();
            add_direct_customer_debt($customerId, $amount, $notes, $locationId, (int) $user['id']);
            flash('تم تسجيل الدين المباشر على العميل بنجاح.');
        } else {
            throw new RuntimeException('طلب غير صالح لصفحة الديون المفتوحة.');
        }
        $query = $_GET;
        unset($query['r']);
        redirect('customers_debts' . ($query ? '&' . http_build_query($query) : ''));
    }

    if ($route === 'customers' && has_permission('customers_view')) {
        if (post_string('action') === 'pay_debt') {
            if (!has_permission('customers_pay_debt')) {
                throw new RuntimeException('غير مصرح لك بتسجيل سداد الديون.');
            }
            add_customer_payment((int) post_string('debt_id'), post_float('amount'), post_string('method', 'cash'), (int) $user['id']);
            flash('تم تسجيل دفعة الدين.');
        } elseif (post_string('action') === 'add_direct_debt') {
            $customerId = (int) post_string('customer_id');
            $amount = post_float('amount');
            $notes = post_string('notes');
            $locationId = (int) post_string('location_id') ?: current_user_location_id();
            add_direct_customer_debt($customerId, $amount, $notes, $locationId, (int) $user['id']);
            flash('تم تسجيل الدين المباشر على العميل بنجاح.');
            $redirectTo = post_string('redirect_to');
            if ($redirectTo === 'customer_view') {
                redirect('customer_view&id=' . $customerId);
            }
            redirect('customers');
        } elseif (post_string('action') === 'update') {
            if (!has_permission('customers_edit')) {
                throw new RuntimeException('غير مصرح لك بتعديل بيانات العملاء.');
            }
            update_customer($_POST);
            flash('تم تعديل بيانات العميل.');
        } elseif (post_string('action') === 'delete') {
            if (!has_permission('customers_edit')) {
                throw new RuntimeException('غير مصرح لك بحذف العملاء.');
            }
            $result = delete_customer((int) post_string('id'));
            if ($result === 'permanently_deleted') {
                flash('تم حذف العميل نهائياً بنجاح.');
            } else {
                flash('تم إخفاء العميل وتعطيله لوجود فواتير أو حركات مرتبطة به.');
            }
        } else {
            if (!has_permission('customers_add')) {
                throw new RuntimeException('غير مصرح لك بإضافة عملاء.');
            }
            add_customer([
                'name' => post_string('name'),
                'phone' => post_string('phone'),
                'source' => post_string('source', 'offline'),
                'notes' => post_string('notes'),
                'location_id' => current_user_location_id(),
                'created_by' => (int) $user['id'],
            ]);
            flash('تم إضافة العميل.');
        }
        redirect('customers');
    }

    if ($route === 'customer_birthdays' && has_permission('customers_view')) {
        $action = post_string('action');
        if ($action === 'save_settings') {
            save_setting('whatsapp_birthday_enabled', post_string('whatsapp_birthday_enabled') === '1' ? '1' : '0');
            save_setting('whatsapp_birthday_send_time', post_string('whatsapp_birthday_send_time', '12:00'));
            save_setting('whatsapp_birthday_message', post_string('whatsapp_birthday_message'));
            flash('تم حفظ إعدادات أعياد الميلاد وموعد الإرسال اليومي بنجاح.');
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
                'تم الإرسال لـ %d عميل | تم تخطي %d | فشل %d (إجمالي اليوم: %d)',
                $res['sent_count'],
                $res['skipped_count'],
                $res['failed_count'],
                $res['total_today']
            );
            if ($res['sent_count'] > 0) {
                flash('تم إرسال تهاني أعياد الميلاد بنجاح! ' . $msg);
            } elseif ($res['skipped_count'] > 0 && $res['total_today'] > 0) {
                flash('تم إرسال التهنئة لجميع عملاء اليوم مسبقاً! ' . $msg, 'warning');
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
            flash('تم حفظ إعدادات استبيان رضا العملاء ووقت التأخير بنجاح.');
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
            flash("تمت معالجة الطابور بنجاح: تم إرسال {$res['sent']} رسالة استبيان (فشل {$res['failed']}).");
            redirect('customer_surveys');
        } elseif ($action === 'cancel') {
            $queueId = (int) post_string('queue_id');
            $db = pdo();
            $stmt = $db->prepare("UPDATE customer_survey_queue SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$queueId]);
            flash('تم إلغاء إرسال رسالة الاستبيان المحددة.');
            redirect('customer_surveys&tab=queue');
        }
        redirect('customer_surveys');
    }

    if ($route === 'pos' && has_permission('pos')) {
        $invoiceId = create_invoice($_POST, $user);
        // إرسال الفاتورة تلقائياً للعميل عبر الواتساب
        try {
            send_invoice_whatsapp($invoiceId);
        } catch (Throwable $waErr) {}
        // جدولة رسالة استبيان رضا العميل بعد وقت محدد
        try {
            schedule_invoice_satisfaction_survey($invoiceId);
        } catch (Throwable $surveyErr) {}
        flash('تم إنشاء الفاتورة رقم #' . $invoiceId . ' وخصم المخزون.');
        // Redirect back to POS and request the client to open the printable invoice in a new window
        // also instruct client to clear the POS cart
        redirect('pos&print_invoice=' . $invoiceId . '&clear_cart=1');
    }

    if ($route === 'shifts' && has_permission('shifts')) {
        $action = post_string('action');
        if ($action === 'delete') {
            delete_shift_closure((int) post_string('id'));
            flash('تم حذف الشيفت بنجاح.');
        } elseif ($action === 'update') {
            update_shift_closure((int) post_string('id'), post_float('actual_cash'), post_string('notes'));
            flash('تم تعديل الشيفت بنجاح.');
        } else {
            $locationId = (int) post_string('location_id');
            $actualCash = post_float('actual_cash');
            $notes = post_string('notes');
            $cashAction = post_string('cash_transfer_action', 'none');
            $cashAmount = post_float('cash_transferred_amount', 0);
            
            close_shift_with_details($locationId, $actualCash, $notes, $user, $cashAction, $cashAmount);
            flash('✅ تم إغلاق الشيفت وتسجيل الكاش بنجاح.');
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
            flash('تم تحديث ملاحظات الفاتورة.');
            redirect('invoices');
        }
        if (post_string('action') === 'delete_invoice') {
            if (!has_permission('manager')) {
                throw new RuntimeException('حذف الفواتير متاح للمديرين فقط.');
            }
            $invoiceId = (int) post_string('invoice_id');
            delete_invoice_with_restore($invoiceId, (int) $user['id']);
            flash('تم حذف الفاتورة وإرجاع المخزون بنجاح.');
            redirect('invoices');
        }
    }

    if ($route === 'returns' && has_permission('returns')) {
        if (post_string('return_type') === 'line') {
            create_return_line_invoice((int) post_string('line_id'), post_string('refund_method', 'cash'), post_string('reason'), (int) $user['id']);
            flash('تم تنفيذ مرتجع البند وإرجاع المخزون.');
        } else {
            create_return_invoice((int) post_string('invoice_id'), post_string('refund_method', 'cash'), post_string('reason'), (int) $user['id']);
            flash('تم تنفيذ المرتجع وإرجاع المخزون.');
        }
        redirect('returns');
    }

    if ($route === 'attendance') {
        if (post_string('action') === 'generate_qr') {
            if (!has_permission('attendance')) {
                throw new RuntimeException('غير مصرح لك بتوليد رمز QR.');
            }
            $locationId = (int) post_string('location_id');
            require_location_type($locationId, ['warehouse', 'branch'], 'الأونلاين لا يتم توليد QR حضور له.');
            $token = 'LOC-' . $locationId . '-' . bin2hex(random_bytes(8));
            $db = pdo();
            $stmt = $db->prepare('UPDATE locations SET qr_code = ? WHERE id = ?');
            $stmt->execute([$token, $locationId]);
            flash('تم توليد رمز QR بنجاح.');
            redirect('attendance&tab=qrcodes');
        } elseif (post_string('action') === 'update_location_geo') {
            if (!has_permission('users_permissions')) {
                throw new RuntimeException('غير مصرح لك بتعديل موقع الفرع.');
            }
            $locationId = (int) post_string('location_id');
            require_location_type($locationId, ['warehouse', 'branch'], 'الأونلاين لا يتم تسجيل حضور أو انصراف له.');
            $lat = post_float('latitude');
            $lng = post_float('longitude');
            $db = pdo();
            $stmt = $db->prepare('UPDATE locations SET latitude = ?, longitude = ? WHERE id = ?');
            $stmt->execute([$lat, $lng, $locationId]);
            flash('تم تحديث الإحداثيات الجغرافية للموقع بنجاح.');
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
                flash('رمز QR غير صالح أو الموقع غير نشط.', 'danger');
                redirect('attendance');
            }
            if ($loc['type'] === 'online') {
                throw new RuntimeException('الأونلاين لا يتم تسجيل حضور أو انصراف له.');
            }
            require_location_access((int) $loc['id']);

            // GPS Lock verification (must be within 10 meters)
            if ($loc['latitude'] === null || $loc['longitude'] === null) {
                throw new RuntimeException('إحداثيات هذا الفرع غير مسجلة بالنظام. يرجى مراجعة الإدارة.');
            }
            if ($lat === null || $lng === null) {
                throw new RuntimeException('يرجى تفعيل الـ GPS والسماح للمتصفح بالوصول لموقعك الجغرافي لتسجيل الحضور.');
            }
            $distance = calculate_distance((float)$lat, (float)$lng, (float)$loc['latitude'], (float)$loc['longitude']);
            if ($distance > 20.0) {
                throw new RuntimeException('أنت بعيد جداً عن الفرع. المسافة الحالية: ' . round($distance, 1) . ' متر. يجب أن تكون على بعد 20 متراً على الأكثر لتسجيل حضور/انصراف.');
            }

            // Double scan / status check verification
            $expectedAction = get_next_attendance_action((int) $user['id']);
            if ($scanAction !== $expectedAction) {
                if ($scanAction === 'check_in') {
                    throw new RuntimeException('لقد قمت بتسجيل الحضور بالفعل.');
                } else {
                    throw new RuntimeException('يجب تسجيل الحضور أولاً قبل تسجيل الانصراف.');
                }
            }
            
            $stmt = $db->prepare('INSERT INTO attendance_records (user_id, location_id, action, source, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], $loc['id'], $scanAction, 'qr', $lat, $lng]);
            
            flash(($scanAction === 'check_in' ? 'تم تسجيل حضورك بنجاح في ' : 'تم تسجيل انصرافك بنجاح من ') . $loc['name']);
            redirect('attendance');
        } else {
            if (!has_permission('attendance')) {
                throw new RuntimeException('غير مصرح لك بالتحكم في الحضور والانصراف.');
            }
            $action = post_string('action');
            if ($action === 'delete_attendance') {
                delete_attendance((int) post_string('attendance_id'), (int) $user['id']);
                flash('تم حذف سجل الحضور بنجاح.');
            } elseif ($action === 'update_attendance') {
                update_attendance((int) post_string('attendance_id'), $_POST, (int) $user['id']);
                flash('تم تعديل سجل الحضور بنجاح.');
            } else {
                add_attendance($_POST, (int) $user['id']);
                flash('تم إضافة سجل الحضور اليدوي بنجاح.');
            }
            redirect('attendance_log');
        }
    }

    if ($route === 'targets' && has_permission('targets')) {
        $action = post_string('action', 'save_tiers');
        if ($action === 'save_tiers') {
            save_target_commission_tiers($_POST, (int) $user['id']);
            flash('تم حفظ شرائح عمولات التارجت.');
            redirect('targets');
        }
        if ($action === 'delete') {
            delete_target((int) post_string('id'));
            flash('تم حذف التارجت اليومي.');
        } elseif ($action === 'update') {
            $locationId = (int) post_string('location_id');
            update_target((int) post_string('id'), $locationId, post_string('target_date'), post_float('target_amount'), (int) $user['id']);
            flash('تم تعديل التارجت اليومي.');
        } else {
            $locationId = (int) post_string('location_id');
            require_location_access($locationId);
            upsert_target($locationId, post_string('target_date'), post_float('target_amount'), (int) $user['id']);
            flash('تم حفظ التارجت اليومي.');
        }
        redirect('targets');
    }

    if ($route === 'expenses' && has_permission('expenses_view')) {
        if (!has_permission('expenses_add')) {
            throw new RuntimeException('غير مصرح لك بتسجيل مصاريف.');
        }
        $action = post_string('action', 'add');
        if ($action === 'edit') {
            edit_expense((int) $_POST['id'], $_POST, (int) $user['id']);
            flash('تم تعديل المصروف.');
        } elseif ($action === 'delete') {
            delete_expense((int) $_POST['id'], (int) $user['id']);
            flash('تم حذف المصروف.');
        } else {
            add_expense($_POST, (int) $user['id']);
            flash('تم تسجيل المصروف.');
        }
        redirect('expenses');
    }

    if ($route === 'branch_cash_transfers' && has_permission('branch_cash_transfers')) {
        $action = post_string('action', 'create');
        if ($action === 'receive') {
            if (!has_permission('manager_treasury') && current_user()['role_code'] !== 'admin') {
                throw new RuntimeException('غير مصرح لك بتأكيد استلام التحويل، متاح لمدير النظام فقط.');
            }
            receive_branch_cash_transfer((int) post_string('id'), (int) $user['id']);
            flash('تم استلام تحويل الخزينة.');
        } elseif ($action === 'cancel') {
            cancel_branch_cash_transfer((int) post_string('id'), (int) $user['id']);
            flash('تم إلغاء تحويل الخزينة وإرجاع الرصيد.');
        } else {
            if (!has_permission('expenses_add') && !has_permission('branch_cash_transfers')) {
                throw new RuntimeException('غير مصرح لك بإنشاء تحويل خزينة.');
            }
            create_branch_cash_transfer($_POST, (int) $user['id']);
            flash('تم إنشاء تحويل الخزينة.');
        }
        redirect('branch_cash_transfers');
    }

    if ($route === 'manager_treasury' && has_permission('manager_treasury')) {
        $action = post_string('action', 'receive');
        if ($action === 'receive') {
            receive_manager_collection((int) post_string('id'), (int) $user['id']);
            flash('تم تأكيد استلام تحصيل خزينة المدير.');
        } elseif ($action === 'cancel') {
            cancel_manager_collection((int) post_string('id'), (int) $user['id']);
            flash('تم إلغاء تحصيل خزينة المدير.');
        }
        redirect('manager_treasury');
    }

    if ($route === 'suppliers' && has_permission('suppliers_view')) {
        if (!has_permission('suppliers_add')) {
            throw new RuntimeException('غير مصرح لك بتسجيل موردين.');
        }
        add_supplier($_POST, (int) $user['id']);
        flash('تم تسجيل المورد.');
        redirect('suppliers');
    }

    if ($route === 'users' && has_permission('users_view')) {
        if (post_string('action') === 'save_permissions') {
            if (!has_permission('users_permissions')) {
                throw new RuntimeException('غير مصرح لك بتعديل الصلاحيات.');
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
                flash('تم حفظ الصلاحيات بنجاح.');
                redirect('users&tab=permissions');
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
        } elseif (post_string('action') === 'add_role') {
            if (!has_permission('users_permissions')) {
                throw new RuntimeException('غير مصرح لك بإضافة أدوار جديدة.');
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
                        flash('تم إضافة الدور الجديد بنجاح مع الصلاحيات المحددة.');
                    } else {
                        $db->rollBack();
                        flash('كود الدور مسجل مسبقاً، يرجى اختيار كود آخر.', 'danger');
                    }
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }
            } else {
                flash('اسم الدور أو الكود غير صالح.', 'danger');
            }
            redirect('users&tab=permissions');
        } elseif (post_string('action') === 'delete_role') {
            if (!has_permission('users_permissions')) {
                throw new RuntimeException('غير مصرح لك بحذف الأدوار.');
            }
            $role_id = (int) post_string('role_id');
            $db = pdo();
            
            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE role_id = ?');
            $stmt->execute([$role_id]);
            $count = (int) $stmt->fetchColumn();
            
            if ($count > 0) {
                flash('لا يمكن حذف هذا الدور لوجود موظفين مسجلين به حالياً.', 'danger');
            } else {
                $db->beginTransaction();
                try {
                    $db->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$role_id]);
                    $db->prepare('DELETE FROM roles WHERE id = ?')->execute([$role_id]);
                    $db->commit();
                    unset($_SESSION['permissions']);
                    flash('تم حذف الدور بنجاح.');
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }
            }
            redirect('users&tab=permissions');
        } elseif (post_string('action') === 'deactivate') {
            if (!has_permission('users_add')) {
                throw new RuntimeException('غير مصرح لك بتعطيل الموظفين.');
            }
            $id = (int) post_string('id');
            if ($id === (int) $user['id']) {
                throw new RuntimeException('لا يمكن تعطيل حسابك الحالي.');
            }
            deactivate_user($id);
            flash('تم تعطيل الموظف.');
        } elseif (post_string('action') === 'delete') {
            if (!has_permission('users_add')) {
                throw new RuntimeException('غير مصرح لك بحذف الموظفين.');
            }
            $id = (int) post_string('id');
            if ($id === (int) $user['id']) {
                throw new RuntimeException('لا يمكن حذف حسابك الحالي نهائياً.');
            }
            delete_user_permanently($id);
            flash('تم حذف الموظف نهائياً.');
        } elseif (post_string('action') === 'update') {
            if (!has_permission('users_add')) {
                throw new RuntimeException('غير مصرح لك بتعديل الموظفين.');
            }
            update_user($_POST);
            flash('تم تعديل بيانات الموظف.');
        } else {
            if (!has_permission('users_add')) {
                throw new RuntimeException('غير مصرح لك بإضافة موظفين.');
            }
            add_user($_POST);
            flash('تم إنشاء حساب الموظف.');
        }
    }

    if ($route === 'payroll' && has_permission('users_view')) {
        $action = post_string('action');
        $month = post_string('month', date('Y-m'));
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = date('Y-m');
        }
        if (in_array($action, ['update_rates', 'add_adjustment', 'delete_adjustment', 'save_override', 'pay_salary'], true) && !has_permission('users_permissions')) {
            throw new RuntimeException('غير مصرح لك بتعديل الرواتب.');
        }

        if ($action === 'add_adjustment') {
            add_payroll_adjustment($_POST, (int) $user['id']);
            flash('تم تسجيل الحافز أو الخصم.');
            redirect('payroll&month=' . $month);
        }

        if ($action === 'delete_adjustment') {
            delete_payroll_adjustment((int) post_string('id'), (int) $user['id']);
            flash('تم حذف الحافز أو الخصم.');
            redirect('payroll&month=' . $month);
        }

        if ($action === 'save_override') {
            save_payroll_override($_POST, (int) $user['id']);
            flash('تم حفظ إجماليات الراتب النهائية.');
            redirect('payroll&month=' . $month);
        }

        if ($action === 'update_rates') {
            $userId = (int) post_string('user_id');
            $salary = post_float('basic_salary');
            $comm = post_float('commission_percent');
            $db = pdo();
            $stmt = $db->prepare('UPDATE users SET basic_salary = ?, commission_percent = ? WHERE id = ?');
            $stmt->execute([$salary, $comm, $userId]);
            flash('تم تحديث الراتب الأساسي.');
            redirect('payroll&month=' . $month);
        }

        if ($action === 'pay_salary') {
            pay_salary($_POST, (int) $user['id']);
            flash('✅ تم تسجيل صرف الراتب بنجاح.');
            redirect('payroll&month=' . $month);
        }
    }

    if ($route === 'online_orders' && has_permission('online_orders')) {
        if (post_string('action') === 'status') {
            update_online_order_status((int) post_string('order_id'), post_string('status'), (int) $user['id']);
            flash('تم تحديث حالة الطلب.');
        } elseif (post_string('action') === 'delete') {
            delete_online_order((int) post_string('order_id'), (int) $user['id']);
            flash('تم حذف الطلب الأونلاين.');
        } elseif (post_string('action') === 'update') {
            update_online_order((int) post_string('order_id'), $_POST, (int) $user['id']);
            flash('تم تحديث طلب الأونلاين.');
        } else {
            create_online_order($_POST, (int) $user['id']);
            flash('تم إنشاء طلب أونلاين.');
        }
        redirect('online_orders');
    }

    if ($route === 'locations' && has_permission('settings')) {
        $action = post_string('action');
        if ($action === 'update') {
            update_location_data($_POST, (int) $user['id']);
            flash('تم تعديل بيانات الفرع/الموقع.');
        } elseif ($action === 'deactivate') {
            set_location_active((int) post_string('id'), false, (int) $user['id']);
            flash('تم تعطيل الفرع/الموقع.');
        } elseif ($action === 'activate') {
            set_location_active((int) post_string('id'), true, (int) $user['id']);
            flash('تم تفعيل الفرع/الموقع.');
        } else {
            add_location($_POST, (int) $user['id']);
            flash('تم إضافة الفرع/الموقع.');
        }
        redirect('locations');
    }

    if ($route === 'backup' && has_permission('backup')) {
        $action = post_string('action');
        if ($action === 'reset') {
            reset_database();
            flash('تم تفريغ البيانات وإعادة تهيئة النظام من البداية. بيانات الدخول الافتراضية: admin / admin123');
        } elseif ($action === 'restore') {
            if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
                $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
                if ($sql) {
                    // إزالة الكلمات غير المدعومة في MySQL 8+ باستخدام regex لضمان التقاط كل المسافات
                    $sql = preg_replace('/DEFAULT\s+(CURRENT_DATE|curdate\(\))/i', '', $sql);
                    
                    $db = pdo(true);
                    // تعطيل القواعد الصارمة مؤقتاً لتمرير التواريخ القديمة مثل 0000-00-00
                    $db->exec("SET sql_mode = '';");
                    $db->exec('SET FOREIGN_KEY_CHECKS=0;');
                    try {
                        $db->exec($sql);
                        log_audit((int) $user['id'], 'restore', 'database', null, 'Restored from uploaded file');
                        flash('تم استرجاع قاعدة البيانات بنجاح.');
                    } catch (Throwable $e) {
                        flash('حدث خطأ أثناء الاسترجاع: ' . $e->getMessage(), 'danger');
                    }
                    $db->exec('SET FOREIGN_KEY_CHECKS=1');
                }
            } else {
                flash('لم يتم رفع ملف صالح.', 'danger');
            }
        } else {
            $file = backup_database((int) $user['id']);
            flash('تم إنشاء نسخة احتياطية: ' . basename($file));
        }
        redirect('backup');
    }

    if ($route === 'settings' && has_permission('settings')) {
        update_settings($_POST, (int) $user['id']);
        flash('تم حفظ إعدادات النظام.');
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
            flash('🚀 تم نشر التحديث بنجاح وبث التنبيه لجميع الشاشات المفتوحة فوراً.');
            redirect('system_updates');
        } elseif ($action === 'delete_update') {
            $id = (int) post_string('id');
            delete_system_update($id, (int) $user['id']);
            flash('تم حذف التحديث من السجل.');
            redirect('system_updates');
        }
    }
}

function render_page(string $route, array $user): void
{
    $allowed = all_routes();

    if (!in_array($route, $allowed, true) && $route !== 'call_center') {
        $route = 'dashboard';
    }

    if (!has_permission($route)) {
        echo '<div class="alert danger">غير مصرح لك بدخول هذه الصفحة.</div>';
        return;
    }

    $file = page_path_for_route($route);
    if (!is_file($file)) {
        $file = __DIR__ . '/pages/main/dashboard.php';
    }

    require $file;
}


