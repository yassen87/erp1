<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null) {
        return $user;
    }

    $stmt = pdo()->prepare('SELECT u.*, r.code AS role_code, r.name AS role_name, l.name AS location_name FROM users u JOIN roles r ON r.id = u.role_id LEFT JOIN locations l ON l.id = u.location_id WHERE u.id = ? AND u.is_active = 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login');
    }
    return $user;
}

function has_role(string ...$roles): bool
{
    $user = current_user();
    return $user !== null && in_array($user['role_code'], $roles, true);
}

function has_permission(string $permission): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    if ($user['role_code'] === 'admin') {
        return true;
    }

    $map = [
        'products'           => 'products_view',
        'product_create'     => 'products_add',
        'product_edit'       => 'products_edit',
        'offers'             => 'products_view',
        'offer_create'       => 'products_add',
        'offer_edit'         => 'products_edit',
        'recipes'            => 'recipes_view',
        'formula_defaults'   => 'recipes_view',
        'inventory'          => 'inventory_view',
        'branch_inventory'   => 'inventory_view',
        'product_trace'      => 'inventory_view',
        'waste'              => 'inventory_view',
        'transfers_branch_create' => 'transfers_branch',
        'transfers_supply_create' => 'transfers_supply',
        'returns'            => 'invoices',
        'customers'          => 'customers_view',
        'customers_debts'    => 'customers_view',
        'call_center'        => 'customers_view',
        'customer_birthdays' => 'customers_view',
        'customer_surveys'   => 'customers_view',
        'users'              => 'users_view',
        'payroll'            => 'users_view',
        'expenses'           => 'expenses_view',
        'branch_cash_transfers' => 'branch_cash_transfers',
        'manager_treasury'   => 'manager_treasury',
        'financial_ledger'   => 'financial_ledger',
        'suppliers'          => 'suppliers_view',
        'customer_view'      => 'customers_view',
        'quick_add_customer' => 'customers_add',
        'print_barcode'      => 'products_view',
        'invoice_view'       => 'invoices',
        'shifts'             => 'shifts_close',
        'shifts_record'      => 'shifts_record',
        'attendance_log'     => 'attendance',
        'locations'          => 'settings',
        'system_updates'     => 'settings',
    ];
    if (isset($map[$permission])) {
        $permission = $map[$permission];
    }

    if ($permission === 'dashboard') {
        return true;
    }

    if (!isset($_SESSION['permissions'])) {
        $db = pdo();
        $stmt = $db->prepare('SELECT permission_code FROM role_permissions WHERE role_id = ?');
        $stmt->execute([$user['role_id']]);
        $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    if ($permission === 'branch_cash_transfers') {
        return in_array('branch_cash_transfers', $_SESSION['permissions'], true)
            || in_array('expenses_view', $_SESSION['permissions'], true)
            || in_array('reports_sales_by_location', $_SESSION['permissions'], true);
    }
    if ($permission === 'financial_ledger') {
        return in_array('financial_ledger', $_SESSION['permissions'], true)
            || in_array('expenses_view', $_SESSION['permissions'], true)
            || in_array('reports_sales_by_location', $_SESSION['permissions'], true);
    }
    return in_array($permission, $_SESSION['permissions'], true);
}

function current_user_id(): ?int
{
    $user = current_user();
    return $user && !empty($user['id']) ? (int) $user['id'] : null;
}

function current_user_location_id(): ?int
{
    $user = current_user();
    return $user && !empty($user['location_id']) ? (int) $user['location_id'] : null;
}

function current_user_location_type(): ?string
{
    $user = current_user();
    if (!$user || empty($user['location_id'])) {
        return null;
    }
    $stmt = pdo()->prepare('SELECT type FROM locations WHERE id = ? AND is_active = 1');
    $stmt->execute([(int) $user['location_id']]);
    $type = $stmt->fetchColumn();
    return $type === false ? null : (string) $type;
}

function scoped_location_id(?int $requestedLocationId = null): ?int
{
    $userLocationId = current_user_location_id();
    return $userLocationId !== null ? $userLocationId : $requestedLocationId;
}

function require_location_access(int $locationId): void
{
    $userLocationId = current_user_location_id();
    if ($userLocationId !== null && $userLocationId !== $locationId) {
        throw new RuntimeException('غير مسموح لك بتسجيل أو عرض بيانات خارج الفرع الخاص بك.');
    }
}

function location_row(int $locationId): ?array
{
    $stmt = pdo()->prepare('SELECT * FROM locations WHERE id = ? AND is_active = 1');
    $stmt->execute([$locationId]);
    return $stmt->fetch() ?: null;
}

function require_location_type(int $locationId, array $allowedTypes, string $message): array
{
    $location = location_row($locationId);
    if (!$location || !in_array($location['type'], $allowedTypes, true)) {
        throw new RuntimeException($message);
    }
    return $location;
}

function can_manage(): bool
{
    return has_permission('inventory');
}

function login_user(string $username, string $password): bool
{
    $stmt = pdo()->prepare('SELECT u.*, r.code AS role_code FROM users u JOIN roles r ON r.id = u.role_id WHERE u.username = ? AND u.is_active = 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['permissions']);
    log_audit((int) $user['id'], 'login', 'user', (int) $user['id'], 'تسجيل دخول المستخدم: ' . $user['username']);
    return true;
}

function logout_user(): void
{
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        log_audit((int) $userId, 'logout', 'user', (int) $userId, 'تسجيل خروج');
    }
    $_SESSION = [];
    session_destroy();
}
