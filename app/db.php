<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
function pdo(bool $withDatabase = true): PDO
{
    static $db = null;
    static $server = null;
    if ($withDatabase && $db instanceof PDO) {
        return $db;
    }
    if (!$withDatabase && $server instanceof PDO) {
        return $server;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ($withDatabase ? ';dbname=' . DB_NAME : '') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    if ($withDatabase) {
        $pdo->exec("SET time_zone = '+03:00'");
        $db = $pdo;
    } else {
        $server = $pdo;
    }
    return $pdo;
}
function database_exists(): bool
{
    try {
        pdo(true)->query('SELECT 1 FROM users LIMIT 1');
        ensure_schema_updates();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
function ensure_schema_updates(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $db = pdo(true);
    $updates = [
        "ALTER TABLE locations ADD COLUMN qr_code VARCHAR(120) NULL",
        "ALTER TABLE locations ADD COLUMN latitude DECIMAL(10,7) NULL",
        "ALTER TABLE locations ADD COLUMN longitude DECIMAL(10,7) NULL",
        "ALTER TABLE locations ADD COLUMN geo_radius_m INT NOT NULL DEFAULT 100",
        "ALTER TABLE products ADD COLUMN min_stock DECIMAL(14,3) NOT NULL DEFAULT 0",
        "ALTER TABLE online_orders ADD COLUMN discount_type ENUM('amount','percent') NULL",
        "ALTER TABLE online_orders ADD COLUMN discount_value DECIMAL(12,2) NOT NULL DEFAULT 0",
        "ALTER TABLE online_orders ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0",
        "ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL",
        "CREATE TABLE IF NOT EXISTS role_permissions (role_id INT NOT NULL, permission_code VARCHAR(80) NOT NULL, PRIMARY KEY (role_id, permission_code), CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE users ADD COLUMN basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE users ADD COLUMN commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE customers ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1",
        "ALTER TABLE customers ADD COLUMN birthdate DATE NULL",
        "ALTER TABLE customers ADD COLUMN location_id INT NULL",
        "ALTER TABLE customers ADD COLUMN created_by INT NULL",
        "ALTER TABLE formula_defaults ADD COLUMN default_grams DECIMAL(12,3) NOT NULL DEFAULT 0",
        "ALTER TABLE formula_defaults ADD COLUMN bottle_product_id INT NULL",
        "ALTER TABLE inventory_transfers ADD COLUMN sender_name VARCHAR(120) NULL",
        "ALTER TABLE inventory_transfers ADD COLUMN receiver_name VARCHAR(120) NULL",
        "ALTER TABLE inventory_transfers ADD COLUMN transfer_date DATE NOT NULL DEFAULT CURRENT_DATE",
        "ALTER TABLE inventory_movements MODIFY COLUMN movement_type ENUM('initial','sale','manual_adjustment','transfer_future','transfer_adjust','return_future') NOT NULL",
        "CREATE TABLE IF NOT EXISTS wasted_products (id INT AUTO_INCREMENT PRIMARY KEY, location_id INT NOT NULL, product_id INT NOT NULL, quantity DECIMAL(14,3) NOT NULL, reason VARCHAR(255) NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_waste_location FOREIGN KEY (location_id) REFERENCES locations(id), CONSTRAINT fk_waste_product FOREIGN KEY (product_id) REFERENCES products(id), CONSTRAINT fk_waste_user FOREIGN KEY (created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS branch_cash_transfers (id INT AUTO_INCREMENT PRIMARY KEY, transfer_number VARCHAR(60) NOT NULL UNIQUE, location_id INT NOT NULL, method ENUM('cash','instapay','vodafone_cash') NOT NULL DEFAULT 'cash', amount DECIMAL(12,2) NOT NULL, transfer_date DATE NOT NULL DEFAULT CURRENT_DATE, status ENUM('pending','received','cancelled') NOT NULL DEFAULT 'pending', notes TEXT NULL, created_by INT NOT NULL, received_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, received_at DATETIME NULL, CONSTRAINT fk_cash_transfer_location FOREIGN KEY (location_id) REFERENCES locations(id), CONSTRAINT fk_cash_transfer_creator FOREIGN KEY (created_by) REFERENCES users(id), CONSTRAINT fk_cash_transfer_receiver FOREIGN KEY (received_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS manager_collections (id INT AUTO_INCREMENT PRIMARY KEY, collection_number VARCHAR(60) NOT NULL UNIQUE, location_id INT NOT NULL, method ENUM('cash','instapay','vodafone_cash') NOT NULL, source_type ENUM('auto_payment','branch_transfer') NOT NULL DEFAULT 'auto_payment', source_id BIGINT NULL, amount DECIMAL(12,2) NOT NULL, collection_date DATE NOT NULL, status ENUM('pending','received','cancelled') NOT NULL DEFAULT 'pending', notes TEXT NULL, created_by INT NULL, received_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, received_at DATETIME NULL, UNIQUE KEY uq_manager_collection_source (source_type, source_id, method), INDEX idx_manager_collections_location (location_id), INDEX idx_manager_collections_date (collection_date), INDEX idx_manager_collections_status (status), CONSTRAINT fk_manager_collection_location FOREIGN KEY (location_id) REFERENCES locations(id), CONSTRAINT fk_manager_collection_creator FOREIGN KEY (created_by) REFERENCES users(id), CONSTRAINT fk_manager_collection_receiver FOREIGN KEY (received_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS financial_ledger (id INT AUTO_INCREMENT PRIMARY KEY, location_id INT NULL, direction ENUM('in','out') NOT NULL, method ENUM('cash','instapay','vodafone_cash') NOT NULL DEFAULT 'cash', amount DECIMAL(12,2) NOT NULL, source_type VARCHAR(40) NOT NULL, source_id INT NULL, description VARCHAR(255) NULL, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_financial_ledger_date (created_at), INDEX idx_financial_ledger_location (location_id), CONSTRAINT fk_financial_ledger_location FOREIGN KEY (location_id) REFERENCES locations(id), CONSTRAINT fk_financial_ledger_user FOREIGN KEY (created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS target_commission_tiers (id INT AUTO_INCREMENT PRIMARY KEY, min_sales DECIMAL(12,2) NOT NULL, max_sales DECIMAL(12,2) NULL, commission_amount DECIMAL(12,2) NOT NULL, sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_target_tier_min (min_sales)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS payroll_adjustments (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, adjustment_month CHAR(7) NOT NULL, type ENUM('bonus','deduction') NOT NULL, amount DECIMAL(12,2) NOT NULL, reason VARCHAR(255) NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_payroll_adjust_user_month (user_id, adjustment_month), CONSTRAINT fk_payroll_adjust_user FOREIGN KEY (user_id) REFERENCES users(id), CONSTRAINT fk_payroll_adjust_creator FOREIGN KEY (created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS payroll_overrides (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, payroll_month CHAR(7) NOT NULL, commission_total DECIMAL(12,2) NULL, additions_total DECIMAL(12,2) NULL, deductions_total DECIMAL(12,2) NULL, notes TEXT NULL, updated_by INT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_payroll_override (user_id, payroll_month), CONSTRAINT fk_payroll_override_user FOREIGN KEY (user_id) REFERENCES users(id), CONSTRAINT fk_payroll_override_updater FOREIGN KEY (updated_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS customer_birthday_logs (id INT AUTO_INCREMENT PRIMARY KEY, customer_id INT NOT NULL, sent_year INT NOT NULL, phone VARCHAR(40) NOT NULL, message TEXT NULL, sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_customer_year (customer_id, sent_year)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS customer_survey_queue (id INT AUTO_INCREMENT PRIMARY KEY, invoice_id INT NOT NULL, customer_id INT NULL, phone VARCHAR(40) NOT NULL, token VARCHAR(64) NOT NULL UNIQUE, scheduled_at DATETIME NOT NULL, sent_at DATETIME NULL, status ENUM('pending', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending', rating ENUM('satisfied', 'unsatisfied') NULL, feedback_notes TEXT NULL, rated_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_survey_status_sched (status, scheduled_at), INDEX idx_survey_invoice (invoice_id), INDEX idx_survey_rating (rating)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE customer_debts MODIFY COLUMN invoice_id INT NULL",
        "ALTER TABLE customer_debts ADD COLUMN location_id INT NULL",
        "ALTER TABLE customer_debts ADD COLUMN notes VARCHAR(255) NULL",
        "ALTER TABLE customer_debts ADD COLUMN created_by INT NULL",
        "CREATE TABLE IF NOT EXISTS system_updates (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, version VARCHAR(50) NULL, content TEXT NOT NULL, urgency ENUM('normal', 'important', 'critical') NOT NULL DEFAULT 'important', is_active TINYINT(1) NOT NULL DEFAULT 1, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_system_updates_active (is_active, id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS offers (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(180) NOT NULL, barcode VARCHAR(40) NULL UNIQUE, price_before DECIMAL(12,2) NOT NULL DEFAULT 0.00, price_after DECIMAL(12,2) NOT NULL DEFAULT 0.00, start_date DATE NOT NULL, end_date DATE NOT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, notes TEXT NULL, created_by INT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_offers_active_dates (is_active, start_date, end_date), CONSTRAINT fk_offers_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS offer_items (id INT AUTO_INCREMENT PRIMARY KEY, offer_id INT NOT NULL, item_type ENUM('product', 'recipe') NOT NULL DEFAULT 'product', product_id INT NULL, quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000, bottle_product_id INT NULL, recipe_name VARCHAR(180) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_offer_items_offer (offer_id), CONSTRAINT fk_offer_items_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE, CONSTRAINT fk_offer_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL, CONSTRAINT fk_offer_items_bottle FOREIGN KEY (bottle_product_id) REFERENCES products(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS offer_item_components (id INT AUTO_INCREMENT PRIMARY KEY, offer_item_id INT NOT NULL, perfume_product_id INT NOT NULL, grams DECIMAL(12,3) NOT NULL DEFAULT 0.000, INDEX idx_offer_item_components_item (offer_item_id), CONSTRAINT fk_offer_comp_item FOREIGN KEY (offer_item_id) REFERENCES offer_items(id) ON DELETE CASCADE, CONSTRAINT fk_offer_comp_perfume FOREIGN KEY (perfume_product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "ALTER TABLE invoice_lines MODIFY COLUMN line_type ENUM('product','custom_recipe','saved_recipe','offer') NOT NULL",
        "ALTER TABLE invoice_lines ADD COLUMN offer_id INT NULL",
    ];
    // Migration: branch_targets from target_month to target_date
    try {
        $stmt = $db->query("SHOW COLUMNS FROM branch_targets LIKE 'target_month'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE branch_targets CHANGE COLUMN target_month target_date DATE NOT NULL");
            // Drop old index (try multiple syntaxes for compatibility)
            try { $db->exec("DROP INDEX uq_target_month ON branch_targets"); } catch (Throwable $ex) {}
            try { $db->exec("DROP INDEX IF EXISTS uq_target_month ON branch_targets"); } catch (Throwable $ex) {}
            // Add new unique index
            try { $db->exec("ALTER TABLE branch_targets ADD UNIQUE INDEX uq_target_date (location_id, target_date)"); } catch (Throwable $ex) {}
            // Convert existing month data to first day of month
            $db->exec("UPDATE branch_targets SET target_date = STR_TO_DATE(CONCAT(target_date, '-01'), '%Y-%m-%d')");
        }
    } catch (Throwable $e) {
    }
    // Migration: add bottle_product_id to formula_defaults
    try {
        $stmt = $db->query("SHOW COLUMNS FROM formula_defaults LIKE 'bottle_product_id'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE formula_defaults ADD COLUMN bottle_product_id INT NULL AFTER id");
        }
    } catch (Throwable $e) {
    }
    // Migration: add refund_paid to return_invoices
    try {
        $stmt = $db->query("SHOW COLUMNS FROM return_invoices LIKE 'refund_paid'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE return_invoices ADD COLUMN refund_paid DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER amount");
            // Set existing records: assume full refund was paid
            $db->exec("UPDATE return_invoices SET refund_paid = amount WHERE refund_paid = 0");
        }
    } catch (Throwable $e) {
    }
    // Migration: create salary_payments table
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS salary_payments (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          payroll_month CHAR(7) NOT NULL,
          basic_salary DECIMAL(12,2) NOT NULL,
          commission DECIMAL(12,2) NOT NULL,
          additions DECIMAL(12,2) NOT NULL,
          deductions DECIMAL(12,2) NOT NULL,
          net_payout DECIMAL(12,2) NOT NULL,
          payment_method ENUM('cash','instapay','vodafone_cash','bank_transfer') NOT NULL DEFAULT 'cash',
          location_id INT NULL,
          paid_by INT NOT NULL,
          paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          UNIQUE KEY uq_user_month (user_id, payroll_month),
          CONSTRAINT fk_sal_pay_user FOREIGN KEY (user_id) REFERENCES users(id),
          CONSTRAINT fk_sal_pay_location FOREIGN KEY (location_id) REFERENCES locations(id),
          CONSTRAINT fk_sal_pay_admin FOREIGN KEY (paid_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    } catch (Throwable $e) {
    }
    // Migration: support exchange refund_method and credit in payments
    try {
        $db->exec("ALTER TABLE return_invoices MODIFY COLUMN refund_method ENUM('cash','instapay','bank_transfer','vodafone_cash','customer_credit','exchange') NOT NULL DEFAULT 'cash'");
    } catch (Throwable $e) {
    }
    try {
        $db->exec("ALTER TABLE payments MODIFY COLUMN method ENUM('cash','instapay','bank_transfer','vodafone_cash','salary_deduction','exchange_credit','customer_credit') NOT NULL DEFAULT 'cash'");
    } catch (Throwable $e) {
    }
    foreach ($updates as $sql) {
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (!str_contains($msg, 'Duplicate column') && 
                !str_contains($msg, 'already exists') && 
                !str_contains($msg, 'Duplicate key') && 
                !str_contains($msg, 'Duplicate entry') && 
                !str_contains($msg, 'Multiple primary key') && 
                !str_contains($msg, 'foreign key constraint') && 
                !str_contains($msg, 'Duplicate foreign key')) {
                throw $e;
            }
        }
    }
    try {
        $db->exec("UPDATE customers c
            JOIN (
                SELECT customer_id, MIN(location_id) AS location_id
                FROM invoices
                WHERE customer_id IS NOT NULL AND location_id IS NOT NULL
                GROUP BY customer_id
            ) x ON x.customer_id = c.id
            SET c.location_id = x.location_id
            WHERE c.location_id IS NULL");
    } catch (Throwable $e) {
        // Safe fallback for installations where invoices/customers are not ready yet.
    }
    foreach ([
        "ALTER TABLE customers ADD CONSTRAINT fk_customers_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL",
        "ALTER TABLE customers ADD CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL",
    ] as $sql) {
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
        }
    }
    try {
        $count = (int) $db->query('SELECT COUNT(*) FROM target_commission_tiers')->fetchColumn();
        if ($count === 0) {
            $stmt = $db->prepare('INSERT INTO target_commission_tiers (min_sales, max_sales, commission_amount, sort_order, is_active) VALUES (?, ?, ?, ?, 1)');
            $defaults = [
                [5000, 6000, 50],
                [6000, 8000, 100],
                [8000, 9000, 150],
                [9000, null, 200],
            ];
            foreach ($defaults as $idx => $tier) {
                $stmt->execute([$tier[0], $tier[1], $tier[2], $idx + 1]);
            }
        }
    } catch (Throwable $e) {
        // Safe fallback if the table could not be created in older installations.
    }
    try {
        $db->exec("DELETE f1 FROM formula_defaults f1 INNER JOIN formula_defaults f2 ON f1.perfume_family = f2.perfume_family AND COALESCE(f1.quality_grade, '') = COALESCE(f2.quality_grade, '') AND f1.bottle_size_ml = f2.bottle_size_ml WHERE f1.id > f2.id");
        $db->exec("UPDATE formula_defaults SET quality_grade = '' WHERE quality_grade IS NULL");
        $db->exec("ALTER TABLE formula_defaults MODIFY COLUMN quality_grade VARCHAR(10) NOT NULL DEFAULT ''");
    } catch (Throwable $e) {
        // Safe fallback if table doesn't exist yet or columns don't match
    }
    try {
        $column = $db->query("SHOW COLUMNS FROM formula_defaults LIKE 'default_grams'")->fetch();
        if ($column) {
            $db->exec("DELETE FROM formula_defaults WHERE (perfume_family = 'oriental' AND quality_grade = 'A' AND bottle_size_ml = 50 AND default_grams = 12.000) OR (perfume_family = 'oriental' AND quality_grade = 'A+' AND bottle_size_ml = 50 AND default_grams = 13.000) OR (perfume_family = 'french' AND quality_grade = '' AND bottle_size_ml = 50 AND default_grams = 15.000) OR (perfume_family = 'oriental' AND quality_grade = 'A' AND bottle_size_ml = 100 AND default_grams = 24.000) OR (perfume_family = 'french' AND quality_grade = '' AND bottle_size_ml = 100 AND default_grams = 30.000)");
        }
    } catch (Throwable $e) {
        // Safe fallback if table doesn't exist yet or query fails
    }
    // Migration: Update formula_defaults table for specific perfume product and price
    try {
        // Add columns if they do not exist
        $stmt = $db->query("SHOW COLUMNS FROM formula_defaults LIKE 'perfume_product_id'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE formula_defaults ADD COLUMN perfume_product_id INT NULL AFTER bottle_product_id");
            $db->exec("ALTER TABLE formula_defaults ADD CONSTRAINT fk_formula_perfume FOREIGN KEY (perfume_product_id) REFERENCES products(id) ON DELETE CASCADE");
        }
        
        $stmt = $db->query("SHOW COLUMNS FROM formula_defaults LIKE 'price'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE formula_defaults ADD COLUMN price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER default_grams");
        }
        
        // Make perfume_family nullable
        $db->exec("ALTER TABLE formula_defaults MODIFY COLUMN perfume_family VARCHAR(40) NULL");
        $db->exec("ALTER TABLE formula_defaults MODIFY COLUMN quality_grade VARCHAR(10) NULL DEFAULT ''");
        
        // Drop old unique key if exists
        try {
            $db->exec("ALTER TABLE formula_defaults DROP KEY uq_formula");
        } catch (Throwable $e) {}
        
        // Add new unique key if not exists
        try {
            $db->exec("ALTER TABLE formula_defaults ADD UNIQUE KEY uq_bottle_perfume (bottle_product_id, perfume_product_id)");
        } catch (Throwable $e) {}
    } catch (Throwable $e) {
        // Safe fallback
    }
    // Migration: shift_closures — drop unique key to allow multiple shifts per day
    try {
        $stmt = $db->query("SHOW INDEX FROM shift_closures WHERE Key_name = 'uq_shift_user_day'");
        if ($stmt->fetch()) {
            $db->exec("ALTER TABLE shift_closures DROP INDEX uq_shift_user_day");
        }
    } catch (Throwable $e) {}
    // Migration: shift_closures — add shift_start_time column
    try {
        $stmt = $db->query("SHOW COLUMNS FROM shift_closures LIKE 'shift_start_time'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE shift_closures ADD COLUMN shift_start_time DATETIME NULL AFTER shift_date");
        }
    } catch (Throwable $e) {}
    // Migration: shift_closures — add detailed financial columns
    $shiftClosureColumns = [
        'total_cash_sales'        => "ALTER TABLE shift_closures ADD COLUMN total_cash_sales DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER difference",
        'total_instapay'          => "ALTER TABLE shift_closures ADD COLUMN total_instapay DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_cash_sales",
        'total_vodafone_cash'     => "ALTER TABLE shift_closures ADD COLUMN total_vodafone_cash DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_instapay",
        'total_returns_cash'      => "ALTER TABLE shift_closures ADD COLUMN total_returns_cash DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_vodafone_cash",
        'total_expenses_cash'     => "ALTER TABLE shift_closures ADD COLUMN total_expenses_cash DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_returns_cash",
        'net_cash'                => "ALTER TABLE shift_closures ADD COLUMN net_cash DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_expenses_cash",
        'total_invoices'          => "ALTER TABLE shift_closures ADD COLUMN total_invoices INT NOT NULL DEFAULT 0 AFTER net_cash",
        'cash_transferred'        => "ALTER TABLE shift_closures ADD COLUMN cash_transferred DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_invoices",
        'cash_transfer_action'    => "ALTER TABLE shift_closures ADD COLUMN cash_transfer_action ENUM('all','partial','none') NOT NULL DEFAULT 'none' AFTER cash_transferred",
        'cash_remaining_in_drawer' => "ALTER TABLE shift_closures ADD COLUMN cash_remaining_in_drawer DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER cash_transfer_action",
        'shift_start_user_id'     => "ALTER TABLE shift_closures ADD COLUMN shift_start_user_id INT NULL AFTER shift_start_time",
    ];
    foreach ($shiftClosureColumns as $colName => $sql) {
        try {
            $stmt = $db->query("SHOW COLUMNS FROM shift_closures LIKE '$colName'");
            if (!$stmt->fetch()) {
                $db->exec($sql);
            }
        } catch (Throwable $e) {}
    }
    try {
        $count = (int) $db->query('SELECT COUNT(*) FROM role_permissions')->fetchColumn();
        if ($count === 0) {
            $roles_by_code = [];
            foreach ($db->query('SELECT id, code FROM roles')->fetchAll() as $r) {
                $roles_by_code[$r['code']] = (int) $r['id'];
            }
            
            $default_perms = [
                'admin' => [
                    'pos', 'invoices', 'invoices_notes', 'returns',
                    'products_view', 'products_add', 'products_edit', 'products_delete',
                    'recipes_view', 'recipes_add', 'recipes_edit',
                    'inventory_view', 'inventory_adjust', 'inventory_add', 'transfers_supply', 'transfers_branch',
                    'customers_view', 'customers_add', 'customers_edit', 'customers_pay_debt',
                    'users_view', 'users_add', 'users_permissions',
                    'attendance', 'shifts_close', 'shifts_record', 'targets',
                    'expenses_view', 'expenses_add', 'branch_cash_transfers', 'manager_treasury', 'financial_ledger',
                    'suppliers_view', 'suppliers_add',
                    'reports_sales_by_location', 'reports_sales_by_payment', 'reports_sales_by_user', 
                    'reports_top_products', 'reports_perfume_usage', 'reports_new_customers', 
                    'online_orders', 'backup', 'settings', 'audit'
                ],
                'branch_manager' => [
                    'pos', 'invoices', 'invoices_notes', 'returns',
                    'inventory_view', 'inventory_adjust', 'transfers_supply', 'transfers_branch',
                    'customers_view', 'customers_add', 'customers_edit', 'customers_pay_debt',
                    'attendance', 'shifts_close', 'shifts_record', 'targets', 'online_orders',
                    'reports_sales_by_location', 'reports_sales_by_payment', 'reports_new_customers', 'branch_cash_transfers', 'financial_ledger'
                ],
                'cashier' => [
                    'pos', 'attendance', 'shifts_close', 'invoices'
                ],
                'warehouse_keeper' => [
                    'products_view', 'products_add', 'products_edit', 'products_delete',
                    'inventory_view', 'inventory_adjust', 'inventory_add', 'transfers_supply', 'transfers_branch',
                    'suppliers_view', 'suppliers_add', 'attendance'
                ]
            ];
            
            $stmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_code) VALUES (?, ?)');
            foreach ($default_perms as $role_code => $perms) {
                if (isset($roles_by_code[$role_code])) {
                    $role_id = $roles_by_code[$role_code];
                    foreach ($perms as $perm) {
                        $stmt->execute([$role_id, $perm]);
                    }
                }
            }
        } else {
            // Run dynamic migration of old permissions to new granular ones if any exist
            $migrations = [
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'products_view' FROM role_permissions WHERE permission_code = 'products'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'products_add' FROM role_permissions WHERE permission_code = 'products'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'products_edit' FROM role_permissions WHERE permission_code = 'products'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'products_delete' FROM role_permissions WHERE permission_code = 'products'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'recipes_view' FROM role_permissions WHERE permission_code = 'recipes'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'recipes_add' FROM role_permissions WHERE permission_code = 'recipes'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'recipes_edit' FROM role_permissions WHERE permission_code = 'recipes'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'inventory_view' FROM role_permissions WHERE permission_code = 'inventory'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'inventory_adjust' FROM role_permissions WHERE permission_code = 'inventory'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'customers_view' FROM role_permissions WHERE permission_code = 'customers'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'customers_add' FROM role_permissions WHERE permission_code = 'customers'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'customers_edit' FROM role_permissions WHERE permission_code = 'customers'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'customers_pay_debt' FROM role_permissions WHERE permission_code = 'customers'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'users_view' FROM role_permissions WHERE permission_code = 'users'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'users_add' FROM role_permissions WHERE permission_code = 'users'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'users_permissions' FROM role_permissions WHERE permission_code = 'users'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'expenses_view' FROM role_permissions WHERE permission_code = 'expenses'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'expenses_add' FROM role_permissions WHERE permission_code = 'expenses'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'suppliers_view' FROM role_permissions WHERE permission_code = 'suppliers'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'suppliers_add' FROM role_permissions WHERE permission_code = 'suppliers'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'branch_cash_transfers' FROM role_permissions WHERE permission_code IN ('expenses_view', 'reports_sales_by_location')",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'manager_treasury' FROM roles WHERE code = 'admin'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'financial_ledger' FROM role_permissions WHERE permission_code IN ('expenses_view', 'reports_sales_by_location')",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'audit' FROM role_permissions WHERE permission_code = 'backup'",
                "DELETE FROM role_permissions WHERE permission_code IN ('products', 'recipes', 'inventory', 'customers', 'users', 'expenses', 'suppliers')",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'shifts_close' FROM role_permissions WHERE permission_code = 'shifts'",
                "INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT role_id, 'shifts_record' FROM role_permissions WHERE permission_code = 'shifts'",
                "DELETE FROM role_permissions WHERE permission_code = 'shifts'"
            ];
            foreach ($migrations as $sql) {
                $db->exec($sql);
            }
        }
    } catch (Throwable $e) {
        // Safe fallback
    }
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        return;
    }
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        if (str_starts_with(strtoupper($statement), 'CREATE TABLE') || str_starts_with(strtoupper($statement), 'INSERT IGNORE')) {
            $db->exec($statement);
        }
    }
}
function install_database(): void
{
    $server = pdo(false);
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $db = pdo(true);
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('لم يتم العثور على ملف schema.sql');
    }
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
        $db->exec($statement);
    }
    ensure_schema_updates();
    $roleId = (int) $db->query("SELECT id FROM roles WHERE code = 'admin'")->fetchColumn();
    $exists = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
    $exists->execute(['admin']);
    if ((int) $exists->fetchColumn() === 0) {
        $stmt = $db->prepare('INSERT INTO users (name, username, password_hash, role_id, location_id) VALUES (?, ?, ?, ?, NULL)');
        $stmt->execute(['مدير النظام', 'admin', password_hash('admin123', PASSWORD_DEFAULT), $roleId]);
    }
}
function reset_database(): void
{
    $db = pdo(true);
    $db->beginTransaction();
    try {
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $db->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
        }
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    install_database();
}
