-- Database Index Optimizations to significantly improve page loading speeds
-- This script applies the missing indexes to existing tables.

-- Products Indexes
ALTER TABLE products ADD INDEX idx_products_active_type (is_active, type);
ALTER TABLE products ADD INDEX idx_products_created_at (created_at);

-- Inventory Movements Indexes
ALTER TABLE inventory_movements ADD INDEX idx_movements_created_at (created_at);

-- Customers Indexes
ALTER TABLE customers ADD INDEX idx_customers_is_active (is_active);
ALTER TABLE customers ADD INDEX idx_customers_created_at (created_at);

-- Invoices Indexes
ALTER TABLE invoices ADD INDEX idx_invoices_created_at (created_at);
ALTER TABLE invoices ADD INDEX idx_invoices_status (status);

-- Customer Debts Indexes
ALTER TABLE customer_debts ADD INDEX idx_debts_status (status);

-- Return Invoices Indexes
ALTER TABLE return_invoices ADD INDEX idx_returns_created_at (created_at);

-- Expenses Indexes
ALTER TABLE expenses ADD INDEX idx_expenses_date (expense_date);

-- Online Orders Indexes
ALTER TABLE online_orders ADD INDEX idx_online_orders_created_at (created_at);
ALTER TABLE online_orders ADD INDEX idx_online_orders_status (status);

SELECT 'Indexes have been successfully created! The system should load much faster now.' as result;
