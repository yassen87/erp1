CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  type ENUM('warehouse','branch','online') NOT NULL,
  qr_code VARCHAR(120) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  geo_radius_m INT NOT NULL DEFAULT 100,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT NOT NULL,
  location_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  basic_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
  CONSTRAINT fk_users_location FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(80) NULL UNIQUE,
  barcode VARCHAR(40) NULL UNIQUE,
  name VARCHAR(180) NOT NULL,
  type ENUM('bottle','perfume_gram','recipe','fixed') NOT NULL,
  unit ENUM('unit','gram') NOT NULL,
  sale_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  cost_price DECIMAL(12,2) NULL,
  min_stock DECIMAL(14,3) NOT NULL DEFAULT 0,
  image_path VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_active_type (is_active, type),
  INDEX idx_products_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_bottle_details (
  product_id INT PRIMARY KEY,
  size_ml INT NOT NULL,
  CONSTRAINT fk_bottle_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_perfume_details (
  product_id INT PRIMARY KEY,
  perfume_family VARCHAR(40) NOT NULL,
  quality_grade VARCHAR(10) NULL,
  price_per_gram DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_perfume_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_headers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NULL,
  name VARCHAR(180) NOT NULL,
  bottle_product_id INT NOT NULL,
  default_sale_price DECIMAL(12,2) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_recipe_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_recipe_bottle FOREIGN KEY (bottle_product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_components (
  id INT AUTO_INCREMENT PRIMARY KEY,
  recipe_id INT NOT NULL,
  perfume_product_id INT NOT NULL,
  grams DECIMAL(12,3) NOT NULL,
  CONSTRAINT fk_recipe_component_recipe FOREIGN KEY (recipe_id) REFERENCES recipe_headers(id) ON DELETE CASCADE,
  CONSTRAINT fk_recipe_component_perfume FOREIGN KEY (perfume_product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS formula_defaults (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bottle_product_id INT NULL,
  perfume_product_id INT NULL,
  perfume_family VARCHAR(40) NULL,
  quality_grade VARCHAR(10) NULL DEFAULT '',
  bottle_size_ml INT NOT NULL,
  default_grams DECIMAL(12,3) NOT NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  UNIQUE KEY uq_bottle_perfume (bottle_product_id, perfume_product_id),
  CONSTRAINT fk_formula_bottle FOREIGN KEY (bottle_product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_formula_perfume FOREIGN KEY (perfume_product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_balances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  location_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_inventory_balance (location_id, product_id),
  CONSTRAINT fk_balance_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_balance_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  location_id INT NOT NULL,
  product_id INT NOT NULL,
  movement_type ENUM('initial','sale','manual_adjustment','transfer_future','transfer_adjust','return_future') NOT NULL,
  quantity_delta DECIMAL(14,3) NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  reference_type VARCHAR(40) NULL,
  reference_id INT NULL,
  notes TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_movements_created_at (created_at),
  CONSTRAINT fk_movement_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_movement_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_movement_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) NULL UNIQUE,
  source ENUM('offline','online') NOT NULL DEFAULT 'offline',
  loyalty_points DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  birthdate DATE NULL,
  location_id INT NULL,
  created_by INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_customers_is_active (is_active),
  INDEX idx_customers_created_at (created_at),
  CONSTRAINT fk_customers_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
  CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(60) NOT NULL UNIQUE,
  location_id INT NOT NULL,
  user_id INT NOT NULL,
  customer_id INT NULL,
  status ENUM('completed','void_future') NOT NULL DEFAULT 'completed',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_type ENUM('amount','percent') NULL,
  discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  due_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_invoices_created_at (created_at),
  INDEX idx_invoices_status (status),
  CONSTRAINT fk_invoice_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_invoice_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_invoice_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_lines (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  line_type ENUM('product','custom_recipe','saved_recipe') NOT NULL,
  product_id INT NULL,
  recipe_id INT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  discount_type ENUM('amount','percent') NULL,
  discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_line_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_line_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_line_recipe FOREIGN KEY (recipe_id) REFERENCES recipe_headers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_line_components (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_line_id INT NOT NULL,
  component_product_id INT NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  unit_cost DECIMAL(12,2) NULL,
  CONSTRAINT fk_component_line FOREIGN KEY (invoice_line_id) REFERENCES invoice_lines(id) ON DELETE CASCADE,
  CONSTRAINT fk_component_product FOREIGN KEY (component_product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NULL,
  customer_id INT NULL,
  payment_type ENUM('invoice_payment','debt_payment') NOT NULL,
  method ENUM('cash','instapay','bank_transfer','vodafone_cash') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  receipt_path VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payment_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  CONSTRAINT fk_payment_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_payment_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_debts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  invoice_id INT NOT NULL,
  original_amount DECIMAL(12,2) NOT NULL,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  remaining_amount DECIMAL(12,2) NOT NULL,
  status ENUM('open','paid') NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  closed_at DATETIME NULL,
  INDEX idx_debts_status (status),
  CONSTRAINT fk_debt_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_debt_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_number VARCHAR(60) NOT NULL UNIQUE,
  from_location_id INT NOT NULL,
  to_location_id INT NOT NULL,
  status ENUM('pending','received','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  sender_name VARCHAR(120) NULL,
  receiver_name VARCHAR(120) NULL,
  transfer_date DATE NOT NULL DEFAULT CURRENT_DATE,
  created_by INT NOT NULL,
  received_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_at DATETIME NULL,
  CONSTRAINT fk_transfer_from FOREIGN KEY (from_location_id) REFERENCES locations(id),
  CONSTRAINT fk_transfer_to FOREIGN KEY (to_location_id) REFERENCES locations(id),
  CONSTRAINT fk_transfer_user FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_transfer_receiver FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_transfer_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  CONSTRAINT fk_transfer_item_transfer FOREIGN KEY (transfer_id) REFERENCES inventory_transfers(id) ON DELETE CASCADE,
  CONSTRAINT fk_transfer_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS return_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  original_invoice_id INT NOT NULL,
  return_number VARCHAR(60) NOT NULL UNIQUE,
  refund_method ENUM('cash','instapay','bank_transfer','vodafone_cash','customer_credit') NOT NULL DEFAULT 'cash',
  amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  reason TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_returns_created_at (created_at),
  CONSTRAINT fk_return_invoice FOREIGN KEY (original_invoice_id) REFERENCES invoices(id),
  CONSTRAINT fk_return_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shift_closures (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  location_id INT NOT NULL,
  shift_date DATE NOT NULL,
  expected_cash DECIMAL(12,2) NOT NULL,
  actual_cash DECIMAL(12,2) NOT NULL,
  difference DECIMAL(12,2) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shift_user_day (user_id, location_id, shift_date),
  CONSTRAINT fk_shift_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_shift_location FOREIGN KEY (location_id) REFERENCES locations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS manager_collections (
  id INT AUTO_INCREMENT PRIMARY KEY,
  collection_number VARCHAR(60) NOT NULL UNIQUE,
  location_id INT NOT NULL,
  method ENUM('cash','instapay','vodafone_cash') NOT NULL,
  source_type ENUM('auto_payment','branch_transfer') NOT NULL DEFAULT 'auto_payment',
  source_id BIGINT NULL,
  amount DECIMAL(12,2) NOT NULL,
  collection_date DATE NOT NULL,
  status ENUM('pending','received','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_by INT NULL,
  received_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_at DATETIME NULL,
  UNIQUE KEY uq_manager_collection_source (source_type, source_id, method),
  INDEX idx_manager_collections_location (location_id),
  INDEX idx_manager_collections_date (collection_date),
  INDEX idx_manager_collections_status (status),
  CONSTRAINT fk_manager_collection_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_manager_collection_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_manager_collection_receiver FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  location_id INT NOT NULL,
  action ENUM('check_in','check_out') NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  source ENUM('qr','manual') NOT NULL DEFAULT 'qr',
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_att_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_att_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branch_targets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  location_id INT NOT NULL,
  target_date DATE NOT NULL,
  target_amount DECIMAL(12,2) NOT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_target_date (location_id, target_date),
  CONSTRAINT fk_target_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_target_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expense_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  location_id INT NULL,
  amount DECIMAL(12,2) NOT NULL,
  expense_date DATE NOT NULL,
  notes TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expenses_date (expense_date),
  CONSTRAINT fk_expense_category FOREIGN KEY (category_id) REFERENCES expense_categories(id),
  CONSTRAINT fk_expense_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_expense_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  phone VARCHAR(60) NULL,
  product_type VARCHAR(120) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT NOT NULL,
  invoice_number VARCHAR(80) NULL,
  total DECIMAL(12,2) NOT NULL,
  paid DECIMAL(12,2) NOT NULL DEFAULT 0,
  due DECIMAL(12,2) NOT NULL DEFAULT 0,
  invoice_date DATE NOT NULL,
  notes TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_supplier_invoice_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  CONSTRAINT fk_supplier_invoice_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  invoice_id INT NULL,
  points_delta DECIMAL(12,2) NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_loyalty_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_loyalty_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(60) NOT NULL UNIQUE,
  customer_id INT NOT NULL,
  status ENUM('new','preparing','shipped','delivered','cancelled') NOT NULL DEFAULT 'new',
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('cash','instapay','bank_transfer','vodafone_cash') NOT NULL DEFAULT 'cash',
  discount_type ENUM('amount','percent') NULL,
  discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_online_orders_created_at (created_at),
  INDEX idx_online_orders_status (status),
  CONSTRAINT fk_online_customer FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS online_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_online_item_order FOREIGN KEY (order_id) REFERENCES online_orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_online_item_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id INT NULL,
  details TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(80) PRIMARY KEY,
  setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO roles (code, name) VALUES
('admin', 'الأدمن العام'),
('branch_manager', 'مدير الفرع'),
('cashier', 'الكاشير / الموظف'),
('warehouse_keeper', 'أمين المخزن');

INSERT IGNORE INTO locations (id, name, type, is_active) VALUES
(1, 'المخزن الرئيسي', 'warehouse', 1),
(2, 'ام خنان', 'branch', 1),
(3, 'المنوات', 'branch', 1),
(4, 'الأونلاين', 'online', 1);

INSERT IGNORE INTO expense_categories (name) VALUES
('إيجار'), ('كهرباء'), ('رواتب'), ('مستلزمات'), ('صيانة'), ('تسويق'), ('متنوعات');

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('loyalty_rate', '1'),
('debt_alert_days', '14'),
('target_midmonth_threshold', '50'),
('allow_negative_stock', '0');

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  permission_code VARCHAR(80) NOT NULL,
  PRIMARY KEY (role_id, permission_code),
  CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'pos' FROM roles WHERE code IN ('admin', 'branch_manager', 'cashier');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'invoices' FROM roles WHERE code IN ('admin', 'branch_manager', 'cashier');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'invoices_notes' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'returns' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'products_view' FROM roles WHERE code IN ('admin', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'products_add' FROM roles WHERE code IN ('admin', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'products_edit' FROM roles WHERE code IN ('admin', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'products_delete' FROM roles WHERE code IN ('admin', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'recipes_view' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'recipes_add' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'recipes_edit' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'inventory_view' FROM roles WHERE code IN ('admin', 'branch_manager', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'inventory_adjust' FROM roles WHERE code IN ('admin', 'branch_manager', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'inventory_add' FROM roles WHERE code IN ('admin', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'transfers_supply' FROM roles WHERE code IN ('admin', 'branch_manager', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'transfers_branch' FROM roles WHERE code IN ('admin', 'branch_manager', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'customers_view' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'customers_add' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'customers_edit' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'customers_pay_debt' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'users_view' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'users_add' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'users_permissions' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'attendance' FROM roles WHERE code IN ('admin', 'branch_manager', 'cashier', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'shifts_close' FROM roles WHERE code IN ('admin', 'branch_manager', 'cashier');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'shifts_record' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'targets' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'expenses_view' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'expenses_add' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'suppliers_view' FROM roles WHERE code IN ('admin', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'suppliers_add' FROM roles WHERE code IN ('admin', 'warehouse_keeper');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'reports_sales_by_location' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'reports_sales_by_payment' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'reports_sales_by_user' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'reports_top_products' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'reports_perfume_usage' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'reports_new_customers' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'online_orders' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'backup' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'settings' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'audit' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'branch_cash_transfers' FROM roles WHERE code IN ('admin', 'branch_manager');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'manager_treasury' FROM roles WHERE code IN ('admin');
INSERT IGNORE INTO role_permissions (role_id, permission_code) SELECT id, 'financial_ledger' FROM roles WHERE code IN ('admin', 'branch_manager');

CREATE TABLE IF NOT EXISTS wasted_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  location_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity DECIMAL(14,3) NOT NULL,
  reason VARCHAR(255) NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_waste_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_waste_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_waste_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS branch_cash_transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_number VARCHAR(60) NOT NULL UNIQUE,
  location_id INT NOT NULL,
  method ENUM('cash','instapay','vodafone_cash') NOT NULL DEFAULT 'cash',
  amount DECIMAL(12,2) NOT NULL,
  transfer_date DATE NOT NULL DEFAULT CURRENT_DATE,
  status ENUM('pending','received','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_by INT NOT NULL,
  received_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  received_at DATETIME NULL,
  CONSTRAINT fk_cash_transfer_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_cash_transfer_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_cash_transfer_receiver FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS financial_ledger (
  id INT AUTO_INCREMENT PRIMARY KEY,
  location_id INT NULL,
  direction ENUM('in','out') NOT NULL,
  method ENUM('cash','instapay','vodafone_cash') NOT NULL DEFAULT 'cash',
  amount DECIMAL(12,2) NOT NULL,
  source_type VARCHAR(40) NOT NULL,
  source_id INT NULL,
  description VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_financial_ledger_date (created_at),
  INDEX idx_financial_ledger_location (location_id),
  CONSTRAINT fk_financial_ledger_location FOREIGN KEY (location_id) REFERENCES locations(id),
  CONSTRAINT fk_financial_ledger_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS target_commission_tiers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  min_sales DECIMAL(12,2) NOT NULL,
  max_sales DECIMAL(12,2) NULL,
  commission_amount DECIMAL(12,2) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_target_tier_min (min_sales)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO target_commission_tiers (min_sales, max_sales, commission_amount, sort_order, is_active) VALUES
(5000.00, 6000.00, 50.00, 1, 1),
(6000.00, 8000.00, 100.00, 2, 1),
(8000.00, 9000.00, 150.00, 3, 1),
(9000.00, NULL, 200.00, 4, 1);

CREATE TABLE IF NOT EXISTS payroll_adjustments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  adjustment_month CHAR(7) NOT NULL,
  type ENUM('bonus','deduction') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  reason VARCHAR(255) NULL,
  created_by INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_payroll_adjust_user_month (user_id, adjustment_month),
  CONSTRAINT fk_payroll_adjust_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_payroll_adjust_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_overrides (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  payroll_month CHAR(7) NOT NULL,
  commission_total DECIMAL(12,2) NULL,
  additions_total DECIMAL(12,2) NULL,
  deductions_total DECIMAL(12,2) NULL,
  notes TEXT NULL,
  updated_by INT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payroll_override (user_id, payroll_month),
  CONSTRAINT fk_payroll_override_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_payroll_override_updater FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  barcode VARCHAR(40) NULL UNIQUE,
  price_before DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  price_after DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_offers_active_dates (is_active, start_date, end_date),
  CONSTRAINT fk_offers_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  offer_id INT NOT NULL,
  item_type ENUM('product', 'recipe') NOT NULL DEFAULT 'product',
  product_id INT NULL,
  quantity DECIMAL(12,3) NOT NULL DEFAULT 1.000,
  bottle_product_id INT NULL,
  recipe_name VARCHAR(180) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_offer_items_offer (offer_id),
  CONSTRAINT fk_offer_items_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_offer_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT fk_offer_items_bottle FOREIGN KEY (bottle_product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS offer_item_components (
  id INT AUTO_INCREMENT PRIMARY KEY,
  offer_item_id INT NOT NULL,
  perfume_product_id INT NOT NULL,
  grams DECIMAL(12,3) NOT NULL DEFAULT 0.000,
  INDEX idx_offer_item_components_item (offer_item_id),
  CONSTRAINT fk_offer_comp_item FOREIGN KEY (offer_item_id) REFERENCES offer_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_offer_comp_perfume FOREIGN KEY (perfume_product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

