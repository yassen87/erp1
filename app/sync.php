<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/services.php';

/**
 * Ensures the table for tracking offline synced invoices exists
 */
function ensure_sync_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $db = pdo();
    $db->exec("CREATE TABLE IF NOT EXISTS offline_sync_invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        offline_uuid VARCHAR(64) UNIQUE NOT NULL,
        invoice_id INT NOT NULL,
        location_id INT NOT NULL,
        synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        offline_created_at DATETIME NULL,
        raw_payload LONGTEXT NULL,
        INDEX idx_offline_uuid (offline_uuid),
        INDEX idx_invoice_id (invoice_id),
        INDEX idx_location_id (location_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    $checked = true;
}

/**
 * Authenticates an API request via session, token, or credentials
 */
function resolve_api_user(): ?array
{
    // 1. Check existing session
    $user = current_user();
    if ($user) {
        return $user;
    }

    // 2. Check JSON input or POST/GET for credentials or token
    $input = [];
    $raw = file_get_contents('php://input');
    if ($raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $input = $decoded;
        }
    }

    $username = trim((string)($input['username'] ?? $_POST['username'] ?? $_SERVER['PHP_AUTH_USER'] ?? ''));
    $password = (string)($input['password'] ?? $_POST['password'] ?? $_SERVER['PHP_AUTH_PW'] ?? '');

    if ($username !== '' && $password !== '') {
        $stmt = pdo()->prepare('SELECT u.*, r.code AS role_code, r.name AS role_name, l.name AS location_name 
                                FROM users u 
                                JOIN roles r ON r.id = u.role_id 
                                LEFT JOIN locations l ON l.id = u.location_id 
                                WHERE u.username = ? AND u.is_active = 1 LIMIT 1');
        $stmt->execute([$username]);
        $found = $stmt->fetch();
        if ($found && password_verify($password, $found['password_hash'])) {
            return $found;
        }
    }

    // Check token header or param
    $token = trim((string)($input['token'] ?? $_POST['token'] ?? $_GET['token'] ?? $_SERVER['HTTP_X_SYNC_TOKEN'] ?? ''));
    if ($token !== '') {
        // Token format: base64(user_id:sha256(password_hash_part)) or admin secret
        $parts = explode(':', base64_decode($token, true) ?: '');
        if (count($parts) === 2 && is_numeric($parts[0])) {
            $userId = (int)$parts[0];
            $hashSig = $parts[1];
            $stmt = pdo()->prepare('SELECT u.*, r.code AS role_code, r.name AS role_name, l.name AS location_name 
                                    FROM users u 
                                    JOIN roles r ON r.id = u.role_id 
                                    LEFT JOIN locations l ON l.id = u.location_id 
                                    WHERE u.id = ? AND u.is_active = 1 LIMIT 1');
            $stmt->execute([$userId]);
            $found = $stmt->fetch();
            if ($found && hash_equals(substr(hash('sha256', $found['password_hash']), 0, 32), $hashSig)) {
                return $found;
            }
        }
    }

    return null;
}

/**
 * Generates an API sync token for a user
 */
function generate_sync_token(array $user): string
{
    $hashSig = substr(hash('sha256', (string)($user['password_hash'] ?? '')), 0, 32);
    return base64_encode($user['id'] . ':' . $hashSig);
}

/**
 * Returns full data payload for offline POS operation
 */
function get_sync_pull_data(int $locationId): array
{
    ensure_sync_schema();
    $db = pdo();

    // 1. Sale locations
    $locations = sale_locations();

    // 2. All products with balances for this location
    $allProductsWithStock = array_values(all_products_with_stock($locationId, null, true));
    
    // Format products for offline POS
    $products = array_map(function ($p) {
        return [
            'id'                => (int)$p['id'],
            'name'              => $p['name'],
            'type'              => $p['type'],
            'unit'              => $p['unit'] ?? 'unit',
            'sale_price'        => (float)$p['sale_price'],
            'cost_price'        => isset($p['cost_price']) ? (float)$p['cost_price'] : null,
            'barcode'           => $p['barcode'] ?? null,
            'sku'               => $p['sku'] ?? null,
            'size_ml'           => isset($p['size_ml']) ? (int)$p['size_ml'] : null,
            'perfume_family'    => $p['perfume_family'] ?? null,
            'quality_grade'     => $p['quality_grade'] ?? null,
            'price_per_gram'    => isset($p['price_per_gram']) ? (float)$p['price_per_gram'] : null,
            'branch_stock'      => isset($p['branch_stock']) ? (float)$p['branch_stock'] : 0.0,
            'stock_initialized' => isset($p['stock_initialized']) ? (int)$p['stock_initialized'] : 0,
        ];
    }, $allProductsWithStock);

    // 3. Customers
    $customers = array_map(function ($c) {
        return [
            'id'    => (int)$c['id'],
            'name'  => $c['name'],
            'phone' => $c['phone'] ?? '',
            'notes' => $c['notes'] ?? '',
        ];
    }, all_customers());

    // 4. Recipes
    $recipes = saved_recipes();

    // 5. Offers
    $offers = active_offers_for_pos($locationId);

    // 6. Formula Defaults
    $defaults = formula_defaults_rows();

    // 7. Payment destinations labels
    $paymentDestinations = [
        'instapay'      => payment_destination_label('instapay'),
        'vodafone_cash' => payment_destination_label('vodafone_cash'),
    ];

    return [
        'success'              => true,
        'server_time'          => date('Y-m-d H:i:s'),
        'location_id'          => $locationId,
        'locations'            => $locations,
        'products'             => $products,
        'customers'            => $customers,
        'recipes'              => $recipes,
        'offers'               => $offers,
        'formula_defaults'     => $defaults,
        'payment_destinations' => $paymentDestinations,
        'app_name'             => APP_NAME,
    ];
}

/**
 * Processes a batch of offline invoices pushed from the desktop app
 */
function process_pushed_offline_invoices(array $invoices, array $user, int $defaultLocationId): array
{
    ensure_sync_schema();
    $db = pdo();

    $results = [];
    $syncedCount = 0;
    $errorsCount = 0;

    foreach ($invoices as $inv) {
        $offlineUuid = trim((string)($inv['offline_uuid'] ?? ''));
        if ($offlineUuid === '') {
            $offlineUuid = 'offline_' . uniqid('', true);
        }

        // Idempotency check: Has this invoice already been synced?
        $stmtCheck = $db->prepare('SELECT s.invoice_id, i.invoice_number, i.total, i.created_at 
                                   FROM offline_sync_invoices s 
                                   JOIN invoices i ON i.id = s.invoice_id 
                                   WHERE s.offline_uuid = ? LIMIT 1');
        $stmtCheck->execute([$offlineUuid]);
        $existing = $stmtCheck->fetch();

        if ($existing) {
            $results[] = [
                'offline_uuid'   => $offlineUuid,
                'status'         => 'already_synced',
                'invoice_id'     => (int)$existing['invoice_id'],
                'invoice_number' => $existing['invoice_number'],
                'total'          => (float)$existing['total'],
                'message'        => 'تمت المزامنة مسبقاً',
            ];
            $syncedCount++;
            continue;
        }

        // Process new offline invoice
        try {
            $locationId = (int)($inv['location_id'] ?? $defaultLocationId);
            if ($locationId <= 0) {
                $locationId = $defaultLocationId;
            }

            // Customer handling: if customer_id not given or 0, check by phone or create new
            $customerId = !empty($inv['customer_id']) ? (int)$inv['customer_id'] : null;
            $custPhone = trim((string)($inv['customer_phone'] ?? ''));
            $custName  = trim((string)($inv['customer_name'] ?? ''));

            if ((!$customerId || $customerId <= 0) && ($custPhone !== '' || $custName !== '')) {
                if ($custPhone !== '') {
                    $stmtCust = $db->prepare('SELECT id FROM customers WHERE phone = ? LIMIT 1');
                    $stmtCust->execute([$custPhone]);
                    $cRow = $stmtCust->fetch();
                    if ($cRow) {
                        $customerId = (int)$cRow['id'];
                    }
                }
                if (!$customerId && $custName !== '') {
                    // Create new customer record
                    $stmtNewCust = $db->prepare('INSERT INTO customers (name, phone, location_id, created_by, source) VALUES (?, ?, ?, ?, "offline_pos")');
                    $stmtNewCust->execute([$custName, $custPhone ?: null, $locationId, (int)$user['id']]);
                    $customerId = (int)$db->lastInsertId();
                }
            }

            // Prepare POST-like payload for create_invoice()
            $postData = [
                'location_id'          => (string)$locationId,
                'customer_id'          => $customerId ? (string)$customerId : '',
                'payment_method'       => (string)($inv['payment_method'] ?? 'cash'),
                'paid_total'           => (string)($inv['paid_total'] ?? 0),
                'paid_cash'            => (string)($inv['paid_cash'] ?? ($inv['paid_total'] ?? 0)),
                'paid_secondary'       => (string)($inv['paid_secondary'] ?? 0),
                'discount_type'        => (string)($inv['discount_type'] ?? ''),
                'discount_value'       => (string)($inv['discount_value'] ?? 0),
                'packer_name'          => (string)($inv['packer_name'] ?? ''),
                'notes'                => (string)($inv['notes'] ?? ''),
                'product_id'           => [],
                'quantity'             => [],
                'unit_price'           => [],
                'line_discount_type'   => [],
                'line_discount_value'  => [],
                'is_gift'              => [],
                'mix_data'             => [],
                'recipe_id'            => [],
                'recipe_bottle_id'     => [],
                'recipe_without_bottle'=> [],
                'recipe_price'         => [],
                'recipe_is_gift'       => [],
                'offer_id'             => [],
                'offer_qty'            => [],
                'offer_price'          => [],
                'offer_custom_data'    => [],
            ];

            // 1. Direct products
            if (!empty($inv['items']) && is_array($inv['items'])) {
                foreach ($inv['items'] as $item) {
                    $postData['product_id'][]          = (string)($item['product_id'] ?? $item['id'] ?? 0);
                    $postData['quantity'][]            = (string)($item['quantity'] ?? 1);
                    $postData['unit_price'][]          = (string)($item['unit_price'] ?? $item['price'] ?? 0);
                    $postData['line_discount_type'][]  = (string)($item['discount_type'] ?? '');
                    $postData['line_discount_value'][] = (string)($item['discount_value'] ?? 0);
                    $postData['is_gift'][]             = !empty($item['is_gift']) ? '1' : '0';
                }
            }

            // 2. Custom Perfume Mixes
            if (!empty($inv['mixes']) && is_array($inv['mixes'])) {
                foreach ($inv['mixes'] as $mix) {
                    $postData['mix_data'][] = is_string($mix) ? $mix : json_encode($mix);
                }
            }

            // 3. Saved Recipes
            if (!empty($inv['recipes']) && is_array($inv['recipes'])) {
                foreach ($inv['recipes'] as $rec) {
                    $postData['recipe_id'][]             = (string)($rec['recipe_id'] ?? $rec['id'] ?? 0);
                    $postData['recipe_bottle_id'][]      = (string)($rec['bottle_id'] ?? 'default');
                    $postData['recipe_without_bottle'][] = !empty($rec['without_bottle']) ? '1' : '0';
                    $postData['recipe_price'][]          = (string)($rec['price'] ?? '');
                    $postData['recipe_is_gift'][]        = !empty($rec['is_gift']) ? '1' : '0';
                }
            }

            // 4. Offers
            if (!empty($inv['offers']) && is_array($inv['offers'])) {
                foreach ($inv['offers'] as $off) {
                    $postData['offer_id'][]          = (string)($off['offer_id'] ?? $off['id'] ?? 0);
                    $postData['offer_qty'][]         = (string)($off['qty'] ?? $off['quantity'] ?? 1);
                    $postData['offer_price'][]       = (string)($off['price'] ?? '');
                    $postData['offer_custom_data'][] = !empty($off['custom_data']) ? (is_string($off['custom_data']) ? $off['custom_data'] : json_encode($off['custom_data'])) : '';
                }
            }

            // Exchange data if present
            if (!empty($inv['exchange_data'])) {
                $postData['exchange_data'] = is_string($inv['exchange_data']) ? $inv['exchange_data'] : json_encode($inv['exchange_data']);
            }

            // Create the invoice in database
            $invoiceId = create_invoice($postData, $user);

            // Fetch created invoice details
            $stmtInv = $db->prepare('SELECT invoice_number, total, created_at FROM invoices WHERE id = ?');
            $stmtInv->execute([$invoiceId]);
            $createdInv = $stmtInv->fetch();

            $offlineCreatedAt = !empty($inv['created_at']) ? (string)$inv['created_at'] : null;

            // Record in offline_sync_invoices mapping table
            $stmtSync = $db->prepare('INSERT INTO offline_sync_invoices (offline_uuid, invoice_id, location_id, offline_created_at, raw_payload) VALUES (?, ?, ?, ?, ?)');
            $stmtSync->execute([
                $offlineUuid,
                $invoiceId,
                $locationId,
                $offlineCreatedAt,
                json_encode($inv, JSON_UNESCAPED_UNICODE)
            ]);

            // Attempt to trigger WhatsApp invoice message if available
            try {
                if (function_exists('send_invoice_whatsapp')) {
                    send_invoice_whatsapp($invoiceId);
                }
            } catch (Throwable $e) {}

            $results[] = [
                'offline_uuid'   => $offlineUuid,
                'status'         => 'synced',
                'invoice_id'     => $invoiceId,
                'invoice_number' => $createdInv ? $createdInv['invoice_number'] : ('INV-' . $invoiceId),
                'total'          => $createdInv ? (float)$createdInv['total'] : 0.0,
                'message'        => 'تمت المزامنة وحفظ الفاتورة بنجاح',
            ];
            $syncedCount++;

        } catch (Throwable $err) {
            $errorsCount++;
            $results[] = [
                'offline_uuid' => $offlineUuid,
                'status'       => 'error',
                'message'      => $err->getMessage(),
            ];
        }
    }

    return [
        'success'       => true,
        'synced_count'  => $syncedCount,
        'errors_count'  => $errorsCount,
        'results'       => $results,
        'server_time'   => date('Y-m-d H:i:s'),
    ];
}
